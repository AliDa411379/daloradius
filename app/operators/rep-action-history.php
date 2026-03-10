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
 * Description:    System Action History / Audit Trail Report
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

    // Filters
    $action_type = (array_key_exists('action_type', $_GET) && !empty($_GET['action_type']))
                  ? trim($_GET['action_type']) : '';
    $target_type = (array_key_exists('target_type', $_GET) && !empty($_GET['target_type']))
                  ? trim($_GET['target_type']) : '';
    $target_id = (array_key_exists('target_id', $_GET) && !empty($_GET['target_id']))
                ? trim($_GET['target_id']) : '';
    $performed_by = (array_key_exists('performed_by', $_GET) && !empty($_GET['performed_by']))
                   ? trim($_GET['performed_by']) : '';
    $date_from = (array_key_exists('date_from', $_GET) && !empty($_GET['date_from']))
                ? $_GET['date_from'] : date('Y-m-01');
    $date_to = (array_key_exists('date_to', $_GET) && !empty($_GET['date_to']))
              ? $_GET['date_to'] : date('Y-m-d');

    // Pagination
    $page = (array_key_exists('p', $_GET) && intval($_GET['p']) > 0) ? intval($_GET['p']) : 1;
    $per_page = 25;
    $offset = ($page - 1) * $per_page;

    $title = "Action History / Audit Trail";
    $help = "View all system actions including user operations, plan changes, logins, and more";

    print_html_prologue($title, $langCode);
    print_title_and_help($title, $help);

    // Connect via mysqli for ActionLogger
    $mysqli = new mysqli($configValues['CONFIG_DB_HOST'], $configValues['CONFIG_DB_USER'],
                         $configValues['CONFIG_DB_PASS'], $configValues['CONFIG_DB_NAME']);

    if ($mysqli->connect_error) {
        echo '<div class="alert alert-danger">Database connection failed</div>';
        include('include/config/logging.php');
        print_footer_and_html_epilogue();
        exit;
    }

    require_once(__DIR__ . '/../common/library/ActionLogger.php');
    $actionLogger = new ActionLogger($mysqli);

    $filters = [
        'action_type' => $action_type,
        'target_type' => $target_type,
        'target_id' => $target_id,
        'performed_by' => $performed_by,
        'date_from' => $date_from,
        'date_to' => $date_to,
    ];

    $result = $actionLogger->getActionHistory($filters, $offset, $per_page);
    $rows = $result['rows'];
    $total = $result['total'];
    $total_pages = ceil($total / $per_page);

    // Export CSV
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=action_history_' . date('Y-m-d') . '.csv');
        $csvResult = $actionLogger->getActionHistory($filters, 0, 10000);
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Date', 'Action', 'Target Type', 'Target', 'Description', 'Performed By', 'IP Address']);
        foreach ($csvResult['rows'] as $row) {
            fputcsv($out, [
                $row['created_at'], $row['action_type'], $row['target_type'],
                $row['target_id'], $row['description'], $row['performed_by'], $row['ip_address']
            ]);
        }
        fclose($out);
        $mysqli->close();
        exit;
    }

    // Action type labels and badges
    $actionBadges = [
        'user_create' => 'bg-success', 'user_edit' => 'bg-info', 'user_delete' => 'bg-danger',
        'plan_create' => 'bg-success', 'plan_edit' => 'bg-warning', 'plan_delete' => 'bg-danger',
        'bundle_purchase' => 'bg-primary', 'bundle_change' => 'bg-warning', 'bundle_cancel' => 'bg-danger',
        'balance_topup' => 'bg-success', 'balance_deduct' => 'bg-warning', 'refund' => 'bg-info',
        'operator_login' => 'bg-secondary', 'config_change' => 'bg-dark', 'agent_create' => 'bg-success',
    ];
?>

<style>
.filter-card { background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 20px; }
.action-badge { font-size: 0.75em; }
.detail-toggle { cursor: pointer; color: #0d6efd; }
.detail-toggle:hover { text-decoration: underline; }
.detail-row { display: none; }
.detail-row td { background: #f8f9fa; }
</style>

<div class="container-fluid">

    <!-- Filters -->
    <div class="filter-card">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label fw-bold">Action Type</label>
                <select name="action_type" class="form-select form-select-sm">
                    <option value="">All Actions</option>
                    <?php
                    $actionTypes = ['user_create', 'user_edit', 'user_delete', 'plan_create', 'plan_edit',
                                    'bundle_purchase', 'bundle_change', 'balance_topup', 'refund',
                                    'operator_login', 'config_change', 'agent_create'];
                    foreach ($actionTypes as $at) {
                        $sel = ($action_type === $at) ? 'selected' : '';
                        echo "<option value=\"$at\" $sel>" . ucwords(str_replace('_', ' ', $at)) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Target Type</label>
                <select name="target_type" class="form-select form-select-sm">
                    <option value="">All Targets</option>
                    <?php
                    $targetTypes = ['user', 'plan', 'bundle', 'operator', 'agent', 'config'];
                    foreach ($targetTypes as $tt) {
                        $sel = ($target_type === $tt) ? 'selected' : '';
                        echo "<option value=\"$tt\" $sel>" . ucfirst($tt) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Target (Username/Plan)</label>
                <input type="text" name="target_id" class="form-control form-control-sm"
                       value="<?php echo htmlspecialchars($target_id, ENT_QUOTES, 'UTF-8'); ?>"
                       placeholder="Search target...">
            </div>
            <div class="col-md-1">
                <label class="form-label fw-bold">By</label>
                <input type="text" name="performed_by" class="form-control form-control-sm"
                       value="<?php echo htmlspecialchars($performed_by, ENT_QUOTES, 'UTF-8'); ?>"
                       placeholder="Operator">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm"
                       value="<?php echo htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm"
                       value="<?php echo htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
            </div>
        </form>
    </div>

    <!-- Summary -->
    <div class="row mb-3">
        <div class="col-md-6">
            <strong>Total: <?php echo number_format($total); ?> actions found</strong>
            (Page <?php echo $page; ?> of <?php echo max(1, $total_pages); ?>)
        </div>
        <div class="col-md-6 text-end">
            <?php
            $exportParams = http_build_query(array_merge($filters, ['export' => 'csv']));
            ?>
            <a href="?<?php echo $exportParams; ?>" class="btn btn-outline-success btn-sm">
                Export CSV
            </a>
        </div>
    </div>

    <!-- Results Table -->
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover table-striped mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Date/Time</th>
                        <th>Action</th>
                        <th>Target</th>
                        <th>Description</th>
                        <th>By</th>
                        <th>IP</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No actions found for the selected filters</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $idx => $row):
                        $badge = isset($actionBadges[$row['action_type']]) ? $actionBadges[$row['action_type']] : 'bg-secondary';
                        $hasDetails = !empty($row['old_value']) || !empty($row['new_value']);
                    ?>
                    <tr>
                        <td class="text-nowrap"><?php echo htmlspecialchars($row['created_at']); ?></td>
                        <td>
                            <span class="badge <?php echo $badge; ?> action-badge">
                                <?php echo ucwords(str_replace('_', ' ', htmlspecialchars($row['action_type']))); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($row['target_type']): ?>
                                <small class="text-muted"><?php echo htmlspecialchars($row['target_type']); ?>:</small>
                            <?php endif; ?>
                            <strong><?php echo htmlspecialchars($row['target_id']); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                        <td><?php echo htmlspecialchars($row['performed_by']); ?></td>
                        <td><small><?php echo htmlspecialchars($row['ip_address']); ?></small></td>
                        <td>
                            <?php if ($hasDetails): ?>
                                <span class="detail-toggle" onclick="toggleDetail(<?php echo $idx; ?>)">View</span>
                            <?php else: ?>
                                <small class="text-muted">-</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($hasDetails): ?>
                    <tr class="detail-row" id="detail-<?php echo $idx; ?>">
                        <td colspan="7">
                            <div class="row">
                                <?php if (!empty($row['old_value'])): ?>
                                <div class="col-md-6">
                                    <strong>Old Values:</strong>
                                    <pre class="bg-white p-2 border rounded" style="max-height:200px;overflow:auto;font-size:0.8em"><?php
                                        $decoded = json_decode($row['old_value'], true);
                                        echo htmlspecialchars($decoded ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $row['old_value']);
                                    ?></pre>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($row['new_value'])): ?>
                                <div class="col-md-6">
                                    <strong>New Values:</strong>
                                    <pre class="bg-white p-2 border rounded" style="max-height:200px;overflow:auto;font-size:0.8em"><?php
                                        $decoded = json_decode($row['new_value'], true);
                                        echo htmlspecialchars($decoded ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $row['new_value']);
                                    ?></pre>
                                </div>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination justify-content-center">
            <?php
            $baseParams = $filters;
            for ($p = max(1, $page - 3); $p <= min($total_pages, $page + 3); $p++) {
                $baseParams['p'] = $p;
                $active = ($p === $page) ? 'active' : '';
                $href = '?' . http_build_query($baseParams);
                echo "<li class=\"page-item $active\"><a class=\"page-link\" href=\"$href\">$p</a></li>";
            }
            ?>
        </ul>
    </nav>
    <?php endif; ?>

</div>

<script>
function toggleDetail(idx) {
    var row = document.getElementById('detail-' + idx);
    if (row) {
        row.style.display = (row.style.display === 'table-row') ? 'none' : 'table-row';
    }
}
</script>

<?php
    $mysqli->close();
    include('include/config/logging.php');
    print_footer_and_html_epilogue();
