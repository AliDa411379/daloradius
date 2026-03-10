<?php
/**
 * API: Payment Refund/Reversal
 * 
 * Allows refunding or reversing agent payments
 * Records in payment_refunds table
 * Restores user balance
 * 
 * @package DaloRADIUS
 * @subpackage API
 */

header('Content-Type: application/json');

// Include required files
require_once('../../common/includes/config_read.php');
require_once('../../common/includes/db_open.php');
require_once('../../common/library/BalanceManager.php');

function apiSendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message, 'error_code' => strtoupper(str_replace(' ', '_', $message))]);
    exit;
}

function apiSendSuccess($data) {
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiSendError('Method not allowed', 405);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    apiSendError('Invalid JSON input');
}

// Validate required fields
$requiredFields = ['payment_reference_type', 'payment_reference_id', 'refund_amount', 'refund_reason', 'performed_by'];
foreach ($requiredFields as $field) {
    if (!isset($input[$field])) {
        apiSendError("Missing required field: $field");
    }
}

$paymentType = trim($input['payment_reference_type']);
$paymentId = intval($input['payment_reference_id']);
$refundAmount = floatval($input['refund_amount']);
$refundReason = trim($input['refund_reason']);
$performedBy = trim($input['performed_by']);
$isPartial = isset($input['partial']) ? (bool)$input['partial'] : false;

// Validate refund amount
if ($refundAmount <= 0) {
    apiSendError('Refund amount must be greater than zero');
}

try {
    // Convert to mysqli
    $mysqli = new mysqli(
        $configValues['CONFIG_DB_HOST'],
        $configValues['CONFIG_DB_USER'],
        $configValues['CONFIG_DB_PASS'],
        $configValues['CONFIG_DB_NAME']
    );
    
    if ($mysqli->connect_error) {
        apiSendError('Database connection failed', 500);
    }
    
    // Begin transaction
    $mysqli->begin_transaction();
    
    // Get original payment details
    $payment = null;
    $userId = null;
    $username = null;
    $originalAmount = 0;
    
    if ($paymentType === 'agent_payment') {
        $sql = sprintf("SELECT * FROM agent_payments WHERE id = %d", $paymentId);
        $result = $mysqli->query($sql);
        
        if (!$result || $result->num_rows === 0) {
            throw new Exception('Payment not found');
        }
        
        $payment = $result->fetch_assoc();
        $userId = $payment['user_id'];
        $username = $payment['username'];
        $originalAmount = floatval($payment['amount']);
        
    } else {
        throw new Exception('Unsupported payment type for refund');
    }
    
    // Validate refund amount
    if ($refundAmount > $originalAmount) {
        throw new Exception(sprintf(
            'Refund amount ($%.2f) cannot exceed original payment ($%.2f)',
            $refundAmount,
            $originalAmount
        ));
    }
    
    // Check if already refunded
    $sql = sprintf(
        "SELECT COUNT(*) as count FROM payment_refunds 
         WHERE original_payment_type = '%s' AND original_payment_id = %d",
        $mysqli->real_escape_string($paymentType),
        $paymentId
    );
    
    $result = $mysqli->query($sql);
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0 && !$isPartial) {
        throw new Exception('Payment already refunded');
    }
    
    // Get current user balance
    $balanceManager = new BalanceManager($mysqli);
    $balanceBefore = $balanceManager->getBalance($userId);
    
    if ($balanceBefore === false) {
        throw new Exception('User not found');
    }
    
    // Add refund amount back to balance
    $balanceResult = $balanceManager->addBalance(
        $userId,
        $username,
        $refundAmount,
        $performedBy,
        "Refund: $refundReason",
        'refund',
        null // Will update with refund_id later
    );
    
    if (!$balanceResult['success']) {
        throw new Exception($balanceResult['message']);
    }
    
    $balanceAfter = $balanceResult['new_balance'];
    
    // Record refund
    $sql = sprintf(
        "INSERT INTO payment_refunds (
            original_payment_type, original_payment_id, user_id, username,
            refund_amount, original_amount, is_partial, refund_reason,
            user_balance_before, user_balance_after, refund_date,
            performed_by, ip_address
        ) VALUES (
            '%s', %d, %d, '%s',
            %.2f, %.2f, %d, '%s',
            %.2f, %.2f, NOW(),
            '%s', '%s'
        )",
        $mysqli->real_escape_string($paymentType),
        $paymentId,
        $userId,
        $mysqli->real_escape_string($username),
        $refundAmount,
        $originalAmount,
        $isPartial ? 1 : 0,
        $mysqli->real_escape_string($refundReason),
        $balanceBefore,
        $balanceAfter,
        $mysqli->real_escape_string($performedBy),
        $_SERVER['REMOTE_ADDR']
    );
    
    if (!$mysqli->query($sql)) {
        throw new Exception('Failed to record refund: ' . $mysqli->error);
    }
    
    $refundId = $mysqli->insert_id;
    
    // Update balance history with refund_id
    $mysqli->query(sprintf(
        "UPDATE user_balance_history SET reference_id = %d 
         WHERE user_id = %d AND reference_type = 'refund' AND reference_id IS NULL
         ORDER BY created_at DESC LIMIT 1",
        $refundId,
        $userId
    ));
    
    // Log in billing history
    $mysqli->query(sprintf(
        "INSERT INTO billing_history (username, planId, billAmount, billAction, creationdate, creationby)
         VALUES ('%s', 0, %.2f, 'Payment refunded: %s', NOW(), '%s')",
        $mysqli->real_escape_string($username),
        $refundAmount,
        $mysqli->real_escape_string($refundReason),
        $mysqli->real_escape_string($performedBy)
    ));
    
    $mysqli->commit();
    $mysqli->close();
    
    // Success response
    apiSendSuccess([
        'refund_id' => $refundId,
        'original_payment_id' => $paymentId,
        'payment_type' => $paymentType,
        'refund_amount' => $refundAmount,
        'original_amount' => $originalAmount,
        'is_partial' => $isPartial,
        'user_balance_before' => $balanceBefore,
        'user_balance_after' => $balanceAfter,
        'refund_date' => date('Y-m-d H:i:s'),
        'message' => 'Payment refunded successfully'
    ]);
    
} catch (Exception $e) {
    if (isset($mysqli)) {
        $mysqli->rollback();
        $mysqli->close();
    }
    apiSendError($e->getMessage(), 500);
}

