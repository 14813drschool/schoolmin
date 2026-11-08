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
            // 3 ช่องเหมือนหน้าครู: คะแนนเก็บ (รายหน่วย) + กลางภาค = midterm, ปลายภาค = final, เก็บแยกไว้ใน rec_split (u|m)
            $ru = trim((string) ($d['unit'] ?? ''));
            $rm = trim((string) ($d['mid'] ?? ''));
            $rf = trim((string) ($d['fin'] ?? ''));
            if (($ru !== '' && !is_numeric($ru)) || ($rm !== '' && !is_numeric($rm)) || ($rf !== '' && !is_numeric($rf))) {
                continue;
            }
            [$ou, $om] = ge_split($gid, $row);
            $of = ge_blank($row) ? '' : (string) (int) $row['final'];
            $nz = fn($v) => $v === '' ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
            if ($nz($ru) === $nz($ou) && $nz($rm) === $nz($om) && $nz($rf) === $nz($of)) {
                continue; // คะแนนไม่เปลี่ยน: คงผลการเรียนเดิม (รวม ร / มส / ผลแก้ตัว)
            }
            if (empty($d['locked'])) {
                md_set('rec_split', $gid, '', '', $ru === '' && $rm === '' ? null : $nz($ru).'|'.$nz($rm));
            }
            $midterm = $ru === '' && $rm === '' ? 0 : max(0, min(100, (int) round((float) $ru + (float) $rm)));
            $fin = $rf === '' ? 0 : max(0, min(100, (int) round((float) $rf)));
            $g = ge_auto_grade($ru, $rm, $rf, (int) $row['type']);
            q('UPDATE {grade} SET `midterm`=?, `final`=?, `grade`=? WHERE `id`=?', [$midterm, $fin, $g, $gid]);
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
    $base = a_url('grade_edit', '&student='.$sid.'&yt='.rawurlencode($ytk));
    $rows = '';
    $maxs = null;
    foreach ($gs as $i => $g) {
        $gid = (int) $g['id'];
        $blank = ge_blank($g);
        [$rwU, $rwM, $rwF] = score_ratio();
        $finMax = function_exists('d_exam') ? (float) (d_exam($g, 'final')['mark'] ?? $rwF) : (float) $rwF;
        $midMax = function_exists('d_exam') ? (float) (d_exam($g, 'mid')['mark'] ?? $rwM) : (float) $rwM;
        $unitMax = max(0, 100 - $finMax - $midMax);
        // รายวิชาที่ออกแบบหน่วยและครูบันทึกคะแนนรายหน่วยแล้ว: คะแนนเก็บคิดจากหน้าครู (แก้ไขที่หน้าครู)
        $ts = function_exists('task_scores') ? (task_scores([$gid])[$gid] ?? []) : [];
        $lockU = $ts && function_exists('d_units') && d_units($g);
        [$uOld, $mOld] = ge_split($gid, $g);
        if ($lockU) {
            $uOld = (string) array_sum(array_map('floatval', array_filter($ts, 'is_numeric')));
        }
        $maxs ??= [$unitMax, $midMax, $finMax];
        $num = fn($k, $v, $mx, $ro = false) => '<input type="text" name="g['.$gid.']['.$k.']" value="'.h((string) $v).'" data-max="'.h((string) $mx).'" class="rec-in ge-n" inputmode="decimal" autocomplete="off"'
            .($ro ? ' readonly style="background:#f2f2f2" title="คะแนนเก็บคิดจากคะแนนรายหน่วยที่ครูบันทึก แก้ไขได้ที่หน้าบันทึกผลการเรียนของครู"' : '').'/>';
        $gt = (string) $g['grade'];
        $tot = (int) $g['midterm'] + (int) $g['final'];
        $bad = in_array($gt, ['0', 'ร', 'มส', 'มผ'], true);
        $rows .= '<tr><td class="c">'.($i + 1).'</td><td class="c">'.h($g['course_code']).'</td><td>'.h($g['course_name']).'</td><td class="c">'.h(COURSE_TYPES[(int) $g['ctype']] ?? '').'</td><td class="c">'.h((string) $g['credit']).'</td>'
            .'<td class="c">'.$num('unit', $uOld, $unitMax, $lockU).($lockU ? '<input type="hidden" name="g['.$gid.'][locked]" value="1"/>' : '').'</td><td class="c">'.$num('mid', $mOld, $midMax).'</td><td class="c">'.$num('fin', $blank ? '' : $g['final'], $finMax).'</td>'
            .'<td class="c sum"><b class="ge-t '.($tot < PASS_SCORE ? 'bad' : 'ok').'">'.($blank ? '' : $tot).'</b></td>'
            .'<td class="c sum"><b class="ge-g '.($bad ? 'bad' : 'ok').'" data-type="'.(int) $g['ctype'].'">'.h($gt).'</b></td>'
            .'<td class="c fix"><label title="ลบรายวิชานี้ออกจากผลการเรียนของนักเรียน" style="font:12px Tahoma;cursor:pointer"><input type="checkbox" name="g['.$gid.'][del]" value="1"/> ลบ</label></td></tr>';
    }
    $gpa = gpa_of(array_filter($gs, fn($g) => (int) $g['ctype'] !== 9));
    $lv = gpax_levels(student_grades($sid));
    $gx = implode('', array_map(fn($k, $v) => '<div class="t4"><span style="width:250px">ผลการเรียนเฉลี่ยสะสม (GPAX)'.($k !== '' ? ' '.LEVEL_SHORT[$k] : '').'</span><span style="width:70px">'.number_format($v, 2).'</span></div>', array_keys($lv), $lv));
    // แถบหัว: เลือกนักเรียน / ภาคเรียน (เหมือนแถบเลือกรายวิชาในหน้าบันทึกผลการเรียนของครู)
    $stOpt = '';
    foreach (student_options() as $k => $v) {
        $stOpt .= '<option value="'.h(a_url('grade_edit', '&student='.$k.'&yt='.rawurlencode($ytk))).'"'.((int) $k === $sid ? ' selected' : '').'>'.h($v).'</option>';
    }
    $ytOpt = '';
    foreach ($terms as $k => $v) {
        $ytOpt .= '<option value="'.h(a_url('grade_edit', '&student='.$sid.'&yt='.rawurlencode($k))).'"'.($k === $ytk ? ' selected' : '').'>'.h($v).'</option>';
    }
    [$r1, $r2, $r3] = score_ratio();
    $info = '<div class="dz-info"><div style="display:flex"><img src="'.h(avatar_url($sid)).'" alt=""/><div><div class="t1">นักเรียน : '.h($s['name']).'</div><div class="t2">ระดับชั้น'.h(cat('class', $s['class'])).' ('.h(class_room_short($s['class'], $s['room'])).' เลขที่ '.(int) $s['number'].')</div>'
        .'<div class="t3">รหัสประจำตัวนักเรียน '.h($s['student_id']).'</div><div class="t4"><span style="width:250px">ผลการเรียนเฉลี่ยภาคเรียนนี้ (GPA)</span><span style="width:70px">'.($gpa === null ? '-' : number_format($gpa, 2)).'</span></div>'.$gx.'</div></div>'
        .'<div style="text-align:right"><div class="yt">ภาคเรียนที่ '.$t.' ปีการศึกษา '.$y.'</div><div class="ratio">อัตราส่วนคะแนนรายวิชา '.$r1.' : '.$r2.' : '.$r3.'</div>'
        .'<a class="dz-addunit" href="'.h(a_url('grade_person', '&student='.$sid)).'" style="margin-top:14px"><b><i class="fa fa-list-alt"></i>ดูผลการเรียนทุกภาคเรียน</b></a></div></div>';
    $bar = '<div class="rec-bar"><h2><i class="fa fa-list-ul"></i>บันทึกผลการเรียนรายบุคคล</h2><span><select onchange="location.href=this.value" style="height:24px;max-width:330px">'.$stOpt.'</select> '
        .'<select onchange="location.href=this.value" style="height:24px">'.$ytOpt.'</select></span></div>';
    $table = '<form name="ge" method="post" action="'.h($base.'&act=save').'"><div class="rec-tabs"><span class="rec-tab on">ผลการเรียนทุกรายวิชา</span></div><div class="rec-line"></div>'
        .'<table class="rec"><tr><th rowspan="3" style="width:40px">ที่</th><th rowspan="3" style="width:80px">รหัสวิชา</th><th rowspan="3">รายวิชา</th><th colspan="8" class="big">คะแนนสอบและผลการเรียน ประจำภาคเรียนที่ '.$t.' ปีการศึกษา '.$y.'</th></tr>'
        .'<tr><th rowspan="2" style="width:62px">ประเภท</th><th rowspan="2" style="width:58px">หน่วยกิต</th><th>รายหน่วย</th><th>กลางภาค</th><th>ปลายภาค</th><th colspan="2">คะแนนรวมและสรุปผล</th><th rowspan="2" style="width:56px">ลบ</th></tr>'
        .'<tr><th class="b">คะแนนเก็บ</th><th class="b">คะแนน</th><th class="b">คะแนน</th><th class="b">รวม</th><th class="b">เกรด</th></tr>'
        .'<tr class="max"><td colspan="5" style="background:#FDE5DA"></td><td>'.h((string) ($maxs[0] ?? $r1)).'</td><td>'.h((string) ($maxs[1] ?? $r2)).'</td><td>'.h((string) ($maxs[2] ?? $r3)).'</td><td>100</td><td>4</td><td></td></tr>'
        .($rows ?: '<tr><td colspan="11" class="c" style="padding:14px;color:#888">ยังไม่มีรายวิชาในภาคเรียนนี้ — เพิ่มรายวิชาได้ด้านล่าง</td></tr>').'</table>'
        .($rows ? '<div class="rec-save"><a href="javascript:void(0)" class="sv" onclick="geSubmit()">บันทึกผลการเรียน</a><a href="'.h($base).'" class="cl">ยกเลิก</a></div>' : '').'</form>'
        .'<script>var GE_SCALE='.json_encode(grade_scale()).';'
        .'function geMsg(t,done){if(window.AlertBox&&AlertBox.alert){AlertBox.alert(t,"",{onComplete:done})}else{alert(t);done()}}'
        .'function geBadVal(i){var v=i.value.trim(),mx=parseFloat($(i).attr("data-max"));return v!==""&&(!/^\\d+(\\.\\d+)?$/.test(v)||(mx>0&&parseFloat(v)>mx))}'
        .'function geColor(i){var $i=$(i);$i.removeClass("ok bad err");if(i.value.trim()==="")return;$i.addClass(geBadVal(i)?"err":"ok")}'
        .'function geGrade(tr){var ins=tr.find(".ge-n"),v=ins.map(function(){return this.value.trim()}).get(),n=v.filter(function(x){return x!==""}).length,g=tr.find(".ge-g"),t=tr.find(".ge-t");ins.each(function(){geColor(this)});'
        .'if(!n){t.text("");g.text("");return}var s=0;v.forEach(function(x){s+=parseFloat(x)||0});s=Math.round(s);t.text(s).removeClass("ok bad").addClass(s<50?"bad":"ok");var r;'
        .'if(g.attr("data-type")==="9"){r=s>=50?"ผ":"มผ"}else if(n<v.length){r="ร"}else if(s<50){r="0"}else{r="0";for(var k=0;k<GE_SCALE.length;k++){if(s>=GE_SCALE[k][0]){r=GE_SCALE[k][1];break}}}'
        .'g.text(r).removeClass("ok bad").addClass(r==="0"||r==="มผ"||r==="ร"?"bad":"ok")}'
        .'function geSubmit(){var bad=$(".ge-n").filter(function(){return geBadVal(this)});if(bad.length){var i=bad.get(0),v=i.value.trim();geMsg(/^\\d+(\\.\\d+)?$/.test(v)?"ไม่อนุญาตให้บันทึกคะแนนมากกว่า "+(Math.round(parseFloat($(i).attr("data-max"))*100)/100)+" คะแนน":"โปรดระบุคะแนนเป็นตัวเลขเท่านั้น",function(){i.focus()});return}document.forms.ge.submit()}'
        // วางคะแนนจาก Excel / Google Sheets: กระจายลงช่อง (ระหว่างภาค, ปลายภาค) ตามแถวและคอลัมน์ เริ่มจากช่องที่วาง
        .'document.addEventListener("paste",function(e){var i=e.target;if(!i.classList||!i.classList.contains("ge-n"))return;var tx=(e.clipboardData||window.clipboardData).getData("text");if(!/[\\t\\n]/.test(tx.trim()))return;e.preventDefault();'
        .'var rows=tx.replace(/\\r/g,"").replace(/\\n+$/,"").split("\\n").map(function(r){return r.split("\\t")});var trs=$(i).closest("table").find("tr").filter(function(){return $(this).find(".ge-n").length>0}).get();'
        .'var r0=trs.indexOf($(i).closest("tr")[0]),c0=$(i).closest("tr").find(".ge-n").index(i),bad=[],over=[];'
        .'rows.forEach(function(cells,ri){var tr=trs[r0+ri];if(!tr)return;var ins=$(tr).find(".ge-n");cells.forEach(function(v,ci){var x=ins.get(c0+ci);if(!x||x.readOnly)return;v=v.trim();'
        .'if(v!==""&&!/^\\d+(\\.\\d+)?$/.test(v)){bad.push(v);x.value="";return}var mx=parseFloat($(x).attr("data-max"));if(v!==""&&mx>0&&parseFloat(v)>mx){over.push(v);x.value="";return}x.value=v});geGrade($(tr))});'
        .'if(bad.length){geMsg("โปรดระบุคะแนนเป็นตัวเลขเท่านั้น",function(){})}else if(over.length){geMsg("มีคะแนนเกินคะแนนเต็ม "+over.length+" ช่อง ระบบเว้นว่างไว้ให้",function(){})}});'
        .'$(function(){$(".ge-n").each(function(){geColor(this)}).bind("keyup change",function(){geGrade($(this).closest("tr"))}).bind("blur",function(){var i=this,v=i.value.trim(),mx=parseFloat($(i).attr("data-max"));'
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
    $add = '<form method="post" action="'.h($base.'&act=add').'" class="dz-info" style="display:block;margin-top:14px;font:13px Tahoma">'
        .'<div class="t3" style="margin-bottom:6px"><i class="fa fa-plus-circle" style="color:#27AE60"></i> เพิ่มรายวิชาให้นักเรียน</div>'.sel('course', $co, '', 'style="width:520px;height:25px"').' <input type="submit" value="เพิ่ม" class="general radius" style="height:25px"/>'
        .'<div class="gray_12" style="margin:10px 0 4px">หรือ ถ้ายังไม่มีรายวิชาในภาคเรียนนี้ (บันทึกย้อนหลัง) กรอกเพื่อสร้างรายวิชาใหม่:</div>'
        .'รหัสวิชา '.inp('code', '', 90).' ชื่อวิชา '.inp('name', '', 200).' หน่วยกิต '.inp('credit', '1.0', 40).' ประเภท '.sel('type', COURSE_TYPES, 1, 'style="height:22px"')
        .' ระดับชั้น '.sel('class', cats('class'), (int) $s['class'], 'style="height:22px"').' <input type="submit" value="สร้างและเพิ่ม" class="general radius" style="height:25px"/></form>';
    return d_css().rec_css().'<div class="dz">'.$bar.$info.$table.$add.'<p class="gray_12" style="margin-top:8px">ระบบคิดผลการเรียนให้อัตโนมัติจากคะแนนรวมเหมือนหน้าบันทึกผลการเรียนของครู (ขาดช่องใดช่องหนึ่ง = ร, รวมต่ำกว่า 50 = 0, กิจกรรม = ผ/มผ) วางคะแนนจาก Excel ได้ 3 คอลัมน์ (คะแนนเก็บ, กลางภาค, ปลายภาค) — รายวิชาที่ไม่ได้แก้คะแนนจะคงผลการเรียนเดิม (เช่น ร, มส, ผลสอบแก้ตัว)</p></div>';
}

/** ผลการเรียนอัตโนมัติจากคะแนน (เหมือนหน้าบันทึกผลการเรียนของครู: ขาดช่องใดช่องหนึ่ง = ร, รวมต่ำกว่า 50 = 0) */
function ge_auto_grade(string $unit, string $mid, string $fin, int $type): string
{
    $filled = array_filter([$unit, $mid, $fin], fn($v) => $v !== '');
    if (!$filled) {
        return '';
    }
    $total = (int) round((float) $unit + (float) $mid + (float) $fin);
    if ($type === 9) {
        return $total >= PASS_SCORE ? 'ผ' : 'มผ';
    }
    if (count($filled) < 3) {
        return 'ร';
    }
    return $total < PASS_SCORE ? '0' : grade_of($total);
}

/** ยังไม่มีผลการเรียน (ไม่เคยบันทึก) */
function ge_blank(array $g): bool
{
    return (string) $g['grade'] === '' && (int) $g['midterm'] === 0 && (int) $g['final'] === 0;
}

/** คะแนนเก็บ / กลางภาค ที่บันทึกแยกไว้ (เหมือนหน้าครู ถ้าไม่มีข้อมูลแยก ให้คะแนนระหว่างภาคทั้งหมดเป็นคะแนนเก็บ) */
function ge_split(int $gid, array $g): array
{
    [$u, $m] = array_pad(explode('|', (string) md_get('rec_split', $gid)), 2, '');
    if ($u === '' && $m === '') {
        return ge_blank($g) ? ['', ''] : [(string) (int) $g['midterm'], ''];
    }
    return [$u, $m];
}
