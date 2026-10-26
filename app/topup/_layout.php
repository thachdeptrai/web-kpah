<?php
if (!defined('KP_ROOT')) { http_response_code(403); exit; }
require_once __DIR__.'/../includes/admin_nav.php';
require_once __DIR__.'/../includes/player_portal.php';
require_once __DIR__.'/../includes/topup_core.php';
function kp_topup_art_icon(string $name): string {
    static $allowed = ['dashboard','user','gamepad','trophy','wallet','gift','bell','receipt','shield','logout','points','bank'];
    if (!in_array($name,$allowed,true)) return '';
    return kp_player_icon_svg($name);
}
function kp_topup_page_start($title, $active, $username = '', $admin = false) {
    kp_send_security_headers(true);
    header('Content-Type: text/html; charset=utf-8');
    $home = $admin ? '/quan-tri' : '/tai-khoan';
    $nav = $admin
        ? [['/quan-tri/nap','Tổng quan','index'],['/quan-tri/ty-le','Tỷ lệ quy đổi','rates'],['/quan-tri/cong-tai-nguyen','Cộng tài nguyên','grant'],['/quan-tri/cong-vnd','Cộng VNĐ & tổng nạp','wallet-credit'],['/quan-tri/doi-soat','Đối soát','orders'],['/quan-tri/diem-tich-luy','Point & đổi quà','points']]
        : [['/nap','Tổng quan','index'],['/nap-ngan-hang','Ngân hàng','bank'],['/nap-the','Nạp thẻ','card'],['/vi-vnd','Ví VNĐ & đổi mốc','wallet'],['/diem-tich-luy','Point & đổi quà','points'],['/lich-su-nap','Lịch sử','history'],['/lich-su-nhan-diem-nong-dan','Lịch sử nhận điểm','farmer-history'],['/top-nap','Top nạp','top']];
    if(!$admin&&!kp_topnap_enabled())$nav=array_values(array_filter($nav,static function($item){return $item[2]!=='top';}));
    ?>
<!doctype html>
<html lang="vi"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#eef7ff"><meta name="robots" content="noindex,nofollow">
<title><?= kp_e($title) ?> · KPAH</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&amp;family=Baloo+2:wght@600;700;800&amp;display=swap">
<link rel="stylesheet" href="/assets/css/user-center.css?v=1">
<link rel="stylesheet" href="/assets/css/topup.css?v=<?= (int) @filemtime(KP_ROOT . '/assets/css/topup.css') ?>">
<?php if($admin):?><link rel="stylesheet" href="/assets/css/admin-nav.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin-nav.css') ?>"><?php endif;?>
<link rel="stylesheet" href="/assets/css/site-responsive.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/site-responsive.css') ?>">
<link rel="stylesheet" href="/assets/css/ui-system.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/ui-system.css') ?>">
<?php if(!$admin):?><link rel="stylesheet" href="/assets/css/topup-player-light.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/topup-player-light.css') ?>"><?php endif;?>
<link rel="stylesheet" href="/assets/css/brand-system.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/brand-system.css') ?>">
<?php if($admin):?><link rel="stylesheet" href="/assets/css/admin-theme.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin-theme.css') ?>"><?php endif;?>
</head>
<body class="tp-page<?= $admin ? ' tp-admin' : '' ?>">
<div class="tp-shell">
<aside class="tp-sidebar" aria-label="Điều hướng">
  <a class="tp-brand" href="<?= kp_e($home) ?>"><?php if($admin): ?><span class="tp-brand-mark">K<span>.</span></span><?php else: ?><span class="tp-brand-mark tp-brand-game-logo"><img src="/assets/img/kpah-logo-main.webp" width="1280" height="512" alt="" loading="eager"></span><?php endif; ?><span><b>KPAH</b><small><?= $admin ? 'Quản trị nạp' : 'Khu nạp tài nguyên' ?></small></span></a>
  <div class="tp-nav-label"><?= $admin ? 'QUẢN TRỊ' : 'TÀI KHOẢN' ?></div>
  <nav class="tp-nav">
    <?php foreach($nav as $item): ?>
      <a class="tp-nav-link<?= $active===$item[2] ? ' is-active' : '' ?>" href="<?= kp_e($item[0]) ?>"<?= $active===$item[2] ? ' aria-current="page"' : '' ?>><?php if($admin): ?><span class="tp-nav-dot">✧</span><?php else: ?><span class="tp-nav-art"><?= kp_topup_art_icon(['index'=>'wallet','card'=>'receipt','history'=>'receipt','top'=>'trophy','bank'=>'bank','points'=>'points','wallet'=>'wallet'][$item[2]]??'wallet') ?></span><?php endif; ?><?= kp_e($item[1]) ?><span class="tp-nav-arrow">↗</span></a>
    <?php endforeach; ?>
  </nav>
  <a class="tp-back" href="<?= kp_e($home) ?>">← <?= $admin ? 'Về admin' : 'Về tài khoản' ?></a>
  <p class="tp-aside-note">KPAH · ĐÚNG TÀI KHOẢN, ĐÚNG TÀI NGUYÊN</p>
</aside>
<main class="tp-content">
<header class="tp-header"><div class="tp-breadcrumb"><?php if($admin): ?><img class="kp-topup-admin-emblem" src="/assets/img/emblem.webp" width="36" height="36" alt="">NGHIỆN KPAH<?php else: ?>KPAH<?php endif; ?> <span>/</span> <?= $admin ? 'QUẢN TRỊ' : 'NẠP TÀI NGUYÊN' ?> <span>/</span> <?= kp_e($title) ?></div><div class="tp-user"><i></i><?= kp_e($username) ?></div></header>
<?php if($admin) kp_admin_nav($active,'admin'); ?>
<?php
}
function kp_topup_page_end() { ?>
<footer class="tp-footer"><span>KPAH · TÀI NGUYÊN TRONG GAME</span><span>Kiểm tra kỹ thông tin trước khi xác nhận</span></footer>
</main></div>
<?php if(!empty($_SESSION['kp_toast']) && is_array($_SESSION['kp_toast'])): $toast=$_SESSION['kp_toast']; unset($_SESSION['kp_toast']); ?>
<div hidden data-ui-toast="<?=kp_e($toast['type']??'success')?>" data-ui-toast-title="<?=kp_e($toast['title']??'')?>" data-ui-toast-message="<?=kp_e($toast['message']??'')?>"></div>
<?php endif;?>
<script src="/assets/js/ui-system.js?v=<?= (int)@filemtime(KP_ROOT.'/assets/js/ui-system.js') ?>" defer></script>
<script src="/assets/js/fantasy-foundation.js?v=<?= (int)@filemtime(KP_ROOT.'/assets/js/fantasy-foundation.js') ?>" defer></script>
</body></html>
<?php }
