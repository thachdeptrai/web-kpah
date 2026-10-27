<?php
/** Website loyalty Points, separate from any game-character progression. */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }
require_once __DIR__.'/user_center.php';
require_once __DIR__.'/points_recipient.php';
require_once __DIR__.'/points_audit.php';
require_once __DIR__.'/points_tiers.php';
require_once __DIR__.'/points_redemption_control.php';

/** Exactly one ledger row per verified payment, under an InnoDB transaction. */
function kp_points_award(int $userId, string $source, string $reference, int $amountVnd, $orderCreatedAt): bool {
    if ($userId < 1 || !in_array($source, ['payos','card'], true)
        || !preg_match('/^[a-zA-Z0-9_-]{1,80}$/D', $reference)
        || $amountVnd < 1000 || $amountVnd > 1000000000) {
        throw new InvalidArgumentException('Thông tin tích điểm không hợp lệ.');
    }
    $db = kp_db();
    $config = $db->query('SELECT activated_at,points_per_1000 FROM kp_points_program WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    if (!$config) return false; // Migration has not been initialized.
    $started = strtotime((string)$config['activated_at']);
    $created = is_numeric($orderCreatedAt) ? (int)$orderCreatedAt : strtotime((string)$orderCreatedAt);
    // Do not silently award Points on historical payments before program launch.
    if (!$created || !$started || $created < $started) return false;
    $rate = (int)$config['points_per_1000'];
    if ($rate < 1 || $rate > 100) throw new RuntimeException('Tỷ lệ Point chưa hợp lệ.');
    $points = intdiv($amountVnd, 1000) * $rate;
    if ($points < 1) return false;

    $db->beginTransaction();
    try {
        $st=$db->prepare('INSERT IGNORE INTO kp_points_wallet (user_id) VALUES (?)');
        $st->execute([$userId]);
        $st=$db->prepare('SELECT balance FROM kp_points_wallet WHERE user_id=? FOR UPDATE');
        $st->execute([$userId]);
        $before=$st->fetchColumn();
        if ($before===false) throw new RuntimeException('Ví Point chưa sẵn sàng.');
        $st=$db->prepare('INSERT IGNORE INTO kp_points_ledger (user_id,points_delta,source_type,source_ref,amount_vnd,note) VALUES (?,?,?,?,?,?)');
        $st->execute([$userId,$points,$source,$reference,$amountVnd,'Tích Point từ giao dịch hợp lệ']);
        if ($st->rowCount()!==1) { $db->commit(); return false; }
        $st=$db->prepare('UPDATE kp_points_wallet SET balance=balance+?,total_earned=total_earned+? WHERE user_id=?');
        $st->execute([$points,$points,$userId]);
        if ($st->rowCount()!==1) throw new RuntimeException('Chưa hoàn tất cập nhật ví Point.');
        kp_points_audit_append($db,$userId,null,null,'award',$source.':'.$reference,$points,(int)$before,(int)$before+$points);
        $db->commit();
        try {
            kp_center_notify($userId,'Bạn vừa nhận Point',
                'Đã cộng '.number_format($points,0,',','.').' Point từ giao dịch nạp hợp lệ.',
                'payment','/diem-tich-luy');
        } catch(Throwable $notifyError) {
            if(function_exists('kp_log'))kp_log('Point award notification failed after commit',$notifyError);
        }
        return true;
    } catch(Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
}

function kp_points_redeem(int $userId, int $rewardId, string $requestToken, int $charId=0): int {
    if ($userId < 1 || $rewardId < 1 || !preg_match('/^[a-f0-9]{32}$/D',$requestToken))
        throw new InvalidArgumentException('Yêu cầu đổi quà không hợp lệ.');
    $db=kp_db();
    $db->beginTransaction();
    try {
        $st=$db->prepare('SELECT id,user_id FROM kp_points_redemptions WHERE request_token=? LIMIT 1');
        $st->execute([$requestToken]);$previous=$st->fetch(PDO::FETCH_ASSOC);
        if($previous) {
            if((int)$previous['user_id']!==$userId)throw new RuntimeException('Yêu cầu đổi quà không hợp lệ.');
            $db->commit();return (int)$previous['id'];
        }
        // Global maintenance mode: preserve replay of an already-created order,
        // but block every NEW redemption before touching stock or balances.
        if(!kp_points_redemption_enabled($db,true))
            throw new RuntimeException('Chức năng hiện đang cập nhật');
        $target=null;
        if(kp_points_target_enabled($db))$target=kp_points_validate_character($db,$userId,$charId);
        $st=$db->prepare('SELECT id,title,points_cost,stock FROM kp_points_rewards WHERE id=? AND is_active=1 FOR UPDATE');
        $st->execute([$rewardId]);$reward=$st->fetch(PDO::FETCH_ASSOC);
        if(!$reward)throw new RuntimeException('Quà tặng này chưa mở đổi.');
        $cost=(int)$reward['points_cost'];
        if($cost<1)throw new RuntimeException('Giá Point không hợp lệ.');
        if($reward['stock']!==null && (int)$reward['stock']<1)throw new RuntimeException('Quà đã hết.');
        $db->prepare('INSERT IGNORE INTO kp_points_wallet (user_id) VALUES (?)')->execute([$userId]);
        $st=$db->prepare('SELECT balance,total_earned FROM kp_points_wallet WHERE user_id=? FOR UPDATE');
        $st->execute([$userId]);$wallet=$st->fetch(PDO::FETCH_ASSOC);
        if(!$wallet || (int)$wallet['balance']<$cost)throw new RuntimeException('Bạn chưa đủ Point để đổi quà.');
        $balance=(int)$wallet['balance'];
        if(kp_points_member_tier((int)$wallet['total_earned'])['id']<kp_points_reward_min_tier($db,$rewardId)){
            throw new RuntimeException('Hạng thành viên chưa đủ để đổi phần quà này.');
        }
        $st=$db->prepare('INSERT INTO kp_points_redemptions (user_id,reward_id,reward_title,points_cost,request_token,status) VALUES (?,?,?,?,?,?)');
        $st->execute([$userId,$rewardId,$reward['title'],$cost,$requestToken,'pending']);
        $id=(int)$db->lastInsertId();
        if($target!==null)kp_points_save_target($db,$id,$target);
        $st=$db->prepare('UPDATE kp_points_wallet SET balance=balance-?,total_spent=total_spent+? WHERE user_id=? AND balance>=?');
        $st->execute([$cost,$cost,$userId,$cost]);
        if($st->rowCount()!==1)throw new RuntimeException('Không trừ được Point.');
        if($reward['stock']!==null){
            $st=$db->prepare('UPDATE kp_points_rewards SET stock=stock-1 WHERE id=? AND stock>0');
            $st->execute([$rewardId]);
            if($st->rowCount()!==1)throw new RuntimeException('Quà đã hết.');
        }
        $st=$db->prepare('INSERT INTO kp_points_ledger (user_id,points_delta,source_type,source_ref,note) VALUES (?,?,?,?,?)');
        $st->execute([$userId,-$cost,'redeem',(string)$id,'Đổi quà: '.$reward['title']]);
        kp_points_audit_append($db,$userId,null,$id,'redeem',(string)$id,-$cost,(int)$balance,(int)$balance-$cost);
        $db->commit();
        try {
            kp_center_notify($userId,'Yêu cầu đổi quà đang chờ duyệt',
                'Đơn #'.$id.' · '.$reward['title'].' đã được gửi. Point đã được giữ và quản trị viên sẽ kiểm tra.',
                'gift','/diem-tich-luy');
        } catch(Throwable $notifyError) {
            if(function_exists('kp_log'))kp_log('Point redeem user notification failed after commit',$notifyError);
        }
        try {
            kp_admin_notify('Có 1 đơn đổi quà đang chờ duyệt',
                'Đơn #'.$id.' · tài khoản ID '.$userId.' yêu cầu đổi '.$reward['title'].'.',
                'reward','/quan-tri/diem-tich-luy');
        } catch(Throwable $notifyError) {
            if(function_exists('kp_log'))kp_log('Point redeem admin notification failed after commit',$notifyError);
        }
        return $id;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

/** Admin-confirmed manual fulfillment or an atomic refund of pending rewards. */
function kp_points_workflow_enabled(PDO $db): bool {
    try {
        $st=$db->query("SHOW TABLES LIKE 'kp_points_redemption_events'");
        return $st && $st->fetchColumn()!==false;
    } catch (Throwable $e) { return false; }
}

/**
 * Multi-step manual delivery: pending -> approved -> delivering -> fulfilled.
 * Rejection with atomic refund is allowed before delivery begins only.
 * Existing installations without the optional migration retain legacy review.
 */
function kp_points_review_redemption(int $redemptionId, int $adminId, string $action, string $note): string {
    $note=trim($note);
    if($redemptionId<1 || $adminId<1
        || !in_array($action,['approved','delivering','fulfilled','rejected'],true)
        || $note==='' || strlen($note)>200)
        throw new InvalidArgumentException('Xác nhận đổi quà không hợp lệ.');
    $db=kp_db();
    $advanced=kp_points_workflow_enabled($db);
    $db->beginTransaction();
    try {
        $st=$db->prepare('SELECT * FROM kp_points_redemptions WHERE id=? FOR UPDATE');
        $st->execute([$redemptionId]);$redemption=$st->fetch(PDO::FETCH_ASSOC);
        if(!$redemption)throw new RuntimeException('Không tìm thấy yêu cầu đổi quà.');
        $old=(string)$redemption['status'];
        $allowed=$advanced
            ? ['pending'=>['approved','rejected'],'approved'=>['delivering','rejected'],'delivering'=>['fulfilled']]
            : ['pending'=>['fulfilled','rejected']];
        if(!in_array($action,$allowed[$old]??[],true)) {
            throw new RuntimeException('Trạng thái đã thay đổi hoặc bước phát quà không hợp lệ. Không xử lý lặp.');
        }
        if($action==='rejected'){
            $userId=(int)$redemption['user_id'];$cost=(int)$redemption['points_cost'];
            $db->prepare('INSERT IGNORE INTO kp_points_wallet (user_id) VALUES (?)')->execute([$userId]);
            $st=$db->prepare('SELECT total_spent FROM kp_points_wallet WHERE user_id=? FOR UPDATE');
            $st->execute([$userId]);$spent=$st->fetchColumn();
            if($spent===false||(int)$spent<$cost)throw new RuntimeException('Ví Point không khớp để hoàn trả.');
            $st=$db->prepare('INSERT INTO kp_points_ledger (user_id,points_delta,source_type,source_ref,note) VALUES (?,?,?,?,?)');
            $st->execute([$userId,$cost,'refund',(string)$redemptionId,'Hoàn Point do yêu cầu đổi quà bị từ chối']);
            $st=$db->prepare('UPDATE kp_points_wallet SET balance=balance+?,total_spent=total_spent-? WHERE user_id=?');
            $st->execute([$cost,$cost,$userId]);
            if($st->rowCount()!==1)throw new RuntimeException('Hoàn Point chưa được ghi nhận.');
            $db->prepare('UPDATE kp_points_rewards SET stock=stock+1 WHERE id=? AND stock IS NOT NULL')->execute([(int)$redemption['reward_id']]);
        }
        $st=$db->prepare('UPDATE kp_points_redemptions SET status=?,admin_note=? WHERE id=? AND status=?');
        $st->execute([$action,'#'.$adminId.' '.$note,$redemptionId,$old]);
        if($st->rowCount()!==1)throw new RuntimeException('Không hoàn tất đối soát yêu cầu.');
        if($advanced){
            $st=$db->prepare('INSERT INTO kp_points_redemption_events (redemption_id,actor_admin_id,from_status,to_status,note) VALUES (?,?,?,?,?)');
            $st->execute([$redemptionId,$adminId,$old,$action,$note]);
        }
        $st=$db->prepare('SELECT balance FROM kp_points_wallet WHERE user_id=?');
        $st->execute([(int)$redemption['user_id']]);
        $afterBalance=$st->fetchColumn();
        $afterBalance=$afterBalance===false?null:(int)$afterBalance;
        $change=$action==='rejected'?(int)$redemption['points_cost']:0;
        kp_points_audit_append($db,(int)$redemption['user_id'],$adminId,$redemptionId,
            $action,(string)$redemptionId,$change,$afterBalance===null?null:$afterBalance-$change,$afterBalance);
        $db->commit();
        $labels=['approved'=>'Đã duyệt, chờ phát quà','delivering'=>'Đang tiến hành phát quà','fulfilled'=>'Quà đã được xác nhận giao','rejected'=>'Đơn bị từ chối, Point đã hoàn'];
        $body='Đơn #'.$redemptionId.' · '.$redemption['reward_title'].' · '.$labels[$action].'. Ghi chú: '.$note;
        try {
            kp_center_notify((int)$redemption['user_id'],$labels[$action],$body,'gift','/diem-tich-luy');
        } catch(Throwable $notifyError) {
            if(function_exists('kp_log'))kp_log('point redemption notification failed after commit',$notifyError);
        }
        return $action;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

/** Append-only administrator adjustment. The request token makes retries idempotent. */
function kp_points_adjust(int $userId, int $adminId, int $delta, string $reason, string $requestToken): bool {
    $reason=trim($reason);
    if($userId<1||$adminId<1||$delta===0||abs($delta)>100000000
        ||$reason===''||strlen($reason)>180||!preg_match('/^[a-f0-9]{32}$/D',$requestToken))
        throw new InvalidArgumentException('Điều chỉnh Point không hợp lệ.');
    $db=kp_db();$db->beginTransaction();
    try{
        $st=$db->prepare('SELECT user_id FROM kp_points_ledger WHERE source_type=? AND source_ref=? LIMIT 1');
        $st->execute(['admin_adjust',$requestToken]);$previous=$st->fetchColumn();
        if($previous!==false){
            if((int)$previous!==$userId)throw new RuntimeException('Mã thao tác Point không hợp lệ.');
            $db->commit();return false;
        }
        $st=$db->prepare('SELECT id,username FROM team_user WHERE id=? AND ban=0 LIMIT 1');
        $st->execute([$userId]);$user=$st->fetch(PDO::FETCH_ASSOC);
        if(!$user)throw new RuntimeException('Không tìm thấy tài khoản đang hoạt động.');
        $db->prepare('INSERT IGNORE INTO kp_points_wallet (user_id) VALUES (?)')->execute([$userId]);
        $st=$db->prepare('SELECT balance FROM kp_points_wallet WHERE user_id=? FOR UPDATE');
        $st->execute([$userId]);$balance=$st->fetchColumn();
        if($balance===false||$delta<0&&(int)$balance<abs($delta))throw new RuntimeException('Số dư Point không đủ để trừ.');
        $st=$db->prepare('INSERT INTO kp_points_ledger (user_id,points_delta,source_type,source_ref,note) VALUES (?,?,?,?,?)');
        $st->execute([$userId,$delta,'admin_adjust',$requestToken,'Admin #'.$adminId.': '.$reason]);
        if($delta>0){
            $st=$db->prepare('UPDATE kp_points_wallet SET balance=balance+?,total_earned=total_earned+? WHERE user_id=?');
            $st->execute([$delta,$delta,$userId]);
        }else{
            $spent=abs($delta);
            $st=$db->prepare('UPDATE kp_points_wallet SET balance=balance-?,total_spent=total_spent+? WHERE user_id=? AND balance>=?');
            $st->execute([$spent,$spent,$userId,$spent]);
        }
        if($st->rowCount()!==1)throw new RuntimeException('Không cập nhật được ví Point.');
        kp_points_audit_append($db,$userId,$adminId,null,'admin_adjust',$requestToken,$delta,(int)$balance,(int)$balance+$delta);
        $db->commit();
        // Notification is best effort after a committed ledger entry.
        // Do not report a failed Point mutation and invite a second adjustment.
        try {
            kp_center_notify($userId,$delta>0?'Point đã được cộng':'Point đã được điều chỉnh',
                'Quản trị viên đã '.($delta>0?'cộng ':'trừ ').number_format(abs($delta),0,',','.').' Point. Lý do: '.$reason,
                'payment','/diem-tich-luy');
        } catch(Throwable $notifyError) {
            if(function_exists('kp_log'))kp_log('Point admin adjustment notification failed after commit',$notifyError);
        }
        return true;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
