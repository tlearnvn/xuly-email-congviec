<?php
/**
 * Auth.php - Đăng nhập, phân quyền, phiên làm việc
 * Hệ thống phân luồng Mail công vụ - Thiết kế bởi Trương Anh Tuấn
 */

class Auth
{
    private static $nguoiDung = null;
    const SO_LAN_SAI_TOI_DA = 8;
    const KHOA_PHUT = 15;

    public static function batDauPhien(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        $baoMat = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => Util::goc() . '/',
            'httponly' => true,
            'secure'   => $baoMat,
            'samesite' => 'Lax',
        ]);
        session_name('PHANLUONG_SESS');
        session_start();
    }

    /** Người dùng hiện tại hoặc null */
    public static function nguoiDung(): ?array
    {
        if (self::$nguoiDung !== null) return self::$nguoiDung ?: null;

        $id = $_SESSION['id_nguoi_dung'] ?? 0;
        if (!$id) {
            $nd = self::thuCookieGhiNho();
            if ($nd) { self::$nguoiDung = $nd; return $nd; }
            self::$nguoiDung = false;
            return null;
        }
        $nd = Db::mot('SELECT * FROM nguoi_xu_ly WHERE id = ? AND trang_thai = 1', [$id]);
        self::$nguoiDung = $nd ?: false;
        return $nd ?: null;
    }

    public static function daDangNhap(): bool { return self::nguoiDung() !== null; }

    public static function laAdmin(): bool
    {
        $nd = self::nguoiDung();
        return $nd && $nd['vai_tro'] === 'admin';
    }

    public static function laLanhDao(): bool
    {
        $nd = self::nguoiDung();
        return $nd && in_array($nd['vai_tro'], ['admin', 'lanh_dao'], true);
    }

    /** Xem được mọi văn bản (admin, lãnh đạo, hoặc người được đánh dấu nhận tất cả) */
    public static function xemTatCa(): bool
    {
        $nd = self::nguoiDung();
        return $nd && ($nd['vai_tro'] === 'admin' || $nd['vai_tro'] === 'lanh_dao' || (int)$nd['nhan_tat_ca'] === 1);
    }

    public static function id(): int
    {
        $nd = self::nguoiDung();
        return $nd ? (int)$nd['id'] : 0;
    }

    public static function ten(): string
    {
        $nd = self::nguoiDung();
        return $nd ? (string)$nd['ho_ten'] : '';
    }

    // ------------------------------------------------------------------
    public static function dangNhap(string $tenDangNhap, string $matKhau, bool $ghiNho = false): array
    {
        $nd = Db::mot('SELECT * FROM nguoi_xu_ly WHERE ten_dang_nhap = ? LIMIT 1', [$tenDangNhap]);
        if (!$nd) {
            NhatKy::ghi('canh_bao', 'dang_nhap_that_bai', 'Sai tên đăng nhập: ' . $tenDangNhap);
            return [false, 'Tên đăng nhập hoặc mật khẩu không đúng.'];
        }
        if ((int)$nd['trang_thai'] !== 1) {
            return [false, 'Tài khoản đã bị khoá. Vui lòng liên hệ quản trị viên.'];
        }
        if (!empty($nd['khoa_den']) && strtotime($nd['khoa_den']) > time()) {
            $con = ceil((strtotime($nd['khoa_den']) - time()) / 60);
            return [false, "Tài khoản tạm khoá do đăng nhập sai nhiều lần. Vui lòng thử lại sau {$con} phút."];
        }
        if (!password_verify($matKhau, $nd['mat_khau'])) {
            $sai = (int)$nd['so_lan_that_bai'] + 1;
            $dat = ['so_lan_that_bai' => $sai];
            if ($sai >= self::SO_LAN_SAI_TOI_DA) {
                $dat['khoa_den'] = date('Y-m-d H:i:s', time() + self::KHOA_PHUT * 60);
                $dat['so_lan_that_bai'] = 0;
            }
            Db::capNhat('nguoi_xu_ly', $dat, 'id = ?', [$nd['id']]);
            NhatKy::ghi('canh_bao', 'dang_nhap_that_bai',
                        'Sai mật khẩu tài khoản ' . $tenDangNhap . ' (lần thứ ' . $sai . ')');
            return [false, 'Tên đăng nhập hoặc mật khẩu không đúng.'];
        }

        // Nâng cấp thuật toán băm nếu cần
        if (password_needs_rehash($nd['mat_khau'], PASSWORD_DEFAULT)) {
            Db::capNhat('nguoi_xu_ly', ['mat_khau' => password_hash($matKhau, PASSWORD_DEFAULT)],
                        'id = ?', [$nd['id']]);
        }

        session_regenerate_id(true);
        $_SESSION['id_nguoi_dung'] = (int)$nd['id'];
        self::$nguoiDung = null;

        Db::capNhat('nguoi_xu_ly', [
            'lan_dang_nhap_cuoi' => date('Y-m-d H:i:s'),
            'ip_dang_nhap_cuoi'  => Util::diaChiIp(),
            'so_lan_dang_nhap'   => (int)$nd['so_lan_dang_nhap'] + 1,
            'so_lan_that_bai'    => 0,
            'khoa_den'           => null,
        ], 'id = ?', [$nd['id']]);

        if ($ghiNho) self::taoCookieGhiNho((int)$nd['id']);

        NhatKy::ghi('info', 'dang_nhap', 'Đăng nhập thành công', 'nguoi_xu_ly', (string)$nd['id']);
        return [true, ''];
    }

    public static function dangXuat(): void
    {
        if (self::daDangNhap()) NhatKy::ghi('info', 'dang_xuat', 'Đăng xuất');
        self::xoaCookieGhiNho();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        self::$nguoiDung = null;
    }

    public static function batBuocDangNhap(): void
    {
        if (!self::daDangNhap()) {
            $ke = $_SERVER['REQUEST_URI'] ?? '';
            Util::chuyenHuong(Util::url('dang-nhap', $ke ? ['ke' => $ke] : []));
        }
    }

    public static function batBuocAdmin(): void
    {
        self::batBuocDangNhap();
        if (!self::laAdmin()) {
            http_response_code(403);
            exit('Bạn không có quyền truy cập chức năng này.');
        }
    }

    // ------------------------- Ghi nhớ đăng nhập -------------------------
    private static function taoCookieGhiNho(int $id): void
    {
        $chon = bin2hex(random_bytes(16));
        $bi   = bin2hex(random_bytes(32));
        $hetHan = time() + 30 * 86400;
        Db::chen('phien_ghi_nho', [
            'id_nguoi_xu_ly' => $id,
            'chon'           => $chon,
            'bam'            => hash('sha256', $bi),
            'het_han'        => date('Y-m-d H:i:s', $hetHan),
            'dia_chi_ip'     => Util::diaChiIp(),
            'ngay_tao'       => date('Y-m-d H:i:s'),
        ]);
        setcookie('PHANLUONG_GHINHO', $chon . ':' . $bi, [
            'expires'  => $hetHan,
            'path'     => Util::goc() . '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function thuCookieGhiNho(): ?array
    {
        $c = $_COOKIE['PHANLUONG_GHINHO'] ?? '';
        if (!$c || strpos($c, ':') === false) return null;
        [$chon, $bi] = explode(':', $c, 2);
        try {
            $p = Db::mot('SELECT * FROM phien_ghi_nho WHERE chon = ? AND het_han > NOW()', [$chon]);
        } catch (Throwable $e) { return null; }
        if (!$p || !hash_equals($p['bam'], hash('sha256', $bi))) return null;

        $nd = Db::mot('SELECT * FROM nguoi_xu_ly WHERE id = ? AND trang_thai = 1', [$p['id_nguoi_xu_ly']]);
        if (!$nd) return null;
        $_SESSION['id_nguoi_dung'] = (int)$nd['id'];
        return $nd;
    }

    private static function xoaCookieGhiNho(): void
    {
        $c = $_COOKIE['PHANLUONG_GHINHO'] ?? '';
        if ($c && strpos($c, ':') !== false) {
            [$chon] = explode(':', $c, 2);
            try { Db::xoa('phien_ghi_nho', 'chon = ?', [$chon]); } catch (Throwable $e) {}
        }
        setcookie('PHANLUONG_GHINHO', '', ['expires' => time() - 42000, 'path' => Util::goc() . '/']);
    }
}
