<?php
declare(strict_types=1);
// Đặt mật khẩu cho tài khoản seed, chỉ chạy bằng quản trị hệ điều hành.
require __DIR__.'/password_input.php';
require __DIR__.'/../src/helpers.php';
try {
    $username=$argv[1]??'';
    if(!preg_match('/\A[a-zA-Z0-9._-]{1,64}\z/',$username)) throw new InvalidArgumentException('Cách dùng: php tools/set_password.php USERNAME');
    $path=getenv('NOVATECH_CONFIG') ?: '/etc/novatech/config.php';
    $GLOBALS['config']=require $path;
    $u=one('SELECT id,username,auth_source FROM users WHERE username=?',[$username]);
    if(!$u) throw new InvalidArgumentException('Tài khoản không tồn tại.');
    if($u['auth_source']!=='db') throw new InvalidArgumentException('M?t kh?u AD ch? ???c qu?n l? tr?n DC.');
    $hash=password_hash(read_password(),PASSWORD_BCRYPT);
    $db=db(); $db->beginTransaction();
    query('UPDATE users SET password_hash=?,must_change_password=1,session_version=session_version+1 WHERE id=?',[$hash,$u['id']]);
    query('INSERT INTO audit_logs(user_id,username,hanh_dong,doi_tuong,ip_nguon,ket_qua) VALUES(?,?,?,?,?,?)',[$u['id'],$username,'user_reset_cli','users:'.$u['id'],'local-cli','success']);
    $db->commit(); echo "Đã đặt mật khẩu tạm; bắt buộc đổi khi đăng nhập.\n";
} catch(Throwable $e) { if(isset($db) && $db->inTransaction()) $db->rollBack(); fwrite(STDERR,"Không thể đặt mật khẩu. Kiểm tra cấu hình và tài khoản.\n"); error_log((string)$e); exit(1); }
