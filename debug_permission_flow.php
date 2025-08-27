<?php
/**
 * Debug the complete permission flow
 */

session_start();
include_once('app/common/includes/config_read.php');
include_once('app/common/includes/db_open.php');

echo "<h2>Complete Permission Flow Debug</h2>\n";

// Step 1: Check session
echo "<h3>Step 1: Session Check</h3>\n";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>\n";
echo "<p><strong>Logged in:</strong> " . (isset($_SESSION['daloradius_logged_in']) ? ($_SESSION['daloradius_logged_in'] ? 'Yes' : 'No') : 'Not set') . "</p>\n";
echo "<p><strong>Operator user:</strong> " . (isset($_SESSION['operator_user']) ? $_SESSION['operator_user'] : 'Not set') . "</p>\n";
echo "<p><strong>Operator ID:</strong> " . (isset($_SESSION['operator_id']) ? $_SESSION['operator_id'] : 'Not set') . "</p>\n";

if (!isset($_SESSION['operator_id'])) {
    echo "<div style='background-color: #f8d7da; padding: 15px; margin: 10px 0; border-radius: 5px;'>\n";
    echo "<h4 style='color: #721c24;'>❌ Critical Issue Found!</h4>\n";
    echo "<p style='color: #721c24;'>operator_id is not set in session. This is why permission checks are failing.</p>\n";
    echo "</div>\n";
} else {
    $operator_id = $_SESSION['operator_id'];
    
    // Step 2: Check operator exists in database
    echo "<h3>Step 2: Operator Database Check</h3>\n";
    $sql = sprintf("SELECT * FROM %s WHERE id=%d", $configValues['CONFIG_DB_TBL_DALOOPERATORS'], $operator_id);
    $result = $dbSocket->query($sql);
    
    if ($result && $result->numRows() > 0) {
        $operator = $result->fetchRow(DB_FETCHMODE_ASSOC);
        echo "<p>✅ Operator found in database:</p>\n";
        echo "<ul>\n";
        echo "<li><strong>ID:</strong> " . $operator['id'] . "</li>\n";
        echo "<li><strong>Username:</strong> " . $operator['username'] . "</li>\n";
        echo "<li><strong>First Name:</strong> " . $operator['firstname'] . "</li>\n";
        echo "<li><strong>Last Name:</strong> " . $operator['lastname'] . "</li>\n";
        echo "<li><strong>Is Agent:</strong> " . (isset($operator['is_agent']) ? ($operator['is_agent'] ? 'Yes' : 'No') : 'Not set') . "</li>\n";
        echo "</ul>\n";
        
        // Step 3: Simulate the permission check
        echo "<h3>Step 3: Permission Check Simulation</h3>\n";
        
        $test_file = "mng-agent-new.php";
        $converted_file = str_replace("-", "_", basename($test_file, ".php"));
        
        echo "<p><strong>Testing file:</strong> $test_file</p>\n";
        echo "<p><strong>Converted to:</strong> $converted_file</p>\n";
        
        // Check if ACL file entry exists
        $sql = sprintf("SELECT * FROM %s WHERE file='%s'", 
                       $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL_FILES'], 
                       $dbSocket->escapeSimple($converted_file));
        $result = $dbSocket->query($sql);
        
        if ($result && $result->numRows() > 0) {
            $acl_file = $result->fetchRow(DB_FETCHMODE_ASSOC);
            echo "<p>✅ ACL file entry exists:</p>\n";
            echo "<ul>\n";
            echo "<li><strong>ID:</strong> " . $acl_file['id'] . "</li>\n";
            echo "<li><strong>File:</strong> " . $acl_file['file'] . "</li>\n";
            echo "<li><strong>Category:</strong> " . $acl_file['category'] . "</li>\n";
            echo "<li><strong>Section:</strong> " . $acl_file['section'] . "</li>\n";
            echo "</ul>\n";
            
            // Check operator permission
            $sql = sprintf("SELECT access FROM %s WHERE operator_id=%d AND file='%s'",
                           $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'], 
                           $operator_id, 
                           $dbSocket->escapeSimple($converted_file));
            $result = $dbSocket->query($sql);
            
            if ($result && $result->numRows() > 0) {
                $access = $result->fetchRow()[0];
                if ($access == 1) {
                    echo "<p>✅ Permission GRANTED for this operator</p>\n";
                } else {
                    echo "<p>❌ Permission DENIED for this operator (access = $access)</p>\n";
                }
            } else {
                echo "<p>❌ No permission entry found for this operator</p>\n";
                
                // Let's add it now
                echo "<h4>Adding missing permission...</h4>\n";
                $sql = sprintf("INSERT INTO %s (operator_id, file, access) VALUES (%d, '%s', 1)",
                              $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'],
                              $operator_id,
                              $dbSocket->escapeSimple($converted_file));
                $result = $dbSocket->query($sql);
                
                if (!DB::isError($result)) {
                    echo "<p>✅ Permission added successfully!</p>\n";
                } else {
                    echo "<p>❌ Error adding permission: " . $result->getMessage() . "</p>\n";
                }
            }
            
        } else {
            echo "<p>❌ ACL file entry does NOT exist for: $converted_file</p>\n";
            
            // Add the ACL file entry
            echo "<h4>Adding missing ACL file entry...</h4>\n";
            $sql = sprintf("INSERT INTO %s (file, category, section) VALUES ('%s', 'Management', 'Agents')",
                          $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL_FILES'],
                          $dbSocket->escapeSimple($converted_file));
            $result = $dbSocket->query($sql);
            
            if (!DB::isError($result)) {
                echo "<p>✅ ACL file entry added successfully!</p>\n";
                
                // Now add the permission
                $sql = sprintf("INSERT INTO %s (operator_id, file, access) VALUES (%d, '%s', 1)",
                              $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'],
                              $operator_id,
                              $dbSocket->escapeSimple($converted_file));
                $result = $dbSocket->query($sql);
                
                if (!DB::isError($result)) {
                    echo "<p>✅ Permission added successfully!</p>\n";
                } else {
                    echo "<p>❌ Error adding permission: " . $result->getMessage() . "</p>\n";
                }
            } else {
                echo "<p>❌ Error adding ACL file entry: " . $result->getMessage() . "</p>\n";
            }
        }
        
    } else {
        echo "<p>❌ Operator not found in database with ID: $operator_id</p>\n";
    }
}

// Step 4: Check all current permissions for this operator
if (isset($_SESSION['operator_id'])) {
    echo "<h3>Step 4: All Current Permissions for This Operator</h3>\n";
    
    $sql = sprintf("SELECT acl.file, acl.access, files.category, files.section 
                    FROM %s acl 
                    LEFT JOIN %s files ON acl.file = files.file 
                    WHERE acl.operator_id=%d 
                    ORDER BY acl.file",
                   $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'],
                   $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL_FILES'],
                   $_SESSION['operator_id']);
    $result = $dbSocket->query($sql);
    
    if ($result && $result->numRows() > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr style='background-color: #f2f2f2;'><th>File</th><th>Access</th><th>Category</th><th>Section</th></tr>\n";
        
        $granted_count = 0;
        $denied_count = 0;
        
        while ($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
            $access_text = $row['access'] ? 'GRANTED' : 'DENIED';
            $color = $row['access'] ? '#d4edda' : '#f8d7da';
            
            if ($row['access']) $granted_count++;
            else $denied_count++;
            
            echo "<tr style='background-color: $color;'><td>" . $row['file'] . "</td><td>" . $access_text . "</td><td>" . $row['category'] . "</td><td>" . $row['section'] . "</td></tr>\n";
        }
        echo "</table>\n";
        
        echo "<p><strong>Summary:</strong> $granted_count granted, $denied_count denied</p>\n";
    } else {
        echo "<p>❌ No permissions found for this operator!</p>\n";
    }
}

include_once('app/common/includes/db_close.php');
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
</style>

<div style='background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0; border-radius: 5px;'>
<h4 style='color: #856404; margin-top: 0;'>🔧 After running this debug</h4>
<p style='color: #856404; margin-bottom: 0;'>
<a href="app/operators/mng-agent-new.php" target="_blank" style="color: #856404; font-weight: bold;">→ Try accessing the agent creation page again</a>
</p>
</div>