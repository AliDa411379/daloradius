<?php
/**
 * Mikrotik Integration Functions Library
 * 
 * This file contains reusable functions that can be included in existing
 * daloRADIUS scripts to add Mikrotik attributes support.
 * 
 * Include this file in your existing scripts:
 * require_once('/var/www/daloradius/var/scripts/mikrotik_integration_functions.php');
 */

// ================= CONFIGURATION =================
if (!defined('MIKROTIK_LOG_FILE')) {
    define('MIKROTIK_LOG_FILE', '/var/www/daloradius/var/logs/mikrotik_integration.log');
}

// Mikrotik constants
if (!defined('BYTES_PER_GIGAWORD')) {
    define('BYTES_PER_GIGAWORD', 4294967296); // 4 GB in bytes
    define('BYTES_PER_MB', 1048576); // 1 MB in bytes
    define('SECONDS_PER_MINUTE', 60);
}

// ================= LOGGING FUNCTION =================
function mikrotik_log($msg, $level = 'INFO') {
    $log_entry = date('[Y-m-d H:i:s]') . " [$level] [MIKROTIK_LIB] $msg\n";
    file_put_contents(MIKROTIK_LOG_FILE, $log_entry, FILE_APPEND);
}

// ================= CONVERSION FUNCTIONS =================

/**
 * Convert traffic from MB to Mikrotik attributes
 * @param float $traffic_mb Traffic in megabytes
 * @return array ['gigawords' => int, 'bytes' => int]
 */
function mikrotik_convert_traffic($traffic_mb) {
    if ($traffic_mb <= 0) {
        return ['gigawords' => 0, 'bytes' => 0];
    }
    
    $total_bytes = $traffic_mb * BYTES_PER_MB;
    $gigawords = floor($total_bytes / BYTES_PER_GIGAWORD);
    $remaining_bytes = $total_bytes % BYTES_PER_GIGAWORD;
    
    // Ensure minimum value of 1 for bytes (except when unlimited)
    if ($remaining_bytes == 0 && $gigawords == 0 && $traffic_mb > 0) {
        $remaining_bytes = 1;
    }
    
    return [
        'gigawords' => (int)$gigawords,
        'bytes' => (int)$remaining_bytes
    ];
}

/**
 * Convert time from minutes to seconds
 * @param float $time_minutes Time in minutes
 * @return int Time in seconds
 */
function mikrotik_convert_time($time_minutes) {
    if ($time_minutes <= 0) {
        return 0;
    }
    
    $seconds = $time_minutes * SECONDS_PER_MINUTE;
    return max(1, (int)$seconds);
}

// ================= DATABASE FUNCTIONS =================

/**
 * Update or insert RADIUS attribute in radreply table
 * @param mysqli $db Database connection
 * @param string $username Username
 * @param string $attribute Attribute name
 * @param string $value Attribute value
 * @param string $op Operator (default '=')
 * @return bool Success status
 */
function mikrotik_set_radius_attribute($db, $username, $attribute, $value, $op = '=') {
    $username = $db->real_escape_string($username);
    $attribute = $db->real_escape_string($attribute);
    $value = $db->real_escape_string($value);
    $op = $db->real_escape_string($op);
    
    // Check if attribute exists
    $check_sql = "SELECT id FROM radreply WHERE username='$username' AND attribute='$attribute'";
    $result = $db->query($check_sql);
    
    if ($result && $result->num_rows > 0) {
        // Update existing attribute
        $update_sql = "UPDATE radreply SET value='$value', op='$op' WHERE username='$username' AND attribute='$attribute'";
        if (!$db->query($update_sql)) {
            mikrotik_log("Failed to update $attribute for $username: " . $db->error, 'ERROR');
            return false;
        }
        mikrotik_log("Updated $attribute=$value for $username");
    } else {
        // Insert new attribute
        $insert_sql = "INSERT INTO radreply (username, attribute, op, value) VALUES ('$username', '$attribute', '$op', '$value')";
        if (!$db->query($insert_sql)) {
            mikrotik_log("Failed to insert $attribute for $username: " . $db->error, 'ERROR');
            return false;
        }
        mikrotik_log("Inserted $attribute=$value for $username");
    }
    
    return true;
}

/**
 * Remove RADIUS attribute from radreply table
 * @param mysqli $db Database connection
 * @param string $username Username
 * @param string $attribute Attribute name
 * @return bool Success status
 */
function mikrotik_remove_radius_attribute($db, $username, $attribute) {
    $username = $db->real_escape_string($username);
    $attribute = $db->real_escape_string($attribute);
    
    $delete_sql = "DELETE FROM radreply WHERE username='$username' AND attribute='$attribute'";
    if ($db->query($delete_sql)) {
        mikrotik_log("Removed $attribute for $username");
        return true;
    } else {
        mikrotik_log("Failed to remove $attribute for $username: " . $db->error, 'ERROR');
        return false;
    }
}

/**
 * Get user's billing plan information
 * @param mysqli $db Database connection
 * @param string $username Username
 * @return array|false Plan information or false on error
 */
function mikrotik_get_user_plan($db, $username) {
    $username = $db->real_escape_string($username);
    
    $query = "
        SELECT 
            u.username,
            u.planName,
            u.timebank_balance,
            u.traffic_balance,
            p.planType,
            p.planTimeBank,
            p.planTrafficTotal,
            p.planActive
        FROM userbillinfo u
        LEFT JOIN billing_plans p ON u.planName = p.planName
        WHERE u.username = '$username'
    ";
    
    $result = $db->query($query);
    if (!$result || $result->num_rows == 0) {
        mikrotik_log("User $username not found in billing info", 'WARNING');
        return false;
    }
    
    return $result->fetch_assoc();
}

// ================= HIGH-LEVEL FUNCTIONS =================

/**
 * Set up initial Mikrotik attributes for a new user based on their plan
 * @param mysqli $db Database connection
 * @param string $username Username
 * @param string $plan_name Plan name (optional, will be retrieved if not provided)
 * @return bool Success status
 */
function mikrotik_setup_new_user($db, $username, $plan_name = null) {
    mikrotik_log("Setting up new user: $username");
    
    // Get user plan information
    $user_plan = mikrotik_get_user_plan($db, $username);
    if (!$user_plan) {
        return false;
    }
    
    // Use provided plan name or get from user info
    if ($plan_name) {
        $user_plan['planName'] = $plan_name;
    }
    
    // Get initial balances from plan
    $initial_time = floatval($user_plan['planTimeBank'] ?: 0);
    $initial_traffic = floatval($user_plan['planTrafficTotal'] ?: 0);
    
    // Update initial balances in userbillinfo if they don't exist
    $update_fields = [];
    if (stripos($user_plan['planType'], 'Time') !== false && $initial_time > 0) {
        if (empty($user_plan['timebank_balance'])) {
            $update_fields[] = "timebank_balance = $initial_time";
        }
    }
    
    if (stripos($user_plan['planType'], 'Traffic') !== false && $initial_traffic > 0) {
        if (empty($user_plan['traffic_balance'])) {
            $update_fields[] = "traffic_balance = $initial_traffic";
        }
    }
    
    if (!empty($update_fields)) {
        $update_sql = "UPDATE userbillinfo SET " . implode(', ', $update_fields) . 
                     ", updatedate = NOW() WHERE username = '" . $db->real_escape_string($username) . "'";
        $db->query($update_sql);
    }
    
    // Set up Mikrotik attributes
    return mikrotik_sync_user_attributes($db, $username);
}

/**
 * Synchronize Mikrotik attributes for a user based on current balances
 * @param mysqli $db Database connection
 * @param string $username Username
 * @return bool Success status
 */
function mikrotik_sync_user_attributes($db, $username) {
    $user_plan = mikrotik_get_user_plan($db, $username);
    if (!$user_plan) {
        return false;
    }
    
    // Get current balances (use plan defaults if balance is empty)
    $traffic_balance = !empty($user_plan['traffic_balance']) ? 
                      floatval($user_plan['traffic_balance']) : 
                      floatval($user_plan['planTrafficTotal'] ?: 0);
    
    $time_balance = !empty($user_plan['timebank_balance']) ? 
                   floatval($user_plan['timebank_balance']) : 
                   floatval($user_plan['planTimeBank'] ?: 0);
    
    mikrotik_log("Syncing attributes for $username - Traffic: {$traffic_balance}MB, Time: {$time_balance}min");
    
    $success = true;
    
    // Set traffic attributes
    if (stripos($user_plan['planType'], 'Traffic') !== false || $traffic_balance > 0) {
        $traffic_attrs = mikrotik_convert_traffic($traffic_balance);
        $success &= mikrotik_set_radius_attribute($db, $username, 'Mikrotik-Total-Limit-Gigawords', $traffic_attrs['gigawords']);
        $success &= mikrotik_set_radius_attribute($db, $username, 'Mikrotik-Total-Limit', $traffic_attrs['bytes']);
    } else {
        $success &= mikrotik_set_radius_attribute($db, $username, 'Mikrotik-Total-Limit-Gigawords', '0');
        $success &= mikrotik_set_radius_attribute($db, $username, 'Mikrotik-Total-Limit', '0');
    }
    
    // Set time attributes
    if (stripos($user_plan['planType'], 'Time') !== false || $time_balance > 0) {
        $session_timeout = mikrotik_convert_time($time_balance);
        $success &= mikrotik_set_radius_attribute($db, $username, 'Session-Timeout', $session_timeout);
    } else {
        $success &= mikrotik_set_radius_attribute($db, $username, 'Session-Timeout', '0');
    }
    
    if ($success) {
        mikrotik_log("Successfully synced attributes for $username");
    } else {
        mikrotik_log("Some errors occurred while syncing attributes for $username", 'WARNING');
    }
    
    return $success;
}

/**
 * Update user balances based on actual usage and sync attributes
 * @param mysqli $db Database connection
 * @param string $username Username
 * @param string $since_date Optional date to calculate usage from
 * @return bool Success status
 */
function mikrotik_update_balances_from_usage($db, $username, $since_date = null) {
    $user_plan = mikrotik_get_user_plan($db, $username);
    if (!$user_plan) {
        return false;
    }
    
    // Calculate usage since specified date or last billing
    if (!$since_date) {
        $since_date = ($user_plan['lastbill'] && $user_plan['lastbill'] != '0000-00-00') ? 
                     $user_plan['lastbill'] : date('Y-m-01');
    }
    
    $username_escaped = $db->real_escape_string($username);
    
    // Try to use billing table first, fall back to radacct if not available
    $table_check = $db->query("SHOW TABLES LIKE 'radacct_billing'");
    $use_billing_table = ($table_check && $table_check->num_rows > 0);
    
    if ($use_billing_table) {
        $usage_query = "
            SELECT 
                SUM(COALESCE(session_minutes * 60, acctsessiontime, 0)) as total_seconds,
                SUM(COALESCE(traffic_mb * 1048576, acctinputoctets + acctoutputoctets, 0)) as total_bytes
            FROM radacct_billing 
            WHERE username='$username_escaped' 
            AND acctstarttime >= '$since_date'
            AND acctstoptime IS NOT NULL
        ";
        mikrotik_log("Using radacct_billing table for usage calculation");
    } else {
        $usage_query = "
            SELECT 
                SUM(acctsessiontime) as total_seconds,
                SUM(acctinputoctets + acctoutputoctets) as total_bytes
            FROM radacct 
            WHERE username='$username_escaped' 
            AND acctstarttime >= '$since_date'
        ";
        mikrotik_log("Using radacct table for usage calculation (billing table not available)");
    }
    
    $result = $db->query($usage_query);
    if (!$result) {
        mikrotik_log("Failed to get usage for $username: " . $db->error, 'ERROR');
        return false;
    }
    
    $usage = $result->fetch_assoc();
    $used_minutes = round(($usage['total_seconds'] ?: 0) / 60, 2);
    $used_mb = round(($usage['total_bytes'] ?: 0) / BYTES_PER_MB, 2);
    
    mikrotik_log("Usage for $username since $since_date: {$used_minutes} min, {$used_mb} MB");
    
    // Calculate new balances
    $update_fields = [];
    
    if (stripos($user_plan['planType'], 'Time') !== false) {
        $current_time = !empty($user_plan['timebank_balance']) ? 
                       floatval($user_plan['timebank_balance']) : 
                       floatval($user_plan['planTimeBank'] ?: 0);
        $new_time_balance = max(0, $current_time - $used_minutes);
        $update_fields[] = "timebank_balance = $new_time_balance";
    }
    
    if (stripos($user_plan['planType'], 'Traffic') !== false) {
        $current_traffic = !empty($user_plan['traffic_balance']) ? 
                          floatval($user_plan['traffic_balance']) : 
                          floatval($user_plan['planTrafficTotal'] ?: 0);
        $new_traffic_balance = max(0, $current_traffic - $used_mb);
        $update_fields[] = "traffic_balance = $new_traffic_balance";
    }
    
    // Update balances in database
    if (!empty($update_fields)) {
        $update_sql = "UPDATE userbillinfo SET " . implode(', ', $update_fields) . 
                     ", updatedate = NOW() WHERE username = '$username_escaped'";
        
        if (!$db->query($update_sql)) {
            mikrotik_log("Failed to update balances for $username: " . $db->error, 'ERROR');
            return false;
        }
        
        mikrotik_log("Updated balances for $username");
    }
    
    // Sync attributes with new balances
    return mikrotik_sync_user_attributes($db, $username);
}

/**
 * Remove all Mikrotik attributes for a user
 * @param mysqli $db Database connection
 * @param string $username Username
 * @return bool Success status
 */
function mikrotik_remove_user_attributes($db, $username) {
    $success = true;
    $success &= mikrotik_remove_radius_attribute($db, $username, 'Mikrotik-Total-Limit');
    $success &= mikrotik_remove_radius_attribute($db, $username, 'Mikrotik-Total-Limit-Gigawords');
    $success &= mikrotik_remove_radius_attribute($db, $username, 'Session-Timeout');
    
    if ($success) {
        mikrotik_log("Removed all Mikrotik attributes for $username");
    }
    
    return $success;
}

// ================= UTILITY FUNCTIONS =================

/**
 * Format bytes in human readable format
 * @param int $bytes Number of bytes
 * @return string Formatted string
 */
function mikrotik_format_bytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Get user's current Mikrotik attributes from radreply
 * @param mysqli $db Database connection
 * @param string $username Username
 * @return array Current attributes
 */
function mikrotik_get_user_attributes($db, $username) {
    $username = $db->real_escape_string($username);
    
    $query = "
        SELECT attribute, value 
        FROM radreply 
        WHERE username = '$username' 
        AND attribute IN ('Mikrotik-Total-Limit', 'Mikrotik-Total-Limit-Gigawords', 'Session-Timeout')
    ";
    
    $result = $db->query($query);
    $attributes = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $attributes[$row['attribute']] = $row['value'];
        }
    }
    
    return $attributes;
}

mikrotik_log("Mikrotik integration functions loaded");
?>