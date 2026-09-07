// =====================================================================
//  gmail.h - Trình khách Gmail API + OAuth 2.0 + phân tích MIME
// =====================================================================
#pragma once

#include "mohinh.h"
#include "json.h"
#include "util.h"

#include <set>
#include <string>
#include <vector>

namespace mr {

struct TokenGmail {
    std::string access_token;
    std::string refresh_token;
    int64_t     het_han = 0;              // epoch UTC
    std::string pham_vi;
    std::string dia_chi;                  // hộp thư đã xác thực

    bool coRefresh() const { return !refresh_token.empty(); }
    bool conHan(int demTruoc = 120) const {
        return !access_token.empty() && (het_han == 0 || het_han - demTruoc > nowEpoch());
    }
};

class Gmail {
public:
    static const char* PHAM_VI_DOC;       // gmail.readonly

    void datUngDung(const std::string& clientId, const std::string& clientSecret);
    void datToken(const TokenGmail& t) { token_ = t; }
    const TokenGmail& token() const { return token_; }
    bool coUngDung() const { return !client_id_.empty() && !client_secret_.empty(); }
    void datTimeout(int giay) { timeout_ = giay; }

    // ------------------------- OAuth --------------------------------
    std::string urlDongY(const std::string& redirectUri, const std::string& state) const;
    bool doiMaLayToken(const std::string& ma, const std::string& redirectUri, std::string& loi);
    bool lamMoiToken(std::string& loi);
    bool damBaoToken(std::string& loi);
    bool thuHoi(std::string& loi);

    // ------------------------- Gmail API -----------------------------
    bool hoSo(std::string& diaChi, long long& tongMail, std::string& loi);
    // gomSpam = true: quét thêm một lượt trong hộp Thư rác (Spam).
    // Gmail API mặc định giấu hẳn thư trong Spam và Thùng rác, phải xin riêng.
    // Lỗi riêng ở lượt Spam ghi vào canhBao chứ không làm hỏng cả phiên.
    // nhanLink = true: nới "has:attachment" để bắt cả thư chỉ dán link chia sẻ.
    bool danhSachMail(const std::string& truyVan, int soLuong, bool gomSpam, bool nhanLink,
                      std::vector<std::string>& ids, std::string& loi,
                      std::string* canhBao = nullptr);
    // Nới truy vấn: "has:attachment" -> "(has:attachment OR "drive.google.com" OR …)".
    // Truy vấn không có "has:attachment" (hoặc chỉ có "-has:attachment") thì giữ nguyên.
    static std::string moRongTruyVanLink(const std::string& truyVan);
    // Truy vấn có lọc theo tệp đính kèm hay không (has:attachment, has:drive,
    // filename:, tên miền chia sẻ…). Không lọc thì Gmail trả về MỌI thư, hạn mức
    // "số mail mỗi lần quét" bị tiêu vào cả thư không liên quan.
    static bool truyVanCoLocTep(const std::string& truyVan);
    bool layMail(const std::string& id, Json& ra, std::string& loi);
    bool layTepDinhKem(const std::string& idMail, const std::string& idTep,
                       std::string& duLieu, std::string& loi);

    // --------------------- Phân tích nội dung -------------------------
    // Chuyển JSON của Gmail API thành bản ghi email (chưa tải nội dung tệp)
    static void phanTichMail(const Json& j, BanGhiEmail& em);
    // Giải mã tiêu đề dạng =?UTF-8?B?...?= và RFC2231
    static std::string giaiMaTieuDeMime(const std::string& s);
    // Chuyển bảng mã thường gặp về UTF-8
    static std::string sangUtf8(const std::string& s, const std::string& bangMa);

private:
    std::string client_id_, client_secret_;
    TokenGmail  token_;
    int timeout_ = 90;

    std::vector<std::string> tieuDeXacThuc() const;
    bool goiApi(const std::string& url, Json& ra, std::string& loi);
    // Một lượt quét (hộp thư chính hoặc hộp Spam), bỏ qua id đã gặp ở lượt trước
    bool quetMotLuot(const std::string& truyVan, int soLuong, bool trongSpam,
                     std::set<std::string>& daCo,
                     std::vector<std::string>& ids, std::string& loi);
};

} // namespace mr
