<?php
session_start();



// Include the database helper
require_once('signup_db_new.php');

// Handle AJAX requests for user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Clean output buffer
    if (ob_get_level()) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    error_reporting(0);
    ini_set('display_errors', 0);

    $conn = getDBConnection(true);

    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_user') {
        $mobile = $_POST['mobile'] ?? '';
        $agent_id = $_POST['agent_id'] ?? '';

        $result = registerUser($conn, $mobile, null, $agent_id);
        echo json_encode($result);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }
}

// Fetch agents for UI
$conn = getDBConnection(false);
$agents = fetchActiveAgents($conn);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WiFi Registration - Samanet ISP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="signup_new.css">
</head>

<body>
    <div class="container">
        <div class="signup-card">
            <div class="header">
                <div class="logo"><img src="samanet-logo.png" alt="Samanet Logo" width="80" height="80"></div>
                <h1>Samanet ISP</h1>
                <p class="subtitle">Free WiFi Registration</p>
            </div>
            <div class="form-container">
                <form id="signupForm" action="signup_new.php" method="POST">
                    <div class="step" id="step1">
                        <h2>Create Your Account</h2>
                        <p class="step-description">Enter your mobile number and select your agent to get started instantly</p>
                        <div class="input-group">
                            <label for="agent_id">Select Agent *</label>
                            <select id="agent_id" name="agent_id" required>
                                <option value="">-- Select an Agent --</option>
                                <?php foreach ($agents as $agent): ?>
                                    <option value="<?php echo htmlspecialchars($agent['id']); ?>"><?php echo htmlspecialchars($agent['name']); ?> <?php if(!empty($agent['company'])) echo '- ' . htmlspecialchars($agent['company']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="input-group">
                            <div class="input-wrapper">
                                <span class="country-code">+963</span>
                                <input type="tel" id="mobile" name="mobile" placeholder="9XX XXX XXX" required pattern="[0-9]{9}" maxlength="9" class="mobile-input">
                            </div>
                            <small class="input-help">Enter your 9-digit mobile number without the country code</small>
                        </div>

                        <button type="button" id="sendCodeBtn" class="btn-primary">
                            <span class="btn-text">Create WiFi Account</span>
                            <span class="btn-loading" style="display: none;">
                                <svg class="spinner" width="20" height="20" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" opacity="0.25" /><path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" opacity="0.75" /></svg>
                                Creating Account...
                            </span>
                        </button>
                    </div>

                    <div class="step" id="step2" style="display: none;">
                        <div class="success-message">
                            <div class="success-icon">
                                <svg width="60" height="60" viewBox="0 0 60 60" fill="none"><circle cx="30" cy="30" r="30" fill="#10b981" /><path d="M20 30l8 8 12-16" stroke="white" stroke-width="3" fill="none" /></svg>
                            </div>
                            <h2>Account Created Successfully!</h2>
                            <p>Your WiFi credentials have been generated and sent via SMS.</p>
                            <div class="credentials-box">
                                <h3>Your WiFi Credentials</h3>
                                <div class="credential-item"><span class="credential-label">Username:</span><span class="credential-value" id="successUsername">-</span></div>
                                <div class="credential-item"><span class="credential-label">Password:</span><span class="credential-value" id="successPassword">-</span></div>
                            </div>
                            <!-- Notice -->
                            <div style="margin: 20px 0; padding: 16px; background: linear-gradient(135deg, #ecfeff 0%, #cffafe 100%); border-radius: 12px; border: 2px solid #06b6d4;">
                                <div style="display: flex; align-items: flex-start; gap: 12px;">
                                    <span style="font-size: 24px; line-height: 1;">ℹ️</span>
                                    <div>
                                        <h4 style="margin: 0 0 8px 0; color: #0e7490; font-size: 16px; font-weight: 700;">Free Access</h4>
                                        <p style="margin: 0; color: #155e75; font-size: 14px;"><strong>You have free internet for one hour.</strong> After that, please charge your balance to continue.</p>
                                    </div>
                                </div>
                            </div>
                            <p><strong>Important:</strong> Save these credentials.</p>
                            <button type="button" onclick="window.history.back()" class="btn-primary">Go To Login</button>
                        </div>
                    </div>
                </form>
                <div class="error-message" id="errorMessage" style="display: none;">
                    <span class="error-icon">⚠️</span>
                    <p id="errorText"></p>
                </div>
            </div>
            <div class="footer"><p>Powered by Samanet ISP</p></div>
        </div>
    </div>
    <script src="signup_new.js"></script>
</body>
</html>
