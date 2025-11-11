<?php
/**
 * Enhanced Billing Script for daloRADIUS
 * Supports Fixed / Time / Traffic plans
 * - Calculates billing amount
 * - Creates invoices, invoice_items, billing_history
 * - Updates lastbill / nextbill / balances
 * - Prevents duplicate invoices per planRecurringPeriod
 * - Detailed logging for each user
 */

// ================= CONFIGURATION =================
define('LOCK_FILE', '/var/www/daloradius/var/scripts/billing.lock');
define('LOG_FILE', '/var/www/daloradius/var/logs/billing.log');

// Database credentials
define('DB_HOST', '172.30.16.200');
define('DB_USER', 'bassel');
define('DB_PASS', 'bassel_password');
define('DB_NAME', 'radius');

// ================= Logging Function =================
function log_message($msg, $level='INFO'){
    file_put_contents(LOG_FILE, date('[Y-m-d H:i:s]') . " [$level] $msg\n", FILE_APPEND);
}

// ================= Main Script =================
try {
    if(file_exists(LOCK_FILE)){
        log_message("Script already running",'WARNING');
        exit(0);
    }
    file_put_contents(LOCK_FILE, getmypid());
    log_message("=== Starting Billing Process ===");

    // Database connection
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if($db->connect_error) throw new Exception("DB Connection Failed: ".$db->connect_error);
    log_message("Connected to Database");

    // Get active recurring plans
    $plans_query = "SELECT * FROM billing_plans WHERE planActive='yes' AND planRecurring='yes'";
    $plans = $db->query($plans_query);
    if(!$plans) throw new Exception("Plan Query Failed: ".$db->error);

    while($plan = $plans->fetch_assoc()){
        $planName = $db->real_escape_string($plan['planName']);
        log_message(">>> Processing Plan: {$planName} (ID: {$plan['id']}, Type: {$plan['planType']})");

        // Get users due for billing
        $users_query = "SELECT * FROM userbillinfo 
                        WHERE planName='{$planName}' 
                        AND (nextbill <= CURDATE() OR nextbill IS NULL OR nextbill='0000-00-00')";
        $users = $db->query($users_query);
        if(!$users){
            log_message("User Query Failed for Plan {$planName}: ".$db->error,'ERROR');
            continue;
        }

        while($user = $users->fetch_assoc()){
            log_message("---- User: {$user['username']} (ID: {$user['id']}) due for billing ----");

            // ================= Calculate Amount =================
            $amount = floatval($plan['planCost']); // default fixed
            $balance = null;

            // Time-based
            if(stripos($plan['planType'],'Time')!==false){
                $lastbill = ($user['lastbill'] && $user['lastbill']!='0000-00-00') 
                ? $user['lastbill'] 
                : date('Y-m-d', strtotime('-1 '.$plan['planRecurringPeriod']));
                $usage_query = "SELECT SUM(acctsessiontime) AS total_seconds 
                    FROM radacct WHERE username='{$user['username']}' AND acctstarttime > '{$lastbill}'";
                $result = $db->query($usage_query);
                $used_seconds = ($result && $row=$result->fetch_assoc()) ? $row['total_seconds'] : 0;

                $plan_minutes = floatval($plan['planTimeBank']);
                $used_minutes = round($used_seconds / 60, 2);

                $amount = floatval($plan['planCost']);  

                $balance = max($plan_minutes - $used_minutes, 0);

                log_message("   Time Plan: Used={$used_minutes} min / Allowed={$plan_minutes} min | Balance={$balance} min | Amount={$amount}");
            }

            // Traffic-based
            if(stripos($plan['planType'],'Traffic')!==false){
                $lastbill = ($user['lastbill'] && $user['lastbill']!='0000-00-00')?$user['lastbill']:'1970-01-01';
                $usage_query = "SELECT SUM(acctinputoctets+acctoutputoctets) AS total_bytes 
                                FROM radacct WHERE username='{$user['username']}' AND acctstarttime > '{$lastbill}'";
                $result = $db->query($usage_query);
                $used_bytes = ($result && $row=$result->fetch_assoc()) ? $row['total_bytes'] : 0;

                $plan_mb = floatval($plan['planTrafficTotal']);
                $used_mb = round($used_bytes / (1024*1024),2);

                $balance = max($plan_mb - $used_mb,0);
                $amount = $plan['planCost']; // fixed per cycle

                log_message("   Traffic Plan: Used={$used_mb} MB / Allowed={$plan_mb} MB | Balance={$balance} MB | Amount={$amount}");
            }

            // ================= Duplicate Check =================
            $dup_sql = generate_duplicate_check_sql($db,$user['username'],$planName,$plan['planRecurringPeriod']);
            $dup_check = $db->query($dup_sql)->fetch_assoc();
            if($dup_check){
                log_message("   SKIPPED: Duplicate invoice exists for user {$user['username']} & plan {$planName}",'WARNING');
                continue;
            }

            // ================= Create Invoice =================
            $db->query("INSERT INTO invoice (user_id,date,status_id,type_id,notes,creationdate,creationby)
                        VALUES ({$user['id']}, NOW(), 1, 1, 'Automated billing for {$planName}', NOW(),'system')");
            if($db->error){ log_message("   Invoice Insert Failed: ".$db->error,'ERROR'); continue; }
            $invoice_id = $db->insert_id;
            log_message("   Invoice created (ID: {$invoice_id})");

            // Invoice Items
            $db->query("INSERT INTO invoice_items (invoice_id,plan_id,amount,notes,creationdate,creationby)
                        VALUES ($invoice_id, {$plan['id']}, {$amount}, '{$plan['planType']} usage - {$planName}', NOW(),'system')");
            if($db->error){ log_message("   Invoice Items Insert Failed: ".$db->error,'ERROR'); continue; }
            log_message("   Invoice item added: {$amount}");

            // Billing History
            $db->query("INSERT INTO billing_history (username, planId, billAmount, billAction, creationdate, creationby)
                        VALUES ('{$user['username']}', {$plan['id']}, {$amount}, 'Automated Billing + Invoice', NOW(),'system')");
            if($db->error){ log_message("   Billing History Insert Failed: ".$db->error,'ERROR'); }
            else log_message("   Billing history recorded");

            // ================= Update userbillinfo =================
            $interval = get_billing_interval($plan['planRecurringPeriod']);
            $update_fields = "lastbill=CURDATE(), nextbill=DATE_ADD(CURDATE(), INTERVAL {$interval})";

            if(stripos($plan['planType'],'Time')!==false){
                $update_fields .= ", timebank_balance={$balance}";
            }
            if(stripos($plan['planType'],'Traffic')!==false){
                $update_fields .= ", traffic_balance={$balance}";
            }

            $db->query("UPDATE userbillinfo SET {$update_fields} WHERE id={$user['id']}");
            if($db->error) log_message("   Failed to update userbillinfo: ".$db->error,'ERROR');
            else log_message("   userbillinfo updated: lastbill=NOW, nextbill=+{$plan['planRecurringPeriod']}, balance={$balance}");

            log_message("==== Billing complete for user {$user['username']} (Amount: {$amount}) ====");
        }
    }

    $db->close();
    log_message("=== Billing Process Completed ===");

} catch(Exception $e){
    log_message($e->getMessage(),'ERROR');
} finally{
    if(file_exists(LOCK_FILE)) unlink(LOCK_FILE);
}

// =========== Helper Functions ===========
function get_billing_interval($period){
    switch(strtolower($period)){
        case 'daily': return '1 DAY';
        case 'weekly': return '1 WEEK';
        case 'monthly': return '1 MONTH';
        case 'quarterly': return '3 MONTH';
        case 'yearly': return '1 YEAR';
        default: return '1 MONTH';
    }
}

function generate_duplicate_check_sql($db,$username,$planName,$period){
    $username = $db->real_escape_string($username);
    $planName = $db->real_escape_string($planName);
    switch(strtolower($period)){
        case 'daily':
            return "SELECT id FROM invoice WHERE user_id=(SELECT id FROM userbillinfo WHERE username='$username') 
                    AND id IN (SELECT invoice_id FROM invoice_items WHERE plan_id=(SELECT id FROM billing_plans WHERE planName='$planName'))
                    AND DATE(date)=CURDATE() LIMIT 1";
        case 'weekly':
            return "SELECT id FROM invoice WHERE user_id=(SELECT id FROM userbillinfo WHERE username='$username') 
                    AND id IN (SELECT invoice_id FROM invoice_items WHERE plan_id=(SELECT id FROM billing_plans WHERE planName='$planName'))
                    AND YEARWEEK(date,1)=YEARWEEK(CURDATE(),1) LIMIT 1";
        case 'monthly':
        default:
            return "SELECT id FROM invoice WHERE user_id=(SELECT id FROM userbillinfo WHERE username='$username') 
                    AND id IN (SELECT invoice_id FROM invoice_items WHERE plan_id=(SELECT id FROM billing_plans WHERE planName='$planName'))
                    AND YEAR(date)=YEAR(CURDATE()) AND MONTH(date)=MONTH(CURDATE()) LIMIT 1";
    }
}
?>
