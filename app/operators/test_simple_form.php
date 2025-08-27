<?php
// Minimal test - bypass all daloRADIUS complexity
session_start();

// Simple permission bypass for testing
if (!isset($_SESSION['operator_user'])) {
    echo "Please login first";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Agent Form Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: white; }
        .container { max-width: 800px; margin: 0 auto; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="text"], input[type="email"] { 
            width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; 
        }
        .btn { 
            background: #007bff; color: white; padding: 10px 20px; 
            border: none; border-radius: 4px; cursor: pointer; 
        }
        .btn:hover { background: #0056b3; }
        fieldset { border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 5px; }
        legend { font-weight: bold; padding: 0 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Simple Agent Form Test</h1>
        <p><strong>Status:</strong> ✅ Page loaded successfully!</p>
        <p><strong>User:</strong> <?php echo $_SESSION['operator_user']; ?></p>
        
        <form method="POST" action="">
            <fieldset>
                <legend>Agent Information</legend>
                
                <div class="form-group">
                    <label for="name">Agent Name *</label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="company">Company</label>
                    <input type="text" id="company" name="company">
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email">
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone">
                </div>
                
                <button type="submit" class="btn">Create Agent</button>
            </fieldset>
        </form>
        
        <?php if ($_POST): ?>
            <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin-top: 20px;">
                <h3>Form Data Received:</h3>
                <pre><?php print_r($_POST); ?></pre>
            </div>
        <?php endif; ?>
        
        <hr>
        <p><a href="mng-agent-new.php">← Back to original agent creation page</a></p>
    </div>
</body>
</html>