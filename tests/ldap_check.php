<?php
declare(strict_types=1);
// Kiểm thử thuần: không cần DC; không thay thế kiểm thử AD end-to-end.
require __DIR__.'/../src/Auth/LdapAuthProvider.php';
$n=0;
function verify(bool $ok,string $name): void { global $n; if(!$ok) throw new RuntimeException('FAIL: '.$name); $n++; }
function invalid(callable $fn,string $name): void { try { $fn(); } catch(RuntimeException|InvalidArgumentException $e) { verify(true,$name); return; } throw new RuntimeException('FAIL: '.$name); }
$config=['uri'=>'ldap://10.10.100.11:389','allow_insecure'=>true,'start_tls'=>false,'base_dn'=>'OU=Employees,DC=ad,DC=novatech,DC=test','bind_dn'=>'CN=svc_portal,OU=Services,DC=ad,DC=novatech,DC=test','bind_password'=>'test-only-not-real','domain'=>'ad.novatech.test','netbios'=>'NOVATECH','groups'=>['it_admin'=>'CN=GG_IT,DC=ad,DC=novatech,DC=test','hr'=>'CN=GG_HR,DC=ad,DC=novatech,DC=test','accounting'=>'CN=GG_KeToan,DC=ad,DC=novatech,DC=test'],'department_map'=>['HR'=>'Nhân sự','IT'=>'IT']];
LdapAuthProvider::validateTransport($config);verify(true,'plain LDAP explicit opt-in');
invalid(fn()=>LdapAuthProvider::validateTransport(array_replace($config,['allow_insecure'=>false])),'plain denied by default');
LdapAuthProvider::validateTransport(array_replace($config,['uri'=>'ldaps://dc01.ad.novatech.test:636','allow_insecure'=>false]));verify(true,'LDAPS permitted');
LdapAuthProvider::validateTransport(array_replace($config,['start_tls'=>true,'allow_insecure'=>false]));verify(true,'StartTLS permitted');
foreach(['http://host','ldap://user:secret@host','ldap://host/path','ldap://host?query'] as $uri) invalid(fn()=>LdapAuthProvider::validateTransport(array_replace($config,['uri'=>$uri])),'bad URI');
invalid(fn()=>LdapAuthProvider::validateTransport(array_replace($config,['bind_password'=>''])),'empty service password');
invalid(fn()=>LdapAuthProvider::validateTransport(array_replace($config,['bind_password'=>"secret\0x"])),'NUL service password');
verify(LdapAuthProvider::normalizeUsername('KT01',$config)==='kt01','short username');
verify(LdapAuthProvider::normalizeUsername('NOVATECH\\kt01',$config)==='kt01','domain username');
verify(LdapAuthProvider::normalizeUsername('kt01@ad.novatech.test',$config)==='kt01','UPN username');
foreach(['OTHER\\kt01','kt01@other.test','*)(|(objectClass=*))','a\\b\\c',''] as $bad) invalid(fn()=>LdapAuthProvider::normalizeUsername($bad,$config),'bad username');
$entry=['useraccountcontrol'=>['512'],'pwdlastset'=>['123'],'accountexpires'=>['0'],'department'=>['IT']];
verify(LdapAuthProvider::enabled($entry),'enabled user');
verify(!LdapAuthProvider::enabled(array_replace($entry,['useraccountcontrol'=>['514']])),'disabled user');
verify(!LdapAuthProvider::enabled(array_replace($entry,['msds-user-account-control-computed'=>['16']])),'locked user');
verify(!LdapAuthProvider::enabled(array_replace($entry,['msds-user-account-control-computed'=>['8388608']])),'expired password');
verify(!LdapAuthProvider::enabled(array_replace($entry,['pwdlastset'=>['0']])),'must change AD password');
verify(!LdapAuthProvider::enabled(array_replace($entry,['accountexpires'=>['1']])),'expired account');
verify(LdapAuthProvider::departmentName($entry,'employee',$config)==='IT','department mapping');
verify(LdapAuthProvider::departmentName($entry,'hr',$config)==='Nhân sự','role department');
invalid(fn()=>LdapAuthProvider::departmentName([],'employee',$config),'missing department fails closed');
verify(LdapAuthProvider::departmentName([],'employee',$config+['default_department'=>'IT'])==='IT','explicit department fallback');
if(extension_loaded('ldap')) {
    $filter=ldap_escape("*)(objectClass=*)\0",'',LDAP_ESCAPE_FILTER);
    verify(!str_contains($filter,'*') && !str_contains($filter,'(') && !str_contains($filter,"\0"),'filter escaping');
    $guid=random_bytes(16); verify(strlen(bin2hex($guid))===32 && hex2bin(bin2hex($guid))===$guid,'GUID roundtrip');
}
echo "PASS: $n LDAP checks\n";
