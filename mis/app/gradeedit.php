<?php
/**
 * mis/app/gradeedit.php
 * - GPAX แยกช่วงชั้น (ม.ต้น / ม.ปลาย) ไม่นำผลการเรียนต่างช่วงชั้นมารวมกัน
 * - บันทึกผลการเรียนรายบุคคล (ทุกวิชาในภาคเรียนที่เลือก ย้อนหลังได้) สำหรับผู้ดูแลระบบและฝ่ายวิชาการ
 */
defined('MIS') or exit;

const LEVEL_NAME = ['p' => 'ประถมศึกษา', 'j' => 'มัธยมศึกษาตอนต้น', 's' => 'มัธยมศึกษาตอนปลาย'];
const LEVEL_SHORT = ['p' => 'ป.', 'j' => 'ม.ต้น', 's' => 'ม.ปลาย'];

/** ช่วงชั้นของระดับชั้น: p = ประถม, j = ม.1-3, s = ม.4-6, '' = ไม่ทราบ */
function class_level($class): string
{
    $c = class_short($class);
    if (preg_match('/^ม\.(\d+)/u', $c, $m)) {
        return (int) $m[1] <= 3 ? 'j' : 's';
    }
    return preg_match('/^ป\./u', $c) ? 'p' : '';
}

/** ช่วงชั้นของผลการเรียน 1 รายการ (ตามระดับชั้นของรายวิชา) */
function grade_level(array $g): string
{
    return class_level($g['cclass'] ?? 0);
}

/** GPAX แยกตามช่วงชั้น [ช่วงชั้น => GPAX] เฉพาะช่วงชั้นที่มีผลการเรียน (ผลการเรียนที่ไม่ทราบช่วงชั้นใช้ key '') */
function gpax_levels(array $grades): array
{
    $by = [];
    foreach ($grades as $g) {
        if ((int) ($g['ctype'] ?? 1) === 9) {
            continue;
        }
        $by[grade_level($g)][] = $g;
    }
    $o = [];
    foreach (['p', 'j', 's', ''] as $k) {
        if (isset($by[$k]) && ($v = gpa_of($by[$k])) !== null) {
            $o[$k] = $v;
        }
    }
    return $o;
}

/** GPAX ของช่วงชั้นปัจจุบันของนักเรียน (ไม่ปนกับช่วงชั้นอื่น) */
function gpax_current(array $grades, $class): ?float
{
    $lv = class_level($class);
    if ($lv === '') {
        return gpa_of($grades);
    }
    return gpa_of(array_filter($grades, fn($g) => in_array(grade_level($g), [$lv, ''], true)));
}

/** แถวสรุป GPAX ท้ายตาราง: แยก ม.ต้น / ม.ปลาย เมื่อมีผลการเรียนทั้งสองช่วงชั้น */
function gpax_rows(array $grades, int $colspan, string $cellAttr = 'class="c"'): string
{
    $lv = gpax_levels($grades);
    $real = array_filter($lv, fn($k) => $k !== '', ARRAY_FILTER_USE_KEY);
    if (count($real) <= 1) {
        $v = gpa_of(array_filter($grades, fn($g) => (int) ($g['ctype'] ?? 1) !== 9));
        return '<tr><td colspan="'.$colspan.'" style="text-align:right"><b>ผลการเรียนเฉลี่ยสะสม (GPAX)'.($real ? ' '.LEVEL_SHORT[array_key_first($real)] : '').'</b></td><td '.$cellAttr.'><b>'.($v === null ? '-' : number_format($v, 2)).'</b></td></tr>';
    }
    $o = '';
    foreach ($real as $k => $v) {
        $o .= '<tr><td colspan="'.$colspan.'" style="text-align:right"><b>ผลการเรียนเฉลี่ยสะสม (GPAX) ระดับ'.LEVEL_NAME[$k].'</b></td><td '.$cellAttr.'><b>'.number_format($v, 2).'</b></td></tr>';
    }
    return $o;
}

/* ================================================================ บันทึกผลการเรียนรายบุคคล */

/** ปีการศึกษา/ภาคเรียนที่มีรายวิชาในระบบ + ภาคเรียนปัจจุบัน */
function ge_terms(): array
{
    $o = [];
    foreach (q('SELECT DISTINCT `year`, `term` FROM {course} ORDER BY `year` DESC, `term` DESC') as $r) {
        $o[(int) $r['year'].'/'.(int) $r['term']] = 'ภาคเรียนที่ '.(int) $r['term'].' ปีการศึกษา '.(int) $r['year'];
    }
    [$y, $t] = array_map('intval', explode('/', yt()));
    for ($yy = $y; $yy >= $y - 6; $yy--) {
        foreach ([2, 1] as $tt) {
            $o[$yy.'/'.$tt] ??= 'ภาคเรียนที่ '.$tt.' ปีการศึกษา '.$yy;
        }
    }
    uksort($o, fn($a, $b) => strcmp($b, $a));
    return $o;
}

function adm_grade_edit(bool $post)
{
    $sid = (int) ($_GET['student'] ?? 0);
    $terms = ge_terms();
    $ytk = (string) ($_GET['yt'] ?? yt());
    if (!isset($terms[$ytk])) {
        $ytk = yt();
    }
    [$y, $t] = array_map('intval', explode('/', $ytk));
    $f = pick_form('grade_edit', ['student' => ['นักเรียน', ['' => '- เลือกนักเรียน -'] + student_options(), $sid], 'yt' => ['ภาคเรียน', $terms, $ytk]]);
    $note = '<p style="width:983px;margin:0 auto 8px;text-align:left" class="gray_12">บันทึก/แก้ไขผลการเรียนของนักเรียนรายบุคคลทุกรายวิชาในภาคเรียนที่เลือก (ย้อนหลังได้) — เว้นช่อง "ผลการเรียน" ไว้ ระบบจะคิดเกรดจากคะแนนรวมให้อัตโนมัติตามเกณฑ์ระดับผลการเรียน</p>';
    if (!$sid || !($s = student_head($sid))) {
        return $f.$note;
    }
    $act = (string) ($_GET['act'] ?? '');
    if ($post && $act === 'save') {
        foreach ((array) ($_POST['g'] ?? []) as $gid => $d) {
            $gid = (int) $gid;
            $row = q1('SELECT G.`id`, C.`type` FROM {grade} G JOIN {course} C ON C.`id`=G.`course_id` WHERE G.`id`=? AND G.`student_id`=? AND C.`year`=? AND C.`term`=?', [$gid, $sid, $y, $t]);
            if (!$row) {
                continue;
            }
            if (!empty($d['del'])) {
                q('DELETE FROM {grade} WHERE `id`=?', [$gid]);
                md_set('tscore', $gid, '', '', null);
                md_set('rec_split', $gid, '', '', null);
                continue;
            }
            $mid = max(0, min(100, (int) round((float) ($d['mid'] ?? 0))));
            $fin = max(0, min(100, (int) round((float) ($d['fin'] ?? 0))));
            $g = trim((string) ($d['grade'] ?? ''));
            if ($g === '' && (int) $row['type'] !== 9 && (($d['mid'] ?? '') !== '' || ($d['fin'] ?? '') !== '')) {
                $g = $mid + $fin < PASS_SCORE ? '0' : grade_of($mid + $fin);
            }
            if ($g !== '' && !in_array($g, GE_GRADES, true)) {
                $g = '';
            }
            q('UPDATE {grade} SET `midterm`=?, `final`=?, `grade`=? WHERE `id`=?', [$mid, $fin, $g, $gid]);
        }
        admin_done('grade_edit', '&student='.$sid.'&yt='.rawurlencode($ytk));
    }
    if ($post && $act === 'add') {
        $cid = (int) ($_POST['course'] ?? 0);
        if (!$cid && trim((string) ($_POST['code'] ?? '')) !== '' && trim((string) ($_POST['name'] ?? '')) !== '') {
            // เพิ่มรายวิชาใหม่ในภาคเรียนนั้น (กรณีบันทึกย้อนหลังที่ยังไม่มีรายวิชาในระบบ)
            $type = isset(COURSE_TYPES[(int) ($_POST['type'] ?? 1)]) ? (int) $_POST['type'] : 1;
            $class = (int) ($_POST['class'] ?? $s['class']);
            $code = mb_substr(trim((string) $_POST['code']), 0, 20);
            $ex = q1('SELECT `id` FROM {course} WHERE `course_code`=? AND `class`=? AND `year`=? AND `term`=?', [$code, $class, $y, $t]);
            if ($ex) {
                $cid = (int) $ex['id'];
            } else {
                q('INSERT INTO {course} (`course_name`,`course_code`,`teacher_id`,`class`,`period`,`credit`,`type`,`year`,`term`) VALUES (?,?,0,?,?,?,?,?,?)',
                    [mb_substr(trim((string) $_POST['name']), 0, 50), $code, $class, $type === 9 ? 20 : 40, min(9.9, max(0, (float) ($_POST['credit'] ?? 1))), $type, $y, $t]);
                $cid = (int) (q1('SELECT MAX(`id`) id FROM {course} WHERE `course_code`=? AND `year`=? AND `term`=?', [$code, $y, $t])['id'] ?? 0);
            }
        }
        if ($cid && q1('SELECT 1 x FROM {course} WHERE `id`=? AND `year`=? AND `term`=?', [$cid, $y, $t]) && !q1('SELECT 1 x FROM {grade} WHERE `student_id`=? AND `course_id`=?', [$sid, $cid])) {
            q('INSERT INTO {grade} (`student_id`,`course_id`,`number`,`room`,`type`,`midterm`,`final`,`grade`) VALUES (?,?,?,?,0,0,0,?)', [$sid, $cid, (int) $s['number'], (int) $s['room'], '']);
        }
        admin_done('grade_edit', '&student='.$sid.'&yt='.rawurlencode($ytk));
    }
    $gs = q('SELECT G.*, C.`course_code`, C.`course_name`, C.`credit`, C.`type` ctype, C.`class` cclass FROM {grade} G JOIN {course} C ON C.`id`=G.`course_id` WHERE G.`student_id`=? AND C.`year`=? AND C.`term`=? ORDER BY C.`type`=9, C.`course_code`', [$sid, $y, $t]);
    $opts = '<option value="">-</option>'.implode('', array_map(fn($g) => '<option>'.h($g).'</option>', GE_GRADES));
    $rows = '';
    foreach ($gs as $i => $g) {
        $gid = (int) $g['id'];
        $sel = str_replace('<option>'.h((string) $g['grade']).'</option>', '<option selected>'.h((string) $g['grade']).'</option>', $opts);
        $num = fn($k, $v) => '<input type="text" name="g['.$gid.']['.$k.']" value="'.h((string) $v).'" class="general radius ge-n" style="width:52px;text-align:center" inputmode="decimal"/>';
        $rows .= zrow([$i + 1, h($g['course_code']), ['<div style="text-align:left">'.h($g['course_name']).'</div>', ''], [h(COURSE_TYPES[(int) $g['ctype']] ?? ''), ' align="center"'], [h((string) $g['credit']), ' align="center"'],
            [$num('mid', $g['midterm']), ' align="center"'], [$num('fin', $g['final']), ' align="center"'], ['<span class="ge-t">'.((int) $g['midterm'] + (int) $g['final']).'</span>', ' align="center"'],
            ['<select name="g['.$gid.'][grade]" class="general radius" style="height:24px">'.$sel.'</select>', ' align="center"'],
            ['<label title="ลบรายวิชานี้ออกจากผลการเรียนของนักเรียน"><input type="checkbox" name="g['.$gid.'][del]" value="1"/> ลบ</label>', ' align="center"']], $i);
    }
    $gpa = gpa_of(array_filter($gs, fn($g) => (int) $g['ctype'] !== 9));
    $all = student_grades($sid);
    $lv = gpax_levels($all);
    $gx = implode(' &nbsp; ', array_map(fn($k, $v) => 'GPAX '.($k !== '' ? LEVEL_SHORT[$k] : '').' <b>'.number_format($v, 2).'</b>', array_keys($lv), $lv));
    $head = '<div style="width:983px;margin:0 auto 8px;text-align:left"><b style="font-size:15px">'.h($s['name']).'</b> รหัส '.h($s['student_id']).' ชั้นปัจจุบัน '.h(class_room_short($s['class'], $s['room']))
        .' — '.h($terms[$ytk]).' &nbsp; GPA ภาคเรียนนี้ <b>'.($gpa === null ? '-' : number_format($gpa, 2)).'</b> &nbsp; '.$gx.'<span style="float:right">'.btn('ดูผลการเรียนทุกภาคเรียน', a_url('grade_person', '&student='.$sid), 'student').'</span></div>';
    $table = '<form method="post" action="'.h(a_url('grade_edit', '&student='.$sid.'&yt='.rawurlencode($ytk).'&act=save')).'">'
        .list_table('ผลการเรียน '.h($terms[$ytk]), ['#', 'รหัสวิชา', 'รายวิชา', 'ประเภท', 'หน่วยกิต', 'ระหว่างภาค', 'ปลายภาค', 'รวม', 'ผลการเรียน', ''], $rows ?: '<tr><td colspan="10" align="center" class="gray_12" style="padding:12px">ยังไม่มีรายวิชาในภาคเรียนนี้ — เพิ่มรายวิชาได้ด้านล่าง</td></tr>', count($gs).' รายวิชา',
            $rows ? '<input type="submit" value="บันทึกผลการเรียน" class="general radius" style="height:26px;font-weight:bold"/>' : '').'</form>'
        .'<script>$(function(){$(".ge-n").bind("keyup change",function(){var tr=$(this).closest("tr");var s=0;tr.find(".ge-n").each(function(){s+=parseFloat(this.value)||0;});tr.find(".ge-t").text(Math.round(s));});});</script>';
    // เพิ่มรายวิชา
    $enrolled = array_map(fn($g) => (int) $g['course_id'], $gs);
    $co = ['' => '- เลือกรายวิชาที่มีในภาคเรียนนี้ -'];
    foreach (q('SELECT `id`, `course_code`, `course_name`, `class` FROM {course} WHERE `year`=? AND `term`=? ORDER BY `class`=?, `course_code`', [$y, $t, (int) $s['class']]) as $c) {
        if (!in_array((int) $c['id'], $enrolled, true)) {
            $co[(int) $c['id']] = $c['course_code'].' '.$c['course_name'].' ('.class_short($c['class']).')';
        }
    }
    $add = '<form method="post" action="'.h(a_url('grade_edit', '&student='.$sid.'&yt='.rawurlencode($ytk).'&act=add')).'" style="width:983px;box-sizing:border-box;margin:10px auto;text-align:left;background:#fff;border:1px solid #ddd;border-radius:4px;padding:10px 14px">'
        .'<b>เพิ่มรายวิชาให้นักเรียน</b><br>'.sel('course', $co, '', 'style="width:520px;height:25px"').' <input type="submit" value="เพิ่ม" class="general radius" style="height:25px"/>'
        .'<div class="gray_12" style="margin:8px 0 4px">หรือ ถ้ายังไม่มีรายวิชาในภาคเรียนนี้ (บันทึกย้อนหลัง) กรอกเพื่อสร้างรายวิชาใหม่:</div>'
        .'รหัสวิชา '.inp('code', '', 90).' ชื่อวิชา '.inp('name', '', 220).' หน่วยกิต '.inp('credit', '1.0', 40).' ประเภท '.sel('type', COURSE_TYPES, 1, 'style="height:22px"')
        .' ระดับชั้น '.sel('class', cats('class'), (int) $s['class'], 'style="height:22px"').' <input type="submit" value="สร้างและเพิ่ม" class="general radius" style="height:25px"/></form>';
    return $f.$note.$head.$table.$add;
}

const GE_GRADES = ['4', '3.5', '3', '2.5', '2', '1.5', '1', '0', 'ร', 'มส', 'มผ', 'ผ', 'ผ่าน', 'ไม่ผ่าน'];
