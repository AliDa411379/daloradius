<?php
/**
 * API: Plan Lookups
 * 
 * Retrieves available billing plans and bundles
 * Supports filtering by subscription type and bundle status
 * 
 * @package DaloRADIUS
 * @subpackage API
 */

// Include authentication and config
require_once('auth.php');

// Include required files
require_once('../../common/includes/config_read.php');
require_once('../../common/includes/db_open.php');

// Get request method and data
$method = $_SERVER['REQUEST_METHOD'];

// Get input data
$input = [];
if ($method === 'GET') {
    $input = $_GET;
} elseif ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true);
    $input = $jsonInput ?? $_POST;
}

// Parameters
$subscription_type = $input['subscription_type'] ?? 'all';  // 'monthly', 'prepaid', 'all'
$bundle_only = isset($input['bundle_only']) ? filter_var($input['bundle_only'], FILTER_VALIDATE_BOOLEAN) : false;
$include_details = isset($input['include_details']) ? filter_var($input['include_details'], FILTER_VALIDATE_BOOLEAN) : true;

try {
    // Database connection
    $mysqli = new mysqli(
        $configValues['CONFIG_DB_HOST'],
        $configValues['CONFIG_DB_USER'],
        $configValues['CONFIG_DB_PASS'],
        $configValues['CONFIG_DB_NAME'],
        $configValues['CONFIG_DB_PORT']
    );
    
    if ($mysqli->connect_error) {
        apiSendError('Database connection failed', 500);
    }
    
    $mysqli->set_charset("utf8mb4");
    
    // Build query
    $sql = "SELECT bp.*
            FROM " . $configValues['CONFIG_DB_TBL_DALOBILLINGPLANS'] . " bp
            WHERE bp.planActive = 'yes'";
    
    // Filter by subscription type
    if ($subscription_type !== 'all') {
        if ($subscription_type === 'monthly') {
            $sql .= " AND bp.subscription_type_id = 1";
        } elseif ($subscription_type === 'prepaid') {
            $sql .= " AND bp.subscription_type_id = 2";
        }
    }
    
    // Filter bundle only
    if ($bundle_only) {
        $sql .= " AND bp.is_bundle = 1";
    }
    
    $sql .= " ORDER BY bp.planCost ASC, bp.planName ASC";
    
    $result = $mysqli->query($sql);
    
    if (!$result) {
        apiSendError('Query failed: ' . $mysqli->error, 500);
    }
    
    $plans = [];
    
    while ($row = $result->fetch_assoc()) {
        // Map subscription_type_id to name
        $subscription_type_name = 'monthly';
        if (isset($row['subscription_type_id'])) {
            $subscription_type_name = ($row['subscription_type_id'] == 2) ? 'prepaid' : 'monthly';
        }
        
        $plan = [
            'plan_id' => (int)$row['id'],
            'plan_name' => $row['planName'],
            'plan_type' => $row['planType'],
            'cost' => (float)$row['planCost'],
            'currency' => $row['planCurrency'] ?? 'SYP',
            'is_bundle' => isset($row['is_bundle']) ? (bool)$row['is_bundle'] : false,
            'subscription_type' => $subscription_type_name,
        ];
        
        // Bundle-specific details
        if ($plan['is_bundle']) {
            $validityDays = isset($row['bundle_validity_days']) ? (int)$row['bundle_validity_days'] : 30;
            $validityHours = isset($row['bundle_validity_hours']) ? (int)$row['bundle_validity_hours'] : 0;
            
            $plan['bundle_details'] = [
                'validity_days' => $validityDays,
                'validity_hours' => $validityHours,
                'auto_renew' => isset($row['auto_renew']) ? (bool)$row['auto_renew'] : false
            ];
            
            // Calculate total validity in hours for display
            $totalHours = ($validityDays * 24) + $validityHours;
            $plan['bundle_details']['total_validity_hours'] = $totalHours;
            
            // Human-readable validity
            if ($validityDays > 0) {
                $plan['bundle_details']['validity_display'] = $validityDays . ' days';
                if ($validityHours > 0) {
                    $plan['bundle_details']['validity_display'] .= ' ' . $validityHours . ' hours';
                }
            } else {
                $plan['bundle_details']['validity_display'] = $validityHours . ' hours';
            }
        }
        
        // Include detailed plan information if requested
        if ($include_details) {
            $plan['details'] = [
                'traffic_total_mb' => isset($row['planTrafficTotal']) ? (float)$row['planTrafficTotal'] : 0,
                'traffic_upload_mb' => isset($row['planTrafficUp']) ? (float)$row['planTrafficUp'] : 0,
                'traffic_download_mb' => isset($row['planTrafficDown']) ? (float)$row['planTrafficDown'] : 0,
                'time_bank_minutes' => isset($row['planTimeBank']) ? (int)$row['planTimeBank'] : 0,
                'recurring' => isset($row['planRecurring']) && $row['planRecurring'] === 'Yes',
                'recurring_period' => $row['planRecurringPeriod'] ?? ''
            ];
            
            // Get profile/group assignments for this plan
            $profileSql = "SELECT profile_name 
                          FROM " . $configValues['CONFIG_DB_TBL_DALOBILLINGPLANSPROFILES'] . " 
                          WHERE plan_name = ?";
            
            $stmt = $mysqli->prepare($profileSql);
            if ($stmt) {
                $stmt->bind_param("s", $row['planName']);
                $stmt->execute();
                $profileResult = $stmt->get_result();
                
                $profiles = [];
                while ($profileRow = $profileResult->fetch_assoc()) {
                    $profiles[] = $profileRow['profile_name'];
                }
                
                $plan['details']['radius_profiles'] = $profiles;
                $stmt->close();
            }
        }
        
        $plans[] = $plan;
    }
    
    // Get summary statistics
    $totalPlans = count($plans);
    $bundlePlans = array_filter($plans, fn($p) => $p['is_bundle']);
    $monthlyPlans = array_filter($plans, fn($p) => $p['subscription_type'] === 'monthly');
    $prepaidPlans = array_filter($plans, fn($p) => $p['subscription_type'] === 'prepaid');
    
    $mysqli->close();
    
    // Success response
    apiSendSuccess([
        'total_plans' => $totalPlans,
        'summary' => [
            'total_bundles' => count($bundlePlans),
            'total_monthly' => count($monthlyPlans),
            'total_prepaid' => count($prepaidPlans)
        ],
        'filters_applied' => [
            'subscription_type' => $subscription_type,
            'bundle_only' => $bundle_only
        ],
        'plans' => $plans
    ]);
    
} catch (Exception $e) {
    apiSendError('Internal server error: ' . $e->getMessage(), 500);
}
