<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Đăng nhập — <?= Util::h(Ung::tenNgan()) ?></title>
<meta name="robots" content="noindex,nofollow">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%93%A8%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="<?= Util::h(Util::goc()) ?>/assets/css/app.css?v=<?= Util::h(PHIEN_BAN_HE_THONG . '.' . SO_BUILD_HE_THONG) ?>">
<style>:root{--xanh:<?= Util::h(Ung::mau()) ?>;}</style>
</head>
<body>
<div class="trang-dn">
  <div class="hop-dn">
    <div class="dn-hieu">
      <div class="bt">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 7.5A2.5 2.5 0 0 1 5.5 5h13A2.5 2.5 0 0 1 21 7.5v9a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 16.5z"/>
          <path d="m3.5 7 8.5 6 8.5-6"/>
        </svg>
      </div>
      <h1><?= Util::h(Ung::tenNgan()) ?></h1>
      <p><?= Util::h(Ung::donVi()) ?></p>
    </div>

    <?php if (!empty($loi)): ?>
      <div class="nhan nhan-loi"><?= Util::h($loi) ?></div>
    <?php endif; ?>
    <?php foreach (Util::layNhan() as $n): ?>
      <div class="nhan nhan-<?= Util::h($n['loai']) ?>"><?= Util::h($n['noi_dung']) ?></div>
    <?php endforeach; ?>

    <form method="post" autocomplete="on">
      <input type="hidden" name="csrf" value="<?= Util::h(Util::token()) ?>">
      <label class="truong">
        Tên đăng nhập
        <input type="text" name="ten_dang_nhap" value="<?= Util::h($ten_dang_nhap ?? '') ?>"
               required autofocus autocomplete="username" placeholder="Ví dụ: tat">
      </label>
      <label class="truong">
        Mật khẩu
        <span class="mk-hop">
          <input type="password" name="mat_khau" id="mk" required autocomplete="current-password" placeholder="••••••••">
          <button type="button" class="mk-hien" data-o="mk" aria-label="Hiện mật khẩu">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </span>
      </label>
      <label class="chuyen" style="margin:.3rem 0 1rem">
        <input type="checkbox" name="ghi_nho" value="1"><span>Ghi nhớ đăng nhập trên máy này</span>
      </label>
      <button type="submit" class="nut nut-chinh">Đăng nhập</button>
    </form>

    <div class="dn-chan">
      <?= Util::h(Ung::banQuyen()) ?><br>
      Phiên bản <?= Util::h(PHIEN_BAN_HE_THONG) ?> (build <?= Util::h(SO_BUILD_HE_THONG) ?>)
    </div>
  </div>
</div>
<script src="<?= Util::h(Util::goc()) ?>/assets/js/app.js?v=<?= Util::h(PHIEN_BAN_HE_THONG . '.' . SO_BUILD_HE_THONG) ?>" defer></script>
</body>
</html>
