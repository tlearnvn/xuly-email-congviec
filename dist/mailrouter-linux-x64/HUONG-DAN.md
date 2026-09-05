# Hướng dẫn bộ nhận mail (MailRouter)

*Hệ thống phân luồng Mail công vụ — Thiết kế bởi Trương Anh Tuấn*

Bộ nhận mail là chương trình chạy trên **máy nhà hoặc VPS**, có nhiệm vụ đăng nhập Gmail, đọc thư,
tách mã, chống trùng và đẩy toàn bộ dữ liệu lên máy chủ MySQL của phần web.

---

## 1. Cài đặt

### Windows 64-bit

1. Giải nén `mailrouter-windows-x64-vX.Y.Z.zip` vào một thư mục, ví dụ `D:\MailRouter`.
2. Nhấp đúp **`chay-bo-nhan-mail.bat`**.
3. Trình duyệt tự mở bảng điều khiển tại `http://127.0.0.1:8899`.

Không cần cài .NET, Visual C++ Redistributable hay bất cứ thứ gì khác — tệp `.exe` đã được liên kết
tĩnh và chỉ dùng các thư viện có sẵn của Windows.

### Linux 64-bit

```bash
tar -xzf mailrouter-linux-x64-vX.Y.Z.tar.gz
cd mailrouter-linux-x64
chmod +x mailrouter
cp mailrouter.example.ini mailrouter.ini
./mailrouter
```

Yêu cầu duy nhất là thư viện `libcurl`:
```bash
sudo apt install libcurl4        # Debian / Ubuntu
sudo yum install libcurl         # CentOS / RHEL / AlmaLinux
```

Trên máy chủ không có màn hình, dùng:
```bash
./mailrouter giao-dien --khong-mo --dia-chi 0.0.0.0 --cong 8899
```
rồi truy cập từ máy khác. **Khi mở cho mạng ngoài, hãy đặt `mat_khau_giao_dien` trong
`mailrouter.ini`** để yêu cầu khoá truy cập.

---

## 2. Bảng điều khiển

Giao diện gồm 6 mục ở thanh bên trái:

| Mục | Nội dung |
|-----|---------|
| **Tổng quan** | Trạng thái 4 thành phần, tiến trình phiên đang chạy, kết quả phiên gần nhất, nhật ký trực tiếp |
| **Kết nối máy chủ** | Chọn cách lưu dữ liệu: MySQL trực tiếp hoặc qua API PHP |
| **Tài khoản Gmail** | Đăng nhập bằng Client ID/Secret hoặc dán sẵn token |
| **Phân luồng & đồng bộ** | Điều kiện lọc thư, chạy nhận mail, bật dịch vụ tự động |
| **Trợ lý AI** | Cấu hình dịch vụ AI tương thích OpenAI |
| **Nhật ký** | Nhật ký đầy đủ, lọc theo mức và từ khoá |

---

## 3. Bước 1 — Kết nối máy chủ dữ liệu

Có **hai chế độ**, chọn một:

### Chế độ A — MySQL trực tiếp *(nhanh hơn)*

Điền máy chủ, cổng, người dùng, mật khẩu, tên cơ sở dữ liệu.

> Trên hosting cPanel phải vào **Remote MySQL** thêm địa chỉ IP của máy này thì mới kết nối được.
> Xem IP hiện tại tại `https://ifconfig.me`.

### Chế độ B — Qua API PHP *(khuyên dùng khi IP nhà hay đổi)*

- **Địa chỉ API**: lấy trong *Cài đặt → Kết nối bộ nhận mail* trên web, dạng
  `https://ten-mien.vn/api/ingest.php`.
- **Khoá API**: chép từ cùng trang đó.

Chế độ này chỉ dùng HTTPS cổng 443 nên không cần mở thêm cổng nào, hoạt động sau NAT/router bình
thường. Tệp lớn được tải lên theo từng khối để không vượt `post_max_size` của hosting.

Bấm **Lưu & kết nối**. Thành công thì thẻ *Kho lưu trữ* ở Tổng quan chuyển sang màu xanh và danh
mục được nạp về.

---

## 4. Bước 2 — Đăng nhập Gmail

### Cách 1 — Từ đầu bằng Client ID / Client Secret *(khuyên dùng)*

1. Tạo OAuth Client theo [docs/GMAIL-OAUTH.md](GMAIL-OAUTH.md).
2. Dán **Client ID** và **Client Secret** vào ô tương ứng.
3. Bấm **Đăng nhập bằng Google** → chọn tài khoản → *Cho phép*.
4. Trình duyệt hiện trang báo thành công, quay lại bảng điều khiển là xong.

Hệ thống lưu **refresh token** nên chỉ cần làm một lần; access token tự gia hạn khi hết hạn.

### Cách 2 — Đã có sẵn token

Dán **Access token** và/hoặc **Refresh token** vào ô tương ứng rồi bấm **Lưu token**.
Có refresh token kèm Client ID/Secret thì token vẫn tự gia hạn.

Bấm **Kiểm tra hộp thư** để xác nhận.

> Hệ thống chỉ xin quyền `gmail.readonly` — **đọc thư, không xoá, không gửi, không sửa nhãn**.

---

## 5. Bước 3 — Điều kiện lọc và chạy

**Phân luồng & đồng bộ** → *Điều kiện tìm kiếm của Gmail*. Dùng đúng cú pháp tìm kiếm của Gmail:

| Điều kiện | Ý nghĩa |
|-----------|---------|
| `has:attachment newer_than:30d` | Thư có tệp đính kèm trong 30 ngày gần đây (mặc định) |
| `has:attachment newer_than:2d` | Chỉ 2 ngày gần đây — hợp với chế độ chạy thường xuyên |
| `from:@dongnai.edu.vn has:attachment` | Chỉ thư từ tên miền của ngành |
| `label:baocao` | Chỉ thư đã gắn nhãn *baocao* |
| `is:unread has:attachment` | Chỉ thư chưa đọc |

Bấm **Nhận mail ngay**. Theo dõi tiến trình ở mục *Tổng quan*.

### Chạy tự động

- **Bật dịch vụ tự động** trong bảng điều khiển: chương trình tự quét theo *Chu kỳ (phút)*, chỉ
  hoạt động khi cửa sổ chương trình còn mở.
- **Chạy nền thật sự**: xem mục 7.

---

## 6. Chế độ dòng lệnh

```bash
mailrouter                       # mở bảng điều khiển (mặc định)
mailrouter nhan                  # chạy một phiên nhận mail rồi thoát
mailrouter nhan -n 100           # giới hạn 100 thư
mailrouter nhan -q "has:attachment newer_than:2d"
mailrouter dich-vu               # chạy nền theo chu kỳ, không mở giao diện
mailrouter kiem-tra              # kiểm tra kết nối CSDL / Gmail / AI rồi thoát
mailrouter cau-hinh              # in ra đường dẫn và nội dung tệp cấu hình
mailrouter --giup                # xem toàn bộ tham số
```

Tham số hữu ích:

| Tham số | Ý nghĩa |
|---------|---------|
| `-c, --cau-hinh <tệp>` | Dùng tệp cấu hình khác (chạy nhiều hộp thư trên cùng một máy) |
| `-p, --cong <số>` | Đổi cổng giao diện (mặc định 8899) |
| `--dia-chi 0.0.0.0` | Cho máy khác trong mạng truy cập giao diện |
| `--khong-mo` | Không tự mở trình duyệt |
| `-v, --chi-tiet` | Ghi nhật ký mức gỡ lỗi |
| `--khong-mau` | Tắt màu ANSI (dùng khi ghi nhật ký ra tệp) |

Mã thoát: `0` = thành công, `1` = có lỗi, `2` = sai tham số.

---

## 7. Chạy nền tự động

### Windows — Task Scheduler

1. Mở **Task Scheduler** → *Create Basic Task*.
2. Tên: `Nhan mail cong vu`. Trigger: *Daily*, lặp lại mỗi 15 phút trong 1 ngày.
3. Action: *Start a program* → chọn `nhan-mail-mot-lan.bat` trong thư mục chương trình.
4. Trong *Properties*, tích *Run whether user is logged on or not*.

### Linux — systemd *(khuyên dùng)*

```bash
sudo useradd -r -s /usr/sbin/nologin mailrouter
sudo mkdir -p /opt/mailrouter && sudo cp -r mailrouter-linux-x64/* /opt/mailrouter/
sudo chown -R mailrouter:mailrouter /opt/mailrouter
sudo cp /opt/mailrouter/mailrouter.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now mailrouter
sudo systemctl status mailrouter
journalctl -u mailrouter -f        # xem nhật ký trực tiếp
```

### Linux — cron

```bash
crontab -e
```
rồi thêm:
```
*/15 * * * * cd /opt/mailrouter && TZ=Asia/Ho_Chi_Minh ./mailrouter nhan --khong-mau >> /opt/mailrouter/nhat_ky/cron.log 2>&1
```

---

## 8. Tệp cấu hình `mailrouter.ini`

Nằm cạnh tệp thực thi. Mọi thiết lập đều sửa được từ bảng điều khiển, nhưng có thể sửa tay:

```ini
[luu_tru]
che_do = mysql              ; mysql | api

[mysql]
may_chu = 103.x.x.x
cong = 3306
nguoi_dung = taikhoan_mail
mat_khau = ...
co_so_du_lieu = taikhoan_phanluong
kich_thuoc_khoi_kb = 256    ; giảm nếu max_allowed_packet nhỏ

[api]
url = https://ten-mien.vn/api/ingest.php
khoa = ...
kich_thuoc_khoi_kb = 512    ; giảm nếu hosting giới hạn post_max_size

[gmail]
client_id = ...
client_secret = ...
refresh_token = ...         ; tự điền sau khi đăng nhập
truy_van =                  ; để trống = lấy theo cấu hình trên máy chủ

[ai]
bat =                       ; để trống = lấy theo cấu hình trên máy chủ

[ung_dung]
dia_chi_giao_dien = 127.0.0.1
cong_giao_dien = 8899
mat_khau_giao_dien =        ; đặt khi mở cho mạng ngoài
tu_bat_dich_vu = 0          ; 1 = tự bật dịch vụ khi khởi động
chu_ky_phut = 15
muc_nhat_ky = info          ; debug | info | canh_bao | loi
dung_luong_tep_toi_da_mb = 25
```

**Các ô để trống trong `[gmail]`, `[ai]` sẽ lấy giá trị từ bảng `cau_hinh` trên máy chủ** — nhờ vậy
quản trị đổi thiết lập một lần trên web là mọi máy nhận mail đều áp dụng.

---

## 9. Quy tắc phân luồng

Thứ tự ưu tiên khi tìm mã:

```
1. Tên tệp đính kèm       001_001_TAT_BaoCaoThang9.pdf
2. Tiêu đề thư            [001_001_TAT] Báo cáo tháng 9
3. Địa chỉ email người gửi (chỉ suy ra mã trường)
4. Người xử lý mặc định của mã văn bản
5. Người xử lý mặc định của hệ thống
6. Trợ lý AI (nếu bật và đạt ngưỡng tin cậy)
7. Không được ⇒ đưa vào hàng chờ phân luồng tay trên web
```

Bộ tách mã chấp nhận:
- Dấu phân cách `_`, `-`, `.`, khoảng trắng: `001-001-TAT`, `001 001 TAT`, `001.001.TAT`.
- Mã người xử lý viết thường: `001_001_tat`.
- Có chữ thừa xung quanh: `BC_001_001_TAT_thang9.pdf`, `Báo cáo 001_001_TAT gửi Sở.pdf`.
- Mã trường bỏ số 0 đầu: `1_001_TAT` khớp với trường `001`.

Và **từ chối** các tên tệp thông thường để không nhận nhầm:
`tai lieu khong ro.pdf`, `Bao cao tong ket nam hoc.docx`, `IMG_2024_0912.jpg` → đưa vào hàng chờ.

Một thư có nhiều tệp thuộc nhiều người xử lý khác nhau sẽ được tách thành nhiều công việc riêng,
mỗi công việc gắn đúng nhóm tệp của mình.

---

## 10. Quy tắc chống trùng

| Tình huống | Kết quả |
|-----------|---------|
| Cùng mã thư Gmail | Bỏ qua, không ghi thêm |
| Cùng nội dung **và** cùng bộ tệp | Ghi nhận *trùng lặp*, không tạo công việc mới |
| Cùng nội dung, **tệp khác dung lượng / khác nội dung** | Tạo **phiên bản mới**; bản cũ đánh dấu đã thay thế |
| Tệp giống hệt nhau ở nhiều thư | Chỉ lưu **một bản** trong CSDL, các thư cùng tham chiếu tới |

*Nội dung* được so bằng SHA-256 của (tiêu đề đã bỏ tiền tố `RE:`/`FW:` + người gửi + thân thư đã
chuẩn hoá). *Bộ tệp* được so bằng SHA-256 của danh sách `mã băm tệp : dung lượng` đã sắp xếp.

---

## 11. Xử lý sự cố

| Hiện tượng | Cách xử lý |
|-----------|-----------|
| *Không tìm thấy thư viện libcurl* | `sudo apt install libcurl4` (Debian/Ubuntu) |
| *Không mở được cổng 8899* | Cổng đang bận. Chạy `mailrouter -p 9100`, hoặc để chương trình tự chọn cổng trống |
| *Chưa có refresh token* | Đăng nhập lại bằng Client ID/Secret ở mục Tài khoản Gmail |
| *Câu lệnh dài … vượt max_allowed_packet* | Giảm `kich_thuoc_khoi_kb` trong `[mysql]` xuống 128 hoặc 64 |
| *Tài khoản dùng caching_sha2_password…* | Chạy trên máy chủ MySQL: `ALTER USER 'user'@'%' IDENTIFIED WITH mysql_native_password BY '<mật khẩu>';` hoặc chuyển sang chế độ API |
| Kết nối MySQL bị từ chối từ máy nhà | Thêm IP vào *Remote MySQL* của cPanel, hoặc chuyển sang chế độ API |
| Thư có nhưng không thấy trên web | Xem *Nhật ký* — nhiều khả năng đã bị đánh dấu trùng, hoặc đang nằm ở *Chờ phân luồng* |
| Nhật ký ghi *Bỏ qua tệp … vượt giới hạn* | Tăng `dung_luong_tep_toi_da_mb`, đồng thời tăng *Dung lượng tệp tối đa* trong Cài đặt trên web |

Nhật ký được ghi ra ba nơi: màn hình, thư mục `nhat_ky/` cạnh chương trình, và bảng `nhat_ky` trên
máy chủ — thuận tiện cho việc dò tìm sự cố về sau.
