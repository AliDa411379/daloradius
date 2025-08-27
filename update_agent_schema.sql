-- SQL script to add operator_id column to agents table
-- Run this manually in your MySQL/MariaDB database

-- Add operator_id column to agents table if it doesn't exist
ALTER TABLE agents ADD COLUMN IF NOT EXISTS operator_id INT(11) NULL COMMENT 'Foreign key to operators table';

-- Add foreign key constraint (optional, will fail silently if already exists)
-- ALTER TABLE agents ADD CONSTRAINT fk_agent_operator FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE SET NULL;

-- Update existing agents to link them with their operators (if possible)
-- This tries to match agents with operators based on company name and email
UPDATE agents a 
SET operator_id = (
    SELECT o.id 
    FROM operators o 
    WHERE o.is_agent = 1 
    AND (
        (a.company != '' AND o.company = a.company) OR
        (a.email != '' AND o.email1 = a.email)
    )
    LIMIT 1
)
WHERE a.operator_id IS NULL 
AND a.is_deleted = 0;

-- Show results
SELECT 
    a.id as agent_id,
    a.name as agent_name,
    a.company as agent_company,
    a.operator_id,
    o.username as operator_username,
    o.firstname as operator_firstname,
    o.lastname as operator_lastname
FROM agents a
LEFT JOIN operators o ON a.operator_id = o.id
WHERE a.is_deleted = 0
ORDER BY a.id;