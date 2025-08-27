<?php
// Minimal test of agent edit functionality without permission checks
echo "<h2>Minimal Agent Edit Test</h2>";

// Simulate session data
session_start();
$_SESSION['operator_user'] = 'admin';
$_SESSION['operator_id'] = 1;
$_SESSION['daloradius_logged_in'] = true;

// Include necessary files
include_once('app/common/includes/config_read.php');

// Test database connection
try {
    include('app/common/includes/db_open.php');
    echo "<p style='color: green;'>Database connection successful</p>";
    
    // Test agent query
    $agent_id = 1;
    $sql = "SELECT name, company, phone, email, address, city, country FROM " . $configValues['CONFIG_DB_TBL_DALOAGENTS'] . " WHERE id = ?";
    $stmt = $dbSocket->prepare($sql);
    
    if (DB::isError($stmt)) {
        echo "<p style='color: red;'>Database prepare failed: " . $stmt->getMessage() . "</p>";
    } else {
        $res = $dbSocket->execute($stmt, array($agent_id));
        if (DB::isError($res)) {
            echo "<p style='color: red;'>Database execute failed: " . $res->getMessage() . "</p>";
        } else {
            $row = $res->fetchRow();
            if (!$row) {
                echo "<p style='color: red;'>Agent not found</p>";
            } else {
                list($agent_name, $company, $phone, $email, $address, $city, $country) = $row;
                echo "<h3>Agent Data Retrieved Successfully:</h3>";
                echo "<ul>";
                echo "<li>Name: " . htmlspecialchars($agent_name) . "</li>";
                echo "<li>Company: " . htmlspecialchars($company) . "</li>";
                echo "<li>Phone: " . htmlspecialchars($phone) . "</li>";
                echo "<li>Email: " . htmlspecialchars($email) . "</li>";
                echo "<li>Address: " . htmlspecialchars($address) . "</li>";
                echo "<li>City: " . htmlspecialchars($city) . "</li>";
                echo "<li>Country: " . htmlspecialchars($country) . "</li>";
                echo "</ul>";
                
                echo "<p style='color: green;'>Core agent edit functionality appears to be working!</p>";
            }
        }
    }
    
    include('app/common/includes/db_close.php');
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Exception: " . $e->getMessage() . "</p>";
}

echo "<h3>Next Steps:</h3>";
echo "<p>If the data above loaded successfully, the issue is likely with:</p>";
echo "<ul>";
echo "<li>User session/login status</li>";
echo "<li>Permission checking</li>";
echo "<li>Missing operator_id in session</li>";
echo "</ul>";

echo "<p><a href='debug_session_permissions.php'>Check Session & Permissions</a></p>";
echo "<p><a href='app/operators/login.php'>Go to Login Page</a></p>";
?>