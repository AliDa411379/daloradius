<?php
/**
 * Enhanced Mikrotik Attributes Synchronization Script for daloRADIUS
 * 
 * This script converts billing plan data from userbillinfo to Mikrotik-compatible
 * RADIUS attributes in the radreply table. Enhanced version with:
 * - Integration with radacct_billing table for better performance
 * - Usage of shared mikrotik_integration_functions library
 * - Improved error handling and logging
 * - Support for batch processing and selective updates
 * 
 * Usage:
 *   php mikrotik_attributes_sync_v2.php [username]  # Sync specific user
 *   php mikrotik_attributes_sync_v2.php --all       # Sync all users
 *   php mikrotik_attributes_sync_v2.php --help      # Show help
 */

// ================= CONFIGURATION =================
define('LOCK_FILE', '/var/www/daloradius/var/scripts/mikrotik_sync_v2.lock');
define('LOG_FILE', '/var/www/daloradius/var/logs/mikrotik_sync_v2.log');

// Database credentials
define('DB_HOST', '172.30.16.200');
define('DB_USER', 'bassel');
define('DB_PASS', 'bassel_password');
define('DB_NAME', 'radius');

// Include the shared Mikrotik integration functions
require_once(__DIR__ . '/mikrotik_integration_functions.php');

// ================= ENHANCED LOGGING =================
function log_message($msg, $level = 'INFO') {
    $log_entry = date('[Y-m-d H:i:s]') . " [$level] [SYNC_V2] $msg\n";
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
    
    // Also output to console if running interactively
    if (php_sapi_name() === 'cli') {
        echo $log_entry;
    }
}

// ================= HELPER FUNCTIONS =================

/**
 * Show usage information
 */
function show_help() {
    echo "Enhanced Mikrotik Attributes Synchronization Script\n";
    echo "Usage:\n";
    echo "  php mikrotik_attributes_sync_v2.php [options] [username]\n\n";
    echo "Options:\n";
    echo "  --all          Sync all users with billing info\n";
    echo "  --active-only  Only sync users with active plans\n";
    echo "  --force        Force sync even if attributes seem current\n";
    echo "  --dry-run      Show what would be done without making changes\n";
    echo "  --help         Show this help message\n\n";
    echo "Examples:\n";
    echo "  php mikrotik_attributes_sync_v2.php --all\n";
    echo "  php mikrotik_attributes_sync_v2.php user123\n";
    echo "  php mikrotik_attributes_sync_v2.php --active-only --dry-run\n";
}

/**
 * Check if billing table is available and functional
 */
function check_billing_table($db) {
    $table_check = $db->query("SHOW TABLES LIKE 'radacct_billing'");
    if (!$table_check || $table_check->num_rows == 0) {
        log_message("radacct_billing table not found - using standard tables only", 'WARNING');
        return false;
    }
    
    // Check if table has data
    $data_check = $db->query("SELECT COUNT(*) as count FROM radacct_billing LIMIT 1");
    if ($data_check) {
        $result = $data_check->fetch_assoc();
        log_message("radacct_billing table available with {$result['count']} records");
        return true;
    }
    
    return false;
}

/**
 * Get users to process based on command line arguments
 */
function get_users_to_process($db, $options) {
    $where_conditions = ["u.username IS NOT NULL", "u.username != ''"];
    
    if ($options['active_only']) {
        $where_conditions[] = "p.planActive = 1";
    }
    
    if ($options['specific_user']) {
        $username = $db->real_escape_string($options['specific_user']);
        $where_conditions[] = "u.username = '$username'";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $query = "
        SELECT 
            u.username,
            u.planName,
            u.timebank_balance,
            u.traffic_balance,
            u.updatedate,
            p.planType,
            p.planTimeBank,
            p.planTrafficTotal,
            p.planActive
        FROM userbillinfo u
        LEFT JOIN billing_plans p ON u.planName = p.planName
        WHERE $where_clause
        ORDER BY u.username
    ";
    
    log_message("Query: $query");
    return $db->query($query);
}

/**
 * Check if user attributes need updating
 */
function needs_attribute_update($db, $username, $force = false) {
    if ($force) {
        return true;
    }
    
    // Check when attributes were last updated
    $username_escaped = $db->real_escape_string($username);
    $check_query = "
        SELECT MAX(UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(
            COALESCE(
                (SELECT updatedate FROM userbillinfo WHERE username = '$username_escaped'),
                '1970-01-01'
            )
        )) as seconds_since_update
    ";
    
    $result = $db->query($check_query);
    if ($result) {
        $row = $result->fetch_assoc();
        $seconds_since_update = $row['seconds_since_update'];
        
        // Update if more than 5 minutes since last billing update
        return $seconds_since_update > 300;
    }
    
    return true; // Update if we can't determine
}

// ================= MAIN SCRIPT =================

// Parse command line arguments
$options = [
    'all' => false,
    'active_only' => false,
    'force' => false,
    'dry_run' => false,
    'help' => false,
    'specific_user' => null
];

$args = array_slice($argv, 1);
foreach ($args as $arg) {
    switch ($arg) {
        case '--all':
            $options['all'] = true;
            break;
        case '--active-only':
            $options['active_only'] = true;
            break;
        case '--force':
            $options['force'] = true;
            break;
        case '--dry-run':
            $options['dry_run'] = true;
            break;
        case '--help':
            $options['help'] = true;
            break;
        default:
            if (!str_starts_with($arg, '--')) {
                $options['specific_user'] = $arg;
            }
    }
}

// Show help if requested
if ($options['help']) {
    show_help();
    exit(0);
}

// Validate arguments
if (!$options['all'] && !$options['specific_user']) {
    echo "Error: You must specify either --all or a specific username\n";
    show_help();
    exit(1);
}

try {
    // Check for lock file (skip in dry-run mode)
    if (!$options['dry_run'] && file_exists(LOCK_FILE)) {
        log_message("Script already running", 'WARNING');
        exit(0);
    }
    
    if (!$options['dry_run']) {
        file_put_contents(LOCK_FILE, getmypid());
    }
    
    log_message("=== Starting Enhanced Mikrotik Attributes Synchronization ===");
    if ($options['dry_run']) {
        log_message("DRY RUN MODE - No changes will be made");
    }

    // Database connection
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        throw new Exception("DB Connection Failed: " . $db->connect_error);
    }
    log_message("Connected to Database");

    // Check billing table availability
    $billing_table_available = check_billing_table($db);

    // Get users to process
    $users_result = get_users_to_process($db, $options);
    if (!$users_result) {
        throw new Exception("Users query failed: " . $db->error);
    }

    $processed_count = 0;
    $skipped_count = 0;
    $error_count = 0;

    while ($user = $users_result->fetch_assoc()) {
        $username = $user['username'];
        
        try {
            // Check if update is needed
            if (!needs_attribute_update($db, $username, $options['force'])) {
                log_message("Skipping $username - attributes are current");
                $skipped_count++;
                continue;
            }
            
            log_message("--- Processing user: $username ---");
            
            if ($options['dry_run']) {
                // In dry-run mode, just show what would be done
                $traffic_balance = !empty($user['traffic_balance']) ? floatval($user['traffic_balance']) : 
                                  (!empty($user['planTrafficTotal']) ? floatval($user['planTrafficTotal']) : 0);
                
                $time_balance = !empty($user['timebank_balance']) ? floatval($user['timebank_balance']) : 
                               (!empty($user['planTimeBank']) ? floatval($user['planTimeBank']) : 0);
                
                log_message("DRY RUN: Would sync $username - Traffic: {$traffic_balance}MB, Time: {$time_balance}min");
                
                // Show what attributes would be set
                if (stripos($user['planType'], 'Traffic') !== false || $traffic_balance > 0) {
                    $traffic_attrs = mikrotik_convert_traffic($traffic_balance);
                    log_message("DRY RUN: Would set Mikrotik-Total-Limit-Gigawords = {$traffic_attrs['gigawords']}");
                    log_message("DRY RUN: Would set Mikrotik-Total-Limit = {$traffic_attrs['bytes']}");
                } else {
                    log_message("DRY RUN: Would set unlimited traffic (0/0)");
                }
                
                if (stripos($user['planType'], 'Time') !== false || $time_balance > 0) {
                    $session_timeout = mikrotik_convert_time($time_balance);
                    log_message("DRY RUN: Would set Session-Timeout = $session_timeout");
                } else {
                    log_message("DRY RUN: Would set unlimited time (0)");
                }
                
                $processed_count++;
            } else {
                // Actually sync the user attributes
                if (mikrotik_sync_user_attributes($db, $username)) {
                    $processed_count++;
                    log_message("Successfully processed user $username");
                } else {
                    $error_count++;
                    log_message("Failed to process user $username", 'ERROR');
                }
            }
            
        } catch (Exception $e) {
            log_message("Error processing user $username: " . $e->getMessage(), 'ERROR');
            $error_count++;
        }
    }

    $db->close();
    
    $mode_text = $options['dry_run'] ? " (DRY RUN)" : "";
    log_message("=== Enhanced Mikrotik Attributes Synchronization Completed$mode_text ===");
    log_message("Processed: $processed_count users, Skipped: $skipped_count, Errors: $error_count");
    
    if ($billing_table_available) {
        log_message("Used enhanced radacct_billing table for optimized performance");
    }

} catch (Exception $e) {
    log_message("Fatal error: " . $e->getMessage(), 'ERROR');
    exit(1);
} finally {
    // Remove lock file
    if (!$options['dry_run'] && file_exists(LOCK_FILE)) {
        unlink(LOCK_FILE);
    }
}

log_message("Script execution completed");
?>