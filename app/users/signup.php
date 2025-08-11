<?php
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// DB config
$db_host = "172.30.16.200";
$db_user = "bassel";
$db_pass = "bassel_password";
$db_name = "radius";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($conn->real_escape_string($_POST['username']));
    $password = trim($_POST['password']);
    $confirm  = trim($_POST['confirm']);

    if ($password !== $confirm) {
        $message = "<p style='color:red;'>Passwords do not match.</p>";
    } elseif (strlen($username) < 3) {
        $message = "<p style='color:red;'>Username must be at least 3 characters.</p>";
    } else {
        // تحقق إذا كان المستخدم موجود مسبقاً
        $check_sql = "SELECT COUNT(*) AS cnt FROM radcheck WHERE username='$username'";
        $result = $conn->query($check_sql);
        $row = $result->fetch_assoc();

        if ($row['cnt'] > 0) {
            $message = "<p style='color:red;'>Username already exists. Please choose another one.</p>";
        } else {
            $conn->begin_transaction();
            try {
                // radcheck
                $sql1 = "INSERT INTO radcheck (username, attribute, op, value)
                         VALUES ('$username', 'Cleartext-Password', ':=', '$password')";
                if (!$conn->query($sql1)) throw new Exception($conn->error);

                // userinfo
                $sql2 = "INSERT INTO userinfo (username) VALUES ('$username')";
                if (!$conn->query($sql2)) throw new Exception($conn->error);

                // userbillinfo
                $sql3 = "INSERT INTO userbillinfo (username) VALUES ('$username')";
                if (!$conn->query($sql3)) throw new Exception($conn->error);

                // radusergroup
                $sql4 = "INSERT INTO radusergroup (username, groupname, priority)
                         VALUES ('$username', 'hotspot', 1)";
                if (!$conn->query($sql4)) throw new Exception($conn->error);

                $conn->commit();
                $message = "<p style='color:green; font-size:18px; font-weight:bold;'>
                                ✅ Account created successfully!
                            </p>
                            <p>Please click the button below to return to the login page.</p>
                            <button onclick=\"window.location.href='https://hotspot.samanet.sy:8003/index.php?zone=hotspot'\">
                                Return to Login
                            </button>";
            } catch (Exception $e) {
                $conn->rollback();
                $message = "<p style='color:red;'>Error creating account: " . $e->getMessage() . "</p>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Signup</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; text-align: center; }
        .signup-box { width: 350px; margin: 80px auto; background: #fff; padding: 20px; 
                      border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,.1); }
        input { width: 90%; padding: 10px; margin: 10px 0; }
        button { padding: 10px; width: 95%; background: #1383c6; color: white; 
                 border: none; border-radius: 5px; font-size: 16px; }
        button:hover { background: #0f6ca5; cursor: pointer; }
    </style>
</head>
<body>
    <div class="signup-box">
        <h2>Signup</h2>
        <?php echo $message; ?>
        <?php if ($message == "" || strpos($message, 'exists') !== false || strpos($message, 'not match') !== false): ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required><br>
            <input type="password" name="password" placeholder="Password" required><br>
            <input type="password" name="confirm" placeholder="Confirm Password" required><br>
            <button type="submit">Signup</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
