<?php
/**
 * Corrected Agent Permissions Fix
 * This addresses the naming inconsistency issue
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once('app/common/includes/config_read.php');
include_once('app/common/includes/db_open.php');

echo "<h2>Corrected Agent Permissions Fix</h2>\n";

// First, let's debug what file names are being checked
$test_files = [
    'mng-agent-new.php',
    'mng-agent-edit.php', 
    'mng-agent-del.php',
    'mng-agent-list.php',
    'mng-agents.php',
    'mng-agents-edit.php',
    'mng-agents-del.php',
    'mng-agents-list.php'
];

echo "<h3>File Name Conversion Analysis</h3>\n";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
echo "<tr style='background-color: #f2f2f2;'><th>Original File</th><th>Converted Name</th><th>Exists in ACL</th></tr>\n";

$converted_files = [];
foreach ($test_files as $file) {
    $converted = str_replace("-", "_", basename($file, ".php"));
    $converted_files[] = $converted;
    
    // Check if it exists in ACL
    $sql = sprintf("SELECT COUNT(*) FROM %s WHERE file='%s'", 
                   $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL_FILES'], 
                   $dbSocket->escapeSimple($converted));
    $result = $dbSocket->query($sql);
    $exists = $result->fetchRow()[0] > 0 ? "✅ Yes" : "❌ No";
    
    echo "<tr><td>$file</td><td>$converted</td><td>$exists</td></tr>\n";
}
echo "</table>\n";

// Now let's add the missing ACL entries
echo "<h3>Adding Missing ACL Entries</h3>\n";

$agent_files = [
    ['file' => 'mng_agent_new', 'category' => 'Management', 'section' => 'Agents'],
    ['file' => 'mng_agent_edit', 'category' => 'Management', 'section' => 'Agents'],
    ['file' => 'mng_agent_del', 'category' => 'Management', 'section' => 'Agents'],
    ['file' => 'mng_agent_list', 'category' => 'Management', 'section' => 'Agents'],
    ['file' => 'mng_agents', 'category' => 'Management', 'section' => 'Agents'],
    ['file' => 'mng_agents_edit', 'category' => 'Management', 'section' => 'Agents'],
    ['file' => 'mng_agents_del', 'category' => 'Management', 'section' => 'Agents'],
    ['file' => 'mng_agents_list', 'category' => 'Management', 'section' => 'Agents']
];

$files_added = 0;
foreach ($agent_files as $file_info) {
    // Check if file already exists
    $sql = sprintf("SELECT COUNT(*) FROM %s WHERE file='%s'", 
                  $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL_FILES'], 
                  $dbSocket->escapeSimple($file_info['file']));
    $result = $dbSocket->query($sql);
    $count = $result->fetchRow()[0];
    
    if ($count == 0) {
        // Add the ACL file entry
        $sql = sprintf("INSERT INTO %s (file, category, section) VALUES ('%s', '%s', '%s')",
                      $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL_FILES'],
                      $dbSocket->escapeSimple($file_info['file']),
                      $dbSocket->escapeSimple($file_info['category']),
                      $dbSocket->escapeSimple($file_info['section']));
        $result = $dbSocket->query($sql);
        
        if (!DB::isError($result)) {
            echo "✅ Added ACL file entry: " . $file_info['file'] . "<br>\n";
            $files_added++;
        } else {
            echo "❌ Error adding " . $file_info['file'] . ": " . $result->getMessage() . "<br>\n";
        }
    } else {
        echo "ℹ️ ACL file entry already exists: " . $file_info['file'] . "<br>\n";
    }
}

echo "<p><strong>New ACL file entries added: $files_added</strong></p>\n";

// Get all operators and grant permissions
echo "<h3>Granting Permissions to All Operators</h3>\n";

$sql = sprintf("SELECT id, username FROM %s", $configValues['CONFIG_DB_TBL_DALOOPERATORS']);
$result = $dbSocket->query($sql);

$operators = [];
while ($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
    $operators[] = $row;
}

echo "<p>Found " . count($operators) . " operators</p>\n";

$permissions_added = 0;
foreach ($operators as $operator) {
    echo "<h4>Operator: " . $operator['username'] . " (ID: " . $operator['id'] . ")</h4>\n";
    
    foreach ($agent_files as $file_info) {
        // Check if permission already exists
        $sql = sprintf("SELECT COUNT(*) FROM %s WHERE operator_id=%d AND file='%s'",
                      $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'],
                      $operator['id'],
                      $dbSocket->escapeSimple($file_info['file']));
        $result = $dbSocket->query($sql);
        $count = $result->fetchRow()[0];
        
        if ($count == 0) {
            // Add permission
            $sql = sprintf("INSERT INTO %s (operator_id, file, access) VALUES (%d, '%s', 1)",
                          $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'],
                          $operator['id'],
                          $dbSocket->escapeSimple($file_info['file']));
            $result = $dbSocket->query($sql);
            
            if (!DB::isError($result)) {
                echo "✅ Granted permission: " . $file_info['file'] . "<br>\n";
                $permissions_added++;
            } else {
                echo "❌ Error granting permission for " . $file_info['file'] . ": " . $result->getMessage() . "<br>\n";
            }
        } else {
            echo "ℹ️ Permission already exists: " . $file_info['file'] . "<br>\n";
        }
    }
}

echo "<p><strong>Total new permissions granted: $permissions_added</strong></p>\n";

// Final verification
echo "<h3>Final Verification</h3>\n";

// Test the specific file that was failing
$test_file = "mng_agent_new";
echo "<h4>Testing access for: $test_file</h4>\n";

foreach ($operators as $operator) {
    $sql = sprintf("SELECT access FROM %s WHERE operator_id=%d AND file='%s'",
                   $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'], 
                   $operator['id'], 
                   $dbSocket->escapeSimple($test_file));
    $result = $dbSocket->query($sql);
    
    if ($result && $result->numRows() > 0) {
        $access = $result->fetchRow()[0];
        $status = $access ? "✅ GRANTED" : "❌ DENIED";
        echo "Operator " . $operator['username'] . ": $status<br>\n";
    } else {
        echo "Operator " . $operator['username'] . ": ❌ NO PERMISSION ENTRY<br>\n";
    }
}

echo "<div style='background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 20px 0; border-radius: 5px;'>\n";
echo "<h4 style='color: #155724; margin-top: 0;'>✅ Fix Applied!</h4>\n";
echo "<p style='color: #155724; margin-bottom: 0;'>The agent permissions have been corrected. Try accessing the agent creation page now.</p>\n";
echo "</div>\n";

// Close database connection
include_once('app/common/includes/db_close.php');
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
</style>

<p><strong>Test the fix:</strong></p>
<ul>
<li><a href="app/operators/mng-agent-new.php" target="_blank">→ Try creating a new agent</a></li>
<li><a href="app/operators/mng-agents.php" target="_blank">→ Go to agent management</a></li>
</ul>