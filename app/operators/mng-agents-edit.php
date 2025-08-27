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

    $agent_id = (array_key_exists('agent_id', $_GET) && isset($_GET['agent_id']) && 
                 intval(trim($_GET['agent_id'])) > 0) ? intval(trim($_GET['agent_id'])) : 0;
    $agent_id_enc = ($agent_id > 0) ? htmlspecialchars($agent_id, ENT_QUOTES, 'UTF-8') : "";

    if ($agent_id <= 0) {
        $failureMsg = "No agent ID provided";
        $logAction .= "Failed editing agent (no agent ID provided) on page: ";
    } else {
        
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
                    $logAction .= "Failed updating agent (missing agent name) on page: ";
                } elseif ($agent_id <= 0) {
                    $failureMsg = "Invalid agent ID";
                    $logAction .= "Failed updating agent (invalid agent ID) on page: ";
                } else {
					include('../common/includes/db_open.php');
					
					// check if agent name already exists (excluding current agent)
					$sql = sprintf("SELECT COUNT(*) FROM %s WHERE name = '%s' AND id != %d AND is_deleted = 0",
								   $configValues['CONFIG_DB_TBL_DALOAGENTS'],
								   $dbSocket->escapeSimple($agent_name),
								   intval($agent_id));
					$res = $dbSocket->query($sql);
					$logDebugSQL .= "$sql;\n";
					
					if (DB::isError($res)) {
						$failureMsg = "Database query failed: " . $res->getMessage();
						$logAction .= "Database query failed on page: ";
					} else {
						list($numrows) = $res->fetchRow();
						if (intval($numrows) > 0) {
							$failureMsg = "Agent with this name already exists (found $numrows duplicates)";
							$logAction .= "Failed updating agent (agent name already exists) on page: ";
						} else {
								// update agent
								$sql = sprintf("UPDATE %s SET name = '%s', company = '%s', phone = '%s', email = '%s', address = '%s', city = '%s', country = '%s' WHERE id = %d",
											   $configValues['CONFIG_DB_TBL_DALOAGENTS'],
											   $dbSocket->escapeSimple($agent_name),
											   $dbSocket->escapeSimple($company),
											   $dbSocket->escapeSimple($phone),
											   $dbSocket->escapeSimple($email),
											   $dbSocket->escapeSimple($address),
											   $dbSocket->escapeSimple($city),
											   $dbSocket->escapeSimple($country),
											   intval($agent_id));
								$res2 = $dbSocket->query($sql);
								$logDebugSQL .= "$sql;\n";
								
								if (DB::isError($res2)) {
									$failureMsg = "Error updating agent: " . $res2->getMessage();
									$logAction .= "Failed updating agent [$agent_name] on page: ";
								} else {
										// Update ACL permissions if provided
										$acl_updated = false;
										
										// Find the operator ID associated with this agent
										$operator_id = null;
										
										// First try to get operator_id directly from the agent record
										$sql_op = sprintf("SELECT operator_id FROM %s WHERE id = %d", 
														   $configValues['CONFIG_DB_TBL_DALOAGENTS'],
														   intval($agent_id));
										$res_op = $dbSocket->query($sql_op);
										if ($res_op && $row_op = $res_op->fetchRow()) {
											$operator_id = $row_op[0];
										}
										
										// If no direct link, try to find by agent marker and company/name match
										if (empty($operator_id)) {
											$sql_op = sprintf("SELECT id FROM %s WHERE is_agent=1 AND (company='%s' OR firstname='%s' OR lastname='%s') LIMIT 1", 
															   $configValues['CONFIG_DB_TBL_DALOOPERATORS'],
															   $dbSocket->escapeSimple($company),
															   $dbSocket->escapeSimple($agent_name),
															   $dbSocket->escapeSimple($agent_name));
											$res_op = $dbSocket->query($sql_op);
											if ($res_op && $row_op = $res_op->fetchRow()) {
												$operator_id = $row_op[0];
												
												// Update the agent record with the found operator_id for future use
												$sql_update_op = sprintf("UPDATE %s SET operator_id = %d WHERE id = %d",
																		 $configValues['CONFIG_DB_TBL_DALOAGENTS'],
																		 intval($operator_id),
																		 intval($agent_id));
												$dbSocket->query($sql_update_op);
											}
										}
										
										if ($operator_id) {
											
											// Delete existing ACL entries
											$sql_del = sprintf("DELETE FROM %s WHERE operator_id=%d", 
															   $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'], $operator_id);
											$dbSocket->query($sql_del);
											
											// Insert new ACL entries
											$sql0 = sprintf("INSERT INTO %s (operator_id, file, access) VALUES ",
															$configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL']);
											$sql_piece_format = sprintf("(%s", $operator_id) . ", '%s', '%s')";
											$sql_pieces = array();
											
											foreach ($_POST as $field => $access) {
												if (!preg_match('/^ACL_/', $field)) { 
													continue;
												}
												
												$file = substr($field, 4);
												$sql_pieces[] = sprintf($sql_piece_format, $dbSocket->escapeSimple($file),
																						   $dbSocket->escapeSimple($access));
											}
											
											if (count($sql_pieces) > 0) {
												$sql_acl = $sql0 . implode(", ", $sql_pieces);
												$res_acl = $dbSocket->query($sql_acl);
												if (!DB::isError($res_acl)) {
													$acl_updated = true;
												}
											}
										}
										
										$successMsg = "Successfully updated agent: <strong>$agent_name_enc</strong>";
										if ($acl_updated) {
											$successMsg .= " and permissions";
										}
										$logAction .= "Successfully updated agent [$agent_name] on page: ";
								}
							}
						}
                }
                
            } else {
                $failureMsg = "CSRF token validation failed";
                $logAction .= "CSRF token validation failed on page: ";
            }
        }
        
		// Load agent data
		include('../common/includes/db_open.php');
		$sql = sprintf("SELECT name, company, phone, email, address, city, country FROM %s WHERE id = %d AND is_deleted = 0",
					   $configValues['CONFIG_DB_TBL_DALOAGENTS'],
					   intval($agent_id));
		$res = $dbSocket->query($sql);
		$logDebugSQL .= "$sql;\n";
		
		if (DB::isError($res)) {
			$failureMsg = "Database query failed: " . $res->getMessage();
			$logAction .= "Database query failed on page: ";
			$agent_name = $company = $phone = $email = $address = $city = $country = "";
		} else {
			$row = $res->fetchRow();
			if (!$row) {
				$failureMsg = "Agent not found";
				$logAction .= "Failed editing agent (agent not found) on page: ";
				$agent_name = $company = $phone = $email = $address = $city = $country = "";
			} else {
				list($agent_name, $company, $phone, $email, $address, $city, $country) = $row;
			}
		}
		include('../common/includes/db_close.php');
    }

    // print HTML prologue
    $extra_css = array();
    
    $extra_js = array(
        "../common/static/js/ajax.js",
        "../common/static/js/ajaxGeneric.js", 
        "../common/static/js/pages_common.js",
        "../common/static/js/productive_funcs.js",
    );
    
    $title = t('Intro','mngagentsedit.php');
    $help = t('helpPage','mngagentsedit');
    
    print_html_prologue($title, $langCode, $extra_css, $extra_js);
    
    include('../common/includes/db_open.php');
    include('include/management/pages_common.php');
    
    // start printing content
    print_header_and_footer($title, $help, $logDebugSQL, true);
    
    include_once('include/management/actionMessages.php');

    if (!isset($successMsg) && $agent_id > 0) {

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
        
        // set navbar stuff
        $navkeys = array( 
            array( 'AgentInfo', "Agent Information" ), 
            'ContactInfo', 
            array( 'ACLSettings', "Access Control List" ) 
        );

        // print navbar controls
        print_tab_header($navkeys);
        
        open_form();
    
        // open tab wrapper
        open_tab_wrapper();
    
        // tab 0
        open_tab($navkeys, 0, true);
    
        $fieldset0_descriptor = array(
                                        "title" => "Agent Information"
                                     );

        open_fieldset($fieldset0_descriptor);

        foreach ($input_descriptors0 as $input_descriptor) {
            print_form_component($input_descriptor);
        }

        close_fieldset();

        close_tab($navkeys, 0);

        // tab 1 - Contact Information
        open_tab($navkeys, 1);

        $fieldset_contact_descriptor = array(
                                                "title" => "Contact Information"
                                            );

        open_fieldset($fieldset_contact_descriptor);

        // Create separate contact descriptors for better organization
        $contact_descriptors = array();
        
        $contact_descriptors[] = array(
                                        "id" => "phone",
                                        "name" => "phone",
                                        "caption" => t('all','Phone'),
                                        "type" => "text",
                                        "value" => ((isset($phone)) ? $phone : "")
                                     );
                                    
        $contact_descriptors[] = array(
                                        "id" => "email",
                                        "name" => "email",
                                        "caption" => t('all','Email'),
                                        "type" => "email",
                                        "value" => ((isset($email)) ? $email : "")
                                     );
                                    
        $contact_descriptors[] = array(
                                        "id" => "address",
                                        "name" => "address",
                                        "caption" => t('all','Address'),
                                        "type" => "text",
                                        "value" => ((isset($address)) ? $address : "")
                                     );
                                    
        $contact_descriptors[] = array(
                                        "id" => "city",
                                        "name" => "city",
                                        "caption" => t('all','City'),
                                        "type" => "text",
                                        "value" => ((isset($city)) ? $city : "")
                                     );
                                    
        $contact_descriptors[] = array(
                                        "id" => "country",
                                        "name" => "country",
                                        "caption" => t('all','Country'),
                                        "type" => "text",
                                        "value" => ((isset($country)) ? $country : "")
                                     );

        foreach ($contact_descriptors as $input_descriptor) {
            print_form_component($input_descriptor);
        }

        close_fieldset();

        close_tab($navkeys, 1);

        // tab 2 - Access Control List
        open_tab($navkeys, 2);

        // Find the operator ID associated with this agent
        $operator_id = "";
        
        // First try to get operator_id directly from the agent record
        $sql = sprintf("SELECT operator_id FROM %s WHERE id = %d", 
                       $configValues['CONFIG_DB_TBL_DALOAGENTS'],
                       intval($agent_id));
        $res = $dbSocket->query($sql);
        if ($res && $row = $res->fetchRow()) {
            $operator_id = $row[0];
        }
        
        // If no direct link, try to find by agent marker and company/name match
        if (empty($operator_id)) {
            $sql = sprintf("SELECT id FROM %s WHERE is_agent=1 AND (company='%s' OR firstname='%s' OR lastname='%s') LIMIT 1", 
                           $configValues['CONFIG_DB_TBL_DALOOPERATORS'],
                           $dbSocket->escapeSimple($company),
                           $dbSocket->escapeSimple($agent_name),
                           $dbSocket->escapeSimple($agent_name));
            $res = $dbSocket->query($sql);
            if ($res && $row = $res->fetchRow()) {
                $operator_id = $row[0];
                
                // Update the agent record with the found operator_id for future use
                $sql_update = sprintf("UPDATE %s SET operator_id = %d WHERE id = %d",
                                     $configValues['CONFIG_DB_TBL_DALOAGENTS'],
                                     intval($operator_id),
                                     intval($agent_id));
                $dbSocket->query($sql_update);
            }
        }

        if (!empty($operator_id)) {
            include_once('include/management/operator_acls.php');
            drawOperatorACLs($operator_id);
        } else {
            echo '<div class="alert alert-warning">';
            echo '<h5><i class="bi bi-exclamation-triangle me-2"></i>No Operator Account Found</h5>';
            echo '<p>This agent is not linked to an operator account. ACL permissions cannot be managed.</p>';
            echo '<p><strong>To fix this:</strong></p>';
            echo '<ol>';
            echo '<li>Ensure this agent has a corresponding operator account marked with "is_agent = 1"</li>';
            echo '<li>The operator account should have matching company name or email</li>';
            echo '<li>Or manually link the agent to an operator by updating the agent\'s operator_id field</li>';
            echo '</ol>';
            echo '</div>';
        }

        close_tab($navkeys, 2);

        // close tab wrapper
        close_tab_wrapper();

        // CSRF token
        $input_csrf = array(
                                "name" => "csrf_token",
                                "type" => "hidden",
                                "value" => dalo_csrf_token()
                           );
        print_form_component($input_csrf);

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

    }
    
    include('../common/includes/db_close.php');
    
    // Close the container divs opened by print_header_and_footer
    echo '</div>' . "\n"; // col-12
    echo '</div>' . "\n"; // row
    echo '</div>' . "\n"; // container-fluid
    
    include('include/config/logging.php');
    print_footer_and_html_epilogue();
?>