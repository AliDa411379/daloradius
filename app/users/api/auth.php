<?php
/**
 * API Authentication Helper
 * 
 * Include this file in all protected API endpoints
 * Handles authentication, CORS, rate limiting, and logging
 * 
 * Usage: require_once('auth.php');
 * 
 * @package DaloRADIUS
 * @subpackage API
 */

require_once('config.php');

// ================== CORS HANDLING ==================

if (API_CORS_ENABLED) {
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    
    if (in_array($origin, API_CORS_ALLOWED_ORIGINS) || in_array('*', API_CORS_ALLOWED_ORIGINS)) {
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, X-API-Key");
        header("Access-Control-Max-Age: 3600");
    }
    
    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// ================== GET CLIENT IP ==================

function getClientIp() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return trim($ip);
}

$clientIp = getClientIp();

// ================== IP WHITELIST CHECK ==================

if (!isIpAllowed($clientIp)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Access denied: IP not whitelisted',
        'error_code' => 'IP_NOT_ALLOWED'
    ]);
    exit;
}

// ================== API KEY AUTHENTICATION ==================

$currentEndpoint = basename($_SERVER['PHP_SELF']);

// Check if endpoint requires authentication
if (API_AUTH_ENABLED && in_array($currentEndpoint, API_PROTECTED_ENDPOINTS)) {
    
    // Get API key from header or query parameter
    $apiKey = null;
    
    if (isset($_SERVER['HTTP_X_API_KEY'])) {
        $apiKey = $_SERVER['HTTP_X_API_KEY'];
    } elseif (isset($_GET['api_key'])) {
        $apiKey = $_GET['api_key'];
    } elseif (isset($_POST['api_key'])) {
        $apiKey = $_POST['api_key'];
    }
    
    if (!$apiKey) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'API key required',
            'error_code' => 'API_KEY_MISSING'
        ]);
        logApiError($currentEndpoint, 'Missing API key', $clientIp);
        exit;
    }
    
    if (!validateApiKey($apiKey)) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid API key',
            'error_code' => 'INVALID_API_KEY'
        ]);
        logApiError($currentEndpoint, 'Invalid API key', $clientIp);
        exit;
    }
    
    // Log successful authentication
    logApiRequest($currentEndpoint, $_SERVER['REQUEST_METHOD'], $clientIp, $apiKey);
}

// ================== RATE LIMITING ==================

if (API_RATE_LIMIT_ENABLED) {
    $rateLimitFile = __DIR__ . '/../../../var/cache/rate_limit_' . md5($clientIp) . '.txt';
    $rateLimitDir = dirname($rateLimitFile);
    
    if (!is_dir($rateLimitDir)) {
        @mkdir($rateLimitDir, 0755, true);
    }
    
    $currentMinute = floor(time() / 60);
    $requests = [];
    
    if (file_exists($rateLimitFile)) {
        $data = json_decode(file_get_contents($rateLimitFile), true);
        if ($data && isset($data['minute']) && $data['minute'] == $currentMinute) {
            $requests = $data['requests'];
        }
    }
    
    if (count($requests) >= API_RATE_LIMIT_PER_MINUTE) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Rate limit exceeded. Try again later.',
            'error_code' => 'RATE_LIMIT_EXCEEDED',
            'retry_after' => 60
        ]);
        exit;
    }
    
    $requests[] = time();
    @file_put_contents($rateLimitFile, json_encode([
        'minute' => $currentMinute,
        'requests' => $requests
    ]));
}

// ================== HELPER FUNCTIONS FOR APIs ==================

/**
 * Send JSON Error Response
 */
function apiSendError($message, $code = 400, $errorCode = null) {
    http_response_code($code);
    
    $response = [
        'success' => false,
        'error' => $message
    ];
    
    if ($errorCode) {
        $response['error_code'] = $errorCode;
    } else {
        $response['error_code'] = strtoupper(str_replace(' ', '_', $message));
    }
    
    if (API_DEBUG_MODE && isset($GLOBALS['debug_info'])) {
        $response['debug'] = $GLOBALS['debug_info'];
    }
    
    $jsonOptions = API_JSON_PRETTY_PRINT ? JSON_PRETTY_PRINT : 0;
    echo json_encode($response, $jsonOptions);
    
    logApiError(basename($_SERVER['PHP_SELF']), $message, "Code: $code");
    exit;
}

/**
 * Send JSON Success Response
 */
function apiSendSuccess($data) {
    $response = array_merge(['success' => true], $data);
    
    if (API_DEBUG_MODE && isset($GLOBALS['debug_info'])) {
        $response['debug'] = $GLOBALS['debug_info'];
    }
    
    $jsonOptions = API_JSON_PRETTY_PRINT ? JSON_PRETTY_PRINT : 0;
    echo json_encode($response, $jsonOptions);
    exit;
}

// ================== SET RESPONSE HEADERS ==================

header('Content-Type: application/json; charset=utf-8');

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Authentication passed - API can proceed
