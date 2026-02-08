-- ======================================================
-- REAL-TIME RADIUS SESSION SYNCHRONIZATION TRIGGER
-- ======================================================
-- This trigger automatically updates User Bill Info and
-- RADIUS Reply attributes (Mikrotik Limits) immediately 
-- after a session ends in radacct.
-- ======================================================

DELIMITER //

DROP TRIGGER IF EXISTS after_radacct_session_stop //

CREATE TRIGGER after_radacct_session_stop
AFTER UPDATE ON radacct
FOR EACH ROW
BEGIN
    DECLARE v_traffic_mb DECIMAL(15,2);
    DECLARE v_total_bytes_used BIGINT;
    DECLARE v_total_bytes_remaining BIGINT;
    DECLARE v_gigawords INT;
    DECLARE v_remaining_bytes BIGINT;

    -- Trigger only when session stops (acctstoptime transitions from NULL to a date)
    IF (OLD.acctstoptime IS NULL AND NEW.acctstoptime IS NOT NULL) THEN
        
        -- 1. Calculate total bytes used in this session (handling 32-bit overflow via Gigawords)
        SET v_total_bytes_used = (COALESCE(NEW.acctinputgigawords, 0) + COALESCE(NEW.acctoutputgigawords, 0)) * 4294967296 
                               + (NEW.acctinputoctets + NEW.acctoutputoctets);

        -- 2. Update userbillinfo: Deduct usage
        -- traffic_balance is in MB (Total Bytes / 1,048,576)
        -- timebank_balance is in seconds
        UPDATE userbillinfo 
        SET 
            traffic_balance = GREATEST(0, traffic_balance - (v_total_bytes_used / 1048576)),
            timebank_balance = GREATEST(0, timebank_balance - NEW.acctsessiontime),
            last_balance_update = NOW()
        WHERE username = NEW.username;

        -- 3. Fetch new traffic balance to calculate Mikrotik attributes
        SELECT traffic_balance INTO v_traffic_mb 
        FROM userbillinfo 
        WHERE username = NEW.username;

        -- 4. Calculate Mikrotik Gigawords and Bytes for radreply
        SET v_total_bytes_remaining = v_traffic_mb * 1048576;
        SET v_gigawords = FLOOR(v_total_bytes_remaining / 4294967296);
        SET v_remaining_bytes = v_total_bytes_remaining % 4294967296;

        -- 5. Update radreply (Delete then Insert to avoid primary key issues)
        
        -- Update Mikrotik-Total-Limit
        DELETE FROM radreply WHERE username = NEW.username AND attribute = 'Mikrotik-Total-Limit';
        INSERT INTO radreply (username, attribute, op, value) 
        VALUES (NEW.username, 'Mikrotik-Total-Limit', '=', CAST(v_remaining_bytes AS CHAR));

        -- Update Mikrotik-Total-Limit-Gigawords
        DELETE FROM radreply WHERE username = NEW.username AND attribute = 'Mikrotik-Total-Limit-Gigawords';
        INSERT INTO radreply (username, attribute, op, value) 
        VALUES (NEW.username, 'Mikrotik-Total-Limit-Gigawords', '=', CAST(v_gigawords AS CHAR));

    END IF;
END //

DELIMITER ;
