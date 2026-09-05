<?php /** Giao diện: Danh sách văn bản (hộp việc / tất cả) */ ?>

<form class="the" method="get" id="form-loc">
  <input type="hidden" name="t" value="<?= Util::h(View::$trangHienTai) ?>">
  <div class="thanh-loc" style="margin-bottom:0">
    <label class="truong dai">
      Tìm kiếm
      <input type="search" name="q" value="<?= Util::h($loc['tu_khoa']) ?>"
             placeholder="Tiêu đề, trích yếu, mã hồ sơ, tên trường…">
    </label>
    <label class="truong">
      Trạng thái
      <select name="tt" data-tu-gui>
        <option value="">— Tất cả —</option>
        <?php foreach (['cho_xu_ly', 'dang_xu_ly', 'da_xu_ly', 'tu_choi', 'cho_phan_luong', 'trung_lap'] as $x): ?>
          <option value="<?= Util::h($x) ?>"<?= $loc['trang_thai'] === $x ? ' selected' : '' ?>>
            <?= Util::h(Util::tenTrangThai($x)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="truong">
      Trường
      <select name="truong" data-tu-gui>
        <option value="">— Tất cả —</option>
        <?php foreach ($dsTruong as $t): ?>
          <option value="<?= (int)$t['id'] ?>"<?= $loc['truong'] === (int)$t['id'] ? ' selected' : '' ?>>
            <?= Util::h($t['ma_truong'] . ' — ' . Util::catChu($t['ten_truong'], 34)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="truong">
      Mã văn bản
      <select name="vb" data-tu-gui>
        <option value="">— Tất cả —</option>
        <?php foreach ($dsVanBan as $v): ?>
          <option value="<?= (int)$v['id'] ?>"<?= $loc['van_ban'] === (int)$v['id'] ? ' selected' : '' ?>>
            <?= Util::h($v['ma_van_ban'] . ' — ' . Util::catChu($v['ten_van_ban'], 34)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php if ($tatCa && $dsNguoi): ?>
    <label class="truong">
      Người xử lý
      <select name="nxl" data-tu-gui>
        <option value="">— Tất cả —</option>
        <?php foreach ($dsNguoi as $n): ?>
          <option value="<?= (int)$n['id'] ?>"<?= $loc['nguoi'] === (int)$n['id'] ? ' selected' : '' ?>>
            <?= Util::h($n['ma_nguoi_xu_ly'] . ' — ' . $n['ho_ten']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php endif; ?>
    <label class="truong">
      Từ ngày
      <input type="date" name="tu" value="<?= Util::h($loc['tu_ngay']) ?>">
    </label>
    <label class="truong">
      Đến ngày
      <input type="date" name="den" value="<?= Util::h($loc['den_ngay']) ?>">
    </label>
    <div class="hang-nut">
      <button type="submit" class="nut nut-chinh">Lọc</button>
      <a class="nut nut-phu" href="<?= Util::h(Util::url(View::$trangHienTai)) ?>">Xoá lọc</a>
    </div>
  </div>
</form>

<form method="post" id="form-hangloat">
  <input type="hidden" name="csrf" value="<?= Util::h(Util::token()) ?>">
  <input type="hidden" name="viec" value="doi_trang_thai">

  <div class="the">
    <div class="the-dau">
      <h2><?= Util::h(View::$tieuDe) ?> <span class="chu-nho chu-mo">(<?= Util::h(Util::so($tong)) ?> văn bản)</span></h2>
      <div class="hang-nut">
        <label class="chuyen" style="margin-right:.4rem">
          <input type="checkbox" data-chon-tat-ca="input[name='chon[]']"><span>Chọn tất cả</span>
        </label>
        <select name="trang_thai_moi" style="width:auto">
          <option value="">— Đổi trạng thái —</option>
          <option value="dang_xu_ly">Đang xử lý</option>
          <option value="da_xu_ly">Đã xử lý</option>
          <option value="cho_xu_ly">Chờ xử lý</option>
          <option value="tu_choi">Từ chối</option>
        </select>
        <button type="submit" class="nut nut-phu">Áp dụng</button>
      </div>
    </div>

    <?php if (!$ds): ?>
      <div class="rong">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
        </svg>
        <h3>Không tìm thấy văn bản nào</h3>
        <p>Thử bỏ bớt điều kiện lọc hoặc chờ bộ nhận mail tiếp nhận thư mới.</p>
      </div>
    <?php else: ?>
    <div class="bang-bao">
      <table class="bang">
        <thead>
          <tr>
            <th class="co-nho"></th>
            <th>Mã hồ sơ</th>
            <th>Trích yếu</th>
            <th>Trường</th>
            <?php if ($tatCa): ?><th>Người xử lý</th><?php endif; ?>
            <th class="giua">Tệp</th>
            <th>Trạng thái</th>
            <th>Ngày nhận</th>
            <th>Hạn</th>
            <th class="co-nho"></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($ds as $d):
            $quaHan = !empty($d['han_xu_ly']) && $d['han_xu_ly'] < date('Y-m-d')
                      && in_array($d['trang_thai'], ['cho_xu_ly', 'dang_xu_ly'], true); ?>
          <tr>
            <td class="co-nho"><input type="checkbox" name="chon[]" value="<?= (int)$d['id'] ?>" style="width:16px"></td>
            <td>
              <span class="ma-ho-so"><?= Util::h($d['ma_ho_so'] ?: '—') ?></span>
              <?php if ((int)$d['phien_ban'] > 1): ?>
                <div class="chu-nho" style="color:#7748e6">Bản cập nhật lần <?= Util::h($d['phien_ban']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <a href="<?= Util::h(Util::url('chi-tiet', ['id' => $d['id']])) ?>">
                <?= Util::h(Util::catChu($d['tieu_de'] ?: '(không có tiêu đề)', 110)) ?>
              </a>
              <div class="chu-nho chu-mo">
                <?= Util::h($d['ten_van_ban'] ?? '') ?> · <?= View::huyHieuNguon($d['nguon_phan_luong']) ?>
                <?php if (empty($d['da_xem'])): ?>
                  <span class="hh hh-xanh-duong hh-nhat">Chưa xem</span>
                <?php endif; ?>
              </div>
            </td>
            <td class="chu-nho"><?= Util::h(Util::catChu($d['ten_truong'] ?: ($d['ma_truong'] ?: '—'), 32)) ?></td>
            <?php if ($tatCa): ?>
              <td class="chu-nho"><?= Util::h($d['ho_ten'] ?: ($d['ma_nguoi_xu_ly'] ?: '—')) ?></td>
            <?php endif; ?>
            <td class="giua chu-nho"><?= (int)$d['so_tep'] ?></td>
            <td><?= View::huyHieuTrangThai($d['trang_thai']) ?></td>
            <td class="chu-nho" title="<?= Util::h(Util::ngay($d['ngay_nhan'])) ?>">
              <?= Util::h(Util::ngay($d['ngay_nhan'], 'd/m/Y H:i')) ?>
            </td>
            <td class="chu-nho">
              <?php if ($d['han_xu_ly']): ?>
                <span<?= $quaHan ? ' style="color:#dc4437;font-weight:600"' : '' ?>>
                  <?= Util::h(Util::ngayNgan($d['han_xu_ly'])) ?></span>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td class="co-nho">
              <a class="nut nut-phu li-ti" href="<?= Util::h(Util::url('chi-tiet', ['id' => $d['id']])) ?>">Mở</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= View::phanTrang($trangSo, $tong, $moiTrang) ?>
    <?php endif; ?>
  </div>
</form>
