<?php
/**
 * Fix Duplicate Agent Names Issue
 * 
 * This script addresses the problem of multiple agents having the same name
 * by implementing several solutions:
 * 1. Identify duplicate agent names
 * 2. Suggest renaming strategies
 * 3. Add unique constraint to prevent future duplicates
 */

include_once('app/common/includes/config_read.php');
include_once('app/common/includes/db_open.php');

echo "<h2>Fix Duplicate Agent Names</h2>\n";

// Step 1: Identify duplicate agent names
echo "<h3>Step 1: Identifying Duplicate Agent Names</h3>\n";

$sql = sprintf("SELECT name, COUNT(*) as count, GROUP_CONCAT(id ORDER BY id) as agent_ids 
                FROM %s 
                WHERE is_deleted = 0 
                GROUP BY name 
                HAVING COUNT(*) > 1 
                ORDER BY count DESC, name", 
               $configValues['CONFIG_DB_TBL_DALOAGENTS']);

$result = $dbSocket->query($sql);

if ($result && $result->numRows() > 0) {
    echo "<p><strong>⚠️ Found duplicate agent names:</strong></p>\n";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr style='background-color: #f2f2f2;'><th>Agent Name</th><th>Count</th><th>Agent IDs</th><th>Details</th></tr>\n";
    
    $duplicates = array();
    while ($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
        $name = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
        $count = $row['count'];
        $agent_ids = $row['agent_ids'];
        
        echo "<tr>";
        echo "<td><strong>$name</strong></td>";
        echo "<td>$count</td>";
        echo "<td>$agent_ids</td>";
        
        // Get details for each duplicate
        $details_sql = sprintf("SELECT id, company, email, phone FROM %s WHERE name = '%s' AND is_deleted = 0 ORDER BY id", 
                              $configValues['CONFIG_DB_TBL_DALOAGENTS'], 
                              $dbSocket->escapeSimple($row['name']));
        $details_result = $dbSocket->query($details_sql);
        
        $details = "<ul style='margin: 0; padding-left: 20px;'>";
        while ($detail_row = $details_result->fetchRow(DB_FETCHMODE_ASSOC)) {
            $details .= sprintf("<li>ID %d: %s (%s) - %s</li>", 
                               $detail_row['id'],
                               htmlspecialchars($detail_row['company'], ENT_QUOTES, 'UTF-8'),
                               htmlspecialchars($detail_row['email'], ENT_QUOTES, 'UTF-8'),
                               htmlspecialchars($detail_row['phone'], ENT_QUOTES, 'UTF-8'));
        }
        $details .= "</ul>";
        
        echo "<td>$details</td>";
        echo "</tr>\n";
        
        $duplicates[] = $row;
    }
    echo "</table>\n";
    
    // Step 2: Suggest automatic renaming
    echo "<h3>Step 2: Suggested Automatic Renaming</h3>\n";
    echo "<p>Here are suggested unique names for the duplicate agents:</p>\n";
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr style='background-color: #f2f2f2;'><th>Current Name</th><th>Agent ID</th><th>Suggested New Name</th><th>Reason</th></tr>\n";
    
    foreach ($duplicates as $duplicate) {
        $name = $duplicate['name'];
        $agent_ids = explode(',', $duplicate['agent_ids']);
        
        // Get details for renaming suggestions
        $details_sql = sprintf("SELECT id, company, email, phone FROM %s WHERE name = '%s' AND is_deleted = 0 ORDER BY id", 
                              $configValues['CONFIG_DB_TBL_DALOAGENTS'], 
                              $dbSocket->escapeSimple($name));
        $details_result = $dbSocket->query($details_sql);
        
        $counter = 1;
        while ($detail_row = $details_result->fetchRow(DB_FETCHMODE_ASSOC)) {
            $current_name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            $agent_id = $detail_row['id'];
            $company = $detail_row['company'];
            $email = $detail_row['email'];
            
            // Generate suggested name
            if ($counter == 1) {
                // Keep the first one as is, or suggest company-based name
                if (!empty($company)) {
                    $suggested_name = $name . " (" . $company . ")";
                    $reason = "Added company name for clarity";
                } else {
                    $suggested_name = $name . " (Agent #" . $agent_id . ")";
                    $reason = "Added agent ID for uniqueness";
                }
            } else {
                if (!empty($company)) {
                    $suggested_name = $name . " (" . $company . ")";
                    $reason = "Added company name for clarity";
                } else if (!empty($email)) {
                    $email_prefix = explode('@', $email)[0];
                    $suggested_name = $name . " (" . $email_prefix . ")";
                    $reason = "Added email prefix for uniqueness";
                } else {
                    $suggested_name = $name . " (Agent #" . $agent_id . ")";
                    $reason = "Added agent ID for uniqueness";
                }
            }
            
            echo "<tr>";
            echo "<td>$current_name</td>";
            echo "<td>$agent_id</td>";
            echo "<td><strong>" . htmlspecialchars($suggested_name, ENT_QUOTES, 'UTF-8') . "</strong></td>";
            echo "<td>$reason</td>";
            echo "</tr>\n";
            
            $counter++;
        }
    }
    echo "</table>\n";
    
    // Step 3: Provide action buttons
    echo "<h3>Step 3: Actions</h3>\n";
    echo "<div style='margin: 20px 0;'>\n";
    echo "<button onclick=\"if(confirm('This will automatically rename duplicate agents. Continue?')) { window.location.href='fix_duplicate_agent_names.php?action=auto_rename'; }\" style='background-color: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin-right: 10px;'>Auto-Rename Duplicates</button>\n";
    echo "<button onclick=\"if(confirm('This will add a unique constraint to prevent future duplicates. Continue?')) { window.location.href='fix_duplicate_agent_names.php?action=add_constraint'; }\" style='background-color: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;'>Add Unique Constraint</button>\n";
    echo "</div>\n";
    
} else {
    echo "<p><strong>✅ No duplicate agent names found!</strong></p>\n";
    
    // Check if unique constraint exists
    echo "<h3>Database Schema Check</h3>\n";
    $sql = sprintf("SHOW INDEX FROM %s WHERE Column_name = 'name'", $configValues['CONFIG_DB_TBL_DALOAGENTS']);
    $result = $dbSocket->query($sql);
    
    if ($result && $result->numRows() > 0) {
        echo "<p><strong>✅ Unique constraint on 'name' column exists</strong></p>\n";
    } else {
        echo "<p><strong>⚠️ No unique constraint on 'name' column</strong></p>\n";
        echo "<button onclick=\"if(confirm('This will add a unique constraint to prevent future duplicates. Continue?')) { window.location.href='fix_duplicate_agent_names.php?action=add_constraint'; }\" style='background-color: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;'>Add Unique Constraint</button>\n";
    }
}

// Handle actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action === 'auto_rename') {
        echo "<h3>Auto-Renaming Duplicate Agents</h3>\n";
        
        // Get duplicates again
        $sql = sprintf("SELECT name, GROUP_CONCAT(id ORDER BY id) as agent_ids 
                        FROM %s 
                        WHERE is_deleted = 0 
                        GROUP BY name 
                        HAVING COUNT(*) > 1", 
                       $configValues['CONFIG_DB_TBL_DALOAGENTS']);
        $result = $dbSocket->query($sql);
        
        $renamed_count = 0;
        while ($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
            $name = $row['name'];
            $agent_ids = explode(',', $row['agent_ids']);
            
            // Skip the first agent (keep original name)
            for ($i = 1; $i < count($agent_ids); $i++) {
                $agent_id = intval($agent_ids[$i]);
                
                // Get agent details
                $detail_sql = sprintf("SELECT company, email FROM %s WHERE id = %d", 
                                     $configValues['CONFIG_DB_TBL_DALOAGENTS'], $agent_id);
                $detail_result = $dbSocket->query($detail_sql);
                $detail_row = $detail_result->fetchRow(DB_FETCHMODE_ASSOC);
                
                $company = $detail_row['company'];
                $email = $detail_row['email'];
                
                // Generate new name
                if (!empty($company)) {
                    $new_name = $name . " (" . $company . ")";
                } else if (!empty($email)) {
                    $email_prefix = explode('@', $email)[0];
                    $new_name = $name . " (" . $email_prefix . ")";
                } else {
                    $new_name = $name . " (Agent #" . $agent_id . ")";
                }
                
                // Update the agent name
                $update_sql = sprintf("UPDATE %s SET name = '%s' WHERE id = %d", 
                                     $configValues['CONFIG_DB_TBL_DALOAGENTS'],
                                     $dbSocket->escapeSimple($new_name),
                                     $agent_id);
                $update_result = $dbSocket->query($update_sql);
                
                if (!DB::isError($update_result)) {
                    echo "<p>✅ Renamed agent ID $agent_id from '" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "' to '" . htmlspecialchars($new_name, ENT_QUOTES, 'UTF-8') . "'</p>\n";
                    $renamed_count++;
                } else {
                    echo "<p>❌ Failed to rename agent ID $agent_id: " . $update_result->getMessage() . "</p>\n";
                }
            }
        }
        
        echo "<p><strong>Renamed $renamed_count agents successfully!</strong></p>\n";
        echo "<p><a href='fix_duplicate_agent_names.php'>Refresh to see updated results</a></p>\n";
        
    } elseif ($action === 'add_constraint') {
        echo "<h3>Adding Unique Constraint</h3>\n";
        
        // First check if there are any duplicates
        $sql = sprintf("SELECT COUNT(*) FROM (SELECT name FROM %s WHERE is_deleted = 0 GROUP BY name HAVING COUNT(*) > 1) as duplicates", 
                       $configValues['CONFIG_DB_TBL_DALOAGENTS']);
        $result = $dbSocket->query($sql);
        $duplicate_count = $result->fetchRow()[0];
        
        if ($duplicate_count > 0) {
            echo "<p><strong>❌ Cannot add unique constraint: $duplicate_count duplicate name(s) still exist!</strong></p>\n";
            echo "<p>Please rename the duplicate agents first using the 'Auto-Rename Duplicates' button.</p>\n";
        } else {
            // Add unique constraint
            $sql = sprintf("ALTER TABLE %s ADD UNIQUE KEY unique_agent_name (name)", 
                          $configValues['CONFIG_DB_TBL_DALOAGENTS']);
            $result = $dbSocket->query($sql);
            
            if (!DB::isError($result)) {
                echo "<p><strong>✅ Successfully added unique constraint to agent names!</strong></p>\n";
                echo "<p>Future attempts to create agents with duplicate names will be prevented.</p>\n";
            } else {
                echo "<p><strong>❌ Failed to add unique constraint:</strong> " . $result->getMessage() . "</p>\n";
            }
        }
        
        echo "<p><a href='fix_duplicate_agent_names.php'>Refresh to see updated results</a></p>\n";
    }
}

include_once('app/common/includes/db_close.php');
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
button:hover { opacity: 0.8; }
</style>