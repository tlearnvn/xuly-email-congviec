# Tạo Client ID / Client Secret cho Gmail

*Hệ thống phân luồng Mail công vụ — Thiết kế bởi Trương Anh Tuấn*

Để bộ nhận mail đọc được hộp thư, cần tạo một **OAuth Client** trong Google Cloud Console. Việc này
chỉ làm **một lần**, mất khoảng 10 phút.

---

## 1. Tạo dự án

1. Vào <https://console.cloud.google.com/> và đăng nhập bằng chính tài khoản Gmail công vụ (hoặc
   tài khoản quản trị của tổ chức).
2. Bấm hộp chọn dự án ở thanh trên → **New Project**.
3. Tên dự án: `Phan luong Mail cong vu` → **Create**.
4. Chờ vài giây rồi chọn dự án vừa tạo.

---

## 2. Bật Gmail API

1. Menu trái → **APIs & Services → Library**.
2. Gõ tìm **Gmail API** → chọn kết quả → bấm **Enable**.

---

## 3. Khai báo màn hình đồng ý (OAuth consent screen)

1. Menu trái → **APIs & Services → OAuth consent screen**.
2. Chọn loại người dùng:
   - **Internal** — nếu tài khoản thuộc Google Workspace của Sở. Đây là lựa chọn tốt nhất: không
     cần kiểm duyệt, không giới hạn thời gian.
   - **External** — nếu dùng Gmail thường (`@gmail.com`).
3. Điền các mục bắt buộc:
   - *App name*: `Phan luong Mail cong vu`
   - *User support email*: chọn email của bạn
   - *Developer contact information*: email của bạn
4. **Save and Continue**.
5. Bước **Scopes**: bấm *Add or Remove Scopes*, tìm và tích:
   ```
   https://www.googleapis.com/auth/gmail.readonly
   ```
   → **Update** → **Save and Continue**.
6. Bước **Test users** *(chỉ hiện khi chọn External)*: bấm **Add Users**, nhập chính địa chỉ Gmail
   sẽ dùng để nhận văn bản → **Save and Continue**.

> **Quan trọng với loại External:** khi ứng dụng ở trạng thái *Testing*, refresh token sẽ hết hạn
> sau **7 ngày** và phải đăng nhập lại. Để dùng lâu dài, vào *OAuth consent screen* bấm
> **Publish App** (chuyển sang *In production*). Vì chỉ xin quyền đọc thư của chính mình nên
> Google thường không yêu cầu kiểm duyệt; nếu có cảnh báo *"Google hasn't verified this app"*, bấm
> **Advanced → Go to … (unsafe)** để tiếp tục.

---

## 4. Tạo OAuth Client

1. Menu trái → **APIs & Services → Credentials**.
2. **+ Create Credentials → OAuth client ID**.
3. **Application type**: chọn **Desktop app** *(đơn giản nhất, khuyên dùng)*.
   - Đặt tên: `Bo nhan mail`
   - Bấm **Create**.
4. Hộp thoại hiện ra chứa **Client ID** và **Client Secret** — bấm **Download JSON** hoặc chép lại
   cả hai chuỗi.

### Nếu chọn "Web application" thay vì "Desktop app"

Phải khai báo địa chỉ chuyển hướng. Trong mục **Authorized redirect URIs**, thêm đúng dòng:

```
http://127.0.0.1:8899/oauth/callback
```

Nếu đổi cổng giao diện (ví dụ chạy `mailrouter -p 9100`) thì thêm cả:
```
http://127.0.0.1:9100/oauth/callback
```

> Địa chỉ chính xác luôn được hiển thị sẵn trong bảng điều khiển của bộ nhận mail, mục
> **Tài khoản Gmail** — chỉ cần chép nguyên văn.

---

## 5. Đăng nhập trong bộ nhận mail

1. Mở bảng điều khiển (`http://127.0.0.1:8899`) → **Tài khoản Gmail**.
2. Dán **Client ID** và **Client Secret**.
3. Bấm **Đăng nhập bằng Google**.
4. Cửa sổ mới mở ra → chọn tài khoản → **Cho phép**.
5. Màn hình báo *Đăng nhập Gmail thành công* → đóng thẻ, quay lại bảng điều khiển.

Refresh token được lưu vào `mailrouter.ini` nên **không cần đăng nhập lại** ở những lần sau.

---

## 6. Câu hỏi thường gặp

**Hệ thống có xoá thư của tôi không?**
Không. Chỉ xin quyền `gmail.readonly` — quyền này về mặt kỹ thuật **không cho phép** xoá, gửi hay
sửa thư. Google cũng hiển thị rõ điều đó ở màn hình đồng ý.

**Lỗi `redirect_uri_mismatch`?**
Địa chỉ chuyển hướng khai trong Google Cloud khác với địa chỉ bộ nhận mail đang dùng. Chép lại
đúng chuỗi hiển thị ở mục *Tài khoản Gmail*, kể cả số cổng.

**Lỗi `access_denied`?**
Với loại *External* ở trạng thái *Testing*, tài khoản của bạn phải nằm trong danh sách *Test users*.

**Lỗi `invalid_grant` sau vài ngày?**
Ứng dụng External đang ở trạng thái *Testing* (refresh token hết hạn sau 7 ngày). Hãy **Publish
App**, hoặc chuyển tài khoản sang Google Workspace và dùng loại *Internal*.

**Muốn dùng nhiều hộp thư cùng lúc?**
Chạy nhiều bản với tệp cấu hình và cổng khác nhau:
```bash
mailrouter -c hopthu-1.ini -p 8899
mailrouter -c hopthu-2.ini -p 8900
```

**Đã có sẵn token từ hệ thống khác?**
Bỏ qua toàn bộ hướng dẫn này, vào mục *Tài khoản Gmail* → **Cách 2**, dán access token và refresh
token rồi bấm **Lưu token**.

**Thu hồi quyền truy cập?**
Bấm **Đăng xuất** trong bảng điều khiển, hoặc vào
<https://myaccount.google.com/permissions> để gỡ ứng dụng.
