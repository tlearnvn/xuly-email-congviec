<?php
/** Điều khiển: Danh mục nguoi (đường dẫn rút gọn) */
$_GET['loai'] = 'nguoi';
View::$trangHienTai = 'danh-muc';
require DUONG_DAN_GOC . '/app/controllers/danh-muc.php';
