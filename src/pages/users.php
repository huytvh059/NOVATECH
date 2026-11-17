<?php
require_role(['it_admin']);
if($_SERVER['REQUEST_METHOD']==='POST') {
    $action=choice('action',['create','update','reset']); $id=$action==='create'?null:post_id();
    $db=db(); $db->beginTransaction();
    // Khóa hàng admin theo cùng thứ tự để không có hai thao tác hạ quyền đồng thời.
    $admins=rows("SELECT id FROM users WHERE role='it_admin' AND trang_thai='active' ORDER BY id FOR UPDATE");
    $target=$id?one('SELECT * FROM users WHERE id=? FOR UPDATE',[$id]):null;
    if($id && !$target) abort_page(404,'Tài khoản không tồn tại.');
    if($target && $target['auth_source']==='ldap') deny('ad_user_managed_externally:'.$id);
    if($action==='reset') {
        $password=password_input();
        query('UPDATE users SET password_hash=?,must_change_password=1,session_version=session_version+1 WHERE id=?',[password_hash($password,PASSWORD_BCRYPT),$id]);
    } else {
        $name=text('ho_ten',150); $email=text('email',190); $ext=text('so_may_le',20,false); $dept=department_id();
        $role=choice('role',['employee','accounting','hr','it_admin']); $status=choice('trang_thai',['active','locked']);
        if(!filter_var($email,FILTER_VALIDATE_EMAIL) || ($ext!=='' && !preg_match('/\A[0-9+() -]{1,20}\z/',$ext))) throw new InvalidArgumentException('Email hoặc số máy lẻ không hợp lệ.');
        if($id && (int)$id===(int)user()['id'] && ($status!=='active' || $role!=='it_admin')) throw new InvalidArgumentException('Không được tự khóa hoặc tự hạ quyền.');
        if($target && $target['role']==='it_admin' && $target['trang_thai']==='active' && count($admins)<=1 && ($role!=='it_admin' || $status!=='active')) throw new InvalidArgumentException('Phải giữ ít nhất một quản trị IT đang hoạt động.');
        if($action==='create') {
            $username=text('username',64); $password=password_input();
            if(!preg_match('/\A[a-zA-Z0-9._-]{1,64}\z/',$username)) throw new InvalidArgumentException('Tên đăng nhập chỉ gồm chữ, số, dấu chấm, gạch ngang, gạch dưới.');
            if(one('SELECT id FROM users WHERE username=?',[$username])) throw new InvalidArgumentException('Tên đăng nhập đã tồn tại.');
            query('INSERT INTO users(username,password_hash,ho_ten,email,so_may_le,phong_ban_id,role,trang_thai) VALUES(?,?,?,?,?,?,?,?)',[$username,password_hash($password,PASSWORD_BCRYPT),$name,$email,$ext,$dept,$role,$status]); $id=db()->lastInsertId();
        } else query('UPDATE users SET ho_ten=?,email=?,so_may_le=?,phong_ban_id=?,role=?,trang_thai=?,session_version=session_version+1 WHERE id=?',[$name,$email,$ext,$dept,$role,$status,$id]);
    }
    audit('user_'.$action,'users:'.$id); $db->commit();
    if((int)$id===(int)user()['id']) { if($action==='reset') { destroy_session(); redirect('/login'); } $_SESSION['version']=(int)user()['session_version']+1; }
    flash('Đã cập nhật tài khoản.'); redirect('/admin/users');
}
$list=rows('SELECT u.id,u.username,u.auth_source,u.ho_ten,u.role,u.trang_thai,d.ten_phong_ban FROM users u JOIN departments d ON d.id=u.phong_ban_id ORDER BY u.id LIMIT 200');
$edit=null; if(isset($_GET['edit'])) { $edit=one('SELECT * FROM users WHERE id=?',[positive_id($_GET['edit'])]); if(!$edit) abort_page(404,'Tài khoản không tồn tại.'); }
if($edit && $edit['auth_source']==='ldap') {
    render('message',['title'=>'Tài khoản Active Directory','message'=>'Thông tin, nhóm quyền, khóa/mở và mật khẩu được quản lý trên DC01. Website đồng bộ khi đăng nhập hoặc kiểm tra lại phiên.']);
    return;
}
render('users',compact('list','edit')+['title'=>'Quản lý người dùng']);
