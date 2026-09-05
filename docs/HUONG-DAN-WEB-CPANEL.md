# Hướng dẫn cài đặt phần web trên hosting cPanel

*Hệ thống phân luồng Mail công vụ — Thiết kế bởi Trương Anh Tuấn*

---

## 1. Chuẩn bị

Đăng nhập cPanel và kiểm tra:

| Hạng mục | Yêu cầu | Vị trí trong cPanel |
|----------|---------|---------------------|
| Phiên bản PHP | 7.4 trở lên (khuyến nghị 8.1–8.3) | *Select PHP Version* / *MultiPHP Manager* |
| Phần mở rộng | `pdo_mysql`, `mbstring`, `json`, `curl` | *Select PHP Version → Extensions* |
| Cơ sở dữ liệu | MySQL ≥ 5.7 hoặc MariaDB ≥ 10.3 | *MySQL Databases* |
| Chứng chỉ SSL | Nên bật (Let's Encrypt miễn phí) | *SSL/TLS Status* |

> **Lưu ý:** bật `curl` nếu muốn dùng trợ lý AI. Không có `curl` thì mọi chức năng khác vẫn chạy
> bình thường, chỉ riêng phần AI bị tắt.

---

## 2. Tạo cơ sở dữ liệu

1. Vào **MySQL Databases**.
2. Mục *Create New Database*: đặt tên, ví dụ `phanluong` → cPanel tạo thành `taikhoan_phanluong`.
3. Mục *Add New User*: tạo người dùng, ví dụ `mail` → thành `taikhoan_mail`. Đặt mật khẩu mạnh và
   **lưu lại**.
4. Mục *Add User To Database*: chọn cặp vừa tạo → **ALL PRIVILEGES** → *Make Changes*.

Ghi lại bốn thông tin: **máy chủ** (thường là `localhost`), **tên CSDL**, **người dùng**,
**mật khẩu**.

---

## 3. Tải mã nguồn lên

1. Giải nén `web-cpanel-vX.Y.Z.zip` trên máy.
2. Vào **File Manager** → thư mục `public_html` (hoặc thư mục con, ví dụ `public_html/phanluong`).
3. Tải toàn bộ nội dung lên. Nhanh nhất: nén thành `.zip`, dùng nút **Upload** rồi **Extract**.

Cấu trúc sau khi tải lên:

```
public_html/            (hoặc public_html/phanluong/)
├── index.php
├── cai-dat.php
├── cau-hinh.mau.php
├── .htaccess
├── api/ingest.php
├── app/
├── assets/
└── sql/
```

> Đặt trong thư mục con cũng chạy bình thường, hệ thống tự nhận biết đường dẫn gốc.

---

## 4. Chạy trình cài đặt

Mở trình duyệt tới `https://ten-mien-cua-ban.vn/cai-dat.php`
(hoặc `https://ten-mien-cua-ban.vn/phanluong/cai-dat.php`).

Trình cài đặt gồm 4 bước:

1. **Kiểm tra môi trường** — mọi dòng phải màu xanh (riêng cURL có thể thiếu nếu không dùng AI).
2. **Kết nối cơ sở dữ liệu** — điền 4 thông tin ở mục 2. Tích *Nạp danh mục mẫu* nếu muốn có sẵn
   dữ liệu để dùng thử.
3. **Tài khoản quản trị** — đặt mã người xử lý (ví dụ `TAT`), tên đăng nhập và mật khẩu.
4. **Thương hiệu** — tên ứng dụng, tên đơn vị, dòng bản quyền (sửa lại được sau trong *Cài đặt*).

Bấm **Tiến hành cài đặt**. Màn hình sẽ hiện **khoá API** — hãy chép lại, sẽ dùng cho bộ nhận mail.

### Sau khi cài xong — bắt buộc làm ngay

```
1. Xoá tệp cai-dat.php trên máy chủ (File Manager → chọn tệp → Delete).
2. Nếu đã nạp dữ liệu mẫu: đổi hoặc khoá các tài khoản mẫu
   (nva / ltc / pvd / lanhdao — mật khẩu mặc định 123456).
3. Kiểm tra tệp cau-hinh.php không truy cập được qua trình duyệt
   (mở https://ten-mien.vn/cau-hinh.php phải báo lỗi 403).
```

---

## 5. Cấu hình sau cài đặt

Đăng nhập bằng tài khoản quản trị vừa tạo.

### 5.1. Danh mục trường *(bắt buộc)*

**Danh mục → Trường**. Thêm từng trường, hoặc dùng ô **Nhập danh sách trường hàng loạt**:

```
001	THPT Chuyên Lương Thế Vinh	THPT	Biên Hòa	c3luongthevinh@dongnai.edu.vn
002	THPT Ngô Quyền	THPT	Biên Hòa	c3ngoquyen@dongnai.edu.vn
```

Thứ tự cột: `mã trường`, `tên trường`, `cấp học`, `địa bàn`, `email`. Cách nhau bằng phím **Tab**
hoặc dấu `;`. Mã đã có sẽ được cập nhật thay vì tạo mới.

Khai báo **email của trường** giúp hệ thống đoán được mã trường khi tên tệp thiếu mã.

### 5.2. Danh mục người xử lý *(bắt buộc)*

**Danh mục → Người xử lý**. Mỗi chuyên viên là một tài khoản đăng nhập:

- **Mã người xử lý**: phần cuối trong tên tệp, ví dụ `TAT`.
- **Bí danh**: nếu các trường quen viết nhiều kiểu (`TUAN`, `ATUAN`…), khai báo ở đây, cách nhau
  bằng dấu phẩy.
- **Vai trò**: *Người xử lý* (chỉ thấy việc của mình), *Lãnh đạo* (xem tất cả), *Quản trị*
  (toàn quyền).
- Tích *Buộc đổi mật khẩu ở lần đăng nhập kế tiếp* khi cấp tài khoản mới.

### 5.3. Danh mục mã văn bản *(nới lỏng)*

**Danh mục → Mã văn bản**. Đây là danh mục **không bắt buộc phải cập nhật kịp** — khi gặp mã lạ,
hệ thống vẫn nhận thư và tự tạo bản ghi tạm tên *"Văn bản/công việc mã XXX"* (hiển thị nền vàng).
Quản trị chỉ cần vào đặt lại tên chính thức khi rảnh; số liệu thống kê không bị mất.

Với các báo cáo cần theo dõi đơn vị nào đã nộp:
- Tích **Đưa vào thống kê nộp / chưa nộp**.
- Chọn **Kỳ báo cáo** và **Hạn nộp**.
- **Phạm vi**: *Tất cả các trường* hoặc *Chỉ một số trường* (rồi chọn danh sách).
- **Người xử lý mặc định**: dùng khi tên tệp có mã trường + mã văn bản nhưng thiếu mã người xử lý.

### 5.4. Kết nối bộ nhận mail

**Cài đặt → Kết nối bộ nhận mail**. Tại đây có sẵn:
- **Địa chỉ API** để dán vào bộ nhận mail.
- **Khoá API** — bấm *Sinh khoá API mới* nếu lỡ để lộ.

Nếu bộ nhận mail nối thẳng vào MySQL (xem mục 6), có thể tắt hẳn cổng API cho an toàn.

### 5.5. Trợ lý AI

**Cài đặt → Trợ lý AI**: bật, điền địa chỉ API, khoá, model, max tokens (≤ 64000), timeout
(≤ 300 giây), ngưỡng tin cậy. Bấm **Kiểm tra kết nối AI** để thử ngay.

---

## 6. Cho phép bộ nhận mail nối thẳng MySQL *(tuỳ chọn)*

Cách này nhanh hơn chế độ API nhưng cần mở cổng:

1. cPanel → **Remote MySQL**.
2. Thêm địa chỉ IP của máy chạy bộ nhận mail (xem IP tại `https://ifconfig.me`).
3. Nếu IP nhà thay đổi liên tục, dùng **chế độ API** thay vì mở `%` cho toàn Internet.

> Không nên thêm `%` (mọi IP) vào Remote MySQL — rất mất an toàn.

---

## 7. Kiểm tra hoạt động

1. Bật bộ nhận mail, bấm **Nhận mail ngay**.
2. Trên web: **Bảng điều khiển** phải hiện số liệu, **Nhật ký** phải có dòng *nhan_mail*.
3. Mở một văn bản, thử **Xem** và **Tải về** tệp đính kèm.
4. Vào **Thống kê nộp báo cáo**, chọn một mã văn bản để xem trường nào đã/chưa nộp.

---

## 8. Xử lý sự cố thường gặp

| Hiện tượng | Nguyên nhân & cách xử lý |
|-----------|--------------------------|
| Trắng trang, không báo gì | Mở `cau-hinh.php`, đổi `'go_loi' => true` để xem lỗi chi tiết. Nhớ đổi lại `false` sau khi sửa xong. |
| *Không kết nối được cơ sở dữ liệu* | Sai thông tin trong `cau-hinh.php`. Kiểm tra lại tên CSDL/người dùng — cPanel luôn thêm tiền tố `taikhoan_`. |
| Bộ nhận mail báo *Khoá API không hợp lệ* | Khoá trong bộ nhận mail khác khoá trên web. Vào *Cài đặt → Kết nối bộ nhận mail* chép lại. |
| Tải tệp lớn bị lỗi giữa chừng | Giảm *Kích thước mỗi khối tải lên* xuống 256 KB hoặc 128 KB trong *Cài đặt → Kết nối bộ nhận mail*. |
| Tiếng Việt hiển thị thành dấu hỏi | Cơ sở dữ liệu không phải `utf8mb4`. Chạy lại `sql/01_schema.sql` trên CSDL trống. |
| Giờ hiển thị lệch | Hệ thống luôn dùng GMT+7; nếu vẫn lệch, kiểm tra `date.timezone` trong *Select PHP Version → Options*. |
| Tệp đính kèm chiếm nhiều dung lượng | Xem *Cài đặt → Số liệu kho dữ liệu*. Tệp trùng nhau chỉ lưu một lần; nếu vẫn lớn, cân nhắc giảm *Dung lượng tệp tối đa*. |

---

## 9. Sao lưu

Sao lưu định kỳ hai thứ:

1. **Cơ sở dữ liệu** — cPanel → *Backup* → *Download a MySQL Database Backup*. Đây là nơi chứa
   **toàn bộ** thư và tệp đính kèm.
2. **Tệp `cau-hinh.php`** — chứa thông tin kết nối.

> Vì tệp đính kèm nằm trong CSDL dạng BLOB, bản sao lưu SQL có thể khá lớn. Nên đặt lịch sao lưu
> hằng tuần và tải về máy lưu trữ riêng.
