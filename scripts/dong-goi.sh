#!/usr/bin/env bash
# =====================================================================
#  dong-goi.sh - Biên dịch và đóng gói toàn bộ hệ thống
#  Hệ thống phân luồng Mail công vụ - Thiết kế bởi Trương Anh Tuấn
# ---------------------------------------------------------------------
#  Cách dùng:
#     ./scripts/dong-goi.sh                # tăng số build rồi đóng gói
#     ./scripts/dong-goi.sh --bump minor   # tăng phiên bản phụ rồi đóng gói
#     ./scripts/dong-goi.sh --khong-bump   # giữ nguyên số phiên bản
#     ./scripts/dong-goi.sh --chi linux    # chỉ build Linux
#     ./scripts/dong-goi.sh --chi windows  # chỉ build Windows
#     ./scripts/dong-goi.sh --chi web      # chỉ đóng gói phần web
# =====================================================================
set -euo pipefail

GOC="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$GOC"

CHI="tat_ca"
BUMP="build"

while [ $# -gt 0 ]; do
    case "$1" in
        --bump)        BUMP="${2:-patch}"; shift 2 ;;
        --khong-bump)  BUMP="khong"; shift ;;
        --chi)         CHI="${2:-tat_ca}"; shift 2 ;;
        -h|--giup)     sed -n '2,17p' "$0"; exit 0 ;;
        *)             echo "Tham số không hợp lệ: $1" >&2; exit 2 ;;
    esac
done

mau()  { printf '\033[1;36m%s\033[0m\n' "$*"; }
xanh() { printf '\033[1;32m%s\033[0m\n' "$*"; }
vang() { printf '\033[1;33m%s\033[0m\n' "$*"; }

# ---------------------------------------------------------------------
#  1. Số phiên bản
# ---------------------------------------------------------------------
case "$BUMP" in
    khong) bash "$GOC/scripts/phien-ban.sh" sinh > /dev/null ;;
    build) bash "$GOC/scripts/phien-ban.sh" bump-build > /dev/null ;;
    *)     bash "$GOC/scripts/phien-ban.sh" bump "$BUMP" > /dev/null ;;
esac

PB="$(tr -d ' \t\r\n' < "$GOC/VERSION")"
BD="$(tr -d ' \t\r\n' < "$GOC/BUILD")"
NGAY="$(TZ='Asia/Ho_Chi_Minh' date '+%d/%m/%Y %H:%M')"

mau "==================================================================="
mau "  ĐÓNG GÓI HỆ THỐNG PHÂN LUỒNG MAIL CÔNG VỤ"
mau "  Phiên bản $PB (build $BD) - $NGAY (giờ Việt Nam)"
mau "==================================================================="

DIST="$GOC/dist"
mkdir -p "$DIST"

# ---------------------------------------------------------------------
#  2. Bộ nhận mail - Linux 64-bit
# ---------------------------------------------------------------------
if [ "$CHI" = "tat_ca" ] || [ "$CHI" = "linux" ]; then
    mau ">> Biên dịch bản Linux 64-bit..."
    cmake -S "$GOC/cpp" -B "$GOC/cpp/build" -DCMAKE_BUILD_TYPE=Release > /dev/null
    cmake --build "$GOC/cpp/build" -j"$(nproc 2>/dev/null || echo 2)" > /dev/null
    strip --strip-unneeded "$GOC/cpp/build/mailrouter" 2>/dev/null || true

    TM="$DIST/mailrouter-linux-x64"
    rm -rf "$TM"; mkdir -p "$TM"
    cp "$GOC/cpp/build/mailrouter" "$TM/"
    cp "$GOC/cpp/mailrouter.example.ini" "$TM/"
    cp "$GOC/scripts/mailrouter.service" "$TM/" 2>/dev/null || true
    cp "$GOC/docs/HUONG-DAN-BO-NHAN-MAIL.md" "$TM/HUONG-DAN.md" 2>/dev/null || true
    printf 'Phien ban %s (build %s) - dong goi %s\n' "$PB" "$BD" "$NGAY" > "$TM/PHIEN-BAN.txt"
    ( cd "$DIST" && tar -czf "mailrouter-linux-x64-v$PB.tar.gz" "mailrouter-linux-x64" )
    xanh "   OK: $TM/mailrouter ($(du -h "$TM/mailrouter" | cut -f1))"
fi

# ---------------------------------------------------------------------
#  3. Bộ nhận mail - Windows 64-bit
# ---------------------------------------------------------------------
if [ "$CHI" = "tat_ca" ] || [ "$CHI" = "windows" ]; then
    if command -v x86_64-w64-mingw32-g++ > /dev/null 2>&1; then
        mau ">> Biên dịch bản Windows 64-bit..."
        cmake -S "$GOC/cpp" -B "$GOC/cpp/build-win" \
              -DCMAKE_TOOLCHAIN_FILE="$GOC/cpp/cmake/mingw64.cmake" \
              -DCMAKE_BUILD_TYPE=Release > /dev/null
        cmake --build "$GOC/cpp/build-win" -j"$(nproc 2>/dev/null || echo 2)" > /dev/null
        x86_64-w64-mingw32-strip --strip-unneeded "$GOC/cpp/build-win/mailrouter.exe" 2>/dev/null || true

        TM="$DIST/mailrouter-windows-x64"
        rm -rf "$TM"; mkdir -p "$TM"
        cp "$GOC/cpp/build-win/mailrouter.exe" "$TM/"
        cp "$GOC/cpp/mailrouter.example.ini" "$TM/"
        cp "$GOC/scripts/chay-bo-nhan-mail.bat" "$TM/" 2>/dev/null || true
        cp "$GOC/docs/HUONG-DAN-BO-NHAN-MAIL.md" "$TM/HUONG-DAN.md" 2>/dev/null || true
        printf 'Phien ban %s (build %s) - dong goi %s\r\n' "$PB" "$BD" "$NGAY" > "$TM/PHIEN-BAN.txt"
        ( cd "$DIST" && zip -qr "mailrouter-windows-x64-v$PB.zip" "mailrouter-windows-x64" 2>/dev/null \
          || tar -czf "mailrouter-windows-x64-v$PB.tar.gz" "mailrouter-windows-x64" )
        xanh "   OK: $TM/mailrouter.exe ($(du -h "$TM/mailrouter.exe" | cut -f1))"
    else
        vang "   Bỏ qua bản Windows: chưa cài mingw-w64"
        vang "   Cài bằng: sudo apt install mingw-w64"
    fi
fi

# ---------------------------------------------------------------------
#  4. Phần web cho cPanel
# ---------------------------------------------------------------------
if [ "$CHI" = "tat_ca" ] || [ "$CHI" = "web" ]; then
    mau ">> Đóng gói phần web cho hosting cPanel..."
    TM="$DIST/web-cpanel"
    rm -rf "$TM"; mkdir -p "$TM"
    cp -r "$GOC/php/." "$TM/"
    rm -f "$TM/cau-hinh.php"          # không đóng gói cấu hình cục bộ
    mkdir -p "$TM/sql"
    cp "$GOC/sql/"*.sql "$TM/sql/"
    cp "$GOC/docs/HUONG-DAN-WEB-CPANEL.md" "$TM/HUONG-DAN.md" 2>/dev/null || true
    printf 'Phien ban %s (build %s) - dong goi %s\n' "$PB" "$BD" "$NGAY" > "$TM/PHIEN-BAN.txt"
    ( cd "$DIST" && zip -qr "web-cpanel-v$PB.zip" "web-cpanel" 2>/dev/null \
      || tar -czf "web-cpanel-v$PB.tar.gz" "web-cpanel" )
    xanh "   OK: $TM ($(du -sh "$TM" | cut -f1))"
fi

echo
xanh "Hoàn tất! Các gói nằm trong thư mục dist/:"
ls -1sh "$DIST" | sed 's/^/   /'
