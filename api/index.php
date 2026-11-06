<?php
/**
 * api/index.php
 *
 * Front controller สำหรับรันบน Vercel (vercel-php runtime)
 * รับทุก request ที่เป็นไฟล์ .php หรือโฟลเดอร์ แล้วเรียกไฟล์ PHP ตัวจริงของโปรเจกต์
 * โดยตั้งค่า $_SERVER ให้เหมือนรันบน Apache ปกติ เพื่อให้ Kotchasan คำนวณ path/URL ได้ถูกต้อง
 */
$root = dirname(__DIR__);
$uri = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
$uri = '/'.ltrim(rawurldecode((string) $uri), '/');

// ไม่ให้เรียกโฟลเดอร์ api เอง
if (strpos($uri, '/api/') === 0 || $uri === '/api') {
    $uri = '/';
}

$path = realpath($root.$uri);

// โฟลเดอร์: ถ้าไม่มี / ปิดท้ายให้ redirect เพื่อให้ลิงก์แบบ relative ทำงานถูก
if ($path !== false && is_dir($path)) {
    if (substr($uri, -1) !== '/') {
        $qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?'.$_SERVER['QUERY_STRING'] : '';
        header('Location: '.$uri.'/'.$qs, true, 301);
        exit;
    }
    if (is_file($path.'/index.php')) {
        $path = $path.'/index.php';
    } elseif (is_file($path.'/index.html')) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($path.'/index.html');
        exit;
    }
}

if ($path === false || strpos($path, $root.DIRECTORY_SEPARATOR) !== 0 || !is_file($path) || substr($path, -4) !== '.php') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo '404 Not Found';
    exit;
}

$script = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root)));
$_SERVER['DOCUMENT_ROOT'] = $root;
$_SERVER['SCRIPT_FILENAME'] = $path;
$_SERVER['SCRIPT_NAME'] = $script;
$_SERVER['PHP_SELF'] = $script;
unset($_SERVER['CONTEXT_DOCUMENT_ROOT']);

chdir(dirname($path));
require $path;
