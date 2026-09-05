// =====================================================================
//  http_server.cpp - Máy chủ HTTP nhúng
// =====================================================================
#include "http_server.h"
#include "util.h"
#include "nhatky.h"

#include <cstring>
#include <cstdio>
#include <sstream>

namespace mr {

MayChuHttp::MayChuHttp() { netKhoiTao(); }
MayChuHttp::~MayChuHttp() { dung(); }

std::string MayChuHttp::diaChiGoc() const {
    std::string h = diaChi_;
    if (h == "0.0.0.0" || h.empty()) h = "127.0.0.1";
    return "http://" + h + ":" + std::to_string(congThuc_);
}

bool MayChuHttp::batDau(const std::string& diaChi, int cong, std::string& loi) {
    dung();
    diaChi_ = diaChi.empty() ? "127.0.0.1" : diaChi;

    nghe_ = ::socket(AF_INET, SOCK_STREAM, IPPROTO_TCP);
    if (nghe_ == MR_SOCK_INVALID) { loi = "Không tạo được socket: " + netLoiCuoi(); return false; }

    int mot = 1;
    setsockopt(nghe_, SOL_SOCKET, SO_REUSEADDR, (const char*)&mot, sizeof(mot));

    struct sockaddr_in dc;
    std::memset(&dc, 0, sizeof(dc));
    dc.sin_family = AF_INET;
    dc.sin_port = htons((unsigned short)cong);
    if (diaChi_ == "0.0.0.0") dc.sin_addr.s_addr = INADDR_ANY;
    else {
        if (::inet_pton(AF_INET, diaChi_.c_str(), &dc.sin_addr) != 1)
            dc.sin_addr.s_addr = htonl(INADDR_LOOPBACK);
    }

    if (::bind(nghe_, (struct sockaddr*)&dc, sizeof(dc)) != 0) {
        loi = "Không mở được cổng " + std::to_string(cong) + ": " + netLoiCuoi() +
              ". Hãy thử cổng khác trong tệp cấu hình (ung_dung.cong_giao_dien).";
        netDong(nghe_);
        nghe_ = MR_SOCK_INVALID;
        return false;
    }
    if (::listen(nghe_, 32) != 0) {
        loi = "Không lắng nghe được: " + netLoiCuoi();
        netDong(nghe_);
        nghe_ = MR_SOCK_INVALID;
        return false;
    }

    struct sockaddr_in thuc;
    socklen_t sl = sizeof(thuc);
    if (::getsockname(nghe_, (struct sockaddr*)&thuc, &sl) == 0) congThuc_ = ntohs(thuc.sin_port);
    else congThuc_ = cong;

    dangChay_.store(true);
    luong_ = std::thread(&MayChuHttp::vongLap, this);
    return true;
}

void MayChuHttp::dung() {
    if (!dangChay_.load()) {
        if (luong_.joinable()) luong_.join();
        return;
    }
    dangChay_.store(false);
    if (nghe_ != MR_SOCK_INVALID) {
#ifdef _WIN32
        ::shutdown(nghe_, SD_BOTH);
#else
        ::shutdown(nghe_, SHUT_RDWR);
#endif
        netDong(nghe_);
        nghe_ = MR_SOCK_INVALID;
    }
    if (luong_.joinable()) luong_.join();
}

void MayChuHttp::vongLap() {
    while (dangChay_.load()) {
        struct sockaddr_in dc;
        socklen_t sl = sizeof(dc);
        socket_t s = ::accept(nghe_, (struct sockaddr*)&dc, &sl);
        if (s == MR_SOCK_INVALID) {
            if (!dangChay_.load()) break;
            nguMiliGiay(20);
            continue;
        }
        char ip[64] = {0};
        ::inet_ntop(AF_INET, &dc.sin_addr, ip, sizeof(ip));
        std::string sip = ip;
        netDatTimeout(s, 30);
        std::thread([this, s, sip]() {
            this->phucVu(s, sip);
            netDong(s);
        }).detach();
    }
}

static const char* moTaMa(int ma) {
    switch (ma) {
        case 200: return "OK";
        case 204: return "No Content";
        case 302: return "Found";
        case 400: return "Bad Request";
        case 401: return "Unauthorized";
        case 403: return "Forbidden";
        case 404: return "Not Found";
        case 405: return "Method Not Allowed";
        case 413: return "Payload Too Large";
        case 500: return "Internal Server Error";
        default:  return "OK";
    }
}

void MayChuHttp::phucVu(socket_t s, const std::string& ip) {
    std::string dem;
    char buf[8192];

    // Đọc phần tiêu đề
    size_t ketThucTieuDe = std::string::npos;
    while (dem.size() < 256 * 1024) {
        long r = netNhan(s, buf, sizeof(buf));
        if (r <= 0) break;
        dem.append(buf, (size_t)r);
        ketThucTieuDe = dem.find("\r\n\r\n");
        if (ketThucTieuDe != std::string::npos) break;
    }
    if (ketThucTieuDe == std::string::npos) return;

    YeuCauMay yc;
    yc.dia_chi_ip = ip;

    std::string phanTieuDe = dem.substr(0, ketThucTieuDe);
    std::string phanThan = dem.substr(ketThucTieuDe + 4);

    std::istringstream ss(phanTieuDe);
    std::string dong;
    if (!std::getline(ss, dong)) return;
    if (!dong.empty() && dong.back() == '\r') dong.pop_back();
    {
        std::istringstream d1(dong);
        std::string url;
        d1 >> yc.phuong_thuc >> url;
        size_t q = url.find('?');
        if (q != std::string::npos) {
            yc.truy_van_tho = url.substr(q + 1);
            url = url.substr(0, q);
        }
        yc.duong_dan = urlDecode(url);
        for (const auto& cap : split(yc.truy_van_tho, '&')) {
            size_t e = cap.find('=');
            if (e == std::string::npos) yc.tham_so[urlDecode(cap)] = "";
            else yc.tham_so[urlDecode(cap.substr(0, e))] = urlDecode(cap.substr(e + 1));
        }
    }
    while (std::getline(ss, dong)) {
        if (!dong.empty() && dong.back() == '\r') dong.pop_back();
        if (dong.empty()) continue;
        size_t c = dong.find(':');
        if (c == std::string::npos) continue;
        yc.tieu_de[toLower(trim(dong.substr(0, c)))] = trim(dong.substr(c + 1));
    }

    // Đọc phần thân theo Content-Length
    long long dai = toLL(yc.td("content-length", "0"), 0);
    const long long THAN_TOI_DA = 64LL * 1024 * 1024;
    if (dai > THAN_TOI_DA) {
        std::string tl = "HTTP/1.1 413 Payload Too Large\r\nConnection: close\r\nContent-Length: 0\r\n\r\n";
        netGuiHet(s, tl.data(), tl.size());
        return;
    }
    while ((long long)phanThan.size() < dai) {
        long r = netNhan(s, buf, sizeof(buf));
        if (r <= 0) break;
        phanThan.append(buf, (size_t)r);
    }
    yc.than = phanThan.substr(0, (size_t)std::min<long long>(dai, (long long)phanThan.size()));

    PhanHoiMay ph;
    if (xuLy_) {
        try { xuLy_(yc, ph); }
        catch (const std::exception& e) { ph.ma = 500; ph.loi(500, std::string("Lỗi máy chủ: ") + e.what()); }
        catch (...) { ph.loi(500, "Lỗi máy chủ không xác định"); }
    } else {
        ph.loi(500, "Chưa gắn bộ xử lý");
    }

    std::string dau = "HTTP/1.1 " + std::to_string(ph.ma) + " " + moTaMa(ph.ma) + "\r\n";
    dau += "Content-Type: " + ph.kieu + "\r\n";
    dau += "Content-Length: " + std::to_string(ph.than.size()) + "\r\n";
    dau += "Connection: close\r\n";
    dau += "Cache-Control: no-store\r\n";
    dau += "X-Content-Type-Options: nosniff\r\n";
    for (const auto& h : ph.tieu_de_them) dau += h + "\r\n";
    dau += "\r\n";
    netGuiHet(s, dau.data(), dau.size());
    if (yc.phuong_thuc != "HEAD" && !ph.than.empty()) netGuiHet(s, ph.than.data(), ph.than.size());
}

} // namespace mr
