<?php
/**
 * mis/logout.php
 * ออกจากระบบ (ยกเลิกโทเคนของ SMS แล้วกลับหน้าเข้าสู่ระบบ)
 */
require __DIR__.'/app/boot.php';
$token = $_COOKIE['auth_token'] ?? '';
if ($token !== '') {
    $payload = \Index\Auth\Model::verifyToken($token);
    \Index\Auth\Model::logout($token, $payload['sub'] ?? null);
}
mis_auth_cookie('auth_token', '', -3600);
mis_auth_cookie('refresh_token', '', -3600);
foreach (['mis_term', 'mis_teacher_view', 'mis_adv_room'] as $c) {
    setcookie($c, '', ['expires' => time() - 3600, 'path' => '/mis/']);
}
// ล้างกล่องเมนูที่เปิดค้างไว้ (sessionStorage) แล้วกลับหน้าเข้าสู่ระบบ
mis_headers();
echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>ออกจากระบบ</title></head><body><script>try{sessionStorage.clear()}catch(e){}location.replace("./")</script>'
    .'<noscript><meta http-equiv="refresh" content="0;url=./"></noscript></body></html>';
exit;
