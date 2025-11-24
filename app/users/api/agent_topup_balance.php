<?php
/**
 * API: Agent Balance Topup
 * 
 * Allows agents to add balance to user accounts
 * Records transaction in agent_payments table
 * 
 * @package DaloRADIUS
 * @subpackage API
 */

// Include authentication and config
require_once('auth.php');

// Include required files
require_once('../../app/common/includes/config_read.php');
require_once('../../app/common/includes/db_open.php');
require_once('../../app/common/library/BalanceManager.php');

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
$requiredFields = ['agent_id', 'username', 'amount'];
foreach ($requiredFields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        apiSendError("Missing required field: $field");
    }
}

$agentId = intval($input['agent_id']);
$username = trim($input['username']);
$amount = floatval($input['amount']);
$paymentMethod = isset($input['payment_method']) ? trim($input['payment_method']) : 'cash';
$notes = isset($input['notes']) ? trim($input['notes']) : 'Balance topup via agent';

// Validate amount
if ($amount <= 0) {
    apiSendError('Amount must be greater than zero');
}

if ($amount > 300000) {
    apiSendError('Amount exceeds maximum limit of 300,000');
}

try {
    // Verify agent exists
    $sql = sprintf("SELECT id, name FROM agents WHERE id = %d AND is_deleted = 0", $agentId);
    $result = $dbSocket->query($sql);
    
    if (!$result || $result->rowCount() === 0) {
        apiSendError('Agent not found');
    }
    
    $agent = $result->fetch(PDO::FETCH_ASSOC);
    
    // Get user info
    $sql = sprintf("SELECT id, username, money_balance FROM userbillinfo WHERE username = '%s'", 
                   $dbSocket->escapeSimple($username));
    $result = $dbSocket->query($sql);
    
    if (!$result || $result->rowCount() === 0) {
        apiSendError('User not found');
    }
    
    $user = $result->fetch(PDO::FETCH_ASSOC);
    $userId = $user['id'];
    $balanceBefore = floatval($user['money_balance']);
    
    // Use BalanceManager to add balance
    // Convert PDO to mysqli for BalanceManager
    $mysqli = new mysqli(
        $configValues['CONFIG_DB_HOST'],
        $configValues['CONFIG_DB_USER'],
        $configValues['CONFIG_DB_PASS'],
        $configValues['CONFIG_DB_NAME']
    );
    
    if ($mysqli->connect_error) {
        apiSendError('Database connection failed', 500);
    }
    
    $balanceManager = new BalanceManager($mysqli);
    
    // Add balance
    $result = $balanceManager->addBalance(
        $userId,
        $username,
        $amount,
        "agent_{$agentId}",
        $notes,
        'agent_payment',
        null // Will update with payment_id later
    );
    
    if (!$result['success']) {
        apiSendError($result['message'], 500);
    }
    
    $balanceAfter = $result['new_balance'];
    
    // Record in agent_payments table
    $sql = sprintf(
        "INSERT INTO agent_payments (
            agent_id, user_id, username, payment_type, amount,
            payment_date, payment_method, reference_type, reference_id,
            user_balance_before, user_balance_after, notes, created_by, created_at, ip_address
        ) VALUES (
            %d, %d, '%s', 'balance_topup', %.2f,
            NOW(), '%s', 'topup', NULL,
            %.2f, %.2f, '%s', 'agent_%d', NOW(), '%s'
        )",
        $agentId,
        $userId,
        $mysqli->real_escape_string($username),
        $amount,
        $mysqli->real_escape_string($paymentMethod),
        $balanceBefore,
        $balanceAfter,
        $mysqli->real_escape_string($notes),
        $agentId,
        $_SERVER['REMOTE_ADDR']
    );
    
    if (!$mysqli->query($sql)) {
        apiSendError('Failed to record agent payment', 500);
    }
    
    $paymentId = $mysqli->insert_id;
    
    // Update balance history with payment_id
    $mysqli->query(sprintf(
        "UPDATE user_balance_history SET reference_id = %d 
         WHERE user_id = %d AND reference_type = 'agent_payment' AND reference_id IS NULL
         ORDER BY created_at DESC LIMIT 1",
        $paymentId,
        $userId
    ));
    
    $mysqli->close();
    
    // Success response
    apiSendSuccess([
        'payment_id' => $paymentId,
        'username' => $username,
        'amount' => $amount,
        'balance_before' => $balanceBefore,
        'new_balance' => $balanceAfter,
        'agent_name' => $agent['name'],
        'payment_date' => date('Y-m-d H:i:s'),
        'message' => 'Balance topup successful'
    ]);
    
} catch (Exception $e) {
    apiSendError('Internal server error: ' . $e->getMessage(), 500);
}

