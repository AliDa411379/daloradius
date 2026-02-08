<?php
/**
 * Bundle Purchase Report API
 * Get bundle purchase statistics and details
 * 
 * GET: /api/report_bundle_purchases.php?start_date=2025-01-01&end_date=2025-01-31&agent_id=1
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
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$username = isset($_GET['username']) ? trim($_GET['username']) : '';

// Default to current month if not specified
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
    
    // Build query with filters
    $where_clauses = array("ub.purchase_date BETWEEN ? AND ?");
    $start_date_full = $start_date . ' 00:00:00';
    $end_date_full = $end_date . ' 23:59:59';
    $params = array('ss', &$start_date_full, &$end_date_full);
    
    if ($agent_id > 0) {
        $where_clauses[] = "ua.agent_id = ?";
        $params[0] .= 'i';
        $params[] = &$agent_id;
    }
    
    if (!empty($status) && in_array($status, array('active', 'expired', 'used'))) {
        $where_clauses[] = "ub.status = ?";
        $params[0] .= 's';
        $params[] = &$status;
    }
    
    if (!empty($username)) {
        $where_clauses[] = "ub.username = ?";
        $params[0] .= 's';
        $params[] = &$username;
    }
    
    $where_sql = implode(' AND ', $where_clauses);
    
    // Get summary statistics
    $summary_sql = "SELECT 
                        COUNT(*) as total_purchases,
                        SUM(bp.planCost) as total_revenue,
                        COUNT(DISTINCT ub.username) as unique_users,
                        COUNT(CASE WHEN ub.status = 'active' THEN 1 END) as active_bundles,
                        COUNT(CASE WHEN ub.status = 'expired' THEN 1 END) as expired_bundles
                    FROM user_bundles ub
                    INNER JOIN {$configValues['CONFIG_DB_TBL_DALOBILLINGPLANS']} bp ON ub.plan_id = bp.id
                    LEFT JOIN {$configValues['CONFIG_DB_TBL_DALOUSERINFO']} ui ON ub.username = ui.username
                    LEFT JOIN user_agent ua ON ui.id = ua.user_id
                    WHERE $where_sql";
    
    $stmt = $db->prepare($summary_sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $db->error);
    }
    call_user_func_array(array($stmt, 'bind_param'), $params);
    $stmt->execute();
    $summary = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Get detailed purchases
    $details_sql = "SELECT 
                        ub.id,
                        ub.username,
                        bp.planName as bundle_name,
                        bp.planCost as cost,
                        bp.planCurrency as currency,
                        ub.purchase_date,
                        ub.activation_date,
                        ub.expiry_date,
                        ub.status
                    FROM user_bundles ub
                    INNER JOIN {$configValues['CONFIG_DB_TBL_DALOBILLINGPLANS']} bp ON ub.plan_id = bp.id
                    LEFT JOIN {$configValues['CONFIG_DB_TBL_DALOUSERINFO']} ui ON ub.username = ui.username
                    LEFT JOIN user_agent ua ON ui.id = ua.user_id
                    WHERE $where_sql
                    ORDER BY ub.purchase_date DESC
                    LIMIT 1000";
    
    $stmt = $db->prepare($details_sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $db->error);
    }
    call_user_func_array(array($stmt, 'bind_param'), $params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $purchases = array();
    while ($row = $result->fetch_assoc()) {
        // Calculate remaining time for active bundles
        $remaining = null;
        if ($row['status'] === 'active' && $row['expiry_date']) {
            $remaining_seconds = strtotime($row['expiry_date']) - time();
            if ($remaining_seconds > 0) {
                $remaining = array(
                    'days' => floor($remaining_seconds / 86400),
                    'hours' => floor(($remaining_seconds % 86400) / 3600)
                );
            }
        }
        
        $purchases[] = array(
            'id' => intval($row['id']),
            'username' => $row['username'],
            'bundle_name' => $row['bundle_name'],
            'cost' => floatval($row['cost']),
            'currency' => $row['currency'],
            'purchase_date' => $row['purchase_date'],
            'activation_date' => $row['activation_date'],
            'expiry_date' => $row['expiry_date'],
            'status' => $row['status'],
            'validity' => array(
                'days' => 0, // Calculated from dates if needed
                'hours' => 0
            ),
            'remaining' => $remaining
        );
    }
    
    $stmt->close();
    
    // Group by bundle plan
    $by_plan_sql = "SELECT 
                        bp.planName,
                        COUNT(*) as count,
                        SUM(bp.planCost) as revenue
                    FROM user_bundles ub
                    INNER JOIN {$configValues['CONFIG_DB_TBL_DALOBILLINGPLANS']} bp ON ub.plan_id = bp.id
                    LEFT JOIN {$configValues['CONFIG_DB_TBL_DALOUSERINFO']} ui ON ub.username = ui.username
                    LEFT JOIN user_agent ua ON ui.id = ua.user_id
                    WHERE $where_sql
                    GROUP BY bp.planName
                    ORDER BY count DESC";
    
    $stmt = $db->prepare($by_plan_sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $db->error);
    }
    call_user_func_array(array($stmt, 'bind_param'), $params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $by_plan = array();
    while ($row = $result->fetch_assoc()) {
        $by_plan[] = array(
            'plan_name' => $row['planName'],
            'count' => intval($row['count']),
            'revenue' => floatval($row['revenue'])
        );
    }
    
    $stmt->close();
    $db->close();
    
    // Build response
    $response = array(
        'period' => array(
            'start_date' => $start_date,
            'end_date' => $end_date
        ),
        'summary' => array(
            'total_purchases' => intval($summary['total_purchases']),
            'total_revenue' => floatval($summary['total_revenue']),
            'unique_users' => intval($summary['unique_users']),
            'active_bundles' => intval($summary['active_bundles']),
            'expired_bundles' => intval($summary['expired_bundles'])
        ),
        'by_plan' => $by_plan,
        'purchases' => $purchases
    );
    
    // Request logging is handled by auth.php
    
    apiSendSuccess(array_merge(['message' => 'Bundle purchase report generated'], $response));
    
} catch (Exception $e) {
    logApiError(basename($_SERVER['PHP_SELF']), $e->getMessage());
    apiSendError('Report generation failed: ' . $e->getMessage());
}
?>
