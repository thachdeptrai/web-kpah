<?php
// Pure source/schema checks: php tests/rankings_smoke.php
define('KP_ROOT', dirname(__DIR__));
require KP_ROOT.'/includes/rankings.php';
function rank_check($cond,$label) {
    if (!$cond) { fwrite(STDERR,"FAILED: ".$label."\n"); exit(1); }
    echo "PASS: ".$label."\n";
}
$catalog=kp_rankings_catalog();
rank_check(count($catalog)===9,'9 leaderboard categories');
foreach($catalog as $key=>$category){
    rank_check((bool)preg_match('/^[a-z_]+$/D',$key),'safe category key '.$key);
    rank_check(!empty($category['label']) && !empty($category['metric']),'title and unit '.$key);
    $sql=$category['sql'];
    rank_check((bool)preg_match('/^\s*SELECT\s/i',$sql),'read-only SELECT '.$key);
    rank_check((bool)preg_match('/LIMIT\s+10\s*$/i',$sql),'top ten only '.$key);
    rank_check(stripos($sql,'ORDER BY')!==false,'explicit ranking order '.$key);
    rank_check(!preg_match('/\b(?:INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE|UNION|OUTFILE)\b/i',$sql),'no writes/SQL union '.$key);
    rank_check(strpos($sql,'SELECT *')===false,'no whole-row exposure '.$key);
}
try { kp_rankings_fetch('level; DROP TABLE team_user'); rank_check(false,'reject type injection'); }
catch(InvalidArgumentException $e) { rank_check(true,'reject type injection'); }
echo "PASS: leaderboard SQL is fixed and never includes request-supplied identifiers\n";
