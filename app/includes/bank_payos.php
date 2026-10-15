<?php
if (!defined('KP_ROOT')) {http_response_code(403);exit;}
require_once __DIR__.'/topup_core.php';
require_once __DIR__.'/loyalty_points.php';
require_once __DIR__.'/wallet_vnd.php';
require_once __DIR__.'/bank_farmer_reward.php';
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
function kp_bank_is_wallet_order(array $order): bool {
 // This marker is snapshotted into the existing provider_reference column at
 // checkout creation. It survives the transition to status=completed.
 $ref=(string)($order['provider_reference']??'');
 return preg_match('/^VND_BONUS:(?:100|[1-9]?[0-9])$/D',$ref)===1
    || strpos((string)($order['status']??''),'wallet_')===0
    || (($order['delivery_mode']??'')==='wallet');
}
function kp_bank_order_bonus(array $order): int {
 $ref=(string)($order['provider_reference']??'');
 if(preg_match('/^VND_BONUS:([0-9]{1,3})$/D',$ref,$m))return min(100,(int)$m[1]);
 if(isset($order['bonus_percent']) && is_numeric($order['bonus_percent']))return max(0,min(100,(int)$order['bonus_percent']));
 return 0;
}
function kp_bank_create($user,$amount,$csrf){
 if(!kp_csrf_check($csrf))throw new RuntimeException('Phiên bảo mật hết hạn.');
 $cfg=kp_bank_cfg();
 if(!$cfg['ready'])throw new RuntimeException('Thanh toán ngân hàng chưa mở.');
 if(!isset(kp_card_packages()[$amount]))throw new RuntimeException('Mệnh giá không hợp lệ.');
 // Fail before requesting payment if migration is missing.
 $bonusPercent=kp_vnd_bonus_percent();
 kp_vnd_balance((int)$user['id']); // Verify wallet and team_user.vnd BEFORE creating a real payOS invoice.
 kp_vnd_require_tongnap(kp_db()); // Never accept payment if gross recharge cannot be recorded.
 // Per-user filesystem lock serializes simultaneous POSTs, including across PHP sessions.
 $lockFile=kp_topup_root().'/bank-create-'.(int)$user['id'].'.lock';
 $lock=@fopen($lockFile,'c+');
 if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Chưa thể khóa yêu cầu nạp. Thử lại sau.');
 try {
  $db=kp_db();
  // Reuse a single existing checkout. No second invoice while the first is pending.
  $st=$db->prepare("SELECT order_code,status,checkout_url,created_at FROM kp_bank_orders WHERE user_id=? AND status IN ('creating','pending','wallet_creating','wallet_pending') ORDER BY id DESC LIMIT 1");
  $st->execute([(int)$user['id']]);$active=$st->fetch(PDO::FETCH_ASSOC);
  if($active){
   if(in_array((string)$active['status'],['creating','pending'],true))
    throw new RuntimeException('Còn một QR theo cơ chế nạp Lượng cũ. Hãy đối soát/hủy đơn cũ trước khi tạo QR nạp VNĐ.');
   $created=strtotime((string)$active['created_at']);
   if($created!==false && $created>time()-600){
    $url=(string)($active['checkout_url']??'');
    if(in_array($active['status'],['pending','wallet_pending'],true) && filter_var($url,FILTER_VALIDATE_URL)
       && parse_url($url,PHP_URL_HOST)==='pay.payos.vn')return $url;
    throw new RuntimeException('Đơn thanh toán trước đang được tạo hoặc chờ đối soát. Không tạo QR khác, hãy kiểm tra lịch sử nạp.');
   }
   // Old invoices are checked/cancelled by the cleanup worker. Never overwrite them.
   throw new RuntimeException('Có một đơn cũ đang chờ payOS đối soát hoặc hết hạn. Vui lòng thử lại sau khi hệ thống xử lý.');
  }
  // Even if payOS/webhook completes the previous order instantly, a burst of
  // 1,000 POSTs must not generate a second invoice. The per-user flock above
  // makes this check and insertion serial on the same VPS filesystem.
  $st=$db->prepare('SELECT order_code,status,created_at FROM kp_bank_orders WHERE user_id=? ORDER BY id DESC LIMIT 1');
  $st->execute([(int)$user['id']]);$last=$st->fetch(PDO::FETCH_ASSOC);
  if($last){
   $lastAt=strtotime((string)$last['created_at']);
   if($lastAt!==false && $lastAt>time()-15){
    throw new RuntimeException('Bạn vừa tạo một lệnh nạp. Vui lòng xem giao dịch gần đây, không cần bấm Tạo QR thêm.');
   }
  }
  list($allow,$retry)=kp_rate_limit('bank_create',(string)$user['id'],3,900,true);
  if(!$allow)throw new RuntimeException('Mỗi tài khoản chỉ được tạo tối đa 3 lệnh nạp trong 15 phút. Thử lại sau '.(int)ceil($retry/60).' phút.');
  $code=random_int(100000000,999999999);
  $units=0;
  // Snapshot the optional farmer gift BEFORE creating the live payOS invoice.
  // No backdating: existing paid/pending orders never gain a gift retroactively.
  kp_bank_farmer_reserve($code,(int)$user['id'],(int)$amount,kp_bank_farmer_settings());
  $stmt=$db->prepare('INSERT INTO kp_bank_orders(order_code,user_id,char_id,username,amount,currency,units,status,provider_reference) VALUES (?,?,?,?,?,?,?,"wallet_creating",?)');
  $stmt->execute([$code,(int)$user['id'],0,(string)$user['username'],$amount,'luong',$units,'VND_BONUS:'.$bonusPercent]);
  $description='KPAH'.$code;
  $base='https://nghienkpah.io.vn/nap-ngan-hang';
  $payload=['orderCode'=>$code,'amount'=>$amount,'description'=>$description,
   'returnUrl'=>$base,'cancelUrl'=>$base,'expiredAt'=>time()+600];
  $payload['signature']=kp_bank_sign(['amount'=>$amount,'cancelUrl'=>$payload['cancelUrl'],'description'=>$description,'orderCode'=>$code,'returnUrl'=>$payload['returnUrl']],$cfg['checksum_key']);
  try {
   $link=kp_bank_api('POST','',$payload);
   if(!isset($link['checkoutUrl'])||!filter_var($link['checkoutUrl'],FILTER_VALIDATE_URL)||parse_url($link['checkoutUrl'],PHP_URL_HOST)!=='pay.payos.vn')throw new RuntimeException('Link thanh toán không hợp lệ.');
   $update=$db->prepare('UPDATE kp_bank_orders SET status="wallet_pending",checkout_url=?,payment_link_id=? WHERE order_code=? AND status="wallet_creating"');
   $update->execute([$link['checkoutUrl'],(string)($link['paymentLinkId']??''),$code]);
   if($update->rowCount()!==1)throw new RuntimeException('Trạng thái đơn thay đổi, cần đối soát.');
   return $link['checkoutUrl'];
  }catch(Throwable $e){
   kp_log('payOS link creation uncertain',$e);
   throw new RuntimeException('Chưa tạo được link, hãy kiểm tra đơn trước khi tạo lại.');
  }
 } finally {
  flock($lock,LOCK_UN);
  fclose($lock);
 }
}
/**
 * Direct MyISAM grant: permanently mark the InnoDB order as crediting before the
 * nontransactional UPDATE. Never auto-repeat after uncertain outcomes.
 */
function kp_bank_direct_grant(array $order) {
 $db=kp_db();
 $charId=(int)($order['char_id']??0);
 if($charId<1)throw new RuntimeException('Đơn không có nhân vật nhận.');
 $currency=(string)$order['currency'];
 if(!in_array($currency,['luong','luonglock'],true))throw new RuntimeException('Tài nguyên không hợp lệ.');
 $units=(int)$order['units'];
 if($units<1||$units>1000000000)throw new RuntimeException('Số Lượng không hợp lệ.');
 $st=$db->prepare('SELECT id FROM team_user WHERE id=? AND username=? AND ban=0');
 $st->execute([(int)$order['user_id'],(string)$order['username']]);
 if(!$st->fetchColumn())throw new RuntimeException('Tài khoản không khả dụng.');
 $st=$db->prepare('SELECT id,luong,luonglock FROM tob_char WHERE id=? AND userId=? AND del=1 AND ban=0 LIMIT 1');
 $st->execute([$charId,(int)$order['user_id']]);$char=$st->fetch(PDO::FETCH_ASSOC);
 if(!$char)throw new RuntimeException('Nhân vật không khả dụng.');
 $before=(string)$char[$currency];
 if(!ctype_digit($before)||strlen($before)>12||(float)$before>4294967295-$units)
  throw new RuntimeException('Số dư game cần đối soát.');
 // The currency identifier is selected exclusively from the fixed whitelist.
 $sql='UPDATE tob_char SET '.$currency.'='.$currency.'+? WHERE id=? AND userId=? AND '.$currency.'=? AND del=1 AND ban=0';
 $st=$db->prepare($sql);$st->execute([$units,$charId,(int)$order['user_id'],$before]);
 if($st->rowCount()!==1)throw new RuntimeException('Số dư đã thay đổi, cần đối soát.');
}
/**
 * Legacy recovery is deliberately admin-only and never scheduled.
 * The operator must confirm the game server no longer consumes the legacy queue.
 * One exact queue row is removed before attempting the direct MyISAM grant.
 */
function kp_bank_recover_queued($code,$charId,$queueId,$confirmation) {
 if($confirmation!=='TOI DA KIEM TRA CHUA NHAN LUONG')
  throw new RuntimeException('Chưa xác nhận đã kiểm tra nhân vật chưa nhận Lượng.');
 if(!is_int($code)||$code<1||!is_int($charId)||$charId<1||!is_int($queueId)||$queueId<1)
  throw new RuntimeException('Mã đơn, nhân vật hoặc dòng queue không hợp lệ.');
 $db=kp_db();
 $db->beginTransaction();
 try {
  $st=$db->prepare('SELECT * FROM kp_bank_orders WHERE order_code=? FOR UPDATE');
  $st->execute([$code]);$order=$st->fetch(PDO::FETCH_ASSOC);
  if(!$order || $order['status']!=='queued' || (int)($order['char_id']??0)!==0)
   throw new RuntimeException('Chỉ có thể xử lý đơn queued cũ chưa từng cộng trực tiếp.');
  $remote=kp_bank_api('GET','/'.rawurlencode((string)$code));
  if((string)($remote['orderCode']??'')!==(string)$code ||
     (int)($remote['amount']??0)!==(int)$order['amount'] ||
     ($remote['status']??'')!=='PAID' ||
     (int)($remote['amountPaid']??0)<(int)$order['amount'])
   throw new RuntimeException('payOS chưa xác minh thanh toán đầy đủ.');
  $st=$db->prepare('SELECT id FROM tob_char WHERE id=? AND userId=? AND del=1 AND ban=0');
  $st->execute([$charId,(int)$order['user_id']]);
  if(!$st->fetchColumn())throw new RuntimeException('Nhân vật nhận không thuộc tài khoản hoặc không khả dụng.');
  $col=$order['currency']==='luong'?'luong':($order['currency']==='luonglock'?'luongKhoa':null);
  if($col===null)throw new RuntimeException('Loại Lượng không hợp lệ.');
  $st=$db->prepare('SELECT id FROM board_naptien WHERE id=? AND username=? AND '.$col.'=? AND xu=0 AND ve=0');
  $st->execute([$queueId,(string)$order['username'],(int)$order['units']]);
  if(!$st->fetchColumn())throw new RuntimeException('Không tìm thấy dòng queue khớp đơn, kiểm tra thủ công.');
  // Commit durable intent prior to MyISAM removal; never retry automatically.
  $db->prepare('UPDATE kp_bank_orders SET status="crediting",char_id=? WHERE order_code=? AND status="queued"')->execute([$charId,$code]);
  $db->commit();
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 try {
  // Delete exactly one pending old instruction to prevent later queue consumption.
  $st=$db->prepare('DELETE FROM board_naptien WHERE id=? AND username=? AND '.$col.'=? AND xu=0 AND ve=0 LIMIT 1');
  $st->execute([$queueId,(string)$order['username'],(int)$order['units']]);
  if($st->rowCount()!==1)throw new RuntimeException('Không xác nhận được việc loại bỏ queue cũ.');
  $order['char_id']=$charId;
  kp_bank_direct_grant($order);
  $db->prepare('UPDATE kp_bank_orders SET status="completed" WHERE order_code=? AND status="crediting"')->execute([$code]);
  return 'completed';
 }catch(Throwable $e){
  kp_log('legacy queue recovery needs manual investigation',$e);
  $db->prepare('UPDATE kp_bank_orders SET status="needs_review" WHERE order_code=? AND status="crediting"')->execute([$code]);
  return 'needs_review';
 }
}
/**
 * New payment mode: verified payOS => account VNĐ wallet, never game currency.
 * InnoDB ledger uniquely keyed by payOS code allows safe webhook replay/retry.
 */
/**
 * Finalize a wallet top-up ONLY if its durable per-payment wallet journal
 * confirms that the exact account/amount/bonus was credited already.
 * Never change team_user.vnd or tongnap here; safe for repeated webhooks.
 */
function kp_bank_finalize_wallet_completed($code,array $order): string {
 $code=(string)$code;
 $status=(string)($order['status']??'');
 if($status==='completed')return 'completed';
 if(!in_array($status,['wallet_crediting','wallet_credited'],true))
  return $status;
 // Completed orders require a durable discriminator, otherwise a future
 // webhook might mistake them for old direct-to-game orders.
 $ref=(string)($order['provider_reference']??'');
 if(!preg_match('/^VND_BONUS:(?:100|[1-9]?[0-9])$/D',$ref))
  return $status;
 $journal=kp_topup_read_file(kp_vnd_record_path('payos',$code));
 if(!is_array($journal)||($journal['status']??'')!=='completed'
    ||(int)($journal['user_id']??0)!==(int)$order['user_id']
    ||(int)($journal['base']??-1)!==(int)$order['amount']
    ||(int)($journal['bonus_percent']??-1)!==kp_bank_order_bonus($order)){
  kp_log('payOS completed-state deferred: missing or mismatched wallet journal code='.$code);
  return $status;
 }
 $st=kp_db()->prepare('UPDATE kp_bank_orders SET status="completed"
   WHERE order_code=? AND status IN ("wallet_crediting","wallet_credited")
     AND provider_reference=?');
 $st->execute([$code,$ref]);
 if($st->rowCount()===1)return 'completed';
 $st=kp_db()->prepare('SELECT status FROM kp_bank_orders WHERE order_code=? LIMIT 1');
 $st->execute([$code]);
 $actual=$st->fetchColumn();
 return $actual===false?$status:(string)$actual;
}
/** Convert previously credited wallet orders without replaying any credit. */
function kp_bank_finalize_legacy_wallet_statuses(int $limit=50,?int $userId=null): int {
 $db=kp_db();
 $limit=max(1,min(150,$limit));
 if($userId===null){
  $st=$db->query("SELECT order_code,user_id,amount,status,provider_reference
                  FROM kp_bank_orders
                  WHERE status='wallet_credited' AND provider_reference LIKE 'VND_BONUS:%'
                  ORDER BY id DESC LIMIT ".$limit);
 }else{
  $st=$db->prepare("SELECT order_code,user_id,amount,status,provider_reference
                    FROM kp_bank_orders WHERE user_id=? AND status='wallet_credited'
                    AND provider_reference LIKE 'VND_BONUS:%'
                    ORDER BY id DESC LIMIT ".$limit);
  $st->execute([$userId]);
 }
 $count=0;
 foreach($st->fetchAll(PDO::FETCH_ASSOC) as $order){
  try{
   if(kp_bank_finalize_wallet_completed((string)$order['order_code'],$order)==='completed')$count++;
  }catch(Throwable $e){kp_log('wallet completed status backfill deferred',$e);}
 }
 return $count;
}
function kp_bank_reconcile_wallet($code,array $order){
 $db=kp_db();
 if(in_array($order['status'],['completed','wallet_credited'],true)){
  $status=$order['status']==='completed'?'completed':kp_bank_finalize_wallet_completed($code,$order);
  try{kp_points_award((int)$order['user_id'],'payos',(string)$code,(int)$order['amount'],$order['created_at']);}
  catch(Throwable $e){kp_log('wallet loyalty retry deferred',$e);}
  return $status;
 }
 if(in_array($order['status'],['failed','cancelled','expired'],true))return $order['status'];
 $remote=kp_bank_api('GET','/'.rawurlencode((string)$code));
 if((string)($remote['orderCode']??'')!==(string)$code || (int)($remote['amount']??-1)!==(int)$order['amount']
    || ($remote['status']??'')!=='PAID'||(int)($remote['amountPaid']??0)<(int)$order['amount'])return 'pending';
 $ref=substr((string)($remote['id']??''),0,100);
 $db->prepare('UPDATE kp_bank_orders SET status="wallet_crediting",paid_at=COALESCE(paid_at,NOW()) WHERE order_code=? AND status IN ("wallet_creating","wallet_pending","wallet_crediting")')
    ->execute([$code]);
 try{
  kp_vnd_credit((int)$order['user_id'],'payos',(string)$code,(int)$order['amount'],kp_bank_order_bonus($order));
  $order['status']='wallet_crediting';
  $status=kp_bank_finalize_wallet_completed($code,$order);
  if($status!=='completed')throw new RuntimeException('Ví đã cộng tiền nhưng chưa lưu trạng thái hoàn thành, cần đối soát.');
  try{kp_points_award((int)$order['user_id'],'payos',(string)$code,(int)$order['amount'],$order['created_at']);}
  catch(Throwable $e){kp_log('bank Point retry needed',$e);}
  return 'completed';
 }catch(Throwable $e){
  kp_log('verified payOS wallet credit retry needed '.$code,$e);
  return 'wallet_crediting';
 }
}
function kp_bank_reconcile($code){
 $db=kp_db();$cfg=kp_bank_cfg();
 $db->beginTransaction();
 try {
  $stmt=$db->prepare('SELECT * FROM kp_bank_orders WHERE order_code=? FOR UPDATE');$stmt->execute([$code]);$order=$stmt->fetch(PDO::FETCH_ASSOC);
  if(!$order){$db->rollBack();return 'unknown';}
  if(kp_bank_is_wallet_order($order)){
   $db->commit();
   return kp_bank_reconcile_wallet($code,$order);
  }
  if($order['status']==='completed'){
   $db->commit();
   try{kp_points_award((int)$order['user_id'],'payos',(string)$code,(int)$order['amount'],$order['created_at']);}
   catch(Throwable $e){kp_log('payOS Points award will need retry',$e);}
   return 'completed';
  }
  if(in_array($order['status'],['queued','crediting','needs_review'],true)){$db->commit();return $order['status'];}
  $response=kp_bank_api('GET','/'.rawurlencode((string)$code));
  if((string)($response['orderCode']??'')!==(string)$code || (int)($response['amount']??-1)!==(int)$order['amount'] || ($response['status']??'')!=='PAID'){
   $db->commit();return 'pending';
  }
  if((int)($response['amountPaid']??0)<(int)$order['amount']){$db->commit();return 'pending';}
  $ref=substr((string)($response['id']??''),0,100);
  // Existing queue-based orders are never replayed as direct credits.
  // All NEW orders bind a selected character at creation time.
  if((int)($order['char_id']??0)<1){
   $db->prepare('UPDATE kp_bank_orders SET status="needs_review",provider_reference=?,paid_at=COALESCE(paid_at,NOW()) WHERE order_code=?')->execute([$ref,$code]);
   $db->commit();return 'needs_review';
  }
  // This transition is DURABLE before touching the game's MyISAM character table.
  $db->prepare('UPDATE kp_bank_orders SET status="crediting",provider_reference=?,paid_at=COALESCE(paid_at,NOW()) WHERE order_code=?')->execute([$ref,$code]);
  $db->commit();
  try{
   kp_bank_direct_grant($order);
   $db->prepare('UPDATE kp_bank_orders SET status="completed" WHERE order_code=? AND status="crediting"')->execute([$code]);
   try{kp_points_award((int)$order['user_id'],'payos',(string)$code,(int)$order['amount'],$order['created_at']);}
   catch(Throwable $e){kp_log('payOS Points award will need retry',$e);}
   return 'completed';
  }catch(Throwable $e){
   kp_log('payOS direct credit requires manual reconciliation',$e);
   $db->prepare('UPDATE kp_bank_orders SET status="needs_review" WHERE order_code=? AND status="crediting"')->execute([$code]);
   return 'needs_review';
  }
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

/**
 * Safe cleanup. Never delete records until payOS explicitly confirms cancellation,
 * and never remove a paid / possibly-paid order.
 * Run from Windows Task Scheduler every minute, and opportunistically on bank page.
 */
function kp_bank_cleanup($limit=20) {
 $db=kp_db();
 $st=$db->prepare("SELECT order_code FROM kp_bank_orders WHERE status IN ('creating','pending','wallet_creating','wallet_pending') AND created_at < (NOW() - INTERVAL 10 MINUTE) ORDER BY id ASC LIMIT ".max(1,min(100,(int)$limit)));
 $st->execute();
 $deleted=0;
 foreach($st->fetchAll(PDO::FETCH_COLUMN) as $code) {
  $code=(string)$code;
  try {
   $state=kp_bank_reconcile($code);
   if($state!=='pending')continue;
   $remote=kp_bank_api('GET','/'.rawurlencode($code));
   $status=strtoupper((string)($remote['status']??''));
   if($status==='PENDING'){
    $remote=kp_bank_api('POST','/'.rawurlencode($code).'/cancel',['cancellationReason'=>'Expired after 10 minutes']);
    $status=strtoupper((string)($remote['status']??''));
   }
   if(!in_array($status,['CANCELLED','EXPIRED'],true))continue;
   if((int)($remote['amountPaid']??0)!==0)continue;
   $db->beginTransaction();
   $lock=$db->prepare('SELECT status FROM kp_bank_orders WHERE order_code=? FOR UPDATE');
   $lock->execute([$code]);
   $local=$lock->fetchColumn();
   if(in_array($local,['creating','pending','wallet_creating','wallet_pending'],true)){
    $db->prepare('DELETE FROM kp_bank_webhook_events WHERE order_code=?')->execute([$code]);
    $db->prepare('DELETE FROM kp_bank_orders WHERE order_code=?')->execute([$code]);
    $deleted++;
   }
   $db->commit();
  }catch(Throwable $e){
   if($db->inTransaction())$db->rollBack();
   kp_log('payOS cleanup deferred pending confirmation',$e);
  }
 }
 return $deleted;
}
