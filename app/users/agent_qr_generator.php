<?php
session_start();

$db_host = "172.30.16.200";
$db_user = "bassel";
$db_pass = "bassel_password";
$db_name = "radius";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Database connection failed");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    if ($_POST['action'] === 'generate_qr') {
        $planName = $_POST['plan'] ?? 'Free WiFi';
        
        $username = generateUsername();
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
}

function generateUsername() {
    return 'user_' . time() . rand(100, 999);
}

function generateRandomPassword($length = 8) {
    $characters = '0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

$plans_query = "SELECT DISTINCT plan_name FROM billing_plans_profiles ORDER BY plan_name";
$plans_result = $conn->query($plans_query);
$plans = [];
if ($plans_result && $plans_result->num_rows > 0) {
    while ($row = $plans_result->fetch_assoc()) {
        $plans[] = $row['plan_name'];
    }
}

if (empty($plans)) {
    $plans = ['Free WiFi', 'Basic Plan', 'Premium Plan'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent QR Generator - Samanet ISP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
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
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .header {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .header h1 {
            color: #1f2937;
            font-size: 28px;
            margin-bottom: 8px;
        }

        .header p {
            color: #6b7280;
            font-size: 14px;
        }

        .main-card {
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .form-group {
            margin-bottom: 24px;
        }

        label {
            display: block;
            color: #374151;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        select, input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 16px;
            color: #1f2937;
            transition: all 0.3s ease;
        }

        select:focus, input:focus {
            outline: none;
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }

        .btn {
            width: 100%;
            padding: 14px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(139, 92, 246, 0.3);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #374151;
            margin-top: 12px;
        }

        .btn-secondary:hover {
            background: #cbd5e1;
        }

        .qr-result {
            display: none;
            margin-top: 30px;
            padding: 30px;
            background: #f8fafc;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
        }

        .qr-result.active {
            display: block;
        }

        .qr-container {
            text-align: center;
            margin-bottom: 24px;
        }

        #qrcode {
            display: inline-block;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .credentials {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .credential-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .credential-row:last-child {
            border-bottom: none;
        }

        .credential-label {
            font-weight: 600;
            color: #374151;
        }

        .credential-value {
            font-family: 'Courier New', monospace;
            color: #8b5cf6;
            font-weight: 600;
        }

        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-top: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }

        .print-btn {
            margin-top: 20px;
        }

        @media print {
            body {
                background: white;
            }

            .container {
                max-width: 100%;
            }

            .header, .form-section {
                display: none;
            }

            .qr-result {
                border: none;
                box-shadow: none;
            }
        }

        .spinner {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎫 Agent QR Generator</h1>
            <p>Generate QR codes for user WiFi registration</p>
        </div>

        <div class="main-card">
            <div class="form-section">
                <h2 style="margin-bottom: 24px; color: #1f2937;">Generate New User QR Code</h2>
                
                <form id="qrForm">
                    <div class="form-group">
                        <label for="plan">Select Plan</label>
                        <select id="plan" name="plan" required>
                            <?php foreach ($plans as $plan): ?>
                                <option value="<?php echo htmlspecialchars($plan); ?>">
                                    <?php echo htmlspecialchars($plan); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary" id="generateBtn">
                        <span class="btn-text">Generate QR Code</span>
                    </button>
                </form>
            </div>

            <div class="qr-result" id="qrResult">
                <h3 style="text-align: center; margin-bottom: 20px; color: #1f2937;">QR Code Generated Successfully</h3>
                
                <div class="qr-container">
                    <div id="qrcode"></div>
                </div>

                <div class="credentials">
                    <h4 style="margin-bottom: 16px; color: #374151;">User Credentials</h4>
                    <div class="credential-row">
                        <span class="credential-label">Username:</span>
                        <span class="credential-value" id="displayUsername">-</span>
                    </div>
                    <div class="credential-row">
                        <span class="credential-label">Password:</span>
                        <span class="credential-value" id="displayPassword">-</span>
                    </div>
                    <div class="credential-row">
                        <span class="credential-label">Plan:</span>
                        <span class="credential-value" id="displayPlan">-</span>
                    </div>
                </div>

                <div class="alert alert-info">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <span>User can scan this QR code to complete registration. The account will be created when they scan and confirm.</span>
                </div>

                <button type="button" class="btn btn-primary print-btn" onclick="window.print()">
                    🖨️ Print QR Code
                </button>

                <button type="button" class="btn btn-secondary" onclick="location.reload()">
                    Generate Another QR Code
                </button>
            </div>
        </div>
    </div>

    <script>
        let qrCodeInstance = null;

        document.getElementById('qrForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('generateBtn');
            const btnText = btn.querySelector('.btn-text');
            
            btn.disabled = true;
            btnText.textContent = 'Generating...';

            const formData = new FormData();
            formData.append('action', 'generate_qr');
            formData.append('plan', document.getElementById('plan').value);

            fetch('agent_qr_generator.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('displayUsername').textContent = data.username;
                    document.getElementById('displayPassword').textContent = data.password;
                    document.getElementById('displayPlan').textContent = data.plan;

                    const qrContainer = document.getElementById('qrcode');
                    qrContainer.innerHTML = '';
                    
                    qrCodeInstance = new QRCode(qrContainer, {
                        text: data.qr_url,
                        width: 256,
                        height: 256,
                        colorDark: "#000000",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.H
                    });

                    document.querySelector('.form-section').style.display = 'none';
                    document.getElementById('qrResult').classList.add('active');
                } else {
                    alert('Error: ' + (data.message || 'Failed to generate QR code'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error: ' + error.message);
            })
            .finally(() => {
                btn.disabled = false;
                btnText.textContent = 'Generate QR Code';
            });
        });
    </script>
</body>
</html>
