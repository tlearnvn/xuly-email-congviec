// =====================================================================
//  crypto.h - Base64, SHA-1, SHA-256, giải mã quoted-printable
//  Không phụ thuộc thư viện ngoài.
// =====================================================================
#pragma once

#include <string>
#include <vector>
#include <cstdint>

namespace mr {

// ------------------------------ Base64 ------------------------------
std::string base64Encode(const std::string& du_lieu, bool urlSafe = false, bool dem = true);
std::string base64Decode(const std::string& vanBan);   // chấp nhận cả base64url và ký tự lạ

// ------------------------------- SHA --------------------------------
std::vector<uint8_t> sha1(const std::string& du_lieu);
std::string          sha1Hex(const std::string& du_lieu);

std::vector<uint8_t> sha256(const std::string& du_lieu);
std::string          sha256Hex(const std::string& du_lieu);

// SHA-256 theo luồng (dùng cho tệp lớn, không cần nạp hết vào RAM)
class Sha256Luong {
public:
    Sha256Luong();
    void napThem(const void* du_lieu, size_t n);
    void napThem(const std::string& s) { napThem(s.data(), s.size()); }
    std::vector<uint8_t> ketThuc();
    std::string ketThucHex();
private:
    uint32_t h_[8];
    uint64_t tongBit_;
    uint8_t  dem_[64];
    size_t   nDem_;
    bool     xong_;
    std::vector<uint8_t> kq_;
};

// --------------------------- Mã hoá khác ----------------------------
std::string giaiMaQuotedPrintable(const std::string& s);
// XOR hai chuỗi byte cùng độ dài
std::vector<uint8_t> xorBytes(const std::vector<uint8_t>& a, const std::vector<uint8_t>& b);

} // namespace mr
