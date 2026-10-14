<?php
/**
 * KPAH website VND balance WITHOUT new SQL tables.
 * Uses existing team_user.vnd plus protected storage/topup journals.
 * MyISAM writes are deliberately not replayed after an uncertain outcome.
 */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }
require_once __DIR__.'/topup_core.php';

function kp_vnd_bonus_percent($set=null): int {
    $path=kp_topup_root().'/bank-bonus.json';
    $fp=@fopen($path.'.lock','c+');
    if(!$fp || !flock($fp,LOCK_EX))throw new RuntimeException('Không khóa được cấu hình khuyến mãi.');
    try{
        $percent=10;
        if(is_file($path)){
            $record=kp_topup_read_file($path);
            if(!isset($record['percent']) || !is_int($record['percent']) || $record['percent']<0 || $record['percent']>100)
                throw new RuntimeException('Cấu hình thưởng ngân hàng không hợp lệ.');
            $percent=$record['percent'];
        }
        if($set!==null){
            if(!is_int($set) || $set<0 || $set>100)throw new InvalidArgumentException('Mức thưởng từ 0 đến 100%.');
            kp_topup_write_file($path,['percent'=>$set,'updated_at'=>time()]);
            $percent=$set;
        }
        return $percent;
    }finally{@flock($fp,LOCK_UN);fclose($fp);}
}
function kp_vnd_with_lock(int $userId,callable $fn) {
    if($userId<1)throw new RuntimeException('Tài khoản không hợp lệ.');
    $db=kp_db();
    $key='kp_vnd_'.substr(hash('sha256',(string)$userId),0,40);
    $st=$db->prepare('SELECT GET_LOCK(?,8)');$st->execute([$key]);
    if((int)$st->fetchColumn()!==1)throw new RuntimeException('Tài khoản đang xử lý tiền, hãy thử lại.');
    try{return $fn($db);}finally{
        try{$st=$db->prepare('SELECT RELEASE_LOCK(?)');$st->execute([$key]);}
        catch(Throwable $e){kp_log('vnd lock release failure',$e);}
    }
}
function kp_vnd_current(PDO $db,int $id): int {
    $st=$db->prepare('SELECT vnd FROM team_user WHERE id=? AND ban=0 LIMIT 1');
    $st->execute([$id]);$value=$st->fetchColumn();
    if($value===false || !preg_match('/^(0|[1-9][0-9]*)$/D',(string)$value) || (float)$value>2000000000)
        throw new RuntimeException('Không đọc được team_user.vnd: kiểm tra cột hoặc tài khoản.');
    return (int)$value;
}
function kp_vnd_balance(int $userId): int {
    return kp_vnd_with_lock($userId,function(PDO $db)use($userId){
        return kp_vnd_current($db,$userId);
    });
}
function kp_vnd_record_path(string $kind,string $ref): string {
    if(!in_array($kind,['payos','card','exchange'],true) ||
       !preg_match('/^[a-zA-Z0-9_-]{1,80}$/D',$ref))
        throw new InvalidArgumentException('Mã giao dịch ví không hợp lệ.');
    $dir=kp_topup_root().'/vnd-journal';
    if(!is_dir($dir)&&!@mkdir($dir,0750,true)&&!is_dir($dir))
        throw new RuntimeException('Không tạo được thư mục nhật ký ví.');
    if(!is_writable($dir))throw new RuntimeException('Không thể ghi nhật ký ví.');
    return $dir.'/'.$kind.'-'.strtolower($ref).'.json';
}
function kp_vnd_credit(int $userId,string $kind,string $ref,int $base,int $bonusPercent=0): bool {
    if(!in_array($kind,['payos','card'],true)||$base<1||$base>1000000000||$bonusPercent<0||$bonusPercent>100)
        throw new RuntimeException('Yêu cầu cộng tiền không hợp lệ.');
    $add=$base+intdiv($base*$bonusPercent,100);
    return kp_vnd_with_lock($userId,function(PDO $db)use($userId,$kind,$ref,$base,$bonusPercent,$add){
        $path=kp_vnd_record_path($kind,$ref);
        $record=kp_topup_read_file($path);
        if($record!==null){
            if((int)($record['user_id']??0)!==$userId||
               (int)($record['base']??-1)!==$base||
               (int)($record['bonus_percent']??-1)!==$bonusPercent)
                throw new RuntimeException('Giao dịch đã tồn tại với thông tin khác.');
            if(($record['status']??'')==='completed')return false;
            // Journal may have been written before an ambiguous MyISAM write.
            // Never retry the same monetary UPDATE after an interrupted request.
            throw new RuntimeException('Giao dịch đang cần đối soát. Không cộng lại.');
        }
        $before=kp_vnd_current($db,$userId);
        if($before>2000000000-$add)throw new RuntimeException('Số dư ví vượt giới hạn.');
        $after=$before+$add;
        $record=['user_id'=>$userId,'source'=>$kind,'ref'=>$ref,
            'base'=>$base,'bonus_percent'=>$bonusPercent,'before'=>$before,'after'=>$after,
            'status'=>'prepared','created_at'=>time()];
        kp_topup_write_file($path,$record); // Durable BEFORE MyISAM mutation
        try{
            $st=$db->prepare('UPDATE team_user SET vnd=? WHERE id=? AND vnd=? AND ban=0');
            $st->execute([$after,$userId,$before]);
            if($st->rowCount()!==1)throw new RuntimeException('Số dư VNĐ đã thay đổi, không được cộng lại.');
            $record['status']='completed';$record['completed_at']=time();
            kp_topup_write_file($path,$record);
            return true;
        }catch(Throwable $e){
            kp_log('vnd deposit requires manual reconciliation '.$kind.' '.$ref,$e);
            // Do NOT replace the earlier prepared journal if the filesystem fails.
            throw new RuntimeException('Đã ghi nhận tiền nhưng chưa xác định được việc cộng ví. Liên hệ admin đối soát.');
        }
    });
}
function kp_vnd_exchange(int $userId,int $charId,string $currency,int $amount,string $token): array {
    $pkg=kp_card_packages();
    if($charId<1 || !isset($pkg[$amount][$currency]) || !preg_match('/^[a-f0-9]{32}$/D',$token))
        throw new RuntimeException('Mốc đổi hoặc nhân vật không hợp lệ.');
    return kp_vnd_with_lock($userId,function(PDO $db)use($userId,$charId,$currency,$amount,$token,$pkg){
        $path=kp_vnd_record_path('exchange',$token);
        $old=kp_topup_read_file($path);
        if($old!==null){
            if((int)($old['user_id']??0)!==$userId)
                throw new RuntimeException('Yêu cầu đổi mốc không thuộc tài khoản.');
            return ['id'=>$token,'status'=>$old['status']];
        }
        $st=$db->prepare('SELECT id,username FROM team_user WHERE id=? AND ban=0');
        $st->execute([$userId]);$acc=$st->fetch(PDO::FETCH_ASSOC);
        if(!$acc)throw new RuntimeException('Tài khoản không tồn tại.');
        $st=$db->prepare('SELECT id FROM tob_char WHERE id=? AND userId=? AND del=1 AND ban=0 LIMIT 1');
        $st->execute([$charId,$userId]);
        if(!$st->fetchColumn())throw new RuntimeException('Nhân vật nhận không hợp lệ.');
        $before=kp_vnd_current($db,$userId);
        if($before<$amount)throw new RuntimeException('Ví VNĐ không đủ để đổi mốc.');
        $units=(int)$pkg[$amount][$currency];
        $record=['id'=>$token,'user_id'=>$userId,'username'=>$acc['username'],
          'char_id'=>$charId,'currency'=>$currency,'amount_vnd'=>$amount,'units'=>$units,
          'balance_before'=>$before,'balance_after'=>$before-$amount,
          'status'=>'prepared','created_at'=>time()];
        kp_topup_write_file($path,$record);
        try{
            $st=$db->prepare('UPDATE team_user SET vnd=? WHERE id=? AND vnd=? AND ban=0');
            $st->execute([$before-$amount,$userId,$before]);
            if($st->rowCount()!==1)throw new RuntimeException('Ví đã thay đổi, không được trừ lại.');
            $record['status']='granting';
            kp_topup_write_file($path,$record);
        }catch(Throwable $e){
            kp_log('vnd exchange debit uncertain '.$token,$e);
            throw new RuntimeException('Trạng thái trừ VNĐ chưa rõ. Hãy yêu cầu đối soát, không thử lại.');
        }
        try{
            require_once __DIR__.'/bank_payos.php';
            kp_bank_direct_grant(['char_id'=>$charId,'user_id'=>$userId,
                'username'=>$acc['username'],'currency'=>$currency,'units'=>$units]);
            $record['status']='completed';$record['completed_at']=time();
            kp_topup_write_file($path,$record);
            return ['id'=>$token,'status'=>'completed'];
        }catch(Throwable $e){
            kp_log('vnd exchange grant uncertain '.$token,$e);
            $record['status']='needs_review';
            try{kp_topup_write_file($path,$record);}catch(Throwable $ignored){}
            return ['id'=>$token,'status'=>'needs_review'];
        }
    });
}
function kp_vnd_recent_exchanges($userId=null,int $limit=100): array {
    $dir=kp_topup_root().'/vnd-journal';
    if(!is_dir($dir))return [];
    $records=[];
    foreach(glob($dir.'/exchange-*.json')?:[] as $file){
        try{$r=kp_topup_read_file($file);}
        catch(Throwable $e){kp_log('vnd history corrupted',$e);continue;}
        if(!is_array($r) || ($userId!==null && (int)($r['user_id']??0)!==(int)$userId))continue;
        $records[]=$r;
    }
    usort($records,function($a,$b){return (int)($b['created_at']??0)<=>(int)($a['created_at']??0);});
    return array_slice($records,0,max(1,min(500,$limit)));
}
