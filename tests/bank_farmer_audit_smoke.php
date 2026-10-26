<?php
/**
 * Tests the real JSON entitlement history functions on a disposable directory.
 * Never queries or modifies a real payment, wallet, game character or GeoIP API.
 */
$root = sys_get_temp_dir() . '/kpah-farmer-audit-' . bin2hex(random_bytes(6));
if (!@mkdir($root . '/storage/topup/bank-farmer', 0700, true)) throw new RuntimeException('Cannot set up farmer audit fixture');
define('KP_ROOT',$root);
require dirname(__DIR__) . '/app/includes/bank_farmer_reward.php';

function audit_check($yes, $label) {
    if (!$yes) throw new RuntimeException('Farmer claim history: '.$label);
}
try {
    $device=kp_bank_farmer_device('Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 Version/18.5 Mobile/15E148 Safari/604.1');
    audit_check($device['type']==='Điện thoại' && $device['os']==='iOS / iPadOS'
        && $device['browser']==='Safari','iPhone visitor is detected as phone/iOS/Safari');
    $desktop=kp_bank_farmer_device('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0.0.0 Safari/537.36');
    audit_check($desktop['type']==='Máy tính' && $desktop['os']==='Windows'
        && $desktop['browser']==='Chrome','Windows Chrome visitor is detected as desktop');
    audit_check(kp_bank_farmer_device('')['type']==='Không xác định','unknown device is not guessed');
    $server=['REMOTE_ADDR'=>'203.0.113.17','HTTP_USER_AGENT'=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/131.0 Safari/537.36',
        'GEOIP_CITY'=>'Hà Nội','GEOIP_REGION'=>'Hà Nội','GEOIP_COUNTRY_NAME'=>'Việt Nam'];
    $audit=kp_bank_farmer_claim_audit($server);
    audit_check($audit['claim_ip']==='203.0.113.17'
        && $audit['claim_location']['city']==='Hà Nội'
        && strpos(kp_bank_farmer_history_place($audit['claim_location']),'ước lượng')!==false,
        'trusted server-side GeoIP is always marked approximate');
    audit_check(kp_bank_farmer_location(['HTTP_CF_IPCOUNTRY'=>'ZZ', 'HTTP_X_FORWARDED_FOR'=>'1.2.3.4'])['source']==='',
        'untrusted request headers must not create a fake location');
    audit_check(kp_bank_farmer_audit_ip(['REMOTE_ADDR'=>'invalid'])==='','invalid IP not logged');
    $legacy = [
        'user_id'=>101,'order_code'=>345678901,'amount'=>10000,
        'char_id'=>101,'charname'=>'Hero','points'=>10000,
        'points_before'=>777,'points_after'=>10777,'status'=>'completed',
        'completed_at'=>1760000000
    ];
    $file=kp_bank_farmer_path(345678901);
    kp_topup_write_file($file,$legacy + $audit);
    kp_topup_write_file(kp_bank_farmer_path(345678902),[
        'user_id'=>202,'order_code'=>345678902,'amount'=>50000,
        'char_id'=>202,'charname'=>'Another','points'=>50000,
        'points_before'=>0,'points_after'=>50000,'status'=>'completed',
        'completed_at'=>1760000020
    ]);
    kp_topup_write_file(kp_bank_farmer_path(345678903),[
        'user_id'=>101,'order_code'=>345678903,'amount'=>50000,
        'char_id'=>101,'charname'=>'Hero','points'=>50000,
        'status'=>'reserved','created_at'=>1760000030
    ]);
    kp_topup_write_file(kp_bank_farmer_path(345678904),[
        'user_id'=>101,'order_code'=>345678904,'amount'=>10000,
        'char_id'=>101,'charname'=>'Hero','points'=>10000,
        'points_before'=>10777,'points_after'=>20777,'status'=>'needs_review',
        'prepared_at'=>1760000040
    ]);
    // Malformed legacy JSON is an isolated corrupted file; it must not
    // prevent the other accounts' history from rendering.
    file_put_contents(kp_bank_farmer_path(345678905), '{broken-json');
    $owner=kp_bank_farmer_history_page(101,1,20);
    audit_check($owner['total']===2
        && $owner['rows'][0]['order_code']===345678904
        && $owner['rows'][1]['order_code']===345678901,
        'player only sees owned attempted/completed claims ordered by timestamp');
    audit_check($owner['rows'][1]['ip']==='203.0.113.17',
        'completed claim contains IP and previous balance');
    audit_check($owner['rows'][0]['ip']==='' && $owner['rows'][0]['device']==='',
        'missing old metadata is displayed as absent, not guessed');
    $source=(string)file_get_contents(dirname(__DIR__).'/app/includes/bank_farmer_audit.php');
    audit_check(strpos($source, "KP_ROOT.'/storage/topup/bank-farmer'")!==false
        && strpos($source, "kp_topup_root().'/bank-farmer'")===false,
        'read-only history must not require write permission in topup root');
    $other=kp_bank_farmer_history_page(202,1,20,'345678901');
    audit_check($other['total']===0,
        'player cannot retrieve a second account via order-code filtering');
    $ids=kp_bank_farmer_history_user_ids([
        ['user_id'=>101], ['user_id'=>101], ['user_id'=>202],
        ['user_id'=>101]
    ]);
    audit_check($ids === [101,202], 'duplicate owners reindexed for PDO positional placeholders');
    audit_check(array_keys($ids) === [0,1], 'owner list contains no missing numeric keys');
    $admin=kp_bank_farmer_history_page(null,1,2);
    audit_check($admin['total']===3 && $admin['pages']===2 && count($admin['rows'])===2,
        'admin sees all accounts, with pagination');
    $next=kp_bank_farmer_history_page(null,2,2);
    audit_check(count($next['rows'])===1,'second page contains the rest of the results');
    audit_check(kp_bank_farmer_history_time(1760000000)==='09/10/2025 15:53:20',
        'date has seconds in Vietnam time zone');
    audit_check(kp_bank_farmer_history_place([])==='Không xác định',
        'historical missing geography shows unknown');

    $sourceRoot=dirname(__DIR__);
    $routes=require $sourceRoot.'/config/routes.php';
    audit_check($routes['routes']['/lich-su-nhan-diem-nong-dan']['scope']==='player'
        && $routes['routes']['/quan-tri/diem-nong-dan/nhan-qua']['scope']==='admin',
        'user and admin histories use isolated session scopes');
    $player=(string)file_get_contents($sourceRoot.'/app/topup/farmer-claims.php');
    $adminSource=(string)file_get_contents($sourceRoot.'/app/admin/farmer-claims.php');
    $bank=(string)file_get_contents($sourceRoot.'/app/includes/bank_farmer_reward.php');
    audit_check(strpos($player, "kp_bank_farmer_history_page((int)\$user['id']")!==false,
        'player owner ID must come only from authenticated session');
    audit_check(strpos($adminSource,"kp_admin_require('/quan-tri/dang-nhap',['admin'])")!==false,
        'admin page restricted to full admin role');
    audit_check(strpos($bank,'$gift += kp_bank_farmer_claim_audit()')!==false,
        'claim must journal device/IP evidence before MyISAM update');
    echo "PASS: Farmer Points user/admin history IP/device/GeoIP, owner isolation and no replay\n";
} finally {
    foreach (glob($root.'/storage/topup/bank-farmer/*') ?: [] as $file) @unlink($file);
    @rmdir($root.'/storage/topup/bank-farmer');
    foreach (glob($root.'/storage/topup/*') ?: [] as $file) if (is_file($file)) @unlink($file);
    @rmdir($root.'/storage/topup'); @rmdir($root.'/storage'); @rmdir($root);
}
