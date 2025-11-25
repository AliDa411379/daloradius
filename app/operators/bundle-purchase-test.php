<?php
include("library/checklogin.php");
include('library/check_operator_perm.php');

// Force output
echo "TEST OUTPUT - If you see this, PHP is working!<br>";
flush();

include_once('../common/includes/config_read.php');
echo "Config loaded<br>";
flush();

include_once("lang/main.php");
echo "Lang loaded<br>";
flush();

include("../common/includes/layout.php");
echo "Layout loaded<br>";
flush();

$title = "Bundle Purchase Test";
$help = "Testing";

print_html_prologue($title, 'en');
print_title_and_help($title, $help);

echo "<h1>If you see this heading, the page is working!</h1>";
echo "<p>The issue is somewhere in the bundle-purchase.php logic</p>";

print_footer_and_html_epilogue();
?>
