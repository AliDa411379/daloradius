<?php
session_start();

$mode = $_GET['mode'] ?? 'user';
$qr_token_data = null;
$is_qr_signup = false;
$qr_auto_created = false;
$qr_creation_error = null;

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $decoded = json_decode(base64_decode($token), true);
    
    if ($decoded && isset($decoded['username']) && isset($decoded['password']) && isset($decoded['hash'])) {
        $expected_hash = md5($decoded['username'] . $decoded['password'] . 'samanet_secret_key');
        if ($decoded['hash'] === $expected_hash) {
            $time_diff = time() - ($decoded['timestamp'] ?? 0);
            if ($time_diff < 86400) {
                $qr_token_data = $decoded;
                $is_qr_signup = true;
                
                $db_host = "172.30.16.200";
                $db_user = "bassel";
                $db_pass = "bassel_password";
                $db_name = "radius";

                $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
                if (!$conn->connect_error) {
                    $qr_username = $decoded['username'];
                    $qr_password = $decoded['password'];
                    $qr_plan = $decoded['plan'] ?? 'Free WiFi';
                    
                    $check_sql = "SELECT COUNT(*) AS cnt FROM radcheck WHERE username = ?";
                    $stmt = $conn->prepare($check_sql);
                    $stmt->bind_param("s", $qr_username);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    
                    if ($row['cnt'] == 0) {
                        $conn->begin_transaction();
                        try {
                            $sql1 = "INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)";
                            $stmt = $conn->prepare($sql1);
                            $stmt->bind_param("ss", $qr_username, $qr_password);
                            if (!$stmt->execute()) throw new Exception("radcheck insert failed");

                            $sql2 = "INSERT INTO userinfo (username, firstname, lastname, email, department, company, workphone, homephone, mobilephone) 
                                     VALUES (?, 'Guest', 'User', '', 'Samanet ISP', 'Samanet ISP', '', '', '')";
                            $stmt = $conn->prepare($sql2);
                            $stmt->bind_param("s", $qr_username);
                            if (!$stmt->execute()) throw new Exception("userinfo insert failed");

                            $sql3 = "INSERT INTO userbillinfo (username, planName) VALUES (?, ?)";
                            $stmt = $conn->prepare($sql3);
                            $stmt->bind_param("ss", $qr_username, $qr_plan);
                            if (!$stmt->execute()) throw new Exception("userbillinfo insert failed");

                            $groupName = getGroupNameFromPlan($conn, $qr_plan);

                            $sql4 = "INSERT INTO radusergroup (username, groupname, priority) VALUES (?, ?, 1)";
                            $stmt = $conn->prepare($sql4);
                            $stmt->bind_param("ss", $qr_username, $groupName);
                            if (!$stmt->execute()) throw new Exception("radusergroup insert failed");

                            $check_group_sql = "SELECT COUNT(*) as cnt FROM radgroupcheck WHERE groupname = ?";
                            $stmt = $conn->prepare($check_group_sql);
                            $stmt->bind_param("s", $groupName);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $row = $result->fetch_assoc();

                            if ($row['cnt'] == 0) {
                                $group_check_sql = "INSERT INTO radgroupcheck (groupname, attribute, op, value) VALUES 
                                               (?, 'Auth-Type', ':=', 'Accept')";
                                $stmt = $conn->prepare($group_check_sql);
                                $stmt->bind_param("s", $groupName);
                                $stmt->execute();
                            }

                            $conn->commit();
                            $qr_auto_created = true;
                        } catch (Exception $e) {
                            $conn->rollback();
                            $qr_creation_error = $e->getMessage();
                        }
                    } else {
                        $qr_auto_created = true;
                    }
                    
                    $conn->close();
                }
            }
        }
    }
}

// Handle AJAX requests for Agent QR code generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_qr') {
    header('Content-Type: application/json; charset=utf-8');
    
    $planName = $_POST['plan'] ?? 'Free WiFi';
    
    $username = 'user_' . time() . rand(100, 999);
    $password = generateRandomPassword(8);
    
    $token = base64_encode(json_encode([
        'username' => $username,
        'password' => $password,
        'plan' => $planName,
        'timestamp' => time(),
        'hash' => md5($username . $password . 'samanet_secret_key')
    ]));
    
    $signup_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                  "://" . $_SERVER['HTTP_HOST'] . 
                  dirname($_SERVER['PHP_SELF']) . "/signup.php?token=" . urlencode($token);
    
    echo json_encode([
        'success' => true,
        'username' => $username,
        'password' => $password,
        'plan' => $planName,
        'qr_url' => $signup_url,
        'token' => $token
    ]);
    exit;
}

// Handle AJAX requests for direct user creation with SMS Hisham two
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Clean output buffer to prevent any unwanted output
    if (ob_get_level()) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    error_reporting(0);
    ini_set('display_errors', 0);

    // DB config
    $db_host = "172.30.16.200";
    $db_user = "bassel";
    $db_pass = "bassel_password";
    $db_name = "radius";

    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        die(json_encode(['success' => false, 'message' => 'Database connection failed']));
    }

    $action = $_POST['action'] ?? '';
    $mobile = $_POST['mobile'] ?? '';

    // Validate mobile number format
    if (!preg_match('/^\+963[0-9]{9}$/', $mobile)) {
        die(json_encode(['success' => false, 'message' => 'Invalid mobile number format']));
    }

    if ($action === 'create_qr_user') {
        $qr_username = $_POST['qr_username'] ?? '';
        $qr_password = $_POST['qr_password'] ?? '';
        $qr_plan = $_POST['qr_plan'] ?? 'Free WiFi';

        if (empty($qr_username) || empty($qr_password)) {
            die(json_encode(['success' => false, 'message' => 'Invalid QR data']));
        }

        error_log("Creating QR user: $qr_username with plan: $qr_plan");

        $check_sql = "SELECT COUNT(*) AS cnt FROM radcheck WHERE username = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("s", $qr_username);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row['cnt'] > 0) {
            die(json_encode([
                'success' => false,
                'message' => 'User already exists.'
            ]));
        }

        $conn->begin_transaction();
        try {
            error_log("Creating QR user account for: $qr_username (mobile: $mobile)");

            $sql1 = "INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)";
            $stmt = $conn->prepare($sql1);
            $stmt->bind_param("ss", $qr_username, $qr_password);
            if (!$stmt->execute()) throw new Exception("radcheck insert failed: " . $conn->error);
            error_log("radcheck entry created for user: $qr_username");

            $sql2 = "INSERT INTO userinfo (username, firstname, lastname, email, department, company, workphone, homephone, mobilephone) 
                     VALUES (?, 'Guest', 'User', '', 'Samanet ISP', 'Samanet ISP', '', '', ?)";
            $stmt = $conn->prepare($sql2);
            $stmt->bind_param("ss", $qr_username, $mobile);
            if (!$stmt->execute()) throw new Exception("userinfo insert failed: " . $conn->error);
            error_log("userinfo entry created for user: $qr_username");

            $sql3 = "INSERT INTO userbillinfo (username, planName) VALUES (?, ?)";
            $stmt = $conn->prepare($sql3);
            $stmt->bind_param("ss", $qr_username, $qr_plan);
            if (!$stmt->execute()) throw new Exception("userbillinfo insert failed: " . $conn->error);
            error_log("userbillinfo entry created for user: $qr_username with plan: $qr_plan");

            $groupName = getGroupNameFromPlan($conn, $qr_plan);
            error_log("Retrieved groupName '$groupName' for planName '$qr_plan' from daloRADIUS configuration");

            $sql4 = "INSERT INTO radusergroup (username, groupname, priority) VALUES (?, ?, 1)";
            $stmt = $conn->prepare($sql4);
            $stmt->bind_param("ss", $qr_username, $groupName);
            if (!$stmt->execute()) throw new Exception("radusergroup insert failed: " . $conn->error);
            error_log("radusergroup entry created for user: $qr_username with group: $groupName");

            $check_group_sql = "SELECT COUNT(*) as cnt FROM radgroupcheck WHERE groupname = ?";
            $stmt = $conn->prepare($check_group_sql);
            $stmt->bind_param("s", $groupName);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            if ($row['cnt'] == 0) {
                $group_check_sql = "INSERT INTO radgroupcheck (groupname, attribute, op, value) VALUES 
                               (?, 'Auth-Type', ':=', 'Accept')";
                $stmt = $conn->prepare($group_check_sql);
                $stmt->bind_param("s", $groupName);
                $stmt->execute();
            }

            $conn->commit();
            error_log("QR user account created successfully for: $qr_username");

            $sms_sent = sendPasswordSMS($mobile, $qr_username, $qr_password);

            echo json_encode([
                'success' => true,
                'message' => $sms_sent ? 'Account created successfully! Your credentials have been sent via SMS.' : 'Account created successfully!',
                'username' => $qr_username,
                'password' => $qr_password,
                'sms_sent' => $sms_sent
            ]);
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Error creating QR user account for $qr_username: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error creating account: ' . $e->getMessage()]);
            exit;
        }
    } else if ($action === 'create_user') {
        // Direct user creation with SMS password delivery

        // Username = phone number (without +)
        $username = str_replace('+', '', $mobile);
        // Generate random password
        $password = generateRandomPassword(8);

        error_log("Creating user: $username with password: $password");

        // Check if user already exists
        $check_sql = "SELECT COUNT(*) AS cnt FROM radcheck WHERE username = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row['cnt'] > 0) {
            die(json_encode([
                'success' => false,
                'message' => 'User already exists with this mobile number.'
            ]));
        }

        // Create new user account
        $conn->begin_transaction();
        try {
            error_log("Creating user account for: $username (mobile: $mobile)");

            // radcheck - store user credentials
            $sql1 = "INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)";
            $stmt = $conn->prepare($sql1);
            $stmt->bind_param("ss", $username, $password);
            if (!$stmt->execute()) throw new Exception("radcheck insert failed: " . $conn->error);
            error_log("radcheck entry created for user: $username");

            // userinfo - store user information
            $sql2 = "INSERT INTO userinfo (username, firstname, lastname, email, department, company, workphone, homephone, mobilephone) 
                     VALUES (?, 'Guest', 'User', '', 'Samanet ISP', 'Samanet ISP', '', '', ?)";
            $stmt = $conn->prepare($sql2);
            $stmt->bind_param("ss", $username, $mobile);
            if (!$stmt->execute()) throw new Exception("userinfo insert failed: " . $conn->error);
            error_log("userinfo entry created for user: $username");

            // userbillinfo - billing information
            $planName = 'Free WiFi';
            $sql3 = "INSERT INTO userbillinfo (username, planName) VALUES (?, ?)";
            $stmt = $conn->prepare($sql3);
            $stmt->bind_param("ss", $username, $planName);
            if (!$stmt->execute()) throw new Exception("userbillinfo insert failed: " . $conn->error);
            error_log("userbillinfo entry created for user: $username with plan: $planName");

            // Query daloRADIUS database to get the group name for this plan
            $groupName = getGroupNameFromPlan($conn, $planName);
            error_log("Retrieved groupName '$groupName' for planName '$planName' from daloRADIUS configuration");

            // radusergroup - assign to plan-based group
            $sql4 = "INSERT INTO radusergroup (username, groupname, priority) VALUES (?, ?, 1)";
            $stmt = $conn->prepare($sql4);
            $stmt->bind_param("ss", $username, $groupName);
            if (!$stmt->execute()) throw new Exception("radusergroup insert failed: " . $conn->error);
            error_log("radusergroup entry created for user: $username with group: $groupName");

            // Check if plan group exists in radgroupcheck
            $check_group_sql = "SELECT COUNT(*) as cnt FROM radgroupcheck WHERE groupname = ?";
            $stmt = $conn->prepare($check_group_sql);
            $stmt->bind_param("s", $groupName);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            if ($row['cnt'] == 0) {
                // Create basic plan group configuration if it doesn't exist
                $group_check_sql = "INSERT INTO radgroupcheck (groupname, attribute, op, value) VALUES 
                               (?, 'Auth-Type', ':=', 'Accept')";
                $stmt = $conn->prepare($group_check_sql);
                $stmt->bind_param("s", $groupName);
                if (!$stmt->execute()) {
                    // Log error but continue
                    error_log("Failed to create radgroupcheck entry for group: $groupName");
                } else {
                    error_log("Created radgroupcheck entry for group: $groupName");
                }
            }

            $conn->commit();
            error_log("User account created successfully for: $username");

            // Send username and password via SMS using Samanet API
            $sms_sent = sendPasswordSMS($mobile, $username, $password);

            echo json_encode([
                'success' => true,
                'message' => $sms_sent ? 'Account created successfully! Your credentials have been sent via SMS.' : 'Account created successfully! SMS sending failed, but your account is ready.',
                'username' => $username,
                'password' => $password,
                'sms_sent' => $sms_sent
            ]);
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Error creating user account for $username: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error creating account: ' . $e->getMessage()]);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }
}

function generateRandomPassword($length = 8)
{
    $characters = '0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

function getGroupNameFromPlan($conn, $planName) {
    $groupName = null;

    $sql = "SELECT profile_name FROM billing_plans_profiles WHERE plan_name = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $planName);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $groupName = $row['profile_name'];
        }
        $stmt->close();
    }

    // Fallback to default group if not found
    if (empty($groupName)) {
        $groupName = "speed_10m";
    }

    return $groupName;
}

function sendPasswordSMS($mobile, $username, $password)
{
    // SMS message content
    $sms_message = "Samanet ISP your password: " . $password;

    // Remove + from mobile number for the API
    $mobile_number = str_replace('+', '', $mobile);

    // Samanet SMS API endpoint
    $api_url = "http://mobily.samanet.sy/sendServerSMS.php";

    // Build the URL with parameters
    $url = $api_url . "?msg=" . urlencode($sms_message) . "&mob=" . urlencode($mobile_number);

    error_log("Sending SMS to $mobile_number: $sms_message");
    error_log("SMS API URL: $url");

    // Send SMS using cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        error_log("SMS API cURL error: " . $curl_error);
        return false;
    }

    if ($http_code !== 200) {
        error_log("SMS API HTTP error: " . $http_code);
        return false;
    }

    return true;
}

$db_host = "172.30.16.200";
$db_user = "bassel";
$db_pass = "bassel_password";
$db_name = "radius";

$plans = [];
if ($mode === 'agent') {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if (!$conn->connect_error) {
        $plans_query = "SELECT DISTINCT plan_name FROM billing_plans_profiles ORDER BY plan_name";
        $plans_result = $conn->query($plans_query);
        if ($plans_result && $plans_result->num_rows > 0) {
            while ($row = $plans_result->fetch_assoc()) {
                $plans[] = $row['plan_name'];
            }
        }
        $conn->close();
    }
    
    if (empty($plans)) {
        $plans = ['Free WiFi', 'Basic Plan', 'Premium Plan'];
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ($mode === 'agent' ? 'Agent QR Generator' : 'WiFi Registration'); ?> - Samanet ISP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php if ($mode === 'agent'): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <?php endif; ?>
    <style>
        /* Samanen ISP - Professional WiFi Signup Styles */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 50%, #6d28d9 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            line-height: 1.6;
        }

        .container {
            width: 100%;
            max-width: 450px;
            margin: 0 auto;
        }

        .signup-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .header {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/><circle cx="10" cy="60" r="0.5" fill="white" opacity="0.1"/><circle cx="90" cy="40" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            pointer-events: none;
        }

        .logo {
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }

        .subtitle {
            font-size: 16px;
            opacity: 0.9;
            font-weight: 400;
            position: relative;
            z-index: 1;
        }

        .form-container {
            padding: 40px 30px;
        }

        .step h2 {
            font-size: 24px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 8px;
            text-align: center;
        }

        .step-description {
            color: #6b7280;
            text-align: center;
            margin-bottom: 32px;
            font-size: 15px;
        }

        .input-group {
            margin-bottom: 24px;
        }

        .input-wrapper {
            display: flex;
            align-items: center;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }

        .input-wrapper:focus-within {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
            background: white;
        }

        .country-code {
            background: #e2e8f0;
            padding: 16px 12px;
            font-weight: 600;
            color: #374151;
            border-right: 2px solid #cbd5e1;
            font-size: 16px;
        }

        .mobile-input,
        .code-input {
            flex: 1;
            border: none;
            padding: 16px;
            font-size: 16px;
            background: transparent;
            outline: none;
            color: #1f2937;
            font-weight: 500;
        }

        .code-input {
            width: 100%;
            text-align: center;
            font-size: 24px;
            font-weight: 600;
            letter-spacing: 8px;
            padding: 20px;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .code-input:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
            background: white;
        }

        .input-help {
            display: block;
            margin-top: 8px;
            color: #6b7280;
            font-size: 13px;
        }

        .btn-primary {
            width: 100%;
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            border: none;
            padding: 16px 24px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(139, 92, 246, 0.3);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .spinner {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .resend-section {
            text-align: center;
            margin: 24px 0;
            padding: 20px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .resend-section p {
            margin: 8px 0;
            color: #6b7280;
            font-size: 14px;
        }

        .link-btn {
            background: none;
            border: none;
            color: #8b5cf6;
            cursor: pointer;
            font-weight: 600;
            text-decoration: underline;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .link-btn:hover {
            color: #7c3aed;
        }

        .success-message {
            text-align: center;
            padding: 20px 0;
        }

        .success-icon {
            margin-bottom: 24px;
            animation: bounceIn 0.6s ease-out;
        }

        @keyframes bounceIn {
            0% {
                transform: scale(0.3);
                opacity: 0;
            }

            50% {
                transform: scale(1.05);
            }

            70% {
                transform: scale(0.9);
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .success-message h2 {
            color: #10b981;
            margin-bottom: 16px;
        }

        .success-message p {
            color: #6b7280;
            margin-bottom: 32px;
            font-size: 15px;
        }

        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 16px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .error-icon {
            font-size: 20px;
        }

        .error-message p {
            color: #dc2626;
            font-weight: 500;
            margin: 0;
        }

        .footer {
            background: #f8fafc;
            padding: 24px 30px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }

        .footer p {
            margin: 8px 0;
            font-size: 13px;
            color: #6b7280;
        }

        .footer .link {
            color: #8b5cf6;
            text-decoration: none;
            font-weight: 500;
        }

        .footer .link:hover {
            text-decoration: underline;
        }

        .powered-by {
            font-weight: 600;
            color: #374151 !important;
        }

        .credentials-box {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }

        .credentials-box h3 {
            color: #1f2937;
            margin-bottom: 16px;
            font-size: 18px;
            text-align: center;
        }

        .credential-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .credential-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .credential-label {
            font-weight: 600;
            color: #374151;
        }

        .credential-value {
            font-family: 'Courier New', monospace;
            background: #e2e8f0;
            padding: 6px 12px;
            border-radius: 6px;
            color: #1f2937;
            font-weight: 600;
            font-size: 14px;
        }

        /* Responsive Design */
        @media (max-width: 480px) {
            .container {
                padding: 10px;
            }

            .signup-card {
                border-radius: 16px;
            }

            .header {
                padding: 30px 20px;
            }

            .form-container {
                padding: 30px 20px;
            }

            .footer {
                padding: 20px;
            }

            .header h1 {
                font-size: 24px;
            }

            .step h2 {
                font-size: 20px;
            }
        }

        /* Animation for step transitions */
        .step {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Loading states */
        .btn-loading {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Input validation styles */
        .input-wrapper.error {
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }

        .input-wrapper.success {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
    </style>
</head>

<body>
<?php if ($mode === 'agent'): ?>
    <!-- AGENT QR GENERATOR MODE -->
    <div class="container" style="max-width: 900px;">
        <div class="signup-card">
            <div class="header">
                <div class="logo">
                    <img src="samanet-logo.png" alt="Samanet Logo" width="80" height="80">
                </div>
                <h1>🎫 Agent QR Generator</h1>
                <p class="subtitle">Generate QR codes for user WiFi registration</p>
            </div>

            <div class="form-container">
                <form id="qrForm">
                    <div class="input-group">
                        <label for="plan" style="display: block; color: #374151; font-weight: 600; margin-bottom: 8px;">Select Plan</label>
                        <select id="plan" name="plan" required style="width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 16px;">
                            <?php foreach ($plans as $plan): ?>
                                <option value="<?php echo htmlspecialchars($plan); ?>">
                                    <?php echo htmlspecialchars($plan); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn-primary" id="generateBtn">
                        <span class="btn-text">🎫 Generate QR Code</span>
                    </button>
                </form>

                <div id="qrResult" style="display: none; margin-top: 30px; padding: 30px; background: #f8fafc; border-radius: 12px; border: 2px solid #e2e8f0;">
                    <h3 style="text-align: center; margin-bottom: 20px; color: #1f2937;">QR Code Generated Successfully!</h3>
                    
                    <div style="text-align: center; margin-bottom: 24px;">
                        <div id="qrcode" style="display: inline-block; padding: 20px; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);"></div>
                    </div>

                    <div class="credentials-box">
                        <h3>User Credentials</h3>
                        <div class="credential-item">
                            <span class="credential-label">Username:</span>
                            <span class="credential-value" id="displayUsername">-</span>
                        </div>
                        <div class="credential-item">
                            <span class="credential-label">Password:</span>
                            <span class="credential-value" id="displayPassword">-</span>
                        </div>
                        <div class="credential-item">
                            <span class="credential-label">Plan:</span>
                            <span class="credential-value" id="displayPlan">-</span>
                        </div>
                    </div>

                    <div style="margin: 20px 0; padding: 16px; background: #dbeafe; border-radius: 8px; border: 1px solid #93c5fd;">
                        <p style="margin: 0; color: #1e40af; font-size: 14px;">
                            <strong>ℹ️ Info:</strong> User can scan this QR code to complete registration. The account will be created automatically when they scan.
                        </p>
                    </div>

                    <button type="button" class="btn-primary" style="margin-top: 10px;" onclick="window.print()">
                        🖨️ Print QR Code
                    </button>

                    <button type="button" class="btn-primary" style="margin-top: 10px; background: #e5e7eb; color: #374151;" onclick="location.reload()">
                        🔄 Generate Another QR Code
                    </button>
                </div>
            </div>

            <div class="footer">
                <p>Agent Mode | <a href="?mode=user" class="link">Switch to User Signup</a></p>
                <p class="powered-by">Powered by Samanet ISP</p>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- USER SIGNUP MODE -->
    <div class="container">
        <div class="signup-card">
            <div class="header">
                <div class="logo">
                    <img src="samanet-logo.png" alt="Samanet Logo" width="80" height="80">
                </div>
                <h1>Samanet ISP</h1>
                <p class="subtitle">Free WiFi Registration</p>
            </div>

            <div class="form-container">
                <form id="signupForm" action="signup.php" method="POST">
                    <?php if ($is_qr_signup): ?>
                        <input type="hidden" id="qrUsername" value="<?php echo htmlspecialchars($qr_token_data['username']); ?>">
                        <input type="hidden" id="qrPassword" value="<?php echo htmlspecialchars($qr_token_data['password']); ?>">
                        <input type="hidden" id="qrPlan" value="<?php echo htmlspecialchars($qr_token_data['plan']); ?>">
                    <?php endif; ?>

                    <div class="step" id="step1" style="display: <?php echo ($qr_auto_created ? 'none' : 'block'); ?>;">
                        <?php if ($is_qr_signup && !$qr_auto_created): ?>
                            <h2>🎫 Complete Your Registration</h2>
                            <p class="step-description">Enter your mobile number to activate your WiFi account</p>
                            
                            <div style="background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 20px; border: 2px solid #e2e8f0;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span style="color: #6b7280; font-size: 14px;">Plan:</span>
                                    <span style="font-weight: 600; color: #8b5cf6;"><?php echo htmlspecialchars($qr_token_data['plan']); ?></span>
                                </div>
                            </div>
                        <?php elseif (!$is_qr_signup): ?>
                            <h2>Enter Your Mobile Number</h2>
                            <p class="step-description">We'll create your WiFi account and send your password via SMS</p>
                        <?php endif; ?>

                        <div class="input-group">
                            <div class="input-wrapper">
                                <span class="country-code">+963</span>
                                <input type="tel" id="mobile" name="mobile" placeholder="9XX XXX XXX" required
                                    pattern="[0-9]{9}" maxlength="9" class="mobile-input">
                            </div>
                            <small class="input-help">Enter your 9-digit mobile number without the country code</small>
                        </div>

                        <button type="button" id="sendCodeBtn" class="btn-primary">
                            <span class="btn-text"><?php echo $is_qr_signup ? 'Activate Account' : 'Create WiFi Account'; ?></span>
                            <span class="btn-loading" style="display: none;">
                                <svg class="spinner" width="20" height="20" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" opacity="0.25" />
                                    <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" opacity="0.75" />
                                </svg>
                                Creating Account...
                            </span>
                        </button>
                    </div>

                    <div class="step" id="step2" style="display: none;">
                        <div class="success-message">
                            <div class="success-icon">
                                <svg width="60" height="60" viewBox="0 0 60 60" fill="none">
                                    <circle cx="30" cy="30" r="30" fill="#10b981" />
                                    <path d="M20 30l8 8 12-16" stroke="white" stroke-width="3" fill="none" />
                                </svg>
                            </div>
                            <h2>Account Created Successfully!</h2>
                            <p>Your WiFi credentials have been generated and sent via SMS.</p>

                            <div class="credentials-box">
                                <h3>Your WiFi Credentials</h3>
                                <div class="credential-item">
                                    <span class="credential-label">Username:</span>
                                    <span class="credential-value" id="successUsername">-</span>
                                </div>
                                <div class="credential-item">
                                    <span class="credential-label">Password:</span>
                                    <span class="credential-value" id="successPassword">-</span>
                                </div>
                            </div>

                            <p><strong>Important:</strong> Save these credentials. You'll need them to connect to WiFi.</p>
                            <button type="button" onclick="window.history.back()" class="btn-primary">Go To Login</button>
                        </div>
                    </div>

                    <div class="step" id="step3" style="display: <?php echo ($qr_auto_created ? 'block' : 'none'); ?>;">
                        <div class="success-message">
                            <div class="success-icon">
                                <svg width="60" height="60" viewBox="0 0 60 60" fill="none">
                                    <circle cx="30" cy="30" r="30" fill="#10b981" />
                                    <path d="M20 30l8 8 12-16" stroke="white" stroke-width="3" fill="none" />
                                </svg>
                            </div>
                            <h2>✅ WiFi Account Activated!</h2>
                            <p>Your account has been created successfully. Use these credentials to connect.</p>
                            
                            <?php if ($qr_auto_created && $qr_token_data): ?>
                            <div class="credentials-box">
                                <h3>Your WiFi Credentials</h3>
                                <div class="credential-item">
                                    <span class="credential-label">Username:</span>
                                    <span class="credential-value"><?php echo htmlspecialchars($qr_token_data['username']); ?></span>
                                </div>
                                <div class="credential-item">
                                    <span class="credential-label">Password:</span>
                                    <span class="credential-value"><?php echo htmlspecialchars($qr_token_data['password']); ?></span>
                                </div>
                                <div class="credential-item">
                                    <span class="credential-label">Plan:</span>
                                    <span class="credential-value"><?php echo htmlspecialchars($qr_token_data['plan'] ?? 'Free WiFi'); ?></span>
                                </div>
                            </div>
                            
                            <div class="alert alert-info" style="margin: 20px 0; background: #dbeafe; padding: 16px; border-radius: 8px; border: 1px solid #93c5fd;">
                                <p style="margin: 0; color: #1e40af; font-size: 14px;">
                                    <strong>Next Steps:</strong> Click the button below to go to the WiFi login page and enter your credentials.
                                </p>
                            </div>
                            
                            <button type="button" onclick="redirectToHotspotLogin('<?php echo htmlspecialchars($qr_token_data['username']); ?>', '<?php echo htmlspecialchars($qr_token_data['password']); ?>')" class="btn-primary">
                                🌐 Connect to WiFi
                            </button>
                            <?php else: ?>
                            <p>Your WiFi access has been activated.</p>
                            <button type="button" onclick="window.history.back()" class="btn-primary">Go to Login</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>

                <div class="error-message" id="errorMessage" style="display: <?php echo ($qr_creation_error ? 'flex' : 'none'); ?>;">
                    <span class="error-icon">⚠️</span>
                    <p id="errorText"><?php echo $qr_creation_error ? 'Error creating account: ' . htmlspecialchars($qr_creation_error) : ''; ?></p>
                </div>

            </div>

            <div class="footer">
                <p>By registering, you agree to our <a href="#" class="link">Terms of Service</a> and <a href="#" class="link">Privacy Policy</a></p>
                <p class="powered-by">Powered by Samanet ISP • Free WiFi Service</p>
            </div>
        </div>
    </div>

    <script>
        // Signup form JavaScript
        let currentStep = 1;
        let mobileNumber = '';

        document.getElementById('sendCodeBtn').addEventListener('click', function() {
            const mobile = document.getElementById('mobile').value;
            if (!mobile || mobile.length !== 9) {
                showError('Please enter a valid 9-digit mobile number');
                return;
            }

            mobileNumber = '+963' + mobile;

            // Show loading state
            this.querySelector('.btn-text').style.display = 'none';
            this.querySelector('.btn-loading').style.display = 'flex';
            this.disabled = true;

            const formData = new FormData();
            
            const qrUsername = document.getElementById('qrUsername');
            const qrPassword = document.getElementById('qrPassword');
            const qrPlan = document.getElementById('qrPlan');
            
            if (qrUsername && qrPassword && qrPlan) {
                formData.append('action', 'create_qr_user');
                formData.append('qr_username', qrUsername.value);
                formData.append('qr_password', qrPassword.value);
                formData.append('qr_plan', qrPlan.value);
            } else {
                formData.append('action', 'create_user');
            }
            
            formData.append('mobile', mobileNumber);

            fetch('signup.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showStep(2);
                        document.getElementById('successUsername').textContent = data.username;
                        document.getElementById('successPassword').textContent = data.password;

                        if (data.sms_sent) {
                            showError('Account created! Your password has been sent via SMS to ' + mobileNumber);
                        } else {
                            showError('Account created! SMS failed, but here are your credentials: Username: ' + data.username + ', Password: ' + data.password);
                        }
                    } else {
                        showError(data.message || 'Failed to create account. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('Network error: ' + error.message + '. Please check your connection and try again.');
                })
                .finally(() => {
                    this.querySelector('.btn-text').style.display = 'flex';
                    this.querySelector('.btn-loading').style.display = 'none';
                    this.disabled = false;
                });
        });

        // No additional event listeners needed for the simplified flow

        function showStep(step) {
            // Hide all steps
            document.querySelectorAll('.step').forEach(s => s.style.display = 'none');

            // Show current step
            document.getElementById('step' + step).style.display = 'block';
            currentStep = step;

            // Hide error message
            document.getElementById('errorMessage').style.display = 'none';
        }

        function showError(message) {
            document.getElementById('errorText').textContent = message;
            document.getElementById('errorMessage').style.display = 'flex';
        }

        function redirectToHotspotLogin(username, password) {
            const params = new URLSearchParams(window.location.search);
            const hotspotUrl = params.get('hotspot_url') || params.get('redirect') || params.get('uamip');
            
            if (hotspotUrl) {
                const loginUrl = hotspotUrl + '?UserName=' + encodeURIComponent(username) + '&Password=' + encodeURIComponent(password);
                window.location.href = loginUrl;
            } else {
                window.history.back();
            }
        }
    </script>
<?php endif; ?>

<?php if ($mode === 'agent'): ?>
<script>
    // QR Generator JavaScript for Agent Mode
    document.getElementById('qrForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const generateBtn = document.getElementById('generateBtn');
        const btnText = generateBtn.querySelector('.btn-text');
        
        // Show loading state
        btnText.textContent = 'Generating...';
        generateBtn.disabled = true;

        const formData = new FormData(this);
        formData.append('action', 'generate_qr');

        fetch('signup.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Display credentials
                document.getElementById('displayUsername').textContent = data.username;
                document.getElementById('displayPassword').textContent = data.password;
                document.getElementById('displayPlan').textContent = data.plan;

                // Generate QR code
                document.getElementById('qrcode').innerHTML = '';
                new QRCode(document.getElementById('qrcode'), {
                    text: data.qr_url,
                    width: 200,
                    height: 200,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });

                // Show result section
                document.getElementById('qrResult').style.display = 'block';

                // Scroll to result
                document.getElementById('qrResult').scrollIntoView({ behavior: 'smooth' });
            } else {
                alert('Error generating QR code: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Network error: ' + error.message);
        })
        .finally(() => {
            // Reset button
            btnText.textContent = '🎫 Generate QR Code';
            generateBtn.disabled = false;
        });
    });
</script>
<?php endif; ?>

</body>
</html>