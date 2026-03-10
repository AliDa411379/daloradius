<?php
/**
 * ActionLogger - System-wide action logging
 *
 * Logs actions to system_action_log table for audit trail.
 * Also handles plan_price_history tracking.
 *
 * @package DaloRADIUS
 */

class ActionLogger {

    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Log an action to system_action_log
     *
     * @param string $actionType  e.g. 'user_create', 'plan_edit', 'bundle_purchase'
     * @param string $targetType  e.g. 'user', 'plan', 'bundle', 'operator'
     * @param string $targetId    e.g. username, plan_name
     * @param string $description Human-readable description
     * @param mixed  $oldValue    Old values (array or string, will be JSON-encoded if array)
     * @param mixed  $newValue    New values (array or string, will be JSON-encoded if array)
     * @param string $performedBy Operator/agent name (auto-detects from session if null)
     * @param string $ipAddress   IP address (auto-detects if null)
     * @return bool
     */
    public function log($actionType, $targetType, $targetId, $description,
                        $oldValue = null, $newValue = null, $performedBy = null, $ipAddress = null) {
        try {
            if ($performedBy === null) {
                $performedBy = isset($_SESSION['operator_user']) ? $_SESSION['operator_user'] : 'system';
            }

            if ($ipAddress === null) {
                $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
            }

            if (is_array($oldValue)) {
                $oldValue = json_encode($oldValue, JSON_UNESCAPED_UNICODE);
            }
            if (is_array($newValue)) {
                $newValue = json_encode($newValue, JSON_UNESCAPED_UNICODE);
            }

            $stmt = $this->db->prepare(
                "INSERT INTO system_action_log
                 (action_type, target_type, target_id, description, old_value, new_value, performed_by, ip_address, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );

            $stmt->bind_param('ssssssss',
                $actionType, $targetType, $targetId, $description,
                $oldValue, $newValue, $performedBy, $ipAddress
            );

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("ActionLogger error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log plan price/config changes to plan_price_history
     *
     * @param string $planName    Plan name
     * @param array  $oldValues   Associative array of old field values
     * @param array  $newValues   Associative array of new field values
     * @param string $changedBy   Operator who made the change
     * @param string $ipAddress   IP address
     * @return int Number of changes recorded
     */
    public function logPlanChanges($planName, $oldValues, $newValues, $changedBy = null, $ipAddress = null) {
        if ($changedBy === null) {
            $changedBy = isset($_SESSION['operator_user']) ? $_SESSION['operator_user'] : 'system';
        }
        if ($ipAddress === null) {
            $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
        }

        $monitoredFields = [
            'planCost', 'planSetupCost', 'planTax',
            'planBandwidthUp', 'planBandwidthDown',
            'planTrafficTotal', 'planTrafficUp', 'planTrafficDown',
            'bundle_validity_days', 'bundle_validity_hours',
            'planActive', 'planType', 'is_bundle',
            'planRecurring', 'planRecurringPeriod'
        ];

        $changesRecorded = 0;
        $planId = isset($oldValues['id']) ? intval($oldValues['id']) : null;

        $stmt = $this->db->prepare(
            "INSERT INTO plan_price_history
             (plan_id, plan_name, field_changed, old_value, new_value, changed_by, changed_at, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)"
        );

        foreach ($monitoredFields as $field) {
            $oldVal = isset($oldValues[$field]) ? (string)$oldValues[$field] : '';
            $newVal = isset($newValues[$field]) ? (string)$newValues[$field] : '';

            if ($oldVal !== $newVal) {
                $stmt->bind_param('issssss',
                    $planId, $planName, $field, $oldVal, $newVal, $changedBy, $ipAddress
                );
                if ($stmt->execute()) {
                    $changesRecorded++;
                }
            }
        }

        // Also log to system_action_log if any changes
        if ($changesRecorded > 0) {
            $changedFields = [];
            foreach ($monitoredFields as $field) {
                $oldVal = isset($oldValues[$field]) ? (string)$oldValues[$field] : '';
                $newVal = isset($newValues[$field]) ? (string)$newValues[$field] : '';
                if ($oldVal !== $newVal) {
                    $changedFields[$field] = ['old' => $oldVal, 'new' => $newVal];
                }
            }

            $this->log(
                'plan_edit',
                'plan',
                $planName,
                "Edited plan '$planName': " . implode(', ', array_keys($changedFields)) . " changed",
                $oldValues,
                $newValues
            );
        }

        return $changesRecorded;
    }

    /**
     * Get action history with filters
     *
     * @param array $filters  Associative array of filters
     * @param int   $offset   Pagination offset
     * @param int   $limit    Results per page
     * @return array ['rows' => [...], 'total' => int]
     */
    public function getActionHistory($filters = [], $offset = 0, $limit = 25) {
        $where = [];
        $params = [];
        $types = '';

        if (!empty($filters['action_type'])) {
            $where[] = "action_type = ?";
            $params[] = $filters['action_type'];
            $types .= 's';
        }
        if (!empty($filters['target_type'])) {
            $where[] = "target_type = ?";
            $params[] = $filters['target_type'];
            $types .= 's';
        }
        if (!empty($filters['target_id'])) {
            $where[] = "target_id LIKE ?";
            $params[] = '%' . $filters['target_id'] . '%';
            $types .= 's';
        }
        if (!empty($filters['performed_by'])) {
            $where[] = "performed_by = ?";
            $params[] = $filters['performed_by'];
            $types .= 's';
        }
        if (!empty($filters['date_from'])) {
            $where[] = "created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
            $types .= 's';
        }
        if (!empty($filters['date_to'])) {
            $where[] = "created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
            $types .= 's';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count total
        $countSql = "SELECT COUNT(*) FROM system_action_log $whereClause";
        $countStmt = $this->db->prepare($countSql);
        if (!empty($params)) {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $countStmt->bind_result($total);
        $countStmt->fetch();
        $countStmt->close();

        // Fetch rows
        $sql = "SELECT id, action_type, target_type, target_id, description, old_value, new_value,
                       performed_by, ip_address, created_at
                FROM system_action_log
                $whereClause
                ORDER BY created_at DESC
                LIMIT ?, ?";

        $fetchStmt = $this->db->prepare($sql);
        $params[] = $offset;
        $params[] = $limit;
        $types .= 'ii';
        $fetchStmt->bind_param($types, ...$params);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Get action history for a specific user
     */
    public function getUserHistory($username, $limit = 50) {
        return $this->getActionHistory(['target_id' => $username], 0, $limit);
    }

    /**
     * Get plan price history
     */
    public function getPlanPriceHistory($planName = null, $limit = 50) {
        $sql = "SELECT id, plan_id, plan_name, field_changed, old_value, new_value,
                       changed_by, changed_at, ip_address
                FROM plan_price_history";
        $params = [];
        $types = '';

        if ($planName !== null) {
            $sql .= " WHERE plan_name = ?";
            $params[] = $planName;
            $types .= 's';
        }

        $sql .= " ORDER BY changed_at DESC LIMIT ?";
        $params[] = $limit;
        $types .= 'i';

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }
}
