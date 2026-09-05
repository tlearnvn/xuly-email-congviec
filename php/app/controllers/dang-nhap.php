<?php
/** Điều khiển: Đăng nhập */

if (Auth::daDangNhap()) Util::chuyenHuong(Util::url('bang-dieu-khien'));

$loi = '';
$tenDangNhap = '';

if (Util::laPost()) {
    Util::batBuocToken();
    $tenDangNhap = Util::gui('ten_dang_nhap');
    $matKhau     = (string)($_POST['mat_khau'] ?? '');
    $ghiNho      = Util::gui('ghi_nho') === '1';

    if ($tenDangNhap === '' || $matKhau === '') {
        $loi = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.';
    } else {
        [$ok, $tb] = Auth::dangNhap($tenDangNhap, $matKhau, $ghiNho);
        if ($ok) {
            $ke = (string)Util::lay('ke', '');
            if ($ke !== '' && strpos($ke, '//') === false && $ke[0] === '/') Util::chuyenHuong($ke);
            Util::chuyenHuong(Util::url('bang-dieu-khien'));
        }
        $loi = $tb;
    }
}

View::$tieuDe = 'Đăng nhập';
View::hien('dang-nhap', ['loi' => $loi, 'ten_dang_nhap' => $tenDangNhap], '');
