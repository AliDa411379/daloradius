-- =====================================================
-- Radacct Billing Table Setup
-- =====================================================
-- This script creates a billing-optimized copy of radacct
-- with automatic replication and balance tracking features

-- Create the billing-optimized radacct table
CREATE TABLE IF NOT EXISTS radacct_billing (
    radacctid bigint(21) NOT NULL AUTO_INCREMENT,
    acctsessionid varchar(64) NOT NULL DEFAULT '',
    acctuniqueid varchar(32) NOT NULL DEFAULT '',
    username varchar(64) NOT NULL DEFAULT '',
    groupname varchar(64) NOT NULL DEFAULT '',
    realm varchar(64) DEFAULT '',
    nasipaddress varchar(15) NOT NULL DEFAULT '',
    nasportid varchar(15) DEFAULT NULL,
    nasporttype varchar(32) DEFAULT NULL,
    acctstarttime datetime DEFAULT NULL,
    acctupdatetime datetime DEFAULT NULL,
    acctstoptime datetime DEFAULT NULL,
    acctinterval int(12) DEFAULT NULL,
    acctsessiontime int(12) unsigned DEFAULT NULL,
    acctauthentic varchar(32) DEFAULT NULL,
    connectinfo_start varchar(50) DEFAULT NULL,
    connectinfo_stop varchar(50) DEFAULT NULL,
    acctinputoctets bigint(20) DEFAULT NULL,
    acctoutputoctets bigint(20) DEFAULT NULL,
    calledstationid varchar(50) NOT NULL DEFAULT '',
    callingstationid varchar(50) NOT NULL DEFAULT '',
    acctterminatecause varchar(32) NOT NULL DEFAULT '',
    servicetype varchar(32) DEFAULT NULL,
    framedprotocol varchar(32) DEFAULT NULL,
    framedipaddress varchar(15) NOT NULL DEFAULT '',
    
    -- Additional billing-specific fields
    processed_for_billing TINYINT(1) DEFAULT 0,
    billing_processed_date DATETIME DEFAULT NULL,
    traffic_mb DECIMAL(15,2) DEFAULT NULL,
    session_minutes DECIMAL(10,2) DEFAULT NULL,
    
    PRIMARY KEY (radacctid),
    UNIQUE KEY acctuniqueid (acctuniqueid),
    KEY username (username),
    KEY framedipaddress (framedipaddress),
    KEY acctsessionid (acctsessionid),
    KEY acctstarttime (acctstarttime),
    KEY acctstoptime (acctstoptime),
    KEY nasipaddress (nasipaddress),
    KEY processed_for_billing (processed_for_billing),
    KEY billing_processed_date (billing_processed_date)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Create trigger to automatically replicate INSERT operations
DELIMITER $$
CREATE TRIGGER radacct_billing_insert 
AFTER INSERT ON radacct
FOR EACH ROW
BEGIN
    -- Calculate traffic in MB and session time in minutes
    SET @traffic_mb = COALESCE((NEW.acctinputoctets + NEW.acctoutputoctets) / 1048576, 0);
    SET @session_min = COALESCE(NEW.acctsessiontime / 60, 0);
    
    INSERT INTO radacct_billing (
        radacctid, acctsessionid, acctuniqueid, username, groupname, realm,
        nasipaddress, nasportid, nasporttype, acctstarttime, acctupdatetime,
        acctstoptime, acctinterval, acctsessiontime, acctauthentic,
        connectinfo_start, connectinfo_stop, acctinputoctets, acctoutputoctets,
        calledstationid, callingstationid, acctterminatecause, servicetype,
        framedprotocol, framedipaddress, traffic_mb, session_minutes
    ) VALUES (
        NEW.radacctid, NEW.acctsessionid, NEW.acctuniqueid, NEW.username, 
        NEW.groupname, NEW.realm, NEW.nasipaddress, NEW.nasportid, NEW.nasporttype,
        NEW.acctstarttime, NEW.acctupdatetime, NEW.acctstoptime, NEW.acctinterval,
        NEW.acctsessiontime, NEW.acctauthentic, NEW.connectinfo_start, 
        NEW.connectinfo_stop, NEW.acctinputoctets, NEW.acctoutputoctets,
        NEW.calledstationid, NEW.callingstationid, NEW.acctterminatecause,
        NEW.servicetype, NEW.framedprotocol, NEW.framedipaddress,
        @traffic_mb, @session_min
    );
END$$

-- Create trigger to automatically replicate UPDATE operations
CREATE TRIGGER radacct_billing_update 
AFTER UPDATE ON radacct
FOR EACH ROW
BEGIN
    -- Calculate traffic in MB and session time in minutes
    SET @traffic_mb = COALESCE((NEW.acctinputoctets + NEW.acctoutputoctets) / 1048576, 0);
    SET @session_min = COALESCE(NEW.acctsessiontime / 60, 0);
    
    UPDATE radacct_billing SET
        acctsessionid = NEW.acctsessionid,
        acctuniqueid = NEW.acctuniqueid,
        username = NEW.username,
        groupname = NEW.groupname,
        realm = NEW.realm,
        nasipaddress = NEW.nasipaddress,
        nasportid = NEW.nasportid,
        nasporttype = NEW.nasporttype,
        acctstarttime = NEW.acctstarttime,
        acctupdatetime = NEW.acctupdatetime,
        acctstoptime = NEW.acctstoptime,
        acctinterval = NEW.acctinterval,
        acctsessiontime = NEW.acctsessiontime,
        acctauthentic = NEW.acctauthentic,
        connectinfo_start = NEW.connectinfo_start,
        connectinfo_stop = NEW.connectinfo_stop,
        acctinputoctets = NEW.acctinputoctets,
        acctoutputoctets = NEW.acctoutputoctets,
        calledstationid = NEW.calledstationid,
        callingstationid = NEW.callingstationid,
        acctterminatecause = NEW.acctterminatecause,
        servicetype = NEW.servicetype,
        framedprotocol = NEW.framedprotocol,
        framedipaddress = NEW.framedipaddress,
        traffic_mb = @traffic_mb,
        session_minutes = @session_min
    WHERE radacctid = NEW.radacctid;
END$$

-- Create trigger to replicate DELETE operations (optional)
CREATE TRIGGER radacct_billing_delete 
AFTER DELETE ON radacct
FOR EACH ROW
BEGIN
    DELETE FROM radacct_billing WHERE radacctid = OLD.radacctid;
END$$

DELIMITER ;

-- Copy existing data from radacct to radacct_billing
INSERT INTO radacct_billing (
    radacctid, acctsessionid, acctuniqueid, username, groupname, realm,
    nasipaddress, nasportid, nasporttype, acctstarttime, acctupdatetime,
    acctstoptime, acctinterval, acctsessiontime, acctauthentic,
    connectinfo_start, connectinfo_stop, acctinputoctets, acctoutputoctets,
    calledstationid, callingstationid, acctterminatecause, servicetype,
    framedprotocol, framedipaddress, traffic_mb, session_minutes
)
SELECT 
    radacctid, acctsessionid, acctuniqueid, username, groupname, realm,
    nasipaddress, nasportid, nasporttype, acctstarttime, acctupdatetime,
    acctstoptime, acctinterval, acctsessiontime, acctauthentic,
    connectinfo_start, connectinfo_stop, acctinputoctets, acctoutputoctets,
    calledstationid, callingstationid, acctterminatecause, servicetype,
    framedprotocol, framedipaddress,
    -- Pre-calculate traffic and time
    COALESCE((acctinputoctets + acctoutputoctets) / 1048576, 0) as traffic_mb,
    COALESCE(acctsessiontime / 60, 0) as session_minutes
FROM radacct
WHERE NOT EXISTS (
    SELECT 1 FROM radacct_billing rb WHERE rb.radacctid = radacct.radacctid
);

-- Create indexes for better performance
CREATE INDEX idx_radacct_billing_user_date ON radacct_billing (username, acctstarttime);
CREATE INDEX idx_radacct_billing_user_processed ON radacct_billing (username, processed_for_billing);
CREATE INDEX idx_radacct_billing_stop_processed ON radacct_billing (acctstoptime, processed_for_billing);

-- Create a view for easy billing queries
CREATE OR REPLACE VIEW v_billing_sessions AS
SELECT 
    username,
    acctstarttime,
    acctstoptime,
    acctsessiontime,
    session_minutes,
    acctinputoctets,
    acctoutputoctets,
    traffic_mb,
    processed_for_billing,
    billing_processed_date,
    -- Additional calculated fields
    DATE(acctstarttime) as session_date,
    YEAR(acctstarttime) as session_year,
    MONTH(acctstarttime) as session_month,
    DAY(acctstarttime) as session_day
FROM radacct_billing
WHERE acctstoptime IS NOT NULL;

SHOW WARNINGS;
SELECT 'Radacct billing table setup completed successfully!' as Status;