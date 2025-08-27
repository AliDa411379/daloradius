<?php
/**
 * Simple Agent Permissions Fix
 * Uses daloRADIUS's own database connection method
 */

// Include daloRADIUS configuration and database connection
include_once('app/common/includes/config_read.php');
include_once('app/common/includes/db_open.php');

echo "<h2>Fixing Agent Management Permissions</h2>\n";

// Agent management files that need ACL entries
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

try {
    echo "<h3>Step 1: Adding ACL file entries</h3>\n";
    
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
                echo "✓ Added ACL file entry: " . $file_info['file'] . "<br>\n";
                $files_added++;
            } else {
                echo "❌ Error adding " . $file_info['file'] . ": " . $result->getMessage() . "<br>\n";
            }
        } else {
            echo "- ACL file entry already exists: " . $file_info['file'] . "<br>\n";
        }
    }
    
    echo "<p><strong>New ACL file entries added: $files_added</strong></p>\n";
    
    echo "<h3>Step 2: Granting permissions to all operators</h3>\n";
    
    // Get all operators
    $sql = sprintf("SELECT id, username FROM %s", $configValues['CONFIG_DB_TBL_DALOOPERATORS']);
    $result = $dbSocket->query($sql);
    
    $operators = [];
    while ($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
        $operators[] = $row;
    }
    
    echo "<p>Found " . count($operators) . " operators</p>\n";
    
    $permissions_added = 0;
    foreach ($operators as $operator) {
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
                    $permissions_added++;
                }
            }
        }
    }
    
    echo "<p><strong>New permissions granted: $permissions_added</strong></p>\n";
    
    echo "<h3>Step 3: Verification</h3>\n";
    
    // Verify the changes
    $sql = sprintf("SELECT * FROM %s WHERE category='Management' AND section='Agents'", 
                   $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL_FILES']);
    $result = $dbSocket->query($sql);
    
    echo "<h4>Agent ACL Files:</h4>\n";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr style='background-color: #f2f2f2;'><th>ID</th><th>File</th><th>Category</th><th>Section</th></tr>\n";
    
    while ($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
        echo "<tr><td>" . $row['id'] . "</td><td>" . $row['file'] . "</td><td>" . $row['category'] . "</td><td>" . $row['section'] . "</td></tr>\n";
    }
    echo "</table>\n";
    
    // Check total permissions
    $sql = sprintf("SELECT COUNT(*) FROM %s WHERE file LIKE 'mng_agent%%'", 
                   $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL']);
    $result = $dbSocket->query($sql);
    $total = $result->fetchRow()[0];
    
    echo "<p><strong>Total agent permissions in database: $total</strong></p>\n";
    
    echo "<div style='background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 20px 0; border-radius: 5px;'>\n";
    echo "<h4 style='color: #155724; margin-top: 0;'>✅ Success!</h4>\n";
    echo "<p style='color: #155724; margin-bottom: 0;'>Agent permissions have been fixed. You should now be able to create new agents.</p>\n";
    echo "</div>\n";
    
} catch (Exception $e) {
    echo "<div style='background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 20px 0; border-radius: 5px;'>\n";
    echo "<h4 style='color: #721c24; margin-top: 0;'>❌ Error</h4>\n";
    echo "<p style='color: #721c24; margin-bottom: 0;'>" . $e->getMessage() . "</p>\n";
    echo "</div>\n";
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

<p><strong>Next steps:</strong></p>
<ul>
<li><a href="app/operators/mng-agent-new.php">→ Try creating a new agent</a></li>
<li><a href="app/operators/mng-agents.php">→ Go to agent management</a></li>
<li><a href="app/operators/">→ Return to operators panel</a></li>
</ul>