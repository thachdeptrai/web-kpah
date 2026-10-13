<?php
/**
 * Development-only router for php -S. NOT used by Apache in production.
 * Mirrors the canonical .htaccess route list for HTTP regression smoke tests.
 */
$root=dirname(__DIR__);
$path=(string)parse_url((string)($_SERVER['REQUEST_URI']??'/'),PHP_URL_PATH);
$clean=trim($path,'/');
$routes=[
 ''=>'index.php',
 'dang-nhap'=>'login.php','dang-ky'=>'register.php',
 'quen-mat-khau'=>'forgot-password.php',
 'tai-khoan'=>'home.php','tai-khoan/ho-so'=>'profile.php',
 'tai-khoan/nhan-vat'=>'characters.php','bang-xep-hang'=>'rankings.php',
 'dang-xuat'=>'api/logout.php','captcha'=>'captcha.php',
 'nap'=>'topup/index.php','nap/the'=>'topup/card.php',
 'nap/ngan-hang'=>'topup/bank.php','nap/lich-su'=>'topup/history.php',
 'nap/top'=>'topup/top.php','nap-the'=>'topup/card.php',
 'nap-ngan-hang'=>'topup/bank.php',
 'quan-tri'=>'admin/index.php','quan-tri/dang-nhap'=>'admin/login.php',
 'quan-tri/dang-xuat'=>'admin/logout.php',
 'quan-tri/ma-gioi-thieu'=>'admin/referrals.php',
 'quan-tri/nap'=>'topup/admin/index.php',
 'quan-tri/nap/ti-le'=>'topup/admin/rates.php',
 'quan-tri/nap/cong-tai-nguyen'=>'topup/admin/grant.php',
 'quan-tri/nap/doi-soat'=>'topup/admin/orders.php',
 'quan-tri/nap/du-lieu-game'=>'topup/admin/legacy.php',
 'api/dang-ky'=>'api/register.php',
 'api/email/gui-ma'=>'api/send-email-code.php',
 'api/email/xac-minh'=>'api/verify-email-code.php',
 'api/tai-khoan/ho-so'=>'api/user-profile.php',
 'api/tai-khoan/mat-khau'=>'api/change-password.php',
 'api/tai-khoan/khoi-phuc'=>'api/forgot-password.php',
];
$private='~(?:^|/)(?:app|tools|storage|includes|database|docs|tests|\.git|\.github|vendor)(?:/|$)~i';
if(preg_match($private,$clean) || preg_match('~(?:^|/)\.[^/]+~',$clean)
 || preg_match('~\.(?:sql|bak|backup|zip|rar|log)$~i',$clean)){
    http_response_code(403);
    exit('Forbidden');
}
if(isset($routes[$clean])){
    $_SERVER['SCRIPT_NAME']='/'.$routes[$clean];
    require $root.'/'.$routes[$clean];
    return true;
}
if (is_file($root.'/'.$clean)) {
    // PHP's built-in server will execute legacy entrypoints or serve CSS/JS.
    return false;
}
http_response_code(404);
exit('Not found');
