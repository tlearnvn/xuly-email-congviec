// =====================================================================
//  Hệ thống phân luồng Mail công vụ - Bộ nhận mail (MailRouter)
//  Thiết kế bởi Trương Anh Tuấn
//  util.h - Tiện ích chuỗi, thời gian (giờ Việt Nam), tệp, hệ thống
// =====================================================================
#pragma once

#include <string>
#include <vector>
#include <cstdint>
#include <ctime>

namespace mr {

// ------------------------- Chuỗi cơ bản -----------------------------
std::string trim(const std::string& s);
std::string toLower(const std::string& s);          // chỉ ASCII
std::string toUpper(const std::string& s);          // chỉ ASCII
bool startsWith(const std::string& s, const std::string& p);
bool endsWith(const std::string& s, const std::string& p);
bool containsIC(const std::string& hay, const std::string& needle); // không phân biệt hoa thường
std::vector<std::string> split(const std::string& s, char sep, bool keepEmpty = false);
std::vector<std::string> splitAny(const std::string& s, const std::string& seps);
std::string join(const std::vector<std::string>& v, const std::string& sep);
std::string replaceAll(std::string s, const std::string& from, const std::string& to);
std::string padLeft(const std::string& s, size_t n, char c = '0');

// --------------------- Tiếng Việt / chuẩn hoá -----------------------
// Bỏ dấu tiếng Việt (đầu vào UTF-8), trả về chuỗi ASCII
std::string boDauTiengViet(const std::string& utf8);
// Chuẩn hoá mã: bỏ dấu, in hoa, bỏ ký tự không phải chữ/số
std::string chuanHoaMa(const std::string& s);
// Chuẩn hoá mã trường/mã văn bản: nếu toàn số thì bỏ số 0 ở đầu
std::string chuanHoaMaSo(const std::string& s);
// Chuẩn hoá tiêu đề để so trùng: bỏ dấu, in hoa, gom khoảng trắng, bỏ tiền tố RE:/FW:
std::string chuanHoaTieuDe(const std::string& s, const std::vector<std::string>& tienToBoQua);
// Cắt chuỗi UTF-8 an toàn (không vỡ ký tự) theo số byte
std::string catUtf8(const std::string& s, size_t maxBytes);
// Loại bỏ ký tự điều khiển, đảm bảo UTF-8 hợp lệ
std::string locUtf8(const std::string& s);

// ------------------------- Thời gian --------------------------------
static const int VN_OFFSET_SECONDS = 7 * 3600;      // GMT+7

int64_t nowEpoch();                                  // giây UTC
int64_t nowEpochMs();                                // mili giây UTC
// Định dạng theo giờ Việt Nam
std::string dinhDangGioVN(int64_t epochSeconds, const char* fmt = "%Y-%m-%d %H:%M:%S");
std::string gioVNHienTai(const char* fmt = "%Y-%m-%d %H:%M:%S");
std::string gioVNTepTin();                           // 20260905_011500
// Phân tích ngày RFC 2822 (Date: header) -> epoch UTC
int64_t phanTichNgayRfc2822(const std::string& s);
// Phân tích chuỗi ISO "YYYY-MM-DD HH:MM:SS" (coi là giờ VN) -> epoch UTC
int64_t phanTichNgayISO_VN(const std::string& s);

// ------------------------- Số / mã hoá ------------------------------
std::string toHex(const std::vector<uint8_t>& v);
std::string toHex(const std::string& s);
std::vector<uint8_t> fromHex(const std::string& hex);
std::string chuoiNgauNhien(size_t nByte);            // hex ngẫu nhiên
long long toLL(const std::string& s, long long mac_dinh = 0);
double toDouble(const std::string& s, double mac_dinh = 0);
bool toBool(const std::string& s, bool mac_dinh = false);
std::string dinhDangDungLuong(long long bytes);      // 1,2 MB

// ------------------------- Tệp / hệ thống ---------------------------
bool docTep(const std::string& duongDan, std::string& noiDung);
bool ghiTep(const std::string& duongDan, const std::string& noiDung);
bool tepTonTai(const std::string& duongDan);
bool taoThuMuc(const std::string& duongDan);
std::string thuMucCuaTep(const std::string& duongDan);
std::string thuMucChuongTrinh();                     // thư mục chứa file thực thi
std::string tenMayChu();
std::string phanMoRong(const std::string& tenTep);   // trả về "pdf" (chữ thường)

// Tìm các liên kết chia sẻ tệp (Google Drive/Docs, OneDrive, Dropbox…) nằm trong
// thân thư. Dùng khi trường không đính kèm tệp mà chỉ dán đường dẫn.
// Quét cả bản text lẫn bản HTML, bỏ trùng, giữ nguyên thứ tự xuất hiện.
std::vector<std::string> timLienKetChiaSe(const std::string& text, const std::string& html);
std::string doanMimeTuTen(const std::string& tenTep);
void nguGiay(int giay);
void nguMiliGiay(int ms);
// Mở URL bằng trình duyệt mặc định của hệ điều hành
bool moTrinhDuyet(const std::string& url);

// URL encode / decode
std::string urlEncode(const std::string& s);
std::string urlDecode(const std::string& s);

} // namespace mr
