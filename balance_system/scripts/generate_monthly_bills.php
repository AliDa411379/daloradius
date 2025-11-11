<?php
/**
 * DaloRADIUS Balance System - Monthly Bill Generation Script
 * 
 * This script generates monthly invoices for all active users on the 26th of each month.
 * Invoices are created based on user's billing plan with due date set to 4th of next month.
 * 
 * Schedule: Run on 26th of each month at midnight
 * Crontab: 0 0 26 * * /usr/bin/php /path/to/generate_monthly_bills.php
 * 
 * @author DaloRADIUS Balance System
 * @version 1.0
 */

// ================== CONFIGURATION ==================
define('LOG_FILE', __DIR__ . '/../../var/logs/monthly_billing.log');
define('LOCK_FILE', __DIR__ . '/../../var/scripts/monthly_billing.lock');

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
 * Generate invoice for a user
 */
function generate_invoice($db, $user) {
    $user_id = intval($user['id']);
    $username = $db->real_escape_string($user['username']);
    $plan_cost = floatval($user['planCost']);
    $plan_name = $db->real_escape_string($user['planName']);
    
    // Validate plan cost
    if ($plan_cost <= 0) {
        throw new Exception("Plan cost is 0 or negative");
    }
    
    if ($plan_cost > 300000) {
        throw new Exception("Plan cost exceeds maximum limit of 300,000");
    }
    
    // Calculate dates
    $invoice_date = date('Y-m-d');
    $due_date = date('Y-m-d', strtotime('first day of next month +3 days')); // 4th of next month
    
    // Get invoice status ID for "sent"
    $status_result = $db->query("SELECT id FROM invoice_status WHERE value = 'sent' LIMIT 1");
    if (!$status_result || $status_result->num_rows === 0) {
        $status_result = $db->query("SELECT id FROM invoice_status WHERE value = 'open' LIMIT 1");
    }
    if (!$status_result || $status_result->num_rows === 0) {
        $status_id = 4; // Default to 4
    } else {
        $row = $status_result->fetch_assoc();
        $status_id = $row['id'];
    }
    
    // Get invoice type ID for "subscription"
    $type_result = $db->query("SELECT id FROM invoice_type WHERE value = 'Subscription' LIMIT 1");
    if (!$type_result || $type_result->num_rows === 0) {
        $type_id = 1; // Default to 1
    } else {
        $row = $type_result->fetch_assoc();
        $type_id = $row['id'];
    }
    
    // Start transaction
    $db->begin_transaction();
    
    try {
        // Create invoice
        $sql = "INSERT INTO invoice (
                    user_id, date, status_id, type_id, notes, due_date,
                    creationdate, creationby, updatedate, updateby
                ) VALUES (
                    $user_id, '$invoice_date', $status_id, $type_id, 
                    'Monthly subscription invoice for $plan_name', '$due_date',
                    NOW(), 'system', NULL, NULL
                )";
        
        if (!$db->query($sql)) {
            throw new Exception('Failed to create invoice: ' . $db->error);
        }
        
        $invoice_id = $db->insert_id;
        
        // Create invoice item
        $sql = "INSERT INTO invoice_items (
                    invoice_id, amount, tax_amount, notes,
                    creationdate, creationby, updatedate, updateby
                ) VALUES (
                    $invoice_id, $plan_cost, 0.00, 'Monthly plan: $plan_name',
                    NOW(), 'system', NULL, NULL
                )";
        
        if (!$db->query($sql)) {
            throw new Exception('Failed to create invoice items: ' . $db->error);
        }
        
        // Update user's total_invoices_amount
        $sql = "UPDATE userbillinfo 
                SET total_invoices_amount = total_invoices_amount + $plan_cost
                WHERE id = $user_id";
        
        if (!$db->query($sql)) {
            throw new Exception('Failed to update total_invoices_amount: ' . $db->error);
        }
        
        // Add to billing history
        $plan_id = get_plan_id($db, $plan_name);
        $sql = "INSERT INTO billing_history (
                    username, planId, billAmount, billAction,
                    creationdate, creationby
                ) VALUES (
                    '$username', $plan_id, $plan_cost, 
                    'Monthly invoice generated - Invoice #$invoice_id',
                    NOW(), 'system'
                )";
        
        if (!$db->query($sql)) {
            // Non-critical, log but don't fail
            log_message("Failed to insert billing_history for $username: " . $db->error, 'WARNING');
        }
        
        // Record in balance history
        $balance = floatval($user['money_balance']);
        $sql = "INSERT INTO user_balance_history (
                    user_id, username, transaction_type, amount,
                    balance_before, balance_after, reference_type,
                    reference_id, description, created_by, created_at
                ) VALUES (
                    $user_id, '$username', 'invoice_created', 0,
                    $balance, $balance, 'invoice',
                    $invoice_id, 'Monthly invoice generated: $$plan_cost', 
                    'system', NOW()
                )";
        
        if (!$db->query($sql)) {
            log_message("Failed to record balance history for $username: " . $db->error, 'WARNING');
        }
        
        $db->commit();
        
        return [
            'success' => true,
            'invoice_id' => $invoice_id,
            'amount' => $plan_cost,
            'due_date' => $due_date
        ];
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function get_plan_id($db, $plan_name) {
    $plan_name = $db->real_escape_string($plan_name);
    $result = $db->query("SELECT id FROM billing_plans WHERE planName = '$plan_name' LIMIT 1");
    if (!$result || $result->num_rows === 0) {
        return 0;
    }
    $row = $result->fetch_assoc();
    return intval($row['id']);
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
    log_message("=== MONTHLY BILL GENERATION START ===");
    log_message("========================================");
    log_message("Date: " . date('Y-m-d H:i:s'));
    
    // Database connection
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        throw new Exception("DB Connection Failed: " . $db->connect_error);
    }
    $db->set_charset("utf8mb4");
    log_message("Connected to database");
    
    // Get all active users with billing plans
    $sql = "SELECT 
                u.id,
                u.username,
                u.planName,
                u.money_balance,
                u.total_invoices_amount,
                bp.planCost,
                bp.planRecurring,
                bp.planActive
            FROM userbillinfo u
            INNER JOIN billing_plans bp ON u.planName = bp.planName
            WHERE u.planName IS NOT NULL 
              AND u.planName != ''
              AND bp.planActive = 1
              AND bp.planRecurring = 'Monthly'
              AND bp.planCost > 0
            ORDER BY u.username";
    
    $result = $db->query($sql);
    if (!$result) {
        throw new Exception("Failed to fetch users: " . $db->error);
    }
    
    $total_users = $result->num_rows;
    log_message("Found $total_users users with monthly billing plans");
    
    $processed = 0;
    $skipped = 0;
    $failed = 0;
    $total_amount = 0;
    
    while ($user = $result->fetch_assoc()) {
        $username = $user['username'];
        
        try {
            // Check if invoice already generated this month
            $check_sql = sprintf(
                "SELECT COUNT(*) as count FROM invoice 
                 WHERE user_id = %d 
                 AND DATE_FORMAT(date, '%%Y-%%m') = '%s'",
                $user['id'],
                date('Y-m')
            );
            $check_result = $db->query($check_sql);
            $check_row = $check_result->fetch_assoc();
            
            if ($check_row['count'] > 0) {
                log_message("SKIP: $username - Invoice already generated this month", 'INFO');
                $skipped++;
                continue;
            }
            
            // Generate invoice
            $invoice_result = generate_invoice($db, $user);
            
            log_message(sprintf(
                "SUCCESS: $username - Invoice #%d created for $%.2f (Due: %s)",
                $invoice_result['invoice_id'],
                $invoice_result['amount'],
                $invoice_result['due_date']
            ), 'INFO');
            
            $processed++;
            $total_amount += $invoice_result['amount'];
            
        } catch (Exception $e) {
            log_message("FAILED: $username - " . $e->getMessage(), 'ERROR');
            $failed++;
        }
    }
    
    $db->close();
    
    log_message("========================================");
    log_message("=== MONTHLY BILL GENERATION COMPLETE ===");
    log_message("========================================");
    log_message("Total Users Found: $total_users");
    log_message("Invoices Created: $processed");
    log_message("Skipped (Already Generated): $skipped");
    log_message("Failed: $failed");
    log_message("Total Amount Billed: $" . number_format($total_amount, 2));
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