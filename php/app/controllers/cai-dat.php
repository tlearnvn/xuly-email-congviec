<?php
/** Điều khiển: Cài đặt hệ thống */

Auth::batBuocAdmin();

// --------------------------- Kiểm tra AI (AJAX) ---------------------------
if (Util::laPost() && Util::gui('viec_ajax') === 'test_ai') {
    if (!Util::kiemTraToken()) Util::json(['ok' => false, 'loi' => 'Phiên làm việc hết hạn.'], 400);
    [$ok, $tb] = Ai::kiemTra([
        'url'         => Util::gui('ai_url') ?: null,
        'api_key'     => Util::gui('ai_api_key') ?: Ung::get('ai.api_key'),
        'model'       => Util::gui('ai_model') ?: null,
        'timeout'     => (int)Util::gui('ai_timeout', 30),
    ]);
    NhatKy::tin('kiem_tra_ai', $ok ? 'Kiểm tra AI thành công' : ('Kiểm tra AI thất bại: ' . $tb));
    Util::json(['ok' => $ok, 'thong_diep' => $tb, 'loi' => $ok ? '' : $tb]);
}

// --------------------------- Lưu cài đặt ---------------------------
if (Util::laPost()) {
    Util::batBuocToken();
    $viec = Util::gui('viec');

    if ($viec === 'sinh_khoa_api') {
        $khoa = Util::chuoiNgauNhien(24);
        Ung::dat('api.khoa', $khoa);
        NhatKy::canhBao('doi_khoa_api', 'Sinh lại khoá API cho bộ nhận mail');
        Util::nhan('Đã sinh khoá API mới. Hãy cập nhật lại trong bộ nhận mail.', 'ok');
        Util::chuyenHuong(Util::url('cai-dat'));
    }

    // Ràng buộc giá trị
    $gioiHan = [
        'ai.max_tokens'     => [1, Ai::MAX_TOKENS_TRAN],
        'ai.timeout'        => [1, Ai::TIMEOUT_TRAN],
        'ai.temperature'    => [0, 2],
        'ai.nguong_tin_cay' => [0, 1],
        'app.so_dong_moi_trang' => [5, 200],
        'log.so_ngay_giu'   => [0, 3650],
        'trung.so_ngay_doi_chieu' => [1, 3650],
        'api.kich_thuoc_khoi_kb'  => [32, 8192],
        'api.dung_luong_toi_da_mb' => [1, 100],
        'phanluong.han_xu_ly_ngay' => [0, 365],
    ];

    $choPhep = array_keys(Ung::MAC_DINH);
    $choPhep[] = 'ai.nhac_he_thong';
    $choPhep[] = 'gmail.hop_thu';
    $choPhep[] = 'gmail.truy_van';
    $choPhep[] = 'gmail.so_mail_moi_lan';
    $choPhep[] = 'gmail.chu_ky_phut';
    $choPhep[] = 'app.logo';
    $choPhep[] = 'phanluong.dau_phan_cach';
    $choPhep = array_unique($choPhep);

    $daLuu = 0;
    foreach ((array)($_POST['ch'] ?? []) as $khoa => $gt) {
        if (!in_array($khoa, $choPhep, true)) continue;
        $gt = is_array($gt) ? implode(',', $gt) : trim((string)$gt);
        // Không ghi đè mật khẩu/khoá bằng chuỗi rỗng
        if ($gt === '' && in_array($khoa, ['ai.api_key', 'api.khoa'], true)) continue;
        if (isset($gioiHan[$khoa])) {
            $gt = max($gioiHan[$khoa][0], min($gioiHan[$khoa][1], (float)$gt));
            $gt = (string)(($gt == (int)$gt) ? (int)$gt : $gt);
        }
        Ung::dat($khoa, $gt);
        $daLuu++;
    }
    // Các ô đánh dấu không tích sẽ không xuất hiện trong $_POST
    foreach ((array)($_POST['co_bool'] ?? []) as $khoa) {
        if (!in_array($khoa, $choPhep, true)) continue;
        if (!isset($_POST['ch'][$khoa])) { Ung::dat($khoa, '0'); $daLuu++; }
    }

    NhatKy::tin('cap_nhat_cai_dat', "Cập nhật {$daLuu} thiết lập hệ thống");
    Util::nhan("Đã lưu {$daLuu} thiết lập.", 'ok');
    Util::chuyenHuong(Util::url('cai-dat', ['nhom' => Util::gui('nhom', 'giao_dien')]));
}

$nhom = (string)Util::lay('nhom', 'giao_dien');
$hopLe = ['giao_dien', 'phan_luong', 'chong_trung', 'ai', 'api', 'nhat_ky'];
if (!in_array($nhom, $hopLe, true)) $nhom = 'giao_dien';

$ch = Ung::tatCa();

// Thông tin hệ thống
$thongTin = [
    'Phiên bản hệ thống' => PHIEN_BAN_HE_THONG . ' (build ' . SO_BUILD_HE_THONG . ')',
    'Ngày đóng gói'      => NGAY_BUILD_HE_THONG ?: '—',
    'Phiên bản PHP'      => PHP_VERSION,
    'Máy chủ web'        => $_SERVER['SERVER_SOFTWARE'] ?? '—',
    'Múi giờ hệ thống'   => date_default_timezone_get() . ' (' . date('P') . ')',
    'Giờ hiện tại'       => Util::bayGio('d/m/Y H:i:s'),
    'Phiên bản MySQL'    => Db::giaTri('SELECT VERSION()', [], '—'),
    'Giới hạn tải lên'   => ini_get('upload_max_filesize') . ' / post_max_size ' . ini_get('post_max_size'),
    'Bộ nhớ tối đa PHP'  => ini_get('memory_limit'),
    'Phần mở rộng cURL'  => function_exists('curl_init') ? 'Có' : 'Không có (AI sẽ không hoạt động)',
];

$soLieu = [
    'Email đã lưu'   => (int)Db::giaTri('SELECT COUNT(*) FROM email', [], 0),
    'Công việc'      => (int)Db::giaTri('SELECT COUNT(*) FROM cong_viec', [], 0),
    'Tệp đính kèm'   => (int)Db::giaTri('SELECT COUNT(*) FROM tep_dinh_kem', [], 0),
    'Tệp lưu thực tế'=> (int)Db::giaTri('SELECT COUNT(*) FROM tep_du_lieu WHERE da_hoan_tat = 1', [], 0),
    'Dung lượng kho' => Util::dungLuong((int)Db::giaTri('SELECT IFNULL(SUM(dung_luong),0) FROM tep_du_lieu', [], 0)),
    'Dòng nhật ký'   => (int)Db::giaTri('SELECT COUNT(*) FROM nhat_ky', [], 0),
];

View::$tieuDe = 'Cài đặt hệ thống';
View::hien('cai-dat', compact('ch', 'nhom', 'thongTin', 'soLieu'));
