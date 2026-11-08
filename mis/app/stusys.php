<?php
/**
 * mis/app/stusys.php
 * โมดูล "ระบบนักเรียน (Student System)" — ตั้งค่าและดูข้อมูลที่นักเรียนบันทึกจากหน้า /student
 * (เมนู ค่าคงที่ และตัวช่วยที่ใช้ร่วมกับ student/*.php อยู่ในไฟล์นี้)
 */
defined('MIS') or exit;

/** เมนูหลัก: กลุ่ม => [ไอคอน, [หน้า => ชื่อ]] ('-' = เส้นคั่น) */
const ST_MENU = [
    'วิชาการ' => ['fa-pencil-square-o', [
        'schedule' => 'ตารางสอน', 'subject' => 'ผลการเรียนแต่ละรายวิชา', 'gpa' => 'ผลการเรียน GPA', 'evalt' => 'ประเมินครูรายวิชา', 'evala' => 'ประเมินครูที่ปรึกษา',
        'club' => 'ลงทะเบียนกิจกรรม', 'chars' => 'ประเมินคุณลักษณะ (นักเรียน)', 'charp' => 'ประเมินคุณลักษณะ (ผู้ปกครอง)', 'docreq' => 'ร้องขอเอกสาร (ปพ.)',
        '-1' => '-', 'grade' => 'ผลการสอบแก้ตัว / พิมพ์ใบเกรด', 'cap' => 'ประเมินสมรรถนะสำคัญ', 'analyze' => 'แบบสอบถามวิเคราะห์ผู้เรียน',
    ]],
    'การเงิน' => ['fa-pencil-square-o', ['fin' => 'รายละเอียดการเงิน', 'bank' => 'เอกสารชำระผ่านธนาคาร', 'shop' => 'จองสินค้าอุปกรณ์การเรียน', '-1' => '-', 'fund' => 'ทุนการศึกษา']],
    'บุคลิกภาพ' => ['fa-pencil-square-o', [
        'good' => 'กิจกรรมนักเรียน', 'behave' => 'พฤติกรรมนักเรียน', 'sdq' => 'ประเมินพฤติกรรมนักเรียนประเมินตนเอง', 'q9' => 'ประเมินโรคซึมเศร้า 9Q', 'sdqp' => 'ประเมินผู้ปกครอง',
        '-1' => '-', 'eq' => 'ประเมินความฉลาดทางอารมณ์', 'profile' => 'ข้อมูลส่วนตัวเพิ่มเติม',
    ]],
    'การเข้าเรียน' => ['fa-pencil-square-o', ['att' => 'การมาเรียน', 'attc' => 'เวลาเรียนรายวิชา']],
];

/** หน้าทั้งหมด [key => ชื่อ] */
function st_pages(): array
{
    $o = [];
    foreach (ST_MENU as [, $items]) {
        foreach ($items as $k => $l) {
            if ($l !== '-') {
                $o[$k] = $l;
            }
        }
    }
    return $o;
}

/** เมนูที่ผู้ดูแลปิดไว้ */
function st_menu_off(): array
{
    return json_decode((string) md_get('cfg', 'stu_menu_off'), true) ?: [];
}

function st_cfg(string $k, string $default = '1'): string
{
    $v = md_get('cfg', $k);
    return $v === null ? $default : (string) $v;
}

const EN_TITLES = ['Mr.' => 'Mr.', 'Master' => 'Master', 'Miss' => 'Miss', 'Mrs.' => 'Mrs.', 'Ms.' => 'Ms.'];
const CHAR_ITEMS = ['รักชาติ ศาสน์ กษัตริย์', 'ซื่อสัตย์ สุจริต', 'มีวินัย', 'ใฝ่เรียนรู้', 'อยู่อย่างพอเพียง', 'มุ่งมั่นในการทำงาน', 'รักความเป็นไทย', 'มีจิตสาธารณะ', 'ความกตัญญู'];
const EVAL_SCALE = ['5' => 'มากที่สุด', '4' => 'มาก', '3' => 'ปานกลาง', '2' => 'น้อย', '1' => 'น้อยที่สุด'];
const EVAL_T_DEFAULT = ['ครูชี้แจงจุดประสงค์การเรียนรู้และเกณฑ์การวัดผลให้นักเรียนทราบ', 'ครูเข้าสอนตรงเวลาและเตรียมการสอนมาอย่างดี', 'ครูอธิบายเนื้อหาได้ชัดเจน เข้าใจง่าย',
    'ครูใช้สื่อและเทคโนโลยีประกอบการสอนได้เหมาะสม', 'ครูเปิดโอกาสให้นักเรียนซักถามและแสดงความคิดเห็น', 'ครูจัดกิจกรรมให้นักเรียนได้คิดและลงมือปฏิบัติ',
    'ครูให้คำแนะนำและช่วยเหลือนักเรียนอย่างทั่วถึง', 'ครูวัดผลตรงตามเนื้อหาและแจ้งผลการประเมินให้นักเรียนทราบ', 'ครูแต่งกายเหมาะสมและเป็นแบบอย่างที่ดี', 'นักเรียนได้รับความรู้และประโยชน์จากการเรียนรายวิชานี้'];
const EVAL_A_DEFAULT = ['ครูที่ปรึกษาดูแลเอาใจใส่นักเรียนอย่างทั่วถึง', 'ครูที่ปรึกษาให้คำปรึกษาด้านการเรียนและการใช้ชีวิต', 'ครูที่ปรึกษาติดตามการมาเรียนและพฤติกรรมของนักเรียน',
    'ครูที่ปรึกษาแจ้งข่าวสารของโรงเรียนให้นักเรียนทราบ', 'ครูที่ปรึกษาประสานงานกับผู้ปกครองอย่างสม่ำเสมอ', 'ครูที่ปรึกษาเป็นแบบอย่างที่ดีแก่นักเรียน', 'นักเรียนกล้าปรึกษาปัญหากับครูที่ปรึกษา',
    'นักเรียนพึงพอใจต่อการดูแลของครูที่ปรึกษาโดยรวม'];
const Q9_ITEMS = [1 => 'เบื่อ ไม่สนใจอยากทำอะไร', 2 => 'ไม่สบายใจ ซึมเศร้า ท้อแท้', 3 => 'หลับยาก หรือหลับ ๆ ตื่น ๆ หรือหลับมากไป', 4 => 'เหนื่อยง่าย หรือไม่ค่อยมีแรง', 5 => 'เบื่ออาหาร หรือกินมากเกินไป',
    6 => 'รู้สึกไม่ดีกับตัวเอง คิดว่าตัวเองล้มเหลว หรือทำให้ตนเองหรือครอบครัวผิดหวัง', 7 => 'สมาธิไม่ดีเวลาทำอะไร เช่น ดูโทรทัศน์ ฟังวิทยุ หรือทำงานที่ต้องใช้ความตั้งใจ',
    8 => 'พูดช้า ทำอะไรช้าลงจนคนอื่นสังเกตเห็นได้ หรือกระสับกระส่ายไม่สามารถอยู่นิ่งได้เหมือนที่เคยเป็น', 9 => 'คิดทำร้ายตนเอง หรือคิดว่าถ้าตายไปคงจะดี'];
const Q9_SCALE = ['0' => 'ไม่มีเลย', '1' => 'เป็นบางวัน 1-7 วัน', '2' => 'เป็นบ่อย > 7 วัน', '3' => 'เป็นทุกวัน'];
const SDQP_ITEMS = [1 => 'ห่วงใยความรู้สึกคนอื่น', 2 => 'อยู่ไม่นิ่ง นั่งนิ่ง ๆ ไม่ได้', 3 => 'มักจะบ่นว่าปวดศีรษะ ปวดท้อง หรือไม่สบาย', 4 => 'เต็มใจแบ่งปันสิ่งของให้เพื่อน (ขนม ของเล่น ดินสอ เป็นต้น)',
    5 => 'มักจะอาละวาด หรือโมโหร้าย', 6 => 'ค่อนข้างแยกตัว ชอบเล่นคนเดียว', 7 => 'เชื่อฟัง มักจะทำตามที่ผู้ใหญ่ต้องการ', 8 => 'กังวลใจหลายเรื่อง ดูวิตกกังวลเสมอ',
    9 => 'เป็นที่พึ่งได้เวลาที่คนอื่นเสียใจ อารมณ์ไม่ดี หรือไม่สบายใจ', 10 => 'อยู่ไม่สุข วุ่นวายอย่างมาก', 11 => 'มีเพื่อนสนิท', 12 => 'มักมีเรื่องทะเลาะวิวาทกับเด็กอื่น หรือรังแกเด็กอื่น',
    13 => 'ดูไม่มีความสุข ท้อแท้ ร้องไห้บ่อย', 14 => 'เป็นที่ชื่นชอบของเพื่อน', 15 => 'วอกแวกง่าย สมาธิสั้น', 16 => 'เครียด ไม่ยอมห่างเวลาอยู่ในสถานการณ์ที่ไม่คุ้น และขาดความเชื่อมั่นในตนเอง',
    17 => 'ใจดีกับเด็กที่เล็กกว่า', 18 => 'ชอบโกหก หรือขี้โกง', 19 => 'ถูกเด็กคนอื่นล้อเลียนหรือรังแก', 20 => 'ชอบอาสาช่วยเหลือผู้อื่น (พ่อแม่ ครู เด็กคนอื่น)', 21 => 'คิดก่อนทำ',
    22 => 'ขโมยของที่บ้าน ที่โรงเรียน หรือที่อื่น', 23 => 'เข้ากับผู้ใหญ่ได้ดีกว่าเด็กวัยเดียวกัน', 24 => 'ขี้กลัว รู้สึกหวาดกลัวได้ง่าย', 25 => 'ทำงานได้จนเสร็จ มีความตั้งใจในการทำงาน'];
const SDQ_IMPACT = ['0' => 'ไม่', '1' => 'ใช่ มีปัญหาเล็กน้อย', '2' => 'ใช่ มีปัญหาชัดเจน', '3' => 'ใช่ มีปัญหาอย่างมาก'];
const DOCS = ['pp1' => ['ขอระเบียนแสดงผลการเรียน (ปพ.1)', 'Transcript (Por Por 1)'], 'pp6' => ['ขอแบบรายงานผลการพัฒนาคุณภาพผู้เรียนรายบุคคล (ปพ.6)', 'Student Progress Report (Por Por 6)'],
    'pp7' => ['ขอใบรับรองการเป็นนักเรียน (ปพ.7)', 'Certificate of Student Status (Por Por 7)']];
const DOC_STATUS = ['รอดำเนินการ' => 'wa', 'กำลังดำเนินการ' => 'in', 'เสร็จสิ้น รับเอกสารได้' => 'ok', 'ยกเลิก' => 'no'];

/** คำถามประเมินครู (ผู้ดูแลแก้ไขได้) */
function st_eval_q(string $w): array
{
    $v = md_get('cfg', 'stu_evq_'.$w);
    $list = $v !== null ? array_values(array_filter(array_map('trim', explode("\n", (string) $v)), fn($x) => $x !== '')) : [];
    return $list ?: ($w === 't' ? EVAL_T_DEFAULT : EVAL_A_DEFAULT);
}

function st_q9_level(int $s): array
{
    return $s >= 19 ? ['มีอาการของโรคซึมเศร้า ระดับรุนแรง', 'c-bad'] : ($s >= 13 ? ['มีอาการของโรคซึมเศร้า ระดับปานกลาง', 'c-bad'] : ($s >= 7 ? ['มีอาการของโรคซึมเศร้า ระดับน้อย', 'c-risk'] : ['ไม่มีอาการหรือมีอาการน้อยมาก', 'c-ok']));
}

/** ข้อมูลบัญชีรับชำระเงิน */
function st_bank_cfg(): array
{
    return (json_decode((string) md_get('cfg', 'stu_bank'), true) ?: []) + ['bank' => '', 'branch' => '', 'acc_no' => '', 'acc_name' => '', 'promptpay' => '', 'qr' => '', 'note' => '', 'due' => ''];
}

/** ข้อมูล PromptPay สำหรับ QR (มาตรฐาน EMVCo ของธนาคารแห่งประเทศไทย) */
function promptpay_payload(string $id, float $amount): string
{
    $id = preg_replace('/\D/', '', $id);
    $f = fn(string $t, string $v) => $t.sprintf('%02d', strlen($v)).$v;
    $acc = match (strlen($id)) {
        13 => $f('02', $id),
        15 => $f('03', $id),
        default => $f('01', '0066'.substr(str_pad($id, 10, '0', STR_PAD_LEFT), 1)),
    };
    $p = $f('00', '01').$f('01', $amount > 0 ? '12' : '11').$f('29', $f('00', 'A000000677010111').$acc).$f('53', '764').($amount > 0 ? $f('54', number_format($amount, 2, '.', '')) : '').$f('58', 'TH').'6304';
    $crc = 0xFFFF;
    for ($i = 0, $n = strlen($p); $i < $n; $i++) {
        $crc ^= ord($p[$i]) << 8;
        for ($b = 0; $b < 8; $b++) {
            $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
        }
    }
    return $p.sprintf('%04X', $crc);
}

/* ---------------------------------------------------------------- SDQ (ใช้ทั้งฉบับนักเรียนและผู้ปกครอง) */

const SDQ_ITEMS = [
    1 => 'ฉันพยายามจะทำตัวดีกับคนอื่น ฉันใส่ใจความรู้สึกของคนอื่น', 2 => 'ฉันอยู่ไม่สุข ฉันอยู่นิ่ง ๆ นานไม่ได้', 3 => 'ฉันปวดศีรษะ ปวดท้อง หรือไม่สบายบ่อย ๆ',
    4 => 'ฉันเต็มใจแบ่งปันสิ่งของให้คนอื่น (ขนม ของเล่น ดินสอ ฯลฯ)', 5 => 'ฉันโกรธแรงและมักอารมณ์เสีย', 6 => 'ฉันชอบอยู่กับตัวเอง ฉันชอบเล่นคนเดียวหรืออยู่ตามลำพัง',
    7 => 'ฉันมักทำตามที่คนอื่นบอก', 8 => 'ฉันขี้กังวล', 9 => 'ใคร ๆ ก็พึ่งฉันได้ถ้าเขากำลังเสียใจ อารมณ์ไม่ดี หรือไม่สบายใจ', 10 => 'ฉันอยู่ไม่สุข วุ่นวายอย่างมาก',
    11 => 'ฉันมีเพื่อนสนิท', 12 => 'ฉันมีเรื่องทะเลาะวิวาทบ่อย ฉันทำให้คนอื่นทำอย่างที่ฉันต้องการได้', 13 => 'ฉันไม่มีความสุข ท้อแท้ ร้องไห้บ่อย', 14 => 'เพื่อน ๆ ส่วนมากชอบฉัน',
    15 => 'ฉันวอกแวกง่าย ฉันรู้สึกว่าไม่มีสมาธิ', 16 => 'ฉันกังวลเวลาอยู่ในสถานการณ์ที่ไม่คุ้น และเสียความเชื่อมั่นในตนเองง่าย', 17 => 'ฉันใจดีกับเด็กที่เล็กกว่า',
    18 => 'มีคนว่าฉันโกหกหรือขี้โกงบ่อย ๆ', 19 => 'เด็ก ๆ คนอื่นล้อเลียนหรือรังแกฉัน', 20 => 'ฉันมักจะอาสาช่วยเหลือคนอื่น (พ่อแม่ ครู เพื่อน)', 21 => 'ฉันคิดก่อนทำ',
    22 => 'ฉันเอาของคนอื่นในบ้าน ที่โรงเรียน หรือที่อื่นที่ไม่ใช่ของฉัน', 23 => 'ฉันเข้ากับผู้ใหญ่ได้ดีกว่าเข้ากับเด็กวัยเดียวกัน', 24 => 'ฉันขี้กลัว รู้สึกหวาดกลัวได้ง่าย',
    25 => 'ฉันทำงานได้จนเสร็จ ความตั้งใจในการทำงานของฉันดี',
];
const SDQ_SCALES = ['emotion' => ['ด้านอารมณ์', [3, 8, 13, 16, 24]], 'conduct' => ['ด้านความประพฤติ/เกเร', [5, 7, 12, 18, 22]], 'hyper' => ['ด้านพฤติกรรมอยู่ไม่นิ่ง/สมาธิสั้น', [2, 10, 15, 21, 25]],
    'peer' => ['ด้านความสัมพันธ์กับเพื่อน', [6, 11, 14, 19, 23]], 'prosocial' => ['ด้านสัมพันธภาพทางสังคม (จุดแข็ง)', [1, 4, 9, 17, 20]]];
const SDQ_REVERSE = [7, 11, 14, 21, 25];

function sdq_score(array $a): array
{
    $sc = [];
    foreach (SDQ_SCALES as $k => [$l, $items]) {
        $sc[$k] = array_sum(array_map(fn($i) => in_array($i, SDQ_REVERSE, true) ? 2 - (int) $a[$i] : (int) $a[$i], $items));
    }
    $sc['total'] = $sc['emotion'] + $sc['conduct'] + $sc['hyper'] + $sc['peer'];
    return $sc;
}

function sdq_level(int $total): array
{
    return $total >= 19 ? ['กลุ่มมีปัญหา', 'c-bad'] : ($total >= 17 ? ['กลุ่มเสี่ยง', 'c-risk'] : ['กลุ่มปกติ', 'c-ok']);
}

/* ================================================================ หน้าผู้ดูแล (โมดูลระบบนักเรียน) */

function stusys_menu(): array
{
    return [
        'ตั้งค่าระบบนักเรียน' => ['stu_portal' => 'หน้าระบบนักเรียน (ลิงก์ / QR Code)', 'stu_settings' => 'เปิด/ปิดเมนูและการประเมิน', 'stu_evalq' => 'กำหนดคำถามประเมินครู',
            'stu_bank' => 'บัญชีรับชำระเงิน / QR พร้อมเพย์', 'stu_shop' => 'สินค้าและอุปกรณ์การเรียน'],
        'ข้อมูลจากระบบนักเรียน' => ['stu_docreq' => 'คำร้องขอเอกสาร (ปพ.)', 'stu_eval_res' => 'ผลประเมินครูรายวิชา / ครูที่ปรึกษา', 'stu_char' => 'ผลประเมินคุณลักษณะ (นักเรียน/ผู้ปกครอง)',
            'stu_screen' => 'ผลคัดกรอง 9Q / SDQ ฉบับผู้ปกครอง', 'stu_booking' => 'รายการจองสินค้า', 'stu_en' => 'ชื่อ-ที่อยู่ภาษาอังกฤษของนักเรียน'],
    ];
}

function stu_note(string $html): string
{
    return '<p style="width:983px;margin:0 auto 8px;text-align:left" class="gray_12">'.$html.'</p>';
}

/** นักเรียนในห้อง (หรือทุกคน) [id => row] */
function stu_students(int $room): array
{
    $o = [];
    foreach (q('SELECT S.`id`, S.`student_id`, S.`class`, S.`room`, S.`number`, U.`name` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE U.`active`=1'.($room ? ' AND S.`room`='.$room : '').' ORDER BY S.`class`, S.`room`, S.`number`, S.`student_id`') as $s) {
        $o[(int) $s['id']] = $s;
    }
    return $o;
}

function adm_stu_portal(bool $post)
{
    $url = 'https://'.($_SERVER['HTTP_HOST'] ?? 'schoolmin.vercel.app').'/student/';
    return '<div style="width:983px;margin:0 auto;text-align:center;background:#fff;border:1px solid #ddd;border-radius:6px;padding:30px 0">'
        .'<h1 style="font-family:thaisansbold;font-weight:normal;font-size:34px;margin:0">ระบบนักเรียน (Student System)</h1><p class="gray_12">ให้นักเรียน/ผู้ปกครองเข้าสู่ระบบด้วยเลขประจำตัวนักเรียน และเลขประจำตัวประชาชน 13 หลัก</p>'
        .'<div id="stuQr" style="width:220px;height:220px;margin:16px auto"></div><p style="font-size:18px"><a href="'.h($url).'" target="_blank">'.h($url).'</a></p>'
        .'<p class="gray_12">บัญชีครู/ผู้ดูแลระบบเข้าหน้าระบบนักเรียนไม่ได้ ถ้าเปิดลิงก์ขณะเข้าสู่ระบบอยู่ จะขึ้นข้อความให้ออกจากระบบก่อน</p></div>'
        .'<script src="../scripts/qrcode.js"></script><script>(function(){var q=qrcode(0,"M");q.addData('.json_encode($url).');q.make();document.getElementById("stuQr").innerHTML=q.createSvgTag({cellSize:5,margin:2,scalable:true});})();</script>';
}

function adm_stu_settings(bool $post)
{
    $flags = ['stu_eval_open' => ['เปิดให้ประเมินครูรายวิชา / ครูที่ปรึกษา', '1'], 'stu_char_open' => ['เปิดให้ประเมินคุณลักษณะ (นักเรียน/ผู้ปกครอง)', '1'],
        'stu_doc_open' => ['เปิดรับคำร้องขอเอกสาร (ปพ.)', '1'], 'stu_shop_open' => ['เปิดระบบจองสินค้าอุปกรณ์การเรียน', '0']];
    if ($post) {
        $on = array_keys((array) ($_POST['menu'] ?? []));
        md_set('cfg', 'stu_menu_off', '', '', json_encode(array_values(array_diff(array_keys(st_pages()), $on))));
        foreach ($flags as $k => $x) {
            md_set('cfg', $k, '', '', !empty($_POST['f'][$k]) ? '1' : '0');
        }
        admin_done('stu_settings');
    }
    $off = st_menu_off();
    $menus = '';
    foreach (ST_MENU as $g => [, $items]) {
        $menus .= '<div style="margin:4px 0 2px"><b>'.h($g).'</b></div>';
        foreach ($items as $k => $l) {
            if ($l !== '-') {
                $menus .= '<label style="display:inline-block;width:280px;padding:2px 0"><input type="checkbox" name="menu['.$k.']" value="1"'.(in_array($k, $off, true) ? '' : ' checked').'/> '.h($l).'</label>';
            }
        }
    }
    $fl = '';
    foreach ($flags as $k => [$l, $d]) {
        $fl .= '<label style="display:block;padding:3px 0"><input type="checkbox" name="f['.$k.']" value="1"'.(st_cfg($k, $d) === '1' ? ' checked' : '').'/> '.h($l).'</label>';
    }
    return saved_msg().admin_form('เปิด/ปิดเมนูและการประเมินของระบบนักเรียน', a_url('stu_settings'), [['เมนูที่แสดงในระบบนักเรียน', $menus, false], ['การเปิดรับข้อมูล', $fl, false]], a_url('stu_portal'));
}

function adm_stu_evalq(bool $post)
{
    if ($post) {
        foreach (['t', 'a'] as $w) {
            $v = trim(str_replace("\r", '', (string) ($_POST['q'.$w] ?? '')));
            md_set('cfg', 'stu_evq_'.$w, '', '', $v !== '' ? mb_substr($v, 0, 4000) : null);
        }
        admin_done('stu_evalq');
    }
    $ta = fn($w) => '<textarea name="q'.$w.'" class="general radius grayborder" style="width:640px;height:230px">'.h(implode("\n", st_eval_q($w))).'</textarea>';
    return saved_msg().stu_note('พิมพ์คำถามบรรทัดละ 1 ข้อ ใช้มาตรวัด 5 ระดับ (มากที่สุด - น้อยที่สุด) ถ้าแก้คำถามระหว่างภาคเรียน ผลประเมินที่ส่งไปแล้วจะจับคู่ตามลำดับข้อ')
        .admin_form('กำหนดคำถามประเมินครู', a_url('stu_evalq'), [['ประเมินครูรายวิชา', $ta('t'), true], ['ประเมินครูที่ปรึกษา', $ta('a'), true]], a_url('stu_portal'));
}

function stu_level5(?float $v): string
{
    if ($v === null) {
        return '-';
    }
    $t = $v >= 4.5 ? 'มากที่สุด' : ($v >= 3.5 ? 'มาก' : ($v >= 2.5 ? 'ปานกลาง' : ($v >= 1.5 ? 'น้อย' : 'น้อยที่สุด')));
    return number_format($v, 2).' <span class="gray_12">('.$t.')</span>';
}

function adm_stu_eval_res(bool $post)
{
    $w = ($_GET['w'] ?? 't') === 'a' ? 'a' : 't';
    $kind = $w === 't' ? 'stu_evt' : 'stu_eva';
    $qs = st_eval_q($w);
    $by = [];
    foreach (md_rows($kind, null, yt()) as $r) {
        $by[(string) $r['r3']][] = json_decode((string) $r['value'], true) ?: [];
    }
    $tabs = '<div style="width:983px;margin:0 auto 10px;text-align:left">'.btn('ประเมินครูรายวิชา', a_url('stu_eval_res', '&w=t'), $w === 't' ? 'scope' : 'student').btn('ประเมินครูที่ปรึกษา', a_url('stu_eval_res', '&w=a'), $w === 'a' ? 'scope' : 'student').'</div>';
    $names = personnel_options();
    $cs = $w === 't' ? courses_by_id(array_keys($by)) : [];
    $label = fn($k) => $w === 't' ? h(($cs[(int) $k]['course_code'] ?? '').' '.($cs[(int) $k]['course_name'] ?? '')).' <span class="gray_12">'.h((string) ($cs[(int) $k]['teacher'] ?? '')).'</span>' : h($names[(int) $k] ?? '-');
    $avg = function (array $list, ?int $qi = null) {
        $v = [];
        foreach ($list as $d) {
            foreach ((array) ($d['a'] ?? []) as $i => $x) {
                if ($qi === null || (int) $i === $qi) {
                    $v[] = (int) $x;
                }
            }
        }
        return $v ? array_sum($v) / count($v) : null;
    };
    if (($k = (string) ($_GET['k'] ?? '')) !== '' && isset($by[$k])) {
        $rows = '';
        foreach ($qs as $i => $q) {
            $rows .= zrow([$i + 1, ['<div style="text-align:left">'.h($q).'</div>', ''], [stu_level5($avg($by[$k], $i)), ' align="center"']], $i);
        }
        $notes = implode('', array_map(fn($d) => trim((string) ($d['note'] ?? '')) !== '' ? '<li>'.h($d['note']).'</li>' : '', $by[$k]));
        return $tabs.list_table('ผลประเมิน: '.$label($k), ['ข้อ', 'รายการประเมิน', 'ค่าเฉลี่ย'], $rows, count($by[$k]).' คน', btn('กลับ', a_url('stu_eval_res', '&w='.$w), 'back'))
            .($notes ? '<div style="width:983px;margin:10px auto;text-align:left;background:#fff;border:1px solid #ddd;padding:10px 20px"><b>ข้อเสนอแนะจากนักเรียน (ไม่ระบุชื่อ)</b><ul>'.$notes.'</ul></div>' : '');
    }
    $rows = '';
    $i = 0;
    uksort($by, fn($a, $b) => strcmp(strip_tags($label($a)), strip_tags($label($b))));
    foreach ($by as $k => $list) {
        $rows .= zrow([++$i, ['<div style="text-align:left">'.$label($k).'</div>', ''], [count($list), ' align="center"'], [stu_level5($avg($list)), ' align="center"'], ['<a href="'.a_url('stu_eval_res', '&w='.$w.'&k='.rawurlencode((string) $k)).'">รายข้อ</a>', ' align="center"']], $i);
    }
    return $tabs.stu_note('ผลประเมินภาคเรียน '.yt().' แสดงเฉพาะค่าเฉลี่ย ไม่แสดงชื่อนักเรียนผู้ประเมิน').list_table($w === 't' ? 'ผลประเมินครูรายวิชา' : 'ผลประเมินครูที่ปรึกษา', ['#', $w === 't' ? 'รายวิชา / ครูผู้สอน' : 'ครูที่ปรึกษา', 'ผู้ประเมิน', 'ค่าเฉลี่ย', ''], $rows, count($by).' รายการ');
}

function adm_stu_char(bool $post)
{
    $room = (int) ($_GET['room'] ?? 0);
    $f = pick_form('stu_char', ['room' => ['ห้อง', room_opts(false), $room]]);
    if (!$room) {
        return $f.stu_note('เลือกห้องเพื่อดูผลประเมินคุณลักษณะอันพึงประสงค์ที่นักเรียนและผู้ปกครองประเมินผ่านระบบนักเรียน เทียบกับผลที่ครูประเมิน');
    }
    $st = stu_students($room);
    $ids = array_keys($st);
    $get = function (string $kind) use ($ids) {
        $o = [];
        foreach (md_rows($kind, $ids, yt()) as $r) {
            $d = json_decode((string) $r['value'], true) ?: [];
            $o[(int) $r['r1']] = $d['a'] ?? [];
        }
        return $o;
    };
    $s = $get('char_s');
    $p = $get('char_p');
    $t = function_exists('scores_of') ? scores_of('char', $ids) : [];
    $av = fn($a) => $a ? array_sum($a) / count($a) : null;
    $cell = fn($a) => [($v = $av($a)) === null ? '<span class="gray_12">-</span>' : number_format($v, 2).' <span class="gray_12">'.level_text($v).'</span>', ' align="center"'];
    $rows = '';
    $i = 0;
    foreach ($st as $sid => $x) {
        $rows .= zrow([[(int) $x['number'], ' align="center"'], h($x['student_id']), ['<div style="text-align:left">'.h($x['name']).'</div>', ''], $cell($s[$sid] ?? []), $cell($p[$sid] ?? []), $cell($t[$sid] ?? [])], $i++);
    }
    return $f.list_table('ผลประเมินคุณลักษณะอันพึงประสงค์ ห้อง '.h(room_options()[$room] ?? ''), ['เลขที่', 'รหัส', 'ชื่อ-นามสกุล', 'นักเรียนประเมิน', 'ผู้ปกครองประเมิน', 'ครูประเมิน'], $rows, count($st).' คน');
}

function adm_stu_screen(bool $post)
{
    $room = (int) ($_GET['room'] ?? 0);
    $f = pick_form('stu_screen', ['room' => ['ห้อง', room_opts(), $room]]);
    $st = stu_students($room);
    $q9 = [];
    foreach (md_rows('q9', array_keys($st), yt()) as $r) {
        $q9[(int) $r['r1']] = json_decode((string) $r['value'], true) ?: [];
    }
    $sp = [];
    foreach (md_rows('sdq_par', array_keys($st), yt()) as $r) {
        $sp[(int) $r['r1']] = json_decode((string) $r['value'], true) ?: [];
    }
    $only = isset($_GET['risk']);
    $rows = '';
    $i = 0;
    foreach ($st as $sid => $x) {
        $a = $q9[$sid] ?? null;
        $b = $sp[$sid] ?? null;
        if (!$a && !$b) {
            continue;
        }
        $risk = ($a && ((int) $a['score'] >= 7 || (int) ($a['a'][9] ?? 0) > 0)) || ($b && (int) $b['scores']['total'] >= 17);
        if ($only && !$risk) {
            continue;
        }
        $qa = $a ? st_q9_level((int) $a['score']) : null;
        $qb = $b ? sdq_level((int) $b['scores']['total']) : null;
        $color = ['c-ok' => '#27AE60', 'c-risk' => '#E67E22', 'c-bad' => '#C0392B'];
        $rows .= zrow([++$i, h(class_room_short($x['class'], $x['room'])).' / '.(int) $x['number'], h($x['student_id']), ['<div style="text-align:left">'.h($x['name']).'</div>', ''],
            [$a ? (int) $a['score'].' <span style="color:'.$color[$qa[1]].'">'.h($qa[0]).'</span>'.((int) ($a['a'][9] ?? 0) > 0 ? '<br><b style="color:#C0392B">ข้อ 9 มีความคิดทำร้ายตนเอง — ควรติดตามโดยเร็ว</b>' : '') : '-', ''],
            [$b ? (int) $b['scores']['total'].' <span style="color:'.$color[$qb[1]].'">'.h($qb[0]).'</span>' : '-', ' align="center"']], $i);
    }
    $flt = '<a href="'.a_url('stu_screen', '&room='.$room.($only ? '' : '&risk=1')).'">'.($only ? 'แสดงทั้งหมด' : 'แสดงเฉพาะกลุ่มที่ควรติดตาม').'</a>';
    return $f.stu_note('ผลคัดกรองเบื้องต้นที่นักเรียน/ผู้ปกครองทำผ่านระบบนักเรียน ภาคเรียน '.yt().' (ไม่ใช่การวินิจฉัย) ใช้ประกอบระบบดูแลช่วยเหลือนักเรียน — ข้อมูลนี้เป็นความลับ ควรเปิดดูเฉพาะผู้ที่เกี่ยวข้อง &nbsp; '.$flt)
        .list_table('ผลคัดกรอง 9Q / SDQ ฉบับผู้ปกครอง', ['#', 'ชั้น/เลขที่', 'รหัส', 'ชื่อ-นามสกุล', '9Q (คะแนน / ผล)', 'SDQ ผู้ปกครอง (คะแนนรวมปัญหา)'], $rows, $i.' คน');
}

function adm_stu_docreq(bool $post)
{
    if ($post && ($id = (string) ($_GET['id'] ?? '')) !== '') {
        foreach (md_rows('docreq', $id) as $r) {
            $d = json_decode((string) $r['value'], true) ?: [];
            $st = (string) ($_POST['status'] ?? '');
            $d['status'] = isset(DOC_STATUS[$st]) ? $st : $d['status'];
            $d['note'] = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 250);
            $d['by'] = (int) me()['id'];
            md_set('docreq', $id, (string) $r['r2'], '', json_encode($d, JSON_UNESCAPED_UNICODE));
        }
        admin_done('stu_docreq', '&s='.rawurlencode((string) ($_GET['s'] ?? '')));
    }
    $fs = (string) ($_GET['s'] ?? '');
    $list = md_rows('docreq');
    usort($list, fn($a, $b) => strcmp((string) $b['r1'], (string) $a['r1']));
    $st = stu_students(0);
    $rows = '';
    $i = 0;
    $cnt = [];
    foreach ($list as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        $status = (string) ($d['status'] ?? 'รอดำเนินการ');
        $cnt[$status] = ($cnt[$status] ?? 0) + 1;
        if ($fs !== '' && $status !== $fs) {
            continue;
        }
        $sid = (int) $r['r2'];
        $s = $st[$sid] ?? q1('SELECT S.`student_id`, S.`class`, S.`room`, S.`number`, U.`name` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`id`=?', [$sid]);
        $docs = implode('<br>', array_map(fn($k, $c) => h(DOCS[$k][0] ?? $k).' x '.(int) $c, array_keys($d['docs'] ?? []), $d['docs'] ?? []));
        $prints = implode(' ', array_map(fn($k) => in_array($k, ['pp1', 'pp7'], true) ? '<a href="'.a_url($k, '&print=1&student='.$sid).'" target="_blank">พิมพ์ '.strtoupper(str_replace('pp', 'ปพ.', $k)).'</a>' : '', array_keys($d['docs'] ?? [])));
        $sel = '';
        foreach (DOC_STATUS as $k => $c) {
            $sel .= '<option'.($k === $status ? ' selected' : '').'>'.h($k).'</option>';
        }
        $info = $d['info'] ?? [];
        $rows .= zrow([++$i, h(th_date(substr((string) $d['date'], 0, 10), ' ')), ['<div style="text-align:left"><b>'.h($s['name'] ?? '-').'</b><br><span class="gray_12">'.h(($s['student_id'] ?? '').' '.(isset($s['class']) ? class_room_short($s['class'], $s['room']) : '')).'</span>'
            .(($d['lang'] ?? '') === 'en' ? '<br><span class="gray_12">EN: '.h(trim(($info['title'] ?? '').' '.($info['first'] ?? '').' '.($info['last'] ?? ''))).'</span>' : '').'</div>', ''],
            ['<div style="text-align:left">'.$docs.'<br><span class="gray_12">ภาษา'.(($d['lang'] ?? '') === 'en' ? 'อังกฤษ' : 'ไทย').' — '.h($d['reason'] ?? '').'</span><br>'.$prints.'</div>', ''],
            ['<form method="post" action="'.h(a_url('stu_docreq', '&id='.rawurlencode((string) $r['r1']).'&s='.rawurlencode($fs))).'" style="margin:0;white-space:nowrap"><select name="status" class="general radius" style="height:24px">'.$sel.'</select> '
                .'<input name="note" value="'.h($d['note'] ?? '').'" placeholder="หมายเหตุถึงนักเรียน" class="general radius" style="width:150px;height:20px"/> <input type="submit" value="บันทึก" class="general radius" style="height:24px"/></form>', ' align="center"']], $i);
    }
    $flt = '<a href="'.a_url('stu_docreq').'">ทั้งหมด ('.count($list).')</a>';
    foreach (array_keys(DOC_STATUS) as $k) {
        $flt .= ' | <a href="'.a_url('stu_docreq', '&s='.rawurlencode($k)).'"'.($fs === $k ? ' style="font-weight:bold"' : '').'>'.h($k).' ('.(int) ($cnt[$k] ?? 0).')</a>';
    }
    return saved_msg().stu_note($flt).list_table('คำร้องขอเอกสาร (ปพ.) จากระบบนักเรียน', ['#', 'วันที่', 'นักเรียน', 'เอกสาร / เหตุผล', 'สถานะ'], $rows, $i.' รายการ');
}

function adm_stu_bank(bool $post)
{
    $b = st_bank_cfg();
    if ($post) {
        foreach (['bank', 'branch', 'acc_no', 'acc_name', 'promptpay', 'due', 'note'] as $k) {
            $b[$k] = mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $k === 'note' ? 500 : 120);
        }
        $b['promptpay'] = preg_replace('/\D/', '', $b['promptpay']);
        if (!empty($_POST['qr_del'])) {
            $b['qr'] = '';
        }
        if (!empty($_FILES['qr']['tmp_name']) && is_uploaded_file($_FILES['qr']['tmp_name']) && ($info = @getimagesize($_FILES['qr']['tmp_name'])) && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            $dir = ROOT_PATH.'datas/mis/';
            @mkdir($dir, 0755, true);
            $name = 'stu_qr_'.time().($info[2] === IMAGETYPE_PNG ? '.png' : '.jpg');
            if (move_uploaded_file($_FILES['qr']['tmp_name'], $dir.$name)) {
                $b['qr'] = $name;
            }
        }
        md_set('cfg', 'stu_bank', '', '', json_encode($b, JSON_UNESCAPED_UNICODE));
        admin_done('stu_bank');
    }
    $in = fn($k, $w = 300, $ph = '') => inp($k, $b[$k], $w, 'text', $ph !== '' ? 'placeholder="'.h($ph).'"' : '');
    return saved_msg().stu_note('ข้อมูลนี้แสดงในเมนู "เอกสารชำระผ่านธนาคาร" ของระบบนักเรียน พร้อมรายการค่าบำรุงการศึกษาของภาคเรียน (กำหนดที่โมดูลรับชำระเงิน) — ถ้าใส่หมายเลขพร้อมเพย์ ระบบจะสร้าง QR พร้อมยอดเงินของนักเรียนแต่ละคนให้อัตโนมัติ')
        .admin_form('บัญชีรับชำระเงิน / QR พร้อมเพย์', a_url('stu_bank'), [['ธนาคาร', $in('bank', 300, 'เช่น ธนาคารกรุงไทย'), false], ['สาขา', $in('branch'), false], ['เลขที่บัญชี', $in('acc_no', 200), false], ['ชื่อบัญชี', $in('acc_name', 400), false],
            ['หมายเลขพร้อมเพย์', $in('promptpay', 200, 'เบอร์โทร 10 หลัก / เลขผู้เสียภาษี 13 หลัก'), false],
            ['หรือรูป QR ชำระเงิน', '<input type="file" name="qr" accept="image/png,image/jpeg"/>'.($b['qr'] !== '' ? ' <a href="/datas/mis/'.h($b['qr']).'" target="_blank">ดูรูปเดิม</a> <label><input type="checkbox" name="qr_del" value="1"/> ลบรูป</label>' : ''), false],
            ['กำหนดชำระภายใน', $in('due', 300, 'เช่น 30 พฤศจิกายน 2569'), false], ['หมายเหตุ', txt('note', $b['note']), false]], a_url('stu_portal'));
}

function adm_stu_shop(bool $post)
{
    return md_page('stu_shop', 'stu_shop', 'สินค้า/อุปกรณ์การเรียน', ['name' => f_text('ชื่อสินค้า', true), 'price' => f_num('ราคา (บาท)'), 'detail' => f_text('รายละเอียด'), 'sizes' => f_text('ขนาด/ตัวเลือก (คั่นด้วย , เช่น S,M,L)'),
        'class' => f_sel('สำหรับระดับชั้น', fn() => ['0' => 'ทุกระดับชั้น'] + cats('class'), false), 'img' => f_file('รูปสินค้า (.jpg .png)'), 'active' => f_sel('แสดงในระบบ', ['1' => 'แสดง', '0' => 'ซ่อน'])],
        ['name' => 'สินค้า', 'price' => 'ราคา', 'sizes' => 'ขนาด', 'class' => 'ระดับชั้น', 'active' => 'แสดง'], $post);
}

function adm_stu_booking(bool $post)
{
    $states = ['จองแล้ว', 'ชำระเงินแล้ว', 'รับสินค้าแล้ว', 'ยกเลิก'];
    if ($post && ($sid = (int) ($_GET['sid'] ?? 0))) {
        $d = json_decode((string) md_get('stu_book', $sid, yt()), true);
        if ($d && in_array($st = (string) ($_POST['status'] ?? ''), $states, true)) {
            $d['status'] = $st;
            md_set('stu_book', $sid, yt(), '', json_encode($d, JSON_UNESCAPED_UNICODE));
        }
        admin_done('stu_booking');
    }
    $st = stu_students(0);
    $rows = '';
    $sum = [];
    $i = 0;
    foreach (md_rows('stu_book', null, yt()) as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        $sid = (int) $r['r1'];
        $items = '';
        foreach ($d['items'] ?? [] as $x) {
            $items .= h($x['name']).($x['size'] !== '' ? ' ('.h($x['size']).')' : '').' x '.(int) $x['qty'].'<br>';
            if (($d['status'] ?? '') !== 'ยกเลิก') {
                $k = $x['name'].($x['size'] !== '' ? ' ('.$x['size'].')' : '');
                $sum[$k] = ($sum[$k] ?? 0) + (int) $x['qty'];
            }
        }
        $sel = implode('', array_map(fn($s) => '<option'.($s === ($d['status'] ?? '') ? ' selected' : '').'>'.h($s).'</option>', $states));
        $rows .= zrow([++$i, h($st[$sid]['student_id'] ?? ''), ['<div style="text-align:left">'.h($st[$sid]['name'] ?? '-').' <span class="gray_12">'.(isset($st[$sid]) ? h(class_room_short($st[$sid]['class'], $st[$sid]['room'])) : '').'</span></div>', ''],
            ['<div style="text-align:left">'.$items.'</div>', ''], [money($d['total'] ?? 0), ' align="right"'],
            ['<form method="post" action="'.h(a_url('stu_booking', '&sid='.$sid)).'" style="margin:0"><select name="status" class="general radius" style="height:24px" onchange="this.form.submit()">'.$sel.'</select></form>', ' align="center"']], $i);
    }
    ksort($sum);
    $s2 = '';
    $j = 0;
    foreach ($sum as $k => $n) {
        $s2 .= zrow([++$j, ['<div style="text-align:left">'.h($k).'</div>', ''], [$n, ' align="center"']], $j);
    }
    return list_table('รายการจองสินค้า ภาคเรียน '.yt(), ['#', 'รหัส', 'นักเรียน', 'รายการ', 'ยอดเงิน', 'สถานะ'], $rows, $i.' รายการ').'<div style="height:10px"></div>'.list_table('สรุปจำนวนที่ต้องเตรียม (ไม่รวมที่ยกเลิก)', ['#', 'สินค้า', 'จำนวน'], $s2);
}

function adm_stu_en(bool $post)
{
    $room = (int) ($_GET['room'] ?? 0);
    $f = pick_form('stu_en', ['room' => ['ห้อง', room_opts(), $room]]);
    $st = stu_students($room);
    $rows = '';
    $i = 0;
    foreach (md_rows('st_en', array_keys($st)) as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        $x = $st[(int) $r['r1']] ?? null;
        if (!$x) {
            continue;
        }
        $nm = fn($w) => h(trim(($d[$w.'_t'] ?? '').' '.($d[$w.'_f'] ?? '').' '.($d[$w.'_l'] ?? '')));
        $rows .= zrow([++$i, h(class_room_short($x['class'], $x['room'])).' / '.(int) $x['number'], ['<div style="text-align:left">'.h($x['name']).'<br><b>'.$nm('st').'</b></div>', ''],
            ['<div style="text-align:left;font-size:11px">บิดา: '.$nm('fa').'<br>มารดา: '.$nm('mo').'<br>ผู้ปกครอง: '.$nm('pa').'</div>', ''],
            ['<div style="text-align:left;font-size:11px">'.h($d['addr_en'] ?? '').'<br>เกิด: '.h($d['birthplace_en'] ?? '').'<br>สถานศึกษาเดิม: '.h($d['school_old'] ?? '').'</div>', ''], h($d['updated'] ?? '')], $i);
    }
    return $f.stu_note('ข้อมูลภาษาอังกฤษที่นักเรียนกรอกเองผ่านปุ่ม "Edit Name English" ในระบบนักเรียน ใช้ประกอบการออกเอกสารภาษาอังกฤษ').list_table('ชื่อ-ที่อยู่ภาษาอังกฤษของนักเรียน', ['#', 'ชั้น/เลขที่', 'ชื่อ', 'บิดา / มารดา / ผู้ปกครอง', 'ที่อยู่ / สถานที่เกิด / สถานศึกษาเดิม', 'แก้ไขล่าสุด'], $rows, $i.' คน');
}
