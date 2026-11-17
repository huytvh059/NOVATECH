<?php
if($_SERVER['REQUEST_METHOD']!=='GET') abort_page(405,'Chức năng chỉ hỗ trợ GET.');
$own=user()['role']==='it_admin'?'':' AND nguoi_tao=?'; $params=user()['role']==='it_admin'?[]:[user()['id']];
$open=(int)one("SELECT COUNT(*) AS n FROM tickets WHERE trang_thai != 'completed'".$own,$params)['n'];
$completed=(int)one("SELECT COUNT(*) AS n FROM tickets WHERE trang_thai = 'completed'".$own,$params)['n'];
$announcements=rows("SELECT a.*,d.ten_phong_ban,u.ho_ten FROM announcements a LEFT JOIN departments d ON d.id=a.phong_ban_id JOIN users u ON u.id=a.tao_boi WHERE a.pham_vi='company' OR a.phong_ban_id=? ORDER BY a.ngay_tao DESC,a.id DESC LIMIT 4",[user()['phong_ban_id']]);
$tickets=rows('SELECT * FROM tickets WHERE 1=1'.$own.' ORDER BY ngay_cap_nhat DESC,id DESC LIMIT 5',$params);
$people=(int)one("SELECT COUNT(*) AS n FROM users WHERE trang_thai='active'")['n'];
render('dashboard',compact('open','completed','announcements','tickets','people')+['title'=>'Tổng quan']);
