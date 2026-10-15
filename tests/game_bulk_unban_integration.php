<?php
/** Disposable fixture only: php tests/game_bulk_unban_integration.php */
if (getenv('KPAH_DB_NAME') !== 'kpah_route_test') {
    fwrite(STDERR, "Refusing to write non-test database\n"); exit(1);
}
require dirname(__DIR__).'/app/includes/bootstrap.php';
require_once KP_ROOT.'/app/includes/game_account_admin.php';
function bulk_assert($ok,$label){
    if (!$ok) { fwrite(STDERR,"FAIL: ".$label."\n");exit(1); }
    echo "PASS: ".$label."\n";
}
function bulk_reject(callable $f,string $message): void {
    try {$f();}
    catch(RuntimeException $e) {
        bulk_assert(strpos($e->getMessage(),$message)!==false,'rejects '.$message);
        return;
    }
    bulk_assert(false,'expected error '.$message);
}
$db=kp_db();
$admin=['id'=>1,'username'=>'routeadmin'];
$base=88100;
$aid=88101;$bid=88102;$gid=88103;$sid=88104;
$charA=88201;$charAlt=88202;$charB=88203;$charGM=88204;$charSpecial=88205;
try {
    $insAcc=$db->prepare('INSERT INTO team_user (id,userid,username,password,provider,ban) VALUES (?,?,?,?,0,?)');
    foreach([
        [$aid,988101,'BulkUserA','*X',1],
        [$bid,988102,'BulkUserB','*X',1],
        [$gid,988103,'BulkUserGM','*X',1],
        [$sid,988104,'BulkUserSpec','*X',3]
    ] as $row)$insAcc->execute($row);
    $insChar=$db->prepare('INSERT INTO tob_char (id,charname,userId,ban,isAdmin,del) VALUES (?,?,?,?,?,1)');
    foreach([
        [$charA,'BulkHeroA',$aid,1,0],
        [$charAlt,'BulkHeroAlt',$aid,1,0],
        [$charB,'BulkHeroB',$bid,1,0],
        [$charGM,'BulkGameGM',$gid,1,1],
        [$charSpecial,'BulkSpecial',$sid,3,0]
    ] as $row)$insChar->execute($row);

    $ac=kp_game_moderation_bulk_targets($db,'account');
    bulk_assert(count($ac)===2,'eligible banned accounts exclude GM and special flags');
    $acFp=kp_game_moderation_bulk_fingerprint($ac);
    bulk_reject(function()use($db,$admin,$acFp){
        kp_game_moderation_bulk_unban($db,$admin,'account','Bulk unban approved','WRONG',$acFp);
    },'Câu xác nhận');
    bulk_reject(function()use($db,$admin){
        kp_game_moderation_bulk_unban($db,$admin,'account','Bulk unban approved','MO BAN TAT CA TAI KHOAN',str_repeat('f',64));
    },'Danh sách bị ban đã thay đổi');
    bulk_assert((int)$db->query("SELECT COUNT(*) FROM team_user WHERE ban=1 AND id BETWEEN $aid AND $sid")->fetchColumn()===3,
        'rejected confirmation does not modify accounts');
    $r=kp_game_moderation_bulk_unban(
        $db,$admin,'account','Reset test account ban','MO BAN TAT CA TAI KHOAN',$acFp
    );
    bulk_assert($r['changed']===2 && $r['failed']===0 && $r['total']===2,'bulk account unban applies exactly eligible targets');
    bulk_assert((int)$db->query("SELECT COUNT(*) FROM team_user WHERE ban=0 AND id IN ($aid,$bid)")->fetchColumn()===2,
        'two accounts opened');
    bulk_assert((int)$db->query("SELECT ban FROM team_user WHERE id=$gid")->fetchColumn()===1,
        'GM-owned account remains banned');
    bulk_assert((int)$db->query("SELECT ban FROM team_user WHERE id=$sid")->fetchColumn()===3,
        'reserved account flag 3 untouched');
    bulk_assert((int)$db->query("SELECT COUNT(*) FROM tob_char WHERE ban=1 AND id IN ($charA,$charAlt,$charB,$charGM)")->fetchColumn()===4,
        'bulk ACC unban does NOT unban characters');
    $charList=kp_game_moderation_bulk_targets($db,'character');
    bulk_assert(count($charList)===3,'eligible banned characters exclude GM and reserved ban');
    $charFp=kp_game_moderation_bulk_fingerprint($charList);
    bulk_reject(function()use($db,$admin,$acFp){
        kp_game_moderation_bulk_unban($db,$admin,'character','Bulk unban approved','MO BAN TAT CA TAI KHOAN',$acFp);
    },'Câu xác nhận');
    $r=kp_game_moderation_bulk_unban(
        $db,$admin,'character','Reset all test player bans','MO BAN TAT CA NHAN VAT',$charFp
    );
    bulk_assert($r['changed']===3 && $r['failed']===0,'bulk player unban applies exactly eligible targets');
    bulk_assert((int)$db->query("SELECT COUNT(*) FROM tob_char WHERE ban=0 AND id IN ($charA,$charAlt,$charB)")->fetchColumn()===3,
        'all eligible characters unbanned');
    bulk_assert((int)$db->query("SELECT ban FROM tob_char WHERE id=$charGM")->fetchColumn()===1,
        'GM ban state preserved');
    bulk_assert((int)$db->query("SELECT ban FROM tob_char WHERE id=$charSpecial")->fetchColumn()===3,
        'special character ban state preserved');
    bulk_assert((int)$db->query("SELECT COUNT(*) FROM tob_char WHERE del=1 AND id BETWEEN $charA AND $charSpecial")->fetchColumn()===5,
        'deletion state of all characters unchanged');
    bulk_assert((int)$db->query("SELECT ban FROM team_user WHERE id=$gid")->fetchColumn()===1,
        'bulk PLAYER unban does NOT unban GM-owned account');
    $logs=$db->query("SELECT target_type,target_id,old_ban,new_ban,reason,result
        FROM kp_game_moderation_log WHERE target_id BETWEEN $aid AND $charSpecial AND admin_name='routeadmin'
          AND (target_name LIKE 'Bulk%') ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    bulk_assert(count($logs)===5,'each unban has its own audit row');
    $accounts=0;$chars=0;
    foreach($logs as $log){
        bulk_assert((int)$log['old_ban']===1 && (int)$log['new_ban']===0 && $log['result']==='applied',
            'audit records before and after + successful write');
        if($log['target_type']==='account')$accounts++;
        if($log['target_type']==='character')$chars++;
    }
    bulk_assert($accounts===2 && $chars===3,'audit target types distinguish ACC vs PLAYER');
    bulk_reject(function()use($db,$admin,$charFp){
        kp_game_moderation_bulk_unban($db,$admin,'character','Duplicate bulk unban test','MO BAN TAT CA NHAN VAT',$charFp);
    },'Danh sách bị ban đã thay đổi');
    echo "PASS: all bulk account / player regression tests\n";
} finally {
    if(kp_game_moderation_log_available($db)){
        $db->prepare("DELETE FROM kp_game_moderation_log WHERE target_name LIKE 'Bulk%'")->execute();
    }
    $db->exec("DELETE FROM tob_char WHERE id BETWEEN 88201 AND 88205");
    $db->exec("DELETE FROM team_user WHERE id BETWEEN 88101 AND 88104");
}
