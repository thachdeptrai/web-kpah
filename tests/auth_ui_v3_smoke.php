<?php
/** Presentation-only check for Auth Journey v3 assets and DOM zones. No auth requests. */
$root=dirname(__DIR__);
function v3check(bool $ok,string $label): void {if(!$ok){fwrite(STDERR,"FAIL: $label\n");exit(1);}echo "PASS: $label\n";}
function v3file(string $p): string {global $root;$c=@file_get_contents("$root/$p");v3check(is_string($c),"read $p");return $c;}
$css=v3file('assets/css/auth-portal-v3.css');
v3check(strpos($css,'.kp-auth-v3 .kp-auth-story__intro')!==false,'separate typography zone');
v3check(strpos($css,'.kp-auth-v3 .kp-auth-story__visual')!==false,'separate illustration zone');
v3check(strpos($css,'object-fit:contain')!==false,'full image shown without cover-cropping');
v3check(strpos($css,'background:none!important')!==false,'legacy cropped background removed');
v3check(strpos($css,'url("/assets/img/home-hero-anime.webp")')===false,'no reused homepage photo');
v3check(strpos($css,'max-width:480px')!==false&&strpos($css,'max-width:820px')!==false,'mobile & tablet layouts');
foreach ([
 'login'=>['app/pages/login.php','auth-login-adventure.webp','/dang-nhap'],
 'register'=>['app/pages/register.php','auth-register-adventure.webp','/dang-ky'],
 'recovery'=>['app/pages/forgot-password.php','auth-recovery-adventure.webp','/quen-mat-khau']
] as $name=>$config) {
 [$page,$art,$route]=$config;
 $html=v3file($page);
 $img=v3file('assets/img/'.$art);
 v3check(strpos($html,'kp-auth-v2 kp-auth-v3')!==false,"$name enabled v3");
 v3check(strpos($html,'auth-portal-v3.css?v=')!==false,"$name cache-busted CSS");
 v3check(strpos($html,'<div class="kp-auth-story__intro">')!==false,"$name has separated text");
 v3check(strpos($html,'<figure class="kp-auth-story__visual"')!==false,"$name has media figure");
 v3check(strpos($html,'/assets/img/'.$art)!==false,"$name unique new image");
 v3check(substr_count($html,'<figure class="kp-auth-story__visual"')===1,"$name one illustration");
 v3check(strpos($html,'name="csrf"')!==false,"$name CSRF untouched");
 v3check(substr($img,0,4)==='RIFF'&&substr($img,8,4)==='WEBP',"$name artwork is WEBP");
}
$login=v3file('app/pages/login.php');
$register=v3file('app/pages/register.php');
$recovery=v3file('app/pages/forgot-password.php');
v3check(strpos($login,'action="/dang-nhap"')!==false,'login backend route');
v3check(strpos($register,'action="/api/register"')!==false&&strpos($register,'name="email_code"')!==false&&strpos($register,'name="referral_code"')!==false,'registration/OTP/referral retained');
v3check(strpos($recovery,'data-endpoint="/api/forgot-password"')!==false,'recovery endpoint');
echo "Auth Journey v3 smoke PASS\n";
