<?php
/**
 * mis/app/affairs.php
 * โมดูล บริหารงานกิจการนักเรียน (ระบบดูแลช่วยเหลือนักเรียน) ตามเมนูของระบบเดิม 6 หมวด
 * ข้อมูลทุกเมนูเก็บใน {mis_data} ผ่านหน้า CRUD แบบเดียวกัน (md_page)
 */
defined('MIS') or exit;

function affairs_menu(): array
{
    return [
        'รู้จักนักเรียนเป็นรายบุคคล' => [
            'sa_profile' => ['รู้จักนักเรียนเป็นรายบุคคล', 'fa-user', '#C0392B'],
            'sa_visit' => ['การเยี่ยมบ้านนักเรียน', 'fa-home', '#E74C3C'],
            'sa_visit_sum' => ['สรุปการเยี่ยมบ้านและข้อมูลด้านต่าง ๆ', 'fa-list-alt', '#8E44AD'],
            'sa_observe' => ['การสังเกตพฤติกรรมนักเรียน', 'fa-user-md', '#2980B9'],
            'sa_eq' => ['ประเมินความฉลาดทางอารมณ์', 'fa-heart', '#E74C3C'],
            'sa_lifeskill' => ['สำรวจทักษะการดำรงชีวิต', 'fa-street-view', '#D35400'],
            'sa_mi' => ['ประเมินพหุปัญญา', 'fa-lightbulb-o', '#F39C12'],
            'sa_depress' => ['วัดโรคซึมเศร้า', 'fa-pencil', '#27AE60'],
            'sa_stress' => ['ประเมินความเครียด', 'fa-bolt', '#2C3E50'],
        ],
        'คัดกรองนักเรียน' => [
            'sa_sdq' => ['ประเมิน SDQ', 'fa-folder', '#F39C12'],
            'sa_strength' => ['การประเมินจุดแข็งและจุดอ่อน', 'fa-desktop', '#2C3E50'],
            'sa_screen' => ['คัดกรองนักเรียนเป็นรายบุคคล', 'fa-search', '#D35400'],
            'sa_screen_sum' => ['สรุปผลการคัดกรองนักเรียน', 'fa-book', '#16A085'],
        ],
        'ส่งเสริมป้องกันและแก้ไข' => [
            'sa_parent_meet' => ['การประชุมผู้ปกครองนักเรียน', 'fa-users', '#C0392B'],
            'sa_homeroom' => ['กิจกรรมโฮมรูม', 'fa-address-book', '#D35400'],
            'sa_counsel' => ['การให้คำปรึกษา', 'fa-comments', '#27AE60'],
            'sa_plan' => ['การวางแผนส่งเสริมและช่วยเหลือนักเรียน', 'fa-calendar', '#E67E22'],
            'sa_promote_sum' => ['สรุปการส่งเสริมและพัฒนาผู้เรียน', 'fa-bar-chart', '#C0392B'],
            'sa_help_sum' => ['สรุปการให้ความช่วยเหลือนักเรียน', 'fa-check-circle', '#2980B9'],
            'sa_research' => ['การวิจัยเชิงปฏิบัติ', 'fa-pie-chart', '#E67E22'],
            'sa_report' => ['บันทึกผลการปฏิบัติงานตามระบบดูแลฯ', 'fa-clipboard', '#7F8C8D'],
            'sa_person_sum' => ['สรุปการคัดกรองและช่วยเหลือฯ รายบุคคล', 'fa-check', '#27AE60'],
        ],
        'บันทึกการส่งต่อ' => [
            'sa_refer_in' => ['การส่งต่อ (ภายใน)', 'fa-file-text-o', '#7F8C8D'],
            'sa_refer_out' => ['การส่งต่อ (ภายนอก)', 'fa-folder-open', '#F39C12'],
            'sa_refer_result' => ['แจ้งผลการช่วยเหลือ', 'fa-book', '#16A085'],
            'sa_refer_sum' => ['ผลการปฏิบัติงานตามระบบดูแลช่วยเหลือฯ', 'fa-user', '#2980B9'],
        ],
        'พฤติกรรมนักเรียน' => [
            'sa_rule' => ['กำหนดเกณฑ์ตัดคะแนนความประพฤติ', 'fa-gavel', '#A0522D'],
            'sa_punish' => ['กำหนดวิธีการลงโทษนักเรียนกระทำผิด', 'fa-th-large', '#2980B9'],
            'sa_bad' => ['พฤติกรรมบกพร่อง', 'fa-heart', '#95A5A6'],
            'sa_good' => ['พฤติกรรมดีเด่น', 'fa-heart', '#E74C3C'],
            'sa_flag' => ['การเข้าแถวหน้าเสาธง', 'fa-flag', '#27AE60'],
            'sa_leave' => ['การขออนุญาตออกนอกโรงเรียน', 'fa-square-o', '#27AE60'],
            'sa_conduct' => ['ออกรายงานความประพฤตินักเรียน', 'fa-list-alt', '#2980B9'],
            'sa_letter' => ['ออกจดหมายถึงผู้ปกครองนักเรียน', 'fa-envelope', '#2C3E50'],
        ],
        'ทุนการศึกษา' => [
            'sa_fund' => ['กำหนดทุนการศึกษาของโรงเรียน', 'fa-briefcase', '#2980B9'],
            'sa_fund_student' => ['นักเรียนได้รับทุนการศึกษา', 'fa-star', '#F39C12'],
        ],
    ];
}

/* ---------------------------------------------------------------- ตัวช่วย */

function room_options(): array
{
    $o = [];
    foreach (rooms_by_class() as $cid => $rooms) {
        foreach ($rooms as $rid => $n) {
            $o[$rid] = $cid ? class_room_short($cid, $rid) : $n;
        }
    }
    return $o;
}

/** ตัวเลือกจากรายการใน mis_data (เช่นเกณฑ์ตัดคะแนน) */
function md_options(string $kind, string $field, string $scope = ''): array
{
    $o = [];
    foreach (md_rows($kind, null, $scope) as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        $o[$r['r1']] = $d[$field] ?? $r['r1'];
    }
    return $o;
}

/** ระดับผลจากคะแนนตามเกณฑ์ [คะแนนต่ำสุด => ข้อความ] (เรียงจากมากไปน้อย) */
function level_by(array $levels): callable
{
    krsort($levels);
    return function ($d) use ($levels) {
        if (!isset($d['score']) || $d['score'] === '') {
            return '-';
        }
        foreach ($levels as $min => [$text, $color]) {
            if ((float) $d['score'] >= $min) {
                return '<span style="color:'.$color.';font-weight:bold">'.h($text).'</span>';
            }
        }
        return '-';
    };
}

const RISK = ['ปกติ' => 'ปกติ', 'เสี่ยง' => 'กลุ่มเสี่ยง', 'มีปัญหา' => 'กลุ่มมีปัญหา'];

function f_student(): array { return ['นักเรียน', 'select', 'student_options', true]; }
function f_date(string $l = 'วันที่'): array { return [$l, 'date', null, true]; }
function f_text(string $l, bool $req = false): array { return [$l, 'text', null, $req]; }
function f_area(string $l, bool $req = false): array { return [$l, 'textarea', null, $req]; }
function f_num(string $l, bool $req = true): array { return [$l, 'number', null, $req]; }
function f_sel(string $l, $opts, bool $req = true): array { return [$l, 'select', $opts, $req]; }
function f_file(string $l = 'ไฟล์แนบ'): array { return [$l, 'file', null, false]; }

/** หน้าแบบประเมินที่บันทึกคะแนนรวมและแปลผล */
function assess_page(string $kind, string $option, string $label, array $levels, string $hint, bool $post)
{
    $body = md_page($kind, $option, 'ผล'.$label, ['student' => f_student(), 'date' => f_date('วันที่ประเมิน'), 'score' => f_num('คะแนนรวม'), 'note' => f_area('ข้อสังเกต/การช่วยเหลือ')],
        ['student' => 'นักเรียน', 'date' => 'วันที่ประเมิน', 'score' => 'คะแนน', 'lv' => ['ผลการประเมิน', level_by($levels)]], $post, yt());
    if (is_string($body)) {
        $body = '<p style="width:983px;margin:0 auto 8px;text-align:left" class="gray_12">'.h($hint).'</p>'.$body;
    }
    return $body;
}

/* ---------------------------------------------------------------- รู้จักนักเรียนเป็นรายบุคคล */

function adm_sa_profile(bool $post)
{
    return md_page('sa_profile', 'sa_profile', 'ข้อมูลนักเรียนรายบุคคล', [
        'student' => f_student(), 'talent' => f_text('ความสามารถพิเศษ'), 'health' => f_text('ด้านสุขภาพ/โรคประจำตัว'), 'family' => f_sel('สถานภาพครอบครัว', ['อยู่ด้วยกัน' => 'บิดามารดาอยู่ด้วยกัน', 'แยกกันอยู่' => 'แยกกันอยู่', 'หย่าร้าง' => 'หย่าร้าง', 'เสียชีวิต' => 'บิดา/มารดาเสียชีวิต', 'อื่น ๆ' => 'อื่น ๆ'], false),
        'income' => f_text('รายได้ครอบครัว/เดือน'), 'live_with' => f_text('อาศัยอยู่กับ'), 'travel' => f_text('การเดินทางมาโรงเรียน'), 'note' => f_area('ข้อมูลอื่น ๆ'),
    ], ['student' => 'นักเรียน', 'talent' => 'ความสามารถพิเศษ', 'health' => 'สุขภาพ', 'family' => 'ครอบครัว', 'live_with' => 'อาศัยอยู่กับ'], $post);
}

function adm_sa_visit(bool $post)
{
    return md_page('sa_visit', 'sa_visit', 'การเยี่ยมบ้าน', [
        'student' => f_student(), 'date' => f_date('วันที่เยี่ยมบ้าน'), 'visitor' => f_sel('ครูผู้เยี่ยม', 'personnel_options', false),
        'house' => f_sel('สภาพที่อยู่อาศัย', ['ดี' => 'มั่นคง ถาวร', 'พอใช้' => 'พอใช้', 'ทรุดโทรม' => 'ทรุดโทรม'], false), 'economy' => f_sel('ฐานะทางเศรษฐกิจ', ['ดี' => 'ดี', 'ปานกลาง' => 'ปานกลาง', 'ยากจน' => 'ยากจน'], false),
        'risk' => f_sel('ผลการเยี่ยมบ้าน', RISK, false), 'detail' => f_area('สิ่งที่พบ/ข้อเสนอแนะ'), 'photo' => f_file('ภาพถ่าย/เอกสาร'),
    ], ['student' => 'นักเรียน', 'date' => 'วันที่', 'visitor' => 'ครูผู้เยี่ยม', 'economy' => 'ฐานะ', 'risk' => 'ผล', 'photo' => 'ไฟล์'], $post, yt());
}

/** สรุปต่อห้อง: นักเรียนทั้งหมด / มีข้อมูลในชนิดที่กำหนด / แยกตามค่าในช่องที่กำหนด */
function room_summary(string $kind, string $field, array $values, string $title, string $scope = ''): string
{
    $by = [];
    foreach (md_rows($kind, null, $scope) as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        if (!empty($d['student'])) {
            $by[(int) $d['student']] = $d[$field] ?? '';
        }
    }
    $rows = '';
    $i = 0;
    $tot = array_fill_keys(array_merge(['all', 'done'], array_keys($values)), 0);
    foreach (rooms_by_class() as $cid => $rooms) {
        foreach ($rooms as $rid => $n) {
            $ids = array_map(fn($s) => (int) $s['id'], q('SELECT S.`id` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`room`=? AND U.`active`=1', [$rid]));
            $done = array_intersect_key($by, array_flip($ids));
            $cells = [h($cid ? class_room_short($cid, $rid) : $n), [count($ids), ' align="center"'], [count($done), ' align="center"'], [count($ids) ? number_format(count($done) * 100 / count($ids), 2) : '0.00', ' align="center"']];
            $tot['all'] += count($ids);
            $tot['done'] += count($done);
            foreach ($values as $v => $l) {
                $c = count(array_filter($done, fn($x) => (string) $x === (string) $v));
                $tot[$v] += $c;
                $cells[] = [$c, ' align="center"'];
            }
            $rows .= zrow($cells, $i++);
        }
    }
    $cells = ['<b>รวม</b>', [$tot['all'], ' align="center"'], [$tot['done'], ' align="center"'], [$tot['all'] ? number_format($tot['done'] * 100 / $tot['all'], 2) : '0.00', ' align="center"']];
    foreach ($values as $v => $l) {
        $cells[] = [$tot[$v], ' align="center"'];
    }
    $rows .= zrow($cells, $i);
    return list_table($title, array_merge(['ห้อง', 'นักเรียน', 'ดำเนินการแล้ว', 'ร้อยละ'], array_values($values)), $rows);
}

function adm_sa_visit_sum(bool $post)
{
    return room_summary('sa_visit', 'risk', RISK, 'สรุปการเยี่ยมบ้านนักเรียน ปีการศึกษา '.yt(), yt())
        .'<div style="height:10px"></div>'.room_summary('sa_visit', 'economy', ['ดี' => 'ฐานะดี', 'ปานกลาง' => 'ปานกลาง', 'ยากจน' => 'ยากจน'], 'สรุปฐานะทางเศรษฐกิจ', yt());
}

function adm_sa_observe(bool $post)
{
    return md_page('sa_observe', 'sa_observe', 'การสังเกตพฤติกรรม', ['student' => f_student(), 'date' => f_date(), 'behavior' => f_area('พฤติกรรมที่สังเกตพบ', true),
        'type' => f_sel('ลักษณะ', ['ด้านบวก' => 'ด้านบวก', 'ด้านลบ' => 'ด้านลบ', 'ทั่วไป' => 'ทั่วไป'], false), 'action' => f_area('การดำเนินการ')],
        ['student' => 'นักเรียน', 'date' => 'วันที่', 'type' => 'ลักษณะ', 'behavior' => 'พฤติกรรม'], $post, yt());
}

function adm_sa_eq(bool $post)
{
    return assess_page('sa_eq', 'sa_eq', 'ประเมินความฉลาดทางอารมณ์ (EQ)', [0 => ['ต่ำกว่าปกติ', '#E74C3C'], 140 => ['ปกติ', '#27AE60'], 171 => ['สูงกว่าปกติ', '#2980B9']],
        'บันทึกคะแนนรวมจากแบบประเมินความฉลาดทางอารมณ์ของกรมสุขภาพจิต (เกณฑ์ปกติประมาณ 140-170 คะแนน)', $post);
}

function adm_sa_lifeskill(bool $post)
{
    return assess_page('sa_lifeskill', 'sa_lifeskill', 'สำรวจทักษะการดำรงชีวิต', [0 => ['ควรพัฒนา', '#E74C3C'], 50 => ['พอใช้', '#E67E22'], 70 => ['ดี', '#27AE60'], 85 => ['ดีมาก', '#2980B9']],
        'บันทึกคะแนนร้อยละจากแบบสำรวจทักษะการดำรงชีวิต', $post);
}

function adm_sa_mi(bool $post)
{
    $int = ['ภาษา' => 'ด้านภาษา', 'ตรรกะ' => 'ด้านตรรกะและคณิตศาสตร์', 'มิติ' => 'ด้านมิติสัมพันธ์', 'ดนตรี' => 'ด้านดนตรี', 'กาย' => 'ด้านร่างกายและการเคลื่อนไหว',
        'สัมพันธ์' => 'ด้านมนุษยสัมพันธ์', 'ตนเอง' => 'ด้านความเข้าใจตนเอง', 'ธรรมชาติ' => 'ด้านธรรมชาติวิทยา'];
    return md_page('sa_mi', 'sa_mi', 'ผลประเมินพหุปัญญา', ['student' => f_student(), 'date' => f_date('วันที่ประเมิน'), 'top1' => f_sel('ด้านที่โดดเด่นที่สุด', $int), 'top2' => f_sel('ด้านที่โดดเด่นรองลงมา', $int, false), 'note' => f_area('ข้อเสนอแนะ')],
        ['student' => 'นักเรียน', 'date' => 'วันที่', 'top1' => 'โดดเด่นที่สุด', 'top2' => 'รองลงมา'], $post, yt());
}

function adm_sa_depress(bool $post)
{
    return assess_page('sa_depress', 'sa_depress', 'วัดโรคซึมเศร้า (9Q)', [0 => ['ไม่มีอาการ/น้อยมาก', '#27AE60'], 7 => ['ระดับน้อย', '#F39C12'], 13 => ['ระดับปานกลาง', '#E67E22'], 19 => ['ระดับรุนแรง', '#E74C3C']],
        'บันทึกคะแนนรวมจากแบบประเมินโรคซึมเศร้า 9 คำถาม (9Q) ของกรมสุขภาพจิต: น้อยกว่า 7 ไม่มีอาการ, 7-12 น้อย, 13-18 ปานกลาง, 19 ขึ้นไป รุนแรง (ควรส่งต่อผู้เชี่ยวชาญ)', $post);
}

function adm_sa_stress(bool $post)
{
    return assess_page('sa_stress', 'sa_stress', 'ประเมินความเครียด (ST-5)', [0 => ['เครียดน้อย', '#27AE60'], 5 => ['เครียดปานกลาง', '#F39C12'], 8 => ['เครียดมาก', '#E67E22'], 10 => ['เครียดมากที่สุด', '#E74C3C']],
        'บันทึกคะแนนรวมจากแบบประเมินความเครียด ST-5 ของกรมสุขภาพจิต (0-4 น้อย, 5-7 ปานกลาง, 8-9 มาก, 10-15 มากที่สุด)', $post);
}

/* ---------------------------------------------------------------- คัดกรอง */

function adm_sa_sdq(bool $post)
{
    return assess_page('sa_sdq', 'sa_sdq', 'ประเมิน SDQ', [0 => ['ปกติ', '#27AE60'], 12 => ['กลุ่มเสี่ยง', '#F39C12'], 16 => ['กลุ่มมีปัญหา', '#E74C3C']],
        'บันทึกคะแนนรวมปัญหา (Total difficulties) จากแบบประเมิน SDQ ฉบับครู: 0-11 ปกติ, 12-15 เสี่ยง, 16 ขึ้นไป มีปัญหา', $post);
}

function adm_sa_strength(bool $post)
{
    return md_page('sa_strength', 'sa_strength', 'จุดแข็งและจุดอ่อน', ['student' => f_student(), 'date' => f_date(), 'strong' => f_area('จุดแข็ง', true), 'weak' => f_area('จุดอ่อน/สิ่งที่ควรพัฒนา'), 'plan' => f_area('แนวทางพัฒนา')],
        ['student' => 'นักเรียน', 'date' => 'วันที่', 'strong' => 'จุดแข็ง', 'weak' => 'จุดอ่อน'], $post, yt());
}

const SCREEN_AREAS = ['learn' => 'ด้านการเรียน', 'health' => 'ด้านสุขภาพ', 'economy' => 'ด้านเศรษฐกิจ', 'safety' => 'ด้านความปลอดภัย', 'drug' => 'ด้านสารเสพติด', 'sex' => 'ด้านพฤติกรรมทางเพศ', 'emotion' => 'ด้านอารมณ์/พฤติกรรม'];

function screen_group(array $d): string
{
    $vals = array_map(fn($k) => $d[$k] ?? '', array_keys(SCREEN_AREAS));
    return in_array('มีปัญหา', $vals, true) ? 'มีปัญหา' : (in_array('เสี่ยง', $vals, true) ? 'เสี่ยง' : 'ปกติ');
}

function adm_sa_screen(bool $post)
{
    $fields = ['student' => f_student(), 'date' => f_date('วันที่คัดกรอง')];
    foreach (SCREEN_AREAS as $k => $l) {
        $fields[$k] = f_sel($l, RISK, false);
    }
    $fields['note'] = f_area('หมายเหตุ');
    return md_page('sa_screen', 'sa_screen', 'การคัดกรองนักเรียน', $fields, ['student' => 'นักเรียน', 'date' => 'วันที่',
        'g' => ['ผลการคัดกรอง', fn($d) => ['ปกติ' => '<span style="color:#27AE60">กลุ่มปกติ</span>', 'เสี่ยง' => '<span style="color:#E67E22">กลุ่มเสี่ยง</span>', 'มีปัญหา' => '<span style="color:#E74C3C">กลุ่มมีปัญหา</span>'][screen_group($d)]],
        'risk' => ['ด้านที่เสี่ยง/มีปัญหา', fn($d) => h(implode(', ', array_filter(array_map(fn($k, $l) => in_array($d[$k] ?? '', ['เสี่ยง', 'มีปัญหา'], true) ? $l : '', array_keys(SCREEN_AREAS), SCREEN_AREAS))))]], $post, yt());
}

function adm_sa_screen_sum(bool $post)
{
    // เติมผลกลุ่มลงข้อมูลชั่วคราวเพื่อใช้สรุปรายห้อง
    $by = [];
    foreach (md_rows('sa_screen', null, yt()) as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        if (!empty($d['student'])) {
            $by[(int) $d['student']] = $d;
        }
    }
    $rows = '';
    $i = 0;
    foreach (rooms_by_class() as $cid => $rooms) {
        foreach ($rooms as $rid => $n) {
            $ids = array_map(fn($s) => (int) $s['id'], q('SELECT S.`id` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`room`=? AND U.`active`=1', [$rid]));
            $g = ['ปกติ' => 0, 'เสี่ยง' => 0, 'มีปัญหา' => 0];
            $area = array_fill_keys(array_keys(SCREEN_AREAS), 0);
            $done = 0;
            foreach ($ids as $id) {
                if (isset($by[$id])) {
                    $done++;
                    $g[screen_group($by[$id])]++;
                    foreach (SCREEN_AREAS as $k => $l) {
                        if (in_array($by[$id][$k] ?? '', ['เสี่ยง', 'มีปัญหา'], true)) {
                            $area[$k]++;
                        }
                    }
                }
            }
            $cells = [h($cid ? class_room_short($cid, $rid) : $n), [count($ids), ' align="center"'], [$done, ' align="center"'], [$g['ปกติ'], ' align="center"'], [$g['เสี่ยง'], ' align="center" style="color:#E67E22"'], [$g['มีปัญหา'], ' align="center" style="color:#E74C3C"']];
            foreach ($area as $c) {
                $cells[] = [$c, ' align="center"'];
            }
            $rows .= zrow($cells, $i++);
        }
    }
    return list_table('สรุปผลการคัดกรองนักเรียน ปีการศึกษา '.yt(), array_merge(['ห้อง', 'นักเรียน', 'คัดกรองแล้ว', 'ปกติ', 'เสี่ยง', 'มีปัญหา'], array_map(fn($l) => str_replace('ด้าน', '', $l), array_values(SCREEN_AREAS))), $rows, 'จำนวนด้านที่เสี่ยง/มีปัญหา');
}

/* ---------------------------------------------------------------- ส่งเสริม ป้องกัน แก้ไข */

function adm_sa_parent_meet(bool $post)
{
    return md_page('sa_parent_meet', 'sa_parent_meet', 'การประชุมผู้ปกครอง', ['room' => f_sel('ห้องเรียน', 'room_options'), 'date' => f_date('วันที่ประชุม'), 'total' => f_num('จำนวนผู้ปกครองทั้งหมด', false),
        'attend' => f_num('จำนวนผู้ปกครองที่มาประชุม'), 'topic' => f_area('เรื่องที่ประชุม', true), 'result' => f_area('ผลการประชุม/ข้อเสนอแนะ'), 'file' => f_file()],
        ['room' => 'ห้อง', 'date' => 'วันที่', 'attend' => 'ผู้มาประชุม', 'pct' => ['ร้อยละ', fn($d) => (int) ($d['total'] ?? 0) ? number_format((int) $d['attend'] * 100 / (int) $d['total'], 2) : '-'], 'topic' => 'เรื่อง'], $post, yt());
}

function adm_sa_homeroom(bool $post)
{
    return md_page('sa_homeroom', 'sa_homeroom', 'กิจกรรมโฮมรูม', ['room' => f_sel('ห้องเรียน', 'room_options'), 'date' => f_date(), 'topic' => f_text('หัวข้อกิจกรรม', true), 'detail' => f_area('รายละเอียด'),
        'teacher' => f_sel('ครูผู้ดำเนินกิจกรรม', 'personnel_options', false)], ['room' => 'ห้อง', 'date' => 'วันที่', 'topic' => 'หัวข้อ', 'teacher' => 'ครู'], $post, yt());
}

function adm_sa_counsel(bool $post)
{
    return md_page('sa_counsel', 'sa_counsel', 'การให้คำปรึกษา', ['student' => f_student(), 'date' => f_date(), 'issue' => f_sel('ด้านปัญหา', SCREEN_AREAS),
        'problem' => f_area('ปัญหา/เรื่องที่ขอรับคำปรึกษา', true), 'advice' => f_area('การให้คำปรึกษา'), 'result' => f_sel('ผล', ['ดีขึ้น' => 'ดีขึ้น', 'ติดตามต่อ' => 'ต้องติดตามต่อ', 'ส่งต่อ' => 'ส่งต่อ'], false)],
        ['student' => 'นักเรียน', 'date' => 'วันที่', 'issue' => 'ด้าน', 'result' => 'ผล'], $post, yt());
}

function adm_sa_plan(bool $post)
{
    return md_page('sa_plan', 'sa_plan', 'แผนส่งเสริมและช่วยเหลือ', ['student' => f_student(), 'type' => f_sel('ประเภท', ['ส่งเสริม' => 'ส่งเสริมพัฒนา', 'ป้องกัน' => 'ป้องกัน', 'แก้ไข' => 'ช่วยเหลือแก้ไข']),
        'goal' => f_area('เป้าหมาย', true), 'method' => f_area('วิธีการ/กิจกรรม'), 'from' => f_date('เริ่ม'), 'to' => [ 'สิ้นสุด', 'date', null, false],
        'status' => f_sel('สถานะ', ['วางแผน' => 'วางแผน', 'ดำเนินการ' => 'กำลังดำเนินการ', 'สำเร็จ' => 'สำเร็จ', 'ไม่สำเร็จ' => 'ไม่สำเร็จ/ส่งต่อ'], false)],
        ['student' => 'นักเรียน', 'type' => 'ประเภท', 'from' => 'เริ่ม', 'status' => 'สถานะ', 'goal' => 'เป้าหมาย'], $post, yt());
}

function count_by(string $kind, string $field, string $scope = ''): array
{
    $c = [];
    foreach (md_rows($kind, null, $scope) as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        $k = (string) ($d[$field] ?? '');
        $c[$k] = ($c[$k] ?? 0) + 1;
    }
    return $c;
}

function adm_sa_promote_sum(bool $post)
{
    $types = count_by('sa_plan', 'type', yt());
    $status = count_by('sa_plan', 'status', yt());
    $rows = '';
    $i = 0;
    foreach (['กิจกรรมโฮมรูม' => count(md_rows('sa_homeroom', null, yt())), 'การประชุมผู้ปกครอง' => count(md_rows('sa_parent_meet', null, yt())),
        'แผนส่งเสริมพัฒนา' => $types['ส่งเสริม'] ?? 0, 'แผนป้องกัน' => $types['ป้องกัน'] ?? 0, 'แผนช่วยเหลือแก้ไข' => $types['แก้ไข'] ?? 0,
        'แผนที่ดำเนินการสำเร็จ' => $status['สำเร็จ'] ?? 0, 'พฤติกรรมดีเด่น' => count(md_rows('sa_good', null, yt())), 'นักเรียนได้รับทุน' => count(md_rows('sa_fund_student', null, yt()))] as $l => $n) {
        $rows .= zrow([++$i, h($l), [$n, ' align="center"']], $i);
    }
    return list_table('สรุปการส่งเสริมและพัฒนาผู้เรียน ปีการศึกษา '.yt(), ['#', 'รายการ', ['จำนวน (ครั้ง/คน)', ' style="text-align:center"']], $rows);
}

function adm_sa_help_sum(bool $post)
{
    $res = count_by('sa_counsel', 'result', yt());
    $issue = count_by('sa_counsel', 'issue', yt());
    $rows = '';
    $i = 0;
    foreach (SCREEN_AREAS as $k => $l) {
        $rows .= zrow([++$i, h($l), [$issue[$k] ?? 0, ' align="center"']], $i);
    }
    $rows .= zrow(['', '<b>ผล: ดีขึ้น / ติดตามต่อ / ส่งต่อ</b>', [($res['ดีขึ้น'] ?? 0).' / '.($res['ติดตามต่อ'] ?? 0).' / '.($res['ส่งต่อ'] ?? 0), ' align="center"']], ++$i);
    $rows .= zrow(['', '<b>การส่งต่อภายใน / ภายนอก</b>', [count(md_rows('sa_refer_in', null, yt())).' / '.count(md_rows('sa_refer_out', null, yt())), ' align="center"']], ++$i);
    return list_table('สรุปการให้ความช่วยเหลือนักเรียน (การให้คำปรึกษา) ปีการศึกษา '.yt(), ['#', 'ด้าน', ['จำนวนครั้ง', ' style="text-align:center"']], $rows);
}

function adm_sa_research(bool $post)
{
    return md_page('sa_research', 'sa_research', 'การวิจัยเชิงปฏิบัติ', ['title' => f_text('ชื่อเรื่อง', true), 'researcher' => f_sel('ผู้วิจัย', 'personnel_options'), 'year' => f_text('ปีการศึกษา'),
        'abstract' => f_area('บทคัดย่อ'), 'file' => f_file('ไฟล์งานวิจัย')], ['title' => 'ชื่อเรื่อง', 'researcher' => 'ผู้วิจัย', 'year' => 'ปีการศึกษา', 'file' => 'ไฟล์'], $post);
}

function adm_sa_report(bool $post)
{
    return md_page('sa_report', 'sa_report', 'ผลการปฏิบัติงานระบบดูแลช่วยเหลือ', ['room' => f_sel('ห้องเรียน', 'room_options'), 'teacher' => f_sel('ครูที่ปรึกษา', 'personnel_options', false),
        'summary' => f_area('สรุปผลการปฏิบัติงาน', true), 'problem' => f_area('ปัญหา/อุปสรรค'), 'file' => f_file()], ['room' => 'ห้อง', 'teacher' => 'ครูที่ปรึกษา', 'summary' => 'สรุป', 'file' => 'ไฟล์'], $post, yt());
}

/** ทุกเรื่องของนักเรียนหนึ่งคน */
function adm_sa_person_sum(bool $post)
{
    $sid = (int) ($_GET['student'] ?? 0);
    $f = '<form method="get" style="width:983px;margin:0 auto 10px;text-align:left"><input type="hidden" name="module" value="admin"/><input type="hidden" name="option" value="sa_person_sum"/><span class="search">นักเรียน '
        .sel('student', ['' => '- เลือกนักเรียน -'] + student_options(), $sid, 'onchange="this.form.submit()" style="width:450px;height:25px"').'</span><div style="clear:both"></div></form>';
    if (!$sid) {
        return $f;
    }
    $names = [];
    foreach (affairs_menu() as $items) {
        foreach ($items as $op => [$label]) {
            $names[$op] = $label;
        }
    }
    $rows = '';
    $i = 0;
    md_ensure();
    foreach (q('SELECT * FROM {mis_data} WHERE `kind` LIKE \'sa\\_%\' AND `value` LIKE ? ORDER BY `kind`, `r1`', ['%"student":"'.$sid.'"%']) as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        if ((int) ($d['student'] ?? 0) !== $sid) {
            continue;
        }
        $detail = [];
        foreach ($d as $k => $v) {
            if (!in_array($k, ['student', 'date', 'file', 'photo'], true) && !str_ends_with($k, '_name') && $v !== '' && !is_array($v)) {
                $detail[] = $v;
            }
        }
        $rows .= zrow([++$i, h($names[$r['kind']] ?? $r['kind']), !empty($d['date']) ? th_date($d['date'], ' ') : '', h(mb_substr(implode(' | ', $detail), 0, 200)),
            [edit_link(a_url($r['kind'], '&func=Edit&id='.rawurlencode((string) $r['r1']))), ' align="center"']], $i);
    }
    return $f.list_table('สรุปการคัดกรองและช่วยเหลือ: '.h(student_options()[$sid] ?? ''), ['#', 'เรื่อง', 'วันที่', 'รายละเอียด', ''], $rows, '', '<a href="javascript:window.print()" id="pinkgreenbtns"><img src="../icon_tools/vec_print_white.png" height="16"/> พิมพ์</a>');
}

/* ---------------------------------------------------------------- ส่งต่อ */

function adm_sa_refer_in(bool $post)
{
    return md_page('sa_refer_in', 'sa_refer_in', 'การส่งต่อภายใน', ['student' => f_student(), 'date' => f_date('วันที่ส่งต่อ'), 'to' => f_sel('ส่งต่อถึง', ['ครูแนะแนว' => 'ครูแนะแนว', 'ฝ่ายปกครอง' => 'ฝ่ายปกครอง', 'ครูพยาบาล' => 'ห้องพยาบาล', 'ฝ่ายวิชาการ' => 'ฝ่ายวิชาการ', 'ผู้บริหาร' => 'ผู้บริหาร', 'อื่น ๆ' => 'อื่น ๆ']),
        'reason' => f_area('สาเหตุ/ปัญหา', true), 'done' => f_area('การช่วยเหลือที่ดำเนินการแล้ว')], ['student' => 'นักเรียน', 'date' => 'วันที่', 'to' => 'ส่งต่อถึง', 'reason' => 'สาเหตุ'], $post, yt());
}

function adm_sa_refer_out(bool $post)
{
    return md_page('sa_refer_out', 'sa_refer_out', 'การส่งต่อภายนอก', ['student' => f_student(), 'date' => f_date('วันที่ส่งต่อ'), 'agency' => f_text('หน่วยงานที่รับส่งต่อ', true),
        'reason' => f_area('สาเหตุ/ปัญหา', true), 'contact' => f_text('ผู้ประสานงาน/โทรศัพท์'), 'file' => f_file('หนังสือส่งตัว')], ['student' => 'นักเรียน', 'date' => 'วันที่', 'agency' => 'หน่วยงาน', 'reason' => 'สาเหตุ'], $post, yt());
}

function refer_options(): array
{
    $st = student_options();
    $o = [];
    foreach (['sa_refer_in' => 'ภายใน', 'sa_refer_out' => 'ภายนอก'] as $k => $l) {
        foreach (md_rows($k, null, yt()) as $r) {
            $d = json_decode((string) $r['value'], true) ?: [];
            $o[$k.':'.$r['r1']] = $l.' '.(!empty($d['date']) ? th_date($d['date'], ' ') : '').' '.($st[$d['student'] ?? 0] ?? '');
        }
    }
    return $o;
}

function adm_sa_refer_result(bool $post)
{
    return md_page('sa_refer_result', 'sa_refer_result', 'ผลการช่วยเหลือ', ['refer' => f_sel('รายการส่งต่อ', 'refer_options'), 'date' => f_date('วันที่แจ้งผล'),
        'result' => f_sel('ผลการช่วยเหลือ', ['ดีขึ้น' => 'ดีขึ้น/แก้ไขได้', 'ติดตาม' => 'ต้องติดตามต่อ', 'ไม่ดีขึ้น' => 'ไม่ดีขึ้น']), 'detail' => f_area('รายละเอียด')],
        ['refer' => 'รายการส่งต่อ', 'date' => 'วันที่', 'result' => 'ผล'], $post, yt());
}

function adm_sa_refer_sum(bool $post)
{
    $res = count_by('sa_refer_result', 'result', yt());
    $in = count_by('sa_refer_in', 'to', yt());
    $rows = '';
    $i = 0;
    foreach ($in as $k => $n) {
        $rows .= zrow([++$i, 'ส่งต่อภายใน: '.h($k), [$n, ' align="center"']], $i);
    }
    $rows .= zrow([++$i, 'ส่งต่อภายนอก', [count(md_rows('sa_refer_out', null, yt())), ' align="center"']], $i);
    foreach (['ดีขึ้น' => 'ผล: ดีขึ้น/แก้ไขได้', 'ติดตาม' => 'ผล: ต้องติดตามต่อ', 'ไม่ดีขึ้น' => 'ผล: ไม่ดีขึ้น'] as $k => $l) {
        $rows .= zrow([++$i, $l, [$res[$k] ?? 0, ' align="center"']], $i);
    }
    return list_table('ผลการปฏิบัติงานตามระบบดูแลช่วยเหลือนักเรียน ปีการศึกษา '.yt(), ['#', 'รายการ', ['จำนวน', ' style="text-align:center"']], $rows);
}

/* ---------------------------------------------------------------- พฤติกรรม */

function adm_sa_rule(bool $post)
{
    return md_page('sa_rule', 'sa_rule', 'เกณฑ์ตัดคะแนนความประพฤติ', ['name' => f_text('พฤติกรรม/ความผิด', true), 'points' => f_num('คะแนนที่ตัด'),
        'level' => f_sel('ระดับความผิด', ['เล็กน้อย' => 'เล็กน้อย', 'ปานกลาง' => 'ปานกลาง', 'ร้ายแรง' => 'ร้ายแรง'], false)], ['name' => 'พฤติกรรม', 'points' => 'ตัดคะแนน', 'level' => 'ระดับ'], $post);
}

function adm_sa_punish(bool $post)
{
    return md_page('sa_punish', 'sa_punish', 'วิธีการลงโทษ', ['name' => f_text('วิธีการลงโทษ', true), 'detail' => f_area('รายละเอียด')], ['name' => 'วิธีการลงโทษ', 'detail' => 'รายละเอียด'], $post);
}

function rule_points(array $d): string
{
    static $rules = null;
    if ($rules === null) {
        $rules = [];
        foreach (md_rows('sa_rule') as $r) {
            $x = json_decode((string) $r['value'], true) ?: [];
            $rules[$r['r1']] = (int) ($x['points'] ?? 0);
        }
    }
    return (string) (isset($d['points']) && $d['points'] !== '' ? (int) $d['points'] : ($rules[$d['rule'] ?? ''] ?? 0));
}

function adm_sa_bad(bool $post)
{
    $body = md_page('sa_bad', 'sa_bad', 'พฤติกรรมบกพร่อง', ['student' => f_student(), 'date' => f_date(), 'rule' => f_sel('พฤติกรรม/ความผิด', fn() => md_options('sa_rule', 'name')),
        'points' => f_num('คะแนนที่ตัด (เว้นว่างใช้ตามเกณฑ์)', false), 'punish' => f_sel('การลงโทษ', fn() => md_options('sa_punish', 'name'), false), 'detail' => f_area('รายละเอียด')],
        ['student' => 'นักเรียน', 'date' => 'วันที่', 'rule' => 'ความผิด', 'pt' => ['ตัดคะแนน', fn($d) => '<span style="color:#E74C3C">-'.rule_points($d).'</span>'], 'punish' => 'การลงโทษ'], $post, yt());
    if (is_string($body) && !md_rows('sa_rule')) {
        $body = msg_box('ยังไม่ได้กำหนดเกณฑ์ตัดคะแนน — <a href="'.a_url('sa_rule').'">กำหนดเกณฑ์ตัดคะแนนความประพฤติ</a>', false).$body;
    }
    return $body;
}

function adm_sa_good(bool $post)
{
    return md_page('sa_good', 'sa_good', 'พฤติกรรมดีเด่น', ['student' => f_student(), 'date' => f_date(), 'detail' => f_area('ความดี/ผลงาน', true), 'points' => f_num('คะแนนที่เพิ่ม', false)],
        ['student' => 'นักเรียน', 'date' => 'วันที่', 'detail' => 'ความดี/ผลงาน', 'points' => 'เพิ่มคะแนน'], $post, yt());
}

function adm_sa_flag(bool $post)
{
    return md_page('sa_flag', 'sa_flag', 'การเข้าแถวหน้าเสาธง', ['student' => f_student(), 'date' => f_date(), 'status' => f_sel('สถานะ', ['ไม่เข้าแถว' => 'ไม่เข้าแถว', 'มาสาย' => 'มาสาย', 'ลา' => 'ลา']), 'note' => f_text('หมายเหตุ')],
        ['student' => 'นักเรียน', 'date' => 'วันที่', 'status' => 'สถานะ', 'note' => 'หมายเหตุ'], $post, yt());
}

function adm_sa_leave(bool $post)
{
    return md_page('sa_leave', 'sa_leave', 'การขออนุญาตออกนอกโรงเรียน', ['student' => f_student(), 'date' => f_date(), 'out' => f_text('เวลาออก', true), 'back' => f_text('เวลากลับ'),
        'reason' => f_area('เหตุผล', true), 'approver' => f_sel('ผู้อนุญาต', 'personnel_options', false), 'parent' => f_text('ผู้ปกครองที่รับทราบ/มารับ')],
        ['student' => 'นักเรียน', 'date' => 'วันที่', 'out' => 'ออก', 'back' => 'กลับ', 'reason' => 'เหตุผล', 'approver' => 'ผู้อนุญาต'], $post, yt());
}

/** คะแนนความประพฤติของนักเรียน: [ตัด, เพิ่ม, คงเหลือ] (ฐาน 100) */
function conduct_scores(): array
{
    $out = [];
    foreach (md_rows('sa_bad', null, yt()) as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        $out[(int) ($d['student'] ?? 0)]['bad'][] = $d;
    }
    foreach (md_rows('sa_good', null, yt()) as $r) {
        $d = json_decode((string) $r['value'], true) ?: [];
        $out[(int) ($d['student'] ?? 0)]['good'][] = $d;
    }
    $res = [];
    foreach ($out as $sid => $x) {
        $minus = array_sum(array_map(fn($d) => (int) rule_points($d), $x['bad'] ?? []));
        $plus = array_sum(array_map(fn($d) => (int) ($d['points'] ?? 0), $x['good'] ?? []));
        $res[$sid] = [$minus, $plus, 100 - $minus + $plus, $x];
    }
    return $res;
}

function adm_sa_conduct(bool $post)
{
    $room = (int) ($_GET['room'] ?? 0);
    $sc = conduct_scores();
    $sql = 'SELECT S.*, U.`name` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE U.`active`=1'.($room ? ' AND S.`room`='.$room : '').' ORDER BY S.`class`, S.`room`, S.`number`';
    $rows = '';
    $i = 0;
    foreach (q($sql) as $s) {
        [$m, $p, $t] = $sc[(int) $s['id']] ?? [0, 0, 100];
        if (!$room && !$m && !$p) {
            continue;
        }
        $rows .= zrow([++$i, h($s['student_id']), name_nbsp($s['name']), h(class_room_short($s['class'], $s['room'])), [$m ? '-'.$m : '0', ' align="center" style="color:#E74C3C"'], [$p ? '+'.$p : '0', ' align="center" style="color:#27AE60"'],
            [$t, ' align="center" style="font-weight:bold'.($t < 60 ? ';color:#E74C3C' : '').'"'], ['<a href="'.a_url('sa_letter', '&print=1&student='.(int) $s['id']).'">จดหมาย</a>', ' align="center"']], $i);
    }
    $f = '<form method="get" style="width:983px;margin:0 auto 10px;text-align:left"><input type="hidden" name="module" value="admin"/><input type="hidden" name="option" value="sa_conduct"/><span class="search">ห้อง '
        .sel('room', ['0' => 'เฉพาะนักเรียนที่มีบันทึก'] + room_options(), $room, 'onchange="this.form.submit()"').'</span><div style="clear:both"></div></form>';
    return $f.list_table('รายงานความประพฤตินักเรียน ปีการศึกษา '.yt().' (คะแนนตั้งต้น 100)', ['#', 'รหัสนร.', 'ชื่อ-นามสกุล', 'ชั้น', ['ตัดคะแนน', ' style="text-align:center"'], ['เพิ่มคะแนน', ' style="text-align:center"'], ['คงเหลือ', ' style="text-align:center"'], ''], $rows, '',
        '<a href="javascript:window.print()" id="pinkgreenbtns"><img src="../icon_tools/vec_print_white.png" height="16"/> พิมพ์</a>');
}

function adm_sa_letter(bool $post)
{
    $sid = (int) ($_GET['student'] ?? 0);
    if ($sid && isset($_GET['print'])) {
        $s = q1('SELECT S.*, U.`name` FROM {student} S JOIN {user} U ON U.`id`=S.`id` WHERE S.`id`=?', [$sid]);
        [$m, $p, $t, $x] = conduct_scores()[$sid] ?? [0, 0, 100, []];
        $rules = md_options('sa_rule', 'name');
        $list = '';
        foreach ($x['bad'] ?? [] as $d) {
            $list .= '<tr><td>'.h(!empty($d['date']) ? th_date($d['date'], ' ') : '').'</td><td>'.h($rules[$d['rule'] ?? ''] ?? '').' '.h($d['detail'] ?? '').'</td><td class="c">-'.rule_points($d).'</td></tr>';
        }
        $school = cfg('school_name', 'โรงเรียนดรุณราษฎร์วิทยา');
        print_page('จดหมายถึงผู้ปกครอง', '<div style="text-align:right">'.h($school).'<br>'.th_date('', ' ').'</div><p>เรื่อง แจ้งพฤติกรรมของนักเรียน</p><p>เรียน '.h($s['parent'] ?: 'ผู้ปกครองของ'.$s['name']).'</p>'
            .'<p style="text-indent:50px">ด้วย'.h($s['name']).' รหัสนักเรียน '.h($s['student_id']).' ชั้น '.h(class_room_short($s['class'], $s['room'])).' มีคะแนนความประพฤติคงเหลือ '.$t.' คะแนน (ถูกตัด '.$m.' คะแนน ได้รับเพิ่ม '.$p.' คะแนน) โดยมีรายการพฤติกรรมดังนี้</p>'
            .'<table><tr><th>วันที่</th><th>พฤติกรรม</th><th>คะแนน</th></tr>'.($list ?: '<tr><td colspan="3" class="c">-</td></tr>').'</table>'
            .'<p style="text-indent:50px">โรงเรียนจึงขอแจ้งให้ท่านทราบ และขอความร่วมมือในการดูแลและอบรมนักเรียนร่วมกับโรงเรียน หากมีข้อสงสัยกรุณาติดต่อครูที่ปรึกษาหรือฝ่ายกิจการนักเรียน</p>'
            .'<p style="margin-top:50px;text-align:right">ขอแสดงความนับถือ<br><br><br>(........................................)<br>หัวหน้าฝ่ายกิจการนักเรียน</p>');
        return null;
    }
    $f = '<form method="get" style="width:983px;margin:0 auto 10px;text-align:left"><input type="hidden" name="module" value="admin"/><input type="hidden" name="option" value="sa_letter"/><input type="hidden" name="print" value="1"/><span class="search">นักเรียน '
        .sel('student', ['' => '- เลือกนักเรียน -'] + student_options(), $sid, 'style="width:450px;height:25px"').' <input type="submit" value="ออกจดหมาย" class="general radius" style="height:25px"/></span><div style="clear:both"></div></form>';
    return $f.'<p style="width:983px;margin:0 auto;text-align:left" class="gray_12">จดหมายแจ้งคะแนนความประพฤติและรายการพฤติกรรมบกพร่องของนักเรียนถึงผู้ปกครอง (ใช้ชื่อผู้ปกครองจากทะเบียนนักเรียน)</p>';
}

/* ---------------------------------------------------------------- ทุนการศึกษา */

function adm_sa_fund(bool $post)
{
    return md_page('sa_fund', 'sa_fund', 'ทุนการศึกษา', ['name' => f_text('ชื่อทุน', true), 'sponsor' => f_text('ผู้ให้ทุน'), 'amount' => f_num('จำนวนเงินต่อทุน (บาท)', false), 'count' => f_num('จำนวนทุน', false), 'condition' => f_area('คุณสมบัติผู้รับทุน')],
        ['name' => 'ชื่อทุน', 'sponsor' => 'ผู้ให้ทุน', 'amount' => 'บาท/ทุน', 'count' => 'จำนวนทุน', 'given' => ['มอบแล้ว (ปีนี้)', 'fund_given']], $post);
}

function fund_given(array $d): string
{
    static $c = null;
    if ($c === null) {
        $names = md_options('sa_fund', 'name');
        $c = [];
        foreach (md_rows('sa_fund_student', null, yt()) as $r) {
            $n = $names[json_decode((string) $r['value'], true)['fund'] ?? ''] ?? '';
            $c[$n] = ($c[$n] ?? 0) + 1;
        }
    }
    return (string) ($c[$d['name'] ?? ''] ?? 0);
}

function adm_sa_fund_student(bool $post)
{
    return md_page('sa_fund_student', 'sa_fund_student', 'นักเรียนได้รับทุน', ['student' => f_student(), 'fund' => f_sel('ทุนการศึกษา', fn() => md_options('sa_fund', 'name')), 'date' => f_date('วันที่ได้รับ'), 'amount' => f_num('จำนวนเงิน (บาท)', false), 'note' => f_text('หมายเหตุ')],
        ['student' => 'นักเรียน', 'fund' => 'ทุน', 'date' => 'วันที่', 'amount' => 'บาท'], $post, yt());
}
