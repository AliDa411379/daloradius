<?php
// Minimal test to isolate the issue
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting test...<br>";

try {
    echo "1. Testing session start...<br>";
    include("library/sessions.php");
    dalo_session_start();
    echo "✅ Session started<br>";
    
    echo "2. Testing checklogin...<br>";
    include("library/checklogin.php");
    echo "✅ Checklogin passed<br>";
    
    if (isset($_SESSION['operator_user'])) {
        echo "✅ Operator user: " . $_SESSION['operator_user'] . "<br>";
    } else {
        echo "❌ No operator_user in session<br>";
    }
    
    echo "3. Testing config read...<br>";
    include_once('../common/includes/config_read.php');
    echo "✅ Config read<br>";
    
    echo "4. Testing permission check...<br>";
    // Let's manually do what check_operator_perm.php does
    $file = str_replace("-", "_", basename('mng-agent-new.php', ".php"));
    echo "File name for permission: $file<br>";
    
    include('../common/includes/db_open.php');
    
    if (isset($_SESSION['operator_id'])) {
        $sql = sprintf("SELECT access FROM %s WHERE operator_id=%d AND file='%s'",
                       $configValues['CONFIG_DB_TBL_DALOOPERATORS_ACL'], $_SESSION['operator_id'], $file);
        echo "SQL: $sql<br>";
        
        $access = intval($dbSocket->getOne($sql)) === 1;
        echo "Permission result: " . ($access ? 'GRANTED' : 'DENIED') . "<br>";
        
        if (!$access) {
            echo "❌ Permission denied - this would cause redirect<br>";
        } else {
            echo "✅ Permission granted<br>";
        }
    } else {
        echo "❌ No operator_id in session<br>";
    }
    
    include('../common/includes/db_close.php');
    
    echo "5. Testing language files...<br>";
    include_once("lang/main.php");
    echo "✅ Language loaded<br>";
    
    echo "6. Testing layout...<br>";
    include("../common/includes/layout.php");
    echo "✅ Layout loaded<br>";
    
    echo "<h2>✅ All basic components loaded successfully!</h2>";
    echo "<p>The issue might be in the form rendering or page content generation.</p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Stack trace: " . $e->getTraceAsString() . "<br>";
} catch (Error $e) {
    echo "❌ Fatal Error: " . $e->getMessage() . "<br>";
    echo "Stack trace: " . $e->getTraceAsString() . "<br>";
}

?>