<?php
/**
 * mis/app/pp6.php
 * สมุดรายงานผลการพัฒนาคุณภาพผู้เรียนรายบุคคล (ปพ.6) — รูปแบบตามระบบเดิม 7 แท็บ
 *  1 หน้าปก  2 ปกใน  3 เกณฑ์การจบหลักสูตร  4 ข้อมูลส่วนตัว  5 ความเจริญเติบโต
 *  6 ผลการเรียน (รายปี > ผลการเรียนรู้ / อ่านคิดเขียน+คุณลักษณะ+เวลาเรียน / ความคิดเห็น รายภาค)  7 ผลประเมินตลอดหลักสูตร
 * ข้อมูลเพิ่ม (ครูประจำชั้นบันทึกที่ "บันทึกข้อมูล ปพ.6"):
 *  md pp6p  r1=นักเรียน            ข้อมูลส่วนตัว บิดา มารดา ผู้ปกครอง ที่อยู่
 *  md pp6g  r1=นักเรียน r2=ชั้นปี  การเจริญเติบโต (พ.ค. ส.ค. พ.ย. ก.พ.)
 *  md pp6c  r1=นักเรียน r2=ปี/ภาค  ความคิดเห็นครูประจำชั้น (การเรียน อุปนิสัย พฤติกรรม สุขภาพ)
 */
defined('MIS') or exit;

const PP6_MONTHS = [5 => 'พฤษภาคม', 8 => 'สิงหาคม', 11 => 'พฤศจิกายน', 2 => 'กุมภาพันธ์'];
const PP6_COMMENT = ['learn' => 'การเรียน', 'habit' => 'อุปนิสัย', 'behave' => 'พฤติกรรม', 'health' => 'สุขภาพ'];
const PP6_PERSON = ['blood' => 'กลุ่มเลือด', 'race' => 'เชื้อชาติ', 'nation' => 'สัญชาติ', 'religion' => 'ศาสนา', 'father' => 'ชื่อ/สกุลบิดา', 'father_job' => 'อาชีพบิดา', 'father_st' => 'สถานภาพบิดา',
    'mother' => 'ชื่อ/สกุลมารดา', 'mother_job' => 'อาชีพมารดา', 'mother_st' => 'สถานภาพมารดา', 'guardian' => 'ชื่อ/สกุลผู้ปกครอง', 'guardian_job' => 'อาชีพผู้ปกครอง', 'guardian_rel' => 'เกี่ยวข้องเป็น',
    'house' => 'บ้านเลขที่', 'moo' => 'หมู่ที่', 'tambon' => 'ตำบล/แขวง', 'amphoe' => 'อำเภอ/เขต', 'province' => 'จังหวัด', 'zip' => 'รหัสไปรษณีย์', 'phone' => 'โทรศัพท์/มือถือ ติดต่อ'];
/** จำนวนตัวชี้วัดของคุณลักษณะอันพึงประสงค์ 8 ข้อ (คะแนนเต็มข้อละ 3 x จำนวนตัวชี้วัด) */
const PP6_CHAR_IND = [4, 2, 1, 2, 2, 2, 3, 2];

/* ---------------------------------------------------------------- ข้อมูล */

/** นักเรียนที่พิมพ์ได้ (ผู้บริหาร/วิชาการทุกคน ครูประจำชั้นเฉพาะห้องตนเอง) */
function pp6_student(): ?array
{
    $id = (int) ($_GET['StudentId'] ?? 0);
    $code = trim((string) ($_GET['StuId'] ?? ''));
    $s = q1('SELECT S.*, U.`name`, U.`sex`, U.`birthday`, U.`phone`, U.`active` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE '.($id ? 'S.`id`=?' : 'S.`student_id`=?'), [$id ?: $code]);
    if (!$s) {
        return null;
    }
    if (is_manager() || in_array('academic', user_modules(), true) || advisor_student((int) $s['id'])) {
        return $s;
    }
    return null;
}

/** [ระดับ ป./ม., ชั้นปี] จากชั้นเรียน */
function pp6_level(int $class): array
{
    $c = cat('class', $class);
    if (preg_match('/(ประถมศึกษา|มัธยมศึกษา)ปีที่\s*(\d)/u', $c, $m)) {
        return [$m[1] === 'ประถมศึกษา' ? 'ป' : 'ม', (int) $m[2]];
    }
    $s = class_short($class);
    return preg_match('/^(ป|ม)\.(\d)/u', $s, $m) ? [$m[1], (int) $m[2]] : ['ม', 0];
}

/** ช่วงชั้นของนักเรียน [ชื่อช่วงชั้น, [ชั้นปี...]] */
function pp6_band(int $class): array
{
    [$t, $n] = pp6_level($class);
    if ($t === 'ป') {
        return ['ระดับชั้นประถมศึกษา', [1, 2, 3, 4, 5, 6]];
    }
    return $n >= 4 ? ['ระดับชั้นมัธยมศึกษาตอนปลาย', [4, 5, 6]] : ['ระดับชั้นมัธยมศึกษาตอนต้น', [1, 2, 3]];
}

function pp6_level_name(string $t, int $n): string
{
    return ($t === 'ป' ? 'ประถมศึกษาปีที่ ' : 'มัธยมศึกษาปีที่ ').$n;
}

/** ปีการศึกษาที่มีผลการเรียนในช่วงชั้นเดียวกัน [ปี => ชั้นปี] */
function pp6_years(array $s): array
{
    [$t] = pp6_level((int) $s['class']);
    [, $levels] = pp6_band((int) $s['class']);
    $o = [];
    foreach (q('SELECT DISTINCT C.`year`, C.`class` FROM {grade} G JOIN {course} C ON C.`id`=G.`course_id` WHERE G.`student_id`=? ORDER BY C.`year`', [(int) $s['id']]) as $r) {
        [$tt, $n] = pp6_level((int) $r['class']);
        if ($tt === $t && in_array($n, $levels, true)) {
            $o[(int) $r['year']] = $n;
        }
    }
    if (!$o) {
        $o[cur_term()[0]] = pp6_level((int) $s['class'])[1];
    }
    ksort($o);
    return $o;
}

/** ครูประจำชั้นรายปี [[ปี, ชั้น, ครู]] */
function pp6_advisors(array $s): array
{
    $po = personnel_options();
    $rows = [];
    $hist = [];
    foreach (md_rows('stroom', (int) $s['id']) as $r) {
        $hist[(int) $r['r2']] = json_decode((string) $r['value'], true) ?: [];
    }
    $cy = cur_term()[0];
    $hist[$cy] = ['class' => (int) $s['class'], 'room' => (int) $s['room']];
    ksort($hist);
    foreach ($hist as $y => $h) {
        $room = (int) ($h['room'] ?? 0);
        $pids = $y === $cy ? (room_advisors()[$room] ?? []) : (json_decode((string) md_get('adv_hist', $y, $room), true) ?: []);
        [$t, $n] = pp6_level((int) ($h['class'] ?? 0));
        $rn = room_short($room);
        $rows[] = [$y, pp6_level_name($t, $n).(strpos($rn, '/') !== false ? substr($rn, strrpos($rn, '/')) : ''), implode(', ', array_filter(array_map(fn($p) => $po[$p] ?? '', $pids)))];
    }
    return $rows;
}

function pp6_person(array $s): array
{
    $d = json_decode((string) md_get('pp6p', (int) $s['id']), true) ?: [];
    $d += ['race' => 'ไทย', 'nation' => 'ไทย', 'religion' => 'พุทธ'];
    if (empty($d['guardian']) && !empty($s['parent'])) {
        $d['guardian'] = $s['parent'];
    }
    if (empty($d['phone'])) {
        $d['phone'] = (string) ($s['parent_phone'] ?: $s['phone']);
    }
    if (empty($d['house']) && empty($d['tambon']) && !empty($s['address'])) {
        $d['house'] = $s['address'];
    }
    return $d;
}

function pp6_idcard(string $id): string
{
    $d = preg_replace('/\D/', '', $id);
    return strlen($d) === 13 ? substr($d, 0, 1).'-'.substr($d, 1, 4).'-'.substr($d, 5, 5).'-'.substr($d, 10, 2).'-'.substr($d, 12) : $id;
}

function pp6_birth(?string $date): string
{
    if (!$date || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $date, $m) || (int) $m[1] < 1900) {
        return '';
    }
    $mon = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    return (int) $m[3].' '.$mon[(int) $m[2]].' '.substr((string) ((int) $m[1] + 543), 2);
}

/** ผลการเรียนของภาคเรียน */
function pp6_term_grades(int $sid, int $y, int $t): array
{
    return q('SELECT G.*, C.`course_code`, C.`course_name`, C.`credit`, C.`type` ctype, C.`period` FROM {grade} G JOIN {course} C ON C.`id`=G.`course_id` WHERE G.`student_id`=? AND C.`year`=? AND C.`term`=? ORDER BY C.`type`, C.`course_code`', [$sid, $y, $t]);
}

/** คะแนนคุณลักษณะ/อ่านคิดเขียนของภาคเรียน [ข้อ => ระดับ] */
function pp6_scores(string $kind, int $sid, string $yt): array
{
    $o = [];
    foreach (md_rows($kind, [$sid], $yt) as $r) {
        $o[(int) $r['r3']] = (int) $r['value'];
    }
    return $o;
}

/** เวลาเรียนรายภาค [เปิด, มา, ขาด, ลา, ป่วย] จากการเช็คชื่อรายวิชา (นับเป็นวัน) */
function pp6_days(int $sid, int $y, int $t): array
{
    $days = [];
    foreach (q('SELECT A.`date`, A.`status` FROM {attendance} A JOIN {course} C ON C.`id`=A.`course_id` WHERE A.`student_id`=? AND C.`year`=? AND C.`term`=?', [$sid, $y, $t]) as $r) {
        $days[$r['date']][] = $r['status'];
    }
    $c = [count($days), 0, 0, 0, 0];
    foreach ($days as $st) {
        if (array_intersect($st, ['present', 'late', 'activity', 'remedial'])) {
            $c[1]++;
        } elseif (in_array('sick', $st, true)) {
            $c[4]++;
        } elseif (in_array('leave', $st, true)) {
            $c[3]++;
        } else {
            $c[2]++;
        }
    }
    return $c;
}

/* ---------------------------------------------------------------- หน้า (โครงสร้าง/ระยะ/ขนาดตาม print_portrait.php ของระบบเดิม) */

/** แยกคำนำหน้าชื่อ [คำนำหน้า, ชื่อ, สกุล] */
function pp6_name_parts(string $name): array
{
    $name = trim(preg_replace('/\s+/u', ' ', $name));
    $pre = '';
    if (preg_match('/^(เด็กชาย|เด็กหญิง|นางสาว|นาย|นาง|ด\.ช\.|ด\.ญ\.)\s*(.*)$/u', $name, $m)) {
        [$pre, $name] = [$m[1], $m[2]];
    }
    $p = explode(' ', $name, 2);
    return [$pre, $p[0], $p[1] ?? ''];
}

/** ชื่อ-สกุลคั่นด้วยช่องว่างตามจำนวนที่กำหนด (ระบบเดิมใช้ &nbsp; คั่นชื่อกับสกุล) */
function pp6_nb(string $name, int $n = 2): string
{
    $name = trim(preg_replace('/\s+/u', ' ', $name));
    $i = mb_strpos($name, ' ');
    return $i === false ? h($name) : h(mb_substr($name, 0, $i)).str_repeat('&nbsp;', $n).h(mb_substr($name, $i + 1));
}

/** ช่องข้อความเส้นประแบบระบบเดิม (ข้อความลอยซ้อนกับเส้นประด้านล่าง) */
function pp6_fld(string $label, string $val, int $w, string $st = 'padding-top:10px', string $line = 'padding-top:10px', string $align = 'center', int $mt = 12): string
{
    return ($label !== '' ? '<span style="float:left;'.$st.'">'.$label.'</span> ' : '')
        .'<span style="float:left;'.$st.';width:'.$w.'px'.($align !== '' ? ';text-align:'.$align : '').'">'.$val.'</span> '
        .'<span style="float:left;'.$line.';width:'.$w.'px;margin-left:-'.$w.'px;margin-top:-'.$mt.'px;border-bottom:1.5px dotted #666666">&nbsp;</span> ';
}

/** ระยะขอบที่แนะนำ [top, right, bottom, left] (มม.) */
function pp6_page(array $s, string $sub, string $tabs, string $style, string $body, array $mg, string $size = 'A4'): void
{
    mis_headers();
    [$t, $r, $b, $l] = $mg;
    echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">'
        .'<html xmlns="http://www.w3.org/1999/xhtml"><head><title>ปพ.6 '.h($s['name']).'</title><meta http-equiv="Content-Type" content="text/html; charset=utf-8" />'
        .'<link rel="stylesheet" type="text/css" href="../css/main.css" /><link rel="stylesheet" type="text/css" href="../css/thaisansbold.css" /><link rel="stylesheet" type="text/css" href="../css/sarabun.css" />'
        .'<link rel="stylesheet" type="text/css" href="../css/tiny_style.css" /><link rel="stylesheet" type="text/css" href="../css/calendar_print_style.css" />'
        .'<style type="text/css">body { margin-left: 0px; margin-top: 0px; background: #ffffff; overflow-y:scroll; overflow-x:hidden; }</style></head>'
        .'<body bgcolor="#ffffff"><center><link rel="stylesheet" href="../css/tabs.css" type="text/css" />'
        .'<style type="text/css">body { margin:10px 10px; font-family:"TH Sarabun New"; } @media print{ .no-print{ display:none;} }'
        .'.dottedLine { display:inline-block;line-height:8pt;border-bottom: 1px dotted #666666; padding:0px 5px;white-space:nowrap;text-align:center;min-width:60px }'
        .'.dottedLine1 { display:inline-block;line-height:10pt;border-bottom: 1px dotted #666666; padding:0px 5px;white-space:nowrap;text-align:center;min-width:60px }'
        .'.dottedLine2 { display:inline-block;line-height:15pt;border-bottom: 1px dotted #666666; padding:0px 5px;white-space:nowrap;text-align:center;min-width:60px }'
        .'table.blacktable th { font-size:18pt; font-weight:bold; line-height:24pt!important; white-space:nowrap; border-right:1px solid #000; border-bottom:1px solid #000; text-align:center; }'
        .'table.blacktable td { font-size:18pt; font-weight:normal; line-height:24pt; white-space:nowrap; padding:0px 8px; }'
        .'input.adviser { font-family:"TH Sarabun New"; width:80%;font-size:24px; line-height:24px;padding:0px;text-align:center; border:none; }</style>'
        .'<div id="printhead" class="no-print"><div style="width:100%;display:table"><div style="display:table-row"><div style="display:table-cell;float:left"><img src="../images/preview_title.png" width="382" height="70" border="0" /></div>'
        .'<div style="display:table-cell;float:right;margin-right:10px;margin-top:20px"><a href="javascript:window.print();"><img src="../icon_tools/icon_mini_print.png" border="0" /></a></div></div></div></div>'
        .'<div id="printtopline" style="width:100%" class="no-print"><div style="border-bottom:1px solid #33CCCC">&nbsp;</div></div>'
        .'<div id="reporttitle" style="width:100%" class="no-print"><h1 style="font-size:30px">สมุดรายงานผลการพัฒนาคุณภาพผู้เรียนรายบุคคล (ปพ.๖)</h1><h1 style="font-size:24px">'.h($sub).'</h1></div>'
        .'<div id="printbottomline" style="width:100%" class="no-print"><div style="border-top:1px solid #33CCCC">&nbsp;</div></div>'
        .$tabs
        .'<style type="text/css">'.$style.' @page { size: '.$size.' portrait; margin: '.$t.'mm '.$r.'mm '.$b.'mm '.$l.'mm; }</style>'
        .$body
        .'<div id="printfooter" class="no-print noticeprint">&nbsp;&nbsp;คำแนะนำสำหรับการพิมพ์ : ตั้งค่ากระดาษเป็น A4 และกำหนดระยะขอบของ Web Browser ดังนี้ &nbsp;&nbsp;&nbsp;&nbsp;Left = '.$l.'&nbsp;&nbsp;&nbsp;Right = '.$r.'&nbsp;&nbsp;&nbsp;Top = '.$t.'&nbsp;&nbsp;&nbsp;Bottom = '.$b.'&nbsp;&nbsp; </div>'
        .'</center></body></html>';
    exit;
}

/** แถบปุ่มแท็บแบบระบบเดิม */
function pp6_tabbar(array $items, int $h1, string $first, string $next, string $wrapStyle): string
{
    $o = '<div id="printtabs" class="no-print" style="'.$wrapStyle.'"><div id="tabs_area" style="display: table-row">';
    $i = 0;
    foreach ($items as [$url, $label, $on]) {
        $o .= '<div style="display: table-cell;float:left;'.($i++ ? $next : $first).'"><a href="'.h($url).'" class="button small '.($on ? 'green' : 'gray').'" style="padding:3px 15px 3px 15px"><h1 style="font-size:'.$h1.'px">'.h($label).'</h1></a></div>';
    }
    return $o.'</div>';
}

function pp6_line(): string
{
    return '<div style="width:984px;text-align:center"><div id="line1" style="display: table-row;width:984px;margin-top:-2px;border-top:2px #00BBBB solid;z-index:2;position:absolute">&nbsp;</div></div>';
}

/** ลายเซ็นครูประจำชั้น/ผู้อำนวยการ ท้ายหน้าผลการเรียน */
function pp6_sign_learn(string $adv, string $dir): string
{
    return '<div><table width="100%" border="0" cellpadding="0" cellspacing="0" style="font-size:15pt;line-height:22pt;margin:20px 0px 0px 0px;font-weight:bold;text-align:center"><tr>'
        .'<td align="left">ลงชื่อ<span class="dottedLine" style="width:200px">&nbsp;&nbsp;</span>ครูประจำชั้น</td><td style="width:2%">&nbsp;</td><td align="left">ลงชื่อ<span class="dottedLine" style="width:200px">&nbsp;&nbsp;</span>ผู้อำนวยการโรงเรียน</td></tr>'
        .'<tr><td align="left" style="padding-left:30px">(<span class="dottedLine" style="width:200px">&nbsp;'.h($adv).'&nbsp;</span>)</td><td>&nbsp;</td><td align="left" style="padding-left:30px">(<span class="dottedLine" style="width:200px">&nbsp;'.h($dir).'&nbsp;</span>)</td></tr></table></div>';
}

function act_task_report_pp6_PrintMattayomReport(): void
{
    act_task_report_pp6_Print();
}

function act_task_report_pp6_Print(): void
{
    $s = pp6_student();
    if (!$s) {
        rpt_print('ไม่สามารถพิมพ์ได้', '<div class="page"><h3>ไม่พบนักเรียน หรือไม่มีสิทธิ์พิมพ์ ปพ.6 ของนักเรียนคนนี้</h3></div>');
    }
    $sid = (int) $s['id'];
    $tab = max(1, min(7, (int) ($_GET['Tab'] ?? 1)));
    [$band, $levels] = pp6_band((int) $s['class']);
    [$lt, $ln] = pp6_level((int) $s['class']);
    $cy = cur_term()[0];
    $rn = room_short((int) $s['room']);
    $roomNo = strpos($rn, '/') !== false ? substr($rn, strrpos($rn, '/') + 1) : $rn;
    $school = cfg('school_name', 'โรงเรียนดรุณราษฎร์วิทยา');
    $base = 'index.php?module=task&option=report_pp6&func=PrintMattayomReport&StudentId='.$sid;
    $years = pp6_years($s);
    $year = isset($years[(int) ($_GET['Year'] ?? 0)]) ? (int) $_GET['Year'] : array_key_last($years);
    $stab = max(1, min(6, (int) ($_GET['subtab'] ?? 1)));
    $term = $stab > 3 ? 2 : 1;
    $items = [];
    foreach ([1 => 'หน้าปก', 2 => 'ปกใน', 3 => 'เกณฑ์การจบหลักสูตร', 4 => 'ข้อมูลส่วนตัว', 5 => 'ความเจริญเติบโต', 6 => 'ผลการเรียน', 7 => 'ผลประเมินตลอดหลักสูตร'] as $k => $l) {
        $items[] = [$base.($k === 6 ? '&Term='.cur_term()[1].'&Year='.$cy : '').'&Tab='.$k, $l, $k === $tab];
    }
    $tabs = pp6_tabbar($items, 20, 'margin-top:10px', 'margin-top:10px;margin-left:5px', 'width:100%;font-size:14px;text-align:left').pp6_line().'<div>&nbsp;</div></div>';
    $sub = $s['name'].' ระดับชั้น'.pp6_level_name($lt, $ln).'/'.$roomNo.' ปีการศึกษา '.$cy;
    $p = pp6_person($s);
    $logo = rpt_logo();
    $dir = (string) rpt_signer('ผู้อำนวยการ');
    $adv = pp6_advisors($s);
    $advName = (string) (end($adv)[2] ?? '');
    [$pre, $fn, $sn] = pp6_name_parts((string) $s['name']);
    $style = '';
    $size = 'A4';
    $body = '';
    if ($tab === 1) {
        $mg = [10, 5, 5, 20];
        $tr = '';
        $in = fn($st = '') => '<input class="adviser"'.($st ? ' style="'.$st.'"' : '').' />';
        for ($i = 0; $i < max(6, count($adv)); $i++) {
            [$y, $lv, $t] = $adv[$i] ?? ['', '', ''];
            $tr .= '<tr><td align="center" valign="middle" style="font-size:24px;height:35px">'.($y !== '' ? h((string) $y) : $in()).'</td>'
                .'<td align="center" valign="middle" style="font-size:24px">'.($lv !== '' ? h($lv) : $in()).'</td>'
                .'<td align="left" valign="middle" style="font-size:24px">'.($t !== '' ? pp6_nb($t) : $in('text-align:left')).'</td></tr>';
        }
        $nameLine = '<span style="float:left;padding-top:10px">ชื่อ-สกุล</span> <span style="float:left;padding-top:10px;width:420px">&nbsp;'.h($pre.$fn).'&nbsp;&nbsp;&nbsp;'.h($sn).'&nbsp;</span> '
            .'<span style="float:left;padding-top:11px;width:420px;margin-left:-420px;margin-top:-17px;border-bottom:1.5px dotted #666666">&nbsp;</span> ';
        $f1 = fn($label, $val, $w, $pad) => pp6_fld($label, str_repeat('&nbsp;', $pad).h($val).'&nbsp;', $w, 'padding-top:10px', 'padding-top:11px', 'left', 17);
        $body = '<div id="printcontent" style="display:table;width:100%;font-family:TH Sarabun New;font-size:14px;text-align:left">'
            .'<div style="display: table-row"><div style="display: table-cell;text-align:center"><img src="'.h($logo).'" border="0" align="middle" style="text-align:center;height:100px" /></div>'
            .'<div style="display: table-cell;width:40px;vertical-align:top;white-space:nowrap"><span style="font-weight:bold;font-size:24px"> ปพ.6 </span></div></div>'
            .'<div style="font-weight:bold;font-size:44px;text-align:center"> รายงานผลการพัฒนาคุณภาพผู้เรียนรายบุคคล </div>'
            .'<div style="font-weight:bold;font-size:34px;text-align:center"> '.h($band).' </div>'
            .'<div style="font-weight:bold;font-size:34px;text-align:center;line-height:35px"> '.h($school.' อำเภอ'.cfg('addr_amphoe', cfg('school_amphoe', 'แม่สาย')).' จังหวัด'.cfg('addr_province', cfg('school_province', 'เชียงราย'))).' </div>'
            .'<center><div style="width:770px;font-weight:bold;font-size:34px;margin:10px 0px;border:solid 0px #000;padding:15px 10px;border-radius:15px"><span style="display:table">'
            .'<div>'.$nameLine.pp6_fld('เลขประจำตัว', '&nbsp;'.h((string) $s['student_id']).'&nbsp;', 88, 'padding-top:10px', 'padding-top:11px', 'center', 17).'</div>'
            .'<div>'.$f1('เลขประจำตัวประชาชน', pp6_idcard((string) $s['id_card']), 490, 9).'</div>'
            .'<div>'.$f1('ชื่อ-ชื่อสกุลบิดา', (string) ($p['father'] ?? ''), 560, 13).'</div>'
            .'<div>'.$f1('ชื่อ-ชื่อสกุลมารดา', (string) ($p['mother'] ?? ''), 540, 10).'</div>'
            .'<div>'.$f1('ชื่อ-ชื่อสกุลผู้ปกครอง', (string) ($p['guardian'] ?? ''), 510, 6).'</div>'
            .'</span></div></center>'
            .'<div style="font-weight:bold;font-size:30px;text-align:center;margin:40px 0px 0px 0px"> ครูประจำชั้น </div>'
            .'<center><div style="width:775px;font-weight:bold;font-size:28px"><table width="90%" border="0" cellspacing="0" cellpadding="0" class="blacktable"><tr>'
            .'<td align="center" valign="middle" style="height:35px;font-size:24px;width:18%;font-weight:bold">ปีการศึกษา</td><td align="center" valign="middle" style="font-size:24px;width:25%;font-weight:bold">ระดับชั้น</td>'
            .'<td align="center" valign="middle" style="font-size:24px;font-weight:bold">ครูประจำชั้น</td></tr>'.$tr.'</table></div></center>'
            .'<center><div style="width:775px;font-weight:bold;font-size:28px;margin:10px 0px"></div></center><center><div style="width:775px;font-weight:bold;font-size:28px;margin:10px 0px"></div></center></div>';
    } elseif ($tab === 2) {
        $mg = [15, 10, 15, 15];
        $size = 'legal';
        $style = '.print_area { width:21cm;font-family:TH Sarabun New;font-size:20pt;border:0px dashed #ccc;padding:0.2cm; }';
        $sp = str_repeat('&nbsp;', 8);
        $body = '<div id="printcontent" style="display:table;width:21cm;font-family:TH Sarabun New;font-size:14px;text-align:left"><div style="display: table-row">'
            .'<table border="0" cellpadding="0" cellspacing="0" class="even" style="width:100%;font-size:30px;font-family:TH Sarabun New;text-shadow:#CCCCCC;word-spacing:inherit;color: #000;text-indent:inherit;text-align:left;border:1px solid #000;padding:30px 40px">'
            .'<tr><td align="left" valign="bottom"><div class="title_24 bold" style="padding-top:10px;font-size:30px">เรียน ท่านผู้ปกครอง</div></td></tr>'
            .'<tr><td colspan="2" style="padding-top:20px; text-align:justify">'.$sp.'โรงเรียนขอเรียนว่า โรงเรียนมีความรัก และห่วงใยนักเรียน ซึ่งเป็นบุตรหลานของท่าน และปรารถนาให้ทุกคนประสบความสำเร็จในการเรียน มีความเจริญก้าวหน้าในการดำเนินชีวิต ด้วยเหตุนี้จึงใคร่ขอความร่วมมือจากท่านได้โปรดติดตามดูแลความประพฤติ และการเรียนของนักเรียนขณะที่อยู่บ้านพร้อมกับให้ความช่วยเหลือสนับสนุนการเรียนในด้านต่างๆ ตามความสามารถทั้งนี้โรงเรียนจะติดตามเอาใจใส่นักเรียนในขณะที่อยู่ในโรงเรียนเป็นอย่างดี เช่นเดียวกัน</td></tr>'
            .'<tr><td colspan="2" style="padding-top:20px; text-align:justify">'.$sp.'แบบรายงานผลการพัฒนาคุณภาพผู้เรียนเล่มนี้ เป็นเอกสารสำหรับสื่อสารระหว่างท่าน กับโรงเรียน เพื่อแจ้งผลการเรียนและข้อมูลบางอย่างของนักเรียนทั้งขณะอยู่ที่โรงเรียน และที่บ้านให้แต่ละฝ่ายได้รับทราบซึ่งจะช่วยให้เกิดความเข้าใจในตัวนักเรียนอย่างถูกต้อง อันจะเป็นประโยชน์ต่อการปรับปรุงแก้ไข หรือให้การสนับสนุนส่งเสริมนักเรียนต่อไป ฉะนั้นเมื่อท่านได้รับเอกสารนี้แล้ว ขอความกรุณาได้โปรดสละเวลาพิจารณาดูผลการเรียน เวลาเรียน และข้อเสนอแนะต่าง ๆ พร้อมกับให้ความเห็นเกี่ยวกับนักเรียนขณะอยู่ที่บ้านด้วย จึงเรียนมาเพื่อขอความร่วมมือ และขอขอบคุณมา ณ โอกาสนี้</td></tr>'
            .'<tr><td colspan="2" style="text-align:right;padding-top:40px"><div style="float:right;width:400px;text-align:center">ขอแสดงความนับถือ</div></td></tr>'
            .'<tr><td colspan="2" style="text-align:right;padding-top:70px"><div style="float:right;width:400px;text-align:center"><span style="float:left;padding-left:40px">(</span> '
            .'<span style="float:left;width:300px">&nbsp;'.($dir !== '' ? pp6_nb($dir) : '').'&nbsp;</span> <span style="float:left;width:300px;margin-left:-300px;margin-top:-10px;border-bottom:1.5px dotted #666666">&nbsp;</span> <span style="float:left">)</span></div></td></tr>'
            .'<tr><td colspan="2" style="text-align:right;padding-top:20px"><div style="float:right;width:400px;text-align:center"> ผู้อำนวยการ'.h($school).' </div></td></tr>'
            .'</table> &nbsp; &nbsp; </div></div>';
    } elseif ($tab === 3) {
        $mg = [5, 7, 5, 12];
        $size = 'legal';
        $tbl = function (array $head, array $rows, bool $top = false): string {
            $o = '<table width="100%" border="0" cellpadding="0" cellspacing="0" bordercolor="#000000" style="border:solid 0px #000000"><tr>';
            foreach ($head as $i => $x) {
                $o .= count($head) === 2 ? '<td width="'.($i ? '65%' : '35%').'" class="bold">'.$x.'</td>' : '<td'.($i < 2 ? ' style="width:35%"' : '').' class="bold">'.$x.'</td>';
            }
            $o .= '</tr>';
            foreach ($rows as $r) {
                $o .= '<tr>'.implode('', array_map(fn($x) => $top ? '<td align="left" valign="top">'.$x.'</td>' : '<td>'.$x.'</td>', $r)).'</tr>';
            }
            return $o.'</table>';
        };
        $lv4 = [['&quot;ดีเยี่ยม&quot;', 'ร้อยละ 80-100', 'มีผลการประเมินอยู่ในระดับ ๓'], ['&quot;ดี&quot;', 'ร้อยละ 60-79', 'มีผลการประเมินอยู่ในระดับ ๒'], ['&quot;ผ่านเกณฑ์การประเมิน&quot;', 'ร้อยละ 50-59', 'มีผลการประเมินอยู่ในระดับ ๑'], ['&quot;ไม่ผ่านเกณฑ์การประเมิน&quot;', 'ร้อยละ 0-49', 'มีผลการประเมินอยู่ในระดับ ๐']];
        $hd = fn($t) => '<tr><td colspan="2" style="font-size:28px;padding-top:15px" class="bold">'.$t.'</td></tr>';
        $li = fn($t, $x = '') => '<tr><td'.$x.' colspan="2" style="text-align:justify">'.$t.'</td></tr>';
        $body = '<div id="printcontent" style="width:21cm;font-family:TH Sarabun New;border:0px dotted #000;"><div style="width:21cm;text-align:right;white-space:nowrap;vertical-align:top"><span class="circle" style="float:right;width:30px;height:30px;text-align:center;font-size:18pt;display:none">1</span></div><div>'
            .'<table border="0" cellpadding="0" cellspacing="0" style="width:21cm;font-size:18pt;font-family:TH Sarabun New;text-shadow:#CCCCCC;word-spacing:inherit;color: #000;text-indent:inherit;text-align:left">'
            .'<tr><td align="center" valign="bottom"><div style="border-top:double;width:300px;padding-bottom:5px"></div><span class="title_34 bold">เกณฑ์การจบหลักสูตร</span><div style="border-top:double;width:300px;margin-top:5px"></div><div class="title_24 bold" style="padding-top:10px;font-size:30px">ตามหลักสูตรแกนกลางการศึกษาขั้นพื้นฐาน พุทธศักราช 2551</div></td></tr>'
            .'<tr><td colspan="2" style="padding-top:10px;text-align:justify">1. ผู้เรียนเรียนรายวิชาพื้นฐานและรายวิชาเพิ่มเติม/กิจกรรม ตามโครงสร้างเวลาเรียนที่หลักสูตรแกนกลางการศึกษาขั้นพื้นฐาน<br />&nbsp;&nbsp;&nbsp;&nbsp;กำหนด</td></tr>'
            .$li('2. ผู้เรียนต้องมี ผลการประเมิน รายวิชาพื้นฐาน ผ่านเกณฑ์การประเมินตามที่สถานศึกษากำหนด')
            .$li('3. ผู้เรียนมี ผลการประเมิน การอ่าน คิดวิเคราะห์และเขียน &nbsp;&nbsp;&nbsp;ในระดับผ่านเกณฑ์การประเมิน ตามที่สถานศึกษากำหนด')
            .$li('4. ผู้เรียนมี ผลการประเมิน คุณลักษณะอันพึงประสงค์ ในระดับผ่านเกณฑ์การประเมินตามที่สถานศึกษากำหนด', ' height="19"')
            .$li('5. ผู้เรียนเข้าร่วมกิจกรรมพัฒนาผู้เรียน และมีผลการประเมินผ่านเกณฑ์ตามที่สถานศึกษากำหนด')
            .$hd('การให้ระดับผลการเรียนในแต่ละกลุ่มสาระ แบ่งเป็น 8 ระดับ ดังนี้')
            .'<tr><td colspan="2" style="padding-left:20px">'.$tbl(['ระดับผลการเรียน', 'คะแนนร้อยละ', 'ความหมายของผลการประเมิน'], [['๔', 'ร้อยละ 80-100', 'ดีเยี่ยม'], ['๓.๕', 'ร้อยละ 75-79', 'ดีมาก'], ['๓', 'ร้อยละ 70-74', 'ดี'], ['๒.๕', 'ร้อยละ 65-69', 'ค่อนข้างดี'], ['๒', 'ร้อยละ 60-64', 'ปานกลาง'], ['๑.๕', 'ร้อยละ 55-59', 'พอใช้'], ['๑', 'ร้อยละ 50-54', 'ผ่านเกณฑ์ขั้นต่ำ'], ['๐', 'ร้อยละ 0-49', 'ต่ำกว่าเกณฑ์']]).'</td></tr>'
            .$hd('ระดับผลการประเมินการอ่าน คิดวิเคราะห์ และเขียน').'<tr><td colspan="2" style="padding-left:20px">'.$tbl(['ระดับผลการประเมิน', 'คะแนนร้อยละ', 'ระดับคุณภาพ'], $lv4, true).'</td></tr>'
            .$hd('ระดับผลการประเมินคุณลักษณะอันพึงประสงค์').'<tr><td colspan="2" style="padding-left:20px">'.$tbl(['ระดับผลการประเมิน', 'คะแนนร้อยละ', 'ระดับคุณภาพ'], $lv4, true).'</td></tr>'
            .$hd('ระดับผลการประเมินกิจกรรมพัฒนาผู้เรียน').'<tr><td colspan="2" style="padding-left:20px">ผู้เรียนจะต้องมีเวลาเข้าร่วมกิจกรรมร้อยละ ๘๐ ขึ้นไป โดยมีผลการตัดสิน ดังนี้</td></tr>'
            .'<tr><td colspan="2" style="padding-left:20px">'.$tbl(['ระดับผลการประเมิน', 'ความหมายของผลการประเมิน'], [['&quot;ผ่าน&quot;', 'ผู้เรียนมีเวลาเข้าร่วมกิจกรรมครบ และปฏิบัติกิจกรรมและผลงานตามเกณฑ์ที่สถานศึกษากำหนด'], ['&quot;ไม่ผ่าน&quot;', 'ผู้เรียนมีเวลาเข้าร่วมกิจกรรมไม่ครบ หรือไม่ผ่านการปฏิบัติกิจกรรมตามเกณฑ์ที่สถานศึกษากำหนด']], true).'</td></tr>'
            .'</table></div></div>';
    } elseif ($tab === 4) {
        $mg = [5, 11, 5, 9];
        $style = '.stu_img { margin:10px 0px 5px 0px; } div.havepic { text-align:center; } div.nopic { text-align:center; } div.subject { font-weight:bold;text-align:left;margin-top:10px;margin-bottom:5px } div.content { margin-left:0.4cm;text-align:left } div.content_left { margin-left:0px;text-align:left } div.content_title { margin-left:0.4cm;text-align:left;font-weight:bold;margin-bottom:5px } div.content_col_left { float:left;width:320px;margin-left:0.4cm;text-align:left;white-space:nowrap } div.content_col_right { float:left;width:320px;margin-left:0.4cm;text-align:left;white-space:nowrap } div.sign_left { float:left;width:225px;text-align:left;white-space:nowrap } div.sign_right { float:left;width:225px;text-align:left;white-space:nowrap;margin-left:100px } div.sign { display:table-row } div.footnotes { page-break-after:always; } .dottedLine_14pt { display:inline-block;line-height:10px;border-bottom: 1px dotted #666666; padding:0px 15px; } .dottedLine_18pt { display:inline-block;line-height:14pt;border-bottom: 1px dotted #666666; padding:0px 15px; } table.blacktable th { text-align:center; border-right:1px solid #000; border-bottom:1px solid #000; white-space:nowrap; padding:0px 10px;vertical-align:middle;line-height:0.85cm; } table.blacktable td { text-align:left; height:20px; padding:0px 10px;vertical-align:middle;line-height:0.85cm; } .print_area { width:21cm;font-family:TH Sarabun New;font-size:20pt;border:0px dashed #ccc;padding:0.2cm; } input#single_header { font-size: 13px!important }';
        $v = fn($x) => '&nbsp;'.h((string) $x).'&nbsp;';
        $p10 = fn($l, $x, $w) => pp6_fld($l, $v($x), $w, 'padding-top:10px', 'padding-top:10px');
        $p15 = fn($l, $x, $w) => pp6_fld($l, $v($x), $w, 'padding-top:15px', 'padding-top:15px');
        $row = fn($inner, $m = 10) => '<div style="margin:'.$m.'px 0px 0px 0px"><span style="display:table"><span style="display:table-row">'.$inner.'</span></span></div>';
        $body = '<div id="printcontent" style="width:21cm;font-family:TH Sarabun New;border:0px dotted #000;"><div style="width:21cm;text-align:right;white-space:nowrap;vertical-align:top"><span class="circle" style="float:right;width:30px;height:30px;text-align:center;font-size:18pt;display:none">2</span></div><div>'
            .'<table border="0" cellpadding="0" cellspacing="0" class="even" style="margin-top:10px;width:21cm;font-size:20pt;font-family:TH Sarabun New;text-shadow:#CCCCCC;word-spacing:inherit;color: #000;text-indent:inherit;text-align:left">'
            .'<tr><td style="width:3cm">&nbsp;</td><td align="center" valign="bottom"><div style="font-weight:bold;font-size:38px;margin:60px 0px 20px 0px;text-align:center"><span style="border:solid 2px #000;padding:5px 20px;border-radius:15px">ข้อมูลส่วนตัวผู้เรียน</span></div></td>'
            .'<td rowspan="2" style="width:3cm"><img src="'.h(avatar_url($sid)).'" border="0" style="border:solid 1px #eee;padding:5px 5px;margin-top:60px;width:2.5cm;height:3.75cm;object-fit:cover" /></td></tr>'
            .'<tr><td style="width:3cm">&nbsp;</td><td>&nbsp;</td></tr><tr><td colspan="3">'
            .'<div style="margin:0px 0px 0px 0px;width:21cm"><span style="display:table"><span style="display:table-row"><span style="display:table-cell;vertical-align:top;text-align:left"><div style="display:table">'
            .'<div style="display:table-row">'.pp6_fld('ชื่อ-นามสกุล', '&nbsp;'.h($pre.$fn).'&nbsp;&nbsp;'.h($sn).'&nbsp;', 450, 'padding-top:10px', 'padding-top:10px').pp6_fld('เลขประจำตัว', $v($s['student_id']), 150, 'padding-top:10px', 'padding-top:10px').'</div>'
            .'<div style="display:table-row">'.$p15('เลขประจำตัวประชาชน', pp6_idcard((string) $s['id_card']), 280).$p15('วัน เดือน ปีเกิด', pp6_birth($s['birthday'] ?? null), 230).'</div>'
            .'<div style="display:table-row">'.$p15('กลุ่มเลือด', $p['blood'] ?? '', 90).$p15('เชื้อชาติ', $p['race'], 170).$p15('สัญชาติ', $p['nation'], 160).$p15('ศาสนา', $p['religion'], 135).'</div>'
            .'</div></span></span></span></div>'
            .$row($p10('ชื่อ/สกุลบิดา', $p['father'] ?? '', 265).$p10('อาชีพ', $p['father_job'] ?? '', 190).$p10('สถานภาพ', $p['father_st'] ?? '', 125))
            .$row($p10('ชื่อ/สกุลมารดา', $p['mother'] ?? '', 255).$p10('อาชีพ', $p['mother_job'] ?? '', 185).$p10('สถานภาพ', $p['mother_st'] ?? '', 125), 12)
            .$row($p10('ชื่อ/สกุลผู้ปกครอง', $p['guardian'] ?? '', 220).$p10('อาชีพ', $p['guardian_job'] ?? '', 195).$p10('เกี่ยวข้องเป็น', $p['guardian_rel'] ?? '', 105))
            .$row($p10('ที่อยู่ปัจจุบัน บ้านเลขที่', $p['house'] ?? '', 100).$p10('หมู่ที่', $p['moo'] ?? '', 40).$p10('ตำบล/แขวง', $p['tambon'] ?? '', 140).$p10('อำเภอ/เขต', $p['amphoe'] ?? '', 145))
            .$row($p10('จังหวัด', $p['province'] ?? '', 150).$p10('รหัสไปรษณีย์', $p['zip'] ?? '', 90).$p10('โทรศัพท์/มือถือ ติดต่อ', $p['phone'] ?? '', 240))
            .'</td></tr></table></div></div>';
    } elseif ($tab === 5) {
        $mg = [5, 7, 5, 15];
        $style = 'table.blacktable th { font-size:15pt; font-weight:bold; line-height:20pt!important; white-space:nowrap; border-right:1px solid #000; border-bottom:1px solid #000; text-align:center; } table.blacktable td { font-size:15pt; font-weight:normal; line-height:18pt; white-space:nowrap; padding:2px 8px; }';
        $o = '<div id="printcontent" style="width:21cm;font-family:TH Sarabun New;font-size:14px;text-align:left"><div style="width:100%;text-align:right;white-space:nowrap;vertical-align:top"><span class="circle" style="float:right;width:30px;height:30px;text-align:center;font-size:18pt;display:none">3</span></div>'
            .'<div style="font-weight:bold;font-size:32px;margin:0px 0px;text-align:center">บันทึกการเจริญเติบโต</div>';
        foreach ($levels as $n) {
            $g = json_decode((string) md_get('pp6g', $sid, $n), true) ?: [];
            $o .= '<div style="font-size:18pt;margin:10px 0px 0px 0px;font-weight:bold;">ชั้น'.h(pp6_level_name($lt, $n)).' </div><div style="font-size:20px;margin:0px 0px 0px 0px"><table border="0" cellpadding="0" cellspacing="0" class="blacktable" style="width:100%"><tr><th style="border-radius:0px;width:20%">&nbsp;</th>';
            foreach (PP6_MONTHS as $l) {
                $o .= '<th style="border-radius:0px;width:20%">'.$l.'</th>';
            }
            $o .= '</tr>';
            foreach (['age' => 'อายุ', 'weight' => 'น้ำหนัก', 'height' => 'ส่วนสูง'] as $k => $l) {
                $o .= '<tr><th align="center" style="background-color:#FFFFFF">'.$l.'</th>';
                foreach (array_keys(PP6_MONTHS) as $m) {
                    $o .= '<td style="background-color:#FFFFFF">'.(($g[$m][$k] ?? '') !== '' ? '&nbsp;'.h((string) $g[$m][$k]).'&nbsp;' : ($k === 'height' ? '&nbsp;' : '&nbsp;&nbsp;')).'</td>';
                }
                $o .= '</tr>';
            }
            $o .= '</table></div>';
        }
        $ref = [['ชาย', [15.5, 17, 18, 20, 22, 24, 27, 29, 33, 37, 43, 46], [106, 111, 116, 120, 125, 125, 135, 140, 145, 153, 159, 163]], ['หญิง', [15, 17, 18, 20, 23, 25, 28, 32, 37, 40, 43, 44], [106, 110, 115, 120, 126, 126, 137, 143, 148, 152, 153, 154]]];
        $o .= '<div style="font-weight:bold;font-size:24px;margin-top:10px;text-align:center">ตารางแสดงน้ำหนักและส่วนสูงของเด็กไทยโดยเฉลี่ย</div><table border="0" cellspacing="0" cellpadding="0" class="blacktable" style="width:100%"><tr><th colspan="2" style="text-align:center;padding:3px 0px">อายุ/ปี</th>'
            .implode('', array_map(fn($a) => '<th style="text-align:center">'.$a.'</th>', range(5, 16))).'</tr>';
        foreach ([1 => 'น้ำหนัก<br />(กก.)', 2 => 'ส่วนสูง<br /> (ซม.)'] as $i => $l) {
            foreach ($ref as $j => $r) {
                $o .= '<tr>'.($j === 0 ? '<th rowspan="2" align="center">'.$l.'</th>' : '').'<th align="center" style="height:30px">'.$r[0].'</th>'.implode('', array_map(fn($v) => '<td style="text-align:center">'.$v.'</td>', $r[$i])).'</tr>';
            }
        }
        $body = $o.'</table></div>';
    } elseif ($tab === 6) {
        $btn = fn($url, $label, $on, $st) => '<div style="display: table-cell;float:left;'.$st.'"><a href="'.h($url).'" class="button small '.($on ? 'green' : 'gray').'" style="padding:3px 15px 3px 15px"><h1 style="font-size:21px">'.h($label).'</h1></a></div>';
        $yb = '';
        foreach ($years as $y => $n) {
            $yb .= $btn($base.'&Tab=6&Year='.$y.'&Page=1', 'ปีการศึกษา '.$y, $y === $year, 'margin-top:3px').'<div style="display: table-cell;float:left;margin-top:3px;width:5px">&nbsp;</div>';
        }
        $sbb = '';
        $i = 0;
        foreach ([1 => 'ผลการเรียนรู้ 1/'.$year, 2 => 'อ่านคิดเขียน 1', 3 => 'คห. 1/'.$year, 4 => 'ผลการเรียนรู้ 2/'.$year, 5 => 'อ่านคิดเขียน 2', 6 => 'คห. 2/'.$year] as $k => $l) {
            $sbb .= $btn($base.'&Tab=6&Page=&Year='.$year.'&Term='.($k > 3 ? 2 : 1).'&subtab='.$k, $l, $k === $stab, $i++ ? 'margin-top:3px;margin-left:3px' : 'margin-top:3px');
        }
        $tabs .= '<div id="printsubtabs" style="width:100%" class="no-print"><div id="printtabs" class="no-print" style="display:table;width:100%;font-size:14px;text-align:left;margin-top:-15px">'
            .'<div id="tabs_area" style="display: table-row">'.$yb.'</div>'.pp6_line().'<div><div id="subtabs_area" style="display: table-row">'.$sbb.'</div>'.pp6_line().'</div></div></div><div class="no-print">&nbsp;</div>';
        $lvn = $years[$year];
        $yt = $year.'/'.$term;
        $who = h($pre).'<span class="dottedLine1">&nbsp;'.h($fn).'&nbsp;&nbsp;&nbsp;'.h($sn).'&nbsp;</span>เลขประจำตัว<span class="dottedLine1">&nbsp;'.h((string) $s['student_id']).'&nbsp;</span>ระดับชั้น<span class="dottedLine1">&nbsp;'.h($lt.'.'.$lvn.'/'.$roomNo).'&nbsp;</span>เลขที่<span class="dottedLine1">&nbsp;'.h((string) $s['number']).'&nbsp;</span>';
        $logoRow = '<div style="width:21cm"><div style="display:table-cell;width:3cm">&nbsp;</div><div style="display:table-cell;text-align:center;width:15cm"><img src="'.h($logo).'" width="50" border="0" align="middle" style="text-align:center" /></div><div style="display: table-cell;white-space:nowrap;vertical-align:top;width:3cm;text-align:right"><span class="circle" style="float:right;width:30px;height:30px;text-align:center;font-size:18pt;display:none">5</span></div></div>';
        $signEval = fn($dl, $w, $w1, $w2) => '<div><table width="100%" border="0" cellpadding="0" cellspacing="0" class="eval_sign"><tr><td style="width:'.$w1.'">&nbsp;</td><td'.($w2 ? ' style="width:'.$w2.'"' : '').' align="left">ลงชื่อ<span class="'.$dl.'" style="width:'.$w.'px">&nbsp;&nbsp;</span>ครูประจำชั้น</td><td style="width:'.$w1.'">&nbsp;</td><td'.($w2 ? ' style="width:'.$w2.'"' : '').' align="left">ลงชื่อ<span class="'.$dl.'" style="width:'.$w.'px">&nbsp;&nbsp;</span>ผู้อำนวยการโรงเรียน</td></tr>'
            .'<tr><td'.($w2 ? '' : ' align="left"').'>&nbsp;</td><td align="left" style="padding-left:30px"><span class="'.$dl.'" style="width:'.$w.'px;border:none">('.($w2 ? '&nbsp;' : '').h($advName).($w2 ? '&nbsp;' : '').')</span></td><td>&nbsp;</td><td align="left" style="padding-left:30px"><span class="'.$dl.'" style="width:'.$w.'px;border:none">('.($w2 ? '&nbsp;' : '').h($dir).($w2 ? '&nbsp;' : '').')</span></td></tr></table></div>';
        $evalCss = ' table.eval_sign { margin:40px 0px 0px 0px;text-align:center } table.eval_sign td { font-weight:bold; font-size:16pt; line-height:17pt; white-space:nowrap!important; }';
        if ($stab === 1 || $stab === 4) {
            $mg = [5, 11, 5, 9];
            $style = 'table.blacktable th { font-size:16pt!important; font-weight:bold; line-height:19pt; white-space:nowrap; border-right:1px solid #000; border-bottom:1px solid #000; text-align:center; padding:2px 0px; } table.blacktable td { font-size:16pt!important; font-weight:normal; line-height:19pt; white-space:nowrap; padding:2px 8px; }';
            $gs = pp6_term_grades($sid, $year, $term);
            usort($gs, fn($a, $b) => [(int) $a['ctype'], sara_rank((string) $a['course_code']), (string) $a['course_code']] <=> [(int) $b['ctype'], sara_rank((string) $b['course_code']), (string) $b['course_code']]);
            $att = rpt_attendance(array_map(fn($g) => (int) $g['course_id'], $gs));
            $tr = '';
            $acts = '';
            $nb = '<td>&nbsp;</td>';
            foreach ([1 => 'รายวิชาพื้นฐาน', 2 => 'รายวิชาเพิ่มเติม'] as $type => $label) {
                $tr .= '<tr>'.$nb.'<td align="left" valign="middle" style="white-space:nowrap;font-weight:bold">'.$label.'</td>'.str_repeat($nb, 6).'</tr>';
                foreach ($gs as $g) {
                    if ((int) $g['ctype'] !== $type) {
                        continue;
                    }
                    $fix = json_decode((string) md_get('remedial', 'g'.(int) $g['id'], $yt), true) ?: [];
                    $pct = $att[(int) $g['course_id']][$sid] ?? null;
                    $has = $g['midterm'] !== null || $g['final'] !== null;
                    $c = fn($x) => '<td align="center" valign="middle">'.$x.'</td>';
                    $tr .= '<tr><td align="center" valign="middle" style="white-space:nowrap">'.h($g['course_code']).'</td><td align="left" valign="middle" style="white-space:nowrap">'.h($g['course_name']).'</td>'
                        .$c(h(rp_num($g['credit']))).$c($pct === null ? '100' : number_format($pct, 0)).$c('100').$c($has ? (int) $g['midterm'] + (int) $g['final'] : '')
                        .$c(h((string) ($fix['old'] ?? $g['grade']))).$c(h((string) ($fix['new'] ?? ''))).'</tr>';
                }
            }
            $ah = act_hours(array_map(fn($g) => (int) $g['id'], array_filter($gs, fn($g) => (int) $g['ctype'] === 9)));
            foreach ($gs as $g) {
                if ((int) $g['ctype'] === 9) {
                    $acts .= '<tr><td style="white-space:nowrap;height:25px;padding:0px 5px">'.h($g['course_name']).'</td><td align="center" valign="middle">'.h((string) ($ah[(int) $g['id']] ?? $g['period'])).'</td><td align="center" valign="middle">'.h((string) $g['grade']).'</td></tr>';
                }
            }
            $gpa = gpa_of(array_map(fn($g) => ['grade' => $g['grade'], 'credit' => $g['credit']], array_filter($gs, fn($g) => (int) $g['ctype'] !== 9)));
            $w80 = fn($t, $rs = '') => '<th'.$rs.' align="center" valign="middle" style="white-space:nowrap;width:80px">'.$t.'</th>';
            $body = '<div id="printcontent" style="width:21cm;font-family:TH Sarabun New;border:0px dotted #000;">'
                .'<div style="width:21cm;border-bottom:1px solid #000;padding-bottom:10px"><div style="display:table-cell;padding-right:10px;white-space:nowrap"><img src="'.h($logo).'" width="50" border="0" align="middle" style="text-align:center" /></div>'
                .'<div style="display:table-cell;width:90%; vertical-align:top; line-height:25px;white-space:nowrap;text-align:left;padding-top:10px"><span style="font-size:24pt;font-weight:bold">'.h($school).'</span><br /><span style="font-size:18pt">'.h(school_address(true)).'</span><br /></div>'
                .'<div style="display: table-cell;text-align:right;white-space:nowrap;vertical-align:top"><span class="circle" style="float:right;width:30px;height:30px;text-align:center;font-size:18pt;display:none">10</span></div></div>'
                .'<div style="font-size:18pt;margin:10px 0px 0px 0px;font-weight:bold;text-align:center;line-height:18pt"> รายงานการวัดประเมินผลการเรียนรู้ ระดับชั้น<span class="dottedLine1">&nbsp;'.h(pp6_level_name($lt, $lvn)).'&nbsp;</span><br /> ปลายภาคเรียนที่ '.$term.' ปีการศึกษา '.$year.'<br /> '.$who.' </div>'
                .'<div style="margin:10px 0px 0px 0px"><table width="100%" border="0" cellspacing="0" cellpadding="0" class="blacktable">'
                .'<tr><th rowspan="3" align="center" valign="middle" style="width:80px;font-size:18pt">รหัสวิชา</th><th rowspan="3" align="center" valign="middle" style="font-size:18pt">รายวิชา</th><th colspan="9" align="center" valign="middle" style="font-size:18pt">การวัดผลและประเมินผลการเรียนรู้</th></tr>'
                .'<tr>'.$w80('น.น.', ' rowspan="2"').$w80('เวลาเรียน').$w80('คะแนน').$w80('คะแนน').$w80('ผลการ<br />ประเมิน', ' rowspan="2"').$w80('แก้ไข', ' rowspan="2"').'</tr>'
                .'<tr>'.$w80('(ร้อยละ)').$w80('เต็ม').$w80('ได้').'</tr>'.$tr
                .'<tr><th colspan="2" rowspan="2">เกรดเฉลี่ย</th><th colspan="6">ภาคเรียนที่ '.$term.'</th></tr><tr><th colspan="6">&nbsp;'.($gpa === null ? '' : number_format($gpa, 2)).'&nbsp;</th></tr></table></div>'
                .'<div style="font-size:18pt;margin:10px 0px 0px 0px;font-weight:bold;text-align:center"> ผลการประเมินกิจกรรมพัฒนาผู้เรียน </div>'
                .'<div style="margin:0px 0px 0px 0px"><table border="0" cellspacing="0" cellpadding="0" class="blacktable" style="width:100%"><tr><th align="center" valign="middle" style="font-size:18pt">กิจกรรมพัฒนาผู้เรียน</th><th align="center" valign="middle" style="width:202px">เวลา (ชั่วโมง)</th><th align="center" valign="middle" style="width:202px">ผลการประเมิน</th></tr>'
                .($acts ?: '<tr><td style="white-space:nowrap;height:25px;padding:0px 5px">&nbsp;</td><td align="center" valign="middle"></td><td align="center" valign="middle"></td></tr>').'</table></div>'
                .pp6_sign_learn($advName, $dir).'</div>';
        } elseif ($stab === 2 || $stab === 5) {
            $mg = [5, 9, 5, 11];
            $style = 'table.blacktable th { font-size:16pt!important; font-weight:bold; line-height:16pt!important; white-space:nowrap; border-right:1px solid #000; border-bottom:1px solid #000; text-align:center; padding:5px 5px 3px 5px!important; } table.blacktable td { font-size:16pt!important; font-weight:normal; line-height:16pt!important; white-space:nowrap; padding:5px 5px 0px 5px!important; }'.$evalCss;
            $ch = pp6_scores('char', $sid, $yt);
            $sk = pp6_scores('skill', $sid, $yt);
            $lvName = [3 => 'ดีเยี่ยม', 2 => 'ดี', 1 => 'ผ่านเกณฑ์การประเมิน', 0 => 'ไม่ผ่านเกณฑ์การประเมิน'];
            $lvOf = fn(array $sc) => $sc ? (int) round(array_sum($sc) / count($sc)) : null;
            $tr = '';
            $sum = 0;
            foreach (SCORE_SETS['char']['items'] as $k => $name) {
                $mul = PP6_CHAR_IND[$k] ?? 1;
                $v = isset($ch[$k]) ? $ch[$k] * $mul : null;
                $sum += (int) $v;
                $tr .= '<tr><td align="center" valign="middle">'.($k + 1).'</td><td colspan="2" valign="middle" style="white-space:nowrap">'.h($name).'</td><td align="center" valign="middle" style="white-space:nowrap">'.(3 * $mul).'</td><td align="center" valign="middle">'.($v ?? '').'</td></tr>';
            }
            $cl = $lvOf($ch);
            $str = '';
            $ssum = 0;
            if ($sk) {
                foreach (SCORE_SETS['skill']['items'] as $k => $name) {
                    $ssum += (int) ($sk[$k] ?? 0);
                    $str .= '<tr><td valign="middle" style="white-space:nowrap">'.($k + 1).'. '.h($name).'</td><td valign="middle" style="white-space:nowrap">&nbsp;</td><td align="center" valign="middle">3</td><td align="center" valign="middle">'.($sk[$k] ?? '').'</td></tr>';
                }
            }
            $sl = $lvOf($sk);
            [$open, $come, $abs, $leave, $sick] = pp6_days($sid, $year, $term);
            $thc = fn($x, $st = '') => '<th align="center" valign="middle"'.($st ? ' style="'.$st.'"' : '').'>'.$x.'</th>';
            $body = '<div id="printcontent" style="width:21cm;font-family:TH Sarabun New;border:0px dotted #000;">'.$logoRow
                .'<div style="font-size:17pt;line-height:18pt;margin:10px 0px 0px 0px;font-weight:bold;text-align:center"> รายงานผลการประเมินคุณลักษณะอันพึงประสงค์ และผลการประเมินการอ่าน การคิดวิเคราะห์ การเขียนสื่อความ<br /> ภาคเรียนที่ '.$term.' ปีการศึกษา '.$year.'<br /> '.$who.' </div>'
                .'<div style="margin:10px 0px 0px 0px"><table width="100%" border="0" cellspacing="0" cellpadding="0" class="blacktable" style="font-size:16px"><tr>'
                .$thc('ข้อที่', 'white-space:nowrap;width:4%').'<th colspan="2" align="center" valign="middle">คุณลักษณะอันพึงประสงค์</th>'.$thc('คะแนนเต็ม', 'white-space:nowrap;width:4%').$thc('ผลประเมิน', 'white-space:nowrap;width:4%').'</tr>'.$tr
                .'<tr><th rowspan="3" align="center" valign="middle">&nbsp;</th><th rowspan="3" align="center" valign="middle">&nbsp;</th><th align="right" valign="middle" class="bold" style="text-align:right">รวม</th><th align="center" valign="middle" style="white-space:nowrap" class="bold">100</th><th align="center" valign="middle" class="bold">'.($ch ? $sum : '').'</th></tr>'
                .'<tr><th align="right" valign="middle" class="bold" style="white-space:nowrap;text-align:right">ระดับคุณภาพ</th><th colspan="2" align="center" valign="middle" class="bold" style="white-space:nowrap">'.($cl ?? '').'</th></tr>'
                .'<tr><th align="right" valign="middle" class="bold" style="white-space:nowrap;width:50px;text-align:right">ผลการประเมิน</th><th colspan="2" align="center" valign="middle">'.($cl === null ? '' : $lvName[$cl]).'</th></tr></table></div>'
                .'<div style="margin:15px 0px 0px 0px"><table width="100%" border="0" cellspacing="0" cellpadding="0" class="blacktable" style="font-size:18px"><tr><th colspan="2" align="center" valign="middle">ความสามารถในการอ่าน คิดวิเคราะห์และเขียนสื่อความ</th>'
                .$thc('คะแนนเต็ม', 'white-space:nowrap;width:4%').$thc('ผลประเมิน', 'white-space:nowrap;width:4%').'</tr>'.$str
                .'<tr><th rowspan="3" align="center" valign="middle">&nbsp;</th><th valign="middle" style="white-space:nowrap;text-align:right" class="bold">รวม</th><th align="center" valign="middle" style="white-space:nowrap" class="bold">100</th><th align="center" valign="middle" class="bold">'.($sk ? $ssum : '').'</th></tr>'
                .'<tr><th valign="middle" class="bold" style="white-space:nowrap;text-align:right">ระดับคุณภาพ</th><th colspan="2" align="center" valign="middle" class="bold" style="white-space:nowrap">'.($sl ?? '').'</th></tr>'
                .'<tr><th valign="middle" class="bold" style="white-space:nowrap;text-align:right;width:50px">ผลการประเมิน</th><th colspan="2" align="center" valign="middle" class="bold" style="white-space:nowrap">'.($sl === null ? '' : $lvName[$sl]).'</th></tr></table></div>'
                .'<div style="font-size:18pt;margin:10px 0px 0px 0px;font-weight:bold;text-align:center"> บันทึกเวลาเรียน </div>'
                .'<div><table width="100%" border="0" cellspacing="0" cellpadding="0" class="blacktable"><tr><th rowspan="2" align="center" valign="middle" style="width:80px">ภาคเรียน</th><th colspan="6" align="center" valign="middle">'.h(pp6_level_name($lt, $lvn)).'</th></tr>'
                .'<tr>'.implode('', array_map(fn($x) => $thc($x, 'width:13%'), ['เปิดเรียน (วัน)', 'มาเรียน', 'ขาด', 'ลา', 'ป่วย', 'ร้อยละ'])).'</tr>'
                .'<tr>'.$thc('ภาคเรียนที่ '.$term, 'width:80px').implode('', array_map(fn($x) => $thc((string) $x), [$open, $come, $abs, $leave, $sick, $open ? number_format($come * 100 / $open, 0) : 0])).'</tr></table></div>'
                .$signEval('dottedLine1', 230, '2%', '').'</div>';
        } else {
            $mg = [5, 9, 5, 11];
            $style = 'table.blacktable th { font-size:18pt; font-weight:bold; line-height:22pt!important; white-space:nowrap; border-right:1px solid #000; border-bottom:1px solid #000; text-align:center; } table.blacktable td { font-size:18pt; font-weight:normal; line-height:25pt; white-space:nowrap; padding:0px 8px; }'.$evalCss;
            $cm = json_decode((string) md_get('pp6c', $sid, $yt), true) ?: [];
            if (!$cm && ($old = json_decode((string) md_get('pp6', $sid, $yt), true))) {
                $cm['learn'] = $old['comment'] ?? '';
            }
            $tr = '';
            foreach (PP6_COMMENT as $k => $l) {
                $tr .= '<tr><th style="white-space:nowrap;text-align:center;vertical-align:top;padding-top:5px;font-weight:normal">'.$l.'</th><th align="left" valign="top" style="padding-top:5px;text-align:left;padding:5px 10px;font-weight:normal;white-space:normal;height:2.3cm"> '.nl2br(h((string) ($cm[$k] ?? ''))).'</th></tr>';
            }
            $dots = str_repeat('<div style="line-height:35px"> '.str_repeat('.', 184).' </div>', 4);
            $body = '<div id="printcontent" style="width:21cm;font-family:TH Sarabun New;border:0px dotted #000;">'.$logoRow
                .'<div style="font-weight:bold;font-size:26px;margin:0px 0px;text-align:center;line-height:26px;margin-top:10px">ความคิดเห็นเกี่ยวกับนักเรียน ระดับชั้น'.h(pp6_level_name($lt, $lvn)).'<br />ภาคเรียนที่ '.$term.' ปีการศึกษา '.$year.'</div>'
                .'<div style="font-size:20pt;margin:10px 0px 0px 0px;font-weight:bold">ความคิดเห็นของครูประจำชั้น</div>'
                .'<div style="margin:0px 0px 0px 0px"><table width="100%" border="0" cellspacing="0" cellpadding="0" class="blacktable" style="font-size:18pt"><tr><th align="center" valign="middle" bgcolor="#eee" style="font-size:18pt;line-height:22pt;padding:3px 0px 0px 0px;width:20%">เรื่อง</th><th align="center" valign="middle" bgcolor="#eee" style="font-size:18pt;line-height:22pt;padding:3px 0px 0px 0px">ภาคเรียนที่ '.$term.'</th></tr>'.$tr.'</table></div>'
                .$signEval('dottedLine', 200, '5%', '45%')
                .'<div style="font-size:20pt;margin:20px 0px 0px 0px;font-weight:bold">ความคิดเห็นของผู้ปกครอง</div>'
                .'<div style="margin:0px 0px 0px 0px"><table width="100%" border="0" cellspacing="0" cellpadding="0" class="blacktable" style="font-size:20px"><tr><th align="center" valign="middle" style="padding-top:3pt;font-size:18pt">ภาคเรียนที่ '.$term.'</th></tr>'
                .'<tr><td align="center" valign="middle" style="height:125px; text-align:left; vertical-align:top;padding:10px 20px 20px 40px">'.$dots.'</td></tr></table></div>'
                .'<div><table width="100%" border="0" cellpadding="0" cellspacing="0" style="font-size:18pt;line-height:22pt;margin:40px 0px 0px 0px;font-weight:bold;text-align:center"><tr><td colspan="4" align="center" style="padding-top:2px">ลงชื่อ<span class="dottedLine1" style="width:250px">&nbsp;&nbsp;</span>ผู้ปกครอง</td></tr>'
                .'<tr><td colspan="4" align="center" style="padding-top:2px">'.str_repeat('&nbsp;', 6).'(<span class="dottedLine1" style="width:250px">&nbsp;&nbsp;</span>)'.str_repeat('&nbsp;', 12).'</td></tr></table></div></div>';
        }
    } else {
        $mg = [5, 11, 5, 9];
        $style = 'table.blacktable td{ line-height:28pt; }';
        $bx = fn($t, $w = '') => '<span style="float:left'.($w ? ';width:'.$w : '').'">☐&nbsp;'.$t.'</span>';
        $four = '<div style="display:table-row">'.$bx('ดีเยี่ยม', '80px').' '.$bx('ดี').'</div><div style="display:table-row">'.$bx('ผ่านเกณฑ์การประเมิน').'</div><div style="display:table-row">'.$bx('ไม่ผ่านเกณฑ์การประเมิน').'</div>';
        $r = fn($l, $v, $right, $va = 'middle', $pad = 'padding:0px 10px') => '<tr><td align="left" valign="middle" style="white-space:nowrap;'.$pad.'">'.$l.'</td><td align="left" valign="middle" style="'.($v === $four ? 'padding:3px 10px' : 'padding:0px 10px').'">'.$v.'</td><td colspan="4" align="center" valign="'.$va.'" style="border-bottom:none">'.$right.'</td></tr>';
        $pass = $bx('ผ่าน', '80px').' '.$bx('ไม่ผ่าน');
        $sub8 = ['ภาษาไทย', 'คณิตศาสตร์', 'วิทยาศาสตร์และเทคโนโลยี', 'สังคมศึกษา ศาสนาและวัฒนธรรม', 'สุขศึกษาและพลศึกษา', 'ศิลปะ', 'การงานอาชีพ', 'ภาษาต่างประเทศ'];
        $ln3 = fn($label, $val, $w) => '<span style="float:left;width:'.$w.'px;text-align:center">&nbsp;'.$val.'&nbsp;</span> <span style="float:left;width:'.$w.'px;margin-left:-'.$w.'px;margin-top:-10px;border-bottom:1.5px dotted #666666">&nbsp;</span> ';
        $eq = '<span style="float:left;width:50px;text-align:center">=</span> ';
        $bandShort = $lt === 'ป' ? 'ป.4-6' : ($ln >= 4 ? 'ม.4-6' : 'ม.1-3');
        $body = '<div id="printcontent" style="display:table;width:100%;font-family:TH Sarabun New;text-align:left"><div style="display: table-row"><div style="display: table-cell;float:right"><span class="circle" style="float:right;width:30px;text-align:center;font-size:18pt;display:none">16</span></div></div>'
            .'<div style="font-weight:bold;font-size:26px;margin:0px 0px;text-align:center">การประเมินกลุ่มสาระการเรียนรู้</div><div style="font-size:24px;margin:5px 0px 0px 0px;font-weight:bold">ผลการประเมินการเรียนตลอดหลักสูตร</div>'
            .'<div style="margin:3px 0px 0px 0px"><table width="100%" border="0" cellspacing="0" cellpadding="0" class="blacktable">'
            .'<tr><td align="center" valign="middle">การประเมินผลการเรียนระดับชั้น</td><td align="center" valign="middle" style="width:30%">ผลการตัดสิน</td><td colspan="4" align="center" valign="middle" style="width:30%;border-bottom:none">&nbsp;</td></tr>'
            .$r('ผลการเรียนรายวิชา', $pass, '&nbsp;', 'middle', 'padding-left:10px')
            .$r('ผลการประเมินการอ่าน คิดวิเคราะห์และเขียน', $four, '('.($advName !== '' ? '&nbsp;&nbsp;&nbsp;'.pp6_nb($advName).'&nbsp;&nbsp;&nbsp;' : str_repeat('&nbsp;', 8)).')', 'bottom')
            .$r('ผลการประเมินคุณลักษณะอันพึงประสงค์', $four, 'ครูประจำชั้น', 'top')
            .$r('ผลการประเมินกิจกรรมพัฒนาผู้เรียน', $pass, '&nbsp;')
            .'<tr><td colspan="2" align="center" valign="middle" style="white-space:nowrap;height:60px;text-align:left;padding-left:10px"><span style="float:left;width:180px;font-weight:bold">สรุปผลการประเมิน</span> <span style="float:left;width:30px">☐</span> <span style="float:left;width:80px">&nbsp;ได้เลื่อนชั้น</span> <span style="float:left;width:30px">☐</span> <span style="float:left">&nbsp;ไม่ผ่านการเลื่อนชั้น</span></td>'
            .'<td colspan="4" align="center" valign="bottom" style="border-bottom:none">('.($dir !== '' ? '&nbsp;&nbsp;&nbsp;'.pp6_nb($dir).'&nbsp;&nbsp;&nbsp;' : str_repeat('&nbsp;', 8)).')</td></tr>'
            .'<tr><td colspan="2" align="left" valign="middle" style="white-space:nowrap;height:60px;padding-left:10px"><span style="float:left;width:180px;font-weight:bold">อนุมัติผลการเรียน</span></td><td colspan="4" align="center" valign="top">ผู้อำนวยการโรงเรียน</td></tr></table></div>'
            .'<div style="font-size:24px;margin:10px 0px 0px 0px;font-weight:bold">ผลการประเมินคุณภาพการศึกษาขั้นพื้นฐานระดับชาติ</div>'
            .'<div style="margin:3px 0px 0px 0px"><table width="100%" border="0" cellspacing="0" cellpadding="0" class="blacktable" style="font-size:20px"><tr><td rowspan="2" align="center" valign="middle" style="font-size:24px;">กลุ่มสาระการเรียนรู้</td><td colspan="3" align="center" valign="middle" style="height:35px">ผลการประเมิน</td><td rowspan="2" align="center" valign="middle" style="width:30%">หมายเหตุ</td></tr>'
            .'<tr><td align="center" valign="middle" style="height:35px;width:12%">คะแนนเต็ม</td><td align="center" valign="middle" style="width:12%"><span style="width:15%">คะแนนที่ได้</span></td><td align="center" valign="middle" style="width:12%">คิดเป็นร้อยละ</td></tr>'
            .implode('', array_map(fn($x) => '<tr><td style="white-space:nowrap;padding-left:10px">'.$x.'</td><td align="center" valign="middle">100</td><td align="center" valign="middle"></td><td align="center" valign="middle"></td><td align="center" valign="middle"><input name="note" type="text" style="height:20px;width:280px;text-align:left;border:none;font-family:TH Sarabun New;font-size:18px; vertical-align:middle" value="" /></td></tr>', $sub8))
            .'</table></div>'
            .'<div style="font-size:24px;margin:20px 0px 0px 0px;font-weight:bold">สัดส่วนผลการเรียนและผลการทดสอบระดับชาติ</div><div style="margin:10px 0px 0px 0px;font-size:22px">'
            .'<span style="display:table-row"><span style="float:left">ร้อยละ</span> '.$ln3('', '80', 75).'<span style="float:left;width:360px">ของผลการเรียนเฉลี่ยตลอด 3 ปี ('.$bandShort.')</span> '.$eq.$ln3('', '&nbsp;', 120).'</span>'
            .'<span style="display:table-row"><div style="padding-top:10px"><span style="float:left">ร้อยละ</span> '.$ln3('', '20', 75).'<span style="float:left;width:360px">ของผลการทดสอบทางการศึกษาระดับชาติขั้นพื้นฐาน</span> '.$eq.$ln3('', '&nbsp;', 120).'</div></span>'
            .'<span style="display:table-row"><div style="padding-top:10px"><span style="float:left;width:475px">ผลการเรียนเฉลี่ยรวม</span> '.$eq.$ln3('', '&nbsp;', 120).'</div></span></div></div>';
    }
    pp6_page($s, $sub, $tabs, $style, $body, $mg, $size);
}

/* ---------------------------------------------------------------- บันทึกข้อมูล ปพ.6 (ครูประจำชั้น) */

function rows_task_info_pp6_Edit()
{
    $s = advisor_student((int) ($_GET['StudentId'] ?? 0)) ?? redirect('?module=task&option=info_pp6');
    $sid = (int) $s['id'];
    $full = q1('SELECT S.*, U.`name`, U.`phone` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`id`=?', [$sid]);
    $p = pp6_person($full);
    [, $ln] = pp6_level((int) $s['class']);
    $g = json_decode((string) md_get('pp6g', $sid, $ln), true) ?: [];
    $cm = json_decode((string) md_get('pp6c', $sid, yt()), true) ?: [];
    if (!$cm && ($old = json_decode((string) md_get('pp6', $sid, yt()), true))) {
        $cm['learn'] = $old['comment'] ?? '';
    }
    $in = fn($n, $v, $w = 200) => '<input type="text" name="'.$n.'" value="'.h((string) $v).'" class="general radius" style="width:'.$w.'px;height:19px"/>';
    $rows = '';
    $i = 0;
    foreach (PP6_PERSON as $k => $l) {
        $rows .= zrow([$l, $in('p['.$k.']', $p[$k] ?? '', in_array($k, ['house', 'father', 'mother', 'guardian'], true) ? 400 : 200)], $i++);
    }
    $grow = '<table style="border-collapse:collapse"><tr><td></td>'.implode('', array_map(fn($m) => '<td align="center"><b>'.$m.'</b></td>', PP6_MONTHS)).'</tr>';
    foreach (['age' => 'อายุ (ปี)', 'weight' => 'น้ำหนัก (กก.)', 'height' => 'ส่วนสูง (ซม.)'] as $k => $l) {
        $grow .= '<tr><td style="padding-right:10px">'.$l.'</td>'.implode('', array_map(fn($m) => '<td style="padding:2px 4px">'.$in('g['.$m.']['.$k.']', $g[$m][$k] ?? '', 80).'</td>', array_keys(PP6_MONTHS))).'</tr>';
    }
    $rows .= zrow(['การเจริญเติบโต (ชั้นปีที่ '.$ln.')', $grow.'</table>'], $i++);
    foreach (PP6_COMMENT as $k => $l) {
        $rows .= zrow(['ความคิดเห็นครูประจำชั้น: '.$l.' (ภาคเรียนนี้)', '<textarea name="c['.$k.']" class="general radius" style="width:600px;height:50px">'.h((string) ($cm[$k] ?? '')).'</textarea>'], $i++);
    }
    $body = '<form name="pp6" method="post" action="?module=task&option=info_pp6&func=Save&StudentId='.$sid.'">'
        .list_table('ข้อมูลนักเรียนใน ปพ.6 : '.name_nbsp($s['name']), ['รายการ', 'ข้อมูล'], $rows, '', btn('กลับ', '?module=task&option=info_pp6', 'back')
            .'<a href="javascript:void(0);" onclick="document.forms.pp6.submit();" id="pinkgreenbtns" style="margin-left:4px"><img src="../icon_tools/vec_add_white.png" border="0" height="16"/> บันทึก</a>').'</form>';
    return sub_page('task_info_pp6', $body);
}

function act_task_info_pp6_Save(): void
{
    $s = advisor_student((int) ($_GET['StudentId'] ?? 0));
    if ($s) {
        $sid = (int) $s['id'];
        $cut = fn($v, $n = 150) => mb_substr(trim((string) $v), 0, $n);
        $p = [];
        foreach (array_keys(PP6_PERSON) as $k) {
            $p[$k] = $cut($_POST['p'][$k] ?? '');
        }
        md_set('pp6p', $sid, '', '', json_encode($p, JSON_UNESCAPED_UNICODE));
        [, $ln] = pp6_level((int) $s['class']);
        $g = [];
        foreach (array_keys(PP6_MONTHS) as $m) {
            foreach (['age', 'weight', 'height'] as $k) {
                $v = $cut($_POST['g'][$m][$k] ?? '', 10);
                if ($v !== '') {
                    $g[$m][$k] = $v;
                }
            }
        }
        md_set('pp6g', $sid, $ln, '', $g ? json_encode($g, JSON_UNESCAPED_UNICODE) : null);
        $c = [];
        foreach (array_keys(PP6_COMMENT) as $k) {
            $c[$k] = $cut($_POST['c'][$k] ?? '', 1000);
        }
        md_set('pp6c', $sid, yt(), '', array_filter($c) ? json_encode($c, JSON_UNESCAPED_UNICODE) : null);
    }
    redirect('?module=task&option=info_pp6&msg=saved');
}
