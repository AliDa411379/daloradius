-- =========================================================
-- DaloRADIUS Balance System - Migration Script 4
-- Add constraints to invoice table
-- =========================================================

USE radius;

-- Add due date field to invoice table if not exists
ALTER TABLE invoice 
ADD COLUMN IF NOT EXISTS due_date DATE DEFAULT NULL COMMENT 'Invoice due date (4th of month)',
ADD INDEX idx_due_date (due_date);

-- Add constraint to invoice items to prevent huge amounts
-- Note: This requires checking if constraint already exists
SET @constraint_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = 'radius' 
    AND TABLE_NAME = 'invoice_items' 
    AND CONSTRAINT_NAME = 'chk_invoice_amount_limit'
);

SET @alter_sql = IF(
    @constraint_exists = 0,
    'ALTER TABLE invoice_items ADD CONSTRAINT chk_invoice_amount_limit CHECK (amount <= 300000.00)',
    'SELECT "Constraint already exists" AS Info'
);

PREPARE stmt FROM @alter_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Invoice constraints added successfully!' AS Status;