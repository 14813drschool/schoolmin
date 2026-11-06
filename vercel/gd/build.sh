#!/bin/sh
# คอมไพล์ libsmsimg.so สำหรับ Vercel (Amazon Linux 2, x86_64, glibc 2.26)
#
# ต้องมี: zig (pip install ziglang), ซอร์ส stb และ libwebp
#   git clone --depth 1 https://github.com/nothings/stb.git           $SRC/stb
#   git clone --depth 1 --branch v1.4.0 https://github.com/webmproject/libwebp.git $SRC/libwebp
# ใช้: SRC=/path/to/sources sh vercel/gd/build.sh
set -e
SRC=${SRC:-/var/tmp/imgbuild}
OUT=$(dirname "$0")/libsmsimg.so
W=$SRC/libwebp
python3 -m ziglang cc -target x86_64-linux-gnu.2.17 -O2 -fPIC -shared -fvisibility=hidden \
  -s -Wl,--gc-sections -ffunction-sections -fdata-sections \
  -I"$SRC/stb" -I"$W" -I"$W/src" \
  "$(dirname "$0")/smsimg.c" \
  $W/src/dec/*.c $W/src/enc/*.c $W/src/dsp/*.c $W/src/utils/*.c $W/sharpyuv/*.c \
  -lm -o "$OUT"
ls -la "$OUT"
