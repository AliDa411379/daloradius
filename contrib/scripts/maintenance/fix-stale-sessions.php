<?php
$logFile = '/var/log/fix-stale.log';
function logMsg($msg) {
    global $logFile;
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - $msg\n", FILE_APPEND);
}

logMsg("=== Script started ===");

// ??? ????? HOME ????? ????? SSH
putenv('HOME=/home/dalo');

logMsg("Running as user: " . trim(shell_exec('whoami')));
logMsg("Using HOME: " . getenv('HOME'));

include_once implode(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', '..', 'app', 'common', 'includes', 'config_read.php']);

$configValues['CONFIG_FIX_STALE_INTERVAL'] = intval($configValues['CONFIG_FIX_STALE_INTERVAL'] ?? 60);
if ($configValues['CONFIG_FIX_STALE_INTERVAL'] <= 0) {
    $configValues['CONFIG_FIX_STALE_INTERVAL'] = 60;
}

$configValues['CONFIG_FIX_STALE_GRACE'] = intval($configValues['CONFIG_FIX_STALE_GRACE'] ?? intdiv($configValues['CONFIG_FIX_STALE_INTERVAL'], 2));
if (
    $configValues['CONFIG_FIX_STALE_GRACE'] <= 0 ||
    $configValues['CONFIG_FIX_STALE_GRACE'] > $configValues['CONFIG_FIX_STALE_INTERVAL']
) {
    $configValues['CONFIG_FIX_STALE_GRACE'] = intdiv($configValues['CONFIG_FIX_STALE_INTERVAL'], 2);
}

$timeThreshold = $configValues['CONFIG_FIX_STALE_INTERVAL'] + $configValues['CONFIG_FIX_STALE_GRACE'];

include implode(DIRECTORY_SEPARATOR, [$configValues['COMMON_INCLUDES'], 'db_open.php']);

$sql = sprintf(
    "SELECT username, nasipaddress, acctsessionid, framedipaddress, servicetype
     FROM %s
     WHERE (UNIX_TIMESTAMP(NOW()) - (UNIX_TIMESTAMP(acctstarttime) + acctsessiontime)) > %d
       AND (acctstoptime = '0000-00-00 00:00:00' OR acctstoptime IS NULL)",
    $configValues['CONFIG_DB_TBL_RADACCT'],
    $timeThreshold
);

$res = $dbSocket->query($sql);
if (PEAR::isError($res)) {
    logMsg("SQL Error: " . $res->getMessage());
} else {
    $count = 0;
    while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $username    = $row['username'];
        $nasip       = $row['nasipaddress'];
        $sessionid   = $row['acctsessionid'];
        $framedip    = $row['framedipaddress'];
        $servicetype = trim($row['servicetype']);

        $count++;
        logMsg("Found stale session: user=$username, service=$servicetype, NAS=$nasip, IP=$framedip");

        if ($servicetype === "Login-User") {
//            // ??? ???? captive portal ??? pfSense
//            $cmd = sprintf('/usr/bin/ssh admin@172.30.255.66 "php /root/disconnect.php %s" 2>&1', escapeshellarg($framedip));
//            $output = shell_exec($cmd);
//            logMsg("pfSense disconnect output: " . trim($output));
	    $cmd = sprintf(
                'echo "Framed-IP-Address = %s" | /usr/bin/radclient -x %s:3799 disconnect sama@123 -r 1 -t 2 2>&1',
                $framedip,
                $nasip
            );
  	    $output = shell_exec($cmd);
            logMsg("hotspot disconnect output: " . trim($output));

            // delete IP from address list MikroTik ??? ??? ???? ???? hotspot
            $mikrotikNASIP = '172.30.255.99';
            $sshCmd = sprintf(
                'ssh daloradiusbot@%s "/ip firewall address-list remove [find address=%s]" 2>&1',
                 $mikrotikNASIP,
                 $framedip
            );
            $sshOutput = shell_exec($sshCmd);
            logMsg("MikroTik firewall address-list remove output: " . trim($sshOutput));
        }
        elseif ($servicetype === "Framed-User") {
            // ??? ???? PPPoE ??? MikroTik ???????? radclient
            $cmd = sprintf(
                'echo "Framed-IP-Address = %s" | /usr/bin/radclient -x %s:3799 disconnect sama@123 -r 1 -t 2 2>&1',
                $framedip,
                $nasip
            );
            $output = shell_exec($cmd);
            logMsg("MikroTik disconnect output: " . trim($output));
        } else {
            logMsg("Unknown service type: $servicetype - skipping");
        }
    }

    if ($count === 0) {
        logMsg("No stale sessions found.");
    }
}

$sql = sprintf(
    "UPDATE %s
     SET acctstoptime = NOW(), acctterminatecause = 'Stale-Session'
     WHERE (UNIX_TIMESTAMP(NOW()) - (UNIX_TIMESTAMP(acctstarttime) + acctsessiontime)) > %d
       AND (acctstoptime = '0000-00-00 00:00:00' OR acctstoptime IS NULL)",
    $configValues['CONFIG_DB_TBL_RADACCT'],
    $timeThreshold
);
$dbSocket->query($sql);

$sql = sprintf(
    "UPDATE %s
     SET acctstarttime = DATE_ADD(NOW(), INTERVAL (acctsessiontime + %d) SECOND)
     WHERE (acctstarttime = '0000-00-00 00:00:00' OR acctstarttime IS NULL)
       AND acctsessiontime > 0",
    $configValues['CONFIG_DB_TBL_RADACCT'],
    $timeThreshold
);
$dbSocket->query($sql);


// Force hotspot sessions to Stale-Session instead of Admin-Reset
$sql = sprintf(
    "UPDATE %s
     SET acctterminatecause = 'Stale-Session'
     WHERE servicetype = 'Login-User'
       AND acctterminatecause = 'Admin-Reset'
       AND (acctstoptime IS NOT NULL AND acctstoptime != '0000-00-00 00:00:00')",
    $configValues['CONFIG_DB_TBL_RADACCT']
);
$dbSocket->query($sql);

//

include implode(DIRECTORY_SEPARATOR, [$configValues['COMMON_INCLUDES'], 'db_close.php']);

logMsg("=== Script finished ===");
?>
