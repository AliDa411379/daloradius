<?php
/*
 * Test form to simulate agent deletion
 */

include_once('app/common/includes/config_read.php');
include('app/common/includes/db_open.php');
include_once("app/operators/lang/main.php");
include_once("app/common/includes/validation.php");

// Get some test agents
$sql = sprintf("SELECT id, name FROM %s WHERE is_deleted = 0 LIMIT 3", $configValues['CONFIG_DB_TBL_DALOAGENTS']);
$res = $dbSocket->query($sql);

echo "<h2>Test Agent Delete Form</h2>\n";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>POST Data Received:</h3>\n";
    echo "<pre>" . print_r($_POST, true) . "</pre>\n";
    
    if (array_key_exists('agent_id', $_POST) && is_array($_POST['agent_id'])) {
        echo "<p>Agent IDs to delete: " . implode(', ', $_POST['agent_id']) . "</p>\n";
        
        // Test the actual deletion logic
        foreach ($_POST['agent_id'] as $agent_id) {
            $agent_id = intval($agent_id);
            if ($agent_id > 0) {
                $sql = sprintf("UPDATE %s SET is_deleted = 1 WHERE id = %d", 
                               $configValues['CONFIG_DB_TBL_DALOAGENTS'], $agent_id);
                $result = $dbSocket->query($sql);
                
                if (!DB::isError($result)) {
                    echo "<p>✓ Successfully marked agent ID $agent_id as deleted</p>\n";
                } else {
                    echo "<p>✗ Failed to delete agent ID $agent_id: " . $result->getMessage() . "</p>\n";
                }
            }
        }
    }
}

echo "<form method='POST' action='' name='testform'>\n";
echo "<h3>Select agents to delete:</h3>\n";

if ($res && $res->numRows() > 0) {
    while ($row = $res->fetchRow()) {
        echo "<label><input type='checkbox' name='agent_id[]' value='{$row[0]}'> {$row[1]} (ID: {$row[0]})</label><br>\n";
    }
} else {
    echo "<p>No agents found</p>\n";
}

echo "<br><input type='hidden' name='csrf_token' value='" . dalo_csrf_token() . "'>\n";
echo "<input type='submit' value='Delete Selected Agents' onclick=\"return confirm('Are you sure?')\">\n";
echo "</form>\n";

echo "<hr>\n";
echo "<h3>JavaScript Test</h3>\n";
echo "<p>Test the removeCheckbox function:</p>\n";
echo "<button onclick=\"removeCheckbox('testform', 'test_agent_delete_form.php')\">Test Delete Function</button>\n";

echo "<script>\n";
echo "function removeCheckbox(formName,pageDst) {\n";
echo "    var count = 0;\n";
echo "    var form = document.getElementsByTagName('input');\n";
echo "    for (var i=0; i < form.length; ++i) {\n";
echo "        var e = form[i];\n";
echo "        if (e.type == 'checkbox' && e.checked)\n";
echo "            ++count;\n";
echo "    }\n";
echo "    if (count == 0) {\n";
echo "        alert('No items selected');\n";
echo "        return;\n";
echo "    }\n";
echo "    if (confirm('You are about to remove ' + count + ' records from database\\nDo you want to continue?')) {\n";
echo "        document.forms[formName].action=pageDst;\n";
echo "        document.forms[formName].submit();\n";
echo "        return true;\n";
echo "    }\n";
echo "    return false;\n";
echo "}\n";
echo "</script>\n";

include('app/common/includes/db_close.php');
?>