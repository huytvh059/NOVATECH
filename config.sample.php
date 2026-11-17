<?php
// Sao chép ra ngoài webroot. Không lưu secret trong repo.
return [
 'db' => ['host'=>'10.10.100.13','port'=>3306,'name'=>'novatech_db','user'=>'webapp','password'=>'<DB_PASSWORD>'],
 'secure_cookie'=>false,
 'error_log'=>'/var/log/novatech/php-error.log',
];
