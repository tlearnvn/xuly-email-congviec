<?php
/** Điều khiển: Nhật ký hệ thống */

Auth::batBuocAdmin();

// --------------------------- Dọn nhật ký ---------------------------
if (Util::laPost() && Util::gui('viec') === 'don') {
    Util::batBuocToken();
    $n = NhatKy::don();
    NhatKy::canhBao('don_nhat_ky', "Dọn {$n} dòng nhật ký cũ");
    Util::nhan("Đã dọn {$n} dòng nhật ký cũ.", 'ok');
    Util::chuyenHuong(Util::url('nhat-ky'));
}

// --------------------------- Bộ lọc ---------------------------
$loc = [
    'muc'     => (string)Util::lay('muc'),
    'nguon'   => (string)Util::lay('nguon'),
    'tu_khoa' => (string)Util::lay('q'),
    'tu'      => (string)Util::lay('tu'),
    'den'     => (string)Util::lay('den'),
];

$dk = ['1=1'];
$ts = [];
if ($loc['muc'] !== '')   { $dk[] = 'muc = ?';   $ts[] = $loc['muc']; }
if ($loc['nguon'] !== '') { $dk[] = 'nguon = ?'; $ts[] = $loc['nguon']; }
if ($loc['tu_khoa'] !== '') {
    $dk[] = '(noi_dung LIKE ? OR hanh_dong LIKE ? OR ten_nguoi_dung LIKE ?)';
    $k = '%' . $loc['tu_khoa'] . '%';
    array_push($ts, $k, $k, $k);
}
if ($loc['tu'] !== '')  { $dk[] = 'thoi_gian >= ?'; $ts[] = $loc['tu'] . ' 00:00:00'; }
if ($loc['den'] !== '') { $dk[] = 'thoi_gian <= ?'; $ts[] = $loc['den'] . ' 23:59:59'; }
$where = implode(' AND ', $dk);

$tong = (int)Db::giaTri("SELECT COUNT(*) FROM nhat_ky WHERE $where", $ts, 0);
$moiTrang = 60;
$trangSo = max(1, (int)Util::lay('trang', 1));
$boQua = ($trangSo - 1) * $moiTrang;

$ds = Db::tatCa("SELECT * FROM nhat_ky WHERE $where ORDER BY id DESC LIMIT $moiTrang OFFSET $boQua", $ts);

$theoMuc = Db::capKhoa("SELECT muc, COUNT(*) FROM nhat_ky WHERE $where GROUP BY muc", $ts);
$dsNguon = Db::cot('SELECT DISTINCT nguon FROM nhat_ky ORDER BY nguon');

// Phiên đồng bộ gần đây
$phien = Db::tatCa('SELECT * FROM phien_dong_bo ORDER BY id DESC LIMIT 12');

View::$tieuDe = 'Nhật ký hệ thống';
View::hien('nhat-ky', compact('ds', 'tong', 'trangSo', 'moiTrang', 'loc', 'theoMuc', 'dsNguon', 'phien'));
