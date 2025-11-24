<?php
/**
 * DaloRADIUS Balance System - Balance API
 * 
 * RESTful API for balance operations
 * 
 * Endpoints:
 * - POST /api/balance/add     - Add balance to user account
 * - GET  /api/balance/get     - Get user balance
 * - GET  /api/balance/history - Get balance transaction history
 * 
 * Authentication: API Key or Session
 * 
 * @author DaloRADIUS Balance System
 * @version 1.0
 */

header('Content-Type: application/json');

// Include balance functions
require_once(__DIR__ . '/../common/library/balance_functions.php');

// Database credentials - CHANGE THESE!
define('DB_HOST', '172.30.16.200');
define('DB_USER', 'bassel');
define('DB_PASS', 'bassel_password');
define('DB_NAME', 'radius');

// API Key (optional - set your own)
define('API_KEY', 'your-secret-api-key-here');

// ================== AUTHENTICATION ==================

function authenticate() {
    // Check API key in header
    $api_key = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? $_POST['api_key'] ?? '';
    
    if ($api_key === API_KEY) {
        return ['authenticated' => true, 'user' => 'api'];
    }
    
    // Check session (if called from web interface)
    session_start();
    if (isset($_SESSION['operator_user'])) {
        return ['authenticated' => true, 'user' => $_SESSION['operator_user']];
    }
    
    return ['authenticated' => false, 'user' => null];
}

// ================== HELPER FUNCTIONS ==================

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

function get_request_data() {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'POST' || $method === 'PUT') {
        $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($content_type, 'application/json') !== false) {
            $data = json_decode(file_get_contents('php://input'), true);
            return $data ?? [];
        }
        return $_POST;
    }
    
    return $_GET;
}

function get_client_ip() {
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

// ================== MAIN API HANDLER ==================

try {
    // Authenticate
    $auth = authenticate();
    if (!$auth['authenticated']) {
        send_error('Authentication required', 401);
    }
    
    $operator = $auth['user'];
    $client_ip = get_client_ip();
    
    // Connect to database
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        send_error('Database connection failed', 500);
    }
    $db->set_charset("utf8mb4");
    
    // Get request method and data
    $method = $_SERVER['REQUEST_METHOD'];
    $data = get_request_data();
    
    // Determine action
    $action = $data['action'] ?? $_GET['action'] ?? '';
    
    // ================== API ENDPOINTS ==================
    
    switch ($action) {
        
        // ==================== ADD BALANCE ====================
        case 'add_balance':
            if ($method !== 'POST') {
                send_error('Method not allowed. Use POST', 405);
            }
            
            $username = $data['username'] ?? '';
            $amount = floatval($data['amount'] ?? 0);
            $description = $data['description'] ?? 'Balance credit via API';
            
            if (empty($username)) {
                send_error('Username is required');
            }
            
            if ($amount <= 0) {
                send_error('Amount must be greater than 0');
            }
            
            $result = add_balance($db, $username, $amount, $description, $operator, $client_ip);
            
            if ($result['success']) {
                send_response(true, 'Balance added successfully', $result, 200);
            } else {
                send_error($result['message'], 400);
            }
            break;
        
        // ==================== GET BALANCE ====================
        case 'get_balance':
            $username = $data['username'] ?? '';
            
            if (empty($username)) {
                send_error('Username is required');
            }
            
            $user_balance = get_user_balance($db, $username);
            
            if (!$user_balance) {
                send_error('User not found', 404);
            }
            
            $unpaid_invoices = get_unpaid_invoices($db, $username);
            
            $response_data = [
                'username' => $user_balance['username'],
                'money_balance' => floatval($user_balance['money_balance']),
                'total_invoices_amount' => floatval($user_balance['total_invoices_amount']),
                'last_balance_update' => $user_balance['last_balance_update'],
                'plan_name' => $user_balance['planName'],
                'plan_cost' => floatval($user_balance['planCost']),
                'unpaid_invoices_count' => count($unpaid_invoices),
                'unpaid_invoices' => $unpaid_invoices
            ];
            
            send_response(true, 'Balance retrieved successfully', $response_data, 200);
            break;
        
        // ==================== GET BALANCE HISTORY ====================
        case 'get_history':
            $username = $data['username'] ?? '';
            $limit = intval($data['limit'] ?? 50);
            $offset = intval($data['offset'] ?? 0);
            
            if (empty($username)) {
                send_error('Username is required');
            }
            
            $history = get_balance_history($db, $username, $limit, $offset);
            
            send_response(true, 'History retrieved successfully', [
                'username' => $username,
                'limit' => $limit,
                'offset' => $offset,
                'count' => count($history),
                'history' => $history
            ], 200);
            break;
        
        // ==================== PROCESS PAYMENT ====================
        case 'pay_invoice':
            if ($method !== 'POST') {
                send_error('Method not allowed. Use POST', 405);
            }
            
            $invoice_id = intval($data['invoice_id'] ?? 0);
            $amount = floatval($data['amount'] ?? 0);
            $notes = $data['notes'] ?? 'Payment via API';
            
            if ($invoice_id <= 0) {
                send_error('Invalid invoice ID');
            }
            
            if ($amount <= 0) {
                send_error('Amount must be greater than 0');
            }
            
            $result = process_balance_payment($db, $invoice_id, $amount, $operator, $notes, $client_ip);
            
            if ($result['success']) {
                send_response(true, 'Payment processed successfully', $result, 200);
            } else {
                send_error($result['message'], 400);
            }
            break;
        
        // ==================== GET UNPAID INVOICES ====================
        case 'get_unpaid_invoices':
            $username = $data['username'] ?? '';
            
            if (empty($username)) {
                send_error('Username is required');
            }
            
            $invoices = get_unpaid_invoices($db, $username);
            
            send_response(true, 'Unpaid invoices retrieved', [
                'username' => $username,
                'count' => count($invoices),
                'invoices' => $invoices
            ], 200);
            break;
        
        // ==================== PAY ALL INVOICES ====================
        case 'pay_all_invoices':
            if ($method !== 'POST') {
                send_error('Method not allowed. Use POST', 405);
            }
            
            $username = $data['username'] ?? '';
            
            if (empty($username)) {
                send_error('Username is required');
            }
            
            // Get all unpaid invoices
            $unpaid_invoices = get_unpaid_invoices($db, $username);
            
            if (count($unpaid_invoices) === 0) {
                send_response(true, 'No unpaid invoices', ['paid_count' => 0], 200);
            }
            
            $results = [];
            $paid_count = 0;
            $failed_count = 0;
            
            foreach ($unpaid_invoices as $invoice) {
                $outstanding = floatval($invoice['outstanding']);
                
                $result = process_balance_payment(
                    $db,
                    $invoice['id'],
                    $outstanding,
                    $operator,
                    'Bulk payment via API',
                    $client_ip
                );
                
                if ($result['success']) {
                    $paid_count++;
                } else {
                    $failed_count++;
                }
                
                $results[] = [
                    'invoice_id' => $invoice['id'],
                    'amount' => $outstanding,
                    'success' => $result['success'],
                    'message' => $result['message']
                ];
            }
            
            send_response(true, "Processed $paid_count of " . count($unpaid_invoices) . " invoices", [
                'total_invoices' => count($unpaid_invoices),
                'paid_count' => $paid_count,
                'failed_count' => $failed_count,
                'details' => $results
            ], 200);
            break;
        
        // ==================== INVALID ACTION ====================
        default:
            send_error('Invalid action. Available actions: add_balance, get_balance, get_history, pay_invoice, get_unpaid_invoices, pay_all_invoices', 400);
    }
    
    $db->close();
    
} catch (Exception $e) {
    send_error('Internal server error: ' . $e->getMessage(), 500);
}
?>