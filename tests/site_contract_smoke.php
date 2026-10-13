<?php
/**
 * Schema-free regression tests for pretty URLs, file layout, links and guards.
 * Run: php tests/site_contract_smoke.php
 */
$root=dirname(__DIR__);
$failures=0;
$assert=function($condition,$label) use (&$failures) {
    if (!$condition) { fwrite(STDERR, "FAIL ".$label.PHP_EOL); $failures++; }
    else echo "PASS ".$label.PHP_EOL;
};
$raw=(string)file_get_contents($root.'/.htaccess');
$routes=[];
foreach (preg_split('/\r?\n/',$raw) as $line) {
    if (preg_match('/^RewriteRule\s+(\S+)\s+(\S+)\s+\[END,QSA\]\s*$/',$line,$m)) {
        $routes[]=[$m[1],$m[2]];
    }
}
$resolve=function($url) use ($routes) {
    $path=ltrim((string)parse_url($url,PHP_URL_PATH),'/');
    foreach ($routes as $row) {
        if (preg_match('~'.$row[0].'~D',$path)) return $row[1];
    }
    return null;
};
$expect=[
    '/'=>'index.php',
    '/dang-nhap'=>'login.php',
    '/dang-ky'=>'register.php',
    '/quen-mat-khau'=>'forgot-password.php',
    '/tai-khoan'=>'home.php',
    '/tai-khoan/ho-so'=>'profile.php',
    '/tai-khoan/nhan-vat'=>'characters.php',
    '/bang-xep-hang'=>'rankings.php',
    '/bang-xep-hang?type=nap'=>'rankings.php',
    '/nap'=>'topup/index.php',
    '/nap/the'=>'topup/card.php',
    '/nap/ngan-hang'=>'topup/bank.php',
    '/nap/lich-su'=>'topup/history.php',
    '/nap/top'=>'topup/top.php',
    '/quan-tri'=>'admin/index.php',
    '/quan-tri/dang-nhap'=>'admin/login.php',
    '/quan-tri/dang-xuat'=>'admin/logout.php',
    '/quan-tri/ma-gioi-thieu'=>'admin/referrals.php',
    '/quan-tri/nap'=>'topup/admin/index.php',
    '/quan-tri/nap/ti-le'=>'topup/admin/rates.php',
    '/quan-tri/nap/cong-tai-nguyen'=>'topup/admin/grant.php',
    '/quan-tri/nap/doi-soat'=>'topup/admin/orders.php',
    '/quan-tri/nap/du-lieu-game'=>'topup/admin/legacy.php',
    '/api/dang-ky'=>'api/register.php',
    '/api/email/gui-ma'=>'api/send-email-code.php',
    '/api/email/xac-minh'=>'api/verify-email-code.php',
    '/api/tai-khoan/ho-so'=>'api/user-profile.php',
    '/api/tai-khoan/mat-khau'=>'api/change-password.php',
    '/api/tai-khoan/khoi-phuc'=>'api/forgot-password.php',
    '/dang-xuat'=>'api/logout.php',
    '/captcha'=>'captcha.php',
    '/nap-the'=>'topup/card.php',
    '/nap-ngan-hang'=>'topup/bank.php',
];
foreach ($expect as $route=>$script) {
    $assert($resolve($route)===$script,'route '.$route.' -> '.$script);
    $assert(is_file($root.'/'.$script),'route target exists '.$script);
    if (strpos($route,'?')===false && $route!=='/') {
        $assert($resolve($route.'/')===$script,'optional trailing slash '.$route);
    }
}
$assert($resolve('/tai-khoan/nhan-vat/xyz')===null,'unknown nested route does not match');
$assert($resolve('/quan-tri/nap/ti-le/injection')===null,'admin route not overbroad');
$assert((bool)preg_match('/\(\?:app\|tools\|storage\|includes/',$raw),'internal app/tools storage blocked by Apache');
$assert((bool)preg_match('/\.git/',$raw) && strpos($raw,'(?:sql')!==false,'Git internals and SQL dumps blocked');
$assert(strpos($raw,'Options -Indexes -MultiViews')!==false,'no directory listings or MultiViews');

$pages=[
    'Index','Login','Register','ForgotPassword','Home','Profile','Characters','Rankings'
];
$filenames=['index','login','register','forgot-password','home','profile','characters','rankings'];
foreach ($pages as $i=>$name) {
    $view=$root.'/app/Pages/'.$name.'.php';
    $shim=$root.'/'.$filenames[$i].'.php';
    $assert(is_file($view),'view moved to app/Pages/'.$name);
    $assert(is_file($shim),'legacy wrapper still exists '.$filenames[$i]);
    if (is_file($shim)) {
        $data=file_get_contents($shim);
        $assert(strpos($data,'kp_legacy_redirect(')!==false,'GET-only legacy redirect '.$filenames[$i]);
        $assert(strpos($data,"/app/Pages/".$name.".php")!==false,'wrapper includes new view '.$filenames[$i]);
    }
}
$oldToNew=[
 'admin_security.php'=>'Security/AdminSecurity.php',
 'security.php'=>'Security/WebSecurity.php',
 'captcha.php'=>'Security/Captcha.php',
 'validator.php'=>'Security/Validator.php',
 'account.php'=>'Accounts/AccountService.php',
 'player_portal.php'=>'Accounts/PlayerPortal.php',
 'rankings.php'=>'Game/Rankings.php',
 'topup_core.php'=>'Payments/Topup.php',
 'topup_settings.php'=>'Payments/Settings.php',
 'referrals.php'=>'Community/Referrals.php',
 'mailer.php'=>'Infrastructure/Mailer.php',
 'db.php'=>'Infrastructure/Database.php',
 'config_loader.php'=>'Infrastructure/Configuration.php',
];
foreach($oldToNew as $old=>$new){
    $loader=$root.'/includes/'.$old;
    $dest=$root.'/app/'.$new;
    $assert(is_file($loader) && is_file($dest),'domain source + compatibility loader '.$old);
    if(is_file($loader)){
        $data=file_get_contents($loader);
        $assert(strpos($data,"'/app/".$new."'")!==false,'loader points to protected source '.$new);
    }
}
$assert(is_file($root.'/tools/diagnostics/Check.php'),'diagnostics moved out of root');
$assert(is_file($root.'/docs/USER_ACCOUNT.md'),'account documentation moved under docs');
$assert(!is_file($root.'/USER-ACCOUNT.md')&&!is_file($root.'/htaccess-snippet.txt'),'old clutter removed from repo root');

// These are the only pages where ordinary users/admins browse.
// No UI anchor/form src should lead to a .php endpoint or relative asset.
$frontend=array_merge(
 array_map(function($name){return 'app/Pages/'.$name.'.php';},$pages),
 ['admin/index.php','admin/login.php','admin/referrals.php','topup/_layout.php',
 'topup/index.php','topup/card.php','topup/history.php','topup/bank.php',
 'topup/admin/index.php','topup/admin/rates.php','topup/admin/grant.php',
 'topup/admin/orders.php','topup/admin/legacy.php']
);
foreach($frontend as $path){
    $source=(string)file_get_contents($root.'/'.$path);
    $attrMatches=[];
    preg_match_all('/\b(?:href|src|action|data-endpoint|data-email-code-endpoint|data-email-verify-endpoint)\s*=\s*(["\'])(.*?)\1/s',$source,$attrMatches);
    foreach($attrMatches[2] as $attr) {
        // PHP template expressions are allowed only for server-rendered hrefs.
        if(strpos($attr,'<?=')!==false || strpos($attr,'<?php')!==false)continue;
        $assert(!preg_match('~(?:^|/)[^\s<>"\']+\.php(?:[?#]|$)~',$attr),
            $path.' does not expose .php link '.$attr);
        $assert(!preg_match('~^(?:\.\./)*assets/~',$attr),
            $path.' uses absolute assets path '.$attr);
        if(strpos($attr,'/assets/')===0){
            $asset=explode('?',substr($attr,1),2)[0];
            $assert(is_file($root.'/'.$asset),$path.' includes present asset '.$asset);
        }
    }
}
$assert(strpos(file_get_contents($root.'/app/Pages/Register.php'),'data-endpoint="/api/dang-ky"')!==false,'register form uses clean API endpoint');
$assert(strpos(file_get_contents($root.'/app/Pages/Profile.php'),'data-endpoint="/api/tai-khoan/ho-so"')!==false,'profile form uses clean API endpoint');
$assert(strpos(file_get_contents($root.'/app/Pages/Profile.php'),'data-endpoint="/api/tai-khoan/mat-khau"')!==false,'password form uses clean API endpoint');
$assert(strpos(file_get_contents($root.'/app/Pages/ForgotPassword.php'),'data-endpoint="/api/tai-khoan/khoi-phuc"')!==false,'recovery form uses clean API endpoint');

if ($failures>0){ fwrite(STDERR,"FAIL: {$failures} site contracts broken".PHP_EOL);exit(1); }
echo "PASS: all route/layout/link contracts\n";
