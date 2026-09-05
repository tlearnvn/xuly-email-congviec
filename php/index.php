<?php
/**
 * index.php - Bộ điều hướng chính của phần web
 * HỆ THỐNG PHÂN LUỒNG MAIL CÔNG VỤ
 * Thiết kế bởi Trương Anh Tuấn
 */

require __DIR__ . '/app/khoi_dong.php';

$trang = Util::lay('t', 'bang-dieu-khien');
if (!preg_match('/^[a-z0-9\-]{1,40}$/', (string)$trang)) $trang = 'bang-dieu-khien';

// --------------------- Các trang không cần đăng nhập ---------------------
$congKhai = ['dang-nhap', 'dang-xuat'];

if (!in_array($trang, $congKhai, true)) {
    Auth::batBuocDangNhap();

    // Buộc đổi mật khẩu lần đầu
    $nd = Auth::nguoiDung();
    if ($nd && (int)$nd['doi_mat_khau'] === 1 && !in_array($trang, ['ho-so', 'dang-xuat'], true)) {
        Util::nhan('Vui lòng đổi mật khẩu trước khi sử dụng hệ thống.', 'canh');
        Util::chuyenHuong(Util::url('ho-so'));
    }
}

View::$trangHienTai = $trang;

$tep = DUONG_DAN_GOC . '/app/controllers/' . $trang . '.php';
if (!is_file($tep)) {
    http_response_code(404);
    View::$tieuDe = 'Không tìm thấy trang';
    View::hien('loi-404');
    exit;
}

require $tep;
