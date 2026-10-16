<?php
// Offline giftcode regression: no production database, accounts, or payment callbacks.
$root = sys_get_temp_dir() . '/kpah-giftcode-smoke-' . bin2hex(random_bytes(6));
if (!mkdir($root, 0700, true)) { fwrite(STDERR, "Cannot create test folder\n"); exit(1); }
define('KP_ROOT', $root);
require dirname(__DIR__) . '/app/includes/giftcodes.php';
$check = function ($ok, $message) {
    if (!$ok) throw new RuntimeException($message);
};
try {
    $check(kp_giftcode_valid_code('KPAH-2026_GIFT'), 'valid code rejected');
    foreach (['', 'a', 'a b', "' OR 1=1", 'đặcbiệt', str_repeat('A', 51)] as $bad) {
        $check(!kp_giftcode_valid_code($bad), 'unsafe code accepted');
    }
    $check(kp_giftcode_valid_item(''), 'empty item should be allowed');
    $check(kp_giftcode_valid_item('DB:12:1:0'), 'game item syntax rejected');
    $check(!kp_giftcode_valid_item("DB:1:1:1'"), 'SQL meta characters accepted');
    $check(kp_giftcode_catalog_read() === [], 'new instance is not empty');
    kp_giftcode_catalog_set(123, ['public'=>true, 'title'=>'Quà khai mở', 'description'=>'Nhập trong game']);
    $data = kp_giftcode_catalog_read();
    $check(!empty($data['123']['public']) && $data['123']['title']==='Quà khai mở', 'catalog entry not persisted');
    kp_giftcode_catalog_set(123, null);
    $check(kp_giftcode_catalog_read() === [], 'catalog entry not removed');
    // Ensure only published DB entries are exposed, and inactive/zero-use never leak.
    kp_giftcode_catalog_set(100, ['public'=>true,'title'=>'A','description'=>'']);
    kp_giftcode_catalog_set(101, ['public'=>true,'title'=>'B','description'=>'']);
    kp_giftcode_catalog_set(102, ['public'=>false,'title'=>'C','description'=>'']);
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE giftcode (id INTEGER PRIMARY KEY, giftcode TEXT, xu INTEGER, luong INTEGER, luongLock INTEGER, item TEXT, limit_use INTEGER, type INTEGER)');
    $pdo->exec("INSERT INTO giftcode VALUES (100,'ACTIVE',100,0,0,NULL,5,0),(101,'EXPIRED',0,0,0,NULL,0,0),(102,'HIDDEN',0,0,0,NULL,9,0),(103,'DISABLED',0,0,0,NULL,8,1)");
    $rows=kp_giftcode_public_list($pdo,8);
    $check(count($rows)===1 && $rows[0]['giftcode']==='ACTIVE', 'hidden/exhausted giftcode leaked');
    echo "Giftcode smoke PASS\n";
} finally {
    foreach (glob($root.'/storage/giftcode/*') ?: [] as $file) @unlink($file);
    @rmdir($root.'/storage/giftcode');
    @rmdir($root.'/storage');
    @rmdir($root);
}
