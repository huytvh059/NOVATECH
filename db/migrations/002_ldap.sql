-- Quản trị DB chạy một lần, sau backup; webapp không có quyền ALTER.
USE novatech_db;
ALTER TABLE users
 ADD COLUMN auth_source ENUM('db','ldap') NOT NULL DEFAULT 'db',
 ADD COLUMN ad_guid CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
 ADD COLUMN ad_synced_at DATETIME NULL,
 ADD UNIQUE KEY uq_users_ad_guid(ad_guid);
