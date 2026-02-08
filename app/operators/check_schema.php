<?php
include('library/checklogin.php');
include('../common/includes/config_read.php');
include('../common/includes/db_open.php');

$table = $configValues['CONFIG_DB_TBL_DALOBILLINGPLANS'];
$sql = "SHOW COLUMNS FROM $table";
$res = $dbSocket->query($sql);

echo "Columns in $table:\n";
while ($row = $res->fetchRow()) {
    echo $row[0] . " - " . $row[1] . "\n";
}

include('../common/includes/db_close.php');
?>
