<?php
/** Shared public fantasy shell. Page handlers must load bootstrap.php first. */

function kp_public_community_url(): string
{
    return 'https://zalo.me/g/zmyedl5en7sldmd9el1z';
}

/** Decorative, local icons for the six public navigation destinations. */
function kp_public_nav_icon(string $key): string
{
    if ($key === 'community') {
        return '<span class="lp-nav-icon" aria-hidden="true"><img src="/assets/img/zalo-official.svg" width="26" height="26" alt="" decoding="async"></span>';
    }
    $paths = [
        'home' => '<path d="m3 10 9-7 9 7v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 21v-7h6v7"/>',
        'about' => '<path d="m12 2 2.25 6.75L21 11l-6.75 2.25L12 20l-2.25-6.75L3 11l6.75-2.25z"/><path d="m19.5 18 .5 1.5 1.5.5-1.5.5-.5 1.5-.5-1.5-1.5-.5 1.5-.5z"/>',
        'download' => '<path d="M12 3v12m0 0 4.5-4.5M12 15l-4.5-4.5"/><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/>',
        'giftcode' => '<rect x="3" y="9" width="18" height="12" rx="2"/><path d="M12 9v12M3 13h18"/><path d="M12 9H8.5A2.5 2.5 0 1 1 11 6.5L12 9Zm0 0h3.5A2.5 2.5 0 1 0 13 6.5L12 9Z"/>',
        'news' => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 8h8M8 12h8M8 16h4"/>',
    ];
    $shape = $paths[$key] ?? $paths['home'];
    return '<span class="lp-nav-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.95" stroke-linecap="round" stroke-linejoin="round" focusable="false">' . $shape . '</svg></span>';
}

function kp_public_page_start(string $title, string $active, string $description): void
{
    $loggedIn = !empty($_SESSION['kp_user_id']);
    $GLOBALS['kp_public_active'] = $active;
    $links = [
        'home' => ['/', 'Trang chủ'],
        'about' => ['/gioi-thieu', 'Giới thiệu'],
        'giftcode' => ['/giftcode', 'Giftcode'],
        'news' => ['/tin-tuc', 'Tin tức'],
        'community' => ['/cong-dong', 'Cộng đồng'],
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
    <link rel="icon" type="image/webp" href="/assets/img/favicon.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800;900&amp;family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&amp;display=swap">
    <link rel="stylesheet" href="/assets/css/landing.css?v=<?= (int) @filemtime(KP_ROOT.'/assets/css/landing.css') ?>">
    <link rel="stylesheet" href="/assets/css/site-responsive.css?v=<?= (int) @filemtime(KP_ROOT.'/assets/css/site-responsive.css') ?>">
    <link rel="stylesheet" href="/assets/css/ui-system.css?v=<?= (int) @filemtime(KP_ROOT.'/assets/css/ui-system.css') ?>">
    <link rel="stylesheet" href="/assets/css/public-pages-v2.css?v=<?= (int) @filemtime(KP_ROOT.'/assets/css/public-pages-v2.css') ?>">
    <?php if ($active === 'home'): ?><link rel="stylesheet" href="/assets/css/home-layout.css?v=<?= (int) @filemtime(KP_ROOT.'/assets/css/home-layout.css') ?>"><?php endif; ?>
    <link rel="stylesheet" href="/assets/css/nav-modern.css?v=<?= (int) @filemtime(KP_ROOT.'/assets/css/nav-modern.css') ?>">
</head>
<body class="landing-page public-inner-page<?= $active === 'home' ? ' kp-home-page' : ' kp-inner-page' ?>">
<a class="lp-skip" href="#noi-dung">Đi đến nội dung chính</a>
<header class="lp-header" data-landing-header>
    <div class="lp-container lp-nav">
        <a class="lp-brand" href="/" aria-label="NGHIỆN KPAH — Trang chủ"><span class="lp-brand-mark"><img src="/assets/img/kpah-logo-main.webp" width="1280" height="512" alt="NGHIỆN KPAH"></span><span><strong>NGHIỆN KPAH</strong><small>THẾ GIỚI PHIÊU LƯU</small></span></a>
        <button class="lp-menu-toggle" type="button" aria-expanded="false" aria-controls="lp-menu" data-landing-menu><span></span><span></span><span></span><b class="lp-sr-only">Mở menu</b></button>
        <nav class="lp-links" id="lp-menu" aria-label="Điều hướng chính">
            <?php foreach ($links as $key => $link): ?><a data-nav="<?= kp_e($key) ?>"<?= $active === $key ? ' class="is-active" aria-current="page"' : '' ?> href="<?= kp_e($link[0]) ?>"><?= kp_public_nav_icon($key) ?><span class="lp-nav-label"><?= kp_e($link[1]) ?></span></a><?php endforeach; ?>
        </nav>
        <div class="lp-nav-actions"><a class="lp-download-nav<?= $active === 'download' ? ' is-active' : '' ?>" href="/tai-game"<?= $active === 'download' ? ' aria-current="page"' : '' ?>><?= kp_public_nav_icon('download') ?><span class="lp-download-label">Tải game</span></a><a class="<?= $loggedIn ? 'lp-nav-account' : 'lp-nav-login' ?>" href="<?= $loggedIn ? '/tai-khoan' : '/dang-nhap' ?>"><?= $loggedIn ? '<span>◎</span>Tài khoản' : 'Đăng nhập' ?></a></div>
    </div>
</header>
<main id="noi-dung">
<?php
}

function kp_public_page_hero(string $kicker, string $title, string $copy, string $symbol = '✦'): void
{
    // Presentation-only artwork. The page-specific handler continues to provide
    // all real content, URLs and data; do not change its API contract.
    global $kp_public_active;
    $artByPage = [
        'about' => ['/assets/img/rank-archer.webp', 'archer', 560, 700],
        'download' => ['/assets/img/rank-guardian.webp?v=20261017-2', 'guardian', 800, 800],
        'giftcode' => ['/assets/img/treasure-chest-fantasy.webp', 'gift', 800, 731],
        'news' => ['/assets/img/fantasy-world.webp', 'news', 1600, 600],
        'community' => ['/assets/img/rank-sorcerer.webp', 'sorcerer', 560, 700],
    ];
    $art = $artByPage[$kp_public_active ?? ''] ?? ['/assets/img/kpah-logo-main.webp', 'logo', 1280, 512];
    ?><section class="lp-inner-hero" aria-label="<?= kp_e($kicker) ?>">
        <div class="lp-container lp-inner-hero-grid">
            <div data-landing-reveal>
                <a class="lp-inner-back" href="/">← Trở về trang chủ</a>
                <span class="lp-section-kicker"><?= kp_e($kicker) ?></span>
                <h1><?= $title ?></h1>
                <p><?= kp_e($copy) ?></p>
            </div>
            <div class="lp-inner-emblem lp-inner-emblem--<?= kp_e($art[1]) ?>" aria-hidden="true">
                <img src="<?= kp_e($art[0]) ?>" width="<?= (int) $art[2] ?>" height="<?= (int) $art[3] ?>" alt="" loading="eager" decoding="async">
            </div>
        </div>
        <nav class="lp-page-tabs lp-container" aria-label="Các trang người chơi">
            <?php foreach (['about'=>['/gioi-thieu','Giới thiệu'],'download'=>['/tai-game','Tải game'],'giftcode'=>['/giftcode','Giftcode'],'news'=>['/tin-tuc','Tin tức'],'community'=>['/cong-dong','Cộng đồng']] as $key=>$tab): ?>
            <a href="<?= kp_e($tab[0]) ?>"<?= ($kp_public_active ?? '') === $key ? ' class="is-active" aria-current="page"' : '' ?>><?= kp_e($tab[1]) ?></a>
            <?php endforeach; ?>
        </nav>
    </section><?php
}

function kp_public_page_end(): void
{
    ?><section class="lp-inner-cta"><div class="lp-container"><div><span class="lp-section-kicker">NGHIỆN KPAH</span><h2>Tải game & <em>tạo tài khoản</em></h2></div><div><a class="lp-button lp-button-primary" href="/tai-game">Tải game ngay</a><a class="lp-button lp-button-glass" href="/dang-ky">Tạo tài khoản</a></div></div></section>
</main>
<footer class="lp-footer"><div class="lp-container lp-footer-main"><a class="lp-brand" href="/"><span class="lp-brand-mark"><img src="/assets/img/kpah-logo-main.webp" width="1280" height="512" alt="NGHIỆN KPAH"></span><span><strong>NGHIỆN KPAH</strong><small>THẾ GIỚI PHIÊU LƯU</small></span></a><nav aria-label="Liên kết cuối trang"><a href="/gioi-thieu">Giới thiệu</a><a href="/tai-game">Tải game</a><a href="/giftcode">Giftcode</a><a href="/tin-tuc">Tin tức</a><a href="/cong-dong">Cộng đồng</a></nav></div><div class="lp-container lp-footer-bottom"><span>© <?= date('Y') ?> NGHIỆN KPAH · Cổng thông tin cộng đồng người chơi</span><a href="#noi-dung">Lên đầu trang ↑</a></div></footer>
<script src="/assets/js/giftcodes.js?v=<?= (int) @filemtime(KP_ROOT.'/assets/js/giftcodes.js') ?>" defer></script>
<script src="/assets/js/landing.js?v=<?= (int) @filemtime(KP_ROOT.'/assets/js/landing.js') ?>" defer></script>
<script src="/assets/js/ui-system.js?v=<?= (int) @filemtime(KP_ROOT.'/assets/js/ui-system.js') ?>" defer></script>
<script src="/assets/js/fantasy-foundation.js?v=<?= (int)@filemtime(KP_ROOT.'/assets/js/fantasy-foundation.js') ?>" defer></script>
</body></html><?php
}
