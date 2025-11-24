<?php
/**
 * Radacct Billing Table Management Script
 * 
 * This script provides management functions for the radacct_billing table:
 * - Sync missing data from radacct
 * - Clean up old processed sessions
 * - Reset processing flags
 * - Generate usage reports
 * - Table maintenance
 */

// ================= CONFIGURATION =================
define('LOG_FILE', '/var/www/daloradius/var/logs/billing_table_mgmt.log');

// Database credentials
define('DB_HOST', '172.30.16.200');
define('DB_USER', 'bassel');
define('DB_PASS', 'bassel_password');
define('DB_NAME', 'radius');

// ================= LOGGING =================
function log_message($msg, $level = 'INFO') {
    $log_entry = date('[Y-m-d H:i:s]') . " [$level] [BILLING_MGMT] $msg\n";
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
    echo $log_entry;
}

// ================= MANAGEMENT FUNCTIONS =================

/**
 * Sync missing data from radacct to radacct_billing
 */
function sync_missing_data($db) {
    log_message("=== Syncing missing data from radacct ===");
    
    $sync_query = "
        INSERT INTO radacct_billing (
            radacctid, acctsessionid, acctuniqueid, username, groupname, realm,
            nasipaddress, nasportid, nasporttype, acctstarttime, acctupdatetime,
            acctstoptime, acctinterval, acctsessiontime, acctauthentic,
            connectinfo_start, connectinfo_stop, acctinputoctets, acctoutputoctets,
            calledstationid, callingstationid, acctterminatecause, servicetype,
            framedprotocol, framedipaddress, traffic_mb, session_minutes
        )
        SELECT 
            r.radacctid, r.acctsessionid, r.acctuniqueid, r.username, r.groupname, r.realm,
            r.nasipaddress, r.nasportid, r.nasporttype, r.acctstarttime, r.acctupdatetime,
            r.acctstoptime, r.acctinterval, r.acctsessiontime, r.acctauthentic,
            r.connectinfo_start, r.connectinfo_stop, r.acctinputoctets, r.acctoutputoctets,
            r.calledstationid, r.callingstationid, r.acctterminatecause, r.servicetype,
            r.framedprotocol, r.framedipaddress,
            COALESCE((r.acctinputoctets + r.acctoutputoctets) / 1048576, 0) as traffic_mb,
            COALESCE(r.acctsessiontime / 60, 0) as session_minutes
        FROM radacct r
        LEFT JOIN radacct_billing rb ON r.radacctid = rb.radacctid
        WHERE rb.radacctid IS NULL
    ";
    
    $result = $db->query($sync_query);
    if ($result) {
        $synced_count = $db->affected_rows;
        log_message("Synced $synced_count missing records from radacct");
        return $synced_count;
    } else {
        log_message("Failed to sync missing data: " . $db->error, 'ERROR');
        return false;
    }
}

/**
 * Reset processing flags for specific criteria
 */
function reset_processing_flags($db, $username = null, $since_date = null) {
    log_message("=== Resetting processing flags ===");
    
    $where_conditions = ["processed_for_billing = 1"];
    
    if ($username) {
        $username = $db->real_escape_string($username);
        $where_conditions[] = "username = '$username'";
    }
    
    if ($since_date) {
        $where_conditions[] = "acctstarttime >= '$since_date'";
    }
    
    $reset_query = "
        UPDATE radacct_billing 
        SET processed_for_billing = 0, 
            billing_processed_date = NULL
        WHERE " . implode(' AND ', $where_conditions);
    
    $result = $db->query($reset_query);
    if ($result) {
        $reset_count = $db->affected_rows;
        log_message("Reset processing flags for $reset_count records");
        return $reset_count;
    } else {
        log_message("Failed to reset processing flags: " . $db->error, 'ERROR');
        return false;
    }
}

/**
 * Clean up old processed sessions
 */
function cleanup_old_sessions($db, $days_old = 90, $actually_delete = false) {
    log_message("=== Cleaning up sessions older than $days_old days ===");
    
    $cutoff_date = date('Y-m-d', strtotime("-$days_old days"));
    
    // First, count what would be affected
    $count_query = "
        SELECT COUNT(*) as count 
        FROM radacct_billing 
        WHERE processed_for_billing = 1 
        AND billing_processed_date < '$cutoff_date'
    ";
    
    $result = $db->query($count_query);
    if (!$result) {
        log_message("Failed to count old sessions: " . $db->error, 'ERROR');
        return false;
    }
    
    $count_row = $result->fetch_assoc();
    $session_count = $count_row['count'];
    
    if ($session_count == 0) {
        log_message("No old processed sessions found");
        return 0;
    }
    
    log_message("Found $session_count processed sessions older than $cutoff_date");
    
    if ($actually_delete) {
        $delete_query = "
            DELETE FROM radacct_billing 
            WHERE processed_for_billing = 1 
            AND billing_processed_date < '$cutoff_date'
        ";
        
        if ($db->query($delete_query)) {
            log_message("Deleted $session_count old processed sessions");
            return $session_count;
        } else {
            log_message("Failed to delete old sessions: " . $db->error, 'ERROR');
            return false;
        }
    } else {
        log_message("DRY RUN: Would delete $session_count sessions (use --delete to actually delete)");
        return $session_count;
    }
}

/**
 * Generate usage report
 */
function generate_usage_report($db, $username = null, $days = 30) {
    log_message("=== Generating usage report for last $days days ===");
    
    $since_date = date('Y-m-d', strtotime("-$days days"));
    $where_conditions = ["acctstarttime >= '$since_date'"];
    
    if ($username) {
        $username = $db->real_escape_string($username);
        $where_conditions[] = "username = '$username'";
    }
    
    $report_query = "
        SELECT 
            username,
            COUNT(*) as session_count,
            SUM(session_minutes) as total_minutes,
            SUM(traffic_mb) as total_mb,
            MIN(acctstarttime) as first_session,
            MAX(acctstoptime) as last_session,
            SUM(CASE WHEN processed_for_billing = 1 THEN 1 ELSE 0 END) as processed_sessions,
            SUM(CASE WHEN processed_for_billing = 0 THEN 1 ELSE 0 END) as unprocessed_sessions
        FROM radacct_billing 
        WHERE " . implode(' AND ', $where_conditions) . "
        AND acctstoptime IS NOT NULL
        GROUP BY username
        ORDER BY total_mb DESC
        LIMIT 20
    ";
    
    $result = $db->query($report_query);
    if (!$result) {
        log_message("Failed to generate report: " . $db->error, 'ERROR');
        return false;
    }
    
    echo "\n" . str_repeat("=", 100) . "\n";
    echo "USAGE REPORT - Last $days days" . ($username ? " for user: $username" : "") . "\n";
    echo str_repeat("=", 100) . "\n";
    printf("%-20s %8s %12s %12s %10s %10s %12s %12s\n", 
           "Username", "Sessions", "Minutes", "MB", "Processed", "Pending", "First", "Last");
    echo str_repeat("-", 100) . "\n";
    
    while ($row = $result->fetch_assoc()) {
        printf("%-20s %8d %12.2f %12.2f %10d %10d %12s %12s\n",
               substr($row['username'], 0, 20),
               $row['session_count'],
               $row['total_minutes'],
               $row['total_mb'],
               $row['processed_sessions'],
               $row['unprocessed_sessions'],
               substr($row['first_session'], 5, 11),
               substr($row['last_session'], 5, 11)
        );
    }
    echo str_repeat("=", 100) . "\n\n";
    
    return true;
}

/**
 * Show table statistics
 */
function show_table_stats($db) {
    log_message("=== Table Statistics ===");
    
    $stats_queries = [
        'Total Records' => "SELECT COUNT(*) as count FROM radacct_billing",
        'Processed Records' => "SELECT COUNT(*) as count FROM radacct_billing WHERE processed_for_billing = 1",
        'Unprocessed Records' => "SELECT COUNT(*) as count FROM radacct_billing WHERE processed_for_billing = 0",
        'Records Last 7 Days' => "SELECT COUNT(*) as count FROM radacct_billing WHERE acctstarttime >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
        'Records Last 30 Days' => "SELECT COUNT(*) as count FROM radacct_billing WHERE acctstarttime >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
        'Unique Users' => "SELECT COUNT(DISTINCT username) as count FROM radacct_billing",
        'Avg Session Minutes' => "SELECT AVG(session_minutes) as count FROM radacct_billing WHERE session_minutes > 0",
        'Avg Traffic MB' => "SELECT AVG(traffic_mb) as count FROM radacct_billing WHERE traffic_mb > 0"
    ];
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "RADACCT_BILLING TABLE STATISTICS\n";
    echo str_repeat("=", 60) . "\n";
    
    foreach ($stats_queries as $label => $query) {
        $result = $db->query($query);
        if ($result && $row = $result->fetch_assoc()) {
            $value = $row['count'];
            if (strpos($label, 'Avg') === 0) {
                printf("%-25s: %15.2f\n", $label, $value);
            } else {
                printf("%-25s: %15s\n", $label, number_format($value));
            }
        }
    }
    echo str_repeat("=", 60) . "\n\n";
}

/**
 * Show help information
 */
function show_help() {
    echo "\nRadacct Billing Table Management Script\n";
    echo str_repeat("=", 50) . "\n";
    echo "Usage: php manage_billing_table.php [command] [options]\n\n";
    echo "Commands:\n";
    echo "  sync                    - Sync missing data from radacct\n";
    echo "  reset [username]        - Reset processing flags\n";
    echo "  reset-since [date]      - Reset processing flags since date (YYYY-MM-DD)\n";
    echo "  cleanup [days]          - Show old sessions that can be cleaned (default: 90 days)\n";
    echo "  cleanup [days] --delete - Actually delete old sessions\n";
    echo "  report [username] [days] - Generate usage report (default: 30 days)\n";
    echo "  stats                   - Show table statistics\n";
    echo "  help                    - Show this help\n\n";
    echo "Examples:\n";
    echo "  php manage_billing_table.php sync\n";
    echo "  php manage_billing_table.php reset testuser\n";
    echo "  php manage_billing_table.php reset-since 2024-01-01\n";
    echo "  php manage_billing_table.php cleanup 60 --delete\n";
    echo "  php manage_billing_table.php report testuser 7\n";
    echo "  php manage_billing_table.php stats\n\n";
}

// ================= MAIN SCRIPT =================
try {
    if ($argc < 2) {
        show_help();
        exit(0);
    }
    
    $command = $argv[1];
    
    // Database connection
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        throw new Exception("DB Connection Failed: " . $db->connect_error);
    }
    
    // Check if billing table exists
    $table_check = $db->query("SHOW TABLES LIKE 'radacct_billing'");
    if (!$table_check || $table_check->num_rows == 0) {
        throw new Exception("radacct_billing table not found. Please run setup_radacct_billing.sql first.");
    }
    
    switch ($command) {
        case 'sync':
            sync_missing_data($db);
            break;
            
        case 'reset':
            $username = isset($argv[2]) ? $argv[2] : null;
            reset_processing_flags($db, $username);
            break;
            
        case 'reset-since':
            $since_date = isset($argv[2]) ? $argv[2] : null;
            if (!$since_date) {
                echo "Error: Please provide a date (YYYY-MM-DD)\n";
                exit(1);
            }
            reset_processing_flags($db, null, $since_date);
            break;
            
        case 'cleanup':
            $days = isset($argv[2]) ? intval($argv[2]) : 90;
            $delete = isset($argv[3]) && $argv[3] === '--delete';
            cleanup_old_sessions($db, $days, $delete);
            break;
            
        case 'report':
            $username = isset($argv[2]) ? $argv[2] : null;
            $days = isset($argv[3]) ? intval($argv[3]) : 30;
            generate_usage_report($db, $username, $days);
            break;
            
        case 'stats':
            show_table_stats($db);
            break;
            
        case 'help':
        default:
            show_help();
            break;
    }
    
    $db->close();
    
} catch (Exception $e) {
    log_message("Fatal error: " . $e->getMessage(), 'ERROR');
    exit(1);
}
?>