<?php
// Test user edit page functionality
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing user edit page...\n";

// Simulate session
session_start();
$_SESSION['operator_user'] = 'admin';

// Simulate GET request
$_GET['username'] = 'testuser';

try {
    // Test the includes that mng-edit.php uses
    include_once('app/common/includes/config_read.php');
    echo "✓ Config loaded\n";
    
    include('app/common/includes/db_open.php');
    echo "✓ Database connected\n";
    
    include_once('app/operators/include/management/functions.php');
    echo "✓ Functions loaded\n";
    
    // Test if we can load user data
    $username = 'testuser';
    $sql = sprintf("SELECT id FROM userinfo WHERE username='%s'", $dbSocket->escapeSimple($username));
    $res = $dbSocket->query($sql);
    
    if ($res && $row = $res->fetchRow()) {
        $user_id = $row[0];
        echo "✓ User found: ID $user_id\n";
        
        // Test agent loading
        $selected_agents = array();
        $sql = sprintf("SELECT agent_id FROM user_agent WHERE user_id=%d", intval($user_id));
        $res = $dbSocket->query($sql);
        
        while ($row = $res->fetchRow()) {
            $selected_agents[] = $row[0];
        }
        
        echo "✓ Selected agents loaded: " . implode(', ', $selected_agents) . "\n";
        
        // Test if userbillinfo.php can be included
        ob_start();
        include('app/operators/include/management/userbillinfo.php');
        $output = ob_get_clean();
        
        if (strlen($output) > 100) {
            echo "✓ userbillinfo.php included successfully (" . strlen($output) . " bytes)\n";
        } else {
            echo "✗ userbillinfo.php output too short: " . strlen($output) . " bytes\n";
            echo "Output: " . substr($output, 0, 200) . "\n";
        }
        
    } else {
        echo "✗ User not found\n";
    }
    
    include('app/common/includes/db_close.php');
    echo "✓ Database closed\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "✗ Fatal Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "Test completed.\n";
?>