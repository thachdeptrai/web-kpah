<?php
/**
 * Static regression guard for the admin-only manual VND / tongnap page.
 * No database writes; safe for CI and production PHP CLI.
 */
$root=dirname(__DIR__);
$page=file_get_contents($root.'/app/topup/admin/wallet-credit.php');
$routes=file_get_contents($root.'/config/routes.php');
$nav=file_get_contents($root.'/app/includes/admin_nav.php');
$wallet=file_get_contents($root.'/app/includes/wallet_vnd.php');
if(!is_string($page)||!is_string($routes)||!is_string($nav)||!is_string($wallet))exit(1);
$assert=function($condition,$message){
    if(!$condition){fwrite(STDERR,"FAIL: ".$message.PHP_EOL);exit(1);}
};
$assert(strpos($routes,"'/quan-tri/cong-vnd'")!==false,'new admin route');
$assert(strpos($routes,"'/quan-tri/cong-tong-nap'")!==false,'old URL compatibility');
$assert(strpos($routes,"'scope' => 'admin'")!==false,'admin route scope');
$assert(strpos($nav,"'wallet-credit' => ['/quan-tri/cong-vnd'")!==false,'shared admin nav');
$assert(strpos($page,'$admin=kp_topup_admin();')!==false,'admin-only server guard');
$assert(strpos($page,'kp_admin_csrf_valid')!==false,'CSRF validation');
$assert(strpos($page,'kp_admin_limit')!==false,'rate limit');
$assert(strpos($page,'kp_vnd_with_lock')!==false,'shared VND account lock');
$assert(strpos($page,"['vnd','tongnap','both']")!==false,'explicit mode whitelist');
$assert(strpos($page,'kp_topup_write_file($path,$record);')!==false,'durable journal');
$assert(strpos($page,"'status'=>'prepared'")!==false,'write-ahead state');
$assert(strpos($page,"'status']='needs_review'")!==false,'uncertain outcome state');
$assert(strpos($page,'COALESCE(vnd,0)=? AND COALESCE(tongnap,0)=?')!==false,'compare and swap');
$assert(strpos($page,'kp_vnd_require_tongnap')!==false,'existing numeric schema check');
$assert(strpos($page,'kp_points_award(')===false,'manual credit must not award Points');
$assert(strpos($page,'kp_bank_create(')===false,'manual credit must not create payment');
$assert(strpos($wallet,"'kp_vnd_'")!==false,'uses payment shared lock namespace');
echo "Manual wallet admin checks OK".PHP_EOL;
