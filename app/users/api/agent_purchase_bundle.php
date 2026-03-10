<?php
/**
 * API: Agent Purchase Bundle
 * 
 * Allows agents to purchase and auto-activate bundles for users
 * Uses BalanceManager, BundleManager, and RadiusAccessManager
 * 
 * @package DaloRADIUS
 * @subpackage API
 */

header('Content-Type: application/json');

// Include required files
require_once('../../common/includes/config_read.php');
require_once('../../common/includes/db_open.php');
require_once('../../common/library/BalanceManager.php');
require_once('../../common/library/BundleManager.php');
require_once('../../common/library/RadiusAccessManager.php');

// Helper functions
function apiSendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
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
$requiredFields = ['agent_id', 'username', 'plan_id'];
foreach ($requiredFields as $field) {
    if (!isset($input[$field])) {
        apiSendError("Missing required field: $field");
    }
}

$agentId = intval($input['agent_id']);
$username = trim($input['username']);
$planId = intval($input['plan_id']);
$paymentMethod = isset($input['payment_method']) ? trim($input['payment_method']) : 'cash';

try {
    // Convert PDO to mysqli
    $mysqli = new mysqli(
        $configValues['CONFIG_DB_HOST'],
        $configValues['CONFIG_DB_USER'],
        $configValues['CONFIG_DB_PASS'],
        $configValues['CONFIG_DB_NAME']
    );
    
    if ($mysqli->connect_error) {
        apiSendError('Database connection failed', 500);
    }
    
    // Verify agent exists
    $sql = sprintf("SELECT id, name FROM agents WHERE id = %d", $agentId);
    $result = $mysqli->query($sql);
    
    if (!$result || $result->num_rows === 0) {
        apiSendError('Agent not found');
    }
    
    $agent = $result->fetch_assoc();
    
    // Get user info
    $sql = sprintf("SELECT id, username, money_balance, planName FROM userbillinfo WHERE username = '%s'", 
                   $mysqli->real_escape_string($username));
    $result = $mysqli->query($sql);
    
    if (!$result || $result->num_rows === 0) {
        apiSendError('User not found');
    }
    
    $user = $result->fetch_assoc();
    $userId = $user['id'];
    $currentPlan = $user['planName'];
    
    // Get bundle plan details
    $sql = sprintf("SELECT * FROM billing_plans WHERE id = %d AND is_bundle = 1", $planId);
    $result = $mysqli->query($sql);
    
    if (!$result || $result->num_rows === 0) {
        apiSendError('Bundle plan not found');
    }
    
    $plan = $result->fetch_assoc();
    $bundleCost = floatval($plan['planCost']);
    $planName = $plan['planName'];
    
    // Initialize managers
    $bundleManager = new BundleManager($mysqli);

    // Purchase bundle (auto-activates, deducts balance, sets RADIUS attributes)
    // BundleManager handles its own transaction internally - do NOT wrap in outer transaction
    // (nested begin_transaction() causes implicit commits in MySQL, breaking rollback)
    $bundleResult = $bundleManager->purchaseBundle(
        $userId,
        $username,
        $planId,
        null, // Will update with agent_payment_id later
        "agent_{$agentId}"
    );

    if (!$bundleResult['success']) {
        apiSendError($bundleResult['message']);
    }

    $bundleId = $bundleResult['bundle_id'];
    $expiryDate = $bundleResult['expiry_date'];
    $newBalance = $bundleResult['new_balance'];

    // Record in agent_payments (after bundle transaction committed successfully)
    $sql = sprintf(
        "INSERT INTO agent_payments (
            agent_id, user_id, username, payment_type, amount,
            payment_date, payment_method, reference_type, reference_id,
            user_balance_before, user_balance_after, notes, created_by, created_at, ip_address
        ) VALUES (
            %d, %d, '%s', 'bundle_purchase', %.2f,
            NOW(), '%s', 'bundle', %d,
            %.2f, %.2f, 'Bundle purchase: %s', 'agent_%d', NOW(), '%s'
        )",
        $agentId,
        $userId,
        $mysqli->real_escape_string($username),
        $bundleCost,
        $mysqli->real_escape_string($paymentMethod),
        $bundleId,
        $newBalance + $bundleCost, // Balance before
        $newBalance,
        $mysqli->real_escape_string($planName),
        $agentId,
        $_SERVER['REMOTE_ADDR']
    );

    if (!$mysqli->query($sql)) {
        apiSendError('Failed to record agent payment', 500);
    }

    $agentPaymentId = $mysqli->insert_id;

    // Update bundle with agent_payment_id
    $mysqli->query(sprintf(
        "UPDATE user_bundles SET agent_payment_id = %d WHERE id = %d",
        $agentPaymentId,
        $bundleId
    ));

    // NOTE: Do NOT call $radiusManager->grantAccess() here.
    // BundleManager::purchaseBundle() already calls activateBundleRadius() which sets:
    // - Expiration in radcheck (from bundle_validity_days)
    // - radusergroup (remove disabled/block, add plan group)
    // - Session-Timeout, Mikrotik-Rate-Limit, Mikrotik-Total-Limit
    // Calling grantAccess() after would overwrite/delete the Expiration via setMikrotikAttributesForPlan().

    // Get actual RADIUS group name from billing_plans_profiles
    $groupResult = $mysqli->query(sprintf(
        "SELECT GROUP_CONCAT(DISTINCT profile_name ORDER BY profile_name SEPARATOR ', ') AS group_names
         FROM billing_plans_profiles WHERE plan_name = '%s'",
        $mysqli->real_escape_string($planName)
    ));
    $groupName = ($groupResult && $row = $groupResult->fetch_assoc()) ? $row['group_names'] : $planName;

    $mysqli->close();

    // Success response
    apiSendSuccess([
        'bundle_id' => $bundleId,
        'plan_name' => $planName,
        'group_name' => $groupName,
        'amount_charged' => $bundleCost,
        'new_balance' => $newBalance,
        'expiry_date' => $expiryDate,
        'expiry_date_formatted' => date('d M Y H:i', strtotime($expiryDate)),
        'agent_name' => $agent['name'],
        'agent_payment_id' => $agentPaymentId,
        'radius_log' => isset($bundleResult['radius_log']) ? $bundleResult['radius_log'] : [],
        'message' => 'Bundle purchased and activated successfully'
    ]);

} catch (Exception $e) {
    if (isset($mysqli)) {
        $mysqli->close();
    }
    apiSendError('Internal server error: ' . $e->getMessage(), 500);
}

