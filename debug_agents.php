<?php
include_once('app/common/includes/config_read.php');

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
    
    echo "<h2>Debug: Agents in Database</h2>";
    
    // Check if agents table exists
    $result = $mysqli->query("SHOW TABLES LIKE 'agents'");
    if ($result->num_rows == 0) {
        echo "<p><strong>❌ Agents table does not exist!</strong></p>";
        
        // Show all tables
        echo "<h3>Available tables:</h3>";
        $result = $mysqli->query("SHOW TABLES");
        while ($row = $result->fetch_row()) {
            echo "- " . $row[0] . "<br>";
        }
    } else {
        echo "<p><strong>✅ Agents table exists</strong></p>";
        
        // Show table structure
        echo "<h3>Agents table structure:</h3>";
        $result = $mysqli->query("DESCRIBE agents");
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['Field']}</td>";
            echo "<td>{$row['Type']}</td>";
            echo "<td>{$row['Null']}</td>";
            echo "<td>{$row['Key']}</td>";
            echo "<td>{$row['Default']}</td>";
            echo "<td>{$row['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Count agents
        $result = $mysqli->query("SELECT COUNT(*) as count FROM agents");
        $count = $result->fetch_assoc()['count'];
        echo "<p><strong>Total agents:</strong> $count</p>";
        
        if ($count > 0) {
            echo "<h3>Agents list:</h3>";
            $result = $mysqli->query("SELECT * FROM agents ORDER BY id DESC LIMIT 10");
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>ID</th><th>Name</th><th>Company</th><th>Email</th><th>Phone</th><th>Creation Date</th><th>Created By</th></tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>{$row['id']}</td>";
                echo "<td>{$row['name']}</td>";
                echo "<td>{$row['company']}</td>";
                echo "<td>{$row['email']}</td>";
                echo "<td>{$row['phone']}</td>";
                echo "<td>{$row['creationdate']}</td>";
                echo "<td>{$row['creationby']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { margin-top: 10px; }
th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
th { background-color: #f2f2f2; }
</style>