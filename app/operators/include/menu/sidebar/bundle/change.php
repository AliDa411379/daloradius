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
 */

// prevent this file to be directly accessed
if (strpos($_SERVER['PHP_SELF'], '/include/menu/sidebar/bundle/change.php') !== false) {
    header("Location: ../../../../index.php");
    exit;
}

// define descriptors
$descriptors1 = array();

$descriptors1[] = array( 'type' => 'link', 'label' => t('button','PurchaseBundle'), 'href' => 'bundle-purchase.php',
                         'icon' => 'cart-plus-fill', );

$descriptors1[] = array( 'type' => 'link', 'label' => t('button','ListBundles'), 'href' => 'bundle-list.php',
                         'icon' => 'list', );

$descriptors1[] = array( 'type' => 'link', 'label' => t('button','ChangeBundle'), 'href' => 'bundle-change.php',
                         'icon' => 'arrow-repeat', );

$descriptors1[] = array( 'type' => 'link', 'label' => t('button','AddBalance'), 'href' => 'bill-balance-add.php',
                         'icon' => 'wallet2', );

$sections = array();
$sections[] = array( 'title' => 'Bundle Management', 'descriptors' => $descriptors1 );

// add sections to menu
$menu = array(
                'title' => 'Bundles',
                'sections' => $sections,
             );
