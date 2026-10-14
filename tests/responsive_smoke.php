<?php
/**
 * Static responsive/layout regression suite. Runs without a game DB or provider.
 * Live mobile/desktop rendering is additionally a manual verification step.
 */
$root=dirname(__DIR__);
function check_layout(bool $condition,string $description): void {
    if(!$condition){fwrite(STDERR,'FAIL: '.$description.PHP_EOL);exit(1);}
    echo 'PASS: '.$description.PHP_EOL;
}
function read_layout(string $path): string {
    global $root;
    $contents=@file_get_contents($root.'/'.$path);
    if(!is_string($contents))throw new RuntimeException('Missing file: '.$path);
    return $contents;
}
$templates=[
    'app/pages/index.php','app/pages/register.php','app/pages/login.php',
    'app/pages/forgot-password.php','app/pages/not-found.php',
    'app/includes/player_portal.php','app/topup/_layout.php',
    'app/admin/index.php','app/admin/login.php','app/admin/referrals.php',
    'app/admin/key-users.php'
];
foreach($templates as $template){
    $html=read_layout($template);
    check_layout(strpos($html,'/assets/css/site-responsive.css')!==false,
                 'responsive CSS included in '.$template);
}
$home=read_layout('app/pages/home.php');
$source=read_layout('app/includes/dashboard_data.php');
$layout=read_layout('assets/css/site-responsive.css');
$dashboardCss=read_layout('assets/css/dashboard.css');
check_layout(strpos(read_layout('app/includes/player_portal.php'),'/assets/css/dashboard.css')!==false,
             'dashboard-specific stylesheet is loaded through account shell');
foreach(['Ví VNĐ hiện có','Tổng đã nạp','Point tích lũy','Nhân vật của tôi',
         'Giao dịch','Bảo mật tài khoản','Danh vọng','Điểm PK'] as $section){
    check_layout(stripos($home,$section)!==false,'dashboard section '. $section);
}
check_layout(strpos($home,"kp_player_account()")!==false,'signed-in account guard retained');
check_layout(strpos($source,"WHERE user_id=?")!==false &&
             strpos($source,"WHERE w.userid=?")!==false &&
             strpos($source,"WHERE userid=?")!==false,
             'private account/character queries are scoped by user ID');
check_layout(strpos($source,"serial")!==false && strpos($home,'serial_end')===false,
             'card PIN/serial not output in private dashboard HTML');
check_layout(strpos($source,'UPDATE team_user')===false &&
             strpos($source,'INSERT INTO')===false,
             'dashboard queries do not modify wallet or total deposits');
check_layout(strpos($source,'catch(Throwable $e)')!==false,
             'missing optional tables/columns can degrade gracefully');
check_layout(strpos($layout,'calc(50% - 685px)')!==false,
             'desktop player shell has centered max width');
check_layout(strpos($layout,'inset:auto 0 0 0!important')!==false,
             'mobile account navigation moves to bottom');
check_layout(strpos($layout,'overflow-x:auto')!==false,
             'wide data tables are scrollable on narrow viewports');
foreach(['680px','440px'] as $width){
    check_layout(strpos($layout,'max-width:'.$width)!==false,
                 'shared stylesheet covers '.$width.' screen width');
}
check_layout(strpos($dashboardCss,'grid-template-columns:repeat(4,minmax(0,1fr))')!==false &&
             strpos($dashboardCss,'grid-template-columns:repeat(2,minmax(0,1fr))')!==false,
             'desktop and mobile dashboard columns adapt');
check_layout(strpos($layout,'url(http')===false,
             'responsive styles do not load external media');
