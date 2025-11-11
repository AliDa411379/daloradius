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
 */

    include('library/checklogin.php');
    $login_user = $_SESSION['login_user'];

    include_once('../common/includes/config_read.php');
    include_once("lang/main.php");
    include("../common/includes/layout.php");

    $log = "visited page: ";

    // print HTML prologue
    $title = "My Agent";
    $help = "This page displays information about your assigned agent who can assist you with your account.";

    print_html_prologue($title, $langCode);
    print_title_and_help($title, $help);
    
    echo '<div class="container-fluid">';
    echo '<div class="alert alert-info">Welcome ' . htmlspecialchars($login_user) . '! This is your agent page.</div>';
    
    // Now try database operations
    include('../common/includes/db_open.php');
    
    $error_message = null;
    $agents_info = array();
    
    // Get user information first
    $sql = "SELECT id, username FROM " . $configValues['CONFIG_DB_TBL_DALOUSERINFO'] . 
           " WHERE username = '" . $dbSocket->escapeSimple($login_user) . "'";
    $res = $dbSocket->query($sql);
    
    if (PEAR::isError($res)) {
        $error_message = "Database error: " . $res->getMessage();
        echo '<div class="alert alert-danger">' . htmlspecialchars($error_message) . '</div>';
    } elseif ($res->numRows() == 0) {
        echo '<div class="alert alert-warning">User not found in database.</div>';
    } else {
        $user_row = $res->fetchRow(DB_FETCHMODE_ASSOC);
        $user_id = $user_row['id'];
        echo '<div class="alert alert-success">User found! ID: ' . $user_id . '</div>';
        
        // Get agents
        $sql = "SELECT a.name, a.email, a.phone FROM " . $configValues['CONFIG_DB_TBL_DALOAGENTS'] . " a
                INNER JOIN user_agent ua ON a.id = ua.agent_id
                WHERE ua.user_id = " . intval($user_id) . " AND a.is_deleted = 0";
        
        $res = $dbSocket->query($sql);
        
        if (PEAR::isError($res)) {
            echo '<div class="alert alert-danger">Agent query error: ' . htmlspecialchars($res->getMessage()) . '</div>';
        } elseif ($res->numRows() == 0) {
            echo '<div class="alert alert-info">No agents assigned to this user.</div>';
        } else {
            echo '<div class="alert alert-success">Found ' . $res->numRows() . ' agent(s):</div>';
            echo '<ul>';
            while ($agent = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
                echo '<li><strong>' . htmlspecialchars($agent['name']) . '</strong>';
                if ($agent['email']) echo ' - Email: ' . htmlspecialchars($agent['email']);
                if ($agent['phone']) echo ' - Phone: ' . htmlspecialchars($agent['phone']);
                echo '</li>';
            }
            echo '</ul>';
        }
    }
    
    include('../common/includes/db_close.php');
    echo '</div>';

    include('include/config/logging.php');
    print_footer_and_html_epilogue();
?>