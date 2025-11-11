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
// no list on this landing page; keep layout minimal like other mng pages

// init logging variables
$log = "visited page: ";
$logQuery = "performed query on page: ";
$logDebugSQL = "";

// prologue (align with standard minimal mng pages)
$title = 'Nodes';
$help = '';
print_html_prologue($title, $langCode);
print_title_and_help($title, $help);
// Minimal landing: no buttons/controls per request

include implode(DIRECTORY_SEPARATOR, [ $configValues['OPERATORS_INCLUDE_CONFIG'], 'logging.php' ]);
print_footer_and_html_epilogue();