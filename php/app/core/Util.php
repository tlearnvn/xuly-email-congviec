<?php
/**
 * Util.php - Hàm tiện ích dùng chung
 * Hệ thống phân luồng Mail công vụ - Thiết kế bởi Trương Anh Tuấn
 */

class Util
{
    // ------------------------------------------------------------------
    //  Hiển thị an toàn
    // ------------------------------------------------------------------
    public static function h($s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function catChu(?string $s, int $n, string $duoi = '…'): string
    {
        $s = trim((string)$s);
        if (function_exists('mb_strlen')) {
            if (mb_strlen($s, 'UTF-8') <= $n) return $s;
            return rtrim(mb_substr($s, 0, $n, 'UTF-8')) . $duoi;
        }
        return strlen($s) <= $n ? $s : substr($s, 0, $n) . $duoi;
    }

    /** Bỏ dấu tiếng Việt */
    public static function boDau(?string $s): string
    {
        $s = (string)$s;
        $bang = [
            'a' => 'áàảãạăắằẳẵặâấầẩẫậ', 'A' => 'ÁÀẢÃẠĂẮẰẲẴẶÂẤẦẨẪẬ',
            'e' => 'éèẻẽẹêếềểễệ',       'E' => 'ÉÈẺẼẸÊẾỀỂỄỆ',
            'i' => 'íìỉĩị',             'I' => 'ÍÌỈĨỊ',
            'o' => 'óòỏõọôốồổỗộơớờởỡợ', 'O' => 'ÓÒỎÕỌÔỐỒỔỖỘƠỚỜỞỠỢ',
            'u' => 'úùủũụưứừửữự',       'U' => 'ÚÙỦŨỤƯỨỪỬỮỰ',
            'y' => 'ýỳỷỹỵ',             'Y' => 'ÝỲỶỸỴ',
            'd' => 'đ',                 'D' => 'Đ',
        ];
        foreach ($bang as $ascii => $dau) {
            $ky = preg_split('//u', $dau, -1, PREG_SPLIT_NO_EMPTY);
            $s = str_replace($ky, $ascii, $s);
        }
        return $s;
    }

    /** Chuẩn hoá mã: bỏ dấu, in hoa, bỏ ký tự lạ */
    public static function chuanHoaMa(?string $s): string
    {
        $s = strtoupper(self::boDau((string)$s));
        return preg_replace('/[^A-Z0-9]/', '', $s) ?? '';
    }

    /** Chuẩn hoá mã số: bỏ số 0 ở đầu nếu toàn chữ số */
    public static function chuanHoaMaSo(?string $s): string
    {
        $m = self::chuanHoaMa($s);
        if ($m !== '' && ctype_digit($m)) return ltrim($m, '0') === '' ? '0' : ltrim($m, '0');
        return $m;
    }

    // ------------------------------------------------------------------
    //  Thời gian (luôn theo giờ Việt Nam)
    // ------------------------------------------------------------------
    public static function bayGio(string $dinhDang = 'Y-m-d H:i:s'): string
    {
        return date($dinhDang);
    }

    public static function ngay(?string $s, string $dinhDang = 'd/m/Y H:i'): string
    {
        if (!$s || $s === '0000-00-00 00:00:00') return '';
        $t = strtotime($s);
        return $t ? date($dinhDang, $t) : '';
    }

    public static function ngayNgan(?string $s): string
    {
        return self::ngay($s, 'd/m/Y');
    }

    /** "3 phút trước", "hôm qua"… */
    public static function tuongDoi(?string $s): string
    {
        if (!$s) return '';
        $t = strtotime($s);
        if (!$t) return '';
        $d = time() - $t;
        if ($d < 0) return self::ngay($s);
        if ($d < 60) return 'vừa xong';
        if ($d < 3600) return floor($d / 60) . ' phút trước';
        if ($d < 86400) return floor($d / 3600) . ' giờ trước';
        if ($d < 172800) return 'hôm qua';
        if ($d < 2592000) return floor($d / 86400) . ' ngày trước';
        return self::ngay($s, 'd/m/Y');
    }

    // ------------------------------------------------------------------
    //  Định dạng
    // ------------------------------------------------------------------
    public static function dungLuong($byte): string
    {
        $byte = (float)$byte;
        $dv = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($byte >= 1024 && $i < 4) { $byte /= 1024; $i++; }
        if ($i === 0) return number_format($byte, 0, ',', '.') . ' B';
        return number_format($byte, 1, ',', '.') . ' ' . $dv[$i];
    }

    public static function so($n, int $thapPhan = 0): string
    {
        return number_format((float)$n, $thapPhan, ',', '.');
    }

    public static function phanTram($tu, $mau, int $thapPhan = 1): string
    {
        if (!$mau) return '0%';
        return number_format($tu * 100 / $mau, $thapPhan, ',', '.') . '%';
    }

    // ------------------------------------------------------------------
    //  Yêu cầu HTTP
    // ------------------------------------------------------------------
    public static function lay(string $khoa, $macDinh = '')
    {
        return isset($_GET[$khoa]) ? (is_array($_GET[$khoa]) ? $_GET[$khoa] : trim((string)$_GET[$khoa])) : $macDinh;
    }

    public static function gui(string $khoa, $macDinh = '')
    {
        return isset($_POST[$khoa]) ? (is_array($_POST[$khoa]) ? $_POST[$khoa] : trim((string)$_POST[$khoa])) : $macDinh;
    }

    public static function laPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    public static function diaChiIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = explode(',', $_SERVER[$k])[0];
                return substr(trim($ip), 0, 45);
            }
        }
        return '';
    }

    /** Đường dẫn gốc của ứng dụng (hỗ trợ đặt trong thư mục con) */
    public static function goc(): string
    {
        static $g = null;
        if ($g !== null) return $g;
        $sn = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $g = rtrim(str_replace('\\', '/', dirname($sn)), '/');
        if ($g === '.' || $g === '/') $g = '';
        return $g;
    }

    public static function url(string $trang = '', array $ts = []): string
    {
        $u = self::goc() . '/index.php';
        if ($trang !== '') $ts = array_merge(['t' => $trang], $ts);
        if ($ts) $u .= '?' . http_build_query($ts);
        return $u;
    }

    public static function chuyenHuong(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    /** Giữ nguyên tham số hiện tại, ghi đè vài tham số */
    public static function urlGiu(array $ghiDe = []): string
    {
        $ts = array_merge($_GET, $ghiDe);
        foreach ($ts as $k => $v) if ($v === '' || $v === null) unset($ts[$k]);
        return self::goc() . '/index.php' . ($ts ? '?' . http_build_query($ts) : '');
    }

    // ------------------------------------------------------------------
    //  CSRF + thông báo nhanh
    // ------------------------------------------------------------------
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
        return $_SESSION['csrf'];
    }

    public static function kiemTraToken(): bool
    {
        $t = $_POST['csrf'] ?? $_GET['csrf'] ?? '';
        return !empty($_SESSION['csrf']) && is_string($t) && hash_equals($_SESSION['csrf'], $t);
    }

    public static function batBuocToken(): void
    {
        if (!self::kiemTraToken()) {
            http_response_code(400);
            exit('Phiên làm việc đã hết hạn hoặc yêu cầu không hợp lệ. Vui lòng tải lại trang.');
        }
    }

    public static function nhan(string $noiDung, string $loai = 'ok'): void
    {
        $_SESSION['nhanh'][] = ['noi_dung' => $noiDung, 'loai' => $loai];
    }

    public static function layNhan(): array
    {
        $n = $_SESSION['nhanh'] ?? [];
        unset($_SESSION['nhanh']);
        return $n;
    }

    // ------------------------------------------------------------------
    //  Khác
    // ------------------------------------------------------------------
    public static function json($duLieu, int $ma = 200): void
    {
        http_response_code($ma);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($duLieu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function chuoiNgauNhien(int $soByte = 16): string
    {
        return bin2hex(random_bytes($soByte));
    }

    /** Biểu tượng theo phần mở rộng tệp */
    public static function bieuTuongTep(?string $tenTep): string
    {
        $e = strtolower(pathinfo((string)$tenTep, PATHINFO_EXTENSION));
        $bang = [
            'pdf' => 'pdf', 'doc' => 'doc', 'docx' => 'doc', 'odt' => 'doc',
            'xls' => 'xls', 'xlsx' => 'xls', 'csv' => 'xls', 'ods' => 'xls',
            'ppt' => 'ppt', 'pptx' => 'ppt',
            'jpg' => 'anh', 'jpeg' => 'anh', 'png' => 'anh', 'gif' => 'anh', 'webp' => 'anh', 'bmp' => 'anh',
            'zip' => 'nen', 'rar' => 'nen', '7z' => 'nen', 'tar' => 'nen', 'gz' => 'nen',
        ];
        return $bang[$e] ?? 'khac';
    }

    /**
     * Tách cột email.lien_ket_ngoai thành mảng ['url' => …, 'ten' => …].
     *
     * Mỗi dòng là một link; có tên tệp thì nằm sau dấu TAB (tên tệp Gmail hiện
     * trong "Drive chip" khi tệp vượt 25 MB). Dòng chỉ có link — dữ liệu lưu từ
     * bản 1.4.x — vẫn đọc được bình thường, tên để rỗng.
     *
     * Lọc lại lần nữa dù bên nhận đã lọc: dòng cũ có từ trước khi có bộ lọc,
     * hoặc ai đó sửa tay bằng phpMyAdmin, vẫn không được thành thẻ <a> nguy hiểm.
     */
    public static function dsLinkChiaSe(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') return [];
        $ra = [];
        $daCo = [];
        foreach (preg_split('/[\r\n]+/', $raw) ?: [] as $dong) {
            $p = explode("\t", $dong, 2);
            $u = trim($p[0]);
            if ($u === '' || strlen($u) > 900) continue;
            if (!preg_match('~^https?://~i', $u)) continue;
            if (preg_match('/[\x00-\x20\x7F]/', $u)) continue;
            if (isset($daCo[$u])) continue;
            $daCo[$u] = true;
            $ra[] = ['url' => $u, 'ten' => mb_substr(trim($p[1] ?? ''), 0, 300)];
            if (count($ra) >= 50) break;
        }
        return $ra;
    }

    /** Nhãn ngắn cho một link chia sẻ: ưu tiên tên tệp, không có thì lấy tên miền */
    public static function nhanLinkChiaSe(array $lk): string
    {
        return $lk['ten'] !== '' ? $lk['ten'] : self::mienCuaLink($lk['url']);
    }

    /** Tên miền của một URL, dùng làm nhãn ngắn cho link chia sẻ */
    public static function mienCuaLink(string $url): string
    {
        $h = parse_url($url, PHP_URL_HOST);
        return $h ? strtolower($h) : $url;
    }

    /** Có thể xem trực tiếp trên trình duyệt hay không */
    public static function xemTrucTiep(?string $mime, ?string $tenTep): bool
    {
        $mime = strtolower((string)$mime);
        if (strpos($mime, 'application/pdf') === 0) return true;
        if (strpos($mime, 'image/') === 0) return true;
        if (strpos($mime, 'text/plain') === 0) return true;
        $e = strtolower(pathinfo((string)$tenTep, PATHINFO_EXTENSION));
        return in_array($e, ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'txt'], true);
    }

    public static function tenTrangThai(string $tt): string
    {
        $b = [
            'cho_phan_luong' => 'Chờ phân luồng',
            'cho_xu_ly'      => 'Chờ xử lý',
            'dang_xu_ly'     => 'Đang xử lý',
            'da_xu_ly'       => 'Đã xử lý',
            'tu_choi'        => 'Từ chối',
            'trung_lap'      => 'Trùng lặp',
            'moi'            => 'Mới',
            'da_phan_luong'  => 'Đã phân luồng',
            'ban_moi'        => 'Bản cập nhật',
            'loi'            => 'Lỗi',
        ];
        return $b[$tt] ?? $tt;
    }

    public static function tenNguon(string $n): string
    {
        $b = [
            'ten_tep'         => 'Tên tệp đính kèm',
            'tieu_de'         => 'Tiêu đề thư',
            'ai'              => 'Trợ lý AI',
            'thu_cong'        => 'Phân luồng tay',
            'mac_dinh'        => 'Mặc định',
            'nguoi_gui'       => 'Địa chỉ người gửi',
            'khong_xac_dinh'  => 'Không xác định',
        ];
        return $b[$n] ?? $n;
    }

    public static function tenMucNhatKy(string $m): string
    {
        $b = ['debug' => 'Gỡ lỗi', 'info' => 'Thông tin', 'canh_bao' => 'Cảnh báo', 'loi' => 'Lỗi'];
        return $b[$m] ?? $m;
    }

    public static function tenKyBaoCao(string $k): string
    {
        $b = [
            'khong' => 'Không định kỳ', 'ngay' => 'Hàng ngày', 'tuan' => 'Hàng tuần',
            'thang' => 'Hàng tháng', 'quy' => 'Hàng quý', 'hoc_ky' => 'Theo học kỳ',
            'nam' => 'Hàng năm', 'dot' => 'Theo đợt',
        ];
        return $b[$k] ?? $k;
    }
}
