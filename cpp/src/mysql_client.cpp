// =====================================================================
//  mysql_client.cpp - Trình khách MySQL/MariaDB thuần C++
// =====================================================================
#include "mysql_client.h"
#include "crypto.h"
#include "util.h"

#include <cstring>
#include <cstdio>
#include <cstdlib>

namespace mr {

// ---------------------------------------------------------------------
//  Cờ khả năng (capability flags)
// ---------------------------------------------------------------------
enum : uint32_t {
    CLIENT_LONG_PASSWORD    = 0x00000001,
    CLIENT_FOUND_ROWS       = 0x00000002,
    CLIENT_LONG_FLAG        = 0x00000004,
    CLIENT_CONNECT_WITH_DB  = 0x00000008,
    CLIENT_LOCAL_FILES      = 0x00000080,
    CLIENT_PROTOCOL_41      = 0x00000200,
    CLIENT_SSL              = 0x00000800,
    CLIENT_TRANSACTIONS     = 0x00002000,
    CLIENT_SECURE_CONNECTION= 0x00008000,
    CLIENT_MULTI_RESULTS    = 0x00020000,
    CLIENT_PS_MULTI_RESULTS = 0x00040000,
    CLIENT_PLUGIN_AUTH      = 0x00080000,
    CLIENT_CONNECT_ATTRS    = 0x00100000,
    CLIENT_PLUGIN_AUTH_LENENC_CLIENT_DATA = 0x00200000,
    CLIENT_SESSION_TRACK    = 0x00800000,
    CLIENT_DEPRECATE_EOF    = 0x01000000
};

static const size_t GOI_TOI_DA = 0xFFFFFF;      // 16MB - 1

// ---------------------------------------------------------------------
//  Đọc/ghi kiểu dữ liệu trong gói
// ---------------------------------------------------------------------
namespace {

struct DocGoi {
    const std::string& b;
    size_t p = 0;
    explicit DocGoi(const std::string& s) : b(s) {}

    bool con(size_t n = 1) const { return p + n <= b.size(); }
    uint8_t u8() { return con() ? (uint8_t)b[p++] : 0; }
    uint16_t u16() {
        if (!con(2)) { p = b.size(); return 0; }
        uint16_t v = (uint8_t)b[p] | ((uint16_t)(uint8_t)b[p + 1] << 8);
        p += 2; return v;
    }
    uint32_t u24() {
        if (!con(3)) { p = b.size(); return 0; }
        uint32_t v = (uint8_t)b[p] | ((uint32_t)(uint8_t)b[p+1] << 8) | ((uint32_t)(uint8_t)b[p+2] << 16);
        p += 3; return v;
    }
    uint32_t u32() {
        if (!con(4)) { p = b.size(); return 0; }
        uint32_t v = (uint8_t)b[p] | ((uint32_t)(uint8_t)b[p+1] << 8) |
                     ((uint32_t)(uint8_t)b[p+2] << 16) | ((uint32_t)(uint8_t)b[p+3] << 24);
        p += 4; return v;
    }
    uint64_t u64() {
        uint64_t v = 0;
        for (int i = 0; i < 8; i++) v |= (uint64_t)(uint8_t)(con() ? b[p++] : 0) << (i * 8);
        return v;
    }
    std::string chuoiNUL() {
        std::string r;
        while (p < b.size() && b[p] != '\0') r += b[p++];
        if (p < b.size()) p++;                 // bỏ NUL
        return r;
    }
    std::string coDinh(size_t n) {
        if (!con(n)) n = (p < b.size()) ? (b.size() - p) : 0;
        std::string r = b.substr(p, n);
        p += n;
        return r;
    }
    // Số nguyên mã hoá theo độ dài. laNull = true khi gặp 0xFB.
    uint64_t lenenc(bool* laNull = nullptr) {
        if (laNull) *laNull = false;
        if (!con()) return 0;
        uint8_t c = (uint8_t)b[p++];
        if (c < 0xFB) return c;
        if (c == 0xFB) { if (laNull) *laNull = true; return 0; }
        if (c == 0xFC) return u16();
        if (c == 0xFD) return u24();
        if (c == 0xFE) return u64();
        return 0;
    }
    std::string chuoiLenenc(bool* laNull = nullptr) {
        bool n = false;
        uint64_t len = lenenc(&n);
        if (laNull) *laNull = n;
        if (n) return "";
        return coDinh((size_t)len);
    }
    std::string conLai() { return coDinh(b.size() - p); }
};

struct GhiGoi {
    std::string b;
    void u8(uint8_t v)  { b += (char)v; }
    void u16(uint16_t v){ b += (char)(v & 0xFF); b += (char)((v >> 8) & 0xFF); }
    void u32(uint32_t v){ for (int i = 0; i < 4; i++) b += (char)((v >> (i * 8)) & 0xFF); }
    void bytes(const std::string& s) { b += s; }
    void chuoiNUL(const std::string& s) { b += s; b += '\0'; }
    void lenenc(uint64_t v) {
        if (v < 0xFB) u8((uint8_t)v);
        else if (v <= 0xFFFF) { u8(0xFC); u16((uint16_t)v); }
        else if (v <= 0xFFFFFF) { u8(0xFD); for (int i = 0; i < 3; i++) b += (char)((v >> (i * 8)) & 0xFF); }
        else { u8(0xFE); for (int i = 0; i < 8; i++) b += (char)((v >> (i * 8)) & 0xFF); }
    }
    void chuoiLenenc(const std::string& s) { lenenc(s.size()); b += s; }
    void dem(size_t n) { b.append(n, '\0'); }
};

std::string vecToStr(const std::vector<uint8_t>& v) {
    return std::string((const char*)v.data(), v.size());
}

} // namespace

// ---------------------------------------------------------------------
//  MySqlKetQua
// ---------------------------------------------------------------------
int MySqlKetQua::viTriCot(const std::string& ten) const {
    for (size_t i = 0; i < cot.size(); i++) if (cot[i] == ten) return (int)i;
    return -1;
}

std::string MySqlKetQua::lay(size_t i, const std::string& tenCot, const std::string& macDinh) const {
    if (i >= dong.size()) return macDinh;
    int c = viTriCot(tenCot);
    if (c < 0 || (size_t)c >= dong[i].o.size()) return macDinh;
    if ((size_t)c < dong[i].laNull.size() && dong[i].laNull[c]) return macDinh;
    return dong[i].o[c];
}

long long MySqlKetQua::laySo(size_t i, const std::string& tenCot, long long macDinh) const {
    std::string v = lay(i, tenCot, "");
    if (v.empty()) return macDinh;
    return toLL(v, macDinh);
}

bool MySqlKetQua::laNull(size_t i, const std::string& tenCot) const {
    if (i >= dong.size()) return true;
    int c = viTriCot(tenCot);
    if (c < 0 || (size_t)c >= dong[i].laNull.size()) return true;
    return dong[i].laNull[c];
}

// ---------------------------------------------------------------------
//  MySql
// ---------------------------------------------------------------------
MySql::MySql() { netKhoiTao(); }
MySql::~MySql() { dong(); }

void MySql::dong() {
    if (sock_ != MR_SOCK_INVALID) {
        // COM_QUIT
        std::string loi;
        seq_ = 0;
        std::string q;
        q += (char)0x01;
        guiGoi(q, loi);
        netDong(sock_);
        sock_ = MR_SOCK_INVALID;
    }
}

std::string MySql::thoat(const std::string& s) {
    std::string r;
    r.reserve(s.size() + 16);
    for (unsigned char c : s) {
        switch (c) {
            case '\0': r += "\\0";   break;
            case '\n': r += "\\n";   break;
            case '\r': r += "\\r";   break;
            case '\\': r += "\\\\";  break;
            case '\'': r += "\\'";   break;
            case '"':  r += "\\\"";  break;
            case 0x1A: r += "\\Z";   break;
            default:   r += (char)c;
        }
    }
    return r;
}

std::string MySql::nhay(const std::string& s) { return "'" + thoat(s) + "'"; }

std::string MySql::hexBlob(const std::string& d) {
    if (d.empty()) return "''";
    static const char* H = "0123456789ABCDEF";
    std::string r;
    r.reserve(d.size() * 2 + 4);
    r += "X'";
    for (unsigned char c : d) { r += H[c >> 4]; r += H[c & 0xF]; }
    r += "'";
    return r;
}

// --------------------------- Tầng gói tin ----------------------------
bool MySql::guiGoi(const std::string& payload, std::string& loi) {
    size_t off = 0;
    bool canGoiRong = false;               // payload dài đúng bội số 16MB-1 -> phải chốt bằng gói rỗng
    for (;;) {
        size_t n = payload.size() - off;
        if (n > GOI_TOI_DA) n = GOI_TOI_DA;
        char hdr[4];
        hdr[0] = (char)(n & 0xFF);
        hdr[1] = (char)((n >> 8) & 0xFF);
        hdr[2] = (char)((n >> 16) & 0xFF);
        hdr[3] = (char)seq_++;
        if (!netGuiHet(sock_, hdr, 4) ||
            (n > 0 && !netGuiHet(sock_, payload.data() + off, n))) {
            loi = "Mất kết nối khi gửi dữ liệu tới MySQL (" + netLoiCuoi() + ")";
            return false;
        }
        off += n;
        canGoiRong = (n == GOI_TOI_DA);
        if (off >= payload.size() && !canGoiRong) break;
    }
    return true;
}

bool MySql::nhanGoi(std::string& payload, std::string& loi) {
    payload.clear();
    for (;;) {
        char hdr[4];
        if (!netNhanHet(sock_, hdr, 4)) {
            loi = "Mất kết nối khi đọc dữ liệu từ MySQL (" + netLoiCuoi() + ")";
            return false;
        }
        size_t n = (size_t)(uint8_t)hdr[0] | ((size_t)(uint8_t)hdr[1] << 8) | ((size_t)(uint8_t)hdr[2] << 16);
        seq_ = (uint8_t)hdr[3] + 1;
        if (n > 0) {
            size_t base = payload.size();
            payload.resize(base + n);
            if (!netNhanHet(sock_, &payload[base], n)) {
                loi = "Mất kết nối khi đọc thân gói MySQL";
                return false;
            }
        }
        if (n < GOI_TOI_DA) break;      // gói cuối
    }
    return true;
}

bool MySql::doiLoi(const std::string& goi, std::string& loi) {
    DocGoi d(goi);
    d.u8();                              // 0xFF
    uint16_t ma = d.u16();
    std::string sqlstate;
    if (d.con() && goi[d.p] == '#') { d.u8(); sqlstate = d.coDinh(5); }
    std::string tb = d.conLai();
    char buf[32];
    std::snprintf(buf, sizeof(buf), "%u", (unsigned)ma);
    loi = "MySQL lỗi " + std::string(buf);
    if (!sqlstate.empty()) loi += " (" + sqlstate + ")";
    loi += ": " + tb;
    return false;
}

// --------------------------- Bắt tay ---------------------------------
static std::string scrambleNativePassword(const std::string& matKhau, const std::string& scramble) {
    if (matKhau.empty()) return "";
    auto stage1 = sha1(matKhau);                        // SHA1(password)
    auto stage2 = sha1(vecToStr(stage1));               // SHA1(SHA1(password))
    auto stage3 = sha1(scramble + vecToStr(stage2));    // SHA1(scramble + stage2)
    std::string r;
    r.resize(20);
    for (int i = 0; i < 20; i++) r[i] = (char)(stage1[i] ^ stage3[i]);
    return r;
}

static std::string scrambleCachingSha2(const std::string& matKhau, const std::string& scramble) {
    if (matKhau.empty()) return "";
    auto d1 = sha256(matKhau);                          // SHA256(password)
    auto d2 = sha256(vecToStr(d1));                     // SHA256(SHA256(password))
    auto d3 = sha256(vecToStr(d2) + scramble);          // SHA256(d2 + scramble)
    std::string r;
    r.resize(32);
    for (int i = 0; i < 32; i++) r[i] = (char)(d1[i] ^ d3[i]);
    return r;
}

bool MySql::bacTay(std::string& loi) {
    std::string goi;
    if (!nhanGoi(goi, loi)) return false;
    if (goi.empty()) { loi = "Máy chủ MySQL trả về gói rỗng"; return false; }
    if ((uint8_t)goi[0] == 0xFF) return doiLoi(goi, loi);

    DocGoi d(goi);
    uint8_t phienBanGiaoThuc = d.u8();
    if (phienBanGiaoThuc != 10) {
        loi = "Phiên bản giao thức MySQL không hỗ trợ (yêu cầu 10)";
        return false;
    }
    phienBanMayChu_ = d.chuoiNUL();
    d.u32();                                    // connection id
    std::string scramble = d.coDinh(8);
    d.u8();                                     // filler
    uint32_t capLo = d.u16();
    uint32_t capHi = 0;
    uint8_t authDataLen = 0;
    if (d.con()) {
        d.u8();                                 // charset
        d.u16();                                // status flags
        capHi = d.u16();
        authDataLen = d.u8();
        d.coDinh(10);                           // reserved
    }
    capabilitiesMayChu_ = capLo | (capHi << 16);

    std::string tenPlugin = "mysql_native_password";
    if (capabilitiesMayChu_ & CLIENT_SECURE_CONNECTION) {
        size_t len = (authDataLen > 8) ? (size_t)(authDataLen - 8) : 12;
        if (len < 12) len = 12;
        std::string p2 = d.coDinh(len);
        // bỏ NUL cuối nếu có
        while (!p2.empty() && p2.back() == '\0') p2.pop_back();
        scramble += p2;
    }
    if (capabilitiesMayChu_ & CLIENT_PLUGIN_AUTH) {
        std::string t = d.chuoiNUL();
        if (!t.empty()) tenPlugin = t;
    }
    if (scramble.size() > 20) scramble = scramble.substr(0, 20);

    // Chọn khả năng
    uint32_t cap = CLIENT_LONG_PASSWORD | CLIENT_LONG_FLAG | CLIENT_PROTOCOL_41 |
                   CLIENT_TRANSACTIONS | CLIENT_SECURE_CONNECTION | CLIENT_MULTI_RESULTS |
                   CLIENT_PS_MULTI_RESULTS | CLIENT_PLUGIN_AUTH;
    if (!ts_.co_so_du_lieu.empty()) cap |= CLIENT_CONNECT_WITH_DB;
    cap &= (capabilitiesMayChu_ | CLIENT_LONG_PASSWORD | CLIENT_PROTOCOL_41 | CLIENT_SECURE_CONNECTION);
    capabilitiesDaChon_ = cap;

    // Sinh chuỗi xác thực
    std::string authResp;
    std::string pluginDung = tenPlugin;
    if (tenPlugin == "caching_sha2_password") authResp = scrambleCachingSha2(ts_.mat_khau, scramble);
    else { pluginDung = "mysql_native_password"; authResp = scrambleNativePassword(ts_.mat_khau, scramble); }

    GhiGoi g;
    g.u32(cap);
    g.u32(64 * 1024 * 1024);                    // max packet size
    g.u8(45);                                   // utf8mb4_general_ci
    g.dem(23);
    g.chuoiNUL(ts_.nguoi_dung);
    g.u8((uint8_t)authResp.size());
    g.bytes(authResp);
    if (cap & CLIENT_CONNECT_WITH_DB) g.chuoiNUL(ts_.co_so_du_lieu);
    if (cap & CLIENT_PLUGIN_AUTH) g.chuoiNUL(pluginDung);

    if (!guiGoi(g.b, loi)) return false;

    // Vòng lặp xử lý phản hồi xác thực
    for (int vong = 0; vong < 8; vong++) {
        if (!nhanGoi(goi, loi)) return false;
        if (goi.empty()) { loi = "Máy chủ MySQL trả về gói rỗng khi xác thực"; return false; }
        uint8_t h = (uint8_t)goi[0];

        if (h == 0x00) return true;                                  // OK
        if (h == 0xFF) return doiLoi(goi, loi);

        if (h == 0xFE && goi.size() > 1) {                           // AuthSwitchRequest
            DocGoi ds(goi);
            ds.u8();
            std::string pl = ds.chuoiNUL();
            std::string sc = ds.conLai();
            while (!sc.empty() && sc.back() == '\0') sc.pop_back();
            if (sc.size() > 20) sc = sc.substr(0, 20);
            std::string resp;
            if (pl == "caching_sha2_password") resp = scrambleCachingSha2(ts_.mat_khau, sc);
            else if (pl == "mysql_native_password") resp = scrambleNativePassword(ts_.mat_khau, sc);
            else if (pl == "mysql_clear_password") resp = ts_.mat_khau + '\0';
            else {
                loi = "Máy chủ yêu cầu phương thức xác thực '" + pl + "' chưa được hỗ trợ. "
                      "Hãy đổi tài khoản sang mysql_native_password hoặc dùng chế độ API.";
                return false;
            }
            if (!guiGoi(resp, loi)) return false;
            continue;
        }

        if (h == 0x01 && goi.size() >= 2) {                          // AuthMoreData
            uint8_t ma = (uint8_t)goi[1];
            if (ma == 0x03) continue;                                // fast auth thành công -> chờ OK
            if (ma == 0x04) {
                if (ts_.mat_khau.empty()) {
                    std::string tr(1, '\0');
                    if (!guiGoi(tr, loi)) return false;
                    continue;
                }
                loi = "Tài khoản dùng caching_sha2_password và cần xác thực đầy đủ qua kênh mã hoá "
                      "(chưa hỗ trợ). Cách khắc phục: chạy trên máy chủ MySQL lệnh\n"
                      "  ALTER USER '" + ts_.nguoi_dung + "'@'%' IDENTIFIED WITH mysql_native_password BY '<mật khẩu>';\n"
                      "hoặc chuyển sang chế độ lưu trữ API (che_do_luu = api).";
                return false;
            }
            continue;
        }
        loi = "Phản hồi xác thực MySQL không nhận dạng được";
        return false;
    }
    loi = "Xác thực MySQL thất bại (quá nhiều bước)";
    return false;
}

bool MySql::khoiTaoPhien(std::string& loi) {
    if (!thucThi("SET NAMES " + ts_.bang_ma, loi)) return false;
    if (!thucThi("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'", loi)) return false;
    if (!thucThi("SET SESSION time_zone = '" + thoat(ts_.mui_gio) + "'", loi)) {
        // Một số máy chủ chưa nạp bảng múi giờ - bỏ qua, không coi là lỗi nặng
        loi.clear();
    }
    MySqlKetQua kq;
    std::string l2;
    if (truyVan("SHOW VARIABLES LIKE 'max_allowed_packet'", kq, l2) && kq.soDong() > 0) {
        long long v = toLL(kq.dong[0][1], 4 * 1024 * 1024);
        if (v > 0) maxAllowedPacket_ = v;
    }
    return true;
}

bool MySql::ketNoi(const MySqlThamSo& ts, std::string& loi) {
    dong();
    ts_ = ts;
    if (ts_.may_chu.empty()) { loi = "Chưa khai báo máy chủ MySQL"; return false; }
    sock_ = netKetNoi(ts_.may_chu, ts_.cong, ts_.timeout, loi);
    if (sock_ == MR_SOCK_INVALID) return false;
    seq_ = 0;
    if (!bacTay(loi)) { netDong(sock_); sock_ = MR_SOCK_INVALID; return false; }
    if (!khoiTaoPhien(loi)) { netDong(sock_); sock_ = MR_SOCK_INVALID; return false; }
    return true;
}

bool MySql::kiemTraSong(std::string& loi) {
    if (sock_ != MR_SOCK_INVALID) {
        seq_ = 0;
        std::string q;
        q += (char)0x0E;                     // COM_PING
        std::string goi;
        if (guiGoi(q, loi) && nhanGoi(goi, loi) && !goi.empty() && (uint8_t)goi[0] == 0x00) return true;
        netDong(sock_);
        sock_ = MR_SOCK_INVALID;
    }
    return ketNoi(ts_, loi);
}

// ------------------------ Xử lý kết quả lệnh --------------------------
bool MySql::xuLyKetQuaLenh(MySqlKetQua* kq, std::string& loi) {
    std::string goi;
    if (!nhanGoi(goi, loi)) return false;
    if (goi.empty()) { loi = "Máy chủ MySQL trả về gói rỗng"; return false; }

    uint8_t h = (uint8_t)goi[0];
    if (h == 0xFF) return doiLoi(goi, loi);

    if (h == 0x00 || (h == 0xFE && goi.size() < 9)) {   // OK / EOF
        DocGoi d(goi);
        d.u8();
        soDongAnhHuong_ = (long long)d.lenenc();
        idChenCuoi_ = (long long)d.lenenc();
        if (kq) { kq->soDongAnhHuong = soDongAnhHuong_; kq->idChen = idChenCuoi_; }
        return true;
    }
    if (h == 0xFB) { loi = "Máy chủ yêu cầu LOCAL INFILE - không hỗ trợ"; return false; }

    // Tập kết quả
    DocGoi dh(goi);
    uint64_t soCot = dh.lenenc();
    if (soCot == 0 || soCot > 4096) { loi = "Số cột trả về không hợp lệ"; return false; }

    std::vector<std::string> tenCot;
    for (uint64_t i = 0; i < soCot; i++) {
        if (!nhanGoi(goi, loi)) return false;
        if (!goi.empty() && (uint8_t)goi[0] == 0xFF) return doiLoi(goi, loi);
        DocGoi d(goi);
        d.chuoiLenenc();                    // catalog
        d.chuoiLenenc();                    // schema
        d.chuoiLenenc();                    // table
        d.chuoiLenenc();                    // org_table
        std::string ten = d.chuoiLenenc();  // name
        tenCot.push_back(ten);
    }
    // EOF sau phần mô tả cột (khi không bật DEPRECATE_EOF)
    if (!(capabilitiesDaChon_ & CLIENT_DEPRECATE_EOF)) {
        if (!nhanGoi(goi, loi)) return false;
        if (!goi.empty() && (uint8_t)goi[0] == 0xFF) return doiLoi(goi, loi);
    }

    if (kq) { kq->cot = tenCot; kq->dong.clear(); }

    for (;;) {
        if (!nhanGoi(goi, loi)) return false;
        if (goi.empty()) { loi = "Gói dòng dữ liệu rỗng"; return false; }
        uint8_t hh = (uint8_t)goi[0];
        if (hh == 0xFF) return doiLoi(goi, loi);
        if (hh == 0xFE && goi.size() < 0xFFFFFF && goi.size() <= 9) break;   // EOF/OK kết thúc

        DocGoi d(goi);
        MySqlDong dg;
        dg.o.reserve(soCot);
        dg.laNull.reserve(soCot);
        for (uint64_t i = 0; i < soCot; i++) {
            bool n = false;
            std::string v = d.chuoiLenenc(&n);
            dg.o.push_back(v);
            dg.laNull.push_back(n);
        }
        if (kq) kq->dong.push_back(std::move(dg));
    }
    return true;
}

bool MySql::thucThi(const std::string& sql, std::string& loi) {
    if (sock_ == MR_SOCK_INVALID) { loi = "Chưa kết nối MySQL"; return false; }
    if (sql.size() + 1 > (size_t)maxAllowedPacket_) {
        char buf[160];
        std::snprintf(buf, sizeof(buf),
            "Câu lệnh dài %.1f MB vượt giới hạn max_allowed_packet (%.1f MB) của máy chủ",
            sql.size() / 1048576.0, maxAllowedPacket_ / 1048576.0);
        loi = buf;
        return false;
    }
    seq_ = 0;
    std::string q;
    q.reserve(sql.size() + 1);
    q += (char)0x03;                        // COM_QUERY
    q += sql;
    if (!guiGoi(q, loi)) return false;
    return xuLyKetQuaLenh(nullptr, loi);
}

bool MySql::truyVan(const std::string& sql, MySqlKetQua& kq, std::string& loi) {
    if (sock_ == MR_SOCK_INVALID) { loi = "Chưa kết nối MySQL"; return false; }
    kq = MySqlKetQua();
    seq_ = 0;
    std::string q;
    q.reserve(sql.size() + 1);
    q += (char)0x03;
    q += sql;
    if (!guiGoi(q, loi)) return false;
    return xuLyKetQuaLenh(&kq, loi);
}

std::string MySql::layMotGiaTri(const std::string& sql, const std::string& macDinh) {
    MySqlKetQua kq;
    std::string loi;
    if (!truyVan(sql, kq, loi) || kq.dong.empty() || kq.dong[0].o.empty()) return macDinh;
    if (!kq.dong[0].laNull.empty() && kq.dong[0].laNull[0]) return macDinh;
    return kq.dong[0].o[0];
}

} // namespace mr
