<?php
/** Điều khiển: Chi tiết một văn bản / công việc */

$id = (int)Util::lay('id', 0);
$cv = Db::mot(
    'SELECT cv.*, t.ten_truong, t.ma_truong AS ma_truong_dm, vb.ten_van_ban, vb.ma_van_ban AS ma_vb_dm,
            vb.tu_dong_tao, n.ho_ten, n.ma_nguoi_xu_ly AS ma_nxl_dm, n.email AS email_nxl
     FROM cong_viec cv
     LEFT JOIN truong t ON t.id = cv.id_truong
     LEFT JOIN van_ban vb ON vb.id = cv.id_van_ban
     LEFT JOIN nguoi_xu_ly n ON n.id = cv.id_nguoi_xu_ly
     WHERE cv.id = ?', [$id]);

if (!$cv) {
    http_response_code(404);
    View::$tieuDe = 'Không tìm thấy văn bản';
    View::hien('loi-404');
    exit;
}

// Kiểm tra quyền xem
if (!Auth::xemTatCa() && (int)$cv['id_nguoi_xu_ly'] !== Auth::id()) {
    http_response_code(403);
    Util::nhan('Bạn không được phân công xử lý văn bản này.', 'canh');
    Util::chuyenHuong(Util::url('hop-viec'));
}

// --------------------------- Hành động ---------------------------
if (Util::laPost()) {
    Util::batBuocToken();
    $viec = Util::gui('viec');

    if ($viec === 'trang_thai') {
        $tt = Util::gui('trang_thai');
        if (in_array($tt, ['cho_xu_ly', 'dang_xu_ly', 'da_xu_ly', 'tu_choi'], true)) {
            Db::capNhat('cong_viec', [
                'trang_thai'    => $tt,
                'ket_qua'       => mb_substr(Util::gui('ket_qua'), 0, 5000),
                'ngay_xu_ly'    => ($tt === 'da_xu_ly' || $tt === 'tu_choi') ? date('Y-m-d H:i:s') : null,
                'ngay_cap_nhat' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$id]);
            NhatKy::tin('doi_trang_thai', 'Chuyển văn bản sang “' . Util::tenTrangThai($tt) . '”',
                        'cong_viec', (string)$id);
            Util::nhan('Đã cập nhật trạng thái văn bản.', 'ok');
        }
    } elseif ($viec === 'ghi_chu') {
        Db::capNhat('cong_viec', [
            'ghi_chu'       => mb_substr(Util::gui('ghi_chu'), 0, 4000),
            'muc_do'        => in_array(Util::gui('muc_do'), ['thuong', 'khan', 'hoa_toc'], true)
                               ? Util::gui('muc_do') : 'thuong',
            'han_xu_ly'     => Util::gui('han_xu_ly') ?: null,
            'ngay_cap_nhat' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
        NhatKy::tin('cap_nhat', 'Cập nhật ghi chú / hạn xử lý', 'cong_viec', (string)$id);
        Util::nhan('Đã lưu ghi chú.', 'ok');
    } elseif ($viec === 'chuyen' && Auth::laAdmin()) {
        $moi = (int)Util::gui('id_nguoi_moi', 0);
        $ng = $moi ? Db::mot('SELECT * FROM nguoi_xu_ly WHERE id = ? AND trang_thai = 1', [$moi]) : null;
        if ($ng) {
            Db::capNhat('cong_viec', [
                'id_nguoi_xu_ly'      => $moi,
                'ma_nguoi_xu_ly'      => $ng['ma_nguoi_xu_ly'],
                'nguon_phan_luong'    => 'thu_cong',
                'trang_thai'          => 'cho_xu_ly',
                'id_nguoi_phan_luong' => Auth::id(),
                'ghi_chu'             => trim(($cv['ghi_chu'] ?? '') . ' | Chuyển cho ' . $ng['ho_ten']
                                          . ' bởi ' . Auth::ten() . ' lúc ' . Util::bayGio('d/m/Y H:i'), ' |'),
                'ngay_cap_nhat'       => date('Y-m-d H:i:s'),
            ], 'id = ?', [$id]);
            NhatKy::tin('chuyen_xu_ly', 'Chuyển văn bản cho ' . $ng['ho_ten'], 'cong_viec', (string)$id);
            Util::nhan('Đã chuyển văn bản cho ' . $ng['ho_ten'] . '.', 'ok');
        }
    }
    Util::chuyenHuong(Util::url('chi-tiet', ['id' => $id]));
}

// Đánh dấu đã xem
if ((int)$cv['da_xem'] === 0 && (int)$cv['id_nguoi_xu_ly'] === Auth::id()) {
    Db::capNhat('cong_viec', ['da_xem' => 1, 'ngay_xem' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
    $cv['da_xem'] = 1;
}

// --------------------------- Dữ liệu liên quan ---------------------------
$email = Db::mot('SELECT * FROM email WHERE id = ?', [$cv['id_email']]);

$tep = Db::tatCa(
    'SELECT tdk.*, IFNULL(tdl.da_hoan_tat,0) AS co_du_lieu
     FROM cong_viec_tep cvt
     JOIN tep_dinh_kem tdk ON tdk.id = cvt.id_tep_dinh_kem
     LEFT JOIN tep_du_lieu tdl ON tdl.id = tdk.id_tep_du_lieu
     WHERE cvt.id_cong_viec = ? ORDER BY tdk.thu_tu, tdk.id', [$id]);

if (!$tep) {
    $tep = Db::tatCa(
        'SELECT tdk.*, IFNULL(tdl.da_hoan_tat,0) AS co_du_lieu
         FROM tep_dinh_kem tdk
         LEFT JOIN tep_du_lieu tdl ON tdl.id = tdk.id_tep_du_lieu
         WHERE tdk.id_email = ? ORDER BY tdk.thu_tu, tdk.id', [$cv['id_email']]);
}

// Các phiên bản khác cùng mã hồ sơ
$phienBan = [];
if (!empty($cv['ma_ho_so']) && $cv['ma_ho_so'] !== '?_?_?') {
    $phienBan = Db::tatCa(
        'SELECT cv.id, cv.phien_ban, cv.la_ban_moi_nhat, cv.ngay_nhan, cv.so_tep, cv.tong_dung_luong
         FROM cong_viec cv WHERE cv.ma_ho_so = ? ORDER BY cv.phien_ban DESC, cv.id DESC LIMIT 20',
        [$cv['ma_ho_so']]);
}

$dsNguoi = Auth::laAdmin()
    ? Db::tatCa('SELECT id, ma_nguoi_xu_ly, ho_ten FROM nguoi_xu_ly WHERE trang_thai = 1 ORDER BY ho_ten')
    : [];

View::$tieuDe = 'Chi tiết văn bản';
View::hien('chi-tiet', compact('cv', 'email', 'tep', 'phienBan', 'dsNguoi'));
