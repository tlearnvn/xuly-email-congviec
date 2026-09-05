-- =====================================================================
--  HỆ THỐNG PHÂN LUỒNG MAIL CÔNG VỤ
--  Thiết kế bởi Trương Anh Tuấn
-- ---------------------------------------------------------------------
--  NÂNG CẤP cơ sở dữ liệu đã cài từ phiên bản trước.
--  Chạy được nhiều lần, không làm mất dữ liệu cũ.
--
--  Cách chạy: mở https://<tên-miền>/nang-cap.php (khuyên dùng - tự bỏ
--  qua phần đã có), hoặc nạp tệp này bằng phpMyAdmin.
--
--  Lưu ý: MySQL 5.7/8.0 không có "ADD COLUMN IF NOT EXISTS" nên phải
--  hỏi information_schema rồi dựng câu lệnh động.
-- =====================================================================

-- ---------------------------------------------------------------------
--  1.2.0 - email.tu_spam: đánh dấu thư vớt được từ hộp Thư rác
-- ---------------------------------------------------------------------
SET @co = (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email' AND COLUMN_NAME = 'tu_spam');
SET @sql = IF(@co = 0,
  'ALTER TABLE `email` ADD COLUMN `tu_spam` TINYINT(1) NOT NULL DEFAULT 0 AFTER `ghi_chu_ai`',
  'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @co = (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email' AND INDEX_NAME = 'idx_email_spam');
SET @sql = IF(@co = 0,
  'ALTER TABLE `email` ADD KEY `idx_email_spam` (`tu_spam`)',
  'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------
--  1.2.0 - khoá cấu hình mới. INSERT IGNORE nên chạy lại không sao,
--  và không ghi đè giá trị quản trị đã tự chỉnh.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `cau_hinh` (`khoa`,`gia_tri`,`nhom`,`kieu`,`nhan`,`mo_ta`,`thu_tu`,`bi_mat`,`ngay_cap_nhat`)
VALUES ('gmail.quet_spam','1','gmail','bool','Quét cả hộp Thư rác (Spam)',
        'Google hay xếp nhầm báo cáo của trường vào Thư rác; tắt đi là bỏ sót',6,0,NOW());
