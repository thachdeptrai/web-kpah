<?php
/** Secure VNĐ web wallet. team_user.vnd is MyISAM: maintain an InnoDB ledger and mirror. */
if (!defined('KP_ROOT')) {http_response_code(403);exit;}

function kp_vnd_bonus_percent(): int {
    $v=kp_db()->query('SELECT bank_bonus_percent FROM kp_vnd_settings WHERE id=1')->fetchColumn();
    if($v===false)throw new RuntimeException('Chưa cài đặt ví VNĐ. Chạy database/vnd_wallet_upgrade.sql.');
    $n=(int)$v;
    if($n<0||$n>100)throw new RuntimeException('Cấu hình thưởng ngân hàng không hợp lệ.');
    return $n;
}
function kp_vnd_with_lock(int $userId, callable $callback) {
    if($userId<1)throw new InvalidArgumentException('Tài khoản không hợp lệ.');
    $db=kp_db();
    $lock='kp_vnd_'.substr(hash('sha256',(string)$userId),0,40);
    $st=$db->prepare('SELECT GET_LOCK(?,8)');$st->execute([$lock]);
    if((int)$st->fetchColumn()!==1)throw new RuntimeException('Ví đang xử lý giao dịch, vui lòng thử lại.');
    try{return $callback($db);}finally{
        try{$st=$db->prepare('SELECT RELEASE_LOCK(?)');$st->execute([$lock]);}
        catch(Throwable $e){kp_log('vnd lock release failed',$e);}
    }
}
function kp_vnd_ensure(PDO $db,int $userId): void {
    // Preserve balances already manually entered in team_user.vnd on first migration use.
    $st=$db->prepare('INSERT IGNORE INTO kp_vnd_wallets(user_id,balance) SELECT id,GREATEST(COALESCE(vnd,0),0) FROM team_user WHERE id=? AND ban=0');
    $st->execute([$userId]);
    $st=$db->prepare('SELECT balance FROM kp_vnd_wallets WHERE user_id=?');
    $st->execute([$userId]);
    if($st->fetchColumn()===false)throw new RuntimeException('Tài khoản hoặc cột vnd chưa sẵn sàng.');
}
function kp_vnd_sync(PDO $db,int $userId): void {
    $st=$db->prepare('SELECT balance FROM kp_vnd_wallets WHERE user_id=?');
    $st->execute([$userId]);$balance=$st->fetchColumn();
    if($balance===false)throw new RuntimeException('Không đọc được ví VNĐ.');
    // Mirror an exact ledger-derived balance; NEVER vnd=vnd+N on a retry.
    $st=$db->prepare('UPDATE team_user SET vnd=? WHERE id=? AND ban=0');
    $st->execute([$balance,$userId]);
    $st=$db->prepare('SELECT vnd FROM team_user WHERE id=? AND ban=0');
    $st->execute([$userId]);
    $actual=$st->fetchColumn();
    if($actual===false||(int)$actual!==(int)$balance)
        throw new RuntimeException('Ví đã ghi sổ nhưng chưa đồng bộ về game. Cần kiểm tra log.');
}
function kp_vnd_balance(int $userId): int {
    return kp_vnd_with_lock($userId,function(PDO $db)use($userId){
        kp_vnd_ensure($db,$userId);
        kp_vnd_sync($db,$userId);
        $st=$db->prepare('SELECT balance FROM kp_vnd_wallets WHERE user_id=?');
        $st->execute([$userId]);return (int)$st->fetchColumn();
    });
}
function kp_vnd_credit(int $userId,string $source,string $ref,int $base,int $bonusPercent=0): bool {
    if(!in_array($source,['payos','card'],true)||!preg_match('/^[a-zA-Z0-9_-]{1,80}$/D',$ref)
       ||$base<1||$base>1000000000||$bonusPercent<0||$bonusPercent>100)
        throw new InvalidArgumentException('Nguồn cộng ví VNĐ không hợp lệ.');
    $bonus=intdiv($base*$bonusPercent,100);
    $total=$base+$bonus;
    return kp_vnd_with_lock($userId,function(PDO $db)use($userId,$source,$ref,$base,$bonus,$total){
        kp_vnd_ensure($db,$userId);
        $db->beginTransaction();
        try {
            $st=$db->prepare('SELECT balance FROM kp_vnd_wallets WHERE user_id=? FOR UPDATE');
            $st->execute([$userId]);$balance=$st->fetchColumn();
            if($balance===false || (float)$balance>2000000000-$total)throw new RuntimeException('Số dư VNĐ đã vượt giới hạn.');
            $st=$db->prepare('INSERT IGNORE INTO kp_vnd_ledger(user_id,source_type,source_ref,delta_vnd,base_vnd,bonus_vnd) VALUES(?,?,?,?,?,?)');
            $st->execute([$userId,$source,$ref,$total,$base,$bonus]);
            $created=$st->rowCount()===1;
            if($created){
                $st=$db->prepare('UPDATE kp_vnd_wallets SET balance=balance+?,deposited=deposited+?,bonus_total=bonus_total+? WHERE user_id=?');
                $st->execute([$total,$base,$bonus,$userId]);
                if($st->rowCount()!==1)throw new RuntimeException('Ghi số dư VNĐ không thành công.');
            } else {
                $st=$db->prepare('SELECT user_id,base_vnd,bonus_vnd FROM kp_vnd_ledger WHERE source_type=? AND source_ref=?');
                $st->execute([$source,$ref]);$prior=$st->fetch(PDO::FETCH_ASSOC);
                if(!$prior||(int)$prior['user_id']!==$userId||(int)$prior['base_vnd']!==$base||(int)$prior['bonus_vnd']!==$bonus)
                    throw new RuntimeException('Mã giao dịch đã tồn tại với số tiền khác. Cần đối soát.');
            }
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        kp_vnd_sync($db,$userId);
        return $created;
    });
}
/** Reserve exactly one exchange: the game MyISAM grant may require admin reconciliation. */
function kp_vnd_exchange(int $userId,int $charId,string $currency,int $amount,string $token): array {
    require_once __DIR__.'/bank_payos.php';
    $packages=kp_card_packages();
    if($charId<1 || !isset($packages[$amount][$currency]) || !preg_match('/^[a-f0-9]{32}$/D',$token))
        throw new RuntimeException('Mốc đổi hoặc nhân vật không hợp lệ.');
    $st=kp_db()->prepare('SELECT id,username FROM team_user WHERE id=? AND ban=0');
    $st->execute([$userId]);$account=$st->fetch(PDO::FETCH_ASSOC);
    if(!$account)throw new RuntimeException('Tài khoản không tồn tại.');
    $st=kp_db()->prepare('SELECT id FROM tob_char WHERE id=? AND userId=? AND del=1 AND ban=0 LIMIT 1');
    $st->execute([$charId,$userId]);
    if(!$st->fetchColumn())throw new RuntimeException('Nhân vật không thuộc tài khoản hoặc không khả dụng.');
    $units=(int)$packages[$amount][$currency];
    return kp_vnd_with_lock($userId,function(PDO $db)use($userId,$charId,$currency,$amount,$token,$account,$units){
        kp_vnd_ensure($db,$userId);
        $st=$db->prepare('SELECT id,user_id,status FROM kp_vnd_exchanges WHERE request_token=? LIMIT 1');
        $st->execute([$token]);$prior=$st->fetch(PDO::FETCH_ASSOC);
        if($prior){
            if((int)$prior['user_id']!==$userId)throw new RuntimeException('Mã yêu cầu không hợp lệ.');
            return ['id'=>(int)$prior['id'],'status'=>$prior['status']];
        }
        $db->beginTransaction();
        try {
            $st=$db->prepare('SELECT balance FROM kp_vnd_wallets WHERE user_id=? FOR UPDATE');
            $st->execute([$userId]);$balance=$st->fetchColumn();
            if($balance===false||(int)$balance<$amount)throw new RuntimeException('Số dư VNĐ không đủ để đổi mốc.');
            $st=$db->prepare('INSERT INTO kp_vnd_exchanges(user_id,char_id,currency,amount_vnd,units,request_token,status) VALUES(?,?,?,?,?,?,"crediting")');
            $st->execute([$userId,$charId,$currency,$amount,$units,$token]);$exchangeId=(int)$db->lastInsertId();
            $st=$db->prepare('UPDATE kp_vnd_wallets SET balance=balance-?,spent=spent+? WHERE user_id=? AND balance>=?');
            $st->execute([$amount,$amount,$userId,$amount]);
            if($st->rowCount()!==1)throw new RuntimeException('Không thể giữ tiền cho giao dịch.');
            $st=$db->prepare('INSERT INTO kp_vnd_ledger(user_id,source_type,source_ref,delta_vnd,base_vnd,bonus_vnd) VALUES(?,?,?,?,0,0)');
            $st->execute([$userId,'exchange',(string)$exchangeId,-$amount]);
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        try{
            kp_vnd_sync($db,$userId);
            kp_bank_direct_grant(['char_id'=>$charId,'user_id'=>$userId,'username'=>$account['username'],'currency'=>$currency,'units'=>$units]);
            $db->prepare('UPDATE kp_vnd_exchanges SET status="completed" WHERE id=? AND status="crediting"')->execute([$exchangeId]);
            return ['id'=>$exchangeId,'status'=>'completed'];
        }catch(Throwable $e){
            kp_log('VNĐ exchange needs review '.$exchangeId,$e);
            $db->prepare('UPDATE kp_vnd_exchanges SET status="needs_review" WHERE id=? AND status="crediting"')->execute([$exchangeId]);
            return ['id'=>$exchangeId,'status'=>'needs_review'];
        }
    });
}
