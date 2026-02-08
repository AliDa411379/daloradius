<?php
/**
 * Payment Report API
 * Get payment statistics and transaction history
 * 
 * GET: /api/report_payments.php?start_date=2025-01-01&end_date=2025-01-31&agent_id=1
 */


require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/auth.php');
require_once(__DIR__ . '/../../common/includes/config_read.php');

// Authentication is handled by auth.php
// No need to call apiHandlePreflight() or apiAuthenticate() explicitly

// Only GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiSendError('Method not allowed', 405);
}

// Get parameters
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$agent_id = isset($_GET['agent_id']) ? intval($_GET['agent_id']) : 0;
$payment_type = isset($_GET['payment_type']) ? trim($_GET['payment_type']) : '';

// Default to current month
if (empty($start_date)) {
    $start_date = date('Y-m-01');
}

if (empty($end_date)) {
    $end_date = date('Y-m-t');
}

try {
    $db = new mysqli(
        $configValues['CONFIG_DB_HOST'],
        $configValues['CONFIG_DB_USER'],
        $configValues['CONFIG_DB_PASS'],
        $configValues['CONFIG_DB_NAME']
    );
    
    if ($db->connect_error) {
        throw new Exception('Database connection failed: ' . $db->connect_error);
    }
    
    $db->set_charset('utf8mb4');
    
    // Build query
    $where_clauses = array("ap.payment_date BETWEEN ? AND ?");
    $start_date_full = $start_date . ' 00:00:00';
    $end_date_full = $end_date . ' 23:59:59';
    $params = array('ss', &$start_date_full, &$end_date_full);
    
    if ($agent_id > 0) {
        $where_clauses[] = "ap.agent_id = ?";
        $params[0] .= 'i';
        $params[] = &$agent_id;
    }
    
    if (!empty($payment_type) && in_array($payment_type, array('balance_topup', 'bundle_purchase'))) {
        $where_clauses[] = "ap.payment_type = ?";
        $params[0] .= 's';
        $params[] = &$payment_type;
    }
    
    $where_sql = implode(' AND ', $where_clauses);
    
    // Summary statistics
    $summary_sql = "SELECT 
                        COUNT(*) as total_transactions,
                        SUM(ap.amount) as total_amount,
                        COUNT(DISTINCT ap.username) as unique_users,
                        COUNT(CASE WHEN ap.payment_type = 'balance_topup' THEN 1 END) as topup_count,
                        SUM(CASE WHEN ap.payment_type = 'balance_topup' THEN ap.amount ELSE 0 END) as topup_amount,
                        COUNT(CASE WHEN ap.payment_type = 'bundle_purchase' THEN 1 END) as bundle_count,
                        SUM(CASE WHEN ap.payment_type = 'bundle_purchase' THEN ap.amount ELSE 0 END) as bundle_amount
                    FROM agent_payments ap
                    WHERE $where_sql";
    
    $stmt = $db->prepare($summary_sql);
    $stmt = $db->prepare($summary_sql);
    call_user_func_array(array($stmt, 'bind_param'), $params);
    $stmt->execute();
    $summary = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Detailed transactions
    $details_sql = "SELECT 
                        ap.id,
                        ap.agent_id,
                        a.name as agent_name,
                        ap.username,
                        ap.payment_type,
                        ap.amount,
                        ap.user_balance_before as balance_before,
                        ap.user_balance_after as balance_after,
                        ub.plan_id as bundle_plan_id,
                        bp.planName as bundle_name,
                        ap.payment_method,
                        ap.payment_date,
                        ap.notes
                    FROM agent_payments ap
                    LEFT JOIN {$configValues['CONFIG_DB_TBL_DALOAGENTS']} a ON ap.agent_id = a.id
                    LEFT JOIN user_bundles ub ON (ap.reference_id = ub.id AND ap.reference_type = 'bundle')
                    LEFT JOIN {$configValues['CONFIG_DB_TBL_DALOBILLINGPLANS']} bp ON ub.plan_id = bp.id
                    WHERE $where_sql
                    ORDER BY ap.payment_date DESC
                    LIMIT 1000";
    
    $stmt = $db->prepare($details_sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $db->error);
    }
    $stmt = $db->prepare($details_sql);
    call_user_func_array(array($stmt, 'bind_param'), $params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactions = array();
    while ($row = $result->fetch_assoc()) {
        $transactions[] = array(
            'id' => intval($row['id']),
            'agent' => array(
                'id' => intval($row['agent_id']),
                'name' => $row['agent_name']
            ),
            'username' => $row['username'],
            'payment_type' => $row['payment_type'],
            'amount' => floatval($row['amount']),
            'balance_before' => floatval($row['balance_before']),
            'balance_after' => floatval($row['balance_after']),
            'bundle' => $row['bundle_plan_id'] ? array(
                'id' => intval($row['bundle_plan_id']),
                'name' => $row['bundle_name']
            ) : null,
            'payment_method' => $row['payment_method'],
            'payment_date' => $row['payment_date'],
            'notes' => $row['notes']
        );
    }
    
    $stmt->close();
    
    // Group by agent
    $by_agent_sql = "SELECT 
                        ap.agent_id,
                        a.name as agent_name,
                        COUNT(*) as transaction_count,
                        SUM(ap.amount) as total_amount
                    FROM agent_payments ap
                    LEFT JOIN {$configValues['CONFIG_DB_TBL_DALOAGENTS']} a ON ap.agent_id = a.id
                    WHERE $where_sql
                    GROUP BY ap.agent_id, a.name
                    ORDER BY total_amount DESC";
    
    $stmt = $db->prepare($by_agent_sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $db->error);
    }
    $stmt = $db->prepare($by_agent_sql);
    call_user_func_array(array($stmt, 'bind_param'), $params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $by_agent = array();
    while ($row = $result->fetch_assoc()) {
        $by_agent[] = array(
            'agent_id' => intval($row['agent_id']),
            'agent_name' => $row['agent_name'],
            'transaction_count' => intval($row['transaction_count']),
            'total_amount' => floatval($row['total_amount'])
        );
    }
    
    $stmt->close();
    $db->close();
    
    $response = array(
        'period' => array(
            'start_date' => $start_date,
            'end_date' => $end_date
        ),
        'summary' => array(
            'total_transactions' => intval($summary['total_transactions']),
            'total_amount' => floatval($summary['total_amount']),
            'unique_users' => intval($summary['unique_users']),
            'balance_topups' => array(
                'count' => intval($summary['topup_count']),
                'amount' => floatval($summary['topup_amount'])
            ),
            'bundle_purchases' => array(
                'count' => intval($summary['bundle_count']),
                'amount' => floatval($summary['bundle_amount'])
            )
        ),
        'by_agent' => $by_agent,
        'transactions' => $transactions
    );
    
    // Request logging is handled by auth.php
    
    apiSendSuccess(array_merge(['message' => 'Payment report generated'], $response));
    
} catch (Exception $e) {
    logApiError(basename($_SERVER['PHP_SELF']), $e->getMessage());
    apiSendError('Report generation failed: ' . $e->getMessage());
}
?>
