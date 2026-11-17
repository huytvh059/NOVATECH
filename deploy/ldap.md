# Kết nối Active Directory bằng LDAP thường — chỉ lab

> LDAP `389` không TLS truyền mật khẩu service và nhân viên không mã hóa. Chỉ dùng tài khoản/mật khẩu lab riêng trên mạng cô lập. Không dùng Domain Admin. HTTPS website không mã hóa đoạn Web_Server → DC01. Không coi đây là cấu hình production.

## 1. Chuẩn bị DC01
- Domain `ad.novatech.test`, IP `10.10.100.11`.
- Tạo tài khoản dịch vụ thường `svc_portal`, chỉ quyền đọc trong OU nhân viên; không thêm nhóm quản trị.
- Tạo/kiểm tra các nhóm security `GG_IT`, `GG_HR`, `GG_KeToan`.
- Gán tài khoản AD vào nhóm phù hợp; `it01` không thuộc GG_IT thì vẫn là employee.
- Nhân viên không thuộc ba nhóm cần thuộc OU được phép và có thuộc tính `department` map đúng phòng ban.
- Không dùng GG_IT làm primary group: phép kiểm tra memberOf không tính primary group.
- Chỉ dùng tài khoản đã đổi mật khẩu lần đầu; website không đổi mật khẩu AD.

Trên PowerShell DC01 (đọc, không sửa cấu hình):
```powershell
Import-Module ActiveDirectory
Get-ADDomain | Select-Object DNSRoot,NetBIOSName,DistinguishedName
Get-ADUser svc_portal | Select-Object DistinguishedName,Enabled
Get-ADGroup GG_IT | Select-Object DistinguishedName
Get-ADGroup GG_HR | Select-Object DistinguishedName
Get-ADGroup GG_KeToan | Select-Object DistinguishedName
Get-ADUser it01 -Properties department,memberOf | Select-Object SamAccountName,department,memberOf
```
Dùng DN thật trong kết quả, không đoán `OU=Groups`. Nên đặt base_dn là OU nhân viên; nếu nhân viên nằm nhiều OU, dùng OU cha phù hợp. Root domain cho phép mọi user account trong domain có phòng ban hợp lệ nên cần cân nhắc.

## 2. Nâng schema
Nếu DB đã chạy Giai đoạn 1: backup rồi chạy **một lần trên DB_Server bằng quản trị DB**:
```bash
sudo sh -c 'umask 077; mariadb-dump --single-transaction novatech_db > /root/novatech-before-ldap.sql'
sudo mariadb < /opt/novatech-db/migrations/002_ldap.sql
```
Chép file migration từ phiên bản mới đến đường dẫn trên trước khi chạy. Không chạy bằng webapp.

Nếu cài mới: import schema.sql/seed.sql bản mới, **không chạy migration lần nữa** vì schema mới đã có cột AD.

## 3. Web_Server
```bash
sudo apt install -y php8.3-ldap ldap-utils netcat-openbsd
nc -zv 10.10.100.11 389
php -m | grep ldap
sudoedit /etc/novatech/config.php
```
Giữ nguyên cấu hình DB/secret hiện có. Bổ sung khối `auth` từ `config.sample.php`, sửa:
```php
'provider' => 'ldap',
'local_admins' => ['itadmin'],
```
Trong khối `ldap`:
```php
'uri' => 'ldap://10.10.100.11:389',
'start_tls' => false,
'allow_insecure' => true, // Chấp nhận rủi ro plain LDAP chỉ cho lab.
'domain' => 'ad.novatech.test',
'netbios' => 'NOVATECH', // Thay theo NetBIOSName thật.
'base_dn' => '<DN_OU_NHAN_VIEN>',
'bind_dn' => '<DN_THAT_CUA_SVC_PORTAL>',
'bind_password' => '<MAT_KHAU_LAB_SVC_PORTAL>',
```
Thay ba groups DN bằng kết quả Get-ADGroup. Không copy cả file mẫu đè secret DB. Cấu hình chỉ ở `/etc/novatech/config.php`, root:www-data 0640:
```bash
sudo chown root:www-data /etc/novatech/config.php
sudo chmod 0640 /etc/novatech/config.php
sudo php -l /etc/novatech/config.php
sudo systemctl restart php8.3-fpm
```
Không gửi mật khẩu bind trong chat hoặc commit Git.

## 4. Kiểm tra LDAP độc lập
Trên Web_Server (thay DN thực tế), lệnh hỏi mật khẩu qua `-W`, không ghi plaintext trên command line:
```bash
ldapsearch -x -H ldap://10.10.100.11:389 -D '<DN_THAT_CUA_SVC_PORTAL>' -W -b '<DN_OU_NHAN_VIEN>' '(&(objectClass=user)(sAMAccountName=it01))' sAMAccountName displayName department memberOf objectGUID
```
Lệnh này cũng truyền mật khẩu không mã hóa trên mạng. Nếu AD báo `Strong(er) authentication required`, DC đang yêu cầu LDAP signing/TLS. **Không hạ chính sách bảo mật domain để ép chạy**; chuyển StartTLS/LDAPS với CA tin cậy.

## 5. Đăng nhập và quyền
- Dùng `username`, `NOVATECH\username`, hoặc `username@ad.novatech.test`.
- Ưu tiên nhóm: GG_IT → it_admin, GG_HR → hr, GG_KeToan → accounting, còn lại employee.
- Nhóm map phòng ban tương ứng; employee dùng department_map, không có mapping thì từ chối.
- Hỗ trợ nhóm lồng nhau trong AD qua matching-rule-in-chain, không chỉ so tên CN.
- Lần đầu tự tạo bản ghi liên kết objectGUID, giữ ID khi đổi username AD.
- Không lưu mật khẩu AD. Tài khoản AD bị chặn xác thực bằng mật khẩu DB.
- Website chỉ đọc dữ liệu tài khoản AD; sửa user/nhóm/password/lock trên DC01.
- Phiên kiểm tra AD khi request sau mỗi 60 giây (cấu hình 10–300 giây). Khi role đổi, session_version tăng và yêu cầu đăng nhập lại. Khóa/xóa user AD thu hồi phiên ở lần kiểm tra tiếp theo.
- DC lỗi: đăng nhập AD/refresh trả lỗi thân thiện, không fallback DB tự động; phiên đã xác minh tiếp tục tối đa đến kỳ kiểm tra kế tiếp.
- Username ngắn `itadmin` trong local_admins được dành riêng cho admin DB cứu hộ. AD nên dùng username quản trị khác, ví dụ `ad_itadmin`, để không trùng.

### Trùng username seed
Nếu AD có `kt01`, `hr01`, `it01` giống tài khoản seed DB, website **không tự liên kết** để tránh chiếm tài khoản. Trước khi bật AD, quản trị DB đổi tên tài khoản demo DB, vẫn giữ ID/ticket:
```sql
USE novatech_db;
UPDATE users SET username='demo_kt01',session_version=session_version+1 WHERE username='kt01' AND auth_source='db';
UPDATE users SET username='demo_hr01',session_version=session_version+1 WHERE username='hr01' AND auth_source='db';
UPDATE users SET username='demo_it01',session_version=session_version+1 WHERE username='it01' AND auth_source='db';
```
Đây là tài khoản riêng, lịch sử demo không tự chuyển sở hữu cho AD. Không đổi tên tài khoản nhân viên thật bằng các lệnh demo. Giữ `itadmin` local, cấp mật khẩu cứu hộ riêng mạnh. Trong LDAP mode các user DB khác không được đăng nhập dù có mật khẩu hợp lệ.

## 6. Checklist nghiệm thu DC thật
1. Đăng nhập ba nhóm, kiểm tra menu/quyền server và audit.
2. Sai mật khẩu 5 lần cùng username/IP, xác minh khóa 5 phút của app; AD còn có chính sách khóa riêng.
3. Username/mật khẩu rỗng, LDAP injection bị từ chối; không bind anonymous.
4. Đổi nhóm AD, đợi hơn 60 giây rồi tải trang: phiên cũ bị thu hồi, login lại có quyền mới.
5. Disable/xóa tài khoản AD: tải lại sau chu kỳ kiểm tra phải mất quyền.
6. Đổi username AD: objectGUID/users.id không đổi, ticket cũ còn nguyên.
7. Dừng kết nối DC trong lab: lỗi thân thiện, không lộ secret, local cứu hộ vẫn đăng nhập được.
8. HR/IT không thể POST đổi role/password/danh bạ của tài khoản AD.

```bash
php tests/self_check.php
php tests/ldap_check.php
```
Kiểm thử thuần/mapping không chứng minh kết nối DC thật. Môi trường tạo code chưa có kết nối DC01 nên bước 1–8 phải chạy tại lab.

## 7. Rollback
Đổi auth.provider về `db`, đăng nhập admin local; tài khoản AD không được xác thực local. Giữ cột migration, không DROP khi rollback code. Nếu quay lại bản cũ chưa hiểu auth_source, chỉ dùng DB backup/bản phát hành tương thích; không cho bản cũ xử lý tài khoản AD.

## 8. Nâng cấp bảo mật sau lab
Chuyển `ldap://dc01.ad.novatech.test:389`, start_tls=true, allow_insecure=false hoặc LDAPS 636; cài CA tin cậy trên Ubuntu, giữ kiểm tra chứng chỉ. Không dùng LDAPTLS_REQCERT=never. Bật HTTPS website, secure_cookie=true.
