// =====================================================================
//  nhatky.h - Ghi nhật ký ra màn hình, tệp, bộ đệm cho giao diện và CSDL
// =====================================================================
#pragma once

#include <string>
#include <vector>
#include <functional>
#include <mutex>
#include <deque>

namespace mr {

enum class Muc { DEBUG_ = 0, INFO = 1, CANH_BAO = 2, LOI = 3 };

struct DongNhatKy {
    long long   soThuTu = 0;
    std::string thoi_gian;     // giờ Việt Nam
    Muc         muc = Muc::INFO;
    std::string hanh_dong;
    std::string noi_dung;
    std::string doi_tuong;
    std::string id_doi_tuong;
};

class NhatKy {
public:
    static NhatKy& i();

    void datMuc(Muc m) { mucToiThieu_ = m; }
    Muc  muc() const { return mucToiThieu_; }
    void datMucTuChuoi(const std::string& s);
    void datThuMuc(const std::string& thuMuc);
    void datGhiCsdl(std::function<void(const DongNhatKy&)> f);
    void tatMauSac(bool tat) { khongMau_ = tat; }

    void ghi(Muc m, const std::string& hanhDong, const std::string& noiDung,
             const std::string& doiTuong = "", const std::string& idDoiTuong = "");

    void go(const std::string& hd, const std::string& nd) { ghi(Muc::DEBUG_, hd, nd); }
    void tin(const std::string& hd, const std::string& nd) { ghi(Muc::INFO, hd, nd); }
    void canhBao(const std::string& hd, const std::string& nd) { ghi(Muc::CANH_BAO, hd, nd); }
    void loi(const std::string& hd, const std::string& nd) { ghi(Muc::LOI, hd, nd); }

    // Lấy các dòng nhật ký gần đây (cho giao diện web), soThuTu > tu
    std::vector<DongNhatKy> ganDay(long long tu = 0, size_t toiDa = 300) const;
    long long soThuTuHienTai() const;

    static std::string tenMuc(Muc m);
    static Muc mucTuChuoi(const std::string& s);

private:
    NhatKy() = default;
    mutable std::mutex khoa_;
    Muc mucToiThieu_ = Muc::INFO;
    std::string thuMuc_;
    std::deque<DongNhatKy> dem_;
    long long soThuTu_ = 0;
    bool khongMau_ = false;
    std::function<void(const DongNhatKy&)> ghiCsdl_;
};

#define NK mr::NhatKy::i()

} // namespace mr
