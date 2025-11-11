<?php
/*
 * Debug Dashboard - Test database connection and queries
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Dashboard Debug</h1>";

try {
    // Include configuration
    include_once implode(DIRECTORY_SEPARATOR, [ __DIR__, '..', 'common', 'includes', 'config_read.php' ]);
    echo "<p>✅ Config loaded successfully</p>";
    
    // Include database connection
    include implode(DIRECTORY_SEPARATOR, [ $configValues['COMMON_INCLUDES'], 'db_open.php' ]);
    echo "<p>✅ Database connection opened</p>";
    
    // Test basic database connection
    $test_query = "SELECT 1 as test";
    $result = $dbSocket->query($test_query);
    if ($result) {
        echo "<p>✅ Database query test successful</p>";
    } else {
        echo "<p>❌ Database query test failed</p>";
    }
    
    // Include functions
    include implode(DIRECTORY_SEPARATOR, [ $configValues['OPERATORS_INCLUDE_MANAGEMENT'], 'functions.php' ]);
    echo "<p>✅ Functions loaded successfully</p>";
    
    // Test count functions
    echo "<h2>Testing Count Functions:</h2>";
    
    try {
        $total_users = count_users($dbSocket);
        echo "<p>Users count: $total_users</p>";
    } catch (Exception $e) {
        echo "<p>❌ Users count error: " . $e->getMessage() . "</p>";
    }
    
    try {
        $total_hotspots = count_hotspots($dbSocket);
        echo "<p>Hotspots count: $total_hotspots</p>";
    } catch (Exception $e) {
        echo "<p>❌ Hotspots count error: " . $e->getMessage() . "</p>";
    }
    
    try {
        $total_nas = count_nas($dbSocket);
        echo "<p>NAS count: $total_nas</p>";
    } catch (Exception $e) {
        echo "<p>❌ NAS count error: " . $e->getMessage() . "</p>";
    }
    
    try {
        $total_agents = count_agents($dbSocket);
        echo "<p>Agents count: $total_agents</p>";
    } catch (Exception $e) {
        echo "<p>❌ Agents count error: " . $e->getMessage() . "</p>";
    }
    
    // Test table existence
    echo "<h2>Testing Table Existence:</h2>";
    
    $tables_to_check = [
        'radcheck' => $configValues['CONFIG_DB_TBL_RADCHECK'],
        'userinfo' => $configValues['CONFIG_DB_TBL_DALOUSERINFO'],
        'hotspots' => $configValues['CONFIG_DB_TBL_DALOHOTSPOTS'],
        'nas' => $configValues['CONFIG_DB_TBL_RADNAS'],
        'agents' => $configValues['CONFIG_DB_TBL_DALOAGENTS'],
        'radpostauth' => $configValues['CONFIG_DB_TBL_RADPOSTAUTH'],
        'radacct' => $configValues['CONFIG_DB_TBL_RADACCT']
    ];
    
    foreach ($tables_to_check as $name => $table) {
        try {
            $sql = "SELECT COUNT(*) FROM `$table`";
            $result = $dbSocket->query($sql);
            if ($result) {
                $count = $result->fetchRow()[0];
                echo "<p>✅ Table '$name' ($table): $count rows</p>";
            } else {
                echo "<p>❌ Table '$name' ($table): Query failed</p>";
            }
        } catch (Exception $e) {
            echo "<p>❌ Table '$name' ($table): " . $e->getMessage() . "</p>";
        }
    }
    
    // Test specific queries from dashboard
    echo "<h2>Testing Dashboard Queries:</h2>";
    
    // Test radpostauth query
    try {
        $tableSetting = [
            'postauth' => [
                'user' => ($configValues['FREERADIUS_VERSION'] == '1') ? 'user' : 'username',
                'date' => ($configValues['FREERADIUS_VERSION'] == '1') ? 'date' : 'authdate'
            ]
        ];
        
        $sql = sprintf(
            "SELECT %s AS `username`, reply, %s AS `datetime` FROM %s ORDER BY `datetime` DESC LIMIT 5",
            $tableSetting['postauth']['user'], $tableSetting['postauth']['date'],
            $configValues['CONFIG_DB_TBL_RADPOSTAUTH']
        );
        
        $result = $dbSocket->query($sql);
        if ($result) {
            $numrows = $result->numRows();
            echo "<p>✅ Last connection attempts query: $numrows rows</p>";
            
            while ($row = $result->fetchRow()) {
                echo "<p>&nbsp;&nbsp;- " . htmlspecialchars($row[0]) . " | " . htmlspecialchars($row[1]) . " | " . htmlspecialchars($row[2]) . "</p>";
            }
        } else {
            echo "<p>❌ Last connection attempts query failed</p>";
        }
    } catch (Exception $e) {
        echo "<p>❌ Last connection attempts error: " . $e->getMessage() . "</p>";
    }
    
    // Close database connection
    include implode(DIRECTORY_SEPARATOR, [ $configValues['COMMON_INCLUDES'], 'db_close.php' ]);
    echo "<p>✅ Database connection closed</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Fatal error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>