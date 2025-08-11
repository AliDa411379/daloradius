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
 * Description:    Main entry point for daloRADIUS application
 *
 *********************************************************************************************************
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>daloRADIUS - RADIUS Web Platform</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 40px;
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        .logo {
            font-size: 2.5em;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 1.1em;
        }
        .button-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 30px;
        }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 1.1em;
            font-weight: bold;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        .btn-secondary {
            background: #f8f9fa;
            color: #333;
            border: 2px solid #dee2e6;
        }
        .btn-secondary:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }
        .description {
            margin: 20px 0;
            color: #666;
            line-height: 1.6;
        }
        @media (min-width: 600px) {
            .button-container {
                flex-direction: row;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">daloRADIUS</div>
        <div class="subtitle">RADIUS Web Platform</div>
        
        <div class="description">
            Welcome to daloRADIUS, a comprehensive web-based platform for managing RADIUS servers, 
            user accounts, billing, and network access control.
        </div>
        
        <div class="button-container">
            <a href="app/operators/" class="btn btn-primary">
                Operators Portal
            </a>
            <a href="app/users/" class="btn btn-secondary">
                Users Portal
            </a>
        </div>
        
        <div class="description" style="margin-top: 30px; font-size: 0.9em;">
            <strong>Operators Portal:</strong> For administrators to manage users, billing, and system configuration.<br>
            <strong>Users Portal:</strong> For end users to view their account information and usage statistics.
        </div>
    </div>
</body>
</html>