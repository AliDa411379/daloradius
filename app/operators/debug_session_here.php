<?php
/**
 * Debug session from within operators directory
 */

include('library/sessions.php');
dalo_session_start();

echo "<h2>Session Debug from Operators Directory</h2>\n";

echo "<h3>Session Variables:</h3>\n";
if (empty($_SESSION)) {
    echo "<p><strong>❌ No session variables found!</strong></p>\n";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr style='background-color: #f2f2f2;'><th>Variable</th><th>Value</th></tr>\n";
    
    foreach ($_SESSION as $key => $value) {
        $display_value = is_array($value) ? print_r($value, true) : $value;
        echo "<tr><td>$key</td><td>" . htmlspecialchars($display_value) . "</td></tr>\n";
    }
    echo "</table>\n";
}

// Now let's test the exact permission check logic
if (isset($_SESSION['operator_id'])) {
    echo "<h3>Permission Check Test</h3>\n";
    
    include('../common/includes/config_read.php');
    include('../common/includes/db_open.php');
    
    $operator_id = $_SESSION['operator_id'];
    $test_file = "mng_agent_new";
    
    echo "<p><strong>Testing permission for:</strong> $test_file</p>\n";
    echo "<p><strong>Operator ID:</strong> $operator_id</p>\n";
    
    $sql = sprintf("SELECT access FROM %s WHERE operator_id=%d AND file='%s'",
                   $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'], $operator_id, $test_file);
    echo "<p><strong>SQL Query:</strong> $sql</p>\n";
    
    $access = intval($dbSocket->getOne($sql)) === 1;
    echo "<p><strong>Permission Result:</strong> " . ($access ? '✅ GRANTED' : '❌ DENIED') . "</p>\n";
    
    // Check if the ACL entry exists at all
    $sql = sprintf("SELECT COUNT(*) FROM %s WHERE file='%s'", 
                   $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL_FILES'], $test_file);
    $acl_exists = $dbSocket->getOne($sql) > 0;
    echo "<p><strong>ACL File Entry Exists:</strong> " . ($acl_exists ? '✅ YES' : '❌ NO') . "</p>\n";
    
    if (!$acl_exists) {
        echo "<h4>Adding missing ACL entry...</h4>\n";
        
        // Add ACL file entry
        $sql = sprintf("INSERT INTO %s (file, category, section) VALUES ('%s', 'Management', 'Agents')",
                      $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL_FILES'], $test_file);
        $result = $dbSocket->query($sql);
        
        if (!DB::isError($result)) {
            echo "<p>✅ ACL file entry added</p>\n";
            
            // Add permission for this operator
            $sql = sprintf("INSERT INTO %s (operator_id, file, access) VALUES (%d, '%s', 1)",
                          $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'], $operator_id, $test_file);
            $result = $dbSocket->query($sql);
            
            if (!DB::isError($result)) {
                echo "<p>✅ Permission granted to operator</p>\n";
                echo "<div style='background-color: #d4edda; padding: 15px; margin: 10px 0; border-radius: 5px;'>\n";
                echo "<h4 style='color: #155724;'>🎉 Fix Applied!</h4>\n";
                echo "<p style='color: #155724; margin-bottom: 0;'>Try accessing the agent creation page now.</p>\n";
                echo "</div>\n";
            } else {
                echo "<p>❌ Error granting permission: " . $result->getMessage() . "</p>\n";
            }
        } else {
            echo "<p>❌ Error adding ACL entry: " . $result->getMessage() . "</p>\n";
        }
    } else {
        // ACL entry exists, check if permission exists for this operator
        $sql = sprintf("SELECT COUNT(*) FROM %s WHERE operator_id=%d AND file='%s'",
                      $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'], $operator_id, $test_file);
        $perm_exists = $dbSocket->getOne($sql) > 0;
        
        if (!$perm_exists) {
            echo "<h4>Adding missing permission...</h4>\n";
            $sql = sprintf("INSERT INTO %s (operator_id, file, access) VALUES (%d, '%s', 1)",
                          $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'], $operator_id, $test_file);
            $result = $dbSocket->query($sql);
            
            if (!DB::isError($result)) {
                echo "<p>✅ Permission granted to operator</p>\n";
                echo "<div style='background-color: #d4edda; padding: 15px; margin: 10px 0; border-radius: 5px;'>\n";
                echo "<h4 style='color: #155724;'>🎉 Fix Applied!</h4>\n";
                echo "<p style='color: #155724; margin-bottom: 0;'>Try accessing the agent creation page now.</p>\n";
                echo "</div>\n";
            } else {
                echo "<p>❌ Error granting permission: " . $result->getMessage() . "</p>\n";
            }
        }
    }
    
    include('../common/includes/db_close.php');
} else {
    echo "<p><strong>❌ operator_id not found in session</strong></p>\n";
}

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
</style>

<p><a href="mng-agent-new.php">→ Try agent creation page</a></p>
<p><a href="index.php">→ Back to operators panel</a></p>