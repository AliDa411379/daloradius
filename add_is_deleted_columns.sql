-- Add is_deleted columns to agents and operators tables if they don't exist
-- This script can be run safely multiple times

USE radius;

-- Check if is_deleted column exists in agents table
SET @col_exists = (SELECT COUNT(*) 
FROM information_schema.columns 
WHERE table_schema = 'radius' 
  AND table_name = 'agents' 
  AND column_name = 'is_deleted');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE agents ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0 COMMENT "1 if agent is deleted, 0 otherwise"',
    'SELECT "Column is_deleted already exists in agents table" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if is_deleted column exists in operators table
SET @col_exists = (SELECT COUNT(*) 
FROM information_schema.columns 
WHERE table_schema = 'radius' 
  AND table_name = 'operators' 
  AND column_name = 'is_deleted');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE operators ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0 COMMENT "1 if operator is deleted, 0 otherwise"',
    'SELECT "Column is_deleted already exists in operators table" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Show the result
SELECT 'is_deleted columns setup completed' AS status;