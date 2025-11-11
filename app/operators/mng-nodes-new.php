<?php
/*
 * Nodes - Create
 */
include_once implode(DIRECTORY_SEPARATOR, [ __DIR__, '..', 'common', 'includes', 'config_read.php' ]);
include implode(DIRECTORY_SEPARATOR, [ $configValues['OPERATORS_LIBRARY'], 'checklogin.php' ]);
$operator = $_SESSION['operator_user'];
include implode(DIRECTORY_SEPARATOR, [ $configValues['OPERATORS_LIBRARY'], 'check_operator_perm.php' ]);
// ACL key: mng_nodes_new
include_once implode(DIRECTORY_SEPARATOR, [ $configValues['OPERATORS_LANG'], 'main.php' ]);
include implode(DIRECTORY_SEPARATOR, [ $configValues['COMMON_INCLUDES'], 'layout.php' ]);
include implode(DIRECTORY_SEPARATOR, [ $configValues['COMMON_INCLUDES'], 'db_open.php' ]);
include_once implode(DIRECTORY_SEPARATOR, [ $configValues['COMMON_INCLUDES'], 'validation.php' ]);

// init logging variables
$log = "visited page: ";
$logAction = "";
$logDebugSQL = "";

$title = 'New Node';
print_html_prologue($title, $langCode);
print_title_and_help($title, 'Create a new node.');

$errors = [];
$successMsg = '';
$failureMsg = '';
$mac = trim($_POST['mac'] ?? '');
$name = trim($_POST['name'] ?? '');
$ip = trim($_POST['ip'] ?? '');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!dalo_check_csrf_token($_POST['csrf_token'] ?? '')) {
        $failureMsg = 'CSRF token error';
        $logAction .= "$failureMsg on page: ";
    } else {
        if (empty($mac) || !preg_match('/^[0-9A-Fa-f:.-]{12,20}$/', $mac)) {
            $errors[] = 'Valid MAC is required';
        }
        
        // allow IP or CIDR
        $validate_ip_or_cidr = function($value) {
            $value = trim($value);
            if ($value === '') return false;
            if (strpos($value, '/') !== false) {
                list($base, $prefix) = explode('/', $value, 2);
                if (!filter_var($base, FILTER_VALIDATE_IP)) return false;
                if ($prefix === '' || preg_match('/^\d+$/', $prefix) !== 1) return false;
                $prefix = (int)$prefix;
                if (filter_var($base, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return $prefix >= 0 && $prefix <= 32;
                if (filter_var($base, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) return $prefix >= 0 && $prefix <= 128;
                return false;
            }
            return filter_var($value, FILTER_VALIDATE_IP) !== false;
        };

        if (empty($ip) || !$validate_ip_or_cidr($ip)) {
            $errors[] = 'Valid IP is required';
        }
        
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
            $logAction .= "Failed creating new node due to validation errors on page: ";
        }
        
        if (!$errors) {
            // Use direct SQL approach like other mng edit files to avoid prepared statement issues
            $sql = sprintf("INSERT INTO node (mac, name, ip, latitude, longitude, description, type, netid, owner_name, owner_email, 
                    owner_phone, owner_address, approval_status, gateway, gateway_bit, hops, wan_iface, wan_ip, wan_mac, 
                    wan_gateway, wan_bup, wan_bdown, wifi_iface, wifi_ip, wifi_mac, wifi_ssid, wifi_key, wifi_channel, 
                    lan_iface, lan_mac, lan_ip, firmware, firmware_revision, uptime, users, cpu, time) 
                    VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', %d, '%s', '%s', '%s', '%s', 
                    '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', 0, 0, 0, NOW())",
                    $dbSocket->escapeSimple($mac), $dbSocket->escapeSimple($name), $dbSocket->escapeSimple($ip), 
                    $dbSocket->escapeSimple($latitude), $dbSocket->escapeSimple($longitude), $dbSocket->escapeSimple($description), 
                    $dbSocket->escapeSimple($type), $dbSocket->escapeSimple($netid), $dbSocket->escapeSimple($owner_name), 
                    $dbSocket->escapeSimple($owner_email), $dbSocket->escapeSimple($owner_phone), $dbSocket->escapeSimple($owner_address), 
                    $dbSocket->escapeSimple($approval_status), $dbSocket->escapeSimple($gateway), $gateway_bit, 
                    $dbSocket->escapeSimple($hops), $dbSocket->escapeSimple($wan_iface), $dbSocket->escapeSimple($wan_ip), 
                    $dbSocket->escapeSimple($wan_mac), $dbSocket->escapeSimple($wan_gateway), $dbSocket->escapeSimple($wan_bup), 
                    $dbSocket->escapeSimple($wan_bdown), $dbSocket->escapeSimple($wifi_iface), $dbSocket->escapeSimple($wifi_ip), 
                    $dbSocket->escapeSimple($wifi_mac), $dbSocket->escapeSimple($wifi_ssid), $dbSocket->escapeSimple($wifi_key), 
                    $dbSocket->escapeSimple($wifi_channel), $dbSocket->escapeSimple($lan_iface), $dbSocket->escapeSimple($lan_mac), 
                    $dbSocket->escapeSimple($lan_ip), $dbSocket->escapeSimple($firmware), $dbSocket->escapeSimple($firmware_revision));
            
            $res = $dbSocket->query($sql);
            $logDebugSQL .= "$sql;\n";
            
            if (PEAR::isError($res)) {
                $failureMsg = sprintf("Failed to create node [%s]: %s", htmlspecialchars($mac, ENT_QUOTES, 'UTF-8'), $res->getMessage());
                $logAction .= "Failed creating new node [$mac] on page: ";
            } else {
                $successMsg = sprintf("Successfully created node: <strong>%s</strong>", htmlspecialchars($mac, ENT_QUOTES, 'UTF-8'));
                $logAction .= "Successfully created new node [$mac] on page: ";
                // Clear form data after successful creation
                $mac = $name = $ip = $latitude = $longitude = $description = $type = $netid = '';
                $owner_name = $owner_email = $owner_phone = $owner_address = '';
                $approval_status = 'P';
                $gateway = $hops = $wan_iface = $wan_ip = $wan_mac = $wan_gateway = $wan_bup = $wan_bdown = '';
                $wifi_iface = $wifi_ip = $wifi_mac = $wifi_ssid = $wifi_key = $wifi_channel = '';
                $lan_iface = $lan_mac = $lan_ip = $firmware = $firmware_revision = '';
            }
        }
    } // end CSRF validation else
}

include_once('include/management/actionMessages.php');

if (!empty($successMsg)) {
    echo '<script>
        setTimeout(function() {
            if (confirm("Node created successfully! Click OK to go to the nodes list, or Cancel to create another node.")) {
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
</ul>

<form method="POST" class="mt-3" action="">
    <input type="hidden" name="csrf_token" value="<?= dalo_csrf_token() ?>">
    
    <!-- Tab content -->
    <div class="tab-content" id="nodeTabContent">
        <!-- Basic Info Tab -->
        <div class="tab-pane fade show active" id="basic" role="tabpanel">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">MAC Address *</label>
                        <input type="text" name="mac" class="form-control" value="<?= htmlspecialchars($mac, ENT_QUOTES, 'UTF-8') ?>" required>
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
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-primary">Create Node</button>
        <a href="mng-nodes.php" class="btn btn-secondary">Cancel</a>
    </div>
</form>
<?php
include implode(DIRECTORY_SEPARATOR, [ $configValues['COMMON_INCLUDES'], 'db_close.php' ]);
include implode(DIRECTORY_SEPARATOR, [ $configValues['OPERATORS_LIBRARY'], 'logging.php' ]);
print_footer_and_html_epilogue();