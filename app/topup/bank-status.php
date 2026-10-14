<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/bank_payos.php';
kp_send_security_headers(false);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){http_response_code(405);echo '{"ok":false}';exit;}
$userId=(int)($_SESSION['kp_user_id']??0);$code=filter_var($_GET['code']??null,FILTER_VALIDATE_INT);
if($userId<1||$code===false||$code<=0){http_response_code(403);echo '{"ok":false}';exit;}
try{
 $st=kp_db()->prepare('SELECT status FROM kp_bank_orders WHERE order_code=? AND user_id=? LIMIT 1');
 $st->execute([$code,$userId]);$status=$st->fetchColumn();
 if($status===false){http_response_code(404);echo '{"ok":false}';exit;}
 // Poll reads our trusted database only; payOS webhook/CLI reconciliation remains authoritative.
 $paid=in_array($status,['paid_waiting_delivery','crediting','queued','needs_review','completed','wallet_crediting','wallet_credited'],true);
 $labels=['creating'=>'Đang tạo QR','pending'=>'Đang chờ chuyển khoản','paid_waiting_delivery'=>'Đã nhận tiền, đang chờ cấp Lượng','crediting'=>'Đang xác minh cấp Lượng','queued'=>'Đã đưa vào hàng chờ game','completed'=>'Đã cộng vào nhân vật','needs_review'=>'Đã ghi nhận thanh toán, cần đối soát','wallet_crediting'=>'Đã trả tiền, đang cộng ví VNĐ','wallet_credited'=>'Đã cộng VNĐ vào ví'];
 echo json_encode(['ok'=>true,'status'=>$status,'paid'=>$paid,'label'=>$labels[$status]??'Đang kiểm tra'],JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){kp_log('payOS status polling failed',$e);http_response_code(503);echo '{"ok":false}';}
