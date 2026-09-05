<?php
/**
 * NhatKy.php - Ghi nhật ký hệ thống
 * Hệ thống phân luồng Mail công vụ - Thiết kế bởi Trương Anh Tuấn
 */

class NhatKy
{
    const MUC = ['debug' => 0, 'info' => 1, 'canh_bao' => 2, 'loi' => 3];
    private static $nguon = 'web';

    public static function datNguon(string $n): void { self::$nguon = $n; }

    public static function ghi(string $muc, string $hanhDong, string $noiDung,
                               string $doiTuong = '', string $idDoiTuong = '', $duLieu = null): void
    {
        try {
            $mucToiThieu = (string)Ung::get('log.muc', 'info');
            if ((self::MUC[$muc] ?? 1) < (self::MUC[$mucToiThieu] ?? 1)) return;

            $nd = null;
            $ten = '';
            if (class_exists('Auth')) {
                $u = Auth::nguoiDung();
                if ($u) { $nd = (int)$u['id']; $ten = (string)$u['ho_ten']; }
            }
            Db::chen('nhat_ky', [
                'thoi_gian'      => date('Y-m-d H:i:s'),
                'muc'            => isset(self::MUC[$muc]) ? $muc : 'info',
                'nguon'          => self::$nguon,
                'hanh_dong'      => mb_substr($hanhDong, 0, 100),
                'doi_tuong'      => mb_substr($doiTuong, 0, 50),
                'id_doi_tuong'   => mb_substr($idDoiTuong, 0, 64),
                'id_nguoi_dung'  => $nd,
                'ten_nguoi_dung' => mb_substr($ten, 0, 150),
                'dia_chi_ip'     => Util::diaChiIp(),
                'may_chu'        => mb_substr((string)($_SERVER['SERVER_NAME'] ?? php_uname('n')), 0, 100),
                'noi_dung'       => mb_substr($noiDung, 0, 60000),
                'du_lieu'        => $duLieu === null ? null
                                     : mb_substr(is_string($duLieu) ? $duLieu
                                         : json_encode($duLieu, JSON_UNESCAPED_UNICODE), 0, 60000),
            ]);
        } catch (Throwable $e) {
            // Không để lỗi ghi nhật ký làm hỏng nghiệp vụ chính
        }
    }

    public static function tin(string $hd, string $nd, string $dt = '', string $idt = ''): void
    { self::ghi('info', $hd, $nd, $dt, $idt); }

    public static function canhBao(string $hd, string $nd, string $dt = '', string $idt = ''): void
    { self::ghi('canh_bao', $hd, $nd, $dt, $idt); }

    public static function loi(string $hd, string $nd, string $dt = '', string $idt = ''): void
    { self::ghi('loi', $hd, $nd, $dt, $idt); }

    /** Dọn nhật ký cũ. Trả về số dòng đã xoá. */
    public static function don(): int
    {
        $ngay = (int)Ung::so('log.so_ngay_giu', 180);
        if ($ngay <= 0) return 0;
        try {
            return Db::chay('DELETE FROM nhat_ky WHERE thoi_gian < DATE_SUB(NOW(), INTERVAL ? DAY)',
                            [$ngay])->rowCount();
        } catch (Throwable $e) { return 0; }
    }
}
