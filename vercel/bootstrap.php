<?php
/**
 * vercel/bootstrap.php
 *
 * ชั้นกลางสำหรับรัน SMS (School Management System) บน Vercel
 *
 * ปัญหาของ Vercel: โฟลเดอร์โค้ดเป็นแบบอ่านอย่างเดียว และไฟล์ที่เขียนลง /tmp อยู่ได้แค่ในเครื่องนั้นชั่วคราว
 * แต่ SMS ต้องเขียนไฟล์ลง settings/ (ค่าตั้งค่า), datas/ (รูป เอกสาร) และ language/ (คำแปล)
 *
 * วิธีแก้ (ไม่ต้องแก้โค้ดของ SMS):
 *  1. เครื่องที่เพิ่งเริ่ม (cold start) คัดลอกโค้ดไปไว้ที่ /tmp แล้วรันจากตรงนั้น ทุกโฟลเดอร์จึงเขียนได้
 *  2. ไฟล์ใน settings/, datas/, language/ ถูกเก็บถาวรในตาราง vercel_files ของฐานข้อมูล
 *     - ต้นคำขอ: ดึงไฟล์ที่เครื่องอื่นแก้ไว้ (ดูจากเลขเวอร์ชันใน vercel_meta)
 *     - ท้ายคำขอ: หาไฟล์ที่เพิ่ม/แก้/ลบระหว่างคำขอนี้ แล้วบันทึกลงฐานข้อมูล
 *  3. settings/database.php สร้างจาก Environment Variables ของ Vercel ทุกครั้ง (ไม่เก็บรหัสผ่านลงฐานข้อมูล)
 *  4. session ของ PHP เก็บในตาราง vercel_sessions เพื่อให้ทุกเครื่องเห็นตรงกัน
 *  5. แปลง URL แบบเดียวกับ .htaccess ของ SMS
 */

final class VercelSms
{
    /** โฟลเดอร์ที่ SMS เขียนไฟล์และต้องเก็บถาวร */
    const SYNC_DIRS = ['settings', 'datas', 'language'];

    /** ไฟล์ที่ไม่เก็บลงฐานข้อมูล (ชั่วคราวหรือสร้างใหม่ได้) */
    const SKIP_PATTERN = '#^(settings/database\.php$|datas/logs/|datas/cache/(?!revoked_tokens\.json$)|.*\.(log|tmp)$)#';

    /** ไฟล์ใหญ่กว่านี้ไม่เก็บ (Vercel รับคำขอได้ไม่เกิน 4.5MB อยู่แล้ว) */
    const MAX_FILE = 8388608;

    /** @var string โฟลเดอร์โค้ดต้นฉบับ (อ่านอย่างเดียว) */
    private $src;
    /** @var string โฟลเดอร์ที่รันจริงใน /tmp */
    private $root;
    /** @var string ไฟล์สถานะของเครื่องนี้ */
    private $stateFile;
    /** @var array{ver:int, files:array} */
    private $state = ['ver' => 0, 'files' => []];
    /** @var array ค่าเชื่อมต่อฐานข้อมูลจาก Environment Variables */
    private $db = [];
    /** @var \PDO|null */
    private $pdo = null;
    /** @var float */
    private $started;

    public function __construct()
    {
        $this->started = microtime(true);
        $this->src = dirname(__DIR__);
        $tmp = rtrim(getenv('SMS_TMP') ?: sys_get_temp_dir(), '/');
        $id = preg_replace('/[^a-zA-Z0-9_]/', '', (string) (getenv('VERCEL_DEPLOYMENT_ID') ?: 'dev'));
        $this->root = $tmp.'/sms-'.$id;
        $this->stateFile = $tmp.'/sms-'.$id.'.state.json';
        $this->db = self::dbEnv();
    }

    /* ======================================================================
     * ค่าฐานข้อมูลจาก Environment Variables
     * รองรับ DATABASE_URL (mysql://user:pass@host:port/db) และตัวแปรแยก
     * DB_* / TIDB_* / MYSQL_* (เช่น DB_HOST, TIDB_HOST, MYSQL_HOST)
     * ==================================================================== */
    public static function dbEnv(): array
    {
        $c = ['host' => '', 'port' => 3306, 'user' => '', 'pass' => '', 'name' => '', 'prefix' => 'app'];
        foreach (['DATABASE_URL', 'TIDB_DATABASE_URL', 'MYSQL_URL'] as $k) {
            $url = (string) getenv($k);
            if ($url !== '' && preg_match('#^mysqls?://#i', $url)) {
                $u = parse_url($url);
                $c['host'] = $u['host'] ?? '';
                $c['port'] = (int) ($u['port'] ?? 3306);
                $c['user'] = rawurldecode($u['user'] ?? '');
                $c['pass'] = rawurldecode($u['pass'] ?? '');
                $c['name'] = ltrim(rawurldecode($u['path'] ?? ''), '/');
                break;
            }
        }
        $pick = function (array $names) {
            foreach ($names as $n) {
                $v = getenv($n);
                if ($v !== false && $v !== '') {
                    return $v;
                }
            }
            return null;
        };
        $map = [
            'host' => ['DB_HOST', 'TIDB_HOST', 'MYSQL_HOST'],
            'port' => ['DB_PORT', 'TIDB_PORT', 'MYSQL_PORT'],
            'user' => ['DB_USER', 'DB_USERNAME', 'TIDB_USER', 'TIDB_USERNAME', 'MYSQL_USER'],
            'pass' => ['DB_PASSWORD', 'TIDB_PASSWORD', 'MYSQL_PASSWORD'],
            'name' => ['DB_NAME', 'DB_DATABASE', 'TIDB_DATABASE', 'MYSQL_DATABASE'],
            'prefix' => ['DB_PREFIX']
        ];
        foreach ($map as $key => $names) {
            $v = $pick($names);
            if ($v !== null) {
                $c[$key] = $key === 'port' ? (int) $v : $v;
            }
        }
        // ชื่อฐานข้อมูลเริ่มต้นของ TiDB คือ test ใช้ฐานชื่อ sms แทนถ้าไม่ได้กำหนด
        if ($c['name'] === '' || ($c['name'] === 'test' && getenv('DB_NAME') === false)) {
            $c['name'] = 'sms';
        }
        $c['name'] = preg_replace('/[^a-zA-Z0-9_]/', '', $c['name']);
        $c['prefix'] = preg_replace('/[^a-zA-Z0-9_]/', '', $c['prefix']) ?: 'app';
        // TLS: เปิดอัตโนมัติเมื่อฐานข้อมูลไม่ได้อยู่ในเครื่องเดียวกัน
        $ssl = getenv('DB_SSL');
        $local = in_array($c['host'], ['', 'localhost', '127.0.0.1', '::1'], true);
        $c['ca'] = '';
        if ($ssl === '1' || ($ssl !== '0' && !$local)) {
            $c['ca'] = (string) getenv('DB_SSL_CA');
            if ($c['ca'] === '') {
                foreach (['/etc/pki/tls/certs/ca-bundle.crt', '/etc/ssl/certs/ca-certificates.crt', '/etc/ssl/cert.pem'] as $f) {
                    if (is_file($f)) {
                        $c['ca'] = $f;
                        break;
                    }
                }
            }
        }
        return $c;
    }

    /* ======================================================================
     * ฐานข้อมูลของชั้นกลาง
     * ==================================================================== */
    private function pdo(): \PDO
    {
        if ($this->pdo === null) {
            if ($this->db['host'] === '') {
                throw new \RuntimeException('ยังไม่ได้เชื่อมฐานข้อมูลกับโปรเจกต์บน Vercel');
            }
            $opt = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 10];
            if ($this->db['ca'] !== '') {
                $opt[\PDO::MYSQL_ATTR_SSL_CA] = $this->db['ca'];
            }
            $dsn = 'mysql:host='.$this->db['host'].';port='.$this->db['port'].';charset=utf8mb4';
            $this->pdo = new \PDO($dsn, $this->db['user'], $this->db['pass'], $opt);
            try {
                $this->pdo->exec('USE `'.$this->db['name'].'`');
            } catch (\PDOException $e) {
                $this->pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$this->db['name'].'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                $this->pdo->exec('USE `'.$this->db['name'].'`');
            }
        }
        return $this->pdo;
    }

    private function ensureTables(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('CREATE TABLE IF NOT EXISTS `vercel_meta` (`k` VARCHAR(32) NOT NULL PRIMARY KEY, `v` BIGINT NOT NULL) ENGINE=InnoDB');
        $pdo->exec("INSERT IGNORE INTO `vercel_meta` (`k`, `v`) VALUES ('ver', 0)");
        $pdo->exec('CREATE TABLE IF NOT EXISTS `vercel_files` ('
            .'`path` VARCHAR(255) NOT NULL PRIMARY KEY, `ver` BIGINT NOT NULL, `deleted` TINYINT NOT NULL DEFAULT 0,'
            .' `mtime` INT NOT NULL, `size` INT NOT NULL DEFAULT 0, `content` LONGBLOB NULL, KEY `ver` (`ver`)'
            .') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
        $pdo->exec('CREATE TABLE IF NOT EXISTS `vercel_sessions` ('
            .'`id` VARCHAR(128) NOT NULL PRIMARY KEY, `data` MEDIUMBLOB NULL, `expires` INT NOT NULL, KEY `expires` (`expires`)'
            .') ENGINE=InnoDB');
    }

    private function remoteVersion(): int
    {
        return (int) $this->pdo()->query("SELECT `v` FROM `vercel_meta` WHERE `k` = 'ver'")->fetchColumn();
    }

    /* ======================================================================
     * เริ่มต้นเครื่อง: คัดลอกโค้ดไป /tmp และดึงไฟล์จากฐานข้อมูล
     * ==================================================================== */
    public function boot(): void
    {
        $lock = fopen($this->root.'.lock', 'c');
        if ($lock) {
            flock($lock, LOCK_EX);
        }
        $ready = is_file($this->root.'/.vercel-ready');
        if ($ready && is_file($this->stateFile)) {
            $this->state = json_decode((string) file_get_contents($this->stateFile), true) ?: $this->state;
        }
        if (!$ready) {
            $this->copyTree($this->src, $this->root);
            $this->state = ['ver' => 0, 'files' => []];
        }
        $this->writeDatabaseSettings();
        if ($this->db['host'] !== '') {
            if (!$ready) {
                $this->ensureTables();
            }
            $remote = $this->remoteVersion();
            if (!$ready || $remote > $this->state['ver']) {
                $this->pull($ready ? $this->state['ver'] : 0, $remote);
            }
            if (!$ready) {
                $this->state['files'] = $this->scan();
            }
            $this->saveState();
        }
        if (!$ready) {
            touch($this->root.'/.vercel-ready');
        }
        if ($lock) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function copyTree(string $from, string $to): void
    {
        if (is_dir($to)) {
            self::removeTree($to);
        }
        @mkdir($to, 0777, true);
        $skip = ['.git', 'node_modules', 'api', 'Now'];
        foreach (scandir($from) as $name) {
            if ($name === '.' || $name === '..' || in_array($name, $skip, true)) {
                continue;
            }
            $s = $from.'/'.$name;
            $d = $to.'/'.$name;
            if (is_dir($s)) {
                $this->copyDir($s, $d);
            } else {
                copy($s, $d);
            }
        }
        // ไฟล์ของ Now.js ใช้ฝั่งเบราว์เซอร์เท่านั้น (Vercel ส่งเองเป็นไฟล์ static) จึงลิงก์ไว้แทนการคัดลอก
        if (is_dir($from.'/Now')) {
            @symlink($from.'/Now', $to.'/Now');
        }
    }

    private function copyDir(string $s, string $d): void
    {
        @mkdir($d, 0777, true);
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($s, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $item) {
            $target = $d.'/'.$it->getSubPathName();
            if ($item->isDir()) {
                @mkdir($target, 0777, true);
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }

    private static function removeTree(string $dir): void
    {
        if (is_link($dir)) {
            @unlink($dir);
            return;
        }
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $p = $dir.'/'.$name;
            is_dir($p) && !is_link($p) ? self::removeTree($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    /** สร้าง settings/database.php จาก Environment Variables */
    private function writeDatabaseSettings(): void
    {
        if (!defined('DB_SSL_CA')) {
            define('DB_SSL_CA', $this->db['ca']);
        }
        if ($this->db['host'] === '') {
            return;
        }
        $tpl = include $this->root.'/install/settings/database.php';
        $tpl['mysql'] = array_merge($tpl['mysql'], [
            'dbdriver' => 'mysql',
            'hostname' => $this->db['host'],
            'port' => $this->db['port'],
            'username' => $this->db['user'],
            'password' => $this->db['pass'],
            'dbname' => $this->db['name'],
            'prefix' => $this->db['prefix']
        ]);
        @mkdir($this->root.'/settings', 0777, true);
        $php = "<?php\n/* settings/database.php สร้างอัตโนมัติจาก Environment Variables ของ Vercel */\nreturn ".var_export($tpl, true).";\n";
        $file = $this->root.'/settings/database.php';
        if (!is_file($file) || file_get_contents($file) !== $php) {
            file_put_contents($file, $php);
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($file, true);
            }
        }
    }

    /* ======================================================================
     * ซิงก์ไฟล์
     * ==================================================================== */
    private function pull(int $since, int $remote): void
    {
        $stmt = $this->pdo()->prepare('SELECT `path`, `deleted`, `mtime`, `content` FROM `vercel_files` WHERE `ver` > ? ORDER BY `ver`');
        $stmt->execute([$since]);
        $touchedConfig = false;
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $rel = $row['path'];
            if (!self::isSyncPath($rel)) {
                continue;
            }
            $file = $this->root.'/'.$rel;
            if ((int) $row['deleted'] === 1) {
                @unlink($file);
                unset($this->state['files'][$rel]);
            } else {
                @mkdir(dirname($file), 0777, true);
                file_put_contents($file, (string) $row['content']);
                @touch($file, (int) $row['mtime']);
                clearstatcache(true, $file);
                $this->state['files'][$rel] = [(int) $row['mtime'], filesize($file), md5_file($file)];
            }
            if (function_exists('opcache_invalidate') && substr($rel, -4) === '.php') {
                @opcache_invalidate($file, true);
            }
            if (strpos($rel, 'settings/') === 0 || strpos($rel, 'language/') === 0) {
                $touchedConfig = true;
            }
        }
        // ค่าตั้งค่าหรือคำแปลเปลี่ยน: ล้างแคชของเครื่องนี้ (ยกเว้นรายการโทเค็นที่ถูกยกเลิก)
        if ($touchedConfig && $since > 0) {
            foreach (['templates', 'views', 'config'] as $sub) {
                self::removeTree($this->root.'/datas/cache/'.$sub);
            }
        }
        $this->state['ver'] = $remote;
    }

    private static function isSyncPath(string $rel): bool
    {
        if (strpos($rel, '..') !== false) {
            return false;
        }
        $top = strstr($rel, '/', true);
        return in_array($top, self::SYNC_DIRS, true) && !preg_match(self::SKIP_PATTERN, $rel);
    }

    /** @return array<string, array{0:int,1:int,2:string|null}> */
    private function scan(): array
    {
        $out = [];
        foreach (self::SYNC_DIRS as $dir) {
            $base = $this->root.'/'.$dir;
            if (!is_dir($base)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if (!$f->isFile()) {
                    continue;
                }
                $rel = $dir.'/'.str_replace('\\', '/', $it->getSubPathName());
                if (!self::isSyncPath($rel)) {
                    continue;
                }
                $old = $this->state['files'][$rel] ?? null;
                $mtime = $f->getMTime();
                $size = $f->getSize();
                $hash = ($old && $old[0] === $mtime && $old[1] === $size) ? $old[2] : null;
                $out[$rel] = [$mtime, $size, $hash];
            }
        }
        return $out;
    }

    /** ท้ายคำขอ: บันทึกไฟล์ที่เปลี่ยนลงฐานข้อมูล */
    public function push(): void
    {
        if ($this->db['host'] === '') {
            return;
        }
        clearstatcache();
        $now = $this->scan();
        $changed = [];
        $recent = (int) floor($this->started) - 2;
        foreach ($now as $rel => $info) {
            $old = $this->state['files'][$rel] ?? null;
            $suspect = !$old || $old[0] !== $info[0] || $old[1] !== $info[1] || $info[0] >= $recent;
            if (!$suspect) {
                $now[$rel][2] = $old[2];
                continue;
            }
            $hash = md5_file($this->root.'/'.$rel);
            $now[$rel][2] = $hash;
            if (!$old || $old[2] !== $hash) {
                $changed[] = $rel;
            }
        }
        $deleted = array_keys(array_diff_key($this->state['files'], $now));
        if (!$changed && !$deleted) {
            return;
        }
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $pdo->exec("UPDATE `vercel_meta` SET `v` = `v` + 1 WHERE `k` = 'ver'");
            $ver = (int) $pdo->query("SELECT `v` FROM `vercel_meta` WHERE `k` = 'ver'")->fetchColumn();
            $up = $pdo->prepare('INSERT INTO `vercel_files` (`path`, `ver`, `deleted`, `mtime`, `size`, `content`) VALUES (?, ?, 0, ?, ?, ?)'
                .' ON DUPLICATE KEY UPDATE `ver` = VALUES(`ver`), `deleted` = 0, `mtime` = VALUES(`mtime`), `size` = VALUES(`size`), `content` = VALUES(`content`)');
            foreach ($changed as $rel) {
                if ($now[$rel][1] > self::MAX_FILE) {
                    error_log('[vercel] ข้ามไฟล์ใหญ่เกิน: '.$rel);
                    unset($now[$rel]);
                    continue;
                }
                $up->bindValue(1, $rel);
                $up->bindValue(2, $ver, \PDO::PARAM_INT);
                $up->bindValue(3, $now[$rel][0], \PDO::PARAM_INT);
                $up->bindValue(4, $now[$rel][1], \PDO::PARAM_INT);
                $up->bindValue(5, (string) file_get_contents($this->root.'/'.$rel), \PDO::PARAM_LOB);
                $up->execute();
            }
            $del = $pdo->prepare('INSERT INTO `vercel_files` (`path`, `ver`, `deleted`, `mtime`, `size`, `content`) VALUES (?, ?, 1, 0, 0, NULL)'
                .' ON DUPLICATE KEY UPDATE `ver` = VALUES(`ver`), `deleted` = 1, `content` = NULL');
            foreach ($deleted as $rel) {
                $del->execute([$rel, $ver]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[vercel] บันทึกไฟล์ลงฐานข้อมูลไม่สำเร็จ: '.$e->getMessage());
            return;
        }
        // ถ้าไม่มีเครื่องอื่นบันทึกแทรกระหว่างนี้ ($ver ต่อจากของเราพอดี) ก็ไม่ต้องดึงของตัวเองกลับมาอีก
        // ไม่งั้นคงเลขเดิมไว้ คำขอถัดไปจะดึงไฟล์ที่เครื่องอื่นบันทึกแทรกมา
        if ($ver === $this->state['ver'] + 1) {
            $this->state['ver'] = $ver;
        }
        $this->state['files'] = $now;
        $this->saveState();
    }

    private function saveState(): void
    {
        file_put_contents($this->stateFile, json_encode($this->state));
    }

    /* ======================================================================
     * session ในฐานข้อมูล
     * ==================================================================== */
    public function registerSessions(): void
    {
        if ($this->db['host'] === '' || session_status() !== PHP_SESSION_NONE) {
            return;
        }
        $layer = $this;
        session_set_save_handler(new class($layer) implements \SessionHandlerInterface {
            private $l;
            public function __construct($l) { $this->l = $l; }
            public function open($path, $name): bool { return true; }
            public function close(): bool { return true; }
            #[\ReturnTypeWillChange]
            public function read($id) { return $this->l->sessionRead((string) $id); }
            public function write($id, $data): bool { return $this->l->sessionWrite((string) $id, (string) $data); }
            public function destroy($id): bool { return $this->l->sessionDestroy((string) $id); }
            #[\ReturnTypeWillChange]
            public function gc($max) { return $this->l->sessionGc((int) $max); }
        }, true);
    }

    public function sessionRead(string $id): string
    {
        $s = $this->pdo()->prepare('SELECT `data` FROM `vercel_sessions` WHERE `id` = ? AND `expires` > ?');
        $s->execute([$id, time()]);
        return (string) $s->fetchColumn();
    }

    public function sessionWrite(string $id, string $data): bool
    {
        $life = (int) ini_get('session.gc_maxlifetime') ?: 1440;
        $s = $this->pdo()->prepare('REPLACE INTO `vercel_sessions` (`id`, `data`, `expires`) VALUES (?, ?, ?)');
        return $s->execute([$id, $data, time() + $life]);
    }

    public function sessionDestroy(string $id): bool
    {
        return $this->pdo()->prepare('DELETE FROM `vercel_sessions` WHERE `id` = ?')->execute([$id]);
    }

    public function sessionGc(int $max): int
    {
        return $this->pdo()->prepare('DELETE FROM `vercel_sessions` WHERE `expires` < ?')->execute([time()]) ? 1 : 0;
    }

    /* ======================================================================
     * แปลง URL (แทน .htaccess)
     * ==================================================================== */
    public function handle(): void
    {
        $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = '/'.ltrim(rawurldecode($uri), '/');

        // TEMP: ช่องรับไฟล์ชั่วคราวสำหรับคัดลอกรูปจากระบบเดิม (จะลบออกหลังใช้งาน)
        if ($path === '/drop-temp') {
            $key = (string) getenv('SMS_SETUP_KEY');
            header('Access-Control-Allow-Origin: *');
            header('Content-Type: text/plain; charset=utf-8');
            $pdo = $this->pdo();
            $pdo->exec('CREATE TABLE IF NOT EXISTS `vercel_drop` (`p` VARCHAR(255) NOT NULL PRIMARY KEY, `d` LONGTEXT NOT NULL) ENGINE=InnoDB');
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $lines = explode("\n", (string) file_get_contents('php://input'));
                if ($key === '' || !hash_equals($key, trim((string) array_shift($lines)))) {
                    $this->deny(403);
                }
                $st = $pdo->prepare('REPLACE INTO `vercel_drop` (`p`,`d`) VALUES (?,?)');
                $n = 0;
                foreach ($lines as $l) {
                    $kv = explode("\t", $l, 2);
                    if (count($kv) === 2 && $kv[0] !== '') {
                        $st->execute([$kv[0], $kv[1]]);
                        $n++;
                    }
                }
                echo 'ok '.$n;
                exit;
            }
            if ($key === '' || !hash_equals($key, (string) ($_GET['key'] ?? ''))) {
                $this->deny(403);
            }
            $rows = $pdo->query('SELECT `p`,`d` FROM `vercel_drop` ORDER BY `p`')->fetchAll(\PDO::FETCH_NUM);
            $off = (int) ($_GET['from'] ?? 0);
            $max = (int) ($_GET['bytes'] ?? 400000);
            $out = '';
            $i = 0;
            foreach ($rows as $i => $r) {
                if ($i < $off) {
                    continue;
                }
                if ($out !== '' && strlen($out) + strlen($r[1]) > $max) {
                    break;
                }
                $out .= $r[0]."\t".$r[1]."\n";
                $i++;
            }
            echo 'NEXT '.$i.' OF '.count($rows)."\n".$out;
            exit;
        }

        // ไฟล์และโฟลเดอร์ที่ห้ามเปิดจากภายนอก
        if (strpos($path, '..') !== false
            || preg_match('#^/(settings|vercel)(/|$)|^/datas/(logs|cache)(/|$)|/\.|\.(md|sql|sqlite3?|log|bak|orig|dist|cache|gz|json\.lock)$#i', $path)
            || preg_match('#^/install/(sample|settings)(/|$)|^/install/cli-#', $path)
            || preg_match('#(^|/)(error_log|sql_log)\.php$#', $path)) {
            $this->deny(404);
        }

        // หลังติดตั้งแล้ว ปิดตัวติดตั้ง (เทียบเท่าการลบโฟลเดอร์ install/ ตามคู่มือ)
        // ถ้าต้องการปรับรุ่น ให้ตั้ง SMS_ALLOW_INSTALL=1 ชั่วคราว
        // ตรวจแบบเดียวกับ install/index.php (ขั้นตรวจโฟลเดอร์จะสร้าง config.php ว่างไว้ก่อน)
        $cfgFile = $this->root.'/settings/config.php';
        $installed = is_file($cfgFile) && filesize($cfgFile) > 0 && is_array(include $cfgFile);
        if (strpos($path, '/install') === 0) {
            if ($installed && getenv('SMS_ALLOW_INSTALL') !== '1') {
                $this->deny(403, 'ระบบติดตั้งเรียบร้อยแล้ว ตัวติดตั้งถูกปิดไว้เพื่อความปลอดภัย');
            }
            $this->checkSetupKey();
        }

        // API: /api/... -> api.php (เหมือน RewriteRule ^api/(.*)$ api.php/$1)
        if (preg_match('#^/api/(.*)$#', $path, $m)) {
            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
                $this->cors();
                http_response_code(200);
                exit;
            }
            $this->cors();
            $this->run('api.php', '/'.$m[1]);
            return;
        }

        $file = $this->root.$path;
        // ไฟล์ข้อมูลที่ระบบสร้าง (รูป เอกสาร) และคำแปล: ส่งจากโฟลเดอร์ที่ซิงก์แล้ว
        if (preg_match('#^/(datas|language)/#', $path)) {
            if (is_file($file) && substr($file, -4) !== '.php') {
                $this->sendFile($file);
            }
            $this->deny(404);
        }
        // โฟลเดอร์ที่มี index.php
        if (is_dir($file)) {
            if (substr($path, -1) !== '/') {
                $qs = ($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?'.$_SERVER['QUERY_STRING'] : '';
                header('Location: '.$path.'/'.$qs, true, 301);
                exit;
            }
            if (is_file($file.'index.php')) {
                $this->run(ltrim($path, '/').'index.php');
                return;
            }
            // โฟลเดอร์ที่มีแต่ index.html (เช่น /mis/)
            // (ไม่ใช้ sendFile เพราะ sendFile ใส่ CSP sandbox ให้ไฟล์ html ที่ผู้ใช้อัปโหลด)
            if (is_file($file.'index.html')) {
                header('Content-Type: text/html; charset=utf-8');
                header('X-Content-Type-Options: nosniff');
                header('X-Frame-Options: SAMEORIGIN');
                header('Cache-Control: no-cache');
                readfile($file.'index.html');
                exit;
            }
        }
        // ไฟล์ PHP ที่มีอยู่จริง (รองรับ PATH_INFO เช่น export.php/...)
        if (preg_match('#^/(.+?\.php)(/.*)?$#', $path, $m) && is_file($this->root.'/'.$m[1])) {
            $this->run($m[1], $m[2] ?? '');
            return;
        }
        // ไฟล์ static อื่นที่ Vercel ไม่ได้ส่ง (ปกติไม่เกิด)
        if (is_file($file)) {
            $this->sendFile($file);
        }
        // URL อื่น ๆ ที่มีนามสกุลไฟล์แต่ไม่มีอยู่จริง
        if (preg_match('#\.[a-z0-9]{1,5}$#i', $path)) {
            $this->deny(404);
        }
        // หน้าเว็บแบบ SPA: ทุก URL ที่เหลือใช้ index.php
        $this->run('index.php');
    }

    private function checkSetupKey(): void
    {
        $key = (string) getenv('SMS_SETUP_KEY');
        if ($key === '') {
            return;
        }
        if (isset($_GET['key']) && hash_equals($key, (string) $_GET['key'])) {
            setcookie('sms_setup', hash('sha256', $key), ['path' => '/install/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
            $_COOKIE['sms_setup'] = hash('sha256', $key);
        }
        if (!isset($_COOKIE['sms_setup']) || !hash_equals(hash('sha256', $key), (string) $_COOKIE['sms_setup'])) {
            $this->deny(403, 'ต้องเปิดตัวติดตั้งด้วยลิงก์ที่มีรหัสติดตั้ง (SMS_SETUP_KEY)');
        }
    }

    private function cors(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: origin, x-requested-with, content-type, authorization, x-api-token, x-access-token');
    }

    private function deny(int $code, string $message = ''): void
    {
        http_response_code($code);
        header('Content-Type: text/plain; charset=utf-8');
        echo $message !== '' ? $message : ($code === 404 ? '404 Not Found' : '403 Forbidden');
        exit;
    }

    private function sendFile(string $file): void
    {
        $types = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
            'svg' => 'image/svg+xml', 'ico' => 'image/x-icon', 'pdf' => 'application/pdf', 'json' => 'application/json; charset=utf-8',
            'css' => 'text/css', 'js' => 'application/javascript', 'txt' => 'text/plain; charset=utf-8', 'html' => 'text/html; charset=utf-8',
            'csv' => 'text/csv; charset=utf-8', 'zip' => 'application/zip', 'doc' => 'application/msword', 'xls' => 'application/vnd.ms-excel',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'
        ];
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $type = $types[$ext] ?? 'application/octet-stream';
        $mtime = filemtime($file);
        $etag = '"'.md5($file.$mtime.filesize($file)).'"';
        header('Content-Type: '.$type);
        header('X-Content-Type-Options: nosniff');
        header('ETag: '.$etag);
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', $mtime).' GMT');
        // ไฟล์เหล่านี้เปลี่ยนได้ตลอด (เช่น รูปโปรไฟล์ใหม่) ให้เบราว์เซอร์ตรวจกับเซิร์ฟเวอร์ทุกครั้ง
        header('Cache-Control: private, no-cache');
        if ($ext === 'svg' || $ext === 'html') {
            header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
        }
        if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            http_response_code(304);
            exit;
        }
        header('Content-Length: '.filesize($file));
        readfile($file);
        exit;
    }

    /** รันไฟล์ PHP ของ SMS โดยตั้ง $_SERVER ให้เหมือนรันบน Apache */
    private function run(string $script, string $pathInfo = ''): void
    {
        $abs = $this->root.'/'.$script;
        $_SERVER['DOCUMENT_ROOT'] = $this->root;
        $_SERVER['SCRIPT_FILENAME'] = $abs;
        $_SERVER['SCRIPT_NAME'] = '/'.$script;
        $_SERVER['PHP_SELF'] = '/'.$script.$pathInfo;
        if ($pathInfo !== '') {
            $_SERVER['PATH_INFO'] = $pathInfo;
        } else {
            unset($_SERVER['PATH_INFO']);
        }
        unset($_SERVER['CONTEXT_DOCUMENT_ROOT']);
        $this->installHints($script);
        chdir(dirname($abs));
        // ให้ตัวแปรของสคริปต์อยู่ใน scope ระดับบนสุดเหมือนรันตรง (บางไฟล์ใช้ global)
        $GLOBALS['__vercel_script'] = $abs;
    }

    /** ตัวติดตั้ง: ใช้ค่าฐานข้อมูลจาก Vercel แทนค่าที่กรอกในฟอร์ม */
    private function installHints(string $script): void
    {
        if ($script !== 'install/index.php' || $this->db['host'] === '') {
            return;
        }
        if (($_POST['step'] ?? '') === '4') {
            $_POST['dbdriver'] = 'mysql';
            $_POST['db_username'] = $this->db['user'];
            $_POST['db_password'] = $this->db['pass'];
            $_POST['db_server'] = $this->db['host'];
            $_POST['db_port'] = (string) $this->db['port'];
            $_POST['db_name'] = $this->db['name'];
            $_POST['prefix'] = $this->db['prefix'];
        }
        $note = '<div style="margin:0 0 16px;padding:12px 14px;border-radius:8px;background:#eef6ff;border:1px solid #bcd7f5;color:#1d3f63">'
            .'<b>ติดตั้งบน Vercel:</b> ระบบใช้ค่าเชื่อมต่อฐานข้อมูลจาก Vercel ให้อัตโนมัติ ช่องด้านล่างไม่ต้องแก้ กด “ติดตั้ง” ได้เลย</div>';
        ob_start(function ($html) use ($note) {
            return str_replace('<h2>ค่ากำหนดของฐานข้อมูล</h2>', '<h2>ค่ากำหนดของฐานข้อมูล</h2>'.$note, $html);
        });
    }

    /* ======================================================================
     * จุดเริ่ม
     * ==================================================================== */
    public static function main(): void
    {
        // ที่อยู่ IP จริงของผู้ใช้ (Vercel ส่งมาใน x-real-ip) ใช้กับระบบกันเดารหัสผ่าน
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_REAL_IP'];
        }
        // พอร์ตและโปรโตคอลจริง (PHP ใน Vercel รันหลัง proxy)
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $https = $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https';
            $_SERVER['SERVER_PORT'] = $https ? 443 : 80;
            if ($https) {
                $_SERVER['HTTPS'] = 'on';
            }
        }
        // Vercel ไม่มีส่วนขยาย gd: ใช้ตัวแทนที่ทำงานผ่าน FFI (ย่อ/ครอป/แปลงรูป)
        if (!extension_loaded('gd') && class_exists('FFI') && is_file(__DIR__.'/gd/libsmsimg.so')) {
            require __DIR__.'/gd/polyfill.php';
        }
        $app = new self();
        try {
            $app->boot();
        } catch (\Throwable $e) {
            error_log('[vercel] boot: '.$e->getMessage());
            http_response_code(503);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
                .'<title>ระบบยังไม่พร้อม</title><div style="font:16px/1.7 sans-serif;max-width:560px;margin:15vh auto;padding:0 16px">'
                .'<h1 style="font-size:22px">ระบบยังเชื่อมต่อฐานข้อมูลไม่ได้</h1>'
                .'<p>ตรวจว่าเชื่อมฐานข้อมูลกับโปรเจกต์บน Vercel แล้ว (แท็บ Storage) จากนั้นสั่ง Redeploy</p>'
                .'<p style="color:#666;font-size:14px">'.htmlspecialchars($e->getMessage(), ENT_QUOTES).'</p></div>';
            exit;
        }
        $app->registerSessions();
        register_shutdown_function(function () use ($app) {
            // ลงทะเบียนซ้อนเพื่อให้ทำงานหลัง shutdown function ของ SMS เอง
            register_shutdown_function(function () use ($app) {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_write_close();
                }
                try {
                    $app->push();
                } catch (\Throwable $e) {
                    error_log('[vercel] push: '.$e->getMessage());
                }
            });
        });
        $app->handle();
    }
}
