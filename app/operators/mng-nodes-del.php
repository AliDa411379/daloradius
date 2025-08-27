<?php
/* Nodes - Delete */
include_once implode(DIRECTORY_SEPARATOR, [ __DIR__, '..', 'common', 'includes', 'config_read.php' ]);
include implode(DIRECTORY_SEPARATOR, [ $configValues['OPERATORS_LIBRARY'], 'checklogin.php' ]);
$operator = $_SESSION['operator_user'];
include implode(DIRECTORY_SEPARATOR, [ $configValues['OPERATORS_LIBRARY'], 'check_operator_perm.php' ]);
// ACL key: mng_nodes_del
include implode(DIRECTORY_SEPARATOR, [ $configValues['COMMON_INCLUDES'], 'db_open.php' ]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mac'])) {
    $macs = $_POST['mac'];
    if (!is_array($macs)) { $macs = [$macs]; }
    foreach ($macs as $mac) {
        $mac = trim($mac);
        if ($mac === '') continue;
        $sql = 'DELETE FROM node WHERE mac=' . $dbSocket->quoteSmart($mac);
        $dbSocket->query($sql);
    }
}

include implode(DIRECTORY_SEPARATOR, [ $configValues['COMMON_INCLUDES'], 'db_close.php' ]);
header('Location: mng-nodes.php');
exit;