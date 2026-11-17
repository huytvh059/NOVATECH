<?php
declare(strict_types=1);
ini_set('display_errors','0'); ini_set('log_errors','1');
date_default_timezone_set('Asia/Ho_Chi_Minh');
require_once __DIR__.'/helpers.php';
set_exception_handler(function(Throwable $error): void {
    if(ob_get_level()) ob_end_clean();
    if(isset($GLOBALS['config'])) { try { if(db()->inTransaction()) db()->rollBack(); } catch(Throwable $ignored) {} }
    error_log((string)$error);
    http_response_code(503);
    echo '<!doctype html><html lang="vi"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dịch vụ tạm gián đoạn | NovaTech</title><link rel="stylesheet" href="/assets/index.css"><main class="error-page"><div class="card"><p class="eyebrow">NOVATECH PORTAL</p><h1>Dịch vụ tạm gián đoạn</h1><p>Không thể xử lý yêu cầu lúc này. Vui lòng thử lại sau hoặc liên hệ bộ phận IT.</p><a href="/">Thử lại</a></div></main></html>';
});
header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: DENY'); header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; font-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
header('Cache-Control: no-store');
$path=getenv('NOVATECH_CONFIG') ?: '/etc/novatech/config.php';
if(!is_file($path)) throw new RuntimeException('Thiếu cấu hình bên ngoài webroot.');
$GLOBALS['config']=require $path;
ini_set('error_log',$GLOBALS['config']['error_log']);
ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1');
session_name('novatech_session');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>(bool)$GLOBALS['config']['secure_cookie'],'httponly'=>true,'samesite'=>'Lax']);
session_start();
$GLOBALS['current_user']=null;
if(isset($_SESSION['uid'])) {
    $u=one('SELECT u.*,d.ten_phong_ban FROM users u JOIN departments d ON d.id=u.phong_ban_id WHERE u.id=?',[$_SESSION['uid']]);
    if(!$u || $u['trang_thai']!=='active' || (int)$u['session_version']!==($_SESSION['version']??0) || time()-($_SESSION['last_seen']??0)>1800) {
        $_SESSION=[]; session_regenerate_id(true);
    } else { $GLOBALS['current_user']=$u; $_SESSION['last_seen']=time(); }
}
