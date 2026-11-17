<?php
if($_SERVER['REQUEST_METHOD']==='POST') {
    $title=text('tieu_de'); $body=text('mo_ta',10000); $priority=choice('uu_tien',['low','medium','high']);
    $asset=text('asset_id',20,false); $asset=$asset===''?null:positive_id($asset);
    if($asset && !one('SELECT id FROM assets WHERE id=?',[$asset])) throw new InvalidArgumentException('Tài sản không tồn tại.');
    $db=db(); $db->beginTransaction();
    query('INSERT INTO tickets(tieu_de,mo_ta,uu_tien,nguoi_tao,asset_id) VALUES(?,?,?,?,?)',[$title,$body,$priority,user()['id'],$asset]);
    $id=db()->lastInsertId(); audit('ticket_create','tickets:'.$id); $db->commit(); flash('Đã gửi yêu cầu hỗ trợ.'); redirect('/tickets/view?id='.$id);
}
$params=[]; $where='';
if(user()['role']!=='it_admin') { $where=' WHERE t.nguoi_tao=?'; $params[] =user()['id']; }
$list=rows('SELECT t.*,u.ho_ten FROM tickets t JOIN users u ON u.id=t.nguoi_tao'.$where.' ORDER BY t.ngay_cap_nhat DESC,t.id DESC LIMIT 200',$params);
$assets=rows('SELECT id,ten_may FROM assets ORDER BY ten_may');
render('tickets',compact('list','assets')+['title'=>'Trung tâm hỗ trợ']);
