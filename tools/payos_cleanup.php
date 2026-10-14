<?php
// CLI only: run every minute from Windows Task Scheduler.
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require dirname(__DIR__).'/app/includes/bootstrap.php';
require_once dirname(__DIR__).'/app/includes/bank_payos.php';
if(!kp_bank_cfg()['ready']){fwrite(STDERR,"payOS is not configured\n");exit(1);}
try{
 $n=kp_bank_cleanup(100);
 echo "payOS cleaned ".$n." cancelled unpaid orders\n";
 // Retry new wallet payments independently of the legacy game queue.
 $st=kp_db()->query("SELECT order_code FROM kp_bank_orders WHERE delivery_mode='wallet' AND status='wallet_crediting' ORDER BY id ASC LIMIT 40");
 $retried=0;
 foreach($st->fetchAll(PDO::FETCH_COLUMN) as $code){
  try{kp_bank_reconcile((string)$code);$retried++;}
  catch(Throwable $e){kp_log('payOS VNĐ wallet retry deferred',$e);}
 }
 echo "payOS rechecked ".$retried." wallet credit attempts\n";
 // Recover existing paid-but-not-delivered orders only after queue verification.
 $cfg=kp_bank_cfg();
 if($cfg['queue_verified']){
  $st=kp_db()->query("SELECT order_code FROM kp_bank_orders WHERE status='paid_waiting_delivery' ORDER BY id ASC LIMIT 20");
  $checked=0;
  foreach($st->fetchAll(PDO::FETCH_COLUMN) as $code){
   try{kp_bank_reconcile((string)$code);$checked++;}
   catch(Throwable $e){kp_log('payOS paid-order retry deferred',$e);}
  }
  echo "payOS rechecked ".$checked." paid orders awaiting delivery\n";
 }
}
catch(Throwable $e){kp_log('payOS CLI cleanup failed',$e);fwrite(STDERR,"Cleanup failed\n");exit(1);}
