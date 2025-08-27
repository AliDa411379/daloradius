<?php
// Debug the permission checking process
session_start();

echo "<h2>Permission Check Debug</h2>";

// Check session
echo "<h3>Session Status:</h3>";
if (isset($_SESSION['daloradius_logged_in']) && $_SESSION['daloradius_logged_in']) {
    echo "<p style='color: green;'>Logged in as: " . htmlspecialchars($_SESSION['operator_user']) . " (ID: " . $_SESSION['operator_id'] . ")</p>";
    
    $operator_id = $_SESSION['operator_id'];
    
    // Simulate the permission check process
    $file = str_replace("-", "_", basename("mng-agents-edit.php", ".php"));
    echo "<h3>Permission Check Process:</h3>";
    echo "<p>Original filename: mng-agents-edit.php</p>";
    echo "<p>Converted filename: $file</p>";
    
    // Check database
    include_once('app/common/includes/config_read.php');
    include('app/common/includes/db_open.php');
    
    $sql = sprintf("SELECT access FROM %s WHERE operator_id=%d AND file='%s'",
                   $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'], $operator_id, $file);
    
    echo "<p>SQL Query: $sql</p>";
    
    $access_result = $dbSocket->getOne($sql);
    echo "<p>Query Result: " . var_export($access_result, true) . "</p>";
    
    $access = intval($access_result) === 1;
    echo "<p>Access Granted: " . ($access ? 'YES' : 'NO') . "</p>";
    
    if (!$access) {
        echo "<p style='color: red;'>This is why you're getting redirected to home-error.php</p>";
        
        // Show all permissions for this operator
        $sql2 = sprintf("SELECT file, access FROM %s WHERE operator_id=%d ORDER BY file",
                       $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'], $operator_id);
        $res = $dbSocket->query($sql2);
        
        echo "<h4>All permissions for operator $operator_id:</h4>";
        echo "<table border='1'>";
        echo "<tr><th>File</th><th>Access</th></tr>";
        while ($row = $res->fetchRow()) {
            $color = $row[1] ? 'green' : 'red';
            echo "<tr><td>" . htmlspecialchars($row[0]) . "</td><td style='color: $color;'>" . ($row[1] ? 'Granted' : 'Denied') . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: green;'>Permission check should pass - the issue might be elsewhere</p>";
    }
    
    include('app/common/includes/db_close.php');
    
} else {
    echo "<p style='color: red;'>Not logged in - this is likely the issue</p>";
    echo "<p><a href='quick_login_test.php'>Use Quick Login Test</a></p>";
}

echo "<h3>Test Links:</h3>";
echo "<p><a href='app/operators/mng-agents-edit.php?agent_id=1'>Direct Agent Edit Test</a></p>";
echo "<p><a href='app/operators/login.php'>Login Page</a></p>";
?>