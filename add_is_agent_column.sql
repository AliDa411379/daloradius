-- Add is_agent column to operators table if it doesn't exist
-- This script can be run safely multiple times

USE radius;

-- Check if column exists and add it if it doesn't
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = 'radius' 
  AND table_name = 'operators' 
  AND column_name = 'is_agent';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE operators ADD COLUMN is_agent TINYINT(1) NOT NULL DEFAULT 0 COMMENT "1 if operator is an agent, 0 otherwise"',
    'SELECT "Column is_agent already exists" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Show the result
SELECT 'is_agent column setup completed' AS status;