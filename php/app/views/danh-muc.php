<?php /** Giao diện: Danh mục */ ?>

<div class="chip-hang" style="margin-bottom:1rem">
  <?php foreach (['truong' => 'Trường', 'nguoi' => 'Người xử lý', 'van_ban' => 'Mã văn bản'] as $k => $v): ?>
    <a class="chip<?= $loai === $k ? ' dang' : '' ?>" href="<?= Util::h(Util::url('danh-muc', ['loai' => $k])) ?>">
      <?= Util::h($v) ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="luoi luoi-2-1">
  <!-- ==================== Danh sách ==================== -->
  <div class="the">
    <div class="the-dau">
      <h2><?= Util::h(View::$tieuDe) ?> <span class="chu-nho chu-mo">(<?= Util::h(Util::so($tong)) ?>)</span></h2>
      <form method="get" style="display:flex;gap:.4rem">
        <input type="hidden" name="t" value="danh-muc">
        <input type="hidden" name="loai" value="<?= Util::h($loai) ?>">
        <input type="search" name="q" value="<?= Util::h($tuKhoa) ?>" placeholder="Tìm kiếm…" style="width:190px">
        <button class="nut nut-phu nho" type="submit">Tìm</button>
      </form>
    </div>

    <div class="bang-bao cuon-doc" style="max-height:640px">
      <?php if ($loai === 'truong'): ?>
        <table class="bang bang-gon">
          <thead><tr><th>Mã</th><th>Tên trường</th><th>Địa bàn</th><th>Email</th>
                     <th class="giua">Văn bản</th><th>TT</th><th class="co-nho"></th></tr></thead>
          <tbody>
          <?php foreach ($ds as $d): ?>
            <tr>
              <td><span class="ma-ho-so"><?= Util::h($d['ma_truong']) ?></span></td>
              <td><?= Util::h($d['ten_truong']) ?>
                <?php if ($d['ten_viet_tat']): ?><span class="chu-nho chu-mo">(<?= Util::h($d['ten_viet_tat']) ?>)</span><?php endif; ?>
              </td>
              <td class="chu-nho"><?= Util::h($d['dia_ban']) ?></td>
              <td class="chu-nho chu-mo"><?= Util::h(Util::catChu($d['email'], 26)) ?></td>
              <td class="giua chu-nho"><?= Util::h($d['so_vb']) ?></td>
              <td><?= (int)$d['trang_thai'] ? '<span class="hh hh-luc">Hoạt động</span>' : '<span class="hh hh-xam">Ngưng</span>' ?></td>
              <td class="co-nho">
                <a class="nut nut-phu li-ti" href="<?= Util::h(Util::urlGiu(['sua' => $d['id']])) ?>">Sửa</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>

      <?php elseif ($loai === 'nguoi'): ?>
        <table class="bang bang-gon">
          <thead><tr><th>Mã</th><th>Họ tên</th><th>Đăng nhập</th><th>Vai trò</th>
                     <th class="giua">Việc</th><th>Lần cuối</th><th>TT</th><th class="co-nho"></th></tr></thead>
          <tbody>
          <?php foreach ($ds as $d): ?>
            <tr>
              <td>
                <span class="ma-ho-so"><?= Util::h($d['ma_nguoi_xu_ly']) ?></span>
                <?php if (!empty($d['bi_danh'])): ?>
                  <div class="chu-nho chu-mo">bí danh: <?= Util::h($d['bi_danh']) ?></div>
                <?php endif; ?>
              </td>
              <td><?= Util::h($d['ho_ten']) ?>
                <div class="chu-nho chu-mo"><?= Util::h($d['chuc_vu']) ?></div>
              </td>
              <td class="chu-nho"><?= Util::h($d['ten_dang_nhap']) ?></td>
              <td class="chu-nho">
                <?= Util::h(['admin' => 'Quản trị', 'lanh_dao' => 'Lãnh đạo', 'nguoi_xu_ly' => 'Người xử lý'][$d['vai_tro']] ?? '') ?>
                <?php if ((int)$d['nhan_tat_ca']): ?><div class="chu-mo">xem tất cả</div><?php endif; ?>
              </td>
              <td class="giua chu-nho"><?= Util::h($d['so_vb']) ?></td>
              <td class="chu-nho chu-mo"><?= Util::h(Util::tuongDoi($d['lan_dang_nhap_cuoi'])) ?></td>
              <td><?= (int)$d['trang_thai'] ? '<span class="hh hh-luc">Hoạt động</span>' : '<span class="hh hh-xam">Khoá</span>' ?></td>
              <td class="co-nho">
                <a class="nut nut-phu li-ti" href="<?= Util::h(Util::urlGiu(['sua' => $d['id']])) ?>">Sửa</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>

      <?php else: ?>
        <table class="bang bang-gon">
          <thead><tr><th>Mã</th><th>Tên văn bản / công việc</th><th>Kỳ</th><th>Người mặc định</th>
                     <th class="giua">Số VB</th><th>TT</th><th class="co-nho"></th></tr></thead>
          <tbody>
          <?php foreach ($ds as $d): ?>
            <tr<?= (int)$d['tu_dong_tao'] ? ' style="background:#fdf9ee"' : '' ?>>
              <td><span class="ma-ho-so"><?= Util::h($d['ma_van_ban']) ?></span></td>
              <td>
                <?= Util::h($d['ten_van_ban']) ?>
                <?php if ((int)$d['tu_dong_tao']): ?>
                  <div><span class="hh hh-vang hh-nhat">Mã tự thêm — cần đặt tên chính thức</span></div>
                <?php endif; ?>
                <?php if ((int)$d['bat_buoc_nop']): ?>
                  <div><span class="hh hh-xanh-duong hh-nhat">Bắt buộc nộp</span>
                    <?php if ($d['han_nop']): ?>
                      <span class="chu-nho chu-mo">hạn <?= Util::h(Util::ngayNgan($d['han_nop'])) ?></span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </td>
              <td class="chu-nho chu-mo"><?= Util::h(Util::tenKyBaoCao($d['ky_bao_cao'])) ?></td>
              <td class="chu-nho"><?= Util::h($d['ten_nxl'] ?: '—') ?></td>
              <td class="giua chu-nho"><?= Util::h($d['so_vb']) ?></td>
              <td><?= (int)$d['trang_thai'] ? '<span class="hh hh-luc">Dùng</span>' : '<span class="hh hh-xam">Ngưng</span>' ?></td>
              <td class="co-nho">
                <a class="nut nut-phu li-ti" href="<?= Util::h(Util::urlGiu(['sua' => $d['id']])) ?>">Sửa</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
    <?= View::phanTrang($trangSo, $tong, $moiTrang) ?>
  </div>

  <!-- ==================== Biểu mẫu ==================== -->
  <div>
    <div class="the">
      <div class="the-dau">
        <h2><?= $sua ? 'Sửa thông tin' : 'Thêm mới' ?></h2>
        <?php if ($sua): ?>
          <a class="nut nut-phu nho" href="<?= Util::h(Util::url('danh-muc', ['loai' => $loai])) ?>">Huỷ</a>
        <?php endif; ?>
      </div>

      <form method="post">
        <input type="hidden" name="csrf" value="<?= Util::h(Util::token()) ?>">
        <input type="hidden" name="viec" value="luu">
        <input type="hidden" name="id" value="<?= (int)($sua['id'] ?? 0) ?>">

        <?php if ($loai === 'truong'): ?>
          <div class="luoi-truong" style="grid-template-columns:1fr">
            <label class="truong">Mã trường <span style="color:#dc4437">*</span>
              <input type="text" name="ma_truong" required value="<?= Util::h($sua['ma_truong'] ?? '') ?>" placeholder="001">
              <span class="goi-y">Dùng trong tên tệp: <code>001_001_TAT.pdf</code></span>
            </label>
            <label class="truong">Tên trường <span style="color:#dc4437">*</span>
              <input type="text" name="ten_truong" required value="<?= Util::h($sua['ten_truong'] ?? '') ?>">
            </label>
            <label class="truong">Tên viết tắt
              <input type="text" name="ten_viet_tat" value="<?= Util::h($sua['ten_viet_tat'] ?? '') ?>">
            </label>
            <label class="truong">Cấp học
              <input type="text" name="cap_hoc" value="<?= Util::h($sua['cap_hoc'] ?? '') ?>" placeholder="THPT / GDTX">
            </label>
            <label class="truong">Địa bàn
              <input type="text" name="dia_ban" value="<?= Util::h($sua['dia_ban'] ?? '') ?>" placeholder="Biên Hòa">
            </label>
            <label class="truong">Email của trường
              <input type="email" name="email" value="<?= Util::h($sua['email'] ?? '') ?>">
              <span class="goi-y">Dùng để đoán mã trường khi tên tệp thiếu mã</span>
            </label>
            <label class="truong">Điện thoại
              <input type="text" name="dien_thoai" value="<?= Util::h($sua['dien_thoai'] ?? '') ?>">
            </label>
            <label class="truong">Người đại diện
              <input type="text" name="nguoi_dai_dien" value="<?= Util::h($sua['nguoi_dai_dien'] ?? '') ?>">
            </label>
            <label class="truong">Thứ tự hiển thị
              <input type="number" name="thu_tu" value="<?= Util::h($sua['thu_tu'] ?? 0) ?>">
            </label>
            <label class="chuyen"><input type="checkbox" name="trang_thai" value="1"
              <?= (!$sua || (int)$sua['trang_thai'] === 1) ? ' checked' : '' ?>><span>Đang hoạt động</span></label>
          </div>

        <?php elseif ($loai === 'nguoi'): ?>
          <div class="luoi-truong" style="grid-template-columns:1fr">
            <label class="truong">Mã người xử lý <span style="color:#dc4437">*</span>
              <input type="text" name="ma_nguoi_xu_ly" required value="<?= Util::h($sua['ma_nguoi_xu_ly'] ?? '') ?>"
                     placeholder="TAT" style="text-transform:uppercase">
              <span class="goi-y">Phần cuối trong tên tệp: <code>001_001_<strong>TAT</strong>.pdf</code></span>
            </label>
            <label class="truong">Bí danh khác (cách nhau dấu phẩy)
              <input type="text" name="bi_danh" value="<?= Util::h($sua['bi_danh'] ?? '') ?>" placeholder="TUAN, ATUAN">
            </label>
            <label class="truong">Họ và tên <span style="color:#dc4437">*</span>
              <input type="text" name="ho_ten" required value="<?= Util::h($sua['ho_ten'] ?? '') ?>">
            </label>
            <label class="truong">Tên đăng nhập <span style="color:#dc4437">*</span>
              <input type="text" name="ten_dang_nhap" required value="<?= Util::h($sua['ten_dang_nhap'] ?? '') ?>">
            </label>
            <label class="truong">Mật khẩu <?= $sua ? '<span class="goi-y">(để trống nếu không đổi)</span>' : '<span style="color:#dc4437">*</span>' ?>
              <input type="password" name="mat_khau" <?= $sua ? '' : 'required' ?> autocomplete="new-password">
            </label>
            <label class="chuyen"><input type="checkbox" name="buoc_doi_mk" value="1" checked>
              <span>Buộc đổi mật khẩu ở lần đăng nhập kế tiếp</span></label>
            <label class="truong">Email
              <input type="email" name="email" value="<?= Util::h($sua['email'] ?? '') ?>">
            </label>
            <label class="truong">Điện thoại
              <input type="text" name="dien_thoai" value="<?= Util::h($sua['dien_thoai'] ?? '') ?>">
            </label>
            <label class="truong">Chức vụ
              <input type="text" name="chuc_vu" value="<?= Util::h($sua['chuc_vu'] ?? '') ?>">
            </label>
            <label class="truong">Phòng ban
              <input type="text" name="phong_ban" value="<?= Util::h($sua['phong_ban'] ?? Ung::donVi()) ?>">
            </label>
            <label class="truong">Vai trò
              <select name="vai_tro">
                <?php foreach (['nguoi_xu_ly' => 'Người xử lý', 'lanh_dao' => 'Lãnh đạo', 'admin' => 'Quản trị hệ thống'] as $k => $v): ?>
                  <option value="<?= Util::h($k) ?>"<?= ($sua['vai_tro'] ?? '') === $k ? ' selected' : '' ?>><?= Util::h($v) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="chuyen"><input type="checkbox" name="nhan_tat_ca" value="1"
              <?= !empty($sua['nhan_tat_ca']) ? ' checked' : '' ?>><span>Được xem toàn bộ văn bản</span></label>
            <label class="chuyen"><input type="checkbox" name="trang_thai" value="1"
              <?= (!$sua || (int)$sua['trang_thai'] === 1) ? ' checked' : '' ?>><span>Tài khoản đang hoạt động</span></label>
          </div>

        <?php else: ?>
          <div class="luoi-truong" style="grid-template-columns:1fr">
            <label class="truong">Mã văn bản <span style="color:#dc4437">*</span>
              <input type="text" name="ma_van_ban" required value="<?= Util::h($sua['ma_van_ban'] ?? '') ?>" placeholder="001">
              <span class="goi-y">Phần giữa trong tên tệp: <code>001_<strong>001</strong>_TAT.pdf</code></span>
            </label>
            <label class="truong">Tên văn bản / công việc <span style="color:#dc4437">*</span>
              <input type="text" name="ten_van_ban" required value="<?= Util::h($sua['ten_van_ban'] ?? '') ?>">
            </label>
            <label class="truong">Loại
              <select name="loai_vb">
                <?php foreach (['bao_cao' => 'Báo cáo', 'cong_viec' => 'Công việc', 'khac' => 'Khác'] as $k => $v): ?>
                  <option value="<?= Util::h($k) ?>"<?= ($sua['loai'] ?? '') === $k ? ' selected' : '' ?>><?= Util::h($v) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="truong">Kỳ báo cáo
              <select name="ky_bao_cao">
                <?php foreach (['khong','ngay','tuan','thang','quy','hoc_ky','nam','dot'] as $k): ?>
                  <option value="<?= Util::h($k) ?>"<?= ($sua['ky_bao_cao'] ?? '') === $k ? ' selected' : '' ?>>
                    <?= Util::h(Util::tenKyBaoCao($k)) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="truong">Hạn nộp
              <input type="date" name="han_nop" value="<?= Util::h($sua['han_nop'] ?? '') ?>">
            </label>
            <label class="truong">Người xử lý mặc định
              <select name="id_nxl">
                <option value="">— Không đặt —</option>
                <?php foreach ($dsNguoiChon as $n): ?>
                  <option value="<?= (int)$n['id'] ?>"<?= (int)($sua['id_nguoi_xu_ly_mac_dinh'] ?? 0) === (int)$n['id'] ? ' selected' : '' ?>>
                    <?= Util::h($n['ma_nguoi_xu_ly'] . ' — ' . $n['ho_ten']) ?></option>
                <?php endforeach; ?>
              </select>
              <span class="goi-y">Dùng khi tên tệp thiếu mã người xử lý</span>
            </label>
            <label class="chuyen"><input type="checkbox" name="bat_buoc_nop" value="1"
              <?= !empty($sua['bat_buoc_nop']) ? ' checked' : '' ?>>
              <span>Đưa vào thống kê nộp / chưa nộp</span></label>
            <label class="truong">Phạm vi áp dụng
              <select name="pham_vi" id="pham_vi">
                <option value="tat_ca"<?= ($sua['pham_vi'] ?? 'tat_ca') === 'tat_ca' ? ' selected' : '' ?>>Tất cả các trường</option>
                <option value="chon_loc"<?= ($sua['pham_vi'] ?? '') === 'chon_loc' ? ' selected' : '' ?>>Chỉ một số trường</option>
              </select>
            </label>
            <label class="truong">Chọn trường áp dụng (khi chọn lọc)
              <select name="truong_ap_dung[]" multiple size="7">
                <?php $chon = $sua['truong_ap_dung'] ?? []; foreach ($dsTruongChon as $t): ?>
                  <option value="<?= (int)$t['id'] ?>"<?= in_array((string)$t['id'], array_map('strval', $chon), true) ? ' selected' : '' ?>>
                    <?= Util::h($t['ma_truong'] . ' — ' . $t['ten_truong']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="truong">Mô tả
              <textarea name="mo_ta"><?= Util::h($sua['mo_ta'] ?? '') ?></textarea>
            </label>
            <label class="chuyen"><input type="checkbox" name="trang_thai" value="1"
              <?= (!$sua || (int)$sua['trang_thai'] === 1) ? ' checked' : '' ?>><span>Đang sử dụng</span></label>
          </div>
        <?php endif; ?>

        <div class="hang-nut" style="margin-top:1rem">
          <button type="submit" class="nut nut-chinh"><?= $sua ? 'Lưu thay đổi' : 'Thêm mới' ?></button>
          <?php if ($sua): ?>
            <button type="submit" name="viec" value="xoa" class="nut nut-do"
                    data-hoi="Xoá mục này khỏi danh mục?">Xoá</button>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <?php if ($loai === 'truong'): ?>
    <div class="the">
      <h2>Nhập danh sách trường hàng loạt</h2>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= Util::h(Util::token()) ?>">
        <input type="hidden" name="viec" value="nhap_hang_loat">
        <label class="truong">
          Mỗi dòng một trường, các cột cách nhau bằng dấu <code>Tab</code> hoặc <code>;</code>
          <textarea name="du_lieu" rows="7"
                    placeholder="001&#9;THPT Chuyên Lương Thế Vinh&#9;THPT&#9;Biên Hòa&#9;email@thpt.edu.vn"></textarea>
          <span class="goi-y">Thứ tự cột: mã trường; tên trường; cấp học; địa bàn; email.
            Mã đã tồn tại sẽ được cập nhật.</span>
        </label>
        <button type="submit" class="nut nut-phu" style="margin-top:.7rem">Nhập danh sách</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>
