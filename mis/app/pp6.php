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

/* ---------------------------------------------------------------- หน้า */

function pp6_page(array $s, string $sub, string $tabs, string $body, string $margin): void
{
    mis_headers();
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><title>ปพ.6 '.h($s['name']).'</title><link rel="stylesheet" href="/mis/css/thaisansbold.css"><style>'
        .'@page{size:A4 portrait;margin:5mm 8mm}body{margin:0;background:#fff;font:18.67px/1.35 "TH Sarabun New","TH SarabunPSK",thaisansbold,Tahoma,sans-serif;color:#000}'
        .'.pv-top{background:#FBFBFB;display:flex;justify-content:space-between;align-items:center;padding:0 10px;border-bottom:1px solid #9FD8E6;margin:10px 10px 0}'
        .'.pv-bar{margin:6px 10px 0;background:linear-gradient(90deg,#71C7F1,#63B5EC);color:#fff;text-align:center;font-family:thaisansbold,"TH Sarabun New";padding:6px 0}.pv-bar b{display:block;font-weight:normal;font-size:24px}.pv-bar span{font-size:19px}'
        .'.pv-line{margin:4px 10px 0;border-top:1px solid #2BB5C6}.tabs{margin:20px 10px 0;width:984px;border-bottom:2px solid #2BB5C6;display:flex;flex-wrap:wrap;gap:4px}'
        .'.tabs a{display:inline-block;background:#A9A9A9;color:#fff;font:16px thaisansbold;padding:6px 14px 3px;border-radius:3px 3px 0 0;text-decoration:none}.tabs a.on{background:#17B4B4}.tabs.sub{margin-top:8px}'
        .'.doc{width:794px;margin:18px auto 10px;position:relative}.dl{display:flex;align-items:flex-end;margin:14px 0}.dl b{white-space:nowrap;font-weight:bold}.dl span{flex:1;border-bottom:1px dotted #000;text-align:center;font-weight:bold}'
        .'.f{display:flex;align-items:flex-end;margin:10px 0;gap:0}.f i{font-style:normal;white-space:nowrap}.f span{flex:1;border-bottom:1px dotted #000;text-align:center;min-width:40px}'
        .'table.t{border-collapse:collapse;width:100%}table.t td,table.t th{border:1px solid #000;padding:2px 6px;font-size:18.67px}table.t th{font-weight:bold}table.t .c{text-align:center}table.t .r{text-align:right}'
        .'.ctr{text-align:center}.bold{font-weight:bold}.pv-tip{background:#D3D3D3;margin:14px 10px 10px;text-align:center;padding:10px;font-size:18px}.chk{display:inline-block;width:12px;height:12px;border:1px solid #000;margin-right:6px;vertical-align:-1px}'
        .'.sig{display:flex;justify-content:space-around;text-align:center;margin-top:28px}'
        .'@media print{.pv-top,.pv-bar,.pv-line,.tabs,.pv-tip{display:none}.doc{margin:0 auto}}</style></head><body>'
        .'<div class="pv-top"><img src="/mis/images/preview_title.png" alt="ตัวอย่างก่อนพิมพ์" style="height:80px"/><a href="javascript:window.print()" title="พิมพ์"><img src="/mis/icon_tools/icon_mini_print.png" border="0"/></a></div>'
        .'<div class="pv-bar"><b>สมุดรายงานผลการพัฒนาคุณภาพผู้เรียนรายบุคคล (ปพ.๖)</b><span>'.h($sub).'</span></div><div class="pv-line"></div>'
        .$tabs.$body.'<div class="pv-tip">คำแนะนำสำหรับการพิมพ์ : ตั้งค่ากระดาษเป็น A4 และกำหนดระยะขอบของ Web Browser ดังนี้ &nbsp; &nbsp; '.$margin.'</div></body></html>';
    exit;
}

function pp6_sign(array $s, bool $parent = false): string
{
    $adv = pp6_advisors($s);
    $advName = end($adv)[2] ?? '';
    return '<div class="sig"><div>ลงชื่อ ........................................ ครูประจำชั้น<br>( '.h($advName ?: '........................................').' )</div>'
        .'<div>ลงชื่อ ........................................ ผู้อำนวยการโรงเรียน<br>( '.h(rpt_signer('ผู้อำนวยการ') ?: '........................................').' )</div></div>';
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
    $tabs = '<div class="tabs">';
    foreach ([1 => 'หน้าปก', 2 => 'ปกใน', 3 => 'เกณฑ์การจบหลักสูตร', 4 => 'ข้อมูลส่วนตัว', 5 => 'ความเจริญเติบโต', 6 => 'ผลการเรียน', 7 => 'ผลประเมินตลอดหลักสูตร'] as $k => $l) {
        $tabs .= '<a href="'.$base.'&Tab='.$k.'"'.($k === $tab ? ' class="on"' : '').'>'.$l.'</a>';
    }
    $tabs .= '</div>';
    $sub = $s['name'].' ระดับชั้น'.pp6_level_name($lt, $ln).'/'.$roomNo.' ปีการศึกษา '.$cy;
    $p = pp6_person($s);
    $logo = '/mis/gallery/logo/school_logo.png';
    $margin = ['Left = 20 &nbsp; Right = 5 &nbsp; Top = 10 &nbsp; Bottom = 5', 'Left = 15 &nbsp; Right = 10 &nbsp; Top = 15 &nbsp; Bottom = 15', 'Left = 12 &nbsp; Right = 7 &nbsp; Top = 5 &nbsp; Bottom = 5',
        'Left = 9 &nbsp; Right = 11 &nbsp; Top = 5 &nbsp; Bottom = 5', 'Left = 15 &nbsp; Right = 7 &nbsp; Top = 5 &nbsp; Bottom = 5', 'Left = 9 &nbsp; Right = 11 &nbsp; Top = 5 &nbsp; Bottom = 5', 'Left = 9 &nbsp; Right = 11 &nbsp; Top = 5 &nbsp; Bottom = 5'][$tab - 1];
    $body = '';
    $dl = fn($label, $value, $extra = '') => '<div class="dl" style="font-size:24px"><b>'.$label.'</b><span>'.h($value).$extra.'</span></div>';
    $f = function (array $pairs): string {
        $o = '<div class="f">';
        foreach ($pairs as [$l, $v, $w]) {
            $o .= '<i>'.$l.'</i><span style="flex:'.$w.'">'.h((string) $v).'</span>';
        }
        return $o.'</div>';
    };
    if ($tab === 1) {
        $adv = pp6_advisors($s);
        $tr = '';
        for ($i = 0; $i < max(6, count($adv)); $i++) {
            [$y, $lv, $t] = $adv[$i] ?? ['', '', ''];
            $tr .= '<tr><td class="c" style="height:30px">'.h((string) $y).'</td><td class="c">'.h($lv).'</td><td>'.h($t).'</td></tr>';
        }
        $body = '<div class="doc"><div style="position:absolute;right:-90px;top:0;font-weight:bold">ปพ.6</div><div class="ctr"><img src="'.$logo.'" style="width:100px"/>'
            .'<div style="font-size:32px;font-weight:bold;margin-top:4px">รายงานผลการพัฒนาคุณภาพผู้เรียนรายบุคคล</div><div style="font-size:26px;font-weight:bold">'.h($band).'</div>'
            .'<div style="font-size:26px;font-weight:bold">'.h($school.' อำเภอ'.cfg('school_amphoe', 'แม่สาย').' จังหวัด'.cfg('school_province', 'เชียงราย')).'</div></div>'
            .'<div style="width:706px;margin:30px auto 0">'.$dl('ชื่อ-สกุล', $s['name'], '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <b>เลขประจำตัว</b>&nbsp; '.h($s['student_id'])).$dl('เลขประจำตัวประชาชน', pp6_idcard((string) $s['id_card']))
            .$dl('ชื่อ-ชื่อสกุลบิดา', $p['father'] ?? '').$dl('ชื่อ-ชื่อสกุลมารดา', $p['mother'] ?? '').$dl('ชื่อ-ชื่อสกุลผู้ปกครอง', $p['guardian'] ?? '')
            .'<div class="ctr bold" style="font-size:22px;margin-top:50px">ครูประจำชั้น</div><table class="t" style="margin-top:4px"><tr><th style="width:125px">ปีการศึกษา</th><th style="width:175px">ระดับชั้น</th><th>ครูประจำชั้น</th></tr>'.$tr.'</table></div></div>';
    } elseif ($tab === 2) {
        $body = '<div class="doc" style="border:1px solid #000;padding:30px 40px;box-sizing:border-box;font-size:21px;line-height:2"><b>เรียน ท่านผู้ปกครอง</b>'
            .'<p style="text-indent:56px;margin:8px 0;text-align:justify">โรงเรียนขอเรียนว่า โรงเรียนมีความรัก และห่วงใยนักเรียน ซึ่งเป็นบุตรหลานของท่าน และปรารถนาให้ทุกคนประสบความสำเร็จในการเรียน มีความเจริญก้าวหน้าในการดำเนินชีวิต ด้วยเหตุนี้จึงใคร่ขอความร่วมมือจากท่านได้โปรดติดตามดูแลความประพฤติ และการเรียนของนักเรียนขณะที่อยู่บ้านพร้อมกับให้ความช่วยเหลือสนับสนุนการเรียนในด้านต่างๆ ตามความสามารถทั้งนี้โรงเรียนจะติดตามเอาใจใส่นักเรียนในขณะที่อยู่ในโรงเรียนเป็นอย่างดี เช่นเดียวกัน</p>'
            .'<p style="text-indent:76px;margin:8px 0;text-align:justify">แบบรายงานผลการพัฒนาคุณภาพผู้เรียนเล่มนี้ เป็นเอกสารสำหรับสื่อสารระหว่างท่าน กับโรงเรียน เพื่อแจ้งผลการเรียนและข้อมูลบางอย่างของนักเรียนทั้งขณะอยู่ที่โรงเรียน และที่บ้านให้แต่ละฝ่ายได้รับทราบซึ่งจะช่วยให้เกิดความเข้าใจในตัวนักเรียนอย่างถูกต้อง อันจะเป็นประโยชน์ต่อการปรับปรุงแก้ไข หรือให้การสนับสนุนส่งเสริมนักเรียนต่อไป ฉะนั้นเมื่อท่านได้รับเอกสารนี้แล้ว ขอความกรุณาได้โปรดสละเวลาพิจารณาดูผลการเรียน เวลาเรียน และข้อเสนอแนะต่าง ๆ พร้อมกับให้ความเห็นเกี่ยวกับนักเรียนขณะอยู่ที่บ้านด้วย จึงเรียนมาเพื่อขอความร่วมมือ และขอขอบคุณมา ณ โอกาสนี้</p>'
            .'<div style="margin:40px 0 20px 360px;text-align:center">ขอแสดงความนับถือ<br><br><br>( '.h(rpt_signer('ผู้อำนวยการ') ?: '........................................').' )<br>ผู้อำนวยการ'.h($school).'</div></div>';
    } elseif ($tab === 3) {
        $g = fn($rows, $h) => '<table style="width:94%;margin:0 0 6px 20px;border-collapse:collapse"><tr>'.implode('', array_map(fn($x) => '<th style="text-align:left;font-size:19px">'.$x.'</th>', $h)).'</tr>'
            .implode('', array_map(fn($r) => '<tr>'.implode('', array_map(fn($x) => '<td style="font-size:18.67px">'.$x.'</td>', $r)).'</tr>', $rows)).'</table>';
        $lv4 = [['"ดีเยี่ยม"', 'ร้อยละ 80-100', 'มีผลการประเมินอยู่ในระดับ ๓'], ['"ดี"', 'ร้อยละ 60-79', 'มีผลการประเมินอยู่ในระดับ ๒'], ['"ผ่านเกณฑ์การประเมิน"', 'ร้อยละ 50-59', 'มีผลการประเมินอยู่ในระดับ ๑'], ['"ไม่ผ่านเกณฑ์การประเมิน"', 'ร้อยละ 0-49', 'มีผลการประเมินอยู่ในระดับ ๐']];
        $body = '<div class="doc"><div class="ctr" style="margin:0 auto;width:300px;border-top:3px double #000;border-bottom:3px double #000;font-size:28px;font-weight:bold;padding:6px 0">เกณฑ์การจบหลักสูตร</div>'
            .'<div class="ctr bold" style="font-size:22px;margin:14px 0 8px">ตามหลักสูตรแกนกลางการศึกษาขั้นพื้นฐาน พุทธศักราช 2551</div><ol style="margin:0;padding-left:22px">'
            .'<li>ผู้เรียนเรียนรายวิชาพื้นฐานและรายวิชาเพิ่มเติม/กิจกรรม ตามโครงสร้างเวลาเรียนที่หลักสูตรแกนกลางการศึกษาขั้นพื้นฐานกำหนด</li><li>ผู้เรียนต้องมี ผลการประเมิน รายวิชาพื้นฐาน ผ่านเกณฑ์การประเมินตามที่สถานศึกษากำหนด</li>'
            .'<li>ผู้เรียนมี ผลการประเมิน การอ่าน คิดวิเคราะห์และเขียน ในระดับผ่านเกณฑ์การประเมิน ตามที่สถานศึกษากำหนด</li><li>ผู้เรียนมี ผลการประเมิน คุณลักษณะอันพึงประสงค์ ในระดับผ่านเกณฑ์การประเมินตามที่สถานศึกษากำหนด</li>'
            .'<li>ผู้เรียนเข้าร่วมกิจกรรมพัฒนาผู้เรียน และมีผลการประเมินผ่านเกณฑ์ตามที่สถานศึกษากำหนด</li></ol>'
            .'<div class="bold" style="font-size:21px;margin:14px 0 4px">การให้ระดับผลการเรียนในแต่ละกลุ่มสาระ แบ่งเป็น 8 ระดับ ดังนี้</div>'
            .$g([['๔', 'ร้อยละ 80-100', 'ดีเยี่ยม'], ['๓.๕', 'ร้อยละ 75-79', 'ดีมาก'], ['๓', 'ร้อยละ 70-74', 'ดี'], ['๒.๕', 'ร้อยละ 65-69', 'ค่อนข้างดี'], ['๒', 'ร้อยละ 60-64', 'ปานกลาง'], ['๑.๕', 'ร้อยละ 55-59', 'พอใช้'], ['๑', 'ร้อยละ 50-54', 'ผ่านเกณฑ์ขั้นต่ำ'], ['๐', 'ร้อยละ 0-49', 'ต่ำกว่าเกณฑ์']], ['ระดับผลการเรียน', 'คะแนนร้อยละ', 'ความหมายของผลการประเมิน'])
            .'<div class="bold" style="font-size:21px;margin:14px 0 4px">ระดับผลการประเมินการอ่าน คิดวิเคราะห์ และเขียน</div>'.$g($lv4, ['ระดับผลการประเมิน', 'คะแนนร้อยละ', 'ระดับคุณภาพ'])
            .'<div class="bold" style="font-size:21px;margin:14px 0 4px">ระดับผลการประเมินคุณลักษณะอันพึงประสงค์</div>'.$g($lv4, ['ระดับผลการประเมิน', 'คะแนนร้อยละ', 'ระดับคุณภาพ'])
            .'<div class="bold" style="font-size:21px;margin:14px 0 0">ระดับผลการประเมินกิจกรรมพัฒนาผู้เรียน</div><div style="margin-left:20px">ผู้เรียนจะต้องมีเวลาเข้าร่วมกิจกรรมร้อยละ ๘๐ ขึ้นไป โดยมีผลการตัดสิน ดังนี้</div>'
            .$g([['"ผ่าน"', 'ผู้เรียนมีเวลาเข้าร่วมกิจกรรมครบ และปฏิบัติกิจกรรมและผลงานตามเกณฑ์ที่สถานศึกษากำหนด'], ['"ไม่ผ่าน"', 'ผู้เรียนมีเวลาเข้าร่วมกิจกรรมไม่ครบ หรือไม่ผ่านการปฏิบัติกิจกรรมตามเกณฑ์ที่สถานศึกษากำหนด']], ['ระดับผลการประเมิน', 'ความหมายของผลการประเมิน']).'</div>';
    } elseif ($tab === 4) {
        $body = '<div class="doc" style="width:790px"><div style="display:flex;justify-content:center;align-items:center;margin:40px 0 90px;position:relative"><div style="border:2px solid #000;border-radius:10px;padding:6px 20px;font-size:28px;font-weight:bold">ข้อมูลส่วนตัวผู้เรียน</div>'
            .'<img src="'.h(avatar_url($sid)).'" style="position:absolute;right:0;top:-28px;width:95px;height:142px;object-fit:cover;border:1px solid #ddd;padding:4px"/></div>'
            .$f([['ชื่อ-นามสกุล', $s['name'], 3], ['เลขประจำตัว', $s['student_id'], 1]]).$f([['เลขประจำตัวประชาชน', pp6_idcard((string) $s['id_card']), 2], ['วัน เดือน ปีเกิด', pp6_birth($s['birthday'] ?? null), 1.4]])
            .$f([['กลุ่มเลือด', $p['blood'] ?? '', 1], ['เชื้อชาติ', $p['race'], 1], ['สัญชาติ', $p['nation'], 1], ['ศาสนา', $p['religion'], 1]])
            .$f([['ชื่อ/สกุลบิดา', $p['father'] ?? '', 2.4], ['อาชีพ', $p['father_job'] ?? '', 1.4], ['สถานภาพ', $p['father_st'] ?? '', 1]])
            .$f([['ชื่อ/สกุลมารดา', $p['mother'] ?? '', 2.4], ['อาชีพ', $p['mother_job'] ?? '', 1.4], ['สถานภาพ', $p['mother_st'] ?? '', 1]])
            .$f([['ชื่อ/สกุลผู้ปกครอง', $p['guardian'] ?? '', 2.2], ['อาชีพ', $p['guardian_job'] ?? '', 1.4], ['เกี่ยวข้องเป็น', $p['guardian_rel'] ?? '', 1]])
            .$f([['ที่อยู่ปัจจุบัน บ้านเลขที่', $p['house'] ?? '', 1.4], ['หมู่ที่', $p['moo'] ?? '', 0.6], ['ตำบล/แขวง', $p['tambon'] ?? '', 1.4]])
            .$f([['อำเภอ/เขต', $p['amphoe'] ?? '', 1.4], ['จังหวัด', $p['province'] ?? '', 1.4], ['รหัสไปรษณีย์', $p['zip'] ?? '', 1]]).$f([['โทรศัพท์/มือถือ ติดต่อ', $p['phone'] ?? '', 1]]).'</div>';
    } elseif ($tab === 5) {
        $o = '<div class="doc"><div class="ctr bold" style="font-size:28px;margin-bottom:10px">บันทึกการเจริญเติบโต</div>';
        foreach ($levels as $n) {
            $g = json_decode((string) md_get('pp6g', $sid, $n), true) ?: [];
            $o .= '<div class="bold" style="margin-top:10px">ชั้น'.h(pp6_level_name($lt, $n)).'</div><table class="t" style="font-size:16px"><tr><th style="width:160px"></th>';
            foreach (PP6_MONTHS as $m => $l) {
                $o .= '<th>'.$l.'</th>';
            }
            $o .= '</tr>';
            foreach (['age' => 'อายุ', 'weight' => 'น้ำหนัก', 'height' => 'ส่วนสูง'] as $k => $l) {
                $o .= '<tr><td class="c">'.$l.'</td>';
                foreach (array_keys(PP6_MONTHS) as $m) {
                    $o .= '<td class="c">'.h((string) ($g[$m][$k] ?? '')).'</td>';
                }
                $o .= '</tr>';
            }
            $o .= '</table>';
        }
        $ref = [['ชาย', [15.5, 17, 18, 20, 22, 24, 27, 29, 33, 37, 43, 46], [106, 111, 116, 120, 125, 125, 135, 140, 145, 153, 159, 163]], ['หญิง', [15, 17, 18, 20, 23, 25, 28, 32, 37, 40, 43, 44], [106, 110, 115, 120, 126, 126, 137, 143, 148, 152, 153, 154]]];
        $o .= '<div class="ctr bold" style="margin:20px 0 4px">ตารางแสดงน้ำหนักและส่วนสูงของเด็กไทยโดยเฉลี่ย</div><table class="t"><tr><th colspan="2">อายุ/ปี</th>'.implode('', array_map(fn($a) => '<th>'.$a.'</th>', range(5, 16))).'</tr>';
        foreach ([1 => 'น้ำหนัก<br>(กก.)', 2 => 'ส่วนสูง<br>(ซม.)'] as $i => $l) {
            foreach ($ref as $j => $r) {
                $o .= '<tr>'.($j === 0 ? '<td rowspan="2" class="c">'.$l.'</td>' : '').'<td class="c">'.$r[0].'</td>'.implode('', array_map(fn($v) => '<td class="c">'.$v.'</td>', $r[$i])).'</tr>';
            }
        }
        $body = $o.'</table></div>';
    } elseif ($tab === 6) {
        $years = pp6_years($s);
        $year = isset($years[(int) ($_GET['Year'] ?? 0)]) ? (int) $_GET['Year'] : array_key_first($years);
        $term = (int) ($_GET['Term'] ?? 1) === 2 ? 2 : 1;
        $stab = max(1, min(3, (int) ($_GET['subtab'] ?? 1)));
        $tabs .= '<div class="tabs sub">';
        foreach ($years as $y => $n) {
            $tabs .= '<a href="'.$base.'&Tab=6&Year='.$y.'&Term=1&subtab=1"'.($y === $year ? ' class="on"' : '').'>ปีการศึกษา '.$y.'</a>';
        }
        $tabs .= '</div><div class="tabs sub">';
        foreach ([1, 2] as $t) {
            foreach ([1 => 'ผลการเรียนรู้ '.$t.'/'.$year, 2 => 'อ่านคิดเขียน '.$t, 3 => 'คห. '.$t.'/'.$year] as $k => $l) {
                $tabs .= '<a href="'.$base.'&Tab=6&Year='.$year.'&Term='.$t.'&subtab='.$k.'"'.($t === $term && $k === $stab ? ' class="on"' : '').'>'.$l.'</a>';
            }
        }
        $tabs .= '</div>';
        $lvn = $years[$year];
        $who = '<u style="text-decoration:none;border-bottom:1px dotted #000">'.h(trim(preg_replace('/^(เด็กชาย|เด็กหญิง|นางสาว|นาย|นาง|ด\.ช\.|ด\.ญ\.)/u', '$1 ', (string) $s['name']))).'</u> เลขประจำตัว <u style="text-decoration:none;border-bottom:1px dotted #000">&nbsp;'.h($s['student_id']).'&nbsp;</u> ระดับชั้น <u style="text-decoration:none;border-bottom:1px dotted #000">&nbsp;'.h($lt.'.'.$lvn.'/'.$roomNo).'&nbsp;</u> เลขที่ <u style="text-decoration:none;border-bottom:1px dotted #000">&nbsp;&nbsp;'.h((string) $s['number']).'&nbsp;&nbsp;</u>';
        $yt = $year.'/'.$term;
        if ($stab === 1) {
            $gs = pp6_term_grades($sid, $year, $term);
            $att = rpt_attendance(array_map(fn($g) => (int) $g['course_id'], $gs));
            $tr = '';
            $acts = '';
            foreach ([1 => 'รายวิชาพื้นฐาน', 2 => 'รายวิชาเพิ่มเติม'] as $type => $label) {
                $tr .= '<tr><td></td><td class="bold">'.$label.'</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
                foreach ($gs as $g) {
                    if ((int) $g['ctype'] !== $type) {
                        continue;
                    }
                    $fix = json_decode((string) md_get('remedial', 'g'.(int) $g['id'], $yt), true) ?: [];
                    $pct = $att[(int) $g['course_id']][$sid] ?? null;
                    $has = $g['midterm'] !== null || $g['final'] !== null;
                    $tr .= '<tr><td class="c">'.h($g['course_code']).'</td><td>'.h($g['course_name']).'</td><td class="c">'.h(rp_num($g['credit'])).'</td><td class="c">'.($pct === null ? '100' : number_format($pct, 0)).'</td><td class="c">100</td>'
                        .'<td class="c">'.($has ? (int) $g['midterm'] + (int) $g['final'] : '').'</td><td class="c">'.h((string) ($fix['old'] ?? $g['grade'])).'</td><td class="c">'.h((string) ($fix['new'] ?? '')).'</td></tr>';
                }
            }
            foreach ($gs as $g) {
                if ((int) $g['ctype'] === 9) {
                    $acts .= '<tr><td>'.h($g['course_name']).'</td><td class="c">'.h((string) $g['period']).'</td><td class="c">'.h((string) $g['grade']).'</td></tr>';
                }
            }
            $gpa = gpa_of(array_map(fn($g) => ['grade' => $g['grade'], 'credit' => $g['credit']], array_filter($gs, fn($g) => (int) $g['ctype'] !== 9)));
            $body = '<div class="doc"><div style="display:flex;align-items:center;gap:8px;margin-left:100px"><img src="'.$logo.'" style="width:52px"/><div><div style="font-size:26px;font-weight:bold;line-height:1">'.h($school).'</div>'
                .h(cfg('address', '7 หมู่ 3 ต.เวียงพางคำ อ.แม่สาย จ.เชียงราย').' โทรศัพท์ '.cfg('phone', '0-5373-1268')).'</div></div><hr style="border:0;border-top:1px solid #000;margin:8px 0"/>'
                .'<div class="ctr bold">รายงานการวัดประเมินผลการเรียนรู้ ระดับชั้น <u style="text-decoration:none;border-bottom:1px dotted #000">'.h(pp6_level_name($lt, $lvn)).'</u><br>ปลายภาคเรียนที่ '.$term.' ปีการศึกษา '.$year.'<br>'.$who.'</div>'
                .'<table class="t" style="margin-top:8px"><tr><th rowspan="3" style="width:80px">รหัสวิชา</th><th rowspan="3">รายวิชา</th><th colspan="6">การวัดผลและประเมินผลการเรียนรู้</th></tr>'
                .'<tr><th rowspan="2" style="width:80px">น.น.</th><th style="width:80px">เวลาเรียน</th><th style="width:80px">คะแนน</th><th style="width:80px">คะแนน</th><th rowspan="2" style="width:80px">ผลการ<br>ประเมิน</th><th rowspan="2" style="width:80px">แก้ไข</th></tr>'
                .'<tr><th>(ร้อยละ)</th><th>เต็ม</th><th>ได้</th></tr>'.$tr.'</table>'
                .'<table class="t" style="border-top:none"><tr><td class="r bold" style="border-top:none">เกรดเฉลี่ย ภาคเรียนที่ '.$term.'</td><td class="c bold" style="width:160px;border-top:none">'.($gpa === null ? '-' : number_format($gpa, 2)).'</td></tr></table>'
                .'<div class="ctr bold" style="margin-top:10px">ผลการประเมินกิจกรรมพัฒนาผู้เรียน</div><table class="t"><tr><th>กิจกรรมพัฒนาผู้เรียน</th><th style="width:140px">เวลา (ชั่วโมง)</th><th style="width:140px">ผลการประเมิน</th></tr>'
                .($acts ?: '<tr><td>&nbsp;</td><td></td><td></td></tr>').'</table>'.pp6_sign($s).'</div>';
        } elseif ($stab === 2) {
            $ch = pp6_scores('char', $sid, $yt);
            $sk = pp6_scores('skill', $sid, $yt);
            $tr = '';
            $sum = 0;
            $max = 0;
            foreach (SCORE_SETS['char']['items'] as $k => $name) {
                $mul = PP6_CHAR_IND[$k] ?? 1;
                $max += 3 * $mul;
                $v = isset($ch[$k]) ? $ch[$k] * $mul : null;
                $sum += (int) $v;
                $tr .= '<tr><td class="c">'.($k + 1).'</td><td>'.h($name).'</td><td class="c">'.(3 * $mul).'</td><td class="c">'.($v ?? '').'</td></tr>';
            }
            $lvTxt = fn(array $sc) => $sc ? (int) round(array_sum($sc) / count($sc)) : null;
            $cl = $lvTxt($ch);
            $str = '';
            $ssum = 0;
            foreach (SCORE_SETS['skill']['items'] as $k => $name) {
                $ssum += (int) ($sk[$k] ?? 0);
                $str .= '<tr><td class="c">'.($k + 1).'</td><td>'.h($name).'</td><td class="c">3</td><td class="c">'.($sk[$k] ?? '').'</td></tr>';
            }
            $sl = $lvTxt($sk);
            [$open, $come, $abs, $leave, $sick] = pp6_days($sid, $year, $term);
            $lvName = [3 => 'ดีเยี่ยม', 2 => 'ดี', 1 => 'ผ่านเกณฑ์การประเมิน', 0 => 'ไม่ผ่านเกณฑ์การประเมิน'];
            $body = '<div class="doc"><div class="ctr"><img src="'.$logo.'" style="width:52px"/><div class="bold">รายงานผลการประเมินคุณลักษณะอันพึงประสงค์ และผลการประเมินการอ่าน การคิดวิเคราะห์ การเขียนสื่อความ<br>ภาคเรียนที่ '.$term.' ปีการศึกษา '.$year.'<br>'.$who.'</div></div>'
                .'<table class="t" style="margin-top:8px"><tr><th style="width:40px">ข้อที่</th><th>คุณลักษณะอันพึงประสงค์</th><th style="width:80px">คะแนนเต็ม</th><th style="width:80px">ผลประเมิน</th></tr>'.$tr
                .'<tr><td></td><td class="r bold">รวม</td><td class="c">'.$max.'</td><td class="c">'.($ch ? $sum : '').'</td></tr><tr><td></td><td class="r bold">ระดับคุณภาพ</td><td colspan="2" class="c">'.($cl ?? '').'</td></tr>'
                .'<tr><td></td><td class="r bold">ผลการประเมิน</td><td colspan="2" class="c">'.($cl === null ? '' : $lvName[$cl]).'</td></tr></table>'
                .'<table class="t" style="margin-top:8px"><tr><th style="width:40px">ข้อที่</th><th>ความสามารถในการอ่าน คิดวิเคราะห์และเขียนสื่อความ</th><th style="width:80px">คะแนนเต็ม</th><th style="width:80px">ผลประเมิน</th></tr>'.$str
                .'<tr><td></td><td class="r bold">รวม</td><td class="c">'.(3 * count(SCORE_SETS['skill']['items'])).'</td><td class="c">'.($sk ? $ssum : '').'</td></tr><tr><td></td><td class="r bold">ระดับคุณภาพ</td><td colspan="2" class="c">'.($sl ?? '').'</td></tr>'
                .'<tr><td></td><td class="r bold">ผลการประเมิน</td><td colspan="2" class="c">'.($sl === null ? '' : $lvName[$sl]).'</td></tr></table>'
                .'<div class="ctr bold" style="margin-top:10px">บันทึกเวลาเรียน</div><table class="t"><tr><th rowspan="2">ภาคเรียน</th><th colspan="6">'.h(pp6_level_name($lt, $lvn)).'</th></tr><tr><th>เปิดเรียน (วัน)</th><th>มาเรียน</th><th>ขาด</th><th>ลา</th><th>ป่วย</th><th>ร้อยละ</th></tr>'
                .'<tr><td class="c">ภาคเรียนที่ '.$term.'</td><td class="c">'.$open.'</td><td class="c">'.$come.'</td><td class="c">'.$abs.'</td><td class="c">'.$leave.'</td><td class="c">'.$sick.'</td><td class="c">'.($open ? number_format($come * 100 / $open, 0) : 0).'</td></tr></table>'.pp6_sign($s).'</div>';
        } else {
            $cm = json_decode((string) md_get('pp6c', $sid, $yt), true) ?: [];
            if (!$cm && ($old = json_decode((string) md_get('pp6', $sid, $yt), true))) {
                $cm['learn'] = $old['comment'] ?? '';
            }
            $tr = '';
            foreach (PP6_COMMENT as $k => $l) {
                $tr .= '<tr><td class="c" style="vertical-align:top;height:96px">'.$l.'</td><td style="vertical-align:top">'.nl2br(h((string) ($cm[$k] ?? ''))).'</td></tr>';
            }
            $dots = str_repeat('<div style="border-bottom:1px dotted #000;height:30px"></div>', 4);
            $body = '<div class="doc"><div class="ctr"><img src="'.$logo.'" style="width:52px"/><div class="bold">ความคิดเห็นเกี่ยวกับนักเรียน ระดับชั้น'.h(pp6_level_name($lt, $lvn)).'<br>ภาคเรียนที่ '.$term.' ปีการศึกษา '.$year.'</div>'
                .'<div class="bold" style="margin-top:10px">ความคิดเห็นของครูประจำชั้น</div></div><table class="t"><tr><th style="width:160px;background:#EEE">เรื่อง</th><th style="background:#EEE">ภาคเรียนที่ '.$term.'</th></tr>'.$tr.'</table>'.pp6_sign($s)
                .'<div class="ctr bold" style="margin-top:20px">ความคิดเห็นของผู้ปกครอง</div><table class="t"><tr><th style="background:#EEE">ภาคเรียนที่ '.$term.'</th></tr><tr><td style="padding:10px 20px">'.$dots
                .'<div style="text-align:center;margin:24px 0 0 300px">ลงชื่อ ........................................ ผู้ปกครอง<br>( ........................................ )</div></td></tr></table></div>';
        }
    } else {
        $box = fn(array $opts) => implode('', array_map(fn($o) => '<div style="display:inline-block;margin-right:22px"><span class="chk"></span>'.$o.'</div>', $opts));
        $four = '<div><span class="chk"></span>ดีเยี่ยม &nbsp; <span class="chk"></span>ดี</div><div><span class="chk"></span>ผ่านเกณฑ์การประเมิน</div><div><span class="chk"></span>ไม่ผ่านเกณฑ์การประเมิน</div>';
        $adv = pp6_advisors($s);
        $sub8 = ['ภาษาไทย', 'คณิตศาสตร์', 'วิทยาศาสตร์และเทคโนโลยี', 'สังคมศึกษา ศาสนาและวัฒนธรรม', 'สุขศึกษาและพลศึกษา', 'ศิลปะ', 'การงานอาชีพ', 'ภาษาต่างประเทศ'];
        $body = '<div class="doc" style="width:984px"><div class="ctr bold" style="font-size:21px">การประเมินกลุ่มสาระการเรียนรู้</div><div class="bold" style="margin-top:6px">ผลการประเมินการเรียนตลอดหลักสูตร</div>'
            .'<table class="t"><tr><td class="c" style="width:540px">การประเมินผลการเรียนระดับชั้น</td><td class="c" style="width:400px">ผลการตัดสิน</td><td rowspan="6" class="c" style="vertical-align:middle">( '.h(end($adv)[2] ?? '&nbsp;&nbsp;&nbsp;&nbsp;').' )<br><br>ครูประจำชั้น</td></tr>'
            .'<tr><td>ผลการเรียนรายวิชา</td><td>'.$box(['ผ่าน', 'ไม่ผ่าน']).'</td></tr><tr><td>ผลการประเมินการอ่าน คิดวิเคราะห์และเขียน</td><td>'.$four.'</td></tr>'
            .'<tr><td>ผลการประเมินคุณลักษณะอันพึงประสงค์</td><td>'.$four.'</td></tr><tr><td>ผลการประเมินกิจกรรมพัฒนาผู้เรียน</td><td>'.$box(['ผ่าน', 'ไม่ผ่าน']).'</td></tr>'
            .'<tr><td colspan="2" class="c"><b>สรุปผลการประเมิน</b> &nbsp; '.$box(['ได้เลื่อนชั้น', 'ไม่ผ่านการเลื่อนชั้น']).'</td></tr>'
            .'<tr><td colspan="3" class="c" style="padding:16px">( '.h(rpt_signer('ผู้อำนวยการ') ?: '........................................').' )<br><br>อนุมัติผลการเรียน<br>ผู้อำนวยการโรงเรียน</td></tr></table>'
            .'<div class="bold" style="margin-top:12px">ผลการประเมินคุณภาพการศึกษาขั้นพื้นฐานระดับชาติ</div><table class="t"><tr><th rowspan="2">กลุ่มสาระการเรียนรู้</th><th colspan="3">ผลการประเมิน</th><th rowspan="2">หมายเหตุ</th></tr><tr><th>คะแนนเต็ม</th><th>คะแนนที่ได้</th><th>คิดเป็นร้อยละ</th></tr>'
            .implode('', array_map(fn($x) => '<tr><td>'.$x.'</td><td class="c">100</td><td></td><td></td><td></td></tr>', $sub8)).'</table>'
            .'<div class="bold" style="margin-top:12px">สัดส่วนผลการเรียนและผลการทดสอบระดับชาติ</div><table style="margin-left:40px;font-size:18.67px"><tr><td>ร้อยละ</td><td class="c" style="width:50px">80</td><td>ของผลการเรียนเฉลี่ยตลอด 3 ปี ('.($lt === 'ป' ? 'ป.4-6' : ($ln >= 4 ? 'ม.4-6' : 'ม.1-3')).')</td><td>=</td><td style="width:120px;border-bottom:1px dotted #000"></td></tr>'
            .'<tr><td>ร้อยละ</td><td class="c">20</td><td>ของผลการทดสอบทางการศึกษาระดับชาติขั้นพื้นฐาน</td><td>=</td><td style="border-bottom:1px dotted #000"></td></tr><tr><td colspan="3" class="r">ผลการเรียนเฉลี่ยรวม</td><td>=</td><td style="border-bottom:1px dotted #000"></td></tr></table></div>';
    }
    pp6_page($s, $sub, $tabs, $body, $margin);
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
