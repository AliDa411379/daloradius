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
include_once("include/management/functions.php");

// init logging variables
$log = "visited page: ";

// print HTML prologue
$title = t('Intro','mntagents.php');
$help = t('helpPage','mntagents');

print_html_prologue($title, $langCode);

include("../common/includes/db_open.php");

// get agent statistics
$total_agents = count_agents($dbSocket);

$sql = sprintf("SELECT COUNT(DISTINCT ua.agent_id) FROM user_agent ua");
$res = $dbSocket->query($sql);
$assigned_agents = $res->fetchRow()[0];

$unassigned_agents = $total_agents - $assigned_agents;

include('../common/includes/db_close.php');

print_title_and_help($title, $help);

?>

<div class="container-fluid">
    <div class="row">
        <!-- Agent Statistics -->
        <div class="col-md-12 mb-4">
            <div class="row">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo $total_agents; ?></h4>
                                    <p class="card-text">Total Agents</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-people-fill" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo $assigned_agents; ?></h4>
                                    <p class="card-text">Assigned Agents</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-person-check-fill" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo $unassigned_agents; ?></h4>
                                    <p class="card-text">Unassigned Agents</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-person-dash-fill" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title">
                                        <a href="mng-agent-new.php" class="text-white text-decoration-none">
                                            <i class="bi bi-plus-circle me-1"></i>Add New
                                        </a>
                                    </h4>
                                    <p class="card-text">Create Agent</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-person-plus-fill" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-md-12 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-lightning-charge me-2"></i>Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="mng-agent-new.php" class="btn btn-success btn-lg w-100">
                                <i class="bi bi-person-plus me-2"></i>
                                <div>Add New Agent</div>
                                <small class="d-block">Create a new agent profile</small>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="mng-agents-list.php" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-list-ul me-2"></i>
                                <div>List All Agents</div>
                                <small class="d-block">View and manage all agents</small>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="mng-list-all.php" class="btn btn-info btn-lg w-100">
                                <i class="bi bi-people me-2"></i>
                                <div>User-Agent View</div>
                                <small class="d-block">See user-agent assignments</small>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="#" class="btn btn-secondary btn-lg w-100 disabled">
                                <i class="bi bi-graph-up me-2"></i>
                                <div>Agent Reports</div>
                                <small class="d-block">View agent statistics</small>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Getting Started -->
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>Getting Started with Agent Management
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-question-circle me-1"></i>What are Agents?</h6>
                            <p class="text-muted">
                                Agents are representatives or support staff that can be assigned to users. 
                                This helps organize customer support and account management by associating 
                                specific agents with specific users.
                            </p>
                            
                            <h6><i class="bi bi-gear me-1"></i>How to Use:</h6>
                            <ol class="text-muted">
                                <li>Create agents using the "Add New Agent" button</li>
                                <li>Assign agents to users in the user edit form (AgentInfo tab)</li>
                                <li>View assignments in the user list or agent reports</li>
                                <li>Manage agent information through the agent list</li>
                            </ol>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bi bi-lightbulb me-1"></i>Tips:</h6>
                            <ul class="text-muted">
                                <li>Use descriptive agent names and companies</li>
                                <li>Keep contact information up to date</li>
                                <li>Multiple agents can be assigned to one user</li>
                                <li>Use the search function in user edit to find agents quickly</li>
                                <li>Check the "Assigned Users" tab when editing agents</li>
                            </ul>
                            
                            <div class="mt-3">
                                <a href="help-main.php" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-question-circle me-1"></i>More Help
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include('include/config/logging.php');
print_footer_and_html_epilogue();
?>