<?php
require __DIR__ . '/../includes/bootstrap.php';
kp_send_security_headers(false);
header('Content-Type: application/json; charset=utf-8');
function kp_recovery_reply(array $payload, $status = 200) {
    http_response_code($status);
    // Match registration: every response issues the next one-use form token.
    $payload['csrf'] = kp_csrf_rotate();
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
function kp_recovery_limited($retry) {
    $retry = max(1, (int)$retry);
    header('Retry-After: '.$retry);
    kp_recovery_reply([
        'ok' => false,
        'code' => 'rate_limit',
        'retry_after' => $retry,
        'message' => 'Bạn yêu cầu mã quá nhiều lần. Vui lòng chờ '.(int)ceil($retry/60).' phút rồi thử lại.'
    ], 429);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); kp_recovery_reply(['ok'=>false,'message'=>'Phương thức không hợp lệ.'],405); }
if (!kp_csrf_check($_POST['csrf'] ?? '')) kp_recovery_reply(['ok'=>false,'message'=>'Phiên bảo mật hết hạn. Hãy tải lại trang.'],403);
$action = (string)($_POST['action'] ?? '');
if ($action === 'send_code') {
    $identity = isset($_POST['identity']) && is_string($_POST['identity']) ? trim($_POST['identity']) : '';
    if ($identity === '' || strlen($identity) > 254) kp_recovery_reply(['ok'=>false,'message'=>'Nhập tên tài khoản hoặc email hợp lệ.'],422);
    $ip = kp_client_ip();
    $normalizedIdentity = strtolower($identity);
    // A global IP bucket prevents bypass by rotating usernames/email addresses.
    // Consume BEFORE querying the DB or calling SMTP, with atomic file locks.
    list($allowed, $retry) = kp_rate_limit('password_recovery_ip', $ip, 5, 600, true);
    if (!$allowed) kp_recovery_limited($retry);
    list($allowed, $retry) = kp_rate_limit('password_recovery_ip_hour', $ip, 15, 3600, true);
    if (!$allowed) kp_recovery_limited($retry);
    // Shared across sessions and source IPs for the same submitted identity.
    // Applied to unknown identities too, so the response cannot reveal existence.
    list($allowed, $retry) = kp_rate_limit('password_recovery_identity_minute', $normalizedIdentity, 1, 60, true);
    if (!$allowed) kp_recovery_limited($retry);
    list($allowed, $retry) = kp_rate_limit('password_recovery_identity_window', $normalizedIdentity, 3, 900, true);
    if (!$allowed) kp_recovery_limited($retry);
    try {
        $st = kp_db()->prepare('SELECT id, email FROM team_user WHERE (username = ? OR LOWER(TRIM(email)) = LOWER(TRIM(?))) AND ban = 0 LIMIT 1');
        $st->execute([$identity, $identity]);
        $user = $st->fetch();
        if ($user && filter_var((string)$user['email'], FILTER_VALIDATE_EMAIL)) {
            // Both "username" and "email" resolve to the SAME recipient bucket.
            // The bucket is global across IPs/sessions and cannot be bypassed
            // using alternative identifiers for the same account.
            $recipient = strtolower(trim((string)$user['email']));
            $recipientKey = hash('sha256', $recipient);
            list($recipientAllowed) = kp_rate_limit('password_recovery_recipient_minute', $recipientKey, 1, 60, true);
            if ($recipientAllowed) {
                list($recipientAllowed) = kp_rate_limit('password_recovery_recipient_window', $recipientKey, 3, 900, true);
            }
            if (!$recipientAllowed) {
                // Match the generic success response for unknown addresses to
                // avoid leaking which usernames have a recovery mailbox.
                kp_recovery_reply([
                    'ok' => true,
                    'retry_after' => 60,
                    'message' => 'Nếu thông tin khớp tài khoản có email hợp lệ, mã sẽ được gửi tới email đã liên kết. Hãy kiểm tra cả Spam/Thư rác.'
                ]);
            }
            $code = (string)random_int(100000, 999999);
            $ttl = max(60, (int)kp_config('registration.email_code_ttl', 600));
            kp_send_mail_smtp((string)$user['email'], 'Mã khôi phục mật khẩu KPAH', "Mã khôi phục mật khẩu của bạn là: ".$code."\nMã có hiệu lực trong ".(int)ceil($ttl/60)." phút.\nNếu bạn không yêu cầu đặt lại mật khẩu, hãy bỏ qua thư này.");
            $_SESSION['kp_recovery_hash'] = hash('sha256', $code);
            $_SESSION['kp_recovery_expiry'] = time() + $ttl;
            $_SESSION['kp_recovery_user'] = (int)$user['id'];
        } else {
            unset($_SESSION['kp_recovery_hash'], $_SESSION['kp_recovery_expiry'], $_SESSION['kp_recovery_user']);
        }
        // Thông báo chung để không tiết lộ tài khoản/email có tồn tại hay không.
        kp_recovery_reply(['ok'=>true,'retry_after'=>60,'message'=>'Nếu thông tin khớp tài khoản có email hợp lệ, mã sẽ được gửi tới email đã liên kết. Hãy kiểm tra cả Spam/Thư rác.']);
    } catch (Throwable $e) {
        kp_log('gửi mã khôi phục mật khẩu thất bại', $e);
        kp_recovery_reply(['ok'=>false,'message'=>'Chưa gửi được mã. Hãy kiểm tra cấu hình SMTP của máy chủ.'],503);
    }
}
if ($action !== 'reset') kp_recovery_reply(['ok'=>false,'message'=>'Yêu cầu không hợp lệ.'],400);
$code = trim((string)($_POST['code'] ?? ''));
list($resetAllowed, $resetRetry) = kp_rate_limit('password_reset_verify', kp_client_ip(), 10, 600, true);
if (!$resetAllowed) kp_recovery_reply(['ok'=>false,'message'=>'Bạn xác minh quá nhiều lần. Hãy thử lại sau ít phút.'],429);
$password = (string)($_POST['new_password'] ?? '');
$confirm = (string)($_POST['confirm_password'] ?? '');
if (strlen($password) < 6 || strlen($password) > 32 || preg_match('/\s/', $password)) kp_recovery_reply(['ok'=>false,'message'=>'Mật khẩu mới phải từ 6–32 ký tự và không chứa khoảng trắng.'],422);
if (!hash_equals($password, $confirm)) kp_recovery_reply(['ok'=>false,'message'=>'Hai mật khẩu không trùng nhau.'],422);
$valid = isset($_SESSION['kp_recovery_hash'], $_SESSION['kp_recovery_expiry'], $_SESSION['kp_recovery_user'])
    && time() <= (int)$_SESSION['kp_recovery_expiry']
    && preg_match('/^\d{6}$/', $code)
    && hash_equals((string)$_SESSION['kp_recovery_hash'], hash('sha256', $code));
if (!$valid) kp_recovery_reply(['ok'=>false,'message'=>'Mã không đúng hoặc đã hết hạn. Hãy yêu cầu mã mới.'],422);
try {
    // Giữ định dạng hash của tài khoản để không phá đăng nhập game:
    // tài khoản bcrypt vẫn dùng bcrypt; tài khoản legacy vẫn dùng hash game.
    $st = kp_db()->prepare('SELECT password FROM team_user WHERE id = ? AND ban = 0 LIMIT 1');
    $st->execute([(int) $_SESSION['kp_recovery_user']]);
    $currentHash = (string) $st->fetchColumn();
    if (preg_match('/^\$2[aby]\$\d{2}\$/', $currentHash) === 1 || strpos($currentHash, '$argon2') === 0) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
    } else {
        $newHash = kp_game_password_hash($password);
    }
    $update = kp_db()->prepare('UPDATE team_user SET password = ? WHERE id = ? AND password = ? AND ban = 0');
    $update->execute([$newHash, (int) $_SESSION['kp_recovery_user'], $currentHash]);
    if ($update->rowCount() !== 1) {
        kp_recovery_reply(['ok'=>false,'message'=>'Tài khoản đã thay đổi hoặc bị khóa. Hãy yêu cầu mã khôi phục mới.'],409);
    }
    unset($_SESSION['kp_recovery_hash'], $_SESSION['kp_recovery_expiry'], $_SESSION['kp_recovery_user']);
    kp_recovery_reply(['ok'=>true,'message'=>'Đổi mật khẩu thành công. Bạn có thể đăng nhập bằng mật khẩu mới.','redirect'=>'/dang-nhap']);
} catch (Throwable $e) {
    kp_log('đặt lại mật khẩu thất bại', $e);
    kp_recovery_reply(['ok'=>false,'message'=>'Chưa thể đổi mật khẩu lúc này. Hãy thử lại sau.'],503);
}
