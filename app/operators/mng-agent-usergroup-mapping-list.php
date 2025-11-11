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

    // set session's page variable
    $_SESSION['PREV_LIST_PAGE'] = $_SERVER['REQUEST_URI'];

    $cols = array(
        "agent_name" => "Agent Name (click to view details)",
        "agent_company" => "Company/Organization",
        "user_count" => "Total Users Assigned",
        "users" => "Usernames (comma-separated list)"
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

    // start printing content
    $title = t('Intro','mngraduseragentlist');
    $help = t('helpPage','mngraduseragentlist');
    
    print_html_prologue($title, $langCode);
    print_title_and_help($title, $help);
    
    // Add helpful description
    echo '<div class="alert alert-light border-left-primary mb-3">';
    echo '<i class="fas fa-info-circle text-primary"></i> ';
    echo '<strong>Overview:</strong> This page shows all agents and their assigned users. ';
    echo 'Click on an agent name to view detailed information and manage their user assignments.';
    echo '</div>';

    include('../common/includes/db_open.php');
    include('include/management/pages_common.php');

    // we use this simplified query for searching
    $search_agent_name = (array_key_exists('search_agent_name', $_GET) && !empty(trim($_GET['search_agent_name'])))
                       ? trim($_GET['search_agent_name']) : "";
    $search_agent_company = (array_key_exists('search_agent_company', $_GET) && !empty(trim($_GET['search_agent_company'])))
                          ? trim($_GET['search_agent_company']) : "";

    $search_agent_name_enc = (!empty($search_agent_name)) ? htmlspecialchars($search_agent_name, ENT_QUOTES, 'UTF-8') : "";
    $search_agent_company_enc = (!empty($search_agent_company)) ? htmlspecialchars($search_agent_company, ENT_QUOTES, 'UTF-8') : "";

    // setup php session variables for exporting
    $_SESSION['reportTable'] = $configValues['CONFIG_DB_TBL_DALOAGENTS'];
    $_SESSION['reportQuery'] = "";
    $_SESSION['reportType'] = "agentUserMappingList";

    //orig: used as maethod to get total rows - this is required for the pages_numbering.php page
    $sql = sprintf("SELECT COUNT(DISTINCT a.id) FROM %s a WHERE a.is_deleted = 0", 
                   $configValues['CONFIG_DB_TBL_DALOAGENTS']);
    
    if (!empty($search_agent_name)) {
        $sql .= sprintf(" AND a.name LIKE '%%%s%%'", $dbSocket->escapeSimple($search_agent_name));
    }
    if (!empty($search_agent_company)) {
        $sql .= sprintf(" AND a.company LIKE '%%%s%%'", $dbSocket->escapeSimple($search_agent_company));
    }
    
    $res = $dbSocket->query($sql);
    $logDebugSQL .= "$sql;\n";
    $numrows = $res->fetchRow()[0];

    // the partial query is built starting from user input
    // and for being passed to setupNumbering and setupLinks functions
    $partial_query_string = "";
    if (!empty($search_agent_name_enc)) {
        $partial_query_string .= sprintf("&search_agent_name=%s", urlencode($search_agent_name_enc));
    }
    if (!empty($search_agent_company_enc)) {
        $partial_query_string .= sprintf("&search_agent_company=%s", urlencode($search_agent_company_enc));
    }

    // we execute and log the actual query
    $sql = sprintf("SELECT a.id as agent_id, a.name as agent_name, a.company as agent_company,
                           COUNT(ua.user_id) as user_count,
                           GROUP_CONCAT(ui.username ORDER BY ui.username SEPARATOR ', ') as users
                    FROM %s a
                    LEFT JOIN user_agent ua ON a.id = ua.agent_id
                    LEFT JOIN %s ui ON ua.user_id = ui.id
                    WHERE a.is_deleted = 0", 
                   $configValues['CONFIG_DB_TBL_DALOAGENTS'],
                   $configValues['CONFIG_DB_TBL_DALOUSERINFO']);
    
    if (!empty($search_agent_name)) {
        $sql .= sprintf(" AND a.name LIKE '%%%s%%'", $dbSocket->escapeSimple($search_agent_name));
    }
    if (!empty($search_agent_company)) {
        $sql .= sprintf(" AND a.company LIKE '%%%s%%'", $dbSocket->escapeSimple($search_agent_company));
    }
    
    $sql .= " GROUP BY a.id, a.name, a.company";
    
    if ($orderBy == "agent_name") {
        $sql .= " ORDER BY a.name";
    } elseif ($orderBy == "agent_company") {
        $sql .= " ORDER BY a.company";
    } elseif ($orderBy == "user_count") {
        $sql .= " ORDER BY user_count";
    } else {
        $sql .= " ORDER BY a.name";
    }
    
    $sql .= " " . strtoupper($orderType);

    include('include/management/pages_numbering.php'); // must be included after we've built the sql query
    $sql .= sprintf(" LIMIT %s, %s", $offset, $rowsPerPage);
    $res = $dbSocket->query($sql);
    $logDebugSQL .= "$sql;\n";

    $per_page_numrows = $res->numRows();

    // the form is used for bulk deletion
    $action = "mng-agent-usergroup-del.php";
    $form_name = "listall";

    // we prepare the "controls bar" on top of the table
    $descriptors1 = array();

    $descriptors1[] = array(
                                'type' => 'text',
                                'name' => 'search_agent_name',
                                'caption' => t('all','AgentName'),
                                'value' => $search_agent_name_enc,
                                'placeholder' => 'Enter agent name to search...'
                            );

    $descriptors1[] = array(
                                'type' => 'text',
                                'name' => 'search_agent_company',
                                'caption' => t('all','Company'),
                                'value' => $search_agent_company_enc,
                                'placeholder' => 'Enter company name to search...'
                            );

    $descriptors1[] = array(
                                'type' => 'submit',
                                'name' => 'submit',
                                'value' => t('buttons','apply')
                            );

    $descriptors2 = array();

    $descriptors2[] = array(
                                'type' => 'submit',
                                'name' => 'submit',
                                'value' => t('buttons','DeleteSelected'),
                                'onclick' => "javascript:return confirm('".t('messages','operator_confirm')."')",
                                'class' => 'btn-outline-danger'
                            );

    // Print search form
    echo '<form method="GET" class="mb-3">';
    echo '<div class="row g-2 align-items-end">';
    echo '<div class="col-md-3">';
    print_form_component($descriptors1[0]); // Agent name search field
    echo '</div>';
    echo '<div class="col-md-3">';
    print_form_component($descriptors1[1]); // Company search field
    echo '</div>';
    echo '<div class="col-md-2">';
    print_form_component($descriptors1[2]); // Apply button
    echo '</div>';
    echo '</div>';
    echo '</form>';
    
    // Print delete form (will be used by table)
    echo '<form method="POST" action="' . $action . '" name="' . $form_name . '" style="display: none;">';
    print_form_component($descriptors2[0]); // Delete button
    echo '</form>';

    if ($numrows > 0) {
        // print table top
        $form_descriptor = array( 'form' => array( 'action' => $action, 'method' => 'POST', 'name' => $form_name ), );
        
        print_table_top($form_descriptor);

        // add CSRF token
        echo '<input name="csrf_token" type="hidden" value="' . dalo_csrf_token() . '" />';

        // second line of table header
        printTableHead($cols, $orderBy, $orderType, $partial_query_string);

        // closes table header, opens table body
        print_table_middle();
        
        while ($row0 = $res->fetchRow()) {
            $row0len = count($row0);
        
            // escape row elements
            for ($i = 0; $i < $row0len; $i++) {
                $row0[$i] = htmlspecialchars($row0[$i], ENT_QUOTES, 'UTF-8');
            }
        
            list($agent_id, $agent_name, $agent_company, $user_count, $users) = $row0;
            
            // preparing checkboxes and tooltips stuff
            $tooltip = array(
                                'subject' => $agent_name,
                                'actions' => array(),
                            );
            $tooltip['actions'][] = array( 
                                            'href' => sprintf('mng-agent-info.php?agent_id=%s', urlencode($agent_id)),
                                            'label' => t('Tooltip','ViewAgentInfo'),
                                         );

            // create tooltip
            $tooltip = get_tooltip_list_str($tooltip);

            // create checkbox for bulk operations
            $d = array( 'name' => 'agent_id[]', 'value' => $agent_id );
            $checkbox = get_checkbox_str($d);
            
            // format users list
            $users_display = (!empty($users)) ? $users : "(no users assigned)";
            if (strlen($users_display) > 100) {
                $users_display = substr($users_display, 0, 97) . "...";
            }
            
            // build table row
            $table_row = array( $tooltip, $agent_company, $user_count, $users_display );

            // print table row
            print_table_row($table_row);
        }

        // close tbody,
        // print tfoot
        print_table_bottom($numrows, $per_page_numrows, $colspan, $orderBy, $orderType, $partial_query_string);

    } else {
        // No results found
        $message = "No agents found";
        if (!empty($search_agent_name) || !empty($search_agent_company)) {
            $message .= " matching your search criteria. Try different search terms or <a href='mng-agent-usergroup-mapping-list.php'>view all agents</a>.";
        } else {
            $message .= ". <a href='mng-agent-new.php'>Create a new agent</a> to get started.";
        }
        
        echo '<div class="alert alert-info mt-3">';
        echo '<i class="fas fa-info-circle"></i> ' . $message;
        echo '</div>';
    }

    include('../common/includes/db_close.php');

    print_footer_and_html_epilogue();

    include('include/config/logging.php');
?>