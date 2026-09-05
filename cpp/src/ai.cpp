// =====================================================================
//  ai.cpp - Trợ lý AI tương thích OpenAI
// =====================================================================
#include "ai.h"
#include "http_client.h"
#include "json.h"
#include "util.h"
#include "nhatky.h"

#include <algorithm>
#include <cstdio>

namespace mr {

void CauHinhAi::chuanHoa() {
    url = trim(url);
    while (!url.empty() && url.back() == '/') url.pop_back();
    model = trim(model);
    api_key = trim(api_key);
    if (max_tokens <= 0) max_tokens = 4096;
    if (max_tokens > MAX_TOKENS_TRAN) max_tokens = MAX_TOKENS_TRAN;
    if (timeout <= 0) timeout = 60;
    if (timeout > TIMEOUT_TRAN) timeout = TIMEOUT_TRAN;
    if (temperature < 0) temperature = 0;
    if (temperature > 2) temperature = 2;
    if (nguong_tin_cay < 0) nguong_tin_cay = 0;
    if (nguong_tin_cay > 1) nguong_tin_cay = 1;
    if (nhac_he_thong.empty())
        nhac_he_thong = "Bạn là trợ lý văn thư của Sở Giáo dục và Đào tạo. Nhiệm vụ: đọc thông tin "
                        "email và xác định MÃ TRƯỜNG, MÃ VĂN BẢN, MÃ NGƯỜI XỬ LÝ dựa trên danh mục "
                        "được cung cấp. Chỉ trả lời bằng JSON.";
}

CauHinhAi CauHinhAi::tuCauHinh(const CauHinh& ch) {
    CauHinhAi c;
    c.bat            = toBool(ch.uuTien("ai.bat", "ai.bat", "0"), false);
    c.url            = ch.uuTien("ai.url", "ai.url", "https://api.openai.com/v1");
    c.api_key        = ch.uuTien("ai.api_key", "ai.api_key", "");
    c.model          = ch.uuTien("ai.model", "ai.model", "gpt-4o-mini");
    c.max_tokens     = (int)toLL(ch.uuTien("ai.max_tokens", "ai.max_tokens", "4096"), 4096);
    c.timeout        = (int)toLL(ch.uuTien("ai.timeout", "ai.timeout", "60"), 60);
    c.temperature    = toDouble(ch.uuTien("ai.temperature", "ai.temperature", "0.1"), 0.1);
    c.nguong_tin_cay = toDouble(ch.uuTien("ai.nguong_tin_cay", "ai.nguong_tin_cay", "0.6"), 0.6);
    c.nhac_he_thong  = ch.uuTien("ai.nhac_he_thong", "ai.nhac_he_thong", "");
    c.chuanHoa();
    return c;
}

std::string TroLyAi::urlChat(const std::string& gocIn) {
    std::string goc = trim(gocIn);
    while (!goc.empty() && goc.back() == '/') goc.pop_back();
    if (goc.empty()) return "";
    if (goc.find("/chat/completions") != std::string::npos) return goc;
    if (endsWith(goc, "/completions")) return goc;
    return goc + "/chat/completions";
}

// ---------------------------------------------------------------------
//  Dựng câu nhắc
// ---------------------------------------------------------------------
std::string TroLyAi::dungPrompt(const BanGhiEmail& em, const DanhMuc& dm) const {
    std::string p;
    p += "DANH MỤC TRƯỜNG (mã | tên):\n";
    size_t n = 0;
    for (const auto& t : dm.truong) {
        if (n++ >= 400) { p += "... (còn nữa)\n"; break; }
        p += t.ma + " | " + t.ten + "\n";
    }
    p += "\nDANH MỤC NGƯỜI XỬ LÝ (mã | họ tên):\n";
    n = 0;
    for (const auto& x : dm.nguoi) {
        if (n++ >= 200) { p += "... (còn nữa)\n"; break; }
        p += x.ma + " | " + x.ho_ten + "\n";
    }
    p += "\nDANH MỤC MÃ VĂN BẢN (mã | tên) - danh mục này KHÔNG đầy đủ, "
         "nếu email dùng mã khác thì vẫn lấy đúng mã đọc được:\n";
    n = 0;
    for (const auto& v : dm.van_ban) {
        if (n++ >= 300) { p += "... (còn nữa)\n"; break; }
        p += v.ma + " | " + v.ten + "\n";
    }

    p += "\n===== THÔNG TIN EMAIL CẦN PHÂN LUỒNG =====\n";
    p += "Người gửi: " + em.ten_nguoi_gui + " <" + em.nguoi_gui + ">\n";
    p += "Tiêu đề: " + em.tieu_de + "\n";
    p += "Thời gian: " + dinhDangGioVN(em.ngay_gui) + " (giờ Việt Nam)\n";
    p += "Tệp đính kèm:\n";
    if (em.tep.empty()) p += "  (không có)\n";
    for (const auto& t : em.tep) p += "  - " + t.ten + " (" + dinhDangDungLuong(t.dung_luong) + ")\n";
    p += "Trích nội dung:\n" + catUtf8(trim(em.noi_dung_text.empty() ? em.doan_trich : em.noi_dung_text), 3000) + "\n";

    p += "\n===== YÊU CẦU =====\n"
         "Quy ước đặt tên: <mã trường>_<mã văn bản>_<mã người xử lý>, ví dụ 001_001_TAT.\n"
         "Hãy xác định 3 mã đó cho email trên. Quy tắc:\n"
         "1. Ưu tiên mã đọc được trực tiếp từ tên tệp đính kèm, sau đó tới tiêu đề, cuối cùng mới suy luận từ nội dung.\n"
         "2. ma_truong và ma_nguoi_xu_ly BẮT BUỘC phải nằm trong danh mục ở trên. Nếu không chắc chắn, để chuỗi rỗng.\n"
         "3. ma_van_ban có thể là mã mới không có trong danh mục - cứ ghi đúng mã đọc/suy được.\n"
         "4. do_tin_cay là số thực 0..1 thể hiện mức độ chắc chắn của bạn.\n"
         "CHỈ TRẢ LỜI bằng một đối tượng JSON duy nhất, không kèm giải thích ngoài JSON, theo đúng khuôn:\n"
         "{\"ma_truong\":\"\",\"ma_van_ban\":\"\",\"ma_nguoi_xu_ly\":\"\",\"do_tin_cay\":0.0,\"ly_do\":\"giải thích ngắn bằng tiếng Việt\"}";
    return p;
}

// ---------------------------------------------------------------------
//  Bóc JSON khỏi câu trả lời của mô hình
// ---------------------------------------------------------------------
static std::string bocJson(const std::string& s) {
    std::string t = trim(s);
    // Bỏ rào ```json ... ```
    size_t f = t.find("```");
    if (f != std::string::npos) {
        size_t bd = t.find('\n', f);
        size_t kt = t.rfind("```");
        if (bd != std::string::npos && kt != std::string::npos && kt > bd)
            t = trim(t.substr(bd + 1, kt - bd - 1));
    }
    size_t a = t.find('{');
    size_t b = t.rfind('}');
    if (a != std::string::npos && b != std::string::npos && b > a) return t.substr(a, b - a + 1);
    return t;
}

// ---------------------------------------------------------------------
//  Gọi AI
// ---------------------------------------------------------------------
bool TroLyAi::doanMa(const BanGhiEmail& em, const DanhMuc& dm, KetQuaAi& ra, std::string& loi) {
    ra = KetQuaAi();
    if (!bat()) { loi = "Chức năng AI đang tắt"; return false; }

    std::string url = urlChat(ch_.url);
    if (url.empty()) { loi = "Chưa khai báo địa chỉ dịch vụ AI"; return false; }

    Json tinNhan = Json::mang();
    Json m1 = Json::doiTuong();
    m1.dat("role", "system");
    m1.dat("content", ch_.nhac_he_thong);
    tinNhan.them(m1);
    Json m2 = Json::doiTuong();
    m2.dat("role", "user");
    m2.dat("content", dungPrompt(em, dm));
    tinNhan.them(m2);

    Json than = Json::doiTuong();
    than.dat("model", ch_.model);
    than.dat("messages", tinNhan);
    than.dat("temperature", ch_.temperature);
    than.dat("max_tokens", (long long)ch_.max_tokens);
    than.dat("stream", false);

    std::vector<std::string> hd;
    if (!ch_.api_key.empty()) hd.push_back("Authorization: Bearer " + ch_.api_key);
    hd.push_back("Accept: application/json");

    int64_t t0 = nowEpochMs();
    HttpPhanHoi pr = HttpClient::postJson(url, than.ketXuat(), hd, ch_.timeout);
    ra.giay = (nowEpochMs() - t0) / 1000.0;

    if (!pr.loi.empty()) { loi = pr.loi; return false; }
    if (pr.than.empty()) { loi = "Dịch vụ AI không trả về dữ liệu (HTTP " + std::to_string(pr.ma) + ")"; return false; }

    Json j;
    if (!Json::phanTich(pr.than, j, nullptr)) {
        loi = "Dịch vụ AI trả về dữ liệu không phải JSON: " + catUtf8(pr.than, 300);
        return false;
    }
    if (j.coKhoa("error")) {
        const Json& e = j.lay("error");
        loi = "AI báo lỗi: " + e.lay("message").chuoi(catUtf8(pr.than, 200));
        return false;
    }
    if (pr.ma < 200 || pr.ma >= 300) {
        loi = "Dịch vụ AI trả về HTTP " + std::to_string(pr.ma) + ": " + catUtf8(pr.than, 300);
        return false;
    }

    std::string noiDung = j.duongDan("choices.0.message.content").chuoi();
    if (noiDung.empty()) noiDung = j.duongDan("choices.0.text").chuoi();
    if (noiDung.empty()) {
        loi = "AI trả về nội dung rỗng";
        return false;
    }
    ra.tra_loi_tho = catUtf8(noiDung, 4000);
    ra.token_da_dung = (int)j.duongDan("usage.total_tokens").nguyen(0);

    Json kq;
    if (!Json::phanTich(bocJson(noiDung), kq, nullptr) || !kq.laDoiTuong()) {
        loi = "Không đọc được JSON trong câu trả lời của AI: " + catUtf8(noiDung, 300);
        return false;
    }

    ra.ma.truong  = trim(kq.lay("ma_truong").chuoi());
    ra.ma.van_ban = trim(kq.lay("ma_van_ban").chuoi());
    ra.ma.nguoi   = toUpper(trim(kq.lay("ma_nguoi_xu_ly").chuoi()));
    ra.do_tin_cay = kq.lay("do_tin_cay").so(0);
    if (ra.do_tin_cay > 1.0) ra.do_tin_cay = ra.do_tin_cay / 100.0;   // mô hình trả 0..100
    if (ra.do_tin_cay < 0) ra.do_tin_cay = 0;
    if (ra.do_tin_cay > 1) ra.do_tin_cay = 1;
    ra.giai_thich = catUtf8(trim(kq.lay("ly_do").chuoi()), 2000);
    return true;
}

bool TroLyAi::kiemTra(std::string& thongDiep, std::string& loi) {
    std::string url = urlChat(ch_.url);
    if (url.empty()) { loi = "Chưa khai báo địa chỉ dịch vụ AI"; return false; }

    Json tinNhan = Json::mang();
    Json m = Json::doiTuong();
    m.dat("role", "user");
    m.dat("content", "Trả lời đúng một từ: OK");
    tinNhan.them(m);

    Json than = Json::doiTuong();
    than.dat("model", ch_.model);
    than.dat("messages", tinNhan);
    than.dat("temperature", 0);
    than.dat("max_tokens", (long long)16);

    std::vector<std::string> hd;
    if (!ch_.api_key.empty()) hd.push_back("Authorization: Bearer " + ch_.api_key);

    HttpPhanHoi pr = HttpClient::postJson(url, than.ketXuat(), hd,
                                          std::min(ch_.timeout, 60));
    if (!pr.loi.empty()) { loi = pr.loi; return false; }

    Json j;
    Json::phanTich(pr.than, j, nullptr);
    if (j.coKhoa("error")) {
        loi = "AI báo lỗi: " + j.duongDan("error.message").chuoi(catUtf8(pr.than, 200));
        return false;
    }
    if (pr.ma < 200 || pr.ma >= 300) {
        loi = "Dịch vụ AI trả về HTTP " + std::to_string(pr.ma) + ": " + catUtf8(pr.than, 300);
        return false;
    }
    std::string nd = trim(j.duongDan("choices.0.message.content").chuoi());
    char buf[256];
    std::snprintf(buf, sizeof(buf), "Kết nối AI thành công (%.1f giây). Mô hình trả lời: \"%s\"",
                  pr.giay, catUtf8(nd, 60).c_str());
    thongDiep = buf;
    return true;
}

} // namespace mr
