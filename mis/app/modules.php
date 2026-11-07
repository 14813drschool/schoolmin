<?php
/**
 * mis/app/modules.php
 * เมนูและหน้าของโมดูล ทะเบียนและข้อมูลหลัก / บริหารงานวิชาการ / บริหารงานบุคลากร / บริหารงานทั่วไป
 * (ตามรายการเมนูของระบบเดิม) ข้อมูลเก็บใน {mis_data} และตารางของ SMS
 */
defined('MIS') or exit;

/* ================================================================ เมนู */

function main_menu(): array
{
    return [
        'ข้อมูลหลัก' => ['admins' => 'กำหนดผู้ดูแลระบบ', 'year' => 'กำหนดปีการศึกษา', 'term' => 'กำหนดภาคเรียน', 'edu_level' => 'กำหนดระดับการศึกษา',
            'class' => 'กำหนดระดับชั้น', 'room' => 'กำหนดห้องเรียน', 'program' => 'กำหนดแผนการเรียน'],
        'ทะเบียนบุคลากร' => ['ptype' => 'กำหนดคุณสมบัติบุคลากร', 'personnel' => 'กำหนดข้อมูลบุคลากร', 'executive' => 'กำหนดผู้บริหารโรงเรียน',
            'pstatus' => 'กำหนดสถานภาพบุคลากร', 'permission' => 'กำหนดสิทธิ์บุคลากร', 'pcard' => 'พิมพ์บัตรประจำตัวบุคลากร'],
        'ทะเบียนนักเรียน' => ['dwelling' => 'กำหนดที่พักอาศัยของนักเรียน', 'travel' => 'วิธีการเดินทางมาโรงเรียน', 'entry_type' => 'ประเภทการเข้าเรียน',
            'no_room' => 'นักเรียนยังไม่กำหนดห้องเรียน', 'student' => 'ทะเบียนนักเรียนปัจจุบัน', 'student_import' => 'นำเข้ารายชื่อนักเรียนจากไฟล์ Excel (CSV)',
            'student_class' => 'จัดการชั้นเรียน', 'promote' => 'เลื่อนชั้นเรียน / ย้ายห้อง', 'sstatus' => 'กำหนดสถานะนักเรียน', 'new_student' => 'นักเรียนเข้าใหม่',
            'graduate' => 'นักเรียนจบการศึกษา', 'dropout' => 'นักเรียนออกกลางคัน', 'stat' => 'สถิตินักเรียน', 'parent' => 'ข้อมูลผู้ปกครอง', 'scard' => 'พิมพ์บัตรประจำตัวนักเรียน'],
    ];
}

function academic_menu(): array
{
    return [
        'หลักสูตร' => ['desired' => 'คุณลักษณะอันพึงประสงค์', 'rtw' => 'การอ่าน คิดวิเคราะห์และเขียน', 'course' => 'รายวิชา', 'dev_act' => 'กิจกรรมพัฒนาผู้เรียน',
            'curriculum' => 'กำหนดหลักสูตรแผนการเรียน', 'sara' => 'กลุ่มสาระการเรียนรู้', 'sara_head' => 'หัวหน้ากลุ่มสาระการเรียนรู้', 'score_import' => 'โอนคะแนนจากโปรแกรม bookmark',
            'approve' => 'ระบบขออนุมัติผลการเรียน', 'course_copy' => 'คัดลอกรายวิชาจากภาคเรียนก่อน', 'enroll' => 'ลงทะเบียนเรียน'],
        'ตารางสอน' => ['venue' => 'ห้องเรียน/ประชุม/กิจกรรม', 'school_days' => 'กำหนดวันเรียน', 'periods' => 'กำหนดคาบเรียน', 'timetable' => 'จัดตารางเรียน',
            'tt_print' => 'พิมพ์ตารางสอน', 'room_tt' => 'พิมพ์ตารางการใช้ห้องเรียน', 'calendar' => 'ปฏิทินการเรียนการสอน'],
        'เกณฑ์ประเมินผล' => ['att_type' => 'ประเภทการเข้าชั้นเรียน', 'grade_scale' => 'กำหนดระดับผลการเรียน', 'rtw_crit' => 'เกณฑ์ประเมินการอ่าน คิดวิเคราะห์ฯ', 'char_crit' => 'เกณฑ์ประเมินคุณลักษณะฯ'],
        'รายงานผลการเรียน' => ['att_sum' => 'สรุปผลบันทึกเวลาเรียน', 'score_sum' => 'สรุปคะแนนสอบรายวิชา', 'activity' => 'สรุปผลประเมินกิจกรรมพัฒนาผู้เรียน', 'char_sum' => 'สรุปคะแนนคุณลักษณะฯ',
            'rtw_sum' => 'สรุปคะแนนการอ่าน คิดวิเคราะห์ฯ', 'grade_report' => 'ผลการเรียนแบบแยกห้องเรียน', 'grade_person' => 'ผลการเรียนแบบรายบุคคล'],
        'หลักฐานการศึกษา' => ['pp1' => 'ระเบียนแสดงผลการเรียน (ปพ.1)', 'pp2' => 'วุฒิการศึกษา (ปพ.2)', 'pp3' => 'รายงานผู้สำเร็จการศึกษา (ปพ.3)', 'pp5' => 'สมุดบันทึกพัฒนาคุณภาพฯ (ปพ.5)',
            'pp6' => 'สมุดรายงานผลการพัฒนาฯ (ปพ.6)', 'pp7' => 'ใบรับรองผลการศึกษา (ปพ.7)'],
        'แจ้งผลสอบ' => ['exam_result' => 'ประกาศผลสอบประจำรายวิชา', 'under80' => 'แจ้งรายชื่อนักเรียนไม่มีสิทธิ์สอบ', 'fail_list' => 'รายชื่อนักเรียนติด 0 ร มผ มส', 'remedial' => 'แจ้งผลการสอบแก้ตัว',
            'top10' => 'จัดอันดับท็อปเท็นรายวิชา', 'gpa_rank' => 'จัดลำดับเกรดเฉลี่ย (GPA)'],
        'ประเมินสมรรถนะฯ' => ['cap_ind' => 'สมรรถนะผู้เรียนและตัวชี้วัด', 'cap_beh6' => 'พฤติกรรมของตัวชี้วัด (ป.6)', 'cap_beh3' => 'พฤติกรรมของตัวชี้วัด (ม.3)',
            'cap_eval6' => 'ประเมินสมรรถนะสำคัญฯ (ป.6)', 'cap_eval3' => 'ประเมินสมรรถนะสำคัญฯ (ม.3)', 'cap_sum' => 'สรุปผลการประเมินรายชั้นเรียน', 'cap_friend' => 'กำหนดเพื่อนประเมินผู้เรียน'],
        'วิเคราะห์ผู้เรียนรายบุคคล' => ['grasha' => 'วิเคราะห์ผู้เรียนตามแนวคิดของกราชา', 'kolb' => 'วิเคราะห์ผู้เรียนตามแนวคิดของคอล์บ', 'sa_mi' => 'วิเคราะห์ผู้เรียนตามทฤษฎีพหุปัญญา',
            'ready5' => 'ความพร้อมของผู้เรียน 5 ด้าน', 'five' => 'วิเคราะห์ผู้เรียนรายบุคคล 5 ด้าน'],
        'สารสนเทศเวลาเรียน' => ['class_att' => 'บันทึกการเข้าชั้นเรียน', 'absent' => 'รายงานสรุปนักเรียนขาดเรียน', 'absent_follow' => 'ระบบติดตามนักเรียนขาดเรียน',
            'absent_letter' => 'ออกหนังสือถึงผู้ปกครอง', 'parent_contact' => 'บันทึกการพบผู้ปกครอง', 'late_gate' => 'บันทึกนักเรียนมาสาย (ประตูโรงเรียน)',
            'under80' => 'รายงานนักเรียนเวลาเรียนไม่ถึง 80%', 'activity' => 'ทะเบียนนักเรียนเข้าร่วมกิจกรรม'],
    ];
}

function personnel_menu(): array
{
    return [
        'ข้อมูลหลัก' => ['budget_year' => 'กำหนดปีงบประมาณ', 'workdays' => 'ปฏิทินวันปฏิบัติงาน', 'work_time' => 'กำหนดเวลาปฏิบัติงาน', 'work_status' => 'สถานะการปฏิบัติงาน'],
        'บันทึกการปฏิบัติงาน' => ['checkin' => 'ลงเวลาการปฏิบัติราชการ', 'official' => 'บันทึกการไปราชการ', 'leave' => 'บันทึกการลา', 'p_out' => 'บันทึกการขออนุญาตออกนอกโรงเรียน'],
        'รายงานการปฏิบัติงาน' => ['rep_checkin' => 'รายงานการลงเวลาปฏิบัติราชการ', 'rep_official' => 'รายงานการไปราชการ', 'leave_report' => 'รายงานการลาราชการ',
            'rep_out' => 'รายงานการขออนุญาตออกนอกโรงเรียน', 'rep_summary' => 'รายงานการปฏิบัติงานแบบสรุปยอด', 'rep_person' => 'รายงานแบบแสดงรายละเอียดรายบุคคล'],
        'ทะเบียนบุคลากร' => ['personnel' => 'บุคลากร', 'permission' => 'สิทธิ์การใช้งาน', 'position' => 'ตำแหน่ง', 'department' => 'กลุ่มงาน/กลุ่มสาระ'],
    ];
}

function general_menu(): array
{
    return [
        'ข้อมูลหลัก' => ['std_early' => 'กำหนดมาตรฐานการศึกษา (ปฐมวัย)', 'std_basic' => 'กำหนดมาตรฐานการศึกษา (ขั้นพื้นฐาน)', 'crit_early' => 'เกณฑ์การให้คะแนนคุณภาพ (ปฐมวัย)',
            'crit_basic' => 'เกณฑ์การให้คะแนนคุณภาพ (ขั้นพื้นฐาน)', 'sar_cover' => 'กำหนดภาพหน้าปกรายงาน (SAR)'],
        'ประเมินคุณภาพการศึกษา' => ['qa_student' => 'ประเมินพฤติกรรมรายตัวบ่งชี้ (ผู้เรียน)', 'qa_other' => 'ประเมินพฤติกรรมรายตัวบ่งชี้ (ด้านที่ 2-5)'],
        'รายงานผลการประเมิน' => ['rep_qa' => 'รายงานแบบประเมินพฤติกรรม (ผู้เรียน)', 'ssr' => 'รายงานการประเมินตนเองของครู (SSR)', 'sar' => 'รายงานคุณภาพการศึกษาประจำปี (SAR)'],
        'ประชาสัมพันธ์และระบบ' => ['news' => 'ข่าวประกาศ', 'document' => 'เอกสาร/หนังสือเวียน', 'log' => 'ประวัติการใช้งาน'],
    ];
}

/** เมนูที่ไปยังหน้าที่มีอยู่แล้วของระบบ (หน้าครู/หน้าวิชาการ) */
function admin_links(): array
{
    return [
        'att_sum' => '?module=task&option=report_attendance', 'char_sum' => '?module=task&option=report_character', 'rtw_sum' => '?module=task&option=report_skill',
        'pp5' => '?module=task&option=report_pp5', 'pp6' => '?module=task&option=report_pp6', 'cap_eval6' => '?module=task&option=capacity_evaluate_prathom',
        'cap_eval3' => '?module=task&option=capacity_evaluate_junior', 'cap_sum' => '?module=task&option=capacity_summary', 'cap_friend' => '?module=task&option=capacity_assign_friend',
        'grasha' => '?module=task&option=grasha_report', 'kolb' => '?module=task&option=kolb_report', 'class_att' => '?module=task&option=time_attendance_byroom',
        'absent_follow' => '?module=task&option=absent_school_attention', 'absent_letter' => '?module=task&option=absent_adviser_notice',
    ];
}

/* ================================================================ ตัวช่วย */

/** รายการพื้นฐาน (catalog) พร้อมปุ่มเพิ่มรายการมาตรฐาน */
function cat_md(string $kind, string $option, string $label, array $fields, array $cols, bool $post, array $seed = [], string $scope = '')
{
    if ($seed && isset($_GET['seed']) && !md_rows($kind, null, $scope)) {
        $base = (int) round(microtime(true) * 1000);
        foreach ($seed as $i => $d) {
            md_set($kind, (string) ($base + $i), $scope, '', json_encode($d, JSON_UNESCAPED_UNICODE));
        }
        admin_done($option);
    }
    $body = md_page($kind, $option, $label, $fields, $cols, $post, $scope);
    if (is_string($body) && $seed && !md_rows($kind, null, $scope)) {
        $body = msg_box('ยังไม่มีข้อมูล — <a href="'.a_url($option, '&seed=1').'"><b>เพิ่มรายการมาตรฐาน '.count($seed).' รายการ</b></a> แล้วแก้ไขภายหลังได้', true).$body;
    }
    return $body;
}

function seed_rows(array $keys, array $rows): array
{
    return array_map(fn($r) => array_combine($keys, $r), $rows);
}

function students_q(int $room = 0, int $class = 0): array
{
    $sql = 'SELECT S.*, U.`name`, U.`sex`, U.`birthday` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE U.`active`=1';
    $p = [];
    if ($room) {
        $sql .= ' AND S.`room`=?';
        $p[] = $room;
    }
    if ($class) {
        $sql .= ' AND S.`class`=?';
        $p[] = $class;
    }
    return q($sql.' ORDER BY S.`class`, S.`room`, S.`number`, S.`student_id`', $p);
}

/** ฟอร์มเลือกค่า (GET) */
function pick_form(string $option, array $selects, string $extra = ''): string
{
    $o = '<form method="get" style="width:983px;margin:0 auto 10px;text-align:left"><input type="hidden" name="module" value="admin"/><input type="hidden" name="option" value="'.h($option).'"/><span class="search">';
    foreach ($selects as $name => [$label, $opts, $value]) {
        $o .= h($label).' '.(is_array($opts) ? sel($name, $opts, $value, 'onchange="this.form.submit()" style="height:25px;max-width:460px"') : $opts).' &nbsp; ';
    }
    return $o.$extra.'</span><div style="clear:both"></div></form>';
}

function print_btn(string $href = 'javascript:window.print()'): string
{
    return '<a href="'.h($href).'" id="pinkgreenbtns"'.($href[0] !== 'j' ? ' target="_blank"' : '').'><img src="../icon_tools/vec_print_white.png" height="16"/> พิมพ์</a>';
}

function d_in(string $name, string $iso, int $w = 100): string
{
    return '<input type="text" name="'.$name.'" value="'.h($iso ? iso_to_be($iso) : '').'" placeholder="วว/ดด/ปปปป" class="general radius" style="width:'.$w.'px;height:23px"/>';
}

function json_of(array $r): array
{
    return json_decode((string) $r['value'], true) ?: [];
}

function room_opts(bool $all = true): array
{
    return ($all ? ['0' => '- ทุกห้อง -'] : ['0' => '- เลือกห้อง -']) + room_options();
}

function logo_url(): string
{
    return '../gallery/logo/school_logo.png';
}

/* ================================================================ โมดูล 1: ข้อมูลหลัก */

function save_cfg(array $vals): void
{
    $file = ROOT_PATH.'settings/config.php';
    $config = \Kotchasan\Config::load($file);
    foreach ($vals as $k => $v) {
        $config->$k = $v;
    }
    \Kotchasan\Config::save($config, $file);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($file, true);
    }
    setcookie('mis_term', '', ['expires' => time() - 3600, 'path' => '/mis/']);
}

function adm_admins(bool $post)
{
    if (!in_array('settings', user_modules(), true)) {
        return denied_box('settings');
    }
    $names = [];
    foreach (admin_modules() as $mk => $m) {
        $names[$mk] = $m[3] ?? $m[0];
    }
    $people = personnel_rows();
    if ($post) {
        foreach ($people as $p) {
            if ((int) $p['status'] === 1) {
                continue;
            }
            $sel = array_values(array_intersect(MODULE_KEYS, array_keys((array) ($_POST['m'][$p['id']] ?? []))));
            md_set('mod_perm', (int) $p['id'], '', '', json_encode($sel));
        }
        admin_done('admins');
    }
    $ths = ['#', 'ชื่อ-นามสกุล'];
    foreach ($names as $n) {
        $ths[] = [h($n), ' style="text-align:center;font-size:11px"'];
    }
    $rows = '';
    foreach ($people as $i => $p) {
        $mine = user_modules(['id' => $p['id'], 'status' => $p['status'], 'permission' => $p['permission']]);
        $cells = [$i + 1, name_nbsp($p['name']).((int) $p['status'] === 1 ? ' <span style="color:#E74C3C">(ผู้ดูแลระบบสูงสุด)</span>' : '')];
        foreach (array_keys($names) as $mk) {
            $cells[] = [(int) $p['status'] === 1 ? '<img src="../images/1347351892_yes.png" height="14"/>'
                : '<input type="checkbox" name="m['.(int) $p['id'].']['.$mk.']" value="1"'.(in_array($mk, $mine, true) ? ' checked' : '').'/>', ' align="center"'];
        }
        $rows .= zrow($cells, $i);
    }
    $save = '<a href="javascript:void(0)" onclick="document.forms.admform.submit()" id="pinkgreenbtns"><img src="../icon_tools/vec_add_white.png" height="16"/> บันทึกสิทธิ์</a>';
    return ['กำหนดผู้ดูแลระบบและสิทธิ์การเข้าใช้งานแต่ละโมดูล', '<p style="width:983px;margin:0 auto 8px;text-align:left" class="gray_12">ติ๊กโมดูลที่บุคลากรแต่ละคนเข้าใช้งานได้ ผู้ที่เข้าโมดูลที่ไม่ได้รับสิทธิ์จะพบข้อความ "ไม่มีสิทธิ์เข้าใช้งานระบบ" (ผู้ที่ไม่ได้ติ๊กเลยจะใช้งานได้เฉพาะหน้าครูผู้สอน)</p>'
        .'<form id="admform" method="post" action="'.h(a_url('admins')).'">'.list_table('ผู้ดูแลระบบและสิทธิ์โมดูล', $ths, $rows, count($people).' คน', $save).'</form>'];
}

function adm_year(bool $post)
{
    if ($post) {
        save_cfg(['academic_year' => max(2500, min(2700, (int) ($_POST['academic_year'] ?? 0)))]);
        admin_done('year');
    }
    return admin_form('กำหนดปีการศึกษาปัจจุบัน', a_url('year'), [['ปีการศึกษา (พ.ศ.)', inp('academic_year', cfg('academic_year', date('Y') + 543), 80), true]], 'index.php');
}

function adm_term(bool $post)
{
    if ($post) {
        $t = (int) ($_POST['term'] ?? 1) === 2 ? 2 : 1;
        save_cfg(['term' => $t]);
        $key = cfg('academic_year', date('Y') + 543).'/'.$t;
        $cal = json_decode((string) md_get('calendar', $key), true) ?: [];
        if (!empty($_POST['start'])) {
            $cal['start'] = be_date_to_iso((string) $_POST['start']);
        }
        if (!empty($_POST['end'])) {
            $cal['end'] = be_date_to_iso((string) $_POST['end']);
        }
        md_set('calendar', $key, '', '', json_encode($cal, JSON_UNESCAPED_UNICODE));
        admin_done('term');
    }
    [$s, $e] = term_range();
    return admin_form('กำหนดภาคเรียนปัจจุบัน', a_url('term'), [['ภาคเรียน', sel('term', [1 => 'ภาคเรียนที่ 1', 2 => 'ภาคเรียนที่ 2'], cfg('term', 1)), true],
        ['วันเปิดภาคเรียน', d_in('start', $s, 120), false], ['วันปิดภาคเรียน', d_in('end', $e, 120), false]], 'index.php');
}

function adm_edu_level(bool $post)
{
    return cat_md('edu_level', 'edu_level', 'ระดับการศึกษา', ['name' => f_text('ระดับการศึกษา', true), 'classes' => f_text('ระดับชั้นที่เปิดสอน')], ['name' => 'ระดับการศึกษา', 'classes' => 'ระดับชั้น'], $post,
        seed_rows(['name', 'classes'], [['ปฐมวัย', 'อ.1-อ.3'], ['ประถมศึกษา', 'ป.1-ป.6'], ['มัธยมศึกษาตอนต้น', 'ม.1-ม.3'], ['มัธยมศึกษาตอนปลาย', 'ม.4-ม.6']]));
}

function adm_ptype(bool $post)
{
    return cat_md('ptype', 'ptype', 'คุณสมบัติ/ประเภทบุคลากร', ['name' => f_text('ประเภทบุคลากร', true), 'note' => f_text('หมายเหตุ')], ['name' => 'ประเภทบุคลากร', 'note' => 'หมายเหตุ'], $post,
        seed_rows(['name', 'note'], [['ข้าราชการครู', ''], ['ครูผู้ช่วย', ''], ['พนักงานราชการ', ''], ['ครูอัตราจ้าง', ''], ['ลูกจ้างประจำ', ''], ['ลูกจ้างชั่วคราว', ''], ['นักการภารโรง', '']]));
}

function adm_executive(bool $post)
{
    return md_page('executive', 'executive', 'ผู้บริหารโรงเรียน', ['person' => f_sel('บุคลากร', 'personnel_options'), 'position' => f_text('ตำแหน่งบริหาร', true), 'order' => f_num('ลำดับการแสดง', false), 'phone' => f_text('โทรศัพท์')],
        ['order' => 'ลำดับ', 'person' => 'ชื่อ-นามสกุล', 'position' => 'ตำแหน่ง', 'phone' => 'โทรศัพท์'], $post);
}

const PSTATUS = ['ปฏิบัติงาน' => 'ปฏิบัติงาน', 'ช่วยราชการ' => 'ช่วยราชการ', 'ลาศึกษาต่อ' => 'ลาศึกษาต่อ', 'พักการสอน' => 'พักการสอน', 'ย้าย' => 'ย้ายออก', 'ลาออก' => 'ลาออก', 'เกษียณ' => 'เกษียณอายุราชการ'];

function adm_pstatus(bool $post)
{
    return md_page('pstatus', 'pstatus', 'สถานภาพบุคลากร', ['person' => f_sel('บุคลากร', 'personnel_options'), 'ptype' => f_sel('ประเภทบุคลากร', fn() => md_options('ptype', 'name'), false),
        'status' => f_sel('สถานภาพ', PSTATUS), 'date' => f_date('ตั้งแต่วันที่'), 'note' => f_text('หมายเหตุ/เลขที่คำสั่ง')],
        ['person' => 'ชื่อ-นามสกุล', 'ptype' => 'ประเภท', 'status' => 'สถานภาพ', 'date' => 'ตั้งแต่', 'note' => 'หมายเหตุ'], $post);
}

function adm_dwelling(bool $post)
{
    return cat_md('dwelling', 'dwelling', 'ที่พักอาศัย', ['name' => f_text('ลักษณะที่พักอาศัย', true)], ['name' => 'ที่พักอาศัย'], $post,
        seed_rows(['name'], [['บ้านของตนเอง/บิดามารดา'], ['บ้านญาติ'], ['บ้านเช่า'], ['หอพัก'], ['วัด/มูลนิธิ'], ['อื่น ๆ']]));
}

function adm_travel(bool $post)
{
    return cat_md('travel', 'travel', 'วิธีการเดินทาง', ['name' => f_text('วิธีการเดินทางมาโรงเรียน', true)], ['name' => 'วิธีการเดินทาง'], $post,
        seed_rows(['name'], [['เดินเท้า'], ['จักรยาน'], ['รถจักรยานยนต์'], ['รถยนต์ผู้ปกครอง'], ['รถรับส่งนักเรียน'], ['รถโดยสารประจำทาง'], ['อื่น ๆ']]));
}

function adm_entry_type(bool $post)
{
    return cat_md('entry_type', 'entry_type', 'ประเภทการเข้าเรียน', ['name' => f_text('ประเภทการเข้าเรียน', true)], ['name' => 'ประเภทการเข้าเรียน'], $post,
        seed_rows(['name'], [['สอบคัดเลือก'], ['โควตา/นักเรียนเดิม'], ['ความสามารถพิเศษ'], ['ย้ายเข้าระหว่างปี'], ['อื่น ๆ']]));
}

function adm_no_room(bool $post)
{
    $rooms = cats('room');
    $rows = '';
    $i = 0;
    foreach (students_q() as $s) {
        if ((int) $s['room'] && isset($rooms[$s['room']])) {
            continue;
        }
        $rows .= zrow([++$i, h($s['student_id']), name_nbsp($s['name']), h($s['class'] ? cat('class', $s['class']) : '-'), [edit_link(a_url('student', '&func=Edit&id='.(int) $s['id'])), ' align="center"']], $i);
    }
    return list_table('นักเรียนที่ยังไม่กำหนดห้องเรียน', ['#', 'รหัสนร.', 'ชื่อ-นามสกุล', 'ระดับชั้น', ''], $rows, $i.' คน', btn('จัดการชั้นเรียน', a_url('student_class'), 'edit'));
}

const SSTATUS = ['เข้าใหม่' => 'นักเรียนเข้าใหม่', 'กำลังศึกษา' => 'กำลังศึกษา', 'จบการศึกษา' => 'จบการศึกษา', 'ย้ายออก' => 'ย้ายออก', 'ลาออก' => 'ลาออก', 'จำหน่าย' => 'จำหน่ายออก', 'พักการเรียน' => 'พักการเรียน'];
const DROPOUT = ['ย้ายออก', 'ลาออก', 'จำหน่าย', 'พักการเรียน'];

function adm_sstatus(bool $post)
{
    return md_page('sstatus', 'sstatus', 'สถานะนักเรียน', ['student' => f_student(), 'status' => f_sel('สถานะ', SSTATUS), 'date' => f_date('ตั้งแต่วันที่'),
        'entry' => f_sel('ประเภทการเข้าเรียน (นักเรียนเข้าใหม่)', fn() => md_options('entry_type', 'name'), false), 'note' => f_text('หมายเหตุ/เหตุผล')],
        ['student' => 'นักเรียน', 'status' => 'สถานะ', 'date' => 'ตั้งแต่', 'note' => 'หมายเหตุ'], $post);
}

/** สถานะล่าสุดของนักเรียนแต่ละคน */
function student_status(): array
{
    static $out = null;
    if ($out !== null) {
        return $out;
    }
    $out = [];
    foreach (md_rows('sstatus') as $r) {
        $d = json_of($r);
        $sid = (int) ($d['student'] ?? 0);
        if ($sid && (!isset($out[$sid]) || ($d['date'] ?? '') >= ($out[$sid]['date'] ?? ''))) {
            $out[$sid] = $d;
        }
    }
    return $out;
}

function status_list(array $statuses, string $title): string
{
    $sids = array_keys(array_filter(student_status(), fn($d) => in_array($d['status'] ?? '', $statuses, true)));
    $info = [];
    if ($sids) {
        foreach (q('SELECT S.*, U.`name` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`id` IN ('.implode(',', $sids).')') as $s) {
            $info[(int) $s['id']] = $s;
        }
    }
    $entry = md_options('entry_type', 'name');
    $rows = '';
    $i = 0;
    foreach (student_status() as $sid => $d) {
        if (!isset($info[$sid]) || !in_array($d['status'] ?? '', $statuses, true)) {
            continue;
        }
        $s = $info[$sid];
        $rows .= zrow([++$i, h($s['student_id']), name_nbsp($s['name']), h(class_room_short($s['class'], $s['room'])), h(SSTATUS[$d['status']] ?? $d['status']),
            !empty($d['date']) ? th_date($d['date'], ' ') : '', h(trim(($entry[$d['entry'] ?? ''] ?? '').' '.($d['note'] ?? '')))], $i);
    }
    return list_table($title, ['#', 'รหัสนร.', 'ชื่อ-นามสกุล', 'ชั้น', 'สถานะ', 'วันที่', 'หมายเหตุ'], $rows, $i.' คน', btn('กำหนดสถานะนักเรียน', a_url('sstatus'), 'edit').' '.print_btn());
}

function adm_new_student(bool $post) { return status_list(['เข้าใหม่'], 'นักเรียนเข้าใหม่'); }
function adm_graduate(bool $post) { return status_list(['จบการศึกษา'], 'นักเรียนจบการศึกษา'); }
function adm_dropout(bool $post) { return status_list(DROPOUT, 'นักเรียนออกกลางคัน (ย้าย/ลาออก/จำหน่าย/พักการเรียน)'); }

/** บัตรประจำตัว */
function id_cards(array $cards, string $title): void
{
    $school = cfg('school_name', 'โรงเรียนดรุณราษฎร์วิทยา');
    $html = '<style>.card{display:inline-block;width:85.6mm;height:54mm;border:1px solid #333;border-radius:3mm;margin:3mm;vertical-align:top;overflow:hidden;position:relative;page-break-inside:avoid;background:#fff}'
        .'.card .hd{background:#0F5A9C;color:#fff;padding:2mm 3mm;font-size:12px;display:flex;align-items:center;gap:2mm}.card .hd img{height:9mm}.card .bd{display:flex;padding:2.5mm 3mm;gap:3mm;font-size:12px}'
        .'.card .ph{width:20mm;height:25mm;border:1px solid #ccc;object-fit:cover}.card b{font-size:14px}</style>';
    foreach ($cards as [$photo, $name, $line1, $line2, $code]) {
        $html .= '<div class="card"><div class="hd"><img src="'.logo_url().'"/><div><b style="font-size:13px">'.h($school).'</b><br>'.h($title).'</div></div>'
            .'<div class="bd"><img class="ph" src="'.h($photo).'"/><div><b>'.h($name).'</b><br>'.h($line1).'<br>'.h($line2).'<br><br>รหัส <b>'.h($code).'</b></div></div></div>';
    }
    print_page($title, $cards ? $html : '<p>ไม่พบข้อมูล</p>');
}

function adm_pcard(bool $post)
{
    if (isset($_GET['print'])) {
        $pid = (int) $_GET['print'];
        $cards = [];
        foreach (personnel_rows() as $p) {
            if ($pid && (int) $p['id'] !== $pid) {
                continue;
            }
            $cards[] = [avatar_url((int) $p['id']), $p['name'], cat('position', $p['position']), cat('department', $p['department']), (string) $p['id']];
        }
        id_cards($cards, 'บัตรประจำตัวบุคลากร');
        return null;
    }
    return pick_form('pcard', ['print' => ['บุคลากร', ['0' => '- ทุกคน -'] + personnel_options(), '']], '<input type="submit" value="พิมพ์บัตร" class="general radius" style="height:25px"/>')
        .'<p style="width:983px;margin:0 auto;text-align:left" class="gray_12">บัตรขนาด 8.5 x 5.4 ซม. ใช้รูปประจำตัวจากข้อมูลผู้ใช้</p>';
}

function adm_scard(bool $post)
{
    if (isset($_GET['print'])) {
        $room = (int) ($_GET['room'] ?? 0);
        $sid = (int) ($_GET['student'] ?? 0);
        $cards = [];
        foreach ($sid ? [q1('SELECT S.*, U.`name` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`id`=?', [$sid])] : students_q($room) as $s) {
            if ($s) {
                $cards[] = [avatar_url((int) $s['id']), $s['name'], 'ชั้น '.class_room_short($s['class'], $s['room']).'  เลขที่ '.$s['number'], 'ผู้ปกครอง '.$s['parent_phone'], $s['student_id']];
            }
        }
        id_cards($cards, 'บัตรประจำตัวนักเรียน');
        return null;
    }
    return '<form method="get" target="_blank" style="width:983px;margin:0 auto 10px;text-align:left"><input type="hidden" name="module" value="admin"/><input type="hidden" name="option" value="scard"/><input type="hidden" name="print" value="1"/><span class="search">'
        .'ห้อง '.sel('room', room_opts(false), 0).' หรือนักเรียน '.sel('student', ['0' => '- ทั้งห้อง -'] + student_options(), 0, 'style="width:330px;height:25px"')
        .' <input type="submit" value="พิมพ์บัตร" class="general radius" style="height:25px"/></span><div style="clear:both"></div></form>';
}

/* ================================================================ โมดูล 2: วิชาการ — หลักสูตร */

function adm_desired(bool $post)
{
    return cat_md('desired', 'desired', 'คุณลักษณะอันพึงประสงค์', ['no' => f_num('ข้อที่'), 'name' => f_text('คุณลักษณะ', true), 'ind' => f_area('ตัวชี้วัด')], ['no' => 'ข้อ', 'name' => 'คุณลักษณะ', 'ind' => 'ตัวชี้วัด'], $post,
        seed_rows(['no', 'name', 'ind'], [[1, 'รักชาติ ศาสน์ กษัตริย์', ''], [2, 'ซื่อสัตย์สุจริต', ''], [3, 'มีวินัย', ''], [4, 'ใฝ่เรียนรู้', ''], [5, 'อยู่อย่างพอเพียง', ''], [6, 'มุ่งมั่นในการทำงาน', ''], [7, 'รักความเป็นไทย', ''], [8, 'มีจิตสาธารณะ', '']]));
}

function adm_rtw(bool $post)
{
    return cat_md('rtw', 'rtw', 'การอ่าน คิดวิเคราะห์และเขียน', ['no' => f_num('ข้อที่'), 'name' => f_text('รายการประเมิน', true), 'detail' => f_area('รายละเอียด')], ['no' => 'ข้อ', 'name' => 'รายการประเมิน', 'detail' => 'รายละเอียด'], $post,
        seed_rows(['no', 'name', 'detail'], [[1, 'สามารถอ่านเพื่อหาข้อมูลสารสนเทศเสริมประสบการณ์จากสื่อประเภทต่าง ๆ', ''], [2, 'สามารถจับประเด็นสำคัญ เปรียบเทียบ เชื่อมโยงความเป็นเหตุเป็นผลจากเรื่องที่อ่าน', ''],
            [3, 'สามารถเชื่อมโยงความสัมพันธ์ของเรื่องราว เหตุการณ์ของเรื่องที่อ่าน', ''], [4, 'สามารถแสดงความคิดเห็นต่อเรื่องที่อ่านโดยมีเหตุผลประกอบ', ''], [5, 'สามารถสรุปคุณค่า แนวคิด แง่คิดที่ได้จากการอ่าน', '']]));
}

function adm_dev_act(bool $post)
{
    return cat_md('dev_act', 'dev_act', 'กิจกรรมพัฒนาผู้เรียน', ['name' => f_text('ชื่อกิจกรรม', true), 'type' => f_sel('ประเภท', ['แนะแนว' => 'กิจกรรมแนะแนว', 'นักเรียน' => 'กิจกรรมนักเรียน', 'สังคม' => 'กิจกรรมเพื่อสังคมและสาธารณประโยชน์']),
        'hours' => f_num('ชั่วโมง/ปี', false), 'teacher' => f_sel('ครูผู้รับผิดชอบ', 'personnel_options', false)], ['name' => 'กิจกรรม', 'type' => 'ประเภท', 'hours' => 'ชั่วโมง', 'teacher' => 'ครูผู้รับผิดชอบ'], $post,
        seed_rows(['name', 'type', 'hours', 'teacher'], [['แนะแนว', 'แนะแนว', 40, ''], ['ลูกเสือ-เนตรนารี', 'นักเรียน', 40, ''], ['ชุมนุม', 'นักเรียน', 40, ''], ['กิจกรรมเพื่อสังคมและสาธารณประโยชน์', 'สังคม', 15, '']]));
}

function adm_curriculum(bool $post)
{
    return md_page('curriculum', 'curriculum', 'หลักสูตรแผนการเรียน', ['program' => f_sel('แผนการเรียน', fn() => md_options('program', 'name')), 'class' => f_sel('ระดับชั้น', fn() => cats('class')),
        'basic' => f_num('หน่วยกิตรายวิชาพื้นฐาน', false), 'extra' => f_num('หน่วยกิตรายวิชาเพิ่มเติม', false), 'hours' => f_num('ชั่วโมงกิจกรรมพัฒนาผู้เรียน', false), 'structure' => f_area('โครงสร้างรายวิชา')],
        ['program' => 'แผนการเรียน', 'class' => 'ชั้น', 'basic' => 'พื้นฐาน', 'extra' => 'เพิ่มเติม', 'hours' => 'กิจกรรม (ชม.)'], $post);
}

function adm_sara_head(bool $post)
{
    return md_page('sara_head', 'sara_head', 'หัวหน้ากลุ่มสาระการเรียนรู้', ['sara' => f_sel('กลุ่มสาระการเรียนรู้', fn() => cats('department')), 'person' => f_sel('หัวหน้ากลุ่มสาระ', 'personnel_options'), 'deputy' => f_sel('รองหัวหน้า', 'personnel_options', false)],
        ['sara' => 'กลุ่มสาระ', 'person' => 'หัวหน้า', 'deputy' => 'รองหัวหน้า'], $post);
}

/** อ่าน CSV ที่อัปโหลด (รองรับ UTF-8 และ TIS-620/Windows-874 จาก Excel) */
function csv_rows(string $field): array
{
    if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return [];
    }
    $raw = (string) file_get_contents($_FILES[$field]['tmp_name']);
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    if (!mb_check_encoding($raw, 'UTF-8')) {
        $raw = (string) @iconv('TIS-620', 'UTF-8//IGNORE', $raw);
    }
    $rows = [];
    foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
        if (trim($line) !== '') {
            $rows[] = array_map('trim', str_getcsv($line));
        }
    }
    return $rows;
}

function upload_form(string $option, string $title, string $help): string
{
    return '<div class="form_content"><form method="post" enctype="multipart/form-data" action="'.h(a_url($option)).'" style="width:964px;margin:0 auto"><div class="formframeheader" style="width:970px"><div class="content_edit"><h1 style="margin-left:25px;margin-top:-3px">'.h($title).'</h1></div></div>'
        .'<div class="formframe" style="width:970px;text-align:left;padding:15px 25px;box-sizing:border-box"><input type="file" name="csv" accept=".csv,.txt"/> <input type="submit" value="นำเข้า" class="general radius" style="height:25px"/><p class="gray_12" style="margin-top:10px">'.$help.'</p></div></form></div>';
}

function adm_score_import(bool $post)
{
    if ($post) {
        [$y, $t] = term();
        $n = 0;
        $err = [];
        foreach (csv_rows('csv') as $i => $r) {
            if (count($r) < 4 || !preg_match('/\d/', $r[0])) {
                continue;
            }
            [$code, $sidc, $mid, $fin] = $r;
            $g = trim((string) ($r[4] ?? ''));
            $c = q1('SELECT * FROM {course} WHERE `course_code`=? AND `year`=? AND `term`=?', [$code, $y, $t]);
            $s = q1('SELECT * FROM {student} WHERE `student_id`=?', [$sidc]);
            if (!$c || !$s) {
                $err[] = 'แถว '.($i + 1).': ไม่พบ'.(!$c ? 'รายวิชา '.$code : 'นักเรียน '.$sidc);
                continue;
            }
            $mid = (int) $mid;
            $fin = (int) $fin;
            $g = $g !== '' ? $g : grade_of($mid + $fin);
            $ex = q1('SELECT `id` FROM {grade} WHERE `student_id`=? AND `course_id`=?', [(int) $s['id'], (int) $c['id']]);
            if ($ex) {
                q('UPDATE {grade} SET `midterm`=?, `final`=?, `grade`=? WHERE `id`=?', [$mid, $fin, $g, (int) $ex['id']]);
            } else {
                q('INSERT INTO {grade} (`student_id`,`course_id`,`number`,`room`,`type`,`midterm`,`final`,`grade`) VALUES (?,?,?,?,0,?,?,?)', [(int) $s['id'], (int) $c['id'], (int) $s['number'], (int) $s['room'], $mid, $fin, $g]);
            }
            $n++;
        }
        return msg_box('นำเข้าคะแนน '.$n.' รายการ'.($err ? '<br>'.h(implode(' / ', array_slice($err, 0, 10))) : ''), !$err).adm_score_import(false);
    }
    return upload_form('score_import', 'โอนคะแนนจากโปรแกรม bookmark / Excel', 'ส่งออกจากโปรแกรม bookmark หรือ Excel เป็นไฟล์ CSV คอลัมน์: <b>รหัสวิชา, รหัสนักเรียน, คะแนนระหว่างภาค, คะแนนปลายภาค, ผลการเรียน (เว้นว่างให้คำนวณ)</b><br>รายวิชาต้องมีในภาคเรียนปัจจุบัน ('.yt().') หากมีคะแนนเดิมจะถูกแทนที่');
}

/* ---------------------------------------------------------------- ตารางสอน */

function adm_venue(bool $post)
{
    return cat_md('venue', 'venue', 'ห้องเรียน/ห้องประชุม/สถานที่', ['name' => f_text('ชื่อห้อง/สถานที่', true), 'type' => f_sel('ประเภท', ['ห้องเรียน' => 'ห้องเรียน', 'ห้องปฏิบัติการ' => 'ห้องปฏิบัติการ', 'ห้องประชุม' => 'ห้องประชุม', 'กิจกรรม' => 'สถานที่จัดกิจกรรม']),
        'building' => f_text('อาคาร'), 'seats' => f_num('จำนวนที่นั่ง', false)], ['name' => 'ห้อง/สถานที่', 'type' => 'ประเภท', 'building' => 'อาคาร', 'seats' => 'ที่นั่ง'], $post);
}

const WEEKDAYS = [1 => 'จันทร์', 2 => 'อังคาร', 3 => 'พุธ', 4 => 'พฤหัสบดี', 5 => 'ศุกร์', 6 => 'เสาร์', 0 => 'อาทิตย์'];

function adm_school_days(bool $post)
{
    if ($post) {
        md_set('cfg', 'school_days', '', '', json_encode(array_map('intval', array_keys((array) ($_POST['d'] ?? [])))));
        admin_done('school_days');
    }
    $days = json_decode((string) md_get('cfg', 'school_days'), true) ?? [1, 2, 3, 4, 5];
    $h = '';
    foreach (WEEKDAYS as $k => $n) {
        $h .= '<label style="margin-right:15px"><input type="checkbox" name="d['.$k.']" value="1"'.(in_array($k, $days, true) ? ' checked' : '').'/> '.$n.'</label>';
    }
    return admin_form('กำหนดวันเรียน', a_url('school_days'), [['วันที่มีการเรียนการสอน', $h, true]], 'index.php');
}

function adm_periods(bool $post)
{
    return cat_md('periods', 'periods', 'คาบเรียน', ['no' => f_num('คาบที่'), 'start' => f_text('เวลาเริ่ม (เช่น 08:30)', true), 'end' => f_text('เวลาสิ้นสุด', true), 'note' => f_text('หมายเหตุ')],
        ['no' => 'คาบที่', 'start' => 'เริ่ม', 'end' => 'สิ้นสุด', 'note' => 'หมายเหตุ'], $post,
        seed_rows(['no', 'start', 'end', 'note'], [[1, '08:30', '09:20', ''], [2, '09:20', '10:10', ''], [3, '10:10', '11:00', ''], [4, '11:00', '11:50', ''], [5, '12:50', '13:40', ''], [6, '13:40', '14:30', ''], [7, '14:30', '15:20', ''], [8, '15:20', '16:10', '']]));
}

function adm_tt_print(bool $post)
{
    $rows = '';
    foreach (personnel_rows() as $i => $p) {
        $rows .= zrow([$i + 1, name_nbsp($p['name']), h(cat('department', $p['department'])), ['<a href="?module=task&option=teacher_timetable&TeacherId='.(int) $p['id'].'">ดู/พิมพ์ตารางสอน</a>', ' align="center"']], $i);
    }
    return list_table('พิมพ์ตารางสอนรายบุคคล ภาคเรียน '.yt(), ['#', 'ชื่อ-นามสกุล', 'กลุ่มสาระ', ''], $rows);
}

function adm_room_tt(bool $post)
{
    $rows = '';
    $i = 0;
    foreach (room_options() as $rid => $n) {
        $rows .= zrow([++$i, h($n), ['<a target="_blank" href="?module=academic&option=student_timetable&func=RoomTable&RoomNum='.(int) $rid.'">พิมพ์ตารางการใช้ห้องเรียน</a>', ' align="center"']], $i);
    }
    return list_table('พิมพ์ตารางการใช้ห้องเรียน ภาคเรียน '.yt(), ['#', 'ห้องเรียน', ''], $rows);
}

/* ---------------------------------------------------------------- เกณฑ์ประเมินผล */

function adm_att_type(bool $post)
{
    return cat_md('att_type', 'att_type', 'ประเภทการเข้าชั้นเรียน', ['code' => f_text('อักษรย่อ', true), 'name' => f_text('ความหมาย', true), 'count' => f_sel('นับเป็นเวลาเรียน', ['ใช่' => 'นับ', 'ไม่' => 'ไม่นับ'])],
        ['code' => 'อักษรย่อ', 'name' => 'ความหมาย', 'count' => 'นับเวลาเรียน'], $post,
        seed_rows(['code', 'name', 'count'], [['/', 'มาเรียน', 'ใช่'], ['ส', 'มาสาย', 'ใช่'], ['ล', 'ลา', 'ไม่'], ['ป', 'ป่วย', 'ไม่'], ['ข', 'ขาดเรียน', 'ไม่'], ['ก', 'กิจกรรม', 'ใช่']]));
}

function adm_grade_scale(bool $post)
{
    if ($post) {
        $sc = [];
        foreach (GRADE_SCALE as [$min, $g]) {
            $sc[] = [max(0, min(100, (int) ($_POST['g'][$g] ?? $min))), $g];
        }
        usort($sc, fn($a, $b) => $b[0] <=> $a[0]);
        md_set('cfg', 'grade_scale', '', '', json_encode($sc));
        admin_done('grade_scale');
    }
    $rows = [];
    foreach (grade_scale() as [$min, $g]) {
        $rows[] = ['ผลการเรียน '.$g.' เมื่อได้คะแนนตั้งแต่', inp('g['.$g.']', $min, 60).' คะแนน', true];
    }
    return admin_form('กำหนดระดับผลการเรียน (คะแนนรวมระหว่างภาค+ปลายภาค 100 คะแนน)', a_url('grade_scale'), $rows, 'index.php');
}

function adm_rtw_crit(bool $post)
{
    return cat_md('rtw_crit', 'rtw_crit', 'เกณฑ์การอ่าน คิดวิเคราะห์ฯ', ['level' => f_text('ระดับ', true), 'min' => f_num('คะแนนเฉลี่ยตั้งแต่'), 'meaning' => f_area('คำอธิบาย')], ['level' => 'ระดับ', 'min' => 'ตั้งแต่', 'meaning' => 'คำอธิบาย'], $post,
        seed_rows(['level', 'min', 'meaning'], [['ดีเยี่ยม', 2.5, ''], ['ดี', 1.5, ''], ['ผ่าน', 1, ''], ['ไม่ผ่าน', 0, '']]));
}

function adm_char_crit(bool $post)
{
    return cat_md('char_crit', 'char_crit', 'เกณฑ์คุณลักษณะอันพึงประสงค์', ['level' => f_text('ระดับ', true), 'min' => f_num('คะแนนเฉลี่ยตั้งแต่'), 'meaning' => f_area('คำอธิบาย')], ['level' => 'ระดับ', 'min' => 'ตั้งแต่', 'meaning' => 'คำอธิบาย'], $post,
        seed_rows(['level', 'min', 'meaning'], [['ดีเยี่ยม', 2.5, ''], ['ดี', 1.5, ''], ['ผ่าน', 1, ''], ['ไม่ผ่าน', 0, '']]));
}

/* ---------------------------------------------------------------- ผลการเรียน */

function term_courses(): array
{
    [$y, $t] = term();
    return q('SELECT C.*, U.`name` teacher FROM {course} C LEFT JOIN {user} U ON U.`id`=C.`teacher_id` WHERE C.`year`=? AND C.`term`=? ORDER BY C.`course_code`', [$y, $t]);
}

function course_opts(): array
{
    $o = [];
    foreach (term_courses() as $c) {
        $o[$c['id']] = $c['course_code'].' '.$c['course_name'].' ('.cat('class', $c['class']).')';
    }
    return $o;
}

function adm_score_sum(bool $post)
{
    $courses = term_courses();
    $gc = grade_counts(array_map(fn($c) => (int) $c['id'], $courses));
    $avg = [];
    if ($courses) {
        foreach (q('SELECT `course_id`, AVG(`midterm`) m, AVG(`final`) f, MAX(`midterm`+`final`) mx, MIN(`midterm`+`final`) mn FROM {grade} WHERE `course_id` IN ('.implode(',', array_map(fn($c) => (int) $c['id'], $courses)).') GROUP BY `course_id`') as $r) {
            $avg[(int) $r['course_id']] = $r;
        }
    }
    $rows = '';
    foreach ($courses as $i => $c) {
        $a = $avg[(int) $c['id']] ?? null;
        $g = $gc[(int) $c['id']] ?? [];
        $cells = [$i + 1, h($c['course_code'].' '.$c['course_name']), h($c['teacher']), [array_sum($g), ' align="center"'], [$a ? number_format($a['m'], 2) : '-', ' align="center"'],
            [$a ? number_format($a['f'], 2) : '-', ' align="center"'], [$a ? number_format($a['m'] + $a['f'], 2) : '-', ' align="center"'], [$a ? $a['mx'].' / '.$a['mn'] : '-', ' align="center"']];
        foreach (['4', '3', '2', '1', '0'] as $k) {
            $cells[] = [(int) ($g[$k] ?? 0) + (int) ($g[$k.'.5'] ?? 0), ' align="center"'];
        }
        $rows .= zrow($cells, $i);
    }
    return list_table('สรุปคะแนนสอบรายวิชา ภาคเรียน '.yt(), ['#', 'รายวิชา', 'ครูผู้สอน', 'นร.', 'ระหว่างภาค', 'ปลายภาค', 'เฉลี่ยรวม', 'สูงสุด/ต่ำสุด', '4-3.5', '3-2.5', '2-1.5', '1', '0'], $rows, '', print_btn());
}

/** ผลการเรียนทุกภาคของนักเรียน */
function student_grades(int $sid): array
{
    return q('SELECT G.*, C.`course_code`, C.`course_name`, C.`credit`, C.`year`, C.`term`, C.`type` ctype FROM {grade} G JOIN {course} C ON C.`id`=G.`course_id` WHERE G.`student_id`=? ORDER BY C.`year`, C.`term`, C.`course_code`', [$sid]);
}

function gpa_of(array $grades): ?float
{
    $cr = 0.0;
    $sum = 0.0;
    foreach ($grades as $g) {
        if (is_numeric($g['grade']) && (float) $g['credit'] > 0) {
            $cr += (float) $g['credit'];
            $sum += (float) $g['credit'] * (float) $g['grade'];
        }
    }
    return $cr > 0 ? $sum / $cr : null;
}

function grade_table(array $grades): string
{
    $html = '';
    $byTerm = [];
    foreach ($grades as $g) {
        $byTerm[$g['year'].'/'.$g['term']][] = $g;
    }
    foreach ($byTerm as $yt => $gs) {
        $html .= '<tr><th colspan="5" style="text-align:left;background:#eef6fb">ภาคเรียนที่ '.h(explode('/', $yt)[1]).' ปีการศึกษา '.h(explode('/', $yt)[0]).'</th></tr>';
        foreach ($gs as $g) {
            $html .= '<tr><td>'.h($g['course_code']).'</td><td>'.h($g['course_name']).'</td><td class="c">'.h($g['credit']).'</td><td class="c">'.((int) $g['midterm'] + (int) $g['final']).'</td><td class="c"><b>'.h($g['grade']).'</b></td></tr>';
        }
        $gpa = gpa_of($gs);
        $html .= '<tr><td colspan="4" style="text-align:right">ผลการเรียนเฉลี่ยภาคเรียนนี้</td><td class="c"><b>'.($gpa === null ? '-' : number_format($gpa, 2)).'</b></td></tr>';
    }
    $all = gpa_of($grades);
    return '<table class="mis-gt"><tr><th>รหัสวิชา</th><th>รายวิชา</th><th>หน่วยกิต</th><th>คะแนน</th><th>ผลการเรียน</th></tr>'.($html ?: '<tr><td colspan="5" class="c">ไม่พบผลการเรียน</td></tr>')
        .'<tr><td colspan="4" style="text-align:right"><b>ผลการเรียนเฉลี่ยสะสม (GPAX)</b></td><td class="c"><b>'.($all === null ? '-' : number_format($all, 2)).'</b></td></tr></table>';
}

function student_head(int $sid): ?array
{
    return q1('SELECT S.*, U.`name`, U.`birthday`, U.`sex` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`id`=?', [$sid]);
}

function adm_grade_person(bool $post)
{
    $sid = (int) ($_GET['student'] ?? 0);
    $f = pick_form('grade_person', ['student' => ['นักเรียน', ['' => '- เลือกนักเรียน -'] + student_options(), $sid]]);
    if (!$sid || !($s = student_head($sid))) {
        return $f;
    }
    $css = '<style>.mis-gt{width:983px;margin:0 auto;border-collapse:collapse;background:#fff}.mis-gt th,.mis-gt td{border:1px solid #c6d5e1;padding:5px;font-size:12px}.mis-gt th{background:#F1F8FD}.mis-gt .c{text-align:center}</style>';
    return $f.$css.'<div style="width:983px;margin:0 auto 8px;text-align:left"><b style="font-size:15px">'.h($s['name']).'</b> รหัส '.h($s['student_id']).' ชั้น '.h(class_room_short($s['class'], $s['room']))
        .' <span style="float:right">'.print_btn(a_url('pp1', '&print=1&student='.$sid)).'</span></div>'.grade_table(student_grades($sid));
}

/* ---------------------------------------------------------------- หลักฐานการศึกษา */

function pp_pick(string $option, string $note): string
{
    return '<form method="get" target="_blank" style="width:983px;margin:0 auto 10px;text-align:left"><input type="hidden" name="module" value="admin"/><input type="hidden" name="option" value="'.$option.'"/><input type="hidden" name="print" value="1"/><span class="search">นักเรียน '
        .sel('student', ['' => '- เลือกนักเรียน -'] + student_options(), '', 'style="width:450px;height:25px"').' <input type="submit" value="พิมพ์" class="general radius" style="height:25px"/></span><div style="clear:both"></div></form>'
        .'<p style="width:983px;margin:0 auto;text-align:left" class="gray_12">'.$note.'</p>';
}

function doc_head(string $title, string $code): string
{
    return '<div style="text-align:right;font-size:12px">'.h($code).'</div><div style="text-align:center"><img src="'.logo_url().'" style="height:70px"/><h1>'.h($title).'</h1><h2>'.h(cfg('school_name', 'โรงเรียนดรุณราษฎร์วิทยา')).'</h2></div>';
}

function student_info_table(array $s): string
{
    return '<table style="margin-top:10px"><tr><td>ชื่อ-นามสกุล <b>'.h($s['name']).'</b></td><td>เลขประจำตัวนักเรียน <b>'.h($s['student_id']).'</b></td></tr>'
        .'<tr><td>เลขประจำตัวประชาชน '.h($s['id_card'] ?: '-').'</td><td>วันเกิด '.(!empty($s['birthday']) && $s['birthday'] !== '0000-00-00' ? th_date($s['birthday'], ' ') : '-').'</td></tr>'
        .'<tr><td>ชั้น '.h(class_room_short($s['class'], $s['room'])).'</td><td>ผู้ปกครอง '.h($s['parent'] ?: '-').'</td></tr></table>';
}

function signs(array $who): string
{
    $o = '<table style="margin-top:50px;border:none"><tr>';
    foreach ($who as $w) {
        $o .= '<td style="border:none;text-align:center">ลงชื่อ ........................................<br>( ........................................ )<br>'.h($w).'</td>';
    }
    return $o.'</tr></table>';
}

function adm_pp1(bool $post)
{
    if (isset($_GET['print']) && ($s = student_head((int) ($_GET['student'] ?? 0)))) {
        print_page('ปพ.1', doc_head('ระเบียนแสดงผลการเรียน', 'ปพ.1').student_info_table($s).str_replace('class="mis-gt"', '', grade_table(student_grades((int) $s['id']))).signs(['นายทะเบียน', 'ผู้อำนวยการโรงเรียน']));
        return null;
    }
    return pp_pick('pp1', 'พิมพ์ระเบียนแสดงผลการเรียนทุกภาคเรียนจากผลการเรียนที่บันทึกในระบบ พร้อมผลการเรียนเฉลี่ยสะสม');
}

function adm_pp2(bool $post)
{
    if (isset($_GET['print']) && ($s = student_head((int) ($_GET['student'] ?? 0)))) {
        $gpa = gpa_of(student_grades((int) $s['id']));
        print_page('ปพ.2', '<div style="border:6px double #0F5A9C;padding:30px;text-align:center">'.doc_head('ประกาศนียบัตร', 'ปพ.2')
            .'<p style="font-size:18px;margin-top:20px">ให้ไว้เพื่อแสดงว่า</p><p style="font-size:26px"><b>'.h($s['name']).'</b></p>'
            .'<p style="font-size:16px">เลขประจำตัวนักเรียน '.h($s['student_id']).' ได้สำเร็จการศึกษาตามหลักสูตรแกนกลางการศึกษาขั้นพื้นฐาน<br>ระดับชั้น '.h(cat('class', $s['class'])).' ผลการเรียนเฉลี่ยสะสม '.($gpa === null ? '-' : number_format($gpa, 2)).'</p>'
            .'<p>ให้ไว้ ณ วันที่ '.th_date('', ' ').'</p>'.signs(['ผู้อำนวยการโรงเรียน']).'</div>');
        return null;
    }
    return pp_pick('pp2', 'พิมพ์ประกาศนียบัตร (วุฒิการศึกษา) ของนักเรียนที่สำเร็จการศึกษา');
}

function adm_pp3(bool $post)
{
    $sids = array_keys(array_filter(student_status(), fn($d) => ($d['status'] ?? '') === 'จบการศึกษา'));
    $rows = '';
    $html = '';
    if ($sids) {
        foreach (q('SELECT S.*, U.`name` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`id` IN ('.implode(',', $sids).') ORDER BY S.`class`, S.`room`, S.`number`') as $i => $s) {
            $gpa = gpa_of(student_grades((int) $s['id']));
            $g = $gpa === null ? '-' : number_format($gpa, 2);
            $rows .= zrow([$i + 1, h($s['student_id']), name_nbsp($s['name']), h(class_room_short($s['class'], $s['room'])), [$g, ' align="center"']], $i);
            $html .= '<tr><td class="c">'.($i + 1).'</td><td>'.h($s['student_id']).'</td><td>'.h($s['name']).'</td><td>'.h(class_room_short($s['class'], $s['room'])).'</td><td class="c">'.$g.'</td></tr>';
        }
    }
    if (isset($_GET['print'])) {
        print_page('ปพ.3', doc_head('รายงานผู้สำเร็จการศึกษา', 'ปพ.3').'<table><tr><th>ที่</th><th>เลขประจำตัว</th><th>ชื่อ-นามสกุล</th><th>ชั้น</th><th>GPAX</th></tr>'.($html ?: '<tr><td colspan="5" class="c">-</td></tr>').'</table>'.signs(['นายทะเบียน', 'ผู้อำนวยการโรงเรียน']));
        return null;
    }
    return list_table('รายงานผู้สำเร็จการศึกษา (นักเรียนที่มีสถานะ "จบการศึกษา")', ['#', 'รหัสนร.', 'ชื่อ-นามสกุล', 'ชั้น', ['GPAX', ' style="text-align:center"']], $rows, count($sids).' คน', btn('กำหนดสถานะนักเรียน', a_url('sstatus'), 'edit').' '.print_btn(a_url('pp3', '&print=1')));
}

function adm_pp7(bool $post)
{
    if (isset($_GET['print']) && ($s = student_head((int) ($_GET['student'] ?? 0)))) {
        $gpa = gpa_of(student_grades((int) $s['id']));
        $st = student_status()[(int) $s['id']]['status'] ?? 'กำลังศึกษา';
        print_page('ปพ.7', doc_head('ใบรับรองผลการศึกษา', 'ปพ.7').student_info_table($s)
            .'<p style="text-indent:50px;margin-top:20px;font-size:15px">'.h(cfg('school_name', 'โรงเรียนดรุณราษฎร์วิทยา')).' ขอรับรองว่า '.h($s['name']).' '.($st === 'จบการศึกษา' ? 'ได้สำเร็จการศึกษา' : 'กำลังศึกษาอยู่').'ในระดับชั้น '.h(cat('class', $s['class']))
            .' ภาคเรียนที่ '.h(explode('/', yt())[1]).' ปีการศึกษา '.h(explode('/', yt())[0]).' มีผลการเรียนเฉลี่ยสะสม '.($gpa === null ? '-' : number_format($gpa, 2)).'</p><p style="text-indent:50px">ให้ไว้ ณ วันที่ '.th_date('', ' ').'</p>'.signs(['ผู้อำนวยการโรงเรียน']));
        return null;
    }
    return pp_pick('pp7', 'พิมพ์ใบรับรองผลการศึกษา (รับรองสถานภาพนักเรียนและผลการเรียนเฉลี่ยสะสม)');
}

/* ---------------------------------------------------------------- แจ้งผลสอบ */

function course_grade_rows(int $cid, string $order = 'S.`number`'): array
{
    return q('SELECT G.*, S.`student_id` code, S.`class`, S.`room` sroom, S.`number` sno, U.`name` FROM {grade} G JOIN {student} S ON S.`id`=G.`student_id` JOIN {user} U ON U.`id`=G.`student_id` WHERE G.`course_id`=? ORDER BY '.$order, [$cid]);
}

function adm_exam_result(bool $post)
{
    $cid = (int) ($_GET['course'] ?? 0);
    $f = pick_form('exam_result', ['course' => ['รายวิชา', ['' => '- เลือกรายวิชา -'] + course_opts(), $cid]]);
    if (!$cid) {
        return $f;
    }
    $rows = '';
    foreach (course_grade_rows($cid, 'S.`room`, S.`number`') as $i => $g) {
        $rows .= zrow([$i + 1, h($g['code']), name_nbsp($g['name']), h(class_room_short($g['class'], $g['sroom'])), [(int) $g['midterm'], ' align="center"'], [(int) $g['final'], ' align="center"'],
            [(int) $g['midterm'] + (int) $g['final'], ' align="center"'], ['<b>'.h($g['grade']).'</b>', ' align="center"']], $i);
    }
    return $f.list_table('ประกาศผลสอบ '.h(course_opts()[$cid] ?? ''), ['#', 'รหัสนร.', 'ชื่อ-นามสกุล', 'ห้อง', 'ระหว่างภาค', 'ปลายภาค', 'รวม', 'ผลการเรียน'], $rows, '', print_btn());
}

function adm_fail_list(bool $post)
{
    [$y, $t] = term();
    $rows = '';
    foreach (q('SELECT G.*, C.`course_code`, C.`course_name`, S.`student_id` code, S.`class`, S.`room` sroom, U.`name` FROM {grade} G JOIN {course} C ON C.`id`=G.`course_id` JOIN {student} S ON S.`id`=G.`student_id` JOIN {user} U ON U.`id`=G.`student_id`'
        .' WHERE C.`year`=? AND C.`term`=? AND G.`grade` IN (\'0\',\'ร\',\'มผ\',\'มส\') ORDER BY S.`class`, S.`room`, S.`number`', [$y, $t]) as $i => $g) {
        $rows .= zrow([$i + 1, h($g['code']), name_nbsp($g['name']), h(class_room_short($g['class'], $g['sroom'])), h($g['course_code'].' '.$g['course_name']), ['<b style="color:#E74C3C">'.h($g['grade']).'</b>', ' align="center"']], $i);
    }
    return list_table('รายชื่อนักเรียนติด 0 ร มผ มส ภาคเรียน '.yt(), ['#', 'รหัสนร.', 'ชื่อ-นามสกุล', 'ชั้น', 'รายวิชา', 'ผล'], $rows, '', print_btn());
}

function adm_remedial(bool $post)
{
    if (!$post && ($_GET['func'] ?? '') === '') {
        remedial_apply();
    }
    return md_page('remedial', 'remedial', 'ผลการสอบแก้ตัว', ['student' => f_student(), 'course' => f_sel('รายวิชา', 'course_opts'), 'old' => f_sel('ผลการเรียนเดิม', ['0' => '0', 'ร' => 'ร', 'มผ' => 'มผ', 'มส' => 'มส']),
        'new' => f_sel('ผลการสอบแก้ตัว', ['1' => '1', '0' => '0 (ไม่ผ่าน)', 'ผ' => 'ผ', 'มผ' => 'มผ']), 'date' => f_date('วันที่สอบแก้ตัว'), 'apply' => f_sel('ปรับผลการเรียนในระบบ', ['ใช่' => 'ปรับเป็นผลใหม่', 'ไม่' => 'บันทึกอย่างเดียว'], false)],
        ['student' => 'นักเรียน', 'course' => 'รายวิชา', 'old' => 'ผลเดิม', 'new' => 'ผลแก้ตัว', 'date' => 'วันที่'], $post, yt());
}

/** หลังบันทึกผลสอบแก้ตัว ปรับเกรดในตารางผลการเรียน */
function remedial_apply(): void
{
    foreach (md_rows('remedial', null, yt()) as $r) {
        $d = json_of($r);
        if (($d['apply'] ?? '') === 'ใช่' && !empty($d['student']) && !empty($d['course'])) {
            q('UPDATE {grade} SET `grade`=? WHERE `student_id`=? AND `course_id`=?', [(string) $d['new'], (int) $d['student'], (int) $d['course']]);
        }
    }
}

function adm_top10(bool $post)
{
    $cid = (int) ($_GET['course'] ?? 0);
    $f = pick_form('top10', ['course' => ['รายวิชา', ['' => '- เลือกรายวิชา -'] + course_opts(), $cid]]);
    if (!$cid) {
        return $f;
    }
    $rows = '';
    foreach (array_slice(course_grade_rows($cid, '(G.`midterm`+G.`final`) DESC, S.`number`'), 0, 10) as $i => $g) {
        $rows .= zrow([['<b>'.($i + 1).'</b>', ' align="center"'], h($g['code']), name_nbsp($g['name']), h(class_room_short($g['class'], $g['sroom'])), [(int) $g['midterm'] + (int) $g['final'], ' align="center"'], [h($g['grade']), ' align="center"']], $i);
    }
    return $f.list_table('10 อันดับคะแนนสูงสุด '.h(course_opts()[$cid] ?? ''), [['อันดับ', ' style="text-align:center"'], 'รหัสนร.', 'ชื่อ-นามสกุล', 'ห้อง', 'คะแนนรวม', 'ผลการเรียน'], $rows, '', print_btn());
}

function adm_gpa_rank(bool $post)
{
    [$y, $t] = term();
    $class = (int) ($_GET['class'] ?? 0);
    $room = (int) ($_GET['room'] ?? 0);
    $f = pick_form('gpa_rank', ['class' => ['ระดับชั้น', ['0' => '- ทุกชั้น -'] + cats('class'), $class], 'room' => ['ห้อง', room_opts(), $room]]);
    $list = [];
    foreach (students_q($room, $class) as $s) {
        $gs = array_filter(student_grades((int) $s['id']), fn($g) => (int) $g['year'] === (int) $y && (int) $g['term'] === (int) $t);
        $gpa = gpa_of($gs);
        if ($gpa !== null) {
            $list[] = [$gpa, $s, count($gs)];
        }
    }
    usort($list, fn($a, $b) => $b[0] <=> $a[0]);
    $rows = '';
    $rank = 0;
    $prev = null;
    foreach ($list as $i => [$gpa, $s, $n]) {
        if ($prev === null || abs($gpa - $prev) > 0.0001) {
            $rank = $i + 1;
        }
        $prev = $gpa;
        $rows .= zrow([[$rank, ' align="center"'], h($s['student_id']), name_nbsp($s['name']), h(class_room_short($s['class'], $s['room'])), [$n, ' align="center"'], ['<b>'.number_format($gpa, 2).'</b>', ' align="center"']], $i);
    }
    return $f.list_table('จัดลำดับผลการเรียนเฉลี่ย (GPA) ภาคเรียน '.yt(), [['อันดับ', ' style="text-align:center"'], 'รหัสนร.', 'ชื่อ-นามสกุล', 'ชั้น', 'จำนวนวิชา', 'GPA'], $rows, count($list).' คน', print_btn());
}

/* ---------------------------------------------------------------- สมรรถนะ / วิเคราะห์ผู้เรียน */

const CAPS = ['ความสามารถในการสื่อสาร', 'ความสามารถในการคิด', 'ความสามารถในการแก้ปัญหา', 'ความสามารถในการใช้ทักษะชีวิต', 'ความสามารถในการใช้เทคโนโลยี'];

function adm_cap_ind(bool $post)
{
    return cat_md('cap_ind', 'cap_ind', 'สมรรถนะสำคัญของผู้เรียน', ['no' => f_num('ข้อที่'), 'name' => f_text('สมรรถนะ', true), 'ind' => f_area('ตัวชี้วัด')], ['no' => 'ข้อ', 'name' => 'สมรรถนะ', 'ind' => 'ตัวชี้วัด'], $post,
        array_map(fn($n, $i) => ['no' => $i + 1, 'name' => $n, 'ind' => ''], CAPS, array_keys(CAPS)));
}

function cap_beh(string $kind, string $label, bool $post)
{
    return md_page($kind, $kind, $label, ['cap' => f_sel('สมรรถนะ', fn() => md_options('cap_ind', 'name') ?: array_combine(CAPS, CAPS)), 'ind' => f_text('ตัวชี้วัด', true), 'beh' => f_area('พฤติกรรมบ่งชี้', true)],
        ['cap' => 'สมรรถนะ', 'ind' => 'ตัวชี้วัด', 'beh' => 'พฤติกรรมบ่งชี้'], $post);
}

function adm_cap_beh6(bool $post) { return cap_beh('cap_beh6', 'พฤติกรรมบ่งชี้ (ป.6)', $post); }
function adm_cap_beh3(bool $post) { return cap_beh('cap_beh3', 'พฤติกรรมบ่งชี้ (ม.3)', $post); }

const FIVE_LEVEL = ['ดีมาก' => 'ดีมาก', 'ดี' => 'ดี', 'พอใช้' => 'พอใช้', 'ปรับปรุง' => 'ควรปรับปรุง'];

function adm_ready5(bool $post)
{
    $sides = ['body' => 'ด้านร่างกาย', 'mind' => 'ด้านอารมณ์-จิตใจ', 'social' => 'ด้านสังคม', 'brain' => 'ด้านสติปัญญา', 'lang' => 'ด้านภาษาและการสื่อสาร'];
    $fields = ['student' => f_student(), 'date' => f_date('วันที่ประเมิน')];
    foreach ($sides as $k => $l) {
        $fields[$k] = f_sel($l, FIVE_LEVEL);
    }
    $fields['note'] = f_area('ข้อเสนอแนะ');
    return md_page('ready5', 'ready5', 'ความพร้อมของผู้เรียน 5 ด้าน', $fields, ['student' => 'นักเรียน'] + array_map(fn($l) => str_replace('ด้าน', '', $l), $sides), $post, yt());
}

function adm_five(bool $post)
{
    $sides = ['know' => 'ด้านความรู้ความสามารถ', 'skill' => 'ด้านทักษะ/กระบวนการ', 'health' => 'ด้านสุขภาพร่างกาย', 'habit' => 'ด้านคุณลักษณะ/นิสัย', 'social' => 'ด้านอารมณ์และสังคม'];
    $fields = ['student' => f_student(), 'date' => f_date('วันที่วิเคราะห์')];
    foreach ($sides as $k => $l) {
        $fields[$k] = f_sel($l, FIVE_LEVEL);
    }
    $fields['plan'] = f_area('แนวทางพัฒนา');
    return md_page('five', 'five', 'วิเคราะห์ผู้เรียนรายบุคคล 5 ด้าน', $fields, ['student' => 'นักเรียน'] + array_map(fn($l) => str_replace('ด้าน', '', $l), $sides), $post, yt());
}

/* ---------------------------------------------------------------- สารสนเทศเวลาเรียน */

function adm_parent_contact(bool $post)
{
    return md_page('parent_contact', 'parent_contact', 'การพบผู้ปกครอง', ['student' => f_student(), 'date' => f_date(), 'who' => f_text('ผู้ปกครองที่พบ', true), 'topic' => f_area('เรื่องที่พูดคุย', true), 'result' => f_area('ผล/ข้อตกลง')],
        ['student' => 'นักเรียน', 'date' => 'วันที่', 'who' => 'ผู้ปกครอง', 'topic' => 'เรื่อง'], $post, yt());
}

function adm_late_gate(bool $post)
{
    return md_page('late_gate', 'late_gate', 'นักเรียนมาสาย (ประตูโรงเรียน)', ['student' => f_student(), 'date' => f_date(), 'time' => f_text('เวลามาถึง', true), 'reason' => f_text('สาเหตุ'), 'by' => f_sel('ครูเวรผู้บันทึก', 'personnel_options', false)],
        ['student' => 'นักเรียน', 'date' => 'วันที่', 'time' => 'เวลา', 'reason' => 'สาเหตุ', 'by' => 'ครูเวร'], $post, yt());
}

/* ================================================================ โมดูล 4: บุคลากร */

function adm_budget_year(bool $post)
{
    if ($post) {
        md_set('cfg', 'budget_year', '', '', (string) max(2500, min(2700, (int) ($_POST['y'] ?? 0))));
        admin_done('budget_year');
    }
    return admin_form('กำหนดปีงบประมาณ', a_url('budget_year'), [['ปีงบประมาณ (พ.ศ.)', inp('y', budget_year(), 80), true]], 'index.php');
}

function budget_year(): int
{
    $v = md_get('cfg', 'budget_year');
    return $v ? (int) $v : (int) date('Y') + 543 + ((int) date('n') >= 10 ? 1 : 0);
}

function adm_workdays(bool $post)
{
    return md_page('workdays', 'workdays', 'ปฏิทินวันปฏิบัติงาน', ['date' => f_date(), 'type' => f_sel('ประเภท', ['หยุด' => 'วันหยุดราชการ/วันหยุดพิเศษ', 'ทำงาน' => 'วันทำงานพิเศษ (เสาร์/อาทิตย์)']), 'name' => f_text('รายละเอียด', true)],
        ['date' => 'วันที่', 'type' => 'ประเภท', 'name' => 'รายละเอียด'], $post);
}

function work_time(): array
{
    return (json_decode((string) md_get('cfg', 'work_time'), true) ?: []) + ['in' => '08:30', 'out' => '16:30'];
}

function adm_work_time(bool $post)
{
    if ($post) {
        $t = fn($v) => preg_match('/^\d{1,2}:\d{2}$/', (string) $v) ? str_pad((string) $v, 5, '0', STR_PAD_LEFT) : '';
        md_set('cfg', 'work_time', '', '', json_encode(['in' => $t($_POST['in'] ?? '') ?: '08:30', 'out' => $t($_POST['out'] ?? '') ?: '16:30']));
        admin_done('work_time');
    }
    $w = work_time();
    return admin_form('กำหนดเวลาปฏิบัติงาน', a_url('work_time'), [['เวลาเข้า (หลังเวลานี้นับเป็นมาสาย)', inp('in', $w['in'], 80), true], ['เวลาออก (ก่อนเวลานี้นับเป็นออกก่อนเวลา)', inp('out', $w['out'], 80), true]], 'index.php');
}

function adm_work_status(bool $post)
{
    return cat_md('work_status', 'work_status', 'สถานะการปฏิบัติงาน', ['name' => f_text('สถานะ', true), 'count' => f_sel('นับเป็นการมาปฏิบัติงาน', ['ใช่' => 'ใช่', 'ไม่' => 'ไม่'])], ['name' => 'สถานะ', 'count' => 'นับว่ามา'], $post,
        seed_rows(['name', 'count'], [['มาปฏิบัติงาน', 'ใช่'], ['มาสาย', 'ใช่'], ['ไปราชการ', 'ใช่'], ['ลาป่วย', 'ไม่'], ['ลากิจ', 'ไม่'], ['ไม่มาปฏิบัติงาน', 'ไม่']]));
}

/** การลงเวลาของวันที่กำหนด: [uid => [in, out, note]] */
function wtime_of(string $date): array
{
    $o = [];
    md_ensure();
    foreach (q('SELECT `r1`,`value` FROM {mis_data} WHERE `kind`=\'wtime\' AND `r2`=?', [$date]) as $r) {
        $o[(int) $r['r1']] = json_decode((string) $r['value'], true) ?: [];
    }
    return $o;
}

function adm_checkin(bool $post)
{
    $date = be_date_to_iso((string) ($_GET['date'] ?? ''), date('Y-m-d'));
    $clean = fn($v) => preg_match('/^\d{1,2}:\d{2}$/', trim((string) $v)) ? str_pad(trim((string) $v), 5, '0', STR_PAD_LEFT) : '';
    if (isset($_GET['stamp'])) {
        $uid = (int) me()['id'];
        $cur = wtime_of(date('Y-m-d'))[$uid] ?? [];
        $cur[empty($cur['in']) ? 'in' : 'out'] = date('H:i');
        md_set('wtime', $uid, date('Y-m-d'), '', json_encode($cur));
        admin_done('checkin');
    }
    if ($post) {
        foreach ((array) ($_POST['w'] ?? []) as $uid => $v) {
            $d = ['in' => $clean($v['in'] ?? ''), 'out' => $clean($v['out'] ?? ''), 'note' => mb_substr(trim((string) ($v['note'] ?? '')), 0, 100)];
            md_set('wtime', (int) $uid, $date, '', $d['in'] || $d['out'] || $d['note'] ? json_encode($d, JSON_UNESCAPED_UNICODE) : null);
        }
        admin_done('checkin', '&date='.iso_to_be($date));
    }
    $w = work_time();
    $data = wtime_of($date);
    $rows = '';
    foreach (personnel_rows() as $i => $p) {
        $d = $data[(int) $p['id']] ?? [];
        $late = !empty($d['in']) && $d['in'] > $w['in'];
        $rows .= zrow([$i + 1, name_nbsp($p['name']), h(cat('position', $p['position'])),
            [inp('w['.(int) $p['id'].'][in]', $d['in'] ?? '', 60, 'text', 'placeholder="--:--"').($late ? ' <span style="color:#E74C3C">สาย</span>' : ''), ' align="center"'],
            [inp('w['.(int) $p['id'].'][out]', $d['out'] ?? '', 60, 'text', 'placeholder="--:--"'), ' align="center"'], inp('w['.(int) $p['id'].'][note]', $d['note'] ?? '', 200)], $i);
    }
    $f = '<form method="get" style="width:983px;margin:0 auto 10px;text-align:left"><input type="hidden" name="module" value="admin"/><input type="hidden" name="option" value="checkin"/><span class="search">วันที่ '.d_in('date', $date)
        .' <input type="submit" value="แสดง" class="general radius" style="height:25px"/></span><span style="float:right">'.btn('ลงเวลาของฉันตอนนี้ ('.date('H:i').')', a_url('checkin', '&stamp=1'), 'add').'</span><div style="clear:both"></div></form>';
    $save = '<a href="javascript:void(0)" onclick="document.forms.wform.submit()" id="pinkgreenbtns"><img src="../icon_tools/vec_add_white.png" height="16"/> บันทึกเวลา</a>';
    return $f.'<form id="wform" method="post" action="'.h(a_url('checkin', '&date='.iso_to_be($date))).'">'.list_table('บัญชีลงเวลามาปฏิบัติงาน '.th_date($date, ' ').' (เข้า '.$w['in'].' / ออก '.$w['out'].')',
        ['#', 'ชื่อ-นามสกุล', 'ตำแหน่ง', ['เวลามา', ' style="text-align:center"'], ['เวลากลับ', ' style="text-align:center"'], 'หมายเหตุ'], $rows, '', $save).'</form>';
}

function adm_official(bool $post)
{
    return md_page('official', 'official', 'การไปราชการ', ['person' => f_sel('บุคลากร', 'personnel_options'), 'book' => f_text('เลขที่หนังสือ/คำสั่ง'), 'topic' => f_text('เรื่อง', true),
        'from' => f_date('วันที่เริ่ม'), 'to' => f_date('วันที่สิ้นสุด'), 'place' => f_text('สถานที่', true), 'purpose' => f_area('จุดประสงค์'), 'file' => f_file()],
        ['person' => 'บุคลากร', 'book' => 'เลขที่', 'topic' => 'เรื่อง', 'from' => 'เริ่ม', 'to' => 'สิ้นสุด', 'place' => 'สถานที่'], $post, yt());
}

function adm_p_out(bool $post)
{
    return md_page('p_out', 'p_out', 'การขออนุญาตออกนอกโรงเรียน', ['person' => f_sel('บุคลากร', 'personnel_options'), 'date' => f_date(), 'out' => f_text('เวลาออก', true), 'back' => f_text('เวลากลับ'),
        'reason' => f_area('เหตุผล/สถานที่', true), 'approver' => f_sel('ผู้อนุญาต', 'personnel_options', false)], ['person' => 'บุคลากร', 'date' => 'วันที่', 'out' => 'ออก', 'back' => 'กลับ', 'reason' => 'เหตุผล'], $post, yt());
}

/** ช่วงวันที่จากฟอร์ม (ค่าเริ่มต้น = เดือนนี้) */
function date_range(): array
{
    $from = be_date_to_iso((string) ($_GET['from'] ?? ''), date('Y-m-01'));
    $to = be_date_to_iso((string) ($_GET['to'] ?? ''), date('Y-m-t'));
    return $from <= $to ? [$from, $to] : [$to, $from];
}

function range_form(string $option, string $from, string $to, string $extra = ''): string
{
    return '<form method="get" style="width:983px;margin:0 auto 10px;text-align:left"><input type="hidden" name="module" value="admin"/><input type="hidden" name="option" value="'.$option.'"/><span class="search">ตั้งแต่วันที่ '.d_in('from', $from)
        .' ถึงวันที่ '.d_in('to', $to).' '.$extra.' <input type="submit" value="ค้นหา" class="general radius" style="height:25px"/></span><div style="clear:both"></div></form>';
}

/** รายการในช่วงวันที่ (ข้อมูลทุกปีการศึกษา) */
function range_rows(string $kind, string $from, string $to, string $f1 = 'date', string $f2 = ''): array
{
    $out = [];
    md_ensure();
    foreach (q('SELECT * FROM {mis_data} WHERE `kind`=?', [$kind]) as $r) {
        $d = json_of($r);
        $a = (string) ($d[$f1] ?? '');
        $b = $f2 !== '' ? (string) ($d[$f2] ?? $a) : $a;
        if ($a !== '' && $a <= $to && $b >= $from) {
            $out[] = $d;
        }
    }
    usort($out, fn($x, $y) => strcmp((string) ($x[$f1] ?? ''), (string) ($y[$f1] ?? '')));
    return $out;
}

function adm_rep_checkin(bool $post)
{
    [$from, $to] = date_range();
    $w = work_time();
    $people = personnel_rows();
    $stat = [];
    for ($t = strtotime($from); $t <= strtotime($to); $t += 86400) {
        foreach (wtime_of(date('Y-m-d', $t)) as $uid => $d) {
            if (!empty($d['in'])) {
                $stat[$uid]['days'] = ($stat[$uid]['days'] ?? 0) + 1;
                if ($d['in'] > $w['in']) {
                    $stat[$uid]['late'] = ($stat[$uid]['late'] ?? 0) + 1;
                }
            }
            if (!empty($d['out']) && $d['out'] < $w['out']) {
                $stat[$uid]['early'] = ($stat[$uid]['early'] ?? 0) + 1;
            }
        }
    }
    $rows = '';
    foreach ($people as $i => $p) {
        $s = $stat[(int) $p['id']] ?? [];
        $rows .= zrow([$i + 1, name_nbsp($p['name']), h(cat('position', $p['position'])), [(int) ($s['days'] ?? 0), ' align="center"'], [(int) ($s['late'] ?? 0), ' align="center" style="color:#E74C3C"'],
            [(int) ($s['early'] ?? 0), ' align="center"'], ['<a href="'.a_url('rep_person', '&person='.(int) $p['id'].'&from='.iso_to_be($from).'&to='.iso_to_be($to)).'">รายละเอียด</a>', ' align="center"']], $i);
    }
    return range_form('rep_checkin', $from, $to).list_table('รายงานการลงเวลาปฏิบัติราชการ '.th_date($from, ' ').' - '.th_date($to, ' '), ['#', 'ชื่อ-นามสกุล', 'ตำแหน่ง', 'วันที่มา', 'มาสาย', 'กลับก่อนเวลา', ''], $rows, '', print_btn());
}

function person_range_table(string $kind, array $cols, string $title, string $f1 = 'date', string $f2 = '', int $person = 0): string
{
    [$from, $to] = date_range();
    $po = personnel_options();
    $rows = '';
    $i = 0;
    foreach (range_rows($kind, $from, $to, $f1, $f2) as $d) {
        if ($person && (int) ($d['person'] ?? 0) !== $person) {
            continue;
        }
        $cells = [++$i];
        foreach ($cols as $k => $l) {
            $v = (string) ($d[$k] ?? '');
            $cells[] = $k === 'person' || $k === 'approver' ? h($po[$v] ?? '') : (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? th_date($v, ' ') : ($k === 'type' ? h(LEAVE_TYPES[$v] ?? $v) : h($v)));
        }
        $rows .= zrow($cells, $i);
    }
    return list_table($title.' '.th_date($from, ' ').' - '.th_date($to, ' '), array_merge(['#'], array_values($cols)), $rows, $i.' รายการ', $person ? '' : print_btn());
}

function adm_rep_official(bool $post)
{
    [$from, $to] = date_range();
    return range_form('rep_official', $from, $to).person_range_table('official', ['person' => 'บุคลากร', 'book' => 'เลขที่หนังสือ', 'topic' => 'เรื่อง', 'from' => 'เริ่ม', 'to' => 'สิ้นสุด', 'place' => 'สถานที่'], 'รายงานการไปราชการ', 'from', 'to');
}

function adm_rep_out(bool $post)
{
    [$from, $to] = date_range();
    return range_form('rep_out', $from, $to).person_range_table('p_out', ['person' => 'บุคลากร', 'date' => 'วันที่', 'out' => 'ออก', 'back' => 'กลับ', 'reason' => 'เหตุผล', 'approver' => 'ผู้อนุญาต'], 'รายงานการขออนุญาตออกนอกโรงเรียน');
}

function adm_rep_summary(bool $post)
{
    $date = be_date_to_iso((string) ($_GET['date'] ?? ''), date('Y-m-d'));
    $w = work_time();
    $people = personnel_rows();
    $wt = wtime_of($date);
    $off = array_column(range_rows('official', $date, $date, 'from', 'to'), 'person');
    $lv = [];
    foreach (range_rows('leave', $date, $date, 'from', 'to') as $d) {
        $lv[(int) $d['person']] = $d['type'] ?? '';
    }
    $out = array_column(range_rows('p_out', $date, $date), 'person');
    $c = ['total' => count($people), 'come' => 0, 'late' => 0, 'official' => 0, 'sick' => 0, 'personal' => 0, 'other' => 0, 'absent' => 0, 'out' => 0, 'early' => 0];
    $ptype = [];
    foreach (md_rows('pstatus') as $r) {
        $d = json_of($r);
        $ptype[(int) ($d['person'] ?? 0)] = md_options('ptype', 'name')[$d['ptype'] ?? ''] ?? '';
    }
    $byType = [];
    foreach ($people as $p) {
        $id = (int) $p['id'];
        $t = $ptype[$id] ?: 'ไม่ระบุประเภท';
        $byType[$t]['n'] = ($byType[$t]['n'] ?? 0) + 1;
        if (!empty($wt[$id]['in'])) {
            $k = $wt[$id]['in'] > $w['in'] ? 'late' : 'come';
        } elseif (in_array((string) $id, array_map('strval', $off), true)) {
            $k = 'official';
        } elseif (isset($lv[$id])) {
            $k = in_array($lv[$id], ['sick', 'personal'], true) ? $lv[$id] : 'other';
        } else {
            $k = 'absent';
        }
        $c[$k]++;
        $byType[$t][$k] = ($byType[$t][$k] ?? 0) + 1;
        if (!empty($wt[$id]['out']) && $wt[$id]['out'] < $w['out']) {
            $c['early']++;
        }
        if (in_array((string) $id, array_map('strval', $out), true)) {
            $c['out']++;
        }
    }
    $labels = ['come' => 'มาปฏิบัติงาน', 'late' => 'มาสาย', 'official' => 'ไปราชการ', 'sick' => 'ลาป่วย', 'personal' => 'ลากิจ', 'other' => 'ลาอื่น ๆ', 'absent' => 'ไม่มาปฏิบัติงาน (ไม่ได้ลงเวลา)', 'out' => 'ขออนุญาตออกนอกโรงเรียน', 'early' => 'กลับก่อนเวลา'];
    $rows = zrow([1, '<b>บุคลากรทั้งหมด</b>', ['<b>'.$c['total'].'</b>', ' align="center"']], 0);
    $i = 1;
    foreach ($labels as $k => $l) {
        $rows .= zrow([++$i, h($l), [$c[$k], ' align="center"']], $i);
    }
    $rows2 = '';
    $j = 0;
    foreach ($byType as $t => $x) {
        $cells = [h($t), [$x['n'], ' align="center"']];
        foreach (['come', 'late', 'official', 'sick', 'personal', 'absent'] as $k) {
            $cells[] = [(int) ($x[$k] ?? 0), ' align="center"'];
        }
        $rows2 .= zrow($cells, $j++);
    }
    $f = '<form method="get" style="width:983px;margin:0 auto 10px;text-align:left"><input type="hidden" name="module" value="admin"/><input type="hidden" name="option" value="rep_summary"/><span class="search">วันที่ '.d_in('date', $date)
        .' <input type="submit" value="แสดง" class="general radius" style="height:25px"/></span><div style="clear:both"></div></form>';
    return $f.list_table('สรุปบัญชีลงเวลาประจำวันที่ '.th_date($date, ' '), ['#', 'รายการ', ['จำนวน (คน)', ' style="text-align:center"']], $rows, '', print_btn())
        .'<div style="height:10px"></div>'.list_table('สรุปจำนวนบุคลากรจำแนกตามสถานะการลงเวลา', ['ประเภทบุคลากร', 'จำนวน', 'ตรงเวลา', 'มาสาย', 'ไปราชการ', 'ลาป่วย', 'ลากิจ', 'ไม่มาปฏิบัติงาน'], $rows2);
}

function adm_rep_person(bool $post)
{
    $pid = (int) ($_GET['person'] ?? 0);
    [$from, $to] = date_range();
    $f = range_form('rep_person', $from, $to, 'บุคลากร '.sel('person', ['' => '- เลือก -'] + personnel_options(), $pid));
    if (!$pid) {
        return $f;
    }
    $w = work_time();
    $rows = '';
    $i = 0;
    for ($t = strtotime($from); $t <= strtotime($to); $t += 86400) {
        $d = wtime_of(date('Y-m-d', $t))[$pid] ?? null;
        if ($d) {
            $rows .= zrow([++$i, th_date(date('Y-m-d', $t), ' '), [h($d['in'] ?? '-').(!empty($d['in']) && $d['in'] > $w['in'] ? ' <span style="color:#E74C3C">สาย</span>' : ''), ' align="center"'], [h($d['out'] ?? '-'), ' align="center"'], h($d['note'] ?? '')], $i);
        }
    }
    return $f.list_table('การลงเวลา: '.h(personnel_options()[$pid] ?? ''), ['#', 'วันที่', 'มา', 'กลับ', 'หมายเหตุ'], $rows, $i.' วัน', print_btn())
        .'<div style="height:8px"></div>'.person_range_table('official', ['topic' => 'เรื่อง', 'from' => 'เริ่ม', 'to' => 'สิ้นสุด', 'place' => 'สถานที่'], 'การไปราชการ', 'from', 'to', $pid)
        .'<div style="height:8px"></div>'.person_range_table('leave', ['type' => 'ประเภท', 'from' => 'ตั้งแต่', 'to' => 'ถึง', 'days' => 'วัน'], 'การลา', 'from', 'to', $pid)
        .'<div style="height:8px"></div>'.person_range_table('p_out', ['date' => 'วันที่', 'out' => 'ออก', 'back' => 'กลับ', 'reason' => 'เหตุผล'], 'การขออนุญาตออกนอกโรงเรียน', 'date', '', $pid);
}

/* ================================================================ โมดูล 5: บริหารงานทั่วไป */

function adm_std_early(bool $post)
{
    return cat_md('std_early', 'std_early', 'มาตรฐานการศึกษา (ปฐมวัย)', ['no' => f_num('มาตรฐานที่'), 'name' => f_text('มาตรฐาน', true), 'ind' => f_area('ประเด็นพิจารณา/ตัวบ่งชี้')], ['no' => 'ที่', 'name' => 'มาตรฐาน', 'ind' => 'ประเด็นพิจารณา'], $post,
        seed_rows(['no', 'name', 'ind'], [[1, 'คุณภาพของเด็ก', ''], [2, 'กระบวนการบริหารและการจัดการ', ''], [3, 'การจัดประสบการณ์ที่เน้นเด็กเป็นสำคัญ', '']]));
}

function adm_std_basic(bool $post)
{
    return cat_md('std_basic', 'std_basic', 'มาตรฐานการศึกษา (ขั้นพื้นฐาน)', ['no' => f_num('มาตรฐานที่'), 'name' => f_text('มาตรฐาน', true), 'ind' => f_area('ประเด็นพิจารณา/ตัวบ่งชี้')], ['no' => 'ที่', 'name' => 'มาตรฐาน', 'ind' => 'ประเด็นพิจารณา'], $post,
        seed_rows(['no', 'name', 'ind'], [[1, 'คุณภาพของผู้เรียน', "1.1 ผลสัมฤทธิ์ทางวิชาการของผู้เรียน\n1.2 คุณลักษณะที่พึงประสงค์ของผู้เรียน"], [2, 'กระบวนการบริหารและการจัดการ', ''], [3, 'กระบวนการจัดการเรียนการสอนที่เน้นผู้เรียนเป็นสำคัญ', '']]));
}

const QA_LEVELS = [['ยอดเยี่ยม', 5], ['ดีเลิศ', 4], ['ดี', 3], ['ปานกลาง', 2], ['กำลังพัฒนา', 1]];

function adm_crit_early(bool $post)
{
    return cat_md('crit_early', 'crit_early', 'เกณฑ์การให้คะแนนคุณภาพ (ปฐมวัย)', ['level' => f_text('ระดับคุณภาพ', true), 'score' => f_num('ระดับ'), 'min' => f_text('ช่วงคะแนน/ร้อยละ'), 'meaning' => f_area('คำอธิบาย')],
        ['level' => 'ระดับคุณภาพ', 'score' => 'ระดับ', 'min' => 'ช่วงคะแนน', 'meaning' => 'คำอธิบาย'], $post, array_map(fn($x) => ['level' => $x[0], 'score' => $x[1], 'min' => '', 'meaning' => ''], QA_LEVELS));
}

function adm_crit_basic(bool $post)
{
    return cat_md('crit_basic', 'crit_basic', 'เกณฑ์การให้คะแนนคุณภาพ (ขั้นพื้นฐาน)', ['level' => f_text('ระดับคุณภาพ', true), 'score' => f_num('ระดับ'), 'min' => f_text('ช่วงคะแนน/ร้อยละ'), 'meaning' => f_area('คำอธิบาย')],
        ['level' => 'ระดับคุณภาพ', 'score' => 'ระดับ', 'min' => 'ช่วงคะแนน', 'meaning' => 'คำอธิบาย'], $post, array_map(fn($x) => ['level' => $x[0], 'score' => $x[1], 'min' => '', 'meaning' => ''], QA_LEVELS));
}

function adm_sar_cover(bool $post)
{
    return md_page('sar_cover', 'sar_cover', 'ภาพหน้าปกรายงาน SAR', ['year' => f_text('ปีการศึกษา', true), 'title' => f_text('ชื่อรายงาน', true), 'file' => f_file('ภาพหน้าปก (jpg/png)')], ['year' => 'ปีการศึกษา', 'title' => 'ชื่อรายงาน', 'file' => 'ภาพหน้าปก'], $post);
}

function qa_page(string $kind, string $label, array $sides, bool $post)
{
    return md_page($kind, $kind, $label, ['side' => f_sel('ด้าน/มาตรฐาน', $sides), 'ind' => f_text('ตัวบ่งชี้/ประเด็นพิจารณา', true),
        'level' => f_sel('ระดับคุณภาพ', array_combine(array_column(QA_LEVELS, 1), array_map(fn($x) => $x[1].' - '.$x[0], QA_LEVELS))), 'evidence' => f_area('ร่องรอย/หลักฐาน'), 'by' => f_sel('ผู้ประเมิน', 'personnel_options', false)],
        ['side' => 'ด้าน', 'ind' => 'ตัวบ่งชี้', 'level' => 'ระดับ', 'by' => 'ผู้ประเมิน'], $post, yt());
}

function std_options(): array
{
    return md_options('std_basic', 'name') ?: ['1' => 'คุณภาพของผู้เรียน', '2' => 'กระบวนการบริหารและการจัดการ', '3' => 'กระบวนการจัดการเรียนการสอนที่เน้นผู้เรียนเป็นสำคัญ'];
}

function adm_qa_student(bool $post) { return qa_page('qa_student', 'การประเมินพฤติกรรมรายตัวบ่งชี้ (ผู้เรียน)', std_options(), $post); }
function adm_qa_other(bool $post) { return qa_page('qa_other', 'การประเมินพฤติกรรมรายตัวบ่งชี้ (ด้านที่ 2-5)', ['2' => 'ด้านที่ 2 การบริหารจัดการ', '3' => 'ด้านที่ 3 การจัดการเรียนการสอน', '4' => 'ด้านที่ 4 การประกันคุณภาพภายใน', '5' => 'ด้านที่ 5 มาตรการส่งเสริม'] + std_options(), $post); }

function qa_summary(string $kind, array $sides): string
{
    $agg = [];
    foreach (md_rows($kind, null, yt()) as $r) {
        $d = json_of($r);
        $agg[$d['side'] ?? ''][] = (int) ($d['level'] ?? 0);
    }
    $rows = '';
    $i = 0;
    foreach ($agg as $side => $ls) {
        $avg = array_sum($ls) / max(1, count($ls));
        $lv = '-';
        foreach (QA_LEVELS as [$n, $s]) {
            if ($avg >= $s - 0.5) {
                $lv = $n;
                break;
            }
        }
        $rows .= zrow([++$i, h($sides[$side] ?? $side), [count($ls), ' align="center"'], [number_format($avg, 2), ' align="center"'], ['<b>'.h($lv).'</b>', ' align="center"']], $i);
    }
    return $rows;
}

function adm_rep_qa(bool $post)
{
    $s = std_options();
    $o = ['2' => 'ด้านที่ 2 การบริหารจัดการ', '3' => 'ด้านที่ 3 การจัดการเรียนการสอน', '4' => 'ด้านที่ 4 การประกันคุณภาพภายใน', '5' => 'ด้านที่ 5 มาตรการส่งเสริม'] + $s;
    $ths = ['#', 'มาตรฐาน/ด้าน', 'จำนวนตัวบ่งชี้', 'ค่าเฉลี่ย', 'ระดับคุณภาพ'];
    return list_table('รายงานแบบประเมินพฤติกรรม (ผู้เรียน) ปีการศึกษา '.yt(), $ths, qa_summary('qa_student', $s), '', print_btn())
        .'<div style="height:10px"></div>'.list_table('รายงานแบบประเมินพฤติกรรม (ด้านที่ 2-5)', $ths, qa_summary('qa_other', $o));
}

function adm_ssr(bool $post)
{
    return md_page('ssr', 'ssr', 'รายงานการประเมินตนเองของครู (SSR)', ['person' => f_sel('ครู', 'personnel_options'), 'title' => f_text('ชื่อรายงาน', true), 'summary' => f_area('สรุปผลการประเมินตนเอง'), 'file' => f_file('ไฟล์รายงาน')],
        ['person' => 'ครู', 'title' => 'ชื่อรายงาน', 'file' => 'ไฟล์'], $post, yt());
}

function adm_sar(bool $post)
{
    if (!isset($_GET['print'])) {
        $rows = '';
        foreach (['บทที่ 1 ข้อมูลพื้นฐานของโรงเรียนและบุคลากร', 'บทที่ 2 ผลสัมฤทธิ์ทางการเรียนของนักเรียน', 'บทที่ 3 ผลการประเมินคุณลักษณะอันพึงประสงค์และการอ่าน คิดวิเคราะห์และเขียน',
            'บทที่ 4 ผลการประเมินสมรรถนะสำคัญของผู้เรียน', 'บทที่ 5 ผลการประเมินคุณภาพตามมาตรฐานการศึกษา'] as $i => $n) {
            $rows .= zrow([$i + 1, h($n)], $i);
        }
        return list_table('รายงานการประเมินตนเอง (SAR) ปีการศึกษา '.yt(), ['#', 'เนื้อหา'], $rows, '', print_btn(a_url('sar', '&print=1')));
    }
    $cover = '';
    foreach (md_rows('sar_cover') as $r) {
        $d = json_of($r);
        if (!empty($d['file'])) {
            $cover = '<div style="text-align:center;page-break-after:always"><img src="/datas/mis/'.h($d['file']).'" style="max-width:100%;max-height:900px"/></div>';
        }
    }
    $people = personnel_rows();
    $students = students_q();
    $byClass = [];
    foreach ($students as $s) {
        $c = cat('class', $s['class']) ?: '-';
        $x = $s['sex'] === 'f' ? 'f' : 'm';
        $byClass[$c][$x] = ($byClass[$c][$x] ?? 0) + 1;
    }
    $t1 = '';
    foreach ($byClass as $c => $x) {
        $t1 .= '<tr><td>'.h($c).'</td><td class="c">'.(int) ($x['m'] ?? 0).'</td><td class="c">'.(int) ($x['f'] ?? 0).'</td><td class="c">'.((int) ($x['m'] ?? 0) + (int) ($x['f'] ?? 0)).'</td></tr>';
    }
    $courses = term_courses();
    $gc = grade_counts(array_map(fn($c) => (int) $c['id'], $courses));
    $t2 = '';
    foreach ($courses as $c) {
        $g = $gc[(int) $c['id']] ?? [];
        $n = array_sum($g);
        $good = (int) ($g['4'] ?? 0) + (int) ($g['3.5'] ?? 0) + (int) ($g['3'] ?? 0);
        $t2 .= '<tr><td>'.h($c['course_code'].' '.$c['course_name']).'</td><td class="c">'.$n.'</td><td class="c">'.$good.'</td><td class="c">'.($n ? number_format($good * 100 / $n, 2) : '-').'</td></tr>';
    }
    $s = std_options();
    $qa = preg_replace(['/<tr class="[a-z]+">/', '/ align="center"/'], ['<tr>', ' class="c"'], qa_summary('qa_student', $s));
    print_page('SAR', $cover.doc_head('รายงานการประเมินตนเอง (Self Assessment Report : SAR)', 'ปีการศึกษา '.yt())
        .'<h2 style="text-align:left">บทที่ 1 ข้อมูลพื้นฐาน</h2><p>'.h(cfg('school_name', '')).' '.h(cfg('address', '')).' โทร '.h(cfg('phone', '')).'</p><p>บุคลากรทั้งหมด '.count($people).' คน นักเรียนทั้งหมด '.count($students).' คน</p>'
        .'<table><tr><th>ระดับชั้น</th><th>ชาย</th><th>หญิง</th><th>รวม</th></tr>'.($t1 ?: '<tr><td colspan="4" class="c">-</td></tr>').'</table>'
        .'<h2 style="text-align:left">บทที่ 2 ผลสัมฤทธิ์ทางการเรียน (ภาคเรียน '.yt().')</h2><table><tr><th>รายวิชา</th><th>จำนวนนักเรียน</th><th>ระดับ 3 ขึ้นไป</th><th>ร้อยละ</th></tr>'.($t2 ?: '<tr><td colspan="4" class="c">-</td></tr>').'</table>'
        .'<h2 style="text-align:left">บทที่ 5 ผลการประเมินคุณภาพตามมาตรฐานการศึกษา</h2><table><tr><th>#</th><th>มาตรฐาน</th><th>ตัวบ่งชี้</th><th>ค่าเฉลี่ย</th><th>ระดับคุณภาพ</th></tr>'.($qa ?: '<tr><td colspan="5" class="c">-</td></tr>').'</table>'
        .signs(['ผู้รับผิดชอบงานประกันคุณภาพ', 'ผู้อำนวยการโรงเรียน']));
    return null;
}
