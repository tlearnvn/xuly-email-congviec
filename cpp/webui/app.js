/* =====================================================================
   Bộ nhận mail - Giao diện điều khiển
   Hệ thống phân luồng Mail công vụ - Thiết kế bởi Trương Anh Tuấn
   ===================================================================== */
(function () {
  'use strict';

  const $  = (s) => document.querySelector(s);
  const $$ = (s) => Array.from(document.querySelectorAll(s));

  let soThuTuLog = 0;
  let cauHinh = {};
  let dangTai = false;

  /* ---------------------- Tiện ích ---------------------- */
  async function goi(duong, tuyChon) {
    const o = Object.assign({ headers: { 'Content-Type': 'application/json' } }, tuyChon || {});
    const r = await fetch(duong, o);
    const t = await r.text();
    let j;
    try { j = JSON.parse(t); }
    catch (e) { throw new Error('Máy chủ trả về dữ liệu không hợp lệ: ' + t.slice(0, 200)); }
    if (j && j.ok === false) throw new Error(j.loi || 'Lỗi không xác định');
    return j;
  }

  function banh(noiDung, loai) {
    const d = document.createElement('div');
    d.className = 'banh ' + (loai || '');
    d.textContent = noiDung;
    $('#khay').appendChild(d);
    setTimeout(() => { d.style.opacity = '0'; d.style.transition = 'opacity .3s'; }, 4600);
    setTimeout(() => d.remove(), 5000);
  }

  function hienThongBao(id, noiDung, loai) {
    const e = $(id);
    if (!e) return;
    e.textContent = noiDung;
    e.className = 'thong-bao ' + (loai || 'tin');
    e.hidden = false;
  }

  function dat(id, gt) {
    const e = document.getElementById(id);
    if (!e) return;
    // Khoá chưa có trong tệp cấu hình thì giữ nguyên mặc định khai trong HTML,
    // đừng tự tắt ô đánh dấu đi.
    if (e.type === 'checkbox') {
      if (gt === undefined || gt === null || gt === '') return;
      e.checked = (gt === true || gt === '1' || gt === 1 || gt === 'true');
    }
    else if (gt !== undefined && gt !== null) e.value = gt;
  }
  function lay(id) {
    const e = document.getElementById(id);
    if (!e) return '';
    if (e.type === 'checkbox') return e.checked ? '1' : '0';
    return e.value.trim();
  }

  /* ---------------------- Điều hướng ---------------------- */
  $$('.muc').forEach((b) => {
    b.addEventListener('click', () => {
      $$('.muc').forEach((x) => x.classList.remove('dang-chon'));
      b.classList.add('dang-chon');
      $$('.trang').forEach((t) => t.classList.remove('hien'));
      const t = document.getElementById('trang-' + b.dataset.trang);
      if (t) t.classList.add('hien');
    });
  });

  /* ---------------------- Đồng hồ ---------------------- */
  function capNhatDongHo() {
    const d = new Date();
    // Giờ Việt Nam (GMT+7) không phụ thuộc múi giờ máy
    const vn = new Date(d.getTime() + (d.getTimezoneOffset() * 60000) + 7 * 3600000);
    const p = (n) => String(n).padStart(2, '0');
    $('#dong-ho').textContent = p(vn.getHours()) + ':' + p(vn.getMinutes()) + ':' + p(vn.getSeconds());
  }
  setInterval(capNhatDongHo, 1000);
  capNhatDongHo();

  /* ---------------------- Trạng thái ---------------------- */
  function veTrangThai(s) {
    // Thương hiệu
    if (s.ung_dung) {
      $('#ten-ung-dung').textContent = s.ung_dung.ten || 'Hệ thống phân luồng Mail công vụ';
      $('#ten-ngan').textContent = s.ung_dung.ten_ngan || 'Phân luồng Mail công vụ';
      $('#ten-don-vi').textContent = s.ung_dung.don_vi || '';
      $('#ban-quyen').textContent = s.ung_dung.ban_quyen || '';
      $('#chan-ban-quyen').textContent = s.ung_dung.ban_quyen || '';
      document.title = (s.ung_dung.ten_ngan || 'Bộ nhận mail') + ' — Bộ nhận mail';
      if (s.ung_dung.mau) document.documentElement.style.setProperty('--xanh', s.ung_dung.mau);
    }
    $('#ten-may').textContent = s.may_chu || '—';

    // Kho lưu trữ
    const k = s.kho || {};
    const eKho = $('#tt-kho');
    eKho.querySelector('.cham').className = 'cham ' + (k.da_ket_noi ? 'ok' : 'loi');
    eKho.querySelector('.tt-gia-tri').textContent = k.da_ket_noi ? (k.ten || 'Đã kết nối') : 'Chưa kết nối';
    eKho.querySelector('.tt-mo-ta').textContent = k.da_ket_noi ? (k.mo_ta || '') : (k.loi || 'Vào mục "Kết nối máy chủ" để thiết lập');

    // Gmail
    const g = s.gmail || {};
    const eG = $('#tt-gmail');
    const gOk = g.da_dang_nhap && g.con_han;
    eG.querySelector('.cham').className = 'cham ' + (gOk ? 'ok' : (g.da_dang_nhap ? 'canh' : 'loi'));
    eG.querySelector('.tt-gia-tri').textContent = g.hop_thu || (g.da_dang_nhap ? 'Đã đăng nhập' : 'Chưa đăng nhập');
    eG.querySelector('.tt-mo-ta').textContent = g.da_dang_nhap
      ? (g.co_refresh
          ? ('Tự động gia hạn, không cần đăng nhập lại' + (g.het_han ? ' · token hiện tại đến ' + g.het_han : ''))
          : 'CHƯA CÓ refresh token — sẽ phải đăng nhập lại khi token hết hạn')
      : 'Vào mục "Tài khoản Gmail" để đăng nhập';
    $('#gmail-hop-thu').textContent = g.hop_thu || 'Chưa đăng nhập';
    $('#gmail-han').textContent = g.da_dang_nhap ? (g.het_han || 'Không rõ') : '—';

    const eGh = $('#gmail-gia-han');
    if (!g.da_dang_nhap) {
      eGh.textContent = '—';
      eGh.style.color = '';
    } else if (g.co_refresh) {
      eGh.textContent = 'Tự gia hạn — không cần đăng nhập lại';
      eGh.style.color = 'var(--luc)';
    } else {
      eGh.textContent = 'Phải đăng nhập lại (thiếu refresh token)';
      eGh.style.color = 'var(--do)';
    }

    // AI
    const a = s.ai || {};
    const eA = $('#tt-ai');
    eA.querySelector('.cham').className = 'cham ' + (a.bat ? 'ok' : '');
    eA.querySelector('.tt-gia-tri').textContent = a.bat ? (a.model || 'Đang bật') : 'Đang tắt';
    eA.querySelector('.tt-mo-ta').textContent = a.bat
      ? (a.url + ' · ' + a.max_tokens + ' token · ' + a.timeout + 's')
      : 'Chỉ dùng khi không đọc được mã từ tên tệp/tiêu đề';

    // Dịch vụ
    const dv = s.dich_vu || {};
    const eD = $('#tt-dich-vu');
    eD.querySelector('.cham').className = 'cham ' + (dv.dang_dong_bo ? 'chay' : (dv.dang_chay ? 'ok' : ''));
    eD.querySelector('.tt-gia-tri').textContent = dv.dang_dong_bo ? 'Đang nhận mail…' : (dv.dang_chay ? 'Đang bật' : 'Đang tắt');
    eD.querySelector('.tt-mo-ta').textContent = dv.dang_chay
      ? ('Chu kỳ ' + dv.chu_ky_phut + ' phút' + (dv.lan_chay_ke ? ' · lần kế: ' + dv.lan_chay_ke : ''))
      : 'Bật để tự động quét mail theo chu kỳ';
    $('#nut-bat-dich-vu').hidden = !!dv.dang_chay;
    $('#nut-tat-dich-vu').hidden = !dv.dang_chay;

    // Thanh bên
    const chamTong = $('#cham-tong');
    if (dv.dang_dong_bo) { chamTong.className = 'cham chay'; $('#ben-tt').textContent = 'Đang nhận mail…'; }
    else if (k.da_ket_noi && gOk) { chamTong.className = 'cham ok'; $('#ben-tt').textContent = 'Sẵn sàng'; }
    else if (k.da_ket_noi) { chamTong.className = 'cham canh'; $('#ben-tt').textContent = 'Cần đăng nhập Gmail'; }
    else { chamTong.className = 'cham loi'; $('#ben-tt').textContent = 'Chưa kết nối máy chủ'; }

    // Danh mục
    const dm = s.danh_muc || {};
    const li = $('#ds-danh-muc').children;
    li[0].querySelector('b').textContent = dm.so_truong ?? 0;
    li[1].querySelector('b').textContent = dm.so_nguoi ?? 0;
    li[2].querySelector('b').textContent = dm.so_van_ban ?? 0;

    // Tiến trình
    const tt = s.tien_trinh || {};
    const dangChay = dv.dang_dong_bo;
    $('#the-tien-trinh').hidden = !dangChay;
    if (dangChay) {
      const ten = { chuan_bi: 'Đang chuẩn bị', liet_ke: 'Đang lấy danh sách mail', xu_ly: 'Đang xử lý mail', xong: 'Hoàn tất', loi: 'Có lỗi' };
      $('#tt-buoc').textContent = ten[tt.buoc] || tt.buoc || 'Đang xử lý';
      $('#tt-so').textContent = (tt.tong > 0) ? (tt.da_lam + '/' + tt.tong) : '';
      $('#thanh-trong').style.width = (tt.tong > 0 ? Math.round(tt.da_lam * 100 / tt.tong) : 8) + '%';
      $('#tt-thong-diep').textContent = tt.thong_diep || '';
    }
    $('#nut-dong-bo').disabled = dangChay;
    $('#nut-chay-ngay').disabled = dangChay;

    // Phiên gần nhất
    const p = s.phien_cuoi || {};
    const o = $('#so-lieu-phien').children;
    const gt = [p.so_mail_quet, p.so_mail_moi, p.so_mail_ban_moi, p.so_mail_trung,
                p.so_cong_viec, p.so_tep, p.so_cho_phan_luong, p.so_loi];
    for (let i = 0; i < o.length; i++) o[i].querySelector('b').textContent = gt[i] ?? 0;
    $('#phien-thong-diep').textContent = p.thong_diep
      ? (p.bat_dau ? '[' + p.bat_dau + '] ' : '') + p.thong_diep
      : 'Chưa có phiên nào được chạy.';
  }

  async function napTrangThai() {
    try {
      const s = await goi('/api/trang-thai');
      veTrangThai(s);
    } catch (e) { /* im lặng khi mất kết nối tạm thời */ }
  }

  /* ---------------------- Cấu hình ---------------------- */
  const TRUONG = [
    'mysql.may_chu', 'mysql.cong', 'mysql.nguoi_dung', 'mysql.mat_khau', 'mysql.co_so_du_lieu',
    'mysql.timeout', 'mysql.kich_thuoc_khoi_kb',
    'api.url', 'api.khoa', 'api.timeout', 'api.kich_thuoc_khoi_kb',
    'gmail.client_id', 'gmail.client_secret', 'gmail.access_token', 'gmail.refresh_token',
    'gmail.truy_van', 'gmail.so_mail_moi_lan', 'gmail.quet_spam', 'gmail.nhan_link_drive',
    'ai.bat', 'ai.url', 'ai.api_key', 'ai.model', 'ai.max_tokens', 'ai.timeout',
    'ai.temperature', 'ai.nguong_tin_cay',
    'ung_dung.chu_ky_phut', 'ung_dung.dung_luong_tep_toi_da_mb'
  ];
  const idCua = (k) => k.replace(/\./g, '_');

  async function napCauHinh() {
    dangTai = true;
    try {
      const j = await goi('/api/cau-hinh');
      cauHinh = j.cau_hinh || {};
      TRUONG.forEach((k) => dat(idCua(k), cauHinh[k]));
      const cd = cauHinh['luu_tru.che_do'] || 'mysql';
      const r = document.querySelector('input[name=che_do][value="' + cd + '"]');
      if (r) r.checked = true;
      veCheDo();
      if (j.uri_chuyen_huong) $('#uri-chuyen-huong').textContent = j.uri_chuyen_huong;
      if (j.phien_ban) $('#chan-phien-ban').textContent = 'MailRouter ' + (j.phien_ban_day_du || j.phien_ban);
    } catch (e) { banh('Không đọc được cấu hình: ' + e.message, 'loi'); }
    dangTai = false;
  }

  function veCheDo() {
    const cd = (document.querySelector('input[name=che_do]:checked') || {}).value || 'mysql';
    $('#khoi-mysql').classList.toggle('hien', cd === 'mysql');
    $('#khoi-api').classList.toggle('hien', cd === 'api');
  }
  $$('input[name=che_do]').forEach((r) => r.addEventListener('change', veCheDo));

  async function luuCauHinh(khoa) {
    const g = {};
    khoa.forEach((k) => { g[k] = lay(idCua(k)); });
    const cd = document.querySelector('input[name=che_do]:checked');
    if (cd && khoa.indexOf('luu_tru.che_do') >= 0) g['luu_tru.che_do'] = cd.value;
    await goi('/api/cau-hinh', { method: 'POST', body: JSON.stringify({ cau_hinh: g }) });
  }

  /* ---------------------- Nhật ký ---------------------- */
  function themDongLog(d) {
    const nhan = { debug: 'GỠ', info: 'TIN', canh_bao: 'CẢNH', loi: 'LỖI' };
    ['#log-nho', '#log-day'].forEach((sel) => {
      const box = $(sel);
      if (!box) return;
      if (sel === '#log-day') {
        const fm = $('#loc-muc').value;
        const ft = $('#loc-tu-khoa').value.toLowerCase();
        if (fm && d.muc !== fm) return;
        if (ft && (d.noi_dung + ' ' + d.hanh_dong).toLowerCase().indexOf(ft) < 0) return;
      }
      const e = document.createElement('div');
      e.className = 'dong-log m-' + d.muc;
      e.innerHTML = '<span class="gio"></span><span class="muc"></span><span class="hd"></span><span class="nd"></span>';
      e.children[0].textContent = (d.thoi_gian || '').slice(11);
      e.children[1].textContent = nhan[d.muc] || d.muc;
      e.children[2].textContent = d.hanh_dong ? '[' + d.hanh_dong + ']' : '';
      e.children[3].textContent = d.noi_dung || '';
      box.appendChild(e);
      while (box.children.length > 1200) box.removeChild(box.firstChild);
      const tuCuon = (sel === '#log-nho') ? $('#tu-cuon').checked : $('#tu-cuon-2').checked;
      if (tuCuon) box.scrollTop = box.scrollHeight;
    });
  }

  async function napNhatKy() {
    try {
      const j = await goi('/api/nhat-ky?tu=' + soThuTuLog);
      (j.dong || []).forEach((d) => { themDongLog(d); soThuTuLog = Math.max(soThuTuLog, d.so); });
    } catch (e) { /* bỏ qua */ }
  }

  $('#nut-xoa-log').addEventListener('click', () => { $('#log-nho').innerHTML = ''; });
  $('#nut-xoa-log-2').addEventListener('click', () => { $('#log-day').innerHTML = ''; });

  /* ---------------------- Hành động ---------------------- */
  async function chay(nut, viec, thongBaoOk) {
    const cu = nut ? nut.textContent : '';
    if (nut) { nut.disabled = true; nut.textContent = 'Đang xử lý…'; }
    try {
      const r = await viec();
      if (thongBaoOk) banh(typeof thongBaoOk === 'function' ? thongBaoOk(r) : thongBaoOk, 'ok');
      return r;
    } catch (e) {
      banh(e.message, 'loi');
      throw e;
    } finally {
      if (nut) { nut.disabled = false; nut.textContent = cu; }
      napTrangThai();
    }
  }

  $('#nut-lam-moi').addEventListener('click', () => { napTrangThai(); napCauHinh(); });

  $('#nut-luu-ket-noi').addEventListener('click', async (ev) => {
    const nut = ev.currentTarget;
    try {
      await chay(nut, async () => {
        await luuCauHinh(['luu_tru.che_do', 'mysql.may_chu', 'mysql.cong', 'mysql.nguoi_dung',
          'mysql.mat_khau', 'mysql.co_so_du_lieu', 'mysql.timeout', 'mysql.kich_thuoc_khoi_kb',
          'api.url', 'api.khoa', 'api.timeout', 'api.kich_thuoc_khoi_kb']);
        return await goi('/api/ket-noi', { method: 'POST', body: '{}' });
      });
      hienThongBao('#tb-ket-noi', 'Kết nối thành công. Danh mục đã được nạp về.', 'ok');
    } catch (e) { hienThongBao('#tb-ket-noi', 'Không kết nối được: ' + e.message, 'loi'); }
  });

  $('#nut-kiem-tra-ket-noi').addEventListener('click', async (ev) => {
    try {
      const r = await chay(ev.currentTarget, () => goi('/api/ket-noi', { method: 'POST', body: '{}' }));
      hienThongBao('#tb-ket-noi', r.thong_diep || 'Kết nối tốt.', 'ok');
    } catch (e) { hienThongBao('#tb-ket-noi', 'Không kết nối được: ' + e.message, 'loi'); }
  });

  $('#nut-nap-danh-muc').addEventListener('click', (ev) =>
    chay(ev.currentTarget, () => goi('/api/ket-noi', { method: 'POST', body: '{}' }),
         (r) => r.thong_diep || 'Đã nạp lại danh mục'));

  // --- Gmail ---
  $('#nut-luu-ung-dung').addEventListener('click', (ev) =>
    chay(ev.currentTarget, () => luuCauHinh(['gmail.client_id', 'gmail.client_secret']), 'Đã lưu Client ID/Secret'));

  $('#nut-dang-nhap-google').addEventListener('click', async (ev) => {
    if (!lay('gmail_client_id') || !lay('gmail_client_secret')) {
      hienThongBao('#tb-gmail', 'Cần nhập đủ Client ID và Client Secret trước khi đăng nhập.', 'loi');
      return;
    }
    try {
      await chay(ev.currentTarget, async () => {
        await luuCauHinh(['gmail.client_id', 'gmail.client_secret']);
        return await goi('/api/gmail/url');
      }).then((r) => {
        hienThongBao('#tb-gmail', 'Đã mở cửa sổ đăng nhập Google. Hoàn tất trên trình duyệt rồi quay lại đây.', 'tin');
        window.open(r.url, '_blank', 'noopener');
      });
    } catch (e) { hienThongBao('#tb-gmail', e.message, 'loi'); }
  });

  $('#nut-luu-token').addEventListener('click', async (ev) => {
    try {
      const r = await chay(ev.currentTarget, () => goi('/api/gmail/token', {
        method: 'POST',
        body: JSON.stringify({
          access_token: lay('gmail_access_token'),
          refresh_token: lay('gmail_refresh_token'),
          client_id: lay('gmail_client_id'),
          client_secret: lay('gmail_client_secret')
        })
      }));
      hienThongBao('#tb-gmail', r.thong_diep || 'Đã lưu token.', 'ok');
    } catch (e) { hienThongBao('#tb-gmail', e.message, 'loi'); }
  });

  $('#nut-kiem-tra-gmail').addEventListener('click', async (ev) => {
    try {
      const r = await chay(ev.currentTarget, () => goi('/api/gmail/kiem-tra', { method: 'POST', body: '{}' }));
      hienThongBao('#tb-gmail', r.thong_diep || 'Kết nối Gmail tốt.', 'ok');
    } catch (e) { hienThongBao('#tb-gmail', e.message, 'loi'); }
  });

  $('#nut-thoat-gmail').addEventListener('click', async (ev) => {
    if (!confirm('Đăng xuất tài khoản Gmail khỏi chương trình này?')) return;
    await chay(ev.currentTarget, () => goi('/api/gmail/thoat', { method: 'POST', body: '{}' }), 'Đã đăng xuất');
    napCauHinh();
  });

  // --- Phân luồng ---
  $('#nut-luu-phan-luong').addEventListener('click', (ev) =>
    chay(ev.currentTarget, () => luuCauHinh(['gmail.truy_van', 'gmail.so_mail_moi_lan',
      'gmail.quet_spam', 'gmail.nhan_link_drive', 'ung_dung.chu_ky_phut',
      'ung_dung.dung_luong_tep_toi_da_mb']), 'Đã lưu thiết lập'));

  async function chayDongBo(nut) {
    const than = {};
    const gh = lay('gioi-han-lan-nay');
    const tv = lay('truy-van-lan-nay');
    if (gh) than.gioi_han = parseInt(gh, 10);
    if (tv) than.truy_van = tv;
    try {
      await chay(nut, () => goi('/api/dong-bo', { method: 'POST', body: JSON.stringify(than) }),
                 'Đã bắt đầu nhận mail');
      hienThongBao('#tb-phan-luong', 'Phiên nhận mail đang chạy, theo dõi ở mục Tổng quan / Nhật ký.', 'tin');
    } catch (e) { hienThongBao('#tb-phan-luong', e.message, 'loi'); }
  }
  $('#nut-dong-bo').addEventListener('click', (ev) => chayDongBo(ev.currentTarget));
  $('#nut-chay-ngay').addEventListener('click', (ev) => chayDongBo(ev.currentTarget));
  $('#nut-dung').addEventListener('click', (ev) =>
    chay(ev.currentTarget, () => goi('/api/dung', { method: 'POST', body: '{}' }), 'Đã gửi yêu cầu dừng'));

  $('#nut-bat-dich-vu').addEventListener('click', (ev) =>
    chay(ev.currentTarget, async () => {
      await luuCauHinh(['ung_dung.chu_ky_phut']);
      return goi('/api/dich-vu', { method: 'POST', body: JSON.stringify({ bat: true }) });
    }, 'Đã bật dịch vụ tự động'));

  $('#nut-tat-dich-vu').addEventListener('click', (ev) =>
    chay(ev.currentTarget, () => goi('/api/dich-vu', { method: 'POST', body: JSON.stringify({ bat: false }) }),
         'Đã tắt dịch vụ tự động'));

  // --- AI ---
  $('#nut-luu-ai').addEventListener('click', async (ev) => {
    try {
      await chay(ev.currentTarget, () => luuCauHinh(['ai.bat', 'ai.url', 'ai.api_key', 'ai.model',
        'ai.max_tokens', 'ai.timeout', 'ai.temperature', 'ai.nguong_tin_cay']));
      hienThongBao('#tb-ai', 'Đã lưu thiết lập AI.', 'ok');
    } catch (e) { hienThongBao('#tb-ai', e.message, 'loi'); }
  });

  $('#nut-kiem-tra-ai').addEventListener('click', async (ev) => {
    try {
      await luuCauHinh(['ai.bat', 'ai.url', 'ai.api_key', 'ai.model', 'ai.max_tokens',
                        'ai.timeout', 'ai.temperature', 'ai.nguong_tin_cay']);
      const r = await chay(ev.currentTarget, () => goi('/api/ai/kiem-tra', { method: 'POST', body: '{}' }));
      hienThongBao('#tb-ai', r.thong_diep || 'Kết nối AI tốt.', 'ok');
    } catch (e) { hienThongBao('#tb-ai', e.message, 'loi'); }
  });

  ['#loc-muc', '#loc-tu-khoa'].forEach((s) =>
    $(s).addEventListener('input', () => { $('#log-day').innerHTML = ''; soThuTuLog = 0; napNhatKy(); }));

  /* ---------------------- Khởi động ---------------------- */
  napCauHinh().then(napTrangThai).then(napNhatKy);
  setInterval(napTrangThai, 2500);
  setInterval(napNhatKy, 1500);
})();
