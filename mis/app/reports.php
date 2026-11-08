<?php
/**
 * mis/app/reports.php
 * พิมพ์เอกสารผลการเรียน (กระดาษ A4):
 *  - card   ใบรายงานผลการเรียน (ใบเกรด) รายภาค/รายปี — รายคนหรือทั้งห้อง
 *  - course ใบคะแนนรายวิชา (ใบส่งผลการเรียน) — รายวิชาหรือทุกวิชาของครู
 *  - room   สรุปผลการเรียนรายห้อง (ตารางเกรดทุกวิชา + GPA + อันดับ)
 *  - blank  แบบกรอกคะแนน (ใบคะแนนเปล่าสำหรับครู)
 * เข้าได้จาก: ผู้ดูแล (บริหารงานวิชาการ), ครู (?module=print — เฉพาะวิชาที่สอน/ห้องที่ปรึกษา), นักเรียน (/student ใบเกรดของตนเอง)
 */
defined('MIS') or exit;

const RPT_DOCS = ['card' => 'ใบรายงานผลการเรียน (ใบเกรด)', 'course' => 'ใบคะแนนรายวิชา (ส่งผลการเรียน)', 'room' => 'สรุปผลการเรียนรายห้อง', 'blank' => 'แบบกรอกคะแนน (ใบคะแนนเปล่า)'];

/* ---------------------------------------------------------------- ข้อมูล */

/** ภาคเรียนจากพารามิเตอร์ yt=ปี/ภาค (ค่าเริ่มต้น = ที่เลือกดูอยู่) */
/** ตราโรงเรียนสำหรับหน้าพิมพ์ (ทางเต็ม) */
function rpt_logo(): string
{
    $l = school_logo();
    return strpos($l, '../') === 0 ? '/mis/'.substr($l, 3) : $l;
}

function rpt_term(): array
{
    if (preg_match('#^(\d{4})/([123])$#', (string) ($_GET['yt'] ?? ''), $m)) {
        return [(int) $m[1], (int) $m[2]];
    }
    [$y, $t] = term();
    return [(int) $y, (int) $t];
}

function rpt_term_options(): array
{
    $o = [];
    foreach (array_keys(all_terms()) as $k) {
        $o[$k] = 'ภาคเรียนที่ '.explode('/', $k)[1].'/'.explode('/', $k)[0];
    }
    return $o;
}

/** ผู้บริหาร/ผู้ลงนาม: ชื่อจาก "กำหนดผู้บริหารโรงเรียน" ตามคำในตำแหน่ง */
function rpt_signer(string $word): string
{
    $po = personnel_options();
    foreach (md_rows('executive') as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        if (mb_strpos((string) ($d['position'] ?? ''), $word) !== false) {
            return $po[$d['person'] ?? 0] ?? '';
        }
    }
    return '';
}

/** เวลาเรียนร้อยละของนักเรียนในรายวิชา [course => [sid => pct]] */
function rpt_attendance(array $courseIds): array
{
    $out = [];
    if (!$courseIds) {
        return $out;
    }
    try {
        $tot = [];
        foreach (q('SELECT `course_id`, `student_id`, `status`, COUNT(*) n FROM {attendance} WHERE `course_id` IN ('.implode(',', array_map('intval', $courseIds)).') GROUP BY `course_id`, `student_id`, `status`') as $r) {
            $c = (int) $r['course_id'];
            $s = (int) $r['student_id'];
            $tot[$c][$s][0] = ($tot[$c][$s][0] ?? 0) + (int) $r['n'];
            if (in_array($r['status'], ['present', 'late', 'activity', 'remedial'], true)) {
                $tot[$c][$s][1] = ($tot[$c][$s][1] ?? 0) + (int) $r['n'];
            }
        }
        foreach ($tot as $c => $ss) {
            foreach ($ss as $s => $v) {
                $out[$c][$s] = $v[0] ? ($v[1] ?? 0) * 100 / $v[0] : null;
            }
        }
    } catch (\Throwable $e) {
    }
    return $out;
}

function rpt_grades(int $sid, int $y, int $t): array
{
    return array_values(array_filter(student_grades($sid), fn($g) => (int) $g['year'] === $y && (int) $t === (int) $g['term']));
}

/** ข้อความเกรด ("0" ต้องแสดงเป็น 0 ไม่ใช่ -) */
function rpt_g($g): string
{
    return $g === null || $g === '' ? '-' : (string) $g;
}

function rpt_gpa_text(?float $g): string
{
    return $g === null ? '-' : number_format(floor($g * 100) / 100, 2);
}

function rpt_room_students(int $rid): array
{
    return q('SELECT S.*, U.`name`, U.`sex` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`room`=? AND U.`active`=1 ORDER BY S.`number`, S.`student_id`', [$rid]);
}

function rpt_room_name(int $rid): string
{
    $cid = room_class($rid);
    return $cid ? class_room_short($cid, $rid) : room_short($rid);
}

/* ---------------------------------------------------------------- หน้ากระดาษ */

function rpt_print(string $title, string $body, bool $landscape = false): void
{
    mis_headers();
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><title>'.h($title).'</title><link rel="stylesheet" href="/mis/css/thaisansbold.css"><link rel="stylesheet" href="/mis/css/sarabun.css"><style>'
        .'@page{size:A4 '.($landscape ? 'landscape' : 'portrait').';margin:10mm 12mm}*{box-sizing:border-box}body{margin:0;background:#888;font:13px/1.35 "TH Sarabun New",sans-serif;font-size-adjust:0.545;color:#000}.page *{font-family:"TH Sarabun New",sans-serif}'
        .'.page{width:'.($landscape ? '297mm' : '210mm').';min-height:'.($landscape ? '210mm' : '297mm').';margin:10px auto;background:#fff;padding:10mm 12mm;page-break-after:always;position:relative}'
        .'.page:last-child{page-break-after:auto}h1,h2,h3{font-family:"TH Sarabun New",sans-serif;font-weight:bold;margin:2px 0;text-align:center}h1{font-size:28px}h2{font-size:22px}h3{font-size:18px}'
        .'table{border-collapse:collapse;width:100%}td,th{border:1px solid #000;padding:3px 5px;font-size:12px}th{background:#eee;font-weight:bold}.c{text-align:center}.r{text-align:right}.nb td{border:none;padding:1px 4px}'
        .'.head{display:flex;align-items:center;gap:12px;justify-content:center;margin-bottom:6px}.head img{height:62px}.sign{display:flex;justify-content:space-around;margin-top:22px;text-align:center;font-size:12px}'
        .'.sign div{width:30%}.small{font-size:11px}.cut{border-top:1px dashed #000;margin-top:14px;padding-top:6px}.vt{writing-mode:vertical-rl;transform:rotate(180deg);white-space:nowrap;padding:4px 1px}'
        .'.bar{position:sticky;top:0;background:#333;color:#fff;padding:8px;text-align:center;z-index:9}.bar button{font:14px Tahoma;padding:4px 14px;margin:0 4px}'
        .'@media print{body{background:#fff}.page{margin:0;width:auto;min-height:0;padding:0}.bar{display:none}}</style></head><body>'
        .'<div class="bar"><button onclick="window.print()">🖨 พิมพ์</button><button onclick="history.length>1?history.back():window.close()">กลับ</button> <span class="small">ตั้งค่ากระดาษ A4 '.($landscape ? 'แนวนอน' : 'แนวตั้ง').' / ระยะขอบค่าเริ่มต้น</span></div>'
        .$body.'</body></html>';
    exit;
}

function rpt_head(string $title, string $sub = ''): string
{
    return '<div class="head"><img src="'.h(rpt_logo()).'" alt=""/><div><h2>'.h(cfg('school_name', 'โรงเรียนดรุณราษฎร์วิทยา')).'</h2><h3>'.h($title).'</h3>'.($sub !== '' ? '<div class="c small">'.$sub.'</div>' : '').'</div></div>';
}

function rpt_signs(array $who): string
{
    $o = '<div class="sign">';
    foreach ($who as [$role, $name]) {
        $o .= '<div>ลงชื่อ ..................................<br>('.($name !== '' ? h($name) : '..................................').')<br>'.h($role).'</div>';
    }
    return $o.'</div>';
}

/* ---------------------------------------------------------------- 1) ใบรายงานผลการเรียน */

function rpt_card_page(array $s, int $y, int $t, string $mode, array $att): string
{
    $sid = (int) $s['id'];
    $terms = $mode === 'year' ? [[$y, 1], [$y, 2]] : [[$y, $t]];
    $body = '';
    $yearGrades = [];
    foreach ($terms as [$yy, $tt]) {
        $gs = rpt_grades($sid, $yy, $tt);
        $yearGrades = array_merge($yearGrades, $gs);
        $tr = '';
        $credits = 0.0;
        foreach ($gs as $i => $g) {
            $pct = $att[(int) $g['course_id']][$sid] ?? null;
            $credits += is_numeric($g['grade']) ? (float) $g['credit'] : 0;
            $tr .= '<tr><td class="c">'.($i + 1).'</td><td>'.h($g['course_code']).'</td><td>'.h($g['course_name']).'</td><td class="c">'.h(COURSE_TYPES[(int) $g['ctype']] ?? '').'</td><td class="c">'.h($g['credit']).'</td>'
                .'<td class="c">'.(int) $g['midterm'].'</td><td class="c">'.(int) $g['final'].'</td><td class="c">'.((int) $g['midterm'] + (int) $g['final']).'</td><td class="c">'.($pct === null ? '-' : number_format($pct, 0)).'</td><td class="c"><b>'.h(rpt_g($g['grade'])).'</b></td></tr>';
        }
        if (count($terms) > 1) {
            $body .= '<div style="margin-top:6px"><b>ภาคเรียนที่ '.$tt.'</b></div>';
        }
        $body .= '<table style="margin-top:4px"><tr><th style="width:28px">ที่</th><th style="width:70px">รหัสวิชา</th><th>รายวิชา</th><th style="width:60px">ประเภท</th><th style="width:48px">หน่วยกิต</th><th style="width:52px">ระหว่างภาค</th>'
            .'<th style="width:48px">ปลายภาค</th><th style="width:40px">รวม</th><th style="width:48px">เวลาเรียน %</th><th style="width:52px">ผลการเรียน</th></tr>'.($tr ?: '<tr><td colspan="10" class="c">ยังไม่มีผลการเรียน</td></tr>')
            .'<tr><td colspan="4" class="r">หน่วยกิตที่ได้ / ผลการเรียนเฉลี่ยภาคเรียนนี้ (GPA)</td><td class="c">'.$credits.'</td><td colspan="4"></td><td class="c"><b>'.rpt_gpa_text(gpa_of($gs)).'</b></td></tr></table>';
    }
    $gpax = gpa_of(array_filter(student_grades($sid), fn($g) => (int) $g['year'] < $y || ((int) $g['year'] === $y && (int) $g['term'] <= max(array_column($terms, 1)))));
    $lv = fn($k) => ($a = score_avg($k, $sid)) === null ? '-' : level_text($a);
    $adv = array_map(fn($p) => personnel_options()[$p] ?? '', room_advisors()[(int) $s['room']] ?? []);
    $sum = '<table class="nb" style="margin-top:8px"><tr><td style="width:50%">ผลการเรียนเฉลี่ย'.($mode === 'year' ? 'ทั้งปี' : 'ภาคเรียนนี้').' <b>'.rpt_gpa_text(gpa_of($yearGrades)).'</b></td><td>ผลการเรียนเฉลี่ยสะสม (GPAX) <b>'.rpt_gpa_text($gpax).'</b></td></tr>'
        .'<tr><td>คุณลักษณะอันพึงประสงค์ <b>'.$lv('char').'</b></td><td>การอ่าน คิดวิเคราะห์และเขียน <b>'.$lv('skill').'</b></td></tr>'
        .'<tr><td>สมรรถนะสำคัญของผู้เรียน <b>'.(($a = score_avg(preg_match('/ป\./', class_short((int) $s['class'])) ? 'capp' : 'capj', $sid)) === null ? '-' : level_text($a)).'</b></td><td></td></tr></table>';
    $title = 'รายงานผลการเรียน'.($mode === 'year' ? ' ปีการศึกษา '.$y : ' ภาคเรียนที่ '.$t.' ปีการศึกษา '.$y);
    $info = '<table class="nb" style="margin-top:6px"><tr><td>ชื่อ-นามสกุล <b>'.h($s['name']).'</b></td><td>เลขประจำตัว <b>'.h($s['student_id']).'</b></td><td>ชั้น <b>'.h(rpt_room_name((int) $s['room'])).'</b> เลขที่ <b>'.h($s['number']).'</b></td></tr></table>';
    $cut = '<div class="cut small">✂ ส่วนส่งคืนโรงเรียน &nbsp; ข้าพเจ้า ...................................................... ผู้ปกครองของ '.h($s['name']).' ชั้น '.h(rpt_room_name((int) $s['room']))
        .' ได้รับทราบผลการเรียนแล้ว<br><br>ความคิดเห็นของผู้ปกครอง ..................................................................................................................... ลงชื่อ ................................ ผู้ปกครอง</div>';
    return '<div class="page">'.rpt_head($title).$info.$body.$sum
        .rpt_signs([['ครูที่ปรึกษา', $adv[0] ?? ''], ['รองผู้อำนวยการฝ่ายวิชาการ', rpt_signer('วิชาการ')], ['ผู้อำนวยการโรงเรียน', rpt_signer('ผู้อำนวยการ')]]).$cut.'</div>';
}

function rpt_card(array $students, int $y, int $t, string $mode): void
{
    $cids = [];
    foreach ($students as $s) {
        foreach (student_grades((int) $s['id']) as $g) {
            $cids[(int) $g['course_id']] = 1;
        }
    }
    $att = rpt_attendance(array_keys($cids));
    $html = '';
    foreach ($students as $s) {
        $html .= rpt_card_page($s, $y, $t, $mode, $att);
    }
    rpt_print('ใบรายงานผลการเรียน', $html ?: '<div class="page">ไม่พบนักเรียน</div>');
}

/* ---------------------------------------------------------------- 2) ใบคะแนนรายวิชา */

function rpt_course_page(array $c, array $att): string
{
    $rows = q('SELECT G.*, S.`student_id` code, S.`room` sroom, S.`number` sno, U.`name` FROM {grade} G JOIN {student} S ON S.`id`=G.`student_id` JOIN {user} U ON U.`id`=G.`student_id` WHERE G.`course_id`=? ORDER BY S.`room`, S.`number`, S.`student_id`', [(int) $c['id']]);
    $tr = '';
    $dist = [];
    foreach ($rows as $i => $g) {
        $pct = $att[(int) $c['id']][(int) $g['student_id']] ?? null;
        $k = rpt_g($g['grade']);
        $dist[$k] = ($dist[$k] ?? 0) + 1;
        $tr .= '<tr><td class="c">'.($i + 1).'</td><td class="c">'.h(rpt_room_name((int) $g['sroom'])).'</td><td class="c">'.h($g['sno']).'</td><td>'.h($g['code']).'</td><td>'.h($g['name']).'</td>'
            .'<td class="c">'.($pct === null ? '-' : number_format($pct, 0)).'</td><td class="c">'.(int) $g['midterm'].'</td><td class="c">'.(int) $g['final'].'</td><td class="c">'.((int) $g['midterm'] + (int) $g['final']).'</td><td class="c"><b>'.h(rpt_g($g['grade'])).'</b></td></tr>';
    }
    $order = ['4', '3.5', '3', '2.5', '2', '1.5', '1', '0', 'ร', 'มส', 'มผ', 'ผ', '-'];
    $dh = '';
    $dv = '';
    foreach ($order as $k) {
        if (isset($dist[$k]) || in_array($k, ['4', '3.5', '3', '2.5', '2', '1.5', '1', '0', 'ร', 'มส'], true)) {
            $dh .= '<th>'.h($k).'</th>';
            $dv .= '<td class="c">'.(int) ($dist[$k] ?? 0).'</td>';
        }
    }
    $n = count($rows);
    $pass = array_sum(array_map(fn($k) => (int) ($dist[$k] ?? 0), ['4', '3.5', '3', '2.5', '2', '1.5', '1', 'ผ']));
    $teacher = personnel_options()[$c['teacher_id']] ?? '';
    $head = rpt_head('แบบรายงานผลการเรียนรายวิชา', 'ภาคเรียนที่ '.(int) $c['term'].' ปีการศึกษา '.(int) $c['year'])
        .'<table class="nb"><tr><td>รหัสวิชา <b>'.h($c['course_code']).'</b> รายวิชา <b>'.h($c['course_name']).'</b></td><td>ประเภท '.h(COURSE_TYPES[(int) $c['type']] ?? '').'</td><td>หน่วยกิต '.h($c['credit']).'</td><td>ชั้น '.h(cat('class', $c['class'])).'</td></tr>'
        .'<tr><td colspan="4">ครูผู้สอน <b>'.h($teacher ?: '-').'</b></td></tr></table>';
    return '<div class="page">'.$head.'<table style="margin-top:6px"><tr><th style="width:28px">ที่</th><th style="width:50px">ห้อง</th><th style="width:36px">เลขที่</th><th style="width:70px">เลขประจำตัว</th><th>ชื่อ-นามสกุล</th>'
        .'<th style="width:52px">เวลาเรียน %</th><th style="width:56px">ระหว่างภาค</th><th style="width:50px">ปลายภาค</th><th style="width:40px">รวม</th><th style="width:52px">ผลการเรียน</th></tr>'.($tr ?: '<tr><td colspan="10" class="c">ไม่มีนักเรียนลงทะเบียน</td></tr>').'</table>'
        .'<table style="margin-top:8px"><tr><th>ผลการเรียน</th>'.$dh.'<th>รวม</th></tr><tr><td class="c">จำนวน (คน)</td>'.$dv.'<td class="c">'.$n.'</td></tr></table>'
        .'<div class="small" style="margin-top:4px">ผ่านเกณฑ์ '.$pass.' คน คิดเป็นร้อยละ '.($n ? number_format($pass * 100 / $n, 2) : '0.00').'</div>'
        .rpt_signs([['ครูผู้สอน', $teacher], ['หัวหน้ากลุ่มสาระการเรียนรู้', ''], ['หัวหน้างานวัดผล', '']]).rpt_signs([['รองผู้อำนวยการฝ่ายวิชาการ', rpt_signer('วิชาการ')], ['ผู้อำนวยการโรงเรียน', rpt_signer('ผู้อำนวยการ')]]).'</div>';
}

function rpt_course(array $courses): void
{
    $att = rpt_attendance(array_map(fn($c) => (int) $c['id'], $courses));
    $html = '';
    foreach ($courses as $c) {
        $html .= rpt_course_page($c, $att);
    }
    rpt_print('ใบคะแนนรายวิชา', $html ?: '<div class="page">ไม่พบรายวิชา</div>');
}

/* ---------------------------------------------------------------- 3) สรุปผลการเรียนรายห้อง */

function rpt_room(int $rid, int $y, int $t): void
{
    $students = rpt_room_students($rid);
    $grades = [];
    $courses = [];
    foreach ($students as $s) {
        foreach (rpt_grades((int) $s['id'], $y, $t) as $g) {
            $grades[(int) $s['id']][(int) $g['course_id']] = $g['grade'];
            $courses[(int) $g['course_id']] = $g;
        }
    }
    uasort($courses, fn($a, $b) => [$a['ctype'], $a['course_code']] <=> [$b['ctype'], $b['course_code']]);
    $gpa = [];
    foreach ($students as $s) {
        $gpa[(int) $s['id']] = gpa_of(rpt_grades((int) $s['id'], $y, $t));
    }
    $sorted = array_filter($gpa, fn($v) => $v !== null);
    arsort($sorted);
    $rank = [];
    $r = 0;
    $prev = null;
    $i = 0;
    foreach ($sorted as $sid => $v) {
        $i++;
        if ($prev === null || abs($v - $prev) > 0.0001) {
            $r = $i;
        }
        $prev = $v;
        $rank[$sid] = $r;
    }
    $th = '';
    $cr = '';
    foreach ($courses as $c) {
        $th .= '<th class="vt" style="height:120px">'.h($c['course_code'].' '.mb_substr($c['course_name'], 0, 22)).'</th>';
        $cr .= '<td class="c small">'.h($c['credit']).'</td>';
    }
    $tr = '';
    foreach ($students as $s) {
        $id = (int) $s['id'];
        $tr .= '<tr><td class="c">'.h($s['number']).'</td><td>'.h($s['student_id']).'</td><td style="white-space:nowrap">'.h($s['name']).'</td>';
        foreach (array_keys($courses) as $cid) {
            $g = $grades[$id][$cid] ?? '';
            $tr .= '<td class="c"'.(in_array($g, ['0', 'ร', 'มส', 'มผ'], true) ? ' style="color:#c00;font-weight:bold"' : '').'>'.h($g).'</td>';
        }
        $tr .= '<td class="c"><b>'.rpt_gpa_text($gpa[$id]).'</b></td><td class="c">'.($rank[$id] ?? '-').'</td></tr>';
    }
    $adv = array_map(fn($p) => personnel_options()[$p] ?? '', room_advisors()[$rid] ?? []);
    rpt_print('สรุปผลการเรียน '.rpt_room_name($rid), '<div class="page">'.rpt_head('สรุปผลการเรียน ชั้น '.rpt_room_name($rid), 'ภาคเรียนที่ '.$t.' ปีการศึกษา '.$y.' &nbsp; ครูที่ปรึกษา '.h(implode(', ', array_filter($adv)) ?: '-'))
        .'<table><tr><th rowspan="2" style="width:30px">เลขที่</th><th rowspan="2" style="width:60px">เลขประจำตัว</th><th rowspan="2">ชื่อ-นามสกุล</th>'.$th.'<th rowspan="2" style="width:40px">GPA</th><th rowspan="2" style="width:36px">อันดับ</th></tr>'
        .'<tr>'.$cr.'</tr>'.($tr ?: '<tr><td colspan="'.(5 + count($courses)).'" class="c">ไม่พบนักเรียน</td></tr>').'</table>'
        .'<div class="small" style="margin-top:4px">แถวที่ 2 ของหัวตาราง = หน่วยกิต / ตัวสีแดง = 0 ร มส มผ</div>'.rpt_signs([['ครูที่ปรึกษา', $adv[0] ?? ''], ['หัวหน้างานวัดผล', ''], ['รองผู้อำนวยการฝ่ายวิชาการ', rpt_signer('วิชาการ')]]).'</div>', true);
}

/* ---------------------------------------------------------------- 4) แบบกรอกคะแนน */

function rpt_blank(array $c, int $cols): void
{
    $rows = q('SELECT S.`student_id` code, S.`room` sroom, S.`number` sno, U.`name` FROM {grade} G JOIN {student} S ON S.`id`=G.`student_id` JOIN {user} U ON U.`id`=G.`student_id` WHERE G.`course_id`=? ORDER BY S.`room`, S.`number`, S.`student_id`', [(int) $c['id']]);
    $cols = max(1, min(15, $cols));
    $th = '';
    for ($k = 1; $k <= $cols; $k++) {
        $th .= '<th style="width:34px">'.$k.'</th>';
    }
    $tr = '';
    foreach ($rows as $i => $g) {
        $tr .= '<tr><td class="c">'.($i + 1).'</td><td class="c">'.h(rpt_room_name((int) $g['sroom'])).'</td><td>'.h($g['code']).'</td><td style="white-space:nowrap">'.h($g['name']).'</td>'.str_repeat('<td></td>', $cols + 3).'</tr>';
    }
    rpt_print('แบบกรอกคะแนน', '<div class="page">'.rpt_head('แบบบันทึกคะแนน รายวิชา '.$c['course_code'].' '.$c['course_name'], 'ภาคเรียนที่ '.(int) $c['term'].' ปีการศึกษา '.(int) $c['year'].' &nbsp; ครูผู้สอน '.h(personnel_options()[$c['teacher_id']] ?? '-'))
        .'<table><tr><th rowspan="2" style="width:28px">ที่</th><th rowspan="2" style="width:46px">ห้อง</th><th rowspan="2" style="width:60px">เลขประจำตัว</th><th rowspan="2">ชื่อ-นามสกุล</th><th colspan="'.$cols.'">คะแนนเก็บ / งานชิ้นที่</th>'
        .'<th rowspan="2" style="width:44px">ระหว่างภาค</th><th rowspan="2" style="width:40px">ปลายภาค</th><th rowspan="2" style="width:36px">รวม</th></tr><tr>'.$th.'</tr>'
        .'<tr><td colspan="4" class="r small">คะแนนเต็ม</td>'.str_repeat('<td></td>', $cols + 3).'</tr>'.($tr ?: '<tr><td colspan="'.(7 + $cols).'" class="c">ไม่มีนักเรียนลงทะเบียน</td></tr>').'</table>'
        .rpt_signs([['ครูผู้สอน', personnel_options()[$c['teacher_id']] ?? '']]).'</div>', $cols > 8);
}

/* ---------------------------------------------------------------- สิทธิ์และหน้าเลือกเอกสาร */

/** ผู้ใช้ปัจจุบันดูได้ทั้งหมดหรือไม่ (ผู้ดูแลวิชาการ / ผู้บริหาร) */
function rpt_all(): bool
{
    return in_array('academic', user_modules(), true) || is_manager();
}

/** รายวิชาที่พิมพ์ได้ของภาคเรียนที่เลือก */
function rpt_courses(int $y, int $t): array
{
    $sql = 'SELECT * FROM {course} WHERE `year`=? AND `term`=?';
    $p = [$y, $t];
    if (!rpt_all()) {
        $sql .= ' AND `teacher_id`=?';
        $p[] = (int) me()['id'];
    }
    return q($sql.' ORDER BY `course_code`, `class`', $p);
}

/** ห้องที่พิมพ์ได้ [rid => ชื่อ] */
function rpt_rooms(): array
{
    if (rpt_all()) {
        $o = [];
        foreach (rooms_by_class() as $cid => $rs) {
            foreach ($rs as $rid => $n) {
                $o[$rid] = $cid ? class_room_short($cid, $rid) : $n;
            }
        }
        return $o;
    }
    $rid = (int) (q1('SELECT `room` FROM {personnel} WHERE `id`=?', [(int) me()['id']])['room'] ?? 0);
    return $rid ? [$rid => rpt_room_name($rid)] : [];
}

/** สร้างเอกสารตามพารามิเตอร์ (ตรวจสิทธิ์แล้ว) — คืนค่าข้อความผิดพลาดถ้าทำไม่ได้ */
function rpt_run(): string
{
    $doc = (string) ($_GET['doc'] ?? '');
    [$y, $t] = rpt_term();
    $rooms = rpt_rooms();
    if ($doc === 'card' || $doc === 'room') {
        $rid = (int) ($_GET['room'] ?? 0);
        $sid = (int) ($_GET['student'] ?? 0);
        if ($sid && !$rid) {
            $rid = (int) (q1('SELECT `room` FROM {student} WHERE `id`=?', [$sid])['room'] ?? 0);
        }
        if (!isset($rooms[$rid])) {
            return 'ไม่มีสิทธิ์พิมพ์เอกสารของห้องนี้';
        }
        if ($doc === 'room') {
            rpt_room($rid, $y, $t);
        }
        $list = rpt_room_students($rid);
        if ($sid) {
            $list = array_values(array_filter($list, fn($s) => (int) $s['id'] === $sid));
        }
        rpt_card($list, $y, $t, ($_GET['mode'] ?? '') === 'year' ? 'year' : 'term');
    }
    if ($doc === 'course' || $doc === 'blank') {
        $mine = [];
        foreach (rpt_courses($y, $t) as $c) {
            $mine[(int) $c['id']] = $c;
        }
        $cid = (int) ($_GET['course'] ?? 0);
        if ($cid && !isset($mine[$cid])) {
            // รายวิชาจากภาคอื่น (ลิงก์ตรง)
            $c = q1('SELECT * FROM {course} WHERE `id`=?', [$cid]);
            if ($c && (rpt_all() || (int) $c['teacher_id'] === (int) me()['id'])) {
                $mine[$cid] = $c;
            } else {
                return 'ไม่มีสิทธิ์พิมพ์เอกสารของรายวิชานี้';
            }
        }
        $tid = (int) ($_GET['teacher'] ?? 0);
        $list = $cid ? [$mine[$cid]] : array_values(array_filter($mine, fn($c) => !$tid || (int) $c['teacher_id'] === $tid));
        if ($doc === 'blank') {
            if (!$list) {
                return 'ไม่พบรายวิชา';
            }
            rpt_blank($list[0], (int) ($_GET['cols'] ?? 10));
        }
        rpt_course($list);
    }
    return 'ไม่พบเอกสาร';
}

/** แบบฟอร์มเลือกเอกสาร (ใช้ทั้งหน้าผู้ดูแลและหน้าครู) */
function rpt_forms(string $base): string
{
    [$y, $t] = rpt_term();
    $yt = $y.'/'.$t;
    $terms = rpt_term_options();
    $rooms = rpt_rooms();
    $courses = [];
    foreach (rpt_courses($y, $t) as $c) {
        $courses[$c['id']] = $c['course_code'].' '.$c['course_name'].' ('.class_short((int) $c['class']).')';
    }
    $teachers = [];
    if (rpt_all()) {
        foreach (q('SELECT DISTINCT C.`teacher_id` FROM {course} C WHERE C.`year`=? AND C.`term`=? AND C.`teacher_id`>0', [$y, $t]) as $r) {
            $teachers[$r['teacher_id']] = personnel_options()[$r['teacher_id']] ?? ('#'.$r['teacher_id']);
        }
    }
    $so = '<option value="0">- ทั้งห้อง -</option>';
    foreach (array_keys($rooms) as $rid) {
        foreach (rpt_room_students((int) $rid) as $s) {
            $so .= '<option value="'.(int) $s['id'].'" data-r="'.(int) $rid.'">เลขที่ '.h($s['number'].' '.$s['student_id'].' '.$s['name']).'</option>';
        }
    }
    $stuSel = '<select name="student" class="rpt-stu" style="height:26px;max-width:380px">'.$so.'</select>';
    $f = function (string $doc, string $fields, string $desc) use ($base, $yt) {
        return '<form method="get" action="'.h($base).'" target="_blank" style="margin:0 0 12px;padding:10px 14px;border:1px solid #d6e4ee;border-radius:6px;background:#fbfdff;text-align:left">'
            .'<input type="hidden" name="module" value="'.h($_GET['module'] ?? 'admin').'"/><input type="hidden" name="option" value="'.h($_GET['option'] ?? 'print_docs').'"/><input type="hidden" name="doc" value="'.$doc.'"/><input type="hidden" name="yt" value="'.h($yt).'"/>'
            .'<div style="font-family:thaisansbold;font-size:22px;color:#0F5A9C">'.h(RPT_DOCS[$doc]).'</div><div class="gray_12" style="margin-bottom:6px">'.$desc.'</div>'.$fields
            .' <button type="submit" class="general radius" style="height:26px;padding:0 14px;cursor:pointer">🖨 พิมพ์</button></form>';
    };
    $sel = fn($n, $o, $v = '', $w = 300) => sel($n, $o, $v, 'style="height:26px;max-width:'.$w.'px"');
    $o = '<div style="width:983px;margin:0 auto 8px;text-align:left"><form method="get" action="'.h($base).'"><input type="hidden" name="module" value="'.h($_GET['module'] ?? 'admin').'"/><input type="hidden" name="option" value="'.h($_GET['option'] ?? 'print_docs').'"/>'
        .'<b>ภาคเรียน</b> '.sel('yt', $terms, $yt, 'onchange="this.form.submit()" style="height:26px"').'</form></div><div style="width:983px;margin:0 auto">';
    $o .= $rooms ? $f('card', 'ห้อง '.sel('room', $rooms, '', 'class="rpt-room" style="height:26px;max-width:140px"').' นักเรียน '.$stuSel.' '.$sel('mode', ['term' => 'รายภาคเรียน', 'year' => 'ทั้งปีการศึกษา (ภาค 1+2)'], 'term', 200),
        'หน้าละ 1 คน มีผลการเรียนทุกรายวิชา เวลาเรียน GPA/GPAX คุณลักษณะฯ การอ่านฯ สมรรถนะ ลายเซ็น และส่วนส่งคืนผู้ปกครอง (เลือกนักเรียนเป็น "ทั้งห้อง" เพื่อพิมพ์ทั้งห้อง)')
        .$f('room', 'ห้อง '.$sel('room', $rooms, '', 140), 'ตารางผลการเรียนทุกรายวิชาของนักเรียนทั้งห้อง พร้อม GPA และอันดับในห้อง (A4 แนวนอน)') : '';
    $o .= $courses ? $f('course', 'รายวิชา '.$sel('course', ['0' => '- ทุกรายวิชา -'] + $courses, '0', 420).($teachers ? ' ครูผู้สอน '.$sel('teacher', ['0' => '- ทุกคน -'] + $teachers, '0', 220) : ''),
        'รายชื่อนักเรียนพร้อมคะแนนระหว่างภาค ปลายภาค รวม ผลการเรียน เวลาเรียน สรุปจำนวนแต่ละเกรด และช่องลงนาม (หน้าละ 1 รายวิชา)')
        .$f('blank', 'รายวิชา '.$sel('course', $courses, '', 420).' จำนวนช่องคะแนนเก็บ '.$sel('cols', array_combine(range(1, 15), range(1, 15)), '10', 60), 'แบบฟอร์มเปล่าสำหรับครูบันทึกคะแนนเก็บด้วยมือ มีรายชื่อนักเรียนที่ลงทะเบียน') : '';
    if (!$rooms && !$courses) {
        $o .= msg_box('ยังไม่มีรายวิชาที่สอนหรือห้องที่ปรึกษาในภาคเรียนนี้', false);
    }
    // กรองรายชื่อนักเรียนตามห้องที่เลือก
    $o .= '<script>(function(){var r=document.querySelector(".rpt-room"),s=document.querySelector(".rpt-stu");if(!r||!s)return;var all=[].slice.call(s.options);'
        .'function f(){var v=r.value;s.innerHTML="";all.forEach(function(o){if(!o.dataset.r||o.dataset.r===v)s.appendChild(o)});s.value="0"}r.addEventListener("change",f);f()})();</script>';
    return $o.'</div>';
}

/** หน้าผู้ดูแล: บริหารงานวิชาการ > พิมพ์เอกสารผลการเรียน */
function adm_print_docs(bool $post)
{
    if (isset($_GET['doc'])) {
        return msg_box(h(rpt_run()), false);
    }
    return ['พิมพ์ใบเกรด / ใบคะแนน / เอกสารผลการเรียน', rpt_forms('index.php')];
}

/** หน้าครู: ?module=print (เข้าได้ทุกคน แต่เห็นเฉพาะวิชาที่สอนและห้องที่ปรึกษา) */
function run_print_page(): void
{
    if (isset($_GET['doc'])) {
        $err = rpt_run();
        rpt_print('ไม่สามารถพิมพ์ได้', '<div class="page"><h3>'.h($err).'</h3></div>');
    }
    $GLOBALS['mis_page_title'] = 'พิมพ์เอกสารผลการเรียน';
    render_page('พิมพ์เอกสารผลการเรียน', '<div style="width:983px;margin:10px auto 6px;text-align:left"><h1 style="font-family:thaisansbold;font-weight:normal;font-size:34px;color:#0F5A9C;margin:0">พิมพ์ใบเกรด / ใบคะแนน</h1>'
        .'<div class="gray_12">แสดงเฉพาะรายวิชาที่สอนและห้องที่เป็นครูที่ปรึกษา</div></div>'.rpt_forms('index.php'), '');
}

/* ---------------------------------------------------------------- พิมพ์จากหน้าบันทึกผลการเรียน (งานการสอน) */

/** ปุ่ม 4 แบบ: [func, ไอคอน, ชื่อ, หัวข้อช่อง, จำนวนช่อง, แนวนอน] */
const RPT_RECORD = [
    'PrintByPoint' => ['print_point', 'รายตัวชี้วัด', 'ตัวชี้วัดที่', 10, true],
    'PrintByUnit' => ['print_unit', 'รายหน่วยการเรียนรู้', 'หน่วยที่', 6, false],
    'PrintByStandard' => ['print_std', 'รายมาตรฐานการเรียนรู้', 'มฐ.', 6, false],
    'PrintByLearning' => ['print_strand', 'รายสาระการเรียนรู้', 'สาระที่', 4, false],
];

/** ปุ่มพิมพ์ 4 ปุ่มบนหัวหน้าบันทึกผลการเรียน */
function rpt_record_buttons(array $c, int $room): string
{
    $o = '';
    foreach (RPT_RECORD as $func => [$ic, $label]) {
        $url = 'index.php?module=task&option=study_record&func='.$func.'&TeacherId='.(int) $c['teacher_id'].'&SubjectNum='.(int) $c['id'].'&RecordType=subject&Elective=0&RoomNum='.$room;
        $o .= "<div style='float:left;margin:0px 2px'> <a href='javascript:void(0);' onclick=\"window.open('".$url."','_blank');\" onMouseOver=\"MM_swapImage('icon_".$ic."','','../icon_tools/icon_".$ic.".png',1);\" onMouseOut=\"MM_swapImgRestore();\">"
            ."<img id='icon_".$ic."' name='icon_".$ic."' src='../icon_tools/hicon_".$ic.".png' border='0' title='พิมพ์".h($label)."' align='top'/></a> </div>";
    }
    return $o;
}

/** ตัวเลขแบบระบบเดิม (ทศนิยมเท่าที่จำเป็น) */
function rp_num($v, int $dec = 2): string
{
    if ($v === null || $v === '') {
        return '';
    }
    $s = number_format((float) $v, $dec, '.', '');
    return strpos($s, '.') !== false ? rtrim(rtrim($s, '0'), '.') : $s;
}

/** สถิติของคอลัมน์ [เฉลี่ย, ร้อยละผ่าน, ต่ำสุด, สูงสุด, ร้อยละของเต็ม, SD, CV] */
function rp_stats(array $vals, float $max): array
{
    $vals = array_values(array_filter($vals, fn($v) => $v !== null));
    $n = count($vals);
    if (!$n) {
        return array_fill(0, 7, '');
    }
    $avg = array_sum($vals) / $n;
    $sd = sqrt(array_sum(array_map(fn($v) => ($v - $avg) ** 2, $vals)) / $n);
    $pass = count(array_filter($vals, fn($v) => $max > 0 && $v >= $max / 2));
    return [number_format($avg, 2), number_format($pass * 100 / $n, 2), rp_num(min($vals)), rp_num(max($vals)), $max > 0 ? number_format($avg * 100 / $max, 2) : '', number_format($sd, 2), $avg > 0 ? number_format($sd * 100 / $avg, 2) : ''];
}

/** หน้าพิมพ์แบบ "ตัวอย่างก่อนพิมพ์" ของระบบเดิม */
function rp_page(string $title, string $func, array $c, int $room, string $body, string $margin = 'Left = 8 &nbsp; Right = 8 &nbsp; Top = 4 &nbsp; Bottom = 4'): void
{
    $opts = '';
    $courses = my_courses();
    if (!array_filter($courses, fn($x) => (int) $x['id'] === (int) $c['id'])) {
        $courses[] = $c;
    }
    foreach (course_rooms($courses) as $cid => $rooms) {
        foreach ($courses as $x) {
            if ((int) $x['id'] === $cid) {
                foreach (array_keys($rooms) as $r) {
                    $opts .= '<option value="index.php?module=task&option=study_record&func='.$func.'&TeacherId='.(int) $x['teacher_id'].'&SubjectNum='.$cid.'&RecordType=subject&Elective=0&RoomNum='.$r.'"'.($cid === (int) $c['id'] && $r === $room ? ' selected' : '').'>'.h($x['course_code'].' - '.class_room_short($x['class'], $r)).'</option>';
                }
            }
        }
    }
    mis_headers();
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><title>'.h($title).'</title><link rel="stylesheet" href="/mis/css/thaisansbold.css"><link rel="stylesheet" href="/mis/css/sarabun.css"><style>'
        .'@page{size:A4 portrait;margin:4mm 8mm}body{margin:0;background:#fff;font:18.67px/1.15 "TH Sarabun New",sans-serif;color:#000}'
        .'.pv-top{background:#FBFBFB;display:flex;justify-content:space-between;align-items:center;padding:0 10px;border-bottom:1px solid #9FD8E6;margin:10px 10px 0}.pv-top select{font:13px Tahoma;height:22px}'
        .'.pv-bar{margin:0 10px;background:linear-gradient(90deg,#71C7F1,#63B5EC);color:#fff;text-align:center;font-size:24px;padding:8px 0;margin-top:8px;font-family:thaisansbold,"TH Sarabun New"}'
        .'.pv-line{margin:4px 10px 0;border-top:2px solid #2BB5C6}.pv-tabs{margin:14px 18px 0;border-bottom:2px solid #2BB5C6}.pv-tabs a{display:inline-block;background:#A9A9A9;color:#fff;font:15px thaisansbold;padding:5px 12px 3px;margin-right:4px;border-radius:3px 3px 0 0;cursor:pointer;text-decoration:none}.pv-tabs a.on{background:#17B4B4}'
        .'.pv-wrap{padding:14px 10px 4px}.pv-h{display:flex;align-items:flex-start;gap:10px;font-weight:bold;font-size:19px}.pv-h img{width:58px;height:58px}.pv-h u{text-decoration:none;border-bottom:1px dotted #000;padding:0 6px}'
        .'.pv-hc{position:relative;text-align:center;font-weight:bold;font-size:19px}.pv-hc img{position:absolute;left:0;top:0;width:58px;height:58px}.pv-hc u{text-decoration:none;border-bottom:1px dotted #000;padding:0 6px}'
        .'table.pv{border-collapse:collapse;margin:14px auto 0;width:984px;max-width:100%}table.pv td,table.pv th{border:1px solid #000;padding:2px 4px;font-weight:normal;font-size:18.67px;height:24px}table.pv th{font-weight:bold}'
        .'table.pv .c{text-align:center}table.pv .r{text-align:right}table.pv .vt{writing-mode:vertical-rl;transform:rotate(180deg);white-space:nowrap;margin:0 auto}table.pv tr.st td{background:#F2F2F2;font-weight:bold}table.pv .cv{color:#E00;font-weight:bold}'
        .'.pv-rep{font:16px "TH Sarabun New",sans-serif;margin:12px 10px 6px}.pv-tip{background:#D3D3D3;margin:0 10px 10px;text-align:center;padding:10px;font-size:18px}.pv-page{display:none}.pv-page.on{display:block}'
        .'@media print{.pv-top,.pv-bar,.pv-line,.pv-tabs,.pv-tip{display:none}.pv-wrap{padding:0}table.pv{width:100%}}</style></head><body>'
        .'<div class="pv-top"><img src="/mis/images/preview_title.png" alt="ตัวอย่างก่อนพิมพ์" style="height:80px"/><div><select onchange="location.href=this.value">'.$opts.'</select> '
        .'<a href="javascript:window.print()" title="พิมพ์"><img src="/mis/icon_tools/icon_mini_print.png" border="0" align="absmiddle"/></a></div></div><div class="pv-bar">'.h($title).'</div><div class="pv-line"></div>'
        .$body.'<div class="pv-rep">Reported: DRW-MIS ('.date('d-m-').(date('Y') + 543).')</div><div class="pv-tip">คำแนะนำสำหรับการพิมพ์ : ตั้งค่ากระดาษเป็น A4 และกำหนดระยะขอบของ Web Browser ดังนี้ &nbsp; &nbsp; '.$margin.'</div>'
        .'<script>function pvTab(n){document.querySelectorAll(".pv-page").forEach(function(p,i){p.classList.toggle("on",i===n)});document.querySelectorAll(".pv-tabs a").forEach(function(a,i){a.classList.toggle("on",i===n)})}</script></body></html>';
    exit;
}

/** ข้อมูลคะแนนของรายวิชา-ห้อง สำหรับหน้าพิมพ์ */
function rp_data(array $c, int $room): array
{
    $sql = 'SELECT G.`id` gid, G.`student_id`, G.`midterm`, G.`final`, G.`grade`, G.`number`, S.`student_id` code, S.`number` sno, U.`name` FROM {grade} G JOIN {student} S ON S.`id`=G.`student_id` JOIN {user} U ON U.`id`=G.`student_id` WHERE G.`course_id`=?';
    $p = [(int) $c['id']];
    if ($room) {
        $sql .= ' AND G.`room`=?';
        $p[] = $room;
    }
    $students = q($sql.' ORDER BY G.`room`, G.`number`, S.`student_id`', $p);
    $ts = task_scores(array_map(fn($s) => (int) $s['gid'], $students));
    $units = d_units($c);
    // รายการคะแนน: ภาระงานทุกชิ้น + กลางภาค + ปลายภาค
    $items = [];
    foreach ($units as $un => $u) {
        foreach ($u['tasks'] as $tn => $t) {
            $items[] = ['key' => $un.'.'.$tn, 'unit' => $un] + $t;
        }
    }
    $items[] = ['key' => 'mid'] + d_exam($c, 'mid');
    $items[] = ['key' => 'final'] + d_exam($c, 'final');
    $score = [];
    foreach ($students as $s) {
        $gid = (int) $s['gid'];
        [$u, $m] = array_pad(explode('|', (string) md_get('rec_split', $gid)), 2, '');
        $score[$gid] = ($ts[$gid] ?? []) + ['mid' => $m === '' ? null : (float) $m, 'final' => $s['final'] === null ? null : (float) $s['final'], 'unitOld' => $u === '' ? null : (float) $u];
    }
    return [$students, $units, $items, $score];
}

/** หัวรายงานแบบโลโก้ซ้าย 2 บรรทัด */
function rp_head(array $c, int $room, string $lead, string $tail): string
{
    $teacher = personnel_options()[$c['teacher_id']] ?? '';
    return '<div class="pv-h"><img src="'.h(rpt_logo()).'" alt=""/><div>'.h($lead).'<u>'.h($c['course_code']).'</u> ชื่อวิชา <u>'.h($c['course_name']).'</u> ระดับชั้น <u>'.h(class_room_short($c['class'], $room)).'</u> '.h($tail)
        .'<br>ครูผู้สอน <u>'.h($teacher).'</u> ภาคเรียนที่ <u>&nbsp;&nbsp;'.(int) $c['term'].'&nbsp;&nbsp;</u> ปีการศึกษา <u>'.(int) $c['year'].'</u></div></div>';
}

/** รายตัวชี้วัด / ราย มฐ. / รายสาระ: คะแนนถ่วงน้ำหนักจากภาระงาน กลางภาค ปลายภาค */
function rp_weighted(string $func, array $c, int $room): void
{
    $cfg = ['PrintByPoint' => ['รายงานคะแนนรายตัวชี้วัด/ผลการเรียนรู้', 'รายงานคะแนนรายวิชา', 'รายตัวชี้วัด/ผลการเรียนรู้', 12],
        'PrintByStandard' => ['รายงานตามมาตรฐานการเรียนรู้', 'สรุปคะแนนรายวิชา', 'ตามมาตรฐานการเรียนรู้', 15],
        'PrintByLearning' => ['รายงานคะแนนรายสาระการเรียนรู้', 'รายงานคะแนนรายวิชา', 'รายสาระการเรียนรู้', 8]][$func];
    [$students, , $items, $score] = rp_data($c, $room);
    $strands = d_strands($c);
    // คอลัมน์ [ชื่อ => น้ำหนักรวม] และส่วนของแต่ละรายการ
    $cols = [];
    $parts = [];
    foreach ($items as $it) {
        $list = $func === 'PrintByLearning' ? $it['strands'] : $it['pts'];
        foreach ($list as [$k, $w]) {
            $name = $func === 'PrintByLearning' ? (mb_substr((string) ($strands[(string) $k] ?? 'สาระที่ '.$k), 0, 40)) : ($func === 'PrintByStandard' ? preg_replace('#\s+[ปม]\.\d.*$#u', '', (string) $k) : (string) $k);
            $cols[$name] = ($cols[$name] ?? 0) + (float) $w;
            $parts[] = [$name, $it['key'], (float) $it['mark'], (float) $w];
        }
    }
    $names = array_keys($cols);
    $n = max($cfg[3], count($names));
    $th = '';
    $mx = '';
    for ($i = 0; $i < $n; $i++) {
        $th .= '<th style="width:46px;height:120px;vertical-align:bottom"><div class="vt">'.h($names[$i] ?? '').'</div></th>';
        $mx .= '<td class="c"><b>'.(isset($names[$i]) ? rp_num($cols[$names[$i]]) : '').'</b></td>';
    }
    $totalMax = array_sum($cols);
    $tr = '';
    $colVals = [];
    foreach ($students as $s) {
        $gid = (int) $s['gid'];
        $v = [];
        foreach ($parts as [$name, $key, $mark, $w]) {
            $sc = $score[$gid][$key] ?? null;
            if ($sc !== null && $mark > 0) {
                $v[$name] = ($v[$name] ?? 0) + $sc / $mark * $w;
            }
        }
        $np = preg_split('/\s+/u', trim((string) $s['name']), 2);
        $tr .= '<tr><td class="c">'.h($s['number'] ?: $s['sno']).'</td><td class="c">'.h($s['code']).'</td><td style="border-right:none;white-space:nowrap">'.h($np[0]).'</td><td style="border-left:none;white-space:nowrap">'.h($np[1] ?? '').'</td>';
        for ($i = 0; $i < $n; $i++) {
            $x = isset($names[$i]) ? ($v[$names[$i]] ?? null) : null;
            if (isset($names[$i])) {
                $colVals[$i][] = $x;
            }
            $tr .= '<td class="c">'.rp_num($x).'</td>';
        }
        $sum = $v ? array_sum($v) : null;
        $colVals['sum'][] = $sum;
        $tr .= '<td class="c">'.rp_num($sum).'</td></tr>';
    }
    $stat = [];
    foreach (array_keys($names) as $i) {
        $stat[$i] = rp_stats($colVals[$i] ?? [], (float) $cols[$names[$i]]);
    }
    $stat['sum'] = rp_stats($colVals['sum'] ?? [], $totalMax);
    $foot = '';
    foreach ([[0, 'คะแนนเฉลี่ย'], [4, 'ร้อยละของคะแนนเต็ม'], [5, 'S.D']] as [$k, $label]) {
        $foot .= '<tr><td></td><td></td><td colspan="2" class="r"><b>'.$label.'</b></td>';
        for ($i = 0; $i < $n; $i++) {
            $foot .= '<td class="c">'.($stat[$i][$k] ?? '').'</td>';
        }
        $foot .= '<td class="c">'.$stat['sum'][$k].'</td></tr>';
    }
    $body = '<div class="pv-wrap">'.rp_head($c, $room, $cfg[1], $cfg[2]).'<table class="pv"><tr><th rowspan="2" style="width:40px;vertical-align:bottom"><div class="vt">เลขที่</div></th><th rowspan="2" style="width:44px;vertical-align:bottom"><div class="vt">เลขประจำตัว</div></th>'
        .'<th rowspan="2" colspan="2">ชื่อ-นามสกุล</th>'.$th.'<th style="width:46px">รวม</th></tr><tr>'.$mx.'<td class="c"><b>'.rp_num($totalMax).'</b></td></tr>'.$tr.$foot.'</table></div>';
    rp_page($cfg[0], $func, $c, $room, $body);
}

/** รายหน่วย: หน้า 1 คะแนนรายหน่วย+กลางภาค / หน้า 2 เวลาเรียน รวมระหว่างภาค ปลายภาค รวม เกรด */
function rp_unit(array $c, int $room): void
{
    [$students, $units, , $score] = rp_data($c, $room);
    $mid = d_exam($c, 'mid');
    $fin = d_exam($c, 'final');
    [$rw] = score_ratio();
    $att = [];
    $sessions = 0;
    foreach (q('SELECT `student_id`, COUNT(*) n, SUM(`status` IN (\''.implode("','", \Attendance\Sheet\Model::PRESENT).'\')) p FROM {attendance} WHERE `course_id`=?'.($room ? ' AND `room`='.$room : '').' GROUP BY `student_id`', [(int) $c['id']]) as $r) {
        $att[(int) $r['student_id']] = (int) $r['p'];
        $sessions = max($sessions, (int) $r['n']);
    }
    $teacher = personnel_options()[$c['teacher_id']] ?? '';
    $head = '<div class="pv-hc"><img src="'.h(rpt_logo()).'" alt=""/>รายงานคะแนนการพัฒนานักเรียน ภาคเรียนที่ '.(int) $c['term'].' ปีการศึกษา '.(int) $c['year'].'<br>รายวิชา <u>'.h($c['course_code']).'</u> ชื่อวิชา <u>'.h($c['course_name']).'</u> ระดับชั้น <u>'.h(class_room_short($c['class'], $room)).'</u> ครูผู้สอน <u>'.h($teacher).'</u></div>';
    $unitMax = $units ? array_sum(array_map(fn($u) => (float) $u['mark'], $units)) : (float) $rw;
    // ค่าของแต่ละนักเรียน
    $rows = [];
    foreach ($students as $s) {
        $gid = (int) $s['gid'];
        $us = [];
        foreach ($units as $un => $u) {
            $has = false;
            $sum = 0.0;
            foreach (array_keys($u['tasks']) as $tn) {
                if (isset($score[$gid][$un.'.'.$tn])) {
                    $has = true;
                    $sum += (float) $score[$gid][$un.'.'.$tn];
                }
            }
            $us[$un] = $has ? $sum : null;
        }
        $m = $score[$gid]['mid'];
        $uSum = $units ? (array_filter($us, fn($x) => $x !== null) ? array_sum($us) : null) : $score[$gid]['unitOld'];
        $f = $score[$gid]['final'];
        $mt = $s['midterm'] === null ? null : (float) $s['midterm'];
        $rows[] = ['s' => $s, 'u' => $us, 'mid' => $m, 'uo' => $uSum, 'mt' => $mt, 'fin' => $f, 'tot' => $mt === null && $f === null ? null : (float) $mt + (float) $f, 'grade' => (string) $s['grade'], 'att' => $att[(int) $s['student_id']] ?? null];
    }
    $statRows = function (array $cols) use ($rows): string {
        $labels = ['คะแนนเฉลี่ย', 'ร้อยละที่ผ่านเกณฑ์', 'คะแนนต่ำสุด', 'คะแนนสูงสุด', 'ร้อยละของคะแนนเต็ม (E<sub>1</sub>/E<sub>2</sub>)', 'ค่าเบี่ยงเบนมาตรฐาน (S.D)', 'ค่าสัมประสิทธิ์การกระจาย (C.V)'];
        $st = [];
        foreach ($cols as $i => [$fn, $max]) {
            $st[$i] = $fn === null ? array_fill(0, 7, '') : rp_stats(array_map($fn, $rows), (float) $max);
        }
        $o = '';
        foreach ($labels as $k => $label) {
            $o .= '<tr class="st"><td class="c">'.($k ? '' : 'รวม').'</td><td class="c">'.($k ? '' : count($rows).' คน').'</td><td class="r">'.$label.'</td>';
            foreach (array_keys($cols) as $i) {
                $o .= '<td class="c'.($k === 6 && $st[$i][$k] !== '' ? ' cv' : '').'">'.$st[$i][$k].'</td>';
            }
            $o .= '</tr>';
        }
        return $o;
    };
    $nameCell = function ($s) {
        return '<td class="c">'.h($s['number'] ?: $s['sno']).'</td><td class="c">'.h($s['code']).'</td>';
    };
    $splitName = function ($s) {
        $np = preg_split('/\s+/u', trim((string) $s['name']), 2);
        return '<td style="white-space:nowrap"><span style="display:inline-block;min-width:190px">'.h($np[0]).'</span>'.h($np[1] ?? '').'</td>';
    };
    $headL = '<tr><th rowspan="2" style="width:40px;vertical-align:bottom"><div class="vt">เลขที่</div></th><th rowspan="2" style="width:44px;vertical-align:bottom"><div class="vt">เลขประจำตัว</div></th><th rowspan="2">ชื่อ-นามสกุล</th>';
    // หน้า 1
    $cols1 = [];
    $th1 = '';
    $mx1 = '';
    foreach ($units as $un => $u) {
        $th1 .= '<th style="width:120px">หน่วยที่ '.$un.'</th>';
        $mx1 .= '<th>'.rp_num($u['mark']).'</th>';
        $cols1[] = [fn($r) => $r['u'][$un], (float) $u['mark']];
    }
    if (!$units) {
        $th1 .= '<th style="width:120px">คะแนนระหว่างภาค</th>';
        $mx1 .= '<th>'.$rw.'</th>';
        $cols1[] = [fn($r) => $r['uo'], (float) $rw];
    }
    $th1 .= '<th style="width:120px">กลางภาค</th>';
    $mx1 .= '<th>'.rp_num($mid['mark']).'</th>';
    $cols1[] = [fn($r) => $r['mid'], (float) $mid['mark']];
    $tr1 = '';
    foreach ($rows as $r) {
        $tr1 .= '<tr>'.$nameCell($r['s']).$splitName($r['s']);
        foreach ($cols1 as [$fn]) {
            $tr1 .= '<td class="c">'.rp_num($fn($r)).'</td>';
        }
        $tr1 .= '</tr>';
    }
    // หน้า 2
    $cols2 = [[null, 0], [fn($r) => $r['mt'], $unitMax + (float) $mid['mark']], [fn($r) => $r['fin'], (float) $fin['mark']], [fn($r) => $r['tot'], 100], [null, 0]];
    $tr2 = '';
    foreach ($rows as $r) {
        $tr2 .= '<tr>'.$nameCell($r['s']).$splitName($r['s']).'<td class="c">'.($r['att'] ?? '').'</td><td class="c">'.rp_num($r['mt']).'</td><td class="c">'.rp_num($r['fin']).'</td><td class="c">'.rp_num($r['tot']).'</td><td class="c">'.h($r['grade']).'</td></tr>';
    }
    $p1 = '<table class="pv">'.$headL.$th1.'</tr><tr>'.$mx1.'</tr>'.$tr1.$statRows($cols1).'</table>';
    $p2 = '<table class="pv">'.$headL.'<th style="width:80px">เวลา<br>เรียน</th><th style="width:80px">รวมระหว่าง<br>ภาค</th><th style="width:80px">ปลายภาค</th><th style="width:80px">รวม</th><th style="width:80px">เกรด</th></tr>'
        .'<tr><th>'.$sessions.'</th><th>'.rp_num($unitMax + (float) $mid['mark']).'</th><th>'.rp_num($fin['mark']).'</th><th>100</th><th>4</th></tr>'.$tr2.$statRows($cols2).'</table>';
    $body = '<div class="pv-tabs"><a class="on" onclick="pvTab(0)">หน้า 1</a><a onclick="pvTab(1)">หน้า 2</a></div>'
        .'<div class="pv-page on"><div class="pv-wrap">'.$head.$p1.'</div></div><div class="pv-page"><div class="pv-wrap">'.$head.$p2.'</div></div>';
    rp_page('รายงานคะแนนพัฒนานักเรียน', 'PrintByUnit', $c, $room, $body, 'Left = 6 &nbsp; Right = 6 &nbsp; Top = 3 &nbsp; Bottom = 3');
}

/** ปุ่มพิมพ์จากหน้าบันทึกผลการเรียน */
function rpt_record_print(string $func): void
{
    $c = att_course();
    if (!$c) {
        rpt_print('ไม่สามารถพิมพ์ได้', '<div class="page"><h3>ไม่มีสิทธิ์พิมพ์เอกสารของรายวิชานี้</h3></div>');
    }
    $room = (int) ($_GET['RoomNum'] ?? 0);
    if ($func === 'PrintByUnit') {
        rp_unit($c, $room);
    }
    rp_weighted($func, $c, $room);
}

function act_task_study_record_PrintByPoint(): void
{
    rpt_record_print('PrintByPoint');
}

function act_task_study_record_PrintByUnit(): void
{
    rpt_record_print('PrintByUnit');
}

function act_task_study_record_PrintByStandard(): void
{
    rpt_record_print('PrintByStandard');
}

function act_task_study_record_PrintByLearning(): void
{
    rpt_record_print('PrintByLearning');
}
