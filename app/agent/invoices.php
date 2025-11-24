<?php
/*
 *********************************************************************************************************
 * daloRADIUS - AGENT PORTAL - INVOICES
 * View user invoices
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

    $status_filter = (array_key_exists('status', $_REQUEST) && !empty(trim($_REQUEST['status'])))
                   ? trim($_REQUEST['status']) : "";

    $invoices = array();
    try {
        $sql = sprintf("SELECT i.id, ub.username, i.total_amount, i.paid_amount, i.outstanding_amount, i.status, i.creation_date 
                        FROM %s i
                        INNER JOIN %s ub ON i.user_id = ub.id
                        INNER JOIN user_agent ua ON ub.id = ua.user_id
                        WHERE ua.agent_id = %d",
                       $configValues['CONFIG_DB_TBL_DALOBILLINGINVOICE'],
                       $configValues['CONFIG_DB_TBL_DALOUSERBILLINFO'],
                       intval($current_agent_id));
        
        if (!empty($status_filter)) {
            $sql .= sprintf(" AND i.status = '%s'", $dbSocket->escapeSimple($status_filter));
        }
        
        $sql .= " ORDER BY i.creation_date DESC LIMIT 500";
        
        $res = $dbSocket->query($sql);
        
        if (!DB::isError($res) && $res) {
            while ($row = $res->fetchRow()) {
                $invoices[] = array(
                    'id' => $row[0],
                    'username' => $row[1],
                    'total' => floatval($row[2]),
                    'paid' => floatval($row[3]),
                    'outstanding' => floatval($row[4]),
                    'status' => $row[5],
                    'date' => $row[6]
                );
            }
        }
    } catch (Exception $e) {
        error_log("Exception loading invoices: " . $e->getMessage());
    }

    include($app_base . 'common/includes/db_close.php');

    $title = "Invoices";
    $help = "View invoices for your users.";
    
    print_html_prologue($title, $langCode);
    
    print_title_and_help($title, $help);

    include_once($app_base . 'operators/include/management/actionMessages.php');
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            
            <div class="card">
                <div class="card-header">
                    <h3>Invoices</h3>
                </div>
                
                <div class="card-body">
                    
                    <form method="GET" class="mb-3">
                        <div class="form-group">
                            <label for="status">Filter by Status:</label>
                            <select name="status" id="status" class="form-control">
                                <option value="">-- All Statuses --</option>
                                <option value="pending" <?php echo ($status_filter === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="unpaid" <?php echo ($status_filter === 'unpaid') ? 'selected' : ''; ?>>Unpaid</option>
                                <option value="partial" <?php echo ($status_filter === 'partial') ? 'selected' : ''; ?>>Partial</option>
                                <option value="paid" <?php echo ($status_filter === 'paid') ? 'selected' : ''; ?>>Paid</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-sm btn-secondary">Clear</a>
                    </form>
                    
                    <?php if (count($invoices) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Username</th>
                                    <th>Total Amount</th>
                                    <th>Paid Amount</th>
                                    <th>Outstanding</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $invoice): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($invoice['id']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($invoice['username']); ?></td>
                                    <td>$<?php echo number_format($invoice['total'], 2); ?></td>
                                    <td><span class="text-success">$<?php echo number_format($invoice['paid'], 2); ?></span></td>
                                    <td>
                                        <span class="<?php echo ($invoice['outstanding'] > 0) ? 'text-danger' : 'text-success'; ?>">
                                            $<?php echo number_format($invoice['outstanding'], 2); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge 
                                            <?php 
                                                switch($invoice['status']) {
                                                    case 'paid': echo 'bg-success'; break;
                                                    case 'partial': echo 'bg-warning'; break;
                                                    case 'unpaid': echo 'bg-danger'; break;
                                                    default: echo 'bg-secondary';
                                                }
                                            ?>">
                                            <?php echo htmlspecialchars($invoice['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($invoice['date']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted">Showing <?php echo count($invoices); ?> invoice(s)</p>
                    <?php else: ?>
                    <div class="alert alert-info">
                        No invoices found.
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
