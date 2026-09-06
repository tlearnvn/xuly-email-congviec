<?php
/** Giao diện: Cài đặt hệ thống */
$g = function (string $k, $md = '') use ($ch) { return $ch[$k] ?? $md; };
$bat = function (string $k) use ($ch) {
    return in_array(strtolower((string)($ch[$k] ?? '')), ['1', 'true', 'yes', 'on'], true);
};
$nhomTen = [
    'giao_dien'   => 'Giao diện & thương hiệu',
    'phan_luong'  => 'Quy tắc phân luồng',
    'chong_trung' => 'Chống trùng lặp',
    'ai'          => 'Trợ lý AI',
    'api'         => 'Kết nối bộ nhận mail',
    'nhat_ky'     => 'Nhật ký',
];
?>

<div class="chip-hang" style="margin-bottom:1rem">
  <?php foreach ($nhomTen as $k => $v): ?>
    <a class="chip<?= $nhom === $k ? ' dang' : '' ?>" href="<?= Util::h(Util::url('cai-dat', ['nhom' => $k])) ?>">
      <?= Util::h($v) ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="luoi luoi-2-1">
  <div>
    <form method="post" class="the">
      <input type="hidden" name="csrf" value="<?= Util::h(Util::token()) ?>">
      <input type="hidden" name="viec" value="luu">
      <input type="hidden" name="nhom" value="<?= Util::h($nhom) ?>">
      <h2><?= Util::h($nhomTen[$nhom]) ?></h2>

      <?php if ($nhom === 'giao_dien'): ?>
        <div class="luoi-truong" style="grid-template-columns:1fr">
          <label class="truong">Tên ứng dụng (hiển thị đầu trang)
            <input type="text" name="ch[app.ten_ung_dung]" value="<?= Util::h($g('app.ten_ung_dung')) ?>">
          </label>
          <label class="truong">Tên rút gọn (menu, tiêu đề tab)
            <input type="text" name="ch[app.ten_ngan]" value="<?= Util::h($g('app.ten_ngan')) ?>">
          </label>
          <label class="truong">Tên đơn vị
            <input type="text" name="ch[app.don_vi]" value="<?= Util::h($g('app.don_vi')) ?>">
          </label>
          <label class="truong">Bản quyền / Copyright (chân trang)
            <input type="text" name="ch[app.ban_quyen]" value="<?= Util::h($g('app.ban_quyen')) ?>">
          </label>
          <label class="truong">Màu chủ đạo
            <input type="text" name="ch[app.mau_chu_dao]" value="<?= Util::h($g('app.mau_chu_dao')) ?>"
                   placeholder="#1e5eff" pattern="#[0-9a-fA-F]{3,8}">
            <span class="goi-y">Mã màu HEX, ví dụ <code>#1e5eff</code>, <code>#0f7b4f</code></span>
          </label>
          <label class="truong">Đường dẫn logo (tuỳ chọn)
            <input type="text" name="ch[app.logo]" value="<?= Util::h($g('app.logo')) ?>" placeholder="assets/logo.png">
          </label>
          <label class="truong">Số dòng mỗi trang
            <input type="number" name="ch[app.so_dong_moi_trang]" value="<?= Util::h($g('app.so_dong_moi_trang', 20)) ?>" min="5" max="200">
          </label>
          <label class="truong">Múi giờ
            <input type="text" name="ch[app.mui_gio]" value="<?= Util::h($g('app.mui_gio', 'Asia/Ho_Chi_Minh')) ?>" readonly>
            <span class="goi-y">Toàn hệ thống dùng giờ Việt Nam (GMT+7)</span>
          </label>
        </div>

      <?php elseif ($nhom === 'phan_luong'): ?>
        <div class="vi-du-ma" style="text-align:center;margin-bottom:1rem">
          <span class="ma-ho-so" style="font-size:1.15rem;padding:.4rem .8rem">001_001_TAT</span>
          <p class="goi-y" style="margin-top:.4rem">mã trường _ mã văn bản _ mã người xử lý</p>
        </div>
        <div class="luoi-truong" style="grid-template-columns:1fr">
          <input type="hidden" name="co_bool[]" value="phanluong.cho_phep_ma_vb_moi">
          <label class="chuyen"><input type="checkbox" name="ch[phanluong.cho_phep_ma_vb_moi]" value="1"
            <?= $bat('phanluong.cho_phep_ma_vb_moi') ? ' checked' : '' ?>>
            <span>Tự thêm mã văn bản lạ vào danh mục (nới lỏng)</span></label>
          <span class="goi-y" style="margin-top:-.4rem">Khi gặp mã văn bản chưa có, hệ thống vẫn nhận và tạo bản ghi tạm
            để thống kê; quản trị đặt tên chính thức sau.</span>

          <input type="hidden" name="co_bool[]" value="phanluong.bat_buoc_ma_truong">
          <label class="chuyen"><input type="checkbox" name="ch[phanluong.bat_buoc_ma_truong]" value="1"
            <?= $bat('phanluong.bat_buoc_ma_truong') ? ' checked' : '' ?>>
            <span>Bắt buộc mã trường phải có trong danh mục</span></label>

          <input type="hidden" name="co_bool[]" value="phanluong.bat_buoc_ma_nguoi">
          <label class="chuyen"><input type="checkbox" name="ch[phanluong.bat_buoc_ma_nguoi]" value="1"
            <?= $bat('phanluong.bat_buoc_ma_nguoi') ? ' checked' : '' ?>>
            <span>Bắt buộc mã người xử lý phải có trong danh mục</span></label>

          <label class="truong">Người xử lý mặc định
            <input type="text" name="ch[phanluong.nguoi_xu_ly_mac_dinh]"
                   value="<?= Util::h($g('phanluong.nguoi_xu_ly_mac_dinh')) ?>" placeholder="TAT">
            <span class="goi-y">Mã người nhận các mail không xác định được. Để trống = đưa vào hàng chờ phân luồng tay.</span>
          </label>
          <label class="truong">Hạn xử lý mặc định (ngày)
            <input type="number" name="ch[phanluong.han_xu_ly_ngay]"
                   value="<?= Util::h($g('phanluong.han_xu_ly_ngay', 7)) ?>" min="0" max="365">
          </label>
          <label class="truong">Ký tự phân cách nhận dạng mã
            <input type="text" name="ch[phanluong.dau_phan_cach]"
                   value="<?= Util::h($g('phanluong.dau_phan_cach', '_-. ()[]{},;+#')) ?>">
            <span class="goi-y">Mặc định nhận cả <code>_</code>, <code>-</code>, <code>.</code> và khoảng trắng.</span>
          </label>
        </div>

      <?php elseif ($nhom === 'chong_trung'): ?>
        <div class="luoi-truong" style="grid-template-columns:1fr">
          <input type="hidden" name="co_bool[]" value="trung.bat">
          <label class="chuyen"><input type="checkbox" name="ch[trung.bat]" value="1"
            <?= $bat('trung.bat') ? ' checked' : '' ?>><span>Bật kiểm tra trùng lặp</span></label>

          <input type="hidden" name="co_bool[]" value="trung.tao_ban_moi_khi_tep_khac">
          <label class="chuyen"><input type="checkbox" name="ch[trung.tao_ban_moi_khi_tep_khac]" value="1"
            <?= $bat('trung.tao_ban_moi_khi_tep_khac') ? ' checked' : '' ?>>
            <span>Trùng nội dung nhưng tệp khác ⇒ ghi nhận thành phiên bản mới</span></label>
          <span class="goi-y" style="margin-top:-.4rem">
            Nếu tắt, các thư trùng nội dung sẽ bị bỏ qua kể cả khi tệp đính kèm khác dung lượng.
          </span>

          <label class="truong">Bỏ qua tiền tố khi so tiêu đề
            <input type="text" name="ch[trung.bo_qua_tien_to]" value="<?= Util::h($g('trung.bo_qua_tien_to')) ?>">
            <span class="goi-y">Cách nhau bằng dấu phẩy, ví dụ <code>RE:,FW:,FWD:</code></span>
          </label>
          <label class="truong">Số ngày đối chiếu trùng
            <input type="number" name="ch[trung.so_ngay_doi_chieu]"
                   value="<?= Util::h($g('trung.so_ngay_doi_chieu', 365)) ?>" min="1" max="3650">
            <span class="goi-y">Chỉ so sánh với các thư nhận trong khoảng thời gian này.</span>
          </label>
        </div>
        <div class="nhan nhan-tin" style="margin-top:1rem;margin-bottom:0">
          <strong>Quy tắc đang áp dụng:</strong><br>
          1. Cùng mã thư Gmail ⇒ bỏ qua hoàn toàn.<br>
          2. Cùng nội dung (tiêu đề + người gửi + thân thư) và cùng bộ tệp ⇒ đánh dấu <em>trùng lặp</em>, không tạo việc mới.<br>
          3. Cùng nội dung nhưng tệp khác dung lượng hoặc khác nội dung ⇒ tạo <em>phiên bản mới</em>, bản cũ được đánh dấu đã thay thế.<br>
          4. Tệp có nội dung giống hệt nhau chỉ được lưu <em>một lần</em> trong cơ sở dữ liệu.
        </div>

      <?php elseif ($nhom === 'ai'): ?>
        <div class="luoi-truong" style="grid-template-columns:1fr">
          <input type="hidden" name="co_bool[]" value="ai.bat">
          <label class="chuyen"><input type="checkbox" name="ch[ai.bat]" value="1"
            <?= $bat('ai.bat') ? ' checked' : '' ?>><span>Bật trợ lý AI</span></label>
          <span class="goi-y" style="margin-top:-.4rem">
            Chỉ dùng khi không đọc được mã từ tên tệp và tiêu đề.
          </span>

          <label class="truong">Địa chỉ API (OpenAI compatible)
            <input type="text" name="ch[ai.url]" id="ai_url" value="<?= Util::h($g('ai.url')) ?>"
                   placeholder="https://api.openai.com/v1">
            <span class="goi-y">Dùng được với OpenAI, Azure OpenAI, Groq, Together, OpenRouter,
              Ollama (<code>http://localhost:11434/v1</code>), LM Studio…</span>
          </label>
          <label class="truong">API Key
            <input type="password" name="ch[ai.api_key]" id="ai_api_key"
                   placeholder="<?= $g('ai.api_key') !== '' ? '•••••••• (để trống nếu không đổi)' : 'sk-...' ?>"
                   autocomplete="new-password">
          </label>
          <label class="truong">Model
            <input type="text" name="ch[ai.model]" id="ai_model" value="<?= Util::h($g('ai.model')) ?>"
                   placeholder="gpt-4o-mini">
          </label>
          <label class="truong">Số token tối đa (≤ <?= Ai::MAX_TOKENS_TRAN ?>)
            <input type="number" name="ch[ai.max_tokens]" id="ai_max_tokens"
                   value="<?= Util::h($g('ai.max_tokens', 4096)) ?>" min="1" max="<?= Ai::MAX_TOKENS_TRAN ?>">
          </label>
          <label class="truong">Thời gian chờ (giây, ≤ <?= Ai::TIMEOUT_TRAN ?>)
            <input type="number" name="ch[ai.timeout]" id="ai_timeout"
                   value="<?= Util::h($g('ai.timeout', 60)) ?>" min="1" max="<?= Ai::TIMEOUT_TRAN ?>">
          </label>
          <label class="truong">Temperature
            <input type="number" name="ch[ai.temperature]" id="ai_temperature"
                   value="<?= Util::h($g('ai.temperature', '0.1')) ?>" step="0.1" min="0" max="2">
          </label>
          <label class="truong">Ngưỡng tin cậy (0 – 1)
            <input type="number" name="ch[ai.nguong_tin_cay]"
                   value="<?= Util::h($g('ai.nguong_tin_cay', '0.6')) ?>" step="0.05" min="0" max="1">
            <span class="goi-y">Dưới ngưỡng này, văn bản được đưa vào hàng chờ phân luồng tay.</span>
          </label>
          <label class="truong">Câu nhắc hệ thống
            <textarea name="ch[ai.nhac_he_thong]" rows="4"><?= Util::h($g('ai.nhac_he_thong')) ?></textarea>
          </label>
        </div>
        <div class="hang-nut" style="margin-top:1rem">
          <button type="submit" class="nut nut-chinh">Lưu thiết lập</button>
          <button type="button" class="nut nut-phu" id="nut-test-ai"
                  data-url="<?= Util::h(Util::url('cai-dat')) ?>" data-csrf="<?= Util::h(Util::token()) ?>">
            Kiểm tra kết nối AI
          </button>
        </div>
        <div id="kq-test-ai" class="nhan nhan-tin" style="margin-top:.9rem" hidden></div>

      <?php elseif ($nhom === 'api'): ?>
        <div class="luoi-truong" style="grid-template-columns:1fr">
          <input type="hidden" name="co_bool[]" value="api.bat">
          <label class="chuyen"><input type="checkbox" name="ch[api.bat]" value="1"
            <?= $bat('api.bat') ? ' checked' : '' ?>>
            <span>Cho phép bộ nhận mail đẩy dữ liệu qua API</span></label>
          <span class="goi-y" style="margin-top:-.4rem">
            Dùng khi hosting không mở kết nối MySQL từ xa. Nếu bộ nhận mail nối thẳng MySQL thì có thể tắt.
          </span>

          <label class="truong">Địa chỉ API để khai báo trong bộ nhận mail
            <?php
              $sch = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
              $urlApi = $sch . '://' . ($_SERVER['HTTP_HOST'] ?? 'ten-mien.vn') . Util::goc() . '/api/ingest.php';
            ?>
            <input type="text" value="<?= Util::h($urlApi) ?>" readonly onclick="this.select()">
          </label>
          <label class="truong">Khoá API
            <input type="text" value="<?= Util::h($g('api.khoa')) ?>" readonly onclick="this.select()">
            <span class="goi-y">Dán vào mục <em>Kết nối máy chủ → Qua API PHP</em> của bộ nhận mail.</span>
          </label>
          <label class="truong">Kích thước mỗi khối tải lên (KB)
            <input type="number" name="ch[api.kich_thuoc_khoi_kb]"
                   value="<?= Util::h($g('api.kich_thuoc_khoi_kb', 512)) ?>" min="32" max="8192">
            <span class="goi-y">Giảm xuống nếu hosting giới hạn <code>post_max_size</code> nhỏ
              (hiện tại: <?= Util::h(ini_get('post_max_size')) ?>).</span>
          </label>
          <label class="truong">Dung lượng tệp tối đa (MB)
            <input type="number" name="ch[api.dung_luong_toi_da_mb]"
                   value="<?= Util::h($g('api.dung_luong_toi_da_mb', 25)) ?>" min="1" max="100">
          </label>
        </div>
        <div class="hang-nut" style="margin-top:1rem">
          <button type="submit" class="nut nut-chinh">Lưu thiết lập</button>
          <button type="submit" name="viec" value="sinh_khoa_api" class="nut nut-do"
                  data-hoi="Sinh khoá API mới? Bộ nhận mail sẽ ngừng hoạt động cho tới khi được cập nhật khoá mới.">
            Sinh khoá API mới
          </button>
        </div>

      <?php else: ?>
        <div class="luoi-truong" style="grid-template-columns:1fr">
          <label class="truong">Mức ghi nhật ký
            <select name="ch[log.muc]">
              <?php foreach (['debug', 'info', 'canh_bao', 'loi'] as $m): ?>
                <option value="<?= Util::h($m) ?>"<?= $g('log.muc') === $m ? ' selected' : '' ?>>
                  <?= Util::h(Util::tenMucNhatKy($m)) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="goi-y">Ghi từ mức này trở lên. Chọn <em>Gỡ lỗi</em> khi cần dò tìm sự cố.</span>
          </label>
          <label class="truong">Số ngày lưu nhật ký
            <input type="number" name="ch[log.so_ngay_giu]" value="<?= Util::h($g('log.so_ngay_giu', 180)) ?>"
                   min="0" max="3650">
            <span class="goi-y">Nhật ký cũ hơn sẽ được xoá khi bấm “Dọn nhật ký cũ”. 0 = giữ mãi.</span>
          </label>
        </div>
      <?php endif; ?>

      <?php if (!in_array($nhom, ['ai', 'api'], true)): ?>
        <div class="hang-nut" style="margin-top:1rem">
          <button type="submit" class="nut nut-chinh">Lưu thiết lập</button>
        </div>
      <?php endif; ?>
    </form>
  </div>

  <div>
    <div class="the">
      <h2>Thông tin hệ thống</h2>
      <dl class="tt-luoi">
        <?php foreach ($thongTin as $k => $v): ?>
          <dt><?= Util::h($k) ?></dt><dd class="chu-nho"><?= Util::h($v) ?></dd>
        <?php endforeach; ?>
      </dl>
    </div>

    <div class="the">
      <h2>Số liệu kho dữ liệu</h2>
      <dl class="tt-luoi">
        <?php foreach ($soLieu as $k => $v): ?>
          <dt><?= Util::h($k) ?></dt>
          <dd><strong><?= is_int($v) ? Util::h(Util::so($v)) : Util::h($v) ?></strong></dd>
        <?php endforeach; ?>
      </dl>
      <hr style="border:0;border-top:1px solid var(--vien);margin:1rem 0 .9rem">
      <p class="chu-nho chu-mo" style="margin:0 0 .7rem">
        Đang thử nghiệm hệ thống và muốn chạy lại từ đầu? Có thể xoá sạch dữ liệu công việc
        mà vẫn giữ nguyên danh mục, tài khoản và thiết lập.
      </p>
      <a class="nut nut-phu nho" href="<?= Util::h(Util::url('don-du-lieu')) ?>">
        <?php View::manh('layout/bieu-tuong', ['ma' => 'xoa']); ?> Dọn dữ liệu thử nghiệm
      </a>
    </div>
  </div>
</div>
