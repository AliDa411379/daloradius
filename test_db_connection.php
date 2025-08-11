<?php
// Test database connection for daloRADIUS
include_once 'app/common/includes/config_read.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>daloRADIUS Database Connection Test</title>
    <link rel="stylesheet" href="app/common/static/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h2 class="mb-0">daloRADIUS Database Connection Test</h2>
                    </div>
                    <div class="card-body">
<?php
// Test database connection
$host = $configValues['CONFIG_DB_HOST'];
$port = $configValues['CONFIG_DB_PORT'];
$user = $configValues['CONFIG_DB_USER'];
$pass = $configValues['CONFIG_DB_PASS'];
$dbname = $configValues['CONFIG_DB_NAME'];

echo "<h5>Connection Details:</h5>";
echo "<ul class='list-group list-group-flush mb-3'>";
echo "<li class='list-group-item'><strong>Host:</strong> $host</li>";
echo "<li class='list-group-item'><strong>Port:</strong> $port</li>";
echo "<li class='list-group-item'><strong>User:</strong> $user</li>";
echo "<li class='list-group-item'><strong>Database:</strong> $dbname</li>";
echo "</ul>";

try {
    $mysqli = new mysqli($host, $user, $pass, $dbname, $port);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    echo "<div class='alert alert-success'><strong>✓ Database connection successful!</strong></div>";
    
    // Test some basic queries
    $result = $mysqli->query("SELECT COUNT(*) as count FROM operators");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p><strong>Operators in database:</strong> " . $row['count'] . "</p>";
    }
    
    $result = $mysqli->query("SELECT COUNT(*) as count FROM billing_plans");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p><strong>Billing plans in database:</strong> " . $row['count'] . "</p>";
    }
    
    $result = $mysqli->query("SELECT COUNT(*) as count FROM billing_history");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p><strong>Billing history records:</strong> " . $row['count'] . "</p>";
    }
    
    // Check for operators with their usernames
    $result = $mysqli->query("SELECT username, firstname, lastname FROM operators LIMIT 5");
    if ($result && $result->num_rows > 0) {
        echo "<h5>Available Operators:</h5>";
        echo "<div class='table-responsive'>";
        echo "<table class='table table-striped'>";
        echo "<thead><tr><th>Username</th><th>First Name</th><th>Last Name</th></tr></thead>";
        echo "<tbody>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>" . htmlspecialchars($row['username']) . "</td>";
            echo "<td>" . htmlspecialchars($row['firstname']) . "</td>";
            echo "<td>" . htmlspecialchars($row['lastname']) . "</td></tr>";
        }
        echo "</tbody></table></div>";
    }
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'><strong>✗ Database connection failed:</strong> " . $e->getMessage() . "</div>";
}
?>
                        <div class="mt-4">
                            <a href="app/operators/" class="btn btn-primary">Go to daloRADIUS Operators Panel</a>
                            <a href="app/users/" class="btn btn-secondary">Go to User Portal</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>