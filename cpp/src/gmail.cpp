// =====================================================================
//  gmail.cpp - Gmail API + OAuth 2.0 + phân tích MIME
// =====================================================================
#include "gmail.h"
#include "http_client.h"
#include "crypto.h"
#include "util.h"
#include "nhatky.h"

#include <cstring>
#include <cstdio>
#include <algorithm>

namespace mr {

const char* Gmail::PHAM_VI_DOC = "https://www.googleapis.com/auth/gmail.readonly";

static const char* URL_TOKEN  = "https://oauth2.googleapis.com/token";
static const char* URL_DONGY  = "https://accounts.google.com/o/oauth2/v2/auth";
static const char* URL_THUHOI = "https://oauth2.googleapis.com/revoke";
static const char* API_GOC    = "https://gmail.googleapis.com/gmail/v1/users/me";

static const size_t GIOI_HAN_THAN = 2 * 1024 * 1024;      // 2MB nội dung mail

void Gmail::datUngDung(const std::string& clientId, const std::string& clientSecret) {
    client_id_ = trim(clientId);
    client_secret_ = trim(clientSecret);
}

std::vector<std::string> Gmail::tieuDeXacThuc() const {
    return { "Authorization: Bearer " + token_.access_token, "Accept: application/json" };
}

// =====================================================================
//  OAuth 2.0
// =====================================================================
std::string Gmail::urlDongY(const std::string& redirectUri, const std::string& state) const {
    std::string u = std::string(URL_DONGY) +
        "?client_id=" + urlEncode(client_id_) +
        "&redirect_uri=" + urlEncode(redirectUri) +
        "&response_type=code" +
        "&scope=" + urlEncode(PHAM_VI_DOC) +
        "&access_type=offline" +
        "&include_granted_scopes=true" +
        "&prompt=consent";
    if (!state.empty()) u += "&state=" + urlEncode(state);
    return u;
}

static bool docToken(const Json& j, TokenGmail& t, std::string& loi) {
    if (j.coKhoa("error")) {
        loi = j.lay("error").chuoi() ;
        std::string mt = j.lay("error_description").chuoi();
        if (!mt.empty()) loi += " - " + mt;
        return false;
    }
    std::string at = j.lay("access_token").chuoi();
    if (at.empty()) { loi = "Google không trả về access_token"; return false; }
    t.access_token = at;
    std::string rt = j.lay("refresh_token").chuoi();
    if (!rt.empty()) t.refresh_token = rt;
    long long hh = j.lay("expires_in").nguyen(3600);
    t.het_han = nowEpoch() + (hh > 0 ? hh : 3600);
    std::string pv = j.lay("scope").chuoi();
    if (!pv.empty()) t.pham_vi = pv;
    return true;
}

bool Gmail::doiMaLayToken(const std::string& ma, const std::string& redirectUri, std::string& loi) {
    if (!coUngDung()) { loi = "Chưa khai báo Client ID / Client Secret"; return false; }
    std::map<std::string, std::string> f;
    f["code"] = ma;
    f["client_id"] = client_id_;
    f["client_secret"] = client_secret_;
    f["redirect_uri"] = redirectUri;
    f["grant_type"] = "authorization_code";

    HttpPhanHoi pr = HttpClient::postForm(URL_TOKEN, f, timeout_);
    if (!pr.loi.empty()) { loi = pr.loi; return false; }
    Json j;
    if (!Json::phanTich(pr.than, j, nullptr)) {
        loi = "Google trả về dữ liệu không hợp lệ: " + catUtf8(pr.than, 300);
        return false;
    }
    if (!docToken(j, token_, loi)) return false;

    long long tong = 0;
    std::string dc, l2;
    if (hoSo(dc, tong, l2)) token_.dia_chi = dc;
    return true;
}

bool Gmail::lamMoiToken(std::string& loi) {
    if (token_.refresh_token.empty()) {
        loi = "Chưa có refresh token - cần đăng nhập lại tài khoản Gmail";
        return false;
    }
    if (!coUngDung()) {
        loi = "Chưa khai báo Client ID / Client Secret để làm mới token";
        return false;
    }
    std::map<std::string, std::string> f;
    f["client_id"] = client_id_;
    f["client_secret"] = client_secret_;
    f["refresh_token"] = token_.refresh_token;
    f["grant_type"] = "refresh_token";

    HttpPhanHoi pr = HttpClient::postForm(URL_TOKEN, f, timeout_);
    if (!pr.loi.empty()) { loi = pr.loi; return false; }
    Json j;
    if (!Json::phanTich(pr.than, j, nullptr)) {
        loi = "Google trả về dữ liệu không hợp lệ khi làm mới token";
        return false;
    }
    if (!docToken(j, token_, loi)) return false;
    NK.go("gmail", "Đã làm mới access token, hiệu lực đến " + dinhDangGioVN(token_.het_han));
    return true;
}

bool Gmail::damBaoToken(std::string& loi) {
    if (token_.conHan()) return true;
    if (token_.coRefresh()) return lamMoiToken(loi);
    if (!token_.access_token.empty()) return true;   // dùng tạm token thủ công
    loi = "Chưa đăng nhập Gmail";
    return false;
}

bool Gmail::thuHoi(std::string& loi) {
    std::string tk = token_.refresh_token.empty() ? token_.access_token : token_.refresh_token;
    if (tk.empty()) { token_ = TokenGmail(); return true; }
    std::map<std::string, std::string> f;
    f["token"] = tk;
    HttpPhanHoi pr = HttpClient::postForm(URL_THUHOI, f, 30);
    token_ = TokenGmail();
    if (!pr.loi.empty()) { loi = pr.loi; return false; }
    return true;
}

// =====================================================================
//  Gmail API
// =====================================================================
bool Gmail::goiApi(const std::string& url, Json& ra, std::string& loi) {
    if (!damBaoToken(loi)) return false;
    HttpPhanHoi pr = HttpClient::get(url, tieuDeXacThuc(), timeout_);

    // Token hết hạn giữa chừng -> làm mới rồi thử lại 1 lần
    if (pr.ma == 401 && token_.coRefresh()) {
        std::string l2;
        if (lamMoiToken(l2)) pr = HttpClient::get(url, tieuDeXacThuc(), timeout_);
    }
    if (!pr.loi.empty()) { loi = pr.loi; return false; }
    if (pr.than.empty()) { loi = "Gmail không trả về dữ liệu (HTTP " + std::to_string(pr.ma) + ")"; return false; }
    if (!Json::phanTich(pr.than, ra, nullptr)) {
        loi = "Gmail trả về dữ liệu không hợp lệ: " + catUtf8(pr.than, 300);
        return false;
    }
    if (ra.coKhoa("error")) {
        const Json& e = ra.lay("error");
        loi = "Gmail lỗi " + std::to_string(e.lay("code").nguyen(pr.ma)) + ": " +
              e.lay("message").chuoi("không rõ");
        return false;
    }
    if (pr.ma < 200 || pr.ma >= 300) {
        loi = "Gmail trả về HTTP " + std::to_string(pr.ma);
        return false;
    }
    return true;
}

bool Gmail::hoSo(std::string& diaChi, long long& tongMail, std::string& loi) {
    Json j;
    if (!goiApi(std::string(API_GOC) + "/profile", j, loi)) return false;
    diaChi = j.lay("emailAddress").chuoi();
    tongMail = j.lay("messagesTotal").nguyen();
    return true;
}

// Quét một lượt danh sách thư. Trả về số id đã thêm vào "ids".
// Bỏ qua id đã có trong "daCo" để hai lượt (hộp thư chính + Spam) không trùng nhau.
bool Gmail::quetMotLuot(const std::string& truyVan, int soLuong, bool trongSpam,
                        std::set<std::string>& daCo,
                        std::vector<std::string>& ids, std::string& loi) {
    std::string pageToken;
    int conLai = soLuong > 0 ? soLuong : 50;
    int soVong = 0;
    int daThem = 0;

    while (conLai > 0 && soVong < 40) {
        soVong++;
        int lay = std::min(conLai, 100);
        std::string url = std::string(API_GOC) + "/messages?maxResults=" + std::to_string(lay);

        // Gmail API mặc định includeSpamTrash=false nên thư trong Spam bị giấu hẳn.
        // Muốn thấy phải bật cờ này, VÀ thêm "in:spam" để không kéo luôn Thùng rác.
        std::string q = truyVan;
        if (trongSpam) {
            url += "&includeSpamTrash=true";
            q = q.empty() ? "in:spam" : (q + " in:spam");
        }
        if (!q.empty()) url += "&q=" + urlEncode(q);
        if (!pageToken.empty()) url += "&pageToken=" + urlEncode(pageToken);

        Json j;
        if (!goiApi(url, j, loi)) return false;
        const Json& ms = j.lay("messages");
        if (ms.soPhanTu() == 0) break;
        for (size_t i = 0; i < ms.soPhanTu(); i++) {
            std::string id = ms[i].lay("id").chuoi();
            if (id.empty() || !daCo.insert(id).second) continue;
            ids.push_back(id);
            daThem++;
        }
        conLai = soLuong - daThem;
        pageToken = j.lay("nextPageToken").chuoi();
        if (pageToken.empty()) break;
    }
    return true;
}

// Các tên miền nhét vào truy vấn Gmail để bắt thư "không tệp, chỉ có link".
// Ngắn hơn danh sách đầy đủ trong util.cpp: truy vấn Gmail có giới hạn độ dài,
// và bộ dò link mới là nơi quyết định cuối cùng - truy vấn chỉ là cái lưới thả rộng.
static const char* MIEN_TIM_KIEM[] = {
    "drive.google.com", "docs.google.com", "1drv.ms", "onedrive.live.com",
    "sharepoint.com", "dropbox.com", "mega.nz", "wetransfer.com",
    nullptr
};

std::string Gmail::moRongTruyVanLink(const std::string& truyVan) {
    // Chỉ nới khi truy vấn đang lọc "has:attachment" - vì chính điều kiện đó
    // loại thẳng thư chỉ dán link. Truy vấn không lọc theo tệp thì đã lấy đủ rồi.
    const std::string moc = "has:attachment";
    std::string thap = toLower(truyVan);
    size_t p = 0, tim = std::string::npos;
    while ((p = thap.find(moc, p)) != std::string::npos) {
        size_t sau = p + moc.size();
        char t = (p == 0) ? ' ' : thap[p - 1];              // '-has:attachment' thì bỏ qua
        char s = (sau >= thap.size()) ? ' ' : thap[sau];
        if ((t == ' ' || t == '(') && (s == ' ' || s == ')')) { tim = p; break; }
        p = sau;
    }
    if (tim == std::string::npos) return truyVan;

    std::string ve = "(has:attachment";
    for (int i = 0; MIEN_TIM_KIEM[i]; i++) ve += std::string(" OR \"") + MIEN_TIM_KIEM[i] + "\"";
    ve += ")";
    return truyVan.substr(0, tim) + ve + truyVan.substr(tim + moc.size());
}

bool Gmail::danhSachMail(const std::string& truyVan, int soLuong, bool gomSpam, bool nhanLink,
                         std::vector<std::string>& ids, std::string& loi,
                         std::string* canhBao) {
    ids.clear();
    std::set<std::string> daCo;

    // Nới truy vấn ngay từ đầu thay vì quét thêm một lượt riêng: cả hộp thư chính
    // lẫn hộp Thư rác đều được lợi, và không đội thêm hạn mức mail mỗi lần.
    const std::string q = nhanLink ? moRongTruyVanLink(truyVan) : truyVan;

    if (!quetMotLuot(q, soLuong, false, daCo, ids, loi)) return false;

    // Lượt hai: hộp Thư rác. Trường gửi báo cáo hay bị Google xếp nhầm vào đây;
    // bỏ qua thì thống kê sẽ báo "chưa nộp" oan cho trường.
    //
    // Hạn mức lượt hai = phần còn thừa của hạn mức chung, nhưng không bao giờ
    // dưới một mức sàn. Cho hẳn thêm một hạn mức đầy đủ thì buổi nhiều thư rác
    // sẽ nuốt gấp đôi số mail người dùng đặt; ngược lại chỉ lấy phần thừa thì
    // hộp thư chính đông là hộp Thư rác bị bỏ quên mãi mãi - đúng cái lỗi đang sửa.
    if (gomSpam) {
        int san = soLuong / 5;
        if (san < 5) san = 5;
        int conLai = soLuong - (int)ids.size();
        if (conLai < san) conLai = san;

        std::string loiSpam;
        if (!quetMotLuot(q, conLai, true, daCo, ids, loiSpam)) {
            // Hộp thư chính đã quét xong, không để lỗi ở Spam làm hỏng cả phiên
            if (canhBao) *canhBao = "Không quét được hộp Thư rác: " + loiSpam;
        }
    }
    return true;
}

bool Gmail::layMail(const std::string& id, Json& ra, std::string& loi) {
    return goiApi(std::string(API_GOC) + "/messages/" + urlEncode(id) + "?format=full", ra, loi);
}

bool Gmail::layTepDinhKem(const std::string& idMail, const std::string& idTep,
                          std::string& duLieu, std::string& loi) {
    Json j;
    std::string url = std::string(API_GOC) + "/messages/" + urlEncode(idMail) +
                      "/attachments/" + urlEncode(idTep);
    if (!goiApi(url, j, loi)) return false;
    std::string d = j.lay("data").chuoi();
    if (d.empty()) { loi = "Gmail trả về tệp rỗng"; return false; }
    duLieu = base64Decode(d);
    return true;
}

// =====================================================================
//  Giải mã tiêu đề MIME (RFC 2047) và bảng mã
// =====================================================================
std::string Gmail::sangUtf8(const std::string& s, const std::string& bangMaIn) {
    std::string bm = toUpper(trim(bangMaIn));
    bm = replaceAll(bm, "-", "");
    bm = replaceAll(bm, "_", "");
    if (bm.empty() || bm == "UTF8" || bm == "USASCII" || bm == "ASCII") return s;
    if (bm == "ISO88591" || bm == "LATIN1" || bm == "WINDOWS1252" || bm == "CP1252" ||
        bm == "ISO885915" || bm == "WINDOWS1258" || bm == "CP1258") {
        // Chuyển từng byte thành code point tương ứng (đủ dùng cho phần lớn tiêu đề)
        std::string out;
        out.reserve(s.size() * 2);
        for (unsigned char c : s) {
            uint32_t cp = c;
            if (bm == "WINDOWS1252" || bm == "CP1252") {
                static const uint16_t bang[32] = {
                    0x20AC,0x0081,0x201A,0x0192,0x201E,0x2026,0x2020,0x2021,
                    0x02C6,0x2030,0x0160,0x2039,0x0152,0x008D,0x017D,0x008F,
                    0x0090,0x2018,0x2019,0x201C,0x201D,0x2022,0x2013,0x2014,
                    0x02DC,0x2122,0x0161,0x203A,0x0153,0x009D,0x017E,0x0178
                };
                if (c >= 0x80 && c <= 0x9F) cp = bang[c - 0x80];
            }
            if (cp < 0x80) out += char(cp);
            else if (cp < 0x800) {
                out += char(0xC0 | (cp >> 6));
                out += char(0x80 | (cp & 0x3F));
            } else {
                out += char(0xE0 | (cp >> 12));
                out += char(0x80 | ((cp >> 6) & 0x3F));
                out += char(0x80 | (cp & 0x3F));
            }
        }
        return out;
    }
    return s;   // bảng mã khác: giữ nguyên
}

static std::string giaiMaQPTieuDe(const std::string& s) {
    // Trong RFC 2047 dạng Q, dấu '_' đại diện cho khoảng trắng
    std::string t = replaceAll(s, "_", " ");
    return giaiMaQuotedPrintable(t);
}

std::string Gmail::giaiMaTieuDeMime(const std::string& sIn) {
    std::string s = sIn;
    std::string out;
    size_t i = 0;
    bool truocLaTuMaHoa = false;

    while (i < s.size()) {
        size_t bd = s.find("=?", i);
        if (bd == std::string::npos) { out += s.substr(i); break; }

        // Khoảng trắng giữa hai từ mã hoá liền nhau phải bị loại bỏ
        std::string giua = s.substr(i, bd - i);
        if (!(truocLaTuMaHoa && trim(giua).empty())) out += giua;

        size_t c1 = s.find('?', bd + 2);
        if (c1 == std::string::npos) { out += s.substr(bd); break; }
        size_t c2 = s.find('?', c1 + 1);
        if (c2 == std::string::npos) { out += s.substr(bd); break; }
        size_t kt = s.find("?=", c2 + 1);
        if (kt == std::string::npos) { out += s.substr(bd); break; }

        std::string bangMa = s.substr(bd + 2, c1 - bd - 2);
        std::string kieu = toUpper(s.substr(c1 + 1, c2 - c1 - 1));
        std::string noiDung = s.substr(c2 + 1, kt - c2 - 1);

        // Bỏ hậu tố ngôn ngữ dạng UTF-8*vi
        size_t sao = bangMa.find('*');
        if (sao != std::string::npos) bangMa = bangMa.substr(0, sao);

        std::string giai;
        if (kieu == "B") giai = base64Decode(noiDung);
        else if (kieu == "Q") giai = giaiMaQPTieuDe(noiDung);
        else giai = noiDung;

        out += sangUtf8(giai, bangMa);
        truocLaTuMaHoa = true;
        i = kt + 2;
    }
    return locUtf8(out);
}

// =====================================================================
//  Phân tích cấu trúc mail
// =====================================================================
static std::string layTieuDe(const Json& headers, const std::string& ten) {
    for (size_t i = 0; i < headers.soPhanTu(); i++) {
        if (toLower(headers[i].lay("name").chuoi()) == toLower(ten))
            return headers[i].lay("value").chuoi();
    }
    return "";
}

static std::string thamSoMime(const std::string& giaTri, const std::string& ten) {
    // Tìm ten="..." hoặc ten=... trong chuỗi Content-Type / Content-Disposition
    std::string low = toLower(giaTri);
    std::string t = toLower(ten);
    size_t p = 0;
    while ((p = low.find(t, p)) != std::string::npos) {
        size_t q = p + t.size();
        while (q < giaTri.size() && (giaTri[q] == ' ' || giaTri[q] == '\t')) q++;
        if (q < giaTri.size() && giaTri[q] == '*') q++;   // dạng RFC 2231: name*=
        while (q < giaTri.size() && (giaTri[q] == ' ' || giaTri[q] == '\t')) q++;
        if (q >= giaTri.size() || giaTri[q] != '=') { p = q; continue; }
        q++;
        while (q < giaTri.size() && (giaTri[q] == ' ' || giaTri[q] == '\t')) q++;
        if (q < giaTri.size() && giaTri[q] == '"') {
            size_t e = giaTri.find('"', q + 1);
            if (e == std::string::npos) return giaTri.substr(q + 1);
            return giaTri.substr(q + 1, e - q - 1);
        }
        size_t e = giaTri.find_first_of(";\r\n", q);
        return trim(e == std::string::npos ? giaTri.substr(q) : giaTri.substr(q, e - q));
    }
    return "";
}

// Giải mã tên tệp dạng RFC 2231: UTF-8''T%C3%AAn%20t%E1%BB%87p
static std::string giaiMaTenTep(const std::string& s) {
    std::string t = trim(s);
    if (t.empty()) return t;
    size_t p1 = t.find("''");
    if (p1 != std::string::npos && p1 <= 24) {
        std::string bangMa = t.substr(0, p1);
        size_t sao = bangMa.find('*');
        if (sao != std::string::npos) bangMa = bangMa.substr(0, sao);
        std::string nd = urlDecode(t.substr(p1 + 2));
        return Gmail::sangUtf8(nd, bangMa);
    }
    if (t.find("=?") != std::string::npos) return Gmail::giaiMaTieuDeMime(t);
    return t;
}

static void duyetPhan(const Json& phan, BanGhiEmail& em, int mucSau) {
    if (mucSau > 24) return;

    std::string mime = toLower(phan.lay("mimeType").chuoi());
    const Json& headers = phan.lay("headers");
    std::string ctype = layTieuDe(headers, "Content-Type");
    std::string cdisp = layTieuDe(headers, "Content-Disposition");
    std::string tenTep = phan.lay("filename").chuoi();
    if (tenTep.empty()) tenTep = thamSoMime(cdisp, "filename");
    if (tenTep.empty()) tenTep = thamSoMime(ctype, "name");
    tenTep = giaiMaTenTep(tenTep);

    const Json& body = phan.lay("body");
    std::string attId = body.lay("attachmentId").chuoi();
    long long coTep = body.lay("size").nguyen(0);
    std::string duLieu = body.lay("data").chuoi();

    bool laDinhKem = !attId.empty() ||
                     (!tenTep.empty() && (startsWith(toLower(trim(cdisp)), "attachment") ||
                                          startsWith(toLower(trim(cdisp)), "inline") ||
                                          mime.find("text/") != 0));

    const Json& parts = phan.lay("parts");
    if (parts.soPhanTu() > 0) {
        for (size_t i = 0; i < parts.soPhanTu(); i++) duyetPhan(parts[i], em, mucSau + 1);
        if (!laDinhKem) return;
    }

    if (laDinhKem && !tenTep.empty()) {
        TepDinhKem t;
        t.ten = locUtf8(tenTep);
        t.mime = phan.lay("mimeType").chuoi(doanMimeTuTen(tenTep));
        t.dung_luong = coTep;
        t.gmail_attachment_id = attId;
        t.part_id = phan.lay("partId").chuoi();
        t.thu_tu = (int)em.tep.size();
        if (attId.empty() && !duLieu.empty()) {
            t.du_lieu = base64Decode(duLieu);
            t.dung_luong = (long long)t.du_lieu.size();
            t.da_tai = true;
        }
        em.tep.push_back(t);
        return;
    }

    if (duLieu.empty()) return;
    std::string bangMa = thamSoMime(ctype, "charset");
    std::string nd = Gmail::sangUtf8(base64Decode(duLieu), bangMa);
    if (mime == "text/plain") {
        if (em.noi_dung_text.size() < GIOI_HAN_THAN)
            em.noi_dung_text += (em.noi_dung_text.empty() ? "" : "\n") + locUtf8(nd);
    } else if (mime == "text/html") {
        if (em.noi_dung_html.size() < GIOI_HAN_THAN)
            em.noi_dung_html += locUtf8(nd);
    }
}

// Bỏ thẻ HTML để lấy văn bản thô (dùng khi mail chỉ có phần HTML)
static std::string boTheHtml(const std::string& html) {
    std::string out;
    bool trongThe = false;
    for (size_t i = 0; i < html.size(); i++) {
        char c = html[i];
        if (c == '<') {
            // bỏ trọn khối <script> và <style>
            std::string con = toLower(html.substr(i, 8));
            if (startsWith(con, "<script")) {
                size_t e = toLower(html).find("</script>", i);
                i = (e == std::string::npos) ? html.size() : e + 8;
                continue;
            }
            if (startsWith(con, "<style")) {
                size_t e = toLower(html).find("</style>", i);
                i = (e == std::string::npos) ? html.size() : e + 7;
                continue;
            }
            trongThe = true;
            continue;
        }
        if (c == '>') { trongThe = false; out += ' '; continue; }
        if (!trongThe) out += c;
    }
    out = replaceAll(out, "&nbsp;", " ");
    out = replaceAll(out, "&amp;", "&");
    out = replaceAll(out, "&lt;", "<");
    out = replaceAll(out, "&gt;", ">");
    out = replaceAll(out, "&quot;", "\"");
    out = replaceAll(out, "&#39;", "'");
    return out;
}

void Gmail::phanTichMail(const Json& j, BanGhiEmail& em) {
    em.gmail_id = j.lay("id").chuoi();
    em.thread_id = j.lay("threadId").chuoi();
    em.doan_trich = locUtf8(j.lay("snippet").chuoi());

    // Nhãn của Gmail - dùng để biết thư nằm ở hộp thư chính hay hộp Thư rác
    em.nhan_gmail.clear();
    const Json& nhan = j.lay("labelIds");
    for (size_t i = 0; i < nhan.soPhanTu(); i++) {
        std::string n = nhan[i].chuoi();
        if (!n.empty()) em.nhan_gmail.push_back(n);
    }

    const Json& payload = j.lay("payload");
    const Json& headers = payload.lay("headers");

    em.tieu_de = giaiMaTieuDeMime(layTieuDe(headers, "Subject"));
    em.message_id_header = trim(layTieuDe(headers, "Message-ID"));
    em.nguoi_nhan = giaiMaTieuDeMime(layTieuDe(headers, "To"));

    std::string from = giaiMaTieuDeMime(layTieuDe(headers, "From"));
    // Tách "Tên hiển thị <dia@chi>"
    size_t lt = from.rfind('<');
    size_t gt = from.rfind('>');
    if (lt != std::string::npos && gt != std::string::npos && gt > lt) {
        em.nguoi_gui = toLower(trim(from.substr(lt + 1, gt - lt - 1)));
        std::string ten = trim(from.substr(0, lt));
        if (ten.size() >= 2 && ten.front() == '"' && ten.back() == '"') ten = ten.substr(1, ten.size() - 2);
        em.ten_nguoi_gui = trim(ten);
    } else {
        em.nguoi_gui = toLower(trim(from));
    }

    // Thời gian: ưu tiên internalDate (mili giây) rồi tới header Date
    long long internal = j.lay("internalDate").nguyen(0);
    if (internal > 0) em.ngay_gui = internal / 1000;
    else em.ngay_gui = phanTichNgayRfc2822(layTieuDe(headers, "Date"));
    if (em.ngay_gui <= 0) em.ngay_gui = nowEpoch();
    em.ngay_nhan = nowEpoch();

    duyetPhan(payload, em, 0);

    if (em.noi_dung_text.empty() && !em.noi_dung_html.empty())
        em.noi_dung_text = trim(catUtf8(boTheHtml(em.noi_dung_html), GIOI_HAN_THAN));
    if (em.doan_trich.empty())
        em.doan_trich = catUtf8(trim(em.noi_dung_text), 480);

    // Nhiều trường không đính kèm tệp mà dán link Google Drive/OneDrive vào
    // thân thư. Quét lấy các link đó để người xử lý còn biết mà mở, đồng thời
    // biết rằng bản thân tệp KHÔNG nằm trong kho.
    em.lien_ket_ngoai = timLienKetChiaSe(em.noi_dung_text, em.noi_dung_html);

    for (size_t i = 0; i < em.tep.size(); i++) em.tep[i].thu_tu = (int)i;
}

} // namespace mr
