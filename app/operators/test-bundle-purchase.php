<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "Starting bundle-purchase.php...<br>";

try {
    include("library/checklogin.php");
    echo "✓ checklogin.php loaded<br>";
    
    $operator = $_SESSION['operator_user'];
    $operator_id = $_SESSION['operator_id'];
    echo "✓ Operator: $operator (ID: $operator_id)<br>";

    include('library/check_operator_perm.php');
    echo "✓ Permission check passed<br>";
    
    include_once('../common/includes/config_read.php');
    echo "✓ Config loaded<br>";
    
    include_once("library/agent_functions.php");
    echo "✓ Agent functions loaded<br>";

    echo "✓ All includes successful!<br>";
    echo "<br><a href='bundle-purchase.php'>Go to actual bundle-purchase.php</a>";
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "<br>";
    echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
}
?>
