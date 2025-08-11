<?php
require_once("/var/www/daloradius/app/common/includes/config_read.php");

// setting CoA
define('COA_SERVER', '10.150.50.2');
define('COA_PORT', '3799');
define('COA_SECRET', 'sama@123');

$realm = 'samawifi.sy';

// database connection
$db = new mysqli(
    $configValues['CONFIG_DB_HOST'],
    $configValues['CONFIG_DB_USER'],
    $configValues['CONFIG_DB_PASS'],
    $configValues['CONFIG_DB_NAME']
);

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

$extension_days = $configValues['CONFIG_BILLING_EXTENSION_DAYS'];
$unpaid_users = $db->query("
    SELECT DISTINCT 
        i.user_id,
        u.username,
        u.planName,
        p.planCost as price
    FROM invoice i
    LEFT JOIN payment pmt ON i.id = pmt.invoice_id
    JOIN userbillinfo u ON i.user_id = u.id
    JOIN billing_plans p ON u.planName = p.planName
    WHERE i.status_id = 1  -- Unpaid
    AND i.date < DATE_SUB(NOW(), INTERVAL $extension_days DAY)  -- Dynamic interval
    AND pmt.id IS NULL
    AND NOT EXISTS (
        SELECT 1 FROM radusergroup 
        WHERE username = u.username 
        AND groupname = 'block_user'
    )
");

if ($db->error) {
    die("Query failed: " . $db->error);
}

foreach ($unpaid_users as $user) {
    $username = $user['username'];
    $planName = $user['planName'];
    $price = $user['price'];
    $user_id = $user['user_id'];

    echo "Suspending user: $username\n";

    // 1. add block_user
    $db->query("
        INSERT INTO radusergroup (username, groupname, priority)
        VALUES ('$username', 'block_user', 0)
    ");
    if ($db->error) {
        die("Add to block_user failed: " . $db->error);
    }

    // 2. update Overdue
    $db->query("
        UPDATE invoice 
        SET status_id = 3,
            updatedate = NOW(),
            updateby = 'system'
        WHERE user_id = $user_id AND status_id = 1
    ");
    if ($db->error) {
        die("Invoice update failed: " . $db->error);
    }

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
        die("Log insert failed: " . $db->error);
    }

    // 4. CoA Disconnect 
    $full_username = $username . '@' . $realm;
//    $coa_payload = "User-Name = \"$full_username\"\nService-Type = Outbound-User\n";
//    $cmd = "echo \"$coa_payload\" | radclient -x " . COA_SERVER . ":" . COA_PORT . " disconnect " . COA_SECRET;
    $cmd = "echo \"User-Name='$full_username'\" | /usr/bin/radclient -c '1' -n '3' -r '3' -t '3' -x '" . COA_SERVER . ":" . COA_PORT . "' 'disconnect' '" . COA_SECRET . "'";
    shell_exec($cmd);
}
?>
