<?php
// Offline render verification against the existing DB/DBO parser, no SQL writes.
function kp_e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
require dirname(__DIR__) . '/app/includes/giftcodes.php';
require dirname(__DIR__) . '/app/includes/giftcode_history_view.php';

function gc_history_assert($valid, $why) {
    if (!$valid) throw new RuntimeException($why);
}
$itemMap = [
    12=>[
        'id'=>12,'name'=>'Kiếm <thần>', 'type_name'=>'Kiếm',
        'image'=>'/res/icon/42.png',
        'default_options'=>[['id'=>0,'param'=>250]],
    ],
    13=>[
        'id'=>13,'name'=>'Nhẫn gió', 'type_name'=>'Nhẫn',
        'image'=>'javascript:alert(1)', 'default_options'=>[],
    ],
];
$optionMap = [
    0=>['name'=>'Tấn công','description'=>'Chỉ số <gốc>','percent'=>false,'display_multiplier'=>1],
    33=>['name'=>'Tăng sức mạnh','description'=>'Mỗi cấp tăng','percent'=>true,'display_multiplier'=>1000],
];
$html = kp_giftcode_history_items_html('DBO:12:2:60:1:1:0=500;33=2,DB:13:1:0,PT:4:10', $itemMap, $optionMap);
foreach ([
    'src="/res/icon/42.png"'=>'real res/icon image',
    'Kiếm &lt;thần&gt;'=>'escaped item name',
    '×2'=>'quantity',
    '60 phút'=>'item duration',
    'Đã khóa'=>'lock state',
    'Hệ vật lý'=>'magic mode label',
    'Chỉ số gốc từ data_item'=>'default options heading',
    'Tấn công'=>'option name',
    '250'=>'default option param',
    'Chỉ số &lt;gốc&gt;'=>'escaped option description',
    'Option trong token DBO'=>'custom options heading',
    '2.000%'=>'option multiplier and percent',
    'Mỗi cấp tăng'=>'custom option description',
    'Nhẫn gió'=>'second DB item',
    'PT:4:10'=>'legacy token not discarded',
] as $needle=>$label) {
    gc_history_assert(strpos($html, $needle)!==false, 'Missing '.$label);
}
gc_history_assert(substr_count($html, '<details class="gc-history-item">')===2,
    'two readable item rows are independently expandable');
gc_history_assert(substr_count($html, '<summary class="gc-history-item-summary">')===2,
    'each item presents a short collapsed summary');
gc_history_assert(strpos($html, '<details class="gc-history-item" open')===false,
    'options must not expand and enlarge table rows by default');
gc_history_assert(strpos($html, '<details class="gc-history-legacy">')!==false,
    'legacy tokens must be collapsible instead of occupying rows');
gc_history_assert(strpos($html, '2 chỉ số')!==false,
    'summary displays option count without expanding options');
gc_history_assert(strpos($html, '<div class="gc-history-item-body">')
    > strpos($html, '</summary>'), 'details appear after summary, not inside it');
gc_history_assert(strpos($html, '<thần>')===false, 'Item name must be escaped');
gc_history_assert(strpos($html, 'javascript:')===false, 'Untrusted icon scheme must be rejected');
gc_history_assert(strpos($html, 'onerror=')===false, 'Never include executable inline handlers');
gc_history_assert(strpos(kp_giftcode_history_items_html('null',$itemMap,$optionMap),
    'Không có dữ liệu vật phẩm')!==false, 'Null log item must have explicit empty state');
gc_history_assert(strpos(kp_giftcode_history_items_html('DB:999:2:0',$itemMap,$optionMap),
    'DB:999:2:0')!==false, 'Unknown item token must not be guessed');

$root=dirname(__DIR__);
$admin=(string)file_get_contents($root.'/app/admin/giftcodes.php');
$css=(string)file_get_contents($root.'/assets/css/giftcodes.css');
gc_history_assert(strpos($admin, 'kp_giftcode_history_items_html')!==false, 'History table must use view helper');
gc_history_assert(strpos($css, '.gc-history-item')!==false, 'History item styling missing');
gc_history_assert(strpos($css, 'min-height:40px')!==false, 'Compact 40px item row height missing');
gc_history_assert(strpos($admin, 'SELECT id, giftcode, player, item, xu, luong, luongK FROM giftcode_log')!==false,
    'Game receipt query contract changed');
echo "Giftcode history thumbnails/options smoke PASS\n";
