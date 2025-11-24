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

// Include authentication and config
require_once('auth.php');

$api_path = dirname(__FILE__);
$app_path = dirname(dirname($api_path));

require_once($app_path . '/common/includes/config_read.php');

// Helper for generating random strings

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
    
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method !== 'POST') {
        apiSendError('Method not allowed. Use POST', 405);
    }
    
    $data = get_request_data();
    
    $username = $data['username'] ?? '';
    
    if (empty($username)) {
        apiSendError('username is required');
    }
    
    $db = new mysqli(
        $configValues['CONFIG_DB_HOST'],
        $configValues['CONFIG_DB_USER'],
        $configValues['CONFIG_DB_PASS'],
        $configValues['CONFIG_DB_NAME'],
        $configValues['CONFIG_DB_PORT']
    );
    
    if ($db->connect_error) {
        apiSendError('Database connection failed: ' . $db->connect_error, 500);
    }
    
    $db->set_charset("utf8mb4");
    
    $username = $db->real_escape_string($username);
    
    $check_sql = "SELECT id FROM " . $configValues['CONFIG_DB_TBL_DALOAGENTS'] . " WHERE name = '$username' AND is_deleted = 0";
    $check_result = $db->query($check_sql);
    if ($check_result && $check_result->num_rows > 0) {
        apiSendError('Agent with this username already exists', 400);
    }
    
    $check_operator_sql = "SELECT id FROM " . $configValues['CONFIG_DB_TBL_DALOOPERATORS'] . " WHERE username = '$username'";
    $check_operator_result = $db->query($check_operator_sql);
    if ($check_operator_result && $check_operator_result->num_rows > 0) {
        apiSendError('Operator with this username already exists', 400);
    }
    
    $currDate = date('Y-m-d H:i:s');
    $api_user = 'api';  // From auth system
    $operator_password = generate_random_string(12);
    $password_hash = strtoupper(md5($operator_password));
    
    $insert_agent_sql = "INSERT INTO " . $configValues['CONFIG_DB_TBL_DALOAGENTS'] . 
        " (name, is_deleted) VALUES " .
        "('$username', 0)";
    
    $res = $db->query($insert_agent_sql);
    if (!$res) {
        apiSendError('Failed to create agent: ' . $db->error, 500);
    }
    
    $get_agent_id_sql = "SELECT id FROM " . $configValues['CONFIG_DB_TBL_DALOAGENTS'] . " WHERE name = '$username' AND is_deleted = 0 LIMIT 1";
    $get_agent_id_result = $db->query($get_agent_id_sql);
    if (!$get_agent_id_result || $get_agent_id_result->num_rows == 0) {
        apiSendError('Failed to retrieve agent ID after creation', 500);
    }
    
    $agent_row = $get_agent_id_result->fetch_assoc();
    $agent_id = intval($agent_row['id']);
    
    $insert_operator_sql = "INSERT INTO " . $configValues['CONFIG_DB_TBL_DALOOPERATORS'] . 
        " (username, password, firstname, lastname, title, department, company, phone1, phone2, email1, email2, messenger1, messenger2, notes, lastlogin, creationdate, creationby, updatedate, updateby, is_agent, is_deleted) VALUES " .
        "('$username', '$password_hash', '$username', 'Agent', '', '', '$username', '', '', '', '', '', '', '', '0000-00-00 00:00:00', '$currDate', '$api_user', '0000-00-00 00:00:00', NULL, 1, 0)";
    
    $res = $db->query($insert_operator_sql);
    if (!$res) {
        $db->query("DELETE FROM " . $configValues['CONFIG_DB_TBL_DALOAGENTS'] . " WHERE id = $agent_id");
        apiSendError('Failed to create operator: ' . $db->error, 500);
    }
    
    $get_operator_id_sql = "SELECT id FROM " . $configValues['CONFIG_DB_TBL_DALOOPERATORS'] . " WHERE username = '$username' LIMIT 1";
    $get_operator_id_result = $db->query($get_operator_id_sql);
    if (!$get_operator_id_result || $get_operator_id_result->num_rows == 0) {
        apiSendError('Failed to retrieve operator ID after creation', 500);
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
    apiSendSuccess($response_data);
    
} catch (Exception $e) {
    apiSendError('Internal server error: ' . $e->getMessage(), 500);
}
?>
