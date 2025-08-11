<?php
// إعدادات قاعدة البيانات
$host     = "172.30.16.200";
$user     = "bassel";              // غيّر إذا لزم الأمر
$password = "bassel_password";     // غيّر إذا لزم الأمر
$dbname   = "radius";

// الاتصال بقاعدة البيانات
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// قراءة البيانات من الرابط
$mac     = $_GET['mac']    ?? '';
$name    = $_GET['name']   ?? 'unknown';
$ip      = $_GET['ip']     ?? '';
$uptime  = $_GET['uptime'] ?? '';
$users   = isset($_GET['users']) ? intval($_GET['users']) : 0;
$cpu     = isset($_GET['cpu']) ? floatval($_GET['cpu']) : 0.0;
$time    = date("Y-m-d H:i:s");

// التحقق من الحقول الأساسية
if (empty($mac) || empty($ip)) {
    http_response_code(400);
    die("Missing required parameters: mac or ip.");
}

// تجهيز الاستعلام
$stmt = $conn->prepare("
    INSERT INTO node (mac, name, ip, uptime, users, cpu, time)
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        ip = VALUES(ip),
        uptime = VALUES(uptime),
        users = VALUES(users),
        cpu = VALUES(cpu),
        time = VALUES(time)
");

if (!$stmt) {
    http_response_code(500);
    die("Prepare failed: " . $conn->error);
}

// ربط القيم
$stmt->bind_param("ssssdds", $mac, $name, $ip, $uptime, $users, $cpu, $time);

// تنفيذ
if ($stmt->execute()) {
    echo "✅ Node data inserted/updated successfully.";
} else {
    http_response_code(500);
    echo "❌ Error: " . $stmt->error;
}

$stmt->close();
$conn->close();

echo "✅ Data received and processed.\n";

?>
