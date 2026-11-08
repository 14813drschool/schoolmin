<?php
/**
 * mis/app/data2.php
 * หน้าที่เหลือของระบบเดิม: ตารางสอน การมาโรงเรียน ติดตามการขาดเรียน ผลการเรียน ปพ.5/ปพ.6
 * คุณลักษณะ การอ่านคิดวิเคราะห์เขียน สมรรถนะ แบบสอบถาม กิจกรรม สถิติ ข้อมูลหลัก
 * ข้อมูลที่ SMS ไม่มีเก็บในตาราง {mis_data} (ชนิด + อ้างอิง 3 ช่อง + ค่า)
 */
defined('MIS') or exit;

/* ================================================================ ที่เก็บข้อมูล */

function md_ensure(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $marker = sys_get_temp_dir().'/mis_data_table';
    if (is_file($marker)) {
        return;
    }
    q('CREATE TABLE IF NOT EXISTS {mis_data} (`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY, `kind` VARCHAR(20) NOT NULL, `r1` VARCHAR(40) NOT NULL DEFAULT \'\','
        .' `r2` VARCHAR(40) NOT NULL DEFAULT \'\', `r3` VARCHAR(40) NOT NULL DEFAULT \'\', `value` TEXT NULL, `updated_by` INT NOT NULL DEFAULT 0,'
        .' `updated_at` DATETIME NULL, UNIQUE KEY `k` (`kind`,`r1`,`r2`,`r3`), KEY `kr2` (`kind`,`r2`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    @touch($marker);
}

function md_set(string $kind, $r1, $r2, $r3, ?string $value): void
{
    md_ensure();
    if ($value === null || $value === '') {
        q('DELETE FROM {mis_data} WHERE `kind`=? AND `r1`=? AND `r2`=? AND `r3`=?', [$kind, (string) $r1, (string) $r2, (string) $r3]);
        return;
    }
    q('INSERT INTO {mis_data} (`kind`,`r1`,`r2`,`r3`,`value`,`updated_by`,`updated_at`) VALUES (?,?,?,?,?,?,NOW())'
        .' ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), `updated_by`=VALUES(`updated_by`), `updated_at`=NOW()',
        [$kind, (string) $r1, (string) $r2, (string) $r3, $value, (int) (me()['id'] ?? 0)]);
}

/** แถวของชนิดที่กำหนด (กรอง r1/r2 ได้; r1 เป็น array ได้) */
function md_rows(string $kind, $r1 = null, $r2 = null): array
{
    md_ensure();
    $sql = 'SELECT * FROM {mis_data} WHERE `kind`=?';
    $p = [$kind];
    if (is_array($r1)) {
        if (!$r1) {
            return [];
        }
        $sql .= ' AND `r1` IN ('.implode(',', array_fill(0, count($r1), '?')).')';
        array_push($p, ...array_map('strval', $r1));
    } elseif ($r1 !== null) {
        $sql .= ' AND `r1`=?';
        $p[] = (string) $r1;
    }
    if ($r2 !== null) {
        $sql .= ' AND `r2`=?';
        $p[] = (string) $r2;
    }
    return q($sql, $p);
}

function md_get(string $kind, $r1, $r2 = '', $r3 = ''): ?string
{
    md_ensure();
    $r = q1('SELECT `value` FROM {mis_data} WHERE `kind`=? AND `r1`=? AND `r2`=? AND `r3`=?', [$kind, (string) $r1, (string) $r2, (string) $r3]);
    return $r['value'] ?? null;
}

function yt(): string
{
    [$y, $t] = term();
    return $y.'/'.$t;
}

/* ================================================================ ส่วนประกอบหน้า */

/** หัวของหน้า (แถบชื่อหน้า + กล่องคำอธิบาย) จากแม่แบบ ใช้กับหน้าย่อย */
function head_of(string $key, string $subtitle = ''): string
{
    $s = (string) file_get_contents(MIS_APP.'pages/'.$key.'.html');
    $i = strpos($s, '<div style="margin-top:10px;min-height:750px;z-index:100">');
    $head = $i === false ? $s : substr($s, 0, $i);
    if ($subtitle !== '') {
        $head = preg_replace('/(<h1 class="blue_secondtitle"[^>]*>)[^<]*(<\/h1>)/u', '${1}'.str_replace('$', '\\$', h($subtitle)).'$2', $head, 1);
    }
    return $head.'<div style="margin-top:10px;min-height:750px;z-index:100">'."\n"
        .'<link rel="stylesheet" type="text/css" href="../css/tiny_style.css">'."\n";
}

function sub_page(string $key, string $body, string $subtitle = ''): array
{
    return ['__CONTENT__' => head_of($key, $subtitle).$body."\n</div>\n"];
}

function msg_box(string $text, bool $ok = true): string
{
    return "<div id='msg_info' style='width:983px;margin:0 auto 10px'><table border='0' cellspacing='0' cellpadding='0' class='"
        .($ok ? 'frame_green_msg' : 'frame_red_msg')."' style='width:983px'><tr><td width=20><img src='../images/".($ok ? '1347351892_yes.png' : '001_11.png')
        ."' border=0 align=middle></td><td align='left' style='text-align:left'>&nbsp;&nbsp;$text</td></tr></table></div>";
}

function saved_msg(): string
{
    return ($_GET['msg'] ?? '') === 'saved' ? msg_box('บันทึกข้อมูลเรียบร้อยแล้ว') : '';
}

function btn(string $label, string $href, string $icon = 'add'): string
{
    $icon = ['back' => 'x', 'edit' => 'wrench', 'up' => 'add'][$icon] ?? $icon;
    return '<a href="'.$href.'" id="pinkgreenbtns" style="margin-left:4px"><img src="../icon_tools/vec_'.$icon.'_white.png" border="0" height="16"/> '.h($label).'</a>';
}

/** ตารางแบบ tinytable ของระบบเดิม (ไม่มีตัวเรียง) */
function list_table(string $title, array $ths, string $rows, string $right = '', string $extra = ''): string
{
    $th = '';
    foreach ($ths as $t) {
        [$label, $attr] = is_array($t) ? $t : [$t, ''];
        $th .= "<th class=\"nosort\"$attr><h3>$label</h3></th>";
    }
    if (trim($rows) === '') {
        $rows = '<tr><td colspan="'.count($ths).'" style="text-align:center;color:#999">ไม่พบข้อมูล</td></tr>';
    }
    return "\n<table id=\"mainList\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" style=\"margin:0 auto\"><tr id=\"titleList\"><td><div class=\"titlenav\" style=\"width:969px\">"
        ."<span style=\"float:left;white-space:nowrap\"><span id=\"Listtitle\">&nbsp;</span><h1 style=\"font-size:23px; white-space:nowrap\">$title</h1></span>"
        ."<span style=\"float:right;margin-top:3px;white-space:nowrap\">$right</span></div></td></tr>"
        ."<tr id=\"contentList\"><td><table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" class=\"tinytable\" align=\"left\" style=\"width:983px\"><thead><tr>$th</tr></thead><tbody>$rows</tbody></table></td></tr>"
        ."<tr><td><div class=\"pagenav\" style=\"width:969px;height:auto;min-height:22px;text-align:right\">$extra</div></td></tr></table>\n";
}

/** แถวสลับสี */
function zrow(array $cells, int $i): string
{
    $o = '<tr class="'.($i % 2 ? 'oddrow' : 'evenrow').'">';
    foreach ($cells as $c) {
        $o .= is_array($c) ? "<td{$c[1]}>{$c[0]}</td>" : "<td>$c</td>";
    }
    return $o.'</tr>';
}

function sel(string $name, array $options, $value, string $attr = ''): string
{
    $o = "<select name=\"$name\" class=\"general radius\" style=\"height:25px\" $attr>";
    foreach ($options as $k => $v) {
        $o .= '<option value="'.h($k).'"'.((string) $k === (string) $value ? ' selected' : '').'>'.h($v).'</option>';
    }
    return $o.'</select>';
}

function back_url(string $option): string
{
    return '?module=task&option='.$option;
}

/** หน้าแสดงสำหรับพิมพ์ (ไม่มีหัวเว็บ) */
function print_page(string $title, string $body): void
{
    mis_headers();
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>'.h($title).'</title><link rel="stylesheet" href="../css/thaisansbold.css">'
        .'<style>body{font:14px Tahoma;margin:20px;color:#000}h1,h2{font-family:thaisansbold;font-weight:normal;text-align:center;margin:4px 0}'
        .'table{border-collapse:collapse;width:100%;margin-top:10px}th,td{border:1px solid #000;padding:4px 6px}th{background:#eee}.c{text-align:center}'
        .'.noprint{margin-bottom:10px}@media print{.noprint{display:none}}</style></head><body><div class="noprint"><button onclick="window.print()">พิมพ์</button> <button onclick="history.back()">กลับ</button></div>'
        .$body.'</body></html>';
    exit;
}

/* ================================================================ ตารางสอน */

const TT_DAYS = [1 => ['จันทร์', '#FFD939'], 2 => ['อังคาร', '#FFAC9D'], 3 => ['พุธ', '#00E1AE'], 4 => ['พฤหัสบดี', '#FF9C42'], 5 => ['ศุกร์', '#AEB7FF']];

/** ตารางสอนของครู [day-period => [course, room]] */
function timetable_of(int $teacher): array
{
    $out = [];
    foreach (md_rows('tt', $teacher, yt()) as $r) {
        [$c, $room] = array_pad(explode('|', (string) $r['value']), 2, 0);
        $out[$r['r3']] = [(int) $c, (int) $room];
    }
    return $out;
}

function tt_cell(?array $course, $room, string $teacher = '', string $edit = ''): string
{
    $rows = $course ? [h($course['course_code']), h($course['course_name']), h(class_room_short($course['class'], $room)), h($teacher)] : ['&nbsp;', '&nbsp;', '&nbsp;', '&nbsp;'];
    return '<td align="center" valign="middle" class="'.($course ? 'schedule-subject' : 'schedule-empty').'" style="padding:5px'.($course ? ';background:#F2FFFB' : '').'">'
        .'<div id="display-table"><div id="display-row" class=\'bold\' style="color:#00cea4;text-align:center;padding:1px 10px">'.$rows[0].'</div>'
        .'<div id="display-row" style="font-size:11px;color:#DE6CFF; text-align:center; padding:1px 10px">'.$rows[1].'</div>'
        .'<div id="display-row" style="text-align:center;padding:1px 10px">'.$rows[2].'</div>'
        .'<div id="display-row" style="text-align:center;padding:1px 10px;font-size:11px">'.$rows[3].'</div>'
        .'<div id="display-row" style="text-align:right;padding:0px 5px 0px 0px">'.($edit ?: '&nbsp;').'</div></div></td>';
}

/** แทนแถววันในตารางสอนของแม่แบบ ด้วยข้อมูลจริง */
function timetable_grid(string $tpl, callable $cell): string
{
    $i = strpos($tpl, '<td height="95"');
    $end = strrpos($tpl, '</table>', $i ?: 0);
    if ($i === false || $end === false) {
        return $tpl;
    }
    $start = strrpos(substr($tpl, 0, $i), '<tr>');
    $rows = '';
    foreach (TT_DAYS as $d => [$name, $color]) {
        $rows .= "\n <tr>\n <td height=\"95\" align=\"center\" valign=\"middle\" bgcolor=\"$color\" class=\"schedule-weekday\" style=\"border-top:#aaa solid 1px\"><h1 style=\"font-size:25px;color:#fff\">$name</h1></td>";
        for ($p = 1; $p <= 8; $p++) {
            $rows .= $cell($d, $p);
        }
        $rows .= "\n </tr>";
    }
    return substr($tpl, 0, $start).$rows."\n ".substr($tpl, $end);
}

function courses_by_id(array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) {
        return [];
    }
    $out = [];
    foreach (q('SELECT C.*, U.`name` teacher FROM {course} C LEFT JOIN {user} U ON U.`id`=C.`teacher_id` WHERE C.`id` IN ('.implode(',', $ids).')') as $c) {
        $out[(int) $c['id']] = $c;
    }
    return $out;
}

function rows_task_teacher_timetable()
{
    $me = (int) me()['id'];
    $tid = isset($_GET['TeacherId']) && is_manager() ? (int) $_GET['TeacherId'] : $me;
    $teacher = q1('SELECT `name` FROM {user} WHERE `id`=?', [$tid]);
    $tt = timetable_of($tid);
    $courses = courses_by_id(array_column($tt, 0));
    $edit = isset($_GET['edit']) && ($tid === $me || is_manager());
    $tpl = (string) file_get_contents(MIS_APP.'pages/task_teacher_timetable.html');
    $opts = ['' => '- ว่าง -'];
    if ($edit) {
        $mine = q('SELECT * FROM {course} WHERE `teacher_id`=? AND `year`=? AND `term`=? ORDER BY `course_code`', [$tid, ...term()]);
        $rooms = course_rooms($mine);
        foreach ($mine as $c) {
            foreach (($rooms[(int) $c['id']] ?? [0 => 0]) as $room => $n) {
                $opts[$c['id'].'|'.$room] = $c['course_code'].' '.class_room_short($c['class'], $room);
            }
        }
    }
    $tpl = timetable_grid($tpl, function ($d, $p) use ($tt, $courses, $edit, $opts) {
        [$cid, $room] = $tt[$d.'-'.$p] ?? [0, 0];
        $c = $courses[$cid] ?? null;
        $e = $edit ? sel("tt[$d-$p]", $opts, $cid ? $cid.'|'.$room : '', 'style="width:96px;height:22px;font-size:11px"') : '';
        return tt_cell($c, $room, '', $e);
    });
    $tpl = str_replace('ตารางสอน {{TNAME}}', 'ตารางสอน '.name_nbsp($teacher['name'] ?? ''), $tpl);
    $bar = $edit
        ? '<div style="width:983px;margin:0 auto;text-align:right;padding:8px 0">'.btn('ยกเลิก', '?module=task&option=teacher_timetable'.($tid !== $me ? '&TeacherId='.$tid : ''), 'back')
            .'<a href="javascript:void(0);" onclick="document.forms.list.submit();" id="pinkgreenbtns" style="margin-left:4px"><img src="../icon_tools/vec_add_white.png" border="0" height="16"/> บันทึกตารางสอน</a></div>'
        : '<div style="width:983px;margin:0 auto;text-align:right;padding:8px 0">'.(($tid === $me || is_manager()) ? btn('จัดตารางสอน', '?module=task&option=teacher_timetable&edit=1'.($tid !== $me ? '&TeacherId='.$tid : ''), 'edit') : '')
            .'<a href="javascript:window.print();" id="pinkgreenbtns" style="margin-left:4px"><img src="../icon_tools/vec_print_white.png" border="0" height="16"/> พิมพ์ตารางสอน</a></div>';
    $tpl = preg_replace('/<form id="list" name="list" method="post">/', saved_msg().'<form id="list" name="list" method="post" action="?module=task&option=teacher_timetable&func=SaveTimetable&TeacherId='.$tid.'">', $tpl, 1);
    $tpl = preg_replace('/<\/form>/', $bar.'</form>', $tpl, 1);
    return ['__CONTENT__' => $tpl];
}

function act_task_teacher_timetable_SaveTimetable(): void
{
    $me = (int) me()['id'];
    $tid = (int) ($_GET['TeacherId'] ?? $me);
    if ($tid !== $me && !is_manager()) {
        redirect('?module=task&option=teacher_timetable');
    }
    $mine = [];
    foreach (q('SELECT `id` FROM {course} WHERE `teacher_id`=?', [$tid]) as $c) {
        $mine[(int) $c['id']] = true;
    }
    foreach (TT_DAYS as $d => $x) {
        for ($p = 1; $p <= 8; $p++) {
            $v = (string) ($_POST['tt'][$d.'-'.$p] ?? '');
            $ok = preg_match('/^(\d+)\|(\d+)$/', $v, $m) && isset($mine[(int) $m[1]]);
            md_set('tt', $tid, yt(), $d.'-'.$p, $ok ? $v : null);
        }
    }
    redirect('?module=task&option=teacher_timetable'.($tid !== $me ? '&TeacherId='.$tid : '').'&msg=saved');
}

/** ตารางเรียนของห้อง (รวมตารางสอนของครูทุกคน) */
function act_academic_student_timetable_RoomTable(): void
{
    $room = (int) ($_GET['RoomNum'] ?? 0);
    $cells = [];
    foreach (md_rows('tt', null, yt()) as $r) {
        [$c, $rm] = array_pad(explode('|', (string) $r['value']), 2, 0);
        if ((int) $rm === $room) {
            $cells[$r['r3']] = (int) $c;
        }
    }
    $courses = courses_by_id($cells);
    [$y, $t] = term();
    $html = '<h2>ตารางเรียน ห้อง '.h(room_short($room)).' ภาคเรียนที่ '.$t.' ปีการศึกษา '.$y.'</h2><table><tr><th>วัน/คาบ</th>';
    for ($p = 1; $p <= 8; $p++) {
        $html .= "<th>คาบที่ $p</th>";
    }
    $html .= '</tr>';
    foreach (TT_DAYS as $d => [$name]) {
        $html .= "<tr><th>$name</th>";
        for ($p = 1; $p <= 8; $p++) {
            $c = $courses[$cells[$d.'-'.$p] ?? 0] ?? null;
            $html .= '<td class="c" style="height:60px">'.($c ? h($c['course_code']).'<br><small>'.h($c['course_name']).'</small><br><small>'.h($c['teacher']).'</small>' : '').'</td>';
        }
        $html .= '</tr>';
    }
    print_page('ตารางเรียน', $html.'</table>');
}

/* ================================================================ การมาโรงเรียน (ครูที่ปรึกษา) */

const SCHOOL_ATT = ['ม' => 'มา', 'ข' => 'ขาด', 'ล' => 'ลา', 'ป' => 'ป่วย', 'ส' => 'สาย', 'น' => 'หนี'];

function be_date_to_iso(string $v, string $default = ''): string
{
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $v, $m)) {
        $y = (int) $m[3] > 2400 ? (int) $m[3] - 543 : (int) $m[3];
        if (checkdate((int) $m[2], (int) $m[1], $y)) {
            return sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]);
        }
    }
    return $default ?: date('Y-m-d');
}

function iso_to_be(string $iso): string
{
    $t = strtotime($iso);
    return date('d/m/', $t).(date('Y', $t) + 543);
}

function school_att(array $ids, string $from, string $to): array
{
    $out = [];
    if (!$ids) {
        return $out;
    }
    md_ensure();
    foreach (q('SELECT `r1`,`r2`,`value` FROM {mis_data} WHERE `kind`=\'satt\' AND `r1` IN ('.implode(',', array_map('intval', $ids)).') AND `r2` BETWEEN ? AND ?', [$from, $to]) as $r) {
        $out[(int) $r['r1']][$r['r2']] = $r['value'];
    }
    return $out;
}

function rows_task_school_attendance_report()
{
    $students = advisor_students();
    $day = be_date_to_iso((string) ($_POST['attendance_date'] ?? $_GET['day'] ?? ''));
    $view = in_array($_POST['attendance_persent'] ?? $_GET['view'] ?? '', ['month', 'semester'], true) ? ($_POST['attendance_persent'] ?? $_GET['view']) : 'daily';
    $ids = array_map(fn($s) => (int) $s['id'], $students);
    $tpl = (string) file_get_contents(MIS_APP.'pages/task_school_attendance_report.html');
    $tpl = preg_replace('/วัน\S+ ที่ \d+ \S+ \d{4}/u', 'วัน'.TH_DAYS[(int) date('w', strtotime($day))].' ที่ '.date('d', strtotime($day)).' '.TH_MONTHS[(int) date('n', strtotime($day))].' '.(date('Y', strtotime($day)) + 543), $tpl, 1);
    $tpl = str_replace('value="06/11/2569"', 'value="'.iso_to_be($day).'"', $tpl);
    $tpl = str_replace('<span class="search" style="display:none">', '<span class="search">', $tpl);
    $tpl = preg_replace('/(<option value="daily") selected=\'selected\'/', '$1', $tpl);
    $tpl = str_replace('<option value="'.$view.'" ', '<option value="'.$view.'" selected=\'selected\' ', $tpl);
    if ($view === 'daily') {
        $att = school_att($ids, $day, $day);
        $rows = '';
        foreach ($students as $i => $s) {
            $v = $att[(int) $s['id']][$day] ?? '';
            $rows .= zrow([$s['number'], h($s['student_id']), ['<span style="float:left;padding-left:10px">'.name_nbsp($s['name']).'</span>', ''],
                ['<input type="text" readonly="readonly" name="att['.(int) $s['id'].']" id="satt'.(int) $s['id'].'" value="'.h($v).'" class="'.($v !== '' ? ATT_CLASS[$v] : '').'" onclick="ChangeVal(\'satt'.(int) $s['id'].'\', this.value==\'\'?\'ซ\':this.value);" style="width:30px;text-align:center;cursor:pointer"/>', ' class="box"']], $i);
        }
        $body = list_table('บันทึก/รายงานการมาโรงเรียน ห้อง '.h(advisor_short()), ['เลขที่', 'รหัสนร.', 'ชื่อ-นามสกุล', 'สถานะ'], $rows, 'ม=มา ข=ขาด ล=ลา ป=ป่วย ส=สาย น=หนี',
            "<a href=\"javascript:void(0);\" onclick=\"$('#schoolatt input[type=text]').each(function(){this.value='ม';this.className='blackcolor';});\" id=\"pinkgreenbtns\">มาทุกคน</a> "
            ."<a href=\"javascript:void(0);\" onclick=\"document.forms.schoolatt.submit();\" id=\"pinkgreenbtns\"><img src=\"../icon_tools/vec_add_white.png\" border=\"0\" height=\"16\"/> บันทึกการมาโรงเรียน</a>");
        $body = '<form id="schoolatt" name="schoolatt" method="post" action="?module=task&option=school_attendance_report&func=SaveSchool&day='.$day.'">'.$body.'</form>'
            ."<script type=\"text/javascript\">function ChangeVal(id,v){var o=['ม','ข','ล','ป','ส','น'],c={'ม':'blackcolor','ข':'redcolor','ล':'bluecolor','ป':'purplecolor','ส':'yellowcolor','น':'redcolor'};var i=o.indexOf(v);var n=o[(i+1)%o.length];var e=document.getElementById(id);e.value=n;e.className=c[n];}</script>";
    } else {
        [$from, $to] = $view === 'month' ? [date('Y-m-01', strtotime($day)), date('Y-m-t', strtotime($day))] : term_range();
        $att = school_att($ids, $from, $to);
        $rows = '';
        foreach ($students as $i => $s) {
            $c = array_count_values($att[(int) $s['id']] ?? []);
            $cells = [$s['number'], h($s['student_id']), ['<span style="float:left;padding-left:10px">'.name_nbsp($s['name']).'</span>', '']];
            foreach (SCHOOL_ATT as $k => $n) {
                $cells[] = (int) ($c[$k] ?? 0);
            }
            $rows .= zrow($cells, $i);
        }
        $body = list_table('รายงานการมาโรงเรียน '.($view === 'month' ? 'ประจำเดือน'.TH_MONTHS[(int) date('n', strtotime($day))] : 'รายภาค').' ห้อง '.h(advisor_short()),
            array_merge(['เลขที่', 'รหัสนร.', 'ชื่อ-นามสกุล'], array_values(SCHOOL_ATT)), $rows);
    }
    $tpl = preg_replace('/<\/form><\/div>\s*$/', '</form>'.saved_msg().$body.'</div>', $tpl);
    $tpl .= "<script type=\"text/javascript\">function submit_daily(){document.forms.list.submit();}function select_view(v){document.forms.list.submit();}</script>";
    return ['__CONTENT__' => $tpl];
}

function act_task_school_attendance_report_SaveSchool(): void
{
    $day = be_date_to_iso(iso_to_be((string) ($_GET['day'] ?? date('Y-m-d'))));
    $ids = array_map(fn($s) => (int) $s['id'], advisor_students());
    foreach ((array) ($_POST['att'] ?? []) as $sid => $v) {
        if (in_array((int) $sid, $ids, true)) {
            md_set('satt', (int) $sid, $day, '', isset(SCHOOL_ATT[$v]) ? $v : null);
        }
    }
    redirect('?module=task&option=school_attendance_report&day='.$day.'&msg=saved');
}

function advisor_short(): string
{
    $p = me()['personnel'] ?? null;
    return $p && (int) $p['room'] ? class_room_short($p['class'], $p['room']) : '-';
}

/** ช่วงวันของภาคเรียน (จากปฏิทินการเรียนการสอน ถ้าไม่ได้ตั้งไว้ใช้ภาค 1 = พ.ค.-ต.ค., ภาค 2 = พ.ย.-มี.ค.) */
function term_range(): array
{
    [$y, $t] = term();
    $cal = json_decode((string) md_get('calendar', yt()), true) ?: [];
    if (!empty($cal['start']) && !empty($cal['end'])) {
        return [$cal['start'], $cal['end']];
    }
    $g = $y - 543;
    return $t === 1 ? ["$g-05-01", "$g-10-31"] : ["$g-11-01", ($g + 1).'-03-31'];
}

/* ================================================================ ติดตามการขาดเรียน */

function rows_task_absent_school_attention()
{
    $status = (string) ($_POST['absent_status'] ?? 'all');
    $freq = (string) ($_POST['absent_freq'] ?? '3');
    $codes = ['all' => ['ข', 'ส', 'น'], 'absent' => ['ข'], 'late' => ['ส'], 'escape' => ['น']];
    $students = advisor_students();
    [$from, $to] = term_range();
    $att = school_att(array_map(fn($s) => (int) $s['id'], $students), $from, $to);
    $rows = '';
    $i = 0;
    foreach ($students as $s) {
        $days = array_keys(array_filter($att[(int) $s['id']] ?? [], fn($v) => in_array($v, $codes[$status] ?? $codes['all'], true)));
        sort($days);
        $hit = false;
        if ($freq === '3') {
            $run = 1;
            for ($k = 1; $k < count($days); $k++) {
                $run = (strtotime($days[$k]) - strtotime($days[$k - 1]) <= 3 * 86400) ? $run + 1 : 1;
                $hit = $hit || $run >= 3;
            }
        } elseif ($freq === '5') {
            foreach (array_count_values(array_map(fn($d) => substr($d, 0, 7), $days)) as $n) {
                $hit = $hit || $n >= 5;
            }
        } else {
            $hit = count($days) >= 10;
        }
        if ($hit) {
            $rows .= zrow([++$i, $s['number'], h($s['student_id']), name_nbsp($s['name']), [count($days).' วัน', ' align="center"'], h(implode(', ', array_map(fn($d) => th_date($d, ' '), array_slice($days, -5))))], $i);
        }
    }
    $sel = '<table border="0" cellspacing="0" cellpadding="0"><tr><td>'.sel('absent_status', ['all' => 'ขาดรวม (ขาด/สาย/หนี)', 'absent' => 'ขาด', 'late' => 'สาย', 'escape' => 'หนี'], $status, 'onchange="this.form.submit()"')
        .'</td><td style="padding-left:5px">'.sel('absent_freq', ['3' => '3 วันติดต่อกัน', '5' => '5 วันในรอบเดือน', '10' => '10 วันขึ้นไป'], $freq, 'onchange="this.form.submit()"').'</td></tr></table>';
    return sub_page('task_absent_school_attention', '<form method="post" style="width:983px;margin:0 auto 10px;text-align:left"><span class="search">'.$sel.'</span><div style="clear:both"></div></form>'
        .list_table('รายชื่อนักเรียนในที่ปรึกษาขาดเรียน ห้อง '.h(advisor_short()), ['#', 'เลขที่', 'รหัสนร.', 'ชื่อ-นามสกุล', ['จำนวน', ' style="text-align:center"'], 'วันที่ล่าสุด'], $rows));
}

/** สถิติการเข้าเรียนรายวิชาของนักเรียน [student][course] => [counts] */
function subject_att(array $ids, string $from = '', string $to = ''): array
{
    if (!$ids) {
        return [];
    }
    att_ensure();
    $sql = 'SELECT A.`student_id`, A.`course_id`, A.`status`, COUNT(*) n FROM {attendance} A WHERE A.`student_id` IN ('.implode(',', array_map('intval', $ids)).')';
    $p = [];
    if ($from !== '') {
        $sql .= ' AND A.`date` BETWEEN ? AND ?';
        $p = [$from, $to];
    }
    $out = [];
    foreach (q($sql.' GROUP BY A.`student_id`, A.`course_id`, A.`status`', $p) as $r) {
        $out[(int) $r['student_id']][(int) $r['course_id']][$r['status']] = (int) $r['n'];
    }
    return $out;
}

const ABSENT_STATUSES = ['absent' => 'ขาด', 'leave' => 'ลา', 'sick' => 'ป่วย', 'late' => 'สาย', 'skip' => 'หนี'];

function rows_task_period_attendance_report()
{
    $mode = (string) ($_POST['attendance_persent'] ?? 'semester');
    $today = date('Y-m-d');
    [$from, $to] = match ($mode) {
        'range' => [be_date_to_iso((string) ($_POST['start_date'] ?? ''), $today), be_date_to_iso((string) ($_POST['end_date'] ?? ''), $today)],
        'week' => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d', strtotime('sunday this week'))],
        'month' => [date('Y-m-01'), date('Y-m-t')],
        default => term_range(),
    };
    $students = advisor_students();
    $att = subject_att(array_map(fn($s) => (int) $s['id'], $students), $from, $to);
    $rows = '';
    foreach ($students as $i => $s) {
        $sum = [];
        foreach ($att[(int) $s['id']] ?? [] as $c) {
            foreach ($c as $st => $n) {
                $sum[$st] = ($sum[$st] ?? 0) + $n;
            }
        }
        $cells = [$s['number'], h($s['student_id']), name_nbsp($s['name'])];
        foreach (ABSENT_STATUSES as $k => $n) {
            $cells[] = [(int) ($sum[$k] ?? 0), ' align="center"'.(($sum[$k] ?? 0) ? ' style="color:#FF0000"' : '')];
        }
        $rows .= zrow($cells, $i);
    }
    $f = '<form method="post" style="width:983px;margin:0 auto 10px;text-align:left"><span class="search"><table border="0" cellspacing="0" cellpadding="0"><tr><td>'
        .sel('attendance_persent', ['range' => 'ช่วงวันที่', 'week' => 'รายสัปดาห์', 'month' => 'รายเดือน', 'semester' => 'รายภาค'], $mode, 'onchange="this.form.submit()"')
        .'</td><td style="padding-left:5px"><input type="text" name="start_date" class="general radius" style="width:90px;height:19px" placeholder="วว/ดด/ปปปป" value="'.iso_to_be($from).'"/></td>'
        .'<td style="padding-left:5px"><input type="text" name="end_date" class="general radius" style="width:90px;height:19px" placeholder="วว/ดด/ปปปป" value="'.iso_to_be($to).'"/></td>'
        .'<td style="padding-left:5px"><input type="submit" value="ค้นหา" class="general radius" style="height:25px"/></td></tr></table></span><div style="clear:both"></div></form>';
    return sub_page('task_period_attendance_report', $f.list_table('รายงานนักเรียนขาดเรียนรายคาบ ห้อง '.h(advisor_short()).' ('.th_date($from, ' ').' - '.th_date($to, ' ').')',
        array_merge(['เลขที่', 'รหัสนร.', 'ชื่อ-นามสกุล'], array_values(ABSENT_STATUSES)), $rows));
}

function rows_task_absent_adviser_notice()
{
    $students = advisor_students();
    $att = subject_att(array_map(fn($s) => (int) $s['id'], $students));
    $courses = courses_by_id(array_merge([], ...array_map('array_keys', array_values($att))));
    $rows = '';
    $i = 0;
    foreach ($students as $s) {
        foreach ($att[(int) $s['id']] ?? [] as $cid => $c) {
            $abs = ($c['absent'] ?? 0) + ($c['skip'] ?? 0);
            if (!$abs) {
                continue;
            }
            $total = array_sum($c);
            $rows .= zrow([++$i, $s['number'], name_nbsp($s['name']), h(($courses[$cid]['course_code'] ?? '').' '.($courses[$cid]['course_name'] ?? '')), h($courses[$cid]['teacher'] ?? ''),
                [$abs, ' align="center" style="color:#FF0000"'], [$total, ' align="center"'], [number_format($abs * 100 / max(1, $total), 2), ' align="center"']], $i);
        }
    }
    return sub_page('task_absent_adviser_notice', list_table('รายชื่อนักเรียนในที่ปรึกษาขาดเรียน ห้อง '.h(advisor_short()),
        ['#', 'เลขที่', 'ชื่อ-นามสกุล', 'รายวิชา', 'ครูผู้สอน', ['ขาด/หนี (คาบ)', ' style="text-align:center"'], ['บันทึกแล้ว', ' style="text-align:center"'], ['ร้อยละ', ' style="text-align:center"']], $rows));
}

function rows_task_absent_teacher_notice()
{
    $min = (int) ($_POST['absent_persent'] ?? $_GET['p'] ?? 5);
    $courses = my_courses();
    $cids = array_map(fn($c) => (int) $c['id'], $courses);
    $rows = '';
    $i = 0;
    if ($cids) {
        att_ensure();
        $by = [];
        foreach ($courses as $c) {
            $by[(int) $c['id']] = $c;
        }
        $data = q('SELECT A.`course_id`, A.`student_id`, A.`room`, S.`student_id` code, U.`name`, COUNT(*) total, SUM(A.`status` IN (\'absent\',\'skip\')) abs FROM {attendance} A'
            .' JOIN {student} S ON S.`id`=A.`student_id` JOIN {user} U ON U.`id`=A.`student_id` WHERE A.`course_id` IN ('.implode(',', $cids).')'
            .' GROUP BY A.`course_id`, A.`student_id`, A.`room`, S.`student_id`, U.`name` HAVING abs*100 > total*? ORDER BY A.`course_id`, A.`room`, S.`student_id`', [$min]);
        foreach ($data as $r) {
            $c = $by[(int) $r['course_id']];
            $note = (string) md_get('follow', $r['student_id'], $r['course_id']);
            $rows .= zrow([++$i, h($c['course_code'].' '.$c['course_name']), h(class_room_short($c['class'], $r['room'])), h($r['code']), name_nbsp($r['name']),
                [(int) $r['abs'].'/'.(int) $r['total'], ' align="center"'], [number_format($r['abs'] * 100 / max(1, $r['total']), 2), ' align="center" style="color:#FF0000"'],
                '<input type="text" name="note['.(int) $r['student_id'].'_'.(int) $r['course_id'].']" value="'.h($note).'" class="general radius" style="width:220px;height:19px" placeholder="บันทึกการติดตาม"/>'], $i);
        }
    }
    $f = '<form method="post" style="width:983px;margin:0 auto 10px;text-align:left"><span class="search">'
        .sel('absent_persent', ['5' => 'ขาดเรียน > 5%', '10' => 'ขาดเรียน > 10%', '15' => 'ขาดเรียน > 15%', '20' => 'ขาดเรียน > 20%'], $min, 'onchange="this.form.submit()"')
        .'</span><div style="clear:both"></div></form>';
    $list = list_table('รายชื่อนักเรียนขาดเรียน ขาดเรียน > '.$min.'%', ['#', 'รายวิชา', 'ห้อง', 'รหัสนร.', 'ชื่อ-นามสกุล', ['ขาด/บันทึก', ' style="text-align:center"'], ['ร้อยละ', ' style="text-align:center"'], 'บันทึกติดตาม'], $rows, '',
        $rows ? '<a href="javascript:void(0);" onclick="document.forms.follow.submit();" id="pinkgreenbtns"><img src="../icon_tools/vec_add_white.png" border="0" height="16"/> บันทึกการติดตาม</a>' : '');
    return sub_page('task_absent_teacher_notice', saved_msg().$f.'<form name="follow" method="post" action="?module=task&option=absent_teacher_notice&func=SaveFollow&p='.$min.'">'.$list.'</form>');
}

function act_task_absent_teacher_notice_SaveFollow(): void
{
    $mine = [];
    foreach (my_courses() as $c) {
        $mine[(int) $c['id']] = true;
    }
    foreach ((array) ($_POST['note'] ?? []) as $k => $v) {
        [$sid, $cid] = array_map('intval', explode('_', (string) $k) + [0, 0]);
        if (isset($mine[$cid])) {
            md_set('follow', $sid, $cid, '', mb_substr(trim((string) $v), 0, 500));
        }
    }
    redirect('?module=task&option=absent_teacher_notice&p='.(int) ($_GET['p'] ?? 5).'&msg=saved');
}

/* ================================================================ ผลการเรียน */

const GRADE_SCALE = [[80, '4'], [75, '3.5'], [70, '3'], [65, '2.5'], [60, '2'], [55, '1.5'], [50, '1'], [0, '0']];
const GRADE_COLS = ['4', '3.5', '3', '2.5', '2', '1.5', '1', '0', 'ร', 'มส'];

/** เกณฑ์ระดับผลการเรียน (ตั้งค่าได้ที่ บริหารงานวิชาการ > กำหนดระดับผลการเรียน) */
function grade_scale(): array
{
    static $sc = null;
    return $sc ??= (json_decode((string) md_get('cfg', 'grade_scale'), true) ?: GRADE_SCALE);
}

function grade_of(int $total): string
{
    foreach (grade_scale() as [$min, $g]) {
        if ($total >= $min) {
            return $g;
        }
    }
    return '0';
}

/** จำนวนเกรดแต่ละระดับของรายวิชา */
function grade_counts(array $courseIds): array
{
    if (!$courseIds) {
        return [];
    }
    $out = [];
    foreach (q('SELECT `course_id`, `grade`, COUNT(*) n FROM {grade} WHERE `course_id` IN ('.implode(',', array_map('intval', $courseIds)).') GROUP BY `course_id`, `grade`') as $r) {
        $out[(int) $r['course_id']][(string) $r['grade']] = (int) $r['n'];
    }
    return $out;
}

function rows_task_ask_measurement()
{
    $courses = my_courses();
    $gc = grade_counts(array_map(fn($c) => (int) $c['id'], $courses));
    $rows = '';
    foreach ($courses as $i => $c) {
        $g = $gc[(int) $c['id']] ?? [];
        $cells = [$i + 1, ['<span style="white-space:nowrap">'.h($c['course_code'].' '.$c['course_name']).'</span>', ''], [array_sum($g), ' align="center"']];
        foreach (GRADE_COLS as $k) {
            $cells[] = [(int) ($g[$k] ?? 0), ' align="center"'];
        }
        $st = md_get('ask', $c['id']);
        $cells[] = [$st ? '<span style="color:#00AA00">ส่งขออนุมัติแล้ว '.h($st).'</span>' : btn('ขออนุมัติ', '?module=task&option=ask_measurement&func=Ask&SubjectNum='.(int) $c['id'], 'up'), ' align="center"'];
        $rows .= tr($cells);
    }
    return ['TBODY0' => $rows];
}

function act_task_ask_measurement_Ask(): void
{
    $c = att_course();
    if ($c) {
        md_set('ask', $c['id'], '', '', th_date('', ' '));
    }
    redirect('?module=task&option=ask_measurement');
}

function rows_task_design_measurement()
{
    $courses = my_courses();
    $rooms = course_rooms($courses);
    $rows = '';
    $i = 0;
    foreach ($courses as $c) {
        $nu = count(d_units($c));
        $st = $nu ? '<span style="color:#00AA00">ออกแบบแล้ว '.$nu.' หน่วย</span>' : '';
        $rows .= tr([++$i, h($c['course_code']), ['<span style="white-space:nowrap">'.h($c['course_name']).'</span>', ''], COURSE_TYPES[(int) $c['type']] ?? '', h(SARA_BY_PREFIX[mb_substr($c['course_code'], 0, 1)] ?? ''),
            [(int) $c['period'], ' align="center"'], [array_sum($rooms[(int) $c['id']] ?? []), ' align="center"'], h(class_short($c['class'])),
            h(implode(', ', array_map(fn($r) => class_room_short($c['class'], $r), array_keys($rooms[(int) $c['id']] ?? [])))), $st,
            [icon_link('?module=task&option=design_measurement&func=DesignMeasurement&SubjectNum='.(int) $c['id'], 'ออกแบบและรายงานผลการเรียน', 'pencil'), ' align="center"']]);
    }
    return ['TBODY0' => $rows];
}

/** สถานะผลการเรียนของรายวิชา-ห้อง: [รหัส, จำนวนที่มีเกรด, จำนวนทั้งหมด] รหัส = not|wait|ask|ok */
function record_status(array $c, int $room, array $done, int $n): array
{
    $cid = (int) $c['id'];
    if (md_get('approved', $cid) !== null) {
        return ['ok', $done[$cid][$room] ?? 0, $n];
    }
    if (md_get('ask', $cid, $room) !== null || md_get('ask', $cid) !== null) {
        return ['ask', $done[$cid][$room] ?? 0, $n];
    }
    $d = $done[$cid][$room] ?? 0;
    return [$n && $d >= $n ? 'wait' : 'not', $d, $n];
}

/** ห้องนี้ล็อกการแก้ไขหรือไม่ (ยืนยันแล้ว/อนุมัติแล้ว) */
function record_locked(array $c, int $room): bool
{
    return md_get('approved', (int) $c['id']) !== null || md_get('ask', (int) $c['id'], $room) !== null || md_get('ask', (int) $c['id']) !== null;
}

const RECORD_STATUS = ['not' => ['alert-square-red.png', 'ไม่พร้อมยืนยัน'], 'wait' => ['1352714924_info.png', 'รอยืนยัน'], 'ask' => ['001_11.png', 'รออนุมัติ'], 'ok' => ['1347351892_yes.png', 'อนุมัติ']];

function record_status_cell(array $st): string
{
    [$k, $d, $n] = $st;
    [$img, $label] = RECORD_STATUS[$k];
    $tip = $k === 'not' ? 'บันทึกผลการเรียนยังไม่ครบตามจำนวน นร.ลงทะเบียน '.$d.'/'.$n.' คน' : $label;
    return "<img src='../images/".$img."' border='0' height='16' class='icontooltip' title='".h($tip)."' align='absmiddle'/> ".$label;
}

function rows_task_study_record()
{
    $all = my_courses();
    $ids = array_map(fn($c) => (int) $c['id'], $all);
    $done = [];
    foreach ($ids ? q('SELECT `course_id`,`room`,SUM(`grade` IS NOT NULL AND `grade`<>\'\') d FROM {grade} WHERE `course_id` IN ('.implode(',', $ids).') GROUP BY `course_id`,`room`') : [] as $r) {
        $done[(int) $r['course_id']][(int) $r['room']] = (int) $r['d'];
    }
    $rooms = course_rooms($all);
    $cb = fn($name, $v) => ['<input type="checkbox" name="'.$name.'[]" value="'.h($v).'"/>', ' align="center"'];
    $edit = fn($c, $room) => ['<a href="?module=task&option=study_record&func=Record&TeacherId='.(int) $c['teacher_id'].'&SubjectNum='.(int) $c['id'].'&RecordType=subject&Elective=0&RoomNum='.$room.'" class="icontooltip" title="บันทึกผลการเรียน"><img src="../icon_tools/vec_add_dark.png" border="0" width="20" height="20"/></a>', ' align="center"'];
    // รายวิชา (ไม่รวมกิจกรรม)
    $courses = array_values(array_filter($all, fn($c) => (int) $c['type'] !== 9));
    $o = '';
    $group = null;
    $i = 0;
    foreach ($courses as $c) {
        if ($group !== (int) $c['type']) {
            $group = (int) $c['type'];
            $o .= group_row(GROUP_TITLES[$group] ?? 'หมวดวิชาอื่น ๆ', 9);
        }
        $first = true;
        foreach (($rooms[(int) $c['id']] ?? [0 => 0]) as $room => $n) {
            $st = record_status($c, (int) $room, $done, (int) $n);
            $o .= tr([++$i, [$first ? h($c['course_code'].' '.$c['course_name']) : '', ' style="white-space:nowrap"'], [$first ? (COURSE_TYPES[(int) $c['type']] ?? '') : '', ' align="center"'],
                h(class_room_short($c['class'], $room)), [$n, ' align="center"'], [record_status_cell($st), ' style="white-space:nowrap"'],
                $st[0] === 'wait' ? $cb('subjectconfirm', $c['id'].'-'.$room) : '', $st[0] === 'ask' ? $cb('subjectcancel', $c['id'].'-'.$room) : '', $edit($c, (int) $room)]);
            $first = false;
        }
    }
    // กิจกรรมพัฒนาผู้เรียน
    $acts = '';
    $j = 0;
    foreach (array_filter($all, fn($c) => (int) $c['type'] === 9) as $c) {
        $rs = $rooms[(int) $c['id']] ?? [];
        $n = array_sum($rs);
        $d = array_sum($done[(int) $c['id']] ?? []);
        $st = md_get('approved', (int) $c['id']) !== null ? ['ok', $d, $n] : (md_get('ask', (int) $c['id']) !== null ? ['ask', $d, $n] : [$n && $d >= $n ? 'wait' : 'not', $d, $n]);
        $acts .= tr([++$j, h($c['course_code']), h($c['course_name']), h(implode(', ', array_map(fn($r) => class_room_short($c['class'], $r), array_keys($rs)))), [$n, ' align="center"'],
            [record_status_cell($st), ' style="white-space:nowrap"'], $st[0] === 'wait' ? $cb('subjectconfirm', $c['id'].'-0') : '', $st[0] === 'ask' ? $cb('subjectcancel', $c['id'].'-0') : '', $edit($c, 0)]);
    }
    $btn = fn($id, $title, $msg, $none, $field, $func, $label) => '<th style="width:24px;text-align:center;height:35px" class="nosort"><div style="float:left;margin:0px 5px"><span style="float:left"><a class="icontooltip" title="'.$title.'" href="javascript:void(0);" onclick="CheckSelectboxes(\'<br>'.$msg.'\',\'<br>'.$none.'\',\''.$field.'[]\',\'task\',\'study_record\',\''.$func.'\');return false;" id="'.$id.'">&nbsp;&nbsp;'.$label.'&nbsp;&nbsp;</a></span></div></th>';
    $actTable = '<tr><td>&nbsp;</td></tr><tr id="titleList2"><td><div class="titlenav" style="width:969px"><span style="float:left;white-space:nowrap"><span id="Listtitle">&nbsp;</span><h1 style="font-size:23px; white-space:nowrap">กิจกรรมพัฒนาผู้เรียนที่สอน</h1></span></div></td></tr>'
        .'<tr id="contentList3"><td><span id="tablewrapper"><span id="tableheader"><table border="0" cellpadding="0" cellspacing="0" class="tinytable" id="table_activity" align="left" style="width:983px"><thead><tr>'
        .'<th width="24" class="nosort"><h3>#</h3></th><th style="width:70px"><h3>รหัส</h3></th><th><h3>กิจกรรมพัฒนาผู้เรียน</h3></th><th style="width:155px"><h3>ห้องเรียน</h3></th><th style="width:80px"><h3>จำนวน นร.</h3></th><th style="width:90px" class="nosort"><h3>สถานะ</h3></th>'
        .$btn('Savebtns', 'ยืนยันผลการเรียน', 'ยืนยันผลการเรียนไปยังฝ่ายวัดผล', 'กรุณาเลือกรายการที่ต้องการยืนยันอย่างน้อย 1 รายการ', 'subjectconfirm', 'ConfirmSubject', 'ยืนยัน')
        .$btn('Clearbtns', 'ยกเลิกการยืนยันผลการเรียน', 'ยกเลิกการยืนยันผลประเมิน', 'กรุณาเลือกรายการที่ต้องการยกเลิกอย่างน้อย 1 รายการ', 'subjectcancel', 'CancelSubject', 'ยกเลิก')
        .'<th class="nosort"><h3>&nbsp;</h3></th></tr></thead><tbody>'.($acts ?: '<tr><td colspan="9" align="center">ไม่มีกิจกรรมพัฒนาผู้เรียนที่สอน</td></tr>').'</tbody></table></span></span></td></tr>'
        .'<tr><td><div class="pagenav" style="width:969px"></div></td></tr>';
    return ['TBODY0' => $o ?: '<tr><td colspan="9" align="center">ไม่มีรายวิชาที่สอน</td></tr>', '__REPLACE__' => ['<tr id="PaginationList">' => $actTable.'<tr id="PaginationList" style="display:none">']];
}

/** ยืนยัน/ยกเลิกการยืนยันผลการเรียน (รายห้อง) */
function study_record_confirm(string $field, bool $on): void
{
    foreach ((array) ($_POST[$field] ?? []) as $v) {
        [$cid, $room] = array_map('intval', explode('-', (string) $v) + [1 => 0]);
        $_GET['SubjectNum'] = $cid;
        $c = att_course();
        if (!$c || md_get('approved', $cid) !== null) {
            continue;
        }
        if ($on) {
            md_set('ask', $cid, $room, '', th_date('', ' '));
            // ทุกห้องยืนยันแล้ว = ส่งขออนุมัติทั้งรายวิชา
            $rs = array_keys(course_rooms([$c])[$cid] ?? []);
            if ($room === 0 || !array_filter($rs, fn($r) => md_get('ask', $cid, $r) === null)) {
                md_set('ask', $cid, '', '', th_date('', ' '));
            }
        } else {
            md_set('ask', $cid, $room, '', null);
            md_set('ask', $cid, '', '', null);
        }
    }
    redirect('?module=task&option=study_record');
}

function act_task_study_record_ConfirmSubject(): void
{
    study_record_confirm('subjectconfirm', true);
}

function act_task_study_record_CancelSubject(): void
{
    study_record_confirm('subjectcancel', false);
}

/** ห้องทั้งหมดที่มีนักเรียน (ผู้ดูแลเห็นทุกห้อง ครูเห็นห้องที่ปรึกษา) */
function visible_rooms(): array
{
    $out = [];
    if (is_manager()) {
        foreach (rooms_by_class() as $cid => $rooms) {
            foreach ($rooms as $rid => $n) {
                $out[] = [(int) $cid, (int) $rid];
            }
        }
    } elseif (($p = me()['personnel'] ?? null) && (int) $p['room']) {
        $out[] = [(int) $p['class'], (int) $p['room']];
    }
    return $out;
}

function rows_task_report_record_byroom()
{
    $rows = '';
    foreach (visible_rooms() as $i => [$cid, $rid]) {
        $q = '&ClassNum='.$cid.'&RoomNum='.$rid;
        $rows .= tr([$i + 1, h($cid ? cat('class', $cid).'/'.preg_replace('/^\d+\//', '', room_short($rid)) : room_short($rid)),
            [icon_link('?module=task&option=report_record_byroom&func=Export'.$q, 'ส่งออกคะแนนเป็นไฟล์ excel', 'attach'), ' align="center"'],
            [icon_link('?module=task&option=report_record_byroom&func=Show&mode=score'.$q, 'ผลการเรียน (คะแนน)', 'paper'), ' align="center"'],
            [icon_link('?module=task&option=report_record_byroom&func=Show&mode=grade'.$q, 'ผลการเรียน (เกรด)', 'print'), ' align="center"']]);
    }
    return $rows;
}

/** ผลการเรียนของห้อง: [students, courses, grades[student][course]] */
function room_results(int $class, int $room): array
{
    [$y, $t] = term();
    $students = q('SELECT S.*, U.`name` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`class`=? AND S.`room`=? ORDER BY S.`number`, S.`student_id`', [$class, $room]);
    $ids = array_map(fn($s) => (int) $s['id'], $students);
    $grades = [];
    $cids = [];
    if ($ids) {
        foreach (q('SELECT G.*, C.`course_code`, C.`course_name`, C.`credit` FROM {grade} G JOIN {course} C ON C.`id`=G.`course_id` WHERE C.`year`=? AND C.`term`=? AND G.`student_id` IN ('.implode(',', $ids).')', [$y, $t]) as $g) {
            $grades[(int) $g['student_id']][(int) $g['course_id']] = $g;
            $cids[(int) $g['course_id']] = $g;
        }
    }
    ksort($cids);
    return [$students, $cids, $grades];
}

function room_allowed(int $class, int $room): bool
{
    foreach (visible_rooms() as [$c, $r]) {
        if ($c === $class && $r === $room) {
            return true;
        }
    }
    return false;
}

function act_task_report_record_byroom_Show(): void
{
    $class = (int) ($_GET['ClassNum'] ?? 0);
    $room = (int) ($_GET['RoomNum'] ?? 0);
    if (!room_allowed($class, $room)) {
        redirect('?module=task&option=report_record_byroom');
    }
    [$students, $courses, $grades] = room_results($class, $room);
    $score = ($_GET['mode'] ?? '') === 'score';
    [$y, $t] = term();
    $h = '<h2>ผลการเรียน'.($score ? ' (คะแนน)' : ' (เกรด)').' ห้อง '.h(class_room_short($class, $room)).' ภาคเรียนที่ '.$t.' ปีการศึกษา '.$y.'</h2><table><tr><th>เลขที่</th><th>รหัส</th><th>ชื่อ-นามสกุล</th>';
    foreach ($courses as $c) {
        $h .= '<th>'.h($c['course_code']).'</th>';
    }
    $h .= $score ? '</tr>' : '<th>GPA</th></tr>';
    foreach ($students as $s) {
        $h .= '<tr><td class="c">'.h($s['number']).'</td><td>'.h($s['student_id']).'</td><td>'.h($s['name']).'</td>';
        $pts = $cr = 0;
        foreach ($courses as $cid => $c) {
            $g = $grades[(int) $s['id']][$cid] ?? null;
            $h .= '<td class="c">'.($g ? h($score ? ((int) $g['midterm'] + (int) $g['final']) : $g['grade']) : '').'</td>';
            if ($g && is_numeric($g['grade'])) {
                $pts += (float) $g['grade'] * (float) $g['credit'];
                $cr += (float) $g['credit'];
            }
        }
        $h .= $score ? '</tr>' : '<td class="c">'.($cr ? number_format($pts / $cr, 2) : '-').'</td></tr>';
    }
    print_page('ผลการเรียน', $h.'</table>');
}

function act_task_report_record_byroom_Export(): void
{
    $class = (int) ($_GET['ClassNum'] ?? 0);
    $room = (int) ($_GET['RoomNum'] ?? 0);
    if (!room_allowed($class, $room)) {
        redirect('?module=task&option=report_record_byroom');
    }
    [$students, $courses, $grades] = room_results($class, $room);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="score_'.$room.'.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $head = ['เลขที่', 'รหัสนักเรียน', 'ชื่อ-นามสกุล'];
    foreach ($courses as $c) {
        array_push($head, $c['course_code'].' กลางภาค', $c['course_code'].' ปลายภาค', $c['course_code'].' เกรด');
    }
    fputcsv($out, $head);
    foreach ($students as $s) {
        $row = [$s['number'], $s['student_id'], $s['name']];
        foreach ($courses as $cid => $c) {
            $g = $grades[(int) $s['id']][$cid] ?? [];
            array_push($row, $g['midterm'] ?? '', $g['final'] ?? '', $g['grade'] ?? '');
        }
        fputcsv($out, $row);
    }
    exit;
}

function rows_task_report_pp5()
{
    return course_room_rows(7, function ($c, $room, $n, $i, $first) {
        return [$i, [$first ? h($c['course_code'].' '.$c['course_name']) : '', ' style="white-space:nowrap"'], [$first ? (COURSE_TYPES[(int) $c['type']] ?? '') : '', ' align="center"'],
            h(class_room_short($c['class'], $room)), [$n, ' align="center"'], md_get('ask', $c['id']) ? 'ขออนุมัติแล้ว' : 'รอขออนุมัติ',
            [icon_link('?module=task&option=report_pp5&func=Print&SubjectNum='.(int) $c['id'].'&RoomNum='.$room, 'พิมพ์ ปพ.5', 'print'), ' align="center"']];
    });
}

function act_task_report_pp5_Print(): void
{
    $c = att_course() ?? redirect('?module=task&option=report_pp5');
    $room = (int) ($_GET['RoomNum'] ?? 0);
    att_ensure();
    $present = "'".implode("','", \Attendance\Sheet\Model::PRESENT)."'";
    $rows = q('SELECT G.*, S.`student_id` code, U.`name`, (SELECT COUNT(*) FROM {attendance} A WHERE A.`course_id`=G.`course_id` AND A.`student_id`=G.`student_id`) total,'
        .' (SELECT COUNT(*) FROM {attendance} A WHERE A.`course_id`=G.`course_id` AND A.`student_id`=G.`student_id` AND A.`status` IN ('.$present.')) present'
        .' FROM {grade} G JOIN {student} S ON S.`id`=G.`student_id` JOIN {user} U ON U.`id`=G.`student_id` WHERE G.`course_id`=? AND G.`room`=? ORDER BY G.`number`, S.`student_id`', [(int) $c['id'], $room]);
    [$y, $t] = term();
    $h = '<h1>แบบบันทึกผลการพัฒนาคุณภาพผู้เรียน (ปพ.5)</h1><h2>'.h(cfg('web_title', 'โรงเรียนดรุณราษฎร์วิทยา')).'</h2>'
        .'<p>รายวิชา '.h($c['course_code'].' '.$c['course_name']).' &nbsp; ชั้น '.h(class_room_short($c['class'], $room)).' &nbsp; หน่วยกิต '.h((float) $c['credit']).' &nbsp; ภาคเรียนที่ '.$t.' ปีการศึกษา '.$y.'<br>ครูผู้สอน '.h($c['teacher']).'</p>'
        .'<table><tr><th>เลขที่</th><th>รหัส</th><th>ชื่อ-นามสกุล</th><th>เวลาเรียน (%)</th><th>กลางภาค</th><th>ปลายภาค</th><th>รวม</th><th>ผลการเรียน</th></tr>';
    foreach ($rows as $r) {
        $h .= '<tr><td class="c">'.h($r['number']).'</td><td>'.h($r['code']).'</td><td>'.h($r['name']).'</td><td class="c">'.($r['total'] ? number_format($r['present'] * 100 / $r['total'], 1) : '-')
            .'</td><td class="c">'.h($r['midterm']).'</td><td class="c">'.h($r['final']).'</td><td class="c">'.((int) $r['midterm'] + (int) $r['final']).'</td><td class="c"><b>'.h($r['grade']).'</b></td></tr>';
    }
    print_page('ปพ.5', $h.'</table><p style="margin-top:40px">ลงชื่อ ........................................ ครูผู้สอน &nbsp;&nbsp;&nbsp; ลงชื่อ ........................................ หัวหน้างานวัดผล</p>');
}

/* ================================================================ ปพ.6 */

function student_row_cells(array $s): array
{
    [$pre, $first, $last] = split_name($s['name']);
    return [$s['number'], h($s['student_id']), h($pre.$first), h($last), (int) $s['active'] ? 'ศึกษาอยู่' : 'พ้นสภาพ'];
}

function rows_task_report_pp6()
{
    $rows = '';
    foreach (advisor_students() as $s) {
        $cells = student_row_cells($s);
        $cells[] = h(advisor_short());
        $cells[] = [icon_link('?module=task&option=report_pp6&func=Print&StudentId='.(int) $s['id'], 'พิมพ์ ปพ.6', 'print'), ' align="center"'];
        $rows .= tr($cells);
    }
    return $rows;
}

function rows_task_info_pp6()
{
    $rows = '';
    foreach (advisor_students() as $s) {
        $cells = student_row_cells($s);
        $cells[] = [icon_link('?module=task&option=info_pp6&func=Edit&StudentId='.(int) $s['id'], 'บันทึกการเจริญเติบโตและความเห็น', 'pencil'), ' align="center"'];
        $rows .= tr($cells);
    }
    return $rows;
}

function advisor_student(int $id): ?array
{
    foreach (advisor_students() as $s) {
        if ((int) $s['id'] === $id) {
            return $s;
        }
    }
    return null;
}

function rows_task_info_pp6_Edit()
{
    $s = advisor_student((int) ($_GET['StudentId'] ?? 0)) ?? redirect('?module=task&option=info_pp6');
    $d = json_decode((string) md_get('pp6', $s['id'], yt()), true) ?: [];
    $in = fn($n, $w = 80) => '<input type="text" name="'.$n.'" value="'.h($d[$n] ?? '').'" class="general radius" style="width:'.$w.'px;height:19px"/>';
    $rows = zrow(['น้ำหนัก (กก.)', $in('weight')], 0).zrow(['ส่วนสูง (ซม.)', $in('height')], 1)
        .zrow(['ความเห็นของครูที่ปรึกษา', '<textarea name="comment" class="general radius" style="width:600px;height:80px">'.h($d['comment'] ?? '').'</textarea>'], 0);
    $body = '<form name="pp6" method="post" action="?module=task&option=info_pp6&func=Save&StudentId='.(int) $s['id'].'">'
        .list_table('ข้อมูลนักเรียนใน ปพ.6 : '.name_nbsp($s['name']), ['รายการ', 'ข้อมูล'], $rows, '', btn('กลับ', '?module=task&option=info_pp6', 'back')
            .'<a href="javascript:void(0);" onclick="document.forms.pp6.submit();" id="pinkgreenbtns" style="margin-left:4px"><img src="../icon_tools/vec_add_white.png" border="0" height="16"/> บันทึก</a>').'</form>';
    return sub_page('task_info_pp6', $body);
}

function act_task_info_pp6_Save(): void
{
    $s = advisor_student((int) ($_GET['StudentId'] ?? 0));
    if ($s) {
        md_set('pp6', $s['id'], yt(), '', json_encode(['weight' => mb_substr((string) ($_POST['weight'] ?? ''), 0, 10), 'height' => mb_substr((string) ($_POST['height'] ?? ''), 0, 10),
            'comment' => mb_substr((string) ($_POST['comment'] ?? ''), 0, 1000)], JSON_UNESCAPED_UNICODE));
    }
    redirect('?module=task&option=info_pp6');
}

function act_task_report_pp6_Print(): void
{
    $s = advisor_student((int) ($_GET['StudentId'] ?? 0)) ?? redirect('?module=task&option=report_pp6');
    [$y, $t] = term();
    $grades = q('SELECT G.*, C.`course_code`, C.`course_name`, C.`credit`, C.`type` FROM {grade} G JOIN {course} C ON C.`id`=G.`course_id` WHERE G.`student_id`=? AND C.`year`=? AND C.`term`=? ORDER BY C.`type`, C.`course_code`', [(int) $s['id'], $y, $t]);
    $d = json_decode((string) md_get('pp6', $s['id'], yt()), true) ?: [];
    $h = '<h1>แบบรายงานประจำตัวนักเรียน (ปพ.6)</h1><h2>'.h(cfg('web_title', 'โรงเรียนดรุณราษฎร์วิทยา')).'</h2><p>ชื่อ '.h($s['name']).' &nbsp; รหัส '.h($s['student_id']).' &nbsp; ชั้น '.h(advisor_short()).' เลขที่ '.h($s['number'])
        .' &nbsp; ภาคเรียนที่ '.$t.' ปีการศึกษา '.$y.'</p><table><tr><th>รหัสวิชา</th><th>รายวิชา</th><th>ประเภท</th><th>หน่วยกิต</th><th>ผลการเรียน</th></tr>';
    $pts = $cr = 0;
    foreach ($grades as $g) {
        $h .= '<tr><td>'.h($g['course_code']).'</td><td>'.h($g['course_name']).'</td><td class="c">'.(COURSE_TYPES[(int) $g['type']] ?? '').'</td><td class="c">'.h((float) $g['credit']).'</td><td class="c"><b>'.h($g['grade']).'</b></td></tr>';
        if (is_numeric($g['grade'])) {
            $pts += (float) $g['grade'] * (float) $g['credit'];
            $cr += (float) $g['credit'];
        }
    }
    $h .= '<tr><th colspan="3" style="text-align:right">ผลการเรียนเฉลี่ย</th><th>'.$cr.'</th><th>'.($cr ? number_format($pts / $cr, 2) : '-').'</th></tr></table>';
    $h .= '<table><tr><th>คุณลักษณะอันพึงประสงค์</th><th>การอ่าน คิดวิเคราะห์และเขียน</th><th>น้ำหนัก/ส่วนสูง</th></tr><tr><td class="c">'.h(level_text(score_avg('char', (int) $s['id'])))
        .'</td><td class="c">'.h(level_text(score_avg('skill', (int) $s['id']))).'</td><td class="c">'.h(($d['weight'] ?? '-').' กก. / '.($d['height'] ?? '-').' ซม.').'</td></tr></table>';
    $h .= '<p><b>ความเห็นของครูที่ปรึกษา</b><br>'.nl2br(h($d['comment'] ?? '')).'</p><p style="margin-top:40px">ลงชื่อ ........................................ ครูที่ปรึกษา &nbsp;&nbsp;&nbsp; ลงชื่อ ........................................ ผู้ปกครอง</p>';
    print_page('ปพ.6', $h);
}

/* ================================================================ แบบประเมิน (คุณลักษณะ / อ่านคิดเขียน / สมรรถนะ) */

const SCORE_SETS = [
    'char' => ['option' => 'character_record', 'report' => 'report_character', 'title' => 'คุณลักษณะอันพึงประสงค์', 'items' => ['รักชาติ ศาสน์ กษัตริย์', 'ซื่อสัตย์สุจริต', 'มีวินัย', 'ใฝ่เรียนรู้', 'อยู่อย่างพอเพียง', 'มุ่งมั่นในการทำงาน', 'รักความเป็นไทย', 'มีจิตสาธารณะ']],
    'skill' => ['option' => 'skill_record', 'report' => 'report_skill', 'title' => 'การอ่าน คิดวิเคราะห์และเขียน', 'items' => ['การอ่าน', 'การคิดวิเคราะห์', 'การเขียน', 'การสรุปความ', 'การนำเสนอ']],
    'capj' => ['option' => 'capacity_evaluate_junior', 'report' => 'capacity_summary', 'title' => 'สมรรถนะสำคัญของผู้เรียน', 'items' => ['ความสามารถในการสื่อสาร', 'ความสามารถในการคิด', 'ความสามารถในการแก้ปัญหา', 'ความสามารถในการใช้ทักษะชีวิต', 'ความสามารถในการใช้เทคโนโลยี']],
    'capp' => ['option' => 'capacity_evaluate_prathom', 'report' => 'capacity_summary', 'title' => 'สมรรถนะสำคัญของผู้เรียน', 'items' => ['ความสามารถในการสื่อสาร', 'ความสามารถในการคิด', 'ความสามารถในการแก้ปัญหา', 'ความสามารถในการใช้ทักษะชีวิต', 'ความสามารถในการใช้เทคโนโลยี']],
];
const LEVELS = [3 => 'ดีเยี่ยม', 2 => 'ดี', 1 => 'ผ่าน', 0 => 'ไม่ผ่าน'];

function level_text(?float $avg): string
{
    if ($avg === null) {
        return '-';
    }
    return $avg >= 2.5 ? 'ดีเยี่ยม' : ($avg >= 1.5 ? 'ดี' : ($avg >= 1 ? 'ผ่าน' : 'ไม่ผ่าน'));
}

function scores_of(string $kind, array $ids): array
{
    $out = [];
    foreach (md_rows($kind, $ids, yt()) as $r) {
        $out[(int) $r['r1']][(int) $r['r3']] = (int) $r['value'];
    }
    return $out;
}

function score_avg(string $kind, int $sid): ?float
{
    $s = scores_of($kind, [$sid])[$sid] ?? [];
    return $s ? array_sum($s) / count($s) : null;
}

/** รายการห้องในความรับผิดชอบ (#, ห้อง, ปีการศึกษา, ปุ่ม) */
function score_room_rows(string $kind): string
{
    $set = SCORE_SETS[$kind];
    $rows = '';
    foreach (visible_rooms() as $i => [$cid, $rid]) {
        $rows .= tr([$i + 1, h($cid ? cat('class', $cid).'/'.preg_replace('/^\d+\//', '', room_short($rid)) : room_short($rid)), yt(),
            [icon_link('?module=task&option='.$set['option'].'&func=Record&ClassNum='.$cid.'&RoomNum='.$rid, 'บันทึก'.$set['title'], 'pencil'), ' align="center" width="60"']]);
    }
    return $rows;
}

function score_sheet(string $kind): array
{
    $set = SCORE_SETS[$kind];
    $class = (int) ($_GET['ClassNum'] ?? 0);
    $room = (int) ($_GET['RoomNum'] ?? 0);
    if (!room_allowed($class, $room)) {
        redirect('?module=task&option='.$set['option']);
    }
    $students = q('SELECT S.*, U.`name` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`class`=? AND S.`room`=? ORDER BY S.`number`, S.`student_id`', [$class, $room]);
    $sc = scores_of($kind, array_map(fn($s) => (int) $s['id'], $students));
    $ths = ['เลขที่', 'ชื่อ-นามสกุล'];
    foreach ($set['items'] as $k => $item) {
        $ths[] = ['<span title="'.h($item).'">'.($k + 1).'</span>', ' style="text-align:center" title="'.h($item).'"'];
    }
    $ths[] = ['ผล', ' style="text-align:center"'];
    $rows = '';
    $opts = ['' => '-'] + array_combine(array_keys(LEVELS), array_keys(LEVELS));
    foreach ($students as $i => $s) {
        $cells = [$s['number'], name_nbsp($s['name'])];
        foreach ($set['items'] as $k => $item) {
            $cells[] = [sel('sc['.(int) $s['id'].']['.$k.']', $opts, $sc[(int) $s['id']][$k] ?? '', 'style="height:22px;width:45px"'), ' align="center"'];
        }
        $v = $sc[(int) $s['id']] ?? [];
        $cells[] = [level_text($v ? array_sum($v) / count($v) : null), ' align="center"'];
        $rows .= zrow($cells, $i);
    }
    $legend = '';
    foreach ($set['items'] as $k => $item) {
        $legend .= ($k + 1).'. '.h($item).' &nbsp; ';
    }
    $body = saved_msg().'<form name="score" method="post" action="?module=task&option='.$set['option'].'&func=SaveScore&ClassNum='.$class.'&RoomNum='.$room.'">'
        .list_table('บันทึก'.$set['title'].' ห้อง '.h(class_room_short($class, $room)).' ปีการศึกษา '.yt(), $ths, $rows, '3=ดีเยี่ยม 2=ดี 1=ผ่าน 0=ไม่ผ่าน',
            '<span style="float:left;text-align:left;font-size:11px;max-width:700px">'.$legend.'</span>'.btn('กลับ', '?module=task&option='.$set['option'], 'back')
            .'<a href="javascript:void(0);" onclick="document.forms.score.submit();" id="pinkgreenbtns" style="margin-left:4px"><img src="../icon_tools/vec_add_white.png" border="0" height="16"/> บันทึก</a>')
        .'</form>';
    return sub_page('task_'.$set['option'], $body);
}

function score_save(string $kind): void
{
    $set = SCORE_SETS[$kind];
    $class = (int) ($_GET['ClassNum'] ?? 0);
    $room = (int) ($_GET['RoomNum'] ?? 0);
    if (room_allowed($class, $room)) {
        $ids = array_map(fn($r) => (int) $r['id'], q('SELECT `id` FROM {student} WHERE `class`=? AND `room`=?', [$class, $room]));
        foreach ((array) ($_POST['sc'] ?? []) as $sid => $items) {
            if (!in_array((int) $sid, $ids, true)) {
                continue;
            }
            foreach ($set['items'] as $k => $x) {
                $v = (string) ($items[$k] ?? '');
                md_set($kind, (int) $sid, yt(), $k, isset(LEVELS[(int) $v]) && $v !== '' ? (string) (int) $v : null);
            }
        }
    }
    redirect('?module=task&option='.$set['option'].'&func=Record&ClassNum='.$class.'&RoomNum='.$room.'&msg=saved');
}

function rows_task_character_record() { return score_room_rows('char'); }
function rows_task_character_record_Record() { return score_sheet('char'); }
function act_task_character_record_SaveScore(): void { score_save('char'); }
function rows_task_skill_record() { return score_room_rows('skill'); }
function rows_task_skill_record_Record() { return score_sheet('skill'); }
function act_task_skill_record_SaveScore(): void { score_save('skill'); }
function rows_task_capacity_evaluate_junior() { return score_room_rows('capj'); }
function rows_task_capacity_evaluate_junior_Record() { return score_sheet('capj'); }
function act_task_capacity_evaluate_junior_SaveScore(): void { score_save('capj'); }

function rows_task_capacity_evaluate_prathom()
{
    return sub_page('task_capacity_evaluate_prathom', list_table('รายชื่อชั้นเรียนในความรับผิดชอบ', ['#', 'ห้องเรียน', 'ปีการศึกษา', ''], score_room_rows('capp')));
}
function rows_task_capacity_evaluate_prathom_Record() { return score_sheet('capp'); }
function act_task_capacity_evaluate_prathom_SaveScore(): void { score_save('capp'); }

/** สรุปรายห้อง: จำนวนนักเรียนตามระดับผล */
function score_summary_rows(string $kind, bool $sex): string
{
    $rows = '';
    foreach (visible_rooms() as $i => [$cid, $rid]) {
        $st = q('SELECT S.`id`, U.`sex` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`class`=? AND S.`room`=?', [$cid, $rid]);
        $sc = scores_of($kind, array_map(fn($s) => (int) $s['id'], $st));
        $lv = array_fill_keys(array_values(LEVELS), 0);
        foreach ($st as $s) {
            $v = $sc[(int) $s['id']] ?? [];
            if ($v) {
                $lv[level_text(array_sum($v) / count($v))]++;
            }
        }
        $name = h($cid ? cat('class', $cid).'/'.preg_replace('/^\d+\//', '', room_short($rid)) : room_short($rid));
        $summary = implode(' ', array_map(fn($k, $n) => "$k $n", array_keys($lv), $lv));
        $cells = $sex
            ? [$i + 1, $name, 'ปกติ', [count($st), ' align="center"'], [count(array_filter($st, fn($s) => $s['sex'] === 'm')), ' align="center"'], [count(array_filter($st, fn($s) => $s['sex'] === 'f')), ' align="center"'], $summary]
            : [$i + 1, $name, [count($st), ' align="center"'], [count(array_filter($st, fn($s) => $s['sex'] === 'm')), ' align="center"'], [count(array_filter($st, fn($s) => $s['sex'] === 'f')), ' align="center"'], $summary];
        $rows .= tr($cells);
    }
    return $rows;
}

function rows_task_report_character() { return score_summary_rows('char', true); }
function rows_task_report_skill() { return score_summary_rows('skill', false); }

function rows_task_capacity_summary()
{
    $rows = '';
    foreach (visible_rooms() as $i => [$cid, $rid]) {
        $st = q('SELECT `id` FROM {student} WHERE `class`=? AND `room`=?', [$cid, $rid]);
        $ids = array_map(fn($s) => (int) $s['id'], $st);
        $lv = array_fill_keys(array_values(LEVELS), 0);
        foreach (['capj', 'capp'] as $k) {
            foreach (scores_of($k, $ids) as $v) {
                $lv[level_text(array_sum($v) / count($v))]++;
            }
        }
        $rows .= tr([$i + 1, h($cid ? cat('class', $cid).'/'.preg_replace('/^\d+\//', '', room_short($rid)) : room_short($rid)), yt(), implode(' ', array_map(fn($k, $n) => "$k $n", array_keys($lv), $lv))]);
    }
    return $rows;
}

function rows_task_capacity_assign_friend()
{
    $students = advisor_students();
    $names = [0 => '- เลือกเพื่อน -'];
    foreach ($students as $s) {
        $names[(int) $s['id']] = $s['number'].'. '.$s['name'];
    }
    $friends = [];
    foreach (md_rows('friend', array_keys($names), yt()) as $r) {
        $friends[(int) $r['r1']] = (int) $r['value'];
    }
    $rows = '';
    foreach ($students as $s) {
        $f = $friends[(int) $s['id']] ?? 0;
        $fs = null;
        foreach ($students as $x) {
            if ((int) $x['id'] === $f) {
                $fs = $x;
            }
        }
        $rows .= tr([$s['number'], h($s['student_id']), name_nbsp($s['name']), h($fs['student_id'] ?? ''), sel('friend['.(int) $s['id'].']', $names, $f, 'style="width:250px;height:22px"')]);
    }
    $btn = '<div style="width:983px;margin:6px auto;text-align:right"><a href="javascript:void(0);" onclick="document.forms.list.action=\'?module=task&option=capacity_assign_friend&func=SaveFriend\';document.forms.list.submit();" id="pinkgreenbtns"><img src="../icon_tools/vec_add_white.png" border="0" height="16"/> บันทึกเพื่อนประเมิน</a></div>';
    return ['TBODY0' => $rows, '__APPEND__' => saved_msg().$btn];
}

function act_task_capacity_assign_friend_SaveFriend(): void
{
    $ids = array_map(fn($s) => (int) $s['id'], advisor_students());
    foreach ((array) ($_POST['friend'] ?? []) as $sid => $f) {
        if (in_array((int) $sid, $ids, true)) {
            md_set('friend', (int) $sid, yt(), '', in_array((int) $f, $ids, true) && (int) $f !== (int) $sid ? (string) (int) $f : null);
        }
    }
    redirect('?module=task&option=capacity_assign_friend&msg=saved');
}

/* ================================================================ แบบสอบถาม กราชา / คอล์บ / ประเมินครู */

function questionnaire_status(string $kind): array
{
    $students = advisor_students();
    $allow = [];
    foreach (md_rows($kind.'_allow', array_map(fn($s) => (int) $s['id'], $students), yt()) as $r) {
        $allow[(int) $r['r1']] = $r['value'];
    }
    $done = [];
    foreach (md_rows($kind, array_map(fn($s) => (int) $s['id'], $students), yt()) as $r) {
        $done[(int) $r['r1']] = true;
    }
    $rows = '';
    foreach ($students as $s) {
        [$pre, $first, $last] = split_name($s['name']);
        $id = (int) $s['id'];
        $a = ($allow[$id] ?? '0') === '1';
        $rows .= tr([$s['number'], h($s['student_id']), h($pre.$first), h($last), isset($done[$id]) ? 'ทำแบบสอบถามแล้ว' : 'ยังไม่ทำแบบสอบถาม',
            ['<input type="radio" name="allow['.$id.']" value="1"'.($a ? ' checked' : '').'/>', ' align="center"'], ['<input type="radio" name="allow['.$id.']" value="0"'.(!$a ? ' checked' : '').'/>', ' align="center"']]);
    }
    $opt = $kind === 'grasha' ? 'grasha_status' : 'kolb_status';
    $btn = '<div style="width:983px;margin:6px auto;text-align:right"><a href="javascript:void(0);" onclick="document.forms.list.action=\'?module=task&option='.$opt.'&func=SaveAllow\';document.forms.list.submit();" id="pinkgreenbtns"><img src="../icon_tools/vec_add_white.png" border="0" height="16"/> บันทึกสิทธิ์การแก้ไข</a></div>';
    return ['TBODY0' => $rows, '__APPEND__' => saved_msg().$btn];
}

function questionnaire_allow(string $kind): void
{
    $ids = array_map(fn($s) => (int) $s['id'], advisor_students());
    foreach ((array) ($_POST['allow'] ?? []) as $sid => $v) {
        if (in_array((int) $sid, $ids, true)) {
            md_set($kind.'_allow', (int) $sid, yt(), '', $v === '1' ? '1' : null);
        }
    }
    redirect('?module=task&option='.($kind === 'grasha' ? 'grasha_status' : 'kolb_status').'&msg=saved');
}

function rows_task_grasha_status() { return questionnaire_status('grasha'); }
function act_task_grasha_status_SaveAllow(): void { questionnaire_allow('grasha'); }
function rows_task_kolb_status() { return questionnaire_status('kolb'); }
function act_task_kolb_status_SaveAllow(): void { questionnaire_allow('kolb'); }

function questionnaire_report(string $kind): string
{
    return course_room_rows(6, function ($c, $room, $n, $i, $first) use ($kind) {
        $ids = array_map(fn($r) => (int) $r['student_id'], q('SELECT `student_id` FROM {grade} WHERE `course_id`=? AND `room`=?', [(int) $c['id'], $room]));
        return [$i, [$first ? h($c['course_code'].' '.$c['course_name']) : '', ' style="white-space:nowrap"'], [$first ? (COURSE_TYPES[(int) $c['type']] ?? '') : '', ' align="center"'],
            h(class_room_short($c['class'], $room)), [$n, ' align="center"'], [count(md_rows($kind, $ids, yt())), ' align="center"']];
    });
}

function rows_task_grasha_report() { return questionnaire_report('grasha'); }
function rows_task_kolb_report() { return questionnaire_report('kolb'); }

function rows_task_report_questionnaire_teacher()
{
    return course_room_rows(6, function ($c, $room, $n, $i, $first) {
        return [$i, [$first ? h($c['course_code'].' '.$c['course_name']) : '', ' style="white-space:nowrap"'], [$first ? (COURSE_TYPES[(int) $c['type']] ?? '') : '', ' align="center"'],
            h(class_room_short($c['class'], $room)), [$n, ' align="center"'], [count(md_rows('teval', null, $c['id'].'-'.$room)), ' align="center"']];
    });
}

function rows_task_usability_info()
{
    $students = advisor_students();
    $ids = array_map(fn($s) => (int) $s['id'], $students);
    $has = [];
    foreach (['grasha', 'kolb', 'char', 'skill', 'sdq'] as $k) {
        foreach (md_rows($k, $ids, yt()) as $r) {
            $has[$k][(int) $r['r1']] = true;
        }
    }
    $rows = '';
    $mark = fn($b) => [$b ? '<img src="../icon_tools/vec_check_dark.png" height="16"/>' : '-', ' align="center"'];
    foreach ($students as $s) {
        [$pre, $first, $last] = split_name($s['name']);
        $id = (int) $s['id'];
        $personal = !empty($s['phone']) || !empty($s['student_id']);
        $flags = [$personal, isset($has['grasha'][$id]), isset($has['kolb'][$id]), isset($has['char'][$id]), isset($has['skill'][$id]), isset($has['sdq'][$id])];
        $rows .= tr(array_merge([$s['number'], h($s['student_id']), h($pre.$first), h($last)], array_map($mark, $flags), [[number_format(count(array_filter($flags)) * 100 / count($flags), 2), ' align="center"']]));
    }
    return $rows;
}

/* ================================================================ กิจกรรม */

function rows_task_join_activity()
{
    $courses = my_courses();
    $students = [];
    foreach ($courses ? q('SELECT DISTINCT S.`id`, S.`student_id`, U.`name` FROM {grade} G JOIN {student} S ON S.`id`=G.`student_id` JOIN {user} U ON U.`id`=S.`id` WHERE G.`course_id` IN ('.implode(',', array_map(fn($c) => (int) $c['id'], $courses)).') ORDER BY S.`student_id`') : [] as $s) {
        $students[(int) $s['id']] = $s['student_id'].' '.$s['name'];
    }
    foreach (advisor_students() as $s) {
        $students[(int) $s['id']] = $s['student_id'].' '.$s['name'];
    }
    $opts = '';
    foreach ($students as $id => $n) {
        $opts .= '<option value="'.$id.'">'.h($n).'</option>';
    }
    $in = fn($n, $ph, $w = 400) => '<input type="text" name="'.$n.'" class="general radius" style="width:'.$w.'px;height:19px" placeholder="'.$ph.'"/>';
    $rows = zrow(['ชื่อกิจกรรม', $in('name', 'ชื่อกิจกรรม')], 0).zrow(['สถานที่', $in('place', 'สถานที่')], 1).zrow(['วันที่', $in('date', 'วว/ดด/ปปปป', 120)], 0)
        .zrow(['นักเรียนที่เข้าร่วม', '<select name="students[]" multiple size="10" class="general radius" style="width:400px;height:auto">'.$opts.'</select><br><small>กด Ctrl ค้างเพื่อเลือกหลายคน</small>'], 1);
    $body = saved_msg().'<form name="ja" method="post" action="?module=task&option=join_activity&func=SaveJoin">'
        .list_table('ขออนุญาตให้นักเรียนเข้าร่วมกิจกรรม', ['รายการ', 'ข้อมูล'], $rows, '', '<a href="javascript:void(0);" onclick="document.forms.ja.submit();" id="pinkgreenbtns"><img src="../icon_tools/vec_add_white.png" border="0" height="16"/> ส่งคำขออนุญาต</a>')
        .'</form>';
    return sub_page('task_join_activity', $body);
}

function act_task_join_activity_SaveJoin(): void
{
    $name = trim((string) ($_POST['name'] ?? ''));
    $ids = array_values(array_filter(array_map('intval', (array) ($_POST['students'] ?? []))));
    if ($name !== '' && $ids) {
        md_set('join', (string) me()['id'], yt(), (string) round(microtime(true) * 1000), json_encode(['name' => mb_substr($name, 0, 200), 'place' => mb_substr((string) ($_POST['place'] ?? ''), 0, 200),
            'date' => be_date_to_iso((string) ($_POST['date'] ?? '')), 'students' => $ids, 'status' => 'รออนุมัติ'], JSON_UNESCAPED_UNICODE));
    }
    redirect('?module=task&option=join_activity_student&msg=saved');
}

function rows_task_join_activity_student()
{
    $rows = '';
    $all = is_manager() ? md_rows('join', null, yt()) : md_rows('join', (string) me()['id'], yt());
    $i = 0;
    foreach ($all as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        $names = array_map(fn($s) => $s['name'], $d['students'] ? q('SELECT `name` FROM {user} WHERE `id` IN ('.implode(',', array_map('intval', $d['students'])).')') : []);
        $rows .= zrow([++$i, h($d['name'] ?? ''), h($d['place'] ?? ''), !empty($d['date']) ? th_date($d['date'], ' ') : '', h(implode(', ', $names)), h($d['status'] ?? '')], $i);
    }
    return sub_page('task_join_activity_student', saved_msg().list_table('รายชื่อนักเรียนขออนุญาตเข้าร่วมกิจกรรม', ['#', 'กิจกรรม', 'สถานที่', 'วันที่', 'นักเรียน', 'สถานะ'], $rows, '', btn('ขออนุญาตเพิ่ม', '?module=task&option=join_activity', 'add')));
}

/* ================================================================ รายชื่อนักเรียนรายวิชา */

function rows_task_respons_subject_StudentSubjectList()
{
    $c = att_course() ?? redirect('?module=task&option=respons_subject');
    $room = (int) ($_GET['RoomNum'] ?? 0);
    $rows = '';
    foreach (\Attendance\Sheet\Model::students($c['id'], $room) as $i => $s) {
        $rows .= zrow([$s['number'], h($s['code']), name_nbsp($s['name']), h($s['room_name'] ?? room_short($s['room']))], $i);
    }
    return sub_page('task_respons_subject', list_table('รายชื่อนักเรียน '.h($c['course_code'].' '.$c['course_name'].' '.class_room_short($c['class'], $room)), ['เลขที่', 'รหัสนร.', 'ชื่อ-นามสกุล', 'ห้อง'], $rows, '', btn('กลับ', '?module=task&option=respons_subject', 'back')));
}

/* ================================================================ ข้อมูลหลัก / วิชาการ */

function rows_main_manager()
{
    $rows = '';
    $i = 0;
    foreach (q('SELECT U.`name`, P.`position` FROM {personnel} P JOIN {user} U ON U.`id`=P.`id` WHERE P.`position` IN (1,2) AND U.`active`=1 ORDER BY P.`position`, P.`order`') as $p) {
        $pos = cat('position', $p['position']);
        $rows .= tr([++$i, h($pos), (int) $p['position'] === 1 ? 'ผอ.' : 'รอง ผอ.', name_nbsp($p['name'])]);
    }
    return $rows;
}

function rows_main_student_stat()
{
    $all = q('SELECT S.`class`, S.`room`, U.`sex`, U.`active` FROM {student} S JOIN {user} U ON U.`id`=S.`id`');
    $count = function (array $rows) {
        $a = array_filter($rows, fn($r) => (int) $r['active'] === 1);
        return [count($a), count(array_filter($a, fn($r) => $r['sex'] === 'm')), count(array_filter($a, fn($r) => $r['sex'] === 'f')), 0, count($rows) - count($a)];
    };
    $c = fn($v) => [$v, ' style="text-align:center"'];
    $t0 = tr(array_map($c, $count($all)));
    $t1 = $t2 = $t3 = '';
    $i = 0;
    foreach (cats('class') as $cid => $name) {
        $rows = array_filter($all, fn($r) => (int) $r['class'] === (int) $cid);
        $t1 .= tr(array_merge([h($name)], array_map($c, $count($rows))));
        $t3 .= tr([++$i, h($name), $c(count($rows)), $c(0), $c('0.00')]);
        foreach (rooms_by_class()[$cid] ?? [] as $rid => $rname) {
            $t2 .= tr(array_merge([h(class_room_short($cid, $rid))], array_map($c, $count(array_filter($rows, fn($r) => (int) $r['room'] === (int) $rid)))));
        }
    }
    return ['TBODY0' => $t0, 'TBODY1' => $t1, 'TBODY2' => $t2, 'TBODY3' => $t3];
}

function rows_academic_sara()
{
    $rows = '';
    foreach (cats('department') as $id => $name) {
        $head = q1('SELECT U.`name`, U.`phone` FROM {personnel} P JOIN {user} U ON U.`id`=P.`id` WHERE P.`department`=? AND U.`active`=1 ORDER BY P.`position`, P.`order` LIMIT 1', [(int) $id]);
        $rows .= tr([$id, h($name), name_nbsp($head['name'] ?? ''), h($head['phone'] ?? '')]);
    }
    return $rows;
}

function rows_academic_target()
{
    $rows = '';
    $i = 0;
    foreach (cats('department') as $id => $name) {
        $d = md_get('target', $id, yt());
        $rows .= tr([++$i, h($name), $d ? th_date($d, ' ') : '-', $d ? 'กำหนดแล้ว' : 'ยังไม่กำหนด',
            [is_manager() ? icon_link('?module=academic&option=target&func=Set&Sara='.(int) $id, 'บันทึกวันที่ประชุมมีมติ', 'check') : '', ' align="center"']]);
    }
    return $rows;
}

function act_academic_target_Set(): void
{
    if (is_manager()) {
        md_set('target', (int) ($_GET['Sara'] ?? 0), yt(), '', date('Y-m-d'));
    }
    redirect('?module=academic&option=target');
}

function rows_academic_academic_calendar()
{
    $cal = json_decode((string) md_get('calendar', yt()), true) ?: [];
    [$from, $to] = term_range();
    $dis = is_manager() ? '' : ' disabled';
    $form = '<tr><td width="200" style="padding:8px">วันเปิดภาคเรียน</td><td><input type="text" name="start" value="'.iso_to_be($cal['start'] ?? $from).'" class="general radius" style="width:120px;height:19px"'.$dis.'/></td></tr>'
        .'<tr><td style="padding:8px">วันปิดภาคเรียน</td><td><input type="text" name="end" value="'.iso_to_be($cal['end'] ?? $to).'" class="general radius" style="width:120px;height:19px"'.$dis.'/></td></tr>'
        .'<tr><td style="padding:8px">วันหยุด (วว/ดด/ปปปป คั่นด้วย ,)</td><td><input type="text" name="holidays" value="'.h($cal['holidays'] ?? '').'" class="general radius" style="width:600px;height:19px"'.$dis.'/></td></tr>'
        .(is_manager() ? '<tr><td></td><td style="padding:8px"><a href="javascript:void(0);" onclick="document.forms.operationform.submit();" id="pinkgreenbtns"><img src="../icon_tools/vec_add_white.png" border="0" height="16"/> บันทึกปฏิทิน</a></td></tr>' : '');
    return ['__REPLACE__' => ["<tr>\n <td>&nbsp;</td>\n </tr>\n <tr>\n </tr>" => $form], '__APPEND__' => saved_msg()];
}

function act_academic_academic_calendar_Save(): void
{
    if (is_manager()) {
        md_set('calendar', yt(), '', '', json_encode(['start' => be_date_to_iso((string) ($_POST['start'] ?? '')), 'end' => be_date_to_iso((string) ($_POST['end'] ?? '')),
            'holidays' => mb_substr((string) ($_POST['holidays'] ?? ''), 0, 1000)], JSON_UNESCAPED_UNICODE));
    }
    redirect('?module=academic&option=academic_calendar&msg=saved');
}

function rows_academic_teacher_questionnaire()
{
    $items = ['ครูเตรียมการสอนและเข้าสอนตรงเวลา', 'ครูอธิบายเนื้อหาชัดเจน เข้าใจง่าย', 'ครูใช้สื่อและวิธีสอนที่หลากหลาย', 'ครูเปิดโอกาสให้นักเรียนซักถามและแสดงความคิดเห็น',
        'ครูวัดผลและประเมินผลอย่างยุติธรรม', 'ครูแจ้งผลการประเมินและให้คำแนะนำ', 'ครูเป็นแบบอย่างที่ดี', 'นักเรียนพึงพอใจต่อการสอนของครูโดยรวม'];
    $rows = '';
    foreach ($items as $i => $t) {
        $rows .= zrow([$i + 1, h($t), ['5 ระดับ (มากที่สุด - น้อยที่สุด)', ' align="center"']], $i);
    }
    return sub_page('academic_teacher_questionnaire', list_table('แบบประเมินครูผู้สอน', ['ข้อ', 'รายการประเมิน', ['มาตรวัด', ' style="text-align:center"']], $rows));
}
