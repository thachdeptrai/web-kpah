<?php
/** UI-only assertions for fantasy-styled public routes; requires no database. */
$root=dirname(__DIR__);
function theme_assert($ok, $label) {
    if (!$ok) { fwrite(STDERR, "FAIL: ".$label.PHP_EOL); exit(1); }
    echo "PASS: ".$label.PHP_EOL;
}
function theme_read($path) {
    global $root;
    $data=@file_get_contents($root.'/'.$path);
    theme_assert(is_string($data), 'read '.$path);
    return $data;
}
$shell=theme_read('app/includes/public_landing.php');
$css=theme_read('assets/css/public-pages-v2.css');
$home=theme_read('app/pages/index.php');
$homeCss=theme_read('assets/css/home-layout.css');
theme_assert(strpos($shell,"' kp-inner-page'")!==false,'separate inner page scope');
theme_assert(strpos($shell,'/assets/css/public-pages-v2.css?v=')!==false,'shared public CSS in shell');
theme_assert(strpos($shell,'/assets/css/home-layout.css?v=')!==false,'home-only CSS still loaded');
theme_assert(strpos($shell,'treasure-chest-fantasy.svg')!==false,'subpage hero supports detailed treasure');
theme_assert(strpos($shell,"'about' =>")!==false &&
    strpos($shell,"'download' =>")!==false &&
    strpos($shell,"'giftcode' =>")!==false &&
    strpos($shell,"'news' =>")!==false &&
    strpos($shell,"'community' =>")!==false,'five illustrated hero variants');
theme_assert(strpos($css,'.kp-inner-page .lp-inner-hero')!==false,'new inner hero style');
theme_assert(strpos($css,'.kp-inner-page .lp-gift-card')!==false,'giftcode card redesigned');
theme_assert(strpos($css,'.kp-inner-page .lp-download-card')!==false,'download card redesigned');
theme_assert(strpos($css,'.kp-inner-page .lp-news-card')!==false,'news card redesigned');
theme_assert(strpos($css,'.kp-inner-page .lp-community-card')!==false,'community card redesigned');
theme_assert(strpos($css,'@media(max-width:600px)')!==false,'mobile subpage layout');
theme_assert(strpos($css,'prefers-reduced-motion')!==false,'motion accessibility');
theme_assert(strpos($home, 'treasure-chest-fantasy.svg')!==false &&
    strpos($homeCss, 'lp-treasure-illustration')!==false, 'home artwork CSS and HTML connected');
theme_assert(strpos($home, 'lp-quest-chest')===false,'emoji gift box removed');
$gift=theme_read('app/pages/giftcodes-public.php');
theme_assert(strpos($gift,'treasure-chest-fantasy.svg')!==false,'giftcode listing shows treasure illustration');
foreach(['kp_giftcode_public_list','data-gift-code','limit_use','giftcode','luongLock'] as $key) {
    theme_assert(strpos($gift,$key)!==false,'giftcode backend/copy contract preserved: '.$key);
}
$news=theme_read('app/pages/news-public.php');
theme_assert(strpos($news, 'kp_game_download_catalog')!==false &&
    strpos($news, 'kp_giftcode_public_list')!==false,'news real status still loaded');
$download=theme_read('app/pages/downloads-public.php');
theme_assert(strpos($download,'kp_game_download_catalog')!==false &&
    strpos($download,"'/tai-game/tep?nen-tang='")!==false,'download release URL preserved');
$community=theme_read('app/pages/community-public.php');
theme_assert(strpos($community,'kp_public_community_url()')!==false &&
    strpos($community,'kp_safe_url')!==false,'community URL validation unchanged');
echo "Public theme smoke passed.".PHP_EOL;
