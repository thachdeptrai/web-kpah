<?php
/** Website loyalty Points, separate from any game-character progression. */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }

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
        if ($st->fetchColumn()===false) throw new RuntimeException('Ví Point chưa sẵn sàng.');
        $st=$db->prepare('INSERT IGNORE INTO kp_points_ledger (user_id,points_delta,source_type,source_ref,amount_vnd,note) VALUES (?,?,?,?,?,?)');
        $st->execute([$userId,$points,$source,$reference,$amountVnd,'Tích Point từ giao dịch hợp lệ']);
        if ($st->rowCount()!==1) { $db->commit(); return false; }
        $st=$db->prepare('UPDATE kp_points_wallet SET balance=balance+?,total_earned=total_earned+? WHERE user_id=?');
        $st->execute([$points,$points,$userId]);
        if ($st->rowCount()!==1) throw new RuntimeException('Chưa hoàn tất cập nhật ví Point.');
        $db->commit();
        return true;
    } catch(Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
}

function kp_points_redeem(int $userId, int $rewardId, string $requestToken): int {
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
        $st=$db->prepare('SELECT id,title,points_cost,stock FROM kp_points_rewards WHERE id=? AND is_active=1 FOR UPDATE');
        $st->execute([$rewardId]);$reward=$st->fetch(PDO::FETCH_ASSOC);
        if(!$reward)throw new RuntimeException('Quà tặng này chưa mở đổi.');
        $cost=(int)$reward['points_cost'];
        if($cost<1)throw new RuntimeException('Giá Point không hợp lệ.');
        if($reward['stock']!==null && (int)$reward['stock']<1)throw new RuntimeException('Quà đã hết.');
        $db->prepare('INSERT IGNORE INTO kp_points_wallet (user_id) VALUES (?)')->execute([$userId]);
        $st=$db->prepare('SELECT balance FROM kp_points_wallet WHERE user_id=? FOR UPDATE');
        $st->execute([$userId]);$balance=$st->fetchColumn();
        if($balance===false || (int)$balance<$cost)throw new RuntimeException('Bạn chưa đủ Point để đổi quà.');
        $st=$db->prepare('INSERT INTO kp_points_redemptions (user_id,reward_id,reward_title,points_cost,request_token,status) VALUES (?,?,?,?,?,?)');
        $st->execute([$userId,$rewardId,$reward['title'],$cost,$requestToken,'pending']);
        $id=(int)$db->lastInsertId();
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
        $db->commit();
        return $id;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
