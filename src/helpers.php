<?php
declare(strict_types=1);
// Mọi truy vấn dùng native prepared statements.
function db(): PDO {
    static $db;
    if (!$db) {
        $c = $GLOBALS['config']['db'];
        $db = new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4", $c['user'], $c['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $GLOBALS['pdo']=$db;
        $db->exec("SET time_zone = '+07:00'");
    }
    return $db;
}
function query(string $sql, array $params=[]): PDOStatement { $s=db()->prepare($sql); $s->execute($params); return $s; }
function rows(string $sql,array $params=[]): array { return query($sql,$params)->fetchAll(); }
function one(string $sql,array $params=[]): ?array { return query($sql,$params)->fetch() ?: null; }
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function redirect(string $url): never { header('Location: '.$url, true, 303); exit; }
function user(): ?array { return $GLOBALS['current_user'] ?? null; }
function ip(): string { return substr($_SERVER['REMOTE_ADDR'] ?? 'unknown',0,45); }
function audit(string $action,string $object='',string $result='success',?array $actor=null): void {
    $u=$actor ?? user();
    query('INSERT INTO audit_logs(user_id,username,hanh_dong,doi_tuong,ip_nguon,ket_qua) VALUES(?,?,?,?,?,?)',[$u['id']??null,mb_substr($u['username']??'guest',0,64),$action,mb_substr($object,0,190),ip(),$result]);
}
function abort_page(int $status,string $message): never {
    if(isset($GLOBALS['pdo']) && $GLOBALS['pdo']->inTransaction()) $GLOBALS['pdo']->rollBack();
    http_response_code($status); render('message',['title'=>(string)$status,'message'=>$message]); exit;
}
function require_role(array $roles): void {
    if (!user() || !in_array(user()['role'],$roles,true)) { audit('forbidden',parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'denied'); abort_page(403,'Bạn không có quyền truy cập chức năng này.'); }
}
function deny(string $object): never { audit('forbidden',$object,'denied'); abort_page(403,'Bạn không có quyền truy cập dữ liệu này.'); }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): void { echo '<input type="hidden" name="csrf" value="'.e(csrf_token()).'">'; }
function verify_csrf(): void { if (!is_string($_POST['csrf']??null) || !hash_equals(csrf_token(),$_POST['csrf'])) abort_page(419,'Phiên biểu mẫu hết hạn. Tải lại trang rồi thử lại.'); }
function text(string $key,int $max=200,bool $required=true): string {
    $v=$_POST[$key]??'';
    if (!is_string($v)) throw new InvalidArgumentException('Dữ liệu không hợp lệ.');
    $v=trim($v);
    if (($required && $v==='') || mb_strlen($v)>$max) throw new InvalidArgumentException('Vui lòng nhập đầy đủ dữ liệu, đúng độ dài quy định.');
    return $v;
}
function choice(string $key,array $allowed): string { $v=text($key); if(!in_array($v,$allowed,true)) throw new InvalidArgumentException('Lựa chọn không hợp lệ.'); return $v; }
function positive_id(mixed $v): int { $id=filter_var($v,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); if($id===false) throw new InvalidArgumentException('Mã bản ghi không hợp lệ.'); return $id; }
function post_id(string $key='id'): int { return positive_id($_POST[$key]??null); }
function get_id(): int { return positive_id($_GET['id']??null); }
function search_value(string $key='q'): string { $v=$_GET[$key]??''; return is_string($v)?mb_substr(trim($v),0,100):''; }
function password_input(string $key='password'): string {
    $v=$_POST[$key]??null;
    if(!is_string($v) || strlen($v)<12 || strlen($v)>72) throw new InvalidArgumentException('Mật khẩu phải dài 12–72 byte.');
    return $v;
}
function render(string $view,array $data=[]): void {
    $path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
    extract($data,EXTR_SKIP);
    ob_start(); require __DIR__.'/../templates/'.$view.'.php';
    $content=ob_get_clean(); require __DIR__.'/../templates/layout.php';
}
function flash(string $message): void { $_SESSION['flash']=$message; }
function departments(): array { return rows('SELECT * FROM departments ORDER BY id'); }
function department_id(string $key='phong_ban_id'): int { $id=post_id($key); if(!one('SELECT id FROM departments WHERE id=?',[$id])) throw new InvalidArgumentException('Phòng ban không tồn tại.'); return $id; }
function label(string $v): string { return ['employee'=>'Nhân viên','accounting'=>'Kế toán','hr'=>'Nhân sự','it_admin'=>'Quản trị IT','new'=>'Mới','in_progress'=>'Đang xử lý','completed'=>'Hoàn thành','low'=>'Thấp','medium'=>'Trung bình','high'=>'Cao','active'=>'Hoạt động','locked'=>'Đã khóa','maintenance'=>'Bảo trì','inactive'=>'Ngừng hoạt động','company'=>'Toàn công ty','department'=>'Theo phòng ban'][$v]??$v; }
function badge(string $value): void { echo '<span class="badge '.e($value).'">'.e(label($value)).'</span>'; }
function field(string $name,string $label,string $value='',string $type='text',int $max=200,bool $required=true): void {
    echo '<label for="'.e($name).'">'.e($label).'</label><input id="'.e($name).'" name="'.e($name).'" type="'.e($type).'" value="'.e($value).'" maxlength="'.$max.'"'.($required?' required':'').'>';
}
function select_field(string $name,string $label,array $options,mixed $selected=''): void {
    echo '<label for="'.e($name).'">'.e($label).'</label><select id="'.e($name).'" name="'.e($name).'">';
    foreach($options as $value=>$caption) echo '<option value="'.e($value).'"'.((string)$value===(string)$selected?' selected':'').'>'.e($caption).'</option>';
    echo '</select>';
}
function textarea_field(string $name,string $label,string $value='',int $max=10000): void { echo '<label for="'.e($name).'">'.e($label).'</label><textarea id="'.e($name).'" name="'.e($name).'" maxlength="'.$max.'" rows="5" required>'.e($value).'</textarea>'; }
function destroy_session(): void { $_SESSION=[]; if(ini_get('session.use_cookies')) { $p=session_get_cookie_params(); setcookie(session_name(),'',['expires'=>time()-42000,'path'=>$p['path'],'secure'=>$p['secure'],'httponly'=>true,'samesite'=>'Lax']); } session_destroy(); }
