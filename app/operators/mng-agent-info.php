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

    $agent_id = (array_key_exists('agent_id', $_GET) && isset($_GET['agent_id']) && is_numeric($_GET['agent_id']))
              ? intval($_GET['agent_id']) : 0;

    if ($agent_id <= 0) {
        header("Location: mng-agents-list.php");
        exit;
    }

    // print HTML prologue
    $title = t('Intro','mngagentinfo');
    $help = t('helpPage','mngagentinfo');
    
    print_html_prologue($title, $langCode);

    // start printing content
    print_title_and_help($title, $help);

    include('../common/includes/db_open.php');
    include('include/management/pages_common.php');

    // Get agent information
    $sql = sprintf("SELECT id, name, company, phone, email, address, city, country, creation_date, is_deleted, operator_id
                    FROM %s WHERE id = %d AND is_deleted = 0", 
                   $configValues['CONFIG_DB_TBL_DALOAGENTS'], $agent_id);
    $res = $dbSocket->query($sql);
    $logDebugSQL .= "$sql;\n";

    if (!$res || $res->numRows() == 0) {
        $failureMsg = "Agent not found or has been deleted";
        include_once("include/management/actionMessages.php");
        include('../common/includes/db_close.php');
        include('include/config/logging.php');
        print_footer_and_html_epilogue();
        exit;
    }

    $agent_row = $res->fetchRow(DB_FETCHMODE_ASSOC);
    
    // Escape agent data for display
    foreach ($agent_row as $key => $value) {
        $agent_row[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    // Get users assigned to this agent
    $sql = sprintf("SELECT ui.id, ui.username, CONCAT(ui.firstname, ' ', ui.lastname) AS fullname,
                           ui.email, ui.workphone, ui.city, ui.country, ui.creationdate
                    FROM %s ui
                    INNER JOIN user_agent ua ON ui.id = ua.user_id
                    WHERE ua.agent_id = %d
                    ORDER BY ui.username", 
                   $configValues['CONFIG_DB_TBL_DALOUSERINFO'],
                   $agent_id);
    $res_users = $dbSocket->query($sql);
    $logDebugSQL .= "$sql;\n";

    $users = array();
    if ($res_users) {
        while ($user_row = $res_users->fetchRow(DB_FETCHMODE_ASSOC)) {
            // Escape user data for display
            foreach ($user_row as $key => $value) {
                $user_row[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
            $users[] = $user_row;
        }
    }

    // Display agent information
    echo '<div class="container-fluid">';
    echo '<div class="row">';
    echo '<div class="col-lg-12">';
    
    // Agent Details Card
    echo '<div class="card mb-4">';
    echo '<div class="card-header">';
    echo '<h4 class="card-title">Agent Information</h4>';
    echo '</div>';
    echo '<div class="card-body">';
    
    echo '<div class="row">';
    echo '<div class="col-md-6">';
    echo '<table class="table table-borderless">';
    echo '<tr><td><strong>Agent ID:</strong></td><td>' . $agent_row['id'] . '</td></tr>';
    echo '<tr><td><strong>Name:</strong></td><td>' . $agent_row['name'] . '</td></tr>';
    echo '<tr><td><strong>Company:</strong></td><td>' . $agent_row['company'] . '</td></tr>';
    echo '<tr><td><strong>Phone:</strong></td><td>' . $agent_row['phone'] . '</td></tr>';
    echo '<tr><td><strong>Email:</strong></td><td>' . $agent_row['email'] . '</td></tr>';
    echo '<tr><td><strong>Address:</strong></td><td>' . $agent_row['address'] . '</td></tr>';
    echo '</table>';
    echo '</div>';
    
    echo '<div class="col-md-6">';
    echo '<table class="table table-borderless">';
    echo '<tr><td><strong>City:</strong></td><td>' . $agent_row['city'] . '</td></tr>';
    echo '<tr><td><strong>Country:</strong></td><td>' . $agent_row['country'] . '</td></tr>';
    echo '<tr><td><strong>Creation Date:</strong></td><td>' . $agent_row['creation_date'] . '</td></tr>';
    echo '<tr><td><strong>Operator ID:</strong></td><td>' . $agent_row['operator_id'] . '</td></tr>';
    echo '</table>';
    echo '</div>';
    echo '</div>';
    
    echo '</div>'; // card-body
    echo '</div>'; // card
    
    // Action buttons
    echo '<div class="mb-3">';
    echo '<a href="mng-agents-edit.php?agent_id=' . $agent_id . '" class="btn btn-primary">Edit Agent</a> ';
    echo '<a href="mng-agents-list.php" class="btn btn-secondary">Back to Agents List</a>';
    echo '</div>';
    
    // Users assigned to this agent
    echo '<div class="card">';
    echo '<div class="card-header">';
    echo '<h4 class="card-title">Users Assigned to This Agent (' . count($users) . ')</h4>';
    echo '</div>';
    echo '<div class="card-body">';
    
    if (count($users) > 0) {
        echo '<div class="table-responsive">';
        echo '<table class="table table-striped table-hover">';
        echo '<thead class="table-dark">';
        echo '<tr>';
        echo '<th>Username</th>';
        echo '<th>Full Name</th>';
        echo '<th>Email</th>';
        echo '<th>Phone</th>';
        echo '<th>Location</th>';
        echo '<th>Creation Date</th>';
        echo '<th>Actions</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($users as $user) {
            echo '<tr>';
            echo '<td>' . $user['username'] . '</td>';
            echo '<td>' . ((!empty(trim($user['fullname']))) ? $user['fullname'] : "(n/d)") . '</td>';
            echo '<td>' . $user['email'] . '</td>';
            echo '<td>' . $user['workphone'] . '</td>';
            echo '<td>' . $user['city'] . (!empty($user['city']) && !empty($user['country']) ? ', ' : '') . $user['country'] . '</td>';
            echo '<td>' . $user['creationdate'] . '</td>';
            echo '<td>';
            echo '<a href="mng-edit.php?username=' . urlencode($user['username']) . '" class="btn btn-sm btn-outline-primary">Edit</a> ';
            echo '<a href="rep-users.php?username=' . urlencode($user['username']) . '" class="btn btn-sm btn-outline-info">Reports</a>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    } else {
        echo '<div class="alert alert-info">';
        echo '<i class="fas fa-info-circle"></i> No users are currently assigned to this agent.';
        echo '</div>';
    }
    
    echo '</div>'; // card-body
    echo '</div>'; // card
    
    echo '</div>'; // col
    echo '</div>'; // row
    echo '</div>'; // container-fluid

    include('../common/includes/db_close.php');
    include('include/config/logging.php');
    
    print_footer_and_html_epilogue();
?>