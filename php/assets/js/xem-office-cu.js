/* =====================================================================
 *  xem-office-cu.js - Xem tệp Office ĐỜI CŨ: .doc / .xls / .ppt
 *  HỆ THỐNG PHÂN LUỒNG MAIL CÔNG VỤ
 *  Thiết kế bởi Trương Anh Tuấn
 * ---------------------------------------------------------------------
 *  Bản .docx/.xlsx/.pptx là ZIP chứa XML nên dễ đọc (xem xem-office.js).
 *  Bản 97-2003 thì khác hẳn: cả ba đều là tệp OLE2 - một "hệ thống tệp
 *  thu nhỏ" nằm trong một tệp, bên trong chứa các luồng dữ liệu nhị phân:
 *
 *      .doc -> luồng "WordDocument" + "1Table"   (FIB, piece table)
 *      .xls -> luồng "Workbook"                  (bản ghi BIFF8)
 *      .ppt -> luồng "PowerPoint Document"       (bản ghi có lồng nhau)
 *
 *  Vì vậy phải tự đọc OLE2 rồi bóc từng định dạng. Vẫn không dùng thư
 *  viện ngoài, và nội dung vẫn không rời khỏi máy chủ của Sở.
 *
 *  GIỚI HẠN cần nói trước: bản dựng lại chỉ lấy được CHỮ, BẢNG SỐ LIỆU và
 *  cấu trúc đoạn. Định dạng đẹp, ảnh, biểu đồ thì không - muốn bản chuẩn
 *  xác phải tải về mở bằng Office. Tệp gõ bằng phông VNI/TCVN3 đời cũ
 *  (font ABC) sẽ hiện sai dấu, vì chữ lưu trong tệp không phải Unicode.
 * =====================================================================
 */
(function () {
  'use strict';

  // =====================================================================
  //  Đọc tệp OLE2 (Compound File Binary)
  // =====================================================================
  const CHU_KY_OLE2 = [0xd0, 0xcf, 0x11, 0xe0, 0xa1, 0xb1, 0x1a, 0xe1];
  const KET_CHUOI = 0xfffffffe;      // ENDOFCHAIN
  const O_TRONG   = 0xffffffff;      // FREESECT

  function laOle2(d) {
    for (let i = 0; i < 8; i++) if (d[i] !== CHU_KY_OLE2[i]) return false;
    return true;
  }

  function docOle2(buf) {
    const d = new Uint8Array(buf);
    const dv = new DataView(buf);
    if (d.length < 512 || !laOle2(d))
      throw new Error('Không phải tệp Office 97-2003 hợp lệ');

    const ss  = 1 << dv.getUint16(30, true);          // cỡ một sector
    const mss = 1 << dv.getUint16(32, true);          // cỡ một mini sector
    const soFat     = dv.getUint32(44, true);
    const dirDau    = dv.getUint32(48, true);
    const nguongMini = dv.getUint32(56, true) || 4096;
    const miniFatDau = dv.getUint32(60, true);
    const difatDau   = dv.getUint32(68, true);
    const soDifat    = dv.getUint32(72, true);

    const viTri = (sec) => 512 + sec * ss;
    const trongTep = (sec) => viTri(sec) + ss <= d.length;

    // --- Dựng bảng FAT từ DIFAT ---
    const dsFatSect = [];
    for (let i = 0; i < 109 && dsFatSect.length < soFat; i++) {
      const s = dv.getUint32(76 + 4 * i, true);
      if (s === O_TRONG || s === KET_CHUOI) break;
      dsFatSect.push(s);
    }
    let sec = difatDau, canh = 0;
    while (sec !== KET_CHUOI && sec !== O_TRONG && canh++ < soDifat + 8 && trongTep(sec)) {
      const g = viTri(sec);
      const soMuc = ss / 4 - 1;
      for (let i = 0; i < soMuc && dsFatSect.length < soFat; i++) {
        const s = dv.getUint32(g + 4 * i, true);
        if (s === O_TRONG || s === KET_CHUOI) break;
        dsFatSect.push(s);
      }
      sec = dv.getUint32(g + ss - 4, true);
    }
    const fat = new Uint32Array(dsFatSect.length * (ss / 4));
    dsFatSect.forEach((s, k) => {
      if (!trongTep(s)) return;
      const g = viTri(s);
      for (let i = 0; i < ss / 4; i++) fat[k * (ss / 4) + i] = dv.getUint32(g + 4 * i, true);
    });

    function theoChuoi(dau, tongCanh) {
      const ra = [];
      let s = dau, n = 0;
      while (s !== KET_CHUOI && s !== O_TRONG && s < fat.length && n++ <= tongCanh + 8) {
        ra.push(s);
        s = fat[s];
      }
      return ra;
    }
    function docSector(dsSec, coDai) {
      const ra = new Uint8Array(coDai);
      let o = 0;
      for (const s of dsSec) {
        if (o >= coDai) break;
        if (!trongTep(s)) break;
        const lay = Math.min(ss, coDai - o);
        ra.set(d.subarray(viTri(s), viTri(s) + lay), o);
        o += lay;
      }
      return ra;
    }

    // --- Đọc thư mục ---
    const dsDir = theoChuoi(dirDau, fat.length);
    const muc = [];
    for (const s of dsDir) {
      if (!trongTep(s)) break;
      for (let k = 0; k + 128 <= ss; k += 128) {
        const g = viTri(s) + k;
        const doDaiTen = dv.getUint16(g + 64, true);
        const loai = d[g + 66];
        if (loai === 0) continue;
        let ten = '';
        for (let i = 0; i + 1 < Math.max(0, doDaiTen - 2); i += 2)
          ten += String.fromCharCode(dv.getUint16(g + i, true));
        muc.push({ ten: ten, loai: loai,
                   secDau: dv.getUint32(g + 116, true),
                   coDai: dv.getUint32(g + 120, true) });
      }
    }

    // --- Mini stream nằm trong luồng của mục gốc (loại 5) ---
    const goc = muc.find((m) => m.loai === 5);
    let miniStream = null, miniFat = null;
    if (goc) {
      miniStream = docSector(theoChuoi(goc.secDau, fat.length), goc.coDai);
      const dsMf = theoChuoi(miniFatDau, fat.length);
      const b = docSector(dsMf, dsMf.length * ss);
      miniFat = new Uint32Array(b.length / 4);
      const mdv = new DataView(b.buffer, b.byteOffset, b.byteLength);
      for (let i = 0; i < miniFat.length; i++) miniFat[i] = mdv.getUint32(4 * i, true);
    }

    const kho = new Map();
    for (const m of muc) {
      if (m.loai !== 2) continue;                      // chỉ lấy luồng dữ liệu
      let b;
      if (m.coDai < nguongMini && miniStream && miniFat) {
        b = new Uint8Array(m.coDai);
        let s = m.secDau, o = 0, n = 0;
        while (s !== KET_CHUOI && s !== O_TRONG && s < miniFat.length &&
               o < m.coDai && n++ <= miniFat.length + 8) {
          const g = s * mss;
          const lay = Math.min(mss, m.coDai - o);
          if (g + lay <= miniStream.length) b.set(miniStream.subarray(g, g + lay), o);
          o += lay;
          s = miniFat[s];
        }
      } else {
        b = docSector(theoChuoi(m.secDau, fat.length), m.coDai);
      }
      kho.set(m.ten, b);
    }
    return kho;
  }

  // =====================================================================
  //  Tiện ích chung
  // =====================================================================
  const GIAI_UTF16 = new TextDecoder('utf-16le');
  // Windows-1252: bảng cho 0x80-0x9F, phần còn lại trùng Latin-1
  const CP1252_CAO = [0x20ac,0x81,0x201a,0x192,0x201e,0x2026,0x2020,0x2021,0x2c6,0x2030,
    0x160,0x2039,0x152,0x8d,0x17d,0x8f,0x90,0x2018,0x2019,0x201c,0x201d,0x2022,0x2013,
    0x2014,0x2dc,0x2122,0x161,0x203a,0x153,0x9d,0x17e,0x178];
  function giaiCp1252(b) {
    let s = '';
    for (let i = 0; i < b.length; i++) {
      const c = b[i];
      s += String.fromCharCode(c >= 0x80 && c <= 0x9f ? CP1252_CAO[c - 0x80] : c);
    }
    return s;
  }
  function the(ten, lop, chu) {
    const e = document.createElement(ten);
    if (lop) e.className = lop;
    if (chu != null) e.textContent = chu;
    return e;
  }
  // Dọn các ký tự điều khiển mà Word/PowerPoint nhét vào giữa chữ.
  // THỨ TỰ QUAN TRỌNG: phải đổi 0x07 (dấu hết ô bảng của .doc) và 0x0B (ngắt
  // dòng mềm) thành TAB / xuống dòng TRƯỚC, vì bước lọc bên dưới xoá sạch
  // chúng - xoá trước thì cả một hàng bảng dồn thành cục chữ dính liền.
  function locChu(s) {
    return s
      .replace(/\x07/g, '\t')                    // hết một ô bảng
      .replace(/\x0b/g, '\n')                    // ngắt dòng trong cùng đoạn
      .replace(/\x1e/g, '-')                      // gạch nối không ngắt
      .replace(/\x1f/g, '')                       // gạch nối tuỳ chọn
      .replace(/\xa0/g, ' ')                      // khoảng trắng không ngắt
      // Giữ lại 0x0D: cả .doc lẫn .ppt dùng CR làm dấu NGẮT ĐOẠN, xoá nó là
      // mọi đoạn dính liền thành một dòng dài.
      .replace(/[\x00-\x06\x08\x0c\x0e-\x1d]/g, '')
      .replace(/[\uFFFE\uFFFF]/g, '');
  }

  // =====================================================================
  //  WORD 97-2003 (.doc)
  // =====================================================================
  function dungWordCu(kho) {
    const wd = kho.get('WordDocument');
    if (!wd) throw new Error('Tệp .doc thiếu luồng WordDocument');
    const dv = new DataView(wd.buffer, wd.byteOffset, wd.byteLength);
    if (dv.getUint16(0, true) !== 0xa5ec)
      throw new Error('Luồng WordDocument không đúng dạng Word 97-2003');

    // Cờ ở offset 10 cho biết bảng phụ nằm ở "1Table" hay "0Table"
    const co = dv.getUint16(10, true);
    const tenBang = (co & 0x0200) ? '1Table' : '0Table';
    const tb = kho.get(tenBang) || kho.get('1Table') || kho.get('0Table');
    if (!tb) throw new Error('Tệp .doc thiếu luồng bảng (' + tenBang + ')');
    const tdv = new DataView(tb.buffer, tb.byteOffset, tb.byteLength);

    // FIB: base 32 byte, rồi csw/rgW97, cslw/rgLw97, cbRgFcLcb/rgFcLcb
    let p = 32;
    const csw = dv.getUint16(p, true); p += 2 + csw * 2;
    const cslw = dv.getUint16(p, true); p += 2;
    const rgLw97 = p;                p += cslw * 4;
    p += 2;                          // cbRgFcLcb
    const rgFcLcb = p;

    const ccpText = cslw > 3 ? dv.getUint32(rgLw97 + 12, true) : 0;
    // fcClx / lcbClx là cặp thứ 33 trong FibRgFcLcb97
    const fcClx  = dv.getUint32(rgFcLcb + 33 * 8, true);
    const lcbClx = dv.getUint32(rgFcLcb + 33 * 8 + 4, true);
    if (!lcbClx || fcClx + lcbClx > tb.length)
      throw new Error('Tệp .doc không có bảng định vị văn bản (Clx)');

    // Clx = nhiều Prc (mỗi cái mở đầu bằng 0x01) rồi tới Pcdt (mở đầu 0x02)
    let q = fcClx;
    const het = fcClx + lcbClx;
    while (q < het && tb[q] === 0x01) {
      const cb = tdv.getUint16(q + 1, true);
      q += 3 + cb;
    }
    if (q >= het || tb[q] !== 0x02)
      throw new Error('Tệp .doc có bảng định vị văn bản không đọc được');
    const lcbPlc = tdv.getUint32(q + 1, true);
    const plc = q + 5;
    const soManh = Math.floor((lcbPlc - 4) / 12);
    if (soManh <= 0) throw new Error('Tệp .doc không có mảnh văn bản nào');

    // aCP: soManh+1 mục u32, rồi aPcd: soManh mục 8 byte
    const aCp = [];
    for (let i = 0; i <= soManh; i++) aCp.push(tdv.getUint32(plc + 4 * i, true));
    const gocPcd = plc + 4 * (soManh + 1);

    let vanBan = '';
    for (let i = 0; i < soManh; i++) {
      const g = gocPcd + 8 * i;
      const fc = tdv.getUint32(g + 2, true);
      const nen = (fc & 0x40000000) !== 0;             // nén = 1 byte/ký tự (CP1252)
      const diaChi = nen ? (fc & 0x3fffffff) >>> 1 : (fc & 0x3fffffff);
      const soKyTu = aCp[i + 1] - aCp[i];
      if (soKyTu <= 0) continue;
      const soByte = nen ? soKyTu : soKyTu * 2;
      if (diaChi + soByte > wd.length) continue;       // mảnh hỏng thì bỏ, đừng làm sập cả tệp
      const b = wd.subarray(diaChi, diaChi + soByte);
      vanBan += nen ? giaiCp1252(b) : GIAI_UTF16.decode(b);
    }
    if (ccpText > 0 && vanBan.length > ccpText) vanBan = vanBan.slice(0, ccpText);

    // Trong .doc: \r ngắt đoạn; ô bảng kết thúc bằng 0x07 (locChu đã đổi ra TAB)
    // nên một hàng bảng nằm gọn trong một "đoạn" và nhận ra được nhờ dấu TAB.
    const goc = the('div', 'vp-word vp-word-cu');
    let soDoan = 0;
    let bangDangMo = null;                             // gom các hàng liền nhau

    function dongBang() { bangDangMo = null; }
    function themHang(oO) {
      if (!bangDangMo) {
        const boc = the('div', 'vp-cuon');
        bangDangMo = the('table', 'vp-bang');
        boc.appendChild(bangDangMo);
        goc.appendChild(boc);
      }
      const tr = the('tr');
      const dongDau = bangDangMo.children.length === 0;
      for (const o of oO) tr.appendChild(the(dongDau ? 'th' : 'td', null, o));
      bangDangMo.appendChild(tr);
      soDoan++;
    }

    for (let doan of vanBan.split('\r')) {
      doan = locChu(doan);
      if (!doan.trim()) { dongBang(); continue; }

      // Bảng trong .doc không dùng \r: mỗi Ô kết thúc bằng 0x07, và mỗi HÀNG
      // kết thúc bằng một 0x07 nữa. Sau khi locChu đổi ra TAB thì "\t\t" chính
      // là dấu hết hàng - tách theo đó trước, rồi mới tách ô theo từng TAB.
      if (doan.indexOf('\t') >= 0) {
        const dsHang = doan.split(/\t\t+/)
          .map((h) => h.split('\t').map((x) => x.trim()))
          .filter((h) => h.some((x) => x !== ''));
        // Chỉ coi là bảng khi thật sự có nhiều ô; một đoạn văn lẫn TAB thì không
        if (dsHang.some((h) => h.length > 1)) {
          for (const h of dsHang) {
            // Word không luôn chèn \r sau bảng, nên đoạn văn ngay dưới bảng có
            // thể lọt vào đây. Hàng chỉ một ô mà bảng đã có hàng nhiều ô thì đó
            // là văn bản thường - đóng bảng lại rồi in ra như đoạn văn.
            if (h.length === 1 && bangDangMo &&
                bangDangMo.rows.length && bangDangMo.rows[0].cells.length > 1) {
              dongBang();
              if (h[0]) { goc.appendChild(the('p', null, h[0])); soDoan++; }
              continue;
            }
            themHang(h);
          }
          continue;
        }
      }
      dongBang();

      for (const dong of doan.split('\n')) {
        if (!dong.trim()) continue;
        goc.appendChild(the('p', null, dong.trim()));
        soDoan++;
      }
    }
    if (!soDoan) throw new Error('Tệp .doc này không có chữ nào đọc được');
    return goc;
  }

  // =====================================================================
  //  EXCEL 97-2003 (.xls) - bản ghi BIFF8
  // =====================================================================
  const B = {
    BOF: 0x0809, EOF: 0x000a, BOUNDSHEET: 0x0085, SST: 0x00fc, CONTINUE: 0x003c,
    LABELSST: 0x00fd, LABEL: 0x0204, RK: 0x027e, NUMBER: 0x0203, BLANK: 0x0201,
    MULRK: 0x00bd, MULBLANK: 0x00be, FORMULA: 0x0006, STRING: 0x0207,
    FORMAT: 0x041e, XF: 0x00e0, DIMENSIONS: 0x0200, RSTRING: 0x00d6,
  };

  /** Duyệt các bản ghi BIFF trong một luồng */
  function dsBanGhi(b) {
    const dv = new DataView(b.buffer, b.byteOffset, b.byteLength);
    const ra = [];
    let p = 0;
    while (p + 4 <= b.length) {
      const ma = dv.getUint16(p, true);
      const co = dv.getUint16(p + 2, true);
      if (p + 4 + co > b.length) break;
      ra.push({ ma: ma, viTri: p + 4, co: co });
      p += 4 + co;
    }
    return ra;
  }

  /** Chuỗi Unicode kiểu BIFF8: [u16 số ký tự][u8 cờ][(rich/far)][dữ liệu] */
  function chuoiBiff(b, dv, p, choDaiTruoc) {
    const soKyTu = dv.getUint16(p, true);
    const co = b[p + 2];
    let q = p + 3;
    let soRich = 0, cbExt = 0;
    if (co & 0x08) { soRich = dv.getUint16(q, true); q += 2; }
    if (co & 0x04) { cbExt = dv.getUint32(q, true); q += 4; }
    const rong = (co & 0x01) !== 0;                    // 1 = UTF-16, 0 = 1 byte
    const soByte = rong ? soKyTu * 2 : soKyTu;
    const lay = Math.min(soByte, b.length - q);
    const chu = rong ? GIAI_UTF16.decode(b.subarray(q, q + lay))
                     : giaiCp1252(b.subarray(q, q + lay));
    return { chu: chu, ket: q + lay + soRich * 4 + cbExt,
             thieu: soByte - lay, rong: rong, soKyTu: soKyTu };
  }

  /** Bảng chuỗi chung (SST) - có thể trải qua nhiều bản ghi CONTINUE */
  function docSst(b, dv, bg, chiSo) {
    const r = bg[chiSo];
    let p = r.viTri + 8;                               // bỏ cbTotal + cbUnique
    let het = r.viTri + r.co;
    let k = chiSo;
    const ra = [];
    const soDuyNhat = dv.getUint32(r.viTri + 4, true);

    // Sang bản ghi CONTINUE kế tiếp; "trongChuoi" cho biết đang đứt giữa dữ liệu
    function sang(trongChuoi, rong) {
      k++;
      while (k < bg.length && bg[k].ma !== B.CONTINUE) {
        if (bg[k].ma === B.SST) return false;
        k++;
      }
      if (k >= bg.length || bg[k].ma !== B.CONTINUE) return false;
      p = bg[k].viTri;
      het = bg[k].viTri + bg[k].co;
      // Chuỗi bị cắt giữa dữ liệu: byte đầu của CONTINUE là cờ rộng/hẹp mới
      if (trongChuoi) { rong.gt = (b[p] & 0x01) !== 0; p += 1; }
      return true;
    }

    while (ra.length < soDuyNhat) {
      if (p + 3 > het && !sang(false, {})) break;
      const soKyTu = dv.getUint16(p, true);
      let co = b[p + 2];
      let q = p + 3;
      let soRich = 0, cbExt = 0;
      if (co & 0x08) { soRich = dv.getUint16(q, true); q += 2; }
      if (co & 0x04) { cbExt = dv.getUint32(q, true); q += 4; }
      const rong = { gt: (co & 0x01) !== 0 };
      let conLai = soKyTu, chu = '';
      p = q;
      while (conLai > 0) {
        const coTheLay = Math.max(0, het - p);
        const canByte = rong.gt ? conLai * 2 : conLai;
        const layByte = Math.min(canByte, coTheLay - (coTheLay % (rong.gt ? 2 : 1)));
        if (layByte > 0) {
          chu += rong.gt ? GIAI_UTF16.decode(b.subarray(p, p + layByte))
                         : giaiCp1252(b.subarray(p, p + layByte));
          conLai -= rong.gt ? layByte / 2 : layByte;
          p += layByte;
        }
        if (conLai > 0 && !sang(true, rong)) { conLai = 0; }
      }
      // Bỏ qua phần định dạng rich text và dữ liệu Far East
      let boQua = soRich * 4 + cbExt;
      while (boQua > 0) {
        const co2 = Math.min(boQua, Math.max(0, het - p));
        p += co2; boQua -= co2;
        if (boQua > 0 && !sang(false, {})) break;
      }
      ra.push(chu);
    }
    return ra;
  }

  function giaiRk(rk) {
    const nguyen = (rk & 0x02) !== 0;
    const chia100 = (rk & 0x01) !== 0;
    let v;
    if (nguyen) {
      v = rk >> 2;                                     // 30 bit có dấu
    } else {
      const bf = new ArrayBuffer(8);
      new DataView(bf).setUint32(4, rk & 0xfffffffc, true);
      v = new DataView(bf).getFloat64(0, true);
    }
    return chia100 ? v / 100 : v;
  }

  // Mã định dạng số có sẵn của Excel dành cho ngày/giờ
  const NGAY_CO_SAN = new Set([14,15,16,17,18,19,20,21,22,27,28,29,30,31,32,33,34,35,36,
                               45,46,47,50,51,52,53,54,55,56,57,58]);
  function soSangNgay(n) {
    const ms = Math.round((n - 25569) * 86400000);
    const d = new Date(ms);
    if (isNaN(d.getTime())) return String(n);
    const h = (x) => String(x).padStart(2, '0');
    const ngay = h(d.getUTCDate()) + '/' + h(d.getUTCMonth() + 1) + '/' + d.getUTCFullYear();
    return (n - Math.floor(n) < 1e-6) ? ngay
         : ngay + ' ' + h(d.getUTCHours()) + ':' + h(d.getUTCMinutes());
  }

  function dungExcelCu(kho) {
    const b = kho.get('Workbook') || kho.get('Book');
    if (!b) throw new Error('Tệp .xls thiếu luồng Workbook');
    const dv = new DataView(b.buffer, b.byteOffset, b.byteLength);
    const bg = dsBanGhi(b);
    if (!bg.length) throw new Error('Tệp .xls không có bản ghi nào đọc được');

    // --- Vòng một: tên bảng tính, bảng chuỗi chung, định dạng số ---
    const bangTen = [];
    let chuoi = [];
    const dinhDangCuaXf = [];
    const maNgay = new Map(NGAY_CO_SAN.size ? [] : []);
    const tuDinhNghia = new Map();

    bg.forEach((r, i) => {
      if (r.ma === B.BOUNDSHEET) {
        const viTriBof = dv.getUint32(r.viTri, true);
        // Tên bảng: [u8 số ký tự][u8 cờ][dữ liệu]
        const soKyTu = b[r.viTri + 6];
        const co = b[r.viTri + 7];
        const rong = (co & 0x01) !== 0;
        const g = r.viTri + 8;
        const soByte = rong ? soKyTu * 2 : soKyTu;
        const ten = rong ? GIAI_UTF16.decode(b.subarray(g, g + soByte))
                         : giaiCp1252(b.subarray(g, g + soByte));
        bangTen.push({ ten: ten || ('Sheet' + (bangTen.length + 1)), bof: viTriBof, o: new Map(),
                       maxCot: 0, maxHang: 0 });
      } else if (r.ma === B.SST) {
        chuoi = docSst(b, dv, bg, i);
      } else if (r.ma === B.FORMAT) {
        const ma = dv.getUint16(r.viTri, true);
        const s = chuoiBiff(b, dv, r.viTri + 2);
        const sach = s.chu.replace(/"[^"]*"/g, '').replace(/\[[^\]]*\]/g, '');
        tuDinhNghia.set(ma, /[ymd]/i.test(sach) || /h.*m|m.*s/i.test(sach));
      } else if (r.ma === B.XF) {
        const ifmt = dv.getUint16(r.viTri + 2, true);
        dinhDangCuaXf.push(ifmt);
      }
    });
    const xfLaNgay = dinhDangCuaXf.map((ifmt) =>
      tuDinhNghia.has(ifmt) ? !!tuDinhNghia.get(ifmt) : NGAY_CO_SAN.has(ifmt));

    if (!bangTen.length)
      bangTen.push({ ten: 'Bảng tính', bof: -1, o: new Map(), maxCot: 0, maxHang: 0 });

    // --- Vòng hai: các ô, chia theo bảng tính nhờ vị trí BOF ---
    // Bản ghi của một bảng tính nằm sau BOF của nó và trước BOF của bảng kế.
    function bangTheoViTri(viTri) {
      let chon = null;
      for (const t of bangTen) if (t.bof >= 0 && t.bof <= viTri && (!chon || t.bof > chon.bof)) chon = t;
      return chon || bangTen[0];
    }
    function datO(t, hang, cot, chu, lop) {
      if (chu === '' || chu == null) return;
      t.o.set(hang + ',' + cot, { chu: String(chu), lop: lop || null });
      if (cot > t.maxCot) t.maxCot = cot;
      if (hang > t.maxHang) t.maxHang = hang;
    }
    function inSo(v, xf) {
      if (xf >= 0 && xfLaNgay[xf] && v > 0) return soSangNgay(v);
      return Number.isInteger(v) ? v.toLocaleString('vi-VN')
                                 : v.toLocaleString('vi-VN', { maximumFractionDigits: 10 });
    }

    for (let i = 0; i < bg.length; i++) {
      const r = bg[i];
      const t = bangTheoViTri(r.viTri);
      const p = r.viTri;
      if (r.ma === B.LABELSST) {
        const hang = dv.getUint16(p, true), cot = dv.getUint16(p + 2, true);
        const k = dv.getUint32(p + 6, true);
        datO(t, hang, cot, k < chuoi.length ? locChu(chuoi[k]) : '');
      } else if (r.ma === B.LABEL || r.ma === B.RSTRING) {
        const hang = dv.getUint16(p, true), cot = dv.getUint16(p + 2, true);
        datO(t, hang, cot, locChu(chuoiBiff(b, dv, p + 6).chu));
      } else if (r.ma === B.RK) {
        const hang = dv.getUint16(p, true), cot = dv.getUint16(p + 2, true);
        const xf = dv.getUint16(p + 4, true);
        datO(t, hang, cot, inSo(giaiRk(dv.getUint32(p + 6, true)), xf), 'vp-so');
      } else if (r.ma === B.NUMBER) {
        const hang = dv.getUint16(p, true), cot = dv.getUint16(p + 2, true);
        const xf = dv.getUint16(p + 4, true);
        datO(t, hang, cot, inSo(dv.getFloat64(p + 6, true), xf), 'vp-so');
      } else if (r.ma === B.MULRK) {
        const hang = dv.getUint16(p, true), cotDau = dv.getUint16(p + 2, true);
        const soO = Math.floor((r.co - 6) / 6);
        for (let k = 0; k < soO; k++) {
          const g = p + 4 + k * 6;
          const xf = dv.getUint16(g, true);
          datO(t, hang, cotDau + k, inSo(giaiRk(dv.getUint32(g + 2, true)), xf), 'vp-so');
        }
      } else if (r.ma === B.FORMULA) {
        const hang = dv.getUint16(p, true), cot = dv.getUint16(p + 2, true);
        const xf = dv.getUint16(p + 4, true);
        // 8 byte kết quả: nếu 2 byte cuối là 0xFFFF thì là giá trị đặc biệt
        if (dv.getUint16(p + 12, true) === 0xffff) {
          const loai = b[p + 6];
          if (loai === 0) {
            // Chuỗi: nội dung nằm ở bản ghi STRING ngay sau
            const sau = bg[i + 1];
            if (sau && sau.ma === B.STRING)
              datO(t, hang, cot, locChu(chuoiBiff(b, dv, sau.viTri).chu));
          } else if (loai === 1) {
            datO(t, hang, cot, b[p + 8] ? 'ĐÚNG' : 'SAI');
          } else if (loai === 2) {
            datO(t, hang, cot, '#LỖI');
          }
          // loại 3 = ô trống
        } else {
          datO(t, hang, cot, inSo(dv.getFloat64(p + 6, true), xf), 'vp-so');
        }
      }
    }

    // --- Dựng HTML ---
    const goc = the('div', 'vp-excel');
    const coDuLieu = bangTen.filter((t) => t.o.size > 0);
    const hienThi = coDuLieu.length ? coDuLieu : bangTen.slice(0, 1);
    if (!coDuLieu.length && bangTen.length)
      throw new Error('Tệp .xls này không có ô dữ liệu nào đọc được');

    if (hienThi.length > 1) {
      const thanh = the('div', 'vp-tab');
      hienThi.forEach((t, i) => {
        const nut = the('button', 'vp-tab-nut' + (i === 0 ? ' dang-chon' : ''), t.ten);
        nut.type = 'button'; nut.dataset.chiSo = String(i);
        thanh.appendChild(nut);
      });
      thanh.addEventListener('click', (ev) => {
        const nut = ev.target.closest('.vp-tab-nut');
        if (!nut) return;
        thanh.querySelectorAll('.vp-tab-nut').forEach((x) => x.classList.toggle('dang-chon', x === nut));
        goc.querySelectorAll('.vp-sheet').forEach((x) => { x.hidden = x.dataset.chiSo !== nut.dataset.chiSo; });
      });
      goc.appendChild(thanh);
    }

    hienThi.forEach((t, chiSo) => {
      const khung = the('div', 'vp-sheet');
      khung.dataset.chiSo = String(chiSo);
      khung.hidden = chiSo !== 0;
      const boc = the('div', 'vp-cuon');
      const tb = the('table', 'vp-bang vp-bang-excel');
      for (let h = 0; h <= t.maxHang; h++) {
        let coGi = false;
        for (let c = 0; c <= t.maxCot; c++) if (t.o.has(h + ',' + c)) { coGi = true; break; }
        const tr = the('tr', coGi ? null : 'vp-hang-trong');
        if (!coGi) {
          const td = the('td'); td.colSpan = t.maxCot + 2;
          td.appendChild(the('span', null, ' '));
          tr.appendChild(td); tb.appendChild(tr);
          continue;
        }
        tr.appendChild(the('th', 'vp-so-hang', String(h + 1)));
        for (let c = 0; c <= t.maxCot; c++) {
          const o = t.o.get(h + ',' + c);
          tr.appendChild(the('td', o ? o.lop : null, o ? o.chu : ''));
        }
        tb.appendChild(tr);
      }
      boc.appendChild(tb);
      khung.appendChild(boc);
      goc.appendChild(khung);
    });
    return goc;
  }

  // =====================================================================
  //  POWERPOINT 97-2003 (.ppt)
  // =====================================================================
  // Bản ghi: [u16 verInstance][u16 type][u32 length][data]
  const P_SLIDE       = 0x03ee;      // container của một trang chiếu
  const P_TEXT_CHARS  = 0x0fa0;      // chữ UTF-16LE
  const P_TEXT_BYTES  = 0x0fa8;      // chữ 1 byte (CP1252)

  function dungPowerPointCu(kho) {
    const b = kho.get('PowerPoint Document');
    if (!b) throw new Error('Tệp .ppt thiếu luồng PowerPoint Document');
    const dv = new DataView(b.buffer, b.byteOffset, b.byteLength);

    // Lấy chữ trong một khoảng của luồng, đi vào cả container con
    function layChu(dau, het, ra) {
      let p = dau;
      while (p + 8 <= het) {
        const vi = dv.getUint16(p, true);
        const ma = dv.getUint16(p + 2, true);
        const co = dv.getUint32(p + 4, true);
        const dl = p + 8;
        if (co > het - dl) break;
        if (ma === P_TEXT_CHARS) {
          ra.push(GIAI_UTF16.decode(b.subarray(dl, dl + co)));
        } else if (ma === P_TEXT_BYTES) {
          ra.push(giaiCp1252(b.subarray(dl, dl + co)));
        } else if ((vi & 0x0f) === 0x0f) {
          layChu(dl, dl + co, ra);                     // là container, đi vào trong
        }
        p = dl + co;
      }
    }

    // Chỉ quét bên trong các container Slide. Quét cả luồng sẽ ra chữ trùng,
    // vì PowerPoint còn giữ một bản sao trong phần Document.
    const slide = [];
    (function timSlide(dau, het) {
      let p = dau;
      while (p + 8 <= het) {
        const vi = dv.getUint16(p, true);
        const ma = dv.getUint16(p + 2, true);
        const co = dv.getUint32(p + 4, true);
        const dl = p + 8;
        if (co > het - dl) break;
        if (ma === P_SLIDE) {
          const ra = [];
          layChu(dl, dl + co, ra);
          slide.push(ra);
        } else if ((vi & 0x0f) === 0x0f) {
          timSlide(dl, dl + co);
        }
        p = dl + co;
      }
    })(0, b.length);

    if (!slide.length) throw new Error('Tệp .ppt này không có trang chiếu nào đọc được');

    const goc = the('div', 'vp-ppt');
    let tongDong = 0;
    slide.forEach((doanChu, i) => {
      const khung = the('section', 'vp-slide');
      khung.appendChild(the('div', 'vp-slide-so', 'Trang ' + (i + 1) + '/' + slide.length));
      const noi = the('div', 'vp-slide-noi');
      for (const khoi of doanChu) {
        // \r ngắt đoạn, \x0b ngắt dòng trong cùng đoạn
        for (const dong of locChu(khoi).split(/[\r]/)) {
          if (!dong.trim()) continue;
          noi.appendChild(the('p', 'vp-slide-dong', dong.trim()));
          tongDong++;
        }
      }
      if (!noi.children.length)
        noi.appendChild(the('p', 'vp-rong', 'Trang chiếu này không có chữ đọc được.'));
      khung.appendChild(noi);
      goc.appendChild(khung);
    });
    if (!tongDong) throw new Error('Tệp .ppt này không có chữ nào đọc được');
    return goc;
  }

  // =====================================================================
  //  Điểm vào
  // =====================================================================
  const BO_DUNG = { doc: dungWordCu, xls: dungExcelCu, ppt: dungPowerPointCu };

  async function xem(url, duoi, vaoDau, khiXong) {
    const loai = String(duoi || '').toLowerCase();
    const dung = BO_DUNG[loai];
    if (!dung) throw new Error('Chưa hỗ trợ xem trực tiếp tệp ' + loai.toUpperCase());

    const kq = await fetch(url, { credentials: 'same-origin' });
    if (!kq.ok) throw new Error('Không tải được tệp từ máy chủ (mã ' + kq.status + ')');
    const buf = await kq.arrayBuffer();
    const kho = docOle2(buf);
    const el = dung(kho);
    vaoDau.textContent = '';
    vaoDau.appendChild(el);
    if (khiXong) khiXong({ so_luong: kho.size, so_byte: buf.byteLength });
  }

  window.XemOfficeCu = { xem: xem, docOle2: docOle2 };
})();
