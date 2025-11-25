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
    $sql = "SELECT 
                bp.id,
                bp.planName,
                bp.planType,
                bp.planCost,
                bp.planCurrency,
                bp.is_bundle,
                bp.bundle_validity_days,
                bp.bundle_validity_hours,
                bp.auto_renew,
                bp.planTrafficTotal,
                bp.planTrafficUpload,
                bp.planTrafficDownload,
                bp.planTimeBank,
                bp.planTimeRefillCost,
                bp.planTrafficRefillCost,
                bp.planRecurring,
                bp.planRecurringPeriod,
                bp.planActive,
                st.id as subscription_type_id,
                st.name as subscription_type_name,
                st.description as subscription_type_description
            FROM " . $configValues['CONFIG_DB_TBL_DALOBILLINGPLANS'] . " bp
            LEFT JOIN subscription_types st ON bp.subscription_type_id = st.id
            WHERE bp.planActive = 1";
    
    // Filter by subscription type
    if ($subscription_type !== 'all') {
        if ($subscription_type === 'monthly') {
            $sql .= " AND st.name = 'monthly'";
        } elseif ($subscription_type === 'prepaid') {
            $sql .= " AND st.name = 'prepaid'";
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
        $plan = [
            'plan_id' => (int)$row['id'],
            'plan_name' => $row['planName'],
            'plan_type' => $row['planType'],
            'cost' => (float)$row['planCost'],
            'currency' => $row['planCurrency'] ?? 'USD',
            'is_bundle' => (bool)$row['is_bundle'],
            'subscription_type' => $row['subscription_type_name'] ?? 'monthly',
        ];
        
        // Bundle-specific details
        if ($row['is_bundle']) {
            $plan['bundle_details'] = [
                'validity_days' => (int)$row['bundle_validity_days'],
                'validity_hours' => (int)$row['bundle_validity_hours'],
                'auto_renew' => (bool)$row['auto_renew']
            ];
            
            // Calculate total validity in hours for display
            $totalHours = ((int)$row['bundle_validity_days'] * 24) + (int)$row['bundle_validity_hours'];
            $plan['bundle_details']['total_validity_hours'] = $totalHours;
            
            // Human-readable validity
            if ($row['bundle_validity_days'] > 0) {
                $plan['bundle_details']['validity_display'] = $row['bundle_validity_days'] . ' days';
                if ($row['bundle_validity_hours'] > 0) {
                    $plan['bundle_details']['validity_display'] .= ' ' . $row['bundle_validity_hours'] . ' hours';
                }
            } else {
                $plan['bundle_details']['validity_display'] = $row['bundle_validity_hours'] . ' hours';
            }
        }
        
        // Include detailed plan information if requested
        if ($include_details) {
            $plan['details'] = [
                'traffic_total_mb' => (float)$row['planTrafficTotal'],
                'traffic_upload_mb' => (float)$row['planTrafficUpload'],
                'traffic_download_mb' => (float)$row['planTrafficDownload'],
                'time_bank_minutes' => (int)$row['planTimeBank'],
                'recurring' => $row['planRecurring'] === 'Yes',
                'recurring_period' => $row['planRecurringPeriod']
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
