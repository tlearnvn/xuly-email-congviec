// =====================================================================
//  nhatky.cpp
// =====================================================================
#include "nhatky.h"
#include "util.h"

#include <cstdio>
#include <iostream>

#ifdef _WIN32
  #ifndef WIN32_LEAN_AND_MEAN
    #define WIN32_LEAN_AND_MEAN
  #endif
  #include <windows.h>
#endif

namespace mr {

static const size_t DEM_TOI_DA = 2000;

NhatKy& NhatKy::i() {
    static NhatKy n;
    static bool khoiTao = false;
    if (!khoiTao) {
        khoiTao = true;
#ifdef _WIN32
        // Bật hỗ trợ màu ANSI trên Windows 10 trở lên
        HANDLE h = GetStdHandle(STD_OUTPUT_HANDLE);
        DWORD che = 0;
        if (h != INVALID_HANDLE_VALUE && GetConsoleMode(h, &che))
            SetConsoleMode(h, che | 0x0004 /*ENABLE_VIRTUAL_TERMINAL_PROCESSING*/);
        SetConsoleOutputCP(CP_UTF8);
#endif
    }
    return n;
}

std::string NhatKy::tenMuc(Muc m) {
    switch (m) {
        case Muc::DEBUG_:    return "debug";
        case Muc::INFO:      return "info";
        case Muc::CANH_BAO:  return "canh_bao";
        case Muc::LOI:       return "loi";
    }
    return "info";
}

Muc NhatKy::mucTuChuoi(const std::string& s) {
    std::string t = toLower(trim(s));
    if (t == "debug" || t == "go") return Muc::DEBUG_;
    if (t == "canh_bao" || t == "warn" || t == "warning") return Muc::CANH_BAO;
    if (t == "loi" || t == "error") return Muc::LOI;
    return Muc::INFO;
}

void NhatKy::datMucTuChuoi(const std::string& s) { mucToiThieu_ = mucTuChuoi(s); }

void NhatKy::datThuMuc(const std::string& thuMuc) {
    std::lock_guard<std::mutex> g(khoa_);
    thuMuc_ = thuMuc;
    if (!thuMuc_.empty()) taoThuMuc(thuMuc_);
}

void NhatKy::datGhiCsdl(std::function<void(const DongNhatKy&)> f) {
    std::lock_guard<std::mutex> g(khoa_);
    ghiCsdl_ = std::move(f);
}

void NhatKy::ghi(Muc m, const std::string& hanhDong, const std::string& noiDung,
                 const std::string& doiTuong, const std::string& idDoiTuong) {
    if ((int)m < (int)mucToiThieu_) return;

    DongNhatKy d;
    d.thoi_gian = gioVNHienTai();
    d.muc = m;
    d.hanh_dong = hanhDong;
    d.noi_dung = locUtf8(noiDung);
    d.doi_tuong = doiTuong;
    d.id_doi_tuong = idDoiTuong;

    std::function<void(const DongNhatKy&)> csdl;
    std::string thuMuc;
    bool khongMau;
    {
        std::lock_guard<std::mutex> g(khoa_);
        d.soThuTu = ++soThuTu_;
        dem_.push_back(d);
        while (dem_.size() > DEM_TOI_DA) dem_.pop_front();
        csdl = ghiCsdl_;
        thuMuc = thuMuc_;
        khongMau = khongMau_;
    }

    // Màn hình
    const char* mau = "";
    const char* het = khongMau ? "" : "\x1b[0m";
    const char* nhan = "";
    switch (m) {
        case Muc::DEBUG_:   mau = khongMau ? "" : "\x1b[90m"; nhan = "GO   "; break;
        case Muc::INFO:     mau = khongMau ? "" : "\x1b[36m"; nhan = "TIN  "; break;
        case Muc::CANH_BAO: mau = khongMau ? "" : "\x1b[33m"; nhan = "CANH "; break;
        case Muc::LOI:      mau = khongMau ? "" : "\x1b[31m"; nhan = "LOI  "; break;
    }
    std::string dong = d.thoi_gian + "  " + mau + nhan + het + " " +
                       (hanhDong.empty() ? "" : ("[" + hanhDong + "] ")) + d.noi_dung;
    std::fputs((dong + "\n").c_str(), (m == Muc::LOI) ? stderr : stdout);
    std::fflush((m == Muc::LOI) ? stderr : stdout);

    // Tệp
    if (!thuMuc.empty()) {
        std::string tep = thuMuc + "/mailrouter-" + dinhDangGioVN(nowEpoch(), "%Y%m%d") + ".log";
        FILE* f = std::fopen(tep.c_str(), "ab");
        if (f) {
            std::string s = d.thoi_gian + "\t" + tenMuc(m) + "\t" +
                            (hanhDong.empty() ? "-" : hanhDong) + "\t" + d.noi_dung + "\n";
            std::fwrite(s.data(), 1, s.size(), f);
            std::fclose(f);
        }
    }

    // CSDL
    if (csdl) {
        try { csdl(d); } catch (...) {}
    }
}

std::vector<DongNhatKy> NhatKy::ganDay(long long tu, size_t toiDa) const {
    std::lock_guard<std::mutex> g(khoa_);
    std::vector<DongNhatKy> r;
    for (const auto& d : dem_) if (d.soThuTu > tu) r.push_back(d);
    if (r.size() > toiDa) r.erase(r.begin(), r.begin() + (r.size() - toiDa));
    return r;
}

long long NhatKy::soThuTuHienTai() const {
    std::lock_guard<std::mutex> g(khoa_);
    return soThuTu_;
}

} // namespace mr
