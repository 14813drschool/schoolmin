<?php
/**
 * mis/chkteacher.php
 * ตรวจชื่อผู้ใช้/รหัสผ่าน (บัญชีเดียวกับ SMS) แล้วเข้าสู่ระบบ
 */
require __DIR__.'/app/boot.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect('./');
}
$user = trim((string) ($_POST['teacher_id'] ?? ''));
$pass = (string) ($_POST['teacher_pass'] ?? '');
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
$result = $user === '' || $pass === '' ? ['success' => false] : \Index\Auth\Model::authenticate($user, $pass, $ip, ['ip' => $ip, 'user_agent' => $ua, 'fingerprint' => hash('sha256', $ip.'|'.$ua)]);
if (empty($result['success'])) {
    redirect('./?error=1');
}
// บัญชีที่ไม่ใช่บุคลากร/ผู้ดูแล/นักเรียน (เช่น สมัครสมาชิกเอง) เข้าระบบไม่ได้
$ud = $result['user'] ?? [];
$u = q1('SELECT * FROM {user} WHERE `id`=?', [(int) (is_array($ud) ? ($ud['id'] ?? 0) : ($ud->id ?? 0))]);
if (!$u || !mis_user_allowed($u)) {
    redirect('./?error=1');
}
mis_auth_cookie('auth_token', $result['token'], \Index\Auth\Model::TOKEN_EXPIRY);
if (!empty($result['refresh_token'])) {
    mis_auth_cookie('refresh_token', $result['refresh_token'], \Index\Auth\Model::REFRESH_TOKEN_EXPIRY);
}
foreach (['mis_term', 'mis_teacher_view', 'mis_adv_room'] as $c) {
    setcookie($c, '', ['expires' => time() - 3600, 'path' => '/mis/']);
}
redirect('edumain/index.php');
