<?php
if (!defined('KP_ROOT')) { http_response_code(403); exit; }
require_once __DIR__.'/../includes/admin_nav.php';
function kp_topup_page_start($title, $active, $username = '', $admin = false) {
    kp_send_security_headers(true);
    header('Content-Type: text/html; charset=utf-8');
    $home = $admin ? '/quan-tri' : '/tai-khoan';
    $nav = $admin
        ? [['/quan-tri/nap','Tổng quan','index'],['/quan-tri/ty-le','Tỷ lệ quy đổi','rates'],['/quan-tri/cong-tai-nguyen','Cộng tài nguyên','grant'],['/quan-tri/doi-soat','Đối soát','orders'],['/quan-tri/diem-tich-luy','Point & đổi quà','points']]
        : [['/nap','Tổng quan','index'],['/nap-the','Nạp thẻ','card'],['/lich-su-nap','Lịch sử','history'],['/top-nap','Top nạp','top'],['/nap-ngan-hang','Ngân hàng','bank'],['/diem-tich-luy','Point & đổi quà','points'],['/vi-vnd','Ví VNĐ & đổi mốc','wallet']];
    ?>
<!doctype html>
<html lang="vi"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#10131e"><meta name="robots" content="noindex,nofollow">
<title><?= kp_e($title) ?> · KPAH</title>
<link rel="stylesheet" href="/assets/css/topup.css?v=<?= (int) @filemtime(KP_ROOT . '/assets/css/topup.css') ?>">
<?php if($admin):?><link rel="stylesheet" href="/assets/css/admin-nav.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin-nav.css') ?>"><?php endif;?>
</head>
<body class="tp-page<?= $admin ? ' tp-admin' : '' ?>">
<div class="tp-shell">
<aside class="tp-sidebar" aria-label="Điều hướng">
  <a class="tp-brand" href="<?= kp_e($home) ?>"><span class="tp-brand-mark">K<span>.</span></span><span><b>KPAH</b><small><?= $admin ? 'Quản trị nạp' : 'Khu nạp tài nguyên' ?></small></span></a>
  <div class="tp-nav-label"><?= $admin ? 'QUẢN TRỊ' : 'TÀI KHOẢN' ?></div>
  <nav class="tp-nav">
    <?php foreach($nav as $item): ?>
      <a class="tp-nav-link<?= $active===$item[2] ? ' is-active' : '' ?>" href="<?= kp_e($item[0]) ?>"<?= $active===$item[2] ? ' aria-current="page"' : '' ?>><span class="tp-nav-dot">✧</span><?= kp_e($item[1]) ?><span class="tp-nav-arrow">↗</span></a>
    <?php endforeach; ?>
  </nav>
  <a class="tp-back" href="<?= kp_e($home) ?>">← <?= $admin ? 'Về admin' : 'Về tài khoản' ?></a>
  <p class="tp-aside-note">KPAH · ĐÚNG TÀI KHOẢN, ĐÚNG TÀI NGUYÊN</p>
</aside>
<main class="tp-content">
<header class="tp-header"><div class="tp-breadcrumb">KPAH <span>/</span> <?= $admin ? 'QUẢN TRỊ' : 'NẠP TÀI NGUYÊN' ?> <span>/</span> <?= kp_e($title) ?></div><div class="tp-user"><i></i><?= kp_e($username) ?></div></header>
<?php if($admin) kp_admin_nav($active,'admin'); ?>
<?php
}
function kp_topup_page_end() { ?>
<footer class="tp-footer"><span>KPAH · TÀI NGUYÊN TRONG GAME</span><span>Kiểm tra kỹ thông tin trước khi xác nhận</span></footer>
</main></div></body></html>
<?php }
