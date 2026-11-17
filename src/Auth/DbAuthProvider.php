<?php
declare(strict_types=1);
require_once __DIR__.'/AuthProvider.php';
require_once __DIR__.'/LoginGuard.php';
final class DbAuthProvider implements AuthProvider {
    public function authenticate(string $username,string $password): ?array {
        return LoginGuard::attempt($username,function() use($username,$password): ?array {
            $u=one("SELECT * FROM users WHERE username=? AND auth_source='db' FOR UPDATE",[$username]);
            // Hash giả giảm chênh lệch thời gian khi tài khoản không tồn tại.
            if(strlen($password)>72 || $password==='') return null;
            $valid=password_verify($password,$u['password_hash']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi');
            return $u && $valid && $u['trang_thai']==='active' ? $u : null;
        });
    }
}
