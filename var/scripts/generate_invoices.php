<?php
require_once("/var/www/daloradius/app/common/includes/config_read.php");

// Database connection
$db = new mysqli(
    $configValues['CONFIG_DB_HOST'],   // e.g., 172.30.16.200
    $configValues['CONFIG_DB_USER'],   // bassel
    $configValues['CONFIG_DB_PASS'],   // bassel_password
    $configValues['CONFIG_DB_NAME']    // radius
);

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Get all active prepaid monthly subscribers
$users = $db->query("
    SELECT 
        u.id as user_id,
        u.username,
        u.planName,
        u.email,
        u.nextbill,
        p.id as planId,
        p.planCost
    FROM userbillinfo u
    JOIN billing_plans p ON u.planName = p.planName
    WHERE p.planRecurringBillingSchedule = 'Fixed'
    AND p.planType = 'Prepaid'
    AND (u.nextbill <= CURDATE() OR u.nextbill IS NULL)
");
if ($db->error) {
    die("Query failed: " . $db->error);
}

foreach ($users as $user) {
    // Calculate next billing date
    $next_bill_date = date('Y-m-d', strtotime('+1 month'));

    // Prevent duplicate invoices per month
    $existing_invoice = $db->query("
        SELECT id FROM invoice 
        WHERE user_id = {$user['user_id']}
        AND DATE_FORMAT(date, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
        LIMIT 1
    ")->fetch_assoc();

    if (!$existing_invoice) {
        // Create invoice
        $db->query("
            INSERT INTO invoice (
                user_id,
                date,
                status_id,
                type_id,
                notes,
                creationdate,
                creationby
            ) VALUES (
                {$user['user_id']},
                NOW(),
                1, -- Unpaid
                1, -- Subscription
                'Monthly subscription for {$user['planName']}',
                NOW(),
                'system'
            )
        ");
        if ($db->error) {
            die("Insert failed: " . $db->error);
        }
        $invoice_id = $db->insert_id;

        // Add invoice line item
        $db->query("
            INSERT INTO invoice_items (invoice_id, description, amount)
            VALUES (
                $invoice_id,
                'Monthly subscription - {$user['planName']}',
                {$user['planCost']}
            )
        ");
        if ($db->error) {
            die("Insert failed: " . $db->error);
        }

        // Update user's next billing date
        $db->query("
            UPDATE userbillinfo 
            SET 
                nextbill = '{$next_bill_date}',
                updatedate = NOW(),
                updateby = 'system'
            WHERE id = {$user['user_id']}
        ");
        if ($db->error) {
            die("Update failed: " . $db->error);
        }

        // Log billing action
        $db->query("
            INSERT INTO billing_history (
                username,
                planId,
                billAmount,
                billAction,
                creationdate,
                creationby
            ) VALUES (
                '{$user['username']}',
                {$user['planId']},
                '{$user['planCost']}',
                'Generated monthly invoice',
                NOW(),
                'system'
            )
        ");
        if ($db->error) {
            die("Log insert failed: " . $db->error);
        }

        // Send email notification if enabled
        if ($user['email'] && $user['emailinvoice'] == 'yes') {
            // Implement your email logic here
            // send_invoice_email($user['email'], $invoice_id);
        }
    }
}
?>
