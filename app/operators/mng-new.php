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
    $operator_id = $_SESSION['operator_id'];

    include('../common/includes/config_read.php');
    include('library/check_operator_perm.php');

    include_once("lang/main.php");
    include("../common/includes/validation.php");
    include("../common/includes/layout.php");
    include_once("include/management/functions.php");
    include_once("library/agent_functions.php");

    // init logging variables
    $log = "visited page: ";
    $logAction = "";
    $logDebugSQL = "";

    // if cleartext passwords are not allowed,
    // we remove Cleartext-Password from the $valid_passwordTypes array
    if (isset($configValues['CONFIG_DB_PASSWORD_ENCRYPTION']) &&
        strtolower(trim($configValues['CONFIG_DB_PASSWORD_ENCRYPTION'])) !== 'yes') {
        $valid_passwordTypes = array_values(array_diff($valid_passwordTypes, array("Cleartext-Password")));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (array_key_exists('csrf_token', $_POST) && isset($_POST['csrf_token']) && dalo_check_csrf_token($_POST['csrf_token'])) {

            // required later
            $current_datetime = date('Y-m-d H:i:s');
            $currBy = $operator;

            // TODO validate user input
            $username = (array_key_exists('username', $_POST) && !empty(str_replace("%", "", trim($_POST['username']))))
                      ? str_replace("%", "", trim($_POST['username'])) : "";
            $username_enc = (!empty($username)) ? htmlspecialchars($username, ENT_QUOTES, 'UTF-8') : "";

            $authType = (array_key_exists('authType', $_POST) && isset($_POST['authType']) &&
                         in_array($_POST['authType'], array_keys($valid_authTypes))) ? $_POST['authType'] : array_keys($valid_authTypes)[0];

            $password = (array_key_exists('password', $_POST) && isset($_POST['password'])) ? $_POST['password'] : "";

            $passwordType = (array_key_exists('passwordType', $_POST) && !empty(trim($_POST['passwordType'])) &&
                             in_array(trim($_POST['passwordType']), $valid_passwordTypes)) ? trim($_POST['passwordType']) : $valid_passwordTypes[0];

            $macaddress = (isset($_POST['macaddress']) && !empty(trim($_POST['macaddress'])) &&
                           preg_match(MACADDR_REGEX, trim($_POST['macaddress']))) ? trim($_POST['macaddress']) : "";

            $pincode = (array_key_exists('pincode', $_POST) && isset($_POST['pincode'])) ? trim($_POST['pincode']) : "";

            // this can be used for all authTypes
            $groups = (array_key_exists('groups', $_POST) && isset($_POST['groups'])) ? $_POST['groups'] : array();

            // user info variables
            $firstname = (array_key_exists('firstname', $_POST) && isset($_POST['firstname'])) ? $_POST['firstname'] : "";
            $lastname = (array_key_exists('lastname', $_POST) && isset($_POST['lastname'])) ? $_POST['lastname'] : "";
            $email = (array_key_exists('email', $_POST) && isset($_POST['email'])) ? $_POST['email'] : "";
            $department = (array_key_exists('department', $_POST) && isset($_POST['department'])) ? $_POST['department'] : "";
            $company = (array_key_exists('company', $_POST) && isset($_POST['company'])) ? $_POST['company'] : "";
            $workphone = (array_key_exists('workphone', $_POST) && isset($_POST['workphone'])) ? $_POST['workphone'] : "";
            $homephone = (array_key_exists('homephone', $_POST) && isset($_POST['homephone'])) ? $_POST['homephone'] : "";
            $mobilephone = (array_key_exists('mobilephone', $_POST) && isset($_POST['mobilephone'])) ? $_POST['mobilephone'] : "";
            $address = (array_key_exists('address', $_POST) && isset($_POST['address'])) ? $_POST['address'] : "";
            $city = (array_key_exists('city', $_POST) && isset($_POST['city'])) ? $_POST['city'] : "";
            $state = (array_key_exists('state', $_POST) && isset($_POST['state'])) ? $_POST['state'] : "";
            $country = (array_key_exists('country', $_POST) && isset($_POST['country'])) ? $_POST['country'] : "";
            $zip = (array_key_exists('zip', $_POST) && isset($_POST['zip'])) ? $_POST['zip'] : "";
            $notes = (isset($_POST['notes']) && !empty(trim($_POST['notes']))) ? trim($_POST['notes']) : "";

            // first we check user portal login password
            $ui_PortalLoginPassword = (isset($_POST['portalLoginPassword']) && !empty(trim($_POST['portalLoginPassword'])))
                                    ? trim($_POST['portalLoginPassword']) : "";

            // these are forced to 0 (disabled) if user portal login password is empty
            $ui_changeuserinfo = (!empty($ui_PortalLoginPassword) && isset($_POST['changeUserInfo']) && $_POST['changeUserInfo'] === '1')
                               ? '1' : '0';
            $ui_enableUserPortalLogin = (!empty($ui_PortalLoginPassword) && isset($_POST['enableUserPortalLogin']) && $_POST['enableUserPortalLogin'] === '1')
                                      ? '1' : '0';

            isset($_POST['dictAttributes']) ? $dictAttributes = $_POST['dictAttributes'] : $dictAttributes = "";

            include('../common/includes/db_open.php');
            
            // Default to agent id 1, and add current agent if operator is an agent
            $selected_agents = array(1); // Default to agent id 1
            $current_agent_id = getCurrentOperatorAgentId($dbSocket, $operator_id, $configValues);
            if ($current_agent_id && $current_agent_id != 1) {
                $selected_agents[] = $current_agent_id; // Add agent's own id if different from 1
            }
            // Merge with form selections
            $form_agents = (array_key_exists('selected_agents', $_POST) && isset($_POST['selected_agents'])) ? $_POST['selected_agents'] : array();
            $selected_agents = array_unique(array_merge($selected_agents, $form_agents));

            // we will have a $username_to_check, only
            // if required arguments have been supplied
            // according to the chosen $authType
            $username_to_check = "";

            if ($authType == "userAuth") {
                // we can add a new record to the check table
                // only if $username and $password are not empty
                if (!empty($username) && !empty($password)) {
                    $username_to_check = $username;
                } else {
                    $failureMsg = "Username and/or password are invalid";

                }
            } else if ($authType == "macAuth") {
                if (!empty($macaddress)) {
                    $username_to_check = $macaddress;
                } else {
                    $failureMsg = "MAC address is invalid";
                }
            } else if ($authType == "pincodeAuth") {
                if (!empty($pincode)) {
                    $username_to_check = $pincode;
                } else {
                    $failureMsg = "PIN code is invalid";
                }
            } else {
                // authentication method is invalid
                $failureMsg = "Unknown authentication method";
            }

            if (empty($username_to_check)) {
                // failure message has been set above
                $logAction .= "Failed adding a new user ($failureMsg) on page: ";

            } else {

                // we can proceed and check if username/mac address/pincode is already present in the radcheck table
                $exists = user_exists($dbSocket, $username_to_check);

                // we proceed only if username/mac address/pincode is not present
                if ($exists) {
                    // user exists
                    $failureMsg = sprintf("record already found in database: <strong>%s</strong>",
                                          htmlspecialchars($username_to_check, ENT_QUOTES, 'UTF-8'));
                    $logAction .= "Failed adding new user already existing in database [$username_to_check] on page: ";
                } else {

                    if ($authType == "userAuth") {
                        // we prepare a password attribute for the "injection" (see below)
                        // and the success/log messages

                        $attribute = $passwordType;
                        $value = $password;

                        $u = $username;
                        $what = "user";

                    } else if ($authType == "macAuth" || $authType == "pincodeAuth") {
                        // we prepare an auth attribute for the "injection" (see below)
                        // and the success/log messages

                        $attribute = 'Auth-Type';
                        $value = 'Accept';

                        if ($authType == "macAuth") {
                            $u = $macaddress;
                            $what = "MAC address";

                        } else {
                            $u = $pincode;
                            $what = "PIN code";
                        }

                    }

                    // we "inject" the prepared password/auth attribute in the $_POST array.
                    // handleAttributes() - called later - will take care of it.
                    $_POST['injected_attribute'] = array( $attribute, $value, ':=', 'check' );

                    include("library/attributes.php");

                    $skipList = array( "authType", "username", "password", "passwordType", "groups",
                                       "macaddress", "pincode", "submit", "firstname", "lastname", "email",
                                       "department", "company", "workphone", "homephone", "mobilephone", "address", "city",
                                       "state", "country", "zip", "notes", "selected_agents", "changeUserInfo", "copycontact", 
                                       "portalLoginPassword", "enableUserPortalLogin", "csrf_token", "submit"
                                     );

                    $attributesCount = handleAttributes($dbSocket, $u, $skipList);

                    $groupsCount = insert_multiple_user_group_mappings($dbSocket, $u, $groups);

                    // adding user info
                    $params = array(
                                        "firstname" => $firstname,
                                        "lastname" => $lastname,
                                        "email" => $email,
                                        "department" => $department,
                                        "company" => $company,
                                        "workphone" => $workphone,
                                        "homephone" => $homephone,
                                        "mobilephone" => $mobilephone,
                                        "address" => $address,
                                        "city" => $city,
                                        "state" => $state,
                                        "country" => $country,
                                        "zip" => $zip,
                                        "notes" => $notes,
                                        "changeuserinfo" => $ui_changeuserinfo,
                                        "enableportallogin" => $ui_enableUserPortalLogin,
                                        "portalloginpassword" => $ui_PortalLoginPassword,
                                        "creationdate" => $current_datetime,
                                        "creationby" => $currBy,
                                   );

                    $addedUserInfo = (add_user_info($dbSocket, $u, $params)) ? "stored" : "nothing to store";

                    // adding agent assignments (replaced billing info)
                    $addedAgentInfo = "nothing to store";
                    $agentCount = 0;
                    
                    if (!empty($selected_agents)) {
                        // First get the user_id from userinfo table
                        $sql = sprintf("SELECT id FROM userinfo WHERE username='%s'", 
                                       $dbSocket->escapeSimple($u));
                        $res = $dbSocket->query($sql);
                        $logDebugSQL .= "$sql;\n";
                        
                        if ($res && $row = $res->fetchRow()) {
                            $user_id = $row[0];
                            
                            // Insert agent assignments
                            foreach ($selected_agents as $agent_id) {
                                if (!empty($agent_id)) {
                                    $sql = sprintf("INSERT INTO user_agent (user_id, agent_id) VALUES (%d, %d)",
                                                   intval($user_id), intval($agent_id));
                                    $res_agent = $dbSocket->query($sql);
                                    $logDebugSQL .= "$sql;\n";
                                    
                                    if ($res_agent) {
                                        $agentCount++;
                                    }
                                }
                            }
                            
                            $addedAgentInfo = ($agentCount > 0) ? "stored $agentCount agent(s)" : "no agents assigned";
                        }
                    }

                    $u_enc = htmlspecialchars($u, ENT_QUOTES, 'UTF-8');

                    $successMsg = sprintf('Inserted new <strong>%s</strong>: ', $what)
                                . sprintf('<a href="mng-edit.php?username=%s" title="Edit">%s</a>', $u_enc, $u_enc)
                                . '<ul style="color: black">'
                                . sprintf("<li><strong>attributes count</strong>: %d</li>", $attributesCount)
                                . sprintf("<li><strong>groups count</strong>: %d</li>", $groupsCount)
                                . sprintf("<li><strong>user info</strong>: %s</li>", $addedUserInfo)
                                . sprintf("<li><strong>agent assignments</strong>: %s</li>", $addedAgentInfo)
                                . "</ul>"


                                . "<strong>Welcome notification</strong>: "
                                . '<a target="_blank" href="include/common/notifications.php?action=preview">Preview</a>';

                    if (strtolower($configValues['CONFIG_MAIL_ENABLED']) == "yes") {
                        $successMsg .= ' or <a href="include/common/notifications.php?action=email">Send</a>';
                    }

                    $_SESSION['notification'] = array( 'username' => $username, 'type' => 'user-welcome' );

                    $logAction .= sprintf("Successfully inserted new %s [%s] on page: ", $what, $u);
                }
            }

            include('../common/includes/db_close.php');
        } else {
            // csrf
            $failureMsg = "CSRF token error";
            $logAction .= "$failureMsg on page: ";
        }
    }

    $hiddenPassword = (strtolower($configValues['CONFIG_IFACE_PASSWORD_HIDDEN']) == "yes")
                    ? 'password' : 'text';


    // print HTML prologue
    $extra_css = array();

    $extra_js = array(
        // Ensure Bootstrap is loaded early on this page
        "../common/static/js/bootstrap.bundle.min.js",
        "../common/static/js/ajax.js",
        "../common/static/js/dynamic_attributes.js",
        "../common/static/js/ajaxGeneric.js",
        "../common/static/js/productive_funcs.js",
    );

    $title = t('Intro','mngnew.php');
    $help = t('helpPage','mngnew');

    print_html_prologue($title, $langCode, $extra_css, $extra_js);

    print_title_and_help($title, $help);

    include_once('include/management/actionMessages.php');

    if (!isset($successMsg)) {

        include_once('include/management/populate_selectbox.php');

        $input_descriptors0 = array();

        $input_descriptors0[] = array(
                                        "type" =>"select",
                                        "name" => "authType",
                                        "caption" => "Authentication Type",
                                        "options" => $valid_authTypes,
                                        "onchange" => "switchAuthType()",
                                        "selected_value" => ((isset($failureMsg)) ? $authType : "")
                                     );

        $options = get_groups();
        array_unshift($options, '');
        $input_descriptors0[] = array(
                                        "type" =>"select",
                                        "name" => "groups[]",
                                        "id" => "groups",
                                        "caption" => t('all','Group'),
                                        "options" => $options,
                                        "multiple" => true,
                                        "size" => 5,
                                        "selected_value" => ((isset($failureMsg)) ? $groups : ""),
                                        "tooltipText" => t('Tooltip','groupTooltip')
                                     );


        $input_descriptors1 = array();

        $input_descriptors1[] = array(
                                        "id" => "username",
                                        "name" => "username",
                                        "caption" => t('all','Username'),
                                        "type" => "text",
                                        "random" => true,
                                        "value" => ((isset($failureMsg)) ? $username : ""),
                                        "tooltipText" => t('Tooltip','usernameTooltip')
                                     );

        $input_descriptors1[] = array(
                                        "id" => "password",
                                        "name" => "password",
                                        "caption" => t('all','Password'),
                                        "type" => $hiddenPassword,
                                        "random" => true,
                                        "tooltipText" => t('Tooltip','passwordTooltip')
                                     );
        $input_descriptors1[] = array(
                                        "name" => "passwordType",
                                        "caption" => t('all','PasswordType'),
                                        "options" => $valid_passwordTypes,
                                        "type" => "select",
                                        "selected_value" => ((isset($failureMsg)) ? $passwordType : "")
                                    );


        $input_descriptors2 = array();

        $input_descriptors2[] = array(
                                        "name" => "macaddress",
                                        "caption" => t('all','MACAddress'),
                                        "type" => "text",
                                        "value" => ((isset($failureMsg)) ? $macaddress : ""),
                                        "tooltipText" => t('Tooltip','macaddressTooltip'),
                                        "pattern" => trim(MACADDR_REGEX, "/"),
                                        "title" => "you should provide a valid MAC address"
                                     );


        $input_descriptors3 = array();

        $input_descriptors3[] = array(
                                        "name" => "pincode",
                                        "caption" => t('all','PINCode'),
                                        "type" => "text",
                                        "value" => ((isset($failureMsg)) ? $pincode : ""),
                                        "tooltipText" => t('Tooltip','pincodeTooltip'),
                                        "random" => true,
                                     );

        // fieldset
        $fieldset0_descriptor = array(
                                        "title" => "Common parameters",
                                     );

        $fieldset1_descriptor = array(
                                        "title" => "Username/password info",
                                        "id" => "userAuth-fieldset",
                                     );

        $fieldset2_descriptor = array(
                                        "title" => "MAC Address info",
                                        "id" => "macAuth-fieldset",
                                     );

        $fieldset3_descriptor = array(
                                        "title" => "PIN code info",
                                        "id" => "pincodeAuth-fieldset",
                                     );

        // set navbar stuff
        $navkeys = array( 'AccountInfo', 'UserInfo', 'AgentInfo', 'Userbilling info', 'Attributes' );

        // print navbar controls
        print_tab_header($navkeys);

        open_form();

        // open tab wrapper
        open_tab_wrapper();

        // open 0-th tab (shown)
        open_tab($navkeys, 0, true);

        // open 0-th fieldset
        open_fieldset($fieldset0_descriptor);

        foreach ($input_descriptors0 as $input_descriptor) {
            print_form_component($input_descriptor);
        }

        close_fieldset();

        // open 1-st fieldset
        open_fieldset($fieldset1_descriptor);

        foreach ($input_descriptors1 as $input_descriptor) {
            print_form_component($input_descriptor);
        }

        close_fieldset();

        // open 2-st fieldset
        open_fieldset($fieldset2_descriptor);

        foreach ($input_descriptors2 as $input_descriptor) {
            print_form_component($input_descriptor);
        }

        close_fieldset();


        // open 3-st fieldset
        open_fieldset($fieldset3_descriptor);

        foreach ($input_descriptors3 as $input_descriptor) {
            print_form_component($input_descriptor);
        }

        close_fieldset();

        close_tab();


        //~ $customApplyButton = sprintf('<input type="submit" name="submit" value="%s" class="button">', t('buttons','apply'));

        // open 1-th tab (shown)
        open_tab($navkeys, 1);
        include_once('include/management/userinfo.php');
        close_tab($navkeys, 1);

        // open 2-nd tab (AgentInfo)
        open_tab($navkeys, 2);
        // ensure DB connection exists for the included section
        include('../common/includes/db_open.php');
        include_once('include/management/agentInfo.php');
        include('../common/includes/db_close.php');
        close_tab($navkeys, 2);

   // open 3-th tab (shown)
   open_tab($navkeys, 3);
   // ensure DB connection exists for the included section
   include('../common/includes/db_open.php');
   include_once('include/management/userbillinfo.php');
   include('../common/includes/db_close.php');
   close_tab($navkeys, 3);

        // open 4-th tab (shown)
        open_tab($navkeys, 4);
        include_once('include/management/attributes.php');
        close_tab($navkeys, 4);

        // close tab wrapper
        close_tab_wrapper();

        $input_descriptors4 = array();
        $input_descriptors4[] = array(
                                        "name" => "csrf_token",
                                        "type" => "hidden",
                                        "value" => dalo_csrf_token(),
                                     );

        $input_descriptors4[] = array(
                                        'type' => 'submit',
                                        'name' => 'submit',
                                        'value' => t('buttons','apply')
                                     );

        foreach ($input_descriptors4 as $input_descriptor) {
            print_form_component($input_descriptor);
        }

        close_form();

    }

    print_back_to_previous_page();

    include('include/config/logging.php');

	$inline_extra_js = '
	function switchAuthType() {
		var switcher = document.getElementById("authType");

		for (var i=0; i<switcher.length; i++) {
			var fieldset_id = switcher[i].value + "-fieldset",
				disabled = switcher.value != switcher[i].value,
				fieldset = document.getElementById(fieldset_id);

			fieldset.disabled = disabled;
			fieldset.style.display = (disabled) ? "none" : "block";
		}
	}

	// Local fallback for tab switching if Bootstrap is not available
	if (typeof window.openTab !== "function") {
		window.openTab = function(event, tabId) {
			if (event && typeof event.preventDefault === "function") { event.preventDefault(); }
			var links = document.querySelectorAll("button.nav-link");
			for (var i = 0; i < links.length; i++) {
				links[i].classList.remove("active");
				links[i].setAttribute("aria-selected", "false");
			}
			var panes = document.querySelectorAll(".tab-pane");
			for (var j = 0; j < panes.length; j++) {
				panes[j].classList.remove("show");
				panes[j].classList.remove("active");
			}
			var pane = document.getElementById(tabId);
			if (pane) { pane.classList.add("show"); pane.classList.add("active"); }
			var btnId = tabId.replace(/-tab$/, "-button");
			var btn = document.getElementById(btnId);
			if (btn) { btn.classList.add("active"); btn.setAttribute("aria-selected", "true"); }
		};
	}

	window.addEventListener("load", function() { switchAuthType(); });
	';

    print_footer_and_html_epilogue($inline_extra_js);
?>
