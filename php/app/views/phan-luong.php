<?php /** Giao diện: Hàng chờ phân luồng tay */ ?>

<?php if ($dangChon): ?>
<div class="the">
  <div class="the-dau">
    <h2>Phân luồng văn bản #<?= Util::h($dangChon['id']) ?></h2>
    <a class="nut nut-phu nho" href="<?= Util::h(Util::url('phan-luong')) ?>">Đóng</a>
  </div>

  <div class="luoi luoi-1-2">
    <div>
      <dl class="tt-luoi">
        <dt>Tiêu đề</dt><dd><strong><?= Util::h($dangChon['tieu_de'] ?: '(không có tiêu đề)') ?></strong></dd>
        <dt>Người gửi</dt>
        <dd><?= Util::h($dangChon['ten_nguoi_gui']) ?>
          <div class="chu-nho chu-mo"><?= Util::h($dangChon['nguoi_gui']) ?></div></dd>
        <dt>Ngày gửi</dt><dd><?= Util::h(Util::ngay($dangChon['ngay_gui'])) ?></dd>
        <?php if (!empty($dangChon['tu_spam'])): ?>
          <dt>Nơi nhận</dt>
          <dd><span class="hh hh-vang">Hộp Thư rác</span>
            <div class="chu-nho chu-mo">Google xếp nhầm. Nên thêm địa chỉ người gửi vào bộ lọc
              “không bao giờ cho vào Thư rác” của Gmail.</div></dd>
        <?php endif; ?>
        <dt>Mã đọc được</dt><dd><span class="ma-ho-so"><?= Util::h($dangChon['ma_ho_so'] ?: '—') ?></span></dd>
        <dt>Lý do</dt><dd class="chu-nho"><?= Util::h($dangChon['ghi_chu'] ?: 'Không đọc được mã từ tên tệp và tiêu đề.') ?></dd>
      </dl>

      <?php if ($tepChon): ?>
        <h3 style="margin-top:1.1rem">Tệp đính kèm</h3>
        <div class="ds-tep">
          <?php foreach ($tepChon as $f): ?>
            <div class="tep">
              <span class="bt bt-<?= Util::h(Util::bieuTuongTep($f['ten_tep'])) ?>">
                <?= Util::h(strtoupper(substr(pathinfo($f['ten_tep'], PATHINFO_EXTENSION) ?: '?', 0, 4))) ?>
              </span>
              <span class="ten">
                <strong><?= Util::h($f['ten_tep']) ?></strong>
                <small><?= Util::h(Util::dungLuong($f['dung_luong'])) ?></small>
              </span>
              <span class="viec">
                <?php if (Util::xemTrucTiep($f['kieu_mime'], $f['ten_tep'])): ?>
                  <a class="nut nut-phu nho" target="_blank" rel="noopener"
                     href="<?= Util::h(Util::url('xem', ['id' => $f['id']])) ?>">Xem</a>
                <?php endif; ?>
                <a class="nut nut-phu nho" href="<?= Util::h(Util::url('tai', ['id' => $f['id']])) ?>">Tải</a>
              </span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (trim((string)$dangChon['noi_dung_text']) !== ''): ?>
        <h3 style="margin-top:1.1rem">Trích nội dung thư</h3>
        <pre class="noi-dung-mail" style="max-height:220px"><?= Util::h(Util::catChu($dangChon['noi_dung_text'], 2500)) ?></pre>
      <?php endif; ?>
    </div>

    <div>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= Util::h(Util::token()) ?>">
        <input type="hidden" name="viec" value="phan_luong">
        <input type="hidden" name="id" value="<?= (int)$dangChon['id'] ?>">

        <?php if (Ai::bat()): ?>
          <div class="hang-nut" style="margin-bottom:.8rem">
            <button type="button" class="nut nut-phu" id="nut-ai"
                    data-url="<?= Util::h(Util::url('phan-luong', ['viec' => 'ai', 'id' => $dangChon['id']])) ?>">
              <?php View::manh('layout/bieu-tuong', ['ma' => 'ai']); ?> Nhờ AI đọc giúp
            </button>
          </div>
          <div class="hop-ai" id="hop-ai" hidden></div>
        <?php endif; ?>

        <?php if (!empty($dangChon['ghi_chu_ai'])): ?>
          <div class="hop-ai" style="margin-bottom:.8rem">
            <strong>AI đã phân tích khi nhận mail:</strong> <?= Util::h($dangChon['ghi_chu_ai']) ?>
          </div>
        <?php endif; ?>

        <div class="luoi-truong" style="margin-top:.8rem">
          <label class="truong">
            Mã trường <span style="color:#dc4437">*</span>
            <input list="ds-truong" name="ma_truong" id="ma_truong"
                   value="<?= Util::h($dangChon['ma_truong']) ?>" placeholder="001" required>
            <span class="goi-y">Gõ mã hoặc chọn trong danh sách</span>
          </label>
          <label class="truong">
            Mã văn bản
            <input list="ds-vanban" name="ma_van_ban" id="ma_van_ban"
                   value="<?= Util::h($dangChon['ma_van_ban']) ?>" placeholder="001">
            <span class="goi-y">Mã lạ sẽ tự được thêm vào danh mục</span>
          </label>
          <label class="truong">
            Người xử lý <span style="color:#dc4437">*</span>
            <input list="ds-nguoi" name="ma_nguoi_xu_ly" id="ma_nguoi_xu_ly"
                   value="<?= Util::h($dangChon['ma_nguoi_xu_ly']) ?>" placeholder="TAT" required>
            <span class="goi-y">Mã viết tắt của người nhận xử lý</span>
          </label>
          <label class="truong het-hang">
            Ghi chú
            <input type="text" name="ghi_chu" placeholder="Lý do phân luồng, hướng dẫn xử lý…">
          </label>
        </div>

        <label class="chuyen" style="margin:.9rem 0">
          <input type="checkbox" name="ap_dung_tuong_tu" value="1">
          <span>Áp dụng cho tất cả văn bản đang chờ từ cùng người gửi</span>
        </label>

        <div class="hang-nut">
          <button type="submit" class="nut nut-chinh">Lưu phân luồng</button>
          <button type="submit" class="nut nut-do" name="viec" value="bo_qua"
                  data-hoi="Bỏ qua văn bản này? Văn bản sẽ được đánh dấu là không cần xử lý.">Bỏ qua</button>
        </div>
      </form>
    </div>
  </div>
</div>

<datalist id="ds-truong">
  <?php foreach ($dsTruong as $t): ?>
    <option value="<?= Util::h($t['ma_truong']) ?>"><?= Util::h($t['ten_truong']) ?></option>
  <?php endforeach; ?>
</datalist>
<datalist id="ds-vanban">
  <?php foreach ($dsVanBan as $v): ?>
    <option value="<?= Util::h($v['ma_van_ban']) ?>"><?= Util::h($v['ten_van_ban']) ?></option>
  <?php endforeach; ?>
</datalist>
<datalist id="ds-nguoi">
  <?php foreach ($dsNguoi as $n): ?>
    <option value="<?= Util::h($n['ma_nguoi_xu_ly']) ?>"><?= Util::h($n['ho_ten']) ?></option>
  <?php endforeach; ?>
</datalist>
<?php endif; ?>

<div class="the">
  <div class="the-dau">
    <h2>Văn bản chờ phân luồng <span class="chu-nho chu-mo">(<?= Util::h(Util::so($tong)) ?>)</span></h2>
    <span class="chu-nho chu-mo">Các mail không đọc được mã từ tên tệp, tiêu đề và AI</span>
  </div>

  <?php if (!$ds): ?>
    <div class="rong">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>
      </svg>
      <h3>Không còn văn bản nào chờ phân luồng</h3>
      <p>Toàn bộ thư tiếp nhận đều đã được chuyển tới đúng người xử lý.</p>
    </div>
  <?php else: ?>
  <div class="bang-bao">
    <table class="bang">
      <thead>
        <tr><th>Tiêu đề</th><th>Người gửi</th><th class="giua">Tệp</th><th>Mã đọc được</th>
            <th>Ngày nhận</th><th class="co-nho"></th></tr>
      </thead>
      <tbody>
      <?php foreach ($ds as $d): ?>
        <tr<?= $dangChon && (int)$dangChon['id'] === (int)$d['id'] ? ' style="background:#eef3ff"' : '' ?>>
          <td>
            <strong><?= Util::h(Util::catChu($d['tieu_de'] ?: '(không có tiêu đề)', 90)) ?></strong>
            <div class="chu-nho chu-mo"><?= Util::h(Util::catChu($d['doan_trich'], 110)) ?></div>
          </td>
          <td class="chu-nho">
            <?= Util::h($d['ten_nguoi_gui'] ?: '') ?>
            <div class="chu-mo"><?= Util::h($d['nguoi_gui']) ?></div>
          </td>
          <td class="giua chu-nho"><?= (int)$d['so_tep'] ?></td>
          <td><span class="ma-ho-so"><?= Util::h($d['ma_ho_so'] ?: '—') ?></span></td>
          <td class="chu-nho"><?= Util::h(Util::ngay($d['ngay_nhan'], 'd/m/Y H:i')) ?></td>
          <td class="co-nho">
            <a class="nut nut-chinh li-ti" href="<?= Util::h(Util::url('phan-luong', ['id' => $d['id']])) ?>">
              Phân luồng
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= View::phanTrang($trangSo, $tong, $moiTrang) ?>
  <?php endif; ?>
</div>
