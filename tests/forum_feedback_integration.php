<?php
/** Database integration: only the disposable kpah_forum_test schema. */
if(getenv('KPAH_DB_NAME')!=='kpah_forum_test'){fwrite(STDERR,"Refusing non-test database\n");exit(1);}
require dirname(__DIR__).'/app/includes/bootstrap.php';
require_once KP_ROOT.'/app/includes/forum_feedback.php';
function forum_test($condition,$name){if(!$condition)throw new RuntimeException('FAIL forum: '.$name);echo 'PASS forum: '.$name."\n";}
$db=kp_db();
if(kp_forum_schema_name()!=='kpah_forum_test_data')throw new RuntimeException('Unsafe forum test database');
$db->exec('CREATE DATABASE IF NOT EXISTS `kpah_forum_test_data` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$db->exec('CREATE TABLE IF NOT EXISTS team_user (id INT NOT NULL PRIMARY KEY,username VARCHAR(40) NOT NULL,ban TINYINT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$db->exec("INSERT INTO team_user (id,username,ban) VALUES (101,'testplayer123',0),(102,'otheruser777',0) ON DUPLICATE KEY UPDATE username=VALUES(username),ban=VALUES(ban)");
// Verify the exact production recovery migration against the disposable game schema.
$noticeSql=preg_replace('/^--.*$/m','',(string)file_get_contents(KP_ROOT.'/database/forum_notifications_kpah.sql'));
$noticeSql=str_replace(chr(96).'kpah'.chr(96),chr(96).'kpah_forum_test'.chr(96),$noticeSql);
for($attempt=0;$attempt<2;$attempt++){
    foreach(preg_split('/;\\s*(?:\\r?\\n|$)/',$noticeSql) as $statement){
        $statement=trim($statement);
        if($statement!==''&&stripos($statement,'SELECT ')!==0)$db->exec($statement);
    }
}
foreach(['kp_admin_notifications','kp_user_notifications'] as $table){
    $st=$db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
    $st->execute(['kpah_forum_test',$table]);
    forum_test($st->fetchColumn()==='InnoDB','notification recovery creates InnoDB '.$table);
}
$centerSql=preg_replace('/^--.*$/m','',(string)file_get_contents(KP_ROOT.'/database/user_center.sql'));
foreach(preg_split('/;\\s*(?:\\r?\\n|$)/',$centerSql) as $st){if(trim($st)!=='')$db->exec($st);}
$db->exec('DELETE FROM kp_admin_notifications');
$db->exec('DELETE FROM kp_user_notifications');
$sql=preg_replace('/^--.*$/m','',(string)file_get_contents(KP_ROOT.'/database/forum_feedback.sql'));
$sql=str_replace(chr(96).'kpah_points'.chr(96),chr(96).'kpah_forum_test_data'.chr(96),$sql);
foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql) as $statement){if(trim($statement)!=='')$db->exec(kp_forum_sql($statement));}
foreach(['kp_forum_replies','kp_forum_actions','kp_forum_topics'] as $table)$db->exec(kp_forum_sql('DELETE FROM '.$table));
forum_test(kp_forum_mask_username('testplayer123')==='t****','username fixed alias');
forum_test(kp_forum_mask_username('t')===kp_forum_mask_username('testplayer123'),'mask length hides real length');
forum_test(kp_forum_mask_username('otheruser777')==='o****','other username');
forum_test(kp_forum_sensitive_content('Vui lòng kiểm tra email test@example.com'),'email held for moderation');
forum_test(kp_forum_sensitive_content('Mật khẩu bị lộ'),'secret held for moderation');
forum_test(!kp_forum_sensitive_content('Không thể qua bản đồ mới, nhân vật tự dịch chuyển.'),'ordinary bug is public');
forum_test(kp_forum_redact_username('Xin chào testplayer123!','testplayer123')==='Xin chào t****!','redact real owner in public text');
$token=str_repeat('a',64);
$id=kp_forum_create($db,101,'bug','Android','Lỗi của testplayer123 trong túi','Nhân vật testplayer123 bị lỗi khi đổi tiêu tím sau khi nhấn thao tác.', $token);
$st=$db->prepare(kp_forum_sql('SELECT public_alias,title,body,visibility FROM kp_forum_topics WHERE id=?'));$st->execute([$id]);$row=$st->fetch(PDO::FETCH_ASSOC);
forum_test($row['public_alias']==='t****','stored public alias masked');
forum_test(strpos($row['title'],'testplayer123')===false&&strpos($row['body'],'testplayer123')===false,'raw username not stored in title/body');
forum_test($row['visibility']==='public','normal bug published');
$notifications=$db->query("SELECT title,body,category,link_path,is_read FROM kp_admin_notifications ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
forum_test(count($notifications)===1,'one new ticket creates exactly one admin notification');
forum_test(strpos($notifications[0]['title'],'Báo lỗi mới #'.$id)!==false,'admin notice identifies new bug');
forum_test($notifications[0]['link_path']==='/quan-tri/forum'&&(int)$notifications[0]['is_read']===0,'admin notice unread with valid link');
forum_test(strpos($notifications[0]['body'],'testplayer123')===false,'admin notice does not include real username');

$pub=kp_forum_public_topics($db,'bug','',50);
forum_test(count($pub)===1 && !array_key_exists('user_id',$pub[0])&&!array_key_exists('account_name',$pub[0]),'public query omits identity and owner id');
forum_test(kp_forum_public_count($db,'bug')===1,'public feed count');
$admin=kp_forum_admin_list($db);
forum_test(count($admin)===1&&$admin[0]['account_name']==='testplayer123','real username only in admin helper');
$res=kp_forum_admin_update($db,$id,42,'reviewing','public','Chào testplayer123, chúng tôi đã tiếp nhận.',str_repeat('b',64));
forum_test($res['changed']===true&&$res['user_id']===101,'admin update returns real recipient internally');
$st=$db->prepare('SELECT user_id,title,body,category,link_path,is_read FROM kp_user_notifications WHERE user_id=? ORDER BY id');
$st->execute([101]);$userNotices=$st->fetchAll(PDO::FETCH_ASSOC);
forum_test(count($userNotices)===1,'one admin reply creates exactly one player notification');
forum_test((int)$userNotices[0]['is_read']===0&&$userNotices[0]['link_path']==='/dien-dan','player notice unread and links to forum');
forum_test(strpos($userNotices[0]['title'],'Admin đã phản hồi')!==false,'specific admin response title');
forum_test(strpos($userNotices[0]['body'],'testplayer123')===false,'player notice contains no clear username');
forum_test((int)$db->query('SELECT COUNT(*) FROM kp_user_notifications WHERE user_id=102')->fetchColumn()===0,'other player receives no reply notification');

$replies=kp_forum_public_replies($db,[$id]);forum_test(count($replies[$id])===1,'official reply visible');
forum_test(strpos($replies[$id][0]['body'],'testplayer123')===false&&strpos($replies[$id][0]['body'],'t****')!==false,'admin reply redacts full username');
forum_test(!array_key_exists('admin_id',$replies[$id][0]),'public reply omits staff identifier');
$mine=kp_forum_own_topics($db,101);
forum_test(count($mine)===1&&$mine[0]['id']===$id,'owner can list own reports');
forum_test(kp_forum_own_topics($db,102)===[],'another user cannot list owner reports');
$privateReplies=kp_forum_own_replies($db,101,[$id]);
forum_test(count($privateReplies[$id])===1,'owner can read official reply');
forum_test(kp_forum_own_replies($db,102,[$id])===[],'another user cannot read private reply');
forum_test(kp_forum_admin_count($db)===1,'admin total count available');

$res=kp_forum_admin_update($db,$id,42,'resolved','hidden','',str_repeat('c',64));
forum_test(kp_forum_public_count($db)===0&&kp_forum_public_replies($db,[$id])===[],'hiding topic also hides its replies');
forum_test(count(kp_forum_own_replies($db,101,[$id])[$id])===1,'owner still reads reply when public topic hidden');
forum_test((int)$db->query('SELECT COUNT(*) FROM kp_user_notifications WHERE user_id=101')->fetchColumn()===2,'status or visibility change sends new player notice');
$st=$db->query('SELECT body FROM kp_user_notifications WHERE user_id=101 ORDER BY id DESC LIMIT 1');
forum_test(strpos((string)$st->fetchColumn(),'Bài của tôi')!==false,'hidden report notice instructs owner to use private inbox');
$res=kp_forum_admin_update($db,$id,42,'resolved','hidden','',str_repeat('f',64));
forum_test($res['changed']===false,'repeated status-only save is no-op');
forum_test((int)$db->query('SELECT COUNT(*) FROM kp_user_notifications WHERE user_id=101')->fetchColumn()===2,'no notification for unchanged status');

try{kp_forum_admin_update($db,$id,42,'hacked','public','',str_repeat('d',64));forum_test(false,'invalid admin status accepted');}
catch(RuntimeException $expected){forum_test(true,'invalid admin status rejected');}
$secretId=kp_forum_create($db,102,'suggestion','Web','Đề nghị hỗ trợ tài khoản','Liên hệ qua email myplayer@example.com để trao đổi thêm.',str_repeat('e',64));
forum_test(kp_forum_public_count($db)===0,'PII automatically held, no accidental public leak');
forum_test(count(kp_forum_admin_list($db))===2,'admin can see hidden report');
forum_test((int)$db->query('SELECT COUNT(*) FROM kp_admin_notifications')->fetchColumn()===2,'two distinct reports create two admin notifications');

try{kp_forum_create($db,101,'bug','Android','Lỗi lặp nội dung','Gửi lại với mã đơn trùng cần bị chặn an toàn.',str_repeat('a',64));forum_test(false,'duplicate nonce accepted');}
catch(PDOException $expected){forum_test(true,'duplicate submission nonce prevented at DB constraint');}
forum_test((int)$db->query('SELECT COUNT(*) FROM kp_admin_notifications')->fetchColumn()===2,'duplicate request does not duplicate admin notifications');
forum_test(!$db->inTransaction(),'database transaction always closed');

$columns=$db->query(kp_forum_sql('SHOW COLUMNS FROM kp_forum_topics'))->fetchAll(PDO::FETCH_COLUMN);
forum_test(!in_array('username',$columns,true)&&!in_array('email',$columns,true),'forum data table does not duplicate personal identifiers');
// Both tables are InnoDB. A failed notice must roll back its Forum mutation.
$beforeTopics=kp_forum_admin_count($db);
$db->exec('DROP TABLE kp_admin_notifications');
try{
    kp_forum_create($db,101,'bug','Android','Lỗi thử rollback','Không thể hoàn tất bước ghi thông báo nên toàn bộ bài phải rollback.',str_repeat('6',64));
    forum_test(false,'forum topic saved without admin notice');
}catch(PDOException $expected){forum_test(true,'new report rolls back when admin notification insert fails');}
forum_test(kp_forum_admin_count($db)===$beforeTopics,'failed notification leaves no orphan topic');
forum_test(!$db->inTransaction(),'failed report transaction closed');

$beforeReplies=count(kp_forum_own_replies($db,101,[$id])[$id]);
$db->exec('DROP TABLE kp_user_notifications');
try{
    kp_forum_admin_update($db,$id,42,'reviewing','hidden','Đang xử lý lỗi và kiểm tra tài khoản.',str_repeat('7',64));
    forum_test(false,'forum reply saved without player notice');
}catch(PDOException $expected){forum_test(true,'admin reply rolls back when player notification insert fails');}
forum_test(count(kp_forum_own_replies($db,101,[$id])[$id])===$beforeReplies,'failed player notification leaves no orphan admin reply');
$st=$db->prepare(kp_forum_sql('SELECT status FROM kp_forum_topics WHERE id=?'));
$st->execute([$id]);forum_test($st->fetchColumn()==='resolved','failed notification also rolls back status change');
forum_test(!$db->inTransaction(),'failed admin transaction closed');
echo "Forum integration COMPLETE\n";
