<?php
require_once("/var/www/daloradius/app/common/includes/config_read.php");

// ??? ?????
$logFile = '/var/www/daloradius/var/logs/suspend.log';
function logMsg($msg) {
    global $logFile;
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - $msg\n", FILE_APPEND);
}

logMsg("=== Starting Suspend Process (Non-blocking Parallel CoA) ===");

// ????? ????? ????????
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

//$extension_days = $configValues['CONFIG_BILLING_EXTENSION_DAYS'];
$extension_days =0;
// ??????? ?????????? ?????????
$sql_unpaid = "
    SELECT DISTINCT 
        i.user_id,
        u.username,
        u.planName,
        p.planCost as price
    FROM invoice i
    LEFT JOIN payment pmt ON i.id = pmt.invoice_id
    JOIN userbillinfo u ON i.user_id = u.id
    JOIN billing_plans p ON u.planName = p.planName
    WHERE i.status_id = 1
      AND i.date < DATE_SUB(NOW(), INTERVAL $extension_days DAY)
      AND pmt.id IS NULL
      AND (u.timebank_balance <= 0 OR u.traffic_balance <= 0)
      AND NOT EXISTS (
          SELECT 1 FROM radusergroup 
          WHERE username = u.username 
          AND groupname = 'block_user'
      )
";

$unpaid_users = $db->query($sql_unpaid);

if ($db->error) {
    logMsg("Query failed: " . $db->error);
    die("Query failed: " . $db->error);
}

if ($unpaid_users->num_rows === 0) {
    logMsg("No unpaid users found.");
}

// ??? ?? ???? CoA ?? ?????? ???????? ?????????
$coAProcesses = [];

// ???? ?????? ????? CoA ???????? proc_open
function prepareCoA($nasip, $framedip, $secret) {
    $cmd = sprintf('echo "Framed-IP-Address = %s" | /usr/bin/radclient -x %s:3799 disconnect %s -r 1 -t 2', 
                   $framedip, $nasip, $secret);

    $descriptorspec = [
        0 => ["pipe", "r"],  // stdin
        1 => ["pipe", "w"],  // stdout
        2 => ["pipe", "w"]   // stderr
    ];

    $process = proc_open($cmd, $descriptorspec, $pipes);

    if (is_resource($process)) {
        fclose($pipes[0]); // ?? ???? stdin
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        return [
            'process' => $process,
            'stdout' => $pipes[1],
            'stderr' => $pipes[2],
            'nasip' => $nasip,
            'framedip' => $framedip
        ];
    } else {
        return false;
    }
}

// ??? ??????
foreach ($unpaid_users as $user) {
    $username = $user['username'];
    $planName = $user['planName'];
    $price    = $user['price'];
    $user_id  = $user['user_id'];

    logMsg("Processing suspend for user: $username");

    // 1. add block_user
    $db->query("
        INSERT INTO radusergroup (username, groupname, priority)
        VALUES ('$username', 'block_user', 0)
    ");
    if ($db->error) {
        logMsg("Add to block_user failed: " . $db->error);
        continue;
    }
    logMsg("User $username added to block_user");

    // 2. update Overdue invoices
    $db->query("
        UPDATE invoice 
        SET status_id = 3,
            updatedate = NOW(),
            updateby = 'system'
        WHERE user_id = $user_id AND status_id = 1
    ");
    if ($db->error) {
        logMsg("Invoice update failed for $username: " . $db->error);
        continue;
    }
    logMsg("Invoices updated for $username");

    // 3. add billing_history
    $db->query("
        INSERT INTO billing_history (
            username,
            planId,
            billAmount,
            billAction,
            creationdate,
            creationby
        ) VALUES (
            '$username',
            (SELECT id FROM billing_plans WHERE planName = '$planName' LIMIT 1),
            '$price',
            'Account suspended for non-payment',
            NOW(),
            'system'
        )
    ");
    if ($db->error) {
        logMsg("Billing history insert failed for $username: " . $db->error);
        continue;
    }
    logMsg("Billing history recorded for $username");

    // 4. ??? ??????? ?????? ????????
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

    if ($sessions->num_rows === 0) {
        logMsg("No active sessions found for $username");
        continue;
    }

    // 5. ????? ???? ?????? CoA ???????? ?????????
    foreach ($sessions as $sess) {
        $nasip    = $sess['nasipaddress'];
        $framedip = $sess['framedipaddress'];
        $sessid   = $sess['acctsessionid'];

        logMsg("Preparing CoA for user=$username, NAS=$nasip, IP=$framedip, SessionID=$sessid");

        $proc = prepareCoA($nasip, $framedip, 'sama@123');
        if ($proc) {
            $proc['username'] = $username;
            $proc['sessionid'] = $sessid;
            $coAProcesses[] = $proc;
        } else {
            logMsg("Failed to start CoA process for $username, IP=$framedip");
        }
    }
}

// 6. ?????? ?? ???????? ???? blocking
while (!empty($coAProcesses)) {
    $read = $write = $except = [];
    foreach ($coAProcesses as $i => $proc) {
        if ($proc['stdout']) $read[] = $proc['stdout'];
        if ($proc['stderr']) $read[] = $proc['stderr'];
    }

    if (empty($read)) break;

    $num_changed_streams = stream_select($read, $write, $except, 0, 200000); // 0.2 ?????
    if ($num_changed_streams === false) break;

    foreach ($coAProcesses as $i => $proc) {
        $stdout = stream_get_contents($proc['stdout']);
        $stderr = stream_get_contents($proc['stderr']);

        if ($stdout !== false && strlen($stdout) > 0) {
            logMsg("CoA stdout for user={$proc['username']}, NAS={$proc['nasip']}, IP={$proc['framedip']}: " . trim($stdout));
        }
        if ($stderr !== false && strlen($stderr) > 0) {
            logMsg("CoA stderr for user={$proc['username']}, NAS={$proc['nasip']}, IP={$proc['framedip']}: " . trim($stderr));
        }

        // ???? ?? ?????? ???????
        $status = proc_get_status($proc['process']);
        if (!$status['running']) {
            proc_close($proc['process']);
            unset($coAProcesses[$i]);
        }
    }
    // ????? ????? ????????
    $coAProcesses = array_values($coAProcesses);
}

logMsg("=== Suspend Process Completed (Non-blocking Parallel CoA) ===");
?>
