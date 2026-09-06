// =====================================================================
//  kholuutru.cpp - Tầng lưu trữ MySQL / API
// =====================================================================
#include "kholuutru.h"
#include "http_client.h"
#include "json.h"
#include "crypto.h"
#include "util.h"

#include <cstdio>
#include <algorithm>

namespace mr {

// =====================================================================
//  DanhMuc
// =====================================================================
void DanhMuc::xayChiMuc() {
    ciTruong_.clear(); ciNguoi_.clear(); ciVanBan_.clear();
    for (size_t i = 0; i < truong.size(); i++) {
        ciTruong_[truong[i].ma_chuan] = i;
        ciTruong_[chuanHoaMa(truong[i].ma)] = i;
    }
    for (size_t i = 0; i < nguoi.size(); i++) {
        ciNguoi_[nguoi[i].ma_chuan] = i;
        ciNguoi_[chuanHoaMa(nguoi[i].ma)] = i;
    }
    for (size_t i = 0; i < van_ban.size(); i++) {
        ciVanBan_[van_ban[i].ma_chuan] = i;
        ciVanBan_[chuanHoaMa(van_ban[i].ma)] = i;
    }
}

const MucTruong* DanhMuc::timTruong(const std::string& ma) const {
    if (ma.empty()) return nullptr;
    auto it = ciTruong_.find(chuanHoaMaSo(ma));
    if (it == ciTruong_.end()) it = ciTruong_.find(chuanHoaMa(ma));
    if (it == ciTruong_.end()) return nullptr;
    return &truong[it->second];
}

const MucNguoiXuLy* DanhMuc::timNguoi(const std::string& ma) const {
    if (ma.empty()) return nullptr;
    auto it = ciNguoi_.find(chuanHoaMa(ma));
    if (it == ciNguoi_.end()) return nullptr;
    return &nguoi[it->second];
}

const MucVanBan* DanhMuc::timVanBan(const std::string& ma) const {
    if (ma.empty()) return nullptr;
    auto it = ciVanBan_.find(chuanHoaMaSo(ma));
    if (it == ciVanBan_.end()) it = ciVanBan_.find(chuanHoaMa(ma));
    if (it == ciVanBan_.end()) return nullptr;
    return &van_ban[it->second];
}

const MucNguoiXuLy* DanhMuc::nguoiTheoId(long long id) const {
    for (const auto& n : nguoi) if (n.id == id) return &n;
    return nullptr;
}

void DanhMuc::themVanBan(const MucVanBan& v) {
    for (auto& x : van_ban) if (x.ma_chuan == v.ma_chuan) { x = v; xayChiMuc(); return; }
    van_ban.push_back(v);
    xayChiMuc();
}

std::string DanhMuc::tomTat() const {
    char buf[160];
    std::snprintf(buf, sizeof(buf), "%d trường, %d người xử lý, %d mã văn bản",
                  (int)truong.size(), (int)nguoi.size(), (int)van_ban.size());
    return buf;
}

// =====================================================================
//  KhoMySql
// =====================================================================
static std::string ngayVN(int64_t epoch) {
    if (epoch <= 0) return "";
    return dinhDangGioVN(epoch);
}

static std::string sqlNgay(int64_t epoch) {
    std::string s = ngayVN(epoch);
    return s.empty() ? "NULL" : ("'" + s + "'");
}

KhoMySql::KhoMySql(const CauHinh& ch) {
    ts_.may_chu       = ch.chuoi("mysql.may_chu", "127.0.0.1");
    ts_.cong          = (int)ch.nguyen("mysql.cong", 3306);
    ts_.nguoi_dung    = ch.chuoi("mysql.nguoi_dung");
    ts_.mat_khau      = ch.chuoi("mysql.mat_khau");
    ts_.co_so_du_lieu = ch.chuoi("mysql.co_so_du_lieu");
    ts_.timeout       = (int)ch.nguyen("mysql.timeout", 30);
    khoiKb_           = ch.nguyen("mysql.kich_thuoc_khoi_kb", 256);
    if (khoiKb_ < 16) khoiKb_ = 16;
    if (khoiKb_ > 4096) khoiKb_ = 4096;
    mayChu_ = tenMayChu();
}

bool KhoMySql::ketNoi(std::string& loi) {
    if (ts_.nguoi_dung.empty() || ts_.co_so_du_lieu.empty()) {
        loi = "Chưa khai báo đầy đủ thông tin MySQL (người dùng / cơ sở dữ liệu)";
        return false;
    }
    if (!db_.ketNoi(ts_, loi)) return false;
    NK.tin("csdl", "Đã kết nối MySQL " + ts_.may_chu + ":" + std::to_string(ts_.cong) +
                   "/" + ts_.co_so_du_lieu + " (" + db_.phienBanMayChu() + ")");

    // Kết nối được nhưng cơ sở dữ liệu chưa có bảng -> báo rõ để người dùng biết
    // phải chạy trình cài đặt của phần web trước, thay vì hiện lỗi SQL khó hiểu.
    if (!kiemTraDaCaiDat(loi)) {
        db_.dong();
        return false;
    }

    // Đọc tham số chống trùng từ bảng cấu hình
    std::map<std::string, std::string> ch;
    std::string l2;
    if (napCauHinh(ch, l2)) {
        if (ch.count("trung.so_ngay_doi_chieu")) soNgayDoiChieu_ = (int)toLL(ch["trung.so_ngay_doi_chieu"], 365);
        if (ch.count("trung.tao_ban_moi_khi_tep_khac")) taoBanMoiKhiTepKhac_ = toBool(ch["trung.tao_ban_moi_khi_tep_khac"], true);
    }
    return true;
}

// Kiểm tra cơ sở dữ liệu đã được cài đặt (đủ các bảng chính) hay chưa
bool KhoMySql::kiemTraDaCaiDat(std::string& loi) {
    static const char* BANG_CHINH[] = {
        "cau_hinh", "truong", "nguoi_xu_ly", "van_ban",
        "email", "tep_du_lieu", "tep_dinh_kem", "cong_viec", nullptr
    };

    MySqlKetQua kq;
    std::string l2;
    if (!db_.truyVan("SELECT TABLE_NAME FROM information_schema.TABLES "
                     "WHERE TABLE_SCHEMA = DATABASE()", kq, l2)) {
        // Không đọc được information_schema thì bỏ qua bước kiểm tra này
        return true;
    }

    std::vector<std::string> thieu;
    for (int i = 0; BANG_CHINH[i]; i++) {
        bool co = false;
        for (const auto& d : kq.dong) {
            if (toLower(d[0]) == BANG_CHINH[i]) { co = true; break; }
        }
        if (!co) thieu.push_back(BANG_CHINH[i]);
    }
    if (thieu.empty()) return kiemTraCotMoi(loi);

    if (thieu.size() == sizeof(BANG_CHINH) / sizeof(BANG_CHINH[0]) - 1) {
        loi = "Kết nối MySQL thành công nhưng cơ sở dữ liệu '" + ts_.co_so_du_lieu +
              "' đang TRỐNG (chưa có bảng nào).\n"
              "Hãy cài đặt phần web trước: mở https://<tên-miền>/cai-dat.php và làm theo 4 bước, "
              "hoặc dùng phpMyAdmin nạp lần lượt sql/01_schema.sql và sql/02_du_lieu_mau.sql. "
              "Cài xong quay lại đây bấm 'Lưu & kết nối'.";
    } else {
        loi = "Cơ sở dữ liệu '" + ts_.co_so_du_lieu + "' thiếu " + std::to_string(thieu.size()) +
              " bảng: " + join(thieu, ", ") + ".\n"
              "Hãy nạp lại tệp sql/01_schema.sql bằng phpMyAdmin để bổ sung các bảng còn thiếu.";
    }
    NK.loi("csdl", loi);
    return false;
}

// Bảng đủ nhưng cơ sở dữ liệu tạo từ phiên bản cũ có thể thiếu cột mới thêm.
// Ghi thẳng vào cột không tồn tại sẽ hỏng cả phiên đồng bộ nên chặn ngay từ đầu.
bool KhoMySql::kiemTraCotMoi(std::string& loi) {
    struct CotCan { const char* bang; const char* cot; const char* tu_ban; };
    static const CotCan CAN[] = {
        { "email", "tu_spam", "1.2.0" },
        { "email", "lien_ket_ngoai", "1.4.0" },
        { nullptr, nullptr, nullptr }
    };

    MySqlKetQua kq;
    std::string l2;
    if (!db_.truyVan("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS "
                     "WHERE TABLE_SCHEMA = DATABASE()", kq, l2)) {
        return true;   // không đọc được thì bỏ qua, để lỗi thật nổi lên lúc ghi
    }

    std::vector<std::string> thieu;
    for (int i = 0; CAN[i].bang; i++) {
        bool co = false;
        for (const auto& d : kq.dong) {
            if (d.o.size() >= 2 && toLower(d[0]) == CAN[i].bang && toLower(d[1]) == CAN[i].cot) {
                co = true; break;
            }
        }
        if (!co) thieu.push_back(std::string(CAN[i].bang) + "." + CAN[i].cot +
                                 " (thêm từ bản " + CAN[i].tu_ban + ")");
    }
    if (thieu.empty()) return true;

    loi = "Cơ sở dữ liệu được tạo từ phiên bản cũ, còn thiếu " + std::to_string(thieu.size()) +
          " cột: " + join(thieu, ", ") + ".\n"
          "Hãy mở https://<tên-miền>/nang-cap.php một lần để bổ sung, "
          "hoặc nạp tệp sql/03_nang_cap.sql bằng phpMyAdmin. "
          "Dữ liệu cũ được giữ nguyên, không mất gì.";
    NK.loi("csdl", loi);
    return false;
}

void KhoMySql::dong() { db_.dong(); }

bool KhoMySql::kiemTra(std::string& loi) { return db_.kiemTraSong(loi); }

bool KhoMySql::napDanhMuc(DanhMuc& dm, std::string& loi) {
    MySqlKetQua kq;
    dm = DanhMuc();

    if (!db_.truyVan("SELECT id, ma_truong, ma_chuan, ten_truong, IFNULL(ten_viet_tat,''), IFNULL(email,'') "
                     "FROM truong WHERE trang_thai = 1 ORDER BY thu_tu, ma_truong", kq, loi)) return false;
    for (const auto& d : kq.dong) {
        MucTruong t;
        t.id = toLL(d[0]);
        t.ma = d[1];
        t.ma_chuan = d[2].empty() ? chuanHoaMaSo(d[1]) : d[2];
        t.ten = d[3];
        t.ten_viet_tat = d[4];
        t.email = d[5];
        dm.truong.push_back(t);
    }

    if (!db_.truyVan("SELECT id, ma_nguoi_xu_ly, ho_ten, IFNULL(email,'') "
                     "FROM nguoi_xu_ly WHERE trang_thai = 1 ORDER BY ma_nguoi_xu_ly", kq, loi)) return false;
    for (const auto& d : kq.dong) {
        MucNguoiXuLy n;
        n.id = toLL(d[0]);
        n.ma = d[1];
        n.ma_chuan = chuanHoaMa(d[1]);
        n.ho_ten = d[2];
        n.email = d[3];
        dm.nguoi.push_back(n);
    }
    // Bí danh -> ánh xạ thêm vào cùng người xử lý
    if (db_.truyVan("SELECT b.bi_danh, n.id, n.ma_nguoi_xu_ly, n.ho_ten "
                    "FROM bi_danh_nguoi_xu_ly b JOIN nguoi_xu_ly n ON n.id = b.id_nguoi_xu_ly "
                    "WHERE n.trang_thai = 1", kq, loi)) {
        for (const auto& d : kq.dong) {
            MucNguoiXuLy n;
            n.id = toLL(d[1]);
            n.ma = d[2];
            n.ma_chuan = chuanHoaMa(d[0]);
            n.ho_ten = d[3];
            dm.nguoi.push_back(n);
        }
    }
    loi.clear();

    if (!db_.truyVan("SELECT id, ma_van_ban, ma_chuan, ten_van_ban, "
                     "IFNULL(id_nguoi_xu_ly_mac_dinh,0), tu_dong_tao "
                     "FROM van_ban WHERE trang_thai = 1 ORDER BY ma_van_ban", kq, loi)) return false;
    for (const auto& d : kq.dong) {
        MucVanBan v;
        v.id = toLL(d[0]);
        v.ma = d[1];
        v.ma_chuan = d[2].empty() ? chuanHoaMaSo(d[1]) : d[2];
        v.ten = d[3];
        v.id_nguoi_mac_dinh = toLL(d[4]);
        v.tu_dong_tao = toLL(d[5]) != 0;
        dm.van_ban.push_back(v);
    }

    dm.xayChiMuc();
    return true;
}

bool KhoMySql::napCauHinh(std::map<std::string, std::string>& ch, std::string& loi) {
    MySqlKetQua kq;
    if (!db_.truyVan("SELECT khoa, IFNULL(gia_tri,'') FROM cau_hinh", kq, loi)) return false;
    ch.clear();
    for (const auto& d : kq.dong) ch[d[0]] = d[1];
    return true;
}

bool KhoMySql::taoVanBanTuDong(const std::string& ma, MucVanBan& ra, std::string& loi) {
    std::string maChuan = chuanHoaMaSo(ma);
    if (maChuan.empty()) { loi = "Mã văn bản rỗng"; return false; }

    std::string sql =
        "INSERT INTO van_ban (ma_van_ban, ma_chuan, ten_van_ban, loai, ky_bao_cao, bat_buoc_nop, "
        "pham_vi, tu_dong_tao, trang_thai, mo_ta, lan_gap_dau, lan_gap_cuoi, so_lan_gap, ngay_tao, ngay_cap_nhat) "
        "VALUES (" + MySql::nhay(ma) + "," + MySql::nhay(maChuan) + "," +
        MySql::nhay("Văn bản/công việc mã " + ma) + ",'khac','khong',0,'tat_ca',1,1," +
        MySql::nhay("Tự động tạo khi tiếp nhận mail - cần cập nhật tên chính thức") +
        ",NOW(),NOW(),1,NOW(),NOW()) "
        "ON DUPLICATE KEY UPDATE lan_gap_cuoi = NOW(), so_lan_gap = so_lan_gap + 1";
    if (!db_.thucThi(sql, loi)) return false;

    MySqlKetQua kq;
    if (!db_.truyVan("SELECT id, ma_van_ban, ma_chuan, ten_van_ban, IFNULL(id_nguoi_xu_ly_mac_dinh,0), tu_dong_tao "
                     "FROM van_ban WHERE ma_van_ban = " + MySql::nhay(ma) + " LIMIT 1", kq, loi)) return false;
    if (kq.rong()) { loi = "Không tạo được mã văn bản " + ma; return false; }
    ra.id = toLL(kq.dong[0][0]);
    ra.ma = kq.dong[0][1];
    ra.ma_chuan = kq.dong[0][2];
    ra.ten = kq.dong[0][3];
    ra.id_nguoi_mac_dinh = toLL(kq.dong[0][4]);
    ra.tu_dong_tao = toLL(kq.dong[0][5]) != 0;
    return true;
}

bool KhoMySql::daLuu(const std::string& gmailId, long long& idEmail, std::string& loi) {
    MySqlKetQua kq;
    if (!db_.truyVan("SELECT id FROM email WHERE gmail_message_id = " + MySql::nhay(gmailId) + " LIMIT 1",
                     kq, loi)) return false;
    idEmail = kq.rong() ? 0 : toLL(kq.dong[0][0]);
    return true;
}

// ---------------------------------------------------------------------
//  Lưu tệp nhị phân theo khối (tránh vượt max_allowed_packet)
// ---------------------------------------------------------------------
bool KhoMySql::luuBlob(const TepDinhKem& t, long long& idTepDuLieu, bool& daCo, std::string& loi) {
    daCo = false;
    idTepDuLieu = 0;
    if (t.hash.empty()) { loi = "Tệp thiếu mã băm"; return false; }

    MySqlKetQua kq;
    if (!db_.truyVan("SELECT id, da_hoan_tat FROM tep_du_lieu WHERE hash_file = " +
                     MySql::nhay(t.hash) + " LIMIT 1", kq, loi)) return false;
    if (!kq.rong() && toLL(kq.dong[0][1]) == 1) {
        idTepDuLieu = toLL(kq.dong[0][0]);
        daCo = true;
        std::string l2;
        db_.thucThi("UPDATE tep_du_lieu SET so_tham_chieu = so_tham_chieu + 1 WHERE id = " +
                    std::to_string(idTepDuLieu), l2);
        return true;
    }
    if (!kq.rong()) {
        // Bản ghi dở dang từ lần chạy trước -> xoá và ghi lại
        std::string l2;
        db_.thucThi("DELETE FROM tep_du_lieu WHERE id = " + std::to_string(toLL(kq.dong[0][0])), l2);
    }

    if (!db_.thucThi("INSERT INTO tep_du_lieu (hash_file, dung_luong, kieu_mime, noi_dung, "
                     "da_hoan_tat, so_tham_chieu, ngay_tao) VALUES (" +
                     MySql::nhay(t.hash) + "," + std::to_string((long long)t.du_lieu.size()) + "," +
                     MySql::nhay(t.mime) + ",'',0,1,NOW())", loi)) return false;
    idTepDuLieu = db_.idChenCuoi();
    if (idTepDuLieu <= 0) { loi = "Không lấy được ID kho tệp"; return false; }

    // Ghi nội dung theo khối: mỗi khối chuyển thành literal X'..' (gấp đôi kích thước)
    long long gioiHan = db_.kichThuocGoiToiDa();
    long long khoi = khoiKb_ * 1024;
    long long toiDaTheoGoi = (gioiHan - 8192) / 2;
    if (toiDaTheoGoi < 16 * 1024) toiDaTheoGoi = 16 * 1024;
    if (khoi > toiDaTheoGoi) khoi = toiDaTheoGoi;

    size_t off = 0;
    while (off < t.du_lieu.size()) {
        size_t n = (size_t)std::min<long long>(khoi, (long long)(t.du_lieu.size() - off));
        std::string phan = t.du_lieu.substr(off, n);
        std::string sql = "UPDATE tep_du_lieu SET noi_dung = CONCAT(noi_dung, " +
                          MySql::hexBlob(phan) + ") WHERE id = " + std::to_string(idTepDuLieu);
        if (!db_.thucThi(sql, loi)) {
            std::string l2;
            db_.thucThi("DELETE FROM tep_du_lieu WHERE id = " + std::to_string(idTepDuLieu), l2);
            return false;
        }
        off += n;
    }

    if (!db_.thucThi("UPDATE tep_du_lieu SET da_hoan_tat = 1, dung_luong = " +
                     std::to_string((long long)t.du_lieu.size()) +
                     " WHERE id = " + std::to_string(idTepDuLieu), loi)) return false;
    return true;
}

// ---------------------------------------------------------------------
//  Phát hiện trùng
// ---------------------------------------------------------------------
bool KhoMySql::timTrung(const BanGhiEmail& em, KetQuaLuu& kq, std::string& loi) {
    kq.trang = KetQuaLuu::MOI;
    kq.id_email_goc = 0;
    kq.phien_ban = 1;
    if (em.hash_noi_dung.empty()) return true;

    MySqlKetQua r;
    std::string sql =
        "SELECT id, IFNULL(hash_tong_hop,''), phien_ban, IFNULL(id_email_goc,0) FROM email "
        "WHERE hash_noi_dung = " + MySql::nhay(em.hash_noi_dung) +
        " AND ngay_gui >= DATE_SUB(NOW(), INTERVAL " + std::to_string(soNgayDoiChieu_) + " DAY) "
        "ORDER BY id ASC LIMIT 200";
    if (!db_.truyVan(sql, r, loi)) return false;
    if (r.rong()) return true;

    long long idGoc = 0;
    int phienMax = 1;
    for (const auto& d : r.dong) {
        long long id = toLL(d[0]);
        long long g = toLL(d[3]);
        if (idGoc == 0) idGoc = (g > 0) ? g : id;
        phienMax = std::max(phienMax, (int)toLL(d[2], 1));
        if (d[1] == em.hash_tong_hop) {
            kq.trang = KetQuaLuu::TRUNG_HOAN_TOAN;
            kq.id_email_goc = (g > 0) ? g : id;
            kq.phien_ban = (int)toLL(d[2], 1);
            kq.thong_diep = "Trùng hoàn toàn với email #" + std::to_string(id) +
                            " (cùng nội dung và cùng tệp đính kèm)";
            return true;
        }
    }

    kq.trang = KetQuaLuu::BAN_MOI;
    kq.id_email_goc = idGoc;
    kq.phien_ban = phienMax + 1;
    kq.thong_diep = "Trùng nội dung với email #" + std::to_string(idGoc) +
                    " nhưng tệp đính kèm khác (dung lượng/nội dung) - ghi nhận là phiên bản " +
                    std::to_string(kq.phien_ban);
    return true;
}

// ---------------------------------------------------------------------
//  Lưu email
// ---------------------------------------------------------------------
bool KhoMySql::luuEmail(const BanGhiEmail& em, const std::vector<NhomCongViec>& nhom,
                        KetQuaLuu& kq, std::string& loi) {
    kq = KetQuaLuu();

    long long idCu = 0;
    if (!daLuu(em.gmail_id, idCu, loi)) return false;
    if (idCu > 0) {
        kq.trang = KetQuaLuu::DA_TON_TAI;
        kq.id_email = idCu;
        kq.thong_diep = "Email đã có trong CSDL (#" + std::to_string(idCu) + ")";
        return true;
    }

    if (!timTrung(em, kq, loi)) return false;
    bool boQuaCongViec = (kq.trang == KetQuaLuu::TRUNG_HOAN_TOAN);

    // 1) Lưu nội dung tệp trước (ngoài giao dịch, kho theo mã băm nên an toàn khi lặp lại)
    std::vector<long long> idTepDuLieu(em.tep.size(), 0);
    for (size_t i = 0; i < em.tep.size(); i++) {
        if (em.tep[i].bo_qua || !em.tep[i].da_tai) continue;
        bool daCo = false;
        long long id = 0;
        if (!luuBlob(em.tep[i], id, daCo, loi)) {
            loi = "Lỗi lưu tệp '" + em.tep[i].ten + "': " + loi;
            return false;
        }
        idTepDuLieu[i] = id;
        if (!daCo) kq.so_byte_moi += (long long)em.tep[i].du_lieu.size();
    }

    // 2) Ghi siêu dữ liệu trong một giao dịch
    std::string l2;
    if (!db_.thucThi("START TRANSACTION", loi)) return false;
    auto huyBo = [&](const std::string& m) {
        std::string x;
        db_.thucThi("ROLLBACK", x);
        loi = m;
        return false;
    };

    std::string trangThaiEmail = "da_phan_luong";
    if (kq.trang == KetQuaLuu::TRUNG_HOAN_TOAN) trangThaiEmail = "trung_lap";
    else if (kq.trang == KetQuaLuu::BAN_MOI)    trangThaiEmail = "ban_moi";
    else {
        bool coCho = false;
        for (const auto& n : nhom) if (!n.du_thong_tin) coCho = true;
        if (coCho && nhom.size() == 1) trangThaiEmail = "cho_phan_luong";
    }

    std::string sql =
        "INSERT INTO email (gmail_message_id, gmail_thread_id, message_id_header, hop_thu, tieu_de, "
        "tieu_de_chuan, nguoi_gui, ten_nguoi_gui, nguoi_nhan, ngay_gui, ngay_nhan, doan_trich, "
        "noi_dung_text, noi_dung_html, so_tep, tong_dung_luong, hash_noi_dung, hash_tep, hash_tong_hop, "
        "trang_thai, id_email_goc, phien_ban, ly_do_trung, nguon_phan_luong, do_tin_cay, ghi_chu_ai, "
        "tu_spam, lien_ket_ngoai, ngay_tao, ngay_cap_nhat) VALUES (" +
        MySql::nhay(em.gmail_id) + "," +
        MySql::nhay(em.thread_id) + "," +
        MySql::nhay(catUtf8(em.message_id_header, 190)) + "," +
        MySql::nhay(catUtf8(em.hop_thu, 190)) + "," +
        MySql::nhay(catUtf8(em.tieu_de, 990)) + "," +
        MySql::nhay(catUtf8(em.tieu_de_chuan, 490)) + "," +
        MySql::nhay(catUtf8(em.nguoi_gui, 310)) + "," +
        MySql::nhay(catUtf8(em.ten_nguoi_gui, 250)) + "," +
        MySql::nhay(catUtf8(em.nguoi_nhan, 2000)) + "," +
        sqlNgay(em.ngay_gui) + ",NOW()," +
        MySql::nhay(catUtf8(em.doan_trich, 990)) + "," +
        MySql::nhay(catUtf8(em.noi_dung_text, 4000000)) + "," +
        MySql::nhay(catUtf8(em.noi_dung_html, 4000000)) + "," +
        std::to_string((int)em.tep.size()) + "," +
        std::to_string(em.tongDungLuong()) + "," +
        MySql::nhay(em.hash_noi_dung) + "," +
        MySql::nhay(em.hash_tep) + "," +
        MySql::nhay(em.hash_tong_hop) + "," +
        MySql::nhay(trangThaiEmail) + "," +
        (kq.id_email_goc > 0 ? std::to_string(kq.id_email_goc) : std::string("NULL")) + "," +
        std::to_string(kq.phien_ban) + "," +
        MySql::nhay(catUtf8(kq.thong_diep, 490)) + "," +
        MySql::nhay(em.nguon_phan_luong) + "," +
        std::to_string(em.do_tin_cay) + "," +
        MySql::nhay(catUtf8(em.ghi_chu_ai, 4000)) + "," +
        (em.tuSpam() ? "1" : "0") + "," +
        MySql::nhay(catUtf8(join(em.lien_ket_ngoai, "\n"), 8000)) + ",NOW(),NOW())";
    if (!db_.thucThi(sql, l2)) return huyBo("Lỗi ghi email: " + l2);
    kq.id_email = db_.idChenCuoi();

    // Tệp đính kèm
    std::vector<long long> idTepDinhKem(em.tep.size(), 0);
    for (size_t i = 0; i < em.tep.size(); i++) {
        const TepDinhKem& t = em.tep[i];
        std::string s =
            "INSERT INTO tep_dinh_kem (id_email, id_tep_du_lieu, ten_tep, ten_tep_chuan, phan_mo_rong, "
            "kieu_mime, dung_luong, hash_file, ma_truong, ma_van_ban, ma_nguoi_xu_ly, doc_duoc_ma, "
            "thu_tu, ngay_tao) VALUES (" +
            std::to_string(kq.id_email) + "," +
            (idTepDuLieu[i] > 0 ? std::to_string(idTepDuLieu[i]) : std::string("NULL")) + "," +
            MySql::nhay(catUtf8(t.ten, 490)) + "," +
            MySql::nhay(catUtf8(boDauTiengViet(t.ten), 490)) + "," +
            MySql::nhay(phanMoRong(t.ten)) + "," +
            MySql::nhay(t.mime) + "," +
            std::to_string(t.dung_luong) + "," +
            MySql::nhay(t.hash) + "," +
            MySql::nhay(t.ma.truong) + "," +
            MySql::nhay(t.ma.van_ban) + "," +
            MySql::nhay(t.ma.nguoi) + "," +
            (t.doc_duoc_ma ? "1" : "0") + "," +
            std::to_string(t.thu_tu) + ",NOW())";
        if (!db_.thucThi(s, l2)) return huyBo("Lỗi ghi tệp đính kèm: " + l2);
        idTepDinhKem[i] = db_.idChenCuoi();
        kq.so_tep++;
    }

    // Công việc
    if (!boQuaCongViec) {
        for (const auto& n : nhom) {
            std::string maHoSo = n.ma.chuoi();
            std::string trangThai = n.du_thong_tin ? "cho_xu_ly" : "cho_phan_luong";
            long long dungLuong = 0;
            for (int ci : n.chi_so_tep) if (ci >= 0 && ci < (int)em.tep.size()) dungLuong += em.tep[ci].dung_luong;

            // Nếu là bản mới: hạ cờ bản mới nhất của các công việc cùng mã hồ sơ
            if (kq.trang == KetQuaLuu::BAN_MOI && n.du_thong_tin) {
                std::string s = "UPDATE cong_viec SET la_ban_moi_nhat = 0 WHERE ma_ho_so = " +
                                MySql::nhay(maHoSo) + " AND la_ban_moi_nhat = 1";
                db_.thucThi(s, l2);
            }

            std::string s =
                "INSERT INTO cong_viec (id_email, ma_ho_so, id_truong, id_van_ban, id_nguoi_xu_ly, "
                "ma_truong, ma_van_ban, ma_nguoi_xu_ly, tieu_de, trich_yeu, nguon_phan_luong, do_tin_cay, "
                "trang_thai, muc_do, ngay_nhan, id_cong_viec_goc, phien_ban, la_ban_moi_nhat, so_tep, "
                "tong_dung_luong, ghi_chu, ngay_tao, ngay_cap_nhat) VALUES (" +
                std::to_string(kq.id_email) + "," +
                MySql::nhay(maHoSo) + "," +
                (n.id_truong > 0 ? std::to_string(n.id_truong) : std::string("NULL")) + "," +
                (n.id_van_ban > 0 ? std::to_string(n.id_van_ban) : std::string("NULL")) + "," +
                (n.id_nguoi > 0 ? std::to_string(n.id_nguoi) : std::string("NULL")) + "," +
                MySql::nhay(n.ma.truong) + "," +
                MySql::nhay(n.ma.van_ban) + "," +
                MySql::nhay(n.ma.nguoi) + "," +
                MySql::nhay(catUtf8(em.tieu_de, 990)) + "," +
                MySql::nhay(catUtf8(em.doan_trich, 2000)) + "," +
                MySql::nhay(n.nguon) + "," +
                std::to_string(n.do_tin_cay) + "," +
                MySql::nhay(trangThai) + ",'thuong'," +
                sqlNgay(em.ngay_gui > 0 ? em.ngay_gui : nowEpoch()) + ",NULL," +
                std::to_string(kq.phien_ban) + ",1," +
                std::to_string((int)n.chi_so_tep.size()) + "," +
                std::to_string(dungLuong) + "," +
                MySql::nhay(catUtf8(n.ghi_chu, 2000)) + ",NOW(),NOW())";
            if (!db_.thucThi(s, l2)) return huyBo("Lỗi ghi công việc: " + l2);
            long long idCv = db_.idChenCuoi();
            kq.so_cong_viec++;
            if (!n.du_thong_tin) kq.so_cho_phan_luong++;

            for (int ci : n.chi_so_tep) {
                if (ci < 0 || ci >= (int)idTepDinhKem.size() || idTepDinhKem[ci] <= 0) continue;
                std::string s2 = "INSERT IGNORE INTO cong_viec_tep (id_cong_viec, id_tep_dinh_kem) VALUES (" +
                                 std::to_string(idCv) + "," + std::to_string(idTepDinhKem[ci]) + ")";
                if (!db_.thucThi(s2, l2)) return huyBo("Lỗi liên kết tệp: " + l2);
            }
        }
    }

    if (!db_.thucThi("COMMIT", l2)) return huyBo("Lỗi hoàn tất giao dịch: " + l2);
    return true;
}

bool KhoMySql::ghiNhatKy(const DongNhatKy& d) {
    if (!db_.dangKetNoi()) return false;
    std::string loi;
    std::string sql =
        "INSERT INTO nhat_ky (thoi_gian, muc, nguon, hanh_dong, doi_tuong, id_doi_tuong, "
        "may_chu, noi_dung) VALUES (" +
        MySql::nhay(d.thoi_gian) + "," +
        MySql::nhay(NhatKy::tenMuc(d.muc)) + ",'mailrouter'," +
        MySql::nhay(catUtf8(d.hanh_dong, 90)) + "," +
        MySql::nhay(catUtf8(d.doi_tuong, 45)) + "," +
        MySql::nhay(catUtf8(d.id_doi_tuong, 60)) + "," +
        MySql::nhay(catUtf8(mayChu_, 90)) + "," +
        MySql::nhay(catUtf8(d.noi_dung, 60000)) + ")";
    return db_.thucThi(sql, loi);
}

bool KhoMySql::moPhien(ThongKePhien& tk, const std::string& hopThu,
                       const std::string& truyVan, std::string& loi) {
    tk.bat_dau = nowEpoch();
    std::string sql =
        "INSERT INTO phien_dong_bo (bat_dau, may_chu, hop_thu, truy_van, trang_thai) VALUES (NOW()," +
        MySql::nhay(catUtf8(mayChu_, 90)) + "," +
        MySql::nhay(catUtf8(hopThu, 190)) + "," +
        MySql::nhay(catUtf8(truyVan, 490)) + ",'dang_chay')";
    if (!db_.thucThi(sql, loi)) return false;
    tk.id = db_.idChenCuoi();
    return true;
}

bool KhoMySql::dongPhien(const ThongKePhien& tk, const std::string& trangThai, std::string& loi) {
    if (tk.id <= 0) return true;
    std::string sql =
        "UPDATE phien_dong_bo SET ket_thuc = NOW(), so_mail_quet = " + std::to_string(tk.so_mail_quet) +
        ", so_mail_moi = " + std::to_string(tk.so_mail_moi) +
        ", so_mail_trung = " + std::to_string(tk.so_mail_trung) +
        ", so_mail_ban_moi = " + std::to_string(tk.so_mail_ban_moi) +
        ", so_cong_viec = " + std::to_string(tk.so_cong_viec) +
        ", so_tep = " + std::to_string(tk.so_tep) +
        ", so_dung_ai = " + std::to_string(tk.so_dung_ai) +
        ", so_cho_phan_luong = " + std::to_string(tk.so_cho_phan_luong) +
        ", so_loi = " + std::to_string(tk.so_loi) +
        ", trang_thai = " + MySql::nhay(trangThai) +
        ", thong_diep = " + MySql::nhay(catUtf8(tk.thong_diep, 60000)) +
        " WHERE id = " + std::to_string(tk.id);
    return db_.thucThi(sql, loi);
}

// =====================================================================
//  KhoApi
// =====================================================================
KhoApi::KhoApi(const CauHinh& ch) {
    url_ = ch.chuoi("api.url");
    khoa_ = ch.chuoi("api.khoa");
    timeout_ = (int)ch.nguyen("api.timeout", 120);
    khoiKb_ = ch.nguyen("api.kich_thuoc_khoi_kb", 512);
    if (khoiKb_ < 32) khoiKb_ = 32;
    if (khoiKb_ > 8192) khoiKb_ = 8192;
    mayChu_ = tenMayChu();
}

bool KhoApi::goi(const std::string& hanhDong, const Json& thamSo, Json& ketQua, std::string& loi) {
    if (url_.empty()) { loi = "Chưa khai báo địa chỉ API"; return false; }
    Json than = thamSo.laDoiTuong() ? thamSo : Json::doiTuong();
    than.dat("hanh_dong", hanhDong);
    than.dat("may_chu", mayChu_);

    std::vector<std::string> hd;
    hd.push_back("X-API-Key: " + khoa_);
    hd.push_back("Accept: application/json");

    HttpPhanHoi pr = HttpClient::postJson(url_, than.ketXuat(), hd, timeout_);
    if (!pr.loi.empty()) { loi = pr.loi; return false; }
    if (pr.than.empty()) {
        loi = "API không trả về dữ liệu (HTTP " + std::to_string(pr.ma) + ")";
        return false;
    }
    std::string l2;
    if (!Json::phanTich(pr.than, ketQua, &l2)) {
        loi = "API trả về dữ liệu không phải JSON: " + catUtf8(pr.than, 300);
        return false;
    }
    if (!ketQua.lay("ok").logic(false)) {
        loi = ketQua.lay("loi").chuoi("API báo lỗi không rõ nguyên nhân");
        return false;
    }
    return true;
}

bool KhoApi::ketNoi(std::string& loi) {
    if (url_.empty()) { loi = "Chưa khai báo api.url"; return false; }
    if (khoa_.empty()) { loi = "Chưa khai báo api.khoa"; return false; }
    Json kq;
    if (!goi("kiem_tra", Json::doiTuong(), kq, loi)) return false;
    NK.tin("csdl", "Đã kết nối API: " + url_ + " (" + kq.lay("phien_ban").chuoi("?") + ")");
    return true;
}

bool KhoApi::kiemTra(std::string& loi) {
    Json kq;
    return goi("kiem_tra", Json::doiTuong(), kq, loi);
}

bool KhoApi::napDanhMuc(DanhMuc& dm, std::string& loi) {
    Json kq;
    if (!goi("danh_muc", Json::doiTuong(), kq, loi)) return false;
    dm = DanhMuc();
    const Json& t = kq.lay("truong");
    for (size_t i = 0; i < t.soPhanTu(); i++) {
        MucTruong m;
        m.id = t[i].lay("id").nguyen();
        m.ma = t[i].lay("ma").chuoi();
        m.ma_chuan = t[i].lay("ma_chuan").chuoi(chuanHoaMaSo(m.ma));
        m.ten = t[i].lay("ten").chuoi();
        m.ten_viet_tat = t[i].lay("ten_viet_tat").chuoi();
        m.email = t[i].lay("email").chuoi();
        dm.truong.push_back(m);
    }
    const Json& n = kq.lay("nguoi_xu_ly");
    for (size_t i = 0; i < n.soPhanTu(); i++) {
        MucNguoiXuLy m;
        m.id = n[i].lay("id").nguyen();
        m.ma = n[i].lay("ma").chuoi();
        m.ma_chuan = n[i].lay("ma_chuan").chuoi(chuanHoaMa(m.ma));
        m.ho_ten = n[i].lay("ho_ten").chuoi();
        m.email = n[i].lay("email").chuoi();
        dm.nguoi.push_back(m);
    }
    const Json& v = kq.lay("van_ban");
    for (size_t i = 0; i < v.soPhanTu(); i++) {
        MucVanBan m;
        m.id = v[i].lay("id").nguyen();
        m.ma = v[i].lay("ma").chuoi();
        m.ma_chuan = v[i].lay("ma_chuan").chuoi(chuanHoaMaSo(m.ma));
        m.ten = v[i].lay("ten").chuoi();
        m.id_nguoi_mac_dinh = v[i].lay("id_nguoi_mac_dinh").nguyen();
        m.tu_dong_tao = v[i].lay("tu_dong_tao").logic();
        dm.van_ban.push_back(m);
    }
    dm.xayChiMuc();
    return true;
}

bool KhoApi::napCauHinh(std::map<std::string, std::string>& ch, std::string& loi) {
    Json kq;
    if (!goi("cau_hinh", Json::doiTuong(), kq, loi)) return false;
    ch.clear();
    const Json& c = kq.lay("cau_hinh");
    for (const auto& k : c.danhSachKhoa()) ch[k] = c.lay(k).chuoi();
    return true;
}

bool KhoApi::taoVanBanTuDong(const std::string& ma, MucVanBan& ra, std::string& loi) {
    Json ts = Json::doiTuong();
    ts.dat("ma_van_ban", ma);
    Json kq;
    if (!goi("them_van_ban", ts, kq, loi)) return false;
    const Json& v = kq.lay("van_ban");
    ra.id = v.lay("id").nguyen();
    ra.ma = v.lay("ma").chuoi(ma);
    ra.ma_chuan = v.lay("ma_chuan").chuoi(chuanHoaMaSo(ma));
    ra.ten = v.lay("ten").chuoi();
    ra.id_nguoi_mac_dinh = v.lay("id_nguoi_mac_dinh").nguyen();
    ra.tu_dong_tao = true;
    return true;
}

bool KhoApi::daLuu(const std::string& gmailId, long long& idEmail, std::string& loi) {
    Json ts = Json::doiTuong();
    ts.dat("gmail_id", gmailId);
    Json kq;
    if (!goi("kiem_tra_mail", ts, kq, loi)) return false;
    idEmail = kq.lay("id_email").nguyen(0);
    return true;
}

bool KhoApi::taiTep(const TepDinhKem& t, std::string& loi) {
    Json ts = Json::doiTuong();
    ts.dat("hash", t.hash);
    ts.dat("dung_luong", (long long)t.du_lieu.size());
    ts.dat("kieu_mime", t.mime);
    ts.dat("ten_tep", t.ten);
    Json kq;
    if (!goi("tep_bat_dau", ts, kq, loi)) return false;
    if (!kq.lay("can_tai").logic(true)) return true;      // máy chủ đã có tệp này

    std::string idTai = kq.lay("id_tai").chuoi();
    if (idTai.empty()) { loi = "API không trả về mã phiên tải tệp"; return false; }

    size_t khoi = (size_t)(khoiKb_ * 1024);
    size_t off = 0;
    int seq = 0;
    while (off < t.du_lieu.size()) {
        size_t n = std::min(khoi, t.du_lieu.size() - off);
        Json c = Json::doiTuong();
        c.dat("id_tai", idTai);
        c.dat("seq", seq++);
        c.dat("du_lieu", base64Encode(t.du_lieu.substr(off, n)));
        Json r;
        if (!goi("tep_khoi", c, r, loi)) return false;
        off += n;
    }

    Json c = Json::doiTuong();
    c.dat("id_tai", idTai);
    Json r;
    if (!goi("tep_ket_thuc", c, r, loi)) return false;
    return true;
}

static Json maSangJson(const MaHoSo& m) {
    Json j = Json::doiTuong();
    j.dat("truong", m.truong);
    j.dat("van_ban", m.van_ban);
    j.dat("nguoi", m.nguoi);
    return j;
}

bool KhoApi::luuEmail(const BanGhiEmail& em, const std::vector<NhomCongViec>& nhom,
                      KetQuaLuu& kq, std::string& loi) {
    kq = KetQuaLuu();

    long long idCu = 0;
    if (!daLuu(em.gmail_id, idCu, loi)) return false;
    if (idCu > 0) {
        kq.trang = KetQuaLuu::DA_TON_TAI;
        kq.id_email = idCu;
        kq.thong_diep = "Email đã có trên máy chủ (#" + std::to_string(idCu) + ")";
        return true;
    }

    // Tải nội dung tệp lên trước
    for (const auto& t : em.tep) {
        if (t.bo_qua || !t.da_tai) continue;
        if (!taiTep(t, loi)) { loi = "Lỗi tải tệp '" + t.ten + "': " + loi; return false; }
    }

    Json j = Json::doiTuong();
    j.dat("gmail_id", em.gmail_id);
    j.dat("thread_id", em.thread_id);
    j.dat("message_id_header", em.message_id_header);
    j.dat("hop_thu", em.hop_thu);
    j.dat("tieu_de", em.tieu_de);
    j.dat("tieu_de_chuan", em.tieu_de_chuan);
    j.dat("nguoi_gui", em.nguoi_gui);
    j.dat("ten_nguoi_gui", em.ten_nguoi_gui);
    j.dat("nguoi_nhan", em.nguoi_nhan);
    j.dat("ngay_gui", ngayVN(em.ngay_gui));
    j.dat("doan_trich", em.doan_trich);
    j.dat("noi_dung_text", catUtf8(em.noi_dung_text, 2000000));
    j.dat("noi_dung_html", catUtf8(em.noi_dung_html, 2000000));
    j.dat("hash_noi_dung", em.hash_noi_dung);
    j.dat("hash_tep", em.hash_tep);
    j.dat("hash_tong_hop", em.hash_tong_hop);
    j.dat("nguon_phan_luong", em.nguon_phan_luong);
    j.dat("do_tin_cay", em.do_tin_cay);
    j.dat("ghi_chu_ai", em.ghi_chu_ai);
    j.dat("tu_spam", em.tuSpam() ? 1LL : 0LL);

    Json lk = Json::mang();
    for (const auto& u : em.lien_ket_ngoai) lk.them(Json(u));
    j.dat("lien_ket_ngoai", lk);

    Json ts = Json::mang();
    for (const auto& t : em.tep) {
        Json x = Json::doiTuong();
        x.dat("ten_tep", t.ten);
        x.dat("kieu_mime", t.mime);
        x.dat("dung_luong", t.dung_luong);
        x.dat("hash", t.hash);
        x.dat("doc_duoc_ma", t.doc_duoc_ma);
        x.dat("bo_qua", t.bo_qua);
        x.dat("ly_do_bo_qua", t.ly_do_bo_qua);
        x.dat("thu_tu", t.thu_tu);
        x.dat("ma", maSangJson(t.ma));
        ts.them(x);
    }
    j.dat("tep", ts);

    Json ns = Json::mang();
    for (const auto& n : nhom) {
        Json x = Json::doiTuong();
        x.dat("ma", maSangJson(n.ma));
        x.dat("nguon", n.nguon);
        x.dat("do_tin_cay", n.do_tin_cay);
        x.dat("du_thong_tin", n.du_thong_tin);
        x.dat("id_truong", n.id_truong);
        x.dat("id_van_ban", n.id_van_ban);
        x.dat("id_nguoi", n.id_nguoi);
        x.dat("ghi_chu", n.ghi_chu);
        Json cs = Json::mang();
        for (int c : n.chi_so_tep) cs.them(Json((long long)c));
        x.dat("chi_so_tep", cs);
        ns.them(x);
    }
    j.dat("cong_viec", ns);

    Json r;
    if (!goi("luu_email", j, r, loi)) return false;

    std::string tr = r.lay("trang").chuoi("moi");
    if (tr == "da_ton_tai")      kq.trang = KetQuaLuu::DA_TON_TAI;
    else if (tr == "trung_lap")  kq.trang = KetQuaLuu::TRUNG_HOAN_TOAN;
    else if (tr == "ban_moi")    kq.trang = KetQuaLuu::BAN_MOI;
    else                         kq.trang = KetQuaLuu::MOI;
    kq.id_email = r.lay("id_email").nguyen();
    kq.id_email_goc = r.lay("id_email_goc").nguyen();
    kq.phien_ban = (int)r.lay("phien_ban").nguyen(1);
    kq.so_cong_viec = (int)r.lay("so_cong_viec").nguyen();
    kq.so_tep = (int)r.lay("so_tep").nguyen();
    kq.so_cho_phan_luong = (int)r.lay("so_cho_phan_luong").nguyen();
    kq.so_byte_moi = r.lay("so_byte_moi").nguyen();
    kq.thong_diep = r.lay("thong_diep").chuoi();
    return true;
}

bool KhoApi::ghiNhatKy(const DongNhatKy& d) {
    Json j = Json::doiTuong();
    j.dat("thoi_gian", d.thoi_gian);
    j.dat("muc", NhatKy::tenMuc(d.muc));
    j.dat("hanh_dong", d.hanh_dong);
    j.dat("doi_tuong", d.doi_tuong);
    j.dat("id_doi_tuong", d.id_doi_tuong);
    j.dat("noi_dung", catUtf8(d.noi_dung, 60000));
    Json r;
    std::string loi;
    return goi("nhat_ky", j, r, loi);
}

bool KhoApi::moPhien(ThongKePhien& tk, const std::string& hopThu,
                     const std::string& truyVan, std::string& loi) {
    tk.bat_dau = nowEpoch();
    Json j = Json::doiTuong();
    j.dat("hop_thu", hopThu);
    j.dat("truy_van", truyVan);
    Json r;
    if (!goi("phien_mo", j, r, loi)) return false;
    tk.id = r.lay("id").nguyen();
    return true;
}

bool KhoApi::dongPhien(const ThongKePhien& tk, const std::string& trangThai, std::string& loi) {
    if (tk.id <= 0) return true;
    Json j = Json::doiTuong();
    j.dat("id", tk.id);
    j.dat("trang_thai", trangThai);
    j.dat("so_mail_quet", tk.so_mail_quet);
    j.dat("so_mail_moi", tk.so_mail_moi);
    j.dat("so_mail_trung", tk.so_mail_trung);
    j.dat("so_mail_ban_moi", tk.so_mail_ban_moi);
    j.dat("so_cong_viec", tk.so_cong_viec);
    j.dat("so_tep", tk.so_tep);
    j.dat("so_dung_ai", tk.so_dung_ai);
    j.dat("so_cho_phan_luong", tk.so_cho_phan_luong);
    j.dat("so_loi", tk.so_loi);
    j.dat("thong_diep", catUtf8(tk.thong_diep, 60000));
    Json r;
    return goi("phien_dong", j, r, loi);
}

// =====================================================================
//  Nhà máy
// =====================================================================
std::unique_ptr<KhoLuuTru> KhoLuuTru::tao(const CauHinh& ch, std::string& loi) {
    std::string cheDo = toLower(ch.chuoi("luu_tru.che_do", "mysql"));
    if (cheDo == "api") return std::unique_ptr<KhoLuuTru>(new KhoApi(ch));
    if (cheDo == "mysql") return std::unique_ptr<KhoLuuTru>(new KhoMySql(ch));
    loi = "Chế độ lưu trữ không hợp lệ: '" + cheDo + "' (chỉ nhận 'mysql' hoặc 'api')";
    return nullptr;
}

} // namespace mr
