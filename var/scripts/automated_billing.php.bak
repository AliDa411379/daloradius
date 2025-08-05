<?php
/**
 * daloRADIUS Automated Billing Script - Dynamic Billing Cycle Version
 */

// ================= CONFIGURATION =================
define('LOCK_FILE', '/var/www/daloradius/var/scripts/billing.lock');
define('LOG_FILE', '/var/www/daloradius/var/logs/billing.log');

// Database credentials
define('DB_HOST', '172.30.16.200');
define('DB_USER', 'bassel');
define('DB_PASS', 'bassel_password');
define('DB_NAME', 'radius');

// Initialize logging
function log_message($message, $level = 'INFO') {
    file_put_contents(LOG_FILE, date('[Y-m-d H:i:s]')." [$level] $message\n", FILE_APPEND);
}

// ================= MAIN SCRIPT =================
try {
    // Lock check
    if (file_exists(LOCK_FILE)) {
        log_message("Script is already running", 'WARNING');
        exit(0);
    }
    file_put_contents(LOCK_FILE, getmypid());
    
    log_message("Starting billing process");
    
    // Database connection
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        throw new Exception("DB connection failed: " . $db->connect_error);
    }
    
    log_message("Connected to database");

    // ================= BILLING LOGIC =================
    // 1. Get active recurring plans
    $plans_query = "SELECT id, planName, planCost, planType, planRecurringPeriod 
                   FROM billing_plans 
                   WHERE planActive = 'yes' AND planRecurring = 'yes'";
    $plans = $db->query($plans_query);
    
    if (!$plans) {
        throw new Exception("Plan query failed: " . $db->error);
    }
    
    if ($plans->num_rows > 0) {
        while ($plan = $plans->fetch_assoc()) {
            log_message("Processing plan: {$plan['planName']} (ID: {$plan['id']}, Cycle: {$plan['planRecurringPeriod']})");
            
            // 2. Get users with due billing for this plan
            $users_query = "SELECT username FROM userbillinfo 
                           WHERE planName = '{$db->real_escape_string($plan['planName'])}' 
                           AND nextbill <= CURDATE()";
            $users = $db->query($users_query);
            
            if (!$users) {
                log_message("User query failed: " . $db->error, 'ERROR');
                continue;
            }
            
            if ($users->num_rows > 0) {
                while ($user = $users->fetch_assoc()) {
                    log_message("- User: {$user['username']} (Billing due)");
                    
                    // 3. Calculate billing amount
                    $amount = calculate_billing_amount($db, $user['username'], $plan);
                    
                    // 4. Record billing
                    $insert = "INSERT INTO billing_history 
                              (username, planId, billAmount, billAction, creationdate) 
                              VALUES (
                                  '{$db->real_escape_string($user['username'])}',
                                  {$plan['id']},
                                  '{$amount}',
                                  'Automated Billing',
                                  NOW()
                              )";
                    
                    if ($db->query($insert)) {
                        log_message("  - Billed {$amount} for user {$user['username']}");
                        
                        // Update next billing date based on plan's recurring period
                        $interval = get_billing_interval($plan['planRecurringPeriod']);
                        $update = "UPDATE userbillinfo SET 
                                  lastbill = CURDATE(), 
                                  nextbill = DATE_ADD(CURDATE(), INTERVAL {$interval})
                                  WHERE username = '{$db->real_escape_string($user['username'])}'";
                        
                        if (!$db->query($update)) {
                            log_message("  - Failed to update billing dates: " . $db->error, 'ERROR');
                        }
                    } else {
                        log_message("  - Failed to bill user: " . $db->error, 'ERROR');
                    }
                }
            } else {
                log_message("- No users with due billing for this plan");
            }
        }
    } else {
        log_message("No active recurring billing plans found");
    }
    
    // ================= CLEANUP =================
    $db->close();
    log_message("Billing process completed");
    
} catch (Exception $e) {
    log_message($e->getMessage(), 'ERROR');
} finally {
    if (file_exists(LOCK_FILE)) {
        unlink(LOCK_FILE);
    }
}

/**
 * Calculate billing amount based on plan type
 */
function calculate_billing_amount($db, $username, $plan) {
    // Default to plan cost if no usage calculation needed
    $amount = floatval($plan['planCost']);
    
    // For time-based plans, calculate usage from radacct
    if (strpos($plan['planType'], 'Time') !== false) {
        $usage_query = "SELECT SUM(acctsessiontime) as total_seconds 
                       FROM radacct 
                       WHERE username = '{$db->real_escape_string($username)}'
                       AND acctstarttime > DATE_SUB(NOW(), INTERVAL 1 {$plan['planRecurringPeriod']})";
        
        $result = $db->query($usage_query);
        if ($result && $row = $result->fetch_assoc()) {
            $minutes = $row['total_seconds'] / 60;
            $amount = $minutes * (floatval($plan['planCost']) / 60);
        }
    }
    
    // For traffic-based plans
    if (strpos($plan['planType'], 'Traffic') !== false) {
        $usage_query = "SELECT SUM(acctinputoctets + acctoutputoctets) as total_bytes 
                       FROM radacct 
                       WHERE username = '{$db->real_escape_string($username)}'
                       AND acctstarttime > DATE_SUB(NOW(), INTERVAL 1 {$plan['planRecurringPeriod']})";
        
        $result = $db->query($usage_query);
        if ($result && $row = $result->fetch_assoc()) {
            $gb = $row['total_bytes'] / (1024 * 1024 * 1024);
            $amount = $gb * floatval($plan['planCost']);
        }
    }
    
    return round($amount, 2);
}

/**
 * Get SQL interval for billing cycle
 */
function get_billing_interval($recurringPeriod) {
    switch (strtolower($recurringPeriod)) {
        case 'daily':   return '1 DAY';
        case 'weekly':  return '1 WEEK';
        case 'monthly': return '1 MONTH';
        case 'yearly':  return '1 YEAR';
        default:        return '1 MONTH'; // Default fallback
    }
}
?>
