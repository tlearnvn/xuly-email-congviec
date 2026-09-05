#!/usr/bin/env node
/* =====================================================================
 *  tao-pdf.js - Dựng bản PDF tông vàng "mệnh Kim" từ hai tài liệu Markdown
 *  Hệ thống phân luồng Mail công vụ - Thiết kế bởi Trương Anh Tuấn
 * ---------------------------------------------------------------------
 *  Cách dùng:
 *      node scripts/tao-pdf.js
 *
 *  Bước 1 luôn chạy được: sinh ra docs/pdf/tai-lieu.html (tự chứa, mở
 *  bằng trình duyệt rồi Ctrl+P cũng ra đúng bản PDF đó).
 *  Bước 2 chỉ chạy khi tìm thấy Chrome/Chromium và gói playwright-core:
 *  in thẳng ra docs/pdf/He-thong-phan-luong-Mail-cong-vu.pdf.
 *
 *  Chỉ định trình duyệt bằng biến môi trường CHROME nếu cần:
 *      CHROME=/usr/bin/chromium node scripts/tao-pdf.js
 *
 *  Gói playwright-core cài ở nơi khác thì trỏ bằng NODE_PATH:
 *      NODE_PATH=/duong/dan/node_modules node scripts/tao-pdf.js
 * ===================================================================== */
'use strict';

const fs = require('fs');
const path = require('path');

const GOC = path.resolve(__dirname, '..');
const DOCS = path.join(GOC, 'docs');
const RA = path.join(DOCS, 'pdf');
const KHO = 'https://github.com/tlearnvn/xuly-email-congviec/blob/HEAD/docs/';

const PHIEN_BAN = doc(path.join(GOC, 'VERSION'), '1.0.0');
const SO_BUILD = doc(path.join(GOC, 'BUILD'), '1');

function doc(t, mac_dinh) {
  try { return fs.readFileSync(t, 'utf8').trim(); } catch (e) { return mac_dinh; }
}

/* ===================================================================
 *  1. Bộ chuyển Markdown -> HTML (đủ dùng cho hai tài liệu của dự án)
 * =================================================================== */

function thoat(s) {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

// Tạo mã neo giống cách GitHub đặt, để mục lục bấm được trong PDF
function neo(tieu_de) {
  return tieu_de
    .replace(/`/g, '')
    .replace(/\*\*/g, '')
    .replace(/\[([^\]]*)\]\([^)]*\)/g, '$1')
    .toLowerCase()
    .trim()
    .replace(/[^\p{L}\p{N} _\-]/gu, '')   // GitHub giữ lại dấu gạch dưới
    .replace(/ +/g, '-');
}

function trongDong(s) {
  let r = thoat(s);
  // mã lệnh trong dòng - làm trước để nội dung bên trong không bị diễn giải tiếp
  const kho_ma = [];
  r = r.replace(/`([^`]+)`/g, (m, ma) => {
    kho_ma.push(ma);
    return '\u0000MA' + (kho_ma.length - 1) + '\u0000';
  });
  // ảnh -> chú thích riêng, xử lý ở mức khối
  r = r.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
  r = r.replace(/(^|[^\w*])\*([^*\n]+)\*(?=[^\w*]|$)/g, '$1<em>$2</em>');
  r = r.replace(/\[([^\]]+)\]\(([^)]+)\)/g, (m, chu, dia_chi) => {
    let d = dia_chi;
    if (/^[\w.-]+\.md(#.*)?$/.test(d)) d = KHO + d;           // sang tài liệu khác
    else if (d.startsWith('pdf/')) return chu;                 // tự trỏ vào chính mình
    return '<a href="' + d + '">' + chu + '</a>';
  });
  r = r.replace(/\u0000MA(\d+)\u0000/g, (m, i) => '<code>' + kho_ma[+i] + '</code>');
  return r;
}

function bang(dong) {
  const o = (d) => d.replace(/^\s*\|/, '').replace(/\|\s*$/, '').split('|').map((x) => x.trim());
  const dau = o(dong[0]);
  const than = dong.slice(2).map(o);
  let h = '<div class="bang-bao"><table><thead><tr>';
  dau.forEach((c) => { h += '<th>' + trongDong(c) + '</th>'; });
  h += '</tr></thead><tbody>';
  than.forEach((hang) => {
    h += '<tr>';
    hang.forEach((c) => { h += '<td>' + trongDong(c) + '</td>'; });
    h += '</tr>';
  });
  return h + '</tbody></table></div>';
}

function chuyen(md, tien_to_neo) {
  const dong = md.split('\n');
  const ra = [];
  const muc_luc = [];
  let i = 0;

  const day = (x) => ra.push(x);

  while (i < dong.length) {
    const d = dong[i];

    // khối mã
    if (/^```/.test(d)) {
      const ngon_ngu = d.slice(3).trim();
      const than = [];
      i++;
      while (i < dong.length && !/^```/.test(dong[i])) { than.push(dong[i]); i++; }
      i++;
      day('<pre class="ma' + (ngon_ngu ? ' ma-' + ngon_ngu : '') + '"><code>'
          + thoat(than.join('\n')) + '</code></pre>');
      continue;
    }

    // bảng
    if (/^\s*\|/.test(d) && i + 1 < dong.length && /^\s*\|[\s:|-]+\|\s*$/.test(dong[i + 1])) {
      const than = [];
      while (i < dong.length && /^\s*\|/.test(dong[i])) { than.push(dong[i]); i++; }
      day(bang(than));
      continue;
    }

    // tiêu đề
    const td = d.match(/^(#{1,6})\s+(.*)$/);
    if (td) {
      const cap = td[1].length;
      const chu = td[2].trim();
      const ma = tien_to_neo + neo(chu);
      if (cap <= 3) {
        const chu_sach = chu.replace(/\*\*/g, '').replace(/`/g, '')
                            .replace(/\[([^\]]*)\]\([^)]*\)/g, '$1');
        muc_luc.push({ cap, chu: chu_sach, ma });
      }
      day('<h' + cap + ' id="' + ma + '">' + trongDong(chu) + '</h' + cap + '>');
      i++;
      continue;
    }

    // đường kẻ ngang
    if (/^---+\s*$/.test(d)) { day('<hr>'); i++; continue; }

    // trích dẫn
    if (/^>\s?/.test(d)) {
      const than = [];
      while (i < dong.length && /^>\s?/.test(dong[i])) { than.push(dong[i].replace(/^>\s?/, '')); i++; }
      if (than.join(' ').includes('Bản PDF in ấn')) continue;   // bỏ dòng tự trỏ
      day('<blockquote>' + trongDong(than.join(' ').trim()) + '</blockquote>');
      continue;
    }

    // ảnh đứng riêng một dòng -> hình có chú thích
    const anh = d.match(/^!\[([^\]]*)\]\(([^)]+)\)\s*$/);
    if (anh) {
      const nguon = anh[2].startsWith('hinh/') ? '../' + anh[2] : anh[2];
      const la_so_do = /\.svg$/.test(nguon);
      day('<figure class="' + (la_so_do ? 'so-do' : 'anh-chup') + '">'
          + '<img src="' + nguon + '" alt="' + thoat(anh[1]) + '">'
          + (anh[1] ? '<figcaption>' + thoat(anh[1]) + '</figcaption>' : '')
          + '</figure>');
      i++;
      continue;
    }

    // danh sách (có hỗ trợ một cấp lồng nhau)
    if (/^\s*([-*]|\d+\.)\s+/.test(d)) {
      const thut = (x) => x.match(/^\s*/)[0].length;
      const goc = thut(d);
      const the = (x) => (/^\s*\d+\./.test(x) ? 'ol' : 'ul');
      let h = '<' + the(d) + '>';
      let dang_long = false;
      while (i < dong.length && /^\s*([-*]|\d+\.)\s+/.test(dong[i])) {
        const sau = thut(dong[i]) > goc;
        if (sau && !dang_long) { h += '<' + the(dong[i]) + ' class="ds-con">'; dang_long = true; }
        else if (!sau && dang_long) { h += '</ul>'; dang_long = false; }
        let noi = dong[i].replace(/^\s*([-*]|\d+\.)\s+/, '');
        const muc_thut = thut(dong[i]);
        i++;
        // dòng nối tiếp thụt sâu hơn đầu mục
        while (i < dong.length && dong[i].trim()
               && thut(dong[i]) > muc_thut && !/^\s*([-*]|\d+\.)\s+/.test(dong[i])) {
          noi += ' ' + dong[i].trim(); i++;
        }
        h += '<li>' + trongDong(noi) + '</li>';
      }
      if (dang_long) h += '</ul>';
      day(h + '</' + the(d) + '>');
      continue;
    }

    // dòng trống
    if (!d.trim()) { i++; continue; }

    // đoạn văn
    const than = [];
    while (i < dong.length && dong[i].trim()
           && !/^(#{1,6}\s|```|>|\s*\||---+\s*$|!\[)/.test(dong[i])
           && !/^\s*([-*]|\d+\.)\s+/.test(dong[i])) {
      than.push(dong[i].trim()); i++;
    }
    if (than.length) {
      const t = than.join(' ');
      const chi_dam = t.match(/^\*\*(.+)\*\*$/);
      day(chi_dam ? '<p class="dan-de">' + trongDong(chi_dam[1]) + '</p>'
                  : '<p>' + trongDong(t) + '</p>');
    }
  }

  return { html: ra.join('\n'), muc_luc };
}

/* ===================================================================
 *  2. Dọn tài liệu trước khi chuyển
 * =================================================================== */

function nap(ten) {
  let md = fs.readFileSync(path.join(DOCS, ten), 'utf8');
  // Bỏ mục lục riêng của từng tài liệu - bản PDF dùng mục lục tổng
  md = md.replace(/\n## Mục lục\n[\s\S]*?\n---\n/, '\n');
  // Bỏ dòng chân trang lặp lại
  md = md.replace(/\n\*Hệ thống phân luồng Mail công vụ[\s\S]*$/, '\n');
  return md;
}

function tach(md) {
  const m = md.match(/^#\s+(.+)$/m);
  const ten = m ? m[1].trim() : '';
  return { ten, than: md.replace(/^#\s+.+$/m, '').trim() };
}

/* ===================================================================
 *  3. Dựng trang HTML tông vàng mệnh Kim
 * =================================================================== */

const NGAY = new Intl.DateTimeFormat('vi-VN', {
  timeZone: 'Asia/Ho_Chi_Minh', day: '2-digit', month: '2-digit', year: 'numeric',
}).format(new Date());

const KY = [
  { tep: 'QUY-TRINH-KY-THUAT.md', so: 'I', ten: 'Quy trình kỹ thuật',
    mo_ta: 'Hệ thống hoạt động thế nào, vì sao thiết kế như vậy', neo: 'kt-' },
  { tep: 'HUONG-DAN-SU-DUNG.md', so: 'II', ten: 'Hướng dẫn sử dụng',
    mo_ta: 'Dùng hàng ngày, đi lần lượt từng màn hình', neo: 'hd-' },
];

function dungHtml() {
  const phan = KY.map((k) => {
    const { than } = tach(nap(k.tep));
    const { html, muc_luc } = chuyen(than, k.neo);
    return Object.assign({}, k, { html, muc_luc });
  });

  let ml = '';
  phan.forEach((p) => {
    ml += '<div class="ml-phan"><span class="ml-so">Phần ' + p.so + '</span>'
        + '<a href="#phan-' + p.so + '">' + p.ten + '</a></div>';
    ml += '<ul class="ml-ds">';
    p.muc_luc.filter((m) => m.cap === 2).forEach((m) => {
      ml += '<li><a href="#' + m.ma + '">' + thoat(m.chu) + '</a></li>';
    });
    ml += '</ul>';
  });

  const than = phan.map((p) =>
    '<section class="phan" id="phan-' + p.so + '">'
    + '<div class="phan-bia"><div class="phan-so">PHẦN ' + p.so + '</div>'
    + '<h1 class="phan-ten">' + p.ten + '</h1>'
    + '<div class="phan-mo-ta">' + p.mo_ta + '</div>'
    + '<div class="hoa-van"><span></span><span class="giua"></span><span></span></div></div>'
    + p.html + '</section>').join('\n');

  return `<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Hệ thống phân luồng Mail công vụ — Tài liệu kỹ thuật &amp; Hướng dẫn sử dụng</title>
<style>
:root{
  --kim-dam:#7a5c15;      /* vàng đồng sẫm - chữ tiêu đề */
  --kim:#a8811f;          /* vàng chủ đạo */
  --kim-sang:#c9a227;     /* vàng sáng - đường viền, điểm nhấn */
  --kim-anh:#e3c766;      /* ánh kim nhạt */
  --kim-nhat:#f6eeda;     /* nền vàng rất nhạt */
  --kem:#fdfaf3;          /* nền trang, màu kem */
  --kem-dam:#f3ecdc;
  --chu:#2f2a21;          /* chữ than ấm */
  --chu-phu:#6b6152;
  --chu-nhat:#948870;
  --vien:#e6dcc4;
}
@page{ size:A4; margin:17mm 15mm 16mm 15mm; }

*{box-sizing:border-box;}
html{-webkit-print-color-adjust:exact; print-color-adjust:exact;}
body{
  margin:0; background:var(--kem); color:var(--chu);
  font-family:"Segoe UI","Noto Sans","DejaVu Sans","Liberation Sans",Arial,sans-serif;
  font-size:10.4pt; line-height:1.62;
}
h1,h2,h3,h4,.tieu-de-serif{
  font-family:"Palatino Linotype",Palatino,Georgia,"Noto Serif","DejaVu Serif","Liberation Serif",serif;
}

/* ===================== BÌA ===================== */
/* Cao đúng bằng vùng in của A4 (297 - 17 - 16) để bìa gọn trong một trang
   và không đè lên đầu trang / chân trang do trình duyệt vẽ ở phần lề. */
.bia{
  height:264mm; page-break-after:always; position:relative; border-radius:2mm;
  background:linear-gradient(155deg,#fdfaf3 0%,#f8f0dc 42%,#f1e4c2 100%);
  display:flex; flex-direction:column; align-items:center; justify-content:center;
  text-align:center; padding:0 20mm; overflow:hidden;
}
.bia::before{
  content:""; position:absolute; top:0; left:0; right:0; height:9mm;
  background:linear-gradient(90deg,var(--kim-dam),var(--kim-sang) 45%,var(--kim-anh) 70%,var(--kim-dam));
}
.bia::after{
  content:""; position:absolute; bottom:0; left:0; right:0; height:4.5mm;
  background:linear-gradient(90deg,var(--kim-dam),var(--kim-sang) 55%,var(--kim-dam));
}
.bia-vien{
  position:absolute; top:15mm; left:10mm; right:10mm; bottom:10.5mm;
  border:1.1pt solid var(--kim-sang); border-radius:2mm; pointer-events:none;
}
.bia-vien::before{
  content:""; position:absolute; top:2.2mm; left:2.2mm; right:2.2mm; bottom:2.2mm;
  border:0.5pt solid rgba(168,129,31,.45); border-radius:2mm;
}
.bia-dau{
  width:26mm; height:26mm; border-radius:50%; margin-bottom:9mm;
  background:linear-gradient(140deg,var(--kim-anh),var(--kim) 55%,var(--kim-dam));
  display:flex; align-items:center; justify-content:center;
  box-shadow:0 3mm 9mm rgba(122,92,21,.28);
}
.bia-dau svg{width:13mm;height:13mm;}
.bia-don-vi{
  font-size:11pt; letter-spacing:.16em; text-transform:uppercase;
  color:var(--kim-dam); font-weight:700; margin-bottom:2mm;
}
.bia-don-vi-2{ font-size:10.5pt; color:var(--chu-phu); margin-bottom:12mm; letter-spacing:.04em;}
.bia h1{
  font-size:30pt; line-height:1.22; color:var(--kim-dam); margin:0 0 5mm;
  font-weight:700; letter-spacing:.005em;
}
.bia-gach{
  width:52mm; height:1.6pt; margin:0 auto 6mm;
  background:linear-gradient(90deg,transparent,var(--kim-sang),transparent);
}
.bia-phu{ font-size:13.5pt; color:var(--chu-phu); margin-bottom:16mm; line-height:1.6;}
.bia-the{
  display:inline-block; padding:2.4mm 8mm; border:1pt solid var(--kim-sang);
  border-radius:20mm; background:rgba(255,255,255,.62);
  font-size:10.5pt; color:var(--kim-dam); font-weight:600; letter-spacing:.05em;
}
.bia-chan{
  position:absolute; bottom:19mm; left:0; right:0;
  font-size:10pt; color:var(--chu-phu);
}
.bia-chan strong{color:var(--kim-dam);}

/* ===================== MỤC LỤC ===================== */
.ml{ page-break-after:always; padding-top:4mm; }
.ml h2{
  font-size:19pt; color:var(--kim-dam); margin:0 0 2mm; border:0; padding:0;
  text-align:center; letter-spacing:.03em;
}
.ml-gach{width:38mm;height:1.4pt;margin:0 auto 9mm;
  background:linear-gradient(90deg,transparent,var(--kim-sang),transparent);}
.ml-phan{
  display:flex; align-items:baseline; gap:4mm; margin:7mm 0 2.5mm;
  padding-bottom:1.6mm; border-bottom:1pt solid var(--kim-sang);
}
.ml-so{
  font-size:8.6pt; letter-spacing:.14em; text-transform:uppercase;
  color:#fff; background:var(--kim); padding:.9mm 3mm; border-radius:8mm; font-weight:700;
}
.ml-phan a{ font-size:14pt; font-weight:700; color:var(--kim-dam); text-decoration:none;
  font-family:"Palatino Linotype",Palatino,Georgia,"DejaVu Serif",serif;}
.ml-ds{ list-style:none; margin:0; padding:0 0 0 2mm;
  column-count:2; column-gap:9mm; }
.ml-ds li{ padding:1.4mm 0; border-bottom:.4pt dotted var(--vien);
  break-inside:avoid; page-break-inside:avoid; }
.ml-ds a{ color:var(--chu); text-decoration:none; font-size:9.8pt; border:0; }

/* ===================== BÌA PHẦN ===================== */
.phan{ page-break-before:always; }
.phan-bia{ text-align:center; padding:14mm 0 10mm; margin-bottom:6mm; }
.phan-so{
  display:inline-block; font-size:8.8pt; letter-spacing:.2em; font-weight:700;
  color:#fff; background:linear-gradient(120deg,var(--kim),var(--kim-dam));
  padding:1.4mm 6mm; border-radius:10mm; margin-bottom:5mm;
}
.phan-ten{ font-size:25pt; color:var(--kim-dam); margin:0 0 3mm; border:0; padding:0; }
.phan-mo-ta{ font-size:11.5pt; color:var(--chu-phu); }
.hoa-van{ display:flex; align-items:center; justify-content:center; gap:3mm; margin-top:7mm; }
.hoa-van span{ display:block; height:1pt; width:26mm;
  background:linear-gradient(90deg,transparent,var(--kim-sang)); }
.hoa-van span:last-child{ background:linear-gradient(90deg,var(--kim-sang),transparent); }
.hoa-van .giua{ width:3mm; height:3mm; background:var(--kim-sang);
  transform:rotate(45deg); border-radius:.6mm; }

/* ===================== NỘI DUNG ===================== */
h1{ font-size:20pt; color:var(--kim-dam); margin:11mm 0 4mm; }
/* Các mốc "Phần A / Phần B..." trong tài liệu con luôn bắt đầu trang mới */
section.phan > h1{ page-break-before:always; margin-top:2mm; padding-bottom:2.5mm;
  border-bottom:1.6pt solid var(--kim-sang); }
h2{
  font-size:15.5pt; color:var(--kim-dam); margin:9mm 0 3.5mm;
  padding-bottom:2mm; border-bottom:1.1pt solid var(--kim-sang);
  page-break-after:avoid;
}
h3{ font-size:12.4pt; color:#8d6c1a; margin:6.5mm 0 2.5mm; page-break-after:avoid; }
h4{ font-size:11pt; color:var(--chu); margin:5mm 0 2mm; page-break-after:avoid; }
p{ margin:0 0 3.2mm; text-align:justify; }
p.dan-de{ font-weight:700; color:var(--kim-dam); margin-top:5mm; }
a{ color:#8d6c1a; text-decoration:none; border-bottom:.4pt solid rgba(168,129,31,.4); }
strong{ color:#453d2d; }
hr{ border:0; height:1pt; margin:8mm 0;
  background:linear-gradient(90deg,transparent,var(--kim-sang),transparent); }
ul,ol{ margin:0 0 3.4mm; padding-left:6mm; }
li{ margin-bottom:1.4mm; }
li::marker{ color:var(--kim); }
ul.ds-con,ol.ds-con{ margin:1.4mm 0 1mm; padding-left:5mm; }
ul.ds-con>li{ margin-bottom:1mm; }

code{
  font-family:"Cascadia Mono",Consolas,"DejaVu Sans Mono","Liberation Mono",monospace;
  font-size:.88em; background:var(--kim-nhat); color:#6d5310;
  border:.4pt solid var(--vien); border-radius:1mm; padding:.3mm 1.2mm;
  overflow-wrap:anywhere; word-break:break-word;
}
pre.ma{
  background:#2f2a21; color:#f2e6c8; border-radius:2mm; padding:4mm 5mm;
  overflow:hidden; margin:0 0 4mm; border-left:1.6mm solid var(--kim-sang);
  page-break-inside:avoid;
}
pre.ma code{
  background:none; border:0; padding:0; color:inherit; font-size:8.9pt; line-height:1.6;
  white-space:pre-wrap; word-break:break-word;
}

blockquote{
  margin:0 0 4mm; padding:3.2mm 5mm; background:var(--kim-nhat);
  border-left:1.6mm solid var(--kim-sang); border-radius:0 2mm 2mm 0;
  color:#5f5340; page-break-inside:avoid;
}
blockquote p{ margin:0; }

.bang-bao{ margin:0 0 5mm; page-break-inside:avoid; }
table{ width:100%; border-collapse:collapse; font-size:9.3pt; }
thead th{
  background:linear-gradient(120deg,var(--kim),var(--kim-dam)); color:#fff;
  text-align:left; font-weight:700; padding:2.2mm 3mm; font-size:8.9pt;
  letter-spacing:.03em;
}
thead th:first-child{ border-top-left-radius:1.6mm; }
thead th:last-child{ border-top-right-radius:1.6mm; }
tbody td{ padding:2mm 3mm; border-bottom:.4pt solid var(--vien); vertical-align:top; }
tbody tr:nth-child(even){ background:#faf5e9; }
tbody tr:last-child td{ border-bottom:1pt solid var(--kim-sang); }

figure{ margin:0 0 6mm; page-break-inside:avoid; text-align:center; }
figure img{ max-width:100%; height:auto; }
figure.so-do img{ max-height:212mm; }
figure.anh-chup img{
  max-height:135mm; border:1pt solid var(--vien); border-radius:2mm;
  box-shadow:0 1.4mm 5mm rgba(122,92,21,.14);
}
figcaption{
  margin-top:2.4mm; font-size:8.8pt; color:var(--chu-nhat); font-style:italic;
}
</style>
</head>
<body>

<div class="bia">
  <div class="bia-vien"></div>
  <div class="bia-dau">
    <svg viewBox="0 0 24 24" fill="none" stroke="#fffdf5" stroke-width="1.7"
         stroke-linecap="round" stroke-linejoin="round">
      <rect x="2" y="4.5" width="20" height="15" rx="2.5"/>
      <path d="M2.6 6.2 12 13.2 21.4 6.2"/>
    </svg>
  </div>
  <div class="bia-don-vi">Sở Giáo dục và Đào tạo Đồng Nai</div>
  <div class="bia-don-vi-2">Phòng Giáo dục Phổ thông — Giáo dục Thường xuyên</div>
  <h1>Hệ thống phân luồng<br>Mail công vụ</h1>
  <div class="bia-gach"></div>
  <div class="bia-phu">Tài liệu quy trình kỹ thuật<br>và hướng dẫn sử dụng</div>
  <div class="bia-the">Phiên bản ${PHIEN_BAN} · build ${SO_BUILD} · ${NGAY}</div>
  <div class="bia-chan">Thiết kế bởi <strong>Trương Anh Tuấn</strong></div>
</div>

<div class="ml">
  <h2>Mục lục</h2>
  <div class="ml-gach"></div>
  ${ml}
</div>

${than}

</body>
</html>`;
}

/* ===================================================================
 *  4. Chạy
 * =================================================================== */

function timTrinhDuyet() {
  const ung_vien = [
    process.env.CHROME,
    '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
    '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome',
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
  ].filter(Boolean);
  for (const t of ung_vien) { try { if (fs.existsSync(t)) return t; } catch (e) { /* bỏ qua */ } }
  // Chromium do playwright tải về, tên thư mục thay đổi theo phiên bản
  try {
    const g = '/opt/pw-browsers';
    for (const d of fs.readdirSync(g)) {
      const t = path.join(g, d, 'chrome-linux', 'chrome');
      if (fs.existsSync(t)) return t;
    }
  } catch (e) { /* bỏ qua */ }
  return null;
}

async function main() {
  fs.mkdirSync(RA, { recursive: true });
  const tep_html = path.join(RA, 'tai-lieu.html');
  fs.writeFileSync(tep_html, dungHtml(), 'utf8');
  console.log('Đã dựng: ' + path.relative(GOC, tep_html));

  const trinh_duyet = timTrinhDuyet();
  if (!trinh_duyet) {
    console.log('Không tìm thấy Chrome/Chromium — bỏ qua bước in PDF.');
    console.log('Mở tệp HTML trên rồi Ctrl+P (chọn khổ A4, bật "In màu nền") để có bản PDF.');
    return;
  }

  let chromium;
  try { ({ chromium } = require('playwright-core')); }
  catch (e) {
    console.log('Chưa có gói playwright-core — bỏ qua bước in PDF.');
    console.log('Cài bằng: npm i -D playwright-core');
    return;
  }

  const tep_pdf = path.join(RA, 'He-thong-phan-luong-Mail-cong-vu.pdf');
  const b = await chromium.launch({
    executablePath: trinh_duyet,
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });
  const p = await (await b.newContext()).newPage();
  await p.goto('file://' + tep_html, { waitUntil: 'networkidle' });
  await p.emulateMedia({ media: 'print' });
  await p.pdf({
    path: tep_pdf,
    // Dùng khổ giấy khai trong CSS để quy tắc @page:first (bìa tràn lề) có hiệu lực
    preferCSSPageSize: true,
    printBackground: true,
    displayHeaderFooter: true,
    headerTemplate: '<div style="width:100%;font-size:7pt;color:#a8811f;'
      + 'font-family:Georgia,serif;padding:0 15mm;display:flex;justify-content:space-between;'
      + 'border-bottom:.5pt solid #e3c766;padding-bottom:2mm;">'
      + '<span>Hệ thống phân luồng Mail công vụ</span>'
      + '<span>Phòng GDPT-GDTX · Sở GD&amp;ĐT Đồng Nai</span></div>',
    footerTemplate: '<div style="width:100%;font-size:7pt;color:#948870;'
      + 'font-family:Georgia,serif;padding:0 15mm;display:flex;justify-content:space-between;'
      + 'border-top:.5pt solid #e6dcc4;padding-top:2mm;">'
      + '<span>Thiết kế bởi Trương Anh Tuấn</span>'
      + '<span>Trang <span class="pageNumber"></span>/<span class="totalPages"></span></span></div>',
  });
  await b.close();

  const co = (fs.statSync(tep_pdf).size / 1048576).toFixed(2);
  console.log('Đã in: ' + path.relative(GOC, tep_pdf) + ' (' + co + ' MB)');
}

main().catch((e) => { console.error('Lỗi: ' + e.message); process.exit(1); });
