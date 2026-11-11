<?php
/**
 * mis/app/attend.php
 * บันทึกเวลาเรียนแบบรายห้อง (ตามระบบเดิม): ตารางรายสัปดาห์ 22 สัปดาห์ (แท็บละ 4 สัปดาห์)
 *  - คลิกไอคอนปฏิทินของวัน -> กำหนด "มีการเรียนการสอน (จำนวนคาบ/คาบที่สอน ไม่เกิน 3 คาบ)" หรือ "ไม่มีการเรียนการสอน"
 *  - วันที่มีการสอน: ช่องสถานะของนักเรียนตามจำนวนคาบ คลิกเพื่อเปลี่ยน ม ข ล ป ส ก น ซ
 *  md att_hour  r1=รายวิชา r2=ห้อง r3=วันที่  value={"t":1,"p":[คาบ...]} | {"t":0}
 * กิจกรรมพัฒนาผู้เรียน: ฝ่ายวิชาการลงทะเบียนกิจกรรมให้นักเรียนทั้งห้อง แล้วครูประจำชั้นของห้องบันทึกผล
 *  md act_hr    r1=grade.id  value=ชั่วโมงที่เข้าร่วม   md act_fail r1=grade.id value=1 (ไม่ผ่านการปฏิบัติกิจกรรม)
 */
defined('MIS') or exit;

const ATT_WEEKS = 22;
const ATT_MONTHS = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
const ATT_WD = ['จ', 'อ', 'พ', 'พฤ', 'ศ'];

/* ---------------------------------------------------------------- ข้อมูลวัน/คาบ */

/** วันจันทร์ของสัปดาห์แรกของภาคเรียน (Y-m-d) */
function att_week1(): string
{
    [$from] = term_range();
    $t = strtotime($from);
    $w = (int) date('N', $t);
    return date('Y-m-d', $w >= 6 ? strtotime('+'.(8 - $w).' days', $t) : strtotime('-'.($w - 1).' days', $t));
}

/** วันที่ของสัปดาห์ที่ $w (1..22) วันที่ $d (0=จันทร์..4=ศุกร์) */
function att_dayof(string $w1, int $w, int $d): string
{
    return date('Y-m-d', strtotime($w1.' +'.(($w - 1) * 7 + $d).' days'));
}

/** หน้าแท็บ (1..6) ที่มีวันที่ $day */
function att_page_of(string $w1, string $day): int
{
    $w = intdiv((int) floor((strtotime($day) - strtotime($w1)) / 86400), 7) + 1;
    $w = max(1, min(ATT_WEEKS, $w));
    return intdiv($w - 1, 4) + 1;
}

/** การเรียนการสอนรายวัน [date => ['t'=>1|0,'p'=>[...]]] */
function att_hours(int $course, int $room): array
{
    $o = [];
    foreach (md_rows('att_hour', $course, $room) as $r) {
        $v = json_decode((string) $r['value'], true);
        if (is_array($v)) {
            $o[(string) $r['r3']] = ['t' => (int) ($v['t'] ?? 0), 'p' => array_values(array_map('intval', (array) ($v['p'] ?? [])))];
        }
    }
    ksort($o);
    return $o;
}

/** จำนวนคาบที่สอนแล้ว */
function att_hours_done(array $hours): int
{
    return array_sum(array_map(fn($h) => $h['t'] ? count($h['p']) : 0, $hours));
}

/* ---------------------------------------------------------------- หน้าบันทึกเวลาเรียน */

function rows_task_time_attendance_byroom_Attendance()
{
    $c = att_course();
    if (!$c) {
        redirect('?module=task&option=time_attendance_byroom');
    }
    att_ensure();
    $cid = (int) $c['id'];
    $room = (int) ($_GET['RoomNum'] ?? 0);
    $today = date('Y-m-d');
    $day = att_day();
    $w1 = att_week1();
    $page = isset($_GET['Page']) && (int) $_GET['Page'] >= 1 ? min(6, (int) $_GET['Page']) : att_page_of($w1, $day);
    $base = '?module=task&option=time_attendance_byroom&func=Attendance&TeacherId='.(int) $c['teacher_id'].'&SubjectId='.rawurlencode($c['course_code']).'&SubjectNum='.$cid.'&AttendType=subject&Elective=0';
    $tpl = (string) file_get_contents(MIS_APP.'pages/task_time_attendance_byroom_Attendance.html');
    $hours = att_hours($cid, $room);

    $rooms = course_rooms([$c])[$cid] ?? [];
    $opts = '';
    foreach ($rooms as $r => $n) {
        $opts .= '<option value="'.h($base.'&RoomNum='.$r.'&actday=').'"'.($r === $room ? " selected='selected'" : '').'>'.h($c['course_code'].' - '.class_room_short($c['class'], $r)).'</option>';
    }
    $done = att_hours_done($hours);
    $total = (int) $c['period'];
    $rep = [
        '/(<select name="SelectAttendance"[^>]*>).*?(<\/select>)/s' => '$1'.str_replace('$', '\\$', $opts).'$2',
        '/ระดับชั้นมัธยมศึกษาปีที่ \S+/u' => 'ระดับชั้น'.h(cat('class', $c['class'])).'/'.h(preg_replace('/^\d+\//', '', room_short($room))),
        '/รายวิชา ส21105 ประวัติศาสตร์/u' => 'รายวิชา '.h($c['course_code'].' '.$c['course_name']),
        '/ประเภท รายวิชาพื้นฐาน/u' => 'ประเภท รายวิชา'.(COURSE_TYPES[(int) $c['type']] ?? ''),
        '/จำนวนหน่วยกิต [\d.]+/u' => 'จำนวนหน่วยกิต '.h((float) $c['credit']),
        '/<span style=\'float:left;width:50px\'>0\/20<\/span>/' => "<span style='float:left;width:50px'>$done/$total</span>",
        '/(80 %<\/span><span style=\'float:left;width:50px\'>)16(<\/span>)/' => '${1}'.(int) ceil($total * 0.8).'$2',
        '/วัน\S+, \d+&nbsp;\S+&nbsp;\d{4}/u' => 'วัน'.TH_DAYS[(int) date('w', strtotime($day))].', '.th_date($day),
        '/goUrl:"[^"]*"/' => 'goUrl:"'.$base.'&actday=&RoomNum='.$room.'"',
        '/disabledDays:\[[^\]]*\]/' => 'disabledDays:[0,0,0,0,0,1,1]',
        "/\\?module=task&option=time_attendance_byroom&RoomNum=\\d+&SubjectId=[^']*'/u" => '?module=task&option=time_attendance_byroom\'',
        "/ReportPortrait\\('print_portrait\\.php[^']*'\\);/" => "location.href='?module=task&option=report_attendance';",
        '/var act = "[^"]*";/' => 'var act = "'.str_replace('func=Attendance', 'func=SaveSubjectAttendTime', $base).'&RoomNum='.$room.'&Page='.$page.'";',
        "/confirm_single\\('[^']*'/" => "confirm_single('".$base."&RoomNum=".$room."'",
    ];
    $tpl = preg_replace(array_keys($rep), array_values($rep), $tpl);
    $msg = $_GET['msg'] ?? '';
    $box = fn($text, $ok) => "<div id='msg_info'><div class='main_msg' style='width:100%'><table border='0' cellspacing='0' cellpadding='0' class='"
        .($ok ? 'frame_green_msg' : 'frame_red_msg')."' style='width:983px'><tr><td width=20><img src='../images/".($ok ? '1347351892_yes.png' : '001_11.png')."' border=0 align=middle></td><td align='left' style='text-align:left'>&nbsp;&nbsp;<div style='margin-top:-15px'>&nbsp;&nbsp;&nbsp;$text</div></td></tr></table></div></div>";
    $newBox = $msg === 'saved' ? $box('บันทึกเวลาเรียนเรียบร้อยแล้ว', true) : ($msg === 'hour' ? $box('บันทึกการเรียนการสอนประจำวันเรียบร้อยแล้ว', true) : '');
    $tpl = preg_replace("/<div id='msg_info'>.*?<\\/table><\\/div><\\/div>/s", str_replace('$', '\\$', $newBox), $tpl, 1);

    // แท็บสัปดาห์
    $grid = "\n".'<div id="tabs_area" style="display: table-row"><div style="width:959px;text-align:center">';
    for ($p = 1; $p <= 6; $p++) {
        $ws = ($p - 1) * 4 + 1;
        $we = min($ws + 3, ATT_WEEKS);
        $grid .= '<div style="display: table-cell;float:left;margin-top:10px'.($p > 1 ? ';margin-left:3px' : '').'"><a href="'.h($base.'&actday=&RoomNum='.$room.'&week='.$ws.'&shweek='.($p > 1 ? $ws - 1 : '').'&Page='.$p).'" class="button small '.($p === $page ? 'green' : 'gray').'">'
            .'<h1 class="thaisansregular" style="font-size:21px">สัปดาห์ที่ '.$ws.($p === 1 ? '- ' : '-').$we.'</h1></a></div>';
    }
    $grid .= '</div></div><div style="width:984px;text-align:center"><div id="line1" style="display: table-row;width:984px;margin-top:-2px;border-top:2px #00BBBB solid;z-index:2;position:absolute">&nbsp;</div></div>';

    // วันในแท็บ
    $ws = ($page - 1) * 4 + 1;
    $we = min($ws + 3, ATT_WEEKS);
    $days = [];
    for ($w = $ws; $w <= $we; $w++) {
        for ($d = 0; $d < 5; $d++) {
            $days[] = [att_dayof($w1, $w, $d), $w, $w - $ws + 1, $d];
        }
    }
    $marks = [];
    foreach (q('SELECT `date`,`period`,`student_id`,`status` FROM {attendance} WHERE `course_id`=? AND `room`=? AND `date` BETWEEN ? AND ?', [$cid, $room, $days[0][0], end($days)[0]]) as $r) {
        $marks[(string) $r['date']][(int) $r['period']][(int) $r['student_id']] = (string) $r['status'];
    }
    $code = array_flip(ATT_CODES);
    $months = [];
    foreach ($days as [$dt]) {
        $m = (int) substr($dt, 5, 2);
        $k = substr($dt, 0, 7);
        $months[$k] = [$m, ($months[$k][1] ?? 0) + 1];
    }
    $hd = fn($t, $x = '') => '<td rowspan="5" class="head_table" style="min-width:1px;white-space:nowrap'.$x.'">'.$t.'</td>';
    $tb = '<table class="month" style="width:984px;margin-top:3px;"><tr class="tr_thick">'.$hd('เลขที่').$hd('รหัส').'<td rowspan="5" class="head_table" style="min-width:120px;white-space:nowrap;display:none">เลขประจำตัวประชาชน</td>'.$hd('ชื่อ-นามสกุล')
        .'<td class="head_name" style="height:30px">เดือน</td>';
    foreach ($months as [$m, $n]) {
        $tb .= '<td colspan="'.$n.'" class="head_table">'.ATT_MONTHS[$m].' </td>';
    }
    $tb .= '</tr><tr class="tr_thick"><td class="head_name" style="width:40px;height:30px">สัปดาห์</td>';
    for ($w = $ws; $w <= $we; $w++) {
        $tb .= '<td colspan="5" class="th">'.$w.'</td>';
    }
    $tb .= '</tr><tr class="tr_days"><td class="head_name" style="height:30px;width:40px">วัน</td>';
    foreach ($days as [$dt, $w, $wi, $d]) {
        $tb .= '<td class="th'.$wi.'" style="width:27px;height:25px;padding-top:5px">'.ATT_WD[$d].'</td>';
    }
    $tb .= '</tr> <tr> <td class="head_name" style="height:30px;width:40px">วันที่</td>';
    foreach ($days as [$dt, $w, $wi]) {
        $tb .= '<td class="td'.$wi.'" style="width:27px;text-align:center;color: '.($dt === $today ? '#EA9500;font-weight:bold' : '#000').'">'.(int) substr($dt, 8, 2).'</td> ';
    }
    $tb .= '</tr> <tr><td class="head_name" style="height:30px">คาบ</td>';
    foreach ($days as $i => [$dt, $w, $wi]) {
        if ($dt > $today) {
            $tb .= '<td class="td_empty" style="width:27px;text-align:center"></td>';
            continue;
        }
        $hr = $hours[$dt] ?? null;
        $url = 'facebox.php?module=task&option=time_attendance_byroom&func=SubjectAttendHour&Term=&Order='.($i + 1).'&Dates='.$dt.'&RoomNum='.$room.'&Elective=0&SubjectNum='.$cid.'&TeacherId='.(int) $c['teacher_id'];
        [$icon, $color, $tip] = !$hr ? ['fa-calendar-check-o', '', ''] : ($hr['t'] ? ['fa-calendar-check-o', ';color:#090', 'มีการเรียนการสอน '.count($hr['p']).' คาบ (คาบที่ '.implode(', ', $hr['p']).')'] : ['fa-calendar-times-o', ';color:#CC0000', 'ไม่มีการเรียนการสอน']);
        $tb .= '<td class="td'.$wi.'" style="width:27px;text-align:center;text-valign:middle"><a href="javascript:void(0);" onclick="GetLink(\''.h($url).'\');"'.($tip ? ' title="'.h($tip).'"' : '').'><i class="fa '.$icon.'" style="font-size:18px'.$color.'"></i></a>'
            .($hr && $hr['t'] ? '<div style="font-size:11px;line-height:11px;color:#090">'.implode(',', $hr['p']).'</div>' : '').'</td>';
    }
    $tb .= '</tr> ';
    $students = \Attendance\Sheet\Model::students($cid, $room);
    foreach ($students as $s) {
        $sid = (int) $s['id'];
        [$pre, $fn, $sn] = split_name((string) $s['name']);
        $tb .= '<tr class="tr" style="hieght:20px"><td style="width:30px;text-align:center" class="content_list">'.h((string) $s['number']).'<a name="'.h((string) $s['code']).'"></a></td>'
            .'<td style="width:40px;text-align:center" class="content_list">'.h((string) $s['code']).'</td><td style="width:90px;text-align:center;display:none" class="content_list"></td>'
            .'<td style="width:240px" class="content_list"><span style="float:left;min-width:120px">'.h($pre.$fn).'</span><span style="float:left;padding-left:10px">'.h($sn).'</span></td><td class="head_name" style="height:20px">&nbsp;</td>';
        foreach ($days as [$dt, $w, $wi]) {
            $hr = $dt <= $today ? ($hours[$dt] ?? null) : null;
            if (!$hr || !$hr['t']) {
                $tb .= '<td class="td'.$wi.' attendance_regular" style="padding:5px 3px 5px 3px'.($hr ? ';background-color:#EEEEEE' : '').'">&nbsp;</td>';
                continue;
            }
            $cell = '';
            foreach ($hr['p'] as $p) {
                $saved = $marks[$dt][$p] ?? null;
                $v = $saved === null ? 'ม' : ($code[$saved[$sid] ?? ''] ?? '');
                $id = 'a'.str_replace('-', '', $dt).'_'.$p.'_'.$sid;
                $cell .= '<div style="margin:1px 0"><input type="text" readonly="readonly" name="att['.$dt.']['.$p.']['.$sid.']" id="'.$id.'" value="'.h($v).'" class="'.($v !== '' ? ATT_CLASS[$v] : '').'" title="คาบที่ '.$p.'" onclick="ChangeVal(\''.$id.'\', this.value==\'\'?\'ซ\':this.value);"/></div>';
            }
            $tb .= '<td class="td'.$wi.' attendance_regular" style="padding:2px 1px 2px 1px">'.$cell.'</td>';
        }
        $tb .= '</tr>';
    }
    if (!$students) {
        $tb .= '<tr><td colspan="'.(5 + count($days)).'" class="content_list" style="text-align:center;height:30px">ยังไม่มีนักเรียนลงทะเบียนในรายวิชานี้</td></tr>';
    }
    $tb .= '</table>';
    $grid .= '<div id="calendar" style="display: table-row">'.$tb.'</div>'
        .'<div class="formframefooter" style="width:972px"><div style="padding-top:4px"><span style="float:left;font-size:12px;margin:4px 0 0 6px;color:#555">ม=มาเรียน ข=ขาดเรียน ล=ลากิจ ป=ลาป่วย ส=มาสาย ก=กิจกรรม น=หนี ซ=ซ่อมเสริม (คลิกที่ช่องเพื่อเปลี่ยนสถานะ)</span>'
        .'<span style="float:right;width:150px;text-align:right;margin-right:10px"><a href="javascript:onclick=chknull()" id="Savebtns"><img src="../icon_tools/vec_add_white.png" border="0" height="18" style="margin-top:-5px"/> บันทึกเวลาเรียน</a></span></div></div>';
    $tpl = preg_replace('/(<\/DIV>\s*<script language="JavaScript">\s*function chknull)/', str_replace('$', '\\$', $grid).'$1', $tpl, 1);
    return ['__CONTENT__' => $tpl];
}

function act_task_time_attendance_byroom_SaveSubjectAttendTime(): void
{
    $c = att_course();
    if (!$c || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        redirect('?module=task&option=time_attendance_byroom');
    }
    att_ensure();
    $cid = (int) $c['id'];
    $room = (int) ($_GET['RoomNum'] ?? 0);
    $hours = att_hours($cid, $room);
    $rooms = [];
    foreach (\Attendance\Sheet\Model::students($cid, $room) as $s) {
        $rooms[(int) $s['id']] = (int) $s['room'];
    }
    foreach ((array) ($_POST['att'] ?? []) as $dt => $byP) {
        $dt = (string) $dt;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt) || empty($hours[$dt]['t']) || $dt > date('Y-m-d')) {
            continue;
        }
        foreach ((array) $byP as $p => $bySid) {
            if (!in_array((int) $p, $hours[$dt]['p'], true)) {
                continue;
            }
            $marks = [];
            foreach ((array) $bySid as $sid => $v) {
                if (isset($rooms[(int) $sid], ATT_CODES[$v])) {
                    $marks[(int) $sid] = ATT_CODES[$v];
                }
            }
            \Attendance\Sheet\Model::save($cid, $dt, (int) $p, $marks, $rooms, me()['id']);
        }
    }
    redirect('?module=task&option=time_attendance_byroom&func=Attendance&TeacherId='.(int) $c['teacher_id'].'&SubjectId='.rawurlencode($c['course_code']).'&SubjectNum='.$cid
        .'&AttendType=subject&Elective=0&RoomNum='.$room.'&Page='.max(1, min(6, (int) ($_GET['Page'] ?? 1))).'&msg=saved');
}

/** หน้าต่างกำหนดการเรียนการสอนประจำวัน (facebox) */
function fb_task_time_attendance_byroom_SubjectAttendHour(): string
{
    $c = att_course();
    $dt = (string) ($_GET['Dates'] ?? '');
    if (!$c || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt)) {
        return '<div style="padding:20px">ไม่พบรายวิชา</div>';
    }
    $room = (int) ($_GET['RoomNum'] ?? 0);
    $hr = att_hours((int) $c['id'], $room)[$dt] ?? null;
    $teach = !$hr || $hr['t'];
    $ps = $hr['p'] ?? [];
    $chk = '';
    for ($p = 1; $p <= 8; $p++) {
        $chk .= '<div style="float:left;width:35px"><div style="margin-top:6px"><label><div style="display:table-cell;white-space:nowrap;margin-right:10px"><input name="period[]" type="checkbox" id="study_period'.$p.'" class="gray-regular-checkbox" value="'.$p.'" onClick="CheckPeriod();"'.(in_array($p, $ps, true) ? ' checked="checked"' : '').'/> '
            .'<label for="study_period'.$p.'" style="text-align:center;margin:0px 5px 0px 0px"></label></div><div style="display:table-cell;white-space:nowrap"><span class="01">'.$p.'</span></div></label></div></div>';
    }
    $hid = fn($n, $v) => '<input name="'.$n.'" type="hidden" id="'.$n.'" value="'.h((string) $v).'" />';
    return '<script>function submited(){var f=document.assignform,n=0,pc=0;for(var i=0;i<f.elements.length;i++){var e=f.elements[i];if(e.type=="radio"&&e.checked)n++;if(e.type=="checkbox"&&e.checked)pc++;}'
        .'if(n<=0){AlertBox.alert("<br>โปรดเลือกสถานะการเรียนการสอนประจำวัน","");}else if(document.getElementById("have_teach").checked==true&&pc<=0){AlertBox.alert("<br>โปรดเลือกคาบสอน","");}'
        .'else if(document.getElementById("have_teach").checked==true&&(f.hour_time.value<=0||f.hour_time.value=="")){AlertBox.alert("<br>โปรดระบุจำนวนคาบเรียนต่อวัน","hour_time");}'
        .'else if(f.hour_time.value!=""&&isNaN(f.hour_time.value)==true){AlertBox.alert("<br>โปรดระบุจำนวนคาบเรียนต่อวันเป็นตัวเลขเท่านั้น","hour_time");f.hour_time.value="";}'
        .'else if(document.getElementById("have_teach").checked==true&&f.hour_time.value>3){AlertBox.alert("<br>โปรดระบุจำนวนคาบเรียนต่อวันเป็นจำนวนไม่เกิน 3 คาบ","hour_time");f.hour_time.value="";}else{f.submit();}}'
        .'function ShowHidden(t){document.getElementById("hourarea").style.display=(t==1?"":"none");}'
        .'function CheckPeriod(){var f=document.assignform,pc=0;for(var i=0;i<f.elements.length;i++){if(f.elements[i].type=="checkbox"&&f.elements[i].checked)pc++;}f.hour_time.value=pc;}</script>'
        .'<form id="assignform" name="assignform" method="post" action="?module=task&option=time_attendance_byroom&func=SaveSubjectAttendHour">'
        .'<div style="display:table-caption;width:px;margin-top:-10px"><div style="display:table-row"><div class="titleonly" style="width:385px"><span style="float:left;white-space:nowrap"><span id="Listtitle"></span>'
        .'<h1 style="float:left;font-size:20px;margin:0px;margin-top:3px">การเรียนการสอน วัน'.TH_DAYS[(int) date('w', strtotime($dt))].', '.th_date($dt).'</h1></span></div></div>'
        .'<div style="display:table-row"><div class="pagecont" style="width:385px"><div style="margin:20px 0px;text-align:left;margin-left:15px">'
        .'<span style="float:left;width:180px"><label><input type="radio" id="have_teach" name="Teach" class="miniregular-radio" value="1"'.($teach ? ' checked="checked"' : '').' onclick="ShowHidden(this.value)"/><label for="have_teach" style="margin:0px"></label>&nbsp;&nbsp;มีการเรียนการสอน</label></span>'
        .'<span style="float:left"><label><input type="radio" id="have_noteach" name="Teach" class="miniregular-radio" value="0"'.(!$teach ? ' checked="checked"' : '').' onClick="ShowHidden(this.value)"/><label for="have_noteach" style="margin:0px"></label>&nbsp;&nbsp;ไม่มีการเรียนการสอน</label></span></div>'
        .'<div id="hourarea" style="margin:20px 0px;text-align:center;margin-left:15px'.($teach ? '' : ';display:none').'"><div style="display:table-row"><div class="field_name_red" style="display:table-cell;text-align:left;padding-top:25px;color:#000;white-space:nowrap;padding-right:10px">'
        .'จำนวน&nbsp;&nbsp;&nbsp;<input name="hour_time" type="text" id="hour_time" class="general radius redborder borderleftthick_red" style="width:50px" maxlength="1" value="'.count($ps).'">&nbsp;&nbsp;คาบ</div></div>'
        .'<div style="display:table-row"><div class="field_name_red" style="text-align:left;padding-top:10px;color:#000;white-space:nowrap;padding-right:10px">คาบที่สอน&nbsp;&nbsp;</div></div><div style="display:table-row">'.$chk.'</div></div></div></div>'
        .$hid('teacher_id', (int) $c['teacher_id']).$hid('hour_order', (int) ($_GET['Order'] ?? 0)).$hid('hour_date', $dt).$hid('subject_no', (int) $c['id']).$hid('room_no', $room).$hid('elective_status', 0).$hid('cur_hour_time', count($ps))
        .'<div style="display:table-row"><div class="pagenav" style="width:385px;padding:10px 6px"><div style="float:right;margin-top:5px">'
        .'<span style="float:left;width:70px"><a href="javascript:onclick=submited()" id="Savebtns"><img src="../icon_tools/vec_add_white.png" border="0" height="18" style="margin-top:-5px"/> บันทึก</a></span>'
        .'<span style="margin-left:3px;float:left;width:65px"><a href="javascript:onclick=document.forms.assignform.reset();" id="Clearbtns"><img src="../icon_tools/vec_x_white.png" border="0" height="14" style="margin-top:-5px"/> เคลียร์</a></span></div></div></div></div></form>';
}

/** บันทึกการเรียนการสอนประจำวัน */
function act_task_time_attendance_byroom_SaveSubjectAttendHour(): void
{
    $_GET['SubjectNum'] = (int) ($_POST['subject_no'] ?? 0);
    $c = att_course();
    $dt = (string) ($_POST['hour_date'] ?? '');
    $room = (int) ($_POST['room_no'] ?? 0);
    if (!$c || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt) || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        redirect('?module=task&option=time_attendance_byroom');
    }
    att_ensure();
    $cid = (int) $c['id'];
    $rooms = course_rooms([$c])[$cid] ?? [];
    if (isset($rooms[$room]) && $dt <= date('Y-m-d') && (int) date('N', strtotime($dt)) <= 5) {
        if ((string) ($_POST['Teach'] ?? '') === '1') {
            $ps = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['period'] ?? [])), fn($p) => $p >= 1 && $p <= 8)));
            sort($ps);
            $ps = array_slice($ps, 0, 3);
            if ($ps) {
                md_set('att_hour', $cid, $room, $dt, json_encode(['t' => 1, 'p' => $ps]));
                q('DELETE FROM {attendance} WHERE `course_id`=? AND `room`=? AND `date`=? AND `period` NOT IN ('.implode(',', $ps).')', [$cid, $room, $dt]);
            }
        } elseif ((string) ($_POST['Teach'] ?? '') === '0') {
            md_set('att_hour', $cid, $room, $dt, json_encode(['t' => 0]));
            q('DELETE FROM {attendance} WHERE `course_id`=? AND `room`=? AND `date`=?', [$cid, $room, $dt]);
        }
    }
    redirect('?module=task&option=time_attendance_byroom&func=Attendance&TeacherId='.(int) $c['teacher_id'].'&SubjectId='.rawurlencode($c['course_code']).'&SubjectNum='.$cid
        .'&AttendType=subject&Elective=0&RoomNum='.$room.'&Page='.att_page_of(att_week1(), $dt).'&msg=hour');
}

/** หน้าต่าง facebox ของระบบ (mis/edumain/facebox.php) */
function run_facebox(): void
{
    if (!me() || is_student()) {
        http_response_code(403);
        exit;
    }
    $fn = 'fb_'.preg_replace('/[^A-Za-z_]/', '', (string) ($_GET['module'] ?? '')).'_'.preg_replace('/[^A-Za-z0-9_]/', '', (string) ($_GET['option'] ?? '')).'_'.preg_replace('/[^A-Za-z0-9_]/', '', (string) ($_GET['func'] ?? ''));
    mis_headers();
    echo function_exists($fn) ? $fn() : '<div style="padding:20px">ไม่พบหน้าที่ต้องการ</div>';
}

/* ================================================================ กิจกรรมพัฒนาผู้เรียน (ลงทะเบียนรายห้อง / ครูประจำชั้นบันทึกผล) */

/** ห้องที่เป็นครูประจำชั้น (0 = ไม่ได้เป็น) */
function my_advisor_room(): int
{
    $p = me()['personnel'] ?? null;
    return $p ? (int) $p['room'] : 0;
}

/** ครูประจำชั้นของห้องนี้ ที่บันทึกผลกิจกรรมพัฒนาผู้เรียนของห้องได้ */
function act_advisor_of(array $c, int $room): bool
{
    return (int) $c['type'] === 9 && $room > 0 && $room === my_advisor_room();
}

/** กิจกรรมพัฒนาผู้เรียนที่ลงทะเบียนให้ห้องที่เป็นครูประจำชั้น (ภาคเรียนที่เลือกดู) */
function advisor_activities(): array
{
    $room = my_advisor_room();
    if (!$room) {
        return [];
    }
    [$y, $t] = term();
    return q('SELECT C.*, COUNT(G.`id`) n, SUM(G.`grade` IS NOT NULL AND G.`grade`<>\'\') d FROM {course} C JOIN {grade} G ON G.`course_id`=C.`id` AND G.`room`=?'
        .' WHERE C.`type`=9 AND C.`year`=? AND C.`term`=? GROUP BY C.`id` ORDER BY C.`course_code`', [$room, $y, $t]);
}

/** ชั่วโมงที่เข้าร่วมกิจกรรม [grade_id => ชม.] */
function act_hours(array $gids): array
{
    $o = [];
    foreach ($gids ? md_rows('act_hr', $gids) : [] as $r) {
        $o[(int) $r['r1']] = (string) $r['value'];
    }
    return $o;
}

/** ผลการประเมินกิจกรรม: เข้าร่วม 80% ขึ้นไป และผ่านการปฏิบัติกิจกรรม = ผ */
function act_result(?string $hr, int $full, bool $fail): string
{
    if ($hr === null || $hr === '') {
        return '';
    }
    return !$fail && (float) $hr * 100 >= $full * 80 ? 'ผ' : 'มผ';
}

/** ฝ่ายวิชาการ: ลงทะเบียนกิจกรรมพัฒนาผู้เรียนให้นักเรียนทั้งห้อง */
function adm_act_enroll(bool $post)
{
    [$y, $t] = term();
    if ($post && isset($_POST['add_act'])) {
        $code = mb_substr(trim((string) ($_POST['code'] ?? '')), 0, 20);
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 150);
        $class = (int) ($_POST['class'] ?? 0);
        if ($code !== '' && $name !== '' && $class && !q1('SELECT 1 x FROM {course} WHERE `course_code`=? AND `class`=? AND `year`=? AND `term`=?', [$code, $class, $y, $t])) {
            q('INSERT INTO {course} (`course_name`,`course_code`,`teacher_id`,`class`,`period`,`credit`,`type`,`year`,`term`) VALUES (?,?,0,?,?,0,9,?,?)',
                [$name, $code, $class, max(0, (int) ($_POST['hours'] ?? 20)), $y, $t]);
        }
        admin_done('act_enroll');
    }
    $acts = q('SELECT * FROM {course} WHERE `type`=9 AND `year`=? AND `term`=? ORDER BY `class`, `course_code`', [$y, $t]);
    $byc = rooms_by_class();
    if ($post && isset($_POST['save'])) {
        $sel = (array) ($_POST['room'] ?? []);
        foreach ($acts as $c) {
            $cid = (int) $c['id'];
            $want = array_map('intval', (array) ($sel[$cid] ?? []));
            $have = array_map('intval', array_column(q('SELECT DISTINCT `room` FROM {grade} WHERE `course_id`=?', [$cid]), 'room'));
            foreach (array_diff($want, $have) as $room) {
                foreach (q('SELECT S.`id`, S.`number` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`room`=? AND U.`active`=1', [$room]) as $s) {
                    if (!q1('SELECT 1 x FROM {grade} WHERE `course_id`=? AND `student_id`=?', [$cid, (int) $s['id']])) {
                        q('INSERT INTO {grade} (`student_id`,`course_id`,`number`,`room`,`type`) VALUES (?,?,?,?,1)', [(int) $s['id'], $cid, $s['number'], $room]);
                    }
                }
            }
            foreach (array_diff($have, $want) as $room) {
                q('DELETE FROM {grade} WHERE `course_id`=? AND `room`=?', [$cid, $room]);
            }
        }
        admin_done('act_enroll');
    }
    $rows = '';
    foreach ($acts as $i => $c) {
        $cid = (int) $c['id'];
        $cnt = [];
        foreach (q('SELECT `room`, COUNT(*) n FROM {grade} WHERE `course_id`=? GROUP BY `room`', [$cid]) as $r) {
            $cnt[(int) $r['room']] = (int) $r['n'];
        }
        $cb = '';
        foreach ($byc[(int) $c['class']] ?? [] as $rid => $rn) {
            $on = isset($cnt[$rid]);
            $cb .= '<label style="display:inline-block;margin:2px 12px 2px 0;white-space:nowrap"><input type="checkbox" name="room['.$cid.'][]" value="'.(int) $rid.'"'.($on ? ' checked data-had="1"' : '').' style="width:auto;height:auto;vertical-align:-2px"/> '
                .h(class_room_short($c['class'], $rid)).($on ? ' <span class="gray_12">('.$cnt[$rid].')</span>' : '').'</label>';
        }
        $rows .= zrow([$i + 1, h($c['course_code']), h($c['course_name']), h(class_short($c['class'])), [h((string) $c['period']), ' align="center"'],
            $cb ?: '<span class="gray_12">ยังไม่มีห้องเรียนในระดับชั้นนี้</span>', [array_sum($cnt), ' align="center"']], $i);
    }
    $classes = cats('class');
    $add = '<form method="post" action="'.h(a_url('act_enroll')).'" style="width:983px;margin:0 auto 10px;text-align:left"><span class="search">เพิ่มกิจกรรม: รหัส '.inp('code', '', 80).' ชื่อกิจกรรม '.inp('name', '', 220)
        .' ระดับชั้น '.sel('class', $classes ?: ['' => '-'], array_key_first($classes) ?? '').' ชั่วโมง '.inp('hours', '20', 50)
        .' <input type="submit" name="add_act" value="เพิ่มกิจกรรม" class="general radius" style="height:25px"/></span><div style="clear:both"></div></form>';
    $save = '<input type="submit" name="save" value="บันทึกการลงทะเบียน" class="general radius" style="height:25px" onclick="var f=this.form,x=false;for(var i=0;i<f.elements.length;i++){var e=f.elements[i];if(e.type==\'checkbox\'&&e.getAttribute(\'data-had\')&&!e.checked)x=true;}return x?misConfirm(this,\'ห้องที่ยกเลิกการเลือก จะลบการลงทะเบียนและผลการประเมินกิจกรรมของนักเรียนห้องนั้น ต้องการดำเนินการต่อหรือไม่?\'):true;"/>';
    $help = '<div style="width:983px;margin:0 auto 8px;text-align:left" class="gray_12">เลือกห้องเรียนที่ต้องการลงทะเบียนกิจกรรม แล้วกด "บันทึกการลงทะเบียน" นักเรียนทุกคนในห้องจะถูกลงทะเบียน และครูประจำชั้นของห้องนั้นจะบันทึกผลกิจกรรมได้ที่ งานรับผิดชอบหลัก &gt; บันทึกผลการเรียน</div>';
    return ['ลงทะเบียนกิจกรรมพัฒนาผู้เรียน (รายห้อง) ภาคเรียนที่ '.$t.'/'.$y, $add.$help.'<form method="post" action="'.h(a_url('act_enroll')).'">'
        .list_table('กิจกรรมพัฒนาผู้เรียน', ['#', 'รหัส', 'กิจกรรมพัฒนาผู้เรียน', 'ระดับชั้น', ['ชั่วโมง', ' style="text-align:center"'], 'ห้องเรียนที่ลงทะเบียน (จำนวนนักเรียน)', ['นักเรียน', ' style="text-align:center"']],
            $rows ?: '<tr><td colspan="7" align="center">ยังไม่มีกิจกรรมพัฒนาผู้เรียนในภาคเรียนนี้ (เพิ่มได้จากช่อง "เพิ่มกิจกรรม" ด้านบน หรือเมนูรายวิชา ประเภทกิจกรรม)</td></tr>', '', $acts ? $save : '').'</form>'];
}

/** ครูประจำชั้น/ครูผู้รับผิดชอบ: บันทึกผลกิจกรรมพัฒนาผู้เรียนของห้อง */
function rows_task_study_record_ActRecord()
{
    $c = att_course() ?? redirect('?module=task&option=study_record');
    if ((int) $c['type'] !== 9) {
        redirect('?module=task&option=study_record&func=Record&SubjectNum='.(int) $c['id'].'&RoomNum='.(int) ($_GET['RoomNum'] ?? 0));
    }
    $room = (int) ($_GET['RoomNum'] ?? 0);
    $full = (int) $c['period'];
    $rs = q('SELECT G.`id` gid, G.`grade`, G.`room`, G.`number`, S.`student_id` code, U.`name` FROM {grade} G JOIN {student} S ON S.`id`=G.`student_id` JOIN {user} U ON U.`id`=G.`student_id` WHERE G.`course_id`=?'
        .($room ? ' AND G.`room`='.$room : '').' ORDER BY G.`room`, G.`number`, S.`student_id`', [(int) $c['id']]);
    $hrs = act_hours(array_map(fn($r) => (int) $r['gid'], $rs));
    $fails = [];
    foreach ($rs ? md_rows('act_fail', array_map(fn($r) => (int) $r['gid'], $rs)) : [] as $r) {
        $fails[(int) $r['r1']] = true;
    }
    $lock = record_locked($c, $room);
    $dis = $lock ? ' disabled' : '';
    $rows = '';
    foreach ($rs as $i => $r) {
        $gid = (int) $r['gid'];
        $hr = $hrs[$gid] ?? null;
        $res = (string) ($r['grade'] ?? '');
        $rows .= zrow([[h((string) $r['number']), ' align="center"'], h((string) $r['code']), name_nbsp((string) $r['name']), [h(room_short($r['room'])), ' align="center"'],
            ['<input type="text" name="hr['.$gid.']" value="'.h((string) $hr).'" class="general radius ah" data-g="'.$gid.'" style="width:60px;height:19px;text-align:center" maxlength="5" oninput="actRes('.$gid.')"'.$dis.'/>', ' align="center"'],
            ['<input type="checkbox" name="fail['.$gid.']" value="1" id="af'.$gid.'" onclick="actRes('.$gid.')"'.(isset($fails[$gid]) ? ' checked' : '').$dis.' style="width:auto;height:auto"/>', ' align="center"'],
            ['<b id="ar'.$gid.'" style="color:'.($res === 'มผ' ? '#CC0000' : '#008800').'">'.h($res).'</b>', ' align="center"']], $i);
    }
    $js = '<script>var ACT_FULL='.$full.';function actRes(g){var h=document.querySelector(\'input[name="hr[\'+g+\']"]\').value.replace(/^\s+|\s+$/g,""),f=document.getElementById("af"+g).checked,o=document.getElementById("ar"+g),r="";'
        .'if(h!==""&&!isNaN(h)){r=(!f&&parseFloat(h)*100>=ACT_FULL*80)?"ผ":"มผ";}o.innerHTML=r;o.style.color=r=="มผ"?"#CC0000":"#008800";}'
        .'function actFull(){var a=document.querySelectorAll("input.ah");for(var i=0;i<a.length;i++){a[i].value=ACT_FULL;actRes(a[i].getAttribute("data-g"));}}</script>';
    $info = '<div style="width:983px;margin:0 auto 10px;text-align:left;font-size:20px;font-family:TH Sarabun New"><b>'.h($c['course_code'].' '.$c['course_name']).'</b> &nbsp; ระดับชั้น '.h($room ? class_room_short($c['class'], $room) : class_short($c['class']))
        .' &nbsp; เวลาเรียน '.$full.' ชั่วโมง &nbsp; <span class="gray_12" style="font-size:13px">(เข้าร่วมกิจกรรมร้อยละ 80 ขึ้นไป และผ่านการปฏิบัติกิจกรรม = ผ)</span>'
        .($lock ? '<div style="color:#CC0000;font-size:14px">ยืนยันผลการประเมินแล้ว ไม่สามารถแก้ไขได้ (ยกเลิกการยืนยันที่หน้าบันทึกผลการเรียนก่อน)</div>' : '').'</div>';
    $btns = btn('กลับ', '?module=task&option=study_record', 'back').($lock ? '' : ' <a href="javascript:void(0);" onclick="actFull();" id="pinkgreenbtns" style="margin-left:4px">เต็มเวลาทุกคน</a>'
        .' <a href="javascript:void(0);" onclick="document.forms.actrec.submit();" id="pinkgreenbtns" style="margin-left:4px"><img src="../icon_tools/vec_add_white.png" border="0" height="16"/> บันทึกผลกิจกรรม</a>');
    $body = saved_msg().$js.$info.'<form name="actrec" method="post" action="?module=task&option=study_record&func=SaveActRecord&SubjectNum='.(int) $c['id'].'&RoomNum='.$room.'">'
        .list_table('บันทึกผลการประเมินกิจกรรมพัฒนาผู้เรียน', [['เลขที่', ' style="width:50px;text-align:center"'], 'รหัสนักเรียน', 'ชื่อ-นามสกุล', ['ห้อง', ' style="text-align:center"'], ['เวลาเข้าร่วม (ชั่วโมง)', ' style="text-align:center"'],
            ['ไม่ผ่านการปฏิบัติกิจกรรม', ' style="text-align:center"'], ['ผลการประเมิน', ' style="text-align:center"']], $rows ?: '<tr><td colspan="7" align="center">ยังไม่มีนักเรียนลงทะเบียนกิจกรรมนี้</td></tr>', '', $btns).'</form>';
    return sub_page('task_study_record', $body, 'บันทึกผลกิจกรรมพัฒนาผู้เรียน');
}

function act_task_study_record_SaveActRecord(): void
{
    $c = att_course();
    $room = (int) ($_GET['RoomNum'] ?? 0);
    if ($c && (int) $c['type'] === 9 && !record_locked($c, $room) && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $full = (int) $c['period'];
        $gids = array_map('intval', array_column(q('SELECT `id` FROM {grade} WHERE `course_id`=?'.($room ? ' AND `room`='.$room : ''), [(int) $c['id']]), 'id'));
        foreach ($gids as $gid) {
            $v = trim((string) ($_POST['hr'][$gid] ?? ''));
            $hr = $v !== '' && is_numeric($v) ? (string) max(0, min(999, round((float) $v, 1))) : '';
            $fail = !empty($_POST['fail'][$gid]);
            md_set('act_hr', $gid, '', '', $hr !== '' ? $hr : null);
            md_set('act_fail', $gid, '', '', $fail ? '1' : null);
            $res = act_result($hr, $full, $fail);
            q('UPDATE {grade} SET `grade`=? WHERE `id`=?', [$res !== '' ? $res : null, $gid]);
        }
    }
    redirect('?module=task&option=study_record&func=ActRecord&SubjectNum='.(int) ($_GET['SubjectNum'] ?? 0).'&RoomNum='.$room.'&msg=saved');
}
