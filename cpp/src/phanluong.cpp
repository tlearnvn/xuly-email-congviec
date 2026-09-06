// =====================================================================
//  phanluong.cpp - Tách mã và phân luồng
// =====================================================================
#include "phanluong.h"
#include "nhatky.h"
#include "util.h"

#include <cctype>
#include <algorithm>
#include <map>

namespace mr {

void BoPhanLuong::datCauHinh(const CauHinh& ch) {
    cho_phep_ma_vb_moi_ = ch.logicMayChu("phanluong.cho_phep_ma_vb_moi", true);
    bat_buoc_ma_truong_ = ch.logicMayChu("phanluong.bat_buoc_ma_truong", true);
    bat_buoc_ma_nguoi_  = ch.logicMayChu("phanluong.bat_buoc_ma_nguoi", true);
    nguoi_mac_dinh_     = ch.chuoiMayChu("phanluong.nguoi_xu_ly_mac_dinh", "");
    std::string dpc     = ch.chuoiMayChu("phanluong.dau_phan_cach", "");
    if (!dpc.empty()) dau_phan_cach_ = dpc;
}

// ---------------------------------------------------------------------
//  Tách chuỗi thành các từ theo dấu phân cách
// ---------------------------------------------------------------------
std::vector<std::string> BoPhanLuong::tachTu(const std::string& sIn) const {
    // Bỏ dấu tiếng Việt để mọi ký tự đều nằm trong ASCII
    std::string s = boDauTiengViet(sIn);
    std::vector<std::string> tu;
    std::string cur;
    for (char c : s) {
        bool laPhanCach = (dau_phan_cach_.find(c) != std::string::npos) || (unsigned char)c <= ' ';
        if (laPhanCach) {
            if (!cur.empty()) tu.push_back(cur);
            cur.clear();
        } else if (std::isalnum((unsigned char)c)) {
            cur += c;
        } else {
            if (!cur.empty()) tu.push_back(cur);
            cur.clear();
        }
    }
    if (!cur.empty()) tu.push_back(cur);
    return tu;
}

static bool toanSo(const std::string& s) {
    if (s.empty()) return false;
    for (char c : s) if (!std::isdigit((unsigned char)c)) return false;
    return true;
}

static bool batDauBangChu(const std::string& s) {
    return !s.empty() && std::isalpha((unsigned char)s[0]);
}

static bool hinhDangMaTruong(const std::string& s) {
    return !s.empty() && s.size() <= 8 && (toanSo(s) || (batDauBangChu(s) && s.size() <= 6));
}

static bool hinhDangMaVanBan(const std::string& s) {
    return !s.empty() && s.size() <= 10;
}

static bool hinhDangMaNguoi(const std::string& s) {
    if (s.size() < 2 || s.size() > 10) return false;
    if (!batDauBangChu(s)) return false;
    for (char c : s) if (!std::isalnum((unsigned char)c)) return false;
    return true;
}

// Toàn chữ in hoa (mã người xử lý viết theo quy ước: TAT, NVA...)
static bool toanChuHoa(const std::string& s) {
    bool coChu = false;
    for (char c : s) {
        if (std::isalpha((unsigned char)c)) {
            if (!std::isupper((unsigned char)c)) return false;
            coChu = true;
        }
    }
    return coChu;
}

// Đúng khuôn quy định: <số>_<số hoặc mã có chữ số>_<CHỮ IN HOA>
static bool dangKhuonChuan(const std::string& a, const std::string& b, const std::string& c) {
    if (!toanSo(a)) return false;
    bool bCoSo = false;
    for (char x : b) if (std::isdigit((unsigned char)x)) { bCoSo = true; break; }
    return bCoSo && toanChuHoa(c);
}

// Mã văn bản chỉ được tự thêm vào danh mục khi trông giống một mã thật
static bool maVanBanDangTinCay(const std::string& s) {
    if (s.empty() || s.size() > 10) return false;
    bool coSo = false;
    for (char c : s) {
        if (!std::isalnum((unsigned char)c)) return false;
        if (std::isdigit((unsigned char)c)) coSo = true;
    }
    return coSo;
}

// Ngưỡng tối thiểu để coi ba từ liền nhau thực sự là mã hồ sơ
static const double NGUONG_CHAP_NHAN = 0.55;

// ---------------------------------------------------------------------
//  Tách mã 001_001_TAT khỏi một chuỗi
// ---------------------------------------------------------------------
bool BoPhanLuong::tachMa(const std::string& sIn, MaTimDuoc& ra, bool laTenTep) const {
    ra = MaTimDuoc();
    std::string s = sIn;
    if (laTenTep) {
        // bỏ phần mở rộng
        size_t p = s.find_last_of('.');
        if (p != std::string::npos && s.size() - p <= 6) s = s.substr(0, p);
    }
    std::vector<std::string> tu = tachTu(s);
    if (tu.size() < 3) return false;

    MaTimDuoc totNhat;
    bool coKetQua = false;

    for (size_t i = 0; i + 2 < tu.size(); i++) {
        const std::string& a = tu[i];
        const std::string& b = tu[i + 1];
        const std::string& c = tu[i + 2];
        if (!hinhDangMaTruong(a) || !hinhDangMaVanBan(b) || !hinhDangMaNguoi(c)) continue;

        MaTimDuoc ut;
        ut.ma.truong = a;
        ut.ma.van_ban = b;
        ut.ma.nguoi = toUpper(c);
        ut.nguon_van_ban = sIn;

        if (dm_) {
            ut.khop_truong  = dm_->timTruong(a) != nullptr;
            ut.khop_nguoi   = dm_->timNguoi(c) != nullptr;
            ut.khop_van_ban = dm_->timVanBan(b) != nullptr;
        }
        // Chấm điểm: khớp danh mục quan trọng nhất, sau đó tới hình dạng chuẩn
        ut.diem = 0.30;
        if (ut.khop_truong)  ut.diem += 0.30;
        if (ut.khop_nguoi)   ut.diem += 0.30;
        if (ut.khop_van_ban) ut.diem += 0.10;
        if (toanSo(a) && toanSo(b)) ut.diem += 0.05;
        // Đúng khuôn 001_001_TAT: hai nhóm số rồi tới mã chữ in hoa
        if (dangKhuonChuan(a, b, c)) ut.diem += 0.20;
        if (ut.diem > 1.0) ut.diem = 1.0;

        if (!coKetQua || ut.diem > totNhat.diem) { totNhat = ut; coKetQua = true; }
        if (ut.khop_truong && ut.khop_nguoi) break;      // đã đủ tin cậy
    }

    // Chỉ chấp nhận khi có căn cứ: khớp danh mục hoặc đúng khuôn mã quy định.
    // Nếu không, ba từ liền nhau trong tên tệp dễ bị hiểu nhầm thành mã.
    if (!coKetQua || totNhat.diem < NGUONG_CHAP_NHAN) return false;
    ra = totNhat;
    return true;
}

// ---------------------------------------------------------------------
//  Tìm trường theo địa chỉ email người gửi
// ---------------------------------------------------------------------
long long BoPhanLuong::timTruongTheoEmail(const std::string& email) const {
    if (!dm_ || email.empty()) return 0;
    std::string e = toLower(trim(email));
    for (const auto& t : dm_->truong)
        if (!t.email.empty() && toLower(trim(t.email)) == e) return t.id;
    return 0;
}

// ---------------------------------------------------------------------
//  Hoàn thiện một nhóm công việc: đối chiếu danh mục, bổ khuyết
// ---------------------------------------------------------------------
void BoPhanLuong::hoanThien(NhomCongViec& n, const BanGhiEmail& em) {
    n.id_truong = n.id_van_ban = n.id_nguoi = 0;
    std::vector<std::string> thieu;

    if (dm_) {
        const MucTruong* t = dm_->timTruong(n.ma.truong);
        // Thiếu mã trường -> thử suy ra từ địa chỉ email người gửi
        if (!t) {
            long long idT = timTruongTheoEmail(em.nguoi_gui);
            if (idT > 0) {
                for (const auto& x : dm_->truong) if (x.id == idT) { t = &x; break; }
                if (t) {
                    n.ghi_chu += (n.ghi_chu.empty() ? "" : " | ");
                    n.ghi_chu += "Mã trường suy ra từ địa chỉ email người gửi";
                    if (n.nguon == "khong_xac_dinh") n.nguon = "nguoi_gui";
                }
            }
        }
        if (t) { n.id_truong = t->id; n.ma.truong = t->ma; }

        const MucVanBan* v = dm_->timVanBan(n.ma.van_ban);
        // Chỉ tự thêm mã văn bản mới khi có căn cứ chắc chắn (đã khớp trường
        // hoặc người xử lý) và mã trông giống mã thật - tránh làm bẩn danh mục.
        bool coCanCu = (t != nullptr) || (dm_->timNguoi(n.ma.nguoi) != nullptr);
        if (!v && coCanCu && maVanBanDangTinCay(n.ma.van_ban) && cho_phep_ma_vb_moi_ && taoVanBan_) {
            MucVanBan moi;
            if (taoVanBan_(n.ma.van_ban, moi)) {
                dm_->themVanBan(moi);
                v = dm_->timVanBan(n.ma.van_ban);
                NK.tin("danh_muc", "Đã tự thêm mã văn bản mới '" + n.ma.van_ban +
                                   "' vào danh mục (cần đặt tên chính thức)");
            }
        }
        if (v) { n.id_van_ban = v->id; n.ma.van_ban = v->ma; }

        const MucNguoiXuLy* p = dm_->timNguoi(n.ma.nguoi);
        if (!p && v && v->id_nguoi_mac_dinh > 0) {
            p = dm_->nguoiTheoId(v->id_nguoi_mac_dinh);
            if (p) {
                n.ma.nguoi = p->ma;
                if (n.nguon == "khong_xac_dinh" || n.ma.nguoi.empty()) n.nguon = "mac_dinh";
                n.ghi_chu += (n.ghi_chu.empty() ? "" : " | ");
                n.ghi_chu += "Người xử lý lấy theo mặc định của mã văn bản " + n.ma.van_ban;
            }
        }
        if (!p && !nguoi_mac_dinh_.empty()) {
            p = dm_->timNguoi(nguoi_mac_dinh_);
            if (p) {
                n.ma.nguoi = p->ma;
                n.ghi_chu += (n.ghi_chu.empty() ? "" : " | ");
                n.ghi_chu += "Chuyển cho người xử lý mặc định của hệ thống";
                if (n.nguon == "khong_xac_dinh") n.nguon = "mac_dinh";
            }
        }
        if (p) n.id_nguoi = p->id;
    }

    if (n.ma.truong.empty()) thieu.push_back("mã trường");
    else if (bat_buoc_ma_truong_ && n.id_truong == 0) thieu.push_back("mã trường '" + n.ma.truong + "' không có trong danh mục");
    if (n.ma.van_ban.empty()) thieu.push_back("mã văn bản");
    if (n.ma.nguoi.empty()) thieu.push_back("mã người xử lý");
    else if (bat_buoc_ma_nguoi_ && n.id_nguoi == 0) thieu.push_back("mã người xử lý '" + n.ma.nguoi + "' không có trong danh mục");

    n.du_thong_tin = thieu.empty() && n.id_nguoi > 0;
    if (!thieu.empty()) {
        n.ghi_chu += (n.ghi_chu.empty() ? "" : " | ");
        n.ghi_chu += "Chờ phân luồng tay - thiếu: " + join(thieu, ", ");
    }
}

// ---------------------------------------------------------------------
//  Phân luồng toàn bộ email
// ---------------------------------------------------------------------
void BoPhanLuong::phanLuong(BanGhiEmail& em, std::vector<NhomCongViec>& nhom) {
    nhom.clear();

    // 1) Đọc mã trên từng tên tệp đính kèm
    for (auto& t : em.tep) {
        MaTimDuoc kq;
        if (tachMa(t.ten, kq, true)) {
            t.ma = kq.ma;
            t.diem_ma = kq.diem;
            t.doc_duoc_ma = true;
        }
    }

    // 2) Đọc mã trên tiêu đề
    MaTimDuoc tuTieuDe;
    bool coTieuDe = tachMa(em.tieu_de, tuTieuDe, false);
    if (coTieuDe && !em.ma_chung.coGiTruoc()) {
        em.ma_chung = tuTieuDe.ma;
        em.nguon_phan_luong = "tieu_de";
        em.do_tin_cay = tuTieuDe.diem;
    }

    // 3) Gom nhóm theo mã
    std::vector<std::pair<MaHoSo, NhomCongViec>> gom;
    auto timNhom = [&](const MaHoSo& m) -> NhomCongViec& {
        for (auto& g : gom) if (g.first.bang(m)) return g.second;
        NhomCongViec n;
        n.ma = m;
        gom.push_back(std::make_pair(m, n));
        return gom.back().second;
    };

    std::vector<int> tepKhongMa;
    for (size_t i = 0; i < em.tep.size(); i++) {
        if (em.tep[i].doc_duoc_ma) {
            NhomCongViec& n = timNhom(em.tep[i].ma);
            n.nguon = "ten_tep";
            n.do_tin_cay = std::max(n.do_tin_cay, em.tep[i].diem_ma);
            n.chi_so_tep.push_back((int)i);
        } else {
            tepKhongMa.push_back((int)i);
        }
    }

    // 4) Các tệp không đọc được mã -> theo mã chung của email
    if (!tepKhongMa.empty() || em.tep.empty()) {
        // Trường hợp hay gặp: trường gửi một tệp đặt tên đúng quy ước, kèm thêm
        // công văn hoặc phụ lục đặt tên tự do. Nếu cả thư chỉ có ĐÚNG MỘT nhóm
        // mã đầy đủ và tiêu đề không chỉ sang mã khác, thì các tệp không mã gần
        // như chắc chắn thuộc về nhóm đó - gộp vào thay vì đẻ ra một công việc
        // mồ côi mà quản trị phải phân luồng tay mỗi lần.
        // Từ hai nhóm mã trở lên thì không đoán, vì đoán sai là giao nhầm người.
        NhomCongViec* duyNhat = nullptr;
        if (!tepKhongMa.empty() && !em.ma_chung.coGiTruoc()) {
            int soNhomDu = 0;
            for (auto& g : gom) {
                if (g.second.ma.coDu()) { soNhomDu++; duyNhat = &g.second; }
            }
            if (soNhomDu != 1) duyNhat = nullptr;
        }

        if (duyNhat) {
            for (int i : tepKhongMa) duyNhat->chi_so_tep.push_back(i);
            duyNhat->ghi_chu += (duyNhat->ghi_chu.empty() ? "" : " | ");
            duyNhat->ghi_chu += "Kèm " + std::to_string(tepKhongMa.size()) +
                                " tệp không có mã trong tên, gộp chung vì cả thư "
                                "chỉ có một mã hồ sơ";
        } else {
            MaHoSo mc = em.ma_chung;
            NhomCongViec& n = timNhom(mc);
            if (n.nguon == "khong_xac_dinh")
                n.nguon = coTieuDe ? em.nguon_phan_luong : "khong_xac_dinh";
            n.do_tin_cay = std::max(n.do_tin_cay, coTieuDe ? tuTieuDe.diem : 0.0);
            for (int i : tepKhongMa) n.chi_so_tep.push_back(i);
        }
    }

    // 4b) Thư không đính kèm tệp, chỉ dán link chia sẻ. Ghi thẳng vào ghi chú
    // của công việc để người xử lý mở hộp việc là thấy ngay - đừng để họ tưởng
    // tệp có trong kho rồi đi tìm mãi không ra.
    if (em.tep.empty() && !em.lien_ket_ngoai.empty()) {
        for (auto& g : gom) {
            g.second.ghi_chu += (g.second.ghi_chu.empty() ? "" : " | ");
            g.second.ghi_chu += "Thư không có tệp đính kèm, chỉ có " +
                                std::to_string(em.lien_ket_ngoai.size()) +
                                " link chia sẻ - kho KHÔNG giữ bản tệp, phải mở link để tải";
        }
    }

    // 5) Hoàn thiện từng nhóm
    for (auto& g : gom) {
        hoanThien(g.second, em);
        nhom.push_back(g.second);
    }

    // 6) Cập nhật nguồn phân luồng cho email
    if (!nhom.empty()) {
        bool coTuTenTep = false, tatCaDu = true;
        double tinCayMax = 0;
        for (const auto& n : nhom) {
            if (n.nguon == "ten_tep") coTuTenTep = true;
            if (!n.du_thong_tin) tatCaDu = false;
            tinCayMax = std::max(tinCayMax, n.do_tin_cay);
        }
        if (coTuTenTep) em.nguon_phan_luong = "ten_tep";
        else if (coTieuDe) em.nguon_phan_luong = "tieu_de";
        else if (em.nguon_phan_luong.empty()) em.nguon_phan_luong = "khong_xac_dinh";
        em.do_tin_cay = tinCayMax;
        (void)tatCaDu;
    }
}

} // namespace mr
