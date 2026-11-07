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
redirect('./');
