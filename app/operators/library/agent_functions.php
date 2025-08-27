<?php
/*
 * Helper functions for agent operations
 */

/**
 * Get agent ID for the current logged-in operator (if they are an agent)
 * @param object $dbSocket Database connection
 * @param int $operator_id Current operator ID
 * @param array $configValues Configuration values
 * @return int|null Agent ID or null if not found/not an agent
 */
function getCurrentOperatorAgentId($dbSocket, $operator_id, $configValues) {
    // First check if the operator is marked as an agent
    $sql = sprintf("SELECT is_agent FROM %s WHERE id = %d", 
                   $configValues['CONFIG_DB_TBL_DALOOPERATORS'], 
                   intval($operator_id));
    $res = $dbSocket->query($sql);
    
    if (!$res || !($row = $res->fetchRow())) {
        return null;
    }
    
    $is_agent = $row[0];
    if ($is_agent != 1) {
        return null; // Not an agent
    }
    
    // Check if operator_id column exists in agents table
    $column_exists = false;
    $sql = "SHOW COLUMNS FROM " . $configValues['CONFIG_DB_TBL_DALOAGENTS'] . " LIKE 'operator_id'";
    $res = $dbSocket->query($sql);
    if ($res && $res->numRows() > 0) {
        $column_exists = true;
    }
    
    if ($column_exists) {
        // Try to find agent by operator_id
        $sql = sprintf("SELECT id FROM %s WHERE operator_id = %d AND is_deleted = 0", 
                       $configValues['CONFIG_DB_TBL_DALOAGENTS'], 
                       intval($operator_id));
        $res = $dbSocket->query($sql);
        
        if ($res && ($row = $res->fetchRow())) {
            return intval($row[0]);
        }
    }
    
    // Fallback: try to match by operator info (company, email, etc.)
    $sql = sprintf("SELECT company, firstname, lastname, email1 FROM %s WHERE id = %d", 
                   $configValues['CONFIG_DB_TBL_DALOOPERATORS'], 
                   intval($operator_id));
    $res = $dbSocket->query($sql);
    
    if (!$res || !($row = $res->fetchRow())) {
        return null;
    }
    
    list($company, $firstname, $lastname, $email) = $row;
    
    // Build search conditions
    $search_conditions = array();
    if (!empty($company)) {
        $search_conditions[] = sprintf("company = '%s'", $dbSocket->escapeSimple($company));
    }
    if (!empty($email)) {
        $search_conditions[] = sprintf("email = '%s'", $dbSocket->escapeSimple($email));
    }
    if (!empty($firstname)) {
        $search_conditions[] = sprintf("name = '%s'", $dbSocket->escapeSimple($firstname));
    }
    if (!empty($lastname)) {
        $search_conditions[] = sprintf("name = '%s'", $dbSocket->escapeSimple($lastname));
    }
    if (!empty($firstname) && !empty($lastname)) {
        $full_name = $firstname . ' ' . $lastname;
        $search_conditions[] = sprintf("name = '%s'", $dbSocket->escapeSimple($full_name));
    }
    
    if (empty($search_conditions)) {
        return null;
    }
    
    // Try to find matching agent
    $sql = sprintf("SELECT id FROM %s WHERE is_deleted = 0 AND (%s) LIMIT 1", 
                   $configValues['CONFIG_DB_TBL_DALOAGENTS'],
                   implode(' OR ', $search_conditions));
    $res = $dbSocket->query($sql);
    
    if ($res && ($row = $res->fetchRow())) {
        return intval($row[0]);
    }
    
    return null;
}

/**
 * Check if current operator is an agent
 * @param object $dbSocket Database connection
 * @param int $operator_id Current operator ID
 * @param array $configValues Configuration values
 * @return bool True if operator is an agent
 */
function isCurrentOperatorAgent($dbSocket, $operator_id, $configValues) {
    $sql = sprintf("SELECT is_agent FROM %s WHERE id = %d", 
                   $configValues['CONFIG_DB_TBL_DALOOPERATORS'], 
                   intval($operator_id));
    $res = $dbSocket->query($sql);
    
    if ($res && ($row = $res->fetchRow())) {
        return ($row[0] == 1);
    }
    
    return false;
}

/**
 * Add agent filtering to SQL WHERE conditions for user queries
 * @param object $dbSocket Database connection
 * @param string $operator Current operator username
 * @param array $configValues Configuration values
 * @param array &$sql_WHERE Reference to SQL WHERE conditions array
 * @param string $userinfo_alias Alias for userinfo table (default: 'ui')
 * @return bool True if agent filtering was applied
 */
function addAgentFilteringToUserQuery($dbSocket, $operator, $configValues, &$sql_WHERE, $userinfo_alias = 'ui') {
    // Get current operator info
    $sql_operator = sprintf("SELECT id, is_agent FROM %s WHERE username = '%s'", 
                           $configValues['CONFIG_DB_TBL_DALOOPERATORS'], 
                           $dbSocket->escapeSimple($operator));
    $res_operator = $dbSocket->query($sql_operator);
    
    if ($res_operator && ($row_operator = $res_operator->fetchRow())) {
        $current_operator_id = $row_operator[0];
        $is_current_operator_agent = ($row_operator[1] == 1);
        
        if ($is_current_operator_agent && $current_operator_id) {
            // Add agent filtering condition
            $sql_WHERE[] = sprintf("%s.creationby = '%s'", $userinfo_alias, $dbSocket->escapeSimple($operator));
            return true;
        }
    }
    
    return false;
}

/**
 * Display agent notice for filtered views
 * @param bool $show_notice Whether to show the notice
 */
function displayAgentNotice($show_notice = true) {
    if ($show_notice) {
        echo '<div class="alert alert-info" role="alert">';
        echo '<i class="bi bi-info-circle me-2"></i>';
        echo '<strong>Agent View:</strong> You are viewing only the users you have created. ';
        echo 'As an agent, you can only see and manage users that were created by your account.';
        echo '</div>';
    }
}

/**
 * Check if a user belongs to the current agent
 * @param object $dbSocket Database connection
 * @param string $username Username to check
 * @param string $operator Current operator username
 * @param array $configValues Configuration values
 * @return bool True if user belongs to agent or if operator is not an agent
 */
function isUserOwnedByAgent($dbSocket, $username, $operator, $configValues) {
    // First check if current operator is an agent
    $sql_operator = sprintf("SELECT id, is_agent FROM %s WHERE username = '%s'", 
                           $configValues['CONFIG_DB_TBL_DALOOPERATORS'], 
                           $dbSocket->escapeSimple($operator));
    $res_operator = $dbSocket->query($sql_operator);
    
    if ($res_operator && ($row_operator = $res_operator->fetchRow())) {
        $is_current_operator_agent = ($row_operator[1] == 1);
        
        // If not an agent, allow access to all users
        if (!$is_current_operator_agent) {
            return true;
        }
        
        // If agent, check if user was created by this agent
        $sql_user = sprintf("SELECT creationby FROM %s WHERE username = '%s'", 
                           $configValues['CONFIG_DB_TBL_DALOUSERINFO'], 
                           $dbSocket->escapeSimple($username));
        $res_user = $dbSocket->query($sql_user);
        
        if ($res_user && ($row_user = $res_user->fetchRow())) {
            return ($row_user[0] === $operator);
        }
    }
    
    return false;
}
?>