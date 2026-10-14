<?php
/**
 * Read-only KPAH leaderboards. Each query is static and whitelisted.
 * Never interpolate ?type or user input into SQL identifiers/ORDER BY.
 *
 * Labels are descriptive, not claims about server-side reward rules.
 */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }

function kp_rankings_catalog()
{
    return [
        'spending' => [
            'label'=>'Tiêu Lượng','short'=>'Tiêu Lượng','metric'=>'Lượng đã tiêu','secondary'=>'Lượng đã nạp',
            'description'=>'Nhân vật sử dụng nhiều Lượng nhất theo tổng hợp nạp và tiêu.',
            'sql'=>"SELECT charname AS name,luongxai AS score,luongnap AS secondary FROM tob_nap_xai_luong WHERE charname <> '' AND luongxai > 0 ORDER BY luongxai DESC,luongnap DESC,charname ASC LIMIT 10"
        ],
        'vip' => [
            'label'=>'Cấp VIP','short'=>'VIP','metric'=>'Cấp VIP','secondary'=>null,
            'description'=>'Những nhân vật đạt bậc VIP cao nhất.',
            'sql'=>"SELECT charname AS name,vip AS score,0 AS secondary FROM tob_char_vip WHERE charname <> '' AND vip > 0 ORDER BY vip DESC,id ASC LIMIT 10"
        ],
        'battle' => [
            'label'=>'Chiến trường','short'=>'Chiến trường','metric'=>'Điểm liên trảm','secondary'=>'Chỉ số chiến trường',
            'description'=>'Thành tích liên trảm trên chiến trường.',
            'sql'=>"SELECT charname AS name,lientram AS score,sttru AS secondary FROM tob_chien_truong WHERE charname <> '' AND lientram > 0 ORDER BY lientram DESC,sttru DESC,id ASC LIMIT 10"
        ],
        'farm' => [
            'label'=>'Nông trại','short'=>'Nông trại','metric'=>'Hạng nông trại','secondary'=>'Cấp cây',
            'description'=>'Những nông trại có thứ hạng cao nhất (hạng càng nhỏ càng cao).',
            'sql'=>"SELECT c.charname AS name,f.rank AS score,f.lv AS secondary FROM tob_farm f INNER JOIN tob_char c ON c.id=f.owner WHERE f.rank>0 AND c.del=1 AND c.ban=0 AND c.charname <> '' ORDER BY f.rank ASC,f.lv DESC,f.id ASC LIMIT 10"
        ],
        'clanxp' => [
            'label'=>'EXP bang hội','short'=>'EXP bang','metric'=>'EXP bang','secondary'=>'Cấp bang',
            'description'=>'Các bang hội tích lũy nhiều kinh nghiệm nhất.',
            'sql'=>"SELECT clanName AS name,COALESCE(xp,0) AS score,COALESCE(lvClan,0) AS secondary FROM tob_clan WHERE isdel=0 AND clanName IS NOT NULL AND clanName<>'' AND xp>0 ORDER BY xp DESC,lvClan DESC,id_clan ASC LIMIT 10"
        ],
        'contribution' => [
            'label'=>'Tích lũy','short'=>'Tích lũy','metric'=>'Điểm tích lũy','secondary'=>'Cấp',
            'description'=>'Những nhân vật có điểm tích lũy cao nhất.',
            'sql'=>"SELECT charname AS name,tichluy AS score,lastLv AS secondary FROM tob_char WHERE charname<>'' AND del=1 AND ban=0 AND tichluy>0 ORDER BY tichluy DESC,lastLv DESC,id ASC LIMIT 10"
        ],
        'kills' => [
            'label'=>'Hạ gục','short'=>'Hạ gục','metric'=>'Lượt hạ gục','secondary'=>'Điểm PK',
            'description'=>'Những nhân vật ghi nhận nhiều lượt hạ gục nhất.',
            'sql'=>"SELECT charname AS name,killer AS score,nPKill AS secondary FROM tob_char WHERE charname<>'' AND del=1 AND ban=0 AND killer>0 ORDER BY killer DESC,nPKill DESC,id ASC LIMIT 10"
        ],
        'toppoints' => [
            'label'=>'Điểm Top nạp','short'=>'Điểm nạp','metric'=>'Điểm Top','secondary'=>'Cấp',
            'description'=>'Điểm tích lũy Top nạp được game ghi nhận cho nhân vật.',
            'sql'=>"SELECT charname AS name,topNap AS score,lastLv AS secondary FROM tob_char WHERE charname<>'' AND del=1 AND ban=0 AND topNap>0 ORDER BY topNap DESC,lastLv DESC,id ASC LIMIT 10"
        ],
        'clanwealth' => [
            'label'=>'Quỹ bang hội','short'=>'Quỹ bang','metric'=>'Xu bang','secondary'=>'Cấp bang',
            'description'=>'Những bang hội có quỹ lớn nhất.',
            'sql'=>"SELECT clanName AS name,COALESCE(money,0) AS score,COALESCE(lvClan,0) AS secondary FROM tob_clan WHERE isdel=0 AND clanName IS NOT NULL AND clanName<>'' AND money>0 ORDER BY money DESC,lvClan DESC,id_clan ASC LIMIT 10"
        ],
        'strength' => [
            'label'=>'Sức mạnh','short'=>'Sức mạnh','metric'=>'Sức mạnh','secondary'=>'Cấp',
            'description'=>'Những nhân vật có chỉ số sức mạnh cao nhất.',
            'sql'=>"SELECT charname AS name,strength AS score,lastLv AS secondary FROM tob_char WHERE charname<>'' AND del=1 AND ban=0 AND strength>0 ORDER BY strength DESC,lastLv DESC,id ASC LIMIT 10"
        ],
        'playtime' => [
            'label'=>'Thời gian chơi','short'=>'Giờ chơi','metric'=>'Phút chơi','secondary'=>'Cấp',
            'description'=>'Xếp hạng theo tổng thời gian chơi được game ghi nhận.',
            'sql'=>"SELECT charname AS name,FLOOR(totalTimePlay/60) AS score,lastLv AS secondary FROM tob_char WHERE charname<>'' AND del=1 AND ban=0 AND totalTimePlay>0 ORDER BY totalTimePlay DESC,lastLv DESC,id ASC LIMIT 10"
        ],
        'supply' => [
            'label'=>'Vận tiêu','short'=>'Vận tiêu','metric'=>'Cấp tiêu','secondary'=>null,
            'description'=>'Nhân vật có cấp tiêu đang nhận cao nhất; không phải xếp hạng lượt vận tiêu tích lũy.',
            'sql'=>"SELECT namechar AS name,lv AS score,0 AS secondary FROM tob_vantieu WHERE namechar<>'' AND lv>0 ORDER BY lv DESC,idchar ASC LIMIT 10"
        ],
        'arena' => [
            'label'=>'Điểm đấu hạng','short'=>'Đấu hạng','metric'=>'Điểm hạng','secondary'=>'Điểm đấu',
            'description'=>'Bảng điểm xếp hạng thách đấu.',
            'sql'=>"SELECT charname AS name,pointrank AS score,point AS secondary FROM tob_char_thach_dau WHERE charname<>'' AND pointrank>0 ORDER BY pointrank DESC,point DESC,id ASC LIMIT 10"
        ],
        'level' => [
            'label'=>'Cấp độ','short'=>'Cấp độ','metric'=>'Cấp','secondary'=>'EXP',
            'description'=>'Những nhân vật có cấp độ cao nhất; cùng cấp thì so kinh nghiệm.',
            'source'=>'tob_char_luong.lv / exp',
            'sql'=>"SELECT charname AS name,lv AS score,exp AS secondary
                    FROM tob_char_luong WHERE charname <> ''
                    ORDER BY lv DESC,exp DESC,id ASC LIMIT 10"
        ],
        'honor' => [
            'label'=>'Danh vọng','short'=>'Danh vọng','metric'=>'Danh vọng','secondary'=>'Cấp',
            'description'=>'Những nhân vật có danh vọng cao nhất.',
            'source'=>'tob_char_luong.honor',
            'sql'=>"SELECT charname AS name,honor AS score,lv AS secondary
                    FROM tob_char_luong WHERE charname <> '' AND honor > 0
                    ORDER BY honor DESC,lv DESC,id ASC LIMIT 10"
        ],
        'pk' => [
            'label'=>'Điểm PK','short'=>'PK','metric'=>'Điểm PK','secondary'=>'Danh vọng',
            'description'=>'Những nhân vật dẫn đầu về điểm PK.',
            'source'=>'tob_char_luong.nPK',
            'sql'=>"SELECT charname AS name,nPK AS score,honor AS secondary
                    FROM tob_char_luong WHERE charname <> '' AND nPK > 0
                    ORDER BY nPK DESC,honor DESC,id ASC LIMIT 10"
        ],
        'clan' => [
            'label'=>'Bang hội','short'=>'Bang hội','metric'=>'Cấp bang','secondary'=>'EXP bang',
            'description'=>'Xếp bang hội đang hoạt động theo cấp bang và kinh nghiệm bang.',
            'source'=>'tob_clan.lvClan / xp',
            'sql'=>"SELECT clanName AS name,COALESCE(lvClan,0) AS score,COALESCE(xp,0) AS secondary
                    FROM tob_clan WHERE isdel=0 AND clanName IS NOT NULL AND clanName <> ''
                    ORDER BY COALESCE(lvClan,0) DESC,COALESCE(xp,0) DESC,id_clan ASC LIMIT 10"
        ],
        'nap' => [
            'label'=>'Top nạp','short'=>'Top nạp','metric'=>'Tổng nạp VNĐ','secondary'=>'Thẻ cào',
            'description'=>'Tổng nạp theo tài khoản, gồm giao dịch payOS và thẻ cào hợp lệ; đối chiếu cùng team_user.tongnap, không cộng lặp các nguồn.',
        ],
        'napgame' => [
            'label'=>'Nạp trong game','short'=>'Nạp game','metric'=>'Lượng đã nạp','secondary'=>'Điểm Top',
            'description'=>'Thành tích nạp Lượng do game ghi nhận cho nhân vật.',
            'sql'=>"SELECT charname AS name,luongnap AS score,toppoint AS secondary
                    FROM tob_event_top WHERE charname<>'' AND luongnap>0
                    ORDER BY luongnap DESC,toppoint DESC,id ASC LIMIT 10"
        ],
        'rich' => [
            'label'=>'Top đại gia','short'=>'Đại gia','metric'=>'Xu sở hữu','secondary'=>'Lượng sở hữu',
            'description'=>'Những nhân vật giàu Xu nhất trên bảng tổng hợp nhân vật của game.',
            'sql'=>"SELECT charname AS name,gold AS score,luong AS secondary
                    FROM tob_char_luong WHERE charname<>'' AND gold>0
                    ORDER BY gold DESC,luong DESC,id ASC LIMIT 10"
        ],
        'richluong' => [
            'label'=>'Đại gia Lượng','short'=>'Giàu Lượng','metric'=>'Lượng sở hữu','secondary'=>'Xu sở hữu',
            'description'=>'Những nhân vật đang có nhiều Lượng nhất theo bảng tổng hợp game.',
            'sql'=>"SELECT charname AS name,luong AS score,gold AS secondary
                    FROM tob_char_luong WHERE charname<>'' AND luong>0
                    ORDER BY luong DESC,gold DESC,id ASC LIMIT 10"
        ],
        'event' => [
            'label'=>'Điểm sự kiện','short'=>'Sự kiện','metric'=>'Điểm sự kiện','secondary'=>'Lượng sự kiện',
            'description'=>'Những người chơi tích lũy nhiều điểm sự kiện nhất.',
            'source'=>'tob_top_event.point1',
            'sql'=>"SELECT charname AS name,point1 AS score,luong AS secondary
                    FROM tob_top_event WHERE charname <> '' AND point1 > 0
                    ORDER BY point1 DESC,luong DESC,id ASC LIMIT 10"
        ],
        'duel' => [
            'label'=>'Thách đấu','short'=>'Thách đấu','metric'=>'Điểm thách đấu','secondary'=>'Cấp',
            'description'=>'Những đối thủ đang dẫn đầu về điểm thách đấu.',
            'source'=>'tob_char_thach_dau.point',
            'sql'=>"SELECT charname AS name,point AS score,lv AS secondary
                    FROM tob_char_thach_dau WHERE charname <> '' AND point > 0
                    ORDER BY point DESC,lv DESC,id ASC LIMIT 10"
        ],
        'siege' => [
            'label'=>'Chiếm thành','short'=>'Chiếm thành','metric'=>'Lần chiến thắng','secondary'=>null,
            'description'=>'Các bang hội có số lần thắng chiếm thành cao nhất.',
            'source'=>'tob_chiemthanh.win',
            'sql'=>"SELECT clan AS name,win AS score,0 AS secondary
                    FROM tob_chiemthanh WHERE clan <> '' AND win > 0
                    ORDER BY win DESC,clan ASC LIMIT 10"
        ],
        'wc' => [
            'label'=>'Sự kiện WC','short'=>'WC','metric'=>'Điểm sự kiện','secondary'=>'Lượng sự kiện',
            'description'=>'Thành tích của người chơi trong sự kiện WC.',
            'source'=>'tob_top_event_wc.point1',
            'sql'=>"SELECT charname AS name,point1 AS score,luong AS secondary
                    FROM tob_top_event_wc WHERE charname <> '' AND point1 > 0
                    ORDER BY point1 DESC,luong DESC,id ASC LIMIT 10"
        ],
    ];
}

/**
 * Approved card topups are stored in protected storage/topup/orders/*.json,
 * not in kp_bank_orders. Never load sealed PIN/serial to the public response.
 */
function kp_rankings_paid_card_totals(): array {
    require_once __DIR__.'/topup_core.php';
    $root=kp_topup_root();
    $cache=$root.'/topnap-card-cache.json';
    try{
        if(is_file($cache) && (int)@filemtime($cache)>time()-60){
            $record=kp_topup_read_file($cache);
            if(is_array($record) && is_array($record['totals']??null))return $record['totals'];
        }
    }catch(Throwable $e){kp_log('Top nạp thẻ cache unreadable',$e);}
    $dir=$root.'/orders';
    $totals=[];
    if(is_dir($dir)){
        foreach(glob($dir.'/*.json')?:[] as $path){
            $raw=@file_get_contents($path);
            if(!is_string($raw)||strlen($raw)>30000)continue;
            $order=json_decode($raw,true);
            if(!is_array($order))continue;
            $status=(string)($order['status']??'');
            // Provider-verified, never pending/failed/ambiguous bad-value cards.
            if(!in_array($status,['wallet_credited','wallet_crediting','verified_pending_delivery','queued','completed'],true))continue;
            $userId=(int)($order['user_id']??0);
            $amount=(int)($order['amount']??0);
            if($userId<1||$amount<1||$amount>1000000000)continue;
            $key=(string)$userId;
            $totals[$key]=($totals[$key]??0)+$amount;
        }
    }
    try{kp_topup_write_file($cache,['at'=>time(),'totals'=>$totals]);}
    catch(Throwable $e){kp_log('Top nạp thẻ cache not writable',$e);}
    return $totals;
}

/**
 * tongnap might already include old payOS/card payments on the live game
 * database. Using MAX(tongnap, verifiedPayOS+verifiedCards) rather than SUM
 * avoids double counting unknown historical sources.
 */
function kp_rankings_topnap(): array {
    static $cached=null;
    if($cached!==null)return $cached;
    $db=kp_db();
    $storedColumn=(bool)$db->query("SHOW COLUMNS FROM team_user LIKE 'tongnap'")->fetch(PDO::FETCH_ASSOC);
    $sql=$storedColumn
       ? "SELECT id,username,COALESCE(tongnap,0) AS stored_vnd FROM team_user WHERE ban=0 AND username IS NOT NULL AND username<>''"
       : "SELECT id,username,0 AS stored_vnd FROM team_user WHERE ban=0 AND username IS NOT NULL AND username<>''";
    $users=$db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $bank=[];
    try{
        $sql="SELECT user_id,SUM(amount) AS paid_vnd FROM kp_bank_orders
              WHERE amount>0 AND
                (status IN ('completed','queued','wallet_credited')
                 OR (status IN ('crediting','paid_waiting_delivery','wallet_crediting','needs_review')
                     AND paid_at IS NOT NULL))
              GROUP BY user_id";
        foreach($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r)
            $bank[(string)(int)$r['user_id']]=(int)$r['paid_vnd'];
    }catch(Throwable $e){kp_log('Top nạp payOS source unavailable',$e);}
    try{$cards=kp_rankings_paid_card_totals();}
    catch(Throwable $e){kp_log('Top nạp thẻ source unavailable',$e);$cards=[];}
    $rows=[];
    foreach($users as $u){
        $id=(string)(int)$u['id'];
        $payos=max(0,(int)($bank[$id]??0));
        $card=max(0,(int)($cards[$id]??0));
        $stored=max(0,(int)($u['stored_vnd']??0));
        $verified=$payos+$card;
        $total=max($stored,$verified);
        if($total<=0)continue;
        $rows[]=['name'=>(string)$u['username'],'score'=>$total,'secondary'=>$card,
          'bank_vnd'=>$payos,'card_vnd'=>$card,'stored_vnd'=>$stored];
    }
    usort($rows,function($a,$b){
        $cmp=$b['score']<=>$a['score'];
        return $cmp!==0?$cmp:strcmp($a['name'],$b['name']);
    });
    return $cached=array_slice($rows,0,10);
}

function kp_rankings_fetch($type)
{
    $catalog=kp_rankings_catalog();
    if (!isset($catalog[$type])) throw new InvalidArgumentException('Loại bảng xếp hạng không hợp lệ.');
    if($type==='nap')return kp_rankings_topnap();
    $st=kp_db()->query($catalog[$type]['sql']);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Only publish rankings that really have records. A broken or missing game
 * table is skipped individually; never expose SQL errors to players.
 */
function kp_rankings_available()
{
    $available=[];
    foreach(kp_rankings_catalog() as $key=>$category){
        try {
            if($key==='nap'){
                if(kp_rankings_topnap())$available[$key]=$category;
                continue;
            }
            $st=kp_db()->query($category['sql']);
            if($st && $st->fetch(PDO::FETCH_ASSOC)!==false)$available[$key]=$category;
        } catch(Throwable $e) {
            kp_log('rankings hidden (empty or unavailable): '.$key,$e);
        }
    }
    return $available;
}
