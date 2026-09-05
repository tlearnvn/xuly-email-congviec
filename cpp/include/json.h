// =====================================================================
//  json.h - Bộ phân tích / kết xuất JSON gọn nhẹ, không phụ thuộc ngoài
// =====================================================================
#pragma once

#include <string>
#include <vector>
#include <utility>
#include <cstdint>
#include <initializer_list>

namespace mr {

class Json {
public:
    enum Kieu { RONG, LOGIC, SO, CHUOI, MANG, DOI_TUONG };

    Json() : kieu_(RONG) {}
    Json(bool b) : kieu_(LOGIC), logic_(b) {}
    Json(double d) : kieu_(SO), so_(d) {}
    Json(int i) : kieu_(SO), so_((double)i) {}
    Json(long long i) : kieu_(SO), so_((double)i) {}
    Json(const char* s) : kieu_(CHUOI), chuoi_(s ? s : "") {}
    Json(const std::string& s) : kieu_(CHUOI), chuoi_(s) {}

    static Json mang()      { Json j; j.kieu_ = MANG; return j; }
    static Json doiTuong()  { Json j; j.kieu_ = DOI_TUONG; return j; }

    // Phân tích chuỗi JSON. Trả về false nếu lỗi (thông điệp trong loi).
    static bool phanTich(const std::string& vanBan, Json& ketQua, std::string* loi = nullptr);
    static Json phanTich(const std::string& vanBan);   // lỗi -> Json rỗng

    // Kết xuất. indent < 0: một dòng; >= 0: xuống dòng & thụt lề
    std::string ketXuat(int indent = -1) const;

    Kieu kieu() const { return kieu_; }
    bool laRong()     const { return kieu_ == RONG; }
    bool laLogic()    const { return kieu_ == LOGIC; }
    bool laSo()       const { return kieu_ == SO; }
    bool laChuoi()    const { return kieu_ == CHUOI; }
    bool laMang()     const { return kieu_ == MANG; }
    bool laDoiTuong() const { return kieu_ == DOI_TUONG; }

    // Truy xuất giá trị (tự chuyển kiểu khi hợp lý)
    std::string chuoi(const std::string& macDinh = "") const;
    double      so(double macDinh = 0) const;
    long long   nguyen(long long macDinh = 0) const;
    bool        logic(bool macDinh = false) const;

    size_t soPhanTu() const;
    const Json& phanTu(size_t i) const;                 // với MANG
    const Json& operator[](size_t i) const { return phanTu(i); }

    bool coKhoa(const std::string& k) const;
    const Json& lay(const std::string& k) const;        // với DOI_TUONG
    const Json& operator[](const std::string& k) const { return lay(k); }
    // Truy xuất theo đường dẫn "a.b.c" hoặc "a.0.b"
    const Json& duongDan(const std::string& p) const;

    std::vector<std::string> danhSachKhoa() const;
    const std::vector<Json>& cacPhanTu() const { return mang_; }

    // Xây dựng
    void them(const Json& v);                            // vào MANG
    void dat(const std::string& k, const Json& v);       // vào DOI_TUONG
    void xoaKhoa(const std::string& k);

    static const Json& rong();
    static std::string thoatChuoi(const std::string& s);

private:
    Kieu kieu_;
    bool logic_ = false;
    double so_ = 0;
    std::string chuoi_;
    std::vector<Json> mang_;
    std::vector<std::pair<std::string, Json>> doiTuong_;

    void ketXuatVao(std::string& out, int indent, int mucHienTai) const;
};

} // namespace mr
