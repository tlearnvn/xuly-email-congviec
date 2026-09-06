<?php
/**
 * Mảnh giao diện: Bảng danh sách công việc / văn bản
 * $ds        - danh sách bản ghi
 * $gon       - bảng rút gọn (dùng ở bảng điều khiển)
 * $hienNguoi - hiện cột người xử lý
 */
$gon = $gon ?? false;
$hienNguoi = $hienNguoi ?? true;
?>
<?php if (!$ds): ?>
  <div class="rong">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round">
      <path d="M4 7h16v12H4z"/><path d="m4 7 8 6 8-6"/>
    </svg>
    <h3>Chưa có văn bản nào</h3>
    <p>Khi bộ nhận mail tiếp nhận thư mới, văn bản sẽ xuất hiện ở đây.</p>
  </div>
<?php else: ?>
<div class="bang-bao">
  <table class="bang<?= $gon ? ' bang-gon' : '' ?>">
    <thead>
      <tr>
        <th>Mã hồ sơ</th>
        <th>Trích yếu</th>
        <th>Trường</th>
        <?php if ($hienNguoi): ?><th>Người xử lý</th><?php endif; ?>
        <th class="giua">Tệp</th>
        <th>Trạng thái</th>
        <th>Ngày nhận</th>
        <th class="co-nho"></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($ds as $d):
        $quaHan = !empty($d['han_xu_ly']) && $d['han_xu_ly'] < date('Y-m-d')
                  && in_array($d['trang_thai'], ['cho_xu_ly', 'dang_xu_ly'], true); ?>
      <tr>
        <td>
          <span class="ma-ho-so"><?= Util::h($d['ma_ho_so'] ?: '—') ?></span>
          <?php if ((int)($d['phien_ban'] ?? 1) > 1): ?>
            <div class="chu-nho" style="color:#7748e6">Phiên bản <?= Util::h($d['phien_ban']) ?></div>
          <?php endif; ?>
        </td>
        <td>
          <a href="<?= Util::h(Util::url('chi-tiet', ['id' => $d['id']])) ?>">
            <?= Util::h(Util::catChu($d['tieu_de'] ?: '(không có tiêu đề)', $gon ? 60 : 110)) ?>
          </a>
          <div class="chu-nho chu-mo">
            <?= Util::h($d['ten_van_ban'] ?? '') ?>
            <?php if (!empty($d['nguon_phan_luong'])): ?>
              · <?= View::huyHieuNguon($d['nguon_phan_luong']) ?>
            <?php endif; ?>
          </div>
        </td>
        <td class="chu-nho"><?= Util::h(Util::catChu($d['ten_truong'] ?? ($d['ma_truong'] ?: '—'), 34)) ?></td>
        <?php if ($hienNguoi): ?>
          <td class="chu-nho"><?= Util::h($d['ho_ten'] ?? ($d['ma_nguoi_xu_ly'] ?: '—')) ?></td>
        <?php endif; ?>
        <td class="giua chu-nho">
          <?= (int)($d['so_tep'] ?? 0) ?>
          <?php $soLink = count(Util::dsLinkChiaSe($d['lien_ket_ngoai'] ?? null)); ?>
          <?php if ($soLink): ?>
            <div class="hh hh-xanh-duong hh-nhat" style="margin-top:.15rem"
                 title="Thư có <?= $soLink ?> link chia sẻ; kho không giữ bản tệp của link">
              <?= $soLink ?> link
            </div>
          <?php endif; ?>
        </td>
        <td>
          <?= View::huyHieuTrangThai($d['trang_thai']) ?>
          <?php if ($quaHan): ?><div class="chu-nho" style="color:#dc4437">Quá hạn</div><?php endif; ?>
        </td>
        <td class="chu-nho" title="<?= Util::h(Util::ngay($d['ngay_nhan'])) ?>">
          <?= Util::h(Util::tuongDoi($d['ngay_nhan'])) ?>
        </td>
        <td class="co-nho">
          <a class="nut nut-phu li-ti" href="<?= Util::h(Util::url('chi-tiet', ['id' => $d['id']])) ?>">Mở</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
