<?php
/**
 * Point reward catalog presentation (stage 1): strictly optional metadata.
 * Never treats client-provided icon/item IDs as authoritative game rewards.
 * Manual fulfillment remains unchanged until explicitly verified server-side.
 */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }

function kp_point_reward_display_available(PDO $db): bool {
    try {
        $st=$db->query(kp_points_sql("SHOW TABLES LIKE 'kp_points_reward_display'"));
        return $st && $st->fetchColumn()!==false;
    } catch (Throwable $e) { return false; }
}

function kp_point_reward_display_input(array $data): array {
    $iconRaw=trim((string)($data['icon_id']??''));
    $itemRaw=trim((string)($data['item_id']??''));
    $icon=$iconRaw===''?null:filter_var($iconRaw,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>999999]]);
    $item=$itemRaw===''?null:filter_var($itemRaw,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>999999]]);
    if ($icon===false||$item===false) throw new RuntimeException('ID icon hoặc vật phẩm không hợp lệ.');
    $source=(string)($data['item_source']??'data_item');
    if(!in_array($source,['data_item','data_shop'],true))
        throw new RuntimeException('Nguồn vật phẩm không hợp lệ.');
    $options=trim((string)($data['option_lines']??''));
    if (strlen($options)>1500) throw new RuntimeException('Mô tả option quá dài (tối đa 1.500 ký tự).');
    $lines=preg_split('/\r\n|\r|\n/', $options);
    if (count($lines)>12) throw new RuntimeException('Mỗi phần quà được mô tả tối đa 12 option.');
    $safe=[];
    foreach ($lines as $line) {
        $line=trim($line);
        if ($line==='') continue;
        if (function_exists('mb_strlen') ? mb_strlen($line,'UTF-8')>110 : strlen($line)>300) {
            throw new RuntimeException('Mỗi dòng option được dài tối đa 110 ký tự.');
        }
        $safe[]=$line;
    }
    return ['icon_id'=>$icon,'item_id'=>$item,'item_source'=>$source,'option_lines'=>implode("\n",$safe)];
}

/** Detect optional additive migration; preserve previous data_item rows unchanged. */
function kp_point_reward_source_column(PDO $db): bool {
    try {
        $schema=kp_points_schema_name();
        if($schema==='')$schema=(string)$db->query('SELECT DATABASE()')->fetchColumn();
        $st=$db->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?');
        $st->execute([$schema,'kp_points_reward_display','item_source']);
        return $st->fetchColumn()!==false;
    }catch(Throwable $e){return false;}
}

function kp_point_reward_display_save(PDO $db, int $rewardId, array $display): void {
    if ($rewardId<1) throw new RuntimeException('Mã quà không hợp lệ.');
    if (!kp_point_reward_display_available($db))
        throw new RuntimeException('Chưa chạy database/points_stage1_reward_display.sql.');
    $source=(string)($display['item_source']??'data_item');
    $hasSource=kp_point_reward_source_column($db);
    if($source==='data_shop'&&!$hasSource)
        throw new RuntimeException('Hãy chạy database/points_stage8_shop_source.sql trước khi chọn data_shop.');
    if($hasSource){
        $st=$db->prepare(kp_points_sql('INSERT INTO kp_points_reward_display (reward_id,icon_id,item_id,item_source,option_lines)
            VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE icon_id=VALUES(icon_id),item_id=VALUES(item_id),
            item_source=VALUES(item_source),option_lines=VALUES(option_lines)'));
        $st->execute([$rewardId,$display['icon_id'],$display['item_id'],$source,$display['option_lines']]);
    }else{
        $st=$db->prepare(kp_points_sql('INSERT INTO kp_points_reward_display (reward_id,icon_id,item_id,option_lines)
            VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE icon_id=VALUES(icon_id),
            item_id=VALUES(item_id),option_lines=VALUES(option_lines)'));
        $st->execute([$rewardId,$display['icon_id'],$display['item_id'],$display['option_lines']]);
    }
}

/** @return array<int,array{icon_id:?int,item_id:?int,option_lines:string}> */
function kp_point_reward_display_all(PDO $db, array $rewards): array {
    if (!$rewards || !kp_point_reward_display_available($db)) return [];
    $ids=array_values(array_unique(array_map('intval',array_column($rewards,'id'))));
    $ids=array_values(array_filter($ids,static fn($id)=>$id>0));
    if (!$ids) return [];
    $columns=kp_point_reward_source_column($db)?'reward_id,icon_id,item_id,item_source,option_lines':'reward_id,icon_id,item_id,option_lines';
    $st=$db->prepare(kp_points_sql('SELECT '.$columns.' FROM kp_points_reward_display WHERE reward_id IN ('.implode(',',array_fill(0,count($ids),'?')).')'));
    $st->execute($ids);
    $result=[];
    foreach($st->fetchAll(PDO::FETCH_ASSOC) as $row) {$row['item_source']=$row['item_source']??'data_item';$result[(int)$row['reward_id']]=$row;}
    return $result;
}

function kp_point_reward_icon(array $display): string {
    $n=$display['icon_id']??null;
    if ($n===null || !filter_var((string)$n,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>999999]] ) && (string)$n!=='0') return '';
    return '/res/icon/'.(int)$n.'.png';
}

function kp_point_reward_option_lines(array $display): array {
    $s=trim((string)($display['option_lines']??''));
    return $s===''?[]:array_values(array_filter(array_map('trim',preg_split('/\r\n|\r|\n/',$s)),static fn($x)=>$x!==''));
}
