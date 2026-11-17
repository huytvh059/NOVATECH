<?php
if($_SERVER['REQUEST_METHOD']==='POST') {
    $old=$_POST['current_password']??''; $new=password_input();
    if(!is_string($old) || !password_verify($old,user()['password_hash'])) throw new InvalidArgumentException('Mật khẩu hiện tại không đúng.');
    if(!is_string($_POST['confirm_password']??null) || !hash_equals($new,$_POST['confirm_password'])) throw new InvalidArgumentException('Xác nhận mật khẩu không khớp.');
    if(password_verify($new,user()['password_hash'])) throw new InvalidArgumentException('Chọn mật khẩu mới khác mật khẩu hiện tại.');
    $db=db(); $db->beginTransaction();
    query('UPDATE users SET password_hash=?,must_change_password=0,session_version=session_version+1 WHERE id=?',[password_hash($new,PASSWORD_BCRYPT),user()['id']]);
    audit('password_change','users:'.user()['id']); $db->commit();
    $_SESSION['version']=(int)user()['session_version']+1; session_regenerate_id(true); $_SESSION['csrf']=bin2hex(random_bytes(32)); flash('Đã đổi mật khẩu.'); redirect('/');
}
render('password',['title'=>'Đổi mật khẩu']);
