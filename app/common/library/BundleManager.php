<?php
/**
 * Bundle Manager - Bundle Purchase and Lifecycle Management
 * 
 * Handles bundle purchases, activation, and expiry
 * Business Logic: Auto-activate, instant block on expiry, one active bundle
 * 
 * @package DaloRADIUS
 * @subpackage Library
 */

require_once('BalanceManager.php');

class BundleManager {
    private $db;
    private $balanceManager;
   
    private $table_bundles = 'user_bundles';
    private $table_userbillinfo = 'userbillinfo';
    private $table_billing_plans = 'billing_plans';
    
    public function __construct($dbConnection) {
        $this->db = $dbConnection;
        $this->balanceManager = new BalanceManager($dbConnection);
    }
    
    /**
     * Purchase bundle and AUTO-ACTIVATE
     * 
     * @param int $userId User ID
     * @param string $username Username
     * @param int $planId Plan/Bundle ID
     * @param int $agentPaymentId Optional agent payment ID if purchased via agent
     * @param string $createdBy Who is purchasing
     * @return array ['success' => bool, 'bundle_id' => int, 'expiry_date' => string, 'message' => string]
     */
    public function purchaseBundle($userId, $username, $planId, $agentPaymentId = null, $createdBy = 'system') {
        try {
            $this->db->begin_transaction();
            
            // Check if user already has an active bundle (only one allowed)
            if ($this->hasActiveBundle($userId)) {
                throw new Exception('User already has an active bundle. Only one bundle allowed at a time.');
            }
            
            // Get bundle/plan details
            $plan = $this->getBundlePlan($planId);
            if (!$plan) {
                throw new Exception('Bundle plan not found');
            }
            
            if ($plan['is_bundle'] != 1 || $plan['subscription_type_id'] != 2) {
                throw new Exception('Selected plan is not a prepaid bundle');
            }
            
            if ($plan['planActive'] != 'yes') {
                throw new Exception('Bundle is not active');
            }
            
            $bundleCost = floatval($plan['planCost']);
            if ($bundleCost <= 0) {
                throw new Exception('Invalid bundle cost');
            }
            
            // Check balance
            if (!$this->balanceManager->hasSufficientBalance($userId, $bundleCost)) {
                $currentBalance = $this->balanceManager->getBalance($userId);
                throw new Exception(sprintf(
                    'Insufficient balance. Required: $%.2f, Available: $%.2f',
                    $bundleCost,
                    $currentBalance
                ));
            }
            
            $balanceBefore = $this->balanceManager->getBalance($userId);
            
            // Deduct balance
            $deductResult = $this->balanceManager->deductBalance(
                $userId,
                $username,
                $bundleCost,
                'bundle',
                null, // Will update with bundle_id later
                $createdBy,
                sprintf('Bundle purchase: %s ($%.2f)', $plan['planName'], $bundleCost)
            );
            
            if (!$deductResult['success']) {
                throw new Exception($deductResult['message']);
            }
            
            $balanceAfter = $deductResult['new_balance'];
            
            // Calculate validity period
            $validityDays = intval($plan['bundle_validity_days'] ?: 30);
            $validityHours = intval($plan['bundle_validity_hours'] ?: 0);
            
            $activationDate = date('Y-m-d H:i:s');
            $expiryDate = date('Y-m-d H:i:s', strtotime("+{$validityDays} days +{$validityHours} hours"));
            
            // Create bundle record (AUTO-ACTIVATED)
            $sql = sprintf(
                "INSERT INTO %s (
                    user_id, username, plan_id, plan_name,
                    purchase_amount, purchase_date, activation_date, expiry_date,
                    status, balance_before, balance_after, agent_payment_id,
                    created_by, created_at, notes
                ) VALUES (
                    %d, '%s', %d, '%s',
                    %.2f, NOW(), '%s', '%s',
                    'active', %.2f, %.2f, %s,
                    '%s', NOW(), 'Auto-activated on purchase'
                )",
                $this->table_bundles,
                $userId,
                $this->db->real_escape_string($username),
                $planId,
                $this->db->real_escape_string($plan['planName']),
                $bundleCost,
                $activationDate,
                $expiryDate,
                $balanceBefore,
                $balanceAfter,
                $agentPaymentId !== null ? $agentPaymentId : 'NULL',
                $this->db->real_escape_string($createdBy)
            );
            
            if (!$this->db->query($sql)) {
                throw new Exception('Failed to create bundle record: ' . $this->db->error);
            }
            
            $bundleId = $this->db->insert_id;
            
            // Update userbillinfo with current bundle info
            $sql = sprintf(
                "UPDATE %s SET 
                    subscription_type_id = 2,
                    current_bundle_id = %d,
                    bundle_activation_date = '%s',
                    bundle_expiry_date = '%s',
                    bundle_status = 'active',
                    planName = '%s'
                WHERE id = %d",
                $this->table_userbillinfo,
                $bundleId,
                $activationDate,
                $expiryDate,
                $this->db->real_escape_string($plan['planName']),
                $userId
            );
            
            if (!$this->db->query($sql)) {
                throw new Exception('Failed to update user bundle info: ' . $this->db->error);
            }
            
            // Update balance history with bundle_id
            $this->db->query(sprintf(
                "UPDATE user_balance_history SET reference_id = %d 
                 WHERE user_id = %d AND reference_type = 'bundle' AND reference_id IS NULL
                 ORDER BY created_at DESC LIMIT 1",
                $bundleId,
                $userId
            ));
            
            $this->db->commit();
            
            return [
                'success' => true,
                'bundle_id' => $bundleId,
                'expiry_date' => $expiryDate,
                'new_balance' => $balanceAfter,
                'message' => 'Bundle purchased and activated successfully'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Check if user has active bundle
     * 
     * @param int $userId User ID
     * @return bool True if has active bundle
     */
    public function hasActiveBundle($userId) {
        $sql = sprintf(
            "SELECT COUNT(*) as count FROM %s 
             WHERE user_id = %d AND status = 'active' AND expiry_date > NOW()",
            $this->table_bundles,
            $userId
        );
        
        $result = $this->db->query($sql);
        if (!$result) {
            return false;
        }
        
        $row = $result->fetch_assoc();
        return ($row['count'] > 0);
    }
    
    /**
     * Get active bundle for user
     * 
     * @param int $userId User ID
     * @return array|false Bundle info or false if none
     */
    public function getActiveBundle($userId) {
        $sql = sprintf(
            "SELECT * FROM %s 
             WHERE user_id = %d AND status = 'active' AND expiry_date > NOW()
             ORDER BY expiry_date DESC LIMIT 1",
            $this->table_bundles,
            $userId
        );
        
        $result = $this->db->query($sql);
        if (!$result || $result->num_rows === 0) {
            return false;
        }
        
        return $result->fetch_assoc();
    }
    
    /**
     * Check and expire bundles
     * Called by cron job
     * 
     * @return array ['expired_count' => int, 'bundles' => array]
     */
    public function checkAndExpireBundles() {
        $expired = [];
        
        // Find expired bundles
        $sql = sprintf(
            "SELECT * FROM %s 
             WHERE status = 'active' AND expiry_date <= NOW()",
            $this->table_bundles
        );
        
        $result = $this->db->query($sql);
        if (!$result) {
            return ['expired_count' => 0, 'bundles' => []];
        }
        
        while ($bundle = $result->fetch_assoc()) {
            if ($this->expireBundle($bundle['id'], $bundle['user_id'])) {
                $expired[] = $bundle;
            }
        }
        
        return ['expired_count' => count($expired), 'bundles' => $expired];
    }
    
    /**
     * Expire a specific bundle
     * 
     * @param int $bundleId Bundle ID
     * @param int $userId User ID
     * @return bool Success
     */
    public function expireBundle($bundleId, $userId) {
        try {
            $this->db->begin_transaction();
            
            // Update bundle status
            $sql = sprintf(
                "UPDATE %s SET status = 'expired' WHERE id = %d",
                $this->table_bundles,
                $bundleId
            );
            
            if (!$this->db->query($sql)) {
                throw new Exception('Failed to update bundle status');
            }
            
            // Update user bundle info
            $sql = sprintf(
                "UPDATE %s SET 
                    bundle_status = 'expired',
                    current_bundle_id = NULL
                WHERE id = %d AND current_bundle_id = %d",
                $this->table_userbillinfo,
                $userId,
                $bundleId
            );
            
            $this->db->query($sql);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    /**
     * Get bundle plan details
     * 
     * @param int $planId Plan ID
     * @return array|false Plan details or false
     */
    private function getBundlePlan($planId) {
        $sql = sprintf(
            "SELECT * FROM %s WHERE id = %d",
            $this->table_billing_plans,
            $planId
        );
        
        $result = $this->db->query($sql);
        if (!$result || $result->num_rows === 0) {
            return false;
        }
        
        return $result->fetch_assoc();
    }
    
    /**
     * Get bundle history for user
     * 
     * @param int $userId User ID
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Bundle history
     */
    public function getBundleHistory($userId, $limit = 50, $offset = 0) {
        $sql = sprintf(
            "SELECT * FROM %s 
             WHERE user_id = %d 
             ORDER BY purchase_date DESC 
             LIMIT %d OFFSET %d",
            $this->table_bundles,
            $userId,
            $limit,
            $offset
        );
        
        $result = $this->db->query($sql);
        if (!$result) {
            return [];
        }
        
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        
        return $history;
    }
}
