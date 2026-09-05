<?php
/** Điều khiển: Hàng chờ phân luồng tay (quản trị) */

Auth::batBuocAdmin();

// --------------------------- Gợi ý bằng AI (AJAX) ---------------------------
if (Util::lay('viec') === 'ai') {
    $idCv = (int)Util::lay('id', 0);
    $cv = Db::mot('SELECT cv.*, e.* FROM cong_viec cv JOIN email e ON e.id = cv.id_email WHERE cv.id = ?', [$idCv]);
    if (!$cv) Util::json(['ok' => false, 'loi' => 'Không tìm thấy văn bản'], 404);

    $tenTep = Db::cot('SELECT ten_tep FROM tep_dinh_kem WHERE id_email = ?', [$cv['id_email']]);
    [$ok, $kq] = Ai::goiYPhanLuong($cv, $tenTep);
    if (!$ok) Util::json(['ok' => false, 'loi' => $kq]);
    NhatKy::tin('ai_goi_y', 'Nhờ AI gợi ý phân luồng: ' . $kq['ma_truong'] . '_' . $kq['ma_van_ban']
                . '_' . $kq['ma_nguoi_xu_ly'], 'cong_viec', (string)$idCv);
    Util::json(['ok' => true, 'du_lieu' => $kq]);
}

// --------------------------- Lưu phân luồng ---------------------------
if (Util::laPost() && Util::gui('viec') === 'phan_luong') {
    Util::batBuocToken();
    $idCv    = (int)Util::gui('id', 0);
    $maT     = Util::gui('ma_truong');
    $maV     = Util::gui('ma_van_ban');
    $maN     = Util::gui('ma_nguoi_xu_ly');
    $ghiChu  = Util::gui('ghi_chu');
    $apDungTuongTu = Util::gui('ap_dung_tuong_tu') === '1';

    $cv = Db::mot('SELECT * FROM cong_viec WHERE id = ?', [$idCv]);
    if (!$cv) {
        Util::nhan('Không tìm thấy văn bản cần phân luồng.', 'loi');
        Util::chuyenHuong(Util::url('phan-luong'));
    }

    $truong = $maT !== '' ? Db::mot('SELECT * FROM truong WHERE ma_truong = ? OR ma_chuan = ?',
                                    [$maT, Util::chuanHoaMaSo($maT)]) : null;
    $nguoi  = $maN !== '' ? Db::mot('SELECT * FROM nguoi_xu_ly WHERE ma_nguoi_xu_ly = ? AND trang_thai = 1',
                                    [strtoupper($maN)]) : null;
    $vanBan = $maV !== '' ? Db::mot('SELECT * FROM van_ban WHERE ma_van_ban = ? OR ma_chuan = ?',
                                    [$maV, Util::chuanHoaMaSo($maV)]) : null;

    $loi = [];
    if ($maT !== '' && !$truong) $loi[] = "Mã trường “{$maT}” không có trong danh mục.";
    if (!$nguoi) $loi[] = 'Phải chọn người xử lý có trong danh mục.';
    if ($maV !== '' && !$vanBan) {
        if (Ung::bat('phanluong.cho_phep_ma_vb_moi', true)) {
            try {
                $v = LuuMail::taoVanBanTuDong($maV);
                $vanBan = Db::mot('SELECT * FROM van_ban WHERE id = ?', [$v['id']]);
                Util::nhan("Đã thêm mã văn bản mới “{$maV}” vào danh mục.", 'tin');
            } catch (Throwable $e) {
                $loi[] = 'Không thêm được mã văn bản mới: ' . $e->getMessage();
            }
        } else {
            $loi[] = "Mã văn bản “{$maV}” không có trong danh mục và hệ thống đang khoá việc tự thêm mã mới.";
        }
    }

    if ($loi) {
        Util::nhan(implode(' ', $loi), 'loi');
        Util::chuyenHuong(Util::url('phan-luong', ['id' => $idCv]));
    }

    $maHoSo = ($truong['ma_truong'] ?? '?') . '_' . ($vanBan['ma_van_ban'] ?? '?')
            . '_' . $nguoi['ma_nguoi_xu_ly'];

    Db::capNhat('cong_viec', [
        'id_truong'           => $truong['id'] ?? null,
        'id_van_ban'          => $vanBan['id'] ?? null,
        'id_nguoi_xu_ly'      => $nguoi['id'],
        'ma_truong'           => $truong['ma_truong'] ?? '',
        'ma_van_ban'          => $vanBan['ma_van_ban'] ?? '',
        'ma_nguoi_xu_ly'      => $nguoi['ma_nguoi_xu_ly'],
        'ma_ho_so'            => $maHoSo,
        'nguon_phan_luong'    => 'thu_cong',
        'do_tin_cay'          => 1,
        'trang_thai'          => 'cho_xu_ly',
        'id_nguoi_phan_luong' => Auth::id(),
        'ghi_chu'             => mb_substr(trim(($cv['ghi_chu'] ? $cv['ghi_chu'] . ' | ' : '')
                                   . 'Phân luồng tay bởi ' . Auth::ten() . ' lúc ' . Util::bayGio('d/m/Y H:i')
                                   . ($ghiChu !== '' ? ': ' . $ghiChu : '')), 0, 4000),
        'ngay_cap_nhat'       => date('Y-m-d H:i:s'),
    ], 'id = ?', [$idCv]);

    Db::capNhat('email', ['trang_thai' => 'da_phan_luong', 'nguon_phan_luong' => 'thu_cong',
                          'ngay_cap_nhat' => date('Y-m-d H:i:s')],
                'id = ? AND trang_thai = "cho_phan_luong"', [$cv['id_email']]);

    // Cập nhật mã cho tệp đính kèm chưa đọc được mã
    Db::chay('UPDATE tep_dinh_kem SET ma_truong = ?, ma_van_ban = ?, ma_nguoi_xu_ly = ?
              WHERE id_email = ? AND doc_duoc_ma = 0',
             [$truong['ma_truong'] ?? '', $vanBan['ma_van_ban'] ?? '', $nguoi['ma_nguoi_xu_ly'], $cv['id_email']]);

    $soTuongTu = 0;
    if ($apDungTuongTu && !empty($cv['id_email'])) {
        $em = Db::mot('SELECT nguoi_gui FROM email WHERE id = ?', [$cv['id_email']]);
        if ($em && $em['nguoi_gui']) {
            $ds = Db::tatCa(
                "SELECT cv.id FROM cong_viec cv JOIN email e ON e.id = cv.id_email
                 WHERE cv.trang_thai = 'cho_phan_luong' AND e.nguoi_gui = ? AND cv.id <> ?",
                [$em['nguoi_gui'], $idCv]);
            foreach ($ds as $x) {
                Db::capNhat('cong_viec', [
                    'id_truong' => $truong['id'] ?? null, 'id_van_ban' => $vanBan['id'] ?? null,
                    'id_nguoi_xu_ly' => $nguoi['id'],
                    'ma_truong' => $truong['ma_truong'] ?? '', 'ma_van_ban' => $vanBan['ma_van_ban'] ?? '',
                    'ma_nguoi_xu_ly' => $nguoi['ma_nguoi_xu_ly'], 'ma_ho_so' => $maHoSo,
                    'nguon_phan_luong' => 'thu_cong', 'trang_thai' => 'cho_xu_ly',
                    'id_nguoi_phan_luong' => Auth::id(), 'ngay_cap_nhat' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$x['id']]);
                $soTuongTu++;
            }
        }
    }

    NhatKy::tin('phan_luong_tay', "Phân luồng tay văn bản #{$idCv} thành {$maHoSo}"
                . ($soTuongTu ? " (áp dụng thêm cho {$soTuongTu} văn bản cùng người gửi)" : ''),
                'cong_viec', (string)$idCv);
    Util::nhan('Đã phân luồng văn bản cho ' . $nguoi['ho_ten'] . '.'
               . ($soTuongTu ? " Đồng thời áp dụng cho {$soTuongTu} văn bản khác cùng người gửi." : ''), 'ok');
    Util::chuyenHuong(Util::url('phan-luong'));
}

// --------------------------- Bỏ qua ---------------------------
if (Util::laPost() && Util::gui('viec') === 'bo_qua') {
    Util::batBuocToken();
    $idCv = (int)Util::gui('id', 0);
    Db::capNhat('cong_viec', ['trang_thai' => 'trung_lap', 'ngay_cap_nhat' => date('Y-m-d H:i:s'),
                              'ghi_chu' => 'Bỏ qua bởi ' . Auth::ten() . ' lúc ' . Util::bayGio('d/m/Y H:i')],
                'id = ?', [$idCv]);
    NhatKy::canhBao('bo_qua', "Bỏ qua văn bản chờ phân luồng #{$idCv}", 'cong_viec', (string)$idCv);
    Util::nhan('Đã bỏ qua văn bản này.', 'ok');
    Util::chuyenHuong(Util::url('phan-luong'));
}

// --------------------------- Dữ liệu hiển thị ---------------------------
$tong = (int)Db::giaTri("SELECT COUNT(*) FROM cong_viec WHERE trang_thai = 'cho_phan_luong'", [], 0);
$moiTrang = Ung::soDongMoiTrang();
$trangSo = max(1, (int)Util::lay('trang', 1));
$boQua = ($trangSo - 1) * $moiTrang;

$ds = Db::tatCa(
    "SELECT cv.*, e.nguoi_gui, e.ten_nguoi_gui, e.ngay_gui, e.doan_trich, e.ghi_chu_ai
     FROM cong_viec cv JOIN email e ON e.id = cv.id_email
     WHERE cv.trang_thai = 'cho_phan_luong'
     ORDER BY cv.ngay_nhan DESC, cv.id DESC LIMIT $moiTrang OFFSET $boQua");

// Văn bản đang mở form
$dangChon = null;
$tepChon = [];
$idChon = (int)Util::lay('id', 0);
if ($idChon) {
    $dangChon = Db::mot(
        'SELECT cv.*, e.nguoi_gui, e.ten_nguoi_gui, e.ngay_gui, e.doan_trich, e.noi_dung_text, e.ghi_chu_ai
         FROM cong_viec cv JOIN email e ON e.id = cv.id_email WHERE cv.id = ?', [$idChon]);
    if ($dangChon) {
        $tepChon = Db::tatCa('SELECT * FROM tep_dinh_kem WHERE id_email = ? ORDER BY thu_tu',
                             [$dangChon['id_email']]);
    }
}

$dsTruong = Db::tatCa('SELECT id, ma_truong, ten_truong FROM truong WHERE trang_thai = 1 ORDER BY thu_tu, ma_truong');
$dsVanBan = Db::tatCa('SELECT id, ma_van_ban, ten_van_ban FROM van_ban WHERE trang_thai = 1 ORDER BY ma_van_ban');
$dsNguoi  = Db::tatCa('SELECT id, ma_nguoi_xu_ly, ho_ten FROM nguoi_xu_ly WHERE trang_thai = 1 ORDER BY ho_ten');

View::$tieuDe = 'Chờ phân luồng tay';
View::hien('phan-luong', compact('ds', 'tong', 'trangSo', 'moiTrang', 'dangChon', 'tepChon',
                                 'dsTruong', 'dsVanBan', 'dsNguoi'));
