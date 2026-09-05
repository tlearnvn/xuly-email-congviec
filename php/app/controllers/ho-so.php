<?php
/** Điều khiển: Hồ sơ cá nhân & đổi mật khẩu */

$nd = Auth::nguoiDung();

if (Util::laPost()) {
    Util::batBuocToken();
    $viec = Util::gui('viec');

    if ($viec === 'thong_tin') {
        Db::capNhat('nguoi_xu_ly', [
            'ho_ten'        => mb_substr(Util::gui('ho_ten'), 0, 150),
            'email'         => mb_substr(Util::gui('email'), 0, 191),
            'dien_thoai'    => mb_substr(Util::gui('dien_thoai'), 0, 30),
            'chuc_vu'       => mb_substr(Util::gui('chuc_vu'), 0, 150),
            'ngay_cap_nhat' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$nd['id']]);
        NhatKy::tin('cap_nhat_ho_so', 'Cập nhật thông tin cá nhân', 'nguoi_xu_ly', (string)$nd['id']);
        Util::nhan('Đã lưu thông tin cá nhân.', 'ok');
        Util::chuyenHuong(Util::url('ho-so'));
    }

    if ($viec === 'doi_mat_khau') {
        $cu    = (string)($_POST['mat_khau_cu'] ?? '');
        $moi   = (string)($_POST['mat_khau_moi'] ?? '');
        $nhac  = (string)($_POST['nhac_lai'] ?? '');

        if (!password_verify($cu, $nd['mat_khau'])) {
            Util::nhan('Mật khẩu hiện tại không đúng.', 'loi');
        } elseif (mb_strlen($moi) < 6) {
            Util::nhan('Mật khẩu mới phải từ 6 ký tự trở lên.', 'loi');
        } elseif ($moi !== $nhac) {
            Util::nhan('Hai lần nhập mật khẩu mới không khớp nhau.', 'loi');
        } elseif ($moi === $cu) {
            Util::nhan('Mật khẩu mới phải khác mật khẩu hiện tại.', 'loi');
        } else {
            Db::capNhat('nguoi_xu_ly', [
                'mat_khau'      => password_hash($moi, PASSWORD_DEFAULT),
                'doi_mat_khau'  => 0,
                'ngay_cap_nhat' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$nd['id']]);
            Db::xoa('phien_ghi_nho', 'id_nguoi_xu_ly = ?', [$nd['id']]);
            NhatKy::canhBao('doi_mat_khau', 'Đổi mật khẩu tài khoản', 'nguoi_xu_ly', (string)$nd['id']);
            Util::nhan('Đã đổi mật khẩu thành công.', 'ok');
        }
        Util::chuyenHuong(Util::url('ho-so'));
    }
}

// Thống kê cá nhân
$thongKe = [
    'Tổng văn bản được giao' => (int)Db::giaTri(
        'SELECT COUNT(*) FROM cong_viec WHERE id_nguoi_xu_ly = ? AND la_ban_moi_nhat = 1', [$nd['id']], 0),
    'Đang chờ xử lý' => (int)Db::giaTri(
        "SELECT COUNT(*) FROM cong_viec WHERE id_nguoi_xu_ly = ? AND trang_thai = 'cho_xu_ly' AND la_ban_moi_nhat = 1", [$nd['id']], 0),
    'Đã xử lý xong' => (int)Db::giaTri(
        "SELECT COUNT(*) FROM cong_viec WHERE id_nguoi_xu_ly = ? AND trang_thai = 'da_xu_ly' AND la_ban_moi_nhat = 1", [$nd['id']], 0),
    'Quá hạn' => (int)Db::giaTri(
        "SELECT COUNT(*) FROM cong_viec WHERE id_nguoi_xu_ly = ? AND la_ban_moi_nhat = 1
         AND trang_thai IN ('cho_xu_ly','dang_xu_ly') AND han_xu_ly IS NOT NULL AND han_xu_ly < CURDATE()",
        [$nd['id']], 0),
];

$biDanh = Db::cot('SELECT bi_danh FROM bi_danh_nguoi_xu_ly WHERE id_nguoi_xu_ly = ?', [$nd['id']]);

View::$tieuDe = 'Hồ sơ cá nhân';
View::hien('ho-so', compact('nd', 'thongKe', 'biDanh'));
