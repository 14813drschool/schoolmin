<?php
/**
 * mis/app/helpers.php
 * ฐานข้อมูล ผู้ใช้ ภาคเรียน และการประกอบหน้า
 */
defined('MIS') or exit;

/** query ดิบ: เขียนชื่อตารางเป็น {user} {course} ... จะใส่คำนำหน้าให้เอง คืนค่าเป็น array ของ array */
function q(string $sql, array $params = []): array
{
    static $db = null;
    if ($db === null) {
        $db = \Kotchasan\DB::create();
    }
    $sql = preg_replace_callback('/\{([a-z_]+)\}/', fn($m) => '`'.$db->getTableName($m[1]).'`', $sql);
    $r = $db->raw($sql, $params);
    if (!$r) {
        return [];
    }
    $out = [];
    foreach ($r->fetchAll() as $row) {
        $out[] = (array) $row;
    }
    return $out;
}

function q1(string $sql, array $params = []): ?array
{
    $r = q($sql, $params);
    return $r[0] ?? null;
}

function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function cfg($key, $default = null)
{
    return \Kotchasan::$cfg->$key ?? $default;
}

const TH_MONTHS = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
const TH_DAYS = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];

/** 7&nbsp;พฤศจิกายน&nbsp;2569 */
function th_date(string $ymd = '', string $sep = '&nbsp;'): string
{
    $t = $ymd === '' ? time() : strtotime($ymd);
    return date('j', $t).$sep.TH_MONTHS[(int) date('n', $t)].$sep.(date('Y', $t) + 543);
}

/* ---------------------------------------------------------------- ผู้ใช้ */

/** ผู้ใช้ปัจจุบันจากคุกกี้ของ SMS (null = ยังไม่ได้เข้าสู่ระบบ) */
function me(): ?array
{
    static $me = false;
    if ($me !== false) {
        return $me;
    }
    $me = null;
    $token = $_COOKIE['auth_token'] ?? '';
    if ($token === '') {
        return null;
    }
    $payload = \Index\Auth\Model::verifyToken($token);
    $user = $payload ? \Index\Auth\Model::getUserByToken($token) : null;
    if (!$user || (int) $user->id <= 0) {
        return null;
    }
    $u = (array) $user;
    $u['permission'] = is_array($u['permission'] ?? null) ? $u['permission'] : array_filter(explode(',', trim((string) ($u['permission'] ?? ''), ',')));
    $u['sid'] = $payload['sid'] ?? '';
    // นับเป็นออนไลน์: อัปเดตเวลาที่เห็นล่าสุดของเครื่องนี้
    if ($u['sid'] !== '') {
        q('UPDATE {user_session} SET `last_seen`=NOW() WHERE `sid`=? AND `member_id`=?', [$u['sid'], (int) $u['id']]);
    }
    $p = q1('SELECT * FROM {personnel} WHERE `id`=?', [(int) $u['id']]);
    $u['personnel'] = $p;
    $me = $u;
    return $me;
}

function can(string ...$perms): bool
{
    $u = me();
    if (!$u) {
        return false;
    }
    if ((int) $u['status'] === 1) {
        return true;
    }
    return (bool) array_intersect($perms, $u['permission']);
}

function is_manager(): bool
{
    return can('can_manage_student', 'can_manage_course');
}

function role_text(array $u): string
{
    if ((int) $u['status'] === 1) {
        return 'ผู้ดูแลระบบ';
    }
    if (array_intersect(['can_teacher', 'can_rate_student'], $u['permission'])) {
        return 'ครูผู้สอน';
    }
    return 'บุคลากร';
}

/** ชื่อ-นามสกุล คั่นด้วย &nbsp;&nbsp; แบบระบบเดิม */
function name_nbsp(string $name): string
{
    $p = preg_split('/\s+/u', trim($name), 2);
    return h($p[0]).(isset($p[1]) ? '&nbsp;&nbsp;'.h($p[1]) : '');
}

function avatar_url(int $id): string
{
    $u = \Gcms\Api::getAvatarUrl($id);
    return $u ?: '../images/man_nopic.png';
}

/** หมวดหมู่ของ SMS (class, room, department, position) */
function cat(string $type, $id, string $default = ''): string
{
    static $all = [];
    if (!isset($all[$type])) {
        $all[$type] = [];
        foreach (q('SELECT `category_id`,`topic`,`language` FROM {category} WHERE `type`=? ORDER BY `language` DESC', [$type]) as $r) {
            if (!isset($all[$type][$r['category_id']]) || $r['language'] === 'th') {
                $all[$type][$r['category_id']] = $r['topic'];
            }
        }
    }
    return $all[$type][(string) $id] ?? $default;
}

function cats(string $type): array
{
    cat($type, '');
    $out = [];
    foreach (q('SELECT DISTINCT `category_id` FROM {category} WHERE `type`=? ORDER BY CAST(`category_id` AS UNSIGNED)', [$type]) as $r) {
        $out[$r['category_id']] = cat($type, $r['category_id']);
    }
    return $out;
}

/** ห้อง "1/4" จากหมวดหมู่ห้อง */
function room_short($room): string
{
    return cat('room', $room, (string) $room);
}

/** ชื่อชั้นแบบสั้น ม.1/1 */
function class_room_short($class, $room): string
{
    $r = room_short($room);
    if (strpos($r, '/') !== false) {
        $c = cat('class', $class, '');
        if (preg_match('/^(ประถมศึกษา|มัธยมศึกษา)ปีที่/u', $c, $m)) {
            return ($m[1] === 'ประถมศึกษา' ? 'ป.' : 'ม.').$r;
        }
    }
    return $r;
}

/** ข้อความ ครูที่ปรึกษาชั้น ... ของผู้ใช้ */
function advisor_text(?array $u = null): string
{
    $u = $u ?? me();
    $p = $u['personnel'] ?? null;
    if (!$p || (int) $p['class'] === 0) {
        return 'ครูที่ปรึกษาชั้น -';
    }
    $c = cat('class', $p['class'], '');
    $r = room_short($p['room']);
    $r = strpos($r, '/') !== false ? substr($r, strrpos($r, '/') + 1) : $r;
    return 'ครูที่ปรึกษาชั้น '.$c.((int) $p['room'] ? '/'.$r : '');
}

/* ---------------------------------------------------------------- ภาคเรียน */

/** [ปี, ภาคเรียน] ที่เลือกดู (ค่าเริ่มต้นตามการตั้งค่าของ SMS) */
function term(): array
{
    static $t = null;
    if ($t !== null) {
        return $t;
    }
    $y = (int) cfg('academic_year', date('Y') + 543);
    $s = (int) cfg('term', 1);
    if (preg_match('/^(\d{4})\|([12])$/', (string) ($_COOKIE['mis_term'] ?? ''), $m)) {
        $y = (int) $m[1];
        $s = (int) $m[2];
    }
    $t = [$y, $s];
    return $t;
}

function year_options(): string
{
    [$y, $s] = term();
    $cur = (int) cfg('academic_year', date('Y') + 543);
    $o = '';
    for ($i = $cur - 13; $i <= $cur; $i++) {
        foreach ([1, 2] as $t) {
            $o .= "\n            <option value=\"$i|$t\"".($i === $y && $t === $s ? 'selected' : '').">&nbsp;&nbsp;$i/$t</option>";
        }
    }
    return $o."\n          ";
}

/* ---------------------------------------------------------------- หน้า */

/** ผู้ใช้ออนไลน์ใน 15 นาที: [ครู, นักเรียน] (รายการ id) */
function online_users(): array
{
    $rows = q('SELECT DISTINCT S.`member_id`, (ST.`id` IS NOT NULL) is_student FROM {user_session} S'
        .' LEFT JOIN {student} ST ON ST.`id`=S.`member_id`'
        .' WHERE S.`expires_at`>? AND S.`last_seen`>=DATE_SUB(NOW(), INTERVAL 15 MINUTE)', [time()]);
    $t = $s = [];
    foreach ($rows as $r) {
        if ((int) $r['is_student']) {
            $s[] = (int) $r['member_id'];
        } else {
            $t[] = (int) $r['member_id'];
        }
    }
    return [array_values(array_unique($t)), array_values(array_unique($s))];
}

function tpl(string $file): string
{
    return (string) file_get_contents(MIS_APP.$file);
}

/** ประกอบหน้าเต็ม: หัวเว็บ + เนื้อหา + ท้ายเว็บ */
function render_page(string $title, string $content, string $module = ''): void
{
    $u = me();
    [$y, $t] = term();
    [$ot, $os] = online_users();
    $online = "\n          <div style=\"display:table-row\"><span style='float:left;width:130px'>Online Teacher , "
        .(count($ot) ? "<a href='?module=onlineTeacher' class='bluelink'>".count($ot).'</a>' : '0')
        ."</span> <span style='float:right;white-space:nowrap'> user</span></div>\n"
        ."          <div style=\"display:table-row\"><span style='float:left;width:130px'>Online Student , "
        .(count($os) ? "<a href='?module=onlineStudent' class='bluelink'>".count($os).'</a>' : '0')
        ."</span><span style='float:right;white-space:nowrap'> user</span></div>";
    $adv = $u['personnel'] ?? null;
    $sel = ['task' => '00', 'main' => '01', 'academic' => '02'][$module] ?? '';
    $rep = [
        '{{TITLE}}' => h($title).' - ระบบบริหารจัดการสารสนเทศโรงเรียน',
        '{{V}}' => (string) @filemtime(dirname(MIS_APP).'/css/main.css'),
        '{{YEAR_OPTIONS}}' => year_options(),
        '{{SELF}}' => h($_SERVER['REQUEST_URI'] ?? 'index.php'),
        '{{ONLINE}}' => $online,
        '{{UID}}' => h($u['username'] ?? ''),
        '{{UNAME}}' => name_nbsp($u['name'] ?? ''),
        '{{UROLE}}' => $u ? role_text($u) : '',
        '{{TODAY}}' => th_date(),
        '{{T}}' => (string) $t,
        '{{Y}}' => (string) $y,
        '{{ADVROOM}}' => (string) ($adv['room'] ?? 0),
        '{{SEL00}}' => $sel === '00' ? "class='selected' " : '',
        '{{SEL01}}' => $sel === '01' ? "class='selected' " : '',
        '{{SEL02}}' => $sel === '02' ? "class='selected' " : '',
    ];
    $html = strtr(tpl('layout_head.html'), $rep).strtr($GLOBALS['mis_top_override'] ?? tpl('layout_top.html'), $rep).$content;
    $foot = strtr(tpl('layout_foot.html'), ['{{EXEC}}' => (string) (microtime(true) - MIS_START)]);
    mis_headers();
    echo $html.$foot;
}

/** ค่าที่ใช้แทนในแม่แบบเนื้อหาทุกหน้า */
function page_vars(): array
{
    $u = me();
    [$y, $t] = term();
    return [
        '{{YT}}' => $y.'/'.$t,
        '{{T}}' => (string) $t,
        '{{Y}}' => (string) $y,
        '{{TNAME}}' => name_nbsp($u['name'] ?? ''),
        '{{PHOTO}}' => h(avatar_url((int) ($u['id'] ?? 0))).'" style="width:100px',
        '{{ADV}}' => h(advisor_text()),
        '{{ADVSHORT}}' => h(($p = $u['personnel'] ?? null) && (int) $p['room'] ? class_room_short($p['class'], $p['room']) : '-'),
        '{{ADVROOMNAME}}' => h(($p = $u['personnel'] ?? null) && (int) $p['room'] ? room_short($p['room']) : '-'),
        '{{ADVROOM}}' => (string) (int) ($u['personnel']['room'] ?? 0),
        '{{ADV2}}' => h(str_replace('ครูที่ปรึกษาชั้น', 'รับผิดชอบระดับชั้น', advisor_text())),
    ];
}

function redirect(string $url): void
{
    header('Location: '.$url, true, 302);
    exit;
}

/** คุกกี้ยืนยันตัวตนแบบเดียวกับ API ของ SMS */
function mis_auth_cookie(string $name, string $value, int $ttl): void
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie($name, $value, ['expires' => time() + $ttl, 'path' => '/', 'domain' => '', 'secure' => $https, 'httponly' => true, 'samesite' => $https ? 'Strict' : 'Lax']);
}

/** สคริปต์ของระบบเดิม (jQuery 1.6, sexyalertbox ฯลฯ) ใช้ eval จึงต้องผ่อน CSP เฉพาะหน้าใน /mis/ */
function mis_headers(): void
{
    header_remove('Content-Security-Policy');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
}
