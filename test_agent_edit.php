<?php
// Simple test to check if agent edit page works
echo "<h2>Testing Agent Edit Page Access</h2>";

// Test database connection
try {
    $mysqli = new mysqli('127.0.0.1', 'root', '', 'radius');
    if ($mysqli->connect_error) {
        echo "<p style='color: red;'>Database connection failed: " . $mysqli->connect_error . "</p>";
    } else {
        echo "<p style='color: green;'>Database connection successful</p>";
        
        // Test agents table
        $result = $mysqli->query("SELECT id, name FROM agents LIMIT 3");
        if ($result) {
            echo "<h3>Available Agents:</h3>";
            echo "<ul>";
            while ($row = $result->fetch_assoc()) {
                $agent_id = $row['id'];
                $agent_name = htmlspecialchars($row['name']);
                echo "<li>ID: $agent_id - Name: $agent_name";
                echo " - <a href='app/operators/mng-agents-edit.php?agent_id=$agent_id' target='_blank'>Edit Link</a></li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color: red;'>Error querying agents table: " . $mysqli->error . "</p>";
        }
    }
    $mysqli->close();
} catch (Exception $e) {
    echo "<p style='color: red;'>Exception: " . $e->getMessage() . "</p>";
}

echo "<h3>Direct Test Links:</h3>";
echo "<p><a href='app/operators/mng-agents-edit.php?agent_id=1' target='_blank'>Test Edit Agent ID 1</a></p>";
echo "<p><a href='app/operators/mng-agents-list.php' target='_blank'>Test Agent List</a></p>";
echo "<p><a href='app/operators/mng-agents.php' target='_blank'>Test Agent Management</a></p>";
?>