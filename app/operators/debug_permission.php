<?php
include("library/checklogin.php");
include_once('../common/includes/config_read.php');
include('../common/includes/db_open.php');

$operator = $_SESSION['operator_user'];
$operator_id = $_SESSION['operator_id'];

echo "<h2>Permission Debug for: $operator (ID: $operator_id)</h2>";

// Check what the file name becomes
$file = str_replace("-", "_", basename('mng-agent-new.php', ".php"));
echo "<p><strong>File being checked:</strong> $file</p>";

// Check if permission exists
$sql = sprintf("SELECT access FROM %s WHERE operator_id=%d AND file='%s'",
               $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'], $operator_id, $file);
echo "<p><strong>SQL:</strong> $sql</p>";

$result = $dbSocket->query($sql);
if ($result && $result->numRows() > 0) {
    $access = $result->fetchRow()[0];
    echo "<p><strong>Permission found:</strong> " . ($access ? 'GRANTED' : 'DENIED') . "</p>";
} else {
    echo "<p><strong>Permission:</strong> NOT FOUND (this is the problem!)</p>";
}

// Show all permissions for this operator
echo "<h3>All permissions for operator $operator:</h3>";
$sql = sprintf("SELECT file, access FROM %s WHERE operator_id=%d ORDER BY file",
               $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'], $operator_id);
$result = $dbSocket->query($sql);

if ($result && $result->numRows() > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>File</th><th>Access</th></tr>";
    while ($row = $result->fetchRow()) {
        $status = $row[1] ? 'GRANTED' : 'DENIED';
        $color = $row[1] ? 'green' : 'red';
        echo "<tr><td>{$row[0]}</td><td style='color: $color'>$status</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p>No permissions found for this operator!</p>";
}

include('../common/includes/db_close.php');
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { margin-top: 10px; }
th, td { padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
</style>