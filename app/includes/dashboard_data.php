<?php
/**
 * Read-only dashboard data for the signed-in game account.
 * Keep each optional source isolated: a missing custom column/table may never
 * take the whole player dashboard down. No balance recalculation or writes.
 */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }
require_once __DIR__.'/topup_core.php';

function kp_dashboard_data(int $userId): array {
    $result=[
        'vnd'=>null,'tongnap'=>null,'points'=>null,'earned'=>null,
        'characters'=>[],'characters_ready'=>false,'characters_count'=>null,
        'payments'=>[],'payments_ready'=>false,'reward'=>null,'rewards_ready'=>false,
        'achievement'=>null
    ];
    $db=kp_db();

    // Both custom columns were added on the live VPS after the SQL dump.
    // An absent column should show a neutral placeholder, never PHP 500.
    try{
        $st=$db->prepare('SELECT vnd,tongnap FROM team_user WHERE id=? AND ban=0 LIMIT 1');
        $st->execute([$userId]);
        $account=$st->fetch(PDO::FETCH_ASSOC);
        if($account){
            $result['vnd']=$account['vnd'];
            $result['tongnap']=$account['tongnap'];
        }
    }catch(Throwable $e){kp_log('dashboard VND/tongnap unavailable',$e);}

    try{
        $st=$db->prepare('SELECT balance,total_earned FROM kp_points_wallet WHERE user_id=? LIMIT 1');
        $st->execute([$userId]);$points=$st->fetch(PDO::FETCH_ASSOC);
        if($points){
            $result['points']=$points['balance'];
            $result['earned']=$points['total_earned'];
        }elseif($db->query('SELECT 1 FROM kp_points_program WHERE id=1 LIMIT 1')->fetchColumn()){
            $result['points']=0;
            $result['earned']=0;
        }
    }catch(Throwable $e){kp_log('dashboard Points unavailable',$e);}

    try{
        $st=$db->prepare('SELECT COUNT(*) FROM tob_web_info_char WHERE userid=?');
        $st->execute([$userId]);$result['characters_count']=(int)$st->fetchColumn();
        $result['characters']=kp_player_characters($userId,3);
        $result['characters_ready']=true;
    }catch(Throwable $e){kp_log('dashboard character preview unavailable',$e);}

    try{
        // The character must be linked to THIS user's tob_web_info_char row.
        $st=$db->prepare('SELECT l.charname,l.lv,l.honor,l.nPK
                          FROM tob_web_info_char AS w
                          INNER JOIN tob_char_luong AS l ON l.charname=w.charname
                          WHERE w.userid=? ORDER BY l.lv DESC,l.exp DESC LIMIT 1');
        $st->execute([$userId]);
        $honors=$st->fetch(PDO::FETCH_ASSOC);
        if($honors){
            $honors['vip']=null;
            try{
                $vip=$db->prepare('SELECT vip FROM tob_char_vip WHERE charname=? ORDER BY vip DESC LIMIT 1');
                $vip->execute([(string)$honors['charname']]);
                $vipValue=$vip->fetchColumn();
                if($vipValue!==false)$honors['vip']=$vipValue;
            }catch(Throwable $e){kp_log('dashboard VIP preview unavailable',$e);}
            $result['achievement']=$honors;
        }
    }catch(Throwable $e){kp_log('dashboard personal achievements unavailable',$e);}

    try{
        $st=$db->prepare('SELECT id,title,points_cost FROM kp_points_rewards WHERE is_active=1 AND (stock IS NULL OR stock>0) ORDER BY points_cost ASC,id ASC LIMIT 1');
        $st->execute();$reward=$st->fetch(PDO::FETCH_ASSOC);
        $result['reward']=$reward?:null;
        $result['rewards_ready']=true;
    }catch(Throwable $e){kp_log('dashboard gift preview unavailable',$e);}

    $payments=[];
    $bankOk=false;$cardOk=false;
    try{
        $st=$db->prepare('SELECT order_code,amount,status,created_at,provider_reference
                          FROM kp_bank_orders WHERE user_id=? ORDER BY id DESC LIMIT 5');
        $st->execute([$userId]);
        foreach($st->fetchAll(PDO::FETCH_ASSOC) as $bank){
            $status=(string)$bank['status'];
            $wallet=($status==='completed' && strpos((string)$bank['provider_reference'],'VND_BONUS:')===0)
                  ||strpos($status,'wallet_')===0;
            $label=in_array($status,['completed','wallet_credited'],true)
                ?($wallet?'Đã cộng ví':'Hoàn thành')
                :(in_array($status,['wallet_pending','pending','wallet_creating','creating'],true)?'Chờ thanh toán':
                 (in_array($status,['needs_review','wallet_crediting','crediting'],true)?'Cần kiểm tra':
                 (in_array($status,['cancelled','expired','failed'],true)?'Không thành công':'Đang xử lý')));
            $time=strtotime((string)$bank['created_at']);
            $payments[]=['source'=>'Ngân hàng','amount'=>$bank['amount'],'label'=>$label,
                         'done'=>in_array($status,['completed','wallet_credited'],true),
                         'time'=>$time===false?0:$time,'reference'=>(string)$bank['order_code']];
        }
        $bankOk=true;
    }catch(Throwable $e){kp_log('dashboard bank history unavailable',$e);}

    try{
        foreach(kp_topup_recent('orders',$userId,5) as $card){
            $status=(string)($card['status']??'');
            $label=in_array($status,['wallet_credited','completed','queued'],true)?'Hoàn thành':
                (in_array($status,['failed','rejected'],true)?'Không thành công':
                (in_array($status,['needs_review','wallet_crediting','crediting'],true)?'Cần kiểm tra':'Đang xử lý'));
            $amount=(int)($card['amount']??0);
            if($amount<=0)continue;
            // Never return card serials, PINs or provider payload to the template.
            $payments[]=['source'=>'Thẻ cào','amount'=>$amount,'label'=>$label,
                         'done'=>in_array($status,['wallet_credited','completed','queued'],true),
                         'time'=>(int)($card['created_at']??0),
                         'reference'=>strtoupper(substr((string)($card['id']??''),0,8))];
        }
        $cardOk=true;
    }catch(Throwable $e){kp_log('dashboard card history unavailable',$e);}
    usort($payments,function($a,$b){return $b['time']<=>$a['time'];});
    $result['payments']=array_slice($payments,0,4);
    $result['payments_ready']=$bankOk||$cardOk;
    return $result;
}
