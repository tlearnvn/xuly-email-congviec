<?php
/**
 * =====================================================================
 *  HỆ THỐNG PHÂN LUỒNG MAIL CÔNG VỤ
 *  Thiết kế bởi Trương Anh Tuấn
 * ---------------------------------------------------------------------
 *  Sao chép tệp này thành "cau-hinh.php" rồi điền thông tin kết nối
 *  cơ sở dữ liệu MySQL do cPanel cấp.
 *  (Chạy trang cai-dat.php sẽ tự tạo tệp này giúp bạn.)
 * =====================================================================
 */

return [
    // ------------------ Kết nối cơ sở dữ liệu ------------------
    'may_chu'       => 'localhost',
    'cong'          => 3306,
    'co_so_du_lieu' => 'taikhoan_phanluong',
    'nguoi_dung'    => 'taikhoan_mail',
    'mat_khau'      => '',
    'bang_ma'       => 'utf8mb4',

    // ------------------ Thiết lập chung ------------------
    // Múi giờ hiển thị (luôn dùng giờ Việt Nam)
    'mui_gio'       => 'Asia/Ho_Chi_Minh',

    // Hiện chi tiết lỗi ra màn hình (chỉ bật khi đang thử nghiệm)
    'go_loi'        => false,

    // Thư mục lưu tệp tạm khi tải lên theo khối (để trống = dùng CSDL)
    'thu_muc_tam'   => '',

    // Khoá bí mật dùng cho cookie ghi nhớ đăng nhập (đổi thành chuỗi ngẫu nhiên)
    'khoa_bi_mat'   => 'DOI-CHUOI-NAY-THANH-CHUOI-NGAU-NHIEN-DAI',
];
