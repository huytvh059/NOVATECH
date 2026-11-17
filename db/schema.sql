-- Quản trị DB chạy, không dùng webapp. Không xóa dữ liệu cũ.
CREATE DATABASE IF NOT EXISTS novatech_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE novatech_db;
CREATE TABLE departments (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ten_phong_ban VARCHAR(100) NOT NULL, nhom_ad VARCHAR(100) NOT NULL) ENGINE=InnoDB;
CREATE TABLE users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, username VARCHAR(64) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL,
 ho_ten VARCHAR(150) NOT NULL, email VARCHAR(190) NOT NULL, so_may_le VARCHAR(20) NOT NULL DEFAULT '', phong_ban_id INT UNSIGNED NOT NULL,
 role ENUM('employee','accounting','hr','it_admin') NOT NULL DEFAULT 'employee', trang_thai ENUM('active','locked') NOT NULL DEFAULT 'active',
 so_lan_sai INT UNSIGNED NOT NULL DEFAULT 0, khoa_den DATETIME NULL, must_change_password TINYINT NOT NULL DEFAULT 1,
 auth_source ENUM('db','ldap') NOT NULL DEFAULT 'db', ad_guid CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE, ad_synced_at DATETIME NULL,
 session_version INT UNSIGNED NOT NULL DEFAULT 1, ngay_tao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(phong_ban_id) REFERENCES departments(id), INDEX(role,trang_thai), INDEX(phong_ban_id)
) ENGINE=InnoDB;
CREATE TABLE assets (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ten_may VARCHAR(100) NOT NULL UNIQUE, vai_tro VARCHAR(190) NOT NULL,
 mang VARCHAR(100) NOT NULL, fixed_ip VARCHAR(45) NOT NULL, floating_ip VARCHAR(45) NULL, he_dieu_hanh VARCHAR(100) NOT NULL,
 tinh_trang ENUM('active','maintenance','inactive') NOT NULL DEFAULT 'active', INDEX(mang)
) ENGINE=InnoDB;
CREATE TABLE announcements (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tieu_de VARCHAR(200) NOT NULL, noi_dung TEXT NOT NULL,
 pham_vi ENUM('company','department') NOT NULL DEFAULT 'company', phong_ban_id INT UNSIGNED NULL, tao_boi INT UNSIGNED NOT NULL,
 ngay_tao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(phong_ban_id) REFERENCES departments(id), FOREIGN KEY(tao_boi) REFERENCES users(id),
 CHECK ((pham_vi='company' AND phong_ban_id IS NULL) OR (pham_vi='department' AND phong_ban_id IS NOT NULL)), INDEX(pham_vi,phong_ban_id,ngay_tao)
) ENGINE=InnoDB;
CREATE TABLE tickets (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tieu_de VARCHAR(200) NOT NULL, mo_ta TEXT NOT NULL,
 uu_tien ENUM('low','medium','high') NOT NULL DEFAULT 'medium', trang_thai ENUM('new','in_progress','completed') NOT NULL DEFAULT 'new',
 nguoi_tao INT UNSIGNED NOT NULL, nguoi_xu_ly INT UNSIGNED NULL, asset_id INT UNSIGNED NULL,
 ngay_tao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ngay_cap_nhat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ngay_dong DATETIME NULL,
 FOREIGN KEY(nguoi_tao) REFERENCES users(id), FOREIGN KEY(nguoi_xu_ly) REFERENCES users(id), FOREIGN KEY(asset_id) REFERENCES assets(id) ON DELETE SET NULL,
 INDEX(nguoi_tao,trang_thai), INDEX(trang_thai,ngay_cap_nhat)
) ENGINE=InnoDB;
CREATE TABLE ticket_comments (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ticket_id INT UNSIGNED NOT NULL, nguoi_gui INT UNSIGNED NOT NULL,
 noi_dung TEXT NOT NULL, ngay_gui DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(ticket_id) REFERENCES tickets(id), FOREIGN KEY(nguoi_gui) REFERENCES users(id), INDEX(ticket_id,ngay_gui)
) ENGINE=InnoDB;
CREATE TABLE audit_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NULL, username VARCHAR(64) NOT NULL,
 hanh_dong VARCHAR(64) NOT NULL, doi_tuong VARCHAR(190) NOT NULL, ip_nguon VARCHAR(45) NOT NULL, ket_qua VARCHAR(30) NOT NULL,
 thoi_gian DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(user_id) REFERENCES users(id),
 INDEX(username,thoi_gian), INDEX(hanh_dong,thoi_gian), INDEX(thoi_gian)
) ENGINE=InnoDB;
CREATE TABLE login_attempts (
 attempt_key CHAR(64) CHARACTER SET ascii PRIMARY KEY, failures INT UNSIGNED NOT NULL DEFAULT 0,
 locked_until DATETIME NULL, last_attempt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(last_attempt)
) ENGINE=InnoDB;
