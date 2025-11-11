<?php
/* Nodes - Delete */
include_once implode(DIRECTORY_SEPARATOR, [ __DIR__, '..', 'common', 'includes', 'config_read.php' ]);
include implode(DIRECTORY_SEPARATOR, [ $configValues['OPERATORS_LIBRARY'], 'checklogin.php' ]);
$operator = $_SESSION['operator_user'];
include implode(DIRECTORY_SEPARATOR, [ $configValues['OPERATORS_LIBRARY'], 'check_operator_perm.php' ]);
// ACL key: mng_nodes_del
include_once implode(DIRECTORY_SEPARATOR, [ $configValues['OPERATORS_LANG'], 'main.php' ]);
include implode(DIRECTORY_SEPARATOR, [ $configValues['COMMON_INCLUDES'], 'layout.php' ]);
include implode(DIRECTORY_SEPARATOR, [ $configValues['COMMON_INCLUDES'], 'db_open.php' ]);
include_once implode(DIRECTORY_SEPARATOR, [ $configValues['COMMON_INCLUDES'], 'validation.php' ]);

// init logging variables
$log = "visited page: ";
$logAction = "";
$logDebugSQL = "";

$successMsg = '';
$failureMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mac'])) {
    // CSRF validation
    if (!dalo_check_csrf_token($_POST['csrf_token'] ?? '')) {
        $failureMsg = 'CSRF token error';
        $logAction .= "$failureMsg on page: ";
    } else {
        $macs = $_POST['mac'];
        if (!is_array($macs)) { $macs = [$macs]; }
        
        $deletedCount = 0;
        $errors = [];
        
        foreach ($macs as $mac) {
            $mac = trim($mac);
            if ($mac === '') continue;
            
            $sql = 'DELETE FROM node WHERE mac=' . $dbSocket->quoteSmart($mac);
            $res = $dbSocket->query($sql);
            $logDebugSQL .= "$sql;\n";
            
            if (PEAR::isError($res)) {
                $errors[] = sprintf("Failed to delete node [%s]: %s", htmlspecialchars($mac, ENT_QUOTES, 'UTF-8'), $res->getMessage());
            } else {
                $deletedCount++;
            }
        }
        
        if ($errors) {
            $failureMsg = implode('<br>', $errors);
            $logAction .= "Failed deleting some nodes on page: ";
        }
        
        if ($deletedCount > 0) {
            $successMsg = sprintf("Successfully deleted %d node%s", $deletedCount, $deletedCount > 1 ? 's' : '');
            $logAction .= "Successfully deleted $deletedCount node(s) on page: ";
        }
        
        if (!$errors && $deletedCount === 0) {
            $failureMsg = 'No nodes were deleted';
            $logAction .= "No nodes deleted on page: ";
        }
    }
}

// If this is a GET request or we have messages to show, display the page
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !empty($successMsg) || !empty($failureMsg)) {
    $title = 'Delete Nodes';
    print_html_prologue($title, $langCode);
    print_title_and_help($title, 'Delete selected nodes.');
    
    include_once('include/management/actionMessages.php');
    
    echo '<div class="alert alert-info">';
    echo '<p>Use the <a href="mng-nodes.php">Node Management</a> page to select and delete nodes.</p>';
    echo '<p><a href="mng-nodes.php" class="btn btn-primary">Go to Node Management</a></p>';
    echo '</div>';
    
    include implode(DIRECTORY_SEPARATOR, [ $configValues['COMMON_INCLUDES'], 'db_close.php' ]);
    include implode(DIRECTORY_SEPARATOR, [ $configValues['OPERATORS_LIBRARY'], 'logging.php' ]);
    include implode(DIRECTORY_SEPARATOR, [ $configValues['COMMON_INCLUDES'], 'layout.php' ]);
} else {
    include implode(DIRECTORY_SEPARATOR, [ $configValues['COMMON_INCLUDES'], 'db_close.php' ]);
    include implode(DIRECTORY_SEPARATOR, [ $configValues['OPERATORS_LIBRARY'], 'logging.php' ]);
    header('Location: mng-nodes.php');
    exit;
}