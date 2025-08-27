<?php
/**
 * Simple session check
 */

session_start();

echo "<h2>Session Debug</h2>\n";
echo "<h3>All Session Variables:</h3>\n";

if (empty($_SESSION)) {
    echo "<p><strong>❌ No session variables found!</strong></p>\n";
    echo "<p>This means you're not logged in or session is not working.</p>\n";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr style='background-color: #f2f2f2;'><th>Variable</th><th>Value</th></tr>\n";
    
    foreach ($_SESSION as $key => $value) {
        $display_value = is_array($value) ? print_r($value, true) : $value;
        echo "<tr><td>$key</td><td>" . htmlspecialchars($display_value) . "</td></tr>\n";
    }
    echo "</table>\n";
}

echo "<h3>Session Info:</h3>\n";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>\n";
echo "<p><strong>Session Status:</strong> " . session_status() . " (1=disabled, 2=active, 3=none)</p>\n";

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
</style>

<p><a href="app/operators/">→ Go to operators login</a></p>