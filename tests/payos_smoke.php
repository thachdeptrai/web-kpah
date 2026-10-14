<?php
// No credentials, payment network or database needed.
define('KP_ROOT',dirname(__DIR__));
require KP_ROOT.'/app/includes/bank_payos.php';
function kp_assert($truth,$message){if(!$truth){fwrite(STDERR,"FAIL: ".$message."\n");exit(1);}echo "OK ".$message."\n";}
$key='example-test-secret-not-for-production';
$payload=['returnUrl'=>'https://nghienkpah.io.vn/nap-ngan-hang?result=return',
 'cancelUrl'=>'https://nghienkpah.io.vn/nap-ngan-hang?result=cancel','orderCode'=>123456789,
 'description'=>'KPAH123456789','amount'=>50000];
$manual='amount=50000&cancelUrl=https://nghienkpah.io.vn/nap-ngan-hang?result=cancel&description=KPAH123456789&orderCode=123456789&returnUrl=https://nghienkpah.io.vn/nap-ngan-hang?result=return';
kp_assert(kp_bank_sign($payload,$key)===hash_hmac('sha256',$manual,$key),'payOS create-payment signature alphabetical');
$exp=[10000=>[250,500],50000=>[1500,3000],100000=>[3500,7000],500000=>[20000,40000],1000000=>[45000,90000]];
foreach($exp as $amount=>$rewards){
 kp_assert(kp_bank_rewards($amount,'luong')===$rewards[0],'bank open '.$amount);
 kp_assert(kp_bank_rewards($amount,'luonglock')===$rewards[1],'bank locked '.$amount);
}
echo "PASS bank payOS unit checks\n";
