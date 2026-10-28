<?php
/** Database integration: only the disposable kpah_forum_test schema. */
if(getenv('KPAH_DB_NAME')!=='kpah_forum_test'){fwrite(STDERR,"Refusing non-test database\n");exit(1);}
require dirname(__DIR__).'/app/includes/bootstrap.php';
require_once KP_ROOT.'/app/includes/forum_feedback.php';
function forum_test($condition,$name){if(!$condition)throw new RuntimeException('FAIL forum: '.$name);echo 'PASS forum: '.$name."\n";}
$db=kp_db();
$db->exec('CREATE TABLE IF NOT EXISTS team_user (id INT NOT NULL PRIMARY KEY,username VARCHAR(40) NOT NULL,ban TINYINT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$db->exec("INSERT INTO team_user (id,username,ban) VALUES (101,'testplayer123',0),(102,'otheruser777',0) ON DUPLICATE KEY UPDATE username=VALUES(username),ban=VALUES(ban)");
$sql=preg_replace('/^--.*$/m','',(string)file_get_contents(KP_ROOT.'/database/forum_feedback.sql'));
foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql) as $statement){if(trim($statement)!=='')$db->exec($statement);}
foreach(['kp_forum_replies','kp_forum_actions','kp_forum_topics'] as $table)$db->exec('DELETE FROM '.$table);
forum_test(kp_forum_mask_username('testplayer123')==='t****','username fixed alias');
forum_test(kp_forum_mask_username('t')===kp_forum_mask_username('testplayer123'),'mask length hides real length');
forum_test(kp_forum_mask_username('otheruser777')==='o****','other username');
forum_test(kp_forum_sensitive_content('Vui lòng kiểm tra email test@example.com'),'email held for moderation');
forum_test(kp_forum_sensitive_content('Mật khẩu bị lộ'),'secret held for moderation');
forum_test(!kp_forum_sensitive_content('Không thể qua bản đồ mới, nhân vật tự dịch chuyển.'),'ordinary bug is public');
forum_test(kp_forum_redact_username('Xin chào testplayer123!','testplayer123')==='Xin chào t****!','redact real owner in public text');
$token=str_repeat('a',64);
$id=kp_forum_create($db,101,'bug','Android','Lỗi của testplayer123 trong túi','Nhân vật testplayer123 bị lỗi khi đổi tiêu tím sau khi nhấn thao tác.', $token);
$st=$db->prepare('SELECT public_alias,title,body,visibility FROM kp_forum_topics WHERE id=?');$st->execute([$id]);$row=$st->fetch(PDO::FETCH_ASSOC);
forum_test($row['public_alias']==='t****','stored public alias masked');
forum_test(strpos($row['title'],'testplayer123')===false&&strpos($row['body'],'testplayer123')===false,'raw username not stored in title/body');
forum_test($row['visibility']==='public','normal bug published');
$pub=kp_forum_public_topics($db,'bug','',50);
forum_test(count($pub)===1 && !array_key_exists('user_id',$pub[0])&&!array_key_exists('account_name',$pub[0]),'public query omits identity and owner id');
forum_test(kp_forum_public_count($db,'bug')===1,'public feed count');
$admin=kp_forum_admin_list($db);
forum_test(count($admin)===1&&$admin[0]['account_name']==='testplayer123','real username only in admin helper');
$res=kp_forum_admin_update($db,$id,42,'reviewing','public','Chào testplayer123, chúng tôi đã tiếp nhận.',str_repeat('b',64));
forum_test($res['changed']===true&&$res['user_id']===101,'admin update returns real recipient internally');
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
try{kp_forum_admin_update($db,$id,42,'hacked','public','',str_repeat('d',64));forum_test(false,'invalid admin status accepted');}
catch(RuntimeException $expected){forum_test(true,'invalid admin status rejected');}
$secretId=kp_forum_create($db,102,'suggestion','Web','Đề nghị hỗ trợ tài khoản','Liên hệ qua email myplayer@example.com để trao đổi thêm.',str_repeat('e',64));
forum_test(kp_forum_public_count($db)===0,'PII automatically held, no accidental public leak');
forum_test(count(kp_forum_admin_list($db))===2,'admin can see hidden report');
try{kp_forum_create($db,101,'bug','Android','Lỗi lặp nội dung','Gửi lại với mã đơn trùng cần bị chặn an toàn.',str_repeat('a',64));forum_test(false,'duplicate nonce accepted');}
catch(PDOException $expected){forum_test(true,'duplicate submission nonce prevented at DB constraint');}
$columns=$db->query('SHOW COLUMNS FROM kp_forum_topics')->fetchAll(PDO::FETCH_COLUMN);
forum_test(!in_array('username',$columns,true)&&!in_array('email',$columns,true),'forum data table does not duplicate personal identifiers');
echo "Forum integration COMPLETE\n";
