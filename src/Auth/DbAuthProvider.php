<?php
declare(strict_types=1);
require_once __DIR__.'/AuthProvider.php';
final class DbAuthProvider implements AuthProvider {
    public function authenticate(string $username,string $password): ?array {
        $key=hash('sha256',mb_strtolower($username).'|'.ip());
        $db=db(); $db->beginTransaction();
        try {
            query('INSERT INTO login_attempts(attempt_key) VALUES(?) ON DUPLICATE KEY UPDATE attempt_key=VALUES(attempt_key)',[$key]);
            $attempt=one('SELECT *, (locked_until > NOW()) AS blocked FROM login_attempts WHERE attempt_key=? FOR UPDATE',[$key]);
            $u=one('SELECT * FROM users WHERE username=? FOR UPDATE',[$username]);
            if ($attempt['blocked']) { audit('login_failed','Tạm khóa theo username/IP','locked',['id'=>$u['id']??null,'username'=>$username]); $db->commit(); return null; }
            if($attempt['locked_until']!==null) { query('UPDATE login_attempts SET failures=0,locked_until=NULL WHERE attempt_key=?',[$key]); $attempt['failures']=0; }
            // Hash giả giảm chênh lệch thời gian khi tài khoản không tồn tại.
            $valid=password_verify($password,$u['password_hash']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi');
            if (!$u || !$valid || $u['trang_thai']!=='active') {
                $fails=(int)$attempt['failures']+1;
                query('UPDATE login_attempts SET failures=?,locked_until=IF(? >= 5,DATE_ADD(NOW(),INTERVAL 5 MINUTE),NULL),last_attempt=NOW() WHERE attempt_key=?',[$fails,$fails,$key]);
                if($u) query('UPDATE users SET so_lan_sai=?,khoa_den=IF(? >= 5,DATE_ADD(NOW(),INTERVAL 5 MINUTE),NULL) WHERE id=?',[$fails,$fails,$u['id']]);
                audit('login_failed','Đăng nhập','failure',['id'=>$u['id']??null,'username'=>$username]); $db->commit(); return null;
            }
            query('UPDATE login_attempts SET failures=0,locked_until=NULL,last_attempt=NOW() WHERE attempt_key=?',[$key]);
            query('UPDATE users SET so_lan_sai=0,khoa_den=NULL WHERE id=?',[$u['id']]);
            audit('login_success','Đăng nhập','success',$u); $db->commit(); return $u;
        } catch(Throwable $e) { if($db->inTransaction()) $db->rollBack(); throw $e; }
    }
}
