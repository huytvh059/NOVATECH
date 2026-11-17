<?php
if($_SERVER['REQUEST_METHOD']==='POST') {
    require_role(['hr']); $id=post_id();
    if(!one('SELECT id FROM users WHERE id=?',[$id])) abort_page(404,'Nhân viên không tồn tại.');
    $name=text('ho_ten',150); $email=text('email',190); $ext=text('so_may_le',20,false); $dept=department_id();
    if(!filter_var($email,FILTER_VALIDATE_EMAIL) || ($ext!=='' && !preg_match('/\A[0-9+() -]{1,20}\z/',$ext))) throw new InvalidArgumentException('Email hoặc số máy lẻ không hợp lệ.');
    $db=db(); $db->beginTransaction(); query('UPDATE users SET ho_ten=?,email=?,so_may_le=?,phong_ban_id=? WHERE id=?',[$name,$email,$ext,$dept,$id]); audit('directory_update','users:'.$id); $db->commit(); flash('Đã cập nhật danh bạ.'); redirect('/directory');
}
$q=search_value(); $like='%'.$q.'%';
$list=rows('SELECT u.id,u.ho_ten,u.email,u.so_may_le,u.phong_ban_id,d.ten_phong_ban FROM users u JOIN departments d ON d.id=u.phong_ban_id WHERE u.ho_ten LIKE ? OR u.email LIKE ? OR u.so_may_le LIKE ? OR d.ten_phong_ban LIKE ? ORDER BY u.ho_ten LIMIT 200',[$like,$like,$like,$like]);
$edit=null;
if(isset($_GET['edit'])) { require_role(['hr']); $edit=one('SELECT * FROM users WHERE id=?',[positive_id($_GET['edit'])]); if(!$edit) abort_page(404,'Nhân viên không tồn tại.'); }
render('directory',compact('list','q','edit')+['title'=>'Danh bạ nhân viên']);
