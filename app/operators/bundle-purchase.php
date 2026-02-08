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
 * Description:    Bundle Purchase - Purchase bundles for users
 *
 *********************************************************************************************************
 */

// TEMPORARY: Enable error display for debugging (hide deprecation warnings)
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 1);

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
    
    
    // Include ERP libraries
    try {
        echo "<!-- Loading BundleManager... -->";
        require_once(__DIR__ . '/../common/library/BundleManager.php');
        echo "<!-- BundleManager loaded -->";
        
        echo "<!-- Loading BalanceManager... -->";
        require_once(__DIR__ . '/../common/library/BalanceManager.php');
        echo "<!-- BalanceManager loaded -->";
        
        echo "<!-- Loading RadiusAccessManager... -->";
        require_once(__DIR__ . '/../common/library/RadiusAccessManager.php');
        echo "<!-- RadiusAccessManager loaded -->";
    } catch (Exception $e) {
        die("<h1>Error loading Manager classes:</h1><pre>" . $e->getMessage() . "\n\n" . $e->getTraceAsString() . "</pre>");
    }
    
    $log = "visited page: ";
    $logAction = "";
    $logDebugSQL = "";

    echo "<!-- Starting database connection setup... -->";
    include('../common/includes/db_open.php');
    echo "<!-- Database opened successfully -->";
    
    // Create mysqli connection for libraries
    $mysqli = null;
    try {
        $mysqli = new mysqli(
            $configValues['CONFIG_DB_HOST'],
            $configValues['CONFIG_DB_USER'],
            $configValues['CONFIG_DB_PASS'],
            $configValues['CONFIG_DB_NAME'],
            $configValues['CONFIG_DB_PORT']
        );
        
        if ($mysqli->connect_error) {
            throw new Exception('Database connection error: ' . $mysqli->connect_error);
        }
        
        $mysqli->set_charset('utf8mb4');
    } catch (Exception $e) {
        error_log('Bundle purchase mysqli error: ' . $e->getMessage());
        $mysqli = null;
    }
    
    
    // TEMPORARY: Disable agent check until operators table has is_agent column
    echo "<!-- Skipping agent check (operators table may not have is_agent column) -->";
    $is_current_operator_agent = false;
    $current_agent_id = 0;
    
    /*
    // Check if operator is an agent
    echo "<!-- Checking if operator is agent... -->";
    $is_current_operator_agent = isCurrentOperatorAgent($dbSocket, $operator_id, $configValues);
    $current_agent_id = $is_current_operator_agent ? getCurrentOperatorAgentId($dbSocket, $operator_id, $configValues) : 0;
    echo "<!-- Agent check complete: is_agent=$is_current_operator_agent, agent_id=$current_agent_id -->";
    */
    
    // Get list of bundle plans
    $bundle_plans = array();
    try {
        $sql = sprintf("SELECT bp.id, bp.planName, bp.planCost, bp.planCurrency, bp.bundle_validity_days, 
                               bp.bundle_validity_hours, bp.planTrafficTotal
                        FROM %s bp
                        WHERE bp.is_bundle = 1 AND bp.planActive = 'yes'
                        ORDER BY bp.planCost ASC",
                       $configValues['CONFIG_DB_TBL_DALOBILLINGPLANS']);
        echo "<!-- SQL Query: " . htmlspecialchars($sql) . " -->";
        $res = $dbSocket->query($sql);
        $logDebugSQL .= "$sql;\n";
        
        if (DB::isError($res)) {
            echo "<!-- DB ERROR: " . htmlspecialchars($res->getMessage()) . " -->";
            error_log("Bundle query error: " . $res->getMessage());
        } elseif ($res) {
            while ($row = $res->fetchRow()) {
                $bundle_plans[intval($row[0])] = array(
                    'name' => $row[1],
                    'cost' => floatval($row[2]),
                    'currency' => $row[3] ? $row[3] : 'SYP',
                    'validity_days' => intval($row[4]),
                    'validity_hours' => intval($row[5]),
                    'traffic_mb' => floatval($row[6])
                );
            }
        } else {
            echo "<!-- Query returned null -->";
        }
    } catch (Exception $e) {
        error_log("Error loading bundle plans: " . $e->getMessage());
        echo "<!-- ERROR loading bundles: " . htmlspecialchars($e->getMessage()) . " -->";
    }
    echo "<!-- Bundle plans loaded: " . count($bundle_plans) . " bundles found -->";
    
    // Get list of users (filtered by agent if applicable)
    $valid_users = array();
    try {
        if ($is_current_operator_agent && $current_agent_id > 0) {
            $sql = sprintf("SELECT ub.id, ub.username, ub.money_balance, ub.planName
                            FROM %s ub
                            INNER JOIN %s u ON ub.username = u.username
                            INNER JOIN user_agent ua ON u.id = ua.user_id
                            WHERE ua.agent_id = %d AND ub.id IS NOT NULL
                            ORDER BY ub.username",
                           $configValues['CONFIG_DB_TBL_DALOUSERBILLINFO'],
                           $configValues['CONFIG_DB_TBL_DALOUSERINFO'],
                           intval($current_agent_id));
        } else {
            $sql = sprintf("SELECT ub.id, ub.username, ub.money_balance, ub.planName
                            FROM %s ub
                            WHERE ub.id IS NOT NULL
                            ORDER BY ub.username",
                           $configValues['CONFIG_DB_TBL_DALOUSERBILLINFO']);
        }
        
        $res = $dbSocket->query($sql);
        $logDebugSQL .= "$sql;\n";
        
        if (!DB::isError($res) && $res) {
            while ($row = $res->fetchRow()) {
                $billinfo_id = intval($row[0]);
                if ($billinfo_id > 0) {
                    $valid_users[$billinfo_id] = array(
                        'username' => $row[1],
                        'balance' => floatval($row[2]),
                        'plan' => $row[3]
                    );
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error loading users: " . $e->getMessage());
        echo "<!-- ERROR loading users: " . htmlspecialchars($e->getMessage()) . " -->";
    }
    echo "<!-- Users loaded: " . count($valid_users) . " users found -->";
    echo "<!-- About to process POST if needed... -->";

    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo "<!-- POST DATA: " . htmlspecialchars(print_r($_POST, true)) . " -->";
        
        if (array_key_exists('csrf_token', $_POST) && isset($_POST['csrf_token']) && dalo_check_csrf_token($_POST['csrf_token'])) {
            
            $current_datetime = date('Y-m-d H:i:s');
            $currBy = $operator;
            
            $user_id = (array_key_exists('user_id', $_POST) && intval(trim($_POST['user_id'])) > 0)
                     ? intval(trim($_POST['user_id'])) : 0;
            $bundle_id = (array_key_exists('bundle_id', $_POST) && intval(trim($_POST['bundle_id'])) > 0)
                       ? intval(trim($_POST['bundle_id'])) : 0;
            $notes = (array_key_exists('notes', $_POST) && !empty(trim($_POST['notes'])))
                   ? trim($_POST['notes']) : "Bundle purchased by operator";
            
            echo "<!-- Parsed: user_id=$user_id, bundle_id=$bundle_id -->";
            
            if ($user_id <= 0 || $bundle_id <= 0) {
                $failureMsg = "Please select both user and bundle (user_id=$user_id, bundle_id=$bundle_id)";
                $logAction .= "$failureMsg on page: ";
            } elseif (!$mysqli || !($mysqli instanceof mysqli)) {
                $failureMsg = "Database connection error. Please contact administrator.";
                $logAction .= "mysqli connection not available for bundle purchase on page: ";
            } elseif (!isset($valid_users[$user_id]) || !isset($bundle_plans[$bundle_id])) {
                $failureMsg = "Invalid user or bundle selection";
                $logAction .= "$failureMsg on page: ";
            } else {
                try {
                    $username = $valid_users[$user_id]['username'];
                    $bundle_info = $bundle_plans[$bundle_id];
                    $bundle_cost = $bundle_info['cost'];
                    $current_balance = $valid_users[$user_id]['balance'];
                    
                    // Check if user has sufficient balance
                    if ($current_balance < $bundle_cost) {
                        throw new Exception("Insufficient balance. Required: $" . number_format($bundle_cost, 2) . ", Available: $" . number_format($current_balance, 2));
                    }
                    
                    // Use BundleManager to purchase bundle
                    $bundleManager = new BundleManager($mysqli);
                    $result = $bundleManager->purchaseBundle(
                        $user_id,      // userId
                        $username,      // username
                        $bundle_id,     // planId
                        null,           // agentPaymentId
                        $operator       // createdBy
                    );
                    
                    if ($result['success']) {
                        $new_balance = $current_balance - $bundle_cost;
                        
                        $successMsg = sprintf(
                            "<strong>Bundle Purchased Successfully!</strong><br><br>" .
                            "Username: <strong>%s</strong><br>" .
                            "Bundle: <strong>%s</strong><br>" .
                            "Cost: <strong>$%.2f</strong><br>" .
                            "Previous Balance: <strong>$%.2f</strong><br>" .
                            "New Balance: <strong>$%.2f</strong><br>" .
                            "Expires: <strong>%s</strong><br><br>" .
                            '<a href="mng-edit.php?username=%s" title="Edit User">View User</a>',
                            htmlspecialchars($username),
                            htmlspecialchars($bundle_info['name']),
                            $bundle_cost,
                            $current_balance,
                            $new_balance,
                            $result['expiry_date'],
                            urlencode($username)
                        );
                        
                        $logAction .= sprintf(
                            "Successfully purchased bundle: User=%s, Bundle=%s, Cost=$%.2f on page: ",
                            $username,
                            $bundle_info['name'],
                            $bundle_cost
                        );
                    } else {
                        throw new Exception($result['message']);
                    }
                    
                } catch (Exception $e) {
                    $failureMsg = "<strong>Bundle Purchase Failed:</strong><br>" . htmlspecialchars($e->getMessage());
                    $logAction .= "Bundle purchase failed: " . $e->getMessage() . " on page: ";
                }
            }
            
        } else {
            $failureMsg = "CSRF token error";
            $logAction .= "$failureMsg on page: ";
        }
    }

    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $mysqli->close();
    }
    
    include('../common/includes/db_close.php');

    $title = "Purchase Bundle";
    $help = "Purchase prepaid bundles for users";
    
    print_html_prologue($title, $langCode);
    
    print_title_and_help($title, $help);

    include_once('include/management/actionMessages.php');
    
    echo "<!-- About to check successMsg condition. successMsg isset: " . (isset($successMsg) ? 'YES' : 'NO') . " -->";
    echo "<!-- Bundle plans count: " . count($bundle_plans) . " -->";
    echo "<!-- Valid users count: " . count($valid_users) . " -->";

    if (!isset($successMsg)) {
?>

<style>
.bundle-card {
    border: 2px solid #4caf50;
    border-radius: 8px;
    padding: 15px;
    margin: 10px 0;
    background: #f1f8f4;
}
.bundle-card:hover {
    background: #e8f5e9;
    cursor: pointer;
}
.bundle-selected {
    background: #c8e6c9 !important;
    border-color: #2e7d32;
}
.user-info {
    background: #e3f2fd;
    border: 2px solid #2196f3;
    padding: 15px;
    border-radius: 5px;
    margin: 15px 0;
}
.balance-sufficient {
    color: #4caf50;
    font-weight: bold;
}
.balance-insufficient {
    color: #f44336;
    font-weight: bold;
}
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-10 offset-md-1">
            
            <div class="card">
                <div class="card-header">
                    <h3>Purchase Bundle for User</h3>
                    <p class="text-muted">Select a user and bundle to purchase</p>
                </div>
                
                <div class="card-body">
                    
                    <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" id="bundleForm">
                        
                        <input type="hidden" name="csrf_token" value="<?php echo dalo_csrf_token(); ?>">
                        
                        <?php if (count($valid_users) > 0 && count($bundle_plans) > 0): ?>
                        
                        <!-- User Selection -->
                        <div class="form-group">
                            <label for="user_id">Select User: <span class="text-danger">*</span></label>
                            <select name="user_id" id="user_id" class="form-control" required onchange="loadUserInfo()">
                                <option value="">-- Select User --</option>
                                <?php foreach ($valid_users as $uid => $udata): ?>
                                <option value="<?php echo $uid; ?>" 
                                        data-username="<?php echo htmlspecialchars($udata['username']); ?>"
                                        data-balance="<?php echo $udata['balance']; ?>"
                                        data-plan="<?php echo htmlspecialchars($udata['plan']); ?>">
                                    <?php echo htmlspecialchars($udata['username']); ?> 
                                    (Balance: $<?php echo number_format($udata['balance'], 2); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div id="userInfo" style="display:none;" class="user-info">
                            <h5>User Information</h5>
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>Username:</strong></td>
                                    <td id="display_username">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Current Plan:</strong></td>
                                    <td id="display_plan">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Current Balance:</strong></td>
                                    <td><span id="display_balance" class="balance-sufficient">$0.00</span></td>
                                </tr>
                                <tr id="balance_check_row" style="display:none;">
                                    <td><strong>Can Afford Bundle:</strong></td>
                                    <td><span id="balance_check"></span></td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Bundle Selection -->
                        <div class="form-group">
                            <label>Select Bundle: <span class="text-danger">*</span></label>
                            <input type="hidden" name="bundle_id" id="bundle_id" required>
                            
                            <div id="bundleList">
                                <?php foreach ($bundle_plans as $bid => $bdata): ?>
                                <div class="bundle-card" onclick="selectBundle(<?php echo $bid; ?>, <?php echo $bdata['cost']; ?>)" id="bundle_<?php echo $bid; ?>">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h5><?php echo htmlspecialchars($bdata['name']); ?></h5>
                                            <p class="text-muted">
                                                <?php 
                                                if ($bdata['validity_days'] > 0) {
                                                    echo $bdata['validity_days'] . " days";
                                                    if ($bdata['validity_hours'] > 0) {
                                                        echo " + " . $bdata['validity_hours'] . " hours";
                                                    }
                                                } else {
                                                    echo $bdata['validity_hours'] . " hours";
                                                }
                                                ?>
                                                | <?php echo $bdata['traffic_mb']; ?> MB
                                            </p>
                                        </div>
                                        <div class="col-md-6 text-right">
                                            <h4 class="text-success">
                                                <?php echo $bdata['currency']; ?> <?php echo number_format($bdata['cost'], 2); ?>
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div id="selectedBundleInfo" style="display:none;" class="alert alert-info mt-2">
                                Selected: <strong id="selected_bundle_name"></strong> - 
                                <strong id="selected_bundle_cost"></strong>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Notes:</label>
                            <textarea name="notes" 
                                      id="notes" 
                                      class="form-control" 
                                      rows="2">Bundle purchased by operator</textarea>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-success btn-lg" id="submitBtn">
                                📦 Purchase Bundle
                            </button>
                            <a href="mng-list-all.php" class="btn btn-secondary">Cancel</a>
                        </div>
                        
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <?php if (count($valid_users) == 0): ?>
                            No users found. Please create users first.
                            <?php elseif (count($bundle_plans) == 0): ?>
                            No bundle plans found. Please create bundle plans first in <a href="bill-plans-new.php">Billing Plans</a>.
 <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                    </form>
                    
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
let currentUserData = {
    balance: 0,
    plan: ''
};

let selectedBundleData = {
    id: 0,
    name: '',
    cost: 0
};

function loadUserInfo() {
    const select = document.getElementById('user_id');
    const selectedOption = select.options[select.selectedIndex];
    
    if (!selectedOption.value) {
        document.getElementById('userInfo').style.display = 'none';
        return;
    }
    
    const username = selectedOption.dataset.username;
    const balance = parseFloat(selectedOption.dataset.balance);
    const plan = selectedOption.dataset.plan || 'Not assigned';
    
    currentUserData.balance = balance;
    currentUserData.plan = plan;
    
    document.getElementById('display_username').textContent = username;
    document.getElementById('display_plan').textContent = plan;
    document.getElementById('display_balance').textContent = '$' + balance.toFixed(2);
    
    document.getElementById('userInfo').style.display = 'block';
    
    // Re-check balance if bundle is selected
    if (selectedBundleData.id > 0) {
        checkBalance();
    }
}

function selectBundle(bundleId, cost) {
    // Remove previous selection
    document.querySelectorAll('.bundle-card').forEach(card => {
        card.classList.remove('bundle-selected');
    });
    
    // Add selection to clicked card
    const card = document.getElementById('bundle_' + bundleId);
    card.classList.add('bundle-selected');
    
    // Update hidden field
    document.getElementById('bundle_id').value = bundleId;
    
    // Store data
    selectedBundleData.id = bundleId;
    selectedBundleData.name = card.querySelector('h5').textContent;
    selectedBundleData.cost = cost;
    
    // Show selected info
    document.getElementById('selected_bundle_name').textContent = selectedBundleData.name;
    document.getElementById('selected_bundle_cost').textContent = '$' + cost.toFixed(2);
    document.getElementById('selectedBundleInfo').style.display = 'block';
    
    // Check balance
    checkBalance();
}

function checkBalance() {
    if (currentUserData.balance > 0 && selectedBundleData.cost > 0) {
        const checkRow = document.getElementById('balance_check_row');
        const checkSpan = document.getElementById('balance_check');
        
        if (currentUserData.balance >= selectedBundleData.cost) {
            checkSpan.innerHTML = '<span class="badge badge-success">✓ YES</span>';
            document.getElementById('submitBtn').disabled = false;
        } else {
            checkSpan.innerHTML = '<span class="badge badge-danger">✗ INSUFFICIENT BALANCE</span>';
            document.getElementById('submitBtn').disabled = true;
        }
        
        checkRow.style.display = 'table-row';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const userSelect = document.getElementById('user_id');
    if (userSelect && userSelect.value) {
        loadUserInfo();
    }
});
</script>

<?php
    }
    
    include('include/config/logging.php');
    print_footer_and_html_epilogue();
?>
