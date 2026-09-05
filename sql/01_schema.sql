-- =====================================================================
--  HỆ THỐNG PHÂN LUỒNG MAIL CÔNG VỤ
--  Phòng GDPT-GDTX - Sở GD&ĐT Đồng Nai
--  Thiết kế bởi Trương Anh Tuấn
-- ---------------------------------------------------------------------
--  Tệp: 01_schema.sql   - Cấu trúc cơ sở dữ liệu
--  Tương thích: MySQL >= 5.7 / MariaDB >= 10.3 (cPanel shared hosting)
--  Bảng mã: utf8mb4 / utf8mb4_unicode_ci
--  Múi giờ  : Asia/Ho_Chi_Minh (+07:00) - ứng dụng luôn SET time_zone
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================================
-- 1. CẤU HÌNH HỆ THỐNG (tên app, copyright, AI, Gmail, quy tắc trùng...)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `cau_hinh` (
  `khoa`           VARCHAR(100)  NOT NULL,
  `gia_tri`        MEDIUMTEXT    NULL,
  `nhom`           VARCHAR(50)   NOT NULL DEFAULT 'chung',
  `kieu`           VARCHAR(20)   NOT NULL DEFAULT 'text',  -- text|number|bool|password|textarea|select
  `nhan`           VARCHAR(255)  NULL,
  `mo_ta`          VARCHAR(500)  NULL,
  `thu_tu`         INT           NOT NULL DEFAULT 0,
  `bi_mat`         TINYINT(1)    NOT NULL DEFAULT 0,       -- 1 = che khi hiển thị
  `ngay_cap_nhat`  DATETIME      NULL,
  PRIMARY KEY (`khoa`),
  KEY `idx_cauhinh_nhom` (`nhom`, `thu_tu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- =====================================================================
-- 2. DANH MỤC TRƯỜNG  (BẮT BUỘC)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `truong` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `ma_truong`      VARCHAR(20)   NOT NULL,                 -- 001, 002...
  `ma_chuan`       VARCHAR(20)   NOT NULL,                 -- mã đã chuẩn hoá (bỏ số 0 đầu, in hoa)
  `ten_truong`     VARCHAR(255)  NOT NULL,
  `ten_viet_tat`   VARCHAR(100)  NULL,
  `cap_hoc`        VARCHAR(50)   NULL,                     -- THPT / THCS / TX / PT...
  `dia_ban`        VARCHAR(150)  NULL,                     -- huyện/thành phố
  `email`          VARCHAR(191)  NULL,
  `dien_thoai`     VARCHAR(30)   NULL,
  `nguoi_dai_dien` VARCHAR(150)  NULL,
  `thu_tu`         INT           NOT NULL DEFAULT 0,
  `trang_thai`     TINYINT(1)    NOT NULL DEFAULT 1,       -- 1 hoạt động, 0 ngưng
  `ghi_chu`        TEXT          NULL,
  `ngay_tao`       DATETIME      NULL,
  `ngay_cap_nhat`  DATETIME      NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_truong_ma` (`ma_truong`),
  KEY `idx_truong_chuan` (`ma_chuan`),
  KEY `idx_truong_email` (`email`),
  KEY `idx_truong_tt` (`trang_thai`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- =====================================================================
-- 3. DANH MỤC NGƯỜI XỬ LÝ (BẮT BUỘC) - đồng thời là tài khoản đăng nhập
-- =====================================================================
CREATE TABLE IF NOT EXISTS `nguoi_xu_ly` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ma_nguoi_xu_ly`     VARCHAR(20)  NOT NULL,              -- TAT, NVA...
  `ho_ten`             VARCHAR(150) NOT NULL,
  `ten_dang_nhap`      VARCHAR(64)  NOT NULL,
  `mat_khau`           VARCHAR(255) NOT NULL,              -- password_hash()
  `email`              VARCHAR(191) NULL,
  `dien_thoai`         VARCHAR(30)  NULL,
  `chuc_vu`            VARCHAR(150) NULL,
  `phong_ban`          VARCHAR(150) NULL,
  `vai_tro`            ENUM('admin','nguoi_xu_ly','lanh_dao') NOT NULL DEFAULT 'nguoi_xu_ly',
  `nhan_tat_ca`        TINYINT(1)   NOT NULL DEFAULT 0,    -- 1 = xem được mọi văn bản
  `trang_thai`         TINYINT(1)   NOT NULL DEFAULT 1,
  `doi_mat_khau`       TINYINT(1)   NOT NULL DEFAULT 0,    -- 1 = buộc đổi MK lần đăng nhập tới
  `lan_dang_nhap_cuoi` DATETIME     NULL,
  `ip_dang_nhap_cuoi`  VARCHAR(45)  NULL,
  `so_lan_dang_nhap`   INT          NOT NULL DEFAULT 0,
  `so_lan_that_bai`    INT          NOT NULL DEFAULT 0,
  `khoa_den`           DATETIME     NULL,
  `ghi_chu`            TEXT         NULL,
  `ngay_tao`           DATETIME     NULL,
  `ngay_cap_nhat`      DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nxl_ma` (`ma_nguoi_xu_ly`),
  UNIQUE KEY `uq_nxl_tdn` (`ten_dang_nhap`),
  KEY `idx_nxl_tt` (`trang_thai`),
  KEY `idx_nxl_vaitro` (`vai_tro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Bí danh mã người xử lý: 1 người có thể có nhiều mã viết tắt khác nhau
CREATE TABLE IF NOT EXISTS `bi_danh_nguoi_xu_ly` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_nguoi_xu_ly` INT UNSIGNED NOT NULL,
  `bi_danh`        VARCHAR(30)  NOT NULL,
  `ngay_tao`       DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bidanh` (`bi_danh`),
  KEY `fk_bidanh_nxl` (`id_nguoi_xu_ly`),
  CONSTRAINT `fk_bidanh_nxl` FOREIGN KEY (`id_nguoi_xu_ly`)
      REFERENCES `nguoi_xu_ly` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- =====================================================================
-- 4. DANH MỤC MÃ VĂN BẢN / CÔNG VIỆC  (NỚI LỎNG)
--    Mã lạ vẫn được nhận, hệ thống tự tạo bản ghi tam voi tu_dong_tao=1
-- =====================================================================
CREATE TABLE IF NOT EXISTS `van_ban` (
  `id`                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ma_van_ban`             VARCHAR(30)  NOT NULL,
  `ma_chuan`               VARCHAR(30)  NOT NULL,
  `ten_van_ban`            VARCHAR(255) NOT NULL,
  `loai`                   ENUM('bao_cao','cong_viec','khac') NOT NULL DEFAULT 'khac',
  `id_nguoi_xu_ly_mac_dinh` INT UNSIGNED NULL,             -- gợi ý người xử lý khi tên tệp thiếu mã
  `ky_bao_cao`             ENUM('khong','ngay','tuan','thang','quy','hoc_ky','nam','dot')
                                        NOT NULL DEFAULT 'khong',
  `han_nop`                DATE         NULL,
  `bat_buoc_nop`           TINYINT(1)   NOT NULL DEFAULT 0, -- 1 = đưa vào thống kê nộp/chưa nộp
  `pham_vi`                ENUM('tat_ca','chon_loc') NOT NULL DEFAULT 'tat_ca',
  `tu_dong_tao`            TINYINT(1)   NOT NULL DEFAULT 0, -- 1 = do hệ thống sinh, chờ đặt tên
  `trang_thai`             TINYINT(1)   NOT NULL DEFAULT 1,
  `mo_ta`                  TEXT         NULL,
  `lan_gap_dau`            DATETIME     NULL,
  `lan_gap_cuoi`           DATETIME     NULL,
  `so_lan_gap`             INT          NOT NULL DEFAULT 0,
  `ngay_tao`               DATETIME     NULL,
  `ngay_cap_nhat`          DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vb_ma` (`ma_van_ban`),
  KEY `idx_vb_chuan` (`ma_chuan`),
  KEY `idx_vb_tudong` (`tu_dong_tao`),
  KEY `idx_vb_bbnop` (`bat_buoc_nop`),
  KEY `fk_vb_nxl` (`id_nguoi_xu_ly_mac_dinh`),
  CONSTRAINT `fk_vb_nxl` FOREIGN KEY (`id_nguoi_xu_ly_mac_dinh`)
      REFERENCES `nguoi_xu_ly` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Phạm vi trường phải nộp (chỉ dùng khi van_ban.pham_vi = 'chon_loc')
CREATE TABLE IF NOT EXISTS `van_ban_truong` (
  `id_van_ban` INT UNSIGNED NOT NULL,
  `id_truong`  INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id_van_ban`, `id_truong`),
  KEY `fk_vbt_truong` (`id_truong`),
  CONSTRAINT `fk_vbt_vb`     FOREIGN KEY (`id_van_ban`) REFERENCES `van_ban` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vbt_truong` FOREIGN KEY (`id_truong`)  REFERENCES `truong`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- =====================================================================
-- 5. EMAIL GỐC (không xoá mail trên Gmail, chỉ lưu bản sao)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `email` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `gmail_message_id`  VARCHAR(64)   NOT NULL,
  `gmail_thread_id`   VARCHAR(64)   NULL,
  `message_id_header` VARCHAR(191)  NULL,
  `hop_thu`           VARCHAR(191)  NULL,                  -- địa chỉ hộp thư đã quét
  `tieu_de`           VARCHAR(1000) NULL,
  `tieu_de_chuan`     VARCHAR(500)  NULL,                  -- tiêu đề đã bỏ dấu, in hoa (để so trùng)
  `nguoi_gui`         VARCHAR(320)  NULL,
  `ten_nguoi_gui`     VARCHAR(255)  NULL,
  `nguoi_nhan`        TEXT          NULL,
  `ngay_gui`          DATETIME      NULL,
  `ngay_nhan`         DATETIME      NULL,
  `doan_trich`        VARCHAR(1000) NULL,
  `noi_dung_text`     MEDIUMTEXT    NULL,
  `noi_dung_html`     MEDIUMTEXT    NULL,
  `so_tep`            INT           NOT NULL DEFAULT 0,
  `tong_dung_luong`   BIGINT        NOT NULL DEFAULT 0,
  `hash_noi_dung`     CHAR(64)      NULL,                  -- SHA256(tiêu đề + người gửi + nội dung)
  `hash_tep`          CHAR(64)      NULL,                  -- SHA256(danh sách hash+size tệp)
  `hash_tong_hop`     CHAR(64)      NULL,                  -- SHA256(hash_noi_dung + hash_tep)
  `trang_thai`        ENUM('moi','da_phan_luong','cho_phan_luong','trung_lap','ban_moi','loi')
                                    NOT NULL DEFAULT 'moi',
  `id_email_goc`      BIGINT UNSIGNED NULL,                -- email trùng nội dung được phát hiện trước đó
  `phien_ban`         INT           NOT NULL DEFAULT 1,
  `ly_do_trung`       VARCHAR(500)  NULL,
  `nguon_phan_luong`  ENUM('ten_tep','tieu_de','ai','thu_cong','mac_dinh','nguoi_gui','khong_xac_dinh')
                                    NOT NULL DEFAULT 'khong_xac_dinh',
  `do_tin_cay`        DECIMAL(5,2)  NOT NULL DEFAULT 0,
  `ghi_chu_ai`        TEXT          NULL,
  `tieu_de_goc_raw`   TEXT          NULL,
  `ngay_tao`          DATETIME      NULL,
  `ngay_cap_nhat`     DATETIME      NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email_gmailid` (`gmail_message_id`),
  KEY `idx_email_hashnd` (`hash_noi_dung`),
  KEY `idx_email_hashth` (`hash_tong_hop`),
  KEY `idx_email_tt` (`trang_thai`),
  KEY `idx_email_ngay` (`ngay_gui`),
  KEY `idx_email_goc` (`id_email_goc`),
  KEY `idx_email_nguoigui` (`nguoi_gui`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- =====================================================================
-- 6. KHO TỆP (BLOB) - khử trùng lặp theo SHA-256 nội dung tệp
-- =====================================================================
CREATE TABLE IF NOT EXISTS `tep_du_lieu` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `hash_file`     CHAR(64)     NOT NULL,
  `dung_luong`    BIGINT       NOT NULL DEFAULT 0,
  `kieu_mime`     VARCHAR(150) NULL,
  `noi_dung`      LONGBLOB     NULL,
  `da_hoan_tat`   TINYINT(1)   NOT NULL DEFAULT 0,         -- 0 = đang ghi theo từng khối
  `so_tham_chieu` INT          NOT NULL DEFAULT 0,
  `ngay_tao`      DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tepdl_hash` (`hash_file`),
  KEY `idx_tepdl_hoantat` (`da_hoan_tat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- =====================================================================
-- 7. TỆP ĐÍNH KÈM
-- =====================================================================
CREATE TABLE IF NOT EXISTS `tep_dinh_kem` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_email`       BIGINT UNSIGNED NOT NULL,
  `id_tep_du_lieu` BIGINT UNSIGNED NULL,
  `ten_tep`        VARCHAR(500) NOT NULL,
  `ten_tep_chuan`  VARCHAR(500) NULL,
  `phan_mo_rong`   VARCHAR(20)  NULL,
  `kieu_mime`      VARCHAR(150) NULL,
  `dung_luong`     BIGINT       NOT NULL DEFAULT 0,
  `hash_file`      CHAR(64)     NULL,
  `ma_truong`      VARCHAR(20)  NULL,
  `ma_van_ban`     VARCHAR(30)  NULL,
  `ma_nguoi_xu_ly` VARCHAR(20)  NULL,
  `doc_duoc_ma`    TINYINT(1)   NOT NULL DEFAULT 0,
  `thu_tu`         INT          NOT NULL DEFAULT 0,
  `ngay_tao`       DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tdk_email` (`id_email`),
  KEY `idx_tdk_hash` (`hash_file`),
  KEY `idx_tdk_dl` (`id_tep_du_lieu`),
  CONSTRAINT `fk_tdk_email` FOREIGN KEY (`id_email`) REFERENCES `email` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tdk_dl`    FOREIGN KEY (`id_tep_du_lieu`) REFERENCES `tep_du_lieu` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- =====================================================================
-- 8. CÔNG VIỆC / VĂN BẢN CẦN XỬ LÝ  (đơn vị phân luồng đến người xử lý)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `cong_viec` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_email`         BIGINT UNSIGNED NOT NULL,
  `ma_ho_so`         VARCHAR(80)  NULL,                    -- 001_001_TAT
  `id_truong`        INT UNSIGNED NULL,
  `id_van_ban`       INT UNSIGNED NULL,
  `id_nguoi_xu_ly`   INT UNSIGNED NULL,
  `ma_truong`        VARCHAR(20)  NULL,
  `ma_van_ban`       VARCHAR(30)  NULL,
  `ma_nguoi_xu_ly`   VARCHAR(20)  NULL,
  `tieu_de`          VARCHAR(1000) NULL,
  `trich_yeu`        TEXT         NULL,
  `nguon_phan_luong` ENUM('ten_tep','tieu_de','ai','thu_cong','mac_dinh','nguoi_gui','khong_xac_dinh')
                                  NOT NULL DEFAULT 'khong_xac_dinh',
  `do_tin_cay`       DECIMAL(5,2) NOT NULL DEFAULT 0,
  `trang_thai`       ENUM('cho_phan_luong','cho_xu_ly','dang_xu_ly','da_xu_ly','tu_choi','trung_lap')
                                  NOT NULL DEFAULT 'cho_xu_ly',
  `muc_do`           ENUM('thuong','khan','hoa_toc') NOT NULL DEFAULT 'thuong',
  `da_xem`           TINYINT(1)   NOT NULL DEFAULT 0,
  `ngay_xem`         DATETIME     NULL,
  `ngay_nhan`        DATETIME     NULL,
  `han_xu_ly`        DATE         NULL,
  `ngay_xu_ly`       DATETIME     NULL,
  `ket_qua`          TEXT         NULL,
  `id_cong_viec_goc` BIGINT UNSIGNED NULL,
  `phien_ban`        INT          NOT NULL DEFAULT 1,
  `la_ban_moi_nhat`  TINYINT(1)   NOT NULL DEFAULT 1,
  `so_tep`           INT          NOT NULL DEFAULT 0,
  `tong_dung_luong`  BIGINT       NOT NULL DEFAULT 0,
  `ghi_chu`          TEXT         NULL,
  `id_nguoi_phan_luong` INT UNSIGNED NULL,
  `ngay_tao`         DATETIME     NULL,
  `ngay_cap_nhat`    DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cv_email` (`id_email`),
  KEY `idx_cv_nxl` (`id_nguoi_xu_ly`, `trang_thai`),
  KEY `idx_cv_truong` (`id_truong`),
  KEY `idx_cv_vb` (`id_van_ban`),
  KEY `idx_cv_tt` (`trang_thai`),
  KEY `idx_cv_ngay` (`ngay_nhan`),
  KEY `idx_cv_moinhat` (`la_ban_moi_nhat`),
  KEY `idx_cv_goc` (`id_cong_viec_goc`),
  CONSTRAINT `fk_cv_email`  FOREIGN KEY (`id_email`)       REFERENCES `email` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cv_truong` FOREIGN KEY (`id_truong`)      REFERENCES `truong` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cv_vb`     FOREIGN KEY (`id_van_ban`)     REFERENCES `van_ban` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cv_nxl`    FOREIGN KEY (`id_nguoi_xu_ly`) REFERENCES `nguoi_xu_ly` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Liên kết công việc <-> tệp đính kèm (một công việc có thể gồm nhiều tệp)
CREATE TABLE IF NOT EXISTS `cong_viec_tep` (
  `id_cong_viec`   BIGINT UNSIGNED NOT NULL,
  `id_tep_dinh_kem` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id_cong_viec`, `id_tep_dinh_kem`),
  KEY `fk_cvt_tep` (`id_tep_dinh_kem`),
  CONSTRAINT `fk_cvt_cv`  FOREIGN KEY (`id_cong_viec`)     REFERENCES `cong_viec` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cvt_tep` FOREIGN KEY (`id_tep_dinh_kem`)  REFERENCES `tep_dinh_kem` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- =====================================================================
-- 9. NHẬT KÝ HỆ THỐNG
-- =====================================================================
CREATE TABLE IF NOT EXISTS `nhat_ky` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `thoi_gian`     DATETIME     NULL,
  `muc`           ENUM('debug','info','canh_bao','loi') NOT NULL DEFAULT 'info',
  `nguon`         VARCHAR(30)  NOT NULL DEFAULT 'web',     -- web | mailrouter | api | cron
  `hanh_dong`     VARCHAR(100) NULL,
  `doi_tuong`     VARCHAR(50)  NULL,
  `id_doi_tuong`  VARCHAR(64)  NULL,
  `id_nguoi_dung` INT UNSIGNED NULL,
  `ten_nguoi_dung` VARCHAR(150) NULL,
  `dia_chi_ip`    VARCHAR(45)  NULL,
  `may_chu`       VARCHAR(100) NULL,
  `noi_dung`      TEXT         NULL,
  `du_lieu`       MEDIUMTEXT   NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nk_thoigian` (`thoi_gian`),
  KEY `idx_nk_muc` (`muc`),
  KEY `idx_nk_nguon` (`nguon`),
  KEY `idx_nk_hanhdong` (`hanh_dong`),
  KEY `idx_nk_doituong` (`doi_tuong`, `id_doi_tuong`),
  KEY `idx_nk_nguoidung` (`id_nguoi_dung`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- =====================================================================
-- 10. PHIÊN ĐỒNG BỘ MAIL
-- =====================================================================
CREATE TABLE IF NOT EXISTS `phien_dong_bo` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bat_dau`        DATETIME     NULL,
  `ket_thuc`       DATETIME     NULL,
  `may_chu`        VARCHAR(100) NULL,
  `hop_thu`        VARCHAR(191) NULL,
  `truy_van`       VARCHAR(500) NULL,
  `so_mail_quet`   INT NOT NULL DEFAULT 0,
  `so_mail_moi`    INT NOT NULL DEFAULT 0,
  `so_mail_trung`  INT NOT NULL DEFAULT 0,
  `so_mail_ban_moi` INT NOT NULL DEFAULT 0,
  `so_cong_viec`   INT NOT NULL DEFAULT 0,
  `so_tep`         INT NOT NULL DEFAULT 0,
  `so_dung_ai`     INT NOT NULL DEFAULT 0,
  `so_cho_phan_luong` INT NOT NULL DEFAULT 0,
  `so_loi`         INT NOT NULL DEFAULT 0,
  `trang_thai`     ENUM('dang_chay','hoan_tat','loi') NOT NULL DEFAULT 'dang_chay',
  `thong_diep`     TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pds_batdau` (`bat_dau`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- =====================================================================
-- 11. TẢI TỆP THEO KHỐI (chế độ API - vượt giới hạn post_max_size)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `tai_len_tam` (
  `id`          VARCHAR(40)  NOT NULL,
  `hash_file`   CHAR(64)     NOT NULL,
  `dung_luong`  BIGINT       NOT NULL DEFAULT 0,
  `da_nhan`     BIGINT       NOT NULL DEFAULT 0,
  `kieu_mime`   VARCHAR(150) NULL,
  `ten_tep`     VARCHAR(500) NULL,
  `du_lieu`     LONGBLOB     NULL,
  `ngay_tao`    DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tlt_ngay` (`ngay_tao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- =====================================================================
-- 12. PHIÊN ĐĂNG NHẬP GHI NHỚ (remember me)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `phien_ghi_nho` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_nguoi_xu_ly` INT UNSIGNED NOT NULL,
  `chon`           CHAR(32)     NOT NULL,
  `bam`            CHAR(64)     NOT NULL,
  `het_han`        DATETIME     NOT NULL,
  `dia_chi_ip`     VARCHAR(45)  NULL,
  `ngay_tao`       DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pgn_chon` (`chon`),
  KEY `fk_pgn_nxl` (`id_nguoi_xu_ly`),
  CONSTRAINT `fk_pgn_nxl` FOREIGN KEY (`id_nguoi_xu_ly`)
      REFERENCES `nguoi_xu_ly` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

SET FOREIGN_KEY_CHECKS = 1;
