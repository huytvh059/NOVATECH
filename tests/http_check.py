"""HTTP integration check, Python stdlib; run only against a disposable lab DB."""
import http.cookiejar
import os
import re
import urllib.error
import urllib.parse
import urllib.request

BASE = os.environ['NOVATECH_TEST_URL'].rstrip('/')
PASSWORD = os.environ['NOVATECH_TEST_PASSWORD']
COUNT = 0


def check(ok, name):
    global COUNT
    if not ok:
        raise AssertionError(name)
    COUNT += 1
    print('PASS:', name)


class Client:
    def __init__(self):
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.token = ''

    def request(self, path, data=None):
        body = None if data is None else urllib.parse.urlencode(data).encode()
        try:
            response = self.opener.open(BASE + path, body, timeout=20)
        except urllib.error.HTTPError as error:
            response = error
        text = response.read().decode('utf-8')
        match = re.search(r'name="csrf" value="([a-f0-9]{64})"', text)
        if match:
            self.token = match.group(1)
        return response.status, text, response.headers, response.geturl()

    def post(self, path, data):
        return self.request(path, {'csrf': self.token, **data})

    def login(self, username):
        self.request('/login')
        result = self.post('/login', {'username': username, 'password': PASSWORD})
        check(result[0] == 200 and 'nav-' in result[1], username + ' login')
        return result


employee, hr, staff, admin = (Client() for _ in range(4))
status, text, headers, _ = employee.request('/login')
check(status == 200 and 'login-form' in text, 'W1 login form')
check('HttpOnly' in headers.get('Set-Cookie', '') and
      'SameSite=Lax' in headers.get('Set-Cookie', ''), 'cookie flags')
check(headers.get('X-Frame-Options') == 'DENY' and
      "default-src 'self'" in headers.get('Content-Security-Policy', ''), 'security headers')
for client, name in [(employee, 'kt01'), (hr, 'hr01'), (staff, 'it01'), (admin, 'itadmin')]:
    result = client.login(name)
    check(('href="/assets"' in result[1]) == (name == 'itadmin'), name + ' role menu')
check(employee.request('/assets')[0] == 403, 'W3 role denial')
check(employee.request('/tickets/view?id=2')[0] == 403, 'W6 IDOR')
check(admin.request('/directory?edit=1')[0] == 403, 'IT cannot edit directory')
check(employee.request('/logout')[0] == 405, 'GET logout rejected')
check(employee.request('/tickets', {'tieu_de': 'no csrf'})[0] == 419, 'CSRF rejected')
check(employee.post('/assets', {'action': 'delete', 'id': '1'})[0] == 403, 'POST role denial')
check(employee.request('/directory?q=' + urllib.parse.quote("' OR 1=1 --"))[0] == 200, 'W9 SQL injection search')
xss = '<script>alert(1)</script>'
created = employee.post('/tickets', {'tieu_de': xss, 'mo_ta': 'HTTP integration check', 'uu_tien': 'high', 'asset_id': ''})
check(created[0] == 200 and '/tickets/view?id=' in created[3], 'W4 ticket create')
check('&lt;script&gt;alert(1)&lt;/script&gt;' in created[1] and xss not in created[1], 'W9 XSS escaped')
ticket_path = '/tickets/view?id=' + re.search(r'id=(\d+)', created[3]).group(1)
check(hr.request(ticket_path)[0] == 403, 'cross-user new ticket denied')
admin.request(ticket_path)
check(admin.post(ticket_path, {'action': 'update', 'trang_thai': 'completed', 'nguoi_xu_ly': '4'})[0] == 422, 'invalid state jump rejected')
check(admin.post(ticket_path, {'action': 'update', 'trang_thai': 'in_progress', 'nguoi_xu_ly': '3'})[0] == 422, 'employee handler rejected')
check(admin.post(ticket_path, {'action': 'update', 'trang_thai': 'in_progress', 'nguoi_xu_ly': '4'})[0] == 200, 'W5 in progress')
check(admin.post(ticket_path, {'action': 'comment', 'noi_dung': 'Đã kiểm tra và khắc phục.'})[0] == 200, 'IT comment')
check(admin.post(ticket_path, {'action': 'update', 'trang_thai': 'completed', 'nguoi_xu_ly': '4'})[0] == 200, 'W5 completed')
check('Đã kiểm tra và khắc phục.' in employee.request(ticket_path)[1], 'employee sees comment history')
check(admin.request('/assets')[0] == 200, 'assets read')
check(admin.request('/admin/users')[0] == 200, 'users read')
check(admin.post('/admin/users', {'action': 'update', 'id': '4', 'ho_ten': 'Phạm Quang Huy', 'email': 'quanghuy@novatech.test', 'so_may_le': '300', 'phong_ban_id': '3', 'role': 'it_admin', 'trang_thai': 'locked'})[0] == 422, 'admin self-lock rejected')
announcement = hr.post('/announcements', {'action': 'create', 'tieu_de': 'HR PRIVATE HTTP CHECK', 'noi_dung': 'Chỉ dành cho phòng Nhân sự.', 'pham_vi': 'department', 'phong_ban_id': '2'})
check(announcement[0] == 200 and 'HR PRIVATE HTTP CHECK' in announcement[1], 'HR announcement create')
check('HR PRIVATE HTTP CHECK' not in employee.request('/announcements')[1], 'department scope')
log = admin.request('/admin/audit?username=kt01&action=forbidden')
check(log[0] == 200 and 'forbidden' in log[1], 'audit filtered')
for route in ['/announcements', '/directory', '/tickets', '/assets', '/admin/users', '/admin/audit']:
    result = admin.request(route)
    check(result[0] == 200 and 'Warning:' not in result[1], 'render ' + route)
unknown = Client()
unknown.request('/login')
for _ in range(5):
    unknown.post('/login', {'username': 'kt01', 'password': 'wrong-password'})
blocked = unknown.post('/login', {'username': 'kt01', 'password': PASSWORD})
check('Không thể đăng nhập.' in blocked[1] and 'login-form' in blocked[1], 'W8 lock after 5 failures')
check(employee.post('/logout', {})[3].endswith('/login'), 'logout POST')
print(f'PASS: {COUNT} HTTP checks')
