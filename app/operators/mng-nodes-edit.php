<?php
/*
 * Nodes - Edit
 */
include_once implode(DIRECTORY_SEPARATOR, [ __DIR__, '..', 'common', 'includes', 'config_read.php' ]);
include implode(DIRECTORY_SEPARATOR, [ $configValues['OPERATORS_LIBRARY'], 'checklogin.php' ]);
$operator = $_SESSION['operator_user'];
include implode(DIRECTORY_SEPARATOR, [ $configValues['OPERATORS_LIBRARY'], 'check_operator_perm.php' ]);
// ACL key: mng_nodes_edit
include_once implode(DIRECTORY_SEPARATOR, [ $configValues['OPERATORS_LANG'], 'main.php' ]);
include implode(DIRECTORY_SEPARATOR, [ $configValues['COMMON_INCLUDES'], 'layout.php' ]);
include implode(DIRECTORY_SEPARATOR, [ $configValues['COMMON_INCLUDES'], 'db_open.php' ]);
include_once implode(DIRECTORY_SEPARATOR, [ $configValues['COMMON_INCLUDES'], 'validation.php' ]);

// init logging variables
$log = "visited page: ";
$logAction = "";
$logDebugSQL = "";

$validate_ip_or_cidr = function($value) {
    $value = trim($value);
    if ($value === '') return true; // Allow empty IP addresses
    if (strpos($value, '/') !== false) {
        list($base, $prefix) = explode('/', $value, 2);
        if (!filter_var($base, FILTER_VALIDATE_IP)) return false;
        if ($prefix === '' || preg_match('/^\d+$/', $prefix) !== 1) return false;
        $prefix = (int)$prefix;
        if (filter_var($base, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $prefix >= 0 && $prefix <= 32;
        }
        if (filter_var($base, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $prefix >= 0 && $prefix <= 128;
        }
        return false;
    }
    return filter_var($value, FILTER_VALIDATE_IP) !== false;
};

$pk = trim($_GET['mac'] ?? $_POST['mac'] ?? '');
if (empty($pk)) { header('Location: mng-nodes.php'); exit; }

$title = 'Edit Node';
print_html_prologue($title, $langCode);
print_title_and_help($title, 'Edit node info.');

$errors = [];
$successMsg = '';
$failureMsg = '';
$name = '';
$ip = '';
$latitude = '';
$longitude = '';
$uptime = $users = $cpu = $time = $memfree = '';

// Basic info
$description = '';
$type = '';
$netid = '';

// Owner info
$owner_name = '';
$owner_email = '';
$owner_phone = '';
$owner_address = '';

// Status
$approval_status = 'P';

// Network info
$gateway = '';
$gateway_bit = 0;
$hops = '';

// WAN interface
$wan_iface = '';
$wan_ip = '';
$wan_mac = '';
$wan_gateway = '';
$wan_bup = '';
$wan_bdown = '';

// WiFi interface
$wifi_iface = '';
$wifi_ip = '';
$wifi_mac = '';
$wifi_ssid = '';
$wifi_key = '';
$wifi_channel = '';

// LAN interface
$lan_iface = '';
$lan_mac = '';
$lan_ip = '';

// System info
$firmware = '';
$firmware_revision = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    // CSRF validation
    if (!dalo_check_csrf_token($_POST['csrf_token'] ?? '')) {
        $failureMsg = 'CSRF token error';
        $logAction .= "$failureMsg on page: ";
    } else {
    $name = trim($_POST['name'] ?? '');
    $ip   = trim($_POST['ip'] ?? '');
    $latitude = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');
    
    // Basic info
    $description = trim($_POST['description'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $netid = trim($_POST['netid'] ?? '');
    
    // Owner info
    $owner_name = trim($_POST['owner_name'] ?? '');
    $owner_email = trim($_POST['owner_email'] ?? '');
    $owner_phone = trim($_POST['owner_phone'] ?? '');
    $owner_address = trim($_POST['owner_address'] ?? '');
    
    // Status
    $approval_status = trim($_POST['approval_status'] ?? 'P');
    
    // Network info
    $gateway = trim($_POST['gateway'] ?? '');
    $gateway_bit = isset($_POST['gateway_bit']) ? 1 : 0;
    $hops = trim($_POST['hops'] ?? '');
    
    // WAN interface
    $wan_iface = trim($_POST['wan_iface'] ?? '');
    $wan_ip = trim($_POST['wan_ip'] ?? '');
    $wan_mac = trim($_POST['wan_mac'] ?? '');
    $wan_gateway = trim($_POST['wan_gateway'] ?? '');
    $wan_bup = trim($_POST['wan_bup'] ?? '');
    $wan_bdown = trim($_POST['wan_bdown'] ?? '');
    
    // WiFi interface
    $wifi_iface = trim($_POST['wifi_iface'] ?? '');
    $wifi_ip = trim($_POST['wifi_ip'] ?? '');
    $wifi_mac = trim($_POST['wifi_mac'] ?? '');
    $wifi_ssid = trim($_POST['wifi_ssid'] ?? '');
    $wifi_key = trim($_POST['wifi_key'] ?? '');
    $wifi_channel = trim($_POST['wifi_channel'] ?? '');
    
    // LAN interface
    $lan_iface = trim($_POST['lan_iface'] ?? '');
    $lan_mac = trim($_POST['lan_mac'] ?? '');
    $lan_ip = trim($_POST['lan_ip'] ?? '');
    
    // System info
    $firmware = trim($_POST['firmware'] ?? '');
    $firmware_revision = trim($_POST['firmware_revision'] ?? '');

    if (!$validate_ip_or_cidr($ip)) $errors[] = 'Valid IP is required';
    
    // Validate latitude and longitude if provided
    if (!empty($latitude) && (!is_numeric($latitude) || $latitude < -90 || $latitude > 90)) {
        $errors[] = 'Latitude must be a number between -90 and 90';
    }
    if (!empty($longitude) && (!is_numeric($longitude) || $longitude < -180 || $longitude > 180)) {
        $errors[] = 'Longitude must be a number between -180 and 180';
    }
    
    // Validate email if provided
    if (!empty($owner_email) && !filter_var($owner_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email address is required';
    }
    
    // Validate approval status
    if (!in_array($approval_status, ['A', 'R', 'P'])) {
        $approval_status = 'P';
    }
    
    // Validate numeric fields
    if (!empty($netid) && !is_numeric($netid)) {
        $errors[] = 'Network ID must be numeric';
    }
    if (!empty($hops) && !is_numeric($hops)) {
        $errors[] = 'Hops must be numeric';
    }
    if (!empty($wifi_channel) && !is_numeric($wifi_channel)) {
        $errors[] = 'WiFi channel must be numeric';
    }

    if ($errors) {
        $failureMsg = 'Please correct the following errors: ' . implode(', ', $errors);
        $logAction .= "Failed editing node [$pk] due to validation errors on page: ";
    }
    
    if (!$errors) {
        // Use direct SQL approach like other mng edit files
        $sql = sprintf("UPDATE node SET name='%s', ip='%s', latitude='%s', longitude='%s', description='%s', type='%s', 
                netid='%s', owner_name='%s', owner_email='%s', owner_phone='%s', owner_address='%s', 
                approval_status='%s', gateway='%s', gateway_bit=%d, hops='%s', wan_iface='%s', wan_ip='%s', 
                wan_mac='%s', wan_gateway='%s', wan_bup='%s', wan_bdown='%s', wifi_iface='%s', wifi_ip='%s', 
                wifi_mac='%s', wifi_ssid='%s', wifi_key='%s', wifi_channel='%s', lan_iface='%s', lan_mac='%s', 
                lan_ip='%s', firmware='%s', firmware_revision='%s' WHERE mac='%s'",
                $dbSocket->escapeSimple($name), $dbSocket->escapeSimple($ip), $dbSocket->escapeSimple($latitude), 
                $dbSocket->escapeSimple($longitude), $dbSocket->escapeSimple($description), $dbSocket->escapeSimple($type), 
                $dbSocket->escapeSimple($netid), $dbSocket->escapeSimple($owner_name), $dbSocket->escapeSimple($owner_email), 
                $dbSocket->escapeSimple($owner_phone), $dbSocket->escapeSimple($owner_address), $dbSocket->escapeSimple($approval_status), 
                $dbSocket->escapeSimple($gateway), $gateway_bit, $dbSocket->escapeSimple($hops), $dbSocket->escapeSimple($wan_iface), 
                $dbSocket->escapeSimple($wan_ip), $dbSocket->escapeSimple($wan_mac), $dbSocket->escapeSimple($wan_gateway), 
                $dbSocket->escapeSimple($wan_bup), $dbSocket->escapeSimple($wan_bdown), $dbSocket->escapeSimple($wifi_iface), 
                $dbSocket->escapeSimple($wifi_ip), $dbSocket->escapeSimple($wifi_mac), $dbSocket->escapeSimple($wifi_ssid), 
                $dbSocket->escapeSimple($wifi_key), $dbSocket->escapeSimple($wifi_channel), $dbSocket->escapeSimple($lan_iface), 
                $dbSocket->escapeSimple($lan_mac), $dbSocket->escapeSimple($lan_ip), $dbSocket->escapeSimple($firmware), 
                $dbSocket->escapeSimple($firmware_revision), $dbSocket->escapeSimple($pk));
        
        $res = $dbSocket->query($sql);
        $logDebugSQL .= "$sql;\n";
        
        if (PEAR::isError($res)) {
            $failureMsg = sprintf("Failed to edit node [%s]: %s", htmlspecialchars($pk, ENT_QUOTES, 'UTF-8'), $res->getMessage());
            $logAction .= "Failed editing node [$pk] on page: ";
        } else {
            $successMsg = sprintf("Successfully edited node: <strong>%s</strong>", htmlspecialchars($pk, ENT_QUOTES, 'UTF-8'));
            $logAction .= "Successfully edited node [$pk] on page: ";
            // Reload the data from database to show updated values
            $sql = "SELECT mac, name, ip, latitude, longitude, description, type, netid, owner_name, owner_email, owner_phone, owner_address, 
                    approval_status, gateway, gateway_bit, hops, wan_iface, wan_ip, wan_mac, wan_gateway, wan_bup, wan_bdown, 
                    wifi_iface, wifi_ip, wifi_mac, wifi_ssid, wifi_key, wifi_channel, lan_iface, lan_mac, lan_ip, 
                    firmware, firmware_revision, uptime, users, cpu, time, memfree FROM node WHERE mac=" . $dbSocket->quoteSmart($pk);
            $res_reload = $dbSocket->query($sql);
            if ($row_reload = $res_reload->fetchRow()) {
                list($mac, $name, $ip, $latitude, $longitude, $description, $type, $netid, $owner_name, $owner_email, 
                     $owner_phone, $owner_address, $approval_status, $gateway, $gateway_bit, $hops, $wan_iface, 
                     $wan_ip, $wan_mac, $wan_gateway, $wan_bup, $wan_bdown, $wifi_iface, $wifi_ip, $wifi_mac, 
                     $wifi_ssid, $wifi_key, $wifi_channel, $lan_iface, $lan_mac, $lan_ip, $firmware, 
                     $firmware_revision, $uptime, $users, $cpu, $time, $memfree) = $row_reload;
            }
        }
    }
    } // end CSRF validation else
}

// load current
$sql = "SELECT mac, name, ip, latitude, longitude, description, type, netid, owner_name, owner_email, owner_phone, owner_address, 
        approval_status, gateway, gateway_bit, hops, wan_iface, wan_ip, wan_mac, wan_gateway, wan_bup, wan_bdown, 
        wifi_iface, wifi_ip, wifi_mac, wifi_ssid, wifi_key, wifi_channel, lan_iface, lan_mac, lan_ip, 
        firmware, firmware_revision, uptime, users, cpu, time, memfree FROM node WHERE mac=" . $dbSocket->quoteSmart($pk);
$res = $dbSocket->query($sql);
$logDebugSQL .= "$sql;\n";
if ($row = $res->fetchRow()) {
    list($mac, $name_db, $ip_db, $latitude_db, $longitude_db, $description_db, $type_db, $netid_db, $owner_name_db, $owner_email_db, 
         $owner_phone_db, $owner_address_db, $approval_status_db, $gateway_db, $gateway_bit_db, $hops_db, $wan_iface_db, 
         $wan_ip_db, $wan_mac_db, $wan_gateway_db, $wan_bup_db, $wan_bdown_db, $wifi_iface_db, $wifi_ip_db, $wifi_mac_db, 
         $wifi_ssid_db, $wifi_key_db, $wifi_channel_db, $lan_iface_db, $lan_mac_db, $lan_ip_db, $firmware_db, 
         $firmware_revision_db, $uptime, $users, $cpu, $time, $memfree) = $row;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
        $name = $name_db; 
        $ip = $ip_db; 
        $latitude = $latitude_db;
        $longitude = $longitude_db;
        $description = $description_db;
        $type = $type_db;
        $netid = $netid_db;
        $owner_name = $owner_name_db;
        $owner_email = $owner_email_db;
        $owner_phone = $owner_phone_db;
        $owner_address = $owner_address_db;
        $approval_status = $approval_status_db;
        $gateway = $gateway_db;
        $gateway_bit = $gateway_bit_db;
        $hops = $hops_db;
        $wan_iface = $wan_iface_db;
        $wan_ip = $wan_ip_db;
        $wan_mac = $wan_mac_db;
        $wan_gateway = $wan_gateway_db;
        $wan_bup = $wan_bup_db;
        $wan_bdown = $wan_bdown_db;
        $wifi_iface = $wifi_iface_db;
        $wifi_ip = $wifi_ip_db;
        $wifi_mac = $wifi_mac_db;
        $wifi_ssid = $wifi_ssid_db;
        $wifi_key = $wifi_key_db;
        $wifi_channel = $wifi_channel_db;
        $lan_iface = $lan_iface_db;
        $lan_mac = $lan_mac_db;
        $lan_ip = $lan_ip_db;
        $firmware = $firmware_db;
        $firmware_revision = $firmware_revision_db;
    }
} else {
    echo '<div class="alert alert-danger">Node not found</div>';
}

include_once('include/management/actionMessages.php');

if (!empty($successMsg)) {
    echo '<script>
        setTimeout(function() {
            if (confirm("Node updated successfully! Click OK to go to the nodes list, or Cancel to continue editing.")) {
                window.location.href = "mng-nodes-list.php";
            }
        }, 2000);
    </script>';
}
?>

<!-- Nav tabs -->
<ul class="nav nav-tabs" id="nodeTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button" role="tab">Basic Info</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="owner-tab" data-bs-toggle="tab" data-bs-target="#owner" type="button" role="tab">Owner Info</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="network-tab" data-bs-toggle="tab" data-bs-target="#network" type="button" role="tab">Network</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="interfaces-tab" data-bs-toggle="tab" data-bs-target="#interfaces" type="button" role="tab">Interfaces</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab">System</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="status-tab" data-bs-toggle="tab" data-bs-target="#status" type="button" role="tab">Status</button>
    </li>
</ul>

<form method="POST" class="mt-3" action="">
    <input type="hidden" name="csrf_token" value="<?= dalo_csrf_token() ?>">
    <input type="hidden" name="mac" value="<?= htmlspecialchars($pk, ENT_QUOTES, 'UTF-8') ?>">
    
    <!-- Tab content -->
    <div class="tab-content" id="nodeTabContent">
        <!-- Basic Info Tab -->
        <div class="tab-pane fade show active" id="basic" role="tabpanel">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">MAC Address</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($pk, ENT_QUOTES, 'UTF-8') ?>" disabled>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">IP Address</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') ?>" disabled>
                        <input type="hidden" name="ip" value="<?= htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" disabled>
                        <input type="hidden" name="name" value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Network ID</label>
                        <input type="number" class="form-control" value="<?= htmlspecialchars($netid, ENT_QUOTES, 'UTF-8') ?>" disabled>
                        <input type="hidden" name="netid" value="<?= htmlspecialchars($netid, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Type</label>
                <select name="type" class="form-select">
                    <option value="">Select Type</option>
                    <option value="point to point" <?= $type === 'point to point' ? 'selected' : '' ?>>Point to Point</option>
                    <option value="sector" <?= $type === 'sector' ? 'selected' : '' ?>>Sector</option>
                    <option value="nas" <?= $type === 'nas' ? 'selected' : '' ?>>NAS</option>
                </select>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Latitude</label>
                        <input type="text" name="latitude" class="form-control" value="<?= htmlspecialchars($latitude, ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g., 40.7128">
                        <div class="form-text">Latitude coordinate (-90 to 90)</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Longitude</label>
                        <input type="text" name="longitude" class="form-control" value="<?= htmlspecialchars($longitude, ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g., -74.0060">
                        <div class="form-text">Longitude coordinate (-180 to 180)</div>
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Approval Status</label>
                <select name="approval_status" class="form-select">
                    <option value="P" <?= $approval_status === 'P' ? 'selected' : '' ?>>Pending</option>
                    <option value="A" <?= $approval_status === 'A' ? 'selected' : '' ?>>Approved</option>
                    <option value="R" <?= $approval_status === 'R' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
        </div>

        <!-- Owner Info Tab -->
        <div class="tab-pane fade" id="owner" role="tabpanel">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Owner Name</label>
                        <input type="text" name="owner_name" class="form-control" value="<?= htmlspecialchars($owner_name, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Owner Email</label>
                        <input type="email" name="owner_email" class="form-control" value="<?= htmlspecialchars($owner_email, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Owner Phone</label>
                <input type="text" name="owner_phone" class="form-control" value="<?= htmlspecialchars($owner_phone, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Owner Address</label>
                <textarea name="owner_address" class="form-control" rows="3"><?= htmlspecialchars($owner_address, ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
        </div>

        <!-- Network Tab -->
        <div class="tab-pane fade" id="network" role="tabpanel">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Gateway</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($gateway, ENT_QUOTES, 'UTF-8') ?>" disabled>
                        <input type="hidden" name="gateway" value="<?= htmlspecialchars($gateway, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Hops to Gateway</label>
                        <input type="number" class="form-control" value="<?= htmlspecialchars($hops, ENT_QUOTES, 'UTF-8') ?>" disabled>
                        <input type="hidden" name="hops" value="<?= htmlspecialchars($hops, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="gateway_bit" <?= $gateway_bit ? 'checked' : '' ?> disabled>
                    <input type="hidden" name="gateway_bit" value="<?= $gateway_bit ? '1' : '0' ?>">
                    <label class="form-check-label" for="gateway_bit">
                        This node is a gateway
                    </label>
                </div>
            </div>
        </div>

        <!-- Interfaces Tab -->
        <div class="tab-pane fade" id="interfaces" role="tabpanel">
            <h5>WAN Interface</h5>
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Interface</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($wan_iface, ENT_QUOTES, 'UTF-8') ?>" disabled>
                        <input type="hidden" name="wan_iface" value="<?= htmlspecialchars($wan_iface, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">IP Address</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($wan_ip, ENT_QUOTES, 'UTF-8') ?>" disabled>
                        <input type="hidden" name="wan_ip" value="<?= htmlspecialchars($wan_ip, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">MAC Address</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($wan_mac, ENT_QUOTES, 'UTF-8') ?>" disabled>
                        <input type="hidden" name="wan_mac" value="<?= htmlspecialchars($wan_mac, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Gateway</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($wan_gateway, ENT_QUOTES, 'UTF-8') ?>" disabled>
                        <input type="hidden" name="wan_gateway" value="<?= htmlspecialchars($wan_gateway, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Bandwidth Up</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($wan_bup, ENT_QUOTES, 'UTF-8') ?>" disabled>
                        <input type="hidden" name="wan_bup" value="<?= htmlspecialchars($wan_bup, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Bandwidth Down</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($wan_bdown, ENT_QUOTES, 'UTF-8') ?>" disabled>
                        <input type="hidden" name="wan_bdown" value="<?= htmlspecialchars($wan_bdown, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
            </div>

            <h5 class="mt-4">WiFi Interface</h5>
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Interface</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($wifi_iface, ENT_QUOTES, 'UTF-8') ?>" disabled>
                        <input type="hidden" name="wifi_iface" value="<?= htmlspecialchars($wifi_iface, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">IP Address</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($wifi_ip, ENT_QUOTES, 'UTF-8') ?>" disabled>
                        <input type="hidden" name="wifi_ip" value="<?= htmlspecialchars($wifi_ip, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">MAC Address</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($wifi_mac, ENT_QUOTES, 'UTF-8') ?>" disabled>
                        <input type="hidden" name="wifi_mac" value="<?= htmlspecialchars($wifi_mac, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">SSID</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($wifi_ssid, ENT_QUOTES, 'UTF-8') ?>" disabled>
                        <input type="hidden" name="wifi_ssid" value="<?= htmlspecialchars($wifi_ssid, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">WiFi Key</label>
                        <input type="password" class="form-control" value="<?= htmlspecialchars($wifi_key, ENT_QUOTES, 'UTF-8') ?>" disabled>
                        <input type="hidden" name="wifi_key" value="<?= htmlspecialchars($wifi_key, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Channel</label>
                        <input type="number" class="form-control" value="<?= htmlspecialchars($wifi_channel, ENT_QUOTES, 'UTF-8') ?>" disabled>
                        <input type="hidden" name="wifi_channel" value="<?= htmlspecialchars($wifi_channel, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
            </div>

            <h5 class="mt-4">LAN Interface</h5>
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Interface</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($lan_iface, ENT_QUOTES, 'UTF-8') ?>" disabled>
                        <input type="hidden" name="lan_iface" value="<?= htmlspecialchars($lan_iface, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">MAC Address</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($lan_mac, ENT_QUOTES, 'UTF-8') ?>" disabled>
                        <input type="hidden" name="lan_mac" value="<?= htmlspecialchars($lan_mac, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">IP Address</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($lan_ip, ENT_QUOTES, 'UTF-8') ?>" disabled>
                        <input type="hidden" name="lan_ip" value="<?= htmlspecialchars($lan_ip, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- System Tab -->
        <div class="tab-pane fade" id="system" role="tabpanel">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Firmware</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($firmware, ENT_QUOTES, 'UTF-8') ?>" disabled>
                        <input type="hidden" name="firmware" value="<?= htmlspecialchars($firmware, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Firmware Revision</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($firmware_revision, ENT_QUOTES, 'UTF-8') ?>" disabled>
                        <input type="hidden" name="firmware_revision" value="<?= htmlspecialchars($firmware_revision, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Tab (Read-only) -->
        <div class="tab-pane fade" id="status" role="tabpanel">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Uptime</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($uptime, ENT_QUOTES, 'UTF-8') ?>" disabled>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Users</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($users, ENT_QUOTES, 'UTF-8') ?>" disabled>
                </div>
                <div class="col-md-3">
                    <label class="form-label">CPU %</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($cpu, ENT_QUOTES, 'UTF-8') ?>" disabled>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Memory Free</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($memfree, ENT_QUOTES, 'UTF-8') ?>" disabled>
                </div>
            </div>
            <div class="row g-3 mt-2">
                <div class="col-md-3">
                    <label class="form-label">Last Seen</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($time, ENT_QUOTES, 'UTF-8') ?>" disabled>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <button type="submit" name="save" class="btn btn-primary">Save Changes</button>
        <a href="mng-nodes.php" class="btn btn-secondary">Cancel</a>
    </div>
</form>
<?php
include implode(DIRECTORY_SEPARATOR, [ $configValues['COMMON_INCLUDES'], 'db_close.php' ]);
include implode(DIRECTORY_SEPARATOR, [ $configValues['OPERATORS_LIBRARY'], 'logging.php' ]);
print_footer_and_html_epilogue();