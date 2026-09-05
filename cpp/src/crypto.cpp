// =====================================================================
//  crypto.cpp - Base64, SHA-1, SHA-256, quoted-printable
// =====================================================================
#include "crypto.h"

#include <cstring>
#include <cstdio>

namespace mr {

// =====================================================================
//  BASE64
// =====================================================================
static const char* B64_STD = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
static const char* B64_URL = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_";

std::string base64Encode(const std::string& d, bool urlSafe, bool dem) {
    const char* B = urlSafe ? B64_URL : B64_STD;
    std::string out;
    out.reserve(((d.size() + 2) / 3) * 4);
    size_t i = 0, n = d.size();
    while (i + 2 < n) {
        uint32_t v = ((uint32_t)(unsigned char)d[i] << 16) |
                     ((uint32_t)(unsigned char)d[i + 1] << 8) |
                     ((uint32_t)(unsigned char)d[i + 2]);
        out += B[(v >> 18) & 63];
        out += B[(v >> 12) & 63];
        out += B[(v >> 6) & 63];
        out += B[v & 63];
        i += 3;
    }
    if (i < n) {
        uint32_t v = (uint32_t)(unsigned char)d[i] << 16;
        bool coHai = (i + 1 < n);
        if (coHai) v |= (uint32_t)(unsigned char)d[i + 1] << 8;
        out += B[(v >> 18) & 63];
        out += B[(v >> 12) & 63];
        if (coHai) out += B[(v >> 6) & 63];
        else if (dem) out += '=';
        if (dem) out += '=';
    }
    return out;
}

std::string base64Decode(const std::string& s) {
    int8_t tbl[256];
    std::memset(tbl, -1, sizeof(tbl));
    for (int i = 0; i < 64; i++) tbl[(unsigned char)B64_STD[i]] = (int8_t)i;
    tbl[(unsigned char)'-'] = 62;      // base64url
    tbl[(unsigned char)'_'] = 63;

    std::string out;
    out.reserve(s.size() * 3 / 4 + 4);
    uint32_t buf = 0;
    int bits = 0;
    for (unsigned char c : s) {
        if (c == '=') break;
        int8_t v = tbl[c];
        if (v < 0) continue;           // bỏ qua xuống dòng, khoảng trắng, ký tự lạ
        buf = (buf << 6) | (uint32_t)v;
        bits += 6;
        if (bits >= 8) {
            bits -= 8;
            out += char((buf >> bits) & 0xFF);
        }
    }
    return out;
}

// =====================================================================
//  SHA-1
// =====================================================================
static inline uint32_t rol32(uint32_t v, int n) { return (v << n) | (v >> (32 - n)); }

std::vector<uint8_t> sha1(const std::string& du_lieu) {
    uint32_t h[5] = {0x67452301u, 0xEFCDAB89u, 0x98BADCFEu, 0x10325476u, 0xC3D2E1F0u};

    std::string msg = du_lieu;
    uint64_t ml = (uint64_t)du_lieu.size() * 8ull;
    msg += (char)0x80;
    while (msg.size() % 64 != 56) msg += (char)0x00;
    for (int i = 7; i >= 0; i--) msg += char((ml >> (i * 8)) & 0xFF);

    for (size_t off = 0; off < msg.size(); off += 64) {
        uint32_t w[80];
        for (int i = 0; i < 16; i++) {
            w[i] = ((uint32_t)(unsigned char)msg[off + i * 4] << 24) |
                   ((uint32_t)(unsigned char)msg[off + i * 4 + 1] << 16) |
                   ((uint32_t)(unsigned char)msg[off + i * 4 + 2] << 8) |
                   ((uint32_t)(unsigned char)msg[off + i * 4 + 3]);
        }
        for (int i = 16; i < 80; i++) w[i] = rol32(w[i - 3] ^ w[i - 8] ^ w[i - 14] ^ w[i - 16], 1);

        uint32_t a = h[0], b = h[1], c = h[2], d = h[3], e = h[4];
        for (int i = 0; i < 80; i++) {
            uint32_t f, k;
            if (i < 20)      { f = (b & c) | ((~b) & d);       k = 0x5A827999u; }
            else if (i < 40) { f = b ^ c ^ d;                  k = 0x6ED9EBA1u; }
            else if (i < 60) { f = (b & c) | (b & d) | (c & d); k = 0x8F1BBCDCu; }
            else             { f = b ^ c ^ d;                  k = 0xCA62C1D6u; }
            uint32_t t = rol32(a, 5) + f + e + k + w[i];
            e = d; d = c; c = rol32(b, 30); b = a; a = t;
        }
        h[0] += a; h[1] += b; h[2] += c; h[3] += d; h[4] += e;
    }

    std::vector<uint8_t> out(20);
    for (int i = 0; i < 5; i++) {
        out[i * 4]     = uint8_t((h[i] >> 24) & 0xFF);
        out[i * 4 + 1] = uint8_t((h[i] >> 16) & 0xFF);
        out[i * 4 + 2] = uint8_t((h[i] >> 8) & 0xFF);
        out[i * 4 + 3] = uint8_t(h[i] & 0xFF);
    }
    return out;
}

std::string sha1Hex(const std::string& d) {
    static const char* H = "0123456789abcdef";
    auto v = sha1(d);
    std::string r;
    for (uint8_t b : v) { r += H[b >> 4]; r += H[b & 0xF]; }
    return r;
}

// =====================================================================
//  SHA-256
// =====================================================================
static const uint32_t K256[64] = {
    0x428a2f98,0x71374491,0xb5c0fbcf,0xe9b5dba5,0x3956c25b,0x59f111f1,0x923f82a4,0xab1c5ed5,
    0xd807aa98,0x12835b01,0x243185be,0x550c7dc3,0x72be5d74,0x80deb1fe,0x9bdc06a7,0xc19bf174,
    0xe49b69c1,0xefbe4786,0x0fc19dc6,0x240ca1cc,0x2de92c6f,0x4a7484aa,0x5cb0a9dc,0x76f988da,
    0x983e5152,0xa831c66d,0xb00327c8,0xbf597fc7,0xc6e00bf3,0xd5a79147,0x06ca6351,0x14292967,
    0x27b70a85,0x2e1b2138,0x4d2c6dfc,0x53380d13,0x650a7354,0x766a0abb,0x81c2c92e,0x92722c85,
    0xa2bfe8a1,0xa81a664b,0xc24b8b70,0xc76c51a3,0xd192e819,0xd6990624,0xf40e3585,0x106aa070,
    0x19a4c116,0x1e376c08,0x2748774c,0x34b0bcb5,0x391c0cb3,0x4ed8aa4a,0x5b9cca4f,0x682e6ff3,
    0x748f82ee,0x78a5636f,0x84c87814,0x8cc70208,0x90befffa,0xa4506ceb,0xbef9a3f7,0xc67178f2
};

static inline uint32_t ror32(uint32_t v, int n) { return (v >> n) | (v << (32 - n)); }

static void sha256Khoi(uint32_t h[8], const uint8_t* p) {
    uint32_t w[64];
    for (int i = 0; i < 16; i++)
        w[i] = ((uint32_t)p[i * 4] << 24) | ((uint32_t)p[i * 4 + 1] << 16) |
               ((uint32_t)p[i * 4 + 2] << 8) | ((uint32_t)p[i * 4 + 3]);
    for (int i = 16; i < 64; i++) {
        uint32_t s0 = ror32(w[i - 15], 7) ^ ror32(w[i - 15], 18) ^ (w[i - 15] >> 3);
        uint32_t s1 = ror32(w[i - 2], 17) ^ ror32(w[i - 2], 19) ^ (w[i - 2] >> 10);
        w[i] = w[i - 16] + s0 + w[i - 7] + s1;
    }
    uint32_t a = h[0], b = h[1], c = h[2], d = h[3], e = h[4], f = h[5], g = h[6], hh = h[7];
    for (int i = 0; i < 64; i++) {
        uint32_t S1 = ror32(e, 6) ^ ror32(e, 11) ^ ror32(e, 25);
        uint32_t ch = (e & f) ^ ((~e) & g);
        uint32_t t1 = hh + S1 + ch + K256[i] + w[i];
        uint32_t S0 = ror32(a, 2) ^ ror32(a, 13) ^ ror32(a, 22);
        uint32_t maj = (a & b) ^ (a & c) ^ (b & c);
        uint32_t t2 = S0 + maj;
        hh = g; g = f; f = e; e = d + t1; d = c; c = b; b = a; a = t1 + t2;
    }
    h[0] += a; h[1] += b; h[2] += c; h[3] += d;
    h[4] += e; h[5] += f; h[6] += g; h[7] += hh;
}

Sha256Luong::Sha256Luong() {
    h_[0] = 0x6a09e667; h_[1] = 0xbb67ae85; h_[2] = 0x3c6ef372; h_[3] = 0xa54ff53a;
    h_[4] = 0x510e527f; h_[5] = 0x9b05688c; h_[6] = 0x1f83d9ab; h_[7] = 0x5be0cd19;
    tongBit_ = 0; nDem_ = 0; xong_ = false;
}

void Sha256Luong::napThem(const void* du_lieu, size_t n) {
    if (xong_) return;
    const uint8_t* p = (const uint8_t*)du_lieu;
    tongBit_ += (uint64_t)n * 8ull;
    while (n > 0) {
        size_t can = 64 - nDem_;
        size_t lay = (n < can) ? n : can;
        std::memcpy(dem_ + nDem_, p, lay);
        nDem_ += lay; p += lay; n -= lay;
        if (nDem_ == 64) { sha256Khoi(h_, dem_); nDem_ = 0; }
    }
}

std::vector<uint8_t> Sha256Luong::ketThuc() {
    if (!xong_) {
        uint8_t pad[72];
        size_t np = 0;
        pad[np++] = 0x80;
        size_t conLai = (nDem_ + 1) % 64;
        size_t them = (conLai <= 56) ? (56 - conLai) : (120 - conLai);
        for (size_t i = 0; i < them; i++) pad[np++] = 0x00;
        uint64_t bits = tongBit_;
        // nạp phần đệm (không đổi tổng bit)
        uint64_t luu = tongBit_;
        napThem(pad, np);
        tongBit_ = luu;
        uint8_t len[8];
        for (int i = 7; i >= 0; i--) { len[7 - i] = uint8_t((bits >> (i * 8)) & 0xFF); }
        napThem(len, 8);
        tongBit_ = luu;

        kq_.resize(32);
        for (int i = 0; i < 8; i++) {
            kq_[i * 4]     = uint8_t((h_[i] >> 24) & 0xFF);
            kq_[i * 4 + 1] = uint8_t((h_[i] >> 16) & 0xFF);
            kq_[i * 4 + 2] = uint8_t((h_[i] >> 8) & 0xFF);
            kq_[i * 4 + 3] = uint8_t(h_[i] & 0xFF);
        }
        xong_ = true;
    }
    return kq_;
}

std::string Sha256Luong::ketThucHex() {
    static const char* H = "0123456789abcdef";
    auto v = ketThuc();
    std::string r;
    for (uint8_t b : v) { r += H[b >> 4]; r += H[b & 0xF]; }
    return r;
}

std::vector<uint8_t> sha256(const std::string& d) {
    Sha256Luong s;
    s.napThem(d.data(), d.size());
    return s.ketThuc();
}

std::string sha256Hex(const std::string& d) {
    Sha256Luong s;
    s.napThem(d.data(), d.size());
    return s.ketThucHex();
}

// =====================================================================
//  Quoted-printable
// =====================================================================
std::string giaiMaQuotedPrintable(const std::string& s) {
    std::string out;
    out.reserve(s.size());
    auto hexVal = [](char c) -> int {
        if (c >= '0' && c <= '9') return c - '0';
        if (c >= 'A' && c <= 'F') return c - 'A' + 10;
        if (c >= 'a' && c <= 'f') return c - 'a' + 10;
        return -1;
    };
    for (size_t i = 0; i < s.size(); i++) {
        char c = s[i];
        if (c == '=') {
            if (i + 1 < s.size() && (s[i + 1] == '\r' || s[i + 1] == '\n')) {
                // soft line break
                i++;
                if (s[i] == '\r' && i + 1 < s.size() && s[i + 1] == '\n') i++;
                continue;
            }
            if (i + 2 < s.size()) {
                int hi = hexVal(s[i + 1]), lo = hexVal(s[i + 2]);
                if (hi >= 0 && lo >= 0) { out += char((hi << 4) | lo); i += 2; continue; }
            }
            out += c;
        } else out += c;
    }
    return out;
}

std::vector<uint8_t> xorBytes(const std::vector<uint8_t>& a, const std::vector<uint8_t>& b) {
    std::vector<uint8_t> r(a.size());
    for (size_t i = 0; i < a.size(); i++) r[i] = a[i] ^ (b.empty() ? 0 : b[i % b.size()]);
    return r;
}

} // namespace mr
