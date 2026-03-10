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
                sprintf('Bundle purchase: %s ($%.2f)', $plan['planName'], $bundleCost),
                true // inTransaction - we're already inside BundleManager's transaction
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

            // Activate RADIUS attributes (Expiration, radusergroup, Session-Timeout, etc.)
            $radiusLog = $this->activateBundleRadius($username, $plan['planName'], $expiryDate, $plan);

            $this->db->commit();

            return [
                'success' => true,
                'bundle_id' => $bundleId,
                'expiry_date' => $expiryDate,
                'new_balance' => $balanceAfter,
                'radius_log' => $radiusLog,
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
     * Change bundle (prorate refund + new purchase) in single transaction
     *
     * Flow:
     * 1. Get current active bundle + remaining days
     * 2. Calculate prorate refund: (remaining_days / total_days) * purchase_amount
     * 3. Credit refund to balance
     * 4. Cancel current bundle (status='cancelled')
     * 5. Check balance >= new plan cost (after refund)
     * 6. Deduct new plan cost
     * 7. Create new bundle record
     * 8. Update userbillinfo
     *
     * @param int $userId User ID
     * @param string $username Username
     * @param int $newPlanId New plan ID
     * @param int $agentPaymentId Optional agent payment ID
     * @param string $createdBy Who initiated the change
     * @return array Result with refund_amount, new_cost, bundle_id, new_balance
     */
    public function changeBundle($userId, $username, $newPlanId, $agentPaymentId = null, $createdBy = 'system') {
        try {
            $this->db->begin_transaction();

            // 1. Get current active bundle
            $currentBundle = $this->getActiveBundle($userId);
            if (!$currentBundle) {
                throw new Exception('User has no active bundle to change');
            }

            // 2. Calculate prorate refund
            $activationDate = new \DateTime($currentBundle['activation_date']);
            $expiryDate = new \DateTime($currentBundle['expiry_date']);
            $now = new \DateTime();

            $totalDays = max(1, $activationDate->diff($expiryDate)->days);
            $remainingDays = max(0, $now->diff($expiryDate)->days);

            // Only refund if there are remaining days
            $purchaseAmount = floatval($currentBundle['purchase_amount']);
            $refundAmount = 0;
            if ($remainingDays > 0 && $purchaseAmount > 0) {
                $refundAmount = round(($remainingDays / $totalDays) * $purchaseAmount, 2);
            }

            // 3. Credit refund to balance
            if ($refundAmount > 0) {
                $refundResult = $this->balanceManager->addBalance(
                    $userId,
                    $username,
                    $refundAmount,
                    $createdBy,
                    sprintf('Prorate refund: %s (%d/%d days remaining)',
                            $currentBundle['plan_name'], $remainingDays, $totalDays),
                    'bundle_refund',
                    $currentBundle['id'],
                    true // inTransaction - we're already inside BundleManager's transaction
                );

                if (!$refundResult['success']) {
                    throw new Exception('Failed to credit refund: ' . $refundResult['message']);
                }
            }

            // 4. Cancel current bundle
            $sql = sprintf(
                "UPDATE %s SET status = 'cancelled', notes = CONCAT(IFNULL(notes,''), ' | Changed to new plan by %s on %s. Refund: $%.2f') WHERE id = %d",
                $this->table_bundles,
                $this->db->real_escape_string($createdBy),
                date('Y-m-d H:i:s'),
                $refundAmount,
                $currentBundle['id']
            );

            if (!$this->db->query($sql)) {
                throw new Exception('Failed to cancel current bundle: ' . $this->db->error);
            }

            // 5. Get new plan details
            $newPlan = $this->getBundlePlan($newPlanId);
            if (!$newPlan) {
                throw new Exception('New bundle plan not found');
            }

            if ($newPlan['is_bundle'] != 1 || $newPlan['subscription_type_id'] != 2) {
                throw new Exception('Selected plan is not a prepaid bundle');
            }

            if ($newPlan['planActive'] != 'yes') {
                throw new Exception('New bundle is not active');
            }

            $newCost = floatval($newPlan['planCost']);
            if ($newCost <= 0) {
                throw new Exception('Invalid new bundle cost');
            }

            // 6. Check balance (after refund) >= new cost
            if (!$this->balanceManager->hasSufficientBalance($userId, $newCost)) {
                $currentBalance = $this->balanceManager->getBalance($userId);
                throw new Exception(sprintf(
                    'Insufficient balance after refund. Required: $%.2f, Available: $%.2f (Refund: $%.2f)',
                    $newCost, $currentBalance, $refundAmount
                ));
            }

            $balanceBefore = $this->balanceManager->getBalance($userId);

            // 7. Deduct new plan cost
            $deductResult = $this->balanceManager->deductBalance(
                $userId,
                $username,
                $newCost,
                'bundle',
                null,
                $createdBy,
                sprintf('Bundle change: %s -> %s ($%.2f)', $currentBundle['plan_name'], $newPlan['planName'], $newCost),
                true // inTransaction - we're already inside BundleManager's transaction
            );

            if (!$deductResult['success']) {
                throw new Exception($deductResult['message']);
            }

            $balanceAfter = $deductResult['new_balance'];

            // 8. Calculate new validity period
            $validityDays = intval($newPlan['bundle_validity_days'] ?: 30);
            $validityHours = intval($newPlan['bundle_validity_hours'] ?: 0);

            $activationDate = date('Y-m-d H:i:s');
            $newExpiryDate = date('Y-m-d H:i:s', strtotime("+{$validityDays} days +{$validityHours} hours"));

            // 9. Create new bundle record
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
                    '%s', NOW(), 'Bundle change from %s. Refund: $%.2f'
                )",
                $this->table_bundles,
                $userId,
                $this->db->real_escape_string($username),
                $newPlanId,
                $this->db->real_escape_string($newPlan['planName']),
                $newCost,
                $activationDate,
                $newExpiryDate,
                $balanceBefore,
                $balanceAfter,
                $agentPaymentId !== null ? $agentPaymentId : 'NULL',
                $this->db->real_escape_string($createdBy),
                $this->db->real_escape_string($currentBundle['plan_name']),
                $refundAmount
            );

            if (!$this->db->query($sql)) {
                throw new Exception('Failed to create new bundle record: ' . $this->db->error);
            }

            $newBundleId = $this->db->insert_id;

            // 10. Update userbillinfo
            $sql = sprintf(
                "UPDATE %s SET
                    current_bundle_id = %d,
                    bundle_activation_date = '%s',
                    bundle_expiry_date = '%s',
                    bundle_status = 'active',
                    planName = '%s'
                WHERE id = %d",
                $this->table_userbillinfo,
                $newBundleId,
                $activationDate,
                $newExpiryDate,
                $this->db->real_escape_string($newPlan['planName']),
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
                $newBundleId,
                $userId
            ));

            // Activate RADIUS attributes for new bundle
            $radiusLog = $this->activateBundleRadius($username, $newPlan['planName'], $newExpiryDate, $newPlan);

            $this->db->commit();

            return [
                'success' => true,
                'bundle_id' => $newBundleId,
                'old_plan' => $currentBundle['plan_name'],
                'new_plan' => $newPlan['planName'],
                'refund_amount' => $refundAmount,
                'new_cost' => $newCost,
                'net_charge' => $newCost - $refundAmount,
                'expiry_date' => $newExpiryDate,
                'new_balance' => $balanceAfter,
                'remaining_days_refunded' => $remainingDays,
                'message' => 'Bundle changed successfully'
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Purchase a FREE bundle (for staff/VIP users - no balance deduction)
     *
     * @param int $userId User ID
     * @param string $username Username
     * @param int $planId Plan ID
     * @param string $createdBy Who is granting the free bundle
     * @param string $notes Reason for free bundle
     * @return array Result
     */
    public function purchaseFreeBundle($userId, $username, $planId, $createdBy = 'system', $notes = 'Free bundle - staff') {
        try {
            $this->db->begin_transaction();

            // Cancel any existing active bundle first
            $currentBundle = $this->getActiveBundle($userId);
            if ($currentBundle) {
                $sql = sprintf(
                    "UPDATE %s SET status = 'cancelled', notes = CONCAT(IFNULL(notes,''), ' | Replaced by free bundle') WHERE id = %d",
                    $this->table_bundles,
                    $currentBundle['id']
                );
                $this->db->query($sql);
            }

            // Get plan details
            $plan = $this->getBundlePlan($planId);
            if (!$plan) {
                throw new Exception('Bundle plan not found');
            }

            if ($plan['is_bundle'] != 1) {
                throw new Exception('Selected plan is not a bundle');
            }

            // Calculate validity
            $validityDays = intval($plan['bundle_validity_days'] ?: 30);
            $validityHours = intval($plan['bundle_validity_hours'] ?: 0);

            $activationDate = date('Y-m-d H:i:s');
            $expiryDate = date('Y-m-d H:i:s', strtotime("+{$validityDays} days +{$validityHours} hours"));

            $currentBalance = $this->balanceManager->getBalance($userId);
            if ($currentBalance === false) $currentBalance = 0;

            // Create bundle record with $0 cost
            $sql = sprintf(
                "INSERT INTO %s (
                    user_id, username, plan_id, plan_name,
                    purchase_amount, purchase_date, activation_date, expiry_date,
                    status, balance_before, balance_after, agent_payment_id,
                    created_by, created_at, notes
                ) VALUES (
                    %d, '%s', %d, '%s',
                    0.00, NOW(), '%s', '%s',
                    'active', %.2f, %.2f, NULL,
                    '%s', NOW(), '%s'
                )",
                $this->table_bundles,
                $userId,
                $this->db->real_escape_string($username),
                $planId,
                $this->db->real_escape_string($plan['planName']),
                $activationDate,
                $expiryDate,
                $currentBalance,
                $currentBalance, // Balance unchanged
                $this->db->real_escape_string($createdBy),
                $this->db->real_escape_string($notes)
            );

            if (!$this->db->query($sql)) {
                throw new Exception('Failed to create free bundle record: ' . $this->db->error);
            }

            $bundleId = $this->db->insert_id;

            // Update userbillinfo
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

            // Activate RADIUS attributes for free bundle
            $radiusLog = $this->activateBundleRadius($username, $plan['planName'], $expiryDate, $plan);

            $this->db->commit();

            return [
                'success' => true,
                'bundle_id' => $bundleId,
                'expiry_date' => $expiryDate,
                'new_balance' => $currentBalance,
                'radius_log' => $radiusLog,
                'message' => 'Free bundle granted successfully'
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Auto-reactivate bundles for flagged users
     * Called by cron job. Only reactivates users with auto_reactivate=1
     *
     * @return array Results of reactivation attempts
     */
    public function autoReactivateBundles() {
        $results = [];

        // Find users with expired bundles who have auto_reactivate flag
        $sql = sprintf(
            "SELECT ub.id as bundle_id, ub.user_id, ub.username, ub.plan_id, ub.plan_name,
                    ubi.auto_reactivate, ubi.money_balance
             FROM %s ub
             INNER JOIN %s ubi ON ub.user_id = ubi.id
             WHERE ub.status = 'expired'
               AND ubi.auto_reactivate = 1
               AND ub.expiry_date = (
                   SELECT MAX(ub2.expiry_date) FROM %s ub2
                   WHERE ub2.user_id = ub.user_id AND ub2.status = 'expired'
               )",
            $this->table_bundles,
            $this->table_userbillinfo,
            $this->table_bundles
        );

        $result = $this->db->query($sql);
        if (!$result) {
            return ['success' => false, 'message' => 'Query failed: ' . $this->db->error, 'results' => []];
        }

        while ($row = $result->fetch_assoc()) {
            $balance = floatval($row['money_balance']);
            $planId = intval($row['plan_id']);
            $plan = $this->getBundlePlan($planId);

            if (!$plan || $plan['planActive'] != 'yes') {
                $results[] = [
                    'username' => $row['username'],
                    'success' => false,
                    'reason' => 'Plan no longer active'
                ];
                continue;
            }

            $cost = floatval($plan['planCost']);

            if ($balance >= $cost) {
                // Has balance - purchase normally
                $purchaseResult = $this->purchaseBundle(
                    $row['user_id'],
                    $row['username'],
                    $planId,
                    null,
                    'auto_reactivate'
                );
                $results[] = [
                    'username' => $row['username'],
                    'success' => $purchaseResult['success'],
                    'reason' => $purchaseResult['message'],
                    'type' => 'paid'
                ];
            } else {
                // Insufficient balance - still reactivate as free (operator chose auto-reactivate)
                $freeResult = $this->purchaseFreeBundle(
                    $row['user_id'],
                    $row['username'],
                    $planId,
                    'auto_reactivate',
                    'Auto-reactivated (insufficient balance, operator-approved)'
                );
                $results[] = [
                    'username' => $row['username'],
                    'success' => $freeResult['success'],
                    'reason' => $freeResult['message'],
                    'type' => 'free_reactivation'
                ];
            }
        }

        return ['success' => true, 'reactivated_count' => count($results), 'results' => $results];
    }

    /**
     * Get bundle change preview (calculate refund without executing)
     *
     * @param int $userId User ID
     * @param int $newPlanId New plan ID
     * @return array Preview data
     */
    public function getChangeBundlePreview($userId, $newPlanId) {
        $currentBundle = $this->getActiveBundle($userId);
        if (!$currentBundle) {
            return ['success' => false, 'message' => 'No active bundle'];
        }

        $newPlan = $this->getBundlePlan($newPlanId);
        if (!$newPlan) {
            return ['success' => false, 'message' => 'New plan not found'];
        }

        $activationDate = new \DateTime($currentBundle['activation_date']);
        $expiryDate = new \DateTime($currentBundle['expiry_date']);
        $now = new \DateTime();

        $totalDays = max(1, $activationDate->diff($expiryDate)->days);
        $remainingDays = max(0, $now->diff($expiryDate)->days);

        $purchaseAmount = floatval($currentBundle['purchase_amount']);
        $refundAmount = 0;
        if ($remainingDays > 0 && $purchaseAmount > 0) {
            $refundAmount = round(($remainingDays / $totalDays) * $purchaseAmount, 2);
        }

        $newCost = floatval($newPlan['planCost']);
        $currentBalance = $this->balanceManager->getBalance($userId);
        $balanceAfterRefund = $currentBalance + $refundAmount;
        $canAfford = $balanceAfterRefund >= $newCost;

        return [
            'success' => true,
            'current_plan' => $currentBundle['plan_name'],
            'current_expiry' => $currentBundle['expiry_date'],
            'remaining_days' => $remainingDays,
            'total_days' => $totalDays,
            'purchase_amount' => $purchaseAmount,
            'refund_amount' => $refundAmount,
            'new_plan' => $newPlan['planName'],
            'new_cost' => $newCost,
            'net_charge' => $newCost - $refundAmount,
            'current_balance' => $currentBalance,
            'balance_after_refund' => $balanceAfterRefund,
            'balance_after_change' => $balanceAfterRefund - $newCost,
            'can_afford' => $canAfford
        ];
    }

    /**
     * Activate RADIUS attributes for a bundle (Expiration, radusergroup, Session-Timeout)
     *
     * Called after bundle record is created to ensure FreeRADIUS enforces the bundle.
     *
     * @param string $username Username
     * @param string $planName Plan name (used as RADIUS group)
     * @param string $expiryDate Expiry date (Y-m-d H:i:s)
     * @param array $plan Plan details from billing_plans
     * @return array Log of actions taken
     */
    private function activateBundleRadius($username, $planName, $expiryDate, $plan) {
        $log = [];
        $username_esc = $this->db->real_escape_string($username);
        $planName_esc = $this->db->real_escape_string($planName);

        // 1. Set Expiration in radcheck
        $expiration_value = date('d M Y H:i:s', strtotime($expiryDate));
        $this->db->query("DELETE FROM radcheck WHERE username = '$username_esc' AND attribute = 'Expiration'");
        $exp_esc = $this->db->real_escape_string($expiration_value);
        if ($this->db->query("INSERT INTO radcheck (username, attribute, op, value) VALUES ('$username_esc', 'Expiration', ':=', '$exp_esc')")) {
            $log[] = "Set Expiration to $expiration_value";
        } else {
            $log[] = "Failed to set Expiration: " . $this->db->error;
        }

        // 2. Update radusergroup: remove from disabled/block groups, add RADIUS profile groups
        $this->db->query("DELETE FROM radusergroup WHERE username = '$username_esc' AND groupname = 'daloRADIUS-Disabled-Users'");
        $this->db->query("DELETE FROM radusergroup WHERE username = '$username_esc' AND groupname = 'block_user'");

        // Look up actual RADIUS profile groups from billing_plans_profiles
        $profileResult = $this->db->query("SELECT DISTINCT profile_name FROM billing_plans_profiles WHERE plan_name = '$planName_esc'");
        if ($profileResult && $profileResult->num_rows > 0) {
            while ($prow = $profileResult->fetch_assoc()) {
                $groupName = $prow['profile_name'];
                $groupName_esc = $this->db->real_escape_string($groupName);
                $grp_check = $this->db->query("SELECT id FROM radusergroup WHERE username = '$username_esc' AND groupname = '$groupName_esc' LIMIT 1");
                if ($grp_check && $grp_check->num_rows === 0) {
                    if ($this->db->query("INSERT INTO radusergroup (username, groupname, priority) VALUES ('$username_esc', '$groupName_esc', 1)")) {
                        $log[] = "Assigned to RADIUS group '$groupName'";
                    }
                } else {
                    $log[] = "Already in RADIUS group '$groupName'";
                }
            }
        } else {
            $log[] = "No RADIUS profiles found for plan '$planName'";
        }

        // 3. Set Mikrotik-Rate-Limit if plan has bandwidth settings
        $bwUp = isset($plan['planBandwidthUp']) ? intval($plan['planBandwidthUp']) : 0;
        $bwDown = isset($plan['planBandwidthDown']) ? intval($plan['planBandwidthDown']) : 0;
        if ($bwUp > 0 || $bwDown > 0) {
            $rateLimit = "{$bwUp}k/{$bwDown}k";
            $rateLimit_esc = $this->db->real_escape_string($rateLimit);
            $this->db->query("DELETE FROM radreply WHERE username = '$username_esc' AND attribute = 'Mikrotik-Rate-Limit'");
            $this->db->query("INSERT INTO radreply (username, attribute, op, value) VALUES ('$username_esc', 'Mikrotik-Rate-Limit', ':=', '$rateLimit_esc')");
            $log[] = "Set Mikrotik-Rate-Limit to $rateLimit";
        }

        // 5. Set Mikrotik-Total-Limit and Mikrotik-Total-Limit-Gigawords if plan has traffic limit
        $trafficMB = floatval($plan['planTrafficTotal'] ?? 0);
        if ($trafficMB > 0) {
            $totalBytes = $trafficMB * 1048576;
            $gigawords = floor($totalBytes / 4294967296); // 2^32
            $remainBytes = intval(fmod($totalBytes, 4294967296));

            $this->db->query("DELETE FROM radreply WHERE username = '$username_esc' AND attribute = 'Mikrotik-Total-Limit'");
            $this->db->query("DELETE FROM radreply WHERE username = '$username_esc' AND attribute = 'Mikrotik-Total-Limit-Gigawords'");

            $this->db->query("INSERT INTO radreply (username, attribute, op, value) VALUES ('$username_esc', 'Mikrotik-Total-Limit', ':=', '$remainBytes')");
            $log[] = "Set Mikrotik-Total-Limit to $remainBytes bytes";

            if ($gigawords > 0) {
                $gw = intval($gigawords);
                $this->db->query("INSERT INTO radreply (username, attribute, op, value) VALUES ('$username_esc', 'Mikrotik-Total-Limit-Gigawords', ':=', '$gw')");
                $log[] = "Set Mikrotik-Total-Limit-Gigawords to $gw";
            }

            $log[] = "Total traffic limit: {$trafficMB} MB";
        }

        return $log;
    }

    /**
     * Renew current bundle - full reactivation flow
     *
     * Similar to handle_user_reactivation() but cleaner, using BundleManager internals.
     *
     * Flow:
     * 1. Check if user already in good state (skip if active + not blocked)
     * 2. Find user's current plan (userbillinfo -> latest bundle fallback)
     * 3. Expire any old bundle
     * 4. Deduct balance and create new bundle (purchaseBundle)
     * 5. Unblock user (remove block_user, Disabled-Users, Auth-Type Reject)
     * 6. Update userbillinfo (timebank, traffic, bundle info)
     * 7. Set RADIUS attributes (Expiration, groups, rate-limit, traffic-limit)
     * 8. Associate agent if provided
     *
     * @param int $userId User ID (userbillinfo.id)
     * @param string $username Username
     * @param string $createdBy Who initiated the renewal
     * @param int|null $agentId Optional agent ID to associate
     * @return array Result with bundle_id, expiry_date, new_balance, log, plan details
     */
    public function renewBundle($userId, $username, $createdBy = 'system', $agentId = null) {
        $log = [];
        $username_esc = $this->db->real_escape_string($username);

        try {
            // 1. Check if user is already in good state
            $state_check = $this->db->query("
                SELECT
                    (SELECT COUNT(*) FROM radusergroup WHERE username = '$username_esc' AND groupname = 'block_user') as is_blocked,
                    (SELECT COUNT(*) FROM user_bundles WHERE username = '$username_esc' AND status = 'active' AND expiry_date > NOW()) as has_active_bundle,
                    (SELECT priority FROM radusergroup WHERE username = '$username_esc' AND groupname NOT IN ('block_user','daloRADIUS-Disabled-Users') ORDER BY priority LIMIT 1) as current_priority
            ");

            if ($state_check && $state_check->num_rows > 0) {
                $state = $state_check->fetch_assoc();
                if ($state['is_blocked'] == 0 && $state['has_active_bundle'] > 0 && $state['current_priority'] == 1) {
                    $log[] = "User already active with correct configuration - skipped renewal";
                    return ['success' => true, 'log' => $log, 'skipped' => true, 'message' => 'User already has active bundle'];
                }
            }

            // 2. Find user's current plan
            $sql = sprintf(
                "SELECT bp.*, ubi.planName as ubi_planName
                 FROM %s ubi
                 JOIN %s bp ON ubi.planName = bp.planName
                 WHERE ubi.id = %d",
                $this->table_userbillinfo,
                $this->table_billing_plans,
                $userId
            );
            $result = $this->db->query($sql);

            $plan = null;
            $planId = null;

            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if ($row['is_bundle'] == 1 && $row['planActive'] == 'yes') {
                    $plan = $row;
                    $planId = intval($row['id']);
                }
            }

            // Fallback: get plan from latest bundle record
            if (!$planId) {
                $sql = sprintf(
                    "SELECT bp.*
                     FROM %s ub
                     JOIN %s bp ON ub.plan_id = bp.id
                     WHERE ub.user_id = %d
                     ORDER BY ub.id DESC LIMIT 1",
                    $this->table_bundles,
                    $this->table_billing_plans,
                    $userId
                );
                $result = $this->db->query($sql);

                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    if ($row['is_bundle'] == 1 && $row['planActive'] == 'yes') {
                        $plan = $row;
                        $planId = intval($row['id']);
                    }
                }
            }

            if (!$plan || !$planId) {
                return ['success' => false, 'message' => 'No bundle plan found for user. Cannot renew.', 'log' => $log];
            }

            $planName = $plan['planName'];
            $planCost = floatval($plan['planCost']);
            $log[] = "Found plan: $planName (cost: $planCost)";

            // 3. Check balance
            $currentBalance = $this->balanceManager->getBalance($userId);
            if ($currentBalance < $planCost) {
                $log[] = "Insufficient balance ($currentBalance < $planCost) - cannot renew";
                return [
                    'success' => false,
                    'message' => sprintf('Insufficient balance. Required: %.2f, Available: %.2f', $planCost, $currentBalance),
                    'log' => $log
                ];
            }

            // 4. Expire any existing active bundle
            $activeBundle = $this->getActiveBundle($userId);
            if ($activeBundle) {
                $sql = sprintf(
                    "UPDATE %s SET status = 'expired', notes = CONCAT(IFNULL(notes,''), ' | Expired for renewal by %s on %s') WHERE id = %d",
                    $this->table_bundles,
                    $this->db->real_escape_string($createdBy),
                    date('Y-m-d H:i:s'),
                    $activeBundle['id']
                );
                $this->db->query($sql);

                // Clear current_bundle_id so purchaseBundle doesn't reject
                $sql = sprintf(
                    "UPDATE %s SET current_bundle_id = NULL, bundle_status = 'expired' WHERE id = %d",
                    $this->table_userbillinfo,
                    $userId
                );
                $this->db->query($sql);
                $log[] = "Expired old bundle ID: " . $activeBundle['id'];
            }

            // 5. Purchase same plan (deducts balance, creates bundle, sets RADIUS attributes)
            $purchaseResult = $this->purchaseBundle($userId, $username, $planId, null, $createdBy);

            if (!$purchaseResult['success']) {
                $log[] = "Purchase failed: " . $purchaseResult['message'];
                return ['success' => false, 'message' => $purchaseResult['message'], 'log' => $log];
            }

            $bundleId = $purchaseResult['bundle_id'];
            $expiryDate = $purchaseResult['expiry_date'];
            $newBalance = $purchaseResult['new_balance'];
            $log[] = "Purchased bundle ID: $bundleId, expires: $expiryDate";

            if (isset($purchaseResult['radius_log'])) {
                $log = array_merge($log, $purchaseResult['radius_log']);
            }

            // 6. Unblock user - remove block groups and Auth-Type Reject
            $this->db->query("DELETE FROM radusergroup WHERE username = '$username_esc' AND groupname IN ('block_user', 'daloRADIUS-Disabled-Users')");
            $this->db->query("DELETE FROM radcheck WHERE username = '$username_esc' AND attribute = 'Auth-Type' AND value = 'Reject'");
            $log[] = "User unblocked (removed block_user, Disabled-Users, Auth-Type Reject)";

            // 7. Update userbillinfo timebank and traffic balances
            $timeBank = floatval($plan['planTimeBank'] ?? 0);
            $trafficTotal = floatval($plan['planTrafficTotal'] ?? 0);

            $sql = sprintf(
                "UPDATE %s SET timebank_balance = %.2f, traffic_balance = %.2f WHERE id = %d",
                $this->table_userbillinfo,
                $timeBank,
                $trafficTotal,
                $userId
            );
            $this->db->query($sql);
            $log[] = "Updated timebank_balance=$timeBank, traffic_balance=$trafficTotal";

            // 8. Associate agent if provided
            if ($agentId) {
                $agentId = intval($agentId);
                // Get userinfo.id (not userbillinfo.id)
                $uidRes = $this->db->query("SELECT id FROM userinfo WHERE username = '$username_esc'");
                if ($uidRes && $uidRes->num_rows > 0) {
                    $uid = intval($uidRes->fetch_assoc()['id']);
                    $checkAgent = $this->db->query("SELECT COUNT(*) as cnt FROM user_agent WHERE user_id = $uid AND agent_id = $agentId");
                    if ($checkAgent && $checkAgent->num_rows > 0) {
                        $cnt = $checkAgent->fetch_assoc()['cnt'];
                        if ($cnt == 0) {
                            $this->db->query("INSERT INTO user_agent (user_id, agent_id) VALUES ($uid, $agentId)");
                            $log[] = "Associated with agent ID $agentId";
                        }
                    }
                }
            }

            return [
                'success' => true,
                'bundle_id' => $bundleId,
                'renewed_plan' => $planName,
                'plan_cost' => $planCost,
                'expiry_date' => $expiryDate,
                'new_balance' => $newBalance,
                'log' => $log,
                'message' => "Bundle renewed successfully: $planName"
            ];

        } catch (Exception $e) {
            $log[] = "Exception: " . $e->getMessage();
            return ['success' => false, 'message' => 'Renewal failed: ' . $e->getMessage(), 'log' => $log];
        }
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
