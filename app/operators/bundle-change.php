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
 * Description:    Change Bundle - Prorate refund and switch to new bundle
 *
 *********************************************************************************************************
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 1);

    include("library/checklogin.php");
    $operator = $_SESSION['operator_user'];
    $operator_id = $_SESSION['operator_id'];

    include('library/check_operator_perm.php');
    include_once('../common/includes/config_read.php');

    include_once("lang/main.php");
    include_once("../common/includes/validation.php");
    include("../common/includes/layout.php");
    include_once("include/management/populate_selectbox.php");

    // Include ERP libraries
    require_once(__DIR__ . '/../common/library/BundleManager.php');
    require_once(__DIR__ . '/../common/library/BalanceManager.php');
    require_once(__DIR__ . '/../common/library/RadiusAccessManager.php');
    require_once(__DIR__ . '/../common/library/ActionLogger.php');

    $log = "visited page: ";
    $logAction = "";
    $logDebugSQL = "";

    include('../common/includes/db_open.php');

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
        error_log('Bundle change mysqli error: ' . $e->getMessage());
        $mysqli = null;
    }

    // Get users with ACTIVE bundles only
    $users_with_bundles = array();
    try {
        $sql = sprintf("SELECT ub.id as user_id, ub.username, ub.money_balance, ub.planName,
                                ub.bundle_expiry_date, ub.current_bundle_id,
                                ubun.plan_name as current_bundle_name, ubun.purchase_amount,
                                ubun.activation_date, ubun.expiry_date
                         FROM %s ub
                         LEFT JOIN user_bundles ubun ON ub.current_bundle_id = ubun.id AND ubun.status = 'active'
                         WHERE ub.bundle_status = 'active' AND ub.current_bundle_id IS NOT NULL
                         ORDER BY ub.username",
                        $configValues['CONFIG_DB_TBL_DALOUSERBILLINFO']);
        $res = $dbSocket->query($sql);
        $logDebugSQL .= "$sql;\n";

        if (!DB::isError($res) && $res) {
            while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
                $uid = intval($row['user_id']);
                if ($uid > 0 && $row['current_bundle_id']) {
                    // Calculate remaining days
                    $now = new DateTime();
                    $expiry = new DateTime($row['expiry_date']);
                    $activation = new DateTime($row['activation_date']);
                    $remainingDays = max(0, $now->diff($expiry)->days);
                    $totalDays = max(1, $activation->diff($expiry)->days);
                    $purchaseAmount = floatval($row['purchase_amount']);
                    $refundEstimate = ($remainingDays > 0 && $purchaseAmount > 0)
                        ? round(($remainingDays / $totalDays) * $purchaseAmount, 2) : 0;

                    $users_with_bundles[$uid] = array(
                        'username' => $row['username'],
                        'balance' => floatval($row['money_balance']),
                        'current_plan' => $row['current_bundle_name'] ?: $row['planName'],
                        'expiry_date' => $row['expiry_date'],
                        'remaining_days' => $remainingDays,
                        'refund_estimate' => $refundEstimate
                    );
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error loading users with bundles: " . $e->getMessage());
    }

    // Get available bundle plans
    $bundle_plans = array();
    try {
        $sql = sprintf("SELECT id, planName, planCost, planCurrency, bundle_validity_days,
                                bundle_validity_hours, planTrafficTotal
                         FROM %s
                         WHERE is_bundle = 1 AND planActive = 'yes'
                         ORDER BY planCost ASC",
                        $configValues['CONFIG_DB_TBL_DALOBILLINGPLANS']);
        $res = $dbSocket->query($sql);
        $logDebugSQL .= "$sql;\n";

        if (!DB::isError($res) && $res) {
            while ($row = $res->fetchRow()) {
                $bundle_plans[intval($row[0])] = array(
                    'name' => $row[1],
                    'cost' => floatval($row[2]),
                    'currency' => $row[3] ?: 'SYP',
                    'validity_days' => intval($row[4]),
                    'validity_hours' => intval($row[5]),
                    'traffic_mb' => floatval($row[6])
                );
            }
        }
    } catch (Exception $e) {
        error_log("Error loading bundle plans: " . $e->getMessage());
    }

    // Get ALL users for free bundle mode (must query before db_close)
    $all_users = array();
    try {
        $allUsersSql = sprintf("SELECT id, username, money_balance, planName FROM %s ORDER BY username",
            $configValues['CONFIG_DB_TBL_DALOUSERBILLINFO']);
        $allUsersRes = $dbSocket->query($allUsersSql);
        if (!DB::isError($allUsersRes) && $allUsersRes) {
            while ($urow = $allUsersRes->fetchRow(DB_FETCHMODE_ASSOC)) {
                $uid = intval($urow['id']);
                if ($uid > 0) {
                    $all_users[$uid] = array(
                        'username' => $urow['username'],
                        'balance' => floatval($urow['money_balance'])
                    );
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error loading all users: " . $e->getMessage());
    }

    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (array_key_exists('csrf_token', $_POST) && isset($_POST['csrf_token']) && dalo_check_csrf_token($_POST['csrf_token'])) {

            $user_id = (array_key_exists('user_id', $_POST) && intval(trim($_POST['user_id'])) > 0)
                     ? intval(trim($_POST['user_id'])) : 0;
            $new_bundle_id = (array_key_exists('new_bundle_id', $_POST) && intval(trim($_POST['new_bundle_id'])) > 0)
                           ? intval(trim($_POST['new_bundle_id'])) : 0;

            // Check for free bundle grant
            $is_free = (array_key_exists('free_bundle', $_POST) && $_POST['free_bundle'] == '1');

            if ($user_id <= 0 || $new_bundle_id <= 0) {
                $failureMsg = "Please select both user and new bundle";
                $logAction .= "$failureMsg on page: ";
            } elseif (!$mysqli) {
                $failureMsg = "Database connection error";
                $logAction .= "mysqli not available on page: ";
            } else {
                try {
                    $bundleManager = new BundleManager($mysqli);

                    if ($is_free) {
                        // Free bundle grant (staff)
                        $username = '';
                        // Look up username
                        if (isset($users_with_bundles[$user_id])) {
                            $username = $users_with_bundles[$user_id]['username'];
                        } else {
                            // User might not have active bundle - look up from userbillinfo
                            $lookupSql = "SELECT username FROM userbillinfo WHERE id = " . intval($user_id);
                            $lookupRes = $mysqli->query($lookupSql);
                            if ($lookupRes && $row = $lookupRes->fetch_assoc()) {
                                $username = $row['username'];
                            }
                        }

                        if (empty($username)) {
                            throw new Exception('User not found');
                        }

                        $notes = (array_key_exists('notes', $_POST) && !empty(trim($_POST['notes'])))
                               ? trim($_POST['notes']) : 'Free bundle - staff';

                        $result = $bundleManager->purchaseFreeBundle(
                            $user_id, $username, $new_bundle_id, $operator, $notes
                        );

                        if ($result['success']) {
                            $bundleName = isset($bundle_plans[$new_bundle_id]) ? $bundle_plans[$new_bundle_id]['name'] : 'Bundle #' . $new_bundle_id;
                            $successMsg = sprintf(
                                "<strong>Free Bundle Granted!</strong><br><br>" .
                                "Username: <strong>%s</strong><br>" .
                                "Bundle: <strong>%s</strong><br>" .
                                "Cost: <strong>FREE ($0.00)</strong><br>" .
                                "Expires: <strong>%s</strong><br><br>" .
                                '<a href="mng-edit.php?username=%s">View User</a>',
                                htmlspecialchars($username),
                                htmlspecialchars($bundleName),
                                $result['expiry_date'],
                                urlencode($username)
                            );
                            $logAction .= "Free bundle granted: User=$username, Bundle=$bundleName on page: ";

                            // Log to action history
                            try {
                                $actionLogger = new ActionLogger($mysqli);
                                $actionLogger->log('bundle_grant_free', 'bundle', $username,
                                    "Granted free bundle '$bundleName' to user '$username'",
                                    null,
                                    array('bundle_name' => $bundleName, 'bundle_id' => $new_bundle_id,
                                          'expiry' => $result['expiry_date'], 'notes' => $notes)
                                );
                            } catch (Exception $logEx) { error_log("ActionLogger error: " . $logEx->getMessage()); }
                        } else {
                            throw new Exception($result['message']);
                        }
                    } else {
                        // Normal bundle change with prorate refund
                        if (!isset($users_with_bundles[$user_id])) {
                            throw new Exception('Selected user has no active bundle to change');
                        }

                        $username = $users_with_bundles[$user_id]['username'];

                        $result = $bundleManager->changeBundle(
                            $user_id, $username, $new_bundle_id, null, $operator
                        );

                        if ($result['success']) {
                            // NOTE: Do NOT call grantAccess() here.
                            // BundleManager::changeBundle() already calls activateBundleRadius()
                            // which sets Expiration, radusergroup, Mikrotik-Rate-Limit, etc.
                            // Calling grantAccess() would overwrite/delete the Expiration.

                            $successMsg = sprintf(
                                "<strong>Bundle Changed Successfully!</strong><br><br>" .
                                "Username: <strong>%s</strong><br>" .
                                "Old Bundle: <strong>%s</strong><br>" .
                                "New Bundle: <strong>%s</strong><br>" .
                                "Refund: <strong>$%.2f</strong> (%d days remaining)<br>" .
                                "New Cost: <strong>$%.2f</strong><br>" .
                                "Net Charge: <strong>$%.2f</strong><br>" .
                                "New Balance: <strong>$%.2f</strong><br>" .
                                "Expires: <strong>%s</strong><br><br>" .
                                '<a href="mng-edit.php?username=%s">View User</a>',
                                htmlspecialchars($username),
                                htmlspecialchars($result['old_plan']),
                                htmlspecialchars($result['new_plan']),
                                $result['refund_amount'],
                                $result['remaining_days_refunded'],
                                $result['new_cost'],
                                $result['net_charge'],
                                $result['new_balance'],
                                $result['expiry_date'],
                                urlencode($username)
                            );
                            $logAction .= sprintf("Bundle changed: User=%s, %s->%s, Refund=$%.2f on page: ",
                                $username, $result['old_plan'], $result['new_plan'], $result['refund_amount']);

                            // Log to action history
                            try {
                                $actionLogger = new ActionLogger($mysqli);
                                $actionLogger->log('bundle_change', 'bundle', $username,
                                    sprintf("Changed bundle for '%s': %s -> %s (refund $%.2f)",
                                        $username, $result['old_plan'], $result['new_plan'], $result['refund_amount']),
                                    array('old_plan' => $result['old_plan'], 'remaining_days' => $result['remaining_days_refunded'],
                                          'refund_amount' => $result['refund_amount']),
                                    array('new_plan' => $result['new_plan'], 'new_cost' => $result['new_cost'],
                                          'net_charge' => $result['net_charge'], 'new_balance' => $result['new_balance'],
                                          'expiry' => $result['expiry_date'])
                                );
                            } catch (Exception $logEx) { error_log("ActionLogger error: " . $logEx->getMessage()); }
                        } else {
                            throw new Exception($result['message']);
                        }
                    }

                } catch (Exception $e) {
                    $failureMsg = "<strong>Operation Failed:</strong><br>" . htmlspecialchars($e->getMessage());
                    $logAction .= "Bundle operation failed: " . $e->getMessage() . " on page: ";
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

    $title = "Change Bundle";
    $help = "Change user bundle with prorate refund, or grant free bundles to staff";

    print_html_prologue($title, $langCode);
    print_title_and_help($title, $help);
    include_once('include/management/actionMessages.php');

    if (!isset($successMsg)) {
?>

<style>
.bundle-card { border: 2px solid #4caf50; border-radius: 8px; padding: 15px; margin: 10px 0; background: #f1f8f4; }
.bundle-card:hover { background: #e8f5e9; cursor: pointer; }
.bundle-selected { background: #c8e6c9 !important; border-color: #2e7d32; }
.user-info { background: #e3f2fd; border: 2px solid #2196f3; padding: 15px; border-radius: 5px; margin: 15px 0; }
.refund-info { background: #fff3e0; border: 2px solid #ff9800; padding: 15px; border-radius: 5px; margin: 15px 0; }
.preview-table td { padding: 6px 12px; }
.preview-positive { color: #4caf50; font-weight: bold; }
.preview-negative { color: #f44336; font-weight: bold; }
.mode-toggle { margin: 20px 0; }
.mode-toggle .btn { min-width: 200px; }
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-10 offset-md-1">

            <!-- Mode Toggle -->
            <div class="mode-toggle text-center">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-primary active" id="btnModeChange" onclick="setMode('change')">
                        Change Bundle (Prorate Refund)
                    </button>
                    <button type="button" class="btn btn-outline-success" id="btnModeFree" onclick="setMode('free')">
                        Grant Free Bundle (Staff)
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 id="cardTitle">Change Bundle with Prorate Refund</h3>
                    <p class="text-muted" id="cardSubtitle">Select a user with active bundle to change to a new plan</p>
                </div>

                <div class="card-body">
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="bundleForm">
                        <input type="hidden" name="csrf_token" value="<?php echo dalo_csrf_token(); ?>">
                        <input type="hidden" name="free_bundle" id="free_bundle" value="0">

                        <?php if (count($bundle_plans) > 0): ?>

                        <!-- User Selection (Change Mode - users with active bundles) -->
                        <div id="changeUserSection">
                            <div class="form-group">
                                <label for="user_id_change">Select User (with active bundle): <span class="text-danger">*</span></label>
                                <select name="user_id" id="user_id_change" class="form-control" onchange="loadChangeUserInfo()">
                                    <option value="">-- Select User --</option>
                                    <?php foreach ($users_with_bundles as $uid => $udata): ?>
                                    <option value="<?php echo $uid; ?>"
                                            data-username="<?php echo htmlspecialchars($udata['username']); ?>"
                                            data-balance="<?php echo $udata['balance']; ?>"
                                            data-plan="<?php echo htmlspecialchars($udata['current_plan']); ?>"
                                            data-expiry="<?php echo $udata['expiry_date']; ?>"
                                            data-remaining="<?php echo $udata['remaining_days']; ?>"
                                            data-refund="<?php echo $udata['refund_estimate']; ?>">
                                        <?php echo htmlspecialchars($udata['username']); ?>
                                        - <?php echo htmlspecialchars($udata['current_plan']); ?>
                                        (<?php echo $udata['remaining_days']; ?> days left)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if (count($users_with_bundles) == 0): ?>
                            <div class="alert alert-warning">No users with active bundles found.</div>
                            <?php endif; ?>
                        </div>

                        <!-- User Selection (Free Mode - all users) -->
                        <div id="freeUserSection" style="display:none;">
                            <div class="form-group">
                                <label for="user_id_free">Select User: <span class="text-danger">*</span></label>
                                <select id="user_id_free" class="form-control" onchange="loadFreeUserInfo()">
                                    <option value="">-- Select User --</option>
                                    <?php foreach ($all_users as $uid => $udata): ?>
                                    <option value="<?php echo $uid; ?>"
                                            data-username="<?php echo htmlspecialchars($udata['username']); ?>"
                                            data-balance="<?php echo $udata['balance']; ?>">
                                        <?php echo htmlspecialchars($udata['username']); ?> (Balance: $<?php echo number_format($udata['balance'], 2); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- User Info Display -->
                        <div id="userInfo" style="display:none;" class="user-info">
                            <h5>User Information</h5>
                            <table class="table table-sm">
                                <tr><td><strong>Username:</strong></td><td id="display_username">-</td></tr>
                                <tr><td><strong>Current Balance:</strong></td><td id="display_balance">-</td></tr>
                                <tr id="row_current_plan"><td><strong>Current Bundle:</strong></td><td id="display_plan">-</td></tr>
                                <tr id="row_expiry"><td><strong>Expires:</strong></td><td id="display_expiry">-</td></tr>
                                <tr id="row_remaining"><td><strong>Days Remaining:</strong></td><td id="display_remaining">-</td></tr>
                            </table>
                        </div>

                        <!-- Refund Preview (Change mode only) -->
                        <div id="refundPreview" style="display:none;" class="refund-info">
                            <h5>Change Preview</h5>
                            <table class="table table-sm preview-table">
                                <tr><td>Prorate Refund:</td><td class="preview-positive" id="preview_refund">$0.00</td></tr>
                                <tr><td>New Bundle Cost:</td><td class="preview-negative" id="preview_cost">$0.00</td></tr>
                                <tr><td><strong>Net Charge:</strong></td><td><strong id="preview_net">$0.00</strong></td></tr>
                                <tr><td>Balance After Change:</td><td id="preview_balance_after">$0.00</td></tr>
                                <tr id="row_can_afford"><td>Can Afford:</td><td id="preview_can_afford">-</td></tr>
                            </table>
                        </div>

                        <!-- Bundle Selection -->
                        <div class="form-group">
                            <label>Select New Bundle: <span class="text-danger">*</span></label>
                            <input type="hidden" name="new_bundle_id" id="new_bundle_id" required>

                            <div id="bundleList">
                                <?php foreach ($bundle_plans as $bid => $bdata): ?>
                                <div class="bundle-card" onclick="selectNewBundle(<?php echo $bid; ?>, <?php echo $bdata['cost']; ?>, '<?php echo htmlspecialchars(addslashes($bdata['name'])); ?>')" id="bundle_<?php echo $bid; ?>">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h5><?php echo htmlspecialchars($bdata['name']); ?></h5>
                                            <p class="text-muted">
                                                <?php
                                                if ($bdata['validity_days'] > 0) {
                                                    echo $bdata['validity_days'] . " days";
                                                    if ($bdata['validity_hours'] > 0) echo " + " . $bdata['validity_hours'] . " hours";
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
                        </div>

                        <!-- Notes -->
                        <div class="form-group">
                            <label for="notes">Notes:</label>
                            <textarea name="notes" id="notes" class="form-control" rows="2">Bundle changed by operator</textarea>
                        </div>

                        <!-- Submit -->
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-lg" id="submitBtn" disabled>
                                Confirm Change
                            </button>
                            <a href="bundle-list.php" class="btn btn-secondary">Cancel</a>
                        </div>

                        <?php else: ?>
                        <div class="alert alert-warning">
                            No bundle plans found. Create bundle plans first in <a href="bill-plans-new.php">Billing Plans</a>.
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentMode = 'change';
let userData = { balance: 0, refundEstimate: 0 };
let selectedBundle = { id: 0, cost: 0, name: '' };

function setMode(mode) {
    currentMode = mode;
    document.getElementById('free_bundle').value = (mode === 'free') ? '1' : '0';

    // Toggle UI
    document.getElementById('changeUserSection').style.display = (mode === 'change') ? 'block' : 'none';
    document.getElementById('freeUserSection').style.display = (mode === 'free') ? 'block' : 'none';
    document.getElementById('refundPreview').style.display = 'none';
    document.getElementById('userInfo').style.display = 'none';

    // Toggle button styles
    document.getElementById('btnModeChange').className = (mode === 'change') ? 'btn btn-primary active' : 'btn btn-outline-primary';
    document.getElementById('btnModeFree').className = (mode === 'free') ? 'btn btn-success active' : 'btn btn-outline-success';

    // Update titles
    if (mode === 'change') {
        document.getElementById('cardTitle').textContent = 'Change Bundle with Prorate Refund';
        document.getElementById('cardSubtitle').textContent = 'Select a user with active bundle to change to a new plan';
        document.getElementById('submitBtn').textContent = 'Confirm Change';
        document.getElementById('submitBtn').className = 'btn btn-primary btn-lg';
        document.getElementById('notes').value = 'Bundle changed by operator';
    } else {
        document.getElementById('cardTitle').textContent = 'Grant Free Bundle (Staff/VIP)';
        document.getElementById('cardSubtitle').textContent = 'Select any user to grant a free bundle (no balance deduction)';
        document.getElementById('submitBtn').textContent = 'Grant Free Bundle';
        document.getElementById('submitBtn').className = 'btn btn-success btn-lg';
        document.getElementById('notes').value = 'Free bundle - staff';
    }

    // Sync user_id to the active select
    syncUserId();

    // Toggle visible rows
    document.getElementById('row_current_plan').style.display = (mode === 'change') ? '' : 'none';
    document.getElementById('row_expiry').style.display = (mode === 'change') ? '' : 'none';
    document.getElementById('row_remaining').style.display = (mode === 'change') ? '' : 'none';

    // Reset selection
    selectedBundle = { id: 0, cost: 0, name: '' };
    document.querySelectorAll('.bundle-card').forEach(c => c.classList.remove('bundle-selected'));
    document.getElementById('new_bundle_id').value = '';
    document.getElementById('submitBtn').disabled = true;
}

function syncUserId() {
    // Copy the value from the active select to the form's user_id field
    let activeSelect = (currentMode === 'change')
        ? document.getElementById('user_id_change')
        : document.getElementById('user_id_free');

    // Ensure the correct name attribute
    document.getElementById('user_id_change').name = (currentMode === 'change') ? 'user_id' : '';
    document.getElementById('user_id_free').name = (currentMode === 'free') ? 'user_id' : '';
}

function loadChangeUserInfo() {
    syncUserId();
    let select = document.getElementById('user_id_change');
    let opt = select.options[select.selectedIndex];
    if (!opt.value) { document.getElementById('userInfo').style.display = 'none'; return; }

    userData.balance = parseFloat(opt.dataset.balance);
    userData.refundEstimate = parseFloat(opt.dataset.refund);

    document.getElementById('display_username').textContent = opt.dataset.username;
    document.getElementById('display_balance').textContent = '$' + userData.balance.toFixed(2);
    document.getElementById('display_plan').textContent = opt.dataset.plan;
    document.getElementById('display_expiry').textContent = opt.dataset.expiry;
    document.getElementById('display_remaining').textContent = opt.dataset.remaining + ' days';
    document.getElementById('userInfo').style.display = 'block';

    if (selectedBundle.id > 0) updatePreview();
}

function loadFreeUserInfo() {
    syncUserId();
    let select = document.getElementById('user_id_free');
    let opt = select.options[select.selectedIndex];
    if (!opt.value) { document.getElementById('userInfo').style.display = 'none'; return; }

    userData.balance = parseFloat(opt.dataset.balance);
    document.getElementById('display_username').textContent = opt.dataset.username;
    document.getElementById('display_balance').textContent = '$' + userData.balance.toFixed(2);
    document.getElementById('userInfo').style.display = 'block';

    if (selectedBundle.id > 0) document.getElementById('submitBtn').disabled = false;
}

function selectNewBundle(bundleId, cost, name) {
    document.querySelectorAll('.bundle-card').forEach(c => c.classList.remove('bundle-selected'));
    document.getElementById('bundle_' + bundleId).classList.add('bundle-selected');
    document.getElementById('new_bundle_id').value = bundleId;

    selectedBundle = { id: bundleId, cost: cost, name: name };

    if (currentMode === 'change') {
        updatePreview();
    } else {
        // Free mode - enable submit if user selected
        let freeSelect = document.getElementById('user_id_free');
        document.getElementById('submitBtn').disabled = !freeSelect.value;
    }
}

function updatePreview() {
    if (!selectedBundle.id || currentMode !== 'change') return;

    let refund = userData.refundEstimate;
    let cost = selectedBundle.cost;
    let net = cost - refund;
    let balanceAfter = userData.balance + refund - cost;
    let canAfford = (userData.balance + refund) >= cost;

    document.getElementById('preview_refund').textContent = '+$' + refund.toFixed(2);
    document.getElementById('preview_cost').textContent = '-$' + cost.toFixed(2);
    document.getElementById('preview_net').textContent = (net >= 0 ? '-' : '+') + '$' + Math.abs(net).toFixed(2);
    document.getElementById('preview_balance_after').textContent = '$' + balanceAfter.toFixed(2);
    document.getElementById('preview_balance_after').className = balanceAfter >= 0 ? 'preview-positive' : 'preview-negative';

    if (canAfford) {
        document.getElementById('preview_can_afford').innerHTML = '<span class="badge badge-success">YES</span>';
        document.getElementById('submitBtn').disabled = false;
    } else {
        document.getElementById('preview_can_afford').innerHTML = '<span class="badge badge-danger">INSUFFICIENT BALANCE</span>';
        document.getElementById('submitBtn').disabled = true;
    }

    document.getElementById('refundPreview').style.display = 'block';
}

document.addEventListener('DOMContentLoaded', function() { syncUserId(); });
</script>

<?php
    }

    include('include/config/logging.php');
    print_footer_and_html_epilogue();
?>
