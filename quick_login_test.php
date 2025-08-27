<?php
// Quick login test to help debug the issue
session_start();

echo "<h2>Quick Login Test</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    try {
        $mysqli = new mysqli('127.0.0.1', 'root', '', 'radius');
        if ($mysqli->connect_error) {
            echo "<p style='color: red;'>Database connection failed: " . $mysqli->connect_error . "</p>";
        } else {
            // Check credentials
            $stmt = $mysqli->prepare("SELECT id, username, firstname, lastname FROM operators WHERE username = ? AND password = ?");
            $stmt->bind_param("ss", $username, $password);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                // Set session variables
                $_SESSION['daloradius_logged_in'] = true;
                $_SESSION['operator_user'] = $row['username'];
                $_SESSION['operator_id'] = $row['id'];
                
                echo "<p style='color: green;'>Login successful!</p>";
                echo "<p>Operator ID: " . $row['id'] . "</p>";
                echo "<p>Username: " . htmlspecialchars($row['username']) . "</p>";
                echo "<p>Name: " . htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) . "</p>";
                
                echo "<h3>Test Links:</h3>";
                echo "<p><a href='app/operators/mng-agents-edit.php?agent_id=1'>Test Agent Edit (ID 1)</a></p>";
                echo "<p><a href='app/operators/mng-agents-list.php'>Test Agent List</a></p>";
                echo "<p><a href='app/operators/mng-agents.php'>Test Agent Management</a></p>";
                echo "<p><a href='app/operators/home-main.php'>Go to Dashboard</a></p>";
                
            } else {
                echo "<p style='color: red;'>Invalid username or password</p>";
            }
        }
        $mysqli->close();
    } catch (Exception $e) {
        echo "<p style='color: red;'>Exception: " . $e->getMessage() . "</p>";
    }
}

// Show current session status
echo "<h3>Current Session Status:</h3>";
if (isset($_SESSION['daloradius_logged_in']) && $_SESSION['daloradius_logged_in']) {
    echo "<p style='color: green;'>Currently logged in as: " . htmlspecialchars($_SESSION['operator_user']) . " (ID: " . $_SESSION['operator_id'] . ")</p>";
    echo "<p><a href='app/operators/mng-agents-edit.php?agent_id=1'>Test Agent Edit</a></p>";
} else {
    echo "<p style='color: red;'>Not logged in</p>";
}

?>

<h3>Available Operators:</h3>
<?php
try {
    $mysqli = new mysqli('127.0.0.1', 'root', '', 'radius');
    if (!$mysqli->connect_error) {
        $result = $mysqli->query("SELECT username FROM operators LIMIT 5");
        echo "<ul>";
        while ($row = $result->fetch_assoc()) {
            echo "<li>" . htmlspecialchars($row['username']) . "</li>";
        }
        echo "</ul>";
        $mysqli->close();
    }
} catch (Exception $e) {
    echo "<p>Error loading operators</p>";
}
?>

<form method="POST">
    <h3>Quick Login:</h3>
    <p>
        <label>Username:</label><br>
        <input type="text" name="username" value="administrator" required>
    </p>
    <p>
        <label>Password:</label><br>
        <input type="password" name="password" placeholder="Enter password" required>
    </p>
    <p>
        <input type="submit" value="Login">
    </p>
</form>

<p><strong>Note:</strong> This is a debug script. Use the actual login page for normal access: <a href="app/operators/login.php">Login Page</a></p>