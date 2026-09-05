<?php
/**
 * =====================================================================
 *  cai-dat.php - Trình cài đặt hệ thống (chạy một lần khi mới tải lên)
 *  HỆ THỐNG PHÂN LUỒNG MAIL CÔNG VỤ
 *  Thiết kế bởi Trương Anh Tuấn
 * =====================================================================
 */

mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Ho_Chi_Minh');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

define('GOC', __DIR__);
$tepCauHinh = GOC . '/cau-hinh.php';
$daCaiDat = is_file($tepCauHinh);

$goc = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$loi = [];
$ok  = [];
$buoc = 1;

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/**
 * Bỏ các dòng chú thích nằm ở ĐẦU một câu lệnh SQL.
 * Tệp schema có khối chú thích trước mỗi CREATE TABLE; nếu bỏ nguyên cả khối
 * thì câu lệnh phía sau cũng bị mất, nên chỉ cắt phần chú thích đầu.
 */
function boChuThichDau(string $cau): string
{
    $dong = preg_split('/\r\n|\r|\n/', $cau);
    while ($dong) {
        $d = trim($dong[0]);
        if ($d === '' || strpos($d, '--') === 0 || strpos($d, '#') === 0) {
            array_shift($dong);
            continue;
        }
        break;
    }
    return trim(implode("\n", $dong));
}

// ---------------------------------------------------------------------
//  Kiểm tra môi trường
// ---------------------------------------------------------------------
$kiemTra = [
    ['PHP 7.4 trở lên', version_compare(PHP_VERSION, '7.4.0', '>='), PHP_VERSION],
    ['Phần mở rộng PDO MySQL', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? 'Có' : 'Thiếu'],
    ['Phần mở rộng mbstring', extension_loaded('mbstring'), extension_loaded('mbstring') ? 'Có' : 'Thiếu'],
    ['Phần mở rộng cURL (cho AI)', function_exists('curl_init'), function_exists('curl_init') ? 'Có' : 'Thiếu'],
    ['Phần mở rộng JSON', function_exists('json_encode'), 'Có'],
    ['Quyền ghi thư mục gốc', is_writable(GOC), is_writable(GOC) ? 'Ghi được' : 'Không ghi được'],
];
$moiTruongOk = true;
foreach ($kiemTra as $k) if (!$k[1] && $k[0] !== 'Phần mở rộng cURL (cho AI)') $moiTruongOk = false;

// ---------------------------------------------------------------------
//  Xử lý biểu mẫu
// ---------------------------------------------------------------------
$duLieu = [
    'may_chu' => 'localhost', 'cong' => '3306', 'co_so_du_lieu' => '',
    'nguoi_dung' => '', 'mat_khau' => '',
    'admin_ten' => 'admin', 'admin_ho_ten' => 'Quản trị hệ thống',
    'admin_ma' => 'TAT', 'admin_email' => '',
    'ten_ung_dung' => 'Hệ thống phân luồng Mail công vụ - Phòng GDPT-GDTX SGDĐT Đồng Nai',
    'ban_quyen' => 'Thiết kế bởi Trương Anh Tuấn',
    'don_vi' => 'Phòng GDPT-GDTX - Sở GD&ĐT Đồng Nai',
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !$daCaiDat) {
    foreach ($duLieu as $k => $v) {
        if (isset($_POST[$k])) $duLieu[$k] = trim((string)$_POST[$k]);
    }
    $mkAdmin  = (string)($_POST['admin_mat_khau'] ?? '');
    $mkNhac   = (string)($_POST['admin_nhac_lai'] ?? '');
    $duLieuMau = !empty($_POST['du_lieu_mau']);

    if ($duLieu['co_so_du_lieu'] === '' || $duLieu['nguoi_dung'] === '') {
        $loi[] = 'Vui lòng nhập đủ tên cơ sở dữ liệu và tên người dùng MySQL.';
    }
    if (mb_strlen($mkAdmin) < 6) $loi[] = 'Mật khẩu quản trị phải từ 6 ký tự trở lên.';
    if ($mkAdmin !== $mkNhac)    $loi[] = 'Hai lần nhập mật khẩu quản trị không khớp.';
    if (!preg_match('/^[a-z0-9._\-]{3,64}$/', strtolower($duLieu['admin_ten']))) {
        $loi[] = 'Tên đăng nhập quản trị chỉ gồm chữ thường, số, dấu chấm, gạch dưới, gạch ngang (3-64 ký tự).';
    }

    if (!$loi) {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                           $duLieu['may_chu'], (int)$duLieu['cong'], $duLieu['co_so_du_lieu']);
            $pdo = new PDO($dsn, $duLieu['nguoi_dung'], $duLieu['mat_khau'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec("SET time_zone = '+07:00'");
            $pdo->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");

            // ---- Nạp cấu trúc bảng ----
            foreach (['01_schema.sql', '02_du_lieu_mau.sql'] as $i => $tenSql) {
                if ($i === 1 && !$duLieuMau) continue;
                $duong = GOC . '/sql/' . $tenSql;
                if (!is_file($duong)) $duong = dirname(GOC) . '/sql/' . $tenSql;
                if (!is_file($duong)) {
                    if ($i === 0) throw new RuntimeException(
                        'Không tìm thấy tệp sql/01_schema.sql. Hãy tải cả thư mục sql lên cùng mã nguồn.');
                    continue;
                }
                $sql = file_get_contents($duong);
                if (substr($sql, 0, 3) === "\xEF\xBB\xBF") $sql = substr($sql, 3);
                // Tách câu lệnh theo dấu ; ở cuối dòng
                foreach (preg_split('/;\s*[\r\n]+/', $sql) as $cau) {
                    $cau = boChuThichDau($cau);
                    if ($cau === '') continue;
                    try { $pdo->exec($cau); } catch (PDOException $e) {
                        if (strpos($e->getMessage(), 'already exists') === false) throw $e;
                    }
                }
            }

            // ---- Tài khoản quản trị ----
            $tdn = strtolower($duLieu['admin_ten']);
            $st = $pdo->prepare('SELECT id FROM nguoi_xu_ly WHERE ten_dang_nhap = ? OR ma_nguoi_xu_ly = ?');
            $st->execute([$tdn, strtoupper($duLieu['admin_ma'])]);
            $co = $st->fetch();
            $bam = password_hash($mkAdmin, PASSWORD_DEFAULT);
            if ($co) {
                $pdo->prepare('UPDATE nguoi_xu_ly SET ho_ten = ?, ten_dang_nhap = ?, mat_khau = ?,
                               email = ?, vai_tro = "admin", nhan_tat_ca = 1, trang_thai = 1,
                               doi_mat_khau = 0, ngay_cap_nhat = NOW() WHERE id = ?')
                    ->execute([$duLieu['admin_ho_ten'], $tdn, $bam, $duLieu['admin_email'], $co['id']]);
            } else {
                $pdo->prepare('INSERT INTO nguoi_xu_ly (ma_nguoi_xu_ly, ho_ten, ten_dang_nhap, mat_khau,
                               email, vai_tro, nhan_tat_ca, trang_thai, doi_mat_khau, ngay_tao, ngay_cap_nhat)
                               VALUES (?,?,?,?,?,"admin",1,1,0,NOW(),NOW())')
                    ->execute([strtoupper($duLieu['admin_ma']), $duLieu['admin_ho_ten'], $tdn, $bam,
                               $duLieu['admin_email']]);
            }

            // ---- Thương hiệu + khoá API ----
            $khoaApi = bin2hex(random_bytes(24));
            $dat = $pdo->prepare('INSERT INTO cau_hinh (khoa, gia_tri, nhom, kieu, ngay_cap_nhat)
                                  VALUES (?,?,?,?,NOW())
                                  ON DUPLICATE KEY UPDATE gia_tri = VALUES(gia_tri), ngay_cap_nhat = NOW()');
            $dat->execute(['app.ten_ung_dung', $duLieu['ten_ung_dung'], 'giao_dien', 'text']);
            $dat->execute(['app.ban_quyen', $duLieu['ban_quyen'], 'giao_dien', 'text']);
            $dat->execute(['app.don_vi', $duLieu['don_vi'], 'giao_dien', 'text']);
            $dat->execute(['api.khoa', $khoaApi, 'api', 'password']);

            // ---- Ghi tệp cấu hình ----
            $noiDung = "<?php\n"
                . "/**\n * Tệp cấu hình do trình cài đặt sinh lúc " . date('d/m/Y H:i:s') . " (giờ Việt Nam)\n"
                . " * HỆ THỐNG PHÂN LUỒNG MAIL CÔNG VỤ - Thiết kế bởi Trương Anh Tuấn\n */\n\n"
                . "return [\n"
                . "    'may_chu'       => " . var_export($duLieu['may_chu'], true) . ",\n"
                . "    'cong'          => " . (int)$duLieu['cong'] . ",\n"
                . "    'co_so_du_lieu' => " . var_export($duLieu['co_so_du_lieu'], true) . ",\n"
                . "    'nguoi_dung'    => " . var_export($duLieu['nguoi_dung'], true) . ",\n"
                . "    'mat_khau'      => " . var_export($duLieu['mat_khau'], true) . ",\n"
                . "    'bang_ma'       => 'utf8mb4',\n"
                . "    'mui_gio'       => 'Asia/Ho_Chi_Minh',\n"
                . "    'go_loi'        => false,\n"
                . "    'thu_muc_tam'   => '',\n"
                . "    'khoa_bi_mat'   => " . var_export(bin2hex(random_bytes(24)), true) . ",\n"
                . "];\n";
            if (@file_put_contents($tepCauHinh, $noiDung) === false) {
                throw new RuntimeException(
                    'Không ghi được tệp cau-hinh.php. Hãy cấp quyền ghi cho thư mục, hoặc tạo tệp thủ công '
                    . 'theo mẫu cau-hinh.mau.php với nội dung:' . "\n\n" . $noiDung);
            }
            @chmod($tepCauHinh, 0640);

            $ok[] = 'Cài đặt thành công!';
            $ok[] = 'Khoá API cho bộ nhận mail: ' . $khoaApi;
            $daCaiDat = true;
            $buoc = 3;
        } catch (Throwable $e) {
            $loi[] = $e->getMessage();
        }
    }
}
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cài đặt — Hệ thống phân luồng Mail công vụ</title>
<meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" href="<?= h($goc) ?>/assets/css/app.css">
<style>
  body{background:linear-gradient(150deg,#101a30,#16294c 45%,#1e3f7d);min-height:100vh;padding:2rem 1rem;}
  .khung{max-width:880px;margin:0 auto;}
  .the{margin-bottom:1.2rem;}
  .dau{text-align:center;color:#fff;margin-bottom:1.6rem;}
  .dau h1{color:#fff;font-size:1.35rem;margin-bottom:.3rem;}
  .dau p{color:#a8bbdd;font-size:.88rem;}
  .kt{display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px dashed var(--vien);font-size:.87rem;}
  .kt:last-child{border-bottom:0;}
  .kt b{font-weight:600;}
</style>
</head>
<body>
<div class="khung">
  <div class="dau">
    <h1>Cài đặt hệ thống phân luồng Mail công vụ</h1>
    <p>Phòng GDPT-GDTX — Sở GD&amp;ĐT Đồng Nai · Thiết kế bởi Trương Anh Tuấn</p>
  </div>

  <?php foreach ($loi as $l): ?>
    <div class="nhan nhan-loi" style="white-space:pre-wrap"><?= h($l) ?></div>
  <?php endforeach; ?>
  <?php foreach ($ok as $o): ?>
    <div class="nhan nhan-ok" style="word-break:break-all"><?= h($o) ?></div>
  <?php endforeach; ?>

  <?php if ($daCaiDat && !$loi): ?>
    <div class="the">
      <h2>Hệ thống đã được cài đặt</h2>
      <p>Tệp <code>cau-hinh.php</code> đã tồn tại nên trình cài đặt không chạy lại.</p>
      <div class="nhan nhan-canh" style="margin-top:1rem">
        <strong>Việc cần làm ngay vì lý do an toàn:</strong><br>
        1. Xoá tệp <code>cai-dat.php</code> khỏi máy chủ.<br>
        2. Đăng nhập và đổi mật khẩu các tài khoản mẫu (nếu đã nạp dữ liệu mẫu).<br>
        3. Vào <em>Cài đặt → Kết nối bộ nhận mail</em> để lấy khoá API cho phần nhận mail.
      </div>
      <div class="hang-nut" style="margin-top:1rem">
        <a class="nut nut-chinh" href="<?= h($goc) ?>/index.php">Vào hệ thống</a>
      </div>
    </div>
  <?php else: ?>

  <div class="the">
    <h2>Bước 1 — Kiểm tra môi trường máy chủ</h2>
    <?php foreach ($kiemTra as $k): ?>
      <div class="kt">
        <span><?= h($k[0]) ?></span>
        <b style="color:<?= $k[1] ? '#10a05a' : '#dc4437' ?>"><?= h($k[2]) ?></b>
      </div>
    <?php endforeach; ?>
    <?php if (!$moiTruongOk): ?>
      <div class="nhan nhan-loi" style="margin-top:1rem">
        Máy chủ chưa đủ điều kiện. Vui lòng liên hệ nhà cung cấp hosting để bật các phần mở rộng còn thiếu.
      </div>
    <?php endif; ?>
  </div>

  <form method="post">
    <div class="the">
      <h2>Bước 2 — Kết nối cơ sở dữ liệu MySQL</h2>
      <p class="goi-y">Thông tin này lấy trong cPanel → MySQL Databases. Hãy tạo sẵn cơ sở dữ liệu
        và người dùng có toàn quyền trên cơ sở dữ liệu đó.</p>
      <div class="luoi-truong">
        <label class="truong">Máy chủ
          <input type="text" name="may_chu" value="<?= h($duLieu['may_chu']) ?>" required>
        </label>
        <label class="truong">Cổng
          <input type="number" name="cong" value="<?= h($duLieu['cong']) ?>" required>
        </label>
        <label class="truong">Tên cơ sở dữ liệu
          <input type="text" name="co_so_du_lieu" value="<?= h($duLieu['co_so_du_lieu']) ?>"
                 placeholder="taikhoan_phanluong" required>
        </label>
        <label class="truong">Người dùng MySQL
          <input type="text" name="nguoi_dung" value="<?= h($duLieu['nguoi_dung']) ?>"
                 placeholder="taikhoan_mail" required>
        </label>
        <label class="truong rong">Mật khẩu MySQL
          <input type="password" name="mat_khau" value="<?= h($duLieu['mat_khau']) ?>">
        </label>
      </div>
      <label class="chuyen" style="margin-top:.9rem">
        <input type="checkbox" name="du_lieu_mau" value="1" checked>
        <span>Nạp danh mục mẫu (12 trường, 5 người xử lý, 8 mã văn bản) để dùng thử</span>
      </label>
    </div>

    <div class="the">
      <h2>Bước 3 — Tài khoản quản trị</h2>
      <div class="luoi-truong">
        <label class="truong">Mã người xử lý
          <input type="text" name="admin_ma" value="<?= h($duLieu['admin_ma']) ?>" required
                 style="text-transform:uppercase">
        </label>
        <label class="truong">Họ và tên
          <input type="text" name="admin_ho_ten" value="<?= h($duLieu['admin_ho_ten']) ?>" required>
        </label>
        <label class="truong">Tên đăng nhập
          <input type="text" name="admin_ten" value="<?= h($duLieu['admin_ten']) ?>" required>
        </label>
        <label class="truong">Email
          <input type="email" name="admin_email" value="<?= h($duLieu['admin_email']) ?>">
        </label>
        <label class="truong">Mật khẩu
          <input type="password" name="admin_mat_khau" required minlength="6">
        </label>
        <label class="truong">Nhập lại mật khẩu
          <input type="password" name="admin_nhac_lai" required minlength="6">
        </label>
      </div>
    </div>

    <div class="the">
      <h2>Bước 4 — Thương hiệu hiển thị</h2>
      <div class="luoi-truong" style="grid-template-columns:1fr">
        <label class="truong">Tên ứng dụng
          <input type="text" name="ten_ung_dung" value="<?= h($duLieu['ten_ung_dung']) ?>">
        </label>
        <label class="truong">Tên đơn vị
          <input type="text" name="don_vi" value="<?= h($duLieu['don_vi']) ?>">
        </label>
        <label class="truong">Bản quyền / Copyright
          <input type="text" name="ban_quyen" value="<?= h($duLieu['ban_quyen']) ?>">
        </label>
      </div>
      <div class="hang-nut" style="margin-top:1.2rem">
        <button type="submit" class="nut nut-chinh" <?= $moiTruongOk ? '' : 'disabled' ?>>
          Tiến hành cài đặt
        </button>
      </div>
    </div>
  </form>
  <?php endif; ?>

  <p style="text-align:center;color:#8ea3c9;font-size:.78rem;margin-top:1.4rem">
    Thiết kế bởi Trương Anh Tuấn
  </p>
</div>
</body>
</html>
