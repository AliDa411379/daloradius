<?php
/**
 * DaloRADIUS Balance System - Core Functions
 * 
 * This library provides all core functions for the prepaid balance system.
 * All payments MUST go through these functions to ensure proper balance tracking.
 * 
 * @author DaloRADIUS Balance System
 * @version 1.0
 */

// ================== CONFIGURATION ==================
define('BALANCE_MIN_LIMIT', -300000.00);
define('BALANCE_MAX_PAYMENT', 300000.00);
define('BALANCE_MAX_INVOICE', 300000.00);

// ================== BALANCE MANAGEMENT ==================

/**
 * Get user balance information
 * 
 * @param mysqli $db Database connection
 * @param string $username Username
 * @return array|false User balance data or false on error
 */
function get_user_balance($db, $username) {
    $username = $db->real_escape_string($username);
    
    $sql = "SELECT 
                u.id,
                u.username,
                u.money_balance,
                u.total_invoices_amount,
                u.last_balance_update,
                u.planName,
                bp.planCost,
                bp.planRecurring
            FROM userbillinfo u
            LEFT JOIN billing_plans bp ON u.planName = bp.planName
            WHERE u.username = '$username'";
    
    $result = $db->query($sql);
    if (!$result || $result->num_rows === 0) {
        return false;
    }
    
    return $result->fetch_assoc();
}

/**
 * Get user balance by user_id
 * 
 * @param mysqli $db Database connection
 * @param int $user_id User ID
 * @return array|false User balance data or false on error
 */
function get_user_balance_by_id($db, $user_id) {
    $user_id = intval($user_id);
    
    $sql = "SELECT 
                u.id,
                u.username,
                u.money_balance,
                u.total_invoices_amount,
                u.last_balance_update,
                u.planName,
                bp.planCost,
                bp.planRecurring
            FROM userbillinfo u
            LEFT JOIN billing_plans bp ON u.planName = bp.planName
            WHERE u.id = $user_id";
    
    $result = $db->query($sql);
    if (!$result || $result->num_rows === 0) {
        return false;
    }
    
    return $result->fetch_assoc();
}

/**
 * Add money to user balance (Credit)
 * 
 * @param mysqli $db Database connection
 * @param string $username Username
 * @param float $amount Amount to add
 * @param string $description Transaction description
 * @param string $created_by Who created this transaction
 * @param string $ip_address IP address of requester
 * @return array Result with success status and message
 */
function add_balance($db, $username, $amount, $description = 'Balance credit', $created_by = 'system', $ip_address = null) {
    $amount = floatval($amount);
    
    if ($amount <= 0) {
        return ['success' => false, 'message' => 'Amount must be greater than 0'];
    }
    
    if ($amount > BALANCE_MAX_PAYMENT) {
        return ['success' => false, 'message' => 'Amount exceeds maximum limit of ' . BALANCE_MAX_PAYMENT];
    }
    
    $user = get_user_balance($db, $username);
    if (!$user) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    $balance_before = floatval($user['money_balance']);
    $balance_after = $balance_before + $amount;
    
    // Start transaction
    $db->begin_transaction();
    
    try {
        // Update balance
        $username_esc = $db->real_escape_string($username);
        $sql = "UPDATE userbillinfo 
                SET money_balance = money_balance + $amount,
                    last_balance_update = NOW()
                WHERE username = '$username_esc'";
        
        if (!$db->query($sql)) {
            throw new Exception('Failed to update balance: ' . $db->error);
        }
        
        // Record in history
        $history_id = record_balance_history(
            $db,
            $user['id'],
            $username,
            'credit',
            $amount,
            $balance_before,
            $balance_after,
            'manual',
            null,
            $description,
            $created_by,
            $ip_address
        );
        
        if (!$history_id) {
            throw new Exception('Failed to record balance history');
        }
        
        $db->commit();
        
        return [
            'success' => true,
            'message' => 'Balance added successfully',
            'balance_before' => $balance_before,
            'balance_after' => $balance_after,
            'amount' => $amount,
            'history_id' => $history_id
        ];
        
    } catch (Exception $e) {
        $db->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Process payment from balance for an invoice
 * 
 * @param mysqli $db Database connection
 * @param int $invoice_id Invoice ID
 * @param float $payment_amount Amount to pay
 * @param string $operator Who is processing the payment
 * @param string $notes Payment notes
 * @param string $ip_address IP address of requester
 * @return array Result with success status and message
 */
function process_balance_payment($db, $invoice_id, $payment_amount, $operator = 'system', $notes = '', $ip_address = null) {
    $invoice_id = intval($invoice_id);
    $payment_amount = floatval($payment_amount);
    
    // Validate payment amount
    if ($payment_amount <= 0) {
        return ['success' => false, 'message' => 'Payment amount must be greater than 0'];
    }
    
    if ($payment_amount > BALANCE_MAX_PAYMENT) {
        return ['success' => false, 'message' => 'Payment amount exceeds maximum limit of ' . BALANCE_MAX_PAYMENT];
    }
    
    // Get invoice details
    $invoice = get_invoice_details($db, $invoice_id);
    if (!$invoice) {
        return ['success' => false, 'message' => 'Invoice not found'];
    }
    
    // Get user balance
    $user = get_user_balance_by_id($db, $invoice['user_id']);
    if (!$user) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    $balance_before = floatval($user['money_balance']);
    $balance_after = $balance_before - $payment_amount;
    
    // Check if payment would exceed minimum balance limit
    if ($balance_after < BALANCE_MIN_LIMIT) {
        return [
            'success' => false,
            'message' => sprintf(
                'Insufficient balance. Current: $%.2f, Payment: $%.2f, Would result in: $%.2f (Limit: $%.2f)',
                $balance_before,
                $payment_amount,
                $balance_after,
                BALANCE_MIN_LIMIT
            ),
            'current_balance' => $balance_before,
            'payment_amount' => $payment_amount,
            'resulting_balance' => $balance_after,
            'minimum_limit' => BALANCE_MIN_LIMIT
        ];
    }
    
    // Check if payment exceeds invoice outstanding amount
    $outstanding = $invoice['total_due'] - $invoice['total_paid'];
    if ($payment_amount > $outstanding + 0.01) { // Allow 1 cent rounding
        return [
            'success' => false,
            'message' => sprintf('Payment amount ($%.2f) exceeds outstanding amount ($%.2f)', $payment_amount, $outstanding)
        ];
    }
    
    // Start transaction
    $db->begin_transaction();
    
    try {
        // 1. Deduct from balance
        $username_esc = $db->real_escape_string($user['username']);
        $sql = "UPDATE userbillinfo 
                SET money_balance = money_balance - $payment_amount,
                    last_balance_update = NOW()
                WHERE id = {$user['id']}";
        
        if (!$db->query($sql)) {
            throw new Exception('Failed to deduct from balance: ' . $db->error);
        }
        
        // 2. Record balance history
        $history_id = record_balance_history(
            $db,
            $user['id'],
            $user['username'],
            'payment',
            -$payment_amount,
            $balance_before,
            $balance_after,
            'invoice',
            $invoice_id,
            "Payment for invoice #{$invoice_id}",
            $operator,
            $ip_address
        );
        
        if (!$history_id) {
            throw new Exception('Failed to record balance history');
        }
        
        // 3. Get Balance Deduction payment type ID
        $type_result = $db->query("SELECT id FROM payment_type WHERE value = 'Balance Deduction' LIMIT 1");
        if (!$type_result || $type_result->num_rows === 0) {
            throw new Exception('Balance Deduction payment type not found. Run migration script 03.');
        }
        $type_row = $type_result->fetch_assoc();
        $payment_type_id = $type_row['id'];
        
        // 4. Create payment record
        $notes_esc = $db->real_escape_string($notes);
        $operator_esc = $db->real_escape_string($operator);
        
        $sql = "INSERT INTO payment (
                    invoice_id, amount, date, type_id, notes, 
                    from_balance, creationdate, creationby
                ) VALUES (
                    $invoice_id, 
                    $payment_amount, 
                    NOW(), 
                    $payment_type_id, 
                    '$notes_esc',
                    1,
                    NOW(), 
                    '$operator_esc'
                )";
        
        if (!$db->query($sql)) {
            throw new Exception('Failed to create payment record: ' . $db->error);
        }
        
        $payment_id = $db->insert_id;
        
        // 5. Calculate total paid for this invoice
        $total_paid_result = $db->query("SELECT COALESCE(SUM(amount), 0) as total FROM payment WHERE invoice_id = $invoice_id");
        $total_paid_row = $total_paid_result->fetch_assoc();
        $total_paid_now = floatval($total_paid_row['total']);
        
        // 6. Update invoice status
        $new_status = 4; // Default: sent
        $status_name = 'Sent';
        
        if ($total_paid_now >= $invoice['total_due'] - 0.01) {
            // Fully paid
            $new_status = 5;
            $status_name = 'Paid';
            
            // Update total_invoices_amount (remove this invoice)
            $sql = "UPDATE userbillinfo 
                    SET total_invoices_amount = GREATEST(0, total_invoices_amount - {$invoice['total_due']})
                    WHERE id = {$user['id']}";
            
            if (!$db->query($sql)) {
                throw new Exception('Failed to update total_invoices_amount: ' . $db->error);
            }
            
        } else if ($total_paid_now > 0) {
            // Partially paid
            $new_status = 6;
            $status_name = 'Partial';
        }
        
        $sql = "UPDATE invoice SET status_id = $new_status WHERE id = $invoice_id";
        if (!$db->query($sql)) {
            throw new Exception('Failed to update invoice status: ' . $db->error);
        }
        
        // 7. Add to billing history
        $plan_id = get_plan_id_by_name($db, $user['planName']);
        $sql = "INSERT INTO billing_history (
                    username, planId, billAmount, billAction, 
                    creationdate, creationby
                ) VALUES (
                    '$username_esc',
                    $plan_id,
                    $payment_amount,
                    'Payment from balance - Invoice #$invoice_id',
                    NOW(),
                    '$operator_esc'
                )";
        
        if (!$db->query($sql)) {
            // Non-critical, log but don't fail
            error_log('Failed to insert billing_history: ' . $db->error);
        }
        
        // 8. Check if user should be reactivated
        check_and_reactivate_user($db, $user['username']);
        
        $db->commit();
        
        return [
            'success' => true,
            'message' => 'Payment processed successfully',
            'payment_id' => $payment_id,
            'invoice_id' => $invoice_id,
            'payment_amount' => $payment_amount,
            'balance_before' => $balance_before,
            'balance_after' => $balance_after,
            'invoice_status' => $status_name,
            'total_paid' => $total_paid_now,
            'total_due' => $invoice['total_due'],
            'outstanding' => $invoice['total_due'] - $total_paid_now
        ];
        
    } catch (Exception $e) {
        $db->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// ================== INVOICE MANAGEMENT ==================

/**
 * Get invoice details with payment information
 * 
 * @param mysqli $db Database connection
 * @param int $invoice_id Invoice ID
 * @return array|false Invoice details or false on error
 */
function get_invoice_details($db, $invoice_id) {
    $invoice_id = intval($invoice_id);
    
    $sql = "SELECT 
                i.id,
                i.user_id,
                i.date,
                i.status_id,
                i.type_id,
                i.notes,
                i.due_date,
                u.username,
                COALESCE(SUM(ii.amount + ii.tax_amount), 0) as total_due,
                (SELECT COALESCE(SUM(p.amount), 0) FROM payment p WHERE p.invoice_id = i.id) as total_paid
            FROM invoice i
            INNER JOIN userbillinfo u ON i.user_id = u.id
            LEFT JOIN invoice_items ii ON i.id = ii.invoice_id
            WHERE i.id = $invoice_id
            GROUP BY i.id";
    
    $result = $db->query($sql);
    if (!$result || $result->num_rows === 0) {
        return false;
    }
    
    return $result->fetch_assoc();
}

/**
 * Get all unpaid invoices for a user
 * 
 * @param mysqli $db Database connection
 * @param string $username Username
 * @return array List of unpaid invoices
 */
function get_unpaid_invoices($db, $username) {
    $username = $db->real_escape_string($username);
    
    $sql = "SELECT 
                i.id,
                i.date,
                i.due_date,
                i.status_id,
                COALESCE(SUM(ii.amount + ii.tax_amount), 0) as total_due,
                (SELECT COALESCE(SUM(p.amount), 0) FROM payment p WHERE p.invoice_id = i.id) as total_paid,
                (COALESCE(SUM(ii.amount + ii.tax_amount), 0) - 
                 (SELECT COALESCE(SUM(p.amount), 0) FROM payment p WHERE p.invoice_id = i.id)) as outstanding
            FROM invoice i
            INNER JOIN userbillinfo u ON i.user_id = u.id
            LEFT JOIN invoice_items ii ON i.id = ii.invoice_id
            WHERE u.username = '$username'
              AND i.status_id NOT IN (5) -- Not paid
            GROUP BY i.id
            HAVING outstanding > 0.01
            ORDER BY i.date ASC";
    
    $result = $db->query($sql);
    if (!$result) {
        return [];
    }
    
    $invoices = [];
    while ($row = $result->fetch_assoc()) {
        $invoices[] = $row;
    }
    
    return $invoices;
}

// ================== HISTORY AND LOGGING ==================

/**
 * Record balance transaction in history
 * 
 * @param mysqli $db Database connection
 * @param int $user_id User ID
 * @param string $username Username
 * @param string $transaction_type Type: credit, debit, payment, refund, adjustment
 * @param float $amount Transaction amount
 * @param float $balance_before Balance before transaction
 * @param float $balance_after Balance after transaction
 * @param string $reference_type Reference type (invoice, payment, manual, api, etc)
 * @param int $reference_id Reference ID
 * @param string $description Human-readable description
 * @param string $created_by Who created this
 * @param string $ip_address IP address
 * @return int|false History record ID or false on error
 */
function record_balance_history($db, $user_id, $username, $transaction_type, $amount, 
                                $balance_before, $balance_after, $reference_type = null, 
                                $reference_id = null, $description = null, 
                                $created_by = 'system', $ip_address = null) {
    
    $user_id = intval($user_id);
    $username = $db->real_escape_string($username);
    $transaction_type = $db->real_escape_string($transaction_type);
    $amount = floatval($amount);
    $balance_before = floatval($balance_before);
    $balance_after = floatval($balance_after);
    $reference_type = $reference_type ? "'" . $db->real_escape_string($reference_type) . "'" : 'NULL';
    $reference_id = $reference_id ? intval($reference_id) : 'NULL';
    $description = $description ? "'" . $db->real_escape_string($description) . "'" : 'NULL';
    $created_by = $db->real_escape_string($created_by);
    $ip_address = $ip_address ? "'" . $db->real_escape_string($ip_address) . "'" : 'NULL';
    
    $sql = "INSERT INTO user_balance_history (
                user_id, username, transaction_type, amount,
                balance_before, balance_after, reference_type,
                reference_id, description, created_by, ip_address, created_at
            ) VALUES (
                $user_id, '$username', '$transaction_type', $amount,
                $balance_before, $balance_after, $reference_type,
                $reference_id, $description, '$created_by', $ip_address, NOW()
            )";
    
    if (!$db->query($sql)) {
        error_log('Failed to record balance history: ' . $db->error);
        return false;
    }
    
    return $db->insert_id;
}

/**
 * Get balance transaction history for a user
 * 
 * @param mysqli $db Database connection
 * @param string $username Username
 * @param int $limit Number of records to return
 * @param int $offset Offset for pagination
 * @return array Transaction history
 */
function get_balance_history($db, $username, $limit = 50, $offset = 0) {
    $username = $db->real_escape_string($username);
    $limit = intval($limit);
    $offset = intval($offset);
    
    $sql = "SELECT 
                id,
                transaction_type,
                amount,
                balance_before,
                balance_after,
                reference_type,
                reference_id,
                description,
                created_by,
                created_at
            FROM user_balance_history
            WHERE username = '$username'
            ORDER BY created_at DESC, id DESC
            LIMIT $limit OFFSET $offset";
    
    $result = $db->query($sql);
    if (!$result) {
        return [];
    }
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    
    return $history;
}

// ================== USER MANAGEMENT ==================

/**
 * Check if user should be reactivated and reactivate if eligible
 * 
 * @param mysqli $db Database connection
 * @param string $username Username
 * @return bool True if reactivated or not blocked, false on error
 */
function check_and_reactivate_user($db, $username) {
    $username_esc = $db->real_escape_string($username);
    
    // Check if user is in block_user group
    $sql = "SELECT COUNT(*) as is_blocked 
            FROM radusergroup 
            WHERE username = '$username_esc' 
              AND groupname = 'block_user'";
    
    $result = $db->query($sql);
    if (!$result) {
        return false;
    }
    
    $row = $result->fetch_assoc();
    if ($row['is_blocked'] == 0) {
        // Not blocked, nothing to do
        return true;
    }
    
    // Check if user has any unpaid invoices
    $unpaid = get_unpaid_invoices($db, $username_esc);
    
    if (count($unpaid) == 0) {
        // All invoices paid, reactivate user
        $sql = "DELETE FROM radusergroup 
                WHERE username = '$username_esc' 
                  AND groupname = 'block_user'";
        
        if (!$db->query($sql)) {
            error_log("Failed to reactivate user $username: " . $db->error);
            return false;
        }
        
        error_log("User $username reactivated - all invoices paid");
        return true;
    }
    
    return false;
}

/**
 * Get plan ID by plan name
 * 
 * @param mysqli $db Database connection
 * @param string $plan_name Plan name
 * @return int Plan ID or 0 if not found
 */
function get_plan_id_by_name($db, $plan_name) {
    if (empty($plan_name)) {
        return 0;
    }
    
    $plan_name = $db->real_escape_string($plan_name);
    $sql = "SELECT id FROM billing_plans WHERE planName = '$plan_name' LIMIT 1";
    $result = $db->query($sql);
    
    if (!$result || $result->num_rows === 0) {
        return 0;
    }
    
    $row = $result->fetch_assoc();
    return intval($row['id']);
}

// ================== VALIDATION FUNCTIONS ==================

/**
 * Validate payment amount
 * 
 * @param float $amount Payment amount
 * @return array Result with valid status and message
 */
function validate_payment_amount($amount) {
    $amount = floatval($amount);
    
    if ($amount <= 0) {
        return ['valid' => false, 'message' => 'Payment amount must be greater than 0'];
    }
    
    if ($amount > BALANCE_MAX_PAYMENT) {
        return ['valid' => false, 'message' => 'Payment amount exceeds maximum limit of $' . number_format(BALANCE_MAX_PAYMENT, 2)];
    }
    
    return ['valid' => true, 'message' => 'Valid amount'];
}

/**
 * Validate balance operation
 * 
 * @param float $current_balance Current balance
 * @param float $amount Amount to deduct
 * @return array Result with valid status and message
 */
function validate_balance_operation($current_balance, $amount) {
    $current_balance = floatval($current_balance);
    $amount = floatval($amount);
    $new_balance = $current_balance - $amount;
    
    if ($new_balance < BALANCE_MIN_LIMIT) {
        return [
            'valid' => false,
            'message' => sprintf(
                'Insufficient balance. Current: $%.2f, Deduction: $%.2f, Would result in: $%.2f (Minimum allowed: $%.2f)',
                $current_balance,
                $amount,
                $new_balance,
                BALANCE_MIN_LIMIT
            )
        ];
    }
    
    return ['valid' => true, 'message' => 'Valid operation'];
}

?>