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

    include('../common/includes/config_read.php');
    include('library/check_operator_perm.php');

    include_once("lang/main.php");
    include_once("../common/includes/validation.php");
    include("../common/includes/layout.php");
    include("include/management/functions.php");
    include("library/extensions/maintenance_radclient.php");

    // init logging variables
    $log = "visited page: ";
    $logAction = "";
    $logDebugSQL = "";


    $valid_packetTypes = array(
                                    "disconnect" => 'PoD - Packet of Disconnect',
                                    "coa" => 'CoA - Change of Authorization'
                              );

    // Handle URL parameters from rep-online.php
    $username_param = (array_key_exists('username', $_GET) && !empty(trim($_GET['username'])))
                    ? trim($_GET['username']) : "";
    $nasaddr_param = (array_key_exists('nasaddr', $_GET) && !empty(trim($_GET['nasaddr'])))
                   ? trim($_GET['nasaddr']) : "";
    $customattributes_param = (array_key_exists('customattributes', $_GET) && !empty(trim($_GET['customattributes'])))
                            ? trim($_GET['customattributes']) : "";

    include('../common/includes/db_open.php');

    $sql = sprintf("SELECT DISTINCT(nasname), ports, shortname, CONCAT('nas-', id) FROM %s ORDER BY nasname ASC",
                   $configValues['CONFIG_DB_TBL_RADNAS']);
    $res = $dbSocket->query($sql);

    $valid_nas_ids = array();
    $nas_ip_to_id = array(); // Map NAS IP to NAS ID for auto-selection
    while ($row = $res->fetchRow()) {
        $value = $row[3];
        $label = sprintf("%s (%s:%d)", $row[2], $row[0], intval($row[1]));
        $valid_nas_ids[$value] = $label;
        $nas_ip_to_id[$row[0]] = $value; // Map nasname (IP) to nas-id
    }

    include('../common/includes/db_close.php');

    $radclient_path = is_radclient_present();

    if ($radclient_path !== false) {

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            if (array_key_exists('csrf_token', $_POST) && isset($_POST['csrf_token']) && dalo_check_csrf_token($_POST['csrf_token'])) {
                $required_fields = array();

                $username = (isset($_POST['username']) && !empty(trim($_POST['username']))) ? trim($_POST['username']) : "";
                if (empty($username)) {
                    $required_fields['username'] = t('all','Username');
                }

                $nas_id = (isset($_POST['nas_id']) && in_array(trim($_POST['nas_id']), array_keys($valid_nas_ids)))
                        ? trim($_POST['nas_id']) : "";
                if (empty($nas_id)) {
                    $required_fields['nas_id'] = t('all','NasIPHost');
                }

                if (count($required_fields) > 0) {
                    // required/invalid
                    $failureMsg = sprintf("Empty or invalid required field(s) [%s]", implode(", ", array_values($required_fields)));
                    $logAction .= "$failureMsg on page: ";
                } else {

                    $packetType = (isset($_POST['packetType']) && in_array(trim($_POST['packetType']), array_keys($valid_packetTypes)))
                                ? trim($_POST['packetType']) : "disconnect"; // Default to PoD
                    $customAttributes = (array_key_exists('customAttributes', $_POST) && !empty(trim($_POST['customAttributes'])))
                                      ? trim($_POST['customAttributes']) : "";

                    $debug = (isset($_POST['debug']) && in_array($_POST['debug'], array("yes", "no"))) ? $_POST['debug'] : "no";
                    $timeout = (isset($_POST['timeout']) && intval($_POST['timeout']) > 0) ? intval($_POST['timeout']) : 3;
                    $retries = (isset($_POST['retries']) && intval($_POST['retries']) > 0) ? intval($_POST['retries']) : 3;
                    $count = (isset($_POST['count']) && intval($_POST['count']) > 0) ? intval($_POST['count']) : 1;
                    $requests = (isset($_POST['requests']) && intval($_POST['requests']) > 0) ? intval($_POST['requests']) : 1;

                    $simulate = (isset($_POST['simulate']) && $_POST['simulate'] === "on");

                    // this will be passed to user_auth function
                    $params =  array(
                                        "nas_id" => intval(str_replace("nas-", "", $nas_id)),
                                        "username" => $username,
                                        "count" => $count,
                                        "requests" => $requests,
                                        "retries" => $retries,
                                        "timeout" => $timeout,
                                        "debug" => ($debug == "yes"),
                                        "command" => $packetType,
                                        "customAttributes" => $customAttributes,
                                        "simulate" => $simulate,
                                    );

                    // disconnect user
                    $result = user_disconnect($params);

                    $username_enc = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

                    if ($result["error"]) {
                        if (!empty($failureMsg)) {
                            $failureMsg .= str_repeat("<br>", 2);
                        }

                        $failureMsg = sprintf("Cannot perform disconnect action on user [<strong>%s</strong>, reason: <strong>%s</strong>]",
                                              $username_enc, $result["output"]);
                        $logAction .= sprintf("Cannot perform disconnect action on user [%s, reason: %s] on page: ",
                                              $username, $result["output"]);
                    } else {
                        if (!empty($successMsg)) {
                            $successMsg .= str_repeat("<br>", 2);
                        }

                        $successMsg = sprintf('Performed disconnect action on user <strong>%s</strong>.'
                                            . '<pre class="font-monospace my-1">%s</pre>',
                                              $username_enc, $result["output"]);
                        $logAction .= sprintf("Performed disconnect action on user [%s] on page: ",
                                              $username, $result["output"]);
                    }
                }

            } else {
                // csrf
                $failureMsg = "CSRF token error";
                $logAction .= "$failureMsg on page: ";
            }
        }

    } else {
        $failureMsg = "Cannot perform disconnect action [radclient binary not found on the system]";
        $logAction .= "$failureMsg on page: ";
    }


    // print HTML prologue
    $title = t('Intro','configmaintdisconnectuser.php');
    $help = t('helpPage','configmaintdisconnectuser');

    print_html_prologue($title, $langCode);

    print_title_and_help($title, $help);

    include_once('include/management/actionMessages.php');

    if ($radclient_path !== false) {

        include("include/management/populate_selectbox.php");
        include('../common/includes/db_open.php');

        // Get online users with their data
        $users_data = get_online_users_with_data();
        $options = array_keys($users_data);
        array_unshift($options, "");

        // Determine selected username (from URL param or POST)
        $selected_username = "";
        if (!empty($username_param)) {
            $selected_username = $username_param;
        } elseif (isset($username)) {
            $selected_username = $username;
        }

        // Auto-select NAS based on username
        $selected_nas_id = "";
        if (!empty($selected_username) && isset($users_data[$selected_username])) {
            $user_nasip = $users_data[$selected_username]['nasip'];
            if (isset($nas_ip_to_id[$user_nasip])) {
                $selected_nas_id = $nas_ip_to_id[$user_nasip];
            }
        } elseif (!empty($nasaddr_param) && isset($nas_ip_to_id[$nasaddr_param])) {
            $selected_nas_id = $nas_ip_to_id[$nasaddr_param];
        }

        include('../common/includes/db_close.php');

        $input_descriptors0 = array();
        $input_descriptors0[] = array(
                                        "name" => "username",
                                        "caption" => t('all','Username'),
                                        "type" => "select",
                                        "options" => $options,
                                        "selected_value" => $selected_username,
                                     );

        $options = $valid_packetTypes;
        // Don't add empty option, we want a default selection

        $input_descriptors0[] = array(
                                        "name" => "packetType",
                                        "caption" => t('all','PacketType'),
                                        "type" => "select",
                                        "options" => $options,
                                        "selected_value" => ((isset($packetType)) ? $packetType : "disconnect"), // Default to PoD
                                     );

        $options = $valid_nas_ids;
        array_unshift($options, "");

        // Determine final NAS selection
        $final_nas_selection = "";
        if (isset($nas_id)) {
            $final_nas_selection = $nas_id;
        } elseif (!empty($selected_nas_id)) {
            $final_nas_selection = $selected_nas_id;
        }

        $input_descriptors0[] = array(
                                        "name" => "nas_id",
                                        "caption" => t('all','NasIPHost'),
                                        "type" => "select",
                                        "options" => $options,
                                        "selected_value" => $final_nas_selection,
                                     );

        // Determine custom attributes - prioritize URL param, then POST, then auto-generate from user data
        $custom_attributes_content = "";
        if (!empty($customattributes_param)) {
            $custom_attributes_content = $customattributes_param;
        } elseif (isset($customAttributes)) {
            $custom_attributes_content = $customAttributes;
        } elseif (!empty($selected_username) && isset($users_data[$selected_username])) {
            // Auto-generate custom attributes with private IP only
            $user_data = $users_data[$selected_username];
            $custom_attributes_content = sprintf("Framed-IP-Address = %s", $user_data['framedip']);
        }

        $input_descriptors0[] = array( "name" => "customAttributes", "caption" => t('all','customAttributes'),
                                       "type" => "textarea", "content" => $custom_attributes_content,
                                     );

        $input_descriptors0[] = array(
                                        "name" => "simulate",
                                        "caption" => "Simulate (only show command, don't execute)",
                                        "type" => "checkbox",
                                        "checked" => (isset($simulate) ? $simulate : false),
                                     );

        // descriptors 1
        $input_descriptors1 = array();
        $input_descriptors1[] = array( "name" => "debug", "caption" => t('all','Debug'), "type" => "select", "options" => array("yes", "no"), );
        $input_descriptors1[] = array( "name" => "timeout", "caption" => t('all','Timeout'), "type" => "number", "value" => "3", "min" => "1", );
        $input_descriptors1[] = array( "name" => "retries", "caption" => t('all','Retries'), "type" => "number", "value" => "3", "min" => "0", );
        $input_descriptors1[] = array( "name" => "count", "caption" => t('all','Count'), "type" => "number", "value" => "1", "min" => "1", );
        $input_descriptors1[] = array( "name" => "requests", "caption" => t('all','Requests'), "type" => "number", "value" => "3", "min" => "1", );

        // descriptors 2
        $input_descriptors2 = array();

        $input_descriptors2[] = array(
                                        "name" => "csrf_token",
                                        "type" => "hidden",
                                        "value" => dalo_csrf_token(),
                                     );

        $input_descriptors2[] = array(
                                        "type" => "submit",
                                        "name" => "submit",
                                        "value" => t('all','TestUser'),
                                     );

        // set navbar stuff
        $navkeys = array( 'Settings', 'Advanced', );

        // print navbar controls
        print_tab_header($navkeys);

        // open form
        open_form();

        // open tab wrapper
        open_tab_wrapper();

        // open tab 0 (shown)
        open_tab($navkeys, 0, true);

        // open a fieldset
        $fieldset0_descriptor = array(
                                        "title" => "Disconnect User",
                                     );

        open_fieldset($fieldset0_descriptor);

        foreach ($input_descriptors0 as $input_descriptor) {
            print_form_component($input_descriptor);
        }

        close_fieldset();

        close_tab($navkeys, 0);

        // open tab 1
        open_tab($navkeys, 1);

        // open a fieldset
        $fieldset1_descriptor = array(
                                        "title" => t('title','Advanced'),
                                     );

        open_fieldset($fieldset1_descriptor);

        foreach ($input_descriptors1 as $input_descriptor) {
            print_form_component($input_descriptor);
        }

        close_fieldset();

        close_tab($navkeys, 1);

        // close tab wrapper
        close_tab_wrapper();

        foreach ($input_descriptors2 as $input_descriptor) {
            print_form_component($input_descriptor);
        }

        close_form();

        // Add JavaScript for dynamic functionality
        echo '<script type="text/javascript">';
        echo 'var usersData = ' . json_encode($users_data) . ';';
        echo 'var nasIpToId = ' . json_encode($nas_ip_to_id) . ';';
        echo '
        document.addEventListener("DOMContentLoaded", function() {
            var usernameSelect = document.querySelector("select[name=\'username\']");
            var nasSelect = document.querySelector("select[name=\'nas_id\']");
            var customAttributesTextarea = document.querySelector("textarea[name=\'customAttributes\']");
            
            // Make username select searchable
            if (usernameSelect) {
                // Add search functionality to username select
                var searchInput = document.createElement("input");
                searchInput.type = "text";
                searchInput.placeholder = "Search users...";
                searchInput.className = "form-control";
                searchInput.style.marginBottom = "5px";
                
                usernameSelect.parentNode.insertBefore(searchInput, usernameSelect);
                
                var originalOptions = Array.from(usernameSelect.options);
                
                searchInput.addEventListener("input", function() {
                    var searchTerm = this.value.toLowerCase();
                    var selectedValue = usernameSelect.value; // Preserve selection
                    usernameSelect.innerHTML = "";
                    
                    originalOptions.forEach(function(option) {
                        if (option.value === "" || option.text.toLowerCase().includes(searchTerm)) {
                            var newOption = option.cloneNode(true);
                            usernameSelect.appendChild(newOption);
                        }
                    });
                    
                    // Restore selection if it still exists
                    if (usernameSelect.querySelector("option[value=\'" + selectedValue + "\']")) {
                        usernameSelect.value = selectedValue;
                    }
                });
                
                // Handle username selection change
                usernameSelect.addEventListener("change", function() {
                    var selectedUsername = this.value;
                    
                    if (selectedUsername && usersData[selectedUsername]) {
                        var userData = usersData[selectedUsername];
                        
                        // Auto-select NAS
                        if (userData.nasip && nasIpToId[userData.nasip] && nasSelect) {
                            nasSelect.value = nasIpToId[userData.nasip];
                        }
                        
                        // Update custom attributes with private IP only
                        if (customAttributesTextarea) {
                            var customAttrs = "Framed-IP-Address = " + userData.framedip;
                            customAttributesTextarea.value = customAttrs;
                        }
                    } else if (!selectedUsername) {
                        // Clear fields when no user is selected
                        if (nasSelect) nasSelect.value = "";
                        if (customAttributesTextarea) customAttributesTextarea.value = "";
                    }
                });
                
                // Trigger change event if a user is already selected (from URL params)
                if (usernameSelect.value) {
                    usernameSelect.dispatchEvent(new Event("change"));
                }
            }
        });
        ';
        echo '</script>';

    }

    include('include/config/logging.php');

    print_footer_and_html_epilogue();
?>
