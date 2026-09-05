// =====================================================================
//  mysql_client.h - Trình khách MySQL/MariaDB thuần C++ (không cần
//  libmysqlclient). Hỗ trợ mysql_native_password và caching_sha2_password
//  (đường nhanh). Dùng giao thức văn bản COM_QUERY.
// =====================================================================
#pragma once

#include "net.h"

#include <string>
#include <vector>
#include <map>
#include <cstdint>

namespace mr {

struct MySqlDong {
    std::vector<std::string> o;          // giá trị các cột
    std::vector<bool> laNull;
    const std::string& operator[](size_t i) const {
        static const std::string rong;
        return (i < o.size()) ? o[i] : rong;
    }
};

class MySqlKetQua {
public:
    std::vector<std::string> cot;
    std::vector<MySqlDong>   dong;
    long long soDongAnhHuong = 0;
    long long idChen = 0;

    int viTriCot(const std::string& ten) const;
    // Lấy giá trị theo tên cột của dòng thứ i
    std::string lay(size_t i, const std::string& tenCot, const std::string& macDinh = "") const;
    long long   laySo(size_t i, const std::string& tenCot, long long macDinh = 0) const;
    bool        laNull(size_t i, const std::string& tenCot) const;
    size_t      soDong() const { return dong.size(); }
    bool        rong() const { return dong.empty(); }
};

struct MySqlThamSo {
    std::string may_chu = "127.0.0.1";
    int         cong = 3306;
    std::string nguoi_dung;
    std::string mat_khau;
    std::string co_so_du_lieu;
    int         timeout = 30;            // giây
    std::string mui_gio = "+07:00";
    std::string bang_ma = "utf8mb4";
};

class MySql {
public:
    MySql();
    ~MySql();
    MySql(const MySql&) = delete;
    MySql& operator=(const MySql&) = delete;

    bool ketNoi(const MySqlThamSo& ts, std::string& loi);
    void dong();
    bool dangKetNoi() const { return sock_ != MR_SOCK_INVALID; }
    bool kiemTraSong(std::string& loi);          // COM_PING, tự kết nối lại nếu rớt

    // Thực thi câu lệnh không trả về bảng (INSERT/UPDATE/DELETE/SET...)
    bool thucThi(const std::string& sql, std::string& loi);
    // Truy vấn trả về bảng
    bool truyVan(const std::string& sql, MySqlKetQua& kq, std::string& loi);
    // Truy vấn lấy 1 giá trị đầu tiên
    std::string layMotGiaTri(const std::string& sql, const std::string& macDinh = "");

    long long idChenCuoi() const { return idChenCuoi_; }
    long long soDongAnhHuong() const { return soDongAnhHuong_; }
    long long kichThuocGoiToiDa() const { return maxAllowedPacket_; }
    const std::string& phienBanMayChu() const { return phienBanMayChu_; }

    // Thoát chuỗi an toàn cho câu lệnh SQL (bảng mã utf8mb4)
    static std::string thoat(const std::string& s);
    // Bọc chuỗi thành 'chuỗi đã thoát'
    static std::string nhay(const std::string& s);
    // Chuyển dữ liệu nhị phân thành literal X'....'
    static std::string hexBlob(const std::string& du_lieu);

private:
    socket_t     sock_ = MR_SOCK_INVALID;
    uint8_t      seq_ = 0;
    uint32_t     capabilitiesMayChu_ = 0;
    uint32_t     capabilitiesDaChon_ = 0;
    long long    idChenCuoi_ = 0;
    long long    soDongAnhHuong_ = 0;
    long long    maxAllowedPacket_ = 4 * 1024 * 1024;
    std::string  phienBanMayChu_;
    MySqlThamSo  ts_;
    std::string  loiCuoi_;

    bool guiGoi(const std::string& payload, std::string& loi);
    bool nhanGoi(std::string& payload, std::string& loi);
    bool bacTay(std::string& loi);
    bool xuLyKetQuaLenh(MySqlKetQua* kq, std::string& loi);
    bool doiLoi(const std::string& goi, std::string& loi);
    bool khoiTaoPhien(std::string& loi);
};

} // namespace mr
