<?php
/**
 * Auth UI v2 compatibility guard — static only, never submits credentials/OTP.
 * The existing backend/security regression is still authoritative.
 */
$root=dirname(__DIR__);
function au_assert($ok,$label){if(!$ok){fwrite(STDERR,"FAIL: $label\n");exit(1);}echo "PASS: $label\n";}
function au_read($path){global $root;$c=@file_get_contents($root.'/'.$path);au_assert(is_string($c),'read '.$path);return $c;}
$css=au_read('assets/css/auth-portal-v2.css');
foreach(['.kp-auth-v2 .stage','.kp-auth-v2 .brand.kp-auth-story','.kp-auth-v2 .panel-inner',
  '.kp-auth-v2 .kp-auth-switch','.kp-auth-v2 .control','.kp-auth-v2 .alert',
  '.kp-auth-v2 .recovery-feedback','.kp-auth-v2 .success','.kp-auth-v2 .kp-auth-steps',
  'max-width:820px','max-width:480px','prefers-reduced-motion'] as $token){
 au_assert(strpos($css,$token)!==false,'auth CSS '.$token);
}
$pages=[
 'login'=>'app/pages/login.php','register'=>'app/pages/register.php',
 'recovery'=>'app/pages/forgot-password.php'
];
foreach($pages as $kind=>$path){
 $c=au_read($path);
 au_assert(strpos($c,'kp-auth-v2')!==false,'opt-in UI scope '.$kind);
 au_assert(strpos($c,'auth-portal-v2.css?v=')!==false,'versioned UI link '.$kind);
 au_assert(strpos($c,'kp-auth-story__logo')!==false,'illustrated story '.$kind);
 au_assert(strpos($c,'kp-auth-switch')!==false,'form navigation '.$kind);
 au_assert(strpos($c,'href="/dang-nhap"')!==false &&
           strpos($c,'href="/dang-ky"')!==false &&
           strpos($c,'href="/quen-mat-khau"')!==false,
           'all navigation links '.$kind);
 au_assert(strpos($c,'name="csrf"')!==false,'CSRF preserved '.$kind);
 au_assert(strpos($c,'/assets/js/ui-system.js')!==false,'shared toast mechanism '.$kind);
}
$login=au_read($pages['login']);
foreach(['kp_csrf_check','kp_rate_limit','kp_verify_account_password','session_regenerate_id(true)',
         'WHERE username = ? AND ban = 0','action="/dang-nhap"','id="toggle-password"',
         'id="login-form"','name="username"','name="password"'] as $str){
 au_assert(strpos($login,$str)!==false,'login/security contract '.$str);
}
$reg=au_read($pages['register']);
foreach(['action="/api/register"','data-endpoint="/api/register"',
         'data-email-code-endpoint="/api/send-email-code"',
         'data-email-verify-endpoint="/api/verify-email-code"',
         'data-user-pattern=','data-pw-min=','data-pw-max=',
         'name="phone"','name="email_code"','name="referral_code"',
         'name="password2"','name="website"','id="terms"',
         'id="success-view"','id="alert"','id="reg-form"'] as $str){
 au_assert(strpos($reg,$str)!==false,'registration contract '.$str);
}
$recovery=au_read($pages['recovery']);
foreach(['data-endpoint="/api/forgot-password"','id="request-reset-code"',
         'name="identity"','name="code"','name="new_password"',
         'name="confirm_password"','id="recovery-feedback"'] as $str){
 au_assert(strpos($recovery,$str)!==false,'recovery contract '.$str);
}
au_assert(strpos($reg,'Google')===false && strpos($login,'Google')===false,
 'no unsupported social auth mocked');
echo "PASS: auth UI v2 preserves authentication contracts\n";
