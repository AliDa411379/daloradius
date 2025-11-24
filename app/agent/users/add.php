<?php
/*
 *********************************************************************************************************
 * daloRADIUS - AGENT PORTAL - ADD USER
 * Create new user account
 *
 *********************************************************************************************************
 */
 
    $app_base = __DIR__ . '/../../';
    
    session_start();
    
    if (!isset($_SESSION['operator_user']) || !isset($_SESSION['operator_id'])) {
        header("Location: ../login.php");
        exit();
    }
    
    $operator = $_SESSION['operator_user'];
    $operator_id = $_SESSION['operator_id'];

    include_once($app_base . 'common/includes/config_read.php');
    include_once($app_base . "operators/library/agent_functions.php");

    include_once($app_base . "operators/lang/main.php");
    include_once($app_base . "common/includes/validation.php");
    include($app_base . "common/includes/layout.php");
    
    $log = "visited page: ";
    $logAction = "";

    include($app_base . 'common/includes/db_open.php');
    
    $is_current_operator_agent = isCurrentOperatorAgent($dbSocket, $operator_id, $configValues);
    $current_agent_id = $is_current_operator_agent ? getCurrentOperatorAgentId($dbSocket, $operator_id, $configValues) : 0;
    
    if (!$is_current_operator_agent || $current_agent_id <= 0) {
        header('Location: ../index.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        if (array_key_exists('csrf_token', $_POST) && isset($_POST['csrf_token']) && dalo_check_csrf_token($_POST['csrf_token'])) {
            
            $username = (array_key_exists('username', $_POST) && !empty(trim($_POST['username'])))
                       ? trim($_POST['username']) : "";
            $password = (array_key_exists('password', $_POST) && !empty($_POST['password']))
                       ? trim($_POST['password']) : "";
            $email = (array_key_exists('email', $_POST)) ? trim($_POST['email']) : "";
            $firstname = (array_key_exists('firstname', $_POST)) ? trim($_POST['firstname']) : "";
            $lastname = (array_key_exists('lastname', $_POST)) ? trim($_POST['lastname']) : "";
            $initial_balance = (array_key_exists('initial_balance', $_POST) && is_numeric($_POST['initial_balance']))
                             ? floatval($_POST['initial_balance']) : 0;
            
            $required_fields = array();
            if (empty($username)) {
                $required_fields['username'] = "Username";
            }
            if (empty($password)) {
                $required_fields['password'] = "Password";
            }
            if (strlen($password) < 6) {
                $required_fields['password_length'] = "Password must be at least 6 characters";
            }
            
            if (count($required_fields) > 0) {
                $failureMsg = "Missing required fields: " . implode(", ", array_values($required_fields));
            } else {
                
                try {
                    // Check if username already exists
                    $sql_check = sprintf("SELECT COUNT(*) FROM %s WHERE username = '%s'",
                                        $configValues['CONFIG_DB_TBL_DALOUSERINFO'],
                                        $dbSocket->escapeSimple($username));
                    $res = $dbSocket->query($sql_check);
                    if ($res && $row = $res->fetchRow()) {
                        if ($row[0] > 0) {
                            throw new Exception("Username already exists");
                        }
                    }
                    
                    // Create user in userinfo
                    $sql_insert_ui = sprintf("INSERT INTO %s (username, firstname, lastname, email, creationdate, creationby) 
                                            VALUES ('%s', '%s', '%s', '%s', NOW(), '%s')",
                                           $configValues['CONFIG_DB_TBL_DALOUSERINFO'],
                                           $dbSocket->escapeSimple($username),
                                           $dbSocket->escapeSimple($firstname),
                                           $dbSocket->escapeSimple($lastname),
                                           $dbSocket->escapeSimple($email),
                                           $dbSocket->escapeSimple($operator));
                    $res = $dbSocket->query($sql_insert_ui);
                    if (DB::isError($res)) {
                        throw new Exception("Failed to create user info: " . $res->getMessage());
                    }
                    
                    // Get the user ID
                    $sql_get_id = sprintf("SELECT id FROM %s WHERE username = '%s'",
                                         $configValues['CONFIG_DB_TBL_DALOUSERINFO'],
                                         $dbSocket->escapeSimple($username));
                    $res = $dbSocket->query($sql_get_id);
                    $user_id = null;
                    if ($res && $row = $res->fetchRow()) {
                        $user_id = intval($row[0]);
                    }
                    
                    if (!$user_id) {
                        throw new Exception("Failed to get user ID");
                    }
                    
                    // Create billing info
                    $sql_insert_ub = sprintf("INSERT INTO %s (username, money_balance, total_invoices_amount) 
                                            VALUES ('%s', %.2f, 0)",
                                           $configValues['CONFIG_DB_TBL_DALOUSERBILLINFO'],
                                           $dbSocket->escapeSimple($username),
                                           $initial_balance);
                    $res = $dbSocket->query($sql_insert_ub);
                    if (DB::isError($res)) {
                        throw new Exception("Failed to create billing info: " . $res->getMessage());
                    }
                    
                    // Add user to agent
                    $sql_insert_ua = sprintf("INSERT INTO user_agent (user_id, agent_id) VALUES (%d, %d)",
                                            intval($user_id),
                                            intval($current_agent_id));
                    $res = $dbSocket->query($sql_insert_ua);
                    if (DB::isError($res)) {
                        throw new Exception("Failed to assign user to agent: " . $res->getMessage());
                    }
                    
                    // Add RADIUS password
                    $sql_insert_radcheck = sprintf("INSERT INTO radcheck (username, attribute, op, value) 
                                                  VALUES ('%s', 'User-Password', ':=', '%s')",
                                                 $dbSocket->escapeSimple($username),
                                                 $dbSocket->escapeSimple($password));
                    $res = $dbSocket->query($sql_insert_radcheck);
                    if (DB::isError($res)) {
                        throw new Exception("Failed to set password: " . $res->getMessage());
                    }
                    
                    $successMsg = sprintf(
                        "<strong>User Created Successfully!</strong><br><br>" .
                        "Username: <strong>%s</strong><br>" .
                        "Initial Balance: <strong>$%.2f</strong><br><br>" .
                        '<a href="list.php" title="View Users">Back to Users</a>',
                        htmlspecialchars($username),
                        $initial_balance
                    );
                    
                } catch (Exception $e) {
                    $failureMsg = "Error creating user: " . htmlspecialchars($e->getMessage());
                }
            }
            
        } else {
            $failureMsg = "CSRF token error";
        }
    }

    include($app_base . 'common/includes/db_close.php');

    $title = "Add New User";
    $help = "Create a new user account for billing.";
    
    print_html_prologue($title, $langCode);
    
    print_title_and_help($title, $help);

    include_once($app_base . 'operators/include/management/actionMessages.php');

    if (!isset($successMsg)) {
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            
            <div class="card">
                <div class="card-header">
                    <h3>Add New User</h3>
                    <p class="text-muted">Create a new user account in the system.</p>
                </div>
                
                <div class="card-body">
                    
                    <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                        
                        <input type="hidden" name="csrf_token" value="<?php echo dalo_csrf_token(); ?>">
                        
                        <div class="form-group">
                            <label for="username">Username: <span class="text-danger">*</span></label>
                            <input type="text" name="username" id="username" class="form-control" 
                                   required placeholder="Enter username">
                            <small class="form-text text-muted">Alphanumeric characters only</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password: <span class="text-danger">*</span></label>
                            <input type="password" name="password" id="password" class="form-control" 
                                   required placeholder="Enter password (min 6 characters)">
                            <small class="form-text text-muted">Minimum 6 characters</small>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="firstname">First Name:</label>
                                <input type="text" name="firstname" id="firstname" class="form-control" 
                                       placeholder="Enter first name">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="lastname">Last Name:</label>
                                <input type="text" name="lastname" id="lastname" class="form-control" 
                                       placeholder="Enter last name">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" name="email" id="email" class="form-control" 
                                   placeholder="Enter email address">
                        </div>
                        
                        <div class="form-group">
                            <label for="initial_balance">Initial Balance ($): <span class="text-danger">*</span></label>
                            <input type="number" name="initial_balance" id="initial_balance" class="form-control" 
                                   step="0.01" min="0" value="0" required placeholder="0.00">
                            <small class="form-text text-muted">Starting prepaid balance for this user</small>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-success btn-lg">
                                ➕ Create User
                            </button>
                            <a href="list.php" class="btn btn-secondary">Cancel</a>
                        </div>
                        
                    </form>
                    
                </div>
            </div>
            
        </div>
    </div>
</div>

<?php
    }
    
    include($app_base . 'common/includes/layout_footer.php');
?>
