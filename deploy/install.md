# Triển khai Ubuntu 24.04 — NovaTech

Chạy các lệnh đúng máy. Mật khẩu placeholder phải thay ở máy đích; không đưa secret vào repo. Import seed chỉ một lần trên database mới. Nếu đã có dữ liệu, sao lưu trước và không import lại.

## 1. DB_Server — 10.10.100.13
```bash
sudo apt update
sudo apt install -y mariadb-server
sudo systemctl enable --now mariadb
sudo mariadb-secure-installation
sudoedit /etc/mysql/mariadb.conf.d/50-server.cnf
```
Trong phần `[mysqld]` đặt:
```ini
bind-address = 10.10.100.13
```
```bash
sudo systemctl restart mariadb
sudo ss -ltnp | grep 3306
```
Chép thư mục `db/` từ dự án đến `/opt/novatech-db/` trên DB_Server. Dùng quản trị viên import:
```bash
sudo mariadb < /opt/novatech-db/schema.sql
sudo mariadb < /opt/novatech-db/seed.sql
sudo mariadb
```
Nhập SQL trong phiên quản trị, thay placeholder bằng secret mạnh. Không dùng `-pPASSWORD` trong command line:
```sql
CREATE USER 'webapp'@'10.10.190.11' IDENTIFIED BY '<DB_PASSWORD>';
GRANT SELECT, INSERT, UPDATE, DELETE ON novatech_db.* TO 'webapp'@'10.10.190.11';
SHOW GRANTS FOR 'webapp'@'10.10.190.11';
EXIT;
```
Không cấp quyền DDL hoặc quyền toàn cục. Host giới hạn IP máy web, không dùng `%`. Bật TLS MariaDB nếu mạng thực tế không tin cậy (chưa cấu hình trong lab này).

## 2. Web_Server — 10.10.190.11
```bash
sudo apt update
sudo apt install -y nginx php8.3-fpm php8.3-mysql php8.3-ldap php8.3-mbstring php8.3-xml netcat-openbsd
sudo systemctl enable --now nginx php8.3-fpm
sudo install -d -o root -g www-data -m 0750 /var/www/novatech
```
Chép mã nguồn vào `/var/www/novatech/` (không lồng thêm thư mục). Document root chỉ là `public/`. Sau khi chép:
```bash
sudo chown -R root:www-data /var/www/novatech
sudo find /var/www/novatech -type d -exec chmod 0750 {} \;
sudo find /var/www/novatech -type f -exec chmod 0640 {} \;
sudo install -d -o root -g www-data -m 0750 /etc/novatech
sudo install -o root -g www-data -m 0640 /var/www/novatech/config.sample.php /etc/novatech/config.php
sudoedit /etc/novatech/config.php
```
Nhập secret trùng DB_Server; giữ host `10.10.100.13`, user `webapp`, name `novatech_db`. Lab HTTP giữ `secure_cookie=false`; HTTPS đổi true.
```bash
sudo install -d -o root -g www-data -m 0750 /var/log/novatech
sudo touch /var/log/novatech/php-error.log
sudo chown www-data:www-data /var/log/novatech/php-error.log
sudo chmod 0640 /var/log/novatech/php-error.log
sudoedit /etc/php/8.3/fpm/conf.d/99-novatech.ini
```
Nội dung:
```ini
display_errors=Off
log_errors=On
expose_php=Off
error_log=/var/log/novatech/php-error.log
session.use_strict_mode=1
session.use_only_cookies=1
session.gc_maxlifetime=1800
```
App cũng thực thi timeout 1800 giây ở phía server. Tạo logrotate:
```bash
sudoedit /etc/logrotate.d/novatech
```
```text
/var/log/novatech/php-error.log {
    weekly
    rotate 8
    compress
    missingok
    notifempty
    create 0640 www-data www-data
    su www-data www-data
}
```
Cấu hình Nginx:
```bash
sudo install -o root -g root -m 0644 /var/www/novatech/deploy/nginx-novatech.conf /etc/nginx/sites-available/novatech
sudo ln -s /etc/nginx/sites-available/novatech /etc/nginx/sites-enabled/novatech
```
Nếu default site hiện có chiếm IP, kiểm tra rồi vô hiệu symlink default (không làm trên máy đang phục vụ website khác):
```bash
sudo unlink /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
sudo systemctl restart php8.3-fpm
```
Nếu symlink novatech đã có thì không tạo lại.

## 3. Kiểm tra kết nối Web → DB
Trên Web_Server:
```bash
nc -zv 10.10.100.13 3306
php -m | grep -E 'PDO|pdo_mysql|mbstring'
sudo -u www-data php -r '$c=require "/etc/novatech/config.php"; $d=$c["db"]; try{$p=new PDO("mysql:host=".$d["host"].";dbname=".$d["name"].";charset=utf8mb4",$d["user"],$d["password"]); echo "DB OK\n";}catch(Throwable $e){fwrite(STDERR,"DB connection failed\n");exit(1);}'
sudo find /var/www/novatech -type f -name '*.php' -exec php -l {} \;
php /var/www/novatech/tests/self_check.php
```
Nếu nc thất bại, kiểm tra route giữa Net_DMZ và Servers, bind-address, IP source; không sửa code để bỏ xác thực hoặc mở DB cho mọi host.

## 4. Cấp mật khẩu tạm
Trên Web_Server, CLI đọc secret ẩn từ terminal:
```bash
sudo php /var/www/novatech/tools/set_password.php kt01
sudo php /var/www/novatech/tools/set_password.php hr01
sudo php /var/www/novatech/tools/set_password.php it01
sudo php /var/www/novatech/tools/set_password.php itadmin
```
Sinh hash thủ công khi cần:
```bash
php /var/www/novatech/tools/hash_password.php
```
Không lưu mật khẩu plaintext/hash output vào README. Gửi mật khẩu tạm qua kênh riêng, đăng nhập lần đầu đổi ngay.

## 5. Nghiệm thu
```bash
curl -s -D - -o /dev/null http://10.10.190.11/login
curl -I http://10.10.190.11/assets/index.css
```
Lưu ý ứng dụng chỉ GET/POST nên `curl -I /login` (HEAD) trả 405 đúng thiết kế; dùng `curl -s -D - -o /dev/null http://10.10.190.11/login` để kiểm tra 200. Kiểm tra cookie HttpOnly/SameSite=Lax và headers bảo mật. Làm toàn bộ W1–W9 trong `TESTING.md` từ máy trạm.

Log:
```bash
sudo tail -n 50 /var/log/novatech/php-error.log
sudo tail -n 50 /var/log/nginx/novatech-error.log
sudo journalctl -u php8.3-fpm -n 50 --no-pager
```
Không công bố log chứa chi tiết DB cho người dùng.

## 6. Backup / rollback
Trên DB_Server, trước thay đổi dữ liệu:
```bash
sudo sh -c 'umask 077; mariadb-dump --single-transaction novatech_db > /root/novatech-backup.sql'
```
Rollback code bằng bản phát hành trước, giữ nguyên cấu hình ngoài repo. Restore DB chỉ sau khi dừng ghi và xác nhận bản backup đúng; không tự động import seed đè dữ liệu. Chưa triển khai máy thật trong quá trình tạo mã nguồn.
