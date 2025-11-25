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
 * Description:    Bundle Purchase Report
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

    // Get date range from params or default to current month
    $start_date = (array_key_exists('start_date', $_GET) && !empty($_GET['start_date']))
                ? $_GET['start_date'] : date('Y-m-01');
    $end_date = (array_key_exists('end_date', $_GET) && !empty($_GET['end_date']))
              ? $_GET['end_date'] : date('Y-m-t');
    
    $status_filter = (array_key_exists('status', $_GET) && isset($_GET['status']) &&
                      in_array($_GET['status'], array('active', 'expired', 'used')))
                   ? $_GET['status'] : "";

    $title = "Bundle Purchase Report";
    $help = "View bundle purchase statistics and details";
    
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
</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4>📊 Bundle Purchase Report</h4>
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
                        <div class="col-md-3">
                            <label for="status">Status Filter:</label>
                            <select name="status" id="status" class="form-control">
                                <option value="">All Status</option>
                                <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="expired" <?php echo ($status_filter == 'expired') ? 'selected' : ''; ?>>Expired</option>
                                <option value="used" <?php echo ($status_filter == 'used') ? 'selected' : ''; ?>>Used</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>&nbsp;</label><br>
                            <button type="submit" class="btn btn-primary">🔍 Generate Report</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php
    // Build query
    $where_clauses = array("ub.purchase_date BETWEEN ? AND ?");
    $start_datetime = $start_date . ' 00:00:00';
    $end_datetime = $end_date . ' 23:59:59';
    
    if (!empty($status_filter)) {
        $where_clauses[] = "ub.status = '" . $dbSocket->escapeSimple($status_filter) . "'";
    }
    
    $where_sql = implode(' AND ', $where_clauses);
    
    // Get summary statistics
    $sql = sprintf("SELECT 
                        COUNT(*) as total_purchases,
                        SUM(bp.planCost) as total_revenue,
                        COUNT(DISTINCT ub.username) as unique_users,
                        COUNT(CASE WHEN ub.status = 'active' THEN 1 END) as active_bundles,
                        COUNT(CASE WHEN ub.status = 'expired' THEN 1 END) as expired_bundles
                    FROM user_bundles ub
                    INNER JOIN %s bp ON ub.plan_id = bp.id
                    WHERE %s",
                   $configValues['CONFIG_DB_TBL_DALOBILLINGPLANS'],
                   $where_sql);
    
    $res = $dbSocket->query($sql);
    $logDebugSQL .= "$sql;\n";
    $summary = $res->fetchRow();
    
    $total_purchases = intval($summary[0]);
    $total_revenue = floatval($summary[1]);
    $unique_users = intval($summary[2]);
    $active_bundles = intval($summary[3]);
    $expired_bundles = intval($summary[4]);
?>

    <div class="row">
        <div class="col-md-3">
            <div class="stats-card">
                <div>Total Purchases</div>
                <div class="stats-number"><?php echo number_format($total_purchases); ?></div>
                <small><?php echo date('M d', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?></small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div>Total Revenue</div>
                <div class="stats-number">$<?php echo number_format($total_revenue, 2); ?></div>
                <small>From bundle sales</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <div>Unique Users</div>
                <div class="stats-number"><?php echo number_format($unique_users); ?></div>
                <small>Different customers</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <div>Active Bundles</div>
                <div class="stats-number"><?php echo number_format($active_bundles); ?></div>
                <small><?php echo number_format($expired_bundles); ?> expired</small>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="chart-container">
                <h5>📦 Top Bundles by Sales</h5>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Bundle Name</th>
                            <th>Sales Count</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
<?php
    $sql = sprintf("SELECT 
                        bp.planName,
                        COUNT(*) as count,
                        SUM(bp.planCost) as revenue
                    FROM user_bundles ub
                    INNER JOIN %s bp ON ub.plan_id = bp.id
                    WHERE %s
                    GROUP BY bp.planName
                    ORDER BY count DESC
                    LIMIT 10",
                   $configValues['CONFIG_DB_TBL_DALOBILLINGPLANS'],
                   $where_sql);
    
    $res = $dbSocket->query($sql);
    $logDebugSQL .= "$sql;\n";
    
    while ($row = $res->fetchRow()) {
        $plan_name = htmlspecialchars($row[0], ENT_QUOTES, 'UTF-8');
        $count = intval($row[1]);
        $revenue = floatval($row[2]);
        
        echo "<tr>";
        echo "<td><strong>$plan_name</strong></td>";
        echo "<td>" . number_format($count) . "</td>";
        echo "<td>$" . number_format($revenue, 2) . "</td>";
        echo "</tr>";
    }
?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-md-6">
            <div class="chart-container">
                <h5>📈 Daily Purchases</h5>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Purchases</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
<?php
    $sql = sprintf("SELECT 
                        DATE(ub.purchase_date) as purchase_day,
                        COUNT(*) as daily_count,
                        SUM(bp.planCost) as daily_revenue
                    FROM user_bundles ub
                    INNER JOIN %s bp ON ub.plan_id = bp.id
                    WHERE %s
                    GROUP BY DATE(ub.purchase_date)
                    ORDER BY purchase_day DESC
                    LIMIT 15",
                   $configValues['CONFIG_DB_TBL_DALOBILLINGPLANS'],
                   $where_sql);
    
    $res = $dbSocket->query($sql);
    $logDebugSQL .= "$sql;\n";
    
    while ($row = $res->fetchRow()) {
        $day = htmlspecialchars($row[0], ENT_QUOTES, 'UTF-8');
        $count = intval($row[1]);
        $revenue = floatval($row[2]);
        
        echo "<tr>";
        echo "<td>" . date('M d, Y', strtotime($day)) . "</td>";
        echo "<td>" . number_format($count) . "</td>";
        echo "<td>$" . number_format($revenue, 2) . "</td>";
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
                <h5>📋 Recent Bundle Purchases</h5>
                <a href="bundle-list.php" class="btn btn-sm btn-primary float-right">View All</a>
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Username</th>
                            <th>Bundle</th>
                            <th>Cost</th>
                            <th>Status</th>
                            <th>Expires</th>
                        </tr>
                    </thead>
                    <tbody>
<?php
    $sql = sprintf("SELECT 
                        ub.purchase_date,
                        ub.username,
                        bp.planName,
                        bp.planCost,
                        ub.status,
                        ub.expiry_date
                    FROM user_bundles ub
                    INNER JOIN %s bp ON ub.plan_id = bp.id
                    WHERE %s
                    ORDER BY ub.purchase_date DESC
                    LIMIT 20",
                   $configValues['CONFIG_DB_TBL_DALOBILLINGPLANS'],
                   $where_sql);
    
    $res = $dbSocket->query($sql);
    $logDebugSQL .= "$sql;\n";
    
    while ($row = $res->fetchRow()) {
        $purchase_date = htmlspecialchars($row[0], ENT_QUOTES, 'UTF-8');
        $username = htmlspecialchars($row[1], ENT_QUOTES, 'UTF-8');
        $bundle_name = htmlspecialchars($row[2], ENT_QUOTES, 'UTF-8');
        $cost = floatval($row[3]);
        $status = htmlspecialchars($row[4], ENT_QUOTES, 'UTF-8');
        $expiry_date = htmlspecialchars($row[5], ENT_QUOTES, 'UTF-8');
        
        $status_class = 'badge-success';
        if ($status == 'expired') $status_class = 'badge-danger';
        if ($status == 'used') $status_class = 'badge-secondary';
        
        echo "<tr>";
        echo "<td>" . date('M d, H:i', strtotime($purchase_date)) . "</td>";
        echo "<td><a href=\"mng-edit.php?username=" . urlencode($username) . "\">$username</a></td>";
        echo "<td>$bundle_name</td>";
        echo "<td>$" . number_format($cost, 2) . "</td>";
        echo "<td><span class=\"badge $status_class\">" . strtoupper($status) . "</span></td>";
        echo "<td>" . ($expiry_date ? date('M d, H:i', strtotime($expiry_date)) : '-') . "</td>";
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
