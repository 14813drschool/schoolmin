<?php
/**
 * mis/app/boot.php
 * ระบบสารสนเทศหน้าตาแบบ DRW-MIS (ระบบเดิมของโรงเรียน) ที่ใช้ข้อมูลและบัญชีผู้ใช้ของ SMS
 * โหลด Kotchasan/SMS, ตรวจผู้ใช้จากคุกกี้ auth_token ของ SMS, ภาคเรียนที่เลือก, และแม่แบบหน้า
 */
define('MIS', 1);
define('MIS_START', microtime(true));
define('MIS_APP', __DIR__.'/');

include dirname(__DIR__, 2).'/load.php';
Kotchasan::createWebApplication('Gcms\Config');

require MIS_APP.'helpers.php';
require MIS_APP.'data.php';
require MIS_APP.'data2.php';
require MIS_APP.'admin.php';
require MIS_APP.'affairs.php';
require MIS_APP.'modules.php';
require MIS_APP.'finance.php';
require MIS_APP.'import.php';
require MIS_APP.'registry.php';
require MIS_APP.'reports.php';
require MIS_APP.'design.php';
require MIS_APP.'pp6.php';
require MIS_APP.'stusys.php';
require MIS_APP.'gradeedit.php';
require MIS_APP.'attend.php';
require MIS_APP.'pages.php';
