<?php
/**
 * mis/app/registry.php
 * ทะเบียนและข้อมูลหลัก (ตามคู่มือระบบเดิม):
 * ปีการศึกษา / ภาคเรียน (ปักหมุดภาคเรียนปัจจุบัน) / ระดับการศึกษา / ระดับชั้น (จำนวนห้อง หัวหน้าระดับ)
 * ทะเบียนนักเรียนปัจจุบัน (ค้นหา ส่งออก นำเข้า อัปเดตเบอร์โทร รูปประจำตัว) / สถานภาพนักเรียน
 * นักเรียนยังไม่กำหนดห้อง / จบการศึกษา / ออกกลางคัน / กำหนดครูที่ปรึกษา (2 คน + แผนการเรียน) / ข้อมูลการใช้งาน
 */
defined('MIS') or exit;

/* ================================================================ สถานภาพนักเรียน */

const STUDENT_STATUS = ['ศึกษาอยู่', 'ย้ายที่เรียน', 'จบการศึกษาภาคบังคับ', 'พ้นการศึกษา', 'ศึกษาต่อที่อื่น', 'พักการเรียน', 'แขวนลอย', 'ลาออก', 'จบการศึกษาระดับมัธยมศึกษาตอนปลาย'];
const GRAD_STATUS = ['จบการศึกษาภาคบังคับ', 'จบการศึกษาระดับมัธยมศึกษาตอนปลาย'];
/** ค่าเดิมจากรุ่นก่อน -> สถานภาพใหม่ */
const STATUS_LEGACY = ['กำลังศึกษา' => 'ศึกษาอยู่', 'เข้าใหม่' => 'ศึกษาอยู่', 'จบการศึกษา' => 'จบการศึกษาระดับมัธยมศึกษาตอนปลาย', 'ย้ายออก' => 'ย้ายที่เรียน', 'จำหน่าย' => 'พ้นการศึกษา'];

/** ข้อมูลเพิ่มเติมของนักเรียน (ชื่อเล่น วิธีเข้าเรียน ปีที่เข้า สถานภาพ วันที่ออก เบอร์โทร บาร์โค้ด) */
function stinfo(int $sid): array
{
    return json_decode((string) md_get('stinfo', $sid), true) ?: [];
}

function stinfo_save(int $sid, array $d): void
{
    md_set('stinfo', $sid, '', '', json_encode(array_merge(stinfo($sid), $d), JSON_UNESCAPED_UNICODE));
}

/** ข้อมูลเพิ่มเติมทุกคน [sid => data] */
function stinfo_all(): array
{
    static $all = null;
    if ($all === null) {
        $all = [];
        foreach (md_rows('stinfo') as $r) {
            $all[(int) $r['r1']] = json_decode((string) $r['value'], true) ?: [];
        }
    }
    return $all;
}

/** ตั้งสถานภาพ: ศึกษาอยู่ = ใช้งานได้ / อื่น ๆ = พ้นจากทะเบียนปัจจุบัน */
function set_student_status(int $sid, string $status, string $date = '', string $note = ''): void
{
    $status = STATUS_LEGACY[$status] ?? $status;
    if (!in_array($status, STUDENT_STATUS, true)) {
        throw new \RuntimeException('สถานภาพไม่ถูกต้อง: '.$status);
    }
    $d = ['status' => $status, 'left_date' => $status === 'ศึกษาอยู่' ? '' : ($date ?: date('Y-m-d'))];
    if ($note !== '') {
        $d['note'] = $note;
    }
    stinfo_save($sid, $d);
    q('UPDATE {user} SET `active`=? WHERE `id`=? AND `status`<>1', [$status === 'ศึกษาอยู่' ? 1 : 0, $sid]);
}

/** สถานภาพปัจจุบัน [sid => [status, date, entry, note]] (รวมข้อมูลรุ่นก่อน) */
function student_status(): array
{
    static $out = null;
    if ($out !== null) {
        return $out;
    }
    $out = [];
    foreach (md_rows('sstatus') as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        $sid = (int) ($d['student'] ?? 0);
        if ($sid && (!isset($out[$sid]) || ($d['date'] ?? '') >= ($out[$sid]['date'] ?? ''))) {
            $out[$sid] = ['status' => STATUS_LEGACY[$d['status'] ?? ''] ?? ($d['status'] ?? ''), 'date' => $d['date'] ?? '', 'entry' => $d['entry'] ?? '', 'note' => $d['note'] ?? ''];
        }
    }
    foreach (stinfo_all() as $sid => $d) {
        if (!empty($d['status'])) {
            $out[$sid] = ['status' => $d['status'], 'date' => $d['left_date'] ?? '', 'entry' => $d['entry'] ?? '', 'note' => $d['note'] ?? ''];
        }
    }
    return $out;
}

function status_badge(string $st): string
{
    $c = $st === '' || $st === 'ศึกษาอยู่' ? '#27AE60' : (in_array($st, GRAD_STATUS, true) ? '#2980B9' : '#E67E22');
    return '<span style="color:'.$c.'">'.h($st ?: 'ศึกษาอยู่').'</span>';
}

/* ================================================================ ปีการศึกษา / ภาคเรียน */

function cur_term(): array
{
    return [(int) cfg('academic_year', date('Y') + 543), (int) cfg('term', 1)];
}

/** ภาคเรียนทั้งหมด [ 'ปี/ภาค' => data ] เรียงใหม่ไปเก่า */
function all_terms(): array
{
    $t = [];
    foreach (md_rows('calendar') as $r) {
        if (preg_match('#^\d{4}/[123]$#', (string) $r['r1'])) {
            $t[(string) $r['r1']] = json_decode((string) $r['value'], true) ?: [];
        }
    }
    [$y, $s] = cur_term();
    $t[$y.'/'.$s] ??= [];
    uksort($t, fn($a, $b) => strcmp($b, $a));
    return $t;
}

function all_years(): array
{
    $y = [];
    foreach (md_rows('ayear') as $r) {
        $y[(int) $r['r1']] = json_decode((string) $r['value'], true) ?: [];
    }
    foreach (array_keys(all_terms()) as $k) {
        $y[(int) explode('/', $k)[0]] ??= [];
    }
    krsort($y);
    return $y;
}

function cur_badge(): string
{
    return ' <span style="background:#1ABC9C;color:#fff;border-radius:3px;padding:2px 8px;font-size:11px;white-space:nowrap">ปีการศึกษาปัจจุบัน</span>';
}

function adm_year(bool $post)
{
    $func = $_GET['func'] ?? '';
    [$cy] = cur_term();
    if ($post && $func === 'Save') {
        $y = (int) ($_POST['year'] ?? 0);
        if ($y >= 2450 && $y <= 2700) {
            $old = (int) ($_GET['id'] ?? 0);
            if ($old && $old !== $y) {
                md_set('ayear', $old, '', '', null);
            }
            md_set('ayear', $y, '', '', json_encode(['start' => imp_date((string) ($_POST['start'] ?? '')), 'end' => imp_date((string) ($_POST['end'] ?? ''))]));
        }
        admin_done('year');
    }
    if (isset($_GET['del']) && (int) $_GET['del'] !== $cy) {
        md_set('ayear', (int) $_GET['del'], '', '', null);
        admin_done('year');
    }
    if ($func === 'Edit') {
        $id = (int) ($_GET['id'] ?? 0);
        $d = $id ? (all_years()[$id] ?? []) : [];
        return ['เพิ่ม/แก้ไขปีการศึกษา', admin_form(($id ? 'แก้ไข' : 'เพิ่ม').'ปีการศึกษา', a_url('year', '&func=Save&id='.$id), [['ปีการศึกษา (พ.ศ.)', inp('year', $id ?: '', 80), true],
            ['วันเปิดปีการศึกษา', d_in('start', $d['start'] ?? '', 120), false], ['วันปิดปีการศึกษา', d_in('end', $d['end'] ?? '', 120), false]], a_url('year'))];
    }
    $rows = '';
    $i = 0;
    foreach (array_reverse(all_years(), true) as $y => $d) {
        $rows .= zrow([++$i, $y.($y === $cy ? cur_badge() : ''), !empty($d['start']) ? th_date($d['start'], ' ') : '-', !empty($d['end']) ? th_date($d['end'], ' ') : '-',
            [($y === $cy ? '' : del_link(a_url('year', '&del='.$y)).' ').edit_link(a_url('year', '&func=Edit&id='.$y)), ' align="center" width="60"']], $i);
    }
    $help = '<div style="width:983px;margin:0 auto 10px;text-align:left" class="gray_12">1. ปีการศึกษาเป็นข้อมูลแรกที่ต้องมีในระบบ และต้องกำหนดทุกครั้งที่เริ่มต้นปีการศึกษาใหม่<br>'
        .'2. วันที่เปิด-ปิดปีการศึกษาใช้แสดงข้อมูลให้ทราบ ไม่ได้นำไปใช้ในการประมวลผล<br>3. การกำหนดปีการศึกษาปัจจุบัน ทำได้ที่เมนู <a href="'.a_url('term').'">"ภาคเรียน"</a> โดยการปักหมุดภาคเรียนที่ต้องการ</div>';
    return ['กำหนดปีการศึกษา', $help.list_table('รายการปีการศึกษา', ['#', 'ปีการศึกษา', 'วันเปิดปีการศึกษา', 'วันปิดปีการศึกษา', ['', ' style="width:60px"']], $rows, 'ทั้งหมด '.$i.' รายการ', btn('เพิ่มปีการศึกษา', a_url('year', '&func=Edit'), 'add')),
        head_btn('เพิ่ม', a_url('year', '&func=Edit'), 'add')];
}

function adm_term(bool $post)
{
    $func = $_GET['func'] ?? '';
    [$cy, $ct] = cur_term();
    $curKey = $cy.'/'.$ct;
    if (isset($_GET['pin']) && preg_match('#^(\d{4})/([123])$#', (string) $_GET['pin'], $m)) {
        save_cfg(['academic_year' => (int) $m[1], 'term' => (int) $m[2]]);
        admin_done('term');
    }
    if (isset($_GET['del']) && (string) $_GET['del'] !== $curKey) {
        md_set('calendar', (string) $_GET['del'], '', '', null);
        admin_done('term');
    }
    if ($post && $func === 'Save') {
        $y = (int) ($_POST['year'] ?? 0);
        $t = (int) ($_POST['term'] ?? 0);
        if ($y >= 2450 && $y <= 2700 && $t >= 1 && $t <= 3) {
            $key = $y.'/'.$t;
            $old = (string) ($_GET['id'] ?? '');
            $cal = json_decode((string) md_get('calendar', $old !== '' ? $old : $key), true) ?: [];
            if ($old !== '' && $old !== $key) {
                md_set('calendar', $old, '', '', null);
            }
            foreach (['start', 'end', 'grade_date', 'fail_date'] as $k) {
                $cal[$k] = imp_date((string) ($_POST[$k] ?? ''));
            }
            md_set('calendar', $key, '', '', json_encode($cal, JSON_UNESCAPED_UNICODE));
            if (!md_get('ayear', $y)) {
                md_set('ayear', $y, '', '', json_encode(['start' => $cal['start'], 'end' => '']));
            }
        }
        admin_done('term');
    }
    if ($func === 'Edit') {
        $id = (string) ($_GET['id'] ?? '');
        $d = $id !== '' ? (all_terms()[$id] ?? []) : [];
        [$y, $t] = $id !== '' ? explode('/', $id) : [$cy, $ct];
        $yo = [];
        foreach (array_keys(all_years()) as $yy) {
            $yo[$yy] = $yy;
        }
        $yo[$cy + 1] ??= $cy + 1;
        krsort($yo);
        return ['เพิ่ม/แก้ไขภาคเรียน', admin_form(($id !== '' ? 'แก้ไข' : 'เพิ่ม').'ภาคเรียน', a_url('term', '&func=Save&id='.rawurlencode($id)), [['ปีการศึกษา', sel('year', $yo, $y), true],
            ['ภาคเรียน', sel('term', ['1' => '1', '2' => '2', '3' => '3 (ภาคฤดูร้อน)'], $t), true], ['วันเปิดภาคเรียน', d_in('start', $d['start'] ?? '', 120), false], ['วันปิดภาคเรียน', d_in('end', $d['end'] ?? '', 120), false],
            ['วันประกาศผลการเรียน (แสดงในหน้านักเรียน)', d_in('grade_date', $d['grade_date'] ?? '', 120), false], ['วันประกาศผลสอบไม่ผ่าน', d_in('fail_date', $d['fail_date'] ?? '', 120), false]], a_url('term'))];
    }
    $rows = '';
    $i = 0;
    foreach (array_reverse(all_terms(), true) as $k => $d) {
        [$y, $t] = explode('/', $k);
        $cur = $k === $curKey;
        $rows .= zrow([++$i, h($y), h($t).($cur ? cur_badge() : ''), !empty($d['start']) ? th_date($d['start'], ' ') : '-', !empty($d['end']) ? th_date($d['end'], ' ') : '-',
            [($cur ? '' : del_link(a_url('term', '&del='.rawurlencode($k))).' ').edit_link(a_url('term', '&func=Edit&id='.rawurlencode($k))).' '
                .($cur ? '<i class="fa fa-thumb-tack" style="color:#ccc;font-size:16px" title="ภาคเรียนปัจจุบัน"></i>' : '<a href="javascript:void(0)" title="กำหนดเป็นภาคเรียนปัจจุบัน" onclick="pinTerm(\''.h(a_url('term', '&pin='.rawurlencode($k))).'\',\''.$t.'/'.$y.'\')"><i class="fa fa-thumb-tack" style="color:#333;font-size:16px"></i></a>'),
                ' align="center" width="80"']], $i);
    }
    $help = '<script>function pinTerm(u,t){var go=function(r){if(r)location.href=u};if(window.AlertBox&&AlertBox.confirm){AlertBox.confirm("<br>กำหนดภาคเรียน "+t+" เป็นภาคเรียนปัจจุบัน?","",{onComplete:go})}else{go(confirm("กำหนดภาคเรียน "+t+" เป็นภาคเรียนปัจจุบัน?"))}}</script>'
        .'<div style="width:983px;margin:0 auto 10px;text-align:left" class="gray_12">คลิกไอคอน <i class="fa fa-thumb-tack"></i> ในภาคเรียนที่ต้องการ เพื่อกำหนดให้ภาคเรียนนั้นเป็นภาคเรียนปัจจุบัน เมื่อผู้ใช้เข้าสู่ระบบ ภาคเรียน/ปีการศึกษาปัจจุบันจะถูกกำหนดตามที่ปักหมุดไว้เสมอ<br>'
        .'ผู้ใช้ยังเลือกดูข้อมูลย้อนหลังได้จาก "ปีการศึกษา" ด้านบนของหน้าจอ (เป็นการเรียกดูชั่วคราว ภาคเรียนปัจจุบันยังเป็นภาคเรียนที่ปักหมุดไว้เช่นเดิม)</div>';
    return ['กำหนดภาคเรียนปัจจุบัน', $help.list_table('รายการภาคเรียน', ['#', 'ปีการศึกษา', 'ภาคเรียน', 'วันเปิดภาคเรียน', 'วันปิดภาคเรียน', ['', ' style="width:80px"']], $rows, 'ทั้งหมด '.$i.' รายการ',
        btn('เพิ่มภาคเรียน', a_url('term', '&func=Edit'), 'add')), head_btn('เพิ่ม', a_url('term', '&func=Edit'), 'add')];
}

/* ================================================================ ระดับการศึกษา / ระดับชั้น */

function adm_edu_level(bool $post)
{
    return cat_md('edu_level', 'edu_level', 'ระดับการศึกษา', ['name' => f_text('ระดับการศึกษา', true), 'short' => f_text('ชื่อย่อ', true)], ['name' => 'ระดับการศึกษา', 'short' => 'ชื่อย่อ'], $post,
        seed_rows(['name', 'short'], [['มัธยมศึกษาตอนต้น', 'ม.ต้น'], ['มัธยมศึกษาตอนปลาย', 'ม.ปลาย']]));
}

const CLASS_NAMES = ['อนุบาล 1', 'อนุบาล 2', 'อนุบาล 3', 'ประถมศึกษาปีที่ 1', 'ประถมศึกษาปีที่ 2', 'ประถมศึกษาปีที่ 3', 'ประถมศึกษาปีที่ 4', 'ประถมศึกษาปีที่ 5', 'ประถมศึกษาปีที่ 6',
    'มัธยมศึกษาปีที่ 1', 'มัธยมศึกษาปีที่ 2', 'มัธยมศึกษาปีที่ 3', 'มัธยมศึกษาปีที่ 4', 'มัธยมศึกษาปีที่ 5', 'มัธยมศึกษาปีที่ 6'];

/** ลำดับของระดับชั้น (ใช้ตั้งชื่อห้อง ลำดับ/เลขห้อง) */
function class_order(int $cid): int
{
    $i = array_search($cid, array_map('intval', array_keys(cats('class'))), true);
    return $i === false ? 0 : $i + 1;
}

function adm_class(bool $post)
{
    $func = $_GET['func'] ?? '';
    if ($post && $func === 'Save') {
        $id = (int) ($_GET['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name !== '') {
            if (!$id && ($found = array_search($name, cats('class'), true)) !== false) {
                $id = (int) $found;
            }
            cat_save('class', $id ?: null, $name);
            if (!$id) {
                $id = (int) (q1('SELECT MAX(CAST(`category_id` AS UNSIGNED)) m FROM {category} WHERE `type`=\'class\'')['m'] ?? 0);
            }
            $n = max(0, min(30, (int) ($_POST['rooms'] ?? 0)));
            md_set('class_info', $id, '', '', json_encode(['edu' => (string) ($_POST['edu'] ?? ''), 'rooms' => $n, 'head' => (string) (int) ($_POST['head'] ?? 0)]));
            // สร้างห้องเรียนที่ยังไม่มี เช่น ม.1 มี 3 ห้อง -> 1/1 1/2 1/3
            $ord = class_order($id);
            $rooms = cats('room');
            for ($k = 1; $ord && $k <= $n; $k++) {
                if (!in_array($ord.'/'.$k, $rooms, true)) {
                    cat_save('room', null, $ord.'/'.$k);
                }
            }
        }
        admin_done('class');
    }
    if (isset($_GET['del'])) {
        $id = (int) $_GET['del'];
        if (!q1('SELECT 1 x FROM {student} WHERE `class`=? LIMIT 1', [$id])) {
            q('DELETE FROM {category} WHERE `type`=\'class\' AND `category_id`=?', [(string) $id]);
            md_set('class_info', $id, '', '', null);
        }
        admin_done('class');
    }
    $edu = md_options('edu_level', 'name');
    if ($func === 'Edit') {
        $id = (int) ($_GET['id'] ?? 0);
        $d = json_decode((string) md_get('class_info', $id), true) ?: [];
        $name = $id ? cat('class', $id) : '';
        $names = array_combine(CLASS_NAMES, CLASS_NAMES);
        if ($name !== '' && !isset($names[$name])) {
            $names = [$name => $name] + $names;
        }
        return ['กำหนดระดับชั้น', '<div style="width:983px;margin:0 auto 10px;text-align:left" class="gray_12">ระบุจำนวนห้องเรียนภายในระดับชั้น ระบบจะสร้างห้องเรียนให้อัตโนมัติ (เช่น ม.1 จำนวน 3 ห้อง = 1/1, 1/2, 1/3) จากนั้นเลือกหัวหน้าระดับชั้น สำหรับนำไปแสดงในรายงานต่าง ๆ</div>'
            .admin_form(($id ? 'แก้ไข' : 'เพิ่ม').'ระดับชั้น', a_url('class', '&func=Save&id='.$id), [['ระดับการศึกษา', sel('edu', ['' => 'กรุณาเลือกระดับการศึกษา'] + $edu, $d['edu'] ?? ''), true],
            ['ระดับชั้น', sel('name', ['' => 'กรุณาเลือกระดับชั้น'] + $names, $name), true], ['จำนวนห้องเรียน', inp('rooms', $d['rooms'] ?? count(rooms_by_class()[$id] ?? []), 80).' <span class="gray_12">(ตัวเลขเท่านั้น)</span>', true],
            ['หัวหน้าระดับชั้น', sel('head', ['0' => 'เลือกผู้ดำรงตำแหน่ง'] + personnel_options(), $d['head'] ?? 0), false]], a_url('class'))];
    }
    $po = personnel_options();
    $rbc = rooms_by_class();
    $rows = '';
    $i = 0;
    foreach (cats('class') as $id => $name) {
        $d = json_decode((string) md_get('class_info', $id), true) ?: [];
        $rows .= zrow([++$i, h($name), h(class_short((int) $id)), h($edu[$d['edu'] ?? ''] ?? '-'), [count($rbc[$id] ?? []), ' align="center"'], h($po[$d['head'] ?? 0] ?? '-'),
            [del_link(a_url('class', '&del='.(int) $id)).' '.edit_link(a_url('class', '&func=Edit&id='.(int) $id)), ' align="center" width="60"']], $i);
    }
    return ['กำหนดระดับชั้น', list_table('รายการระดับชั้น', ['#', 'ระดับชั้น', 'ชื่อย่อ', 'ระดับการศึกษา', ['จำนวนห้อง', ' style="text-align:center"'], 'หัวหน้าระดับชั้น', ['', ' style="width:60px"']], $rows,
        'ทั้งหมด '.$i.' รายการ', btn('เพิ่มระดับชั้น', a_url('class', '&func=Edit'), 'add')), head_btn('เพิ่ม', a_url('class', '&func=Edit'), 'add')];
}

/* ================================================================ ทะเบียนนักเรียนปัจจุบัน */

function student_query(array $f): array
{
    $sql = 'SELECT S.*, U.`name`, U.`username`, U.`active`, U.`sex`, U.`phone`, U.`birthday` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE 1';
    $p = [];
    if (($f['all'] ?? '') !== '1') {
        $sql .= ' AND U.`active`=1';
    }
    foreach (['c' => 'class', 'r' => 'room'] as $k => $col) {
        if (($f[$k] ?? '') !== '' && (int) $f[$k]) {
            $sql .= " AND S.`$col`=?";
            $p[] = (int) $f[$k];
        }
    }
    $rows = q($sql.' ORDER BY S.`class`, S.`room`, S.`number`, S.`student_id`', $p);
    $qt = trim((string) ($f['q'] ?? ''));
    if ($qt !== '') {
        $info = stinfo_all();
        $entry = md_options('entry_type', 'name');
        $rows = array_values(array_filter($rows, function ($s) use ($qt, $info, $entry) {
            $i = $info[(int) $s['id']] ?? [];
            $hay = implode(' ', [$s['student_id'], $s['name'], $s['id_card'], $i['nickname'] ?? '', $entry[$i['entry'] ?? ''] ?? '', $i['barcode'] ?? '']);
            return mb_stripos($hay, $qt) !== false;
        }));
    }
    return $rows;
}

function adm_student(bool $post)
{
    // ลบหลายรายการ
    if ($post && isset($_POST['del_ids'])) {
        foreach ((array) $_POST['del_ids'] as $id) {
            delete_student((int) $id);
        }
        admin_done('student', '&'.http_build_query(array_intersect_key($_GET, array_flip(['c', 'r', 'q', 'all']))));
    }
    $f = ['c' => (string) ($_GET['c'] ?? ''), 'r' => (string) ($_GET['r'] ?? ''), 'q' => (string) ($_GET['q'] ?? ''), 'all' => (string) ($_GET['all'] ?? '')];
    $list = student_query($f);
    if (isset($_GET['export'])) {
        student_export($list);
        return null;
    }
    $info = stinfo_all();
    $st = student_status();
    $entry = md_options('entry_type', 'name');
    $rows = '';
    foreach (array_slice($list, 0, 1500) as $i => $s) {
        $id = (int) $s['id'];
        [$pre, $first, $last] = split_name($s['name']);
        $in = $info[$id] ?? [];
        $rows .= zrow([$i + 1, h($s['student_id']), h($pre.$first), h($last), h($in['nickname'] ?? ''), status_badge((int) $s['active'] ? ($st[$id]['status'] ?? 'ศึกษาอยู่') : ($st[$id]['status'] ?? 'พ้นการศึกษา')),
            h(class_room_full((int) $s['class'], (int) $s['room'])), [h($s['number']), ' align="center"'], h($entry[$in['entry'] ?? ''] ?? ''),
            ['<input type="checkbox" name="del_ids[]" value="'.$id.'"/>', ' align="center"'],
            [del_link(a_url('student', '&func=Delete&id='.$id)).' '.edit_link(a_url('student', '&func=Edit&id='.$id)).' <a href="'.a_url('student', '&func=View&id='.$id).'" title="ดูข้อมูล"><i class="fa fa-search" style="color:#333;font-size:15px"></i></a>', ' align="center" width="70"']], $i);
    }
    $qs = http_build_query(array_filter($f, fn($v) => $v !== ''));
    $rooms = ['' => 'ทุกห้อง'];
    foreach (rooms_by_class() as $cid => $rs) {
        if ($f['c'] === '' || (int) $f['c'] === (int) $cid) {
            foreach ($rs as $rid => $n) {
                $rooms[$rid] = $cid ? class_room_short($cid, $rid) : $n;
            }
        }
    }
    $form = '<form method="get" style="width:983px;margin:0 auto 10px;text-align:left"><input type="hidden" name="module" value="admin"/><input type="hidden" name="option" value="student"/><span class="search">'
        .sel('c', ['' => 'เลือกระดับชั้น'] + cats('class'), $f['c'], 'onchange="this.form.r.value=\'\';this.form.submit()"').' '.sel('r', $rooms, $f['r'], 'onchange="this.form.submit()"').' '
        .inp('q', $f['q'], 220, 'text', 'placeholder="ค้นหานักเรียน (รหัส ชื่อ ชื่อเล่น เลขบัตร วิธีเข้าเรียน)"').' <label><input type="checkbox" name="all" value="1"'.($f['all'] === '1' ? ' checked' : '').' onchange="this.form.submit()"/> รวมนักเรียนที่พ้นสภาพ</label>'
        .' <input type="submit" value="ค้นหา" class="general radius" style="height:25px"/></span><div style="clear:both"></div></form>';
    $tools = head_btn('อัพเดต', a_url('csv_import').'#phones', 'reload').head_btn('นำเข้าไฟล์', a_url('csv_import').'#students', 'download').head_btn('ส่งออกไฟล์', a_url('student', '&export=1&'.$qs), 'upload')
        .head_btn('เพิ่ม', a_url('student', '&func=Edit'), 'add').'<div style="float:left;margin:0px 2px"><a href="javascript:void(0)" onclick="return misConfirm(this,\'ลบนักเรียนที่เลือก (รวมผลการเรียน)?\',function(){document.forms.stlist.submit()})" style="display:inline-block;width:58px;height:56px;border:1px solid #ddd;border-radius:6px;background:#fafafa;text-align:center;font-size:11px;color:#555;padding-top:4px"><i class="fa fa-times-circle" style="font-size:24px;color:#E74C3C;margin-top:4px"></i><br>ลบ</a></div>';
    return ['กำหนดทะเบียนนักเรียนปัจจุบัน', $form.'<form id="stlist" name="stlist" method="post" action="'.h(a_url('student', '&'.$qs)).'">'.list_table('รายการทะเบียนนักเรียนปัจจุบัน ประจำปีการศึกษา '.explode('/', yt())[0],
        ['#', 'รหัสนักเรียน', 'ชื่อ', 'นามสกุล', 'ชื่อเล่น', 'สถานภาพ', 'ปัจจุบันอยู่ชั้น', 'เลขที่', 'วิธีการเข้าเรียน', ['<input type="checkbox" onclick="var c=document.querySelectorAll(\'input[name^=del_ids]\');for(var i=0;i<c.length;i++)c[i].checked=this.checked"/>', ' style="text-align:center"'], ['', ' style="width:70px"']],
        $rows, 'ทั้งหมด '.count($list).' รายการ').'</form>', $tools];
}

function class_room_full(int $cid, int $rid): string
{
    $c = $cid ? cat('class', $cid) : '';
    if (!$rid) {
        return $c ?: '-';
    }
    $r = room_short($rid);
    $num = strpos($r, '/') !== false ? substr($r, strrpos($r, '/') + 1) : $r;
    return $c !== '' ? $c.'/'.$num : $r;
}

/** ส่งออก CSV (โครงสร้างเดียวกับไฟล์นำเข้า "ทะเบียนนักเรียน" ใช้แก้แล้วนำเข้ากลับได้) */
function student_export(array $list): void
{
    $info = stinfo_all();
    $entry = md_options('entry_type', 'name');
    $line = fn($a) => implode(',', array_map(fn($v) => '"'.str_replace('"', '""', (string) $v).'"', $a))."\r\n";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="students_'.date('Ymd').'.csv"');
    header('Cache-Control: no-store');
    echo "\xEF\xBB\xBF".$line(array_merge(array_keys(import_sets()['students'][2])));
    foreach ($list as $s) {
        [$pre, $first, $last] = split_name($s['name']);
        $in = $info[(int) $s['id']] ?? [];
        echo $line([$s['student_id'], $pre, $first, $last, $s['sex'] === 'f' ? 'ญ' : ($s['sex'] === 'm' ? 'ช' : ''), cat('class', $s['class']), room_short((int) $s['room']), $s['number'], $s['id_card'],
            !empty($s['birthday']) && $s['birthday'] !== '0000-00-00' ? iso_to_be($s['birthday']) : '', $s['parent'], $s['parent_phone'], $s['address'], $in['nickname'] ?? '', $entry[$in['entry'] ?? ''] ?? '', $in['entry_year'] ?? '']);
    }
}

function delete_student(int $id): void
{
    if ($id && q1('SELECT 1 x FROM {student} WHERE `id`=?', [$id])) {
        q('DELETE FROM {grade} WHERE `student_id`=?', [$id]);
        q('DELETE FROM {student} WHERE `id`=?', [$id]);
        q('DELETE FROM {user_session} WHERE `member_id`=?', [$id]);
        q('DELETE FROM {user} WHERE `id`=? AND `status`<>1', [$id]);
        md_set('stinfo', $id, '', '', null);
    }
}

function adm_student_Delete(bool $post)
{
    delete_student((int) ($_GET['id'] ?? 0));
    admin_done('student');
}

/** บันทึกรูปประจำตัว (datas/avatar/{id}.ext ที่เดียวกับ SMS) */
function save_photo(int $id, string $field = 'photo'): void
{
    if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return;
    }
    $info = @getimagesize($_FILES[$field]['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($types[$info[2]])) {
        return;
    }
    $dir = ROOT_PATH.DATA_FOLDER.'avatar/';
    @mkdir($dir, 0755, true);
    $ext = ltrim((string) (\Kotchasan::$cfg->stored_img_type ?? '.jpg'), '.') ?: 'jpg';
    $size = (int) (\Kotchasan::$cfg->member_img_size ?? 250) ?: 250;
    foreach (glob($dir.$id.'.*') ?: [] as $old) {
        @unlink($old);
    }
    if (function_exists('imagecreatefromstring') && ($src = @imagecreatefromstring((string) file_get_contents($_FILES[$field]['tmp_name'])))) {
        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $dst = imagecreatetruecolor($size, $size);
        imagecopyresampled($dst, $src, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 4), $size, $size, $side, $side);
        $ok = match ($ext) {
            'png' => imagepng($dst, $dir.$id.'.png'),
            'webp' => function_exists('imagewebp') && imagewebp($dst, $dir.$id.'.webp', 85),
            default => imagejpeg($dst, $dir.$id.'.jpg', 85),
        };
        if ($ok) {
            return;
        }
    }
    move_uploaded_file($_FILES[$field]['tmp_name'], $dir.$id.'.'.$types[$info[2]]);
}

function adm_student_Edit(bool $post)
{
    $id = (int) ($_GET['id'] ?? 0);
    $s = $id ? q1('SELECT S.*, U.`name`, U.`username`, U.`active`, U.`sex`, U.`phone`, U.`birthday`, U.`id_card` uid FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`id`=?', [$id]) : null;
    [$pre, $first, $last] = split_name((string) ($s['name'] ?? ''));
    $in = $id ? stinfo($id) : [];
    $st = $id ? (student_status()[$id]['status'] ?? ((int) ($s['active'] ?? 1) ? 'ศึกษาอยู่' : 'พ้นการศึกษา')) : 'ศึกษาอยู่';
    $rooms = ['0' => '- เลือก -'];
    foreach (rooms_by_class() as $cid => $rs) {
        foreach ($rs as $rid => $n) {
            $rooms[$rid] = $cid ? cat('class', $cid).'/'.preg_replace('/^\d+\//', '', $n) : $n;
        }
    }
    [$cy] = cur_term();
    $years = [];
    for ($y = $cy; $y >= min($cy - 12, 2554); $y--) {
        $years[$y] = $y;
    }
    $photo = '<img src="'.h($id ? avatar_url($id) : '../images/man_nopic.png').'" style="width:110px;height:130px;object-fit:cover;border:1px solid #ccc;border-radius:4px;display:block;margin-bottom:5px"/><input type="file" name="photo" accept="image/jpeg,image/png,image/webp"/>';
    $rows = [
        ['รูปภาพประจำตัว', $photo, false],
        ['รหัสนักเรียน', inp('student_id', $s['student_id'] ?? '', 150).' <span class="gray_12">(ตัวเลขเท่านั้น)</span>', true],
        ['เลขที่บัตรประจำตัวประชาชน', inp('id_card', $s['id_card'] ?? ($s['uid'] ?? ''), 200), false],
        ['เพศ', '<label><input type="radio" name="sex" value="m"'.(($s['sex'] ?? '') === 'm' ? ' checked' : '').'/> เพศชาย</label> &nbsp; <label><input type="radio" name="sex" value="f"'.(($s['sex'] ?? '') === 'f' ? ' checked' : '').'/> เพศหญิง</label>', true],
        ['คำนำหน้าชื่อ', sel('title', title_options(), $pre), true],
        ['ชื่อนักเรียน', inp('first', $first), true], ['นามสกุล', inp('last', $last), true], ['ชื่อเล่น', inp('nickname', $in['nickname'] ?? '', 150), false],
        ['รหัสบาร์โค้ด', inp('barcode', $in['barcode'] ?? '', 200, 'text', 'placeholder="เว้นว่าง = ใช้รหัสนักเรียน"'), false],
        ['ชั้นเรียน', sel('room', $rooms, $s['room'] ?? 0, 'style="width:260px;height:25px"'), true], ['เลขที่', inp('number', $s['number'] ?? '', 80), true],
        ['ประจำปีการศึกษา (ปีที่เข้าเรียน)', sel('entry_year', $years, $in['entry_year'] ?? $cy), true],
        ['วิธีการเข้าเรียน', sel('entry', ['' => 'เลือกวิธีการเข้าเรียน'] + md_options('entry_type', 'name'), $in['entry'] ?? ''), false],
        ['สถานภาพ', sel('status', array_combine(STUDENT_STATUS, STUDENT_STATUS), $st), true],
        ['วันที่ออกจากโรงเรียน', d_in('left_date', $in['left_date'] ?? '', 120).' <span class="gray_12">(กรณีไม่ใช่ "ศึกษาอยู่")</span>', false],
        ['วันเกิด', d_in('birthday', !empty($s['birthday']) && $s['birthday'] !== '0000-00-00' ? $s['birthday'] : '', 120), false],
        ['เบอร์โทรศัพท์นักเรียน', inp('phone', $s['phone'] ?? '', 200), false], ['ที่อยู่', txt('address', $s['address'] ?? ''), false],
        ['ชื่อผู้ปกครอง', inp('parent', $s['parent'] ?? ''), false], ['ผู้ปกครอง: มือถือ / บ้าน', inp('parent_phone', $s['parent_phone'] ?? '', 140).' '.inp('guardian_home', $in['guardian_home'] ?? '', 140), false],
        ['บิดา: มือถือ / บ้าน', inp('father_mobile', $in['father_mobile'] ?? '', 140).' '.inp('father_home', $in['father_home'] ?? '', 140), false],
        ['มารดา: มือถือ / บ้าน', inp('mother_mobile', $in['mother_mobile'] ?? '', 140).' '.inp('mother_home', $in['mother_home'] ?? '', 140), false],
        ['รหัสผ่านเข้าระบบ', inp('password', '', 200, 'password', 'autocomplete="new-password" placeholder="'.($s ? 'เว้นว่างถ้าไม่เปลี่ยน' : 'ค่าเริ่มต้น = รหัสนักเรียน').'"'), false],
    ];
    $note = '<div style="width:983px;margin:0 auto 10px;text-align:left" class="gray_12">รหัสนักเรียนต้องไม่ซ้ำกัน / หากสถานภาพไม่ใช่ "ศึกษาอยู่" รายชื่อจะย้ายไปแสดงที่เมนู "นักเรียนจบการศึกษา" หรือ "นักเรียนออกกลางคัน"</div>';
    return [$s ? 'แก้ไขข้อมูลนักเรียน' : 'เพิ่มนักเรียน', $note.admin_form($s ? 'แก้ไขข้อมูลนักเรียน' : 'เพิ่มนักเรียน', a_url('student', '&func=Save&id='.$id.(isset($_GET['back']) ? '&back='.rawurlencode((string) $_GET['back']) : '')), $rows, a_url((string) ($_GET['back'] ?? 'student')))];
}

function adm_student_Save(bool $post)
{
    $id = (int) ($_GET['id'] ?? 0);
    $code = trim((string) ($_POST['student_id'] ?? ''));
    $first = trim((string) ($_POST['first'] ?? ''));
    $last = trim((string) ($_POST['last'] ?? ''));
    if (!$post || $code === '' || $first === '' || username_taken($code, $id)) {
        redirect(a_url('student', '&func=Edit&id='.$id));
    }
    $card = preg_replace('/\D/', '', (string) ($_POST['id_card'] ?? ''));
    $status = in_array($_POST['status'] ?? '', STUDENT_STATUS, true) ? (string) $_POST['status'] : 'ศึกษาอยู่';
    $uid = save_user($id, ['username' => $code, 'name' => trim(($_POST['title'] ?? '').$first.' '.$last), 'sex' => in_array($_POST['sex'] ?? '', ['m', 'f'], true) ? $_POST['sex'] : null,
        'phone' => trim((string) ($_POST['phone'] ?? '')), 'birthday' => imp_date((string) ($_POST['birthday'] ?? '')) ?: null, 'status' => 0, 'active' => $status === 'ศึกษาอยู่' ? 1 : 0],
        (string) ($_POST['password'] ?? '') !== '' ? (string) $_POST['password'] : ($id ? '' : $code));
    $room = (int) ($_POST['room'] ?? 0);
    $cid = 0;
    foreach (rooms_by_class() as $c => $rs) {
        if (isset($rs[$room])) {
            $cid = (int) $c;
        }
    }
    $st = [$code, mb_substr((string) ($_POST['address'] ?? ''), 0, 100), mb_substr((string) ($_POST['parent'] ?? ''), 0, 100), mb_substr((string) ($_POST['parent_phone'] ?? ''), 0, 32),
        $cid, $room, ($_POST['number'] ?? '') === '' ? null : (int) $_POST['number'], strlen($card) === 13 ? $card : null];
    if (q1('SELECT 1 x FROM {student} WHERE `id`=?', [$uid])) {
        q('UPDATE {student} SET `student_id`=?, `address`=?, `parent`=?, `parent_phone`=?, `class`=?, `room`=?, `number`=?, `id_card`=? WHERE `id`=?', array_merge($st, [$uid]));
    } else {
        q('INSERT INTO {student} (`student_id`,`address`,`parent`,`parent_phone`,`class`,`room`,`number`,`id_card`,`department`,`id`) VALUES (?,?,?,?,?,?,?,?,0,?)', array_merge($st, [$uid]));
    }
    $d = [];
    foreach (['nickname', 'barcode', 'entry', 'entry_year', 'guardian_home', 'father_mobile', 'father_home', 'mother_mobile', 'mother_home'] as $k) {
        $d[$k] = mb_substr(trim((string) ($_POST[$k] ?? '')), 0, 60);
    }
    stinfo_save($uid, $d);
    set_student_status($uid, $status, imp_date((string) ($_POST['left_date'] ?? '')));
    save_photo($uid);
    admin_done(preg_replace('/[^a-z_]/', '', (string) ($_GET['back'] ?? 'student')) ?: 'student');
}

/** ดูข้อมูลนักเรียน */
function adm_student_View(bool $post)
{
    $id = (int) ($_GET['id'] ?? 0);
    $s = student_head($id);
    if (!$s) {
        redirect(a_url('student'));
    }
    $in = stinfo($id);
    $st = student_status()[$id]['status'] ?? 'ศึกษาอยู่';
    $entry = md_options('entry_type', 'name');
    $rows = '';
    $i = 0;
    foreach (['รหัสนักเรียน' => $s['student_id'], 'ชื่อ-นามสกุล' => $s['name'], 'ชื่อเล่น' => $in['nickname'] ?? '', 'เลขประจำตัวประชาชน' => $s['id_card'], 'วันเกิด' => !empty($s['birthday']) && $s['birthday'] !== '0000-00-00' ? th_date($s['birthday'], ' ') : '',
        'ชั้น' => class_room_short($s['class'], $s['room']).' เลขที่ '.$s['number'], 'สถานภาพ' => $st, 'วิธีการเข้าเรียน' => $entry[$in['entry'] ?? ''] ?? '', 'ปีที่เข้าเรียน' => $in['entry_year'] ?? '',
        'ผู้ปกครอง' => $s['parent'].' โทร '.$s['parent_phone'].(!empty($in['guardian_home']) ? ' / '.$in['guardian_home'] : ''), 'บิดา' => trim(($in['father_mobile'] ?? '').' '.($in['father_home'] ?? '')),
        'มารดา' => trim(($in['mother_mobile'] ?? '').' '.($in['mother_home'] ?? '')), 'ที่อยู่' => $s['address']] as $k => $v) {
        $rows .= zrow([h($k), h((string) $v)], $i++);
    }
    $gpa = gpax_current(student_grades($id), $s['class']);
    $rows .= zrow(['ผลการเรียนเฉลี่ยสะสม', $gpa === null ? '-' : number_format($gpa, 2).' (<a href="'.a_url('grade_person', '&student='.$id).'">ดูผลการเรียน</a>)'], $i);
    return ['ข้อมูลนักเรียน', '<div style="width:983px;margin:0 auto 10px;text-align:left"><img src="'.h(avatar_url($id)).'" style="width:110px;height:130px;object-fit:cover;border:1px solid #ccc;border-radius:4px"/></div>'
        .list_table(h($s['name']), ['รายการ', 'ข้อมูล'], $rows, '', btn('แก้ไข', a_url('student', '&func=Edit&id='.$id), 'edit').' '.print_btn())];
}

/* ================================================================ สถานภาพ / ยังไม่กำหนดห้อง / จบ / ออกกลางคัน */

function adm_sstatus(bool $post)
{
    if ($post) {
        $sid = (int) ($_POST['student'] ?? 0);
        if ($sid) {
            try {
                set_student_status($sid, (string) ($_POST['status'] ?? ''), imp_date((string) ($_POST['date'] ?? '')), mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 200));
            } catch (\RuntimeException $e) {
            }
        }
        admin_done('sstatus');
    }
    $all = [];
    foreach (q('SELECT S.`id`, S.`student_id`, S.`class`, S.`room`, U.`name` FROM {student} S JOIN {user} U ON U.`id`=S.`id` ORDER BY S.`class`, S.`room`, S.`number`') as $s) {
        $all[$s['id']] = $s['student_id'].' '.$s['name'].' ('.class_room_short($s['class'], $s['room']).')';
    }
    $form = admin_form('กำหนดสถานภาพนักเรียน', a_url('sstatus'), [['นักเรียน', sel('student', ['' => '- เลือกนักเรียน -'] + $all, $_GET['student'] ?? '', 'style="width:450px;height:25px"'), true],
        ['สถานภาพ', sel('status', array_combine(STUDENT_STATUS, STUDENT_STATUS), 'ศึกษาอยู่'), true], ['วันที่ออกจากโรงเรียน', d_in('date', '', 120), false], ['หมายเหตุ', inp('note', '', 400), false]], a_url('student'));
    $c = array_count_values(array_map(fn($d) => $d['status'], student_status()));
    $rows = '';
    $i = 0;
    foreach (STUDENT_STATUS as $s) {
        $rows .= zrow([++$i, status_badge($s), [(int) ($c[$s] ?? 0), ' align="center"']], $i);
    }
    return ['กำหนดสถานภาพนักเรียน', '<div style="width:983px;margin:0 auto 10px;text-align:left" class="gray_12">สถานภาพ "ศึกษาอยู่" = อยู่ในทะเบียนนักเรียนปัจจุบัน / สถานภาพอื่นจะย้ายไปแสดงที่ "นักเรียนจบการศึกษา" หรือ "นักเรียนออกกลางคัน" และเข้าระบบไม่ได้</div>'
        .$form.'<div style="height:10px"></div>'.list_table('จำนวนนักเรียนตามสถานภาพที่บันทึกไว้', ['#', 'สถานภาพ', ['จำนวน', ' style="text-align:center"']], $rows)];
}

function status_list(array $statuses, string $title, string $option): string
{
    $st = student_status();
    $sids = array_keys(array_filter($st, fn($d) => in_array($d['status'], $statuses, true)));
    $rows = '';
    $i = 0;
    if ($sids) {
        $qt = trim((string) ($_GET['q'] ?? ''));
        foreach (q('SELECT S.*, U.`name` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`id` IN ('.implode(',', $sids).') ORDER BY S.`class`, S.`room`, S.`number`') as $s) {
            if ($qt !== '' && mb_stripos($s['student_id'].' '.$s['name'], $qt) === false) {
                continue;
            }
            $id = (int) $s['id'];
            [$pre, $first, $last] = split_name($s['name']);
            $d = $st[$id];
            $rows .= zrow([++$i, h($s['student_id']), h($pre.$first), h($last), h(class_room_full((int) $s['class'], (int) $s['room'])), status_badge($d['status']), !empty($d['date']) ? th_date($d['date'], ' ') : '-',
                [del_link(a_url('student', '&func=Delete&id='.$id)).' '.edit_link(a_url('student', '&func=Edit&id='.$id.'&back='.$option)), ' align="center" width="60"']], $i);
        }
    }
    $f = pick_form($option, [], inp('q', (string) ($_GET['q'] ?? ''), 220, 'text', 'placeholder="ค้นหาจากรหัสหรือชื่อ"').' <input type="submit" value="ค้นหา" class="general radius" style="height:25px"/>');
    return $f.list_table($title, ['#', 'รหัสนักเรียน', 'ชื่อ', 'นามสกุล', 'ห้องเรียนล่าสุด', 'สถานภาพ', 'วันที่ออกจากโรงเรียน', ['', ' style="width:60px"']], $rows, 'ทั้งหมด '.$i.' รายการ', print_btn())
        .'<p style="width:983px;margin:6px auto;text-align:left" class="gray_12">คลิก <i class="fa fa-pencil"></i> เพื่อแก้ไขสถานภาพ หากเปลี่ยนเป็น "ศึกษาอยู่" รายชื่อจะกลับไปอยู่ในทะเบียนนักเรียนปัจจุบัน</p>';
}

function adm_graduate(bool $post) { return status_list(GRAD_STATUS, 'รายชื่อนักเรียนจบการศึกษา', 'graduate'); }
function adm_dropout(bool $post) { return status_list(array_values(array_diff(STUDENT_STATUS, array_merge(GRAD_STATUS, ['ศึกษาอยู่']))), 'รายชื่อนักเรียนออกกลางคัน (ย้าย/พ้น/ศึกษาต่อที่อื่น/พัก/แขวนลอย/ลาออก)', 'dropout'); }

function adm_new_student(bool $post)
{
    [$cy] = cur_term();
    $year = (int) ($_GET['y'] ?? $cy);
    $entry = md_options('entry_type', 'name');
    $rows = '';
    $i = 0;
    $info = stinfo_all();
    foreach (students_q() as $s) {
        $in = $info[(int) $s['id']] ?? [];
        if ((int) ($in['entry_year'] ?? 0) !== $year) {
            continue;
        }
        $rows .= zrow([++$i, h($s['student_id']), name_nbsp($s['name']), h(class_room_short($s['class'], $s['room'])), h($entry[$in['entry'] ?? ''] ?? '-'), [edit_link(a_url('student', '&func=Edit&id='.(int) $s['id'])), ' align="center"']], $i);
    }
    $years = [];
    foreach (array_keys(all_years()) as $y) {
        $years[$y] = $y;
    }
    return pick_form('new_student', ['y' => ['ปีที่เข้าเรียน', $years ?: [$cy => $cy], $year]]).list_table('นักเรียนเข้าใหม่ ปีการศึกษา '.$year, ['#', 'รหัสนักเรียน', 'ชื่อ-นามสกุล', 'ชั้น', 'วิธีการเข้าเรียน', ''], $rows, 'ทั้งหมด '.$i.' คน', print_btn());
}

function adm_no_room(bool $post)
{
    if ($post) {
        foreach ((array) ($_POST['set'] ?? []) as $sid => $rid) {
            $rid = (int) $rid;
            if ($rid) {
                $cid = 0;
                foreach (rooms_by_class() as $c => $rs) {
                    if (isset($rs[$rid])) {
                        $cid = (int) $c;
                    }
                }
                q('UPDATE {student} SET `room`=?, `class`=IF(?>0,?,`class`) WHERE `id`=?', [$rid, $cid, $cid, (int) $sid]);
            }
        }
        admin_done('no_room');
    }
    $rooms = cats('room');
    $ro = ['0' => '- เลือกห้องเรียน -'];
    foreach (rooms_by_class() as $cid => $rs) {
        foreach ($rs as $rid => $n) {
            $ro[$rid] = $cid ? cat('class', $cid).'/'.preg_replace('/^\d+\//', '', $n) : $n;
        }
    }
    $st = student_status();
    $rows = '';
    $i = 0;
    foreach (students_q() as $s) {
        if ((int) $s['room'] && isset($rooms[$s['room']])) {
            continue;
        }
        $id = (int) $s['id'];
        [$pre, $first, $last] = split_name($s['name']);
        $rows .= zrow([++$i, h($s['student_id']), h($pre.$first), h($last), h($s['class'] ? cat('class', $s['class']) : '-'), status_badge($st[$id]['status'] ?? 'ศึกษาอยู่'),
            sel('set['.$id.']', $ro, 0, 'style="width:190px;height:24px"'), [del_link(a_url('student', '&func=Delete&id='.$id)).' '.edit_link(a_url('student', '&func=Edit&id='.$id.'&back=no_room')), ' align="center" width="60"']], $i);
    }
    $save = '<a href="javascript:void(0)" onclick="document.forms.nr.submit()" id="pinkgreenbtns"><img src="../icon_tools/vec_add_white.png" height="16"/> บันทึกห้องเรียน</a>';
    return ['รายชื่อนักเรียนยังไม่กำหนดห้องเรียน', '<form name="nr" method="post" action="'.h(a_url('no_room')).'">'.list_table('รายชื่อนักเรียนยังไม่กำหนดห้องเรียน ประจำปีการศึกษา '.cur_term()[0],
        ['#', 'รหัสนักเรียน', 'ชื่อ', 'นามสกุล', 'ระดับชั้นล่าสุด', 'สถานภาพ', 'กำหนดห้องเรียน', ['', ' style="width:60px"']], $rows, 'ทั้งหมด '.$i.' รายการ', $save).'</form>'
        .'<p style="width:983px;margin:6px auto;text-align:left" class="gray_12">เลือกห้องเรียนให้นักเรียนแล้วกด "บันทึกห้องเรียน" รายชื่อจะหายไปจากหน้านี้</p>'];
}

/* ================================================================ จัดการชั้นเรียน (ครูที่ปรึกษา แผนการเรียน เลื่อนชั้น ซ้ำชั้น จบการศึกษา) */

/** ครูที่ปรึกษาของห้อง [room => [pid, pid]] (ปัจจุบัน) */
function room_advisors(): array
{
    $o = [];
    foreach (q('SELECT `id`, `room` FROM {personnel} WHERE `room`>0 ORDER BY `id`') as $p) {
        $o[(int) $p['room']][] = (int) $p['id'];
    }
    return $o;
}

function room_class(int $room): int
{
    foreach (rooms_by_class() as $c => $rs) {
        if (isset($rs[$room])) {
            return (int) $c;
        }
    }
    return 0;
}

/** ตั้งครูที่ปรึกษาห้องละไม่เกิน 2 คน และเก็บประวัติของปีการศึกษาปัจจุบันไว้ (ใช้คัดลอกข้ามปี) */
function set_room_advisors(int $room, array $pids): void
{
    $cid = room_class($room);
    $pids = array_slice(array_values(array_unique(array_filter(array_map('intval', $pids)))), 0, 2);
    q('UPDATE {personnel} SET `class`=0, `room`=0 WHERE `room`=?', [$room]);
    foreach ($pids as $pid) {
        if (!q1('SELECT 1 x FROM {personnel} WHERE `id`=?', [$pid])) {
            q('INSERT INTO {personnel} (`id`,`position`,`department`,`order`) VALUES (?,0,0,0)', [$pid]);
        }
        q('UPDATE {personnel} SET `class`=?, `room`=? WHERE `id`=?', [$cid, $room, $pid]);
    }
    md_set('adv_hist', cur_term()[0], $room, '', $pids ? json_encode($pids) : null);
}

function room_prog(int $room, ?int $year = null): string
{
    return (string) md_get('room_prog', $room, (string) ($year ?? cur_term()[0]));
}

function prog_options(): array
{
    $o = [];
    foreach (md_rows('program') as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        $o[(string) $r['r1']] = ($d['short'] ?? '') !== '' ? $d['short'] : ($d['name'] ?? '');
    }
    return $o;
}

/** ปีการศึกษาที่มีข้อมูลประวัติ (ครูที่ปรึกษา/แผนการเรียน) */
function hist_years(string $kind): array
{
    md_ensure();
    $col = $kind === 'room_prog' ? 'r2' : 'r1';
    $y = [];
    foreach (q("SELECT DISTINCT `$col` y FROM {mis_data} WHERE `kind`=?", [$kind]) as $r) {
        if ((int) $r['y'] && (int) $r['y'] !== cur_term()[0]) {
            $y[(int) $r['y']] = (int) $r['y'];
        }
    }
    krsort($y);
    return $y;
}

/** ห้องเรียน "ม.1/2" ของระดับชั้นถัดไปที่ตรงกัน */
function next_room(int $room, int $step = 1): int
{
    $rn = room_short($room);
    if (!preg_match('/^(\d+)\/(.+)$/', cats('room')[$room] ?? '', $m)) {
        return 0;
    }
    $want = ((int) $m[1] + $step).'/'.$m[2];
    return (int) (array_search($want, cats('room'), true) ?: 0);
}

function room_choices(string $first): array
{
    $o = ['0' => $first];
    foreach (rooms_by_class() as $cid => $rs) {
        foreach ($rs as $rid => $n) {
            $o[$rid] = 'ชั้น '.($cid ? class_room_short($cid, $rid) : $n);
        }
    }
    return $o;
}

function room_students(int $room): array
{
    return q('SELECT S.*, U.`name`, U.`sex`, U.`active` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`room`=? AND U.`active`=1 ORDER BY S.`number`, S.`student_id`', [$room]);
}

/** เก็บห้องเดิมของนักเรียนในปีการศึกษาปัจจุบันไว้เป็นประวัติก่อนย้าย */
function keep_room_history(array $s): void
{
    if (md_get('stroom', (int) $s['id'], cur_term()[0]) === null) {
        md_set('stroom', (int) $s['id'], cur_term()[0], '', json_encode(['class' => (int) $s['class'], 'room' => (int) $s['room'], 'number' => $s['number']]));
    }
}

/** ห้องที่ครูเป็นครูประจำชั้นในปีการศึกษา $y (จากประวัติครูประจำชั้น) */
function adv_room_in_year(int $pid, int $y): int
{
    foreach (md_rows('adv_hist', $y) as $r) {
        if (in_array($pid, array_map('intval', json_decode((string) $r['value'], true) ?: []), true)) {
            return (int) $r['r2'];
        }
    }
    return 0;
}

/** ครูประจำชั้นของแต่ละห้องในปีการศึกษา $y [room => [pid...]] (ปีปัจจุบัน = ข้อมูลปัจจุบัน) */
function room_advisors_in_year(int $y): array
{
    if ($y === cur_term()[0]) {
        return room_advisors();
    }
    $o = [];
    foreach (md_rows('adv_hist', $y) as $r) {
        $o[(int) $r['r2']] = array_map('intval', json_decode((string) $r['value'], true) ?: []);
    }
    return $o;
}

/** ห้อง/ชั้น/เลขที่ของนักเรียนในปีการศึกษาย้อนหลัง [sid => ['class','room','number']] (ประวัติห้องเรียน + การลงทะเบียนเรียนของปีนั้น) */
function year_rooms(int $y): array
{
    static $cache = [];
    if (isset($cache[$y])) {
        return $cache[$y];
    }
    $o = [];
    foreach (md_rows('stroom', null, (string) $y) as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        if ((int) ($d['room'] ?? 0)) {
            $o[(int) $r['r1']] = ['class' => (int) ($d['class'] ?? 0), 'room' => (int) $d['room'], 'number' => $d['number'] ?? null];
        }
    }
    foreach (q('SELECT G.`student_id`, MAX(G.`room`) room, MAX(G.`number`) number, MAX(C.`class`) class FROM {grade} G JOIN {course} C ON C.`id`=G.`course_id` WHERE C.`year`=? AND G.`room`>0 GROUP BY G.`student_id`', [$y]) as $r) {
        // ห้องในข้อมูลลงทะเบียนต้องอยู่ในระดับชั้นของรายวิชาปีนั้น (ข้อมูลเก่าที่นำเข้าอาจเป็นห้องปัจจุบัน)
        if ((int) $r['class'] && room_class((int) $r['room']) !== (int) $r['class']) {
            continue;
        }
        $o[(int) $r['student_id']] ??= ['class' => (int) $r['class'] ?: room_class((int) $r['room']), 'room' => (int) $r['room'], 'number' => $r['number']];
    }
    return $cache[$y] = $o;
}

/** นักเรียนของห้องในปีการศึกษา $y (ปีปัจจุบัน = ห้องปัจจุบัน) */
function year_room_students(int $room, int $y): array
{
    if ($y === cur_term()[0]) {
        return q('SELECT S.*, U.`name`, U.`phone`, U.`username`, U.`active`, U.`sex` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`room`=? ORDER BY S.`number`, S.`student_id`', [$room]);
    }
    $h = array_filter(year_rooms($y), fn($x) => $x['room'] === $room);
    if (!$h) {
        return [];
    }
    $rows = q('SELECT S.*, U.`name`, U.`phone`, U.`username`, U.`active`, U.`sex` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`id` IN ('.implode(',', array_map('intval', array_keys($h))).')');
    foreach ($rows as &$s) {
        $x = $h[(int) $s['id']];
        $s['class'] = $x['class'];
        $s['room'] = $x['room'];
        $s['number'] = $x['number'];
    }
    unset($s);
    usort($rows, fn($a, $b) => [(int) $a['number'], (string) $a['student_id']] <=> [(int) $b['number'], (string) $b['student_id']]);
    return $rows;
}

function move_student(array $s, int $to, ?int $number = null): void
{
    keep_room_history($s);
    q('UPDATE {student} SET `room`=?, `class`=?, `number`=? WHERE `id`=?', [$to, $to ? room_class($to) : (int) $s['class'], $number, (int) $s['id']]);
}

function adm_student_class(bool $post)
{
    $cy = cur_term()[0];
    $vy = term()[0];
    $past = $vy !== $cy;
    // คัดลอกครูที่ปรึกษา / แผนการเรียน จากปีการศึกษาอื่น
    if ($post && isset($_POST['copy_adv']) && ($fy = (int) $_POST['copy_adv'])) {
        foreach (md_rows('adv_hist', $fy) as $r) {
            set_room_advisors((int) $r['r2'], json_decode((string) $r['value'], true) ?: []);
        }
        admin_done('student_class');
    }
    if ($post && isset($_POST['copy_prog']) && ($fy = (int) $_POST['copy_prog'])) {
        foreach (md_rows('room_prog', null, (string) $fy) as $r) {
            md_set('room_prog', (int) $r['r1'], (string) $cy, '', (string) $r['value']);
        }
        admin_done('student_class');
    }
    // ยืนยันประวัติ (บันทึกจำนวนนักเรียนที่บันทึกข้อมูลแล้ว ณ วันที่ยืนยัน)
    if ($post && isset($_POST['confirm_act'])) {
        foreach ((array) ($_POST['cf'] ?? []) as $room => $v) {
            md_set('room_confirm', (int) $room, (string) $vy, '', $_POST['confirm_act'] === 'yes' ? (string) $v : null);
        }
        admin_done('student_class');
    }
    if ($post && isset($_POST['adv1'])) {
        foreach ((array) $_POST['adv1'] as $room => $p1) {
            $pids = [(int) $p1, (int) ($_POST['adv2'][$room] ?? 0)];
            if ($past) {
                // ปีการศึกษาย้อนหลัง: บันทึกเฉพาะประวัติครูประจำชั้นของปีนั้น (ไม่เปลี่ยนครูประจำชั้นปัจจุบัน)
                $pids = array_slice(array_values(array_unique(array_filter($pids))), 0, 2);
                md_set('adv_hist', $vy, (int) $room, '', $pids ? json_encode($pids) : null);
            } else {
                set_room_advisors((int) $room, $pids);
            }
            md_set('room_prog', (int) $room, (string) $vy, '', (string) ($_POST['prog'][$room] ?? '') ?: null);
        }
        admin_done('student_class');
    }
    $adv = room_advisors_in_year($vy);
    $opts = [0 => '- ไม่กำหนด -'] + personnel_options();
    $progs = ['' => '- ไม่กำหนด -'] + prog_options();
    $cnt = [];
    if ($past) {
        $yr = year_rooms($vy);
        $sex = $yr ? array_column(q('SELECT `id`, `sex` FROM {user} WHERE `id` IN ('.implode(',', array_map('intval', array_keys($yr))).')'), 'sex', 'id') : [];
        foreach ($yr as $sid => $x) {
            $k = ($sex[$sid] ?? '') === 'f' ? 'f' : 'm';
            $cnt[$x['room']][$k] = ($cnt[$x['room']][$k] ?? 0) + 1;
        }
    } else {
        foreach (q('SELECT S.`room`, U.`sex`, COUNT(*) n FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE U.`active`=1 GROUP BY S.`room`, U.`sex`') as $r) {
            $k = $r['sex'] === 'f' ? 'f' : 'm';
            $cnt[(int) $r['room']][$k] = ($cnt[(int) $r['room']][$k] ?? 0) + (int) $r['n'];
        }
    }
    $recorded = [];
    foreach (md_rows('sa_profile') as $r) {
        $recorded[(int) ((json_decode((string) $r['value'], true) ?: [])['student'] ?? 0)] = true;
    }
    $rec = [];
    $roomOf = $past ? array_map(fn($x) => $x['room'], year_rooms($vy)) : array_column(q('SELECT S.`id`, S.`room` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE U.`active`=1'), 'room', 'id');
    foreach ($roomOf as $sid => $rm) {
        if (isset($recorded[(int) $sid])) {
            $rec[(int) $rm] = ($rec[(int) $rm] ?? 0) + 1;
        }
    }
    $icon = fn($f, $rid, $ic, $t) => '<a href="'.a_url('student_class', '&func='.$f.'&room='.$rid).'" title="'.$t.'" style="margin:0 2px"><i class="fa '.$ic.'" style="font-size:15px;color:#555"></i></a>';
    $rows = '';
    $i = 0;
    foreach (rooms_by_class() as $cid => $rooms) {
        foreach ($rooms as $rid => $rname) {
            $a = $adv[$rid] ?? [];
            $m = (int) ($cnt[$rid]['m'] ?? 0);
            $f = (int) ($cnt[$rid]['f'] ?? 0);
            $n = $m + $f;
            $r = (int) ($rec[$rid] ?? 0);
            $cf = md_get('room_confirm', $rid, (string) $vy);
            $rows .= zrow([h($cid ? class_room_short($cid, $rid) : $rname), sel("adv1[$rid]", $opts, $a[0] ?? 0, 'style="width:190px;height:24px"').'<br>'.sel("adv2[$rid]", $opts, $a[1] ?? 0, 'style="width:190px;height:24px;margin-top:2px"'),
                sel("prog[$rid]", $progs, room_prog($rid, $vy), 'style="width:120px;height:24px"'), [$n, ' align="center"'], [$m, ' align="center"'], [$f, ' align="center"'], [$r, ' align="center"'],
                [$n ? number_format($r * 100 / $n, 2).'%' : '-', ' align="center"'], [$cf !== null ? h($cf) : '-', ' align="center" style="font-size:11px"'],
                ['<input type="checkbox" name="cf['.$rid.']" value="'.$r.' คน ['.($n ? number_format($r * 100 / $n, 2) : '0.00').'%]" form="cfform"/>', ' align="center"'],
                [$past ? '<span class="gray_12">ย้อนหลัง</span>' : $icon('Room', $rid, 'fa-wrench', 'จัดการนักเรียน').$icon('Promote', $rid, 'fa-cloud-upload', 'เลื่อนชั้น').$icon('Repeat', $rid, 'fa-retweet', 'ซ้ำชั้น').$icon('Grad', $rid, 'fa-thumbs-up', 'จบการศึกษา'), ' align="center" style="white-space:nowrap"']], $i++);
        }
    }
    $save = '<a href="javascript:void(0);" onclick="document.forms.sc.submit();" id="pinkgreenbtns"><img src="../icon_tools/vec_add_white.png" border="0" height="16"/> บันทึกครูที่ปรึกษา/แผนการเรียน</a>';
    $hy = hist_years('adv_hist');
    $py = hist_years('room_prog');
    $copy = '<div style="width:983px;margin:0 auto 10px;text-align:left"><form method="post" action="'.h(a_url('student_class')).'" style="display:inline-block;margin-right:20px" onsubmit="return misConfirm(this,\'คัดลอกครูที่ปรึกษาทุกห้องจากปีที่เลือกมายังปีการศึกษา '.$cy.'?\')"><span class="search">คัดลอกครูประจำชั้นจาก '
        .sel('copy_adv', ['' => 'เลือกปีการศึกษา'] + $hy, '').' <input type="submit" value="คัดลอก" class="general radius" style="height:25px"/></span></form>'
        .'<form method="post" action="'.h(a_url('student_class')).'" style="display:inline-block" onsubmit="return misConfirm(this,\'คัดลอกแผนการเรียนทุกห้องจากปีที่เลือกมายังปีการศึกษา '.$cy.'?\')"><span class="search">คัดลอกแผนการเรียนจาก '
        .sel('copy_prog', ['' => 'เลือกปีการศึกษา'] + $py, '').' <input type="submit" value="คัดลอก" class="general radius" style="height:25px"/></span></form></div>'
        .'<div style="width:983px;margin:0 auto 8px;text-align:left" class="gray_12">คำอธิบาย: <i class="fa fa-wrench"></i> จัดการนักเรียน &nbsp; <i class="fa fa-cloud-upload"></i> เลื่อนชั้น &nbsp; <i class="fa fa-retweet"></i> ซ้ำชั้น &nbsp; <i class="fa fa-thumbs-up"></i> จบการศึกษา'
        .' &nbsp;|&nbsp; ครูที่ปรึกษาห้องละไม่เกิน 2 คน / "บันทึก" = นักเรียนที่บันทึกข้อมูลส่วนตัวแล้ว / ติ๊กห้องแล้วกด "ยืนยัน" เพื่อบันทึกประวัติ</div>';
    $cfBtns = '<form id="cfform" method="post" action="'.h(a_url('student_class')).'" style="display:inline"><button name="confirm_act" value="yes" class="general radius" style="height:24px;background:#3498DB;color:#fff;border:0;padding:0 10px">ยืนยัน</button> '
        .'<button name="confirm_act" value="no" class="general radius" style="height:24px;background:#F39C12;color:#fff;border:0;padding:0 10px">ยกเลิก</button></form>';
    if ($past) {
        $copy = msg_box('กำลังดูข้อมูลย้อนหลังของปีการศึกษา '.$vy.' — ครูที่ปรึกษา/แผนการเรียนที่บันทึกในหน้านี้จะเก็บเป็นประวัติของปี '.$vy.' เท่านั้น (ไม่เปลี่ยนข้อมูลปีปัจจุบัน '.$cy.')', true);
    }
    return ['จัดการชั้นเรียน', $copy.'<div style="width:983px;margin:0 auto 6px;text-align:right">ยืนยันประวัติห้องที่ติ๊กไว้: '.$cfBtns.'</div><form name="sc" method="post" action="'.h(a_url('student_class')).'">'.list_table('จัดการชั้นเรียน ประจำปีการศึกษา '.$vy, ['ห้อง', 'ครูที่ปรึกษา', 'แผนการเรียน', ['ทั้งหมด', ' style="text-align:center"'],
        ['ชาย', ' style="text-align:center"'], ['หญิง', ' style="text-align:center"'], ['บันทึก', ' style="text-align:center"'], ['ร้อยละ', ' style="text-align:center"'], ['ยืนยันประวัติ', ' style="text-align:center"'], ['', ' style="text-align:center"'], ''],
        $rows, '', $save).'</form>', head_btn('พิมพ์', 'javascript:window.print()', 'print')];
}

function room_title(int $rid): string
{
    $cid = room_class($rid);
    return $cid ? class_room_short($cid, $rid) : room_short($rid);
}

/** จัดการนักเรียนประจำชั้น: ลบออกจากห้อง / จัดลำดับเลขที่ / ย้ายห้อง / ส่งออก */
function adm_student_class_Room(bool $post)
{
    $rid = (int) ($_GET['room'] ?? 0);
    $back = a_url('student_class', '&func=Room&room='.$rid);
    if (isset($_GET['remove'])) {
        $s = q1('SELECT * FROM {student} WHERE `id`=? AND `room`=?', [(int) $_GET['remove'], $rid]);
        if ($s) {
            move_student($s, 0, null);
        }
        redirect($back.'&msg=saved');
    }
    if ($post && isset($_POST['num'])) {
        foreach ((array) $_POST['num'] as $sid => $n) {
            q('UPDATE {student} SET `number`=? WHERE `id`=? AND `room`=?', [$n === '' ? null : (int) $n, (int) $sid, $rid]);
        }
        redirect($back.'&msg=saved');
    }
    if ($post && isset($_POST['move'])) {
        foreach ((array) $_POST['move'] as $sid => $to) {
            $s = q1('SELECT * FROM {student} WHERE `id`=? AND `room`=?', [(int) $sid, $rid]);
            if ($s && (int) $to && (int) $to !== $rid) {
                move_student($s, (int) $to, null);
            }
        }
        redirect($back.'&msg=saved');
    }
    $list = room_students($rid);
    $st = student_status();
    if (isset($_GET['export'])) {
        $adv = array_map(fn($p) => personnel_options()[$p] ?? '', room_advisors()[$rid] ?? []);
        $m = count(array_filter($list, fn($s) => $s['sex'] !== 'f'));
        $line = fn($a) => implode(',', array_map(fn($v) => '"'.str_replace('"', '""', (string) $v).'"', $a))."\r\n";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="room_'.preg_replace('/\W+/', '_', room_short($rid)).'.csv"');
        echo "\xEF\xBB\xBF".$line(['รายชื่อนักเรียนชั้น'.room_title($rid).' ปีการศึกษา '.cur_term()[0].' '.cfg('school_name', '')])
            .$line(['ครูที่ปรึกษา '.implode(', ', $adv).'  นักเรียนทั้งสิ้น '.count($list).' คน นักเรียนชาย '.$m.' คน นักเรียนหญิง '.(count($list) - $m).' คน'])
            .$line([]).$line(['รหัส', 'เลขที่', 'ชื่อ', 'นามสกุล']);
        foreach ($list as $s) {
            [$pre, $first, $last] = split_name($s['name']);
            echo $line([$s['student_id'], $s['number'], $pre.$first, $last]);
        }
        return null;
    }
    $mode = (string) ($_GET['mode'] ?? '');
    $rows = '';
    $choices = room_choices('เลือกห้องเรียนปลายทาง');
    unset($choices[$rid]);
    foreach ($list as $i => $s) {
        $id = (int) $s['id'];
        [$pre, $first, $last] = split_name($s['name']);
        $cells = $mode === 'number'
            ? [$i + 1, h($s['student_id']), inp('num['.$id.']', $s['number'] ?? '', 60), h($pre.$first), h($last), status_badge($st[$id]['status'] ?? 'ศึกษาอยู่')]
            : [[h($s['number']), ' align="center"'], h($s['student_id']), h($pre.$first), h($last), status_badge($st[$id]['status'] ?? 'ศึกษาอยู่'), h(class_room_full((int) $s['class'], $rid)),
                sel('move['.$id.']', $choices, 0, 'style="width:170px;height:24px"'),
                ['<a href="'.h($back.'&remove='.$id).'" onclick="return misConfirm(this,\'ลบออกจากห้องเรียน? (รายชื่อจะไปอยู่ที่เมนูนักเรียนยังไม่กำหนดห้องเรียน)\')" style="background:#F39C12;color:#fff;padding:3px 8px;border-radius:3px;white-space:nowrap"><i class="fa fa-times"></i> ลบออกจากห้องเรียน</a>', ' align="center"']];
        $rows .= zrow($cells, $i);
    }
    $title = 'รายชื่อนักเรียนชั้น'.room_title($rid).' ประจำปีการศึกษา '.cur_term()[0];
    $tools = head_btn('กลับ', a_url('student_class'), 'x').head_btn('ย้ายห้อง', 'javascript:document.forms.rm.submit()', 'reload').head_btn('จัดลำดับ', $back.'&mode=number', 'edit').head_btn('ส่งออกไฟล์', $back.'&export=1', 'upload');
    if ($mode === 'number') {
        $save = '<a href="javascript:void(0)" onclick="document.forms.rm.submit()" id="pinkgreenbtns" style="background:#F39C12"><img src="../icon_tools/vec_add_white.png" height="16"/> บันทึกเลขที่นักเรียน</a>';
        return ['จัดลำดับนักเรียนในห้อง (เลขที่นักเรียน)', '<form name="rm" method="post" action="'.h($back).'">'.list_table($title, ['#', 'รหัสนักเรียน', 'เลขที่', 'ชื่อ', 'นามสกุล', 'สถานภาพ'], $rows, count($list).' คน', $save).'</form>', $tools];
    }
    return ['จัดการนักเรียนประจำชั้น '.room_title($rid), '<div style="width:983px;margin:0 auto 8px;text-align:left" class="gray_12">เลือก "ชั้นเรียนปลายทาง" ให้นักเรียนที่ต้องการย้าย (ย้ายพร้อมกันได้หลายคน) แล้วกด "ย้ายห้อง" / "ลบออกจากห้องเรียน" รายชื่อจะไปอยู่ที่เมนูนักเรียนยังไม่กำหนดห้องเรียน</div>'
        .'<form name="rm" method="post" action="'.h($back).'">'.list_table($title, [['เลขที่', ' style="text-align:center"'], 'รหัสนักเรียน', 'ชื่อ', 'นามสกุล', 'สถานภาพ', 'ปัจจุบันอยู่ชั้น', 'ชั้นเรียนปลายทาง', ''], $rows, count($list).' คน').'</form>', $tools];
}

/** เลื่อนชั้น / ซ้ำชั้น: เลือกห้องปลายทางรายคน (ค่าเริ่มต้น = ห้องเลขเดียวกันของชั้นถัดไป) */
function promote_page(bool $post, string $type): array
{
    $rid = (int) ($_GET['room'] ?? 0);
    $cy = cur_term()[0];
    $back = a_url('student_class', '&func='.($type === 'promote' ? 'Promote' : 'Repeat').'&room='.$rid);
    $list = room_students($rid);
    if ($post) {
        foreach ($list as $s) {
            $to = (int) ($_POST['to'][$s['id']] ?? 0);
            $note = mb_substr(trim((string) ($_POST['note'][$s['id']] ?? '')), 0, 100);
            if ($to) {
                md_set('promote', (int) $s['id'], (string) $cy, '', json_encode(['type' => $type, 'from' => $rid, 'to' => $to, 'note' => $note, 'date' => date('Y-m-d')], JSON_UNESCAPED_UNICODE));
                move_student($s, $to, $s['number'] === null ? null : (int) $s['number']);
            } elseif ($note !== '') {
                md_set('promote', (int) $s['id'], (string) $cy, '', json_encode(['type' => 'none', 'from' => $rid, 'to' => 0, 'note' => $note, 'date' => date('Y-m-d')], JSON_UNESCAPED_UNICODE));
            }
        }
        redirect(a_url('student_class', '&msg=saved'));
    }
    $done = [];
    foreach (md_rows('promote', null, (string) $cy) as $r) {
        $done[(int) $r['r1']] = json_decode((string) $r['value'], true) ?: [];
    }
    $st = student_status();
    $def = $type === 'promote' ? next_room($rid) : $rid;
    $choices = room_choices($type === 'promote' ? 'ไม่เลื่อนชั้นเรียน' : 'ไม่ซ้ำชั้น');
    $rows = '';
    foreach ($list as $i => $s) {
        $id = (int) $s['id'];
        [$pre, $first, $last] = split_name($s['name']);
        $d = $done[$id] ?? null;
        $mark = $d ? (($d['type'] ?? '') === 'repeat' ? '<i class="fa fa-repeat" style="color:#E67E22;font-size:16px" title="ซ้ำชั้น"></i>' : (($d['type'] ?? '') === 'promote' ? '<i class="fa fa-check-circle" style="color:#27AE60;font-size:16px" title="เลื่อนชั้นแล้ว"></i>' : '')) : '';
        $rows .= zrow([[h($s['number']), ' align="center"'], h($s['student_id']), h($pre.$first), h($last), status_badge($st[$id]['status'] ?? 'ศึกษาอยู่'), h(class_room_full((int) $s['class'], $rid)), [$mark, ' align="center"'],
            sel('to['.$id.']', $choices, $type === 'promote' ? $def : 0, 'style="width:150px;height:24px"'), inp('note['.$id.']', $d['note'] ?? '', 150, 'text', 'placeholder="หมายเหตุ"')], $i);
    }
    $btn = '<a href="javascript:void(0)" onclick="return misConfirm(this,\'ยืนยันการ'.($type === 'promote' ? 'เลื่อนระดับชั้น' : 'กำหนดนักเรียนซ้ำชั้น').'?\',function(){document.forms.pm.submit()})" id="pinkgreenbtns" style="background:#F39C12">'.($type === 'promote' ? 'เลื่อนระดับชั้นนักเรียน' : 'กำหนดนักเรียนซ้ำชั้น').'</a>';
    $help = $type === 'promote'
        ? 'เลือกชั้นเรียนที่ต้องการเลื่อนไป (ค่าเริ่มต้นคือห้องเดียวกันของชั้นถัดไป) หากนักเรียนคนใดไม่เลื่อนชั้นให้เลือก "ไม่เลื่อนชั้นเรียน" แล้วกด "เลื่อนระดับชั้นนักเรียน"'
        : 'เลือกห้องที่นักเรียนต้องเรียนซ้ำชั้น (คนที่ไม่ซ้ำชั้นให้คงไว้ที่ "ไม่ซ้ำชั้น") บันทึกหมายเหตุ (ถ้ามี) แล้วกด "กำหนดนักเรียนซ้ำชั้น"';
    return [($type === 'promote' ? 'เลื่อนชั้นนักเรียน ' : 'กำหนดนักเรียนซ้ำชั้น ').room_title($rid), '<div style="width:983px;margin:0 auto 8px;text-align:left" class="gray_12">'.$help
        .' &nbsp; อธิบายสถานะ: <i class="fa fa-check-circle" style="color:#27AE60"></i> เลื่อนชั้น <i class="fa fa-repeat" style="color:#E67E22"></i> ซ้ำชั้น</div><form name="pm" method="post" action="'.h($back).'">'
        .list_table('รายชื่อนักเรียนชั้น'.room_title($rid).' ประจำปีการศึกษา '.$cy, [['เลขที่', ' style="text-align:center"'], 'รหัสนักเรียน', 'ชื่อ', 'นามสกุล', 'สถานภาพ', 'ปัจจุบันอยู่ชั้น', 'สถานะ', 'ชั้นเรียนปลายทาง', 'หมายเหตุ'], $rows, count($list).' คน', $btn).'</form>',
        head_btn('กลับ', a_url('student_class'), 'x')];
}

function adm_student_class_Promote(bool $post) { return promote_page($post, 'promote'); }
function adm_student_class_Repeat(bool $post) { return promote_page($post, 'repeat'); }

/** กำหนดนักเรียนจบการศึกษาทั้งห้อง */
function adm_student_class_Grad(bool $post)
{
    $rid = (int) ($_GET['room'] ?? 0);
    $back = a_url('student_class', '&func=Grad&room='.$rid);
    $cid = room_class($rid);
    $gradStatus = preg_match('/6|หก/', cat('class', $cid)) ? 'จบการศึกษาระดับมัธยมศึกษาตอนปลาย' : 'จบการศึกษาภาคบังคับ';
    $list = q('SELECT S.*, U.`name`, U.`active` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`room`=? ORDER BY S.`number`, S.`student_id`', [$rid]);
    if ($post) {
        foreach ($list as $s) {
            $v = (string) ($_POST['g'][$s['id']] ?? '');
            $note = mb_substr(trim((string) ($_POST['note'][$s['id']] ?? '')), 0, 100);
            if ($v === 'yes') {
                set_student_status((int) $s['id'], $gradStatus, date('Y-m-d'), $note);
            } elseif ($v === 'no') {
                set_student_status((int) $s['id'], 'ศึกษาอยู่', '', $note !== '' ? $note : 'ไม่จบการศึกษา');
                stinfo_save((int) $s['id'], ['not_grad' => (string) cur_term()[0]]);
            }
        }
        redirect($back.'&msg=saved');
    }
    $st = student_status();
    $info = stinfo_all();
    $rows = '';
    foreach ($list as $i => $s) {
        $id = (int) $s['id'];
        [$pre, $first, $last] = split_name($s['name']);
        $status = $st[$id]['status'] ?? 'ศึกษาอยู่';
        $isGrad = in_array($status, GRAD_STATUS, true);
        $notGrad = ($info[$id]['not_grad'] ?? '') === (string) cur_term()[0];
        $mark = $isGrad ? '<i class="fa fa-check-circle" style="color:#27AE60;font-size:16px"></i>' : ($notGrad ? '<i class="fa fa-exclamation-circle" style="color:#E74C3C;font-size:16px"></i>' : '');
        $rows .= zrow([[h($s['number']), ' align="center"'], h($s['student_id']), h($pre.$first), h($last), status_badge($status), h(room_title($rid)), [$mark, ' align="center"'],
            sel('g['.$id.']', ['' => '- ไม่เปลี่ยน -', 'yes' => 'จบการศึกษา', 'no' => 'ไม่จบการศึกษา'], $isGrad ? 'yes' : ($notGrad ? 'no' : 'yes'), 'style="width:140px;height:24px"'),
            inp('note['.$id.']', $st[$id]['note'] ?? '', 150, 'text', 'placeholder="หมายเหตุ"')], $i);
    }
    $btn = '<a href="javascript:void(0)" onclick="return misConfirm(this,\'กำหนดสถานะจบการศึกษาตามที่เลือก?\',function(){document.forms.gd.submit()})" id="pinkgreenbtns" style="background:#F39C12">กำหนดจบการศึกษา</a>';
    return ['กำหนดนักเรียนจบการศึกษา '.room_title($rid), '<div style="width:983px;margin:0 auto 8px;text-align:left" class="gray_12">นักเรียนที่เลือก "จบการศึกษา" จะได้สถานภาพ "'.h($gradStatus).'" และย้ายไปที่เมนู "นักเรียนจบการศึกษา"'
        .' &nbsp; อธิบายสถานะ: <i class="fa fa-check-circle" style="color:#27AE60"></i> จบการศึกษา <i class="fa fa-exclamation-circle" style="color:#E74C3C"></i> ไม่จบการศึกษา</div><form name="gd" method="post" action="'.h($back).'">'
        .list_table('รายชื่อนักเรียนชั้น'.room_title($rid).' ประจำปีการศึกษา '.cur_term()[0], [['เลขที่', ' style="text-align:center"'], 'รหัส', 'ชื่อ', 'นามสกุล', 'สถานภาพ', 'ชั้นเรียน', 'สถานะ', 'จบการศึกษา', 'หมายเหตุ'], $rows, count($list).' คน', $btn).'</form>',
        head_btn('กลับ', a_url('student_class'), 'x')];
}

/* ================================================================ ข้อมูลการใช้งาน */

function usage_rows(): array
{
    $students = students_q();
    $ids = array_map(fn($s) => (int) $s['id'], $students);
    $has = [];
    foreach (['grasha', 'kolb', 'st_cap', 'st_eq', 'sdq'] as $k) {
        foreach ($ids ? md_rows($k, $ids, yt()) : [] as $r) {
            $has[$k][(int) $r['r1']] = true;
        }
    }
    foreach (md_rows('sa_profile') as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        $has['profile'][(int) ($d['student'] ?? 0)] = true;
    }
    $by = [];
    foreach ($students as $s) {
        $by[(int) $s['room']][] = (int) $s['id'];
    }
    $adv = room_advisors();
    $names = personnel_options();
    $out = [];
    foreach (rooms_by_class() as $cid => $rooms) {
        foreach ($rooms as $rid => $rn) {
            $list = $by[$rid] ?? [];
            $n = count($list);
            $c = [];
            foreach (['profile', 'grasha', 'kolb', 'st_cap', 'st_eq', 'sdq'] as $k) {
                $c[$k] = count(array_filter($list, fn($id) => isset($has[$k][$id])));
            }
            $avg = $n ? array_sum($c) * 100 / ($n * count($c)) : 0;
            $a = $adv[$rid] ?? [];
            $out[] = ['room' => $cid ? class_room_short($cid, $rid) : $rn, 'a1' => $names[$a[0] ?? 0] ?? '-', 'a2' => $names[$a[1] ?? 0] ?? '-', 'n' => $n, 'c' => $c, 'avg' => $avg];
        }
    }
    if (($_GET['sort'] ?? '') === 'avg') {
        usort($out, fn($x, $y) => $y['avg'] <=> $x['avg']);
    }
    return $out;
}

function adm_usage(bool $post)
{
    $data = usage_rows();
    $heads = ['ลำดับ', 'ห้อง', 'ครูที่ปรึกษาคนที่ 1', 'ครูที่ปรึกษาคนที่ 2', 'จำนวนนักเรียน', 'ข้อมูลส่วนตัว', 'วิเคราะห์ผู้เรียนแบบที่ 1 (กราชา)', 'วิเคราะห์ผู้เรียนแบบที่ 2 (คอล์บ)',
        'วิเคราะห์ผู้เรียนแบบที่ 3 (สมรรถนะ)', 'วิเคราะห์ผู้เรียนแบบที่ 4 (EQ)', 'ประเมิน SDQ', 'เฉลี่ยร้อยละ'];
    $cell = fn($v) => $v ?: '-';
    if (isset($_GET['print'])) {
        $tr = '';
        foreach ($data as $i => $d) {
            $tr .= '<tr><td class="c">'.($i + 1).'</td><td class="c">'.h($d['room']).'</td><td>'.h($d['a1']).'</td><td>'.h($d['a2']).'</td><td class="c">'.$d['n'].'</td>';
            foreach ($d['c'] as $v) {
                $tr .= '<td class="c">'.$cell($v).'</td>';
            }
            $tr .= '<td class="c">'.number_format($d['avg'], 2).'</td></tr>';
        }
        $th = '';
        foreach ($heads as $k => $h) {
            $th .= $k >= 4 ? '<th style="writing-mode:vertical-rl;transform:rotate(180deg);height:150px;font-size:12px">'.h($h).'</th>' : '<th>'.h($h).'</th>';
        }
        [$y, $t] = term();
        print_page('ข้อมูลการใช้งาน', '<style>@page{size:A4;margin:10mm 12mm}td,th{font-size:12px}</style><div class="noprint" style="margin-bottom:8px"><form method="get"><input type="hidden" name="module" value="admin"/><input type="hidden" name="option" value="usage"/><input type="hidden" name="print" value="1"/>'
            .sel('sort', ['room' => 'เรียงตามห้องเรียน', 'avg' => 'เรียงตามร้อยละเฉลี่ย'], (string) ($_GET['sort'] ?? 'room'), 'onchange="this.form.submit()"').'</form>คำแนะนำสำหรับการพิมพ์: ตั้งค่ากระดาษเป็น A4 แนวตั้ง</div>'
            .'<div style="text-align:center"><img src="'.logo_url().'" style="height:70px"/><h2>'.h(cfg('school_name', 'โรงเรียนดรุณราษฎร์วิทยา')).'</h2><h2>รายงานจำนวนนักเรียนที่จัดทำข้อมูลสารสนเทศเพื่อการบริหารงานโรงเรียน</h2>'
            .'<h2>ภาคเรียนที่ '.$t.' ปีการศึกษา '.$y.': ข้อมูล ณ วันที่ '.th_date('', ' ').' : '.date('H:i').'</h2></div><table><tr>'.$th.'</tr>'.($tr ?: '<tr><td colspan="12" class="c">-</td></tr>').'</table>');
        return null;
    }
    $rows = '';
    foreach ($data as $i => $d) {
        $cells = [$i + 1, h($d['room']), h($d['a1']), h($d['a2']), [$d['n'], ' align="center"']];
        foreach ($d['c'] as $v) {
            $cells[] = [$cell($v), ' align="center"'];
        }
        $cells[] = [number_format($d['avg'], 2), ' align="center"'];
        $rows .= zrow($cells, $i);
    }
    $ths = array_map(fn($h, $k) => $k >= 4 ? [h($h), ' style="text-align:center;font-size:11px"'] : h($h), $heads, array_keys($heads));
    return ['ข้อมูลการใช้งาน', pick_form('usage', ['sort' => ['เรียงลำดับ', ['room' => 'เรียงตามห้องเรียน', 'avg' => 'เรียงตามร้อยละเฉลี่ย'], (string) ($_GET['sort'] ?? 'room')]])
        .list_table('ข้อมูลการใช้งาน ประจำปีการศึกษา '.yt(), $ths, $rows, count($data).' ห้อง', print_btn(a_url('usage', '&print=1&sort='.rawurlencode((string) ($_GET['sort'] ?? 'room'))))),
        head_btn('พิมพ์', a_url('usage', '&print=1&sort='.rawurlencode((string) ($_GET['sort'] ?? 'room'))), 'print')];
}
