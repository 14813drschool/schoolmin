<?php
/**
 * mis/app/admin.php
 * หน้าผู้ดูแลระบบ (แทนหน้าจัดการของ SMS): 6 โมดูล
 * ทะเบียนและข้อมูลหลัก / บริหารงานวิชาการ / บริหารงานกิจการนักเรียน / บริหารงานบุคลากร / บริหารงานทั่วไป / การตั้งค่า
 * URL: ?module=admin&option=<ชื่อเมนู>[&func=...]
 */
defined('MIS') or exit;

const MODULE_KEYS = ['main', 'academic', 'affairs', 'personnel', 'general', 'finance', 'settings'];

/** โมดูลที่ผู้ใช้มีสิทธิ์ (ผู้ดูแลระบบสูงสุด = ทุกโมดูล; ที่กำหนดในหน้ากำหนดผู้ดูแลระบบ; ถ้ายังไม่กำหนดใช้สิทธิ์เดิมของ SMS) */
function user_modules(?array $u = null): array
{
    static $cache = [];
    $u = $u ?? me();
    if (!$u) {
        return [];
    }
    $id = (int) $u['id'];
    if (isset($cache[$id])) {
        return $cache[$id];
    }
    if ((int) $u['status'] === 1) {
        return $cache[$id] = MODULE_KEYS;
    }
    $v = md_get('mod_perm', $id);
    if ($v !== null) {
        return $cache[$id] = array_values(array_intersect(MODULE_KEYS, json_decode($v, true) ?: []));
    }
    $p = is_array($u['permission'] ?? null) ? $u['permission'] : array_filter(explode(',', trim((string) ($u['permission'] ?? ''), ',')));
    if (in_array('can_config', $p, true)) {
        return $cache[$id] = MODULE_KEYS;
    }
    $m = [];
    if (in_array('can_manage_student', $p, true)) {
        array_push($m, 'main', 'affairs');
    }
    if (in_array('can_manage_course', $p, true)) {
        $m[] = 'academic';
    }
    if (in_array('can_manage_personnel', $p, true)) {
        $m[] = 'personnel';
    }
    return $cache[$id] = array_values(array_intersect(MODULE_KEYS, $m));
}

function is_admin(): bool
{
    return (bool) user_modules();
}

/** เมนูของแต่ละโมดูล: [ชื่อ, ไอคอน, [หัวข้อ => [option => ชื่อเมนู]], ชื่อบนแถบเมนู] */
function admin_modules(): array
{
    static $m = null;
    return $m ??= [
        'main' => ['ทะเบียนและข้อมูลหลัก', 'main', main_menu()],
        'academic' => ['บริหารงานวิชาการ', 'academic', academic_menu()],
        'affairs' => ['บริหารงานกิจการนักเรียน', 'affairs', affairs_menu()],
        'personnel' => ['บริหารงานบุคลากร', 'personnel', personnel_menu()],
        'general' => ['บริหารงานทั่วไป', 'general', general_menu()],
        'finance' => ['รับชำระเงินค่าบำรุงการศึกษา', 'finance', finance_menu(), 'รับชำระเงิน'],
        'settings' => ['การตั้งค่า', 'settings', [
            'กำหนดค่าเริ่มต้นของระบบ' => ['school' => 'ข้อมูลโรงเรียน', 'admins' => 'กำหนดผู้ดูแลระบบและสิทธิ์โมดูล', 'csv_import' => 'นำเข้าข้อมูลจากไฟล์ CSV', 'users' => 'บัญชีผู้ใช้', 'password' => 'เปลี่ยนรหัสผ่าน', 'teacher_view' => 'ไปหน้าครูผู้สอน'],
        ]],
    ];
}

/** โมดูลทั้งหมดที่มีเมนูนี้ */
function option_modules(string $option): array
{
    $out = [];
    foreach (admin_modules() as $mk => [$mname, $icon, $sections]) {
        foreach ($sections as $items) {
            if (isset($items[$option])) {
                $out[] = $mk;
                break;
            }
        }
    }
    return $out;
}

function admin_option_info(string $option, string $prefer = ''): array
{
    $mods = admin_modules();
    $order = $prefer !== '' && isset($mods[$prefer]) ? [$prefer => $mods[$prefer]] + $mods : $mods;
    foreach ($order as $mk => [$mname, $icon, $sections]) {
        foreach ($sections as $sec => $items) {
            if (isset($items[$option])) {
                return [$mk, $mname, $sec, is_array($items[$option]) ? $items[$option][0] : $items[$option]];
            }
        }
    }
    return ['', '', '', ''];
}

/** หน้าแจ้งไม่มีสิทธิ์ */
function denied_box(string $module = ''): string
{
    $name = $module !== '' ? (admin_modules()[$module][0] ?? '') : '';
    return '<div style="width:983px;margin:60px auto;text-align:center;border:1px solid #F5B7B1;border-radius:8px;background:#FDEDEC;padding:40px 0">'
        .'<img src="../images/001_11.png" style="width:48px"/><h1 style="font-family:thaisansbold;font-weight:normal;font-size:38px;color:#C0392B;margin:10px 0">ไม่มีสิทธิ์เข้าใช้งานระบบ</h1>'
        .'<p style="font-size:14px;color:#555">'.($name !== '' ? 'บัญชีของคุณไม่ได้รับสิทธิ์ใช้งานโมดูล "'.h($name).'"<br>' : '').'กรุณาติดต่อผู้ดูแลระบบเพื่อขอสิทธิ์การใช้งาน</p>'
        .'<p style="margin-top:15px"><a href="index.php" id="pinkgreenbtns"><img src="../icon_tools/vec_x_white.png" height="14"/> กลับหน้าหลัก</a></p></div>';
}

/* ================================================================ หน้าตา */

function admin_top(): string
{
    $mods = admin_modules();
    $nav = '';
    $panels = '';
    $i = 0;
    $n = count($mods) - 1;
    $w = (int) floor((983 - 5 * ($n - 1)) / $n);
    foreach ($mods as $mk => $m) {
        [$name, $icon, $sections] = $m;
        if ($mk === 'settings') {
            continue;
        }
        $nav .= '<li id="am'.$i.'" onclick="adminMenu('.$i.')" style="margin-right:'.($i < $n - 1 ? '5' : '0').'px"><a href="javascript:void(0);" class="adm-nav" style="width:'.$w.'px">'.h($m[3] ?? $name).'</a></li>';
        $cols = '';
        foreach ($sections as $sec => $items) {
            $links = '';
            $cols .= menu_col($sec, $items);
        }
        $panels .= '<div class="adm-panel" id="ap'.$i.'" style="display:none">'.$cols.'<div style="clear:both"></div></div>';
        $i++;
    }
    return $nav.'|'.$panels;
}

/** ไอคอนเล็กของเมนู (ตามคำในชื่อเมนู) */
function menu_icon(string $text): array
{
    static $map = [
        'เลื่อนชั้น' => ['fa-level-up', '#16A085'], 'ยังไม่กำหนดห้อง' => ['fa-question-circle', '#E67E22'], 'การเข้าเรียน' => ['fa-sign-in', '#16A085'], 'ใช้ห้องเรียน' => ['fa-table', '#2980B9'],
        'ผู้ดูแล' => ['fa-cog', '#E67E22'], 'สิทธิ์' => ['fa-key', '#F1C40F'], 'ปีงบ' => ['fa-money', '#27AE60'], 'ปีการศึกษา' => ['fa-globe', '#2980B9'], 'ภาคเรียน' => ['fa-calendar', '#C0392B'],
        'ระดับการศึกษา' => ['fa-graduation-cap', '#8E44AD'], 'ระดับชั้น' => ['fa-signal', '#2980B9'], 'ห้อง' => ['fa-building', '#16A085'], 'แผนการเรียน' => ['fa-book', '#27AE60'],
        'ที่พัก' => ['fa-home', '#C0392B'], 'เดินทาง' => ['fa-bus', '#E67E22'], 'บัตร' => ['fa-id-card-o', '#2C3E50'], 'ผู้บริหาร' => ['fa-user-secret', '#2C3E50'],
        'คุณสมบัติ' => ['fa-lightbulb-o', '#F39C12'], 'สถานภาพ' => ['fa-briefcase', '#8E5A2B'], 'สถานะ' => ['fa-tags', '#8E44AD'], 'บุคลากร' => ['fa-users', '#C0392B'],
        'สถิติ' => ['fa-line-chart', '#C0392B'], 'จัดการชั้นเรียน' => ['fa-th', '#2980B9'], 'จัดชั้น' => ['fa-th', '#2980B9'], 'เลื่อนชั้น' => ['fa-level-up', '#16A085'], 'นำเข้า' => ['fa-upload', '#27AE60'],
        'โอน' => ['fa-exchange', '#27AE60'], 'ผู้ปกครอง' => ['fa-child', '#D35400'], 'ใบเสร็จ' => ['fa-file-text-o', '#16A085'], 'ใบแจ้ง' => ['fa-file-o', '#E67E22'],
        'ชำระ' => ['fa-money', '#27AE60'], 'ค่าบำรุง' => ['fa-money', '#27AE60'], 'ตาราง' => ['fa-table', '#2980B9'], 'ปฏิทิน' => ['fa-calendar', '#C0392B'], 'คาบ' => ['fa-clock-o', '#E67E22'],
        'วันเรียน' => ['fa-calendar-check-o', '#16A085'], 'เกณฑ์' => ['fa-check-square-o', '#8E44AD'], 'ระดับผล' => ['fa-sort-numeric-desc', '#C0392B'], 'ปพ.' => ['fa-print', '#2C3E50'],
        'พิมพ์' => ['fa-print', '#2C3E50'], 'ผลสอบ' => ['fa-bullhorn', '#E67E22'], 'แก้ตัว' => ['fa-repeat', '#E74C3C'], 'ท็อป' => ['fa-trophy', '#F1C40F'], 'GPA' => ['fa-sort-amount-desc', '#2980B9'],
        'ติด 0' => ['fa-exclamation-circle', '#E74C3C'], 'ไม่มีสิทธิ์' => ['fa-ban', '#E74C3C'], '80%' => ['fa-exclamation-triangle', '#E74C3C'], 'สมรรถนะ' => ['fa-star', '#F39C12'],
        'พฤติกรรม' => ['fa-heart', '#E74C3C'], 'วิเคราะห์' => ['fa-pie-chart', '#E67E22'], 'ความพร้อม' => ['fa-child', '#16A085'], 'ขาดเรียน' => ['fa-user-times', '#C0392B'],
        'มาสาย' => ['fa-clock-o', '#E67E22'], 'หนังสือ' => ['fa-envelope', '#2C3E50'], 'กิจกรรม' => ['fa-flag', '#27AE60'], 'เข้าชั้นเรียน' => ['fa-check', '#27AE60'],
        'ลงเวลา' => ['fa-clock-o', '#2980B9'], 'ราชการ' => ['fa-car', '#16A085'], 'การลา' => ['fa-bed', '#8E44AD'], 'ลาราชการ' => ['fa-bed', '#8E44AD'], 'ออกนอก' => ['fa-sign-out', '#E67E22'],
        'มาตรฐาน' => ['fa-certificate', '#F39C12'], 'หน้าปก' => ['fa-picture-o', '#16A085'], 'SAR' => ['fa-book', '#2C3E50'], 'SSR' => ['fa-user', '#2980B9'], 'ประเมิน' => ['fa-check-circle', '#27AE60'],
        'ข่าว' => ['fa-newspaper-o', '#E67E22'], 'เอกสาร' => ['fa-folder-open', '#F39C12'], 'ประวัติ' => ['fa-history', '#7F8C8D'], 'รหัสผ่าน' => ['fa-lock', '#C0392B'], 'บัญชี' => ['fa-user-circle', '#2980B9'],
        'ครูผู้สอน' => ['fa-share', '#16A085'], 'โรงเรียน' => ['fa-university', '#2980B9'], 'รายวิชา' => ['fa-book', '#2980B9'], 'หลักสูตร' => ['fa-sitemap', '#16A085'], 'กลุ่มสาระ' => ['fa-cubes', '#8E44AD'],
        'ลงทะเบียน' => ['fa-pencil-square-o', '#27AE60'], 'คัดลอก' => ['fa-copy', '#7F8C8D'], 'อนุมัติ' => ['fa-thumbs-up', '#27AE60'], 'คุณลักษณะ' => ['fa-heart', '#E74C3C'],
        'การอ่าน' => ['fa-book', '#E67E22'], 'เพื่อน' => ['fa-handshake-o', '#16A085'], 'ทุน' => ['fa-gift', '#F39C12'], 'ตรวจสอบ' => ['fa-search', '#2980B9'], 'สรุป' => ['fa-list-alt', '#2980B9'],
        'รายงาน' => ['fa-list-alt', '#2980B9'], 'นักเรียน' => ['fa-user', '#2980B9'],
    ];
    foreach ($map as $k => $v) {
        if (mb_strpos($text, $k) !== false) {
            return $v;
        }
    }
    return ['fa-caret-right', '#0883DC'];
}

/** คอลัมน์เมนู 1 หมวด */
function menu_col(string $sec, array $items): string
{
    $links = '';
    foreach ($items as $op => $label) {
        [$text, $ic, $col] = is_array($label) ? $label : array_merge([$label], menu_icon($label));
        $links .= '<a class="waiting_link" href="?module=admin&option='.$op.'"><i class="fa '.$ic.'" style="width:18px;text-align:center;color:'.$col.'"></i> '.h($text).'</a>';
    }
    return '<div class="adm-col"><h3>'.h($sec).'</h3>'.$links.'</div>';
}

function admin_css(): string
{
    return '<style>
#nav li a.adm-nav{padding:6px 0;width:190px;text-align:center;font-family:thaisansbold;font-size:19px;color:#fff;white-space:nowrap;overflow:hidden}
#nav li.selected{background-color:#00F0FF}#nav li.selected a.adm-nav{color:#000}
.adm-panel{width:978px;border:3px solid #A9F4F9;border-radius:8px;background:#fff;margin:4px 0 10px;padding:10px 0;text-align:left;position:absolute;z-index:21}
.adm-panel{background:linear-gradient(#fff 70%,#E9FBFF)}.adm-col{float:left;width:276px;margin:0 0 14px 24px;padding-right:20px;border-right:1px solid #eef5f8;min-height:120px}.adm-col:nth-child(3n){border-right:none}.adm-col:nth-child(3n+1){clear:left}
.adm-col h3{font-family:thaisansbold;font-weight:normal;font-size:30px;color:#0F5A9C;margin:4px 0 6px}
.adm-col a{display:block;font-size:12px;padding:4px 0;border-bottom:dashed 1px #ccc;color:#333}
.adm-box{width:981px;margin:10px auto 0;border:1px solid #ddd;border-radius:6px;background:#fff;text-align:left;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.adm-box .hd{display:flex;align-items:center;gap:12px;padding:10px 20px;border-bottom:1px solid #e5e5e5;background:#fafafa;border-radius:6px 6px 0 0}.adm-box .hd img{width:58px;height:58px}
.adm-box .hd h1{font-family:thaisansbold;font-weight:normal;font-size:40px;color:#0F5A9C;margin:0;flex:1}.adm-box .hd a.x{font-size:22px;color:#999;text-decoration:none}.adm-box .bd{padding:14px 0 4px}
.adm-tile a{cursor:pointer}.adm-tile.on a{border:2px solid #E74C3C;box-shadow:0 0 10px #F5B7B1}
.adm-tiles{width:984px;margin:30px auto;text-align:center}.adm-tile{display:inline-block;width:235px;margin:10px 0 30px;vertical-align:top}
.adm-tile h2{font-family:thaisansbold;font-size:24px;color:#019DF1;margin:0 0 8px}.adm-tile a{display:inline-block;width:150px;height:140px;border:1px solid #C9EFFB;border-radius:8px;background:#fff}
.adm-tile a:hover{box-shadow:0 0 10px #C9EFFB}.adm-tile.off a{opacity:.35;filter:grayscale(1)}.adm-tile.off h2{color:#aaa}.adm-tile img{width:116px;height:116px;margin-top:12px}
.adm-form td.titleinputfill{width:200px}.adm-news{width:983px;margin:0 auto 10px;text-align:left;border:1px solid #c6d5e1;border-radius:4px;background:#FFFDF2;padding:8px 12px}
</style>';
}

/** หัวหน้า (แถบชื่อ + กล่องชื่อหน้า) แบบระบบเดิม */
function admin_head(string $crumb1, string $crumb2, string $title, string $sub, string $buttons = ''): string
{
    return '<div style="padding-top:5px"><div id="page_topic_blue" style="width:967px"><div style="float:left"><h1 class=\'title_26\' style=\'font-size:21px;float:left;white-space:nowrap\'>&nbsp;'.h($crumb1)
        .'&nbsp;<img src="../images/white_br_next.png" border="0" style="width:5px"/>&nbsp;'.h($crumb2).'</h1></div><div style="float:right">'
        .'<span style="float:left;margin-top:3px;padding:5px 10px;background-image:url(../icon_tools/menubar_year.png); background-repeat:no-repeat;width:163px;height:22px;font-size:11px;font-weight:normal">แสดงข้อมูล ปีการศึกษา '.yt().'</span>'
        .'<span style="float:left;margin-top:3px;padding:0px 2px"><a href="index.php"><img src="../icon_tools/menubar_dashboard.png" border="0" alt="แผงเมนูหลัก" title="แผงเมนูหลัก"/></a></span>'
        .(in_array('settings', user_modules(), true) ? '<span style="float:left;margin-top:3px;padding:0px 2px"><a href="'.h(a_url('school')).'" title="การตั้งค่า"><img src="../icon_tools/icon_setting.png" border="0" alt="การตั้งค่า" style="height:22px"/></a></span>' : '').'</div></div>'
        .'<div id="maindetail" style="width:965px"><table border="0" cellpadding="0" cellspacing="0" style="margin-top:3px;width:967px"><tr><td align="left" valign="bottom" style="width:70px;white-space:nowrap">&nbsp;<img src="../icon_tools/icon_main.png"/></td>'
        .'<td align="left" valign="bottom" style="padding-left:5px;white-space:nowrap"><table border="0" cellspacing="0" cellpadding="0"><tr><td style="white-space:nowrap"><h1 class="header_subtitle" style="font-size:25px;white-space:nowrap">'.h($title).'</h1></td></tr>'
        .'<tr><td style="white-space:nowrap"><h1 class="blue_secondtitle" style="font-size:23px;white-space:nowrap">'.h($sub).'</h1></td></tr></table></td>'
        .'<td align="right" valign="middle" style="white-space:nowrap">'.$buttons.'</td></tr></table></div></div>'
        .'<div style="margin-top:10px;min-height:750px;z-index:100"><link rel="stylesheet" type="text/css" href="../css/tiny_style.css">';
}

function head_btn(string $label, string $href, string $icon): string
{
    $fa = ['reload' => ['fa-refresh', '#27AE60'], 'download' => ['fa-download', '#E67E22'], 'upload' => ['fa-upload', '#27AE60'], 'x' => ['fa-arrow-circle-left', '#2980B9']][$icon] ?? null;
    $img = $fa ? '<i class="fa '.$fa[0].'" style="font-size:24px;color:'.$fa[1].';margin-top:4px;display:inline-block"></i>' : '<img src="../icon_tools/vec_'.$icon.'_dark.png" height="24" style="margin-top:4px"/>';
    return '<div style="float:left;margin:0px 2px"><a href="'.$href.'" title="'.h($label).'" style="display:inline-block;width:58px;height:56px;border:1px solid #ddd;border-radius:6px;background:#fafafa;text-align:center;font-size:11px;color:#555;padding-top:4px">'
        .$img.'<br>'.h($label).'</a></div>';
}

/** ฟอร์มแบบเดียวกับหน้าแก้ไขข้อมูลส่วนตัว: $rows = [[label, html, required], ...] */
function admin_form(string $title, string $action, array $rows, string $back): string
{
    $tr = '';
    foreach ($rows as [$label, $html, $req]) {
        $tr .= '<tr><td valign="top" class="titleinputfill" style="padding:6px 10px">'.h($label).' <span class="'.($req ? 'warningfiilsign">*<span style=\'color:#000\'>:</span>' : 'nowarningfiilsign">:').'</span></td><td valign="top" class="columninputfill" style="padding:6px 0">'.$html.'</td></tr>';
    }
    return '<div class="form_content"><form id="operationform" name="operationform" method="post" action="'.h($action).'" enctype="multipart/form-data" style="width:964px;margin:0 auto">'
        .'<div class="formframeheader" style="width:970px"><div class="content_edit"><h1 style="margin-left:25px;margin-top:-3px">'.h($title).'</h1></div></div>'
        .'<div class="formframe" id="formframe" style="width:970px"><table width="900" border="0" cellpadding="0" cellspacing="0" class="black_12 adm-form" style="margin:10px 20px">'.$tr.'</table></div>'
        .'<div class="formframefooter" style="width:970px"><div style="padding-top:4px;float:right;padding-right:5px">'
        .'<span style="float:left;width:70px"><a href="javascript:void(0);" onclick="document.forms.operationform.submit();" id="Savebtns"><img src="../icon_tools/vec_add_white.png" border="0" height="18" style="margin-top:-5px"/> บันทึก</a></span>'
        .'<span style="margin-left:3px;float:left;width:65px"><a href="'.h($back).'" id="Clearbtns"><img src="../icon_tools/vec_x_white.png" border="0" height="14" style="margin-top:-5px"/> กลับ</a></span></div></div></form></div>';
}

function inp(string $name, $value, int $w = 250, string $type = 'text', string $extra = ''): string
{
    return '<input name="'.$name.'" type="'.$type.'" value="'.h($value).'" class="general radius grayborder borderleftthick_gray" style="width:'.$w.'px;height:20px" '.$extra.'/>';
}

function txt(string $name, $value, int $w = 600): string
{
    return '<textarea name="'.$name.'" class="general radius grayborder" style="width:'.$w.'px;height:90px">'.h($value).'</textarea>';
}

function del_link(string $href): string
{
    return '<a href="'.$href.'" onclick="return confirm(\'ยืนยันการลบ?\')" class="icontooltip" title="ลบ"><img src="../icon_tools/vec_x_dark.png" onerror="this.src=\'../icon_tools/vec_bin_disable.png\'" height="16"/></a>';
}

function edit_link(string $href): string
{
    return '<a href="'.$href.'" class="icontooltip" title="แก้ไข"><img src="../icon_tools/vec_pencil_dark.png" height="18"/></a>';
}

function a_url(string $option, string $extra = ''): string
{
    return '?module=admin&option='.$option.$extra;
}

function admin_done(string $option, string $extra = ''): void
{
    redirect(a_url($option, $extra.'&msg=saved'));
}

/* ================================================================ ตัวควบคุม */

function run_admin(string $option, string $func): void
{
    if (!is_admin()) {
        render_page('ไม่มีสิทธิ์เข้าใช้งานระบบ', denied_box(), '');
        return;
    }
    if ($option === 'teacher_view') {
        setcookie('mis_teacher_view', '1', ['path' => '/mis/', 'samesite' => 'Lax']);
        redirect('index.php');
    }
    $prefer = '';
    if ($option !== '' && $option !== 'password') {
        $mods = option_modules($option);
        $mine = user_modules();
        $ok = array_values(array_intersect($mods, $mine));
        // เมนูที่ไม่อยู่ในโมดูลใด ให้เฉพาะผู้มีสิทธิ์การตั้งค่า
        if (($mods && !$ok) || (!$mods && !in_array('settings', $mine, true))) {
            render_admin('ไม่มีสิทธิ์เข้าใช้งานระบบ', denied_box($mods[0] ?? ''), $mods[0] ?? '');
            return;
        }
        $prefer = $ok[0] ?? '';
        // ข้อมูลโรงเรียนเป็นเมนูของ "การตั้งค่า" (เมนูรับชำระเงินใช้หน้าเดียวกัน)
        if ($option === 'school' && in_array('settings', $ok, true)) {
            $prefer = 'settings';
        }
        $links = admin_links();
        if (isset($links[$option])) {
            redirect($links[$option]);
        }
    }
    $post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    $fn = 'adm_'.$option.($func !== '' ? '_'.$func : '');
    if (!function_exists($fn)) {
        $fn = 'adm_'.$option;
    }
    if ($option === '' || !function_exists($fn)) {
        render_admin('แผงเมนูหลัก', admin_home(), '');
        return;
    }
    [$mk, $mname, $sec, $label] = admin_option_info($option, $prefer);
    $body = $fn($post);
    if ($body === null) {
        return;
    }
    if (is_array($body)) {
        [$sub, $html, $buttons] = $body + ['', '', ''];
    } else {
        [$sub, $html, $buttons] = [$label, $body, ''];
    }
    render_admin($label, admin_head($mname, $sec, $label, $sub ?: $label, $buttons).saved_msg().$html.'</div>', $mk);
}

function render_admin(string $title, string $content, string $module): void
{
    [$nav, $panels] = explode('|', admin_top(), 2);
    $mods = array_keys(admin_modules());
    $idx = array_search($module, $mods, true);
    $nmod = count($mods) - 1;
    if ($idx !== false && $idx < $nmod) {
        $nav = str_replace('<li id="am'.$idx.'"', '<li class="selected" id="am'.$idx.'"', $nav);
    }
    $top = tpl('layout_top.html');
    // เปลี่ยนแถบเมนู 3 ปุ่มของครู เป็น 5 ปุ่มของผู้ดูแลระบบ
    $u = strpos($top, '<ul>', (int) stripos($top, 'id="nav"'));
    $ue = strpos($top, '</ul>', (int) $u);
    if ($u !== false && $ue !== false) {
        $top = substr($top, 0, $u + 4).$nav.substr($top, $ue);
    }
    $a = strpos($top, '<div id="sbmenu00"');
    $b = strpos($top, '<script src="../scripts/drop_Menu.js"');
    if ($a !== false && $b !== false) {
        $p = $b;
        for ($k = 0; $k < 3; $k++) {
            $p = strrpos(substr($top, 0, $p), '</DIV>');
        }
        $top = substr($top, 0, $a).$panels.substr($top, $p);
    }
    $js = '<script type="text/javascript">function adminMenu(i){for(var k=0;k<'.$nmod.';k++){var p=document.getElementById("ap"+k);if(p){p.style.display=(k===i&&p.style.display==="none")?"block":"none";}}}'
        .'document.addEventListener("click",function(e){if(!e.target.closest||(!e.target.closest("#nav")&&!e.target.closest(".adm-panel"))){for(var k=0;k<'.$nmod.';k++){var p=document.getElementById("ap"+k);if(p)p.style.display="none";}}});</script>';
    $GLOBALS['mis_top_override'] = $top;
    render_page($title, admin_css().$js.$content, '');
}

function admin_home(): string
{
    $news = admin_news_box();
    $tiles = '';
    $boxes = '';
    $mine = user_modules();
    foreach (admin_modules() as $mk => [$name, $icon, $sections]) {
        $cols = '';
        foreach ($sections as $sec => $items) {
            $cols .= menu_col($sec, $items);
        }
        $boxes .= '<div class="adm-box" id="hb_'.$mk.'" style="display:none"><div class="hd"><img src="../images/admin/'.$icon.'.svg" alt=""/><h1>'.h($name).'</h1>'
            .'<a class="x" href="javascript:void(0)" onclick="homeBox(\'\')" title="ปิด">&times;</a></div><div class="bd">'.$cols.'<div style="clear:both"></div></div></div>';
        $tiles .= '<div class="adm-tile'.(in_array($mk, $mine, true) ? '' : ' off').'" id="ht_'.$mk.'"><h2>'.h($name).'</h2><a onclick="homeBox(\''.$mk.'\')"><img src="../images/admin/'.$icon.'.svg" alt=""/></a></div>';
    }
    $tiles .= '<div class="adm-tile"><h2>ออกจากระบบ</h2><a href="../logout.php"><img src="../images/admin/logout.svg" alt=""/></a></div>';
    $js = '<script>function homeBox(m){var b=document.querySelectorAll(".adm-box"),t=document.querySelectorAll(".adm-tile");for(var i=0;i<b.length;i++){b[i].style.display=b[i].id==="hb_"+m?"block":"none";}'
        .'for(var j=0;j<t.length;j++){t[j].classList.toggle("on",t[j].id==="ht_"+m);}try{sessionStorage.setItem("misHome",m);}catch(e){}if(m){window.scrollTo(0,0);}}'
        .'(function(){var m="'.preg_replace('/[^a-z]/', '', (string) ($_GET['home'] ?? '')).'";if(!m){try{m=sessionStorage.getItem("misHome")||"";}catch(e){}}if(m&&document.getElementById("hb_"+m)){homeBox(m);}})();</script>';
    return '<div style="padding-top:5px">'.$boxes.$news.'<div class="adm-tiles">'.$tiles.'</div></div>'.$js;
}

function admin_news_box(): string
{
    $rows = array_slice(array_reverse(md_rows('news')), 0, 3);
    if (!$rows) {
        return '';
    }
    $o = '<div class="adm-news"><b style="font-family:thaisansbold;font-size:20px;color:#E8AE00">ข่าวประกาศ</b>';
    foreach ($rows as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        $o .= '<div style="padding:4px 0;border-top:dashed 1px #eee"><b>'.h($d['title'] ?? '').'</b> <span class="gray_12">('.h(th_date(substr((string) $r['updated_at'], 0, 10), ' ')).')</span><br>'.nl2br(h($d['body'] ?? '')).'</div>';
    }
    return $o.'</div>';
}

/* ================================================================ หมวดหมู่ (ระดับชั้น ห้อง ตำแหน่ง กลุ่มสาระ) */

function cat_save(string $type, ?int $id, string $topic): void
{
    if ($id === null) {
        $id = (int) (q1('SELECT MAX(CAST(`category_id` AS UNSIGNED)) m FROM {category} WHERE `type`=?', [$type])['m'] ?? 0) + 1;
    }
    $exists = q1('SELECT 1 x FROM {category} WHERE `type`=? AND `category_id`=? LIMIT 1', [$type, (string) $id]);
    if ($exists) {
        q('UPDATE {category} SET `topic`=? WHERE `type`=? AND `category_id`=? AND `language` IN (\'th\', \'\')', [$topic, $type, (string) $id]);
        if (!q1('SELECT 1 x FROM {category} WHERE `type`=? AND `category_id`=? AND `language` IN (\'th\', \'\') LIMIT 1', [$type, (string) $id])) {
            q('INSERT INTO {category} (`type`,`category_id`,`language`,`topic`,`is_active`) VALUES (?,?,?,?,1)', [$type, (string) $id, 'th', $topic]);
        }
    } else {
        q('INSERT INTO {category} (`type`,`category_id`,`language`,`topic`,`is_active`) VALUES (?,?,?,?,1)', [$type, (string) $id, 'th', $topic]);
    }
}

/** หน้าจัดการหมวดหมู่แบบทั่วไป */
function cat_page(string $type, string $option, string $label, bool $post, callable $extraCols = null, array $extraHead = []): array|string|null
{
    $func = $_GET['func'] ?? '';
    if ($post) {
        $topic = trim((string) ($_POST['topic'] ?? ''));
        if ($topic !== '') {
            cat_save($type, isset($_GET['id']) && $_GET['id'] !== '' ? (int) $_GET['id'] : null, mb_substr($topic, 0, 150));
        }
        admin_done($option);
    }
    if (isset($_GET['del'])) {
        q('DELETE FROM {category} WHERE `type`=? AND `category_id`=?', [$type, (string) (int) $_GET['del']]);
        admin_done($option);
    }
    $editId = $_GET['edit'] ?? null;
    $rows = '';
    $i = 0;
    foreach (cats($type) as $id => $name) {
        $cells = [++$i, h($name)];
        if ($extraCols) {
            $cells = array_merge($cells, $extraCols($id, $name));
        }
        $cells[] = [edit_link(a_url($option, '&edit='.$id)).' '.del_link(a_url($option, '&del='.$id)), ' align="center" width="60"'];
        $rows .= zrow($cells, $i);
    }
    $form = '<form method="post" action="'.h(a_url($option, $editId !== null ? '&id='.(int) $editId : '')).'" style="width:983px;margin:0 auto 10px;text-align:left"><span class="search">'
        .($editId !== null ? 'แก้ไข ' : 'เพิ่ม'.h($label).' ').inp('topic', $editId !== null ? cat($type, $editId) : '', 300, 'text', 'placeholder="ชื่อ'.h($label).'"')
        .' <input type="submit" value="'.($editId !== null ? 'บันทึก' : 'เพิ่ม').'" class="general radius" style="height:25px"/>'.($editId !== null ? ' <a href="'.a_url($option).'">ยกเลิก</a>' : '').'</span><div style="clear:both"></div></form>';
    return $form.list_table('รายการ'.h($label), array_merge(['#', $label], $extraHead, [['', ' style="width:60px"']]), $rows);
}

function adm_room(bool $post) { return cat_page('room', 'room', 'ห้องเรียน', $post, fn($id) => ['รหัส '.h($id)], ['รหัสห้อง']) . '<p style="width:983px;margin:0 auto;text-align:left" class="gray_12">ตั้งชื่อห้องเป็น "เลขชั้น/เลขห้อง" เช่น 1/1, 1/2 ระบบจะจัดห้องเข้าระดับชั้นลำดับที่ 1 ให้อัตโนมัติ</p>'; }
function adm_position(bool $post) { return cat_page('position', 'position', 'ตำแหน่ง', $post); }
function adm_department(bool $post) { return cat_page('department', 'department', 'กลุ่มงาน/กลุ่มสาระ', $post); }
function adm_sara(bool $post) { return cat_page('department', 'sara', 'กลุ่มสาระการเรียนรู้', $post); }

/* ================================================================ ข้อมูลแบบรายการ (เก็บใน mis_data) */

/** หน้า CRUD ของข้อมูลใน mis_data: $fields = [name => [label, type, options|null, required]] */
function md_page(string $kind, string $option, string $label, array $fields, array $listCols, bool $post, string $scope = ''): array|string|null
{
    $func = $_GET['func'] ?? '';
    if ($post && ($func === 'Save')) {
        $id = (string) ($_GET['id'] ?? '') ?: (string) round(microtime(true) * 1000);
        $d = [];
        foreach ($fields as $n => [$l, $t]) {
            if ($t === 'file') {
                $old = json_decode((string) md_get($kind, $id, $scope), true) ?: [];
                $d[$n] = $old[$n] ?? '';
                if (!empty($_FILES[$n]['tmp_name']) && is_uploaded_file($_FILES[$n]['tmp_name'])) {
                    $ext = strtolower(pathinfo((string) $_FILES[$n]['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip', 'txt'], true)) {
                        $dir = ROOT_PATH.'datas/mis/';
                        @mkdir($dir, 0755, true);
                        $fname = $kind.'_'.$id.'.'.$ext;
                        if (move_uploaded_file($_FILES[$n]['tmp_name'], $dir.$fname)) {
                            $d[$n] = $fname;
                            $d[$n.'_name'] = mb_substr((string) $_FILES[$n]['name'], 0, 120);
                        }
                    }
                }
                continue;
            }
            $v = $_POST[$n] ?? '';
            $d[$n] = is_array($v) ? array_map('strval', $v) : mb_substr(trim((string) $v), 0, $t === 'textarea' ? 3000 : 250);
            if ($t === 'date') {
                $d[$n] = $d[$n] !== '' ? be_date_to_iso($d[$n]) : '';
            }
        }
        md_set($kind, $id, $scope, '', json_encode($d, JSON_UNESCAPED_UNICODE));
        admin_done($option);
    }
    if (isset($_GET['del'])) {
        md_set($kind, (string) $_GET['del'], $scope, '', null);
        admin_done($option);
    }
    if ($func === 'Edit') {
        $id = (string) ($_GET['id'] ?? '');
        $d = $id !== '' ? (json_decode((string) md_get($kind, $id, $scope), true) ?: []) : [];
        $rows = [];
        foreach ($fields as $n => [$l, $t, $opts, $req]) {
            $v = $d[$n] ?? '';
            $html = match ($t) {
                'textarea' => txt($n, $v),
                'select' => sel($n, ['' => '- เลือก -'] + (is_callable($opts) ? $opts() : $opts), $v, 'style="width:300px;height:25px"'),
                'date' => inp($n, $v ? iso_to_be($v) : '', 120, 'text', 'placeholder="วว/ดด/ปปปป"'),
                'number' => inp($n, $v, 100),
                'file' => '<input type="file" name="'.$n.'"/>'.($v ? ' <a href="/datas/mis/'.h($v).'" target="_blank">'.h($d[$n.'_name'] ?? $v).'</a>' : ''),
                default => inp($n, $v, 400),
            };
            $rows[] = [$l, $html, $req];
        }
        return [($id === '' ? 'เพิ่ม' : 'แก้ไข').$label, admin_form(($id === '' ? 'เพิ่ม' : 'แก้ไข').$label, a_url($option, '&func=Save&id='.rawurlencode($id)), $rows, a_url($option))];
    }
    $items = md_rows($kind, null, $scope);
    usort($items, fn($a, $b) => strcmp((string) $b['r1'], (string) $a['r1']));
    $rows = '';
    foreach ($items as $i => $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        $cells = [$i + 1];
        foreach ($listCols as $c => $lab) {
            if (is_array($lab)) {
                $cells[] = $lab[1]($d);
                continue;
            }
            $v = $d[$c] ?? '';
            $t = $fields[$c][1] ?? 'text';
            $opts = $fields[$c][2] ?? null;
            if ($t === 'select' && $opts) {
                $o = is_callable($opts) ? $opts() : $opts;
                $v = $o[$v] ?? $v;
            }
            $cells[] = match ($t) {
                'date' => $v ? th_date($v, ' ') : '',
                'file' => $v ? '<a href="/datas/mis/'.h($v).'" target="_blank">'.h($d[$c.'_name'] ?? 'เปิดไฟล์').'</a>' : '-',
                'textarea' => nl2br(h(mb_substr((string) $v, 0, 200))),
                default => h($v),
            };
        }
        $cells[] = [edit_link(a_url($option, '&func=Edit&id='.rawurlencode((string) $r['r1']))).' '.del_link(a_url($option, '&del='.rawurlencode((string) $r['r1']))), ' align="center" width="60"'];
        $rows .= zrow($cells, $i);
    }
    return list_table('รายการ'.h($label), array_merge(['#'], array_map(fn($l) => is_array($l) ? $l[0] : $l, array_values($listCols)), [['', ' style="width:60px"']]), $rows, count($items).' รายการ', btn('เพิ่ม'.$label, a_url($option, '&func=Edit'), 'add'));
}

function personnel_options(): array
{
    static $o = null;
    if ($o !== null) {
        return $o;
    }
    $o = [];
    foreach (personnel_rows() as $p) {
        $o[$p['id']] = $p['name'];
    }
    return $o;
}

function student_options(): array
{
    static $o = null;
    if ($o !== null) {
        return $o;
    }
    $o = [];
    foreach (q('SELECT S.`id`, S.`student_id`, S.`class`, S.`room`, U.`name` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE U.`active`=1 ORDER BY S.`class`, S.`room`, S.`number`') as $s) {
        $o[$s['id']] = $s['student_id'].' '.$s['name'].' ('.class_room_short($s['class'], $s['room']).')';
    }
    return $o;
}

function adm_program(bool $post)
{
    return md_page('program', 'program', 'แผนการเรียน', ['code' => ['รหัสแผนการเรียน', 'text', null, true], 'name' => ['ชื่อแผนการเรียน', 'text', null, true], 'short' => ['ชื่อย่อ', 'text', null, false]],
        ['code' => 'รหัส', 'name' => 'ชื่อแผนการเรียน', 'short' => 'ชื่อย่อ'], $post);
}

function adm_behavior(bool $post)
{
    return md_page('behavior', 'behavior', 'พฤติกรรม', ['student' => ['นักเรียน', 'select', 'student_options', true], 'date' => ['วันที่', 'date', null, true],
        'type' => ['ประเภท', 'select', ['plus' => 'ความดี (เพิ่มคะแนน)', 'minus' => 'ความผิด (หักคะแนน)'], true], 'points' => ['คะแนน', 'number', null, true], 'detail' => ['รายละเอียด', 'textarea', null, false]],
        ['student' => 'นักเรียน', 'date' => 'วันที่', 'type' => 'ประเภท', 'points' => 'คะแนน', 'detail' => 'รายละเอียด'], $post, yt());
}

const LEAVE_TYPES = ['sick' => 'ลาป่วย', 'personal' => 'ลากิจส่วนตัว', 'maternity' => 'ลาคลอดบุตร', 'vacation' => 'ลาพักผ่อน', 'training' => 'ลาศึกษา/อบรม', 'other' => 'อื่น ๆ'];

function adm_leave(bool $post)
{
    return md_page('leave', 'leave', 'การลา', ['person' => ['บุคลากร', 'select', 'personnel_options', true], 'type' => ['ประเภทการลา', 'select', LEAVE_TYPES, true],
        'from' => ['ตั้งแต่วันที่', 'date', null, true], 'to' => ['ถึงวันที่', 'date', null, true], 'days' => ['จำนวนวัน', 'number', null, true], 'note' => ['หมายเหตุ', 'textarea', null, false]],
        ['person' => 'บุคลากร', 'type' => 'ประเภท', 'from' => 'ตั้งแต่', 'to' => 'ถึง', 'days' => 'วัน'], $post, yt());
}

function adm_leave_report(bool $post)
{
    $sum = [];
    foreach (md_rows('leave', null, yt()) as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        $sum[$d['person'] ?? 0][$d['type'] ?? 'other'] = ($sum[$d['person'] ?? 0][$d['type'] ?? 'other'] ?? 0) + (float) ($d['days'] ?? 0);
    }
    $rows = '';
    $i = 0;
    foreach (personnel_options() as $id => $name) {
        $cells = [++$i, name_nbsp($name)];
        foreach (LEAVE_TYPES as $k => $l) {
            $cells[] = [($sum[$id][$k] ?? 0) ?: '-', ' align="center"'];
        }
        $cells[] = [array_sum($sum[$id] ?? []) ?: '-', ' align="center" style="font-weight:bold"'];
        $rows .= zrow($cells, $i);
    }
    return list_table('สรุปวันลา ปีการศึกษา '.yt(), array_merge(['#', 'ชื่อ-นามสกุล'], array_values(LEAVE_TYPES), ['รวม']), $rows);
}

function adm_news(bool $post)
{
    return md_page('news', 'news', 'ข่าวประกาศ', ['title' => ['หัวข้อ', 'text', null, true], 'body' => ['รายละเอียด', 'textarea', null, true], 'file' => ['ไฟล์แนบ', 'file', null, false]],
        ['title' => 'หัวข้อ', 'body' => 'รายละเอียด', 'file' => 'ไฟล์แนบ'], $post);
}

function adm_document(bool $post)
{
    return md_page('doc', 'document', 'เอกสาร', ['no' => ['เลขที่หนังสือ', 'text', null, false], 'title' => ['เรื่อง', 'text', null, true], 'from' => ['จาก', 'text', null, false],
        'date' => ['ลงวันที่', 'date', null, false], 'file' => ['ไฟล์เอกสาร', 'file', null, false], 'note' => ['หมายเหตุ', 'textarea', null, false]],
        ['no' => 'เลขที่', 'title' => 'เรื่อง', 'from' => 'จาก', 'date' => 'ลงวันที่', 'file' => 'ไฟล์'], $post);
}

/* ================================================================ นักเรียน */


function title_options(): array
{
    return ['' => '-', 'เด็กชาย' => 'เด็กชาย', 'เด็กหญิง' => 'เด็กหญิง', 'นาย' => 'นาย', 'นางสาว' => 'นางสาว', 'นาง' => 'นาง'];
}


/** สร้าง/แก้บัญชีผู้ใช้ คืน id */
function save_user(int $id, array $u, string $password = ''): int
{
    $cols = ['username', 'name', 'sex', 'phone', 'birthday', 'id_card', 'active', 'status', 'permission', 'address'];
    $data = array_intersect_key($u, array_flip($cols));
    foreach (['phone', 'id_card', 'username'] as $k) {
        if (array_key_exists($k, $data) && $data[$k] === '') {
            $data[$k] = null;
        }
    }
    if ($password !== '') {
        $hp = \Index\Auth\Model::hashPassword($password);
        $data['password'] = $hp['hash'];
        $data['salt'] = $hp['salt'];
    }
    if ($id) {
        if ($data) {
            q('UPDATE {user} SET '.implode(',', array_map(fn($k) => "`$k`=?", array_keys($data))).' WHERE `id`=?', array_merge(array_values($data), [$id]));
        }
        return $id;
    }
    $data += ['salt' => '', 'password' => '', 'active' => 1, 'status' => 0, 'permission' => '', 'name' => ''];
    $data['created_at'] = date('Y-m-d H:i:s');
    q('INSERT INTO {user} ('.implode(',', array_map(fn($k) => "`$k`", array_keys($data))).') VALUES ('.implode(',', array_fill(0, count($data), '?')).')', array_values($data));
    return (int) (q1('SELECT MAX(`id`) m FROM {user}')['m'] ?? 0);
}

function username_taken(string $username, int $except): bool
{
    return $username !== '' && (bool) q1('SELECT 1 x FROM {user} WHERE `username`=? AND `id`<>? LIMIT 1', [$username, $except]);
}



function adm_parent(bool $post)
{
    $rows = '';
    foreach (q('SELECT S.*, U.`name` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE U.`active`=1 ORDER BY S.`class`, S.`room`, S.`number` LIMIT 1000') as $i => $s) {
        $rows .= zrow([$i + 1, h($s['student_id']), name_nbsp($s['name']), h(class_room_short($s['class'], $s['room'])), h($s['parent']), h($s['parent_phone']),
            [edit_link(a_url('student', '&func=Edit&id='.(int) $s['id'])), ' align="center"']], $i);
    }
    return list_table('ข้อมูลผู้ปกครอง', ['#', 'รหัสนร.', 'ชื่อ-นามสกุล', 'ชั้น', 'ผู้ปกครอง', 'โทรศัพท์', ''], $rows);
}

/** นำเข้า CSV: รหัสนักเรียน,คำนำหน้า,ชื่อ,นามสกุล,เพศ(ช/ญ),ระดับชั้น(ลำดับ),ห้อง(เช่น 1/1),เลขที่,เลขบัตรประชาชน */
function adm_student_import(bool $post)
{
    redirect(a_url('csv_import').'#students');
}

function adm_promote(bool $post)
{
    $classes = cats('class');
    $ids = array_keys($classes);
    if ($post && ($_POST['confirm'] ?? '') === 'yes') {
        $rooms = cats('room');
        $byName = array_flip($rooms);
        // จากชั้นสูงสุดลงมา: ชั้นสุดท้ายจบการศึกษา (พ้นสภาพ) ชั้นอื่นเลื่อนขึ้น 1 ชั้น ห้อง "n/x" เป็น "n+1/x"
        $last = end($ids);
        $gs = preg_match('/6|หก/', (string) cat('class', (int) $last)) ? 'จบการศึกษาระดับมัธยมศึกษาตอนปลาย' : 'จบการศึกษาภาคบังคับ';
        foreach (q('SELECT S.`id` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`class`=? AND U.`active`=1', [(int) $last]) as $g) {
            set_student_status((int) $g['id'], $gs, date('Y-m-d'));
        }
        for ($k = count($ids) - 2; $k >= 0; $k--) {
            foreach (q('SELECT `id`, `room` FROM {student} WHERE `class`=?', [(int) $ids[$k]]) as $s) {
                $rn = $rooms[$s['room']] ?? '';
                $new = preg_match('/^(\d+)\/(.+)$/', $rn, $m) ? ($byName[((int) $m[1] + 1).'/'.$m[2]] ?? $s['room']) : $s['room'];
                keep_room_history(q1('SELECT * FROM {student} WHERE `id`=?', [(int) $s['id']]));
                q('UPDATE {student} SET `class`=?, `room`=? WHERE `id`=?', [(int) $ids[$k + 1], (int) $new, (int) $s['id']]);
            }
        }
        admin_done('promote');
    }
    $body = '<p>เลื่อนนักเรียนทุกคนขึ้นหนึ่งระดับชั้น (ห้อง 1/1 → 2/1) นักเรียนชั้นสุดท้าย ('.h(end($classes) ?: '-').') จะได้สถานภาพจบการศึกษา</p><p>ถ้าต้องการเลือกห้องปลายทางรายคน ใช้ปุ่มเลื่อนชั้นในหน้า <a href="'.a_url('student_class').'">จัดการชั้นเรียน</a></p>'
        .'<p style="color:#f00">ทำเมื่อขึ้นปีการศึกษาใหม่เท่านั้น ย้อนกลับไม่ได้</p><label><input type="checkbox" name="confirm" value="yes"/> ยืนยันการเลื่อนชั้น</label>';
    return admin_form('เลื่อนชั้นนักเรียน', a_url('promote'), [['การเลื่อนชั้น', $body, true]], a_url('student'));
}

function adm_stat(bool $post)
{
    redirect('?module=main&option=student_stat');
}

/* ================================================================ บุคลากร */

const PERMISSIONS = ['can_teacher' => 'ครูผู้สอน', 'can_rate_student' => 'ให้คะแนนนักเรียน', 'can_manage_student' => 'จัดการนักเรียน', 'can_manage_course' => 'จัดการรายวิชา',
    'can_manage_personnel' => 'จัดการบุคลากร', 'can_config' => 'ตั้งค่าระบบ'];

function adm_personnel(bool $post)
{
    $rows = '';
    foreach (personnel_rows() as $i => $p) {
        $perm = array_filter(explode(',', trim((string) $p['permission'], ',')));
        $rows .= zrow([$i + 1, h($p['username']), name_nbsp($p['name']), h(cat('position', $p['position'], '-')), h(cat('department', $p['department'], '-')),
            (int) $p['status'] === 1 ? 'ผู้ดูแลระบบ' : h(implode(', ', array_map(fn($k) => PERMISSIONS[$k] ?? $k, $perm))), (int) $p['room'] ? h(class_room_short($p['class'], $p['room'])) : '-',
            [edit_link(a_url('personnel', '&func=Edit&id='.(int) $p['id'])).' '.del_link(a_url('personnel', '&func=Delete&id='.(int) $p['id'])), ' align="center" width="60"']], $i);
    }
    return ['บุคลากรปัจจุบัน', list_table('รายชื่อบุคลากรปัจจุบัน', ['#', 'รหัส/ชื่อผู้ใช้', 'ชื่อ-นามสกุล', 'ตำแหน่ง', 'กลุ่มงาน/กลุ่มสาระ', 'สิทธิ์', 'ครูที่ปรึกษา', ['', ' style="width:60px"']], $rows, '',
        btn('เพิ่มบุคลากร', a_url('personnel', '&func=Edit'), 'add')), head_btn('เพิ่ม', a_url('personnel', '&func=Edit'), 'add')];
}

function adm_personnel_Edit(bool $post)
{
    $id = (int) ($_GET['id'] ?? 0);
    $p = $id ? q1('SELECT U.*, P.`position`, P.`department`, P.`order`, P.`class` pclass, P.`room` proom FROM {user} U LEFT JOIN {personnel} P ON P.`id`=U.`id` WHERE U.`id`=?', [$id]) : null;
    [$pre, $first, $last] = split_name((string) ($p['name'] ?? ''));
    $perm = array_filter(explode(',', trim((string) ($p['permission'] ?? ''), ',')));
    $pc = '';
    foreach (PERMISSIONS as $k => $l) {
        $pc .= '<label style="margin-right:12px;white-space:nowrap"><input type="checkbox" name="perm[]" value="'.$k.'"'.(in_array($k, $perm, true) || (!$p && $k === 'can_teacher') ? ' checked' : '').'/> '.h($l).'</label> ';
    }
    $rooms = ['0' => '- ไม่ได้เป็นครูที่ปรึกษา -'];
    foreach (rooms_by_class() as $cid => $rs) {
        foreach ($rs as $rid => $n) {
            $rooms[$rid] = $cid ? class_room_short($cid, $rid) : $n;
        }
    }
    $rows = [
        ['ชื่อผู้ใช้ (รหัสบุคลากร/อีเมล)', inp('username', $p['username'] ?? '', 250), true],
        ['คำนำหน้าชื่อ', sel('title', title_options(), $pre), false], ['ชื่อ', inp('first', $first), true], ['นามสกุล', inp('last', $last), true],
        ['เพศ', sel('sex', ['' => '-', 'm' => 'ชาย', 'f' => 'หญิง'], $p['sex'] ?? ''), false],
        ['เบอร์โทรศัพท์', inp('phone', $p['phone'] ?? '', 200), false],
        ['ตำแหน่ง', sel('position', ['0' => '- เลือก -'] + cats('position'), $p['position'] ?? 0), false],
        ['กลุ่มงาน/กลุ่มสาระ', sel('department', ['0' => '- เลือก -'] + cats('department'), $p['department'] ?? 0), false],
        ['ครูที่ปรึกษาห้อง', sel('room', $rooms, $p['proom'] ?? 0), false],
        ['ลำดับการแสดง', inp('order', $p['order'] ?? 0, 80), false],
        ['ประเภทบัญชี', sel('status', ['0' => 'บุคลากร', '1' => 'ผู้ดูแลระบบ'], $p['status'] ?? 0), false],
        ['สิทธิ์การใช้งาน', $pc, false],
        ['สถานะ', sel('active', ['1' => 'ปฏิบัติงานปกติ', '0' => 'พ้นสภาพ/ลาออก'], $p['active'] ?? 1), false],
        ['รหัสผ่าน', inp('password', '', 200, 'password', 'autocomplete="new-password" placeholder="'.($p ? 'เว้นว่างถ้าไม่เปลี่ยน' : 'อย่างน้อย 6 ตัวอักษร').'"'), !$p],
    ];
    return [$p ? 'แก้ไขข้อมูลบุคลากร' : 'เพิ่มบุคลากร', admin_form($p ? 'แก้ไขข้อมูลบุคลากร' : 'เพิ่มบุคลากร', a_url('personnel', '&func=Save&id='.$id), $rows, a_url('personnel'))];
}

function adm_personnel_Save(bool $post)
{
    $id = (int) ($_GET['id'] ?? 0);
    $username = trim((string) ($_POST['username'] ?? ''));
    $first = trim((string) ($_POST['first'] ?? ''));
    $pw = (string) ($_POST['password'] ?? '');
    if (!$post || $username === '' || $first === '' || username_taken($username, $id) || (!$id && strlen($pw) < 6)) {
        redirect(a_url('personnel', '&func=Edit&id='.$id));
    }
    $perm = array_values(array_intersect(array_keys(PERMISSIONS), (array) ($_POST['perm'] ?? [])));
    $status = (int) ($_POST['status'] ?? 0) === 1 ? 1 : 0;
    if ($id === (int) me()['id']) {
        $status = (int) me()['status'];
    }
    $uid = save_user($id, ['username' => $username, 'name' => trim(($_POST['title'] ?? '').$first.' '.trim((string) ($_POST['last'] ?? ''))), 'sex' => in_array($_POST['sex'] ?? '', ['m', 'f'], true) ? $_POST['sex'] : null,
        'phone' => trim((string) ($_POST['phone'] ?? '')), 'active' => (int) ($_POST['active'] ?? 1), 'status' => $status, 'permission' => $perm ? ','.implode(',', $perm).',' : ''], strlen($pw) >= 6 ? $pw : '');
    $room = (int) ($_POST['room'] ?? 0);
    $cid = 0;
    foreach (rooms_by_class() as $c => $rs) {
        if (isset($rs[$room])) {
            $cid = (int) $c;
        }
    }
    if ($room) {
        // ห้องละไม่เกิน 2 คน: เกินให้ยกเลิกคนที่กำหนดไว้ก่อน
        $others = array_map(fn($r) => (int) $r['id'], q('SELECT `id` FROM {personnel} WHERE `room`=? AND `id`<>? ORDER BY `id`', [$room, $uid]));
        foreach (array_slice($others, 0, max(0, count($others) - 1)) as $oid) {
            q('UPDATE {personnel} SET `class`=0, `room`=0 WHERE `id`=?', [$oid]);
        }
    }
    $vals = [(int) ($_POST['position'] ?? 0), (int) ($_POST['department'] ?? 0), min(127, max(0, (int) ($_POST['order'] ?? 0))), $cid, $room];
    if (q1('SELECT 1 x FROM {personnel} WHERE `id`=?', [$uid])) {
        q('UPDATE {personnel} SET `position`=?, `department`=?, `order`=?, `class`=?, `room`=? WHERE `id`=?', array_merge($vals, [$uid]));
    } else {
        q('INSERT INTO {personnel} (`position`,`department`,`order`,`class`,`room`,`id`) VALUES (?,?,?,?,?,?)', array_merge($vals, [$uid]));
    }
    admin_done('personnel');
}

function adm_personnel_Delete(bool $post)
{
    $id = (int) ($_GET['id'] ?? 0);
    if ($id && $id !== (int) me()['id']) {
        q('UPDATE {user} SET `active`=0 WHERE `id`=? AND `status`<>1', [$id]);
        q('DELETE FROM {user_session} WHERE `member_id`=?', [$id]);
    }
    admin_done('personnel');
}

function adm_permission(bool $post)
{
    if ($post) {
        foreach (personnel_rows() as $p) {
            if ((int) $p['status'] === 1) {
                continue;
            }
            $perm = array_values(array_intersect(array_keys(PERMISSIONS), (array) ($_POST['perm'][$p['id']] ?? [])));
            q('UPDATE {user} SET `permission`=? WHERE `id`=?', [$perm ? ','.implode(',', $perm).',' : '', (int) $p['id']]);
        }
        admin_done('permission');
    }
    $rows = '';
    foreach (personnel_rows() as $i => $p) {
        $perm = array_filter(explode(',', trim((string) $p['permission'], ',')));
        $cells = [$i + 1, name_nbsp($p['name'])];
        foreach (PERMISSIONS as $k => $l) {
            $cells[] = [(int) $p['status'] === 1 ? '✓' : '<input type="checkbox" name="perm['.(int) $p['id'].'][]" value="'.$k.'"'.(in_array($k, $perm, true) ? ' checked' : '').'/>', ' align="center"'];
        }
        $rows .= zrow($cells, $i);
    }
    return '<form name="pm" method="post">'.list_table('สิทธิ์การใช้งาน', array_merge(['#', 'ชื่อ-นามสกุล'], array_map(fn($l) => [$l, ' style="text-align:center"'], array_values(PERMISSIONS))), $rows, 'ผู้ดูแลระบบมีสิทธิ์ทั้งหมด',
        '<a href="javascript:void(0);" onclick="document.forms.pm.submit();" id="pinkgreenbtns"><img src="../icon_tools/vec_add_white.png" border="0" height="16"/> บันทึกสิทธิ์</a>').'</form>';
}

/* ================================================================ วิชาการ */

function adm_course(bool $post)
{
    [$y, $t] = term();
    $rows = '';
    $courses = q('SELECT C.*, U.`name` teacher FROM {course} C LEFT JOIN {user} U ON U.`id`=C.`teacher_id` WHERE C.`year`=? AND C.`term`=? ORDER BY C.`class`, C.`type`, C.`course_code`', [$y, $t]);
    $rooms = course_rooms($courses);
    foreach ($courses as $i => $c) {
        $rows .= zrow([$i + 1, h($c['course_code']), h($c['course_name']), COURSE_TYPES[(int) $c['type']] ?? '', h(class_short($c['class'])), [h((float) $c['credit']), ' align="center"'], [(int) $c['period'], ' align="center"'],
            name_nbsp((string) $c['teacher']), [array_sum($rooms[(int) $c['id']] ?? []), ' align="center"'],
            [edit_link(a_url('course', '&func=Edit&id='.(int) $c['id'])).' <a href="'.a_url('enroll', '&course='.(int) $c['id']).'" title="ลงทะเบียนเรียน"><img src="../icon_tools/vec_man_dark.png" height="16"/></a> '.del_link(a_url('course', '&func=Delete&id='.(int) $c['id'])), ' align="center" width="80"']], $i);
    }
    return ['รายวิชา ประจำภาคเรียนที่ '.$t.' ปีการศึกษา '.$y, list_table('รายวิชา ประจำภาคเรียนที่ '.$t.' ปีการศึกษา '.$y, ['#', 'รหัสวิชา', 'ชื่อรายวิชา', 'ประเภท', 'ชั้น', 'หน่วยกิต', 'คาบ', 'ครูผู้สอน', 'นักเรียน', ['', ' style="width:80px"']], $rows, count($courses).' รายวิชา',
        btn('เพิ่มรายวิชา', a_url('course', '&func=Edit'), 'add')), head_btn('เพิ่ม', a_url('course', '&func=Edit'), 'add')];
}

function adm_course_Edit(bool $post)
{
    $id = (int) ($_GET['id'] ?? 0);
    $c = $id ? q1('SELECT * FROM {course} WHERE `id`=?', [$id]) : null;
    $rows = [
        ['รหัสวิชา', inp('course_code', $c['course_code'] ?? '', 150), true], ['ชื่อรายวิชา', inp('course_name', $c['course_name'] ?? '', 400), true],
        ['ประเภท', sel('type', COURSE_TYPES, $c['type'] ?? 1), true], ['ระดับชั้น', sel('class', ['' => '- เลือก -'] + cats('class'), $c['class'] ?? ''), true],
        ['หน่วยกิต', inp('credit', $c['credit'] ?? '1.0', 80), true], ['จำนวนคาบ/ภาคเรียน', inp('period', $c['period'] ?? 40, 80), true],
        ['ครูผู้สอน', sel('teacher_id', ['0' => '- เลือก -'] + personnel_options(), $c['teacher_id'] ?? 0, 'style="width:300px;height:25px"'), false],
    ];
    return [$c ? 'แก้ไขรายวิชา' : 'เพิ่มรายวิชา', admin_form($c ? 'แก้ไขรายวิชา' : 'เพิ่มรายวิชา', a_url('course', '&func=Save&id='.$id), $rows, a_url('course'))];
}

function adm_course_Save(bool $post)
{
    $id = (int) ($_GET['id'] ?? 0);
    [$y, $t] = term();
    $v = [mb_substr(trim((string) ($_POST['course_name'] ?? '')), 0, 50), mb_substr(trim((string) ($_POST['course_code'] ?? '')), 0, 20), (int) ($_POST['teacher_id'] ?? 0), (int) ($_POST['class'] ?? 0),
        max(0, (int) ($_POST['period'] ?? 0)), min(9.9, max(0, (float) ($_POST['credit'] ?? 0))), isset(COURSE_TYPES[(int) ($_POST['type'] ?? 1)]) ? (int) $_POST['type'] : 1];
    $dup = q1('SELECT 1 x FROM {course} WHERE `course_code`=? AND `class`=? AND `year`=? AND `term`=? AND `id`<>?', [$v[1], $v[3], $y, $t, $id]);
    if ($post && $v[0] !== '' && $v[1] !== '' && !$dup) {
        if ($id) {
            q('UPDATE {course} SET `course_name`=?, `course_code`=?, `teacher_id`=?, `class`=?, `period`=?, `credit`=?, `type`=? WHERE `id`=?', array_merge($v, [$id]));
        } else {
            q('INSERT INTO {course} (`course_name`,`course_code`,`teacher_id`,`class`,`period`,`credit`,`type`,`year`,`term`) VALUES (?,?,?,?,?,?,?,?,?)', array_merge($v, [$y, $t]));
        }
    }
    admin_done('course');
}

function adm_course_Delete(bool $post)
{
    $id = (int) ($_GET['id'] ?? 0);
    q('DELETE FROM {grade} WHERE `course_id`=?', [$id]);
    q('DELETE FROM {course} WHERE `id`=?', [$id]);
    admin_done('course');
}

function adm_course_copy(bool $post)
{
    [$y, $t] = term();
    [$py, $pt] = $t === 2 ? [$y, 1] : [$y - 1, 2];
    if ($post) {
        $sy = (int) ($_POST['from_year'] ?? $py);
        $stt = (int) ($_POST['from_term'] ?? $pt);
        $n = 0;
        foreach (q('SELECT * FROM {course} WHERE `year`=? AND `term`=?', [$sy, $stt]) as $c) {
            if (!q1('SELECT 1 x FROM {course} WHERE `course_code`=? AND `year`=? AND `term`=?', [$c['course_code'], $y, $t])) {
                q('INSERT INTO {course} (`course_name`,`course_code`,`teacher_id`,`class`,`period`,`credit`,`type`,`year`,`term`) VALUES (?,?,?,?,?,?,?,?,?)',
                    [$c['course_name'], $c['course_code'], $c['teacher_id'], $c['class'], $c['period'], $c['credit'], $c['type'], $y, $t]);
                $n++;
            }
        }
        return msg_box("คัดลอกแล้ว $n รายวิชา").adm_course(false)[1];
    }
    return admin_form('คัดลอกรายวิชามาที่ภาคเรียน '.$t.'/'.$y, a_url('course_copy'), [['จากปีการศึกษา', inp('from_year', $py, 80), true], ['ภาคเรียน', sel('from_term', [1 => '1', 2 => '2'], $pt), true]], a_url('course'));
}

function adm_enroll(bool $post)
{
    [$y, $t] = term();
    $courses = q('SELECT * FROM {course} WHERE `year`=? AND `term`=? ORDER BY `class`, `course_code`', [$y, $t]);
    $cid = (int) ($_GET['course'] ?? ($courses[0]['id'] ?? 0));
    $copts = [];
    foreach ($courses as $c) {
        $copts[$c['id']] = $c['course_code'].' '.$c['course_name'].' ('.class_short($c['class']).')';
    }
    if ($post && $cid) {
        $room = (int) ($_POST['room'] ?? 0);
        $n = 0;
        if (isset($_POST['add_room']) && $room) {
            foreach (q('SELECT S.`id`, S.`number` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`room`=? AND U.`active`=1', [$room]) as $s) {
                if (!q1('SELECT 1 x FROM {grade} WHERE `course_id`=? AND `student_id`=?', [$cid, (int) $s['id']])) {
                    q('INSERT INTO {grade} (`student_id`,`course_id`,`number`,`room`,`type`) VALUES (?,?,?,?,1)', [(int) $s['id'], $cid, $s['number'], $room]);
                    $n++;
                }
            }
        }
        foreach ((array) ($_POST['remove'] ?? []) as $gid) {
            q('DELETE FROM {grade} WHERE `id`=? AND `course_id`=?', [(int) $gid, $cid]);
        }
        redirect(a_url('enroll', '&course='.$cid.'&msg=saved'));
    }
    $rows = '';
    foreach ($cid ? q('SELECT G.`id` gid, G.`room`, S.`student_id`, S.`number`, U.`name` FROM {grade} G JOIN {student} S ON S.`id`=G.`student_id` JOIN {user} U ON U.`id`=G.`student_id` WHERE G.`course_id`=? ORDER BY G.`room`, S.`number`', [$cid]) : [] as $i => $s) {
        $rows .= zrow([$i + 1, h($s['student_id']), name_nbsp($s['name']), h(room_short($s['room'])), [h($s['number']), ' align="center"'], ['<input type="checkbox" name="remove[]" value="'.(int) $s['gid'].'"/>', ' align="center"']], $i);
    }
    $f = '<form method="get" style="width:983px;margin:0 auto 10px;text-align:left"><input type="hidden" name="module" value="admin"/><input type="hidden" name="option" value="enroll"/><span class="search">รายวิชา '
        .sel('course', $copts ?: ['' => '- ยังไม่มีรายวิชา -'], $cid, 'onchange="this.form.submit()" style="width:400px;height:25px"').'</span><div style="clear:both"></div></form>';
    $add = '<span style="float:left">เพิ่มนักเรียนทั้งห้อง '.sel('room', ['0' => '- เลือกห้อง -'] + cats('room'), 0).' <input type="submit" name="add_room" value="เพิ่ม" class="general radius" style="height:25px"/></span>'
        .'<input type="submit" name="remove_btn" value="ลบนักเรียนที่เลือก" class="general radius" style="height:25px" onclick="return confirm(\'ลบนักเรียนที่เลือกออกจากรายวิชา?\')"/>';
    return ['ลงทะเบียนนักเรียนเข้ารายวิชา', $f.'<form method="post" action="'.h(a_url('enroll', '&course='.$cid)).'">'.list_table('นักเรียนที่ลงทะเบียน', ['#', 'รหัสนร.', 'ชื่อ-นามสกุล', 'ห้อง', 'เลขที่', ['เลือกลบ', ' style="text-align:center"']], $rows, '', $add).'</form>'];
}

function adm_timetable(bool $post)
{
    redirect('?module=academic&option=teacher_timetable');
}

function adm_calendar(bool $post)
{
    redirect('?module=academic&option=academic_calendar');
}

function adm_grade_report(bool $post)
{
    redirect('?module=task&option=report_record_byroom');
}

function adm_approve(bool $post)
{
    [$y, $t] = term();
    if (isset($_GET['ok'])) {
        md_set('approved', (int) $_GET['ok'], '', '', th_date('', ' '));
        admin_done('approve');
    }
    if (isset($_GET['reject'])) {
        md_set('ask', (int) $_GET['reject'], '', '', null);
        md_set('approved', (int) $_GET['reject'], '', '', null);
        admin_done('approve');
    }
    $courses = q('SELECT C.*, U.`name` teacher FROM {course} C LEFT JOIN {user} U ON U.`id`=C.`teacher_id` WHERE C.`year`=? AND C.`term`=? ORDER BY C.`course_code`', [$y, $t]);
    $gc = grade_counts(array_map(fn($c) => (int) $c['id'], $courses));
    $rows = '';
    foreach ($courses as $i => $c) {
        $ask = md_get('ask', $c['id']);
        $ok = md_get('approved', $c['id']);
        $g = $gc[(int) $c['id']] ?? [];
        $dist = implode(' ', array_filter(array_map(fn($k) => isset($g[$k]) ? "$k:{$g[$k]}" : '', GRADE_COLS)));
        $act = $ok ? '<span style="color:#00AA00">อนุมัติแล้ว '.h($ok).'</span> <a href="'.a_url('approve', '&reject='.(int) $c['id']).'">ยกเลิก</a>'
            : ($ask ? '<a href="'.a_url('approve', '&ok='.(int) $c['id']).'" id="pinkgreenbtns">อนุมัติ</a> <a href="'.a_url('approve', '&reject='.(int) $c['id']).'">ส่งกลับแก้ไข</a>' : '<span class="gray_12">ยังไม่ส่งขออนุมัติ</span>');
        $rows .= zrow([$i + 1, h($c['course_code'].' '.$c['course_name']), name_nbsp((string) $c['teacher']), [array_sum($g), ' align="center"'], h($dist), $ask ? h($ask) : '-', $act], $i);
    }
    return list_table('อนุมัติผลการเรียน ภาคเรียนที่ '.$t.'/'.$y, ['#', 'รายวิชา', 'ครูผู้สอน', 'นักเรียน', 'การกระจายเกรด', 'วันที่ขออนุมัติ', 'การอนุมัติ'], $rows);
}

/* ================================================================ กิจการนักเรียน */

function adm_school_att(bool $post)
{
    $day = be_date_to_iso((string) ($_GET['day'] ?? ''));
    $rows = '';
    $i = 0;
    foreach (rooms_by_class() as $cid => $rooms) {
        foreach ($rooms as $rid => $rname) {
            $ids = array_map(fn($s) => (int) $s['id'], q('SELECT S.`id` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`room`=? AND U.`active`=1', [$rid]));
            $att = school_att($ids, $day, $day);
            $c = array_count_values(array_map(fn($x) => $x[$day] ?? '', $att));
            $cells = [h($cid ? class_room_short($cid, $rid) : $rname), [count($ids), ' align="center"']];
            foreach (SCHOOL_ATT as $k => $n) {
                $cells[] = [(int) ($c[$k] ?? 0), ' align="center"'];
            }
            $cells[] = [count($ids) - count($att) ?: '-', ' align="center" style="color:#999"'];
            $rows .= zrow($cells, $i++);
        }
    }
    $f = '<form method="get" style="width:983px;margin:0 auto 10px;text-align:left"><input type="hidden" name="module" value="admin"/><input type="hidden" name="option" value="school_att"/><span class="search">วันที่ '
        .inp('day', iso_to_be($day), 120).' <input type="submit" value="แสดง" class="general radius" style="height:25px"/></span><div style="clear:both"></div></form>';
    return $f.list_table('สรุปการมาโรงเรียน วัน'.TH_DAYS[(int) date('w', strtotime($day))].'ที่ '.th_date($day, ' '), array_merge(['ห้อง', 'นักเรียน'], array_values(SCHOOL_ATT), ['ยังไม่บันทึก']), $rows);
}

function adm_absent(bool $post)
{
    [$from, $to] = term_range();
    $min = max(1, (int) ($_GET['min'] ?? 3));
    md_ensure();
    $rows = '';
    $data = q('SELECT M.`r1`, COUNT(*) n FROM {mis_data} M WHERE M.`kind`=\'satt\' AND M.`value` IN (\'ข\',\'น\') AND M.`r2` BETWEEN ? AND ? GROUP BY M.`r1` HAVING n>=? ORDER BY n DESC', [$from, $to, $min]);
    $st = [];
    if ($data) {
        foreach (q('SELECT S.*, U.`name` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`id` IN ('.implode(',', array_map(fn($r) => (int) $r['r1'], $data)).')') as $s) {
            $st[(int) $s['id']] = $s;
        }
    }
    foreach ($data as $i => $r) {
        $s = $st[(int) $r['r1']] ?? null;
        if ($s) {
            $rows .= zrow([$i + 1, h($s['student_id']), name_nbsp($s['name']), h(class_room_short($s['class'], $s['room'])), h($s['parent']), h($s['parent_phone']), [(int) $r['n'], ' align="center" style="color:#f00"']], $i);
        }
    }
    $f = '<form method="get" style="width:983px;margin:0 auto 10px;text-align:left"><input type="hidden" name="module" value="admin"/><input type="hidden" name="option" value="absent"/><span class="search">ขาด/หนี ตั้งแต่ '
        .sel('min', [3 => '3 วัน', 5 => '5 วัน', 10 => '10 วัน', 20 => '20 วัน'], $min, 'onchange="this.form.submit()"').' ขึ้นไป (ภาคเรียนนี้)</span><div style="clear:both"></div></form>';
    return $f.list_table('นักเรียนขาดเรียนบ่อย', ['#', 'รหัสนร.', 'ชื่อ-นามสกุล', 'ชั้น', 'ผู้ปกครอง', 'โทรศัพท์', 'วันขาด/หนี'], $rows);
}

function adm_under80(bool $post)
{
    [$y, $t] = term();
    att_ensure();
    $present = "'".implode("','", \Attendance\Sheet\Model::PRESENT)."'";
    $rows = q('SELECT A.`course_id`, A.`student_id`, A.`room`, C.`course_code`, C.`course_name`, C.`class`, S.`student_id` code, S.`number`, U.`name`, COUNT(*) total, SUM(A.`status` IN ('.$present.')) present'
        .' FROM {attendance} A JOIN {course} C ON C.`id`=A.`course_id` JOIN {student} S ON S.`id`=A.`student_id` JOIN {user} U ON U.`id`=A.`student_id` WHERE C.`year`=? AND C.`term`=?'
        .' GROUP BY A.`course_id`, A.`student_id`, A.`room`, C.`course_code`, C.`course_name`, C.`class`, S.`student_id`, S.`number`, U.`name` HAVING present*100 < total*80 ORDER BY C.`course_code`, A.`room`, S.`number`', [$y, $t]);
    return under80_table($rows, 'นักเรียนเวลาเรียนไม่ถึง 80% ทั้งโรงเรียน');
}

function adm_activity(bool $post)
{
    if (isset($_GET['set'], $_GET['who'], $_GET['st'])) {
        $raw = md_get('join', (string) $_GET['who'], yt(), (string) $_GET['set']);
        if ($raw) {
            $d = json_decode($raw, true) ?: [];
            $d['status'] = $_GET['st'] === 'ok' ? 'อนุมัติแล้ว' : 'ไม่อนุมัติ';
            md_set('join', (string) $_GET['who'], yt(), (string) $_GET['set'], json_encode($d, JSON_UNESCAPED_UNICODE));
        }
        admin_done('activity');
    }
    $rows = '';
    foreach (array_reverse(md_rows('join', null, yt())) as $i => $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        $who = q1('SELECT `name` FROM {user} WHERE `id`=?', [(int) $r['r1']]);
        $q = '&set='.rawurlencode((string) $r['r3']).'&who='.rawurlencode((string) $r['r1']);
        $rows .= zrow([$i + 1, h($d['name'] ?? ''), h($d['place'] ?? ''), !empty($d['date']) ? th_date($d['date'], ' ') : '', count($d['students'] ?? []).' คน', name_nbsp($who['name'] ?? ''), h($d['status'] ?? ''),
            '<a href="'.a_url('activity', $q.'&st=ok').'">อนุมัติ</a> | <a href="'.a_url('activity', $q.'&st=no').'">ไม่อนุมัติ</a>'], $i);
    }
    return list_table('คำขออนุญาตให้นักเรียนเข้าร่วมกิจกรรม', ['#', 'กิจกรรม', 'สถานที่', 'วันที่', 'นักเรียน', 'ผู้ขอ', 'สถานะ', 'จัดการ'], $rows);
}

/* ================================================================ ทั่วไป / ตั้งค่า */

function adm_log(bool $post)
{
    $rows = '';
    $logs = q1("SHOW TABLES LIKE '".\Kotchasan\DB::create()->getTableName('logs')."'") ? q('SELECT L.*, U.`name` FROM {logs} L LEFT JOIN {user} U ON U.`id`=L.`member_id` ORDER BY L.`id` DESC LIMIT 200') : [];
    foreach ($logs as $i => $l) {
        $rows .= zrow([$i + 1, h($l['created_at'] ?? ''), name_nbsp((string) ($l['name'] ?? '')), h($l['module'] ?? ''), h($l['action'] ?? ''), h(mb_substr(strip_tags((string) ($l['topic'] ?? '')), 0, 120))], $i);
    }
    return list_table('ประวัติการใช้งานล่าสุด', ['#', 'เวลา', 'ผู้ใช้', 'โมดูล', 'การกระทำ', 'รายละเอียด'], $rows);
}

/* ---------------------------------------------------------------- ข้อมูลโรงเรียน (การตั้งค่า) */

const SCHOOL_FIELDS = [
    'school_name' => ['ชื่อโรงเรียน', true], 'affiliation' => ['สังกัด', true], 'school_area' => ['เขต', true], 'web_title' => ['ชื่อระบบโรงเรียน', true],
    'addr_no' => ['ที่ตั้ง เลขที่', false], 'addr_moo' => ['หมู่', false], 'addr_tambon' => ['แขวง/ตำบล', false], 'addr_amphoe' => ['เขต/อำเภอ', false],
    'addr_province' => ['จังหวัด', false], 'addr_zip' => ['รหัสไปรษณีย์', false], 'phone' => ['เบอร์โทรศัพท์', false], 'fax' => ['โทรสาร', false],
    'email' => ['อีเมล', false], 'network' => ['ศูนย์เครือข่ายที่', false], 'area_rai' => ['พื้นที่ (ไร่)', false], 'area_ngan' => ['พื้นที่ (งาน)', false],
    'area_wa' => ['พื้นที่ (ตารางวา)', false], 'levels_open' => ['ระดับชั้นที่เปิดสอน', false],
];

/** ที่อยู่โรงเรียนแบบย่อ (7 หมู่ 3 ต.เวียงพางคำ อ.แม่สาย จ.เชียงราย) */
function school_address(bool $withPhone = false): string
{
    $parts = array_filter([cfg('addr_no', ''), cfg('addr_moo', '') !== '' ? 'หมู่ '.cfg('addr_moo') : '', cfg('addr_tambon', '') !== '' ? 'ต.'.cfg('addr_tambon') : '',
        cfg('addr_amphoe', '') !== '' ? 'อ.'.cfg('addr_amphoe') : '', cfg('addr_province', '') !== '' ? 'จ.'.cfg('addr_province') : '', (string) cfg('addr_zip', '')], fn($x) => (string) $x !== '');
    $a = $parts ? implode(' ', $parts) : (string) cfg('address', '7 หมู่ 3 ต.เวียงพางคำ อ.แม่สาย จ.เชียงราย');
    return $a.($withPhone && cfg('phone', '') !== '' ? ' โทรศัพท์ '.cfg('phone') : '');
}

/** ตราโรงเรียน (อัปโหลดได้ที่การตั้งค่า > ข้อมูลโรงเรียน) */
function school_logo(): string
{
    foreach (['png', 'jpg', 'gif'] as $e) {
        $f = ROOT_PATH.'datas/school/logo.'.$e;
        if (is_file($f)) {
            return '/datas/school/logo.'.$e.'?v='.filemtime($f);
        }
    }
    return '../gallery/logo/school_logo.png';
}

/** ชื่อนายทะเบียน */
function registrar_name(): string
{
    return (string) (personnel_options()[(int) cfg('registrar', 0)] ?? '');
}

function adm_school(bool $post)
{
    if ($post) {
        $vals = [];
        foreach (SCHOOL_FIELDS as $k => $x) {
            $vals[$k] = mb_substr(trim((string) ($_POST[$k] ?? '')), 0, 200);
        }
        $vals['registrar'] = (int) ($_POST['registrar'] ?? 0);
        // ค่าเดิมที่ส่วนอื่นของระบบใช้
        $vals['school_amphoe'] = $vals['addr_amphoe'];
        $vals['school_province'] = $vals['addr_province'];
        $vals['address'] = trim(implode(' ', array_filter([$vals['addr_no'], $vals['addr_moo'] !== '' ? 'หมู่ '.$vals['addr_moo'] : '', $vals['addr_tambon'] !== '' ? 'ต.'.$vals['addr_tambon'] : '',
            $vals['addr_amphoe'] !== '' ? 'อ.'.$vals['addr_amphoe'] : '', $vals['addr_province'] !== '' ? 'จ.'.$vals['addr_province'] : '', $vals['addr_zip']])));
        $miss = array_filter(SCHOOL_FIELDS, fn($x, $k) => $x[1] && $vals[$k] === '', ARRAY_FILTER_USE_BOTH);
        if ($miss || !$vals['registrar']) {
            return msg_box('กรุณากรอกรายละเอียดในช่องที่มีเครื่องหมาย * ให้ครบทุกช่อง', false).adm_school(false);
        }
        $up = $_FILES['logo'] ?? null;
        if ($up && (int) $up['error'] === 0 && (int) $up['size'] > 0 && (int) $up['size'] < 2 * 1024 * 1024) {
            $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif'][(string) @mime_content_type($up['tmp_name'])] ?? '';
            if ($ext !== '') {
                @mkdir(ROOT_PATH.'datas/school', 0777, true);
                foreach (['png', 'jpg', 'gif'] as $e) {
                    @unlink(ROOT_PATH.'datas/school/logo.'.$e);
                }
                move_uploaded_file($up['tmp_name'], ROOT_PATH.'datas/school/logo.'.$ext);
            }
        }
        save_cfg($vals);
        admin_done('school');
    }
    $field = function (string $k) {
        [$label, $req] = SCHOOL_FIELDS[$k];
        $def = ['school_name' => 'โรงเรียนดรุณราษฎร์วิทยา', 'web_title' => 'ระบบบริหารจัดการสารสนเทศโรงเรียน', 'addr_amphoe' => (string) cfg('school_amphoe', ''), 'addr_province' => (string) cfg('school_province', '')][$k] ?? '';
        $v = (string) (cfg($k) ?: $def);
        $style = $req ? 'border:1px solid #F5B7A8;border-left:3px solid #C0392B;background:#FFF8F2;color:#C0392B' : 'border:1px solid #ccc;border-left:3px solid #555;background:#F4F4F4';
        return '<td class="sl">'.h($label).'</td><td class="sc">'.($req ? '<span style="color:#C0392B">*</span>' : '').':</td><td><input name="'.$k.'" value="'.h($v).'" style="width:220px;height:24px;padding:0 6px;border-radius:2px;'.$style.'"/></td>';
    };
    $po = ['' => '- เลือกนายทะเบียน -'] + personnel_options();
    $logo = school_logo();
    $grid = [['__logo', 'school_name'], [null, 'affiliation'], [null, 'school_area'], [null, 'web_title'], ['addr_no', 'addr_moo'], ['addr_tambon', 'addr_amphoe'], ['addr_province', 'addr_zip'],
        ['phone', 'fax'], ['email', 'network'], ['area_rai', 'area_ngan'], ['area_wa', 'levels_open'], ['__registrar', null]];
    $rows = '';
    foreach ($grid as $i => [$l, $r]) {
        $left = '';
        if ($l === '__logo') {
            $left = '<td class="sl" style="vertical-align:top">ตราโรงเรียน</td><td class="sc" style="vertical-align:top">:</td><td rowspan="4" style="vertical-align:top"><div style="width:96px;height:96px;border:1px solid #ccc;border-radius:3px;display:flex;align-items:center;justify-content:center;background:#fff"><img src="'.h($logo).'" style="max-width:88px;max-height:88px"/></div>'
                .'<input type="file" name="logo" accept="image/png,image/jpeg,image/gif" style="margin-top:6px;width:240px;font-size:12px"/><div class="gray_12" style="font-size:11px">ไฟล์ PNG/JPG/GIF ไม่เกิน 2 MB</div></td>';
        } elseif ($l === '__registrar') {
            $left = '<td class="sl">นายทะเบียน</td><td class="sc"><span style="color:#C0392B">*</span>:</td><td>'.str_replace('<select', '<select style="width:234px;height:26px;border:1px solid #F5B7A8;border-left:3px solid #C0392B;background:#FFF8F2;color:#C0392B"', sel('registrar', $po, (string) cfg('registrar', ''))).'</td>';
        } elseif ($l !== null) {
            $left = $field($l);
        } elseif ($i <= 3) {
            $left = '<td></td><td></td>';
        } else {
            $left = '<td></td><td></td><td></td>';
        }
        $rows .= '<tr>'.$left.($r !== null ? $field($r) : '<td></td><td></td><td></td>').'</tr>';
    }
    $html = '<style>.sch td{padding:5px 4px;font-size:13px;vertical-align:middle}.sch td.sl{width:150px;color:#333}.sch td.sc{width:20px;text-align:center}</style>'
        .'<div style="width:983px;margin:0 auto;text-align:left"><form method="post" enctype="multipart/form-data" action="'.h(a_url('school')).'">'
        .'<div style="border:1px solid #d6dde3;border-radius:4px;background:#fff"><div style="background:linear-gradient(#fff,#f1f1f1);border-bottom:1px solid #ddd;padding:8px 12px;font-family:thaisansbold;font-size:24px;color:#17A2B8"><i class="fa fa-pencil-square-o"></i> ข้อมูลโรงเรียน</div>'
        .'<div style="padding:10px 16px"><div style="color:#17A2B8;font-size:12px;margin-bottom:8px"><i class="fa fa-commenting" style="font-size:16px"></i> กรุณากรอกรายละเอียดในช่องที่มีเครื่องหมาย * ให้ครบทุกช่อง</div>'
        .'<table class="sch" style="width:100%">'.$rows.'</table></div>'
        .'<div style="background:#F5F5F5;border-top:1px solid #ddd;padding:8px 12px 8px 196px"><button type="submit" style="background:#3EA2E8;border:1px solid #2f8fd3;color:#fff;border-radius:3px;padding:4px 12px;font-size:13px;cursor:pointer"><i class="fa fa-cog"></i> บันทึก</button> '
        .'<button type="reset" style="background:#F7B331;border:1px solid #e09a16;color:#fff;border-radius:3px;padding:4px 12px;font-size:13px;cursor:pointer">✕ เคลียร์</button></div></div></form>'
        .'<div class="gray_12" style="margin-top:8px">ข้อมูลเหล่านี้จะถูกเรียกไปแสดงในส่วนต่าง ๆ ของโปรแกรม เช่น หัวเว็บ รายงาน และหลักฐานการศึกษา (ปพ.) จึงควรระบุข้อมูลให้สมบูรณ์และตรงตามความเป็นจริง'
        .' &nbsp;|&nbsp; กำหนดปีการศึกษา/ภาคเรียนปัจจุบันได้ที่เมนู <a href="'.h(a_url('term')).'">ภาคเรียน</a></div></div>';
    return ['ตั้งค่าระบบ', $html];
}

function adm_users(bool $post)
{
    if (isset($_GET['reset'])) {
        $id = (int) $_GET['reset'];
        $u = q1('SELECT `username` FROM {user} WHERE `id`=?', [$id]);
        if ($u && $id !== (int) me()['id']) {
            save_user($id, [], (string) $u['username']);
        }
        admin_done('users');
    }
    if (isset($_GET['toggle'])) {
        $id = (int) $_GET['toggle'];
        if ($id !== (int) me()['id']) {
            q('UPDATE {user} SET `active`=1-`active` WHERE `id`=?', [$id]);
            q('DELETE FROM {user_session} WHERE `member_id`=?', [$id]);
        }
        admin_done('users');
    }
    $type = (string) ($_GET['t'] ?? 'staff');
    $sql = 'SELECT U.*, S.`id` sid FROM {user} U LEFT JOIN {student} S ON S.`id`=U.`id` WHERE '.($type === 'student' ? 'S.`id` IS NOT NULL' : 'S.`id` IS NULL').' ORDER BY U.`id` LIMIT 1000';
    $rows = '';
    foreach (q($sql) as $i => $u) {
        $rows .= zrow([$i + 1, h($u['username']), name_nbsp($u['name']), (int) $u['status'] === 1 ? 'ผู้ดูแลระบบ' : ($u['sid'] ? 'นักเรียน' : 'บุคลากร'), (int) $u['active'] ? 'ใช้งาน' : '<span style="color:#f00">ระงับ</span>',
            '<a href="'.a_url('users', '&t='.$type.'&toggle='.(int) $u['id']).'">'.((int) $u['active'] ? 'ระงับ' : 'เปิดใช้').'</a> | <a href="'.a_url('users', '&t='.$type.'&reset='.(int) $u['id']).'" onclick="return confirm(\'ตั้งรหัสผ่านใหม่ให้เท่ากับชื่อผู้ใช้?\')">รีเซ็ตรหัสผ่าน</a>'], $i);
    }
    $f = '<div style="width:983px;margin:0 auto 10px;text-align:left"><span class="search">'.($type === 'student' ? '<a href="'.a_url('users', '&t=staff').'">บุคลากร</a> | <b>นักเรียน</b>' : '<b>บุคลากร</b> | <a href="'.a_url('users', '&t=student').'">นักเรียน</a>')
        .'</span><div style="clear:both"></div></div>';
    return $f.list_table('บัญชีผู้ใช้', ['#', 'ชื่อผู้ใช้', 'ชื่อ-นามสกุล', 'ประเภท', 'สถานะ', 'จัดการ'], $rows, 'รีเซ็ตรหัสผ่าน = ตั้งเป็นชื่อผู้ใช้');
}

function adm_password(bool $post)
{
    if ($post) {
        $pw = (string) ($_POST['pw1'] ?? '');
        if (strlen($pw) >= 6 && $pw === (string) ($_POST['pw2'] ?? '')) {
            save_user((int) me()['id'], [], $pw);
            admin_done('password');
        }
        return msg_box('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษรและตรงกันทั้งสองช่อง', false).adm_password(false);
    }
    return admin_form('เปลี่ยนรหัสผ่าน', a_url('password'), [['รหัสผ่านใหม่', inp('pw1', '', 200, 'password', 'autocomplete="new-password"'), true], ['ยืนยันรหัสผ่านใหม่', inp('pw2', '', 200, 'password', 'autocomplete="new-password"'), true]], 'index.php');
}

/** หน้าแรกของครู: ข่าวประกาศ และทางกลับหน้าผู้ดูแลระบบ */
function rows_index_()
{
    $top = admin_news_box();
    if (is_admin()) {
        $top .= '<div style="width:983px;margin:0 auto 6px;text-align:right">'.btn('กลับหน้าผู้ดูแลระบบ', 'index.php?admin=1', 'cog').'</div>';
    }
    $tpl = (string) file_get_contents(MIS_APP.'pages/index_.html');
    return ['__CONTENT__' => preg_replace('/^(\s*<div[^>]*>)/', '$1'.str_replace('$', '\\$', $top), $tpl, 1)];
}
