<?php
/**
 * Ban/unban integration on disposable MariaDB fixture.
 * NEVER run against a real game database.
 */
if (getenv('KPAH_DB_NAME') !== 'kpah_route_test') {
    fwrite(STDERR,"Refusing to change non-test game DB\n");
    exit(1);
}
require dirname(__DIR__).'/app/includes/bootstrap.php';
require_once KP_ROOT.'/app/includes/game_account_admin.php';
function mod_expect($ok,string $label): void {
    if (!$ok) {fwrite(STDERR,"FAILED: $label\n");exit(1);}
    echo "PASS: $label\n";
}
function mod_throws(callable $fn,string $message): void {
    try{$fn();}catch(RuntimeException $e){mod_expect(strpos($e->getMessage(),$message)!==false,'rejects '.$message);return;}
    mod_expect(false,'expected failure '.$message);
}
$pdo=kp_db();
$admin=['id'=>1,'username'=>'ci_moderator'];
$accountId=70701;
$primary=70711;
$secondary=70712;
$privileged=70713;
try{
    $pdo->prepare("INSERT INTO team_user (id,userid,provider,username,password,ban)
        VALUES (?,?,?,?,?,0)")->execute([$accountId,770701,0,'moderation_fixture','*TEST']);
    $insertChar=$pdo->prepare("INSERT INTO tob_char (id,charname,userId,ban,isAdmin,del)
                               VALUES (?,?,?,0,?,1)");
    $insertChar->execute([$primary,'ModMain',$accountId,0]);
    $insertChar->execute([$secondary,'ModAlt',$accountId,0]);
    $insertChar->execute([$privileged,'ModGM',$accountId,1]);

    mod_throws(function()use($pdo,$admin,$primary){
        kp_game_moderation_change($pdo,$admin,'character',$primary,'ban','Reason long enough','WrongName');
    },'Tên xác nhận');
    mod_throws(function()use($pdo,$admin,$primary){
        kp_game_moderation_change($pdo,$admin,'character',$primary,'ban','short','ModMain');
    },'Lý do');
    mod_throws(function()use($pdo,$admin,$privileged){
        kp_game_moderation_change($pdo,$admin,'character',$privileged,'ban','Admin character protected','ModGM');
    },'quản trị game');
    mod_throws(function()use($pdo,$admin,$accountId){
        kp_game_moderation_change($pdo,$admin,'account',$accountId,'ban','Protect GM owner','moderation_fixture');
    },'quản trị game');
    // Remove only the test GM so remaining fixtures can be moderated.
    $pdo->prepare('DELETE FROM tob_char WHERE id=?')->execute([$privileged]);
    mod_expect(strpos(kp_game_moderation_change(
        $pdo,$admin,'character',$primary,'ban','Abuse test account','ModMain'
    ),'đã bị ban')!==false,'character ban applied');
    mod_expect((int)$pdo->query('SELECT ban FROM tob_char WHERE id='.$primary)->fetchColumn()===1,
        'target character ban set to 1');
    mod_expect((int)$pdo->query('SELECT ban FROM tob_char WHERE id='.$secondary)->fetchColumn()===0,
        'sibling character remains unbanned');
    mod_expect((int)$pdo->query('SELECT ban FROM team_user WHERE id='.$accountId)->fetchColumn()===0,
        'account remains unbanned when only character is banned');
    mod_expect((int)$pdo->query('SELECT del FROM tob_char WHERE id='.$primary)->fetchColumn()===1,
        'character deletion flag is untouched');
    mod_throws(function()use($pdo,$admin,$primary){
        kp_game_moderation_change($pdo,$admin,'character',$primary,'ban','Duplicate ban test','ModMain');
    },'đã đúng');
    kp_game_moderation_change($pdo,$admin,'account',$accountId,'ban','Account protection test','moderation_fixture');
    mod_expect((int)$pdo->query('SELECT ban FROM team_user WHERE id='.$accountId)->fetchColumn()===1,
        'account ban is independent from character ban');
    mod_expect((int)$pdo->query('SELECT ban FROM tob_char WHERE id='.$secondary)->fetchColumn()===0,
        'account ban does not ban sibling characters');
    kp_game_moderation_change($pdo,$admin,'account',$accountId,'unban','Release account test','moderation_fixture');
    mod_expect((int)$pdo->query('SELECT ban FROM team_user WHERE id='.$accountId)->fetchColumn()===0,
        'account ban reversible without altering character ban');
    mod_expect((int)$pdo->query('SELECT ban FROM tob_char WHERE id='.$primary)->fetchColumn()===1,
        'previously banned character stays banned when account is unbanned');
    kp_game_moderation_change($pdo,$admin,'character',$primary,'unban','Release character test','ModMain');
    mod_expect((int)$pdo->query('SELECT ban FROM tob_char WHERE id='.$primary)->fetchColumn()===0,
        'individual character ban reversible');
    $pdo->prepare('UPDATE team_user SET ban=3 WHERE id=?')->execute([$accountId]);
    mod_throws(function()use($pdo,$admin,$accountId){
        kp_game_moderation_change($pdo,$admin,'account',$accountId,'unban','Reserved ban protection','moderation_fixture');
    },'giá trị đặc biệt');
    mod_expect((int)$pdo->query('SELECT ban FROM team_user WHERE id='.$accountId)->fetchColumn()===3,
        'reserved account ban=3 not clobbered');

    mod_expect(kp_game_moderation_log_available($pdo),'audit table installed automatically');
    $events=kp_game_moderation_history($pdo,$accountId);
    mod_expect(count($events)===4,'exactly four successful audit records');
    foreach($events as $event) {
        mod_expect($event['result']==='applied' && $event['admin_name']==='ci_moderator',
            'audit contains applied moderation result and moderator');
    }
    $summary=$pdo->query("SELECT target_type,COUNT(*) AS n FROM kp_game_moderation_log
                       WHERE owner_id=$accountId GROUP BY target_type")->fetchAll(PDO::FETCH_KEY_PAIR);
    mod_expect((int)$summary['account']===2 && (int)$summary['character']===2,
        'account and character audit entries remain distinct');
    echo "PASS: game moderation integration suite\n";
}finally{
    $pdo->prepare('DELETE FROM kp_game_moderation_log WHERE owner_id=?')->execute([$accountId]);
    $pdo->prepare('DELETE FROM tob_char WHERE userId=?')->execute([$accountId]);
    $pdo->prepare('DELETE FROM team_user WHERE id=?')->execute([$accountId]);
}
