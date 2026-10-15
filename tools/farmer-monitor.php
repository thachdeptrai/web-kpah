<?php
/**
 * Read-only observer of KPAH game balances. PHP CLI only.
 * php tools/farmer-monitor.php install|collect|status
 * Balance deltas have UNKNOWN game source without Java-side event logging.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
define('KP_ROOT', dirname(__DIR__));
require_once KP_ROOT.'/app/includes/config_loader.php';
require_once KP_ROOT.'/app/includes/security.php';
require_once KP_ROOT.'/app/includes/db.php';
require_once KP_ROOT.'/app/includes/farmer_points.php';
date_default_timezone_set((string) kp_config('app.timezone', 'Asia/Ho_Chi_Minh'));

function kp_farmer_monitor_install(PDO $db): void
{
    $sql=file_get_contents(KP_ROOT.'/database/farmer-monitor.sql');
    if(!is_string($sql))throw new RuntimeException('Schema file unavailable');
    foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql) as $part){
        $part=preg_replace('/^\s*--[^\r\n]*$/m','',$part);
        if(trim($part)!=='')$db->exec($part);
    }
}

function kp_farmer_monitor_collect(PDO $db): array
{
    $obtained=$db->query("SELECT GET_LOCK('kpah_farmer_monitor_collector',0)")->fetchColumn();
    if((int)$obtained!==1)throw new RuntimeException('Another collector is already running');
    $now=date('Y-m-d H:i:s');
    $scanned=0;$baselines=0;$changes=0;$errors=0;
    try{
        // PDO MySQL buffered query allows writes while scanning game character table.
        $rows=$db->query('SELECT id,charname,userId,pInfo,luong FROM tob_char');
        $get=$db->prepare('SELECT * FROM kp_farmer_monitor_state WHERE char_id=? FOR UPDATE');
        $create=$db->prepare(
            'INSERT INTO kp_farmer_monitor_state
             (char_id,charname,user_id,farmer_points,luong,boxes_today,farm_ms_today,first_sampled_at,sampled_at)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $update=$db->prepare(
            'UPDATE kp_farmer_monitor_state
             SET charname=?,user_id=?,farmer_points=?,luong=?,boxes_today=?,farm_ms_today=?,sampled_at=?
             WHERE char_id=?'
        );
        $log=$db->prepare(
            'INSERT INTO kp_farmer_monitor_changes
             (char_id,charname,user_id,started_at,observed_at,interval_seconds,
             points_before,points_after,points_delta,luong_before,luong_after,luong_delta,
             boxes_before,boxes_after,farm_ms_before,farm_ms_after)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        while(($r=$rows->fetch(PDO::FETCH_ASSOC))!==false){
            $id=(int)$r['id'];
            if($id<1)continue;
            $scanned++;
            $name=(string)$r['charname'];$uid=(int)$r['userId'];
            $points=kp_farmer_points($r['pInfo']);
            $boxes=kp_farmer_field($r['pInfo'],118,2147483647);
            $ms=kp_farmer_field($r['pInfo'],122);
            $luong=max(0,(int)$r['luong']);
            try{
                $db->beginTransaction();
                $get->execute([$id]);$before=$get->fetch(PDO::FETCH_ASSOC);$get->closeCursor();
                if(!$before){
                    $create->execute([$id,$name,$uid,$points,$luong,$boxes,$ms,$now,$now]);
                    $baselines++;
                }else{
                    $prevPoints=$before['farmer_points']===null?null:(int)$before['farmer_points'];
                    $prevLuong=(int)$before['luong'];
                    $prevBoxes=$before['boxes_today']===null?null:(int)$before['boxes_today'];
                    $prevMs=$before['farm_ms_today']===null?null:(int)$before['farm_ms_today'];
                    if($points!==$prevPoints || $luong!==$prevLuong || $boxes!==$prevBoxes || $ms!==$prevMs){
                        $gap=max(0,strtotime($now)-strtotime((string)$before['sampled_at']));
                        $delta=$prevPoints===null||$points===null?null:$points-$prevPoints;
                        $log->execute([$id,$name,$uid,$before['sampled_at'],$now,$gap,
                            $prevPoints,$points,$delta,$prevLuong,$luong,$luong-$prevLuong,
                            $prevBoxes,$boxes,$prevMs,$ms]);
                        $changes++;
                    }
                    $update->execute([$name,$uid,$points,$luong,$boxes,$ms,$now,$id]);
                }
                $db->commit();
            }catch(Throwable $e){
                if($db->inTransaction())$db->rollBack();
                $errors++; kp_log('farmer monitor char='.$id,$e);
            }
        }
        $env=getenv('KPAH_FARM_MONITOR_RETENTION_DAYS');
        $days=is_string($env)&&ctype_digit($env)?max(7,min(3650,(int)$env)):90;
        $db->exec('DELETE FROM kp_farmer_monitor_changes WHERE observed_at < DATE_SUB(NOW(), INTERVAL '.$days.' DAY) LIMIT 5000');
    }finally{
        $db->query("SELECT RELEASE_LOCK('kpah_farmer_monitor_collector')")->closeCursor();
    }
    return ['scanned'=>$scanned,'baselines'=>$baselines,'changes'=>$changes,'errors'=>$errors,'at'=>$now];
}

$action=$argv[1]??'status';
try{
    if(!in_array($action,['install','collect','status'],true)){
        fwrite(STDERR,"Usage: php tools/farmer-monitor.php install|collect|status\n");exit(2);
    }
    $db=kp_db();
    if($action==='install'){
        kp_farmer_monitor_install($db);
        echo "Monitoring tables installed. Game data not modified.\n";
    }elseif($action==='collect'){
        $r=kp_farmer_monitor_collect($db);
        echo json_encode($r,JSON_UNESCAPED_UNICODE)."\n";
        if($r['errors'])exit(1);
    }else{
        $state=$db->query('SELECT COUNT(*) AS chars,MAX(sampled_at) AS latest FROM kp_farmer_monitor_state')->fetch(PDO::FETCH_ASSOC);
        $events=$db->query('SELECT COUNT(*) FROM kp_farmer_monitor_changes')->fetchColumn();
        echo 'Characters: '.$state['chars'].', latest: '.($state['latest']?:'never').', changes: '.$events."\n";
    }
}catch(Throwable $e){
    kp_log('farmer monitor '.$action,$e);
    fwrite(STDERR,"Monitoring failed. Check database permissions, tables and PHP error log.\n");
    exit(1);
}
