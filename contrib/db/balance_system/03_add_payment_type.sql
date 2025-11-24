-- =========================================================
-- DaloRADIUS Balance System - Migration Script 3
-- Add Balance Deduction payment type
-- =========================================================

USE radius;

-- Add Balance Deduction payment type
INSERT INTO payment_type (value, notes, creationdate, creationby)
SELECT 'Balance Deduction', 'Automatic payment from user account balance - PRIMARY METHOD', NOW(), 'system'
WHERE NOT EXISTS (
    SELECT 1 FROM payment_type WHERE value = 'Balance Deduction'
);

-- Add flag to payment table to identify balance payments
ALTER TABLE payment 
ADD COLUMN IF NOT EXISTS from_balance TINYINT(1) DEFAULT 0 COMMENT '1 if paid from balance, 0 otherwise',
ADD INDEX idx_from_balance (from_balance);

-- Mark all existing payments as NOT from balance
UPDATE payment SET from_balance = 0 WHERE from_balance IS NULL;

SELECT 'Payment type added successfully!' AS Status;