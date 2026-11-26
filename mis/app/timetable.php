<?php
/**
 * mis/app/timetable.php
 * ระบบจัดตารางเรียน / ตารางสอน
 *  - จัดตาราง (โมดูลวิชาการ > จัดตารางเรียน): เฉพาะผู้ดูแลระบบและผู้มีสิทธิ์โมดูลวิชาการ
 *    มุมมองรายห้องเรียน และรายครู ตรวจชนกันก่อนบันทึก: ครูสอนซ้อนเวลา, ห้องเรียนมีวิชาซ้อน, ห้อง/สถานที่ถูกใช้ซ้อน
 *    (วิชาเดียวกัน ครูคนเดียวกัน หลายห้องพร้อมกัน = เรียนรวม ไม่นับว่าชน)
 *  - พิมพ์ (A4 แนวนอน หน้าละ ๑ ตาราง พิมพ์ทีละหลายหน้าได้): ตารางเรียนรายห้อง / ตารางสอนรายครู / ตารางการใช้ห้อง-สถานที่
 *    ครูทุกคนพิมพ์ได้ (อ่านอย่างเดียว) ที่หน้า "ตารางสอน" ของครู
 *  - วันเรียน = "กำหนดวันเรียน", คาบ/เวลา = "กำหนดคาบเรียน", ห้อง/สถานที่ = "ห้องเรียน/ประชุม/กิจกรรม"
 * ข้อมูล: {mis_data} kind=tt r1='R<ห้อง>' r2=ปี/ภาค r3='<วัน>-<คาบ>' value={"c":รายวิชา,"v":ห้อง/สถานที่}
 *  (ข้อมูลรุ่นเก่า r1=<รหัสครู> value='รายวิชา|ห้อง' ถูกย้ายมารูปแบบใหม่อัตโนมัติ)
 */
defined('MIS') or exit;

const TT_DAY_COLOR = [1 => '#F7D358', 2 => '#F2A293', 3 => '#5DD5A4', 4 => '#F3A35B', 5 => '#AEB7FF', 6 => '#C39BD3', 0 => '#F1948A'];

/** สีพื้นอ่อนของแถววัน (ผสมสีประจำวันกับสีขาว) */
function tt_tint(int $day, float $white = 0.86): string
{
    $hex = ltrim(TT_DAY_COLOR[$day] ?? '#DDDDDD', '#');
    $o = '#';
    foreach (str_split($hex, 2) as $c) {
        $o .= sprintf('%02X', (int) round(hexdec($c) * (1 - $white) + 255 * $white));
    }
    return $o;
}

/** ชื่อครูแบบในตารางเรียน: ครู + ชื่อต้น (ไม่มีคำนำหน้า/นามสกุล) */
function tt_teacher_label(string $n): string
{
    $n = trim(preg_replace('/^(ครู|นางสาว|นาง|นาย|ว่าที่\s*ร(้อย)?\.?\s*ต(รี)?\.?|ดร\.|Mr\.|Mrs\.|Ms\.|Miss)\s*/u', '', trim($n)));
    $first = preg_split('/\s+/u', $n)[0] ?? '';
    return $first !== '' ? 'ครู'.$first : '';
}
const TT_COLORS = ['#E8F6F3', '#FEF5E7', '#EBF5FB', '#F5EEF8', '#FDEDEC', '#EAFAF1', '#FEF9E7', '#EBEDEF', '#F4ECF7', '#E8F8F5', '#FDF2E9', '#EAF2F8', '#F9EBEA', '#E9F7EF', '#FCF3CF', '#D6EAF8'];

/** ผู้จัดตารางได้: ผู้ดูแลระบบ หรือผู้มีสิทธิ์โมดูลวิชาการ */
function tt_can_edit(): bool
{
    $u = me();
    return $u && ((int) $u['status'] === 1 || in_array('academic', function_exists('user_modules') ? user_modules() : [], true));
}

/** วันเรียน [วัน => ชื่อ] เรียง จันทร์..อาทิตย์ */
function tt_days(): array
{
    $set = json_decode((string) md_get('cfg', 'school_days'), true);
    $set = is_array($set) && $set ? array_map('intval', $set) : [1, 2, 3, 4, 5];
    $o = [];
    foreach ([1, 2, 3, 4, 5, 6, 0] as $d) {
        if (in_array($d, $set, true)) {
            $o[$d] = WEEKDAYS[$d];
        }
    }
    return $o ?: [1 => 'จันทร์', 2 => 'อังคาร', 3 => 'พุธ', 4 => 'พฤหัสบดี', 5 => 'ศุกร์'];
}

/** คาบเรียน [คาบที่ => [เริ่ม, สิ้นสุด, หมายเหตุ]] */
function tt_periods(): array
{
    static $p = null;
    if ($p !== null) {
        return $p;
    }
    $p = [];
    foreach (md_rows('periods') as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        $no = (int) ($d['no'] ?? 0);
        if ($no >= 1 && $no <= 15) {
            $p[$no] = [(string) ($d['start'] ?? ''), (string) ($d['end'] ?? ''), (string) ($d['note'] ?? '')];
        }
    }
    ksort($p);
    if (!$p) {
        foreach (['08:30', '09:20', '10:10', '11:00', '12:50', '13:40', '14:30', '15:20'] as $i => $s) {
            $p[$i + 1] = [$s, date('H:i', strtotime($s) + 50 * 60), ''];
        }
    }
    return $p;
}

function tt_period_label(int $no): string
{
    [$s, $e] = tt_periods()[$no] ?? ['', ''];
    return 'คาบที่ '.$no.($s !== '' ? '<br><span class="tm">'.h(str_replace(':', '.', $s)).'-'.h(str_replace(':', '.', $e)).'</span>' : '');
}

/** ห้องเรียน/สถานที่ [id => ชื่อ] */
function tt_venues(): array
{
    $o = [];
    foreach (md_rows('venue') as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        if (trim((string) ($d['name'] ?? '')) !== '') {
            $o[(string) $r['r1']] = trim((string) $d['name']).(($d['building'] ?? '') !== '' ? ' ('.$d['building'].')' : '');
        }
    }
    asort($o);
    return $o;
}

/** คาบต่อสัปดาห์ที่ควรจัด: ชั่วโมงต่อภาค / 20 สัปดาห์ (หรือ หน่วยกิต x 2) */
function tt_need(array $c): int
{
    $h = (int) ($c['period'] ?? 0);
    if ($h > 0) {
        return max(1, (int) round($h / 20));
    }
    return max(1, (int) round((float) ($c['credit'] ?? 0) * 2));
}

/** ตารางทั้งหมดของภาคเรียน: [ห้อง][วัน-คาบ] => ['c' => รายวิชา, 'v' => สถานที่] (ย้ายข้อมูลรุ่นเก่าให้อัตโนมัติ) */
function tt_all(?string $yt = null): array
{
    $yt = $yt ?? yt();
    if (isset($GLOBALS['__tt_cache'][$yt])) {
        return $GLOBALS['__tt_cache'][$yt];
    }
    $o = [];
    $legacy = [];
    foreach (md_rows('tt', null, $yt) as $r) {
        $k = (string) $r['r1'];
        if (preg_match('/^R(\d+)$/', $k, $m)) {
            $d = json_decode((string) $r['value'], true) ?: [];
            if ((int) ($d['c'] ?? 0) > 0) {
                $o[(int) $m[1]][(string) $r['r3']] = ['c' => (int) $d['c'], 'v' => (string) ($d['v'] ?? '')];
            }
        } else {
            $legacy[] = $r;
        }
    }
    foreach ($legacy as $r) {
        [$c, $room] = array_pad(explode('|', (string) $r['value']), 2, 0);
        $slot = (string) $r['r3'];
        if ((int) $c > 0 && (int) $room > 0 && !isset($o[(int) $room][$slot])) {
            $o[(int) $room][$slot] = ['c' => (int) $c, 'v' => ''];
            tt_put((int) $room, $slot, (int) $c, '', $yt);
        }
        md_set('tt', (string) $r['r1'], $yt, $slot, null);
    }
    return $GLOBALS['__tt_cache'][$yt] = $o;
}

function tt_put(int $room, string $slot, int $cid, string $venue, ?string $yt = null): void
{
    $yt = $yt ?? yt();
    md_set('tt', 'R'.$room, $yt, $slot, $cid > 0 ? json_encode(['c' => $cid, 'v' => $venue]) : null);
    if (isset($GLOBALS['__tt_cache'][$yt])) { // ให้การตรวจชนในคำขอเดียวกันเห็นค่าล่าสุด
        if ($cid > 0) {
            $GLOBALS['__tt_cache'][$yt][$room][$slot] = ['c' => $cid, 'v' => $venue];
        } else {
            unset($GLOBALS['__tt_cache'][$yt][$room][$slot]);
        }
    }
}

/** รายวิชาของภาคเรียนปัจจุบันทั้งหมด (พร้อมชื่อครู) */
function tt_courses(): array
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    $c = [];
    foreach (q('SELECT C.*, U.`name` teacher FROM {course} C LEFT JOIN {user} U ON U.`id`=C.`teacher_id` WHERE C.`year`=? AND C.`term`=? ORDER BY C.`course_code`, C.`id`', term()) as $r) {
        $c[(int) $r['id']] = $r;
    }
    // วิชาที่อยู่ในตารางแต่ไม่ใช่ภาคนี้ (เช่น ข้อมูลเก่า) ให้ยังแสดงชื่อได้
    $miss = [];
    foreach (tt_all() as $slots) {
        foreach ($slots as $e) {
            if (!isset($c[$e['c']])) {
                $miss[] = $e['c'];
            }
        }
    }
    return $c += courses_by_id($miss);
}

/** รายวิชาของห้อง: ลงทะเบียนในห้องนี้ หรือ วิชาของชั้นนี้ที่ยังไม่มีการลงทะเบียน */
function tt_room_courses(int $room): array
{
    $all = tt_courses();
    $ids = [];
    $enrolled = [];
    if ($all) {
        $in = implode(',', array_map('intval', array_keys($all)));
        foreach (q('SELECT `course_id`, `room` FROM {grade} WHERE `course_id` IN ('.$in.') GROUP BY `course_id`, `room`') as $g) {
            $enrolled[(int) $g['course_id']] = true;
            if ((int) $g['room'] === $room) {
                $ids[(int) $g['course_id']] = true;
            }
        }
    }
    $cls = tt_room_class($room);
    foreach ($all as $id => $c) {
        if (!isset($enrolled[$id]) && $cls && (int) $c['class'] === $cls && (int) $c['year'] === term()[0]) {
            $ids[$id] = true;
        }
    }
    foreach (tt_all()[$room] ?? [] as $e) {
        $ids[$e['c']] = true;
    }
    return array_intersect_key($all, $ids);
}

/** ชั้นของห้อง */
function tt_room_class(int $room): int
{
    foreach (rooms_by_class() as $cid => $rooms) {
        if (isset($rooms[$room])) {
            return (int) $cid;
        }
    }
    return 0;
}

/** คู่ (รายวิชา, ห้อง) ที่ครูสอน */
function tt_teacher_pairs(int $tid): array
{
    $mine = array_filter(tt_courses(), fn($c) => (int) $c['teacher_id'] === $tid && (int) $c['year'] === term()[0] && (int) $c['term'] === term()[1]);
    $rooms = course_rooms($mine);
    $byClass = rooms_by_class();
    $o = [];
    foreach ($mine as $id => $c) {
        $rs = array_keys($rooms[$id] ?? []) ?: array_keys($byClass[(int) $c['class']] ?? []);
        foreach ($rs as $r) {
            if ((int) $r > 0) {
                $o[$id.'|'.$r] = [$id, (int) $r];
            }
        }
    }
    foreach (tt_all() as $r => $slots) {
        foreach ($slots as $e) {
            if ((int) (tt_courses()[$e['c']]['teacher_id'] ?? 0) === $tid) {
                $o[$e['c'].'|'.$r] = [$e['c'], $r];
            }
        }
    }
    return $o;
}

/** ตารางของครู: [วัน-คาบ] => [[ห้อง, รายวิชา, สถานที่], ...] */
function tt_teacher_grid(int $tid): array
{
    $o = [];
    $cs = tt_courses();
    foreach (tt_all() as $r => $slots) {
        foreach ($slots as $slot => $e) {
            if ($tid > 0 && (int) ($cs[$e['c']]['teacher_id'] ?? 0) === $tid) {
                $o[$slot][] = [$r, $e['c'], $e['v']];
            }
        }
    }
    return $o;
}

/** ตารางการใช้สถานที่: [วัน-คาบ] => [[ห้อง, รายวิชา], ...] */
function tt_venue_grid(string $vid): array
{
    $o = [];
    foreach (tt_all() as $r => $slots) {
        foreach ($slots as $slot => $e) {
            if ($e['v'] === $vid && $vid !== '') {
                $o[$slot][] = [$r, $e['c']];
            }
        }
    }
    return $o;
}

/** ตรวจว่าวาง (ห้อง, รายวิชา, สถานที่) ที่คาบนี้ได้ไหม: '' = ได้ */
function tt_conflict(int $room, string $slot, int $cid, string $venue): string
{
    $cs = tt_courses();
    $tid = (int) ($cs[$cid]['teacher_id'] ?? 0);
    foreach (tt_all() as $r => $slots) {
        if ($r === $room || !isset($slots[$slot])) {
            continue;
        }
        $e = $slots[$slot];
        if ($tid > 0 && (int) ($cs[$e['c']]['teacher_id'] ?? 0) === $tid && $e['c'] !== $cid) {
            return tt_teacher_word((string) ($cs[$cid]['teacher'] ?? '')).' สอน '.($cs[$e['c']]['course_code'] ?? '').' ห้อง '.room_short($r).' อยู่แล้ว';
        }
        if ($venue !== '' && $e['v'] === $venue && $e['c'] !== $cid) {
            return (tt_venues()[$venue] ?? 'สถานที่').' ถูกใช้โดย '.room_short($r).' ('.($cs[$e['c']]['course_code'] ?? '').') แล้ว';
        }
    }
    return '';
}

/** รายการที่ชนกันทั้งภาคเรียน (เช่น ข้อมูลเก่า หรือเปลี่ยนครูผู้สอนภายหลัง) */
function tt_conflicts_all(): array
{
    $cs = tt_courses();
    $seenT = $seenV = $o = [];
    foreach (tt_all() as $r => $slots) {
        foreach ($slots as $slot => $e) {
            $tid = (int) ($cs[$e['c']]['teacher_id'] ?? 0);
            if ($tid > 0) {
                foreach ($seenT[$tid][$slot] ?? [] as [$r2, $c2]) {
                    if ($c2 !== $e['c']) {
                        $o[] = [$slot, tt_teacher_word((string) ($cs[$e['c']]['teacher'] ?? '')).' สอนซ้อน '.room_short($r2).' และ '.room_short($r), $r];
                    }
                }
                $seenT[$tid][$slot][] = [$r, $e['c']];
            }
            if ($e['v'] !== '') {
                foreach ($seenV[$e['v']][$slot] ?? [] as [$r2, $c2]) {
                    if ($c2 !== $e['c']) {
                        $o[] = [$slot, (tt_venues()[$e['v']] ?? 'สถานที่').' ใช้ซ้อน '.room_short($r2).' และ '.room_short($r), $r];
                    }
                }
                $seenV[$e['v']][$slot][] = [$r, $e['c']];
            }
        }
    }
    return $o;
}

/** "ครู<ชื่อ>" ไม่ซ้ำคำว่าครู */
function tt_teacher_word(string $n): string
{
    return str_starts_with($n, 'ครู') ? $n : 'ครู'.$n;
}

function tt_slot_text(string $slot): string
{
    [$d, $p] = array_map('intval', explode('-', $slot.'-0'));
    return 'วัน'.(WEEKDAYS[$d] ?? '?').' คาบที่ '.$p;
}

/** สีประจำรายวิชา */
function tt_color(int $cid): string
{
    return TT_COLORS[$cid % count(TT_COLORS)];
}

/* ================================================================ หน้าจัดตาราง (ผู้ดูแล / วิชาการ) */

function adm_timetable(bool $post)
{
    if (!tt_can_edit()) {
        return ['จัดตารางเรียน', msg_box('การจัดตารางเรียนทำได้เฉพาะผู้ดูแลระบบและฝ่ายวิชาการ — ครูพิมพ์ตารางได้ที่เมนู "ตารางสอน"', false)];
    }
    $view = ($_GET['view'] ?? '') === 'teacher' ? 'teacher' : 'room';
    $rooms = room_options();
    $teachers = personnel_options();
    $id = (int) ($_GET['id'] ?? 0);
    if ($view === 'room' && !isset($rooms[$id])) {
        $id = (int) array_key_first($rooms);
    }
    if ($view === 'teacher' && !isset($teachers[$id])) {
        $id = (int) array_key_first($teachers);
    }
    $msgs = [];
    if ($post && $id) {
        $msgs = $view === 'room' ? tt_save_room($id) : tt_save_teacher($id);
        if (!$msgs && !isset($_GET['ajax'])) {
            redirect(a_url('timetable', '&view='.$view.'&id='.$id.'&msg=saved'));
        }
    }
    if (isset($_GET['clear']) && $view === 'room' && $id) {
        foreach (array_keys(tt_all()[$id] ?? []) as $slot) {
            tt_put($id, (string) $slot, 0, '');
        }
        redirect(a_url('timetable', '&view=room&id='.$id.'&msg=saved'));
    }
    $days = tt_days();
    $pers = tt_periods();
    $cs = tt_courses();
    $venues = tt_venues();
    $all = tt_all();

    // ตัวเลือกด้านบน
    $list = $view === 'room' ? $rooms : $teachers;
    $keys = array_keys($list);
    $pos = array_search($id, $keys, true);
    $prev = $pos > 0 ? $keys[$pos - 1] : null;
    $next = $pos !== false && $pos < count($keys) - 1 ? $keys[$pos + 1] : null;
    $opts = '';
    foreach ($list as $k => $n) {
        $opts .= '<option value="'.(int) $k.'"'.($k === $id ? ' selected' : '').'>'.h($n).'</option>';
    }
    $tab = fn($v, $l) => '<a href="'.h(a_url('timetable', '&view='.$v)).'" class="tt-tab'.($v === $view ? ' on' : '').'">'.$l.'</a>';
    $printUrl = '?module=task&option=teacher_timetable&func=Print&kind='.$view.'&ids='.$id;
    $top = '<div class="tt-top">'.$tab('room', 'จัดรายห้องเรียน').$tab('teacher', 'จัดรายครู')
        .'<form method="get" action="index.php" style="display:inline-block;margin-left:14px"><input type="hidden" name="module" value="admin"/><input type="hidden" name="option" value="timetable"/><input type="hidden" name="view" value="'.$view.'"/>'
        .($prev !== null ? '<a class="tt-nav" href="'.h(a_url('timetable', '&view='.$view.'&id='.$prev)).'" title="ก่อนหน้า">‹</a>' : '<span class="tt-nav off">‹</span>')
        .'<select name="id" onchange="this.form.submit()" style="height:28px;min-width:230px;font-size:14px">'.$opts.'</select>'
        .($next !== null ? '<a class="tt-nav" href="'.h(a_url('timetable', '&view='.$view.'&id='.$next)).'" title="ถัดไป">›</a>' : '<span class="tt-nav off">›</span>').'</form>'
        .'<span style="float:right"><a class="tt-btn" target="_blank" href="'.h($printUrl).'">🖨 พิมพ์ตารางนี้</a> <a class="tt-btn" href="'.h(a_url('room_tt')).'">พิมพ์หลายตาราง</a></span></div>';

    // ตัวเลือกในแต่ละช่อง
    $summary = '';
    $cells = [];
    if ($view === 'room') {
        $mine = tt_room_courses($id);
        $placed = [];
        foreach ($all[$id] ?? [] as $e) {
            $placed[$e['c']] = ($placed[$e['c']] ?? 0) + 1;
        }
        foreach ($mine as $cid => $c) {
            $need = tt_need($c);
            $summary .= '<tr data-c="'.$cid.'"><td><span class="dot" style="background:'.tt_color($cid).'"></span><b>'.h($c['course_code']).'</b> '.h($c['course_name']).'</td><td>'.h($c['teacher'] ?? '-').'</td>'
                .'<td class="c need">'.$need.'</td><td class="c cnt">'.($placed[$cid] ?? 0).'</td></tr>';
        }
        // ครู/สถานที่ที่ไม่ว่าง (จากห้องอื่น) เพื่อปิดตัวเลือกที่ชน
        foreach ($days as $d => $dn) {
            foreach ($pers as $p => $x) {
                $slot = $d.'-'.$p;
                $cur = $all[$id][$slot] ?? ['c' => 0, 'v' => ''];
                $o = '<option value="">- ว่าง -</option>';
                foreach ($mine as $cid => $c) {
                    $why = $cid === $cur['c'] ? '' : tt_conflict($id, $slot, $cid, '');
                    $o .= '<option value="'.$cid.'" data-col="'.tt_color($cid).'" data-t="'.h(name_short((string) ($c['teacher'] ?? ''))).'"'.($cid === $cur['c'] ? ' selected' : '').($why !== '' ? ' disabled' : '').'>'
                        .h($c['course_code'].' '.mb_substr((string) $c['course_name'], 0, 22).($why !== '' ? ' ✗ ครูไม่ว่าง' : '')).'</option>';
                }
                $cells[$slot] = [$o, tt_venue_select($slot, $cur['v'], $id, $cur['c']), $cur['c']];
            }
        }
        $sumHead = '<tr><th>รายวิชา</th><th>ครูผู้สอน</th><th>ควรจัด<br>(คาบ/สัปดาห์)</th><th>จัดแล้ว</th></tr>';
    } else {
        $pairs = tt_teacher_pairs($id);
        $grid = tt_teacher_grid($id);
        $placed = [];
        foreach ($grid as $es) {
            foreach ($es as [$r, $cid]) {
                $placed[$cid.'|'.$r] = ($placed[$cid.'|'.$r] ?? 0) + 1;
            }
        }
        foreach ($pairs as $k => [$cid, $r]) {
            $c = $cs[$cid] ?? [];
            $summary .= '<tr data-c="'.h($k).'"><td><span class="dot" style="background:'.tt_color($cid).'"></span><b>'.h($c['course_code'] ?? '').'</b> '.h($c['course_name'] ?? '').'</td><td>'.h(class_room_short($c['class'] ?? 0, $r)).'</td>'
                .'<td class="c need">'.tt_need($c).'</td><td class="c cnt">'.($placed[$k] ?? 0).'</td></tr>';
        }
        foreach ($days as $d => $dn) {
            foreach ($pers as $p => $x) {
                $slot = $d.'-'.$p;
                $curs = $grid[$slot] ?? [];
                $curK = $curs ? $curs[0][1].'|'.$curs[0][0] : '';
                $o = '<option value="">- ว่าง -</option>';
                foreach ($pairs as $k => [$cid, $r]) {
                    $occ = $all[$r][$slot] ?? null;
                    $busy = $occ && (int) ($cs[$occ['c']]['teacher_id'] ?? 0) !== $id;
                    $o .= '<option value="'.h($k).'" data-col="'.tt_color($cid).'"'.($k === $curK ? ' selected' : '').($busy && $k !== $curK ? ' disabled' : '').'>'
                        .h(($cs[$cid]['course_code'] ?? '').' '.class_room_short($cs[$cid]['class'] ?? 0, $r).($busy && $k !== $curK ? ' ✗ ห้องมีวิชาอื่น' : '')).'</option>';
                }
                $extra = count($curs) > 1 ? '<div class="tt-note">เรียนรวม: '.h(implode(', ', array_map(fn($x) => room_short($x[0]), $curs))).'</div>' : '';
                $cells[$slot] = [$o, tt_venue_select($slot, $curs[0][2] ?? '', $curs[0][0] ?? 0, $curs[0][1] ?? 0).$extra, $curs ? $curs[0][1] : 0];
            }
        }
        $sumHead = '<tr><th>รายวิชา</th><th>ห้อง</th><th>ควรจัด<br>(คาบ/สัปดาห์)</th><th>จัดแล้ว</th></tr>';
    }

    // ตาราง
    $head = '<tr><th class="dh">วัน / คาบ</th>';
    foreach ($pers as $p => $x) {
        $head .= '<th>'.tt_period_label($p).'</th>';
    }
    $rows = '';
    foreach ($days as $d => $dn) {
        $rows .= '<tr><th class="dh" style="background:'.(TT_DAY_COLOR[$d] ?? '#ccc').'">'.h($dn).'</th>';
        foreach ($pers as $p => $x) {
            $slot = $d.'-'.$p;
            [$o, $v, $c] = $cells[$slot];
            $rows .= '<td class="tt-c"'.($c ? ' style="background:'.tt_color($c).'"' : '').'><select name="c['.$slot.']" class="tt-s">'.$o.'</select><div class="tt-t"></div>'.$v.'</td>';
        }
        $rows .= '</tr>';
    }
    $conf = tt_conflicts_all();
    $confHtml = '';
    if ($conf) {
        $li = '';
        foreach (array_slice($conf, 0, 30) as [$slot, $txt, $r]) {
            $li .= '<li>'.h(tt_slot_text($slot)).': '.h($txt).' — <a href="'.h(a_url('timetable', '&view=room&id='.$r)).'">แก้ที่ห้อง '.h(room_short($r)).'</a></li>';
        }
        $confHtml = '<div class="tt-warn"><b>พบตารางชนกัน '.count($conf).' รายการ</b> (เช่น เปลี่ยนครูผู้สอนหลังจัดตาราง) กรุณาแก้ไข:<ul>'.$li.'</ul></div>';
    }
    $err = $msgs ? '<div class="tt-warn"><b>บันทึกแล้ว ยกเว้น '.count($msgs).' ช่องที่ชนกัน (คงค่าเดิมไว้):</b><ul><li>'.implode('</li><li>', array_map('h', $msgs)).'</li></ul></div>' : '';
    $who = $view === 'room' ? 'ห้อง '.($rooms[$id] ?? '') : ($teachers[$id] ?? '');
    $form = '<form method="post" id="ttf" action="'.h(a_url('timetable', '&view='.$view.'&id='.$id)).'">'
        .'<div class="tt-wrap"><table class="tt-g">'.$head.'</tr>'.$rows.'</table></div>'
        .'<div class="tt-bar"><span class="gray_12">ช่องที่มี ✗ คือครูหรือห้องไม่ว่างในคาบนั้น (ระบบไม่ให้เลือก) — เลือกห้อง/สถานที่ได้ใต้ชื่อวิชา (เว้นว่าง = ห้องประจำชั้น)</span>'
        .($view === 'room' ? ' <a href="'.h(a_url('timetable', '&view=room&id='.$id.'&clear=1')).'" class="tt-btn" style="background:#fdecea;color:#c0392b" onclick="return confirm(\'ล้างตารางของห้องนี้ทั้งหมด?\')">ล้างตารางห้องนี้</a>' : '')
        .' <a href="javascript:void(0)" onclick="document.getElementById(\'ttf\').submit()" id="pinkgreenbtns"><img src="../icon_tools/vec_add_white.png" border="0" height="16"/> บันทึกตาราง</a></div></form>';
    $side = '<div class="tt-sum"><div class="tt-sh">รายวิชาของ'.h($who).' ภาคเรียน '.h(yt()).'</div><table>'.$sumHead.($summary ?: '<tr><td colspan="4" class="c gray_12">ยังไม่มีรายวิชา — เพิ่มรายวิชาและลงทะเบียนเรียนก่อน</td></tr>').'</table>'
        .'<div class="gray_12" style="padding:6px 8px">ควรจัด = ชั่วโมงต่อภาค ÷ 20 สัปดาห์ (หรือหน่วยกิต × 2) · สีแดง = ยังจัดไม่ครบ / เกิน</div></div>';
    return ['จัดตารางเรียน', tt_css().saved_msg().$confHtml.$err.'<div class="tt-box">'.$top.$form.$side.'</div>'.tt_js()];
}

/** ตัวเลือกห้อง/สถานที่ของช่อง */
function tt_venue_select(string $slot, string $cur, int $room, int $cid): string
{
    $vs = tt_venues();
    if (!$vs) {
        return '';
    }
    $o = '<option value="">ห้องประจำชั้น</option>';
    $used = [];
    foreach (tt_all() as $r => $slots) {
        $e = $slots[$slot] ?? null;
        if ($r !== $room && $e && $e['v'] !== '' && $e['c'] !== $cid) {
            $used[$e['v']] = true; // ห้อง/สถานที่ถูกห้องอื่นใช้ในคาบนี้ (วิชาเดียวกัน = เรียนรวม ใช้ร่วมได้)
        }
    }
    foreach ($vs as $k => $n) {
        $busy = $k !== $cur && isset($used[(string) $k]);
        $o .= '<option value="'.h($k).'"'.($k === $cur ? ' selected' : '').($busy ? ' disabled' : '').'>'.h(mb_substr($n, 0, 24).($busy ? ' ✗ ไม่ว่าง' : '')).'</option>';
    }
    return '<select name="v['.$slot.']" class="tt-v">'.$o.'</select>';
}

/** บันทึกมุมมองรายห้อง: คืนรายการช่องที่ชน */
function tt_save_room(int $room): array
{
    $mine = tt_room_courses($room);
    $venues = tt_venues();
    $cur = tt_all()[$room] ?? [];
    $bad = [];
    foreach (tt_days() as $d => $x) {
        foreach (tt_periods() as $p => $y) {
            $slot = $d.'-'.$p;
            $cid = (int) ($_POST['c'][$slot] ?? 0);
            $v = (string) ($_POST['v'][$slot] ?? '');
            $v = isset($venues[$v]) ? $v : '';
            if ($cid && !isset($mine[$cid])) {
                continue;
            }
            $old = $cur[$slot] ?? null;
            if ($old && $old['c'] === $cid && $old['v'] === $v) {
                continue;
            }
            if ($cid) {
                $why = tt_conflict($room, $slot, $cid, $v);
                if ($why !== '') {
                    $bad[] = tt_slot_text($slot).': '.$why;
                    continue;
                }
            }
            tt_put($room, $slot, $cid, $cid ? $v : '');
        }
    }
    return $bad;
}

/** บันทึกมุมมองรายครู */
function tt_save_teacher(int $tid): array
{
    $pairs = tt_teacher_pairs($tid);
    $venues = tt_venues();
    $grid = tt_teacher_grid($tid);
    $all = tt_all();
    $cs = tt_courses();
    $bad = [];
    foreach (tt_days() as $d => $x) {
        foreach (tt_periods() as $p => $y) {
            $slot = $d.'-'.$p;
            $k = (string) ($_POST['c'][$slot] ?? '');
            $v = (string) ($_POST['v'][$slot] ?? '');
            $v = isset($venues[$v]) ? $v : '';
            if ($k !== '' && !isset($pairs[$k])) {
                continue;
            }
            $curs = $grid[$slot] ?? [];
            $curK = $curs ? $curs[0][1].'|'.$curs[0][0] : '';
            if ($k === $curK) {
                if ($curs && $curs[0][2] !== $v) { // เปลี่ยนเฉพาะสถานที่
                    $why = $v !== '' ? tt_conflict($curs[0][0], $slot, $curs[0][1], $v) : '';
                    if ($why === '') {
                        tt_put($curs[0][0], $slot, $curs[0][1], $v);
                    } else {
                        $bad[] = tt_slot_text($slot).': '.$why;
                    }
                }
                continue;
            }
            if ($k !== '') {
                [$cid, $r] = $pairs[$k];
                $occ = $all[$r][$slot] ?? null;
                if ($occ && (int) ($cs[$occ['c']]['teacher_id'] ?? 0) !== $tid) {
                    $bad[] = tt_slot_text($slot).': ห้อง '.room_short($r).' มีวิชา '.($cs[$occ['c']]['course_code'] ?? '').' อยู่แล้ว';
                    continue;
                }
                if ($v !== '' && ($why = tt_conflict($r, $slot, $cid, $v)) !== '' && str_contains($why, 'ถูกใช้')) {
                    $bad[] = tt_slot_text($slot).': '.$why;
                    continue;
                }
            }
            foreach ($curs as [$r0]) {
                tt_put($r0, $slot, 0, '');
            }
            if ($k !== '') {
                tt_put($r, $slot, $cid, $v);
            }
        }
    }
    return $bad;
}

function tt_css(): string
{
    return '<style>.tt-box{width:983px;margin:0 auto;text-align:left}.tt-top{background:#fff;border:1px solid #ddd;border-radius:4px;padding:8px 10px;margin-bottom:8px}'
        .'.tt-tab{display:inline-block;padding:5px 12px;border-radius:4px;border:1px solid #9CD3F2;background:#F2FAFF;margin-right:4px;font-size:14px}.tt-tab.on{background:#2E86C1;color:#fff}'
        .'.tt-nav{display:inline-block;width:26px;text-align:center;font-size:20px;line-height:26px;margin:0 4px;border:1px solid #ccc;border-radius:4px;background:#fafafa;color:#333;text-decoration:none}.tt-nav.off{color:#ccc}'
        .'.tt-btn{display:inline-block;padding:5px 10px;border-radius:4px;background:#EAF2F8;color:#1F618D;font-size:13px;margin-left:4px}'
        .'.tt-wrap{overflow-x:auto;background:#fff;border:1px solid #ddd;border-radius:4px}.tt-g{border-collapse:collapse;width:100%;table-layout:fixed}'
        .'.tt-g th{background:#5b9aa0;color:#fff;font-size:12px;padding:4px 2px;border:1px solid #ccc;font-weight:bold;line-height:1.2}.tt-g .tm{font-weight:normal;font-size:11px}'
        .'.tt-g th.dh{width:70px;color:#333;font-size:14px}.tt-c{border:1px solid #ddd;padding:3px;vertical-align:top;height:58px}.tt-s,.tt-v{width:100%;font-size:11px;height:22px;margin-bottom:2px;background:#fff;border:1px solid #bbb;border-radius:3px}'
        .'.tt-t{font-size:10px;color:#1F618D;line-height:1.2;min-height:0;margin:-1px 0 2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.tt-v{color:#555;height:20px;font-size:10px}.tt-note{font-size:10px;color:#1F618D}'
        .'.tt-bar{text-align:right;padding:8px 0}.tt-sum{background:#fff;border:1px solid #ddd;border-radius:4px;margin-top:6px}.tt-sh{background:#EAF2F8;padding:6px 8px;font-weight:bold}'
        .'.tt-sum table{width:100%;border-collapse:collapse;font-size:13px}.tt-sum th{background:#f4f6f7;font-size:12px;padding:4px;border-bottom:1px solid #ddd}.tt-sum td{padding:4px 6px;border-bottom:1px solid #eee}.tt-sum .c{text-align:center}'
        .'.tt-sum tr.bad .cnt{color:#c0392b;font-weight:bold}.tt-sum tr.ok .cnt{color:#1e8449;font-weight:bold}.dot{display:inline-block;width:10px;height:10px;border-radius:2px;margin-right:6px;border:1px solid #bbb;vertical-align:-1px}'
        .'.tt-warn{width:983px;box-sizing:border-box;margin:0 auto 8px;text-align:left;background:#fdecea;border:1px solid #f5b7b1;color:#922b21;border-radius:4px;padding:8px 12px;font-size:13px}.tt-warn ul{margin:4px 0 0 18px;padding:0}</style>';
}

/** สีช่อง + นับคาบที่จัดแล้วแบบทันที */
function tt_js(): string
{
    return '<script>(function(){var f=document.getElementById("ttf");if(!f)return;function upd(){var n={};f.querySelectorAll(".tt-s").forEach(function(s){var o=s.options[s.selectedIndex];s.parentNode.style.background=s.value?(o.getAttribute("data-col")||""):"";var tl=s.parentNode.querySelector(".tt-t");if(tl)tl.textContent=s.value?(o.getAttribute("data-t")||""):"";if(s.value)n[s.value]=(n[s.value]||0)+1;});'
        .'document.querySelectorAll(".tt-sum tr[data-c]").forEach(function(r){var c=n[r.getAttribute("data-c")]||0,need=+r.querySelector(".need").textContent;r.querySelector(".cnt").textContent=c;r.className=c===need?"ok":"bad";});}'
        .'f.addEventListener("change",upd);upd();var dirty=false;f.addEventListener("change",function(){dirty=true;});f.addEventListener("submit",function(){dirty=false;});window.addEventListener("beforeunload",function(e){if(dirty){e.preventDefault();e.returnValue="";}});'
        .'document.querySelectorAll("#ttf a#pinkgreenbtns").forEach(function(a){a.addEventListener("click",function(){dirty=false;});});})();</script>';
}

/* ================================================================ พิมพ์ (A4 แนวนอน) */

/** ข้อความในช่องตาราง */
function tt_print_cell(array $lines): string
{
    return implode('', array_map(fn($l) => '<div class="'.$l[0].'">'.$l[1].'</div>', $lines));
}

/** หน้าพิมพ์ตาราง 1 หน้า */
function tt_print_page(string $title, string $sub, callable $cell, string $foot): string
{
    // หัวตาราง 2 แถว: เวลา (เทาเข้ม) / คาบที่ (เทาอ่อน)
    $h1 = '<tr><th class="dh hd" rowspan="2">วัน/เวลา</th>';
    $h2 = '<tr>';
    foreach (tt_periods() as $p => [$s, $e, $note]) {
        $h1 .= '<th class="ht">'.($s !== '' ? h(str_replace(':', '.', $s)).'-'.h(str_replace(':', '.', $e)) : '&nbsp;').'</th>';
        $h2 .= '<th class="hp">คาบที่ '.$p.'</th>';
    }
    $head = $h1.'</tr>'.$h2;
    $rows = '';
    foreach (tt_days() as $d => $dn) {
        $bg = tt_tint($d);
        $rows .= '<tr><th class="dh dy" style="background:'.(TT_DAY_COLOR[$d] ?? '#ddd').'">'.h($dn).'</th>';
        foreach (tt_periods() as $p => $x) {
            $rows .= '<td style="background:'.$bg.'">'.$cell($d.'-'.$p).'</td>';
        }
        $rows .= '</tr>';
    }
    [$y, $t] = term();
    return '<section class="pg"><div class="ph"><img src="'.h(rpt_logo()).'" alt=""/><div><div class="t1">'.h($title).'</div><div class="t2">'.h($sub !== '' ? $sub.' · ' : '').'ภาคเรียนที่ '.$t.' ปีการศึกษา '.$y.' · '.h((string) cfg('school_name', '')).'</div></div></div>'
        .'<table class="g">'.$head.'</tr>'.$rows.'</table>'.$foot.'</section>';
}

/** ตารางสรุปรายวิชาท้ายหน้า */
function tt_print_summary(array $rows, string $colLabel): string
{
    if (!$rows) {
        return '';
    }
    $h = '';
    $tot = 0;
    foreach ($rows as [$code, $name, $who, $n]) {
        $tot += $n;
        $h .= '<tr><td>'.h($code).'</td><td>'.h($name).'</td><td>'.h($who).'</td><td class="c">'.$n.'</td></tr>';
    }
    return '<table class="sm"><tr><th>รหัสวิชา</th><th>รายวิชา</th><th>'.$colLabel.'</th><th>คาบ/สัปดาห์</th></tr>'.$h.'<tr><td colspan="3" class="r"><b>รวม</b></td><td class="c"><b>'.$tot.'</b></td></tr></table>';
}

/** สร้างหน้าพิมพ์: kind = room | teacher | venue, ids = รายการ */
function tt_print_out(string $kind, array $ids): void
{
    $cs = tt_courses();
    $all = tt_all();
    $vs = tt_venues();
    $pages = '';
    $adv = advisors();
    foreach ($ids as $id) {
        if ($kind === 'room') {
            $id = (int) $id;
            $slots = $all[$id] ?? [];
            $cnt = [];
            foreach ($slots as $e) {
                $cnt[$e['c']] = ($cnt[$e['c']] ?? 0) + 1;
            }
            $sum = [];
            foreach ($cnt as $cid => $n) {
                $c = $cs[$cid] ?? [];
                $sum[] = [$c['course_code'] ?? '', $c['course_name'] ?? '', $c['teacher'] ?? '', $n];
            }
            usort($sum, fn($a, $b) => strcmp($a[0], $b[0]));
            $pages .= tt_print_page('ตารางเรียน ชั้น '.(room_options()[$id] ?? room_short($id)),
                ($adv[$id] ?? []) ? 'ครูที่ปรึกษา '.implode(', ', $adv[$id]) : '',
                function ($slot) use ($slots, $cs, $vs) {
                    $e = $slots[$slot] ?? null;
                    if (!$e) {
                        return '';
                    }
                    $c = $cs[$e['c']] ?? [];
                    return tt_print_cell([['cd', h($c['course_code'] ?? '')], ['nm', h($c['course_name'] ?? '')], ['tc', h(tt_teacher_label((string) ($c['teacher'] ?? '')))], ['vn', h($vs[$e['v']] ?? '')]]);
                }, tt_print_summary($sum, 'ครูผู้สอน'));
        } elseif ($kind === 'teacher') {
            $id = (int) $id;
            $grid = tt_teacher_grid($id);
            $cnt = [];
            foreach ($grid as $es) {
                foreach ($es as [$r, $cid]) {
                    $cnt[$cid.'|'.$r] = ($cnt[$cid.'|'.$r] ?? 0) + 1;
                }
            }
            $sum = [];
            foreach ($cnt as $k => $n) {
                [$cid, $r] = explode('|', $k);
                $c = $cs[(int) $cid] ?? [];
                $sum[] = [$c['course_code'] ?? '', $c['course_name'] ?? '', class_room_short($c['class'] ?? 0, (int) $r), $n];
            }
            usort($sum, fn($a, $b) => strcmp($a[0].$a[2], $b[0].$b[2]));
            $name = (string) (personnel_options()[$id] ?? q1('SELECT `name` FROM {user} WHERE `id`=?', [$id])['name'] ?? '');
            $pages .= tt_print_page('ตารางสอน '.$name, '', function ($slot) use ($grid, $cs, $vs) {
                $es = $grid[$slot] ?? [];
                if (!$es) {
                    return '';
                }
                $c = $cs[$es[0][1]] ?? [];
                return tt_print_cell([['cd', h($c['course_code'] ?? '')], ['nm', h($c['course_name'] ?? '')],
                    ['tc', h(implode(', ', array_map(fn($x) => class_room_short($c['class'] ?? 0, $x[0]), $es)))], ['vn', h($vs[$es[0][2]] ?? '')]]);
            }, tt_print_summary($sum, 'ชั้น/ห้อง'));
        } else {
            $id = (string) $id;
            if (!isset($vs[$id])) {
                continue;
            }
            $grid = tt_venue_grid($id);
            $pages .= tt_print_page('ตารางการใช้ '.$vs[$id], '', function ($slot) use ($grid, $cs) {
                $es = $grid[$slot] ?? [];
                if (!$es) {
                    return '';
                }
                $c = $cs[$es[0][1]] ?? [];
                return tt_print_cell([['cd', h($c['course_code'] ?? '')], ['nm', h($c['course_name'] ?? '')], ['tc', h(implode(', ', array_map(fn($x) => room_short($x[0]), $es)))],
                    ['vn', h(tt_teacher_label((string) ($c['teacher'] ?? '')))]]);
            }, '');
        }
    }
    if ($pages === '') {
        $pages = '<section class="pg"><p>ไม่มีข้อมูลตาราง</p></section>';
    }
    mis_headers();
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><title>พิมพ์ตาราง</title><link rel="shortcut icon" href="/mis/images/favicon.ico"><link rel="stylesheet" href="/mis/css/sarabun.css"><style>'
        .'@page{size:A4 landscape;margin:8mm 9mm}*{box-sizing:border-box}body{margin:0;background:#808080;font-family:"TH Sarabun New",sans-serif;color:#000}'
        .'.pg{width:297mm;min-height:210mm;margin:10px auto;background:#fff;padding:8mm 9mm;box-shadow:0 2px 10px rgba(0,0,0,.35);page-break-after:always;break-after:page}.pg:last-child{page-break-after:auto;break-after:auto}'
        .'.ph{display:flex;align-items:center;gap:10px;margin-bottom:6px}.ph img{height:16mm}.t1{font-size:22pt;font-weight:bold;line-height:1.1}.t2{font-size:14pt}'
        .'table.g{width:100%;border-collapse:collapse;table-layout:fixed}table.g th,table.g td{border:1px solid #555;text-align:center;vertical-align:middle}'
        .'table.g th{font-size:13pt;line-height:1.1;padding:2px}table.g th.ht{background:#9E9E9E;color:#fff;font-weight:bold}table.g th.hp{background:#DCDCDC;color:#222;font-weight:normal}'
        .'table.g th.dh{width:24mm}table.g th.hd{background:#9E9E9E;color:#fff;font-size:17pt}table.g th.dy{color:#fff;font-size:19pt;text-shadow:0 0 1px rgba(0,0,0,.25)}'
        .'table.g td{height:24mm;padding:1px 2px;line-height:1.1}.cd{font-weight:bold;font-size:14pt;color:#22B38C}.nm{font-size:13pt;color:#C36BE0}.tc{font-size:13pt;color:#E07B7B}.vn{font-size:11pt;font-style:italic;color:#777}'
        .'table.sm{border-collapse:collapse;margin-top:6px;font-size:13pt;min-width:60%}table.sm th,table.sm td{border:1px solid #888;padding:0 6px;line-height:1.15}table.sm th{background:#f2f3f4}.c{text-align:center}.r{text-align:right}'
        .'.bar{position:sticky;top:0;background:#333;color:#fff;padding:8px;text-align:center;z-index:9;font:13px Tahoma}.bar button{font:14px Tahoma;padding:4px 14px;margin:0 4px}'
        .'@media print{body{background:#fff}.pg{width:auto;min-height:0;margin:0;padding:0;box-shadow:none}.bar{display:none}}'
        .'</style></head><body><div class="bar"><button onclick="window.print()">🖨 พิมพ์ / บันทึกเป็น PDF</button><button onclick="history.length>1?history.back():window.close()">กลับ</button>'
        .' <span style="font-size:12px">กระดาษ A4 แนวนอน · ระยะขอบ: ค่าเริ่มต้น · เปิด "กราฟิกพื้นหลัง" (สีช่องวัน)</span></div>'.$pages.'</body></html>';
    exit;
}

/** ชื่อแบบย่อสำหรับช่องตาราง (ตัดคำนำหน้ายาว) */
function name_short(string $n): string
{
    return trim(preg_replace('/^(นางสาว|นาง|นาย|ว่าที่ร้อยตรี|ว่าที่ ร\.ต\.)\s*/u', '', $n)) ?: $n;
}

/** ครูพิมพ์ตาราง (อ่านอย่างเดียว): ?module=task&option=teacher_timetable&func=Print&kind=room|teacher|venue&ids=1,2 */
function act_task_teacher_timetable_Print(): void
{
    $kind = in_array($_GET['kind'] ?? '', ['room', 'teacher', 'venue'], true) ? (string) $_GET['kind'] : 'teacher';
    $raw = (string) ($_GET['ids'] ?? '');
    if ($raw === 'all') {
        $ids = match ($kind) {
            'room' => array_keys(room_options()),
            'teacher' => array_keys(personnel_options()),
            default => array_keys(tt_venues()),
        };
    } elseif (str_starts_with($raw, 'class:')) {
        $ids = array_keys(rooms_by_class()[(int) substr($raw, 6)] ?? []);
    } else {
        $ids = array_values(array_filter(array_map('trim', explode(',', $raw)), 'strlen'));
    }
    if ($kind === 'teacher' && !$ids) {
        $ids = [(int) me()['id']];
    }
    tt_print_out($kind, array_slice($ids, 0, 300));
}

/* ================================================================ เมนูพิมพ์ในโมดูลวิชาการ */

function adm_tt_print(bool $post)
{
    $rows = '';
    $grid = [];
    $cs = tt_courses();
    foreach (tt_all() as $slots) {
        foreach ($slots as $e) {
            $t = (int) ($cs[$e['c']]['teacher_id'] ?? 0);
            $grid[$t] = ($grid[$t] ?? 0) + 1;
        }
    }
    $base = '?module=task&option=teacher_timetable&func=Print&kind=teacher&ids=';
    foreach (personnel_rows() as $i => $p) {
        $rows .= zrow([$i + 1, name_nbsp($p['name']), h(cat('department', $p['department'])), [(string) ($grid[(int) $p['id']] ?? 0), ' align="center"'],
            ['<a target="_blank" href="'.h($base.(int) $p['id']).'">พิมพ์</a>'.(tt_can_edit() ? ' | <a href="'.h(a_url('timetable', '&view=teacher&id='.(int) $p['id'])).'">จัดตาราง</a>' : ''), ' align="center"']], $i);
    }
    return ['พิมพ์ตารางสอน', list_table('พิมพ์ตารางสอนรายบุคคล ภาคเรียน '.yt(), ['#', 'ชื่อ-นามสกุล', 'กลุ่มสาระ', 'คาบ/สัปดาห์', ''], $rows, '',
        '<a target="_blank" href="'.h($base.'all').'" id="pinkgreenbtns"><img src="../icon_tools/vec_print_white.png" border="0" height="16"/> พิมพ์ตารางสอนทุกคน</a>')];
}

function adm_room_tt(bool $post)
{
    $base = '?module=task&option=teacher_timetable&func=Print&kind=';
    $all = tt_all();
    $rows = '';
    $i = 0;
    foreach (rooms_by_class() as $cid => $rooms) {
        foreach ($rooms as $rid => $n) {
            $rows .= zrow([++$i, h(room_options()[$rid] ?? $n), [(string) count($all[$rid] ?? []), ' align="center"'],
                ['<a target="_blank" href="'.h($base.'room&ids='.(int) $rid).'">พิมพ์ตารางเรียน</a>'.(tt_can_edit() ? ' | <a href="'.h(a_url('timetable', '&view=room&id='.(int) $rid)).'">จัดตาราง</a>' : ''), ' align="center"']], $i);
        }
    }
    $cls = '';
    foreach (rooms_by_class() as $cid => $rooms) {
        if ($cid && $rooms) {
            $cls .= '<a target="_blank" href="'.h($base.'room&ids=class:'.(int) $cid).'" class="tt-btn">ทั้งชั้น '.h(cat('class', $cid)).'</a> ';
        }
    }
    $vrows = '';
    $j = 0;
    foreach (tt_venues() as $vid => $vn) {
        $vrows .= zrow([++$j, h($vn), [(string) array_sum(array_map('count', tt_venue_grid((string) $vid))), ' align="center"'], ['<a target="_blank" href="'.h($base.'venue&ids='.rawurlencode((string) $vid)).'">พิมพ์ตารางการใช้</a>', ' align="center"']], $j);
    }
    return ['พิมพ์ตารางเรียน / ตารางการใช้ห้อง', tt_css().'<div class="tt-box" style="margin-bottom:8px">พิมพ์ทีละหลายห้อง: <a target="_blank" href="'.h($base.'room&ids=all').'" class="tt-btn">ทุกห้อง</a> '.$cls.'</div>'
        .list_table('ตารางเรียนรายห้อง ภาคเรียน '.yt(), ['#', 'ห้องเรียน', 'คาบที่จัดแล้ว', ''], $rows)
        .($vrows !== '' ? '<br>'.list_table('ตารางการใช้ห้อง/สถานที่', ['#', 'ห้อง/สถานที่', 'คาบที่ใช้', ''], $vrows, '', '<a target="_blank" href="'.h($base.'venue&ids=all').'" id="pinkgreenbtns">พิมพ์ทุกห้อง/สถานที่</a>') : '')];
}
