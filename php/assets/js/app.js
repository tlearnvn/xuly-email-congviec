/* =====================================================================
   HỆ THỐNG PHÂN LUỒNG MAIL CÔNG VỤ - Kịch bản giao diện
   Thiết kế bởi Trương Anh Tuấn
   ===================================================================== */
(function () {
  'use strict';

  /* ------------------------- Đồng hồ giờ Việt Nam ------------------------- */
  var dh = document.getElementById('dong-ho');
  if (dh) {
    setInterval(function () {
      var d = new Date();
      var vn = new Date(d.getTime() + d.getTimezoneOffset() * 60000 + 7 * 3600000);
      var p = function (n) { return String(n).padStart(2, '0'); };
      dh.textContent = p(vn.getHours()) + ':' + p(vn.getMinutes());
    }, 20000);
  }

  /* ------------------------- Menu trên di động ------------------------- */
  var nutMenu = document.getElementById('nut-menu');
  var ben = document.getElementById('thanh-ben');
  if (nutMenu && ben) {
    nutMenu.addEventListener('click', function (e) {
      e.stopPropagation();
      ben.classList.toggle('mo');
    });
    document.addEventListener('click', function (e) {
      if (ben.classList.contains('mo') && !ben.contains(e.target)) ben.classList.remove('mo');
    });
  }

  /* ------------------------- Hiện/ẩn mật khẩu ------------------------- */
  document.querySelectorAll('.mk-hien').forEach(function (b) {
    b.addEventListener('click', function () {
      var o = document.getElementById(b.dataset.o);
      if (!o) return;
      o.type = (o.type === 'password') ? 'text' : 'password';
      b.setAttribute('aria-label', o.type === 'password' ? 'Hiện mật khẩu' : 'Ẩn mật khẩu');
    });
  });

  /* ------------------------- Xác nhận trước khi xoá ------------------------- */
  document.querySelectorAll('[data-hoi]').forEach(function (e) {
    e.addEventListener('click', function (ev) {
      if (!window.confirm(e.dataset.hoi)) { ev.preventDefault(); ev.stopPropagation(); }
    });
  });

  /* ------------------------- Tự gửi biểu mẫu lọc ------------------------- */
  document.querySelectorAll('[data-tu-gui]').forEach(function (e) {
    e.addEventListener('change', function () {
      var f = e.closest('form');
      if (f) f.submit();
    });
  });

  /* ------------------------- Chọn tất cả trong bảng ------------------------- */
  document.querySelectorAll('[data-chon-tat-ca]').forEach(function (o) {
    o.addEventListener('change', function () {
      document.querySelectorAll(o.dataset.chonTatCa).forEach(function (x) { x.checked = o.checked; });
    });
  });

  /* ------------------------- Nhờ AI gợi ý phân luồng ------------------------- */
  var nutAi = document.getElementById('nut-ai');
  if (nutAi) {
    nutAi.addEventListener('click', function () {
      var hop = document.getElementById('hop-ai');
      var cu = nutAi.innerHTML;
      nutAi.disabled = true;
      nutAi.textContent = 'Đang hỏi AI…';
      if (hop) { hop.hidden = false; hop.textContent = 'Đang gửi thông tin email cho trợ lý AI…'; }

      fetch(nutAi.dataset.url, { headers: { 'X-Yeu-Cau': 'ajax' } })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (!j.ok) throw new Error(j.loi || 'Không rõ lỗi');
          var d = j.du_lieu || {};
          if (hop) {
            hop.innerHTML = '<strong>AI đề xuất:</strong> ' +
              '<span class="ma-ho-so">' + (d.ma_truong || '?') + '_' + (d.ma_van_ban || '?') + '_' +
              (d.ma_nguoi_xu_ly || '?') + '</span> — độ tin cậy ' +
              Math.round((d.do_tin_cay || 0) * 100) + '%<br>' +
              '<span class="chu-nho">' + (d.ly_do || '') + '</span>';
          }
          var g = function (id, v) { var e = document.getElementById(id); if (e && v) e.value = v; };
          g('ma_truong', d.ma_truong);
          g('ma_van_ban', d.ma_van_ban);
          g('ma_nguoi_xu_ly', d.ma_nguoi_xu_ly);
        })
        .catch(function (e) {
          if (hop) hop.innerHTML = '<strong>Không dùng được AI:</strong> ' + e.message;
        })
        .finally(function () { nutAi.disabled = false; nutAi.innerHTML = cu; });
    });
  }

  /* ------------------------- Kiểm tra kết nối AI (trang Cài đặt) ------------------------- */
  var nutTestAi = document.getElementById('nut-test-ai');
  if (nutTestAi) {
    nutTestAi.addEventListener('click', function () {
      var kq = document.getElementById('kq-test-ai');
      var cu = nutTestAi.textContent;
      nutTestAi.disabled = true;
      nutTestAi.textContent = 'Đang kiểm tra…';
      if (kq) { kq.hidden = false; kq.className = 'nhan nhan-tin'; kq.textContent = 'Đang gọi dịch vụ AI…'; }

      var than = new URLSearchParams();
      ['ai_url', 'ai_api_key', 'ai_model', 'ai_max_tokens', 'ai_timeout', 'ai_temperature']
        .forEach(function (id) {
          var e = document.getElementById(id);
          if (e) than.append(id, e.value);
        });
      than.append('csrf', nutTestAi.dataset.csrf || '');

      fetch(nutTestAi.dataset.url, { method: 'POST', body: than })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (kq) {
            kq.className = 'nhan ' + (j.ok ? 'nhan-ok' : 'nhan-loi');
            kq.textContent = j.thong_diep || j.loi || '';
          }
        })
        .catch(function (e) {
          if (kq) { kq.className = 'nhan nhan-loi'; kq.textContent = 'Lỗi: ' + e.message; }
        })
        .finally(function () { nutTestAi.disabled = false; nutTestAi.textContent = cu; });
    });
  }

  /* ------------------------- Sao chép nhanh ------------------------- */
  document.querySelectorAll('[data-chep]').forEach(function (b) {
    b.addEventListener('click', function () {
      var v = b.dataset.chep;
      var xong = function () {
        var cu = b.textContent;
        b.textContent = 'Đã chép!';
        setTimeout(function () { b.textContent = cu; }, 1600);
      };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(v).then(xong);
      } else {
        var t = document.createElement('textarea');
        t.value = v;
        t.style.position = 'fixed';
        t.style.opacity = '0';
        document.body.appendChild(t);
        t.select();
        try { document.execCommand('copy'); xong(); } catch (e) {}
        t.remove();
      }
    });
  });

  /* ------------------------- Tự ẩn thông báo ------------------------- */
  document.querySelectorAll('.nhan-ok').forEach(function (n) {
    setTimeout(function () {
      n.style.transition = 'opacity .4s, margin .4s, padding .4s';
      n.style.opacity = '0';
      setTimeout(function () { n.remove(); }, 420);
    }, 6000);
  });
})();
