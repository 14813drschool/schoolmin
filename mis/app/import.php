<?php
/**
 * mis/app/import.php
 * นำเข้าข้อมูลจากไฟล์ CSV (ศูนย์รวม) — แต่ละชุดข้อมูลมีไฟล์ตัวอย่างให้ดาวน์โหลด
 * ไฟล์ที่บันทึกจาก Excel (CSV UTF-8 หรือ CSV ธรรมดา/TIS-620) ใช้ได้ทั้งคู่
 * ข้อมูลที่มีอยู่แล้ว (รหัสเดียวกัน) จะถูกปรับปรุง ไม่สร้างซ้ำ
 */
defined('MIS') or exit;

/** ชุดข้อมูล: key => [ชื่อ, โมดูลที่ต้องมีสิทธิ์, [หัวคอลัมน์ => ตัวอย่าง], คำอธิบาย] */
function import_sets(): array
{
    return [
        'rooms' => ['ระดับชั้นและห้องเรียน', 'main', ['ระดับชั้น' => 'ม.1', 'ห้องเรียน' => '1/1'], 'สร้างระดับชั้น/ห้องที่ยังไม่มี (ตั้งชื่อห้องเป็น เลขชั้น/เลขห้อง)'],
        'students' => ['ทะเบียนนักเรียน', 'main', ['รหัสนักเรียน' => '12345', 'คำนำหน้า' => 'เด็กชาย', 'ชื่อ' => 'สมชาย', 'นามสกุล' => 'ใจดี', 'เพศ (ช/ญ)' => 'ช', 'ระดับชั้น' => 'ม.1',
            'ห้อง' => '1/1', 'เลขที่' => '1', 'เลขประจำตัวประชาชน' => '1234567890123', 'วันเกิด (วว/ดด/ปปปป)' => '01/05/2556', 'ชื่อผู้ปกครอง' => 'นายสมศักดิ์ ใจดี', 'โทรศัพท์ผู้ปกครอง' => '0812345678', 'ที่อยู่' => '1 ม.1 ต.ท่าศาลา',
            'ชื่อเล่น' => 'ชาย', 'วิธีการเข้าเรียน' => 'สอบคัดเลือก', 'ปีที่เข้าเรียน' => '2569'],
            'นักเรียนใหม่ใช้รหัสนักเรียนเป็นชื่อผู้ใช้และรหัสผ่านเริ่มต้น / รหัสที่มีอยู่แล้วจะปรับปรุงข้อมูล และถ้าห้องเปลี่ยนจะถูกย้ายไปห้องใหม่ (ใช้ย้ายห้องหลายคนพร้อมกันได้)'],
        'phones' => ['อัพเดตเบอร์โทรผู้ปกครอง', 'main', ['รหัสนักเรียน' => '12345', 'ผู้ปกครอง: เบอร์มือถือ' => '0812345678', 'ผู้ปกครอง: เบอร์บ้าน' => '', 'บิดา: เบอร์มือถือ' => '', 'บิดา: เบอร์บ้าน' => '',
            'มารดา: เบอร์มือถือ' => '', 'มารดา: เบอร์บ้าน' => ''], 'ไม่จำเป็นต้องใส่ครบทุกช่อง ช่องที่เว้นว่างจะไม่เปลี่ยนข้อมูลเดิม'],
        'personnel' => ['ข้อมูลบุคลากร', 'main', ['ชื่อผู้ใช้' => 'teacher01', 'คำนำหน้า' => 'นาง', 'ชื่อ' => 'สมศรี', 'นามสกุล' => 'รักเรียน', 'เพศ (ช/ญ)' => 'ญ', 'ตำแหน่ง' => 'ครู',
            'กลุ่มสาระ/กลุ่มงาน' => 'คณิตศาสตร์', 'โทรศัพท์' => '0891234567', 'รหัสผ่าน' => 'teacher01'], 'ตำแหน่ง/กลุ่มสาระที่ยังไม่มีจะถูกสร้างให้ / รหัสผ่านเว้นว่าง = ใช้ชื่อผู้ใช้ (บัญชีเดิมไม่เปลี่ยนรหัสผ่านถ้าเว้นว่าง)'],
        'advisors' => ['ครูที่ปรึกษาประจำห้อง', 'main', ['ห้อง' => '1/1', 'ชื่อผู้ใช้ครูที่ปรึกษาคนที่ 1' => 'teacher01', 'ชื่อผู้ใช้ครูที่ปรึกษาคนที่ 2' => ''], 'ห้องละไม่เกิน 2 คน (แทนที่ครูที่ปรึกษาเดิมของห้อง)'],
        'stroom' => ['ประวัติห้องเรียนย้อนหลัง', 'main', ['รหัสนักเรียน' => '12345', 'ปีการศึกษา' => '2566', 'ห้อง' => '1/3', 'เลขที่' => '5'],
            'ห้องที่นักเรียนเคยอยู่ในปีการศึกษาที่ผ่านมา (ใช้ใน ปพ.6 และรายชื่อนักเรียนประจำชั้นย้อนหลัง) ไฟล์เดียวใส่ได้หลายปี / ปีการศึกษาปัจจุบันใช้ข้อมูลทะเบียนนักเรียน (ไม่ต้องใส่)'],
        'advhist' => ['ครูที่ปรึกษาย้อนหลัง', 'main', ['ปีการศึกษา' => '2566', 'ห้อง' => 'ม.1/3', 'ครูที่ปรึกษาคนที่ 1 (ชื่อผู้ใช้ หรือ ชื่อ-นามสกุล)' => 'ครูสมศรี รักเรียน', 'ครูที่ปรึกษาคนที่ 2' => ''],
            'ครูที่ปรึกษาของห้องในปีการศึกษาที่ผ่านมา (ไม่เปลี่ยนครูที่ปรึกษาปัจจุบัน) ใส่ชื่อ-นามสกุลได้ ไม่ต้องตรงคำนำหน้า'],
        'sstatus' => ['สถานะนักเรียน (เข้าใหม่/จบ/ย้าย/ลาออก)', 'main', ['รหัสนักเรียน' => '12345', 'สถานภาพ' => 'จบการศึกษาภาคบังคับ', 'วันที่' => '31/03/2570', 'หมายเหตุ' => ''],
            'สถานภาพ: '.implode(', ', STUDENT_STATUS).' (สถานภาพอื่นที่ไม่ใช่ "ศึกษาอยู่" จะพ้นจากทะเบียนปัจจุบัน)'],
        'courses' => ['รายวิชา (ได้ทุกปีการศึกษา)', 'academic', ['ปีการศึกษา' => '2568', 'ภาคเรียน' => '1', 'รหัสวิชา' => 'ค21101', 'ชื่อวิชา' => 'คณิตศาสตร์ 1', 'ระดับชั้น' => 'ม.1', 'หน่วยกิต' => '1.5', 'คาบ/สัปดาห์' => '3',
            'ประเภท (พื้นฐาน/เพิ่มเติม/กิจกรรม)' => 'พื้นฐาน', 'ชื่อผู้ใช้ครูผู้สอน' => 'teacher01'], 'ใส่ปีการศึกษา/ภาคเรียนของแต่ละแถว ไฟล์เดียวมีหลายปีได้ (เว้นว่าง = ภาคเรียนปัจจุบัน '.yt().') / ปี+ภาค+รหัสวิชา+ชั้นเดิม = ปรับปรุง'],
        'enroll' => ['ลงทะเบียนเรียน (ได้ทุกปีการศึกษา)', 'academic', ['ปีการศึกษา' => '2568', 'ภาคเรียน' => '1', 'รหัสวิชา' => 'ค21101', 'ห้อง หรือ รหัสนักเรียน' => '1/1'],
            'ใส่ห้อง = ลงทะเบียนทั้งห้อง (ตามรายชื่อห้องปัจจุบัน) / ใส่รหัสนักเรียน = รายคน / ปีการศึกษาเว้นว่าง = ภาคเรียนปัจจุบัน'],
        'scores' => ['ผลการเรียน (ได้ทุกปีการศึกษา)', 'academic', ['ปีการศึกษา' => '2568', 'ภาคเรียน' => '1', 'รหัสวิชา' => 'ค21101', 'รหัสนักเรียน' => '12345', 'คะแนนระหว่างภาค' => '60', 'คะแนนปลายภาค' => '25',
            'ผลการเรียน (เว้นว่างให้คำนวณ)' => '', 'ชื่อวิชา' => 'คณิตศาสตร์ 1', 'หน่วยกิต' => '1.5', 'ระดับชั้นของรายวิชา' => 'ม.1', 'ประเภท (พื้นฐาน/เพิ่มเติม/กิจกรรม)' => 'พื้นฐาน'],
            'ไฟล์เดียวใส่ผลการเรียนหลายปี/หลายภาคได้ ถ้ายังไม่มีรายวิชาในภาคนั้นและกรอกชื่อวิชาไว้ ระบบจะสร้างรายวิชาให้ / ผลการเรียนใส่ได้ทั้งตัวเลขและ ร มส มผ ผ'],
        'clubs' => ['ชุมนุม', 'academic', ['ชื่อชุมนุม' => 'ชุมนุมดนตรีไทย', 'ชื่อผู้ใช้ครูที่ปรึกษา' => 'teacher01', 'สถานที่' => 'ห้องดนตรี', 'จำนวนรับ' => '30', 'ระดับชั้น (ว่าง=ทุกชั้น)' => ''], 'ภาคเรียน '.yt()],
        'behavior' => ['พฤติกรรมบกพร่อง (ตัดคะแนน)', 'affairs', ['รหัสนักเรียน' => '12345', 'วันที่' => '08/11/2569', 'พฤติกรรม/ความผิด' => 'มาสาย', 'คะแนนที่ตัด' => '5', 'รายละเอียด' => ''],
            'พฤติกรรมที่ยังไม่มีในเกณฑ์จะถูกเพิ่มในเกณฑ์ตัดคะแนนให้'],
        'scholar' => ['นักเรียนได้รับทุนการศึกษา', 'affairs', ['รหัสนักเรียน' => '12345', 'ชื่อทุน' => 'ทุนเรียนดี', 'จำนวนเงิน' => '2000', 'วันที่ได้รับ' => '08/11/2569'], 'ทุนที่ยังไม่มีจะถูกเพิ่มให้ (ปีการศึกษา '.yt().')'],
        'leave' => ['การลาของบุคลากร', 'personnel', ['ชื่อผู้ใช้' => 'teacher01', 'ประเภทการลา' => 'ลาป่วย', 'ตั้งแต่วันที่' => '08/11/2569', 'ถึงวันที่' => '09/11/2569', 'จำนวนวัน' => '2', 'หมายเหตุ' => ''],
            'ประเภท: '.implode(', ', LEAVE_TYPES)],
        'holidays' => ['ปฏิทินวันหยุด', 'personnel', ['วันที่' => '05/12/2569', 'รายละเอียด' => 'วันพ่อแห่งชาติ'], 'บันทึกเป็นวันหยุดราชการ'],
        'fees' => ['รายการค่าบำรุงการศึกษา', 'finance', ['รายการ' => 'ค่าคอมพิวเตอร์', 'ระดับชั้น (ว่าง=ทุกชั้น)' => 'ม.1', 'จำนวนเงิน' => '500', 'ใบเสร็จ (โรงเรียน/สพฐ.)' => 'โรงเรียน'], 'ภาคเรียน '.yt()],
        'payments' => ['การชำระเงินค่าบำรุงการศึกษา', 'finance', ['รหัสนักเรียน' => '12345', 'จำนวนเงิน' => '3500', 'วันที่ชำระ' => '08/11/2569', 'วิธีชำระ (เงินสด/ธนาคาร/โอน)' => 'ธนาคาร', 'เล่มที่' => '', 'เลขที่' => '', 'เลขอ้างอิง' => 'TX0001'],
            'บันทึกเป็นการชำระของภาคเรียน '.yt().' (ข้อมูลเดิมของนักเรียนคนเดิมจะถูกแทนที่)'],
    ];
}

/* ---------------------------------------------------------------- ตัวช่วยค้นหา */

function imp_date(string $v): string
{
    $v = trim($v);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        $y = (int) substr($v, 0, 4);
        return $y > 2400 ? ($y - 543).substr($v, 4) : $v;
    }
    return preg_match('#^\d{1,2}/\d{1,2}/\d{4}$#', $v) ? be_date_to_iso($v) : '';
}

function imp_class(string $v): int
{
    $v = trim($v);
    if ($v === '') {
        return 0;
    }
    $classes = cats('class');
    foreach ($classes as $id => $n) {
        if ($v === $n || $v === class_short($id) || str_replace(' ', '', $v) === str_replace(' ', '', $n)) {
            return (int) $id;
        }
    }
    return 0;
}

/** ห้องจากชื่อ "1/1" หรือ "ม.1/1" */
function imp_room(string $v): int
{
    $v = trim($v);
    if ($v === '') {
        return 0;
    }
    foreach (cats('room') as $id => $n) {
        if ($v === $n) {
            return (int) $id;
        }
    }
    foreach (room_options() as $id => $n) {
        if ($v === $n) {
            return (int) $id;
        }
    }
    return 0;
}

/** บุคลากรจากชื่อผู้ใช้ หรือชื่อ-นามสกุล (ไม่สนใจคำนำหน้า เช่น ครู นาย นาง นางสาว) */
function imp_person(string $v): int
{
    $v = trim($v);
    if ($v === '') {
        return 0;
    }
    $uid = imp_user($v);
    if ($uid && !q1('SELECT 1 x FROM {student} WHERE `id`=?', [$uid])) {
        return $uid;
    }
    static $map = null;
    $norm = fn($s) => preg_replace('/\s+/u', '', preg_replace('/^(ว่าที่\s*ร(้อย)?\.?\s*ต(รี)?\.?|ครู|นางสาว|นาง|นาย|น\.ส\.|ดร\.|Mr\.|Mrs\.|Ms\.|Miss)\s*/u', '', trim($s)));
    if ($map === null) {
        $map = [];
        foreach (personnel_options() as $id => $n) {
            $map[$norm((string) $n)] ??= (int) $id;
        }
    }
    return $map[$norm($v)] ?? 0;
}

function imp_user(string $username): int
{
    return $username === '' ? 0 : (int) (q1('SELECT `id` FROM {user} WHERE `username`=?', [trim($username)])['id'] ?? 0);
}

function imp_student(string $code): ?array
{
    return trim($code) === '' ? null : q1('SELECT S.*, U.`name` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`student_id`=?', [trim($code)]);
}

/** หา id ในหมวดหมู่ (สร้างใหม่ถ้าไม่มี) */
function imp_cat(string $type, string $name): int
{
    $name = trim($name);
    if ($name === '') {
        return 0;
    }
    $id = array_search($name, cats($type), true);
    if ($id !== false) {
        return (int) $id;
    }
    cat_save($type, null, $name);
    return (int) (q1('SELECT MAX(CAST(`category_id` AS UNSIGNED)) m FROM {category} WHERE `type`=?', [$type])['m'] ?? 0);
}

function imp_md_find(string $kind, string $name, string $scope = ''): ?string
{
    foreach (md_rows($kind, null, $scope) as $r) {
        if (trim((string) (json_decode((string) $r['value'], true)['name'] ?? '')) === trim($name)) {
            return (string) $r['r1'];
        }
    }
    return null;
}

/** หา r1 ของรายการเดิมที่ค่าตรงกับเงื่อนไขทุกช่อง (ไม่พบ = null) */
function imp_md_match(string $kind, string $scope, array $where): ?string
{
    foreach (md_rows($kind, null, $scope) as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        foreach ($where as $k => $v) {
            if ((string) ($d[$k] ?? '') !== (string) $v) {
                continue 2;
            }
        }
        return (string) $r['r1'];
    }
    return null;
}

/** หา r1 ในรายการ mis_data ตามชื่อ (สร้างใหม่ถ้าไม่มี) */
function imp_md(string $kind, string $name, array $extra = [], string $scope = ''): string
{
    foreach (md_rows($kind, null, $scope) as $r) {
        if (trim((string) (json_decode((string) $r['value'], true)['name'] ?? '')) === trim($name)) {
            return (string) $r['r1'];
        }
    }
    $id = (string) round(microtime(true) * 1000).random_int(10, 99);
    md_set($kind, $id, $scope, '', json_encode(['name' => trim($name)] + $extra, JSON_UNESCAPED_UNICODE));
    return $id;
}

function imp_sex(string $v): ?string
{
    return in_array(trim($v), ['ช', 'ชาย', 'm', 'M', 'ด.ช.', 'นาย'], true) ? 'm' : (in_array(trim($v), ['ญ', 'หญิง', 'f', 'F'], true) ? 'f' : null);
}

/** ปีการศึกษา/ภาคเรียนจากไฟล์ (เว้นว่าง = ภาคเรียนปัจจุบัน) */
function imp_term(string $y, string $t): array
{
    [$cy, $ct] = term();
    $y = trim($y);
    $t = trim($t);
    if ($y === '' && $t === '') {
        return [(int) $cy, (int) $ct];
    }
    $yy = (int) $y;
    if ($yy > 0 && $yy < 2400) {
        $yy += 543;
    }
    if ($yy < 2450 || $yy > 2700) {
        throw new \RuntimeException('ปีการศึกษาไม่ถูกต้อง: '.$y);
    }
    $tt = $t === '' ? (int) $ct : (int) $t;
    if ($tt < 1 || $tt > 3) {
        throw new \RuntimeException('ภาคเรียนไม่ถูกต้อง: '.$t);
    }
    return [$yy, $tt];
}

function imp_course(string $code, int $class = 0, ?array $yt = null): ?array
{
    [$y, $t] = $yt ?? term();
    return q1('SELECT * FROM {course} WHERE `course_code`=? AND `year`=? AND `term`=?'.($class ? ' AND `class`='.$class : '').' ORDER BY `id` LIMIT 1', [trim($code), $y, $t]);
}

/* ---------------------------------------------------------------- การนำเข้าแต่ละแถว: คืนค่า 'add' | 'update' หรือ throw ข้อความผิดพลาด */

function imp_row(string $set, array $c): string
{
    $c = array_pad($c, 16, '');
    $need = function (string $v, string $label) {
        if (trim($v) === '') {
            throw new \RuntimeException('ไม่มี'.$label);
        }
    };
    switch ($set) {
        case 'rooms':
            [$cls, $room] = $c;
            $need($room, 'ชื่อห้อง');
            $new = false;
            if ($cls !== '' && !imp_class($cls)) {
                cat_save('class', null, trim($cls));
                $new = true;
            }
            if (!array_search(trim($room), cats('room'), true)) {
                cat_save('room', null, trim($room));
                $new = true;
            }
            return $new ? 'add' : 'update';

        case 'students':
            [$code, $title, $fn, $ln, $sex, $cls, $room, $num, $card, $birth, $parent, $pphone, $addr, $nick, $entry, $eyear] = $c;
            $extra = array_filter(['nickname' => trim($nick), 'entry' => trim($entry) !== '' ? imp_md('entry_type', $entry) : '', 'entry_year' => preg_match('/^\d{4}$/', trim($eyear)) ? trim($eyear) : ''], fn($v) => $v !== '');
            $need($code, 'รหัสนักเรียน');
            $s = imp_student($code);
            $cid = imp_class($cls);
            $rid = imp_room($room);
            if ($room !== '' && !$rid) {
                throw new \RuntimeException('ไม่พบห้อง '.$room.' (นำเข้าระดับชั้นและห้องเรียนก่อน)');
            }
            $card = preg_replace('/\D/', '', $card);
            $u = array_filter(['name' => trim($fn) !== '' ? trim($title.$fn.' '.$ln) : null, 'sex' => imp_sex($sex), 'birthday' => imp_date($birth) ?: null, 'id_card' => strlen($card) === 13 ? $card : null], fn($v) => $v !== null);
            if ($s) {
                save_user((int) $s['id'], $u);
                if ($extra) {
                    stinfo_save((int) $s['id'], $extra);
                }
                if ($rid && $rid !== (int) $s['room']) {
                    keep_room_history($s);
                }
                $set = [];
                $p = [];
                foreach (['class' => $cid ?: null, 'room' => $rid ?: null, 'number' => $num !== '' ? (int) $num : null, 'id_card' => $u['id_card'] ?? null, 'parent' => $parent !== '' ? mb_substr($parent, 0, 100) : null,
                    'parent_phone' => $pphone !== '' ? mb_substr($pphone, 0, 32) : null, 'address' => $addr !== '' ? mb_substr($addr, 0, 100) : null] as $k => $v) {
                    if ($v !== null) {
                        $set[] = "`$k`=?";
                        $p[] = $v;
                    }
                }
                if ($set) {
                    q('UPDATE {student} SET '.implode(',', $set).' WHERE `id`=?', array_merge($p, [(int) $s['id']]));
                }
                return 'update';
            }
            // มีบัญชีผู้ใช้รหัสนี้อยู่แล้ว (แต่ยังไม่มีข้อมูลนักเรียน) = ใช้บัญชีเดิม ไม่สร้างใหม่
            $uid = imp_user($code);
            if ($uid && q1('SELECT 1 x FROM {personnel} WHERE `id`=?', [$uid])) {
                throw new \RuntimeException('ชื่อผู้ใช้ '.$code.' เป็นบัญชีบุคลากร');
            }
            if ($uid) {
                save_user($uid, $u);
            } else {
                $need($fn, 'ชื่อ');
                $uid = save_user(0, $u + ['username' => trim($code), 'status' => 0, 'active' => 1], trim($code));
            }
            q('INSERT INTO {student} (`id`,`student_id`,`class`,`room`,`number`,`id_card`,`department`,`parent`,`parent_phone`,`address`) VALUES (?,?,?,?,?,?,0,?,?,?)',
                [$uid, trim($code), $cid, $rid, $num === '' ? null : (int) $num, $u['id_card'] ?? null, mb_substr($parent, 0, 100), mb_substr($pphone, 0, 32), mb_substr($addr, 0, 100)]);
            stinfo_save($uid, $extra + ['status' => 'ศึกษาอยู่', 'entry_year' => (string) cur_term()[0]]);
            return 'add';

        case 'phones':
            [$code, $gm, $gh, $fm, $fh, $mm, $mh] = $c;
            $s = imp_student($code) ?? throw new \RuntimeException('ไม่พบนักเรียน '.$code);
            if (trim($gm) !== '') {
                q('UPDATE {student} SET `parent_phone`=? WHERE `id`=?', [mb_substr(trim($gm), 0, 32), (int) $s['id']]);
            }
            $d = array_filter(['guardian_home' => trim($gh), 'father_mobile' => trim($fm), 'father_home' => trim($fh), 'mother_mobile' => trim($mm), 'mother_home' => trim($mh)], fn($v) => $v !== '');
            if ($d) {
                stinfo_save((int) $s['id'], $d);
            }
            return 'update';

        case 'personnel':
            [$un, $title, $fn, $ln, $sex, $pos, $dep, $phone, $pw] = $c;
            $need($un, 'ชื่อผู้ใช้');
            $id = imp_user($un);
            if ($id && q1('SELECT 1 x FROM {student} WHERE `id`=?', [$id])) {
                throw new \RuntimeException('ชื่อผู้ใช้ '.$un.' เป็นบัญชีนักเรียน');
            }
            if (!$id) {
                $need($fn, 'ชื่อ');
            }
            $u = array_filter(['username' => trim($un), 'name' => trim($fn) !== '' ? trim($title.$fn.' '.$ln) : null, 'sex' => imp_sex($sex), 'phone' => trim($phone) !== '' ? trim($phone) : null], fn($v) => $v !== null);
            $uid = save_user($id, $id ? $u : $u + ['status' => 0, 'active' => 1, 'permission' => ',can_teacher,'], trim($pw) !== '' ? trim($pw) : ($id ? '' : trim($un)));
            $posId = imp_cat('position', $pos);
            $depId = imp_cat('department', $dep);
            if (q1('SELECT 1 x FROM {personnel} WHERE `id`=?', [$uid])) {
                if ($posId || $depId) {
                    q('UPDATE {personnel} SET `position`=IF(?>0,?,`position`), `department`=IF(?>0,?,`department`) WHERE `id`=?', [$posId, $posId, $depId, $depId, $uid]);
                }
            } else {
                q('INSERT INTO {personnel} (`id`,`position`,`department`,`order`,`class`,`room`) VALUES (?,?,?,0,0,0)', [$uid, $posId, $depId]);
            }
            return $id ? 'update' : 'add';

        case 'advisors':
            [$room, $un, $un2] = $c;
            $rid = imp_room($room) ?: throw new \RuntimeException('ไม่พบห้อง '.$room);
            $ids = [];
            foreach ([$un, $un2] as $u) {
                if (trim($u) === '') {
                    continue;
                }
                $uid = imp_user($u);
                if (!$uid || q1('SELECT 1 x FROM {student} WHERE `id`=?', [$uid])) {
                    throw new \RuntimeException('ไม่พบบุคลากร '.$u);
                }
                $ids[] = $uid;
            }
            set_room_advisors($rid, $ids);
            return 'update';

        case 'stroom':
            [$code, $y, $room, $num] = $c;
            $s = imp_student($code) ?? throw new \RuntimeException('ไม่พบนักเรียน '.$code);
            $y = (int) trim($y);
            if ($y < 2400 || $y >= cur_term()[0]) {
                throw new \RuntimeException('ปีการศึกษาต้องเป็นปีที่ผ่านมา (ก่อน '.cur_term()[0].')');
            }
            $rid = imp_room($room) ?: throw new \RuntimeException('ไม่พบห้อง '.$room);
            $had = md_get('stroom', (int) $s['id'], (string) $y) !== null;
            set_room_history((int) $s['id'], $y, $rid, trim($num) === '' ? null : (int) $num);
            return $had ? 'update' : 'add';

        case 'advhist':
            [$y, $room, $un, $un2] = $c;
            $y = (int) trim($y);
            if ($y < 2400 || $y >= cur_term()[0]) {
                throw new \RuntimeException('ปีการศึกษาต้องเป็นปีที่ผ่านมา (ก่อน '.cur_term()[0].')');
            }
            $rid = imp_room($room) ?: throw new \RuntimeException('ไม่พบห้อง '.$room);
            $ids = [];
            foreach ([$un, $un2] as $u) {
                if (trim($u) === '') {
                    continue;
                }
                $uid = imp_person($u);
                if (!$uid) {
                    throw new \RuntimeException('ไม่พบบุคลากร '.$u);
                }
                $ids[] = $uid;
            }
            md_set('adv_hist', $y, $rid, '', $ids ? json_encode(array_values(array_unique($ids))) : null);
            return 'update';

        case 'sstatus':
            [$code, $st, $date, $note] = $c;
            $s = imp_student($code) ?? throw new \RuntimeException('ไม่พบนักเรียน '.$code);
            set_student_status((int) $s['id'], trim($st), imp_date($date), trim($note));
            return 'update';

        case 'courses':
            [$yr, $tm, $code, $name, $cls, $credit, $period, $type, $un] = $c;
            $need($code, 'รหัสวิชา');
            $need($name, 'ชื่อวิชา');
            [$y, $t] = imp_term($yr, $tm);
            $cid = imp_class($cls);
            $typeId = array_search(trim($type), COURSE_TYPES, true) ?: 1;
            $tid = imp_user($un);
            if ($un !== '' && !$tid) {
                throw new \RuntimeException('ไม่พบครู '.$un);
            }
            $v = [mb_substr(trim($name), 0, 50), (int) $tid, $cid, max(0, (int) $period), min(9.9, max(0, (float) $credit)), (int) $typeId];
            if ($ex = q1('SELECT `id` FROM {course} WHERE `course_code`=? AND `class`=? AND `year`=? AND `term`=?', [trim($code), $cid, $y, $t])) {
                q('UPDATE {course} SET `course_name`=?, `teacher_id`=IF(?>0,?,`teacher_id`), `class`=?, `period`=?, `credit`=?, `type`=? WHERE `id`=?', [$v[0], $v[1], $v[1], $v[2], $v[3], $v[4], $v[5], (int) $ex['id']]);
                return 'update';
            }
            q('INSERT INTO {course} (`course_name`,`course_code`,`teacher_id`,`class`,`period`,`credit`,`type`,`year`,`term`) VALUES (?,?,?,?,?,?,?,?,?)', [$v[0], mb_substr(trim($code), 0, 20), $v[1], $v[2], $v[3], $v[4], $v[5], $y, $t]);
            return 'add';

        case 'enroll':
            [$yr, $tm, $code, $who] = $c;
            $yt = imp_term($yr, $tm);
            $rid = imp_room($who);
            $one = $rid ? null : imp_student($who);
            if (!$rid && !$one) {
                throw new \RuntimeException('ไม่พบห้องหรือนักเรียน '.$who);
            }
            $course = imp_course($code, $rid ? (int) (q1('SELECT `class` FROM {student} WHERE `room`=? LIMIT 1', [$rid])['class'] ?? 0) : (int) $one['class'], $yt) ?? imp_course($code, 0, $yt)
                ?? throw new \RuntimeException('ไม่พบรายวิชา '.$code.' ในภาคเรียน '.$yt[1].'/'.$yt[0]);
            $list = $rid ? q('SELECT S.`id`, S.`number`, S.`room` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`room`=? AND U.`active`=1', [$rid]) : [$one];
            $n = 0;
            foreach ($list as $s) {
                if (!q1('SELECT 1 x FROM {grade} WHERE `course_id`=? AND `student_id`=?', [(int) $course['id'], (int) $s['id']])) {
                    q('INSERT INTO {grade} (`student_id`,`course_id`,`number`,`room`,`type`) VALUES (?,?,?,?,1)', [(int) $s['id'], (int) $course['id'], $s['number'], (int) $s['room']]);
                    $n++;
                }
            }
            return $n ? 'add' : 'update';

        case 'scores':
            [$yr, $tm, $code, $sc, $mid, $fin, $g, $cname, $credit, $ccls, $ctype] = $c;
            $need($code, 'รหัสวิชา');
            $yt = imp_term($yr, $tm);
            $s = imp_student($sc) ?? throw new \RuntimeException('ไม่พบนักเรียน '.$sc);
            $cc = imp_class($ccls);
            $course = imp_course($code, $cc ?: (int) $s['class'], $yt) ?? imp_course($code, 0, $yt);
            if (!$course) {
                if (trim($cname) === '') {
                    throw new \RuntimeException('ไม่พบรายวิชา '.$code.' ในภาคเรียน '.$yt[1].'/'.$yt[0].' (กรอกชื่อวิชาเพื่อให้สร้างรายวิชาอัตโนมัติ)');
                }
                q('INSERT INTO {course} (`course_name`,`course_code`,`teacher_id`,`class`,`period`,`credit`,`type`,`year`,`term`) VALUES (?,?,0,?,0,?,?,?,?)',
                    [mb_substr(trim($cname), 0, 50), mb_substr(trim($code), 0, 20), $cc ?: (int) $s['class'], min(9.9, max(0, (float) $credit)), (int) (array_search(trim($ctype), COURSE_TYPES, true) ?: 1), $yt[0], $yt[1]]);
                $course = imp_course($code, $cc ?: (int) $s['class'], $yt);
            }
            $g = trim($g) !== '' ? trim($g) : ((trim($mid) === '' && trim($fin) === '') ? '' : grade_of((int) $mid + (int) $fin));
            if ($ex = q1('SELECT `id` FROM {grade} WHERE `student_id`=? AND `course_id`=?', [(int) $s['id'], (int) $course['id']])) {
                q('UPDATE {grade} SET `midterm`=?, `final`=?, `grade`=? WHERE `id`=?', [(int) $mid, (int) $fin, $g, (int) $ex['id']]);
                return 'update';
            }
            q('INSERT INTO {grade} (`student_id`,`course_id`,`number`,`room`,`type`,`midterm`,`final`,`grade`) VALUES (?,?,?,?,0,?,?,?)', [(int) $s['id'], (int) $course['id'], (int) $s['number'], (int) $s['room'], (int) $mid, (int) $fin, $g]);
            return 'add';

        case 'clubs':
            [$name, $un, $place, $seats, $cls] = $c;
            $need($name, 'ชื่อชุมนุม');
            $had = imp_md_find('club', $name, yt()) !== null;
            $id = imp_md('club', $name, [], yt());
            $d = json_decode((string) md_get('club', $id, yt()), true) ?: [];
            $d = ['name' => trim($name), 'teacher' => (string) (imp_user($un) ?: ($d['teacher'] ?? '')), 'place' => trim($place), 'seats' => trim($seats), 'class' => (string) imp_class($cls), 'detail' => $d['detail'] ?? ''];
            md_set('club', $id, yt(), '', json_encode($d, JSON_UNESCAPED_UNICODE));
            return $had ? 'update' : 'add';

        case 'behavior':
            [$code, $date, $rule, $pts, $detail] = $c;
            $s = imp_student($code) ?? throw new \RuntimeException('ไม่พบนักเรียน '.$code);
            $need($rule, 'พฤติกรรม');
            $rid = imp_md('sa_rule', $rule, ['points' => trim($pts), 'level' => '']);
            $d = imp_date($date) ?: date('Y-m-d');
            // นักเรียน + วันที่ + พฤติกรรมเดิม = ปรับปรุงรายการเดิม
            $key = imp_md_match('sa_bad', yt(), ['student' => (string) $s['id'], 'date' => $d, 'rule' => $rid]);
            $old = $key !== null ? (json_decode((string) md_get('sa_bad', $key, yt()), true) ?: []) : [];
            md_set('sa_bad', $key ?? (string) round(microtime(true) * 1000).random_int(10, 99), yt(), '', json_encode(['student' => (string) $s['id'], 'date' => $d, 'rule' => $rid,
                'points' => trim($pts), 'punish' => $old['punish'] ?? '', 'detail' => trim($detail) !== '' ? trim($detail) : ($old['detail'] ?? '')] + $old, JSON_UNESCAPED_UNICODE));
            return $key !== null ? 'update' : 'add';

        case 'scholar':
            [$code, $fund, $amount, $date] = $c;
            $s = imp_student($code) ?? throw new \RuntimeException('ไม่พบนักเรียน '.$code);
            $need($fund, 'ชื่อทุน');
            $fid = imp_md('sa_fund', $fund, ['sponsor' => '', 'amount' => trim($amount), 'count' => '', 'condition' => '']);
            $had = md_get('sa_fund_student', 'i'.(int) $s['id'].'_'.$fid, yt()) !== null;
            md_set('sa_fund_student', 'i'.(int) $s['id'].'_'.$fid, yt(), '', json_encode(['student' => (string) $s['id'], 'fund' => $fid, 'date' => imp_date($date) ?: date('Y-m-d'), 'amount' => trim($amount), 'note' => 'นำเข้าจากไฟล์'], JSON_UNESCAPED_UNICODE));
            return $had ? 'update' : 'add';

        case 'leave':
            [$un, $type, $from, $to, $days, $note] = $c;
            $uid = imp_user($un) ?: throw new \RuntimeException('ไม่พบบุคลากร '.$un);
            $tk = array_search(trim($type), LEAVE_TYPES, true) ?: (isset(LEAVE_TYPES[trim($type)]) ? trim($type) : 'other');
            $f = imp_date($from) ?: throw new \RuntimeException('วันที่ไม่ถูกต้อง: '.$from);
            // บุคลากร + ประเภทการลา + วันเริ่มลาเดิม = ปรับปรุงรายการเดิม
            $key = imp_md_match('leave', yt(), ['person' => (string) $uid, 'type' => $tk, 'from' => $f]);
            $old = $key !== null ? (json_decode((string) md_get('leave', $key, yt()), true) ?: []) : [];
            md_set('leave', $key ?? (string) round(microtime(true) * 1000).random_int(10, 99), yt(), '', json_encode(['person' => (string) $uid, 'type' => $tk, 'from' => $f, 'to' => imp_date($to) ?: $f, 'days' => trim($days) ?: '1',
                'note' => trim($note) !== '' ? trim($note) : ($old['note'] ?? '')] + $old, JSON_UNESCAPED_UNICODE));
            return $key !== null ? 'update' : 'add';

        case 'holidays':
            [$date, $name] = $c;
            $d = imp_date($date) ?: throw new \RuntimeException('วันที่ไม่ถูกต้อง: '.$date);
            $had = md_get('workdays', 'd'.$d) !== null;
            md_set('workdays', 'd'.$d, '', '', json_encode(['date' => $d, 'type' => 'หยุด', 'name' => trim($name)], JSON_UNESCAPED_UNICODE));
            return $had ? 'update' : 'add';

        case 'fees':
            [$name, $cls, $amount, $grp] = $c;
            $need($name, 'รายการ');
            $cid = imp_class($cls);
            if (trim($cls) !== '' && !$cid) {
                throw new \RuntimeException('ไม่พบระดับชั้น '.$cls);
            }
            $had = md_get('fee_item', 'f'.md5(trim($name).'|'.$cid), yt()) !== null;
            md_set('fee_item', 'f'.md5(trim($name).'|'.$cid), yt(), '', json_encode(['name' => trim($name), 'class' => (string) $cid, 'amount' => (string) (float) str_replace(',', '', $amount),
                'group' => mb_strpos($grp, 'สพฐ') !== false ? 'obec' : 'school'], JSON_UNESCAPED_UNICODE));
            return $had ? 'update' : 'add';

        case 'payments':
            [$code, $amount, $date, $method, $book, $no, $ref] = $c;
            $s = imp_student($code) ?? throw new \RuntimeException('ไม่พบนักเรียน '.$code);
            $had = md_get('pay', (int) $s['id'], yt()) !== null;
            $m = mb_strpos($method, 'ธนาคาร') !== false ? 'bank' : (mb_strpos($method, 'โอน') !== false ? 'transfer' : 'cash');
            pay_record((int) $s['id'], (float) str_replace(',', '', $amount), imp_date($date) ?: date('Y-m-d'), $m, mb_substr(trim($book), 0, 20), mb_substr(trim($no), 0, 20), mb_substr(trim($ref), 0, 40));
            return $had ? 'update' : 'add';
    }
    throw new \RuntimeException('ไม่รู้จักชุดข้อมูล');
}

/* ---------------------------------------------------------------- หน้า */

function adm_csv_import(bool $post)
{
    $sets = import_sets();
    $mine = user_modules();
    $sets = array_filter($sets, fn($s) => in_array($s[1], $mine, true));
    $key = (string) ($_GET['set'] ?? $_POST['set'] ?? '');

    // ดาวน์โหลดไฟล์ตัวอย่าง (UTF-8 BOM ให้ Excel อ่านภาษาไทยได้)
    if (isset($_GET['tpl']) && isset($sets[$_GET['tpl']])) {
        $cols = $sets[$_GET['tpl']][2];
        $line = fn($a) => implode(',', array_map(fn($v) => '"'.str_replace('"', '""', (string) $v).'"', $a))."\r\n";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="import_'.$_GET['tpl'].'.csv"');
        header('Cache-Control: no-store');
        echo "\xEF\xBB\xBF".$line(array_keys($cols)).$line(array_values($cols));
        return null;
    }

    $result = '';
    if ($post && isset($sets[$key])) {
        $headers = array_keys($sets[$key][2]);
        $rows = csv_rows('csv');
        $add = $upd = 0;
        $errs = [];
        foreach ($rows as $i => $r) {
            // ข้ามแถวหัวตาราง
            if ($i === 0 && trim((string) ($r[0] ?? '')) === $headers[0]) {
                continue;
            }
            if (!array_filter($r, fn($v) => trim((string) $v) !== '')) {
                continue;
            }
            try {
                imp_row($key, array_map(fn($v) => trim((string) $v), $r)) === 'add' ? $add++ : $upd++;
            } catch (\Throwable $e) {
                $errs[] = [$i + 1, implode(', ', array_slice($r, 0, 3)), $e instanceof \RuntimeException ? $e->getMessage() : 'บันทึกไม่ได้'];
            }
        }
        if (!$rows) {
            $result = msg_box('ไม่พบข้อมูลในไฟล์ — กรุณาเลือกไฟล์ .csv', false);
        } else {
            $result = msg_box('นำเข้า "'.h($sets[$key][0]).'" เรียบร้อย: เพิ่มใหม่ '.$add.' รายการ, ปรับปรุง '.$upd.' รายการ'.($errs ? ', <b style="color:#C0392B">ผิดพลาด '.count($errs).' แถว</b>' : ''), !$errs);
            if ($errs) {
                $er = '';
                foreach (array_slice($errs, 0, 200) as $j => [$line, $data, $msg]) {
                    $er .= zrow([$line, h($data), '<span style="color:#C0392B">'.h($msg).'</span>'], $j);
                }
                $result .= list_table('แถวที่นำเข้าไม่ได้', ['แถวที่', 'ข้อมูล', 'สาเหตุ'], $er).'<div style="height:10px"></div>';
            }
        }
    }

    $mods = admin_modules();
    $rows = '';
    $i = 0;
    foreach ($sets as $k => [$label, $mod, $cols, $note]) {
        $rows .= zrow([++$i, '<a id="'.$k.'"></a><b>'.h($label).'</b><br><span class="gray_12">'.h($mods[$mod][3] ?? $mods[$mod][0]).'</span>', '<span style="font-size:11px">'.h(implode(', ', array_keys($cols))).'</span><br><span class="gray_12" style="font-size:11px">'.h($note).'</span>',
            ['<a href="'.a_url('csv_import', '&tpl='.$k).'"><i class="fa fa-download"></i> ไฟล์ตัวอย่าง</a>', ' align="center" style="white-space:nowrap"'],
            ['<form method="post" enctype="multipart/form-data" action="'.h(a_url('csv_import', '&set='.$k)).'" style="margin:0;white-space:nowrap"><input type="hidden" name="set" value="'.$k.'"/>'
                .'<input type="file" name="csv" accept=".csv,.txt" required style="width:190px;font-size:11px"/> <input type="submit" value="นำเข้า" class="general radius" style="height:24px"/></form>', ' align="center"']], $i);
    }
    $help = '<p style="width:983px;margin:0 auto 8px;text-align:left" class="gray_12">ขั้นตอน: 1) ดาวน์โหลดไฟล์ตัวอย่าง 2) กรอกข้อมูลใน Excel ตามหัวคอลัมน์ (ลบแถวตัวอย่างออก) 3) บันทึกเป็น CSV (UTF-8) 4) เลือกไฟล์แล้วกด "นำเข้า"<br>'
        .'วันที่ใช้รูปแบบ วว/ดด/ปปปป (พ.ศ.) / ระดับชั้นใช้ชื่อเต็มหรือชื่อย่อ เช่น ม.1 / ห้องใช้ชื่อห้อง เช่น 1/1 / ข้อมูลที่มีอยู่แล้วจะถูกปรับปรุง ไม่สร้างซ้ำ</p>';
    return ['นำเข้าข้อมูลจากไฟล์ CSV', $result.$help.list_table('ชุดข้อมูลที่นำเข้าได้', ['#', 'ข้อมูล', 'คอลัมน์ในไฟล์', ['ตัวอย่าง', ' style="text-align:center"'], ['นำเข้าไฟล์', ' style="text-align:center"']], $rows, count($sets).' ชุดข้อมูล')];
}
