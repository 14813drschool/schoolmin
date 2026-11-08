<?php
/**
 * student/pages.php
 * หน้าของระบบนักเรียน (Student System) ที่ทำตามระบบ CRMS — ข้อมูลเชื่อมกับระบบสารสนเทศ (mis)
 *
 * ข้อมูลที่ระบบนักเรียนบันทึกเพิ่ม (ตาราง mis_data)
 *  st_en     r1=นักเรียน                 ชื่อ/ที่อยู่ภาษาอังกฤษ (นักเรียน บิดา มารดา ผู้ปกครอง)
 *  stu_evt   r1=นักเรียน r2=ปี/ภาค r3=รายวิชา   ประเมินครูรายวิชา
 *  stu_eva   r1=นักเรียน r2=ปี/ภาค r3=ครู      ประเมินครูที่ปรึกษา
 *  char_s / char_p  r1=นักเรียน r2=ปี/ภาค     ประเมินคุณลักษณะอันพึงประสงค์ (นักเรียน / ผู้ปกครอง)
 *  docreq    r1=เลขคำร้อง r2=นักเรียน         คำร้องขอเอกสาร (ปพ.)
 *  stu_book  r1=นักเรียน r2=ปี/ภาค           จองสินค้า/อุปกรณ์การเรียน
 *  q9 / sdq_par  r1=นักเรียน r2=ปี/ภาค        9Q / SDQ ฉบับผู้ปกครอง
 */
defined('MIS') or exit;

/* ================================================================ ตัวช่วย */

function st_saved_msg(): string
{
    return isset($_GET['saved']) ? '<div class="msg ok"><i class="fa fa-check"></i> บันทึกข้อมูลเรียบร้อยแล้ว</div>' : '';
}

function st_go(string $p, string $extra = ''): void
{
    redirect('?p='.$p.$extra.'&saved=1');
}

function st_ytl(): array
{
    [$y, $t] = array_map('intval', explode('/', yt()));
    return [$y, $t];
}

function st_name_parts(string $name): array
{
    return function_exists('split_name') ? split_name($name) : ['', $name, ''];
}

function st_en(int $sid): array
{
    return json_decode((string) md_get('st_en', $sid), true) ?: [];
}

/** ข้อมูลส่วนตัว (ปพ.6 / ทะเบียน / ข้อมูลเพิ่มเติม) */
function st_person(array $S): array
{
    $p = function_exists('pp6_person') ? pp6_person($S) : [];
    $i = stinfo((int) $S['id']);
    return [$p, $i, st_en((int) $S['id'])];
}

function st_addr(array $p, string $fallback): string
{
    $a = trim(implode(' ', array_filter([($p['house'] ?? '') !== '' ? 'บ้านเลขที่ '.$p['house'] : '', ($p['moo'] ?? '') !== '' ? 'หมู่ที่ '.$p['moo'] : '',
        ($p['tambon'] ?? '') !== '' ? 'ตำบล '.$p['tambon'] : '', ($p['amphoe'] ?? '') !== '' ? 'อำเภอ '.$p['amphoe'] : '', ($p['province'] ?? '') !== '' ? 'จังหวัด '.$p['province'] : ''])));
    return $a !== '' ? $a : $fallback;
}

function st_dash($v): string
{
    $v = trim((string) $v);
    return $v === '' ? '-' : h($v);
}

function st_photo(array $S): string
{
    $u = \Gcms\Api::getAvatarUrl((int) $S['id']);
    return $u ? $u.'?v='.substr(md5((string) $u.@filemtime(ROOT_PATH.DATA_FOLDER.'avatar/'.(int) $S['id'].'.jpg')), 0, 6) : school_logo_abs();
}

function st_rows(string $kind, $r1, string $r2): array
{
    $o = [];
    foreach (md_rows($kind, $r1, $r2) as $r) {
        $o[(string) $r['r3']] = json_decode((string) $r['value'], true) ?: [];
    }
    return $o;
}

/* ================================================================ หน้าหลัก (Information Student) */

function st_home(array $S, bool $post): string
{
    $sid = (int) $S['id'];
    if ($post && ($_GET['act'] ?? '') === 'photo') {
        if (function_exists('save_photo')) {
            save_photo($sid, 'photo');
        }
        st_go('home');
    }
    if ($post && ($_GET['act'] ?? '') === 'en') {
        $d = [];
        foreach (['st', 'fa', 'mo', 'pa'] as $w) {
            $t = (string) ($_POST[$w.'_t'] ?? '');
            $d[$w.'_t'] = isset(EN_TITLES[$t]) ? $t : '';
            foreach (['f', 'l'] as $x) {
                $d[$w.'_'.$x] = mb_substr(preg_replace('/[^A-Za-z .\'-]/', '', trim((string) ($_POST[$w.'_'.$x] ?? ''))), 0, 60);
            }
        }
        foreach (['school_old' => 150, 'addr_en' => 300, 'birthplace_en' => 200] as $k => $n) {
            $d[$k] = mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $n);
        }
        $d['updated'] = date('Y-m-d H:i');
        md_set('st_en', $sid, '', '', json_encode($d, JSON_UNESCAPED_UNICODE));
        st_go('home');
    }
    [$p, $i, $en] = st_person($S);
    $sex = ['m' => 'ชาย', 'f' => 'หญิง'][$S['sex'] ?? ''] ?? '-';
    $card = preg_replace('/\D/', '', (string) ($S['id_card'] ?: ''));
    $ename = fn($w) => trim(($en[$w.'_t'] ?? '').' '.($en[$w.'_f'] ?? '').' '.($en[$w.'_l'] ?? ''));
    $addr = st_addr($p, (string) $S['address']);
    $kv = fn(array $pairs) => '<div class="kv">'.implode('', array_map(fn($x) => '<div><b>'.h($x[0]).' :</b> '.$x[1].'</div>', $pairs)).'</div>';
    $general = $kv([['รหัสนักเรียน', h($S['student_id'])], ['เลขที่บัตรประชาชน', st_dash($card)], ['ชื่อ-นามสกุล', h($S['name'])], ['Name', st_dash($ename('st'))],
        ['ชื่อเล่น', st_dash($i['nickname'] ?? '')], ['วันเกิด', !empty($S['birthday']) && $S['birthday'] !== '0000-00-00' ? th_date($S['birthday'], ' ') : '-'],
        ['ห้องเรียน', h(class_room_short($S['class'], $S['room'])).' <span class="mut">เลขที่ '.(int) $S['number'].'</span>'], ['เพศ', $sex.' &nbsp; <b>กรุ๊ปเลือด :</b> '.st_dash($p['blood'] ?? '')]]);
    $who = function (string $label, string $icon, string $name, string $nameEn, string $job, string $phone) use ($kv, $addr, $p) {
        return st_card($label, $icon, '<div class="kv kv1">'.implode('', array_map(fn($x) => '<div><b>'.h($x[0]).' :</b> '.$x[1].'</div>', [['ชื่อ-นามสกุล', st_dash($name)], ['Name', st_dash($nameEn)],
            ['ที่อยู่', st_dash($addr)], ['รหัสไปรษณีย์', st_dash($p['zip'] ?? '')], ['เบอร์โทร', st_dash($phone)], ['อาชีพ', st_dash($job)], ['สัญชาติ', st_dash($p['nation'] ?? '').' &nbsp; <b>ศาสนา :</b> '.st_dash($p['religion'] ?? '')]])).'</div>');
    };
    $o = st_saved_msg().'<div class="row rside"><div class="card photo"><img src="'.h(st_photo($S)).'" alt="รูปนักเรียน"><div style="margin-top:10px"><button type="button" class="btn info sm" data-mo="moImg">Edit Image</button></div></div>'
        .'<div class="card"><h4><i class="fa fa-user-o"></i>ข้อมูลนักเรียนทั่วไป</h4>'.$general.'<button type="button" class="btn info sm" data-mo="moEn">Edit Name English</button></div></div>'
        .'<div class="row r2">'.st_card('ข้อมูลที่อยู่นักเรียน', 'fa-users', '<div class="kv kv1">'.implode('', array_map(fn($x) => '<div><b>'.h($x[0]).' :</b> '.$x[1].'</div>', [['ที่อยู่', st_dash($addr)], ['รหัสไปรษณีย์', st_dash($p['zip'] ?? '')],
            ['Email', st_dash($i['email'] ?? '')], ['เบอร์โทร', st_dash($S['phone'])], ['สัญชาติ', st_dash($p['nation'] ?? '')], ['ศาสนา', st_dash($p['religion'] ?? '')]])).'</div>')
        .$who('ข้อมูลผู้ปกครอง', 'fa-university', (string) ($p['guardian'] ?? $S['parent']), $ename('pa'), (string) ($p['guardian_job'] ?? ''), (string) ($S['parent_phone'] ?: ($p['phone'] ?? '')))
        .$who('ข้อมูลบิดา', 'fa-male', (string) ($p['father'] ?? ''), $ename('fa'), (string) ($p['father_job'] ?? ''), (string) ($i['father_mobile'] ?? ''))
        .$who('ข้อมูลมารดา', 'fa-female', (string) ($p['mother'] ?? ''), $ename('mo'), (string) ($p['mother_job'] ?? ''), (string) ($i['mother_mobile'] ?? '')).'</div>';
    // ป๊อปอัป: แก้ไขรูป
    $o .= '<form method="post" action="?p=home&act=photo" enctype="multipart/form-data">'.st_modal('moImg', 'แก้ไขข้อมูลส่วนตัว', '<h3 style="text-align:center;font-weight:400;margin:0 0 14px">อัพโหลดแก้ไขรูปนักเรียน</h3>'
        .'<div style="display:flex;gap:10px;align-items:center;justify-content:center;flex-wrap:wrap"><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required> <button class="btn ok" type="submit"><i class="fa fa-upload"></i> Upload image</button></div>'
        .'<p class="mut" style="text-align:center;font-size:13px;margin:12px 0 0">ไฟล์ .jpg .png หรือ .webp ระบบจะปรับเป็นภาพสี่เหลี่ยมจัตุรัสให้อัตโนมัติ</p>', '<button type="button" class="btn warn" data-x="1">Close</button>', 'sm', 'fa-id-card-o', true).'</form>';
    // ป๊อปอัป: แก้ไขชื่อภาษาอังกฤษ
    $line = function (string $w, string $label, string $tag, string $color, string $def) use ($en) {
        $t = $en[$w.'_t'] ?? $def;
        $opts = '<option value="">-</option>';
        foreach (EN_TITLES as $k => $v) {
            $opts .= '<option value="'.h($k).'"'.($k === $t ? ' selected' : '').'>'.h($v).'</option>';
        }
        return '<label class="l">'.h($label).'</label><div class="g3"><div class="grp"><span style="background:'.$color.'">#'.h($tag).'</span><select class="in" name="'.$w.'_t">'.$opts.'</select></div>'
            .'<div class="grp"><span style="background:'.$color.'">First Name</span><input class="in" name="'.$w.'_f" value="'.h($en[$w.'_f'] ?? '').'" pattern="[A-Za-z .\'\-]*"></div>'
            .'<div class="grp"><span style="background:'.$color.'">Last Name</span><input class="in" name="'.$w.'_l" value="'.h($en[$w.'_l'] ?? '').'" pattern="[A-Za-z .\'\-]*"></div></div>';
    };
    $ef = '<div style="background:#fbf3ec;border-radius:4px;padding:6px 12px 12px">'.$line('st', 'Student Name', 'Student', '#fdebc8', ($S['sex'] ?? '') === 'f' ? 'Miss' : 'Mr.').$line('fa', 'Father Name', 'Father', '#fbd3c2', 'Mr.')
        .$line('mo', 'Mather Name', 'Mother', '#e6d3f0', 'Mrs.').$line('pa', 'Parent Name', 'Parent', '#f9a98b', '')
        .'<label class="l">สถานศึกษาเดิม</label><div class="grp"><span><i class="fa fa-graduation-cap"></i></span><input class="in" name="school_old" value="'.h($en['school_old'] ?? '').'"></div>'
        .'<div class="g2" style="margin-top:8px"><div><label class="l"><i class="fa fa-address-card-o"></i> Student Address <span style="color:#dc3545"><i class="fa fa-certificate"></i> หมายเหตุ ให้ระบุเป็นภาษาอังกฤษ</span></label><textarea class="in" name="addr_en">'.h($en['addr_en'] ?? '').'</textarea></div>'
        .'<div><label class="l"><i class="fa fa-address-card-o"></i> Student PlaceBirth <span style="color:#dc3545"><i class="fa fa-certificate"></i> หมายเหตุ ให้ระบุเป็นภาษาอังกฤษ</span></label><textarea class="in" name="birthplace_en">'.h($en['birthplace_en'] ?? '').'</textarea></div></div></div>';
    $o .= '<form method="post" action="?p=home&act=en">'.st_modal('moEn', 'แก้ไขข้อมูลส่วนตัว', $ef, '<button class="btn" type="submit">บันทึกข้อมูล</button><button type="button" class="btn warn" data-x="1">Close</button>', 'lg', 'fa-language', true).'</form>';
    return $o;
}

/* ================================================================ วิชาการ */

/** รายวิชาที่ลงทะเบียน ภาคเรียนที่กำหนด */
function st_courses(int $sid, int $y, int $t): array
{
    return q('SELECT G.`id` gid, G.`midterm`, G.`final`, G.`grade`, C.*, U.`name` teacher FROM {grade} G JOIN {course} C ON C.`id`=G.`course_id` LEFT JOIN {user} U ON U.`id`=C.`teacher_id` WHERE G.`student_id`=? AND C.`year`=? AND C.`term`=? ORDER BY C.`course_code`', [$sid, $y, $t]);
}

function st_schedule(array $S, bool $post): string
{
    $grid = [];
    $cids = [];
    foreach (md_rows('tt', null, yt()) as $r) {
        [$c, $room] = array_pad(explode('|', (string) $r['value']), 2, 0);
        if ((int) $room === (int) $S['room'] && (int) $S['room'] > 0) {
            $grid[(string) $r['r3']] = [(int) $c, (int) $r['r1']];
            $cids[] = (int) $c;
        }
    }
    if (!$grid) {
        return '<div class="empty">"ระบบยังไม่มีข้อมูล กรุณาตรวจสอบข้อมูลรายวิชาที่ลงทะเบียน"</div>';
    }
    $cs = courses_by_id($cids);
    $tn = [];
    foreach (q('SELECT `id`, `name` FROM {user} WHERE `id` IN ('.implode(',', array_unique(array_map(fn($g) => $g[1], $grid))).')') as $u) {
        $tn[(int) $u['id']] = $u['name'];
    }
    $head = '<tr><th>วัน / คาบ</th>';
    for ($p = 1; $p <= 8; $p++) {
        $head .= '<th>'.$p.'</th>';
    }
    $rows = '';
    foreach (TT_DAYS as $d => [$name, $color]) {
        $rows .= '<tr><td class="day" style="background:'.$color.'">'.h($name).'</td>';
        for ($p = 1; $p <= 8; $p++) {
            [$c, $t] = $grid[$d.'-'.$p] ?? [0, 0];
            $x = $cs[$c] ?? null;
            $rows .= '<td>'.($x ? '<div class="code">'.h($x['course_code']).'</div><div>'.h($x['course_name']).'</div><div class="mut" style="font-size:12px">'.h($tn[$t] ?? '').'</div>' : '').'</td>';
        }
        $rows .= '</tr>';
    }
    return '<div class="tbw"><table class="tb tt"><tr class="cap"><th colspan="9">ตารางเรียน ชั้น '.h(class_room_short($S['class'], $S['room'])).' ภาคเรียนที่ '.st_ytl()[1].' ปีการศึกษา '.st_ytl()[0].'</th></tr>'.$head.'</tr>'.$rows.'</table></div>';
}

/** คะแนนรายส่วนของนักเรียนในรายวิชา: [[รายการ, เต็ม, ได้], ...] */
function st_score_parts(array $c): array
{
    $gid = (int) $c['gid'];
    $units = function_exists('d_units') ? d_units($c) : [];
    $ts = function_exists('task_scores') ? (task_scores([$gid])[$gid] ?? []) : [];
    [$u0, $m0] = array_pad(explode('|', (string) md_get('rec_split', $gid)), 2, '');
    $mid = function_exists('d_exam') ? d_exam($c, 'mid') : ['mark' => 20];
    $fin = function_exists('d_exam') ? d_exam($c, 'final') : ['mark' => 20];
    $num = fn($v) => $v === null || $v === '' ? null : (float) $v;
    if (!$units) {
        $finFull = (float) $fin['mark'];
        return [['คะแนนระหว่างภาค', 100 - $finFull, $c['midterm'] === null || ($c['midterm'] === '0' && $c['grade'] === '') ? null : (float) $c['midterm']], ['สอบปลายภาค', $finFull, $num($c['final'])]];
    }
    $part = ['before' => [0.0, 0.0, false], 'after' => [0.0, 0.0, false]];
    foreach ($units as $un => $u) {
        foreach ($u['tasks'] as $tn => $t) {
            $h = ($t['half'] ?? 'before') === 'after' ? 'after' : 'before';
            $part[$h][0] += (float) $t['mark'];
            $v = $ts[$un.'.'.$tn] ?? '';
            if ($v !== '' && is_numeric($v)) {
                $part[$h][1] += (float) $v;
                $part[$h][2] = true;
            }
        }
    }
    return [['เก็บคะแนน 1', $part['before'][0], $part['before'][2] ? $part['before'][1] : ($u0 !== '' ? (float) $u0 : null)], ['สอบกลางภาค', (float) $mid['mark'], $num($m0)],
        ['เก็บคะแนน 2', $part['after'][0], $part['after'][2] ? $part['after'][1] : null], ['สอบปลายภาค', (float) $fin['mark'], $num($c['final'])]];
}

function st_n($v): string
{
    return $v === null ? '-' : rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
}

function st_subject(array $S, bool $post): string
{
    [$y, $t] = st_ytl();
    $cs = st_courses((int) $S['id'], $y, $t);
    if (!$cs) {
        return '<div class="empty">"ระบบยังไม่มีข้อมูล กรุณาตรวจสอบข้อมูลรายวิชาที่ลงทะเบียน"</div>';
    }
    $rows = '';
    $mo = '';
    foreach ($cs as $c) {
        $parts = st_score_parts($c);
        $full = array_sum(array_column($parts, 1));
        $got = array_filter(array_column($parts, 2), fn($v) => $v !== null);
        $approved = md_get('approved', (int) $c['id']) !== null;
        $id = 'mc'.(int) $c['id'];
        $rows .= '<tr><td>'.h($c['course_code']).'</td><td>'.h($c['course_name']).'</td><td>'.st_dash($c['teacher'] ? 'อ.'.preg_replace('/^(นาย|นางสาว|นาง|ว่าที่ร้อยตรี|ดร\.)\s*/u', '', (string) $c['teacher']) : 'อ.').'</td>'
            .'<td class="c">'.st_n($full ?: null).'</td><td class="c">'.($got ? st_n(array_sum($got)) : '-').'</td><td class="c"><b>'.($approved && $c['grade'] !== '' ? h($c['grade']) : '-').'</b></td>'
            .'<td class="c"><button type="button" class="btn ok sm" data-mo="'.$id.'" title="รายละเอียดคะแนน"><i class="fa fa-file-text"></i></button></td></tr>';
        $pr = '';
        foreach ($parts as [$l, $f, $g]) {
            $pr .= '<tr><td>'.h($l).'</td><td class="c">'.st_n($f ?: null).'</td><td class="c">'.st_n($g).'</td></tr>';
        }
        $mo .= st_modal($id, '<b>รายละเอียดคะแนน '.h($c['course_code']).' วิชา '.h($c['course_name']).'</b>', '<table class="tb"><tr><th>รายการ</th><th>คะแนนเต็ม</th><th>คะแนนที่ได้</th></tr>'.$pr
            .'<tr><td><b>รวมคะแนน</b></td><td class="c"><b>'.st_n($full ?: null).'</b></td><td class="c"><b>'.($got ? st_n(array_sum($got)) : '-').'</b></td></tr></table>', '<button type="button" class="btn warn" data-x="1">Close</button>', 'lg');
    }
    return '<div class="tbw"><table class="tb st"><tr class="cap"><th colspan="7">ภาคเรียนที่ '.$t.' ปีการศึกษา '.$y.'<br>รหัส '.h($S['student_id']).' '.h($S['name']).'</th></tr>'
        .'<tr><th>รหัสวิชา</th><th>ชื่อวิชา</th><th>ผู้สอน</th><th>คะแนนเต็ม</th><th>คะแนนที่ได้</th><th>เกรด</th><th>รายละเอียด</th></tr>'.$rows.'</table></div>'
        .'<p class="mut" style="font-size:13px;margin:10px 0 0">ผลการเรียน (เกรด) จะแสดงเมื่อรายวิชาได้รับการอนุมัติผลการเรียนแล้ว</p>'.$mo;
}

function st_gpa(array $S, bool $post): string
{
    $by = [];
    foreach (student_grades((int) $S['id']) as $g) {
        $by[$g['year'].'/'.$g['term']][] = $g;
    }
    $cur = yt();
    if (!isset($by[$cur])) {
        $by[$cur] = [];
    }
    uksort($by, fn($a, $b) => strcmp($b, $a));
    $rows = '';
    $i = 0;
    $all = [];
    $crAll = $crGot = 0.0;
    foreach ($by as $k => $gs) {
        [$y, $t] = explode('/', $k);
        $ok = array_filter($gs, fn($g) => md_get('approved', (int) $g['course_id']) !== null || $k !== $cur);
        $cr = array_sum(array_map(fn($g) => (float) $g['credit'], $ok));
        $got = array_sum(array_map(fn($g) => is_numeric($g['grade']) && (float) $g['grade'] > 0 ? (float) $g['credit'] : 0, $ok));
        $gpa = $ok ? gpa_of($ok) : null;
        $all = array_merge($all, $ok);
        $crAll += $cr;
        $crGot += $got;
        $rows .= '<tr><td class="c">'.(++$i).'</td><td>'.h($S['name']).'</td><td class="c">'.h($y).'</td><td class="c">'.h($t).'</td><td class="c">'.($ok ? st_n($cr) : '-').'</td><td class="c">'.($ok ? st_n($got) : '-').'</td><td class="c"><b>'.($gpa === null ? '-' : number_format($gpa, 2)).'</b></td></tr>';
    }
    $gpax = gpa_of($all);
    return '<div class="tbw"><table class="tb st"><tr><th>ลำดับ</th><th>ชื่อ-นามสกุล</th><th>ปีการศึกษา</th><th>เทอม</th><th>หน่วยกิตรวม</th><th>หน่วยกิตที่ได้</th><th>เกรด GPA</th></tr>'.$rows
        .'<tr><td colspan="4" class="r">ระดับคะแนนเฉลี่ยสะสม</td><td class="c">'.st_n($crAll).'</td><td class="c">'.st_n($crGot).'</td><td class="c"><b>'.($gpax === null ? '-' : number_format($gpax, 2)).'</b></td></tr></table></div>'
        .'<p style="margin:12px 0 0"><a class="btn info sm" href="?p=grade"><i class="fa fa-print"></i> พิมพ์ใบรายงานผลการเรียน / ผลการสอบแก้ตัว</a></p>';
}

function st_eval_form(string $action, string $id, string $title, array $qs, array $old, bool $open): string
{
    $rows = '';
    foreach ($qs as $i => $q) {
        $rows .= '<tr><td class="c">'.($i + 1).'</td><td>'.h($q).'</td>';
        foreach (EVAL_SCALE as $v => $l) {
            $rows .= '<td class="c"><input type="radio" name="a['.$i.']" value="'.$v.'"'.((string) ($old['a'][$i] ?? '') === (string) $v ? ' checked' : '').' required'.($open ? '' : ' disabled').'></td>';
        }
        $rows .= '</tr>';
    }
    $ths = implode('', array_map(fn($l) => '<th style="width:70px">'.h($l).'</th>', EVAL_SCALE));
    $body = '<div class="tbw"><table class="tb rad"><tr><th style="width:40px">ข้อ</th><th>รายการประเมิน</th>'.$ths.'</tr>'.$rows.'</table></div>'
        .'<label class="l">ข้อเสนอแนะเพิ่มเติม</label><textarea class="in" name="note"'.($open ? '' : ' disabled').'>'.h($old['note'] ?? '').'</textarea>';
    $foot = ($open ? '<button class="btn" type="submit"><i class="fa fa-check-circle-o"></i> ส่งแบบประเมิน</button>' : '<span class="mut" style="margin-right:10px">ปิดการประเมินแล้ว</span>').'<button type="button" class="btn warn" data-x="1">Close</button>';
    return '<form method="post" action="'.h($action).'">'.st_modal($id, $title, $body, $foot, 'lg', 'fa-commenting-o').'</form>';
}

function st_eval_save(): ?array
{
    $a = [];
    foreach ((array) ($_POST['a'] ?? []) as $i => $v) {
        if (!isset(EVAL_SCALE[(string) $v])) {
            return null;
        }
        $a[(int) $i] = (int) $v;
    }
    return $a ? ['a' => $a, 'note' => mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 1000), 'date' => date('Y-m-d H:i')] : null;
}

function st_evalt(array $S, bool $post): string
{
    $sid = (int) $S['id'];
    [$y, $t] = st_ytl();
    $open = st_cfg('stu_eval_open') === '1';
    $qs = st_eval_q('t');
    $cs = st_courses($sid, $y, $t);
    if ($post && $open && ($cid = (int) ($_GET['c'] ?? 0)) && array_filter($cs, fn($c) => (int) $c['id'] === $cid) && ($d = st_eval_save())) {
        md_set('stu_evt', $sid, yt(), (string) $cid, json_encode($d, JSON_UNESCAPED_UNICODE));
        st_go('evalt');
    }
    if (!$cs) {
        return '<div class="empty">"ระบบยังไม่มีข้อมูล กรุณาตรวจสอบข้อมูลรายวิชาที่ลงทะเบียน"</div>';
    }
    $done = st_rows('stu_evt', $sid, yt());
    $rows = '';
    $forms = '';
    foreach ($cs as $c) {
        $cid = (int) $c['id'];
        $has = (int) $c['teacher_id'] > 0;
        $ok = isset($done[(string) $cid]);
        $rows .= '<tr><td>'.h($c['course_code']).'</td><td>'.h($c['course_name']).'</td><td>'.st_dash($c['teacher'] ?: 'อ.').'</td><td class="c">'
            .(!$has ? '--' : '<button type="button" class="btn sm '.($ok ? 'ok' : 'bad').'" data-mo="ev'.$cid.'">'.($ok ? 'ประเมินแล้ว' : 'ยังไม่ได้ประเมิน').'</button>').'</td></tr>';
        if ($has) {
            $forms .= st_eval_form('?p=evalt&c='.$cid, 'ev'.$cid, 'ประเมินครูรายวิชา '.h($c['course_code'].' '.$c['course_name']).'<br><small>'.h((string) $c['teacher']).'</small>', $qs, $done[(string) $cid] ?? [], $open);
        }
    }
    return st_saved_msg().(!$open ? '<div class="msg wa">ขณะนี้ปิดการประเมินครูรายวิชา</div>' : '')
        .'<div class="tbw"><table class="tb st"><tr class="cap"><th colspan="4">ภาคเรียนที่ '.$t.' ปีการศึกษา '.$y.'</th></tr><tr><th>รหัสวิชา</th><th>ชื่อวิชา</th><th>ผู้สอน</th><th>ประเมิน</th></tr>'.$rows.'</table></div>'
        .'<p class="mut" style="font-size:13px;margin:10px 0 0">ผลการประเมินเป็นความลับ ครูผู้สอนจะเห็นเฉพาะผลสรุปภาพรวม ไม่เห็นชื่อผู้ประเมิน</p>'.$forms;
}

function st_evala(array $S, bool $post): string
{
    $sid = (int) $S['id'];
    $open = st_cfg('stu_eval_open') === '1';
    $adv = room_advisors()[(int) $S['room']] ?? [];
    if ($post && $open && in_array($tid = (int) ($_GET['t'] ?? 0), $adv, true) && ($d = st_eval_save())) {
        md_set('stu_eva', $sid, yt(), (string) $tid, json_encode($d, JSON_UNESCAPED_UNICODE));
        st_go('evala');
    }
    if (!$adv) {
        return '<div class="empty">ไม่มีข้อมูลของนักเรียนในการประเมินครูที่ปรึกษา<br><small class="mut">(ห้องเรียนยังไม่ได้กำหนดครูที่ปรึกษา)</small></div>';
    }
    $done = st_rows('stu_eva', $sid, yt());
    $names = personnel_options();
    $qs = st_eval_q('a');
    $rows = '';
    $forms = '';
    foreach ($adv as $i => $tid) {
        $ok = isset($done[(string) $tid]);
        $rows .= '<tr><td class="c">'.($i + 1).'</td><td>'.h($names[$tid] ?? '-').'</td><td class="c">'.h(class_room_short($S['class'], $S['room'])).'</td><td class="c"><button type="button" class="btn sm '.($ok ? 'ok' : 'bad').'" data-mo="ea'.$tid.'">'.($ok ? 'ประเมินแล้ว' : 'ยังไม่ได้ประเมิน').'</button></td></tr>';
        $forms .= st_eval_form('?p=evala&t='.$tid, 'ea'.$tid, 'ประเมินครูที่ปรึกษา '.h($names[$tid] ?? ''), $qs, $done[(string) $tid] ?? [], $open);
    }
    return st_saved_msg().(!$open ? '<div class="msg wa">ขณะนี้ปิดการประเมินครูที่ปรึกษา</div>' : '')
        .'<table class="tb st"><tr class="cap"><th colspan="4">ภาคเรียนที่ '.st_ytl()[1].' ปีการศึกษา '.st_ytl()[0].'</th></tr><tr><th>ลำดับ</th><th>ครูที่ปรึกษา</th><th>ห้องเรียน</th><th>ประเมิน</th></tr>'.$rows.'</table>'.$forms;
}

/** ประเมินคุณลักษณะ (นักเรียน/ผู้ปกครอง) */
function st_char(array $S, bool $post, string $kind, string $title, string $p): string
{
    $sid = (int) $S['id'];
    $open = st_cfg('stu_char_open') === '1';
    if ($post && $open) {
        $a = [];
        foreach (array_keys(CHAR_ITEMS) as $i) {
            $v = (int) ($_POST['s'][$i] ?? 0);
            if ($v < 1 || $v > 3) {
                $a = null;
                break;
            }
            $a[$i] = $v;
        }
        if ($a) {
            md_set($kind, $sid, yt(), '', json_encode(['a' => $a, 'date' => date('Y-m-d H:i')], JSON_UNESCAPED_UNICODE));
            st_go($p);
        }
    }
    $old = json_decode((string) md_get($kind, $sid, yt()), true) ?: [];
    $rows = '';
    foreach (CHAR_ITEMS as $i => $l) {
        $rows .= '<tr><td colspan="5" style="background:#fff">'.h($l).'</td></tr><tr><td class="c" style="width:60px">-</td><td>'.h($l).'</td>';
        foreach ([3, 2, 1] as $v) {
            $rows .= '<td class="c" style="width:46px"><input type="radio" name="s['.$i.']" value="'.$v.'"'.((int) ($old['a'][$i] ?? 0) === $v ? ' checked' : '').' required'.($open ? '' : ' disabled').'></td>';
        }
        $rows .= '</tr>';
    }
    [$y, $t] = st_ytl();
    $res = '';
    if ($old) {
        $avg = array_sum($old['a']) / count($old['a']);
        $res = '<div class="msg ok">ส่งคะแนนแล้วเมื่อ '.h(th_date(substr((string) $old['date'], 0, 10), ' ')).' — เฉลี่ย '.number_format($avg, 2).' ('.($avg >= 2.5 ? 'ดีเยี่ยม' : ($avg >= 1.5 ? 'ดี' : 'ผ่าน')).') ส่งใหม่ได้จนกว่าจะปิดการประเมิน</div>';
    }
    return st_saved_msg().'<div class="msg in" style="text-align:center"><h4 style="margin:0;font-weight:400;font-size:22px"><i class="fa fa-pencil-square-o"></i> '.h($title).'</h4>'
        .'ชื่อ-สกุล : '.h($S['name']).' ห้องเรียน : '.h(class_room_short($S['class'], $S['room'])).' ปีการศึกษา : '.$y.'/'.$t.'</div>'.$res
        .($open ? '' : '<div class="msg wa">ขณะนี้ปิดการประเมินคุณลักษณะ</div>')
        .'<form method="post" action="?p='.$p.'"><div class="tbw"><table class="tb rad"><tr><th rowspan="2" style="width:60px">หัวข้อ</th><th rowspan="2">รายการ</th><th colspan="3">คะแนน</th></tr><tr><th>3</th><th>2</th><th>1</th></tr>'.$rows.'</table></div>'
        .'<p class="mut" style="font-size:13px">3 = ดีเยี่ยม &nbsp; 2 = ดี &nbsp; 1 = ผ่าน</p>'
        .($open ? '<p style="text-align:right;margin:10px 0 0"><button class="btn" type="submit"><i class="fa fa-check-circle-o"></i> ส่งคะแนน</button></p>' : '').'</form>';
}

function st_chars(array $S, bool $post): string
{
    return st_char($S, $post, 'char_s', 'ประเมินคุณลักษณะ (นักเรียน)', 'chars');
}

function st_charp(array $S, bool $post): string
{
    return st_char($S, $post, 'char_p', 'ประเมินคุณลักษณะ (ผู้ปกครอง)', 'charp');
}

/* ---------------------------------------------------------------- คำร้องขอเอกสาร */

function st_docreq(array $S, bool $post): string
{
    $sid = (int) $S['id'];
    $lang = ($_GET['f'] ?? '') === 'en' ? 'en' : (($_GET['f'] ?? '') === 'th' ? 'th' : '');
    $open = st_cfg('stu_doc_open') === '1';
    [$p, $i, $en] = st_person($S);
    if ($post && $open && $lang !== '') {
        $docs = [];
        foreach (DOCS as $k => $x) {
            if (!empty($_POST['doc'][$k])) {
                $docs[$k] = max(1, min(10, (int) ($_POST['n'][$k] ?? 1)));
            }
        }
        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 500);
        if ($docs && $reason !== '' && !empty($_POST['confirm'])) {
            $info = [];
            foreach (['title', 'first', 'last', 'phone', 'birthday', 'religion', 'race', 'nation', 'school_old', 'sex', 'address', 'province', 'fa_t', 'fa_f', 'fa_l', 'mo_t', 'mo_f', 'mo_l'] as $k) {
                $info[$k] = mb_substr(trim((string) ($_POST[$k] ?? '')), 0, 250);
            }
            $id = (string) round(microtime(true) * 1000);
            md_set('docreq', $id, $sid, '', json_encode(['lang' => $lang, 'docs' => $docs, 'info' => $info, 'reason' => $reason, 'status' => 'รอดำเนินการ', 'note' => '',
                'date' => date('Y-m-d H:i'), 'class' => (int) $S['class'], 'room' => (int) $S['room'], 'number' => (int) $S['number']], JSON_UNESCAPED_UNICODE));
            st_go('docreq', '&h=1');
        }
        return '<div class="msg no">กรุณาเลือกเอกสาร กรอกเหตุผล และติ๊กยืนยันข้อมูลถูกต้อง</div>'.st_docreq($S, false);
    }
    $sub = '<div style="display:flex;gap:8px;margin-bottom:14px" class="noprint"><a class="btn lt sm" href="?p=docreq"><i class="fa fa-home"></i> หน้าหลัก</a><a class="btn lt sm" href="?p=docreq&h=1"><i class="fa fa-history"></i> ประวัติร้องขอเอกสาร</a></div>';
    if (isset($_GET['h'])) {
        $rows = '';
        $n = 0;
        $list = md_rows('docreq');
        usort($list, fn($a, $b) => strcmp((string) $b['r1'], (string) $a['r1']));
        foreach ($list as $r) {
            if ((int) $r['r2'] !== $sid) {
                continue;
            }
            $d = json_decode((string) $r['value'], true) ?: [];
            $docs = implode('<br>', array_map(fn($k, $c) => h(DOCS[$k][0] ?? $k).' x '.(int) $c, array_keys($d['docs'] ?? []), $d['docs'] ?? []));
            $st = (string) ($d['status'] ?? 'รอดำเนินการ');
            $rows .= '<tr><td class="c">'.(++$n).'</td><td class="c">'.h(th_date(substr((string) $d['date'], 0, 10), ' ')).'</td><td>'.$docs.'</td><td class="c">'.(($d['lang'] ?? '') === 'en' ? 'อังกฤษ' : 'ไทย').'</td>'
                .'<td>'.h($d['reason'] ?? '').'</td><td class="c"><span class="msg '.(DOC_STATUS[$st] ?? 'wa').'" style="padding:2px 8px;margin:0;display:inline-block">'.h($st).'</span>'.(!empty($d['note']) ? '<br><small class="mut">'.h($d['note']).'</small>' : '').'</td></tr>';
        }
        return st_saved_msg().$sub.'<h4 class="ttl" style="font-size:19px"><i class="fa fa-history"></i>ประวัติร้องขอเอกสาร</h4><div class="tbw"><table class="tb st"><tr><th>#</th><th>วันที่ร้องขอ</th><th>เอกสาร</th><th>ภาษา</th><th>เหตุผล</th><th>สถานะ</th></tr>'
            .($rows ?: '<tr><td colspan="6" class="c mut">ยังไม่มีการร้องขอเอกสาร</td></tr>').'</table></div>';
    }
    if ($lang === '') {
        return $sub.'<h3 style="text-align:center;font-weight:400;font-size:28px;color:#6c757d;margin:4px 0 0">--= ระบบคำร้องขอเอกสาร =--</h3><p class="mut" style="text-align:center;margin:0 0 20px">'.h(st_school()).'</p>'
            .(!$open ? '<div class="msg wa" style="text-align:center">ขณะนี้ปิดรับคำร้องขอเอกสาร</div>' : '')
            .'<div class="row r2"><a class="btn gray" style="padding:20px;font-size:18px" href="?p=docreq&f=en"><i class="fa fa-flag"></i> แบบฟอร์มคำร้องขอเอกสารภาษาอังกฤษ</a><a class="btn gray" style="padding:20px;font-size:18px" href="?p=docreq&f=th"><i class="fa fa-flag"></i> แบบฟอร์มคำร้องขอเอกสารภาษาไทย</a></div>';
    }
    [$pre, $first, $last] = st_name_parts((string) $S['name']);
    $ise = $lang === 'en';
    if ($ise) {
        $pre = $en['st_t'] ?? '';
        $first = $en['st_f'] ?? '';
        $last = $en['st_l'] ?? '';
    }
    [$fp, $ff, $fl] = st_name_parts((string) ($p['father'] ?? ''));
    [$mp, $mf, $ml] = st_name_parts((string) ($p['mother'] ?? ''));
    if ($ise) {
        [$fp, $ff, $fl, $mp, $mf, $ml] = [$en['fa_t'] ?? '', $en['fa_f'] ?? '', $en['fa_l'] ?? '', $en['mo_t'] ?? '', $en['mo_f'] ?? '', $en['mo_l'] ?? ''];
    }
    $f = fn($n, $l, $v, $cls = '', $ro = false) => '<div class="'.$cls.'"><label class="l">'.h($l).'</label><input class="in" name="'.$n.'" value="'.h($v).'"'.($ro ? ' readonly style="background:#e9ecef"' : '').'></div>';
    $chk = '';
    foreach (DOCS as $k => [$th, $enl]) {
        $chk .= '<div style="display:flex;align-items:center;gap:10px;padding:6px 0"><label style="flex:1;cursor:pointer"><input type="checkbox" name="doc['.$k.']" value="1"> '.h($ise ? $enl : $th).'</label>'
            .'<input class="in" type="number" name="n['.$k.']" min="1" max="10" placeholder="'.($ise ? 'copies' : 'จำนวนฉบับ').'" style="width:150px;background:#e9ecef"></div>';
    }
    $birth = !empty($S['birthday']) && $S['birthday'] !== '0000-00-00' ? date('d-m-', strtotime($S['birthday'])).(date('Y', strtotime($S['birthday'])) + 543) : '';
    $form = '<form method="post" action="?p=docreq&f='.$lang.'"><h4 class="ttl" style="font-size:19px"><i class="fa fa-flag"></i>บันทึกแบบคำร้องขอเอกสาร'.($ise ? 'ภาษาอังกฤษ' : 'ภาษาไทย').'</h4>'
        .'<p class="mut" style="margin:-6px 0 10px;font-size:13px">นักเรียนที่ต้องการขอหลักฐานเป็นภาษา'.($ise ? 'อังกฤษ' : 'ไทย').'ให้เขียน โดยกรอกรายละเอียดดังนี้</p>'
        .'<div class="card" style="padding:8px 14px">'.$chk.'</div>'
        .'<div class="g4">'.$f('title', $ise ? 'Title' : 'คำนำหน้า', $pre).$f('first', $ise ? 'First name' : 'ชื่อ', $first).$f('last', $ise ? 'Last name' : 'นามสกุล', $last).$f('phone', 'เบอร์โทรศัพท์', (string) $S['phone'])
        .$f('class_x', 'ระดับชั้น', class_short((int) $S['class']), '', true).$f('room_x', 'ห้องเรียน', class_room_short($S['class'], $S['room']), '', true).$f('no_x', 'เลขที่', (string) (int) $S['number'], '', true).$f('code_x', 'รหัสประจำตัว', (string) $S['student_id'], '', true)
        .$f('card_x', 'เลขบัตรประจำตัวประชาชน', (string) $S['id_card'], '', true).$f('birthday', 'วันเดือนปีเกิด', $birth).$f('religion', 'ศาสนา', (string) ($p['religion'] ?? '')).$f('race', 'เชื้อชาติ', (string) ($p['race'] ?? ''))
        .$f('nation', 'สัญชาติ', (string) ($p['nation'] ?? '')).$f('school_old', 'สถานศึกษาเดิม', (string) ($en['school_old'] ?? ''), 's2').$f('sex', 'เพศ', ['m' => 'ชาย', 'f' => 'หญิง'][$S['sex'] ?? ''] ?? '')
        .$f('address', $ise ? 'Address' : 'ที่อยู่', $ise ? (string) ($en['addr_en'] ?? '') : st_addr($p, (string) $S['address']), 's4').$f('province', 'จังหวัด', (string) ($p['province'] ?? ''), 's4')
        .$f('fa_t', 'คำนำหน้าบิดา', $fp).$f('fa_f', 'ชื่อ', $ff).$f('fa_l', 'นามสกุล', $fl).'<div></div>'.$f('mo_t', 'คำนำหน้ามารดา', $mp).$f('mo_f', 'ชื่อ', $mf).$f('mo_l', 'นามสกุล', $ml).'<div></div></div>'
        .'<h4 style="color:#198754;margin:16px 0 6px;font-weight:400">#เหตุผลการขอเอกสาร</h4><input class="in" name="reason" placeholder="กรุณากรอกเหตุผลการขอเอกสาร" required style="height:44px">'
        .'<hr style="border:0;border-top:1px solid #ccc;margin:16px 0"><div style="display:flex;justify-content:flex-end;align-items:center;gap:14px"><label><input type="checkbox" name="confirm" value="1" id="dcOk" required> ยืนยันข้อมูลถูกต้อง</label>'
        .'<button class="btn" type="submit"'.($open ? '' : ' disabled').'><i class="fa fa-file-text"></i> บันทึก/ถัดไป</button></div></form>';
    return $sub.(!$open ? '<div class="msg wa">ขณะนี้ปิดรับคำร้องขอเอกสาร</div>' : '').$form;
}

/* ================================================================ การเงิน */

function st_fin(array $S, bool $post): string
{
    $items = function_exists('fee_items') ? fee_items((int) $S['class']) : [];
    $pay = payments()[(int) $S['id']] ?? null;
    [$y, $t] = st_ytl();
    $rows = '';
    $sum = 0.0;
    foreach ($items as $i => $d) {
        $amt = (float) ($d['amount'] ?? 0);
        $sum += $amt;
        $rows .= '<tr><td class="c">'.($i + 1).'</td><td class="c">'.$t.'/'.$y.'</td><td>'.h($d['name'] ?? '').'</td><td class="r">'.money($amt).'</td><td class="r">'.($pay ? '0.00' : money($amt)).'</td>'
            .'<td class="c">'.($pay ? '<i class="fa fa-check c-ok"></i>' : '-').'</td><td class="c">'.($pay ? h(trim(($pay['book'] ?? '') !== '' ? $pay['book'].'/'.($pay['no'] ?? '') : (string) ($pay['no'] ?? ''), '/')) ?: '-' : '-').'</td></tr>';
    }
    $paid = $pay ? (float) ($pay['amount'] ?? 0) : 0.0;
    $o = '<div class="tbw"><table class="tb"><tr><th>ลำดับ</th><th>ภาคเรียน/ปี</th><th>รายการ</th><th>ยอดเงิน</th><th>ยอดคงค้าง</th><th>ชำระแล้ว</th><th>เลขที่ใบเสร็จ</th></tr>'.$rows
        .'<tr><td></td><td></td><td class="r">รวมทั้งหมด</td><td class="r">'.money($sum).'</td><td class="r">'.money($pay ? max(0, $sum - $paid) : $sum).'</td><td class="c">'.($pay ? money($paid) : '-').'</td><td class="c">-</td></tr></table></div>';
    if ($pay) {
        $o .= '<p style="margin:12px 0 0" class="c-ok"><i class="fa fa-check-circle"></i> ชำระเงินแล้วเมื่อ '.h(th_date((string) ($pay['date'] ?? ''), ' ')).' ('.h(PAY_METHOD[$pay['method'] ?? ''] ?? '').')</p>';
    } elseif ($sum > 0 && !in_array('bank', st_menu_off(), true)) {
        $o .= '<p style="margin:12px 0 0"><a class="btn info sm" href="?p=bank"><i class="fa fa-qrcode"></i> เอกสารชำระผ่านธนาคาร</a></p>';
    }
    return $o;
}

function st_bank(array $S, bool $post): string
{
    $b = st_bank_cfg();
    $items = function_exists('fee_items') ? fee_items((int) $S['class']) : [];
    $sum = array_sum(array_map(fn($d) => (float) ($d['amount'] ?? 0), $items));
    $pay = payments()[(int) $S['id']] ?? null;
    [$y, $t] = st_ytl();
    if (!$items) {
        return '<div class="empty">ไม่มีรายการค่าบำรุงการศึกษาของภาคเรียนนี้</div>';
    }
    $rows = '';
    foreach ($items as $i => $d) {
        $rows .= '<tr><td class="c">'.($i + 1).'</td><td>'.h($d['name'] ?? '').'</td><td class="r">'.money($d['amount'] ?? 0).'</td></tr>';
    }
    $ref1 = (string) $S['student_id'];
    $ref2 = $y.$t;
    $qr = '';
    if ($b['promptpay'] !== '') {
        $qr = '<div id="ppqr" data-p="'.h(promptpay_payload($b['promptpay'], $sum)).'" style="width:200px;height:200px;margin:0 auto"></div><div style="text-align:center;font-size:13px" class="mut">สแกนจ่ายผ่าน PromptPay '.money($sum).' บาท</div>'
            .'<script src="/mis/scripts/qrcode.js"></script><script>(function(){var e=document.getElementById("ppqr");var q=qrcode(0,"M");q.addData(e.getAttribute("data-p"));q.make();e.innerHTML=q.createSvgTag({cellSize:4,margin:2,scalable:true});})();</script>';
    } elseif ($b['qr'] !== '') {
        $qr = '<div style="text-align:center"><img src="/datas/mis/'.h($b['qr']).'" alt="QR ชำระเงิน" style="width:200px;height:200px;object-fit:contain"></div>';
    }
    $doc = '<div class="card" id="payDoc"><div style="display:flex;gap:14px;align-items:center;border-bottom:2px solid #2aa3cf;padding-bottom:10px;margin-bottom:12px"><img src="'.h(school_logo_abs()).'" style="width:64px;height:64px;object-fit:contain">'
        .'<div><b style="font-size:19px">'.h(st_school()).'</b><br><span class="mut" style="font-size:13px">'.h(st_school_address()).'</span></div><div style="margin-left:auto;text-align:right"><b>ใบแจ้งชำระเงินค่าบำรุงการศึกษา</b><br><span style="font-size:13px">ภาคเรียนที่ '.$t.'/'.$y.'</span></div></div>'
        .'<div class="kv"><div><b>ชื่อ-นามสกุล :</b> '.h($S['name']).'</div><div><b>รหัสนักเรียน (Ref.1) :</b> '.h($ref1).'</div><div><b>ชั้น :</b> '.h(class_room_short($S['class'], $S['room'])).' เลขที่ '.(int) $S['number'].'</div><div><b>Ref.2 :</b> '.h($ref2).'</div></div>'
        .'<div class="row" style="grid-template-columns:1fr 230px;align-items:start"><table class="tb"><tr><th>ลำดับ</th><th>รายการ</th><th>จำนวนเงิน (บาท)</th></tr>'.$rows.'<tr><td colspan="2" class="r"><b>รวมเป็นเงิน</b></td><td class="r"><b>'.money($sum).'</b></td></tr></table><div>'.$qr.'</div></div>'
        .'<div style="margin-top:12px;font-size:14px">'.($b['bank'] !== '' ? '<b>ชำระผ่านธนาคาร :</b> '.h($b['bank']).($b['branch'] !== '' ? ' สาขา '.h($b['branch']) : '').'<br>' : '')
        .($b['acc_no'] !== '' ? '<b>เลขที่บัญชี :</b> '.h($b['acc_no']).' &nbsp; <b>ชื่อบัญชี :</b> '.h($b['acc_name']).'<br>' : '').($b['due'] !== '' ? '<b>กำหนดชำระภายใน :</b> '.h($b['due']).'<br>' : '')
        .($b['note'] !== '' ? '<span class="mut">'.nl2br(h($b['note'])).'</span>' : '').($b['bank'] === '' && $b['promptpay'] === '' && $b['qr'] === '' ? '<span class="mut">ติดต่อชำระเงินได้ที่ฝ่ายการเงินของโรงเรียน</span>' : '').'</div></div>';
    return ($pay ? '<div class="msg ok"><i class="fa fa-check-circle"></i> นักเรียนชำระเงินภาคเรียนนี้แล้ว</div>' : '').$doc.'<p class="noprint" style="text-align:right;margin:12px 0 0"><button type="button" class="btn info" onclick="window.print()"><i class="fa fa-print"></i> พิมพ์เอกสารชำระเงิน</button></p>';
}

function st_shop(array $S, bool $post): string
{
    if (st_cfg('stu_shop_open', '0') !== '1') {
        return '<div class="empty">-- ระบบยังไม่เปิดให้บริการ --</div>';
    }
    $sid = (int) $S['id'];
    $items = [];
    foreach (md_rows('stu_shop') as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        if (($d['active'] ?? '1') !== '0' && (empty($d['class']) || (string) $d['class'] === (string) $S['class'])) {
            $items[(string) $r['r1']] = $d;
        }
    }
    $mine = json_decode((string) md_get('stu_book', $sid, yt()), true) ?: [];
    $locked = $mine && ($mine['status'] ?? 'จองแล้ว') !== 'จองแล้ว';
    if ($post && !$locked) {
        if (isset($_POST['cancel'])) {
            md_set('stu_book', $sid, yt(), '', null);
            st_go('shop');
        }
        $list = [];
        $total = 0.0;
        foreach ($items as $k => $d) {
            $n = max(0, min(20, (int) ($_POST['q'][$k] ?? 0)));
            if ($n > 0) {
                $sizes = array_filter(array_map('trim', explode(',', (string) ($d['sizes'] ?? ''))));
                $size = (string) ($_POST['s'][$k] ?? '');
                $list[] = ['id' => $k, 'name' => (string) $d['name'], 'size' => in_array($size, $sizes, true) ? $size : '', 'qty' => $n, 'price' => (float) ($d['price'] ?? 0)];
                $total += $n * (float) ($d['price'] ?? 0);
            }
        }
        if ($list) {
            md_set('stu_book', $sid, yt(), '', json_encode(['items' => $list, 'total' => $total, 'date' => date('Y-m-d H:i'), 'status' => 'จองแล้ว'], JSON_UNESCAPED_UNICODE));
            st_go('shop');
        }
    }
    $o = st_saved_msg();
    if ($mine) {
        $r = '';
        foreach ($mine['items'] as $x) {
            $r .= '<tr><td>'.h($x['name']).($x['size'] !== '' ? ' ('.h($x['size']).')' : '').'</td><td class="c">'.(int) $x['qty'].'</td><td class="r">'.money($x['price']).'</td><td class="r">'.money($x['qty'] * $x['price']).'</td></tr>';
        }
        $o .= st_card('รายการจองของนักเรียน', 'fa-shopping-basket', '<div class="msg '.($locked ? 'ok' : 'in').'">สถานะ: <b>'.h($mine['status'] ?? 'จองแล้ว').'</b> (จองเมื่อ '.h(th_date(substr((string) $mine['date'], 0, 10), ' ')).')</div>'
            .'<table class="tb"><tr><th>สินค้า</th><th>จำนวน</th><th>ราคา/หน่วย</th><th>รวม</th></tr>'.$r.'<tr><td colspan="3" class="r"><b>รวมทั้งสิ้น</b></td><td class="r"><b>'.money($mine['total']).'</b></td></tr></table>'
            .(!$locked ? '<form method="post" action="?p=shop" style="margin-top:10px;text-align:right"><button class="btn bad sm" name="cancel" value="1" onclick="return confirm(\'ยกเลิกการจองทั้งหมด?\')">ยกเลิกการจอง</button></form>' : ''));
    }
    if (!$items) {
        return $o.'<div class="empty">ยังไม่มีรายการสินค้า</div>';
    }
    if ($locked) {
        return $o;
    }
    $cards = '';
    foreach ($items as $k => $d) {
        $sizes = array_filter(array_map('trim', explode(',', (string) ($d['sizes'] ?? ''))));
        $q = 0;
        $sz = '';
        foreach ($mine['items'] ?? [] as $x) {
            if ($x['id'] === $k) {
                $q = (int) $x['qty'];
                $sz = (string) $x['size'];
            }
        }
        $cards .= '<div class="card" style="margin:0">'.(!empty($d['img']) ? '<img src="/datas/mis/'.h($d['img']).'" alt="" style="width:100%;height:150px;object-fit:contain;margin-bottom:8px">' : '')
            .'<b>'.h($d['name'] ?? '').'</b><div class="mut" style="font-size:13px;min-height:20px">'.h($d['detail'] ?? '').'</div><div style="font-size:18px;color:#c0392b;margin:6px 0">'.money($d['price'] ?? 0).' บาท</div>'
            .($sizes ? '<select class="in" name="s['.h($k).']" style="margin-bottom:6px"><option value="">- ขนาด -</option>'.implode('', array_map(fn($s) => '<option'.($s === $sz ? ' selected' : '').'>'.h($s).'</option>', $sizes)).'</select>' : '')
            .'<div class="grp"><span>จำนวน</span><input class="in" type="number" min="0" max="20" name="q['.h($k).']" value="'.$q.'"></div></div>';
    }
    return $o.'<form method="post" action="?p=shop"><div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:14px">'.$cards.'</div>'
        .'<p style="text-align:right;margin:14px 0 0"><button class="btn" type="submit"><i class="fa fa-shopping-cart"></i> '.($mine ? 'แก้ไขการจอง' : 'จองสินค้า').'</button></p></form>';
}

/* ================================================================ บุคลิกภาพ */

function st_good(array $S, bool $post): string
{
    $sid = (int) $S['id'];
    [$cy, $ct] = st_ytl();
    $y = (int) ($_GET['year'] ?? $cy);
    $t = (int) ($_GET['term'] ?? $ct);
    $scope = $y.'/'.$t;
    $rows = [];
    foreach (md_rows('join', null, $scope) as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        if (in_array($sid, array_map('intval', (array) ($d['students'] ?? [])), true) && ($d['status'] ?? '') !== 'ไม่อนุมัติ') {
            $rows[] = [(string) ($d['date'] ?? ''), (string) ($d['name'] ?? '').(!empty($d['place']) ? ' ('.$d['place'].')' : '')];
        }
    }
    foreach (md_rows('sa_good', null, $scope) as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        if ((int) ($d['student'] ?? 0) === $sid) {
            $rows[] = [(string) ($d['date'] ?? ''), 'ความดี: '.($d['detail'] ?? '').(!empty($d['points']) ? ' (+'.(int) $d['points'].' คะแนน)' : '')];
        }
    }
    usort($rows, fn($a, $b) => strcmp($b[0], $a[0]));
    $years = [];
    for ($i = 0; $i < 4; $i++) {
        $years[$cy - $i] = $cy - $i;
    }
    $tr = '';
    foreach ($rows as $i => [$d, $l]) {
        $tr .= '<tr><td class="c">'.($i + 1).'</td><td class="c">'.($d ? h(th_date($d, ' ')) : '-').'</td><td class="c">'.$t.'/'.$y.'</td><td class="c">'.h(class_room_short($S['class'], $S['room'])).'</td><td>'.h($l).'</td></tr>';
    }
    return '<form method="get" action="./" style="display:flex;gap:8px;align-items:center;margin-bottom:12px;flex-wrap:wrap"><input type="hidden" name="p" value="good"><span>ภาคเรียน</span><select class="in" name="term" style="width:auto">'
        .implode('', array_map(fn($x) => '<option'.($x === $t ? ' selected' : '').'>'.$x.'</option>', [1, 2, 3])).'</select><span>ปีการศึกษา</span><select class="in" name="year" style="width:auto">'
        .implode('', array_map(fn($x) => '<option'.($x === $y ? ' selected' : '').'>'.$x.'</option>', $years)).'</select><button class="btn" type="submit">ตกลง</button></form>'
        .'<h4 style="font-weight:400;margin:0 0 8px">ข้อมูล ภาคเรียน '.$t.' ปีการศึกษา '.$y.'</h4><div class="tbw"><table class="tb st"><tr><th>ลำดับ</th><th>วันที่</th><th>ปีการศึกษา</th><th>ระดับชั้น</th><th>กิจกรรม</th></tr>'
        .($tr ?: '<tr><td colspan="5" class="c mut">ไม่มีข้อมูลกิจกรรม</td></tr>').'</table></div>';
}

/** หมวดของพฤติกรรมที่ถูกตัดคะแนน */
function st_bad_group(string $name): string
{
    if (preg_match('/สาย|เข้าแถว|ขาด|โดด|หนี|ชั้นเรียน|คาบ/u', $name)) {
        return 'att';
    }
    if (preg_match('/แต่งกาย|ทรงผม|ระเบียบ|วินัย|เครื่องแบบ|เล็บ|รองเท้า/u', $name)) {
        return 'rule';
    }
    return 'other';
}

function st_behave(array $S, bool $post): string
{
    $sid = (int) $S['id'];
    $sc = function_exists('conduct_scores') ? (conduct_scores()[$sid] ?? [0, 0, 100, []]) : [0, 0, 100, []];
    [$minus, $plus, $left, $x] = $sc;
    $rules = md_options('sa_rule', 'name');
    $g = ['att' => 0, 'rule' => 0, 'other' => 0];
    $det = '';
    foreach ($x['bad'] ?? [] as $i => $d) {
        $name = (string) ($rules[$d['rule'] ?? ''] ?? '-');
        $pt = (int) rule_points($d);
        $g[st_bad_group($name)] += $pt;
        $det .= '<tr><td class="c">'.($i + 1).'</td><td class="c">'.(!empty($d['date']) ? h(th_date($d['date'], ' ')) : '-').'</td><td>'.h($name).(!empty($d['detail']) ? '<br><small class="mut">'.h($d['detail']).'</small>' : '').'</td><td class="c c-bad">-'.$pt.'</td></tr>';
    }
    foreach ($x['good'] ?? [] as $d) {
        if ((int) ($d['points'] ?? 0) > 0) {
            $det .= '<tr><td class="c">+</td><td class="c">'.(!empty($d['date']) ? h(th_date($d['date'], ' ')) : '-').'</td><td>'.h($d['detail'] ?? '').'</td><td class="c c-ok">+'.(int) $d['points'].'</td></tr>';
        }
    }
    [$y, $t] = st_ytl();
    return '<div class="tbw"><table class="tb"><tr class="cap"><th colspan="6"><i class="fa fa-tags"></i> คะแนนพฤติกรรมนักเรียนทั้งหมด</th></tr><tr><th rowspan="2">ปีการศึกษา</th><th rowspan="2">คะแนน คงเหลือ ในภาคเรียน</th><th colspan="3">คะแนนที่ถูกหัก</th><th rowspan="2">คงเหลือสิ้นภาคเรียน</th></tr>'
        .'<tr><th>เข้าแถว/เข้าชั้นเรียน</th><th>ระเบียบวินัย</th><th>กรณีอื่นๆ</th></tr><tr><td class="c">'.$t.'/'.$y.'</td><td class="c">'.(100 + $plus).'</td><td class="c">'.$g['att'].'</td><td class="c">'.$g['rule'].'</td><td class="c">'.$g['other'].'</td><td class="c"><b>'.$left.'</b></td></tr>'
        .($det === '' ? '<tr class="cap"><th colspan="6"><i class="fa fa-times"></i> ไม่มีคะแนนที่ถูกหัก</th></tr>' : '').'</table></div>'
        .($det !== '' ? '<h4 style="font-weight:400;margin:16px 0 8px">รายละเอียด</h4><table class="tb st"><tr><th>ลำดับ</th><th>วันที่</th><th>พฤติกรรม</th><th>คะแนน</th></tr>'.$det.'</table>' : '');
}

function st_q9(array $S, bool $post): string
{
    $sid = (int) $S['id'];
    if ($post) {
        $a = [];
        foreach (array_keys(Q9_ITEMS) as $k) {
            $v = (string) ($_POST['a'][$k] ?? '');
            if (!isset(Q9_SCALE[$v])) {
                $a = null;
                break;
            }
            $a[$k] = (int) $v;
        }
        if ($a) {
            md_set('q9', $sid, yt(), '', json_encode(['a' => $a, 'score' => array_sum($a), 'date' => date('Y-m-d H:i')], JSON_UNESCAPED_UNICODE));
            st_go('q9');
        }
    }
    $old = json_decode((string) md_get('q9', $sid, yt()), true) ?: [];
    $res = '';
    if ($old) {
        [$lv, $cls] = st_q9_level((int) $old['score']);
        $care = (int) $old['score'] >= 7 || (int) ($old['a'][9] ?? 0) > 0;
        $res = '<div class="msg '.($care ? 'wa' : 'ok').'">ประเมินแล้วเมื่อ '.h(th_date(substr((string) $old['date'], 0, 10), ' ')).' — คะแนนรวม <b>'.(int) $old['score'].'</b> <span class="lv '.$cls.'">'.$lv.'</span>'
            .($care ? '<br>ผลนี้เป็นการคัดกรองเบื้องต้น ไม่ใช่การวินิจฉัย ครูที่ปรึกษาและครูแนะแนวพร้อมรับฟังและช่วยเหลือ หากรู้สึกไม่ไหว สามารถโทรปรึกษาสายด่วนสุขภาพจิต <b>1323</b> ได้ตลอด 24 ชั่วโมง' : '').'</div>';
    }
    $rows = '';
    foreach (Q9_ITEMS as $k => $q) {
        $rows .= '<tr><td class="c">'.$k.'</td><td>'.h($q).'</td><td>';
        foreach (Q9_SCALE as $v => $l) {
            $rows .= '<label style="display:inline-block;margin-right:14px;white-space:nowrap"><input type="radio" name="a['.$k.']" value="'.$v.'"'.((string) ($old['a'][$k] ?? '') === (string) $v ? ' checked' : '').' required> '.h($l).'</label>';
        }
        $rows .= '</td></tr>';
    }
    return st_saved_msg().'<div class="msg in" style="text-align:center;font-size:18px">แบบประเมินโรคซึมเศร้าด้วย 9 คำถาม ( 9Q)</div>'.$res
        .'<form method="post" action="?p=q9"><div class="tbw"><table class="tb"><tr><th style="width:40px">#</th><th>ใน 2 สัปดาห์ที่ผ่านมารวมวันนี้ ท่านมีอาการเหล่านี้บ่อยแค่ไหน</th><th>การประเมิน</th></tr>'.$rows
        .'<tr><th colspan="3" style="background:#fff"><button class="btn" type="submit"><i class="fa fa-paper-plane"></i> ส่งแบบสอบถาม</button></th></tr></table></div></form>';
}

function st_sdqp(array $S, bool $post): string
{
    $sid = (int) $S['id'];
    $choices = ['0' => 'ไม่จริง', '1' => 'ค่อนข้างจริง', '2' => 'จริง'];
    if ($post) {
        $a = [];
        foreach (array_keys(SDQP_ITEMS) as $k) {
            $v = (string) ($_POST['a'][$k] ?? '');
            if (!isset($choices[$v])) {
                $a = null;
                break;
            }
            $a[$k] = $v;
        }
        $imp = (string) ($_POST['impact'] ?? '');
        if ($a && isset(SDQ_IMPACT[$imp])) {
            $sc = sdq_score($a);
            md_set('sdq_par', $sid, yt(), '', json_encode(['answers' => $a, 'scores' => $sc, 'impact' => $imp, 'who' => mb_substr(trim((string) ($_POST['who'] ?? '')), 0, 100), 'date' => date('Y-m-d H:i')], JSON_UNESCAPED_UNICODE));
            st_go('sdqp');
        }
    }
    $old = json_decode((string) md_get('sdq_par', $sid, yt()), true) ?: [];
    $res = '';
    if ($old) {
        [$lv, $cls] = sdq_level((int) $old['scores']['total']);
        $res = '<div class="msg ok">ผู้ปกครองประเมินแล้วเมื่อ '.h(th_date(substr((string) $old['date'], 0, 10), ' ')).' — คะแนนรวมปัญหา <b>'.(int) $old['scores']['total'].'</b> <span class="lv '.$cls.'">'.$lv.'</span></div>';
    }
    $rows = '';
    foreach (SDQP_ITEMS as $k => $q) {
        $rows .= '<tr><td class="c">'.$k.'</td><td>'.h($q).'</td><td>';
        foreach ($choices as $v => $l) {
            $rows .= '<label style="display:inline-block;margin-right:14px;white-space:nowrap"><input type="radio" name="a['.$k.']" value="'.$v.'"'.((string) ($old['answers'][$k] ?? '') === (string) $v ? ' checked' : '').' required> '.h($l).'</label>';
        }
        $rows .= '</td></tr>';
    }
    $imp = '';
    foreach (SDQ_IMPACT as $v => $l) {
        $imp .= '<label style="display:inline-block;margin-right:18px"><input type="radio" name="impact" value="'.$v.'"'.((string) ($old['impact'] ?? '') === (string) $v ? ' checked' : '').' required> '.h($l).'</label>';
    }
    return st_saved_msg().'<div class="msg in" style="text-align:center;font-size:18px">แบบประเมินพฤติกรรมฉบับผู้ปกครองประเมิน</div>'.$res
        .'<p>ให้ผู้ปกครองอ่านข้อความแต่ละข้อ แล้วเลือกคำตอบที่ตรงกับพฤติกรรมของนักเรียนมากที่สุด ในช่วง 6 เดือนที่ผ่านมา</p>'
        .'<form method="post" action="?p=sdqp"><div style="max-width:420px"><label class="l">ชื่อผู้ปกครองผู้ประเมิน</label><input class="in" name="who" value="'.h($old['who'] ?? (string) $S['parent']).'"></div>'
        .'<div class="tbw" style="margin-top:10px"><table class="tb"><tr><th style="width:40px">ข้อ</th><th>พฤติกรรม</th><th>ความคิดเห็น</th></tr>'.$rows.'</table></div>'
        .'<h5 style="font-size:16px;font-weight:400;margin:16px 0 6px">1. ด้านอารมณ์ ด้านสมาธิ ด้านพฤติกรรม หรือความสามารถเข้ากับผู้อื่น โดยรวมท่านคิดว่านักเรียนมีปัญหาด้านใดด้านหนึ่งหรือไม่</h5>'.$imp
        .'<p style="text-align:right;margin:14px 0 0"><button class="btn" type="submit"><i class="fa fa-paper-plane"></i> ส่งแบบประเมิน</button></p></form>';
}

/* ================================================================ การเข้าเรียน */

function st_att(array $S, bool $post): string
{
    $sid = (int) $S['id'];
    [$from, $to] = term_range();
    $flag = school_att([$sid], $from, $to)[$sid] ?? [];
    ksort($flag);
    $today = $flag[date('Y-m-d')] ?? null;
    $f = array_count_values($flag);
    $cls = [];
    $det = '';
    $n = 0;
    try {
        $list = q('SELECT A.`date`, A.`period`, A.`status`, C.`course_code`, C.`course_name` FROM {attendance} A JOIN {course} C ON C.`id`=A.`course_id` WHERE A.`student_id`=? AND A.`date` BETWEEN ? AND ? ORDER BY A.`date` DESC, A.`period`', [$sid, $from, $to]);
    } catch (\Throwable $e) {
        $list = [];
    }
    foreach ($list as $r) {
        $cls[$r['status']] = ($cls[$r['status']] ?? 0) + 1;
        if (!in_array($r['status'], ['present', 'activity', 'remedial'], true)) {
            $det .= '<tr><td class="c">'.(++$n).'</td><td class="c">'.h(th_date($r['date'], ' ')).'</td><td class="c">'.(int) $r['period'].'</td><td>'.h($r['course_code'].' '.$r['course_name']).'</td><td class="c">'.h(ATT_TH[$r['status']] ?? $r['status']).'</td></tr>';
        }
    }
    $fd = '';
    $m = 0;
    foreach (array_reverse($flag, true) as $d => $v) {
        if ($v !== 'ม') {
            $fd .= '<tr><td class="c">'.(++$m).'</td><td class="c">'.h(th_date($d, ' ')).'</td><td class="c">'.h(SCHOOL_ATT[$v] ?? $v).'</td></tr>';
        }
    }
    $fLate = (int) ($f['ส'] ?? 0);
    $fAbs = (int) ($f['ข'] ?? 0) + (int) ($f['น'] ?? 0);
    $fLeave = (int) ($f['ล'] ?? 0) + (int) ($f['ป'] ?? 0);
    $cLate = (int) ($cls['late'] ?? 0);
    $cAbs = (int) ($cls['absent'] ?? 0) + (int) ($cls['skip'] ?? 0);
    $num = fn($v) => '<td class="c" style="font-size:20px">'.$v.'</td>';
    return '<div class="msg wa"><i class="fa fa-briefcase"></i> วันที่มาเรียนปัจจุบัน : '.($today === null ? '"ไม่มีข้อมูลการมาเรียน ณ วันที่ปัจจุบัน."' : '<b>'.h(SCHOOL_ATT[$today] ?? $today).'</b> ('.h(th_date(date('Y-m-d'), ' ')).')')
        .' <a class="btn info sm" href="#dt">เพิ่มเติม..</a></div>'
        .'<div class="msg ok2"><i class="fa fa-file-text"></i> <b>รายงานพฤติกรรม สาย/ขาด/ลากิจ/ลาป่วย</b> <i class="fa fa-calendar"></i> ระหว่างวันที่ '.h(th_date($from, ' ')).' ถึง '.h(th_date($to, ' '))
        .'<br>รหัส <u>'.h($S['student_id']).'</u> ชื่อ <u>'.h($S['name']).'</u> ชั้น <u>'.h(class_room_short($S['class'], $S['room'])).'</u> เลขที่ <u>'.(int) $S['number'].'</u></div>'
        .'<div class="tbw"><table class="tb"><tr class="cap"><th colspan="10" style="font-size:18px">ข้อมูลสรุปพฤติกรรม สาย/ขาด/ลากิจ/ลาป่วย</th></tr>'
        .'<tr><th colspan="3" style="background:#dbe9f1;color:#333">การเข้าแถว</th><th colspan="4" style="background:#eee7d6;color:#333">การเข้าเรียน</th><th colspan="3" style="background:#d9efe0;color:#333">สรุป(สาย+ขาด)</th></tr>'
        .'<tr><th>สาย (ครั้ง)</th><th>ขาด (ครั้ง)</th><th>ลา (ครั้ง)</th><th>สาย (คาบ)</th><th>ขาด (คาบ)</th><th>ลากิจ (คาบ)</th><th>ลาป่วย (คาบ)</th><th>เข้าแถว</th><th>เข้าเรียน</th><th>รวม</th></tr>'
        .'<tr>'.$num($fLate).$num($fAbs).$num($fLeave).$num($cLate).$num($cAbs).$num((int) ($cls['leave'] ?? 0)).$num((int) ($cls['sick'] ?? 0)).$num($fLate + $fAbs).$num($cLate + $cAbs).$num($fLate + $fAbs + $cLate + $cAbs).'</tr></table></div>'
        .'<div class="row r2" id="dt" style="margin-top:16px"><table class="tb st"><tr class="cap"><th colspan="3">รายละเอียดการเข้าแถว</th></tr><tr><th>ลำดับ</th><th>วันที่</th><th>รายละเอียด</th></tr>'.($fd ?: '<tr><td colspan="3" class="c mut">-</td></tr>').'</table>'
        .'<table class="tb st"><tr class="cap"><th colspan="5">รายละเอียดการเข้าชั้นเรียน</th></tr><tr><th>ลำดับ</th><th>วันที่</th><th>คาบ</th><th>วิชา</th><th>รายละเอียด</th></tr>'.($det ?: '<tr><td colspan="5" class="c mut">-</td></tr>').'</table></div>';
}
