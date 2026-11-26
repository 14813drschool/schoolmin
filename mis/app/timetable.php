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
const TT_FREE_COLOR = '#FFF6D5';
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
        $no = (int) preg_replace('/\D+/', '', tt_ar((string) ($d['no'] ?? '')));   // รับเลขไทย/ข้อความ เช่น "คาบที่ ๓"
        if (($d['type'] ?? '') !== 'break' && $no >= 1 && $no <= 15) {
            $p[$no] = [tt_hm((string) ($d['start'] ?? '')), tt_hm((string) ($d['end'] ?? '')), (string) ($d['note'] ?? '')];
        }
    }
    ksort($p);
    // ตั้งค่าไว้ไม่ครบ (เช่น เพิ่มไว้แค่คาบที่ ๕) — เติมคาบที่ขาดถึงคาบสุดท้ายที่มีวิชาจัดไว้ ไม่ให้ตารางที่จัดแล้วหายไป
    $GLOBALS['tt_periods_filled'] = false;
    if ($p && array_keys($p) !== range(1, count($p))) {
        $n = max(max(array_keys($p)), tt_max_used_period());
        $mm = fn($t) => preg_match('/^(\d{2}):(\d{2})$/', $t, $m) ? (int) $m[1] * 60 + (int) $m[2] : null;
        $hm = fn($x) => sprintf('%02d:%02d', intdiv(max(0, $x), 60) % 24, max(0, $x) % 60);
        $t = [];
        foreach ($p as $no => [$a, $b]) {
            if ($mm($a) !== null && $mm($b) !== null) {
                $t[$no] = [$mm($a), $mm($b)];
            }
        }
        for ($i = $n; $i >= 1; $i--) {
            if (!isset($t[$i]) && isset($t[$i + 1]) && !isset($p[$i])) {
                $t[$i] = [$t[$i + 1][0] - 50, $t[$i + 1][0]];
            }
        }
        for ($i = 1; $i <= $n; $i++) {
            if (!isset($t[$i])) {
                $st = isset($t[$i - 1]) ? $t[$i - 1][1] : 8 * 60 + 30;
                $t[$i] = [$st, $st + 50];
            }
            if (!isset($p[$i])) {
                $p[$i] = [$hm($t[$i][0]), $hm($t[$i][1]), ''];
            }
        }
        ksort($p);
        $GLOBALS['tt_periods_filled'] = true;
    }
    if (!$p) {
        foreach (['08:30', '09:20', '10:10', '11:00', '12:50', '13:40', '14:30', '15:20'] as $i => $s) {
            $p[$i + 1] = [$s, date('H:i', strtotime($s) + 50 * 60), ''];
        }
    }
    return $p;
}

/** เลขไทย → อารบิก */
function tt_ar(string $s): string
{
    return strtr($s, ['๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4', '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9']);
}

/** เวลา "8.30" / "๐๘:๓๐" / "08:30:00" → "08:30" (รูปแบบอื่นคืนตามเดิม) */
function tt_hm(string $s): string
{
    $s = trim(tt_ar($s));
    return preg_match('/^([01]?\d|2[0-3])\s*[:.]\s*([0-5]\d)/', $s, $m) ? sprintf('%02d:%02d', $m[1], $m[2]) : $s;
}

/** ช่วงพัก [หลังคาบที่ => [ชื่อ, เริ่ม, สิ้นสุด]] — ยังไม่เคยตั้งค่า: เว้นช่วงระหว่างคาบตั้งแต่ 40 นาที = พักกลางวัน */
function tt_breaks(): array
{
    static $b = null;
    if ($b !== null) {
        return $b;
    }
    $b = [];
    $pers = tt_periods();
    $max = $pers ? max(array_keys($pers)) : 0;
    foreach (md_rows('periods') as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        $after = (int) ($d['after'] ?? 0);
        if (($d['type'] ?? '') === 'break' && $after >= 1 && $after < $max) {
            $b[$after] = [(string) ($d['name'] ?? '') ?: 'พักกลางวัน', (string) ($d['start'] ?? ''), (string) ($d['end'] ?? '')];
        }
    }
    if (!$b && md_get('cfg', 'tt_breaks_set') === null) {
        $prev = null;
        foreach ($pers as $no => [$s, $e]) {
            if ($prev !== null && $prev[1] !== '' && $s !== '' && strtotime($s) - strtotime($prev[1]) >= 40 * 60) {
                $b[$prev[0]] = ['พักกลางวัน', $prev[1], $s];
            }
            $prev = [$no, $e];
        }
    }
    ksort($b);
    return $b;
}

/** คอลัมน์ตามลำดับเวลา: ['p', คาบที่] หรือ ['b', หลังคาบที่] */
function tt_cols(): array
{
    $o = [];
    $br = tt_breaks();
    foreach (tt_periods() as $p => $x) {
        $o[] = ['p', $p];
        if (isset($br[$p])) {
            $o[] = ['b', $p];
        }
    }
    return $o;
}

/** ช่องพัก (แนวตั้ง รวมทุกวัน) */
function tt_break_td(int $after, int $rows, string $style = ''): string
{
    [$n] = tt_breaks()[$after];
    return '<td class="brk" rowspan="'.$rows.'" style="background:#EEEEEE;text-align:center;vertical-align:middle;padding:0'.($style !== '' ? ';'.$style : '').'"><div style="writing-mode:vertical-rl;margin:0 auto;color:#555;font-weight:bold">'.h($n).'</div></td>';
}

function tt_time_txt(string $s, string $e): string
{
    return $s !== '' ? str_replace(':', '.', $s).'-'.str_replace(':', '.', $e) : '';
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
            $x = tt_x_clean($d['x'] ?? null);
            if ((int) ($d['c'] ?? 0) > 0 || $x) {
                $o[(int) $m[1]][(string) $r['r3']] = ['c' => $x ? 0 : (int) $d['c'], 'v' => (string) ($d['v'] ?? ''), 'x' => $x];
            }
        } else {
            $legacy[] = $r;
        }
    }
    foreach ($legacy as $r) {
        [$c, $room] = array_pad(explode('|', (string) $r['value']), 2, 0);
        $slot = (string) $r['r3'];
        if ((int) $c > 0 && (int) $room > 0 && !isset($o[(int) $room][$slot])) {
            $o[(int) $room][$slot] = ['c' => (int) $c, 'v' => '', 'x' => null];
            tt_put((int) $room, $slot, (int) $c, '', $yt);
        }
        md_set('tt', (string) $r['r1'], $yt, $slot, null);
    }
    return $GLOBALS['__tt_cache'][$yt] = $o;
}

function tt_put(int $room, string $slot, int $cid, string $venue, ?string $yt = null, ?array $x = null): void
{
    $yt = $yt ?? yt();
    $x = tt_x_clean($x);
    $on = $cid > 0 || $x;
    md_set('tt', 'R'.$room, $yt, $slot, $on ? json_encode($x ? ['c' => 0, 'v' => $venue, 'x' => $x] : ['c' => $cid, 'v' => $venue], JSON_UNESCAPED_UNICODE) : null);
    if (isset($GLOBALS['__tt_cache'][$yt])) { // ให้การตรวจชนในคำขอเดียวกันเห็นค่าล่าสุด
        if ($on) {
            $GLOBALS['__tt_cache'][$yt][$room][$slot] = ['c' => $x ? 0 : $cid, 'v' => $venue, 'x' => $x];
        } else {
            unset($GLOBALS['__tt_cache'][$yt][$room][$slot]);
        }
    }
}

/* ---------------------------------------------------------------- รายการพิมพ์เอง (ไม่ผูกรายวิชา) เช่น ชุมนุม แนะแนว ว่าง
 * x = ['n' => ชื่อวิชา/กิจกรรม, 'k' => รหัส, 't' => ครู (ข้อความ), 'ti' => รหัสบุคลากร ถ้าพิมพ์ตรงกับชื่อในระบบ] */

function tt_x_clean($x): ?array
{
    if (!is_array($x)) {
        return null;
    }
    $n = mb_substr(trim((string) ($x['n'] ?? '')), 0, 60);
    if ($n === '') {
        return null;
    }
    return ['n' => $n, 'k' => mb_substr(trim((string) ($x['k'] ?? '')), 0, 20), 't' => mb_substr(trim((string) ($x['t'] ?? '')), 0, 60), 'ti' => max(0, (int) ($x['ti'] ?? 0))];
}

/** รหัสบุคลากรจากชื่อที่พิมพ์ (ต้องตรงกับชื่อในระบบ เช่น เลือกจากรายการ) */
function tt_match_teacher(string $t): int
{
    $t = preg_replace('/\s+/u', ' ', trim($t));
    if ($t === '') {
        return 0;
    }
    foreach (personnel_options() as $id => $n) {
        $n = preg_replace('/\s+/u', ' ', trim((string) $n));
        if ($t === $n || $t === name_short($n)) {
            return (int) $id;
        }
    }
    return 0;
}

/** ข้อมูลแบบรายวิชาของช่อง (รายวิชาจริง หรือรายการพิมพ์เอง) */
function tt_course_of(?array $e, int $room = 0): ?array
{
    if (!$e) {
        return null;
    }
    if (!empty($e['x'])) {
        $x = $e['x'];
        return ['id' => 0, 'course_code' => $x['k'], 'course_name' => $x['n'], 'teacher' => $x['t'], 'teacher_id' => $x['ti'], 'class' => $room ? tt_room_class($room) : 0, 'free' => true];
    }
    return tt_courses()[$e['c']] ?? null;
}

/** ชื่อครูสำหรับแสดงในช่อง: รายวิชาจริง = ครู+ชื่อ, พิมพ์เอง = ตามที่พิมพ์ */
function tt_tlabel(?array $c): string
{
    if (!$c) {
        return '';
    }
    return !empty($c['free']) ? (string) $c['teacher'] : tt_teacher_label((string) ($c['teacher'] ?? ''));
}

/** คีย์เทียบ "วิชาเดียวกัน" (เรียนรวม) */
function tt_ekey(array $e): string
{
    return !empty($e['x']) ? 'x:'.$e['x']['n'].'|'.$e['x']['k'] : 'c:'.$e['c'];
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
function tt_teacher_grid(int $tid, bool $free = true): array
{
    $o = [];
    $cs = tt_courses();
    foreach (tt_all() as $r => $slots) {
        foreach ($slots as $slot => $e) {
            if ($tid <= 0) {
                continue;
            }
            if (!empty($e['x']) ? ($free && $e['x']['ti'] === $tid) : (int) ($cs[$e['c']]['teacher_id'] ?? 0) === $tid) {
                $o[$slot][] = [$r, $e['c'], $e['v'], $e];
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
                $o[$slot][] = [$r, $e['c'], $e];
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
        $ec = tt_course_of($e) ?? [];
        $same = empty($e['x']) && $e['c'] === $cid;
        if ($tid > 0 && (int) ($ec['teacher_id'] ?? 0) === $tid && !$same) {
            return tt_teacher_word((string) ($cs[$cid]['teacher'] ?? '')).' สอน '.(($ec['course_code'] ?? '') ?: ($ec['course_name'] ?? '')).' ห้อง '.room_short($r).' อยู่แล้ว';
        }
        if ($venue !== '' && $e['v'] === $venue && !$same) {
            return (tt_venues()[$venue] ?? 'สถานที่').' ถูกใช้โดย '.room_short($r).' ('.(($ec['course_code'] ?? '') ?: ($ec['course_name'] ?? '')).') แล้ว';
        }
    }
    return '';
}

/** ตรวจรายการพิมพ์เอง: ครูที่ผูกไว้ (ti) และห้อง/สถานที่ */
function tt_conflict_x(int $room, string $slot, array $x, string $venue): string
{
    foreach (tt_all() as $r => $slots) {
        if ($r === $room || !isset($slots[$slot])) {
            continue;
        }
        $e = $slots[$slot];
        $ec = tt_course_of($e) ?? [];
        $same = tt_ekey($e) === 'x:'.$x['n'].'|'.$x['k'];
        if ($x['ti'] > 0 && (int) ($ec['teacher_id'] ?? 0) === $x['ti'] && !$same) {
            return tt_teacher_word($x['t']).' มี '.(($ec['course_code'] ?? '') ?: ($ec['course_name'] ?? '')).' ห้อง '.room_short($r).' อยู่แล้ว';
        }
        if ($venue !== '' && $e['v'] === $venue && !$same) {
            return (tt_venues()[$venue] ?? 'สถานที่').' ถูกใช้โดย '.room_short($r).' แล้ว';
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
            $ec = tt_course_of($e) ?? [];
            $tid = (int) ($ec['teacher_id'] ?? 0);
            $key = tt_ekey($e);
            if ($tid > 0) {
                foreach ($seenT[$tid][$slot] ?? [] as [$r2, $c2]) {
                    if ($c2 !== $key) {
                        $o[] = [$slot, tt_teacher_word((string) ($ec['teacher'] ?? '')).' สอนซ้อน '.room_short($r2).' และ '.room_short($r), $r];
                    }
                }
                $seenT[$tid][$slot][] = [$r, $key];
            }
            if ($e['v'] !== '') {
                foreach ($seenV[$e['v']][$slot] ?? [] as [$r2, $c2]) {
                    if ($c2 !== $key) {
                        $o[] = [$slot, (tt_venues()[$e['v']] ?? 'สถานที่').' ใช้ซ้อน '.room_short($r2).' และ '.room_short($r), $r];
                    }
                }
                $seenV[$e['v']][$slot][] = [$r, $key];
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
    if ($post && $id && $view === 'room' && isset($_POST['tt_act'])) {
        $act = (string) $_POST['tt_act'];
        if ($act === 'undo') {
            $n = tt_room_undo($id);
            redirect(a_url('timetable', '&view=room&id='.$id.'&ttm='.rawurlencode($n >= 0 ? 'ย้อนกลับตารางของห้องนี้เป็นก่อนการเปลี่ยนแปลงครั้งล่าสุดแล้ว' : 'ไม่มีข้อมูลให้ย้อนกลับ')));
        }
        if ($act === 'copy') {
            [$y2, $r2] = [(string) ($_POST['src_yt'] ?? ''), (int) ($_POST['src_room'] ?? 0)];
            [$ok, $bad] = tt_copy_room($id, $y2, $r2);
            md_set('tt_msg', (int) me()['id'], '', '', $bad ? json_encode($bad, JSON_UNESCAPED_UNICODE) : null);
            redirect(a_url('timetable', '&view=room&id='.$id.'&ttm='.rawurlencode('คัดลอกตารางจาก '.room_short($r2).' ภาคเรียน '.$y2.' แล้ว '.$ok.' ช่อง'.($bad ? ' (ข้าม '.count($bad).' ช่องที่ชนกัน)' : ''))));
        }
        if ($act === 'import') {
            [$ok, $bad] = tt_import_room($id, (string) ($_POST['src_text'] ?? ''), !empty($_POST['src_clear']));
            md_set('tt_msg', (int) me()['id'], '', '', $bad ? json_encode($bad, JSON_UNESCAPED_UNICODE) : null);
            redirect(a_url('timetable', '&view=room&id='.$id.'&ttm='.rawurlencode('นำเข้าตารางแล้ว '.$ok.' ช่อง'.($bad ? ' (ข้าม/มีปัญหา '.count($bad).' รายการ)' : ''))));
        }
    }
    if ($post && $id && !isset($_POST['tt_act'])) {
        if ($view === 'room') {
            tt_room_backup($id);
        }
        $msgs = $view === 'room' ? tt_save_room($id) : tt_save_teacher($id);
        if (!$msgs && !isset($_GET['ajax'])) {
            redirect(a_url('timetable', '&view='.$view.'&id='.$id.'&msg=saved'));
        }
    }
    if (isset($_GET['clear']) && $view === 'room' && $id) {
        tt_room_backup($id);
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
        $free = [];
        foreach ($all[$id] ?? [] as $e) {
            if (!empty($e['x'])) {
                $k = tt_ekey($e);
                $free[$k] = [$e['x'], ($free[$k][1] ?? 0) + 1];
            }
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
                $cur = $all[$id][$slot] ?? ['c' => 0, 'v' => '', 'x' => null];
                $cx = $cur['x'] ?? null;
                $o = '<option value="">- ว่าง -</option>';
                foreach ($mine as $cid => $c) {
                    $sel = !$cx && $cid === $cur['c'];
                    $why = $sel ? '' : tt_conflict($id, $slot, $cid, '');
                    $o .= '<option value="'.$cid.'" data-col="'.tt_color($cid).'" data-t="'.h(name_short((string) ($c['teacher'] ?? ''))).'"'.($sel ? ' selected' : '').($why !== '' ? ' disabled' : '').'>'
                        .h($c['course_code'].' '.mb_substr((string) $c['course_name'], 0, 22).($why !== '' ? ' ✗ ครูไม่ว่าง' : '')).'</option>';
                }
                $o .= '<option value="x" data-col="'.TT_FREE_COLOR.'"'.($cx ? ' selected' : '').'>✎ พิมพ์เอง...</option>';
                $xin = '<div class="tt-x"'.($cx ? '' : ' style="display:none"').'><input name="xn['.$slot.']" value="'.h($cx['n'] ?? '').'" placeholder="ชื่อวิชา/กิจกรรม" maxlength="60"/>'
                    .'<input name="xk['.$slot.']" value="'.h($cx['k'] ?? '').'" placeholder="รหัส" maxlength="20" class="k"/><input name="xt['.$slot.']" value="'.h($cx['t'] ?? '').'" placeholder="ครู" maxlength="60" class="t" list="ttppl"/></div>';
                $cells[$slot] = [$o, $xin.tt_venue_select($slot, $cur['v'], $id, $cur['c']), $cx ? TT_FREE_COLOR : ($cur['c'] ? tt_color($cur['c']) : '')];
            }
        }
        foreach ($free as [$x, $n]) {
            $summary .= '<tr><td><span class="dot" style="background:'.TT_FREE_COLOR.'"></span>'.($x['k'] !== '' ? '<b>'.h($x['k']).'</b> ' : '').h($x['n']).' <span class="gray_12">(พิมพ์เอง)</span></td><td>'.h($x['t'] ?: '-').'</td><td class="c">-</td><td class="c">'.$n.'</td></tr>';
        }
        $sumHead = '<tr><th>รายวิชา</th><th>ครูผู้สอน</th><th>ควรจัด<br>(คาบ/สัปดาห์)</th><th>จัดแล้ว</th></tr>';
    } else {
        $pairs = tt_teacher_pairs($id);
        $grid = tt_teacher_grid($id, false);
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
                $cells[$slot] = [$o, tt_venue_select($slot, $curs[0][2] ?? '', $curs[0][0] ?? 0, $curs[0][1] ?? 0).$extra, $curs ? tt_color($curs[0][1]) : ''];
            }
        }
        $sumHead = '<tr><th>รายวิชา</th><th>ห้อง</th><th>ควรจัด<br>(คาบ/สัปดาห์)</th><th>จัดแล้ว</th></tr>';
    }

    // ตาราง
    $head = '<tr><th class="dh">วัน / คาบ</th>';
    foreach (tt_cols() as [$k, $p]) {
        $head .= $k === 'b' ? '<th style="width:28px;font-weight:normal;font-size:10px">พัก</th>' : '<th>'.tt_period_label($p).'</th>';
    }
    $rows = '';
    $first = true;
    foreach ($days as $d => $dn) {
        $rows .= '<tr><th class="dh" style="background:'.(TT_DAY_COLOR[$d] ?? '#ccc').'">'.h($dn).'</th>';
        foreach (tt_cols() as [$k, $p]) {
            if ($k === 'b') {
                $rows .= $first ? tt_break_td($p, count($days), 'font-size:12px') : '';
                continue;
            }
            $slot = $d.'-'.$p;
            [$o, $v, $col] = $cells[$slot];
            $rows .= '<td class="tt-c"'.($col !== '' ? ' style="background:'.$col.'"' : '').'><select name="c['.$slot.']" class="tt-s">'.$o.'</select><div class="tt-t"></div>'.$v.'</td>';
        }
        $rows .= '</tr>';
        $first = false;
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
    if (isset($_GET['ttm'])) {
        $bad = (array) (json_decode((string) md_get('tt_msg', (int) me()['id']), true) ?: []);
        md_set('tt_msg', (int) me()['id'], '', '', null);
        $err .= msg_box(h((string) $_GET['ttm'])).($bad ? '<div class="tt-warn"><b>รายการที่ข้าม:</b><ul><li>'.implode('</li><li>', array_map('h', array_slice($bad, 0, 40))).'</li></ul></div>' : '');
    }
    $dl = '<datalist id="ttppl">';
    foreach (personnel_options() as $n) {
        $dl .= '<option value="'.h((string) $n).'">';
    }
    $dl .= '</datalist>';
    $tools = $view === 'room' ? tt_room_tools($id) : '';
    $who = $view === 'room' ? 'ห้อง '.($rooms[$id] ?? '') : ($teachers[$id] ?? '');
    $form = '<form method="post" id="ttf" action="'.h(a_url('timetable', '&view='.$view.'&id='.$id)).'">'
        .'<div class="tt-wrap"><table class="tt-g">'.$head.'</tr>'.$rows.'</table></div>'
        .'<div class="tt-bar"><span class="gray_12">ช่องที่มี ✗ คือครูหรือห้องไม่ว่างในคาบนั้น (ระบบไม่ให้เลือก) — เลือกห้อง/สถานที่ได้ใต้ชื่อวิชา (เว้นว่าง = ห้องประจำชั้น)</span>'
        .($view === 'room' ? ' <a href="'.h(a_url('timetable', '&view=room&id='.$id.'&clear=1')).'" class="tt-btn" style="background:#fdecea;color:#c0392b" onclick="return confirm(\'ล้างตารางของห้องนี้ทั้งหมด?\')">ล้างตารางห้องนี้</a>' : '')
        .' <a href="javascript:void(0)" onclick="document.getElementById(\'ttf\').submit()" id="pinkgreenbtns"><img src="../icon_tools/vec_add_white.png" border="0" height="16"/> บันทึกตาราง</a></div></form>';
    $side = '<div class="tt-sum"><div class="tt-sh">รายวิชาของ'.h($who).' ภาคเรียน '.h(yt()).'</div><table>'.$sumHead.($summary ?: '<tr><td colspan="4" class="c gray_12">ยังไม่มีรายวิชา — เพิ่มรายวิชาและลงทะเบียนเรียนก่อน</td></tr>').'</table>'
        .'<div class="gray_12" style="padding:6px 8px">ควรจัด = ชั่วโมงต่อภาค ÷ 20 สัปดาห์ (หรือหน่วยกิต × 2) · สีแดง = ยังจัดไม่ครบ / เกิน</div></div>';
    return ['จัดตารางเรียน', tt_css().saved_msg().$confHtml.$err.'<div class="tt-box">'.$top.$tools.$form.$side.'</div>'.$dl.tt_js()];
}

/* ---------------------------------------------------------------- สำรอง / คัดลอก / นำเข้า (รายห้อง) */

/** สำรองตารางของห้องก่อนเปลี่ยนแปลง (ย้อนกลับได้ ๑ ครั้ง) */
function tt_room_backup(int $room): void
{
    $slots = tt_all()[$room] ?? [];
    md_set('tt_bak', 'R'.$room, yt(), '', json_encode(['at' => date('Y-m-d H:i'), 'slots' => $slots], JSON_UNESCAPED_UNICODE));
}

/** ย้อนกลับตารางของห้องเป็นชุดที่สำรองไว้ (คืนจำนวนช่อง, -1 = ไม่มีข้อมูล) */
function tt_room_undo(int $room): int
{
    $b = json_decode((string) md_get('tt_bak', 'R'.$room, yt()), true);
    if (!is_array($b) || !isset($b['slots'])) {
        return -1;
    }
    tt_room_backup($room);   // สลับกัน กดซ้ำเพื่อกลับมาได้
    foreach (array_keys(tt_all()[$room] ?? []) as $slot) {
        tt_put($room, (string) $slot, 0, '');
    }
    $n = 0;
    foreach ((array) $b['slots'] as $slot => $e) {
        tt_put($room, (string) $slot, (int) ($e['c'] ?? 0), (string) ($e['v'] ?? ''), null, $e['x'] ?? null);
        $n++;
    }
    return $n;
}

/** ภาคเรียนที่มีข้อมูลตาราง [yt => yt] (ใหม่ → เก่า) */
function tt_terms(): array
{
    $o = [yt() => yt()];
    foreach (q('SELECT DISTINCT `r2` FROM {mis_data} WHERE `kind`=?', ['tt']) as $r) {
        if (preg_match('#^\d{4}/\d$#', (string) $r['r2'])) {
            $o[(string) $r['r2']] = (string) $r['r2'];
        }
    }
    krsort($o);
    return $o;
}

/** แทนตารางของห้องด้วยรายการ [slot => ['c'=>, 'v'=>, 'x'=>]] (ตรวจชน) คืน [จำนวนที่ลง, รายการที่ข้าม] */
function tt_room_replace(int $room, array $items): array
{
    tt_room_backup($room);
    foreach (array_keys(tt_all()[$room] ?? []) as $slot) {
        tt_put($room, (string) $slot, 0, '');
    }
    $ok = 0;
    $bad = [];
    foreach ($items as $slot => $e) {
        $x = tt_x_clean($e['x'] ?? null);
        $v = (string) ($e['v'] ?? '');
        $why = $x ? tt_conflict_x($room, (string) $slot, $x, $v) : tt_conflict($room, (string) $slot, (int) $e['c'], $v);
        if ($why !== '' && $v !== '' && str_contains($why, 'ถูกใช้')) { // สถานที่ไม่ว่าง: ลงวิชาไว้ ใช้ห้องประจำชั้นแทน
            $v = '';
            $why = $x ? tt_conflict_x($room, (string) $slot, $x, '') : tt_conflict($room, (string) $slot, (int) $e['c'], '');
        }
        if ($why !== '') {
            $bad[] = tt_slot_text((string) $slot).': '.$why;
            continue;
        }
        tt_put($room, (string) $slot, $x ? 0 : (int) $e['c'], $v, null, $x);
        $ok++;
    }
    return [$ok, $bad];
}

/** แปลงรายวิชาของตารางต้นทางให้เป็นของห้อง/ภาคเรียนนี้: รายวิชาเดียวกัน → รหัสวิชาเดียวกัน → พิมพ์เอง */
function tt_map_course(int $room, array $src): array
{
    static $byCode = [];
    if (!isset($byCode[$room])) {
        $byCode[$room] = [];
        foreach (tt_room_courses($room) as $id => $c) {
            $byCode[$room][mb_strtolower(trim((string) $c['course_code']))] ??= (int) $id;
        }
    }
    $code = mb_strtolower(trim((string) ($src['course_code'] ?? '')));
    if (!empty($src['id']) && isset(tt_room_courses($room)[(int) $src['id']])) {
        return ['c' => (int) $src['id'], 'x' => null];
    }
    if ($code !== '' && isset($byCode[$room][$code])) {
        return ['c' => $byCode[$room][$code], 'x' => null];
    }
    return ['c' => 0, 'x' => ['n' => (string) ($src['course_name'] ?? ''), 'k' => (string) ($src['course_code'] ?? ''), 't' => (string) ($src['teacher'] ?? ''), 'ti' => (int) ($src['teacher_id'] ?? 0)]];
}

/** คัดลอกตารางจากห้อง/ภาคเรียนอื่น (แทนตารางเดิมของห้องนี้) */
function tt_copy_room(int $room, string $srcYt, int $srcRoom): array
{
    if (!isset(tt_terms()[$srcYt]) || !isset(room_options()[$srcRoom]) || ($srcYt === yt() && $srcRoom === $room)) {
        return [0, ['เลือกห้อง/ภาคเรียนต้นทางไม่ถูกต้อง']];
    }
    $src = tt_all($srcYt)[$srcRoom] ?? [];
    if (!$src) {
        return [0, ['ห้อง '.room_short($srcRoom).' ภาคเรียน '.$srcYt.' ยังไม่มีตาราง']];
    }
    $cids = array_filter(array_map(fn($e) => empty($e['x']) ? (int) $e['c'] : 0, $src));
    $cs = courses_by_id($cids);
    $items = [];
    foreach ($src as $slot => $e) {
        $m = !empty($e['x']) ? ['c' => 0, 'x' => $e['x']] : tt_map_course($room, $cs[(int) $e['c']] ?? []);
        if (!$m['c'] && !tt_x_clean($m['x'])) {
            continue;
        }
        $items[$slot] = $m + ['v' => (string) $e['v']];
    }
    return tt_room_replace($room, $items);
}

/**
 * นำเข้าจากข้อความ บรรทัดละช่อง: วัน คาบ | รหัสวิชา | ชื่อวิชา | ครู
 *   เช่น  จันทร์ 1 | ท31102 | ภาษาไทย | อ.อภินัทธ์   หรือ  จันทร์ 3-4 | ว31202 | ฟิสิกส์ | อ.กัมปนาถ
 * รหัสวิชาตรงกับรายวิชาของห้อง → ผูกรายวิชา (ครูตามรายวิชา) ไม่ตรง → พิมพ์เอง
 */
function tt_import_room(int $room, string $text, bool $clear): array
{
    $days = [];
    foreach (WEEKDAYS as $k => $n) {
        $days[$n] = $k;
    }
    $days += ['พฤหัส' => 4, 'จ' => 1, 'อ' => 2, 'พ' => 3, 'พฤ' => 4, 'ศ' => 5, 'ส' => 6, 'อา' => 0];
    $items = $clear ? [] : (tt_all()[$room] ?? []);
    $bad = [];
    $n = 0;
    foreach (preg_split('/\r?\n/', $text) as $i => $line) {
        $line = trim(tt_ar($line));
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $f = array_map('trim', explode('|', str_replace("\t", '|', $line)));
        if (!preg_match('/^(\S+)\s*(?:คาบ(?:ที่)?)?\s*(\d{1,2})\s*(?:-\s*(\d{1,2}))?$/u', $f[0], $m) || !isset($days[$m[1]])) {
            $bad[] = 'บรรทัด '.($i + 1).': อ่านวัน/คาบไม่ได้ "'.$f[0].'"';
            continue;
        }
        $d = $days[$m[1]];
        $p1 = (int) $m[2];
        $p2 = (int) ($m[3] ?? 0) ?: $p1;
        $code = (string) ($f[1] ?? '');
        $name = (string) ($f[2] ?? '');
        $t = (string) ($f[3] ?? '');
        if ($code === '' && $name === '') {
            for ($p = $p1; $p <= $p2; $p++) {   // ว่าง = ลบช่อง
                unset($items[$d.'-'.$p]);
            }
            continue;
        }
        $mm = tt_map_course($room, ['course_code' => $code, 'course_name' => $name !== '' ? $name : $code, 'teacher' => $t, 'teacher_id' => tt_match_teacher($t)]);
        for ($p = min($p1, $p2); $p <= max($p1, $p2) && $p <= 15; $p++) {
            $items[$d.'-'.$p] = $mm + ['v' => ''];
            $n++;
        }
    }
    if (!$n && !$clear) {
        return [0, $bad ?: ['ไม่พบรายการที่นำเข้าได้']];
    }
    [$ok, $b2] = tt_room_replace($room, $items);
    return [$ok, array_merge($bad, $b2)];
}

/** แถบเครื่องมือ: คัดลอกจากห้อง/ภาคอื่น, นำเข้าจากข้อความ, ย้อนกลับ */
function tt_room_tools(int $room): string
{
    $act = h(a_url('timetable', '&view=room&id='.$room));
    $yo = '';
    foreach (tt_terms() as $k) {
        $yo .= '<option value="'.h($k).'"'.($k === yt() ? ' selected' : '').'>'.h($k).($k === yt() ? ' (ภาคนี้)' : '').'</option>';
    }
    $ro = '';
    foreach (room_options() as $k => $n) {
        $ro .= '<option value="'.(int) $k.'"'.($k === $room ? ' selected' : '').'>'.h($n).'</option>';
    }
    $bak = json_decode((string) md_get('tt_bak', 'R'.$room, yt()), true);
    $undo = is_array($bak) ? '<form method="post" action="'.$act.'" style="display:inline" onsubmit="return confirm(\'ย้อนกลับตารางของห้องนี้เป็นก่อนการเปลี่ยนแปลงครั้งล่าสุด ('.h((string) ($bak['at'] ?? '')).')?\')"><input type="hidden" name="tt_act" value="undo"/><button type="submit">↶ ย้อนกลับการเปลี่ยนแปลงครั้งล่าสุด ('.h((string) ($bak['at'] ?? '')).')</button></form>' : '';
    return '<details class="tt-tools"><summary>📋 คัดลอกตารางจากห้อง/ปีการศึกษาอื่น · นำเข้าจากข้อความ'.($undo ? ' · ย้อนกลับ' : '').'</summary><div class="in">'
        .'<fieldset><legend>คัดลอกตาราง (แทนตารางเดิมของห้องนี้)</legend><form method="post" action="'.$act.'" onsubmit="return confirm(\'แทนตารางของห้องนี้ด้วยตารางที่เลือก? (ย้อนกลับได้)\')"><input type="hidden" name="tt_act" value="copy"/>'
        .'จากภาคเรียน <select name="src_yt">'.$yo.'</select> ห้อง <select name="src_room">'.$ro.'</select> <button type="submit">คัดลอกมาที่ห้องนี้</button>'
        .'<div class="gray_12" style="font-size:11px;margin-top:4px">วิชาที่ห้องนี้มีรายวิชารหัสเดียวกัน จะผูกกับรายวิชาของภาคนี้ให้ (นับคาบ/ตารางครูได้) — ที่ไม่มี จะลงเป็น "พิมพ์เอง" ตามชื่อ/ครูเดิม · ช่องที่ครูชนกับห้องอื่นจะข้ามและแจ้งให้ทราบ</div></form></fieldset>'
        .'<fieldset><legend>นำเข้าจากข้อความ</legend><form method="post" action="'.$act.'" onsubmit="return confirm(\'นำเข้าตารางให้ห้องนี้? (ย้อนกลับได้)\')"><input type="hidden" name="tt_act" value="import"/>'
        .'<textarea name="src_text" placeholder="บรรทัดละช่อง:  วัน คาบ | รหัสวิชา | ชื่อวิชา | ครู&#10;จันทร์ 1 | ท31102 | ภาษาไทย | อ.อภินัทธ์&#10;จันทร์ 3-4 | ว31202 | ฟิสิกส์ | อ.กัมปนาถ&#10;ศุกร์ 6 | | ว่าง | อ.กัมปนาถ"></textarea>'
        .'<label><input type="checkbox" name="src_clear" value="1" checked/> ล้างตารางเดิมของห้องนี้ก่อน</label> <button type="submit">นำเข้า</button>'
        .'<div class="gray_12" style="font-size:11px;margin-top:4px">วัน: จันทร์–อาทิตย์ · คาบ: เลขเดียว หรือช่วง เช่น 3-4 · รหัสวิชาตรงกับรายวิชาของห้อง = ผูกรายวิชา (ครูตามรายวิชา) ไม่ตรง = พิมพ์เอง · คั่นด้วย | หรือ Tab (วางจาก Excel ได้)</div></form></fieldset>'
        .($undo ? '<fieldset><legend>ย้อนกลับ</legend>'.$undo.'</fieldset>' : '')
        .'</div></details>';
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
        if ($r !== $room && $e && $e['v'] !== '' && ($e['c'] !== $cid || !empty($e['x']))) {
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
            $raw = (string) ($_POST['c'][$slot] ?? '');
            $v = (string) ($_POST['v'][$slot] ?? '');
            $v = isset($venues[$v]) ? $v : '';
            $old = $cur[$slot] ?? null;
            if ($raw === 'x') { // พิมพ์เอง
                $t = trim((string) ($_POST['xt'][$slot] ?? ''));
                $x = tt_x_clean(['n' => $_POST['xn'][$slot] ?? '', 'k' => $_POST['xk'][$slot] ?? '', 't' => $t, 'ti' => tt_match_teacher($t)]);
                if (!$x) {
                    if ($old) {
                        $bad[] = tt_slot_text($slot).': เลือก "พิมพ์เอง" แต่ไม่ได้กรอกชื่อวิชา/กิจกรรม (คงค่าเดิมไว้)';
                    }
                    continue;
                }
                if ($old && ($old['x'] ?? null) == $x && $old['v'] === $v) {
                    continue;
                }
                $why = tt_conflict_x($room, $slot, $x, $v);
                if ($why !== '') {
                    $bad[] = tt_slot_text($slot).': '.$why;
                    continue;
                }
                tt_put($room, $slot, 0, $v, null, $x);
                continue;
            }
            $cid = (int) $raw;
            if ($cid && !isset($mine[$cid])) {
                continue;
            }
            if ($old && empty($old['x']) && $old['c'] === $cid && $old['v'] === $v) {
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
    $grid = tt_teacher_grid($tid, false);
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
        .'.tt-x input{width:100%;box-sizing:border-box;font-size:11px;height:20px;margin-bottom:2px;border:1px solid #d4ac0d;border-radius:3px;padding:0 3px;background:#fff}.tt-x .k{width:38%}.tt-x .t{width:60%;margin-left:2%}'
        .'.tt-tools{background:#fff;border:1px solid #ddd;border-radius:4px;margin-bottom:8px;font-size:13px}.tt-tools summary{padding:7px 10px;cursor:pointer;color:#1F618D}.tt-tools .in{padding:4px 12px 10px}.tt-tools textarea{width:100%;box-sizing:border-box;height:150px;font-size:12px;font-family:monospace}.tt-tools fieldset{border:1px solid #e5e7e9;border-radius:4px;margin:6px 0;padding:6px 10px}'
        .'.tt-bar{text-align:right;padding:8px 0}.tt-sum{background:#fff;border:1px solid #ddd;border-radius:4px;margin-top:6px}.tt-sh{background:#EAF2F8;padding:6px 8px;font-weight:bold}'
        .'.tt-sum table{width:100%;border-collapse:collapse;font-size:13px}.tt-sum th{background:#f4f6f7;font-size:12px;padding:4px;border-bottom:1px solid #ddd}.tt-sum td{padding:4px 6px;border-bottom:1px solid #eee}.tt-sum .c{text-align:center}'
        .'.tt-sum tr.bad .cnt{color:#c0392b;font-weight:bold}.tt-sum tr.ok .cnt{color:#1e8449;font-weight:bold}.dot{display:inline-block;width:10px;height:10px;border-radius:2px;margin-right:6px;border:1px solid #bbb;vertical-align:-1px}'
        .'.tt-warn{width:983px;box-sizing:border-box;margin:0 auto 8px;text-align:left;background:#fdecea;border:1px solid #f5b7b1;color:#922b21;border-radius:4px;padding:8px 12px;font-size:13px}.tt-warn ul{margin:4px 0 0 18px;padding:0}</style>';
}

/** สีช่อง + นับคาบที่จัดแล้วแบบทันที */
function tt_js(): string
{
    return '<script>(function(){var f=document.getElementById("ttf");if(!f)return;function upd(){var n={};f.querySelectorAll(".tt-s").forEach(function(s){var o=s.options[s.selectedIndex];s.parentNode.style.background=s.value?(o.getAttribute("data-col")||""):"";var tl=s.parentNode.querySelector(".tt-t");if(tl)tl.textContent=s.value&&s.value!=="x"?(o.getAttribute("data-t")||""):"";var bx=s.parentNode.querySelector(".tt-x");if(bx)bx.style.display=s.value==="x"?"":"none";if(s.value&&s.value!=="x")n[s.value]=(n[s.value]||0)+1;});'
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
    $pers = tt_periods();
    $brs = tt_breaks();
    foreach (tt_cols() as [$k, $p]) {
        if ($k === 'b') {
            [$bn, $bs, $be] = $brs[$p];
            $h1 .= '<th class="ht hb" rowspan="2" title="'.h($bn).'">'.($bs !== '' ? h(str_replace(':', '.', $bs)).'<br>-<br>'.h(str_replace(':', '.', $be)) : 'พัก').'</th>';
            continue;
        }
        [$s, $e] = $pers[$p];
        $h1 .= '<th class="ht">'.($s !== '' ? h(tt_time_txt($s, $e)) : '&nbsp;').'</th>';
        $h2 .= '<th class="hp">คาบที่ '.$p.'</th>';
    }
    $head = $h1.'</tr>'.$h2;
    $rows = '';
    $first = true;
    $days = tt_days();
    foreach ($days as $d => $dn) {
        $bg = tt_tint($d);
        $rows .= '<tr><th class="dh dy" style="background:'.(TT_DAY_COLOR[$d] ?? '#ddd').'">'.h($dn).'</th>';
        foreach (tt_cols() as [$k, $p]) {
            if ($k === 'b') {
                $rows .= $first ? tt_break_td($p, count($days), 'font-size:15pt') : '';
                continue;
            }
            $rows .= '<td style="background:'.$bg.'">'.$cell($d.'-'.$p).'</td>';
        }
        $rows .= '</tr>';
        $first = false;
    }
    [$y, $t] = term();
    return '<section class="pg"><div class="ph"><img src="'.h(rpt_logo()).'" alt=""/><div><div class="t1">'.h($title).'</div><div class="t2">'.h($sub !== '' ? $sub.' · ' : '').'ภาคเรียนที่ '.$t.' ปีการศึกษา '.$y.' · '.h((string) cfg('school_name', '')).'</div></div></div>'
        .'<table class="g">'.$head.'</tr>'.$rows.'</table>'.$foot.'</section>';
}

/** ตารางสรุปรายวิชาท้ายหน้า */
function tt_print_summary(array $rows, string $colLabel): string
{
    if (!$rows) {
        return '<div class="smw"><p class="sme">ยังไม่มีรายวิชาในตารางนี้</p></div>';
    }
    $h = '';
    $tot = 0;
    foreach ($rows as [$code, $name, $who, $n]) {
        $tot += $n;
        $h .= '<tr><td>'.h($code).'</td><td>'.h($name).'</td><td>'.h($who).'</td><td class="c">'.$n.'</td></tr>';
    }
    return '<div class="smw"><div class="smt">ข้อมูลรายวิชา</div><table class="sm"><tr><th>รหัสวิชา</th><th>รายวิชา</th><th>'.$colLabel.'</th><th>คาบ/สัปดาห์</th></tr>'.$h.'<tr><td colspan="3" class="r"><b>รวม</b></td><td class="c"><b>'.$tot.'</b></td></tr></table></div>';
}

/** สร้างหน้าพิมพ์: kind = room | teacher | venue, ids = รายการ */
function tt_print_out(string $kind, array $ids): void
{
    $all = tt_all();
    $vs = tt_venues();
    $pages = '';
    $adv = advisors();
    // สรุปรายวิชา: นับตามคีย์ (รายวิชา/รายการพิมพ์เอง) + ห้อง
    $summ = function (array $list, callable $who) {
        $cnt = [];
        foreach ($list as [$e, $r]) {
            $k = tt_ekey($e).'#'.$r;
            $cnt[$k] = [$e, $r, ($cnt[$k][2] ?? 0) + 1];
        }
        $sum = [];
        foreach ($cnt as [$e, $r, $n]) {
            $c = tt_course_of($e, $r) ?? [];
            $sum[] = [$c['course_code'] ?? '', $c['course_name'] ?? '', $who($c, $r), $n];
        }
        usort($sum, fn($a, $b) => strcmp($a[0].$a[1].$a[2], $b[0].$b[1].$b[2]));
        return $sum;
    };
    foreach ($ids as $id) {
        if ($kind === 'room') {
            $id = (int) $id;
            $slots = $all[$id] ?? [];
            $sum = $summ(array_map(fn($e) => [$e, 0], array_values($slots)), fn($c, $r) => (string) (!empty($c['free']) ? $c['teacher'] : ($c['teacher'] ?? '')));
            $pages .= tt_print_page('ตารางเรียน ชั้น '.(room_options()[$id] ?? room_short($id)),
                ($adv[$id] ?? []) ? 'ครูที่ปรึกษา '.implode(', ', $adv[$id]) : '',
                function ($slot) use ($slots, $vs, $id) {
                    $e = $slots[$slot] ?? null;
                    if (!$e) {
                        return '';
                    }
                    $c = tt_course_of($e, $id) ?? [];
                    return tt_print_cell([['cd', h($c['course_code'] ?? '')], ['nm', h($c['course_name'] ?? '')], ['tc', h(tt_tlabel($c))], ['vn', h($vs[$e['v']] ?? '')]]);
                }, tt_print_summary($sum, 'ครูผู้สอน'));
        } elseif ($kind === 'teacher') {
            $id = (int) $id;
            $grid = tt_teacher_grid($id);
            $list = [];
            foreach ($grid as $es) {
                foreach ($es as $x) {
                    $list[] = [$x[3], $x[0]];
                }
            }
            $sum = $summ($list, fn($c, $r) => !empty($c['free']) ? (string) (room_options()[$r] ?? room_short($r)) : class_room_short($c['class'] ?? 0, (int) $r));
            $name = (string) (personnel_options()[$id] ?? q1('SELECT `name` FROM {user} WHERE `id`=?', [$id])['name'] ?? '');
            $pages .= tt_print_page('ตารางสอน '.$name, '', function ($slot) use ($grid, $vs) {
                $es = $grid[$slot] ?? [];
                if (!$es) {
                    return '';
                }
                $c = tt_course_of($es[0][3], $es[0][0]) ?? [];
                return tt_print_cell([['cd', h($c['course_code'] ?? '')], ['nm', h($c['course_name'] ?? '')],
                    ['tc', h(implode(', ', array_map(fn($x) => !empty($c['free']) ? (string) (room_options()[$x[0]] ?? room_short($x[0])) : class_room_short($c['class'] ?? 0, $x[0]), $es)))], ['vn', h($vs[$es[0][2]] ?? '')]]);
            }, tt_print_summary($sum, 'ชั้น/ห้อง'));
        } else {
            $id = (string) $id;
            if (!isset($vs[$id])) {
                continue;
            }
            $grid = tt_venue_grid($id);
            $list = [];
            foreach ($grid as $es) {
                foreach ($es as $x) {
                    $list[] = [$x[2], $x[0]];
                }
            }
            $sum = $summ($list, fn($c, $r) => room_short((int) $r).' · '.tt_tlabel($c));
            $pages .= tt_print_page('ตารางการใช้ '.$vs[$id], '', function ($slot) use ($grid) {
                $es = $grid[$slot] ?? [];
                if (!$es) {
                    return '';
                }
                $c = tt_course_of($es[0][2], $es[0][0]) ?? [];
                return tt_print_cell([['cd', h($c['course_code'] ?? '')], ['nm', h($c['course_name'] ?? '')], ['tc', h(implode(', ', array_map(fn($x) => room_short($x[0]), $es)))],
                    ['vn', h(tt_tlabel($c))]]);
            }, tt_print_summary($sum, 'ห้อง / ครูผู้สอน'));
        }
    }
    if ($pages === '') {
        $pages = '<section class="pg"><p>ไม่มีข้อมูลตาราง</p></section>';
    }
    // รูปแบบการพิมพ์: both = ตาราง + ข้อมูลรายวิชา, grid = เฉพาะตาราง, info = เฉพาะข้อมูลรายวิชา (?show=)
    $show = in_array($_GET['show'] ?? '', ['both', 'grid', 'info'], true) ? (string) $_GET['show'] : 'both';
    $opt = '';
    foreach (['both' => 'ตารางเรียน + ข้อมูลรายวิชา', 'grid' => 'เฉพาะตารางเรียน', 'info' => 'เฉพาะข้อมูลรายวิชา'] as $k => $lb) {
        $opt .= '<label><input type="radio" name="ttshow" value="'.$k.'"'.($k === $show ? ' checked' : '').'> '.$lb.'</label>';
    }
    mis_headers();
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><title>พิมพ์ตาราง</title><link rel="shortcut icon" href="/mis/images/favicon.ico"><link rel="stylesheet" href="/mis/css/sarabun.css"><style>'
        .'@page{size:A4 landscape;margin:8mm 9mm}*{box-sizing:border-box}body{margin:0;background:#808080;font-family:"TH Sarabun New",sans-serif;color:#000}'
        .'.pg{width:297mm;min-height:210mm;margin:10px auto;background:#fff;padding:8mm 9mm;box-shadow:0 2px 10px rgba(0,0,0,.35);page-break-after:always;break-after:page}.pg:last-child{page-break-after:auto;break-after:auto}'
        .'.ph{display:flex;align-items:center;gap:10px;margin-bottom:6px}.ph img{height:16mm}.t1{font-size:22pt;font-weight:bold;line-height:1.1}.t2{font-size:14pt}'
        .'table.g{width:100%;border-collapse:collapse;table-layout:fixed}table.g th,table.g td{border:1px solid #555;text-align:center;vertical-align:middle}'
        .'table.g th{font-size:13pt;line-height:1.1;padding:2px}table.g th.ht{background:#9E9E9E;color:#fff;font-weight:bold}table.g th.hp{background:#DCDCDC;color:#222;font-weight:normal}'
        .'table.g th.hb{width:12mm;font-size:11pt;line-height:1}table.g th.dh{width:24mm}table.g th.hd{background:#9E9E9E;color:#fff;font-size:17pt}table.g th.dy{color:#fff;font-size:19pt;text-shadow:0 0 1px rgba(0,0,0,.25)}'
        .'table.g td{height:24mm;padding:1px 2px;line-height:1.1}.cd{font-weight:bold;font-size:14pt;color:#22B38C}.nm{font-size:13pt;color:#C36BE0}.tc{font-size:13pt;color:#E07B7B}.vn{font-size:11pt;font-style:italic;color:#777}'
        .'table.sm{border-collapse:collapse;margin-top:6px;font-size:13pt;min-width:60%}table.sm th,table.sm td{border:1px solid #888;padding:0 6px;line-height:1.15}table.sm th{background:#f2f3f4}.c{text-align:center}.r{text-align:right}'
        .'.smt{font-size:15pt;font-weight:bold;margin-top:6px}.smw .sm{margin-top:2px}.sme{font-size:14pt;color:#777}'
        .'body.m-grid .smw{display:none}body.m-grid table.g td{height:27mm}body.m-info table.g{display:none}body.m-info table.sm{width:100%;font-size:15pt}body.m-info .smt{font-size:18pt;margin:8px 0 4px}'
        .'.bar label{margin:0 6px;cursor:pointer;font-size:13px}.bar .opt{display:inline-block;margin:0 10px;padding:2px 8px;background:#444;border-radius:4px}'
        .'.bar{position:sticky;top:0;background:#333;color:#fff;padding:8px;text-align:center;z-index:9;font:13px Tahoma}.bar button{font:14px Tahoma;padding:4px 14px;margin:0 4px}'
        .'@media print{body{background:#fff}.pg{width:auto;min-height:0;margin:0;padding:0;box-shadow:none}.bar{display:none}}'
        .'</style></head><body class="m-'.$show.'"><div class="bar"><button onclick="window.print()">🖨 พิมพ์ / บันทึกเป็น PDF</button><button onclick="history.length>1?history.back():window.close()">กลับ</button>'
        .'<span class="opt">พิมพ์: '.$opt.'</span>'
        .' <span style="font-size:12px">กระดาษ A4 แนวนอน · เปิด "กราฟิกพื้นหลัง" (สีช่องวัน)</span></div>'.$pages
        .'<script>document.querySelectorAll("input[name=ttshow]").forEach(function(r){r.addEventListener("change",function(){document.body.className="m-"+r.value;try{var u=new URL(location.href);u.searchParams.set("show",r.value);history.replaceState(null,"",u);}catch(e){}});});</script></body></html>';
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

/* ================================================================ กำหนดคาบเรียน / ช่วงพัก (ผู้ดูแลระบบ + ฝ่ายวิชาการ) */

/** แถวทั้งหมดตามลำดับเวลา: [type p|b, ชื่อ/หมายเหตุ, เริ่ม, สิ้นสุด] */
function tt_period_rows(): array
{
    $o = [];
    $pers = tt_periods();
    $br = tt_breaks();
    foreach (tt_cols() as [$k, $p]) {
        $o[] = $k === 'b' ? ['b', $br[$p][0], $br[$p][1], $br[$p][2]] : ['p', $pers[$p][2], $pers[$p][0], $pers[$p][1]];
    }
    return $o;
}

/** ตรวจและบันทึก: คืนข้อความผิดพลาด ([] = สำเร็จ) */
function tt_periods_save(array $type, array $name, array $start, array $end): array
{
    $rows = [];
    $err = [];
    $tm = fn($x) => preg_match('/^([01]?\d|2[0-3])[:.]([0-5]\d)$/', trim(tt_ar((string) $x)), $m) ? sprintf('%02d:%02d', $m[1], $m[2]) : null;
    foreach (array_values($type) as $i => $t) {
        $t = $t === 'b' ? 'b' : 'p';
        $s = $tm($start[$i] ?? '');
        $e = $tm($end[$i] ?? '');
        $label = $t === 'b' ? 'ช่วงพัก' : 'คาบที่ '.(count(array_filter($rows, fn($r) => $r[0] === 'p')) + 1);
        if ($s === null || $e === null) {
            $err[] = $label.': กรุณากรอกเวลาเริ่ม/สิ้นสุดแบบ ๒๔ ชั่วโมง เช่น 08:30 หรือ 13.40';
            continue;
        }
        if ($e <= $s) {
            $err[] = $label.': เวลาสิ้นสุดต้องหลังเวลาเริ่ม';
        }
        if ($rows && $s < $rows[count($rows) - 1][3]) {
            $err[] = $label.': เวลาเริ่ม '.$s.' ทับกับช่วงก่อนหน้า (สิ้นสุด '.$rows[count($rows) - 1][3].')';
        }
        $rows[] = [$t, mb_substr(trim((string) ($name[$i] ?? '')), 0, 40), $s, $e];
    }
    $np = count(array_filter($rows, fn($r) => $r[0] === 'p'));
    if ($np < 1) {
        $err[] = 'ต้องมีคาบเรียนอย่างน้อย ๑ คาบ';
    }
    if ($np > 15) {
        $err[] = 'กำหนดได้ไม่เกิน ๑๕ คาบ';
    }
    if ($rows && ($rows[0][0] === 'b' || $rows[count($rows) - 1][0] === 'b')) {
        $err[] = 'ช่วงพักต้องอยู่ระหว่างคาบเรียน (ไม่ใช่ก่อนคาบแรกหรือหลังคาบสุดท้าย)';
    }
    foreach ($rows as $i => $r) {
        if ($i && $r[0] === 'b' && $rows[$i - 1][0] === 'b') {
            $err[] = 'มีช่วงพักติดกัน ๒ ช่วง — รวมเป็นช่วงเดียว';
            break;
        }
    }
    if ($err) {
        return $err;
    }
    tt_periods_backup();
    q('DELETE FROM {mis_data} WHERE `kind`=?', ['periods']);
    $no = 0;
    foreach ($rows as [$t, $n, $s, $e]) {
        if ($t === 'p') {
            $no++;
            md_set('periods', 'p'.$no, '', '', json_encode(['no' => $no, 'start' => $s, 'end' => $e, 'note' => $n], JSON_UNESCAPED_UNICODE));
        } else {
            md_set('periods', 'b'.$no, '', '', json_encode(['type' => 'break', 'after' => $no, 'name' => $n !== '' ? $n : 'พักกลางวัน', 'start' => $s, 'end' => $e], JSON_UNESCAPED_UNICODE));
        }
    }
    md_set('cfg', 'tt_breaks_set', '', '', '1');
    return [];
}

/** สำรองค่าคาบเรียนชุดปัจจุบัน (ใช้ "ย้อนกลับการบันทึกครั้งล่าสุด") */
function tt_periods_backup(): void
{
    $rows = array_map(fn($r) => [$r['r1'], $r['r2'], $r['r3'], $r['value']], md_rows('periods'));
    md_set('cfg', 'periods_prev', '', '', json_encode(['at' => date('Y-m-d H:i'), 'by' => (int) (me()['id'] ?? 0), 'rows' => $rows], JSON_UNESCAPED_UNICODE));
}

/** ย้อนกลับเป็นชุดก่อนบันทึกครั้งล่าสุด (สลับกัน จึงกดซ้ำเพื่อกลับมาได้) */
function tt_periods_undo(): bool
{
    $b = json_decode((string) md_get('cfg', 'periods_prev'), true);
    if (!is_array($b) || !isset($b['rows'])) {
        return false;
    }
    tt_periods_backup();
    q('DELETE FROM {mis_data} WHERE `kind`=?', ['periods']);
    foreach ($b['rows'] as [$r1, $r2, $r3, $v]) {
        md_set('periods', $r1, $r2, $r3, (string) $v);
    }
    return true;
}

/** คาบสูงสุดที่มีวิชาจัดไว้ (ทุกภาคเรียน) */
function tt_max_used_period(): int
{
    $max = 0;
    foreach (md_rows('tt') as $r) {
        $p = (int) (explode('-', (string) $r['r3'])[1] ?? 0);
        if ($p <= 15 && $p > $max) {
            $max = $p;
        }
    }
    return $max;
}

/**
 * เติมคาบที่ขาดให้ครบ ๑..N (คงเวลาของคาบที่มีอยู่ เวลาคาบที่เติมเป็นค่าประมาณ ๕๐ นาที ให้ตรวจแล้วบันทึก)
 * คืนแถวสำหรับฟอร์ม [type, ชื่อ, เริ่ม, สิ้นสุด]
 */
function tt_period_fill_rows(): array
{
    $pers = tt_periods();
    $br = tt_breaks();
    $n = max($pers ? max(array_keys($pers)) : 0, tt_max_used_period(), 1);
    $mm = fn($t) => preg_match('/^(\d{1,2})[:.](\d{2})$/', (string) $t, $m) ? (int) $m[1] * 60 + (int) $m[2] : null;
    $hm = fn($x) => sprintf('%02d:%02d', intdiv(max(0, $x), 60) % 24, max(0, $x) % 60);
    $t = [];  // p => [start, end] เป็นนาที
    foreach ($pers as $p => [$a, $b]) {
        if ($p <= $n && $mm($a) !== null && $mm($b) !== null) {
            $t[$p] = [$mm($a), $mm($b)];
        }
    }
    $len = 50;
    // ย้อนหลังจากคาบที่รู้เวลา แล้วไล่ไปข้างหน้า
    for ($p = $n; $p >= 1; $p--) {
        if (!isset($t[$p]) && isset($t[$p + 1])) {
            $end = $t[$p + 1][0] - (isset($br[$p]) && $mm($br[$p][1]) !== null && $mm($br[$p][2]) !== null ? $mm($br[$p][2]) - $mm($br[$p][1]) : 0);
            $t[$p] = [$end - $len, $end];
        }
    }
    for ($p = 1; $p <= $n; $p++) {
        if (!isset($t[$p])) {
            $st = isset($t[$p - 1]) ? $t[$p - 1][1] + (isset($br[$p - 1]) && $mm($br[$p - 1][1]) !== null && $mm($br[$p - 1][2]) !== null ? $mm($br[$p - 1][2]) - $mm($br[$p - 1][1]) : 0) : 8 * 60 + 30;
            $t[$p] = [$st, $st + $len];
        }
    }
    $rows = [];
    for ($p = 1; $p <= $n; $p++) {
        $rows[] = ['p', $pers[$p][2] ?? '', $hm($t[$p][0]), $hm($t[$p][1])];
        if (isset($br[$p]) && $p < $n) {
            $rows[] = ['b', $br[$p][0], $br[$p][1], $br[$p][2]];
        }
    }
    return $rows;
}

function adm_periods(bool $post)
{
    if (!tt_can_edit()) {
        return ['กำหนดคาบเรียน', msg_box('กำหนดคาบเรียนได้เฉพาะผู้ดูแลระบบและฝ่ายวิชาการ', false)];
    }
    $msg = '';
    $rows = tt_period_rows();
    if ($post && isset($_POST['undo'])) {
        redirect(a_url('periods', tt_periods_undo() ? '&undone=1' : ''));
    }
    if (isset($_GET['fill'])) {
        $rows = tt_period_fill_rows();
        $msg = msg_box('เติมคาบที่หายไปให้ครบแล้ว (ยังไม่บันทึก) — เวลาของคาบที่เติมเป็นค่าประมาณ กรุณาตรวจ/แก้เวลาให้ถูกต้อง แล้วกด "บันทึก" ด้านล่าง วิชาที่จัดไว้จะกลับมาแสดงตามคาบเดิม', false);
    }
    if ($post) {
        $type = (array) ($_POST['type'] ?? []);
        $err = tt_periods_save($type, (array) ($_POST['name'] ?? []), (array) ($_POST['start'] ?? []), (array) ($_POST['end'] ?? []));
        if (!$err) {
            redirect(a_url('periods', '&done=1'));
        }
        $msg = msg_box(implode('<br>', array_map('h', $err)), false);
        $rows = [];
        foreach (array_values($type) as $i => $t) {
            $rows[] = [$t === 'b' ? 'b' : 'p', (string) ($_POST['name'][$i] ?? ''), (string) ($_POST['start'][$i] ?? ''), (string) ($_POST['end'][$i] ?? '')];
        }
    } elseif (isset($_GET['done'])) {
        $msg = msg_box('บันทึกคาบเรียนแล้ว — ตารางเรียน/ตารางสอนและหน้าพิมพ์ใช้เวลาชุดนี้ทันที');
    } elseif (isset($_GET['undone'])) {
        $msg = msg_box('ย้อนกลับเป็นค่าคาบเรียนชุดก่อนการบันทึกครั้งล่าสุดแล้ว');
    }
    // ตารางที่จัดไว้ในคาบที่ไม่มีแล้ว (ไม่แสดง แต่ยังเก็บไว้)
    $pers = tt_periods();
    $orph = 0;
    foreach (tt_all() as $slots) {
        foreach (array_keys($slots) as $slot) {
            if (!isset($pers[(int) explode('-', (string) $slot)[1]])) {
                $orph++;
            }
        }
    }
    if (!empty($GLOBALS['tt_periods_filled']) && !$post && !isset($_GET['fill'])) {
        $msg .= msg_box('การตั้งค่าคาบเดิมมีไม่ครบ (บันทึกไว้บางคาบ) ระบบเติมคาบที่ขาดให้อัตโนมัติแล้ว ตารางเรียนและหน้าพิมพ์แสดงครบทุกคาบ — เวลาของคาบที่เติมเป็นค่าประมาณ ตรวจ/แก้เวลาด้านล่าง แล้วกด "บันทึก" เพื่อยืนยัน', false);
    } elseif (($orph || tt_max_used_period() > ($pers ? max(array_keys($pers)) : 0)) && !$post && !isset($_GET['fill'])) {
        $msg .= msg_box(($orph ? 'มีวิชาที่จัดไว้ในคาบที่ไม่มีในการตั้งค่าตอนนี้ '.$orph.' ช่อง (ภาคเรียนนี้) — ข้อมูลยังอยู่ครบ แค่ไม่แสดง' : 'เลขคาบในการตั้งค่าไม่ต่อเนื่อง')
            .' &nbsp; <a href="'.h(a_url('periods', '&fill=1')).'"><b>» กดเพื่อเติมคาบที่หายไปให้ครบ</b></a> (ตรวจเวลาแล้วกดบันทึก วิชาจะกลับมาแสดงตามคาบเดิม)', false);
    }
    $prev = json_decode((string) md_get('cfg', 'periods_prev'), true);
    $undo = is_array($prev) && isset($prev['rows'])
        ? '<form method="post" action="'.h(a_url('periods')).'" style="width:970px;margin:6px auto 0;text-align:right;font-size:12px" onsubmit="return confirm(\'ย้อนกลับค่าคาบเรียนเป็นชุดก่อนการบันทึกครั้งล่าสุด ('.h((string) ($prev['at'] ?? '')).')?\')">'
            .'<input type="hidden" name="undo" value="1"/><button type="submit" style="font-size:12px;padding:3px 10px;cursor:pointer">↶ ย้อนกลับการบันทึกครั้งล่าสุด ('.h((string) ($prev['at'] ?? '')).')</button></form>'
        : '';
    $tr = '';
    foreach ($rows as $r) {
        $tr .= tt_period_tr(...$r);
    }
    $tpl = tt_period_tr('p', '', '', '');
    $html = '<style>#ttp{border-collapse:collapse;width:100%}#ttp th{background:#EAECEE;padding:5px;font-weight:normal;border:1px solid #D5D8DC}#ttp td{border:1px solid #E5E7E9;padding:4px 6px;text-align:center}'
        .'#ttp tr.b td{background:#F4F6F6}#ttp .lb{font-weight:bold;white-space:nowrap}#ttp tr.b .lb{color:#7F8C8D}#ttp .s,#ttp .e{height:22px;width:70px;text-align:center}#ttp .nm{width:200px;height:22px}#ttp .dur{color:#888;font-size:12px;white-space:nowrap}'
        .'.ttp-a{display:inline-block;margin:8px 6px 0 0;padding:4px 12px;border-radius:4px;background:#EAF2F8;color:#1F618D;text-decoration:none;cursor:pointer}.ttp-a.b{background:#F2F3F4;color:#555}#ttp a{cursor:pointer;text-decoration:none;margin:0 2px;font-size:14px}</style>'
        .'<table id="ttp"><tr><th style="width:90px">ลำดับ</th><th>เวลาเริ่ม</th><th>เวลาสิ้นสุด</th><th>นาที</th><th>ชื่อช่วงพัก / หมายเหตุ</th><th style="width:90px">เลื่อน / ลบ</th></tr>'.$tr.'</table>'
        .'<a class="ttp-a" onclick="ttpAdd(\'p\')">+ เพิ่มคาบเรียน</a><a class="ttp-a b" onclick="ttpAdd(\'b\')">+ เพิ่มช่วงพัก (เช่น พักกลางวัน)</a>'
        .'<div class="gray_12" style="font-size:11px;margin-top:6px">เพิ่มแล้วเลื่อนขึ้น/ลงไปวางระหว่างคาบได้ · ช่วงพักแสดงเป็นช่องแนวตั้งในตารางเรียน/ตารางสอนและหน้าพิมพ์ · ลบคาบท้าย ๆ แล้ว วิชาที่จัดไว้ในคาบนั้นจะซ่อนไว้ ไม่ถูกลบ</div>'
        .'<template id="ttpt">'.$tpl.'</template>'
        .'<script>(function(){var T=document.getElementById("ttp");function mm(v){var m=/^\s*(\d{1,2})[:.](\d{2})\s*$/.exec(v||"");return m&&+m[1]<24&&+m[2]<60?(+m[1])*60+(+m[2]):null}function hm(m){m=((m%1440)+1440)%1440;return ("0"+Math.floor(m/60)).slice(-2)+":"+("0"+m%60).slice(-2)}'
        .'function rows(){return Array.prototype.slice.call(T.querySelectorAll("tr.r"))}'
        .'window.ttpFix=function(){var n=0;rows().forEach(function(r){var b=r.classList.contains("b");r.querySelector(".lb").textContent=b?"พัก":"คาบที่ "+(++n);var s=mm(r.querySelector(".s").value),e=mm(r.querySelector(".e").value);r.querySelector(".dur").textContent=(s!==null&&e!==null&&e>s)?(e-s)+" นาที":"";})};'
        .'window.ttpAdd=function(t){var x=document.getElementById("ttpt").content.firstElementChild.cloneNode(true);var rs=rows(),last=rs[rs.length-1];var st=last?mm(last.querySelector(".e").value):510;if(st===null)st=510;'
        .'var dur=50;for(var i=rs.length-1;i>=0;i--){if(!rs[i].classList.contains("b")){var a=mm(rs[i].querySelector(".s").value),b=mm(rs[i].querySelector(".e").value);if(a!==null&&b!==null&&b>a)dur=b-a;break;}}'
        .'if(t=="b"){x.className="r b";x.querySelector(".ty").value="b";x.querySelector(".nm").value="พักกลางวัน";dur=60;}x.querySelector(".s").value=hm(st);x.querySelector(".e").value=hm(st+dur);T.querySelector("tbody").appendChild(x);ttpFix();};'
        .'window.ttpMv=function(a,d){var r=a.closest("tr"),o=d<0?r.previousElementSibling:r.nextElementSibling;if(!o||!o.classList.contains("r"))return;if(d<0)r.parentNode.insertBefore(r,o);else r.parentNode.insertBefore(o,r);ttpFix();};'
        .'window.ttpDel=function(a){a.closest("tr").remove();ttpFix();};T.addEventListener("change",ttpFix);ttpFix();})();</script>';
    return ['กำหนดคาบเรียน', $msg.admin_form('กำหนดคาบเรียนและช่วงพัก', a_url('periods'), [['คาบเรียน / ช่วงพัก', $html, true]], a_url('timetable')).$undo];
}

function tt_period_tr(string $t, string $name, string $s, string $e): string
{
    $b = $t === 'b';
    return '<tr class="r'.($b ? ' b' : '').'"><td class="lb"></td>'
        .'<td><input type="hidden" name="type[]" class="ty" value="'.($b ? 'b' : 'p').'"/><input type="text" name="start[]" class="s general radius grayborder" value="'.h($s).'" maxlength="5" placeholder="08:30" inputmode="numeric" autocomplete="off"/></td>'
        .'<td><input type="text" name="end[]" class="e general radius grayborder" value="'.h($e).'" maxlength="5" placeholder="09:20" inputmode="numeric" autocomplete="off"/></td><td class="dur"></td>'
        .'<td><input type="text" name="name[]" class="nm general radius grayborder" maxlength="40" value="'.h($name).'" placeholder="'.($b ? 'พักกลางวัน' : '(ไม่บังคับ)').'"/></td>'
        .'<td style="white-space:nowrap"><a onclick="ttpMv(this,-1)" title="เลื่อนขึ้น">▲</a><a onclick="ttpMv(this,1)" title="เลื่อนลง">▼</a> <a onclick="ttpDel(this)" title="ลบ"><img src="../icon_tools/vec_bin_dark.png" height="15" style="vertical-align:middle"/></a></td></tr>';
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
