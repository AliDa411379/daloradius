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
 * Description: This script manages non-terminated (or stale) sessions in a RADIUS accounting table.
 * It updates the `acctstoptime` field to the current time and sets `acctterminatecause`
 * to 'Stale-Session' for sessions that exceed a predefined time threshold.
 * Authors: Filippo Lauria <filippo.lauria@iit.cnr.it>
 *********************************************************************************************************
 */

include_once implode(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', '..', 'app', 'common', 'includes', 'config_read.php']);

// Set INTERVAL and GRACE from config, with defaults
$configValues['CONFIG_FIX_STALE_INTERVAL'] = intval($configValues['CONFIG_FIX_STALE_INTERVAL'] ?? 60);
if ($configValues['CONFIG_FIX_STALE_INTERVAL'] <= 0) {
    $configValues['CONFIG_FIX_STALE_INTERVAL'] = 60;
}

$configValues['CONFIG_FIX_STALE_GRACE'] = intval($configValues['CONFIG_FIX_STALE_GRACE'] ?? intdiv($configValues['CONFIG_FIX_STALE_INTERVAL'], 2));
if (
    $configValues['CONFIG_FIX_STALE_GRACE'] <= 0 ||
    $configValues['CONFIG_FIX_STALE_GRACE'] > $configValues['CONFIG_FIX_STALE_INTERVAL']
) {
    $configValues['CONFIG_FIX_STALE_GRACE'] = intdiv($configValues['CONFIG_FIX_STALE_INTERVAL'], 2);
}

$timeThreshold = $configValues['CONFIG_FIX_STALE_INTERVAL'] + $configValues['CONFIG_FIX_STALE_GRACE'];

include implode(DIRECTORY_SEPARATOR, [$configValues['COMMON_INCLUDES'], 'db_open.php']);

// First query: mark stale sessions as stopped
$sql = sprintf(
    "UPDATE %s
     SET `acctstoptime` = NOW(), `acctterminatecause` = 'Stale-Session'
     WHERE (UNIX_TIMESTAMP(NOW()) - (UNIX_TIMESTAMP(`acctstarttime`) + `acctsessiontime`)) > %d
       AND (`acctstoptime` = '0000-00-00 00:00:00' OR `acctstoptime` IS NULL)",
    $configValues['CONFIG_DB_TBL_RADACCT'],
    $timeThreshold
);
$res = $dbSocket->query($sql);

// Second query: adjust invalid start times
$sql = sprintf(
    "UPDATE %s
     SET `acctstarttime` = DATE_ADD(NOW(), INTERVAL (`acctsessiontime` + %d) SECOND)
     WHERE (`acctstarttime` = '0000-00-00 00:00:00' OR `acctstarttime` IS NULL)
       AND `acctsessiontime` > 0",
    $configValues['CONFIG_DB_TBL_RADACCT'],
    $timeThreshold
);
$res = $dbSocket->query($sql);

include implode(DIRECTORY_SEPARATOR, [$configValues['COMMON_INCLUDES'], 'db_close.php']);
