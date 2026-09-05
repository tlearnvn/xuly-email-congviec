// =====================================================================
//  json.cpp - Phân tích / kết xuất JSON
// =====================================================================
#include "json.h"

#include <cstdio>
#include <cstdlib>
#include <cmath>
#include <cstring>
#include <cctype>

namespace mr {

static const Json g_rong;

const Json& Json::rong() { return g_rong; }

// ---------------------------------------------------------------------
//  Truy xuất
// ---------------------------------------------------------------------
std::string Json::chuoi(const std::string& macDinh) const {
    switch (kieu_) {
        case CHUOI: return chuoi_;
        case SO: {
            char buf[64];
            if (so_ == (long long)so_) std::snprintf(buf, sizeof(buf), "%lld", (long long)so_);
            else std::snprintf(buf, sizeof(buf), "%.10g", so_);
            return buf;
        }
        case LOGIC: return logic_ ? "1" : "0";
        default: return macDinh;
    }
}

double Json::so(double macDinh) const {
    switch (kieu_) {
        case SO: return so_;
        case LOGIC: return logic_ ? 1.0 : 0.0;
        case CHUOI: {
            if (chuoi_.empty()) return macDinh;
            char* end = nullptr;
            double v = std::strtod(chuoi_.c_str(), &end);
            if (end == chuoi_.c_str()) return macDinh;
            return v;
        }
        default: return macDinh;
    }
}

long long Json::nguyen(long long macDinh) const {
    if (kieu_ == RONG) return macDinh;
    double d = so((double)macDinh);
    return (long long)d;
}

bool Json::logic(bool macDinh) const {
    switch (kieu_) {
        case LOGIC: return logic_;
        case SO: return so_ != 0;
        case CHUOI: {
            if (chuoi_ == "1" || chuoi_ == "true" || chuoi_ == "TRUE" || chuoi_ == "yes") return true;
            if (chuoi_ == "0" || chuoi_ == "false" || chuoi_ == "FALSE" || chuoi_ == "no" || chuoi_.empty()) return false;
            return macDinh;
        }
        default: return macDinh;
    }
}

size_t Json::soPhanTu() const {
    if (kieu_ == MANG) return mang_.size();
    if (kieu_ == DOI_TUONG) return doiTuong_.size();
    return 0;
}

const Json& Json::phanTu(size_t i) const {
    if (kieu_ == MANG && i < mang_.size()) return mang_[i];
    return g_rong;
}

bool Json::coKhoa(const std::string& k) const {
    if (kieu_ != DOI_TUONG) return false;
    for (const auto& p : doiTuong_) if (p.first == k) return true;
    return false;
}

const Json& Json::lay(const std::string& k) const {
    if (kieu_ != DOI_TUONG) return g_rong;
    for (const auto& p : doiTuong_) if (p.first == k) return p.second;
    return g_rong;
}

const Json& Json::duongDan(const std::string& p) const {
    const Json* cur = this;
    size_t i = 0;
    while (i < p.size()) {
        size_t j = p.find('.', i);
        std::string seg = (j == std::string::npos) ? p.substr(i) : p.substr(i, j - i);
        if (seg.empty()) return g_rong;
        if (cur->kieu_ == MANG) {
            bool soNguyen = true;
            for (char c : seg) if (!std::isdigit((unsigned char)c)) { soNguyen = false; break; }
            if (!soNguyen) return g_rong;
            size_t idx = (size_t)std::strtoul(seg.c_str(), nullptr, 10);
            if (idx >= cur->mang_.size()) return g_rong;
            cur = &cur->mang_[idx];
        } else if (cur->kieu_ == DOI_TUONG) {
            const Json* found = nullptr;
            for (const auto& kv : cur->doiTuong_) if (kv.first == seg) { found = &kv.second; break; }
            if (!found) return g_rong;
            cur = found;
        } else return g_rong;
        if (j == std::string::npos) break;
        i = j + 1;
    }
    return *cur;
}

std::vector<std::string> Json::danhSachKhoa() const {
    std::vector<std::string> r;
    if (kieu_ == DOI_TUONG) for (const auto& p : doiTuong_) r.push_back(p.first);
    return r;
}

void Json::them(const Json& v) {
    if (kieu_ != MANG) { kieu_ = MANG; mang_.clear(); }
    mang_.push_back(v);
}

void Json::dat(const std::string& k, const Json& v) {
    if (kieu_ != DOI_TUONG) { kieu_ = DOI_TUONG; doiTuong_.clear(); }
    for (auto& p : doiTuong_) if (p.first == k) { p.second = v; return; }
    doiTuong_.push_back(std::make_pair(k, v));
}

void Json::xoaKhoa(const std::string& k) {
    if (kieu_ != DOI_TUONG) return;
    for (size_t i = 0; i < doiTuong_.size(); i++)
        if (doiTuong_[i].first == k) { doiTuong_.erase(doiTuong_.begin() + i); return; }
}

// ---------------------------------------------------------------------
//  Kết xuất
// ---------------------------------------------------------------------
std::string Json::thoatChuoi(const std::string& s) {
    std::string o;
    o.reserve(s.size() + 8);
    o += '"';
    for (size_t i = 0; i < s.size(); i++) {
        unsigned char c = (unsigned char)s[i];
        switch (c) {
            case '"':  o += "\\\""; break;
            case '\\': o += "\\\\"; break;
            case '\b': o += "\\b";  break;
            case '\f': o += "\\f";  break;
            case '\n': o += "\\n";  break;
            case '\r': o += "\\r";  break;
            case '\t': o += "\\t";  break;
            default:
                if (c < 0x20) {
                    char buf[8];
                    std::snprintf(buf, sizeof(buf), "\\u%04x", c);
                    o += buf;
                } else o += (char)c;
        }
    }
    o += '"';
    return o;
}

void Json::ketXuatVao(std::string& out, int indent, int muc) const {
    auto xuongDong = [&](int m) {
        if (indent < 0) return;
        out += '\n';
        out.append((size_t)(indent * m), ' ');
    };
    switch (kieu_) {
        case RONG: out += "null"; break;
        case LOGIC: out += logic_ ? "true" : "false"; break;
        case SO: {
            char buf[64];
            if (std::isnan(so_) || std::isinf(so_)) out += "0";
            else if (so_ == (long long)so_ && so_ < 9.0e15 && so_ > -9.0e15)
                { std::snprintf(buf, sizeof(buf), "%lld", (long long)so_); out += buf; }
            else {
                // Chọn dạng ngắn nhất mà vẫn khôi phục đúng giá trị
                std::snprintf(buf, sizeof(buf), "%.15g", so_);
                if (std::strtod(buf, nullptr) != so_) std::snprintf(buf, sizeof(buf), "%.17g", so_);
                out += buf;
            }
            break;
        }
        case CHUOI: out += thoatChuoi(chuoi_); break;
        case MANG: {
            if (mang_.empty()) { out += "[]"; break; }
            out += '[';
            for (size_t i = 0; i < mang_.size(); i++) {
                if (i) out += ',';
                xuongDong(muc + 1);
                mang_[i].ketXuatVao(out, indent, muc + 1);
            }
            xuongDong(muc);
            out += ']';
            break;
        }
        case DOI_TUONG: {
            if (doiTuong_.empty()) { out += "{}"; break; }
            out += '{';
            for (size_t i = 0; i < doiTuong_.size(); i++) {
                if (i) out += ',';
                xuongDong(muc + 1);
                out += thoatChuoi(doiTuong_[i].first);
                out += ':';
                if (indent >= 0) out += ' ';
                doiTuong_[i].second.ketXuatVao(out, indent, muc + 1);
            }
            xuongDong(muc);
            out += '}';
            break;
        }
    }
}

std::string Json::ketXuat(int indent) const {
    std::string out;
    ketXuatVao(out, indent, 0);
    return out;
}

// ---------------------------------------------------------------------
//  Phân tích
// ---------------------------------------------------------------------
namespace {

struct BoPhanTich {
    const char* p;
    const char* end;
    std::string loi;

    void boTrang() {
        while (p < end) {
            char c = *p;
            if (c == ' ' || c == '\t' || c == '\n' || c == '\r') { p++; continue; }
            // hỗ trợ chú thích // và /* */ (một số API trả về)
            if (c == '/' && p + 1 < end) {
                if (p[1] == '/') { p += 2; while (p < end && *p != '\n') p++; continue; }
                if (p[1] == '*') { p += 2; while (p + 1 < end && !(p[0] == '*' && p[1] == '/')) p++; p = (p + 1 < end) ? p + 2 : end; continue; }
            }
            break;
        }
    }

    bool datLoi(const std::string& m) { if (loi.empty()) loi = m; return false; }

    bool giaTri(Json& out, int depth) {
        if (depth > 200) return datLoi("JSON lồng quá sâu");
        boTrang();
        if (p >= end) return datLoi("Kết thúc bất ngờ");
        char c = *p;
        if (c == '{') return doiTuong(out, depth);
        if (c == '[') return mang(out, depth);
        if (c == '"') {
            std::string s;
            if (!chuoi(s)) return false;
            out = Json(s);
            return true;
        }
        if (c == 't') {
            if (end - p >= 4 && std::memcmp(p, "true", 4) == 0) { p += 4; out = Json(true); return true; }
            return datLoi("Ký tự không hợp lệ");
        }
        if (c == 'f') {
            if (end - p >= 5 && std::memcmp(p, "false", 5) == 0) { p += 5; out = Json(false); return true; }
            return datLoi("Ký tự không hợp lệ");
        }
        if (c == 'n') {
            if (end - p >= 4 && std::memcmp(p, "null", 4) == 0) { p += 4; out = Json(); return true; }
            return datLoi("Ký tự không hợp lệ");
        }
        // số
        const char* s0 = p;
        if (*p == '-' || *p == '+') p++;
        bool coSo = false;
        while (p < end && std::isdigit((unsigned char)*p)) { p++; coSo = true; }
        if (p < end && *p == '.') { p++; while (p < end && std::isdigit((unsigned char)*p)) { p++; coSo = true; } }
        if (coSo && p < end && (*p == 'e' || *p == 'E')) {
            p++;
            if (p < end && (*p == '+' || *p == '-')) p++;
            while (p < end && std::isdigit((unsigned char)*p)) p++;
        }
        if (!coSo) return datLoi("Giá trị JSON không hợp lệ");
        out = Json(std::strtod(std::string(s0, p - s0).c_str(), nullptr));
        return true;
    }

    bool chuoi(std::string& out) {
        if (p >= end || *p != '"') return datLoi("Thiếu dấu nháy kép");
        p++;
        out.clear();
        while (p < end) {
            unsigned char c = (unsigned char)*p;
            if (c == '"') { p++; return true; }
            if (c == '\\') {
                p++;
                if (p >= end) return datLoi("Chuỗi chưa đóng");
                char e = *p++;
                switch (e) {
                    case '"':  out += '"';  break;
                    case '\\': out += '\\'; break;
                    case '/':  out += '/';  break;
                    case 'b':  out += '\b'; break;
                    case 'f':  out += '\f'; break;
                    case 'n':  out += '\n'; break;
                    case 'r':  out += '\r'; break;
                    case 't':  out += '\t'; break;
                    case 'u': {
                        if (end - p < 4) return datLoi("Escape \\u không hợp lệ");
                        unsigned cp = (unsigned)std::strtoul(std::string(p, 4).c_str(), nullptr, 16);
                        p += 4;
                        if (cp >= 0xD800 && cp <= 0xDBFF && end - p >= 6 && p[0] == '\\' && p[1] == 'u') {
                            unsigned lo = (unsigned)std::strtoul(std::string(p + 2, 4).c_str(), nullptr, 16);
                            if (lo >= 0xDC00 && lo <= 0xDFFF) {
                                cp = 0x10000 + ((cp - 0xD800) << 10) + (lo - 0xDC00);
                                p += 6;
                            }
                        }
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
                        break;
                    }
                    default: out += e; break;
                }
                continue;
            }
            out += (char)c;
            p++;
        }
        return datLoi("Chuỗi chưa đóng");
    }

    bool mang(Json& out, int depth) {
        p++;                       // '['
        out = Json::mang();
        boTrang();
        if (p < end && *p == ']') { p++; return true; }
        while (p < end) {
            Json v;
            if (!giaTri(v, depth + 1)) return false;
            out.them(v);
            boTrang();
            if (p < end && *p == ',') { p++; continue; }
            if (p < end && *p == ']') { p++; return true; }
            return datLoi("Mảng JSON thiếu dấu , hoặc ]");
        }
        return datLoi("Mảng chưa đóng");
    }

    bool doiTuong(Json& out, int depth) {
        p++;                       // '{'
        out = Json::doiTuong();
        boTrang();
        if (p < end && *p == '}') { p++; return true; }
        while (p < end) {
            boTrang();
            std::string k;
            if (!chuoi(k)) return false;
            boTrang();
            if (p >= end || *p != ':') return datLoi("Thiếu dấu :");
            p++;
            Json v;
            if (!giaTri(v, depth + 1)) return false;
            out.dat(k, v);
            boTrang();
            if (p < end && *p == ',') { p++; continue; }
            if (p < end && *p == '}') { p++; return true; }
            return datLoi("Đối tượng JSON thiếu dấu , hoặc }");
        }
        return datLoi("Đối tượng chưa đóng");
    }
};

} // namespace

bool Json::phanTich(const std::string& vanBan, Json& ketQua, std::string* loi) {
    BoPhanTich bp;
    bp.p = vanBan.data();
    bp.end = vanBan.data() + vanBan.size();
    // Bỏ BOM UTF-8 nếu có
    if (vanBan.size() >= 3 && (unsigned char)vanBan[0] == 0xEF &&
        (unsigned char)vanBan[1] == 0xBB && (unsigned char)vanBan[2] == 0xBF) bp.p += 3;
    Json tmp;
    if (!bp.giaTri(tmp, 0)) {
        if (loi) *loi = bp.loi.empty() ? "JSON không hợp lệ" : bp.loi;
        return false;
    }
    ketQua = tmp;
    return true;
}

Json Json::phanTich(const std::string& vanBan) {
    Json j;
    phanTich(vanBan, j, nullptr);
    return j;
}

} // namespace mr
