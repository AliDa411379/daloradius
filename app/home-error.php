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
 * Description:    Error page for access denied
 *
 * Authors:        Liran Tal <liran@lirantal.com>
 *                 Filippo Lauria <filippo.lauria@iit.cnr.it>
 *
 *********************************************************************************************************
 */

include("operators/library/checklogin.php");
$operator = $_SESSION['operator_user'];

include_once('common/includes/config_read.php');
include_once("operators/lang/main.php");
include("common/includes/layout.php");

// print HTML prologue
$title = "Access Denied";
$help = "You do not have permission to access the requested page.";

print_html_prologue($title, $langCode);

// start printing content
print_header_and_footer($title, $help, "", true);

?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="alert alert-danger" role="alert">
                <h4 class="alert-heading"><i class="bi bi-exclamation-triangle-fill"></i> Access Denied</h4>
                <p>You do not have permission to access the requested page.</p>
                <hr>
                <p class="mb-0">
                    <strong>Possible reasons:</strong>
                    <ul>
                        <li>Your account doesn't have the required permissions</li>
                        <li>The page requires administrator access</li>
                        <li>Your session may have expired</li>
                    </ul>
                </p>
                <div class="mt-3">
                    <a href="operators/" class="btn btn-primary">
                        <i class="bi bi-house"></i> Go to Dashboard
                    </a>
                    <a href="operators/logout.php" class="btn btn-secondary">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
print_footer_and_html_epilogue();
?>
