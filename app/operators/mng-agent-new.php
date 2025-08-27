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
 * Description:    Add new agent
 *
 * Authors:        Filippo Lauria <filippo.lauria@iit.cnr.it>
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

// Function to assign permissions to a new agent operator
function assignAgentPermissions($dbSocket, $operator_id, $configValues, &$logDebugSQL, $post_data = array()) {
    $permissions_granted = 0;
    
    // Check if we have ACL data from the form
    if (!empty($post_data)) {
        // left piece of the query which is the same for all common values to insert
        $sql0 = sprintf("INSERT INTO %s (operator_id, file, access) VALUES ",
                        $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL']);

        $sql_piece_format = sprintf("(%s", $operator_id) . ", '%s', '%s')";
        $sql_pieces = array();

        // insert operators acl for this operator
        foreach ($post_data as $field => $access) {
            
            if (!preg_match('/^ACL_/', $field)) { 
                continue;
            }
            
            $file = substr($field, 4);
            $sql_pieces[] = sprintf($sql_piece_format, $dbSocket->escapeSimple($file),
                                                       $dbSocket->escapeSimple($access));
            $permissions_granted++;
        }
        
        if (count($sql_pieces) > 0) {
            $sql = $sql0 . implode(", ", $sql_pieces);
            $res = $dbSocket->query($sql);
            $logDebugSQL .= "$sql;\n";
            
            if (DB::isError($res)) {
                $permissions_granted = 0; // Reset on error
            }
        }
    } else {
        // Fallback: Grant all permissions if no ACL data provided
        $sql = sprintf("SELECT file FROM %s", $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL_FILES']);
        $res = $dbSocket->query($sql);
        $logDebugSQL .= "$sql;\n";
        
        $sql0 = sprintf("INSERT INTO %s (operator_id, file, access) VALUES ",
                        $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL']);
        $sql_piece_format = sprintf("(%s", $operator_id) . ", '%s', 1)";
        $sql_pieces = array();
        
        while ($row = $res->fetchRow()) {
            $file = $row[0];
            $sql_pieces[] = sprintf($sql_piece_format, $dbSocket->escapeSimple($file));
            $permissions_granted++;
        }
        
        if (count($sql_pieces) > 0) {
            $sql = $sql0 . implode(", ", $sql_pieces);
            $dbSocket->query($sql);
            $logDebugSQL .= "$sql;\n";
        }
    }
    
    return $permissions_granted;
}

// print HTML prologue
$title = t('Intro','mngagentsnew.php');
$help = t('helpPage','mngagentsnew');

print_html_prologue($title, $langCode);

include("../common/includes/db_open.php");
include('include/management/pages_common.php');

// start printing content
print_header_and_footer($title, $help, $logDebugSQL, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (array_key_exists('csrf_token', $_POST) && isset($_POST['csrf_token']) && dalo_check_csrf_token($_POST['csrf_token'])) {

        // get form data
        $name = (array_key_exists('name', $_POST) && isset($_POST['name'])) ? trim($_POST['name']) : "";
        $company = (array_key_exists('company', $_POST) && isset($_POST['company'])) ? trim($_POST['company']) : "";
        $email = (array_key_exists('email', $_POST) && isset($_POST['email'])) ? trim($_POST['email']) : "";
        $phone = (array_key_exists('phone', $_POST) && isset($_POST['phone'])) ? trim($_POST['phone']) : "";
        $address = (array_key_exists('address', $_POST) && isset($_POST['address'])) ? trim($_POST['address']) : "";
        $city = (array_key_exists('city', $_POST) && isset($_POST['city'])) ? trim($_POST['city']) : "";
        $country = (array_key_exists('country', $_POST) && isset($_POST['country'])) ? trim($_POST['country']) : "";
        
        // operator account fields
        $operator_username = (array_key_exists('operator_username', $_POST) && isset($_POST['operator_username'])) ? trim($_POST['operator_username']) : "";
        $operator_password = (array_key_exists('operator_password', $_POST) && isset($_POST['operator_password'])) ? trim($_POST['operator_password']) : "";
        $firstname = (array_key_exists('firstname', $_POST) && isset($_POST['firstname'])) ? trim($_POST['firstname']) : "";
        $lastname = (array_key_exists('lastname', $_POST) && isset($_POST['lastname'])) ? trim($_POST['lastname']) : "";

        // validate required fields
        if (empty($name)) {
            $failureMsg = "Agent name is required";
            $logAction .= "Failed creating agent (empty name) on page: ";
        } elseif (empty($operator_username) || empty($operator_password)) {
            $failureMsg = "Operator username and password are required for agent login access";
            $logAction .= "Failed creating agent (missing operator credentials) on page: ";
        } else {
            // Check if agent name already exists
            $sql_check = sprintf("SELECT COUNT(*) FROM %s WHERE name = '%s' AND is_deleted = 0",
                                $configValues['CONFIG_DB_TBL_DALOAGENTS'],
                                $dbSocket->escapeSimple($name));
            $res_check = $dbSocket->query($sql_check);
            $logDebugSQL .= "$sql_check;\n";
            $name_exists = ($res_check->fetchrow()[0] > 0);
            
            if ($name_exists) {
                $failureMsg = "Agent name already exists. Please choose a different name.";
                $logAction .= "Failed creating agent (duplicate name) on page: ";
            } else {
            // create agent
            $current_datetime = date('Y-m-d H:i:s');
            $currBy = $operator;

            $sql = sprintf("INSERT INTO %s 
                            (name, company, email, phone, address, city, country) 
                            VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s')",
                           $configValues['CONFIG_DB_TBL_DALOAGENTS'],
                           $dbSocket->escapeSimple($name),
                           $dbSocket->escapeSimple($company),
                           $dbSocket->escapeSimple($email),
                           $dbSocket->escapeSimple($phone),
                           $dbSocket->escapeSimple($address),
                           $dbSocket->escapeSimple($city),
                           $dbSocket->escapeSimple($country));

            $res = $dbSocket->query($sql);
            $logDebugSQL .= "$sql;\n";

            if (!DB::isError($res)) {
                $agent_id = $dbSocket->getOne("SELECT LAST_INSERT_ID()");
                
                // Check if operator username already exists
                $sql = sprintf("SELECT COUNT(*) FROM %s WHERE username='%s'",
                               $configValues['CONFIG_DB_TBL_DALOOPERATORS'], $dbSocket->escapeSimple($operator_username));
                $res2 = $dbSocket->query($sql);
                $logDebugSQL .= "$sql;\n";
                $operator_exists = ($res2->fetchrow()[0] > 0);
                
                if ($operator_exists) {
                    $failureMsg = "Operator username already exists";
                    $logAction .= "Failed creating operator account (username exists) on page: ";
                } else {
                    // Create operator account for the agent
                    $sql = sprintf("INSERT INTO %s (username, password, firstname, lastname, title, department, company, phone1, phone2, email1, email2, messenger1, messenger2, notes, creationdate, creationby, is_agent) VALUES ('%s', '%s', '%s', '%s', '', '', '%s', '%s', '', '%s', '', '', '', '', '%s', '%s', 1)",
                                   $configValues['CONFIG_DB_TBL_DALOOPERATORS'],
                                   $dbSocket->escapeSimple($operator_username),
                                   $dbSocket->escapeSimple($operator_password),
                                   $dbSocket->escapeSimple($firstname),
                                   $dbSocket->escapeSimple($lastname),
                                   $dbSocket->escapeSimple($company),
                                   $dbSocket->escapeSimple($phone),
                                   $dbSocket->escapeSimple($email),
                                   $dbSocket->escapeSimple($current_datetime),
                                   $dbSocket->escapeSimple($currBy));
                    $res3 = $dbSocket->query($sql);
                    $logDebugSQL .= "$sql;\n";
                    
                    if (!DB::isError($res3)) {
                        // Get the newly created operator ID
                        $sql = sprintf("SELECT id FROM %s WHERE username='%s'",
                                       $configValues['CONFIG_DB_TBL_DALOOPERATORS'],
                                       $dbSocket->escapeSimple($operator_username));
                        $res4 = $dbSocket->query($sql);
                        $logDebugSQL .= "$sql;\n";
                        
                        if ($res4 && $row = $res4->fetchRow()) {
                            $new_operator_id = $row[0];
                            
                            // Update the agent record to link it to the operator
                            $sql_update = sprintf("UPDATE %s SET operator_id = %d WHERE id = %d",
                                                  $configValues['CONFIG_DB_TBL_DALOAGENTS'],
                                                  intval($new_operator_id),
                                                  intval($agent_id));
                            $res_update = $dbSocket->query($sql_update);
                            $logDebugSQL .= "$sql_update;\n";
                            
                            if (DB::isError($res_update)) {
                                // If operator_id column doesn't exist, we'll continue without it
                                // The system will fall back to matching by company/name
                                $logDebugSQL .= "Warning: Could not set operator_id relationship (column may not exist);\n";
                            }
                            
                            // Grant permissions to the new agent operator based on form selection
                            $permissions_granted = assignAgentPermissions($dbSocket, $new_operator_id, $configValues, $logDebugSQL, $_POST);
                            
                            $successMsg = sprintf("Successfully created agent <strong>%s</strong> (ID: %d) with operator account <strong>%s</strong><br>Operator marked as agent and granted %d permissions", 
                                                  htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), $agent_id,
                                                  htmlspecialchars($operator_username, ENT_QUOTES, 'UTF-8'), $permissions_granted);
                            $logAction .= sprintf("Successfully created agent %s (ID: %d) with operator account %s marked as agent and granted %d permissions on page: ", $name, $agent_id, $operator_username, $permissions_granted);
                        } else {
                            $successMsg = sprintf("Successfully created agent <strong>%s</strong> (ID: %d) with operator account <strong>%s</strong><br>Operator marked as agent. Warning: Could not assign permissions automatically", 
                                                  htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), $agent_id,
                                                  htmlspecialchars($operator_username, ENT_QUOTES, 'UTF-8'));
                            $logAction .= sprintf("Successfully created agent %s (ID: %d) with operator account %s marked as agent but failed to assign permissions on page: ", $name, $agent_id, $operator_username);
                        }
                        
                        // clear form data after successful creation
                        $name = $company = $email = $phone = $address = $city = $country = "";
                        $operator_username = $operator_password = $firstname = $lastname = "";
                    } else {
                        $failureMsg = "Error creating operator account: " . $res3->getMessage();
                        $logAction .= sprintf("Failed creating operator account %s on page: ", $operator_username);
                    }
                }
            } else {
                $failureMsg = "Error creating agent: " . $res->getMessage();
                $logAction .= sprintf("Failed creating agent %s on page: ", $name);
            }
            } // End of name_exists check
        }

    } else {
        // csrf
        $failureMsg = "CSRF token error";
        $logAction .= "$failureMsg on page: ";
    }
}

// set navbar stuff
$navkeys = array( 
    array( 'AgentInfo', "Agent Information & Login Account" ), 
    'ContactInfo', 
    array( 'ACLSettings', "Access Control List" ) 
);

// include action messages to display success/error messages
include_once('include/management/actionMessages.php');

// print navbar controls
print_tab_header($navkeys);

open_form();

// open tab wrapper
open_tab_wrapper();

// tab 0 - Agent Information & Login Account
open_tab($navkeys, 0, true);

// set form component descriptors for agent info
$input_descriptors1 = array();

$input_descriptors1[] = array(
                                'name' => 'name',
                                'caption' => t('all','Name'),
                                'type' => 'text',
                                'value' => ((isset($name)) ? $name : ""),
                                'required' => true
                             );

$input_descriptors1[] = array(
                                'name' => 'company',
                                'caption' => t('all','Company'),
                                'type' => 'text',
                                'value' => ((isset($company)) ? $company : "")
                             );

$input_descriptors1[] = array(
                                'name' => 'email',
                                'caption' => t('all','Email'),
                                'type' => 'email',
                                'value' => ((isset($email)) ? $email : "")
                             );

$input_descriptors1[] = array(
                                'name' => 'phone',
                                'caption' => t('all','Phone'),
                                'type' => 'text',
                                'value' => ((isset($phone)) ? $phone : "")
                             );

// Operator Account Fields
$input_descriptors1[] = array(
                                'name' => 'operator_username',
                                'caption' => 'Operator Username',
                                'type' => 'text',
                                'value' => ((isset($operator_username)) ? $operator_username : ""),
                                'required' => true,
                                'tooltipText' => 'Username for agent login access'
                             );

$input_descriptors1[] = array(
                                'name' => 'operator_password',
                                'caption' => 'Operator Password',
                                'type' => 'password',
                                'value' => ((isset($operator_password)) ? $operator_password : ""),
                                'required' => true,
                                'tooltipText' => 'Password for agent login access'
                             );

$input_descriptors1[] = array(
                                'name' => 'firstname',
                                'caption' => 'First Name',
                                'type' => 'text',
                                'value' => ((isset($firstname)) ? $firstname : ""),
                                'tooltipText' => 'Agent operator first name'
                             );

$input_descriptors1[] = array(
                                'name' => 'lastname',
                                'caption' => 'Last Name',
                                'type' => 'text',
                                'value' => ((isset($lastname)) ? $lastname : ""),
                                'tooltipText' => 'Agent operator last name'
                             );

$input_descriptors2 = array();

$input_descriptors2[] = array(
                                'name' => 'address',
                                'caption' => t('all','Address'),
                                'type' => 'text',
                                'value' => ((isset($address)) ? $address : "")
                             );

$input_descriptors2[] = array(
                                'name' => 'city',
                                'caption' => t('all','City'),
                                'type' => 'text',
                                'value' => ((isset($city)) ? $city : "")
                             );

$input_descriptors2[] = array(
                                'name' => 'country',
                                'caption' => t('all','Country'),
                                'type' => 'text',
                                'value' => ((isset($country)) ? $country : "")
                             );

// Agent Information fieldset
$fieldset1_descriptor = array(
    "title" => "Agent Information & Login Account",
);

open_fieldset($fieldset1_descriptor);
foreach ($input_descriptors1 as $input_descriptor) {
    print_form_component($input_descriptor);
}
close_fieldset();

close_tab($navkeys, 0);

// tab 1 - Contact Information
open_tab($navkeys, 1);

$fieldset2_descriptor = array(
    "title" => "Contact Information",
);

open_fieldset($fieldset2_descriptor);
foreach ($input_descriptors2 as $input_descriptor) {
    print_form_component($input_descriptor);
}
close_fieldset();

close_tab($navkeys, 1);

// tab 2 - Access Control List
open_tab($navkeys, 2);

include_once('include/management/operator_acls.php');
drawOperatorACLs();

close_tab($navkeys, 2);

// close tab wrapper
close_tab_wrapper();

$input_descriptors_hidden = array();
$input_descriptors_hidden[] = array(
                                        'name' => 'csrf_token',
                                        'type' => 'hidden',
                                        'value' => dalo_csrf_token(),
                                    );

foreach ($input_descriptors_hidden as $input_descriptor) {
    print_form_component($input_descriptor);
}

$input_descriptors_submit = array();
$input_descriptors_submit[] = array(
                                        'type' => 'submit',
                                        'name' => 'submit',
                                        'value' => t('buttons','apply')
                                    );

foreach ($input_descriptors_submit as $input_descriptor) {
    print_form_component($input_descriptor);
}

close_form();

// Quick actions
echo '<div class="mt-4">';
echo '<div class="card">';
echo '<div class="card-header">';
echo '<h5 class="card-title mb-0"><i class="bi bi-lightning-charge me-2"></i>Quick Actions</h5>';
echo '</div>';
echo '<div class="card-body">';
echo '<a href="mng-agents-list.php" class="btn btn-primary me-2">';
echo '<i class="bi bi-list-ul me-1"></i>View All Agents';
echo '</a>';
echo '<a href="mng-agents.php" class="btn btn-secondary">';
echo '<i class="bi bi-arrow-left me-1"></i>Back to Agent Management';
echo '</a>';
echo '</div>';
echo '</div>';
echo '</div>';

include('../common/includes/db_close.php');

// Close the container divs opened by print_header_and_footer
echo '</div>' . "\n"; // col-12
echo '</div>' . "\n"; // row
echo '</div>' . "\n"; // container-fluid

print_footer_and_html_epilogue();
?>