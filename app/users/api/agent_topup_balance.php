<?php
/**
 * API: Agent Balance Topup
 *
 * Adds balance to user accounts. Optionally renews the user's current bundle.
 *
 * Parameters:
 *   - agent_id (required): Agent performing the topup
 *   - username (required): Target user
 *   - amount (required): Topup amount (must be > 0, max 300,000)
 *   - payment_method (optional): default 'cash'
 *   - notes (optional): default 'Balance topup via agent'
 *   - renew_current_bundle (optional, bool): If true, renew user's current bundle after topup
 *
 * @package DaloRADIUS
 * @subpackage API
 */

require_once('auth.php');
require_once('../../common/includes/config_read.php');
require_once('../../common/includes/db_open.php');
require_once('../../common/library/BalanceManager.php');

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
$renewBundle = isset($input['renew_current_bundle']) ? filter_var($input['renew_current_bundle'], FILTER_VALIDATE_BOOLEAN) : false;

// Validate amount
if ($amount <= 0) {
    apiSendError('Amount must be greater than zero');
}

if ($amount > 300000) {
    apiSendError('Amount exceeds maximum limit of 300,000');
}

try {
    // Verify agent exists
    $sql = sprintf("SELECT id, name FROM %s WHERE id = %d AND is_deleted = 0",
                   $configValues['CONFIG_DB_TBL_DALOAGENTS'], $agentId);

    $result = $dbSocket->query($sql);

    if (DB::isError($result) || $result->numRows() === 0) {
        apiSendError('Agent not found or inactive');
    }

    $agent = $result->fetchRow(DB_FETCHMODE_ASSOC);

    // Get user info
    $sql = sprintf("SELECT id, username, money_balance FROM %s WHERE username = '%s'",
                   $configValues['CONFIG_DB_TBL_DALOUSERBILLINFO'],
                   $dbSocket->escapeSimple($username));
    $result = $dbSocket->query($sql);

    if (DB::isError($result) || $result->numRows() === 0) {
        apiSendError('User not found');
    }

    $user = $result->fetchRow(DB_FETCHMODE_ASSOC);
    $userId = $user['id'];
    $balanceBefore = floatval($user['money_balance']);

    // Create mysqli connection for BalanceManager/BundleManager
    $mysqli = new mysqli(
        $configValues['CONFIG_DB_HOST'],
        $configValues['CONFIG_DB_USER'],
        $configValues['CONFIG_DB_PASS'],
        $configValues['CONFIG_DB_NAME']
    );

    if ($mysqli->connect_error) {
        apiSendError('Database connection failed', 500);
    }

    $mysqli->set_charset('utf8mb4');

    // ========================
    // STEP 1: Add balance (topup)
    // ========================
    $balanceManager = new BalanceManager($mysqli);

    $topupResult = $balanceManager->addBalance(
        $userId,
        $username,
        $amount,
        "agent_{$agentId}",
        $notes,
        'agent_payment',
        null
    );

    if (!$topupResult['success']) {
        apiSendError($topupResult['message'], 500);
    }

    $balanceAfterTopup = $topupResult['new_balance'];

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
        $balanceAfterTopup,
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

    // ========================
    // STEP 2: Renew bundle (if requested)
    // ========================
    $renewResult = null;
    $finalBalance = $balanceAfterTopup;

    if ($renewBundle) {
        require_once('../../common/library/BundleManager.php');
        $bundleManager = new BundleManager($mysqli);

        $renewResult = $bundleManager->renewBundle($userId, $username, "agent_{$agentId}", $agentId);

        // Re-read actual final balance after renewal (bundle cost was deducted)
        $balanceCheck = $mysqli->query(sprintf(
            "SELECT money_balance FROM userbillinfo WHERE id = %d", $userId
        ));
        if ($balanceCheck && $balanceCheck->num_rows > 0) {
            $finalBalance = floatval($balanceCheck->fetch_assoc()['money_balance']);
        }
    }

    // ========================
    // STEP 3: Balance verification
    // ========================
    // Cross-check: sum of all topups - sum of all deductions should equal current balance
    $verifyQuery = $mysqli->query(sprintf(
        "SELECT
            (SELECT COALESCE(SUM(amount), 0) FROM user_balance_history WHERE user_id = %d AND transaction_type = 'credit') as total_credits,
            (SELECT COALESCE(SUM(ABS(amount)), 0) FROM user_balance_history WHERE user_id = %d AND transaction_type IN ('debit', 'payment')) as total_debits,
            (SELECT money_balance FROM userbillinfo WHERE id = %d) as current_balance",
        $userId, $userId, $userId
    ));

    $balanceVerification = null;
    if ($verifyQuery && $verifyQuery->num_rows > 0) {
        $v = $verifyQuery->fetch_assoc();
        $expectedBalance = floatval($v['total_credits']) - floatval($v['total_debits']);
        $actualBalance = floatval($v['current_balance']);
        $balanceVerification = [
            'total_credits' => floatval($v['total_credits']),
            'total_debits' => floatval($v['total_debits']),
            'expected_balance' => round($expectedBalance, 2),
            'actual_balance' => $actualBalance,
            'match' => abs($expectedBalance - $actualBalance) < 0.01
        ];
    }

    // Get actual RADIUS group name for renewed bundle
    $renewedGroupName = null;
    if ($renewResult && $renewResult['success'] && !empty($renewResult['renewed_plan'])) {
        $grpResult = $mysqli->query(sprintf(
            "SELECT GROUP_CONCAT(DISTINCT profile_name ORDER BY profile_name SEPARATOR ', ') AS group_names
             FROM billing_plans_profiles WHERE plan_name = '%s'",
            $mysqli->real_escape_string($renewResult['renewed_plan'])
        ));
        $renewedGroupName = ($grpResult && $grpRow = $grpResult->fetch_assoc()) ? $grpRow['group_names'] : $renewResult['renewed_plan'];
    }

    $mysqli->close();

    // ========================
    // Build response
    // ========================
    $response = [
        'payment_id' => $paymentId,
        'username' => $username,
        'amount_added' => $amount,
        'balance_before' => $balanceBefore,
        'balance_after_topup' => $balanceAfterTopup,
        'final_balance' => $finalBalance,
        'agent_name' => $agent['name'],
        'payment_date' => date('Y-m-d H:i:s'),
        'message' => 'Balance topup successful'
    ];

    // Add renewal info
    if ($renewBundle) {
        $response['renew_current_bundle'] = true;
        if ($renewResult && $renewResult['success']) {
            $response['bundle_renewed'] = true;
            $response['bundle_skipped'] = !empty($renewResult['skipped']);
            $response['bundle_id'] = $renewResult['bundle_id'] ?? null;
            $response['bundle_expiry'] = $renewResult['expiry_date'] ?? null;
            $response['bundle_expiry_formatted'] = !empty($renewResult['expiry_date']) ? date('d M Y H:i', strtotime($renewResult['expiry_date'])) : null;
            $response['bundle_plan'] = $renewResult['renewed_plan'] ?? '';
            $response['group_name'] = $renewedGroupName ?? ($renewResult['renewed_plan'] ?? '');
            $response['bundle_cost_deducted'] = $balanceAfterTopup - $finalBalance;
            if (!empty($renewResult['log'])) {
                $response['renewal_log'] = $renewResult['log'];
            }
            $response['message'] = !empty($renewResult['skipped'])
                ? 'Balance topup successful. Bundle already active.'
                : 'Balance topup and bundle renewal successful';
        } else {
            $response['bundle_renewed'] = false;
            $response['bundle_error'] = $renewResult ? $renewResult['message'] : 'Unknown error';
            if (!empty($renewResult['log'])) {
                $response['renewal_log'] = $renewResult['log'];
            }
            $response['message'] = 'Balance topup successful, but bundle renewal failed: ' . ($renewResult ? $renewResult['message'] : 'Unknown error');
        }
    } else {
        $response['renew_current_bundle'] = false;
    }

    // Add balance verification
    if ($balanceVerification) {
        $response['balance_verification'] = $balanceVerification;
    }

    apiSendSuccess($response);

} catch (Exception $e) {
    error_log("agent_topup_balance error: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
    apiSendError('Internal server error. Please try again or contact support.', 500);
}
