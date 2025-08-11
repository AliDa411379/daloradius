<?php
// إعداد الاتصال بقاعدة البيانات
$pdo = new PDO("mysql:host=172.30.16.200;dbname=radius", "bassel", "bassel_password");

// أمر التحديث
$sql = "
    UPDATE node
    SET 
        cpu = ROUND(RAND() * 100, 2),
        users = LPAD(FLOOR(RAND() * 20), 3, '0'),
        time = NOW()
";

// تنفيذ التحديث
$pdo->exec($sql);
?>
