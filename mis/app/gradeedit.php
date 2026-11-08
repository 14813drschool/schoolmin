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
    $note = '<p style="width:983px;margin:0 auto 8px;text-align:left" class="gray_12">บันทึก/แก้ไขผลการเรียนของนักเรียนรายบุคคลทุกรายวิชาในภาคเรียนที่เลือก (ย้อนหลังได้) — ระบบคิดผลการเรียนให้อัตโนมัติจากคะแนนรวมเหมือนหน้าบันทึกผลการเรียนของครู (รวมต่ำกว่า 50 = 0, กิจกรรม = ผ/มผ) เว้นคะแนนว่างทั้งสองช่อง = ยังไม่มีผลการเรียน</p>';
    if (!$sid || !($s = student_head($sid))) {
        return $f.$note;
    }
    $act = (string) ($_GET['act'] ?? '');
    if ($post && $act === 'save') {
        foreach ((array) ($_POST['g'] ?? []) as $gid => $d) {
            $gid = (int) $gid;
            $row = q1('SELECT G.`id`, G.`midterm`, G.`final`, G.`grade`, C.`type` FROM {grade} G JOIN {course} C ON C.`id`=G.`course_id` WHERE G.`id`=? AND G.`student_id`=? AND C.`year`=? AND C.`term`=?', [$gid, $sid, $y, $t]);
            if (!$row) {
                continue;
            }
            if (!empty($d['del'])) {
                q('DELETE FROM {grade} WHERE `id`=?', [$gid]);
                md_set('tscore', $gid, '', '', null);
                md_set('rec_split', $gid, '', '', null);
                continue;
            }
            $rm = trim((string) ($d['mid'] ?? ''));
            $rf = trim((string) ($d['fin'] ?? ''));
            if (($rm !== '' && !is_numeric($rm)) || ($rf !== '' && !is_numeric($rf))) {
                continue;
            }
            $mid = max(0, min(100, (int) round((float) $rm)));
            $fin = max(0, min(100, (int) round((float) $rf)));
            if ($mid === (int) $row['midterm'] && $fin === (int) $row['final'] && ($rm !== '' || $rf !== '' || (string) $row['grade'] === '')) {
                continue; // คะแนนไม่เปลี่ยน: คงผลการเรียนเดิม (รวม ร / มส / ผลแก้ตัว)
            }
            $g = ge_auto_grade($rm, $rf, (int) $row['type']);
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
    $gs = q('SELECT G.*, C.`course_code`, C.`course_name`, C.`credit`, C.`type` ctype, C.`class` cclass, C.`year`, C.`term` FROM {grade} G JOIN {course} C ON C.`id`=G.`course_id` WHERE G.`student_id`=? AND C.`year`=? AND C.`term`=? ORDER BY C.`type`=9, C.`course_code`', [$sid, $y, $t]);
    $rows = '';
    foreach ($gs as $i => $g) {
        $gid = (int) $g['id'];
        $blank = (string) $g['grade'] === '' && (int) $g['midterm'] === 0 && (int) $g['final'] === 0;
        $finMax = function_exists('d_exam') ? (float) (d_exam($g, 'final')['mark'] ?? score_ratio()[2]) : 20.0;
        $num = fn($k, $v, $mx) => '<input type="text" name="g['.$gid.']['.$k.']" value="'.($blank ? '' : h((string) $v)).'" data-max="'.h((string) $mx).'" class="general radius ge-n" style="width:52px;text-align:center" inputmode="decimal" autocomplete="off"/>';
        $gt = (string) $g['grade'];
        $rows .= zrow([$i + 1, h($g['course_code']), ['<div style="text-align:left">'.h($g['course_name']).'</div>', ''], [h(COURSE_TYPES[(int) $g['ctype']] ?? ''), ' align="center"'], [h((string) $g['credit']), ' align="center"'],
            [$num('mid', $g['midterm'], 100 - $finMax), ' align="center"'], [$num('fin', $g['final'], $finMax), ' align="center"'], ['<span class="ge-t">'.($blank ? '-' : (int) $g['midterm'] + (int) $g['final']).'</span>', ' align="center"'],
            ['<b class="ge-g" data-type="'.(int) $g['ctype'].'" style="font-size:14px;color:'.(in_array($gt, ['0', 'ร', 'มส', 'มผ'], true) ? '#C0392B' : '#1F618D').'">'.($gt === '' ? '-' : h($gt)).'</b>', ' align="center"'],
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
        .'<script>var GE_SCALE='.json_encode(grade_scale()).';'
        .'function geMsg(t,done){if(window.AlertBox&&AlertBox.alert){AlertBox.alert(t,"",{onComplete:done})}else{alert(t);done()}}'
        .'function geGrade(tr){var ins=tr.find(".ge-n"),a=ins.eq(0).val().trim(),b=ins.eq(1).val().trim(),g=tr.find(".ge-g"),t=tr.find(".ge-t");'
        .'if(a===""&&b===""){t.text("-");g.text("-").css("color","#1F618D");return}var s=Math.round((parseFloat(a)||0)+(parseFloat(b)||0));t.text(s);var r;'
        .'if(g.attr("data-type")==="9"){r=s>=50?"ผ":"มผ"}else if(s<50){r="0"}else{r="0";for(var k=0;k<GE_SCALE.length;k++){if(s>=GE_SCALE[k][0]){r=GE_SCALE[k][1];break}}}'
        .'g.text(r).css("color",(r==="0"||r==="มผ")?"#C0392B":"#1F618D")}'
        // วางคะแนนจาก Excel / Google Sheets: กระจายลงช่อง (ระหว่างภาค, ปลายภาค) ตามแถวและคอลัมน์ เริ่มจากช่องที่วาง
        .'document.addEventListener("paste",function(e){var i=e.target;if(!i.classList||!i.classList.contains("ge-n"))return;var tx=(e.clipboardData||window.clipboardData).getData("text");if(!/[\\t\\n]/.test(tx.trim()))return;e.preventDefault();'
        .'var rows=tx.replace(/\\r/g,"").replace(/\\n+$/,"").split("\\n").map(function(r){return r.split("\\t")});var trs=$(i).closest("table").find("tr").filter(function(){return $(this).find(".ge-n").length>0}).get();'
        .'var r0=trs.indexOf($(i).closest("tr")[0]),c0=$(i).closest("tr").find(".ge-n").index(i),bad=[],over=[];'
        .'rows.forEach(function(cells,ri){var tr=trs[r0+ri];if(!tr)return;var ins=$(tr).find(".ge-n");cells.forEach(function(v,ci){var x=ins.get(c0+ci);if(!x)return;v=v.trim();'
        .'if(v!==""&&!/^\\d+(\\.\\d+)?$/.test(v)){bad.push(v);x.value="";return}var mx=parseFloat($(x).attr("data-max"));if(v!==""&&mx>0&&parseFloat(v)>mx){over.push(v);x.value="";return}x.value=v});geGrade($(tr))});'
        .'if(bad.length){geMsg("โปรดระบุคะแนนเป็นตัวเลขเท่านั้น",function(){})}else if(over.length){geMsg("มีคะแนนเกินคะแนนเต็ม "+over.length+" ช่อง ระบบเว้นว่างไว้ให้",function(){})}});'
        .'$(function(){$(".ge-n").bind("keyup change",function(){geGrade($(this).closest("tr"))}).bind("blur",function(){var i=this,v=i.value.trim(),mx=parseFloat($(i).attr("data-max"));'
        .'if(v!==""&&!/^\\d+(\\.\\d+)?$/.test(v)){geMsg("โปรดระบุคะแนนเป็นตัวเลขเท่านั้น",function(){i.value="";geGrade($(i).closest("tr"));i.focus()});return}'
        .'if(v!==""&&mx>0&&parseFloat(v)>mx){geMsg("ไม่อนุญาตให้บันทึกคะแนนมากกว่า "+(Math.round(mx*100)/100)+" คะแนน",function(){i.value="";geGrade($(i).closest("tr"));i.focus()})}})});</script>';
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

/** ผลการเรียนอัตโนมัติจากคะแนน (เหมือนหน้าบันทึกผลการเรียนของครู) */
function ge_auto_grade(string $mid, string $fin, int $type): string
{
    if ($mid === '' && $fin === '') {
        return '';
    }
    $total = (int) round((float) $mid + (float) $fin);
    if ($type === 9) {
        return $total >= PASS_SCORE ? 'ผ' : 'มผ';
    }
    return $total < PASS_SCORE ? '0' : grade_of($total);
}
