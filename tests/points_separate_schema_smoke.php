<?php
/** No DB writes: routing must leave game tables and string literals intact. */
define('KP_ROOT',dirname(__DIR__));
require KP_ROOT.'/app/includes/points_storage.php';
function ps_ok($cond,$note){if(!$cond)throw new RuntimeException('Point schema: '.$note);}
$schema='kpah_points';
$target=chr(96).$schema.chr(96).'.'.chr(96);
$cases=[
 "SELECT * FROM kp_points_wallet WHERE user_id=?"=>"SELECT * FROM ".$target."kp_points_wallet".chr(96)." WHERE user_id=?",
 "UPDATE kp_points_rewards SET stock=stock-1 WHERE id=?"=>"UPDATE ".$target."kp_points_rewards".chr(96)." SET stock=stock-1 WHERE id=?",
 "INSERT IGNORE INTO kp_points_ledger (user_id) VALUES (?)"=>"INSERT IGNORE INTO ".$target."kp_points_ledger".chr(96)." (user_id) VALUES (?)",
 "DELETE FROM kp_points_reward_tiers WHERE reward_id=?"=>"DELETE FROM ".$target."kp_points_reward_tiers".chr(96)." WHERE reward_id=?",
 "CREATE TABLE IF NOT EXISTS kp_points_wallet (id INT)"=>"CREATE TABLE IF NOT EXISTS ".$target."kp_points_wallet".chr(96)." (id INT)",
];
foreach($cases as $before=>$expected)ps_ok(kp_points_sql($before,$schema)===$expected,$before);
$join=kp_points_sql("SELECT * FROM kp_bank_orders b JOIN kp_points_ledger l ON l.source_ref=CAST(b.order_code AS CHAR)",$schema);
ps_ok(strpos($join,'FROM kp_bank_orders b')!==false,'game bank untouched');
ps_ok(strpos($join,'JOIN '.$target.'kp_points_ledger'.chr(96).' l')!==false,'cross-schema JOIN');
$meta=kp_points_sql("SHOW TABLES LIKE 'kp_points_redemption_control'",$schema);
ps_ok(strpos($meta,"TABLE_SCHEMA='kpah_points'")!==false,'SHOW table schema');
ps_ok(strpos($meta,"TABLE_NAME='kp_points_redemption_control'")!==false,'SHOW original table name');
$quoted=kp_points_sql("SELECT 'FROM kp_points_wallet' AS debug FROM kp_points_rewards",$schema);
ps_ok(strpos($quoted,"'FROM kp_points_wallet'")!==false,'string literal preserved');
ps_ok(kp_points_sql('SELECT * FROM kp_points_wallet','')==='SELECT * FROM kp_points_wallet','legacy is unchanged');
try{kp_points_sql('SELECT * FROM kp_points_wallet','kpah'.chr(96).'points');ps_ok(false,'bad schema accepted');}
catch(InvalidArgumentException $e){}
echo "PASS: split schema SQL routing\n";
