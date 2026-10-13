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
$minutes = (int)ceil($ttl / 60);
$communityUrl = 'https://zalo.me/g/zmyedl5en7sldmd9el1z';
try {
    $plain = "Xin chào,\n\nBạn vừa yêu cầu xác minh email cho tài khoản KPAH.\n\nMã xác minh của bạn: ".$code."\nMã có hiệu lực trong ".$minutes." phút. Không chia sẻ mã này với bất kỳ ai.\n\nCộng đồng KPAH trên Zalo: ".$communityUrl."\n\nNếu bạn không yêu cầu mã này, hãy bỏ qua email.\nKPAH - Email tự động, vui lòng không trả lời.";
    $html = '<!doctype html><html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Xác minh email KPAH</title></head>'
        .'<body style="margin:0;padding:0;background:#f6f8fa;font-family:Arial,Helvetica,sans-serif;color:#24292f;">'
        .'<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f8fa;padding:28px 12px;"><tr><td align="center">'
        .'<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border:1px solid #d8dee4;border-radius:12px;overflow:hidden;">'
        .'<tr><td style="padding:22px 28px;border-bottom:1px solid #d8dee4;"><div style="font-size:22px;font-weight:700;letter-spacing:.5px;color:#24292f;">KPAH</div><div style="font-size:12px;color:#656d76;margin-top:5px;">XÁC MINH TÀI KHOẢN</div></td></tr>'
        .'<tr><td style="padding:28px;">'
        .'<h1 style="font-size:22px;line-height:1.35;margin:0 0 18px;color:#24292f;">Xác minh địa chỉ email</h1>'
        .'<p style="font-size:15px;line-height:1.65;margin:0 0 12px;">Xin chào,</p>'
        .'<p style="font-size:15px;line-height:1.65;margin:0 0 22px;">Bạn vừa yêu cầu xác minh email cho tài khoản KPAH. Nhập mã bên dưới để tiếp tục.</p>'
        .'<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f8fa;border:1px solid #d8dee4;border-radius:10px;"><tr><td align="center" style="padding:22px 12px;">'
        .'<div style="font-size:11px;font-weight:700;letter-spacing:1.2px;color:#656d76;">MÃ XÁC MINH CỦA BẠN</div>'
        .'<div style="font-size:34px;line-height:1.5;font-weight:700;letter-spacing:8px;color:#0969da;padding-left:8px;">'.htmlspecialchars($code, ENT_QUOTES, 'UTF-8').'</div>'
        .'<div style="font-size:12px;color:#656d76;">Mã có hiệu lực trong '.htmlspecialchars((string)$minutes, ENT_QUOTES, 'UTF-8').' phút</div>'
        .'</td></tr></table>'
        .'<p style="font-size:13px;line-height:1.65;color:#57606a;margin:20px 0 0;">Vì sự an toàn của bạn, không chia sẻ mã này với bất kỳ ai. Nếu bạn không yêu cầu xác minh, hãy bỏ qua email.</p>'
        .'<div style="height:1px;background:#d8dee4;margin:24px 0;"></div>'
        .'<p style="font-size:14px;line-height:1.6;margin:0 0 10px;font-weight:700;">Cần hỗ trợ?</p>'
        .'<p style="font-size:14px;line-height:1.6;margin:0 0 18px;color:#57606a;">Tham gia cộng đồng KPAH trên Zalo để nhận hỗ trợ và cập nhật.</p>'
        .'<table role="presentation" cellspacing="0" cellpadding="0"><tr><td bgcolor="#0969da" style="border-radius:6px;"><a href="'.$communityUrl.'" style="display:inline-block;padding:11px 16px;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;border-radius:6px;">Tham gia cộng đồng Zalo</a></td></tr></table>'
        .'</td></tr><tr><td style="padding:18px 28px;background:#f6f8fa;border-top:1px solid #d8dee4;font-size:12px;line-height:1.6;color:#656d76;">KPAH · Email tự động, vui lòng không trả lời.<br>Nếu nút không mở được, sao chép liên kết: <a href="'.$communityUrl.'" style="color:#0969da;word-break:break-all;">'.$communityUrl.'</a></td></tr>'
        .'</table></td></tr></table></body></html>';
    kp_send_mail_smtp($email, 'Mã xác minh email KPAH', $plain, $html);
    $_SESSION['kp_email_code_hash'] = hash('sha256',$code);
    $_SESSION['kp_email_code_expiry'] = time()+$ttl;
    $_SESSION['kp_email_code_email'] = $email;
    unset($_SESSION['kp_verified_email']);
    kp_email_code_reply(['ok'=>true,'message'=>'Đã gửi mã xác minh. Kiểm tra cả thư mục Spam/Thư rác.']);
} catch (Throwable $e) {
    kp_log('gửi mã xác minh email thất bại',$e);
    kp_email_code_reply(['ok'=>false,'message'=>'Chưa gửi được mã xác minh. Bạn thử lại sau hoặc liên hệ hỗ trợ nhé.'],503);
}
