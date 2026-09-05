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
#
#  Ghi kèm lịch sử thay đổi vào mục "Lịch sử phiên bản" của README.md:
#     ./scripts/phien-ban.sh bump patch "Sửa lỗi tải tệp lớn" "Thêm bộ lọc địa bàn"
#     ./scripts/phien-ban.sh bump "Sửa lỗi tải tệp lớn"      # ngầm hiểu là patch
#     ./scripts/phien-ban.sh ghi-chu "Cập nhật tài liệu"     # ghi cho phiên bản hiện tại
# =====================================================================
set -euo pipefail

GOC="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TEP_VERSION="$GOC/VERSION"
TEP_BUILD="$GOC/BUILD"
TEP_README="$GOC/README.md"
MOC_CHANGELOG="<!-- BAT-DAU-CHANGELOG -->"

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

# ---------------------------------------------------------------------
#  Chèn một mục mới vào đầu danh sách lịch sử phiên bản trong README.md
#  Tham số: <số phiên bản> <ghi chú 1> [ghi chú 2] ...
# ---------------------------------------------------------------------
ghi_changelog() {
    local pb="$1"; shift
    [ $# -gt 0 ] || return 0

    if [ ! -f "$TEP_README" ]; then
        echo "Cảnh báo: không tìm thấy README.md, bỏ qua phần lịch sử phiên bản." >&2
        return 0
    fi
    if ! grep -qF "$MOC_CHANGELOG" "$TEP_README"; then
        echo "Cảnh báo: README.md chưa có mốc $MOC_CHANGELOG, bỏ qua phần lịch sử phiên bản." >&2
        return 0
    fi

    local ngay tam
    ngay="$(ngay_vn '%d/%m/%Y')"
    tam="$(mktemp)"
    {
        echo "### $pb — $ngay"
        echo
        local g
        for g in "$@"; do
            [ -n "$g" ] && echo "- $g"
        done
        echo
    } > "$tam"

    awk -v moc="$MOC_CHANGELOG" -v tepmoi="$tam" '
        { print }
        index($0, moc) {
            while ((getline dong < tepmoi) > 0) print dong
            close(tepmoi)
        }
    ' "$TEP_README" > "$TEP_README.tam" && mv "$TEP_README.tam" "$TEP_README"
    rm -f "$tam"

    echo "Đã thêm $# mục vào lịch sử phiên bản $pb trong README.md"
}

tang() {
    local loai="${1:-patch}"; shift || true
    local pb major minor patch
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
    ghi_changelog "$major.$minor.$patch" "$@"
}

LENH="${1:-doc}"
shift || true

case "$LENH" in
    doc)
        echo "$(doc_phien_ban) (build $(doc_build))"
        ;;
    bump)
        LOAI="patch"
        case "${1:-}" in
            major|minor|patch) LOAI="$1"; shift ;;
        esac
        tang "$LOAI" "$@"
        ;;
    bump-build)
        echo "$(( $(doc_build) + 1 ))" > "$TEP_BUILD"
        sinh_tep_phai_sinh
        ;;
    dat)
        [ -n "${1:-}" ] || { echo "Thiếu số phiên bản. Ví dụ: $0 dat 2.1.0" >&2; exit 2; }
        PB="$1"; shift
        echo "$PB" > "$TEP_VERSION"
        echo "$(( $(doc_build) + 1 ))" > "$TEP_BUILD"
        sinh_tep_phai_sinh
        ghi_changelog "$PB" "$@"
        ;;
    ghi-chu)
        [ $# -gt 0 ] || { echo "Thiếu nội dung ghi chú. Ví dụ: $0 ghi-chu \"Sửa lỗi X\"" >&2; exit 2; }
        ghi_changelog "$(doc_phien_ban)" "$@"
        ;;
    sinh)
        sinh_tep_phai_sinh
        ;;
    *)
        echo "Tham số không hợp lệ: $LENH" >&2
        sed -n '2,19p' "$0" >&2
        exit 2
        ;;
esac
