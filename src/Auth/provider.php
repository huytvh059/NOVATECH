<?php
declare(strict_types=1);
require_once __DIR__.'/DbAuthProvider.php';
require_once __DIR__.'/LdapAuthProvider.php';
// Chỉ username cứu hộ khai báo rõ mới dùng DB trong chế độ LDAP.
function authentication_provider(string $input): array {
    $auth=$GLOBALS['config']['auth']??['provider'=>'db'];
    $provider=$auth['provider']??'db';
    if($provider==='db') {
        if(!preg_match('/\A[a-zA-Z0-9._-]{1,64}\z/',$input)) throw new InvalidArgumentException('Tên đăng nhập không hợp lệ.');
        return [new DbAuthProvider(),strtolower($input)];
    }
    if($provider!=='ldap') throw new RuntimeException('Auth provider không hợp lệ.');
    $ldap=$auth['ldap']??[];
    $username=LdapAuthProvider::normalizeUsername($input,$ldap);
    $local=array_map('strtolower',$auth['local_admins']??[]);
    // Cứu hộ chỉ qua username ngắn, không dùng DOMAIN\name hoặc UPN.
    if($input===$username && in_array($username,$local,true)) {
        $rescue=one("SELECT id FROM users WHERE username=? AND auth_source='db' AND role='it_admin' AND trang_thai='active'",[$username]);
        if(!$rescue) throw new RuntimeException('T?i kho?n c?u h? ph?i l? qu?n tr? DB ?ang ho?t ??ng.');
        return [new DbAuthProvider(),$username];
    }
    return [new LdapAuthProvider($ldap),$username];
}
