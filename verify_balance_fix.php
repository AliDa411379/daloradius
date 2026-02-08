<?php
/**
 * Test Script: Verify User Balance Status Logic
 * 
 * Verifies:
 * 1. Banned users return 'suspended'
 * 2. Expired prepaid users return 'expired'
 * 3. Normal users return 'active'
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
    }

    public function query($sql)
    {
        $res = $this->conn->query($sql);
        $this->insert_id = $this->conn->insert_id;
        return $res;
    }
}

$db = new SimpleDB($configValues);
$username = 'test_status_user_' . time();

// Cleanup first
$db->query("DELETE FROM userbillinfo WHERE username = '$username'");
$db->query("DELETE FROM userinfo WHERE username = '$username'");
$db->query("DELETE FROM radusergroup WHERE username = '$username'");
$db->query("DELETE FROM subscription_types WHERE id IN (1, 2)");
$db->query("INSERT IGNORE INTO subscription_types (id, type_name, display_name) VALUES (1, 'postpaid', 'Postpaid'), (2, 'prepaid', 'Prepaid')");

// Create base user (Prepaid, Active)
$db->query("INSERT INTO userbillinfo (username, billstatus, subscription_type_id) VALUES ('$username', 'active', 2)");
$db->query("INSERT INTO userinfo (username) VALUES ('$username')");

function checkStatus($username)
{
    global $configValues;
    $_GET['username'] = $username;
    $_SERVER['REQUEST_METHOD'] = 'GET';

    $cwd = getcwd();
    chdir('app/users/api');

    ob_start();
    include('user_balance.php');
    $out = ob_get_clean();

    chdir($cwd);

    $json = json_decode($out, true);
    return $json['status'] ?? 'ERROR';
}

echo "TEST 1: Normal Prepaid User (No Bundle = Expired)... ";
$status = checkStatus($username);
if ($status === 'expired') {
    echo "PASS (Got: $status)\n";
} else {
    echo "FAIL (Got: $status)\n";
}

echo "TEST 2: Active Bundle... ";
$db->query("INSERT INTO user_bundles (user_id, username, status, expiry_date) VALUES (
    (SELECT id FROM userbillinfo WHERE username = '$username'), 
    '$username', 'active', DATE_ADD(NOW(), INTERVAL 1 DAY))");
$status = checkStatus($username);
if ($status === 'active') {
    echo "PASS (Got: $status)\n";
} else {
    echo "FAIL (Got: $status)\n";
}

echo "TEST 3: Banned User (Disabled Group)... ";
$db->query("INSERT INTO radusergroup (username, groupname) VALUES ('$username', 'daloRADIUS-Disabled-Users')");
$status = checkStatus($username);
if ($status === 'suspended') {
    echo "PASS (Got: $status)\n";
} else {
    echo "FAIL (Got: $status)\n";
}

echo "TEST 4: Banned User (Block Group)... ";
$db->query("DELETE FROM radusergroup WHERE username = '$username'");
$db->query("INSERT INTO radusergroup (username, groupname) VALUES ('$username', 'block_user')");
$status = checkStatus($username);
if ($status === 'suspended') {
    echo "PASS (Got: $status)\n";
} else {
    echo "FAIL (Got: $status)\n";
}

// Cleanup
$db->query("DELETE FROM userbillinfo WHERE username = '$username'");
$db->query("DELETE FROM userinfo WHERE username = '$username'");
$db->query("DELETE FROM radusergroup WHERE username = '$username'");
$db->query("DELETE FROM user_bundles WHERE username = '$username'");

echo "Done.\n";
