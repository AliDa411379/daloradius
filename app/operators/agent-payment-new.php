<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform - AGENT PAYMENT
 * Record payments received from agents
 * 
 * Allows operators to record payments made by agents to the ISP.
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
        error_log('Agent payment mysqli error: ' . $e->getMessage());
        $mysqli = null;
    }
    
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Load Agents
    $valid_agents = array();
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
    
    $payment_types = array('Cash', 'Bank Transfer', 'Check', 'Credit Card', 'Other');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
        if (array_key_exists('csrf_token', $_POST) && isset($_POST['csrf_token']) && dalo_check_csrf_token($_POST['csrf_token'])) {
        
            $current_datetime = date('Y-m-d H:i:s');
            $currBy = $operator;
        
            $required_fields = array();

            $agent_id = (array_key_exists('agent_id', $_POST) && intval(trim($_POST['agent_id'])) > 0)
                      ? intval(trim($_POST['agent_id'])) : "";
            if (empty($agent_id)) {
                $required_fields['agent_id'] = "Agent";
            }
            
            $amount = (array_key_exists('amount', $_POST) && is_numeric(trim($_POST['amount'])))
                         ? floatval(trim($_POST['amount'])) : 0;
            if ($amount <= 0) {
                $required_fields['amount'] = "Amount";
            }
            
            $payment_type = (array_key_exists('payment_type', $_POST) && !empty(trim($_POST['payment_type'])))
                          ? trim($_POST['payment_type']) : "Cash";
            
            $transaction_id = (array_key_exists('transaction_id', $_POST)) ? trim($_POST['transaction_id']) : "";
            
            $notes = (array_key_exists('notes', $_POST)) ? trim($_POST['notes']) : "";
            
            if (count($required_fields) > 0) {
                $failureMsg = sprintf("Empty or invalid required field(s) [%s]", implode(", ", array_values($required_fields)));
                $logAction .= "$failureMsg on page: ";
            } else {
                
                if (!$mysqli || !($mysqli instanceof mysqli)) {
                    $failureMsg = "Database connection error. Please contact administrator.";
                    $logAction .= "mysqli connection not available for agent payment on page: ";
                } else {
                
                try {
                    // Start transaction
                    $mysqli->begin_transaction();
                    
                    // Insert into agent_payments
                    $stmt = $mysqli->prepare("INSERT INTO agent_payments (agent_id, amount, payment_type, transaction_id, notes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    if (!$stmt) {
                        throw new Exception("Prepare failed: " . $mysqli->error);
                    }
                    
                    $stmt->bind_param("idssss", $agent_id, $amount, $payment_type, $transaction_id, $notes, $operator);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Execute failed: " . $stmt->error);
                    }
                    
                    $payment_id = $stmt->insert_id;
                    $stmt->close();
                    
                    $mysqli->commit();
                    
                    $agent_name = $valid_agents[$agent_id] ?? 'Unknown Agent';
                    
                    $successMsg = sprintf(
                        "<strong>Payment Recorded Successfully!</strong><br><br>" .
                        "Agent: <strong>%s</strong><br>" .
                        "Amount: <strong>$%.2f</strong><br>" .
                        "Type: <strong>%s</strong><br>" .
                        "Transaction ID: <strong>%s</strong><br>" .
                        "Notes: <strong>%s</strong><br><br>" .
                        '<a href="rep-agent-payments.php" title="Payment Report">View Payment Report</a>',
                        htmlspecialchars($agent_name),
                        $amount,
                        htmlspecialchars($payment_type),
                        htmlspecialchars($transaction_id),
                        htmlspecialchars($notes)
                    );
                    
                    $logAction .= sprintf(
                        "Successfully recorded agent payment: Agent=%s, Amount=$%.2f, Type=%s on page: ",
                        $agent_name,
                        $amount,
                        $payment_type
                    );
                    
                } catch (Exception $e) {
                    $mysqli->rollback();
                    $failureMsg = "<strong>Payment Recording Failed:</strong><br>" . htmlspecialchars($e->getMessage());
                    $logAction .= "Agent payment failed: " . $e->getMessage() . " on page: ";
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

    $title = "Record Agent Payment";
    $help = "Record payments received from agents.";
    
    print_html_prologue($title, $langCode);
    
    print_title_and_help($title, $help);

    include_once('include/management/actionMessages.php');

    if (!isset($successMsg)) {
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            
            <div class="card">
                <div class="card-header">
                    <h3>Record Agent Payment</h3>
                    <p class="text-muted">Enter details of payment received from agent.</p>
                </div>
                
                <div class="card-body">
                    
                    <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                        
                        <input type="hidden" name="csrf_token" value="<?php echo dalo_csrf_token(); ?>">
                        
                        <div class="form-group">
                            <label for="agent_id">Select Agent: <span class="text-danger">*</span></label>
                            <select name="agent_id" id="agent_id" class="form-control" required>
                                <option value="">-- Select Agent --</option>
                                <?php foreach ($valid_agents as $id => $name): ?>
                                <option value="<?php echo $id; ?>">
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="amount">Amount ($): <span class="text-danger">*</span></label>
                            <input type="number" 
                                   name="amount" 
                                   id="amount" 
                                   class="form-control" 
                                   step="0.01" 
                                   min="0.01" 
                                   required
                                   placeholder="Enter amount">
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_type">Payment Type: <span class="text-danger">*</span></label>
                            <select name="payment_type" id="payment_type" class="form-control" required>
                                <?php foreach ($payment_types as $type): ?>
                                <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="transaction_id">Transaction ID / Reference:</label>
                            <input type="text" 
                                   name="transaction_id" 
                                   id="transaction_id" 
                                   class="form-control" 
                                   placeholder="e.g., Bank Ref, Check No">
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Notes:</label>
                            <textarea name="notes" 
                                      id="notes" 
                                      class="form-control" 
                                      rows="3"
                                      placeholder="Optional notes"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-lg">
                                💾 Record Payment
                            </button>
                            <a href="rep-agent-payments.php" class="btn btn-secondary">Cancel</a>
                        </div>
                        
                    </form>
                    
                </div>
            </div>
            
        </div>
    </div>
</div>

<?php
    }
    
    include('../common/includes/layout_footer.php');
?>
