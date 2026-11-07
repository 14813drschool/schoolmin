<?php
/**
 * mis/login_form.php
 * ฟอร์มเข้าสู่ระบบที่เปิดในกล่อง (facebox) ของหน้าเข้าสู่ระบบ
 */
$roles = ['teacher', 'admin', 'evaluator', 'sara_header', 'manager', 'schoolweb', 'mintra'];
$role = in_array($_GET['role'] ?? '', $roles, true) ? $_GET['role'] : 'teacher';
$role = $role === 'mintra' ? 'admin' : $role;
$error = isset($_GET['error']);
header('Content-Type: text/html; charset=utf-8');
header_remove('Content-Security-Policy');
?><table border="0" cellpadding="0" cellspacing="0">
<tr>
  <td align="center">
  <form name="login" method="post" action="chkteacher.php">
    <table border="0" cellpadding="0" cellspacing="0">
      <tr>
        <td><img src="images/spacer.gif" width="100" height="1" border="0" alt="" /></td>
        <td><img src="images/spacer.gif" width="132" height="1" border="0" alt="" /></td>
        <td><img src="images/spacer.gif" width="20" height="1" border="0" alt="" /></td>
        <td><img src="images/spacer.gif" width="74" height="1" border="0" alt="" /></td>
        <td><img src="images/spacer.gif" width="25" height="1" border="0" alt="" /></td>
        <td><img src="images/spacer.gif" width="1" height="1" border="0" alt="" /></td>
      </tr>
      <tr>
        <td colspan="5" style="background-image:url(images/user_<?php echo $role; ?>.png);background-repeat:no-repeat">&nbsp;</td>
        <td><img src="images/spacer.gif" width="1" height="30" border="0" alt="" /></td>
      </tr>
      <tr>
        <td colspan="5" background="images/login_title.png">&nbsp;</td>
        <td><img src="images/spacer.gif" width="1" height="65" border="0" alt="" /></td>
      </tr>
      <tr>
        <td background="images/login_id.png" style="background-repeat:no-repeat">&nbsp;</td>
        <td><input name="teacher_id" type="text" class="general radius" id="teacher_id" style="width:120px;height:20px">        </td>
        <td rowspan="3" valign="top"><p style="margin:0px"></p></td>
        <td rowspan="3" align="left" valign="top"><input type="image" name="login_button" src="images/login_button.png" height="45" border="0" id="login_button" style="border:none;outline:none" tabindex="-1"></td>
        <td rowspan="3" valign="top"></td>
        <td><img src="images/spacer.gif" width="1" height="21" border="0" alt="" /></td>
      </tr>
      <tr>
        <td valign="top"></td>
        <td valign="top"></td>
        <td><img src="images/spacer.gif" width="1" height="13" border="0" alt="" /></td>
      </tr>
      <tr>
        <td background="images/login_pass.png" style="background-repeat:no-repeat">&nbsp;</td>
        <td>
        <input name="teacher_pass" type="password" class="general radius" id="teacher_pass" style="width:120px;height:20px" autocomplete="new-password">
        </td>
        <td><img src="images/spacer.gif" width="1" height="21" border="0" alt="" /></td>
      </tr>
      <tr>
        <td colspan="4" valign="top" style="text-align:center;line-height:10px;padding-top:30px">
<?php if ($error) { echo '<span style="color:#FF0000">ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง</span>'; } ?>
        &nbsp;	    </td>
        <td valign="bottom" align="right" class="blue_12_dark" style="padding-top:20px">
           <a href='javascript:void(0);' onclick='jQuery("#facebox_overlay").click();' class="bluelink"><img src="images/closelabel.png" border="none"></a>        </td>
        <td><img src="images/spacer.gif" width="1" height="1" border="0" alt="" /></td>
      </tr>
      <tr>
        <td colspan="5">&nbsp;</td>
        <td><img src="images/spacer.gif" width="1" height="9" border="0" alt="" /></td>
      </tr>
    </table>
    </form>
  </td>
</tr>
</table>