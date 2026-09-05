<?php /** Giao diện: Hồ sơ cá nhân */ ?>

<?php if ((int)$nd['doi_mat_khau'] === 1): ?>
  <div class="nhan nhan-canh">
    Bạn đang dùng mật khẩu do quản trị viên cấp. Vui lòng đổi mật khẩu mới trước khi sử dụng hệ thống.
  </div>
<?php endif; ?>

<div class="the-so">
  <?php $mau = ['bt-xanh', 'bt-vang', 'bt-luc', 'bt-do']; $i = 0;
        foreach ($thongKe as $k => $v): ?>
    <div class="o-so">
      <span class="bt <?= Util::h($mau[$i++ % 4]) ?>"><?php View::manh('layout/bieu-tuong', ['ma' => 'vb']); ?></span>
      <span class="noi"><b><?= Util::h(Util::so($v)) ?></b><span><?= Util::h($k) ?></span></span>
    </div>
  <?php endforeach; ?>
</div>

<div class="luoi luoi-2">
  <form method="post" class="the">
    <input type="hidden" name="csrf" value="<?= Util::h(Util::token()) ?>">
    <input type="hidden" name="viec" value="thong_tin">
    <h2>Thông tin cá nhân</h2>
    <div class="luoi-truong" style="grid-template-columns:1fr">
      <label class="truong">Mã người xử lý
        <input type="text" value="<?= Util::h($nd['ma_nguoi_xu_ly']) ?>" readonly>
        <span class="goi-y">Dùng trong tên tệp đính kèm: <code>001_001_<?= Util::h($nd['ma_nguoi_xu_ly']) ?>.pdf</code>
          <?php if ($biDanh): ?><br>Bí danh khác: <?= Util::h(implode(', ', $biDanh)) ?><?php endif; ?>
        </span>
      </label>
      <label class="truong">Tên đăng nhập
        <input type="text" value="<?= Util::h($nd['ten_dang_nhap']) ?>" readonly>
      </label>
      <label class="truong">Họ và tên
        <input type="text" name="ho_ten" value="<?= Util::h($nd['ho_ten']) ?>" required>
      </label>
      <label class="truong">Email
        <input type="email" name="email" value="<?= Util::h($nd['email']) ?>">
      </label>
      <label class="truong">Điện thoại
        <input type="text" name="dien_thoai" value="<?= Util::h($nd['dien_thoai']) ?>">
      </label>
      <label class="truong">Chức vụ
        <input type="text" name="chuc_vu" value="<?= Util::h($nd['chuc_vu']) ?>">
      </label>
      <label class="truong">Vai trò
        <input type="text" readonly value="<?= Util::h(['admin' => 'Quản trị hệ thống', 'lanh_dao' => 'Lãnh đạo',
                'nguoi_xu_ly' => 'Người xử lý'][$nd['vai_tro']] ?? $nd['vai_tro']) ?>">
      </label>
    </div>
    <div class="hang-nut" style="margin-top:1rem">
      <button type="submit" class="nut nut-chinh">Lưu thông tin</button>
    </div>
  </form>

  <div>
    <form method="post" class="the">
      <input type="hidden" name="csrf" value="<?= Util::h(Util::token()) ?>">
      <input type="hidden" name="viec" value="doi_mat_khau">
      <h2>Đổi mật khẩu</h2>
      <div class="luoi-truong" style="grid-template-columns:1fr">
        <label class="truong">Mật khẩu hiện tại
          <input type="password" name="mat_khau_cu" required autocomplete="current-password">
        </label>
        <label class="truong">Mật khẩu mới
          <input type="password" name="mat_khau_moi" required minlength="6" autocomplete="new-password">
          <span class="goi-y">Tối thiểu 6 ký tự. Nên dùng cả chữ hoa, chữ thường, số và ký tự đặc biệt.</span>
        </label>
        <label class="truong">Nhập lại mật khẩu mới
          <input type="password" name="nhac_lai" required minlength="6" autocomplete="new-password">
        </label>
      </div>
      <div class="hang-nut" style="margin-top:1rem">
        <button type="submit" class="nut nut-chinh">Đổi mật khẩu</button>
      </div>
      <p class="goi-y" style="margin-top:.7rem">
        Sau khi đổi mật khẩu, mọi thiết bị đang “ghi nhớ đăng nhập” sẽ bị đăng xuất.
      </p>
    </form>

    <div class="the">
      <h2>Lần đăng nhập gần nhất</h2>
      <dl class="tt-luoi">
        <dt>Thời gian</dt><dd><?= Util::h(Util::ngay($nd['lan_dang_nhap_cuoi'])) ?: '—' ?></dd>
        <dt>Địa chỉ IP</dt><dd class="chu-nho"><?= Util::h($nd['ip_dang_nhap_cuoi'] ?: '—') ?></dd>
        <dt>Tổng số lần</dt><dd><?= Util::h(Util::so($nd['so_lan_dang_nhap'])) ?></dd>
        <dt>Ngày tạo tài khoản</dt><dd><?= Util::h(Util::ngayNgan($nd['ngay_tao'])) ?></dd>
      </dl>
    </div>
  </div>
</div>
