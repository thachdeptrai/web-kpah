<?php
/** Safe integration: run ONLY against the named disposable fixture database. */
if(getenv('KPAH_DB_NAME')!=='kpah_route_test'){
    fwrite(STDERR,"Refusing to change non-test game database\n");exit(1);
}
require dirname(__DIR__).'/app/includes/bootstrap.php';
require_once KP_ROOT.'/app/includes/game_account_admin.php';
function assert_bulk($ok,$label){
    if(!$ok){fwrite(STDERR,"FAILED: $label\n");exit(1);}
    echo "PASS: $label\n";
}
function reject_bulk(callable $callback,string $part): void {
    try{$callback();}
    catch(RuntimeException $e){
        assert_bulk(strpos($e->getMessage(),$part)!==false,'rejects '.$part);
        return;
    }
    assert_bulk(false,'must reject '.$part);
}
$db=kp_db();
$admin=['id'=>1,'username'=>'routeadmin'];
$aid=88101;$bid=88102;$gid=88103;$sid=88104;
$charA=88201;$charAlt=88202;$charB=88203;$charGM=88204;$charSpecial=88205;
$accountsPhrase='MO BAN TAT CA TAI KHOAN';
$charsPhrase='MO BAN TAT CA NHAN VAT';
try{
    $ins=$db->prepare('INSERT INTO team_user (id,userid,username,password,provider,ban) VALUES (?,?,?,?,0,?)');
    foreach([
        [$aid,988101,'BulkUserA','*X',1],
        [$bid,988102,'BulkUserB','*X',1],
        [$gid,988103,'BulkUserGM','*X',1],
        [$sid,988104,'BulkUserSpec','*X',3]
    ] as $row)$ins->execute($row);
    $ins=$db->prepare('INSERT INTO tob_char (id,charname,userId,ban,isAdmin,del) VALUES (?,?,?,?,?,1)');
    foreach([
        [$charA,'BulkHeroA',$aid,1,0],
        [$charAlt,'BulkHeroAlt',$aid,1,0],
        [$charB,'BulkHeroB',$bid,1,0],
        [$charGM,'BulkGameGM',$gid,1,1],
        [$charSpecial,'BulkSpecial',$sid,3,0]
    ] as $row)$ins->execute($row);

    $eligible=kp_game_moderation_bulk_targets($db,'account');
    assert_bulk(count($eligible)===2,'bulk ACC eligibility omits GM-owned and special-state accounts');
    $fp=kp_game_moderation_bulk_fingerprint($eligible);
    reject_bulk(function()use($db,$admin,$fp,$accountsPhrase,$aid){
        kp_game_moderation_bulk_unban($db,$admin,'account','Approved account reset','WRONG',$fp,[$aid]);
    },'Câu xác nhận');
    reject_bulk(function()use($db,$admin,$fp,$accountsPhrase){
        kp_game_moderation_bulk_unban($db,$admin,'account','Approved account reset',$accountsPhrase,$fp,[]);
    },'Chưa chọn');
    reject_bulk(function()use($db,$admin,$fp,$accountsPhrase,$aid){
        kp_game_moderation_bulk_unban($db,$admin,'account','Approved account reset',$accountsPhrase,$fp,[$aid,$aid]);
    },'chọn trùng');
    reject_bulk(function()use($db,$admin,$fp,$accountsPhrase,$gid){
        kp_game_moderation_bulk_unban($db,$admin,'account','Approved account reset',$accountsPhrase,$fp,[$gid]);
    },'không đủ điều kiện');
    reject_bulk(function()use($db,$admin,$accountsPhrase,$aid){
        kp_game_moderation_bulk_unban($db,$admin,'account','Approved account reset',$accountsPhrase,str_repeat('f',64),[$aid]);
    },'Danh sách bị ban đã thay đổi');
    assert_bulk((int)$db->query("SELECT COUNT(*) FROM team_user WHERE id IN ($aid,$bid,$gid) AND ban=1")->fetchColumn()===3,
        'invalid bulk requests leave all account flags untouched');

    $result=kp_game_moderation_bulk_unban(
        $db,$admin,'account','Release user A only',$accountsPhrase,$fp,[(string)$aid]);
    assert_bulk($result['total']===1&&$result['eligible']===2&&$result['excluded']===1&&$result['changed']===1&&$result['failed']===0,
        'subset releases one selected account and counts one excluded');
    assert_bulk((int)$db->query("SELECT ban FROM team_user WHERE id=$aid")->fetchColumn()===0,
        'checked account opened');
    assert_bulk((int)$db->query("SELECT ban FROM team_user WHERE id=$bid")->fetchColumn()===1,
        'UNCHECKED account remains BANNED');
    assert_bulk((int)$db->query("SELECT ban FROM team_user WHERE id=$gid")->fetchColumn()===1,
        'GM-owned account remains banned');
    assert_bulk((int)$db->query("SELECT ban FROM team_user WHERE id=$sid")->fetchColumn()===3,
        'reserved account ban=3 stays untouched');
    reject_bulk(function()use($db,$admin,$fp,$accountsPhrase,$bid){
        kp_game_moderation_bulk_unban($db,$admin,'account','Old selection test',$accountsPhrase,$fp,[$bid]);
    },'Danh sách bị ban đã thay đổi');

    $next=kp_game_moderation_bulk_targets($db,'account');
    $result=kp_game_moderation_bulk_unban(
        $db,$admin,'account','Release remaining account B',$accountsPhrase,
        kp_game_moderation_bulk_fingerprint($next),[$bid]);
    assert_bulk($result['changed']===1&&$result['excluded']===0,'reloaded list allows independent second account unban');
    assert_bulk((int)$db->query("SELECT COUNT(*) FROM tob_char WHERE ban=1 AND id IN ($charA,$charAlt,$charB,$charGM)")->fetchColumn()===4,
        'bulk account unban NEVER modifies character bans');

    $chars=kp_game_moderation_bulk_targets($db,'character');
    assert_bulk(count($chars)===3,'bulk PLAYER eligibility excludes GM character and special ban=3');
    $fp=kp_game_moderation_bulk_fingerprint($chars);
    $byId=[];
    foreach($chars as $row)$byId[(int)$row['id']]=$row;
    assert_bulk(isset($byId[$charA]['owner_name'])&&$byId[$charA]['owner_name']==='BulkUserA',
        'selectable player list includes owning account name');
    reject_bulk(function()use($db,$admin,$fp,$charsPhrase,$charGM){
        kp_game_moderation_bulk_unban($db,$admin,'character','Do not unban GM',$charsPhrase,$fp,[$charGM]);
    },'không đủ điều kiện');
    reject_bulk(function()use($db,$admin,$fp,$charsPhrase,$charA){
        kp_game_moderation_bulk_unban($db,$admin,'character','Wrong selection',$charsPhrase,$fp,['xx']);
    },'ID không hợp lệ');

    $result=kp_game_moderation_bulk_unban(
        $db,$admin,'character','Release main character only',$charsPhrase,$fp,[$charA]);
    assert_bulk($result['total']===1&&$result['excluded']===2&&$result['changed']===1,
        'subset releases ONE player and excludes two others');
    assert_bulk((int)$db->query("SELECT ban FROM tob_char WHERE id=$charA")->fetchColumn()===0,
        'checked character opened');
    assert_bulk((int)$db->query("SELECT ban FROM tob_char WHERE id=$charAlt")->fetchColumn()===1,
        'UNCHECKED same-account character remains BANNED');
    assert_bulk((int)$db->query("SELECT ban FROM tob_char WHERE id=$charB")->fetchColumn()===1,
        'UNCHECKED other-account character remains BANNED');
    assert_bulk((int)$db->query("SELECT ban FROM tob_char WHERE id=$charGM")->fetchColumn()===1,
        'GM character stays banned');
    assert_bulk((int)$db->query("SELECT ban FROM tob_char WHERE id=$charSpecial")->fetchColumn()===3,
        'special character state stays unchanged');
    $next=kp_game_moderation_bulk_targets($db,'character');
    $result=kp_game_moderation_bulk_unban(
        $db,$admin,'character','Release both remaining characters',$charsPhrase,
        kp_game_moderation_bulk_fingerprint($next),[$charAlt,$charB]);
    assert_bulk($result['changed']===2&&$result['total']===2,'explicit select-all subset can release remaining characters');
    assert_bulk((int)$db->query("SELECT COUNT(*) FROM team_user WHERE id IN ($aid,$bid) AND ban=0")->fetchColumn()===2,
        'player releases NEVER modify account ban flags');
    assert_bulk((int)$db->query("SELECT COUNT(*) FROM tob_char WHERE del=1 AND id BETWEEN $charA AND $charSpecial")->fetchColumn()===5,
        'ban operations never modify deletion state');

    $logs=$db->query("SELECT target_type,target_id,old_ban,new_ban,result FROM kp_game_moderation_log
                      WHERE target_name LIKE 'Bulk%' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    assert_bulk(count($logs)===5,'audit includes only five selected subjects (none for excluded)');
    $acc=0;$chr=0;
    foreach($logs as $event){
        assert_bulk($event['result']==='applied'&&(int)$event['old_ban']===1&&(int)$event['new_ban']===0,
            'audit saves successful 1 to 0 transition');
        if($event['target_type']==='account')$acc++;
        if($event['target_type']==='character')$chr++;
    }
    assert_bulk($acc===2&&$chr===3,'account and character histories remain distinct');
    echo "PASS: selected-only bulk game moderation integration suite\n";
}finally{
    if(kp_game_moderation_log_available($db)){
        $db->exec("DELETE FROM kp_game_moderation_log WHERE target_name LIKE 'Bulk%'");
    }
    $db->exec('DELETE FROM tob_char WHERE id BETWEEN 88201 AND 88205');
    $db->exec('DELETE FROM team_user WHERE id BETWEEN 88101 AND 88104');
}
