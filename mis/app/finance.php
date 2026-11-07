<?php
/**
 * mis/app/finance.php
 * โมดูล ระบบรับชำระเงินค่าบำรุงการศึกษา
 * รายการค่าบำรุง: {mis_data} kind=fee_item (แยกภาคเรียน)  การชำระเงิน: kind=pay, r1=นักเรียน, r2=ภาคเรียน
 */
defined('MIS') or exit;

function finance_menu(): array
{
    return [
        'ข้อมูลหลัก' => ['school' => 'ตั้งค่าข้อมูลพื้นฐานในโรงเรียน', 'admins' => 'กำหนดผู้ดูแลระบบและสิทธิ์การใช้ระบบ', 'year' => 'กำหนดปีการศึกษา', 'term' => 'กำหนดภาคเรียน',
            'room' => 'กำหนดระดับชั้นและห้องเรียน', 'student_import' => 'โอนรายชื่อนักเรียนจากไฟล์ Excel', 'student_class' => 'จัดชั้นเรียน', 'promote' => 'ย้ายห้อง / เลื่อนชั้นเรียน', 'fee_item' => 'กำหนดรายการค่าบำรุงการศึกษา'],
        'รับชำระเงินค่าเทอม' => ['invoice' => 'ออกใบแจ้งชำระเงินรายคนและรายกลุ่ม', 'pay' => 'รับชำระเงินที่โรงเรียน', 'bank_import' => 'โอนข้อมูลชำระเงินจากการชำระผ่านธนาคาร',
            'receipt' => 'ออกใบเสร็จรับเงินแบบรายคน', 'receipt_obec' => 'ออกใบเสร็จ สพฐ.', 'receipt_day' => 'ออกใบเสร็จรับเงินรายวัน (พร้อมกันหลายคน)', 'pay_check' => 'ตรวจสอบการชำระเงินของนักเรียน'],
        'รายงานการชำระเงิน' => ['pay_daily' => 'รายงานการชำระเงินรายวัน', 'pay_class' => 'รายงานการชำระเงินแยกชั้นเรียน', 'pay_year' => 'รายงานการชำระเงินแยกปีการศึกษา',
            'pay_due' => 'รายงานค้างชำระเงิน แยกห้อง แยกชั้นเรียน', 'pay_total' => 'รายงานสรุปรวม'],
    ];
}

function adm_fee_item(bool $post)
{
    return md_page('fee_item', 'fee_item', 'รายการค่าบำรุงการศึกษา', ['name' => f_text('รายการ', true), 'class' => f_sel('ระดับชั้น', fn() => ['0' => 'ทุกระดับชั้น'] + cats('class')), 'amount' => f_num('จำนวนเงิน (บาท)'),
        'group' => f_sel('ประเภทใบเสร็จ', ['school' => 'ใบเสร็จโรงเรียน', 'obec' => 'ใบเสร็จ สพฐ. (เงินอุดหนุน)'], false)],
        ['name' => 'รายการ', 'class' => 'ระดับชั้น', 'amount' => 'บาท', 'group' => 'ใบเสร็จ'], $post, yt());
}

/** รายการที่นักเรียนชั้นนี้ต้องชำระ: [[name, amount, group], ...] */
function fee_items(int $class): array
{
    static $all = null;
    $all ??= array_map('json_of', md_rows('fee_item', null, yt()));
    return array_values(array_filter($all, fn($d) => in_array((string) ($d['class'] ?? '0'), ['0', '', (string) $class], true)));
}

function fee_due(int $class, string $group = ''): float
{
    return array_sum(array_map(fn($d) => (float) ($d['amount'] ?? 0), array_filter(fee_items($class), fn($d) => $group === '' || ($d['group'] ?? 'school') === $group)));
}

/** การชำระของภาคเรียนนี้ [sid => data] */
function payments(string $scope = ''): array
{
    static $c = [];
    $scope = $scope ?: yt();
    if (!isset($c[$scope])) {
        $c[$scope] = [];
        foreach (md_rows('pay', null, $scope) as $r) {
            $c[$scope][(int) $r['r1']] = json_of($r) + ['sid' => (int) $r['r1']];
        }
    }
    return $c[$scope];
}

function money($v): string
{
    return number_format((float) $v, 2);
}

function pay_record(int $sid, float $amount, string $date, string $method, string $book = '', string $no = '', string $ref = ''): void
{
    md_set('pay', $sid, yt(), '', json_encode(['amount' => $amount, 'date' => $date, 'method' => $method, 'book' => $book, 'no' => $no, 'ref' => $ref, 'by' => (int) me()['id']], JSON_UNESCAPED_UNICODE));
}

const PAY_METHOD = ['cash' => 'เงินสด (ที่โรงเรียน)', 'bank' => 'ชำระผ่านธนาคาร', 'transfer' => 'โอนเงิน'];

function adm_pay(bool $post)
{
    $room = (int) ($_GET['room'] ?? 0);
    if (isset($_GET['cancel'])) {
        md_set('pay', (int) $_GET['cancel'], yt(), '', null);
        admin_done('pay', '&room='.$room);
    }
    if ($post) {
        $date = be_date_to_iso((string) ($_POST['date'] ?? ''), date('Y-m-d'));
        $paid = payments();
        foreach ((array) ($_POST['p'] ?? []) as $sid => $v) {
            if (!empty($v['on']) && !isset($paid[(int) $sid])) {
                pay_record((int) $sid, (float) str_replace(',', '', (string) ($v['amount'] ?? 0)), $date, (string) ($_POST['method'] ?? 'cash'), mb_substr((string) ($v['book'] ?? ''), 0, 20), mb_substr((string) ($v['no'] ?? ''), 0, 20));
            }
        }
        admin_done('pay', '&room='.$room);
    }
    $f = pick_form('pay', ['room' => ['ห้อง', room_opts(false), $room]]);
    if (!$room) {
        return $f.msg_box(fee_items(0) || md_rows('fee_item', null, yt()) ? 'เลือกห้องเพื่อรับชำระเงิน' : 'ยังไม่ได้กำหนดรายการค่าบำรุงการศึกษาของภาคเรียนนี้ — <a href="'.a_url('fee_item').'">กำหนดรายการค่าบำรุง</a>', true);
    }
    $paid = payments();
    $rows = '';
    $sum = 0;
    foreach (students_q($room) as $i => $s) {
        $id = (int) $s['id'];
        $due = fee_due((int) $s['class']);
        $p = $paid[$id] ?? null;
        $sum += $p ? (float) $p['amount'] : 0;
        $rows .= zrow([$i + 1, h($s['student_id']), name_nbsp($s['name']), [money($due), ' align="right"'],
            [$p ? '<img src="../images/1347351892_yes.png" height="14"/>' : '<input type="checkbox" name="p['.$id.'][on]" value="1"/>', ' align="center"'],
            [$p ? money($p['amount']) : inp('p['.$id.'][amount]', $due, 80), ' align="right"'], [$p ? th_date($p['date'], ' ') : '', ' align="center"'],
            [$p ? h($p['book']) : inp('p['.$id.'][book]', '', 50), ' align="center"'], [$p ? h($p['no']) : inp('p['.$id.'][no]', '', 50), ' align="center"'],
            [$p ? '<a href="'.a_url('receipt', '&print=1&student='.$id).'" target="_blank">ใบเสร็จ</a> | <a href="'.a_url('pay', '&room='.$room.'&cancel='.$id).'" onclick="return confirm(\'ยกเลิกการชำระเงินรายการนี้?\')">ยกเลิก</a>' : '', ' align="center"']], $i);
    }
    $bar = 'วันที่ชำระ '.d_in('date', date('Y-m-d')).' วิธีชำระ '.sel('method', PAY_METHOD, 'cash').' <a href="javascript:void(0)" onclick="document.forms.payform.submit()" id="pinkgreenbtns"><img src="../icon_tools/vec_add_white.png" height="16"/> บันทึกการรับชำระ</a>';
    return $f.'<form id="payform" method="post" action="'.h(a_url('pay', '&room='.$room)).'">'.list_table('รายการห้องเรียน '.h(room_options()[$room] ?? ''), ['#', 'รหัส', 'ชื่อ-นามสกุล', 'ยอดที่ต้องชำระ', 'ชำระ', 'จำนวนเงิน', 'วันที่ชำระ', 'เล่มที่', 'เลขที่', ''],
        $rows, 'รับแล้ว '.money($sum).' บาท', $bar).'</form>';
}

function adm_bank_import(bool $post)
{
    if ($post) {
        $n = 0;
        $err = [];
        foreach (csv_rows('csv') as $i => $r) {
            if (count($r) < 2 || !preg_match('/\d/', $r[0])) {
                continue;
            }
            $s = q1('SELECT `id` FROM {student} WHERE `student_id`=?', [$r[0]]);
            if (!$s) {
                $err[] = 'แถว '.($i + 1).': ไม่พบนักเรียน '.$r[0];
                continue;
            }
            pay_record((int) $s['id'], (float) str_replace(',', '', $r[1]), be_date_to_iso((string) ($r[2] ?? ''), date('Y-m-d')), 'bank', '', '', mb_substr((string) ($r[3] ?? ''), 0, 40));
            $n++;
        }
        return msg_box('นำเข้าข้อมูลการชำระเงิน '.$n.' รายการ'.($err ? '<br>'.h(implode(' / ', array_slice($err, 0, 10))) : ''), !$err).adm_bank_import(false);
    }
    return upload_form('bank_import', 'โอนข้อมูลชำระเงินจากธนาคาร', 'ไฟล์ CSV จากธนาคาร/Excel คอลัมน์: <b>รหัสนักเรียน, จำนวนเงิน, วันที่ชำระ (วว/ดด/ปปปป), เลขอ้างอิง</b><br>บันทึกเป็นการชำระของภาคเรียน '.yt().' (ถ้ามีข้อมูลเดิมจะถูกแทนที่)');
}

/** ใบเสร็จ 1 ใบ */
function receipt_html(array $s, array $p, string $group = ''): string
{
    $items = array_filter(fee_items((int) $s['class']), fn($d) => $group === '' || ($d['group'] ?? 'school') === $group);
    $tr = '';
    $sum = 0;
    foreach (array_values($items) as $i => $d) {
        $sum += (float) $d['amount'];
        $tr .= '<tr><td class="c">'.($i + 1).'</td><td>'.h($d['name']).'</td><td style="text-align:right">'.money($d['amount']).'</td></tr>';
    }
    if ($group === '' && abs($sum - (float) $p['amount']) > 0.009) {
        $tr .= '<tr><td></td><td>ปรับยอดตามที่ชำระจริง</td><td style="text-align:right">'.money((float) $p['amount'] - $sum).'</td></tr>';
        $sum = (float) $p['amount'];
    }
    $title = $group === 'obec' ? 'ใบเสร็จรับเงิน (เงินอุดหนุน สพฐ.)' : 'ใบเสร็จรับเงิน';
    return '<div style="page-break-after:always;border:1px solid #000;padding:15px;margin-bottom:15px"><div style="display:flex;justify-content:space-between;align-items:center"><img src="'.logo_url().'" style="height:60px"/>'
        .'<div style="text-align:center"><h1 style="margin:0">'.h($title).'</h1><b>'.h(cfg('school_name', 'โรงเรียนดรุณราษฎร์วิทยา')).'</b><br><small>'.h(cfg('address', '')).'</small></div><div style="font-size:12px">เล่มที่ '.h($p['book'] ?: '-').'<br>เลขที่ '.h($p['no'] ?: '-').'</div></div>'
        .'<p>วันที่ '.th_date($p['date'], ' ').' &nbsp; ได้รับเงินจาก <b>'.h($s['name']).'</b> รหัส '.h($s['student_id']).' ชั้น '.h(class_room_short($s['class'], $s['room'])).'<br>ค่าบำรุงการศึกษา ภาคเรียนที่ '.h(explode('/', yt())[1]).' ปีการศึกษา '.h(explode('/', yt())[0])
        .' &nbsp; ชำระโดย '.h(PAY_METHOD[$p['method'] ?? 'cash'] ?? '').'</p><table><tr><th style="width:40px">ที่</th><th>รายการ</th><th style="width:120px">จำนวนเงิน (บาท)</th></tr>'.$tr
        .'<tr><th colspan="2" style="text-align:right">รวมเงิน</th><th style="text-align:right">'.money($sum).'</th></tr></table>'.signs(['ผู้รับเงิน']).'</div>';
}

function receipt_page(string $option, string $group)
{
    $sid = (int) ($_GET['student'] ?? 0);
    if (isset($_GET['print']) && ($s = student_head($sid))) {
        $p = payments()[$sid] ?? null;
        print_page('ใบเสร็จรับเงิน', $p ? receipt_html($s, $p, $group) : '<p>นักเรียนคนนี้ยังไม่ได้ชำระเงินในภาคเรียน '.yt().'</p>');
        return null;
    }
    $paid = payments();
    $opts = array_filter(student_options(), fn($k) => isset($paid[(int) $k]), ARRAY_FILTER_USE_KEY);
    return '<form method="get" target="_blank" style="width:983px;margin:0 auto 10px;text-align:left"><input type="hidden" name="module" value="admin"/><input type="hidden" name="option" value="'.$option.'"/><input type="hidden" name="print" value="1"/><span class="search">นักเรียนที่ชำระแล้ว '
        .sel('student', ['' => '- เลือกนักเรียน -'] + $opts, '', 'style="width:450px;height:25px"').' <input type="submit" value="พิมพ์ใบเสร็จ" class="general radius" style="height:25px"/></span><div style="clear:both"></div></form>'
        .'<p style="width:983px;margin:0 auto;text-align:left" class="gray_12">'.count($opts).' คนที่ชำระเงินแล้วในภาคเรียน '.yt().($group === 'obec' ? ' (แสดงเฉพาะรายการประเภทใบเสร็จ สพฐ.)' : '').'</p>';
}

function adm_receipt(bool $post) { return receipt_page('receipt', ''); }
function adm_receipt_obec(bool $post) { return receipt_page('receipt_obec', 'obec'); }

function adm_receipt_day(bool $post)
{
    $date = be_date_to_iso((string) ($_GET['date'] ?? ''), date('Y-m-d'));
    $list = array_filter(payments(), fn($p) => ($p['date'] ?? '') === $date);
    if (isset($_GET['print'])) {
        $html = '';
        foreach ($list as $sid => $p) {
            if ($s = student_head($sid)) {
                $html .= receipt_html($s, $p);
            }
        }
        print_page('ใบเสร็จรับเงินรายวัน', $html ?: '<p>ไม่มีการรับชำระในวันที่ '.th_date($date, ' ').'</p>');
        return null;
    }
    return '<form method="get" target="_blank" style="width:983px;margin:0 auto 10px;text-align:left"><input type="hidden" name="module" value="admin"/><input type="hidden" name="option" value="receipt_day"/><input type="hidden" name="print" value="1"/><span class="search">วันที่ชำระ '
        .d_in('date', $date).' <input type="submit" value="พิมพ์ใบเสร็จทั้งหมดของวัน" class="general radius" style="height:25px"/></span><div style="clear:both"></div></form>'
        .'<p style="width:983px;margin:0 auto;text-align:left" class="gray_12">วันที่ '.th_date($date, ' ').' มีการรับชำระ '.count($list).' รายการ</p>';
}

function adm_invoice(bool $post)
{
    $room = (int) ($_GET['room'] ?? 0);
    $sid = (int) ($_GET['student'] ?? 0);
    if (isset($_GET['print'])) {
        $list = $sid ? [student_head($sid)] : students_q($room);
        $html = '';
        foreach (array_filter($list) as $s) {
            $tr = '';
            foreach (fee_items((int) $s['class']) as $i => $d) {
                $tr .= '<tr><td class="c">'.($i + 1).'</td><td>'.h($d['name']).'</td><td style="text-align:right">'.money($d['amount']).'</td></tr>';
            }
            $html .= '<div style="page-break-after:always;border:1px solid #000;padding:15px;margin-bottom:15px">'.doc_head('ใบแจ้งชำระเงินค่าบำรุงการศึกษา', 'ภาคเรียน '.yt())
                .'<p>ชื่อ <b>'.h($s['name']).'</b> รหัส '.h($s['student_id']).' ชั้น '.h(class_room_short($s['class'], $s['room'])).'</p><table><tr><th style="width:40px">ที่</th><th>รายการ</th><th style="width:120px">จำนวนเงิน (บาท)</th></tr>'
                .($tr ?: '<tr><td colspan="3" class="c">-</td></tr>').'<tr><th colspan="2" style="text-align:right">รวมที่ต้องชำระ</th><th style="text-align:right">'.money(fee_due((int) $s['class'])).'</th></tr></table>'
                .'<p style="font-size:12px">ชำระได้ที่ฝ่ายการเงินของโรงเรียน หรือโอนผ่านธนาคาร (แจ้งรหัสนักเรียนเป็นเลขอ้างอิง)</p></div>';
        }
        print_page('ใบแจ้งชำระเงิน', $html ?: '<p>ไม่พบข้อมูล</p>');
        return null;
    }
    return '<form method="get" target="_blank" style="width:983px;margin:0 auto 10px;text-align:left"><input type="hidden" name="module" value="admin"/><input type="hidden" name="option" value="invoice"/><input type="hidden" name="print" value="1"/><span class="search">'
        .'รายกลุ่ม (ห้อง) '.sel('room', room_opts(false), 0).' หรือรายคน '.sel('student', ['0' => '- ทั้งห้อง -'] + student_options(), 0, 'style="width:330px;height:25px"')
        .' <input type="submit" value="ออกใบแจ้งชำระเงิน" class="general radius" style="height:25px"/></span><div style="clear:both"></div></form>';
}

function adm_pay_check(bool $post)
{
    $sid = (int) ($_GET['student'] ?? 0);
    $f = pick_form('pay_check', ['student' => ['นักเรียน', ['' => '- เลือกนักเรียน -'] + student_options(), $sid]]);
    if (!$sid || !($s = student_head($sid))) {
        return $f;
    }
    $rows = '';
    $i = 0;
    md_ensure();
    foreach (q('SELECT `r2`,`value` FROM {mis_data} WHERE `kind`=\'pay\' AND `r1`=? ORDER BY `r2` DESC', [$sid]) as $r) {
        $p = json_decode((string) $r['value'], true) ?: [];
        $rows .= zrow([++$i, h($r['r2']), [money($p['amount'] ?? 0), ' align="right"'], th_date($p['date'] ?? date('Y-m-d'), ' '), h(PAY_METHOD[$p['method'] ?? ''] ?? ''), h(trim(($p['book'] ?? '').'/'.($p['no'] ?? ''), '/')), h($p['ref'] ?? '')], $i);
    }
    $due = fee_due((int) $s['class']);
    $p = payments()[$sid] ?? null;
    $status = $p ? '<span style="color:#27AE60;font-weight:bold">ชำระแล้ว</span>' : '<span style="color:#E74C3C;font-weight:bold">ค้างชำระ '.money($due).' บาท</span>';
    return $f.msg_box(h($s['name']).' ('.h(class_room_short($s['class'], $s['room'])).') ภาคเรียน '.yt().' ยอดที่ต้องชำระ '.money($due).' บาท — '.$status, (bool) $p)
        .list_table('ประวัติการชำระเงิน', ['#', 'ภาคเรียน', 'จำนวนเงิน', 'วันที่', 'วิธีชำระ', 'เล่ม/เลขที่', 'อ้างอิง'], $rows);
}

/* ---------------------------------------------------------------- รายงาน */

function adm_pay_daily(bool $post)
{
    [$from, $to] = date_range();
    $st = student_options();
    $list = array_filter(payments(), fn($p) => ($p['date'] ?? '') >= $from && ($p['date'] ?? '') <= $to);
    uasort($list, fn($a, $b) => strcmp($a['date'], $b['date']));
    $rows = '';
    $i = 0;
    $sum = 0;
    $byDay = [];
    foreach ($list as $sid => $p) {
        $sum += (float) $p['amount'];
        $byDay[$p['date']] = ($byDay[$p['date']] ?? 0) + (float) $p['amount'];
        $rows .= zrow([++$i, th_date($p['date'], ' '), h($st[$sid] ?? ''), h(PAY_METHOD[$p['method'] ?? ''] ?? ''), h(trim(($p['book'] ?? '').'/'.($p['no'] ?? ''), '/')), [money($p['amount']), ' align="right"']], $i);
    }
    $rows .= zrow(['', '<b>รวม</b>', '', '', '', ['<b>'.money($sum).'</b>', ' align="right"']], ++$i);
    $rows2 = '';
    $j = 0;
    foreach ($byDay as $d => $v) {
        $rows2 .= zrow([th_date($d, ' '), [money($v), ' align="right"']], $j++);
    }
    return range_form('pay_daily', $from, $to).list_table('รายงานการชำระเงินรายวัน '.th_date($from, ' ').' - '.th_date($to, ' '), ['#', 'วันที่', 'นักเรียน', 'วิธีชำระ', 'เล่ม/เลขที่', 'จำนวนเงิน'], $rows, count($list).' รายการ', print_btn())
        .'<div style="height:10px"></div>'.list_table('สรุปยอดรายวัน', ['วันที่', 'จำนวนเงิน'], $rows2);
}

/** สรุปการชำระแยกห้อง: [cid => [rid => [n, paid, amount, due]]] */
function pay_by_room(): array
{
    $paid = payments();
    $out = [];
    foreach (students_q() as $s) {
        $x = &$out[(int) $s['class']][(int) $s['room']];
        $x = $x ?? [0, 0, 0.0, 0.0];
        $x[0]++;
        if (isset($paid[(int) $s['id']])) {
            $x[1]++;
            $x[2] += (float) $paid[(int) $s['id']]['amount'];
        } else {
            $x[3] += fee_due((int) $s['class']);
        }
        unset($x);
    }
    return $out;
}

function adm_pay_class(bool $post)
{
    $html = '';
    foreach (pay_by_room() as $cid => $rooms) {
        $rows = '';
        $t = [0, 0, 0.0];
        $i = 0;
        foreach ($rooms as $rid => [$n, $p, $amt]) {
            $t[0] += $n;
            $t[1] += $p;
            $t[2] += $amt;
            $rows .= zrow([h(class_room_short($cid, $rid)), [$n, ' align="center"'], [$p, ' align="center"'], [$n ? number_format($p * 100 / $n, 2) : '-', ' align="center"'], [money($amt), ' align="right"']], $i++);
        }
        $rows .= zrow(['<b>รวม</b>', ['<b>'.$t[0].'</b>', ' align="center"'], ['<b>'.$t[1].'</b>', ' align="center"'], ['<b>'.($t[0] ? number_format($t[1] * 100 / $t[0], 2) : '-').'</b>', ' align="center"'], ['<b>'.money($t[2]).'</b>', ' align="right"']], $i);
        $html .= list_table('ระดับชั้น '.h(cat('class', $cid) ?: 'ไม่ระบุ'), ['ห้อง', 'จำนวนคน', 'ชำระแล้ว', 'ร้อยละ', 'จำนวนเงิน'], $rows).'<div style="height:10px"></div>';
    }
    return '<div style="width:983px;margin:0 auto 8px;text-align:right">'.print_btn().'</div>'.($html ?: list_table('สรุปการชำระเงินแยกชั้นเรียน', ['ห้อง'], ''));
}

function adm_pay_year(bool $post)
{
    md_ensure();
    $rows = '';
    $agg = [];
    foreach (q('SELECT `r2`, `value` FROM {mis_data} WHERE `kind`=\'pay\' ORDER BY `r2` DESC') as $r) {
        $agg[$r['r2']][0] = ($agg[$r['r2']][0] ?? 0) + 1;
        $agg[$r['r2']][1] = ($agg[$r['r2']][1] ?? 0) + (float) (json_decode((string) $r['value'], true)['amount'] ?? 0);
    }
    $i = 0;
    foreach ($agg as $k => [$n, $amt]) {
        [$y, $t] = explode('/', $k) + ['', ''];
        $rows .= zrow([++$i, h($y), h($t), [$n, ' align="center"'], [money($amt), ' align="right"']], $i);
    }
    return list_table('รายงานการชำระเงินแยกปีการศึกษา', ['#', 'ปีการศึกษา', 'ภาคเรียน', 'จำนวนผู้ชำระ', 'จำนวนเงิน'], $rows, '', print_btn());
}

function adm_pay_due(bool $post)
{
    $room = (int) ($_GET['room'] ?? 0);
    $class = (int) ($_GET['class'] ?? 0);
    $paid = payments();
    $rows = '';
    $i = 0;
    $sum = 0;
    foreach (students_q($room, $class) as $s) {
        if (isset($paid[(int) $s['id']])) {
            continue;
        }
        $due = fee_due((int) $s['class']);
        $sum += $due;
        $rows .= zrow([++$i, h($s['student_id']), name_nbsp($s['name']), h(class_room_short($s['class'], $s['room'])), h($s['parent_phone']), [money($due), ' align="right"']], $i);
    }
    return pick_form('pay_due', ['class' => ['ระดับชั้น', ['0' => '- ทุกชั้น -'] + cats('class'), $class], 'room' => ['ห้อง', room_opts(), $room]])
        .list_table('รายงานค้างชำระเงิน ภาคเรียน '.yt(), ['#', 'รหัส', 'ชื่อ-นามสกุล', 'ชั้น', 'โทรผู้ปกครอง', 'ยอดค้างชำระ'], $rows, $i.' คน รวม '.money($sum).' บาท', print_btn());
}

function adm_pay_total(bool $post)
{
    $paid = payments();
    $n = 0;
    $p = 0;
    $amt = 0.0;
    $due = 0.0;
    foreach (pay_by_room() as $rooms) {
        foreach ($rooms as [$a, $b, $c, $d]) {
            $n += $a;
            $p += $b;
            $amt += $c;
            $due += $d;
        }
    }
    $m = [];
    foreach ($paid as $x) {
        $m[$x['method'] ?? 'cash'] = ($m[$x['method'] ?? 'cash'] ?? 0) + (float) $x['amount'];
    }
    $rows = '';
    $i = 0;
    foreach (['นักเรียนทั้งหมด' => $n.' คน', 'ชำระแล้ว' => $p.' คน ('.($n ? number_format($p * 100 / $n, 2) : '0.00').'%)', 'ค้างชำระ' => ($n - $p).' คน',
        'ยอดเงินที่รับแล้ว' => money($amt).' บาท', 'ยอดค้างชำระ' => money($due).' บาท'] as $l => $v) {
        $rows .= zrow([++$i, h($l), ['<b>'.$v.'</b>', ' align="right"']], $i);
    }
    foreach (PAY_METHOD as $k => $l) {
        $rows .= zrow([++$i, 'รับชำระโดย'.h($l), [money($m[$k] ?? 0).' บาท', ' align="right"']], $i);
    }
    return list_table('รายงานสรุปรวมการชำระเงินค่าบำรุงการศึกษา ภาคเรียน '.yt(), ['#', 'รายการ', ['จำนวน', ' style="text-align:right"']], $rows, '', print_btn());
}
