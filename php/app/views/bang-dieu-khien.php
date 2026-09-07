<?php /** Giao diện: Bảng điều khiển */ ?>

<div class="the-so">
  <div class="o-so">
    <span class="bt bt-xanh"><?php View::manh('layout/bieu-tuong', ['ma' => 'vb']); ?></span>
    <span class="noi">
      <b><?= Util::h(Util::so($so['tong'])) ?></b>
      <span><?= $xemTatCa ? 'Tổng văn bản trong hệ thống' : 'Văn bản của tôi' ?></span>
      <small><?= Util::h(Util::so($so['thang_nay'])) ?> văn bản trong tháng này</small>
    </span>
  </div>
  <div class="o-so">
    <span class="bt bt-vang"><?php View::manh('layout/bieu-tuong', ['ma' => 'hv']); ?></span>
    <span class="noi">
      <b><?= Util::h(Util::so($so['cho_xu_ly'] + $so['dang_xu_ly'])) ?></b>
      <span>Đang chờ xử lý</span>
      <small><?= Util::h(Util::so($so['dang_xu_ly'])) ?> đang xử lý ·
             <?= Util::h(Util::so($so['qua_han'])) ?> quá hạn</small>
    </span>
  </div>
  <div class="o-so">
    <span class="bt bt-luc"><?php View::manh('layout/bieu-tuong', ['ma' => 'ok']); ?></span>
    <span class="noi">
      <b><?= Util::h(Util::so($so['da_xu_ly'])) ?></b>
      <span>Đã xử lý xong</span>
      <small>Tỉ lệ hoàn thành <?= Util::h(Util::phanTram($so['da_xu_ly'], max(1, $so['tong']))) ?></small>
    </span>
  </div>
  <div class="o-so">
    <span class="bt <?= $so['cho_phan_luong'] > 0 ? 'bt-do' : 'bt-xam' ?>">
      <?php View::manh('layout/bieu-tuong', ['ma' => 'pl']); ?>
    </span>
    <span class="noi">
      <b><?= Util::h(Util::so($so['cho_phan_luong'])) ?></b>
      <span>Chờ phân luồng tay</span>
      <small><?php if ($laAdmin && $so['cho_phan_luong'] > 0): ?>
        <a href="<?= Util::h(Util::url('phan-luong')) ?>">Xử lý ngay &rarr;</a>
      <?php else: ?>Mail không đọc được mã<?php endif; ?></small>
    </span>
  </div>
</div>

<div class="luoi luoi-2-1">
  <div>
    <div class="the">
      <div class="the-dau">
        <h2>Lượng văn bản tiếp nhận 6 tháng gần nhất</h2>
        <span class="chu-nho">Theo ngày nhận thư · giờ Việt Nam</span>
      </div>
      <?= View::bieuDoCot($theoThang, Ung::mau(), 190) ?>
    </div>

    <div class="the">
      <div class="the-dau">
        <h2>Tiến độ nộp báo cáo của các trường</h2>
        <a class="nut nut-phu nho" href="<?= Util::h(Util::url('thong-ke')) ?>">Xem chi tiết</a>
      </div>
      <?php if (!$baoCao): ?>
        <p class="rong-nho">Chưa có mã văn bản nào được đánh dấu <em>bắt buộc nộp</em>.
          <?php if ($laAdmin): ?>
            <a href="<?= Util::h(Util::url('danh-muc-van-ban')) ?>">Thiết lập trong danh mục mã văn bản</a>.
          <?php endif; ?>
        </p>
      <?php else: ?>
        <div class="bang-bao">
          <table class="bang bang-gon">
            <thead>
              <tr>
                <th>Mã</th><th>Tên văn bản / báo cáo</th><th class="giua">Đã nộp</th>
                <th class="giua">Chưa nộp</th><th style="width:180px">Tiến độ</th><th>Hạn nộp</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($baoCao as $b):
                $mau = $b['ti_le'] >= 90 ? '#10a05a' : ($b['ti_le'] >= 60 ? '#e08a06' : '#dc4437'); ?>
              <tr>
                <td><span class="ma-ho-so"><?= Util::h($b['ma']) ?></span></td>
                <td>
                  <a href="<?= Util::h(Util::url('thong-ke', ['vb' => $b['id']])) ?>"><?= Util::h($b['ten']) ?></a>
                  <div class="chu-nho chu-mo"><?= Util::h(Util::tenKyBaoCao($b['ky'])) ?></div>
                </td>
                <td class="giua"><strong style="color:#10a05a"><?= Util::h($b['da_nop']) ?></strong></td>
                <td class="giua"><strong style="color:<?= $b['chua_nop'] > 0 ? '#dc4437' : '#8b95a8' ?>"><?= Util::h($b['chua_nop']) ?></strong></td>
                <td>
                  <?= View::thanhTienDo($b['ti_le'], $mau) ?>
                  <span class="chu-nho"><?= Util::h(Util::phanTram($b['da_nop'], max(1, $tongTruong))) ?>
                    của <?= Util::h($tongTruong) ?> trường</span>
                </td>
                <td class="chu-nho"><?= $b['han_nop'] ? Util::h(Util::ngayNgan($b['han_nop'])) : '—' ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="the">
      <div class="the-dau">
        <h2>Văn bản tiếp nhận gần đây</h2>
        <a class="nut nut-phu nho" href="<?= Util::h(Util::url($xemTatCa ? 'van-ban' : 'hop-viec')) ?>">Xem tất cả</a>
      </div>
      <?php View::manh('phan/bang-cong-viec', ['ds' => $ganDay, 'gon' => true, 'hienNguoi' => $xemTatCa]); ?>
    </div>
  </div>

  <div>
    <div class="the">
      <h2>Phân bổ theo trạng thái</h2>
      <?php if (!$mucTron): ?>
        <p class="rong-nho">Chưa có dữ liệu.</p>
      <?php else: ?>
        <?= View::bieuDoTron($mucTron, 176, Util::so($so['tong']), 'văn bản') ?>
        <div class="chu-thich">
          <?php foreach ($mucTron as $m): ?>
            <span><i style="background:<?= Util::h($m['mau']) ?>"></i><?= Util::h($m['nhan']) ?>
              (<?= Util::h(Util::so($m['gia_tri'])) ?>)</span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($phien): ?>
    <div class="the">
      <h2>Phiên nhận mail gần nhất</h2>
      <dl class="tt-luoi">
        <dt>Bắt đầu</dt><dd><?= Util::h(Util::ngay($phien['bat_dau'])) ?></dd>
        <dt>Kết thúc</dt><dd><?= $phien['ket_thuc'] ? Util::h(Util::ngay($phien['ket_thuc'])) : '<em>đang chạy…</em>' ?></dd>
        <dt>Hộp thư</dt><dd><?= Util::h($phien['hop_thu'] ?: '—') ?></dd>
        <dt>Máy chạy</dt><dd><?= Util::h($phien['may_chu'] ?: '—') ?></dd>
        <dt>Đã quét</dt><dd><?= Util::h(Util::so($phien['so_mail_quet'])) ?> thư</dd>
        <dt>Mới / bản mới</dt>
        <dd><strong style="color:#10a05a"><?= Util::h($phien['so_mail_moi']) ?></strong> /
            <strong style="color:#7748e6"><?= Util::h($phien['so_mail_ban_moi']) ?></strong></dd>
        <dt>Trùng bỏ qua</dt><dd><?= Util::h(Util::so($phien['so_mail_trung'])) ?></dd>
        <dt>Dùng AI</dt><dd><?= Util::h(Util::so($phien['so_dung_ai'])) ?> lần</dd>
        <dt>Lỗi</dt>
        <dd><?= (int)$phien['so_loi'] > 0
              ? '<strong style="color:#dc4437">' . Util::h($phien['so_loi']) . '</strong>'
              : '<span class="chu-mo">0</span>' ?></dd>
      </dl>
      <?php if (!empty($phien['thong_diep'])): ?>
        <p class="chu-nho chu-mo" style="margin-top:.6rem"><?= Util::h(Util::catChu($phien['thong_diep'], 300)) ?></p>
      <?php endif; ?>

      <?php /* Điều kiện lọc không chặt theo tệp là kiểu bỏ sót im lặng: chạy vẫn
               ra kết quả, không báo lỗi, mà có thể thiếu thư. Nhắc ở đây vì đây
               là trang quản trị xem hằng ngày, không phải trang Nhật ký. */ ?>
      <?php if (!empty($phien['truy_van']) && !Util::truyVanCoLocTep($phien['truy_van'])): ?>
        <div class="nhan nhan-canh" style="margin:.8rem 0 0;font-size:.82rem">
          <strong>Điều kiện lọc không lọc theo tệp đính kèm.</strong>
          Phiên vừa rồi chạy với <code><?= Util::h(Util::catChu($phien['truy_van'], 120)) ?></code>,
          nên Gmail trả về mọi thư trong khoảng đó. Thư có tệp vẫn nhận được, nhưng hạn mức
          <i>Số mail mỗi lần quét</i> bị tiêu vào cả thư không liên quan — hộp thư đông thì báo
          cáo cũ hơn có thể bị bỏ sót mà <b>không báo lỗi</b>.
          Nên thêm <code>has:attachment</code> vào <i>Điều kiện tìm kiếm của Gmail</i> trong bộ
          nhận mail; hệ thống tự nới ra để bắt cả thư dán link Drive và tệp lớn Gmail tự chuyển.
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="the">
      <h2>Kho dữ liệu</h2>
      <dl class="tt-luoi">
        <dt>Email đã lưu</dt><dd><?= Util::h(Util::so($so['tong_email'])) ?></dd>
        <dt>Tệp đính kèm</dt><dd><?= Util::h(Util::so($so['tong_tep'])) ?></dd>
        <dt>Dung lượng thực</dt><dd><?= Util::h(Util::dungLuong($so['dung_luong'])) ?></dd>
      </dl>
      <p class="chu-nho chu-mo" style="margin-top:.55rem">
        Tệp trùng nội dung chỉ được lưu một lần trong cơ sở dữ liệu để tiết kiệm dung lượng.
      </p>
    </div>

    <?php if ($topNguoi): ?>
    <div class="the">
      <h2>Khối lượng theo người xử lý</h2>
      <table class="bang bang-gon" style="min-width:0">
        <tbody>
        <?php foreach ($topNguoi as $n): $t = (int)$n['tong']; $x = (int)$n['da_xong']; ?>
          <tr>
            <td>
              <strong><?= Util::h($n['ho_ten']) ?></strong>
              <div class="chu-nho chu-mo"><?= Util::h($n['ma_nguoi_xu_ly']) ?></div>
            </td>
            <td style="width:110px">
              <?= View::thanhTienDo($t > 0 ? $x * 100 / $t : 0) ?>
              <span class="chu-nho"><?= Util::h($x) ?>/<?= Util::h($t) ?> xong</span>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
