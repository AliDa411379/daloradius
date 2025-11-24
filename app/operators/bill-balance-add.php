<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform - ADD BALANCE
 * Add prepaid balance to user accounts
 * 
 * This is part of the Balance System implementation.
 * Allows operators to add balance credit to user accounts.
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
    
    require_once(__DIR__ . '/../common/library/balance_functions.php');
    
    $log = "visited page: ";
    $logAction = "";
    $logDebugSQL = "";

    include('../common/includes/db_open.php');
    
    $mysqli = null;
    try {
        $mysqli_host = $configValues['CONFIG_DB_HOST'];
        $mysqli_user = $configValues['CONFIG_DB_USER'];
        $mysqli_pass = $configValues['CONFIG_DB_PASS'];
        $mysqli_name = $configValues['CONFIG_DB_NAME'];
        $mysqli_port = $configValues['CONFIG_DB_PORT'];
        
        $mysqli = @new mysqli($mysqli_host, $mysqli_user, $mysqli_pass, $mysqli_name, $mysqli_port);
        if ($mysqli && $mysqli->connect_error) {
            throw new Exception('Database connection error: ' . $mysqli->connect_error);
        }
        if ($mysqli) {
            $mysqli->set_charset('utf8');
        }
    } catch (Exception $e) {
        error_log('Balance add mysqli error: ' . $e->getMessage());
        $mysqli = null;
    }
    
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $is_current_operator_agent = isCurrentOperatorAgent($dbSocket, $operator_id, $configValues);
    $current_agent_id = $is_current_operator_agent ? getCurrentOperatorAgentId($dbSocket, $operator_id, $configValues) : 0;
    
    $valid_agents = array();
    if (!$is_current_operator_agent) {
        try {
            $sql = sprintf("SELECT id, name FROM %s WHERE is_deleted = 0 ORDER BY name", 
                           $configValues['CONFIG_DB_TBL_DALOAGENTS']);
            $res = $dbSocket->query($sql);
            if (!DB::isError($res) && $res) {
                while ($row = $res->fetchRow()) {
                    $valid_agents[intval($row[0])] = $row[1];
                }
            }
        } catch (Exception $e) {
            error_log("Error loading agents: " . $e->getMessage());
        }
    }
    
    $agent_id = (!$is_current_operator_agent && array_key_exists('agent_id', $_REQUEST) && intval(trim($_REQUEST['agent_id'])) > 0)
              ? intval(trim($_REQUEST['agent_id'])) : $current_agent_id;

    $valid_users = array();
    try {
        if ($is_current_operator_agent) {
            if ($current_agent_id > 0) {
                $sql = sprintf("SELECT ub.id, ub.username, COALESCE(ub.money_balance, 0) as money_balance, COALESCE(ub.total_invoices_amount, 0) as total_invoices_amount 
                                FROM %s ub
                                INNER JOIN user_agent ua ON ub.id = ua.user_id
                                WHERE ua.agent_id = %d
                                GROUP BY ub.id, ub.username, ub.money_balance, ub.total_invoices_amount
                                ORDER BY ub.username",
                               $configValues['CONFIG_DB_TBL_DALOUSERBILLINFO'],
                               intval($current_agent_id));
                $res = $dbSocket->query($sql);
                
                if (!DB::isError($res) && $res) {
                    while ($row = $res->fetchRow()) {
                        $valid_users[intval($row[0])] = array(
                            'username' => $row[1],
                            'balance' => floatval($row[2]),
                            'invoices' => floatval($row[3])
                        );
                    }
                }
            }
        } else {
            if ($agent_id > 0) {
                $sql = sprintf("SELECT ub.id, ub.username, COALESCE(ub.money_balance, 0) as money_balance, COALESCE(ub.total_invoices_amount, 0) as total_invoices_amount 
                                FROM %s ub
                                INNER JOIN user_agent ua ON ub.id = ua.user_id
                                WHERE ua.agent_id = %d
                                GROUP BY ub.id, ub.username, ub.money_balance, ub.total_invoices_amount
                                ORDER BY ub.username",
                               $configValues['CONFIG_DB_TBL_DALOUSERBILLINFO'],
                               intval($agent_id));
            } else {
                $sql = sprintf("SELECT id, username, COALESCE(money_balance, 0) as money_balance, COALESCE(total_invoices_amount, 0) as total_invoices_amount 
                                FROM %s
                                ORDER BY username",
                               $configValues['CONFIG_DB_TBL_DALOUSERBILLINFO']);
            }
            $res = $dbSocket->query($sql);
            
            if (!DB::isError($res) && $res) {
                while ($row = $res->fetchRow()) {
                    $valid_users[intval($row[0])] = array(
                        'username' => $row[1],
                        'balance' => floatval($row[2]),
                        'invoices' => floatval($row[3])
                    );
                }
            } else if (DB::isError($res)) {
                error_log("Error loading users for agent $agent_id: " . $res->getMessage());
            }
        }
    } catch (Exception $e) {
        error_log("Exception loading users: " . $e->getMessage());
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
        if (array_key_exists('csrf_token', $_POST) && isset($_POST['csrf_token']) && dalo_check_csrf_token($_POST['csrf_token'])) {
        
            $current_datetime = date('Y-m-d H:i:s');
            $currBy = $operator;
        
            $required_fields = array();

            $user_id = (array_key_exists('user_id', $_POST) && intval(trim($_POST['user_id'])) > 0)
                     ? intval(trim($_POST['user_id'])) : "";
            if (empty($user_id)) {
                $required_fields['user_id'] = t('all','UserId');
            }
            
            $add_amount = (array_key_exists('add_amount', $_POST) && is_numeric(trim($_POST['add_amount'])))
                         ? floatval(trim($_POST['add_amount'])) : 0;
            if ($add_amount <= 0) {
                $required_fields['add_amount'] = "Amount to add";
            }
            
            $add_notes = (array_key_exists('add_notes', $_POST) && !empty(trim($_POST['add_notes'])))
                       ? trim($_POST['add_notes']) : "Balance added by operator";
            
            if (count($required_fields) > 0) {
                $failureMsg = sprintf("Empty or invalid required field(s) [%s]", implode(", ", array_values($required_fields)));
                $logAction .= "$failureMsg on page: ";
            } else {
                
                if (!$mysqli || !($mysqli instanceof mysqli)) {
                    $failureMsg = "Database connection error. Please contact administrator.";
                    $logAction .= "mysqli connection not available for balance addition on page: ";
                } else {
                
                try {
                    $user_data = get_user_balance_by_id($mysqli, $user_id);
                    if (!$user_data) {
                        throw new Exception("User not found");
                    }
                    
                    $username = $user_data['username'];
                    $old_balance = $user_data['balance'];
                    $new_balance = $old_balance + $add_amount;
                    
                    $sql_update = sprintf("UPDATE %s SET money_balance = %.2f WHERE id = %d",
                                         $configValues['CONFIG_DB_TBL_DALOUSERBILLINFO'],
                                         $new_balance,
                                         intval($user_id));
                    $res = $dbSocket->query($sql_update);
                    $logDebugSQL .= "$sql_update;\n";
                    
                    if (DB::isError($res)) {
                        throw new Exception("Failed to update balance: " . $res->getMessage());
                    }
                    
                    $successMsg = sprintf(
                        "<strong>Balance Added Successfully!</strong><br><br>" .
                        "Username: <strong>%s</strong><br>" .
                        "Amount Added: <strong>$%.2f</strong><br>" .
                        "Previous Balance: <strong>$%.2f</strong><br>" .
                        "New Balance: <strong>$%.2f</strong><br>" .
                        "Notes: <strong>%s</strong><br><br>" .
                        '<a href="bill-payments-list.php?username=%s" title="Payment History">View Payment History</a>',
                        htmlspecialchars($username),
                        $add_amount,
                        $old_balance,
                        $new_balance,
                        htmlspecialchars($add_notes),
                        urlencode($username)
                    );
                    
                    $logAction .= sprintf(
                        "Successfully added balance: User=%s, Amount=$%.2f, OldBalance=$%.2f, NewBalance=$%.2f on page: ",
                        $username,
                        $add_amount,
                        $old_balance,
                        $new_balance
                    );
                    
                } catch (Exception $e) {
                    $failureMsg = "<strong>Balance Addition Failed:</strong><br>" . htmlspecialchars($e->getMessage());
                    $logAction .= "Balance addition failed: " . $e->getMessage() . " on page: ";
                }
                
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

    $title = "Add Balance";
    $help = "Add prepaid balance credit to user accounts.";
    
    print_html_prologue($title, $langCode);
    
    print_title_and_help($title, $help);

    include_once('include/management/actionMessages.php');

    if (!isset($successMsg)) {
?>

<style>
.balance-info {
    background: #e3f2fd;
    border: 2px solid #2196f3;
    padding: 15px;
    border-radius: 5px;
    margin: 15px 0;
}
.balance-positive {
    color: #4caf50;
    font-weight: bold;
}
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            
            <div class="card">
                <div class="card-header">
                    <h3>Add Balance to User Account</h3>
                    <p class="text-muted">Add prepaid balance credit to user accounts.</p>
                </div>
                
                <div class="card-body">
                    
                    <?php if (!$is_current_operator_agent && count($valid_agents) > 0): ?>
                    <form method="GET" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="mb-3">
                        <div class="form-group">
                            <label for="agent_id">Select Agent:</label>
                            <select name="agent_id" id="agent_id" class="form-control" onchange="this.form.submit()">
                                <option value="">-- Select Agent --</option>
                                <?php foreach ($valid_agents as $id => $name): ?>
                                <option value="<?php echo $id; ?>" <?php echo ($agent_id == $id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                    <?php endif; ?>
                    
                    <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" id="balanceForm">
                        
                        <input type="hidden" name="csrf_token" value="<?php echo dalo_csrf_token(); ?>">
                        
                        <?php if (count($valid_users) > 0): ?>
                        <div class="form-group">
                            <label for="user_id">Select User: <span class="text-danger">*</span></label>
                            <select name="user_id" id="user_id" class="form-control" required onchange="loadUserBalance()">
                                <option value="">-- Select User --</option>
                                <?php foreach ($valid_users as $uid => $udata): ?>
                                <option value="<?php echo $uid; ?>" 
                                        data-username="<?php echo htmlspecialchars($udata['username']); ?>"
                                        data-balance="<?php echo $udata['balance']; ?>"
                                        data-invoices="<?php echo $udata['invoices']; ?>">
                                    <?php echo htmlspecialchars($udata['username']); ?> 
                                    (Balance: $<?php echo number_format($udata['balance'], 2); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div id="balanceInfo" style="display:none;" class="balance-info">
                            <h5>User Balance Information</h5>
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>Username:</strong></td>
                                    <td id="display_username">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Current Balance:</strong></td>
                                    <td><span id="display_balance" class="balance-positive">$0.00</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Unpaid Invoices:</strong></td>
                                    <td><span id="display_invoices">$0.00</span></td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="form-group">
                            <label for="add_amount">Amount to Add ($): <span class="text-danger">*</span></label>
                            <input type="number" 
                                   name="add_amount" 
                                   id="add_amount" 
                                   class="form-control" 
                                   step="0.01" 
                                   min="0.01" 
                                   max="300000" 
                                   required
                                   placeholder="Enter amount">
                            <small class="form-text text-muted">Maximum: $300,000.00</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="add_notes">Notes:</label>
                            <textarea name="add_notes" 
                                      id="add_notes" 
                                      class="form-control" 
                                      rows="3"><?php echo isset($add_notes) ? htmlspecialchars($add_notes) : 'Balance added by operator'; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-success btn-lg" id="submitBtn">
                                ➕ Add Balance
                            </button>
                            <a href="bill-payments-list.php" class="btn btn-secondary">Cancel</a>
                        </div>
                        
                        <?php else: ?>
                        <div class="alert alert-info">
                            <?php if ($agent_id > 0): ?>
                            No users found for selected agent. Please create users or select a different agent.
                            <?php else: ?>
                            Please select an agent first.
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
    invoices: 0
};

function loadUserBalance() {
    const select = document.getElementById('user_id');
    const selectedOption = select.options[select.selectedIndex];
    
    if (!selectedOption.value) {
        document.getElementById('balanceInfo').style.display = 'none';
        return;
    }
    
    const username = selectedOption.dataset.username;
    const balance = parseFloat(selectedOption.dataset.balance);
    const invoices = parseFloat(selectedOption.dataset.invoices);
    
    currentUserData.balance = balance;
    currentUserData.invoices = invoices;
    
    document.getElementById('display_username').textContent = username;
    document.getElementById('display_balance').textContent = '$' + balance.toFixed(2);
    document.getElementById('display_invoices').textContent = '$' + invoices.toFixed(2);
    
    document.getElementById('balanceInfo').style.display = 'block';
}

document.addEventListener('DOMContentLoaded', function() {
    const userSelect = document.getElementById('user_id');
    if (userSelect && userSelect.value) {
        loadUserBalance();
    }
});
</script>

<?php
    }
    
    include('../common/includes/layout_footer.php');
?>
