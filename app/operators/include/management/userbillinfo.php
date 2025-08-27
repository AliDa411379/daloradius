<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Copyright (C) 2007 - Liran Tal <liran@lirantal.com> All Rights Reserved.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *********************************************************************************************************
 *
 * Description:    provides user agent selection input fields (replaced billing info)
 *
 * Authors:        Liran Tal <liran@lirantal.com>
 *                 Filippo Lauria <filippo.lauria@iit.cnr.it>
 *
 *********************************************************************************************************
 */

// prevent this file to be directly accessed
if (strpos($_SERVER['PHP_SELF'], '/include/management/userbillinfo.php') !== false) {
    header('Location: ../../index.php');
    exit;
}

// Check if current operator is an agent
include_once('library/agent_functions.php');
$operator_id = $_SESSION['operator_id'];
$current_agent_id = getCurrentOperatorAgentId($dbSocket, $operator_id, $configValues);
$is_current_operator_agent = ($current_agent_id !== null);

// Get available agents from database (using existing connection)
$sql = sprintf("SELECT id, name, company FROM %s WHERE is_deleted = 0 ORDER BY name", $configValues['CONFIG_DB_TBL_DALOAGENTS']);
$res = $dbSocket->query($sql);

$agent_options = array();
if (!$is_current_operator_agent) {
    $agent_options[''] = 'Select Agent...';
}

while ($row = $res->fetchRow()) {
    $agent_id = $row[0];
    $agent_name = $row[1];
    $agent_company = $row[2];
    $display_name = $agent_name . ($agent_company ? " ({$agent_company})" : "");
    
    // If current operator is an agent, only show their own agent
    if ($is_current_operator_agent) {
        if ($agent_id == $current_agent_id) {
            $agent_options[$agent_id] = $display_name;
        }
    } else {
        $agent_options[$agent_id] = $display_name;
    }
}

// Agent selection will be rendered as custom HTML below

// Function to generate smooth checkbox-based agent selection
function generate_agent_checkboxes($agent_options, $selected_agents = array()) {
    // Add custom CSS for smooth interactions
    $html = '<style>
    .agent-selection-container {
        transition: all 0.3s ease;
    }
    .agent-item {
        padding: 8px 12px;
        margin: 2px 0;
        border-radius: 4px;
        transition: all 0.3s ease;
    }
    .agent-item:hover {
        background-color: #e9ecef;
        transform: translateX(2px);
    }
    
    /* Special styling for currently assigned agents */
    .agent-item.bg-light {
        background-color: #d1e7dd !important;
        border: 2px solid #198754 !important;
        box-shadow: 0 2px 8px rgba(25, 135, 84, 0.2);
        margin: 4px 0;
    }
    
    .agent-item.bg-light:hover {
        background-color: #c3e6cb !important;
        transform: translateX(3px);
        box-shadow: 0 4px 12px rgba(25, 135, 84, 0.3);
    }
    
    .agent-item .form-check-input:checked + .form-check-label {
        font-weight: 600;
        color: #198754;
    }
    
    .agent-checkbox {
        margin-right: 8px;
    }
    
    #agent-search {
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    #agent-search:focus {
        border-color: #86b7fe;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
    
    .btn-outline-primary:hover, .btn-outline-secondary:hover {
        transform: translateY(-1px);
        transition: transform 0.2s ease;
    }
    
    /* Badge styling for "Currently Assigned" */
    .badge.bg-success {
        font-size: 0.65em;
        padding: 0.25em 0.5em;
        animation: fadeIn 0.5s ease-in;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: scale(0.8); }
        to { opacity: 1; transform: scale(1); }
    }
    
    /* Icon styling */
    .bi-check-circle-fill {
        animation: bounceIn 0.6s ease-out;
    }
    
    @keyframes bounceIn {
        0% { transform: scale(0); }
        50% { transform: scale(1.2); }
        100% { transform: scale(1); }
    }
    </style>';
    
    $html .= '<div class="agent-selection-container" style="max-height: 350px; overflow-y: auto; border: 1px solid #ddd; border-radius: 6px; padding: 15px; background-color: #f8f9fa;">';
    
    // Show current assignment status
    $assigned_count = count($selected_agents);
    $total_count = count($agent_options) - 1; // Subtract 1 for the empty option
    
    if ($assigned_count > 0) {
        $html .= '<div class="alert alert-success alert-sm mb-3" role="alert">';
        $html .= '<i class="bi bi-info-circle me-1"></i>';
        $html .= '<strong>Current Status:</strong> ' . $assigned_count . ' agent(s) currently assigned to this user';
        $html .= '</div>';
    } else {
        $html .= '<div class="alert alert-warning alert-sm mb-3" role="alert">';
        $html .= '<i class="bi bi-exclamation-triangle me-1"></i>';
        $html .= '<strong>No agents assigned</strong> - Select agents below to assign them to this user';
        $html .= '</div>';
    }
    
    // Search box for filtering
    $html .= '<div class="mb-3">';
    $html .= '<input type="text" id="agent-search" class="form-control form-control-sm" placeholder="Search agents..." onkeyup="filterAgents()">';
    $html .= '</div>';
    
    // Select All / Deselect All buttons
    $html .= '<div class="mb-3">';
    $html .= '<button type="button" class="btn btn-sm btn-outline-primary me-2" onclick="selectAllAgents()">Select All</button>';
    $html .= '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllAgents()">Deselect All</button>';
    $html .= '</div>';
    
    // Agent checkboxes
    $html .= '<div id="agent-list">';
    foreach ($agent_options as $agent_id => $agent_name) {
        if (empty($agent_id)) continue; // Skip the "Select Agent..." option
        
        $is_selected = in_array($agent_id, $selected_agents);
        $checked = $is_selected ? 'checked' : '';
        
        // Add visual styling for pre-selected agents
        $item_class = 'form-check agent-item';
        $label_class = 'form-check-label';
        if ($is_selected) {
            $item_class .= ' bg-light border border-success rounded p-2 mb-1';
            $label_class .= ' fw-bold text-success';
        }
        
        $html .= '<div class="' . $item_class . '" data-agent-name="' . strtolower($agent_name) . '">';
        $html .= '<input class="form-check-input agent-checkbox" type="checkbox" name="selected_agents[]" value="' . $agent_id . '" id="agent_' . $agent_id . '" ' . $checked . '>';
        $html .= '<label class="' . $label_class . '" for="agent_' . $agent_id . '">';
        
        if ($is_selected) {
            $html .= '<i class="bi bi-check-circle-fill text-success me-1"></i>';
        }
        
        $html .= htmlspecialchars($agent_name);
        
        if ($is_selected) {
            $html .= ' <small class="badge bg-success ms-1">Currently Assigned</small>';
        }
        
        $html .= '</label>';
        $html .= '</div>';
    }
    $html .= '</div>';
    
    $html .= '</div>';
    
    // JavaScript for smooth interactions
    $html .= '
    <script>
    function filterAgents() {
        const searchTerm = document.getElementById("agent-search").value.toLowerCase();
        const agentItems = document.querySelectorAll(".agent-item");
        let visibleCount = 0;
        
        agentItems.forEach(function(item) {
            const agentName = item.getAttribute("data-agent-name");
            if (agentName.includes(searchTerm)) {
                item.style.display = "block";
                item.style.opacity = "1";
                visibleCount++;
            } else {
                item.style.display = "none";
                item.style.opacity = "0";
            }
        });
        
        // Show/hide "no results" message
        let noResultsMsg = document.getElementById("no-results-msg");
        if (visibleCount === 0 && searchTerm.length > 0) {
            if (!noResultsMsg) {
                noResultsMsg = document.createElement("div");
                noResultsMsg.id = "no-results-msg";
                noResultsMsg.className = "text-muted text-center py-3";
                noResultsMsg.innerHTML = "<em>No agents found matching your search</em>";
                document.getElementById("agent-list").appendChild(noResultsMsg);
            }
            noResultsMsg.style.display = "block";
        } else if (noResultsMsg) {
            noResultsMsg.style.display = "none";
        }
    }
    
    function selectAllAgents() {
        const visibleCheckboxes = document.querySelectorAll(".agent-item:not([style*=\"display: none\"]) .agent-checkbox");
        visibleCheckboxes.forEach(function(checkbox) {
            checkbox.checked = true;
            // Add visual feedback
            checkbox.parentElement.style.backgroundColor = "#e7f3ff";
            setTimeout(() => {
                checkbox.parentElement.style.backgroundColor = "";
            }, 300);
        });
        updateSelectedCount();
    }
    
    function deselectAllAgents() {
        const checkboxes = document.querySelectorAll(".agent-checkbox");
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = false;
            // Add visual feedback
            checkbox.parentElement.style.backgroundColor = "#ffe7e7";
            setTimeout(() => {
                checkbox.parentElement.style.backgroundColor = "";
            }, 300);
        });
        updateSelectedCount();
    }
    
    function updateSelectedCount() {
        const selectedCount = document.querySelectorAll(".agent-checkbox:checked").length;
        const totalCount = document.querySelectorAll(".agent-checkbox").length;
        const searchBox = document.getElementById("agent-search");
        
        if (selectedCount > 0) {
            searchBox.placeholder = `Search agents... (${selectedCount}/${totalCount} selected)`;
            searchBox.style.borderColor = "#28a745";
        } else {
            searchBox.placeholder = `Search agents... (${totalCount} available)`;
            searchBox.style.borderColor = "";
        }
    }
    
    // Add smooth checkbox interactions
    document.addEventListener("change", function(e) {
        if (e.target.classList.contains("agent-checkbox")) {
            updateSelectedCount();
            
            // Add visual feedback for individual checkbox changes
            const item = e.target.parentElement;
            if (e.target.checked) {
                item.style.backgroundColor = "#e7f3ff";
                setTimeout(() => {
                    item.style.backgroundColor = "";
                }, 200);
            }
        }
    });
    
    // Add keyboard navigation
    document.addEventListener("keydown", function(e) {
        if (e.target.id === "agent-search") {
            if (e.key === "Enter") {
                e.preventDefault();
                // Select first visible agent if Enter is pressed
                const firstVisible = document.querySelector(".agent-item:not([style*=\"display: none\"]) .agent-checkbox");
                if (firstVisible) {
                    firstVisible.checked = !firstVisible.checked;
                    firstVisible.dispatchEvent(new Event("change"));
                }
            }
        }
    });
    
    // Initialize on page load
    document.addEventListener("DOMContentLoaded", function() {
        updateSelectedCount();
        
        // Add focus to search box for better UX
        setTimeout(() => {
            const searchBox = document.getElementById("agent-search");
            if (searchBox) {
                searchBox.focus();
            }
        }, 500);
    });
    </script>';
    
    return $html;
}

// Empty arrays - all billing fields replaced with agent selection above
$_input_descriptors1 = array();
$_input_descriptors2 = array();
$_input_descriptors3 = array();

// fieldset
$_fieldset0_descriptor = array(
                                "title" => "Agent Assignment",
                              );

// Check if layout functions are available (they should be when included from mng-edit.php)
if (function_exists('open_fieldset')) {
    open_fieldset($_fieldset0_descriptor);
    
    if ($is_current_operator_agent) {
        // For agent operators: show read-only assignment
        echo '<div class="mb-1">';
        echo '<label class="form-label mb-1">Assigned Agent</label>';
        echo '<div class="alert alert-info">';
        echo '<i class="bi bi-info-circle me-2"></i>';
        echo '<strong>Auto-assigned:</strong> This user will be automatically assigned to your agent account.';
        echo '</div>';
        
        // Get current agent name for display
        $current_agent_name = '';
        foreach ($agent_options as $id => $name) {
            if ($id == $current_agent_id) {
                $current_agent_name = $name;
                break;
            }
        }
        
        echo '<div class="form-control-plaintext bg-light p-2 rounded border">';
        echo '<i class="bi bi-person-check-fill text-success me-2"></i>';
        echo '<strong>' . htmlspecialchars($current_agent_name) . '</strong>';
        echo '</div>';
        
        // Hidden input to ensure the agent is selected
        echo '<input type="hidden" name="selected_agents[]" value="' . $current_agent_id . '">';
        echo '<div class="form-text">Users created by agent operators are automatically assigned to their agent account.</div>';
        echo '</div>';
    } else {
        // For non-agent operators: show full selection
        echo '<div class="mb-1">';
        echo '<label class="form-label mb-1">Select Agents</label>';
        echo generate_agent_checkboxes($agent_options, ((isset($selected_agents)) ? $selected_agents : array()));
        echo '<div class="form-text">Select one or more agents to assign to this user</div>';
        echo '</div>';
    }
    
    close_fieldset();
} else {
    // Fallback for when layout functions are not available
    echo '<div class="card">';
    echo '<div class="card-header"><h5>Agent Assignment</h5></div>';
    echo '<div class="card-body">';
    
    if ($is_current_operator_agent) {
        // For agent operators: show read-only assignment
        echo '<div class="mb-1">';
        echo '<label class="form-label mb-1">Assigned Agent</label>';
        echo '<div class="alert alert-info">';
        echo '<i class="bi bi-info-circle me-2"></i>';
        echo '<strong>Auto-assigned:</strong> This user will be automatically assigned to your agent account.';
        echo '</div>';
        
        // Get current agent name for display
        $current_agent_name = '';
        foreach ($agent_options as $id => $name) {
            if ($id == $current_agent_id) {
                $current_agent_name = $name;
                break;
            }
        }
        
        echo '<div class="form-control-plaintext bg-light p-2 rounded border">';
        echo '<i class="bi bi-person-check-fill text-success me-2"></i>';
        echo '<strong>' . htmlspecialchars($current_agent_name) . '</strong>';
        echo '</div>';
        
        // Hidden input to ensure the agent is selected
        echo '<input type="hidden" name="selected_agents[]" value="' . $current_agent_id . '">';
        echo '<div class="form-text">Users created by agent operators are automatically assigned to their agent account.</div>';
        echo '</div>';
    } else {
        // For non-agent operators: show full selection
        echo '<div class="mb-1">';
        echo '<label class="form-label mb-1">Select Agents</label>';
        echo generate_agent_checkboxes($agent_options, ((isset($selected_agents)) ? $selected_agents : array()));
        echo '<div class="form-text">Select one or more agents to assign to this user</div>';
        echo '</div>';
    }
    
    echo '</div>';
    echo '</div>';
}
