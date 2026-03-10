-- =========================================================
-- DaloRADIUS ERP Integration - Consolidated ACL Updates
-- Registers ALL new pages and grants admin (operator_id=1) access
--
-- This file is IDEMPOTENT: safe to run multiple times.
-- It consolidates entries from 02_acl_updates.sql and 03_fix_acl_and_permissions.sql
-- so you only need to run THIS file for all ACL setup.
-- =========================================================

-- Step 1: Clean up any old-format entries (hyphens, .php extensions)
DELETE FROM operators_acl_files WHERE file IN (
    'bundle-purchase.php', 'bundle-list.php', 'bundle-change.php',
    'rep-bundle-purchases.php', 'rep-agent-payments.php', 'agent-payment-new.php',
    'bill-balance-add.php', 'rep-action-history.php'
);
DELETE FROM operators_acl WHERE file IN (
    'bundle-purchase.php', 'bundle-list.php', 'bundle-change.php',
    'rep-bundle-purchases.php', 'rep-agent-payments.php', 'agent-payment-new.php',
    'bill-balance-add.php', 'rep-action-history.php'
);

-- Step 2: Register ALL new pages in operators_acl_files
-- DaloRADIUS converts filenames: bundle-purchase.php -> bundle_purchase
INSERT IGNORE INTO operators_acl_files (file, section, category) VALUES
('bundle_purchase',      'Purchase Bundles for Users',        'Billing'),
('bundle_list',          'List Bundle Purchases',             'Billing'),
('bundle_change',        'Change User Bundle',                'Billing'),
('bill_balance_add',     'Add Balance to User Account',       'Billing'),
('agent_payment_new',    'Record Agent Payment',              'Billing'),
('rep_bundle_purchases', 'Report: Bundle Purchases',          'Reports'),
('rep_agent_payments',   'Report: Agent Payments',            'Reports'),
('rep_action_history',   'Action History / Audit Trail',      'Reports');

-- Step 3: Grant access to Administrator (operator_id = 1)
INSERT IGNORE INTO operators_acl (operator_id, file, access) VALUES
(1, 'bundle_purchase',      1),
(1, 'bundle_list',          1),
(1, 'bundle_change',        1),
(1, 'bill_balance_add',     1),
(1, 'agent_payment_new',    1),
(1, 'rep_bundle_purchases', 1),
(1, 'rep_agent_payments',   1),
(1, 'rep_action_history',   1);

-- Step 4: Verify - should show 8 rows, all with access=1
SELECT f.file, f.section, f.category, COALESCE(a.access, 0) AS access
FROM operators_acl_files f
LEFT JOIN operators_acl a ON f.file = a.file AND a.operator_id = 1
WHERE f.file IN (
    'bundle_purchase', 'bundle_list', 'bundle_change',
    'bill_balance_add', 'agent_payment_new',
    'rep_bundle_purchases', 'rep_agent_payments', 'rep_action_history'
)
ORDER BY f.category, f.file;
