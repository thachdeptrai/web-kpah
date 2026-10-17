<?php
// Static contract test: the admin must build rewards from live DB data, never raw item strings.
$root = dirname(__DIR__);
$admin = (string)file_get_contents($root.'/app/admin/giftcodes.php');
$helper = (string)file_get_contents($root.'/app/includes/giftcodes.php');
$js = (string)file_get_contents($root.'/assets/js/admin-giftcode-builder.js');
$css = (string)file_get_contents($root.'/assets/css/giftcodes.css');
$patch = (string)file_get_contents($root.'/game-server-patch/giftcode-item-options.patch');

function gc_builder_check($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}

gc_builder_check(strpos($admin, 'name="item"') === false, 'raw giftcode item input still exposed');
gc_builder_check(strpos($admin, 'name="reward_items"') !== false, 'canonical reward JSON field missing');
gc_builder_check(strpos($admin, 'kp_giftcode_validate_reward_payload') !== false, 'POST does not use backend reward validator');
gc_builder_check(strpos($admin, "SELECT giftcode, item FROM giftcode WHERE id = ?") !== false, 'legacy tokens are not loaded before update');
gc_builder_check(strpos($helper, 'FROM data_item ORDER BY id') !== false, 'item catalog is not sourced from data_item');
gc_builder_check(strpos($helper, 'FROM data_attribute ORDER BY id') !== false, 'option catalog is not sourced from data_attribute');
gc_builder_check(strpos($helper, "'res/icon/'.\$iconId.'.png'") !== false, 'map/icon to res/icon mapping missing');
gc_builder_check(strpos($helper, "Vật phẩm ID '.\$itemId.' không tồn tại") !== false, 'unknown item backend rejection missing');
gc_builder_check(strpos($helper, "Option ID '.\$optionId.' không tồn tại") !== false, 'unknown option backend rejection missing');
gc_builder_check(strpos($js, 'innerHTML') === false, 'builder uses unsafe innerHTML');
foreach (['gc-item-search','gc-item-type','gc-option-search','gc-option-group','gc-selected-items'] as $contract) {
    gc_builder_check(strpos($admin.$js, $contract) !== false, 'UI contract missing: '.$contract);
}
foreach (['gc-reward-builder','gc-item-grid','gc-option-row','gc-picker-panel'] as $selector) {
    gc_builder_check(strpos($css, '.'.$selector) !== false, 'builder CSS missing: '.$selector);
}
gc_builder_check(strpos($patch, 'tachTime[0].equals("DBO")') !== false, 'Java DBO parser patch missing');
gc_builder_check(strpos($patch, 'applyAdminOptions') !== false, 'Java option application patch missing');

echo "Giftcode builder smoke PASS\n";
