<?php
declare(strict_types=1);
// Xác thực AD qua LDAP; không lưu mật khẩu AD, không tự fallback DB.
require_once __DIR__.'/AuthProvider.php';
require_once __DIR__.'/LoginGuard.php';
final class LdapAuthProvider implements AuthProvider {
    private array $config;
    public function __construct(array $config) { $this->config=$config; }

    public static function normalizeUsername(string $input,array $config): string {
        $name=trim($input);
        if(str_contains($name,'\\')) {
            $parts=explode('\\',$name);
            if(count($parts)!==2 || strcasecmp($parts[0],$config['netbios']??'NOVATECH')!==0) throw new InvalidArgumentException('Domain đăng nhập không hợp lệ.');
            $name=$parts[1];
        } elseif(str_contains($name,'@')) {
            $parts=explode('@',$name);
            if(count($parts)!==2 || strcasecmp($parts[1],$config['domain'])!==0) throw new InvalidArgumentException('Domain đăng nhập không hợp lệ.');
            $name=$parts[0];
        }
        if(!preg_match('/\A[a-zA-Z0-9._-]{1,64}\z/',$name)) throw new InvalidArgumentException('Tên đăng nhập AD không hợp lệ.');
        return strtolower($name);
    }

    public static function validateTransport(array $config): void {
        $uri=$config['uri']??'';
        $parts=parse_url($uri);
        if(!$parts || !in_array($parts['scheme']??'',['ldap','ldaps'],true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || !in_array($parts['path']??'',['','/'],true)) throw new RuntimeException('Cấu hình LDAP URI không hợp lệ.');
        if($parts['scheme']==='ldap' && empty($config['start_tls']) && ($config['allow_insecure']??false)!==true) throw new RuntimeException('LDAP không mã hóa chưa được cho phép rõ ràng.');
        foreach(['base_dn','bind_dn','bind_password','domain'] as $key) {
            if(!isset($config[$key]) || !is_string($config[$key]) || $config[$key]==='' || str_contains($config[$key],"\0") || str_starts_with($config[$key],'<')) throw new RuntimeException('Thiếu cấu hình LDAP.');
        }
        foreach(['it_admin','hr','accounting'] as $role) {
            if(empty($config['groups'][$role]) || !is_string($config['groups'][$role])) throw new RuntimeException('Thiếu DN nhóm AD.');
        }
    }

    private function connect(): LDAP\Connection {
        self::validateTransport($this->config);
        if(!extension_loaded('ldap')) throw new RuntimeException('Thiếu PHP LDAP extension.');
        $connection=ldap_connect($this->config['uri']);
        if(!$connection) throw new RuntimeException('Không khởi tạo được LDAP.');
        $timeout=max(1,min(10,(int)($this->config['timeout']??3)));
        ldap_set_option($connection,LDAP_OPT_PROTOCOL_VERSION,3);
        ldap_set_option($connection,LDAP_OPT_REFERRALS,0);
        ldap_set_option($connection,LDAP_OPT_NETWORK_TIMEOUT,$timeout);
        ldap_set_option($connection,LDAP_OPT_TIMELIMIT,$timeout);
        // Không tắt xác minh chứng chỉ khi nâng lên TLS.
        ldap_set_option($connection,LDAP_OPT_X_TLS_REQUIRE_CERT,LDAP_OPT_X_TLS_DEMAND);
        if(!empty($this->config['start_tls']) && !@ldap_start_tls($connection)) { ldap_unbind($connection); throw new RuntimeException('Không thiết lập được LDAP StartTLS.'); }
        return $connection;
    }

    private function serviceConnection(): LDAP\Connection {
        $connection=$this->connect();
        if(!@ldap_bind($connection,$this->config['bind_dn'],$this->config['bind_password'])) {
            ldap_unbind($connection); throw new RuntimeException('LDAP service bind thất bại. Kiểm tra log DC và cấu hình.');
        }
        return $connection;
    }

    private function attributes(): array {
        return ['objectGUID','sAMAccountName','displayName','mail','telephoneNumber','department','userAccountControl','msDS-User-Account-Control-Computed','accountExpires','pwdLastSet'];
    }

    private function search(LDAP\Connection $connection,string $filter): ?array {
        $result=@ldap_search($connection,$this->config['base_dn'],$filter,$this->attributes(),0,2,max(1,min(10,(int)($this->config['timeout']??3))));
        if($result===false) throw new RuntimeException('LDAP search thất bại hoặc kết quả không duy nhất.');
        $entries=ldap_get_entries($connection,$result);
        if($entries===false) throw new RuntimeException('Không đọc được dữ liệu LDAP.');
        if($entries['count']!==1) return null;
        return $entries[0];
    }

    public static function value(array $entry,string $key): string { return (string)($entry[strtolower($key)][0]??''); }

    public static function enabled(array $entry): bool {
        $uac=self::value($entry,'userAccountControl');
        if($uac==='' || ((int)$uac & 2)!==0) return false;
        $computed=(int)self::value($entry,'msDS-User-Account-Control-Computed');
        if(($computed & (16 | 8388608))!==0 || self::value($entry,'pwdLastSet')==='0') return false;
        $expires=self::value($entry,'accountExpires');
        return $expires==='' || $expires==='0' || $expires==='9223372036854775807' || (float)$expires / 10000000 - 11644473600 > time();
    }

    private function role(LDAP\Connection $connection,array $entry): string {
        // Matching-rule-in-chain hỗ trợ nhóm lồng nhau, so theo DN thay vì tên CN.
        foreach(['it_admin','hr','accounting'] as $role) {
            $filter='(&(objectCategory=person)(objectClass=user)(sAMAccountName='.ldap_escape(self::value($entry,'sAMAccountName'),'',LDAP_ESCAPE_FILTER).')(memberOf:1.2.840.113556.1.4.1941:='.ldap_escape($this->config['groups'][$role],'',LDAP_ESCAPE_FILTER).'))';
            $result=@ldap_read($connection,$entry['dn'],$filter,['objectGUID']);
            if($result===false) throw new RuntimeException('Không kiểm tra được nhóm AD.');
            if(ldap_count_entries($connection,$result)===1) return $role;
        }
        return 'employee';
    }

    public static function departmentName(array $entry,string $role,array $config): string {
        $mapping=['it_admin'=>'IT','hr'=>'Nhân sự','accounting'=>'Kế toán'];
        if(isset($mapping[$role])) return $mapping[$role];
        $adDepartment=self::value($entry,'department');
        $department=$config['department_map'][$adDepartment]??($config['default_department']??'');
        if(!in_array($department,['IT','Nhân sự','Kế toán'],true)) throw new RuntimeException('Nhân viên AD chưa có phòng ban hợp lệ.');
        return $department;
    }

    private function sync(LDAP\Connection $connection,array $entry,?int $expectedId=null): ?array {
        $binary=self::value($entry,'objectGUID');
        if(strlen($binary)!==16) throw new RuntimeException('AD objectGUID không hợp lệ.');
        $guid=bin2hex($binary);
        $existing=one('SELECT * FROM users WHERE ad_guid=? FOR UPDATE',[$guid]);
        if($expectedId!==null && (!$existing || (int)$existing['id']!==$expectedId)) return null;
        if(!self::enabled($entry)) {
            if($existing) query("UPDATE users SET trang_thai='locked',session_version=session_version+1,ad_synced_at=NOW() WHERE id=?",[$existing['id']]);
            return null;
        }
        $username=self::normalizeUsername(self::value($entry,'sAMAccountName'),$this->config);
        $collision=one('SELECT id FROM users WHERE username=? AND (ad_guid IS NULL OR ad_guid<>?)',[$username,$guid]);
        if($collision) throw new RuntimeException('Username AD trùng tài khoản khác. Không tự liên kết để tránh chiếm tài khoản.');
        $role=$this->role($connection,$entry);
        $name=mb_substr(self::value($entry,'displayName')?:$username,0,150);
        $mail=self::value($entry,'mail');
        if($mail!=='' && (!filter_var($mail,FILTER_VALIDATE_EMAIL) || strlen($mail)>190)) $mail='';
        $extension=mb_substr(self::value($entry,'telephoneNumber'),0,20);
        $department=self::departmentName($entry,$role,$this->config);
        $dept=one('SELECT id FROM departments WHERE ten_phong_ban=?',[$department]);
        if(!$dept) throw new RuntimeException('Phòng ban chưa được seed.');
        if($existing) {
            $changed=$existing['role']!==$role || (int)$existing['phong_ban_id']!==(int)$dept['id'] || $existing['trang_thai']!=='active';
            query("UPDATE users SET username=?,ho_ten=?,email=?,so_may_le=?,phong_ban_id=?,role=?,trang_thai='active',must_change_password=0,session_version=session_version+?,ad_synced_at=NOW() WHERE id=?",[$username,$name,$mail,$extension,$dept['id'],$role,$changed?1:0,$existing['id']]);
            $id=$existing['id'];
            if($changed) audit('ad_permissions_sync','users:'.$id,'success',['id'=>$id,'username'=>$username]);
        } else {
            // Hash ngẫu nhiên không thể dùng làm mật khẩu dự phòng; DB provider chặn auth_source=ldap.
            query("INSERT INTO users(username,password_hash,ho_ten,email,so_may_le,phong_ban_id,role,auth_source,ad_guid,ad_synced_at,must_change_password) VALUES(?,?,?,?,?,?,?,'ldap',?,NOW(),0)",[$username,password_hash(bin2hex(random_bytes(32)),PASSWORD_BCRYPT),$name,$mail,$extension,$dept['id'],$role,$guid]);
            $id=db()->lastInsertId(); audit('ad_user_create','users:'.$id,'success',['id'=>$id,'username'=>$username]);
        }
        return one('SELECT * FROM users WHERE id=?',[$id]);
    }

    public function authenticate(string $username,string $password): ?array {
        return LoginGuard::attempt($username,function() use($username,$password): ?array {
            // AD không được phép anonymous/unauthenticated bind với mật khẩu rỗng.
            if($password==='' || str_contains($password,"\0")) return null;
            $service=$this->serviceConnection();
            try {
                $entry=$this->search($service,'(&(objectCategory=person)(objectClass=user)(sAMAccountName='.ldap_escape($username,'',LDAP_ESCAPE_FILTER).'))');
                if(!$entry || !self::enabled($entry)) return null;
                $connection=$this->connect();
                try {
                    if(!@ldap_bind($connection,$entry['dn'],$password)) {
                        $code=ldap_errno($connection);
                        if(!in_array($code,[48,49],true)) throw new RuntimeException('AD từ chối bind hoặc dịch vụ không khả dụng.');
                        return null;
                    }
                } finally { ldap_unbind($connection); }
                return $this->sync($service,$entry);
            } finally { ldap_unbind($service); }
        });
    }

    public function refresh(array $user): ?array {
        $service=$this->serviceConnection();
        $db=db(); $db->beginTransaction();
        try {
            $binary=hex2bin($user['ad_guid']);
            if($binary===false || strlen($binary)!==16) throw new RuntimeException('GUID đã lưu không hợp lệ.');
            $entry=$this->search($service,'(&(objectCategory=person)(objectClass=user)(objectGUID='.ldap_escape($binary,'',LDAP_ESCAPE_FILTER).'))');
            if(!$entry) {
                query("UPDATE users SET trang_thai='locked',session_version=session_version+1,ad_synced_at=NOW() WHERE id=?",[$user['id']]);
                audit('ad_access_revoked','users:'.$user['id'],'denied',$user); $db->commit(); return null;
            }
            $synced=$this->sync($service,$entry,(int)$user['id']);
            if(!$synced) audit('ad_access_revoked','users:'.$user['id'],'denied',$user);
            $db->commit(); return $synced;
        } catch(Throwable $error) { if($db->inTransaction()) $db->rollBack(); throw $error; }
        finally { ldap_unbind($service); }
    }
}
