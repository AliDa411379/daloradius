<?php
/**
 * RADIUS Access Manager - Subscription-based Access Control
 * 
 * Manages RADIUS group assignments and blocking for both subscription types
 * Integrates with FreeRADIUS via radusergroup table
 * Also handles Mikrotik attribute conversion and setting
 * 
 * @package DaloRADIUS
 * @subpackage Library
 */

// Include Mikrotik integration functions for attribute conversion
require_once(__DIR__ . '/../../../contrib/scripts/mikrotik_integration_functions.php');

class RadiusAccessManager
{
    private $db;
    private $table_radusergroup = 'radusergroup';
    private $table_radcheck = 'radcheck';
    private $table_radreply = 'radreply';
    private $table_billing_plans = 'billing_plans';
    private $table_billing_plans_profiles = 'billing_plans_profiles';
    private $table_billing_history = 'billing_history';
    private $table_userbillinfo = 'userbillinfo';

    const BLOCK_GROUP = 'block_user';

    public function __construct($dbConnection)
    {
        $this->db = $dbConnection;
    }

    /**
     * Grant access by assigning user to plan's RADIUS groups
     * Also sets Mikrotik attributes based on plan
     * 
     * @param string $username Username
     * @param string $planName Plan name
     * @return array ['success' => bool, 'groups_assigned' => int, 'message' => string]
     */
    public function grantAccess($username, $planName)
    {
        try {
            $this->db->begin_transaction();

            // Remove from block_user group first
            $this->removeFromBlockGroup($username);

            // Remove from disabled group
            $username_esc = $this->db->real_escape_string($username);
            $this->db->query("DELETE FROM radusergroup WHERE username='$username_esc' AND groupname='daloRADIUS-Disabled-Users'");

            // Explicitly remove Auth-Type := Reject from radcheck
            $username_esc = $this->db->real_escape_string($username);
            $sql_reject = sprintf(
                "DELETE FROM %s WHERE username='%s' AND attribute='Auth-Type' AND value='Reject'",
                $this->table_radcheck,
                $username_esc
            );
            $this->db->query($sql_reject);

            // Get RADIUS groups/profiles for this plan
            $groups = $this->getPlanProfiles($planName);

            if (empty($groups)) {
                throw new Exception("No RADIUS profiles found for plan: $planName");
            }

            $groupsAssigned = 0;
            $username_esc = $this->db->real_escape_string($username);

            foreach ($groups as $groupname) {
                // Check if already in group
                $check_sql = sprintf(
                    "SELECT COUNT(*) as count FROM %s WHERE username='%s' AND groupname='%s'",
                    $this->table_radusergroup,
                    $username_esc,
                    $this->db->real_escape_string($groupname)
                );

                $result = $this->db->query($check_sql);
                $row = $result->fetch_assoc();

                if ($row['count'] == 0) {
                    // Add to group
                    $sql = sprintf(
                        "INSERT INTO %s (username, groupname, priority) VALUES ('%s', '%s', 1)",
                        $this->table_radusergroup,
                        $username_esc,
                        $this->db->real_escape_string($groupname)
                    );

                    if ($this->db->query($sql)) {
                        $groupsAssigned++;
                    }
                }
            }

            // Set Mikrotik attributes based on plan
            $attributesSet = $this->setMikrotikAttributesForPlan($username, $planName);

            // Log in billing history
            $this->logAccessChange($username, "Access granted - Assigned to plan: $planName");

            $this->db->commit();

            return [
                'success' => true,
                'groups_assigned' => $groupsAssigned,
                'attributes_set' => $attributesSet,
                'message' => "Access granted successfully"
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Revoke access by adding to block_user group
     * 
     * @param string $username Username
     * @param string $reason Reason for blocking
     * @return array ['success' => bool, 'message' => string]
     */
    public function revokeAccess($username, $reason = 'Subscription expired or suspended')
    {
        try {
            $username_esc = $this->db->real_escape_string($username);

            // Check if already in block_user group
            if ($this->isSuspended($username)) {
                return [
                    'success' => true,
                    'message' => 'User already suspended',
                    'already_blocked' => true
                ];
            }

            // Add to block_user group
            $sql = sprintf(
                "INSERT INTO %s (username, groupname, priority) VALUES ('%s', '%s', 0)",
                $this->table_radusergroup,
                $username_esc,
                self::BLOCK_GROUP
            );

            if (!$this->db->query($sql)) {
                throw new Exception('Failed to add to block_user group: ' . $this->db->error);
            }

            // Log in billing history
            $this->logAccessChange($username, "Access revoked - $reason");

            return [
                'success' => true,
                'message' => 'Access revoked successfully',
                'already_blocked' => false
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Check if user is suspended (in block_user group)
     * 
     * @param string $username Username
     * @return bool True if suspended
     */
    public function isSuspended($username)
    {
        $username_esc = $this->db->real_escape_string($username);

        $sql = sprintf(
            "SELECT COUNT(*) as count FROM %s WHERE username='%s' AND groupname='%s'",
            $this->table_radusergroup,
            $username_esc,
            self::BLOCK_GROUP
        );

        $result = $this->db->query($sql);
        if (!$result) {
            return false;
        }

        $row = $result->fetch_assoc();
        return ($row['count'] > 0);
    }

    /**
     * Remove user from block_user group
     * 
     * @param string $username Username
     * @return bool Success
     */
    public function removeFromBlockGroup($username)
    {
        $username_esc = $this->db->real_escape_string($username);

        $sql = sprintf(
            "DELETE FROM %s WHERE username='%s' AND groupname='%s'",
            $this->table_radusergroup,
            $username_esc,
            self::BLOCK_GROUP
        );

        return $this->db->query($sql);
    }

    /**
     * Remove user from all plan-related groups
     * 
     * @param string $username Username
     * @param string $planName Plan name
     * @return bool Success
     */
    public function removeFromPlanGroups($username, $planName)
    {
        $groups = $this->getPlanProfiles($planName);

        if (empty($groups)) {
            return true;
        }

        $username_esc = $this->db->real_escape_string($username);
        $groups_quoted = array_map(function ($g) {
            return "'" . $this->db->real_escape_string($g) . "'";
        }, $groups);

        $sql = sprintf(
            "DELETE FROM %s WHERE username='%s' AND groupname IN (%s)",
            $this->table_radusergroup,
            $username_esc,
            implode(',', $groups_quoted)
        );

        return $this->db->query($sql);
    }

    /**
     * Get RADIUS profiles/groups for a billing plan
     * 
     * @param string $planName Plan name
     * @return array List of group names
     */
    private function getPlanProfiles($planName)
    {
        $planName_esc = $this->db->real_escape_string($planName);

        $sql = sprintf(
            "SELECT DISTINCT profile_name FROM %s WHERE plan_name='%s'",
            $this->table_billing_plans_profiles,
            $planName_esc
        );

        $result = $this->db->query($sql);
        if (!$result) {
            return [];
        }

        $groups = [];
        while ($row = $result->fetch_assoc()) {
            $groups[] = $row['profile_name'];
        }

        return $groups;
    }

    /**
     * Log access change in billing history
     * 
     * @param string $username Username
     * @param string $action Action description
     * @return bool Success
     */
    private function logAccessChange($username, $action)
    {
        $username_esc = $this->db->real_escape_string($username);
        $action_esc = $this->db->real_escape_string($action);

        $sql = sprintf(
            "INSERT INTO %s (username, planId, billAmount, billAction, creationdate, creationby)
             VALUES ('%s', 0, 0, '%s', NOW(), 'system')",
            $this->table_billing_history,
            $username_esc,
            $action_esc
        );

        return $this->db->query($sql);
    }

    /**
     * Reactivate user (remove from block, assign to plan)
     * For use after payment or bundle purchase
     * 
     * @param string $username Username
     * @param string $planName Plan name
     * @return array ['success' => bool, 'message' => string]
     */
    public function reactivateUser($username, $planName)
    {
        try {
            $this->db->begin_transaction();

            // Remove from block_user
            $this->removeFromBlockGroup($username);

            // Grant access to plan groups
            $result = $this->grantAccess($username, $planName);

            if (!$result['success']) {
                throw new Exception($result['message']);
            }

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'User reactivated successfully'
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Set Mikrotik RADIUS attributes based on plan
     * Uses mikrotik_convert_traffic() and mikrotik_convert_time() for consistency
     * 
     * @param string $username Username
     * @param string $planName Plan name
     * @return bool Success status
     */
    private function setMikrotikAttributesForPlan($username, $planName)
    {
        global $configValues;
        try {
            // Get plan details
            $planName_esc = $this->db->real_escape_string($planName);
            $sql = sprintf(
                "SELECT planType, planTimeBank, planTrafficTotal FROM %s WHERE planName = '%s'",
                $this->table_billing_plans,
                $planName_esc
            );

            $result = $this->db->query($sql);
            if (!$result || $result->num_rows === 0) {
                mikrotik_log("Plan $planName not found for Mikrotik attribute setting", 'WARNING');
                return false;
            }

            $plan = $result->fetch_assoc();

            // Get user's current balances
            $username_esc = $this->db->real_escape_string($username);
            $sql = sprintf(
                "SELECT timebank_balance, traffic_balance FROM %s WHERE username = '%s'",
                $this->table_userbillinfo,
                $username_esc
            );

            $result = $this->db->query($sql);
            if (!$result || $result->num_rows === 0) {
                mikrotik_log("User $username not found in userbillinfo", 'WARNING');
                return false;
            }

            $user = $result->fetch_assoc();

            // Determine balances to use (user balance or plan default)
            $traffic_balance = !empty($user['traffic_balance']) ?
                floatval($user['traffic_balance']) :
                floatval($plan['planTrafficTotal'] ?: 0);

            $time_balance = !empty($user['timebank_balance']) ?
                floatval($user['timebank_balance']) :
                floatval($plan['planTimeBank'] ?: 0);

            mikrotik_log("Setting Mikrotik attributes for $username - Plan: $planName, Traffic: {$traffic_balance}MB, Time: {$time_balance}min");

            $success = true;

            // Set traffic attributes using mikrotik_convert_traffic()
            if (stripos($plan['planType'], 'Traffic') !== false || $traffic_balance > 0) {
                $traffic_attrs = mikrotik_convert_traffic($traffic_balance);
                $success &= mikrotik_set_radius_attribute($this->db, $username, 'Mikrotik-Total-Limit-Gigawords', $traffic_attrs['gigawords']);
                $success &= mikrotik_set_radius_attribute($this->db, $username, 'Mikrotik-Total-Limit', $traffic_attrs['bytes']);
            } else {
                $success &= mikrotik_set_radius_attribute($this->db, $username, 'Mikrotik-Total-Limit-Gigawords', '0');
                $success &= mikrotik_set_radius_attribute($this->db, $username, 'Mikrotik-Total-Limit', '0');
            }

            // Set time attributes using mikrotik_convert_time()
            // Set time attributes using mikrotik_convert_time()
            // REPLACED: Session-Timeout with Expiration in radcheck
            if (stripos($plan['planType'], 'Time') !== false || $time_balance > 0) {
                // Calculate expiration date based on current time + time balance (minutes)
                // Note: This assumes continuous usage from now, which is a common interpretation for "Expiration"
                // when replacing Session-Timeout.
                $expiry_timestamp = time() + ($time_balance * 60);
                $expiration_value = date('d M Y H:i', $expiry_timestamp);

                // Use insert_single_attribute from attributes management if available, or raw SQL
                // Since this class uses $this->db which is mysqli usually (or the socket wrapper)

                // We'll reuse the helper but point to Expiration
                // NOTE: mikrotik_set_radius_attribute defaults to radreply usually (depends on implementation)
                // We need to ensure it goes to RADCHECK.
                // The provided mikrotik_set_radius_attribute (in functions file) goes to radreply.
                // We need to use mikrotik_set_radcheck_attribute if available, or raw SQL.

                // RadiusAccessManager doesn't seem to have mikrotik_set_radcheck_attribute available directly 
                // unless explicitly included.
                // Let's implement a direct query for robustness here or use the available helper if we are sure.
                // The viewed file imports 'mikrotik_integration_functions.php' typically?
                // Let's assume we can use raw SQL or a specific helper if defined in this class.
                // Looking at the file content, it calls `mikrotik_set_radius_attribute`.

                // We will add a helper method to this class or use raw SQL to insert into radcheck.
                // Existing method setMikrotikAttributesForPlan uses $this->db.

                // Let's try to use mikrotik_set_radcheck_attribute if it exists (it was in the scripts file),
                // but this is a class file.

                // Safer to use direct SQL injection here to ensure it goes to radcheck.
                $u_esc = $this->db->real_escape_string($username);
                $attr = 'Expiration';
                $op = ':=';
                $val_esc = $this->db->real_escape_string($expiration_value);

                // Delete existing first
                $sql_del = "DELETE FROM " . $configValues['CONFIG_DB_TBL_RADCHECK'] .
                    " WHERE username='$u_esc' AND attribute='$attr'";
                $this->db->query($sql_del);

                $sql_ins = "INSERT INTO " . $configValues['CONFIG_DB_TBL_RADCHECK'] .
                    " (username, attribute, op, value) VALUES ('$u_esc', '$attr', '$op', '$val_esc')";
                $res = $this->db->query($sql_ins);
                $success &= ($res !== false);

                // Remove Session-Timeout from radreply if it exists, to avoid conflicts
                $sql_del_reply = "DELETE FROM radreply WHERE username='$u_esc' AND attribute='Session-Timeout'";
                $this->db->query($sql_del_reply);

            } else {
                // Convert Time limit 0 to explicit expiration? Or just remove it?
                // Usually checks: if Time limit is 0, maybe we don't set expiration based on time.
                // But if we want to enforce "NO ACCESS", we might set past expiration.
                // For now, let's just ensure no Session-Timeout and no Expiration if balance is 0?
                // Or typically, 0 balance means expired.

                // Let's remove Expiration if balance is 0.
                $u_esc = $this->db->real_escape_string($username);
                $sql_del = "DELETE FROM " . $configValues['CONFIG_DB_TBL_RADCHECK'] .
                    " WHERE username='$u_esc' AND attribute='Expiration'";
                $this->db->query($sql_del);

                // Also remove Session-Timeout
                $sql_del_reply = "DELETE FROM radreply WHERE username='$u_esc' AND attribute='Session-Timeout'";
                $this->db->query($sql_del_reply);
            }

            if ($success) {
                mikrotik_log("Successfully set Mikrotik attributes for $username");
            } else {
                mikrotik_log("Some errors occurred while setting Mikrotik attributes for $username", 'WARNING');
            }

            return $success;

        } catch (Exception $e) {
            mikrotik_log("Error setting Mikrotik attributes: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Remove Mikrotik RADIUS attributes for a user
     * 
     * @param string $username Username
     * @return bool Success status
     */
    public function removeMikrotikAttributes($username)
    {
        return mikrotik_remove_user_attributes($this->db, $username);
    }
}
