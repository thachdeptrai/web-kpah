<?php
/** Optional membership tiers. Uses earned Point, never current balance. */
if(!defined('KP_ROOT')){http_response_code(403);exit;}
function kp_points_tiers(): array {
 return [
  ['id'=>0,'name'=>'Đồng','threshold'=>0,'image'=>'/assets/img/point-tier-dong.webp'],
  ['id'=>1,'name'=>'Bạc','threshold'=>1000,'image'=>'/assets/img/point-tier-bac.webp'],
  ['id'=>2,'name'=>'Vàng','threshold'=>5000,'image'=>'/assets/img/point-tier-vang.webp'],
  ['id'=>3,'name'=>'Kim Cương','threshold'=>15000,'image'=>'/assets/img/point-tier-kim-cuong.webp'],
 ];
}
function kp_points_member_tier(int $earned): array {
 $earned=max(0,$earned);
 $tier=kp_points_tiers()[0];
 foreach(kp_points_tiers() as $candidate){
  if($earned >= $candidate['threshold'])$tier=$candidate;
 }
 return $tier;
}
function kp_points_next_tier(int $earned): ?array {
 foreach(kp_points_tiers() as $tier)if($earned<$tier['threshold'])return $tier;
 return null;
}
function kp_points_tier_enabled(PDO $db): bool {
 try{$st=$db->query("SHOW TABLES LIKE 'kp_points_reward_tiers'");return $st && $st->fetchColumn()!==false;}
 catch(Throwable $e){return false;}
}
function kp_points_reward_required_tiers(PDO $db,array $rewards): array {
 if(!kp_points_tier_enabled($db)||!$rewards)return [];
 $ids=array_values(array_unique(array_map('intval',array_column($rewards,'id'))));
 if(!$ids)return [];
 $st=$db->prepare('SELECT reward_id,min_tier FROM kp_points_reward_tiers WHERE reward_id IN ('.implode(',',array_fill(0,count($ids),'?')).')');
 $st->execute($ids);
 $out=[];
 foreach($st->fetchAll(PDO::FETCH_ASSOC) as $row)$out[(int)$row['reward_id']]=(int)$row['min_tier'];
 return $out;
}
function kp_points_reward_min_tier(PDO $db,int $rewardId): int {
 if(!kp_points_tier_enabled($db))return 0;
 $st=$db->prepare('SELECT min_tier FROM kp_points_reward_tiers WHERE reward_id=?');
 $st->execute([$rewardId]);$id=$st->fetchColumn();
 return $id===false?0:max(0,min(3,(int)$id));
}
function kp_points_reward_set_tier(PDO $db,int $rewardId,int $tier): void {
 if($tier<0||$tier>3||$rewardId<1)throw new RuntimeException('Hạng đổi quà không hợp lệ.');
 if(!kp_points_tier_enabled($db)){
  if($tier===0)return;
  throw new RuntimeException('Chưa chạy database/points_stage6_tiers.sql.');
 }
 $st=$db->prepare('INSERT INTO kp_points_reward_tiers (reward_id,min_tier) VALUES (?,?) ON DUPLICATE KEY UPDATE min_tier=VALUES(min_tier)');
 $st->execute([$rewardId,$tier]);
}
