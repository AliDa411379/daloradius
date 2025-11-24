<?php
/**
 * DaloRADIUS Balance System - User Reactivation Script
 * 
 * This script reactivates users who have paid all their invoices.
 * Can be run hourly or daily to automatically restore service.
 * 
 * Reactivation Logic:
 * - User is currently in block_user group (suspended)
 * - All invoices are paid (status = 5) OR outstanding amount = 0
 * - Remove from block_user group
 * 
 * Schedule: Run hourly or after each payment
 * Crontab: 0 * * * * /usr/bin/php /path/to/reactivate_paid_users.php
 * 
 * @author DaloRADIUS Balance System
 * @version 1.0
 */

// ================== CONFIGURATION ==================
define('LOG_FILE', __DIR__ . '/../../var/logs/reactivation.log');
define('LOCK_FILE', __DIR__ . '/../../var/scripts/reactivation.lock');

// Database credentials - CHANGE THESE!
define('DB_HOST', '172.30.16.200');
define('DB_USER', 'bassel');
define('DB_PASS', 'bassel_password');
define('DB_NAME', 'radius');

// ================== FUNCTIONS ==================

function log_message($msg, $level = 'INFO') {
    $log_dir = dirname(LOG_FILE);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    file_put_contents(LOG_FILE, date('[Y-m-d H:i:s]') . " [$level] $msg\n", FILE_APPEND);
    echo date('[Y-m-d H:i:s]') . " [$level] $msg\n";
}

/**
 * Get unpaid invoice amount for user
 */
function get_unpaid_amount($db, $username) {
    $username_esc = $db->real_escape_string($username);
    
    $sql = "SELECT 
                COALESCE(SUM(ii.amount + ii.tax_amount), 0) - 
                COALESCE((SELECT SUM(p.amount) FROM payment p WHERE p.invoice_id = i.id), 0) as outstanding
            FROM invoice i
            LEFT JOIN invoice_items ii ON i.id = ii.invoice_id
            INNER JOIN userbillinfo u ON i.user_id = u.id
            WHERE u.username = '$username_esc'
              AND i.status_id NOT IN (5) -- Not fully paid
            GROUP BY u.username
            HAVING outstanding > 0.01";
    
    $result = $db->query($sql);
    if (!$result || $result->num_rows === 0) {
        return 0;
    }
    
    $row = $result->fetch_assoc();
    return floatval($row['outstanding']);
}

/**
 * Reactivate user by removing from block_user group
 */
function reactivate_user($db, $username) {
    $username_esc = $db->real_escape_string($username);
    
    // Remove from block_user group
    $sql = "DELETE FROM radusergroup 
            WHERE username = '$username_esc' AND groupname = 'block_user'";
    
    if (!$db->query($sql)) {
        return ['success' => false, 'message' => 'Failed to remove from block_user group: ' . $db->error];
    }
    
    $affected = $db->affected_rows;
    
    if ($affected === 0) {
        return ['success' => true, 'message' => 'User not in block_user group', 'was_blocked' => false];
    }
    
    // Log reactivation in billing_history
    $sql = "INSERT INTO billing_history (
                username, planId, billAmount, billAction,
                creationdate, creationby
            ) VALUES (
                '$username_esc', 0, 0, 
                'User reactivated - All invoices paid',
                NOW(), 'system'
            )";
    
    if (!$db->query($sql)) {
        log_message("Failed to log reactivation in billing_history for $username: " . $db->error, 'WARNING');
    }
    
    return ['success' => true, 'message' => 'User reactivated successfully', 'was_blocked' => true];
}

// ================== MAIN SCRIPT ==================

try {
    // Check for lock file
    if (file_exists(LOCK_FILE)) {
        $pid = file_get_contents(LOCK_FILE);
        log_message("Script already running with PID $pid", 'WARNING');
        exit(0);
    }
    
    file_put_contents(LOCK_FILE, getmypid());
    log_message("========================================");
    log_message("=== USER REACTIVATION CHECK START ===");
    log_message("========================================");
    log_message("Date: " . date('Y-m-d H:i:s'));
    
    // Database connection
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        throw new Exception("DB Connection Failed: " . $db->connect_error);
    }
    $db->set_charset("utf8mb4");
    log_message("Connected to database");
    
    // Get all suspended users (in block_user group)
    $sql = "SELECT DISTINCT
                rug.username,
                u.id,
                u.money_balance,
                u.total_invoices_amount
            FROM radusergroup rug
            INNER JOIN userbillinfo u ON rug.username = u.username
            WHERE rug.groupname = 'block_user'
            ORDER BY rug.username";
    
    $result = $db->query($sql);
    if (!$result) {
        throw new Exception("Failed to fetch suspended users: " . $db->error);
    }
    
    $total_users = $result->num_rows;
    log_message("Found $total_users suspended users");
    
    $reactivated = 0;
    $not_reactivated = 0;
    $failed = 0;
    
    while ($user = $result->fetch_assoc()) {
        $username = $user['username'];
        $balance = floatval($user['money_balance']);
        $total_invoices = floatval($user['total_invoices_amount']);
        
        try {
            // Check for unpaid invoices
            $unpaid_amount = get_unpaid_amount($db, $username);
            
            if ($unpaid_amount > 0) {
                log_message(sprintf(
                    "NOT_REACTIVATED: $username - Still has unpaid invoices (Amount: $%.2f, Balance: $%.2f)",
                    $unpaid_amount,
                    $balance
                ), 'INFO');
                $not_reactivated++;
                continue;
            }
            
            // All invoices paid - reactivate
            $reactivate_result = reactivate_user($db, $username);
            
            if ($reactivate_result['success']) {
                if ($reactivate_result['was_blocked']) {
                    log_message(sprintf(
                        "REACTIVATED: $username - All invoices paid (Balance: $%.2f)",
                        $balance
                    ), 'INFO');
                    $reactivated++;
                } else {
                    log_message("SKIP: $username - Not in block_user group", 'INFO');
                    $not_reactivated++;
                }
            } else {
                throw new Exception($reactivate_result['message']);
            }
            
        } catch (Exception $e) {
            log_message("FAILED: $username - " . $e->getMessage(), 'ERROR');
            $failed++;
        }
    }
    
    $db->close();
    
    log_message("========================================");
    log_message("=== USER REACTIVATION CHECK COMPLETE ===");
    log_message("========================================");
    log_message("Total Suspended Users: $total_users");
    log_message("Reactivated: $reactivated");
    log_message("Not Reactivated (Still Unpaid): $not_reactivated");
    log_message("Failed: $failed");
    log_message("========================================");
    
} catch (Exception $e) {
    log_message("FATAL ERROR: " . $e->getMessage(), 'ERROR');
    exit(1);
} finally {
    // Remove lock file
    if (file_exists(LOCK_FILE)) {
        unlink(LOCK_FILE);
    }
}

exit(0);
?>