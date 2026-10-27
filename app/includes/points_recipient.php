<?php
/** Recipient character belongs to the web account; game data is read only. */
if(!defined('KP_ROOT')){http_response_code(403);exit;}
function kp_points_target_enabled(PDO $db): bool {
    try{$st=$db->query("SHOW TABLES LIKE 'kp_points_redemption_targets'");return $st && $st->fetchColumn()!==false;}
    catch(Throwable $e){return false;}
}
function kp_points_available_characters(PDO $db,int $userId): array {
    if($userId<1)return [];
    $st=$db->prepare('SELECT id,charname FROM tob_char WHERE userId=? AND del=1 AND ban=0 ORDER BY id ASC LIMIT 30');
    $st->execute([$userId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
function kp_points_validate_character(PDO $db,int $userId,int $charId): array {
    if($userId<1||$charId<1)throw new RuntimeException('Hãy chọn nhân vật nhận quà.');
    $st=$db->prepare('SELECT id,charname FROM tob_char WHERE id=? AND userId=? AND del=1 AND ban=0 LIMIT 1');
    $st->execute([$charId,$userId]);
    $char=$st->fetch(PDO::FETCH_ASSOC);
    if(!$char)throw new RuntimeException('Nhân vật không tồn tại hoặc không thuộc tài khoản đang đăng nhập.');
    return $char;
}
function kp_points_save_target(PDO $db,int $redemptionId,array $char): void {
    $st=$db->prepare('INSERT INTO kp_points_redemption_targets (redemption_id,char_id,char_name) VALUES (?,?,?)');
    $st->execute([$redemptionId,(int)$char['id'],(string)$char['charname']]);
}
function kp_points_targets(PDO $db,array $redemptions): array {
    if(!$redemptions||!kp_points_target_enabled($db))return [];
    $ids=array_values(array_unique(array_map('intval',array_column($redemptions,'id'))));
    if(!$ids)return [];
    $st=$db->prepare('SELECT redemption_id,char_id,char_name FROM kp_points_redemption_targets WHERE redemption_id IN ('.implode(',',array_fill(0,count($ids),'?')).')');
    $st->execute($ids);
    $result=[];
    foreach($st->fetchAll(PDO::FETCH_ASSOC) as $row)$result[(int)$row['redemption_id']]=$row;
    return $result;
}
