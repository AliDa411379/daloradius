<?php
/**
 * DaloRADIUS User Creation API - ERP Integration
 * 
 * Creates a RADIUS user with ERP invoice ID and returns QR code
 * 
 * POST /api/create_user_erp.php
 * 
 * @author DaloRADIUS
 * @version 1.0
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/create_user_erp_api.log');

header('Content-Type: application/json');

$api_path = dirname(__FILE__);
$app_path = dirname(dirname($api_path));

require_once($app_path . '/common/includes/config_read.php');

$billing_file = $app_path . '/operators/include/management/userBilling.php';
if (file_exists($billing_file)) {
    require_once($billing_file);
}

$mikrotik_file = $app_path . '/contrib/scripts/mikrotik_integration_functions.php';
if (file_exists($mikrotik_file)) {
    require_once($mikrotik_file);
}

define('API_KEY', 'your-secret-api-key-here');

function authenticate() {
    $api_key = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? $_POST['api_key'] ?? '';
    
    if (!empty(API_KEY) && $api_key === API_KEY) {
        return ['authenticated' => true, 'user' => 'api'];
    }
    
    session_start();
    if (isset($_SESSION['operator_user'])) {
        return ['authenticated' => true, 'user' => $_SESSION['operator_user']];
    }
    
    return ['authenticated' => false, 'user' => null];
}

function send_response($success, $message, $data = null, $http_code = 200) {
    http_response_code($http_code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    exit;
}

function send_error($message, $http_code = 400) {
    send_response(false, $message, null, $http_code);
}

function generate_random_string($length = 8) {
    $characters = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $result = '';
    for ($i = 0; $i < $length; $i++) {
        $result .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $result;
}

function generate_qrcode($text, $size = 200) {
    $url = urlencode($text);
    return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$url}";
}

function get_request_data() {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'POST') {
        $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($content_type, 'application/json') !== false) {
            $data = json_decode(file_get_contents('php://input'), true);
            return $data ?? [];
        }
        return $_POST;
    }
    
    return $_GET;
}

try {
    global $configValues;
    
    $auth = authenticate();
    if (!$auth['authenticated']) {
        send_error('Authentication required', 401);
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method !== 'POST') {
        send_error('Method not allowed. Use POST', 405);
    }
    
    $data = get_request_data();
    
    $external_invoice_id = $data['external_invoice_id'] ?? '';
    $planName = $data['plan_name'] ?? '';
    $agent_id = $data['agent_id'] ?? 1;
    
    if (empty($external_invoice_id)) {
        send_error('external_invoice_id is required');
    }
    
    if (empty($planName)) {
        send_error('plan_name is required');
    }
    
    $db = new mysqli(
        $configValues['CONFIG_DB_HOST'],
        $configValues['CONFIG_DB_USER'],
        $configValues['CONFIG_DB_PASS'],
        $configValues['CONFIG_DB_NAME'],
        $configValues['CONFIG_DB_PORT']
    );
    
    if ($db->connect_error) {
        send_error('Database connection failed: ' . $db->connect_error, 500);
    }
    
    $db->set_charset("utf8mb4");
    
    $external_invoice_id = $db->real_escape_string($external_invoice_id);
    $planName = $db->real_escape_string($planName);
    
    $check_sql = "SELECT id FROM " . $configValues['CONFIG_DB_TBL_DALOUSERBILLINFO'] . " WHERE external_invoice_id = '$external_invoice_id'";
    $check_result = $db->query($check_sql);
    if ($check_result && $check_result->num_rows > 0) {
        send_error('External Invoice ID already exists', 400);
    }
    
    $plan_sql = "SELECT * FROM " . $configValues['CONFIG_DB_TBL_DALOBILLINGPLANS'] . " WHERE planName = '$planName' LIMIT 1";
    $plan_result = $db->query($plan_sql);
    if (!$plan_result || $plan_result->num_rows == 0) {
        send_error('Plan not found', 404);
    }
    $plan_row = $plan_result->fetch_assoc();
    
    $username = generate_random_string(10);
    $password = generate_random_string(12);
    
    $check_user_sql = "SELECT COUNT(*) as cnt FROM " . $configValues['CONFIG_DB_TBL_RADCHECK'] . " WHERE username = '$username'";
    $check_user_result = $db->query($check_user_sql);
    if ($check_user_result) {
        $user_count = $check_user_result->fetch_assoc();
        if ($user_count['cnt'] > 0) {
            send_error('Username generation failed: collision detected', 500);
        }
    }
    
    $password_hash = strtoupper(md5($password));
    $username_escaped = $db->real_escape_string($username);
    
    $insert_radcheck_sql = "INSERT INTO " . $configValues['CONFIG_DB_TBL_RADCHECK'] . 
        " (id, username, attribute, op, value) VALUES " .
        "(0, '$username_escaped', 'MD5-Password', ':=', '$password_hash')";
    
    $res = $db->query($insert_radcheck_sql);
    if (!$res) {
        send_error('Failed to create user: ' . $db->error, 500);
    }
    
    $currDate = date('Y-m-d H:i:s');
    $api_user = $db->real_escape_string($auth['user']);
    
    $nextBillDate = "0000-00-00";
    $planRecurring = $plan_row['planRecurring'] ?? 'No';
    $planRecurringPeriod = $plan_row['planRecurringPeriod'] ?? '';
    $planRecurringBillingSchedule = $plan_row['planRecurringBillingSchedule'] ?? '';
    
    if ($planRecurring == "Yes" && function_exists('getNextBillingDate')) {
        $nextBillDate = getNextBillingDate($planRecurringPeriod, $planRecurringBillingSchedule);
    }
    
    $planTrafficTotal = $plan_row['planTrafficTotal'] ?? 0;
    $planTimeBank = $plan_row['planTimeBank'] ?? 0;
    
    $insert_billinfo_sql = "INSERT INTO " . $configValues['CONFIG_DB_TBL_DALOUSERBILLINFO'] . 
        " (id, external_invoice_id, username, planname, traffic_balance, timebank_balance, " .
        "  billstatus, nextbill, creationdate, creationby) " .
        " VALUES (0, '$external_invoice_id', '$username_escaped', '$planName', " .
        floatval($planTrafficTotal) . ", " . floatval($planTimeBank) . ", " .
        "'active', '$nextBillDate', '$currDate', '$api_user')";
    
    $res = $db->query($insert_billinfo_sql);
    if (!$res) {
        $db->query("DELETE FROM " . $configValues['CONFIG_DB_TBL_RADCHECK'] . " WHERE username = '$username_escaped'");
        send_error('Failed to create billing info: ' . $db->error, 500);
    }
    
    $userinfo_sql = "INSERT INTO " . $configValues['CONFIG_DB_TBL_DALOUSERINFO'] . 
        " (username, firstname, lastname, email) VALUES " .
        "('$username_escaped', '$username_escaped', 'User', '$username_escaped@local')";
    
    $res = $db->query($userinfo_sql);
    if (!$res) {
        $db->query("DELETE FROM " . $configValues['CONFIG_DB_TBL_RADCHECK'] . " WHERE username = '$username_escaped'");
        $db->query("DELETE FROM " . $configValues['CONFIG_DB_TBL_DALOUSERBILLINFO'] . " WHERE username = '$username_escaped'");
        send_error('Failed to create user info: ' . $db->error, 500);
    }
    
    $profile_sql = "SELECT profile_name FROM " . $configValues['CONFIG_DB_TBL_DALOBILLINGPLANSPROFILES'] . 
        " WHERE plan_name = '$planName'";
    $profile_result = $db->query($profile_sql);
    
    if ($profile_result && $profile_result->num_rows > 0) {
        while ($profile_row = $profile_result->fetch_assoc()) {
            $profile_name = $db->real_escape_string($profile_row['profile_name']);
            $group_insert_sql = "INSERT INTO " . $configValues['CONFIG_DB_TBL_RADUSERGROUP'] . 
                " (username, groupname, priority) VALUES ('$username_escaped', '$profile_name', 1)";
            $db->query($group_insert_sql);
        }
    }
    
    $get_user_id_sql = "SELECT id FROM " . $configValues['CONFIG_DB_TBL_DALOUSERINFO'] . " WHERE username = '$username_escaped' LIMIT 1";
    $get_user_id_result = $db->query($get_user_id_sql);
    if ($get_user_id_result && $get_user_id_result->num_rows > 0) {
        $user_id_row = $get_user_id_result->fetch_assoc();
        $user_id = intval($user_id_row['id']);
        
        $agent_id = intval($agent_id);
        $assign_agent_sql = "INSERT INTO user_agent (user_id, agent_id) VALUES ($user_id, $agent_id)";
        $db->query($assign_agent_sql);
    }
    
    if (function_exists('mikrotik_setup_new_user')) {
        mikrotik_setup_new_user($db, $username, $planName);
    }
    
    $qrcode_text = "Username: $username\nPassword: $password\nPlan: $planName";
    $qrcode_url = generate_qrcode($qrcode_text);
    
    $response_data = [
        'username' => $username,
        'password' => $password,
        'plan_name' => $planName,
        'external_invoice_id' => $external_invoice_id,
        'qrcode_url' => $qrcode_url,
        'qrcode_text' => $qrcode_text,
        'created_at' => $currDate,
        'traffic_balance' => floatval($planTrafficTotal),
        'time_balance' => floatval($planTimeBank)
    ];
    
    $db->close();
    send_response(true, 'User created successfully', $response_data, 201);
    
} catch (Exception $e) {
    send_error('Internal server error: ' . $e->getMessage(), 500);
}
?>