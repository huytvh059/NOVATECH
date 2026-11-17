<?php
// Router chỉ dùng cho PHP development server, không dùng ở production.
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(preg_match('#^/assets/[a-zA-Z0-9_.-]+$#',$path) && is_file(__DIR__.$path)) return false;
require __DIR__.'/index.php';
