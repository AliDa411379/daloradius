<?php
/**
 * DaloRADIUS - CoA Disconnect Processor
 *
 * Lightweight script that ONLY sends CoA Disconnect-Request packets via radclient.
 * All billing, blocking, and reactivation logic is handled by MySQL events and triggers.
 * See: contrib/db/erp_integration/08_subscription_lifecycle_events.sql
 *
 * This script reads from the `pending_disconnects` table (populated by MySQL stored
 * procedures when users are blocked) and sends Disconnect-Request to the NAS for
 * each user's active sessions.
 *
 * Schedule: Run every 5 minutes
 * Crontab: every 5 min via /usr/bin/php /var/www/daloradius/contrib/scripts/check_bundle_expiry.php
 *
 * @package DaloRADIUS
 * @version 3.0
 */

// ================== PATHS ==================
$baseDir = realpath(__DIR__ . '/../..');

define('LOG_FILE', $baseDir . '/var/logs/coa_disconnect.log');
define('LOCK_FILE', $baseDir . '/var/scripts/coa_disconnect.lock');

// ================== LOAD CONFIG ==================
$configValues = [];
$confFile = $baseDir . '/app/common/includes/daloradius.conf.php';
if (!file_exists($confFile)) {
    fwrite(STDERR, "Config file not found: $confFile\n");
    exit(1);
}

$_SERVER['PHP_SELF'] = '/contrib/scripts/check_bundle_expiry.php';
include($confFile);

// ================== FUNCTIONS ==================

function log_message($msg, $level = 'INFO') {
    $log_dir = dirname(LOG_FILE);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    file_put_contents(LOG_FILE, date('[Y-m-d H:i:s]') . " [$level] $msg\n", FILE_APPEND);
    if (php_sapi_name() === 'cli') {
        echo date('[Y-m-d H:i:s]') . " [$level] $msg\n";
    }
}

/**
 * Send CoA Disconnect-Request to NAS for a user's active sessions.
 * Returns the number of successfully sent disconnect packets.
 */
function disconnect_user_sessions($db, $username) {
    $username_esc = $db->real_escape_string($username);
    $disconnected = 0;

    // Find active sessions for this user
    $sql = "SELECT ra.nasipaddress, ra.framedipaddress, ra.acctsessionid, n.secret
            FROM radacct ra
            LEFT JOIN nas n ON ra.nasipaddress = n.nasname
            WHERE ra.username = '$username_esc'
              AND (ra.acctstoptime IS NULL OR ra.acctstoptime = '0000-00-00 00:00:00')";

    $sessions = $db->query($sql);
    if (!$sessions || $sessions->num_rows === 0) {
        return 0;
    }

    while ($sess = $sessions->fetch_assoc()) {
        $nasip = $sess['nasipaddress'];
        $framedip = $sess['framedipaddress'];
        $secret = $sess['secret'] ?: 'secret';

        if (empty($nasip) || empty($framedip)) {
            continue;
        }

        // Send Disconnect-Request via radclient (1 retry, 2 second timeout)
        $cmd = sprintf(
            'echo "Framed-IP-Address = %s" | /usr/bin/radclient -x %s:3799 disconnect %s -r 1 -t 2 2>&1',
            escapeshellarg($framedip),
            escapeshellarg($nasip),
            escapeshellarg($secret)
        );

        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        if ($returnCode === 0) {
            log_message("  CoA sent: user=$username NAS=$nasip IP=$framedip");
            $disconnected++;
        } else {
            log_message("  CoA failed: user=$username NAS=$nasip IP=$framedip rc=$returnCode", 'WARNING');
        }
    }

    return $disconnected;
}

// ================== MAIN ==================

try {
    // Lock file (prevent concurrent runs)
    if (file_exists(LOCK_FILE)) {
        $pid = trim(file_get_contents(LOCK_FILE));
        if ($pid && file_exists("/proc/$pid")) {
            exit(0); // Already running, exit silently
        }
        unlink(LOCK_FILE);
    }
    file_put_contents(LOCK_FILE, getmypid());

    // Database connection
    $db = new mysqli(
        $configValues['CONFIG_DB_HOST'],
        $configValues['CONFIG_DB_USER'],
        $configValues['CONFIG_DB_PASS'],
        $configValues['CONFIG_DB_NAME']
    );
    if ($db->connect_error) {
        throw new Exception("DB Connection Failed: " . $db->connect_error);
    }
    $db->set_charset("utf8mb4");

    // Fetch unprocessed disconnect requests
    $result = $db->query(
        "SELECT id, username, reason FROM pending_disconnects WHERE processed = 0 ORDER BY created_at ASC LIMIT 100"
    );

    if (!$result || $result->num_rows === 0) {
        $db->close();
        exit(0); // Nothing to do
    }

    $total = $result->num_rows;
    $disconnected = 0;
    $processedIds = [];

    log_message("Processing $total pending disconnect(s)");

    while ($row = $result->fetch_assoc()) {
        $username = $row['username'];
        $reason = $row['reason'];

        log_message("Disconnect: $username - $reason");
        $dc = disconnect_user_sessions($db, $username);
        $disconnected += $dc;

        $processedIds[] = intval($row['id']);
    }

    // Mark all as processed
    if (!empty($processedIds)) {
        $idList = implode(',', $processedIds);
        $db->query("UPDATE pending_disconnects SET processed = 1, processed_at = NOW() WHERE id IN ($idList)");
    }

    log_message("Done: processed=$total disconnects_sent=$disconnected");

    $db->close();

} catch (Exception $e) {
    log_message("FATAL: " . $e->getMessage(), 'ERROR');
    exit(1);
} finally {
    if (file_exists(LOCK_FILE)) {
        unlink(LOCK_FILE);
    }
}

exit(0);
