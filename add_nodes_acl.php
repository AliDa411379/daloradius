<?php
// One-off helper: register Nodes pages in ACL files and grant access to current operator for quick testing
include_once('app/common/includes/config_read.php');
include_once('app/common/includes/db_open.php');
session_start();
$operator_id = isset($_SESSION['operator_id']) ? intval($_SESSION['operator_id']) : 0;

$files = [ 'mng_nodes', 'mng_nodes_list', 'mng_nodes_new', 'mng_nodes_edit', 'mng_nodes_del' ];

foreach ($files as $f) {
    // Add ACL file entry if missing
    $sql = sprintf("SELECT COUNT(*) FROM %s WHERE file='%s'",
                   $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL_FILES'], $dbSocket->escapeSimple($f));
    $exists = intval($dbSocket->getOne($sql));
    if ($exists === 0) {
        $sql = sprintf("INSERT INTO %s (file, category, section) VALUES ('%s', 'Management', 'Nodes')",
                       $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL_FILES'], $dbSocket->escapeSimple($f));
        $dbSocket->query($sql);
    }

    if ($operator_id > 0) {
        // Grant permission to current operator if missing
        $sql = sprintf("SELECT COUNT(*) FROM %s WHERE operator_id=%d AND file='%s'",
                       $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'], $operator_id, $dbSocket->escapeSimple($f));
        $has = intval($dbSocket->getOne($sql));
        if ($has === 0) {
            $sql = sprintf("INSERT INTO %s (operator_id, file, access) VALUES (%d, '%s', 1)",
                           $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'], $operator_id, $dbSocket->escapeSimple($f));
            $dbSocket->query($sql);
        }
    }
}

echo "Nodes ACL entries ensured. You can remove this file after seeding.";
include_once('app/common/includes/db_close.php');