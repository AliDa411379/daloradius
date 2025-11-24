-- =========================================================
-- DaloRADIUS Balance System - Migration Script 1
-- Add balance fields to userbillinfo table
-- =========================================================

USE radius;

-- Add money balance field
ALTER TABLE userbillinfo 
ADD COLUMN IF NOT EXISTS money_balance DECIMAL(10,2) DEFAULT 0.00 
COMMENT 'User monetary balance in dollars',
ADD COLUMN IF NOT EXISTS total_invoices_amount DECIMAL(10,2) DEFAULT 0.00 
COMMENT 'Total amount of unpaid invoices',
ADD COLUMN IF NOT EXISTS last_balance_update DATETIME DEFAULT NULL
COMMENT 'Last time balance was modified';

-- Add constraint to enforce minimum balance limit (-300,000)
ALTER TABLE userbillinfo 
ADD CONSTRAINT chk_money_balance_limit 
CHECK (money_balance >= -300000.00);

-- Create index for better performance
CREATE INDEX IF NOT EXISTS idx_money_balance ON userbillinfo(money_balance);
CREATE INDEX IF NOT EXISTS idx_last_balance_update ON userbillinfo(last_balance_update);

-- Update existing users to have 0 balance if NULL
UPDATE userbillinfo SET money_balance = 0.00 WHERE money_balance IS NULL;
UPDATE userbillinfo SET total_invoices_amount = 0.00 WHERE total_invoices_amount IS NULL;

SELECT 'Balance fields added successfully!' AS Status;