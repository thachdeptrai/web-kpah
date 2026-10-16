<?php
/** Shared public fantasy shell. Page handlers must load bootstrap.php first. */

function kp_public_community_url(): string
{
    return 'https://zalo.me/g/zmyedl5en7sldmd9el1z';
}

function kp_public_page_start(string $title, string $active, string $description): void
{
    $loggedIn = !empty($_SESSION['kp_user_id']);
    $links = [
        'home' => ['/', '⌂', 'Trang chủ'],
        'about' => ['/gioi-thieu', '✦', 'Giới thiệu'],
        'download' => ['/tai-game', '▣', 'Tải game'],
        'giftcode' => ['/giftcode', '♢', 'Giftcode'],
        'news' => ['/tin-tuc', '◉', 'Tin tức'],
        'community' => ['/cong-dong', '♧', 'Cộng đồng'],
    ];
    ?><!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#dff2ff">
    <meta name="description" content="<?= kp_e($description) ?>">
    <meta name="robots" content="index,follow">
    <title><?= kp_e($title) ?> · NGHIỆN KPAH</title>
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&amp;display=swap">
    <link rel="stylesheet" href="/assets/css/landing.css?v=<?= (int) @filemtime(KP_ROOT.'/assets/css/landing.css') ?>">
    <link rel="stylesheet" href="/assets/css/site-responsive.css?v=<?= (int) @filemtime(KP_ROOT.'/assets/css/site-responsive.css') ?>">
    <link rel="stylesheet" href="/assets/css/ui-system.css?v=<?= (int) @filemtime(KP_ROOT.'/assets/css/ui-system.css') ?>">
</head>
<body class="landing-page public-inner-page">
<a class="lp-skip" href="#noi-dung">Đi đến nội dung chính</a>
<header class="lp-header" data-landing-header>
    <div class="lp-container lp-nav">
        <a class="lp-brand" href="/" aria-label="NGHIỆN KPAH — Trang chủ"><span class="lp-brand-mark"><img src="/assets/img/emblem.svg" width="52" height="52" alt=""></span><span><strong>NGHIỆN KPAH</strong><small>THẾ GIỚI PHIÊU LƯU</small></span></a>
        <button class="lp-menu-toggle" type="button" aria-expanded="false" aria-controls="lp-menu" data-landing-menu><span></span><span></span><span></span><b class="lp-sr-only">Mở menu</b></button>
        <nav class="lp-links" id="lp-menu" aria-label="Điều hướng chính">
            <?php foreach ($links as $key => $link): ?><a<?= $active === $key ? ' class="is-active" aria-current="page"' : '' ?> href="<?= kp_e($link[0]) ?>"><span><?= kp_e($link[1]) ?></span> <?= kp_e($link[2]) ?></a><?php endforeach; ?>
        </nav>
        <div class="lp-nav-actions"><a class="lp-download-nav" href="/tai-game">▣ <span>Tải game</span></a><a class="<?= $loggedIn ? 'lp-nav-account' : 'lp-nav-login' ?>" href="<?= $loggedIn ? '/tai-khoan' : '/dang-nhap' ?>"><?= $loggedIn ? '<span>◎</span>Tài khoản' : 'Đăng nhập' ?></a></div>
    </div>
</header>
<main id="noi-dung">
<?php
}

function kp_public_page_hero(string $kicker, string $title, string $copy, string $symbol = '✦'): void
{
    ?><section class="lp-inner-hero"><div class="lp-inner-orb orb-one"></div><div class="lp-inner-orb orb-two"></div><div class="lp-container lp-inner-hero-grid"><div data-landing-reveal><a class="lp-inner-back" href="/">← Trang chủ</a><span class="lp-section-kicker"><?= kp_e($kicker) ?></span><h1><?= $title ?></h1><p><?= kp_e($copy) ?></p></div><div class="lp-inner-emblem" aria-hidden="true"><span><?= kp_e($symbol) ?></span><img src="/assets/img/emblem.svg" width="170" height="170" alt=""></div></div></section><?php
}

function kp_public_page_end(): void
{
    ?><section class="lp-inner-cta"><div class="lp-container"><div><span class="lp-section-kicker">SẴN SÀNG PHIÊU LƯU?</span><h2>Vào game và viết tiếp <em>hành trình của bạn</em></h2></div><div><a class="lp-button lp-button-primary" href="/tai-game">Tải game ngay</a><a class="lp-button lp-button-glass" href="/dang-ky">Tạo tài khoản</a></div></div></section>
</main>
<footer class="lp-footer"><div class="lp-container lp-footer-main"><a class="lp-brand" href="/"><span class="lp-brand-mark"><img src="/assets/img/emblem.svg" width="52" height="52" alt=""></span><span><strong>NGHIỆN KPAH</strong><small>THẾ GIỚI PHIÊU LƯU</small></span></a><nav aria-label="Liên kết cuối trang"><a href="/gioi-thieu">Giới thiệu</a><a href="/tai-game">Tải game</a><a href="/giftcode">Giftcode</a><a href="/tin-tuc">Tin tức</a><a href="/cong-dong">Cộng đồng</a></nav></div><div class="lp-container lp-footer-bottom"><span>© <?= date('Y') ?> NGHIỆN KPAH · Cổng thông tin cộng đồng người chơi</span><a href="#noi-dung">Lên đầu trang ↑</a></div></footer>
<script src="/assets/js/giftcodes.js?v=<?= (int) @filemtime(KP_ROOT.'/assets/js/giftcodes.js') ?>" defer></script>
<script src="/assets/js/landing.js?v=<?= (int) @filemtime(KP_ROOT.'/assets/js/landing.js') ?>" defer></script>
<script src="/assets/js/ui-system.js?v=<?= (int) @filemtime(KP_ROOT.'/assets/js/ui-system.js') ?>" defer></script>
</body></html><?php
}
