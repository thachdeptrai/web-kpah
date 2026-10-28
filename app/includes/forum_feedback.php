<?php
/**
 * Public bug reports and suggestions. Public queries never join team_user or
 * return user_id: only a pre-masked alias is exposed. Admin-only queries may
 * resolve the real username after kp_admin_require().
 */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }
require_once __DIR__.'/forum_storage.php';

function kp_forum_mask_username($username): string {
    $name=strtolower(trim((string)$username));
    // Fixed masking length prevents leaking exact account-name length.
    if (!preg_match('/^[a-z0-9_]/D',substr($name,0,1))) return '*****';
    return substr($name,0,1).'****';
}
function kp_forum_redact_username(string $text,string $username): string {
    if($username===''||strlen($username)<3)return $text;
    $pattern='/(?<![A-Za-z0-9_])'.preg_quote($username,'/').'(?![A-Za-z0-9_])/iu';
    $value=preg_replace($pattern,kp_forum_mask_username($username),$text);
    return is_string($value)?$value:$text;
}
function kp_forum_valid_text($value,int $min,int $max): string {
    if(!is_string($value))throw new RuntimeException('Nội dung không hợp lệ.');
    $text=trim(str_replace(["\r\n","\r"],"\n",$value));
    if(!preg_match('//u',$text) || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$text))
        throw new RuntimeException('Nội dung chứa ký tự không hợp lệ.');
    $length=function_exists('mb_strlen')?mb_strlen($text,'UTF-8'):strlen($text);
    if($length<$min||$length>$max)throw new RuntimeException('Độ dài nội dung không hợp lệ.');
    return $text;
}
function kp_forum_sensitive_content(string $text): bool {
    // Conservative hold: email, contact number, secrets, payment data,
    // host/SQL vulnerabilities. Admin can review before publishing.
    return (bool)preg_match('/(?:[\w.+-]+@[\w.-]+\.[a-z]{2,}|(?:\+?84|0)[\d .-]{8,15}\d|mật\s*khẩu|password|api[_ -]?key|token|otp|sql\s*injection|xss|rce|lỗ\s*hổng|lộ\s*dữ\s*liệu|tên\s*tài\s*khoản|username|access[_ -]?key)/iu',$text);
}
function kp_forum_create(PDO $db,int $userId,string $kind,string $platform,string $title,string $body,string $nonce): int {
    if($userId<1||!in_array($kind,['bug','suggestion'],true)||!in_array($platform,['Android','iOS','Windows','J2ME','Web','Khác'],true))
        throw new RuntimeException('Thông tin gửi không hợp lệ.');
    $title=kp_forum_valid_text($title,8,140);
    $body=kp_forum_valid_text($body,20,5000);
    if(!preg_match('/^[a-f0-9]{64}$/D',$nonce))throw new RuntimeException('Mã phiên gửi không hợp lệ.');
    $st=$db->prepare(kp_forum_sql('SELECT username FROM team_user WHERE id=? AND ban=0 LIMIT 1'));
    $st->execute([$userId]);$username=$st->fetchColumn();
    if(!is_string($username)||$username==='')throw new RuntimeException('Tài khoản không hợp lệ.');
    $title=kp_forum_redact_username($title,$username);
    $body=kp_forum_redact_username($body,$username);
    $visibility=kp_forum_sensitive_content($title."\n".$body)?'hidden':'public';
    // Do not store raw usernames or addresses in the forum schema.
    $st=$db->prepare(kp_forum_sql('INSERT INTO kp_forum_topics (user_id,public_alias,kind,platform,title,body,visibility,submission_token) VALUES (?,?,?,?,?,?,?,?)'));
    $st->execute([$userId,kp_forum_mask_username($username),$kind,$platform,$title,$body,$visibility,$nonce]);
    return (int)$db->lastInsertId();
}
function kp_forum_public_topics(PDO $db,string $kind='',string $status='',int $limit=40,int $page=1): array {
    $where="t.visibility='public'";$params=[];
    if(in_array($kind,['bug','suggestion'],true)){$where.=' AND t.kind=?';$params[]=$kind;}
    if(in_array($status,['new','reviewing','resolved','declined'],true)){$where.=' AND t.status=?';$params[]=$status;}
    $limit=max(1,min(60,$limit));$offset=(max(1,min(10000,$page))-1)*$limit;
    $sql="SELECT t.id,t.public_alias,t.kind,t.platform,t.title,t.body,t.status,t.created_at,t.updated_at
           FROM kp_forum_topics t WHERE ".$where." ORDER BY t.id DESC LIMIT ".$limit." OFFSET ".$offset;
    $st=$db->prepare(kp_forum_sql($sql));$st->execute($params);return $st->fetchAll(PDO::FETCH_ASSOC);
}
function kp_forum_public_count(PDO $db,string $kind='',string $status=''): int {
    $where="visibility='public'";$values=[];
    if(in_array($kind,['bug','suggestion'],true)){$where.=' AND kind=?';$values[]=$kind;}
    if(in_array($status,['new','reviewing','resolved','declined'],true)){$where.=' AND status=?';$values[]=$status;}
    $st=$db->prepare(kp_forum_sql('SELECT COUNT(*) FROM kp_forum_topics WHERE '.$where));
    $st->execute($values);return (int)$st->fetchColumn();
}
function kp_forum_public_replies(PDO $db,array $ids): array {
    $ids=array_values(array_unique(array_filter(array_map('intval',$ids),static function($n){return $n>0;})));
    if(!$ids)return [];
    $sql='SELECT r.topic_id,r.body,r.created_at FROM kp_forum_replies r
          INNER JOIN kp_forum_topics t ON t.id=r.topic_id AND t.visibility=\'public\'
          WHERE r.topic_id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY r.id ASC';
    $st=$db->prepare(kp_forum_sql($sql));$st->execute($ids);
    $result=[];foreach($st->fetchAll(PDO::FETCH_ASSOC) as $row)$result[(int)$row['topic_id']][]=$row;
    return $result;
}
function kp_forum_own_topics(PDO $db,int $userId,int $limit=20): array {
    if($userId<1)return [];
    $st=$db->prepare(kp_forum_sql('SELECT id,kind,title,status,visibility,created_at FROM kp_forum_topics WHERE user_id=? ORDER BY id DESC LIMIT '.max(1,min(50,$limit))));
    $st->execute([$userId]);return $st->fetchAll(PDO::FETCH_ASSOC);
}
function kp_forum_own_replies(PDO $db,int $userId,array $topicIds): array {
    if($userId<1)return [];
    $ids=array_values(array_unique(array_filter(array_map('intval',$topicIds),static function($x){return $x>0;})));
    if(!$ids)return [];
    $sql='SELECT r.topic_id,r.body,r.created_at FROM kp_forum_replies r
          INNER JOIN kp_forum_topics t ON t.id=r.topic_id
          WHERE t.user_id=? AND r.topic_id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY r.id ASC';
    $st=$db->prepare(kp_forum_sql($sql));$st->execute(array_merge([$userId],$ids));
    $rows=[];foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r)$rows[(int)$r['topic_id']][]=$r;
    return $rows;
}
function kp_forum_admin_count(PDO $db,string $status=''): int {
    $where=in_array($status,['new','reviewing','resolved','declined'],true)?'WHERE status=?':'';
    $st=$db->prepare(kp_forum_sql('SELECT COUNT(*) FROM kp_forum_topics '.$where));
    $st->execute($where?[$status]:[]);return (int)$st->fetchColumn();
}
function kp_forum_admin_list(PDO $db,string $status='',int $limit=100,int $page=1): array {
    $limit=max(1,min(150,$limit));$offset=(max(1,min(10000,$page))-1)*$limit;
    // Admin endpoint only. Real usernames never enter public queries.
    $where=in_array($status,['new','reviewing','resolved','declined'],true)?'WHERE t.status=?':'';
    $st=$db->prepare(kp_forum_sql('SELECT t.*,u.username AS account_name,
      (SELECT COUNT(*) FROM kp_forum_replies r WHERE r.topic_id=t.id) AS reply_count
      FROM kp_forum_topics t LEFT JOIN team_user u ON u.id=t.user_id '.$where.'
      ORDER BY FIELD(t.status,\'new\',\'reviewing\',\'resolved\',\'declined\'),t.id DESC LIMIT '.$limit.' OFFSET '.$offset));
    $st->execute($where?[$status]:[]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
function kp_forum_admin_update(PDO $db,int $topicId,int $adminId,string $status,string $visibility,string $reply,string $nonce): array {
    if($topicId<1||$adminId<1||!in_array($status,['new','reviewing','resolved','declined'],true)
       ||!in_array($visibility,['public','hidden'],true))
        throw new RuntimeException('Trạng thái quản trị không hợp lệ.');
    $reply=trim($reply);
    if($reply!=='')$reply=kp_forum_valid_text($reply,3,3000);
    if($reply!==''&&!preg_match('/^[a-f0-9]{64}$/D',$nonce))
        throw new RuntimeException('Mã phản hồi không hợp lệ.');
    $db->beginTransaction();
    try {
        $st=$db->prepare(kp_forum_sql('SELECT user_id,status,visibility FROM kp_forum_topics WHERE id=? FOR UPDATE'));
        $st->execute([$topicId]);$old=$st->fetch(PDO::FETCH_ASSOC);
        if(!$old)throw new RuntimeException('Không tìm thấy bài viết.');
        if($status!==(string)$old['status']||$visibility!==(string)$old['visibility']){
            $db->prepare(kp_forum_sql('UPDATE kp_forum_topics SET status=?,visibility=? WHERE id=?'))->execute([$status,$visibility,$topicId]);
            $db->prepare(kp_forum_sql('INSERT INTO kp_forum_actions (topic_id,admin_id,previous_status,new_status,previous_visibility,new_visibility) VALUES (?,?,?,?,?,?)'))
                ->execute([$topicId,$adminId,$old['status'],$status,$old['visibility'],$visibility]);
        }
        if($reply!==''){
            $lookup=$db->prepare(kp_forum_sql('SELECT username FROM team_user WHERE id=? LIMIT 1'));
            $lookup->execute([(int)$old['user_id']]);$owner=$lookup->fetchColumn();
            if(is_string($owner))$reply=kp_forum_redact_username($reply,$owner);
            if($visibility==='public' && kp_forum_sensitive_content($reply))
                throw new RuntimeException('Phản hồi nhạy cảm phải giữ bài ở trạng thái ẩn.');
            $st=$db->prepare(kp_forum_sql('INSERT INTO kp_forum_replies (topic_id,admin_id,body,reply_token) VALUES (?,?,?,?)'));
            $st->execute([$topicId,$adminId,$reply,$nonce]);
        }
        $db->commit();
        return ['user_id'=>(int)$old['user_id'],'changed'=>$status!==$old['status']||$visibility!==$old['visibility']||$reply!==''];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
