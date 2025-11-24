<?php

/*
 *********************************************************************************************************
 * daloRADIUS - AGENT PORTAL - LOGIN
 * Dedicated login page for agents
 *
 *********************************************************************************************************
 */

// Enable error logging
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', '/tmp/daloradius_agent_login.log');

$app_base = __DIR__ . '/../';

include($app_base . "operators/library/sessions.php");
dalo_session_start();

// If already logged in as an agent, redirect to index
if (isset($_SESSION['daloradius_logged_in']) && $_SESSION['daloradius_logged_in'] === true) {
    if (isset($_SESSION['operator_id'])) {
        error_log("LOGIN: User already logged in as operator_id=" . $_SESSION['operator_id']);
        header("Location: index.php");
        exit();
    }
}

$loginError = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("LOGIN ATTEMPT: Starting POST handler");
    
    try {
        error_log("LOGIN ATTEMPT: Including config_read.php");
        include_once($app_base . 'common/includes/config_read.php');
        
        error_log("LOGIN ATTEMPT: Including db_open.php");
        include($app_base . 'common/includes/db_open.php');
        
        error_log("LOGIN ATTEMPT: dbSocket = " . (isset($dbSocket) ? gettype($dbSocket) : "NOT SET"));
        
        if (!isset($dbSocket) || is_null($dbSocket)) {
            error_log("LOGIN ATTEMPT: FATAL - dbSocket is not set or null");
            $loginError = "Database connection failed";
        } else {
            error_log("LOGIN ATTEMPT: Getting POST variables");
            $username = isset($_POST['username']) ? trim($_POST['username']) : '';
            $password = isset($_POST['password']) ? $_POST['password'] : '';
            
            error_log("LOGIN ATTEMPT: Username=" . $username);
            
            $login_success = false;
            
            if (empty($username) || empty($password)) {
                $loginError = "Username and password are required";
                error_log("LOGIN ATTEMPT: Empty credentials");
            } else {
                error_log("LOGIN ATTEMPT: Building SQL query");
                $sql = sprintf("SELECT id, password, is_agent FROM %s WHERE username = '%s' LIMIT 1",
                               $configValues['CONFIG_DB_TBL_DALOOPERATORS'],
                               $dbSocket->escapeSimple($username));
                
                error_log("LOGIN ATTEMPT: Executing query");
                $res = $dbSocket->query($sql);
                
                error_log("LOGIN ATTEMPT: Query result type = " . gettype($res));
                
                if (!DB::isError($res)) {
                    error_log("LOGIN ATTEMPT: No DB error, fetching row");
                    $row = $res->fetchRow();
                    if ($row) {
                        error_log("LOGIN ATTEMPT: Row found");
                        $operator_id = intval($row[0]);
                        $stored_pass = $row[1];
                        $is_agent = intval($row[2]);
                        
                        if ($is_agent == 1) {
                            // Check password - try plaintext first, then MD5, then bcrypt
                            $password_matches = false;
                            
                            if ($password === $stored_pass) {
                                // Plaintext password match
                                $password_matches = true;
                                error_log("LOGIN ATTEMPT: Plaintext password match");
                            } elseif (md5($password) === $stored_pass) {
                                // MD5 password match
                                $password_matches = true;
                                error_log("LOGIN ATTEMPT: MD5 password match");
                            } elseif (password_verify($password, $stored_pass)) {
                                // Bcrypt password match
                                $password_matches = true;
                                error_log("LOGIN ATTEMPT: Bcrypt password match");
                            }
                            
                            if ($password_matches) {
                                error_log("LOGIN ATTEMPT: Password match - setting session");
                                $_SESSION['operator_user'] = $username;
                                $_SESSION['operator_id'] = $operator_id;
                                $_SESSION['daloradius_logged_in'] = true;
                                $login_success = true;
                            } else {
                                $loginError = "Invalid password";
                                error_log("LOGIN ATTEMPT: Password mismatch - stored=$stored_pass, input=$password");
                            }
                        } else {
                            $loginError = "This account is not authorized for agent portal access";
                            error_log("LOGIN ATTEMPT: Not an agent (is_agent=$is_agent)");
                        }
                    } else {
                        $loginError = "Username not found";
                        error_log("LOGIN ATTEMPT: Row not found");
                    }
                } else {
                    error_log("LOGIN ATTEMPT: DB Error - " . $res->getMessage());
                    $loginError = "Database error: " . $res->getMessage();
                }
            }
            
            // Always close database connection
            include($app_base . 'common/includes/db_close.php');
            error_log("LOGIN ATTEMPT: DB closed");
            
            if ($login_success) {
                error_log("LOGIN ATTEMPT: Login successful");
                error_log("LOGIN ATTEMPT: Session data - operator_user=" . $_SESSION['operator_user'] . ", operator_id=" . $_SESSION['operator_id']);
                
                // Ensure session is written to disk
                session_write_close();
                
                error_log("LOGIN ATTEMPT: Redirecting to index.php");
                header("Location: index.php");
                exit();
            }
        }
    } catch (Exception $e) {
        error_log("LOGIN ATTEMPT: Exception caught - " . $e->getMessage());
        $loginError = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Portal Login - daloRADIUS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 400px;
            width: 100%;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 5px;
        }
        .login-header p {
            color: #666;
            font-size: 14px;
        }
        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            padding: 10px 15px;
            font-size: 14px;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-weight: 600;
            padding: 12px;
            border-radius: 5px;
            width: 100%;
            margin-top: 20px;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #5568d3 0%, #653a8a 100%);
            color: white;
        }
        .alert {
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .login-footer {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Agent Portal</h1>
            <p>daloRADIUS Agent Management System</p>
        </div>
        
        <?php if (!empty($loginError)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($loginError); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" class="form-control" id="username" name="username" 
                       value="<?php echo htmlspecialchars($username); ?>" 
                       placeholder="Enter your username" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" 
                       placeholder="Enter your password" required>
            </div>
            
            <button type="submit" class="btn btn-login">Login to Agent Portal</button>
        </form>
        
        <div class="login-footer">
            <p>For support, contact your system administrator.</p>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
