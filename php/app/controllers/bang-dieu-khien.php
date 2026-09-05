<?php
/** Điều khiển: Bảng điều khiển (dashboard) */

$laAdmin  = Auth::laAdmin();
$xemTatCa = Auth::xemTatCa();
$toi      = Auth::id();

// Điều kiện giới hạn theo quyền
$dk  = $xemTatCa ? '1=1' : 'cv.id_nguoi_xu_ly = ?';
$ts  = $xemTatCa ? [] : [$toi];

// --------------------------- Ô số liệu ---------------------------
$so = [
    'tong'        => (int)Db::giaTri("SELECT COUNT(*) FROM cong_viec cv WHERE $dk AND cv.la_ban_moi_nhat = 1", $ts, 0),
    'cho_xu_ly'   => (int)Db::giaTri("SELECT COUNT(*) FROM cong_viec cv WHERE $dk AND cv.trang_thai = 'cho_xu_ly' AND cv.la_ban_moi_nhat = 1", $ts, 0),
    'dang_xu_ly'  => (int)Db::giaTri("SELECT COUNT(*) FROM cong_viec cv WHERE $dk AND cv.trang_thai = 'dang_xu_ly' AND cv.la_ban_moi_nhat = 1", $ts, 0),
    'da_xu_ly'    => (int)Db::giaTri("SELECT COUNT(*) FROM cong_viec cv WHERE $dk AND cv.trang_thai = 'da_xu_ly' AND cv.la_ban_moi_nhat = 1", $ts, 0),
    'thang_nay'   => (int)Db::giaTri("SELECT COUNT(*) FROM cong_viec cv WHERE $dk AND cv.la_ban_moi_nhat = 1
                                      AND YEAR(cv.ngay_nhan) = YEAR(NOW()) AND MONTH(cv.ngay_nhan) = MONTH(NOW())", $ts, 0),
    'qua_han'     => (int)Db::giaTri("SELECT COUNT(*) FROM cong_viec cv WHERE $dk AND cv.la_ban_moi_nhat = 1
                                      AND cv.trang_thai IN ('cho_xu_ly','dang_xu_ly')
                                      AND cv.han_xu_ly IS NOT NULL AND cv.han_xu_ly < CURDATE()", $ts, 0),
    'cho_phan_luong' => (int)Db::giaTri("SELECT COUNT(*) FROM cong_viec WHERE trang_thai = 'cho_phan_luong'", [], 0),
    'tong_tep'    => (int)Db::giaTri("SELECT COUNT(*) FROM tep_dinh_kem", [], 0),
    'dung_luong'  => (int)Db::giaTri("SELECT IFNULL(SUM(dung_luong),0) FROM tep_du_lieu WHERE da_hoan_tat = 1", [], 0),
    'tong_email'  => (int)Db::giaTri("SELECT COUNT(*) FROM email", [], 0),
];

// --------------------------- Biểu đồ theo trạng thái ---------------------------
$theoTrangThai = Db::capKhoa("SELECT trang_thai, COUNT(*) FROM cong_viec cv WHERE $dk AND cv.la_ban_moi_nhat = 1 GROUP BY trang_thai", $ts);
$mauTT = [
    'cho_xu_ly'      => '#1e5eff',
    'dang_xu_ly'     => '#7748e6',
    'da_xu_ly'       => '#10a05a',
    'cho_phan_luong' => '#e08a06',
    'tu_choi'        => '#dc4437',
    'trung_lap'      => '#8b95a8',
];
$mucTron = [];
foreach ($mauTT as $k => $m) {
    if (!empty($theoTrangThai[$k])) {
        $mucTron[] = ['nhan' => Util::tenTrangThai($k), 'gia_tri' => (int)$theoTrangThai[$k], 'mau' => $m];
    }
}

// --------------------------- Biểu đồ 6 tháng gần nhất ---------------------------
$theoThang = [];
for ($i = 5; $i >= 0; $i--) {
    $t = strtotime("-$i month");
    $n = (int)Db::giaTri(
        "SELECT COUNT(*) FROM cong_viec cv WHERE $dk AND cv.la_ban_moi_nhat = 1
         AND YEAR(cv.ngay_nhan) = ? AND MONTH(cv.ngay_nhan) = ?",
        array_merge($ts, [(int)date('Y', $t), (int)date('n', $t)]), 0);
    $theoThang[] = ['nhan' => 'Tháng ' . date('n/Y', $t), 'nhan_ngan' => date('n/Y', $t), 'gia_tri' => $n];
}

// --------------------------- Tiến độ nộp báo cáo ---------------------------
$tongTruong = (int)Db::giaTri('SELECT COUNT(*) FROM truong WHERE trang_thai = 1', [], 0);
$baoCao = [];
foreach (Db::tatCa("SELECT id, ma_van_ban, ten_van_ban, ky_bao_cao, han_nop
                    FROM van_ban WHERE bat_buoc_nop = 1 AND trang_thai = 1
                    ORDER BY (han_nop IS NULL), han_nop DESC, ma_van_ban LIMIT 6") as $vb) {
    $daNop = (int)Db::giaTri(
        'SELECT COUNT(DISTINCT id_truong) FROM cong_viec
         WHERE id_van_ban = ? AND id_truong IS NOT NULL AND trang_thai <> "trung_lap"',
        [$vb['id']], 0);
    $baoCao[] = [
        'id' => (int)$vb['id'], 'ma' => $vb['ma_van_ban'], 'ten' => $vb['ten_van_ban'],
        'ky' => $vb['ky_bao_cao'], 'han_nop' => $vb['han_nop'],
        'da_nop' => $daNop, 'chua_nop' => max(0, $tongTruong - $daNop),
        'ti_le' => $tongTruong > 0 ? $daNop * 100 / $tongTruong : 0,
    ];
}

// --------------------------- Danh sách gần đây ---------------------------
$ganDay = Db::tatCa(
    "SELECT cv.*, t.ten_truong, vb.ten_van_ban, n.ho_ten
     FROM cong_viec cv
     LEFT JOIN truong t ON t.id = cv.id_truong
     LEFT JOIN van_ban vb ON vb.id = cv.id_van_ban
     LEFT JOIN nguoi_xu_ly n ON n.id = cv.id_nguoi_xu_ly
     WHERE $dk AND cv.la_ban_moi_nhat = 1
     ORDER BY cv.ngay_nhan DESC, cv.id DESC LIMIT 8", $ts);

// --------------------------- Phiên đồng bộ gần nhất ---------------------------
$phien = Db::mot('SELECT * FROM phien_dong_bo ORDER BY id DESC LIMIT 1');

// --------------------------- Top người xử lý (chỉ quản trị) ---------------------------
$topNguoi = [];
if ($xemTatCa) {
    $topNguoi = Db::tatCa(
        "SELECT n.ho_ten, n.ma_nguoi_xu_ly, COUNT(cv.id) tong,
                SUM(cv.trang_thai = 'da_xu_ly') da_xong
         FROM nguoi_xu_ly n
         LEFT JOIN cong_viec cv ON cv.id_nguoi_xu_ly = n.id AND cv.la_ban_moi_nhat = 1
         WHERE n.trang_thai = 1
         GROUP BY n.id ORDER BY tong DESC LIMIT 6");
}

View::$tieuDe = 'Bảng điều khiển';
View::hien('bang-dieu-khien', compact(
    'so', 'mucTron', 'theoThang', 'baoCao', 'tongTruong', 'ganDay', 'phien', 'topNguoi', 'xemTatCa', 'laAdmin'));
