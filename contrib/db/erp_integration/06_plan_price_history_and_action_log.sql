-- Plan Price History & System Action Log
-- Phase 4: Track plan price/config changes
-- Phase 5: System-wide audit trail

-- ============================================
-- 1. Plan Price History Table
-- ============================================
CREATE TABLE IF NOT EXISTS plan_price_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_id INT DEFAULT NULL,
    plan_name VARCHAR(128) NOT NULL,
    field_changed VARCHAR(50) NOT NULL,
    old_value VARCHAR(255) NOT NULL,
    new_value VARCHAR(255) NOT NULL,
    changed_by VARCHAR(128) NOT NULL,
    changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) DEFAULT NULL,
    KEY idx_plan_name (plan_name),
    KEY idx_changed_at (changed_at),
    KEY idx_field_changed (field_changed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 2. System Action Log Table
-- ============================================
CREATE TABLE IF NOT EXISTS system_action_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action_type VARCHAR(50) NOT NULL
        COMMENT 'user_create, user_edit, user_delete, plan_create, plan_edit, plan_delete, bundle_purchase, bundle_change, bundle_cancel, balance_topup, balance_deduct, refund, operator_login, config_change, agent_create',
    target_type VARCHAR(50) DEFAULT NULL
        COMMENT 'user, plan, bundle, operator, agent, config',
    target_id VARCHAR(128) DEFAULT NULL
        COMMENT 'username, plan_name, operator_name, etc.',
    description TEXT NOT NULL
        COMMENT 'Human-readable description of the action',
    old_value TEXT DEFAULT NULL
        COMMENT 'JSON of old values (for edits)',
    new_value TEXT DEFAULT NULL
        COMMENT 'JSON of new values (for edits)',
    performed_by VARCHAR(128) NOT NULL
        COMMENT 'Operator/agent/system who performed the action',
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_action_type (action_type),
    KEY idx_target (target_type, target_id),
    KEY idx_performed_by (performed_by),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 3. Verify tables created
-- ============================================
SELECT TABLE_NAME, ENGINE, TABLE_ROWS
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_NAME IN ('plan_price_history', 'system_action_log')
  AND TABLE_SCHEMA = DATABASE();
