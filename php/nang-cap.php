<?php
/**
 * =====================================================================
 *  nang-cap.php - Nâng cấp cơ sở dữ liệu đã cài từ phiên bản trước
 *  HỆ THỐNG PHÂN LUỒNG MAIL CÔNG VỤ
 *  Thiết kế bởi Trương Anh Tuấn
 * ---------------------------------------------------------------------
 *  Chạy được nhiều lần: phần nào đã có thì bỏ qua, dữ liệu cũ giữ nguyên.
 *  Xoá tệp này sau khi nâng cấp xong.
 * =====================================================================
 */

mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Ho_Chi_Minh');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

define('GOC', __DIR__);
$goc = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$loi = [];
$ok  = [];
$viec = [];              // [tên việc, đã có sẵn?, mô tả]
$daChay = false;

// ---------------------------------------------------------------------
//  Danh sách việc cần nâng cấp, khai báo tập trung một chỗ
// ---------------------------------------------------------------------
$DANH_SACH = [
    [
        'ten'  => 'Cột email.tu_spam',
        'mo_ta' => 'Đánh dấu thư vớt được từ hộp Thư rác của Gmail',
        'ban'  => '1.2.0',
        'co'   => function (PDO $db) {
            return (bool)$db->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email'
                   AND COLUMN_NAME = 'tu_spam'")->fetchColumn();
        },
        'lam'  => function (PDO $db) {
            $db->exec("ALTER TABLE `email`
                       ADD COLUMN `tu_spam` TINYINT(1) NOT NULL DEFAULT 0 AFTER `ghi_chu_ai`");
        },
    ],
    [
        'ten'  => 'Chỉ mục idx_email_spam',
        'mo_ta' => 'Lọc nhanh danh sách thư đến từ hộp Thư rác',
        'ban'  => '1.2.0',
        'co'   => function (PDO $db) {
            return (bool)$db->query(
                "SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email'
                   AND INDEX_NAME = 'idx_email_spam'")->fetchColumn();
        },
        'lam'  => function (PDO $db) {
            $db->exec("ALTER TABLE `email` ADD KEY `idx_email_spam` (`tu_spam`)");
        },
    ],
    [
        'ten'  => 'Thiết lập gmail.quet_spam',
        'mo_ta' => 'Bật/tắt việc quét hộp Thư rác, mặc định bật',
        'ban'  => '1.2.0',
        'co'   => function (PDO $db) {
            $s = $db->prepare("SELECT COUNT(*) FROM cau_hinh WHERE khoa = ?");
            $s->execute(['gmail.quet_spam']);
            return (bool)$s->fetchColumn();
        },
        'lam'  => function (PDO $db) {
            $s = $db->prepare(
                "INSERT INTO cau_hinh (khoa, gia_tri, nhom, kieu, nhan, mo_ta, thu_tu, bi_mat, ngay_cap_nhat)
                 VALUES (?,?,?,?,?,?,?,0,NOW())");
            $s->execute([
                'gmail.quet_spam', '1', 'gmail', 'bool', 'Quét cả hộp Thư rác (Spam)',
                'Google hay xếp nhầm báo cáo của trường vào Thư rác; tắt đi là bỏ sót', 6,
            ]);
        },
    ],
];

// ---------------------------------------------------------------------
//  Kết nối bằng chính tệp cấu hình của phần web
// ---------------------------------------------------------------------
$tepCauHinh = GOC . '/cau-hinh.php';
$db = null;

if (!is_file($tepCauHinh)) {
    $loi[] = "Chưa có tệp cau-hinh.php — hệ thống chưa được cài đặt.\n"
           . "Hãy mở cai-dat.php để cài mới, không cần chạy trang nâng cấp này.";
} else {
    $c = require $tepCauHinh;
    try {
        $dsn = 'mysql:host=' . ($c['may_chu'] ?? 'localhost')
             . ';port=' . (int)($c['cong'] ?? 3306)
             . ';dbname=' . ($c['co_so_du_lieu'] ?? '')
             . ';charset=' . ($c['bang_ma'] ?? 'utf8mb4');
        $db = new PDO($dsn, $c['nguoi_dung'] ?? '', $c['mat_khau'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $db->exec("SET time_zone = '+07:00'");
    } catch (Throwable $e) {
        $db = null;
        $loi[] = 'Không kết nối được cơ sở dữ liệu: ' . $e->getMessage();
    }
}

// ---------------------------------------------------------------------
//  Xác định việc nào còn thiếu
// ---------------------------------------------------------------------
if ($db) {
    foreach ($DANH_SACH as $v) {
        try {
            $viec[] = ['ten' => $v['ten'], 'mo_ta' => $v['mo_ta'], 'ban' => $v['ban'],
                       'da_co' => ($v['co'])($db)];
        } catch (Throwable $e) {
            $loi[] = 'Không kiểm tra được "' . $v['ten'] . '": ' . $e->getMessage();
        }
    }
}

// ---------------------------------------------------------------------
//  Thực hiện nâng cấp
// ---------------------------------------------------------------------
if ($db && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $daChay = true;
    $soLam = 0;
    foreach ($DANH_SACH as $v) {
        try {
            if (($v['co'])($db)) continue;
            ($v['lam'])($db);
            $ok[] = 'Đã thêm: ' . $v['ten'];
            $soLam++;
        } catch (Throwable $e) {
            $loi[] = 'Lỗi khi thêm "' . $v['ten'] . '": ' . $e->getMessage();
        }
    }
    if (!$loi) {
        $ok[] = $soLam > 0
            ? "Nâng cấp xong $soLam mục. Hãy XOÁ tệp nang-cap.php để tránh người lạ chạy lại."
            : 'Cơ sở dữ liệu đã đầy đủ, không có gì phải nâng cấp.';
    }
    // Đọc lại trạng thái sau khi chạy
    $viec = [];
    foreach ($DANH_SACH as $v) {
        try {
            $viec[] = ['ten' => $v['ten'], 'mo_ta' => $v['mo_ta'], 'ban' => $v['ban'],
                       'da_co' => ($v['co'])($db)];
        } catch (Throwable $e) { /* lỗi đã ghi ở trên */ }
    }
}

$conThieu = 0;
foreach ($viec as $v) if (!$v['da_co']) $conThieu++;
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nâng cấp — Hệ thống phân luồng Mail công vụ</title>
<meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" href="<?= h($goc) ?>/assets/css/app.css">
<style>
  body{background:linear-gradient(150deg,#101a30,#16294c 45%,#1e3f7d);min-height:100vh;padding:2rem 1rem;}
  .khung{max-width:880px;margin:0 auto;}
  .the{margin-bottom:1.2rem;}
  .dau{text-align:center;color:#fff;margin-bottom:1.6rem;}
  .dau h1{color:#fff;font-size:1.35rem;margin-bottom:.3rem;}
  .dau p{color:#a8bbdd;font-size:.88rem;}
  .kt{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;
      padding:.62rem 0;border-bottom:1px dashed var(--vien);font-size:.87rem;}
  .kt:last-child{border-bottom:0;}
  .kt b{font-weight:600;}
  .kt .phu{color:var(--chu-nhat);font-size:.79rem;font-weight:400;display:block;margin-top:.15rem;}
</style>
</head>
<body>
<div class="khung">
  <div class="dau">
    <h1>Nâng cấp cơ sở dữ liệu</h1>
    <p>Phòng GDPT-GDTX — Sở GD&amp;ĐT Đồng Nai · Thiết kế bởi Trương Anh Tuấn</p>
  </div>

  <?php foreach ($loi as $l): ?>
    <div class="nhan nhan-loi" style="white-space:pre-wrap"><?= h($l) ?></div>
  <?php endforeach; ?>
  <?php foreach ($ok as $o): ?>
    <div class="nhan nhan-ok"><?= h($o) ?></div>
  <?php endforeach; ?>

  <?php if ($db): ?>
    <div class="the">
      <h2>Các mục cần có</h2>
      <?php foreach ($viec as $v): ?>
        <div class="kt">
          <span>
            <b><?= h($v['ten']) ?></b>
            <span class="phu"><?= h($v['mo_ta']) ?> · có từ bản <?= h($v['ban']) ?></span>
          </span>
          <span class="hh <?= $v['da_co'] ? 'hh-luc' : 'hh-vang' ?>">
            <?= $v['da_co'] ? 'Đã có' : 'Còn thiếu' ?>
          </span>
        </div>
      <?php endforeach; ?>

      <?php if ($conThieu > 0): ?>
        <form method="post" style="margin-top:1.1rem">
          <p class="goi-y" style="margin-bottom:.7rem">
            Còn <?= (int)$conThieu ?> mục chưa có. Nâng cấp chỉ <em>thêm</em> cột và thiết lập mới,
            không sửa và không xoá dữ liệu đã lưu.
          </p>
          <button type="submit" class="nut nut-chinh">Nâng cấp ngay</button>
        </form>
      <?php elseif ($daChay || $viec): ?>
        <div class="nhan nhan-ok" style="margin-top:1.1rem">
          Cơ sở dữ liệu đã đầy đủ. Hãy <b>xoá tệp nang-cap.php</b> khỏi hosting.
        </div>
        <a class="nut nut-phu" href="<?= h($goc) ?>/index.php">Về trang chính</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <p style="text-align:center;color:#8ea5cc;font-size:.78rem;margin-top:1.4rem">
    Nâng cấp xong nhớ xoá tệp này để người ngoài không chạy lại được.
  </p>
</div>
</body>
</html>
