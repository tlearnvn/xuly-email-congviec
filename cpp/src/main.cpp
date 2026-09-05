// =====================================================================
//  HỆ THỐNG PHÂN LUỒNG MAIL CÔNG VỤ - BỘ NHẬN MAIL (MailRouter)
//  Phòng GDPT-GDTX - Sở GD&ĐT Đồng Nai
//  Thiết kế bởi Trương Anh Tuấn
// =====================================================================
#include "ungdung.h"
#include "webui.h"
#include "nhatky.h"
#include "util.h"
#include "http_client.h"
#include "phien_ban.h"

#include <csignal>
#include <cstdio>
#include <cstring>
#include <iostream>
#include <atomic>

#ifdef _WIN32
  #ifndef WIN32_LEAN_AND_MEAN
    #define WIN32_LEAN_AND_MEAN
  #endif
  #include <windows.h>
#endif

namespace mr {
    const char* PHIEN_BAN_UNG_DUNG = MR_PHIEN_BAN;
    const char* PHIEN_BAN_DAY_DU  = MR_PHIEN_BAN_DAY_DU;
    const char* NGAY_BUILD        = MR_NGAY_BUILD;
    extern const int SO_BUILD;
    const int   SO_BUILD          = MR_SO_BUILD;
}

using namespace mr;

static std::atomic<bool> g_thoat{false};

static void batTinHieu(int) {
    g_thoat.store(true);
    std::fprintf(stderr, "\nĐang dừng chương trình...\n");
}

static void inTieuDe() {
    std::printf(
        "\n"
        "  ============================================================\n"
        "   HE THONG PHAN LUONG MAIL CONG VU - BO NHAN MAIL\n"
        "   Phong GDPT-GDTX - So GD&DT Dong Nai\n"
        "   Thiet ke boi Truong Anh Tuan\n"
        "   Phien ban %s  -  build %d  -  %s\n"
        "  ============================================================\n\n",
        PHIEN_BAN_UNG_DUNG, SO_BUILD, NGAY_BUILD);
}

static void inHuongDan() {
    std::printf(
        "Cach dung: mailrouter [che-do] [tuy-chon]\n\n"
        "Che do:\n"
        "  giao-dien       Mo bang dieu khien do hoa trong trinh duyet (mac dinh)\n"
        "  nhan            Chay mot phien nhan mail roi thoat (dung cho Task Scheduler / cron)\n"
        "  dich-vu         Chay nen, tu dong quet mail theo chu ky\n"
        "  kiem-tra        Kiem tra ket noi CSDL / Gmail / AI roi thoat\n"
        "  cau-hinh        In duong dan va noi dung tep cau hinh\n\n"
        "Tuy chon:\n"
        "  -c, --cau-hinh <tep>   Chi dinh tep cau hinh (mac dinh: mailrouter.ini canh chuong trinh)\n"
        "  -p, --cong <so>        Cong cho giao dien web (mac dinh 8899)\n"
        "      --dia-chi <ip>     Dia chi lang nghe (mac dinh 127.0.0.1; dung 0.0.0.0 de truy cap tu xa)\n"
        "      --khong-mo         Khong tu mo trinh duyet\n"
        "  -n, --gioi-han <so>    Gioi han so mail xu ly trong phien nay\n"
        "  -q, --truy-van <chuoi> Dieu kien tim kiem Gmail cho phien nay\n"
        "  -v, --chi-tiet         Ghi nhat ky muc go loi (debug)\n"
        "      --khong-mau        Tat mau sac tren man hinh console\n"
        "  -h, --giup             Hien thong tin nay\n\n"
        "Vi du:\n"
        "  mailrouter                                  # mo bang dieu khien\n"
        "  mailrouter nhan -n 100                      # nhan toi da 100 mail roi thoat\n"
        "  mailrouter nhan -q \"has:attachment newer_than:2d\"\n"
        "  mailrouter dich-vu                          # chay nen theo chu ky\n"
        "  mailrouter --dia-chi 0.0.0.0 --cong 8899    # mo giao dien cho may khac truy cap\n\n");
}

int main(int argc, char** argv) {
#ifdef _WIN32
    SetConsoleOutputCP(CP_UTF8);
#endif
    std::signal(SIGINT, batTinHieu);
#ifdef SIGTERM
    std::signal(SIGTERM, batTinHieu);
#endif

    std::string cheDo = "giao-dien";
    std::string tepCauHinh;
    std::string truyVan;
    int gioiHan = 0;
    int cong = 0;
    std::string diaChi;
    bool khongMo = false;
    bool chiTiet = false;

    for (int i = 1; i < argc; i++) {
        std::string a = argv[i];
        auto keTiep = [&](const char* ten) -> std::string {
            if (i + 1 < argc) return argv[++i];
            std::fprintf(stderr, "Thieu gia tri cho tham so %s\n", ten);
            std::exit(2);
        };
        if (a == "-h" || a == "--giup" || a == "--help") { inTieuDe(); inHuongDan(); return 0; }
        else if (a == "-c" || a == "--cau-hinh") tepCauHinh = keTiep("--cau-hinh");
        else if (a == "-p" || a == "--cong") cong = (int)toLL(keTiep("--cong"), 0);
        else if (a == "--dia-chi") diaChi = keTiep("--dia-chi");
        else if (a == "--khong-mo") khongMo = true;
        else if (a == "-n" || a == "--gioi-han") gioiHan = (int)toLL(keTiep("--gioi-han"), 0);
        else if (a == "-q" || a == "--truy-van") truyVan = keTiep("--truy-van");
        else if (a == "-v" || a == "--chi-tiet") chiTiet = true;
        else if (a == "--khong-mau") NK.tatMauSac(true);
        else if (!a.empty() && a[0] == '-') {
            std::fprintf(stderr, "Tham so khong hop le: %s (dung --giup de xem huong dan)\n", a.c_str());
            return 2;
        } else cheDo = toLower(a);
    }

    inTieuDe();
    if (chiTiet) NK.datMuc(Muc::DEBUG_);

    UngDung ud;
    std::string loi;
    if (!ud.khoiTao(tepCauHinh, loi)) {
        NK.loi("khoi_dong", loi);
        return 1;
    }
    if (chiTiet) NK.datMuc(Muc::DEBUG_);
    if (cong > 0) ud.cauHinh().dat("ung_dung.cong_giao_dien", std::to_string(cong));
    if (!diaChi.empty()) ud.cauHinh().dat("ung_dung.dia_chi_giao_dien", diaChi);

    // ------------------------------------------------------------------
    if (cheDo == "cau-hinh") {
        std::printf("Tep cau hinh: %s\n\n%s\n",
                    ud.cauHinh().chuoi("__tep").c_str(), ud.cauHinh().noiDungIni().c_str());
        return 0;
    }

    // ------------------------------------------------------------------
    if (cheDo == "kiem-tra") {
        int soLoi = 0;
        NK.tin("kiem_tra", "Đang kiểm tra kết nối tới máy chủ dữ liệu...");
        if (!ud.ketNoiMayChu(loi)) { NK.loi("kiem_tra", "Kho lưu trữ: " + loi); soLoi++; }
        else NK.tin("kiem_tra", "Kho lưu trữ: OK (" + ud.tenKho() + ") - " + ud.danhMuc().tomTat());

        NK.tin("kiem_tra", "Đang kiểm tra tài khoản Gmail...");
        std::string hop;
        long long tong = 0;
        if (!ud.gmail().hoSo(hop, tong, loi)) { NK.loi("kiem_tra", "Gmail: " + loi); soLoi++; }
        else NK.tin("kiem_tra", "Gmail: OK - " + hop + " (" + std::to_string(tong) + " thư)");

        if (ud.troLyAi().bat()) {
            NK.tin("kiem_tra", "Đang kiểm tra dịch vụ AI...");
            std::string td;
            if (!ud.troLyAi().kiemTra(td, loi)) { NK.loi("kiem_tra", "AI: " + loi); soLoi++; }
            else NK.tin("kiem_tra", "AI: " + td);
        } else NK.tin("kiem_tra", "AI: đang tắt");

        NK.tin("kiem_tra", soLoi == 0 ? "Tất cả đều tốt." : ("Có " + std::to_string(soLoi) + " hạng mục lỗi."));
        return soLoi == 0 ? 0 : 1;
    }

    // ------------------------------------------------------------------
    if (cheDo == "nhan" || cheDo == "sync" || cheDo == "dong-bo") {
        ThongKePhien tk;
        if (!ud.dongBo(tk, loi, gioiHan, truyVan)) {
            NK.loi("nhan", loi);
            return 1;
        }
        return tk.so_loi > 0 ? 1 : 0;
    }

    // ------------------------------------------------------------------
    if (cheDo == "dich-vu" || cheDo == "daemon" || cheDo == "service") {
        if (!ud.ketNoiMayChu(loi)) NK.canhBao("khoi_dong", "Chưa kết nối được máy chủ: " + loi);
        ud.batDauDichVu();
        NK.tin("khoi_dong", "Đang chạy ở chế độ dịch vụ. Nhấn Ctrl+C để dừng.");
        while (!g_thoat.load()) nguMiliGiay(200);
        ud.dungDichVu();
        return 0;
    }

    // ------------------------------------------------------------------
    if (cheDo != "giao-dien" && cheDo != "gui" && cheDo != "ui") {
        std::fprintf(stderr, "Che do khong hop le: %s\n", cheDo.c_str());
        inHuongDan();
        return 2;
    }

    // ---------------------- Chế độ giao diện ----------------------
    GiaoDienWeb gd(ud);
    if (!gd.batDau(loi)) {
        NK.loi("giao_dien", "Không mở được giao diện: " + loi);
        return 1;
    }
    std::string url = gd.diaChi();
    std::printf("  Bang dieu khien: %s\n", url.c_str());
    std::printf("  Tep cau hinh   : %s\n", ud.cauHinh().chuoi("__tep").c_str());
    std::printf("  Nhan Ctrl+C de dung chuong trinh.\n\n");
    NK.tin("giao_dien", "Giao diện điều khiển đang chạy tại " + url);

    // Thử kết nối máy chủ ngay khi khởi động (không chặn nếu lỗi)
    if (!ud.ketNoiMayChu(loi))
        NK.canhBao("khoi_dong", "Chưa kết nối được máy chủ dữ liệu: " + loi);

    bool tuMo = ud.cauHinh().logic("ung_dung.tu_mo_trinh_duyet", true);
    if (tuMo && !khongMo) {
        if (!moTrinhDuyet(url))
            NK.canhBao("giao_dien", "Không tự mở được trình duyệt, hãy mở thủ công: " + url);
    }

    if (ud.cauHinh().logic("ung_dung.tu_bat_dich_vu", false)) ud.batDauDichVu();

    while (!g_thoat.load()) nguMiliGiay(200);

    NK.tin("giao_dien", "Đang đóng giao diện...");
    ud.dungDichVu();
    gd.dung();
    return 0;
}
