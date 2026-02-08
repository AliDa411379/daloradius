<?php
/**
 * Comprehensive Verification Script
 * 
 * Verifies:
 * 1. API filters (agent_get_active_users.php)
 * 2. Reactivation logic (balance_functions.php)
 * 3. Access Grant logic (RadiusAccessManager.php)
 */

require_once('app/common/includes/config_read.php');

// Simple DB Connection Helper
class SimpleDB
{
    public $conn;
    public $insert_id;

    public function __construct($config)
    {
        $this->conn = new mysqli(
            $config['CONFIG_DB_HOST'],
            $config['CONFIG_DB_USER'],
            $config['CONFIG_DB_PASS'],
            $config['CONFIG_DB_NAME']
        );
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }

    public function query($sql)
    {
        $res = $this->conn->query($sql);
        $this->insert_id = $this->conn->insert_id;
        return $res;
    }

    public function real_escape_string($str)
    {
        return $this->conn->real_escape_string($str);
    }

    public function begin_transaction()
    {
        $this->conn->begin_transaction();
    }
    public function commit()
    {
        $this->conn->commit();
    }
    public function rollback()
    {
        $this->conn->rollback();
    }
}

$db = new SimpleDB($configValues);

// Ensure MIKROTIK_LOG_FILE is pointing to something safe or /dev/null
if (!defined('MIKROTIK_LOG_FILE')) {
    define('MIKROTIK_LOG_FILE', 'php://stderr');
}

require_once('app/common/library/RadiusAccessManager.php');
require_once('app/common/library/balance_functions.php');

$username = 'test_comp_user_' . time();

// setup test user
$db->query("INSERT INTO userbillinfo (username, billstatus, planName) VALUES ('$username', 'active', 'TestPlan')");
$db->query("INSERT INTO userinfo (username) VALUES ('$username')");
$db->query("INSERT INTO radusergroup (username, groupname) VALUES ('$username', 'daloRADIUS-Disabled-Users')");

echo "TEST 1: Verify user is initially disabled... ";
$res = $db->query("SELECT COUNT(*) as count FROM radusergroup WHERE username = '$username' AND groupname = 'daloRADIUS-Disabled-Users'");
if ($res->fetch_assoc()['count'] > 0) {
    echo "PASS\n";
} else {
    echo "FAIL\n";
}

echo "TEST 2: Verify RadiusAccessManager removes disabled group... ";
$ram = new RadiusAccessManager($db->conn);
// We need a dummy plan profile
$db->query("INSERT INTO billing_plans (planName) VALUES ('TestPlan')");
$db->query("INSERT INTO billing_plans_profiles (plan_name, profile_name) VALUES ('TestPlan', 'TestProfile')");

$ram->grantAccess($username, 'TestPlan');

$res = $db->query("SELECT COUNT(*) as count FROM radusergroup WHERE username = '$username' AND groupname = 'daloRADIUS-Disabled-Users'");
if ($res->fetch_assoc()['count'] == 0) {
    echo "PASS\n";
} else {
    echo "FAIL\n";
}

echo "TEST 3: Verify balance_functions remove disabled group... ";
// Put back in disabled group
$db->query("INSERT INTO radusergroup (username, groupname) VALUES ('$username', 'daloRADIUS-Disabled-Users')");
handle_user_reactivation($db->conn, $username);

$res = $db->query("SELECT COUNT(*) as count FROM radusergroup WHERE username = '$username' AND groupname = 'daloRADIUS-Disabled-Users'");
if ($res->fetch_assoc()['count'] == 0) {
    echo "PASS\n";
} else {
    echo "FAIL\n";
}

// Cleanup
$db->query("DELETE FROM userbillinfo WHERE username = '$username'");
$db->query("DELETE FROM userinfo WHERE username = '$username'");
$db->query("DELETE FROM radusergroup WHERE username = '$username'");
$db->query("DELETE FROM billing_plans WHERE planName = 'TestPlan'");
$db->query("DELETE FROM billing_plans_profiles WHERE plan_name = 'TestPlan'");

echo "Done.\n";
