<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Nodes Management - List and actions
 *********************************************************************************************************
*/

include_once implode(DIRECTORY_SEPARATOR, [ __DIR__, '..', 'common', 'includes', 'config_read.php' ]);
include implode(DIRECTORY_SEPARATOR, [ $configValues['OPERATORS_LIBRARY'], 'checklogin.php' ]);
$operator = $_SESSION['operator_user'];

include implode(DIRECTORY_SEPARATOR, [ $configValues['OPERATORS_LIBRARY'], 'check_operator_perm.php' ]);
// For ACL to match, expected file key is mng_nodes (dash->underscore)
include_once implode(DIRECTORY_SEPARATOR, [ $configValues['OPERATORS_LANG'], 'main.php' ]);
include implode(DIRECTORY_SEPARATOR, [ $configValues['COMMON_INCLUDES'], 'layout.php' ]);
include_once implode(DIRECTORY_SEPARATOR, [ $configValues['OPERATORS_INCLUDE_MANAGEMENT'], 'functions.php' ]);
include implode(DIRECTORY_SEPARATOR, [ $configValues['COMMON_INCLUDES'], 'db_open.php' ]);

// init logging variables
$log = "visited page: ";
$logQuery = "performed query on page: ";
$logDebugSQL = "";

// set session's page variable
$_SESSION['PREV_LIST_PAGE'] = $_SERVER['REQUEST_URI'];

$cols = [
    'mac'   => 'MAC',
    'name'  => 'Name',
    'ip'    => 'IP',
    'latitude' => 'Latitude',
    'longitude' => 'Longitude',
    'uptime'=> 'Uptime',
    'users' => 'Users',
    'cpu'   => 'CPU %',
    'time'  => 'Last Seen',
];
$colspan = count($cols);

// whitelist ordering
$param_cols = [];
foreach ($cols as $k => $v) { if (!is_int($k)) { $param_cols[$k] = $v; } }
$orderBy = (array_key_exists('orderBy', $_GET) && isset($_GET['orderBy']) && in_array($_GET['orderBy'], array_keys($param_cols)))
         ? $_GET['orderBy'] : 'time';
$orderType = (array_key_exists('orderType', $_GET) && isset($_GET['orderType']) && in_array(strtolower($_GET['orderType']), [ 'asc', 'desc' ]))
           ? strtolower($_GET['orderType']) : 'desc';

// prologue
$title = 'Nodes';
$help = '<p>List of monitored nodes. You can create, edit or delete nodes. MAC is the identifier.</p>';
print_html_prologue($title, $langCode);
print_title_and_help($title, $help);

include implode(DIRECTORY_SEPARATOR, [ $configValues['OPERATORS_INCLUDE_MANAGEMENT'], 'pages_common.php' ]);

// counting rows
$sql = "SELECT COUNT(*) FROM node";
$numrows = get_numrows($dbSocket, $sql);

if ($numrows > 0) {
    include implode(DIRECTORY_SEPARATOR, [ $configValues['OPERATORS_INCLUDE_MANAGEMENT'], 'pages_numbering.php' ]);
    $drawNumberLinks = strtolower($configValues['CONFIG_IFACE_TABLES_LISTING_NUM']) == "yes" && $maxPage > 1;

    $sql = sprintf("SELECT mac, name, ip, latitude, longitude, uptime, users, cpu, time FROM node ORDER BY %s %s LIMIT %s, %s",
                    $orderBy, $orderType, $offset, $rowsPerPage);
    $res = $dbSocket->query($sql);
    $logDebugSQL = "$sql;\n";
    $per_page_numrows = $res->numRows();

    // Controls bar
    $action = 'mng-nodes-del.php';
    $form_name = 'form_' . rand();

    $additional_controls = [];
    $additional_controls[] = [ 'href' => 'mng-nodes-new.php', 'label' => 'New Node', 'class' => 'btn-success' ];
    $additional_controls[] = [ 'onclick' => sprintf("removeCheckbox('%s','%s')", $form_name, $action), 'label' => 'Delete', 'class' => 'btn-danger' ];

    $descriptors = [];
    $descriptors['start'] = [ 'common_controls' => 'mac[]', 'additional_controls' => $additional_controls ];

    $params = [ 'num_rows' => $numrows, 'rows_per_page' => $rowsPerPage, 'page_num' => $pageNum, 'order_by' => $orderBy, 'order_type' => $orderType ];
    $descriptors['center'] = [ 'draw' => $drawNumberLinks, 'params' => $params ];

    print_table_prologue($descriptors);

    $form_descriptor = [ 'form' => [ 'action' => $action, 'method' => 'POST', 'name' => $form_name ] ];
    print_table_top($form_descriptor);
    printTableHead($cols, $orderBy, $orderType);
    print_table_middle();

    while ($row = $res->fetchRow()) {
        list($mac, $name, $ip, $latitude, $longitude, $uptime, $users, $cpu, $time) = $row;
        $mac   = htmlspecialchars($mac, ENT_QUOTES, 'UTF-8');
        $name  = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $ip    = htmlspecialchars($ip, ENT_QUOTES, 'UTF-8');
        $latitude = htmlspecialchars($latitude, ENT_QUOTES, 'UTF-8');
        $longitude = htmlspecialchars($longitude, ENT_QUOTES, 'UTF-8');
        $uptime= htmlspecialchars($uptime, ENT_QUOTES, 'UTF-8');
        $users = htmlspecialchars($users, ENT_QUOTES, 'UTF-8');
        $cpu   = htmlspecialchars($cpu, ENT_QUOTES, 'UTF-8');
        $time  = htmlspecialchars($time, ENT_QUOTES, 'UTF-8');

        $checkbox = get_checkbox_str([ 'name' => 'mac[]', 'value' => $mac, 'label' => '' ]);
        $name_link = sprintf('<a href="%s">%s</a>', 'mng-nodes-edit.php?mac=' . urlencode($mac), $name ?: $mac);

        print_table_row([ $checkbox, $name_link, $ip, $latitude, $longitude, $uptime, $users, $cpu, $time ]);
    }

    $table_foot = [ 'num_rows' => $numrows, 'rows_per_page' => $per_page_numrows, 'colspan' => $colspan, 'multiple_pages' => $drawNumberLinks ];
    $descriptor = [ 'form' => $form_descriptor, 'table_foot' => $table_foot ];
    print_table_bottom($descriptor);

    $links = setupLinks_str($pageNum, $maxPage, $orderBy, $orderType);
    printLinks($links, $drawNumberLinks);

} else {
    echo '<div class="alert alert-info">No nodes found. <a class="btn btn-sm btn-success ms-2" href="mng-nodes-new.php">New Node</a></div>';
}

include implode(DIRECTORY_SEPARATOR, [ $configValues['COMMON_INCLUDES'], 'db_close.php' ]);
include implode(DIRECTORY_SEPARATOR, [ $configValues['OPERATORS_INCLUDE_CONFIG'], 'logging.php' ]);
print_footer_and_html_epilogue();