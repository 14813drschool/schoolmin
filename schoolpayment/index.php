<?php
/**
 * schoolpayment/index.php
 * หน้าเข้าสู่ระบบ "ระบบชำระเงินค่าบำรุงการศึกษา" (หน้าตาแบบระบบเดิม /schoolmin/schoolpayment/)
 * เข้าสู่ระบบแล้วไปที่โมดูลรับชำระเงินค่าบำรุงการศึกษา — เฉพาะผู้ที่มีสิทธิ์โมดูลนี้
 */
require dirname(__DIR__).'/mis/app/boot.php';

const PAY_HOME = '/mis/edumain/index.php?module=admin&home=finance';

$error = '';
$u = me();
if ($u && in_array('finance', user_modules(), true)) {
    redirect(PAY_HOME);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $user = trim((string) ($_POST['username'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $found = $user !== '' && $pass !== '' ? \Index\Auth\Model::findUserByCredentials($user) : null;
    $mods = $found ? user_modules(['id' => (int) $found->id, 'status' => (int) $found->status, 'permission' => (string) $found->permission]) : [];
    $result = $user === '' || $pass === '' ? ['success' => false] : \Index\Auth\Model::authenticate($user, $pass, $ip, ['ip' => $ip, 'user_agent' => $ua, 'fingerprint' => hash('sha256', $ip.'|'.$ua)]);
    if (empty($result['success'])) {
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    } elseif (!in_array('finance', $mods, true)) {
        // เข้าสู่ระบบได้แต่ไม่มีสิทธิ์ระบบรับชำระเงิน: ไม่เก็บการเข้าสู่ระบบ
        \Index\Auth\Model::logout($result['token']);
        $error = 'ไม่มีสิทธิ์เข้าใช้งานระบบ';
    } else {
        mis_auth_cookie('auth_token', $result['token'], \Index\Auth\Model::TOKEN_EXPIRY);
        if (!empty($result['refresh_token'])) {
            mis_auth_cookie('refresh_token', $result['refresh_token'], \Index\Auth\Model::REFRESH_TOKEN_EXPIRY);
        }
        redirect(PAY_HOME);
    }
} elseif ($u) {
    $error = 'ไม่มีสิทธิ์เข้าใช้งานระบบ';
}
mis_headers();
$school = cfg('school_name', 'โรงเรียนดรุณราษฎร์วิทยา');
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ระบบรับชำระเงินค่าบำรุงการศึกษา</title>
<link rel="shortcut icon" href="/mis/images/favicon.ico">
<link rel="stylesheet" href="/mis/css/thaisansbold.css">
<link rel="stylesheet" href="/mis/root/css/font-awesome.css">
<style>
html,body{margin:0;height:100%}
body{background:#ddd url(bg4.jpg) no-repeat center top;background-size:cover;font:15px Tahoma,sans-serif;color:#593741}
.hd{height:93px;background:rgba(255,255,255,.55);border-bottom:1px solid rgba(0,0,0,.12)}
.hd .in{max-width:1152px;margin:0 auto;display:flex;align-items:center;gap:8px;height:93px;padding:0 16px;box-sizing:border-box}
.hd img{height:60px}.hd h5{margin:0;font-family:thaisansbold;font-weight:normal;line-height:1}
.hd .t1{font-size:40px;color:#176ED0}.hd .t2{font-size:28px;color:#333}
.ct{position:absolute;top:94px;left:0;right:0;bottom:0;background:rgba(183,228,233,.3)}
.box{width:350px;max-width:calc(100% - 32px);margin:104px auto 0;background:rgba(255,255,255,.8);border:1px solid rgba(0,0,0,.08);border-radius:4px;padding:16px 35px 24px;box-sizing:border-box}
.box h4{margin:0 0 10px;font-family:thaisansbold;font-weight:normal;font-size:28px;color:#222}.box h4 i{font-size:26px;margin-right:6px}
.box input[type=text],.box input[type=password]{display:block;width:100%;box-sizing:border-box;height:34px;margin:0 0 15px;padding:4px 10px;border:1px solid #ccc;border-radius:2px;background:rgba(240,240,240,.85);font:13px Tahoma}
.box input:focus{outline:none;border-color:#4fb8d9;background:#fff}
.box button{width:100%;height:34px;border:1px solid #2f8fb8;border-radius:3px;background:linear-gradient(90deg,#3fa9d6,#55c8e0 50%,#3fa9d6);color:#fff;font:bold 13px Tahoma;letter-spacing:1px;cursor:pointer;text-shadow:0 1px 1px rgba(0,0,0,.25)}
.box button:hover{filter:brightness(1.07)}
.err{margin:0 0 12px;padding:8px 10px;border-radius:3px;background:#FDEDEC;border:1px solid #F5B7B1;color:#C0392B;font-size:13px}
.err b{font-family:thaisansbold;font-weight:normal;font-size:22px}
@media (max-width:600px){.hd .t1{font-size:28px}.hd .t2{font-size:20px}.hd img{height:46px}.box{margin-top:50px}}
</style>
</head>
<body>
<div class="hd"><div class="in"><a href="/mis/"><img src="/mis/gallery/logo/school_logo.png" alt="logo"></a>
<div><h5 class="t1">ระบบบชำระเงินค่าบำรุงการศึกษา</h5><h5 class="t2"><?php echo h($school); ?></h5></div></div></div>
<div class="ct">
<form class="box" method="post" action="./" autocomplete="on">
<h4><i class="fa fa-lock"></i>เข้าสู่ระบบ</h4>
<?php if ($error !== '') { echo '<div class="err"><b>'.h($error).'</b>'.($error === 'ไม่มีสิทธิ์เข้าใช้งานระบบ' ? '<br>บัญชีนี้ไม่ได้รับสิทธิ์ใช้งานระบบรับชำระเงิน กรุณาติดต่อผู้ดูแลระบบ'.($u ? ' (<a href="/mis/logout.php">ออกจากระบบ</a>)' : '') : '').'</div>'; } ?>
<input type="text" name="username" placeholder="Username" autocomplete="username" required>
<input type="password" name="password" placeholder="Password" autocomplete="current-password" required>
<button type="submit">LOG IN</button>
</form>
</div>
</body>
</html>
