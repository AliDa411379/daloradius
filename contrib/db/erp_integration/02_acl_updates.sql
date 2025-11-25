-- ACL Updates for ERP Integration
-- Registers new Operator UI pages and grants access to administrator

-- 1. Register new files in operators_acl_files
-- NOTE: DaloRADIUS converts hyphens to underscores in filenames for ACL
INSERT INTO operators_acl_files (file, section, category) VALUES 
('bundle_purchase', 'Purchase Bundles for Users', 'Billing'),
('bundle_list', 'List Bundle Purchases', 'Billing'),
('rep_bundle_purchases', 'Report: Bundle Purchases', 'Reports'),
('rep_agent_payments', 'Report: Agent Payments', 'Reports'),
('agent_payment_new', 'Record Agent Payment', 'Billing');

-- 2. Grant access to Administrator (operator_id = 1)
-- Adjust operator_id if your administrator has a different ID
INSERT INTO operators_acl (operator_id, file, access) VALUES 
(1, 'bundle_purchase', 1),
(1, 'bundle_list', 1),
(1, 'rep_bundle_purchases', 1),
(1, 'rep_agent_payments', 1),
(1, 'agent_payment_new', 1);

-- 3. Grant access to other operators if needed
-- Example: Grant to operator_id 2
-- INSERT INTO operators_acl (operator_id, file, access) VALUES 
-- (2, 'bundle-purchase.php', 1),
-- ...
