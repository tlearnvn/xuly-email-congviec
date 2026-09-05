# Kiến trúc hệ thống

*Hệ thống phân luồng Mail công vụ — Thiết kế bởi Trương Anh Tuấn*

---

## 1. Tổng thể

```
        ┌──────────────┐
        │  Gmail API   │  (chỉ đọc: gmail.readonly)
        └──────┬───────┘
               │ HTTPS
        ┌──────▼──────────────────────────────┐
        │  BỘ NHẬN MAIL  (C++17, máy nhà/VPS) │
        │  ─────────────────────────────────  │
        │  • OAuth 2.0, tự gia hạn token      │
        │  • Giải mã MIME / RFC 2047          │
        │  • Tách mã 001_001_TAT              │
        │  • Băm SHA-256 chống trùng          │
        │  • Trợ lý AI khi cần                │
        │  • Giao diện web nhúng (cổng 8899)  │
        └──────┬───────────────────┬──────────┘
               │ (A) MySQL         │ (B) HTTPS + khoá API
               │     trực tiếp     │
        ┌──────▼───────────────────▼──────────┐
        │      HOSTING cPANEL                 │
        │  ─────────────────────────────────  │
        │  MySQL:  email, tep_du_lieu (BLOB), │
        │          cong_viec, danh mục, log   │
        │  PHP:    web quản trị + api/ingest  │
        └──────┬──────────────────────────────┘
               │ HTTPS
        ┌──────▼──────────────┐
        │  Người xử lý        │  xem / tải văn bản, cập nhật trạng thái
        │  Quản trị           │  phân luồng tay, danh mục, thống kê, nhật ký
        └─────────────────────┘
```

Hai đường ghi dữ liệu (A) và (B) cho **kết quả giống hệt nhau**: cùng quy tắc chống trùng, cùng
cách tạo công việc. Chọn (A) khi mở được Remote MySQL, chọn (B) khi IP máy nhận mail hay thay đổi.

---

## 2. Bộ nhận mail (C++)

### 2.1. Nguyên tắc: không phụ thuộc thư viện ngoài

Toàn bộ phần lõi được viết trong dự án để tệp thực thi chạy độc lập:

| Thành phần | Tệp | Ghi chú |
|-----------|-----|---------|
| JSON | `json.cpp` | Phân tích + kết xuất, hỗ trợ `\uXXXX` và cặp thay thế |
| Base64 | `crypto.cpp` | Chuẩn + biến thể URL-safe của Gmail |
| SHA-1, SHA-256 | `crypto.cpp` | SHA-1 cho xác thực MySQL, SHA-256 cho mã băm nội dung |
| Giao thức MySQL | `mysql_client.cpp` | Bắt tay, `mysql_native_password`, `caching_sha2_password` (đường nhanh), COM_QUERY |
| Máy chủ HTTP | `http_server.cpp` | Phục vụ giao diện + nhận chuyển hướng OAuth |
| Trình khách HTTPS | `http_client.cpp` | WinHTTP trên Windows, nạp động `libcurl.so.4` trên Linux |
| Tiếng Việt | `util.cpp` | Bỏ dấu UTF-8, chuẩn hoá mã, giờ GMT+7 không phụ thuộc TZ hệ thống |

### 2.2. Các lớp chính

```
UngDung          Điều phối: khởi tạo, kết nối, vòng đời phiên đồng bộ, dịch vụ nền
├── CauHinh      Tệp INI + cấu hình lấy từ bảng cau_hinh trên máy chủ
├── Gmail        OAuth 2.0 + Gmail API + phân tích MIME
├── BoPhanLuong  Tách mã, đối chiếu danh mục, gom nhóm công việc
├── TroLyAi      Gọi dịch vụ tương thích OpenAI
├── KhoLuuTru    Giao diện trừu tượng
│   ├── KhoMySql Ghi thẳng vào MySQL
│   └── KhoApi   Đẩy qua api/ingest.php
├── NhatKy       Ghi ra màn hình + tệp + bộ đệm giao diện + CSDL
└── GiaoDienWeb  Định tuyến giao diện đồ hoạ, tài nguyên nhúng trong binary
```

### 2.3. Luồng xử lý một phiên

```
1. Kiểm tra / nối lại kho lưu trữ
2. Bảo đảm access token còn hạn (tự gia hạn bằng refresh token)
3. Mở bản ghi phien_dong_bo
4. Gọi Gmail messages.list theo điều kiện lọc
5. Với mỗi thư:
   a. Đã có gmail_message_id trong CSDL?  → bỏ qua
   b. messages.get format=full → phân tích MIME
   c. Tải nội dung từng tệp đính kèm (bỏ qua tệp vượt giới hạn)
   d. Tính hash_noi_dung, hash_tep, hash_tong_hop
   e. Tách mã từ tên tệp / tiêu đề → gom nhóm công việc
   f. Nhóm nào thiếu thông tin và AI đang bật → hỏi AI
   g. Lưu: khử trùng tệp theo hash → INSERT email → tệp → công việc (trong một giao dịch)
6. Đóng bản ghi phien_dong_bo với đầy đủ số liệu
```

### 2.4. Ghi tệp lớn vào MySQL

Để không vượt `max_allowed_packet` của máy chủ:

```sql
INSERT INTO tep_du_lieu (hash_file, ..., noi_dung, da_hoan_tat) VALUES (?, ..., '', 0);
UPDATE tep_du_lieu SET noi_dung = CONCAT(noi_dung, X'....') WHERE id = ?;   -- lặp theo từng khối
UPDATE tep_du_lieu SET da_hoan_tat = 1 WHERE id = ?;
```

Kích thước khối tự động thu nhỏ theo `max_allowed_packet` thực tế đọc được từ máy chủ. Cờ
`da_hoan_tat` bảo đảm một bản ghi dở dang (do mất kết nối) sẽ bị ghi đè ở lần chạy sau chứ không
bị dùng nhầm.

Ở chế độ API, tệp được tải lên theo từng khối base64 rồi mới ghép và **kiểm tra lại SHA-256** trước
khi chuyển vào kho — sai một byte là từ chối, tránh dữ liệu hỏng âm thầm.

### 2.5. Giao diện đồ hoạ nhúng

`cpp/webui/{index.html, app.css, app.js}` được chuyển thành mảng byte C++ lúc biên dịch
(`cmake/nhung_tai_nguyen.cmake`) và nhúng thẳng vào tệp thực thi. Khi chạy, chương trình mở một máy
chủ HTTP nhỏ ở `127.0.0.1:8899` và tự mở trình duyệt. Nhờ vậy:

- Không cần cài framework đồ hoạ, tệp thực thi vẫn chỉ khoảng 1–3 MB.
- Giao diện đồng nhất trên Windows và Linux.
- Chạy được cả trên VPS không màn hình (truy cập từ máy khác qua `--dia-chi 0.0.0.0`).
- Cùng một máy chủ HTTP đó nhận luôn chuyển hướng OAuth của Google.

---

## 3. Phần web (PHP)

### 3.1. Nguyên tắc

- **PHP thuần**, không composer, không framework → chép lên hosting là chạy.
- Định tuyến bằng `index.php?t=<tên-trang>` → không phụ thuộc `mod_rewrite`.
- Truy vấn qua PDO **luôn dùng tham số ràng buộc**; mọi dữ liệu in ra đều qua `Util::h()`.
- Mọi biểu mẫu POST đều có **CSRF token**.
- Mật khẩu băm bằng `password_hash()` (bcrypt), khoá tài khoản 15 phút sau 8 lần sai.
- Biểu đồ vẽ bằng **SVG sinh phía máy chủ** → không tải thư viện từ Internet, in giấy vẫn đẹp.

### 3.2. Các lớp lõi

| Lớp | Nhiệm vụ |
|-----|---------|
| `Db` | PDO, luôn `SET time_zone='+07:00'`, các hàm rút gọn `mot/tatCa/giaTri/chen/capNhat` |
| `Ung` | Đọc/ghi bảng `cau_hinh`, thương hiệu, giá trị mặc định |
| `Auth` | Đăng nhập, phân quyền, ghi nhớ đăng nhập bằng cặp *chọn/bí mật* |
| `Util` | Bỏ dấu, chuẩn hoá mã, định dạng giờ Việt Nam, CSRF, thông báo nhanh |
| `View` | Kết xuất giao diện, phân trang, huy hiệu trạng thái, biểu đồ SVG |
| `NhatKy` | Ghi nhật ký theo mức, dọn nhật ký cũ |
| `Ai` | Gọi dịch vụ tương thích OpenAI, gợi ý phân luồng |
| `LuuMail` | **Toàn bộ nghiệp vụ tiếp nhận**: danh mục, tải tệp theo khối, chống trùng, lưu công việc |

### 3.3. Phân quyền

| Vai trò | Phạm vi |
|---------|---------|
| `nguoi_xu_ly` | Chỉ thấy văn bản được phân công cho mình |
| `lanh_dao` | Xem toàn bộ văn bản, không sửa danh mục / cài đặt |
| `admin` | Toàn quyền: danh mục, phân luồng tay, cài đặt, nhật ký |

Riêng cờ `nhan_tat_ca` cho phép một người xử lý bình thường xem được mọi văn bản (dùng cho văn thư).

Việc tải tệp luôn kiểm tra lại quyền ở phía máy chủ, không dựa vào việc ẩn nút trên giao diện.

---

## 4. Cơ sở dữ liệu

### 4.1. Các bảng

| Bảng | Vai trò |
|------|---------|
| `cau_hinh` | Mọi thiết lập hệ thống (khoá → giá trị) |
| `truong` | Danh mục trường **(bắt buộc)** |
| `nguoi_xu_ly` | Danh mục người xử lý **(bắt buộc)**, đồng thời là tài khoản đăng nhập |
| `bi_danh_nguoi_xu_ly` | Nhiều mã viết tắt cùng trỏ về một người |
| `van_ban` | Danh mục mã văn bản **(nới lỏng)**, cờ `tu_dong_tao` đánh dấu mã hệ thống tự thêm |
| `van_ban_truong` | Phạm vi trường phải nộp (khi `pham_vi = chon_loc`) |
| `email` | Bản sao thư gốc + các mã băm chống trùng |
| `tep_du_lieu` | Kho tệp dạng BLOB, **khử trùng lặp theo SHA-256** |
| `tep_dinh_kem` | Siêu dữ liệu tệp của từng thư, trỏ tới `tep_du_lieu` |
| `cong_viec` | Đơn vị phân luồng tới người xử lý |
| `cong_viec_tep` | Liên kết công việc ↔ tệp |
| `nhat_ky` | Nhật ký toàn hệ thống |
| `phien_dong_bo` | Lịch sử các phiên nhận mail |
| `tai_len_tam` | Vùng đệm tải tệp theo khối (chế độ API) |
| `phien_ghi_nho` | Cookie ghi nhớ đăng nhập |

### 4.2. Vì sao lưu tệp trong CSDL?

- Sao lưu **một lần là đủ**: bản dump SQL chứa trọn văn bản lẫn tệp.
- Không lo phân quyền thư mục hay ai đó tải trực tiếp tệp qua URL.
- Khử trùng lặp theo mã băm giúp cùng một báo cáo gửi lại nhiều lần chỉ chiếm một chỗ.

Đổi lại, bản sao lưu sẽ lớn hơn. Có thể giới hạn dung lượng tệp tối đa trong *Cài đặt*.

### 4.3. Các mã băm chống trùng

```
hash_noi_dung = SHA256( tiêu_đề_chuẩn_hoá + "\n" + người_gửi + "\n" + thân_thư_chuẩn_hoá )
hash_tep      = SHA256( sắp_xếp( "hash_tệp:dung_lượng" ) nối bằng "|" )
hash_tong_hop = SHA256( hash_noi_dung + "|" + hash_tep )
```

*Chuẩn hoá tiêu đề*: bỏ dấu tiếng Việt, in hoa, loại bỏ các tiền tố `RE:`, `FW:`, `FWD:`… lặp lại,
gom khoảng trắng. Nhờ vậy `Re: Fw: Báo cáo tháng 9` và `BÁO CÁO THÁNG 9` được coi là cùng nội dung.

---

## 5. Đánh số phiên bản

`VERSION` (dạng `X.Y.Z`) và `BUILD` (số nguyên) là nguồn duy nhất. `scripts/phien-ban.sh` sinh ra:

- `cpp/include/phien_ban.h` → hiển thị trên bảng điều khiển và trong `mailrouter --giup`
- `php/app/phien_ban.php` → hiển thị ở chân trang web và dùng làm tham số `?v=` cho CSS/JS

Mỗi lần chạy `scripts/dong-goi.sh`, **số build tự tăng** để hai phần luôn khớp nhau và người dùng
nhận được CSS/JS mới thay vì bản cũ trong bộ nhớ đệm trình duyệt.

---

## 6. Những điểm đã cân nhắc về an toàn

| Rủi ro | Biện pháp |
|--------|-----------|
| SQL injection | PDO tham số ràng buộc (PHP); hàm thoát chuỗi + literal `X'..'` cho BLOB (C++) |
| XSS | `Util::h()` cho mọi dữ liệu in ra; tệp xem trực tiếp bị ép `Content-Type` và có CSP riêng |
| CSRF | Token bắt buộc cho mọi thao tác ghi |
| Dò mật khẩu | Khoá tài khoản 15 phút sau 8 lần sai, có ghi nhật ký |
| Lộ tệp cấu hình | `.htaccess` chặn `cau-hinh.php`, `*.sql`, `*.ini`, thư mục `app/` và `sql/` |
| Lộ khoá API | Khoá không bao giờ được trả về qua API; sinh lại được bất cứ lúc nào |
| Truy cập tệp trái phép | Kiểm tra quyền ở phía máy chủ trước khi trả nội dung tệp |
| Giao diện bộ nhận mail bị truy cập từ ngoài | Mặc định chỉ nghe `127.0.0.1`; mở rộng thì bắt buộc đặt `mat_khau_giao_dien` |
| Dữ liệu hỏng khi truyền | Kiểm tra lại SHA-256 sau khi ghép các khối tải lên |
