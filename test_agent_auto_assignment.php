<?php
/*
 * Test script to verify agent auto-assignment functionality
 */

// Simulate session for testing
session_start();
$_SESSION['operator_user'] = 'test_agent';
$_SESSION['operator_id'] = 1; // This should be an agent operator ID

include_once('app/common/includes/config_read.php');
include_once('app/operators/library/agent_functions.php');

echo "<h2>Agent Auto-Assignment Test</h2>";

// Database connection
try {
    include('app/common/includes/db_open.php');
    
    echo "<h3>1. Testing getCurrentOperatorAgentId function</h3>";
    $current_agent_id = getCurrentOperatorAgentId($dbSocket, $_SESSION['operator_id'], $configValues);
    
    if ($current_agent_id) {
        echo "<p style='color: green;'>✓ Current operator is an agent with ID: $current_agent_id</p>";
        
        // Get agent details
        $sql = sprintf("SELECT name, company FROM %s WHERE id = %d", 
                       $configValues['CONFIG_DB_TBL_DALOAGENTS'], $current_agent_id);
        $res = $dbSocket->query($sql);
        if ($res && ($row = $res->fetchRow())) {
            echo "<p>Agent Name: <strong>{$row[0]}</strong></p>";
            echo "<p>Agent Company: <strong>{$row[1]}</strong></p>";
        }
    } else {
        echo "<p style='color: orange;'>⚠ Current operator is not an agent or no matching agent found</p>";
    }
    
    echo "<h3>2. Testing isCurrentOperatorAgent function</h3>";
    $is_agent = isCurrentOperatorAgent($dbSocket, $_SESSION['operator_id'], $configValues);
    echo "<p>Is current operator an agent: " . ($is_agent ? "<span style='color: green;'>Yes</span>" : "<span style='color: red;'>No</span>") . "</p>";
    
    echo "<h3>3. Database Structure Check</h3>";
    
    // Check if operator_id column exists in agents table
    $sql = "SHOW COLUMNS FROM agents LIKE 'operator_id'";
    $res = $dbSocket->query($sql);
    if ($res && $res->numRows() > 0) {
        echo "<p style='color: green;'>✓ operator_id column exists in agents table</p>";
    } else {
        echo "<p style='color: orange;'>⚠ operator_id column does not exist in agents table (using fallback matching)</p>";
    }
    
    // Check if is_agent column exists in operators table
    $sql = "SHOW COLUMNS FROM operators LIKE 'is_agent'";
    $res = $dbSocket->query($sql);
    if ($res && $res->numRows() > 0) {
        echo "<p style='color: green;'>✓ is_agent column exists in operators table</p>";
    } else {
        echo "<p style='color: red;'>✗ is_agent column does not exist in operators table</p>";
    }
    
    echo "<h3>4. Sample Data</h3>";
    
    // Show some operators marked as agents
    $sql = sprintf("SELECT id, username, firstname, lastname, company, is_agent FROM %s WHERE is_agent = 1 LIMIT 5", 
                   $configValues['CONFIG_DB_TBL_DALOOPERATORS']);
    $res = $dbSocket->query($sql);
    
    if ($res && $res->numRows() > 0) {
        echo "<p><strong>Agent Operators:</strong></p>";
        echo "<ul>";
        while ($row = $res->fetchRow()) {
            echo "<li>ID: {$row[0]}, Username: {$row[1]}, Name: {$row[2]} {$row[3]}, Company: {$row[4]}</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: orange;'>No agent operators found in database</p>";
    }
    
    // Show some agents
    $sql = sprintf("SELECT id, name, company, operator_id FROM %s WHERE is_deleted = 0 LIMIT 5", 
                   $configValues['CONFIG_DB_TBL_DALOAGENTS']);
    $res = $dbSocket->query($sql);
    
    if ($res && $res->numRows() > 0) {
        echo "<p><strong>Agents:</strong></p>";
        echo "<ul>";
        while ($row = $res->fetchRow()) {
            $operator_id_display = isset($row[3]) ? $row[3] : 'N/A';
            echo "<li>ID: {$row[0]}, Name: {$row[1]}, Company: {$row[2]}, Operator ID: $operator_id_display</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: orange;'>No agents found in database</p>";
    }
    
    include('app/common/includes/db_close.php');
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<h3>5. Test Links</h3>";
echo "<ul>";
echo "<li><a href='app/operators/mng-new.php'>Test User Creation (should auto-assign agent)</a></li>";
echo "<li><a href='app/operators/bill-payments-new.php'>Test Payment Creation (should auto-assign agent)</a></li>";
echo "<li><a href='app/operators/mng-agent-new.php'>Test Agent Creation</a></li>";
echo "</ul>";

echo "<h3>6. Instructions</h3>";
echo "<div style='background: #f0f8ff; padding: 15px; border-left: 4px solid #0066cc;'>";
echo "<p><strong>To test the functionality:</strong></p>";
echo "<ol>";
echo "<li>Make sure you have an operator marked as an agent (is_agent = 1)</li>";
echo "<li>Log in with that operator account</li>";
echo "<li>Try creating a new user - the agent should be auto-assigned</li>";
echo "<li>Try creating a new payment - only users from your agent should be available</li>";
echo "</ol>";
echo "<p><strong>Note:</strong> If the operator_id column doesn't exist in the agents table, the system will use fallback matching by company name and email.</p>";
echo "</div>";
?>