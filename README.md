# NovaTech Intranet Portal — Giai đoạn 1

PHP 8.3 thuần · PDO · MariaDB · Nginx · CSS/JavaScript cục bộ. Không Composer, Node, CDN.

## Chức năng
- Đăng nhập DB, phiên timeout 30 phút, khóa username/IP 5 phút sau 5 lần sai.
- Đổi mật khẩu bắt buộc lần đầu; khóa/reset tài khoản vô hiệu hóa các phiên cũ.
- Dashboard theo vai trò; thông báo toàn công ty hoặc theo phòng ban.
- Danh bạ tìm tên/phòng ban/email/máy lẻ; chỉ HR được sửa.
- Helpdesk: tạo ticket, kiểm tra sở hữu, IT gán xử lý, chuyển trạng thái tuần tự, bình luận.
- IT CRUD tài sản, quản lý tài khoản, đọc/lọc audit log.

## Cấu trúc
- `public/`: document root, front controller, assets tự chứa.
- `src/Auth/`: `AuthProvider`, `DbAuthProvider`. Giai đoạn 2 có thể bổ sung LDAP tại đây.
- `src/pages/`, `templates/`: xử lý server và view dùng layout chung.
- `db/`: schema, seed theo IP Plan. Không chạy DDL bằng tài khoản webapp.
- `tools/`: sinh hash và cấp mật khẩu qua stdin, không nhận mật khẩu trong argument.
- `deploy/install.md`: triển khai đầy đủ trên hai máy Ubuntu.

## Bắt đầu
1. Làm theo `deploy/install.md`: import schema/seed bằng quản trị DB, tạo webapp quyền DML.
2. Sao chép `config.sample.php` thành `/etc/novatech/config.php`, nhập secret ngoài repo.
3. Cấp mật khẩu tạm cho tài khoản mẫu:
   ```bash
   sudo php /var/www/novatech/tools/set_password.php kt01
   sudo php /var/www/novatech/tools/set_password.php hr01
   sudo php /var/www/novatech/tools/set_password.php it01
   sudo php /var/www/novatech/tools/set_password.php itadmin
   ```
4. Truy cập `http://10.10.190.11`, đăng nhập rồi đổi mật khẩu tạm.

| Tài khoản | Vai trò | Phòng ban | Mật khẩu |
|---|---|---|---|
| kt01 | accounting | Kế toán | `<MẬT_KHẨU_TẠM_TỰ_CẤP>` |
| hr01 | hr | Nhân sự | `<MẬT_KHẨU_TẠM_TỰ_CẤP>` |
| it01 | employee | IT | `<MẬT_KHẨU_TẠM_TỰ_CẤP>` |
| itadmin | it_admin | IT | `<MẬT_KHẨU_TẠM_TỰ_CẤP>` |

Seed có hash bcrypt ngẫu nhiên, không có mật khẩu được phát hành. Không đăng nhập được trước khi cấp mật khẩu. Luôn dùng mật khẩu riêng cho từng người.

## Sinh hash thủ công
```bash
php tools/hash_password.php
```
Nhập mật khẩu khi được hỏi (không echo). Chỉ đầu ra hash được in, không in plaintext. Có thể dùng hash trong thao tác UPDATE thủ công bằng tài khoản quản trị; khuyến nghị `set_password.php` để cập nhật cờ đổi mật khẩu và ghi audit.

## Chạy development
Cần PHP 8.3, `pdo_mysql`, `mbstring`, DB MariaDB đã import dữ liệu. Không có chế độ dữ liệu giả/SQLite thay thế production.
```bash
export NOVATECH_CONFIG=/etc/novatech/config.php
php -S 127.0.0.1:8080 -t public public/router.php
```
`NOVATECH_CONFIG` chỉ cho phép đổi đường dẫn cấu hình ngoài webroot khi phát triển; mặc định production giữ `/etc/novatech/config.php`. Không đặt cấu hình trong workspace. PHP development server không dành cho production.

## Kiểm tra
```bash
find . -type f -name '*.php' -exec php -l {} \;
php tests/self_check.php
```
Xem `TESTING.md` cho W1–W9 và các ca bảo mật. `tests/http_check.py` chạy bằng Python stdlib trên DB lab riêng; không dùng với dữ liệu thật.

## Giả định / giới hạn
- Đúng ma trận: IT không được sửa danh bạ; HR được sửa thông tin liên hệ/phòng ban, không được đổi role/tài khoản.
- HR và IT quản lý thông báo qua ID; danh sách xem chỉ hiện phạm vi dành cho mình. Không upload.
- Nhân viên đọc lịch sử ticket của mình; chỉ IT thêm bình luận/xử lý theo FR06.
- Chỉ IT đang hoạt động được gán xử lý; trạng thái chỉ Mới → Đang xử lý → Hoàn thành.
- Tài sản xóa sẽ bỏ liên kết ticket bằng FK `SET NULL`; ticket và audit không có chức năng xóa.
- Danh sách giới hạn 100 thông báo, 200 bản ghi/module, dashboard 4 thông báo/5 ticket; chưa phân trang (ponytail: lab nhỏ; thêm phân trang server khi dữ liệu tăng).
- `so_lan_sai/khoa_den` trên users chỉ hiển thị trạng thái lần thất bại gần nhất; thực thi khóa bằng `login_attempts` theo cặp username/IP.
- Dùng thời gian Asia/Ho_Chi_Minh, DB session +07:00.
- Font hệ thống hiện đại, toàn bộ tài nguyên offline; không phụ thuộc Google Fonts/CDN.
- Chưa LDAP/AD, email, upload, kế toán nghiệp vụ, MFA, khôi phục mật khẩu tự động.
- Cần chính sách lưu trữ/backup audit; quản trị DB có thể xóa login_attempts hết hạn theo lịch khi quy mô tăng.

## Bảo mật vận hành
HTTP lab không mã hóa mật khẩu/cookie trên đường truyền. Chỉ dùng trong mạng lab tin cậy. Khi vận hành thật phải cấu hình HTTPS, bật `secure_cookie=true`, giới hạn mạng truy cập DB, backup và giám sát. Header CSP không thay thế TLS hoặc kiểm soát mạng.

Webapp chỉ SELECT/INSERT/UPDATE/DELETE trên novatech_db, không CREATE/ALTER/DROP. App không thể bảo vệ DB khỏi người đã có credentials/OS root. Không log mật khẩu hay CSRF token. Sao lưu DB trước mọi thay đổi schema/seed.

## Active Directory / LDAP (lab)
Đã có provider LDAP, đồng bộ objectGUID và quyền nhóm AD, refresh phiên, admin DB cứu hộ. Mặc định vẫn `auth.provider=db` để không gián đoạn local.

Hướng dẫn chi tiết: `deploy/ldap.md`. DB cũ phải import `db/migrations/002_ldap.sql` một lần bằng quản trị; cài mới dùng schema.sql bản mới, không import migration lần nữa. LDAP thường chỉ khi cấu hình rõ `allow_insecure=true`; không dùng production.

Tài khoản AD quản lý trên DC, không reset password/role/lock qua website; không lưu mật khẩu AD, không tự fallback DB. Các giới hạn “chưa LDAP/AD” ở mô tả Giai đoạn 1 được thay thế bởi phần này. Chưa xác minh end-to-end với DC01 thật.
