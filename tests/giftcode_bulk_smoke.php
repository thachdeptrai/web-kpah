<?php
// Offline bulk Giftcode regression. No real database, users, or live rewards.
$base = sys_get_temp_dir() . '/kpah-bulk-' . bin2hex(random_bytes(6));
if (!mkdir($base, 0700, true)) throw new RuntimeException('Cannot create test directory');
define('KP_ROOT', $base);
require dirname(__DIR__) . '/app/includes/giftcodes.php';
require dirname(__DIR__) . '/app/includes/giftcode_bulk.php';
function gc_bulk_assert($truth, $reason) {
    if (!$truth) throw new RuntimeException($reason);
}

class GiftcodeBulkFakeDb {
    public $rows = [];
    public $nextId = 1;
    public $lastId = 0;
    public $invalidIdAt = -1;
    public function prepare($sql) { return new GiftcodeBulkFakeStatement($this, $sql); }
    public function lastInsertId() {
        return $this->lastId === $this->invalidIdAt ? '0' : (string)$this->lastId;
    }
}
class GiftcodeBulkFakeStatement {
    private $db;
    private $sql;
    private $found = false;
    private $affected = 0;
    public function __construct($db, $sql) { $this->db=$db; $this->sql=$sql; }
    public function execute($params) {
        $this->affected = 0;
        if (strpos($this->sql, 'SELECT id FROM giftcode') === 0) {
            $this->found = isset($this->db->rows[(string)$params[0]]);
        } elseif (strpos($this->sql, 'INSERT INTO giftcode') === 0) {
            $name = (string)$params[0];
            if (isset($this->db->rows[$name])) throw new RuntimeException('duplicate');
            $id=$this->db->nextId++;
            $this->db->lastId=$id;
            $this->db->rows[$name] = [
                'id'=>$id, 'type'=>1, 'params'=>$params
            ];
            $this->affected = 1;
        } elseif (strpos($this->sql, 'UPDATE giftcode SET type') === 0) {
            foreach ($this->db->rows as &$row) {
                if ($row['id'] === (int)$params[1] && $row['type']===1) {
                    $row['type']=(int)$params[0];
                    $this->affected=1;
                }
            }
            unset($row);
        } else {
            throw new RuntimeException('Unknown SQL contract: ' . $this->sql);
        }
        return true;
    }
    public function fetchColumn() { return $this->found ? 1 : false; }
    public function rowCount() { return $this->affected; }
}

$values=['xu'=>100,'luong'=>2,'luongLock'=>0,'limit_use'=>1];
foreach ([0,101,-4,'100'] as $bad) {
    try {
        kp_giftcode_bulk_generate('KPAH', $bad);
        gc_bulk_assert(false, 'Invalid batch count accepted');
    } catch (RuntimeException $expected) {}
}
foreach (['A', 'ABC!', str_repeat('A',31)] as $bad) {
    try {
        kp_giftcode_bulk_generate($bad,1);
        gc_bulk_assert(false,'Invalid prefix accepted');
    } catch (RuntimeException $expected) {}
}

$codes=kp_giftcode_bulk_generate('KPAH',100);
gc_bulk_assert(count($codes)===100 && count(array_unique($codes))===100,
    '100 distinct codes must be generated');
foreach ($codes as $code) {
    gc_bulk_assert(kp_giftcode_valid_code($code),'Generated code violates game format');
}

$db=new GiftcodeBulkFakeDb();
$result=kp_giftcode_bulk_create($db,'Event',100,$values,'DB:12:1:0',
    ['public'=>true,'title'=>'Bulk test','description'=>'Private distribution'], false);
gc_bulk_assert(count($result['codes'])===100 && !$result['locked'] && $result['error']==='',
    '100 giftcodes should complete with no partial result');
gc_bulk_assert(count($db->rows)===100,'Insert count must equal requested count');
gc_bulk_assert(count(array_unique($result['codes']))===100,'No duplicate giftcodes');
foreach ($db->rows as $name=>$row) {
    gc_bulk_assert($row['type']===0,'Only fully configured codes may be active');
    gc_bulk_assert($row['params'][0]===$name && $row['params'][4]==='DB:12:1:0',
        'Code/item serialization must be unchanged');
    gc_bulk_assert($row['params'][5]===1,'Each code must receive its own limit_use');
}
$metadata=kp_giftcode_catalog_read();
gc_bulk_assert(count($metadata)===100,'All completed rows must have metadata');
foreach ($metadata as $entry) gc_bulk_assert($entry['public']===false,'Bulk codes must not be publicly listed');

$lockedDb=new GiftcodeBulkFakeDb();
$locked=kp_giftcode_bulk_create($lockedDb,'LOCK',2,$values,'',
    ['title'=>'Locked','description'=>''], true);
gc_bulk_assert(count($locked['codes'])===2,'Two disabled codes created');
foreach ($lockedDb->rows as $row) gc_bulk_assert($row['type']===1,'Explicitly locked codes remain disabled');

// Simulate a failed ID acknowledgement *after* INSERT on a MyISAM-like table.
// The code must stay disabled and appear in the partial report.
$partialDb=new GiftcodeBulkFakeDb();
$partialDb->invalidIdAt=3;
$partial=kp_giftcode_bulk_create($partialDb,'PARTIAL',5,$values,'DB:12:1:0',[],false);
gc_bulk_assert(count($partial['codes'])===2 && count($partial['locked'])===1
    && $partial['error']!=='','Partial insert must be reported, not silently retried');
gc_bulk_assert(count($partialDb->rows)===3,'No automatic inserts after failure');
gc_bulk_assert($partialDb->rows[$partial['locked'][0]]['type']===1,
    'Interrupted code must remain disabled');

$admin=(string)file_get_contents(dirname(__DIR__).'/app/admin/giftcodes.php');
$js=(string)file_get_contents(dirname(__DIR__).'/assets/js/admin-giftcode-builder.js');
gc_bulk_assert(strpos($admin, "value=\"bulk_create\"")!==false,'Bulk action missing from admin UI');
gc_bulk_assert(strpos($admin, "name=\"bulk_nonce\"")!==false,'Anti-replay nonce missing');
gc_bulk_assert(strpos($admin, "hash_equals(\$bulkNonce")!==false,'Bulk nonce not checked');
gc_bulk_assert(strpos($admin, "kp_giftcode_validate_reward_payload")!==false,
    'Must keep server-side item/option validation');
gc_bulk_assert(strpos($js, 'filtered.slice(0, 180)')===false,'Hard-coded 180 item cap remains');
gc_bulk_assert(strpos($js, 'renderItemPicker(true)')!==false,
    'Load more items button not connected');
gc_bulk_assert(strpos($js, 'exactId')!==false,'Exact numeric ID search missing');

foreach (glob($base.'/storage/giftcode/*') ?: [] as $path) @unlink($path);
@rmdir($base.'/storage/giftcode');
@rmdir($base.'/storage');
@rmdir($base);
echo "Bulk Giftcode 100-codes, replay and item picker smoke PASS\n";
