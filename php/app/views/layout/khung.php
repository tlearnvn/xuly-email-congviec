<?php
/** Khung trang chính */
$nd  = Auth::nguoiDung();
$goc = Util::goc();
$menu = [
    ['ma' => 'bang-dieu-khien', 'ten' => 'Bảng điều khiển', 'bt' => 'bd'],
    ['ma' => 'hop-viec',        'ten' => 'Hộp việc của tôi', 'bt' => 'hv'],
    ['ma' => 'van-ban',         'ten' => 'Tất cả văn bản',  'bt' => 'vb', 'quyen' => 'xem_tat_ca'],
    ['ma' => 'phan-luong',      'ten' => 'Chờ phân luồng',  'bt' => 'pl', 'quyen' => 'admin'],
    ['ma' => 'thong-ke',        'ten' => 'Thống kê nộp báo cáo', 'bt' => 'tk'],
    ['ma' => 'danh-muc',        'ten' => 'Danh mục',        'bt' => 'dm', 'quyen' => 'admin'],
    ['ma' => 'nhat-ky',         'ten' => 'Nhật ký',         'bt' => 'nk', 'quyen' => 'admin'],
    ['ma' => 'cai-dat',         'ten' => 'Cài đặt',         'bt' => 'cd', 'quyen' => 'admin'],
];
$soCho = 0;
try {
    if (Auth::laAdmin()) {
        $soCho = (int)Db::giaTri("SELECT COUNT(*) FROM cong_viec WHERE trang_thai = 'cho_phan_luong'", [], 0);
    }
} catch (Throwable $e) {}
$soViecCuaToi = 0;
try {
    $soViecCuaToi = (int)Db::giaTri(
        "SELECT COUNT(*) FROM cong_viec WHERE id_nguoi_xu_ly = ? AND trang_thai IN ('cho_xu_ly','dang_xu_ly') AND la_ban_moi_nhat = 1",
        [Auth::id()], 0);
} catch (Throwable $e) {}
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Util::h((View::$tieuDe ? View::$tieuDe . ' — ' : '') . Ung::tenNgan()) ?></title>
<meta name="robots" content="noindex,nofollow">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%93%A8%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="<?= Util::h($goc) ?>/assets/css/app.css?v=<?= Util::h(PHIEN_BAN_HE_THONG . '.' . SO_BUILD_HE_THONG) ?>">
<style>:root{--xanh:<?= Util::h(Ung::mau()) ?>;}</style>
</head>
<body>

<a class="bo-qua" href="#noi-dung">Bỏ qua tới nội dung chính</a>

<div class="ung-dung">
  <aside class="ben" id="thanh-ben">
    <div class="hieu">
      <div class="hieu-bt" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 7.5A2.5 2.5 0 0 1 5.5 5h13A2.5 2.5 0 0 1 21 7.5v9a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 16.5z"/>
          <path d="m3.5 7 8.5 6 8.5-6"/>
        </svg>
      </div>
      <div class="hieu-chu">
        <strong><?= Util::h(Ung::tenNgan()) ?></strong>
        <span><?= Util::h(Ung::donVi()) ?></span>
      </div>
    </div>

    <nav class="dieu-huong" aria-label="Menu chính">
      <?php foreach ($menu as $m):
          if (($m['quyen'] ?? '') === 'admin' && !Auth::laAdmin()) continue;
          if (($m['quyen'] ?? '') === 'xem_tat_ca' && !Auth::xemTatCa()) continue;
          $dang = (View::$trangHienTai === $m['ma']
                  || (View::$trangHienTai === 'chi-tiet' && $m['ma'] === 'hop-viec')
                  || (strpos(View::$trangHienTai, 'danh-muc') === 0 && $m['ma'] === 'danh-muc'));
      ?>
      <a class="muc<?= $dang ? ' dang-chon' : '' ?>" href="<?= Util::h(Util::url($m['ma'])) ?>">
        <?php View::manh('layout/bieu-tuong', ['ma' => $m['bt']]); ?>
        <span><?= Util::h($m['ten']) ?></span>
        <?php if ($m['ma'] === 'phan-luong' && $soCho > 0): ?>
          <em class="dem"><?= Util::h($soCho) ?></em>
        <?php elseif ($m['ma'] === 'hop-viec' && $soViecCuaToi > 0): ?>
          <em class="dem xanh"><?= Util::h($soViecCuaToi) ?></em>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </nav>

    <div class="ben-chan">
      <a class="nguoi" href="<?= Util::h(Util::url('ho-so')) ?>">
        <span class="anh"><?= Util::h(mb_strtoupper(mb_substr($nd['ho_ten'] ?? '?', 0, 1))) ?></span>
        <span class="nguoi-chu">
          <strong><?= Util::h($nd['ho_ten'] ?? '') ?></strong>
          <small><?= Util::h($nd['ma_nguoi_xu_ly'] ?? '') ?> ·
            <?= Util::h(['admin' => 'Quản trị', 'lanh_dao' => 'Lãnh đạo', 'nguoi_xu_ly' => 'Người xử lý'][$nd['vai_tro'] ?? ''] ?? '') ?>
          </small>
        </span>
      </a>
      <a class="thoat" href="<?= Util::h(Util::url('dang-xuat', ['csrf' => Util::token()])) ?>" title="Đăng xuất">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>
        </svg>
      </a>
    </div>
  </aside>

  <main class="chinh" id="noi-dung">
    <header class="dinh">
      <button class="nut-menu" id="nut-menu" aria-label="Mở menu">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>
      <div class="dinh-chu">
        <h1><?= Util::h(View::$tieuDe ?: Ung::tenNgan()) ?></h1>
        <p class="phu"><?= Util::h(Ung::tenUngDung()) ?></p>
      </div>
      <div class="dinh-phai">
        <span class="dong-ho" id="dong-ho" title="Giờ Việt Nam (GMT+7)"><?= Util::h(Util::bayGio('H:i')) ?></span>
      </div>
    </header>

    <?php foreach (Util::layNhan() as $n): ?>
      <div class="nhan nhan-<?= Util::h($n['loai']) ?>"><?= Util::h($n['noi_dung']) ?></div>
    <?php endforeach; ?>

    <?= $noiDung ?>

    <footer class="chan">
      <span><?= Util::h(Ung::banQuyen()) ?></span>
      <span>
        <?= Util::h(Ung::tenNgan()) ?> · phiên bản <?= Util::h(PHIEN_BAN_HE_THONG) ?>
        (build <?= Util::h(SO_BUILD_HE_THONG) ?>)
      </span>
    </footer>
  </main>
</div>

<script src="<?= Util::h($goc) ?>/assets/js/app.js?v=<?= Util::h(PHIEN_BAN_HE_THONG . '.' . SO_BUILD_HE_THONG) ?>" defer></script>
</body>
</html>
