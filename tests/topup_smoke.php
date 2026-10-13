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
if (kp_topup_hmac('123456789','SERIAL1',$secret)===kp_topup_hmac('123456789','SERIAL2',$secret)) {
    fwrite(STDERR,"FAIL card fingerprint must bind serial\n");exit(1);
}
try { kp_topup_units(1,100); fwrite(STDERR,"FAIL zero-unit conversion not rejected\n");exit(1); }
catch (RuntimeException $e) { echo "OK reject zero-unit conversion\n"; }
echo "PASS: topup smoke checks\n";
