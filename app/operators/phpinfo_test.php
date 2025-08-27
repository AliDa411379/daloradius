<?php
echo "PHP is working!<br>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Current directory: " . getcwd() . "<br>";
echo "Script name: " . $_SERVER['SCRIPT_NAME'] . "<br>";

// Test if we can include files
if (file_exists("library/sessions.php")) {
    echo "✅ sessions.php exists<br>";
} else {
    echo "❌ sessions.php missing<br>";
}

if (file_exists("library/checklogin.php")) {
    echo "✅ checklogin.php exists<br>";
} else {
    echo "❌ checklogin.php missing<br>";
}

phpinfo();
?>