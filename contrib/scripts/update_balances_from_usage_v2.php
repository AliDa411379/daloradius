<?php
/**
 * Enhanced Balance Update Script with Billing Table Support
 * 
 * This version uses the radacct_billing table for improved performance
 * and includes session processing tracking to avoid double-counting.
 * 
 * Features:
 * - Uses optimized radacct_billing table
 * - Tracks processed sessions to avoid double-counting
 * - Better performance with pre-calculated fields
 * - Supports both single user and batch processing
 * - Archives processed sessions for cleanup
 */

// ================= CONFIGURATION =================
define('LOCK_FILE', '/var/www/daloradius/var/scripts/balance_update_v2.lock');
define('LOG_FILE', '/var/www/daloradius/var/logs/balance_update_v2.log');

// Database credentials
define('DB_HOST', '172.30.16.200');
define('DB_USER', 'bassel');
define('DB_PASS', 'bassel_password');
define('DB_NAME', 'radius');

// Constants
define('BYTES_PER_MB', 1048576);

// Include Mikrotik integration functions
require_once(__DIR__ . '/mikrotik_integration_functions.php');

// ================= LOGGING =================
function log_message($msg, $level = 'INFO') {
    $log_entry = date('[Y-m-d H:i:s]') . " [$level] [BALANCE_V2] $msg\n";
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
}

// ================= ENHANCED USAGE CALCULATION =================

/**
 * Get user usage from billing table with processing tracking
 * @param mysqli $db Database connection
 * @param string $username Username
 * @param string $since_date Optional start date
 * @param bool $mark_processed Whether to mark sessions as processed
 * @return array|false Usage data or false on error
 */
function get_user_usage_from_billing($db, $username, $since_date = null, $mark_processed = true) {
    $username = $db->real_escape_string($username);
    
    // Get user's last billing date if not provided
    if (!$since_date) {
        $billing_query = "SELECT lastbill FROM userbillinfo WHERE username='$username'";
        $billing_result = $db->query($billing_query);
        if ($billing_result && $row = $billing_result->fetch_assoc()) {
            $since_date = ($row['lastbill'] && $row['lastbill'] != '0000-00-00') ? 
                         $row['lastbill'] : date('Y-m-01');
        } else {
            $since_date = date('Y-m-01');
        }
    }
    
    log_message("Calculating usage for $username since $since_date");
    
    // Query unprocessed sessions from billing table
    $usage_query = "
        SELECT 
            COUNT(*) as session_count,
            SUM(COALESCE(session_minutes, acctsessiontime/60, 0)) as total_minutes,
            SUM(COALESCE(traffic_mb, (acctinputoctets + acctoutputoctets)/1048576, 0)) as total_mb,
            SUM(COALESCE(acctinputoctets, 0)) as total_input_bytes,
            SUM(COALESCE(acctoutputoctets, 0)) as total_output_bytes,
            MIN(acctstarttime) as first_session,
            MAX(acctstoptime) as last_session
        FROM radacct_billing 
        WHERE username='$username' 
        AND acctstarttime >= '$since_date'
        AND acctstoptime IS NOT NULL
        AND processed_for_billing = 0
    ";
    
    $result = $db->query($usage_query);
    if (!$result) {
        log_message("Failed to get usage for user $username: " . $db->error, 'ERROR');
        return false;
    }
    
    $usage = $result->fetch_assoc();
    
    // Mark sessions as processed if requested
    if ($mark_processed && $usage['session_count'] > 0) {
        $mark_query = "
            UPDATE radacct_billing 
            SET processed_for_billing = 1, 
                billing_processed_date = NOW()
            WHERE username='$username' 
            AND acctstarttime >= '$since_date'
            AND acctstoptime IS NOT NULL
            AND processed_for_billing = 0
        ";
        
        if ($db->query($mark_query)) {
            log_message("Marked {$usage['session_count']} sessions as processed for $username");
        } else {
            log_message("Failed to mark sessions as processed for $username: " . $db->error, 'WARNING');
        }
    }
    
    return [
        'session_count' => (int)($usage['session_count'] ?: 0),
        'total_minutes' => round(floatval($usage['total_minutes'] ?: 0), 2),
        'total_mb' => round(floatval($usage['total_mb'] ?: 0), 2),
        'total_seconds' => round(floatval($usage['total_minutes'] ?: 0) * 60),
        'total_bytes' => round(floatval($usage['total_mb'] ?: 0) * BYTES_PER_MB),
        'input_bytes' => (int)($usage['total_input_bytes'] ?: 0),
        'output_bytes' => (int)($usage['total_output_bytes'] ?: 0),
        'since_date' => $since_date,
        'first_session' => $usage['first_session'],
        'last_session' => $usage['last_session']
    ];
}

/**
 * Update user balances based on billing table usage
 * @param mysqli $db Database connection
 * @param string $username Username
 * @param bool $force_recalculate Force recalculation of all sessions
 * @return bool Success status
 */
function update_user_balances_v2($db, $username, $force_recalculate = false) {
    log_message("--- Processing user: $username ---");
    
    // Get user plan information
    $user_plan = mikrotik_get_user_plan($db, $username);
    if (!$user_plan) {
        log_message("User $username not found in billing info", 'WARNING');
        return false;
    }
    
    // If force recalculate, reset processed flags
    if ($force_recalculate) {
        $reset_query = "UPDATE radacct_billing SET processed_for_billing = 0 WHERE username='" . 
                      $db->real_escape_string($username) . "'";
        $db->query($reset_query);
        log_message("Reset processed flags for $username (force recalculate)");
    }
    
    // Get usage from billing table
    $usage = get_user_usage_from_billing($db, $username);
    if ($usage === false) {
        return false;
    }
    
    if ($usage['session_count'] == 0) {
        log_message("No unprocessed sessions found for $username");
        return true; // Not an error, just no new usage
    }
    
    log_message("Found {$usage['session_count']} unprocessed sessions for $username: " .
               "{$usage['total_minutes']} min, {$usage['total_mb']} MB");
    
    // Calculate new balances
    $update_fields = [];
    $current_time = !empty($user_plan['timebank_balance']) ? 
                   floatval($user_plan['timebank_balance']) : 
                   floatval($user_plan['planTimeBank'] ?: 0);
    
    $current_traffic = !empty($user_plan['traffic_balance']) ? 
                      floatval($user_plan['traffic_balance']) : 
                      floatval($user_plan['planTrafficTotal'] ?: 0);
    
    // Update time balance if plan has time limits
    if (stripos($user_plan['planType'], 'Time') !== false) {
        $new_time_balance = max(0, $current_time - $usage['total_minutes']);
        $update_fields[] = "timebank_balance = $new_time_balance";
        log_message("Time balance for $username: $current_time - {$usage['total_minutes']} = $new_time_balance min");
    }
    
    // Update traffic balance if plan has traffic limits
    if (stripos($user_plan['planType'], 'Traffic') !== false) {
        $new_traffic_balance = max(0, $current_traffic - $usage['total_mb']);
        $update_fields[] = "traffic_balance = $new_traffic_balance";
        log_message("Traffic balance for $username: $current_traffic - {$usage['total_mb']} = $new_traffic_balance MB");
    }
    
    // Update balances in database
    if (!empty($update_fields)) {
        $username_escaped = $db->real_escape_string($username);
        $update_sql = "UPDATE userbillinfo SET " . implode(', ', $update_fields) . 
                     ", updatedate = NOW() WHERE username = '$username_escaped'";
        
        if (!$db->query($update_sql)) {
            log_message("Failed to update balances for $username: " . $db->error, 'ERROR');
            return false;
        }
        
        log_message("Updated balances for $username");
        
        // Sync Mikrotik attributes with new balances
        if (!mikrotik_sync_user_attributes($db, $username)) {
            log_message("Failed to sync Mikrotik attributes for $username", 'WARNING');
        }
    }
    
    return true;
}

/**
 * Clean up old processed sessions (archive/delete)
 * @param mysqli $db Database connection
 * @param int $days_old Days old to consider for cleanup
 * @param bool $delete_after_archive Whether to delete after archiving
 * @return bool Success status
 */
function cleanup_processed_sessions($db, $days_old = 90, $delete_after_archive = false) {
    log_message("Starting cleanup of processed sessions older than $days_old days");
    
    $cutoff_date = date('Y-m-d', strtotime("-$days_old days"));
    
    // Count sessions to be cleaned
    $count_query = "
        SELECT COUNT(*) as count 
        FROM radacct_billing 
        WHERE processed_for_billing = 1 
        AND billing_processed_date < '$cutoff_date'
    ";
    
    $result = $db->query($count_query);
    if (!$result) {
        log_message("Failed to count sessions for cleanup: " . $db->error, 'ERROR');
        return false;
    }
    
    $count_row = $result->fetch_assoc();
    $session_count = $count_row['count'];
    
    if ($session_count == 0) {
        log_message("No old processed sessions found for cleanup");
        return true;
    }
    
    log_message("Found $session_count processed sessions to clean up");
    
    if ($delete_after_archive) {
        // Delete old processed sessions
        $delete_query = "
            DELETE FROM radacct_billing 
            WHERE processed_for_billing = 1 
            AND billing_processed_date < '$cutoff_date'
        ";
        
        if ($db->query($delete_query)) {
            log_message("Deleted $session_count old processed sessions");
            return true;
        } else {
            log_message("Failed to delete old sessions: " . $db->error, 'ERROR');
            return false;
        }
    } else {
        log_message("Cleanup simulation: would process $session_count sessions (delete_after_archive=false)");
        return true;
    }
}

// ================= MAIN SCRIPT =================
try {
    // Check for lock file
    if (file_exists(LOCK_FILE)) {
        log_message("Script already running", 'WARNING');
        exit(0);
    }
    file_put_contents(LOCK_FILE, getmypid());
    
    log_message("=== Starting Enhanced Balance Update (v2) ===");
    
    // Database connection
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        throw new Exception("DB Connection Failed: " . $db->connect_error);
    }
    log_message("Connected to Database");
    
    // Check if billing table exists
    $table_check = $db->query("SHOW TABLES LIKE 'radacct_billing'");
    if (!$table_check || $table_check->num_rows == 0) {
        throw new Exception("radacct_billing table not found. Please run setup_radacct_billing.sql first.");
    }
    
    // Handle command line arguments
    if (isset($argv[1])) {
        // Single user mode
        $username = $argv[1];
        $force_recalc = isset($argv[2]) && $argv[2] === '--force';
        
        log_message("Processing single user: $username" . ($force_recalc ? " (force recalculate)" : ""));
        
        if (update_user_balances_v2($db, $username, $force_recalc)) {
            log_message("Successfully processed user $username");
        } else {
            log_message("Failed to process user $username", 'ERROR');
            exit(1);
        }
    } else {
        // Batch mode - process all active users
        log_message("Processing all active users");
        
        $users_query = "
            SELECT DISTINCT u.username 
            FROM userbillinfo u
            INNER JOIN billing_plans p ON u.planName = p.planName
            WHERE p.planActive = 'yes'
            AND u.username IS NOT NULL 
            AND u.username != ''
        ";
        
        $users_result = $db->query($users_query);
        if (!$users_result) {
            throw new Exception("Users query failed: " . $db->error);
        }
        
        $processed_count = 0;
        $error_count = 0;
        
        while ($user_row = $users_result->fetch_assoc()) {
            $username = $user_row['username'];
            
            if (update_user_balances_v2($db, $username)) {
                $processed_count++;
            } else {
                $error_count++;
            }
        }
        
        log_message("=== Batch Processing Completed: $processed_count users processed, $error_count errors ===");
        
        // Optional cleanup of old processed sessions
        if (isset($argv[1]) && $argv[1] === '--cleanup') {
            cleanup_processed_sessions($db, 90, false); // Don't delete by default
        }
    }
    
    $db->close();
    
} catch (Exception $e) {
    log_message("Fatal error: " . $e->getMessage(), 'ERROR');
    exit(1);
} finally {
    if (file_exists(LOCK_FILE)) {
        unlink(LOCK_FILE);
    }
}

log_message("Enhanced balance update completed");
?>