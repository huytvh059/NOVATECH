<?php
// Lỗi chung, không tiết lộ tài khoản tồn tại hay trạng thái khóa.
if(user()) redirect('/');
if($_SERVER['REQUEST_METHOD']==='POST') {
    $username=text('username',64); $password=$_POST['password']??'';
    if(!preg_match('/\A[a-zA-Z0-9._-]{1,64}\z/',$username) || !is_string($password) || strlen($password)>72) throw new InvalidArgumentException('Thông tin đăng nhập không hợp lệ.');
    require_once __DIR__.'/../Auth/DbAuthProvider.php';
    $u=(new DbAuthProvider())->authenticate($username,$password);
    if($u) { session_regenerate_id(true); $_SESSION=['uid'=>$u['id'],'version'=>(int)$u['session_version'],'last_seen'=>time(),'csrf'=>bin2hex(random_bytes(32))]; redirect($u['must_change_password']?'/password':'/'); }
    $error='Không thể đăng nhập. Kiểm tra thông tin hoặc thử lại sau 5 phút.';
}
render('login',['title'=>'Đăng nhập','error'=>$error]);
