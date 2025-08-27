<?php
// Check agent permissions in the database
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
    
    echo "<h2>Agent Permission Analysis</h2>\n";
    
    // Check if agent-related ACL files exist
    echo "<h3>1. Checking ACL Files for Agent Management:</h3>\n";
    $result = $mysqli->query("SELECT * FROM operators_acl_files WHERE file LIKE '%agent%'");
    if ($result && $result->num_rows > 0) {
        echo "<table border='1'>\n";
        echo "<tr><th>ID</th><th>File</th><th>Category</th><th>Section</th></tr>\n";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>" . $row['id'] . "</td><td>" . $row['file'] . "</td><td>" . $row['category'] . "</td><td>" . $row['section'] . "</td></tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "<p><strong>❌ No agent-related ACL files found!</strong></p>\n";
        
        // Let's check what files are available
        echo "<h4>Available ACL files (first 20):</h4>\n";
        $result = $mysqli->query("SELECT * FROM operators_acl_files LIMIT 20");
        if ($result) {
            echo "<table border='1'>\n";
            echo "<tr><th>ID</th><th>File</th><th>Category</th><th>Section</th></tr>\n";
            while ($row = $result->fetch_assoc()) {
                echo "<tr><td>" . $row['id'] . "</td><td>" . $row['file'] . "</td><td>" . $row['category'] . "</td><td>" . $row['section'] . "</td></tr>\n";
            }
            echo "</table>\n";
        }
    }
    
    // Check current operators and their permissions
    echo "<h3>2. Current Operators:</h3>\n";
    $result = $mysqli->query("SELECT id, username, firstname, lastname FROM operators");
    if ($result) {
        echo "<table border='1'>\n";
        echo "<tr><th>ID</th><th>Username</th><th>First Name</th><th>Last Name</th></tr>\n";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>" . $row['id'] . "</td><td>" . $row['username'] . "</td><td>" . $row['firstname'] . "</td><td>" . $row['lastname'] . "</td></tr>\n";
        }
        echo "</table>\n";
    }
    
    // Check permissions for a specific operator (if provided)
    if (isset($_GET['operator_id'])) {
        $operator_id = intval($_GET['operator_id']);
        echo "<h3>3. Permissions for Operator ID $operator_id:</h3>\n";
        
        $stmt = $mysqli->prepare("SELECT acl.file, acl.access FROM operators_acl acl WHERE acl.operator_id = ?");
        $stmt->bind_param("i", $operator_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            echo "<table border='1'>\n";
            echo "<tr><th>File</th><th>Access</th></tr>\n";
            while ($row = $result->fetch_assoc()) {
                $access_text = $row['access'] ? 'Granted' : 'Denied';
                echo "<tr><td>" . $row['file'] . "</td><td>" . $access_text . "</td></tr>\n";
            }
            echo "</table>\n";
        } else {
            echo "<p><strong>❌ No permissions found for this operator!</strong></p>\n";
        }
    }
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>\n";
}
?>

<p><strong>Usage:</strong> Add ?operator_id=X to the URL to check permissions for a specific operator.</p>