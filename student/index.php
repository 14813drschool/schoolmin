<?php
/**
 * student/index.php
 * ระบบสารสนเทศสำหรับผู้ปกครองและนักเรียน (/student)
 * - เข้าสู่ระบบด้วยเลขประจำตัวนักเรียน + เลขประจำตัวประชาชน 13 หลัก (หรือรหัสผ่านของบัญชี)
 * - ใช้ได้เฉพาะนักเรียน ครู/ผู้ดูแลระบบใช้หน้านี้ไม่ได้
 * ?p= home | profile | sdq | cap | analyze | eq | att | grade | fund | club | logout
 */
require dirname(__DIR__).'/mis/app/boot.php';
require __DIR__.'/forms.php';

$p = preg_replace('/[^a-z0-9_]/', '', (string) ($_GET['p'] ?? 'home'));
$err = '';

/* ---------------------------------------------------------------- ออกจากระบบ */
if ($p === 'logout') {
    $token = $_COOKIE['auth_token'] ?? '';
    if ($token !== '') {
        $payload = \Index\Auth\Model::verifyToken($token);
        \Index\Auth\Model::logout($token, $payload['sub'] ?? null);
    }
    mis_auth_cookie('auth_token', '', -3600);
    mis_auth_cookie('refresh_token', '', -3600);
    redirect('./');
}

/* ---------------------------------------------------------------- เข้าสู่ระบบ */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['login'])) {
    $user = trim((string) ($_POST['username'] ?? ''));
    $pass = trim((string) ($_POST['password'] ?? ''));
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $uid = 0;
    $rate = \Index\Auth\Model::checkRateLimit($user, $ip);
    if (empty($rate['allowed'])) {
        $err = (string) ($rate['message'] ?? 'เข้าสู่ระบบผิดหลายครั้ง กรุณารอสักครู่');
    } elseif ($user !== '' && $pass !== '') {
        // 1) เลขประจำตัวนักเรียน + เลขประจำตัวประชาชน
        $s = q1('SELECT S.`id`, S.`id_card` sic, U.`id_card` uic, U.`active` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`student_id`=?', [$user]);
        $digits = preg_replace('/\D/', '', $pass);
        if ($s && (int) $s['active'] === 1 && strlen($digits) === 13 && in_array($digits, [preg_replace('/\D/', '', (string) $s['sic']), preg_replace('/\D/', '', (string) $s['uic'])], true)) {
            $uid = (int) $s['id'];
        } else {
            // 2) ชื่อผู้ใช้/รหัสผ่านของบัญชี (เฉพาะบัญชีนักเรียน)
            $found = \Index\Auth\Model::findUserByCredentials($user);
            if ($found && q1('SELECT `id` FROM {student} WHERE `id`=?', [(int) $found->id])) {
                $r = \Index\Auth\Model::authenticate($user, $pass, $ip, ['ip' => $ip, 'user_agent' => $ua, 'fingerprint' => hash('sha256', $ip.'|'.$ua)]);
                if (!empty($r['success'])) {
                    mis_auth_cookie('auth_token', $r['token'], \Index\Auth\Model::TOKEN_EXPIRY);
                    if (!empty($r['refresh_token'])) {
                        mis_auth_cookie('refresh_token', $r['refresh_token'], \Index\Auth\Model::REFRESH_TOKEN_EXPIRY);
                    }
                    redirect('./');
                }
            } elseif ($found) {
                $err = 'ไม่มีสิทธิ์เข้าใช้งานระบบ (สำหรับนักเรียนเท่านั้น)';
            }
        }
    }
    if ($uid) {
        \Index\Auth\Model::recordLoginAttempt($user, $ip, true);
        mis_login_user($uid);
        redirect('./');
    }
    if ($err === '') {
        \Index\Auth\Model::recordLoginAttempt($user, $ip, false, $ua);
        $err = 'เลขประจำตัวนักเรียนหรือรหัสผ่านไม่ถูกต้อง';
    }
}

$me = me();
if (!$me) {
    st_login_page($err);
    exit;
}
if (!is_student($me)) {
    st_login_page('ไม่มีสิทธิ์เข้าใช้งานระบบ — หน้านี้สำหรับนักเรียนเท่านั้น', true);
    exit;
}

$sid = (int) $me['id'];
$S = q1('SELECT S.*, U.`name`, U.`phone`, U.`sex`, U.`birthday` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`id`=?', [$sid]);
$pages = st_pages();
if (!isset($pages[$p]) && $p !== 'home') {
    $p = 'home';
}
$post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$body = $p === 'home' ? st_home() : ('st_'.$p)($S, $post);
st_layout($p === 'home' ? '' : $pages[$p][0], $body, $S);
