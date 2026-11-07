<?php
/**
 * mis/app/data.php
 * ข้อมูลของแต่ละหน้า (แถวในตาราง) จากฐานข้อมูลของ SMS
 * ชื่อฟังก์ชัน rows_<แม่แบบ>() คืนค่า ['TBODY0' => html, ...] หรือ html ของ tbody แรก
 */
defined('MIS') or exit;

/* ---------------------------------------------------------------- ตัวช่วย */

const ATT_CODES = ['ม' => 'present', 'ข' => 'absent', 'ล' => 'leave', 'ป' => 'sick', 'ส' => 'late', 'ก' => 'activity', 'น' => 'skip', 'ซ' => 'remedial'];
const ATT_CLASS = ['ม' => 'blackcolor', 'ข' => 'redcolor', 'ล' => 'bluecolor', 'ป' => 'purplecolor', 'ส' => 'yellowcolor', 'ก' => 'greencolor', 'น' => 'redcolor', 'ซ' => 'pinkcolor'];
const COURSE_TYPES = [1 => 'พื้นฐาน', 2 => 'เพิ่มเติม', 9 => 'กิจกรรม'];
const SARA_BY_PREFIX = ['ท' => 'ภาษาไทย', 'ค' => 'คณิตศาสตร์', 'ว' => 'วิทยาศาสตร์และเทคโนโลยี', 'ส' => 'สังคมศึกษา ศาสนาและวัฒนธรรม', 'พ' => 'สุขศึกษาและพลศึกษา', 'ศ' => 'ศิลปะ', 'ง' => 'การงานอาชีพ', 'อ' => 'ภาษาต่างประเทศ', 'จ' => 'ภาษาต่างประเทศ', 'ญ' => 'ภาษาต่างประเทศ', 'ม' => 'ภาษาต่างประเทศ', 'ก' => 'กิจกรรมพัฒนาผู้เรียน'];

/** แถว tr แบบระบบเดิม (คลิกเลือกแถวได้) */
function tr(array $cells, string $extra = ''): string
{
    $o = "\n <tr onclick=\"SelecteachRow(event,\$(this))\"$extra>";
    foreach ($cells as $c) {
        $o .= is_array($c) ? "\n <td{$c[1]}>{$c[0]}</td>" : "\n <td>$c</td>";
    }
    return $o."\n </tr>";
}

function split_name(string $name): array
{
    $name = trim($name);
    $prefix = '';
    if (preg_match('/^(นางสาว|นาง|นาย|ว่าที่ร้อยตรี|ครู|เด็กชาย|เด็กหญิง|ด\.ช\.|ด\.ญ\.)\s*(.*)$/u', $name, $m)) {
        $prefix = $m[1];
        $name = $m[2];
    }
    $p = preg_split('/\s+/u', $name, 2);
    return [$prefix, $p[0] ?? '', $p[1] ?? ''];
}

function class_short($class): string
{
    $c = cat('class', $class, '');
    if (preg_match('/^(ประถมศึกษา|มัธยมศึกษา)ปีที่\s*(\d+)/u', $c, $m)) {
        return ($m[1] === 'ประถมศึกษา' ? 'ป.' : 'ม.').$m[2];
    }
    if (preg_match('/^อนุบาลปีที่\s*(\d+)/u', $c, $m)) {
        return 'อ.'.$m[1];
    }
    return $c;
}

function photo_img($id, int $w = 45): string
{
    return '<img src="'.h(avatar_url((int) $id)).'" border="0" style="width:'.$w.'px"/>';
}

function icon_link(string $href, string $title, string $icon): string
{
    return "<a href=\"$href\" border=\"0\" class='icontooltip' title=\"".h($title)."\"><img src='../icon_tools/vec_{$icon}_dark.png' border='0' rel='../icon_tools/vec_{$icon}_gray.png' class='overlay' style='width:18px'/></a>";
}

/** แทนรายการตัวเลือกของ select ตามชื่อ */
function replace_select(string $html, string $name, string $options): string
{
    return preg_replace('/(<select\b[^>]*\b(?:name|id)="'.preg_quote($name, '/').'"[^>]*>).*?(<\/select>)/s', '$1'.str_replace('$', '\\$', $options).'$2', $html, 1);
}

/** รายวิชาของภาคเรียนที่ดู: ครูเห็นของตัวเอง ผู้ดูแล/ผู้จัดการเห็นทั้งหมด */
function my_courses(bool $all = false): array
{
    [$y, $t] = term();
    $sql = 'SELECT C.*, U.`name` teacher FROM {course} C LEFT JOIN {user} U ON U.`id`=C.`teacher_id` WHERE C.`year`=? AND C.`term`=?';
    $p = [$y, $t];
    if (!$all || !is_manager()) {
        $sql .= ' AND C.`teacher_id`=?';
        $p[] = (int) me()['id'];
    }
    return q($sql.' ORDER BY C.`type`, C.`course_code`', $p);
}

/** ห้องและจำนวนนักเรียนของรายวิชา [course_id => [room => count]] */
function course_rooms(array $courses): array
{
    $ids = array_map(fn($c) => (int) $c['id'], $courses);
    if (!$ids) {
        return [];
    }
    $out = [];
    foreach (q('SELECT `course_id`,`room`,COUNT(*) n FROM {grade} WHERE `course_id` IN ('.implode(',', $ids).') GROUP BY `course_id`,`room` ORDER BY `room`') as $r) {
        $out[(int) $r['course_id']][(int) $r['room']] = (int) $r['n'];
    }
    return $out;
}

function att_ensure(): void
{
    \Attendance\Sheet\Model::ensureTable();
}

/** คาบที่บันทึกแล้วและวันล่าสุด [course_id][room] => [sessions, last] */
function att_stats(array $courses): array
{
    $ids = array_map(fn($c) => (int) $c['id'], $courses);
    if (!$ids) {
        return [];
    }
    att_ensure();
    $out = [];
    foreach (q('SELECT `course_id`,`room`,COUNT(DISTINCT `date`,`period`) n, MAX(`date`) last FROM {attendance} WHERE `course_id` IN ('.implode(',', $ids).') GROUP BY `course_id`,`room`') as $r) {
        $out[(int) $r['course_id']][(int) $r['room']] = [(int) $r['n'], $r['last']];
    }
    return $out;
}

function group_row(string $text, int $cols): string
{
    return "<tr><td colspan=\"$cols\" style=\"background-color:#FAFAFA;font-weight:bold;font-size:20px;font-family:TH Sarabun New;padding:3px 5px\">$text</td></tr>";
}

const GROUP_TITLES = [1 => 'หมวดวิชาบังคับ', 2 => 'หมวดวิชาเลือก', 9 => 'หมวดกิจกรรมพัฒนาผู้เรียน'];

/** แถวรายวิชา×ห้อง จัดกลุ่มตามหมวด ใช้กับหลายหน้า */
function course_room_rows(int $cols, callable $cells): string
{
    $courses = my_courses();
    $rooms = course_rooms($courses);
    $o = '';
    $group = null;
    $i = 0;
    foreach ($courses as $c) {
        $type = (int) $c['type'];
        if ($group !== $type) {
            $group = $type;
            $o .= group_row(GROUP_TITLES[$type] ?? 'หมวดวิชาอื่น ๆ', $cols);
        }
        $first = true;
        foreach (($rooms[(int) $c['id']] ?? [0 => 0]) as $room => $n) {
            $i++;
            $o .= tr($cells($c, $room, $n, $i, $first));
            $first = false;
        }
    }
    return $o;
}

/* ---------------------------------------------------------------- ผู้ใช้ออนไลน์ */

function online_rows(array $ids): string
{
    if (!$ids) {
        return '';
    }
    $o = '';
    $i = 0;
    foreach (q('SELECT U.`id`,U.`username`,U.`name`,S.`student_id` FROM {user} U LEFT JOIN {student} S ON S.`id`=U.`id` WHERE U.`id` IN ('.implode(',', $ids).') ORDER BY U.`name`') as $r) {
        [$pre, $first, $last] = split_name($r['name']);
        $o .= tr([++$i, [h($r['student_id'] ?: $r['username']), ' width="100"'], h($pre), [h($first), ' style="border-right:none"'], [h($last), ' style="border-left:none"']]);
    }
    return $o;
}

function rows_onlineTeacher_()
{
    return online_rows(online_users()[0]);
}

function rows_onlineStudent_()
{
    return [
        'TBODY0' => online_rows(online_users()[1]),
        '__REPLACE__' => ['รายชื่อครูสถานะออนไลน์' => 'รายชื่อนักเรียนสถานะออนไลน์', 'รหัสครู' => 'รหัสนักเรียน', 'ค้นหาครูโดย ชื่อ/นามสกุล/รหัส' => 'ค้นหานักเรียนโดย ชื่อ/นามสกุล/รหัส'],
    ];
}

/* ---------------------------------------------------------------- ข้อมูลหลัก */

function rows_main_level()
{
    $levels = [];
    foreach (cats('class') as $c) {
        if (preg_match('/^อนุบาล/u', $c)) {
            $levels['อนุบาล'] = 'อ.';
        } elseif (preg_match('/^ประถมศึกษา/u', $c)) {
            $levels['ประถมศึกษา'] = 'ป.';
        } elseif (preg_match('/^มัธยมศึกษาปีที่\s*([1-3])$/u', $c)) {
            $levels['มัธยมศึกษาตอนต้น'] = 'ม.ต้น';
        } elseif (preg_match('/^มัธยมศึกษา/u', $c)) {
            $levels['มัธยมศึกษาตอนปลาย'] = 'ม.ปลาย';
        }
    }
    $o = '';
    $i = 0;
    foreach ($levels as $n => $s) {
        $o .= tr([++$i, h($n), h($s)]);
    }
    return $o;
}

/** ห้องของแต่ละชั้น (จากชื่อห้อง "1/2" ตัวเลขหน้า / คือชั้น) */
function rooms_by_class(): array
{
    $classes = cats('class');
    $idx = array_flip(array_keys($classes));
    $out = [];
    foreach (cats('room') as $rid => $rname) {
        $cid = null;
        if (preg_match('/^(\d+)\//', $rname, $m)) {
            $keys = array_keys($classes);
            $cid = $keys[(int) $m[1] - 1] ?? null;
        }
        $out[$cid ?? 0][$rid] = $rname;
    }
    return $out;
}

function rows_main_class()
{
    $byc = rooms_by_class();
    $o = '';
    $i = 0;
    foreach (cats('class') as $id => $name) {
        $o .= tr([++$i, h($name), h(class_short($id)), [h($id), ' style="display:none"'], count($byc[$id] ?? []).' ห้อง']);
    }
    return ['TBODY0' => $o];
}

function rows_main_room()
{
    $o = '';
    $i = 0;
    foreach (rooms_by_class() as $cid => $rooms) {
        foreach ($rooms as $rid => $rname) {
            $full = $cid ? cat('class', $cid).'/'.preg_replace('/^\d+\//', '', $rname) : $rname;
            $o .= tr([++$i, h($full), h($cid ? class_room_short($cid, $rid) : $rname), h($rid), 'ห้องเรียนปกติ']);
        }
    }
    return $o;
}

function class_options(string $first = 'ระดับชั้น'): string
{
    $o = '<option value="">'.$first.'</option>';
    foreach (cats('class') as $id => $n) {
        $o .= '<option value="'.h($id).'"'.((string) ($_POST['c_no'] ?? $_GET['c_no'] ?? '') === (string) $id ? ' selected' : '').'>'.h(class_short($id)).'</option>';
    }
    return $o;
}

function rows_main_student_regist()
{
    $c = (string) ($_POST['c_no'] ?? $_GET['c_no'] ?? '');
    $r = (string) ($_POST['r_no'] ?? $_GET['r_no'] ?? '');
    $sql = 'SELECT S.*, U.`name`, U.`id_card` uidcard, U.`active` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE 1';
    $p = [];
    if ($c !== '') {
        $sql .= ' AND S.`class`=?';
        $p[] = (int) $c;
    }
    if ($r !== '') {
        $sql .= ' AND S.`room`=?';
        $p[] = (int) $r;
    }
    $o = '';
    $i = 0;
    foreach (q($sql.' ORDER BY S.`class`,S.`room`,S.`number`,S.`student_id`', $p) as $s) {
        [$pre, $first, $last] = split_name($s['name']);
        $o .= tr([++$i, [h($s['student_id']), ' style="white-space:nowrap"'], [h($s['id_card'] ?: $s['uidcard']).' ', ' style="white-space:nowrap"'], h($pre.$first), h($last),
            [photo_img($s['id']), ' style="text-align:center"'], [(int) $s['active'] ? 'ปกติ' : 'พ้นสภาพ', ' align="center" style="display:none"'], '',
            h(class_room_short($s['class'], $s['room'])), h($s['number']), ['', ' style="display:none"'],
            ['<a href="../../school-student?id='.(int) $s['id'].'" class="icontooltip" title="ข้อมูลนักเรียน"><i class="fa fa-search icon_td"></i></a>', ' width="65" style="text-align:center;padding:5px 5px;white-space:nowrap"']]);
    }
    $rooms = '<option value="">ห้องเรียน</option>';
    foreach (cats('room') as $id => $n) {
        $rooms .= '<option value="'.h($id).'"'.($r === (string) $id ? ' selected' : '').'>'.h($n).'</option>';
    }
    return ['TBODY0' => $o, '__SELECT__' => ['c_no' => class_options(), 'r_no' => $rooms]];
}

function personnel_rows(bool $current = true): array
{
    $sql = 'SELECT U.`id`,U.`username`,U.`name`,U.`status`,U.`active`,U.`permission`,P.`position`,P.`department`,P.`class`,P.`room` FROM {user} U'
        .' LEFT JOIN {personnel} P ON P.`id`=U.`id` LEFT JOIN {student} S ON S.`id`=U.`id` WHERE S.`id` IS NULL AND U.`active`=?';
    return q($sql.' ORDER BY P.`order`, U.`id`', [$current ? 1 : 0]);
}

function rows_main_personel_regist()
{
    $o = '';
    $i = 0;
    foreach (personnel_rows() as $p) {
        [$pre, $first, $last] = split_name($p['name']);
        $type = (int) $p['status'] === 1 ? 'ผู้ดูแลระบบ' : (strpos((string) $p['permission'], 'can_teacher') !== false ? 'ครูผู้สอน' : 'อื่น ๆ');
        $o .= tr([++$i, h($p['username']), [h($pre.$first), ' style="border-right:none;white-space:nowrap"'], [h($last), ' style="border-left:none;white-space:nowrap"'],
            h(cat('position', $p['position'], '')), [$type, ' style="white-space:nowrap"'], ['', ' style="display:none"'], ['', ' style="display:none"'],
            [photo_img($p['id']), ' align="center" width="60"']]);
    }
    return $o;
}

function rows_main_personel_past()
{
    $o = '';
    $i = 0;
    foreach (personnel_rows(false) as $p) {
        $o .= tr([++$i, h($p['username']), h($p['name']), h(cat('position', $p['position'], '')), 'พ้นสภาพ']);
    }
    return $o;
}

/** ครูที่ปรึกษาของห้อง [room => name] */
function advisors(): array
{
    $out = [];
    foreach (q('SELECT P.`room`,U.`name` FROM {personnel} P JOIN {user} U ON U.`id`=P.`id` WHERE P.`room`>0') as $r) {
        $out[(int) $r['room']][] = $r['name'];
    }
    return $out;
}

function rows_main_student_class()
{
    $adv = advisors();
    $cnt = [];
    foreach (q('SELECT S.`room`, U.`sex`, COUNT(*) n FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE U.`active`=1 GROUP BY S.`room`, U.`sex`') as $r) {
        $cnt[(int) $r['room']][$r['sex']] = (int) $r['n'];
    }
    $o = '';
    foreach (rooms_by_class() as $cid => $rooms) {
        foreach ($rooms as $rid => $rname) {
            $c = $cnt[$rid] ?? [];
            $o .= tr([h($cid ? class_room_short($cid, $rid) : $rname), 'ห้องเรียนปกติ',
                ["<span style='float:left;width:150px'>".h(implode(', ', $adv[$rid] ?? []))."</span>", ' style="width:180px;white-space:nowrap"'],
                ['ว่าง ', ' style="white-space:nowrap"'], [array_sum($c), ' style="text-align:center"'], [$c['m'] ?? 0, ' style="text-align:center"'], [$c['f'] ?? 0, ' style="text-align:center"']]);
        }
    }
    return $o;
}

/* ---------------------------------------------------------------- หลักสูตร / ตารางสอน */

function rows_academic_subject()
{
    [$y, $t] = term();
    $cls = (string) ($_POST['class_no'] ?? '');
    $sql = 'SELECT * FROM {course} WHERE `year`=? AND `term`=?';
    $p = [$y, $t];
    if ($cls !== '') {
        $sql .= ' AND `class`=?';
        $p[] = (int) $cls;
    }
    $o = '';
    $i = 0;
    foreach (q($sql.' ORDER BY `class`,`type`,`course_code`', $p) as $c) {
        $first = mb_substr($c['course_code'], 0, 1);
        $o .= tr([++$i.'<a name="'.(int) $c['id'].'"></a>', ['<span style="width:60px;float:left;white-space:nowrap">'.h($c['course_code']).'</span>'.h($c['course_name']), ' style="white-space:nowrap"'],
            [h(SARA_BY_PREFIX[$first] ?? ''), ' style="white-space:nowrap"'], [COURSE_TYPES[(int) $c['type']] ?? '', ' style="white-space:nowrap"'], ['รายภาค', ' style="white-space:nowrap"'],
            [(int) $c['type'] === 2 ? 'เลือก' : 'บังคับ', ' style="white-space:nowrap"'], [h(class_short($c['class'])), ' style="white-space:nowrap"'], [h((float) $c['credit']), ' style="white-space:nowrap;text-align:center"']]);
    }
    $opts = '<option value="">รายวิชาไม่จำกัดชั้นปี</option>';
    foreach (cats('class') as $id => $n) {
        $opts .= '<option value="'.h($id).'"'.($cls === (string) $id ? ' selected' : '').'>'.h($n).'</option>';
    }
    return ['TBODY0' => $o, '__SELECT__' => ['class_no' => $opts]];
}

function rows_academic_teacher_timetable()
{
    $o = '';
    $i = 0;
    foreach (personnel_rows() as $p) {
        [$pre, $first, $last] = split_name($p['name']);
        $o .= "\n <tr onclick=\"SelecteachRow(event,\$(this))\">\n <td>".++$i."</td>\n <td>".h($p['username'])."</td>\n <td><span style='float:left;min-width: 80px'>".h($pre.$first)."</span><span style='float:left;padding-left:10px'>".h($last)."</span></td>\n <td>".h(cat('position', $p['position'], ''))."</td>\n <td width=\"50\" style=\"text-align:center;padding:5px 5px; vertical-align:middle\">\n "
            .icon_link('?module=task&option=teacher_timetable', 'พิมพ์ตารางสอน', 'print')."\n</td> </tr>";
    }
    return $o;
}

function rows_academic_student_timetable()
{
    $adv = advisors();
    $o = '';
    $i = 0;
    foreach (rooms_by_class() as $cid => $rooms) {
        foreach ($rooms as $rid => $rname) {
            $a = $adv[$rid] ?? [];
            $o .= "\n <tr onclick=\"SelecteachRow(event,\$(this))\">\n <td>".++$i."</td>\n <td>".h($cid ? cat('class', $cid).'/'.preg_replace('/^\d+\//', '', $rname) : $rname)."</td>\n <td>".name_nbsp($a[0] ?? '')."</td>\n <td>".name_nbsp($a[1] ?? '')."</td>\n <td>ปกติ</td>\n <td style=\"white-space:nowrap\">\n ว่าง </td>\n <td width=\"130\" style=\"text-align:center;padding:5px 5px; vertical-align:middle\">\n <a href=\"javascript:void(0);\" border=\"0\" id=\"pinkgreenbtns\"><img src=\"../icon_tools/vec_print_white.png\" border=\"0\" height=\"18\"/> พิมพ์ตารางเรียน</a>\n</td> </tr>";
        }
    }
    return $o;
}

/* ---------------------------------------------------------------- งานการสอน */

function rows_task_respons_subject()
{
    $me = (int) me()['id'];
    $rows = course_room_rows(6, function ($c, $room, $n, $i, $first) {
        $q = 'TeacherId='.(int) $c['teacher_id'].'&SubjectNum='.(int) $c['id'].'&Subject='.rawurlencode($c['course_code']).'&RoomNum='.$room;
        return [$i, [$first ? h($c['course_code'].' '.$c['course_name']) : '', ' style="white-space:nowrap"'], [COURSE_TYPES[(int) $c['type']] ?? '', ' align="center"'], h(class_room_short($c['class'], $room)), $n,
            [' '.icon_link('?module=task&option=respons_subject&func=StudentSubjectList&'.$q, 'แสดงรายชื่อนักเรียน', 'scope'), ' align="center" width="50" style="text-align:center;padding:15px 5px 15px 5px;white-space:nowrap"']];
    });
    return ['TBODY0' => $rows, '__REPLACE__' => ['<tr id="info_teacher" style="display:none">' => '<tr id="info_teacher">']];
}

function att_row_cells(string $option, string $icon, string $title): callable
{
    $stats = att_stats(my_courses());
    return function ($c, $room, $n, $i, $first) use ($stats, $option, $icon, $title) {
        [$done, $last] = $stats[(int) $c['id']][$room] ?? [0, null];
        $total = (int) $c['period'];
        $pct = $total > 0 ? number_format($done * 100 / $total, 2) : '0.00';
        $q = 'module=task&option='.$option.'&func=Attendance&TeacherId='.(int) $c['teacher_id'].'&SubjectId='.rawurlencode($c['course_code']).'&SubjectNum='.(int) $c['id'].'&AttendType=subject&Elective=0&RoomNum='.$room;
        return [$i, [$first ? h($c['course_code'].' '.$c['course_name']) : '', ' style="white-space:nowrap"'], [$first ? (COURSE_TYPES[(int) $c['type']] ?? '') : '', ' align="center"'],
            h(class_room_short($c['class'], $room)), [$n, ' align="center"'], ['<span style="display:none">'.h($last).'</span>'.($last ? th_date($last) : ''), ''],
            [$total, ' align="center"'], [$done, ' align="center"'], [$pct, ' align="center"'],
            [' '.icon_link('?'.$q, $title, $icon).' ', ' align="center" width="40" style="text-align:center;padding:15px 5px 15px 5px"']];
    };
}

function rows_task_time_attendance_byroom()
{
    return course_room_rows(10, att_row_cells('time_attendance_byroom', 'add', 'บันทึกเวลาเรียน'));
}

function rows_task_report_attendance()
{
    return course_room_rows(10, att_row_cells('time_attendance_byroom', 'print', 'รายงานการเข้าเรียน'));
}

/** นักเรียนในห้องที่เป็นครูที่ปรึกษา */
function advisor_students(): array
{
    $p = me()['personnel'] ?? null;
    if (!$p || (int) $p['room'] === 0) {
        return [];
    }
    return q('SELECT S.*, U.`name`, U.`phone`, U.`username`, U.`active`, U.`sex` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`class`=? AND S.`room`=? ORDER BY S.`number`, S.`student_id`', [(int) $p['class'], (int) $p['room']]);
}

const DUTIES = [1 => 'หัวหน้าห้อง', 2 => 'รองหัวหน้าห้อง', 3 => 'วิชาการห้อง', 4 => 'เหรัญญิกห้อง', 5 => 'ประชาสัมพันธ์ห้อง', 6 => 'ผู้ช่วยเหริญญิก', 7 => 'ผู้ช่วยวิชาการห้อง', 8 => 'ยุวบรรณารักษ์', 9 => 'ยุวพยาบาล'];

function rows_task_respons_student()
{
    $students = advisor_students();
    $duty = [];
    if ($students) {
        foreach (q("SELECT `member_id`,`value` FROM {user_meta} WHERE `name`='mis_duty' AND `member_id` IN (".implode(',', array_map(fn($s) => (int) $s['id'], $students)).')') as $r) {
            $duty[(int) $r['member_id']] = (int) $r['value'];
        }
    }
    $o = '';
    $i = 0;
    foreach ($students as $s) {
        $id = (int) $s['id'];
        $sel = '<select name="duty[]" style="outline:none;width:160px;height:25px" class="general radius grayborder " id="sduty'.$id.'" type="list" onchange="addClass(this.value,\''.$id.'\');">'
            .'<option value="'.$id.'|">ยังไม่กำหนดหน้าที่</option>';
        foreach (DUTIES as $k => $v) {
            $sel .= '<option value="'.$id.'|'.$k.'" '.(($duty[$id] ?? 0) === $k ? 'selected' : '').'> '.$v.'</option>';
        }
        $sel .= '</select>';
        $o .= tr([[++$i, ' width="20" align="center"'], h($s['student_id']), name_nbsp($s['name']), [(int) $s['active'] ? 'ศึกษาอยู่' : 'พ้นสภาพ', ' '], '', h($s['phone']),
            [photo_img($id), ' align="center"'], $sel, ['-', ' style="text-align:center"'], ['-', ' style="text-align:center"'],
            ['<a href="../../school-student?id='.$id.'" border="0" title="แก้ไขข้อมูลนักเรียน" class="icontooltip"><img title="แก้ไข" border="0" src="../icon_tools/vec_pencil_dark.png" rel="../icon_tools/vec_pencil_gray.png" class="overlay" height="20"/></a>', ' style="text-align:center;width:45px"']]);
    }
    return $o;
}

/** กำหนดหน้าที่ให้นักเรียน (ปุ่ม "กำหนดหน้าที่ให้กับนักเรียน" ส่ง duty[] = "รหัส|หน้าที่") */
function act_task_respons_student_AssignDuty(): void
{
    $ids = array_map(fn($s) => (int) $s['id'], advisor_students());
    foreach ((array) ($_POST['duty'] ?? []) as $v) {
        [$sid, $duty] = array_pad(explode('|', (string) $v), 2, '');
        $sid = (int) $sid;
        if (!in_array($sid, $ids, true)) {
            continue;
        }
        q("DELETE FROM {user_meta} WHERE `name`='mis_duty' AND `member_id`=?", [$sid]);
        if (isset(DUTIES[(int) $duty])) {
            q("INSERT INTO {user_meta} (`value`,`name`,`member_id`) VALUES (?, 'mis_duty', ?)", [(string) (int) $duty, $sid]);
        }
    }
    redirect('?module=task&option=respons_student');
}

/* ---------------------------------------------------------------- บันทึกเวลาเรียน (รายห้อง) */

function att_course(): ?array
{
    $c = q1('SELECT C.*, U.`name` teacher FROM {course} C LEFT JOIN {user} U ON U.`id`=C.`teacher_id` WHERE C.`id`=?', [(int) ($_GET['SubjectNum'] ?? 0)]);
    if (!$c) {
        return null;
    }
    if (!is_manager() && (int) $c['teacher_id'] !== (int) me()['id']) {
        return null;
    }
    return $c;
}

/** วันที่จาก actday (วว/ดด/ปปปป) ไม่มีใช้วันนี้ */
function att_day(): string
{
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', (string) ($_GET['actday'] ?? ''), $m)) {
        $y = (int) $m[3] > 2400 ? (int) $m[3] - 543 : (int) $m[3];
        if (checkdate((int) $m[2], (int) $m[1], $y)) {
            return sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]);
        }
    }
    return date('Y-m-d');
}

function rows_task_time_attendance_byroom_Attendance()
{
    $c = att_course();
    if (!$c) {
        redirect('?module=task&option=time_attendance_byroom');
    }
    att_ensure();
    $room = (int) ($_GET['RoomNum'] ?? 0);
    $day = att_day();
    $period = max(1, min(12, (int) ($_GET['Period'] ?? 1)));
    $weekend = in_array((int) date('w', strtotime($day)), [0, 6], true);
    $base = '?module=task&option=time_attendance_byroom&func=Attendance&TeacherId='.(int) $c['teacher_id'].'&SubjectId='.rawurlencode($c['course_code']).'&SubjectNum='.(int) $c['id'].'&AttendType=subject&Elective=0';
    $tpl = (string) file_get_contents(MIS_APP.'pages/task_time_attendance_byroom_Attendance.html');

    // ส่วนหัวของรายวิชา
    $rooms = course_rooms([$c])[(int) $c['id']] ?? [];
    $opts = '';
    foreach ($rooms as $r => $n) {
        $opts .= '<option value="'.h($base.'&RoomNum='.$r.'&actday='.date('d/m/Y', strtotime($day))).'"'.($r === $room ? " selected='selected'" : '').'>'.h($c['course_code'].' - '.class_room_short($c['class'], $r)).'</option>';
    }
    $done = (int) (q1('SELECT COUNT(DISTINCT `date`,`period`) n FROM {attendance} WHERE `course_id`=? AND `room`=?', [(int) $c['id'], $room])['n'] ?? 0);
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
        '/goUrl:"[^"]*"/' => 'goUrl:"'.$base.'&RoomNum='.$room.'"',
        '/disabledDays:\[[^\]]*\]/' => 'disabledDays:[0,0,0,0,0,1,1]',
        "/\\?module=task&option=time_attendance_byroom&RoomNum=\\d+&SubjectId=[^']*'/u" => '?module=task&option=time_attendance_byroom\'',
        "/ReportPortrait\\('print_portrait\\.php[^']*'\\);/" => "location.href='?module=task&option=report_attendance';",
        '/var act = "[^"]*";/' => 'var act = "'.str_replace('func=Attendance', 'func=SaveSubjectAttendTime', $base).'&RoomNum='.$room.'&actday='.date('d/m/Y', strtotime($day)).'";',
        "/confirm_single\\('[^']*'/" => "confirm_single('".$base."&RoomNum=".$room."'",
    ];
    $tpl = preg_replace(array_keys($rep), array_values($rep), $tpl);
    $msg = $_GET['msg'] ?? '';
    $box = fn($text, $ok) => "<div id='msg_info'><div class='main_msg' style='width:100%'><table border='0' cellspacing='0' cellpadding='0' class='"
        .($ok ? 'frame_green_msg' : 'frame_red_msg')."' style='width:983px'><tr><td width=20><img src='../images/".($ok ? '1347351892_yes.png' : '001_11.png')."' border=0 align=middle></td><td align='left' style='text-align:left'>&nbsp;&nbsp;<div style='margin-top:-15px'>&nbsp;&nbsp;&nbsp;$text</div></td></tr></table></div></div>";
    $newBox = $msg === 'saved' ? $box('บันทึกเวลาเรียนเรียบร้อยแล้ว', true) : ($weekend ? $box('กรุณากำหนดวันที่บันทึกให้ตรงกับวันที่มีการเรียนการสอนในภาคเรียนปัจจุบัน', false) : '');
    $tpl = preg_replace("/<div id='msg_info'>.*?<\\/table><\\/div><\\/div>/s", str_replace('$', '\\$', $newBox), $tpl, 1);

    // ตารางรายชื่อ
    $grid = '';
    if (!$weekend) {
        $students = \Attendance\Sheet\Model::students($c['id'], $room);
        [$marks] = \Attendance\Sheet\Model::marks($c['id'], $day, $period);
        $code = array_flip(ATT_CODES);
        $popts = '';
        for ($p = 1; $p <= 10; $p++) {
            $popts .= '<option value="'.h($base.'&RoomNum='.$room.'&actday='.date('d/m/Y', strtotime($day)).'&Period='.$p).'"'.($p === $period ? ' selected' : '').'>คาบที่ '.$p.'</option>';
        }
        $grid = "\n<div style=\"width:984px;margin:10px auto 0;text-align:left;overflow:hidden\"><div class=\"titlenav\" style=\"width:969px\"><span style=\"float:left;white-space:nowrap\"><span id=\"Listtitle\">&nbsp;</span><h1 style=\"font-size:23px; white-space:nowrap\">รายชื่อนักเรียน</h1></span>"
            ."<span style=\"float:right;margin-top:2px;white-space:nowrap\"><select class=\"general radius\" style=\"margin:0px;height:25px;min-width:100px\" onchange=\"select_attendance(this.value);\">$popts</select></span></div>"
            ."\n<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" class=\"tinytable\" id=\"table\" align=\"left\" style=\"width:983px\"><thead><tr><th width=\"24\" class=\"nosort\"><h3>เลขที่</h3></th><th class=\"nosort\"><h3>รหัสนักเรียน</h3></th><th class=\"nosort\"><h3>ชื่อ-นามสกุล</h3></th><th class=\"nosort\" style=\"text-align:center\"><h3>คาบที่ $period</h3></th></tr></thead><tbody>";
        foreach ($students as $k => $s) {
            $v = $code[$marks[$s['id']] ?? ''] ?? '';
            $cls = $v !== '' ? ATT_CLASS[$v] : '';
            $id = 'att'.(int) $s['id'];
            $grid .= "\n<tr class=\"".($k % 2 ? 'oddrow' : 'evenrow')."\"><td>".h($s['number'])."</td><td>".h($s['code'])."</td><td>".name_nbsp($s['name'])."</td><td class=\"box\" style=\"text-align:center\">"
                ."<input type=\"text\" readonly=\"readonly\" name=\"att[".(int) $s['id']."]\" id=\"$id\" value=\"".h($v)."\" class=\"$cls\" onclick=\"ChangeVal('$id', this.value==''?'ซ':this.value);\"/></td></tr>";
        }
        if (!$students) {
            $grid .= '<tr><td colspan="4" style="text-align:center">ยังไม่มีนักเรียนลงทะเบียนในรายวิชานี้</td></tr>';
        }
        $grid .= "\n</tbody></table>\n<div class=\"pagenav\" style=\"width:969px;height:auto;text-align:center\">"
            ."<span style=\"float:left;font-size:12px;margin-top:4px\">ม=มาเรียน ข=ขาดเรียน ล=ลากิจ ป=ลาป่วย ส=มาสาย ก=กิจกรรม น=หนี ซ=ซ่อมเสริม (คลิกที่ช่องเพื่อเปลี่ยนสถานะ)</span>"
            ."<a href=\"javascript:void(0);\" onclick=\"$('#list input[type=text]').each(function(){this.value='ม';this.className='blackcolor';});\" id=\"pinkgreenbtns\" style=\"margin-right:6px\">มาเรียนทุกคน</a>"
            ."<a href=\"javascript:void(0);\" onclick=\"chknull();\" id=\"pinkgreenbtns\"><img src=\"../icon_tools/vec_add_white.png\" border=\"0\" height=\"16\"/> บันทึกเวลาเรียน</a></div></div>";
    }
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
    $room = (int) ($_GET['RoomNum'] ?? 0);
    $day = att_day();
    $period = max(1, min(12, (int) ($_GET['Period'] ?? $_POST['Period'] ?? 1)));
    $rooms = [];
    foreach (\Attendance\Sheet\Model::students($c['id'], 0) as $s) {
        $rooms[$s['id']] = $s['room'];
    }
    $marks = [];
    foreach ((array) ($_POST['att'] ?? []) as $sid => $v) {
        if (isset($rooms[(int) $sid], ATT_CODES[$v])) {
            $marks[(int) $sid] = ATT_CODES[$v];
        }
    }
    \Attendance\Sheet\Model::save($c['id'], $day, $period, $marks, $rooms, me()['id']);
    redirect(str_replace('func=SaveSubjectAttendTime', 'func=Attendance', $_SERVER['REQUEST_URI']).'&Period='.$period.'&msg=saved');
}

/* ---------------------------------------------------------------- เวลาเรียนไม่ถึง 80% */

/** นักเรียนที่เวลาเรียนต่ำกว่า 80% ของรายวิชาที่สอน (หรือห้องที่ปรึกษา) */
function under80(bool $advisor): array
{
    att_ensure();
    if ($advisor) {
        $ids = array_map(fn($s) => (int) $s['id'], advisor_students());
        if (!$ids) {
            return [];
        }
        [$y, $t] = term();
        $where = 'A.`student_id` IN ('.implode(',', $ids).') AND C.`year`='.(int) $y.' AND C.`term`='.(int) $t;
    } else {
        $cs = array_map(fn($c) => (int) $c['id'], my_courses());
        if (!$cs) {
            return [];
        }
        $where = 'A.`course_id` IN ('.implode(',', $cs).')';
    }
    $present = "'".implode("','", \Attendance\Sheet\Model::PRESENT)."'";
    return q('SELECT A.`course_id`, A.`student_id`, A.`room`, C.`course_code`, C.`course_name`, C.`class`, C.`period`, S.`student_id` code, S.`number`, U.`name`,'
        .' COUNT(*) total, SUM(A.`status` IN ('.$present.')) present FROM {attendance} A JOIN {course} C ON C.`id`=A.`course_id`'
        .' JOIN {student} S ON S.`id`=A.`student_id` JOIN {user} U ON U.`id`=A.`student_id` WHERE '.$where
        .' GROUP BY A.`course_id`, A.`student_id`, A.`room`, C.`course_code`, C.`course_name`, C.`class`, C.`period`, S.`student_id`, S.`number`, U.`name`'
        .' HAVING present*100 < total*80 ORDER BY C.`course_code`, A.`room`, S.`number`');
}

function under80_table(array $rows, string $title): string
{
    $o = "\n<table id=\"mainList\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tr id=\"titleList\"><td><div class=\"titlenav\" style=\"width:969px\"><span style=\"float:left;white-space:nowrap\"><span id=\"Listtitle\">&nbsp;</span><h1 style=\"font-size:23px; white-space:nowrap\">".h($title)."</h1></span>"
        ."<span style=\"float:right;margin-top:5px;white-space:nowrap\">ทั้งหมด&nbsp;".count($rows)."&nbsp;รายการ</span></div></td></tr><tr id=\"contentList\"><td>"
        ."<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" class=\"tinytable\" id=\"table\" align=\"left\" style=\"width:983px\"><thead><tr><th width=\"24\" class=\"nosort\"><h3>#</h3></th><th><h3>รายวิชา</h3></th><th><h3>ห้องเรียน</h3></th><th><h3>เลขที่</h3></th><th><h3>รหัสนร.</h3></th><th><h3>ชื่อ-นามสกุล</h3></th><th style=\"text-align:center\"><h3>มาเรียน/บันทึกแล้ว</h3></th><th style=\"text-align:center\"><h3>ร้อยละ</h3></th></tr></thead><tbody>";
    foreach ($rows as $i => $r) {
        $pct = $r['total'] ? $r['present'] * 100 / $r['total'] : 0;
        $o .= "\n<tr class=\"".($i % 2 ? 'oddrow' : 'evenrow')."\"><td>".($i + 1).'</td><td style="white-space:nowrap">'.h($r['course_code'].' '.$r['course_name']).'</td><td>'.h(class_room_short($r['class'], $r['room'])).'</td><td>'.h($r['number']).'</td><td>'.h($r['code']).'</td><td>'.name_nbsp($r['name'])
            .'</td><td align="center">'.(int) $r['present'].'/'.(int) $r['total'].'</td><td align="center" style="color:#FF0000">'.number_format($pct, 2).'</td></tr>';
    }
    if (!$rows) {
        $o .= '<tr><td colspan="8" style="text-align:center">ไม่พบนักเรียนที่มีเวลาเรียนไม่ถึง 80%</td></tr>';
    }
    return $o."\n</tbody></table></td></tr></table>";
}

function rows_task_failure_attend()
{
    return ['__APPEND__' => under80_table(under80(false), 'นักเรียนเวลาเรียนไม่ถึง 80% (รายวิชาที่สอน)')];
}

function rows_task_attend_consider()
{
    return ['__APPEND__' => under80_table(under80(true), 'รายชื่อนักเรียนที่มีเวลาเรียนไม่ถึง 80% (ประจำชั้น)')];
}

/* ---------------------------------------------------------------- ข้อมูลส่วนตัว */

const TITLES = ['นาย' => 3, 'นางสาว' => 4, 'นาง' => 5, 'ว่าที่ร้อยตรี' => 6];

function rows_profile_()
{
    $u = me();
    $r = q1('SELECT * FROM {user} WHERE `id`=?', [(int) $u['id']]) ?? [];
    [$pre, $first, $last] = split_name((string) ($r['name'] ?? ''));
    $b = !empty($r['birthday']) ? strtotime($r['birthday']) : 0;
    $d = [
        'personel_id' => $r['username'] ?? '', 'psn_id_card' => $r['id_card'] ?? '', 'psn_name' => $first, 'psn_surname' => $last,
        'psn_phone' => $r['phone'] ?? '', 'psn_address' => trim(($r['address'] ?? '').' '.($r['address2'] ?? '')),
        'psn_title' => (string) (TITLES[$pre] ?? ''), 'psn_type' => (int) ($r['status'] ?? 0) === 1 ? '1' : '2',
        'psn_gender' => ($r['sex'] ?? '') === 'f' ? '2' : (($r['sex'] ?? '') === 'm' ? '1' : ''),
        'b_day' => $b ? date('d', $b) : '', 'b_month' => $b ? date('m', $b) : '', 'b_year' => $b ? (string) (date('Y', $b) + 543) : '',
    ];
    $msg = ($_GET['msg'] ?? '') === 'saved' ? 'try { AlertBox.info("<br>บันทึกข้อมูลส่วนตัวเรียบร้อยแล้ว"); } catch (e) {}' : '';
    // ใช้ JavaScript ธรรมดา (สคริปต์แท็บของหน้าเดิมเรียก .on ที่ jQuery 1.6 ไม่มี จึง error และหยุด ready อื่น)
    $js = "\n<script type=\"text/javascript\">\n(function(){\n var d = ".json_encode($d, JSON_UNESCAPED_UNICODE).";\n"
        ." function fill(){ for (var k in d) { var v = d[k]; if (v === '') continue; var els = document.getElementsByName(k); for (var i = 0; i < els.length; i++) { var e = els[i];"
        ." if (e.type === 'radio') { e.checked = (e.value === v); } else if (e.tagName === 'SELECT') { for (var j = 0; j < e.options.length; j++) { var t = e.options[j].value.replace(/^\\s+|\\s+$/g, ''); if (t === v || t === String(parseInt(v, 10))) { e.selectedIndex = j; } } } else { e.value = v; } } }"
        ." var pid = document.getElementsByName('personel_id'); if (pid.length) { pid[0].readOnly = true; } $msg }\n"
        ." if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', fill); } else { fill(); }\n})();\n</script>\n";
    return ['__APPEND__' => $js, 'UIDNUM' => (string) (int) $u['id'], 'PHOTOURL' => h(avatar_url((int) $u['id']))];
}

/** บันทึกข้อมูลส่วนตัว (ฟอร์ม แก้ไขข้อมูลส่วนตัว) เฉพาะช่องที่มีในบัญชี SMS */
function act_profile__Update(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        redirect('?module=profile');
    }
    $u = me();
    $first = trim((string) ($_POST['psn_name'] ?? ''));
    $last = trim((string) ($_POST['psn_surname'] ?? ''));
    if ($first === '' || $last === '') {
        redirect('?module=profile');
    }
    $pre = array_search((int) ($_POST['psn_title'] ?? 0), TITLES, true);
    $sex = ['1' => 'm', '2' => 'f'][(string) ($_POST['psn_gender'] ?? '')] ?? null;
    $bd = null;
    if (checkdate((int) ($_POST['b_month'] ?? 0), (int) ($_POST['b_day'] ?? 0), (int) ($_POST['b_year'] ?? 0) - 543)) {
        $bd = sprintf('%04d-%02d-%02d', (int) $_POST['b_year'] - 543, (int) $_POST['b_month'], (int) $_POST['b_day']);
    }
    $phone = trim((string) ($_POST['psn_phone'] ?? ''));
    $card = preg_replace('/\D/', '', (string) ($_POST['psn_id_card'] ?? ''));
    $address = mb_substr(trim((string) ($_POST['psn_address'] ?? '')), 0, 128);
    q('UPDATE {user} SET `name`=?, `sex`=?, `birthday`=?, `address`=?, `address2`=?, `phone`=NULLIF(?, \'\'), `id_card`=NULLIF(?, \'\') WHERE `id`=?', [
        ($pre ?: '').$first.' '.$last, $sex, $bd, mb_substr($address, 0, 64), mb_substr($address, 64, 64), $phone, strlen($card) === 13 ? $card : '', (int) $u['id'],
    ]);
    redirect('?module=profile&msg=saved');
}
