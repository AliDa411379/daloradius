<?php
// Debug form rendering step by step
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Debug Form Rendering</h2>";

try {
    echo "1. Starting session and login check...<br>";
    include("library/checklogin.php");
    $operator = $_SESSION['operator_user'];
    echo "✅ Login check passed<br>";

    echo "2. Permission check...<br>";
    include('library/check_operator_perm.php');
    echo "✅ Permission check passed<br>";

    echo "3. Config and language...<br>";
    include_once('../common/includes/config_read.php');
    include_once("lang/main.php");
    include_once("../common/includes/validation.php");
    include("../common/includes/layout.php");
    echo "✅ Basic includes loaded<br>";

    echo "4. Testing title and help...<br>";
    $title = t('Intro','mngagentsnew.php');
    $help = t('helpPage','mngagentsnew');
    echo "Title: '$title'<br>";
    echo "Help: '$help'<br>";

    echo "5. Testing database connection...<br>";
    include("../common/includes/db_open.php");
    echo "✅ Database connected<br>";

    echo "6. Testing pages_common include...<br>";
    include('include/management/pages_common.php');
    echo "✅ pages_common loaded<br>";

    echo "7. Testing print_html_prologue function...<br>";
    if (function_exists('print_html_prologue')) {
        echo "✅ print_html_prologue exists<br>";
    } else {
        echo "❌ print_html_prologue missing<br>";
    }

    echo "8. Testing print_header_and_footer function...<br>";
    if (function_exists('print_header_and_footer')) {
        echo "✅ print_header_and_footer exists<br>";
    } else {
        echo "❌ print_header_and_footer missing<br>";
    }

    echo "9. Testing form functions...<br>";
    $form_functions = ['open_form', 'close_form', 'open_fieldset', 'close_fieldset', 'print_form_component'];
    foreach ($form_functions as $func) {
        if (function_exists($func)) {
            echo "✅ $func exists<br>";
        } else {
            echo "❌ $func missing<br>";
        }
    }

    echo "10. Testing CSRF token function...<br>";
    if (function_exists('dalo_csrf_token')) {
        echo "✅ dalo_csrf_token exists<br>";
        $token = dalo_csrf_token();
        echo "Token generated: " . substr($token, 0, 10) . "...<br>";
    } else {
        echo "❌ dalo_csrf_token missing<br>";
    }

    echo "11. Testing translation functions...<br>";
    $test_translations = [
        "t('all','Name')" => t('all','Name'),
        "t('all','Company')" => t('all','Company'),
        "t('all','Email')" => t('all','Email'),
        "t('buttons','apply')" => t('buttons','apply')
    ];
    
    foreach ($test_translations as $call => $result) {
        if ($result === "Lang Error!") {
            echo "❌ $call = '$result'<br>";
        } else {
            echo "✅ $call = '$result'<br>";
        }
    }

    echo "<h3>✅ All components seem to be working!</h3>";
    echo "<p>The issue might be in the actual form rendering logic.</p>";

    include('../common/includes/db_close.php');

} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>";
    echo "Stack trace: " . $e->getTraceAsString() . "<br>";
} catch (Error $e) {
    echo "❌ Fatal Error: " . $e->getMessage() . "<br>";
    echo "Stack trace: " . $e->getTraceAsString() . "<br>";
}

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
</style>