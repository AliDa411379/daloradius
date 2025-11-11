<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// DB config
$db_host = "172.30.16.200";
$db_user = "bassel";
$db_pass = "bassel_password";
$db_name = "radius";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    die(json_encode(['success' => false, 'message' => 'Invalid request format']));
}

$action = $input['action'] ?? '';
$mobile = $input['mobile'] ?? '';

// Validate mobile number format
if (!preg_match('/^\+963[0-9]{9}$/', $mobile)) {
    die(json_encode(['success' => false, 'message' => 'Invalid mobile number format']));
}

if ($action === 'send_code') {
    // Generate 6-digit verification code
    $verification_code = sprintf('%06d', mt_rand(100000, 999999));
    
    // Set expiration time (10 minutes from now)
    $expires_at = date('Y-m-d H:i:s', time() + 600);
    
    // Clean up old codes for this mobile number
    $cleanup_sql = "DELETE FROM sms_verification WHERE mobile = ? OR expires_at < NOW()";
    $stmt = $conn->prepare($cleanup_sql);
    $stmt->bind_param("s", $mobile);
    $stmt->execute();
    
    // Insert new verification code
    $insert_sql = "INSERT INTO sms_verification (mobile, verification_code, expires_at) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("sss", $mobile, $verification_code, $expires_at);
    
    if ($stmt->execute()) {
        // Send SMS with verification code
        $sms_sent = sendVerificationSMS($mobile, $verification_code);
        
        // For testing purposes, always return success even if SMS fails
        // In production, you should configure a real SMS provider
        echo json_encode([
            'success' => true, 
            'message' => $sms_sent ? 'Verification code sent successfully' : 'Verification code generated (SMS not configured). For testing, check the database or logs.',
            'expires_in' => 600, // 10 minutes
            'debug_code' => $verification_code // Remove this in production!
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: ' . $conn->error
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function sendVerificationSMS($mobile, $code) {
    global $conn;
    
    // SMS message content
    $sms_message = "Your Samanen ISP WiFi verification code is: $code\n\nThis code will expire in 10 minutes.\n\nDo not share this code with anyone.";
    
    // Create SMS queue table if it doesn't exist
    $create_queue_sql = "CREATE TABLE IF NOT EXISTS sms_queue (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mobile VARCHAR(20) NOT NULL,
        message TEXT NOT NULL,
        message_type ENUM('verification', 'password', 'notification') DEFAULT 'verification',
        status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
        attempts INT DEFAULT 0,
        max_attempts INT DEFAULT 3,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        sent_at TIMESTAMP NULL,
        error_message TEXT NULL,
        INDEX idx_status (status),
        INDEX idx_mobile (mobile),
        INDEX idx_created (created_at)
    )";
    $conn->query($create_queue_sql);
    
    // Add SMS to queue
    $queue_sql = "INSERT INTO sms_queue (mobile, message, message_type) VALUES (?, ?, 'verification')";
    $stmt = $conn->prepare($queue_sql);
    $stmt->bind_param("ss", $mobile, $sms_message);
    
    if ($stmt->execute()) {
        error_log("SMS queued for $mobile - verification code: $code");
        return true;
    } else {
        error_log("Failed to queue SMS for $mobile: " . $conn->error);
        return false;
    }
}

// Alternative SMS providers configuration examples:

/*
// Example for Twilio SMS API
function sendVerificationSMS_Twilio($mobile, $code) {
    $account_sid = 'your_twilio_account_sid';
    $auth_token = 'your_twilio_auth_token';
    $twilio_number = '+1234567890'; // Your Twilio phone number
    
    $message = "Your Samanen ISP WiFi verification code is: $code\n\nThis code will expire in 10 minutes.";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.twilio.com/2010-04-01/Accounts/$account_sid/Messages.json");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'From' => $twilio_number,
        'To' => $mobile,
        'Body' => $message
    ]));
    curl_setopt($ch, CURLOPT_USERPWD, "$account_sid:$auth_token");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLOPT_HTTP_CODE);
    curl_close($ch);
    
    return $http_code === 201;
}
*/

/*
// Example for local Syrian SMS provider
function sendVerificationSMS_Syrian($mobile, $code) {
    $api_url = "https://sms.syrian-provider.com/api/send";
    $username = "your_username";
    $password = "your_password";
    
    $message = "Your Samanen ISP WiFi verification code is: $code\n\nThis code will expire in 10 minutes.";
    
    $post_data = [
        'username' => $username,
        'password' => $password,
        'to' => $mobile,
        'message' => $message,
        'sender' => 'SamanenISP'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLOPT_HTTP_CODE);
    curl_close($ch);
    
    return $http_code === 200;
}
*/

?>