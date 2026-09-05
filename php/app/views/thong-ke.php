<?php /** Giao diện: Thống kê nộp báo cáo */ ?>

<form class="the" method="get">
  <input type="hidden" name="t" value="thong-ke">
  <div class="thanh-loc" style="margin-bottom:0">
    <label class="truong dai">
      Văn bản / báo cáo cần thống kê
      <select name="vb" data-tu-gui>
        <?php foreach ($dsVanBan as $v): ?>
          <option value="<?= (int)$v['id'] ?>"<?= $idVb === (int)$v['id'] ? ' selected' : '' ?>>
            <?= Util::h($v['ma_van_ban'] . ' — ' . $v['ten_van_ban']) ?>
            <?= (int)$v['bat_buoc_nop'] ? ' (bắt buộc nộp)' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="truong">
      Địa bàn
      <select name="dia_ban" data-tu-gui>
        <option value="">— Tất cả —</option>
        <?php foreach ($dsDiaBan as $d): ?>
          <option value="<?= Util::h($d) ?>"<?= $diaBan === $d ? ' selected' : '' ?>><?= Util::h($d) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="truong">Từ ngày<input type="date" name="tu" value="<?= Util::h($tuNgay) ?>"></label>
    <label class="truong">Đến ngày<input type="date" name="den" value="<?= Util::h($denNgay) ?>"></label>
    <div class="hang-nut">
      <button type="submit" class="nut nut-chinh">Xem</button>
      <?php if ($vb): ?>
        <a class="nut nut-phu" href="<?= Util::h(Util::urlGiu(['xuat' => 'csv'])) ?>">Xuất Excel (CSV)</a>
      <?php endif; ?>
    </div>
  </div>
</form>

<?php if (!$vb): ?>
  <div class="the"><div class="rong">
    <h3>Chưa chọn được văn bản để thống kê</h3>
    <p>Hãy thêm mã văn bản trong danh mục và đánh dấu <em>bắt buộc nộp</em>.</p>
  </div></div>
<?php else:
  $tong = $daNop + $chuaNop;
  $tiLe = $tong > 0 ? $daNop * 100 / $tong : 0; ?>

<div class="luoi luoi-1-2">
  <div class="the">
    <h2>Tỉ lệ nộp</h2>
    <?= View::bieuDoTron([
        ['nhan' => 'Đã nộp',   'gia_tri' => $daNop,   'mau' => '#10a05a'],
        ['nhan' => 'Chưa nộp', 'gia_tri' => $chuaNop, 'mau' => '#dc4437'],
      ], 186, Util::phanTram($daNop, max(1, $tong), 0), 'đã nộp') ?>
    <div class="chu-thich">
      <span><i style="background:#10a05a"></i>Đã nộp (<?= Util::h($daNop) ?>)</span>
      <span><i style="background:#dc4437"></i>Chưa nộp (<?= Util::h($chuaNop) ?>)</span>
    </div>
    <hr class="tach">
    <dl class="tt-luoi">
      <dt>Mã văn bản</dt><dd><span class="ma-ho-so"><?= Util::h($vb['ma_van_ban']) ?></span></dd>
      <dt>Tên</dt><dd><?= Util::h($vb['ten_van_ban']) ?></dd>
      <dt>Kỳ báo cáo</dt><dd><?= Util::h(Util::tenKyBaoCao($vb['ky_bao_cao'])) ?></dd>
      <dt>Hạn nộp</dt><dd><?= $vb['han_nop'] ? Util::h(Util::ngayNgan($vb['han_nop'])) : '— không đặt —' ?></dd>
      <dt>Phạm vi</dt>
      <dd><?= $vb['pham_vi'] === 'chon_loc' ? 'Một số trường được chỉ định' : 'Tất cả các trường' ?></dd>
      <dt>Tổng đơn vị</dt><dd><?= Util::h($tong) ?> trường</dd>
    </dl>
  </div>

  <div class="the">
    <div class="the-dau">
      <h2>Danh sách theo trường</h2>
      <div class="chip-hang">
        <span class="chip">Đã nộp: <strong style="color:#10a05a">&nbsp;<?= Util::h($daNop) ?></strong></span>
        <span class="chip">Chưa nộp: <strong style="color:#dc4437">&nbsp;<?= Util::h($chuaNop) ?></strong></span>
      </div>
    </div>
    <div class="bang-bao cuon-doc" style="max-height:560px">
      <table class="bang bang-gon">
        <thead>
          <tr><th class="co-nho">#</th><th>Trường</th><th>Địa bàn</th><th>Tình trạng</th>
              <th>Lần nộp gần nhất</th><th class="giua">Số lần</th><th class="co-nho"></th></tr>
        </thead>
        <tbody>
        <?php $i = 1; foreach ($bang as $r): ?>
          <tr>
            <td class="chu-mo"><?= $i++ ?></td>
            <td>
              <span class="ma-ho-so"><?= Util::h($r['truong']['ma_truong']) ?></span>
              <?= Util::h(Util::catChu($r['truong']['ten_truong'], 46)) ?>
            </td>
            <td class="chu-nho chu-mo"><?= Util::h($r['truong']['dia_ban']) ?></td>
            <td>
              <?php if ($r['da_nop']): ?>
                <span class="hh hh-luc">Đã nộp</span>
                <?php if ($r['tre_han']): ?><span class="hh hh-vang hh-nhat">Trễ hạn</span><?php endif; ?>
                <?php if ($r['phien_ban'] > 1): ?>
                  <span class="hh hh-tim hh-nhat">Bản <?= Util::h($r['phien_ban']) ?></span>
                <?php endif; ?>
              <?php else: ?>
                <span class="hh hh-do">Chưa nộp</span>
              <?php endif; ?>
            </td>
            <td class="chu-nho"><?= $r['lan_cuoi'] ? Util::h(Util::ngay($r['lan_cuoi'], 'd/m/Y H:i')) : '—' ?></td>
            <td class="giua chu-nho"><?= $r['so_lan'] ?: '—' ?></td>
            <td class="co-nho">
              <?php if ($r['id_cv']): ?>
                <a class="nut nut-phu li-ti" href="<?= Util::h(Util::url('chi-tiet', ['id' => $r['id_cv']])) ?>">Xem</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="the">
  <div class="the-dau">
    <h2>Tổng hợp toàn bộ mã văn bản</h2>
    <span class="chu-nho chu-mo"><?= Util::h($tongTruongHd) ?> trường đang hoạt động</span>
  </div>
  <div class="bang-bao">
    <table class="bang bang-gon">
      <thead>
        <tr><th>Mã</th><th>Tên văn bản / công việc</th><th>Kỳ</th><th class="giua">Số văn bản</th>
            <th class="giua">Trường đã nộp</th><th style="width:190px">Tiến độ</th><th>Hạn nộp</th></tr>
      </thead>
      <tbody>
      <?php foreach ($tongHop as $r):
          $tl = $tongTruongHd > 0 ? (int)$r['so_truong_nop'] * 100 / $tongTruongHd : 0;
          $mau = $tl >= 90 ? '#10a05a' : ($tl >= 60 ? '#e08a06' : '#dc4437'); ?>
        <tr>
          <td><span class="ma-ho-so"><?= Util::h($r['ma_van_ban']) ?></span></td>
          <td>
            <a href="<?= Util::h(Util::url('thong-ke', ['vb' => $r['id']])) ?>"><?= Util::h($r['ten_van_ban']) ?></a>
            <?php if ((int)$r['bat_buoc_nop']): ?><span class="hh hh-xanh-duong hh-nhat">Bắt buộc</span><?php endif; ?>
          </td>
          <td class="chu-nho chu-mo"><?= Util::h(Util::tenKyBaoCao($r['ky_bao_cao'])) ?></td>
          <td class="giua"><?= Util::h(Util::so($r['so_van_ban'])) ?></td>
          <td class="giua"><strong><?= Util::h($r['so_truong_nop']) ?></strong>/<?= Util::h($tongTruongHd) ?></td>
          <td>
            <?= View::thanhTienDo($tl, $mau) ?>
            <span class="chu-nho"><?= Util::h(Util::phanTram((int)$r['so_truong_nop'], max(1, $tongTruongHd))) ?></span>
          </td>
          <td class="chu-nho"><?= $r['han_nop'] ? Util::h(Util::ngayNgan($r['han_nop'])) : '—' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
