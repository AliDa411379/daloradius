<?php
/**
 * Test script for create_user_erp.php API
 * 
 * Usage: Access via web browser or command line
 * php test_create_user_erp.php
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DaloRADIUS ERP User Creation API - Test</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: #2c3e50;
            color: white;
            padding: 30px;
        }
        .header h1 {
            margin-bottom: 10px;
        }
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        .content {
            padding: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        input, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
            font-size: 14px;
        }
        input:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        button {
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }
        button:hover {
            background: #5568d3;
        }
        button:active {
            transform: translateY(1px);
        }
        .info-box {
            background: #f0f4ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 13px;
            line-height: 1.6;
        }
        .info-box strong {
            color: #667eea;
        }
        .result {
            margin-top: 30px;
            padding: 20px;
            border: 2px solid #ddd;
            border-radius: 4px;
            display: none;
        }
        .result.success {
            display: block;
            background: #f0fff4;
            border-color: #9ae6b4;
        }
        .result.error {
            display: block;
            background: #fff5f5;
            border-color: #fc8181;
        }
        .result-title {
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .result.success .result-title {
            color: #22543d;
        }
        .result.error .result-title {
            color: #742a2a;
        }
        .result-content {
            font-size: 13px;
            font-family: 'Courier New', monospace;
            line-height: 1.6;
            background: white;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
            max-height: 500px;
            overflow-y: auto;
        }
        .qrcode-display {
            margin-top: 15px;
            text-align: center;
        }
        .qrcode-display img {
            max-width: 300px;
            border: 2px solid #ddd;
            border-radius: 4px;
        }
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        .loading span {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #667eea;
            border-radius: 50%;
            animation: pulse 1.5s infinite;
            margin: 0 3px;
        }
        .loading span:nth-child(2) {
            animation-delay: 0.2s;
        }
        .loading span:nth-child(3) {
            animation-delay: 0.4s;
        }
        @keyframes pulse {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 1; }
        }
        .curl-example {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
            margin: 20px 0;
            line-height: 1.6;
        }
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>DaloRADIUS ERP User Creation API</h1>
            <p>Test the user creation endpoint with ERP invoice ID support</p>
        </div>
        
        <div class="content">
            <div class="info-box">
                <strong>📌 Note:</strong> This form sends requests to <code>create_user_erp.php</code>. 
                Make sure to update the API_KEY in the script before testing in production.
            </div>
            
            <form id="testForm">
                <div class="form-group">
                    <label for="apiKey">API Key (Optional - remove if using session auth)</label>
                    <input type="password" id="apiKey" placeholder="your-secret-api-key-here">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="externalInvoiceId">External Invoice ID *</label>
                        <input type="text" id="externalInvoiceId" placeholder="e.g., INV-2025-001234" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="planName">Plan Name *</label>
                        <input type="text" id="planName" placeholder="e.g., Premium Plan" required>
                    </div>
                </div>
                
                <button type="submit">Create User</button>
            </form>
            
            <div class="curl-example" id="curlExample" style="display: none;">
                <strong>cURL Example:</strong><br>
                <code id="curlCode"></code>
            </div>
            
            <div class="loading" id="loading">
                <span></span><span></span><span></span>
                Creating user...
            </div>
            
            <div class="result" id="result">
                <div class="result-title"></div>
                <div class="result-content"></div>
                <div class="qrcode-display"></div>
            </div>
        </div>
    </div>
    
    <script>
        document.getElementById('testForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const apiKey = document.getElementById('apiKey').value;
            const externalInvoiceId = document.getElementById('externalInvoiceId').value;
            const planName = document.getElementById('planName').value;
            
            if (!externalInvoiceId || !planName) {
                alert('Please fill in all required fields');
                return;
            }
            
            document.getElementById('loading').style.display = 'block';
            document.getElementById('result').style.display = 'none';
            
            try {
                const headers = {
                    'Content-Type': 'application/json'
                };
                if (apiKey) {
                    headers['X-API-Key'] = apiKey;
                }
                
                const response = await fetch('create_user_erp.php', {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify({
                        external_invoice_id: externalInvoiceId,
                        plan_name: planName
                    })
                });
                
                const data = await response.json();
                
                document.getElementById('loading').style.display = 'none';
                const resultDiv = document.getElementById('result');
                const resultTitle = resultDiv.querySelector('.result-title');
                const resultContent = resultDiv.querySelector('.result-content');
                const qrcodeDisplay = resultDiv.querySelector('.qrcode-display');
                
                if (data.success) {
                    resultDiv.className = 'result success';
                    resultTitle.textContent = '✅ User Created Successfully!';
                    
                    let html = '<strong>Credentials:</strong><br>';
                    html += '├ Username: ' + data.data.username + '<br>';
                    html += '├ Password: ' + data.data.password + '<br>';
                    html += '├ Plan: ' + data.data.plan_name + '<br>';
                    html += '├ External Invoice ID: ' + data.data.external_invoice_id + '<br>';
                    html += '├ Created: ' + data.data.created_at + '<br>';
                    html += '├ Traffic Balance: ' + data.data.traffic_balance + ' MB<br>';
                    html += '└ Time Balance: ' + data.data.time_balance + ' minutes<br>';
                    html += '<br><strong>Full Response:</strong><br>';
                    html += '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                    
                    resultContent.innerHTML = html;
                    
                    if (data.data.qrcode_url) {
                        qrcodeDisplay.innerHTML = '<strong>QR Code:</strong><br><img src="' + data.data.qrcode_url + '" alt="QR Code">';
                    }
                } else {
                    resultDiv.className = 'result error';
                    resultTitle.textContent = '❌ Error: ' + data.message;
                    resultContent.innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                }
                
                resultDiv.style.display = 'block';
            } catch (error) {
                document.getElementById('loading').style.display = 'none';
                const resultDiv = document.getElementById('result');
                resultDiv.className = 'result error';
                resultDiv.querySelector('.result-title').textContent = '❌ Network Error';
                resultDiv.querySelector('.result-content').textContent = error.message;
                resultDiv.style.display = 'block';
            }
        });
        
        document.getElementById('externalInvoiceId').addEventListener('change', updateCurl);
        document.getElementById('planName').addEventListener('change', updateCurl);
        document.getElementById('apiKey').addEventListener('change', updateCurl);
        
        function updateCurl() {
            const apiKey = document.getElementById('apiKey').value;
            const externalInvoiceId = document.getElementById('externalInvoiceId').value;
            const planName = document.getElementById('planName').value;
            
            if (!externalInvoiceId || !planName) return;
            
            let curl = 'curl -X POST http://your-domain/app/api/create_user_erp.php \\\n';
            if (apiKey) {
                curl += '  -H "X-API-Key: ' + apiKey + '" \\\n';
            }
            curl += '  -H "Content-Type: application/json" \\\n';
            curl += '  -d \'{\n';
            curl += '    "external_invoice_id": "' + externalInvoiceId + '",\n';
            curl += '    "plan_name": "' + planName + '"\n';
            curl += '  }\'';
            
            document.getElementById('curlCode').textContent = curl;
            document.getElementById('curlExample').style.display = 'block';
        }
    </script>
</body>
</html>
