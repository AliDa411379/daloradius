<?php
/**
 * API: Get Agent Payment History
 * 
 * Returns payment history for a specific agent
 * Supports filtering by date range
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
$startDate = null;
$endDate = null;
$limit = 50;
$offset = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $agentId = isset($input['agent_id']) ? intval($input['agent_id']) : null;
    $startDate = isset($input['start_date']) ? $input['start_date'] : null;
    $endDate = isset($input['end_date']) ? $input['end_date'] : null;
    $limit = isset($input['limit']) ? min(intval($input['limit']), 100) : 50;
    $offset = isset($input['offset']) ? intval($input['offset']) : 0;
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $agentId = isset($_GET['agent_id']) ? intval($_GET['agent_id']) : null;
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
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
    $whereConditions = [sprintf("ap.agent_id = %d", $agentId)];
    
    if ($startDate) {
        $whereConditions[] = sprintf("ap.payment_date >= '%s'", $mysqli->real_escape_string($startDate));
    }
    
    if ($endDate) {
        $whereConditions[] = sprintf("ap.payment_date <= '%s 23:59:59'", $mysqli->real_escape_string($endDate));
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get total count and sum
    $statsSql = "SELECT 
                    COUNT(*) as total_count,
                    COALESCE(SUM(amount), 0) as total_amount,
                    COALESCE(SUM(CASE WHEN payment_type = 'balance_topup' THEN amount ELSE 0 END), 0) as topup_total,
                    COALESCE(SUM(CASE WHEN payment_type = 'bundle_purchase' THEN amount ELSE 0 END), 0) as bundle_total
                 FROM agent_payments ap
                 WHERE $whereClause";
    
    $result = $mysqli->query($statsSql);
    $stats = $result->fetch_assoc();
    
    // Get payment history
    $sql = "SELECT 
                ap.*,
                u.username,
                CONCAT(COALESCE(ui.firstname, ''), ' ', COALESCE(ui.lastname, '')) as user_full_name,
                ub.plan_name as bundle_name
            FROM agent_payments ap
            LEFT JOIN userbillinfo u ON ap.user_id = u.id
            LEFT JOIN userinfo ui ON u.username = ui.username
            LEFT JOIN user_bundles ub ON ap.reference_type = 'bundle' AND ap.reference_id = ub.id
            WHERE $whereClause
            ORDER BY ap.payment_date DESC
            LIMIT $limit OFFSET $offset";
    
    $result = $mysqli->query($sql);
    
    if (!$result) {
        apiSendError('Query failed: ' . $mysqli->error, 500);
    }
    
    $payments = [];
    while ($row = $result->fetch_assoc()) {
        $payment = [
            'payment_id' => intval($row['id']),
            'username' => $row['username'],
            'user_full_name' => trim($row['user_full_name']) ?: $row['username'],
            'payment_type' => $row['payment_type'],
            'amount' => floatval($row['amount']),
            'payment_date' => $row['payment_date'],
            'payment_method' => $row['payment_method'],
            'balance_before' => floatval($row['user_balance_before']),
            'balance_after' => floatval($row['user_balance_after']),
            'notes' => $row['notes']
        ];
        
        // Add reference info if applicable
        if ($row['reference_type'] === 'bundle' && $row['bundle_name']) {
            $payment['bundle_name'] = $row['bundle_name'];
            $payment['bundle_id'] = intval($row['reference_id']);
        }
        
        $payments[] = $payment;
    }
    
    $mysqli->close();
    
    apiSendSuccess([
        'agent_id' => $agentId,
        'date_range' => [
            'start' => $startDate ?: 'all',
            'end' => $endDate ?: 'all'
        ],
        'statistics' => [
            'total_transactions' => intval($stats['total_count']),
            'total_amount' => floatval($stats['total_amount']),
            'topup_amount' => floatval($stats['topup_total']),
            'bundle_amount' => floatval($stats['bundle_total'])
        ],
        'returned_count' => count($payments),
        'payments' => $payments
    ]);
    
} catch (Exception $e) {
    if (isset($mysqli)) $mysqli->close();
    apiSendError('Internal server error: ' . $e->getMessage(), 500);
}

