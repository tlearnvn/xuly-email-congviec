// =====================================================================
//  kholuutru.h - Tầng lưu trữ: MySQL trực tiếp hoặc qua API PHP
// =====================================================================
#pragma once

#include "mohinh.h"
#include "cauhinh.h"
#include "mysql_client.h"
#include "nhatky.h"

#include <memory>
#include <string>
#include <map>

namespace mr {

class KhoLuuTru {
public:
    virtual ~KhoLuuTru() {}

    virtual std::string ten() const = 0;
    virtual bool ketNoi(std::string& loi) = 0;
    virtual void dong() = 0;
    virtual bool kiemTra(std::string& loi) = 0;      // kiểm tra kết nối, tự nối lại

    virtual bool napDanhMuc(DanhMuc& dm, std::string& loi) = 0;
    virtual bool napCauHinh(std::map<std::string, std::string>& ch, std::string& loi) = 0;

    // Mã văn bản lạ -> tạo bản ghi tạm (nới lỏng danh mục)
    virtual bool taoVanBanTuDong(const std::string& ma, MucVanBan& ra, std::string& loi) = 0;

    // Đã lưu email này chưa? (theo gmail_message_id)
    virtual bool daLuu(const std::string& gmailId, long long& idEmail, std::string& loi) = 0;

    // Lưu trọn một email cùng tệp đính kèm và các công việc
    virtual bool luuEmail(const BanGhiEmail& em, const std::vector<NhomCongViec>& nhom,
                          KetQuaLuu& kq, std::string& loi) = 0;

    virtual bool ghiNhatKy(const DongNhatKy& d) = 0;

    virtual bool moPhien(ThongKePhien& tk, const std::string& hopThu,
                         const std::string& truyVan, std::string& loi) = 0;
    virtual bool dongPhien(const ThongKePhien& tk, const std::string& trangThai,
                           std::string& loi) = 0;

    // Tạo kho lưu trữ theo cấu hình (che_do = mysql | api)
    static std::unique_ptr<KhoLuuTru> tao(const CauHinh& ch, std::string& loi);
};

// ---------------------------------------------------------------------
//  Lưu trực tiếp vào MySQL
// ---------------------------------------------------------------------
class KhoMySql : public KhoLuuTru {
public:
    explicit KhoMySql(const CauHinh& ch);
    std::string ten() const override { return "MySQL trực tiếp"; }
    bool ketNoi(std::string& loi) override;
    void dong() override;
    bool kiemTra(std::string& loi) override;
    bool napDanhMuc(DanhMuc& dm, std::string& loi) override;
    bool napCauHinh(std::map<std::string, std::string>& ch, std::string& loi) override;
    bool taoVanBanTuDong(const std::string& ma, MucVanBan& ra, std::string& loi) override;
    bool daLuu(const std::string& gmailId, long long& idEmail, std::string& loi) override;
    bool luuEmail(const BanGhiEmail& em, const std::vector<NhomCongViec>& nhom,
                  KetQuaLuu& kq, std::string& loi) override;
    bool ghiNhatKy(const DongNhatKy& d) override;
    bool moPhien(ThongKePhien& tk, const std::string& hopThu,
                 const std::string& truyVan, std::string& loi) override;
    bool dongPhien(const ThongKePhien& tk, const std::string& trangThai, std::string& loi) override;

private:
    MySql db_;
    MySqlThamSo ts_;
    long long khoiKb_ = 256;
    int soNgayDoiChieu_ = 365;
    bool taoBanMoiKhiTepKhac_ = true;
    std::string mayChu_;

    bool luuBlob(const TepDinhKem& t, long long& idTepDuLieu, bool& daCo, std::string& loi);
    bool timTrung(const BanGhiEmail& em, KetQuaLuu& kq, std::string& loi);
    bool kiemTraDaCaiDat(std::string& loi);
    bool kiemTraCotMoi(std::string& loi);      // CSDL cũ có thể thiếu cột mới thêm
};

// ---------------------------------------------------------------------
//  Lưu qua API PHP (dùng khi hosting không cho phép MySQL từ xa)
// ---------------------------------------------------------------------
class KhoApi : public KhoLuuTru {
public:
    explicit KhoApi(const CauHinh& ch);
    std::string ten() const override { return "API PHP"; }
    bool ketNoi(std::string& loi) override;
    void dong() override {}
    bool kiemTra(std::string& loi) override;
    bool napDanhMuc(DanhMuc& dm, std::string& loi) override;
    bool napCauHinh(std::map<std::string, std::string>& ch, std::string& loi) override;
    bool taoVanBanTuDong(const std::string& ma, MucVanBan& ra, std::string& loi) override;
    bool daLuu(const std::string& gmailId, long long& idEmail, std::string& loi) override;
    bool luuEmail(const BanGhiEmail& em, const std::vector<NhomCongViec>& nhom,
                  KetQuaLuu& kq, std::string& loi) override;
    bool ghiNhatKy(const DongNhatKy& d) override;
    bool moPhien(ThongKePhien& tk, const std::string& hopThu,
                 const std::string& truyVan, std::string& loi) override;
    bool dongPhien(const ThongKePhien& tk, const std::string& trangThai, std::string& loi) override;

private:
    std::string url_;
    std::string khoa_;
    int timeout_ = 120;
    long long khoiKb_ = 512;
    std::string mayChu_;

    bool goi(const std::string& hanhDong, const class Json& thamSo,
             class Json& ketQua, std::string& loi);
    bool taiTep(const TepDinhKem& t, std::string& loi);
};

} // namespace mr
