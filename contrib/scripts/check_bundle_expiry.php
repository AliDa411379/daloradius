<?php
/**
 * DaloRADIUS - Bundle Expiry Check Script
 * 
 * Checks for expired bundles and revokes RADIUS access
 * Business Logic: Instant block on expiry (no grace period for bundles)
 * 
 * Schedule: Run hourly
 * Crontab: 0 * * * * /usr/bin/php /path/to/check_bundle_expiry.php
 * 
 * @package DaloRADIUS
 * @version 1.0
 */

// ================== CONFIGURATION ==================
define('LOG_FILE', __DIR__ . '/../../var/logs/bundle_expiry.log');
define('LOCK_FILE', __DIR__ . '/../../var/scripts/bundle_expiry.lock');

// Database credentials
define('DB_HOST', '172.30.16.200');
define('DB_USER', 'bassel');
define('DB_PASS', 'bassel_password');
define('DB_NAME', 'radius');

// ================== INCLUDES ==================
require_once(__DIR__ . '/../app/common/library/BundleManager.php');
require_once(__DIR__ . '/../app/common/library/RadiusAccessManager.php');

// ================== FUNCTIONS ==================

function log_message($msg, $level = 'INFO') {
    $log_dir = dirname(LOG_FILE);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    file_put_contents(LOG_FILE, date('[Y-m-d H:i:s]') . " [$level] $msg\n", FILE_APPEND);
    echo date('[Y-m-d H:i:s]') . " [$level] $msg\n";
}

// ================== MAIN SCRIPT ==================

try {
    // Check for lock file
    if (file_exists(LOCK_FILE)) {
        $pid = file_get_contents(LOCK_FILE);
        log_message("Script already running with PID $pid", 'WARNING');
        exit(0);
    }
    
    file_put_contents(LOCK_FILE, getmypid());
    log_message("========================================");
    log_message("=== BUNDLE EXPIRY CHECK START ===");
    log_message("========================================");
    log_message("Date: " . date('Y-m-d H:i:s'));
    
    // Database connection
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        throw new Exception("DB Connection Failed: " . $db->connect_error);
    }
    $db->set_charset("utf8mb4");
    log_message("Connected to database");
    
    // Initialize managers
    $bundleManager = new BundleManager($db);
    $radiusManager = new RadiusAccessManager($db);
    
    // Find and expire bundles
    $result = $bundleManager->checkAndExpireBundles();
    
    $expiredCount = $result['expired_count'];
    $expiredBundles = $result['bundles'];
    
    log_message("Found $expiredCount expired bundles");
    
    $blocked = 0;
    $failed = 0;
    
    // Block users with expired bundles
    foreach ($expiredBundles as $bundle) {
        $username = $bundle['username'];
        $bundleId = $bundle['id'];
        $planName = $bundle['plan_name'];
        
        try {
            // Check if user has other active bundle or monthly subscription
            $sql = sprintf(
                "SELECT subscription_type_id, current_bundle_id, planName 
                 FROM userbillinfo 
                 WHERE username = '%s'",
                $db->real_escape_string($username)
            );
            
            $userResult = $db->query($sql);
            if (!$userResult || $userResult->num_rows === 0) {
                log_message("SKIP: $username - User not found", 'WARNING');
                continue;
            }
            
            $user = $userResult->fetch_assoc();
            
            // If user has monthly subscription (type_id = 1), don't block
            if ($user['subscription_type_id'] == 1) {
                log_message("SKIP: $username - Has monthly subscription, not blocking", 'INFO');
                continue;
            }
            
            // If user has another active bundle, don't block
            $hasOtherBundle = $bundleManager->hasActiveBundle($user['id'] ?? 0);
            if ($hasOtherBundle) {
                log_message("SKIP: $username - Has another active bundle", 'INFO');
                continue;
            }
            
            // Remove from plan groups
            $radiusManager->removeFromPlanGroups($username, $planName);
            
            // Block user (add to block_user group)
            $blockResult = $radiusManager->revokeAccess(
                $username,
                "Bundle expired: $planName (ID: $bundleId)"
            );
            
            if ($blockResult['success']) {
                log_message(sprintf(
                    "BLOCKED: $username - Bundle expired: $planName (Expiry: %s)",
                    $bundle['expiry_date']
                ), 'INFO');
                $blocked++;
            } else {
                throw new Exception($blockResult['message']);
            }
            
        } catch (Exception $e) {
            log_message("FAILED: $username - " . $e->getMessage(), 'ERROR');
            $failed++;
        }
    }
    
    $db->close();
    
    log_message("========================================");
    log_message("=== BUNDLE EXPIRY CHECK COMPLETE ===");
    log_message("========================================");
    log_message("Expired Bundles: $expiredCount");
    log_message("Users Blocked: $blocked");
    log_message("Failed: $failed");
    log_message("========================================");
    
} catch (Exception $e) {
    log_message("FATAL ERROR: " . $e->getMessage(), 'ERROR');
    exit(1);
} finally {
    // Remove lock file
    if (file_exists(LOCK_FILE)) {
        unlink(LOCK_FILE);
    }
}

exit(0);
