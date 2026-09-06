<?php /** Giao diện: Dọn dữ liệu thử nghiệm */ ?>

<div class="nhan nhan-loi">
  <strong>Chức năng này xoá vĩnh viễn, không có nút hoàn tác.</strong>
  Chỉ dùng khi đang thử nghiệm hệ thống. Đang chạy trên cơ sở dữ liệu
  <b><?= Util::h($tenCsdl) ?></b> — xin xem lại cho đúng trước khi bấm.
  Nếu đây là dữ liệu thật thì hãy <b>sao lưu</b> (cPanel → phpMyAdmin → <i>Export</i>) trước.
</div>

<div class="luoi luoi-1-2">
  <div>
    <div class="the">
      <div class="the-dau">
        <h2>Sẽ bị xoá</h2>
        <span class="chu-nho chu-mo">Tổng nội dung tệp:
          <b><?= Util::h(Util::dungLuong($dem['dung_luong_blob'])) ?></b></span>
      </div>
      <div class="vp-cuon">
        <table class="bang">
          <thead><tr><th>Bảng</th><th>Nội dung</th><th class="phai">Số dòng</th></tr></thead>
          <tbody>
            <?php foreach ($dem['viec'] as $bang => $m): ?>
              <tr>
                <td><code><?= Util::h($bang) ?></code></td>
                <td class="chu-nho"><?= Util::h($m['nhan']) ?></td>
                <td class="phai">
                  <?php if ($m['so'] < 0): ?>
                    <span class="chu-mo chu-nho">chưa có bảng</span>
                  <?php else: ?>
                    <strong><?= Util::h(Util::so($m['so'])) ?></strong>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="the">
      <h2>Giữ nguyên, không đụng tới</h2>
      <table class="bang">
        <tbody>
          <?php foreach ($dem['giu'] as $bang => $m): ?>
            <tr>
              <td><code><?= Util::h($bang) ?></code></td>
              <td class="chu-nho"><?= Util::h($m['nhan']) ?></td>
              <td class="phai"><strong><?= Util::h(Util::so(max(0, $m['so']))) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="chu-nho chu-mo" style="margin:.7rem 0 0">
        Danh mục trường, người xử lý, mã văn bản chính thức, tài khoản đăng nhập và mọi
        thiết lập trong <i>Cài đặt</i> đều còn nguyên — dọn xong là chạy thử lại được ngay,
        không phải khai báo lại từ đầu.
      </p>
    </div>
  </div>

  <div>
    <form method="post" class="the">
      <input type="hidden" name="csrf" value="<?= Util::h(Util::token()) ?>">
      <h2>Dọn dữ liệu</h2>

      <p class="chu-nho chu-mo">Chọn thêm những phần muốn dọn cùng:</p>

      <label class="chuyen">
        <input type="checkbox" name="xoa_ma_tu_dong" value="1" checked>
        <span>Xoá mã văn bản hệ thống <b>tự thêm</b>
          (<?= Util::h(Util::so(max(0, $dem['khac']['van_ban_tu_dong']['so']))) ?> mã)</span>
      </label>
      <p class="chu-nho chu-mo" style="margin:.15rem 0 .8rem 1.7rem">
        Là các mã lạ hệ thống sinh ra trong lúc nhận thư. Mã văn bản do quản trị tự đặt
        thì không bị xoá.
      </p>

      <label class="chuyen">
        <input type="checkbox" name="xoa_nhat_ky" value="1">
        <span>Xoá luôn <b>nhật ký hệ thống</b>
          (<?= Util::h(Util::so(max(0, $dem['khac']['nhat_ky']['so']))) ?> dòng)</span>
      </label>
      <p class="chu-nho chu-mo" style="margin:.15rem 0 .8rem 1.7rem">
        Để trống nếu còn muốn xem lại nhật ký của lần chạy thử trước.
      </p>

      <label class="chuyen">
        <input type="checkbox" name="dat_lai_id" value="1" checked>
        <span>Đặt lại <b>số đếm ID</b> về 1</span>
      </label>
      <p class="chu-nho chu-mo" style="margin:.15rem 0 1.1rem 1.7rem">
        Lần thử sau đánh số từ đầu cho dễ theo dõi. Cần quyền ALTER trên cơ sở dữ liệu;
        không có quyền thì hệ thống bỏ qua chứ không báo lỗi.
      </p>

      <label class="truong">Gõ <code><?= Util::h(XAC_NHAN_DON) ?></code> để xác nhận
        <input type="text" name="xac_nhan" autocomplete="off" spellcheck="false"
               placeholder="<?= Util::h(XAC_NHAN_DON) ?>" required>
      </label>
      <p class="chu-nho chu-mo" style="margin:.3rem 0 1rem">
        Bắt gõ tay để không xoá oan vì bấm nhầm.
      </p>

      <div class="hang-nut">
        <button type="submit" class="nut nut-do">
          <?php View::manh('layout/bieu-tuong', ['ma' => 'xoa']); ?> Xoá sạch dữ liệu công việc
        </button>
        <a class="nut nut-phu" href="<?= Util::h(Util::url('cai-dat')) ?>">Thôi, quay lại</a>
      </div>
    </form>

    <div class="the">
      <h2>Sau khi dọn thì làm gì</h2>
      <ol class="chu-nho" style="margin:.2rem 0 0 1.1rem;line-height:1.7">
        <li>Mở bộ nhận mail, bấm <b>Nhận mail ngay</b> — thư sẽ được lấy lại từ đầu, vì việc
          chống trùng dựa vào bảng <code>email</code> vừa được dọn sạch.</li>
        <li>Muốn giới hạn phạm vi thử thì đặt <i>Điều kiện riêng cho lần này</i>, ví dụ
          <code>has:attachment newer_than:2d</code>.</li>
        <li>Không cần đăng nhập lại Gmail: token nằm trong <code>mailrouter.ini</code>,
          chức năng này không đụng tới.</li>
      </ol>
    </div>
  </div>
</div>
