<?php
/**
 * mis/app/saraban.php
 * งานสารบรรณ (โมดูลบริหารงานทั่วไป): ออกหนังสือราชการ + ทะเบียนหนังสือส่ง
 * รูปแบบตามระเบียบสำนักนายกรัฐมนตรีว่าด้วยงานสารบรรณ พ.ศ. ๒๕๒๖ และที่แก้ไขเพิ่มเติม
 *   หนังสือภายนอก / บันทึกข้อความ (หนังสือภายใน) / หนังสือประทับตรา / คำสั่ง / ประกาศ / หนังสือรับรอง / คำร้อง
 *   ชั้นความเร็ว ปกติ / ด่วน / ด่วนมาก / ด่วนที่สุด (ตัวอักษรสีแดง ๓๒ พอยท์)
 *   กระดาษ A4 ขอบบน ๒.๕ ซม. (หน้าแรกครุฑห่างขอบบน ๑.๕ ซม.) ซ้าย ๓ ซม. ขวา ๒ ซม. ล่าง ๒ ซม.
 *   ฟอนต์ TH Sarabun New ๑๖ พอยท์ ย่อหน้า ๒.๕ ซม. ครุฑสูง ๓ ซม. (บันทึกข้อความ ๑.๕ ซม.)
 *   เลขหน้าตั้งแต่หน้า ๒ กึ่งกลางด้านบน "- ๒ -"
 * ข้อมูล: {mis_data} kind=sb_doc (r1=id, r2=ปี พ.ศ.), kind=sb_cfg (r1=main)
 * ตราครุฑ: datas/school/garuda.(png|jpg|gif) อัปโหลดที่ "ตั้งค่างานสารบรรณ"
 */
defined('MIS') or exit;

/** ประเภทหนังสือ => [ชื่อ, ทะเบียนเลขที่ (ใช้เลขชุดเดียวกัน)] */
const SB_TYPES = [
    'ext' => ['หนังสือภายนอก', 'out'],
    'memo' => ['บันทึกข้อความ (หนังสือภายใน)', 'memo'],
    'stamp' => ['หนังสือประทับตรา', 'out'],
    'order' => ['คำสั่ง', 'order'],
    'announce' => ['ประกาศ', 'announce'],
    'cert' => ['หนังสือรับรอง', 'out'],
    'petition' => ['คำร้อง', ''],
];

const SB_URGENCY = ['' => 'ปกติ', 'ด่วน' => 'ด่วน', 'ด่วนมาก' => 'ด่วนมาก', 'ด่วนที่สุด' => 'ด่วนที่สุด'];

/** ช่องในฟอร์มที่แต่ละประเภทใช้ */
const SB_FIELDS = [
    'no' => ['ext', 'memo', 'stamp', 'order', 'announce', 'cert'],
    'urgency' => ['ext', 'memo', 'stamp'],
    'org' => ['ext', 'cert'],
    'memo_org' => ['memo'],
    'org_name' => ['stamp', 'order', 'announce'],
    'place' => ['petition'],
    'title' => ['ext', 'memo', 'order', 'announce', 'petition'],
    'to' => ['ext', 'memo', 'stamp', 'petition'],
    'ref' => ['ext'],
    'encl' => ['ext'],
    'closing' => ['ext'],
    'sign_name' => ['ext', 'memo', 'order', 'announce', 'cert'],
    'sign_pos' => ['ext', 'memo', 'order', 'announce', 'cert'],
    'sender' => ['petition'],
    'approve' => ['petition'],
    'dept' => ['ext', 'stamp'],
    'tel' => ['ext', 'stamp'],
    'fax' => ['ext', 'stamp'],
    'email' => ['ext', 'stamp'],
    'cc' => ['ext'],
];

/* ================================================================ ค่าตั้ง */

function sb_cfg(): array
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    $saved = json_decode((string) md_get('sb_cfg', 'main'), true) ?: [];
    return $c = $saved + sb_cfg_default();
}

function sb_cfg_default(): array
{
    $school = (string) cfg('school_name', 'โรงเรียนดรุณราษฎร์วิทยา');
    $no = trim((string) cfg('addr_no', ''));
    $moo = trim((string) cfg('addr_moo', ''));
    $tb = trim((string) cfg('addr_tambon', ''));
    $am = trim((string) cfg('addr_amphoe', ''));
    $pv = trim((string) cfg('addr_province', ''));
    $zip = trim((string) cfg('addr_zip', ''));
    if ($no.$tb.$am.$pv === '') {
        // ค่าเริ่มต้นตามที่อยู่โรงเรียนในระบบเดิม (ยังไม่ได้ตั้งค่าข้อมูลโรงเรียน)
        [$no, $moo, $tb, $am, $pv, $zip] = ['7', '3', 'เวียงพางคำ', 'แม่สาย', 'เชียงราย', '57130'];
    }
    $l1 = trim($no.($moo !== '' ? ' หมู่ '.$moo : '').($tb !== '' ? ' ตำบล'.$tb : ''));
    $l2 = trim(($am !== '' ? 'อำเภอ'.$am : '').($pv !== '' ? ' จังหวัด'.$pv : '').($zip !== '' ? ' '.$zip : ''));
    $phone = trim((string) cfg('phone', '')) ?: '0 5373 1268';
    $dir = function_exists('rpt_signer') ? rpt_signer('ผู้อำนวยการ') : '';
    return [
        'fmt_out' => '{n}/{y}', 'fmt_memo' => '{n}/{y}', 'fmt_order' => '{n}/{y}', 'fmt_announce' => '',
        'org' => implode("\n", array_filter([$school, $l1, $l2])),
        'memo_org' => $school.' โทร. '.$phone,
        'org_name' => $school,
        'dept' => 'กลุ่มบริหารงานทั่วไป', 'tel' => $phone, 'fax' => trim((string) cfg('fax', '')), 'email' => trim((string) cfg('email', '')),
        'sign_name' => $dir, 'sign_pos' => 'ผู้อำนวยการ'.$school,
        'closing' => 'ขอแสดงความนับถือ',
        'approve' => "ความเห็นของครูที่ปรึกษา\nความเห็นของรองผู้อำนวยการ\nคำสั่งผู้อำนวยการ",
        'thai_digits' => '1',
    ];
}

/** URL ตราครุฑ หรือ '' */
function sb_garuda(): string
{
    foreach (['png', 'jpg', 'gif'] as $e) {
        $f = ROOT_PATH.'datas/school/garuda.'.$e;
        if (is_file($f)) {
            return '/datas/school/garuda.'.$e.'?v='.filemtime($f);
        }
    }
    return '/mis/images/garuda.png?v=1'; // ตราครุฑมาตรฐาน (ค่าเริ่มต้น)
}

/** มีตราครุฑที่อัปโหลดเองหรือไม่ */
function sb_garuda_custom(): bool
{
    return strpos(sb_garuda(), '/datas/') === 0;
}

function adm_sb_settings(bool $post)
{
    $c = sb_cfg();
    if ($post) {
        $up = $_FILES['garuda'] ?? null;
        $del = !empty($_POST['garuda_del']);
        if ($del || ($up && (int) $up['error'] === 0 && (int) $up['size'] > 0)) {
            $ext = $del ? '' : (['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif'][(string) @mime_content_type($up['tmp_name'])] ?? '');
            if (!$del && ($ext === '' || (int) $up['size'] > 3 * 1024 * 1024)) {
                return msg_box('ไฟล์ตราครุฑต้องเป็น PNG/JPG/GIF ไม่เกิน 3 MB', false).sb_settings_form($c);
            }
            is_dir(ROOT_PATH.'datas/school') || mkdir(ROOT_PATH.'datas/school', 0777, true);
            foreach (['png', 'jpg', 'gif'] as $e) {
                is_file(ROOT_PATH.'datas/school/garuda.'.$e) && unlink(ROOT_PATH.'datas/school/garuda.'.$e);
            }
            if (!$del) {
                move_uploaded_file($up['tmp_name'], ROOT_PATH.'datas/school/garuda.'.$ext);
            }
        }
        $d = [];
        foreach (array_keys(sb_cfg_default()) as $k) {
            $d[$k] = mb_substr(trim(str_replace("\r", '', (string) ($_POST[$k] ?? ''))), 0, 600);
        }
        $d['thai_digits'] = !empty($_POST['thai_digits']) ? '1' : '0';
        md_set('sb_cfg', 'main', '', '', json_encode($d, JSON_UNESCAPED_UNICODE));
        admin_done('sb_settings');
    }
    return sb_settings_form($c);
}

function sb_settings_form(array $c): array
{
    $g = sb_garuda();
    $ta = fn($n, $h = 70) => '<textarea name="'.$n.'" class="general radius grayborder" style="width:520px;height:'.$h.'px">'.h($c[$n] ?? '').'</textarea>';
    $tip = fn($t) => '<div class="gray_12" style="font-size:11px;margin-top:2px">'.$t.'</div>';
    $rows = [
        ['ตราครุฑ', '<div style="display:flex;gap:14px;align-items:flex-start"><div style="width:90px;height:100px;border:1px solid #ccc;border-radius:3px;background:#fff;display:flex;align-items:center;justify-content:center">'
            .($g !== '' ? '<img src="'.h($g).'" style="max-width:84px;max-height:94px"/>' : '<span class="gray_12" style="text-align:center">ยังไม่มี<br>ตราครุฑ</span>').'</div><div>'
            .'<input type="file" name="garuda" accept="image/png,image/jpeg,image/gif" style="width:260px;font-size:12px"/>'
            .$tip('ไฟล์ภาพตราครุฑ (PNG พื้นหลังโปร่งใสดีที่สุด) ไม่เกิน 3 MB — ระบบย่อให้สูง ๓ ซม. (หนังสือภายนอก คำสั่ง ประกาศ) และ ๑.๕ ซม. (บันทึกข้อความ) ตามระเบียบ')
            .(sb_garuda_custom() ? '<label style="font-size:12px"><input type="checkbox" name="garuda_del" value="1" style="width:auto;height:auto;vertical-align:-2px"/> ลบไฟล์ที่อัปโหลด (กลับไปใช้ตราครุฑมาตรฐาน)</label>' : '<div style="font-size:12px;color:#27AE60">ใช้ตราครุฑมาตรฐานของระบบอยู่ — อัปโหลดเฉพาะเมื่อต้องการเปลี่ยนไฟล์</div>').'</div></div>', false],
        ['รูปแบบเลขที่ หนังสือภายนอก / ประทับตรา / รับรอง', inp('fmt_out', $c['fmt_out'], 260).$tip('{n} = เลขทะเบียนหนังสือส่ง (เริ่ม ๑ ใหม่ทุกปีปฏิทิน), {y} = ปี พ.ศ. เช่น <b>ศธ ๐๔๐๐๗.๖๓๔/{n}</b> หรือ <b>ดร {n}/{y}</b>'), false],
        ['รูปแบบเลขที่ บันทึกข้อความ', inp('fmt_memo', $c['fmt_memo'], 260).$tip('ใช้ทะเบียนแยกจากหนังสือภายนอก'), false],
        ['รูปแบบเลขที่ คำสั่ง', inp('fmt_order', $c['fmt_order'], 260).$tip('ตามระเบียบ: ที่ ลำดับ/ปี พ.ศ. เช่น {n}/{y}'), false],
        ['รูปแบบเลขที่ ประกาศ', inp('fmt_announce', $c['fmt_announce'], 260).$tip('เว้นว่าง = ประกาศไม่มีเลขที่'), false],
        ['ส่วนราชการเจ้าของหนังสือ (หนังสือภายนอก/รับรอง)', $ta('org').$tip('บรรทัดแรกชื่อโรงเรียน บรรทัดถัดไปที่ตั้ง — พิมพ์ชิดขวาบนหนังสือ'), false],
        ['ส่วนราชการ (บันทึกข้อความ)', inp('memo_org', $c['memo_org'], 520), false],
        ['ชื่อส่วนราชการ (คำสั่ง/ประกาศ/ประทับตรา)', inp('org_name', $c['org_name'], 520).$tip('ใช้ต่อท้าย "คำสั่ง..." "ประกาศ..." และตราชื่อส่วนราชการในหนังสือประทับตรา'), false],
        ['ส่วนราชการเจ้าของเรื่อง', inp('dept', $c['dept'], 300), false],
        ['โทรศัพท์', inp('tel', $c['tel'], 200), false],
        ['โทรสาร', inp('fax', $c['fax'], 200), false],
        ['ไปรษณีย์อิเล็กทรอนิกส์', inp('email', $c['email'], 300), false],
        ['ผู้ลงนาม (ค่าเริ่มต้น)', inp('sign_name', $c['sign_name'], 300), false],
        ['ตำแหน่งผู้ลงนาม', $ta('sign_pos', 50).$tip('ขึ้นบรรทัดใหม่ได้ เช่น ตำแหน่งบรรทัดที่สอง'), false],
        ['คำลงท้าย (ค่าเริ่มต้น)', inp('closing', $c['closing'], 300), false],
        ['ช่องความเห็นในคำร้อง', $ta('approve').$tip('บรรทัดละ ๑ ช่อง'), false],
        ['ตัวเลข', '<label><input type="checkbox" name="thai_digits" value="1"'.(($c['thai_digits'] ?? '1') === '1' ? ' checked' : '').' style="width:auto;height:auto;vertical-align:-2px"/> พิมพ์เป็นเลขไทย (๑ ๒ ๓)</label>', false],
    ];
    return ['ตั้งค่าตราครุฑ รูปแบบเลขที่หนังสือ และค่าเริ่มต้นของหนังสือราชการ', admin_form('ตั้งค่างานสารบรรณ', a_url('sb_settings'), $rows, a_url('sb_letter'))];
}

/* ================================================================ ทะเบียนหนังสือส่ง */

function sb_doc(string $id): array
{
    return json_decode((string) (md_rows('sb_doc', $id)[0]['value'] ?? ''), true) ?: [];
}

/** เลขทะเบียนถัดไปของชุดเลขในปี พ.ศ. */
function sb_next_num(string $series, int $by): int
{
    $max = 0;
    foreach (md_rows('sb_doc', null, (string) $by) as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        if ((SB_TYPES[$d['type'] ?? ''][1] ?? '') === $series) {
            $max = max($max, (int) ($d['num'] ?? 0));
        }
    }
    return $max + 1;
}

function sb_format_no(string $fmt, int $n, int $by): string
{
    return strtr($fmt, ['{n}' => (string) $n, '{y}' => (string) $by]);
}

function adm_sb_letter(bool $post)
{
    $func = $_GET['func'] ?? '';
    if (isset($_GET['del'])) {
        md_rows('sb_doc', (string) $_GET['del']) && q('DELETE FROM {mis_data} WHERE `kind`=\'sb_doc\' AND `r1`=?', [(string) $_GET['del']]);
        admin_done('sb_letter');
    }
    if ($post && $func === 'Save') {
        return sb_save();
    }
    if ($func === 'Edit') {
        return sb_form((string) ($_GET['id'] ?? ''), (string) ($_GET['type'] ?? ''));
    }
    $years = [];
    foreach (q('SELECT DISTINCT `r2` FROM {mis_data} WHERE `kind`=\'sb_doc\' ORDER BY `r2` DESC') as $r) {
        $years[(string) $r['r2']] = 'ปี พ.ศ. '.$r['r2'];
    }
    $cy = (string) ((int) date('Y') + 543);
    $years += [$cy => 'ปี พ.ศ. '.$cy];
    krsort($years);
    $y = isset($years[(string) ($_GET['y'] ?? '')]) ? (string) $_GET['y'] : $cy;
    $ty = isset(SB_TYPES[(string) ($_GET['t'] ?? '')]) ? (string) $_GET['t'] : '';
    $tOpts = ['' => '- ทุกประเภท -'] + array_map(fn($x) => $x[0], SB_TYPES);
    $list = [];
    foreach (md_rows('sb_doc', null, $y) as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        if ($ty !== '' && ($d['type'] ?? '') !== $ty) {
            continue;
        }
        $list[] = [(string) $r['r1'], $d];
    }
    usort($list, fn($a, $b) => [$b[1]['date'] ?? '', $b[0]] <=> [$a[1]['date'] ?? '', $a[0]]);
    $rows = '';
    foreach ($list as $i => [$id, $d]) {
        $urg = (string) ($d['urgency'] ?? '');
        $pr = '<a href="'.h(a_url('sb_letter', '&func=Print&id='.rawurlencode($id))).'" target="_blank" class="icontooltip" title="พิมพ์ / ดูตัวอย่าง"><img src="../icon_tools/vec_print_dark.png" height="17"/></a>';
        $rows .= zrow([
            [$i + 1, ' align="center"'],
            [h($d['no'] ?? '') ?: '-', ' style="white-space:nowrap"'],
            [($d['date'] ?? '') !== '' ? th_date($d['date'], ' ') : '<span class="gray_12">ไม่ระบุวัน</span>', ' style="white-space:nowrap"'],
            h(SB_TYPES[$d['type'] ?? ''][0] ?? '').($urg !== '' ? ' <b style="color:#E00">'.h($urg).'</b>' : ''),
            h($d['title'] ?? '') ?: '<span class="gray_12">'.h(mb_substr((string) ($d['body'] ?? ''), 0, 60)).'</span>',
            h($d['to'] ?? ''),
            h(($d['type'] ?? '') === 'petition' ? ($d['sender'] ?? '') : ($d['sign_name'] ?? '')),
            [$pr.' '.edit_link(a_url('sb_letter', '&func=Edit&id='.rawurlencode($id))).' '.del_link(a_url('sb_letter', '&del='.rawurlencode($id))), ' align="center" style="white-space:nowrap" width="80"'],
        ], $i);
    }
    $filter = pick_form('sb_letter', ['y' => ['ทะเบียน', $years, $y], 't' => ['ประเภท', $tOpts, $ty]]);
    $add = '';
    foreach (SB_TYPES as $k => [$name]) {
        $add .= '<a href="'.h(a_url('sb_letter', '&func=Edit&type='.$k)).'" style="display:inline-block;margin:0 6px 6px 0;padding:6px 12px;border:1px solid #9CD3F2;border-radius:4px;background:#F2FAFF;color:#0F5A9C;font-size:13px">'
            .'<img src="../icon_tools/vec_add_dark.png" height="13" style="vertical-align:-2px"/> '.h($name).'</a>';
    }
    $addBox = '<div style="width:983px;box-sizing:border-box;margin:0 auto 10px;text-align:left;padding:10px 12px 4px;border:1px dashed #33cc99;border-radius:4px;background:#FCFCFC">'
        .'<b style="display:block;margin-bottom:6px">ออกหนังสือใหม่</b>'.$add
        .'<a href="'.h(a_url('sb_settings')).'" style="float:right;font-size:12px;margin-top:6px">ตั้งค่าตราครุฑ / รูปแบบเลขที่</a>'
        .'<div style="clear:both"></div></div>';
    return ['ทะเบียนหนังสือส่ง ปี พ.ศ. '.$y, $addBox.$filter.list_table('ทะเบียนหนังสือส่ง ปี พ.ศ. '.h($y),
        [['#', ' style="width:30px"'], 'เลขที่', 'ลงวันที่', 'ประเภท', 'เรื่อง', 'เรียน/ถึง', 'ผู้ลงนาม/ผู้ร้อง', ['', ' style="width:80px"']], $rows, count($list).' รายการ')];
}

function sb_form(string $id, string $type): array
{
    $c = sb_cfg();
    $d = $id !== '' ? sb_doc($id) : [];
    if ($id !== '' && !$d) {
        return ['ไม่พบหนังสือ', msg_box('ไม่พบหนังสือที่ต้องการแก้ไข', false)];
    }
    $type = (string) ($d['type'] ?? $type);
    if (!isset(SB_TYPES[$type])) {
        $type = 'ext';
    }
    if (!$d) {
        $d = ['type' => $type, 'date' => date('Y-m-d'), 'urgency' => '', 'closing' => $c['closing'], 'org' => $c['org'], 'memo_org' => $c['memo_org'], 'org_name' => $c['org_name'],
            'dept' => $c['dept'], 'tel' => $c['tel'], 'fax' => $c['fax'], 'email' => $c['email'], 'sign_name' => $c['sign_name'], 'sign_pos' => $c['sign_pos'],
            'place' => cfg('school_name', 'โรงเรียนดรุณราษฎร์วิทยา'), 'to' => $type === 'petition' ? 'ผู้อำนวยการ'.cfg('school_name', 'โรงเรียนดรุณราษฎร์วิทยา') : '', 'approve' => $c['approve']];
    }
    $v = fn($k) => (string) ($d[$k] ?? '');
    $ta = fn($n, $hgt = 60, $ph = '') => '<textarea name="'.$n.'" class="general radius grayborder" style="width:640px;height:'.$hgt.'px;font-size:14px"'.($ph !== '' ? ' placeholder="'.h($ph).'"' : '').'>'.h($v($n)).'</textarea>';
    $tip = fn($t) => '<div class="gray_12" style="font-size:11px;margin-top:2px">'.$t.'</div>';
    $urg = '';
    foreach (SB_URGENCY as $k => $l) {
        $urg .= '<label style="margin-right:14px;white-space:nowrap'.($k !== '' ? ';color:#D00;font-weight:bold' : '').'"><input type="radio" name="urgency" value="'.h($k).'"'.($v('urgency') === $k ? ' checked' : '').' style="width:auto;height:auto;vertical-align:-2px"/> '.h($l).'</label>';
    }
    $fmtKey = 'fmt_'.(SB_TYPES[$type][1] ?: 'out');
    $rows = [
        'type' => ['ประเภทหนังสือ', sel('type', array_map(fn($x) => $x[0], SB_TYPES), $type, 'id="sb_type" style="width:300px;height:25px" onchange="sbType()"'), true],
        'no' => ['เลขที่', inp('no', $v('no'), 260, 'text', 'placeholder="เว้นว่าง = ออกเลขอัตโนมัติ"').$tip('เว้นว่างเพื่อออกเลขทะเบียนถัดไปอัตโนมัติ (ไม่ต้องพิมพ์คำว่า "ที่") — รูปแบบกำหนดที่ <a href="'.h(a_url('sb_settings')).'">ตั้งค่างานสารบรรณ</a>'), false],
        'urgency' => ['ชั้นความเร็ว', $urg, false],
        'date' => ['ลงวันที่', inp('date', $v('date') !== '' ? iso_to_be($v('date')) : '', 120, 'text', 'placeholder="วว/ดด/ปปปป"').$tip('เว้นว่าง = พิมพ์เดือน ปี ปัจจุบันแล้วเว้นช่องวันที่ไว้เขียนตอนลงนาม'), false],
        'org' => ['ส่วนราชการเจ้าของหนังสือ', $ta('org', 62).$tip('พิมพ์ชิดขวาด้านบน บรรทัดแรกชื่อส่วนราชการ บรรทัดถัดไปที่ตั้ง'), false],
        'memo_org' => ['ส่วนราชการ', inp('memo_org', $v('memo_org'), 640).$tip('ชื่อส่วนราชการเจ้าของเรื่อง/หน่วยงาน และหมายเลขโทรศัพท์'), false],
        'org_name' => ['ชื่อส่วนราชการ', inp('org_name', $v('org_name'), 640).$tip('คำสั่ง/ประกาศ พิมพ์เป็น "คำสั่ง<ชื่อส่วนราชการ>" — หนังสือประทับตรา พิมพ์เหนือวันที่เพื่อประทับตรา'), false],
        'place' => ['เขียนที่', inp('place', $v('place'), 400), false],
        'title' => ['เรื่อง', inp('title', $v('title'), 640), false],
        'to' => ['<span id="sb_to_l">เรียน</span>', inp('to', $v('to'), 640).$tip('คำขึ้นต้นและตำแหน่งผู้รับ เช่น ผู้อำนวยการสำนักงานคณะกรรมการส่งเสริมการศึกษาเอกชน'), false],
        'ref' => ['อ้างถึง', $ta('ref', 40).$tip('ถ้ามีหลายรายการ พิมพ์บรรทัดละรายการ (ใส่เลขลำดับเอง เช่น ๑. ...)'), false],
        'encl' => ['สิ่งที่ส่งมาด้วย', $ta('encl', 40).$tip('บรรทัดละรายการ เช่น ๑. ... จำนวน ๑ ฉบับ'), false],
        'body' => ['ข้อความ', $ta('body', 230).$tip('พิมพ์ย่อหน้าละ ๑ บรรทัด (กด Enter เมื่อขึ้นย่อหน้าใหม่) ระบบย่อหน้า ๒.๕ ซม. ให้อัตโนมัติ — บรรทัดว่างคือเว้นบรรทัด'), true],
        'closing' => ['คำลงท้าย', inp('closing', $v('closing'), 300, 'text', 'list="sb_closing"').'<datalist id="sb_closing"><option value="ขอแสดงความนับถือ"><option value="ขอแสดงความนับถืออย่างยิ่ง"><option value="ขอแสดงความเคารพอย่างยิ่ง"><option value="ขอแสดงความเคารพอย่างสูง"></datalist>', false],
        'sign_name' => ['ผู้ลงนาม', inp('sign_name', $v('sign_name'), 300, 'text', 'list="sb_signers"').'<datalist id="sb_signers">'.implode('', array_map(fn($x) => '<option value="'.h($x[2]).'">', executives())).'</datalist>'.$tip('พิมพ์ในวงเล็บใต้ช่องลงชื่อ'), false],
        'sign_pos' => ['ตำแหน่ง', $ta('sign_pos', 40), false],
        'sender' => ['ชื่อผู้ร้อง', inp('sender', $v('sender'), 300), false],
        'approve' => ['ช่องความเห็น/คำสั่ง', $ta('approve', 60).$tip('บรรทัดละ ๑ ช่อง (เว้นว่าง = ไม่มีช่องความเห็น)'), false],
        'dept' => ['ส่วนราชการเจ้าของเรื่อง', inp('dept', $v('dept'), 300), false],
        'tel' => ['โทร.', inp('tel', $v('tel'), 200), false],
        'fax' => ['โทรสาร', inp('fax', $v('fax'), 200), false],
        'email' => ['ไปรษณีย์อิเล็กทรอนิกส์', inp('email', $v('email'), 300), false],
        'cc' => ['สำเนาส่ง', $ta('cc', 40).$tip('ถ้ามี — บรรทัดละรายการ'), false],
    ];
    $tr = '';
    foreach ($rows as $k => [$label, $html, $req]) {
        $cls = isset(SB_FIELDS[$k]) ? ' class="sbf '.implode(' ', array_map(fn($t) => 'sbf-'.$t, SB_FIELDS[$k])).'"' : '';
        $tr .= '<tr'.$cls.'><td valign="top" class="titleinputfill" style="padding:6px 10px;width:190px">'.$label.' <span class="'.($req ? 'warningfiilsign">*<span style=\'color:#000\'>:</span>' : 'nowarningfiilsign">:').'</span></td><td valign="top" class="columninputfill" style="padding:6px 0">'.$html.'</td></tr>';
    }
    $title = ($id === '' ? 'ออกหนังสือใหม่' : 'แก้ไขหนังสือ '.$v('no'));
    $html = '<div class="form_content"><form id="operationform" name="operationform" method="post" action="'.h(a_url('sb_letter', '&func=Save&id='.rawurlencode($id))).'" style="width:964px;margin:0 auto">'
        .'<input type="hidden" name="then" id="sb_then" value=""/>'
        .'<div class="formframeheader" style="width:970px"><div class="content_edit"><h1 style="margin-left:25px;margin-top:-3px">'.h($title).'</h1></div></div>'
        .'<div class="formframe" id="formframe" style="width:970px"><table width="900" border="0" cellpadding="0" cellspacing="0" class="black_12 adm-form" style="margin:10px 20px">'.$tr.'</table></div>'
        .'<div class="formframefooter" style="width:970px"><div style="padding-top:4px;float:right;padding-right:5px">'
        .'<span style="float:left;width:150px"><a href="javascript:void(0);" onclick="document.getElementById(\'sb_then\').value=\'print\';document.forms.operationform.submit();" id="Savebtns"><img src="../icon_tools/vec_print_white.png" border="0" height="16" style="margin-top:-5px"/> บันทึกและพิมพ์</a></span>'
        .'<span style="float:left;width:70px;margin-left:3px"><a href="javascript:void(0);" onclick="document.forms.operationform.submit();" id="Savebtns"><img src="../icon_tools/vec_add_white.png" border="0" height="18" style="margin-top:-5px"/> บันทึก</a></span>'
        .'<span style="margin-left:3px;float:left;width:65px"><a href="'.h(a_url('sb_letter')).'" id="Clearbtns"><img src="../icon_tools/vec_x_white.png" border="0" height="14" style="margin-top:-5px"/> กลับ</a></span></div></div></form></div>'
        .'<script>function sbType(){var t=document.getElementById("sb_type").value;var r=document.querySelectorAll("tr.sbf");for(var i=0;i<r.length;i++){r[i].style.display=r[i].classList.contains("sbf-"+t)?"":"none";}'
        .'document.getElementById("sb_to_l").textContent=t==="stamp"?"ถึง":"เรียน";}sbType();</script>';
    return [$title, $html];
}

function sb_save()
{
    $id = (string) ($_GET['id'] ?? '');
    $old = $id !== '' ? sb_doc($id) : [];
    if ($id !== '' && !$old) {
        return ['ไม่พบหนังสือ', msg_box('ไม่พบหนังสือที่ต้องการแก้ไข', false)];
    }
    $type = isset(SB_TYPES[(string) ($_POST['type'] ?? '')]) ? (string) $_POST['type'] : 'ext';
    $long = ['org', 'ref', 'encl', 'sign_pos', 'approve', 'cc'];
    $d = ['type' => $type];
    foreach (array_keys(SB_FIELDS) as $k) {
        $val = str_replace("\r", '', (string) ($_POST[$k] ?? ''));
        $d[$k] = in_array($k, $long, true) ? mb_substr(trim($val), 0, 2000) : mb_substr(trim(preg_replace('/\s+/u', ' ', $val)), 0, 400);
    }
    $d['urgency'] = isset(SB_URGENCY[$d['urgency']]) ? $d['urgency'] : '';
    $d['body'] = mb_substr(rtrim(str_replace("\r", '', (string) ($_POST['body'] ?? ''))), 0, 20000);
    $dt = trim((string) ($_POST['date'] ?? ''));
    $d['date'] = $dt !== '' ? be_date_to_iso($dt, '') : '';
    if ($dt !== '' && $d['date'] === '') {
        return ['ออกหนังสือ', msg_box('รูปแบบวันที่ไม่ถูกต้อง (วว/ดด/ปปปป)', false).sb_form($id, $type)[1]];
    }
    if (trim($d['body']) === '') {
        return ['ออกหนังสือ', msg_box('กรุณาพิมพ์ข้อความของหนังสือ', false).sb_form($id, $type)[1]];
    }
    $by = (int) date('Y', $d['date'] !== '' ? strtotime($d['date']) : time()) + 543;
    $series = SB_TYPES[$type][1];
    $d['num'] = 0;
    if ($d['no'] === '' && $series !== '') {
        if ($old && ($old['no'] ?? '') !== '' && (SB_TYPES[$old['type'] ?? ''][1] ?? '') === $series && (int) ($old['by'] ?? 0) === $by) {
            // แก้ไขหนังสือเดิมแล้วลบเลขที่ทิ้ง: คงเลขเดิม
            $d['no'] = $old['no'];
            $d['num'] = (int) ($old['num'] ?? 0);
        } else {
            $fmt = (string) (sb_cfg()['fmt_'.$series] ?? '');
            if ($fmt !== '') {
                $d['num'] = sb_next_num($series, $by);
                $d['no'] = sb_format_no($fmt, $d['num'], $by);
            }
        }
    } elseif ($old && $d['no'] === ($old['no'] ?? null)) {
        $d['num'] = (int) ($old['num'] ?? 0);
    } elseif (preg_match('/(\d+)\D*$/u', strtr($d['no'], array_flip(SB_TH_DIGITS)), $m) && $series !== '') {
        // เลขที่พิมพ์เอง: นับเป็นเลขทะเบียน เพื่อให้เลขอัตโนมัติถัดไปต่อจากเลขนี้ (เฉพาะรูปแบบ {n}/... หรือ .../{n})
        $fmt = (string) (sb_cfg()['fmt_'.$series] ?? '');
        if ($fmt !== '' && preg_match('/^'.str_replace(['\{n\}', '\{y\}'], ['(\d+)', '\d+'], preg_quote(strtr($fmt, array_flip(SB_TH_DIGITS)), '/')).'$/u', strtr($d['no'], array_flip(SB_TH_DIGITS)), $mm)) {
            $d['num'] = (int) $mm[1];
        }
    }
    $d['by'] = $by;
    $d['created_by'] = (int) ($old['created_by'] ?? (me()['id'] ?? 0));
    if ($id === '') {
        $id = (string) round(microtime(true) * 1000);
    } elseif ((int) ($old['by'] ?? $by) !== $by) {
        q('DELETE FROM {mis_data} WHERE `kind`=\'sb_doc\' AND `r1`=?', [$id]); // ย้ายปีทะเบียน
    }
    md_set('sb_doc', $id, (string) $by, '', json_encode($d, JSON_UNESCAPED_UNICODE));
    if (($_POST['then'] ?? '') === 'print') {
        redirect(a_url('sb_letter', '&func=Print&id='.rawurlencode($id).'&back=1'));
    }
    admin_done('sb_letter', '&y='.$by);
}

/* ================================================================ พิมพ์หนังสือ (A4) */

const SB_TH_DIGITS = ['0' => '๐', '1' => '๑', '2' => '๒', '3' => '๓', '4' => '๔', '5' => '๕', '6' => '๖', '7' => '๗', '8' => '๘', '9' => '๙'];

/** ข้อความสำหรับพิมพ์: escape + เลขไทย (ตามตั้งค่า) */
function sb_t(string $s): string
{
    $s = h($s);
    return (sb_cfg()['thai_digits'] ?? '1') === '1' ? strtr($s, SB_TH_DIGITS) : $s;
}

/** หลายบรรทัด -> <br> */
function sb_lines(string $s): string
{
    return implode('<br>', array_map('sb_t', explode("\n", trim($s))));
}

/** วันที่แบบหนังสือราชการ: $style = plain (๒๕ พฤศจิกายน ๒๕๖๙) | full (วันที่ ๒๕ เดือน พฤศจิกายน พ.ศ. ๒๕๖๙) | be (๒๕ พฤศจิกายน พ.ศ. ๒๕๖๙) */
function sb_date(string $iso, string $style = 'plain'): string
{
    $t = $iso !== '' ? strtotime($iso) : time();
    $day = $iso !== '' ? sb_t(date('j', $t)) : '<span style="display:inline-block;width:1.2cm"></span>';
    $mon = TH_MONTHS[(int) date('n', $t)];
    $yr = sb_t((string) ((int) date('Y', $t) + 543));
    return match ($style) {
        'full' => $day.' เดือน '.$mon.' พ.ศ. '.$yr,
        'be' => $day.' '.$mon.' พ.ศ. '.$yr,
        default => $day.' '.$mon.' '.$yr,
    };
}

/** ย่อหน้าข้อความ: บรรทัดละย่อหน้า ย่อหน้า ๒.๕ ซม. บรรทัดว่าง = เว้นบรรทัด */
function sb_paras(string $body, string $cls = 'p'): string
{
    $o = '';
    foreach (explode("\n", str_replace("\r", '', $body)) as $line) {
        $o .= trim($line) === '' ? '<div class="gap"></div>' : '<div class="'.$cls.'">'.sb_t(trim($line)).'</div>';
    }
    return $o;
}

/** แถวหัวข้อแบบห้อยบรรทัด (เรื่อง / เรียน / อ้างถึง / สิ่งที่ส่งมาด้วย) */
function sb_row(string $label, string $html, string $style = ''): string
{
    return '<div class="row"'.($style !== '' ? ' style="'.$style.'"' : '').'><span class="lb">'.$label.'</span><span class="tx">'.$html.'</span></div>';
}

/** ตราครุฑ (สูง $cm ซม.) */
function sb_krut(float $cm, string $style): string
{
    $g = sb_garuda();
    return $g !== '' ? '<img class="krut" src="'.h($g).'" alt="ตราครุฑ" style="height:'.$cm.'cm;'.$style.'"/>'
        : '<div class="krut nokrut" style="height:'.$cm.'cm;width:'.round($cm * 0.9, 2).'cm;'.$style.'">ตราครุฑ</div>';
}

/** คำลงท้าย + ลงชื่อ: ชื่อในวงเล็บอยู่กึ่งกลางใต้คำลงท้าย (คำลงท้ายเริ่มตรงกึ่งกลางหน้ากระดาษ) */
function sb_sign(string $closing, string $name, string $pos, string $pre = ''): string
{
    $anchor = $closing !== '' ? sb_t($closing) : '<span class="ghost">ขอแสดงความนับถือ</span>';
    $who = '<div style="height:1.9cm"></div><div>('.($name !== '' ? sb_t($name) : '<span style="display:inline-block;width:5cm"></span>').')</div>'.($pos !== '' ? '<div>'.sb_lines($pos).'</div>' : '');
    return '<div class="sig"><div class="cl">'.$pre.$anchor.'</div><div class="sw"><div class="si">'.$who.'</div></div></div>';
}

/** ส่วนราชการเจ้าของเรื่อง โทร. โทรสาร อีเมล สำเนาส่ง */
function sb_contact(array $d): string
{
    $o = '';
    foreach ([['', 'dept'], ['โทร. ', 'tel'], ['โทรสาร ', 'fax'], ['ไปรษณีย์อิเล็กทรอนิกส์ ', 'email']] as [$l, $k]) {
        if (trim((string) ($d[$k] ?? '')) !== '') {
            $o .= '<div>'.$l.sb_t((string) $d[$k]).'</div>';
        }
    }
    $o = $o !== '' ? '<div class="contact">'.$o.'</div>' : '';
    if (trim((string) ($d['cc'] ?? '')) !== '') {
        $o .= '<div class="contact">'.sb_row('สำเนาส่ง', sb_lines((string) $d['cc'])).'</div>';
    }
    return $o;
}

function sb_urg(array $d, string $cls = 'urg'): string
{
    $u = (string) ($d['urgency'] ?? '');
    return $u !== '' ? '<div class="'.$cls.'">'.h($u).'</div>' : '';
}

function adm_sb_letter_Print(bool $post)
{
    $id = (string) ($_GET['id'] ?? '');
    $d = $id !== '' ? sb_doc($id) : [];
    if (!$d) {
        rpt_print('ไม่พบหนังสือ', '<div class="page">ไม่พบหนังสือ</div>');
    }
    $type = (string) ($d['type'] ?? 'ext');
    $v = fn($k) => (string) ($d[$k] ?? '');
    $no = $v('no') !== '' ? sb_t($v('no')) : '<span style="display:inline-block;width:3cm"></span>';
    $body = '';
    switch ($type) {
        case 'memo':
            $body = sb_urg($d, 'urg-flow')
                .'<div class="mhead">'.sb_krut(1.5, 'position:absolute;left:0;top:0').'<div class="mtitle">บันทึกข้อความ</div></div>'
                .'<div class="mrow"><b class="ml">ส่วนราชการ</b><span class="mv">'.sb_t($v('memo_org')).'</span></div>'
                .'<div class="mrow"><span class="mh"><b class="ml">ที่</b><span class="mv">'.($v('no') !== '' ? sb_t($v('no')) : '&nbsp;').'</span></span>'
                .'<span class="mh2"><b class="ml">วันที่</b><span class="mv">'.sb_date($v('date')).'</span></span></div>'
                .'<div class="mrow"><b class="ml">เรื่อง</b><span class="mv">'.sb_t($v('title')).'</span></div>'
                .sb_row('เรียน', sb_t($v('to')), 'margin-top:8pt')
                .sb_paras($v('body'))
                .sb_sign('', $v('sign_name'), $v('sign_pos'));
            break;
        case 'order':
        case 'announce':
            $word = $type === 'order' ? 'คำสั่ง' : 'ประกาศ';
            $body = '<div class="c">'.sb_krut(3, 'display:block;margin:0 auto').'</div>'
                .'<div class="c b" style="margin-top:6pt">'.$word.sb_t($v('org_name')).'</div>'
                .($type === 'order' || $v('no') !== '' ? '<div class="c">ที่ '.$no.'</div>' : '')
                .($v('title') !== '' ? '<div class="c">เรื่อง '.sb_t($v('title')).'</div>' : '')
                .'<div class="c"><span class="dash"></span></div>'
                .sb_paras($v('body'))
                .'<div class="p" style="margin-top:12pt">'.($type === 'order' ? 'สั่ง' : 'ประกาศ').' ณ วันที่ '.sb_date($v('date'), 'be').'</div>'
                .sb_sign('', $v('sign_name'), $v('sign_pos'));
            break;
        case 'petition':
            $logo = rpt_logo();
            $appr = '';
            foreach (array_filter(array_map('trim', explode("\n", $v('approve')))) as $lab) {
                $appr .= '<div class="appr"><div class="b">'.sb_t($lab).'</div><div class="dl"></div><div class="dl"></div>'
                    .'<div class="asig">ลงชื่อ<span class="dl in" style="width:5.5cm"></span><br>(<span class="dl in" style="width:5.5cm"></span>)</div></div>';
            }
            $body = '<div class="c"><img src="'.h($logo).'" alt="" style="height:2.2cm"/></div>'
                .'<div class="c b" style="font-size:20pt;margin-top:2pt">คำร้อง</div>'
                .'<div style="padding-left:7.5cm;margin-top:6pt">เขียนที่ '.sb_t($v('place')).'</div>'
                .'<div style="padding-left:7.5cm">วันที่ '.sb_date($v('date'), 'full').'</div>'
                .sb_row('เรื่อง', sb_t($v('title')), 'margin-top:8pt')
                .sb_row('เรียน', sb_t($v('to')))
                .sb_paras($v('body'))
                // ผู้ร้อง: ลงชื่อ .......... ผู้ร้อง / (ชื่อ)
                .'<div class="sig"><div class="cl"><span class="ghost">ขอแสดงความนับถือ</span></div><div class="sw"><div class="si">'
                .'<div style="height:1.2cm"></div><div>ลงชื่อ<span class="dl in" style="width:5cm"></span>ผู้ร้อง</div><div>('.($v('sender') !== '' ? sb_t($v('sender')) : '<span style="display:inline-block;width:5cm"></span>').')</div></div></div></div>'
                .($appr !== '' ? '<div class="apprs">'.$appr.'</div>' : '');
            break;
        case 'stamp':
            $body = sb_urg($d)
                .'<div class="c">'.sb_krut(3, 'display:block;margin:0 auto').'</div>'
                .'<div class="hdr"><div>ที่ '.$no.'</div></div>'
                .sb_row('ถึง', sb_t($v('to')), 'margin-top:8pt')
                .sb_paras($v('body'))
                .'<div class="sig"><div class="cl"><span class="ghost">ขอแสดงความนับถือ</span></div><div class="sw"><div class="si">'
                .'<div style="height:1.9cm"></div><div>'.sb_t($v('org_name')).'</div><div>'.sb_date($v('date')).'</div></div></div></div>'
                .sb_contact($d);
            break;
        case 'cert':
            $body = '<div class="c">'.sb_krut(3, 'display:block;margin:0 auto').'</div>'
                .'<div class="hdr"><div>ที่ '.$no.'</div><div class="org">'.sb_lines($v('org')).'</div></div>'
                .'<div class="p" style="margin-top:12pt">หนังสือฉบับนี้ให้ไว้เพื่อรับรองว่า</div>'
                .sb_paras($v('body'))
                .'<div class="p" style="margin-top:12pt">ให้ไว้ ณ วันที่ '.sb_date($v('date'), 'full').'</div>'
                .sb_sign('', $v('sign_name'), $v('sign_pos'));
            break;
        default: // หนังสือภายนอก
            $encl = trim($v('encl'));
            $body = sb_urg($d)
                .'<div class="c">'.sb_krut(3, 'display:block;margin:0 auto').'</div>'
                .'<div class="hdr"><div>ที่ '.$no.'</div><div class="org">'.sb_lines($v('org')).'</div></div>'
                .'<div class="dt">'.sb_date($v('date')).'</div>'
                .sb_row('เรื่อง', sb_t($v('title')), 'margin-top:8pt')
                .sb_row('เรียน', sb_t($v('to')), 'margin-top:8pt')
                .(trim($v('ref')) !== '' ? sb_row('อ้างถึง', sb_lines($v('ref')), 'margin-top:8pt') : '')
                .($encl !== '' ? sb_row('สิ่งที่ส่งมาด้วย', sb_lines($encl), 'margin-top:8pt') : '')
                .sb_paras($v('body'))
                .sb_sign($v('closing'), $v('sign_name'), $v('sign_pos'))
                .sb_contact($d);
    }
    $thai = (sb_cfg()['thai_digits'] ?? '1') === '1';
    $back = !empty($_GET['back']) ? '<button onclick="location.href=\''.h(a_url('sb_letter')).'\'">กลับทะเบียนหนังสือ</button>' : '<button onclick="history.length>1?history.back():window.close()">กลับ</button>';
    mis_headers();
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><title>'.h((SB_TYPES[$type][0] ?? 'หนังสือ').($v('no') !== '' ? ' '.$v('no') : '').($v('title') !== '' ? ' '.$v('title') : '')).'</title>'
        .'<link rel="shortcut icon" href="/mis/images/favicon.ico"><link rel="stylesheet" href="/mis/css/sarabun.css"><style>'
        .'@page{size:A4;margin:2.5cm 2cm 2cm 3cm;@top-center{content:"- " counter(page'.($thai ? ',thai' : '').') " -";font-family:"TH Sarabun New";font-size:16pt;vertical-align:bottom;padding-bottom:.35cm}}'
        .'@page:first{margin-top:1.5cm;@top-center{content:none}}'
        .'*{box-sizing:border-box}body{margin:0;background:#808080}'
        .'.sheet{width:21cm;min-height:29.7cm;margin:12px auto;background:#fff;padding:1.5cm 2cm 2cm 3cm;box-shadow:0 2px 10px rgba(0,0,0,.35)}'
        .'.pg{position:relative;font-family:"TH Sarabun New",sans-serif;font-size:16pt;line-height:normal;color:#000;text-align:left}'
        .'.c{text-align:center}.b{font-weight:bold}'
        .'.krut{display:block}.nokrut{border:1px dashed #E74C3C;color:#E74C3C;font-size:11pt;display:flex;align-items:center;justify-content:center;text-align:center}'
        .'.urg{position:absolute;left:0;top:.75cm;color:#E00;font-weight:bold;font-size:32pt;line-height:1}.urg-flow{color:#E00;font-weight:bold;font-size:32pt;line-height:1.1}'
        .'.hdr{display:flex;justify-content:space-between;align-items:flex-start;margin-top:-.45cm}.hdr .org{max-width:7.2cm}'
        .'.dt{padding-left:7.5cm;margin-top:8pt}'
        .'.row{display:flex}.row .lb{white-space:nowrap;margin-right:.35cm}.row .tx{flex:1}'
        .'.p{text-indent:2.5cm;margin-top:8pt;text-align:justify;text-justify:inter-character}.gap{height:8pt}'
        .'.sig{margin-top:12pt;page-break-inside:avoid;break-inside:avoid}.sig .cl{margin-left:7.5cm;width:max-content}.sig .sw{margin-left:7.5cm;width:0;position:relative}'
        .'.ghost{visibility:hidden;display:block;height:0;overflow:hidden}.sig .si{width:11cm;text-align:center;margin-left:var(--half,1.65cm);transform:translateX(-50%)}'
        .'.contact{margin-top:14pt}.contact+.contact{margin-top:6pt}'
        .'.dash{display:inline-block;width:4.5cm;border-top:1px solid #000;vertical-align:middle;margin:6pt 0}'
        .'.mhead{position:relative;height:1.5cm;margin-bottom:2pt}.mtitle{text-align:center;font-weight:bold;font-size:29pt;line-height:1.5cm}'
        .'.mrow{display:flex;align-items:baseline;margin-top:2pt}.ml{font-size:20pt;white-space:nowrap;margin-right:.25cm}.mv{flex:1;border-bottom:1px dotted #000;padding-left:.1cm;min-height:1em}'
        .'.mh{display:flex;align-items:baseline;width:7.5cm;padding-right:.3cm}.mh2{display:flex;align-items:baseline;flex:1}'
        .'.dl{display:block;border-bottom:1px dotted #000;height:1.05em}.dl.in{display:inline-block;vertical-align:baseline;height:auto;margin:0 .15cm}'
        .'.apprs{margin-top:14pt;display:grid;grid-template-columns:1fr 1fr;gap:10pt 1cm}.appr{break-inside:avoid;page-break-inside:avoid}.asig{text-align:center;margin-top:6pt}'
        .'.bar{position:sticky;top:0;background:#333;color:#fff;padding:8px;text-align:center;z-index:9;font:13px Tahoma}.bar button{font:14px Tahoma;padding:4px 14px;margin:0 4px}'
        .'@media print{body{background:#fff}.sheet{width:auto;min-height:0;margin:0;padding:0;box-shadow:none}.bar{display:none}.nokrut{visibility:hidden}}'
        .'</style></head><body>'
        .'<div class="bar"><button onclick="window.print()">🖨 พิมพ์ / บันทึกเป็น PDF</button><button onclick="location.href=\''.h(a_url('sb_letter', '&func=Edit&id='.rawurlencode($id))).'\'">แก้ไข</button>'.$back
        .' <span style="font-size:12px">กระดาษ A4 แนวตั้ง · ระยะขอบ: ค่าเริ่มต้น · ปิด "หัวกระดาษและท้ายกระดาษ" · เปิด "กราฟิกพื้นหลัง"</span></div>'
        .'<div class="sheet"><div class="pg">'.$body.'</div></div>'
        // ชื่อในวงเล็บอยู่กึ่งกลางใต้คำลงท้าย: วัดความกว้างคำลงท้ายจริง
        .'<script>document.fonts&&document.fonts.ready.then(f);f();function f(){var s=document.querySelectorAll(".sig");for(var i=0;i<s.length;i++){var c=s[i].querySelector(".cl");s[i].querySelector(".si").style.setProperty("--half",(c.getBoundingClientRect().width/2)+"px");}}</script>'
        .'</body></html>';
    exit;
}
