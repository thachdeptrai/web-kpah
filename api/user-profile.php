<?php
require __DIR__ . '/../includes/bootstrap.php';
kp_send_security_headers(false);
header('Content-Type: application/json; charset=utf-8');
function kp_profile_reply(array $payload, $status = 200) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); kp_profile_reply(['ok'=>false,'message'=>'Phương thức không hợp lệ.'],405); }
if (empty($_SESSION['kp_user_id'])) { kp_profile_reply(['ok'=>false,'message'=>'Phiên đăng nhập đã hết hạn. Hãy đăng nhập lại.'],401); }
if (!kp_csrf_check($_POST['csrf'] ?? '')) { kp_profile_reply(['ok'=>false,'message'=>'Phiên bảo mật hết hạn. Tải lại trang rồi thử lại.'],403); }
$action = (string)($_POST['action'] ?? 'update');
$userId = (int)$_SESSION['kp_user_id'];
if ($action === 'send_email_code') {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    if (!$email || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) kp_profile_reply(['ok'=>false,'message'=>'Hãy nhập địa chỉ email hợp lệ.'],422);
    try {
        $db = kp_db();
        $st = $db->prepare('SELECT email FROM team_user WHERE id = ? LIMIT 1');
        $st->execute([$userId]);
        $current = $st->fetch();
        if (!$current) kp_profile_reply(['ok'=>false,'message'=>'Không tìm thấy tài khoản.'],404);
        if (strtolower(trim((string)$current['email'])) === $email) kp_profile_reply(['ok'=>false,'message'=>'Email này đang được liên kết rồi.'],422);
        $dupe = $db->prepare('SELECT 1 FROM team_user WHERE LOWER(TRIM(email)) = ? AND id <> ? LIMIT 1');
        $dupe->execute([$email, $userId]);
        if ($dupe->fetchColumn()) kp_profile_reply(['ok'=>false,'message'=>'Email này đã được tài khoản khác sử dụng.'],409);
        list($allowed, $retry) = kp_rate_limit('profile_email_code', $userId.'|'.kp_client_ip(), 5, 600, true);
        if (!$allowed) kp_profile_reply(['ok'=>false,'message'=>'Bạn yêu cầu mã quá nhiều lần. Hãy thử lại sau ít phút.'],429);
        $code = (string)random_int(100000, 999999);
        $ttl = max(60, (int)kp_config('registration.email_code_ttl', 600));
        kp_send_mail_smtp($email, 'Mã xác minh thay đổi email KPAH', "Mã xác minh email mới của bạn là: ".$code."\nMã có hiệu lực trong ".(int)ceil($ttl/60)." phút.\nNếu bạn không yêu cầu thay đổi email, hãy bỏ qua thư này.");
        $_SESSION['kp_profile_email_hash'] = hash('sha256', $code);
        $_SESSION['kp_profile_email_expiry'] = time() + $ttl;
        $_SESSION['kp_profile_email_target'] = $email;
        $_SESSION['kp_profile_email_user'] = $userId;
        kp_profile_reply(['ok'=>true,'message'=>'Đã gửi mã. Kiểm tra hộp thư đến và Spam/Thư rác.']);
    } catch (Throwable $e) {
        kp_log('gửi OTP cập nhật email thất bại', $e);
        kp_profile_reply(['ok'=>false,'message'=>'Không gửi được mã email. Kiểm tra cấu hình SMTP trên máy chủ.'],503);
    }
}
if ($action !== 'update') kp_profile_reply(['ok'=>false,'message'=>'Yêu cầu không hợp lệ.'],400);
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$phone = trim((string)($_POST['phone'] ?? ''));
$code = trim((string)($_POST['email_code'] ?? ''));
if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254)) kp_profile_reply(['ok'=>false,'message'=>'Địa chỉ email không hợp lệ.'],422);
if ($phone !== '' && !preg_match('/^(?:\+84|0)(?:3|5|7|8|9)\d{8}$/', $phone)) kp_profile_reply(['ok'=>false,'message'=>'Số điện thoại chưa đúng định dạng. Dùng 0xxxxxxxxx hoặc +84xxxxxxxxx.'],422);
try {
    $db = kp_db();
    $st = $db->prepare('SELECT email FROM team_user WHERE id = ? LIMIT 1');
    $st->execute([$userId]);
    $current = $st->fetch();
    if (!$current) kp_profile_reply(['ok'=>false,'message'=>'Không tìm thấy tài khoản.'],404);
    $oldEmail = strtolower(trim((string)$current['email']));
    if ($email !== $oldEmail) {
        $validCode = isset($_SESSION['kp_profile_email_hash'], $_SESSION['kp_profile_email_expiry'], $_SESSION['kp_profile_email_target'], $_SESSION['kp_profile_email_user'])
            && (int)$_SESSION['kp_profile_email_user'] === $userId
            && time() <= (int)$_SESSION['kp_profile_email_expiry']
            && hash_equals((string)$_SESSION['kp_profile_email_target'], $email)
            && preg_match('/^\d{6}$/', $code)
            && hash_equals((string)$_SESSION['kp_profile_email_hash'], hash('sha256', $code));
        if (!$validCode) kp_profile_reply(['ok'=>false,'message'=>'Email mới chưa được xác minh hoặc mã đã hết hạn. Hãy gửi mã mới.'],422);
        $dupe = $db->prepare('SELECT 1 FROM team_user WHERE LOWER(TRIM(email)) = ? AND id <> ? LIMIT 1');
        $dupe->execute([$email, $userId]);
        if ($dupe->fetchColumn()) kp_profile_reply(['ok'=>false,'message'=>'Email này đã được tài khoản khác sử dụng.'],409);
    }
    $up = $db->prepare('UPDATE team_user SET email = ?, phone = ? WHERE id = ?');
    $up->execute([$email, $phone, $userId]);
    if ($email !== $oldEmail) {
        unset($_SESSION['kp_profile_email_hash'], $_SESSION['kp_profile_email_expiry'], $_SESSION['kp_profile_email_target'], $_SESSION['kp_profile_email_user']);
    }
    kp_profile_reply(['ok'=>true,'message'=>'Đã lưu thông tin tài khoản.','email'=>$email,'phone'=>$phone]);
} catch (Throwable $e) {
    kp_log('cập nhật hồ sơ user thất bại', $e);
    kp_profile_reply(['ok'=>false,'message'=>'Không thể lưu thông tin lúc này. Hãy thử lại sau.'],503);
}
