<?php
/**
 * Create Agent API
 * 
 * Creates an agent with a given username and returns the agent ID
 * 
 * POST /api/create_agent.php
 * 
 * @author DaloRADIUS
 * @version 1.0
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/create_agent_api.log');

header('Content-Type: application/json');

$api_path = dirname(__FILE__);
$app_path = dirname(dirname($api_path));

require_once($app_path . '/common/includes/config_read.php');

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

function generate_random_string($length = 12) {
    $characters = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $result = '';
    for ($i = 0; $i < $length; $i++) {
        $result .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $result;
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
    
    $username = $data['username'] ?? '';
    
    if (empty($username)) {
        send_error('username is required');
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
    
    $username = $db->real_escape_string($username);
    
    $check_sql = "SELECT id FROM " . $configValues['CONFIG_DB_TBL_DALOAGENTS'] . " WHERE name = '$username' AND is_deleted = 0";
    $check_result = $db->query($check_sql);
    if ($check_result && $check_result->num_rows > 0) {
        send_error('Agent with this username already exists', 400);
    }
    
    $check_operator_sql = "SELECT id FROM " . $configValues['CONFIG_DB_TBL_DALOOPERATORS'] . " WHERE username = '$username'";
    $check_operator_result = $db->query($check_operator_sql);
    if ($check_operator_result && $check_operator_result->num_rows > 0) {
        send_error('Operator with this username already exists', 400);
    }
    
    $currDate = date('Y-m-d H:i:s');
    $api_user = $db->real_escape_string($auth['user']);
    $operator_password = generate_random_string(12);
    $password_hash = strtoupper(md5($operator_password));
    
    $insert_agent_sql = "INSERT INTO " . $configValues['CONFIG_DB_TBL_DALOAGENTS'] . 
        " (name, is_deleted) VALUES " .
        "('$username', 0)";
    
    $res = $db->query($insert_agent_sql);
    if (!$res) {
        send_error('Failed to create agent: ' . $db->error, 500);
    }
    
    $get_agent_id_sql = "SELECT id FROM " . $configValues['CONFIG_DB_TBL_DALOAGENTS'] . " WHERE name = '$username' AND is_deleted = 0 LIMIT 1";
    $get_agent_id_result = $db->query($get_agent_id_sql);
    if (!$get_agent_id_result || $get_agent_id_result->num_rows == 0) {
        send_error('Failed to retrieve agent ID after creation', 500);
    }
    
    $agent_row = $get_agent_id_result->fetch_assoc();
    $agent_id = intval($agent_row['id']);
    
    $insert_operator_sql = "INSERT INTO " . $configValues['CONFIG_DB_TBL_DALOOPERATORS'] . 
        " (username, password, firstname, lastname, title, department, company, phone1, phone2, email1, email2, messenger1, messenger2, notes, lastlogin, creationdate, creationby, updatedate, updateby, is_agent, is_deleted) VALUES " .
        "('$username', '$password_hash', '$username', 'Agent', '', '', '$username', '', '', '', '', '', '', '', '0000-00-00 00:00:00', '$currDate', '$api_user', '0000-00-00 00:00:00', NULL, 1, 0)";
    
    $res = $db->query($insert_operator_sql);
    if (!$res) {
        $db->query("DELETE FROM " . $configValues['CONFIG_DB_TBL_DALOAGENTS'] . " WHERE id = $agent_id");
        send_error('Failed to create operator: ' . $db->error, 500);
    }
    
    $get_operator_id_sql = "SELECT id FROM " . $configValues['CONFIG_DB_TBL_DALOOPERATORS'] . " WHERE username = '$username' LIMIT 1";
    $get_operator_id_result = $db->query($get_operator_id_sql);
    if (!$get_operator_id_result || $get_operator_id_result->num_rows == 0) {
        send_error('Failed to retrieve operator ID after creation', 500);
    }
    
    $operator_row = $get_operator_id_result->fetch_assoc();
    $operator_id = intval($operator_row['id']);
    
    $response_data = [
        'agent_id' => $agent_id,
        'operator_id' => $operator_id,
        'username' => $username,
        'password' => $operator_password,
        'created_at' => $currDate
    ];
    
    $db->close();
    send_response(true, 'Agent and operator created successfully', $response_data, 201);
    
} catch (Exception $e) {
    send_error('Internal server error: ' . $e->getMessage(), 500);
}
?>
