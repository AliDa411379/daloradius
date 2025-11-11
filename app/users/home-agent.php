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
    $login_user = $_SESSION['login_user'];

    include_once('../common/includes/config_read.php');
    include_once("lang/main.php");
    include("../common/includes/layout.php");

    $log = "visited page: ";

    // Get agent information
    include('../common/includes/db_open.php');
    
    // Get user information first
    $sql = "SELECT id, username FROM " . $configValues['CONFIG_DB_TBL_DALOUSERINFO'] . 
           " WHERE username = '" . $dbSocket->escapeSimple($login_user) . "'";
    $res = $dbSocket->query($sql);
    
    if (PEAR::isError($res)) {
        $error_message = "Database error: " . $res->getMessage();
    } elseif ($res->numRows() == 0) {
        $error_message = "User not found in database.";
    } else {
        $user_row = $res->fetchRow(DB_FETCHMODE_ASSOC);
        $user_id = $user_row['id'];
        
        // Get all agents assigned to this user (handle multiple agents)
        $sql = "SELECT a.id as agent_id, a.name as agent_name, a.company as agent_company,
                       a.email as agent_email, a.phone as agent_phone, a.address as agent_address,
                       a.city as agent_city, a.country as agent_country, a.creation_date
                FROM " . $configValues['CONFIG_DB_TBL_DALOAGENTS'] . " a
                INNER JOIN user_agent ua ON a.id = ua.agent_id
                WHERE ua.user_id = " . intval($user_id) . " AND a.is_deleted = 0
                ORDER BY a.name";
        
        $res = $dbSocket->query($sql);
        
        if (PEAR::isError($res)) {
            $error_message = "Database error when fetching agents: " . $res->getMessage();
        } elseif ($res->numRows() == 0) {
            $no_agent_message = "You are not currently assigned to any agent.";
        } else {
            $agents_info = array();
            while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
                $agents_info[] = $row;
            }
        }
    }

    include('../common/includes/db_close.php');

    // print HTML prologue
    $title = "My Agent";
    $help = "This page displays information about your assigned agent who can assist you with your account.";

    print_html_prologue($title, $langCode);
    print_title_and_help($title, $help);
    


    if (isset($error_message)) {
        echo '<div class="alert alert-danger mt-3">';
        echo '<i class="bi bi-exclamation-triangle"></i> ' . $error_message;
        echo '</div>';
    } elseif (isset($no_agent_message)) {
        echo '<div class="alert alert-info mt-3">';
        echo '<i class="bi bi-info-circle"></i> ' . $no_agent_message;
        echo '<br><br>If you need assistance, please contact your system administrator.';
        echo '</div>';
    } else {
        // Display agents information (handle multiple agents)
        $agent_count = count($agents_info);
        
        if ($agent_count == 1) {
            echo '<div class="alert alert-info mt-3">';
            echo '<i class="bi bi-info-circle"></i> You have <strong>1 agent</strong> assigned to assist you.';
            echo '</div>';
        } else {
            echo '<div class="alert alert-info mt-3">';
            echo '<i class="bi bi-info-circle"></i> You have <strong>' . $agent_count . ' agents</strong> assigned to assist you.';
            echo '</div>';
        }
        
        foreach ($agents_info as $index => $agent_info) {
            echo '<div class="row mt-4">';
            echo '<div class="col-lg-8">';
            
            // Agent Details Card
            echo '<div class="card">';
            echo '<div class="card-header">';
            if ($agent_count > 1) {
                echo '<h5 class="card-title mb-0"><i class="bi bi-person-badge"></i> Agent #' . ($index + 1) . ' - ' . htmlspecialchars($agent_info['agent_name']) . '</h5>';
            } else {
                echo '<h5 class="card-title mb-0"><i class="bi bi-person-badge"></i> Your Agent</h5>';
            }
            echo '</div>';
            echo '<div class="card-body">';
            
            echo '<div class="row">';
            echo '<div class="col-md-6">';
            echo '<table class="table table-borderless">';
            echo '<tr><td><strong>Name:</strong></td><td>' . htmlspecialchars($agent_info['agent_name']) . '</td></tr>';
            echo '<tr><td><strong>Company:</strong></td><td>' . (!empty($agent_info['agent_company']) ? htmlspecialchars($agent_info['agent_company']) : '<span class="text-muted">Not provided</span>') . '</td></tr>';
            echo '<tr><td><strong>Email:</strong></td><td>';
            if (!empty($agent_info['agent_email'])) {
                echo '<a href="mailto:' . htmlspecialchars($agent_info['agent_email']) . '">' . htmlspecialchars($agent_info['agent_email']) . '</a>';
            } else {
                echo '<span class="text-muted">Not provided</span>';
            }
            echo '</td></tr>';
            echo '<tr><td><strong>Phone:</strong></td><td>';
            if (!empty($agent_info['agent_phone'])) {
                echo '<a href="tel:' . htmlspecialchars($agent_info['agent_phone']) . '">' . htmlspecialchars($agent_info['agent_phone']) . '</a>';
            } else {
                echo '<span class="text-muted">Not provided</span>';
            }
            echo '</td></tr>';
            echo '</table>';
            echo '</div>';
            
            echo '<div class="col-md-6">';
            echo '<table class="table table-borderless">';
            echo '<tr><td><strong>Address:</strong></td><td>' . (!empty($agent_info['agent_address']) ? htmlspecialchars($agent_info['agent_address']) : '<span class="text-muted">Not provided</span>') . '</td></tr>';
            echo '<tr><td><strong>City:</strong></td><td>' . (!empty($agent_info['agent_city']) ? htmlspecialchars($agent_info['agent_city']) : '<span class="text-muted">Not provided</span>') . '</td></tr>';
            echo '<tr><td><strong>Country:</strong></td><td>' . (!empty($agent_info['agent_country']) ? htmlspecialchars($agent_info['agent_country']) : '<span class="text-muted">Not provided</span>') . '</td></tr>';
            echo '<tr><td><strong>Member Since:</strong></td><td>' . (!empty($agent_info['creation_date']) ? date('F j, Y', strtotime($agent_info['creation_date'])) : '<span class="text-muted">Not available</span>') . '</td></tr>';
            echo '</table>';
            echo '</div>';
            echo '</div>';

            echo '</div>'; // card-body
            echo '</div>'; // card
            echo '</div>'; // col-lg-8
            
            // Contact Actions Card
            echo '<div class="col-lg-4">';
            echo '<div class="card">';
            echo '<div class="card-header">';
            echo '<h5 class="card-title mb-0"><i class="bi bi-telephone"></i> Quick Contact</h5>';
            echo '</div>';
            echo '<div class="card-body">';
            
            if (!empty($agent_info['agent_email'])) {
                echo '<a href="mailto:' . htmlspecialchars($agent_info['agent_email']) . '" class="btn btn-primary btn-sm w-100 mb-2">';
                echo '<i class="bi bi-envelope"></i> Send Email';
                echo '</a>';
            }
            
            if (!empty($agent_info['agent_phone'])) {
                echo '<a href="tel:' . htmlspecialchars($agent_info['agent_phone']) . '" class="btn btn-success btn-sm w-100 mb-2">';
                echo '<i class="bi bi-telephone"></i> Call Now';
                echo '</a>';
            }
            
            if (empty($agent_info['agent_email']) && empty($agent_info['agent_phone'])) {
                echo '<div class="text-muted text-center">';
                echo '<i class="bi bi-info-circle"></i><br>';
                echo 'No contact information available.<br>';
                echo 'Please contact your system administrator.';
                echo '</div>';
            }
            
            echo '</div>'; // card-body
            echo '</div>'; // card
            echo '</div>'; // col-lg-4
            echo '</div>'; // row
            
            // Add separator between agents if there are multiple
            if ($agent_count > 1 && $index < $agent_count - 1) {
                echo '<hr class="my-4">';
            }
        }
        
        // Help Card (shown once at the bottom)
        echo '<div class="row mt-4">';
        echo '<div class="col-12">';
        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h5 class="card-title mb-0"><i class="bi bi-question-circle"></i> Need Help?</h5>';
        echo '</div>';
        echo '<div class="card-body">';
        if ($agent_count == 1) {
            echo '<p class="card-text">Your agent is here to help you with:</p>';
        } else {
            echo '<p class="card-text">Your agents are here to help you with:</p>';
        }
        echo '<div class="row">';
        echo '<div class="col-md-6">';
        echo '<ul class="list-unstyled">';
        echo '<li><i class="bi bi-check-circle text-success"></i> Account questions</li>';
        echo '<li><i class="bi bi-check-circle text-success"></i> Technical support</li>';
        echo '</ul>';
        echo '</div>';
        echo '<div class="col-md-6">';
        echo '<ul class="list-unstyled">';
        echo '<li><i class="bi bi-check-circle text-success"></i> Billing inquiries</li>';
        echo '<li><i class="bi bi-check-circle text-success"></i> Service changes</li>';
        echo '</ul>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    include('include/config/logging.php');
    print_footer_and_html_epilogue();
?>