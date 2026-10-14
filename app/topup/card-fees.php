<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/topup_core.php';
kp_send_security_headers(false);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
if (($_SERVER['REQUEST_METHOD']??'')!=='GET') {http_response_code(405);echo json_encode(['ok'=>false]);exit;}
if (empty($_SESSION['kp_user_id'])) {http_response_code(401);echo json_encode(['ok'=>false]);exit;}
$telco=strtoupper(trim((string)($_GET['telco']??'')));
$amount=filter_var($_GET['amount']??null,FILTER_VALIDATE_INT);
if (!in_array($telco,['VIETTEL','VINAPHONE','MOBIFONE','VNMOBI','GATE','ZING'],true) || $amount===false || $amount<1000 || $amount>1000000) {http_response_code(422);echo json_encode(['ok'=>false]);exit;}
try {
    list($allowed,$retry)=kp_rate_limit('topup_quote', (string)$_SESSION['kp_user_id'].'|'.kp_client_ip(), 30, 60, true);
    if (!$allowed) {http_response_code(429);echo json_encode(['ok'=>false,'message'=>'Yêu cầu quá nhiều.']);exit;}
    $quote=kp_topup_provider_fee($telco,$amount);
    echo json_encode(['ok'=>true,'net'=>$quote['net'],'fee'=>$quote['fee']],JSON_UNESCAPED_UNICODE);
} catch(Throwable $e) {
    kp_log('topup fee quote failed',$e);
    http_response_code(503);echo json_encode(['ok'=>false,'message'=>'Chưa lấy được bảng chiết khấu.'],JSON_UNESCAPED_UNICODE);
}
