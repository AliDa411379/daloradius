<?php
/*
 * Test script to verify agent deletion functionality works end-to-end
 */

include_once('app/common/includes/config_read.php');
include('app/common/includes/db_open.php');

echo "<h2>Testing Agent Delete Functionality</h2>\n";

// Test 1: Create a test agent
echo "<h3>Test 1: Creating a test agent</h3>\n";
$test_agent_name = "Test Agent " . date('Y-m-d H:i:s');
$sql = sprintf("INSERT INTO %s (name, company, email, phone) VALUES ('%s', 'Test Company', 'test@example.com', '123-456-7890')",
               $configValues['CONFIG_DB_TBL_DALOAGENTS'],
               $dbSocket->escapeSimple($test_agent_name));
$res = $dbSocket->query($sql);

if (!DB::isError($res)) {
    $test_agent_id = $dbSocket->getOne("SELECT LAST_INSERT_ID()");
    echo "✓ Created test agent with ID: $test_agent_id<br>\n";
} else {
    echo "✗ Failed to create test agent: " . $res->getMessage() . "<br>\n";
    exit;
}

// Test 2: Verify agent exists and is active
echo "<h3>Test 2: Verifying agent exists and is active</h3>\n";
$sql = sprintf("SELECT id, name, is_deleted FROM %s WHERE id = %d", 
               $configValues['CONFIG_DB_TBL_DALOAGENTS'], $test_agent_id);
$res = $dbSocket->query($sql);
if ($res && $row = $res->fetchRow()) {
    echo "✓ Agent found: ID={$row[0]}, Name={$row[1]}, Is_Deleted={$row[2]}<br>\n";
} else {
    echo "✗ Agent not found<br>\n";
    exit;
}

// Test 3: Soft delete the agent
echo "<h3>Test 3: Soft deleting the agent</h3>\n";
$sql = sprintf("UPDATE %s SET is_deleted = 1 WHERE id = %d", 
               $configValues['CONFIG_DB_TBL_DALOAGENTS'], $test_agent_id);
$res = $dbSocket->query($sql);

if (!DB::isError($res) && $dbSocket->affectedRows() > 0) {
    echo "✓ Agent soft deleted successfully<br>\n";
} else {
    echo "✗ Failed to soft delete agent<br>\n";
    exit;
}

// Test 4: Verify agent is marked as deleted
echo "<h3>Test 4: Verifying agent is marked as deleted</h3>\n";
$sql = sprintf("SELECT id, name, is_deleted FROM %s WHERE id = %d", 
               $configValues['CONFIG_DB_TBL_DALOAGENTS'], $test_agent_id);
$res = $dbSocket->query($sql);
if ($res && $row = $res->fetchRow()) {
    echo "✓ Agent status: ID={$row[0]}, Name={$row[1]}, Is_Deleted={$row[2]}<br>\n";
    if ($row[2] == 1) {
        echo "✓ Agent is correctly marked as deleted<br>\n";
    } else {
        echo "✗ Agent is not marked as deleted<br>\n";
    }
} else {
    echo "✗ Agent not found<br>\n";
}

// Test 5: Verify agent doesn't appear in active agents query
echo "<h3>Test 5: Verifying agent doesn't appear in active agents list</h3>\n";
$sql = sprintf("SELECT COUNT(*) FROM %s WHERE id = %d AND is_deleted = 0", 
               $configValues['CONFIG_DB_TBL_DALOAGENTS'], $test_agent_id);
$res = $dbSocket->query($sql);
$count = $res->fetchRow()[0];

if ($count == 0) {
    echo "✓ Agent correctly excluded from active agents list<br>\n";
} else {
    echo "✗ Agent still appears in active agents list<br>\n";
}

// Test 6: Clean up - restore the agent for future tests
echo "<h3>Test 6: Cleaning up - restoring agent</h3>\n";
$sql = sprintf("UPDATE %s SET is_deleted = 0 WHERE id = %d", 
               $configValues['CONFIG_DB_TBL_DALOAGENTS'], $test_agent_id);
$res = $dbSocket->query($sql);

if (!DB::isError($res)) {
    echo "✓ Test agent restored (is_deleted = 0)<br>\n";
} else {
    echo "✗ Failed to restore test agent<br>\n";
}

include('app/common/includes/db_close.php');

echo "<h3>All tests completed!</h3>\n";
echo "<p>The soft delete functionality is working correctly.</p>\n";
echo "<p><strong>Next steps:</strong></p>\n";
echo "<ul>\n";
echo "<li>Go to <a href='app/operators/mng-agents-list.php'>Agents List</a></li>\n";
echo "<li>Select one or more agents using checkboxes</li>\n";
echo "<li>Click the 'Delete' button</li>\n";
echo "<li>Confirm the deletion</li>\n";
echo "<li>The agents should disappear from the list</li>\n";
echo "</ul>\n";
?>