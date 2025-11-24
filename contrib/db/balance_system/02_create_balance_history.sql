-- =========================================================
-- DaloRADIUS Balance System - Migration Script 2
-- Create balance history table for complete audit trail
-- =========================================================

USE radius;

-- Create balance history table
CREATE TABLE IF NOT EXISTS user_balance_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL COMMENT 'Reference to userbillinfo.id',
    username VARCHAR(128) NOT NULL COMMENT 'Username for quick reference',
    transaction_type ENUM('credit', 'debit', 'payment', 'refund', 'adjustment', 'invoice_created', 'invoice_cancelled') NOT NULL,
    amount DECIMAL(10,2) NOT NULL COMMENT 'Transaction amount (positive for credits, negative for debits)',
    balance_before DECIMAL(10,2) NOT NULL COMMENT 'Balance before transaction',
    balance_after DECIMAL(10,2) NOT NULL COMMENT 'Balance after transaction',
    reference_type VARCHAR(50) DEFAULT NULL COMMENT 'Type of reference: invoice, payment, manual, api, etc',
    reference_id INT DEFAULT NULL COMMENT 'ID of related record (invoice_id, payment_id, etc)',
    description TEXT DEFAULT NULL COMMENT 'Human-readable description',
    created_by VARCHAR(128) DEFAULT NULL COMMENT 'Who created this transaction',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IP address of requester',
    INDEX idx_user_id (user_id),
    INDEX idx_username (username),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_created_at (created_at),
    INDEX idx_reference (reference_type, reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Complete audit trail for all balance transactions';

SELECT 'Balance history table created successfully!' AS Status;