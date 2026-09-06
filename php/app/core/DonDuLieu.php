<?php
/**
 * =====================================================================
 *  DonDuLieu.php - Xoá sạch dữ liệu công việc để thử nghiệm lại từ đầu
 *  HỆ THỐNG PHÂN LUỒNG MAIL CÔNG VỤ
 *  Thiết kế bởi Trương Anh Tuấn
 * ---------------------------------------------------------------------
 *  Đây là chức năng PHÁ DỮ LIỆU, nên nguyên tắc là chỉ xoá đúng phần
 *  sinh ra trong quá trình chạy, KHÔNG bao giờ đụng tới:
 *      - danh mục trường, người xử lý, bí danh
 *      - mã văn bản chính thức (chỉ xoá mã do hệ thống TỰ thêm, nếu chọn)
 *      - tài khoản đăng nhập và mật khẩu
 *      - thiết lập trong bảng cau_hinh
 *
 *  Sau khi xoá, bộ nhận mail quét lại Gmail là dữ liệu về từ đầu, vì
 *  việc chống trùng dựa vào gmail_message_id đã lưu trong bảng email.
 * =====================================================================
 */

class DonDuLieu
{
    /**
     * Các bảng dữ liệu công việc, xếp theo THỨ TỰ XOÁ AN TOÀN.
     *
     * Khoá ngoại của lược đồ: email -> tep_dinh_kem, cong_viec (CASCADE);
     * cong_viec -> cong_viec_tep (CASCADE). Riêng tep_dinh_kem -> tep_du_lieu
     * chỉ là SET NULL, nên kho BLOB phải xoá riêng, nếu không sẽ còn lại
     * toàn bộ tệp đính kèm mà không ai tham chiếu tới.
     */
    private const BANG_VIEC = [
        'cong_viec_tep' => 'Liên kết công việc ↔ tệp',
        'cong_viec'     => 'Công việc (văn bản đã phân luồng)',
        'tep_dinh_kem'  => 'Tệp đính kèm',
        'tep_du_lieu'   => 'Kho nội dung tệp (BLOB)',
        'email'         => 'Thư đã nhận',
        'phien_dong_bo' => 'Phiên đồng bộ',
        'tai_len_tam'   => 'Tệp đang tải lên dở',
    ];

    /** Bảng KHÔNG bao giờ bị chức năng này xoá */
    private const BANG_GIU = [
        'truong'              => 'Danh mục trường',
        'nguoi_xu_ly'         => 'Người xử lý (kèm tài khoản đăng nhập)',
        'bi_danh_nguoi_xu_ly' => 'Bí danh người xử lý',
        'cau_hinh'            => 'Thiết lập hệ thống',
    ];

    /** Đếm số bản ghi hiện có, để hiện ra trước khi người dùng bấm xoá */
    public static function demTruoc(): array
    {
        $ra = ['viec' => [], 'giu' => [], 'khac' => []];
        foreach (self::BANG_VIEC as $bang => $nhan)
            $ra['viec'][$bang] = ['nhan' => $nhan, 'so' => self::dem($bang)];
        foreach (self::BANG_GIU as $bang => $nhan)
            $ra['giu'][$bang] = ['nhan' => $nhan, 'so' => self::dem($bang)];

        $ra['khac']['nhat_ky'] = ['nhan' => 'Dòng nhật ký hệ thống', 'so' => self::dem('nhat_ky')];
        $ra['khac']['van_ban_tu_dong'] = [
            'nhan' => 'Mã văn bản do hệ thống tự thêm',
            'so'   => (int)Db::giaTri('SELECT COUNT(*) FROM van_ban WHERE tu_dong_tao = 1', [], 0),
        ];
        $ra['dung_luong_blob'] = (int)Db::giaTri(
            'SELECT COALESCE(SUM(dung_luong), 0) FROM tep_du_lieu', [], 0);
        return $ra;
    }

    private static function dem(string $bang): int
    {
        if (!Db::coBang($bang)) return -1;                // -1 = bảng không tồn tại
        return (int)Db::giaTri('SELECT COUNT(*) FROM `' . $bang . '`', [], 0);
    }

    /**
     * Xoá dữ liệu. Trả về mảng [bảng => số dòng đã xoá].
     *
     * @param bool $nhatKy   xoá luôn nhật ký hệ thống
     * @param bool $maTuDong xoá các mã văn bản do hệ thống tự thêm
     * @param bool $datLaiId đặt lại số đếm ID về 1 cho các bảng đã dọn
     */
    public static function xoa(bool $nhatKy, bool $maTuDong, bool $datLaiId): array
    {
        $daXoa = [];
        Db::batGiaoDich();
        try {
            foreach (array_keys(self::BANG_VIEC) as $bang) {
                if (!Db::coBang($bang)) continue;
                $daXoa[$bang] = Db::chay('DELETE FROM `' . $bang . '`')->rowCount();
            }
            if ($maTuDong) {
                // Xoá mã tự thêm SAU khi đã xoá công việc: lúc này không còn
                // công việc nào trỏ tới, nên không mất liên kết của dữ liệu thật.
                $daXoa['van_ban'] = Db::chay('DELETE FROM van_ban WHERE tu_dong_tao = 1')->rowCount();
            }
            if ($nhatKy && Db::coBang('nhat_ky')) {
                $daXoa['nhat_ky'] = Db::chay('DELETE FROM nhat_ky')->rowCount();
            }
            Db::chotGiaoDich();
        } catch (Throwable $e) {
            Db::huyGiaoDich();
            throw $e;
        }

        // ALTER TABLE là DDL nên MySQL tự chốt giao dịch — phải chạy SAU khi
        // commit, và lỗi ở đây không được làm hỏng kết quả xoá đã thành công.
        if ($datLaiId) {
            $ds = array_keys(self::BANG_VIEC);
            if ($nhatKy) $ds[] = 'nhat_ky';
            foreach ($ds as $bang) {
                if (!Db::coBang($bang)) continue;
                try { Db::chay('ALTER TABLE `' . $bang . '` AUTO_INCREMENT = 1'); }
                catch (Throwable $e) { /* thiếu quyền ALTER thì thôi, không sao */ }
            }
        }
        return $daXoa;
    }
}
