/*
 * smsimg.c — ไลบรารีจัดการรูปภาพขนาดเล็กสำหรับใช้แทนส่วนขยาย gd ของ PHP บน Vercel
 *
 * Vercel (vercel-php) ไม่มีส่วนขยาย gd แต่มี FFI ไฟล์นี้คอมไพล์เป็น libsmsimg.so
 * แล้ว vercel/gd/polyfill.php เรียกใช้ผ่าน FFI เพื่อทำฟังก์ชัน image*() ที่ SMS ใช้
 *
 * อ่านรูป: JPEG PNG GIF BMP (stb_image), WebP (libwebp)
 * เขียนรูป: JPEG PNG (stb_image_write), WebP (libwebp)
 * ภาพเก็บเป็น RGBA 8 บิต (alpha แบบปกติ ไม่ premultiplied)
 *
 * คอมไพล์: ดู vercel/gd/build.sh
 */
#include <stdlib.h>
#include <string.h>
#include <math.h>

#define STB_IMAGE_IMPLEMENTATION
#define STBI_ONLY_JPEG
#define STBI_ONLY_PNG
#define STBI_ONLY_GIF
#define STBI_ONLY_BMP
#define STBI_NO_STDIO
#include "stb_image.h"

#define STB_IMAGE_WRITE_IMPLEMENTATION
#define STBI_WRITE_NO_STDIO
#include "stb_image_write.h"

#include "webp/decode.h"
#include "webp/encode.h"

#define EXPORT __attribute__((visibility("default")))

typedef struct {
    int w;
    int h;
    unsigned char *px; /* RGBA */
} si_img;

EXPORT si_img *si_new(int w, int h, unsigned int r, unsigned int g, unsigned int b, unsigned int a)
{
    if (w <= 0 || h <= 0 || w > 20000 || h > 20000) {
        return NULL;
    }
    si_img *im = (si_img *) malloc(sizeof(si_img));
    if (!im) {
        return NULL;
    }
    im->w = w;
    im->h = h;
    im->px = (unsigned char *) malloc((size_t) w * h * 4);
    if (!im->px) {
        free(im);
        return NULL;
    }
    for (size_t i = 0, n = (size_t) w * h; i < n; i++) {
        im->px[i * 4] = r;
        im->px[i * 4 + 1] = g;
        im->px[i * 4 + 2] = b;
        im->px[i * 4 + 3] = a;
    }
    return im;
}

EXPORT void si_free(si_img *im)
{
    if (im) {
        free(im->px);
        free(im);
    }
}

EXPORT void si_free_buf(void *p)
{
    free(p);
}

/* อ่านรูปจากข้อมูลในหน่วยความจำ คืน NULL ถ้าอ่านไม่ได้ */
EXPORT si_img *si_load(const unsigned char *data, int len)
{
    int w = 0, h = 0, n = 0;
    if (len <= 0) {
        return NULL;
    }
    if (WebPGetInfo(data, (size_t) len, &w, &h)) {
        si_img *im = (si_img *) malloc(sizeof(si_img));
        if (!im) {
            return NULL;
        }
        im->px = WebPDecodeRGBA(data, (size_t) len, &im->w, &im->h);
        if (!im->px) {
            free(im);
            return NULL;
        }
        /* WebP ใช้ตัวจัดสรรหน่วยความจำของตัวเอง คัดลอกมาไว้ใน malloc ปกติเพื่อให้ si_free ใช้ได้ */
        size_t sz = (size_t) im->w * im->h * 4;
        unsigned char *copy = (unsigned char *) malloc(sz);
        if (!copy) {
            WebPFree(im->px);
            free(im);
            return NULL;
        }
        memcpy(copy, im->px, sz);
        WebPFree(im->px);
        im->px = copy;
        return im;
    }
    unsigned char *px = stbi_load_from_memory(data, len, &w, &h, &n, 4);
    if (!px) {
        return NULL;
    }
    si_img *im = (si_img *) malloc(sizeof(si_img));
    if (!im) {
        stbi_image_free(px);
        return NULL;
    }
    im->w = w;
    im->h = h;
    im->px = px; /* stb ใช้ malloc */
    return im;
}

EXPORT void si_fill(si_img *im, unsigned int r, unsigned int g, unsigned int b, unsigned int a)
{
    for (size_t i = 0, n = (size_t) im->w * im->h; i < n; i++) {
        im->px[i * 4] = r;
        im->px[i * 4 + 1] = g;
        im->px[i * 4 + 2] = b;
        im->px[i * 4 + 3] = a;
    }
}

/* น้ำหนักของพิกเซลต้นทางสำหรับพิกเซลปลายทางแต่ละตัว (ย่อ = เฉลี่ยตามพื้นที่, ขยาย = bilinear) */
typedef struct {
    int start;
    int count;
    float *w;
} si_span;

static si_span *make_spans(int dn, int sn, int s0, int smax)
{
    si_span *sp = (si_span *) calloc((size_t) dn, sizeof(si_span));
    if (!sp) {
        return NULL;
    }
    double scale = (double) sn / dn;
    for (int i = 0; i < dn; i++) {
        if (scale > 1.0) {
            double a = s0 + i * scale, b = a + scale;
            int ia = (int) floor(a), ib = (int) ceil(b);
            if (ia < 0) ia = 0;
            if (ib > smax) ib = smax;
            if (ib <= ia) ib = ia + 1;
            sp[i].start = ia;
            sp[i].count = ib - ia;
            sp[i].w = (float *) malloc(sizeof(float) * sp[i].count);
            double total = 0;
            for (int k = 0; k < sp[i].count; k++) {
                double lo = ia + k, hi = lo + 1;
                double cov = (hi < b ? hi : b) - (lo > a ? lo : a);
                if (cov < 0) cov = 0;
                sp[i].w[k] = (float) cov;
                total += cov;
            }
            for (int k = 0; k < sp[i].count; k++) {
                sp[i].w[k] = total > 0 ? (float) (sp[i].w[k] / total) : 1.0f / sp[i].count;
            }
        } else {
            double c = s0 + (i + 0.5) * scale - 0.5;
            int ia = (int) floor(c);
            double f = c - ia;
            int ib = ia + 1;
            if (ia < 0) { ia = 0; f = 0; }
            if (ib >= smax) ib = smax - 1;
            if (ia >= smax) ia = smax - 1;
            sp[i].start = ia;
            sp[i].count = (ib > ia) ? 2 : 1;
            sp[i].w = (float *) malloc(sizeof(float) * 2);
            if (sp[i].count == 2) {
                sp[i].w[0] = (float) (1 - f);
                sp[i].w[1] = (float) f;
            } else {
                sp[i].w[0] = 1.0f;
            }
        }
    }
    return sp;
}

static void free_spans(si_span *sp, int n)
{
    if (!sp) return;
    for (int i = 0; i < n; i++) free(sp[i].w);
    free(sp);
}

/*
 * เทียบเท่า imagecopyresampled(): คัดลอกส่วน (sx,sy,sw,sh) ของ src ไปวางที่ (dx,dy,dw,dh) ของ dst
 * sw/sh ติดลบ = พลิกภาพ (แบบที่ Kotchasan\Image::flip ใช้) · blend=1 วางทับแบบผสม alpha
 */
EXPORT int si_copy_resampled(si_img *dst, si_img *src, int dx, int dy, int sx, int sy, int dw, int dh, int sw, int sh, int blend)
{
    int flipx = 0, flipy = 0;
    if (sw < 0) { flipx = 1; sw = -sw; sx = sx - sw + 1; }
    if (sh < 0) { flipy = 1; sh = -sh; sy = sy - sh + 1; }
    if (dw <= 0 || dh <= 0 || sw <= 0 || sh <= 0) return 0;
    if (sx < 0) sx = 0;
    if (sy < 0) sy = 0;
    if (sx + sw > src->w) sw = src->w - sx;
    if (sy + sh > src->h) sh = src->h - sy;
    if (sw <= 0 || sh <= 0) return 0;
    si_span *xs = make_spans(dw, sw, sx, sx + sw);
    si_span *ys = make_spans(dh, sh, sy, sy + sh);
    if (!xs || !ys) { free_spans(xs, dw); free_spans(ys, dh); return 0; }
    for (int j = 0; j < dh; j++) {
        int ty = dy + j;
        if (ty < 0 || ty >= dst->h) continue;
        si_span *yy = &ys[flipy ? dh - 1 - j : j];
        for (int i = 0; i < dw; i++) {
            int tx = dx + i;
            if (tx < 0 || tx >= dst->w) continue;
            si_span *xx = &xs[flipx ? dw - 1 - i : i];
            /* เฉลี่ยแบบ premultiplied alpha เพื่อไม่ให้ขอบโปร่งใสเป็นสีเพี้ยน */
            double r = 0, g = 0, b = 0, a = 0;
            for (int ky = 0; ky < yy->count; ky++) {
                const unsigned char *row = src->px + ((size_t) (yy->start + ky) * src->w) * 4;
                for (int kx = 0; kx < xx->count; kx++) {
                    const unsigned char *p = row + (size_t) (xx->start + kx) * 4;
                    double wt = (double) yy->w[ky] * xx->w[kx];
                    double pa = p[3] * wt;
                    r += p[0] * pa;
                    g += p[1] * pa;
                    b += p[2] * pa;
                    a += pa;
                }
            }
            unsigned char *q = dst->px + ((size_t) ty * dst->w + tx) * 4;
            double nr = a > 0 ? r / a : 0, ng = a > 0 ? g / a : 0, nb = a > 0 ? b / a : 0;
            double na = a;
            if (blend && na < 255.0) {
                double da = q[3], oa = na + da * (1 - na / 255.0);
                if (oa > 0) {
                    nr = (nr * na + q[0] * da * (1 - na / 255.0)) / oa;
                    ng = (ng * na + q[1] * da * (1 - na / 255.0)) / oa;
                    nb = (nb * na + q[2] * da * (1 - na / 255.0)) / oa;
                }
                na = oa;
            }
            q[0] = (unsigned char) (nr + 0.5 > 255 ? 255 : nr + 0.5);
            q[1] = (unsigned char) (ng + 0.5 > 255 ? 255 : ng + 0.5);
            q[2] = (unsigned char) (nb + 0.5 > 255 ? 255 : nb + 0.5);
            q[3] = (unsigned char) (na + 0.5 > 255 ? 255 : na + 0.5);
        }
    }
    free_spans(xs, dw);
    free_spans(ys, dh);
    return 1;
}

/* หมุนภาพทวนเข็มนาฬิกาครั้งละ 90 องศา (turns = 0..3) เหมือนมุมของ imagerotate() */
EXPORT si_img *si_rotate(si_img *im, int turns)
{
    turns = ((turns % 4) + 4) % 4;
    int nw = (turns % 2) ? im->h : im->w;
    int nh = (turns % 2) ? im->w : im->h;
    si_img *out = si_new(nw, nh, 0, 0, 0, 255);
    if (!out) return NULL;
    for (int y = 0; y < im->h; y++) {
        for (int x = 0; x < im->w; x++) {
            int nx, ny;
            switch (turns) {
            case 1: nx = y; ny = im->w - 1 - x; break;              /* 90 ทวนเข็ม */
            case 2: nx = im->w - 1 - x; ny = im->h - 1 - y; break;  /* 180 */
            case 3: nx = im->h - 1 - y; ny = x; break;              /* 270 ทวนเข็ม = 90 ตามเข็ม */
            default: nx = x; ny = y;
            }
            memcpy(out->px + ((size_t) ny * nw + nx) * 4, im->px + ((size_t) y * im->w + x) * 4, 4);
        }
    }
    return out;
}

typedef struct {
    unsigned char *buf;
    size_t len;
    size_t cap;
} si_mem;

static void mem_write(void *ctx, void *data, int size)
{
    si_mem *m = (si_mem *) ctx;
    if (m->len + size > m->cap) {
        size_t nc = (m->cap ? m->cap * 2 : 65536);
        while (nc < m->len + size) nc *= 2;
        unsigned char *nb = (unsigned char *) realloc(m->buf, nc);
        if (!nb) return;
        m->buf = nb;
        m->cap = nc;
    }
    memcpy(m->buf + m->len, data, size);
    m->len += size;
}

/*
 * เข้ารหัสภาพ: fmt 1 = JPEG, 2 = PNG, 3 = WebP · quality 0-100
 * keep_alpha=0 เขียน PNG แบบไม่มี alpha (เหมือน gd เมื่อไม่ได้ imagesavealpha)
 * คืนบัฟเฟอร์ (ต้องคืนด้วย si_free_buf) และความยาวใน *outlen
 */
EXPORT unsigned char *si_encode(si_img *im, int fmt, int quality, int keep_alpha, int *outlen)
{
    *outlen = 0;
    if (quality < 0 || quality > 100) quality = 75;
    if (fmt == 3) {
        uint8_t *out = NULL;
        size_t n;
        if (keep_alpha) {
            n = WebPEncodeRGBA(im->px, im->w, im->h, im->w * 4, (float) quality, &out);
        } else {
            unsigned char *rgb = (unsigned char *) malloc((size_t) im->w * im->h * 3);
            if (!rgb) return NULL;
            for (size_t i = 0, c = (size_t) im->w * im->h; i < c; i++) memcpy(rgb + i * 3, im->px + i * 4, 3);
            n = WebPEncodeRGB(rgb, im->w, im->h, im->w * 3, (float) quality, &out);
            free(rgb);
        }
        if (!n || !out) return NULL;
        unsigned char *copy = (unsigned char *) malloc(n);
        if (copy) memcpy(copy, out, n);
        WebPFree(out);
        *outlen = (int) n;
        return copy;
    }
    si_mem m = {NULL, 0, 0};
    int ok;
    if (fmt == 1) {
        /* JPEG ไม่มี alpha: ส่วนโปร่งใสกลายเป็นสีของพิกเซลนั้น เหมือน gd */
        unsigned char *rgb = (unsigned char *) malloc((size_t) im->w * im->h * 3);
        if (!rgb) return NULL;
        for (size_t i = 0, c = (size_t) im->w * im->h; i < c; i++) memcpy(rgb + i * 3, im->px + i * 4, 3);
        ok = stbi_write_jpg_to_func(mem_write, &m, im->w, im->h, 3, rgb, quality);
        free(rgb);
    } else if (keep_alpha) {
        ok = stbi_write_png_to_func(mem_write, &m, im->w, im->h, 4, im->px, im->w * 4);
    } else {
        unsigned char *rgb = (unsigned char *) malloc((size_t) im->w * im->h * 3);
        if (!rgb) return NULL;
        for (size_t i = 0, c = (size_t) im->w * im->h; i < c; i++) memcpy(rgb + i * 3, im->px + i * 4, 3);
        ok = stbi_write_png_to_func(mem_write, &m, im->w, im->h, 3, rgb, im->w * 3);
        free(rgb);
    }
    if (!ok) {
        free(m.buf);
        return NULL;
    }
    *outlen = (int) m.len;
    return m.buf;
}
