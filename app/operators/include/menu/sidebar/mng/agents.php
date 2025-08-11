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

// prevent this file to be directly accessed
if (strpos($_SERVER['PHP_SELF'], '/include/menu/sidebar/mng/agents.php') !== false) {
    header("Location: ../../../../index.php");
    exit;
}

$m_active = "Management";

include_once("include/menu/sidebar/mng/default.php");

$descriptors1 = array();

$descriptors1[] = array( 'type' => 'link', 'label' => t('button','NewAgent'), 'href' =>'mng-agents-new.php',
                         'icon' => 'add', );

if (isset($configValues['CONFIG_IFACE_TABLES_LISTING']) && $configValues['CONFIG_IFACE_TABLES_LISTING'] == "yes") {
    $descriptors1[] = array( 'type' => 'link', 'label' => t('button','ListAgents'), 'href' => 'mng-agents-list.php',
                             'icon' => 'list', );
}

$descriptors1[] = array( 'type' => 'text', 'label' => '' );

if (isset($configValues['CONFIG_IFACE_TABLES_LISTING']) && $configValues['CONFIG_IFACE_TABLES_LISTING'] == "yes") {
    $descriptors1[] = array( 'type' => 'form', 'title' => t('button','EditAgent'), 'action' => 'mng-agents-edit.php', 'method' => 'GET',
                             'inputs' => array( 'agent_id' => array( 'type' => 'text', 'size' => '15' ) ), );
}

$descriptors1[] = array( 'type' => 'link', 'label' => t('button','RemoveAgent'), 'href' => 'mng-agents-del.php',
                         'icon' => 'delete', );

$descriptors2 = array();

$descriptors2[] = array( 'type' => 'link', 'label' => t('button','CSVExport'), 'href' => 'include/management/fileExport.php?reportFormat=csv',
                         'icon' => 'csv', );

$sections = array();
$sections[] = array( 'title' => t('title','AgentManagement'), 'descriptors' => $descriptors1 );
$sections[] = array( 'title' => t('title','ImportExport'), 'descriptors' => $descriptors2 );

// add sections to menu
$menu = array(
                'title' => 'Management',
                'sections' => $sections,
             );

?>