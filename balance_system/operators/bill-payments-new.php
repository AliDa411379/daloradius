<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform - BALANCE SYSTEM PAYMENT
 * Modified version that ONLY accepts payments from user account balance
 * 
 * This is part of the Balance System implementation.
 * ALL payments are deducted from user balance - no cash/check/transfer allowed.
 *
 *********************************************************************************************************
 */
 
    include("../../../app/operators/library/checklogin.php");
    $operator = $_SESSION['operator_user'];
    $operator_id = $_SESSION['operator_id'];

    include('../../../app/operators/library/check_operator_perm.php');
    include_once('../../../app/common/includes/config_read.php');
    include_once("../../../app/operators/library/agent_functions.php");

    include_once("../../../app/operators/lang/main.php");
    include_once("../../../app/common/includes/validation.php");
    include("../../../app/common/includes/layout.php");
    include_once("../../../app/operators/include/management/populate_selectbox.php");
    
    // Include balance system library
    require_once(__DIR__ . '/../../balance_system/library/balance_functions.php');
    
    // init logging variables
    $log = "visited page: ";
    $logAction = "";
    $logDebugSQL = "";

    include('../../../app/common/includes/db_open.php');
    
    // Get client IP
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Valid agents check (if applicable)
    $is_current_operator_agent = is_operator_agent($dbSocket, $operator_id, $current_agent_id);
    
    $valid_agents = array();
    if (!$is_current_operator_agent) {
        $valid_agents = get_all_agents($dbSocket);
    }
    
    $agent_id = (!$is_current_operator_agent && array_key_exists('agent_id', $_REQUEST) && intval(trim($_REQUEST['agent_id'])) > 0)
              ? intval(trim($_REQUEST['agent_id'])) : $current_agent_id;

    // Get valid users for selected agent
    $valid_users = array();
    if ($agent_id > 0) {
        $sql = sprintf("SELECT u.id, u.username, u.money_balance, u.total_invoices_amount 
                        FROM %s u
                        INNER JOIN %s m ON u.username = m.username
                        WHERE m.agent_id = %d
                        ORDER BY u.username",
                       $configValues['CONFIG_DB_TBL_DALOUSERBILLINFO'],
                       $configValues['CONFIG_DB_TBL_DALOAGENT_USER_MAPPING'],
                       $agent_id);
        $res = $dbSocket->query($sql);
        
        if ($res) {
            while ($row = $res->fetchrow()) {
                $valid_users[intval($row[0])] = array(
                    'username' => $row[1],
                    'balance' => floatval($row[2]),
                    'invoices' => floatval($row[3])
                );
            }
        }
    }

    // PAYMENT PROCESSING
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
        if (array_key_exists('csrf_token', $_POST) && isset($_POST['csrf_token']) && dalo_check_csrf_token($_POST['csrf_token'])) {
        
            $current_datetime = date('Y-m-d H:i:s');
            $currBy = $operator;
        
            $required_fields = array();

            // Get user_id
            $user_id = (array_key_exists('user_id', $_POST) && intval(trim($_POST['user_id'])) > 0)
                     ? intval(trim($_POST['user_id'])) : "";
            if (empty($user_id)) {
                $required_fields['user_id'] = t('all','UserId');
            }
            
            // Get payment amount
            $payment_amount = (array_key_exists('payment_amount', $_POST) && is_numeric(trim($_POST['payment_amount'])))
                             ? floatval(trim($_POST['payment_amount'])) : 0;
            if ($payment_amount <= 0) {
                $required_fields['payment_amount'] = t('all','PaymentAmount');
            }
            
            // Get payment notes
            $payment_notes = (array_key_exists('payment_notes', $_POST) && !empty(trim($_POST['payment_notes'])))
                           ? trim($_POST['payment_notes']) : "Balance payment by operator";
            
            // Get invoice_id (optional - if specified, pay specific invoice, otherwise find oldest unpaid)
            $specific_invoice_id = (array_key_exists('invoice_id', $_POST) && intval(trim($_POST['invoice_id'])) > 0)
                                  ? intval(trim($_POST['invoice_id'])) : null;
            
            if (count($required_fields) > 0) {
                // Required field error
                $failureMsg = sprintf("Empty or invalid required field(s) [%s]", implode(", ", array_values($required_fields)));
                $logAction .= "$failureMsg on page: ";
            } else {
                
                // ==================== BALANCE PAYMENT PROCESSING ====================
                
                try {
                    // Get user details
                    $user_data = get_user_balance_by_id($dbSocket, $user_id);
                    if (!$user_data) {
                        throw new Exception("User not found");
                    }
                    
                    $username = $user_data['username'];
                    
                    // Find invoice to pay
                    $target_invoice_id = null;
                    
                    if ($specific_invoice_id) {
                        // Specific invoice requested
                        $target_invoice_id = $specific_invoice_id;
                    } else {
                        // Find oldest unpaid invoice
                        $unpaid_invoices = get_unpaid_invoices($dbSocket, $username);
                        if (count($unpaid_invoices) > 0) {
                            $target_invoice_id = $unpaid_invoices[0]['id'];
                        } else {
                            throw new Exception("No unpaid invoices found for this user");
                        }
                    }
                    
                    // Validate invoice
                    $invoice_details = get_invoice_details($dbSocket, $target_invoice_id);
                    if (!$invoice_details) {
                        throw new Exception("Invoice not found");
                    }
                    
                    // Verify invoice belongs to this user
                    if ($invoice_details['user_id'] != $user_id) {
                        throw new Exception("Invoice does not belong to this user");
                    }
                    
                    // Process payment from balance
                    $result = process_balance_payment(
                        $dbSocket,
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
                            '<a href="bill-invoice-edit.php?invoice_id=%d" title="View Invoice">View Invoice #%d</a> | ' .
                            '<a href="bill-payments-list.php?username=%s" title="Payment History">Payment History</a>',
                            htmlspecialchars($username),
                            $target_invoice_id,
                            $payment_amount,
                            $result['balance_before'],
                            $result['balance_after'],
                            $result['invoice_status'],
                            $result['outstanding'],
                            $target_invoice_id,
                            $target_invoice_id,
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

        } else {
            // CSRF token error
            $failureMsg = "CSRF token error";
            $logAction .= "$failureMsg on page: ";
        }
    }

    include('../../../app/common/includes/db_close.php');

    // print HTML prologue   
    $title = "New Payment (Balance Only)";
    $help = "Process payment from user account balance. All payments are deducted from the user's prepaid balance.";
    
    print_html_prologue($title, $langCode);
    
    print_title_and_help($title, $help);

    include_once('../../../app/operators/include/management/actionMessages.php');

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
.invoice-list {
    background: #f5f5f5;
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
                    <p class="text-muted">⚠️ <strong>IMPORTANT:</strong> All payments are deducted from user account balance. No other payment methods are available.</p>
                </div>
                
                <div class="card-body">
                    
                    <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" id="paymentForm">
                        
                        <?php
                        // CSRF token
                        dalo_csrf_field();
                        ?>
                        
                        <!-- Agent Selector (if not agent operator) -->
                        <?php if (!$is_current_operator_agent && count($valid_agents) > 0): ?>
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
                        <?php endif; ?>
                        
                        <!-- User Selector -->
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
                        
                        <!-- Balance Info Display -->
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
                                <tr>
                                    <td><strong>Available After Invoices:</strong></td>
                                    <td><span id="display_available">$0.00</span></td>
                                </tr>
                            </table>
                            
                            <div id="unpaidInvoicesList" class="invoice-list">
                                <strong>Unpaid Invoices:</strong>
                                <div id="invoiceDetails">Loading...</div>
                            </div>
                        </div>
                        
                        <!-- Payment Amount -->
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
                        
                        <!-- Payment Notes -->
                        <div class="form-group">
                            <label for="payment_notes">Payment Notes:</label>
                            <textarea name="payment_notes" 
                                      id="payment_notes" 
                                      class="form-control" 
                                      rows="3"><?php echo isset($payment_notes) ? htmlspecialchars($payment_notes) : 'Balance payment by operator'; ?></textarea>
                        </div>
                        
                        <!-- Warnings -->
                        <div id="paymentWarning" style="display:none;" class="balance-warning">
                            <strong>⚠️ Warning:</strong> <span id="warningText"></span>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                💳 Process Payment from Balance
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
const BALANCE_MIN_LIMIT = <?php echo BALANCE_MIN_LIMIT; ?>;
const BALANCE_MAX_PAYMENT = <?php echo BALANCE_MAX_PAYMENT; ?>;

let currentUserData = {
    balance: 0,
    invoices: 0,
    unpaidInvoices: []
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
    const available = balance - invoices;
    
    currentUserData.balance = balance;
    currentUserData.invoices = invoices;
    
    // Display balance info
    document.getElementById('display_username').textContent = username;
    document.getElementById('display_balance').textContent = '$' + balance.toFixed(2);
    document.getElementById('display_balance').className = balance >= 0 ? 'balance-positive' : 'balance-negative';
    document.getElementById('display_invoices').textContent = '$' + invoices.toFixed(2);
    document.getElementById('display_available').textContent = '$' + available.toFixed(2);
    
    document.getElementById('balanceInfo').style.display = 'block';
    
    // Load unpaid invoices via AJAX
    loadUnpaidInvoices(username);
}

function loadUnpaidInvoices(username) {
    const invoiceDiv = document.getElementById('invoiceDetails');
    invoiceDiv.innerHTML = 'Loading...';
    
    // You can implement an AJAX call here to fetch unpaid invoices
    // For now, showing a placeholder
    invoiceDiv.innerHTML = '<small>Select payment amount and click "Process Payment" to pay oldest unpaid invoice.</small>';
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
    
    if (newBalance < BALANCE_MIN_LIMIT) {
        warningText.textContent = `This payment would result in a balance of $${newBalance.toFixed(2)}, which exceeds the minimum limit of $${BALANCE_MIN_LIMIT.toFixed(2)}. Payment will be rejected.`;
        warningDiv.style.display = 'block';
        warningDiv.className = 'balance-warning';
        warningDiv.style.background = '#ffebee';
        warningDiv.style.borderColor = '#f44336';
        submitBtn.disabled = true;
    } else if (newBalance < 0) {
        warningText.textContent = `This payment will result in a negative balance of $${newBalance.toFixed(2)}. Are you sure?`;
        warningDiv.style.display = 'block';
        submitBtn.disabled = false;
    } else {
        warningDiv.style.display = 'none';
        submitBtn.disabled = false;
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const userSelect = document.getElementById('user_id');
    if (userSelect && userSelect.value) {
        loadUserBalance();
    }
});
</script>

<?php
    }
    
    include('../../../app/common/includes/layout_footer.php');
?>