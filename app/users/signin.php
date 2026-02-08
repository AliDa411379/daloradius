<?php
/*
 *********************************************************************************************************
 * Samanet ISP - Central Hotspot Sign In Page
 * 
 * This page serves as the central authentication point for MikroTik hotspot users.
 * Users are redirected here from MikroTik, enter their credentials, and upon successful
 * authentication, are forwarded back to MikroTik with the login parameters.
 *
 * Query Parameters supported from MikroTik:
 *   - mac: Client MAC address
 *   - ip: Client IP address  
 *   - username: Pre-filled username (optional)
 *   - link-login: Login URL for MikroTik
 *   - link-orig: Original destination URL
 *   - error: Error message from MikroTik
 *   - chap-id: CHAP ID for authentication
 *   - chap-challenge: CHAP challenge string
 *   - link-login-only: Login-only URL
 *   - link-orig-esc: Escaped original URL
 *   - mac-esc: Escaped MAC address
 *
 *********************************************************************************************************
 */

session_start();

// Database configuration
$db_host = "172.30.16.200";
$db_user = "bassel";
$db_pass = "bassel_password";
$db_name = "radius";

// Get MikroTik hotspot parameters
$mac = $_GET['mac'] ?? '';
$ip = $_GET['ip'] ?? '';
$username = $_GET['username'] ?? '';
$link_login = $_GET['link-login'] ?? '';
$link_orig = $_GET['link-orig'] ?? '';
$error = $_GET['error'] ?? '';
$chap_id = $_GET['chap-id'] ?? '';
$chap_challenge = $_GET['chap-challenge'] ?? '';
$link_login_only = $_GET['link-login-only'] ?? '';

// Handle login form submission
$login_error = '';
$login_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_username = trim($_POST['username'] ?? '');
    $post_password = $_POST['password'] ?? '';
    $return_url = $_POST['return_url'] ?? '';
    
    if (empty($post_username) || empty($post_password)) {
        $login_error = 'Please enter both username and password.';
    } else {
        // Connect to database and verify credentials
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        
        if ($conn->connect_error) {
            $login_error = 'Service temporarily unavailable. Please try again later.';
            error_log("Signin DB connection failed: " . $conn->connect_error);
        } else {
            // Check credentials in radcheck table
            $sql = "SELECT value FROM radcheck WHERE username = ? AND attribute = 'Cleartext-Password' LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $post_username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $stored_password = $row['value'];
                
                if ($post_password === $stored_password) {
                    // Successful authentication
                    $login_success = true;
                    
                    // Check user's balance and bundle status
                    $balance_sql = "SELECT timebank_balance, traffic_balance, planName FROM userbillinfo WHERE username = ? LIMIT 1";
                    $balance_stmt = $conn->prepare($balance_sql);
                    $balance_stmt->bind_param("s", $post_username);
                    $balance_stmt->execute();
                    $balance_result = $balance_stmt->get_result();
                    
                    $has_balance = false;
                    $plan_name = 'Unknown';
                    
                    if ($balance_result->num_rows > 0) {
                        $balance_row = $balance_result->fetch_assoc();
                        $timebank = floatval($balance_row['timebank_balance'] ?? 0);
                        $traffic = floatval($balance_row['traffic_balance'] ?? 0);
                        $plan_name = $balance_row['planName'] ?? 'Unknown';
                        
                        // User has balance if either timebank or traffic is greater than minimal threshold
                        $has_balance = ($timebank > 1 || $traffic > 1);
                    }
                    
                    $_SESSION['signin_username'] = $post_username;
                    $_SESSION['signin_has_balance'] = $has_balance;
                    $_SESSION['signin_plan'] = $plan_name;
                    
                    // If MikroTik login URL is available, redirect with credentials
                    if (!empty($return_url)) {
                        // Build MikroTik login URL with credentials
                        $redirect_url = $return_url;
                        
                        // Parse and rebuild URL with username and password
                        $parsed = parse_url($return_url);
                        $query_params = [];
                        if (isset($parsed['query'])) {
                            parse_str($parsed['query'], $query_params);
                        }
                        $query_params['username'] = $post_username;
                        $query_params['password'] = $post_password;
                        
                        $redirect_url = $parsed['scheme'] . '://' . $parsed['host'];
                        if (isset($parsed['port'])) {
                            $redirect_url .= ':' . $parsed['port'];
                        }
                        if (isset($parsed['path'])) {
                            $redirect_url .= $parsed['path'];
                        }
                        $redirect_url .= '?' . http_build_query($query_params);
                        
                        header("Location: $redirect_url");
                        exit;
                    }
                } else {
                    $login_error = 'Invalid username or password. Please try again.';
                }
            } else {
                $login_error = 'Invalid username or password. Please try again.';
            }
            
            $conn->close();
        }
    }
}

// Map MikroTik error codes to user-friendly messages
function getMikroTikErrorMessage($error) {
    $error_messages = [
        'chap missing' => 'Authentication error. Please refresh the page and try again.',
        'invalid username or password' => 'Invalid username or password.',
        'user not found' => 'User account not found.',
        'password expired' => 'Your password has expired. Please contact support.',
        'already logged in' => 'You are already logged in from another device.',
        'reached session limit' => 'Maximum sessions reached. Please disconnect other devices.',
        'out of time' => 'Your time balance has run out. Please recharge.',
        'out of traffic' => 'Your data balance has run out. Please recharge.',
        'disabled' => 'Your account has been disabled. Please contact support.',
    ];
    
    $error_lower = strtolower($error);
    foreach ($error_messages as $key => $message) {
        if (strpos($error_lower, $key) !== false) {
            return $message;
        }
    }
    
    return !empty($error) ? $error : '';
}

$mikrotik_error = getMikroTikErrorMessage($error);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WiFi Login - Samanet ISP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
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
            max-width: 420px;
            margin: 0 auto;
        }

        .signin-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(255, 255, 255, 0.2);
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            padding: 35px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
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
            margin-bottom: 16px;
            position: relative;
            z-index: 1;
        }

        .logo img {
            width: 70px;
            height: 70px;
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .header h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 6px;
            position: relative;
            z-index: 1;
        }

        .subtitle {
            font-size: 15px;
            opacity: 0.9;
            font-weight: 400;
            position: relative;
            z-index: 1;
        }

        .wifi-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 48px;
            opacity: 0.15;
            z-index: 0;
        }

        .form-container {
            padding: 35px 30px;
        }

        .form-title {
            font-size: 20px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 6px;
            text-align: center;
        }

        .form-description {
            color: #6b7280;
            text-align: center;
            margin-bottom: 28px;
            font-size: 14px;
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-label {
            display: block;
            color: #374151;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
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
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.1);
            background: white;
        }

        .input-icon {
            padding: 0 12px 0 16px;
            color: #9ca3af;
            font-size: 18px;
        }

        .input-field {
            flex: 1;
            border: none;
            padding: 15px 16px 15px 0;
            font-size: 16px;
            background: transparent;
            outline: none;
            color: #1f2937;
            font-weight: 500;
        }

        .input-field::placeholder {
            color: #9ca3af;
            font-weight: 400;
        }

        .password-toggle {
            padding: 0 16px;
            background: none;
            border: none;
            cursor: pointer;
            color: #9ca3af;
            font-size: 18px;
            transition: color 0.2s;
        }

        .password-toggle:hover {
            color: #6b7280;
        }

        .btn-login {
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
            gap: 10px;
            margin-top: 8px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(139, 92, 246, 0.35);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-login .spinner {
            display: none;
            animation: spin 1s linear infinite;
        }

        .btn-login.loading .spinner {
            display: inline-block;
        }

        .btn-login.loading .btn-text {
            display: none;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .error-message {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border: 2px solid #fecaca;
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            animation: shake 0.5s ease-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }

        .error-icon {
            font-size: 20px;
            line-height: 1;
        }

        .error-message p {
            color: #dc2626;
            font-weight: 500;
            margin: 0;
            font-size: 14px;
            line-height: 1.5;
        }

        .warning-message {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 2px solid #f59e0b;
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .warning-icon {
            font-size: 20px;
            line-height: 1;
        }

        .warning-message p {
            color: #92400e;
            font-weight: 500;
            margin: 0;
            font-size: 14px;
            line-height: 1.5;
        }

        .success-message {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border: 2px solid #34d399;
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .success-icon {
            font-size: 20px;
            line-height: 1;
        }

        .success-message p {
            color: #047857;
            font-weight: 500;
            margin: 0;
            font-size: 14px;
            line-height: 1.5;
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 28px 0;
            color: #9ca3af;
            font-size: 13px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e5e7eb;
        }

        .divider span {
            padding: 0 16px;
            background: white;
        }

        .signup-link {
            text-align: center;
        }

        .signup-link p {
            color: #6b7280;
            font-size: 14px;
            margin: 0;
        }

        .signup-link a {
            color: #8b5cf6;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s;
        }

        .signup-link a:hover {
            color: #7c3aed;
            text-decoration: underline;
        }

        .footer {
            background: #f8fafc;
            padding: 20px 30px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }

        .footer p {
            margin: 6px 0;
            font-size: 12px;
            color: #6b7280;
        }

        .footer .powered-by {
            font-weight: 600;
            color: #374151;
        }

        .connection-info {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 24px;
            font-size: 13px;
        }

        .connection-info-row {
            display: flex;
            justify-content: space-between;
            margin: 4px 0;
            color: #0369a1;
        }

        .connection-info-label {
            font-weight: 600;
        }

        .connection-info-value {
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }

        /* Responsive Design */
        @media (max-width: 480px) {
            .container {
                padding: 10px;
            }

            .signin-card {
                border-radius: 20px;
            }

            .header {
                padding: 30px 24px;
            }

            .form-container {
                padding: 28px 24px;
            }

            .footer {
                padding: 16px 24px;
            }

            .header h1 {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="signin-card">
            <div class="header">
                <span class="wifi-icon">📶</span>
                <div class="logo">
                    <img src="samanet-logo.png" alt="Samanet Logo">
                </div>
                <h1>Samanet ISP</h1>
                <p class="subtitle">WiFi Hotspot Login</p>
            </div>

            <div class="form-container">
                <h2 class="form-title">Welcome Back!</h2>
                <p class="form-description">Enter your credentials to connect to the internet</p>

                <?php if (!empty($mikrotik_error)): ?>
                <div class="error-message">
                    <span class="error-icon">❌</span>
                    <p><?php echo htmlspecialchars($mikrotik_error); ?></p>
                </div>
                <?php endif; ?>

                <?php if (!empty($login_error)): ?>
                <div class="error-message">
                    <span class="error-icon">⚠️</span>
                    <p><?php echo htmlspecialchars($login_error); ?></p>
                </div>
                <?php endif; ?>

                <?php if ($login_success && !isset($_SESSION['signin_has_balance'])): ?>
                <div class="success-message">
                    <span class="success-icon">✅</span>
                    <p>Login successful! Connecting you to the internet...</p>
                </div>
                <?php endif; ?>

                <?php if ($login_success && isset($_SESSION['signin_has_balance']) && !$_SESSION['signin_has_balance']): ?>
                <div class="warning-message">
                    <span class="warning-icon">⚠️</span>
                    <div>
                        <p><strong>Account verified but no active balance!</strong></p>
                        <p style="margin-top: 6px; font-weight: 400;">You need to charge your balance and pay for your bundle to get internet access. Please contact your agent.</p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($mac) || !empty($ip)): ?>
                <div class="connection-info">
                    <?php if (!empty($ip)): ?>
                    <div class="connection-info-row">
                        <span class="connection-info-label">Your IP:</span>
                        <span class="connection-info-value"><?php echo htmlspecialchars($ip); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($mac)): ?>
                    <div class="connection-info-row">
                        <span class="connection-info-label">Your MAC:</span>
                        <span class="connection-info-value"><?php echo htmlspecialchars($mac); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="" id="signinForm">
                    <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($link_login ?: $link_login_only); ?>">
                    
                    <div class="input-group">
                        <label class="input-label" for="username">Username / Phone Number</label>
                        <div class="input-wrapper">
                            <span class="input-icon">👤</span>
                            <input type="text" 
                                   id="username" 
                                   name="username" 
                                   class="input-field" 
                                   placeholder="Enter your username"
                                   value="<?php echo htmlspecialchars($username); ?>"
                                   required
                                   autocomplete="username">
                        </div>
                    </div>

                    <div class="input-group">
                        <label class="input-label" for="password">Password</label>
                        <div class="input-wrapper">
                            <span class="input-icon">🔒</span>
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   class="input-field" 
                                   placeholder="Enter your password"
                                   required
                                   autocomplete="current-password">
                            <button type="button" class="password-toggle" onclick="togglePassword()" aria-label="Toggle password visibility">
                                <span id="toggleIcon">👁️</span>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-login" id="loginBtn">
                        <svg class="spinner" width="20" height="20" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" opacity="0.25"/>
                            <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" opacity="0.75"/>
                        </svg>
                        <span class="btn-text">🌐 Connect to WiFi</span>
                    </button>
                </form>

                <div class="divider">
                    <span>Don't have an account?</span>
                </div>

                <div class="signup-link">
                    <p>
                        <a href="signup.php<?php echo !empty($link_login) ? '?hotspot_url=' . urlencode($link_login) : ''; ?>">
                            Create a new account →
                        </a>
                    </p>
                </div>
            </div>

            <div class="footer">
                <p>By logging in, you agree to our Terms of Service</p>
                <p class="powered-by">Powered by Samanet ISP</p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.textContent = '🙈';
            } else {
                passwordField.type = 'password';
                toggleIcon.textContent = '👁️';
            }
        }

        // Form submission loading state
        document.getElementById('signinForm').addEventListener('submit', function(e) {
            const loginBtn = document.getElementById('loginBtn');
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                return;
            }
            
            loginBtn.classList.add('loading');
            loginBtn.disabled = true;
        });

        // Auto-focus on username field if empty, otherwise on password
        document.addEventListener('DOMContentLoaded', function() {
            const usernameField = document.getElementById('username');
            const passwordField = document.getElementById('password');
            
            if (usernameField.value.trim() === '') {
                usernameField.focus();
            } else {
                passwordField.focus();
            }
        });

        // Handle MikroTik direct login (POST from MikroTik)
        <?php if (!empty($link_login) && !empty($username)): ?>
        // If we have a login link and username from MikroTik, focus on password
        document.getElementById('password').focus();
        <?php endif; ?>
    </script>
</body>
</html>
