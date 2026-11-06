<?php
/**
 * vercel/gd/polyfill.php
 *
 * ฟังก์ชัน image*() ของส่วนขยาย gd สำหรับ Vercel ซึ่งไม่มี gd ให้
 * ทำงานผ่าน FFI กับ libsmsimg.so (ซอร์สอยู่ที่ smsimg.c) ครอบคลุมเฉพาะที่ SMS/Kotchasan ใช้:
 * อ่าน JPEG/PNG/GIF/WebP, ย่อ/ครอป/พลิก/หมุน, บันทึกเป็น JPEG/PNG/WebP
 * ส่วนที่ไม่รองรับ: วาดตัวอักษร (imagettftext) จะข้ามไป ภาพยังบันทึกได้ตามปกติ
 *
 * โหลดโดย vercel/bootstrap.php เฉพาะเมื่อไม่มีส่วนขยาย gd
 */
if (extension_loaded('gd') || function_exists('imagecreatetruecolor') || !class_exists('FFI')) {
    return;
}

foreach (['IMG_GIF' => 1, 'IMG_JPG' => 2, 'IMG_JPEG' => 2, 'IMG_PNG' => 4, 'IMG_WEBP' => 32, 'IMG_FLIP_HORIZONTAL' => 1, 'IMG_FLIP_VERTICAL' => 2, 'IMG_FLIP_BOTH' => 3] as $name => $value) {
    if (!defined($name)) {
        define($name, $value);
    }
}

final class VercelGd
{
    /** @var \FFI|null */
    private static $ffi = null;

    public static function ffi(): \FFI
    {
        if (self::$ffi === null) {
            self::$ffi = \FFI::cdef('
                typedef struct { int w; int h; unsigned char *px; } si_img;
                si_img *si_new(int w, int h, unsigned int r, unsigned int g, unsigned int b, unsigned int a);
                void si_free(si_img *im);
                void si_free_buf(void *p);
                si_img *si_load(const unsigned char *data, int len);
                void si_fill(si_img *im, unsigned int r, unsigned int g, unsigned int b, unsigned int a);
                int si_copy_resampled(si_img *dst, si_img *src, int dx, int dy, int sx, int sy, int dw, int dh, int sw, int sh, int blend);
                si_img *si_rotate(si_img *im, int turns);
                unsigned char *si_encode(si_img *im, int fmt, int quality, int keep_alpha, int *outlen);
            ', __DIR__.'/libsmsimg.so');
        }
        return self::$ffi;
    }

    /** สีแบบ gd (alpha 0-127 โดย 127 = โปร่งใส) เป็น [r,g,b,a 0-255] */
    public static function rgba(int $color): array
    {
        $a7 = ($color >> 24) & 0x7F;
        return [($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF, (int) round(255 - $a7 * 255 / 127)];
    }

    public static function load(string $data)
    {
        if ($data === '') {
            return false;
        }
        $ffi = self::ffi();
        $len = strlen($data);
        $buf = $ffi->new('unsigned char['.$len.']', false);
        \FFI::memcpy($buf, $data, $len);
        $p = $ffi->si_load($buf, $len);
        \FFI::free($buf);
        return \FFI::isNull($p) ? false : new GdImage($p);
    }

    public static function output(GdImage $im, int $fmt, int $quality, bool $alpha, $to): bool
    {
        $ffi = self::ffi();
        $len = $ffi->new('int');
        $buf = $ffi->si_encode($im->p, $fmt, $quality, $alpha ? 1 : 0, \FFI::addr($len));
        if (\FFI::isNull($buf) || $len->cdata <= 0) {
            return false;
        }
        $bytes = \FFI::string($buf, $len->cdata);
        $ffi->si_free_buf($buf);
        if ($to === null || $to === '') {
            echo $bytes;
            return true;
        }
        if (is_resource($to)) {
            return fwrite($to, $bytes) === strlen($bytes);
        }
        return file_put_contents((string) $to, $bytes) !== false;
    }
}

final class GdImage
{
    /** @var \FFI\CData si_img* */
    public $p;
    public $alphaBlending = true;
    public $saveAlpha = false;

    public function __construct($p)
    {
        $this->p = $p;
    }

    public function __destruct()
    {
        if ($this->p !== null) {
            VercelGd::ffi()->si_free($this->p);
            $this->p = null;
        }
    }
}

function gd_info()
{
    return [
        'GD Version' => 'smsimg (FFI)', 'FreeType Support' => false, 'GIF Read Support' => true, 'GIF Create Support' => false,
        'JPEG Support' => true, 'PNG Support' => true, 'WBMP Support' => false, 'XPM Support' => false, 'XBM Support' => false,
        'WebP Support' => true, 'BMP Support' => true, 'AVIF Support' => false, 'TGA Read Support' => false, 'JIS-mapped Japanese Font Support' => false
    ];
}

function imagetypes()
{
    return 1 | 2 | 4 | 32; // IMG_GIF | IMG_JPG | IMG_PNG | IMG_WEBP
}

function imagecreatetruecolor($width, $height)
{
    $p = VercelGd::ffi()->si_new((int) $width, (int) $height, 0, 0, 0, 255);
    return \FFI::isNull($p) ? false : new GdImage($p);
}

function imagecreatefromstring($data)
{
    return VercelGd::load((string) $data);
}

function imagecreatefromjpeg($filename)
{
    $data = @file_get_contents($filename);
    return $data === false ? false : VercelGd::load($data);
}

function imagecreatefrompng($filename)
{
    return imagecreatefromjpeg($filename);
}

function imagecreatefromgif($filename)
{
    return imagecreatefromjpeg($filename);
}

function imagecreatefromwebp($filename)
{
    return imagecreatefromjpeg($filename);
}

function imagecreatefrombmp($filename)
{
    return imagecreatefromjpeg($filename);
}

function imagesx($image)
{
    return $image->p->w;
}

function imagesy($image)
{
    return $image->p->h;
}

function imagecolorallocatealpha($image, $red, $green, $blue, $alpha)
{
    return (((int) $alpha & 0x7F) << 24) | (((int) $red & 0xFF) << 16) | (((int) $green & 0xFF) << 8) | ((int) $blue & 0xFF);
}

function imagecolorallocate($image, $red, $green, $blue)
{
    return imagecolorallocatealpha($image, $red, $green, $blue, 0);
}

function imagecolortransparent($image, $color = null)
{
    return $color === null ? -1 : (int) $color;
}

function imagefill($image, $x, $y, $color)
{
    // ใช้กับภาพเปล่าที่เพิ่งสร้าง (สีเดียวทั้งภาพ) จึงเทสีทั้งภาพ
    [$r, $g, $b, $a] = VercelGd::rgba((int) $color);
    VercelGd::ffi()->si_fill($image->p, $r, $g, $b, $a);
    return true;
}

function imagefilledrectangle($image, $x1, $y1, $x2, $y2, $color)
{
    if ($x1 <= 0 && $y1 <= 0 && $x2 >= imagesx($image) - 1 && $y2 >= imagesy($image) - 1) {
        return imagefill($image, 0, 0, $color);
    }
    return true;
}

function imagealphablending($image, $enable)
{
    $image->alphaBlending = (bool) $enable;
    return true;
}

function imagesavealpha($image, $enable)
{
    $image->saveAlpha = (bool) $enable;
    return true;
}

function imagecopyresampled($dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_width, $dst_height, $src_width, $src_height)
{
    return VercelGd::ffi()->si_copy_resampled(
        $dst_image->p, $src_image->p, (int) $dst_x, (int) $dst_y, (int) $src_x, (int) $src_y,
        (int) $dst_width, (int) $dst_height, (int) $src_width, (int) $src_height, $dst_image->alphaBlending ? 1 : 0
    ) === 1;
}

function imagecopyresized($dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_width, $dst_height, $src_width, $src_height)
{
    return imagecopyresampled($dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_width, $dst_height, $src_width, $src_height);
}

function imagecopy($dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $src_width, $src_height)
{
    return imagecopyresampled($dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $src_width, $src_height, $src_width, $src_height);
}

function imagerotate($image, $angle, $background_color, $ignore_transparent = false)
{
    // รองรับมุมที่เป็นผลคูณของ 90 (ใช้หมุนตาม EXIF ของรูปจากกล้อง)
    $turns = (int) round(fmod((float) $angle, 360.0) / 90);
    $p = VercelGd::ffi()->si_rotate($image->p, $turns);
    if (\FFI::isNull($p)) {
        return false;
    }
    $out = new GdImage($p);
    $out->saveAlpha = $image->saveAlpha;
    return $out;
}

function imageflip($image, $mode)
{
    $w = imagesx($image);
    $h = imagesy($image);
    $copy = imagecreatetruecolor($w, $h);
    imagealphablending($copy, false);
    imagecopyresampled($copy, $image, 0, 0, 0, 0, $w, $h, $w, $h);
    $fx = $mode === 1 || $mode === 3; // IMG_FLIP_HORIZONTAL | IMG_FLIP_BOTH
    $fy = $mode === 2 || $mode === 3;
    imagealphablending($image, false);
    imagecopyresampled($image, $copy, 0, 0, $fx ? $w - 1 : 0, $fy ? $h - 1 : 0, $w, $h, $fx ? -$w : $w, $fy ? -$h : $h);
    return true;
}

function imagejpeg($image, $file = null, $quality = -1)
{
    return VercelGd::output($image, 1, (int) $quality < 0 ? 75 : (int) $quality, false, $file);
}

function imagepng($image, $file = null, $quality = -1, $filters = -1)
{
    return VercelGd::output($image, 2, 100, $image->saveAlpha, $file);
}

function imagewebp($image, $file = null, $quality = -1)
{
    return VercelGd::output($image, 3, (int) $quality < 0 ? 80 : (int) $quality, true, $file);
}

function imagegif($image, $file = null)
{
    // เขียน GIF ไม่ได้ บันทึกเป็น PNG แทน (เบราว์เซอร์แสดงได้ตามเนื้อไฟล์)
    return VercelGd::output($image, 2, 100, true, $file);
}

function imagedestroy($image)
{
    return true; // คืนหน่วยความจำเมื่ออ็อบเจ็กต์ถูกทำลาย
}

function imageistruecolor($image)
{
    return true;
}

function imagettfbbox($size, $angle, $font_filename, $string, $options = [])
{
    // ไม่มีตัววาดตัวอักษร: ประมาณขนาดกล่องข้อความไว้ให้คำนวณตำแหน่งได้
    $w = (int) round(mb_strlen((string) $string) * $size * 0.6);
    $h = (int) round($size * 1.2);
    return [0, 0, $w, 0, $w, -$h, 0, -$h];
}

function imagettftext($image, $size, $angle, $x, $y, $color, $font_filename, $text, $options = [])
{
    // ข้ามการวาดลายน้ำตัวอักษร (ไม่รองรับบน Vercel)
    return imagettfbbox($size, $angle, $font_filename, $text);
}
