<?php
/*
 *********************************************************************************************************
 * daloRADIUS - AGENT PORTAL - PROCESS PAYMENT
 * Process payment from user account balance
 *
 *********************************************************************************************************
 */
 
    $app_base = __DIR__ . '/../';
    
    session_start();
    
    if (!isset($_SESSION['operator_user']) || !isset($_SESSION['operator_id'])) {
        header("Location: login.php");
        exit();
    }
    
    $operator = $_SESSION['operator_user'];
    $operator_id = $_SESSION['operator_id'];

    include_once($app_base . 'common/includes/config_read.php');
    include_once($app_base . "operators/library/agent_functions.php");

    include_once($app_base . "operators/lang/main.php");
    include_once($app_base . "common/includes/validation.php");
    include($app_base . "common/includes/layout.php");
    include_once($app_base . "operators/include/management/populate_selectbox.php");
    
    require_once($app_base . 'common/library/balance_functions.php');
    
    $log = "visited page: ";
    $logAction = "";
    $logDebugSQL = "";

    include($app_base . 'common/includes/db_open.php');
    
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
        error_log('Balance payment mysqli error: ' . $e->getMessage());
        $mysqli = null;
    }
    
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $is_current_operator_agent = isCurrentOperatorAgent($dbSocket, $operator_id, $configValues);
    $current_agent_id = $is_current_operator_agent ? getCurrentOperatorAgentId($dbSocket, $operator_id, $configValues) : 0;
    
    if (!$is_current_operator_agent || $current_agent_id <= 0) {
        header('Location: index.php');
        exit;
    }

    $valid_users = array();
    try {
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
            
            $payment_amount = (array_key_exists('payment_amount', $_POST) && is_numeric(trim($_POST['payment_amount'])))
                             ? floatval(trim($_POST['payment_amount'])) : 0;
            if ($payment_amount <= 0) {
                $required_fields['payment_amount'] = "Payment Amount";
            }
            
            $payment_notes = (array_key_exists('payment_notes', $_POST) && !empty(trim($_POST['payment_notes'])))
                           ? trim($_POST['payment_notes']) : "Balance payment by agent";
            
            $specific_invoice_id = (array_key_exists('invoice_id', $_POST) && intval(trim($_POST['invoice_id'])) > 0)
                                  ? intval(trim($_POST['invoice_id'])) : null;
            
            if (count($required_fields) > 0) {
                $failureMsg = sprintf("Empty or invalid required field(s) [%s]", implode(", ", array_values($required_fields)));
                $logAction .= "$failureMsg on page: ";
            } else {
                
                if (!$mysqli || !($mysqli instanceof mysqli)) {
                    $failureMsg = "Database connection error. Please contact administrator.";
                    $logAction .= "mysqli connection not available for payment processing on page: ";
                } else {
                
                try {
                    $user_data = get_user_balance_by_id($mysqli, $user_id);
                    if (!$user_data) {
                        throw new Exception("User not found");
                    }
                    
                    $username = $user_data['username'];
                    
                    $target_invoice_id = null;
                    
                    if ($specific_invoice_id) {
                        $target_invoice_id = $specific_invoice_id;
                    } else {
                        $unpaid_invoices = get_unpaid_invoices($mysqli, $username);
                        if (count($unpaid_invoices) > 0) {
                            $target_invoice_id = $unpaid_invoices[0]['id'];
                        } else {
                            throw new Exception("No unpaid invoices found for this user");
                        }
                    }
                    
                    $invoice_details = get_invoice_details($mysqli, $target_invoice_id);
                    if (!$invoice_details) {
                        throw new Exception("Invoice not found");
                    }
                    
                    if ($invoice_details['user_id'] != $user_id) {
                        throw new Exception("Invoice does not belong to this user");
                    }
                    
                    $result = process_balance_payment(
                        $mysqli,
                        $target_invoice_id,
                        $payment_amount,
                        $operator,
                        $payment_notes,
                        $client_ip
                    );
                    
                    if ($result['success']) {
                        $successMsg = sprintf(
                            "<strong>Payment Successful!</strong><br><br>" .
                            "Username: <strong>%s</strong><br>" .
                            "Invoice #: <strong>%d</strong><br>" .
                            "Amount Paid: <strong>$%.2f</strong><br>" .
                            "Previous Balance: <strong>$%.2f</strong><br>" .
                            "New Balance: <strong>$%.2f</strong><br>" .
                            "Invoice Status: <strong>%s</strong><br>" .
                            "Outstanding: <strong>$%.2f</strong><br><br>" .
                            '<a href="payment-list.php?username=%s" title="Payment History">View Payment History</a>',
                            htmlspecialchars($username),
                            $target_invoice_id,
                            $payment_amount,
                            $result['balance_before'],
                            $result['balance_after'],
                            $result['invoice_status'],
                            $result['outstanding'],
                            urlencode($username)
                        );
                        
                        $logAction .= sprintf(
                            "Successfully processed balance payment: User=%s, Amount=$%.2f, Invoice=#%d, NewBalance=$%.2f on page: ",
                            $username,
                            $payment_amount,
                            $target_invoice_id,
                            $result['balance_after']
                        );
                        
                    } else {
                        throw new Exception($result['message']);
                    }
                    
                } catch (Exception $e) {
                    $failureMsg = "<strong>Payment Failed:</strong><br>" . htmlspecialchars($e->getMessage());
                    $logAction .= "Payment failed: " . $e->getMessage() . " on page: ";
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
    
    include($app_base . 'common/includes/db_close.php');

    $title = "Process Payment";
    $help = "Process payment from user account balance.";
    
    print_html_prologue($title, $langCode);
    
    print_title_and_help($title, $help);

    include_once($app_base . 'operators/include/management/actionMessages.php');

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
.balance-negative {
    color: #f44336;
    font-weight: bold;
}
.balance-warning {
    background: #fff3cd;
    border: 2px solid #ffc107;
    padding: 10px;
    border-radius: 5px;
    margin: 10px 0;
}
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            
            <div class="card">
                <div class="card-header">
                    <h3>Process Payment from Balance</h3>
                    <p class="text-muted">⚠️ All payments are deducted from user account balance.</p>
                </div>
                
                <div class="card-body">
                    
                    <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" id="paymentForm">
                        
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
                            <label for="payment_amount">Payment Amount ($): <span class="text-danger">*</span></label>
                            <input type="number" 
                                   name="payment_amount" 
                                   id="payment_amount" 
                                   class="form-control" 
                                   step="0.01" 
                                   min="0.01" 
                                   max="300000" 
                                   required
                                   onchange="validatePayment()">
                            <small class="form-text text-muted">Maximum: $300,000.00</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_notes">Payment Notes:</label>
                            <textarea name="payment_notes" 
                                      id="payment_notes" 
                                      class="form-control" 
                                      rows="3"><?php echo isset($payment_notes) ? htmlspecialchars($payment_notes) : 'Balance payment by agent'; ?></textarea>
                        </div>
                        
                        <div id="paymentWarning" style="display:none;" class="balance-warning">
                            <strong>⚠️ Warning:</strong> <span id="warningText"></span>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                💳 Process Payment
                            </button>
                            <a href="index.php" class="btn btn-secondary">Back</a>
                        </div>
                        
                        <?php else: ?>
                        <div class="alert alert-info">
                            No users found.
                        </div>
                        <a href="index.php" class="btn btn-secondary">Back</a>
                        <?php endif; ?>
                        
                    </form>
                    
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
const BALANCE_MIN_LIMIT = 0;
const BALANCE_MAX_PAYMENT = 300000;

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

function validatePayment() {
    const amountInput = document.getElementById('payment_amount');
    const amount = parseFloat(amountInput.value);
    const warningDiv = document.getElementById('paymentWarning');
    const warningText = document.getElementById('warningText');
    const submitBtn = document.getElementById('submitBtn');
    
    if (isNaN(amount) || amount <= 0) {
        return;
    }
    
    const newBalance = currentUserData.balance - amount;
    
    if (newBalance < 0) {
        warningText.textContent = `This payment will result in a negative balance. Proceed?`;
        warningDiv.style.display = 'block';
        submitBtn.disabled = false;
    } else {
        warningDiv.style.display = 'none';
        submitBtn.disabled = false;
    }
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
    
    include($app_base . 'common/includes/layout_footer.php');
?>
