<?php
/**
 * DaloRADIUS Balance System - User Suspension Script
 * 
 * This script suspends users with insufficient balance to pay unpaid invoices.
 * Runs on the 4th of each month (7 days after billing on 26th).
 * 
 * Suspension Logic:
 * - User has unpaid invoices with due date in the past
 * - User's money_balance + minimum_limit < total_unpaid_amount
 * - 7+ days have passed since invoice creation
 * 
 * Schedule: Run on 4th of each month
 * Crontab: 0 1 4 * * /usr/bin/php /path/to/suspend_unpaid_users.php
 * 
 * @author DaloRADIUS Balance System
 * @version 1.0
 */

// ================== CONFIGURATION ==================
define('LOG_FILE', __DIR__ . '/../../var/logs/suspension.log');
define('LOCK_FILE', __DIR__ . '/../../var/scripts/suspension.lock');

// Database credentials - CHANGE THESE!
define('DB_HOST', '172.30.16.200');
define('DB_USER', 'bassel');
define('DB_PASS', 'bassel_password');
define('DB_NAME', 'radius');

// Balance limits
define('BALANCE_MIN_LIMIT', -300000.00);
define('GRACE_PERIOD_DAYS', 7);

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
 * Check if user is already suspended
 */
function is_user_suspended($db, $username) {
    $username_esc = $db->real_escape_string($username);
    $sql = "SELECT COUNT(*) as count FROM radusergroup 
            WHERE username = '$username_esc' AND groupname = 'block_user'";
    $result = $db->query($sql);
    if (!$result) {
        return false;
    }
    $row = $result->fetch_assoc();
    return ($row['count'] > 0);
}

/**
 * Suspend user by adding to block_user group
 */
function suspend_user($db, $username, $reason = '') {
    $username_esc = $db->real_escape_string($username);
    
    // Check if already suspended
    if (is_user_suspended($db, $username_esc)) {
        return ['success' => true, 'message' => 'Already suspended', 'already_suspended' => true];
    }
    
    // Get priority for block_user group
    $priority_result = $db->query("SELECT priority FROM radusergroup 
                                   WHERE groupname = 'block_user' LIMIT 1");
    $priority = 0;
    if ($priority_result && $priority_result->num_rows > 0) {
        $row = $priority_result->fetch_assoc();
        $priority = intval($row['priority']);
    }
    
    // Add to block_user group
    $sql = "INSERT INTO radusergroup (username, groupname, priority) 
            VALUES ('$username_esc', 'block_user', $priority)";
    
    if (!$db->query($sql)) {
        return ['success' => false, 'message' => 'Failed to add to block_user group: ' . $db->error];
    }
    
    // Log suspension in billing_history
    $reason_esc = $db->real_escape_string($reason);
    $sql = "INSERT INTO billing_history (
                username, planId, billAmount, billAction,
                creationdate, creationby
            ) VALUES (
                '$username_esc', 0, 0, 
                'User suspended - $reason_esc',
                NOW(), 'system'
            )";
    
    if (!$db->query($sql)) {
        log_message("Failed to log suspension in billing_history for $username: " . $db->error, 'WARNING');
    }
    
    return ['success' => true, 'message' => 'User suspended successfully', 'already_suspended' => false];
}

/**
 * Get unpaid invoice amount for user
 */
function get_unpaid_amount($db, $user_id) {
    $user_id = intval($user_id);
    
    $sql = "SELECT 
                COALESCE(SUM(ii.amount + ii.tax_amount), 0) - 
                COALESCE((SELECT SUM(p.amount) FROM payment p WHERE p.invoice_id = i.id), 0) as outstanding
            FROM invoice i
            LEFT JOIN invoice_items ii ON i.id = ii.invoice_id
            WHERE i.user_id = $user_id
              AND i.status_id NOT IN (5) -- Not fully paid
            GROUP BY i.user_id
            HAVING outstanding > 0.01";
    
    $result = $db->query($sql);
    if (!$result || $result->num_rows === 0) {
        return 0;
    }
    
    $row = $result->fetch_assoc();
    return floatval($row['outstanding']);
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
    log_message("=== USER SUSPENSION CHECK START ===");
    log_message("========================================");
    log_message("Date: " . date('Y-m-d H:i:s'));
    log_message("Grace Period: " . GRACE_PERIOD_DAYS . " days");
    
    // Database connection
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        throw new Exception("DB Connection Failed: " . $db->connect_error);
    }
    $db->set_charset("utf8mb4");
    log_message("Connected to database");
    
    // Get users with unpaid invoices past due date
    $grace_date = date('Y-m-d', strtotime('-' . GRACE_PERIOD_DAYS . ' days'));
    
    $sql = "SELECT DISTINCT
                u.id,
                u.username,
                u.money_balance,
                u.total_invoices_amount,
                u.planName
            FROM userbillinfo u
            INNER JOIN invoice i ON u.id = i.user_id
            WHERE i.status_id NOT IN (5) -- Not fully paid
              AND (i.due_date IS NULL OR i.due_date <= CURDATE())
              AND i.date <= '$grace_date'
            ORDER BY u.username";
    
    $result = $db->query($sql);
    if (!$result) {
        throw new Exception("Failed to fetch users: " . $db->error);
    }
    
    $total_users = $result->num_rows;
    log_message("Found $total_users users with overdue unpaid invoices");
    
    $suspended = 0;
    $already_suspended = 0;
    $not_suspended = 0;
    $failed = 0;
    
    while ($user = $result->fetch_assoc()) {
        $username = $user['username'];
        $balance = floatval($user['money_balance']);
        $total_invoices = floatval($user['total_invoices_amount']);
        
        try {
            // Get actual unpaid amount
            $unpaid_amount = get_unpaid_amount($db, $user['id']);
            
            if ($unpaid_amount <= 0) {
                log_message("SKIP: $username - No unpaid invoices (may have been paid)", 'INFO');
                $not_suspended++;
                continue;
            }
            
            // Check if user can pay with current balance
            $balance_after_payment = $balance - $unpaid_amount;
            
            if ($balance_after_payment >= BALANCE_MIN_LIMIT) {
                log_message(sprintf(
                    "NOT_SUSPENDED: $username - Has sufficient balance to pay (Balance: $%.2f, Unpaid: $%.2f, After: $%.2f)",
                    $balance,
                    $unpaid_amount,
                    $balance_after_payment
                ), 'INFO');
                $not_suspended++;
                continue;
            }
            
            // User cannot pay - suspend
            $reason = sprintf(
                "Insufficient balance to pay overdue invoices (Balance: $%.2f, Unpaid: $%.2f, Required: $%.2f)",
                $balance,
                $unpaid_amount,
                $balance - BALANCE_MIN_LIMIT
            );
            
            $suspend_result = suspend_user($db, $username, $reason);
            
            if ($suspend_result['success']) {
                if ($suspend_result['already_suspended']) {
                    log_message("ALREADY_SUSPENDED: $username - $reason", 'INFO');
                    $already_suspended++;
                } else {
                    log_message("SUSPENDED: $username - $reason", 'INFO');
                    $suspended++;
                }
            } else {
                throw new Exception($suspend_result['message']);
            }
            
        } catch (Exception $e) {
            log_message("FAILED: $username - " . $e->getMessage(), 'ERROR');
            $failed++;
        }
    }
    
    $db->close();
    
    log_message("========================================");
    log_message("=== USER SUSPENSION CHECK COMPLETE ===");
    log_message("========================================");
    log_message("Total Users Checked: $total_users");
    log_message("Newly Suspended: $suspended");
    log_message("Already Suspended: $already_suspended");
    log_message("Not Suspended (Sufficient Balance): $not_suspended");
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