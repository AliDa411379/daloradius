<?php
include_once('../common/includes/daloradius.conf.php');

/**
 * Get Database Connection
 * Uses global $configValues from daloradius.conf.php
 */
function getDBConnection($isAjax = false) {
    global $configValues;
    
    if (!isset($configValues['CONFIG_DB_HOST'])) {
        $msg = 'Database configuration missing';
        if ($isAjax) {
            die(json_encode(['success' => false, 'message' => $msg]));
        } else {
            die($msg);
        }
    }

    $conn = new mysqli(
        $configValues['CONFIG_DB_HOST'],
        $configValues['CONFIG_DB_USER'],
        $configValues['CONFIG_DB_PASS'],
        $configValues['CONFIG_DB_NAME']
    );

    if ($conn->connect_error) {
        $msg = 'Database connection failed: ' . $conn->connect_error;
        if ($isAjax) {
            die(json_encode(['success' => false, 'message' => 'Database connection failed']));
        } else {
            die($msg);
        }
    }
    return $conn;
}

function generateRandomPassword($length = 8) {
    $characters = '0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

function getGroupNameFromPlan($conn, $planName) {
    $groupName = null;
    $sql = "SELECT profile_name FROM billing_plans_profiles WHERE plan_name = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $planName);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $groupName = $row['profile_name'];
        }
        $stmt->close();
    }
    if (empty($groupName)) {
        $groupName = "speed_10m";
    }
    return $groupName;
}

function sendPasswordSMS($mobile, $username, $password) {
    $sms_message = "Samanet ISP your password: " . $password;
    $mobile_number = str_replace('+', '', $mobile);
    $api_url = "http://mobily.samanet.sy/sendServerSMS.php";
    $url = $api_url . "?msg=" . urlencode($sms_message) . "&mob=" . urlencode($mobile_number);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error || $http_code !== 200) {
        return false;
    }
    return true;
}

function fetchActivePlans($conn) {
    $plans = [];
    $plans_query = "SELECT id, planName, planCost, planCurrency FROM billing_plans 
                    WHERE planActive = 'yes' AND is_bundle = 1 
                    ORDER BY planName";
    $result = $conn->query($plans_query);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $plans[] = $row;
        }
    }
    return $plans;
}

function fetchActiveAgents($conn) {
    $agents = [];
    $agents_query = "SELECT id, name, company, phone, email FROM agents 
                     WHERE is_deleted = 0 
                     ORDER BY name";
    $result = $conn->query($agents_query);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $agents[] = $row;
        }
    }
    return $agents;
}

/**
 * Register a new user
 * Returns array ['success' => bool, 'message' => string, ...]
 */
function registerUser($conn, $mobile, $plan_id, $agent_id) {
    // Validate mobile
    if (!preg_match('/^\+963[0-9]{9}$/', $mobile)) {
        return ['success' => false, 'message' => 'Invalid mobile number format'];
    }

    if (empty($plan_id) || empty($agent_id)) {
        return ['success' => false, 'message' => 'Plan and Agent selection are required'];
    }

    $username = str_replace('+', '', $mobile);
    $password = generateRandomPassword(8);

    // Check existing user
    $check_sql = "SELECT COUNT(*) AS cnt FROM radcheck WHERE username = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['cnt'] > 0) {
        return ['success' => false, 'message' => 'User already exists with this mobile number.'];
    }

    // Get Plan
    $plan_sql = "SELECT * FROM billing_plans WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($plan_sql);
    $stmt->bind_param("i", $plan_id);
    $stmt->execute();
    $plan_result = $stmt->get_result();
    
    if ($plan_result->num_rows == 0) {
        return ['success' => false, 'message' => 'Invalid plan selected'];
    }
    $plan = $plan_result->fetch_assoc();

    // Get Agent
    $agent_sql = "SELECT id, name FROM agents WHERE id = ? AND is_deleted = 0 LIMIT 1";
    $stmt = $conn->prepare($agent_sql);
    $stmt->bind_param("i", $agent_id);
    $stmt->execute();
    $agent_result = $stmt->get_result();
    
    if ($agent_result->num_rows == 0) {
        return ['success' => false, 'message' => 'Invalid agent selected'];
    }
    $agent = $agent_result->fetch_assoc();

    // Begin Transaction
    $conn->begin_transaction();
    try {
        // radcheck
        $sql1 = "INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)";
        $stmt = $conn->prepare($sql1);
        $stmt->bind_param("ss", $username, $password);
        if (!$stmt->execute()) throw new Exception("radcheck insert failed");

        // userinfo
        $sql2 = "INSERT INTO userinfo (username, firstname, lastname, email, department, company, workphone, homephone, mobilephone) 
                 VALUES (?, 'Guest', 'User', '', 'Samanet ISP', 'Samanet ISP', '', '', ?)";
        $stmt = $conn->prepare($sql2);
        $stmt->bind_param("ss", $username, $mobile);
        if (!$stmt->execute()) throw new Exception("userinfo insert failed");
        
        $user_info_id = $conn->insert_id;

        // userbillinfo
        $current_datetime = date('Y-m-d H:i:s');
        $timebank_balance = isset($plan['planTimeBank']) ? floatval($plan['planTimeBank']) : 1.00;
        $traffic_balance = isset($plan['planTrafficTotal']) ? floatval($plan['planTrafficTotal']) : 1;
        
        $check_col_sql = "SHOW COLUMNS FROM userbillinfo LIKE 'current_bundle_id'";
        $col_result = $conn->query($check_col_sql);
        $has_bundle_col = ($col_result && $col_result->num_rows > 0);
        
        $plan_note = $plan['planName'];
        $agent_note = $agent['name'];

        if ($has_bundle_col) {
            $sql3 = "INSERT INTO userbillinfo (
                        username, planName, paymentmethod, cash, notes,
                        billstatus, creationdate, creationby,
                        timebank_balance, traffic_balance, current_bundle_id
                     ) VALUES (?, ?, 'Cash', '0', CONCAT('Plan: ', ?, ', Agent: ', ?),
                               'Active', ?, 'system', ?, ?, ?)";
            $stmt = $conn->prepare($sql3);
            $stmt->bind_param("sssssddi", $username, $plan['planName'], $plan_note, $agent_note, $current_datetime, $timebank_balance, $traffic_balance, $plan_id);
        } else {
            $sql3 = "INSERT INTO userbillinfo (
                        username, planName, paymentmethod, cash, notes,
                        billstatus, creationdate, creationby,
                        timebank_balance, traffic_balance
                     ) VALUES (?, ?, 'Cash', '0', CONCAT('Plan: ', ?, ', Agent: ', ?),
                               'Active', ?, 'system', ?, ?)";
            $stmt = $conn->prepare($sql3);
            $stmt->bind_param("sssssdd", $username, $plan['planName'], $plan_note, $agent_note, $current_datetime, $timebank_balance, $traffic_balance);
        }
        if (!$stmt->execute()) throw new Exception("userbillinfo insert failed");

        // Group Name logic - Force Disabled Users
        $groupName = 'daloRADIUS-Disabled-Users';

        $sql4 = "INSERT INTO radusergroup (username, groupname, priority) VALUES (?, ?, 0)";
        $stmt = $conn->prepare($sql4);
        $stmt->bind_param("ss", $username, $groupName);
        if (!$stmt->execute()) throw new Exception("radusergroup insert failed");

        // radgroupcheck
        $check_group_sql = "SELECT COUNT(*) as cnt FROM radgroupcheck WHERE groupname = ?";
        $stmt = $conn->prepare($check_group_sql);
        $stmt->bind_param("s", $groupName);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row['cnt'] == 0) {
            $group_check_sql = "INSERT INTO radgroupcheck (groupname, attribute, op, value) VALUES (?, 'Auth-Type', ':=', 'Accept')";
            $stmt = $conn->prepare($group_check_sql);
            $stmt->bind_param("s", $groupName);
            $stmt->execute();
        }

        // user_agent
        $sql5 = "INSERT INTO user_agent (user_id, agent_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql5);
        $stmt->bind_param("ii", $user_info_id, $agent_id);
        if (!$stmt->execute()) throw new Exception("user_agent insert failed");
        
        // Expiration Logic
        $validity_days = isset($plan['bundle_validity_days']) ? intval($plan['bundle_validity_days']) : 0;
        $validity_hours = isset($plan['bundle_validity_hours']) ? intval($plan['bundle_validity_hours']) : 0;
        
        if ($validity_days > 0 || $validity_hours > 0) {
            $total_seconds = ($validity_days * 86400) + ($validity_hours * 3600);
            $expiration_timestamp = time() + $total_seconds;
            $expiration_value = date("d M Y H:i:s", $expiration_timestamp); // Format: 16 Dec 2025 14:00:00
            
            $sql_exp = "INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Expiration', ':=', ?)";
            $stmt = $conn->prepare($sql_exp);
            $stmt->bind_param("ss", $username, $expiration_value);
            $stmt->execute();
        }
        
        // radreply defaults


        $sql7 = "INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Mikrotik-Total-Limit', '=', '1073741824')";
        $stmt = $conn->prepare($sql7);
        $stmt->bind_param("s", $username);
        $stmt->execute(); // Ignore error

        $conn->commit();
        
        $sms_sent = sendPasswordSMS($mobile, $username, $password);

        return [
            'success' => true,
            'message' => $sms_sent ? 'Account created! Credentials sent via SMS.' : 'Account created!',
            'username' => $username,
            'password' => $password,
            'sms_sent' => $sms_sent
        ];
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error creating user: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error creating account: ' . $e->getMessage()];
    }
}
?>  
