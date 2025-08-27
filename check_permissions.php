<?php
/**
 * Check current permissions for mng_agent_new
 */

session_start();
include_once('app/common/includes/config_read.php');
include_once('app/common/includes/db_open.php');

echo "<h2>Permission Check for mng_agent_new</h2>\n";

if (!isset($_SESSION['operator_id'])) {
    echo "<p>❌ Not logged in - operator_id not in session</p>\n";
    exit;
}

$operator_id = $_SESSION['operator_id'];
$file = 'mng_agent_new';

echo "<p><strong>Operator ID:</strong> $operator_id</p>\n";
echo "<p><strong>File:</strong> $file</p>\n";

// Check if permission exists
$sql = sprintf("SELECT access FROM %s WHERE operator_id=%d AND file='%s'",
               $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'], $operator_id, $file);
echo "<p><strong>SQL:</strong> $sql</p>\n";

$result = $dbSocket->query($sql);
if ($result && $result->numRows() > 0) {
    $access = $result->fetchRow()[0];
    echo "<p><strong>Permission exists:</strong> " . ($access ? 'GRANTED' : 'DENIED') . "</p>\n";
    
    if (!$access) {
        echo "<h3>Fixing permission...</h3>\n";
        $sql = sprintf("UPDATE %s SET access=1 WHERE operator_id=%d AND file='%s'",
                      $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'], $operator_id, $file);
        $result = $dbSocket->query($sql);
        if (!DB::isError($result)) {
            echo "<p>✅ Permission updated to GRANTED</p>\n";
        } else {
            echo "<p>❌ Error updating permission: " . $result->getMessage() . "</p>\n";
        }
    }
} else {
    echo "<p><strong>Permission exists:</strong> NO</p>\n";
    echo "<h3>Adding permission...</h3>\n";
    
    $sql = sprintf("INSERT INTO %s (operator_id, file, access) VALUES (%d, '%s', 1)",
                  $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'], $operator_id, $file);
    $result = $dbSocket->query($sql);
    if (!DB::isError($result)) {
        echo "<p>✅ Permission added and GRANTED</p>\n";
    } else {
        echo "<p>❌ Error adding permission: " . $result->getMessage() . "</p>\n";
    }
}

// Show all permissions for this operator
echo "<h3>All permissions for operator $operator_id:</h3>\n";
$sql = sprintf("SELECT file, access FROM %s WHERE operator_id=%d ORDER BY file",
               $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'], $operator_id);
$result = $dbSocket->query($sql);

if ($result && $result->numRows() > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>\n";
    echo "<tr><th>File</th><th>Access</th></tr>\n";
    while ($row = $result->fetchRow()) {
        $access_text = $row[1] ? 'GRANTED' : 'DENIED';
        $color = $row[1] ? '#d4edda' : '#f8d7da';
        echo "<tr style='background-color: $color;'><td>" . $row[0] . "</td><td>$access_text</td></tr>\n";
    }
    echo "</table>\n";
} else {
    echo "<p>No permissions found for this operator!</p>\n";
}

include_once('app/common/includes/db_close.php');
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
</style>

<p><a href="app/operators/mng-agent-new.php">→ Try agent creation page</a></p>