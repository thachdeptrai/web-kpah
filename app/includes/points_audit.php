<?php
/** Optional append-only Point audit. Ledger is authoritative for balances. */
if(!defined('KP_ROOT')){http_response_code(403);exit;}
function kp_points_audit_enabled(PDO $db): bool {
 try{$st=$db->query("SHOW TABLES LIKE 'kp_points_audit'");return $st && $st->fetchColumn()!==false;}
 catch(Throwable $e){return false;}
}
function kp_points_audit_context(array $server): array {
 $ip=(string)($server['REMOTE_ADDR']??'');
 if(!filter_var($ip,FILTER_VALIDATE_IP))$ip='';
 $ua=substr((string)($server['HTTP_USER_AGENT']??''),0,300);
 $device='Không rõ';
 if(preg_match('/iPad|Tablet|Android(?!.*Mobile)/i',$ua))$device='Máy tính bảng';
 elseif(preg_match('/iPhone|iPod|Mobile|Android/i',$ua))$device='Điện thoại';
 elseif(preg_match('/Windows|Macintosh|Linux|X11/i',$ua))$device='Máy tính';
 $browser='Không rõ';
 if(preg_match('/Edg\/|EdgiOS\/|EdgA\//i',$ua))$browser='Edge';
 elseif(preg_match('/Firefox\/|FxiOS\//i',$ua))$browser='Firefox';
 elseif(preg_match('/Chrome\/|CriOS\//i',$ua))$browser='Chrome';
 elseif(preg_match('/Safari\//i',$ua))$browser='Safari';
 // GeoIP fields must be set by trusted server modules, not by client POST
 // or arbitrary CF/X-Forwarded request headers. This is NOT GPS.
 $loc=[];
 foreach(['GEOIP_CITY','GEOIP_REGION','GEOIP_COUNTRY_NAME'] as $key){
  $value=trim((string)($server[$key]??''));
  if($value!=='' && strlen($value)<=60 && preg_match('/\A[\p{L}\p{M}\p{N} .,\x27()\-]+\z/uD',$value))$loc[]=$value;
 }
 return ['ip'=>$ip,'device'=>$device,'browser'=>$browser,'location'=>implode(', ',array_unique($loc))];
}
function kp_points_audit_append(PDO $db,int $userId,?int $adminId,?int $redemptionId,string $operation,string $reference,int $delta,?int $before,?int $after): void {
 if(!kp_points_audit_enabled($db))return;
 if($userId<=0||!preg_match('/\A[a-z_]{3,24}\z/D',$operation)||strlen($reference)>80)throw new RuntimeException('Point audit event invalid.');
 $ctx=$operation==='award'
    ? ['ip'=>'','device'=>'Hệ thống','browser'=>'PayOS/Thẻ','location'=>'']
    : kp_points_audit_context($_SERVER);
 // Webhooks and reconciliation workers have their OWN IP/UA; never label
 // provider-server metadata as if it belonged to the paying customer.
 $st=$db->prepare('INSERT INTO kp_points_audit (user_id,actor_admin_id,redemption_id,operation,reference,points_delta,balance_before,balance_after,ip,device_type,browser,location_hint) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
 $st->execute([$userId,$adminId,$redemptionId,$operation,$reference,$delta,$before,$after,$ctx['ip'],$ctx['device'],$ctx['browser'],$ctx['location']]);
}
/** Return only the caller-owned rows unless explicitly authorized admin sets null. */
function kp_points_audit_list(PDO $db,?int $owner,int $limit=80): array {
 if(!kp_points_audit_enabled($db)||($owner!==null && $owner<1))return [];
 $limit=max(1,min(200,$limit));
 if($owner===null){$st=$db->query('SELECT * FROM kp_points_audit ORDER BY id DESC LIMIT '.$limit);}
 else{$st=$db->prepare('SELECT * FROM kp_points_audit WHERE user_id=? ORDER BY id DESC LIMIT '.$limit);$st->execute([$owner]);}
 return $st->fetchAll(PDO::FETCH_ASSOC);
}
