<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Copyright (C) 2007 - Liran Tal <liran@lirantal.com> All Rights Reserved.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *********************************************************************************************************
 *
 * Description:    Bundle List - View bundle purchase history
 *
 *********************************************************************************************************
 */

// TEMPORARY: Enable error display for debugging (hide deprecation warnings)
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 1);

    include("library/checklogin.php");
    $operator = $_SESSION['operator_user'];

    include('library/check_operator_perm.php');
    include_once('../common/includes/config_read.php');
    include_once("lang/main.php");
    include("../common/includes/layout.php");

    // init logging variables
    $log = "visited page: ";
    $logQuery = "performed query on page: ";
    $logDebugSQL = "";

    // set session's page variable
    $_SESSION['PREV_LIST_PAGE'] = $_SERVER['REQUEST_URI'];

    $cols = array(
                    "id" => "ID",
                    "username" => "Username",
                    "bundle_name" => "Bundle Plan",
                    "purchase_date" => "Purchase Date",
                    "activation_date" => "Activation Date", 
                    "expiry_date" => "Expiry Date",
                    "status" => "Status",
                    "cost" => "Cost"
                 );
    $colspan = count($cols);
    $half_colspan = intval($colspan / 2);
                 
    $param_cols = array();
    foreach ($cols as $k => $v) { if (!is_int($k)) { $param_cols[$k] = $v; } }
    
    // whenever possible we use a whitelist approach
    $orderBy = (array_key_exists('orderBy', $_GET) && isset($_GET['orderBy']) &&
                in_array($_GET['orderBy'], array_keys($param_cols)))
             ? $_GET['orderBy'] : 'id';

    $orderType = (array_key_exists('orderType', $_GET) && isset($_GET['orderType']) &&
                  in_array(strtolower($_GET['orderType']), array( "desc", "asc" )))
               ? strtolower($_GET['orderType']) : "desc";

    $username = (array_key_exists('username', $_GET) && isset($_GET['username']))
              ? str_replace('%', '', $_GET['username']) : "";
    $username_enc = (!empty($username)) ? htmlspecialchars($username, ENT_QUOTES, 'UTF-8') : "";
    
    $status_filter = (array_key_exists('status', $_GET) && isset($_GET['status']) &&
                      in_array($_GET['status'], array('active', 'expired', 'used')))
                   ? $_GET['status'] : "";
    
    // print HTML prologue    
    $title = "Bundle List";
    $help = "View all bundle purchases and their status";
    
    print_html_prologue($title, $langCode);
    
    // start printing content
    print_title_and_help($title, $help);
    
    include('../common/includes/db_open.php');
    include('include/management/pages_common.php');

    $sql_WHERE = array();
    
    // Filter by username if provided
    if (!empty($username)) {
        $sql_WHERE[] = sprintf("ub.username = '%s'", $dbSocket->escapeSimple($username));
    }
    
    // Filter by status if provided
    if (!empty($status_filter)) {
        $sql_WHERE[] = sprintf("ub.status = '%s'", $dbSocket->escapeSimple($status_filter));
    }
    
    $sql = "SELECT ub.id, ub.username, ub.plan_name, ub.purchase_date, ub.activation_date, 
                   ub.expiry_date, ub.status, ub.purchase_amount
              FROM user_bundles AS ub";
    
    if (count($sql_WHERE) > 0) {
        $sql .= " WHERE " . implode(" AND ", $sql_WHERE);
    }
    
    $res = $dbSocket->query($sql);
    $numrows = $res->numRows();

    if ($numrows > 0) {
        /* START - Related to pages_numbering.php */
        
        // when $numrows is set, $maxPage is calculated inside this include file
        include('include/management/pages_numbering.php');    // must be included after opendb because it needs to read
                                                              // the CONFIG_IFACE_TABLES_LISTING variable from the config file
        
        // here we decide if page numbers should be shown
        $drawNumberLinks = strtolower($configValues['CONFIG_IFACE_TABLES_LISTING_NUM']) == "yes" && $maxPage > 1;
        
        /* END */
        
        // we execute and log the actual query
        $sql .= sprintf(" ORDER BY %s %s LIMIT %s, %s", $orderBy, $orderType, $offset, $rowsPerPage);
        $res = $dbSocket->query($sql);
        $logDebugSQL .= "$sql;\n";
        
        $per_page_numrows = $res->numRows();
        
        // we prepare the "controls bar" (aka the table prologue bar)
        $params = array(
                            'num_rows' => $numrows,
                            'rows_per_page' => $rowsPerPage,
                            'page_num' => $pageNum,
                            'order_by' => $orderBy,
                            'order_type' => $orderType,
                        );
        
        $descriptors = array();
        $descriptors['center'] = array( 'draw' => $drawNumberLinks, 'params' => $params );
        print_table_prologue($descriptors);
?>

<style>
.status-active {
    background-color: #4caf50;
    color: white;
    padding: 5px 10px;
    border-radius: 4px;
    font-weight: bold;
}
.status-expired {
    background-color: #f44336;
    color: white;
    padding: 5px 10px;
    border-radius: 4px;
    font-weight: bold;
}
.status-used {
    background-color: #9e9e9e;
    color: white;
    padding: 5px 10px;
    border-radius: 4px;
    font-weight: bold;
}
.filter-form {
    background: #f5f5f5;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
}
</style>

<div class="filter-form">
    <form method="GET" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="row">
        <div class="col-md-4">
            <label for="username">Filter by Username:</label>
            <input type="text" name="username" id="username" class="form-control" 
                   value="<?php echo $username_enc; ?>" placeholder="Enter username">
        </div>
        <div class="col-md-3">
            <label for="status">Filter by Status:</label>
            <select name="status" id="status" class="form-control">
                <option value="">All Status</option>
                <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                <option value="expired" <?php echo ($status_filter == 'expired') ? 'selected' : ''; ?>>Expired</option>
                <option value="used" <?php echo ($status_filter == 'used') ? 'selected' : ''; ?>>Used</option>
            </select>
        </div>
        <div class="col-md-2">
            <label>&nbsp;</label><br>
            <button type="submit" class="btn btn-primary">🔍 Filter</button>
        </div>
        <div class="col-md-3">
            <label>&nbsp;</label><br>
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">Clear Filters</a>
            <a href="bundle-purchase.php" class="btn btn-success">➕ New Bundle</a>
        </div>
    </form>
</div>

<?php
        $form_descriptor = array();
        
        // print table top
        print_table_top($form_descriptor);

        // second line of table header
        printTableHead($cols, $orderBy, $orderType);

        // closes table header, opens table body
        print_table_middle();
   
        // table content
        $count = 0;
        while ($row = $res->fetchRow()) {
            $rowlen = count($row);
        
            // escape row elements
            for ($i = 0; $i < $rowlen; $i++) {
                $row[$i] = htmlspecialchars($row[$i], ENT_QUOTES, 'UTF-8');
            }
        
            list($bundle_id, $username, $bundle_name, $purchase_date, $activation_date, 
                 $expiry_date, $status, $cost) = $row;
            
            $bundle_id = intval($bundle_id);
            
            // Format dates
            $purchase_date_formatted = date('Y-m-d H:i', strtotime($purchase_date));
            $activation_date_formatted = $activation_date ? date('Y-m-d H:i', strtotime($activation_date)) : '-';
            $expiry_date_formatted = $expiry_date ? date('Y-m-d H:i', strtotime($expiry_date)) : '-';
            
            // Calculate remaining time for active bundles
            $remaining_info = '';
            if ($status == 'active' && $expiry_date) {
                $now = time();
                $expiry_timestamp = strtotime($expiry_date);
                $remaining_seconds = $expiry_timestamp - $now;
                
                if ($remaining_seconds > 0) {
                    $remaining_days = floor($remaining_seconds / 86400);
                    $remaining_hours = floor(($remaining_seconds % 86400) / 3600);
                    $remaining_info = sprintf(' (%dd %dh left)', $remaining_days, $remaining_hours);
                }
            }
            
            // Format status badge
            $status_class = 'status-' . $status;
            $status_badge = sprintf('<span class="%s">%s%s</span>', 
                                   $status_class, 
                                   strtoupper($status),
                                   $remaining_info);
            
            // Format cost
            $cost_formatted = '$' . number_format(floatval($cost), 2);
            
            // Create username link
            $username_link = sprintf('<a href="mng-edit.php?username=%s" title="Edit User">%s</a>', 
                                    urlencode($username), $username);
        
            // build table row
            $table_row = array( 
                $bundle_id,
                $username_link, 
                $bundle_name, 
                $purchase_date_formatted,
                $activation_date_formatted, 
                $expiry_date_formatted,
                $status_badge,
                $cost_formatted
            );

            // print table row
            print_table_row($table_row);

            $count++;
        }
        
        // close tbody,
        // print tfoot
        // and close table + form (if any)
        $table_foot = array(
                                'num_rows' => $numrows,
                                'rows_per_page' => $per_page_numrows,
                                'colspan' => $colspan,
                                'multiple_pages' => $drawNumberLinks
                           );

        $descriptor = array( 'table_foot' => $table_foot );
        print_table_bottom($descriptor);

        // get and print "links"
        $links = setupLinks_str($pageNum, $maxPage, $orderBy, $orderType);
        printLinks($links, $drawNumberLinks);

    } else {
        $failureMsg = "No bundles found";
        if (!empty($username)) {
            $failureMsg .= " for user <strong>" . $username_enc . "</strong>";
        }
        if (!empty($status_filter)) {
            $failureMsg .= " with status <strong>" . $status_filter . "</strong>";
        }
        include_once("include/management/actionMessages.php");
?>
        <div class="text-center mt-4">
            <a href="bundle-purchase.php" class="btn btn-success btn-lg">➕ Purchase First Bundle</a>
        </div>
<?php
    }
    
    include('../common/includes/db_close.php');
    
    include('include/config/logging.php');
    
    print_footer_and_html_epilogue();
?>
