<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/bank_payos.php';
kp_send_security_headers(false);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){http_response_code(405);echo '{"ok":false}';exit;}
try { $userId=kp_player_api_user_id(); }
catch(Throwable $e){kp_log('payOS status authentication unavailable',$e);http_response_code(503);echo '{"ok":false}';exit;}
if($userId<1){http_response_code(401);echo '{"ok":false}';exit;}
$code=filter_var($_GET['code']??null,FILTER_VALIDATE_INT);
if($code===false||$code===null||$code<=0){http_response_code(422);echo '{"ok":false}';exit;}
try{
 $st=kp_db()->prepare('SELECT status,provider_reference FROM kp_bank_orders WHERE order_code=? AND user_id=? LIMIT 1');
 $st->execute([$code,$userId]);$order=$st->fetch(PDO::FETCH_ASSOC);
 if(!$order){http_response_code(404);echo '{"ok":false}';exit;}
 $status=(string)$order['status'];
 // Poll reads our trusted database only; payOS webhook/CLI reconciliation remains authoritative.
 $paid=in_array($status,['paid_waiting_delivery','crediting','queued','needs_review','completed','wallet_crediting','wallet_credited'],true);
 $labels=['creating'=>'Đang tạo QR','pending'=>'Đang chờ chuyển khoản','wallet_creating'=>'Đang tạo QR nạp VNĐ','wallet_pending'=>'Chờ chuyển khoản vào ví VNĐ','paid_waiting_delivery'=>'Đã nhận tiền, đang chờ cấp Lượng','crediting'=>'Đang xác minh cấp Lượng','queued'=>'Đã đưa vào hàng chờ game','completed'=>kp_bank_is_wallet_order($order)?'Hoàn thành · Đã cộng VNĐ vào ví':'Hoàn thành · Đã cấp Lượng','needs_review'=>'Đã ghi nhận thanh toán, cần đối soát','wallet_crediting'=>'Đã trả tiền, đang cộng ví VNĐ','wallet_credited'=>'Đã cộng VNĐ vào ví'];
 echo json_encode(['ok'=>true,'status'=>$status,'paid'=>$paid,'label'=>$labels[$status]??'Đang kiểm tra'],JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){kp_log('payOS status polling failed',$e);http_response_code(503);echo '{"ok":false}';}
