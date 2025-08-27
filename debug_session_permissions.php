<?php
session_start();
echo "<h2>Session Debug Information</h2>";

echo "<h3>Session Data:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

if (isset($_SESSION['operator_id'])) {
    $operator_id = $_SESSION['operator_id'];
    echo "<h3>Operator ID: $operator_id</h3>";
    
    // Check database connection and permissions
    try {
        $mysqli = new mysqli('127.0.0.1', 'root', '', 'radius');
        if ($mysqli->connect_error) {
            echo "<p style='color: red;'>Database connection failed: " . $mysqli->connect_error . "</p>";
        } else {
            echo "<p style='color: green;'>Database connection successful</p>";
            
            // Check operator info
            $stmt = $mysqli->prepare("SELECT username, firstname, lastname, is_agent FROM operators WHERE id = ?");
            $stmt->bind_param("i", $operator_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                echo "<h3>Operator Info:</h3>";
                echo "<ul>";
                echo "<li>Username: " . htmlspecialchars($row['username']) . "</li>";
                echo "<li>Name: " . htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) . "</li>";
                echo "<li>Is Agent: " . ($row['is_agent'] ? 'Yes' : 'No') . "</li>";
                echo "</ul>";
            }
            
            // Check agent-related permissions
            $stmt2 = $mysqli->prepare("SELECT file, access FROM operators_acl WHERE operator_id = ? AND file LIKE '%agent%'");
            $stmt2->bind_param("i", $operator_id);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            
            echo "<h3>Agent-related Permissions:</h3>";
            echo "<table border='1'>";
            echo "<tr><th>File</th><th>Access</th></tr>";
            while ($row2 = $result2->fetch_assoc()) {
                $access_text = $row2['access'] ? 'Granted' : 'Denied';
                $color = $row2['access'] ? 'green' : 'red';
                echo "<tr><td>" . htmlspecialchars($row2['file']) . "</td><td style='color: $color;'>$access_text</td></tr>";
            }
            echo "</table>";
        }
        $mysqli->close();
    } catch (Exception $e) {
        echo "<p style='color: red;'>Exception: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>No operator_id in session - user may not be logged in</p>";
}

echo "<h3>Test Links:</h3>";
echo "<p><a href='app/operators/mng-agents-edit.php?agent_id=1'>Test Agent Edit (ID 1)</a></p>";
echo "<p><a href='app/operators/login.php'>Login Page</a></p>";
?>