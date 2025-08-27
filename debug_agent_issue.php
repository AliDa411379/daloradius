<?php
// Debug agent creation issue - bypasses permission system
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Debug Agent Creation Issue</h2>";

// Start session manually
session_start();

echo "<h3>1. Session Check</h3>";
if (isset($_SESSION['operator_user'])) {
    echo "✅ Logged in as: " . $_SESSION['operator_user'] . "<br>";
    if (isset($_SESSION['operator_id'])) {
        echo "✅ Operator ID: " . $_SESSION['operator_id'] . "<br>";
    } else {
        echo "❌ No operator_id in session<br>";
    }
} else {
    echo "❌ Not logged in<br>";
    echo "<p><a href='app/operators/login.php'>Please login first</a></p>";
    exit;
}

echo "<h3>2. Check Agent Creation Page Permission</h3>";

try {
    include_once('app/common/includes/config_read.php');
    include_once('app/common/includes/db_open.php');
    
    $operator_id = $_SESSION['operator_id'];
    $file = 'mng_agent_new';
    
    $sql = sprintf("SELECT access FROM %s WHERE operator_id=%d AND file='%s'",
                   $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'], $operator_id, $file);
    echo "SQL: $sql<br>";
    
    $result = $dbSocket->query($sql);
    if ($result && $result->numRows() > 0) {
        $access = $result->fetchRow()[0];
        echo "Permission exists: " . ($access ? 'GRANTED' : 'DENIED') . "<br>";
        
        if (!$access) {
            echo "<h4>Fixing permission...</h4>";
            $sql = sprintf("UPDATE %s SET access=1 WHERE operator_id=%d AND file='%s'",
                          $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'], $operator_id, $file);
            $result = $dbSocket->query($sql);
            if (!DB::isError($result)) {
                echo "✅ Permission updated to GRANTED<br>";
            } else {
                echo "❌ Error updating permission: " . $result->getMessage() . "<br>";
            }
        }
    } else {
        echo "Permission does not exist<br>";
        echo "<h4>Adding permission...</h4>";
        
        $sql = sprintf("INSERT INTO %s (operator_id, file, access) VALUES (%d, '%s', 1)",
                      $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'], $operator_id, $file);
        $result = $dbSocket->query($sql);
        if (!DB::isError($result)) {
            echo "✅ Permission added and GRANTED<br>";
        } else {
            echo "❌ Error adding permission: " . $result->getMessage() . "<br>";
        }
    }
    
    include_once('app/common/includes/db_close.php');
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<h3>3. Test Agent Creation Page Access</h3>";
echo "<p><a href='app/operators/mng-agent-new.php' target='_blank'>→ Try Agent Creation Page</a></p>";

echo "<h3>4. Check Recent Error Logs</h3>";
$error_log = '/opt/lampp/logs/error_log';
if (file_exists($error_log)) {
    $lines = file($error_log);
    $recent_lines = array_slice($lines, -10);
    echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
    foreach ($recent_lines as $line) {
        echo htmlspecialchars($line);
    }
    echo "</pre>";
} else {
    echo "Error log not found<br>";
}

echo "<h3>5. Manual Form Test</h3>";
echo "<p>Let's test if we can render a simple form without the permission system:</p>";

try {
    // Test basic HTML form
    echo "<form method='POST' action='#'>";
    echo "<fieldset>";
    echo "<legend>Test Agent Form</legend>";
    echo "<div class='mb-3'>";
    echo "<label for='name' class='form-label'>Agent Name</label>";
    echo "<input type='text' class='form-control' id='name' name='name' required>";
    echo "</div>";
    echo "<div class='mb-3'>";
    echo "<label for='email' class='form-label'>Email</label>";
    echo "<input type='email' class='form-control' id='email' name='email'>";
    echo "</div>";
    echo "<button type='submit' class='btn btn-primary'>Create Agent</button>";
    echo "</fieldset>";
    echo "</form>";
    
    echo "<p>✅ Basic HTML form rendered successfully</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error rendering basic form: " . $e->getMessage() . "</p>";
}

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.form-label { font-weight: bold; }
.form-control { width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 4px; }
.btn { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
.btn:hover { background: #0056b3; }
fieldset { border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; }
legend { font-weight: bold; padding: 0 10px; }
.mb-3 { margin-bottom: 15px; }
</style>