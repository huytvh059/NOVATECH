<?php
$id=get_id();
$t=one('SELECT t.*,u.ho_ten,a.ten_may,p.ho_ten AS handler FROM tickets t JOIN users u ON u.id=t.nguoi_tao LEFT JOIN assets a ON a.id=t.asset_id LEFT JOIN users p ON p.id=t.nguoi_xu_ly WHERE t.id=?',[$id]);
if(!$t) abort_page(404,'Yêu cầu không tồn tại.');
if(user()['role']!=='it_admin' && (int)$t['nguoi_tao']!==(int)user()['id']) deny('tickets:'.$id);
if($_SERVER['REQUEST_METHOD']==='POST') {
    require_role(['it_admin']); $action=choice('action',['comment','update']);
    $db=db(); $db->beginTransaction();
    $locked=one('SELECT * FROM tickets WHERE id=? FOR UPDATE',[$id]);
    if($action==='comment') {
        $body=text('noi_dung',5000); query('INSERT INTO ticket_comments(ticket_id,nguoi_gui,noi_dung) VALUES(?,?,?)',[$id,user()['id'],$body]);
        query('UPDATE tickets SET ngay_cap_nhat=NOW() WHERE id=?',[$id]);
    } else {
        $status=choice('trang_thai',['new','in_progress','completed']); $handler=text('nguoi_xu_ly',20,false); $handler=$handler===''?null:positive_id($handler);
        if($handler && !one("SELECT id FROM users WHERE id=? AND role='it_admin' AND trang_thai='active'",[$handler])) throw new InvalidArgumentException('Người xử lý phải là IT đang hoạt động.');
        $allowed=['new'=>['new','in_progress'],'in_progress'=>['in_progress','completed'],'completed'=>['completed']];
        if(!in_array($status,$allowed[$locked['trang_thai']],true)) throw new InvalidArgumentException('Chỉ chuyển Mới → Đang xử lý → Hoàn thành.');
        if($status!=='new' && !$handler) throw new InvalidArgumentException('Chọn người xử lý trước khi chuyển trạng thái.');
        query("UPDATE tickets SET trang_thai=?,nguoi_xu_ly=?,ngay_cap_nhat=NOW(),ngay_dong=IF(?='completed',COALESCE(ngay_dong,NOW()),NULL) WHERE id=?",[$status,$handler,$status,$id]);
        if($locked['trang_thai']!==$status) query('INSERT INTO ticket_comments(ticket_id,nguoi_gui,noi_dung) VALUES(?,?,?)',[$id,user()['id'],'Trạng thái: '.label($locked['trang_thai']).' → '.label($status)]);
    }
    audit('ticket_'.$action,'tickets:'.$id); $db->commit(); flash('Đã cập nhật yêu cầu.'); redirect('/tickets/view?id='.$id);
}
$comments=rows('SELECT c.*,u.ho_ten FROM ticket_comments c JOIN users u ON u.id=c.nguoi_gui WHERE c.ticket_id=? ORDER BY c.ngay_gui,c.id',[$id]);
$handlers=rows("SELECT id,ho_ten FROM users WHERE role='it_admin' AND trang_thai='active' ORDER BY ho_ten");
render('ticket',compact('t','comments','handlers')+['title'=>'Yêu cầu #'.str_pad((string)$id,4,'0',STR_PAD_LEFT)]);
