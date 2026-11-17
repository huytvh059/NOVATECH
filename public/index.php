<?php
declare(strict_types=1);
// Front controller: chỉ thư mục public được công khai.
require __DIR__.'/../src/bootstrap.php';
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$method=$_SERVER['REQUEST_METHOD'];
if(!in_array($method,['GET','POST'],true)) { header('Allow: GET, POST'); abort_page(405,'Phương thức không được hỗ trợ.'); }
if($method==='POST') verify_csrf();
$error=null;
try {
    if($path==='/login') { require __DIR__.'/../src/pages/login.php'; exit; }
    if(!user()) redirect('/login');
    if($path==='/logout') {
        if($method!=='POST') abort_page(405,'Đăng xuất yêu cầu POST.');
        audit('logout','Đăng xuất'); destroy_session(); redirect('/login');
    }
    if(user()['must_change_password'] && $path!=='/password') redirect('/password');
    $routes=['/'=>'dashboard','/password'=>'password','/announcements'=>'announcements','/directory'=>'directory','/tickets'=>'tickets','/tickets/view'=>'ticket','/assets'=>'assets','/admin/users'=>'users','/admin/audit'=>'audit'];
    if(!isset($routes[$path])) abort_page(404,'Trang không tồn tại.');
    require __DIR__.'/../src/pages/'.$routes[$path].'.php';
} catch(InvalidArgumentException $ex) {
    if(isset($GLOBALS['pdo']) && $GLOBALS['pdo']->inTransaction()) $GLOBALS['pdo']->rollBack();
    http_response_code(422);
    render('message',['title'=>'Dữ liệu chưa hợp lệ','message'=>$ex->getMessage()]);
}
