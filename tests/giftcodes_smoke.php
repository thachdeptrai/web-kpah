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
    $check(kp_giftcode_valid_item('DBO:12:1:0:1:0:0=500;33=1'), 'DBO item syntax rejected');
    $check(!kp_giftcode_valid_item("DB:1:1:1'"), 'SQL meta characters accepted');
    $optionMap = [
        0=>['max'=>32767],
        33=>['max'=>127],
        61=>['max'=>32767],
    ];
    $itemMap = [12=>['id'=>12,'name'=>'Test item']];
    $payload = json_encode([[
        'item_id'=>12, 'quantity'=>2, 'minutes'=>1440, 'lock'=>1, 'magic'=>0,
        'options'=>[['id'=>33,'param'=>1],['id'=>0,'param'=>500]],
    ]]);
    $rewards = kp_giftcode_validate_reward_payload($payload, $itemMap, $optionMap, true);
    $serialized = kp_giftcode_serialize_rewards($rewards);
    $check($serialized === 'DBO:12:2:1440:1:0:0=500;33=1', 'canonical DBO serialization mismatch');
    $parsed = kp_giftcode_parse_rewards($serialized.',PT:4:10');
    $check(count($parsed['rewards']) === 1 && $parsed['rewards'][0]['options'][1]['id'] === 33, 'DBO parser round-trip failed');
    $check($parsed['legacy'] === ['PT:4:10'], 'legacy token was not preserved');
    $defaultOnly = kp_giftcode_validate_reward_payload(json_encode([[
        'item_id'=>12, 'quantity'=>1, 'minutes'=>0, 'lock'=>0, 'magic'=>0, 'options'=>[],
    ]]), $itemMap, $optionMap, false);
    $check(kp_giftcode_serialize_rewards($defaultOnly) === 'DB:12:1:0', 'default item must retain legacy DB format');
    try {
        kp_giftcode_validate_reward_payload(json_encode([[
            'item_id'=>999, 'quantity'=>1, 'minutes'=>0, 'lock'=>0, 'magic'=>0, 'options'=>[],
        ]]), $itemMap, $optionMap, true);
        $check(false, 'unknown item accepted');
    } catch (RuntimeException $expected) {}
    try {
        kp_giftcode_validate_reward_payload(json_encode([[
            'item_id'=>12, 'quantity'=>1, 'minutes'=>0, 'lock'=>0, 'magic'=>0,
            'options'=>[['id'=>33,'param'=>128]],
        ]]), $itemMap, $optionMap, true);
        $check(false, 'byte overflow accepted');
    } catch (RuntimeException $expected) {}
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
    // Fake PDO-compatible query object: tests selection without requiring SQLite or DB.
    $statement = new class {
        public $ids = [];
        public function execute($params) { $this->ids = $params; }
        public function fetchAll($mode) {
            $data = [
                100=>['id'=>100,'giftcode'=>'ACTIVE','xu'=>100,'luong'=>0,'luongLock'=>0,'item'=>null,'limit_use'=>5],
                101=>['id'=>101,'giftcode'=>'EXPIRED','xu'=>0,'luong'=>0,'luongLock'=>0,'item'=>null,'limit_use'=>0],
                102=>['id'=>102,'giftcode'=>'HIDDEN','xu'=>0,'luong'=>0,'luongLock'=>0,'item'=>null,'limit_use'=>9]
            ];
            $found=[];
            foreach ($this->ids as $id) {
                if (isset($data[$id]) && $data[$id]['limit_use']>0) $found[]=$data[$id];
            }
            return $found;
        }
    };
    $pdo = new class($statement) {
        private $statement;
        public function __construct($statement) { $this->statement=$statement; }
        public function prepare($sql) {
            if (strpos($sql,'type = 0 AND limit_use > 0')===false) throw new RuntimeException('Active-only SQL filter absent');
            return $this->statement;
        }
    };
    $rows=kp_giftcode_public_list($pdo,8);
    $check(count($rows)===1 && $rows[0]['giftcode']==='ACTIVE', 'hidden/exhausted giftcode leaked');
    echo "Giftcode smoke PASS\n";
} finally {
    foreach (glob($root.'/storage/giftcode/*') ?: [] as $file) @unlink($file);
    @rmdir($root.'/storage/giftcode');
    @rmdir($root.'/storage');
    @rmdir($root);
}
