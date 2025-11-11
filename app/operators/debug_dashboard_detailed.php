<?php
/*
 * Detailed Dashboard Debug - Test the exact dashboard logic
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Detailed Dashboard Debug</h1>";

try {
    // Include all the same files as the real dashboard
    include_once implode(DIRECTORY_SEPARATOR, [ __DIR__, '..', 'common', 'includes', 'config_read.php' ]);
    
    // Mock session data since we're not logged in
    session_start();
    $_SESSION['operator_user'] = 'debug_user';
    $_SESSION['operator_id'] = 1;
    
    include implode(DIRECTORY_SEPARATOR, [ $configValues['COMMON_INCLUDES'], 'db_open.php' ]);
    include implode(DIRECTORY_SEPARATOR, [ $configValues['OPERATORS_INCLUDE_MANAGEMENT'], 'functions.php' ]);
    include_once implode(DIRECTORY_SEPARATOR, [ $configValues['OPERATORS_LIBRARY'], 'agent_functions.php' ]);
    
    echo "<p>✅ All includes loaded</p>";
    
    // Test the exact logic from dashboard
    $operator = $_SESSION['operator_user'];
    $operator_id = $_SESSION['operator_id'];
    
    // Test agent function
    try {
        $is_current_operator_agent = isCurrentOperatorAgent($dbSocket, $operator_id, $configValues);
        echo "<p>Is current operator agent: " . ($is_current_operator_agent ? 'YES' : 'NO') . "</p>";
    } catch (Exception $e) {
        echo "<p>❌ Agent check error: " . $e->getMessage() . "</p>";
        $is_current_operator_agent = false;
    }
    
    // Test count functions exactly as dashboard does
    echo "<h2>Testing Dashboard Count Logic:</h2>";
    
    try {
        $total_users = $is_current_operator_agent
            ? count_users($dbSocket, true, $operator)
            : count_users($dbSocket);
        echo "<p>✅ Total users: $total_users</p>";
    } catch (Exception $e) {
        echo "<p>❌ Users count error: " . $e->getMessage() . "</p>";
        $total_users = 0;
    }
    
    try {
        $total_hotspots = count_hotspots($dbSocket);
        echo "<p>✅ Total hotspots: $total_hotspots</p>";
    } catch (Exception $e) {
        echo "<p>❌ Hotspots count error: " . $e->getMessage() . "</p>";
        $total_hotspots = 0;
    }
    
    try {
        $total_nas = count_nas($dbSocket);
        echo "<p>✅ Total NAS: $total_nas</p>";
    } catch (Exception $e) {
        echo "<p>❌ NAS count error: " . $e->getMessage() . "</p>";
        $total_nas = 0;
    }
    
    try {
        $total_agents = count_agents($dbSocket);
        echo "<p>✅ Total agents: $total_agents</p>";
    } catch (Exception $e) {
        echo "<p>❌ Agents count error: " . $e->getMessage() . "</p>";
        $total_agents = 0;
    }
    
    // Test the problematic count_nodes function
    try {
        $total_nodes = count_nodes($dbSocket);
        echo "<p>✅ Total nodes: $total_nodes</p>";
    } catch (Exception $e) {
        echo "<p>❌ Nodes count error: " . $e->getMessage() . "</p>";
        $total_nodes = 0;
    }
    
    // Test card generation
    echo "<h2>Testing Card Generation:</h2>";
    
    function generateCard($title, $total, $linkText, $linkURL, $bgColor, $icon) {
        return <<<HTML
<div class="col-md-4 m-0 p-0">
    <div class="card m-1 rounded-0">
        <div class="row g-0">
            <div class="d-none d-md-flex col-md-2 text-bg-{$bgColor} align-items-center justify-content-center" style="--bs-bg-opacity: .9;">
                <i class="bi bi-{$icon} fs-2"></i>
            </div>
            <div class="col-md-10 p-1 d-flex align-items-center justify-content-center flex-column text-bg-{$bgColor}">
                <h5 class="card-title">{$title}</h5>
                <p class="card-text">{$total}</p>
                <a href="{$linkURL}" class="btn btn-light btn-sm">{$linkText}</a>
            </div>
        </div>
    </div>
</div>
HTML;
    }
    
    // Mock translation function
    function t($category, $key) {
        $translations = [
            'submenu' => [
                'Users' => 'Users',
                'Nas' => 'NAS',
                'Hotspots' => 'Hotspots',
                'Agents' => 'Agents'
            ],
            'all' => [
                'Total' => 'Total'
            ]
        ];
        return $translations[$category][$key] ?? $key;
    }
    
    // Test card parameters exactly as dashboard
    $card_params = [
        [
            "title" => t('submenu', 'Users'),
            "total" => sprintf("%s: <strong>%d</strong>", t('all', 'Total'), $total_users),
            "linkText" => "Go to users list",
            "linkURL" => "mng-list-all.php",
            "bgColor" => "success",
            "icon" => "people-fill"
        ],
        [
            "title" => t('submenu', 'Nas'),
            "total" => sprintf("%s: <strong>%d</strong>", t('all', 'Total'), $total_nas),
            "linkText" => "Go to NAS list",
            "linkURL" => "mng-rad-nas-list.php",
            "bgColor" => "danger",
            "icon" => "router-fill"
        ],
        [
            "title" => t('submenu', 'Hotspots'),
            "total" => sprintf("%s: <strong>%d</strong>", t('all', 'Total'), $total_hotspots),
            "linkText" => "Go to hotspots list",
            "linkURL" => "mng-hs-list.php",
            "bgColor" => "primary",
            "icon" => "wifi"
        ],
        [
            "title" => t('submenu', 'Agents'),
            "total" => sprintf("%s: <strong>%d</strong>", t('all', 'Total'), $total_agents),
            "linkText" => "Go to agents list",
            "linkURL" => "mng-agents-list.php",
            "bgColor" => "warning",
            "icon" => "person-badge-fill"
        ]
    ];
    
    echo "<h3>Generated Cards HTML:</h3>";
    echo '<div style="border: 1px solid #ccc; padding: 10px; background: #f9f9f9;">';
    
    if ($is_current_operator_agent) {
        echo "<p><strong>Agent Mode - Only Users Card:</strong></p>";
        echo generateCard(
            t('submenu', 'Users'),
            sprintf("%s: <strong>%d</strong>", t('all', 'Total'), $total_users),
            "Go to users list",
            "mng-list-all.php",
            "success",
            "people-fill"
        );
    } else {
        echo "<p><strong>Full Mode - All Cards:</strong></p>";
        foreach ($card_params as $params) {
            echo generateCard($params["title"], $params["total"], $params["linkText"], $params["linkURL"], $params["bgColor"], $params["icon"]);
        }
        // Add Nodes card
        echo generateCard(
            'Nodes',
            sprintf('%s: <strong>%d</strong>', t('all','Total'), $total_nodes),
            'Go to nodes list',
            'mng-nodes-list.php',
            'secondary',
            'server'
        );
    }
    
    echo '</div>';
    
    include implode(DIRECTORY_SEPARATOR, [ $configValues['COMMON_INCLUDES'], 'db_close.php' ]);
    
} catch (Exception $e) {
    echo "<p>❌ Fatal error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>