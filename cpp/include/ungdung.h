// =====================================================================
//  ungdung.h - Bộ điều phối chính của chương trình nhận mail
// =====================================================================
#pragma once

#include "cauhinh.h"
#include "kholuutru.h"
#include "gmail.h"
#include "phanluong.h"
#include "ai.h"
#include "mohinh.h"
#include "json.h"

#include <atomic>
#include <mutex>
#include <string>
#include <thread>
#include <vector>

namespace mr {

struct DongTienTrinh {
    std::string  buoc;
    int          da_lam = 0;
    int          tong = 0;
    std::string  thong_diep;
};

class UngDung {
public:
    UngDung();
    ~UngDung();

    bool khoiTao(const std::string& tepCauHinh, std::string& loi);
    void ghiCauHinh();
    CauHinh& cauHinh() { return ch_; }
    const CauHinh& cauHinh() const { return ch_; }
    Gmail& gmail() { return gmail_; }
    DanhMuc& danhMuc() { return dm_; }
    TroLyAi& troLyAi() { return ai_; }
    BoPhanLuong& boPhanLuong() { return pl_; }

    // Kết nối kho lưu trữ + nạp danh mục, cấu hình từ máy chủ
    bool ketNoiMayChu(std::string& loi);
    bool daKetNoi() const { return kho_ != nullptr && daKetNoi_; }
    std::string tenKho() const;
    KhoLuuTru* kho() { return kho_.get(); }

    // Đồng bộ một lần (đồng bộ hoá bằng khoá, chỉ một phiên chạy tại một thời điểm)
    bool dongBo(ThongKePhien& tk, std::string& loi, int gioiHan = 0, const std::string& truyVanRieng = "");
    bool dangDongBo() const { return dangDongBo_.load(); }
    void yeuCauDung() { yeuCauDung_.store(true); }

    // Dịch vụ chạy nền theo chu kỳ
    void batDauDichVu();
    void dungDichVu();
    bool dichVuDangChay() const { return dichVuChay_.load(); }
    int64_t lanChayKe() const { return lanChayKe_.load(); }

    // Trạng thái tổng hợp cho giao diện
    Json trangThai();
    DongTienTrinh tienTrinh() const;
    const ThongKePhien& phienGanNhat() const { return phienCuoi_; }

    // Áp dụng thông tin OAuth mới
    void luuToken(const TokenGmail& t);
    std::string diaChiChuyenHuong() const;
    void datCongGiaoDien(int cong) { congGiaoDien_ = cong; }

private:
    CauHinh     ch_;
    DanhMuc     dm_;
    Gmail       gmail_;
    BoPhanLuong pl_;
    TroLyAi     ai_;
    std::unique_ptr<KhoLuuTru> kho_;
    std::map<std::string, std::string> chMayChu_;

    mutable std::mutex khoaViec_;
    mutable std::mutex khoaTienTrinh_;
    DongTienTrinh tienTrinh_;
    ThongKePhien  phienCuoi_;
    std::atomic<bool> dangDongBo_{false};
    std::atomic<bool> yeuCauDung_{false};
    std::atomic<bool> dichVuChay_{false};
    std::atomic<long long> lanChayKe_{0};
    std::thread luongDichVu_;
    bool daKetNoi_ = false;
    int  congGiaoDien_ = 0;
    int64_t lanDongBoCuoi_ = 0;
    std::string loiCuoi_;

    void datTienTrinh(const std::string& buoc, int daLam, int tong, const std::string& td = "");
    void vongLapDichVu();
    bool xuLyMotMail(const std::string& id, ThongKePhien& tk, std::string& loi);
    void tinhHash(BanGhiEmail& em) const;
    bool taiTepDinhKem(BanGhiEmail& em, ThongKePhien& tk);
    bool nhoAi(BanGhiEmail& em, std::vector<NhomCongViec>& nhom, ThongKePhien& tk);
};

} // namespace mr
