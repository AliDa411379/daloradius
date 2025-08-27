<?php
/* Sidebar: Node Management */
if (strpos($_SERVER['PHP_SELF'], '/include/menu/sidebar/mng/nodes.php') !== false) {
    header('Location: ../../../../index.php');
    exit;
}

$m_active = 'Management';
include_once('include/menu/sidebar/mng/default.php');

$descriptors1 = array();

$descriptors1[] = array(
    'type' => 'link',
    'label' => 'New Node',
    'href'  => 'mng-nodes-new.php',
    'icon'  => 'add'
);

if (isset($configValues['CONFIG_IFACE_TABLES_LISTING']) && $configValues['CONFIG_IFACE_TABLES_LISTING'] == 'yes') {
    $descriptors1[] = array(
        'type' => 'link',
        'label' => 'List Nodes',
        'href'  => 'mng-nodes-list.php',
        'icon'  => 'list'
    );
}

$descriptors1[] = array(
    'type' => 'form',
    'title' => 'Edit Node',
    'action' => 'mng-nodes-edit.php',
    'method' => 'GET',
    'inputs' => array(
        'mac' => array('type' => 'text', 'size' => '17', 'placeholder' => 'MAC (e.g. AA:BB:CC:DD:EE:FF)')
    )
);

$descriptors1[] = array(
    'type' => 'link',
    'label' => 'Remove Nodes',
    'href'  => 'mng-nodes-del.php',
    'icon'  => 'delete'
);

$sections = array();
$sections[] = array('title' => 'Node Management', 'descriptors' => $descriptors1);

$menu = array(
    'title' => 'Management',
    'sections' => $sections,
);