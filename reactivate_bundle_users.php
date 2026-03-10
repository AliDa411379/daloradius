<?php
/**
 * One-time script to reactivate bundle users who paid before auto_reactivate fix
 * Run once then DELETE this file.
 *
 * Usage: php reactivate_bundle_users.php
 *   OR visit in browser: http://yourserver/daloradius/reactivate_bundle_users.php
 */

// Load config
require_once(__DIR__ . '/app/common/includes/config_read.php');
require_once(__DIR__ . '/app/common/library/balance_functions.php');

// Users that need reactivation (phone numbers = usernames)
$users_to_reactivate = [
    '963934369558',
    '963985328723',
    '963938336629',
    '963932841278',
    '963947092890',
    '963980921815',
    '963938136681',
    '963930089223',
];

// Connect via mysqli (required by handle_user_reactivation)
$mysqli = new mysqli(
    $configValues['CONFIG_DB_HOST'],
    $configValues['CONFIG_DB_USER'],
    $configValues['CONFIG_DB_PASS'],
    $configValues['CONFIG_DB_NAME'],
    $configValues['CONFIG_DB_PORT']
);

if ($mysqli->connect_error) {
    die("DB connection failed: " . $mysqli->connect_error . "\n");
}
$mysqli->set_charset('utf8mb4');

$output = php_sapi_name() === 'cli' ? 'cli' : 'html';

if ($output === 'html') {
    echo "<html><body><h2>Bundle User Reactivation</h2><pre>\n";
}

foreach ($users_to_reactivate as $username) {
    echo "=== Processing: $username ===\n";

    // Check user exists and get current state
    $res = $mysqli->query(sprintf(
        "SELECT ub.id, ub.username, ub.money_balance, ub.bundle_status, ub.subscription_type_id,
                bp.planName, bp.planCost
         FROM userbillinfo ub
         LEFT JOIN billing_plans bp ON ub.planName = bp.planName
         WHERE ub.username = '%s'",
        $mysqli->real_escape_string($username)
    ));

    if (!$res || $res->num_rows === 0) {
        echo "  ERROR: User '$username' not found in userbillinfo\n\n";
        continue;
    }

    $user = $res->fetch_assoc();
    echo "  Balance: " . $user['money_balance'] . "\n";
    echo "  Plan: " . $user['planName'] . " (cost: " . $user['planCost'] . ")\n";
    echo "  Bundle Status: " . $user['bundle_status'] . "\n";
    echo "  Subscription Type: " . $user['subscription_type_id'] . "\n";

    // Check if already active
    $active_check = $mysqli->query(sprintf(
        "SELECT COUNT(*) as cnt FROM user_bundles WHERE username = '%s' AND status = 'active' AND expiry_date > NOW()",
        $mysqli->real_escape_string($username)
    ));
    $active = $active_check->fetch_assoc();
    if ($active['cnt'] > 0) {
        echo "  SKIPPED: Already has active bundle\n\n";
        continue;
    }

    // First ensure auto_reactivate is set
    $mysqli->query(sprintf(
        "UPDATE userbillinfo SET auto_reactivate = 1 WHERE username = '%s'",
        $mysqli->real_escape_string($username)
    ));

    // Call the reactivation function
    $result = handle_user_reactivation($mysqli, $username, null);

    if ($result && isset($result['success']) && $result['success']) {
        echo "  SUCCESS: Reactivated\n";
        if (isset($result['log'])) {
            foreach ($result['log'] as $logEntry) {
                echo "    - $logEntry\n";
            }
        }
        if (isset($result['skipped']) && $result['skipped']) {
            echo "    (was already in good state)\n";
        }
    } else {
        echo "  FAILED: ";
        if ($result && isset($result['log'])) {
            foreach ($result['log'] as $logEntry) {
                echo "    - $logEntry\n";
            }
        } else {
            echo "    Unknown error\n";
        }
    }

    // Show final state
    $final = $mysqli->query(sprintf(
        "SELECT money_balance, bundle_status FROM userbillinfo WHERE username = '%s'",
        $mysqli->real_escape_string($username)
    ));
    if ($final && $final->num_rows > 0) {
        $f = $final->fetch_assoc();
        echo "  Final Balance: " . $f['money_balance'] . "\n";
        echo "  Final Bundle Status: " . $f['bundle_status'] . "\n";
    }
    echo "\n";
}

$mysqli->close();

if ($output === 'html') {
    echo "</pre><p><strong>Done! Delete this file now.</strong></p></body></html>\n";
} else {
    echo "Done! Delete this file (reactivate_bundle_users.php) now.\n";
}
