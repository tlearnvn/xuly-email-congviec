<?php /** Giao diện: Chi tiết văn bản */ ?>

<div class="hang-nut" style="margin-bottom:1rem">
  <a class="nut nut-phu" href="<?= Util::h(Util::url(Auth::xemTatCa() ? 'van-ban' : 'hop-viec')) ?>">
    <?php View::manh('layout/bieu-tuong', ['ma' => 'quay-lai']); ?> Quay lại danh sách
  </a>
  <?php if ((int)$cv['phien_ban'] > 1): ?>
    <span class="hh hh-tim" style="align-self:center">Bản cập nhật lần <?= Util::h($cv['phien_ban']) ?></span>
  <?php endif; ?>
  <?php if (!(int)$cv['la_ban_moi_nhat']): ?>
    <span class="hh hh-xam" style="align-self:center">Đã có bản mới hơn</span>
  <?php endif; ?>
</div>

<div class="luoi luoi-2-1">
  <div>
    <!-- ---------------- Thông tin văn bản ---------------- -->
    <div class="the">
      <div class="the-dau">
        <h2><?= Util::h($cv['tieu_de'] ?: '(không có tiêu đề)') ?></h2>
        <?= View::huyHieuTrangThai($cv['trang_thai']) ?>
      </div>

      <dl class="tt-luoi">
        <dt>Mã hồ sơ</dt>
        <dd>
          <span class="ma-ho-so"><?= Util::h($cv['ma_ho_so'] ?: '—') ?></span>
          <button type="button" class="nut nut-phu li-ti" data-chep="<?= Util::h($cv['ma_ho_so']) ?>">Chép</button>
        </dd>
        <dt>Trường gửi</dt>
        <dd><?= Util::h($cv['ten_truong'] ?: '—') ?>
          <?php if ($cv['ma_truong']): ?><span class="chu-nho chu-mo">(mã <?= Util::h($cv['ma_truong']) ?>)</span><?php endif; ?>
        </dd>
        <dt>Loại văn bản</dt>
        <dd><?= Util::h($cv['ten_van_ban'] ?: '—') ?>
          <?php if ($cv['ma_van_ban']): ?><span class="chu-nho chu-mo">(mã <?= Util::h($cv['ma_van_ban']) ?>)</span><?php endif; ?>
          <?php if (!empty($cv['tu_dong_tao'])): ?>
            <span class="hh hh-vang hh-nhat">Mã tự thêm, chưa đặt tên chính thức</span>
          <?php endif; ?>
        </dd>
        <dt>Người xử lý</dt>
        <dd><?= Util::h($cv['ho_ten'] ?: ($cv['ma_nguoi_xu_ly'] ?: '— chưa phân công —')) ?></dd>
        <dt>Nguồn phân luồng</dt>
        <dd><?= View::huyHieuNguon($cv['nguon_phan_luong']) ?>
          <?php if ((float)$cv['do_tin_cay'] > 0): ?>
            <span class="chu-nho chu-mo">độ tin cậy <?= Util::h(round((float)$cv['do_tin_cay'] * 100)) ?>%</span>
          <?php endif; ?>
        </dd>
        <dt>Ngày nhận</dt><dd><?= Util::h(Util::ngay($cv['ngay_nhan'], 'd/m/Y H:i')) ?> <span class="chu-nho chu-mo">(giờ Việt Nam)</span></dd>
        <?php if (!empty($email['tu_spam'])): ?>
          <dt>Nơi nhận</dt>
          <dd><span class="hh hh-vang">Hộp Thư rác</span>
            <span class="chu-nho chu-mo">Google xếp nhầm — thư vẫn được nhận đủ</span></dd>
        <?php endif; ?>
        <dt>Hạn xử lý</dt>
        <dd><?= $cv['han_xu_ly'] ? Util::h(Util::ngayNgan($cv['han_xu_ly'])) : '—' ?></dd>
        <?php if ($cv['ngay_xu_ly']): ?>
          <dt>Ngày xử lý</dt><dd><?= Util::h(Util::ngay($cv['ngay_xu_ly'])) ?></dd>
        <?php endif; ?>
        <?php if (!empty($cv['ghi_chu'])): ?>
          <dt>Ghi chú</dt><dd class="chu-nho"><?= nl2br(Util::h($cv['ghi_chu'])) ?></dd>
        <?php endif; ?>
        <?php if (!empty($cv['ket_qua'])): ?>
          <dt>Kết quả</dt><dd><?= nl2br(Util::h($cv['ket_qua'])) ?></dd>
        <?php endif; ?>
      </dl>

      <?php if (!empty($email['ghi_chu_ai'])): ?>
        <div class="hop-ai" style="margin-top:1rem">
          <strong>Trợ lý AI:</strong> <?= Util::h($email['ghi_chu_ai']) ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($email['ly_do_trung'])): ?>
        <div class="nhan nhan-canh" style="margin-top:1rem;margin-bottom:0">
          <?= Util::h($email['ly_do_trung']) ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- ---------------- Tệp đính kèm ---------------- -->
    <div class="the">
      <div class="the-dau">
        <h2>Tệp đính kèm (<?= count($tep) ?>)</h2>
        <?php if ($tep): ?>
          <span class="chu-nho chu-mo">Tổng <?= Util::h(Util::dungLuong(array_sum(array_column($tep, 'dung_luong')))) ?></span>
        <?php endif; ?>
      </div>

      <?php if (!$tep): ?>
        <p class="rong-nho">Thư này không có tệp đính kèm.<?php
          if ($email && Util::dsLinkChiaSe($email['lien_ket_ngoai'] ?? null)) {
              echo ' Xem mục “Link chia sẻ trong thư” bên dưới.';
          } ?></p>
      <?php else: ?>
      <div class="ds-tep">
        <?php foreach ($tep as $f):
            $bt = Util::bieuTuongTep($f['ten_tep']);
            $coDl = (int)$f['co_du_lieu'] === 1;
            $xemDuoc = $coDl && Util::xemTrucTiep($f['kieu_mime'], $f['ten_tep']);
            $duoiOff = $coDl ? Util::duoiBoDocOffice($f['kieu_mime'], $f['ten_tep']) : ''; ?>
          <div class="tep">
            <span class="bt bt-<?= Util::h($bt) ?>">
              <?= Util::h(strtoupper(substr(pathinfo($f['ten_tep'], PATHINFO_EXTENSION) ?: '?', 0, 4))) ?>
            </span>
            <span class="ten">
              <strong><?= Util::h($f['ten_tep']) ?></strong>
              <small>
                <?= Util::h(Util::dungLuong($f['dung_luong'])) ?>
                <?php if (!empty($f['doc_duoc_ma'])): ?>
                  · đọc được mã <span class="ma-ho-so"><?= Util::h(($f['ma_truong'] ?: '?') . '_' . ($f['ma_van_ban'] ?: '?') . '_' . ($f['ma_nguoi_xu_ly'] ?: '?')) ?></span>
                <?php endif; ?>
                <?php if ((int)$f['co_du_lieu'] !== 1): ?>
                  · <span style="color:#dc4437">chưa tải được nội dung</span>
                <?php endif; ?>
              </small>
            </span>
            <span class="viec">
              <?php if ($xemDuoc): ?>
                <a class="nut nut-phu nho" target="_blank" rel="noopener"
                   href="<?= Util::h(Util::url('xem', ['id' => $f['id']])) ?>">
                  <?php View::manh('layout/bieu-tuong', ['ma' => 'xem']); ?> Xem
                </a>
              <?php elseif ($duoiOff !== ''): ?>
                <a class="nut nut-phu nho"
                   href="<?= Util::h(Util::url('xem-office', ['id' => $f['id'], 'cv' => $cv['id']])) ?>">
                  <?php View::manh('layout/bieu-tuong', ['ma' => 'xem']); ?> Xem
                </a>
              <?php endif; ?>
              <?php if ($coDl): ?>
                <a class="nut nut-chinh nho" href="<?= Util::h(Util::url('tai', ['id' => $f['id']])) ?>">
                  <?php View::manh('layout/bieu-tuong', ['ma' => 'tai']); ?> Tải về
                </a>
              <?php endif; ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- ---------------- Link chia sẻ trong thân thư ---------------- -->
    <?= $email ? View::hopLinkChiaSe($email['lien_ket_ngoai'] ?? null, !$tep) : '' ?>

    <!-- ---------------- Nội dung thư gốc ---------------- -->
    <?php if ($email): ?>
    <div class="the">
      <h2>Nội dung thư gốc</h2>
      <dl class="tt-luoi" style="margin-bottom:.9rem">
        <dt>Người gửi</dt>
        <dd><?= Util::h($email['ten_nguoi_gui'] ?: '') ?>
          <span class="chu-nho chu-mo">&lt;<?= Util::h($email['nguoi_gui']) ?>&gt;</span></dd>
        <dt>Người nhận</dt><dd class="chu-nho"><?= Util::h(Util::catChu($email['nguoi_nhan'], 200)) ?></dd>
        <dt>Ngày gửi</dt><dd><?= Util::h(Util::ngay($email['ngay_gui'])) ?></dd>
        <dt>Mã thư Gmail</dt><dd class="chu-nho chu-mo"><?= Util::h($email['gmail_message_id']) ?></dd>
      </dl>
      <?php if (trim((string)$email['noi_dung_text']) !== ''): ?>
        <pre class="noi-dung-mail"><?= Util::h($email['noi_dung_text']) ?></pre>
      <?php else: ?>
        <p class="rong-nho">Thư không có phần nội dung dạng văn bản.</p>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- ---------------- Cột phải ---------------- -->
  <div>
    <div class="the">
      <h2>Cập nhật xử lý</h2>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= Util::h(Util::token()) ?>">
        <input type="hidden" name="viec" value="trang_thai">
        <label class="truong" style="margin-bottom:.7rem">
          Trạng thái
          <select name="trang_thai">
            <?php foreach (['cho_xu_ly', 'dang_xu_ly', 'da_xu_ly', 'tu_choi'] as $x): ?>
              <option value="<?= Util::h($x) ?>"<?= $cv['trang_thai'] === $x ? ' selected' : '' ?>>
                <?= Util::h(Util::tenTrangThai($x)) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="truong" style="margin-bottom:.8rem">
          Kết quả xử lý
          <textarea name="ket_qua" placeholder="Ghi lại kết quả, số văn bản trả lời…"><?= Util::h($cv['ket_qua']) ?></textarea>
        </label>
        <button type="submit" class="nut nut-chinh" style="width:100%;justify-content:center">Lưu trạng thái</button>
      </form>
    </div>

    <div class="the">
      <h2>Ghi chú &amp; hạn xử lý</h2>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= Util::h(Util::token()) ?>">
        <input type="hidden" name="viec" value="ghi_chu">
        <label class="truong" style="margin-bottom:.7rem">
          Mức độ
          <select name="muc_do">
            <?php foreach (['thuong' => 'Thường', 'khan' => 'Khẩn', 'hoa_toc' => 'Hoả tốc'] as $k => $v): ?>
              <option value="<?= Util::h($k) ?>"<?= $cv['muc_do'] === $k ? ' selected' : '' ?>><?= Util::h($v) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="truong" style="margin-bottom:.7rem">
          Hạn xử lý
          <input type="date" name="han_xu_ly" value="<?= Util::h($cv['han_xu_ly']) ?>">
        </label>
        <label class="truong" style="margin-bottom:.8rem">
          Ghi chú
          <textarea name="ghi_chu"><?= Util::h($cv['ghi_chu']) ?></textarea>
        </label>
        <button type="submit" class="nut nut-phu" style="width:100%;justify-content:center">Lưu ghi chú</button>
      </form>
    </div>

    <?php if (Auth::laAdmin() && $dsNguoi): ?>
    <div class="the">
      <h2>Chuyển người xử lý</h2>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= Util::h(Util::token()) ?>">
        <input type="hidden" name="viec" value="chuyen">
        <label class="truong" style="margin-bottom:.8rem">
          Chuyển cho
          <select name="id_nguoi_moi" required>
            <option value="">— Chọn người xử lý —</option>
            <?php foreach ($dsNguoi as $n): ?>
              <option value="<?= (int)$n['id'] ?>"<?= (int)$cv['id_nguoi_xu_ly'] === (int)$n['id'] ? ' selected' : '' ?>>
                <?= Util::h($n['ma_nguoi_xu_ly'] . ' — ' . $n['ho_ten']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button type="submit" class="nut nut-phu" style="width:100%;justify-content:center">Chuyển văn bản</button>
      </form>
    </div>
    <?php endif; ?>

    <?php if (count($phienBan) > 1): ?>
    <div class="the">
      <h2>Các phiên bản của hồ sơ</h2>
      <table class="bang bang-gon" style="min-width:0">
        <tbody>
        <?php foreach ($phienBan as $p): ?>
          <tr<?= (int)$p['id'] === (int)$cv['id'] ? ' style="background:#eef3ff"' : '' ?>>
            <td>
              <a href="<?= Util::h(Util::url('chi-tiet', ['id' => $p['id']])) ?>">Phiên bản <?= Util::h($p['phien_ban']) ?></a>
              <?php if ((int)$p['la_ban_moi_nhat']): ?><span class="hh hh-luc hh-nhat">Mới nhất</span><?php endif; ?>
              <div class="chu-nho chu-mo"><?= Util::h(Util::ngay($p['ngay_nhan'])) ?></div>
            </td>
            <td class="phai chu-nho">
              <?= (int)$p['so_tep'] ?> tệp<br>
              <?= Util::h(Util::dungLuong($p['tong_dung_luong'])) ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p class="chu-nho chu-mo" style="margin-top:.5rem">
        Các bản có cùng nội dung thư nhưng tệp đính kèm khác nhau được ghi nhận thành nhiều phiên bản.
      </p>
    </div>
    <?php endif; ?>
  </div>
</div>
