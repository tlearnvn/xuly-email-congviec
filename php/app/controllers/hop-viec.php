<?php
/** Điều khiển: Hộp việc của tôi / Tất cả văn bản (dùng chung) */

$tatCa = (View::$trangHienTai === 'van-ban');
if ($tatCa && !Auth::xemTatCa()) {
    Util::nhan('Bạn không có quyền xem toàn bộ văn bản.', 'canh');
    Util::chuyenHuong(Util::url('hop-viec'));
}

// --------------------------- Cập nhật trạng thái nhanh ---------------------------
if (Util::laPost() && Util::gui('viec') === 'doi_trang_thai') {
    Util::batBuocToken();
    $ids = array_filter(array_map('intval', (array)($_POST['chon'] ?? [])));
    $tt  = Util::gui('trang_thai_moi');
    $hopLe = ['cho_xu_ly', 'dang_xu_ly', 'da_xu_ly', 'tu_choi'];
    if ($ids && in_array($tt, $hopLe, true)) {
        $cho = implode(',', array_fill(0, count($ids), '?'));
        $dk = Auth::xemTatCa() ? '' : ' AND id_nguoi_xu_ly = ' . Auth::id();
        $n = Db::chay("UPDATE cong_viec SET trang_thai = ?, ngay_cap_nhat = NOW(),
                       ngay_xu_ly = IF(? = 'da_xu_ly', NOW(), ngay_xu_ly)
                       WHERE id IN ($cho)$dk",
                      array_merge([$tt, $tt], $ids))->rowCount();
        NhatKy::tin('doi_trang_thai', "Đổi trạng thái $n văn bản sang " . Util::tenTrangThai($tt),
                    'cong_viec', implode(',', $ids));
        Util::nhan("Đã cập nhật $n văn bản sang trạng thái “" . Util::tenTrangThai($tt) . '”.', 'ok');
    } else {
        Util::nhan('Chưa chọn văn bản hoặc trạng thái không hợp lệ.', 'canh');
    }
    Util::chuyenHuong(Util::urlGiu(['viec' => null]));
}

// --------------------------- Bộ lọc ---------------------------
$loc = [
    'tu_khoa'    => (string)Util::lay('q'),
    'trang_thai' => (string)Util::lay('tt'),
    'truong'     => (int)Util::lay('truong', 0),
    'van_ban'    => (int)Util::lay('vb', 0),
    'nguoi'      => (int)Util::lay('nxl', 0),
    'tu_ngay'    => (string)Util::lay('tu'),
    'den_ngay'   => (string)Util::lay('den'),
];

$dk = ['cv.la_ban_moi_nhat = 1'];
$ts = [];

if (!$tatCa || !Auth::xemTatCa()) { $dk[] = 'cv.id_nguoi_xu_ly = ?'; $ts[] = Auth::id(); }
if (!$tatCa) $dk[] = "cv.trang_thai <> 'cho_phan_luong'";

if ($loc['tu_khoa'] !== '') {
    $dk[] = '(cv.tieu_de LIKE ? OR cv.trich_yeu LIKE ? OR cv.ma_ho_so LIKE ? OR t.ten_truong LIKE ?)';
    $k = '%' . $loc['tu_khoa'] . '%';
    array_push($ts, $k, $k, $k, $k);
}
if ($loc['trang_thai'] !== '') { $dk[] = 'cv.trang_thai = ?'; $ts[] = $loc['trang_thai']; }
if ($loc['truong'] > 0)  { $dk[] = 'cv.id_truong = ?';  $ts[] = $loc['truong']; }
if ($loc['van_ban'] > 0) { $dk[] = 'cv.id_van_ban = ?'; $ts[] = $loc['van_ban']; }
if ($loc['nguoi'] > 0 && $tatCa) { $dk[] = 'cv.id_nguoi_xu_ly = ?'; $ts[] = $loc['nguoi']; }
if ($loc['tu_ngay'] !== '')  { $dk[] = 'cv.ngay_nhan >= ?'; $ts[] = $loc['tu_ngay'] . ' 00:00:00'; }
if ($loc['den_ngay'] !== '') { $dk[] = 'cv.ngay_nhan <= ?'; $ts[] = $loc['den_ngay'] . ' 23:59:59'; }

$where = implode(' AND ', $dk);

$sqlDem = "SELECT COUNT(*) FROM cong_viec cv LEFT JOIN truong t ON t.id = cv.id_truong WHERE $where";
$tong = (int)Db::giaTri($sqlDem, $ts, 0);

$moiTrang = Ung::soDongMoiTrang();
$trangSo = max(1, (int)Util::lay('trang', 1));
$boQua = ($trangSo - 1) * $moiTrang;

$ds = Db::tatCa(
    "SELECT cv.*, t.ten_truong, vb.ten_van_ban, n.ho_ten, e.lien_ket_ngoai
     FROM cong_viec cv
     LEFT JOIN email e ON e.id = cv.id_email
     LEFT JOIN truong t ON t.id = cv.id_truong
     LEFT JOIN van_ban vb ON vb.id = cv.id_van_ban
     LEFT JOIN nguoi_xu_ly n ON n.id = cv.id_nguoi_xu_ly
     WHERE $where
     ORDER BY cv.ngay_nhan DESC, cv.id DESC
     LIMIT $moiTrang OFFSET $boQua", $ts);

// Dữ liệu cho bộ lọc
$dsTruong = Db::tatCa('SELECT id, ma_truong, ten_truong FROM truong WHERE trang_thai = 1 ORDER BY thu_tu, ma_truong');
$dsVanBan = Db::tatCa('SELECT id, ma_van_ban, ten_van_ban FROM van_ban WHERE trang_thai = 1 ORDER BY ma_van_ban');
$dsNguoi  = $tatCa ? Db::tatCa('SELECT id, ma_nguoi_xu_ly, ho_ten FROM nguoi_xu_ly WHERE trang_thai = 1 ORDER BY ho_ten') : [];

View::$tieuDe = $tatCa ? 'Tất cả văn bản' : 'Hộp việc của tôi';
View::hien('danh-sach-van-ban', compact(
    'ds', 'tong', 'trangSo', 'moiTrang', 'loc', 'dsTruong', 'dsVanBan', 'dsNguoi', 'tatCa'));
