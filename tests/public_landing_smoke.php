<?php
$root=dirname(__DIR__);
function landing_assert(bool $ok,string $message): void {if(!$ok){fwrite(STDERR,'FAIL: '.$message.PHP_EOL);exit(1);}echo 'PASS: '.$message.PHP_EOL;}
function landing_read(string $path): string {$value=@file_get_contents(dirname(__DIR__).'/'.$path);if(!is_string($value))throw new RuntimeException('Missing '.$path);return $value;}

$routes=require $root.'/config/routes.php';
$publicPages=[
    '/gioi-thieu'=>'app/pages/about.php',
    '/tai-game'=>'app/pages/downloads-public.php',
    '/giftcode'=>'app/pages/giftcodes-public.php',
    '/tin-tuc'=>'app/pages/news-public.php',
    '/cong-dong'=>'app/pages/community-public.php',
];
foreach($publicPages as $url=>$file){
    landing_assert(isset($routes['routes'][$url]),'public route exists '.$url);
    landing_assert($routes['routes'][$url]['file']===$file,'public route handler '.$url);
    $source=landing_read($file);
    landing_assert(strpos($source,'public_landing.php')!==false,'shared shell used by '.$file);
    landing_assert(strpos($source,'kp_public_page_start')!==false && strpos($source,'kp_public_page_end')!==false,'complete public layout in '.$file);
}
$home=landing_read('app/pages/index.php');
foreach(array_keys($publicPages) as $url)landing_assert(strpos($home,'href="'.$url.'"')!==false,'home links to '.$url);
foreach(['id="gioi-thieu"','id="tai-game"','id="giftcode"','id="tin-tuc"','id="cong-dong"'] as $oldSection)landing_assert(strpos($home,$oldSection)===false,'long home section removed '.$oldSection);
landing_assert(strpos($home,'SELECT COUNT(*) FROM team_user')!==false && strpos($home,'kp_rankings_fetch')!==false && strpos($home,'kp_giftcode_public_list')!==false,'home dynamic data retained');
$gift=landing_read('app/pages/giftcodes-public.php');
landing_assert(strpos($gift,'data-gift-code')!==false && strpos($gift,'kp_giftcode_public_list')!==false,'giftcode copy UI and live query retained');
$downloads=landing_read('app/pages/downloads-public.php');
landing_assert(strpos($downloads,'kp_game_download_catalog')!==false && strpos($downloads,"'/tai-game/tep?nen-tang='")!==false,'download catalog and protected file route retained');
$shell=landing_read('app/includes/public_landing.php');
landing_assert(strpos($shell,'aria-current="page"')!==false,'active navigation is accessible');
landing_assert(strpos($shell,'function kp_public_nav_icon')!==false,'six public routes use local illustrated navigation icons');
landing_assert(strpos($shell,'assets/css/nav-modern.css')!==false,'modern navigation stylesheet is wired into shared header');
landing_assert(strpos($shell,'lp-nav-label')!==false,'navigation labels remain visible and accessible');
$navigation=landing_read('assets/css/nav-modern.css');
foreach (['home','about','download','giftcode','news','community'] as $key) {
    landing_assert(strpos($shell,"'".$key."'")!==false,'navigation destination retained '.$key);
}
landing_assert(strpos($navigation,'width:39px;height:39px')!==false,'desktop navigation icon container is enlarged');
landing_assert(strpos($navigation,'max-width:1240px')!==false && strpos($navigation,'max-width:430px')!==false,'tablet and narrow phone navigation layouts exist');
echo "PASS: public landing page split\n";
