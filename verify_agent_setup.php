<?php
/*
 * Simple verification script for agent auto-assignment setup
 */

echo "<h2>Agent Auto-Assignment Setup Verification</h2>";

try {
    include_once('app/common/includes/config_read.php');
    include('app/common/includes/db_open.php');
    
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    
    // Check database tables exist
    echo "<h3>1. Database Tables Check</h3>";
    
    $tables_to_check = array(
        'operators' => $configValues['CONFIG_DB_TBL_DALOOPERATORS'],
        'agents' => $configValues['CONFIG_DB_TBL_DALOAGENTS'],
        'userinfo' => $configValues['CONFIG_DB_TBL_DALOUSERINFO'],
        'user_agent' => 'user_agent'
    );
    
    foreach ($tables_to_check as $name => $table) {
        $sql = "SHOW TABLES LIKE '$table'";
        $res = $dbSocket->query($sql);
        if ($res && $res->numRows() > 0) {
            echo "<p style='color: green;'>✓ Table '$name' ($table) exists</p>";
        } else {
            echo "<p style='color: red;'>✗ Table '$name' ($table) missing</p>";
        }
    }
    
    // Check required columns
    echo "<h3>2. Required Columns Check</h3>";
    
    // Check is_agent column in operators table
    $sql = "SHOW COLUMNS FROM " . $configValues['CONFIG_DB_TBL_DALOOPERATORS'] . " LIKE 'is_agent'";
    $res = $dbSocket->query($sql);
    if ($res && $res->numRows() > 0) {
        echo "<p style='color: green;'>✓ 'is_agent' column exists in operators table</p>";
    } else {
        echo "<p style='color: red;'>✗ 'is_agent' column missing in operators table</p>";
        echo "<p style='color: orange;'>→ Run: ALTER TABLE operators ADD COLUMN is_agent TINYINT(1) NOT NULL DEFAULT 0;</p>";
    }
    
    // Check operator_id column in agents table (optional)
    $sql = "SHOW COLUMNS FROM " . $configValues['CONFIG_DB_TBL_DALOAGENTS'] . " LIKE 'operator_id'";
    $res = $dbSocket->query($sql);
    if ($res && $res->numRows() > 0) {
        echo "<p style='color: green;'>✓ 'operator_id' column exists in agents table (recommended)</p>";
    } else {
        echo "<p style='color: orange;'>⚠ 'operator_id' column missing in agents table (will use fallback matching)</p>";
        echo "<p style='color: blue;'>→ Optional: ALTER TABLE agents ADD COLUMN operator_id INT(11) NULL;</p>";
    }
    
    // Check sample data
    echo "<h3>3. Sample Data Check</h3>";
    
    // Count operators marked as agents
    $sql = sprintf("SELECT COUNT(*) FROM %s WHERE is_agent = 1", $configValues['CONFIG_DB_TBL_DALOOPERATORS']);
    $res = $dbSocket->query($sql);
    if ($res && ($row = $res->fetchRow())) {
        $agent_operators_count = $row[0];
        echo "<p>Agent operators in database: <strong>$agent_operators_count</strong></p>";
        
        if ($agent_operators_count > 0) {
            // Show some examples
            $sql = sprintf("SELECT id, username, firstname, lastname, company FROM %s WHERE is_agent = 1 LIMIT 3", 
                           $configValues['CONFIG_DB_TBL_DALOOPERATORS']);
            $res = $dbSocket->query($sql);
            echo "<p><strong>Sample agent operators:</strong></p>";
            echo "<ul>";
            while ($row = $res->fetchRow()) {
                echo "<li>ID: {$row[0]}, Username: {$row[1]}, Name: {$row[2]} {$row[3]}, Company: {$row[4]}</li>";
            }
            echo "</ul>";
        }
    }
    
    // Count agents
    $sql = sprintf("SELECT COUNT(*) FROM %s WHERE is_deleted = 0", $configValues['CONFIG_DB_TBL_DALOAGENTS']);
    $res = $dbSocket->query($sql);
    if ($res && ($row = $res->fetchRow())) {
        $agents_count = $row[0];
        echo "<p>Active agents in database: <strong>$agents_count</strong></p>";
    }
    
    echo "<h3>4. Files Check</h3>";
    
    $files_to_check = array(
        'Agent Functions' => 'app/operators/library/agent_functions.php',
        'User Creation' => 'app/operators/mng-new.php',
        'Payment Creation' => 'app/operators/bill-payments-new.php',
        'Agent UI' => 'app/operators/include/management/userbillinfo.php'
    );
    
    foreach ($files_to_check as $name => $file) {
        if (file_exists($file)) {
            echo "<p style='color: green;'>✓ $name file exists</p>";
        } else {
            echo "<p style='color: red;'>✗ $name file missing: $file</p>";
        }
    }
    
    echo "</div>";
    
    echo "<h3>5. Test Instructions</h3>";
    echo "<div style='background: #e7f3ff; padding: 15px; border-left: 4px solid #0066cc; margin: 10px 0;'>";
    echo "<p><strong>To test the agent auto-assignment:</strong></p>";
    echo "<ol>";
    echo "<li><strong>Create or mark an operator as agent:</strong><br>";
    echo "<code>UPDATE operators SET is_agent = 1 WHERE username = 'your_agent_username';</code></li>";
    echo "<li><strong>Create a matching agent record:</strong><br>";
    echo "Go to <a href='app/operators/mng-agent-new.php'>Agent Creation</a> and create an agent with the same company/email as the operator</li>";
    echo "<li><strong>Login with the agent operator account</strong></li>";
    echo "<li><strong>Test user creation:</strong><br>";
    echo "Go to <a href='app/operators/mng-new.php'>User Creation</a> - the agent should be auto-assigned and read-only</li>";
    echo "<li><strong>Test payment creation:</strong><br>";
    echo "Go to <a href='app/operators/bill-payments-new.php'>Payment Creation</a> - agent should be auto-assigned</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<h3>6. Quick Setup Commands</h3>";
    echo "<div style='background: #f0f8f0; padding: 15px; border-left: 4px solid #28a745; margin: 10px 0;'>";
    echo "<p><strong>Run these SQL commands to set up a test scenario:</strong></p>";
    echo "<pre style='background: #f8f8f8; padding: 10px; border-radius: 3px;'>";
    echo "-- 1. Add is_agent column if it doesn't exist\n";
    echo "ALTER TABLE operators ADD COLUMN IF NOT EXISTS is_agent TINYINT(1) NOT NULL DEFAULT 0;\n\n";
    echo "-- 2. Add operator_id column to agents (optional)\n";
    echo "ALTER TABLE agents ADD COLUMN IF NOT EXISTS operator_id INT(11) NULL;\n\n";
    echo "-- 3. Mark an existing operator as agent\n";
    echo "UPDATE operators SET is_agent = 1 WHERE username = 'admin'; -- Replace 'admin' with your operator username\n\n";
    echo "-- 4. Create a test agent (adjust values as needed)\n";
    echo "INSERT INTO agents (name, company, email, phone, address, city, country, is_deleted) \n";
    echo "VALUES ('Test Agent', 'Test Company', 'test@example.com', '123-456-7890', '123 Test St', 'Test City', 'Test Country', 0);\n\n";
    echo "-- 5. Link agent to operator (if operator_id column exists)\n";
    echo "UPDATE agents SET operator_id = (SELECT id FROM operators WHERE username = 'admin' LIMIT 1) WHERE name = 'Test Agent';\n";
    echo "</pre>";
    echo "</div>";
    
    include('app/common/includes/db_close.php');
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='test_agent_auto_assignment.php'>→ Run detailed test script</a></p>";
?>