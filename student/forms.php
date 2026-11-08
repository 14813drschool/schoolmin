<?php
/**
 * student/forms.php
 * หน้าต่าง ๆ ของระบบสารสนเทศสำหรับนักเรียน (เรียกจาก student/index.php เท่านั้น)
 */
defined('MIS') or exit;

function st_pages(): array
{
    return [
        'profile' => ['บันทึกข้อมูลส่วนตัวของนักเรียน', 'fa-user', '#2980B9'],
        'sdq' => ['ประเมินพฤติกรรมของตนเอง (SDQ)', 'fa-folder-open', '#F39C12'],
        'cap' => ['ประเมินสมรรถนะสำคัญของผู้เรียน', 'fa-star', '#8E44AD'],
        'analyze' => ['ทำแบบสอบถามวิเคราะห์ผู้เรียน', 'fa-pie-chart', '#E67E22'],
        'eq' => ['ประเมินความฉลาดทางอารมณ์', 'fa-heart', '#E74C3C'],
        'att' => ['ตรวจสอบเวลาเรียน', 'fa-clock-o', '#16A085'],
        'grade' => ['ตรวจสอบผลการเรียน ผลการสอบแก้ตัว', 'fa-graduation-cap', '#2C3E50'],
        'fund' => ['ตรวจสอบข้อมูลทุนการศึกษา', 'fa-gift', '#D35400'],
        'club' => ['ลงทะเบียนกิจกรรมชุมนุม', 'fa-users', '#27AE60'],
    ];
}

/* ================================================================ หน้าตา */

function st_css(): string
{
    return '<style>
html,body{margin:0;min-height:100%}body{background:#eef7fb url(/mis/images/bg2.png) no-repeat center top;background-size:cover;font:13px Tahoma,sans-serif;color:#222}
.wrap{width:720px;max-width:calc(100% - 24px);margin:0 auto;padding:16px 0 40px}
.brand{display:flex;align-items:center;gap:10px;margin:8px 0 18px}.brand img{height:78px}.brand h1{margin:0;font-family:thaisansbold;font-weight:normal;font-size:40px;line-height:1;color:#111}
.brand small{display:block;font:15px Tahoma;letter-spacing:.5px;color:#111;margin-top:4px}
.sys{font-family:thaisansbold;font-size:32px;color:#111;margin:0 0 12px}
.card{background:rgba(255,255,255,.88);border:1px solid #e3e3e3;border-radius:4px;padding:16px 18px;box-shadow:0 1px 3px rgba(0,0,0,.05)}
.card h2{margin:0 0 10px;font-family:thaisansbold;font-weight:normal;font-size:28px;color:#1C6EA4}.card h2 i{margin-right:6px}
label.l{display:block;font-size:14px;margin:4px 0 6px}
.in{display:block;width:100%;box-sizing:border-box;height:34px;padding:4px 10px;margin:0 0 14px;border:1px solid #ccc;border-radius:3px;background:#f6f6f6;font:14px Tahoma}
textarea.in{height:70px}.in:focus{outline:none;border-color:#4fb8d9;background:#fff}
.btn{display:inline-block;border:1px solid #2f8fb8;border-radius:3px;background:linear-gradient(90deg,#3fa9d6,#55c8e0 50%,#3fa9d6);color:#fff;font:bold 15px Tahoma;padding:8px 22px;cursor:pointer;text-decoration:none;text-shadow:0 1px 1px rgba(0,0,0,.2)}
.btn.full{width:100%;font-size:17px}.btn.gray{background:#95a5a6;border-color:#7f8c8d}.btn:disabled{opacity:.5;cursor:default}
.help{margin:14px 0}.help b{font-size:14px}
table.t{width:100%;border-collapse:collapse;background:rgba(255,255,255,.75);margin:8px 0}
table.t th{background:#c6dce4;padding:6px;font-size:13px}table.t td{padding:6px;border-bottom:1px solid #e5eef2;text-align:center}table.t td.l{text-align:left}
.msg{padding:10px 12px;border-radius:3px;margin:0 0 12px;font-size:14px}.msg.ok{background:#E9F7EF;border:1px solid #A9DFBF;color:#1E8449}.msg.no{background:#FDEDEC;border:1px solid #F5B7B1;color:#C0392B}
.who{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:12px;padding:10px 14px}.who b{font-size:15px}
.tiles{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
.tile{display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:8px;min-height:120px;padding:12px;background:rgba(255,255,255,.9);border:1px solid #dde8ee;border-radius:6px;color:#333;text-decoration:none;font-size:14px}
.tile i{font-size:38px}.tile:hover{border-color:#4fb8d9;box-shadow:0 0 10px rgba(79,184,217,.4)}
.back{display:inline-block;margin:0 0 10px;color:#1C6EA4;text-decoration:none}
.q{padding:8px 0;border-bottom:1px dashed #ddd}.q .tx{margin-bottom:5px;font-size:14px}.q label{margin-right:14px;white-space:nowrap;display:inline-block;padding:2px 0}
.lv{font-weight:bold}.c-ok{color:#27AE60}.c-risk{color:#E67E22}.c-bad{color:#E74C3C}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}
@media (max-width:620px){.tiles{grid-template-columns:repeat(2,1fr)}.brand h1{font-size:30px}.brand img{height:60px}.sys{font-size:24px}.grid2{grid-template-columns:1fr}}
</style>';
}

function st_head(string $title, string $wrap = ''): string
{
    return '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>'.h($title).'</title>'
        .'<link rel="shortcut icon" href="/mis/images/favicon.ico"><link rel="stylesheet" href="/mis/css/thaisansbold.css"><link rel="stylesheet" href="/mis/root/css/font-awesome.css">'.st_css().'</head><body><div class="wrap"'.($wrap ? ' style="width:'.$wrap.'"' : '').'>'
        .'<div class="brand"><a href="/mis/"><img src="/mis/gallery/logo/school_logo.png" alt="logo"></a><div><h1>'.h(cfg('school_name', 'โรงเรียนดรุณราษฎร์วิทยา')).'</h1><small>DAROONRAJWITTAYA SCHOOL</small></div></div>';
}

function st_login_page(string $err, bool $loggedOther = false): void
{
    mis_headers();
    $terms = '';
    foreach (array_slice(array_reverse(md_rows('calendar')), 0, 4) as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        $terms .= '<tr><td>'.h(str_replace('/', '/', implode('/', array_reverse(explode('/', (string) $r['r1']))))).'</td><td>'.h($d['classes'] ?? 'ทุกระดับชั้น').'</td><td>'.(!empty($d['grade_date']) ? th_date($d['grade_date'], ' ') : '').'</td><td>'.(!empty($d['fail_date']) ? th_date($d['fail_date'], ' ') : '').'</td></tr>';
    }
    echo st_head('ระบบสารสนเทศสำหรับผู้ปกครองและนักเรียน | เข้าสู่ระบบ', '430px'), '<div class="sys">ระบบสารสนเทศสำหรับผู้ปกครองและนักเรียน</div>',
        '<form class="card" method="post" action="./" style="max-width:430px"><h2><i class="fa fa-lock"></i>เข้าสู่ระบบ</h2>',
        $err !== '' ? '<div class="msg no">'.h($err).($loggedOther ? ' (<a href="?p=logout">ออกจากระบบบัญชีนี้</a>)' : '').'</div>' : '',
        '<input type="hidden" name="login" value="1"><label class="l">USERNAME</label><input class="in" name="username" placeholder="ชื่อผู้ใช้ (UserName)" autocomplete="username" required>',
        '<label class="l">PASSWORD (เลขประจำตัวประชาชน 13 หลัก)</label><input class="in" type="password" name="password" placeholder="รหัสผ่าน (Password)" autocomplete="current-password" required>',
        '<button class="btn full" type="submit">LOG IN</button></form>',
        '<div class="help"><b>คำแนะนำขั้นตอนการเข้าใช้ระบบ</b><br>1. ช่องชื่อผู้ใช้ (UserName) ให้กรอกเลขประจำตัวนักเรียน<br>2. ช่องรหัสผ่าน (Password) ให้กรอกเลขประจำตัวประชาชน 13 หลัก<br>3. คลิกปุ่ม login</div>',
        $terms ? '<table class="t" style="max-width:430px"><tr><th>ภาคเรียนที่</th><th>ระดับชั้น</th><th>วันประกาศผลการเรียน</th><th>วันประกาศผลสอบไม่ผ่าน</th></tr>'.$terms.'</table>' : '',
        '</div></body></html>';
}

function st_layout(string $title, string $body, array $S): void
{
    mis_headers();
    echo st_head(($title ?: 'หน้าหลัก').' | ระบบสารสนเทศสำหรับนักเรียน'), '<div class="card who"><div><b>'.h($S['name']).'</b><br>รหัสนักเรียน '.h($S['student_id']).' ชั้น '.h(class_room_short($S['class'], $S['room'])).' เลขที่ '.(int) $S['number']
        .'<br><span style="color:#777">ภาคเรียนที่ '.h(explode('/', yt())[1]).' ปีการศึกษา '.h(explode('/', yt())[0]).'</span></div><a class="btn gray" href="?p=logout"><i class="fa fa-sign-out"></i> ออกจากระบบ</a></div>',
        $title !== '' ? '<a class="back" href="./"><i class="fa fa-chevron-left"></i> กลับหน้าหลัก</a><div class="card"><h2>'.h($title).'</h2>'.$body.'</div>' : $body, '</div></body></html>';
}

function st_home(): string
{
    $o = '<div class="sys">ระบบสารสนเทศสำหรับผู้ปกครองและนักเรียน</div><div class="tiles">';
    foreach (st_pages() as $k => [$t, $ic, $c]) {
        $o .= '<a class="tile" href="?p='.$k.'"><i class="fa '.$ic.'" style="color:'.$c.'"></i>'.h($t).'</a>';
    }
    return $o.'</div>';
}

function st_saved(): string
{
    return isset($_GET['saved']) ? '<div class="msg ok"><i class="fa fa-check"></i> บันทึกข้อมูลเรียบร้อยแล้ว</div>' : '';
}

function st_done(string $p): void
{
    redirect('?p='.$p.'&saved=1');
}

function st_in(string $name, $v, string $label, string $type = 'text'): string
{
    return '<label class="l">'.h($label).'</label>'.($type === 'textarea' ? '<textarea class="in" name="'.$name.'">'.h($v).'</textarea>' : '<input class="in" type="'.$type.'" name="'.$name.'" value="'.h($v).'">');
}

function st_sel(string $name, array $opts, $v, string $label): string
{
    $o = '<label class="l">'.h($label).'</label><select class="in" name="'.$name.'"><option value="">- เลือก -</option>';
    foreach ($opts as $k => $t) {
        $o .= '<option value="'.h($k).'"'.((string) $k === (string) $v ? ' selected' : '').'>'.h($t).'</option>';
    }
    return $o.'</select>';
}

/** แบบสอบถามแบบเลือกตอบ: $items = [ข้อ => ข้อความ], $choices = [ค่า => ข้อความ] */
function st_questions(array $items, array $choices, array $ans, string $name = 'a'): string
{
    $o = '';
    $n = 0;
    foreach ($items as $k => $text) {
        $o .= '<div class="q"><div class="tx">'.(++$n).'. '.h($text).'</div>';
        foreach ($choices as $v => $t) {
            $o .= '<label><input type="radio" name="'.$name.'['.$k.']" value="'.h($v).'"'.((string) ($ans[$k] ?? '') === (string) $v ? ' checked' : '').' required> '.h($t).'</label>';
        }
        $o .= '</div>';
    }
    return $o;
}

function st_answers(array $items, array $choices, string $name = 'a'): ?array
{
    $a = [];
    foreach (array_keys($items) as $k) {
        $v = (string) ($_POST[$name][$k] ?? '');
        if (!array_key_exists($v, $choices)) {
            return null;
        }
        $a[$k] = $v;
    }
    return $a;
}

function st_form(string $p, string $inner, string $btn = 'บันทึกข้อมูล'): string
{
    return '<form method="post" action="?p='.$p.'">'.$inner.'<p style="margin-top:14px"><button class="btn" type="submit"><i class="fa fa-save"></i> '.h($btn).'</button></p></form>';
}

/* ================================================================ 1. ข้อมูลส่วนตัว */

function st_profile(array $S, bool $post): string
{
    $key = 's'.(int) $S['id'];
    $d = json_decode((string) md_get('sa_profile', $key), true) ?: [];
    $fields = ['talent' => 'ความสามารถพิเศษ', 'health' => 'ด้านสุขภาพ/โรคประจำตัว', 'live_with' => 'อาศัยอยู่กับ', 'income' => 'รายได้ครอบครัว/เดือน (บาท)'];
    if ($post) {
        $cut = fn($k, $n) => mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $n);
        q('UPDATE {student} SET `address`=?, `parent`=?, `parent_phone`=? WHERE `id`=?', [$cut('address', 100), $cut('parent', 100), $cut('parent_phone', 32), (int) $S['id']]);
        q('UPDATE {user} SET `phone`=? WHERE `id`=?', [$cut('phone', 32), (int) $S['id']]);
        $nd = ['student' => (string) (int) $S['id']];
        foreach (array_merge(array_keys($fields), ['family', 'dwelling', 'travel', 'note']) as $k) {
            $nd[$k] = $cut($k, 250);
        }
        md_set('sa_profile', $key, '', '', json_encode($nd, JSON_UNESCAPED_UNICODE));
        st_done('profile');
    }
    $o = st_saved().'<div class="grid2">'.st_in('phone', $S['phone'], 'โทรศัพท์นักเรียน').st_in('parent', $S['parent'], 'ชื่อผู้ปกครอง').st_in('parent_phone', $S['parent_phone'], 'โทรศัพท์ผู้ปกครอง')
        .st_sel('family', ['อยู่ด้วยกัน' => 'บิดามารดาอยู่ด้วยกัน', 'แยกกันอยู่' => 'แยกกันอยู่', 'หย่าร้าง' => 'หย่าร้าง', 'เสียชีวิต' => 'บิดา/มารดาเสียชีวิต', 'อื่น ๆ' => 'อื่น ๆ'], $d['family'] ?? '', 'สถานภาพครอบครัว')
        .st_sel('dwelling', md_options('dwelling', 'name'), $d['dwelling'] ?? '', 'ที่พักอาศัย').st_sel('travel', md_options('travel', 'name'), $d['travel'] ?? '', 'การเดินทางมาโรงเรียน');
    foreach ($fields as $k => $l) {
        $o .= st_in($k, $d[$k] ?? '', $l);
    }
    return st_form('profile', $o.'</div>'.st_in('address', $S['address'], 'ที่อยู่ปัจจุบัน', 'textarea').st_in('note', $d['note'] ?? '', 'ข้อมูลอื่น ๆ ที่อยากให้ครูทราบ', 'textarea'));
}

/* ================================================================ 2. SDQ ฉบับนักเรียนประเมินตนเอง */

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

function st_sdq(array $S, bool $post): string
{
    $sid = (int) $S['id'];
    $old = json_decode((string) md_get('sdq', $sid, yt()), true) ?: [];
    $allow = !$old || md_get('sdq_allow', $sid, yt()) === '1';
    $choices = ['0' => 'ไม่จริง', '1' => 'ค่อนข้างจริง', '2' => 'จริง'];
    if ($post && $allow && ($a = st_answers(SDQ_ITEMS, $choices))) {
        $sc = sdq_score($a);
        md_set('sdq', $sid, yt(), '', json_encode(['date' => date('Y-m-d'), 'answers' => $a, 'scores' => $sc], JSON_UNESCAPED_UNICODE));
        // ให้ครูเห็นในหน้า "ประเมิน SDQ" ของฝ่ายกิจการนักเรียนด้วย
        md_set('sa_sdq', 's'.$sid, yt(), '', json_encode(['student' => (string) $sid, 'date' => date('Y-m-d'), 'score' => (string) $sc['total'], 'note' => 'นักเรียนประเมินตนเอง: '.sdq_level($sc['total'])[0]], JSON_UNESCAPED_UNICODE));
        st_done('sdq');
    }
    $res = '';
    if ($old) {
        $sc = $old['scores'];
        [$lv, $cls] = sdq_level((int) $sc['total']);
        $rows = '';
        foreach (SDQ_SCALES as $k => [$l]) {
            $rows .= '<tr><td class="l">'.h($l).'</td><td>'.(int) $sc[$k].'</td></tr>';
        }
        $res = '<div class="msg ok">ประเมินแล้วเมื่อ '.th_date($old['date'], ' ').' — คะแนนรวมปัญหา <b>'.(int) $sc['total'].'</b> <span class="lv '.$cls.'">'.$lv.'</span></div><table class="t"><tr><th>ด้าน</th><th>คะแนน</th></tr>'.$rows.'</table>';
    }
    if (!$allow) {
        return st_saved().$res.'<p style="color:#777">หากต้องการทำแบบประเมินใหม่ กรุณาติดต่อครูที่ปรึกษาเพื่อเปิดสิทธิ์การแก้ไข</p>';
    }
    return st_saved().$res.'<p>อ่านข้อความแต่ละข้อแล้วเลือกคำตอบที่ตรงกับตัวนักเรียนมากที่สุด ในช่วง 6 เดือนที่ผ่านมา</p>'.st_form('sdq', st_questions(SDQ_ITEMS, $choices, $old['answers'] ?? []), 'ส่งแบบประเมิน');
}

/* ================================================================ 3. สมรรถนะสำคัญ (ประเมินตนเอง) */

const CAP_ITEMS = [
    'c1' => ['ความสามารถในการสื่อสาร', ['ฉันพูด อ่าน เขียน เพื่อถ่ายทอดความคิดของตนเองได้ชัดเจน', 'ฉันรับฟังและเข้าใจสิ่งที่ผู้อื่นสื่อสาร', 'ฉันใช้วิธีสื่อสารที่เหมาะสมและสุภาพ']],
    'c2' => ['ความสามารถในการคิด', ['ฉันคิดวิเคราะห์ แยกแยะข้อมูลได้', 'ฉันคิดสร้างสรรค์และหาแนวทางใหม่ ๆ', 'ฉันใช้เหตุผลในการตัดสินใจ']],
    'c3' => ['ความสามารถในการแก้ปัญหา', ['ฉันเข้าใจสาเหตุของปัญหาที่เกิดขึ้น', 'ฉันหาวิธีแก้ปัญหาได้หลายวิธี', 'ฉันแก้ปัญหาโดยคำนึงถึงผลที่ตามมา']],
    'c4' => ['ความสามารถในการใช้ทักษะชีวิต', ['ฉันทำงานร่วมกับผู้อื่นได้ดี', 'ฉันจัดการอารมณ์และความเครียดของตนเองได้', 'ฉันหลีกเลี่ยงพฤติกรรมที่ไม่พึงประสงค์']],
    'c5' => ['ความสามารถในการใช้เทคโนโลยี', ['ฉันใช้เทคโนโลยีค้นหาข้อมูลเพื่อการเรียนรู้', 'ฉันใช้เทคโนโลยีสร้างสรรค์ผลงาน', 'ฉันใช้เทคโนโลยีอย่างปลอดภัยและมีคุณธรรม']],
];

function st_cap(array $S, bool $post): string
{
    $sid = (int) $S['id'];
    $items = [];
    foreach (CAP_ITEMS as $k => [$cap, $list]) {
        foreach ($list as $i => $t) {
            $items[$k.'_'.$i] = $t;
        }
    }
    $choices = ['3' => 'ดีเยี่ยม', '2' => 'ดี', '1' => 'ผ่าน', '0' => 'ยังไม่ผ่าน'];
    if ($post && ($a = st_answers($items, $choices))) {
        md_set('st_cap', $sid, yt(), '', json_encode(['date' => date('Y-m-d'), 'answers' => $a], JSON_UNESCAPED_UNICODE));
        st_done('cap');
    }
    $old = json_decode((string) md_get('st_cap', $sid, yt()), true) ?: [];
    $res = '';
    if ($old) {
        $rows = '';
        foreach (CAP_ITEMS as $k => [$cap]) {
            $v = array_map(fn($i) => (int) ($old['answers'][$k.'_'.$i] ?? 0), [0, 1, 2]);
            $avg = array_sum($v) / 3;
            $rows .= '<tr><td class="l">'.h($cap).'</td><td>'.number_format($avg, 2).'</td><td class="lv">'.($avg >= 2.5 ? 'ดีเยี่ยม' : ($avg >= 1.5 ? 'ดี' : ($avg >= 1 ? 'ผ่าน' : 'ไม่ผ่าน'))).'</td></tr>';
        }
        $res = '<div class="msg ok">ประเมินล่าสุด '.th_date($old['date'], ' ').'</div><table class="t"><tr><th>สมรรถนะ</th><th>เฉลี่ย</th><th>ระดับ</th></tr>'.$rows.'</table>';
    }
    $q = '';
    foreach (CAP_ITEMS as $k => [$cap, $list]) {
        $sub = [];
        foreach ($list as $i => $t) {
            $sub[$k.'_'.$i] = $t;
        }
        $q .= '<h3 style="margin:14px 0 2px;font-family:thaisansbold;font-weight:normal;font-size:22px;color:#8E44AD">'.h($cap).'</h3>'.st_questions($sub, $choices, $old['answers'] ?? []);
    }
    return st_saved().$res.st_form('cap', $q, 'ส่งแบบประเมิน');
}

/* ================================================================ 4. แบบสอบถามวิเคราะห์ผู้เรียน (กราชา / คอล์บ) */

const GRASHA = [
    'independent' => ['แบบอิสระ', ['ฉันชอบคิดและเรียนรู้ด้วยตนเอง', 'ฉันมั่นใจในความสามารถในการเรียนของตนเอง', 'ฉันชอบเลือกเรื่องที่จะศึกษาเอง']],
    'avoidant' => ['แบบหลีกเลี่ยง', ['ฉันไม่ค่อยสนใจสิ่งที่เรียนในห้อง', 'ฉันไม่อยากมีส่วนร่วมกับกิจกรรมในชั้นเรียน', 'ฉันมักเบื่อเมื่ออยู่ในห้องเรียน']],
    'collaborative' => ['แบบร่วมมือ', ['ฉันชอบเรียนรู้โดยการแลกเปลี่ยนความคิดกับเพื่อน', 'ฉันชอบทำงานกลุ่ม', 'ฉันคิดว่าการช่วยเหลือกันทำให้เรียนรู้ได้ดี']],
    'dependent' => ['แบบพึ่งพา', ['ฉันชอบให้ครูบอกว่าต้องทำอะไรบ้าง', 'ฉันเรียนเฉพาะสิ่งที่ครูกำหนด', 'ฉันชอบมีแนวทางที่ชัดเจนก่อนลงมือทำงาน']],
    'competitive' => ['แบบแข่งขัน', ['ฉันอยากทำได้ดีกว่าเพื่อนในชั้นเรียน', 'ฉันชอบได้รับคำชมจากครูมากกว่าเพื่อน', 'ฉันชอบกิจกรรมที่มีการแข่งขัน']],
    'participant' => ['แบบมีส่วนร่วม', ['ฉันชอบเข้าร่วมกิจกรรมในชั้นเรียนให้มากที่สุด', 'ฉันตั้งใจทำงานที่ได้รับมอบหมายทุกชิ้น', 'ฉันชอบซักถามและแสดงความคิดเห็นในห้องเรียน']],
];
const KOLB = [
    ['เมื่อเรียนสิ่งใหม่ ฉันชอบ', ['ce' => 'ลงมือสัมผัสกับประสบการณ์จริง', 'ro' => 'สังเกตและฟังก่อน', 'ac' => 'ทำความเข้าใจหลักการและเหตุผล', 'ae' => 'ทดลองทำดูเลย']],
    ['ฉันเรียนได้ดีที่สุดเมื่อ', ['ce' => 'ได้ใช้ความรู้สึกและความสัมพันธ์กับผู้อื่น', 'ro' => 'ได้ไตร่ตรองอย่างรอบคอบ', 'ac' => 'ได้คิดอย่างมีเหตุผล', 'ae' => 'ได้ลงมือปฏิบัติ']],
    ['เวลาทำงาน ฉันมักจะ', ['ce' => 'ทำตามความรู้สึก', 'ro' => 'พิจารณาหลายมุมมองก่อน', 'ac' => 'วางแผนอย่างเป็นระบบ', 'ae' => 'รีบทำให้เห็นผล']],
    ['ฉันเชื่อมั่นใน', ['ce' => 'ความรู้สึกของตนเอง', 'ro' => 'การสังเกตของตนเอง', 'ac' => 'ความคิดของตนเอง', 'ae' => 'การกระทำของตนเอง']],
    ['ในชั้นเรียน ฉันชอบ', ['ce' => 'กิจกรรมที่เกี่ยวกับชีวิตจริง', 'ro' => 'การอภิปรายและฟังความเห็น', 'ac' => 'การบรรยายเนื้อหาและทฤษฎี', 'ae' => 'การทดลองและทำโครงงาน']],
    ['ฉันเป็นคนที่', ['ce' => 'เปิดรับสิ่งใหม่ ๆ', 'ro' => 'สุขุม รอบคอบ', 'ac' => 'มีเหตุผล', 'ae' => 'กระตือรือร้น ชอบลงมือ']],
    ['เมื่อแก้ปัญหา ฉันมัก', ['ce' => 'ถามความคิดเห็นของคนรอบข้าง', 'ro' => 'รวบรวมข้อมูลให้ครบก่อน', 'ac' => 'วิเคราะห์หาสาเหตุอย่างเป็นขั้นตอน', 'ae' => 'ลองหลายวิธีจนกว่าจะได้ผล']],
    ['ฉันจำได้ดีเมื่อ', ['ce' => 'มีประสบการณ์ตรง', 'ro' => 'ได้ดูตัวอย่าง', 'ac' => 'เข้าใจหลักการ', 'ae' => 'ได้ฝึกทำซ้ำ']],
];
const KOLB_STYLES = ['diverging' => 'แบบคิดอเนกนัย (Diverging) ช่างคิด ช่างจินตนาการ มองหลายมุม', 'assimilating' => 'แบบซึมซับ (Assimilating) ชอบหลักการ ทฤษฎี คิดเป็นระบบ',
    'converging' => 'แบบคิดเอกนัย (Converging) นำทฤษฎีไปใช้แก้ปัญหาจริง', 'accommodating' => 'แบบปรับปรุง (Accommodating) ชอบลงมือทำ ทดลอง เรียนจากประสบการณ์'];

function st_analyze(array $S, bool $post): string
{
    $sid = (int) $S['id'];
    $kind = ($_GET['k'] ?? '') === 'kolb' ? 'kolb' : (($_GET['k'] ?? '') === 'grasha' ? 'grasha' : '');
    $done = ['grasha' => json_decode((string) md_get('grasha', $sid, yt()), true) ?: [], 'kolb' => json_decode((string) md_get('kolb', $sid, yt()), true) ?: []];
    if ($kind === '') {
        $o = st_saved().'<table class="t"><tr><th>แบบสอบถาม</th><th>ผล</th><th></th></tr>';
        foreach (['grasha' => 'วิเคราะห์ผู้เรียนตามแนวคิดของกราชา (รูปแบบการเรียน)', 'kolb' => 'วิเคราะห์ผู้เรียนตามแนวคิดของคอล์บ (วิธีการเรียนรู้)'] as $k => $l) {
            $d = $done[$k];
            $o .= '<tr><td class="l">'.h($l).'</td><td>'.($d ? '<span class="c-ok">'.h($d['result_text'] ?? '').'</span>' : '<span style="color:#999">ยังไม่ทำ</span>').'</td><td><a class="btn" style="padding:4px 12px;font-size:13px" href="?p=analyze&k='.$k.'">'.($d ? 'ดูผล/ทำใหม่' : 'ทำแบบสอบถาม').'</a></td></tr>';
        }
        return $o.'</table><p style="color:#777">ผลวิเคราะห์ด้านพหุปัญญาบันทึกโดยครูแนะแนว/ครูที่ปรึกษา</p>';
    }
    $allow = !$done[$kind] || md_get($kind.'_allow', $sid, yt()) === '1';
    if ($kind === 'grasha') {
        $items = [];
        foreach (GRASHA as $k => [$l, $list]) {
            foreach ($list as $i => $t) {
                $items[$k.'_'.$i] = $t;
            }
        }
        $ch = ['5' => 'มากที่สุด', '4' => 'มาก', '3' => 'ปานกลาง', '2' => 'น้อย', '1' => 'น้อยที่สุด'];
        if ($post && $allow && ($a = st_answers($items, $ch))) {
            $sc = [];
            foreach (GRASHA as $k => [$l]) {
                $sc[$k] = array_sum(array_map(fn($i) => (int) $a[$k.'_'.$i], [0, 1, 2]));
            }
            arsort($sc);
            $top = array_key_first($sc);
            md_set('grasha', $sid, yt(), '', json_encode(['date' => date('Y-m-d'), 'answers' => $a, 'scores' => $sc, 'result' => $top, 'result_text' => GRASHA[$top][0]], JSON_UNESCAPED_UNICODE));
            st_done('analyze');
        }
        $q = st_questions($items, $ch, $done['grasha']['answers'] ?? []);
    } else {
        $items = [];
        foreach (KOLB as $i => [$t]) {
            $items['k'.$i] = $t;
        }
        if ($post && $allow) {
            $a = [];
            foreach (KOLB as $i => [$t, $opts]) {
                $v = (string) ($_POST['a']['k'.$i] ?? '');
                if (!isset($opts[$v])) {
                    $a = null;
                    break;
                }
                $a['k'.$i] = $v;
            }
            if ($a) {
                $c = array_count_values($a) + ['ce' => 0, 'ro' => 0, 'ac' => 0, 'ae' => 0];
                $style = $c['ac'] - $c['ce'] >= 0 ? ($c['ae'] - $c['ro'] >= 0 ? 'converging' : 'assimilating') : ($c['ae'] - $c['ro'] >= 0 ? 'accommodating' : 'diverging');
                md_set('kolb', $sid, yt(), '', json_encode(['date' => date('Y-m-d'), 'answers' => $a, 'scores' => $c, 'result' => $style, 'result_text' => explode(' (', KOLB_STYLES[$style])[0]], JSON_UNESCAPED_UNICODE));
                st_done('analyze');
            }
        }
        $q = '';
        foreach (KOLB as $i => [$t, $opts]) {
            $q .= '<div class="q"><div class="tx">'.($i + 1).'. '.h($t).'</div>';
            foreach ($opts as $v => $l) {
                $q .= '<label style="display:block"><input type="radio" name="a[k'.$i.']" value="'.$v.'"'.(($done['kolb']['answers']['k'.$i] ?? '') === $v ? ' checked' : '').' required> '.h($l).'</label>';
            }
            $q .= '</div>';
        }
    }
    $d = $done[$kind];
    $res = $d ? '<div class="msg ok">ผลการวิเคราะห์ ('.th_date($d['date'], ' ').'): <b>'.h($kind === 'kolb' ? KOLB_STYLES[$d['result']] : $d['result_text']).'</b></div>' : '';
    $title = $kind === 'grasha' ? 'แบบสอบถามรูปแบบการเรียนตามแนวคิดของกราชา' : 'แบบสอบถามวิธีการเรียนรู้ตามแนวคิดของคอล์บ';
    $body = '<a class="back" href="?p=analyze"><i class="fa fa-chevron-left"></i> แบบสอบถามทั้งหมด</a><h3 style="font-family:thaisansbold;font-weight:normal;font-size:24px;margin:4px 0">'.h($title).'</h3>'.$res;
    if (!$allow) {
        return $body.'<p style="color:#777">ทำแบบสอบถามแล้ว หากต้องการทำใหม่ กรุณาติดต่อครูที่ปรึกษาเพื่อเปิดสิทธิ์การแก้ไข</p>';
    }
    return $body.'<form method="post" action="?p=analyze&k='.$kind.'">'.$q.'<p style="margin-top:14px"><button class="btn" type="submit"><i class="fa fa-save"></i> ส่งแบบสอบถาม</button></p></form>';
}

/* ================================================================ 5. ความฉลาดทางอารมณ์ (แบบประเมินตนเองฉบับย่อ) */

const EQ_PARTS = [
    'good' => ['ดี (ควบคุมอารมณ์ เห็นใจผู้อื่น รับผิดชอบ)', ['เวลาโกรธหรือไม่สบายใจ ฉันรู้สาเหตุและควบคุมตัวเองได้', 'ฉันรอคอยได้ เมื่อยังไม่ถึงเวลาที่ควรได้สิ่งที่ต้องการ', 'ฉันเข้าใจความรู้สึกของเพื่อนเมื่อเขาไม่สบายใจ', 'ฉันยอมรับผิดเมื่อทำผิด', 'ฉันทำหน้าที่ของตนเองจนสำเร็จ']],
    'smart' => ['เก่ง (มีแรงจูงใจ แก้ปัญหา มีสัมพันธภาพ)', ['เมื่อทำสิ่งใดไม่สำเร็จ ฉันพยายามใหม่จนกว่าจะทำได้', 'ฉันมีเป้าหมายในการเรียนของตนเอง', 'เมื่อมีปัญหา ฉันหาทางแก้ไขอย่างมีเหตุผล', 'ฉันกล้าบอกความต้องการของตนเองอย่างเหมาะสม', 'ฉันเข้ากับเพื่อนใหม่ได้ง่าย']],
    'happy' => ['สุข (ภูมิใจในตนเอง พอใจในชีวิต สุขสงบทางใจ)', ['ฉันรู้สึกภูมิใจในตัวเอง', 'ฉันรู้สึกพอใจกับสิ่งที่ตนเองมี', 'ฉันมองโลกในแง่ดี', 'ฉันมีวิธีผ่อนคลายเมื่อรู้สึกเครียด', 'ฉันมีความสุขกับการใช้ชีวิตในแต่ละวัน']],
];

function eq_level(float $pct): array
{
    return $pct >= 80 ? ['สูงกว่าเกณฑ์', 'c-ok'] : ($pct >= 60 ? ['อยู่ในเกณฑ์ปกติ', 'c-ok'] : ['ควรได้รับการพัฒนา', 'c-bad']);
}

function st_eq(array $S, bool $post): string
{
    $sid = (int) $S['id'];
    $items = [];
    foreach (EQ_PARTS as $k => [$l, $list]) {
        foreach ($list as $i => $t) {
            $items[$k.'_'.$i] = $t;
        }
    }
    $ch = ['4' => 'จริงมาก', '3' => 'ค่อนข้างจริง', '2' => 'จริงบางครั้ง', '1' => 'ไม่จริง'];
    if ($post && ($a = st_answers($items, $ch))) {
        $sc = [];
        foreach (EQ_PARTS as $k => [$l, $list]) {
            $sc[$k] = round(array_sum(array_map(fn($i) => (int) $a[$k.'_'.$i], array_keys($list))) * 100 / (4 * count($list)), 1);
        }
        md_set('st_eq', $sid, yt(), '', json_encode(['date' => date('Y-m-d'), 'answers' => $a, 'scores' => $sc], JSON_UNESCAPED_UNICODE));
        st_done('eq');
    }
    $old = json_decode((string) md_get('st_eq', $sid, yt()), true) ?: [];
    $res = '';
    if ($old) {
        $rows = '';
        foreach (EQ_PARTS as $k => [$l]) {
            [$lv, $c] = eq_level((float) $old['scores'][$k]);
            $rows .= '<tr><td class="l">'.h($l).'</td><td>'.number_format((float) $old['scores'][$k], 1).'%</td><td class="lv '.$c.'">'.$lv.'</td></tr>';
        }
        $res = '<div class="msg ok">ประเมินล่าสุด '.th_date($old['date'], ' ').'</div><table class="t"><tr><th>ด้าน</th><th>ร้อยละ</th><th>ผล</th></tr>'.$rows.'</table>';
    }
    $q = '';
    foreach (EQ_PARTS as $k => [$l, $list]) {
        $sub = [];
        foreach ($list as $i => $t) {
            $sub[$k.'_'.$i] = $t;
        }
        $q .= '<h3 style="margin:14px 0 2px;font-family:thaisansbold;font-weight:normal;font-size:22px;color:#E74C3C">'.h($l).'</h3>'.st_questions($sub, $ch, $old['answers'] ?? []);
    }
    return st_saved().$res.'<p>แบบประเมินตนเองฉบับย่อ 3 ด้าน (ดี เก่ง สุข) ตอบตามความเป็นจริงของตนเอง</p>'.st_form('eq', $q, 'ส่งแบบประเมิน');
}

/* ================================================================ 6. เวลาเรียน */

const ATT_TH = ['present' => 'มา', 'late' => 'สาย', 'leave' => 'ลา', 'sick' => 'ป่วย', 'absent' => 'ขาด', 'activity' => 'กิจกรรม', 'skip' => 'โดดเรียน', 'remedial' => 'ซ่อมเสริม'];

function st_att(array $S, bool $post): string
{
    $sid = (int) $S['id'];
    [$y, $t] = term();
    $rows = '';
    $cnt = [];
    try {
        foreach (q('SELECT A.`course_id`, A.`status`, COUNT(*) n FROM {attendance} A JOIN {course} C ON C.`id`=A.`course_id` WHERE A.`student_id`=? AND C.`year`=? AND C.`term`=? GROUP BY A.`course_id`, A.`status`', [$sid, $y, $t]) as $r) {
            $cnt[(int) $r['course_id']][$r['status']] = (int) $r['n'];
        }
    } catch (\Throwable $e) {
        $cnt = [];
    }
    $courses = courses_by_id(array_keys($cnt));
    foreach ($cnt as $cid => $c) {
        $total = array_sum($c);
        $present = ($c['present'] ?? 0) + ($c['late'] ?? 0) + ($c['activity'] ?? 0) + ($c['remedial'] ?? 0);
        $pct = $total ? $present * 100 / $total : 0;
        $rows .= '<tr><td class="l">'.h(($courses[$cid]['course_code'] ?? '').' '.($courses[$cid]['course_name'] ?? '')).'</td><td>'.$total.'</td><td>'.$present.'</td><td>'.(int) ($c['late'] ?? 0).'</td><td>'.((int) ($c['leave'] ?? 0) + (int) ($c['sick'] ?? 0)).'</td><td>'.((int) ($c['absent'] ?? 0) + (int) ($c['skip'] ?? 0)).'</td>'
            .'<td class="lv '.($pct < 80 ? 'c-bad' : 'c-ok').'">'.number_format($pct, 2).'%</td></tr>';
    }
    [$from, $to] = term_range();
    $sa = school_att([$sid], $from, $to)[$sid] ?? [];
    $sc = array_count_values($sa);
    $s2 = '';
    foreach (SCHOOL_ATT as $k => $l) {
        $s2 .= '<td>'.(int) ($sc[$k] ?? 0).'</td>';
    }
    return '<h3 style="font-family:thaisansbold;font-weight:normal;font-size:22px;margin:0">เวลาเรียนรายวิชา ภาคเรียน '.yt().'</h3>'
        .'<table class="t"><tr><th>รายวิชา</th><th>คาบที่เช็ค</th><th>มาเรียน</th><th>สาย</th><th>ลา/ป่วย</th><th>ขาด</th><th>ร้อยละ</th></tr>'.($rows ?: '<tr><td colspan="7">ยังไม่มีการบันทึกเวลาเรียน</td></tr>').'</table>'
        .'<p style="color:#777">นักเรียนต้องมีเวลาเรียนไม่น้อยกว่าร้อยละ 80 จึงมีสิทธิ์สอบ</p>'
        .'<h3 style="font-family:thaisansbold;font-weight:normal;font-size:22px;margin:10px 0 0">การมาโรงเรียน (ครูที่ปรึกษาบันทึก)</h3><table class="t"><tr>'.implode('', array_map(fn($l) => '<th>'.h($l).'</th>', SCHOOL_ATT)).'</tr><tr>'.$s2.'</tr></table>';
}

/* ================================================================ 7. ผลการเรียน / สอบแก้ตัว */

function st_grade(array $S, bool $post): string
{
    $sid = (int) $S['id'];
    $terms = [];
    foreach (student_grades($sid) as $g) {
        $terms[$g['year'].'/'.$g['term']] = 'ภาคเรียนที่ '.$g['term'].'/'.$g['year'];
    }
    // พิมพ์ใบรายงานผลการเรียน (ใบเกรด) ของตนเอง
    if (isset($_GET['print']) && isset($terms[$_GET['yt'] ?? ''])) {
        [$y, $t] = rpt_term();
        rpt_card([$S], $y, $t, ($_GET['mode'] ?? '') === 'year' ? 'year' : 'term');
    }
    $html = str_replace('class="mis-gt"', 'class="t"', grade_table(student_grades($sid)));
    if ($terms) {
        krsort($terms);
        $html = '<form method="get" target="_blank" style="margin:0 0 10px;padding:8px 10px;background:#f4f8fb;border:1px solid #d6e4ee;border-radius:6px"><input type="hidden" name="p" value="grade"/><input type="hidden" name="print" value="1"/>'
            .'<b>พิมพ์ใบรายงานผลการเรียน (ใบเกรด)</b> &nbsp; '.sel('yt', $terms, array_key_first($terms)).' '.sel('mode', ['term' => 'รายภาคเรียน', 'year' => 'ทั้งปีการศึกษา'], 'term').' <button type="submit" style="cursor:pointer"><i class="fa fa-print"></i> พิมพ์</button></form>'.$html;
    }
    $courses = [];
    foreach (q('SELECT `r2`, `value` FROM {mis_data} WHERE `kind`=\'remedial\'') as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        if ((int) ($d['student'] ?? 0) === $sid) {
            $courses[] = (int) ($d['course'] ?? 0);
        }
    }
    $cs = courses_by_id($courses);
    $rr = '';
    foreach (q('SELECT `r2`, `value` FROM {mis_data} WHERE `kind`=\'remedial\'') as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        if ((int) ($d['student'] ?? 0) === $sid) {
            $c = $cs[(int) ($d['course'] ?? 0)] ?? null;
            $rr .= '<tr><td>'.h($r['r2']).'</td><td class="l">'.h($c ? $c['course_code'].' '.$c['course_name'] : '-').'</td><td>'.h($d['old'] ?? '').'</td><td class="lv">'.h($d['new'] ?? '').'</td><td>'.(!empty($d['date']) ? th_date($d['date'], ' ') : '').'</td></tr>';
        }
    }
    return '<h3 style="font-family:thaisansbold;font-weight:normal;font-size:22px;margin:0">ผลการเรียนทุกภาคเรียน</h3>'.$html
        .'<h3 style="font-family:thaisansbold;font-weight:normal;font-size:22px;margin:12px 0 0">ผลการสอบแก้ตัว</h3><table class="t"><tr><th>ภาคเรียน</th><th>รายวิชา</th><th>ผลเดิม</th><th>ผลแก้ตัว</th><th>วันที่</th></tr>'.($rr ?: '<tr><td colspan="5">ไม่มีรายการสอบแก้ตัว</td></tr>').'</table>';
}

/* ================================================================ 8. ทุนการศึกษา */

function st_fund(array $S, bool $post): string
{
    $sid = (int) $S['id'];
    $funds = md_options('sa_fund', 'name');
    $got = '';
    foreach (q('SELECT `r2`, `value` FROM {mis_data} WHERE `kind`=\'sa_fund_student\' ORDER BY `r2` DESC') as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        if ((int) ($d['student'] ?? 0) === $sid) {
            $got .= '<tr><td>'.h($r['r2']).'</td><td class="l">'.h($funds[$d['fund'] ?? ''] ?? '-').'</td><td>'.(!empty($d['date']) ? th_date($d['date'], ' ') : '').'</td><td>'.($d['amount'] !== '' && isset($d['amount']) ? number_format((float) $d['amount'], 2) : '-').'</td></tr>';
        }
    }
    $nom = '';
    foreach (q('SELECT `r2`, `value` FROM {mis_data} WHERE `kind`=\'sa_fund_nom\' ORDER BY `r2` DESC') as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        if ((int) ($d['student'] ?? 0) === $sid) {
            $p = $d['pick'] ?? '';
            $nom .= '<tr><td>'.h($r['r2']).'</td><td class="l">'.h($funds[$d['fund'] ?? ''] ?? '-').'</td><td class="lv '.($p === 'ได้รับ' ? 'c-ok' : ($p === 'ไม่ได้รับ' ? 'c-bad' : '')).'">'.h($p === 'ได้รับ' ? 'ได้รับทุน' : ($p === 'ไม่ได้รับ' ? 'ไม่ได้รับทุน' : 'รอการคัดเลือก')).'</td></tr>';
        }
    }
    $open = '';
    foreach (md_rows('sa_fund') as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        $open .= '<tr><td class="l">'.h($d['name'] ?? '').'</td><td>'.h($d['sponsor'] ?? '').'</td><td>'.(!empty($d['amount']) ? number_format((float) $d['amount'], 2) : '-').'</td><td class="l">'.h($d['condition'] ?? '').'</td></tr>';
    }
    return '<h3 style="font-family:thaisansbold;font-weight:normal;font-size:22px;margin:0">ทุนที่ได้รับ</h3><table class="t"><tr><th>ปีการศึกษา</th><th>ทุน</th><th>วันที่ได้รับ</th><th>จำนวนเงิน</th></tr>'.($got ?: '<tr><td colspan="4">ยังไม่มีข้อมูล</td></tr>').'</table>'
        .'<h3 style="font-family:thaisansbold;font-weight:normal;font-size:22px;margin:12px 0 0">การเสนอชื่อรับทุน</h3><table class="t"><tr><th>ปีการศึกษา</th><th>ทุน</th><th>ผล</th></tr>'.($nom ?: '<tr><td colspan="3">ไม่มีการเสนอชื่อ</td></tr>').'</table>'
        .'<h3 style="font-family:thaisansbold;font-weight:normal;font-size:22px;margin:12px 0 0">ทุนการศึกษาของโรงเรียน</h3><table class="t"><tr><th>ชื่อทุน</th><th>ผู้ให้ทุน</th><th>บาท/ทุน</th><th>คุณสมบัติ</th></tr>'.($open ?: '<tr><td colspan="4">-</td></tr>').'</table>';
}

/* ================================================================ 9. ลงทะเบียนชุมนุม */

function club_counts(): array
{
    $c = [];
    foreach (md_rows('club_reg', null, yt()) as $r) {
        $c[(string) $r['value']] = ($c[(string) $r['value']] ?? 0) + 1;
    }
    return $c;
}

function st_club(array $S, bool $post): string
{
    $sid = (int) $S['id'];
    $clubs = [];
    foreach (md_rows('club', null, yt()) as $r) {
        $clubs[(string) $r['r1']] = json_decode((string) $r['value'], true) ?: [];
    }
    $mine = (string) md_get('club_reg', $sid, yt());
    $open = md_get('cfg', 'club_open') !== '0';
    if ($post && $open) {
        $cid = (string) ($_POST['club'] ?? '');
        $cnt = club_counts();
        if (isset($clubs[$cid])) {
            $d = $clubs[$cid];
            $okClass = empty($d['class']) || (string) $d['class'] === '0' || (string) $d['class'] === (string) $S['class'];
            $seats = (int) ($d['seats'] ?? 0);
            if ($okClass && ($cid === $mine || !$seats || ($cnt[$cid] ?? 0) < $seats)) {
                md_set('club_reg', $sid, yt(), '', $cid);
                st_done('club');
            }
        }
        return '<div class="msg no">ลงทะเบียนไม่สำเร็จ ชุมนุมนี้เต็มแล้วหรือไม่เปิดรับระดับชั้นของนักเรียน</div>'.st_club($S, false);
    }
    $cnt = club_counts();
    $po = personnel_options();
    $rows = '';
    foreach ($clubs as $k => $d) {
        $k = (string) $k;
        $seats = (int) ($d['seats'] ?? 0);
        $n = (int) ($cnt[$k] ?? 0);
        $okClass = empty($d['class']) || (string) $d['class'] === '0' || (string) $d['class'] === (string) $S['class'];
        $full = $seats && $n >= $seats && $k !== $mine;
        $rows .= '<tr'.($k === $mine ? ' style="background:#E9F7EF"' : '').'><td>'.($k === $mine ? '<i class="fa fa-check c-ok"></i>' : ($open && $okClass && !$full ? '<input type="radio" name="club" value="'.h($k).'" required>' : '')).'</td>'
            .'<td class="l"><b>'.h($d['name'] ?? '').'</b>'.(!empty($d['detail']) ? '<br><small>'.h($d['detail']).'</small>' : '').'</td><td>'.h($po[$d['teacher'] ?? ''] ?? '-').'</td><td>'.h($d['place'] ?? '').'</td>'
            .'<td>'.$n.($seats ? '/'.$seats : '').'</td><td>'.(!$okClass ? 'ไม่เปิดรับชั้นนี้' : ($full ? '<span class="c-bad">เต็ม</span>' : 'ว่าง')).'</td></tr>';
    }
    $head = $mine && isset($clubs[$mine]) ? '<div class="msg ok">นักเรียนลงทะเบียนชุมนุม <b>'.h($clubs[$mine]['name'] ?? '').'</b> แล้ว'.($open ? ' (เปลี่ยนชุมนุมได้จนกว่าจะปิดรับสมัคร)' : '').'</div>' : '';
    $table = '<table class="t"><tr><th></th><th>ชุมนุม</th><th>ครูที่ปรึกษาชุมนุม</th><th>สถานที่</th><th>สมัคร</th><th>สถานะ</th></tr>'.($rows ?: '<tr><td colspan="6">ยังไม่มีชุมนุมเปิดรับสมัครในภาคเรียนนี้</td></tr>').'</table>';
    if (!$open) {
        return st_saved().$head.'<div class="msg no">ปิดรับสมัครชุมนุมแล้ว</div>'.$table;
    }
    return st_saved().$head.($rows ? st_form('club', $table, $mine ? 'เปลี่ยนชุมนุม' : 'ลงทะเบียน') : $table);
}
