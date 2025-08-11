<?php
require_once("/var/www/daloradius/app/common/includes/config_read.php");

//
$db = new mysqli(
    $configValues['CONFIG_DB_HOST'],
    $configValues['CONFIG_DB_USER'],
    $configValues['CONFIG_DB_PASS'],
    $configValues['CONFIG_DB_NAME']
);

if ($db->connect_error) {
    die("DB connection failed: " . $db->connect_error);
}

// 
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
        i.status_id = 5,  -- paid
        i.updatedate = NOW(),
        i.updateby = 'auto-payment-check'
    WHERE i.status_id IN (1, 2, 3, 4, 6)
";

$result = $db->query($update_query);

if ($db->error) {
    die("❌ Update failed: " . $db->error);
} else {
    echo "✅ Invoices updated to 'paid' successfully.\n";
}

$db->close();
?>
