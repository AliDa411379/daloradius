<?php
/**
 * Check existing operators
 */

include_once('app/common/includes/config_read.php');
include_once('app/common/includes/db_open.php');

echo "<h2>Existing Operators Check</h2>\n";

$sql = sprintf("SELECT id, username, firstname, lastname, creationdate FROM %s ORDER BY id", 
               $configValues['CONFIG_DB_TBL_DALOOPERATORS']);
$result = $dbSocket->query($sql);

if ($result && $result->numRows() > 0) {
    echo "<h3>✅ Found " . $result->numRows() . " operator(s):</h3>\n";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr style='background-color: #f2f2f2;'><th>ID</th><th>Username</th><th>First Name</th><th>Last Name</th><th>Created</th></tr>\n";
    
    while ($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td><strong>" . $row['username'] . "</strong></td>";
        echo "<td>" . $row['firstname'] . "</td>";
        echo "<td>" . $row['lastname'] . "</td>";
        echo "<td>" . $row['creationdate'] . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    echo "<div style='background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 20px 0; border-radius: 5px;'>\n";
    echo "<h4 style='color: #155724; margin-top: 0;'>✅ Operators Found!</h4>\n";
    echo "<p style='color: #155724; margin-bottom: 0;'>You can log in using one of the usernames above. If you don't know the password, you may need to reset it or create a new operator.</p>\n";
    echo "</div>\n";
    
} else {
    echo "<h3>❌ No operators found in database!</h3>\n";
    echo "<p>You need to create an operator account first.</p>\n";
    
    echo "<div style='background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0; border-radius: 5px;'>\n";
    echo "<h4 style='color: #856404; margin-top: 0;'>🔧 Create Default Operator</h4>\n";
    echo "<p style='color: #856404;'>I can create a default administrator operator for you.</p>\n";
    echo "<form method='post' style='margin-bottom: 0;'>\n";
    echo "<p><strong>Username:</strong> <input type='text' name='username' value='admin' required></p>\n";
    echo "<p><strong>Password:</strong> <input type='password' name='password' placeholder='Enter password' required></p>\n";
    echo "<p><strong>First Name:</strong> <input type='text' name='firstname' value='Administrator' required></p>\n";
    echo "<p><strong>Last Name:</strong> <input type='text' name='lastname' value='User' required></p>\n";
    echo "<p><input type='submit' name='create_operator' value='Create Operator' style='background-color: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'></p>\n";
    echo "</form>\n";
    echo "</div>\n";
}

// Handle operator creation
if (isset($_POST['create_operator'])) {
    $username = $dbSocket->escapeSimple($_POST['username']);
    $password = $dbSocket->escapeSimple($_POST['password']);
    $firstname = $dbSocket->escapeSimple($_POST['firstname']);
    $lastname = $dbSocket->escapeSimple($_POST['lastname']);
    $creationdate = date('Y-m-d H:i:s');
    
    // Insert operator
    $sql = sprintf("INSERT INTO %s (username, password, firstname, lastname, creationdate) VALUES ('%s', '%s', '%s', '%s', '%s')",
                   $configValues['CONFIG_DB_TBL_DALOOPERATORS'],
                   $username, $password, $firstname, $lastname, $creationdate);
    $result = $dbSocket->query($sql);
    
    if (!DB::isError($result)) {
        $operator_id = $dbSocket->getOne("SELECT LAST_INSERT_ID()");
        
        echo "<div style='background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 20px 0; border-radius: 5px;'>\n";
        echo "<h4 style='color: #155724; margin-top: 0;'>✅ Operator Created Successfully!</h4>\n";
        echo "<p style='color: #155724;'><strong>Username:</strong> $username</p>\n";
        echo "<p style='color: #155724;'><strong>Operator ID:</strong> $operator_id</p>\n";
        echo "<p style='color: #155724; margin-bottom: 0;'>Now I'll grant all permissions to this operator...</p>\n";
        echo "</div>\n";
        
        // Grant all permissions
        $sql = sprintf("SELECT file FROM %s", $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL_FILES']);
        $result = $dbSocket->query($sql);
        
        $permissions_granted = 0;
        while ($row = $result->fetchRow()) {
            $file = $row[0];
            $sql = sprintf("INSERT INTO %s (operator_id, file, access) VALUES (%d, '%s', 1)",
                          $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'],
                          $operator_id, $dbSocket->escapeSimple($file));
            $perm_result = $dbSocket->query($sql);
            if (!DB::isError($perm_result)) {
                $permissions_granted++;
            }
        }
        
        echo "<p><strong>✅ Granted $permissions_granted permissions to the new operator.</strong></p>\n";
        echo "<p><strong>You can now log in with:</strong></p>\n";
        echo "<ul><li><strong>Username:</strong> $username</li><li><strong>Password:</strong> [the password you entered]</li></ul>\n";
        
    } else {
        echo "<div style='background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 20px 0; border-radius: 5px;'>\n";
        echo "<h4 style='color: #721c24; margin-top: 0;'>❌ Error Creating Operator</h4>\n";
        echo "<p style='color: #721c24; margin-bottom: 0;'>" . $result->getMessage() . "</p>\n";
        echo "</div>\n";
    }
}

include_once('app/common/includes/db_close.php');
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
input[type="text"], input[type="password"] { padding: 5px; width: 200px; }
</style>

<p><a href="app/operators/">→ Go to operators login page</a></p>