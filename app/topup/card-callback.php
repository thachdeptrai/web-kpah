<?php
// Public callback: NO session/CSRF requirement, authentication is the provider signature.
// Never log or echo raw card serial/PIN.
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/topup_core.php';
kp_send_security_headers(false);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
if (!in_array(($_SERVER['REQUEST_METHOD'] ?? ''),['POST','GET'],true) || (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192) {
    http_response_code(405); exit('INVALID');
}
$cfg=kp_topup_cfg();
if (!$cfg['card_ready']) { http_response_code(503); exit('UNAVAILABLE'); }
$payload = ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' ? $_GET : $_POST;
$id = (string)($payload['request_id'] ?? '');
$code = strtoupper(trim((string)($payload['code'] ?? '')));
$serial = strtoupper(trim((string)($payload['serial'] ?? '')));
$signature = strtolower((string)($payload['callback_sign'] ?? ''));
$telco = strtoupper(trim((string)($payload['telco'] ?? '')));
if (!preg_match('/^[a-f0-9]{32}$/D',$id) || !preg_match('/^[A-Z0-9]{6,32}$/D',$code)
    || !preg_match('/^[A-Z0-9]{6,32}$/D',$serial)
    || !preg_match('/^[a-f0-9]{32}$/D',$signature)) {
    http_response_code(400); exit('INVALID');
}
$expected=md5($cfg['partner_key'].$code.$serial);
if (!hash_equals($expected,$signature)) { http_response_code(403); exit('INVALID'); }
try {
    $order=kp_topup_read_file(kp_topup_path('orders',$id));
    if (!$order || !hash_equals((string)($order['fingerprint']??''),kp_topup_hmac($code,$serial,$cfg['secret']))
        || (string)$order['telco']!==$telco) {
        http_response_code(403); exit('INVALID');
    }
    $result=kp_topup_reconcile($id);
    // Only the HTTPS provider status re-check (not this callback's status/value)
    // authorizes a successful recharge.
    http_response_code(200);
    echo 'OK';
} catch (Throwable $e) {
    kp_log('card callback awaiting reconciliation',$e);
    http_response_code(503);
    echo 'RETRY';
}
