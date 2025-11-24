<?php
/*
 *********************************************************************************************************
 * daloRADIUS - AGENT PORTAL - HOME
 * Agent management dashboard
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

    // Include required files
    include_once($app_base . 'common/includes/config_read.php');
    include_once($app_base . "operators/library/agent_functions.php");
    include_once($app_base . "operators/lang/main.php");
    include_once($app_base . "common/includes/validation.php");
    include($app_base . "common/includes/layout.php");
    
    $log = "visited agent home page: ";
    $logAction = "";

    include($app_base . 'common/includes/db_open.php');
    
    // Get agent info - but don't redirect if not an agent, just show message
    $is_current_operator_agent = isCurrentOperatorAgent($dbSocket, $operator_id, $configValues);
    $current_agent_id = 0;
    $failureMsg = "";
    
    if ($is_current_operator_agent) {
        $current_agent_id = getCurrentOperatorAgentId($dbSocket, $operator_id, $configValues);
        if (!$current_agent_id || $current_agent_id <= 0) {
            $failureMsg = "Access denied. Could not determine agent ID.";
        }
    } else {
        $failureMsg = "Access denied. You are not an agent operator.";
    }

    include($app_base . 'common/includes/db_close.php');

    $title = "Agent Portal";
    $help = "Manage your users, balances, and payments.";
    $langCode = isset($configValues['CONFIG_LANG']) ? $configValues['CONFIG_LANG'] : 'en';
    
    print_html_prologue($title, $langCode);
    
    print_title_and_help($title, $help);

    include_once($app_base . 'operators/include/management/actionMessages.php');
?>

<style>
.dashboard-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 8px;
    padding: 30px;
    margin: 15px 0;
    text-decoration: none;
    transition: transform 0.2s, box-shadow 0.2s;
    display: block;
}

.dashboard-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.2);
    color: white;
    text-decoration: none;
}

.dashboard-card h4 {
    margin: 0 0 10px 0;
    font-size: 24px;
}

.dashboard-card p {
    margin: 0;
    font-size: 14px;
    opacity: 0.9;
}

.dashboard-card.users { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.dashboard-card.balance { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.dashboard-card.payments { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.dashboard-card.invoices { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
.dashboard-card.history { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
</style>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <h2 class="mb-4">Welcome to Agent Portal, <?php echo htmlspecialchars($operator); ?></h2>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <a href="users/list.php" class="dashboard-card users">
                <h4>👥 Manage Users</h4>
                <p>Add, edit, or view your user accounts</p>
            </a>
        </div>

        <div class="col-md-6">
            <a href="add-balance.php" class="dashboard-card balance">
                <h4>➕ Add Balance</h4>
                <p>Add prepaid balance to user accounts</p>
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <a href="payment-new.php" class="dashboard-card payments">
                <h4>💳 Process Payment</h4>
                <p>Process payment from user balance</p>
            </a>
        </div>

        <div class="col-md-6">
            <a href="payment-list.php" class="dashboard-card history">
                <h4>📋 Payment History</h4>
                <p>View payment transactions</p>
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <a href="invoices.php" class="dashboard-card invoices">
                <h4>📄 Invoices</h4>
                <p>View and manage user invoices</p>
            </a>
        </div>
    </div>

    <div class="row mt-5">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-light">
                    <h5>Quick Stats</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php
                            $stats = array(
                                'total_users' => 0,
                                'total_balance' => 0,
                                'unpaid_invoices' => 0
                            );
                            
                            if ($current_agent_id > 0) {
                                include($app_base . 'common/includes/db_open.php');
                                
                                try {
                                    // Query 1: Total Users
                                    $sql = sprintf("SELECT COUNT(DISTINCT ua.user_id) FROM user_agent ua WHERE ua.agent_id = %d", intval($current_agent_id));
                                    $res = $dbSocket->query($sql);
                                    if ($res && $row = $res->fetchRow()) {
                                        $stats['total_users'] = intval($row[0]);
                                    }
                                    
                                    // Query 2: Total Balance
                                    if (isset($configValues['CONFIG_DB_TBL_DALOUSERBILLINFO'])) {
                                        $sql = sprintf("SELECT SUM(COALESCE(ub.money_balance, 0)) FROM %s ub INNER JOIN user_agent ua ON ub.id = ua.user_id WHERE ua.agent_id = %d", 
                                                     $configValues['CONFIG_DB_TBL_DALOUSERBILLINFO'],
                                                     intval($current_agent_id));
                                        $res = $dbSocket->query($sql);
                                        if ($res && $row = $res->fetchRow()) {
                                            $stats['total_balance'] = floatval($row[0]);
                                        }
                                        
                                        // Query 3: Unpaid Invoices
                                        $sql = sprintf("SELECT SUM(COALESCE(ub.total_invoices_amount, 0)) FROM %s ub INNER JOIN user_agent ua ON ub.id = ua.user_id WHERE ua.agent_id = %d", 
                                                     $configValues['CONFIG_DB_TBL_DALOUSERBILLINFO'],
                                                     intval($current_agent_id));
                                        $res = $dbSocket->query($sql);
                                        if ($res && $row = $res->fetchRow()) {
                                            $stats['unpaid_invoices'] = floatval($row[0]);
                                        }
                                    }
                                } catch (Exception $e) {
                                    // Silently fail - stats will show zeros
                                }
                                
                                include($app_base . 'common/includes/db_close.php');
                            }
                        ?>
                        <div class="col-md-4">
                            <strong>Total Users:</strong>
                            <?php echo htmlspecialchars($stats['total_users']); ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Total Balance:</strong>
                            <?php echo '$' . number_format($stats['total_balance'], 2); ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Unpaid Invoices:</strong>
                            <?php echo '$' . number_format($stats['unpaid_invoices'], 2); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
    include($app_base . 'common/includes/layout_footer.php');
?>
