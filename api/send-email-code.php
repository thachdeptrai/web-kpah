<?php
require __DIR__ . '/../includes/bootstrap.php';
kp_send_security_headers(false);
header('Content-Type: application/json; charset=utf-8');
function kp_email_code_reply(array $payload, $status = 200) {
    http_response_code($status);
    $payload['csrf'] = kp_csrf_rotate();
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); kp_email_code_reply(['ok'=>false,'message'=>'Phương thức không hợp lệ.'],405); }
if (!kp_csrf_check(isset($_POST['csrf']) ? $_POST['csrf'] : '')) { kp_email_code_reply(['ok'=>false,'code'=>'csrf','message'=>'Phiên đã hết hạn, hãy tải lại trang.'],403); }
$email = isset($_POST['email']) && is_string($_POST['email']) ? strtolower(trim($_POST['email'])) : '';
if (!$email || strlen($email)>254 || !filter_var($email,FILTER_VALIDATE_EMAIL)) { kp_email_code_reply(['ok'=>false,'message'=>'Vui lòng nhập email hợp lệ.','errors'=>['email'=>'Địa chỉ email chưa đúng định dạng.']],422); }
// Mỗi email chỉ được đăng ký một tài khoản; không gửi OTP cho email đã có trong game.
try {
    $st = kp_db()->prepare('SELECT 1 FROM `team_user` WHERE LOWER(TRIM(`email`)) = LOWER(TRIM(?)) LIMIT 1');
    $st->execute([$email]);
    if ($st->fetchColumn()) {
        kp_email_code_reply(['ok'=>false,'code'=>'email_exists','message'=>'Email này đã được liên kết với một tài khoản khác.','errors'=>['email'=>'Mỗi email chỉ được dùng cho một tài khoản.']],409);
    }
} catch (Throwable $e) {
    kp_log('kiểm tra email trước khi gửi OTP thất bại', $e);
    kp_email_code_reply(['ok'=>false,'message'=>'Chưa thể kiểm tra email lúc này. Vui lòng thử lại sau.'],503);
}
$ip = kp_client_ip();
list($allowed,$retry) = kp_rate_limit('reg_email_code',$ip,5,600,true);
if (!$allowed) { header('Retry-After: '.$retry); kp_email_code_reply(['ok'=>false,'message'=>'Bạn yêu cầu mã quá nhiều lần. Hãy thử lại sau.'],429); }
$code = (string) random_int(100000,999999);
$ttl = max(60,(int)kp_config('registration.email_code_ttl',600));
try {
    kp_send_mail_smtp($email,'Mã xác minh email KPAH',"Mã xác minh email của bạn là: ".$code."\nMã có hiệu lực trong ".(int)ceil($ttl/60)." phút.\nNếu bạn không yêu cầu mã này, hãy bỏ qua email.");
    $_SESSION['kp_email_code_hash'] = hash('sha256',$code);
    $_SESSION['kp_email_code_expiry'] = time()+$ttl;
    $_SESSION['kp_email_code_email'] = $email;
    unset($_SESSION['kp_verified_email']);
    kp_email_code_reply(['ok'=>true,'message'=>'Đã gửi mã xác minh. Kiểm tra cả thư mục Spam/Thư rác.']);
} catch (Throwable $e) {
    kp_log('gửi mã xác minh email thất bại',$e);
    kp_email_code_reply(['ok'=>false,'message'=>'Chưa gửi được email. Máy chủ cần cấu hình SMTP trong config.register.local.php.'],503);
}
