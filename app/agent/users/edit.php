<?php
/*
 *********************************************************************************************************
 * daloRADIUS - AGENT PORTAL - EDIT USER
 * Edit user account details
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

    $user_id = (array_key_exists('user_id', $_REQUEST) && intval(trim($_REQUEST['user_id'])) > 0)
             ? intval(trim($_REQUEST['user_id'])) : 0;

    $user_data = null;
    $username = "";
    
    if ($user_id > 0) {
        // Verify user belongs to this agent
        $sql_verify = sprintf("SELECT ub.id, ub.username, COALESCE(ub.money_balance, 0) as balance 
                              FROM %s ub
                              INNER JOIN user_agent ua ON ub.id = ua.user_id
                              WHERE ub.id = %d AND ua.agent_id = %d",
                             $configValues['CONFIG_DB_TBL_DALOUSERBILLINFO'],
                             intval($user_id),
                             intval($current_agent_id));
        $res = $dbSocket->query($sql_verify);
        if ($res && $row = $res->fetchRow()) {
            $user_data = array(
                'id' => $row[0],
                'username' => $row[1],
                'balance' => floatval($row[2])
            );
            $username = $user_data['username'];
        } else {
            $failureMsg = "User not found or does not belong to your account";
        }
    } else {
        $failureMsg = "Invalid user ID";
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_data) {
        
        if (array_key_exists('csrf_token', $_POST) && isset($_POST['csrf_token']) && dalo_check_csrf_token($_POST['csrf_token'])) {
            
            $firstname = (array_key_exists('firstname', $_POST)) ? trim($_POST['firstname']) : "";
            $lastname = (array_key_exists('lastname', $_POST)) ? trim($_POST['lastname']) : "";
            $email = (array_key_exists('email', $_POST)) ? trim($_POST['email']) : "";
            $new_password = (array_key_exists('new_password', $_POST) && !empty($_POST['new_password']))
                          ? trim($_POST['new_password']) : "";
            
            if (!empty($new_password) && strlen($new_password) < 6) {
                $failureMsg = "Password must be at least 6 characters";
            } else {
                
                try {
                    // Update userinfo
                    $sql_update = sprintf("UPDATE %s SET firstname = '%s', lastname = '%s', email = '%s', updatedate = NOW(), updateby = '%s' 
                                         WHERE username = '%s'",
                                        $configValues['CONFIG_DB_TBL_DALOUSERINFO'],
                                        $dbSocket->escapeSimple($firstname),
                                        $dbSocket->escapeSimple($lastname),
                                        $dbSocket->escapeSimple($email),
                                        $dbSocket->escapeSimple($operator),
                                        $dbSocket->escapeSimple($username));
                    $res = $dbSocket->query($sql_update);
                    if (DB::isError($res)) {
                        throw new Exception("Failed to update user info: " . $res->getMessage());
                    }
                    
                    // Update password if provided
                    if (!empty($new_password)) {
                        $sql_pwd = sprintf("UPDATE radcheck SET value = '%s' WHERE username = '%s' AND attribute = 'User-Password'",
                                         $dbSocket->escapeSimple($new_password),
                                         $dbSocket->escapeSimple($username));
                        $res = $dbSocket->query($sql_pwd);
                        if (DB::isError($res)) {
                            throw new Exception("Failed to update password: " . $res->getMessage());
                        }
                    }
                    
                    $successMsg = sprintf(
                        "<strong>User Updated Successfully!</strong><br><br>" .
                        "Username: <strong>%s</strong><br>" .
                        '<a href="list.php" title="View Users">Back to Users</a>',
                        htmlspecialchars($username)
                    );
                    
                    // Reload user data
                    $sql_reload = sprintf("SELECT firstname, lastname, email FROM %s WHERE username = '%s'",
                                        $configValues['CONFIG_DB_TBL_DALOUSERINFO'],
                                        $dbSocket->escapeSimple($username));
                    $res = $dbSocket->query($sql_reload);
                    if ($res && $row = $res->fetchRow()) {
                        $firstname = $row[0];
                        $lastname = $row[1];
                        $email = $row[2];
                    }
                    
                } catch (Exception $e) {
                    $failureMsg = "Error updating user: " . htmlspecialchars($e->getMessage());
                }
            }
            
        } else {
            $failureMsg = "CSRF token error";
        }
    }
    
    // Load user details for editing
    if ($user_data && !isset($successMsg)) {
        $sql_load = sprintf("SELECT firstname, lastname, email FROM %s WHERE username = '%s'",
                           $configValues['CONFIG_DB_TBL_DALOUSERINFO'],
                           $dbSocket->escapeSimple($username));
        $res = $dbSocket->query($sql_load);
        if ($res && $row = $res->fetchRow()) {
            $firstname = $row[0];
            $lastname = $row[1];
            $email = $row[2];
        }
    }

    include($app_base . 'common/includes/db_close.php');

    $title = "Edit User";
    $help = "Edit user account details.";
    
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
                    <h3>Edit User: <?php echo htmlspecialchars($username); ?></h3>
                    <p class="text-muted">Update user account information.</p>
                </div>
                
                <div class="card-body">
                    
                    <?php if ($user_data): ?>
                    <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                        
                        <input type="hidden" name="csrf_token" value="<?php echo dalo_csrf_token(); ?>">
                        <input type="hidden" name="user_id" value="<?php echo intval($user_id); ?>">
                        
                        <div class="form-group">
                            <label><strong>Username:</strong></label>
                            <p class="form-control-static"><?php echo htmlspecialchars($username); ?></p>
                        </div>
                        
                        <div class="form-group">
                            <label><strong>Current Balance:</strong></label>
                            <p class="form-control-static">$<?php echo number_format($user_data['balance'], 2); ?></p>
                        </div>
                        
                        <hr>
                        
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="firstname">First Name:</label>
                                <input type="text" name="firstname" id="firstname" class="form-control" 
                                       value="<?php echo htmlspecialchars($firstname ?? ''); ?>"
                                       placeholder="Enter first name">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="lastname">Last Name:</label>
                                <input type="text" name="lastname" id="lastname" class="form-control" 
                                       value="<?php echo htmlspecialchars($lastname ?? ''); ?>"
                                       placeholder="Enter last name">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" name="email" id="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($email ?? ''); ?>"
                                   placeholder="Enter email address">
                        </div>
                        
                        <hr>
                        
                        <div class="form-group">
                            <label for="new_password">Change Password (leave blank to keep current):</label>
                            <input type="password" name="new_password" id="new_password" class="form-control" 
                                   placeholder="Enter new password (min 6 characters)">
                            <small class="form-text text-muted">Only fill if you want to change the password</small>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-warning btn-lg">
                                💾 Update User
                            </button>
                            <a href="list.php" class="btn btn-secondary">Cancel</a>
                        </div>
                        
                    </form>
                    <?php else: ?>
                    <div class="alert alert-danger">
                        User not found.
                    </div>
                    <a href="list.php" class="btn btn-secondary">Back to Users</a>
                    <?php endif; ?>
                    
                </div>
            </div>
            
        </div>
    </div>
</div>

<?php
    }
    
    include($app_base . 'common/includes/layout_footer.php');
?>
