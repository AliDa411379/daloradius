<?php
require_once("/var/www/daloradius/app/common/includes/config_read.php");

// CoA
define('COA_SERVER', '10.150.50.2');  // عنوان خادم الـ RADIUS
define('COA_PORT', '3799');
define('COA_SECRET', 'sama@123');
$realm = 'samawifi.sy';

// connect database
$db = new mysqli(
    $configValues['CONFIG_DB_HOST'],
    $configValues['CONFIG_DB_USER'],
    $configValues['CONFIG_DB_PASS'],
    $configValues['CONFIG_DB_NAME']
);

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// المستخدمون الموقوفون الذين دفعوا وفاتورتهم أصبحت مدفوعة
$paid_users = $db->query("
    SELECT DISTINCT u.id as user_id, u.username, u.planName
    FROM invoice i
    JOIN userbillinfo u ON i.user_id = u.id
    JOIN radusergroup rg ON rg.username = u.username
    WHERE rg.groupname = 'block_user'
    AND i.status_id = 5
");

if ($db->error) {
    die("Query failed: " . $db->error);
}

foreach ($paid_users as $user) {
    $username = $user['username'];
    $user_id = $user['user_id'];
    $planName = $user['planName'];
    $full_username = $username . '@' . $realm;

    echo "🔄 Reactivating user: $username\n";

    // 1. delete block_user
    $db->query("DELETE FROM radusergroup WHERE username = '$username' AND groupname = 'block_user'");
    if ($db->error) die("❌ Failed to remove block_user: " . $db->error);

    // 2. update nextbill
    $next_bill_date = date('Y-m-d', strtotime('+1 month')); // عدّل حسب نوع الخطة إن لزم
    $db->query("
        UPDATE userbillinfo 
        SET nextbill = '$next_bill_date', updatedate = NOW(), updateby = 'system'
        WHERE id = $user_id
    ");
    if ($db->error) die("❌ Failed to update nextbill: " . $db->error);

    // 3. add billing_history
    $plan = $db->query("SELECT id as planId, planCost as amount FROM billing_plans WHERE planName = '$planName' LIMIT 1")->fetch_assoc();
    $db->query("
        INSERT INTO billing_history (
            username, planId, billAmount, billAction, creationdate, creationby
        ) VALUES (
            '$username', {$plan['planId']}, '{$plan['amount']}', 'Account reactivated after payment', NOW(), 'system'
        )
    ");
    if ($db->error) die("❌ Log insert failed: " . $db->error);

        // 4. CoA Disconnect 
    $full_username = $username . '@' . $realm;
//    $coa_payload = "User-Name = \"$full_username\"\nService-Type = Outbound-User\n";
//    $cmd = "echo \"$coa_payload\" | radclient -x " . COA_SERVER . ":" . COA_PORT . " disconnect " . COA_SECRET;
    $cmd = "echo \"User-Name='$full_username'\" | /usr/bin/radclient -c '1' -n '3' -r '3' -t '3' -x '" . COA_SERVER . ":" . COA_PORT . "' 'disconnect' '" . COA_SECRET . "'";
    shell_exec($cmd);

    echo "✅ User '$username' reactivated and CoA sent.\n";
}

$db->close();
?>
