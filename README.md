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

## Tài liệu

| Tài liệu | Nội dung |
|---|---|
| 📕 **[Bản PDF trọn bộ](docs/pdf/He-thong-phan-luong-Mail-cong-vu.pdf)** | Quy trình kỹ thuật + hướng dẫn sử dụng, 71 trang, đầy đủ sơ đồ và ảnh chụp — bản in ấn |
| [Quy trình kỹ thuật](docs/QUY-TRINH-KY-THUAT.md) | Hệ thống hoạt động thế nào, vì sao thiết kế như vậy |
| [Hướng dẫn sử dụng](docs/HUONG-DAN-SU-DUNG.md) | Dùng hàng ngày, đi lần lượt từng màn hình |
| [Cài đặt web trên cPanel](docs/HUONG-DAN-WEB-CPANEL.md) | Từng bước đưa web lên hosting |
| [Cài đặt bộ nhận mail](docs/HUONG-DAN-BO-NHAN-MAIL.md) | Chạy trên máy nhà / VPS |
| [Lấy Client ID Gmail](docs/GMAIL-OAUTH.md) | Thao tác trên Google Cloud Console |
| [Kiến trúc hệ thống](docs/KIEN-TRUC.md) | Cấu trúc lớp và mã nguồn |

<p align="center">
  <a href="docs/QUY-TRINH-KY-THUAT.md">
    <img src="docs/hinh/so-do-kien-truc.svg" alt="Kiến trúc tổng thể" width="880">
  </a>
</p>

---

## Tải về

Các bản đã đóng gói sẵn nằm ở mục
**[Releases](https://github.com/tlearnvn/xuly-email-congviec/releases/latest)** — tải về là dùng
được ngay, không phải tự biên dịch:

| Tệp | Dùng cho | Cách dùng |
|---|---|---|
| `mailrouter-windows-x64-vX.Y.Z.zip` | Bộ nhận mail trên Windows 64-bit | Giải nén, nhấp đúp `mailrouter.exe` |
| `mailrouter-linux-x64-vX.Y.Z.tar.gz` | Bộ nhận mail trên Linux 64-bit | Giải nén, chạy `./mailrouter giao-dien` |
| `web-cpanel-vX.Y.Z.zip` | Phần web cho hosting cPanel | Giải nén vào `public_html`, mở `cai-dat.php` |
| `SHA256SUMS.txt` | Mã băm để kiểm chứng tệp tải về | `sha256sum -c SHA256SUMS.txt` |

Bộ nhận mail là **một tệp nhị phân duy nhất**, không cần cài thêm thư viện nào.

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
- Một thư nhiều tệp: cùng mã ⇒ gộp **một** công việc; khác mã ⇒ tách thành **nhiều** công việc
  cho nhiều người xử lý. Tệp không có mã được gộp vào khi cả thư chỉ nói về một hồ sơ (báo cáo
  kèm công văn), còn từ hai mã trở lên thì đưa vào hàng chờ chứ không đoán bừa.
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
│   ├── 02_du_lieu_mau.sql
│   └── 03_nang_cap.sql         Nâng cấp CSDL cài từ bản cũ (chạy lại được)
├── cpp/                        Bộ nhận mail (C++17, không phụ thuộc thư viện ngoài)
│   ├── include/  src/          Mã nguồn
│   ├── webui/                  Giao diện đồ hoạ (nhúng vào tệp thực thi khi build)
│   ├── cmake/                  Kịch bản nhúng tài nguyên, toolchain mingw-w64
│   └── CMakeLists.txt
├── php/                        Web quản trị (PHP thuần, không cần composer)
│   ├── index.php  cai-dat.php  nang-cap.php
│   ├── app/core/               Db, Auth, Ung, Util, View, NhatKy, Ai, LuuMail
│   ├── app/controllers/        Điều khiển từng trang
│   ├── app/views/              Giao diện
│   ├── api/ingest.php          Cổng tiếp nhận dữ liệu từ bộ nhận mail
│   └── assets/                 CSS + JS
├── scripts/                    Đóng gói, đánh số phiên bản, dịch vụ systemd, .bat
├── docs/                       Tài liệu tiếng Việt
│   ├── hinh/                   Sơ đồ SVG và ảnh chụp màn hình
│   └── pdf/                    Bản PDF trọn bộ (tông vàng mệnh Kim)
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

# Vừa tăng phiên bản vừa ghi thẳng vào mục "Lịch sử phiên bản" của README
./scripts/phien-ban.sh bump patch "Sửa lỗi tải tệp lớn" "Thêm bộ lọc theo địa bàn"
```
Mỗi lần chạy `scripts/dong-goi.sh`, **số build tự tăng** và được ghi vào cả phần C++
(`cpp/include/phien_ban.h`) lẫn phần PHP (`php/app/phien_ban.php`); số phiên bản hiển thị ở chân
trang web và trên bảng điều khiển của bộ nhận mail.

### Dựng lại bản PDF tài liệu

```bash
node scripts/tao-pdf.js
```

Đọc hai tệp `docs/QUY-TRINH-KY-THUAT.md` và `docs/HUONG-DAN-SU-DUNG.md`, dựng
`docs/pdf/tai-lieu.html` rồi in ra `docs/pdf/He-thong-phan-luong-Mail-cong-vu.pdf`.
Bước in cần một bản Chrome/Chromium (chỉ định bằng biến `CHROME=` nếu máy cài ở nơi khác) và gói
`playwright-core`; không có thì mở tệp HTML rồi Ctrl+P cũng ra đúng bản đó.

### Phát hành lên GitHub Releases

```bash
./scripts/phien-ban.sh bump patch "Nội dung thay đổi"   # cập nhật VERSION + README
git commit -am "Phát hành 1.0.3" && git push
git tag v1.0.3 && git push origin v1.0.3
```

Thẻ dạng `vX.Y.Z` sẽ kích hoạt workflow [`.github/workflows/phat-hanh.yml`](.github/workflows/phat-hanh.yml):
GitHub tự biên dịch cả hai nền tảng, đóng gói phần web, tính SHA-256, rồi tạo bản phát hành kèm
đủ tệp tải về. Workflow từ chối chạy nếu thẻ không khớp nội dung tệp `VERSION`.

---

## Yêu cầu hệ thống

| Thành phần | Yêu cầu |
|-----------|---------|
| Hosting web | PHP 7.4 – 8.4, các phần mở rộng `pdo_mysql`, `mbstring`, `json`, `curl` (cho AI) |
| Cơ sở dữ liệu | MySQL ≥ 5.7 hoặc MariaDB ≥ 10.3, bảng mã `utf8mb4` |
| Bộ nhận mail | Windows 7 64-bit trở lên, hoặc Linux 64-bit có `libcurl4` |
| Tài khoản Gmail | Bật Gmail API trong Google Cloud Console |

---

## Lịch sử phiên bản

Số phiên bản theo quy ước `CHÍNH.PHỤ.VÁ`: **CHÍNH** đổi khi thay đổi lớn không tương thích ngược,
**PHỤ** khi thêm tính năng, **VÁ** khi sửa lỗi. Số *build* tăng mỗi lần đóng gói.

<!-- BAT-DAU-CHANGELOG -->
### 1.7.0 — 06/09/2026

- **Dọn dữ liệu thử nghiệm:** trang mới trong *Cài đặt* xoá sạch dữ liệu công việc để chạy thử lại từ đầu — thư đã nhận, công việc, tệp đính kèm, kho nội dung tệp, phiên đồng bộ, tệp tải lên dở. **Giữ nguyên** danh mục trường, người xử lý kèm tài khoản đăng nhập, mã văn bản chính thức và mọi thiết lập, nên dọn xong chạy thử lại được ngay mà không phải khai báo lại.
- **Xoá cả kho BLOB — chỗ dễ sót nhất.** Khoá ngoại `tep_dinh_kem → tep_du_lieu` chỉ là SET NULL, nên `DELETE FROM email` để lại toàn bộ nội dung tệp không ai tham chiếu tới, đúng phần chiếm gần hết dung lượng. Bảng này được xoá tường minh theo thứ tự khai sẵn.
- **Ba tuỳ chọn:** xoá mã văn bản hệ thống tự thêm (mặc định bật), xoá luôn nhật ký (mặc định tắt để còn xem lại lần chạy trước), đặt lại số đếm ID về 1 (mặc định bật).
- **Ba lớp chặn vì đây là chức năng phá dữ liệu:** chỉ quản trị mở được (403 cả GET lẫn POST), có token chống giả mạo biểu mẫu, và phải tự tay gõ đúng `XOA SACH` — bấm nhầm nút thì không xoá được gì. Tên cơ sở dữ liệu hiện ngay dòng cảnh báo để không dọn nhầm CSDL thật.
- **`ALTER TABLE` chạy ngoài giao dịch:** phần xoá nằm trong một giao dịch, nhưng `AUTO_INCREMENT = 1` là DDL nên MySQL tự chốt giao dịch ngầm — gọi trong giao dịch là mất tính nguyên tử. Hosting không cho quyền ALTER thì bỏ qua chứ không làm hỏng kết quả xoá.
- **Tài liệu:** hướng dẫn sử dụng thêm mục 11.7 kèm bảng đối chiếu xoá/giữ và 2 ảnh; quy trình kỹ thuật thêm mục 6.0b về sơ đồ khoá ngoại và ba lớp chặn.

### 1.6.0 — 06/09/2026

- **Xem trực tiếp tệp Word / Excel / PowerPoint ngay trên web, không cần tải về.** Nút *Xem* nay mở được cả sáu định dạng Office. Bản 2007+ (`.docx/.xlsx/.pptx`) dựng lại được tiêu đề, in đậm/nghiêng/gạch chân/màu, danh sách có số và không số, bảng kể cả gộp ô, ảnh, siêu liên kết; Excel hiện nhiều sheet có tab chuyển, số hàng, ngày tháng đúng định dạng; PowerPoint hiện từng trang chiếu kèm ghi chú người trình bày.
- **Hồ sơ KHÔNG đi ra ngoài.** Không dùng Office Online hay Google Docs Viewer — hai dịch vụ đó bắt buộc tệp phải công khai trên Internet để máy chủ Microsoft/Google tải về được. Trình duyệt tải tệp trực tiếp từ máy chủ của Sở (cùng phiên đăng nhập, cùng luật quyền) rồi tự dựng lại tại chỗ. Máy chủ web cũng không cần cài thêm gì — chạy được trên hosting cPanel dùng chung.
- **Đọc được cả Office đời cũ 97-2003** (`.doc/.xls/.ppt`). Ba loại này là tệp OLE2 chứa dữ liệu nhị phân chứ không phải ZIP, nên có bộ đọc riêng: `.doc` qua FIB và piece table, `.xls` qua bản ghi BIFF8 (kể cả `SST`+`CONTINUE`, `RK`, `MULRK`), `.ppt` qua các container Slide. Lấy được chữ và bảng số liệu; định dạng đẹp, ảnh và biểu đồ thì không.
- **Nói rõ giới hạn ngay trên trang xem:** bản dựng lại có thể khác về bố cục và phông chữ, cần bản chuẩn xác để in hay ký thì tải về; tệp gõ bằng phông VNI/TCVN3 (font ABC) sẽ hiện sai dấu vì chữ trong tệp không phải Unicode, kèm cách xử lý tận gốc là lưu lại thành `.docx`.
- **Sửa bẫy trong `View::noiDung()`:** hàm dùng biến cục bộ tên `$f` cộng `extract(..., EXTR_SKIP)`, nên bất kỳ giao diện nào truyền vào biến tên `f` đều nhận được đường dẫn tệp view thay vì dữ liệu của mình — bị bỏ lặng lẽ, không một cảnh báo. Các biến nội bộ nay mang tiền tố `__vi`.
- **Không mời bấm Xem rồi báo lỗi:** nút *Xem* chỉ hiện cho đúng sáu đuôi tệp Office cùng PDF/ảnh/văn bản thuần.
- **Tài liệu:** quy trình kỹ thuật thêm mục 6.0 (so sánh ba đường đi, bảng phần nào đọc từ đâu, ba chỗ dễ sai đã trả giá để biết); hướng dẫn sử dụng thêm mục 4.1 và 4.2 kèm 5 ảnh minh hoạ.

### 1.5.0 — 06/09/2026

- **Xử lý được tệp Gmail tự chuyển sang Drive khi vượt 25 MB.** Trường đính kèm đàng hoàng, đặt tên đúng quy ước, nhưng tệp nặng quá nên Gmail tự đưa lên Drive rồi thay tệp bằng một ô link. Thư đó `has:attachment` là **sai**, nên bản 1.4.x vẫn bỏ sót; nay cụm nới truy vấn thêm bốn toán tử riêng của Gmail: `has:drive`, `has:document`, `has:spreadsheet`, `has:presentation`.
- **Đọc mã hồ sơ từ tên tệp trên Drive.** Gmail giữ nguyên tên tệp gốc trong ô link, nên `001_003_TAT.pdf` vẫn tách ra được mã y như tên tệp đính kèm thường — kể cả khi tiêu đề thư không có mã. Trước đây thư loại này ra `?_?_?` và nằm chờ phân luồng tay.
- **Hai tệp Drive hai mã thì tách hai công việc** cho đúng hai người xử lý, thay vì gom một cục mồ côi. Thứ tự ưu tiên giữ nguyên: tên tệp trước, tiêu đề sau.
- **Trang chi tiết và phân luồng tay hiện TÊN TỆP** thay vì đường dẫn dài. Tên tệp lưu chung vào cột `email.lien_ket_ngoai` sẵn có (mỗi dòng `đường-dẫn<TAB>tên-tệp`) nên nơi đã cài bản 1.4.x **không phải nâng cấp cơ sở dữ liệu thêm lần nữa**; dòng cũ chỉ có đường dẫn vẫn đọc bình thường.
- **Không nhầm link dán tay thành tên tệp:** kiểu `<a href="URL">URL</a>` thì chữ hiển thị chính là đường dẫn, hệ thống bỏ qua.
- **Tài liệu:** quy trình kỹ thuật thêm mục 3.3 *Tệp vượt 25 MB — Gmail tự đưa lên Drive* (bảng 10 tổ hợp chạy thật); hướng dẫn sử dụng thêm mục 16.4 và mục cho các trường *Tệp nặng quá 25 MB thì làm sao?* kèm cách làm nhẹ tệp.

### 1.4.1 — 06/09/2026

- **Dò link đúng thứ tự xuất hiện:** bộ dò link trước đây quét hết `https://` rồi mới quét `http://`, nên thư có link `http://` đứng trước link `https://` sẽ bị đảo thứ tự. Nay quét đúng một lượt từ trái sang phải, xét cả hai giao thức tại từng vị trí.
- **Bắt được link Drive lồng trong đường dẫn chuyển hướng:** `https://vanban.…/go?u=https://drive.google.com/…` nay được nhận ra, mà link Drive lồng trong chính link Drive khác vẫn chỉ tính một lần.

### 1.4.0 — 06/09/2026

- **Sửa lỗi bỏ sót thư nghiêm trọng:** trường không đính kèm tệp mà dán link Google Drive thì điều kiện lọc mặc định `has:attachment` **loại thẳng** bức thư — hệ thống không hề nhìn thấy, thống kê báo "chưa nộp" oan. Nay khi bật `gmail.nhan_link_drive` (mặc định bật), cụm `has:attachment` được nới thành `(has:attachment OR "drive.google.com" OR "docs.google.com" OR "1drv.ms" OR …)`. Nới ngay ở truy vấn nên cả hộp thư chính lẫn hộp Thư rác đều được lợi mà **không đội thêm** hạn mức số mail mỗi lần quét.
- **Dò link chia sẻ trong thân thư:** quét cả bản text lẫn bản HTML, giải thực thể HTML trong `href`, cắt dấu câu và dấu ngoặc bao ngoài, bỏ trùng, tối đa 50 link mỗi thư. So khớp theo **host** nên tên miền giả dạng `drive.google.com.kexau.tld` bị loại, còn tên miền con thật `abc-my.sharepoint.com` vẫn nhận.
- **Nói rõ giới hạn, không để nhầm:** kho **chỉ giữ đường dẫn, KHÔNG giữ bản tệp** (hệ thống chỉ xin quyền `gmail.readonly`, không đụng tới Google Drive). Trang chi tiết và trang phân luồng tay hiện thẻ *Link chia sẻ trong thư* viền đứt kèm cảnh báo vàng; danh sách văn bản hiện huy hiệu *N link* dưới số tệp; ghi chú công việc, nhật ký và thống kê phiên đều ghi rõ.
- **Bỏ thư vô can:** truy vấn nới rộng kéo về cả thư chỉ tình cờ có chữ `drive.google.com` trong chữ ký — thư không có tệp lẫn link chia sẻ bị bỏ ngay, không lưu vào kho.
- **Cột mới** `email.lien_ket_ngoai` cùng khoá cấu hình `gmail.nhan_link_drive`; `php/nang-cap.php` và `sql/03_nang_cap.sql` bổ sung sẵn, chạy lại nhiều lần vẫn an toàn.
- **Tài liệu:** quy trình kỹ thuật thêm mục 3.2 *Thư không đính kèm tệp, chỉ dán link Google Drive*; hướng dẫn sử dụng thêm mục 16.3, mục hỏi đáp cho các trường *Gửi link Google Drive thay cho tệp đính kèm được không?* và 2 dòng xử lý sự cố mới.

### 1.3.0 — 06/09/2026

- **Cải thiện gom nhóm tệp đính kèm:** thư kèm một tệp đặt tên đúng quy ước cộng thêm công văn/phụ lục đặt tên tự do trước đây sinh ra **hai** công việc — một cái đúng, một cái mồ côi phải phân luồng tay mỗi lần. Nay nếu cả thư chỉ có **đúng một** mã hồ sơ thì các tệp không mã được gộp chung vào đó, kèm ghi chú giải thích.
- **Vẫn không đoán bừa:** từ hai mã hồ sơ trở lên trong cùng thư, hoặc tiêu đề chỉ sang mã khác, thì tệp không mã vẫn vào hàng chờ phân luồng tay — đoán sai là giao nhầm người.
- **Tài liệu:** thêm mục 2.1 *Một thư nhiều tệp đính kèm* (bảng 8 tổ hợp chạy thật) trong quy trình kỹ thuật, và mục hướng dẫn đặt tên khi gửi nhiều tệp cho các trường.

### 1.2.2 — 05/09/2026

- **Tài liệu:** bổ sung đầy đủ các tính năng của bản 1.2.x — quy trình kỹ thuật thêm mục 15 *Nâng cấp hệ thống đang chạy* (ba lớp bảo vệ, vì sao chạy lại nhiều lần vẫn an toàn, trình tự 5 bước), hướng dẫn sử dụng thêm mục 12 *Nâng cấp lên phiên bản mới* và mục 16.1–16.2 về hộp Thư rác kèm cách xử lý tận gốc bằng bộ lọc Gmail.
- **Tài liệu:** sơ đồ CSDL và bảng mô tả bảng `email` nay có cột `tu_spam`; thêm 3 ảnh minh hoạ (trang nâng cấp, ô quét Thư rác, huy hiệu *Hộp Thư rác*).
- **Sửa lỗi bản PDF:** chữ tiếng Việt trong khối mã bị loang lổ dấu do `DejaVu Sans Mono` thiếu ký tự dựng sẵn (ể, ổ, ữ…). Đưa `Liberation Mono` lên trước trong danh sách font.

### 1.2.1 — 05/09/2026

- **Chia hạn mức quét:** hộp Thư rác dùng phần còn thừa của *Số mail mỗi lần quét*, nhưng không bao giờ dưới một phần năm hạn mức. Bản 1.2.0 cấp cho lượt quét Thư rác một hạn mức đầy đủ nữa nên một buổi nhiều thư rác có tệp đính kèm sẽ nuốt gấp đôi số mail đã đặt.
- **Tài liệu:** nói rõ hạn chế — nếu điều kiện lọc dùng nhãn tự đặt (`label:...`) thì lượt quét Thư rác gần như không ra kết quả, vì Gmail không gắn nhãn người dùng cho thư đã bị xếp vào Thư rác. Nên lọc theo `has:attachment` hoặc theo người gửi.

### 1.2.0 — 05/09/2026

- **Sửa lỗi bỏ sót thư:** Gmail API mặc định giấu hẳn thư trong hộp Thư rác, nên báo cáo bị Google xếp nhầm vào đó sẽ **không bao giờ được nhận** và thống kê báo "chưa nộp" oan cho trường. Nay mỗi phiên quét hai lượt — hộp thư chính rồi hộp Thư rác (`includeSpamTrash=true` kèm `in:spam`).
- **Thùng rác luôn bỏ qua:** thư người dùng đã chủ động xoá thì không lôi lại, kiểm tra bằng nhãn `TRASH` trên từng thư.
- **Đánh dấu nguồn:** thêm cột `email.tu_spam`; web hiện huy hiệu vàng "Hộp Thư rác" ở trang chi tiết và phân luồng tay; nhật ký ghi cảnh báo kèm địa chỉ người gửi; phiên đồng bộ đếm riêng số thư vớt được.
- **Thiết lập mới** `gmail.quet_spam` (mặc định bật) cùng ô đánh dấu trong mục *Phân luồng & đồng bộ* của bộ nhận mail.
- **Nâng cấp CSDL:** thêm `php/nang-cap.php` và `sql/03_nang_cap.sql` — chạy được nhiều lần, chỉ thêm cột và thiết lập mới, không đụng dữ liệu cũ. Bộ nhận mail tự phát hiện CSDL thiếu cột và chỉ rõ cách nâng cấp thay vì báo lỗi SQL khó hiểu.

### 1.1.0 — 05/09/2026

- **Tài liệu:** thêm bộ tài liệu đầy đủ — [Quy trình kỹ thuật](docs/QUY-TRINH-KY-THUAT.md) và [Hướng dẫn sử dụng](docs/HUONG-DAN-SU-DUNG.md), kèm 8 sơ đồ SVG và 23 ảnh chụp màn hình.
- **Tài liệu:** xuất bản [bản PDF trọn bộ 54 trang](docs/pdf/He-thong-phan-luong-Mail-cong-vu.pdf) tông vàng mệnh Kim, dựng bằng `scripts/tao-pdf.js`.
- **Phát hành:** thêm workflow `.github/workflows/phat-hanh.yml` — đẩy thẻ `vX.Y.Z` là GitHub tự biên dịch Windows + Linux, đóng gói web và tạo bản phát hành kèm tệp tải về.
- **Sửa lỗi giao diện:** các ô nhập trong biểu mẫu bị lệch nhau (rõ nhất ở trang *Chờ phân luồng tay*). Nguyên nhân: `.truong` dùng `display:flex` khiến dấu `*` bắt buộc rớt xuống dòng riêng; lớp `.truong.rong` trùng tên với `.rong` của khung báo trống; các ô `<input list=…>` không ghi `type` nên rơi về khung mặc định của trình duyệt.
- **Sửa lỗi giao diện:** mọi ô nhập một dòng nay cùng cao 38px nên thanh lọc ở các trang Nhật ký, Thống kê, Tất cả văn bản đều thẳng hàng.

### 1.0.2 — 05/09/2026

- **Giao diện:** mục *Tài khoản Gmail* hiển thị rõ token có tự gia hạn được hay không, để biết ngay có phải đăng nhập lại khi hết hạn hay không.
- **Cải thiện:** access token sau khi được tự gia hạn sẽ ghi lại vào `mailrouter.ini`, lần khởi động sau không phải gọi gia hạn thừa và hiển thị đúng hạn hiệu lực.

### 1.0.1 — 05/09/2026

- **Sửa:** bộ nhận mail kiểm tra cơ sở dữ liệu ngay sau khi kết nối. Nếu CSDL còn trống hoặc thiếu
  bảng, chương trình hiện hướng dẫn tiếng Việt cụ thể (chạy `cai-dat.php` hoặc nạp
  `sql/01_schema.sql`) thay vì lỗi SQL thô `Table ... doesn't exist`; trường hợp thiếu một vài bảng
  thì liệt kê đúng tên bảng còn thiếu.
- **Tài liệu:** bổ sung tình huống trên vào bảng xử lý sự cố của cả hai hướng dẫn cài đặt.
- **Công cụ:** `scripts/phien-ban.sh` ghi được entry changelog thẳng vào README khi tăng phiên bản.

### 1.0.0 — 05/09/2026

Phát hành lần đầu.

- **Bộ nhận mail (C++17):** đăng nhập Gmail bằng OAuth 2.0 hoặc token có sẵn (tự gia hạn), đọc thư
  với quyền chỉ đọc, giải mã MIME/RFC 2047/quoted-printable đúng tiếng Việt.
- **Phân luồng:** tách mã `<mã trường>_<mã văn bản>_<mã người xử lý>` từ tên tệp rồi tới tiêu đề,
  chấp nhận nhiều kiểu dấu phân cách; một thư nhiều tệp của nhiều người tách thành nhiều công việc.
- **Chống trùng ba mức:** cùng mã thư Gmail, trùng nội dung + trùng tệp, trùng nội dung nhưng tệp
  khác dung lượng (ghi nhận thành phiên bản mới). Khử trùng lặp tệp theo SHA-256.
- **Trợ lý AI:** tương thích chuẩn OpenAI, tuỳ chỉnh URL / model / API key / max tokens (≤ 64000) /
  timeout (≤ 300 giây) / ngưỡng tin cậy.
- **Hai chế độ lưu trữ:** MySQL trực tiếp hoặc qua API PHP khi hosting chặn kết nối MySQL từ xa.
- **Giao diện đồ hoạ** tiếng Việt nhúng sẵn trong tệp thực thi, kèm chế độ dòng lệnh cho Task
  Scheduler / cron / systemd.
- **Web quản trị (PHP thuần):** trình cài đặt 4 bước, bảng điều khiển biểu đồ SVG, hộp việc cá nhân,
  xem trực tiếp PDF/ảnh hoặc tải tệp, thống kê trường đã nộp / chưa nộp + xuất CSV, danh mục trường
  và người xử lý, danh mục mã văn bản nới lỏng, phân luồng tay có gợi ý AI, nhật ký đầy đủ, tuỳ
  chỉnh thương hiệu.
- **Khác:** toàn hệ thống dùng giờ Việt Nam (GMT+7); số phiên bản tự tăng khi đóng gói; sẵn bản nhị
  phân Linux 64-bit và Windows 64-bit.
<!-- KET-THUC-CHANGELOG -->

---

## Giấy phép và bản quyền

Phần mềm được xây dựng phục vụ công tác quản lý văn bản của Phòng GDPT-GDTX, Sở GD&ĐT Đồng Nai.

**Thiết kế bởi Trương Anh Tuấn.**
Tên ứng dụng và dòng bản quyền có thể tuỳ chỉnh trong *Cài đặt → Giao diện & thương hiệu*.
