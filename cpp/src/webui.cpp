// =====================================================================
//  webui.cpp - Bộ định tuyến giao diện đồ hoạ
// =====================================================================
#include "webui.h"
#include "crypto.h"
#include "util.h"
#include "nhatky.h"
#include "http_client.h"

#include <cstring>
#include <algorithm>

namespace mr {

extern const char* PHIEN_BAN_UNG_DUNG;
extern const char* PHIEN_BAN_DAY_DU;
extern const char* NGAY_BUILD;
extern const int SO_BUILD;

GiaoDienWeb::~GiaoDienWeb() { dung(); }

bool GiaoDienWeb::batDau(std::string& loi) {
    std::string diaChi = ud_.cauHinh().chuoi("ung_dung.dia_chi_giao_dien", "127.0.0.1");
    int cong = (int)ud_.cauHinh().nguyen("ung_dung.cong_giao_dien", 8899);
    may_.datBoXuLy([this](const YeuCauMay& yc, PhanHoiMay& ph) { this->dinhTuyen(yc, ph); });

    if (!may_.batDau(diaChi, cong, loi)) {
        // Cổng bận -> thử cổng ngẫu nhiên do hệ điều hành cấp
        NK.canhBao("giao_dien", loi + " Đang thử cổng khác...");
        if (!may_.batDau(diaChi, 0, loi)) return false;
    }
    ud_.datCongGiaoDien(may_.cong());
    state_oauth_ = chuoiNgauNhien(16);
    return true;
}

void GiaoDienWeb::dung() {
    may_.dung();
    if (luongDongBo_.joinable()) luongDongBo_.join();
}

void GiaoDienWeb::traJson(PhanHoiMay& ph, const Json& j) {
    ph.ma = 200;
    ph.json(j.ketXuat());
}

void GiaoDienWeb::traLoi(PhanHoiMay& ph, int ma, const std::string& thongDiep) {
    Json j = Json::doiTuong();
    j.dat("ok", false);
    j.dat("loi", thongDiep);
    ph.ma = ma;
    ph.json(j.ketXuat());
}

bool GiaoDienWeb::duocPhep(const YeuCauMay& yc) const {
    std::string mk = ud_.cauHinh().chuoi("ung_dung.mat_khau_giao_dien", "");
    if (mk.empty()) return true;
    if (yc.dia_chi_ip == "127.0.0.1" || yc.dia_chi_ip == "::1") return true;
    if (yc.td("x-khoa") == mk) return true;
    if (yc.ts("khoa") == mk) return true;
    return false;
}

void GiaoDienWeb::chayDongBoNen(int gioiHan, const std::string& truyVan) {
    if (luongDongBo_.joinable()) luongDongBo_.join();
    luongDongBo_ = std::thread([this, gioiHan, truyVan]() {
        ThongKePhien tk;
        std::string loi;
        if (!ud_.dongBo(tk, loi, gioiHan, truyVan))
            NK.loi("dong_bo", "Phiên nhận mail thất bại: " + loi);
    });
}

std::string GiaoDienWeb::trangKetQuaOAuth(bool ok, const std::string& thongDiep) const {
    std::string mau = ok ? "#12a45c" : "#e0453a";
    std::string tieuDe = ok ? "Đăng nhập Gmail thành công" : "Đăng nhập Gmail thất bại";
    std::string icon = ok ? "&#10004;" : "&#10007;";
    return
        "<!DOCTYPE html><html lang=\"vi\"><head><meta charset=\"utf-8\">"
        "<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">"
        "<title>" + tieuDe + "</title><style>"
        "body{margin:0;height:100vh;display:grid;place-items:center;background:#f5f7fb;"
        "font-family:'Segoe UI',Arial,sans-serif;color:#1a2233}"
        ".h{background:#fff;border:1px solid #e4e9f2;border-radius:16px;padding:2.4rem 2.6rem;"
        "text-align:center;max-width:520px;box-shadow:0 12px 40px rgba(17,26,48,.09)}"
        ".i{width:62px;height:62px;border-radius:50%;display:grid;place-items:center;margin:0 auto 1rem;"
        "font-size:30px;color:#fff;background:" + mau + "}"
        "h1{font-size:1.2rem;margin:0 0 .6rem}p{color:#68738a;font-size:.92rem;line-height:1.6;margin:0}"
        "small{display:block;margin-top:1.3rem;color:#93a0b6}"
        "</style></head><body><div class=\"h\"><div class=\"i\">" + icon + "</div>"
        "<h1>" + tieuDe + "</h1><p>" + thongDiep + "</p>"
        "<small>Bạn có thể đóng thẻ này và quay lại cửa sổ điều khiển.</small>"
        "</div></body></html>";
}

// =====================================================================
//  Định tuyến
// =====================================================================
void GiaoDienWeb::dinhTuyen(const YeuCauMay& yc, PhanHoiMay& ph) {
    const std::string& d = yc.duong_dan;

    if (!duocPhep(yc)) { traLoi(ph, 401, "Không có quyền truy cập giao diện"); return; }

    // ---------------- Tài nguyên tĩnh ----------------
    std::string tep = (d == "/" || d.empty()) ? "/index.html" : d;
    for (unsigned int i = 0; i < SO_TAI_NGUYEN; i++) {
        if (tep == TAI_NGUYEN[i].duong_dan) {
            ph.ma = 200;
            ph.kieu = TAI_NGUYEN[i].kieu;
            ph.than.assign((const char*)TAI_NGUYEN[i].du_lieu, TAI_NGUYEN[i].do_dai);
            return;
        }
    }

    Json than;
    if (yc.phuong_thuc == "POST" && !yc.than.empty()) Json::phanTich(yc.than, than, nullptr);

    // ---------------- Trạng thái ----------------
    if (d == "/api/trang-thai") { traJson(ph, ud_.trangThai()); return; }

    // ---------------- Cấu hình ----------------
    if (d == "/api/cau-hinh" && yc.phuong_thuc == "GET") {
        Json j = Json::doiTuong();
        j.dat("ok", true);
        Json c = Json::doiTuong();
        for (const auto& k : ud_.cauHinh().danhSachKhoa()) {
            if (startsWith(k, "__")) continue;
            c.dat(k, ud_.cauHinh().chuoi(k, ""));
        }
        j.dat("cau_hinh", c);
        j.dat("uri_chuyen_huong", ud_.diaChiChuyenHuong());
        j.dat("phien_ban", PHIEN_BAN_UNG_DUNG);
        j.dat("phien_ban_day_du", PHIEN_BAN_DAY_DU);
        j.dat("so_build", (long long)SO_BUILD);
        j.dat("ngay_build", NGAY_BUILD);
        j.dat("tep_cau_hinh", ud_.cauHinh().chuoi("__tep", ""));
        traJson(ph, j);
        return;
    }

    if (d == "/api/cau-hinh" && yc.phuong_thuc == "POST") {
        const Json& c = than.lay("cau_hinh");
        if (!c.laDoiTuong()) { traLoi(ph, 400, "Thiếu dữ liệu cấu hình"); return; }
        int n = 0;
        for (const auto& k : c.danhSachKhoa()) {
            if (startsWith(k, "__")) continue;
            ud_.cauHinh().dat(k, c.lay(k).chuoi());
            n++;
        }
        ud_.ghiCauHinh();
        // Áp dụng ngay cho các thành phần liên quan
        ud_.gmail().datUngDung(ud_.cauHinh().chuoi("gmail.client_id"),
                               ud_.cauHinh().chuoi("gmail.client_secret"));
        ud_.troLyAi().datCauHinh(CauHinhAi::tuCauHinh(ud_.cauHinh()));
        NK.tin("cau_hinh", "Đã cập nhật " + std::to_string(n) + " thiết lập từ giao diện");
        Json j = Json::doiTuong();
        j.dat("ok", true);
        j.dat("so_khoa", (long long)n);
        traJson(ph, j);
        return;
    }

    // ---------------- Kết nối máy chủ ----------------
    if (d == "/api/ket-noi" && yc.phuong_thuc == "POST") {
        std::string loi;
        if (!ud_.ketNoiMayChu(loi)) { traLoi(ph, 400, loi); return; }
        Json j = Json::doiTuong();
        j.dat("ok", true);
        j.dat("thong_diep", "Kết nối " + ud_.tenKho() + " thành công. Danh mục: " + ud_.danhMuc().tomTat());
        traJson(ph, j);
        return;
    }

    // ---------------- Đồng bộ ----------------
    if (d == "/api/dong-bo" && yc.phuong_thuc == "POST") {
        if (ud_.dangDongBo()) { traLoi(ph, 409, "Đang có phiên nhận mail chạy"); return; }
        int gh = (int)than.lay("gioi_han").nguyen(0);
        std::string tv = than.lay("truy_van").chuoi("");
        chayDongBoNen(gh, tv);
        Json j = Json::doiTuong();
        j.dat("ok", true);
        j.dat("thong_diep", "Đã bắt đầu phiên nhận mail");
        traJson(ph, j);
        return;
    }

    if (d == "/api/dung" && yc.phuong_thuc == "POST") {
        ud_.yeuCauDung();
        Json j = Json::doiTuong();
        j.dat("ok", true);
        traJson(ph, j);
        return;
    }

    if (d == "/api/dich-vu" && yc.phuong_thuc == "POST") {
        bool bat = than.lay("bat").logic(false);
        if (bat) ud_.batDauDichVu(); else ud_.dungDichVu();
        Json j = Json::doiTuong();
        j.dat("ok", true);
        j.dat("dang_chay", ud_.dichVuDangChay());
        traJson(ph, j);
        return;
    }

    // ---------------- Nhật ký ----------------
    if (d == "/api/nhat-ky") {
        long long tu = toLL(yc.ts("tu", "0"), 0);
        Json j = Json::doiTuong();
        j.dat("ok", true);
        Json ds = Json::mang();
        for (const auto& x : NK.ganDay(tu, 400)) {
            Json e = Json::doiTuong();
            e.dat("so", x.soThuTu);
            e.dat("thoi_gian", x.thoi_gian);
            e.dat("muc", NhatKy::tenMuc(x.muc));
            e.dat("hanh_dong", x.hanh_dong);
            e.dat("noi_dung", x.noi_dung);
            ds.them(e);
        }
        j.dat("dong", ds);
        j.dat("moi_nhat", NK.soThuTuHienTai());
        traJson(ph, j);
        return;
    }

    // ---------------- Gmail ----------------
    if (d == "/api/gmail/url") {
        if (!ud_.gmail().coUngDung()) { traLoi(ph, 400, "Chưa khai báo Client ID / Client Secret"); return; }
        {
            std::lock_guard<std::mutex> g(khoa_);
            state_oauth_ = chuoiNgauNhien(16);
        }
        Json j = Json::doiTuong();
        j.dat("ok", true);
        j.dat("url", ud_.gmail().urlDongY(ud_.diaChiChuyenHuong(), state_oauth_));
        traJson(ph, j);
        return;
    }

    if (d == "/oauth/callback") {
        std::string loiG = yc.ts("error");
        if (!loiG.empty()) {
            ph.ma = 200;
            ph.html(trangKetQuaOAuth(false, "Google trả về lỗi: " + loiG));
            NK.loi("gmail", "Đăng nhập Google thất bại: " + loiG);
            return;
        }
        std::string ma = yc.ts("code");
        std::string st = yc.ts("state");
        if (ma.empty()) {
            ph.ma = 200;
            ph.html(trangKetQuaOAuth(false, "Không nhận được mã xác thực từ Google."));
            return;
        }
        {
            std::lock_guard<std::mutex> g(khoa_);
            if (!state_oauth_.empty() && st != state_oauth_) {
                ph.ma = 200;
                ph.html(trangKetQuaOAuth(false, "Mã trạng thái không khớp - hãy thử đăng nhập lại từ đầu."));
                NK.canhBao("gmail", "State OAuth không khớp, từ chối");
                return;
            }
        }
        std::string loi;
        if (!ud_.gmail().doiMaLayToken(ma, ud_.diaChiChuyenHuong(), loi)) {
            ph.ma = 200;
            ph.html(trangKetQuaOAuth(false, loi));
            NK.loi("gmail", "Đổi mã lấy token thất bại: " + loi);
            return;
        }
        ud_.luuToken(ud_.gmail().token());
        std::string hop = ud_.gmail().token().dia_chi;
        NK.tin("gmail", "Đăng nhập Gmail thành công: " + (hop.empty() ? "(không rõ hộp thư)" : hop));
        ph.ma = 200;
        ph.html(trangKetQuaOAuth(true, "Hộp thư <strong>" + (hop.empty() ? "đã kết nối" : hop) +
                                       "</strong> đã sẵn sàng. Refresh token đã được lưu để tự gia hạn."));
        return;
    }

    if (d == "/api/gmail/token" && yc.phuong_thuc == "POST") {
        std::string at = trim(than.lay("access_token").chuoi());
        std::string rt = trim(than.lay("refresh_token").chuoi());
        std::string ci = trim(than.lay("client_id").chuoi());
        std::string cs = trim(than.lay("client_secret").chuoi());
        if (at.empty() && rt.empty()) { traLoi(ph, 400, "Cần ít nhất access token hoặc refresh token"); return; }
        if (!ci.empty()) ud_.cauHinh().dat("gmail.client_id", ci);
        if (!cs.empty()) ud_.cauHinh().dat("gmail.client_secret", cs);
        ud_.gmail().datUngDung(ud_.cauHinh().chuoi("gmail.client_id"),
                               ud_.cauHinh().chuoi("gmail.client_secret"));

        TokenGmail t;
        t.access_token = at;
        t.refresh_token = rt;
        t.het_han = at.empty() ? 0 : (nowEpoch() + 3000);
        ud_.gmail().datToken(t);

        std::string loi;
        if (at.empty() && !rt.empty() && !ud_.gmail().lamMoiToken(loi)) {
            traLoi(ph, 400, "Không dùng được refresh token: " + loi);
            return;
        }
        long long tong = 0;
        std::string hop;
        if (!ud_.gmail().hoSo(hop, tong, loi)) { traLoi(ph, 400, "Token không dùng được: " + loi); return; }

        TokenGmail t2 = ud_.gmail().token();
        t2.dia_chi = hop;
        ud_.luuToken(t2);
        NK.tin("gmail", "Đã lưu token thủ công cho hộp thư " + hop);

        Json j = Json::doiTuong();
        j.dat("ok", true);
        j.dat("thong_diep", "Đã lưu token. Hộp thư: " + hop + " (" + std::to_string(tong) + " thư).");
        traJson(ph, j);
        return;
    }

    if (d == "/api/gmail/kiem-tra" && yc.phuong_thuc == "POST") {
        std::string loi, hop;
        long long tong = 0;
        if (!ud_.gmail().hoSo(hop, tong, loi)) { traLoi(ph, 400, loi); return; }
        TokenGmail t = ud_.gmail().token();
        if (t.dia_chi != hop) { t.dia_chi = hop; ud_.luuToken(t); }
        Json j = Json::doiTuong();
        j.dat("ok", true);
        j.dat("thong_diep", "Kết nối tốt. Hộp thư " + hop + " có " + std::to_string(tong) + " thư.");
        traJson(ph, j);
        return;
    }

    if (d == "/api/gmail/thoat" && yc.phuong_thuc == "POST") {
        std::string loi;
        ud_.gmail().thuHoi(loi);
        TokenGmail t;
        ud_.luuToken(t);
        ud_.cauHinh().dat("gmail.hop_thu", "");
        ud_.ghiCauHinh();
        NK.canhBao("gmail", "Đã đăng xuất tài khoản Gmail");
        Json j = Json::doiTuong();
        j.dat("ok", true);
        traJson(ph, j);
        return;
    }

    // ---------------- AI ----------------
    if (d == "/api/ai/kiem-tra" && yc.phuong_thuc == "POST") {
        ud_.troLyAi().datCauHinh(CauHinhAi::tuCauHinh(ud_.cauHinh()));
        std::string td, loi;
        if (!ud_.troLyAi().kiemTra(td, loi)) { traLoi(ph, 400, loi); return; }
        Json j = Json::doiTuong();
        j.dat("ok", true);
        j.dat("thong_diep", td);
        traJson(ph, j);
        return;
    }

    // ---------------- Danh mục ----------------
    if (d == "/api/danh-muc") {
        Json j = Json::doiTuong();
        j.dat("ok", true);
        Json t = Json::mang();
        for (const auto& x : ud_.danhMuc().truong) {
            Json e = Json::doiTuong();
            e.dat("ma", x.ma);
            e.dat("ten", x.ten);
            t.them(e);
        }
        j.dat("truong", t);
        Json n = Json::mang();
        for (const auto& x : ud_.danhMuc().nguoi) {
            Json e = Json::doiTuong();
            e.dat("ma", x.ma);
            e.dat("ho_ten", x.ho_ten);
            n.them(e);
        }
        j.dat("nguoi_xu_ly", n);
        Json v = Json::mang();
        for (const auto& x : ud_.danhMuc().van_ban) {
            Json e = Json::doiTuong();
            e.dat("ma", x.ma);
            e.dat("ten", x.ten);
            e.dat("tu_dong_tao", x.tu_dong_tao);
            v.them(e);
        }
        j.dat("van_ban", v);
        traJson(ph, j);
        return;
    }

    traLoi(ph, 404, "Không tìm thấy đường dẫn " + d);
}

} // namespace mr
