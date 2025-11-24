<?php
include_once('app/common/includes/config_read.php');
include_once('app/common/includes/db_open.php');

echo "=== Testing Agent-User Relationship ===\n\n";

// Check if agents table has operator_id
echo "1. Checking agents table structure:\n";
$sql = "DESCRIBE agents";
$res = $dbSocket->query($sql);
if ($res) {
    while ($row = $res->fetchRow()) {
        echo "   Field: " . $row[0] . " Type: " . $row[1] . "\n";
    }
} else {
    echo "   Error: " . $res->getMessage() . "\n";
}

// Check for mapping table
echo "\n2. Looking for agent_user_mapping table:\n";
$sql = "SHOW TABLES LIKE '%agent%user%'";
$res = $dbSocket->query($sql);
$mapping_exists = false;
if ($res && $res->numRows() > 0) {
    $mapping_exists = true;
    while ($row = $res->fetchRow()) {
        echo "   Found: " . $row[0] . "\n";
    }
} else {
    echo "   No mapping table found\n";
}

// List sample agents
echo "\n3. Sample agents:\n";
$sql = "SELECT id, name FROM agents WHERE is_deleted = 0 LIMIT 5";
$res = $dbSocket->query($sql);
if ($res) {
    while ($row = $res->fetchRow()) {
        $agent_id = $row[0];
        $agent_name = $row[1];
        echo "   Agent ID: $agent_id, Name: $agent_name\n";
        
        // Try to find users for this agent using creationby
        $sql_users = "SELECT COUNT(*) FROM userinfo WHERE creationby = (SELECT username FROM operators WHERE id IN (SELECT operator_id FROM agents WHERE id = $agent_id))";
        $res_users = $dbSocket->query($sql_users);
        if ($res_users) {
            $user_row = $res_users->fetchRow();
            echo "      Users (via creationby/operator_id): " . $user_row[0] . "\n";
        }
    }
}

include_once('app/common/includes/db_close.php');
?>
