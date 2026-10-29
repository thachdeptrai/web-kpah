<?php
/**
 * Verify the notification relocation using throwaway CI schemas only.
 */
if(getenv('KPAH_DB_NAME')!=='kpah_forum_test' ||
   getenv('KPAH_POINTS_DB_NAME')!=='kpah_forum_test_data'){
    fwrite(STDERR,"Refusing migration test outside the disposable CI database\n");exit(1);
}
require dirname(__DIR__).'/app/includes/bootstrap.php';
function relocation_check($ok,string $message):void{
    if(!$ok)throw new RuntimeException('FAIL relocation: '.$message);
    echo 'PASS relocation: '.$message."\n";
}
$db=kp_db();
$src='kpah_notification_move_source';
$dst='kpah_notification_move_target';
foreach([$src,$dst] as $schema)$db->exec('CREATE DATABASE IF NOT EXISTS '.$schema.' CHARACTER SET utf8mb4');
$ddl=(string)file_get_contents(KP_ROOT.'/database/forum_notifications_kpah.sql');
preg_match_all('/^CREATE TABLE IF NOT EXISTS.*?;/ms',$ddl,$definitions);
relocation_check(count($definitions[0])===2,'both production notification definitions exist');
foreach(['kp_admin_notifications','kp_user_notifications'] as $table){
    $db->exec('DROP TABLE IF EXISTS '.$src.'.'.$table);
    $db->exec('DROP TABLE IF EXISTS '.$dst.'.'.$table);
}
foreach($definitions[0] as $statement){
    $db->exec(str_replace(chr(96).'kpah'.chr(96),chr(96).$src.chr(96),$statement));
}
$db->exec("INSERT INTO ".$src.".kp_admin_notifications
    (id,title,body,category,link_path,is_read,created_at)
    VALUES (101,'Tin mới','Giữ nội dung','system','/quan-tri/forum',0,'2026-01-02 03:04:05')");
$db->exec("INSERT INTO ".$src.".kp_user_notifications
    (id,user_id,title,body,category,link_path,is_read,created_at)
    VALUES (305,88,'Phản hồi','Admin trả lời','system','/dien-dan',1,'2026-01-03 05:06:07')");
$script=(string)file_get_contents(KP_ROOT.'/database/forum_move_misplaced_notifications_to_kpah.sql');
relocation_check((bool)preg_match('/^RENAME TABLE\s+.+?;/ms',$script,$matched),'production script has RENAME TABLE');
$renamed=str_replace([chr(96).'kpah_points'.chr(96),chr(96).'kpah'.chr(96)],
    [chr(96).$src.chr(96),chr(96).$dst.chr(96)],$matched[0]);
relocation_check(strpos($renamed,'kp_forum_')===false,'forum content is not moved');

// A pre-existing destination must prevent the move without losing records.
$db->exec(str_replace(chr(96).'kpah'.chr(96),chr(96).$dst.chr(96),$definitions[0][0]));
$db->exec("INSERT INTO ".$dst.".kp_admin_notifications (id,title,body)
    VALUES (999,'Existing notice','Keep this')");
try{$db->exec($renamed);relocation_check(false,'rename did not reject occupied destination');}
catch(PDOException $expected){relocation_check(true,'rename rejects occupied destination');}
relocation_check((int)$db->query('SELECT COUNT(*) FROM '.$src.'.kp_admin_notifications')->fetchColumn()===1,'source retained after collision');
relocation_check((int)$db->query('SELECT COUNT(*) FROM '.$dst.'.kp_admin_notifications')->fetchColumn()===1,'target retained after collision');
relocation_check((int)$db->query('SELECT COUNT(*) FROM '.$src.'.kp_user_notifications')->fetchColumn()===1,'user source retained after collision');

// Remove only the disposable test collision table and perform the real move.
$db->exec('DROP TABLE '.$dst.'.kp_admin_notifications');
$db->exec($renamed);
foreach(['kp_admin_notifications','kp_user_notifications'] as $table){
    $st=$db->prepare('SELECT TABLE_SCHEMA,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA IN (?,?) AND TABLE_NAME=? ORDER BY TABLE_SCHEMA');
    $st->execute([$src,$dst,$table]);$rows=$st->fetchAll(PDO::FETCH_ASSOC);
    relocation_check(count($rows)===1 && $rows[0]['TABLE_SCHEMA']===$dst && $rows[0]['ENGINE']==='InnoDB',$table.' moved');
}
$admin=$db->query('SELECT id,body,is_read,created_at FROM '.$dst.'.kp_admin_notifications WHERE id=101')->fetch(PDO::FETCH_ASSOC);
relocation_check($admin && $admin['body']==='Giữ nội dung' && (int)$admin['is_read']===0
    && $admin['created_at']==='2026-01-02 03:04:05','admin contents, timestamp and unread state preserved');
$user=$db->query('SELECT id,user_id,is_read,created_at FROM '.$dst.'.kp_user_notifications WHERE id=305')->fetch(PDO::FETCH_ASSOC);
relocation_check($user && (int)$user['user_id']===88 && (int)$user['is_read']===1
    && $user['created_at']==='2026-01-03 05:06:07','user ID, ownership and read state preserved');
$db->exec("INSERT INTO ".$dst.".kp_admin_notifications(title,body) VALUES('Next','Notice')");
relocation_check((int)$db->lastInsertId()>101,'auto increment continues after move');
try{$db->exec($renamed);relocation_check(false,'rerun should fail safely');}
catch(PDOException $expected){
    relocation_check((int)$db->query('SELECT COUNT(*) FROM '.$dst.'.kp_admin_notifications')->fetchColumn()===2,
        'rerun does not erase migrated data');
}
echo "Notification relocation integration COMPLETE\n";
