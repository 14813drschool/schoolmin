<?php
/**
 * mis/app/design.php
 * ออกแบบการวัดและประเมินผล (ตามระบบเดิม) + บันทึกผลการเรียนแยกแท็บรายหน่วย
 *  - อัตราส่วนคะแนนรายวิชา (ระหว่างภาค : กลางภาค : ปลายภาค) ตั้งค่าระบบ = 60 : 20 : 20
 *  - หน่วยการเรียนรู้ / ภาระงาน-ชิ้นงาน-กิจกรรม / คะแนนกลางภาค-ปลายภาค พร้อมตัวชี้วัด สาระการเรียนรู้ และน้ำหนักคะแนน
 *    ใช้ร่วมกันทุกห้องของรายวิชาเดียวกันในภาคเรียนเดียวกัน (คีย์ = ปี/ภาค|รหัสวิชา)
 *  - ผลการเรียนคำนวณอัตโนมัติ: ค้างงาน (ช่องคะแนนว่าง) = ร, รวมไม่ถึง 50 = 0, เวลาเรียนไม่ถึง 80% = มส
 */
defined('MIS') or exit;

/** อัตราส่วนคะแนนรายวิชาของระบบ [ระหว่างภาค, กลางภาค, ปลายภาค] */
function score_ratio(): array
{
    $r = array_map('intval', explode(':', (string) cfg('score_ratio', '60:20:20')));
    return count($r) === 3 && array_sum($r) === 100 ? $r : [60, 20, 20];
}

const PASS_SCORE = 50;

/** สาระการเรียนรู้ตามกลุ่มสาระ (อักษรนำรหัสวิชา) — แก้ไขได้ที่ บริหารงานวิชาการ > ตัวชี้วัดและสาระการเรียนรู้ */
const STRANDS_DEFAULT = [
    'ท' => ['สาระที่ 1 การอ่าน', 'สาระที่ 2 การเขียน', 'สาระที่ 3 การฟัง การดู และการพูด', 'สาระที่ 4 หลักการใช้ภาษาไทย', 'สาระที่ 5 วรรณคดีและวรรณกรรม'],
    'ค' => ['สาระที่ 1 จำนวนและพีชคณิต', 'สาระที่ 2 การวัดและเรขาคณิต', 'สาระที่ 3 สถิติและความน่าจะเป็น'],
    'ว' => ['สาระที่ 1 วิทยาศาสตร์ชีวภาพ', 'สาระที่ 2 วิทยาศาสตร์กายภาพ', 'สาระที่ 3 วิทยาศาสตร์โลกและอวกาศ', 'สาระที่ 4 เทคโนโลยี'],
    'ส' => ['สาระที่ 1 ศาสนา ศีลธรรม จริยธรรม', 'สาระที่ 2 หน้าที่พลเมือง วัฒนธรรม และการดำเนินชีวิตในสังคม', 'สาระที่ 3 เศรษฐศาสตร์', 'สาระที่ 4 ประวัติศาสตร์', 'สาระที่ 5 ภูมิศาสตร์'],
    'พ' => ['สาระที่ 1 การเจริญเติบโตและพัฒนาการของมนุษย์', 'สาระที่ 2 ชีวิตและครอบครัว', 'สาระที่ 3 การเคลื่อนไหว การออกกำลังกาย การเล่นเกม กีฬาไทย และกีฬาสากล', 'สาระที่ 4 การสร้างเสริมสุขภาพ สมรรถภาพและการป้องกันโรค', 'สาระที่ 5 ความปลอดภัยในชีวิต'],
    'ศ' => ['สาระที่ 1 ทัศนศิลป์', 'สาระที่ 2 ดนตรี', 'สาระที่ 3 นาฏศิลป์'],
    'ง' => ['สาระที่ 1 การดำรงชีวิตและครอบครัว', 'สาระที่ 2 การอาชีพ'],
    'อ' => ['สาระที่ 1 ภาษาเพื่อการสื่อสาร', 'สาระที่ 2 ภาษาและวัฒนธรรม', 'สาระที่ 3 ภาษากับความสัมพันธ์กับกลุ่มสาระการเรียนรู้อื่น', 'สาระที่ 4 ภาษากับความสัมพันธ์กับชุมชนและโลก'],
];

/** ตัวชี้วัดตั้งต้น (สังคมศึกษา ม.1 ตามระบบเดิม) */
function indicator_seed(): array
{
    $o = [];
    foreach (['1.1' => 11, '1.2' => 5, '2.1' => 4, '2.2' => 3, '3.1' => 3, '3.2' => 4, '4.1' => 3, '4.2' => 2, '4.3' => 3, '5.1' => 3, '5.2' => 4] as $s => $n) {
        for ($i = 1; $i <= $n; $i++) {
            $o[] = 'ส '.$s.' ม.1/'.$i;
        }
    }
    return ['ส|ม.1' => $o];
}

/* ---------------------------------------------------------------- ข้อมูลการออกแบบ */

function dkey(array $c): string
{
    return (int) $c['year'].'/'.(int) $c['term'].'|'.$c['course_code'];
}

function d_prefix(array $c): string
{
    return mb_substr((string) $c['course_code'], 0, 1);
}

function d_level(array $c): string
{
    return preg_replace('#/.*$#', '', class_short((int) $c['class']));
}

function d_strands(array $c): array
{
    $p = d_prefix($c);
    $v = md_get('strand', $p);
    $list = $v !== null ? array_values(array_filter(array_map('trim', explode("\n", $v)))) : (STRANDS_DEFAULT[$p] ?? []);
    $o = [];
    foreach ($list as $i => $s) {
        $o[(string) ($i + 1)] = $s;
    }
    return $o;
}

function d_indicators(array $c): array
{
    $k = d_prefix($c).'|'.d_level($c);
    $v = md_get('indicator', $k);
    $list = $v !== null ? array_values(array_filter(array_map('trim', explode("\n", $v)))) : (indicator_seed()[$k] ?? []);
    return array_combine($list, $list) ?: [];
}

/** หน่วยการเรียนรู้ [no => [title, mark, tasks => [no => [...]]]] */
function d_units(array $c): array
{
    $key = dkey($c);
    $u = [];
    foreach (md_rows('dunit', $key) as $r) {
        $u[(int) $r['r2']] = (json_decode((string) $r['value'], true) ?: []) + ['title' => '', 'mark' => 0, 'tasks' => []];
    }
    foreach (md_rows('dtask', $key) as $r) {
        [$un, $tn] = array_map('intval', explode('.', (string) $r['r2']) + [1 => 0]);
        if (isset($u[$un])) {
            $u[$un]['tasks'][$tn] = (json_decode((string) $r['value'], true) ?: []) + ['title' => '', 'mark' => 0, 'pass' => '', 'half' => 'before', 'pts' => [], 'strands' => []];
        }
    }
    ksort($u);
    foreach ($u as &$x) {
        ksort($x['tasks']);
    }
    return $u;
}

function d_exam(array $c, string $which): array
{
    [, $mid, $fin] = score_ratio();
    $d = json_decode((string) md_get('dexam', dkey($c), $which), true) ?: [];
    return $d + ['mark' => $which === 'mid' ? $mid : $fin, 'pass' => '', 'pts' => [], 'strands' => []];
}

/* ---------------------------------------------------------------- ส่วนประกอบหน้า */

function d_sel(string $name, array $options, $value, string $attr = ''): string
{
    $o = '<select name="'.h($name).'" '.$attr.'>';
    foreach ($options as $k => $v) {
        $o .= '<option value="'.h((string) $k).'"'.((string) $k === (string) $value ? ' selected' : '').'>'.h($v).'</option>';
    }
    return $o.'</select>';
}

function d_head_buttons(array $btns): string
{
    $o = '';
    foreach ($btns as [$id, $href, $title]) {
        $o .= "<div style='float:left;margin:0px 2px'> <a href=\"".$href."\" onMouseOver=\"MM_swapImage('icon_".$id."','','../icon_tools/icon_".$id.".png',1);\" onMouseOut=\"MM_swapImgRestore();\"><img id='icon_".$id."' name='icon_".$id."' src='../icon_tools/hicon_".$id.".png' border='0' title='".h($title)."' align='top'/></a> </div>";
    }
    return $o;
}

function d_with_buttons(array $page, string $buttons): array
{
    $page['__CONTENT__'] = preg_replace('/<DIV style="float:right">/', '<DIV style="float:right">'.str_replace('$', '\\$', $buttons), $page['__CONTENT__'], 1);
    return $page;
}

/** กรอบข้อมูลครู/รายวิชา */
function d_info(array $c, string $level, string $right): string
{
    $teacher = personnel_options()[$c['teacher_id']] ?? ($c['teacher'] ?? '');
    return '<div class="dz-info"><div style="display:flex"><img src="'.h(avatar_url((int) $c['teacher_id'])).'" alt=""/><div><div class="t1">ครูผู้รับผิดชอบ : '.h($teacher).'</div><div class="t2">'.h($level).'</div>'
        .'<div class="t3">รายวิชา '.h($c['course_code'].' '.$c['course_name']).' (รายวิชา'.h(COURSE_TYPES[(int) $c['type']] ?? '').')</div>'
        .'<div class="t4"><span style="width:200px">จำนวนหน่วยกิตต่อภาค</span><span style="width:70px">'.h($c['credit']).'</span>หน่วยกิต</div><div class="t4"><span style="width:200px">จำนวนชั่วโมงเรียนต่อภาค</span><span style="width:70px">'.h($c['period']).'</span>ชั่วโมง</div></div></div>'
        .'<div style="text-align:right"><div class="yt">ภาคเรียนที่ '.(int) $c['term'].' ปีการศึกษา '.(int) $c['year'].'</div>'.$right.'</div></div>';
}

function d_css(): string
{
    return '<style>.dz{width:983px;margin:0 auto;text-align:left;font-family:Tahoma}'
        .'.dz-info{border:1px dashed #33cc99!important;border-radius:4px;background:#fff;padding:14px 16px;display:flex;justify-content:space-between;margin-bottom:14px}'
        .'.dz-info img{width:90px;height:100px;object-fit:cover;border:1px solid #ddd;padding:3px;margin-right:16px;background:#fff}.dz-info .t1{font-family:thaisansbold;font-size:28px;color:#2B7BB9}.dz-info .t2{font-family:thaisansbold;font-size:21px;color:#27AE60}'
        .'.dz-info .t3{font-family:thaisansbold;font-size:21px;color:#111}.dz-info .t4{font-family:thaisansbold;font-size:19px;color:#333}.dz-info .t4 span{display:inline-block}.dz-info .yt{font-family:thaisansbold;font-size:32px;color:#F7A21B}'
        .'.dz-info .ratio{font-family:thaisansbold;font-size:24px;color:#111;margin-top:6px}'
        .'.dz-addunit{display:inline-block;margin-top:12px;background:#0B5CAD;color:#fff!important;font-family:thaisansbold;font-size:24px;padding:6px 16px;border-radius:3px;text-decoration:none;border:1px solid #084a8c}.dz-addunit:hover{background:#0a4f95}'
        .'.dz-h{display:flex;justify-content:space-between;align-items:center;margin:4px 0 8px}.dz-h h2{font-family:thaisansbold;font-weight:normal;font-size:30px;color:#4A90C2;margin:0}.dz-h h2 i{margin-right:8px}'
        .'.dz-h select{height:26px;padding:0 4px;border:1px solid #ccc;border-radius:3px;background:#f7f7f7}'
        .'table.dz-t{border-collapse:collapse;width:100%;font:13px Tahoma}table.dz-t th{background:#DDF0FA;border:1px solid #9CCFD6;font-weight:normal;color:#111;padding:4px;text-align:center!important;vertical-align:middle}'
        .'table.dz-t th.g{background:#EFEFEF}table.dz-t td{border:1px solid #9CCFD6;padding:5px 6px;height:30px;color:#111;vertical-align:middle}table.dz-t td.c{text-align:center}table.dz-t td.r{text-align:right}'
        .'table.dz-t td.lb{background:#F2F8FC}table.dz-t tr.sum td{background:#DDF0FA}table.dz-t a{color:#1F6FB5;text-decoration:none}table.dz-t a:hover{text-decoration:underline}'
        .'.dz-plus{float:right;display:inline-block;width:16px;height:16px;line-height:15px;text-align:center;background:#5ED39B;color:#fff!important;border-radius:3px;font-weight:bold;font-size:14px;text-decoration:none!important}'
        .'.dz-edit{color:#2EBD59!important;font-size:18px}.dz-red{color:#E00!important}'
        .'.dz-print{float:right;margin-top:10px}'
        .'.dz-ov{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;z-index:9999;overflow:auto}.dz-ov.on{display:block}'
        .'.dz-m{background:#fff;margin:40px auto;border:6px solid #f2f2f2;outline:1px solid #ccc;border-radius:4px;box-shadow:0 4px 20px rgba(0,0,0,.3);text-align:left}'
        .'.dz-m .hd{background:#F7F9FB;border-bottom:1px solid #ddd;padding:8px 10px;font-family:thaisansbold;font-size:21px;color:#333}.dz-m .hd i{color:#F7941D;margin-right:8px}.dz-m .hd .x{float:right;cursor:pointer;color:#999;font-size:18px}'
        .'.dz-m .bd{padding:10px 16px 14px}.dz-m .note{display:flex;gap:10px;align-items:center;border-bottom:1px solid #eee;padding:6px 0 8px;margin-bottom:8px;font-size:12px;color:#222}.dz-m .note i{color:#2BC3C9;font-size:22px}'
        .'.dz-m label{display:block;font-family:thaisansbold;font-size:18px;color:#222;margin:8px 0 2px}.dz-m .in{height:28px;border:1px solid #e3b7a6;border-left:3px solid #c0392b;background:#FFF8F2;padding:0 6px;font:14px Tahoma;box-sizing:border-box}'
        .'.dz-m textarea.in{height:68px;width:100%;padding:4px 6px;background:#f2f2f2;border:1px solid #ccc;border-left:3px solid #555}'
        .'.dz-m select.pt{width:220px;height:28px;margin:3px 0;border:1px solid #ccc;border-radius:2px;font:13px Tahoma}.dz-m select.st{width:316px;height:28px;margin:3px 0;border:1px solid #ccc;border-radius:2px;font:13px Tahoma}'
        .'.dz-m input.w{width:62px;height:28px;margin:3px 0 3px 8px;border:1px solid #ccc;border-left:2px solid #555;background:#f2f2f2;text-align:center;box-sizing:border-box}'
        .'.dz-m .ft{background:#F5F5F5;border-top:1px solid #ddd;padding:8px 12px;text-align:right}.dz-m .ft button{font:14px Tahoma;color:#fff;border:0;border-radius:3px;padding:5px 12px;cursor:pointer;margin-left:4px}'
        .'.dz-m .ft .s{background:#3EA2E8}.dz-m .ft .c{background:#F7B331}.dz-m .ft .d{background:#E74C3C;float:left}.dz-m .half{background:#eee;border-radius:3px;padding:6px 12px;display:inline-block;margin-top:22px}</style>';
}

/** ช่องเลือกตัวชี้วัด 10 ช่อง + สาระ 5 ช่อง */
function d_pts_fields(array $c, array $d): string
{
    $ind = ['' => 'กรุณาเลือกตัวชี้วัด'] + d_indicators($c);
    $str = ['' => 'กรุณาเลือกสาระการเรียนรู้'] + d_strands($c);
    $l = '<div><label style="display:flex;justify-content:space-between;width:290px">มาตรฐาน/ตัวชี้วัด <span>นน.คะแนน</span></label>';
    for ($i = 0; $i < 10; $i++) {
        [$k, $w] = $d['pts'][$i] ?? ['', ''];
        $o = $ind;
        if ($k !== '' && !isset($o[$k])) {
            $o[$k] = $k;
        }
        // กลุ่มสาระ/ชั้นที่ยังไม่มีรายการตัวชี้วัด: พิมพ์รหัสตัวชี้วัดเอง
        $l .= '<div>'.(count($ind) > 1 ? d_sel('pt[]', $o, $k, 'class="pt"') : '<input class="pt" name="pt[]" value="'.h($k).'" placeholder="รหัสตัวชี้วัด เช่น '.h(d_prefix($c)).' 1.1 '.h(d_level($c)).'/1" style="box-sizing:border-box;padding:0 6px"/>')
            .'<input class="w" name="ptw[]" value="'.h($w).'"/></div>';
    }
    $r = '<div><label style="display:flex;justify-content:space-between;width:390px">สาระการเรียนรู้ <span>นน.คะแนน</span></label>';
    for ($i = 0; $i < 5; $i++) {
        [$k, $w] = $d['strands'][$i] ?? ['', ''];
        $r .= '<div>'.d_sel('st[]', $str, (string) $k, 'class="st"').'<input class="w" name="stw[]" value="'.h($w).'"/></div>';
    }
    return '<div style="display:flex;gap:40px;margin-top:4px">'.$l.'</div>'.$r.'</div></div>';
}

function d_post_pts(): array
{
    $pts = [];
    foreach ((array) ($_POST['pt'] ?? []) as $i => $k) {
        if ((string) $k !== '') {
            $pts[] = [mb_substr((string) $k, 0, 40), (string) (float) ($_POST['ptw'][$i] ?? 0)];
        }
    }
    $st = [];
    foreach ((array) ($_POST['st'] ?? []) as $i => $k) {
        if ((string) $k !== '') {
            $st[] = [(string) (int) $k, (string) (float) ($_POST['stw'][$i] ?? 0)];
        }
    }
    return [$pts, $st];
}

function d_modal(string $id, string $title, int $width, string $action, string $body, string $note = '', string $del = ''): string
{
    return '<div class="dz-ov" id="'.$id.'"><div class="dz-m" style="width:'.$width.'px"><form method="post" action="'.h($action).'">'
        .'<div class="hd"><i class="fa fa-list-ul"></i>'.h($title).'<span class="x" onclick="dzClose(\''.$id.'\')">✕</span></div><div class="bd">'
        .($note !== '' ? '<div class="note"><i class="fa fa-commenting"></i><span>'.h($note).'</span></div>' : '').$body.'</div>'
        .'<div class="ft">'.($del !== '' ? '<button type="submit" name="del" value="1" class="d" onclick="return confirm(\'ยืนยันการลบ '.h($del).'?\')">✕ ลบ</button>' : '')
        .'<button type="submit" class="s">✎ บันทึก</button><button type="reset" class="c">✕ เคลียร์</button></div></form></div></div>';
}

/* ---------------------------------------------------------------- หน้าออกแบบการวัดและประเมินผล */

function rows_task_design_measurement_Design()
{
    return rows_task_design_measurement_DesignMeasurement();
}

function rows_task_design_measurement_DesignMeasurement()
{
    $c = att_course() ?? redirect('?module=task&option=design_measurement');
    $cid = (int) $c['id'];
    $base = '?module=task&option=design_measurement&SubjectNum='.$cid;
    [$rw, $rm, $rf] = score_ratio();
    $units = d_units($c);
    $mid = d_exam($c, 'mid');
    $fin = d_exam($c, 'final');
    $strands = d_strands($c);
    $rooms = array_keys(course_rooms([$c])[$cid] ?? []);
    $level = 'ระดับชั้น '.(implode(', ', array_map(fn($r) => class_room_short($c['class'], $r), $rooms)) ?: class_short((int) $c['class']));
    // รายวิชาของครูสำหรับเลือก
    $opts = '';
    foreach (my_courses() as $x) {
        $opts .= '<option value="?module=task&option=design_measurement&func=DesignMeasurement&SubjectNum='.(int) $x['id'].'"'.((int) $x['id'] === $cid ? ' selected' : '').'>'.h($x['course_code'].' - '.$x['course_name']).'</option>';
    }
    $ptCell = function (array $d) use ($strands): array {
        $a = $b = $s = $w = [];
        foreach ($d['pts'] as [$k, $v]) {
            $a[] = h($k);
            $b[] = h((string) (float) $v);
        }
        foreach ($d['strands'] as [$k, $v]) {
            $s[] = h((string) $k);
            $w[] = h((string) (float) $v);
        }
        return ['<td class="c">'.implode('<br>', $a).'</td><td class="c">'.implode('<br>', $b).'</td>', '<td class="c">'.implode('<br>', $s).'</td><td class="c">'.implode('<br>', $w).'</td>'];
    };
    $rows = '';
    $modals = '';
    $sumUnit = 0.0;
    $sumTask = 0.0;
    $sumPt = 0.0;
    $sumSt = 0.0;
    foreach ($units as $un => $u) {
        $tasks = $u['tasks'];
        $tsum = array_sum(array_map(fn($t) => (float) $t['mark'], $tasks));
        $sumUnit += (float) $u['mark'];
        $span = max(1, count($tasks));
        $head = '<td class="c" rowspan="'.$span.'">'.$un.'</td><td rowspan="'.$span.'"><a href="javascript:void(0)" onclick="dzOpen(\'mu'.$un.'\')">'.h($u['title']).'</a>'
            .'<a href="javascript:void(0)" class="dz-plus" title="เพิ่มภาระงาน/ชิ้นงาน/กิจกรรม" onclick="dzOpen(\'mt'.$un.'_0\')">+</a></td>'
            .'<td class="c" rowspan="'.$span.'">'.h((string) (float) $u['mark']).'</td><td class="c'.(abs($tsum - (float) $u['mark']) > 0.001 ? ' dz-red' : '').'" rowspan="'.$span.'">'.h((string) $tsum).'</td>';
        if (!$tasks) {
            $rows .= '<tr>'.$head.'<td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
        }
        $first = true;
        foreach ($tasks as $tn => $t) {
            $sumTask += (float) $t['mark'];
            $sumPt += array_sum(array_map(fn($p) => (float) $p[1], $t['pts']));
            $sumSt += array_sum(array_map(fn($p) => (float) $p[1], $t['strands']));
            [$pc, $sc] = $ptCell($t);
            $rows .= '<tr>'.($first ? $head : '').'<td class="c">'.$tn.'</td><td><a href="javascript:void(0)" onclick="dzOpen(\'mt'.$un.'_'.$tn.'\')">'.h($t['title']).'</a></td><td class="c">'.h((string) (float) $t['mark']).'</td>'.$pc.$sc.'</tr>';
            $first = false;
            $modals .= d_task_modal($c, $un, $tn, $t, $base);
        }
        $modals .= d_task_modal($c, $un, 0, ['title' => '', 'mark' => '', 'pass' => '', 'half' => 'before', 'pts' => [], 'strands' => []], $base);
        $modals .= d_modal('mu'.$un, 'แก้ไขหน่วยการเรียนรู้ที่ '.$un, 400, $base.'&func=SaveUnit&unit='.$un, d_unit_fields($un, $u), 'หน่วยการเรียนรู้ และรายละเอียดของหน่วยการเรียนรู้ จะถูกนำไปใช้กับครูทุกคนที่สอนรายวิชาเดียวกันในภาคเรียนปัจจุบันเท่านั้น', 'หน่วยการเรียนรู้ที่ '.$un);
    }
    $examRow = function (string $label, string $which, array $d) use ($ptCell, &$sumPt, &$sumSt, $c): string {
        $sumPt += array_sum(array_map(fn($p) => (float) $p[1], $d['pts']));
        $sumSt += array_sum(array_map(fn($p) => (float) $p[1], $d['strands']));
        [$pc, $sc] = $ptCell($d);
        $set = md_get('dexam', dkey($c), $which) !== null;
        return '<tr><td class="lb"></td><td class="lb"></td><td class="lb"></td><td class="lb"></td><td class="lb"></td><td class="r">'.$label.'</td><td class="c"><a href="javascript:void(0)" onclick="dzOpen(\'mx_'.$which.'\')" class="'.($set ? '' : 'dz-edit').'" title="กำหนด'.h($label).'">'
            .($set ? h((string) (float) $d['mark']) : '<i class="fa fa-pencil-square-o"></i>').'</a></td>'.$pc.$sc.'</tr>';
    };
    $total = $sumTask + (float) $mid['mark'] + (float) $fin['mark'];
    $table = '<table class="dz-t"><tr><th rowspan="3" style="width:44px">หน่วยที่</th><th rowspan="3">ชื่อหน่วยการเรียนรู้</th><th rowspan="3" style="width:60px">คะแนนเต็ม<br>รายหน่วย</th><th rowspan="3" style="width:60px">คะแนนรวม<br>รายหน่วย</th>'
        .'<th colspan="7">วิเคราะห์ความสอดคล้อง</th></tr><tr><th rowspan="2" style="width:36px">ครั้งที่</th><th rowspan="2" class="g">ภาระงาน/ชิ้นงาน/กิจกรรม</th><th rowspan="2" style="width:44px">คะแนน</th><th colspan="2">มาตรฐาน/ตัวชี้วัด</th><th colspan="2">สาระการเรียนรู้</th></tr>'
        .'<tr><th style="width:96px">ตัวชี้วัดที่</th><th style="width:56px">นน.คะแนน</th><th style="width:44px">สาระที่</th><th style="width:56px">นน.คะแนน</th></tr>'.$rows
        .'<tr><td class="lb"></td><td class="r">รวม คะแนนหน่วยการเรียนรู้</td><td class="c'.($units && abs($sumUnit - $rw) > 0.001 ? ' dz-red' : '').'">'.($units ? h((string) $sumUnit) : '').'</td><td class="lb"></td><td class="lb"></td><td class="r">รวม คะแนนภาระงาน/ชิ้นงาน/กิจกรรม</td><td class="c'.($units && abs($sumTask - $rw) > 0.001 ? ' dz-red' : '').'">'.($units ? h((string) $sumTask) : '').'</td><td class="lb"></td><td class="lb"></td><td class="lb"></td><td class="lb"></td></tr>'
        .$examRow('คะแนนกลางภาค', 'mid', $mid).$examRow('คะแนนปลายภาค', 'final', $fin)
        .'<tr class="sum"><td></td><td></td><td></td><td></td><td></td><td class="r"><b>คะแนนรวม</b></td><td class="c'.(abs($total - 100) > 0.001 ? ' dz-red' : '').'">'.h((string) ($units ? $total : 0)).'</td><td></td><td class="c">'.h((string) $sumPt).'</td><td></td><td class="c">'.h((string) $sumSt).'</td></tr></table>';
    $nextUnit = $units ? max(array_keys($units)) + 1 : 1;
    $modals .= d_modal('mu0', 'เพิ่มหน่วยการเรียนรู้', 400, $base.'&func=SaveUnit&unit=0', d_unit_fields($nextUnit, ['title' => '', 'mark' => '']), 'หน่วยการเรียนรู้ และรายละเอียดของหน่วยการเรียนรู้ จะถูกนำไปใช้กับครูทุกคนที่สอนรายวิชาเดียวกันในภาคเรียนปัจจุบันเท่านั้น');
    foreach (['mid' => ['กำหนดคะแนนกลางภาค', $mid], 'final' => ['กำหนดคะแนนปลายภาค', $fin]] as $w => [$t, $d]) {
        $modals .= d_modal('mx_'.$w, $t, 770, $base.'&func=SaveExam&which='.$w, '<div style="display:flex;gap:10px"><div><label>คะแนนเต็ม</label><input class="in" name="mark" style="width:218px" value="'.h((string) $d['mark']).'"/></div>'
            .'<div><label>ผ่านเกณฑ์</label><input class="in" name="pass" style="width:62px" value="'.h((string) $d['pass']).'"/></div></div>'.d_pts_fields($c, $d),
            ($w === 'mid' ? 'คะแนนกลางภาค และรายละเอียดของคะแนนกลางภาค' : 'คะแนนปลายภาค และรายละเอียดของคะแนนปลายภาค').' จะถูกนำไปใช้กับครูทุกคนที่สอนวิชาเดียวกันในภาคเรียนปัจจุบันเท่านั้น');
    }
    // คัดลอกการออกแบบ: จากรายวิชาที่มีการออกแบบไว้แล้ว (ทุกภาคเรียน)
    $src = ['' => '- เลือกรายวิชาต้นแบบ -'];
    foreach (q('SELECT DISTINCT `r1` FROM {mis_data} WHERE `kind`=\'dunit\' ORDER BY `r1` DESC') as $r) {
        if ((string) $r['r1'] !== dkey($c)) {
            [$yt, $code] = explode('|', (string) $r['r1'], 2) + [1 => ''];
            $src[(string) $r['r1']] = $code.' (ภาคเรียนที่ '.str_replace('/', '/', preg_replace('#^(\d+)/(\d)$#', '$2/$1', $yt)).')';
        }
    }
    $modals .= d_modal('mcopy', 'คัดลอกการออกแบบ', 460, $base.'&func=CopyDesign', '<label>คัดลอกหน่วยการเรียนรู้ ภาระงาน และคะแนนกลางภาค/ปลายภาค จาก</label>'.d_sel('from', $src, '', 'class="in" style="width:100%"')
        .'<div style="font-size:12px;color:#c00;margin-top:6px">การออกแบบเดิมของรายวิชานี้จะถูกแทนที่ทั้งหมด</div>', 'คัดลอกการออกแบบการวัดและประเมินผลจากรายวิชาอื่นมาใช้กับรายวิชานี้');
    $body = d_css().saved_msg().'<div class="dz">'.d_info($c, $level, '<div class="ratio">นน.คะแนนคะแนนรายวิชา '.$rw.' : '.$rm.' : '.$rf.'</div><a href="javascript:void(0)" class="dz-addunit" onclick="dzOpen(\'mu0\')"><i class="fa fa-pencil-square-o"></i> เพิ่มหน่วยการเรียนรู้</a>')
        .'<div class="dz-h"><h2><i class="fa fa-book"></i>ตารางออกแบบการวัดและประเมินผล</h2><select onchange="location.href=this.value">'.$opts.'</select></div>'.$table
        .'<div class="dz-print"><a href="?module=task&option=design_measurement&func=PrintDesign&SubjectNum='.$cid.'" target="_blank" onMouseOver="MM_swapImage(\'icon_print\',\'\',\'../icon_tools/icon_print.png\',1);" onMouseOut="MM_swapImgRestore();"><img id="icon_print" src="../icon_tools/hicon_print.png" border="0" title="พิมพ์"/></a></div><div style="clear:both"></div></div>'
        .$modals.'<script>function dzOpen(i){var e=document.getElementById(i);if(e)e.classList.add("on")}function dzClose(i){document.getElementById(i).classList.remove("on")}'
        .'document.addEventListener("keydown",function(e){if(e.key==="Escape")document.querySelectorAll(".dz-ov.on").forEach(function(x){x.classList.remove("on")})});'
        .'document.querySelectorAll(".dz-ov").forEach(function(o){o.addEventListener("click",function(e){if(e.target===o)o.classList.remove("on")})});</script>';
    $page = sub_page('task_design_measurement', $body, 'ออกแบบการวัดและประเมินผล');
    $page['__CONTENT__'] = preg_replace('/(<h1 class="blue_title"[^>]*>)[^<]*(<\/h1>)/u', '${1}รายชื่อนักเรียนที่รับผิดชอบการสอน$2', $page['__CONTENT__'], 1);
    return d_with_buttons($page, d_head_buttons([['copy_design', 'javascript:dzOpen(\'mcopy\')', 'คัดลอกการออกแบบ'], ['back', '?module=task&option=design_measurement', 'กลับ']]));
}

function d_unit_fields(int $no, array $u): string
{
    return '<label>หน่วยการเรียนรู้ที่</label><input class="in" name="no" style="width:82px;color:#c0392b" value="'.$no.'"/>'
        .'<label>ชื่อหน่วยการเรียนรู้</label><textarea class="in" name="title">'.h((string) $u['title']).'</textarea>'
        .'<label>คะแนนเต็ม</label><input class="in" name="mark" style="width:82px" value="'.h((string) $u['mark']).'"/>';
}

function d_task_modal(array $c, int $un, int $tn, array $t, string $base): string
{
    $units = d_units($c);
    $no = $tn ?: (($units[$un]['tasks'] ?? []) ? max(array_keys($units[$un]['tasks'])) + 1 : 1);
    $half = '<div class="half"><label style="display:inline;font-family:Tahoma;font-size:13px;margin-right:24px"><input type="radio" name="half" value="before"'.($t['half'] !== 'after' ? ' checked' : '').'/> คะแนนก่อนกลางภาค</label>'
        .'<label style="display:inline;font-family:Tahoma;font-size:13px"><input type="radio" name="half" value="after"'.($t['half'] === 'after' ? ' checked' : '').'/> คะแนนหลังกลางภาค</label></div>';
    $body = '<div style="display:flex;gap:40px"><div><label>ชื่อภาระงาน/ชิ้นงาน/กิจกรรม</label><input class="in" name="title" style="width:292px" value="'.h((string) $t['title']).'"/>'
        .'<div style="display:flex;gap:10px"><div><label>คะแนนเต็ม</label><input class="in" name="mark" style="width:218px" value="'.h((string) $t['mark']).'"/></div><div><label>ผ่านเกณฑ์</label><input class="in" name="pass" style="width:62px" value="'.h((string) $t['pass']).'"/></div></div></div>'
        .'<div>'.$half.'</div></div>'.d_pts_fields($c, $t);
    return d_modal('mt'.$un.'_'.$tn, 'กำหนดคะแนนหน่วยที่ '.$un.'.'.$no, 770, $base.'&func=SaveTask&unit='.$un.'&task='.$tn, $body, '', $tn ? 'ภาระงานครั้งที่ '.$tn : '');
}

function act_task_design_measurement_SaveUnit(): void
{
    $c = att_course();
    if ($c) {
        $key = dkey($c);
        $old = (int) ($_GET['unit'] ?? 0);
        $no = max(1, (int) ($_POST['no'] ?? 1));
        if (!empty($_POST['del']) && $old) {
            md_set('dunit', $key, $old, '', null);
            foreach (md_rows('dtask', $key) as $r) {
                if ((int) explode('.', (string) $r['r2'])[0] === $old) {
                    md_set('dtask', $key, $r['r2'], '', null);
                }
            }
        } elseif (trim((string) ($_POST['title'] ?? '')) !== '') {
            $v = json_encode(['title' => mb_substr(trim((string) $_POST['title']), 0, 200), 'mark' => (string) (float) ($_POST['mark'] ?? 0)], JSON_UNESCAPED_UNICODE);
            if ($old && $old !== $no) {
                md_set('dunit', $key, $old, '', null);
                foreach (md_rows('dtask', $key) as $r) {
                    [$u, $t] = explode('.', (string) $r['r2']) + [1 => '0'];
                    if ((int) $u === $old) {
                        md_set('dtask', $key, $no.'.'.$t, '', (string) $r['value']);
                        md_set('dtask', $key, $r['r2'], '', null);
                    }
                }
            }
            md_set('dunit', $key, $no, '', $v);
        }
    }
    redirect('?module=task&option=design_measurement&func=DesignMeasurement&SubjectNum='.(int) ($_GET['SubjectNum'] ?? 0).'&msg=saved');
}

function act_task_design_measurement_SaveTask(): void
{
    $c = att_course();
    if ($c) {
        $key = dkey($c);
        $un = (int) ($_GET['unit'] ?? 0);
        $tn = (int) ($_GET['task'] ?? 0);
        $units = d_units($c);
        if (!empty($_POST['del']) && $tn) {
            md_set('dtask', $key, $un.'.'.$tn, '', null);
        } elseif (isset($units[$un]) && trim((string) ($_POST['title'] ?? '')) !== '') {
            if (!$tn) {
                $tn = ($units[$un]['tasks'] ? max(array_keys($units[$un]['tasks'])) : 0) + 1;
            }
            [$pts, $st] = d_post_pts();
            md_set('dtask', $key, $un.'.'.$tn, '', json_encode(['title' => mb_substr(trim((string) $_POST['title']), 0, 200), 'mark' => (string) (float) ($_POST['mark'] ?? 0),
                'pass' => trim((string) ($_POST['pass'] ?? '')) === '' ? '' : (string) (float) $_POST['pass'], 'half' => ($_POST['half'] ?? '') === 'after' ? 'after' : 'before', 'pts' => $pts, 'strands' => $st], JSON_UNESCAPED_UNICODE));
        }
    }
    redirect('?module=task&option=design_measurement&func=DesignMeasurement&SubjectNum='.(int) ($_GET['SubjectNum'] ?? 0).'&msg=saved');
}

function act_task_design_measurement_SaveExam(): void
{
    $c = att_course();
    $w = ($_GET['which'] ?? '') === 'final' ? 'final' : 'mid';
    if ($c) {
        [$pts, $st] = d_post_pts();
        md_set('dexam', dkey($c), $w, '', json_encode(['mark' => (string) (float) ($_POST['mark'] ?? 0), 'pass' => trim((string) ($_POST['pass'] ?? '')) === '' ? '' : (string) (float) $_POST['pass'],
            'pts' => $pts, 'strands' => $st], JSON_UNESCAPED_UNICODE));
    }
    redirect('?module=task&option=design_measurement&func=DesignMeasurement&SubjectNum='.(int) ($_GET['SubjectNum'] ?? 0).'&msg=saved');
}

function act_task_design_measurement_CopyDesign(): void
{
    $c = att_course();
    $from = (string) ($_POST['from'] ?? '');
    if ($c && $from !== '' && $from !== dkey($c)) {
        $key = dkey($c);
        foreach (['dunit', 'dtask', 'dexam'] as $k) {
            foreach (md_rows($k, $key) as $r) {
                md_set($k, $key, $r['r2'], $r['r3'], null);
            }
            foreach (md_rows($k, $from) as $r) {
                md_set($k, $key, $r['r2'], $r['r3'], (string) $r['value']);
            }
        }
    }
    redirect('?module=task&option=design_measurement&func=DesignMeasurement&SubjectNum='.(int) ($_GET['SubjectNum'] ?? 0).'&msg=saved');
}

/** พิมพ์ตารางออกแบบการวัดและประเมินผล */
function act_task_design_measurement_PrintDesign(): void
{
    $c = att_course();
    if (!$c) {
        rpt_print('ไม่สามารถพิมพ์ได้', '<div class="page"><h3>ไม่มีสิทธิ์พิมพ์เอกสารของรายวิชานี้</h3></div>');
    }
    [$rw, $rm, $rf] = score_ratio();
    $tr = '';
    $pt = fn($d) => '<td class="c">'.implode('<br>', array_map(fn($p) => h($p[0]), $d['pts'])).'</td><td class="c">'.implode('<br>', array_map(fn($p) => h((string) (float) $p[1]), $d['pts'])).'</td>'
        .'<td class="c">'.implode('<br>', array_map(fn($p) => h($p[0]), $d['strands'])).'</td><td class="c">'.implode('<br>', array_map(fn($p) => h((string) (float) $p[1]), $d['strands'])).'</td>';
    $sum = 0.0;
    foreach (d_units($c) as $un => $u) {
        $span = max(1, count($u['tasks']));
        $first = true;
        foreach ($u['tasks'] ?: [0 => null] as $tn => $t) {
            $sum += (float) ($t['mark'] ?? 0);
            $tr .= '<tr>'.($first ? '<td class="c" rowspan="'.$span.'">'.$un.'</td><td rowspan="'.$span.'">'.h($u['title']).'</td><td class="c" rowspan="'.$span.'">'.h((string) (float) $u['mark']).'</td>' : '')
                .($t ? '<td class="c">'.$tn.'</td><td>'.h($t['title']).'</td><td class="c">'.h((string) (float) $t['mark']).'</td>'.$pt($t) : '<td></td><td></td><td></td><td></td><td></td><td></td><td></td>').'</tr>';
            $first = false;
        }
    }
    $mid = d_exam($c, 'mid');
    $fin = d_exam($c, 'final');
    $tr .= '<tr><td colspan="5" class="r">คะแนนกลางภาค</td><td class="c">'.h((string) (float) $mid['mark']).'</td>'.$pt($mid).'</tr><tr><td colspan="5" class="r">คะแนนปลายภาค</td><td class="c">'.h((string) (float) $fin['mark']).'</td>'.$pt($fin).'</tr>'
        .'<tr><td colspan="5" class="r"><b>คะแนนรวม</b></td><td class="c"><b>'.h((string) ($sum + (float) $mid['mark'] + (float) $fin['mark'])).'</b></td><td colspan="4"></td></tr>';
    $teacher = personnel_options()[$c['teacher_id']] ?? '';
    rpt_print('ตารางออกแบบการวัดและประเมินผล', '<div class="page">'.rpt_head('ตารางออกแบบการวัดและประเมินผล', 'ภาคเรียนที่ '.(int) $c['term'].' ปีการศึกษา '.(int) $c['year'])
        .'<table class="nb"><tr><td>รายวิชา <b>'.h($c['course_code'].' '.$c['course_name']).'</b></td><td>หน่วยกิต '.h($c['credit']).'</td><td>เวลาเรียน '.h($c['period']).' ชั่วโมง</td><td>อัตราส่วนคะแนน '.$rw.' : '.$rm.' : '.$rf.'</td></tr><tr><td colspan="4">ครูผู้สอน <b>'.h($teacher).'</b></td></tr></table>'
        .'<table style="margin-top:6px"><tr><th rowspan="2">หน่วยที่</th><th rowspan="2">ชื่อหน่วยการเรียนรู้</th><th rowspan="2">คะแนน<br>เต็ม</th><th rowspan="2">ครั้งที่</th><th rowspan="2">ภาระงาน/ชิ้นงาน/กิจกรรม</th><th rowspan="2">คะแนน</th><th colspan="2">มาตรฐาน/ตัวชี้วัด</th><th colspan="2">สาระการเรียนรู้</th></tr>'
        .'<tr><th>ตัวชี้วัดที่</th><th>นน.คะแนน</th><th>สาระที่</th><th>นน.คะแนน</th></tr>'.$tr.'</table>'.rpt_signs([['ครูผู้สอน', $teacher], ['หัวหน้ากลุ่มสาระการเรียนรู้', ''], ['หัวหน้างานวัดผล', '']]).'</div>');
}

/* ---------------------------------------------------------------- บันทึกผลการเรียน (แท็บรายหน่วย + สรุป) */

/** คะแนนรายภาระงานของนักเรียน [gid => ['u.t' => score]] */
function task_scores(array $gids): array
{
    $o = [];
    foreach (md_rows('tscore', array_map('strval', $gids)) as $r) {
        $o[(int) $r['r1']] = json_decode((string) $r['value'], true) ?: [];
    }
    return $o;
}

/** ผลการเรียนอัตโนมัติ: '' = ยังไม่มีคะแนน */
function auto_grade(bool $any, bool $missing, ?float $total, ?float $attPct): string
{
    if (!$any) {
        return '';
    }
    if ($attPct !== null && $attPct < 80) {
        return 'มส';
    }
    if ($missing) {
        return 'ร';
    }
    return (float) $total < PASS_SCORE ? '0' : grade_of((int) round((float) $total));
}

function rows_task_study_record_Record()
{
    $c = att_course() ?? redirect('?module=task&option=study_record');
    $room = (int) ($_GET['RoomNum'] ?? 0);
    [$rw] = score_ratio();
    $units = d_units($c);
    $mid = d_exam($c, 'mid');
    $fin = d_exam($c, 'final');
    $yt = (int) $c['year'].'/'.(int) $c['term'];
    // รายวิชา-ห้องของครู สำหรับเลือกเปลี่ยน
    $courses = my_courses();
    if (!array_filter($courses, fn($x) => (int) $x['id'] === (int) $c['id'])) {
        $courses[] = $c;
    }
    $opts = '';
    foreach (course_rooms($courses) as $cid => $rooms) {
        foreach ($courses as $x) {
            if ((int) $x['id'] === $cid) {
                foreach (array_keys($rooms) as $r) {
                    $opts .= '<option value="?module=task&option=study_record&func=Record&SubjectNum='.$cid.'&RoomNum='.$r.'"'.($cid === (int) $c['id'] && $r === $room ? ' selected' : '').'>'.h($x['course_code'].' - '.class_room_short($x['class'], $r)).'</option>';
                }
            }
        }
    }
    $att = [];
    $total = 0;
    foreach (q('SELECT `student_id`, COUNT(*) n, SUM(`status` IN (\''.implode("','", \Attendance\Sheet\Model::PRESENT).'\')) p FROM {attendance} WHERE `course_id`=?'.($room ? ' AND `room`='.$room : '').' GROUP BY `student_id`', [(int) $c['id']]) as $r) {
        $att[(int) $r['student_id']] = [(int) $r['n'], (int) $r['p']];
        $total = max($total, (int) $r['n']);
    }
    $sql = 'SELECT G.`id` gid, G.`student_id`, G.`midterm`, G.`final`, G.`grade`, G.`number`, S.`student_id` code, S.`number` sno, U.`name` FROM {grade} G JOIN {student} S ON S.`id`=G.`student_id` JOIN {user} U ON U.`id`=G.`student_id` WHERE G.`course_id`=?';
    $p = [(int) $c['id']];
    if ($room) {
        $sql .= ' AND G.`room`=?';
        $p[] = $room;
    }
    $students = q($sql.' ORDER BY G.`room`, G.`number`, S.`student_id`', $p);
    $ts = task_scores(array_map(fn($s) => (int) $s['gid'], $students));
    $locked = record_locked($c, $room);
    $in = fn($n, $v, $max) => $locked ? h($v) : '<input type="text" name="'.$n.'" value="'.h($v).'" class="rec-in" maxlength="5" data-max="'.h((string) $max).'"/>';
    $nameCell = function ($s) {
        $np = preg_split('/\s+/u', trim((string) $s['name']), 2);
        return '<td class="c">'.h($s['number'] ?: $s['sno']).'</td><td class="c">'.h($s['code']).'</td><td style="white-space:nowrap"><span style="display:inline-block;min-width:150px">'.h($np[0]).'</span>'.h($np[1] ?? '').'</td>';
    };
    $attCells = function ($s) use ($att) {
        [$n, $pr] = $att[(int) $s['student_id']] ?? [0, 0];
        return '<td class="c">'.$pr.'</td><td class="c">'.($n ? number_format($pr * 100 / $n, 0) : '100').'</td>';
    };
    $unitMarkAll = array_sum(array_map(fn($u) => (float) $u['mark'], $units));
    $saveBar = fn($label) => $locked ? '' : '<div class="rec-save"><a href="javascript:void(0)" class="sv" onclick="document.forms.rec.submit()"><i class="fa fa-pencil-square-o"></i> '.h($label).'</a><a href="javascript:void(0)" class="cl" onclick="document.forms.rec.reset();recColor()">✕ เคลียร์</a></div>';
    // แท็บรายหน่วย
    $tabs = '';
    $panes = '';
    $ti = 0;
    foreach ($units as $un => $u) {
        $tabs .= '<span class="rec-tab" data-p="p'.$ti.'">หน่วยที่ '.$un.'</span>';
        $th = '';
        $mx = '';
        foreach ($u['tasks'] as $tn => $t) {
            $th .= '<th class="vt"><div>'.h($tn.'.'.$t['title']).'</div></th>';
            $mx .= '<td>'.h((string) (float) $t['mark']).'</td>';
        }
        $rows = '';
        foreach ($students as $s) {
            $gid = (int) $s['gid'];
            $sum = 0.0;
            $cells = '';
            foreach ($u['tasks'] as $tn => $t) {
                $v = $ts[$gid][$un.'.'.$tn] ?? '';
                $sum += (float) $v;
                $cells .= '<td class="c">'.$in('ts['.$gid.']['.$un.'.'.$tn.']', $v, (float) $t['mark']).'</td>';
            }
            $all = 0.0;
            foreach ($units as $uu => $x) {
                foreach ($x['tasks'] as $tt => $y) {
                    $all += (float) ($ts[$gid][$uu.'.'.$tt] ?? 0);
                }
            }
            $rows .= '<tr>'.$nameCell($s).$attCells($s).$cells.'<td class="c u-sum">'.h((string) $sum).'</td><td class="c u-sum">'.h((string) $all).'</td></tr>';
        }
        $n = count($u['tasks']);
        $panes .= '<div class="rec-pane" id="p'.$ti.'"><table class="rec u"><tr><th rowspan="3" style="width:46px">เลขที่</th><th rowspan="3" style="width:56px">รหัส</th><th rowspan="3">ชื่อ-นามสกุล</th><th colspan="'.(3 + $n + 1).'" style="font-size:21px">คะแนนประจำหน่วยการเรียนรู้ที่ '.$un.'</th></tr>'
            .'<tr><th colspan="2">เวลาเรียน</th><th colspan="'.($n + 1).'">'.h($u['title']).'</th><th>รวม</th></tr>'
            .'<tr><th class="b">คาบ</th><th class="b">ร้อยละ</th>'.$th.'<th class="b">รวม</th><th class="b">เต็ม</th></tr>'
            .'<tr class="max"><td colspan="3" style="background:#EDFCBE"></td><td>'.$total.'</td><td>100</td>'.$mx.'<td>'.h((string) (float) $u['mark']).'</td><td>'.h((string) $unitMarkAll).'</td></tr>'
            .($rows ?: '<tr><td colspan="'.(6 + $n).'" class="c">ไม่มีนักเรียนลงทะเบียน</td></tr>').'</table>'.$saveBar('บันทึกคะแนนหน่วยที่ '.$un).'</div>';
        $ti++;
    }
    // แท็บสรุป
    $rows = '';
    foreach ($students as $s) {
        $gid = (int) $s['gid'];
        [$uOld, $mOld] = array_pad(explode('|', (string) md_get('rec_split', $gid)), 2, '');
        $fix = json_decode((string) md_get('remedial', 'g'.$gid, $yt), true) ?: [];
        if ($units) {
            $usum = 0.0;
            foreach ($units as $uu => $x) {
                foreach ($x['tasks'] as $tt => $y) {
                    $usum += (float) ($ts[$gid][$uu.'.'.$tt] ?? 0);
                }
            }
            $unitCell = '<td class="c">'.h((string) $usum).'</td>';
        } else {
            $unitCell = '<td class="c">'.$in('unit['.$gid.']', $uOld !== '' || $mOld !== '' ? $uOld : (string) $s['midterm'], $rw).'</td>';
        }
        $has = $s['midterm'] !== null || $s['final'] !== null;
        $grade = (string) ($fix['old'] ?? $s['grade']);
        $tot = (int) $s['midterm'] + (int) $s['final'];
        // ช่อง "แก้" ขึ้นเฉพาะผู้ที่ติด 0 หรือ ร (กดเพื่อบันทึกคะแนนสอบแก้ไข)
        $np = preg_split('/\s+/u', trim((string) $s['name']), 2);
        $fixCell = in_array($grade, ['0', 'ร'], true) ? ($locked ? h((string) ($fix['score'] ?? ''))
            : '<input type="text" readonly class="rec-fix" value="'.h((string) ($fix['score'] ?? '')).'" title="บันทึกคะแนนสอบแก้ไข" onclick="recFix('.$gid.','.h(json_encode(trim(($np[0] ?? '').' '.($np[1] ?? '')), JSON_UNESCAPED_UNICODE)).','
                .h(json_encode((string) ($s['number'] ?: $s['sno']))).','.($grade === 'ร' ? 1 : 0).','.h(json_encode((string) ($fix['score'] ?? ''))).','.(!empty($fix['special']) ? 1 : 0).')"/>') : '';
        $rows .= '<tr>'.$nameCell($s).$attCells($s).$unitCell.'<td class="c">'.$in('mid['.$gid.']', $mOld, (float) $mid['mark']).'</td><td class="c">'.$in('fin['.$gid.']', (string) $s['final'], (float) $fin['mark']).'</td>'
            .'<td class="c sum"><b class="'.($tot < PASS_SCORE ? 'bad' : 'ok').'">'.($has ? $tot : '').'</b></td><td class="c sum"><b class="'.(in_array($grade, ['0', 'ร', 'มส'], true) ? 'bad' : 'ok').'">'.h($grade).'</b></td>'
            .'<td class="c fix">'.$fixCell.'</td><td class="c fix"><b class="'.(isset($fix['score']) && (float) $fix['score'] < PASS_SCORE ? 'bad' : 'ok').'">'.h((string) ($fix['score'] ?? '')).'</b></td><td class="c fix"><b class="'.(($fix['new'] ?? '') === '0' ? 'bad' : 'ok').'">'.h((string) ($fix['new'] ?? '')).'</b></td></tr>';
    }
    $tabs .= '<span class="rec-tab" data-p="p'.$ti.'">กลางภาค ปลายภาค และสรุปรวม</span>';
    $panes .= '<div class="rec-pane" id="p'.$ti.'"><table class="rec"><tr><th rowspan="3" style="width:46px">เลขที่</th><th rowspan="3" style="width:56px">รหัส</th><th rowspan="3">ชื่อ-นามสกุล</th><th colspan="10" style="font-size:21px">คะแนนสอบและผลการเรียน ประจำภาคเรียนที่ '.(int) $c['term'].' ปีการศึกษา '.(int) $c['year'].'</th></tr>'
        .'<tr><th colspan="2">เวลาเรียน</th><th>รายหน่วย</th><th>กลางภาค</th><th>ปลายภาค</th><th colspan="2">คะแนนรวมและสรุปผล</th><th colspan="3">แก้ไข</th></tr>'
        .'<tr><th class="b">คาบ</th><th class="b">ร้อยละ</th><th class="b">รวม</th><th class="b">คะแนน</th><th class="b">คะแนน</th><th class="b">รวม</th><th class="b">เกรด</th><th rowspan="2" class="b">แก้</th><th class="b">รวม</th><th class="b">เกรด</th></tr>'
        .'<tr class="max"><td colspan="3" style="background:#FDE5DA"></td><td>'.$total.'</td><td>100</td><td>'.h((string) ($units ? $unitMarkAll : $rw)).'</td><td>'.h((string) (float) $mid['mark']).'</td><td>'.h((string) (float) $fin['mark']).'</td><td>100</td><td>4</td><td>100</td><td>4</td></tr>'
        .($rows ?: '<tr><td colspan="13" class="c">ไม่มีนักเรียนลงทะเบียน</td></tr>').'</table>'.$saveBar('บันทึกคะแนนกลางภาค และปลายภาค').'</div>';
    $rn = room_short($room);
    $level = 'ระดับชั้น'.cat('class', $c['class']).($room ? '/'.(strpos($rn, '/') !== false ? substr($rn, strrpos($rn, '/') + 1) : $rn) : '');
    [$r1, $r2, $r3] = score_ratio();
    $style = '<style>.rec-bar{border:1px solid #d3d3d3;border-radius:4px 4px 0 0;background:linear-gradient(#fff,#f3f3f3);padding:6px 10px;display:flex;align-items:center;justify-content:space-between}'
        .'.rec-bar h2{font-family:thaisansbold;font-weight:normal;font-size:24px;margin:0;color:#333}.rec-bar h2 i{color:#F7941D;margin-right:8px}.rec-bar+.dz-info{border-top:none!important;border-radius:0 0 4px 4px}'
        .'.rec-tabs{margin-top:4px}.rec-tab{display:inline-block;background:#A9A9A9;color:#fff;font-family:thaisansbold;font-size:17px;padding:5px 10px 3px;border-radius:3px 3px 0 0;cursor:pointer;margin-right:3px}.rec-tab.on{background:#17B4B4}'
        .'.rec-line{border-top:3px solid #17B4B4}.rec-pane{display:none}.rec-pane.on{display:block}'
        .'table.rec{border-collapse:collapse;width:100%;font:13px Tahoma}table.rec th{background:#FDE5DA;border:1px solid #9CCFD6;font-family:thaisansbold;font-weight:normal;font-size:19px;color:#222;padding:3px 4px;text-align:center!important;vertical-align:middle}'
        .'table.rec th.b{background:#EDEDED}table.rec.u th{background:#EDFCBE}table.rec.u th.b,table.rec.u th.vt{background:#EDEDED}'
        .'table.rec th.vt{height:240px;vertical-align:bottom;padding:6px 2px;width:46px}table.rec th.vt div{writing-mode:vertical-rl;transform:rotate(180deg);white-space:nowrap;margin:0 auto;font-size:16px;max-height:230px;overflow:hidden}'
        .'table.rec td{border:1px solid #9CCFD6;padding:4px 5px;height:26px;color:#000}table.rec .c{text-align:center}table.rec .sum{background:#F3F5FF}table.rec .fix{background:#FFFDE8}table.rec .u-sum{background:#F8FDE8}'
        .'table.rec tr.max td{background:#F7F7F7;text-align:center;font-size:14px}table.rec b.ok{color:#1BA826}table.rec b.bad{color:#E00}'
        .'.rec-in{width:40px;height:20px;text-align:center;border:1px solid #ccc;border-radius:3px;font:13px Tahoma}.rec-in.ok{border-color:#B1D2A4;background:#ECFFEC}.rec-in.bad{border-color:#EEB9B9;background:#FFFAF2;color:#E00}.rec-in.err{border-color:#e00;background:#ffecec}'
        .'.rec-fix{width:37px;height:20px;border:1px solid #B1D2A4;background:#ECFFEC;text-align:center;cursor:pointer;font:13px Tahoma}'
        .'.rec-save{text-align:right;padding:8px 0;border:1px solid #ddd;border-top:none;background:#fafafa;padding:6px 6px}.rec-save a{display:inline-block;color:#fff!important;text-decoration:none;font:13px Tahoma;padding:5px 12px;border-radius:3px;margin-left:5px}'
        .'.rec-save .sv{background:#3EA2E8;border:1px solid #2f8fd3;min-width:200px;text-align:center}.rec-save .cl{background:#F7B331;border:1px solid #e09a16}</style>';
    $lockMsg = $locked ? msg_box(md_get('approved', (int) $c['id']) !== null ? 'ผลการเรียนได้รับการอนุมัติแล้ว ไม่สามารถแก้ไขได้ หากต้องการแก้ไขให้แจ้งแอดมินยกเลิกการอนุมัติ'
        : 'ยืนยันผลการเรียนแล้ว (รออนุมัติ) ไม่สามารถแก้ไขข้อมูลได้ จนกว่าจะยกเลิกการยืนยันที่หน้ารายงานผลการเรียน', false) : '';
    $body = d_css().$style.saved_msg().$lockMsg.'<div class="dz"><div class="rec-bar"><h2><i class="fa fa-list-ul"></i>โอนคะแนนและผลการเรียน</h2><select onchange="location.href=this.value" style="height:24px;min-width:160px">'.$opts.'</select></div>'
        .d_info($c, $level, '<div class="ratio">อัตราส่วนคะแนนรายวิชา '.$r1.' : '.$r2.' : '.$r3.'</div>')
        .'<form name="rec" method="post" action="?module=task&option=study_record&func=SaveRecord&SubjectNum='.(int) $c['id'].'&RoomNum='.$room.'"><input type="hidden" name="tab" id="rec-tab" value=""/>'
        .'<div class="rec-tabs">'.$tabs.'</div><div class="rec-line"></div>'.$panes
        .'</form></div>'
        .'<div class="dz-ov" id="mfix"><div class="dz-m" style="width:430px"><form method="post" action="?module=task&option=study_record&func=SaveFix&SubjectNum='.(int) $c['id'].'&RoomNum='.$room.'"><input type="hidden" name="gid" id="fx-gid"/>'
        .'<div class="hd"><i class="fa fa-list-ul"></i>บันทึกคะแนนสอบแก้ไข<span class="x" onclick="dzClose(\'mfix\')">✕</span></div><div class="bd"><div style="display:flex;gap:10px;align-items:center;background:#F7F7F7;border:1px solid #eee;padding:8px;font-size:13px;clear:both;float:none;width:auto"><i class="fa fa-commenting" style="color:#2BC3C9;font-size:22px"></i><span id="fx-name"></span></div>'
        .'<div id="fx-sp" style="margin:10px 0 0 86px;clear:both"><label style="display:inline;font-family:Tahoma;font-size:13px;margin:0"><input type="checkbox" name="special" value="1" id="fx-special"/> กรณีพิเศษ</label></div>'
        .'<div style="display:flex;align-items:center;gap:10px;margin-top:10px;clear:both"><span style="width:76px;font-size:13px;white-space:nowrap">คะแนนแก้</span><input class="in" name="score" id="fx-score" style="width:150px" maxlength="5" oninput="var v=parseFloat(this.value),t=this;if(!isNaN(v)&&v>100){t.blur();recMsg(\'ไม่อนุญาตให้บันทึกคะแนนมากกว่า 100 คะแนน\',function(){t.value=\'\';t.focus()})}"/><span style="font-size:13px">คะแนน</span></div></div>'
        .'<div class="ft"><button type="submit" class="s">✎ บันทึก</button><button type="button" class="c" onclick="document.getElementById(\'fx-score\').value=\'\';document.getElementById(\'fx-special\').checked=false">✕ เคลียร์</button></div></form></div></div>'
        .'<script>(function(){var tabs=document.querySelectorAll(".rec-tab");function show(id){tabs.forEach(function(t){t.classList.toggle("on",t.dataset.p===id)});document.querySelectorAll(".rec-pane").forEach(function(p){p.classList.toggle("on",p.id===id)});document.getElementById("rec-tab").value=id}'
        .'tabs.forEach(function(t){t.addEventListener("click",function(){show(t.dataset.p)})});var m=location.search.match(/[?&]tab=(p\d+)/);show(m&&document.getElementById(m[1])?m[1]:tabs[tabs.length-1].dataset.p);'
        .'document.querySelectorAll(".rec-in").forEach(function(i){i.addEventListener("input",function(){recColor(i);recMax(i)})});recColor();'
        // กดบันทึก: ถ้ายังมีช่องที่เกินคะแนนเต็ม แจ้งเตือนและไม่บันทึก
        .'var f=document.forms.rec;if(f){var sub=f.submit;f.submit=function(){var bad=[].slice.call(f.querySelectorAll(".rec-in")).filter(function(i){return recOver(i)});if(bad.length){recMax(bad[0]);return}sub.call(f)}}})();'
        // ระบบเดิม: "ไม่อนุญาตให้บันทึกคะแนนมากกว่า N คะแนน" แล้วล้างช่องนั้น
        .'function recOver(i){var mx=parseFloat(i.dataset.max),v=parseFloat(i.value);return i.value!==""&&mx>0&&!isNaN(v)&&v>mx}'
        .'function recMsg(t,done){if(window.AlertBox&&AlertBox.alert){AlertBox.alert(t,"",{onComplete:done})}else{alert(t);done()}}'
        .'function recMax(i){if(!recOver(i))return;var mx=parseFloat(i.dataset.max);i.blur();recMsg("ไม่อนุญาตให้บันทึกคะแนนมากกว่า "+(Math.round(mx*100)/100)+" คะแนน",function(){i.value="";recColor(i);i.focus()})}'
        // สีช่องคะแนน: ได้ตั้งแต่ครึ่งหนึ่งของคะแนนเต็ม = เขียว, ต่ำกว่า = แดง, เกินคะแนนเต็ม/ไม่ใช่ตัวเลข = ผิดพลาด
        .'function recColor(one){(one?[one]:document.querySelectorAll(".rec-in")).forEach(function(i){var mx=parseFloat(i.dataset.max),v=parseFloat(i.value),e=i.value!==""&&(isNaN(v)||v<0||(mx>0&&v>mx));'
        .'i.classList.toggle("err",e);i.classList.toggle("ok",!e&&i.value!==""&&mx>0&&v>=mx/2);i.classList.toggle("bad",!e&&i.value!==""&&mx>0&&v<mx/2)})}'
        .'function recFix(g,n,no,isR,sc,sp){document.getElementById("fx-gid").value=g;document.getElementById("fx-name").textContent=n+" เลขที่ "+no;document.getElementById("fx-sp").style.display=isR?"":"none";'
        .'document.getElementById("fx-special").checked=!!sp;document.getElementById("fx-score").value=sc;dzOpen("mfix");document.getElementById("fx-score").focus()}'
        .'function dzOpen(i){document.getElementById(i).classList.add("on")}function dzClose(i){document.getElementById(i).classList.remove("on")}document.addEventListener("keydown",function(e){if(e.key==="Escape")dzClose("mfix")});</script>';
    $page = sub_page('task_study_record', $body, 'รายงานผลการเรียน');
    $back = "<div style='float:left;margin:0px 2px'> <a href='?module=task&option=study_record' onMouseOver=\"MM_swapImage('icon_back','','../icon_tools/icon_back.png',1);\" onMouseOut=\"MM_swapImgRestore();\"><img id='icon_back' name='icon_back' src='../icon_tools/hicon_back.png' border='0' title='กลับ' align='top'/></a> </div>";
    return d_with_buttons($page, rpt_record_buttons($c, $room).$back);
}

function act_task_study_record_SaveRecord(): void
{
    $c = att_course();
    $tab = preg_replace('/[^a-z0-9]/', '', (string) ($_POST['tab'] ?? ''));
    if ($c && !record_locked($c, (int) ($_GET['RoomNum'] ?? 0))) {
        $units = d_units($c);
        $mid = d_exam($c, 'mid');
        $fin = d_exam($c, 'final');
        [$rw] = score_ratio();
        $yt = (int) $c['year'].'/'.(int) $c['term'];
        $num = fn($v, $max) => trim((string) $v) === '' || !is_numeric(trim((string) $v)) ? null : max(0, min((float) $max ?: 100, (float) $v));
        $attPct = [];
        foreach (q('SELECT `student_id`, COUNT(*) n, SUM(`status` IN (\''.implode("','", \Attendance\Sheet\Model::PRESENT).'\')) p FROM {attendance} WHERE `course_id`=? GROUP BY `student_id`', [(int) $c['id']]) as $r) {
            $attPct[(int) $r['student_id']] = (int) $r['n'] ? (int) $r['p'] * 100 / (int) $r['n'] : null;
        }
        foreach ((array) ($_POST['fin'] ?? []) as $gid => $fv) {
            $gid = (int) $gid;
            $row = q1('SELECT `student_id` FROM {grade} WHERE `id`=? AND `course_id`=?', [$gid, (int) $c['id']]);
            if (!$row) {
                continue;
            }
            $any = false;
            $missing = false;
            if ($units) {
                $sc = [];
                $usum = 0.0;
                foreach ($units as $un => $u) {
                    foreach ($u['tasks'] as $tn => $t) {
                        $v = $num($_POST['ts'][$gid][$un.'.'.$tn] ?? '', $t['mark']);
                        if ($v === null) {
                            $missing = true;
                        } else {
                            $sc[$un.'.'.$tn] = $v;
                            $usum += $v;
                            $any = true;
                        }
                    }
                }
                md_set('tscore', $gid, '', '', $sc ? json_encode($sc) : null);
                $u = $sc ? $usum : null;
            } else {
                $u = $num($_POST['unit'][$gid] ?? '', $rw);
                $missing = $u === null;
                $any = $u !== null;
            }
            $m = $num($_POST['mid'][$gid] ?? '', $mid['mark']);
            $f = $num($fv, $fin['mark']);
            $any = $any || $m !== null || $f !== null;
            $missing = $missing || $m === null || $f === null;
            md_set('rec_split', $gid, '', '', $u === null && $m === null ? null : ($u ?? '').'|'.($m ?? ''));
            $midterm = $u === null && $m === null ? null : (int) round((float) $u + (float) $m);
            $final = $f === null ? null : (int) round($f);
            $grade = auto_grade($any, $missing, (float) $midterm + (float) $final, $attPct[(int) $row['student_id']] ?? null);
            // คะแนนสอบแก้ไขเดิม: ยังติด 0/ร = ใช้ผลแก้ไข, ไม่ติดแล้ว = ลบผลแก้ไข
            $fix = json_decode((string) md_get('remedial', 'g'.$gid, $yt), true) ?: [];
            if ($fix) {
                if (in_array($grade, ['0', 'ร'], true)) {
                    $fix['old'] = $grade;
                    $fix['new'] = fix_grade((float) $fix['score'], $grade, !empty($fix['special']));
                    md_set('remedial', 'g'.$gid, $yt, '', json_encode($fix, JSON_UNESCAPED_UNICODE));
                    $grade = $fix['new'];
                } else {
                    md_set('remedial', 'g'.$gid, $yt, '', null);
                }
            }
            q('UPDATE {grade} SET `midterm`=?, `final`=?, `grade`=? WHERE `id`=? AND `course_id`=?', [$midterm, $final, $grade === '' ? null : $grade, $gid, (int) $c['id']]);
        }
    }
    redirect('?module=task&option=study_record&func=Record&SubjectNum='.(int) ($_GET['SubjectNum'] ?? 0).'&RoomNum='.(int) ($_GET['RoomNum'] ?? 0).($tab !== '' ? '&tab='.$tab : '').'&msg=saved');
}

/** ผลการสอบแก้ไข: ปกติผ่านเกณฑ์ได้ 1 ไม่ผ่านได้ 0 / ติด ร กรณีพิเศษ = ตัดเกรดตามคะแนนจริง */
function fix_grade(float $score, string $old, bool $special): string
{
    if ($score < PASS_SCORE) {
        return '0';
    }
    return $special && $old === 'ร' ? grade_of((int) round($score)) : '1';
}

/** บันทึกคะแนนสอบแก้ไข (ป๊อปอัปในแท็บสรุป) */
function act_task_study_record_SaveFix(): void
{
    $c = att_course();
    $room = (int) ($_GET['RoomNum'] ?? 0);
    $gid = (int) ($_POST['gid'] ?? 0);
    if ($c && !record_locked($c, $room)) {
        $row = q1('SELECT `student_id`, `grade` FROM {grade} WHERE `id`=? AND `course_id`=?', [$gid, (int) $c['id']]);
        $yt = (int) $c['year'].'/'.(int) $c['term'];
        $fix = json_decode((string) md_get('remedial', 'g'.$gid, $yt), true) ?: [];
        $old = (string) ($fix['old'] ?? ($row['grade'] ?? ''));
        if ($row && in_array($old, ['0', 'ร'], true)) {
            $raw = trim((string) ($_POST['score'] ?? ''));
            if ($raw === '' || !is_numeric($raw)) {
                md_set('remedial', 'g'.$gid, $yt, '', null);
                q('UPDATE {grade} SET `grade`=? WHERE `id`=?', [$old, $gid]);
            } else {
                $score = max(0, min(100, (float) $raw));
                $special = $old === 'ร' && !empty($_POST['special']);
                $new = fix_grade($score, $old, $special);
                md_set('remedial', 'g'.$gid, $yt, '', json_encode(['student' => (int) $row['student_id'], 'course' => (int) $c['id'], 'old' => $old, 'new' => $new,
                    'date' => date('Y-m-d'), 'apply' => 'ใช่', 'score' => (string) (int) round($score), 'special' => $special ? 1 : 0], JSON_UNESCAPED_UNICODE));
                q('UPDATE {grade} SET `grade`=? WHERE `id`=?', [$new, $gid]);
            }
        }
    }
    redirect('?module=task&option=study_record&func=Record&SubjectNum='.(int) ($_GET['SubjectNum'] ?? 0).'&RoomNum='.$room.'&msg=saved');
}

/* ---------------------------------------------------------------- ผู้ดูแล: ตัวชี้วัดและสาระการเรียนรู้ */

function adm_indicators(bool $post)
{
    $pre = (string) ($_GET['sara'] ?? 'ส');
    $groups = array_intersect_key(SARA_BY_PREFIX, STRANDS_DEFAULT);
    if (!isset($groups[$pre])) {
        $pre = 'ส';
    }
    $levels = ['ป.1', 'ป.2', 'ป.3', 'ป.4', 'ป.5', 'ป.6', 'ม.1', 'ม.2', 'ม.3', 'ม.4', 'ม.5', 'ม.6'];
    $lv = in_array($_GET['level'] ?? '', $levels, true) ? $_GET['level'] : 'ม.1';
    if ($post) {
        md_set('indicator', $pre.'|'.$lv, '', '', trim((string) ($_POST['ind'] ?? '')) === '' ? null : trim((string) $_POST['ind']));
        md_set('strand', $pre, '', '', trim((string) ($_POST['str'] ?? '')) === '' ? null : trim((string) $_POST['str']));
        admin_done('indicators', '&sara='.rawurlencode($pre).'&level='.rawurlencode($lv));
    }
    $ind = md_get('indicator', $pre.'|'.$lv) ?? implode("\n", indicator_seed()[$pre.'|'.$lv] ?? []);
    $str = md_get('strand', $pre) ?? implode("\n", STRANDS_DEFAULT[$pre] ?? []);
    $f = pick_form('indicators', ['sara' => ['กลุ่มสาระฯ', $groups, $pre], 'level' => ['ระดับชั้น', array_combine($levels, $levels), $lv]]);
    return ['ตัวชี้วัดและสาระการเรียนรู้', $f.admin_form('ตัวชี้วัด '.$groups[$pre].' ชั้น '.$lv.' และสาระการเรียนรู้', a_url('indicators', '&sara='.rawurlencode($pre).'&level='.rawurlencode($lv)), [
        ['ตัวชี้วัด (บรรทัดละ 1 ตัว เช่น ส 1.1 ม.1/1)', '<textarea name="ind" style="width:420px;height:260px">'.h($ind).'</textarea>', false],
        ['สาระการเรียนรู้ของกลุ่มสาระ (เรียงตามสาระที่ 1, 2, ...)', '<textarea name="str" style="width:420px;height:120px">'.h($str).'</textarea>', false],
    ], a_url('indicators'))];
}
