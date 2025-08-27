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
 * Authors:    Liran Tal <liran@lirantal.com>
 *             Filippo Lauria <filippo.lauria@iit.cnr.it>
 *
 *********************************************************************************************************
 */

// Allow direct access for AJAX calls
// if (strpos($_SERVER['PHP_SELF'], '/library/ajax/get_agent_users.php') !== false) {
//     header("Location: ../../index.php");
//     exit;
// }

include_once implode(DIRECTORY_SEPARATOR, [ __DIR__, '..', '..', '..', 'common', 'includes', 'config_read.php' ]);
include implode(DIRECTORY_SEPARATOR, [ $configValues['OPERATORS_LIBRARY'], 'checklogin.php' ]);

header('Content-Type: application/json');

$response = array('success' => false, 'users' => array());

// Debug logging
error_log("GET parameters: " . print_r($_GET, true));

$agent_param = array_key_exists('agent_id', $_GET) ? trim($_GET['agent_id']) : '';

// Check if it's numeric (agent ID) or string (agent name)
if (is_numeric($agent_param) && intval($agent_param) > 0) {
    $agent_id = intval($agent_param);
    $agent_name = null;
} elseif (!empty($agent_param)) {
    $agent_id = null;
    $agent_name = $agent_param;
} else {
    $agent_id = 0;
    $agent_name = null;
}

error_log("Parsed agent_id: " . $agent_id . ", agent_name: " . $agent_name);

if ($agent_id > 0 || !empty($agent_name)) {
    try {
        include implode(DIRECTORY_SEPARATOR, [ $configValues['COMMON_INCLUDES'], 'db_open.php' ]);
        
        // Build query based on whether we have ID or name
        if ($agent_id > 0) {
            // Query by agent ID
            $sql = sprintf("SELECT u.id, u.username FROM %s u 
                            INNER JOIN user_agent ua ON ua.user_id = u.id 
                            INNER JOIN %s a ON a.id = ua.agent_id 
                            WHERE ua.agent_id = %d AND a.is_deleted = 0
                            ORDER BY u.username ASC",
                           $configValues['CONFIG_DB_TBL_DALOUSERINFO'], 
                           $configValues['CONFIG_DB_TBL_DALOAGENTS'], $agent_id);
        } else {
            // Query by agent name - need to join with agents table
            $sql = sprintf("SELECT u.id, u.username FROM %s u 
                            INNER JOIN user_agent ua ON ua.user_id = u.id 
                            INNER JOIN %s a ON a.id = ua.agent_id 
                            WHERE a.name = '%s' AND a.is_deleted = 0
                            ORDER BY u.username ASC",
                           $configValues['CONFIG_DB_TBL_DALOUSERINFO'],
                           $configValues['CONFIG_DB_TBL_DALOAGENTS'],
                           $dbSocket->escapeSimple($agent_name));
        }
        
        error_log("SQL Query: " . $sql);
        $res = $dbSocket->query($sql);
        
        if ($res && !DB::isError($res)) {
            $users = array();
            while ($row = $res->fetchRow()) {
                $users[] = array(
                    'id' => intval($row[0]),
                    'username' => htmlspecialchars($row[1], ENT_QUOTES, 'UTF-8')
                );
            }
            
            $response['success'] = true;
            $response['users'] = $users;
            error_log("Found " . count($users) . " users for agent");
        } else {
            $response['error'] = 'Database query failed: ' . ($res ? $res->getMessage() : 'Unknown error');
        }
        
        include implode(DIRECTORY_SEPARATOR, [ $configValues['COMMON_INCLUDES'], 'db_close.php' ]);
    } catch (Exception $e) {
        $response['error'] = 'Database error: ' . $e->getMessage();
    }
} else {
    $response['error'] = 'Invalid agent parameter: ' . $agent_param;
}

echo json_encode($response);
?>
