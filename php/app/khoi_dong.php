<?php
/**
 * khoi_dong.php - Khởi tạo ứng dụng (nạp cấu hình, CSDL, phiên làm việc)
 * Hệ thống phân luồng Mail công vụ - Thiết kế bởi Trương Anh Tuấn
 */

if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    exit('Hệ thống yêu cầu PHP 7.4 trở lên. Phiên bản hiện tại: ' . PHP_VERSION);
}

define('DUONG_DAN_GOC', dirname(__DIR__));

// Số phiên bản do scripts/phien-ban.sh sinh tự động mỗi lần đóng gói
$__pb = is_file(__DIR__ . '/phien_ban.php') ? require __DIR__ . '/phien_ban.php' : [];
define('PHIEN_BAN_HE_THONG', $__pb['phien_ban'] ?? '1.0.0');
define('SO_BUILD_HE_THONG',  (int)($__pb['build'] ?? 0));
define('NGAY_BUILD_HE_THONG', $__pb['ngay'] ?? '');
define('PHIEN_BAN_DAY_DU',   $__pb['day_du'] ?? PHIEN_BAN_HE_THONG);
unset($__pb);

mb_internal_encoding('UTF-8');

// --------------------------- Tệp cấu hình ---------------------------
$tepCauHinh = DUONG_DAN_GOC . '/cau-hinh.php';
if (!is_file($tepCauHinh)) {
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'cai-dat.php') {
        header('Location: ' . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/') . '/cai-dat.php');
        exit;
    }
    $CH = [];
} else {
    $CH = require $tepCauHinh;
    if (!is_array($CH)) $CH = [];
}

// --------------------------- Múi giờ Việt Nam ---------------------------
date_default_timezone_set($CH['mui_gio'] ?? 'Asia/Ho_Chi_Minh');

// --------------------------- Hiển thị lỗi ---------------------------
$goLoi = !empty($CH['go_loi']);
ini_set('display_errors', $goLoi ? '1' : '0');
ini_set('log_errors', '1');
error_reporting($goLoi ? E_ALL : (E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING));

// --------------------------- Nạp lớp ---------------------------
spl_autoload_register(function ($ten) {
    foreach ([DUONG_DAN_GOC . '/app/core/', DUONG_DAN_GOC . '/app/controllers/'] as $tm) {
        $f = $tm . $ten . '.php';
        if (is_file($f)) { require_once $f; return; }
    }
});

// --------------------------- Cơ sở dữ liệu ---------------------------
if ($CH) Db::khoiTao($CH);

// --------------------------- Bắt lỗi chung ---------------------------
set_exception_handler(function (Throwable $e) use ($goLoi) {
    http_response_code(500);
    error_log('[PhanLuongMail] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (PHP_SAPI === 'cli') { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
    $ct = $goLoi
        ? '<pre style="white-space:pre-wrap">' . htmlspecialchars($e->getMessage() . "\n\n"
            . $e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString()) . '</pre>'
        : '<p>Hệ thống gặp sự cố khi xử lý yêu cầu. Vui lòng thử lại hoặc liên hệ quản trị viên.</p>';
    echo '<!DOCTYPE html><html lang="vi"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1"><title>Lỗi hệ thống</title>'
       . '<style>body{font-family:"Segoe UI",Arial,sans-serif;background:#f5f7fb;color:#1a2233;'
       . 'display:grid;place-items:center;min-height:100vh;margin:0}'
       . '.h{background:#fff;border:1px solid #e4e9f2;border-radius:14px;padding:2rem 2.2rem;max-width:760px;'
       . 'box-shadow:0 12px 40px rgba(17,26,48,.08)}h1{font-size:1.15rem;margin:0 0 .8rem;color:#c0392b}'
       . 'a{color:#1e5eff}</style></head><body><div class="h"><h1>Đã xảy ra lỗi</h1>' . $ct
       . '<p style="margin-top:1rem"><a href="' . htmlspecialchars(Util::url()) . '">&larr; Quay lại trang chủ</a></p>'
       . '</div></body></html>';
    exit;
});

// --------------------------- Phiên làm việc ---------------------------
if (PHP_SAPI !== 'cli') {
    Auth::batDauPhien();
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
}
