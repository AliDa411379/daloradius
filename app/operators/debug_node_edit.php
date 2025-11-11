<?php
/*
 * Debug version of Node Edit to identify save issues
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Node Edit Debug Information</h2>";

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>POST Data Received:</h3>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    // Check specific fields you want to edit
    $fields_to_check = ['owner_name', 'owner_email', 'owner_phone', 'owner_address', 'latitude', 'longitude', 'type', 'description'];
    echo "<h3>Fields You Want to Edit:</h3>";
    foreach ($fields_to_check as $field) {
        $value = trim($_POST[$field] ?? '');
        echo "$field: '" . htmlspecialchars($value) . "'<br>";
    }
    
    echo "<h3>MAC Address (Primary Key):</h3>";
    $pk = trim($_POST['mac'] ?? '');
    echo "MAC: '" . htmlspecialchars($pk) . "'<br>";
    
    if (empty($pk)) {
        echo "<strong style='color: red;'>ERROR: MAC address is empty!</strong><br>";
    }
} else {
    echo "<p>No POST data - this is a GET request</p>";
    
    // Check GET parameters
    $pk = trim($_GET['mac'] ?? '');
    echo "<h3>MAC from GET:</h3>";
    echo "MAC: '" . htmlspecialchars($pk) . "'<br>";
}

// Include necessary files to test database connection
try {
    include_once implode(DIRECTORY_SEPARATOR, [ __DIR__, 'app', 'common', 'includes', 'config_read.php' ]);
    echo "<h3>✓ Config loaded successfully</h3>";
    
    include implode(DIRECTORY_SEPARATOR, [ $configValues['COMMON_INCLUDES'], 'db_open.php' ]);
    echo "<h3>✓ Database connection established</h3>";
    
    // Test if the node exists
    if (!empty($pk)) {
        $sql = "SELECT mac, name, owner_name, owner_email, latitude, longitude, type, description FROM node WHERE mac=" . $dbSocket->quoteSmart($pk);
        echo "<h3>Testing Node Query:</h3>";
        echo "SQL: " . htmlspecialchars($sql) . "<br>";
        
        $res = $dbSocket->query($sql);
        if (PEAR::isError($res)) {
            echo "<strong style='color: red;'>Query Error: " . $res->getMessage() . "</strong><br>";
        } else {
            $row = $res->fetchRow();
            if ($row) {
                echo "<h3>✓ Node found in database:</h3>";
                echo "<pre>";
                print_r($row);
                echo "</pre>";
            } else {
                echo "<strong style='color: red;'>ERROR: Node not found in database!</strong><br>";
            }
        }
    }
    
    // If this is a POST request, test the UPDATE query
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($pk)) {
        echo "<h3>Testing UPDATE Query:</h3>";
        
        // Get the values
        $owner_name = trim($_POST['owner_name'] ?? '');
        $owner_email = trim($_POST['owner_email'] ?? '');
        $owner_phone = trim($_POST['owner_phone'] ?? '');
        $owner_address = trim($_POST['owner_address'] ?? '');
        $latitude = trim($_POST['latitude'] ?? '');
        $longitude = trim($_POST['longitude'] ?? '');
        $type = trim($_POST['type'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        // Simple update query for just the fields you want to edit
        $sql = "UPDATE node SET owner_name=?, owner_email=?, owner_phone=?, owner_address=?, latitude=?, longitude=?, type=?, description=? WHERE mac=?";
        echo "SQL: " . htmlspecialchars($sql) . "<br>";
        
        $stmt = $dbSocket->prepare($sql);
        if (PEAR::isError($stmt)) {
            echo "<strong style='color: red;'>Prepare Error: " . $stmt->getMessage() . "</strong><br>";
        } else {
            echo "✓ Statement prepared successfully<br>";
            
            $params = [$owner_name, $owner_email, $owner_phone, $owner_address, $latitude, $longitude, $type, $description, $pk];
            echo "<h4>Parameters:</h4>";
            echo "<pre>";
            print_r($params);
            echo "</pre>";
            
            $res = $stmt->execute($params);
            if (PEAR::isError($res)) {
                echo "<strong style='color: red;'>Execute Error: " . $res->getMessage() . "</strong><br>";
            } else {
                echo "<strong style='color: green;'>✓ UPDATE executed successfully!</strong><br>";
                
                // Check if any rows were affected
                $affected = $dbSocket->affectedRows();
                echo "Affected rows: $affected<br>";
                
                if ($affected == 0) {
                    echo "<strong style='color: orange;'>WARNING: No rows were updated. This could mean the MAC address doesn't exist or the values are the same.</strong><br>";
                }
            }
        }
    }
    
} catch (Exception $e) {
    echo "<strong style='color: red;'>Exception: " . $e->getMessage() . "</strong><br>";
}

echo "<hr>";
echo "<h3>Test Form (Only for the fields you want to edit):</h3>";

// Get the MAC from GET or POST
$mac = trim($_GET['mac'] ?? $_POST['mac'] ?? '');

?>
<form method="POST" action="">
    <input type="hidden" name="mac" value="<?= htmlspecialchars($mac) ?>">
    
    <h4>Owner Information:</h4>
    <label>Owner Name: <input type="text" name="owner_name" value="<?= htmlspecialchars($_POST['owner_name'] ?? '') ?>"></label><br><br>
    <label>Owner Email: <input type="email" name="owner_email" value="<?= htmlspecialchars($_POST['owner_email'] ?? '') ?>"></label><br><br>
    <label>Owner Phone: <input type="text" name="owner_phone" value="<?= htmlspecialchars($_POST['owner_phone'] ?? '') ?>"></label><br><br>
    <label>Owner Address: <textarea name="owner_address"><?= htmlspecialchars($_POST['owner_address'] ?? '') ?></textarea></label><br><br>
    
    <h4>Location:</h4>
    <label>Latitude: <input type="text" name="latitude" value="<?= htmlspecialchars($_POST['latitude'] ?? '') ?>"></label><br><br>
    <label>Longitude: <input type="text" name="longitude" value="<?= htmlspecialchars($_POST['longitude'] ?? '') ?>"></label><br><br>
    
    <h4>Node Info:</h4>
    <label>Type: 
        <select name="type">
            <option value="">Select Type</option>
            <option value="point to point" <?= ($_POST['type'] ?? '') === 'point to point' ? 'selected' : '' ?>>Point to Point</option>
            <option value="sector" <?= ($_POST['type'] ?? '') === 'sector' ? 'selected' : '' ?>>Sector</option>
            <option value="nas" <?= ($_POST['type'] ?? '') === 'nas' ? 'selected' : '' ?>>NAS</option>
        </select>
    </label><br><br>
    <label>Description: <textarea name="description"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea></label><br><br>
    
    <button type="submit">Test Save</button>
</form>

<p><strong>Instructions:</strong></p>
<ol>
    <li>First, access this file with a MAC parameter: <code>debug_node_edit.php?mac=YOUR_MAC_ADDRESS</code></li>
    <li>Fill in the form with test data</li>
    <li>Click "Test Save" to see what happens</li>
</ol>