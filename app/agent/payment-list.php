<?php
/*
 *********************************************************************************************************
 * daloRADIUS - AGENT PORTAL - PAYMENT LIST
 * View payment history and transactions
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
    
    $log = "visited page: ";
    $logAction = "";

    include($app_base . 'common/includes/db_open.php');
    
    $is_current_operator_agent = isCurrentOperatorAgent($dbSocket, $operator_id, $configValues);
    $current_agent_id = $is_current_operator_agent ? getCurrentOperatorAgentId($dbSocket, $operator_id, $configValues) : 0;
    
    if (!$is_current_operator_agent || $current_agent_id <= 0) {
        header('Location: index.php');
        exit;
    }

    $username_filter = (array_key_exists('username', $_REQUEST) && !empty(trim($_REQUEST['username'])))
                     ? trim($_REQUEST['username']) : "";

    $payments = array();
    try {
        $sql = "SELECT p.id, p.username, p.amount, p.payment_date, p.notes, p.createdby 
                FROM payment p 
                INNER JOIN user_agent ua ON (SELECT id FROM %s WHERE username = p.username) = ua.user_id 
                WHERE ua.agent_id = %d";
        
        if (!empty($username_filter)) {
            $sql .= sprintf(" AND p.username = '%s'", $dbSocket->escapeSimple($username_filter));
        }
        
        $sql .= " ORDER BY p.payment_date DESC LIMIT 500";
        
        $sql = sprintf($sql, $configValues['CONFIG_DB_TBL_DALOUSERBILLINFO'], intval($current_agent_id));
        $res = $dbSocket->query($sql);
        
        if (!DB::isError($res) && $res) {
            while ($row = $res->fetchRow()) {
                $payments[] = array(
                    'id' => $row[0],
                    'username' => $row[1],
                    'amount' => floatval($row[2]),
                    'date' => $row[3],
                    'notes' => $row[4],
                    'createdby' => $row[5]
                );
            }
        }
    } catch (Exception $e) {
        error_log("Exception loading payments: " . $e->getMessage());
    }

    include($app_base . 'common/includes/db_close.php');

    $title = "Payment History";
    $help = "View payment transactions for your users.";
    
    print_html_prologue($title, $langCode);
    
    print_title_and_help($title, $help);

    include_once($app_base . 'operators/include/management/actionMessages.php');
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            <h3>Payment History</h3>
                        </div>
                        <div class="col-md-6 text-right">
                            <a href="payment-new.php" class="btn btn-primary">
                                <i class="bi bi-plus"></i> New Payment
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    
                    <form method="GET" class="mb-3">
                        <div class="form-group">
                            <label for="username">Filter by Username:</label>
                            <input type="text" name="username" id="username" class="form-control" 
                                   value="<?php echo htmlspecialchars($username_filter); ?>" placeholder="Enter username">
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary">Search</button>
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-sm btn-secondary">Clear</a>
                    </form>
                    
                    <?php if (count($payments) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Notes</th>
                                    <th>Created By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['id']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['username']); ?></td>
                                    <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($payment['date']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['notes']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['createdby']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted">Showing <?php echo count($payments); ?> payment(s)</p>
                    <?php else: ?>
                    <div class="alert alert-info">
                        No payments found.
                    </div>
                    <?php endif; ?>
                    
                </div>
            </div>
            
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-12">
            <a href="index.php" class="btn btn-secondary">Back to Home</a>
        </div>
    </div>
</div>

<?php
    include($app_base . 'common/includes/layout_footer.php');
?>
