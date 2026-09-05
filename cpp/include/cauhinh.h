// =====================================================================
//  cauhinh.h - Đọc/ghi tệp cấu hình INI + cấu hình lấy từ máy chủ
// =====================================================================
#pragma once

#include <string>
#include <vector>
#include <map>

namespace mr {

class CauHinh {
public:
    bool doc(const std::string& duongDan);
    bool ghi(const std::string& duongDan) const;
    const std::string& duongDanTep() const { return duongDan_; }

    std::string  chuoi(const std::string& khoa, const std::string& macDinh = "") const;
    long long    nguyen(const std::string& khoa, long long macDinh = 0) const;
    double       thuc(const std::string& khoa, double macDinh = 0) const;
    bool         logic(const std::string& khoa, bool macDinh = false) const;
    bool         coKhoa(const std::string& khoa) const;

    void dat(const std::string& khoa, const std::string& giaTri);
    void datNeuTrong(const std::string& khoa, const std::string& giaTri);

    // Cấu hình tải từ máy chủ (bảng cau_hinh) - ưu tiên thấp hơn tệp INI
    void napTuMayChu(const std::map<std::string, std::string>& tuMayChu);
    std::string chuoiMayChu(const std::string& khoa, const std::string& macDinh = "") const;
    long long   nguyenMayChu(const std::string& khoa, long long macDinh = 0) const;
    bool        logicMayChu(const std::string& khoa, bool macDinh = false) const;
    // Ưu tiên: tệp INI (nếu có giá trị) -> máy chủ -> mặc định
    std::string uuTien(const std::string& khoaIni, const std::string& khoaMayChu,
                       const std::string& macDinh = "") const;

    std::vector<std::string> danhSachKhoa() const;
    std::string noiDungIni() const;
    static std::string tepMacDinh();          // <thư mục chương trình>/mailrouter.ini

private:
    std::map<std::string, std::string> gt_;
    std::map<std::string, std::string> mayChu_;
    std::vector<std::string> thuTu_;
    std::string duongDan_;
};

} // namespace mr
