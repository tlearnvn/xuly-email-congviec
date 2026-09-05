<?php
/**
 * Ung.php - Cấu hình ứng dụng lưu trong bảng cau_hinh
 * Hệ thống phân luồng Mail công vụ - Thiết kế bởi Trương Anh Tuấn
 */

class Ung
{
    private static $ch = null;

    const MAC_DINH = [
        'app.ten_ung_dung'   => 'Hệ thống phân luồng Mail công vụ - Phòng GDPT-GDTX SGDĐT Đồng Nai',
        'app.ten_ngan'       => 'Phân luồng Mail công vụ',
        'app.don_vi'         => 'Phòng GDPT-GDTX - Sở GD&ĐT Đồng Nai',
        'app.ban_quyen'      => 'Thiết kế bởi Trương Anh Tuấn',
        'app.mau_chu_dao'    => '#1e5eff',
        'app.logo'           => '',
        'app.so_dong_moi_trang' => '20',
        'app.mui_gio'        => 'Asia/Ho_Chi_Minh',
        'api.bat'            => '1',
        'api.khoa'           => '',
        'api.kich_thuoc_khoi_kb' => '512',
        'api.dung_luong_toi_da_mb' => '25',
        'log.muc'            => 'info',
        'log.so_ngay_giu'    => '180',
        'ai.bat'             => '0',
        'ai.url'             => 'https://api.openai.com/v1',
        'ai.api_key'         => '',
        'ai.model'           => 'gpt-4o-mini',
        'ai.max_tokens'      => '4096',
        'ai.timeout'         => '60',
        'ai.temperature'     => '0.1',
        'ai.nguong_tin_cay'  => '0.6',
        'trung.bat'          => '1',
        'trung.tao_ban_moi_khi_tep_khac' => '1',
        'trung.bo_qua_tien_to' => 'RE:,FW:,FWD:,TRA LOI:,CHUYEN TIEP:',
        'trung.so_ngay_doi_chieu' => '365',
        'phanluong.cho_phep_ma_vb_moi' => '1',
        'phanluong.bat_buoc_ma_truong' => '1',
        'phanluong.bat_buoc_ma_nguoi'  => '1',
        'phanluong.nguoi_xu_ly_mac_dinh' => '',
        'phanluong.han_xu_ly_ngay' => '7',
    ];

    /** Nạp toàn bộ cấu hình từ CSDL (một lần cho mỗi lượt truy cập) */
    public static function nap(): void
    {
        if (self::$ch !== null) return;
        self::$ch = self::MAC_DINH;
        try {
            foreach (Db::capKhoa('SELECT khoa, gia_tri FROM cau_hinh') as $k => $v) {
                if ($v !== null && $v !== '') self::$ch[$k] = $v;
                elseif (!isset(self::$ch[$k])) self::$ch[$k] = '';
            }
        } catch (Throwable $e) {
            // CSDL chưa sẵn sàng - dùng giá trị mặc định
        }
    }

    public static function get(string $khoa, $macDinh = '')
    {
        self::nap();
        return self::$ch[$khoa] ?? $macDinh;
    }

    public static function so(string $khoa, $macDinh = 0)
    {
        $v = self::get($khoa, null);
        return ($v === null || $v === '') ? $macDinh : (0 + $v);
    }

    public static function bat(string $khoa, bool $macDinh = false): bool
    {
        $v = self::get($khoa, null);
        if ($v === null || $v === '') return $macDinh;
        return in_array(strtolower((string)$v), ['1', 'true', 'yes', 'on', 'co'], true);
    }

    public static function tatCa(): array
    {
        self::nap();
        return self::$ch;
    }

    public static function dat(string $khoa, $giaTri): void
    {
        self::nap();
        $co = Db::giaTri('SELECT COUNT(*) FROM cau_hinh WHERE khoa = ?', [$khoa], 0);
        if ($co) {
            Db::chay('UPDATE cau_hinh SET gia_tri = ?, ngay_cap_nhat = NOW() WHERE khoa = ?',
                     [(string)$giaTri, $khoa]);
        } else {
            Db::chay('INSERT INTO cau_hinh (khoa, gia_tri, nhom, kieu, ngay_cap_nhat) VALUES (?,?,?,?,NOW())',
                     [$khoa, (string)$giaTri, explode('.', $khoa)[0], 'text']);
        }
        self::$ch[$khoa] = (string)$giaTri;
    }

    // ------------------- Thương hiệu -------------------
    public static function tenUngDung(): string { return (string)self::get('app.ten_ung_dung'); }
    public static function tenNgan(): string    { return (string)self::get('app.ten_ngan'); }
    public static function donVi(): string      { return (string)self::get('app.don_vi'); }
    public static function banQuyen(): string   { return (string)self::get('app.ban_quyen'); }
    public static function mau(): string
    {
        $m = trim((string)self::get('app.mau_chu_dao', '#1e5eff'));
        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $m) ? $m : '#1e5eff';
    }
    public static function soDongMoiTrang(): int
    {
        $n = (int)self::so('app.so_dong_moi_trang', 20);
        return max(5, min(200, $n));
    }
}
