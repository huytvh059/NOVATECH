<?php
require_role(['it_admin']);
if($_SERVER['REQUEST_METHOD']==='POST') {
    $action=choice('action',['create','update','delete']); $id=$action==='create'?null:post_id();
    if($id && !one('SELECT id FROM assets WHERE id=?',[$id])) abort_page(404,'Tài sản không tồn tại.');
    $db=db(); $db->beginTransaction();
    if($action==='delete') query('DELETE FROM assets WHERE id=?',[$id]);
    else {
        $name=text('ten_may',100); $role=text('vai_tro',190); $network=choice('mang',['Net_LAN (Servers)','Net_DMZ','Net_LAN (IT)','Net_LAN (HR)','Net_LAN (Kế toán)']);
        $fixed=text('fixed_ip',45); $floating=text('floating_ip',45,false); $os=text('he_dieu_hanh',100); $status=choice('tinh_trang',['active','maintenance','inactive']);
        if(!filter_var($fixed,FILTER_VALIDATE_IP) || ($floating!=='' && !filter_var($floating,FILTER_VALIDATE_IP))) throw new InvalidArgumentException('Địa chỉ IP không hợp lệ.');
        if(one('SELECT id FROM assets WHERE ten_may=? AND id<>?',[$name,$id??0])) throw new InvalidArgumentException('Tên máy đã tồn tại.');
        if($action==='create') { query('INSERT INTO assets(ten_may,vai_tro,mang,fixed_ip,floating_ip,he_dieu_hanh,tinh_trang) VALUES(?,?,?,?,?,?,?)',[$name,$role,$network,$fixed,$floating?:null,$os,$status]); $id=db()->lastInsertId(); }
        else query('UPDATE assets SET ten_may=?,vai_tro=?,mang=?,fixed_ip=?,floating_ip=?,he_dieu_hanh=?,tinh_trang=? WHERE id=?',[$name,$role,$network,$fixed,$floating?:null,$os,$status,$id]);
    }
    audit('asset_'.$action,'assets:'.$id); $db->commit(); flash('Đã cập nhật tài sản.'); redirect('/assets');
}
$network=search_value('network'); $list=rows('SELECT * FROM assets WHERE (?=\'\' OR mang=?) ORDER BY mang,ten_may LIMIT 200',[$network,$network]);
$edit=null; if(isset($_GET['edit'])) { $edit=one('SELECT * FROM assets WHERE id=?',[positive_id($_GET['edit'])]); if(!$edit) abort_page(404,'Tài sản không tồn tại.'); }
render('assets',compact('list','edit','network')+['title'=>'Tài sản & hạ tầng']);
