// =====================================================================
//  phanluong.h - Tách mã 001_001_TAT và phân luồng về đúng người xử lý
// =====================================================================
#pragma once

#include "mohinh.h"
#include "cauhinh.h"

#include <functional>
#include <string>
#include <vector>

namespace mr {

struct MaTimDuoc {
    MaHoSo ma;
    bool   khop_truong = false;      // mã trường có trong danh mục
    bool   khop_nguoi = false;       // mã người xử lý có trong danh mục
    bool   khop_van_ban = false;     // mã văn bản có trong danh mục
    double diem = 0;                 // độ tin cậy 0..1
    std::string nguon_van_ban;       // chuỗi đã dùng để tách
};

class BoPhanLuong {
public:
    void datDanhMuc(DanhMuc* dm) { dm_ = dm; }
    void datCauHinh(const CauHinh& ch);
    // Hàm gọi lại để tạo mã văn bản mới khi danh mục chưa có (nới lỏng)
    void datTaoVanBan(std::function<bool(const std::string&, MucVanBan&)> f) { taoVanBan_ = std::move(f); }

    // Tách mã từ một chuỗi bất kỳ (tên tệp, tiêu đề...)
    bool tachMa(const std::string& s, MaTimDuoc& ra, bool laTenTep) const;

    // Phân luồng trọn một email. Trả về danh sách nhóm công việc.
    void phanLuong(BanGhiEmail& em, std::vector<NhomCongViec>& nhom);

    // Bổ khuyết và đối chiếu danh mục cho một nhóm
    void hoanThien(NhomCongViec& n, const BanGhiEmail& em);

    bool choPhepMaVanBanMoi() const { return cho_phep_ma_vb_moi_; }
    bool batBuocMaTruong() const { return bat_buoc_ma_truong_; }
    bool batBuocMaNguoi() const { return bat_buoc_ma_nguoi_; }
    const std::string& nguoiMacDinh() const { return nguoi_mac_dinh_; }

private:
    DanhMuc* dm_ = nullptr;
    std::function<bool(const std::string&, MucVanBan&)> taoVanBan_;
    bool cho_phep_ma_vb_moi_ = true;
    bool bat_buoc_ma_truong_ = true;
    bool bat_buoc_ma_nguoi_ = true;
    std::string nguoi_mac_dinh_;
    std::string dau_phan_cach_ = "_-. ()[]{},;+#";

    std::vector<std::string> tachTu(const std::string& s) const;
    long long timTruongTheoEmail(const std::string& email) const;
};

} // namespace mr
