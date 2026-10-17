<?php
// Standalone test: php tests/topup_smoke.php (no database or card provider needed).
define('KP_ROOT', dirname(__DIR__));
require KP_ROOT . '/app/includes/topup_core.php';
function check($actual, $expected, $name) {
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL {$name}: expected " . var_export($expected,true) . " got " . var_export($actual,true) . "\n");
        exit(1);
    }
    echo "OK {$name}\n";
}
foreach ([10000=>[250,500],50000=>[1500,3000],100000=>[3500,7000],500000=>[20000,40000],1000000=>[45000,90000]] as $vnd=>$expected) {
    check(kp_card_package_units($vnd,$vnd,'luong'),$expected[0],'open package '.$vnd);
    check(kp_card_package_units($vnd,$vnd,'luonglock'),$expected[1],'locked package '.$vnd);
}
check(kp_card_package_units(100000,80000,'luong'),2800,'package after 20 percent fee');
try { kp_card_package_units(20000,16000,'luong');fwrite(STDERR,"FAIL invalid package accepted\n");exit(1); }
catch(RuntimeException $e) {echo "OK unsupported denomination rejected\n";}
check(kp_topup_units(10000,100),100,'default normal Luong');
check(kp_topup_after_discount(10000,20),8000,'card net value after 20 percent deduction');
check(kp_topup_units(kp_topup_after_discount(10000,20),100),80,'normal Luong after discount');
check(kp_topup_after_discount(10000,0),10000,'zero discount');
check(kp_topup_units(10000,200),200,'default locked Luong');
check(kp_topup_units(5000,100),50,'any amount 5000 normal Luong');
check(kp_topup_units(5000,200),100,'any amount 5000 locked Luong');
check(kp_topup_units(150,100),1,'integer rounding without packs');
check(kp_topup_units(199,100),1,'integer flooring');
check(kp_topup_status_label('queued'),'Đã đưa vào hàng chờ game','queued is not called credited');
check(kp_topup_status_label('verified_pending_delivery'),'Thẻ hợp lệ · chờ cấp','verified is not delivered');
$secret='a very long dedicated signing secret for tests';
check(strlen(kp_topup_hmac('123456789','SERIAL1',$secret)),64,'card fingerprint size');
if (function_exists('openssl_encrypt')) {
    $sealed=kp_topup_secretbox(json_encode(['code'=>'TESTCODE123','serial'=>'SERIAL123']),$secret);
    check(json_decode(kp_topup_openbox($sealed,$secret),true)['code'],'TESTCODE123','TheCaoSieuToc encrypted check payload');
    try { kp_topup_openbox($sealed,'another unrelated test signing secret');fwrite(STDERR,"FAIL wrong decryption secret accepted\n");exit(1); }
    catch(RuntimeException $e) {echo "OK wrong secret rejected\n";}
}

if (kp_topup_hmac('123456789','SERIAL1',$secret)===kp_topup_hmac('123456789','SERIAL2',$secret)) {
    fwrite(STDERR,"FAIL card fingerprint must bind serial\n");exit(1);
}
try { kp_topup_units(1,100); fwrite(STDERR,"FAIL zero-unit conversion not rejected\n");exit(1); }
catch (RuntimeException $e) { echo "OK reject zero-unit conversion\n"; }
$layout=file_get_contents(KP_ROOT.'/app/topup/_layout.php');
$overview=file_get_contents(KP_ROOT.'/app/topup/index.php');
$theme=file_get_contents(KP_ROOT.'/assets/css/topup-player-light.css');
check(strpos($layout,'if(!$admin):?><link rel="stylesheet" href="/assets/css/topup-player-light.css')!==false,true,'only player topup shell loads light theme');
check(strpos($layout,'if($admin): ?><span class="tp-brand-mark"')!==false,true,'admin brand is unchanged');
check(strpos($layout,'function kp_topup_art_icon')!==false,true,'topup uses bespoke KPAH icon art');
check(strpos($overview,'kp_topup_art_icon')!==false,true,'topup overview replaces industrial unicode glyphs');
check(strpos($theme,'body.tp-page:not(.tp-admin)')!==false,true,'player theme scoped away from admin');
check(strpos($theme,'kpah-player-icon-atlas.png')!==false,true,'topup uses original PNG fantasy icon atlas');
echo "PASS: topup smoke checks\n";
