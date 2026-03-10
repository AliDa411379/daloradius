<?php
include_once('../common/includes/daloradius.conf.php');

define('FREE_PLAN_NAME', 'free registration bundle');

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

function fetchActiveAgents($conn) {
    $agents = [];
    $agents_query = "SELECT id, name, company, phone, email FROM agents
                     WHERE is_deleted = 0 AND show_in_signup = 1
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
 * Register a new user with free 1-hour bundle
 * Returns array ['success' => bool, 'message' => string, ...]
 */
function registerUser($conn, $mobile, $plan_id, $agent_id) {
    // Validate mobile
    if (!preg_match('/^\+963[0-9]{9}$/', $mobile)) {
        return ['success' => false, 'message' => 'Invalid mobile number format'];
    }

    if (empty($agent_id)) {
        return ['success' => false, 'message' => 'Agent selection is required'];
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

    // Get free registration plan
    $plan_sql = "SELECT * FROM billing_plans WHERE planName = ? LIMIT 1";
    $stmt = $conn->prepare($plan_sql);
    $free_plan_name = FREE_PLAN_NAME;
    $stmt->bind_param("s", $free_plan_name);
    $stmt->execute();
    $plan_result = $stmt->get_result();

    if ($plan_result->num_rows == 0) {
        return ['success' => false, 'message' => 'Free registration plan not found'];
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
        // 1. radcheck - password
        $sql1 = "INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)";
        $stmt = $conn->prepare($sql1);
        $stmt->bind_param("ss", $username, $password);
        if (!$stmt->execute()) throw new Exception("radcheck insert failed");

        // 2. userinfo
        $sql2 = "INSERT INTO userinfo (username, firstname, lastname, email, department, company, workphone, homephone, mobilephone)
                 VALUES (?, 'Guest', 'User', '', 'Samanet ISP', 'Samanet ISP', '', '', ?)";
        $stmt = $conn->prepare($sql2);
        $stmt->bind_param("ss", $username, $mobile);
        if (!$stmt->execute()) throw new Exception("userinfo insert failed");

        $user_info_id = $conn->insert_id;

        // 3. userbillinfo - with bundle fields
        $current_datetime = date('Y-m-d H:i:s');
        $timebank_balance = floatval($plan['planTimeBank'] ?? 0);
        $traffic_balance = floatval($plan['planTrafficTotal'] ?? 0);

        $validity_days = intval($plan['bundle_validity_days'] ?? 0);
        $validity_hours = intval($plan['bundle_validity_hours'] ?? 0);
        $expiry_datetime = null;
        if ($validity_days > 0 || $validity_hours > 0) {
            $expiry_datetime = date('Y-m-d H:i:s', time() + ($validity_days * 86400) + ($validity_hours * 3600));
        }

        $plan_note = $plan['planName'];
        $agent_note = $agent['name'];

        $sql3 = "INSERT INTO userbillinfo (
                    username, planName, paymentmethod, cash, notes,
                    billstatus, creationdate, creationby,
                    subscription_type_id, money_balance,
                    timebank_balance, traffic_balance,
                    bundle_activation_date, bundle_expiry_date, bundle_status
                 ) VALUES (?, ?, 'Cash', '0', CONCAT('Plan: ', ?, ', Agent: ', ?),
                           'Active', ?, 'system',
                           2, 0,
                           ?, ?,
                           ?, ?, 'active')";
        $stmt = $conn->prepare($sql3);
        $stmt->bind_param("sssssddss",
            $username, $plan_note, $plan_note, $agent_note,
            $current_datetime,
            $timebank_balance, $traffic_balance,
            $current_datetime, $expiry_datetime
        );
        if (!$stmt->execute()) throw new Exception("userbillinfo insert failed: " . $stmt->error);

        // 4. radusergroup - from billing_plans_profiles (not hardcoded)
        $planName_esc = $conn->real_escape_string($plan['planName']);
        $profileResult = $conn->query("SELECT DISTINCT profile_name FROM billing_plans_profiles WHERE plan_name = '$planName_esc'");
        if ($profileResult && $profileResult->num_rows > 0) {
            while ($prow = $profileResult->fetch_assoc()) {
                $groupName = $prow['profile_name'];
                $sql4 = "INSERT INTO radusergroup (username, groupname, priority) VALUES (?, ?, 1)";
                $stmt = $conn->prepare($sql4);
                $stmt->bind_param("ss", $username, $groupName);
                if (!$stmt->execute()) throw new Exception("radusergroup insert failed for group: $groupName");
            }
        } else {
            // Fallback: use plan name as group
            $groupName = $plan['planName'];
            $sql4 = "INSERT INTO radusergroup (username, groupname, priority) VALUES (?, ?, 1)";
            $stmt = $conn->prepare($sql4);
            $stmt->bind_param("ss", $username, $groupName);
            if (!$stmt->execute()) throw new Exception("radusergroup insert failed");
        }

        // 5. user_agent
        $sql5 = "INSERT INTO user_agent (user_id, agent_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql5);
        $stmt->bind_param("ii", $user_info_id, $agent_id);
        if (!$stmt->execute()) throw new Exception("user_agent insert failed");

        // 6. Expiration - FreeRADIUS format: "Mon DD YYYY HH:MM:SS"
        if ($validity_days > 0 || $validity_hours > 0) {
            $total_seconds = ($validity_days * 86400) + ($validity_hours * 3600);
            $expiration_value = date("d M Y H:i:s", time() + $total_seconds);

            $sql_exp = "INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Expiration', ':=', ?)";
            $stmt = $conn->prepare($sql_exp);
            $stmt->bind_param("ss", $username, $expiration_value);
            $stmt->execute();
        }

        // 7. Mikrotik-Rate-Limit from plan bandwidth
        $bwUp = intval($plan['planBandwidthUp'] ?? 0);
        $bwDown = intval($plan['planBandwidthDown'] ?? 0);
        if ($bwUp > 0 || $bwDown > 0) {
            $rateLimit = "{$bwUp}k/{$bwDown}k";
            $sql_bw = "INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Mikrotik-Rate-Limit', ':=', ?)";
            $stmt = $conn->prepare($sql_bw);
            $stmt->bind_param("ss", $username, $rateLimit);
            $stmt->execute();
        }

        // 8. Mikrotik-Total-Limit + Gigawords (operator := , with >4GB support)
        $trafficMB = floatval($plan['planTrafficTotal'] ?? 0);
        if ($trafficMB > 0) {
            $totalBytes = $trafficMB * 1048576;
            $gigawords = floor($totalBytes / 4294967296); // 2^32
            $remainBytes = intval(fmod($totalBytes, 4294967296));

            $sql_tl = "INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Mikrotik-Total-Limit', ':=', ?)";
            $stmt = $conn->prepare($sql_tl);
            $remainStr = (string)$remainBytes;
            $stmt->bind_param("ss", $username, $remainStr);
            $stmt->execute();

            if ($gigawords > 0) {
                $gwStr = (string)intval($gigawords);
                $sql_gw = "INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Mikrotik-Total-Limit-Gigawords', ':=', ?)";
                $stmt = $conn->prepare($sql_gw);
                $stmt->bind_param("ss", $username, $gwStr);
                $stmt->execute();
            }
        }

        $conn->commit();

        // Get actual RADIUS group name from billing_plans_profiles
        $groupResult = $conn->query(sprintf(
            "SELECT GROUP_CONCAT(DISTINCT profile_name ORDER BY profile_name SEPARATOR ', ') AS group_names
             FROM billing_plans_profiles WHERE plan_name = '%s'",
            $conn->real_escape_string($plan['planName'])
        ));
        $groupName = ($groupResult && $grow = $groupResult->fetch_assoc()) ? $grow['group_names'] : $plan['planName'];

        $sms_sent = sendPasswordSMS($mobile, $username, $password);

        return [
            'success' => true,
            'message' => $sms_sent ? 'Account created! Credentials sent via SMS.' : 'Account created!',
            'username' => $username,
            'password' => $password,
            'sms_sent' => $sms_sent,
            'group_name' => $groupName,
            'expiry_date' => $expiry_datetime,
            'expiry_date_formatted' => $expiry_datetime ? date('d M Y H:i', strtotime($expiry_datetime)) : null
        ];
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error creating user: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error creating account: ' . $e->getMessage()];
    }
}
?>
