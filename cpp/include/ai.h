// =====================================================================
//  ai.h - Trợ lý AI (OpenAI compatible) hỗ trợ đọc các mail khó
// =====================================================================
#pragma once

#include "mohinh.h"
#include "cauhinh.h"

#include <string>

namespace mr {

struct CauHinhAi {
    bool        bat = false;
    std::string url = "https://api.openai.com/v1";
    std::string api_key;
    std::string model = "gpt-4o-mini";
    int         max_tokens = 4096;        // tối đa 64000
    int         timeout = 60;             // tối đa 300 giây
    double      temperature = 0.1;
    double      nguong_tin_cay = 0.6;
    std::string nhac_he_thong;

    static const int MAX_TOKENS_TRAN = 64000;
    static const int TIMEOUT_TRAN = 300;

    void chuanHoa();
    static CauHinhAi tuCauHinh(const CauHinh& ch);
};

struct KetQuaAi {
    MaHoSo      ma;
    double      do_tin_cay = 0;
    std::string giai_thich;
    std::string tra_loi_tho;
    int         token_da_dung = 0;
    double      giay = 0;
};

class TroLyAi {
public:
    void datCauHinh(const CauHinhAi& c) { ch_ = c; ch_.chuanHoa(); }
    const CauHinhAi& cauHinh() const { return ch_; }
    bool bat() const { return ch_.bat && !ch_.url.empty() && !ch_.model.empty(); }

    // Nhờ AI đọc mã trường / mã văn bản / mã người xử lý
    bool doanMa(const BanGhiEmail& em, const DanhMuc& dm, KetQuaAi& ra, std::string& loi);
    // Kiểm tra kết nối tới dịch vụ AI
    bool kiemTra(std::string& thongDiep, std::string& loi);

    static std::string urlChat(const std::string& goc);

private:
    CauHinhAi ch_;
    std::string dungPrompt(const BanGhiEmail& em, const DanhMuc& dm) const;
};

} // namespace mr
