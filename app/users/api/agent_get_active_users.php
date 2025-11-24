<?php
/**
 * API: Get Active Users by Agent
 * 
 * Returns list of active users for a specific agent
 * with balance, subscription, and payment info
 * 
 * @package DaloRADIUS
 * @subpackage API
 */

header('Content-Type: application/json');

// Include required files
require_once('../../../app/common/includes/config_read.php');
require_once('../../../app/common/includes/db_open.php');

function apiSendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function apiSendSuccess($data) {
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

// Get parameters
$agentId = null;
$status = 'active';
$subscriptionType = 'all';
$limit = 50;
$offset = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $agentId = isset($input['agent_id']) ? intval($input['agent_id']) : null;
    $status = isset($input['status']) ? $input['status'] : 'active';
    $subscriptionType = isset($input['subscription_type']) ? $input['subscription_type'] : 'all';
    $limit = isset($input['limit']) ? min(intval($input['limit']), 100) : 50;
    $offset = isset($input['offset']) ? intval($input['offset']) : 0;
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $agentId = isset($_GET['agent_id']) ? intval($_GET['agent_id']) : null;
    $status = isset($_GET['status']) ? $_GET['status'] : 'active';
    $subscriptionType = isset($_GET['subscription_type']) ? $_GET['subscription_type'] : 'all';
    $limit = isset($_GET['limit']) ? min(intval($_GET['limit']), 100) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
}

if (!$agentId) {
    apiSendError('agent_id is required');
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
    
    // Build WHERE clause
    $whereConditions = ["ap.agent_id = $agentId"];
    
    if ($status !== 'all') {
        $whereConditions[] = sprintf("u.billstatus = '%s'", $mysqli->real_escape_string($status));
    }
    
    if ($subscriptionType !== 'all') {
        $typeId = ($subscriptionType === 'monthly') ? 1 : 2;
        $whereConditions[] = "u.subscription_type_id = $typeId";
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get total count
    $countSql = "SELECT COUNT(DISTINCT u.id) as count
                 FROM agent_payments ap
                 INNER JOIN userbillinfo u ON ap.user_id = u.id
                 WHERE $whereClause";
    
    $result = $mysqli->query($countSql);
    $totalCount = $result ? $result->fetch_assoc()['count'] : 0;
    
    // Get users with latest payment info
    $sql = "SELECT 
                u.id,
                u.username,
                CONCAT(COALESCE(ui.firstname, ''), ' ', COALESCE(ui.lastname, '')) as full_name,
                st.type_name as subscription_type,
                u.planName as plan_name,
                u.money_balance as balance,
                u.billstatus as status,
                u.creationdate as creation_date,
                u.bundle_expiry_date,
                ub.plan_name as active_bundle,
                MAX(ap.payment_date) as last_payment,
                (SELECT SUM(amount) FROM agent_payments WHERE user_id = u.id AND payment_date = MAX(ap.payment_date) LIMIT 1) as last_payment_amount
            FROM agent_payments ap
            INNER JOIN userbillinfo u ON ap.user_id = u.id
            LEFT JOIN userinfo ui ON u.username = ui.username
            LEFT JOIN subscription_types st ON u.subscription_type_id = st.id
            LEFT JOIN user_bundles ub ON u.current_bundle_id = ub.id AND ub.status = 'active'
            WHERE $whereClause
            GROUP BY u.id, u.username, full_name, subscription_type, plan_name, 
                     balance, status, creation_date, bundle_expiry_date, active_bundle
            ORDER BY last_payment DESC
            LIMIT $limit OFFSET $offset";
    
    $result = $mysqli->query($sql);
    
    if (!$result) {
        apiSendError('Query failed: ' . $mysqli->error, 500);
    }
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $userData = [
            'username' => $row['username'],
            'full_name' => trim($row['full_name']) ?: $row['username'],
            'subscription_type' => $row['subscription_type'],
            'plan_name' => $row['plan_name'],
            'balance' => floatval($row['balance']),
            'status' => $row['status'],
            'creation_date' => $row['creation_date']
        ];
        
        // Add subscription-specific fields
        if ($row['subscription_type'] === 'monthly') {
            $userData['last_payment'] = $row['last_payment'];
            $userData['last_payment_amount'] = floatval($row['last_payment_amount']);
        } else {
            // Prepaid
            if ($row['active_bundle']) {
                $userData['active_bundle'] = $row['active_bundle'];
                $userData['bundle_expiry'] = $row['bundle_expiry_date'];
            }
        }
        
        $users[] = $userData;
    }
    
    $mysqli->close();
    
    apiSendSuccess([
        'total_count' => intval($totalCount),
        'returned_count' => count($users),
        'limit' => $limit,
        'offset' => $offset,
        'users' => $users
    ]);
    
} catch (Exception $e) {
    if (isset($mysqli)) $mysqli->close();
    apiSendError('Internal server error: ' . $e->getMessage(), 500);
}

