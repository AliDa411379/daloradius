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


echo "
	<form name='form1' method='post' action='$loginpath' class='samanet-login-form'>
		<input type='hidden' name='challenge' value='$challenge'>
		<input type='hidden' name='uamip' value='$uamip'>
		<input type='hidden' name='uamport' value='$uamport'>
		<input type='hidden' name='userurl' value='$userurl'>

		<div class='form-group'>
			<label for='UserName'>$centerUsername</label>
			<input type='text' id='UserName' name='UserName' placeholder='Enter username' class='samanet-input' required>
		</div>

		<div class='form-group'>
			<label for='Password'>$centerPassword</label>
			<input type='password' id='Password' name='Password' placeholder='Enter password' class='samanet-input' required>
		</div>

		<button type='submit' name='button' value='Login' class='samanet-btn samanet-btn-primary' 
			onClick=\"javascript:popUp('$loginpath?res=popup1&uamip=$uamip&uamport=$uamport')\">
			🌐 تسجيل الدخول | Login
		</button>

		<div class='samanet-divider'>
			<span>أو | OR</span>
		</div>

		<div class='samanet-signup-section'>
			<p>ليس لديك حساب؟ | Don't have an account?</p>
			<a href='/app/users/signup.php?uamip=$uamip&uamport=$uamport' class='samanet-btn samanet-btn-secondary'>
				📱 تسجيل جديد | Sign Up
			</a>
		</div>
	</form>

        </div>
        
        <div class='samanet-footer'>
            <p>Powered by <strong>Samanet ISP</strong></p>
            <p class='footer-small'>مزود خدمة الإنترنت | Internet Service Provider</p>
        </div>
    </div>
</div>

</body>
</html>
";


?>
