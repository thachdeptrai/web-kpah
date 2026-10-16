<?php
/**
 * Homepage no-overlap source guard, read-only and independent of game DB.
 * Geometry still requires visual verification at browser viewport sizes.
 */
$root = dirname(__DIR__);
function home_check($ok, $label) {
    if (!$ok) { fwrite(STDERR, 'FAIL: ' . $label . PHP_EOL); exit(1); }
    echo 'PASS: ' . $label . PHP_EOL;
}
function home_source($name) {
    global $root;
    $source = @file_get_contents($root . '/' . $name);
    home_check(is_string($source), 'file exists: ' . $name);
    return $source;
}
$layout = home_source('assets/css/home-layout.css');
$landing = home_source('assets/css/landing.css');
$shell = home_source('app/includes/public_landing.php');
$home = home_source('app/pages/index.php');
$routes = home_source('config/routes.php');

home_check(strpos($shell, "'home' ? ' kp-home-page'") !== false,
    'home-specific body class added only for public homepage');
home_check(strpos($shell, "if (\$active === 'home')") !== false &&
    strpos($shell, '/assets/css/home-layout.css?v=') !== false,
    'homepage loads versioned stylesheet conditionally');
home_check(strpos($shell, '/assets/css/ui-system.css?v=') < strpos($shell, '/assets/css/home-layout.css?v='),
    'home CSS loads after foundation');
home_check(strpos($layout, '.kp-home-page .lp-header') !== false &&
    strpos($layout, 'position:sticky') !== false,
    'header participates in normal flow instead of covering hero');
home_check(strpos($layout, '.kp-home-page .lp-home-hero') !== false &&
    strpos($layout, 'height:auto') !== false,
    'hero grows with content');
home_check(strpos($layout, '.kp-home-page .lp-home-stage::after{content:none') !== false,
    'extra artwork gradient layer removed');
home_check(strpos($layout, 'mask-image:none') !== false,
    'old masked artwork composite removed');
home_check(strpos($layout, '.kp-home-page .lp-menu-toggle{display:block') !== false,
    'tablet/mobile uses collapsed menu');
home_check(strpos($layout, '.kp-home-page .lp-links.is-open') !== false &&
    strpos($layout, 'pointer-events:auto') !== false,
    'collapsed menu retains click behavior');
home_check(strpos($layout, '.kp-home-page .lp-quest-grid{grid-template-columns:1fr') !== false,
    'mobile cards form a single column');
home_check(strpos($layout, '.kp-home-page .lp-home-stats{grid-template-columns:repeat(2,minmax(0,1fr))') !== false,
    'mobile stats become two columns');
home_check(strpos($layout, '.kp-home-page .lp-home-destinations{grid-template-columns:repeat(2,minmax(0,1fr))') !== false,
    'mobile quick links become two columns');
home_check(strpos($landing, '2026 fantasy quest home: deliberately after legacy landing rules.') === false,
    'conflicting appended homepage overrides have been removed');
home_check(strpos($home, 'lp-home-summary') !== false &&
    strpos($home, 'lp-quest-grid') !== false &&
    strpos($home, 'lp-home-destinations') !== false,
    'current homepage modules retained');
home_check(strpos($home, 'kp_rankings_fetch') !== false &&
    strpos($home, 'kp_giftcode_public_list') !== false,
    'data sources remain intact');
home_check(strpos($routes, "'/' => ['file' => 'app/pages/index.php'") !== false,
    'root routing preserved');
echo 'Homepage layout smoke passed.' . PHP_EOL;
