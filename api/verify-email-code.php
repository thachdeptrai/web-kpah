<?php
require __DIR__ . '/../includes/bootstrap.php';
kp_send_security_headers(false);
header('Content-Type: application/json; charset=utf-8');
function kp_email_verify_reply(array $payload, $status = 200) {
    http_response_code($status);
    $payload['csrf'] = kp_csrf_rotate();
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); kp_email_verify_reply(['ok'=>false,'message'=>'Phương thức không hợp lệ.'],405); }
if (!kp_csrf_check(isset($_POST['csrf']) ? $_POST['csrf'] : '')) { kp_email_verify_reply(['ok'=>false,'code'=>'csrf','message'=>'Phiên đã hết hạn, hãy tải lại trang.'],403); }
$email = isset($_POST['email']) && is_string($_POST['email']) ? strtolower(trim($_POST['email'])) : '';
$code = isset($_POST['email_code']) && is_string($_POST['email_code']) ? trim($_POST['email_code']) : '';
$ip = kp_client_ip();
list($attemptAllowed, $attemptRetry) = kp_rate_limit('verify_email_code', $ip, 10, 600, true);
if (!$attemptAllowed) {
    header('Retry-After: ' . $attemptRetry);
    kp_email_verify_reply(['ok'=>false,'code'=>'rate_limit','message'=>'Bạn nhập mã xác minh quá nhiều lần. Vui lòng thử lại sau.'],429);
}
$valid = isset($_SESSION['kp_email_code_hash'],$_SESSION['kp_email_code_expiry'],$_SESSION['kp_email_code_email'])
    && time() <= (int)$_SESSION['kp_email_code_expiry']
    && hash_equals((string)$_SESSION['kp_email_code_email'],$email)
    && preg_match('/^\d{6}$/',$code)
    && hash_equals((string)$_SESSION['kp_email_code_hash'],hash('sha256',$code));
if (!$valid) { kp_email_verify_reply(['ok'=>false,'message'=>'Mã không đúng, hết hạn hoặc không khớp email. Hãy yêu cầu mã mới.','errors'=>['email_code'=>'Mã xác minh không hợp lệ.']],422); }
$_SESSION['kp_verified_email'] = $email;
unset($_SESSION['kp_email_code_hash'],$_SESSION['kp_email_code_expiry'],$_SESSION['kp_email_code_email']);
kp_email_verify_reply(['ok'=>true,'message'=>'Email đã được xác minh. Bạn có thể tạo tài khoản.']);
