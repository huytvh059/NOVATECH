-- Import une seule fois sur base neuve. Hash aléatoire sans mot de passe distribué.
USE novatech_db;
START TRANSACTION;
INSERT INTO departments(id,ten_phong_ban,nhom_ad) VALUES (1,'Kế toán','GG_KeToan'),(2,'Nhân sự','GG_HR'),(3,'IT','GG_IT');
-- Bắt buộc cấp mật khẩu tạm bằng tools/set_password.php, đổi khi đăng nhập đầu tiên.
INSERT INTO users(id,username,password_hash,ho_ten,email,so_may_le,phong_ban_id,role) VALUES
(1,'kt01','$2y$10$/Cq0zlpsy5Xmyx61YkGhu.Ruhf4OO116gDWKtUvs.VhScVuFEKwFq','Nguyễn Minh Anh','minhanh@novatech.test','101',1,'accounting'),
(2,'hr01','$2y$10$/Cq0zlpsy5Xmyx61YkGhu.Ruhf4OO116gDWKtUvs.VhScVuFEKwFq','Trần Thu Hà','thuha@novatech.test','201',2,'hr'),
(3,'it01','$2y$10$/Cq0zlpsy5Xmyx61YkGhu.Ruhf4OO116gDWKtUvs.VhScVuFEKwFq','Lê Hoàng Nam','hoangnam@novatech.test','301',3,'employee'),
(4,'itadmin','$2y$10$/Cq0zlpsy5Xmyx61YkGhu.Ruhf4OO116gDWKtUvs.VhScVuFEKwFq','Phạm Quang Huy','quanghuy@novatech.test','300',3,'it_admin');
INSERT INTO assets(id,ten_may,vai_tro,mang,fixed_ip,floating_ip,he_dieu_hanh,tinh_trang) VALUES
(1,'DC01','AD DS và DNS','Net_LAN (Servers)','10.10.100.11','172.20.1.2','Windows Server 2019','active'),
(2,'FS01','File Server','Net_LAN (Servers)','10.10.100.12','172.20.1.150','Windows Server 2019','active'),
(3,'DB_Server','Database Server','Net_LAN (Servers)','10.10.100.13','172.20.1.219','Ubuntu Noble','active'),
(4,'Web_Server','Web Server','Net_DMZ','10.10.190.11','172.20.1.115','Ubuntu Noble','active'),
(5,'Mail_Server','Mail Server (tạm hoãn)','Net_DMZ','10.10.190.12','172.20.1.25','Ubuntu Noble','inactive'),
(6,'CLIENT_IT01','Máy trạm IT','Net_LAN (IT)','10.10.110.184',NULL,'Win10','active'),
(7,'CLIENT_IT02','Máy trạm IT','Net_LAN (IT)','10.10.110.213','172.20.1.41','Win10','active'),
(8,'CLIENT_HR01','Máy trạm Nhân sự','Net_LAN (HR)','10.10.120.208',NULL,'Win10','active'),
(9,'CLIENT_KETOAN01','Máy trạm Kế toán','Net_LAN (Kế toán)','10.10.130.204',NULL,'Win10','active');
INSERT INTO announcements(tieu_de,noi_dung,pham_vi,phong_ban_id,tao_boi,ngay_tao) VALUES
('Chào mừng đến với NovaTech Portal','Không gian làm việc nội bộ đã sẵn sàng. Tra cứu danh bạ, đọc thông báo và gửi yêu cầu hỗ trợ IT ngay trên Portal.','company',NULL,2,DATE_SUB(NOW(),INTERVAL 2 DAY)),
('Hướng dẫn gửi yêu cầu hỗ trợ kỹ thuật','Mô tả rõ sự cố, chọn mức ưu tiên phù hợp và máy liên quan. Bộ phận IT sẽ tiếp nhận và phản hồi trong lịch sử yêu cầu.','company',NULL,4,DATE_SUB(NOW(),INTERVAL 1 DAY)),
('Nhắc lịch cập nhật thông tin nhân sự','Vui lòng kiểm tra email và số máy lẻ trong danh bạ. Liên hệ Nhân sự để điều chỉnh khi có thay đổi.','company',NULL,2,NOW()),
('Rà soát hạ tầng mạng nội bộ','Đội IT kiểm tra danh mục máy chủ, trạng thái sao lưu và phân vùng mạng theo IP Plan.','department',3,4,NOW());
INSERT INTO tickets(id,tieu_de,mo_ta,uu_tien,trang_thai,nguoi_tao,nguoi_xu_ly,asset_id,ngay_dong) VALUES
(1,'Không truy cập được thư mục kế toán','Máy trạm không mở được thư mục chia sẻ trên FS01. Nhờ IT kiểm tra quyền truy cập.','high','new',1,NULL,9,NULL),
(2,'Cần kiểm tra kết nối máy trạm HR','Kết nối nội bộ chập chờn khi truy cập tài liệu nhân sự.','medium','in_progress',2,4,8,NULL),
(3,'Hỗ trợ cấu hình máy trạm IT','Cần kiểm tra cấu hình DNS trên máy trạm IT01.','low','completed',3,4,6,NOW()),
(4,'Rà soát dịch vụ web nội bộ','Kiểm tra trạng thái dịch vụ Nginx và PHP-FPM trên Web_Server.','medium','new',4,NULL,4,NULL);
INSERT INTO ticket_comments(ticket_id,nguoi_gui,noi_dung) VALUES
(2,4,'Đã tiếp nhận. Đang kiểm tra kết nối mạng máy trạm HR.'),
(3,4,'Đã kiểm tra DNS nội bộ. Máy trạm truy cập Portal bình thường.');
COMMIT;