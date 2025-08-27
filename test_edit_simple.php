<?php
// Simple test to check what's causing the 500 error
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing edit page includes...\n";

try {
    // Test basic includes
    include("app/operators/library/checklogin.php");
    echo "✓ checklogin.php included\n";
} catch (Exception $e) {
    echo "✗ checklogin.php error: " . $e->getMessage() . "\n";
    exit;
}

// Mock session for testing
$_SESSION['operator_user'] = 'admin';

try {
    include('app/operators/library/check_operator_perm.php');
    echo "✓ check_operator_perm.php included\n";
} catch (Exception $e) {
    echo "✗ check_operator_perm.php error: " . $e->getMessage() . "\n";
}

try {
    include_once('app/common/includes/config_read.php');
    echo "✓ config_read.php included\n";
} catch (Exception $e) {
    echo "✗ config_read.php error: " . $e->getMessage() . "\n";
}

try {
    include_once("app/operators/lang/main.php");
    echo "✓ lang/main.php included\n";
} catch (Exception $e) {
    echo "✗ lang/main.php error: " . $e->getMessage() . "\n";
}

try {
    include("app/common/includes/validation.php");
    echo "✓ validation.php included\n";
} catch (Exception $e) {
    echo "✗ validation.php error: " . $e->getMessage() . "\n";
}

try {
    include("app/common/includes/layout.php");
    echo "✓ layout.php included\n";
} catch (Exception $e) {
    echo "✗ layout.php error: " . $e->getMessage() . "\n";
}

try {
    include_once("app/operators/include/management/functions.php");
    echo "✓ functions.php included\n";
} catch (Exception $e) {
    echo "✗ functions.php error: " . $e->getMessage() . "\n";
}

try {
    include('app/common/includes/db_open.php');
    echo "✓ db_open.php included\n";
} catch (Exception $e) {
    echo "✗ db_open.php error: " . $e->getMessage() . "\n";
}

echo "All includes successful!\n";
?>