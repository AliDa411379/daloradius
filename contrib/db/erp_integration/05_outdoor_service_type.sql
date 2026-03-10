-- Outdoor Service Type
-- Adds outdoor/fiber subscription type to the system
-- NO ALTER TABLE needed - reuses existing columns: planType, subscription_type_id, address, hotspot_id

-- 1. Add outdoor subscription type
INSERT IGNORE INTO subscription_types (type_name, display_name, description) VALUES
('outdoor', 'Outdoor/Fiber Service', 'Fiber-to-MikroTik connection broadcasting WiFi for single user. Operator-created only.');

-- 2. Verify subscription types
SELECT * FROM subscription_types;
-- Expected:
-- 1 | monthly | Monthly Subscription
-- 2 | prepaid | Prepaid (Bundles)
-- 3 | outdoor | Outdoor/Fiber Service
