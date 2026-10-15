<?php
/**
 * Disposable database integration: php tests/farmer_monitor_integration.php.
 * Never execute against production. The schema-only test database has seeded
 * FixtureHero (id=101) and OtherHero (id=202).
 */
if(getenv('KPAH_DB_NAME')!=='kpah_route_test'){
    fwrite(STDERR,"Refusing to mutate non-test database\n");exit(1);
}
require dirname(__DIR__).'/app/includes/bootstrap.php';
require_once KP_ROOT.'/app/includes/farmer_monitor.php';
require_once KP_ROOT.'/app/includes/farmer_points.php';
function assert_monitor($passed,$description){
    if(!$passed){fwrite(STDERR,"FAILED: $description\n");exit(1);}
    echo "PASS: $description\n";
}
function monitor_cli($arg){
    $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg(KP_ROOT.'/tools/farmer-monitor.php').' '.escapeshellarg($arg).' 2>&1';
    exec($command,$lines,$exit);
    assert_monitor($exit===0,'monitor CLI '.$arg.': '.implode(' ',$lines));
}
$pdo=kp_db();
monitor_cli('install');
monitor_cli('install'); // Idempotent.
monitor_cli('collect'); // Baseline only.
assert_monitor(kp_farmer_monitor_ready($pdo),'monitor schema visible to admin');
$initial=(int)$pdo->query('SELECT COUNT(*) FROM kp_farmer_monitor_changes')->fetchColumn();
assert_monitor($initial===0,'first scan creates no synthetic gains');
// Change the second TEST character. FixtureHero stays untouched for player route tests.
$st=$pdo->query('SELECT pInfo FROM tob_char WHERE id=202');
$parts=explode(',',(string)$st->fetchColumn());
assert_monitor(count($parts)>122,'fixture has farmer pInfo fields');
$parts[117]='300';
$st=$pdo->prepare('UPDATE tob_char SET pInfo=?,luong=120 WHERE id=202');
$st->execute([implode(',',$parts)]);
sleep(1);
monitor_cli('collect');
$st=$pdo->query('SELECT * FROM kp_farmer_monitor_changes WHERE char_id=202 ORDER BY id DESC LIMIT 1');
$change=$st->fetch(PDO::FETCH_ASSOC);
assert_monitor((int)$change['points_delta']===300,'farmer point gain recorded');
assert_monitor((int)$change['luong_delta']===20,'Luong rise recorded');
assert_monitor((int)$change['points_before']===0 && (int)$change['points_after']===300,'farmer before/after recorded');
assert_monitor((int)$change['luong_before']===100 && (int)$change['luong_after']===120,'Luong before/after recorded');
$stats=kp_farmer_monitor_hourly($pdo,[202]);
assert_monitor(isset($stats[202]) && $stats[202]['points_1h']===300 && $stats[202]['luong_1h']===20,'hourly gains aggregated');
monitor_cli('collect');
assert_monitor((int)$pdo->query('SELECT COUNT(*) FROM kp_farmer_monitor_changes')->fetchColumn()===1,'idempotent unchanged snapshot');
$parts[117]='270';
$st->closeCursor();
$pdo->prepare('UPDATE tob_char SET pInfo=?,luong=115 WHERE id=202')->execute([implode(',',$parts)]);
sleep(1);
monitor_cli('collect');
$latest=$pdo->query('SELECT points_delta,luong_delta FROM kp_farmer_monitor_changes WHERE char_id=202 ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
assert_monitor((int)$latest['points_delta']===-30 && (int)$latest['luong_delta']===-5,'negative balance movements recorded');
assert_monitor((int)$pdo->query('SELECT COUNT(*) FROM kp_farmer_monitor_changes')->fetchColumn()===2,'exactly two nonzero changes');
monitor_cli('status');
