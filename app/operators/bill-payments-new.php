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
 *             Filippo Maria Del Prete <filippo.delprete@gmail.com>
 *             Filippo Lauria <filippo.lauria@iit.cnr.it>
 *
 *********************************************************************************************************
 */
 
    include("library/checklogin.php");
    $operator = $_SESSION['operator_user'];
    $operator_id = $_SESSION['operator_id'];

    include('library/check_operator_perm.php');
    include_once('../common/includes/config_read.php');
    include_once("library/agent_functions.php");

    include_once("lang/main.php");
    include_once("../common/includes/validation.php");
    include("../common/includes/layout.php");
    include_once("include/management/populate_selectbox.php");
    
    // init logging variables
    $log = "visited page: ";
    $logAction = "";
    $logDebugSQL = "";


    include('../common/includes/db_open.php');
    
    // get valid payment types
    $sql = sprintf("SELECT id, value FROM %s", $configValues['CONFIG_DB_TBL_DALOPAYMENTTYPES']);
    $res = $dbSocket->query($sql);
    $logDebugSQL .= "$sql;\n";
    
    $valid_paymentTypes = array( );
    while ($row = $res->fetchrow()) {
        list($id, $value) = $row;
        
        $valid_paymentTypes["paymentType-$id"] = $value;
    }

    
    // Check if current operator is an agent
    $current_agent_id = getCurrentOperatorAgentId($dbSocket, $operator_id, $configValues);
    $is_current_operator_agent = ($current_agent_id !== null);
    
    // Auto-assign agent if operator is an agent, otherwise use form selection
    if ($is_current_operator_agent) {
        $agent_id = $current_agent_id;
    } else {
        $agent_id = (array_key_exists('agent_id', $_REQUEST) && intval(trim($_REQUEST['agent_id'])) > 0)
                  ? intval(trim($_REQUEST['agent_id'])) : "";
    }

    // preload agents for selection (only current agent if operator is an agent)
    $valid_agents = array();
    if ($is_current_operator_agent) {
        // Only show current agent
        $sql = sprintf("SELECT id, name FROM %s WHERE id = %d AND is_deleted = 0", 
                       $configValues['CONFIG_DB_TBL_DALOAGENTS'], $current_agent_id);
    } else {
        // Show all agents for non-agent operators
        $sql = sprintf("SELECT id, name FROM %s WHERE is_deleted = 0 ORDER BY name ASC", 
                       $configValues['CONFIG_DB_TBL_DALOAGENTS']);
    }
    $res = $dbSocket->query($sql);
    $logDebugSQL .= "$sql;\n";
    while ($row = $res->fetchrow()) {
        list($id, $name) = $row;
        $valid_agents[intval($id)] = $name;
    }

    // preload users connected to selected agent (if any)
    $valid_users = array();
    if (!empty($agent_id)) {
        $sql = sprintf("SELECT u.id, u.username FROM %s u INNER JOIN user_agent ua ON ua.user_id=u.id WHERE ua.agent_id=%d ORDER BY u.username ASC",
                       $configValues['CONFIG_DB_TBL_DALOUSERINFO'], intval($agent_id));
        $res = $dbSocket->query($sql);
        $logDebugSQL .= "$sql;\n";
        while ($row = $res->fetchrow()) {
            list($id, $username) = $row;
            $valid_users[intval($id)] = $username;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
        if (array_key_exists('csrf_token', $_POST) && isset($_POST['csrf_token']) && dalo_check_csrf_token($_POST['csrf_token'])) {
        
            // required later
            $current_datetime = date('Y-m-d H:i:s');
            $currBy = $operator;
        
            $required_fields = array();

            // New flow: select user (connected to an agent), system finds the most recent open/partial invoice
            $user_id = (array_key_exists('user_id', $_POST) && intval(trim($_POST['user_id'])) > 0)
                     ? intval(trim($_POST['user_id'])) : "";
            if (empty($user_id)) {
                $required_fields['user_id'] = t('all','UserId');
            }
            
            $payment_type_id = (array_key_exists('payment_type_id', $_POST) && !empty(trim($_POST['payment_type_id'])) &&
                                in_array(trim($_POST['payment_type_id']), array_keys($valid_paymentTypes)))
                             ? intval(str_replace("paymentType-", "", trim($_POST['payment_type_id']))) : "";
            
            $payment_amount = (array_key_exists('payment_amount', $_POST) && is_numeric(trim($_POST['payment_amount'])))
                             ? trim($_POST['payment_amount']) : 0;
            if (empty($payment_amount)) {
                $required_fields['payment_amount'] = t('all','PaymentAmount');
            }
            
            $payment_date = (
                                array_key_exists('payment_date', $_POST) &&
                                !empty(trim($_POST['payment_date'])) &&
                                preg_match(DATE_REGEX, trim($_POST['payment_date']), $m) !== false &&
                                checkdate($m[2], $m[3], $m[1])
                            ) ? trim($_POST['payment_date']) : date('Y-m-d');
            if (empty($payment_date)) {
                $required_fields['payment_date'] = t('all','PaymentDate');
            }
            
            $payment_notes = (array_key_exists('payment_notes', $_POST) && !empty(trim($_POST['payment_notes'])))
                           ? trim($_POST['payment_notes']) : "";
            
            if (count($required_fields) > 0) {
                // required/invalid
                $failureMsg = sprintf("Empty or invalid required field(s) [%s]", implode(", ", array_values($required_fields)));
                $logAction .= "$failureMsg on page: ";
            } else {
                // Determine target invoice for the selected user: prefer most recent OPEN/PARTIAL invoice
                $status_map = get_invoice_status_id(); // id => name
                $open_partial_ids = array();
                foreach ($status_map as $sid => $sname) {
                    $sname_l = strtolower(trim($sname));
                    if (in_array($sname_l, array('open', 'partial'))) {
                        $open_partial_ids[] = intval($sid);
                    }
                }

                $where_status = '';
                if (!empty($open_partial_ids)) {
                    $where_status = sprintf(" AND status_id IN (%s)", implode(",", $open_partial_ids));
                }

                $sql = sprintf("SELECT id FROM %s WHERE user_id=%d%s ORDER BY date DESC LIMIT 1",
                               $configValues['CONFIG_DB_TBL_DALOBILLINGINVOICE'], $user_id, $where_status);
                $res = $dbSocket->query($sql);
                $logDebugSQL .= "$sql;\n";

                $payment_invoice_id = "";
                if ($res && ($row = $res->fetchrow())) {
                    $payment_invoice_id = intval($row[0]);
                }

                if (empty($payment_invoice_id)) {
                    $failureMsg = "No open or partial invoice found for the selected user";
                    $logAction .= "$failureMsg on page: ";
                } else {
                $sql = sprintf("INSERT INTO %s (id, invoice_id, amount, date, type_id, notes,
                                                creationdate, creationby, updatedate, updateby)
                                        VALUES (0, %d, %s, '%s', %d, '%s', '%s', '%s', NULL, NULL)",
                               $configValues['CONFIG_DB_TBL_DALOPAYMENTS'], $payment_invoice_id, $payment_amount,
                               $payment_date, $payment_type_id, $dbSocket->escapeSimple($payment_notes), $current_datetime, $currBy);
                               
                $res = $dbSocket->query($sql);
                $logDebugSQL .= "$sql;\n";
                
                if (!DB::isError($res)) {
                    $successMsg = sprintf("Inserted new payment for user ID: <strong>%d</strong> (invoice #<strong>%d</strong>)<br>", $user_id, $payment_invoice_id)
                                . sprintf('<a href="bill-invoice-edit.php?invoice_id=%d" title="Edit">edit invoice #%d</a>',
                                          $payment_invoice_id, $payment_invoice_id);
                    $logAction .= "Successfully inserted new payment for invoice [#$payment_invoice_id] on page: ";
                } else {
                    $failureMsg = "Failed to insert new payment for invoice: #<strong>$payment_invoice_id</strong>";
                    $logAction .= "Failed to insert new payment for invoice [#$payment_invoice_id] on page: ";
                }
                }
            }

        } else {
            // csrf
            $failureMsg = "CSRF token error";
            $logAction .= "$failureMsg on page: ";
        }
    } else {
        $payment_date = (
                            array_key_exists('payment_date', $_REQUEST) &&
                            !empty(trim($_REQUEST['payment_date'])) &&
                            preg_match(DATE_REGEX, trim($_REQUEST['payment_date']), $m) !== false &&
                            checkdate($m[2], $m[3], $m[1])
                        ) ? trim($_REQUEST['payment_date']) : "";
    }


    include('../common/includes/db_close.php');

    // print HTML prologue   
    $title = t('Intro','paymentsnew.php');
    $help = t('helpPage','paymentsnew');
    
    print_html_prologue($title, $langCode);

    


    print_title_and_help($title, $help);

    include_once('include/management/actionMessages.php');


    if (!isset($successMsg)) {
    
        // descriptors 0
        $input_descriptors0 = array();
        
        // Agent selector (only show for non-agent operators)
        if (!$is_current_operator_agent) {
            $agent_options = array( '' => '' );
            foreach ($valid_agents as $id => $name) { $agent_options[$id] = $name; }
            $input_descriptors0[] = array(
                                            "name" => "agent_id",
                                            "caption" => t('all','Agent'),
                                            "type" => "select",
                                            "options" => $agent_options,
                                            "selected_value" => ((isset($agent_id) && intval($agent_id) > 0) ? $agent_id : ""),
                                            "tooltipText" => 'Filter users by agent'
                                         );
        } else {
            // For agent operators, show read-only agent info
            $current_agent_name = isset($valid_agents[$current_agent_id]) ? $valid_agents[$current_agent_id] : 'Unknown Agent';
            $input_descriptors0[] = array(
                                            "name" => "agent_display",
                                            "caption" => t('all','Agent'),
                                            "type" => "text",
                                            "value" => $current_agent_name,
                                            "readonly" => true,
                                            "tooltipText" => 'Auto-assigned to your agent account'
                                         );
            // Hidden field to maintain agent_id for processing
            $input_descriptors0[] = array(
                                            "name" => "agent_id",
                                            "type" => "hidden",
                                            "value" => $current_agent_id
                                         );
        }

        // User selector (users linked to selected agent)
        $user_options = array( '' => '' );
        foreach ($valid_users as $id => $uname) { $user_options[$id] = $uname; }
        $input_descriptors0[] = array(
                                        "name" => "user_id",
                                        "caption" => t('all','UserId'),
                                        "type" => "select",
                                        "options" => $user_options,
                                        "selected_value" => ((isset($user_id) && intval($user_id) > 0) ? $user_id : ""),
                                        "required" => true,
                                        "tooltipText" => 'Select a user assigned to the chosen agent'
                                     );
        
        $input_descriptors0[] = array(
                                        "name" => "payment_amount",
                                        "caption" => t('all','PaymentAmount'),
                                        "type" => "number",
                                        "value" => ((isset($payment_amount)) ? $payment_amount : ""),
                                        "min" => 0,
                                        "step" => ".01",
                                        "required" => true,
                                        "tooltipText" => t('Tooltip','amountTooltip')
                                     );
        
        $input_descriptors0[] = array(
                                        "name" => "payment_date",
                                        "caption" => t('all','PaymentDate'),
                                        "type" => "date",
                                        "value" => ((!empty($payment_date)) ? $payment_date : date("Y-m-d")),
                                        "required" => true,
                                        "min" => date("1970-m-01")
                                     );
        
        $input_descriptors0[] = array(
                                        "name" => "payment_notes",
                                        "caption" => t('all','PaymentNotes'),
                                        "type" => "textarea",
                                        "content" => ((isset($payment_notes)) ? $payment_notes : ""),
                                        "tooltipText" => t('Tooltip','paymentNotesTooltip')
                                     );
        
        $options = $valid_paymentTypes;
        array_unshift($options , '');
        $input_descriptors0[] = array(
                                        "type" =>"select",
                                        "name" => "payment_type_id",
                                        "caption" => t('all','PaymentType'),
                                        "options" => $options,
                                        "selected_value" => ((isset($payment_type_id) && intval($payment_type_id) > 0) ? "paymentType-$payment_type_id" : ""),
                                        "tooltipText" => t('Tooltip','paymentTypeIdTooltip')
                                     );
        
        // descriptors 1
        $input_descriptors1 = array();

        $input_descriptors1[] = array(
                                        "name" => "csrf_token",
                                        "type" => "hidden",
                                        "value" => dalo_csrf_token(),
                                     );
        
        $input_descriptors1[] = array(
                                        "type" => "submit",
                                        "name" => "submit",
                                        "value" => t('buttons','apply')
                                      );
        
        open_form();

        // AJAX reload of user dropdown when agent changes
        echo '<script>
document.addEventListener("DOMContentLoaded", function() {
  var agentSel = document.querySelector("select[name=\\"agent_id\\"]");
  var userSel = document.querySelector("select[name=\\"user_id\\"]");
  
  if (agentSel && userSel) {
    agentSel.addEventListener("change", function() {
      var agentId = this.value || "";
      
      // Clear user dropdown while loading
      userSel.innerHTML = "<option value=\\"\\">Loading...</option>";
      userSel.disabled = true;
      
      if (!agentId) {
        userSel.innerHTML = "<option value=\\"\\">Select an agent first</option>";
        return;
      }
      
      // Fetch users for selected agent via AJAX
      console.log("Sending agent ID:", agentId); // Debug log
      fetch("library/ajax/get_agent_users.php?agent_id=" + encodeURIComponent(agentId))
        .then(response => response.json())
        .then(data => {
          console.log("AJAX response:", data); // Debug log
          userSel.innerHTML = "<option value=\\"\\">Select User</option>";
          if (data.success && data.users) {
            data.users.forEach(function(user) {
              var option = document.createElement("option");
              option.value = user.id;
              option.textContent = user.username;
              userSel.appendChild(option);
            });
          } else {
            var errorMsg = data.error || "No users found";
            userSel.innerHTML = "<option value=\\"\\">Error: " + errorMsg + "</option>";
          }
          userSel.disabled = false;
        })
        .catch(error => {
          console.error("Error fetching users:", error);
          userSel.innerHTML = "<option value=\\"\\">Error loading users</option>";
          userSel.disabled = false;
        });
    });
  }
});
</script>';
        
        // fieldset 0
        $fieldset0_descriptor = array(
                                        "title" => t('title','PaymentInfo'),
                                     );
                                     
        open_fieldset($fieldset0_descriptor);
        
        foreach ($input_descriptors0 as $input_descriptor) {
            print_form_component($input_descriptor);
        }
        
        close_fieldset();
        
        foreach ($input_descriptors1 as $input_descriptor) {
            print_form_component($input_descriptor);
        }
        
        close_form();
    }
    
    
    print_back_to_previous_page();
    
    include('include/config/logging.php');
    print_footer_and_html_epilogue();

?>
