<?php
declare(strict_types=1);
// Kiểm thử không cần database; không phụ thuộc framework/assert.ini.
require __DIR__.'/../src/helpers.php';
$count=0;
function check(bool $condition,string $name): void { global $count; if(!$condition) throw new RuntimeException('FAIL: '.$name); $count++; }
function rejects(callable $fn,string $name): void { try { $fn(); } catch(InvalidArgumentException $e) { check(true,$name); return; } throw new RuntimeException('FAIL: '.$name); }
check(e('<script>"&\'')==='&lt;script&gt;&quot;&amp;&#039;','escape HTML');
check(positive_id('12')===12,'positive ID');
foreach(['0','-1','1 OR 1=1',[],null] as $bad) rejects(fn()=>positive_id($bad),'invalid ID');
$_POST=['title'=>'  Xin chào  ']; check(text('title')==='Xin chào','trim text');
$_POST=['title'=>['x']]; rejects(fn()=>text('title'),'array input');
$_POST=['title'=>str_repeat('a',201)]; rejects(fn()=>text('title'),'max text');
$_POST=['role'=>'it_admin']; check(choice('role',['it_admin','hr'])==='it_admin','choice');
rejects(fn()=>choice('role',['hr']),'invalid role');
$_POST=['password'=>'short']; rejects(fn()=>password_input(),'short password');
$_POST=['password'=>str_repeat('a',73)]; rejects(fn()=>password_input(),'bcrypt byte limit');
$_POST=['password'=>str_repeat('a',12)]; check(strlen(password_input())===12,'valid password');
$_SESSION=[]; $token=csrf_token(); check(strlen($token)===64 && hash_equals($token,csrf_token()),'stable CSRF');
$_POST=['csrf'=>$token]; verify_csrf(); check(true,'CSRF valid');
$_GET=['q'=>['bad']]; check(search_value()==='','search array');
check(label('in_progress')==='Đang xử lý','Vietnamese labels');
check(!str_contains(file_get_contents(__DIR__.'/../public/index.php'),'$_GET[\'action\']'),'no GET mutation');
echo "PASS: $count checks\n";
