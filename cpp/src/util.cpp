// =====================================================================
//  util.cpp - Tiện ích dùng chung
// =====================================================================
#include "util.h"

#include <algorithm>
#include <cctype>
#include <cstdio>
#include <cstring>
#include <cstdlib>
#include <random>
#include <sstream>
#include <chrono>
#include <thread>
#include <sys/stat.h>

#ifdef _WIN32
  #ifndef WIN32_LEAN_AND_MEAN
    #define WIN32_LEAN_AND_MEAN
  #endif
  #include <windows.h>
  #include <direct.h>
  #include <shellapi.h>
#else
  #include <unistd.h>
  #include <limits.h>
#endif

namespace mr {

// =====================================================================
//  Chuỗi cơ bản
// =====================================================================
std::string trim(const std::string& s) {
    size_t a = 0, b = s.size();
    while (a < b && (unsigned char)s[a] <= ' ') a++;
    while (b > a && (unsigned char)s[b - 1] <= ' ') b--;
    return s.substr(a, b - a);
}

std::string toLower(const std::string& s) {
    std::string r = s;
    for (auto& c : r) if (c >= 'A' && c <= 'Z') c = char(c - 'A' + 'a');
    return r;
}

std::string toUpper(const std::string& s) {
    std::string r = s;
    for (auto& c : r) if (c >= 'a' && c <= 'z') c = char(c - 'a' + 'A');
    return r;
}

bool startsWith(const std::string& s, const std::string& p) {
    return s.size() >= p.size() && std::memcmp(s.data(), p.data(), p.size()) == 0;
}

bool endsWith(const std::string& s, const std::string& p) {
    return s.size() >= p.size() && std::memcmp(s.data() + s.size() - p.size(), p.data(), p.size()) == 0;
}

bool containsIC(const std::string& hay, const std::string& needle) {
    if (needle.empty()) return true;
    return toLower(hay).find(toLower(needle)) != std::string::npos;
}

std::vector<std::string> split(const std::string& s, char sep, bool keepEmpty) {
    std::vector<std::string> out;
    std::string cur;
    for (char c : s) {
        if (c == sep) {
            if (keepEmpty || !cur.empty()) out.push_back(cur);
            cur.clear();
        } else cur += c;
    }
    if (keepEmpty || !cur.empty()) out.push_back(cur);
    return out;
}

std::vector<std::string> splitAny(const std::string& s, const std::string& seps) {
    std::vector<std::string> out;
    std::string cur;
    for (char c : s) {
        if (seps.find(c) != std::string::npos) {
            if (!cur.empty()) out.push_back(cur);
            cur.clear();
        } else cur += c;
    }
    if (!cur.empty()) out.push_back(cur);
    return out;
}

std::string join(const std::vector<std::string>& v, const std::string& sep) {
    std::string r;
    for (size_t i = 0; i < v.size(); i++) { if (i) r += sep; r += v[i]; }
    return r;
}

std::string replaceAll(std::string s, const std::string& from, const std::string& to) {
    if (from.empty()) return s;
    size_t pos = 0;
    while ((pos = s.find(from, pos)) != std::string::npos) {
        s.replace(pos, from.size(), to);
        pos += to.size();
    }
    return s;
}

std::string padLeft(const std::string& s, size_t n, char c) {
    if (s.size() >= n) return s;
    return std::string(n - s.size(), c) + s;
}

// =====================================================================
//  Tiếng Việt
// =====================================================================
// Giải mã UTF-8 -> danh sách code point
static std::vector<uint32_t> utf8ToCodepoints(const std::string& s) {
    std::vector<uint32_t> out;
    size_t i = 0, n = s.size();
    while (i < n) {
        unsigned char c = (unsigned char)s[i];
        uint32_t cp; int len;
        if (c < 0x80)           { cp = c;          len = 1; }
        else if ((c & 0xE0) == 0xC0) { cp = c & 0x1F; len = 2; }
        else if ((c & 0xF0) == 0xE0) { cp = c & 0x0F; len = 3; }
        else if ((c & 0xF8) == 0xF0) { cp = c & 0x07; len = 4; }
        else { i++; continue; }                        // byte hỏng -> bỏ
        if (i + len > n) break;
        bool ok = true;
        for (int k = 1; k < len; k++) {
            unsigned char cc = (unsigned char)s[i + k];
            if ((cc & 0xC0) != 0x80) { ok = false; break; }
            cp = (cp << 6) | (cc & 0x3F);
        }
        if (!ok) { i++; continue; }
        out.push_back(cp);
        i += len;
    }
    return out;
}

static void codepointToUtf8(uint32_t cp, std::string& out) {
    if (cp < 0x80) out += char(cp);
    else if (cp < 0x800) {
        out += char(0xC0 | (cp >> 6));
        out += char(0x80 | (cp & 0x3F));
    } else if (cp < 0x10000) {
        out += char(0xE0 | (cp >> 12));
        out += char(0x80 | ((cp >> 6) & 0x3F));
        out += char(0x80 | (cp & 0x3F));
    } else {
        out += char(0xF0 | (cp >> 18));
        out += char(0x80 | ((cp >> 12) & 0x3F));
        out += char(0x80 | ((cp >> 6) & 0x3F));
        out += char(0x80 | (cp & 0x3F));
    }
}

// Bỏ dấu một code point tiếng Việt / Latin mở rộng
static char boDauCodepoint(uint32_t cp) {
    if (cp < 128) return char(cp);
    // Latin-1 Supplement
    if (cp >= 0x00C0 && cp <= 0x00C5) return 'A';
    if (cp == 0x00C7) return 'C';
    if (cp >= 0x00C8 && cp <= 0x00CB) return 'E';
    if (cp >= 0x00CC && cp <= 0x00CF) return 'I';
    if (cp == 0x00D1) return 'N';
    if (cp >= 0x00D2 && cp <= 0x00D6) return 'O';
    if (cp == 0x00D8) return 'O';
    if (cp >= 0x00D9 && cp <= 0x00DC) return 'U';
    if (cp == 0x00DD) return 'Y';
    if (cp >= 0x00E0 && cp <= 0x00E5) return 'a';
    if (cp == 0x00E7) return 'c';
    if (cp >= 0x00E8 && cp <= 0x00EB) return 'e';
    if (cp >= 0x00EC && cp <= 0x00EF) return 'i';
    if (cp == 0x00F1) return 'n';
    if (cp >= 0x00F2 && cp <= 0x00F6) return 'o';
    if (cp == 0x00F8) return 'o';
    if (cp >= 0x00F9 && cp <= 0x00FC) return 'u';
    if (cp == 0x00FD || cp == 0x00FF) return 'y';
    // Latin Extended-A / B
    if (cp == 0x0100 || cp == 0x0102 || cp == 0x0104) return 'A';
    if (cp == 0x0101 || cp == 0x0103 || cp == 0x0105) return 'a';
    if (cp == 0x0110) return 'D';                       // Đ
    if (cp == 0x0111) return 'd';                       // đ
    if (cp == 0x0128 || cp == 0x012A || cp == 0x012C) return 'I';
    if (cp == 0x0129 || cp == 0x012B || cp == 0x012D) return 'i';
    if (cp == 0x0168 || cp == 0x016A || cp == 0x016C) return 'U';
    if (cp == 0x0169 || cp == 0x016B || cp == 0x016D) return 'u';
    if (cp == 0x01A0) return 'O';                       // Ơ
    if (cp == 0x01A1) return 'o';                       // ơ
    if (cp == 0x01AF) return 'U';                       // Ư
    if (cp == 0x01B0) return 'u';                       // ư
    // Latin Extended Additional (tiếng Việt)
    if (cp >= 0x1EA0 && cp <= 0x1EB7) return (cp % 2 == 0) ? 'A' : 'a';
    if (cp >= 0x1EB8 && cp <= 0x1EC7) return (cp % 2 == 0) ? 'E' : 'e';
    if (cp >= 0x1EC8 && cp <= 0x1ECB) return (cp % 2 == 0) ? 'I' : 'i';
    if (cp >= 0x1ECC && cp <= 0x1EE3) return (cp % 2 == 0) ? 'O' : 'o';
    if (cp >= 0x1EE4 && cp <= 0x1EF1) return (cp % 2 == 0) ? 'U' : 'u';
    if (cp >= 0x1EF2 && cp <= 0x1EF9) return (cp % 2 == 0) ? 'Y' : 'y';
    return 0;   // không ánh xạ được
}

std::string boDauTiengViet(const std::string& utf8) {
    std::string out;
    out.reserve(utf8.size());
    for (uint32_t cp : utf8ToCodepoints(utf8)) {
        char c = boDauCodepoint(cp);
        if (c) out += c;
        else if (cp == 0x2019 || cp == 0x2018) out += '\'';
        else if (cp == 0x201C || cp == 0x201D) out += '"';
        else if (cp == 0x2013 || cp == 0x2014) out += '-';
        else if (cp == 0x00A0) out += ' ';
        else out += ' ';   // ký tự lạ -> khoảng trắng
    }
    return out;
}

std::string chuanHoaMa(const std::string& s) {
    std::string a = toUpper(boDauTiengViet(s));
    std::string r;
    for (char c : a) if (std::isalnum((unsigned char)c)) r += c;
    return r;
}

std::string chuanHoaMaSo(const std::string& s) {
    std::string m = chuanHoaMa(s);
    bool toanSo = !m.empty();
    for (char c : m) if (!std::isdigit((unsigned char)c)) { toanSo = false; break; }
    if (toanSo) {
        size_t i = 0;
        while (i + 1 < m.size() && m[i] == '0') i++;
        return m.substr(i);
    }
    return m;
}

std::string chuanHoaTieuDe(const std::string& s, const std::vector<std::string>& tienToBoQua) {
    std::string a = toUpper(boDauTiengViet(s));
    // Bỏ các tiền tố lặp lại kiểu "RE: FW: RE:"
    bool caiTien = true;
    while (caiTien) {
        caiTien = false;
        a = trim(a);
        for (const auto& p : tienToBoQua) {
            std::string pp = trim(toUpper(boDauTiengViet(p)));
            if (pp.empty()) continue;
            if (startsWith(a, pp)) { a = trim(a.substr(pp.size())); caiTien = true; }
        }
    }
    // Gom khoảng trắng, bỏ ký tự đặc biệt trùng lặp
    std::string r;
    bool space = false;
    for (char c : a) {
        if ((unsigned char)c <= ' ') { space = true; continue; }
        if (space && !r.empty()) r += ' ';
        space = false;
        r += c;
    }
    return r;
}

std::string catUtf8(const std::string& s, size_t maxBytes) {
    if (s.size() <= maxBytes) return s;
    size_t n = maxBytes;
    while (n > 0 && ((unsigned char)s[n] & 0xC0) == 0x80) n--;
    return s.substr(0, n);
}

std::string locUtf8(const std::string& s) {
    std::string out;
    out.reserve(s.size());
    for (uint32_t cp : utf8ToCodepoints(s)) {
        if (cp == '\n' || cp == '\r' || cp == '\t') { out += char(cp); continue; }
        if (cp < 0x20 || cp == 0x7F) continue;
        if (cp >= 0xD800 && cp <= 0xDFFF) continue;
        codepointToUtf8(cp, out);
    }
    return out;
}

// =====================================================================
//  Thời gian (giờ Việt Nam GMT+7, không phụ thuộc TZ hệ thống)
// =====================================================================
int64_t nowEpoch() {
    return (int64_t)std::chrono::duration_cast<std::chrono::seconds>(
        std::chrono::system_clock::now().time_since_epoch()).count();
}

int64_t nowEpochMs() {
    return (int64_t)std::chrono::duration_cast<std::chrono::milliseconds>(
        std::chrono::system_clock::now().time_since_epoch()).count();
}

std::string dinhDangGioVN(int64_t epochSeconds, const char* fmt) {
    time_t t = (time_t)(epochSeconds + VN_OFFSET_SECONDS);
    struct tm tmv;
#ifdef _WIN32
    gmtime_s(&tmv, &t);
#else
    gmtime_r(&t, &tmv);
#endif
    char buf[128];
    if (std::strftime(buf, sizeof(buf), fmt, &tmv) == 0) return "";
    return std::string(buf);
}

std::string gioVNHienTai(const char* fmt) { return dinhDangGioVN(nowEpoch(), fmt); }
std::string gioVNTepTin() { return dinhDangGioVN(nowEpoch(), "%Y%m%d_%H%M%S"); }

static int thangTuTen(const std::string& m) {
    static const char* ts[] = {"Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"};
    for (int i = 0; i < 12; i++) if (toLower(m).substr(0,3) == toLower(ts[i])) return i;
    return -1;
}

// Chuyển struct tm (coi là UTC) sang epoch, không phụ thuộc timezone hệ thống
static int64_t timegm_portable(int year, int mon, int day, int hour, int min, int sec) {
    static const int cumDays[12] = {0,31,59,90,120,151,181,212,243,273,304,334};
    if (mon < 0 || mon > 11) return 0;
    int64_t y = year;
    int64_t days = (y - 1970) * 365 + ((y - 1969) / 4) - ((y - 1901) / 100) + ((y - 1601) / 400);
    days += cumDays[mon];
    bool nhuan = (y % 4 == 0 && y % 100 != 0) || (y % 400 == 0);
    if (nhuan && mon >= 2) days += 1;
    days += (day - 1);
    return days * 86400LL + hour * 3600LL + min * 60LL + sec;
}

int64_t phanTichNgayRfc2822(const std::string& sIn) {
    // Ví dụ: "Fri, 05 Sep 2026 08:15:00 +0700"
    std::string s = trim(sIn);
    size_t comma = s.find(',');
    if (comma != std::string::npos && comma <= 4) s = trim(s.substr(comma + 1));
    // bỏ phần chú thích trong ngoặc
    size_t par = s.find('(');
    if (par != std::string::npos) s = trim(s.substr(0, par));

    int day = 0, year = 0, hh = 0, mm = 0, ss = 0;
    char monBuf[16] = {0}, tzBuf[16] = {0};
    int n = std::sscanf(s.c_str(), "%d %15s %d %d:%d:%d %15s", &day, monBuf, &year, &hh, &mm, &ss, tzBuf);
    if (n < 6) {
        n = std::sscanf(s.c_str(), "%d %15s %d %d:%d %15s", &day, monBuf, &year, &hh, &mm, tzBuf);
        if (n < 5) return 0;
        ss = 0;
    }
    int mon = thangTuTen(monBuf);
    if (mon < 0) return 0;
    if (year < 100) year += (year < 70) ? 2000 : 1900;

    int64_t t = timegm_portable(year, mon, day, hh, mm, ss);
    // Xử lý múi giờ
    std::string tz = trim(tzBuf);
    if (!tz.empty()) {
        if (tz[0] == '+' || tz[0] == '-') {
            int sign = (tz[0] == '-') ? -1 : 1;
            std::string digits;
            for (size_t i = 1; i < tz.size(); i++) if (std::isdigit((unsigned char)tz[i])) digits += tz[i];
            if (digits.size() >= 3) {
                int hOff = std::atoi(digits.substr(0, digits.size() - 2).c_str());
                int mOff = std::atoi(digits.substr(digits.size() - 2).c_str());
                t -= sign * (hOff * 3600 + mOff * 60);
            }
        } else {
            std::string u = toUpper(tz);
            if (u == "EST") t += 5 * 3600;
            else if (u == "EDT") t += 4 * 3600;
            else if (u == "CST") t += 6 * 3600;
            else if (u == "PST") t += 8 * 3600;
            // GMT / UT / UTC / Z -> 0
        }
    }
    return t;
}

int64_t phanTichNgayISO_VN(const std::string& s) {
    int Y = 0, M = 0, D = 0, h = 0, m = 0, sec = 0;
    if (std::sscanf(s.c_str(), "%d-%d-%d %d:%d:%d", &Y, &M, &D, &h, &m, &sec) < 3) {
        if (std::sscanf(s.c_str(), "%d-%d-%dT%d:%d:%d", &Y, &M, &D, &h, &m, &sec) < 3) return 0;
    }
    if (Y < 1970 || M < 1 || M > 12) return 0;
    return timegm_portable(Y, M - 1, D, h, m, sec) - VN_OFFSET_SECONDS;
}

// =====================================================================
//  Số / mã hoá
// =====================================================================
static const char* HEXD = "0123456789abcdef";

std::string toHex(const std::vector<uint8_t>& v) {
    std::string r;
    r.reserve(v.size() * 2);
    for (uint8_t b : v) { r += HEXD[b >> 4]; r += HEXD[b & 0xF]; }
    return r;
}

std::string toHex(const std::string& s) {
    std::string r;
    r.reserve(s.size() * 2);
    for (unsigned char b : s) { r += HEXD[b >> 4]; r += HEXD[b & 0xF]; }
    return r;
}

std::vector<uint8_t> fromHex(const std::string& hex) {
    std::vector<uint8_t> out;
    auto val = [](char c) -> int {
        if (c >= '0' && c <= '9') return c - '0';
        if (c >= 'a' && c <= 'f') return c - 'a' + 10;
        if (c >= 'A' && c <= 'F') return c - 'A' + 10;
        return -1;
    };
    for (size_t i = 0; i + 1 < hex.size(); i += 2) {
        int hi = val(hex[i]), lo = val(hex[i + 1]);
        if (hi < 0 || lo < 0) break;
        out.push_back(uint8_t((hi << 4) | lo));
    }
    return out;
}

std::string chuoiNgauNhien(size_t nByte) {
    static std::mt19937_64 rng(
        (uint64_t)std::chrono::high_resolution_clock::now().time_since_epoch().count() ^
        (uint64_t)(uintptr_t)&nByte);
    std::string r;
    r.reserve(nByte * 2);
    for (size_t i = 0; i < nByte; i++) {
        uint8_t b = uint8_t(rng() & 0xFF);
        r += HEXD[b >> 4]; r += HEXD[b & 0xF];
    }
    return r;
}

long long toLL(const std::string& s, long long mac_dinh) {
    std::string t = trim(s);
    if (t.empty()) return mac_dinh;
    char* end = nullptr;
    long long v = std::strtoll(t.c_str(), &end, 10);
    if (end == t.c_str()) return mac_dinh;
    return v;
}

double toDouble(const std::string& s, double mac_dinh) {
    std::string t = trim(s);
    if (t.empty()) return mac_dinh;
    char* end = nullptr;
    double v = std::strtod(t.c_str(), &end);
    if (end == t.c_str()) return mac_dinh;
    return v;
}

bool toBool(const std::string& s, bool mac_dinh) {
    std::string t = toLower(trim(s));
    if (t.empty()) return mac_dinh;
    return (t == "1" || t == "true" || t == "yes" || t == "on" || t == "co" || t == "có");
}

std::string dinhDangDungLuong(long long bytes) {
    const char* dv[] = {"B", "KB", "MB", "GB", "TB"};
    double v = (double)bytes;
    int i = 0;
    while (v >= 1024.0 && i < 4) { v /= 1024.0; i++; }
    char buf[64];
    if (i == 0) std::snprintf(buf, sizeof(buf), "%lld B", bytes);
    else std::snprintf(buf, sizeof(buf), "%.1f %s", v, dv[i]);
    std::string r = buf;
    return replaceAll(r, ".", ",");   // dấu thập phân kiểu Việt Nam
}

// =====================================================================
//  Tệp / hệ thống
// =====================================================================
bool docTep(const std::string& duongDan, std::string& noiDung) {
    FILE* f = std::fopen(duongDan.c_str(), "rb");
    if (!f) return false;
    noiDung.clear();
    char buf[65536];
    size_t n;
    while ((n = std::fread(buf, 1, sizeof(buf), f)) > 0) noiDung.append(buf, n);
    std::fclose(f);
    return true;
}

bool ghiTep(const std::string& duongDan, const std::string& noiDung) {
    FILE* f = std::fopen(duongDan.c_str(), "wb");
    if (!f) return false;
    size_t w = noiDung.empty() ? 0 : std::fwrite(noiDung.data(), 1, noiDung.size(), f);
    std::fclose(f);
    return w == noiDung.size();
}

bool tepTonTai(const std::string& duongDan) {
    struct stat st;
    return ::stat(duongDan.c_str(), &st) == 0;
}

bool taoThuMuc(const std::string& duongDan) {
    if (duongDan.empty()) return false;
    if (tepTonTai(duongDan)) return true;
#ifdef _WIN32
    return _mkdir(duongDan.c_str()) == 0;
#else
    return ::mkdir(duongDan.c_str(), 0755) == 0;
#endif
}

std::string thuMucCuaTep(const std::string& duongDan) {
    size_t p = duongDan.find_last_of("/\\");
    if (p == std::string::npos) return ".";
    return duongDan.substr(0, p);
}

std::string thuMucChuongTrinh() {
#ifdef _WIN32
    char buf[MAX_PATH];
    DWORD n = GetModuleFileNameA(NULL, buf, MAX_PATH);
    if (n == 0) return ".";
    return thuMucCuaTep(std::string(buf, n));
#else
    char buf[PATH_MAX];
    ssize_t n = ::readlink("/proc/self/exe", buf, sizeof(buf) - 1);
    if (n <= 0) return ".";
    buf[n] = 0;
    return thuMucCuaTep(std::string(buf));
#endif
}

std::string tenMayChu() {
#ifdef _WIN32
    char buf[256];
    DWORD sz = sizeof(buf);
    if (GetComputerNameA(buf, &sz)) return std::string(buf, sz);
    return "windows";
#else
    char buf[256] = {0};
    if (::gethostname(buf, sizeof(buf) - 1) == 0) return std::string(buf);
    return "linux";
#endif
}

std::string phanMoRong(const std::string& tenTep) {
    size_t p = tenTep.find_last_of('.');
    if (p == std::string::npos || p + 1 >= tenTep.size()) return "";
    std::string e = toLower(tenTep.substr(p + 1));
    if (e.size() > 10) return "";
    return e;
}

std::string doanMimeTuTen(const std::string& tenTep) {
    std::string e = phanMoRong(tenTep);
    if (e == "pdf")  return "application/pdf";
    if (e == "doc")  return "application/msword";
    if (e == "docx") return "application/vnd.openxmlformats-officedocument.wordprocessingml.document";
    if (e == "xls")  return "application/vnd.ms-excel";
    if (e == "xlsx") return "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";
    if (e == "ppt")  return "application/vnd.ms-powerpoint";
    if (e == "pptx") return "application/vnd.openxmlformats-officedocument.presentationml.presentation";
    if (e == "png")  return "image/png";
    if (e == "jpg" || e == "jpeg") return "image/jpeg";
    if (e == "gif")  return "image/gif";
    if (e == "webp") return "image/webp";
    if (e == "svg")  return "image/svg+xml";
    if (e == "txt")  return "text/plain; charset=utf-8";
    if (e == "csv")  return "text/csv; charset=utf-8";
    if (e == "html" || e == "htm") return "text/html; charset=utf-8";
    if (e == "css")  return "text/css; charset=utf-8";
    if (e == "js")   return "application/javascript; charset=utf-8";
    if (e == "json") return "application/json; charset=utf-8";
    if (e == "zip")  return "application/zip";
    if (e == "rar")  return "application/vnd.rar";
    if (e == "7z")   return "application/x-7z-compressed";
    if (e == "ico")  return "image/x-icon";
    if (e == "woff2") return "font/woff2";
    return "application/octet-stream";
}

void nguGiay(int giay) { std::this_thread::sleep_for(std::chrono::seconds(giay)); }
void nguMiliGiay(int ms) { std::this_thread::sleep_for(std::chrono::milliseconds(ms)); }

bool moTrinhDuyet(const std::string& url) {
#ifdef _WIN32
    HINSTANCE r = ShellExecuteA(NULL, "open", url.c_str(), NULL, NULL, SW_SHOWNORMAL);
    return (INT_PTR)r > 32;
#else
    std::string cmd = "xdg-open '" + replaceAll(url, "'", "'\\''") + "' >/dev/null 2>&1 &";
    return std::system(cmd.c_str()) == 0;
#endif
}

std::string urlEncode(const std::string& s) {
    std::string r;
    char buf[8];
    for (unsigned char c : s) {
        if (std::isalnum(c) || c == '-' || c == '_' || c == '.' || c == '~') r += char(c);
        else { std::snprintf(buf, sizeof(buf), "%%%02X", c); r += buf; }
    }
    return r;
}

std::string urlDecode(const std::string& s) {
    std::string r;
    for (size_t i = 0; i < s.size(); i++) {
        if (s[i] == '+') r += ' ';
        else if (s[i] == '%' && i + 2 < s.size()) {
            auto val = [](char c) -> int {
                if (c >= '0' && c <= '9') return c - '0';
                if (c >= 'a' && c <= 'f') return c - 'a' + 10;
                if (c >= 'A' && c <= 'F') return c - 'A' + 10;
                return -1;
            };
            int hi = val(s[i + 1]), lo = val(s[i + 2]);
            if (hi >= 0 && lo >= 0) { r += char((hi << 4) | lo); i += 2; }
            else r += s[i];
        } else r += s[i];
    }
    return r;
}

} // namespace mr
