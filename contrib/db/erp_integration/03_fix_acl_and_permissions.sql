-- Fix Script for ACL and mng-edit Issues
-- Run this script to resolve permission and 500 errors

-- Step 1: Delete old ACL entries with hyphens (if they exist)
DELETE FROM operators_acl_files WHERE file IN ('bundle-purchase.php', 'bundle-list.php', 'rep-bundle-purchases.php', 'rep-agent-payments.php', 'agent-payment-new.php');
DELETE FROM operators_acl WHERE file IN ('bundle-purchase.php', 'bundle-list.php', 'rep-bundle-purchases.php', 'rep-agent-payments.php', 'agent-payment-new.php');

-- Step 2: Insert correct ACL entries with underscores
-- NOTE: DaloRADIUS converts hyphens to underscores in filenames for ACL
INSERT INTO operators_acl_files (file, section, category) VALUES 
('bundle_purchase', 'Purchase Bundles for Users', 'Billing'),
('bundle_list', 'List Bundle Purchases', 'Billing'),
('rep_bundle_purchases', 'Report: Bundle Purchases', 'Reports'),
('rep_agent_payments', 'Report: Agent Payments', 'Reports'),
('agent_payment_new', 'Record Agent Payment', 'Billing');

-- Step 3: Grant access to Administrator (change operator_id if needed)
INSERT INTO operators_acl (operator_id, file, access) VALUES 
(1, 'bundle_purchase', 1),
(1, 'bundle_list', 1),
(1, 'rep_bundle_purchases', 1),
(1, 'rep_agent_payments', 1),
(1, 'agent_payment_new', 1);

-- Step 4: Verify subscription_type column exists in userbillinfo
-- This should return 1 row if the column exists
SELECT COLUMN_NAME, DATA_TYPE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'userbillinfo' 
  AND COLUMN_NAME = 'subscription_type';

-- If the above query returns no results, run this:
-- ALTER TABLE userbillinfo ADD COLUMN subscription_type VARCHAR(50) DEFAULT NULL AFTER planName;

-- Step 5: Verify ACL entries were created correctly
SELECT * FROM operators_acl_files WHERE file LIKE '%bundle%' OR file LIKE '%agent_payment%';
SELECT * FROM operators_acl WHERE file LIKE '%bundle%' OR file LIKE '%agent_payment%';
