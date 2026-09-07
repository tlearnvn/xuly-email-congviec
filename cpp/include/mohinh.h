// =====================================================================
//  mohinh.h - Các cấu trúc dữ liệu nghiệp vụ
// =====================================================================
#pragma once

#include <string>
#include <vector>
#include <map>
#include <cstdint>

namespace mr {

// ------------------------- Danh mục ----------------------------------
struct MucTruong {
    long long   id = 0;
    std::string ma;          // 001
    std::string ma_chuan;    // 1
    std::string ten;
    std::string ten_viet_tat;
    std::string email;
};

struct MucNguoiXuLy {
    long long   id = 0;
    std::string ma;          // TAT
    std::string ma_chuan;    // TAT
    std::string ho_ten;
    std::string email;
};

struct MucVanBan {
    long long   id = 0;
    std::string ma;
    std::string ma_chuan;
    std::string ten;
    long long   id_nguoi_mac_dinh = 0;
    bool        tu_dong_tao = false;
};

class DanhMuc {
public:
    std::vector<MucTruong>    truong;
    std::vector<MucNguoiXuLy> nguoi;
    std::vector<MucVanBan>    van_ban;

    void xayChiMuc();
    const MucTruong*    timTruong(const std::string& ma) const;
    const MucNguoiXuLy* timNguoi(const std::string& ma) const;
    const MucVanBan*    timVanBan(const std::string& ma) const;
    const MucNguoiXuLy* nguoiTheoId(long long id) const;
    void themVanBan(const MucVanBan& v);
    bool rong() const { return truong.empty() && nguoi.empty(); }
    std::string tomTat() const;

private:
    std::map<std::string, size_t> ciTruong_, ciNguoi_, ciVanBan_;
};

// ------------------------- Mã hồ sơ ----------------------------------
struct MaHoSo {
    std::string truong;       // mã trường như đọc được
    std::string van_ban;
    std::string nguoi;

    bool coDu() const { return !truong.empty() && !van_ban.empty() && !nguoi.empty(); }
    bool coGiTruoc() const { return !truong.empty() || !van_ban.empty() || !nguoi.empty(); }
    std::string chuoi() const {
        return (truong.empty() ? "?" : truong) + "_" +
               (van_ban.empty() ? "?" : van_ban) + "_" +
               (nguoi.empty() ? "?" : nguoi);
    }
    bool bang(const MaHoSo& k) const {
        return truong == k.truong && van_ban == k.van_ban && nguoi == k.nguoi;
    }
};

// ------------------------- Tệp đính kèm -------------------------------
struct TepDinhKem {
    std::string ten;               // tên tệp gốc (đã giải mã RFC2047)
    std::string mime;
    long long   dung_luong = 0;
    std::string hash;              // SHA-256 hex của nội dung
    std::string du_lieu;           // nội dung nhị phân (có thể rỗng nếu chưa tải)
    std::string gmail_attachment_id;
    std::string part_id;
    MaHoSo      ma;
    double      diem_ma = 0;
    bool        doc_duoc_ma = false;
    bool        da_tai = false;
    bool        bo_qua = false;    // quá lớn hoặc lỗi
    std::string ly_do_bo_qua;
    int         thu_tu = 0;
};

// ------------------------- Bản ghi email ------------------------------
struct BanGhiEmail {
    std::string gmail_id;
    std::string thread_id;
    std::string message_id_header;
    std::string hop_thu;
    std::string tieu_de;
    std::string tieu_de_chuan;
    std::string nguoi_gui;
    std::string ten_nguoi_gui;
    std::string nguoi_nhan;
    int64_t     ngay_gui = 0;      // epoch UTC
    int64_t     ngay_nhan = 0;
    std::string doan_trich;
    std::string noi_dung_text;
    std::string noi_dung_html;
    std::vector<TepDinhKem> tep;
    std::vector<std::string> nhan_gmail;         // labelIds: INBOX, SPAM, TRASH…
    std::vector<std::string> lien_ket_ngoai;     // link Drive/OneDrive… trong thân thư
    // Tên tệp Gmail hiện trong "Drive chip" (khi tệp > 25 MB nó tự đưa lên Drive).
    // Cùng chỉ số với lien_ket_ngoai; rỗng nếu link chỉ được dán tay.
    std::vector<std::string> ten_tep_ngoai;

    bool coNhan(const char* n) const {
        for (const auto& x : nhan_gmail) if (x == n) return true;
        return false;
    }
    bool tuSpam() const    { return coNhan("SPAM"); }
    bool trongThung() const { return coNhan("TRASH"); }

    std::string hash_noi_dung;
    std::string hash_tep;
    std::string hash_tong_hop;

    MaHoSo      ma_chung;                       // suy ra từ tiêu đề / AI / người gửi
    std::string nguon_phan_luong = "khong_xac_dinh";
    double      do_tin_cay = 0;
    std::string ghi_chu_ai;

    long long   tongDungLuong() const {
        long long t = 0;
        for (const auto& f : tep) t += f.dung_luong;
        return t;
    }
};

// ------------------- Nhóm công việc sẽ tạo ---------------------------
struct NhomCongViec {
    MaHoSo      ma;
    std::string nguon = "khong_xac_dinh";
    double      do_tin_cay = 0;
    bool        du_thong_tin = false;           // đủ 3 mã và khớp danh mục
    long long   id_truong = 0;
    long long   id_van_ban = 0;
    long long   id_nguoi = 0;
    std::vector<int> chi_so_tep;                // chỉ số trong BanGhiEmail::tep
    std::string ghi_chu;
};

// ------------------------- Kết quả lưu --------------------------------
struct KetQuaLuu {
    enum Trang { MOI, DA_TON_TAI, TRUNG_HOAN_TOAN, BAN_MOI, LOI };
    Trang       trang = MOI;
    long long   id_email = 0;
    long long   id_email_goc = 0;
    int         phien_ban = 1;
    int         so_cong_viec = 0;
    int         so_tep = 0;
    int         so_cho_phan_luong = 0;
    long long   so_byte_moi = 0;
    std::string thong_diep;

    static std::string tenTrang(Trang t) {
        switch (t) {
            case MOI:             return "moi";
            case DA_TON_TAI:      return "da_ton_tai";
            case TRUNG_HOAN_TOAN: return "trung_lap";
            case BAN_MOI:         return "ban_moi";
            default:              return "loi";
        }
    }
};

// --------------------- Thống kê phiên đồng bộ -------------------------
struct ThongKePhien {
    long long id = 0;
    int so_mail_quet = 0;
    int so_mail_moi = 0;
    int so_mail_trung = 0;
    int so_mail_ban_moi = 0;
    int so_cong_viec = 0;
    int so_tep = 0;
    int so_dung_ai = 0;
    int so_cho_phan_luong = 0;
    int so_mail_spam = 0;              // vớt được từ hộp Thư rác
    int so_mail_link = 0;              // không tệp đính kèm, chỉ có link chia sẻ
    // Thư lấy về rồi mới thấy không có tệp lẫn link chia sẻ nên bỏ. Chỉ đếm
    // trong bộ nhớ để cảnh báo cuối phiên, không lưu thành cột riêng.
    int so_mail_bo_qua = 0;
    int so_loi = 0;
    int64_t bat_dau = 0;
    int64_t ket_thuc = 0;
    std::string thong_diep;
};

} // namespace mr
