# Kiểm thử NovaTech

## Điều kiện
DB lab riêng đã import schema/seed, webapp quyền DML; cấp mật khẩu tạm riêng cho bốn tài khoản. Sau đổi mật khẩu lần đầu mới kiểm tra các module. Không dừng MariaDB production để làm W7.

| ID | Bước thực hiện | Kết quả mong đợi |
|---|---|---|
| W1 | Mở http://10.10.190.11 từ máy trạm | Chuyển tới /login, form tiếng Việt, CSS cục bộ |
| W2 | Đăng nhập lần lượt kt01/hr01/it01/itadmin; đổi mật khẩu đầu tiên | Menu đúng vai trò; chỉ IT có tài sản/users/audit; HR sửa danh bạ, IT không được sửa |
| W3 | kt01 mở /assets | HTTP 403 tiếng Việt; audit forbidden có user/IP/đối tượng |
| W4 | Nhân viên tạo ticket gồm tiêu đề, mô tả, ưu tiên, máy tùy chọn | Ticket nằm trên DB_Server với đúng nguoi_tao, trạng thái new; audit ticket_create |
| W5 | IT mở ticket mới, gán IT, chuyển in_progress rồi completed | Nhân viên đọc được cập nhật; ngay_dong có giá trị, lịch sử chuyển trạng thái được lưu |
| W6 | kt01 đổi id trên /tickets/view?id=… sang ticket HR | HTTP 403, không lộ tiêu đề/mô tả/bình luận; audit denied |
| W7 | Trên DB lab: sudo systemctl stop mariadb; tải dashboard đang đăng nhập; sau đó sudo systemctl start mariadb | HTTP 503 thân thiện, không lộ IP/username/password/stack trace; lỗi nằm trong log server |
| W8 | Sai mật khẩu cùng username/IP 5 lần; lần 6 nhập đúng; thử lại sau 5 phút | Bị chặn trong thời gian khóa; thành công sau hết khóa; audit login_failed/locked |
| W9 | Tìm danh bạ với `' OR 1=1 --`; tạo ticket `<script>alert(1)</script>` | SQL không bị thay đổi; XSS hiển thị dạng văn bản, không thực thi |
| S1 | POST không có CSRF hoặc token giả tới mọi module | HTTP 419; không mutation |
| S2 | GET /logout, GET /assets?action=delete&id=1 | Logout trả 405; GET không xóa tài sản |
| S3 | Nhân viên POST trực tiếp sửa asset/user/announcement/directory/ticket | 403; không thay đổi DB |
| S4 | IT POST sửa danh bạ | 403 đúng ma trận |
| S5 | HR đăng thông báo chỉ phòng HR; kt01/it01 xem | Không xuất hiện trong danh sách/dashboard của phòng khác |
| S6 | IT tự khóa hoặc tự hạ quyền, hạ quyền admin active cuối cùng | HTTP 422; vẫn có admin active |
| S7 | Khóa/reset tài khoản đang có phiên trình duyệt khác | Request tiếp theo bị yêu cầu đăng nhập lại |
| S8 | Giữ phiên quá 30 phút không hoạt động | Request tiếp theo về /login |
| S9 | IT chuyển new thẳng completed hoặc gán employee xử lý | HTTP 422; dữ liệu không thay đổi |
| S10 | Mở trên màn hình 390px; dùng Tab/Enter/Escape | Không tràn viewport; menu mở/đóng; focus rõ; form có label |
| S11 | Dùng webapp thử CREATE TABLE trong phiên DB riêng | Access denied, quyền chỉ SELECT/INSERT/UPDATE/DELETE |
| S12 | Xóa asset được liên kết ticket | Ticket còn nguyên, asset_id NULL, audit ghi xóa |
| S13 | Thử id mảng, giá trị enum ngoài danh sách, IP/email sai, mật khẩu quá 72 byte | HTTP 422, không đổi DB |

## Tự động
```bash
find . -name '*.php' -type f -exec php -l {} \;
php tests/self_check.php
```
`tests/http_check.py` yêu cầu `NOVATECH_TEST_URL`, `NOVATECH_TEST_PASSWORD` và bốn tài khoản đã đổi mật khẩu dùng chung **chỉ trên DB lab riêng**. Mật khẩu truyền qua environment lúc chạy, không lưu trong repo. Script tạo dữ liệu kiểm thử, có thể thay đổi trạng thái ticket và ghi audit; không chạy production.

## Trạng thái xác minh
- PHP 8.3: lint 34 file đạt tại thời điểm tạo mã.
- Self-check: 20 kiểm tra đạt.
- Kết quả HTTP/DB được cập nhật sau vòng kiểm thử tích hợp.
- Nginx/PHP-FPM, route OpenStack và W1–W9 trên IP lab thật cần nghiệm thu tại hai máy đích.

## LDAP bổ sung
```bash
php tests/ldap_check.php
```
Đã kiểm tra thuần: normalizer username, cờ cho phép LDAP thường, URI, trạng thái khóa/hết hạn AD, mapping phòng ban, LDAP filter escape, GUID roundtrip. Chưa kết nối DC01 thật; checklist AD end-to-end nằm tại `deploy/ldap.md`.
