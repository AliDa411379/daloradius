<?php
include("library/checklogin.php");
$login_user = $_SESSION['login_user'];

include_once('../common/includes/config_read.php');
include_once("lang/main.php");
include("../common/includes/layout.php");

// print HTML prologue
$title = "My Agent";
$help = "This page displays information about your assigned agent.";
print_html_prologue($title, $langCode);
print_title_and_help($title, $help);

echo '<div class="container-fluid">';
echo '<div class="alert alert-info">Testing agent page for user: ' . htmlspecialchars($login_user) . '</div>';

include('../common/includes/db_open.php');

// Simple query to get user ID
$sql = "SELECT id FROM userinfo WHERE username = '" . $dbSocket->escapeSimple($login_user) . "'";
$res = $dbSocket->query($sql);

if (PEAR::isError($res)) {
    echo '<div class="alert alert-danger">Database error: ' . htmlspecialchars($res->getMessage()) . '</div>';
} elseif ($res->numRows() == 0) {
    echo '<div class="alert alert-warning">User not found in database.</div>';
} else {
    $user_row = $res->fetchRow(DB_FETCHMODE_ASSOC);
    $user_id = $user_row['id'];
    
    echo '<div class="alert alert-success">User found! ID: ' . $user_id . '</div>';
    
    // Simple query to get agents
    $sql = "SELECT a.name, a.email, a.phone FROM agents a 
            INNER JOIN user_agent ua ON a.id = ua.agent_id 
            WHERE ua.user_id = " . intval($user_id) . " AND a.is_deleted = 0";
    
    $res = $dbSocket->query($sql);
    
    if (PEAR::isError($res)) {
        echo '<div class="alert alert-danger">Agent query error: ' . htmlspecialchars($res->getMessage()) . '</div>';
    } elseif ($res->numRows() == 0) {
        echo '<div class="alert alert-info">No agents assigned to this user.</div>';
    } else {
        echo '<div class="alert alert-success">Found ' . $res->numRows() . ' agent(s):</div>';
        echo '<ul>';
        while ($agent = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
            echo '<li>' . htmlspecialchars($agent['name']);
            if ($agent['email']) echo ' - ' . htmlspecialchars($agent['email']);
            if ($agent['phone']) echo ' - ' . htmlspecialchars($agent['phone']);
            echo '</li>';
        }
        echo '</ul>';
    }
}

include('../common/includes/db_close.php');
echo '</div>';

print_footer_and_html_epilogue();
?>