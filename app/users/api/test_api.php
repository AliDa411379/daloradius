<?php
/**
 * Quick Test for API Functionality
 * This file helps diagnose API issues
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include authentication
try {
    require_once('auth.php');
    echo "✓ auth.php loaded successfully\n";
} catch (Exception $e) {
    echo "✗ auth.php failed: " . $e->getMessage() . "\n";
    exit;
}

// Include config
try {
    require_once('../../common/includes/config_read.php');
    echo "✓ config_read.php loaded successfully\n";
} catch (Exception $e) {
    echo "✗ config_read.php failed: " . $e->getMessage() . "\n";
    exit;
}

// Include db
try {
    require_once('../../common/includes/db_open.php');
    echo "✓ db_open.php loaded successfully\n";
} catch (Exception $e) {
    echo "✗ db_open.php failed: " . $e->getMessage() . "\n";
    exit;
}

// Test database connection
try {
    $sql = "SELECT COUNT(*) FROM " . $configValues['CONFIG_DB_TBL_DALOAGENTS'];
    $result = $dbSocket->query($sql);
    if (DB::isError($result)) {
        echo "✗ Database query failed: " . $result->getMessage() . "\n";
    } else {
        $count = $result->fetchRow();
        echo "✓ Database query successful: {$count[0]} agents found\n";
    }
} catch (Exception $e) {
    echo "✗ Database test failed: " . $e->getMessage() . "\n";
}

// Test BalanceManager
try {
    require_once('../../common/library/BalanceManager.php');
    echo "✓ BalanceManager.php loaded successfully\n";
} catch (Exception $e) {
    echo "✗ BalanceManager.php failed: " . $e->getMessage() . "\n";
}

echo "\nAll tests completed!\n";
