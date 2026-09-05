// =====================================================================
//  http_client.h - Trình khách HTTPS đa nền tảng
//    * Windows : WinHTTP (có sẵn trong hệ điều hành, không cần cài thêm)
//    * Linux   : libcurl nạp động (libcurl.so.4)
// =====================================================================
#pragma once

#include <string>
#include <vector>
#include <map>

namespace mr {

struct HttpPhanHoi {
    long        ma = 0;                  // mã trạng thái HTTP
    std::string than;                    // nội dung trả về
    std::map<std::string, std::string> tieuDe;   // khoá đã hạ chữ thường
    std::string loi;                     // rỗng nếu không có lỗi tầng vận chuyển
    double      giay = 0;                // thời gian thực hiện

    bool thanhCong() const { return loi.empty() && ma >= 200 && ma < 300; }
    std::string moTa() const;
};

struct HttpYeuCau {
    std::string phuong_thuc = "GET";
    std::string url;
    std::vector<std::string> tieu_de;    // dạng "Khoá: giá trị"
    std::string than;
    int         timeout = 60;            // giây
    bool        theo_chuyen_huong = true;
};

class HttpClient {
public:
    static HttpPhanHoi gui(const HttpYeuCau& yc);

    static HttpPhanHoi get(const std::string& url,
                           const std::vector<std::string>& tieuDe = {},
                           int timeout = 60);

    static HttpPhanHoi post(const std::string& url,
                            const std::string& than,
                            const std::vector<std::string>& tieuDe = {},
                            int timeout = 60);

    static HttpPhanHoi postJson(const std::string& url,
                                const std::string& json,
                                const std::vector<std::string>& tieuDe = {},
                                int timeout = 60);

    static HttpPhanHoi postForm(const std::string& url,
                                const std::map<std::string, std::string>& truong,
                                int timeout = 60);

    // Kiểm tra tầng HTTP đã sẵn sàng chưa (Linux: có libcurl hay không)
    static bool sanSang(std::string& loi);
    static std::string tenNenTang();
};

} // namespace mr
