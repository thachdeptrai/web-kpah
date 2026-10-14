<?php
// Webhook cannot award money based on browser/posted values.
// The authoritative payment state is queried directly using payOS credentials.
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/bank_payos.php';
kp_send_security_headers(false);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit('{"success":false}');}
if((int)($_SERVER['CONTENT_LENGTH']??0)>16384){http_response_code(413);exit('{"success":false}');}
try{
 $raw=file_get_contents('php://input');$payload=json_decode($raw,true);
 if(!is_array($payload)||!is_array($payload['data']??null)||!isset($payload['signature']))throw new RuntimeException('Malformed webhook');
 $data=$payload['data'];$code=filter_var($data['orderCode']??null,FILTER_VALIDATE_INT);
 if($code===false||$code<=0)throw new RuntimeException('Invalid order');
 $cfg=kp_bank_cfg();
 if(!$cfg['ready']){http_response_code(503);exit('{"success":false}');}
 // Even if a forged callback passes parsing, it cannot mark an unpaid order paid.
 $status=kp_bank_reconcile($code);
 if($status!=='unknown'){
  $hash=hash('sha256',(string)($data['reference']??'').'|'.$code.'|'.(string)($data['amount']??''));
  kp_db()->prepare('INSERT IGNORE INTO kp_bank_webhook_events(event_hash,order_code) VALUES(?,?)')->execute([$hash,$code]);
 }
 echo '{"success":true}';
}catch(Throwable $e){kp_log('payOS webhook failed',$e);http_response_code(503);echo '{"success":false}';}
