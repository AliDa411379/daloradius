<?php
/**
 * Agent Change Bundle API
 *
 * Changes a user's active bundle to a new plan with prorate refund.
 *
 * POST Parameters:
 *   - agent_id: Agent ID (required)
 *   - username: Username (required)
 *   - new_plan_id: New bundle plan ID (required)
 *
 * Returns JSON:
 *   - success: bool
 *   - refund_amount: float (prorate refund credited)
 *   - new_cost: float (new bundle cost deducted)
 *   - net_charge: float (new_cost - refund_amount)
 *   - bundle_id: int (new bundle ID)
 *   - expiry_date: string
 *   - new_balance: float
 *   - old_plan: string
 *   - new_plan: string
 *   - message: string
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

// Load configuration and authentication
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/auth.php');

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

// Authenticate
$auth = authenticateRequest();
if (!$auth['success']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => $auth['message']]);
    exit;
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$agent_id = isset($input['agent_id']) ? intval($input['agent_id']) : 0;
$username = isset($input['username']) ? trim($input['username']) : '';
$new_plan_id = isset($input['new_plan_id']) ? intval($input['new_plan_id']) : 0;

// Validate
if ($agent_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'agent_id is required']);
    exit;
}

if (empty($username)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'username is required']);
    exit;
}

if ($new_plan_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'new_plan_id is required']);
    exit;
}

try {
    // Database connection
    $db = getDbConnection();

    // Load managers
    require_once(__DIR__ . '/../../common/library/BundleManager.php');
    require_once(__DIR__ . '/../../common/library/RadiusAccessManager.php');

    // Verify agent exists and is active
    $stmt = $db->prepare("SELECT id, agent_name FROM agents WHERE id = ? AND is_active = 1");
    $stmt->bind_param('i', $agent_id);
    $stmt->execute();
    $agentResult = $stmt->get_result();

    if ($agentResult->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Agent not found or inactive']);
        exit;
    }
    $agent = $agentResult->fetch_assoc();

    // Get user ID from username
    $stmt = $db->prepare("SELECT id, username FROM userbillinfo WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $userResult = $stmt->get_result();

    if ($userResult->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    $user = $userResult->fetch_assoc();
    $userId = intval($user['id']);

    // Verify agent has access to this user
    $stmt = $db->prepare("SELECT ua.id FROM user_agent ua
                           INNER JOIN userinfo ui ON ua.user_id = ui.id
                           WHERE ua.agent_id = ? AND ui.username = ?");
    $stmt->bind_param('is', $agent_id, $username);
    $stmt->execute();
    $accessResult = $stmt->get_result();

    if ($accessResult->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Agent does not have access to this user']);
        exit;
    }

    // Execute bundle change
    $bundleManager = new BundleManager($db);
    $result = $bundleManager->changeBundle(
        $userId,
        $username,
        $new_plan_id,
        null,
        'agent:' . $agent['agent_name']
    );

    if ($result['success']) {
        // NOTE: Do NOT call $radiusManager->grantAccess() here.
        // BundleManager::changeBundle() already calls activateBundleRadius() which sets
        // Expiration, radusergroup, Session-Timeout, Mikrotik-Rate-Limit, Mikrotik-Total-Limit.
        // Calling grantAccess() would overwrite/delete Expiration via setMikrotikAttributesForPlan().

        // Get actual RADIUS group name from billing_plans_profiles
        $newPlanName = $result['new_plan'];
        $groupResult = $db->query(sprintf(
            "SELECT GROUP_CONCAT(DISTINCT profile_name ORDER BY profile_name SEPARATOR ', ') AS group_names
             FROM billing_plans_profiles WHERE plan_name = '%s'",
            $db->real_escape_string($newPlanName)
        ));
        $groupName = ($groupResult && $row = $groupResult->fetch_assoc()) ? $row['group_names'] : $newPlanName;

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'bundle_id' => $result['bundle_id'],
            'old_plan' => $result['old_plan'],
            'new_plan' => $result['new_plan'],
            'group_name' => $groupName,
            'refund_amount' => $result['refund_amount'],
            'new_cost' => $result['new_cost'],
            'net_charge' => $result['net_charge'],
            'expiry_date' => $result['expiry_date'],
            'expiry_date_formatted' => date('d M Y H:i', strtotime($result['expiry_date'])),
            'new_balance' => $result['new_balance'],
            'remaining_days_refunded' => $result['remaining_days_refunded'],
            'radius_log' => isset($result['radius_log']) ? $result['radius_log'] : [],
            'message' => $result['message']
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $result['message']]);
    }

    $db->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
