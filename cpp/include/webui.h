// =====================================================================
//  webui.h - Giao diện đồ hoạ chạy trong trình duyệt (nhúng sẵn)
// =====================================================================
#pragma once

#include "http_server.h"
#include "ungdung.h"

#include <string>
#include <thread>
#include <mutex>

namespace mr {

// Tài nguyên giao diện được nhúng thẳng vào tệp thực thi
struct TaiNguyen {
    const char* duong_dan;
    const unsigned char* du_lieu;
    unsigned int do_dai;
    const char* kieu;
};
extern const TaiNguyen TAI_NGUYEN[];
extern const unsigned int SO_TAI_NGUYEN;

class GiaoDienWeb {
public:
    explicit GiaoDienWeb(UngDung& ud) : ud_(ud) {}
    ~GiaoDienWeb();

    bool batDau(std::string& loi);
    void dung();
    std::string diaChi() const { return may_.diaChiGoc(); }
    int cong() const { return may_.cong(); }

private:
    UngDung&    ud_;
    MayChuHttp  may_;
    std::string state_oauth_;
    std::mutex  khoa_;
    std::thread luongDongBo_;

    void dinhTuyen(const YeuCauMay& yc, PhanHoiMay& ph);
    bool duocPhep(const YeuCauMay& yc) const;
    static void traJson(PhanHoiMay& ph, const Json& j);
    static void traLoi(PhanHoiMay& ph, int ma, const std::string& thongDiep);
    void chayDongBoNen(int gioiHan, const std::string& truyVan);
    std::string trangKetQuaOAuth(bool ok, const std::string& thongDiep) const;
};

} // namespace mr
