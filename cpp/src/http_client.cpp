// =====================================================================
//  http_client.cpp - Trình khách HTTPS đa nền tảng
// =====================================================================
#include "http_client.h"
#include "util.h"

#include <cstring>
#include <cstdio>
#include <mutex>

#ifdef _WIN32
  #ifndef WIN32_LEAN_AND_MEAN
    #define WIN32_LEAN_AND_MEAN
  #endif
  #include <windows.h>
  #include <winhttp.h>
#else
  #include <dlfcn.h>
#endif

namespace mr {

std::string HttpPhanHoi::moTa() const {
    if (!loi.empty()) return loi;
    char buf[64];
    std::snprintf(buf, sizeof(buf), "HTTP %ld", ma);
    std::string r = buf;
    if (!than.empty()) r += ": " + catUtf8(than, 500);
    return r;
}

static void luuTieuDe(HttpPhanHoi& pr, const std::string& raw) {
    for (const auto& dong : split(raw, '\n')) {
        std::string d = trim(dong);
        if (d.empty()) continue;
        size_t c = d.find(':');
        if (c == std::string::npos) continue;
        pr.tieuDe[toLower(trim(d.substr(0, c)))] = trim(d.substr(c + 1));
    }
}

// =====================================================================
//                            WINDOWS - WinHTTP
// =====================================================================
#ifdef _WIN32

static std::wstring sangW(const std::string& s) {
    if (s.empty()) return L"";
    int n = MultiByteToWideChar(CP_UTF8, 0, s.c_str(), (int)s.size(), nullptr, 0);
    std::wstring w(n, 0);
    MultiByteToWideChar(CP_UTF8, 0, s.c_str(), (int)s.size(), &w[0], n);
    return w;
}

static std::string sangU8(const std::wstring& w) {
    if (w.empty()) return "";
    int n = WideCharToMultiByte(CP_UTF8, 0, w.c_str(), (int)w.size(), nullptr, 0, nullptr, nullptr);
    std::string s(n, 0);
    WideCharToMultiByte(CP_UTF8, 0, w.c_str(), (int)w.size(), &s[0], n, nullptr, nullptr);
    return s;
}

static std::string loiWin(DWORD e) {
    char* buf = nullptr;
    DWORD n = FormatMessageA(FORMAT_MESSAGE_ALLOCATE_BUFFER | FORMAT_MESSAGE_FROM_SYSTEM |
                             FORMAT_MESSAGE_IGNORE_INSERTS | FORMAT_MESSAGE_FROM_HMODULE,
                             GetModuleHandleA("winhttp.dll"), e,
                             MAKELANGID(LANG_NEUTRAL, SUBLANG_DEFAULT), (LPSTR)&buf, 0, nullptr);
    std::string r;
    if (n && buf) { r.assign(buf, n); LocalFree(buf); }
    if (r.empty()) {
        char tmp[64];
        std::snprintf(tmp, sizeof(tmp), "mã lỗi WinHTTP %lu", (unsigned long)e);
        r = tmp;
    }
    return trim(r);
}

HttpPhanHoi HttpClient::gui(const HttpYeuCau& yc) {
    HttpPhanHoi pr;
    int64_t t0 = nowEpochMs();

    std::wstring wurl = sangW(yc.url);
    URL_COMPONENTS uc;
    std::memset(&uc, 0, sizeof(uc));
    uc.dwStructSize = sizeof(uc);
    wchar_t host[512] = {0}, duong[4096] = {0}, extra[4096] = {0};
    uc.lpszHostName = host;      uc.dwHostNameLength = 511;
    uc.lpszUrlPath = duong;      uc.dwUrlPathLength = 4095;
    uc.lpszExtraInfo = extra;    uc.dwExtraInfoLength = 4095;

    if (!WinHttpCrackUrl(wurl.c_str(), (DWORD)wurl.size(), 0, &uc)) {
        pr.loi = "Địa chỉ không hợp lệ: " + yc.url;
        return pr;
    }

    HINTERNET hSession = WinHttpOpen(L"MailRouter/1.0",
                                     WINHTTP_ACCESS_TYPE_DEFAULT_PROXY,
                                     WINHTTP_NO_PROXY_NAME, WINHTTP_NO_PROXY_BYPASS, 0);
    if (!hSession) { pr.loi = "Không khởi tạo được WinHTTP: " + loiWin(GetLastError()); return pr; }

    DWORD ms = (DWORD)(yc.timeout > 0 ? yc.timeout * 1000 : 60000);
    WinHttpSetTimeouts(hSession, (int)ms, (int)ms, (int)ms, (int)ms);

    // Cho phép TLS 1.2 / 1.3
    DWORD giaoThuc = WINHTTP_FLAG_SECURE_PROTOCOL_TLS1_2;
#ifdef WINHTTP_FLAG_SECURE_PROTOCOL_TLS1_3
    giaoThuc |= WINHTTP_FLAG_SECURE_PROTOCOL_TLS1_3;
#endif
    WinHttpSetOption(hSession, WINHTTP_OPTION_SECURE_PROTOCOLS, &giaoThuc, sizeof(giaoThuc));

    HINTERNET hConnect = WinHttpConnect(hSession, uc.lpszHostName, uc.nPort, 0);
    if (!hConnect) {
        pr.loi = "Không kết nối được máy chủ: " + loiWin(GetLastError());
        WinHttpCloseHandle(hSession);
        return pr;
    }

    std::wstring duongDay = duong;
    if (extra[0]) duongDay += extra;
    if (duongDay.empty()) duongDay = L"/";

    DWORD co = (uc.nScheme == INTERNET_SCHEME_HTTPS) ? WINHTTP_FLAG_SECURE : 0;
    if (!yc.theo_chuyen_huong) co |= WINHTTP_FLAG_REFRESH;

    HINTERNET hReq = WinHttpOpenRequest(hConnect, sangW(yc.phuong_thuc).c_str(), duongDay.c_str(),
                                        nullptr, WINHTTP_NO_REFERER,
                                        WINHTTP_DEFAULT_ACCEPT_TYPES, co);
    if (!hReq) {
        pr.loi = "Không tạo được yêu cầu HTTP: " + loiWin(GetLastError());
        WinHttpCloseHandle(hConnect);
        WinHttpCloseHandle(hSession);
        return pr;
    }

    if (!yc.theo_chuyen_huong) {
        DWORD kh = WINHTTP_DISABLE_REDIRECTS;
        WinHttpSetOption(hReq, WINHTTP_OPTION_DISABLE_FEATURE, &kh, sizeof(kh));
    }

    std::string tieuDeGop;
    for (const auto& h : yc.tieu_de) {
        if (trim(h).empty()) continue;
        tieuDeGop += h + "\r\n";
    }
    std::wstring wHeaders = sangW(tieuDeGop);

    BOOL ok = WinHttpSendRequest(hReq,
                                 wHeaders.empty() ? WINHTTP_NO_ADDITIONAL_HEADERS : wHeaders.c_str(),
                                 wHeaders.empty() ? 0 : (DWORD)-1L,
                                 yc.than.empty() ? WINHTTP_NO_REQUEST_DATA : (LPVOID)yc.than.data(),
                                 (DWORD)yc.than.size(), (DWORD)yc.than.size(), 0);
    if (ok) ok = WinHttpReceiveResponse(hReq, nullptr);
    if (!ok) {
        pr.loi = "Lỗi gửi/nhận HTTP: " + loiWin(GetLastError());
        WinHttpCloseHandle(hReq);
        WinHttpCloseHandle(hConnect);
        WinHttpCloseHandle(hSession);
        return pr;
    }

    DWORD maTT = 0, sz = sizeof(maTT);
    WinHttpQueryHeaders(hReq, WINHTTP_QUERY_STATUS_CODE | WINHTTP_QUERY_FLAG_NUMBER,
                        WINHTTP_HEADER_NAME_BY_INDEX, &maTT, &sz, WINHTTP_NO_HEADER_INDEX);
    pr.ma = (long)maTT;

    // Tiêu đề thô
    DWORD szH = 0;
    WinHttpQueryHeaders(hReq, WINHTTP_QUERY_RAW_HEADERS_CRLF, WINHTTP_HEADER_NAME_BY_INDEX,
                        nullptr, &szH, WINHTTP_NO_HEADER_INDEX);
    if (szH > 0) {
        std::wstring wh(szH / sizeof(wchar_t) + 1, 0);
        if (WinHttpQueryHeaders(hReq, WINHTTP_QUERY_RAW_HEADERS_CRLF, WINHTTP_HEADER_NAME_BY_INDEX,
                                &wh[0], &szH, WINHTTP_NO_HEADER_INDEX)) {
            luuTieuDe(pr, sangU8(wh.c_str()));
        }
    }

    // Thân
    for (;;) {
        DWORD sanCo = 0;
        if (!WinHttpQueryDataAvailable(hReq, &sanCo)) {
            pr.loi = "Lỗi đọc dữ liệu HTTP: " + loiWin(GetLastError());
            break;
        }
        if (sanCo == 0) break;
        std::string buf;
        buf.resize(sanCo);
        DWORD daDoc = 0;
        if (!WinHttpReadData(hReq, &buf[0], sanCo, &daDoc)) {
            pr.loi = "Lỗi đọc dữ liệu HTTP: " + loiWin(GetLastError());
            break;
        }
        if (daDoc == 0) break;
        pr.than.append(buf.data(), daDoc);
    }

    WinHttpCloseHandle(hReq);
    WinHttpCloseHandle(hConnect);
    WinHttpCloseHandle(hSession);
    pr.giay = (nowEpochMs() - t0) / 1000.0;
    return pr;
}

bool HttpClient::sanSang(std::string& loi) { (void)loi; return true; }
std::string HttpClient::tenNenTang() { return "WinHTTP"; }

// =====================================================================
//                            LINUX - libcurl
// =====================================================================
#else

// Khai báo tối thiểu của libcurl (không cần tệp tiêu đề của libcurl)
typedef void CURL;
typedef int  CURLcode;
struct curl_slist;

enum {
    MR_CURLOPT_URL              = 10002,
    MR_CURLOPT_WRITEFUNCTION    = 20011,
    MR_CURLOPT_WRITEDATA        = 10001,
    MR_CURLOPT_HEADERFUNCTION   = 20079,
    MR_CURLOPT_HEADERDATA       = 10029,
    MR_CURLOPT_HTTPHEADER       = 10023,
    MR_CURLOPT_POSTFIELDS       = 10015,
    MR_CURLOPT_POSTFIELDSIZE_LARGE = 30120,
    MR_CURLOPT_CUSTOMREQUEST    = 10036,
    MR_CURLOPT_TIMEOUT_MS       = 155,
    MR_CURLOPT_CONNECTTIMEOUT_MS= 156,
    MR_CURLOPT_FOLLOWLOCATION   = 52,
    MR_CURLOPT_MAXREDIRS        = 68,
    MR_CURLOPT_USERAGENT        = 10018,
    MR_CURLOPT_ACCEPT_ENCODING  = 10102,
    MR_CURLOPT_NOSIGNAL         = 99,
    MR_CURLOPT_SSL_VERIFYPEER   = 64,
    MR_CURLOPT_SSL_VERIFYHOST   = 81,
    MR_CURLOPT_CAINFO           = 10065,
    MR_CURLOPT_HTTPGET          = 80,
    MR_CURLOPT_NOBODY           = 44,
    MR_CURLINFO_RESPONSE_CODE   = 0x200002,
    MR_CURLINFO_TOTAL_TIME      = 0x300003
};

namespace {

struct CurlApi {
    void* thuVien = nullptr;
    CURLcode (*global_init)(long) = nullptr;
    CURL*    (*easy_init)() = nullptr;
    CURLcode (*easy_setopt)(CURL*, int, ...) = nullptr;
    CURLcode (*easy_perform)(CURL*) = nullptr;
    void     (*easy_cleanup)(CURL*) = nullptr;
    CURLcode (*easy_getinfo)(CURL*, int, ...) = nullptr;
    const char* (*easy_strerror)(CURLcode) = nullptr;
    struct curl_slist* (*slist_append)(struct curl_slist*, const char*) = nullptr;
    void (*slist_free_all)(struct curl_slist*) = nullptr;
    std::string loiNap;
    bool ok = false;
};

CurlApi& api() {
    static CurlApi a;
    static std::once_flag co;
    std::call_once(co, [&]() {
        const char* ungVien[] = {
            "libcurl.so.4", "libcurl.so", "libcurl.so.3",
            "libcurl-gnutls.so.4", "libcurl-nss.so.4", nullptr
        };
        for (int i = 0; ungVien[i]; i++) {
            a.thuVien = dlopen(ungVien[i], RTLD_LAZY | RTLD_LOCAL);
            if (a.thuVien) break;
        }
        if (!a.thuVien) {
            a.loiNap = "Không tìm thấy thư viện libcurl. Hãy cài đặt: "
                       "sudo apt install libcurl4  (Debian/Ubuntu) hoặc "
                       "sudo yum install libcurl  (CentOS/RHEL)";
            return;
        }
        #define NAP(bien, ten) \
            *(void**)(&a.bien) = dlsym(a.thuVien, ten); \
            if (!a.bien) { a.loiNap = std::string("Thiếu hàm ") + ten + " trong libcurl"; return; }
        NAP(global_init,    "curl_global_init")
        NAP(easy_init,      "curl_easy_init")
        NAP(easy_setopt,    "curl_easy_setopt")
        NAP(easy_perform,   "curl_easy_perform")
        NAP(easy_cleanup,   "curl_easy_cleanup")
        NAP(easy_getinfo,   "curl_easy_getinfo")
        NAP(easy_strerror,  "curl_easy_strerror")
        NAP(slist_append,   "curl_slist_append")
        NAP(slist_free_all, "curl_slist_free_all")
        #undef NAP
        a.global_init(3);          // CURL_GLOBAL_DEFAULT
        a.ok = true;
    });
    return a;
}

size_t ghiThan(char* ptr, size_t sz, size_t nm, void* ud) {
    std::string* s = (std::string*)ud;
    s->append(ptr, sz * nm);
    return sz * nm;
}

size_t ghiTieuDe(char* ptr, size_t sz, size_t nm, void* ud) {
    std::string* s = (std::string*)ud;
    s->append(ptr, sz * nm);
    return sz * nm;
}

} // namespace

HttpPhanHoi HttpClient::gui(const HttpYeuCau& yc) {
    HttpPhanHoi pr;
    int64_t t0 = nowEpochMs();
    CurlApi& a = api();
    if (!a.ok) { pr.loi = a.loiNap; return pr; }

    CURL* c = a.easy_init();
    if (!c) { pr.loi = "Không khởi tạo được libcurl"; return pr; }

    std::string tieuDeTho;
    struct curl_slist* hs = nullptr;
    for (const auto& h : yc.tieu_de) {
        if (trim(h).empty()) continue;
        hs = a.slist_append(hs, h.c_str());
    }
    // Tắt Expect: 100-continue để tránh chậm khi POST lớn
    hs = a.slist_append(hs, "Expect:");

    a.easy_setopt(c, MR_CURLOPT_URL, yc.url.c_str());
    a.easy_setopt(c, MR_CURLOPT_WRITEFUNCTION, (void*)&ghiThan);
    a.easy_setopt(c, MR_CURLOPT_WRITEDATA, (void*)&pr.than);
    a.easy_setopt(c, MR_CURLOPT_HEADERFUNCTION, (void*)&ghiTieuDe);
    a.easy_setopt(c, MR_CURLOPT_HEADERDATA, (void*)&tieuDeTho);
    a.easy_setopt(c, MR_CURLOPT_HTTPHEADER, (void*)hs);
    a.easy_setopt(c, MR_CURLOPT_USERAGENT, "MailRouter/1.0");
    a.easy_setopt(c, MR_CURLOPT_ACCEPT_ENCODING, "");
    a.easy_setopt(c, MR_CURLOPT_NOSIGNAL, 1L);
    a.easy_setopt(c, MR_CURLOPT_FOLLOWLOCATION, yc.theo_chuyen_huong ? 1L : 0L);
    a.easy_setopt(c, MR_CURLOPT_MAXREDIRS, 5L);
    long tms = (long)(yc.timeout > 0 ? yc.timeout : 60) * 1000L;
    a.easy_setopt(c, MR_CURLOPT_TIMEOUT_MS, tms);
    a.easy_setopt(c, MR_CURLOPT_CONNECTTIMEOUT_MS, (long)(tms > 30000 ? 30000 : tms));

    // Chứng chỉ CA tuỳ chọn (một số môi trường doanh nghiệp cần)
    const char* ca = ::getenv("MAILROUTER_CA_BUNDLE");
    if (!ca) ca = ::getenv("CURL_CA_BUNDLE");
    if (ca && *ca) a.easy_setopt(c, MR_CURLOPT_CAINFO, ca);

    std::string pt = toUpper(yc.phuong_thuc);
    if (pt != "GET") a.easy_setopt(c, MR_CURLOPT_CUSTOMREQUEST, pt.c_str());
    if (pt == "HEAD") a.easy_setopt(c, MR_CURLOPT_NOBODY, 1L);
    if (!yc.than.empty()) {
        a.easy_setopt(c, MR_CURLOPT_POSTFIELDS, yc.than.data());
        a.easy_setopt(c, MR_CURLOPT_POSTFIELDSIZE_LARGE, (long long)yc.than.size());
    }

    CURLcode rc = a.easy_perform(c);
    if (rc != 0) {
        const char* m = a.easy_strerror ? a.easy_strerror(rc) : nullptr;
        pr.loi = std::string("Lỗi kết nối HTTP: ") + (m ? m : "không rõ");
    } else {
        long ma = 0;
        a.easy_getinfo(c, MR_CURLINFO_RESPONSE_CODE, &ma);
        pr.ma = ma;
        luuTieuDe(pr, tieuDeTho);
    }

    if (hs) a.slist_free_all(hs);
    a.easy_cleanup(c);
    pr.giay = (nowEpochMs() - t0) / 1000.0;
    return pr;
}

bool HttpClient::sanSang(std::string& loi) {
    CurlApi& a = api();
    if (!a.ok) { loi = a.loiNap; return false; }
    return true;
}

std::string HttpClient::tenNenTang() { return "libcurl"; }

#endif  // _WIN32

// =====================================================================
//                        Hàm tiện ích chung
// =====================================================================
HttpPhanHoi HttpClient::get(const std::string& url, const std::vector<std::string>& tieuDe, int timeout) {
    HttpYeuCau yc;
    yc.phuong_thuc = "GET";
    yc.url = url;
    yc.tieu_de = tieuDe;
    yc.timeout = timeout;
    return gui(yc);
}

HttpPhanHoi HttpClient::post(const std::string& url, const std::string& than,
                             const std::vector<std::string>& tieuDe, int timeout) {
    HttpYeuCau yc;
    yc.phuong_thuc = "POST";
    yc.url = url;
    yc.than = than;
    yc.tieu_de = tieuDe;
    yc.timeout = timeout;
    return gui(yc);
}

HttpPhanHoi HttpClient::postJson(const std::string& url, const std::string& json,
                                 const std::vector<std::string>& tieuDe, int timeout) {
    std::vector<std::string> h = tieuDe;
    h.push_back("Content-Type: application/json; charset=utf-8");
    return post(url, json, h, timeout);
}

HttpPhanHoi HttpClient::postForm(const std::string& url,
                                 const std::map<std::string, std::string>& truong, int timeout) {
    std::string than;
    for (const auto& kv : truong) {
        if (!than.empty()) than += "&";
        than += urlEncode(kv.first) + "=" + urlEncode(kv.second);
    }
    return post(url, than, {"Content-Type: application/x-www-form-urlencoded"}, timeout);
}

} // namespace mr
