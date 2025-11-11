<?php
require_once("/var/www/daloradius/app/common/includes/config_read.php");

$logFile = '/var/www/daloradius/var/logs/reactivate.log';
function logMsg($msg) {
    global $logFile;
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - $msg\n", FILE_APPEND);
}

logMsg("=== Starting Reactivate Process (Non-blocking Parallel Disconnect CoA) ===");

// CoA settings
define('COA_PORT', '3799');
define('COA_SECRET', 'sama@123');

// DB connection
$db = new mysqli(
    $configValues['CONFIG_DB_HOST'],
    $configValues['CONFIG_DB_USER'],
    $configValues['CONFIG_DB_PASS'],
    $configValues['CONFIG_DB_NAME']
);
if ($db->connect_error) {
    logMsg("DB Connection failed: " . $db->connect_error);
    die("Connection failed: " . $db->connect_error);
}

// 1?? ????? ???????? ???????? ???????
$update_query = "
    UPDATE invoice i
    JOIN (
        SELECT 
            ii.invoice_id,
            COALESCE(SUM(ii.amount + ii.tax_amount), 0) AS total_billed,
            COALESCE(SUM(p.amount), 0) AS total_paid
        FROM invoice_items ii
        LEFT JOIN payment p ON p.invoice_id = ii.invoice_id
        GROUP BY ii.invoice_id
        HAVING total_paid >= total_billed AND total_billed > 0
    ) AS payment_data ON i.id = payment_data.invoice_id
    SET 
        i.status_id = 5,
        i.updatedate = NOW(),
        i.updateby = 'auto-payment-check'
    WHERE i.status_id IN (1,2,3,4,6)
";
$db->query($update_query);
if ($db->error) die("Update invoices failed: " . $db->error);
logMsg("? Updated invoices to 'paid'.");

// 2?? ??? ?????????? ????? ????? ?????? ???????
$paid_users = $db->query("
    SELECT DISTINCT u.id as user_id, u.username, u.planName
    FROM invoice i
    JOIN userbillinfo u ON i.user_id = u.id
    JOIN radusergroup rg ON rg.username = u.username
    WHERE rg.groupname = 'block_user'
    AND i.status_id = 5
    AND (u.timebank_balance > 0 OR u.traffic_balance > 0)
");
if ($db->error) die("Query failed: " . $db->error);

// 3?? ????? CoA ??? ???? ?????? ??? ?? NAS
$coas = []; // Array ?????? ?? ????? CoA ? streams

foreach ($paid_users as $user) {
    $username = $user['username'];
    $planName = $user['planName'];
    $user_id  = $user['user_id'];

    logMsg("Processing reactivate for user: $username");

    // ????? ?????
    $db->query("DELETE FROM radusergroup WHERE username = '$username' AND groupname = 'block_user'");
    if ($db->error) {
        logMsg("Failed to remove block_user for $username: " . $db->error);
        continue;
    }
    logMsg("User $username removed from block_user");

    // ????? ????? ?? billing_history
    $plan = $db->query("SELECT id as planId, planCost as amount FROM billing_plans WHERE planName = '$planName' LIMIT 1")->fetch_assoc();
    $db->query("
        INSERT INTO billing_history (
            username, planId, billAmount, billAction, creationdate, creationby
        ) VALUES (
            '$username', {$plan['planId']}, '{$plan['amount']}', 'Account reactivated after payment', NOW(), 'system'
        )
    ");
    if ($db->error) {
        logMsg("Billing history insert failed for $username: " . $db->error);
        continue;
    }
    logMsg("Billing history recorded for $username");

    // ??? ??????? ?????? ?? radacct
    $sessions = $db->query("
        SELECT nasipaddress, framedipaddress, acctsessionid
        FROM radacct
        WHERE username = '$username'
          AND (acctstoptime IS NULL OR acctstoptime = '0000-00-00 00:00:00')
    ");
    if ($db->error) {
        logMsg("Radacct query failed for $username: " . $db->error);
        continue;
    }

    while ($sess = $sessions->fetch_assoc()) {
        $nasip    = $sess['nasipaddress'];
        $framedip = $sess['framedipaddress'];
        $sessid   = $sess['acctsessionid'];

        logMsg("Preparing Disconnect CoA for user=$username, NAS=$nasip, IP=$framedip, SessionID=$sessid");

        // ????? ??? CoA ???? blocking
        $descriptors = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"]
        ];
        $cmd = sprintf(
            '/usr/bin/radclient -x %s:3799 disconnect %s -r 1 -t 2',
            $nasip,
            COA_SECRET
        );
        $proc = proc_open($cmd, $descriptors, $pipes);
        if (is_resource($proc)) {
            fwrite($pipes[0], "Framed-IP-Address = $framedip\n");
            fclose($pipes[0]);
            $coas[] = ['proc' => $proc, 'stdout' => $pipes[1], 'stderr' => $pipes[2], 'user' => $username, 'nas' => $nasip, 'ip' => $framedip];
        }
    }
}

// 4?? ?????? ?? ??? CoA ???? blocking
$timeout = 2; // ????? ??? ??????
foreach ($coas as $key => $coa) {
    $stdout = $coa['stdout'];
    $stderr = $coa['stderr'];
    $user = $coa['user'];
    $nas = $coa['nas'];
    $ip  = $coa['ip'];

    $read = [$stdout, $stderr];
    $write = null;
    $except = null;
    stream_select($read, $write, $except, $timeout);

    foreach ($read as $r) {
        $output = stream_get_contents($r);
        logMsg("CoA stdout/stderr for user=$user, NAS=$nas, IP=$ip: " . trim($output));
        fclose($r);
    }
    proc_close($coa['proc']);
}

logMsg("=== Reactivate Process Completed (Non-blocking Parallel Disconnect CoA) ===");

$db->close();
?>
