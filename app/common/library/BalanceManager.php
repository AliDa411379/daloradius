<?php
/**
 * Balance Manager - Centralized Balance Management
 * 
 * Handles all user balance operations with audit trail
 * 
 * @package DaloRADIUS
 * @subpackage Library
 */

class BalanceManager {
    private $db;
    private $table_userbillinfo = 'userbillinfo';
    private $table_balance_history = 'user_balance_history';
    
    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }
    
    /**
     * Add balance to user account (topup)
     * 
     * @param int $userId User ID from userbillinfo
     * @param string $username Username
     * @param float $amount Amount to add
     * @param string $createdBy Who is adding the balance
     * @param string $notes Optional notes
     * @param string $referenceType Optional reference type (agent_payment, manual, api)
     * @param int $referenceId Optional reference ID
     * @return array ['success' => bool, 'new_balance' => float, 'message' => string]
     */
    public function addBalance($userId, $username, $amount, $createdBy, $notes = '', $referenceType = 'manual', $referenceId = null) {
        try {
            // Validate inputs
            if ($amount <= 0) {
                return ['success' => false, 'message' => 'Amount must be greater than zero'];
            }
            
            // Start transaction
            $this->db->begin_transaction();
            
            // Get current balance
            $currentBalance = $this->getBalance($userId);
            if ($currentBalance === false) {
                throw new Exception('User not found');
            }
            
            $newBalance = $currentBalance + $amount;
            
            // Update user balance
            $sql = sprintf(
                "UPDATE %s SET money_balance = %.2f, last_balance_update = NOW() WHERE id = %d",
                $this->table_userbillinfo,
                $newBalance,
                $userId
            );
            
            if (!$this->db->query($sql)) {
                throw new Exception('Failed to update balance: ' . $this->db->error);
            }
            
            // Record in balance history
            $this->recordBalanceHistory(
                $userId,
                $username,
                'credit',
                $amount,
                $currentBalance,
                $newBalance,
                $referenceType,
                $referenceId,
                $notes,
                $createdBy
            );
            
            $this->db->commit();
            
            return [
                'success' => true,
                'new_balance' => $newBalance,
                'message' => 'Balance added successfully'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Deduct balance from user account (for bundle purchase, etc.)
     * 
     * @param int $userId User ID
     * @param string $username Username
     * @param float $amount Amount to deduct
     * @param string $referenceType Reference type (bundle, invoice, etc.)
     * @param int $referenceId Reference ID
     * @param string $createdBy Who is deducting
     * @param string $description Description
     * @return array ['success' => bool, 'new_balance' => float, 'message' => string]
     */
    public function deductBalance($userId, $username, $amount, $referenceType, $referenceId, $createdBy, $description = '') {
        try {
            if ($amount <= 0) {
                return ['success' => false, 'message' => 'Amount must be greater than zero'];
            }
            
            $this->db->begin_transaction();
            
            // Get current balance
            $currentBalance = $this->getBalance($userId);
            if ($currentBalance === false) {
                throw new Exception('User not found');
            }
            
            // Check sufficient balance
            if ($currentBalance < $amount) {
                throw new Exception(sprintf(
                    'Insufficient balance. Current: $%.2f, Required: $%.2f',
                    $currentBalance,
                    $amount
                ));
            }
            
            $newBalance = $currentBalance - $amount;
            
            // Check minimum balance limit (-300000)
            if ($newBalance < -300000.00) {
                throw new Exception('Balance would exceed minimum limit of -$300,000');
            }
            
            // Update user balance
            $sql = sprintf(
                "UPDATE %s SET money_balance = %.2f, last_balance_update = NOW() WHERE id = %d",
                $this->table_userbillinfo,
                $newBalance,
                $userId
            );
            
            if (!$this->db->query($sql)) {
                throw new Exception('Failed to update balance: ' . $this->db->error);
            }
            
            // Record in balance history
            $this->recordBalanceHistory(
                $userId,
                $username,
                'debit',
                -$amount, // Negative for debit
                $currentBalance,
                $newBalance,
                $referenceType,
                $referenceId,
                $description,
                $createdBy
            );
            
            $this->db->commit();
            
            return [
                'success' => true,
                'new_balance' => $newBalance,
                'message' => 'Balance deducted successfully'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Check if user has sufficient balance
     * 
     * @param int $userId User ID
     * @param float $amount Amount to check
     * @return bool True if sufficient balance
     */
    public function hasSufficientBalance($userId, $amount) {
        $balance = $this->getBalance($userId);
        return ($balance !== false && $balance >= $amount);
    }
    
    /**
     * Get current user balance
     * 
     * @param int $userId User ID
     * @return float|false Balance or false if user not found
     */
    public function getBalance($userId) {
        $sql = sprintf(
            "SELECT money_balance FROM %s WHERE id = %d",
            $this->table_userbillinfo,
            $userId
        );
        
        $result = $this->db->query($sql);
        if (!$result || $result->num_rows === 0) {
            return false;
        }
        
        $row = $result->fetch_assoc();
        return floatval($row['money_balance']);
    }
    
    /**
     * Get balance history for user
     * 
     * @param int $userId User ID
     * @param int $limit Number of records to return
     * @param int $offset Offset for pagination
     * @return array|false Array of history records or false on error
     */
    public function getBalanceHistory($userId, $limit = 50, $offset = 0) {
        $sql = sprintf(
            "SELECT * FROM %s 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            $this->table_balance_history,
            $userId,
            $limit,
            $offset
        );
        
        $result = $this->db->query($sql);
        if (!$result) {
            return false;
        }
        
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        
        return $history;
    }
    
    /**
     * Record balance transaction in history
     * 
     * @param int $userId User ID
     * @param string $username Username
     * @param string $transactionType credit, debit, payment, refund, adjustment
     * @param float $amount Transaction amount
     * @param float $balanceBefore Balance before transaction
     * @param float $balanceAfter Balance after transaction
     * @param string $referenceType Reference type
     * @param int $referenceId Reference ID
     * @param string $description Description
     * @param string $createdBy Who created the transaction
     * @return bool Success
     */
    private function recordBalanceHistory($userId, $username, $transactionType, $amount, $balanceBefore, $balanceAfter, $referenceType, $referenceId, $description, $createdBy) {
        $username = $this->db->real_escape_string($username);
        $transactionType = $this->db->real_escape_string($transactionType);
        $referenceType = $this->db->real_escape_string($referenceType);
        $description = $this->db->real_escape_string($description);
        $createdBy = $this->db->real_escape_string($createdBy);
        
        $sql = sprintf(
            "INSERT INTO %s (
                user_id, username, transaction_type, amount,
                balance_before, balance_after, reference_type,
                reference_id, description, created_by, created_at
            ) VALUES (
                %d, '%s', '%s', %.2f,
                %.2f, %.2f, '%s',
                %s, '%s', '%s', NOW()
            )",
            $this->table_balance_history,
            $userId,
            $username,
            $transactionType,
            $amount,
            $balanceBefore,
            $balanceAfter,
            $referenceType,
            $referenceId !== null ? $referenceId : 'NULL',
            $description,
            $createdBy
        );
        
        return $this->db->query($sql);
    }
}
