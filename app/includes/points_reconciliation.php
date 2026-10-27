<?php
/**
 * READ-ONLY Point reconciliation. Never correct, repeat webhook, refund,
 * alter wallet, or write to any MyISAM game table from this report.
 */
if(!defined('KP_ROOT')){http_response_code(403);exit;}
function kp_points_reconcile(PDO $db): array {
 $report=['wallet_mismatch'=>[],'overdue_orders'=>[],'missing_payos_ledger'=>[],'errors'=>[]];
 $statements=[
  'wallet_mismatch'=>"SELECT w.user_id,w.balance,COALESCE(SUM(l.points_delta),0) AS ledger_balance,
  w.total_earned,w.total_spent
  FROM kp_points_wallet w LEFT JOIN kp_points_ledger l ON l.user_id=w.user_id
  GROUP BY w.user_id,w.balance,w.total_earned,w.total_spent
  HAVING w.balance<>COALESCE(SUM(l.points_delta),0)
  ORDER BY w.user_id DESC LIMIT 100",
  'overdue_orders'=>"SELECT id,user_id,reward_title,status,created_at
  FROM kp_points_redemptions
  WHERE status IN ('pending','approved','delivering') AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
  ORDER BY created_at ASC LIMIT 100",
  'missing_payos_ledger'=>"SELECT b.order_code,b.user_id,b.amount,b.created_at
  FROM kp_bank_orders b
  INNER JOIN kp_points_program p ON p.id=1
  LEFT JOIN kp_points_ledger l ON l.source_type='payos' AND l.source_ref=CAST(b.order_code AS CHAR)
  WHERE b.status IN ('completed','wallet_credited')
  AND b.created_at>=p.activated_at AND l.id IS NULL
  ORDER BY b.id DESC LIMIT 100"
 ];
 foreach($statements as $type=>$sql) {
  try {$report[$type]=$db->query($sql)->fetchAll(PDO::FETCH_ASSOC);}
  catch(Throwable $e){
   $report['errors'][]=$type;
   if(function_exists('kp_log'))kp_log('Point reconciliation failed '.$type,$e);
  }
 }
 return $report;
}
