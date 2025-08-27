<?php
/*
 * Test script to verify agent soft deletion functionality
 */

include_once('app/common/includes/config_read.php');
include('app/common/includes/db_open.php');

echo "<h2>Testing Agent Soft Deletion Functionality</h2>\n";

// Test 1: Check if is_deleted columns exist
echo "<h3>Test 1: Checking if is_deleted columns exist</h3>\n";

$sql = "SHOW COLUMNS FROM agents LIKE 'is_deleted'";
$res = $dbSocket->query($sql);
if ($res && $res->numRows() > 0) {
    echo "✓ is_deleted column exists in agents table<br>\n";
} else {
    echo "✗ is_deleted column missing in agents table<br>\n";
}

$sql = "SHOW COLUMNS FROM operators LIKE 'is_deleted'";
$res = $dbSocket->query($sql);
if ($res && $res->numRows() > 0) {
    echo "✓ is_deleted column exists in operators table<br>\n";
} else {
    echo "✗ is_deleted column missing in operators table<br>\n";
}

// Test 2: Count active agents
echo "<h3>Test 2: Counting active agents</h3>\n";
$sql = sprintf("SELECT COUNT(*) FROM %s WHERE is_deleted = 0", $configValues['CONFIG_DB_TBL_DALOAGENTS']);
$res = $dbSocket->query($sql);
$active_agents = $res->fetchRow()[0];
echo "Active agents: $active_agents<br>\n";

$sql = sprintf("SELECT COUNT(*) FROM %s WHERE is_deleted = 1", $configValues['CONFIG_DB_TBL_DALOAGENTS']);
$res = $dbSocket->query($sql);
$deleted_agents = $res->fetchRow()[0];
echo "Deleted agents: $deleted_agents<br>\n";

// Test 3: Count active operators
echo "<h3>Test 3: Counting active operators</h3>\n";
$sql = sprintf("SELECT COUNT(*) FROM %s WHERE is_deleted = 0", $configValues['CONFIG_DB_TBL_DALOOPERATORS']);
$res = $dbSocket->query($sql);
$active_operators = $res->fetchRow()[0];
echo "Active operators: $active_operators<br>\n";

$sql = sprintf("SELECT COUNT(*) FROM %s WHERE is_deleted = 1", $configValues['CONFIG_DB_TBL_DALOOPERATORS']);
$res = $dbSocket->query($sql);
$deleted_operators = $res->fetchRow()[0];
echo "Deleted operators: $deleted_operators<br>\n";

// Test 4: Show sample agents
echo "<h3>Test 4: Sample active agents</h3>\n";
$sql = sprintf("SELECT id, name, company, is_deleted FROM %s WHERE is_deleted = 0 LIMIT 5", $configValues['CONFIG_DB_TBL_DALOAGENTS']);
$res = $dbSocket->query($sql);
if ($res && $res->numRows() > 0) {
    echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Company</th><th>Is Deleted</th></tr>\n";
    while ($row = $res->fetchRow()) {
        echo "<tr><td>{$row[0]}</td><td>{$row[1]}</td><td>{$row[2]}</td><td>{$row[3]}</td></tr>\n";
    }
    echo "</table>\n";
} else {
    echo "No active agents found<br>\n";
}

include('app/common/includes/db_close.php');

echo "<h3>Test completed!</h3>\n";
echo "<p><a href='app/operators/mng-agents-list.php'>Go to Agents List</a></p>\n";
?>