<?php
// radius_diagnostic.php
include_once('app/common/includes/config_read.php');
include_once('app/common/includes/db_open.php');

echo "--- RADIUS Broad Diagnostic ---\n";

// 1. Check ALL radcheck for anything related to expiration or 300
$sql = "SELECT username, attribute, value FROM radcheck WHERE attribute = 'Expiration' OR value LIKE '%300%' OR value LIKE '%5%'";
$res = $dbSocket->query($sql);
echo "Records in radcheck (Expiration or potential 300/5):\n";
if ($res) {
    while ($row = $res->fetchRow()) {
        echo "User: {$row[0]} | Attribute: {$row[1]} | Value: {$row[2]}\n";
    }
}

// 2. Check for very small balances (likely divided by 100)
$sql = "SELECT username, timebank_balance, traffic_balance, money_balance FROM userbillinfo WHERE (timebank_balance > 0 AND timebank_balance < 10) OR (traffic_balance > 0 AND traffic_balance < 10) OR (money_balance > 0 AND money_balance < 1)";
$res = $dbSocket->query($sql);
echo "\nUsers with very low balances (potential division side-effect):\n";
if ($res) {
    while ($row = $res->fetchRow()) {
        echo "User: {$row[0]} | Time: {$row[1]} | Traffic: {$row[2]} | Money: {$row[3]}\n";
    }
}

// 3. Check for any attribute set to 300 seconds (5 mins) across radreply and radgroupreply
$sql = "SELECT 'Reply' as Tab, username as Name, attribute, value FROM radreply WHERE value = '300' 
        UNION 
        SELECT 'GroupReply', groupname, attribute, value FROM radgroupreply WHERE value = '300'";
$res = $dbSocket->query($sql);
echo "\nAny Reply/GroupReply attribute set to 300:\n";
if ($res) {
    while ($row = $res->fetchRow()) {
        echo "{$row[0]} | Name: {$row[1]} | Attr: {$row[2]} | Val: {$row[3]}\n";
    }
}

include_once('app/common/includes/db_close.php');
?>