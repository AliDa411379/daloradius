-- ========================================
-- DaloRADIUS ERP Integration - Database Schema
-- Dual Subscription Model (Monthly + Prepaid Bundles)
-- ========================================

-- Part 1: Subscription Types
-- ========================================

CREATE TABLE IF NOT EXISTS `subscription_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type_name` varchar(50) NOT NULL COMMENT 'monthly or prepaid',
  `display_name` varchar(100) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type_name` (`type_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Default subscription types
INSERT INTO subscription_types (type_name, display_name, description) VALUES
('monthly', 'Monthly Subscription', 'Fixed monthly billing with invoices generated on 26th'),
('prepaid', 'Prepaid Bundle', 'Pay-as-you-go with balance deduction and bundle activation');

-- Part 2: Billing Plans Extensions
-- ========================================

ALTER TABLE `billing_plans`
ADD COLUMN `subscription_type_id` int(10) unsigned DEFAULT 1 COMMENT 'Link to subscription_types (1=monthly, 2=prepaid)' AFTER `planActive`,
ADD COLUMN `is_bundle` tinyint(1) DEFAULT 0 COMMENT '1 for prepaid bundles, 0 for monthly plans' AFTER `subscription_type_id`,
ADD COLUMN `bundle_validity_days` int(11) DEFAULT NULL COMMENT 'Validity period for bundles in days' AFTER `is_bundle`,
ADD COLUMN `bundle_validity_hours` int(11) DEFAULT NULL COMMENT 'Additional hours for bundle validity' AFTER `bundle_validity_days`,
ADD COLUMN `auto_renew` tinyint(1) DEFAULT 0 COMMENT 'Auto-renew from balance if available (future feature)' AFTER `bundle_validity_hours`,
ADD INDEX `idx_subscription_type` (`subscription_type_id`),
ADD INDEX `idx_is_bundle` (`is_bundle`);

-- Part 3: User Billing Info Extensions
-- ========================================

ALTER TABLE `userbillinfo`
ADD COLUMN `subscription_type_id` int(10) unsigned DEFAULT 1 COMMENT 'Current subscription type (1=monthly, 2=prepaid)' AFTER `updateby`,
ADD COLUMN `current_bundle_id` int(32) DEFAULT NULL COMMENT 'Active bundle ID from user_bundles table' AFTER `subscription_type_id`,
ADD COLUMN `bundle_activation_date` datetime DEFAULT NULL COMMENT 'When current bundle was activated' AFTER `current_bundle_id`,
ADD COLUMN `bundle_expiry_date` datetime DEFAULT NULL COMMENT 'When current bundle expires' AFTER `bundle_activation_date`,
ADD COLUMN `bundle_status` enum('active','expired','suspended') DEFAULT NULL COMMENT 'Current bundle status' AFTER `bundle_expiry_date`,
ADD COLUMN `monthly_billing_day` int(2) DEFAULT 26 COMMENT 'Day of month for invoice generation (1-28, default 26)' AFTER `bundle_status`,
ADD INDEX `idx_subscription_type` (`subscription_type_id`),
ADD INDEX `idx_bundle_expiry` (`bundle_expiry_date`),
ADD INDEX `idx_bundle_status` (`bundle_status`),
ADD INDEX `idx_current_bundle` (`current_bundle_id`);

-- Part 4: User Bundles Table
-- ========================================

CREATE TABLE IF NOT EXISTS `user_bundles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(8) unsigned NOT NULL COMMENT 'Reference to userbillinfo.id',
  `username` varchar(128) NOT NULL,
  `plan_id` int(8) NOT NULL COMMENT 'Reference to billing_plans.id',
  `plan_name` varchar(128) NOT NULL,
  `purchase_amount` decimal(10,2) NOT NULL COMMENT 'Amount paid for bundle',
  `purchase_date` datetime NOT NULL,
  `activation_date` datetime DEFAULT NULL COMMENT 'When bundle was activated',
  `expiry_date` datetime DEFAULT NULL COMMENT 'When bundle expires',
  `status` enum('pending','active','expired','cancelled') DEFAULT 'active' COMMENT 'Bundle status - auto-activate on purchase',
  `balance_before` decimal(10,2) NOT NULL COMMENT 'User balance before purchase',
  `balance_after` decimal(10,2) NOT NULL COMMENT 'User balance after purchase',
  `agent_payment_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Link to agent_payments if purchased via agent',
  `created_by` varchar(128) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `notes` text,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_username` (`username`),
  KEY `idx_plan_id` (`plan_id`),
  KEY `idx_status` (`status`),
  KEY `idx_expiry_date` (`expiry_date`),
  KEY `idx_agent_payment` (`agent_payment_id`),
  KEY `idx_purchase_date` (`purchase_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Bundle purchase and activation history';

-- Part 5: Agent Payments Table
-- ========================================

CREATE TABLE IF NOT EXISTS `agent_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `agent_id` int(10) unsigned NOT NULL COMMENT 'Reference to agents.id',
  `user_id` int(8) unsigned NOT NULL COMMENT 'Reference to userbillinfo.id',
  `username` varchar(128) NOT NULL,
  `payment_type` enum('balance_topup','bundle_purchase','invoice_payment') NOT NULL,
  `amount` decimal(10,2) NOT NULL COMMENT 'Amount agent paid',
  `payment_date` datetime NOT NULL,
  `payment_method` varchar(50) DEFAULT 'cash' COMMENT 'cash, bank_transfer, card, etc',
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'bundle, invoice, topup',
  `reference_id` bigint(20) DEFAULT NULL COMMENT 'ID of related record (bundle_id, invoice_id, etc)',
  `user_balance_before` decimal(10,2) DEFAULT NULL,
  `user_balance_after` decimal(10,2) DEFAULT NULL,
  `notes` text,
  `created_by` varchar(128) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_agent_id` (`agent_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_username` (`username`),
  KEY `idx_payment_date` (`payment_date`),
  KEY `idx_payment_type` (`payment_type`),
  KEY `idx_reference` (`reference_type`, `reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Agent payment transactions for users (no agent balance stored)';

-- Part 6: Payment Refunds Table
-- ========================================

CREATE TABLE IF NOT EXISTS `payment_refunds` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `original_payment_type` enum('agent_payment','invoice_payment','balance_topup') NOT NULL,
  `original_payment_id` bigint(20) unsigned NOT NULL,
  `user_id` int(8) unsigned NOT NULL,
  `username` varchar(128) NOT NULL,
  `refund_amount` decimal(10,2) NOT NULL,
  `original_amount` decimal(10,2) NOT NULL,
  `is_partial` tinyint(1) DEFAULT 0,
  `refund_reason` text,
  `user_balance_before` decimal(10,2),
  `user_balance_after` decimal(10,2),
  `refund_date` datetime NOT NULL,
  `performed_by` varchar(128) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_original_payment` (`original_payment_type`, `original_payment_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_username` (`username`),
  KEY `idx_refund_date` (`refund_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Payment refund tracking for audit trail';

-- Part 7: Monthly Subscription Configuration
-- ========================================

CREATE TABLE IF NOT EXISTS `monthly_subscription_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_generation_day` int(2) NOT NULL DEFAULT 26 COMMENT 'Day of month (1-28)',
  `invoice_due_days` int(3) NOT NULL DEFAULT 8 COMMENT 'Days until invoice is due (26th + 8 = 4th next month)',
  `grace_period_days` int(3) NOT NULL DEFAULT 7 COMMENT 'Additional days before suspension after due date',
  `auto_suspend_on_overdue` tinyint(1) DEFAULT 1 COMMENT 'Auto-suspend after grace period',
  `allow_balance_payment` tinyint(1) DEFAULT 1 COMMENT 'Allow payment from user balance',
  `auto_pay_from_balance` tinyint(1) DEFAULT 0 COMMENT 'Auto-deduct from balance when invoice created',
  `send_invoice_email` tinyint(1) DEFAULT 1,
  `send_reminder_email` tinyint(1) DEFAULT 1,
  `reminder_days_before_due` int(2) DEFAULT 3,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Default configuration based on existing system
INSERT INTO monthly_subscription_config (
  invoice_generation_day, invoice_due_days, grace_period_days
) VALUES (26, 8, 7);

-- ========================================
-- Migration Complete
-- ========================================
-- Run this script on your database:
-- mysql -u username -p radius < 01_erp_dual_subscription_schema.sql
