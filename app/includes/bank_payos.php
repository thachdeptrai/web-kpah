<?php
if (!defined('KP_ROOT')) {http_response_code(403);exit;}
require_once __DIR__.'/topup_core.php';
function kp_bank_cfg() {
 $localFile=KP_ROOT.'/config/payos.local.php';
 $v=is_file($localFile)?require $localFile:[];
 if(!is_array($v))$v=[];
 $get=function($key,$env)use($v){$e=getenv($env);return is_string($e)&&$e!==''?$e:(string)($v[$key]??'');};
 $client=$get('client_id','KPAH_PAYOS_CLIENT_ID');$api=$get('api_key','KPAH_PAYOS_API_KEY');$checksum=$get('checksum_key','KPAH_PAYOS_CHECKSUM_KEY');
 return ['client_id'=>$client,'api_key'=>$api,'checksum_key'=>$checksum,'ready'=>$client!==''&&$api!==''&&strlen($checksum)>=16&&function_exists('curl_init'),
 'queue_verified'=>kp_topup_cfg()['game_queue_verified'],'server_id'=>kp_topup_cfg()['server_id']];
}
function kp_bank_sign(array $data,$key) {
 ksort($data,SORT_STRING);$items=[];
 foreach($data as $k=>$v)$items[]=$k.'='.($v===null?'':(string)$v);
 return hash_hmac('sha256',implode('&',$items),$key);
}
function kp_bank_api($method,$suffix,$body=null) {
 $cfg=kp_bank_cfg();
 if(!$cfg['ready'])throw new RuntimeException('payOS chưa cấu hình.');
 $h=curl_init('https://api-merchant.payos.vn/v2/payment-requests'.$suffix);
 $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>18,
 CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false,
 CURLOPT_HTTPHEADER=>['x-client-id: '.$cfg['client_id'],'x-api-key: '.$cfg['api_key'],'Content-Type: application/json']];
 if($method==='POST'){$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=json_encode($body,JSON_UNESCAPED_SLASHES);}
 curl_setopt_array($h,$opts);$raw=curl_exec($h);$status=(int)curl_getinfo($h,CURLINFO_HTTP_CODE);curl_close($h);
 if(!is_string($raw)||strlen($raw)>100000||$status!==200)throw new RuntimeException('Chưa nhận được phản hồi hợp lệ từ payOS.');
 $data=json_decode($raw,true);
 if(!is_array($data)||($data['code']??'')!=='00'||!is_array($data['data']??null))throw new RuntimeException('payOS chưa chấp nhận yêu cầu.');
 return $data['data'];
}
function kp_bank_rewards($amount,$currency){
 // Bank payments do not suffer card-processing commission.
 return kp_card_package_units($amount,$amount,$currency);
}
function kp_bank_create($user,$amount,$currency,$csrf){
 if(!kp_csrf_check($csrf))throw new RuntimeException('Phiên bảo mật hết hạn.');
 $cfg=kp_bank_cfg();
 if(!$cfg['ready'])throw new RuntimeException('Thanh toán ngân hàng chưa mở.');
 if(!in_array($currency,['luong','luonglock'],true)||!isset(kp_card_packages()[$amount]))throw new RuntimeException('Mệnh giá không hợp lệ.');
 list($allow,$retry)=kp_rate_limit('bank_create',$user['id'].'|'.kp_client_ip(),5,3600,true);
 if(!$allow)throw new RuntimeException('Bạn tạo quá nhiều đơn nạp. Hãy thử lại sau.');
 $db=kp_db();
 // Random positive order code fitting payOS signed 64-bit and MySQL BIGINT.
 $code=random_int(100000000,999999999);
 $units=kp_bank_rewards($amount,$currency);
 $stmt=$db->prepare('INSERT INTO kp_bank_orders(order_code,user_id,username,amount,currency,units,status) VALUES (?,?,?,?,?,?,"creating")');
 $stmt->execute([$code,(int)$user['id'],(string)$user['username'],$amount,$currency,$units]);
 $description='KPAH'.$code;
 $base='https://nghienkpah.io.vn/nap-ngan-hang';
 $payload=['orderCode'=>$code,'amount'=>$amount,'description'=>$description,
 'returnUrl'=>$base.'?result=return','cancelUrl'=>$base.'?result=cancel','expiredAt'=>time()+900];
 $payload['signature']=kp_bank_sign(['amount'=>$amount,'cancelUrl'=>$payload['cancelUrl'],'description'=>$description,'orderCode'=>$code,'returnUrl'=>$payload['returnUrl']],$cfg['checksum_key']);
 try {
  $link=kp_bank_api('POST','',$payload);
  if(!isset($link['checkoutUrl'])||!filter_var($link['checkoutUrl'],FILTER_VALIDATE_URL)||parse_url($link['checkoutUrl'],PHP_URL_HOST)!=='pay.payos.vn')throw new RuntimeException('Link thanh toán không hợp lệ.');
  $update=$db->prepare('UPDATE kp_bank_orders SET status="pending",checkout_url=?,payment_link_id=? WHERE order_code=?');
  $update->execute([$link['checkoutUrl'],(string)($link['paymentLinkId']??''),$code]);
  return $link['checkoutUrl'];
 }catch(Throwable $e) {
  // DO NOT retry using another orderCode automatically: API call may have succeeded.
  kp_log('payOS link creation uncertain',$e);
  throw new RuntimeException('Chưa tạo được link, hãy kiểm tra đơn trước khi tạo lại.');
 }
}
function kp_bank_reconcile($code){
 $db=kp_db();$cfg=kp_bank_cfg();
 $db->beginTransaction();
 try {
  $stmt=$db->prepare('SELECT * FROM kp_bank_orders WHERE order_code=? FOR UPDATE');$stmt->execute([$code]);$order=$stmt->fetch(PDO::FETCH_ASSOC);
  if(!$order){$db->rollBack();return 'unknown';}
  if(in_array($order['status'],['queued','crediting','needs_review'],true)){$db->commit();return $order['status'];}
  $response=kp_bank_api('GET','/'.rawurlencode((string)$code));
  if((string)($response['orderCode']??'')!==(string)$code || (int)($response['amount']??-1)!==(int)$order['amount'] || ($response['status']??'')!=='PAID'){
   $db->commit();return 'pending';
  }
  if((int)($response['amountPaid']??0)<(int)$order['amount']){$db->commit();return 'pending';}
  $ref=substr((string)($response['id']??''),0,100);
  if(!$cfg['queue_verified']||$cfg['server_id']<0){
   $db->prepare('UPDATE kp_bank_orders SET status="paid_waiting_delivery",provider_reference=?,paid_at=COALESCE(paid_at,NOW()) WHERE order_code=?')->execute([$ref,$code]);
   $db->commit();return 'paid_waiting_delivery';
  }
  $valid=$db->prepare('SELECT 1 FROM team_user WHERE id=? AND username=? AND ban=0');
  $valid->execute([(int)$order['user_id'],(string)$order['username']]);
  if(!$valid->fetchColumn()){$db->prepare('UPDATE kp_bank_orders SET status="needs_review" WHERE order_code=?')->execute([$code]);$db->commit();return 'needs_review';}
  // MyISAM queue insert is not transactional. Mark crediting durably first.
  $db->prepare('UPDATE kp_bank_orders SET status="crediting",provider_reference=?,paid_at=COALESCE(paid_at,NOW()) WHERE order_code=?')->execute([$ref,$code]);
  $db->commit();
  try {
   kp_topup_queue_existing_board(['user_id'=>$order['user_id'],'username'=>$order['username'],
    'currency'=>$order['currency'],'units'=>$order['units']]);
   $db->prepare('UPDATE kp_bank_orders SET status="queued" WHERE order_code=? AND status="crediting"')->execute([$code]);
   return 'queued';
  }catch(Throwable $e){
   kp_log('bank queue needs manual verification',$e);
   $db->prepare('UPDATE kp_bank_orders SET status="needs_review" WHERE order_code=? AND status="crediting"')->execute([$code]);
   return 'needs_review';
  }
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
