<?php /** Giao diện: Nhật ký hệ thống */
$mauMuc = ['debug' => 'xam', 'info' => 'xanh-duong', 'canh_bao' => 'vang', 'loi' => 'do'];
?>

<div class="the-so">
  <?php foreach (['info' => ['Thông tin', 'xanh'], 'canh_bao' => ['Cảnh báo', 'vang'],
                  'loi' => ['Lỗi', 'do'], 'debug' => ['Gỡ lỗi', 'xam']] as $k => $v): ?>
    <div class="o-so">
      <span class="bt bt-<?= Util::h($v[1]) ?>">
        <?php View::manh('layout/bieu-tuong', ['ma' => $k === 'loi' ? 'canh' : 'nk']); ?>
      </span>
      <span class="noi">
        <b><?= Util::h(Util::so($theoMuc[$k] ?? 0)) ?></b>
        <span><?= Util::h($v[0]) ?></span>
        <small><a href="<?= Util::h(Util::url('nhat-ky', ['muc' => $k])) ?>">Chỉ xem mức này</a></small>
      </span>
    </div>
  <?php endforeach; ?>
</div>

<div class="the">
  <form method="get">
    <input type="hidden" name="t" value="nhat-ky">
    <div class="thanh-loc" style="margin-bottom:0">
      <label class="truong dai">Từ khoá
        <input type="search" name="q" value="<?= Util::h($loc['tu_khoa']) ?>" placeholder="Nội dung, hành động, người dùng…">
      </label>
      <label class="truong">Mức
        <select name="muc" data-tu-gui>
          <option value="">— Tất cả —</option>
          <?php foreach (['debug', 'info', 'canh_bao', 'loi'] as $m): ?>
            <option value="<?= Util::h($m) ?>"<?= $loc['muc'] === $m ? ' selected' : '' ?>>
              <?= Util::h(Util::tenMucNhatKy($m)) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="truong">Nguồn
        <select name="nguon" data-tu-gui>
          <option value="">— Tất cả —</option>
          <?php foreach ($dsNguon as $n): ?>
            <option value="<?= Util::h($n) ?>"<?= $loc['nguon'] === $n ? ' selected' : '' ?>><?= Util::h($n) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="truong">Từ ngày<input type="date" name="tu" value="<?= Util::h($loc['tu']) ?>"></label>
      <label class="truong">Đến ngày<input type="date" name="den" value="<?= Util::h($loc['den']) ?>"></label>
      <div class="hang-nut">
        <button type="submit" class="nut nut-chinh">Lọc</button>
        <a class="nut nut-phu" href="<?= Util::h(Util::url('nhat-ky')) ?>">Xoá lọc</a>
      </div>
    </div>
  </form>
</div>

<div class="the">
  <div class="the-dau">
    <h2>Nhật ký <span class="chu-nho chu-mo">(<?= Util::h(Util::so($tong)) ?> dòng)</span></h2>
    <form method="post" style="margin:0">
      <input type="hidden" name="csrf" value="<?= Util::h(Util::token()) ?>">
      <input type="hidden" name="viec" value="don">
      <button type="submit" class="nut nut-phu nho"
              data-hoi="Xoá các dòng nhật ký cũ hơn <?= Util::h(Ung::so('log.so_ngay_giu', 180)) ?> ngày?">
        Dọn nhật ký cũ
      </button>
    </form>
  </div>

  <?php if (!$ds): ?>
    <div class="rong"><h3>Không có dòng nhật ký nào</h3><p>Thử bỏ bớt điều kiện lọc.</p></div>
  <?php else: ?>
  <div class="bang-bao">
    <table class="bang bang-gon">
      <thead>
        <tr><th style="width:140px">Thời gian</th><th style="width:90px">Mức</th><th style="width:90px">Nguồn</th>
            <th style="width:130px">Hành động</th><th>Nội dung</th><th style="width:120px">Người dùng</th></tr>
      </thead>
      <tbody>
      <?php foreach ($ds as $d): ?>
        <tr>
          <td class="chu-nho" style="white-space:nowrap"><?= Util::h(Util::ngay($d['thoi_gian'], 'd/m/Y H:i:s')) ?></td>
          <td><span class="hh hh-<?= Util::h($mauMuc[$d['muc']] ?? 'xam') ?>"><?= Util::h(Util::tenMucNhatKy($d['muc'])) ?></span></td>
          <td class="chu-nho chu-mo"><?= Util::h($d['nguon']) ?></td>
          <td class="chu-nho"><?= Util::h($d['hanh_dong']) ?></td>
          <td>
            <?= Util::h(Util::catChu($d['noi_dung'], 240)) ?>
            <?php if ($d['doi_tuong']): ?>
              <div class="chu-nho chu-mo"><?= Util::h($d['doi_tuong']) ?>
                <?= $d['id_doi_tuong'] ? '#' . Util::h($d['id_doi_tuong']) : '' ?></div>
            <?php endif; ?>
          </td>
          <td class="chu-nho">
            <?= Util::h($d['ten_nguoi_dung'] ?: '—') ?>
            <?php if ($d['dia_chi_ip']): ?><div class="chu-mo"><?= Util::h($d['dia_chi_ip']) ?></div><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= View::phanTrang($trangSo, $tong, $moiTrang) ?>
  <?php endif; ?>
</div>

<div class="the">
  <h2>Các phiên nhận mail gần đây</h2>
  <?php if (!$phien): ?>
    <p class="rong-nho">Chưa có phiên nhận mail nào được ghi nhận.</p>
  <?php else: ?>
  <div class="bang-bao">
    <table class="bang bang-gon">
      <thead>
        <tr><th>Bắt đầu</th><th>Kết thúc</th><th>Máy chạy</th><th>Hộp thư</th>
            <th class="giua">Quét</th><th class="giua">Mới</th><th class="giua">Bản mới</th>
            <th class="giua">Trùng</th><th class="giua">Tệp</th><th class="giua">AI</th>
            <th class="giua">Chờ PL</th><th class="giua">Lỗi</th><th>Trạng thái</th></tr>
      </thead>
      <tbody>
      <?php foreach ($phien as $p): ?>
        <tr>
          <td class="chu-nho"><?= Util::h(Util::ngay($p['bat_dau'], 'd/m H:i:s')) ?></td>
          <td class="chu-nho"><?= $p['ket_thuc'] ? Util::h(Util::ngay($p['ket_thuc'], 'd/m H:i:s')) : '—' ?></td>
          <td class="chu-nho chu-mo"><?= Util::h($p['may_chu']) ?></td>
          <td class="chu-nho chu-mo"><?= Util::h(Util::catChu($p['hop_thu'], 24)) ?></td>
          <td class="giua"><?= (int)$p['so_mail_quet'] ?></td>
          <td class="giua" style="color:#10a05a;font-weight:600"><?= (int)$p['so_mail_moi'] ?></td>
          <td class="giua" style="color:#7748e6"><?= (int)$p['so_mail_ban_moi'] ?></td>
          <td class="giua chu-mo"><?= (int)$p['so_mail_trung'] ?></td>
          <td class="giua"><?= (int)$p['so_tep'] ?></td>
          <td class="giua"><?= (int)$p['so_dung_ai'] ?></td>
          <td class="giua"><?= (int)$p['so_cho_phan_luong'] ?></td>
          <td class="giua"<?= (int)$p['so_loi'] ? ' style="color:#dc4437;font-weight:600"' : '' ?>><?= (int)$p['so_loi'] ?></td>
          <td>
            <?php $lop = ['dang_chay' => 'xanh-duong', 'hoan_tat' => 'luc', 'loi' => 'do'][$p['trang_thai']] ?? 'xam'; ?>
            <span class="hh hh-<?= Util::h($lop) ?>">
              <?= Util::h(['dang_chay' => 'Đang chạy', 'hoan_tat' => 'Hoàn tất', 'loi' => 'Lỗi'][$p['trang_thai']] ?? $p['trang_thai']) ?>
            </span>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
