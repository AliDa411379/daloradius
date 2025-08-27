<?php
/**
 * Fix Agent Permissions Script - Updated Version
 * This script adds the missing ACL entries for agent management functionality
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Fix Agent Creation Permissions - Updated</h2>";

include_once 'app/common/includes/config_read.php';

$host = $configValues['CONFIG_DB_HOST'];
$port = $configValues['CONFIG_DB_PORT'];
$user = $configValues['CONFIG_DB_USER'];
$pass = $configValues['CONFIG_DB_PASS'];
$dbname = $configValues['CONFIG_DB_NAME'];

try {
    $mysqli = new mysqli($host, $user, $pass, $dbname, $port);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    echo "<h2>Fixing Agent Permissions</h2>\n";
    
    // Agent management files that need ACL entries
    $agent_files = [
        'mng_agent_new' => ['Management', 'Agents'],
        'mng_agent_edit' => ['Management', 'Agents'],
        'mng_agent_del' => ['Management', 'Agents'],
        'mng_agent_list' => ['Management', 'Agents'],
        'mng_agents' => ['Management', 'Agents'],
        'mng_agents_edit' => ['Management', 'Agents'],
        'mng_agents_del' => ['Management', 'Agents'],
        'mng_agents_list' => ['Management', 'Agents'],
        'agent_minimal' => ['Management', 'Agents']
    ];
    
    echo "<h3>Step 1: Adding ACL file entries</h3>\n";
    
    // Add ACL file entries
    $stmt = $mysqli->prepare("INSERT IGNORE INTO operators_acl_files (file, category, section) VALUES (?, ?, ?)");
    $files_added = 0;
    
    foreach ($agent_files as $file => $details) {
        $stmt->bind_param("sss", $file, $details[0], $details[1]);
        if ($stmt->execute()) {
            if ($mysqli->affected_rows > 0) {
                echo "✓ Added ACL file entry: $file<br>\n";
                $files_added++;
            } else {
                echo "- ACL file entry already exists: $file<br>\n";
            }
        }
    }
    
    echo "<p><strong>Total new ACL file entries added: $files_added</strong></p>\n";
    
    echo "<h3>Step 2: Granting permissions to all operators</h3>\n";
    
    // Get all operators
    $result = $mysqli->query("SELECT id, username FROM operators");
    $operators = [];
    while ($row = $result->fetch_assoc()) {
        $operators[] = $row;
    }
    
    echo "<p>Found " . count($operators) . " operators</p>\n";
    
    // Grant permissions to all operators
    $stmt = $mysqli->prepare("INSERT IGNORE INTO operators_acl (operator_id, file, access) VALUES (?, ?, 1)");
    $permissions_added = 0;
    
    foreach ($operators as $operator) {
        foreach (array_keys($agent_files) as $file) {
            $stmt->bind_param("is", $operator['id'], $file);
            if ($stmt->execute()) {
                if ($mysqli->affected_rows > 0) {
                    $permissions_added++;
                }
            }
        }
    }
    
    echo "<p><strong>Total new permissions granted: $permissions_added</strong></p>\n";
    
    echo "<h3>Step 3: Verification</h3>\n";
    
    // Verify the changes
    $result = $mysqli->query("SELECT * FROM operators_acl_files WHERE category = 'Management' AND section = 'Agents'");
    echo "<h4>Agent ACL Files:</h4>\n";
    echo "<table border='1'>\n";
    echo "<tr><th>ID</th><th>File</th><th>Category</th><th>Section</th></tr>\n";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>" . $row['id'] . "</td><td>" . $row['file'] . "</td><td>" . $row['category'] . "</td><td>" . $row['section'] . "</td></tr>\n";
    }
    echo "</table>\n";
    
    // Check permissions for each operator
    $result = $mysqli->query("SELECT COUNT(*) as total FROM operators_acl WHERE file LIKE 'mng_agent%'");
    $row = $result->fetch_assoc();
    echo "<p><strong>Total agent permissions in database: " . $row['total'] . "</strong></p>\n";
    
    echo "<div style='background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 20px 0; border-radius: 5px;'>\n";
    echo "<h4 style='color: #155724; margin-top: 0;'>✅ Success!</h4>\n";
    echo "<p style='color: #155724; margin-bottom: 0;'>Agent permissions have been fixed. You should now be able to create new agents.</p>\n";
    echo "</div>\n";
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "<div style='background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 20px 0; border-radius: 5px;'>\n";
    echo "<h4 style='color: #721c24; margin-top: 0;'>❌ Error</h4>\n";
    echo "<p style='color: #721c24; margin-bottom: 0;'>" . $e->getMessage() . "</p>\n";
    echo "</div>\n";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
</style>

<p><a href="app/operators/mng-agent-new.php">→ Try creating a new agent now</a></p>