// =====================================================================
//  cauhinh.cpp
// =====================================================================
#include "cauhinh.h"
#include "util.h"

#include <sstream>

namespace mr {

std::string CauHinh::tepMacDinh() {
    return thuMucChuongTrinh() + "/mailrouter.ini";
}

bool CauHinh::doc(const std::string& duongDan) {
    duongDan_ = duongDan;
    std::string noiDung;
    if (!docTep(duongDan, noiDung)) return false;
    // Bỏ BOM
    if (noiDung.size() >= 3 && (unsigned char)noiDung[0] == 0xEF) noiDung = noiDung.substr(3);

    std::string nhom;
    std::istringstream ss(noiDung);
    std::string dong;
    while (std::getline(ss, dong)) {
        if (!dong.empty() && dong.back() == '\r') dong.pop_back();
        std::string d = trim(dong);
        if (d.empty() || d[0] == ';' || d[0] == '#') continue;
        if (d.front() == '[' && d.back() == ']') {
            nhom = toLower(trim(d.substr(1, d.size() - 2)));
            continue;
        }
        size_t eq = d.find('=');
        if (eq == std::string::npos) continue;
        std::string k = toLower(trim(d.substr(0, eq)));
        std::string v = trim(d.substr(eq + 1));
        // bỏ chú thích cuối dòng nếu giá trị không nằm trong dấu nháy
        if (!v.empty() && v.front() == '"' && v.back() == '"' && v.size() >= 2)
            v = v.substr(1, v.size() - 2);
        if (k.empty()) continue;
        dat(nhom.empty() ? k : (nhom + "." + k), v);
    }
    return true;
}

std::string CauHinh::noiDungIni() const {
    // Nhóm theo section, giữ thứ tự xuất hiện
    std::vector<std::string> nhomThuTu;
    std::map<std::string, std::vector<std::string>> theoNhom;
    for (const auto& k : thuTu_) {
        size_t p = k.find('.');
        std::string nhom = (p == std::string::npos) ? "chung" : k.substr(0, p);
        if (theoNhom.find(nhom) == theoNhom.end()) nhomThuTu.push_back(nhom);
        theoNhom[nhom].push_back(k);
    }
    std::string out;
    out += "; =====================================================================\n";
    out += ";  Cau hinh Bo nhan mail - He thong phan luong Mail cong vu\n";
    out += ";  Thiet ke boi Truong Anh Tuan\n";
    out += ";  Cap nhat: " + gioVNHienTai() + " (gio Viet Nam)\n";
    out += "; =====================================================================\n";
    for (const auto& nhom : nhomThuTu) {
        out += "\n[" + nhom + "]\n";
        for (const auto& k : theoNhom[nhom]) {
            size_t p = k.find('.');
            std::string ten = (p == std::string::npos) ? k : k.substr(p + 1);
            auto it = gt_.find(k);
            out += ten + " = " + (it == gt_.end() ? "" : it->second) + "\n";
        }
    }
    return out;
}

bool CauHinh::ghi(const std::string& duongDan) const {
    return ghiTep(duongDan.empty() ? duongDan_ : duongDan, noiDungIni());
}

bool CauHinh::coKhoa(const std::string& khoa) const {
    return gt_.find(toLower(khoa)) != gt_.end();
}

std::string CauHinh::chuoi(const std::string& khoa, const std::string& macDinh) const {
    auto it = gt_.find(toLower(khoa));
    if (it == gt_.end() || it->second.empty()) return macDinh;
    return it->second;
}

long long CauHinh::nguyen(const std::string& khoa, long long macDinh) const {
    return toLL(chuoi(khoa, ""), macDinh);
}

double CauHinh::thuc(const std::string& khoa, double macDinh) const {
    return toDouble(chuoi(khoa, ""), macDinh);
}

bool CauHinh::logic(const std::string& khoa, bool macDinh) const {
    return toBool(chuoi(khoa, ""), macDinh);
}

void CauHinh::dat(const std::string& khoa, const std::string& giaTri) {
    std::string k = toLower(khoa);
    if (gt_.find(k) == gt_.end()) thuTu_.push_back(k);
    gt_[k] = giaTri;
}

void CauHinh::datNeuTrong(const std::string& khoa, const std::string& giaTri) {
    std::string k = toLower(khoa);
    auto it = gt_.find(k);
    if (it == gt_.end() || it->second.empty()) dat(k, giaTri);
}

void CauHinh::napTuMayChu(const std::map<std::string, std::string>& tuMayChu) {
    mayChu_ = tuMayChu;
}

std::string CauHinh::chuoiMayChu(const std::string& khoa, const std::string& macDinh) const {
    auto it = mayChu_.find(khoa);
    if (it == mayChu_.end() || it->second.empty()) return macDinh;
    return it->second;
}

long long CauHinh::nguyenMayChu(const std::string& khoa, long long macDinh) const {
    return toLL(chuoiMayChu(khoa, ""), macDinh);
}

bool CauHinh::logicMayChu(const std::string& khoa, bool macDinh) const {
    return toBool(chuoiMayChu(khoa, ""), macDinh);
}

std::string CauHinh::uuTien(const std::string& khoaIni, const std::string& khoaMayChu,
                            const std::string& macDinh) const {
    std::string v = chuoi(khoaIni, "");
    if (!v.empty()) return v;
    v = chuoiMayChu(khoaMayChu, "");
    if (!v.empty()) return v;
    return macDinh;
}

std::vector<std::string> CauHinh::danhSachKhoa() const { return thuTu_; }

} // namespace mr
