<!doctype html>
<html lang="vi">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="NovaTech Intranet Portal — thông báo nội bộ, danh bạ nhân viên, hỗ trợ kỹ thuật và quản lý hạ tầng."><title><?= e($title) ?> | NovaTech Portal</title><link rel="stylesheet" href="/assets/index.css"><script src="/assets/app.js" defer></script></head>
<body>
<?php if(user()): ?>
<a class="skip-link" href="#main-content">Đến nội dung chính</a>
<aside class="sidebar" id="sidebar">
<a class="brand" href="/"><span class="brand-mark">N</span><span>NovaTech<small>INTRANET PORTAL</small></span></a>
<p class="nav-label">KHÔNG GIAN LÀM VIỆC</p>
<nav aria-label="Điều hướng chính">
<?php $nav=[['/','Tổng quan','◈',null],['/announcements','Thông báo','▤',null],['/directory','Danh bạ nhân viên','▦',null],['/tickets','Trung tâm hỗ trợ','◎',null],['/assets','Tài sản & hạ tầng','▣','it_admin'],['/admin/users','Quản lý người dùng','♙','it_admin'],['/admin/audit','Nhật ký hoạt động','≡','it_admin']]; foreach($nav as [$href,$caption,$icon,$role]): if($role && user()['role']!==$role) continue; $active=($href==='/'?$path==='/':str_starts_with($path,$href)); ?>
<a id="nav-<?= e(str_replace('/','-',$href)?:'home') ?>" href="<?= e($href) ?>" class="nav-item <?= $active?'selected':'' ?>" <?= $active?'aria-current="page"':'' ?>><span aria-hidden="true"><?= e($icon) ?></span><?= e($caption) ?></a>
<?php endforeach ?>
</nav>
<div class="sidebar-bottom"><div class="workspace-tag"><span class="status-dot"></span> NovaTech · Nội bộ<small>Kết nối. Chia sẻ. Cùng phát triển.</small></div><a href="/password" class="profile"><span class="avatar"><?= e(mb_substr(user()['ho_ten'],0,1)) ?></span><span><?= e(user()['ho_ten']) ?><small><?= e(label(user()['role'])) ?></small></span></a><form method="post" action="/logout"><?php csrf_field() ?><button class="logout" id="logout-button">Đăng xuất <span aria-hidden="true">↗</span></button></form></div>
</aside>
<div class="app-shell"><header class="topbar"><button id="menu-toggle" class="menu-toggle" aria-controls="sidebar" aria-expanded="false" aria-label="Mở menu">☰</button><div class="breadcrumb">Không gian làm việc <span>/</span> <strong><?= e($title) ?></strong></div><div class="topbar-right"><span class="internal-tag">MẠNG NỘI BỘ</span><span class="topbar-date"><?= e(date('d.m.Y')) ?></span><a href="/password" class="avatar small" aria-label="Đổi mật khẩu"><?= e(mb_substr(user()['ho_ten'],0,1)) ?></a></div></header>
<main id="main-content" class="main-content"><header class="page-heading"><div><p class="eyebrow">NOVATECH WORKSPACE</p><h1><?= e($title) ?></h1></div><span class="page-caption"><?= e(user()['ten_phong_ban']) ?></span></header>
<?php if(isset($_SESSION['flash'])): ?><div class="notice success" role="status"><?= e($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif ?>
<?= $content ?>
<footer class="page-footer"><span>© <?= e(date('Y')) ?> NovaTech. Dành riêng cho nhân viên.</span><span>Intranet Portal · Giai đoạn 01</span></footer></main></div>
<?php else: ?><?= $content ?><?php endif ?>
</body></html>
