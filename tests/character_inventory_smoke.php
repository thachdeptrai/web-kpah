<?php
// Read-only offline tests; do not connect to a live database.
define('KP_ROOT', dirname(__DIR__));
require KP_ROOT . '/app/includes/character_inventory.php';
function inv_assert($condition, $description) {
    if (!$condition) { fwrite(STDERR, "FAIL: ".$description."\n"); exit(1); }
}
$raw = '0,1,1,200,2000,5,12,0,0|0,15,1,1,0>5,123,1,100,100,0,1|0,0,0>INVALID>6,456,1,10,10,0,1|0,0';
$items = kp_inventory_parse($raw);
inv_assert(count($items) === 2, 'invalid game items rejected');
inv_assert($items[0]['class'] === 0 && $items[0]['template_id'] === 1, 'correct Item.getDBInfo offsets');
inv_assert($items[0]['plus'] === 5 && $items[0]['level'] === 12, 'plus and level offsets');
inv_assert($items[0]['durability'] === 200 && $items[0]['max_durability'] === 2000, 'durability offsets');
inv_assert($items[0]['attributes'][1] === 15, 'attributes preserved');
inv_assert(count(kp_inventory_parse(null)) === 0, 'empty inventory');
inv_assert(count(kp_inventory_parse(str_repeat('a', 500001))) === 0, 'oversized inventory fails closed');
inv_assert(count(kp_inventory_parse('5,123,1,100,100,0,1|0', 1)) === 1, 'one item limit');
inv_assert(kp_inventory_icon_url(['icon_id' => '176']) === '/res/icon/176.png', 'template xstart icon URL');
inv_assert(kp_inventory_icon_url(['icon_id' => '../../foo']) === '', 'no path traversal into icon URL');
inv_assert(kp_inventory_icon_url(['icon_id' => '-1']) === '', 'negative icons rejected');
inv_assert(kp_inventory_icon_url(null) === '', 'no icon when template unknown');
$statement = new class {
    public $params;
    public function execute($params) { $this->params = $params; }
    public function fetch($mode) { return false; }
};
$db = new class($statement) {
    private $st;
    public function __construct($st) { $this->st = $st; }
    public function prepare($query) {
        if (strpos($query, 'w.userid = ? AND w.charname = ?') === false ||
            strpos($query, 'c.userId = w.userid') === false) {
            throw new Exception('Missing ownership predicates');
        }
        return $this->st;
    }
};
kp_inventory_character($db, 7, 'Alice');
inv_assert($statement->params === [7,'Alice'], 'ownership parameter binding');
inv_assert(kp_inventory_character($db, 7, '../') === null || $statement->params === [7,'../'], 'input never becomes SQL identifier');
$attributeSql = new class {
    public function fetchAll($mode) {
        return [
            ['id'=>0,'name'=>'Tấn công'],
            ['id'=>1,'name'=>'Thủ vật'],
            ['id'=>2,'name'=>'Né tránh'],
            ['id'=>30,'name'=>'Tăng tấn công']
        ];
    }
};
$attributeDb = new class($attributeSql) {
    private $statement;
    public function __construct($statement) { $this->statement=$statement; }
    public function query($sql) {
        inv_assert(strpos($sql, 'FROM data_attribute') !== false, 'load from correct table');
        inv_assert(strpos($sql, 'ORDER BY id') !== false, 'deterministic index mapping');
        return $this->statement;
    }
};
$labels = kp_inventory_attribute_labels($attributeDb);
inv_assert(kp_inventory_attribute_name(0,$labels) === 'Tấn công', 'attack attribute label');
inv_assert(kp_inventory_attribute_name(1,$labels) === 'Thủ vật', 'physical defense label');
inv_assert(kp_inventory_attribute_name(2,$labels) === 'Né tránh', 'dodge attribute label');
inv_assert(kp_inventory_attribute_name(30,$labels) === 'Tăng tấn công', 'attack boost label');
inv_assert(kp_inventory_attribute_name(70,$labels) === 'Thuộc tính #70', 'unknown attribute fallback');
echo "Character inventory smoke PASS\\n";
