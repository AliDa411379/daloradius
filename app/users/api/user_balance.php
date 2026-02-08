<?php
/**
 * API: Get User Balance and Bundle Status
 * 
 * Quick lookup for user's current balance and active bundle
 * Lightweight endpoint for balance checks
 * 
 * @package DaloRADIUS
 * @subpackage API
 */

header('Content-Type: application/json');

// Include required files
// Include required files
require_once(__DIR__ . '/../../common/includes/config_read.php');
require_once(__DIR__ . '/../../common/includes/db_open.php');

function apiSendError($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function apiSendSuccess($data)
{
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

// Get username
$username = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $username = isset($input['username']) ? trim($input['username']) : null;
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $username = isset($_GET['username']) ? trim($_GET['username']) : null;
}

if (empty($username)) {
    apiSendError('Username is required');
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

    $username_esc = $mysqli->real_escape_string($username);

    // Get user info
    $sql = "SELECT 
                u.id,
                u.username,
                u.money_balance,
                u.traffic_balance,
                u.timebank_balance,
                u.subscription_type_id,
                u.billstatus,
                u.planName,
                st.type_name as subscription_type,
                st.display_name as subscription_type_display
            FROM userbillinfo u
            LEFT JOIN subscription_types st ON u.subscription_type_id = st.id
            WHERE u.username = '$username_esc'
            LIMIT 1";

    $result = $mysqli->query($sql);

    if (!$result || $result->num_rows === 0) {
        apiSendError('User not found', 404);
    }

    $user = $result->fetch_assoc();

    // Get active bundle if exists
    $activeBundle = null;
    if ($user['subscription_type_id'] == 2) { // Prepaid
        $sql = sprintf(
            "SELECT 
                id, plan_name, purchase_date, activation_date, 
                expiry_date, status, purchase_amount
             FROM user_bundles 
             WHERE user_id = %d AND status = 'active' AND expiry_date > NOW()
             ORDER BY expiry_date DESC LIMIT 1",
            $user['id']
        );

        $result = $mysqli->query($sql);
        if ($result && $result->num_rows > 0) {
            $bundle = $result->fetch_assoc();
            $activeBundle = [
                'bundle_id' => intval($bundle['id']),
                'plan_name' => $bundle['plan_name'],
                'purchase_date' => $bundle['purchase_date'],
                'activation_date' => $bundle['activation_date'],
                'expiry_date' => $bundle['expiry_date'],
                'status' => $bundle['status'],
                'days_remaining' => max(0, floor((strtotime($bundle['expiry_date']) - time()) / 86400))
            ];
        }
    }

    // Check for explicit blocks/bans
    $group_check = $mysqli->query("SELECT count(*) as cnt FROM radusergroup WHERE username = '$username_esc' AND groupname IN ('daloRADIUS-Disabled-Users', 'block_user')");
    $is_banned = ($group_check && $group_check->fetch_assoc()['cnt'] > 0);

    // Determine effective status
    $effective_status = $user['billstatus'];

    if ($is_banned) {
        $effective_status = 'suspended';
    } elseif ($user['subscription_type_id'] == 2 && !$activeBundle) {
        // Prepaid user with no active bundle (or expired)
        $effective_status = 'expired';
    }

    $mysqli->close();

    // Build response
    $response = [
        'username' => $user['username'],
        'subscription_type' => $user['subscription_type'],
        'subscription_type_display' => $user['subscription_type_display'],
        'plan_name' => $user['planName'],
        'status' => $effective_status,
        'balances' => [
            'money' => floatval($user['money_balance']),
            'traffic_mb' => floatval($user['traffic_balance']),
            'time_minutes' => floatval($user['timebank_balance'])
        ]
    ];

    if ($activeBundle) {
        $response['active_bundle'] = $activeBundle;
    }

    apiSendSuccess($response);

} catch (Exception $e) {
    if (isset($mysqli))
        $mysqli->close();
    apiSendError('Internal server error: ' . $e->getMessage(), 500);
}

