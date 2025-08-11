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
    include_once("../common/includes/validation.php");
    include("../common/includes/layout.php");
    
    // init logging variables
    $log = "visited page: ";
    $logAction = "";
    $logDebugSQL = "";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        if (array_key_exists('csrf_token', $_POST) && isset($_POST['csrf_token']) && dalo_check_csrf_token($_POST['csrf_token'])) {
        
            $agent_name = (array_key_exists('agent_name', $_POST) && isset($_POST['agent_name']))
                           ? trim(str_replace("%", "", $_POST['agent_name'])) : "";
            $agent_name_enc = (!empty($agent_name)) ? htmlspecialchars($agent_name, ENT_QUOTES, 'UTF-8') : "";
            
            $company = (array_key_exists('company', $_POST) && isset($_POST['company'])) ? trim($_POST['company']) : "";
            $phone = (array_key_exists('phone', $_POST) && isset($_POST['phone'])) ? trim($_POST['phone']) : "";
            $email = (array_key_exists('email', $_POST) && isset($_POST['email'])) ? trim($_POST['email']) : "";
            $address = (array_key_exists('address', $_POST) && isset($_POST['address'])) ? trim($_POST['address']) : "";
            $city = (array_key_exists('city', $_POST) && isset($_POST['city'])) ? trim($_POST['city']) : "";
            $country = (array_key_exists('country', $_POST) && isset($_POST['country'])) ? trim($_POST['country']) : "";

            // validate required fields
            if (empty($agent_name)) {
                $failureMsg = "Agent name is required";
                $logAction .= "Failed creating agent (missing agent name) on page: ";
            } else {
                include('../common/includes/db_open.php');
                
                // check if agent name already exists
                $sql = "SELECT COUNT(*) FROM " . $configValues['CONFIG_DB_TBL_DALOAGENTS'] . " WHERE name = ?";
                $prep = $dbSocket->prepare($sql);
                $prep->bind_param('s', $agent_name);
                $prep->execute();
                $prep->bind_result($numrows);
                $prep->fetch();
                $prep->close();
                
                if ($numrows > 0) {
                    $failureMsg = "Agent with this name already exists";
                    $logAction .= "Failed creating agent (agent name already exists) on page: ";
                } else {
                    // insert new agent
                    $sql = "INSERT INTO " . $configValues['CONFIG_DB_TBL_DALOAGENTS'] . 
                           " (name, company, phone, email, address, city, country, creation_date) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                    $prep = $dbSocket->prepare($sql);
                    $prep->bind_param('sssssss', $agent_name, $company, $phone, $email, $address, $city, $country);
                    $res = $prep->execute();
                    
                    if ($res) {
                        $successMsg = "Successfully created agent: <strong>$agent_name_enc</strong>";
                        $logAction .= "Successfully created agent [$agent_name] on page: ";
                        
                        // clear form data after successful creation
                        $agent_name = $company = $phone = $email = $address = $city = $country = "";
                    } else {
                        $failureMsg = "Error creating agent";
                        $logAction .= "Failed creating agent [$agent_name] on page: ";
                    }
                    
                    $prep->close();
                }
                
                include('../common/includes/db_close.php');
            }
            
        } else {
            $failureMsg = "CSRF token validation failed";
            $logAction .= "CSRF token validation failed on page: ";
        }
    }

    // print HTML prologue
    $extra_css = array();
    
    $extra_js = array(
        "static/js/productive_funcs.js",
    );
    
    $title = t('Intro','mngagentsnew.php');
    $help = t('helpPage','mngagentsnew');
    
    print_html_prologue($title, $langCode, $extra_css, $extra_js);
    
    include_once('include/management/actionMessages.php');
    print_title_and_help($title, $help);

    if (!isset($successMsg)) {

        $input_descriptors0 = array();
        
        $input_descriptors0[] = array(
                                        "id" => "agent_name",
                                        "name" => "agent_name",
                                        "caption" => t('all','AgentName'),
                                        "type" => "text",
                                        "value" => ((isset($agent_name)) ? $agent_name : ""),
                                        "required" => true
                                     );
                                    
        $input_descriptors0[] = array(
                                        "id" => "company",
                                        "name" => "company",
                                        "caption" => t('all','Company'),
                                        "type" => "text",
                                        "value" => ((isset($company)) ? $company : "")
                                     );
                                    
        $input_descriptors0[] = array(
                                        "id" => "phone",
                                        "name" => "phone",
                                        "caption" => t('all','Phone'),
                                        "type" => "text",
                                        "value" => ((isset($phone)) ? $phone : "")
                                     );
                                    
        $input_descriptors0[] = array(
                                        "id" => "email",
                                        "name" => "email",
                                        "caption" => t('all','Email'),
                                        "type" => "email",
                                        "value" => ((isset($email)) ? $email : "")
                                     );
                                    
        $input_descriptors0[] = array(
                                        "id" => "address",
                                        "name" => "address",
                                        "caption" => t('all','Address'),
                                        "type" => "text",
                                        "value" => ((isset($address)) ? $address : "")
                                     );
                                    
        $input_descriptors0[] = array(
                                        "id" => "city",
                                        "name" => "city",
                                        "caption" => t('all','City'),
                                        "type" => "text",
                                        "value" => ((isset($city)) ? $city : "")
                                     );
                                    
        $input_descriptors0[] = array(
                                        "id" => "country",
                                        "name" => "country",
                                        "caption" => t('all','Country'),
                                        "type" => "text",
                                        "value" => ((isset($country)) ? $country : "")
                                     );
        
        // set navbar stuff
        $navkeys = array( array( 'AgentInfo', "Agent Info" ) );

        // print navbar controls
        print_tab_header($navkeys);
        
        open_form();
    
        // open tab wrapper
        open_tab_wrapper();
    
        // tab 0
        open_tab($navkeys, 0, true);
    
        $fieldset0_descriptor = array(
                                        "title" => "Agent Info"
                                     );

        open_fieldset($fieldset0_descriptor);

        foreach ($input_descriptors0 as $input_descriptor) {
            print_form_component($input_descriptor);
        }

        close_fieldset();

        close_tab($navkeys, 0);

        // close tab wrapper
        close_tab_wrapper();

        $input_descriptors2 = array();
        $input_descriptors2[] = array(
                                        "type" => "submit",
                                        "name" => "submit",
                                        "value" => t('buttons','apply')
                                     );

        foreach ($input_descriptors2 as $input_descriptor) {
            print_form_component($input_descriptor);
        }

        close_form();

    }
    
    include('include/config/logging.php');
    print_footer_and_html_epilogue();
?>