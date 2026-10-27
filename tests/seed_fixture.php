<?php
// Destructive only to an explicitly named disposable integration database.
$name=getenv('KPAH_DB_NAME');
if ($name!=='kpah_route_test') { fwrite(STDERR,"Refusing to seed a non-test database\n"); exit(1); }
require dirname(__DIR__).'/app/includes/bootstrap.php';
$pdo=kp_db();
$pointsName=(string)(getenv('KPAH_POINTS_DB_NAME')?:'');
if($pointsName!==''){
    // Only disposable CI databases may be created from this script.
    if($pointsName!=='kpah_route_points_test')throw new RuntimeException('Refusing to initialize a non-fixture Point database');
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `kpah_route_points_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
}
foreach ([__DIR__.'/fixtures/schema.sql', KP_ROOT.'/database/referral_codes.sql'] as $file) {
    $sql=preg_replace('/^--.*$/m','',file_get_contents($file));
    foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql) as $statement) {
        if(trim($statement)!=='') $pdo->exec($statement);
    }
}
function seed_row($table,array $values) {
    $pdo=kp_db();
    $columns=$pdo->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll();
    foreach($columns as $col) {
        if (array_key_exists($col['Field'],$values) || $col['Null']==='YES' || $col['Default']!==null || strpos($col['Extra'],'auto_increment')!==false) continue;
        $type=strtolower($col['Type']);
        $values[$col['Field']]=preg_match('/int|decimal|float|double/',$type)?0:(strpos($type,'date')!==false?'2026-01-01 00:00:00':'');
    }
    $names=array_keys($values);
    $sql='INSERT INTO `'.$table.'` (`'.implode('`,`',$names).'`) VALUES ('.implode(',',array_fill(0,count($names),'?')).')';
    $pdo->prepare($sql)->execute(array_values($values));
}
foreach([101=>'routeplayer',202=>'otherplayer'] as $id=>$name) {
    seed_row('team_user',['id'=>$id,'userid'=>$id+9000,'username'=>$name,'password'=>password_hash('FixturePass123',PASSWORD_DEFAULT),'email'=>$name.'@example.test','phone'=>'0900000000','regdate'=>'2026-01-01 00:00:00']);
    $character=$id===101?'FixtureHero':'OtherHero';
    $farmerInfo=implode(',',array_fill(0,117,'0')).','.($id===101?'777':'0').',50,0,0,0,3600000';
    seed_row('tob_char',['id'=>$id,'charname'=>$character,'userId'=>$id,'lastLv'=>50,'xp'=>1000,'gold'=>1200,'luong'=>100,'luonglock'=>200,'pInfo'=>$farmerInfo]);
    seed_row('tob_web_info_char',['charname'=>$character,'userid'=>$id,'lv'=>50,'acountname'=>$name]);
    seed_row('tob_char_luong',['id'=>$id,'charname'=>$character,'lv'=>50,'exp'=>1000,'honor'=>10,'nPK'=>2]);
}
seed_row('kpah_web_admins',['username'=>'routeadmin','password_hash'=>password_hash('FixtureAdmin123',PASSWORD_DEFAULT),'role'=>'admin','is_active'=>1]);
seed_row('kpah_referral_codes',['code'=>'ROUTETEST','count'=>100,'used_count'=>0,'is_active'=>1]);
require KP_ROOT.'/app/includes/rankings.php';
require KP_ROOT.'/app/includes/player_portal.php';
foreach(kp_rankings_catalog() as $key=>$category) {
    try {
        kp_rankings_fetch($key);
        echo "PASS: supplied SQL schema query $key\n";
    } catch(PDOException $e) {
        // This disposable fixture deliberately includes a subset of game tables;
        // expanded optional leaderboards can query tables from the full game dump.
        // Only an absent TABLE is optional; other SQL errors must still fail CI.
        if((string)$e->getCode()!=='42S02')throw $e;
        echo "SKIP: optional leaderboard table absent from minimal fixture $key\n";
    }
}
$chars=kp_player_characters(101);
if(count($chars)!==1 || $chars[0]['charname']!=='FixtureHero') throw new RuntimeException('Character ownership mismatch');
if($chars[0]['diemNongDan']!==777) throw new RuntimeException('Farmer points from pInfo mismatch');
echo "PASS: character ownership joins on supplied schema\n";
