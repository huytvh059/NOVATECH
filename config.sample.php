<?php
// Sao chép ra ngoài webroot. Không lưu secret trong repo.
return [
 'db' => ['host'=>'10.10.100.13','port'=>3306,'name'=>'novatech_db','user'=>'webapp','password'=>'<DB_PASSWORD>'],
 // Chuyển ldap sau khi import migration và cấu hình DC.
 'auth'=>[
  'provider'=>'db',
  'local_admins'=>['itadmin'], // Chỉ tài khoản DB cứu hộ, dùng username ngắn.
  'ldap'=>[
   'uri'=>'ldap://10.10.100.11:389',
   'start_tls'=>false,
   'allow_insecure'=>false, // Chỉ true khi chấp nhận LDAP không mã hóa trong lab.
   'domain'=>'ad.novatech.test', 'netbios'=>'NOVATECH',
   'base_dn'=>'DC=ad,DC=novatech,DC=test', // Nên giới hạn OU nhân viên.
   'bind_dn'=>'<LDAP_READONLY_ACCOUNT_DN>', 'bind_password'=>'<LDAP_BIND_PASSWORD>',
   'timeout'=>3, 'refresh_seconds'=>60,
   'groups'=>[
    'it_admin'=>'CN=GG_IT,OU=Groups,DC=ad,DC=novatech,DC=test',
    'hr'=>'CN=GG_HR,OU=Groups,DC=ad,DC=novatech,DC=test',
    'accounting'=>'CN=GG_KeToan,OU=Groups,DC=ad,DC=novatech,DC=test',
   ],
   'department_map'=>['IT'=>'IT','HR'=>'Nhân sự','Nhân sự'=>'Nhân sự','Kế toán'=>'Kế toán','Accounting'=>'Kế toán'],
   'default_department'=>'', // Không đoán phòng ban; cấu hình rõ nếu cần fallback.
  ],
 ],
 'secure_cookie'=>false,
 'error_log'=>'/var/log/novatech/php-error.log',
];
