<?php
/**
 * Check Agent Naming Convention in ACL System
 */

include_once('app/common/includes/config_read.php');
include_once('app/common/includes/db_open.php');

echo "<h2>Agent Naming Convention Analysis</h2>\n";

// Check what agent-related entries exist in ACL
echo "<h3>Current ACL Entries (agent/agents related)</h3>\n";
$sql = sprintf("SELECT * FROM %s WHERE file LIKE '%%agent%%' ORDER BY file", 
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
    echo "<p><strong>❌ No agent-related ACL entries found!</strong></p>\n";
}

// Show the file conversion for each actual file
echo "<h3>File Name Conversion for Actual Files</h3>\n";

$actual_files = [
    'mng-agent-new.php',
    'mng-agent-edit.php', 
    'mng-agent-del.php',
    'mng-agent-list.php',
    'mng-agents.php',
    'mng-agents-edit.php',
    'mng-agents-del.php',
    'mng-agents-list.php'
];

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
echo "<tr style='background-color: #f2f2f2;'><th>Actual File</th><th>Converted ACL Name</th><th>Exists in ACL</th><th>Type</th></tr>\n";

foreach ($actual_files as $file) {
    $converted = str_replace("-", "_", basename($file, ".php"));
    
    // Check if it exists in ACL
    $sql = sprintf("SELECT COUNT(*) FROM %s WHERE file='%s'", 
                   $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL_FILES'], 
                   $dbSocket->escapeSimple($converted));
    $result = $dbSocket->query($sql);
    $exists = $result->fetchRow()[0] > 0 ? "✅ Yes" : "❌ No";
    
    $type = (strpos($file, 'agents-') !== false || $file === 'mng-agents.php') ? "Plural (agents)" : "Singular (agent)";
    
    echo "<tr><td>$file</td><td>$converted</td><td>$exists</td><td>$type</td></tr>\n";
}
echo "</table>\n";

// Check what similar entries exist in the database
echo "<h3>Similar Management Entries in ACL</h3>\n";
$sql = sprintf("SELECT file FROM %s WHERE file LIKE 'mng_%%' AND (file LIKE '%%user%%' OR file LIKE '%%operator%%' OR file LIKE '%%hotspot%%') ORDER BY file LIMIT 10", 
               $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL_FILES']);
$result = $dbSocket->query($sql);

if ($result && $result->numRows() > 0) {
    echo "<p><strong>Examples of existing management ACL entries:</strong></p>\n";
    echo "<ul>\n";
    while ($row = $result->fetchRow()) {
        echo "<li>" . $row[0] . "</li>\n";
    }
    echo "</ul>\n";
}

include_once('app/common/includes/db_close.php');
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
</style>