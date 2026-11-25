<?php
/**
 * student/customer.php
 * ผู้สมัคร / ผู้ซื้อบริการ ในหน้าระบบนักเรียน
 *  - ?p=signup  สมัครใช้บริการ (รอแอดมินอนุมัติที่โมดูลรับชำระเงิน > ผู้สมัคร / ผู้ซื้อบริการ)
 *  - เข้าระบบหน้าเดียวกับนักเรียน ด้วยอีเมลหรือเบอร์โทร + รหัสผ่าน
 *  - หลังเข้าระบบเห็นเฉพาะ: ภาพรวม / ยอดที่ต้องชำระ (QR + แนบสลิป) / สัญญา / ใบเสร็จ / ข้อมูลผู้สมัคร (ไม่มีหน้าวิชาการ)
 * ข้อมูลและตัวช่วยหลักอยู่ที่ mis/app/payment.php
 */
defined('MIS') or exit;

const CU_MENU = ['home' => ['fa-home', 'ภาพรวม'], 'bills' => ['fa-money', 'ยอดที่ต้องชำระ'], 'contracts' => ['fa-file-text-o', 'สัญญา'], 'receipts' => ['fa-list-alt', 'ใบเสร็จรับเงิน'], 'profile' => ['fa-user', 'ข้อมูลผู้สมัคร']];

function cu_doc(string $cur, string $title, string $body): void
{
    mis_headers();
    if (isset($_GET['frag'])) {
        // โหลดเนื้อหาในหน้าเดิม (เหมือนหน้านักเรียน)
        header('X-Page-Title: '.rawurlencode($title.' | '.st_school()));
        echo $body;
        return;
    }
    $nav = '';
    foreach (CU_MENU as $k => [$ic, $l]) {
        $nav .= '<li><a href="?p='.$k.'" data-full="1"'.($k === $cur ? ' style="color:#fff"' : '').'><i class="fa '.$ic.'"></i> '.h($l).'</a></li>';
    }
    $nav .= '<li><a href="?p=logout" data-full="1"><i class="fa fa-power-off"></i> Logout</a></li>';
    $css = '<style>.nav{background:#343a40}.nav .brand{color:#fff}.nav li>a{color:rgba(255,255,255,.6)!important}.nav li>a:hover{color:#fff!important}'
        .'.st-badge{display:inline-block;padding:1px 9px;border-radius:10px;font-size:13px;color:#fff}.sum3{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:16px}'
        .'.sum3 div{background:#fff;border-radius:6px;padding:14px;text-align:center}.sum3 b{display:block;font-size:26px}@media(max-width:700px){.sum3{grid-template-columns:1fr}}</style>';
    echo st_doc($title.' | '.st_school(), $css.'<nav class="nav dark"><div class="nin"><a class="brand" href="./">DRW-MIS</a><button type="button" class="tog" aria-label="เมนู"><i class="fa fa-bars"></i></button><ul>'.$nav.'</ul></div></nav>'
        .'<header class="hero"><h1>Payment &amp; Contract</h1><hr></header><main class="wrap"><div class="box">'.$body.'</div></main>');
}

function cu_badge(string $s, array $map): string
{
    $col = ['unpaid' => '#B9770E', 'pending' => '#1F618D', 'paid' => '#1E8449', 'approved' => '#1E8449', 'cancel' => '#7F8C8D', 'rejected' => '#C0392B'][$s] ?? '#555';
    return '<span class="st-badge" style="background:'.$col.'">'.h($map[$s] ?? $s).'</span>';
}

/** สัญญาของผู้สมัคร (หนังสือสัญญาในงานสารบรรณที่เลือกผู้สมัครนี้เป็นคู่สัญญา) */
function cu_contracts(string $cid): array
{
    $o = [];
    foreach (md_rows('sb_doc') as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        if (($d['type'] ?? '') === 'contract' && (string) ($d['ct_cust'] ?? '') === $cid) {
            $o[(string) $r['r1']] = $d;
        }
    }
    return $o;
}

/** สลิปล่าสุดของยอดนี้ */
function cu_last_slip(string $owner, string $ref): ?array
{
    foreach (slip_all($owner) as $s) {
        if ((string) $s['ref'] === $ref) {
            return $s;
        }
    }
    return null;
}

/* ---------------------------------------------------------------- สมัครใช้บริการ */

function cu_signup(): void
{
    $msg = '';
    $done = false;
    $d = [];
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $ipk = substr(hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '')), 0, 40);
        $hits = array_values(array_filter(json_decode((string) md_get('cust_rl', $ipk), true) ?: [], fn($t) => $t > time() - 3600));
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            $done = true; // ช่องดักบอท: ไม่บันทึก แต่ตอบเหมือนสำเร็จ
        } elseif (count($hits) >= 5) {
            $msg = 'สมัครหลายครั้งเกินไป กรุณาลองใหม่ภายหลัง';
        } else {
            [$d, $msg] = cust_form_read([], '', false);
            if ($msg === '' && empty($_POST['accept'])) {
                $msg = 'กรุณายอมรับการเก็บข้อมูลเพื่อใช้ติดต่อและออกเอกสาร';
            }
            if ($msg === '') {
                $d['status'] = 'pending';
                $d['created'] = date('Y-m-d H:i:s');
                cust_put(new_id(), $d);
                $hits[] = time();
                md_set('cust_rl', $ipk, '', '', json_encode($hits));
                $done = true;
            }
        }
    }
    $v = fn($k) => h((string) ($d[$k] ?? $_POST[$k] ?? ''));
    $f = fn($k, $label, $type = 'text', $req = true, $extra = '') => '<label class="l">'.$label.($req ? ' *' : '').'</label><input class="in" type="'.$type.'" name="'.$k.'"'.($type !== 'password' ? ' value="'.$v($k).'"' : '').($req ? ' required' : '').' '.$extra.'>';
    $form = $done ? '<div class="msg ok"><i class="fa fa-check-circle"></i> ส่งใบสมัครเรียบร้อยแล้ว — โรงเรียนจะตรวจสอบและอนุมัติบัญชี เมื่ออนุมัติแล้วเข้าสู่ระบบได้ด้วยอีเมลหรือเบอร์โทรศัพท์และรหัสผ่านที่ตั้งไว้</div><p><a class="btn" href="./">ไปหน้าเข้าสู่ระบบ</a></p>'
        : ($msg !== '' ? '<div class="msg no">'.h($msg).'</div>' : '')
        .'<form method="post" action="?p=signup" autocomplete="on"><div class="g2">'
        .'<div>'.$f('name', 'ชื่อ-นามสกุล ผู้ติดต่อ', 'text', true, 'maxlength="120"').'</div><div>'.$f('org', 'หน่วยงาน / บริษัท / ร้าน (ถ้ามี)', 'text', false, 'maxlength="160"').'</div>'
        .'<div>'.$f('email', 'อีเมล (ใช้เข้าระบบ)', 'email', true, 'maxlength="120" autocomplete="email"').'</div><div>'.$f('phone', 'เบอร์โทรศัพท์ (ใช้เข้าระบบได้)', 'tel', true, 'maxlength="12" inputmode="tel"').'</div>'
        .'<div class="s2"><label class="l">ที่อยู่ (สำหรับออกสัญญา/ใบเสร็จ)</label><textarea class="in" name="address" maxlength="400">'.$v('address').'</textarea></div>'
        .'<div>'.$f('taxid', 'เลขประจำตัวผู้เสียภาษี / เลขบัตรประชาชน (ถ้าต้องการให้พิมพ์ในเอกสาร)', 'text', false, 'maxlength="13" inputmode="numeric"').'</div><div></div>'
        .'<div class="s2"><label class="l">บริการที่สนใจ / รายละเอียด</label><textarea class="in" name="interest" maxlength="500">'.$v('interest').'</textarea></div>'
        .'<div>'.$f('password', 'ตั้งรหัสผ่าน (อย่างน้อย 8 ตัวอักษร)', 'password', true, 'minlength="8" autocomplete="new-password"').'</div><div>'.$f('password2', 'ยืนยันรหัสผ่าน', 'password', true, 'minlength="8" autocomplete="new-password"').'</div>'
        .'<div class="s2" style="position:absolute;left:-9999px" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>'
        .'<div class="s2" style="margin-top:8px"><label><input type="checkbox" name="accept" value="1" required> ยินยอมให้โรงเรียนเก็บข้อมูลข้างต้นเพื่อใช้ติดต่อ ออกใบเสนอราคา สัญญา และใบเสร็จ</label></div>'
        .'</div><p style="margin-top:14px"><button class="btn" type="submit"><i class="fa fa-paper-plane"></i> ส่งใบสมัคร</button> <a class="btn lt" href="./">กลับหน้าเข้าสู่ระบบ</a></p></form>';
    mis_headers();
    echo st_doc('สมัครใช้บริการ | '.st_school(), '<nav class="nav dark"><div class="nin"><a class="brand" href="./">DRW-MIS</a></div></nav><header class="hero"><h1>Register</h1><hr></header>'
        .'<main class="wrap"><div class="box"><div class="card"><h4 class="ttl"><i class="fa fa-user-plus"></i>สมัครใช้บริการ / ลงทะเบียนผู้สมัคร — '.h(st_school()).'</h4>'.$form.'</div></div></main>');
}

/* ---------------------------------------------------------------- หน้าหลังเข้าสู่ระบบ */

function cu_portal(array $C, string $p): void
{
    $cid = (string) $C['id'];
    $owner = 'c'.$cid;
    $id = (string) ($_GET['id'] ?? '');
    $post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    $okMsg = ['sent' => 'ส่งสลิปเรียบร้อยแล้ว ฝ่ายการเงินจะตรวจสอบและออกใบเสร็จให้', 'pw' => 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว'][(string) ($_GET['msg'] ?? '')] ?? '';
    $flash = $okMsg !== '' ? '<div class="msg ok"><i class="fa fa-check-circle"></i> '.h($okMsg).'</div>' : '';

    // ไฟล์/เอกสารของตนเองเท่านั้น
    if ($p === 'slipimg') {
        $s = slip_get($id);
        ($s && $s['owner'] === $owner) ? slip_output($s) : exit('ไม่พบไฟล์');
    }
    if ($p === 'rc') {
        $s = slip_get($id);
        if (!$s || $s['owner'] !== $owner) {
            exit('ไม่พบใบเสร็จ');
        }
        rc_output($s, '<button onclick="window.print()">🖨 พิมพ์ / บันทึกเป็น PDF</button><a href="?p=receipts">กลับ</a>');
    }
    if ($p === 'contract') {
        $all = cu_contracts($cid);
        if (!isset($all[$id])) {
            exit('ไม่พบสัญญา');
        }
        sb_print_out($id, $all[$id], '<button onclick="window.print()">🖨 พิมพ์ / บันทึกเป็น PDF</button><a href="?p=contracts">กลับ</a>');
    }

    $bills = array_filter(bill_all($cid), fn($b) => ($b['status'] ?? '') !== 'cancel');
    $billRow = function (array $b) use ($owner): string {
        $act = match ($b['status'] ?? '') {
            'unpaid' => '<a class="btn sm ok" href="?p=pay&id='.rawurlencode($b['id']).'" data-full="1"><i class="fa fa-qrcode"></i> ชำระเงิน</a>',
            'pending' => '<span class="mut">รอฝ่ายการเงินตรวจสอบ</span>',
            'paid' => ($b['slip'] ?? '') !== '' ? '<a class="btn sm info" href="?p=rc&id='.rawurlencode((string) $b['slip']).'" target="_blank"><i class="fa fa-print"></i> ใบเสร็จ</a>' : '-',
            default => '-',
        };
        $last = ($b['status'] ?? '') === 'unpaid' ? cu_last_slip($owner, $b['id']) : null;
        $rej = $last && ($last['status'] ?? '') === 'rejected' ? '<br><span class="c-bad" style="font-size:13px">สลิปล่าสุดไม่ผ่าน: '.h((string) ($last['reason'] ?? '')).' — กรุณาชำระ/แนบสลิปใหม่</span>' : '';
        return '<tr><td>'.h((string) $b['title']).$rej.'</td><td class="c">'.(($b['due'] ?? '') !== '' ? th_date((string) $b['due'], ' ') : '-').'</td><td class="r">'.money($b['amount']).'</td><td class="c">'.cu_badge((string) $b['status'], BILL_STATUS).'</td><td class="c">'.$act.'</td></tr>';
    };
    $billTable = fn(array $list) => $list ? '<div class="tbw"><table class="tb st"><tr><th>รายการ</th><th>กำหนดชำระ</th><th>ยอดเงิน (บาท)</th><th>สถานะ</th><th></th></tr>'.implode('', array_map($billRow, $list)).'</table></div>' : '<div class="empty">ไม่มีรายการ</div>';

    switch ($p) {
        case 'bills':
            cu_doc('bills', 'ยอดที่ต้องชำระ', $flash.st_card('ยอดที่ต้องชำระ', 'fa-money', $billTable($bills)));
            return;
        case 'pay':
            $b = $bills[$id] ?? null;
            if (!$b) {
                cu_doc('bills', 'ชำระเงิน', '<div class="msg no">ไม่พบรายการที่ต้องชำระ</div>');
                return;
            }
            $err = '';
            if ($post && ($b['status'] ?? '') === 'unpaid') {
                $err = slip_submit($owner, $b['id'], (float) $b['amount'], (string) $b['title']);
                if ($err === '') {
                    $b['status'] = 'pending';
                    bill_put($b['id'], $b);
                    redirect('?p=bills&msg=sent');
                }
            }
            if (($b['status'] ?? '') !== 'unpaid') {
                cu_doc('bills', 'ชำระเงิน', '<div class="msg in">รายการนี้'.h(BILL_STATUS[$b['status']] ?? '').'</div><p><a class="btn" href="?p=bills">กลับ</a></p>');
                return;
            }
            cu_doc('bills', 'ชำระเงิน', st_card('ชำระเงิน: '.(string) $b['title'], 'fa-qrcode', cu_pay_box((float) $b['amount'], '?p=pay&id='.rawurlencode($b['id']), $err)));
            return;
        case 'contracts':
            $rows = '';
            foreach (cu_contracts($cid) as $k => $d) {
                $amt = (float) ($d['ct_amount'] ?? 0);
                $rows .= '<tr><td>สัญญา'.h(preg_replace('/^สัญญา/u', '', (string) ($d['title'] ?? ''))).'</td><td class="c">'.h((string) ($d['no'] ?? '-')).'</td><td class="c">'.(($d['date'] ?? '') !== '' ? th_date((string) $d['date'], ' ') : '-').'</td>'
                    .'<td class="r">'.($amt > 0 ? money($amt).' / '.h(CT_PLANS[$d['ct_plan'] ?? 'once'] ?? '') : '-').'</td><td class="c"><a class="btn sm info" href="?p=contract&id='.rawurlencode($k).'" target="_blank"><i class="fa fa-file-text-o"></i> ดู / พิมพ์</a></td></tr>';
            }
            cu_doc('contracts', 'สัญญา', st_card('สัญญาของฉัน', 'fa-file-text-o', $rows !== '' ? '<div class="tbw"><table class="tb st"><tr><th>สัญญา</th><th>เลขที่</th><th>วันที่</th><th>ค่าตอบแทน (บาท)</th><th></th></tr>'.$rows.'</table></div>' : '<div class="empty">ยังไม่มีสัญญา</div>'));
            return;
        case 'receipts':
            $rows = '';
            foreach (slip_all($owner) as $k => $s) {
                $rows .= '<tr><td>'.h(substr((string) $s['at'], 0, 16)).'</td><td>'.h((string) $s['title']).'</td><td class="r">'.money($s['amount']).'</td><td class="c">'.cu_badge((string) $s['status'], SLIP_STATUS)
                    .(($s['status'] ?? '') === 'rejected' ? '<br><span class="c-bad" style="font-size:13px">'.h((string) ($s['reason'] ?? '')).'</span>' : '').'</td>'
                    .'<td class="c"><a href="?p=slipimg&id='.rawurlencode($k).'" target="_blank">สลิป</a>'.(($s['status'] ?? '') === 'approved' ? ' · <a href="?p=rc&id='.rawurlencode($k).'" target="_blank"><b>ใบเสร็จ '.h((string) $s['rc_no']).'</b></a>' : '').'</td></tr>';
            }
            cu_doc('receipts', 'ใบเสร็จรับเงิน', st_card('ประวัติการชำระเงิน / ใบเสร็จ', 'fa-list-alt', $rows !== '' ? '<div class="tbw"><table class="tb st"><tr><th>ส่งสลิปเมื่อ</th><th>รายการ</th><th>ยอดเงิน</th><th>สถานะ</th><th></th></tr>'.$rows.'</table></div>' : '<div class="empty">ยังไม่มีประวัติการชำระเงิน</div>'));
            return;
        case 'profile':
            $err = '';
            if ($post) {
                $cur = (string) ($_POST['cur'] ?? '');
                $n1 = (string) ($_POST['new1'] ?? '');
                if (!password_verify($cur, (string) ($C['pw'] ?? ''))) {
                    $err = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
                } elseif (strlen($n1) < 8 || $n1 !== (string) ($_POST['new2'] ?? '')) {
                    $err = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร และยืนยันให้ตรงกัน';
                } else {
                    $C['pw'] = password_hash($n1, PASSWORD_DEFAULT);
                    cust_put($cid, $C);
                    redirect('?p=profile&msg=pw');
                }
            }
            $kv = '<div class="kv"><div><b>ชื่อผู้ติดต่อ :</b> '.h((string) $C['name']).'</div><div><b>หน่วยงาน :</b> '.h((string) ($C['org'] ?: '-')).'</div><div><b>อีเมล :</b> '.h((string) $C['email']).'</div><div><b>โทรศัพท์ :</b> '.h((string) $C['phone']).'</div>'
                .'<div><b>ที่อยู่ :</b> '.nl2br(h((string) ($C['address'] ?: '-'))).'</div><div><b>เลขประจำตัวผู้เสียภาษี/บัตร :</b> '.h((string) ($C['taxid'] ?: '-')).'</div></div><p class="mut" style="font-size:14px">ต้องการแก้ไขข้อมูล กรุณาติดต่อโรงเรียน</p>';
            $pw = ($err !== '' ? '<div class="msg no">'.h($err).'</div>' : '').'<form method="post" action="?p=profile"><div class="g3"><div><label class="l">รหัสผ่านปัจจุบัน</label><input class="in" type="password" name="cur" required autocomplete="current-password"></div>'
                .'<div><label class="l">รหัสผ่านใหม่</label><input class="in" type="password" name="new1" minlength="8" required autocomplete="new-password"></div><div><label class="l">ยืนยันรหัสผ่านใหม่</label><input class="in" type="password" name="new2" minlength="8" required autocomplete="new-password"></div></div>'
                .'<p style="margin-top:10px"><button class="btn" type="submit">เปลี่ยนรหัสผ่าน</button></p></form>';
            cu_doc('profile', 'ข้อมูลผู้สมัคร', $flash.st_card('ข้อมูลผู้สมัคร', 'fa-user', $kv).st_card('เปลี่ยนรหัสผ่าน', 'fa-key', $pw));
            return;
    }
    // ภาพรวม
    $due = $pend = $paid = 0.0;
    foreach ($bills as $b) {
        match ($b['status'] ?? '') {
            'unpaid' => $due += (float) $b['amount'],
            'pending' => $pend += (float) $b['amount'],
            'paid' => $paid += (float) $b['amount'],
            default => null,
        };
    }
    $open = array_filter($bills, fn($b) => in_array($b['status'] ?? '', ['unpaid', 'pending'], true));
    cu_doc('home', 'ภาพรวม', $flash.'<div class="card"><h4 class="ttl"><i class="fa fa-user"></i>สวัสดี '.h(cust_label($C)).'</h4><div class="sum3">'
        .'<div><span class="mut">ยอดค้างชำระ</span><b class="c-bad">'.money($due).'</b>บาท</div><div><span class="mut">รอตรวจสอบสลิป</span><b style="color:#1F618D">'.money($pend).'</b>บาท</div><div><span class="mut">ชำระแล้ว</span><b class="c-ok">'.money($paid).'</b>บาท</div></div>'
        .'<div class="mut" style="font-size:14px">สัญญา '.count(cu_contracts($cid)).' ฉบับ — <a href="?p=contracts">ดูสัญญา</a></div></div>'
        .st_card('รายการที่ต้องดำเนินการ', 'fa-money', $billTable($open)));
}

/** กล่องชำระเงิน: QR ตามยอด + คำเตือนชื่อผู้รับเงิน + ฟอร์มแนบสลิป (ใช้ทั้งผู้สมัครและนักเรียน) */
function cu_pay_box(float $amount, string $action, string $err): string
{
    return ($err !== '' ? '<div class="msg no">'.h($err).'</div>' : '')
        .'<div class="row r2" style="align-items:start"><div>'.pay_qr_html($amount).'</div><div>'
        .'<div style="font-size:15px;line-height:1.7"><b>ขั้นตอน</b><br>1. สแกน QR ด้วยแอปธนาคาร (ยอดเงิน '.money($amount).' บาท ใส่ให้อัตโนมัติ)<br>2. <b style="color:#d00">ตรวจสอบชื่อผู้รับเงินปลายทางก่อนยืนยัน</b><br>3. บันทึกสลิป แล้วแนบด้านล่าง</div>'
        .pay_warn_html().'</div></div>'
        .'<form method="post" enctype="multipart/form-data" action="'.h($action).'" style="margin-top:6px;border-top:1px solid #dee2e6;padding-top:12px"><div class="g3">'
        .'<div><label class="l">รูปสลิปการโอน (JPG / PNG ไม่เกิน 4 MB) *</label><input class="in" type="file" name="slip" accept="image/jpeg,image/png,image/webp" required style="height:auto;padding:4px"></div>'
        .'<div><label class="l">วันเวลาที่โอน</label><input class="in" type="datetime-local" name="paid_at" value="'.date('Y-m-d\TH:i').'"></div>'
        .'<div><label class="l">หมายเหตุ (ถ้ามี)</label><input class="in" name="note" maxlength="300"></div></div>'
        .'<p style="margin-top:12px"><button class="btn ok" type="submit"><i class="fa fa-upload"></i> ส่งสลิปให้ฝ่ายการเงินตรวจสอบ</button></p></form>';
}

/* ---------------------------------------------------------------- นักเรียน: ชำระค่าบำรุงการศึกษาออนไลน์ */

function st_payonline(array $S, bool $post): string
{
    $sid = (int) $S['id'];
    $owner = 's'.$sid;
    $yt = yt();
    [$y, $t] = explode('/', $yt);
    $id = (string) ($_GET['id'] ?? '');
    if (isset($_GET['img'])) {
        $s = slip_get((string) $_GET['img']);
        ($s && $s['owner'] === $owner) ? slip_output($s) : exit('ไม่พบไฟล์');
    }
    if (isset($_GET['rc'])) {
        $s = slip_get((string) $_GET['rc']);
        if (!$s || $s['owner'] !== $owner) {
            exit('ไม่พบใบเสร็จ');
        }
        rc_output($s, '<button onclick="window.print()">🖨 พิมพ์ / บันทึกเป็น PDF</button><button onclick="window.close()">ปิด</button>');
    }
    $items = fee_items((int) $S['class']);
    $due = round(array_sum(array_map(fn($d) => (float) ($d['amount'] ?? 0), $items)), 2);
    $pay = payments()[$sid] ?? null;
    $mine = slip_all($owner);
    $pending = array_filter($mine, fn($s) => $s['ref'] === $yt && $s['status'] === 'pending');
    $err = '';
    if ($post && !$pay && !$pending && $due > 0) {
        $err = slip_submit($owner, $yt, $due, 'ค่าบำรุงการศึกษา ภาคเรียนที่ '.$t.'/'.$y);
        if ($err === '') {
            st_go('payonline', '&msg=sent');
        }
    }
    $o = ($_GET['msg'] ?? '') === 'sent' ? '<div class="msg ok"><i class="fa fa-check-circle"></i> ส่งสลิปเรียบร้อยแล้ว ฝ่ายการเงินจะตรวจสอบและออกใบเสร็จให้</div>' : '';
    $rows = '';
    foreach ($items as $i => $d) {
        $rows .= '<tr><td class="c">'.($i + 1).'</td><td>'.h($d['name'] ?? '').'</td><td class="r">'.money($d['amount'] ?? 0).'</td></tr>';
    }
    $o .= '<div class="tbw"><table class="tb"><tr><th>ลำดับ</th><th>รายการ ภาคเรียนที่ '.h($t.'/'.$y).'</th><th>จำนวนเงิน (บาท)</th></tr>'.$rows.'<tr><td></td><td class="r"><b>รวม</b></td><td class="r"><b>'.money($due).'</b></td></tr></table></div>';
    if ($pay) {
        $ref = (string) ($pay['ref'] ?? '');
        $o .= '<div class="msg ok" style="margin-top:12px"><i class="fa fa-check-circle"></i> ชำระค่าบำรุงการศึกษาภาคเรียนนี้แล้ว'
            .(str_starts_with($ref, 'slip:') ? ' — <a href="?p=payonline&rc='.rawurlencode(substr($ref, 5)).'" target="_blank"><b>พิมพ์ใบเสร็จรับเงิน เลขที่ '.h((string) ($pay['no'] ?? '')).'</b></a>' : ' (ชำระที่โรงเรียน/ธนาคาร รับใบเสร็จที่ฝ่ายการเงิน)').'</div>';
    } elseif ($pending) {
        $o .= '<div class="msg in" style="margin-top:12px"><i class="fa fa-clock-o"></i> ส่งสลิปแล้ว อยู่ระหว่างฝ่ายการเงินตรวจสอบ</div>';
    } elseif ($due <= 0) {
        $o .= '<div class="empty">ไม่มียอดที่ต้องชำระในภาคเรียนนี้</div>';
    } else {
        $o .= '<div style="margin-top:14px">'.cu_pay_box($due, '?p=payonline', $err).'</div>';
    }
    if ($mine) {
        $h = '';
        foreach ($mine as $k => $s) {
            $h .= '<tr><td>'.h(substr((string) $s['at'], 0, 16)).'</td><td>'.h((string) $s['title']).'</td><td class="r">'.money($s['amount']).'</td><td class="c">'.cu_badge((string) $s['status'], SLIP_STATUS)
                .(($s['status'] ?? '') === 'rejected' ? '<br><span class="c-bad" style="font-size:13px">'.h((string) ($s['reason'] ?? '')).'</span>' : '').'</td>'
                .'<td class="c"><a href="?p=payonline&img='.rawurlencode($k).'" target="_blank">สลิป</a>'.(($s['status'] ?? '') === 'approved' ? ' · <a href="?p=payonline&rc='.rawurlencode($k).'" target="_blank">ใบเสร็จ '.h((string) $s['rc_no']).'</a>' : '').'</td></tr>';
        }
        $o .= '<h4 style="margin:18px 0 8px">ประวัติการส่งสลิป</h4><div class="tbw"><table class="tb st"><tr><th>ส่งเมื่อ</th><th>รายการ</th><th>ยอดเงิน</th><th>สถานะ</th><th></th></tr>'.$h.'</table></div>';
    }
    return $o;
}
