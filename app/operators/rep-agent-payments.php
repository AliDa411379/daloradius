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
 * Description:    Agent Payment Report
 *
 *********************************************************************************************************
 */

    include("library/checklogin.php");
    $operator = $_SESSION['operator_user'];

    include('library/check_operator_perm.php');
    include_once('../common/includes/config_read.php');
    include_once("lang/main.php");
    include("../common/includes/layout.php");

    $log = "visited page: ";
    $logQuery = "performed query on page: ";
    $logDebugSQL = "";

    // Get date range
    $start_date = (array_key_exists('start_date', $_GET) && !empty($_GET['start_date']))
                ? $_GET['start_date'] : date('Y-m-01');
    $end_date = (array_key_exists('end_date', $_GET) && !empty($_GET['end_date']))
              ? $_GET['end_date'] : date('Y-m-t');
    
    $agent_filter = (array_key_exists('agent_id', $_GET) && intval($_GET['agent_id']) > 0)
                  ? intval($_GET['agent_id']) : 0;
    
    $payment_type_filter = (array_key_exists('payment_type', $_GET) && 
                           in_array($_GET['payment_type'], array('balance_topup', 'bundle_purchase')))
                         ? $_GET['payment_type'] : "";

    $title = "Agent Payment Report";
    $help = "View agent payment transactions and statistics";
    
    print_html_prologue($title, $langCode);
    print_title_and_help($title, $help);

    include('../common/includes/db_open.php');
?>

<style>
.stats-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
}
.stats-number {
    font-size: 2.5em;
    font-weight: bold;
    margin: 10px 0;
}
.chart-container {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}
.payment-topup {
    color: #4caf50;
    font-weight: bold;
}
.payment-bundle {
    color: #2196f3;
    font-weight: bold;
}
</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4>💰 Agent Payment Report</h4>
                </div>
                <div class="card-body">
                    <form method="GET" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="row">
                        <div class="col-md-3">
                            <label for="start_date">Start Date:</label>
                            <input type="date" name="start_date" id="start_date" 
                                   class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="end_date">End Date:</label>
                            <input type="date" name="end_date" id="end_date" 
                                   class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="agent_id">Agent:</label>
                            <select name="agent_id" id="agent_id" class="form-control">
                                <option value="">All Agents</option>
<?php
    $sql = sprintf("SELECT id, name FROM %s WHERE is_deleted = 0 ORDER BY name",
                   $configValues['CONFIG_DB_TBL_DALOAGENTS']);
    $res = $dbSocket->query($sql);
    while ($row = $res->fetchRow()) {
        $selected = ($agent_filter == intval($row[0])) ? 'selected' : '';
        echo sprintf('<option value="%d" %s>%s</option>', 
                    intval($row[0]), $selected, htmlspecialchars($row[1]));
    }
?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="payment_type">Type:</label>
                            <select name="payment_type" id="payment_type" class="form-control">
                                <option value="">All Types</option>
                                <option value="balance_topup" <?php echo ($payment_type_filter == 'balance_topup') ? 'selected' : ''; ?>>Balance Topup</option>
                                <option value="bundle_purchase" <?php echo ($payment_type_filter == 'bundle_purchase') ? 'selected' : ''; ?>>Bundle Purchase</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>&nbsp;</label><br>
                            <button type="submit" class="btn btn-primary">🔍 Generate</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php
    // Build query
    $where_clauses = array();
    $where_clauses[] = sprintf("ap.payment_date BETWEEN '%s 00:00:00' AND '%s 23:59:59'",
                              $dbSocket->escapeSimple($start_date),
                              $dbSocket->escapeSimple($end_date));
    
    if ($agent_filter > 0) {
        $where_clauses[] = sprintf("ap.agent_id = %d", $agent_filter);
    }
    
    if (!empty($payment_type_filter)) {
        $where_clauses[] = sprintf("ap.payment_type = '%s'", 
                                  $dbSocket->escapeSimple($payment_type_filter));
    }
    
    $where_sql = implode(' AND ', $where_clauses);
    
    // Summary statistics
    $sql = sprintf("SELECT 
                        COUNT(*) as total_transactions,
                        SUM(ap.amount) as total_amount,
                        COUNT(DISTINCT ap.username) as unique_users,
                        COUNT(CASE WHEN ap.payment_type = 'balance_topup' THEN 1 END) as topup_count,
                        SUM(CASE WHEN ap.payment_type = 'balance_topup' THEN ap.amount ELSE 0 END) as topup_amount,
                        COUNT(CASE WHEN ap.payment_type = 'bundle_purchase' THEN 1 END) as bundle_count,
                        SUM(CASE WHEN ap.payment_type = 'bundle_purchase' THEN ap.amount ELSE 0 END) as bundle_amount
                    FROM agent_payments ap
                    WHERE %s", $where_sql);
    
    $res = $dbSocket->query($sql);
    $logDebugSQL .= "$sql;\n";
    $summary = $res->fetchRow();
    
    $total_transactions = intval($summary[0]);
    $total_amount = floatval($summary[1]);
    $unique_users = intval($summary[2]);
    $topup_count = intval($summary[3]);
    $topup_amount = floatval($summary[4]);
    $bundle_count = intval($summary[5]);
    $bundle_amount = floatval($summary[6]);
?>

    <div class="row">
        <div class="col-md-3">
            <div class="stats-card">
                <div>Total Transactions</div>
                <div class="stats-number"><?php echo number_format($total_transactions); ?></div>
                <small><?php echo date('M d', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?></small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div>Total Amount</div>
                <div class="stats-number">$<?php echo number_format($total_amount, 2); ?></div>
                <small>All payment types</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <div>Balance Topups</div>
                <div class="stats-number"><?php echo number_format($topup_count); ?></div>
                <small>$<?php echo number_format($topup_amount, 2); ?></small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <div>Bundle Purchases</div>
                <div class="stats-number"><?php echo number_format($bundle_count); ?></div>
                <small>$<?php echo number_format($bundle_amount, 2); ?></small>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="chart-container">
                <h5>👥 Top Agents by Transaction Count</h5>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Agent Name</th>
                            <th>Transactions</th>
                            <th>Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
<?php
    $sql = sprintf("SELECT 
                        a.name,
                        COUNT(*) as transaction_count,
                        SUM(ap.amount) as total_amount
                    FROM agent_payments ap
                    LEFT JOIN %s a ON ap.agent_id = a.id
                    WHERE %s
                    GROUP BY a.name
                    ORDER BY transaction_count DESC
                    LIMIT 10",
                   $configValues['CONFIG_DB_TBL_DALOAGENTS'],
                   $where_sql);
    
    $res = $dbSocket->query($sql);
    $logDebugSQL .= "$sql;\n";
    
    while ($row = $res->fetchRow()) {
        $agent_name = htmlspecialchars($row[0], ENT_QUOTES, 'UTF-8');
        $count = intval($row[1]);
        $amount = floatval($row[2]);
        
        echo "<tr>";
        echo "<td><strong>$agent_name</strong></td>";
        echo "<td>" . number_format($count) . "</td>";
        echo "<td>$" . number_format($amount, 2) . "</td>";
        echo "</tr>";
    }
?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-md-6">
            <div class="chart-container">
                <h5>📅 Daily Transaction Volume</h5>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Transactions</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
<?php
    $sql = sprintf("SELECT 
                        DATE(ap.payment_date) as payment_day,
                        COUNT(*) as daily_count,
                        SUM(ap.amount) as daily_amount
                    FROM agent_payments ap
                    WHERE %s
                    GROUP BY DATE(ap.payment_date)
                    ORDER BY payment_day DESC
                    LIMIT 15",
                   $where_sql);
    
    $res = $dbSocket->query($sql);
    $logDebugSQL .= "$sql;\n";
    
    while ($row = $res->fetchRow()) {
        $day = htmlspecialchars($row[0], ENT_QUOTES, 'UTF-8');
        $count = intval($row[1]);
        $amount = floatval($row[2]);
        
        echo "<tr>";
        echo "<td>" . date('M d, Y', strtotime($day)) . "</td>";
        echo "<td>" . number_format($count) . "</td>";
        echo "<td>$" . number_format($amount, 2) . "</td>";
        echo "</tr>";
    }
?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="chart-container">
                <h5>📋 Recent Transactions</h5>
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Agent</th>
                            <th>Username</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Balance Change</th>
                            <th>Method</th>
                        </tr>
                    </thead>
                    <tbody>
<?php
    $sql = sprintf("SELECT 
                        ap.payment_date,
                        a.name as agent_name,
                        ap.username,
                        ap.payment_type,
                        ap.amount,
                        ap.balance_before,
                        ap.balance_after,
                        ap.payment_method
                    FROM agent_payments ap
                    LEFT JOIN %s a ON ap.agent_id = a.id
                    WHERE %s
                    ORDER BY ap.payment_date DESC
                    LIMIT 30",
                   $configValues['CONFIG_DB_TBL_DALOAGENTS'],
                   $where_sql);
    
    $res = $dbSocket->query($sql);
    $logDebugSQL .= "$sql;\n";
    
    while ($row = $res->fetchRow()) {
        $payment_date = htmlspecialchars($row[0], ENT_QUOTES, 'UTF-8');
        $agent_name = htmlspecialchars($row[1], ENT_QUOTES, 'UTF-8');
        $username = htmlspecialchars($row[2], ENT_QUOTES, 'UTF-8');
        $payment_type = htmlspecialchars($row[3], ENT_QUOTES, 'UTF-8');
        $amount = floatval($row[4]);
        $balance_before = floatval($row[5]);
        $balance_after = floatval($row[6]);
        $payment_method = htmlspecialchars($row[7], ENT_QUOTES, 'UTF-8');
        
        $type_class = ($payment_type == 'balance_topup') ? 'payment-topup' : 'payment-bundle';
        $type_display = ($payment_type == 'balance_topup') ? '💰 Topup' : '📦 Bundle';
        
        echo "<tr>";
        echo "<td>" . date('M d, H:i', strtotime($payment_date)) . "</td>";
        echo "<td>$agent_name</td>";
        echo "<td><a href=\"mng-edit.php?username=" . urlencode($username) . "\">$username</a></td>";
        echo "<td><span class=\"$type_class\">$type_display</span></td>";
        echo "<td>$" . number_format($amount, 2) . "</td>";
        echo "<td>$" . number_format($balance_before, 2) . " → $" . number_format($balance_after, 2) . "</td>";
        echo "<td>$payment_method</td>";
        echo "</tr>";
    }
?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php
    include('../common/includes/db_close.php');
    include('include/config/logging.php');
    print_footer_and_html_epilogue();
?>
