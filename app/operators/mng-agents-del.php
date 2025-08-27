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
                 intval(trim($_GET['agent_id'])) > 0) ? intval(trim($_GET['agent_id'])) : "";
    $agent_id_enc = (!empty($agent_id)) ? htmlspecialchars($agent_id, ENT_QUOTES, 'UTF-8') : "";

    if (empty($agent_id)) {
        $failureMsg = "No agent ID provided";
        $logAction .= "Failed deleting agent (no agent ID provided) on page: ";
    } else {
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            
            if (array_key_exists('csrf_token', $_POST) && isset($_POST['csrf_token']) && dalo_check_csrf_token($_POST['csrf_token'])) {
            
                include('../common/includes/db_open.php');
                
                // Get agent name for logging (PEAR DB style)
                $sql = "SELECT name FROM " . $configValues['CONFIG_DB_TBL_DALOAGENTS'] . " WHERE id = ? AND is_deleted = 0";
                $stmt = $dbSocket->prepare($sql);
                if (DB::isError($stmt)) {
                    $failureMsg = "Database prepare failed: " . $stmt->getMessage();
                    $logAction .= "Database prepare failed on page: ";
                } else {
                    $res = $dbSocket->execute($stmt, array($agent_id));
                    if (DB::isError($res)) {
                        $failureMsg = "Database execute failed: " . $res->getMessage();
                        $logAction .= "Database execute failed on page: ";
                    } else {
                        $row = $res->fetchRow();
                        if ($row) {
                            $agent_name = $row[0];
                            
                            // Soft delete the agent (mark as deleted)
                            $sql2 = "UPDATE " . $configValues['CONFIG_DB_TBL_DALOAGENTS'] . " SET is_deleted = 1 WHERE id = ?";
                            $stmt2 = $dbSocket->prepare($sql2);
                            if (DB::isError($stmt2)) {
                                $failureMsg = "Database prepare failed: " . $stmt2->getMessage();
                                $logAction .= "Database prepare failed on page: ";
                            } else {
                                $res2 = $dbSocket->execute($stmt2, array($agent_id));
                                if (!DB::isError($res2) && $dbSocket->affectedRows() > 0) {
                                    // Also mark the associated operator as deleted
                                    $sql3 = "UPDATE " . $configValues['CONFIG_DB_TBL_DALOOPERATORS'] . " SET is_deleted = 1 WHERE is_agent = 1 AND (company = ? OR email1 = ?)";
                                    $stmt3 = $dbSocket->prepare($sql3);
                                    if (!DB::isError($stmt3)) {
                                        // Get agent details to match with operator
                                        $sql_agent = "SELECT company, email FROM " . $configValues['CONFIG_DB_TBL_DALOAGENTS'] . " WHERE id = ?";
                                        $stmt_agent = $dbSocket->prepare($sql_agent);
                                        if (!DB::isError($stmt_agent)) {
                                            $res_agent = $dbSocket->execute($stmt_agent, array($agent_id));
                                            if (!DB::isError($res_agent)) {
                                                $agent_row = $res_agent->fetchRow();
                                                if ($agent_row) {
                                                    $agent_company = $agent_row[0];
                                                    $agent_email = $agent_row[1];
                                                    $dbSocket->execute($stmt3, array($agent_company, $agent_email));
                                                }
                                            }
                                        }
                                    }
                                    
                                    $successMsg = "Successfully deleted agent: <strong>" . htmlspecialchars($agent_name, ENT_QUOTES, 'UTF-8') . "</strong>";
                                    $logAction .= "Successfully deleted agent [$agent_name] on page: ";
                                } else {
                                    $failureMsg = "Error deleting agent or agent not found";
                                    $logAction .= "Failed deleting agent [$agent_name] on page: ";
                                }
                            }
                        } else {
                            $failureMsg = "Agent not found";
                            $logAction .= "Failed deleting agent (agent not found) on page: ";
                        }
                    }
                }
                
                include('../common/includes/db_close.php');
                
            } else {
                $failureMsg = "CSRF token validation failed";
                $logAction .= "CSRF token validation failed on page: ";
            }
        }
        
        // Load agent data for confirmation (PEAR DB style)
        if (!isset($successMsg)) {
            include('../common/includes/db_open.php');
            $sql = "SELECT name, company, phone, email, city, country FROM " . $configValues['CONFIG_DB_TBL_DALOAGENTS'] . " WHERE id = ? AND is_deleted = 0";
            $stmt = $dbSocket->prepare($sql);
            if (DB::isError($stmt)) {
                $failureMsg = "Database prepare failed: " . $stmt->getMessage();
                $logAction .= "Database prepare failed on page: ";
                $agent_name = $company = $phone = $email = $city = $country = "";
            } else {
                $res = $dbSocket->execute($stmt, array($agent_id));
                if (DB::isError($res)) {
                    $failureMsg = "Database execute failed: " . $res->getMessage();
                    $logAction .= "Database execute failed on page: ";
                    $agent_name = $company = $phone = $email = $city = $country = "";
                } else {
                    $row = $res->fetchRow();
                    if (!$row) {
                        $failureMsg = "Agent not found";
                        $logAction .= "Failed deleting agent (agent not found) on page: ";
                        $agent_name = $company = $phone = $email = $city = $country = "";
                    } else {
                        list($agent_name, $company, $phone, $email, $city, $country) = $row;
                    }
                }
            }
            include('../common/includes/db_close.php');
        }
    }

    // print HTML prologue
    $title = t('Intro','mngagentsdel.php');
    $help = t('helpPage','mngagentsdel');
    
    print_html_prologue($title, $langCode);
    
    include_once('include/management/actionMessages.php');
    print_title_and_help($title, $help);

    if (!isset($successMsg) && !empty($agent_id) && isset($agent_name)) {
?>

<div id="contentnorightbar">
    <h2 id="Intro"><a href="#" onclick="javascript:toggleShowDiv('helpPage')"><?= t('Intro','mngagentsdel.php') ?></a></h2>
    
    <div id="helpPage" style="display:none">
        <br/>
        <h3><?= t('helpPage','mngagentsdel') ?></h3>
        <?= t('helpText','mngagentsdel') ?>
        <br/>
    </div>
    <br/>

    <form name="deleteagent" method="POST">
        <input name="csrf_token" type="hidden" value="<?= dalo_csrf_token() ?>" />
        
        <fieldset>
            <h302><?= t('title','AgentInfo') ?></h302>
            <br/>
            
            <div class="alert alert-warning">
                <strong>Warning:</strong> You are about to delete the following agent. This action cannot be undone.
            </div>
            <br/>

            <label class="form"><?= t('all','AgentName') ?></label>
            <div class="form-value"><?= htmlspecialchars($agent_name, ENT_QUOTES, 'UTF-8') ?></div>
            <br/>

            <label class="form"><?= t('all','Company') ?></label>
            <div class="form-value"><?= htmlspecialchars($company, ENT_QUOTES, 'UTF-8') ?></div>
            <br/>

            <label class="form"><?= t('all','Phone') ?></label>
            <div class="form-value"><?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></div>
            <br/>

            <label class="form"><?= t('all','Email') ?></label>
            <div class="form-value"><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></div>
            <br/>

            <label class="form"><?= t('all','City') ?></label>
            <div class="form-value"><?= htmlspecialchars($city, ENT_QUOTES, 'UTF-8') ?></div>
            <br/>

            <label class="form"><?= t('all','Country') ?></label>
            <div class="form-value"><?= htmlspecialchars($country, ENT_QUOTES, 'UTF-8') ?></div>
            <br/>

            <br/><br/>
            <hr><br/>

            <input type="submit" name="submit" value="<?= t('buttons','apply') ?>" onclick="return confirm('Are you sure you want to delete this agent?')" class="button" />
            <input type="button" name="cancel" value="<?= t('buttons','cancel') ?>" onclick="history.back()" class="button" />

        </fieldset>

    </form>

</div>

<?php
    }
    
    if (isset($successMsg)) {
?>
    <div class="text-center">
        <br/>
        <a href="mng-agents-list.php" class="button"><?= t('button','ListAgents') ?></a>
        <a href="mng-agent-new.php" class="button"><?= t('button','NewAgent') ?></a>
    </div>
<?php
    }
    
    include('include/config/logging.php');
    print_footer_and_html_epilogue();
?>