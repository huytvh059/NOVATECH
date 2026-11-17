<?php
require_role(['it_admin']);
if($_SERVER['REQUEST_METHOD']!=='GET') abort_page(405,'Chức năng chỉ hỗ trợ GET.');
$username=search_value('username'); $action=search_value('action');
$list=rows('SELECT * FROM audit_logs WHERE (?=\'\' OR username=?) AND (?=\'\' OR hanh_dong=?) ORDER BY id DESC LIMIT 200',[$username,$username,$action,$action]);
render('audit',compact('list','username','action')+['title'=>'Nhật ký hoạt động']);
