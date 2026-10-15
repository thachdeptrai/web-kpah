<?php
/** Read-only rollups of sampled farmer points and Luong. */
if(!defined('KP_ROOT')){http_response_code(403);exit;}

function kp_farmer_monitor_ready(PDO $db): bool {
    try {
        return (bool)$db->query("SHOW TABLES LIKE 'kp_farmer_monitor_state'")->fetchColumn()
            && (bool)$db->query("SHOW TABLES LIKE 'kp_farmer_monitor_changes'")->fetchColumn();
    } catch(Throwable $e) {
        kp_log('monitor schema not ready',$e);
        return false;
    }
}

/** Hourly observed gains (positive net balance differences), per character. */
function kp_farmer_monitor_hourly(PDO $db,array $ids): array {
    $ids=array_values(array_unique(array_filter(array_map('intval',$ids),function($id){return $id>0;})));
    if(!$ids)return [];
    $ph=implode(',',array_fill(0,count($ids),'?'));
    $st=$db->prepare("SELECT char_id,first_sampled_at,sampled_at FROM kp_farmer_monitor_state WHERE char_id IN ($ph)");
    $st->execute($ids);
    $data=[];
    foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){
        $id=(int)$r['char_id'];
        $first=strtotime($r['first_sampled_at']);
        $last=strtotime($r['sampled_at']);
        $coverage=max(0,min(time(),$last)-max(time()-86400,$first));
        $data[$id]=['first'=>$r['first_sampled_at'],'last'=>$r['sampled_at'],
            'fresh'=>$last>=time()-900,'hours'=>$coverage/3600,
            'points_1h'=>0,'luong_1h'=>0,'luong_24h'=>0,'avg_luong_hour'=>null];
    }
    if(!$data)return [];
    $sql="SELECT char_id,
          SUM(CASE WHEN observed_at>=? AND points_delta>0 THEN points_delta ELSE 0 END) AS points_hour,
          SUM(CASE WHEN observed_at>=? AND luong_delta>0 THEN luong_delta ELSE 0 END) AS luong_hour,
          SUM(CASE WHEN luong_delta>0 THEN luong_delta ELSE 0 END) AS luong_day
          FROM kp_farmer_monitor_changes
          WHERE observed_at>=? AND char_id IN ($ph)
          GROUP BY char_id";
    $hour=date('Y-m-d H:i:s',time()-3600);
    $day=date('Y-m-d H:i:s',time()-86400);
    $st=$db->prepare($sql);
    $st->execute(array_merge([$hour,$hour,$day],$ids));
    foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){
        $id=(int)$r['char_id'];
        if(!isset($data[$id]))continue;
        $data[$id]['points_1h']=(int)$r['points_hour'];
        $data[$id]['luong_1h']=(int)$r['luong_hour'];
        $data[$id]['luong_24h']=(int)$r['luong_day'];
    }
    foreach($data as &$r){
        if($r['hours']>0.05)$r['avg_luong_hour']=$r['luong_24h']/$r['hours'];
    }
    unset($r);
    return $data;
}

function kp_farmer_monitor_hot(PDO $db,string $which='points'): array {
    $order=$which==='luong'?'luong_up':'points_up';
    $st=$db->prepare("SELECT char_id,MAX(charname) AS charname,
        SUM(CASE WHEN points_delta>0 THEN points_delta ELSE 0 END) AS points_up,
        SUM(CASE WHEN luong_delta>0 THEN luong_delta ELSE 0 END) AS luong_up,
        MAX(observed_at) AS last_observed
        FROM kp_farmer_monitor_changes
        WHERE observed_at>=? GROUP BY char_id
        ORDER BY $order DESC,char_id ASC LIMIT 8");
    $st->execute([date('Y-m-d H:i:s',time()-3600)]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
