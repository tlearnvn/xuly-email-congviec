/* =====================================================================
 *  xem-office.js - Xem trực tiếp tệp Word / Excel / PowerPoint
 *  HỆ THỐNG PHÂN LUỒNG MAIL CÔNG VỤ
 *  Thiết kế bởi Trương Anh Tuấn
 * ---------------------------------------------------------------------
 *  Vì sao tự viết thay vì nhúng Office Online hay Google Docs Viewer:
 *  hai dịch vụ đó bắt buộc tệp phải CÔNG KHAI trên Internet để máy chủ
 *  của Microsoft/Google tải về được. Hồ sơ công vụ thì không thể. Ở đây
 *  tệp đi thẳng từ máy chủ của Sở về trình duyệt cán bộ rồi dựng lại tại
 *  chỗ - không có bên thứ ba nào nhìn thấy nội dung.
 *
 *  .docx/.xlsx/.pptx đều là tệp ZIP chứa XML (Office Open XML), nên chỉ
 *  cần hai thứ trình duyệt đã có sẵn: DecompressionStream('deflate-raw')
 *  để giải nén và DOMParser để đọc XML. Không thư viện ngoài.
 *
 *  Tệp .doc/.xls/.ppt đời cũ là định dạng nhị phân khác hẳn, không đọc
 *  được bằng cách này - chỗ gọi phải tự lo, đừng mời người dùng bấm Xem.
 * =====================================================================
 */
(function () {
  'use strict';

  // =============== Đọc tệp ZIP ===============
  const TEN_ZIP = new TextDecoder('utf-8');

  function u16(d, p) { return d[p] | (d[p + 1] << 8); }
  function u32(d, p) { return (d[p] | (d[p + 1] << 8) | (d[p + 2] << 16)) + d[p + 3] * 0x1000000; }

  async function giaiNen(nen, phuongThuc) {
    if (phuongThuc === 0) return nen;                    // stored - không nén
    if (phuongThuc !== 8) throw new Error('Tệp dùng cách nén ZIP không hỗ trợ (mã ' + phuongThuc + ')');
    const ds = new DecompressionStream('deflate-raw');
    const ghi = ds.writable.getWriter();
    ghi.write(nen); ghi.close();
    const manh = [];
    let tong = 0;
    const doc = ds.readable.getReader();
    for (;;) {
      const { done, value } = await doc.read();
      if (done) break;
      manh.push(value); tong += value.length;
    }
    const ra = new Uint8Array(tong);
    let o = 0;
    for (const m of manh) { ra.set(m, o); o += m.length; }
    return ra;
  }

  /** Đọc toàn bộ ZIP -> Map<đường dẫn trong tệp, Uint8Array> */
  async function docZip(buf) {
    const d = new Uint8Array(buf);
    // Tìm End Of Central Directory từ cuối lên (có thể có comment ở đuôi)
    let eocd = -1;
    for (let i = d.length - 22; i >= 0 && i >= d.length - 22 - 65536; i--) {
      if (d[i] === 0x50 && d[i + 1] === 0x4b && d[i + 2] === 0x05 && d[i + 3] === 0x06) { eocd = i; break; }
    }
    if (eocd < 0) throw new Error('Không phải tệp Office hợp lệ (thiếu cấu trúc ZIP)');
    let soPhan = u16(d, eocd + 10);
    let batDau = u32(d, eocd + 16);
    if (soPhan === 0xffff || batDau === 0xffffffff)
      throw new Error('Tệp quá lớn (ZIP64), hãy tải về máy để mở');

    const kho = new Map();
    let p = batDau;
    for (let n = 0; n < soPhan; n++) {
      if (u32(d, p) !== 0x02014b50) break;              // hết mục hợp lệ
      const pt      = u16(d, p + 10);                    // phương thức nén
      const cLen    = u32(d, p + 20);                    // độ dài đã nén
      const nLen    = u16(d, p + 28);
      const eLen    = u16(d, p + 30);
      const cmLen   = u16(d, p + 32);
      const viTriLh = u32(d, p + 42);                    // vị trí local header
      const ten     = TEN_ZIP.decode(d.subarray(p + 46, p + 46 + nLen));
      p += 46 + nLen + eLen + cmLen;

      if (ten.endsWith('/')) continue;                   // thư mục
      // Local header có thể mang extra field khác central directory, đọc lại
      if (u32(d, viTriLh) !== 0x04034b50) continue;
      const nLen2 = u16(d, viTriLh + 26);
      const eLen2 = u16(d, viTriLh + 28);
      const batDauDL = viTriLh + 30 + nLen2 + eLen2;
      kho.set(ten, { pt: pt, du_lieu: d.subarray(batDauDL, batDauDL + cLen) });
    }
    // Giải nén sẵn (tệp Office của một bức thư đủ nhỏ để làm luôn một lượt)
    const ra = new Map();
    for (const [ten, m] of kho) ra.set(ten, await giaiNen(m.du_lieu, m.pt));
    return ra;
  }

  const XML = new DOMParser();
  function docXml(kho, ten) {
    const b = kho.get(ten);
    if (!b) return null;
    const t = new TextDecoder('utf-8').decode(b);
    const doc = XML.parseFromString(t, 'application/xml');
    if (doc.querySelector('parsererror')) return null;
    return doc;
  }

  /** Đọc tệp .rels -> Map<Id, Target> */
  function docRels(kho, ten) {
    const m = new Map();
    const doc = docXml(kho, ten);
    if (!doc) return m;
    for (const r of doc.getElementsByTagName('Relationship'))
      m.set(r.getAttribute('Id'), r.getAttribute('Target') || '');
    return m;
  }

  // Bỏ "../" và "/" đầu, ghép về đường dẫn trong ZIP
  function ghepDuongDan(gocThuMuc, dich) {
    if (/^https?:/i.test(dich)) return null;             // link ngoài, không phải phần trong tệp
    let d = dich.replace(/^\/+/, '');
    let g = gocThuMuc;
    while (d.startsWith('../')) { d = d.slice(3); g = g.replace(/[^/]*\/$/, ''); }
    return g + d;
  }

  const LOAI_ANH = {
    png: 'image/png', jpg: 'image/jpeg', jpeg: 'image/jpeg', gif: 'image/gif',
    bmp: 'image/bmp', webp: 'image/webp', svg: 'image/svg+xml', tiff: 'image/tiff', emf: '', wmf: ''
  };

  function anhSangUrl(kho, duongDan) {
    const b = kho.get(duongDan);
    if (!b) return null;
    const duoi = (duongDan.split('.').pop() || '').toLowerCase();
    const mime = LOAI_ANH[duoi];
    if (!mime) return null;                              // EMF/WMF trình duyệt không vẽ được
    return URL.createObjectURL(new Blob([b], { type: mime }));
  }

  // =============== Tiện ích DOM ===============
  function the(ten, lop, chu) {
    const e = document.createElement(ten);
    if (lop) e.className = lop;
    if (chu != null) e.textContent = chu;
    return e;
  }
  // Lấy con trực tiếp theo tên (bỏ tiền tố namespace)
  function conTheoTen(el, ten) {
    const ra = [];
    for (let c = el.firstElementChild; c; c = c.nextElementSibling)
      if (c.localName === ten) ra.push(c);
    return ra;
  }
  function conDau(el, ten) {
    for (let c = el.firstElementChild; c; c = c.nextElementSibling)
      if (c.localName === ten) return c;
    return null;
  }

  // =====================================================================
  //  WORD (.docx)
  // =====================================================================
  /**
   * Bảng "numId -> đánh số hay gạch đầu dòng" lấy từ word/numbering.xml.
   * Không đọc tệp này thì không biết danh sách là <ol> hay <ul>, vì trong
   * document.xml chỉ có mã numId chứ không nói kiểu.
   */
  function docKieuDanhSach(kho) {
    const ra = new Map();                                // numId -> true nếu có đánh số
    const doc = docXml(kho, 'word/numbering.xml');
    if (!doc) return ra;
    const truuTuong = new Map();                         // abstractNumId -> có đánh số
    for (const an of doc.getElementsByTagName('w:abstractNum')) {
      const id = an.getAttribute('w:abstractNumId');
      let coSo = false;
      for (const lvl of an.getElementsByTagName('w:lvl')) {
        if ((lvl.getAttribute('w:ilvl') || '0') !== '0') continue;
        const fmt = lvl.getElementsByTagName('w:numFmt')[0];
        const v = fmt && fmt.getAttribute('w:val');
        coSo = !!v && v !== 'bullet' && v !== 'none';
        break;
      }
      truuTuong.set(id, coSo);
    }
    for (const n of doc.getElementsByTagName('w:num')) {
      const id = n.getAttribute('w:numId');
      const a = n.getElementsByTagName('w:abstractNumId')[0];
      const aid = a && a.getAttribute('w:val');
      ra.set(id, aid != null && !!truuTuong.get(aid));
    }
    return ra;
  }

  function dungWord(kho) {
    const doc = docXml(kho, 'word/document.xml');
    if (!doc) throw new Error('Không đọc được nội dung Word (thiếu word/document.xml)');
    const rels = docRels(kho, 'word/_rels/document.xml.rels');
    const kieuDs = docKieuDanhSach(kho);
    const than = doc.getElementsByTagName('w:body')[0] || doc.documentElement;

    const goc = the('div', 'vp-word');
    let dsHienTai = null, kieuDsHienTai = null;

    // Một "run": chữ kèm định dạng in đậm/nghiêng/gạch chân/màu
    function dungRun(r, vaoDau) {
      const rPr = conDau(r, 'rPr');
      for (const c of r.children) {
        const t = c.localName;
        if (t === 'br') { vaoDau.appendChild(document.createElement('br')); continue; }
        if (t === 'tab') { vaoDau.appendChild(document.createTextNode('    ')); continue; }
        if (t === 'drawing' || t === 'pict') {
          const blip = c.getElementsByTagName('a:blip')[0]
                    || c.getElementsByTagName('v:imagedata')[0];
          const id = blip && (blip.getAttribute('r:embed') || blip.getAttribute('r:id'));
          const dich = id && rels.get(id);
          const dd = dich && ghepDuongDan('word/', dich);
          const url = dd && anhSangUrl(kho, dd);
          if (url) {
            const img = the('img', 'vp-anh');
            img.src = url;
            img.alt = 'Hình trong văn bản';
            vaoDau.appendChild(img);
          } else {
            vaoDau.appendChild(the('span', 'vp-thieu', '[hình không hiển thị được]'));
          }
          continue;
        }
        if (t !== 't') continue;
        let nut = document.createTextNode(c.textContent);
        if (rPr) {
          const boc = (tenThe) => { const e = document.createElement(tenThe); e.appendChild(nut); nut = e; };
          const co = (n) => {
            const x = conDau(rPr, n);
            return x && x.getAttribute('w:val') !== '0' && x.getAttribute('w:val') !== 'false' &&
                   x.getAttribute('w:val') !== 'none';
          };
          if (co('b')) boc('strong');
          if (co('i')) boc('em');
          if (co('u')) boc('u');
          if (co('strike')) boc('s');
          const vt = conDau(rPr, 'vertAlign');
          if (vt) {
            const v = vt.getAttribute('w:val');
            if (v === 'superscript') boc('sup');
            else if (v === 'subscript') boc('sub');
          }
          const mau = conDau(rPr, 'color');
          const gt = mau && mau.getAttribute('w:val');
          if (gt && /^[0-9A-Fa-f]{6}$/.test(gt) && gt.toLowerCase() !== '000000') {
            const s = document.createElement('span');
            s.style.color = '#' + gt;
            s.appendChild(nut); nut = s;
          }
        }
        vaoDau.appendChild(nut);
      }
    }

    function dungDoan(p, vaoDau) {
      const pPr = conDau(p, 'pPr');
      const kieuEl = pPr && conDau(pPr, 'pStyle');
      const kieu = (kieuEl && kieuEl.getAttribute('w:val')) || '';
      const numPr = pPr && conDau(pPr, 'numPr');

      // Danh sách nhận diện theo hai đường: có w:numPr trong chính đoạn, hoặc
      // đoạn mang style ListBullet/ListNumber (Word đẩy w:numPr vào styles.xml
      // nên document.xml không có, dễ tưởng là đoạn văn thường).
      const kieuList = /^List(Bullet|Number|Paragraph)/i.test(kieu);
      if ((numPr || kieuList) && vaoDau === goc) {
        let soThuTu = /^ListNumber/i.test(kieu);
        if (numPr) {
          const numId = conDau(numPr, 'numId');
          const idv = numId && numId.getAttribute('w:val');
          if (idv != null && kieuDs.has(idv)) soThuTu = !!kieuDs.get(idv);
          else if (/Number|Ordered/i.test(kieu)) soThuTu = true;
        }
        const canKieu = soThuTu ? 'ol' : 'ul';
        if (!dsHienTai || kieuDsHienTai !== canKieu) {
          dsHienTai = the(canKieu, 'vp-ds');
          kieuDsHienTai = canKieu;
          goc.appendChild(dsHienTai);
        }
        const li = the('li');
        for (const r of conTheoTen(p, 'r')) dungRun(r, li);
        for (const h of conTheoTen(p, 'hyperlink'))
          for (const r of conTheoTen(h, 'r')) dungRun(r, li);
        dsHienTai.appendChild(li);
        return;
      }
      dsHienTai = null; kieuDsHienTai = null;

      let tenThe = 'p';
      const m = /^Heading(\d)/i.exec(kieu) || /^Tieude(\d)/i.exec(kieu);
      if (m) tenThe = 'h' + Math.min(6, parseInt(m[1], 10) + 1);
      else if (/^Title$/i.test(kieu)) tenThe = 'h1';
      else if (/^Subtitle$/i.test(kieu)) tenThe = 'h3';

      const el = the(tenThe);
      const jc = pPr && conDau(pPr, 'jc');
      const canLe = jc && jc.getAttribute('w:val');
      if (canLe === 'center') el.style.textAlign = 'center';
      else if (canLe === 'right') el.style.textAlign = 'right';
      else if (canLe === 'both') el.style.textAlign = 'justify';

      for (const c of p.children) {
        if (c.localName === 'r') dungRun(c, el);
        else if (c.localName === 'hyperlink') {
          const id = c.getAttribute('r:id');
          const dich = id && rels.get(id);
          if (dich && /^https?:/i.test(dich)) {
            const a = the('a');
            a.href = dich; a.target = '_blank';
            a.rel = 'noopener noreferrer nofollow';
            for (const r of conTheoTen(c, 'r')) dungRun(r, a);
            el.appendChild(a);
          } else {
            for (const r of conTheoTen(c, 'r')) dungRun(r, el);
          }
        }
      }
      if (!el.textContent.trim() && !el.querySelector('img')) el.appendChild(the('span', null, ' '));
      vaoDau.appendChild(el);
    }

    function dungBang(tbl, vaoDau) {
      dsHienTai = null; kieuDsHienTai = null;
      const boc = the('div', 'vp-cuon');
      const t = the('table', 'vp-bang');
      let dongDau = true;
      for (const tr of conTheoTen(tbl, 'tr')) {
        const hang = the('tr');
        for (const tc of conTheoTen(tr, 'tc')) {
          const tcPr = conDau(tc, 'tcPr');
          const o = the(dongDau ? 'th' : 'td');
          const gop = tcPr && conDau(tcPr, 'gridSpan');
          if (gop) o.colSpan = parseInt(gop.getAttribute('w:val'), 10) || 1;
          for (const c of tc.children) {
            if (c.localName === 'p') dungDoan(c, o);
            else if (c.localName === 'tbl') dungBang(c, o);
          }
          hang.appendChild(o);
        }
        if (hang.children.length) t.appendChild(hang);
        dongDau = false;
      }
      boc.appendChild(t);
      vaoDau.appendChild(boc);
    }

    for (const c of than.children) {
      if (c.localName === 'p') dungDoan(c, goc);
      else if (c.localName === 'tbl') dungBang(c, goc);
    }
    if (!goc.textContent.trim() && !goc.querySelector('img'))
      throw new Error('Tệp Word này không có nội dung văn bản để hiển thị');
    return goc;
  }

  // =====================================================================
  //  EXCEL (.xlsx)
  // =====================================================================
  // Ô Excel lưu ngày dưới dạng số ngày kể từ 30/12/1899. Muốn biết ô nào là
  // ngày thì phải tra định dạng số của ô trong styles.xml.
  const NGAY_CO_SAN = new Set([14,15,16,17,18,19,20,21,22,27,28,29,30,31,32,33,34,35,36,45,46,47,50,51,52,53,54,55,56,57,58]);

  function docDinhDangSo(kho) {
    const doc = docXml(kho, 'xl/styles.xml');
    const laNgay = [];
    if (!doc) return laNgay;
    const tuDinhNghia = new Map();
    for (const n of doc.getElementsByTagName('numFmt')) {
      const ma = parseInt(n.getAttribute('numFmtId'), 10);
      const code = n.getAttribute('formatCode') || '';
      // Bỏ phần trong ngoặc kép rồi mới xét, tránh chữ "y" trong nhãn tiền tệ
      const sach = code.replace(/"[^"]*"/g, '').replace(/\[[^\]]*\]/g, '');
      tuDinhNghia.set(ma, /[ymdhs]/i.test(sach) && !/^[^ymdhs]*$/i.test(sach));
    }
    const cellXfs = doc.getElementsByTagName('cellXfs')[0];
    if (!cellXfs) return laNgay;
    let i = 0;
    for (const xf of conTheoTen(cellXfs, 'xf')) {
      const ma = parseInt(xf.getAttribute('numFmtId') || '0', 10);
      laNgay[i++] = tuDinhNghia.has(ma) ? !!tuDinhNghia.get(ma) : NGAY_CO_SAN.has(ma);
    }
    return laNgay;
  }

  function soSangNgay(n) {
    // Excel coi 1900 là năm nhuận (sai) nên mốc là 30/12/1899
    const ms = Math.round((n - 25569) * 86400000);
    const d = new Date(ms);
    if (isNaN(d.getTime())) return String(n);
    const hai = (x) => String(x).padStart(2, '0');
    const ngay = hai(d.getUTCDate()) + '/' + hai(d.getUTCMonth() + 1) + '/' + d.getUTCFullYear();
    const phanLe = n - Math.floor(n);
    if (phanLe < 1e-6) return ngay;
    return ngay + ' ' + hai(d.getUTCHours()) + ':' + hai(d.getUTCMinutes());
  }

  function chuSoO(diaChi) {                              // "B12" -> 2
    let c = 0;
    for (let i = 0; i < diaChi.length; i++) {
      const k = diaChi.charCodeAt(i);
      if (k < 65 || k > 90) break;
      c = c * 26 + (k - 64);
    }
    return c;
  }

  function docChuoiChung(kho) {
    const doc = docXml(kho, 'xl/sharedStrings.xml');
    const ra = [];
    if (!doc) return ra;
    for (const si of conTheoTen(doc.documentElement, 'si')) {
      // <si> có thể là <t> đơn hoặc nhiều <r><t>
      let s = '';
      for (const t of si.getElementsByTagName('t')) s += t.textContent;
      ra.push(s);
    }
    return ra;
  }

  function dungExcel(kho) {
    const wb = docXml(kho, 'xl/workbook.xml');
    if (!wb) throw new Error('Không đọc được nội dung Excel (thiếu xl/workbook.xml)');
    const rels = docRels(kho, 'xl/_rels/workbook.xml.rels');
    const chuoi = docChuoiChung(kho);
    const laNgay = docDinhDangSo(kho);

    const bangTen = [];
    const sheets = wb.getElementsByTagName('sheet');
    for (const s of sheets) {
      const id = s.getAttribute('r:id');
      let dich = (id && rels.get(id)) || '';
      let dd = dich ? ghepDuongDan('xl/', dich) : '';
      if (!dd || !kho.has(dd)) {
        // Một số công cụ ghi rels khác chuẩn: đoán theo thứ tự
        const doan = 'xl/worksheets/sheet' + (bangTen.length + 1) + '.xml';
        if (kho.has(doan)) dd = doan; else continue;
      }
      bangTen.push({ ten: s.getAttribute('name') || ('Sheet' + (bangTen.length + 1)), duong_dan: dd });
    }
    if (!bangTen.length) throw new Error('Tệp Excel này không có bảng tính nào');

    const goc = the('div', 'vp-excel');
    if (bangTen.length > 1) {
      const thanh = the('div', 'vp-tab');
      bangTen.forEach((b, i) => {
        const nut = the('button', 'vp-tab-nut' + (i === 0 ? ' dang-chon' : ''), b.ten);
        nut.type = 'button';
        nut.dataset.chiSo = String(i);
        thanh.appendChild(nut);
      });
      thanh.addEventListener('click', (ev) => {
        const nut = ev.target.closest('.vp-tab-nut');
        if (!nut) return;
        const i = nut.dataset.chiSo;
        thanh.querySelectorAll('.vp-tab-nut').forEach((x) => x.classList.toggle('dang-chon', x === nut));
        goc.querySelectorAll('.vp-sheet').forEach((x) => { x.hidden = x.dataset.chiSo !== i; });
      });
      goc.appendChild(thanh);
    }

    bangTen.forEach((b, chiSo) => {
      const doc = docXml(kho, b.duong_dan);
      const khung = the('div', 'vp-sheet');
      khung.dataset.chiSo = String(chiSo);
      khung.hidden = chiSo !== 0;

      // Gom ô đã gộp để không in lặp
      const daGop = new Map();                           // "R,C" -> {colSpan, rowSpan} hoặc 'an'
      const mc = doc && doc.getElementsByTagName('mergeCell');
      if (mc) for (const m of mc) {
        const ref = m.getAttribute('ref') || '';
        const p = ref.split(':');
        if (p.length !== 2) continue;
        const r1 = parseInt(p[0].replace(/\D/g, ''), 10), c1 = chuSoO(p[0]);
        const r2 = parseInt(p[1].replace(/\D/g, ''), 10), c2 = chuSoO(p[1]);
        for (let r = r1; r <= r2; r++)
          for (let c = c1; c <= c2; c++)
            daGop.set(r + ',' + c, (r === r1 && c === c1)
              ? { colSpan: c2 - c1 + 1, rowSpan: r2 - r1 + 1 } : 'an');
      }

      const sheetData = doc && doc.getElementsByTagName('sheetData')[0];
      const hangs = sheetData ? conTheoTen(sheetData, 'row') : [];
      if (!hangs.length) {
        khung.appendChild(the('p', 'vp-rong', 'Bảng tính “' + b.ten + '” không có dữ liệu.'));
        goc.appendChild(khung);
        return;
      }

      // Số cột rộng nhất, để các dòng thiếu ô vẫn thẳng hàng
      let maxCot = 0;
      for (const r of hangs)
        for (const c of conTheoTen(r, 'c'))
          maxCot = Math.max(maxCot, chuSoO(c.getAttribute('r') || ''));

      const boc = the('div', 'vp-cuon');
      const t = the('table', 'vp-bang vp-bang-excel');
      let soHangTruoc = 0;
      for (const r of hangs) {
        const soHang = parseInt(r.getAttribute('r') || '0', 10) || (soHangTruoc + 1);
        // Giữ lại các dòng trống ở giữa để bố cục không bị co lại
        for (let k = soHangTruoc + 1; k < soHang; k++) {
          const tr = the('tr', 'vp-hang-trong');
          const td = the('td'); td.colSpan = maxCot || 1;
          td.appendChild(the('span', null, ' '));
          tr.appendChild(td); t.appendChild(tr);
        }
        soHangTruoc = soHang;

        const tr = the('tr');
        const o = the('th', 'vp-so-hang', String(soHang));
        tr.appendChild(o);
        let cotTruoc = 0;
        for (const c of conTheoTen(r, 'c')) {
          const dc = c.getAttribute('r') || '';
          const cot = chuSoO(dc) || (cotTruoc + 1);
          for (let k = cotTruoc + 1; k < cot; k++) {
            if (daGop.get(soHang + ',' + k) === 'an') continue;
            tr.appendChild(the('td'));
          }
          cotTruoc = cot;

          const gop = daGop.get(soHang + ',' + cot);
          if (gop === 'an') continue;

          const kieu = c.getAttribute('t') || 'n';
          const vEl = conDau(c, 'v');
          const isEl = conDau(c, 'is');
          let chu = '';
          if (kieu === 's') {
            const i = parseInt(vEl ? vEl.textContent : '-1', 10);
            chu = (i >= 0 && i < chuoi.length) ? chuoi[i] : '';
          } else if (kieu === 'inlineStr') {
            if (isEl) for (const tt of isEl.getElementsByTagName('t')) chu += tt.textContent;
          } else if (kieu === 'b') {
            chu = (vEl && vEl.textContent === '1') ? 'ĐÚNG' : 'SAI';
          } else if (kieu === 'e') {
            chu = vEl ? vEl.textContent : '#LỖI';
          } else if (kieu === 'str') {
            chu = vEl ? vEl.textContent : '';
          } else {
            const thoSo = vEl ? vEl.textContent : '';
            const so = parseFloat(thoSo);
            const iStyle = parseInt(c.getAttribute('s') || '-1', 10);
            if (thoSo !== '' && !isNaN(so) && iStyle >= 0 && laNgay[iStyle] && so > 0) {
              chu = soSangNgay(so);
            } else if (thoSo !== '' && !isNaN(so)) {
              // In số theo lối Việt Nam, tránh làm tròn mất chữ số thật
              chu = Number.isInteger(so) ? so.toLocaleString('vi-VN')
                                         : so.toLocaleString('vi-VN', { maximumFractionDigits: 10 });
            } else {
              chu = thoSo;
            }
          }
          const laSo = (kieu === 'n' || kieu === '') && chu !== '' && !isNaN(parseFloat(vEl ? vEl.textContent : ''));
          const fEl = conDau(c, 'f');
          let lopO = laSo ? 'vp-so' : null;
          if (fEl && chu === '') {
            // Ô công thức mà tệp không lưu kèm kết quả (hay gặp ở tệp do công cụ
            // tự sinh). Để trống thì người xem tưởng ô rỗng, nên hiện công thức.
            chu = '=' + fEl.textContent;
            lopO = 'vp-congthuc';
          }
          const td = the('td', lopO, chu);
          if (fEl) td.title = chu.charAt(0) === '='
            ? 'Ô công thức: tệp không lưu kèm kết quả nên chỉ hiển thị được công thức'
            : 'Ô có công thức — giá trị hiển thị là kết quả đã lưu trong tệp';
          if (gop) { td.colSpan = gop.colSpan; td.rowSpan = gop.rowSpan; }
          tr.appendChild(td);
        }
        t.appendChild(tr);
      }
      boc.appendChild(t);
      khung.appendChild(boc);
      goc.appendChild(khung);
    });
    return goc;
  }

  // =====================================================================
  //  POWERPOINT (.pptx)
  // =====================================================================
  function dungPowerPoint(kho) {
    const pres = docXml(kho, 'ppt/presentation.xml');
    if (!pres) throw new Error('Không đọc được nội dung PowerPoint (thiếu ppt/presentation.xml)');
    const rels = docRels(kho, 'ppt/_rels/presentation.xml.rels');

    const dsSlide = [];
    for (const s of pres.getElementsByTagName('p:sldId')) {
      const id = s.getAttribute('r:id');
      const dich = id && rels.get(id);
      const dd = dich && ghepDuongDan('ppt/', dich);
      if (dd && kho.has(dd)) dsSlide.push(dd);
    }
    if (!dsSlide.length) {                               // dự phòng: quét theo tên
      const ten = [...kho.keys()].filter((k) => /^ppt\/slides\/slide\d+\.xml$/.test(k))
        .sort((a, b) => (parseInt(a.match(/\d+/)[0], 10) - parseInt(b.match(/\d+/)[0], 10)));
      dsSlide.push(...ten);
    }
    if (!dsSlide.length) throw new Error('Tệp PowerPoint này không có trang chiếu nào');

    const goc = the('div', 'vp-ppt');
    dsSlide.forEach((dd, i) => {
      const doc = docXml(kho, dd);
      const relSlide = docRels(kho, dd.replace(/slides\//, 'slides/_rels/') + '.rels');
      const khung = the('section', 'vp-slide');
      khung.appendChild(the('div', 'vp-slide-so', 'Trang ' + (i + 1) + '/' + dsSlide.length));
      const noi = the('div', 'vp-slide-noi');

      // Chữ trong slide: p:sp > p:txBody > a:p > a:r > a:t
      let coChu = false;
      if (doc) for (const tx of doc.getElementsByTagName('p:txBody')) {
        for (const p of conTheoTen(tx, 'p')) {
          let s = '';
          for (const r of p.getElementsByTagName('a:t')) s += r.textContent;
          if (!s.trim()) continue;
          const pPr = conDau(p, 'pPr');
          const bac = pPr ? parseInt(pPr.getAttribute('lvl') || '0', 10) : 0;
          const d = the('p', 'vp-slide-dong', s);
          if (bac > 0) d.style.paddingLeft = (bac * 1.2) + 'rem';
          noi.appendChild(d);
          coChu = true;
        }
      }
      // Ảnh trong slide
      if (doc) for (const blip of doc.getElementsByTagName('a:blip')) {
        const id = blip.getAttribute('r:embed');
        const dich = id && relSlide.get(id);
        const p = dich && ghepDuongDan(dd.replace(/[^/]*$/, ''), dich);
        const url = p && anhSangUrl(kho, p);
        if (url) {
          const img = the('img', 'vp-anh');
          img.src = url; img.alt = 'Hình trong trang chiếu ' + (i + 1);
          noi.appendChild(img);
          coChu = true;
        }
      }
      // Ghi chú của người trình bày
      const ghiChu = dd.replace('ppt/slides/slide', 'ppt/notesSlides/notesSlide');
      const dn = kho.has(ghiChu) ? docXml(kho, ghiChu) : null;
      if (dn) {
        let s = '';
        for (const t of dn.getElementsByTagName('a:t')) s += t.textContent + ' ';
        s = s.replace(/\s+/g, ' ').trim();
        // Bỏ ghi chú chỉ chứa số trang
        if (s && !/^\d+$/.test(s)) {
          const g = the('div', 'vp-slide-ghichu');
          g.appendChild(the('strong', null, 'Ghi chú: '));
          g.appendChild(document.createTextNode(s));
          noi.appendChild(g);
        }
      }
      if (!coChu) noi.appendChild(the('p', 'vp-rong', 'Trang chiếu này không có chữ hay hình đọc được.'));
      khung.appendChild(noi);
      goc.appendChild(khung);
    });
    return goc;
  }

  // =====================================================================
  //  Điểm vào
  // =====================================================================
  const BO_DUNG = { docx: dungWord, xlsx: dungExcel, pptx: dungPowerPoint };

  async function xem(url, duoi, vaoDau, khiXong) {
    const loai = String(duoi || '').toLowerCase();
    const dung = BO_DUNG[loai];
    if (!dung) throw new Error('Chưa hỗ trợ xem trực tiếp tệp ' + loai.toUpperCase());
    if (typeof DecompressionStream !== 'function')
      throw new Error('Trình duyệt quá cũ, không giải nén được tệp Office. '
                    + 'Hãy cập nhật Chrome/Edge/Firefox, hoặc tải tệp về máy để mở.');

    const kq = await fetch(url, { credentials: 'same-origin' });
    if (!kq.ok) throw new Error('Không tải được tệp từ máy chủ (mã ' + kq.status + ')');
    const buf = await kq.arrayBuffer();
    const kho = await docZip(buf);
    const el = dung(kho);
    vaoDau.textContent = '';
    vaoDau.appendChild(el);
    if (khiXong) khiXong({ so_phan: kho.size, so_byte: buf.byteLength });
  }

  window.XemOffice = { xem: xem, docZip: docZip };
})();
