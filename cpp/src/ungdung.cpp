// =====================================================================
//  ungdung.cpp - Bộ điều phối chính
// =====================================================================
#include "ungdung.h"
#include "http_client.h"
#include "phien_ban.h"
#include "crypto.h"
#include "util.h"
#include "nhatky.h"

#include <algorithm>
#include <cstdio>

namespace mr {

UngDung::UngDung() {}
UngDung::~UngDung() { dungDichVu(); if (kho_) kho_->dong(); }

// ---------------------------------------------------------------------
//  Khởi tạo
// ---------------------------------------------------------------------
bool UngDung::khoiTao(const std::string& tepCauHinh, std::string& loi) {
    std::string tep = tepCauHinh.empty() ? CauHinh::tepMacDinh() : tepCauHinh;
    if (!ch_.doc(tep)) {
        // Tệp chưa có -> tạo mẫu
        ch_.dat("luu_tru.che_do", "mysql");
        ch_.dat("mysql.may_chu", "localhost");
        ch_.dat("mysql.cong", "3306");
        ch_.dat("mysql.nguoi_dung", "");
        ch_.dat("mysql.mat_khau", "");
        ch_.dat("mysql.co_so_du_lieu", "");
        ch_.dat("mysql.timeout", "30");
        ch_.dat("mysql.kich_thuoc_khoi_kb", "256");
        ch_.dat("api.url", "");
        ch_.dat("api.khoa", "");
        ch_.dat("api.timeout", "120");
        ch_.dat("api.kich_thuoc_khoi_kb", "512");
        ch_.dat("gmail.client_id", "");
        ch_.dat("gmail.client_secret", "");
        ch_.dat("gmail.access_token", "");
        ch_.dat("gmail.refresh_token", "");
        ch_.dat("gmail.token_het_han", "0");
        ch_.dat("gmail.hop_thu", "");
        ch_.dat("gmail.truy_van", "");
        ch_.dat("gmail.so_mail_moi_lan", "");
        ch_.dat("gmail.quet_spam", "1");
        ch_.dat("gmail.nhan_link_drive", "1");
        ch_.dat("ai.bat", "");
        ch_.dat("ai.url", "");
        ch_.dat("ai.api_key", "");
        ch_.dat("ai.model", "");
        ch_.dat("ai.max_tokens", "");
        ch_.dat("ai.timeout", "");
        ch_.dat("ung_dung.dia_chi_giao_dien", "127.0.0.1");
        ch_.dat("ung_dung.cong_giao_dien", "8899");
        ch_.dat("ung_dung.tu_mo_trinh_duyet", "1");
        ch_.dat("ung_dung.chu_ky_phut", "15");
        ch_.dat("ung_dung.muc_nhat_ky", "info");
        ch_.dat("ung_dung.thu_muc_nhat_ky", "nhat_ky");
        ch_.dat("ung_dung.dung_luong_tep_toi_da_mb", "25");
        if (!ch_.ghi(tep)) {
            loi = "Không tạo được tệp cấu hình: " + tep;
            return false;
        }
        NK.canhBao("cau_hinh", "Chưa có tệp cấu hình, đã tạo mẫu tại: " + tep);
    }
    ch_.dat("__tep", tep);

    NK.datMucTuChuoi(ch_.chuoi("ung_dung.muc_nhat_ky", "info"));
    std::string thuMucLog = ch_.chuoi("ung_dung.thu_muc_nhat_ky", "nhat_ky");
    if (!thuMucLog.empty()) {
        if (thuMucLog.find('/') == std::string::npos && thuMucLog.find('\\') == std::string::npos)
            thuMucLog = thuMucChuongTrinh() + "/" + thuMucLog;
        NK.datThuMuc(thuMucLog);
    }

    gmail_.datUngDung(ch_.chuoi("gmail.client_id"), ch_.chuoi("gmail.client_secret"));
    TokenGmail t;
    t.access_token  = ch_.chuoi("gmail.access_token");
    t.refresh_token = ch_.chuoi("gmail.refresh_token");
    t.het_han       = ch_.nguyen("gmail.token_het_han", 0);
    t.dia_chi       = ch_.chuoi("gmail.hop_thu");
    gmail_.datToken(t);

    pl_.datDanhMuc(&dm_);
    ai_.datCauHinh(CauHinhAi::tuCauHinh(ch_));

    std::string lHttp;
    if (!HttpClient::sanSang(lHttp)) NK.canhBao("http", lHttp);
    return true;
}

void UngDung::ghiCauHinh() {
    std::string tep = ch_.chuoi("__tep", CauHinh::tepMacDinh());
    CauHinh luu = ch_;
    luu.dat("__tep", "");
    // không ghi khoá nội bộ
    std::string nd;
    ch_.ghi(tep);
}

void UngDung::luuToken(const TokenGmail& t) {
    gmail_.datToken(t);
    ch_.dat("gmail.access_token", t.access_token);
    ch_.dat("gmail.refresh_token", t.refresh_token);
    ch_.dat("gmail.token_het_han", std::to_string((long long)t.het_han));
    if (!t.dia_chi.empty()) ch_.dat("gmail.hop_thu", t.dia_chi);
    ghiCauHinh();
}

// Sau khi access token được tự gia hạn, ghi lại vào tệp cấu hình để lần khởi
// động sau không phải gọi gia hạn thêm một lần nữa. Chỉ ghi khi có thay đổi.
void UngDung::luuTokenNeuDoi() {
    const TokenGmail& t = gmail_.token();
    if (t.access_token.empty()) return;
    if (t.access_token == ch_.chuoi("gmail.access_token") &&
        (long long)t.het_han == ch_.nguyen("gmail.token_het_han", 0)) return;
    luuToken(t);
    NK.go("gmail", "Đã lưu access token mới vào tệp cấu hình (hiệu lực đến " +
                   dinhDangGioVN(t.het_han) + ")");
}

std::string UngDung::diaChiChuyenHuong() const {
    int c = congGiaoDien_ > 0 ? congGiaoDien_ : (int)ch_.nguyen("ung_dung.cong_giao_dien", 8899);
    return "http://127.0.0.1:" + std::to_string(c) + "/oauth/callback";
}

std::string UngDung::tenKho() const { return kho_ ? kho_->ten() : "chưa cấu hình"; }

// ---------------------------------------------------------------------
//  Kết nối máy chủ + nạp danh mục
// ---------------------------------------------------------------------
bool UngDung::ketNoiMayChu(std::string& loi) {
    daKetNoi_ = false;
    kho_ = KhoLuuTru::tao(ch_, loi);
    if (!kho_) return false;

    if (!kho_->ketNoi(loi)) { loiCuoi_ = loi; return false; }

    if (!kho_->napCauHinh(chMayChu_, loi)) { loiCuoi_ = loi; return false; }
    ch_.napTuMayChu(chMayChu_);

    if (!kho_->napDanhMuc(dm_, loi)) { loiCuoi_ = loi; return false; }
    NK.tin("danh_muc", "Đã nạp danh mục: " + dm_.tomTat());

    if (dm_.truong.empty() || dm_.nguoi.empty()) {
        NK.canhBao("danh_muc", "Danh mục trường hoặc người xử lý đang trống - "
                               "hãy cập nhật trên trang quản trị trước khi nhận mail");
    }

    pl_.datCauHinh(ch_);
    pl_.datTaoVanBan([this](const std::string& ma, MucVanBan& ra) -> bool {
        std::string l;
        return kho_ && kho_->taoVanBanTuDong(ma, ra, l);
    });
    ai_.datCauHinh(CauHinhAi::tuCauHinh(ch_));

    // Đẩy nhật ký lên máy chủ
    KhoLuuTru* k = kho_.get();
    NK.datGhiCsdl([k](const DongNhatKy& d) {
        if ((int)d.muc >= (int)Muc::INFO) k->ghiNhatKy(d);
    });

    daKetNoi_ = true;
    return true;
}

// ---------------------------------------------------------------------
//  Tiến trình
// ---------------------------------------------------------------------
void UngDung::datTienTrinh(const std::string& buoc, int daLam, int tong, const std::string& td) {
    std::lock_guard<std::mutex> g(khoaTienTrinh_);
    tienTrinh_.buoc = buoc;
    tienTrinh_.da_lam = daLam;
    tienTrinh_.tong = tong;
    tienTrinh_.thong_diep = td;
}

DongTienTrinh UngDung::tienTrinh() const {
    std::lock_guard<std::mutex> g(khoaTienTrinh_);
    return tienTrinh_;
}

// ---------------------------------------------------------------------
//  Tính mã băm phục vụ chống trùng
// ---------------------------------------------------------------------
void UngDung::tinhHash(BanGhiEmail& em) const {
    std::vector<std::string> tienTo = split(ch_.chuoiMayChu("trung.bo_qua_tien_to",
                                            "RE:,FW:,FWD:,TRA LOI:,CHUYEN TIEP:"), ',');
    em.tieu_de_chuan = chuanHoaTieuDe(em.tieu_de, tienTo);

    // Chuẩn hoá phần thân: bỏ dấu, in hoa, gom khoảng trắng
    std::string than = toUpper(boDauTiengViet(em.noi_dung_text));
    std::string gon;
    bool sp = false;
    for (char c : than) {
        if ((unsigned char)c <= ' ') { sp = true; continue; }
        if (sp && !gon.empty()) gon += ' ';
        sp = false;
        gon += c;
    }

    em.hash_noi_dung = sha256Hex(em.tieu_de_chuan + "\n" + toLower(em.nguoi_gui) + "\n" + gon);

    // Mã băm tập tệp: sắp xếp theo "hash:dung_luong" để không phụ thuộc thứ tự
    std::vector<std::string> ds;
    for (const auto& t : em.tep) {
        std::string h = t.hash.empty() ? ("?" + t.ten) : t.hash;
        ds.push_back(h + ":" + std::to_string(t.dung_luong));
    }
    std::sort(ds.begin(), ds.end());
    em.hash_tep = ds.empty() ? sha256Hex("khong-co-tep") : sha256Hex(join(ds, "|"));
    em.hash_tong_hop = sha256Hex(em.hash_noi_dung + "|" + em.hash_tep);
}

// ---------------------------------------------------------------------
//  Tải nội dung tệp đính kèm
// ---------------------------------------------------------------------
bool UngDung::taiTepDinhKem(BanGhiEmail& em, ThongKePhien& tk) {
    long long gioiHanMb = ch_.nguyen("ung_dung.dung_luong_tep_toi_da_mb",
                                     ch_.nguyenMayChu("api.dung_luong_toi_da_mb", 25));
    if (gioiHanMb <= 0) gioiHanMb = 25;
    long long gioiHan = gioiHanMb * 1024 * 1024;
    bool tatCaOk = true;

    for (auto& t : em.tep) {
        if (t.da_tai) {
            t.hash = sha256Hex(t.du_lieu);
            t.dung_luong = (long long)t.du_lieu.size();
            continue;
        }
        if (t.dung_luong > gioiHan) {
            t.bo_qua = true;
            t.ly_do_bo_qua = "Tệp " + dinhDangDungLuong(t.dung_luong) +
                             " vượt giới hạn " + std::to_string(gioiHanMb) + " MB";
            NK.canhBao("tep", "Bỏ qua tệp '" + t.ten + "': " + t.ly_do_bo_qua);
            continue;
        }
        if (t.gmail_attachment_id.empty()) {
            t.bo_qua = true;
            t.ly_do_bo_qua = "Không có mã tệp trên Gmail";
            continue;
        }
        std::string loi;
        if (!gmail_.layTepDinhKem(em.gmail_id, t.gmail_attachment_id, t.du_lieu, loi)) {
            t.bo_qua = true;
            t.ly_do_bo_qua = loi;
            tatCaOk = false;
            tk.so_loi++;
            NK.loi("tep", "Không tải được tệp '" + t.ten + "': " + loi);
            continue;
        }
        t.da_tai = true;
        t.dung_luong = (long long)t.du_lieu.size();
        t.hash = sha256Hex(t.du_lieu);
        if (t.mime.empty()) t.mime = doanMimeTuTen(t.ten);
        tk.so_tep++;
    }
    return tatCaOk;
}

// ---------------------------------------------------------------------
//  Nhờ AI khi không đọc được mã
// ---------------------------------------------------------------------
bool UngDung::nhoAi(BanGhiEmail& em, std::vector<NhomCongViec>& nhom, ThongKePhien& tk) {
    if (!ai_.bat()) return false;

    bool can = nhom.empty();
    for (const auto& n : nhom) if (!n.du_thong_tin) can = true;
    if (!can) return false;

    KetQuaAi kq;
    std::string loi;
    NK.tin("ai", "Nhờ AI đọc mail: " + catUtf8(em.tieu_de, 120));
    if (!ai_.doanMa(em, dm_, kq, loi)) {
        NK.canhBao("ai", "AI không xử lý được: " + loi);
        return false;
    }
    tk.so_dung_ai++;

    char buf[128];
    std::snprintf(buf, sizeof(buf), "%.2f", kq.do_tin_cay);
    em.ghi_chu_ai = "AI đề xuất " + kq.ma.chuoi() + " (độ tin cậy " + buf + "). " + kq.giai_thich;
    NK.tin("ai", em.ghi_chu_ai);

    if (kq.do_tin_cay < ai_.cauHinh().nguong_tin_cay) {
        NK.canhBao("ai", "Độ tin cậy thấp hơn ngưỡng " +
                          std::to_string(ai_.cauHinh().nguong_tin_cay) +
                          " - chuyển sang hàng chờ phân luồng tay");
        return false;
    }

    bool coApDung = false;
    for (auto& n : nhom) {
        if (n.du_thong_tin) continue;
        if (n.ma.truong.empty() && !kq.ma.truong.empty()) n.ma.truong = kq.ma.truong;
        if (n.ma.van_ban.empty() && !kq.ma.van_ban.empty()) n.ma.van_ban = kq.ma.van_ban;
        if (n.ma.nguoi.empty() && !kq.ma.nguoi.empty()) n.ma.nguoi = kq.ma.nguoi;
        // Nếu mã đọc được không có trong danh mục thì thay bằng đề xuất của AI
        if (!dm_.timTruong(n.ma.truong) && dm_.timTruong(kq.ma.truong)) n.ma.truong = kq.ma.truong;
        if (!dm_.timNguoi(n.ma.nguoi) && dm_.timNguoi(kq.ma.nguoi)) n.ma.nguoi = kq.ma.nguoi;

        std::string ghiChuCu = n.ghi_chu;
        n.ghi_chu.clear();
        n.nguon = "ai";
        n.do_tin_cay = kq.do_tin_cay;
        pl_.hoanThien(n, em);
        if (!ghiChuCu.empty()) n.ghi_chu = ghiChuCu + " | " + n.ghi_chu;
        n.ghi_chu += (n.ghi_chu.empty() ? "" : " | ");
        n.ghi_chu += "AI: " + catUtf8(kq.giai_thich, 500);
        if (n.du_thong_tin) coApDung = true;
    }
    if (coApDung) {
        em.nguon_phan_luong = "ai";
        em.do_tin_cay = kq.do_tin_cay;
    }
    return coApDung;
}

// ---------------------------------------------------------------------
//  Xử lý một mail
// ---------------------------------------------------------------------
bool UngDung::xuLyMotMail(const std::string& id, ThongKePhien& tk, std::string& loi) {
    long long idCu = 0;
    if (kho_->daLuu(id, idCu, loi) && idCu > 0) {
        NK.go("mail", "Bỏ qua mail đã lưu: " + id);
        tk.so_mail_trung++;
        return true;
    }
    loi.clear();

    Json j;
    if (!gmail_.layMail(id, j, loi)) return false;

    BanGhiEmail em;
    Gmail::phanTichMail(j, em);
    em.hop_thu = gmail_.token().dia_chi.empty() ? ch_.chuoi("gmail.hop_thu") : gmail_.token().dia_chi;

    // Thư đã bị người dùng xoá thì không lôi lại, dù cờ includeSpamTrash có bật
    if (em.trongThung()) {
        NK.go("mail", "Bỏ qua mail nằm trong Thùng rác: " + id);
        return true;
    }

    // Truy vấn được nới ra để bắt thư dán link, nên Gmail trả về cả những thư
    // vô can (chữ "drive.google.com" nằm trong chữ ký chẳng hạn). Thư không tệp
    // mà cũng không có link chia sẻ thì không phải hồ sơ - bỏ, đừng làm rác kho.
    if (em.tep.empty() && em.lien_ket_ngoai.empty()) {
        NK.go("mail", "Bỏ qua mail không có tệp đính kèm và không có link chia sẻ: " +
                      catUtf8(em.tieu_de, 100));
        return true;
    }

    NK.tin("mail", std::string(em.tuSpam() ? "[THƯ RÁC] Đang xử lý: " : "Đang xử lý: ") +
                   "[" + dinhDangGioVN(em.ngay_gui, "%d/%m/%Y %H:%M") + "] " +
                   catUtf8(em.tieu_de, 150) + " - " + em.nguoi_gui +
                   " (" + std::to_string(em.tep.size()) + " tệp" +
                   (em.lien_ket_ngoai.empty() ? "" :
                    ", " + std::to_string(em.lien_ket_ngoai.size()) + " link") + ")");

    // Thư chỉ có link: KHÔNG lưu được tệp vào kho. Phải nói thẳng để người xử lý
    // biết mà vào Drive tải về, và để trường biết đường lần sau đính kèm thẳng.
    if (em.tep.empty() && !em.lien_ket_ngoai.empty()) {
        tk.so_mail_link++;
        std::string ds;
        for (const auto& u : em.lien_ket_ngoai) {
            if (!ds.empty()) ds += " | ";
            ds += catUtf8(u, 160);
        }
        NK.canhBao("link", "Thư KHÔNG có tệp đính kèm, chỉ có link chia sẻ - kho lưu trữ "
                           "sẽ không giữ được bản tệp: " + catUtf8(em.tieu_de, 100) +
                           " - " + em.nguoi_gui + ". Link: " + catUtf8(ds, 600));
    }

    if (em.tuSpam()) {
        tk.so_mail_spam++;
        NK.canhBao("spam", "Vớt được từ hộp Thư rác: " + catUtf8(em.tieu_de, 120) +
                           " - " + em.nguoi_gui + ". Nên thêm địa chỉ này vào bộ lọc "
                           "\"không bao giờ cho vào Thư rác\" của Gmail.");
    }

    taiTepDinhKem(em, tk);
    tinhHash(em);

    std::vector<NhomCongViec> nhom;
    pl_.phanLuong(em, nhom);

    bool canAi = nhom.empty();
    for (const auto& n : nhom) if (!n.du_thong_tin) canAi = true;
    if (canAi) nhoAi(em, nhom, tk);

    KetQuaLuu kq;
    if (!kho_->luuEmail(em, nhom, kq, loi)) return false;

    switch (kq.trang) {
        case KetQuaLuu::DA_TON_TAI:
            tk.so_mail_trung++;
            NK.go("mail", kq.thong_diep);
            break;
        case KetQuaLuu::TRUNG_HOAN_TOAN:
            tk.so_mail_trung++;
            NK.canhBao("trung", "TRÙNG: " + catUtf8(em.tieu_de, 100) + " - " + kq.thong_diep);
            break;
        case KetQuaLuu::BAN_MOI:
            tk.so_mail_ban_moi++;
            tk.so_cong_viec += kq.so_cong_viec;
            tk.so_cho_phan_luong += kq.so_cho_phan_luong;
            NK.canhBao("trung", "BẢN MỚI: " + catUtf8(em.tieu_de, 100) + " - " + kq.thong_diep);
            break;
        default:
            tk.so_mail_moi++;
            tk.so_cong_viec += kq.so_cong_viec;
            tk.so_cho_phan_luong += kq.so_cho_phan_luong;
            std::string mo;
            for (const auto& n : nhom) {
                if (!mo.empty()) mo += ", ";
                mo += n.ma.chuoi();
                if (n.du_thong_tin) {
                    const MucNguoiXuLy* p = dm_.nguoiTheoId(n.id_nguoi);
                    if (p) mo += " -> " + p->ho_ten;
                } else mo += " -> CHỜ PHÂN LUỒNG TAY";
            }
            NK.tin("mail", "Đã lưu #" + std::to_string(kq.id_email) + ": " + mo);
            break;
    }
    return true;
}

// ---------------------------------------------------------------------
//  Đồng bộ
// ---------------------------------------------------------------------
bool UngDung::dongBo(ThongKePhien& tk, std::string& loi, int gioiHan, const std::string& truyVanRieng) {
    std::unique_lock<std::mutex> khoa(khoaViec_, std::try_to_lock);
    if (!khoa.owns_lock()) {
        loi = "Một phiên đồng bộ khác đang chạy";
        return false;
    }
    dangDongBo_.store(true);
    yeuCauDung_.store(false);
    tk = ThongKePhien();
    tk.bat_dau = nowEpoch();

    struct Ket {
        UngDung* u;
        ~Ket() { u->dangDongBo_.store(false); }
    } ket{this};

    datTienTrinh("chuan_bi", 0, 0, "Đang kiểm tra kết nối...");

    if (!daKetNoi_) {
        if (!ketNoiMayChu(loi)) { datTienTrinh("loi", 0, 0, loi); return false; }
    } else if (!kho_->kiemTra(loi)) {
        NK.canhBao("csdl", "Kết nối rớt, đang nối lại: " + loi);
        if (!ketNoiMayChu(loi)) { datTienTrinh("loi", 0, 0, loi); return false; }
    }

    if (!gmail_.damBaoToken(loi)) { datTienTrinh("loi", 0, 0, loi); return false; }
    luuTokenNeuDoi();

    std::string hopThu = gmail_.token().dia_chi;
    if (hopThu.empty()) {
        long long tong = 0;
        std::string l2;
        if (gmail_.hoSo(hopThu, tong, l2)) {
            TokenGmail t = gmail_.token();
            t.dia_chi = hopThu;
            luuToken(t);
        }
    }

    std::string truyVan = truyVanRieng.empty()
        ? ch_.uuTien("gmail.truy_van", "gmail.truy_van", "has:attachment newer_than:30d")
        : truyVanRieng;
    int soLuong = gioiHan > 0 ? gioiHan
        : (int)toLL(ch_.uuTien("gmail.so_mail_moi_lan", "gmail.so_mail_moi_lan", "50"), 50);
    if (soLuong <= 0) soLuong = 50;

    // Google hay xếp nhầm báo cáo của các trường vào hộp Thư rác. Bỏ qua thì
    // thống kê báo "chưa nộp" oan, nên mặc định quét luôn cả hộp đó.
    bool quetSpam = toBool(ch_.uuTien("gmail.quet_spam", "gmail.quet_spam", "1"), true);

    // Trường không đính kèm mà dán link Google Drive thì "has:attachment" loại
    // thẳng thư đó, hệ thống không bao giờ nhìn thấy. Mặc định nới truy vấn.
    bool nhanLink = toBool(ch_.uuTien("gmail.nhan_link_drive", "gmail.nhan_link_drive", "1"), true);
    std::string truyVanThat = nhanLink ? Gmail::moRongTruyVanLink(truyVan) : truyVan;

    kho_->moPhien(tk, hopThu, truyVanThat, loi);
    NK.tin("dong_bo", "Bắt đầu phiên đồng bộ - hộp thư: " + (hopThu.empty() ? "(chưa rõ)" : hopThu) +
                      " | điều kiện: " + truyVanThat + " | tối đa " + std::to_string(soLuong) + " mail" +
                      (quetSpam ? " | có quét hộp Thư rác" : " | bỏ qua hộp Thư rác") +
                      (nhanLink ? " | có bắt link chia sẻ" : " | bỏ qua link chia sẻ"));

    datTienTrinh("liet_ke", 0, 0, "Đang lấy danh sách mail...");
    std::vector<std::string> ids;
    std::string canhBaoSpam;
    if (!gmail_.danhSachMail(truyVan, soLuong, quetSpam, nhanLink, ids, loi, &canhBaoSpam)) {
        tk.thong_diep = loi;
        tk.so_loi++;
        std::string l2;
        kho_->dongPhien(tk, "loi", l2);
        datTienTrinh("loi", 0, 0, loi);
        NK.loi("dong_bo", "Không lấy được danh sách mail: " + loi);
        return false;
    }
    if (!canhBaoSpam.empty()) NK.canhBao("dong_bo", canhBaoSpam);
    tk.so_mail_quet = (int)ids.size();
    NK.tin("dong_bo", "Tìm thấy " + std::to_string(ids.size()) + " mail phù hợp");

    int i = 0;
    for (const auto& id : ids) {
        if (yeuCauDung_.load()) {
            NK.canhBao("dong_bo", "Đã dừng theo yêu cầu người dùng");
            break;
        }
        i++;
        datTienTrinh("xu_ly", i, (int)ids.size(), "Đang xử lý mail " + std::to_string(i) +
                                                  "/" + std::to_string(ids.size()));
        std::string l;
        if (!xuLyMotMail(id, tk, l)) {
            tk.so_loi++;
            NK.loi("mail", "Lỗi xử lý mail " + id + ": " + l);
        }
    }

    luuTokenNeuDoi();
    tk.ket_thuc = nowEpoch();
    char buf[400];
    std::snprintf(buf, sizeof(buf),
        "Hoàn tất: quét %d mail, mới %d, bản mới %d, trùng %d, công việc %d, tệp %d, "
        "chờ phân luồng %d, dùng AI %d, vớt từ Thư rác %d, chỉ có link %d, lỗi %d (%.1f giây)",
        tk.so_mail_quet, tk.so_mail_moi, tk.so_mail_ban_moi, tk.so_mail_trung, tk.so_cong_viec,
        tk.so_tep, tk.so_cho_phan_luong, tk.so_dung_ai, tk.so_mail_spam, tk.so_mail_link, tk.so_loi,
        (double)(tk.ket_thuc - tk.bat_dau));
    tk.thong_diep = buf;
    NK.tin("dong_bo", tk.thong_diep);

    std::string l2;
    kho_->dongPhien(tk, tk.so_loi > 0 ? "hoan_tat" : "hoan_tat", l2);
    phienCuoi_ = tk;
    lanDongBoCuoi_ = nowEpoch();
    datTienTrinh("xong", (int)ids.size(), (int)ids.size(), tk.thong_diep);
    return true;
}

// ---------------------------------------------------------------------
//  Dịch vụ chạy nền
// ---------------------------------------------------------------------
void UngDung::batDauDichVu() {
    if (dichVuChay_.load()) return;
    dichVuChay_.store(true);
    luongDichVu_ = std::thread(&UngDung::vongLapDichVu, this);
}

void UngDung::dungDichVu() {
    if (!dichVuChay_.load()) {
        if (luongDichVu_.joinable()) luongDichVu_.join();
        return;
    }
    dichVuChay_.store(false);
    yeuCauDung_.store(true);
    if (luongDichVu_.joinable()) luongDichVu_.join();
}

void UngDung::vongLapDichVu() {
    int chuKy = (int)toLL(ch_.uuTien("ung_dung.chu_ky_phut", "gmail.chu_ky_phut", "15"), 15);
    if (chuKy < 1) chuKy = 1;
    NK.tin("dich_vu", "Dịch vụ tự động đã bật - quét mỗi " + std::to_string(chuKy) + " phút");

    while (dichVuChay_.load()) {
        ThongKePhien tk;
        std::string loi;
        yeuCauDung_.store(false);
        if (!dongBo(tk, loi)) NK.loi("dich_vu", "Phiên tự động thất bại: " + loi);

        lanChayKe_.store(nowEpoch() + chuKy * 60);
        for (int i = 0; i < chuKy * 60 && dichVuChay_.load(); i++) nguGiay(1);
    }
    lanChayKe_.store(0);
    NK.tin("dich_vu", "Dịch vụ tự động đã tắt");
}

// ---------------------------------------------------------------------
//  Trạng thái cho giao diện
// ---------------------------------------------------------------------
Json UngDung::trangThai() {
    Json j = Json::doiTuong();
    j.dat("ok", true);
    j.dat("thoi_gian", gioVNHienTai());
    j.dat("may_chu", tenMayChu());
    j.dat("nen_tang", HttpClient::tenNenTang());
    j.dat("phien_ban", MR_PHIEN_BAN);
    j.dat("phien_ban_day_du", MR_PHIEN_BAN_DAY_DU);

    Json kho = Json::doiTuong();
    kho.dat("che_do", ch_.chuoi("luu_tru.che_do", "mysql"));
    kho.dat("ten", tenKho());
    kho.dat("da_ket_noi", daKetNoi_);
    kho.dat("mo_ta", ch_.chuoi("luu_tru.che_do", "mysql") == "api"
                        ? ch_.chuoi("api.url")
                        : (ch_.chuoi("mysql.may_chu") + ":" + ch_.chuoi("mysql.cong", "3306") +
                           "/" + ch_.chuoi("mysql.co_so_du_lieu")));
    kho.dat("loi", loiCuoi_);
    j.dat("kho", kho);

    Json g = Json::doiTuong();
    const TokenGmail& t = gmail_.token();
    g.dat("co_ung_dung", gmail_.coUngDung());
    g.dat("da_dang_nhap", !t.access_token.empty() || !t.refresh_token.empty());
    g.dat("hop_thu", t.dia_chi.empty() ? ch_.chuoi("gmail.hop_thu") : t.dia_chi);
    g.dat("con_han", t.conHan());
    g.dat("het_han", t.het_han > 0 ? dinhDangGioVN(t.het_han) : "");
    g.dat("co_refresh", t.coRefresh());
    g.dat("truy_van", ch_.uuTien("gmail.truy_van", "gmail.truy_van", "has:attachment newer_than:30d"));
    j.dat("gmail", g);

    Json d = Json::doiTuong();
    d.dat("so_truong", (long long)dm_.truong.size());
    d.dat("so_nguoi", (long long)dm_.nguoi.size());
    d.dat("so_van_ban", (long long)dm_.van_ban.size());
    j.dat("danh_muc", d);

    Json a = Json::doiTuong();
    a.dat("bat", ai_.bat());
    a.dat("url", ai_.cauHinh().url);
    a.dat("model", ai_.cauHinh().model);
    a.dat("max_tokens", (long long)ai_.cauHinh().max_tokens);
    a.dat("timeout", (long long)ai_.cauHinh().timeout);
    a.dat("nguong_tin_cay", ai_.cauHinh().nguong_tin_cay);
    j.dat("ai", a);

    Json dv = Json::doiTuong();
    dv.dat("dang_chay", dichVuChay_.load());
    dv.dat("dang_dong_bo", dangDongBo_.load());
    long long ke = lanChayKe_.load();
    dv.dat("lan_chay_ke", ke > 0 ? dinhDangGioVN(ke) : "");
    dv.dat("chu_ky_phut", toLL(ch_.uuTien("ung_dung.chu_ky_phut", "gmail.chu_ky_phut", "15"), 15));
    j.dat("dich_vu", dv);

    DongTienTrinh tt = tienTrinh();
    Json p = Json::doiTuong();
    p.dat("buoc", tt.buoc);
    p.dat("da_lam", (long long)tt.da_lam);
    p.dat("tong", (long long)tt.tong);
    p.dat("thong_diep", tt.thong_diep);
    j.dat("tien_trinh", p);

    Json ph = Json::doiTuong();
    ph.dat("bat_dau", phienCuoi_.bat_dau > 0 ? dinhDangGioVN(phienCuoi_.bat_dau) : "");
    ph.dat("ket_thuc", phienCuoi_.ket_thuc > 0 ? dinhDangGioVN(phienCuoi_.ket_thuc) : "");
    ph.dat("so_mail_quet", (long long)phienCuoi_.so_mail_quet);
    ph.dat("so_mail_moi", (long long)phienCuoi_.so_mail_moi);
    ph.dat("so_mail_trung", (long long)phienCuoi_.so_mail_trung);
    ph.dat("so_mail_ban_moi", (long long)phienCuoi_.so_mail_ban_moi);
    ph.dat("so_cong_viec", (long long)phienCuoi_.so_cong_viec);
    ph.dat("so_tep", (long long)phienCuoi_.so_tep);
    ph.dat("so_dung_ai", (long long)phienCuoi_.so_dung_ai);
    ph.dat("so_cho_phan_luong", (long long)phienCuoi_.so_cho_phan_luong);
    ph.dat("so_mail_spam", (long long)phienCuoi_.so_mail_spam);
    ph.dat("so_loi", (long long)phienCuoi_.so_loi);
    ph.dat("thong_diep", phienCuoi_.thong_diep);
    j.dat("phien_cuoi", ph);

    Json ud = Json::doiTuong();
    ud.dat("ten", ch_.chuoiMayChu("app.ten_ung_dung",
              "Hệ thống phân luồng Mail công vụ - Phòng GDPT-GDTX SGDĐT Đồng Nai"));
    ud.dat("ten_ngan", ch_.chuoiMayChu("app.ten_ngan", "Phân luồng Mail công vụ"));
    ud.dat("don_vi", ch_.chuoiMayChu("app.don_vi", "Phòng GDPT-GDTX - Sở GD&ĐT Đồng Nai"));
    ud.dat("ban_quyen", ch_.chuoiMayChu("app.ban_quyen", "Thiết kế bởi Trương Anh Tuấn"));
    ud.dat("mau", ch_.chuoiMayChu("app.mau_chu_dao", "#1e5eff"));
    j.dat("ung_dung", ud);

    return j;
}

} // namespace mr
