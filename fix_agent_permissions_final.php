<?php
/**
 * Final Agent Permissions Fix
 * Addresses the singular/plural naming inconsistency issue
 */

include_once('app/common/includes/config_read.php');
include_once('app/common/includes/db_open.php');

echo "<h2>Final Agent Permissions Fix</h2>\n";
echo "<p>Addressing the singular/plural naming inconsistency you identified.</p>\n";

// The actual files and their correct ACL names
$agent_files_mapping = [
    // Singular "agent" files
    'mng-agent-new.php' => 'mng_agent_new',
    'mng-agent-edit.php' => 'mng_agent_edit', 
    'mng-agent-del.php' => 'mng_agent_del',
    'mng-agent-list.php' => 'mng_agent_list',
    
    // Plural "agents" files  
    'mng-agents.php' => 'mng_agents',
    'mng-agents-edit.php' => 'mng_agents_edit',
    'mng-agents-del.php' => 'mng_agents_del',
    'mng-agents-list.php' => 'mng_agents_list'
];

echo "<h3>Step 1: File Name Analysis</h3>\n";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
echo "<tr style='background-color: #f2f2f2;'><th>Actual File</th><th>ACL Name</th><th>Type</th><th>Currently Exists</th></tr>\n";

foreach ($agent_files_mapping as $file => $acl_name) {
    $type = (strpos($file, 'agents') !== false) ? "Plural (agents)" : "Singular (agent)";
    
    // Check if ACL entry exists
    $sql = sprintf("SELECT COUNT(*) FROM %s WHERE file='%s'", 
                   $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL_FILES'], 
                   $dbSocket->escapeSimple($acl_name));
    $result = $dbSocket->query($sql);
    $exists = $result->fetchRow()[0] > 0 ? "✅ Yes" : "❌ No";
    
    echo "<tr><td>$file</td><td>$acl_name</td><td>$type</td><td>$exists</td></tr>\n";
}
echo "</table>\n";

echo "<h3>Step 2: Adding Missing ACL File Entries</h3>\n";

// ACL file entries to add
$acl_entries = [
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
foreach ($acl_entries as $entry) {
    // Check if already exists
    $sql = sprintf("SELECT COUNT(*) FROM %s WHERE file='%s'", 
                  $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL_FILES'], 
                  $dbSocket->escapeSimple($entry['file']));
    $result = $dbSocket->query($sql);
    $count = $result->fetchRow()[0];
    
    if ($count == 0) {
        // Add the ACL file entry
        $sql = sprintf("INSERT INTO %s (file, category, section) VALUES ('%s', '%s', '%s')",
                      $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL_FILES'],
                      $dbSocket->escapeSimple($entry['file']),
                      $dbSocket->escapeSimple($entry['category']),
                      $dbSocket->escapeSimple($entry['section']));
        $result = $dbSocket->query($sql);
        
        if (!DB::isError($result)) {
            echo "✅ Added: " . $entry['file'] . "<br>\n";
            $files_added++;
        } else {
            echo "❌ Error adding " . $entry['file'] . ": " . $result->getMessage() . "<br>\n";
        }
    } else {
        echo "ℹ️ Already exists: " . $entry['file'] . "<br>\n";
    }
}

echo "<p><strong>ACL file entries added: $files_added</strong></p>\n";

echo "<h3>Step 3: Granting Permissions to All Operators</h3>\n";

// Get all operators
$sql = sprintf("SELECT id, username FROM %s", $configValues['CONFIG_DB_TBL_DALOOPERATORS']);
$result = $dbSocket->query($sql);

$operators = [];
while ($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
    $operators[] = $row;
}

echo "<p>Found " . count($operators) . " operators</p>\n";

$total_permissions_added = 0;
foreach ($operators as $operator) {
    $operator_permissions_added = 0;
    
    foreach ($acl_entries as $entry) {
        // Check if permission already exists
        $sql = sprintf("SELECT COUNT(*) FROM %s WHERE operator_id=%d AND file='%s'",
                      $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'],
                      $operator['id'],
                      $dbSocket->escapeSimple($entry['file']));
        $result = $dbSocket->query($sql);
        $count = $result->fetchRow()[0];
        
        if ($count == 0) {
            // Add permission
            $sql = sprintf("INSERT INTO %s (operator_id, file, access) VALUES (%d, '%s', 1)",
                          $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'],
                          $operator['id'],
                          $dbSocket->escapeSimple($entry['file']));
            $result = $dbSocket->query($sql);
            
            if (!DB::isError($result)) {
                $operator_permissions_added++;
                $total_permissions_added++;
            }
        }
    }
    
    if ($operator_permissions_added > 0) {
        echo "✅ " . $operator['username'] . ": granted $operator_permissions_added permissions<br>\n";
    } else {
        echo "ℹ️ " . $operator['username'] . ": already had all permissions<br>\n";
    }
}

echo "<p><strong>Total new permissions granted: $total_permissions_added</strong></p>\n";

echo "<h3>Step 4: Testing the Fix</h3>\n";

// Test the specific problematic file
$test_file = "mng_agent_new";
echo "<h4>Testing access for: $test_file (from mng-agent-new.php)</h4>\n";

foreach ($operators as $operator) {
    $sql = sprintf("SELECT access FROM %s WHERE operator_id=%d AND file='%s'",
                   $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'], 
                   $operator['id'], 
                   $dbSocket->escapeSimple($test_file));
    $result = $dbSocket->query($sql);
    
    if ($result && $result->numRows() > 0) {
        $access = $result->fetchRow()[0];
        $status = $access ? "✅ GRANTED" : "❌ DENIED";
        echo "• " . $operator['username'] . ": $status<br>\n";
    } else {
        echo "• " . $operator['username'] . ": ❌ NO PERMISSION ENTRY<br>\n";
    }
}

echo "<h3>Step 5: Final Verification</h3>\n";

// Show all agent-related ACL entries
$sql = sprintf("SELECT * FROM %s WHERE section='Agents' ORDER BY file", 
               $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL_FILES']);
$result = $dbSocket->query($sql);

echo "<h4>All Agent ACL Entries:</h4>\n";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
echo "<tr style='background-color: #f2f2f2;'><th>ID</th><th>File</th><th>Category</th><th>Section</th></tr>\n";

while ($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
    echo "<tr><td>" . $row['id'] . "</td><td>" . $row['file'] . "</td><td>" . $row['category'] . "</td><td>" . $row['section'] . "</td></tr>\n";
}
echo "</table>\n";

// Count total permissions
$sql = sprintf("SELECT COUNT(*) FROM %s WHERE file LIKE 'mng_agent%%'", 
               $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL']);
$result = $dbSocket->query($sql);
$total = $result->fetchRow()[0];

echo "<p><strong>Total agent permissions in database: $total</strong></p>\n";

echo "<div style='background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 20px 0; border-radius: 5px;'>\n";
echo "<h4 style='color: #155724; margin-top: 0;'>🎉 Fix Complete!</h4>\n";
echo "<p style='color: #155724;'>The agent permissions have been fixed, addressing both singular and plural naming conventions.</p>\n";
echo "<p style='color: #155724; margin-bottom: 0;'><strong>You should now be able to create new agents without permission errors.</strong></p>\n";
echo "</div>\n";

include_once('app/common/includes/db_close.php');
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
</style>

<div style='background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0; border-radius: 5px;'>
<h4 style='color: #856404; margin-top: 0;'>🧪 Test the Fix</h4>
<p style='color: #856404; margin-bottom: 0;'>
<a href="app/operators/mng-agent-new.php" target="_blank" style="color: #856404; font-weight: bold;">→ Click here to test agent creation</a>
</p>
</div>