<?php
/**
 * mis/app/pages.php
 * เลือกหน้าตาม ?module=&option=&func= แบบระบบเดิม แล้วเติมข้อมูลลงแม่แบบ
 */
defined('MIS') or exit;

function page_meta(): array
{
    static $m = null;
    if ($m === null) {
        $m = json_decode((string) file_get_contents(MIS_APP.'pages/_meta.json'), true) ?: [];
    }
    return $m;
}

/** ชื่อแม่แบบจากพารามิเตอร์ของ URL */
function page_key(string $module, string $option, string $func): string
{
    if ($module === '' || $module === 'index') {
        return 'index_';
    }
    if (in_array($module, ['onlineTeacher', 'onlineStudent', 'profile'], true)) {
        return $module.'_';
    }
    $key = $module.'_'.$option;
    if ($func !== '' && is_file(MIS_APP.'pages/'.$key.'_'.$func.'.html')) {
        return $key.'_'.$func;
    }
    return $key;
}

function run_page(): void
{
    // เปลี่ยนภาคเรียนที่ดู (ฟอร์มปีการศึกษาบนหัวเว็บ)
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['select_active']) && preg_match('/^\d{4}\|[123]$/', $_POST['select_active'])) {
        setcookie('mis_term', $_POST['select_active'], ['path' => '/mis/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
        redirect($_SERVER['REQUEST_URI'] ?? 'index.php');
    }
    if (!me()) {
        redirect('../');
    }
    admin_room_override();
    // นักเรียนใช้ได้เฉพาะระบบสารสนเทศสำหรับนักเรียน
    if (is_student()) {
        redirect('/student/');
    }
    $module = preg_replace('/[^A-Za-z_]/', '', (string) ($_GET['module'] ?? ''));
    $option = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($_GET['option'] ?? ''));
    $func = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($_GET['func'] ?? ''));
    // หน้าผู้ดูแลระบบ
    if ($module === 'admin' || ($module === '' && is_admin() && empty($_COOKIE['mis_teacher_view']))) {
        run_admin($option, $func);
        return;
    }
    // พิมพ์เอกสารผลการเรียนของครู (เฉพาะวิชาที่สอน/ห้องที่ปรึกษา)
    if ($module === 'print') {
        run_print_page();
        return;
    }
    if ($module === '' && isset($_GET['admin']) && is_admin()) {
        setcookie('mis_teacher_view', '', ['expires' => time() - 3600, 'path' => '/mis/']);
        redirect('index.php');
    }
    $key = page_key($module, $option, $func);

    // การบันทึก (POST) ของแต่ละหน้า
    $action = 'act_'.$key.($func !== '' && strpos($key, $func) === false ? '_'.$func : '');
    if (function_exists($action)) {
        $action();
        return;
    }

    $meta = page_meta();
    $file = MIS_APP.'pages/'.($key === 'onlineStudent_' ? 'onlineTeacher_' : $key).'.html';
    if (!is_file($file)) {
        $key = 'index_';
        $file = MIS_APP.'pages/index_.html';
    }
    $tpl = (string) file_get_contents($file);
    $vars = page_vars();
    $provider = 'rows_'.$key;
    if ($func !== '' && strpos($key, $func) === false && function_exists('rows_'.$key.'_'.$func)) {
        $provider = 'rows_'.$key.'_'.$func;
    }
    $data = function_exists($provider) ? $provider() : [];
    if (is_string($data)) {
        $data = ['TBODY0' => $data];
    }
    if (isset($data['__CONTENT__'])) {
        $tpl = $data['__CONTENT__'];
        unset($data['__CONTENT__']);
    }
    if (isset($data['__SELECT__'])) {
        foreach ($data['__SELECT__'] as $name => $options) {
            $tpl = replace_select($tpl, $name, $options);
        }
        unset($data['__SELECT__']);
    }
    if (isset($data['__APPEND__'])) {
        $pos = strrpos($tpl, '</div>');
        $tpl = $pos === false ? $tpl.$data['__APPEND__'] : substr($tpl, 0, $pos).$data['__APPEND__'].substr($tpl, $pos);
        unset($data['__APPEND__']);
    }
    // แทนที่ทั้งก้อน (เช่นหน้าที่ต้องสร้างเนื้อหาใหม่ทั้งหมด)
    if (isset($data['__REPLACE__'])) {
        foreach ($data['__REPLACE__'] as $from => $to) {
            $tpl = str_replace($from, $to, $tpl);
        }
        unset($data['__REPLACE__']);
    }
    foreach ($data as $k => $v) {
        $vars['{{'.$k.'}}'] = $v;
    }
    for ($i = 0; $i < 6; $i++) {
        $vars += ['{{TBODY'.$i.'}}' => ''];
    }
    $content = strtr($tpl, $vars);
    // ตารางที่ไม่มีข้อมูล: ใส่แถว "ไม่พบข้อมูล" (สคริปต์เรียงตารางเดิมต้องมีอย่างน้อย 1 แถว)
    $content = preg_replace_callback('/(<thead>(?:(?!<\/thead>).)*<\/thead>\s*<tbody[^>]*>)\s*(<\/tbody>)/s', function ($m) {
        $n = max(1, preg_match_all('/<th\b/', $m[1]));
        $cells = '<td style="text-align:center;color:#999">ไม่พบข้อมูล</td>'.str_repeat('<td></td>', $n - 1);
        return $m[1]."\n <tr>$cells</tr>\n ".$m[2];
    }, $content);
    $title = $meta[$key]['title'] ?? 'แผงเมนูหลัก - ระบบบริหารจัดการสารสนเทศโรงเรียน';
    $title = preg_replace('/ - ระบบบริหารจัดการสารสนเทศโรงเรียน$/u', '', $title);
    if ($key === 'onlineStudent_') {
        $title = 'ข้อมูลการเข้าใช้งานระบบ';
    }
    $mod = explode('_', $key)[0];
    render_page($title, $content, $mod);
}


/* ---------------------------------------------------------------- ผู้ดูแล: ดูข้อมูลห้องใดก็ได้โดยไม่ต้องเป็นครูประจำชั้น */

function is_school_admin(): bool
{
    $u = me();
    return $u && ((int) $u['status'] === 1 || array_intersect(['main', 'academic', 'affairs'], user_modules()));
}

/** ห้องที่ผู้ดูแลเลือกดู (คุกกี้ mis_adv_room = ชั้น|ห้อง) แทนห้องประจำชั้นของตนเอง */
function admin_room_override(): void
{
    if (!is_school_admin()) {
        return;
    }
    if (isset($_GET['AdvRoom'])) {
        $v = preg_match('/^\d+\|\d+$/', (string) $_GET['AdvRoom']) ? (string) $_GET['AdvRoom'] : '';
        setcookie('mis_adv_room', $v, ['expires' => $v === '' ? time() - 3600 : 0, 'path' => '/mis/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
        $q = $_GET;
        unset($q['AdvRoom']);
        redirect('index.php'.($q ? '?'.http_build_query($q) : ''));
    }
    if (preg_match('/^(\d+)\|(\d+)$/', (string) ($_COOKIE['mis_adv_room'] ?? ''), $m)) {
        $u = me();
        $p = $u['personnel'] ?: ['id' => (int) $u['id']];
        $p['class'] = (int) $m[1];
        $p['room'] = (int) $m[2];
        $u['personnel'] = $p;
        $u['adv_override'] = true;
        me($u);
    }
}

/** แถบเลือกห้องสำหรับผู้ดูแล (แสดงในหน้างานครู) */
function admin_room_picker(): string
{
    if (!is_school_admin() || ($_GET['module'] ?? '') !== 'task') {
        return '';
    }
    $cur = preg_match('/^\d+\|\d+$/', (string) ($_COOKIE['mis_adv_room'] ?? '')) ? (string) $_COOKIE['mis_adv_room'] : '';
    $opt = '<option value="">- ห้องประจำชั้นของตนเอง -</option>';
    foreach (rooms_by_class() as $cid => $rooms) {
        foreach ($rooms as $rid => $n) {
            $v = (int) $cid.'|'.(int) $rid;
            $opt .= '<option value="'.$v.'"'.($v === $cur ? ' selected' : '').'>'.h($cid ? class_room_short($cid, $rid) : $n).'</option>';
        }
    }
    $hidden = '';
    foreach ($_GET as $k => $v) {
        if (is_string($v) && $k !== 'AdvRoom') {
            $hidden .= '<input type="hidden" name="'.h($k).'" value="'.h($v).'"/>';
        }
    }
    return '<div style="width:983px;margin:6px auto 0;text-align:left;background:#FFF8E1;border:1px solid #F5D78E;border-radius:4px;padding:5px 10px;font-size:13px">'
        .'<form method="get" action="index.php" style="margin:0">'.$hidden.'<i class="fa fa-user-secret"></i> ผู้ดูแลระบบ: ดูข้อมูลห้อง <select name="AdvRoom" onchange="this.form.submit()" style="height:22px">'.$opt.'</select>'
        .' <span style="color:#777">(ตรวจสอบและพิมพ์เอกสารของนักเรียนทุกห้องได้โดยไม่ต้องเป็นครูประจำชั้น)</span></form></div>';
}
