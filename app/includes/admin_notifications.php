<?php
if(!defined('KP_ROOT')){http_response_code(403);exit;}
require_once __DIR__.'/user_center.php';
require_once __DIR__.'/topup_core.php';
require_once __DIR__.'/wallet_vnd.php';
function kp_admin_attention_summary():array{
 $r=['total'=>0,'unread'=>0,'points'=>0,'cards'=>0,'bank'=>0,'wallet'=>0,'latest'=>null];
 try{$db=kp_db();$r['unread']=(int)$db->query('SELECT COUNT(*) FROM kp_admin_notifications WHERE is_read=0')->fetchColumn();$r['points']=(int)$db->query(kp_points_sql("SELECT COUNT(*) FROM kp_points_redemptions WHERE status='pending'"))->fetchColumn();$r['bank']=(int)$db->query("SELECT COUNT(*) FROM kp_bank_orders WHERE status IN ('queued','paid_waiting_delivery','crediting','needs_review','wallet_crediting')")->fetchColumn();$r['latest']=$db->query('SELECT title,body FROM kp_admin_notifications WHERE is_read=0 ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC)?:null;}catch(Throwable $e){kp_log('admin attention database unavailable',$e);}
 try{foreach(kp_topup_recent('orders',null,500) as $x)if(in_array((string)($x['status']??''),['submitting','verified_pending_delivery','queued','crediting','needs_review','wallet_crediting'],true))$r['cards']++;}catch(Throwable $e){kp_log('admin attention card journal unavailable',$e);}
 try{foreach(kp_vnd_recent_exchanges(null,500) as $x)if(in_array((string)($x['status']??''),['prepared','granting','needs_review'],true))$r['wallet']++;}catch(Throwable $e){kp_log('admin attention wallet journal unavailable',$e);}
 $r['total']=$r['points']+$r['cards']+$r['bank']+$r['wallet']+max(0,$r['unread']-$r['points']);return $r;
}
