<?php
/**
 * Fantasy foundation static regression guard (no DB, no browser, no writes).
 * PHP 8.0+ compatible. Visual fidelity must still be checked in browser.
 */
$root = dirname(__DIR__);
$check = static function ($ok, $label) {
    if (!$ok) {
        fwrite(STDERR, 'FAIL: ' . $label . PHP_EOL);
        exit(1);
    }
    echo 'PASS: ' . $label . PHP_EOL;
};
$read = static function ($path) use ($root, $check) {
    $full = $root . '/' . $path;
    $content = is_file($full) ? file_get_contents($full) : false;
    $check(is_string($content) && $content !== '', 'readable ' . $path);
    return $content;
};
$css = $read('assets/css/fantasy-foundation.css');
$js = $read('assets/js/fantasy-foundation.js');
$uiCss = $read('assets/css/ui-system.css');
$home = $read('app/pages/index.php');
$landing = $read('assets/css/landing.css');
$homeCss = $read('assets/css/home-layout.css');
foreach (['--kpf-gradient:', '--kpf-space-', '--kpf-radius-', '--kpf-shadow', '.kpf-hero', '.kpf-card', '.kpf-btn', '.kpf-input',
          '.kpf-table', '.kpf-badge', '.kpf-tab', '.kpf-stat', '.kpf-portal', '.kpf-admin-panel', '.kpf-modal', '.kpf-toast', 'prefers-reduced-motion'] as $token) {
    $check(strpos($css, $token) !== false, 'foundation CSS provides ' . $token);
}
foreach (['window.KpFantasy', 'KpUI.toast', 'data-kpf-tabs', 'data-kpf-nav-toggle', 'data-kpf-modal-open', 'data-kpf-reveal', 'data-label'] as $token) {
    $check(strpos($js, $token) !== false, 'foundation JS provides ' . $token);
}
$check(strpos($uiCss, 'fantasy-foundation.css') !== false, 'existing UI stylesheet imports foundation');
foreach (['app/includes/public_landing.php', 'app/includes/player_portal.php', 'app/topup/_layout.php',
          'app/includes/admin_nav.php', 'app/pages/login.php', 'app/pages/register.php',
          'app/pages/forgot-password.php', 'app/admin/login.php'] as $template) {
    $check(strpos($read($template), '/assets/js/fantasy-foundation.js') !== false, 'JS enabled in ' . $template);
}
foreach (['assets/img/fantasy-world.webp', 'assets/img/treasure-chest-fantasy.webp'] as $asset) {
    $drawing = $read($asset);
    $check(substr($drawing, 0, 4) === 'RIFF' && substr($drawing, 8, 4) === 'WEBP',
        'generated artwork is WEBP ' . $asset);
}
$siteLogo = $read('assets/img/kpah-logo-main.webp');
$check(substr($siteLogo, 0, 4) === 'RIFF' && substr($siteLogo, 8, 4) === 'WEBP',
    'generated site logo is WEBP');
foreach (['app/includes/public_landing.php', 'app/pages/login.php', 'app/pages/register.php',
          'app/pages/forgot-password.php'] as $template) {
    $check(strpos($read($template), 'kpah-logo-main.webp') !== false,
        'shared WEBP logo used in ' . $template);
}
foreach (['Khởi đầu hành trình', 'Kho báu phiêu lưu', 'Đi cùng đồng đội'] as $label) {
    $check(strpos($home, $label) !== false, 'quest feature ' . $label);
}
foreach (['Top Sức mạnh', 'Nhân vật nổi bật', 'Khám phá KPAH'] as $label) {
    $check(strpos($home, $label) === false, 'removed legacy home section ' . $label);
}
$check(strpos($home, 'kp_rankings_fetch') !== false &&
       strpos($home, 'kp_giftcode_public_list') !== false &&
       strpos($home, 'SELECT COUNT(*) FROM team_user') !== false,
       'public dynamic database sources kept');
$check(strpos($home, 'kpah-logo-main.webp') !== false &&
       strpos($homeCss, 'home-hero-anime.webp') !== false &&
       strpos($css, 'fantasy-world.webp') !== false, 'illustrated assets are available for home and shared components');
echo 'Fantasy foundation static regression passed.' . PHP_EOL;
