<?php
/**
 * mis/app/payment.php
 * ชำระเงินออนไลน์: QR พร้อมเพย์ตามยอด + แนบสลิป + ฝ่ายการเงินตรวจสอบ + ออกใบเสร็จอัตโนมัติ
 *  - ผู้สมัคร/ผู้ซื้อบริการ (kind=cust) สมัครเองที่หน้า /student/?p=signup แล้วแอดมินอนุมัติ เข้าระบบหน้าเดียวกับนักเรียน (เห็นเฉพาะการเงิน/สัญญา)
 *  - ยอดที่ต้องชำระ (kind=bill, r2=c<ผู้สมัคร>) กำหนดเอง หรือสร้างจากหนังสือสัญญา (งานสารบรรณ)
 *  - สลิป (kind=slip, r2=c<ผู้สมัคร> | s<นักเรียน>) ไฟล์อยู่ที่ datas/private/slip/ (เปิดตรงจากเว็บไม่ได้ ต้องผ่านสิทธิ์)
 *  - นักเรียนชำระค่าบำรุงการศึกษา: อนุมัติสลิปแล้วบันทึกการชำระ (kind=pay) ของภาคเรียนนั้นให้อัตโนมัติ
 * ตั้งค่าพร้อมเพย์/ชื่อผู้รับเงิน: เมนู "ตั้งค่าพร้อมเพย์ / ชื่อผู้รับเงิน" (หน้าเดียวกับบัญชีรับชำระเงินของระบบนักเรียน)
 */
defined('MIS') or exit;

/** ประเภทพร้อมเพย์ => [ชื่อ, จำนวนหลัก] */
const PP_TYPES = ['phone' => ['เบอร์โทรศัพท์มือถือ', 10], 'citizen' => ['เลขประจำตัวประชาชน', 13], 'tax' => ['เลขประจำตัวผู้เสียภาษี (นิติบุคคล/หน่วยงาน)', 13], 'ewallet' => ['เลข e-Wallet ID', 15]];
const BILL_STATUS = ['unpaid' => 'รอชำระ', 'pending' => 'รอตรวจสอบสลิป', 'paid' => 'ชำระแล้ว', 'cancel' => 'ยกเลิก'];
const SLIP_STATUS = ['pending' => 'รอตรวจสอบ', 'approved' => 'อนุมัติแล้ว', 'rejected' => 'ไม่ผ่านการตรวจสอบ'];
const CUST_STATUS = ['pending' => 'รออนุมัติ', 'active' => 'ใช้งานได้', 'rejected' => 'ไม่อนุมัติ', 'suspended' => 'ระงับการใช้งาน'];
const SLIP_MAX = 4 * 1024 * 1024;

/* ================================================================ ตั้งค่า / QR */

function pay_cfg(): array
{
    return st_bank_cfg() + ['pp_type' => '', 'pp_name' => '', 'rc_fmt' => '{n}/{y}'];
}

/** ชื่อผู้รับเงินที่ผู้โอนต้องเห็นในแอปธนาคาร */
function pay_payee(): string
{
    $c = pay_cfg();
    return trim((string) ($c['pp_name'] ?: $c['acc_name'])) ?: (string) cfg('school_name', 'โรงเรียนดรุณราษฎร์วิทยา');
}

/** กล่องเตือนตรวจสอบชื่อผู้รับเงิน (ตัวหนาสีแดง) */
function pay_warn_html(): string
{
    return '<div class="ppwarn" style="border:2px solid #e00;background:#fff5f5;color:#d00000;font-weight:bold;padding:12px 14px;border-radius:6px;margin:12px 0;line-height:1.6;text-align:center">'
        .'!!! ก่อนกดยืนยันการโอน โปรดตรวจสอบชื่อผู้รับเงินปลายทางในแอปธนาคารให้ตรงกับ<br><span style="font-size:1.25em">“'.h(pay_payee()).'”</span><br>ถ้าชื่อไม่ตรง ห้ามโอน และติดต่อฝ่ายการเงินของโรงเรียน !!!</div>';
}

/** QR พร้อมเพย์ตามยอดเงิน (ใช้ /mis/scripts/qrcode.js) หรือรูป QR ที่อัปโหลด */
function pay_qr_html(float $amount, string $id = 'ppqr'): string
{
    $c = pay_cfg();
    if ($c['promptpay'] !== '') {
        return '<div id="'.h($id).'" class="ppqr" data-p="'.h(promptpay_payload($c['promptpay'], $amount)).'" style="width:220px;height:220px;margin:0 auto;background:#fff"></div>'
            .'<div style="text-align:center;font-size:14px;margin-top:4px">สแกนจ่ายผ่าน <b>PromptPay</b> ยอด <b>'.money($amount).'</b> บาท</div>'
            .'<script src="/mis/scripts/qrcode.js"></script><script>(function f(n){var e=document.getElementById('.json_encode($id).');if(!e)return;if(!window.qrcode){if((n||0)<100)setTimeout(function(){f((n||0)+1);},50);return;}'
            .'var q=qrcode(0,"M");q.addData(e.getAttribute("data-p"));q.make();e.innerHTML=q.createSvgTag({cellSize:4,margin:2,scalable:true});})();</script>';
    }
    if ($c['qr'] !== '') {
        return '<div style="text-align:center"><img src="/datas/mis/'.h($c['qr']).'" alt="QR ชำระเงิน" style="width:220px;height:220px;object-fit:contain"><div style="font-size:14px">ยอดที่ต้องโอน <b>'.money($amount).'</b> บาท</div></div>';
    }
    return '<div class="mut" style="text-align:center">ยังไม่ได้ตั้งค่าพร้อมเพย์ — ติดต่อฝ่ายการเงินของโรงเรียน</div>';
}

/** ตรวจหมายเลขพร้อมเพย์ตามประเภท: '' = ถูกต้อง */
function pay_check_pp(string $type, string $num): string
{
    if ($num === '') {
        return '';
    }
    $len = PP_TYPES[$type][1] ?? 0;
    if ($len && strlen($num) !== $len) {
        return 'หมายเลขพร้อมเพย์ประเภท "'.PP_TYPES[$type][0].'" ต้องมี '.$len.' หลัก (ใส่มา '.strlen($num).' หลัก)';
    }
    if ($type === 'phone' && $num[0] !== '0') {
        return 'เบอร์โทรศัพท์ต้องขึ้นต้นด้วย 0';
    }
    return '';
}

/* ================================================================ ผู้สมัคร / ผู้ซื้อบริการ */

function cust_login_key(string $u): string
{
    $u = trim(mb_strtolower($u));
    return str_contains($u, '@') ? $u : preg_replace('/\D/', '', $u);
}

function cust_all(): array
{
    $o = [];
    foreach (md_rows('cust') as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        $d['id'] = (string) $r['r1'];
        $o[$d['id']] = $d;
    }
    uasort($o, fn($a, $b) => strcmp((string) ($b['created'] ?? ''), (string) ($a['created'] ?? '')));
    return $o;
}

function cust_get(string $id): ?array
{
    if ($id === '') {
        return null;
    }
    $r = md_rows('cust', $id)[0] ?? null;
    if (!$r) {
        return null;
    }
    $d = json_decode((string) $r['value'], true) ?: [];
    $d['id'] = $id;
    return $d;
}

function cust_put(string $id, array $d): void
{
    unset($d['id']);
    md_set('cust', $id, '', '', json_encode($d, JSON_UNESCAPED_UNICODE));
}

/** ชื่อที่แสดง: หน่วยงาน (ชื่อผู้ติดต่อ) */
function cust_label(array $c): string
{
    $org = trim((string) ($c['org'] ?? ''));
    $n = trim((string) ($c['name'] ?? ''));
    return $org !== '' ? $org.($n !== '' ? ' ('.$n.')' : '') : $n;
}

/** อีเมล/เบอร์โทรซ้ำกับบัญชีอื่น (ไม่นับที่ไม่อนุมัติ) */
function cust_taken(string $email, string $phone, string $except = ''): bool
{
    foreach (cust_all() as $id => $c) {
        if ($id === $except || ($c['status'] ?? '') === 'rejected') {
            continue;
        }
        if (($email !== '' && ($c['email'] ?? '') === $email) || ($phone !== '' && ($c['phone'] ?? '') === $phone)) {
            return true;
        }
    }
    return false;
}

/** [บัญชี|null, ข้อความผิดพลาด] */
function cust_check_login(string $user, string $pass): array
{
    $k = cust_login_key($user);
    if ($k === '' || $pass === '') {
        return [null, ''];
    }
    foreach (cust_all() as $c) {
        if ($k !== ($c['email'] ?? '') && $k !== ($c['phone'] ?? '')) {
            continue;
        }
        if (!password_verify($pass, (string) ($c['pw'] ?? ''))) {
            return [null, ''];
        }
        return match ($c['status'] ?? '') {
            'active' => [$c, ''],
            'pending' => [null, 'บัญชีผู้สมัครอยู่ระหว่างรอการอนุมัติจากโรงเรียน'],
            'suspended' => [null, 'บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อโรงเรียน'],
            default => [null, 'บัญชีนี้ไม่ได้รับการอนุมัติ กรุณาติดต่อโรงเรียน'],
        };
    }
    return [null, ''];
}

const CUST_COOKIE = 'mis_cust';

/** คีย์ session ในฐานข้อมูล (r1 ยาวได้ 40 ตัว) */
function cust_sess_key(string $tok): string
{
    return substr(hash('sha256', $tok), 0, 40);
}
const CUST_TTL = 8 * 3600;

function cust_login(string $cid): void
{
    $tok = bin2hex(random_bytes(24));
    md_set('cust_sess', cust_sess_key($tok), '', '', json_encode(['cid' => $cid, 'exp' => time() + CUST_TTL]));
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie(CUST_COOKIE, $tok, ['expires' => time() + CUST_TTL, 'path' => '/student/', 'secure' => $https, 'httponly' => true, 'samesite' => $https ? 'Strict' : 'Lax']);
    // ลบ session ที่หมดอายุ
    foreach (md_rows('cust_sess') as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        if ((int) ($d['exp'] ?? 0) < time()) {
            md_set('cust_sess', $r['r1'], '', '', null);
        }
    }
}

/** บัญชีผู้สมัครที่เข้าระบบอยู่ (ใช้งานได้เท่านั้น) */
function cust_session(): ?array
{
    static $c = false;
    if ($c !== false) {
        return $c;
    }
    $tok = (string) ($_COOKIE[CUST_COOKIE] ?? '');
    if (!preg_match('/^[0-9a-f]{48}$/', $tok)) {
        return $c = null;
    }
    $s = json_decode((string) md_get('cust_sess', cust_sess_key($tok)), true) ?: [];
    if ((int) ($s['exp'] ?? 0) < time()) {
        return $c = null;
    }
    $u = cust_get((string) ($s['cid'] ?? ''));
    return $c = ($u && ($u['status'] ?? '') === 'active') ? $u : null;
}

function cust_logout(): void
{
    $tok = (string) ($_COOKIE[CUST_COOKIE] ?? '');
    if (preg_match('/^[0-9a-f]{48}$/', $tok)) {
        md_set('cust_sess', cust_sess_key($tok), '', '', null);
    }
    setcookie(CUST_COOKIE, '', ['expires' => time() - 3600, 'path' => '/student/']);
}

/* ================================================================ ยอดที่ต้องชำระ */

function bill_all(?string $cid = null): array
{
    $o = [];
    foreach (md_rows('bill', null, $cid === null ? null : 'c'.$cid) as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        $d['id'] = (string) $r['r1'];
        $o[$d['id']] = $d;
    }
    uasort($o, fn($a, $b) => [(string) ($a['due'] ?? ''), $a['id']] <=> [(string) ($b['due'] ?? ''), $b['id']]);
    return $o;
}

function bill_get(string $id): ?array
{
    $r = $id !== '' ? (md_rows('bill', $id)[0] ?? null) : null;
    if (!$r) {
        return null;
    }
    $d = json_decode((string) $r['value'], true) ?: [];
    $d['id'] = $id;
    return $d;
}

function bill_put(string $id, array $d): void
{
    unset($d['id']);
    md_set('bill', $id, 'c'.($d['cid'] ?? ''), '', json_encode($d, JSON_UNESCAPED_UNICODE));
}

function new_id(): string
{
    return (string) round(microtime(true) * 1000).random_int(10, 99);
}

/** สร้างยอดชำระตามงวดของหนังสือสัญญา (ซ้ำได้: งวดที่สร้างแล้วไม่สร้างใหม่) คืนจำนวนงวดที่สร้าง */
function bill_from_contract(string $docId, array $d): int
{
    $cid = (string) ($d['ct_cust'] ?? '');
    $amt = round((float) ($d['ct_amount'] ?? 0), 2);
    if ($cid === '' || $amt <= 0 || !cust_get($cid)) {
        return 0;
    }
    $plan = (string) ($d['ct_plan'] ?? 'once');
    $n = $plan === 'once' ? 1 : max(1, min(120, (int) ($d['ct_periods'] ?? 1)));
    $start = (string) ($d['ct_start'] ?? '') ?: date('Y-m-d');
    $made = 0;
    for ($k = 0; $k < $n; $k++) {
        $id = 'ct'.$docId.'-'.($k + 1);
        if (bill_get($id)) {
            continue;
        }
        $due = $plan === 'once' ? $start : date('Y-m-d', strtotime($start.' +'.$k.' '.($plan === 'year' ? 'year' : 'month')));
        $t = strtotime($due);
        $period = $plan === 'month' ? 'งวดที่ '.($k + 1).' (เดือน'.TH_MONTHS[(int) date('n', $t)].' '.((int) date('Y', $t) + 543).')'
            : ($plan === 'year' ? 'งวดที่ '.($k + 1).' (ปี '.((int) date('Y', $t) + 543).')' : '');
        bill_put($id, ['cid' => $cid, 'title' => trim('ค่า'.preg_replace('/^สัญญา/u', '', (string) ($d['title'] ?? 'ตามสัญญา')).' '.$period), 'amount' => $amt, 'due' => $due,
            'contract' => $docId, 'status' => 'unpaid', 'created' => date('Y-m-d H:i:s'), 'by' => (int) (me()['id'] ?? 0)]);
        $made++;
    }
    return $made;
}

/* ================================================================ สลิป / ใบเสร็จ */

function slip_all(?string $owner = null): array
{
    $o = [];
    foreach (md_rows('slip', null, $owner) as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        $d['id'] = (string) $r['r1'];
        $o[$d['id']] = $d;
    }
    uasort($o, fn($a, $b) => strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? '')));
    return $o;
}

function slip_get(string $id): ?array
{
    $r = $id !== '' ? (md_rows('slip', $id)[0] ?? null) : null;
    if (!$r) {
        return null;
    }
    $d = json_decode((string) $r['value'], true) ?: [];
    $d['id'] = $id;
    return $d;
}

function slip_put(string $id, array $d): void
{
    unset($d['id']);
    md_set('slip', $id, (string) $d['owner'], '', json_encode($d, JSON_UNESCAPED_UNICODE));
}

/** เก็บไฟล์สลิปที่อัปโหลด: [ชื่อไฟล์, ข้อความผิดพลาด] */
function slip_store(?array $f): array
{
    if (!$f || (int) ($f['error'] ?? 4) === 4 || (int) ($f['size'] ?? 0) === 0) {
        return ['', 'กรุณาแนบรูปสลิปการโอนเงิน'];
    }
    if ((int) $f['error'] !== 0 || (int) $f['size'] > SLIP_MAX) {
        return ['', 'ไฟล์สลิปต้องไม่เกิน 4 MB'];
    }
    $info = @getimagesize($f['tmp_name']);
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? '';
    if ($ext === '') {
        return ['', 'ไฟล์สลิปต้องเป็นรูปภาพ JPG / PNG / WEBP'];
    }
    $dir = ROOT_PATH.'datas/private/slip';
    is_dir($dir) || mkdir($dir, 0777, true);
    $name = bin2hex(random_bytes(12)).'.'.$ext;
    if (!move_uploaded_file($f['tmp_name'], $dir.'/'.$name)) {
        return ['', 'บันทึกไฟล์สลิปไม่สำเร็จ กรุณาลองใหม่'];
    }
    return [$name, ''];
}

/** ส่งไฟล์สลิป (เรียกหลังตรวจสิทธิ์แล้วเท่านั้น) */
function slip_output(array $s): void
{
    $name = (string) ($s['file'] ?? '');
    $f = ROOT_PATH.'datas/private/slip/'.$name;
    if (!preg_match('/^[0-9a-f]{24}\.(jpg|png|webp)$/', $name) || !is_file($f)) {
        http_response_code(404);
        exit('ไม่พบไฟล์');
    }
    header('Content-Type: '.['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][pathinfo($name, PATHINFO_EXTENSION)]);
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: '.filesize($f));
    readfile($f);
    exit;
}

/** ส่งสลิปใหม่ (ผู้สมัคร: ยอดชำระ / นักเรียน: ค่าบำรุงการศึกษาภาคเรียน) — คืนข้อความผิดพลาด หรือ '' */
function slip_submit(string $owner, string $ref, float $amount, string $title): string
{
    [$file, $err] = slip_store($_FILES['slip'] ?? null);
    if ($err !== '') {
        return $err;
    }
    $paid = trim((string) ($_POST['paid_at'] ?? ''));
    $paid = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $paid) ? str_replace('T', ' ', $paid) : '';
    slip_put(new_id(), ['owner' => $owner, 'ref' => $ref, 'title' => mb_substr($title, 0, 200), 'amount' => round($amount, 2), 'file' => $file, 'paid_at' => $paid,
        'note' => mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 300), 'status' => 'pending', 'at' => date('Y-m-d H:i:s')]);
    return '';
}

/** เลขที่ใบเสร็จถัดไปของปี พ.ศ. */
function rc_next(int $by): int
{
    $max = 0;
    foreach (md_rows('slip') as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        if ((int) ($d['rc_by'] ?? 0) === $by) {
            $max = max($max, (int) ($d['rc_num'] ?? 0));
        }
    }
    return $max + 1;
}

/** อนุมัติสลิป: ออกเลขใบเสร็จ + ปิดยอด (ผู้สมัคร) / บันทึกการชำระภาคเรียน (นักเรียน) */
function slip_approve(string $id): string
{
    $s = slip_get($id);
    if (!$s || ($s['status'] ?? '') !== 'pending') {
        return 'สลิปนี้ถูกตรวจสอบไปแล้ว';
    }
    $by = (int) date('Y') + 543;
    $n = rc_next($by);
    $s['status'] = 'approved';
    $s['rc_by'] = $by;
    $s['rc_num'] = $n;
    $s['rc_no'] = strtr((string) (pay_cfg()['rc_fmt'] ?: '{n}/{y}'), ['{n}' => (string) $n, '{y}' => (string) $by]);
    $s['rc_date'] = date('Y-m-d');
    $s['done_by'] = (int) (me()['id'] ?? 0);
    $s['done_at'] = date('Y-m-d H:i:s');
    if (str_starts_with((string) $s['owner'], 'c')) {
        $b = bill_get((string) $s['ref']);
        if ($b) {
            $b['status'] = 'paid';
            $b['slip'] = $id;
            bill_put($b['id'], $b);
        }
    } else {
        $sid = (int) substr((string) $s['owner'], 1);
        md_set('pay', $sid, (string) $s['ref'], '', json_encode(['amount' => (float) $s['amount'], 'date' => date('Y-m-d'), 'method' => 'transfer', 'book' => '', 'no' => $s['rc_no'],
            'ref' => 'slip:'.$id, 'by' => $s['done_by']], JSON_UNESCAPED_UNICODE));
    }
    slip_put($id, $s);
    return '';
}

function slip_reject(string $id, string $reason): string
{
    $s = slip_get($id);
    if (!$s || ($s['status'] ?? '') !== 'pending') {
        return 'สลิปนี้ถูกตรวจสอบไปแล้ว';
    }
    $s['status'] = 'rejected';
    $s['reason'] = mb_substr(trim($reason), 0, 300) ?: 'ข้อมูลไม่ถูกต้อง';
    $s['done_by'] = (int) (me()['id'] ?? 0);
    $s['done_at'] = date('Y-m-d H:i:s');
    slip_put($id, $s);
    if (str_starts_with((string) $s['owner'], 'c') && ($b = bill_get((string) $s['ref'])) && ($b['status'] ?? '') === 'pending') {
        $b['status'] = 'unpaid';
        bill_put($b['id'], $b);
    }
    return '';
}

/** ข้อมูลผู้ชำระของสลิป: [ชื่อ, รายละเอียดบรรทัดล่าง] */
function slip_payer(array $s): array
{
    $o = (string) ($s['owner'] ?? '');
    if (str_starts_with($o, 'c')) {
        $c = cust_get(substr($o, 1));
        return $c ? [cust_label($c), trim((string) ($c['address'] ?? '')).(($c['taxid'] ?? '') !== '' ? "\nเลขประจำตัวผู้เสียภาษี/บัตรประชาชน ".$c['taxid'] : '')] : ['(ไม่พบผู้สมัคร)', ''];
    }
    $st = q1('SELECT S.`student_id`, S.`class`, S.`room`, U.`name` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`id`=?', [(int) substr($o, 1)]);
    return $st ? [(string) $st['name'], 'เลขประจำตัวนักเรียน '.$st['student_id'].' ชั้น '.class_room_short($st['class'], $st['room'])] : ['(ไม่พบนักเรียน)', ''];
}

/** เอกสาร A4 (ใบเสร็จ) */
function pay_print_doc(string $title, string $body, string $bar): void
{
    mis_headers();
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'.h($title).'</title>'
        .'<link rel="shortcut icon" href="/mis/images/favicon.ico"><link rel="stylesheet" href="/mis/css/sarabun.css"><style>'
        .'@page{size:A4;margin:1.5cm 1.8cm 1.5cm 2cm}*{box-sizing:border-box}body{margin:0;background:#808080}'
        .'.sheet{width:21cm;min-height:14cm;margin:12px auto;background:#fff;padding:1.5cm 1.8cm 1.5cm 2cm;box-shadow:0 2px 10px rgba(0,0,0,.35);font-family:"TH Sarabun New",sans-serif;font-size:16pt;line-height:1.25;color:#000}'
        .'.rh{display:flex;gap:.4cm;align-items:center;border-bottom:1.5px solid #000;padding-bottom:6pt}.rh img{height:2cm}.rh .o{flex:1}.rh .o b{font-size:20pt}.rh .t{font-size:24pt;font-weight:bold;text-align:right}'
        .'.ri{display:flex;justify-content:space-between;gap:1cm;margin-top:10pt}.ri .n{white-space:nowrap;text-align:right}'
        .'table{width:100%;border-collapse:collapse;margin-top:10pt;font-size:15pt}th,td{border:1px solid #000;padding:2pt 6pt}th{text-align:center}.r{text-align:right;white-space:nowrap}.c{text-align:center}'
        .'.sg{display:flex;justify-content:flex-end;margin-top:22pt}.sg>div{width:8cm;text-align:center}.sgl{position:relative;display:inline-block}'
        .'.paid{display:inline-block;border:3px solid #1e8449;color:#1e8449;font-weight:bold;font-size:20pt;padding:0 12pt;border-radius:6pt;transform:rotate(-6deg)}'
        .'.bar{position:sticky;top:0;background:#333;color:#fff;padding:8px;text-align:center;z-index:9;font:13px Tahoma}.bar button,.bar a{font:14px Tahoma;padding:4px 14px;margin:0 4px}.bar a{color:#fff}'
        .'@media print{body{background:#fff}.sheet{width:auto;min-height:0;margin:0;padding:0;box-shadow:none}.bar{display:none}}'
        .'</style></head><body><div class="bar">'.$bar.'</div><div class="sheet">'.$body.'</div>'.sign_trim_js().'</body></html>';
    exit;
}

/** ใบเสร็จรับเงินของสลิปที่อนุมัติแล้ว */
function rc_html(array $s): string
{
    [$name, $detail] = slip_payer($s);
    $c = sb_cfg();
    $org = array_values(array_filter(array_map('trim', explode("\n", $c['org'])), 'strlen'));
    $items = '';
    $sum = (float) $s['amount'];
    if (str_starts_with((string) $s['owner'], 's')) {
        [$yy, $tt] = array_pad(explode('/', (string) $s['ref']), 2, '');
        $items = '<tr><td class="c">1</td><td>ค่าบำรุงการศึกษา ภาคเรียนที่ '.h($tt).' ปีการศึกษา '.h($yy).'</td><td class="r">'.money($sum).'</td></tr>';
    } else {
        $items = '<tr><td class="c">1</td><td>'.h((string) ($s['title'] ?? '')).'</td><td class="r">'.money($sum).'</td></tr>';
    }
    $who = (int) ($s['done_by'] ?? 0);
    $wname = $who ? (string) (personnel_options()[$who] ?? q1('SELECT `name` FROM {user} WHERE `id`=?', [$who])['name'] ?? '') : '';
    $sig = $who ? sign_center(person_sign_url($who), 46, '-8px', 200) : '';
    return '<div class="rh"><img src="'.h(rpt_logo()).'" alt=""/><div class="o"><b>'.h($org[0] ?? cfg('school_name', '')).'</b><br>'.implode('<br>', array_map('h', array_slice($org, 1)))
        .($c['tel'] !== '' ? '<br>โทร. '.h($c['tel']) : '').'</div><div class="t">ใบเสร็จรับเงิน</div></div>'
        .'<div class="ri"><div>ได้รับเงินจาก <b>'.h($name).'</b>'.($detail !== '' ? '<br>'.nl2br(h($detail)) : '').'</div><div class="n">เลขที่ <b>'.h((string) $s['rc_no']).'</b><br>วันที่ '.th_date((string) $s['rc_date'], ' ').'</div></div>'
        .'<table><tr><th style="width:1.4cm">ลำดับ</th><th>รายการ</th><th style="width:3.6cm">จำนวนเงิน (บาท)</th></tr>'.$items
        .'<tr><td colspan="2" class="c">('.sb_bahttext($sum).')</td><td class="r"><b>'.money($sum).'</b></td></tr></table>'
        .'<div style="margin-top:8pt">ชำระโดย โอนเงินผ่าน QR พร้อมเพย์'.(($s['paid_at'] ?? '') !== '' ? ' เมื่อ '.th_date(substr((string) $s['paid_at'], 0, 10), ' ').' เวลา '.h(substr((string) $s['paid_at'], 11, 5)).' น.' : '')
        .' — ตรวจสอบหลักฐานการชำระเงินแล้ว <span class="paid" style="margin-left:10pt">ชำระแล้ว</span></div>'
        .'<div class="sg"><div>ลงชื่อ <span class="sgl">........................................'.$sig.'</span> ผู้รับเงิน<br>('.($wname !== '' ? h($wname) : str_repeat('&nbsp;', 30)).')<br>ฝ่ายการเงิน</div></div>'
        .'<div style="margin-top:14pt;font-size:13pt;color:#555">ใบเสร็จนี้ออกโดยระบบสารสนเทศเมื่อฝ่ายการเงินตรวจสอบหลักฐานการชำระเงินแล้ว</div>';
}

function rc_output(array $s, string $bar): void
{
    if (($s['status'] ?? '') !== 'approved') {
        http_response_code(404);
        exit('ยังไม่มีใบเสร็จ');
    }
    pay_print_doc('ใบเสร็จรับเงิน '.$s['rc_no'], rc_html($s), $bar);
}

/* ================================================================ หน้าผู้ดูแล (โมดูลรับชำระเงิน) */

function pay_badge(string $status, array $map): string
{
    $col = ['unpaid' => '#B9770E', 'pending' => '#1F618D', 'paid' => '#1E8449', 'approved' => '#1E8449', 'active' => '#1E8449', 'cancel' => '#7F8C8D', 'rejected' => '#C0392B', 'suspended' => '#C0392B'][$status] ?? '#555';
    return '<span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:12px;color:#fff;background:'.$col.'">'.h($map[$status] ?? $status).'</span>';
}

function adm_slip(bool $post)
{
    $st = isset(SLIP_STATUS[(string) ($_GET['s'] ?? '')]) ? (string) $_GET['s'] : 'pending';
    $rows = '';
    $i = 0;
    foreach (slip_all() as $id => $s) {
        if (($s['status'] ?? '') !== $st) {
            continue;
        }
        [$name] = slip_payer($s);
        $rows .= zrow([[$i + 1, ' align="center"'], h(substr((string) $s['at'], 0, 16)), (str_starts_with((string) $s['owner'], 's') ? 'นักเรียน: ' : 'ผู้สมัคร: ').h($name), h((string) $s['title']),
            [money($s['amount']), ' align="right"'], pay_badge((string) $s['status'], SLIP_STATUS).(($s['rc_no'] ?? '') !== '' ? ' <span class="gray_12">ใบเสร็จ '.h($s['rc_no']).'</span>' : ''),
            ['<a href="'.h(a_url('slip', '&func=View&id='.rawurlencode($id))).'">ตรวจสอบ</a>', ' align="center"']], $i++);
    }
    $tabs = '';
    foreach (SLIP_STATUS as $k => $l) {
        $tabs .= '<a href="'.h(a_url('slip', '&s='.$k)).'" style="display:inline-block;margin-right:6px;padding:5px 12px;border-radius:4px;border:1px solid #9CD3F2;'.($k === $st ? 'background:#2E86C1;color:#fff' : 'background:#F2FAFF').'">'.h($l).'</a>';
    }
    return ['ตรวจสอบสลิปการชำระเงิน', '<div style="width:983px;margin:0 auto 8px;text-align:left">'.$tabs.'</div>'
        .list_table('สลิปการชำระเงิน — '.SLIP_STATUS[$st], [['#', ' style="width:30px"'], 'ส่งเมื่อ', 'ผู้ชำระ', 'รายการ', 'ยอดเงิน', 'สถานะ', ['', ' style="width:70px"']], $rows, $i.' รายการ')];
}

function adm_slip_View(bool $post)
{
    $id = (string) ($_GET['id'] ?? '');
    $s = slip_get($id);
    if (!$s) {
        return ['ไม่พบสลิป', msg_box('ไม่พบสลิป', false)];
    }
    $err = '';
    if ($post) {
        $act = (string) ($_POST['act'] ?? '');
        $err = $act === 'approve' ? slip_approve($id) : ($act === 'reject' ? slip_reject($id, (string) ($_POST['reason'] ?? '')) : 'คำสั่งไม่ถูกต้อง');
        if ($err === '') {
            redirect(a_url('slip', '&func=View&id='.rawurlencode($id).'&msg=saved'));
        }
        $s = slip_get($id);
    }
    [$name, $detail] = slip_payer($s);
    $img = h(a_url('slip', '&func=File&id='.rawurlencode($id)));
    $kv = fn($k, $v) => '<tr><td style="padding:5px 10px;width:170px;color:#555;vertical-align:top">'.$k.'</td><td style="padding:5px 0">'.$v.'</td></tr>';
    $info = '<table style="font-size:14px">'.$kv('ผู้ชำระ', '<b>'.h($name).'</b>'.($detail !== '' ? '<br><span class="gray_12">'.nl2br(h($detail)).'</span>' : ''))
        .$kv('รายการ', h((string) $s['title'])).$kv('ยอดที่ต้องชำระ', '<b style="font-size:18px">'.money($s['amount']).'</b> บาท')
        .$kv('วันเวลาที่โอน (ผู้ชำระแจ้ง)', h((string) ($s['paid_at'] ?: '-'))).$kv('หมายเหตุผู้ชำระ', h((string) ($s['note'] ?: '-'))).$kv('ส่งสลิปเมื่อ', h((string) $s['at']))
        .$kv('ชื่อผู้รับเงินที่ต้องตรงกับสลิป', '<b style="color:#C00">'.h(pay_payee()).'</b>')
        .$kv('สถานะ', pay_badge((string) $s['status'], SLIP_STATUS).(($s['reason'] ?? '') !== '' ? ' <span style="color:#C0392B">เหตุผล: '.h($s['reason']).'</span>' : '')
            .(($s['status'] ?? '') === 'approved' ? ' ใบเสร็จเลขที่ <b>'.h($s['rc_no']).'</b> <a href="'.h(a_url('slip', '&func=Receipt&id='.rawurlencode($id))).'" target="_blank">พิมพ์ใบเสร็จ</a>' : '')).'</table>';
    $act = ($s['status'] ?? '') === 'pending' ? '<form method="post" action="'.h(a_url('slip', '&func=View&id='.rawurlencode($id))).'" style="margin-top:14px;padding:12px;border:1px dashed #33cc99;border-radius:4px;background:#FCFCFC">'
        .'<div style="margin-bottom:8px;font-size:13px">ตรวจสอบให้ตรงกัน: ยอดเงิน, ชื่อผู้รับเงินปลายทาง, วันเวลาโอน และเลขอ้างอิงในแอปธนาคารของโรงเรียน ก่อนอนุมัติ</div>'
        .'<button type="submit" name="act" value="approve" style="padding:6px 18px;background:#1E8449;color:#fff;border:0;border-radius:4px;font-size:14px;cursor:pointer" onclick="return confirm(\'ยืนยันอนุมัติสลิปนี้และออกใบเสร็จ?\')">✓ อนุมัติ และออกใบเสร็จ</button>'
        .' &nbsp; หรือ ไม่ผ่าน เพราะ <input type="text" name="reason" class="general radius grayborder" style="width:280px;height:26px" placeholder="เช่น ยอดเงินไม่ตรง / สลิปไม่ชัด"/>'
        .' <button type="submit" name="act" value="reject" style="padding:6px 14px;background:#C0392B;color:#fff;border:0;border-radius:4px;font-size:14px;cursor:pointer">✗ ไม่ผ่าน</button></form>' : '';
    return ['ตรวจสอบสลิป', ($err !== '' ? msg_box($err, false) : '').'<div style="width:983px;margin:0 auto;display:flex;gap:20px;text-align:left;background:#fff;border:1px solid #ddd;border-radius:4px;padding:16px;box-sizing:border-box">'
        .'<div style="width:330px;flex:none"><a href="'.$img.'" target="_blank"><img src="'.$img.'" alt="สลิป" style="width:330px;max-height:560px;object-fit:contain;border:1px solid #ccc;border-radius:4px;background:#f4f4f4"/></a><div class="gray_12" style="text-align:center">คลิกรูปเพื่อดูขนาดเต็ม</div></div>'
        .'<div style="flex:1">'.$info.$act.'<p style="margin-top:14px"><a href="'.h(a_url('slip', '&s='.$s['status'])).'">« กลับรายการสลิป</a></p></div></div>'];
}

function adm_slip_File(bool $post)
{
    $s = slip_get((string) ($_GET['id'] ?? ''));
    $s ? slip_output($s) : exit('ไม่พบไฟล์');
}

function adm_slip_Receipt(bool $post)
{
    $s = slip_get((string) ($_GET['id'] ?? ''));
    if (!$s) {
        exit('ไม่พบใบเสร็จ');
    }
    rc_output($s, '<button onclick="window.print()">🖨 พิมพ์ / บันทึกเป็น PDF</button><button onclick="history.length>1?history.back():window.close()">กลับ</button>');
}

function adm_cust(bool $post)
{
    // toggle=<สถานะ> (ชื่อพารามิเตอร์อยู่ในรายการที่ mis_csrf_guard ตรวจที่มาของคำขอ)
    if (isset($_GET['toggle'], $_GET['id']) && isset(CUST_STATUS[(string) $_GET['toggle']]) && ($c = cust_get((string) $_GET['id']))) {
        $c['status'] = (string) $_GET['toggle'];
        $c['status_by'] = (int) (me()['id'] ?? 0);
        $c['status_at'] = date('Y-m-d H:i:s');
        cust_put($c['id'], $c);
        admin_done('cust', '&s='.rawurlencode((string) ($_GET['s'] ?? '')));
    }
    $st = isset(CUST_STATUS[(string) ($_GET['s'] ?? '')]) ? (string) $_GET['s'] : '';
    $rows = '';
    $i = 0;
    foreach (cust_all() as $id => $c) {
        if ($st !== '' && ($c['status'] ?? '') !== $st) {
            continue;
        }
        $bills = bill_all($id);
        $due = array_sum(array_map(fn($b) => in_array($b['status'] ?? '', ['unpaid', 'pending'], true) ? (float) $b['amount'] : 0, $bills));
        $a = fn($s, $l, $col) => '<a href="'.h(a_url('cust', '&toggle='.$s.'&id='.rawurlencode($id).'&s='.$st)).'" style="color:'.$col.'" onclick="return confirm(\''.$l.' ผู้สมัครนี้?\')">'.$l.'</a>';
        $acts = match ($c['status'] ?? '') {
            'pending' => $a('active', 'อนุมัติ', '#1E8449').' | '.$a('rejected', 'ไม่อนุมัติ', '#C0392B'),
            'active' => $a('suspended', 'ระงับ', '#C0392B'),
            default => $a('active', 'เปิดใช้งาน', '#1E8449'),
        };
        $rows .= zrow([[$i + 1, ' align="center"'], '<b>'.h(cust_label($c)).'</b><br><span class="gray_12">'.h((string) ($c['email'] ?? '')).' · '.h((string) ($c['phone'] ?? '')).'</span>',
            h(mb_substr((string) ($c['interest'] ?? ''), 0, 60)), h(substr((string) ($c['created'] ?? ''), 0, 16)), pay_badge((string) ($c['status'] ?? ''), CUST_STATUS),
            [money($due), ' align="right"'], [$acts.' | '.edit_link(a_url('cust', '&func=Edit&id='.rawurlencode($id))), ' align="center" style="white-space:nowrap"']], $i++);
    }
    $tabs = '<a href="'.h(a_url('cust')).'" style="display:inline-block;margin-right:6px;padding:5px 12px;border-radius:4px;border:1px solid #9CD3F2;'.($st === '' ? 'background:#2E86C1;color:#fff' : 'background:#F2FAFF').'">ทั้งหมด</a>';
    foreach (CUST_STATUS as $k => $l) {
        $tabs .= '<a href="'.h(a_url('cust', '&s='.$k)).'" style="display:inline-block;margin-right:6px;padding:5px 12px;border-radius:4px;border:1px solid #9CD3F2;'.($k === $st ? 'background:#2E86C1;color:#fff' : 'background:#F2FAFF').'">'.h($l).'</a>';
    }
    return ['ผู้สมัคร / ผู้ซื้อบริการ', '<p style="width:983px;margin:0 auto 8px;text-align:left" class="gray_12">ผู้สมัครลงทะเบียนเองที่หน้าระบบนักเรียน (ลิงก์ "สมัครใช้บริการ") — อนุมัติแล้วเข้าระบบด้วยอีเมลหรือเบอร์โทร เห็นเฉพาะหน้าการเงิน สัญญา และใบเสร็จของตนเอง</p>'
        .'<div style="width:983px;margin:0 auto 8px;text-align:left">'.$tabs.'</div>'
        .list_table('ผู้สมัคร / ผู้ซื้อบริการ', [['#', ' style="width:30px"'], 'ชื่อ / หน่วยงาน', 'บริการที่สนใจ', 'สมัครเมื่อ', 'สถานะ', 'ค้างชำระ', ['', ' style="width:170px"']], $rows, $i.' ราย',
            btn('เพิ่มผู้ซื้อ / ผู้สมัคร', a_url('cust', '&func=Edit'), 'add')), head_btn('เพิ่ม', a_url('cust', '&func=Edit'), 'add')];
}

function adm_cust_Edit(bool $post)
{
    $id = (string) ($_GET['id'] ?? '');
    $c = $id !== '' ? cust_get($id) : null;
    if ($id !== '' && !$c) {
        return ['ไม่พบผู้สมัคร', msg_box('ไม่พบผู้สมัคร', false)];
    }
    $c = $c ?? ['status' => 'active'];
    $err = '';
    if ($post) {
        [$d, $err] = cust_form_read($c, $id, true);
        if ($err === '') {
            $id = $id !== '' ? $id : new_id();
            $d['created'] ??= date('Y-m-d H:i:s');
            cust_put($id, $d);
            admin_done('cust');
        }
        $c = $d + $c;
    }
    $v = fn($k) => (string) ($c[$k] ?? '');
    $rows = [['ชื่อ-นามสกุล ผู้ติดต่อ', inp('name', $v('name'), 320), true], ['หน่วยงาน / บริษัท / ร้าน', inp('org', $v('org'), 420), false],
        ['ที่อยู่', txt('address', $v('address'), 520), false], ['เลขประจำตัวผู้เสียภาษี / บัตรประชาชน', inp('taxid', $v('taxid'), 200), false],
        ['อีเมล (ใช้เข้าระบบ)', inp('email', $v('email'), 300, 'email'), true], ['เบอร์โทรศัพท์ (ใช้เข้าระบบได้)', inp('phone', $v('phone'), 200), true],
        ['บริการที่สนใจ / หมายเหตุ', txt('interest', $v('interest'), 520), false], ['สถานะ', sel('status', CUST_STATUS, $v('status') ?: 'active'), false],
        ['รหัสผ่านใหม่', inp('password', '', 200, 'password', 'autocomplete="new-password" placeholder="'.($id !== '' ? 'เว้นว่างถ้าไม่เปลี่ยน' : 'อย่างน้อย 8 ตัวอักษร').'"'), $id === '']];
    $billLink = $id !== '' ? '<p style="width:983px;margin:8px auto;text-align:left"><a href="'.h(a_url('bill', '&c='.rawurlencode($id))).'">ดูยอดที่ต้องชำระของผู้สมัครนี้ »</a></p>' : '';
    return [$id !== '' ? 'แก้ไขผู้สมัคร' : 'เพิ่มผู้สมัคร', ($err !== '' ? msg_box($err, false) : '').admin_form($id !== '' ? 'แก้ไขข้อมูลผู้สมัคร' : 'เพิ่มผู้สมัคร', a_url('cust', '&func=Edit&id='.rawurlencode($id)), $rows, a_url('cust')).$billLink];
}

/** อ่านฟอร์มข้อมูลผู้สมัคร: [ข้อมูล, ข้อความผิดพลาด] (ใช้ทั้งหน้าสมัครและหน้าแอดมิน) */
function cust_form_read(array $old, string $id, bool $admin): array
{
    $g = fn($k, $n = 200) => mb_substr(trim(str_replace("\r", '', (string) ($_POST[$k] ?? ''))), 0, $n);
    $d = $old;
    foreach (['name' => 120, 'org' => 160, 'address' => 400, 'taxid' => 20, 'interest' => 500] as $k => $n) {
        $d[$k] = $g($k, $n);
    }
    $d['email'] = cust_login_key($g('email', 120));
    $d['phone'] = preg_replace('/\D/', '', $g('phone', 20));
    $d['taxid'] = preg_replace('/\D/', '', $d['taxid']);
    if ($admin) {
        $d['status'] = isset(CUST_STATUS[(string) ($_POST['status'] ?? '')]) ? (string) $_POST['status'] : ($old['status'] ?? 'active');
    }
    $pw = (string) ($_POST['password'] ?? '');
    if ($d['name'] === '') {
        return [$d, 'กรุณากรอกชื่อ-นามสกุลผู้ติดต่อ'];
    }
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
        return [$d, 'รูปแบบอีเมลไม่ถูกต้อง'];
    }
    if (strlen($d['phone']) < 9 || strlen($d['phone']) > 10) {
        return [$d, 'เบอร์โทรศัพท์ต้องมี 9-10 หลัก'];
    }
    if (cust_taken($d['email'], $d['phone'], $id)) {
        return [$d, 'อีเมลหรือเบอร์โทรศัพท์นี้ถูกใช้สมัครแล้ว'];
    }
    if ($pw !== '' || $id === '') {
        if (strlen($pw) < 8) {
            return [$d, 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร'];
        }
        if (!$admin && $pw !== (string) ($_POST['password2'] ?? '')) {
            return [$d, 'ยืนยันรหัสผ่านไม่ตรงกัน'];
        }
        $d['pw'] = password_hash($pw, PASSWORD_DEFAULT);
    }
    return [$d, ''];
}

function adm_bill(bool $post)
{
    if (isset($_GET['cancel']) && ($b = bill_get((string) $_GET['cancel'])) && in_array($b['status'] ?? '', ['unpaid'], true)) {
        $b['status'] = 'cancel';
        bill_put($b['id'], $b);
        admin_done('bill');
    }
    $cf = (string) ($_GET['c'] ?? '');
    $st = isset(BILL_STATUS[(string) ($_GET['s'] ?? '')]) ? (string) $_GET['s'] : '';
    $custs = cust_all();
    $rows = '';
    $i = 0;
    $sum = 0.0;
    foreach (bill_all($cf !== '' ? $cf : null) as $id => $b) {
        if ($st !== '' && ($b['status'] ?? '') !== $st) {
            continue;
        }
        $sum += (float) $b['amount'];
        $c = $custs[(string) ($b['cid'] ?? '')] ?? null;
        $rows .= zrow([[$i + 1, ' align="center"'], $c ? h(cust_label($c)) : '<span class="gray_12">-</span>', h((string) $b['title']).(($b['contract'] ?? '') !== '' ? ' <span class="gray_12">(ตามสัญญา)</span>' : ''),
            [($b['due'] ?? '') !== '' ? th_date((string) $b['due'], ' ') : '-', ' style="white-space:nowrap"'], [money($b['amount']), ' align="right"'], pay_badge((string) ($b['status'] ?? ''), BILL_STATUS),
            [(($b['status'] ?? '') === 'unpaid' ? edit_link(a_url('bill', '&func=Edit&id='.rawurlencode($id))).' <a href="'.h(a_url('bill', '&cancel='.rawurlencode($id))).'" style="color:#C0392B" onclick="return confirm(\'ยกเลิกยอดนี้?\')">ยกเลิก</a>' : '')
                .(($b['slip'] ?? '') !== '' ? '<a href="'.h(a_url('slip', '&func=View&id='.rawurlencode((string) $b['slip']))).'">สลิป/ใบเสร็จ</a>' : ''), ' align="center" style="white-space:nowrap"']], $i++);
    }
    $cOpts = ['' => '- ผู้สมัครทุกราย -'];
    foreach ($custs as $k => $c) {
        $cOpts[$k] = cust_label($c);
    }
    $filter = pick_form('bill', ['c' => ['ผู้สมัคร', $cOpts, $cf], 's' => ['สถานะ', ['' => '- ทุกสถานะ -'] + BILL_STATUS, $st]]);
    return ['ยอดที่ต้องชำระ', $filter.list_table('ยอดที่ต้องชำระของผู้สมัคร/ผู้ซื้อบริการ', [['#', ' style="width:30px"'], 'ผู้สมัคร', 'รายการ', 'กำหนดชำระ', 'ยอดเงิน', 'สถานะ', ['', ' style="width:110px"']], $rows,
        $i.' รายการ รวม '.money($sum).' บาท', btn('กำหนดยอดที่ต้องชำระ', a_url('bill', '&func=Edit'), 'add')), head_btn('เพิ่ม', a_url('bill', '&func=Edit'), 'add')];
}

function adm_bill_Edit(bool $post)
{
    $id = (string) ($_GET['id'] ?? '');
    $b = $id !== '' ? bill_get($id) : null;
    if ($id !== '' && (!$b || ($b['status'] ?? '') !== 'unpaid')) {
        return ['แก้ไขยอด', msg_box('แก้ไขได้เฉพาะยอดที่ยังรอชำระ', false)];
    }
    $custs = array_filter(cust_all(), fn($c) => ($c['status'] ?? '') === 'active' || ($b && $c['id'] === ($b['cid'] ?? '')));
    $err = '';
    if ($post) {
        $cid = (string) ($_POST['cid'] ?? '');
        $amt = round((float) str_replace(',', '', (string) ($_POST['amount'] ?? '0')), 2);
        $due = be_date_to_iso(trim((string) ($_POST['due'] ?? '')), '');
        $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 200);
        if (!isset($custs[$cid])) {
            $err = 'กรุณาเลือกผู้สมัคร (ที่อนุมัติแล้ว)';
        } elseif ($title === '' || $amt <= 0) {
            $err = 'กรุณากรอกรายการและยอดเงินที่มากกว่า 0';
        } else {
            bill_put($id !== '' ? $id : new_id(), ['cid' => $cid, 'title' => $title, 'amount' => $amt, 'due' => $due, 'contract' => $b['contract'] ?? '', 'status' => 'unpaid',
                'created' => $b['created'] ?? date('Y-m-d H:i:s'), 'by' => (int) (me()['id'] ?? 0)]);
            admin_done('bill', '&c='.rawurlencode($cid));
        }
    }
    $b = $b ?? ['cid' => (string) ($_GET['c'] ?? ''), 'due' => date('Y-m-d')];
    $opts = ['' => '- เลือกผู้สมัคร -'];
    foreach ($custs as $k => $c) {
        $opts[$k] = cust_label($c);
    }
    $rows = [['ผู้สมัคร / ผู้ซื้อบริการ', sel('cid', $opts, (string) ($_POST['cid'] ?? $b['cid'] ?? ''), 'style="width:420px"'), true],
        ['รายการ', inp('title', (string) ($_POST['title'] ?? $b['title'] ?? ''), 520), true],
        ['ยอดเงิน (บาท)', inp('amount', (string) ($_POST['amount'] ?? $b['amount'] ?? ''), 140), true],
        ['กำหนดชำระ', inp('due', ($b['due'] ?? '') !== '' ? iso_to_be((string) $b['due']) : '', 120, 'text', 'placeholder="วว/ดด/ปปปป"'), false]];
    return [$id !== '' ? 'แก้ไขยอดที่ต้องชำระ' : 'กำหนดยอดที่ต้องชำระ', ($err !== '' ? msg_box($err, false) : '').admin_form('ยอดที่ต้องชำระ', a_url('bill', '&func=Edit&id='.rawurlencode($id)), $rows, a_url('bill'))
        .'<p style="width:983px;margin:8px auto;text-align:left" class="gray_12">ยอดตามสัญญารายเดือน/รายปี: ออกหนังสือสัญญาที่งานสารบรรณ แล้วกด "สร้างยอดชำระตามสัญญา" ในทะเบียนหนังสือ ระบบจะสร้างยอดทุกงวดให้</p>'];
}
