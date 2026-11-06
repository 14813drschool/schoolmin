<?php
/**
 * api/index.php
 *
 * จุดเข้าเดียวของเว็บบน Vercel (vercel-php runtime)
 * เตรียมระบบด้วย vercel/bootstrap.php แล้วรันไฟล์ PHP ของ SMS ที่ตรงกับ URL
 * (ไฟล์นี้อยู่ที่ api/ เพราะ Vercel กำหนดให้ฟังก์ชันอยู่ในโฟลเดอร์นี้ ส่วน API ของ SMS ยังเป็น api.php เหมือนเดิม)
 */
require __DIR__.'/../vercel/bootstrap.php';

VercelSms::main();

// รันในระดับบนสุดเพื่อให้ตัวแปรของสคริปต์เป็น global เหมือนรันบน Apache
if (isset($GLOBALS['__vercel_script'])) {
    require $GLOBALS['__vercel_script'];
}
