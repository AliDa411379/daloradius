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
 * Authors:     Liran Tal <liran@lirantal.com>
 *
 * daloRADIUS edition - fixed up variable definition through-out the code
 * as well as parted the code for the sake of modularity and ability to
 * to support templates and languages easier.
 * Copyright (C) Enginx and Liran Tal 2007, 2008
 *
 *********************************************************************************************************
 */

$signup_url = "/app/users/signup.php";

echo <<<END

<div id="samanet-container">
    <div class="samanet-card">
        <div class="samanet-header">
            <img src="template/images/samanet-logo.png" alt="Samanet ISP" class="samanet-logo">
            <h1>مرحباً بك في شبكة سمانت</h1>
            <p class="subtitle">Welcome to Samanet WiFi Network</p>
        </div>
		
        <div class="samanet-body">
            <h2>تسجيل الدخول | Login</h2>

END;


?>
