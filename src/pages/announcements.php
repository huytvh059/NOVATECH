<?php
$canEdit=in_array(user()['role'],['hr','it_admin'],true);
if($_SERVER['REQUEST_METHOD']==='POST') {
    require_role(['hr','it_admin']); $action=choice('action',['create','update','delete']);
    $id=$action==='create'?null:post_id();
    if($id && !one('SELECT id FROM announcements WHERE id=?',[$id])) abort_page(404,'Thông báo không tồn tại.');
    $db=db(); $db->beginTransaction();
    if($action==='delete') query('DELETE FROM announcements WHERE id=?',[$id]);
    else {
        $title=text('tieu_de'); $body=text('noi_dung',10000); $scope=choice('pham_vi',['company','department']);
        $dept=$scope==='department'?department_id():null;
        if($action==='create') { query('INSERT INTO announcements(tieu_de,noi_dung,pham_vi,phong_ban_id,tao_boi) VALUES(?,?,?,?,?)',[$title,$body,$scope,$dept,user()['id']]); $id=db()->lastInsertId(); }
        else query('UPDATE announcements SET tieu_de=?,noi_dung=?,pham_vi=?,phong_ban_id=? WHERE id=?',[$title,$body,$scope,$dept,$id]);
    }
    audit('announcement_'.$action,'announcements:'.$id); $db->commit(); flash('Đã cập nhật thông báo.'); redirect('/announcements');
}
$edit=null;
if(isset($_GET['edit'])) { require_role(['hr','it_admin']); $edit=one('SELECT * FROM announcements WHERE id=?',[positive_id($_GET['edit'])]); if(!$edit) abort_page(404,'Thông báo không tồn tại.'); }
// Quyền quản lý không làm lộ thông báo phòng ban khác trên danh sách xem.
$list=rows("SELECT a.*,u.ho_ten,d.ten_phong_ban FROM announcements a JOIN users u ON u.id=a.tao_boi LEFT JOIN departments d ON d.id=a.phong_ban_id WHERE a.pham_vi='company' OR a.phong_ban_id=? ORDER BY a.ngay_tao DESC,a.id DESC LIMIT 100",[user()['phong_ban_id']]);
render('announcements',compact('list','canEdit','edit')+['title'=>'Thông báo nội bộ']);
