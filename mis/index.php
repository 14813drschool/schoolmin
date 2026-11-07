<?php
/**
 * mis/index.php
 * หน้าเข้าสู่ระบบ (หน้าตาแบบระบบเดิม) เข้าสู่ระบบแล้วไปหน้าแผงเมนูหลัก
 */
require __DIR__.'/app/boot.php';
if (me()) {
    redirect(is_student() ? '/student/' : 'edumain/index.php');
}
mis_headers();
?><!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1"> 
<meta name="viewport" content="width=device-width, initial-scale=1.0"> 
<title>ระบบสารสนเทศเพื่อการบริหารจัดการโรงเรียน</title>
<link rel="shortcut icon" href="images/favicon.ico" />
<link rel="stylesheet" type="text/css" href="css/main.css"/>
<link rel="stylesheet" type="text/css" href="css/thaisansbold.css">
<link rel="stylesheet" type="text/css" href="css/faceform.css" >
<script type="text/javascript" src="scripts/jquery-1.6.4.js"></script>
<script type="text/javascript" src="scripts/faceform.js"></script>
<script type="text/javascript" src="scripts/MM_findObj.js"></script>
<script type="text/javascript">
	jQuery(document).ready(function() {
		jQuery.facebox({ 
			ajax: "login_form.php?role=teacher<?php echo isset($_GET['error']) ? '&error=1' : ''; ?>" ,
			opacity      : 0.1
		} )
	});
</script>
<script type="text/javascript">
function GetLink(goto){
	jQuery.facebox({ 
		ajax: goto ,
		opacity      : 0.1
	} )
}
</script>
<style type='text/css'>

/* CSS reset */
body,div,dl,dt,dd,ul,ol,li,h1,h2,h3,h4,h5,h6,pre,form,fieldset,input,textarea,p,blockquote,th,td { 
	margin: auto;
}
html,body {
	background-color: #efefef;
	background: url(images/bg2.png) no-repeat center top;
	-webkit-background-size: cover;
	-moz-background-size: cover;
	background-size: cover;
	
	margin: auto;
	font-size:13px;
	font-family:tahoma;
    height: 100%;
	overflow: hidden;
}

input:-webkit-autofill {
    -webkit-box-shadow:0 0 0 50px #ffffff inset;
    -webkit-text-fill-color: #333;
}

input:-webkit-autofill:focus {
    -webkit-box-shadow: 0 0 0 50px #ffffff inset!important;
    -webkit-text-fill-color: #333;
} 
</style>
</head>
<body onLoad="MM_preloadImages('images/home_manager_icon_f2.png','images/home_schoolweb_icon_f2.png','images/home_sara_header_icon_f2.png','images/home_academic_icon_f2.png','images/home_admin_icon_f2.png','images/home_teacher_icon_f2.png','images/home_parents_icon_f2.png')">
<center>
<table width="911" border="0" align="center" cellpadding="0" cellspacing="0" class="maintable">
<tr>
<td width="20"><img src="images/spacer.gif" width="20" height="1" border="0" alt="" /></td>
<td width="103"><img src="images/spacer.gif" width="103" height="1" border="0" alt="" /></td>
<td width="24"><img src="images/spacer.gif" width="24" height="1" border="0" alt="" /></td>
<td width="111"><img src="images/spacer.gif" width="111" height="1" border="0" alt="" /></td>
<td width="25"><img src="images/spacer.gif" width="25" height="1" border="0" alt="" /></td>
<td width="96"><img src="images/spacer.gif" width="96" height="1" border="0" alt="" /></td>
<td width="32"><img src="images/spacer.gif" width="32" height="1" border="0" alt="" /></td>
<td width="96"><img src="images/spacer.gif" width="96" height="1" border="0" alt="" /></td>
<td width="35"><img src="images/spacer.gif" width="35" height="1" border="0" alt="" /></td>
<td width="101"><img src="images/spacer.gif" width="101" height="1" border="0" alt="" /></td>
<td width="24"><img src="images/spacer.gif" width="24" height="1" border="0" alt="" /></td>
<td width="93"><img src="images/spacer.gif" width="93" height="1" border="0" alt="" /></td>
<td width="32"><img src="images/spacer.gif" width="32" height="1" border="0" alt="" /></td>
<td width="94"><img src="images/spacer.gif" width="94" height="1" border="0" alt="" /></td>
<td width="25"><img src="images/spacer.gif" width="25" height="1" border="0" alt="" /></td>
</tr>
<tr>
<td colspan="15">
<span class="left" style="margin-left:30px">
<a href="index.php"><img src="gallery/logo/school_logo.png" alt="logo" class="logo"/></a></span>
<span class="left">
    <h2 style="margin-left:10px;font-size:38px">ระบบบริหารจัดการสารสนเทศโรงเรียน</h2>
    <h5 style="font-size:18px;line-height:22px;font-family:Verdana, Arial, Helvetica, sans-serif;color:#666666;margin-left:10px">Online Administrative School Information System</h5>
    <h4 style="font-size:38px;line-height:20px;margin-left:10px">โรงเรียนดรุณราษฎร์วิทยา</h4>
</span></td>
</tr>
<tr>
<td colspan="15" valign="top"><p style="margin:80px"></p></td>
</tr>
<tr>
<td colspan="9" valign="top" align="center" style="padding-left:3em;padding-top:25px;text-align:center"></td>
<td align="center">&nbsp;</td>
<td valign="top">&nbsp;</td>
<td>&nbsp;</td>
<td valign="top">&nbsp;</td>
<td align="center">&nbsp;</td>
<td valign="top"><p style="margin:0px"></p></td>
</tr>
<tr>
<td colspan="15" valign="top"><p style="margin:0px"></p></td>
</tr>
<tr>
  <td valign="top">&nbsp;</td>
  <td align="center"><a href="../student" onMouseOut="MM_swapImgRestore()" onMouseOver="MM_swapImage('home_parents_icon','','images/home_parents_icon_f2.png',1);"><img name="home_parents_icon" src="images/home_parents_icon.png" height="116" border="0" id="home_parents_icon" alt="นักเรียน/ผู้ปกครอง"/></a></td>
  <td valign="top">&nbsp;</td>
  <td align="center"><a href="javascript:void(0);" onClick="GetLink('login_form.php?role=mintra')" onMouseOut="MM_swapImgRestore()" onMouseOver="MM_swapImage('home_admin_icon1','','images/home_admin_icon_f2.png',1)"><img name="home_admin_icon1" src="images/home_admin_icon.png" width="94" height="116" border="0" id="home_admin_icon1" alt="" /></a></td>
  <td valign="top"><p style="margin:0px"></p></td>
  <td><a href="javascript:void(0);" onClick="GetLink('login_form.php?role=evaluator')" onMouseOut="MM_swapImgRestore()" onMouseOver="MM_swapImage('home_academic_icon1','','images/home_academic_icon_f2.png',1)"><img name="home_academic_icon1" src="images/home_academic_icon.png"  height="116" border="0" id="home_academic_icon1" alt="" /></a></td>
  <td valign="top">&nbsp;</td>
  <td><a href="javascript:void(0);" onClick="GetLink('login_form.php?role=sara_header')" onMouseOut="MM_swapImgRestore()" onMouseOver="MM_swapImage('home_sara_header_icon1','','images/home_sara_header_icon_f2.png',1)"><img name="home_sara_header_icon1" src="images/home_sara_header_icon.png" width="111" height="116" border="0" id="home_sara_header_icon1" alt="" /></a></td>
  <td valign="top">&nbsp;</td>
  <td align="center"><a href="javascript:void(0);" onClick="GetLink('login_form.php?role=manager')" onMouseOut="MM_swapImgRestore()" onMouseOver="MM_swapImage('home_manager_icon11','','images/home_manager_icon_f2.png',1)"><img name="home_manager_icon11" src="images/home_manager_icon.png" width="94" height="114" border="0" id="home_manager_icon11" alt="" /></a></td>
  <td valign="top">&nbsp;</td>
  <td><a href="javascript:void(0);" onClick="GetLink('login_form.php?role=schoolweb')" onMouseOut="MM_swapImgRestore()" onMouseOver="MM_swapImage('home_schoolweb_icon11','','images/home_schoolweb_icon_f2.png',1)" style="displays:none"><img name="home_schoolweb_icon11" src="images/home_schoolweb_icon.png" width="111" height="116" border="0" id="home_schoolweb_icon11" alt="" /></a></td>
  <td valign="top"><p style="margin:0px"></p></td>
  <td><a href="javascript:void(0);" onClick="GetLink('login_form.php?role=teacher')" onMouseOut="MM_swapImgRestore()" onMouseOver="MM_swapImage('home_teacher_icon','','images/home_teacher_icon_f2.png',1);"><img name="home_teacher_icon" src="images/home_teacher_icon.png" width="93" height="114" border="0" id="home_teacher_icon" alt="" /></a></td>
  <td valign="top">&nbsp;</td>
</tr>
<tr>
  <td valign="top">&nbsp;</td>
  <td>&nbsp;</td>
  <td valign="top">&nbsp;</td>
  <td>&nbsp;</td>
  <td valign="top">&nbsp;</td>
  <td>&nbsp;</td>
  <td valign="top">&nbsp;</td>
  <td align="center">&nbsp;</td>
  <td valign="top">&nbsp;</td>
  <td>&nbsp;</td>
  <td valign="top">&nbsp;</td>
  <td align="center">&nbsp;</td>
  <td valign="top">&nbsp;</td>
<td>&nbsp;</td>
<td valign="top">&nbsp;</td>
</tr>
<tr>
<td colspan="15" valign="top"><p style="margin:0px"></p></td>
</tr>
</table>
</center>
</body>
</html>