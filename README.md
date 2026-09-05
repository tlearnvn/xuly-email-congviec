# Hệ thống phân luồng Mail công vụ

**Phòng GDPT-GDTX — Sở Giáo dục và Đào tạo Đồng Nai**
*Thiết kế bởi Trương Anh Tuấn*

Hệ thống tự động đọc hộp thư Gmail công vụ, nhận diện mã trên **tên tệp đính kèm** và **tiêu đề
thư**, rồi chuyển văn bản về đúng chuyên viên phụ trách. Toàn bộ thư và tệp đính kèm được lưu
vào MySQL (tệp lưu dạng BLOB), **không xoá thư trên Gmail**.

```
   001_001_TAT.pdf
   ─┬─ ─┬─ ─┬─
    │   │   └── mã người xử lý     → chuyển cho đúng chuyên viên
    │   └────── mã văn bản/công việc → thống kê nộp báo cáo
    └────────── mã trường            → biết đơn vị nào gửi
```

---

## Hai phần của hệ thống

| Phần | Ngôn ngữ | Chạy ở đâu | Vai trò |
|------|----------|-----------|---------|
| **Bộ nhận mail** (`mailrouter`) | C++17 | Máy nhà / VPS (Windows hoặc Linux) | Đăng nhập Gmail, đọc thư, tách mã, chống trùng, đẩy dữ liệu lên MySQL |
| **Web quản trị** | PHP 7.4+ | Hosting cPanel | Người xử lý đăng nhập xem/tải văn bản, bảng điều khiển, thống kê, danh mục, phân luồng tay, nhật ký |

Bộ nhận mail có **giao diện đồ hoạ** chạy ngay trong trình duyệt (máy chủ web nhúng sẵn trong
tệp thực thi — không cần cài thêm gì), đồng thời có chế độ dòng lệnh cho Task Scheduler / cron /
systemd.

---

## Tính năng chính

**Nhận và phân luồng thư**
- Đăng nhập Gmail bằng **Client ID + Client Secret** (OAuth 2.0, một cú bấm) hoặc dán sẵn
  **access token + refresh token**; token tự gia hạn.
- Đọc tiêu đề, nội dung, tên tệp đính kèm; giải mã đúng tiếng Việt (RFC 2047, quoted-printable,
  base64, nhiều bảng mã).
- Tách mã `<mã trường>_<mã văn bản>_<mã người xử lý>` từ tên tệp trước, sau đó tới tiêu đề.
  Chấp nhận dấu phân cách `_`, `-`, `.`, khoảng trắng và tên tệp có dấu tiếng Việt.
- Một thư nhiều tệp của nhiều người xử lý khác nhau ⇒ tách thành nhiều công việc riêng.
- **Chỉ đọc** hộp thư (`gmail.readonly`) — không bao giờ xoá thư.

**Chống trùng lặp**
- Cùng mã thư Gmail ⇒ bỏ qua.
- Cùng nội dung (tiêu đề + người gửi + thân thư) **và** cùng bộ tệp ⇒ đánh dấu *trùng lặp*.
- Cùng nội dung nhưng **tệp khác dung lượng / khác nội dung** ⇒ ghi nhận **phiên bản mới**, bản
  cũ được đánh dấu đã thay thế; người xử lý luôn thấy bản mới nhất và xem lại được các bản trước.
- Tệp có nội dung giống hệt nhau chỉ lưu **một lần** trong CSDL (khử trùng lặp theo SHA-256).

**Trợ lý AI (tuỳ chọn)**
- Tương thích chuẩn OpenAI: tuỳ chỉnh **URL**, **model**, **API key**, **max tokens** (≤ 64000),
  **timeout** (≤ 300 giây), temperature, ngưỡng tin cậy.
- Chỉ được gọi khi tên tệp và tiêu đề không đọc được mã. Dưới ngưỡng tin cậy ⇒ đưa vào hàng chờ
  phân luồng tay.
- Dùng được với OpenAI, Azure OpenAI, Groq, Together, OpenRouter, Ollama, LM Studio…

**Web quản trị**
- Bảng điều khiển với biểu đồ (SVG thuần, không phụ thuộc mạng ngoài).
- Hộp việc cá nhân, xem trực tiếp PDF/ảnh trong trình duyệt hoặc tải về máy.
- **Thống kê trường đã nộp / chưa nộp** theo từng mã văn bản, xuất Excel (CSV).
- Danh mục **trường** và **người xử lý** (bắt buộc), danh mục **mã văn bản** *nới lỏng* — mã lạ
  vẫn được nhận và tự thêm vào danh mục để không mất số liệu thống kê.
- **Phân luồng tay** cho các thư không đọc được mã, có nút *Nhờ AI đọc giúp*.
- Nhật ký đầy đủ (web + bộ nhận mail + API), lọc theo mức/nguồn/thời gian.
- Tuỳ chỉnh **tên ứng dụng**, **tên đơn vị**, **bản quyền**, **màu chủ đạo**.
- Toàn hệ thống dùng **giờ Việt Nam (GMT+7)**.

---

## Cài đặt nhanh

### 1. Phần web trên hosting cPanel

```
1. Giải nén dist/web-cpanel-vX.Y.Z.zip vào public_html (hoặc thư mục con).
2. cPanel → MySQL Databases: tạo cơ sở dữ liệu + người dùng, gán toàn quyền.
3. Mở https://ten-mien.vn/cai-dat.php và làm theo 4 bước.
4. Xoá tệp cai-dat.php sau khi cài xong.
```
Chi tiết: [docs/HUONG-DAN-WEB-CPANEL.md](docs/HUONG-DAN-WEB-CPANEL.md)

### 2. Bộ nhận mail trên máy nhà / VPS

**Windows:** giải nén `mailrouter-windows-x64-vX.Y.Z.zip`, nhấp đúp `chay-bo-nhan-mail.bat`.

**Linux:**
```bash
tar -xzf mailrouter-linux-x64-vX.Y.Z.tar.gz
cd mailrouter-linux-x64
cp mailrouter.example.ini mailrouter.ini
./mailrouter                 # mở bảng điều khiển tại http://127.0.0.1:8899
```

Trong bảng điều khiển: khai báo **Kết nối máy chủ** → **Tài khoản Gmail** → bấm **Nhận mail ngay**.

Chi tiết: [docs/HUONG-DAN-BO-NHAN-MAIL.md](docs/HUONG-DAN-BO-NHAN-MAIL.md) ·
[docs/GMAIL-OAUTH.md](docs/GMAIL-OAUTH.md)

---

## Cấu trúc mã nguồn

```
├── VERSION, BUILD              Số phiên bản (tự tăng khi đóng gói)
├── sql/                        Cấu trúc CSDL + dữ liệu khởi tạo
│   ├── 01_schema.sql
│   └── 02_du_lieu_mau.sql
├── cpp/                        Bộ nhận mail (C++17, không phụ thuộc thư viện ngoài)
│   ├── include/  src/          Mã nguồn
│   ├── webui/                  Giao diện đồ hoạ (nhúng vào tệp thực thi khi build)
│   ├── cmake/                  Kịch bản nhúng tài nguyên, toolchain mingw-w64
│   └── CMakeLists.txt
├── php/                        Web quản trị (PHP thuần, không cần composer)
│   ├── index.php  cai-dat.php
│   ├── app/core/               Db, Auth, Ung, Util, View, NhatKy, Ai, LuuMail
│   ├── app/controllers/        Điều khiển từng trang
│   ├── app/views/              Giao diện
│   ├── api/ingest.php          Cổng tiếp nhận dữ liệu từ bộ nhận mail
│   └── assets/                 CSS + JS
├── scripts/                    Đóng gói, đánh số phiên bản, dịch vụ systemd, .bat
├── docs/                       Tài liệu tiếng Việt
└── dist/                       Gói phát hành (sinh ra khi chạy scripts/dong-goi.sh)
```

---

## Biên dịch từ mã nguồn

```bash
# Linux 64-bit
cmake -S cpp -B cpp/build -DCMAKE_BUILD_TYPE=Release
cmake --build cpp/build -j

# Windows 64-bit (biên dịch chéo, cần: sudo apt install mingw-w64)
cmake -S cpp -B cpp/build-win -DCMAKE_TOOLCHAIN_FILE=cpp/cmake/mingw64.cmake -DCMAKE_BUILD_TYPE=Release
cmake --build cpp/build-win -j

# Hoặc đóng gói trọn bộ (tự tăng số build)
./scripts/dong-goi.sh
```

Bộ nhận mail **không dùng thư viện ngoài**: JSON, base64, SHA-1/SHA-256, giao thức MySQL, máy chủ
HTTP và trình khách HTTPS đều được viết trong dự án. Trên Windows dùng WinHTTP có sẵn của hệ điều
hành; trên Linux nạp động `libcurl.so.4` (gói `libcurl4`, hầu như bản phân phối nào cũng có sẵn).

### Đánh số phiên bản

```bash
./scripts/phien-ban.sh doc          # xem phiên bản hiện tại
./scripts/phien-ban.sh bump         # 1.0.3 → 1.0.4
./scripts/phien-ban.sh bump minor   # 1.0.4 → 1.1.0
./scripts/phien-ban.sh bump major   # 1.1.0 → 2.0.0
```
Mỗi lần chạy `scripts/dong-goi.sh`, **số build tự tăng** và được ghi vào cả phần C++
(`cpp/include/phien_ban.h`) lẫn phần PHP (`php/app/phien_ban.php`); số phiên bản hiển thị ở chân
trang web và trên bảng điều khiển của bộ nhận mail.

---

## Yêu cầu hệ thống

| Thành phần | Yêu cầu |
|-----------|---------|
| Hosting web | PHP 7.4 – 8.4, các phần mở rộng `pdo_mysql`, `mbstring`, `json`, `curl` (cho AI) |
| Cơ sở dữ liệu | MySQL ≥ 5.7 hoặc MariaDB ≥ 10.3, bảng mã `utf8mb4` |
| Bộ nhận mail | Windows 7 64-bit trở lên, hoặc Linux 64-bit có `libcurl4` |
| Tài khoản Gmail | Bật Gmail API trong Google Cloud Console |

---

## Giấy phép và bản quyền

Phần mềm được xây dựng phục vụ công tác quản lý văn bản của Phòng GDPT-GDTX, Sở GD&ĐT Đồng Nai.

**Thiết kế bởi Trương Anh Tuấn.**
Tên ứng dụng và dòng bản quyền có thể tuỳ chỉnh trong *Cài đặt → Giao diện & thương hiệu*.
