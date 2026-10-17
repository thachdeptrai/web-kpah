<?php
// Isolated setting persistence checks: php tests/topnap_toggle_smoke.php
$root=sys_get_temp_dir().'/kpah-topnap-toggle-'.bin2hex(random_bytes(5));
mkdir($root.'/storage/topup',0700,true);
define('KP_ROOT',$root);
require dirname(__DIR__).'/app/includes/rankings.php';
function topnap_check($condition,$message){
    if(!$condition){fwrite(STDERR,"FAIL: $message\n");exit(1);}
    echo "PASS: $message\n";
}
topnap_check(kp_topnap_enabled()===true,'missing config preserves current enabled behavior');
kp_topnap_set_enabled(false);
topnap_check(kp_topnap_enabled()===false,'admin can persist disabled state');
try{kp_rankings_fetch('nap');topnap_check(false,'disabled Top nap data is not served directly');}
catch(RuntimeException $e){topnap_check(true,'disabled Top nap data is not served directly');}
kp_topnap_set_enabled(true);
topnap_check(kp_topnap_enabled()===true,'admin can re-enable Top nap');
$page=file_get_contents(dirname(__DIR__).'/app/pages/home.php');
$layout=file_get_contents(dirname(__DIR__).'/app/topup/_layout.php');
$admin=file_get_contents(dirname(__DIR__).'/app/topup/admin/index.php');
topnap_check(strpos($page,"if(kp_topnap_enabled())")!==false,'player dashboard gates Top nap shortcuts');
topnap_check(strpos($layout,'if(!$admin&&!kp_topnap_enabled())')!==false,'player top-up navigation gates Top nap link');
topnap_check(strpos($admin,"topnap_visibility")!==false&&strpos($admin,'kp_admin_csrf_valid')!==false,'admin toggle is CSRF protected');
foreach(glob($root.'/storage/topup/*')?:[] as $file)@unlink($file);
@rmdir($root.'/storage/topup');@rmdir($root.'/storage');@rmdir($root);
