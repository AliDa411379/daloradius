<?php
/**
 * Bulk Balance Topup API
 * Add balance to multiple users at once
 * 
 * POST: /api/agent_bulk_topup_balance.php
 * 
 * Request Body:
 * {
 *   "agent_id": 1,
 *   "users": [
 *     {"username": "user1", "amount": 100.00},
 *     {"username": "user2", "amount": 50.00}
 *   ],
 *   "notes": "Bulk topup for January"
 * }
 */

require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/auth.php');
require_once(__DIR__ . '/../../common/library/BalanceManager.php');
require_once(__DIR__ . '/../../common/library/balance_functions.php');

// Handle preflight
apiHandlePreflight();

// Authenticate
apiAuthenticate();

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiSendError('Method not allowed', 405);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    apiSendError('Invalid JSON input');
}

// Validate required fields
$agent_id = isset($input['agent_id']) ? intval($input['agent_id']) : 0;
$users = isset($input['users']) ? $input['users'] : array();
$notes = isset($input['notes']) ? trim($input['notes']) : 'Bulk balance topup';

if ($agent_id <= 0) {
    apiSendError('Invalid agent_id');
}

if (!is_array($users) || count($users) == 0) {
    apiSendError('Users array is required and must not be empty');
}

if (count($users) > 100) {
    apiSendError('Maximum 100 users per bulk operation');
}

try {
    // Database connection
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    if ($db->connect_error) {
        throw new Exception('Database connection failed: ' . $db->connect_error);
    }
    
    $db->set_charset('utf8mb4');
    
    // Verify agent exists
    $stmt = $db->prepare("SELECT name FROM daloagents WHERE id = ? AND is_deleted = 0");
    $stmt->bind_param("i", $agent_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Agent not found or is deleted');
    }
    
    $agent_data = $result->fetch_assoc();
    $agent_name = $agent_data['name'];
    $stmt->close();
    
    // Initialize BalanceManager
    $balanceManager = new BalanceManager($db);
    
    // Process results
    $results = array(
        'total_users' => count($users),
        'successful' => 0,
        'failed' => 0,
        'total_amount' => 0,
        'details' => array()
    );
    
    // Start transaction for atomicity
    $db->begin_transaction();
    
    try {
        foreach ($users as $user_data) {
            $username = isset($user_data['username']) ? trim($user_data['username']) : '';
            $amount = isset($user_data['amount']) ? floatval($user_data['amount']) : 0;
            
            $user_result = array(
                'username' => $username,
                'amount' => $amount,
                'success' => false,
                'message' => ''
            );
            
            // Validate user data
            if (empty($username)) {
                $user_result['message'] = 'Username is empty';
                $results['details'][] = $user_result;
                $results['failed']++;
                continue;
            }
            
            if ($amount <= 0) {
                $user_result['message'] = 'Amount must be positive';
                $results['details'][] = $user_result;
                $results['failed']++;
                continue;
            }
            
            if ($amount > 300000) {
                $user_result['message'] = 'Amount exceeds maximum (300,000)';
                $results['details'][] = $user_result;
                $results['failed']++;
                continue;
            }
            
            // Check if user exists and belongs to agent
            $stmt = $db->prepare("SELECT u.id, ub.money_balance 
                                   FROM userinfo u
                                   LEFT JOIN userbillinfo ub ON u.username = ub.username
                                   INNER JOIN user_agent ua ON u.id = ua.user_id
                                   WHERE u.username = ? AND ua.agent_id = ?");
            $stmt->bind_param("si", $username, $agent_id);
            $stmt->execute();
            $user_check = $stmt->get_result();
            
            if ($user_check->num_rows === 0) {
                $user_result['message'] = 'User not found or not assigned to this agent';
                $results['details'][] = $user_result;
                $results['failed']++;
                $stmt->close();
                continue;
            }
            
            $user_info = $user_check->fetch_assoc();
            $old_balance = floatval($user_info['money_balance']);
            $stmt->close();
            
            // Add balance
            $balance_result = $balanceManager->addBalance(
                $username,
                $amount,
                'money',
                $notes . ' (Agent: ' . $agent_name . ')',
                'agent_api'
            );
            
            if ($balance_result['success']) {
                $user_result['success'] = true;
                $user_result['old_balance'] = $old_balance;
                $user_result['new_balance'] = $old_balance + $amount;
                $user_result['message'] = 'Balance added successfully';
                
                // Handle reactivation
                $reactivation = handle_user_reactivation($db, $username, $agent_id);
                $user_result['reactivation'] = $reactivation;
                
                $results['successful']++;
                $results['total_amount'] += $amount;
            } else {
                $user_result['message'] = $balance_result['message'];
                $results['failed']++;
            }
            
            $results['details'][] = $user_result;
        }
        
        // Commit transaction
        $db->commit();
        
        // Log the bulk operation
        apiLogRequest(array(
            'agent_id' => $agent_id,
            'total_users' => $results['total_users'],
            'successful' => $results['successful'],
            'failed' => $results['failed'],
            'total_amount' => $results['total_amount']
        ));
        
        apiSendSuccess('Bulk topup completed', $results);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
    $db->close();
    
} catch (Exception $e) {
    apiLogError($e->getMessage());
    apiSendError('Bulk topup failed: ' . $e->getMessage());
}
?>
