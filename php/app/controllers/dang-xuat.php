<?php
/** Điều khiển: Đăng xuất */
Auth::dangXuat();
Util::nhan('Bạn đã đăng xuất khỏi hệ thống.', 'tin');
Util::chuyenHuong(Util::url('dang-nhap'));
