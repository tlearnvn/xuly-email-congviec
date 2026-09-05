#!/usr/bin/env bash
# =====================================================================
#  phien-ban.sh - Quản lý số phiên bản của hệ thống
#  Hệ thống phân luồng Mail công vụ - Thiết kế bởi Trương Anh Tuấn
# ---------------------------------------------------------------------
#  Cách dùng:
#     ./scripts/phien-ban.sh doc            # in ra phiên bản hiện tại
#     ./scripts/phien-ban.sh bump           # tăng số vá (1.0.3 -> 1.0.4)
#     ./scripts/phien-ban.sh bump minor     # 1.0.4 -> 1.1.0
#     ./scripts/phien-ban.sh bump major     # 1.1.0 -> 2.0.0
#     ./scripts/phien-ban.sh bump-build     # chỉ tăng số lần build
#     ./scripts/phien-ban.sh dat 2.3.1      # đặt phiên bản cụ thể
#     ./scripts/phien-ban.sh sinh           # sinh lại các tệp phái sinh
# =====================================================================
set -euo pipefail

GOC="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TEP_VERSION="$GOC/VERSION"
TEP_BUILD="$GOC/BUILD"

[ -f "$TEP_VERSION" ] || echo "1.0.0" > "$TEP_VERSION"
[ -f "$TEP_BUILD" ]   || echo "0"     > "$TEP_BUILD"

doc_phien_ban() { tr -d ' \t\r\n' < "$TEP_VERSION"; }
doc_build()     { tr -d ' \t\r\n' < "$TEP_BUILD"; }

# Ngày giờ Việt Nam (GMT+7) không phụ thuộc múi giờ máy
ngay_vn() { TZ='Asia/Ho_Chi_Minh' date "+$1"; }

sinh_tep_phai_sinh() {
    local pb bd ngay
    pb="$(doc_phien_ban)"
    bd="$(doc_build)"
    ngay="$(ngay_vn '%Y-%m-%d %H:%M:%S')"

    # ---- Tệp phiên bản cho phần web PHP ----
    mkdir -p "$GOC/php/app"
    cat > "$GOC/php/app/phien_ban.php" <<EOF
<?php
/**
 * phien_ban.php - TỆP SINH TỰ ĐỘNG, KHÔNG SỬA TAY
 * Sinh bởi scripts/phien-ban.sh lúc $ngay (giờ Việt Nam)
 */
return [
    'phien_ban' => '$pb',
    'build'     => $bd,
    'ngay'      => '$ngay',
    'day_du'    => '$pb (build $bd)',
];
EOF

    # ---- Tệp phiên bản cho phần C++ ----
    mkdir -p "$GOC/cpp/include"
    cat > "$GOC/cpp/include/phien_ban.h" <<EOF
// =====================================================================
//  phien_ban.h - TỆP SINH TỰ ĐỘNG, KHÔNG SỬA TAY
//  Sinh bởi scripts/phien-ban.sh lúc $ngay (giờ Việt Nam)
// =====================================================================
#pragma once

#define MR_PHIEN_BAN      "$pb"
#define MR_SO_BUILD       $bd
#define MR_NGAY_BUILD     "$ngay"
#define MR_PHIEN_BAN_DAY_DU "$pb (build $bd - $ngay)"
EOF

    echo "Đã sinh tệp phiên bản: $pb (build $bd) - $ngay"
}

tang() {
    local loai="${1:-patch}" pb major minor patch
    pb="$(doc_phien_ban)"
    IFS='.' read -r major minor patch <<< "$pb"
    major="${major:-1}"; minor="${minor:-0}"; patch="${patch:-0}"
    case "$loai" in
        major) major=$((major + 1)); minor=0; patch=0 ;;
        minor) minor=$((minor + 1)); patch=0 ;;
        patch|*) patch=$((patch + 1)) ;;
    esac
    echo "$major.$minor.$patch" > "$TEP_VERSION"
    echo "$(( $(doc_build) + 1 ))" > "$TEP_BUILD"
    sinh_tep_phai_sinh
}

case "${1:-doc}" in
    doc)
        echo "$(doc_phien_ban) (build $(doc_build))"
        ;;
    bump)
        tang "${2:-patch}"
        ;;
    bump-build)
        echo "$(( $(doc_build) + 1 ))" > "$TEP_BUILD"
        sinh_tep_phai_sinh
        ;;
    dat)
        [ -n "${2:-}" ] || { echo "Thiếu số phiên bản. Ví dụ: $0 dat 2.1.0" >&2; exit 2; }
        echo "$2" > "$TEP_VERSION"
        echo "$(( $(doc_build) + 1 ))" > "$TEP_BUILD"
        sinh_tep_phai_sinh
        ;;
    sinh)
        sinh_tep_phai_sinh
        ;;
    *)
        echo "Tham số không hợp lệ: $1" >&2
        sed -n '2,16p' "$0" >&2
        exit 2
        ;;
esac
