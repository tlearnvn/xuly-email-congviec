// =====================================================================
//  http_server.h - Máy chủ HTTP nhúng (phục vụ giao diện đồ hoạ và
//  tiếp nhận chuyển hướng OAuth của Google)
// =====================================================================
#pragma once

#include "net.h"

#include <string>
#include <map>
#include <vector>
#include <functional>
#include <atomic>
#include <thread>

namespace mr {

struct YeuCauMay {
    std::string phuong_thuc;
    std::string duong_dan;                          // đã giải mã URL
    std::string truy_van_tho;
    std::string than;
    std::map<std::string, std::string> tieu_de;     // khoá viết thường
    std::map<std::string, std::string> tham_so;     // tham số truy vấn đã giải mã
    std::string dia_chi_ip;

    std::string ts(const std::string& k, const std::string& macDinh = "") const {
        auto it = tham_so.find(k);
        return (it == tham_so.end()) ? macDinh : it->second;
    }
    std::string td(const std::string& k, const std::string& macDinh = "") const {
        auto it = tieu_de.find(k);
        return (it == tieu_de.end()) ? macDinh : it->second;
    }
};

struct PhanHoiMay {
    int ma = 200;
    std::string kieu = "text/html; charset=utf-8";
    std::string than;
    std::vector<std::string> tieu_de_them;

    void json(const std::string& s) { kieu = "application/json; charset=utf-8"; than = s; }
    void vanBan(const std::string& s) { kieu = "text/plain; charset=utf-8"; than = s; }
    void html(const std::string& s) { kieu = "text/html; charset=utf-8"; than = s; }
    void chuyenHuong(const std::string& url) {
        ma = 302;
        tieu_de_them.push_back("Location: " + url);
        than = "";
    }
    void loi(int m, const std::string& thongDiep) {
        ma = m;
        kieu = "application/json; charset=utf-8";
        than = std::string("{\"ok\":false,\"loi\":\"") + thongDiep + "\"}";
    }
};

class MayChuHttp {
public:
    typedef std::function<void(const YeuCauMay&, PhanHoiMay&)> BoXuLy;

    MayChuHttp();
    ~MayChuHttp();

    void datBoXuLy(BoXuLy f) { xuLy_ = std::move(f); }
    // cong = 0 -> hệ điều hành tự chọn cổng trống
    bool batDau(const std::string& diaChi, int cong, std::string& loi);
    void dung();
    bool dangChay() const { return dangChay_.load(); }
    int  cong() const { return congThuc_; }
    std::string diaChiGoc() const;

private:
    socket_t nghe_ = MR_SOCK_INVALID;
    std::atomic<bool> dangChay_{false};
    std::thread luong_;
    int congThuc_ = 0;
    std::string diaChi_;
    BoXuLy xuLy_;

    void vongLap();
    void phucVu(socket_t s, const std::string& ip);
};

} // namespace mr
