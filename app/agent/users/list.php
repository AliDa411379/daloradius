<?php
/*
 *********************************************************************************************************
 * daloRADIUS - AGENT PORTAL - USERS LIST
 * Manage user accounts
 *
 *********************************************************************************************************
 */
 
    $app_base = __DIR__ . '/../../';
    
    session_start();
    
    if (!isset($_SESSION['operator_user']) || !isset($_SESSION['operator_id'])) {
        header("Location: ../login.php");
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
        header('Location: ../index.php');
        exit;
    }

    $search_filter = (array_key_exists('search', $_REQUEST) && !empty(trim($_REQUEST['search'])))
                   ? trim($_REQUEST['search']) : "";

    $users = array();
    try {
        $sql = sprintf("SELECT ub.id, ub.username, COALESCE(ub.money_balance, 0) as balance, COALESCE(ub.total_invoices_amount, 0) as invoices,
                               ui.creationdate, ui.createdby
                        FROM %s ub
                        LEFT JOIN %s ui ON ub.username = ui.username
                        INNER JOIN user_agent ua ON ub.id = ua.user_id
                        WHERE ua.agent_id = %d",
                       $configValues['CONFIG_DB_TBL_DALOUSERBILLINFO'],
                       $configValues['CONFIG_DB_TBL_DALOUSERINFO'],
                       intval($current_agent_id));
        
        if (!empty($search_filter)) {
            $sql .= sprintf(" AND ub.username LIKE '%%%s%%'", $dbSocket->escapeSimple($search_filter));
        }
        
        $sql .= " ORDER BY ub.username LIMIT 500";
        
        $res = $dbSocket->query($sql);
        
        if (!DB::isError($res) && $res) {
            while ($row = $res->fetchRow()) {
                $users[] = array(
                    'id' => $row[0],
                    'username' => $row[1],
                    'balance' => floatval($row[2]),
                    'invoices' => floatval($row[3]),
                    'created' => $row[4],
                    'created_by' => $row[5]
                );
            }
        }
    } catch (Exception $e) {
        error_log("Exception loading users: " . $e->getMessage());
    }

    include($app_base . 'common/includes/db_close.php');

    $title = "Users";
    $help = "Manage your user accounts.";
    
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
                            <h3>User Accounts</h3>
                        </div>
                        <div class="col-md-6 text-right">
                            <a href="add.php" class="btn btn-primary">
                                <i class="bi bi-plus"></i> Add New User
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    
                    <form method="GET" class="mb-3">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" 
                                   value="<?php echo htmlspecialchars($search_filter); ?>" placeholder="Search by username">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-primary">Search</button>
                                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">Clear</a>
                            </div>
                        </div>
                    </form>
                    
                    <?php if (count($users) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Username</th>
                                    <th>Balance</th>
                                    <th>Invoices</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="<?php echo ($user['balance'] >= 0) ? 'text-success' : 'text-danger'; ?>">
                                            $<?php echo number_format($user['balance'], 2); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="<?php echo ($user['invoices'] > 0) ? 'text-danger' : 'text-success'; ?>">
                                            $<?php echo number_format($user['invoices'], 2); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['created']); ?></td>
                                    <td>
                                        <a href="edit.php?user_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                        <a href="../payment-new.php?user_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info">Payment</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted">Showing <?php echo count($users); ?> user(s)</p>
                    <?php else: ?>
                    <div class="alert alert-info">
                        No users found.
                        <a href="add.php" class="btn btn-sm btn-primary">Create your first user</a>
                    </div>
                    <?php endif; ?>
                    
                </div>
            </div>
            
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-12">
            <a href="../index.php" class="btn btn-secondary">Back to Home</a>
        </div>
    </div>
</div>

<?php
    include($app_base . 'common/includes/layout_footer.php');
?>
