<?php
/**
 * API: Get Comprehensive User Information
 * 
 * Returns all user data in single call:
 * - Personal info
 * - Subscription details
 * - Balances (money, traffic, time)
 * - Active bundle
 * - Payment history
 * - Bundle history
 * - Usage summary
 * 
 * @package DaloRADIUS
 * @subpackage API
 */

header('Content-Type: application/json');

// Include required files
require_once('../../common/includes/config_read.php');
require_once('../../common/includes/db_open.php');

function apiSendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function apiSendSuccess($data) {
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

// Allow both GET and POST
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
    
    // Get user billing info and personal info
    $sql = "SELECT 
                u.id, u.username, u.planName, u.subscription_type_id,
                u.money_balance, u.traffic_balance, u.timebank_balance,
                u.bundle_activation_date, u.bundle_expiry_date, u.bundle_status,
                u.billstatus, u.last_balance_update,
                ui.firstname, ui.lastname, ui.email, ui.phone,
                ui.address, ui.city, ui.country,
                st.type_name, st.display_name as subscription_type_display
            FROM userbillinfo u
            LEFT JOIN userinfo ui ON u.username = ui.username
            LEFT JOIN subscription_types st ON u.subscription_type_id = st.id
            WHERE u.username = '$username_esc'
            LIMIT 1";
    
    $result = $mysqli->query($sql);
    if (!$result || $result->num_rows === 0) {
        apiSendError('User not found', 404);
    }
    
    $user = $result->fetch_assoc();
    $userId = $user['id'];
    
    // Get active bundle if exists
    $activeBundle = null;
    $sql = sprintf(
        "SELECT * FROM user_bundles 
         WHERE user_id = %d AND status = 'active' AND expiry_date > NOW()
         ORDER BY expiry_date DESC LIMIT 1",
        $userId
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
            'purchase_amount' => floatval($bundle['purchase_amount'])
        ];
    }
    
    // Get payment history (from agent_payments and balance history)
    $paymentHistory = [];
    
    // Agent payments
    $sql = sprintf(
        "SELECT 
            ap.id as payment_id,
            ap.payment_date as date,
            ap.amount,
            ap.payment_type as type,
            ap.payment_method as method,
            ap.reference_type,
            ap.reference_id,
            ap.notes,
            a.name as agent_name
         FROM agent_payments ap
         LEFT JOIN agents a ON ap.agent_id = a.id
         WHERE ap.user_id = %d
         ORDER BY ap.payment_date DESC
         LIMIT 20",
        $userId
    );
    
    $result = $mysqli->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $paymentHistory[] = [
                'payment_id' => intval($row['payment_id']),
                'date' => $row['date'],
                'amount' => floatval($row['amount']),
                'type' => $row['type'],
                'method' => $row['method'],
                'agent_name' => $row['agent_name'],
                'notes' => $row['notes']
            ];
        }
    }
    
    // Additional payment history from invoice payments
    $sql = sprintf(
        "SELECT 
            p.id as payment_id,
            p.date,
            p.amount,
            'invoice_payment' as type,
            pt.value as method,
            i.id as invoice_id,
            p.notes
         FROM payment p
         LEFT JOIN invoice i ON p.invoice_id = i.id
         LEFT JOIN payment_type pt ON p.type_id = pt.id
         WHERE i.user_id = %d
         ORDER BY p.date DESC
         LIMIT 10",
        $userId
    );
    
    $result = $mysqli->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $paymentHistory[] = [
                'payment_id' => intval($row['payment_id']),
                'date' => $row['date'],
                'amount' => floatval($row['amount']),
                'type' => $row['type'],
                'method' => $row['method'],
                'invoice_id' => intval($row['invoice_id']),
                'notes' => $row['notes']
            ];
        }
    }
    
    // Sort combined payment history by date
    usort($paymentHistory, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    $paymentHistory = array_slice($paymentHistory, 0, 20);
    
    // Get bundle history
    $bundleHistory = [];
    $sql = sprintf(
        "SELECT * FROM user_bundles 
         WHERE user_id = %d 
         ORDER BY purchase_date DESC 
         LIMIT 20",
        $userId
    );
    
    $result = $mysqli->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $bundleHistory[] = [
                'bundle_id' => intval($row['id']),
                'plan_name' => $row['plan_name'],
                'purchase_date' => $row['purchase_date'],
                'activation_date' => $row['activation_date'],
                'expiry_date' => $row['expiry_date'],
                'status' => $row['status'],
                'amount' => floatval($row['purchase_amount'])
            ];
        }
    }
    
    // Get usage summary from radacct
    $usageSummary = [
        'total_traffic_used_mb' => 0,
        'total_time_used_minutes' => 0,
        'total_sessions' => 0,
        'last_session' => null
    ];
    
    $sql = sprintf(
        "SELECT 
            COUNT(*) as session_count,
            COALESCE(SUM((acctinputoctets + acctoutputoctets) / 1048576), 0) as total_mb,
            COALESCE(SUM(acctsessiontime / 60), 0) as total_minutes,
            MAX(acctstarttime) as last_session
         FROM radacct 
         WHERE username = '%s'",
        $username_esc
    );
    
    $result = $mysqli->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        $usageSummary = [
            'total_traffic_used_mb' => floatval($row['total_mb']),
            'total_time_used_minutes' => floatval($row['total_minutes']),
            'total_sessions' => intval($row['session_count']),
            'last_session' => $row['last_session']
        ];
    }
    
    $mysqli->close();
    
    // Build response
    apiSendSuccess([
        'user' => [
            'username' => $user['username'],
            'personal_info' => [
                'firstname' => $user['firstname'],
                'lastname' => $user['lastname'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'address' => $user['address'],
                'city' => $user['city'],
                'country' => $user['country']
            ],
            'subscription' => [
                'type' => $user['type_name'],
                'type_display' => $user['subscription_type_display'],
                'plan_name' => $user['planName'],
                'status' => $user['billstatus']
            ],
            'balances' => [
                'money_balance' => floatval($user['money_balance']),
                'traffic_balance_mb' => floatval($user['traffic_balance']),
                'timebank_balance_minutes' => floatval($user['timebank_balance']),
                'last_balance_update' => $user['last_balance_update']
            ],
            'active_bundle' => $activeBundle,
            'payment_history' => $paymentHistory,
            'bundle_history' => $bundleHistory,
            'usage_summary' => $usageSummary
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($mysqli)) $mysqli->close();
    apiSendError('Internal server error: ' . $e->getMessage(), 500);
}

