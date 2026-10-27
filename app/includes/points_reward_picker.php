<?php
/**
 * Point reward editor: visual item/icon and typed option selection.
 * Reuses real game data_item and data_attribute catalog, presentation only.
 * Does NOT mint, deliver, or serialize in-game items.
 */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }
require_once __DIR__.'/giftcodes.php';
require_once __DIR__.'/points_reward_display.php';

function kp_points_picker_catalog(PDO $db): array {
    $raw=kp_giftcode_reward_catalog($db);
    return [
        'items'=>array_map(static function(array $item): array {
            return [
                'id'=>(int)$item['id'],'name'=>(string)$item['name'],
                'icon_id'=>(int)$item['icon_id'],'type'=>(int)$item['type'],
                'type_name'=>(string)$item['type_name'],
                'image'=>(string)($item['image']??''),
            ];
        },$raw['items']),
        'options'=>array_map(static function(array $option): array {
            return [
                'id'=>(int)$option['id'],'name'=>(string)$option['name'],
                'description'=>(string)$option['description'],
                'percent'=>(bool)$option['percent'],
                'min'=>(int)$option['min'],'max'=>(int)$option['max'],
                'display_multiplier'=>(int)$option['display_multiplier'],
            ];
        },$raw['options']),
    ];
}

/**
 * Server-owned validation for selections received through the picker.
 * Unstructured old descriptions remain compatible until the admin selects
 * actual options. A malicious request cannot inject extra item grants:
 * all saved fields are only item presentation metadata.
 */
function kp_points_picker_display_input(array $post, ?array $catalog): array {
    $display=kp_point_reward_display_input($post);
    $json=trim((string)($post['point_picker_options']??''));
    if ($json==='') return $display; // Backwards-compatible text-only gift.
    if (strlen($json)>4096) throw new RuntimeException('Danh sách option quá lớn.');
    if ($catalog===null) throw new RuntimeException('Chưa tải được catalog game để xác thực option.');
    $options=json_decode($json,true);
    if (!is_array($options)||!array_is_list($options)||count($options)>12)
        throw new RuntimeException('Dữ liệu option phải là danh sách tối đa 12 chỉ số.');
    if (!$options) {
        $display['option_lines']='';
        return $display;
    }
    if ($display['item_id']===null) throw new RuntimeException('Chọn vật phẩm trước khi đặt option.');
    $items=[];
    foreach($catalog['items']??[] as $i)$items[(int)$i['id']]=$i;
    if (!isset($items[(int)$display['item_id']]))
        throw new RuntimeException('ID vật phẩm không còn tồn tại trong catalog game.');
    $optionsById=[];
    foreach($catalog['options']??[] as $o)$optionsById[(int)$o['id']]=$o;
    $lines=[];$seen=[];
    foreach($options as $entry){
        if (!is_array($entry)||array_keys($entry)!==['id','param'])
            throw new RuntimeException('Cấu trúc option không hợp lệ.');
        foreach(['id','param'] as $key) {
            if (!is_int($entry[$key])) throw new RuntimeException('Option yêu cầu ID và param nguyên.');
        }
        $id=$entry['id'];$param=$entry['param'];
        if(!isset($optionsById[$id])||isset($seen[$id]))
            throw new RuntimeException('Option không tồn tại hoặc bị lặp: #'.$id);
        $option=$optionsById[$id];
        if($param<(int)$option['min']||$param>(int)$option['max'])
            throw new RuntimeException('Giá trị option #'.$id.' vượt giới hạn.');
        $seen[$id]=true;
        $value=number_format($param*(int)$option['display_multiplier'],0,',','.')
            .(!empty($option['percent'])?'%':'');
        $lines[]='#'.$id.' '.(string)$option['name'].' +'.$value;
    }
    $display['option_lines']=implode("\n",$lines);
    if(strlen($display['option_lines'])>1500)
        throw new RuntimeException('Option mô tả vượt quá dung lượng lưu trữ.');
    return $display;
}
