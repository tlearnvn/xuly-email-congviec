-- =====================================================================
--  HỆ THỐNG PHÂN LUỒNG MAIL CÔNG VỤ - DỮ LIỆU KHỞI TẠO
--  Thiết kế bởi Trương Anh Tuấn
-- ---------------------------------------------------------------------
--  Tài khoản quản trị mặc định:  admin / Admin@123   (buộc đổi mật khẩu)
--  Tài khoản người xử lý mẫu :  tat, nvb, ltc ... / 123456
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';

-- ---------------------------------------------------------------------
-- CẤU HÌNH HỆ THỐNG
-- ---------------------------------------------------------------------
INSERT INTO `cau_hinh` (`khoa`,`gia_tri`,`nhom`,`kieu`,`nhan`,`mo_ta`,`thu_tu`,`bi_mat`,`ngay_cap_nhat`) VALUES
-- Nhóm: giao diện / thương hiệu
('app.ten_ung_dung','Hệ thống phân luồng Mail công vụ - Phòng GDPT-GDTX SGDĐT Đồng Nai','giao_dien','text','Tên ứng dụng','Hiển thị trên thanh tiêu đề và đầu trang',1,0,NOW()),
('app.ten_ngan','Phân luồng Mail công vụ','giao_dien','text','Tên rút gọn','Dùng ở nơi hẹp (menu, tab trình duyệt)',2,0,NOW()),
('app.don_vi','Phòng GDPT-GDTX - Sở GD&ĐT Đồng Nai','giao_dien','text','Tên đơn vị','Hiển thị dưới tên ứng dụng',3,0,NOW()),
('app.ban_quyen','Thiết kế bởi Trương Anh Tuấn','giao_dien','text','Bản quyền / Copyright','Hiển thị ở chân trang',4,0,NOW()),
('app.mau_chu_dao','#1e5eff','giao_dien','text','Màu chủ đạo','Mã màu HEX cho giao diện',5,0,NOW()),
('app.logo','','giao_dien','text','Đường dẫn logo','Bỏ trống để dùng biểu tượng mặc định',6,0,NOW()),
('app.so_dong_moi_trang','20','giao_dien','number','Số dòng mỗi trang','Phân trang danh sách',7,0,NOW()),
('app.mui_gio','Asia/Ho_Chi_Minh','giao_dien','text','Múi giờ','Mặc định giờ Việt Nam (GMT+7)',8,0,NOW()),

-- Nhóm: phân luồng
('phanluong.mau_ma','^\\s*([0-9A-Za-z]{1,8})[ _\\-\\.]+([0-9A-Za-z]{1,10})[ _\\-\\.]+([A-Za-z]{2,10})','phan_luong','text','Mẫu nhận dạng mã','Thứ tự: mã trường _ mã văn bản _ mã người xử lý',1,0,NOW()),
('phanluong.uu_tien','ten_tep,tieu_de,ai,nguoi_gui','phan_luong','text','Thứ tự ưu tiên nhận dạng','Các nguồn cách nhau bởi dấu phẩy',2,0,NOW()),
('phanluong.cho_phep_ma_vb_moi','1','phan_luong','bool','Tự thêm mã văn bản lạ','Mã văn bản chưa có trong danh mục vẫn được nhận và tự tạo bản ghi tạm',3,0,NOW()),
('phanluong.bat_buoc_ma_truong','1','phan_luong','bool','Bắt buộc đúng mã trường','Mã trường không có trong danh mục sẽ chuyển sang chờ phân luồng tay',4,0,NOW()),
('phanluong.bat_buoc_ma_nguoi','1','phan_luong','bool','Bắt buộc đúng mã người xử lý','Mã người xử lý lạ sẽ chuyển sang chờ phân luồng tay',5,0,NOW()),
('phanluong.nguoi_xu_ly_mac_dinh','','phan_luong','text','Người xử lý mặc định','Mã người nhận các mail không xác định được (bỏ trống = đưa vào hàng chờ)',6,0,NOW()),
('phanluong.han_xu_ly_ngay','7','phan_luong','number','Hạn xử lý mặc định (ngày)','Tính từ ngày nhận',7,0,NOW()),

-- Nhóm: chống trùng
('trung.bat','1','chong_trung','bool','Bật kiểm tra trùng','Bỏ qua hoặc đánh dấu các mail trùng',1,0,NOW()),
('trung.tao_ban_moi_khi_tep_khac','1','chong_trung','bool','Trùng nội dung nhưng tệp khác => bản mới','Tạo phiên bản mới thay vì bỏ qua',2,0,NOW()),
('trung.bo_qua_tien_to','RE:,FW:,FWD:,TRA LOI:,CHUYEN TIEP:','chong_trung','text','Tiền tố bỏ qua khi so tiêu đề','Danh sách cách nhau bởi dấu phẩy',3,0,NOW()),
('trung.so_ngay_doi_chieu','365','chong_trung','number','Số ngày đối chiếu trùng','Chỉ so với mail trong khoảng thời gian này',4,0,NOW()),

-- Nhóm: AI
('ai.bat','0','ai','bool','Bật hỗ trợ AI','Dùng khi không đọc được mã từ tên tệp/tiêu đề',1,0,NOW()),
('ai.url','https://api.openai.com/v1','ai','text','Địa chỉ API (OpenAI compatible)','Ví dụ: https://api.openai.com/v1 , https://generativelanguage.googleapis.com/v1beta/openai , http://localhost:11434/v1',2,0,NOW()),
('ai.api_key','','ai','password','API Key','Khoá truy cập dịch vụ AI',3,1,NOW()),
('ai.model','gpt-4o-mini','ai','text','Tên model','Ví dụ: gpt-4o-mini, gpt-4.1-mini, qwen2.5:7b',4,0,NOW()),
('ai.max_tokens','4096','ai','number','Số token tối đa','Tối đa cho phép 64000',5,0,NOW()),
('ai.timeout','60','ai','number','Thời gian chờ (giây)','Tối đa cho phép 300 giây',6,0,NOW()),
('ai.temperature','0.1','ai','text','Độ sáng tạo (temperature)','0 = ổn định nhất, khuyến nghị 0.0 - 0.3',7,0,NOW()),
('ai.nguong_tin_cay','0.6','ai','text','Ngưỡng tin cậy','Dưới ngưỡng này sẽ đưa vào hàng chờ phân luồng tay',8,0,NOW()),
('ai.nhac_he_thong','Bạn là trợ lý văn thư của Sở Giáo dục và Đào tạo. Nhiệm vụ: đọc thông tin email và xác định MÃ TRƯỜNG, MÃ VĂN BẢN, MÃ NGƯỜI XỬ LÝ dựa trên danh mục được cung cấp. Chỉ trả lời bằng JSON.','ai','textarea','Câu nhắc hệ thống','Prompt hệ thống gửi kèm mỗi yêu cầu',9,0,NOW()),

-- Nhóm: Gmail
('gmail.hop_thu','','gmail','text','Địa chỉ hộp thư','Địa chỉ Gmail dùng để nhận văn bản',1,0,NOW()),
('gmail.truy_van','has:attachment newer_than:30d','gmail','text','Điều kiện lọc (Gmail query)','Cú pháp tìm kiếm của Gmail',2,0,NOW()),
('gmail.so_mail_moi_lan','50','gmail','number','Số mail mỗi lần quét','Giới hạn mỗi phiên đồng bộ',3,0,NOW()),
('gmail.chu_ky_phut','15','gmail','number','Chu kỳ quét (phút)','Dùng khi chạy chế độ dịch vụ',4,0,NOW()),
('gmail.giu_mail','1','gmail','bool','Không xoá mail sau khi lưu','Luôn bật - hệ thống chỉ đọc, không xoá',5,0,NOW()),
('gmail.quet_spam','1','gmail','bool','Quét cả hộp Thư rác (Spam)','Google hay xếp nhầm báo cáo của trường vào Thư rác; tắt đi là bỏ sót',6,0,NOW()),

-- Nhóm: API tiếp nhận
('api.bat','1','api','bool','Bật API tiếp nhận','Cho phép bộ nhận mail đẩy dữ liệu qua HTTPS',1,0,NOW()),
('api.khoa','DOI-KHOA-NAY-NGAY-LAP-TUC','api','password','Khoá API','Chuỗi bí mật dùng cho bộ nhận mail (đổi ngay sau khi cài)',2,1,NOW()),
('api.kich_thuoc_khoi_kb','512','api','number','Kích thước mỗi khối tải lên (KB)','Giảm xuống nếu hosting giới hạn post_max_size nhỏ',3,0,NOW()),
('api.dung_luong_toi_da_mb','25','api','number','Dung lượng tệp tối đa (MB)','Tệp lớn hơn sẽ bị bỏ qua và ghi nhật ký',4,0,NOW()),

-- Nhóm: nhật ký
('log.muc','info','nhat_ky','select','Mức ghi nhật ký','debug | info | canh_bao | loi',1,0,NOW()),
('log.so_ngay_giu','180','nhat_ky','number','Số ngày lưu nhật ký','Nhật ký cũ hơn sẽ được dọn',2,0,NOW())
ON DUPLICATE KEY UPDATE `gia_tri` = VALUES(`gia_tri`);

-- ---------------------------------------------------------------------
-- NGƯỜI XỬ LÝ  (mật khẩu mặc định: admin => Admin@123 ; còn lại => 123456)
-- ---------------------------------------------------------------------
INSERT INTO `nguoi_xu_ly`
 (`ma_nguoi_xu_ly`,`ho_ten`,`ten_dang_nhap`,`mat_khau`,`email`,`chuc_vu`,`phong_ban`,`vai_tro`,`nhan_tat_ca`,`trang_thai`,`doi_mat_khau`,`ngay_tao`,`ngay_cap_nhat`) VALUES
('TAT','Trương Anh Tuấn','admin','$2y$12$cLo4f6rWwAcgoi2RdH0sPuAuXV692PBD1Ajy.QU7aSraIT7HYxZpy','truonganhtuan.sgd.ai@gmail.com','Chuyên viên','Phòng GDPT-GDTX','admin',1,1,1,NOW(),NOW()),
('NVA','Nguyễn Văn An','nva','$2y$12$lHthAhXNqzoBf4k5ciLKK.LLrJigALM2L4aAFBlvSDyWI.mHd1G8y',NULL,'Chuyên viên','Phòng GDPT-GDTX','nguoi_xu_ly',0,1,1,NOW(),NOW()),
('LTC','Lê Thị Cúc','ltc','$2y$12$lHthAhXNqzoBf4k5ciLKK.LLrJigALM2L4aAFBlvSDyWI.mHd1G8y',NULL,'Chuyên viên','Phòng GDPT-GDTX','nguoi_xu_ly',0,1,1,NOW(),NOW()),
('PVD','Phạm Văn Dũng','pvd','$2y$12$lHthAhXNqzoBf4k5ciLKK.LLrJigALM2L4aAFBlvSDyWI.mHd1G8y',NULL,'Chuyên viên','Phòng GDPT-GDTX','nguoi_xu_ly',0,1,1,NOW(),NOW()),
('LDP','Lãnh đạo Phòng','lanhdao','$2y$12$lHthAhXNqzoBf4k5ciLKK.LLrJigALM2L4aAFBlvSDyWI.mHd1G8y',NULL,'Trưởng phòng','Phòng GDPT-GDTX','lanh_dao',1,1,1,NOW(),NOW())
ON DUPLICATE KEY UPDATE `ho_ten` = VALUES(`ho_ten`);

-- ---------------------------------------------------------------------
-- DANH MỤC TRƯỜNG (mẫu - vui lòng thay bằng danh sách thực tế)
-- ---------------------------------------------------------------------
INSERT INTO `truong` (`ma_truong`,`ma_chuan`,`ten_truong`,`ten_viet_tat`,`cap_hoc`,`dia_ban`,`thu_tu`,`trang_thai`,`ngay_tao`,`ngay_cap_nhat`) VALUES
('001','1','THPT Chuyên Lương Thế Vinh','CLTV','THPT','Biên Hòa',1,1,NOW(),NOW()),
('002','2','THPT Ngô Quyền','NQ','THPT','Biên Hòa',2,1,NOW(),NOW()),
('003','3','THPT Trấn Biên','TB','THPT','Biên Hòa',3,1,NOW(),NOW()),
('004','4','THPT Nguyễn Trãi','NT','THPT','Biên Hòa',4,1,NOW(),NOW()),
('005','5','THPT Nam Hà','NH','THPT','Biên Hòa',5,1,NOW(),NOW()),
('006','6','THPT Tam Hiệp','TH','THPT','Biên Hòa',6,1,NOW(),NOW()),
('007','7','THPT Long Khánh','LK','THPT','Long Khánh',7,1,NOW(),NOW()),
('008','8','THPT Xuân Lộc','XL','THPT','Xuân Lộc',8,1,NOW(),NOW()),
('009','9','THPT Thống Nhất A','TNA','THPT','Thống Nhất',9,1,NOW(),NOW()),
('010','10','THPT Trị An','TA','THPT','Vĩnh Cửu',10,1,NOW(),NOW()),
('011','11','THPT Nhơn Trạch','NTr','THPT','Nhơn Trạch',11,1,NOW(),NOW()),
('012','12','Trung tâm GDTX tỉnh Đồng Nai','GDTX','GDTX','Biên Hòa',12,1,NOW(),NOW())
ON DUPLICATE KEY UPDATE `ten_truong` = VALUES(`ten_truong`);

-- ---------------------------------------------------------------------
-- DANH MỤC MÃ VĂN BẢN / CÔNG VIỆC (nới lỏng - mã lạ vẫn được nhận)
-- ---------------------------------------------------------------------
INSERT INTO `van_ban` (`ma_van_ban`,`ma_chuan`,`ten_van_ban`,`loai`,`ky_bao_cao`,`bat_buoc_nop`,`pham_vi`,`tu_dong_tao`,`trang_thai`,`mo_ta`,`ngay_tao`,`ngay_cap_nhat`) VALUES
('001','1','Báo cáo sơ kết học kỳ I','bao_cao','hoc_ky',1,'tat_ca',0,1,'Báo cáo tổng kết công tác học kỳ I',NOW(),NOW()),
('002','2','Báo cáo tổng kết năm học','bao_cao','nam',1,'tat_ca',0,1,'Báo cáo tổng kết năm học',NOW(),NOW()),
('003','3','Báo cáo tháng công tác chuyên môn','bao_cao','thang',1,'tat_ca',0,1,'Báo cáo định kỳ hàng tháng',NOW(),NOW()),
('004','4','Đăng ký thi tốt nghiệp THPT','cong_viec','dot',1,'tat_ca',0,1,'Hồ sơ đăng ký dự thi',NOW(),NOW()),
('005','5','Báo cáo phổ cập giáo dục - xoá mù chữ','bao_cao','nam',1,'tat_ca',0,1,NULL,NOW(),NOW()),
('006','6','Kế hoạch giáo dục nhà trường','cong_viec','nam',1,'tat_ca',0,1,NULL,NOW(),NOW()),
('007','7','Báo cáo công tác giáo dục thường xuyên','bao_cao','quy',0,'tat_ca',0,1,NULL,NOW(),NOW()),
('999','999','Văn bản khác','khac','khong',0,'tat_ca',0,1,'Dùng cho các văn bản chưa phân loại',NOW(),NOW())
ON DUPLICATE KEY UPDATE `ten_van_ban` = VALUES(`ten_van_ban`);
