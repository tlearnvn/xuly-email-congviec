<?php /** Giao diện: Xem trực tiếp tệp Word / Excel / PowerPoint */ ?>
<?php
$nhan  = Util::tenUngDungOffice($duoi);
$laCu  = Util::laOfficeDoiCu($duoi);
$tepJs = $laCu ? 'xem-office-cu.js' : 'xem-office.js';
$bien  = $laCu ? 'XemOfficeCu' : 'XemOffice';
?>

<div class="the">
  <div class="the-dau">
    <div>
      <h2 style="margin-bottom:.15rem"><?= Util::h($f['ten_tep']) ?></h2>
      <span class="chu-nho chu-mo">
        <?= Util::h($nhan) ?><?= $laCu ? ' 97-2003' : '' ?> · <?= Util::h(Util::dungLuong($f['dung_luong'])) ?>
        <?php if (!empty($f['doc_duoc_ma'])): ?>
          · mã <span class="ma-ho-so"><?= Util::h(($f['ma_truong'] ?: '?') . '_' . ($f['ma_van_ban'] ?: '?') . '_' . ($f['ma_nguoi_xu_ly'] ?: '?')) ?></span>
        <?php endif; ?>
      </span>
    </div>
    <div class="hang-nut">
      <?php if ($idCv): ?>
        <a class="nut nut-phu nho" href="<?= Util::h(Util::url('chi-tiet', ['id' => $idCv])) ?>">
          <?php View::manh('layout/bieu-tuong', ['ma' => 'quay-lai']); ?> Quay lại văn bản
        </a>
      <?php endif; ?>
      <a class="nut nut-chinh nho" href="<?= Util::h(Util::url('tai', ['id' => $id])) ?>">
        <?php View::manh('layout/bieu-tuong', ['ma' => 'tai']); ?> Tải về máy
      </a>
    </div>
  </div>

  <div class="nhan nhan-tin" id="vp-trangthai">Đang mở tệp, xin chờ…</div>

  <div class="nhan nhan-canh" id="vp-loi" hidden></div>

  <div id="vp-noidung" class="vp-khung"></div>

  <p class="chu-nho chu-mo" id="vp-chan" hidden>
    <?php if ($laCu): ?>
      Đây là tệp <b><?= Util::h($nhan) ?> 97-2003</b> — định dạng nhị phân đời cũ, nên bản xem
      nhanh chỉ dựng lại được <b>chữ và bảng số liệu</b>, không giữ định dạng đẹp, ảnh hay biểu
      đồ. Nếu tệp được gõ bằng phông VNI/TCVN3 đời cũ (font ABC) thì dấu tiếng Việt sẽ hiện sai,
      vì chữ trong tệp không phải Unicode.
      Nên nhắc đơn vị lưu lại thành <b>.<?= Util::h($duoi) ?>x</b> (mở bằng <?= Util::h($nhan) ?>
      → <i>Lưu thành</i>) cho những lần sau.
    <?php else: ?>
      Bản xem nhanh dựng lại từ nội dung tệp nên có thể khác đôi chút về bố cục,
      phông chữ và màu sắc.
    <?php endif; ?>
    Cần bản chuẩn xác để in hoặc ký thì hãy
    <a href="<?= Util::h(Util::url('tai', ['id' => $id])) ?>">tải về máy</a> rồi mở bằng
    <?= Util::h($nhan) ?>. Nội dung tệp <b>không</b> được gửi ra ngoài — trình duyệt của bạn
    tải trực tiếp từ máy chủ này rồi tự dựng lại.
  </p>
</div>

<script src="<?= Util::h(Util::goc()) ?>/assets/js/<?= Util::h($tepJs) ?>?v=<?= Util::h(PHIEN_BAN_HE_THONG . '.' . SO_BUILD_HE_THONG) ?>" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var url   = <?= json_encode(Util::url('tai', ['id' => $id]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  var duoi  = <?= json_encode($duoi) ?>;
  var boDoc = window[<?= json_encode($bien) ?>];
  var noi   = document.getElementById('vp-noidung');
  var tt    = document.getElementById('vp-trangthai');
  var oLoi  = document.getElementById('vp-loi');
  var chan  = document.getElementById('vp-chan');

  function bao(html) {
    tt.hidden = true;
    oLoi.hidden = false;
    oLoi.innerHTML = html;
  }
  if (!boDoc) { bao('Không nạp được bộ đọc tệp Office. Hãy tải lại trang.'); return; }

  boDoc.xem(url, duoi, noi, function () {
    tt.hidden = true;
    chan.hidden = false;
  }).catch(function (e) {
    bao('<b>Không mở được tệp ngay trên web:</b> ' +
        String(e && e.message ? e.message : e).replace(/[<>&]/g, '') +
        '<br>Hãy bấm <b>Tải về máy</b> ở trên rồi mở bằng ứng dụng tương ứng.');
  });
});
</script>
