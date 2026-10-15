<?php
// No database, credentials or live player changes. Run: php tests/bank_farmer_smoke.php
$root = sys_get_temp_dir() . '/kpah-bank-farmer-' . bin2hex(random_bytes(6));
if (!@mkdir($root . '/storage/topup', 0700, true)) exit(1);
define('KP_ROOT', $root);
require dirname(__DIR__) . '/app/includes/bank_farmer_reward.php';
function farmer_check($yes, $label) {
    if (!$yes) { fwrite(STDERR, "FAIL: " . $label . PHP_EOL); exit(1); }
    echo "PASS: " . $label . PHP_EOL;
}
function farmer_throws(callable $fn, $name) {
    try { $fn(); farmer_check(false, $name); }
    catch (RuntimeException $e) { farmer_check(true, $name); }
    catch (InvalidArgumentException $e) { farmer_check(true, $name); }
}
try {
    $defaults = kp_bank_farmer_settings();
    farmer_check($defaults['per_10k'] === 10000 && !$defaults['enabled'], 'safely disabled until admin enables new rewards');
    farmer_check(kp_bank_farmer_amount(10000, $defaults) === 0, 'disabled gift awards zero');
    $active = kp_bank_farmer_settings(['enabled' => true, 'per_10k' => 10000]);
    farmer_check(kp_bank_farmer_settings() === $active, 'admin settings roundtrip');
    farmer_check(kp_bank_farmer_amount(10000, $active) === 10000, '10k VNĐ gives 10k farmer points');
    farmer_check(kp_bank_farmer_amount(50000, $active) === 50000, '50k VNĐ gives 50k farmer points');
    farmer_check(kp_bank_farmer_amount(1000000, $active) === 1000000, '1m VNĐ gives 1m farmer points');
    $new = ['enabled' => true, 'per_10k' => 2500];
    farmer_check(kp_bank_farmer_amount(50000, $new) === 12500, 'configurable per-10k conversion');
    farmer_throws(function() { kp_bank_farmer_settings(['enabled' => true, 'per_10k' => 100001]); }, 'excessive reward rate rejected');
    $code = 987654321;
    kp_bank_farmer_reserve($code, 24, 10000, $active);
    $record = kp_topup_read_file(kp_bank_farmer_path($code));
    farmer_check($record['points'] === 10000 && $record['status'] === 'reserved' && $record['user_id'] === 24, 'gift entitlement snapshots amount and owner');
    farmer_throws(function() use ($code,$active) { kp_bank_farmer_reserve($code,24,10000,$active); }, 'cannot overwrite existing payment entitlement');
    $prefix = implode(',', array_fill(0,117,'0')) . ',';
    $old = $prefix . '123,50,-7,abc,8';
    $newP = kp_bank_farmer_increment($old, 10000);
    farmer_check($newP['before'] === 123 && $newP['after'] === 10123, 'farmer index 117 incremented correctly');
    $a = explode(',', $newP['pinfo']);
    farmer_check(count($a) === 122 && $a[117] === '10123' && $a[118] === '50' && $a[119] === '-7' && $a[120] === 'abc', 'all other pInfo fields preserved');
    farmer_throws(function() use ($prefix) { kp_bank_farmer_increment($prefix.'2147483647,50', 1); }, 'overflow prohibited');
    farmer_throws(function() { kp_bank_farmer_increment('1,2,3', 10000); }, 'short legacy pInfo prohibited');
    farmer_throws(function() use ($prefix) { kp_bank_farmer_increment($prefix.'abc,50', 10000); }, 'corrupt farmer balance prohibited');
    $disabledCode = 987654322;
    kp_bank_farmer_reserve($disabledCode, 24, 10000, ['enabled'=>false,'per_10k'=>10000]);
    farmer_check(!is_file(kp_bank_farmer_path($disabledCode)), 'disabled promotion does not reserve a gift');
    $config = kp_bank_farmer_settings(['enabled'=>false,'per_10k'=>10000]);
    farmer_check($config['enabled'] === false && is_file(kp_bank_farmer_path($code)), 'disabling future promotion never removes old entitlement');
    echo "PASS: farmer bank rewards isolated smoke checks" . PHP_EOL;
} finally {
    foreach (glob($root . '/storage/topup/bank-farmer/*') ?: [] as $file) @unlink($file);
    @rmdir($root . '/storage/topup/bank-farmer');
    foreach (glob($root . '/storage/topup/*') ?: [] as $file) if (is_file($file)) @unlink($file);
    @rmdir($root . '/storage/topup');
    @rmdir($root . '/storage');
    @rmdir($root);
}
