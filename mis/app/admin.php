<?php
/**
 * mis/app/admin.php
 * หน้าผู้ดูแลระบบ (แทนหน้าจัดการของ SMS): 6 โมดูล
 * ทะเบียนและข้อมูลหลัก / บริหารงานวิชาการ / บริหารงานกิจการนักเรียน / บริหารงานบุคลากร / บริหารงานทั่วไป / การตั้งค่า
 * URL: ?module=admin&option=<ชื่อเมนู>[&func=...]
 */
defined('MIS') or exit;

function is_admin(): bool
{
    $u = me();
    return $u && ((int) $u['status'] === 1 || array_intersect(['can_config', 'can_manage_student', 'can_manage_course', 'can_manage_personnel'], $u['permission']));
}

/** เมนูของแต่ละโมดูล: [ชื่อ, ไอคอน, [หัวข้อ => [option => ชื่อเมนู]]] */
function admin_modules(): array
{
    return [
        'main' => ['ทะเบียนและข้อมูลหลัก', 'main', [
            'ข้อมูลหลัก' => ['class' => 'ระดับชั้น', 'room' => 'ห้องเรียน', 'program' => 'แผนการเรียน'],
            'ทะเบียนนักเรียน' => ['student' => 'ทะเบียนนักเรียน', 'student_import' => 'นำเข้ารายชื่อนักเรียน', 'student_class' => 'จัดการชั้นเรียน / ครูที่ปรึกษา', 'promote' => 'เลื่อนชั้นนักเรียน', 'stat' => 'สถิตินักเรียน'],
        ]],
        'academic' => ['บริหารงานวิชาการ', 'academic', [
            'หลักสูตร' => ['course' => 'รายวิชา', 'course_copy' => 'คัดลอกรายวิชาจากภาคเรียนก่อน', 'enroll' => 'ลงทะเบียนเรียน', 'sara' => 'กลุ่มสาระการเรียนรู้'],
            'ตารางสอนและการวัดผล' => ['timetable' => 'จัดตารางสอน', 'calendar' => 'ปฏิทินการเรียนการสอน', 'approve' => 'อนุมัติผลการเรียน', 'grade_report' => 'ผลการเรียนรายห้อง'],
        ]],
        'affairs' => ['บริหารงานกิจการนักเรียน', 'affairs', affairs_menu()],
        'personnel' => ['บริหารงานบุคลากร', 'personnel', [
            'ทะเบียนบุคลากร' => ['personnel' => 'บุคลากร', 'permission' => 'สิทธิ์การใช้งาน', 'position' => 'ตำแหน่ง', 'department' => 'กลุ่มงาน/กลุ่มสาระ'],
            'การปฏิบัติงาน' => ['leave' => 'บันทึกการลา', 'leave_report' => 'สรุปวันลา'],
        ]],
        'general' => ['บริหารงานทั่วไป', 'general', [
            'ประชาสัมพันธ์' => ['news' => 'ข่าวประกาศ', 'document' => 'เอกสาร/หนังสือเวียน'],
            'ระบบ' => ['log' => 'ประวัติการใช้งาน'],
        ]],
        'settings' => ['การตั้งค่า', 'settings', [
            'ตั้งค่าระบบ' => ['school' => 'ข้อมูลโรงเรียน / ปีการศึกษา', 'users' => 'บัญชีผู้ใช้', 'password' => 'เปลี่ยนรหัสผ่าน', 'teacher_view' => 'ไปหน้าครูผู้สอน'],
        ]],
    ];
}

function admin_option_info(string $option): array
{
    foreach (admin_modules() as $mk => [$mname, $icon, $sections]) {
        foreach ($sections as $sec => $items) {
            if (isset($items[$option])) {
                return [$mk, $mname, $sec, is_array($items[$option]) ? $items[$option][0] : $items[$option]];
            }
        }
    }
    return ['', '', '', ''];
}

/* ================================================================ หน้าตา */

function admin_top(): string
{
    $mods = admin_modules();
    $nav = '';
    $panels = '';
    $i = 0;
    foreach ($mods as $mk => [$name, $icon, $sections]) {
        if ($mk === 'settings') {
            continue;
        }
        $nav .= '<li id="am'.$i.'" onclick="adminMenu('.$i.')" style="margin-right:'.($i < 4 ? '5' : '0').'px"><a href="javascript:void(0);" class="adm-nav">'.h($name).'</a></li>';
        $cols = '';
        foreach ($sections as $sec => $items) {
            $links = '';
            foreach ($items as $op => $label) {
                [$text, $ic, $col] = is_array($label) ? $label + ['', 'fa-caret-right', '#0883DC'] : [$label, 'fa-caret-right', '#0883DC'];
                $links .= '<a class="waiting_link" href="?module=admin&option='.$op.'"><i class="fa '.$ic.'" style="width:18px;text-align:center;color:'.$col.'"></i> '.h($text).'</a>';
            }
            $cols .= '<div class="adm-col"><h3>'.h($sec).'</h3>'.$links.'</div>';
        }
        $panels .= '<div class="adm-panel" id="ap'.$i.'" style="display:none">'.$cols.'<div style="clear:both"></div></div>';
        $i++;
    }
    return $nav.'|'.$panels;
}

function admin_css(): string
{
    return '<style>
#nav li a.adm-nav{padding:6px 0;width:190px;text-align:center;font-family:thaisansbold;font-size:21px;color:#fff}
#nav li.selected{background-color:#00F0FF}#nav li.selected a.adm-nav{color:#000}
.adm-panel{width:978px;border:3px solid #A9F4F9;border-radius:8px;background:#fff;margin:4px 0 10px;padding:10px 0;text-align:left;position:absolute;z-index:21}
.adm-panel{background:linear-gradient(#fff 70%,#E9FBFF)}.adm-col{float:left;width:276px;margin:0 0 14px 24px;padding-right:20px;border-right:1px solid #eef5f8;min-height:120px}.adm-col:nth-child(3n){border-right:none}.adm-col:nth-child(3n+1){clear:left}
.adm-col h3{font-family:thaisansbold;font-weight:normal;font-size:30px;color:#0F5A9C;margin:4px 0 6px}
.adm-col a{display:block;font-size:12px;padding:4px 0;border-bottom:dashed 1px #ccc;color:#333}
.adm-tiles{width:984px;margin:30px auto;text-align:center}.adm-tile{display:inline-block;width:235px;margin:10px 0 30px;vertical-align:top}
.adm-tile h2{font-family:thaisansbold;font-size:24px;color:#019DF1;margin:0 0 8px}.adm-tile a{display:inline-block;width:150px;height:140px;border:1px solid #C9EFFB;border-radius:8px;background:#fff}
.adm-tile a:hover{box-shadow:0 0 10px #C9EFFB}.adm-tile img{width:116px;height:116px;margin-top:12px}
.adm-form td.titleinputfill{width:200px}.adm-news{width:983px;margin:0 auto 10px;text-align:left;border:1px solid #c6d5e1;border-radius:4px;background:#FFFDF2;padding:8px 12px}
</style>';
}

/** หัวหน้า (แถบชื่อ + กล่องชื่อหน้า) แบบระบบเดิม */
function admin_head(string $crumb1, string $crumb2, string $title, string $sub, string $buttons = ''): string
{
    return '<div style="padding-top:5px"><div id="page_topic_blue" style="width:967px"><div style="float:left"><h1 class=\'title_26\' style=\'font-size:21px;float:left;white-space:nowrap\'>&nbsp;'.h($crumb1)
        .'&nbsp;<img src="../images/white_br_next.png" border="0" style="width:5px"/>&nbsp;'.h($crumb2).'</h1></div><div style="float:right">'
        .'<span style="float:left;margin-top:3px;padding:5px 10px;background-image:url(../icon_tools/menubar_year.png); background-repeat:no-repeat;width:163px;height:22px;font-size:11px;font-weight:normal">แสดงข้อมูล ปีการศึกษา '.yt().'</span>'
        .'<span style="float:left;margin-top:3px;padding:0px 2px"><a href="index.php"><img src="../icon_tools/menubar_dashboard.png" border="0" alt="แผงเมนูหลัก" title="แผงเมนูหลัก"/></a></span></div></div>'
        .'<div id="maindetail" style="width:965px"><table border="0" cellpadding="0" cellspacing="0" style="margin-top:3px;width:967px"><tr><td align="left" valign="bottom" style="width:70px;white-space:nowrap">&nbsp;<img src="../icon_tools/icon_main.png"/></td>'
        .'<td align="left" valign="bottom" style="padding-left:5px;white-space:nowrap"><table border="0" cellspacing="0" cellpadding="0"><tr><td style="white-space:nowrap"><h1 class="header_subtitle" style="font-size:25px;white-space:nowrap">'.h($title).'</h1></td></tr>'
        .'<tr><td style="white-space:nowrap"><h1 class="blue_secondtitle" style="font-size:23px;white-space:nowrap">'.h($sub).'</h1></td></tr></table></td>'
        .'<td align="right" valign="middle" style="white-space:nowrap">'.$buttons.'</td></tr></table></div></div>'
        .'<div style="margin-top:10px;min-height:750px;z-index:100"><link rel="stylesheet" type="text/css" href="../css/tiny_style.css">';
}

function head_btn(string $label, string $href, string $icon): string
{
    return '<div style="float:left;margin:0px 2px"><a href="'.$href.'" title="'.h($label).'" style="display:inline-block;width:58px;height:56px;border:1px solid #ddd;border-radius:6px;background:#fafafa;text-align:center;font-size:11px;color:#555;padding-top:4px">'
        .'<img src="../icon_tools/vec_'.$icon.'_dark.png" height="24" style="margin-top:4px"/><br>'.h($label).'</a></div>';
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
        redirect('index.php');
    }
    if ($option === 'teacher_view') {
        setcookie('mis_teacher_view', '1', ['path' => '/mis/', 'samesite' => 'Lax']);
        redirect('index.php');
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
    [$mk, $mname, $sec, $label] = admin_option_info($option);
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
    if ($idx !== false && $idx < 5) {
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
    $js = '<script type="text/javascript">function adminMenu(i){for(var k=0;k<5;k++){var p=document.getElementById("ap"+k);if(p){p.style.display=(k===i&&p.style.display==="none")?"block":"none";}}}'
        .'document.addEventListener("click",function(e){if(!e.target.closest||(!e.target.closest("#nav")&&!e.target.closest(".adm-panel"))){for(var k=0;k<5;k++){var p=document.getElementById("ap"+k);if(p)p.style.display="none";}}});</script>';
    $GLOBALS['mis_top_override'] = $top;
    render_page($title, admin_css().$js.$content, '');
}

function admin_home(): string
{
    $news = admin_news_box();
    $tiles = '';
    foreach (admin_modules() as $mk => [$name, $icon, $sections]) {
        $first = array_key_first(reset($sections));
        $tiles .= '<div class="adm-tile"><h2>'.h($name).'</h2><a href="'.a_url($first).'"><img src="../images/admin/'.$icon.'.svg" alt=""/></a></div>';
    }
    $tiles .= '<div class="adm-tile"><h2>ออกจากระบบ</h2><a href="../logout.php"><img src="../images/admin/logout.svg" alt=""/></a></div>';
    return '<div style="padding-top:5px">'.$news.'<div class="adm-tiles">'.$tiles.'</div></div>';
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

function adm_class(bool $post) { return cat_page('class', 'class', 'ระดับชั้น', $post, fn($id) => [h(class_short($id)), count(rooms_by_class()[$id] ?? []).' ห้อง'], ['ชื่อย่อ', 'จำนวนห้องเรียน']); }
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

function adm_student(bool $post)
{
    $c = (string) ($_GET['c'] ?? '');
    $r = (string) ($_GET['r'] ?? '');
    $qtext = trim((string) ($_GET['q'] ?? ''));
    $sql = 'SELECT S.*, U.`name`, U.`username`, U.`active`, U.`sex`, U.`phone` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE 1';
    $p = [];
    if ($c !== '') {
        $sql .= ' AND S.`class`=?';
        $p[] = (int) $c;
    }
    if ($r !== '') {
        $sql .= ' AND S.`room`=?';
        $p[] = (int) $r;
    }
    if ($qtext !== '') {
        $sql .= ' AND (U.`name` LIKE ? OR S.`student_id` LIKE ?)';
        array_push($p, "%$qtext%", "%$qtext%");
    }
    $rows = '';
    foreach (q($sql.' ORDER BY S.`class`, S.`room`, S.`number`, S.`student_id` LIMIT 500', $p) as $i => $s) {
        $rows .= zrow([$i + 1, h($s['student_id']), name_nbsp($s['name']), $s['sex'] === 'f' ? 'หญิง' : ($s['sex'] === 'm' ? 'ชาย' : '-'), h(class_room_short($s['class'], $s['room'])), [h($s['number']), ' align="center"'],
            (int) $s['active'] ? 'ปกติ' : '<span style="color:#f00">พ้นสภาพ</span>', [edit_link(a_url('student', '&func=Edit&id='.(int) $s['id'])).' '.del_link(a_url('student', '&func=Delete&id='.(int) $s['id'])), ' align="center" width="60"']], $i);
    }
    $classes = ['' => 'ทุกระดับชั้น'] + array_map(fn($x) => $x, cats('class'));
    $rooms = ['' => 'ทุกห้อง'] + cats('room');
    $f = '<form method="get" style="width:983px;margin:0 auto 10px;text-align:left"><input type="hidden" name="module" value="admin"/><input type="hidden" name="option" value="student"/><span class="search">'
        .sel('c', $classes, $c, 'onchange="this.form.submit()"').' '.sel('r', $rooms, $r, 'onchange="this.form.submit()"').' '.inp('q', $qtext, 200, 'text', 'placeholder="ค้นหาชื่อ/รหัสนักเรียน"')
        .' <input type="submit" value="ค้นหา" class="general radius" style="height:25px"/></span><div style="clear:both"></div></form>';
    return ['ทะเบียนนักเรียนปัจจุบัน', $f.list_table('รายการทะเบียนนักเรียน', ['#', 'รหัสนร.', 'ชื่อ-นามสกุล', 'เพศ', 'ชั้น', 'เลขที่', 'สถานะ', ['', ' style="width:60px"']], $rows, '',
        btn('เพิ่มนักเรียน', a_url('student', '&func=Edit'), 'add').btn('นำเข้ารายชื่อ', a_url('student_import'), 'add')), head_btn('เพิ่ม', a_url('student', '&func=Edit'), 'add')];
}

function title_options(): array
{
    return ['' => '-', 'เด็กชาย' => 'เด็กชาย', 'เด็กหญิง' => 'เด็กหญิง', 'นาย' => 'นาย', 'นางสาว' => 'นางสาว', 'นาง' => 'นาง'];
}

function adm_student_Edit(bool $post)
{
    $id = (int) ($_GET['id'] ?? 0);
    $s = $id ? q1('SELECT S.*, U.`name`, U.`username`, U.`active`, U.`sex`, U.`phone`, U.`birthday`, U.`id_card` uid FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`id`=?', [$id]) : null;
    [$pre, $first, $last] = split_name((string) ($s['name'] ?? ''));
    $rows = [
        ['รหัสนักเรียน', inp('student_id', $s['student_id'] ?? '', 150), true],
        ['คำนำหน้าชื่อ', sel('title', title_options(), $pre), false],
        ['ชื่อ', inp('first', $first), true], ['นามสกุล', inp('last', $last), true],
        ['เพศ', sel('sex', ['' => '-', 'm' => 'ชาย', 'f' => 'หญิง'], $s['sex'] ?? ''), false],
        ['เลขประจำตัวประชาชน', inp('id_card', $s['id_card'] ?? ($s['uid'] ?? ''), 200), false],
        ['วันเกิด', inp('birthday', !empty($s['birthday']) ? iso_to_be($s['birthday']) : '', 120, 'text', 'placeholder="วว/ดด/ปปปป"'), false],
        ['ระดับชั้น', sel('class', ['' => '- เลือก -'] + cats('class'), $s['class'] ?? ''), true],
        ['ห้องเรียน', sel('room', ['0' => '- เลือก -'] + cats('room'), $s['room'] ?? 0), true],
        ['เลขที่', inp('number', $s['number'] ?? '', 80), false],
        ['เบอร์โทรศัพท์', inp('phone', $s['phone'] ?? '', 200), false],
        ['ที่อยู่', txt('address', $s['address'] ?? ''), false],
        ['ชื่อผู้ปกครอง', inp('parent', $s['parent'] ?? ''), false], ['โทรศัพท์ผู้ปกครอง', inp('parent_phone', $s['parent_phone'] ?? '', 200), false],
        ['สถานะ', sel('active', ['1' => 'ปกติ', '0' => 'พ้นสภาพ'], $s['active'] ?? 1), false],
        ['รหัสผ่านเข้าระบบ', inp('password', '', 200, 'password', 'autocomplete="new-password" placeholder="'.($s ? 'เว้นว่างถ้าไม่เปลี่ยน' : 'ค่าเริ่มต้น = รหัสนักเรียน').'"'), false],
    ];
    return [$s ? 'แก้ไขข้อมูลนักเรียน' : 'เพิ่มนักเรียน', admin_form($s ? 'แก้ไขข้อมูลนักเรียน' : 'เพิ่มนักเรียน', a_url('student', '&func=Save&id='.$id), $rows, a_url('student'))];
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

function adm_student_Save(bool $post)
{
    $id = (int) ($_GET['id'] ?? 0);
    $code = trim((string) ($_POST['student_id'] ?? ''));
    $first = trim((string) ($_POST['first'] ?? ''));
    $last = trim((string) ($_POST['last'] ?? ''));
    if (!$post || $code === '' || $first === '' || username_taken($code, $id)) {
        redirect(a_url('student', '&func=Edit&id='.$id));
    }
    $card = preg_replace('/\D/', '', (string) ($_POST['id_card'] ?? ''));
    $bd = trim((string) ($_POST['birthday'] ?? ''));
    $uid = save_user($id, ['username' => $code, 'name' => trim(($_POST['title'] ?? '').$first.' '.$last), 'sex' => in_array($_POST['sex'] ?? '', ['m', 'f'], true) ? $_POST['sex'] : null,
        'phone' => trim((string) ($_POST['phone'] ?? '')), 'birthday' => $bd !== '' ? be_date_to_iso($bd) : null, 'active' => (int) ($_POST['active'] ?? 1), 'status' => 0],
        (string) ($_POST['password'] ?? '') !== '' ? (string) $_POST['password'] : ($id ? '' : $code));
    $st = [(string) $code, mb_substr((string) ($_POST['address'] ?? ''), 0, 100), mb_substr((string) ($_POST['parent'] ?? ''), 0, 100), mb_substr((string) ($_POST['parent_phone'] ?? ''), 0, 32),
        (int) ($_POST['class'] ?? 0), (int) ($_POST['room'] ?? 0), ($_POST['number'] ?? '') === '' ? null : (int) $_POST['number'], strlen($card) === 13 ? $card : null];
    if (q1('SELECT 1 x FROM {student} WHERE `id`=?', [$uid])) {
        q('UPDATE {student} SET `student_id`=?, `address`=?, `parent`=?, `parent_phone`=?, `class`=?, `room`=?, `number`=?, `id_card`=? WHERE `id`=?', array_merge($st, [$uid]));
    } else {
        q('INSERT INTO {student} (`student_id`,`address`,`parent`,`parent_phone`,`class`,`room`,`number`,`id_card`,`department`,`id`) VALUES (?,?,?,?,?,?,?,?,0,?)', array_merge($st, [$uid]));
    }
    admin_done('student');
}

function adm_student_Delete(bool $post)
{
    $id = (int) ($_GET['id'] ?? 0);
    if ($id && q1('SELECT 1 x FROM {student} WHERE `id`=?', [$id])) {
        q('DELETE FROM {grade} WHERE `student_id`=?', [$id]);
        q('DELETE FROM {student} WHERE `id`=?', [$id]);
        q('DELETE FROM {user_session} WHERE `member_id`=?', [$id]);
        q('DELETE FROM {user} WHERE `id`=? AND `status`<>1', [$id]);
    }
    admin_done('student');
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
    $msg = '';
    if ($post && !empty($_FILES['csv']['tmp_name'])) {
        $rooms = array_flip(cats('room'));
        $classIds = array_keys(cats('class'));
        $n = $skip = 0;
        $fh = fopen($_FILES['csv']['tmp_name'], 'r');
        $first = true;
        while (($row = fgetcsv($fh)) !== false) {
            $row = array_map(fn($v) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $v)), $row);
            if ($first && !preg_match('/^\d+$/', $row[0] ?? '')) {
                $first = false;
                continue;
            }
            $first = false;
            [$code, $title, $fn, $ln, $sex, $cls, $room, $num, $card] = array_pad($row, 9, '');
            if ($code === '' || $fn === '' || username_taken($code, 0)) {
                $skip++;
                continue;
            }
            $cid = ctype_digit($cls) ? ($classIds[(int) $cls - 1] ?? 0) : (int) array_search($cls, cats('class'));
            $uid = save_user(0, ['username' => $code, 'name' => trim($title.$fn.' '.$ln), 'sex' => in_array($sex, ['ช', 'ชาย', 'm'], true) ? 'm' : (in_array($sex, ['ญ', 'หญิง', 'f'], true) ? 'f' : null), 'status' => 0, 'active' => 1], $code);
            q('INSERT INTO {student} (`id`,`student_id`,`class`,`room`,`number`,`id_card`,`department`) VALUES (?,?,?,?,?,?,0)', [$uid, $code, (int) $cid, (int) ($rooms[$room] ?? 0), $num === '' ? null : (int) $num, strlen($card) === 13 ? $card : null]);
            $n++;
        }
        $msg = msg_box("นำเข้าแล้ว $n คน".($skip ? " ข้าม $skip แถว (ข้อมูลไม่ครบหรือรหัสซ้ำ)" : ''));
    }
    $help = 'ไฟล์ CSV (UTF-8) คอลัมน์: รหัสนักเรียน, คำนำหน้า, ชื่อ, นามสกุล, เพศ (ช/ญ), ระดับชั้น (ลำดับ เช่น 1 หรือชื่อเต็ม), ห้อง (เช่น 1/1), เลขที่, เลขบัตรประชาชน — รหัสผ่านเริ่มต้นคือรหัสนักเรียน';
    return $msg.admin_form('นำเข้ารายชื่อนักเรียน', a_url('student_import'), [['ไฟล์ CSV', '<input type="file" name="csv" accept=".csv"/><br><span class="blue_11_fade">'.h($help).'</span>', true]], a_url('student'));
}

function adm_student_class(bool $post)
{
    if ($post) {
        foreach ((array) ($_POST['adv'] ?? []) as $room => $pid) {
            $room = (int) $room;
            q('UPDATE {personnel} SET `class`=0, `room`=0 WHERE `room`=?', [$room]);
            if ((int) $pid) {
                $cid = 0;
                foreach (rooms_by_class() as $c => $rs) {
                    if (isset($rs[$room])) {
                        $cid = (int) $c;
                    }
                }
                if (!q1('SELECT 1 x FROM {personnel} WHERE `id`=?', [(int) $pid])) {
                    q('INSERT INTO {personnel} (`id`,`position`,`department`,`order`) VALUES (?,0,0,0)', [(int) $pid]);
                }
                q('UPDATE {personnel} SET `class`=?, `room`=? WHERE `id`=?', [$cid, $room, (int) $pid]);
            }
        }
        admin_done('student_class');
    }
    $adv = [];
    foreach (q('SELECT `id`,`room` FROM {personnel} WHERE `room`>0') as $p) {
        $adv[(int) $p['room']] = (int) $p['id'];
    }
    $opts = [0 => '- ไม่กำหนด -'] + personnel_options();
    $cnt = [];
    foreach (q('SELECT `room`, COUNT(*) n FROM {student} GROUP BY `room`') as $r) {
        $cnt[(int) $r['room']] = (int) $r['n'];
    }
    $rows = '';
    $i = 0;
    foreach (rooms_by_class() as $cid => $rooms) {
        foreach ($rooms as $rid => $rname) {
            $rows .= zrow([h($cid ? class_room_short($cid, $rid) : $rname), [$cnt[$rid] ?? 0, ' align="center"'], sel("adv[$rid]", $opts, $adv[$rid] ?? 0, 'style="width:300px;height:25px"')], $i++);
        }
    }
    return '<form name="sc" method="post">'.list_table('จัดการชั้นเรียน ประจำปีการศึกษา '.yt(), ['ห้อง', ['จำนวนนักเรียน', ' style="text-align:center"'], 'ครูที่ปรึกษา'], $rows, '',
        '<a href="javascript:void(0);" onclick="document.forms.sc.submit();" id="pinkgreenbtns"><img src="../icon_tools/vec_add_white.png" border="0" height="16"/> บันทึก</a>').'</form>';
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
        q('UPDATE {user} U JOIN {student} S ON S.`id`=U.`id` SET U.`active`=0 WHERE S.`class`=?', [(int) $last]);
        for ($k = count($ids) - 2; $k >= 0; $k--) {
            foreach (q('SELECT `id`, `room` FROM {student} WHERE `class`=?', [(int) $ids[$k]]) as $s) {
                $rn = $rooms[$s['room']] ?? '';
                $new = preg_match('/^(\d+)\/(.+)$/', $rn, $m) ? ($byName[((int) $m[1] + 1).'/'.$m[2]] ?? $s['room']) : $s['room'];
                q('UPDATE {student} SET `class`=?, `room`=? WHERE `id`=?', [(int) $ids[$k + 1], (int) $new, (int) $s['id']]);
            }
        }
        admin_done('promote');
    }
    $body = '<p>เลื่อนนักเรียนทุกคนขึ้นหนึ่งระดับชั้น (ห้อง 1/1 → 2/1) นักเรียนชั้นสุดท้าย ('.h(end($classes) ?: '-').') จะถูกตั้งเป็นพ้นสภาพ (จบการศึกษา)</p>'
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
        q('UPDATE {personnel} SET `class`=0, `room`=0 WHERE `room`=? AND `id`<>?', [$room, $uid]);
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

function adm_school(bool $post)
{
    $file = ROOT_PATH.'settings/config.php';
    if ($post) {
        $config = \Kotchasan\Config::load($file);
        foreach (['school_name', 'web_title', 'phone', 'address'] as $k) {
            $config->$k = mb_substr(trim((string) ($_POST[$k] ?? '')), 0, 200);
        }
        $config->academic_year = max(2500, min(2700, (int) ($_POST['academic_year'] ?? 0)));
        $config->term = (int) ($_POST['term'] ?? 1) === 2 ? 2 : 1;
        \Kotchasan\Config::save($config, $file);
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
        setcookie('mis_term', '', ['expires' => time() - 3600, 'path' => '/mis/']);
        admin_done('school');
    }
    $rows = [
        ['ชื่อโรงเรียน', inp('school_name', cfg('school_name', 'โรงเรียนดรุณราษฎร์วิทยา'), 400), true],
        ['ชื่อระบบ', inp('web_title', cfg('web_title', ''), 400), false],
        ['ที่อยู่', inp('address', cfg('address', ''), 500), false],
        ['โทรศัพท์', inp('phone', cfg('phone', ''), 200), false],
        ['ปีการศึกษาปัจจุบัน', inp('academic_year', cfg('academic_year', date('Y') + 543), 80), true],
        ['ภาคเรียนปัจจุบัน', sel('term', [1 => '1', 2 => '2'], cfg('term', 1)), true],
    ];
    return admin_form('ข้อมูลโรงเรียน / ปีการศึกษา', a_url('school'), $rows, 'index.php');
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
