<?php
declare(strict_types=1);
require_once __DIR__.'/AuthProvider.php';
// Một khóa cho username/IP, dùng chung mọi provider, kể cả username chưa tồn tại.
final class LoginGuard {
    public static function attempt(string $username, callable $verify): ?array {
        $key=hash('sha256',mb_strtolower($username).'|'.ip());
        $db=db(); $db->beginTransaction();
        try {
            query('INSERT INTO login_attempts(attempt_key) VALUES(?) ON DUPLICATE KEY UPDATE attempt_key=VALUES(attempt_key)',[$key]);
            $attempt=one('SELECT *, (locked_until > NOW()) AS blocked FROM login_attempts WHERE attempt_key=? FOR UPDATE',[$key]);
            $actor=one('SELECT id,username FROM users WHERE username=?',[$username]) ?? ['username'=>$username];
            if($attempt['blocked']) {
                audit('login_failed','Tạm khóa theo username/IP','locked',$actor); $db->commit(); return null;
            }
            if($attempt['locked_until']!==null) $attempt['failures']=0;
            $u=$verify();
            if(!$u) {
                $fails=(int)$attempt['failures']+1;
                query('UPDATE login_attempts SET failures=?,locked_until=IF(? >= 5,DATE_ADD(NOW(),INTERVAL 5 MINUTE),NULL),last_attempt=NOW() WHERE attempt_key=?',[$fails,$fails,$key]);
                if(isset($actor['id'])) query('UPDATE users SET so_lan_sai=?,khoa_den=IF(? >= 5,DATE_ADD(NOW(),INTERVAL 5 MINUTE),NULL) WHERE id=?',[$fails,$fails,$actor['id']]);
                audit('login_failed','Đăng nhập','failure',$actor); $db->commit(); return null;
            }
            query('UPDATE login_attempts SET failures=0,locked_until=NULL,last_attempt=NOW() WHERE attempt_key=?',[$key]);
            query('UPDATE users SET so_lan_sai=0,khoa_den=NULL WHERE id=?',[$u['id']]);
            audit('login_success','Đăng nhập','success',$u); $db->commit(); return $u;
        } catch(Throwable $error) {
            if($db->inTransaction()) $db->rollBack();
            throw $error;
        }
    }
}
