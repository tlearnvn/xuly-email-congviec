<?php
/**
 * =====================================================================
 *  api/ingest.php - Cổng tiếp nhận dữ liệu từ bộ nhận mail (MailRouter)
 *  HỆ THỐNG PHÂN LUỒNG MAIL CÔNG VỤ
 *  Thiết kế bởi Trương Anh Tuấn
 * ---------------------------------------------------------------------
 *  Giao thức: POST JSON, xác thực bằng tiêu đề X-API-Key.
 *  Mọi phản hồi đều là JSON dạng {"ok":true|false, ...}
 * =====================================================================
 */

require dirname(__DIR__) . '/app/khoi_dong.php';

NhatKy::datNguon('mailrouter');
header('Content-Type: application/json; charset=utf-8');

function traLoi(array $d, int $ma = 200): void
{
    http_response_code($ma);
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function traLoiSai(string $thongDiep, int $ma = 400): void
{
    traLoi(['ok' => false, 'loi' => $thongDiep], $ma);
}

// --------------------------- Chỉ nhận POST ---------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    traLoi([
        'ok' => true,
        'thong_diep' => 'Cổng tiếp nhận đang hoạt động. Hãy gửi yêu cầu bằng phương thức POST.',
        'phien_ban'  => PHIEN_BAN_HE_THONG,
    ]);
}

// --------------------------- Bật/tắt ---------------------------
if (!Ung::bat('api.bat', true)) {
    traLoiSai('Cổng tiếp nhận API đang bị tắt trong phần Cài đặt của hệ thống.', 403);
}

// --------------------------- Xác thực ---------------------------
$khoaCauHinh = (string)Ung::get('api.khoa', '');
$khoaGui = $_SERVER['HTTP_X_API_KEY'] ?? ($_SERVER['HTTP_X_APIKEY'] ?? '');
if ($khoaGui === '' && function_exists('apache_request_headers')) {
    foreach (apache_request_headers() as $k => $v) {
        if (strcasecmp($k, 'X-API-Key') === 0) { $khoaGui = $v; break; }
    }
}
if ($khoaCauHinh === '' || $khoaCauHinh === 'DOI-KHOA-NAY-NGAY-LAP-TUC') {
    traLoiSai('Máy chủ chưa đặt khoá API. Vào trang Cài đặt → Kết nối bộ nhận mail để sinh khoá.', 403);
}
if (!is_string($khoaGui) || !hash_equals($khoaCauHinh, trim($khoaGui))) {
    NhatKy::canhBao('api_tu_choi', 'Từ chối yêu cầu API do sai khoá (IP ' . Util::diaChiIp() . ')');
    traLoiSai('Khoá API không hợp lệ.', 401);
}

// --------------------------- Đọc dữ liệu ---------------------------
$tho = file_get_contents('php://input');
if ($tho === false || $tho === '') traLoiSai('Không nhận được dữ liệu.');
$yc = json_decode($tho, true);
if (!is_array($yc)) traLoiSai('Dữ liệu gửi lên không phải JSON hợp lệ.');

$hanhDong = (string)($yc['hanh_dong'] ?? '');
$mayChu   = mb_substr((string)($yc['may_chu'] ?? ''), 0, 100);

try {
    switch ($hanhDong) {

        // ------------------------------------------------------------
        case 'kiem_tra':
            traLoi([
                'ok'        => true,
                'phien_ban' => PHIEN_BAN_HE_THONG . ' (build ' . SO_BUILD_HE_THONG . ')',
                'ten_ung_dung' => Ung::tenUngDung(),
                'thoi_gian' => Util::bayGio(),
                'mui_gio'   => date_default_timezone_get(),
                'gioi_han_tep_mb'  => (int)Ung::so('api.dung_luong_toi_da_mb', 25),
                'kich_thuoc_khoi_kb' => (int)Ung::so('api.kich_thuoc_khoi_kb', 512),
            ]);
            break;

        // ------------------------------------------------------------
        case 'danh_muc':
            traLoi(array_merge(['ok' => true], LuuMail::danhMuc()));
            break;

        // ------------------------------------------------------------
        case 'cau_hinh':
            $ch = Ung::tatCa();
            unset($ch['api.khoa'], $ch['ai.api_key']);   // không trả về bí mật
            traLoi(['ok' => true, 'cau_hinh' => $ch]);
            break;

        // ------------------------------------------------------------
        case 'them_van_ban':
            $ma = trim((string)($yc['ma_van_ban'] ?? ''));
            if ($ma === '') traLoiSai('Thiếu mã văn bản.');
            traLoi(['ok' => true, 'van_ban' => LuuMail::taoVanBanTuDong($ma)]);
            break;

        // ------------------------------------------------------------
        case 'kiem_tra_mail':
            $gid = trim((string)($yc['gmail_id'] ?? ''));
            if ($gid === '') traLoiSai('Thiếu mã thư Gmail.');
            traLoi(['ok' => true, 'id_email' => LuuMail::daLuu($gid)]);
            break;

        // ------------------------------------------------------------
        case 'tep_bat_dau':
            $r = LuuMail::tepBatDau(
                (string)($yc['hash'] ?? ''),
                (int)($yc['dung_luong'] ?? 0),
                (string)($yc['kieu_mime'] ?? ''),
                (string)($yc['ten_tep'] ?? '')
            );
            traLoi(array_merge(['ok' => true], $r));
            break;

        case 'tep_khoi':
            $daNhan = LuuMail::tepKhoi((string)($yc['id_tai'] ?? ''), (string)($yc['du_lieu'] ?? ''));
            traLoi(['ok' => true, 'da_nhan' => $daNhan]);
            break;

        case 'tep_ket_thuc':
            $r = LuuMail::tepKetThuc((string)($yc['id_tai'] ?? ''));
            traLoi(array_merge(['ok' => true], $r));
            break;

        // ------------------------------------------------------------
        case 'luu_email':
            $em = [
                'gmail_id'          => (string)($yc['gmail_id'] ?? ''),
                'thread_id'         => (string)($yc['thread_id'] ?? ''),
                'message_id_header' => (string)($yc['message_id_header'] ?? ''),
                'hop_thu'           => (string)($yc['hop_thu'] ?? ''),
                'tieu_de'           => (string)($yc['tieu_de'] ?? ''),
                'tieu_de_chuan'     => (string)($yc['tieu_de_chuan'] ?? ''),
                'nguoi_gui'         => (string)($yc['nguoi_gui'] ?? ''),
                'ten_nguoi_gui'     => (string)($yc['ten_nguoi_gui'] ?? ''),
                'nguoi_nhan'        => (string)($yc['nguoi_nhan'] ?? ''),
                'ngay_gui'          => (string)($yc['ngay_gui'] ?? ''),
                'doan_trich'        => (string)($yc['doan_trich'] ?? ''),
                'noi_dung_text'     => (string)($yc['noi_dung_text'] ?? ''),
                'noi_dung_html'     => (string)($yc['noi_dung_html'] ?? ''),
                'hash_noi_dung'     => (string)($yc['hash_noi_dung'] ?? ''),
                'hash_tep'          => (string)($yc['hash_tep'] ?? ''),
                'hash_tong_hop'     => (string)($yc['hash_tong_hop'] ?? ''),
                'nguon_phan_luong'  => (string)($yc['nguon_phan_luong'] ?? ''),
                'do_tin_cay'        => (float)($yc['do_tin_cay'] ?? 0),
                'ghi_chu_ai'        => (string)($yc['ghi_chu_ai'] ?? ''),
            ];
            $tep = is_array($yc['tep'] ?? null) ? $yc['tep'] : [];
            $cv  = is_array($yc['cong_viec'] ?? null) ? $yc['cong_viec'] : [];

            $kq = LuuMail::luu($em, $tep, $cv);

            NhatKy::tin('nhan_mail',
                'Tiếp nhận thư “' . Util::catChu($em['tieu_de'], 120) . '” từ ' . $em['nguoi_gui']
                . ' — kết quả: ' . $kq['trang']
                . ($kq['thong_diep'] ? ' (' . $kq['thong_diep'] . ')' : ''),
                'email', (string)$kq['id_email']);

            traLoi(array_merge(['ok' => true], $kq));
            break;

        // ------------------------------------------------------------
        case 'nhat_ky':
            $muc = (string)($yc['muc'] ?? 'info');
            NhatKy::ghi(
                in_array($muc, ['debug', 'info', 'canh_bao', 'loi'], true) ? $muc : 'info',
                (string)($yc['hanh_dong_log'] ?? ($yc['hanh_dong_nk'] ?? 'mailrouter')),
                (string)($yc['noi_dung'] ?? ''),
                (string)($yc['doi_tuong'] ?? ''),
                (string)($yc['id_doi_tuong'] ?? '')
            );
            traLoi(['ok' => true]);
            break;

        // ------------------------------------------------------------
        case 'phien_mo':
            $id = LuuMail::moPhien(
                (string)($yc['hop_thu'] ?? ''),
                (string)($yc['truy_van'] ?? ''),
                $mayChu
            );
            traLoi(['ok' => true, 'id' => $id]);
            break;

        case 'phien_dong':
            LuuMail::dongPhien((int)($yc['id'] ?? 0), $yc, (string)($yc['trang_thai'] ?? 'hoan_tat'));
            traLoi(['ok' => true]);
            break;

        // ------------------------------------------------------------
        default:
            traLoiSai('Hành động không được hỗ trợ: ' . $hanhDong, 400);
    }
} catch (InvalidArgumentException $e) {
    traLoiSai($e->getMessage(), 400);
} catch (Throwable $e) {
    NhatKy::loi('api_loi', 'Lỗi xử lý API (' . $hanhDong . '): ' . $e->getMessage());
    traLoiSai('Lỗi máy chủ: ' . $e->getMessage(), 500);
}
