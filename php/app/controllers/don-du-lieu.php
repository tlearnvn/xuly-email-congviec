<?php
/**
 * Điều khiển: Dọn dữ liệu thử nghiệm — xoá sạch dữ liệu công việc trong CSDL.
 *
 * Chỉ quản trị dùng được. Vì là chức năng phá dữ liệu nên phải qua ba cửa:
 * đúng quyền admin, đúng token chống giả mạo biểu mẫu, và phải tự tay gõ đúng
 * chuỗi xác nhận — bấm nhầm một nút thì không xoá được gì.
 */

Auth::batBuocAdmin();

const XAC_NHAN_DON = 'XOA SACH';

$daLam = null;

if (Util::laPost()) {
    if (!Util::kiemTraToken()) {
        Util::nhan('Phiên làm việc đã hết hạn, xin thử lại.', 'loi');
        Util::chuyenHuong(Util::url('don-du-lieu'));
    }

    $goNhap = trim((string)Util::gui('xac_nhan', ''));
    if ($goNhap !== XAC_NHAN_DON) {
        Util::nhan('Chưa xoá gì cả — phải gõ đúng “' . XAC_NHAN_DON . '” vào ô xác nhận.', 'canh');
        Util::chuyenHuong(Util::url('don-du-lieu'));
    }

    $xoaNhatKy   = (string)Util::gui('xoa_nhat_ky', '') !== '';
    $xoaMaTuDong = (string)Util::gui('xoa_ma_tu_dong', '') !== '';
    $datLaiId    = (string)Util::gui('dat_lai_id', '') !== '';

    try {
        $daXoa = DonDuLieu::xoa($xoaNhatKy, $xoaMaTuDong, $datLaiId);
        $tong  = array_sum($daXoa);

        $moTa = [];
        foreach ($daXoa as $bang => $so) if ($so > 0) $moTa[] = $bang . ': ' . Util::so($so);

        // Ghi nhật ký SAU khi xoá, nên dòng này còn lại kể cả khi vừa dọn nhật ký
        NhatKy::canhBao('don_du_lieu',
            'Đã xoá sạch dữ liệu công việc để thử nghiệm — tổng ' . Util::so($tong) . ' dòng'
            . ($moTa ? ' (' . implode(', ', $moTa) . ')' : '')
            . ($xoaNhatKy ? ' | có xoá nhật ký cũ' : '')
            . ($xoaMaTuDong ? ' | có xoá mã văn bản tự thêm' : '')
            . ($datLaiId ? ' | có đặt lại số đếm ID' : ''));

        Util::nhan('Đã xoá sạch ' . Util::so($tong) . ' dòng dữ liệu công việc. '
                 . 'Danh mục, tài khoản và thiết lập vẫn còn nguyên.', 'ok');
    } catch (Throwable $e) {
        NhatKy::loi('don_du_lieu', 'Lỗi khi xoá dữ liệu: ' . $e->getMessage());
        Util::nhan('Không xoá được: ' . $e->getMessage(), 'loi');
    }
    Util::chuyenHuong(Util::url('don-du-lieu'));
}

$dem = DonDuLieu::demTruoc();
// Hiện tên cơ sở dữ liệu đang thao tác, để không dọn nhầm CSDL thật
$tenCsdl = (string)Db::giaTri('SELECT DATABASE()', [], '(không rõ)');

View::$tieuDe = 'Dọn dữ liệu thử nghiệm';
View::hien('don-du-lieu', compact('dem', 'tenCsdl'));
