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

$pk = trim($_GET['mac'] ?? $_POST['mac'] ?? '');
if (empty($pk)) { header('Location: mng-nodes.php'); exit; }

$title = 'Edit Node';
print_html_prologue($title, $langCode);
print_title_and_help($title, 'Edit node info.');

$errors = [];
$name = '';
$ip = '';
$latitude = '';
$longitude = '';
$uptime = $users = $cpu = $time = '';

// Basic info
$description = '';
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
    $name = trim($_POST['name'] ?? '');
    $ip   = trim($_POST['ip'] ?? '');
    $latitude = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');
    
    // Basic info
    $description = trim($_POST['description'] ?? '');
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

    if (!filter_var($ip, FILTER_VALIDATE_IP)) $errors[] = 'Valid IP is required';
    
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

    if (!$errors) {
        $sql = "UPDATE node SET name=?, ip=?, latitude=?, longitude=?, description=?, netid=?, owner_name=?, owner_email=?, 
                owner_phone=?, owner_address=?, approval_status=?, gateway=?, gateway_bit=?, hops=?, wan_iface=?, wan_ip=?, wan_mac=?, 
                wan_gateway=?, wan_bup=?, wan_bdown=?, wifi_iface=?, wifi_ip=?, wifi_mac=?, wifi_ssid=?, wifi_key=?, wifi_channel=?, 
                lan_iface=?, lan_mac=?, lan_ip=?, firmware=?, firmware_revision=? WHERE mac=?";
        
        $stmt = $dbSocket->prepare($sql);
        if (PEAR::isError($stmt)) {
            $errors[] = $stmt->getMessage();
        } else {
            $res = $stmt->execute([
                $name, $ip, $latitude, $longitude, $description, $netid, $owner_name, $owner_email,
                $owner_phone, $owner_address, $approval_status, $gateway, $gateway_bit, $hops, $wan_iface, 
                $wan_ip, $wan_mac, $wan_gateway, $wan_bup, $wan_bdown, $wifi_iface, $wifi_ip, $wifi_mac, 
                $wifi_ssid, $wifi_key, $wifi_channel, $lan_iface, $lan_mac, $lan_ip, $firmware, $firmware_revision, $pk
            ]);
            if (PEAR::isError($res)) $errors[] = $res->getMessage();
            else { header('Location: mng-nodes.php'); exit; }
        }
    }
}

// load current
$sql = "SELECT mac, name, ip, latitude, longitude, description, netid, owner_name, owner_email, owner_phone, owner_address, 
        approval_status, gateway, gateway_bit, hops, wan_iface, wan_ip, wan_mac, wan_gateway, wan_bup, wan_bdown, 
        wifi_iface, wifi_ip, wifi_mac, wifi_ssid, wifi_key, wifi_channel, lan_iface, lan_mac, lan_ip, 
        firmware, firmware_revision, uptime, users, cpu, time FROM node WHERE mac=" . $dbSocket->quoteSmart($pk);
$res = $dbSocket->query($sql);
if ($row = $res->fetchRow()) {
    list($mac, $name_db, $ip_db, $latitude_db, $longitude_db, $description_db, $netid_db, $owner_name_db, $owner_email_db, 
         $owner_phone_db, $owner_address_db, $approval_status_db, $gateway_db, $gateway_bit_db, $hops_db, $wan_iface_db, 
         $wan_ip_db, $wan_mac_db, $wan_gateway_db, $wan_bup_db, $wan_bdown_db, $wifi_iface_db, $wifi_ip_db, $wifi_mac_db, 
         $wifi_ssid_db, $wifi_key_db, $wifi_channel_db, $lan_iface_db, $lan_mac_db, $lan_ip_db, $firmware_db, 
         $firmware_revision_db, $uptime, $users, $cpu, $time) = $row;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
        $name = $name_db; 
        $ip = $ip_db; 
        $latitude = $latitude_db;
        $longitude = $longitude_db;
        $description = $description_db;
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

if ($errors) {
    echo '<div class="alert alert-danger"><ul class="mb-0">';
    foreach ($errors as $e) echo '<li>' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . '</li>';
    echo '</ul></div>';
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
                        <label class="form-label">IP Address *</label>
                        <input type="text" name="ip" class="form-control" value="<?= htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Network ID</label>
                        <input type="number" name="netid" class="form-control" value="<?= htmlspecialchars($netid, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea>
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
                        <input type="text" name="gateway" class="form-control" value="<?= htmlspecialchars($gateway, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Hops to Gateway</label>
                        <input type="number" name="hops" class="form-control" value="<?= htmlspecialchars($hops, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="gateway_bit" id="gateway_bit" <?= $gateway_bit ? 'checked' : '' ?>>
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
                        <input type="text" name="wan_iface" class="form-control" value="<?= htmlspecialchars($wan_iface, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">IP Address</label>
                        <input type="text" name="wan_ip" class="form-control" value="<?= htmlspecialchars($wan_ip, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">MAC Address</label>
                        <input type="text" name="wan_mac" class="form-control" value="<?= htmlspecialchars($wan_mac, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Gateway</label>
                        <input type="text" name="wan_gateway" class="form-control" value="<?= htmlspecialchars($wan_gateway, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Bandwidth Up</label>
                        <input type="text" name="wan_bup" class="form-control" value="<?= htmlspecialchars($wan_bup, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Bandwidth Down</label>
                        <input type="text" name="wan_bdown" class="form-control" value="<?= htmlspecialchars($wan_bdown, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
            </div>

            <h5 class="mt-4">WiFi Interface</h5>
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Interface</label>
                        <input type="text" name="wifi_iface" class="form-control" value="<?= htmlspecialchars($wifi_iface, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">IP Address</label>
                        <input type="text" name="wifi_ip" class="form-control" value="<?= htmlspecialchars($wifi_ip, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">MAC Address</label>
                        <input type="text" name="wifi_mac" class="form-control" value="<?= htmlspecialchars($wifi_mac, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">SSID</label>
                        <input type="text" name="wifi_ssid" class="form-control" value="<?= htmlspecialchars($wifi_ssid, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">WiFi Key</label>
                        <input type="password" name="wifi_key" class="form-control" value="<?= htmlspecialchars($wifi_key, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Channel</label>
                        <input type="number" name="wifi_channel" class="form-control" value="<?= htmlspecialchars($wifi_channel, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
            </div>

            <h5 class="mt-4">LAN Interface</h5>
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Interface</label>
                        <input type="text" name="lan_iface" class="form-control" value="<?= htmlspecialchars($lan_iface, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">MAC Address</label>
                        <input type="text" name="lan_mac" class="form-control" value="<?= htmlspecialchars($lan_mac, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">IP Address</label>
                        <input type="text" name="lan_ip" class="form-control" value="<?= htmlspecialchars($lan_ip, ENT_QUOTES, 'UTF-8') ?>">
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
                        <input type="text" name="firmware" class="form-control" value="<?= htmlspecialchars($firmware, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Firmware Revision</label>
                        <input type="text" name="firmware_revision" class="form-control" value="<?= htmlspecialchars($firmware_revision, ENT_QUOTES, 'UTF-8') ?>">
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
print_footer_and_html_epilogue();