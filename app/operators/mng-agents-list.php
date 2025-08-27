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
 * Authors:    Liran Tal <liran@lirantal.com>
 *             Filippo Lauria <filippo.lauria@iit.cnr.it>
 *
 *********************************************************************************************************
 */

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

    // Handle bulk delete
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (array_key_exists('csrf_token', $_POST) && isset($_POST['csrf_token']) && dalo_check_csrf_token($_POST['csrf_token'])) {
            if (array_key_exists('agent_id', $_POST) && is_array($_POST['agent_id']) && count($_POST['agent_id']) > 0) {
                
                include('../common/includes/db_open.php');
                
                $deleted_count = 0;
                $deleted_names = array();
                
                foreach ($_POST['agent_id'] as $agent_id) {
                    $agent_id = intval($agent_id);
                    if ($agent_id > 0) {
                        // Get agent name and details for logging
                        $sql = sprintf("SELECT name, company, email FROM %s WHERE id = %d AND is_deleted = 0", 
                                       $configValues['CONFIG_DB_TBL_DALOAGENTS'], $agent_id);
                        $res = $dbSocket->query($sql);
                        $agent_name = "";
                        $agent_company = "";
                        $agent_email = "";
                        if ($res && $row = $res->fetchRow()) {
                            $agent_name = $row[0];
                            $agent_company = $row[1];
                            $agent_email = $row[2];
                        }
                        
                        // Soft delete the agent (mark as deleted)
                        $sql = sprintf("UPDATE %s SET is_deleted = 1 WHERE id = %d", 
                                       $configValues['CONFIG_DB_TBL_DALOAGENTS'], $agent_id);
                        $res = $dbSocket->query($sql);
                        $logDebugSQL .= "$sql;\n";
                        
                        if (!DB::isError($res) && $dbSocket->affectedRows() > 0) {
                            // Also mark the associated operator as deleted
                            if (!empty($agent_company) || !empty($agent_email)) {
                                $sql_op = sprintf("UPDATE %s SET is_deleted = 1 WHERE is_agent = 1 AND (company = '%s' OR email1 = '%s')", 
                                                  $configValues['CONFIG_DB_TBL_DALOOPERATORS'], 
                                                  $dbSocket->escapeSimple($agent_company),
                                                  $dbSocket->escapeSimple($agent_email));
                                $dbSocket->query($sql_op);
                                $logDebugSQL .= "$sql_op;\n";
                            }
                            
                            $deleted_count++;
                            if (!empty($agent_name)) {
                                $deleted_names[] = $agent_name;
                            }
                        }
                    }
                }
                
                include('../common/includes/db_close.php');
                
                if ($deleted_count > 0) {
                    $successMsg = sprintf("Successfully deleted %d agent(s): %s", 
                                          $deleted_count, 
                                          implode(', ', $deleted_names));
                    $logAction = sprintf("Successfully deleted %d agents on page: ", $deleted_count);
                } else {
                    $failureMsg = "No agents were deleted";
                    $logAction = "Failed deleting agents (no agents deleted) on page: ";
                }
            } else {
                $failureMsg = "No agents selected for deletion";
                $logAction = "Failed deleting agents (no agents selected) on page: ";
            }
        } else {
            $failureMsg = "CSRF token validation failed";
            $logAction = "CSRF token validation failed on page: ";
        }
    }

    // set session's page variable
    $_SESSION['PREV_LIST_PAGE'] = $_SERVER['REQUEST_URI'];

    $cols = array(
                    "id" => t('all','ID'), 
                    "name" => t('all','AgentName'),
                    "company" => t('all','Company'),
                    "phone" => t('all','Phone'),
                    "email" => t('all','Email'),
                    "city" => t('all','City'),
                    "country" => t('all','Country'),
                    "creation_date" => t('all','CreationDate')
                 );
    
    $colspan = count($cols);
    $half_colspan = intval($colspan / 2);
                 
    $param_cols = array();
    foreach ($cols as $k => $v) { if (!is_int($k)) { $param_cols[$k] = $v; } }
    
    // whenever possible we use a whitelist approach
    $orderBy = (array_key_exists('orderBy', $_GET) && isset($_GET['orderBy']) &&
                in_array($_GET['orderBy'], array_keys($param_cols)))
             ? $_GET['orderBy'] : array_keys($param_cols)[0];

    $orderType = (array_key_exists('orderType', $_GET) && isset($_GET['orderType']) &&
                  in_array(strtolower($_GET['orderType']), array( "desc", "asc" )))
               ? strtolower($_GET['orderType']) : "asc";

    // print HTML prologue
    $title = t('Intro','mngagentslist.php');
    $help = t('helpPage','mngagentslist');
    
    print_html_prologue($title, $langCode);

    include_once('include/management/actionMessages.php');
    print_title_and_help($title, $help);

    include('../common/includes/db_open.php');
    include('include/management/pages_common.php');

    // we use this simplified query just to initialize $numrows
    $sql = sprintf("SELECT COUNT(id) FROM %s WHERE is_deleted = 0", $configValues['CONFIG_DB_TBL_DALOAGENTS']);
    $res = $dbSocket->query($sql);
    $numrows = $res->fetchrow()[0];

     if ($numrows > 0) {
        /* START - Related to pages_numbering.php */
        
        // when $numrows is set, $maxPage is calculated inside this include file
        include('include/management/pages_numbering.php');    // must be included after opendb because it needs to read
                                                              // the CONFIG_IFACE_TABLES_LISTING variable from the config file
        
        // here we decide if page numbers should be shown
        $drawNumberLinks = strtolower($configValues['CONFIG_IFACE_TABLES_LISTING_NUM']) == "yes" && $maxPage > 1;

        /* END */
        
        // we execute and log the actual query
        $sql = sprintf("SELECT id, name, company, phone, email, address, city, country, creation_date
                          FROM %s WHERE is_deleted = 0", $configValues['CONFIG_DB_TBL_DALOAGENTS']);
        $sql .= sprintf(" ORDER BY %s %s LIMIT %s, %s", $orderBy, $orderType, $offset, $rowsPerPage);
        $res = $dbSocket->query($sql);
        $logDebugSQL .= "$sql;\n";
        
        $per_page_numrows = $res->numRows();
        
        // this can be passed as form attribute and 
        // printTableFormControls function parameter
        // Submit to this page to handle bulk delete in the POST branch above
        $action = "mng-agents-list.php";
        
        // we prepare the "controls bar" (aka the table prologue bar)
        $params = array(
                            'num_rows' => $numrows,
                            'rows_per_page' => $rowsPerPage,
                            'page_num' => $pageNum,
                            'order_by' => $orderBy,
                            'order_type' => $orderType,
                        );
        
        // we prepare the "controls bar" (aka the table prologue bar)
        $additional_controls = array();
        $additional_controls[] = array(
                                'onclick' => "removeCheckbox('listall','mng-agents-list.php')",
                                'label' => 'Delete',
                                'class' => 'btn-danger',
                              );
        
        $descriptors = array();
        $descriptors['start'] = array( 'common_controls' => 'agent_id[]', 'additional_controls' => $additional_controls );
        $descriptors['center'] = array( 'draw' => $drawNumberLinks, 'params' => $params );
        print_table_prologue($descriptors);
        
        $form_descriptor = array( 'form' => array( 'action' => $action, 'method' => 'POST', 'name' => 'listall' ), );
        
        // print table top
        print_table_top($form_descriptor);
        
        // add CSRF token
        echo '<input name="csrf_token" type="hidden" value="' . dalo_csrf_token() . '" />';

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
            
            list($id, $name, $company, $phone, $email, $address, $city, $country, $creation_date) = $row;
            
            // preparing checkboxes and tooltips stuff
            $tooltip = array(
                                'subject' => $name,
                                'actions' => array(),
                            );
            $tooltip['actions'][] = array( 'href' => sprintf('mng-agents-edit.php?agent_id=%s', urlencode($id), ), 'label' => t('Tooltip','EditAgent'), );

            // create tooltip
            $tooltip = get_tooltip_list_str($tooltip);

            // create checkbox
            $d = array( 'name' => 'agent_id[]', 'value' => $id, 'label' => $id );
            $checkbox = get_checkbox_str($d);

            // format creation date
            if (!empty($creation_date)) {
                $creation_date = date('Y-m-d H:i:s', strtotime($creation_date));
            }

            // build table row (note: we skip address column in display)
            $table_row = array( $checkbox, $tooltip, $company, $phone, $email, $city, $country, $creation_date );

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

        $descriptor = array( 'form' => $form_descriptor, 'table_foot' => $table_foot );
        print_table_bottom($descriptor);

        // get and print "links"
        $links = setupLinks_str($pageNum, $maxPage, $orderBy, $orderType);
        printLinks($links, $drawNumberLinks);

        include('../common/includes/db_close.php');
        
    } else {
        $failureMsg = "Nothing to display";
        include_once("include/management/actionMessages.php");
    }
    
    include('include/config/logging.php');
    print_footer_and_html_epilogue();
?>