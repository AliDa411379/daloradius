<?php
/**
 * API Configuration
 * 
 * Security settings and API key management
 * IMPORTANT: Keep this file secure and outside web root in production
 * 
 * @package DaloRADIUS
 * @subpackage API
 */

// ================== API KEYS ==================
// Generate strong API keys: openssl rand -hex 32

define('API_KEYS', [
    // Agent API Keys
    'agent_api_production' => 'YOUR_AGENT_API_KEY_HERE',  // Replace with actual key
    'agent_api_test' => 'test_key_12345',  // For testing only
    
    // Admin API Keys
    'admin_api_production' => 'YOUR_ADMIN_API_KEY_HERE',  // Replace with actual key
    
    // External ERP API Keys
    'erp_api_production' => 'YOUR_ERP_API_KEY_HERE',  // Replace with actual key
]);

// ================== SECURITY SETTINGS ==================

// Enable/Disable API Key Authentication
define('API_AUTH_ENABLED', true);  // Set to true in production

// Rate Limiting (requests per minute per IP)
define('API_RATE_LIMIT_ENABLED', true);
define('API_RATE_LIMIT_PER_MINUTE', 60);

// Allowed IP Addresses (empty array = allow all)
define('API_ALLOWED_IPS', [
    // Empty = Allow all IPs
    // Add specific IPs when ready for production:
    // '192.168.1.100',
    // '10.0.0.0/8',  // CIDR notation
]);

// CORS Settings
define('API_CORS_ENABLED', true);
define('API_CORS_ALLOWED_ORIGINS', [
    '*',  // Allow all origins (for development - restrict in production!)
    // Production: Replace '*' with specific domains:
    // 'https://your-frontend-domain.com',
    // 'https://agent-portal.com',
]);

// Request Logging
define('API_LOG_ENABLED', true);
define('API_LOG_FILE', __DIR__ . '/../../../var/logs/api_access.log');

// Error Logging
define('API_ERROR_LOG_FILE', __DIR__ . '/../../../var/logs/api_errors.log');

// ================== API ENDPOINTS CONFIGURATION ==================

// Endpoints that require authentication
define('API_PROTECTED_ENDPOINTS', [
    'agent_topup_balance.php',
    'agent_purchase_bundle.php',
    'agent_get_active_users.php',
    'agent_payment_history.php',
    'payment_refund.php',
    'report_bundle_purchases.php',
    'report_payments.php',
]);

// Endpoints accessible without authentication (for testing)
define('API_PUBLIC_ENDPOINTS', [
    'user_balance.php',
    'user_comprehensive_info.php',
]);

// ================== DATABASE CONFIGURATION ==================
// These are loaded from main config, but can be overridden here if needed

// define('API_DB_HOST', '172.30.16.200');
// define('API_DB_USER', 'bassel');
// define('API_DB_PASS', 'bassel_password');
// define('API_DB_NAME', 'radius');

// ================== RESPONSE CONFIGURATION ==================

// Pretty print JSON responses
define('API_JSON_PRETTY_PRINT', false);  // Set to false in production for smaller responses

// Include debug info in responses
define('API_DEBUG_MODE', false);  // Set to false in production

// ================== VALIDATION SETTINGS ==================

// Maximum allowed amounts
define('API_MAX_TOPUP_AMOUNT', 300000.00);
define('API_MAX_REFUND_AMOUNT', 300000.00);

// Minimum allowed amounts
define('API_MIN_TOPUP_AMOUNT', 1.00);
define('API_MIN_REFUND_AMOUNT', 0.01);

// ================== HELPER FUNCTIONS ==================

/**
 * Validate API Key
 */
function validateApiKey($providedKey) {
    if (!API_AUTH_ENABLED) {
        return true;
    }
    
    return in_array($providedKey, API_KEYS);
}

/**
 * Check if IP is allowed
 */
function isIpAllowed($ip) {
    if (empty(API_ALLOWED_IPS)) {
        return true;
    }
    
    foreach (API_ALLOWED_IPS as $allowedIp) {
        // Check for CIDR notation
        if (strpos($allowedIp, '/') !== false) {
            if (ipInCidr($ip, $allowedIp)) {
                return true;
            }
        } else {
            if ($ip === $allowedIp) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Check if IP is in CIDR range
 */
function ipInCidr($ip, $cidr) {
    list($subnet, $mask) = explode('/', $cidr);
    $ip_long = ip2long($ip);
    $subnet_long = ip2long($subnet);
    $mask_long = -1 << (32 - $mask);
    
    return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
}

/**
 * Log API Request
 */
function logApiRequest($endpoint, $method, $ip, $apiKey = null) {
    if (!API_LOG_ENABLED) {
        return;
    }
    
    $logDir = dirname(API_LOG_FILE);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = sprintf(
        "[%s] %s %s from %s (Key: %s)\n",
        date('Y-m-d H:i:s'),
        $method,
        $endpoint,
        $ip,
        $apiKey ? substr($apiKey, 0, 8) . '...' : 'None'
    );
    
    @file_put_contents(API_LOG_FILE, $logEntry, FILE_APPEND);
}

/**
 * Log API Error
 */
function logApiError($endpoint, $error, $details = '') {
    $logDir = dirname(API_ERROR_LOG_FILE);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = sprintf(
        "[%s] ERROR in %s: %s %s\n",
        date('Y-m-d H:i:s'),
        $endpoint,
        $error,
        $details ? "($details)" : ''
    );
    
    @file_put_contents(API_ERROR_LOG_FILE, $logEntry, FILE_APPEND);
}
