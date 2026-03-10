-- ============================================================================
-- DaloRADIUS - Subscription Lifecycle: MySQL Events + Trigger
-- ============================================================================
-- Replaces the old PHP cron scripts (billing.php, suspend.php, reactivate.php)
-- with native MySQL events and a trigger for instant reactivation.
--
-- Architecture:
--   EVENT evt_outdoor_monthly_billing  -> runs daily at 01:00
--   EVENT evt_expire_bundles           -> runs every 15 minutes
--   EVENT evt_reactivate_outdoor       -> runs every 30 minutes (fallback)
--   TRIGGER trg_balance_topup_reactivate -> instant reactivation on balance increase
--
-- The ONLY thing still requiring a PHP cron is CoA Disconnect (radclient).
-- See: contrib/scripts/check_bundle_expiry.php (simplified to CoA-only)
--
-- Prerequisites:
--   The MySQL Event Scheduler must be enabled BEFORE running this script.
--   Ask your DBA to run (as root/SUPER user):
--     SET GLOBAL event_scheduler = ON;
--   Or add to /etc/mysql/my.cnf under [mysqld]:
--     event_scheduler=ON
--   Then restart MySQL: systemctl restart mysql
--
-- Required privileges for this script:
--   CREATE ROUTINE, EVENT, TRIGGER on the radius database
--   Ask your DBA to grant:
--     GRANT CREATE ROUTINE, EVENT, TRIGGER ON radius.* TO 'your_user'@'host';
--     FLUSH PRIVILEGES;
-- ============================================================================

-- NOTE: SET GLOBAL event_scheduler = ON requires SUPER privilege.
-- If you have it, uncomment the next line. Otherwise ask your DBA.
-- SET GLOBAL event_scheduler = ON;

-- ============================================================================
-- 1. Helper table: tracks users who were just blocked (for CoA disconnect)
-- ============================================================================
CREATE TABLE IF NOT EXISTS pending_disconnects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(128) NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed TINYINT(1) DEFAULT 0,
    processed_at DATETIME DEFAULT NULL,
    KEY idx_processed (processed),
    KEY idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Queue for CoA disconnect - processed by lightweight PHP cron';


-- ============================================================================
-- 2. Stored Procedure: Outdoor/ADSL Monthly Billing
-- ============================================================================
DELIMITER //

DROP PROCEDURE IF EXISTS sp_outdoor_monthly_billing //

CREATE PROCEDURE sp_outdoor_monthly_billing()
BEGIN
    DECLARE v_done INT DEFAULT 0;
    DECLARE v_user_id INT;
    DECLARE v_username VARCHAR(128);
    DECLARE v_balance DECIMAL(10,2);
    DECLARE v_plan_name VARCHAR(128);
    DECLARE v_plan_cost DECIMAL(10,2);
    DECLARE v_plan_id INT;
    DECLARE v_period VARCHAR(50);
    DECLARE v_new_balance DECIMAL(10,2);
    DECLARE v_interval_expr VARCHAR(20);
    DECLARE v_billed INT DEFAULT 0;
    DECLARE v_suspended INT DEFAULT 0;

    -- Cursor: outdoor users due for billing
    DECLARE cur CURSOR FOR
        SELECT ub.id, ub.username, ub.money_balance, ub.planName,
               bp.planCost, bp.id AS plan_id, LOWER(IFNULL(bp.planRecurringPeriod, 'monthly'))
        FROM userbillinfo ub
        JOIN billing_plans bp ON ub.planName = bp.planName
        WHERE bp.planActive = 'yes'
          AND bp.planRecurring = 'yes'
          AND (ub.subscription_type_id = 3 OR LOWER(bp.planType) = 'outdoor')
          AND (ub.nextbill <= CURDATE() OR ub.nextbill IS NULL OR ub.nextbill = '0000-00-00')
          AND bp.planCost > 0;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

    OPEN cur;

    billing_loop: LOOP
        FETCH cur INTO v_user_id, v_username, v_balance, v_plan_name,
                       v_plan_cost, v_plan_id, v_period;
        IF v_done THEN
            LEAVE billing_loop;
        END IF;

        -- Duplicate check: skip if already billed this month
        IF EXISTS (
            SELECT 1 FROM billing_history
            WHERE username = v_username
              AND billAction LIKE CONCAT('%Outdoor monthly%', v_plan_name, '%')
              AND YEAR(creationdate) = YEAR(CURDATE())
              AND MONTH(creationdate) = MONTH(CURDATE())
            LIMIT 1
        ) THEN
            ITERATE billing_loop;
        END IF;

        -- Determine interval
        SET v_interval_expr = CASE v_period
            WHEN 'daily'     THEN '1 DAY'
            WHEN 'weekly'    THEN '1 WEEK'
            WHEN 'quarterly' THEN '3 MONTH'
            WHEN 'yearly'    THEN '1 YEAR'
            ELSE '1 MONTH'
        END;

        IF v_balance >= v_plan_cost THEN
            -- Sufficient balance: deduct and advance billing date
            SET v_new_balance = v_balance - v_plan_cost;

            START TRANSACTION;

            UPDATE userbillinfo
            SET money_balance = v_new_balance,
                last_balance_update = NOW(),
                lastbill = CURDATE(),
                nextbill = DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
            WHERE id = v_user_id;

            -- Use dynamic interval via prepared statement workaround:
            -- (MySQL doesn't allow variable in INTERVAL directly in stored proc)
            -- We handle the common cases explicitly
            IF v_period = 'daily' THEN
                UPDATE userbillinfo SET nextbill = DATE_ADD(CURDATE(), INTERVAL 1 DAY) WHERE id = v_user_id;
            ELSEIF v_period = 'weekly' THEN
                UPDATE userbillinfo SET nextbill = DATE_ADD(CURDATE(), INTERVAL 1 WEEK) WHERE id = v_user_id;
            ELSEIF v_period = 'quarterly' THEN
                UPDATE userbillinfo SET nextbill = DATE_ADD(CURDATE(), INTERVAL 3 MONTH) WHERE id = v_user_id;
            ELSEIF v_period = 'yearly' THEN
                UPDATE userbillinfo SET nextbill = DATE_ADD(CURDATE(), INTERVAL 1 YEAR) WHERE id = v_user_id;
            END IF;
            -- Default monthly already set above

            INSERT INTO user_balance_history
                (user_id, username, transaction_type, amount, balance_before, balance_after,
                 reference_type, description, created_by, created_at)
            VALUES
                (v_user_id, v_username, 'debit', -v_plan_cost, v_balance, v_new_balance,
                 'monthly_billing', CONCAT('Outdoor monthly billing for ', v_plan_name),
                 'mysql_event', NOW());

            INSERT INTO billing_history
                (username, planId, billAmount, billAction, creationdate, creationby)
            VALUES
                (v_username, v_plan_id, v_plan_cost,
                 CONCAT('Outdoor monthly billing - ', v_plan_name), NOW(), 'mysql_event');

            COMMIT;
            SET v_billed = v_billed + 1;

        ELSE
            -- Insufficient balance: block user if not already blocked
            IF NOT EXISTS (
                SELECT 1 FROM radusergroup
                WHERE username = v_username AND groupname = 'block_user'
            ) THEN
                INSERT INTO radusergroup (username, groupname, priority)
                VALUES (v_username, 'block_user', 0);

                INSERT INTO billing_history
                    (username, planId, billAmount, billAction, creationdate, creationby)
                VALUES
                    (v_username, v_plan_id, 0,
                     CONCAT('Suspended - insufficient balance for ', v_plan_name,
                            ' (balance=', v_balance, ', cost=', v_plan_cost, ')'),
                     NOW(), 'mysql_event');

                -- Queue for CoA disconnect
                INSERT INTO pending_disconnects (username, reason)
                VALUES (v_username, CONCAT('Insufficient balance for ', v_plan_name));

                SET v_suspended = v_suspended + 1;
            END IF;

            -- Set nextbill to tomorrow so we retry daily
            UPDATE userbillinfo
            SET nextbill = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
            WHERE id = v_user_id;
        END IF;

    END LOOP;

    CLOSE cur;

    -- Log summary to action log
    IF v_billed > 0 OR v_suspended > 0 THEN
        INSERT INTO system_action_log
            (action_type, target_type, description, performed_by, created_at)
        VALUES
            ('outdoor_billing', 'system',
             CONCAT('Outdoor monthly billing: billed=', v_billed, ' suspended=', v_suspended),
             'mysql_event', NOW());
    END IF;
END //

DELIMITER ;


-- ============================================================================
-- 3. Stored Procedure: Expire Bundles + Block Users
-- ============================================================================
DELIMITER //

DROP PROCEDURE IF EXISTS sp_expire_and_block_bundles //

CREATE PROCEDURE sp_expire_and_block_bundles()
BEGIN
    DECLARE v_done INT DEFAULT 0;
    DECLARE v_bundle_id BIGINT;
    DECLARE v_username VARCHAR(128);
    DECLARE v_plan_name VARCHAR(128);
    DECLARE v_user_id INT;
    DECLARE v_expired INT DEFAULT 0;
    DECLARE v_blocked INT DEFAULT 0;

    -- Step 1: Mark expired bundles
    UPDATE user_bundles
    SET status = 'expired'
    WHERE status = 'active'
      AND expiry_date IS NOT NULL
      AND expiry_date <= NOW();

    SET v_expired = ROW_COUNT();

    -- Step 2: Block users who have NO active bundles and are prepaid (type_id=2)
    -- and are not already blocked
    BEGIN
        DECLARE cur CURSOR FOR
            SELECT DISTINCT ub2.username, ub2.plan_name, ubi.id AS user_id
            FROM user_bundles ub2
            JOIN userbillinfo ubi ON ub2.username = ubi.username
            WHERE ub2.status = 'expired'
              AND ub2.expiry_date >= DATE_SUB(NOW(), INTERVAL 1 HOUR)  -- recently expired
              AND ubi.subscription_type_id = 2  -- prepaid only
              AND NOT EXISTS (
                  SELECT 1 FROM user_bundles ub3
                  WHERE ub3.username = ub2.username
                    AND ub3.status = 'active'
                    AND ub3.expiry_date > NOW()
              )
              AND NOT EXISTS (
                  SELECT 1 FROM radusergroup
                  WHERE username = ub2.username AND groupname = 'block_user'
              );

        DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

        OPEN cur;

        block_loop: LOOP
            FETCH cur INTO v_username, v_plan_name, v_user_id;
            IF v_done THEN
                LEAVE block_loop;
            END IF;

            -- Block user
            INSERT INTO radusergroup (username, groupname, priority)
            VALUES (v_username, 'block_user', 0);

            -- Remove from plan group (set priority to 999 to effectively disable)
            UPDATE radusergroup
            SET priority = 999
            WHERE username = v_username
              AND groupname != 'block_user'
              AND groupname != 'daloRADIUS-Disabled-Users';

            -- Log
            INSERT INTO billing_history
                (username, planId, billAmount, billAction, creationdate, creationby)
            VALUES
                (v_username, 0, 0,
                 CONCAT('Bundle expired - blocked: ', v_plan_name), NOW(), 'mysql_event');

            -- Queue for CoA disconnect
            INSERT INTO pending_disconnects (username, reason)
            VALUES (v_username, CONCAT('Bundle expired: ', v_plan_name));

            SET v_blocked = v_blocked + 1;
        END LOOP;

        CLOSE cur;
    END;

    -- Log summary
    IF v_expired > 0 OR v_blocked > 0 THEN
        INSERT INTO system_action_log
            (action_type, target_type, description, performed_by, created_at)
        VALUES
            ('bundle_expiry', 'system',
             CONCAT('Bundle expiry check: expired=', v_expired, ' blocked=', v_blocked),
             'mysql_event', NOW());
    END IF;
END //

DELIMITER ;


-- ============================================================================
-- 4. Stored Procedure: Reactivate Outdoor Users with Sufficient Balance
--    (Fallback - the trigger handles instant reactivation on topup)
-- ============================================================================
DELIMITER //

DROP PROCEDURE IF EXISTS sp_reactivate_outdoor_users //

CREATE PROCEDURE sp_reactivate_outdoor_users()
BEGIN
    DECLARE v_done INT DEFAULT 0;
    DECLARE v_username VARCHAR(128);
    DECLARE v_user_id INT;
    DECLARE v_plan_name VARCHAR(128);
    DECLARE v_plan_cost DECIMAL(10,2);
    DECLARE v_balance DECIMAL(10,2);
    DECLARE v_profile_name VARCHAR(256);
    DECLARE v_reactivated INT DEFAULT 0;

    -- Find blocked outdoor users who have auto_renew enabled and sufficient balance
    DECLARE cur CURSOR FOR
        SELECT ubi.id, ubi.username, ubi.money_balance, ubi.planName,
               COALESCE(NULLIF(bp.planCost, ''), 0),
               (SELECT bpp.profile_name FROM billing_plans_profiles bpp
                WHERE bpp.plan_name = bp.planName LIMIT 1) AS profile_name
        FROM userbillinfo ubi
        JOIN billing_plans bp ON ubi.planName = bp.planName
        WHERE (ubi.subscription_type_id = 3 OR LOWER(bp.planType) = 'outdoor')
          AND bp.auto_renew = 1
          AND ubi.money_balance >= COALESCE(NULLIF(bp.planCost, ''), 0)
          AND COALESCE(NULLIF(bp.planCost, ''), 0) > 0
          AND EXISTS (
              SELECT 1 FROM radusergroup
              WHERE username = ubi.username AND groupname = 'block_user'
          );

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

    OPEN cur;

    reactivate_loop: LOOP
        FETCH cur INTO v_user_id, v_username, v_balance, v_plan_name,
                       v_plan_cost, v_profile_name;
        IF v_done THEN
            LEAVE reactivate_loop;
        END IF;

        -- Remove from block_user group
        DELETE FROM radusergroup
        WHERE username = v_username AND groupname IN ('block_user', 'daloRADIUS-Disabled-Users');

        -- Remove Auth-Type := Reject from radcheck
        DELETE FROM radcheck
        WHERE username = v_username AND attribute = 'Auth-Type' AND value = 'Reject';

        -- Restore plan group with priority 1
        IF v_profile_name IS NOT NULL AND v_profile_name != '' THEN
            IF EXISTS (SELECT 1 FROM radusergroup WHERE username = v_username AND groupname = v_profile_name) THEN
                UPDATE radusergroup SET priority = 1
                WHERE username = v_username AND groupname = v_profile_name;
            ELSE
                INSERT INTO radusergroup (username, groupname, priority)
                VALUES (v_username, v_profile_name, 1);
            END IF;
        END IF;

        -- Log
        INSERT INTO billing_history
            (username, planId, billAmount, billAction, creationdate, creationby)
        VALUES
            (v_username, 0, 0,
             CONCAT('Reactivated - outdoor user, balance=', v_balance, ' >= cost=', v_plan_cost),
             NOW(), 'mysql_event');

        SET v_reactivated = v_reactivated + 1;
    END LOOP;

    CLOSE cur;

    IF v_reactivated > 0 THEN
        INSERT INTO system_action_log
            (action_type, target_type, description, performed_by, created_at)
        VALUES
            ('outdoor_reactivate', 'system',
             CONCAT('Outdoor reactivation (fallback): reactivated=', v_reactivated),
             'mysql_event', NOW());
    END IF;
END //

DELIMITER ;


-- ============================================================================
-- 5. MySQL Events (Scheduled Jobs)
-- ============================================================================

-- Event: Outdoor monthly billing - runs daily at 01:00
DROP EVENT IF EXISTS evt_outdoor_monthly_billing;

CREATE EVENT evt_outdoor_monthly_billing
    ON SCHEDULE EVERY 1 DAY
    STARTS CONCAT(CURDATE(), ' 01:00:00')
    ON COMPLETION PRESERVE
    ENABLE
    COMMENT 'Deduct monthly plan cost from outdoor/ADSL user balances'
DO
    CALL sp_outdoor_monthly_billing();


-- Event: Bundle expiry check - runs every 15 minutes
DROP EVENT IF EXISTS evt_expire_bundles;

CREATE EVENT evt_expire_bundles
    ON SCHEDULE EVERY 15 MINUTE
    ON COMPLETION PRESERVE
    ENABLE
    COMMENT 'Expire bundles and block users with no active bundle'
DO
    CALL sp_expire_and_block_bundles();


-- Event: Outdoor reactivation (fallback) - only for plans with auto_renew = 1
DROP EVENT IF EXISTS evt_reactivate_outdoor;

CREATE EVENT evt_reactivate_outdoor
    ON SCHEDULE EVERY 30 MINUTE
    ON COMPLETION PRESERVE
    ENABLE
    COMMENT 'Fallback: reactivate outdoor users with auto_renew=1 and sufficient balance'
DO
    CALL sp_reactivate_outdoor_users();


-- Event: Cleanup old pending_disconnects (older than 7 days)
DROP EVENT IF EXISTS evt_cleanup_pending_disconnects;

CREATE EVENT evt_cleanup_pending_disconnects
    ON SCHEDULE EVERY 1 DAY
    STARTS CONCAT(CURDATE(), ' 03:00:00')
    ON COMPLETION PRESERVE
    ENABLE
    COMMENT 'Remove old processed disconnect records'
DO
    DELETE FROM pending_disconnects
    WHERE processed = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);


-- ============================================================================
-- 6. Trigger: Auto-reactivation on balance topup ONLY when plan.auto_renew = 1
-- ============================================================================
-- Fires when money_balance increases on userbillinfo.
-- Only reactivates if the user's plan has auto_renew enabled.
-- ============================================================================

DELIMITER //

DROP TRIGGER IF EXISTS trg_balance_topup_reactivate //

CREATE TRIGGER trg_balance_topup_reactivate
AFTER UPDATE ON userbillinfo
FOR EACH ROW
BEGIN
    DECLARE v_plan_cost DECIMAL(10,2) DEFAULT 0;
    DECLARE v_plan_type VARCHAR(50) DEFAULT '';
    DECLARE v_auto_renew TINYINT DEFAULT 0;
    DECLARE v_profile_name VARCHAR(256) DEFAULT NULL;
    DECLARE v_is_blocked INT DEFAULT 0;

    -- Only trigger when balance INCREASES
    IF NEW.money_balance > OLD.money_balance THEN

        -- Get plan info including auto_renew flag
        SELECT COALESCE(NULLIF(bp.planCost, ''), 0),
               LOWER(IFNULL(bp.planType, '')),
               COALESCE(bp.auto_renew, 0),
               (SELECT bpp.profile_name FROM billing_plans_profiles bpp
                WHERE bpp.plan_name = bp.planName LIMIT 1)
        INTO v_plan_cost, v_plan_type, v_auto_renew, v_profile_name
        FROM billing_plans bp
        WHERE bp.planName = NEW.planName
        LIMIT 1;

        -- Only reactivate if plan has auto_renew enabled
        IF v_auto_renew = 1
           AND (NEW.subscription_type_id = 3 OR v_plan_type = 'outdoor')
           AND v_plan_cost > 0
           AND NEW.money_balance >= v_plan_cost THEN

            -- Check if user is currently blocked
            SELECT COUNT(*) INTO v_is_blocked
            FROM radusergroup
            WHERE username = NEW.username AND groupname = 'block_user';

            IF v_is_blocked > 0 THEN
                -- Unblock: remove from block groups
                DELETE FROM radusergroup
                WHERE username = NEW.username
                  AND groupname IN ('block_user', 'daloRADIUS-Disabled-Users');

                -- Remove Auth-Type := Reject
                DELETE FROM radcheck
                WHERE username = NEW.username
                  AND attribute = 'Auth-Type' AND value = 'Reject';

                -- Restore plan group with priority 1
                IF v_profile_name IS NOT NULL AND v_profile_name != '' THEN
                    IF EXISTS (SELECT 1 FROM radusergroup
                               WHERE username = NEW.username AND groupname = v_profile_name) THEN
                        UPDATE radusergroup SET priority = 1
                        WHERE username = NEW.username AND groupname = v_profile_name;
                    ELSE
                        INSERT INTO radusergroup (username, groupname, priority)
                        VALUES (NEW.username, v_profile_name, 1);
                    END IF;
                END IF;

                -- Log the reactivation
                INSERT INTO billing_history
                    (username, planId, billAmount, billAction, creationdate, creationby)
                VALUES
                    (NEW.username, 0, 0,
                     CONCAT('Auto-reactivated on topup (auto_renew=1, balance=', NEW.money_balance,
                            ', cost=', v_plan_cost, ')'),
                     NOW(), 'trigger');
            END IF;
        END IF;
    END IF;
END //

DELIMITER ;


-- ============================================================================
-- 7. Verify everything was created
-- ============================================================================
SELECT 'Stored Procedures:' AS section;
SELECT ROUTINE_NAME, ROUTINE_TYPE
FROM INFORMATION_SCHEMA.ROUTINES
WHERE ROUTINE_SCHEMA = DATABASE()
  AND ROUTINE_NAME IN ('sp_outdoor_monthly_billing', 'sp_expire_and_block_bundles', 'sp_reactivate_outdoor_users');

SELECT 'Events:' AS section;
SELECT EVENT_NAME, STATUS, INTERVAL_VALUE, INTERVAL_FIELD, LAST_EXECUTED
FROM INFORMATION_SCHEMA.EVENTS
WHERE EVENT_SCHEMA = DATABASE()
  AND EVENT_NAME IN ('evt_outdoor_monthly_billing', 'evt_expire_bundles',
                     'evt_reactivate_outdoor', 'evt_cleanup_pending_disconnects');

SELECT 'Triggers:' AS section;
SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE
FROM INFORMATION_SCHEMA.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
  AND TRIGGER_NAME = 'trg_balance_topup_reactivate';

SELECT 'Pending Disconnects Table:' AS section;
SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pending_disconnects';

SELECT '08_subscription_lifecycle_events.sql applied successfully!' AS result;
