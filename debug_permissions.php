<?php
/**
 * Debug Agent Permissions
 * This script helps debug the exact permission issue
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once('app/common/includes/config_read.php');
include_once('app/common/includes/db_open.php');

echo "<h2>Debug Agent Permissions</h2>\n";

// Simulate what the permission check does
$test_file = "mng-agent-new.php";
$converted_file = str_replace("-", "_", basename($test_file, ".php"));

echo "<h3>File Name Conversion Test</h3>\n";
echo "<p><strong>Original file:</strong> $test_file</p>\n";
echo "<p><strong>Converted file:</strong> $converted_file</p>\n";

// Check what's in operators_acl_files table
echo "<h3>Available ACL Files (agent related)</h3>\n";
$sql = sprintf("SELECT * FROM %s WHERE file LIKE '%%agent%%' OR file LIKE '%%agents%%'", 
               $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL_FILES']);
$result = $dbSocket->query($sql);

if ($result && $result->numRows() > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr style='background-color: #f2f2f2;'><th>ID</th><th>File</th><th>Category</th><th>Section</th></tr>\n";
    while ($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
        echo "<tr><td>" . $row['id'] . "</td><td>" . $row['file'] . "</td><td>" . $row['category'] . "</td><td>" . $row['section'] . "</td></tr>\n";
    }
    echo "</table>\n";
} else {
    echo "<p><strong>❌ No agent-related ACL files found!</strong></p>\n";
}

// Check all operators
echo "<h3>All Operators</h3>\n";
$sql = sprintf("SELECT id, username FROM %s", $configValues['CONFIG_DB_TBL_DALOOPERATORS']);
$result = $dbSocket->query($sql);

if ($result && $result->numRows() > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr style='background-color: #f2f2f2;'><th>ID</th><th>Username</th><th>Permissions for '$converted_file'</th></tr>\n";
    
    while ($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
        // Check permissions for this operator
        $perm_sql = sprintf("SELECT access FROM %s WHERE operator_id=%d AND file='%s'",
                           $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'], 
                           $row['id'], 
                           $dbSocket->escapeSimple($converted_file));
        $perm_result = $dbSocket->query($perm_sql);
        
        $permission_status = "❌ No permission found";
        if ($perm_result && $perm_result->numRows() > 0) {
            $perm_row = $perm_result->fetchRow();
            $permission_status = $perm_row[0] ? "✅ Access granted" : "❌ Access denied";
        }
        
        echo "<tr><td>" . $row['id'] . "</td><td>" . $row['username'] . "</td><td>" . $permission_status . "</td></tr>\n";
    }
    echo "</table>\n";
}

// Check what files exist in ACL that are similar
echo "<h3>Similar ACL Files</h3>\n";
$sql = sprintf("SELECT file FROM %s WHERE file LIKE '%%mng%%' ORDER BY file", 
               $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL_FILES']);
$result = $dbSocket->query($sql);

if ($result && $result->numRows() > 0) {
    echo "<p><strong>Files starting with 'mng':</strong></p>\n";
    echo "<ul>\n";
    while ($row = $result->fetchRow()) {
        echo "<li>" . $row[0] . "</li>\n";
    }
    echo "</ul>\n";
}

// Close database connection
include_once('app/common/includes/db_close.php');
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
</style>