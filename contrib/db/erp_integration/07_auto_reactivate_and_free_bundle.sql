-- Auto-Reactivate Flag & Free Bundle Support
-- Adds auto_reactivate column to userbillinfo for operator-selected auto-renewal

-- 1. Add auto_reactivate flag to userbillinfo (operator chooses per-user)
ALTER TABLE userbillinfo
    ADD COLUMN IF NOT EXISTS auto_reactivate TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'Auto-reactivate bundle on expiry (operator-selected)';

-- 2. Verify column was added
SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT, COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'userbillinfo' AND COLUMN_NAME = 'auto_reactivate';
