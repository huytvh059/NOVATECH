<?php
// Lỗi chung, không tiết lộ tài khoản tồn tại hay trạng thái khóa.
if(user()) redirect('/');
if($_SERVER['REQUEST_METHOD']==='POST') {
    $input=text('username',190); $password=$_POST['password']??'';
    if(!is_string($password) || strlen($password)>1024 || str_contains($password,"\0")) throw new InvalidArgumentException('Thông tin đăng nhập không hợp lệ.');
    require_once __DIR__.'/../Auth/provider.php';
    [$provider,$username]=authentication_provider($input);
    $u=$provider->authenticate($username,$password);
    if($u) { session_regenerate_id(true); $_SESSION=['uid'=>$u['id'],'version'=>(int)$u['session_version'],'last_seen'=>time(),'ad_checked_at'=>time(),'csrf'=>bin2hex(random_bytes(32))]; redirect($u['must_change_password']?'/password':'/'); }
    $error='Không thể đăng nhập. Kiểm tra thông tin hoặc thử lại sau 5 phút.';
}
render('login',['title'=>'Đăng nhập','error'=>$error]);
