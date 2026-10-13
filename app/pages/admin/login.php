<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/admin_security.php';
kp_send_security_headers(true);
header('Content-Type: text/html; charset=utf-8');

if (!kp_admin_allowed_ip()) {
    http_response_code(403);
    exit('Truy cập quản trị bị giới hạn.');
}
if (!empty($_SESSION['kp_admin_id']) && ($_SESSION['kp_admin_role'] ?? '') === 'admin') {
    kp_admin_require();
    header('Location: index.php');
    exit;
}
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!kp_admin_csrf_valid($_POST['csrf'] ?? '')) {
        // Never bypass CSRF: record only the failure category, not any token.
        $token = $_POST['csrf'] ?? '';
        $reason = !kp_csrf_check($token)
            ? (empty($_COOKIE[session_name()]) ? 'login_csrf_cookie_missing' : 'login_csrf_token_stale')
            : 'login_csrf_origin_rejected';
        kp_admin_log_event($reason);
        http_response_code(403);
        $error = 'Phiên đăng nhập đã thay đổi. Mã xác minh mới đã được tạo, vui lòng nhập lại mật khẩu và thử lại.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $otp = trim((string)($_POST['otp'] ?? ''));
        if ($username === '' || strlen($username) > 64 || $password === '' || strlen($password) > 256) {
            $error = 'Tên đăng nhập hoặc thông tin xác minh không đúng.';
        } else {
            // Two independent budgets: per-source and per-target admin account.
            // Both deny access if the storage/lock layer is unhealthy.
            list($ipAllowed, $ipRetry) = kp_admin_limit('login-ip', kp_client_ip(), 25, 900);
            list($userAllowed, $userRetry) = kp_admin_limit('login-user', strtolower($username), 8, 900);
            if (!$ipAllowed || !$userAllowed) {
                header('Retry-After: '.max(1, $ipRetry, $userRetry));
                http_response_code(429);
                $error = 'Tạm khóa đăng nhập để bảo vệ quản trị. Thử lại sau ít phút.';
            } else {
                try {
                    $st = kp_db()->prepare(
                        "SELECT id, username, password_hash, role, is_active
                         FROM kpah_web_admins WHERE username = ? LIMIT 1"
                    );
                    $st->execute([$username]);
                    $admin = $st->fetch(PDO::FETCH_ASSOC);
                    $valid = $admin && (int)$admin['is_active'] === 1
                        && $admin['role'] === 'admin'
                        && password_verify($password, (string)$admin['password_hash']);
                    $totpOk = !kp_admin_totp_enabled();
                    if ($valid && kp_admin_totp_enabled()) {
                        $secret = kp_admin_totp_secret((string)$admin['username']);
                        $step = kp_admin_totp_step($secret, $otp);
                        $totpOk = $step !== false
                            && kp_admin_totp_consume((int)$admin['id'], (int)$step);
                    }
                    if ($valid && $totpOk) {
                        kp_admin_create_session($admin, $totpOk);
                        header('Location: index.php');
                        exit;
                    }
                    kp_admin_log_event('login_failed', $admin ? (int)$admin['id'] : 0);
                    $error = 'Tên đăng nhập hoặc thông tin xác minh không đúng.';
                } catch (Throwable $e) {
                    kp_log('admin sign-in error', $e);
                    http_response_code(503);
                    $error = 'Chưa thể đăng nhập quản trị. Kiểm tra kết nối và nhật ký hệ thống.';
                }
            }
        }
    }
}
$csrf = kp_csrf_token();
?><!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Đăng nhập quản trị KPAH</title>
<link rel="stylesheet" href="../assets/css/admin-login.css?v=1"></head><body><main class="card"><div class="mark" aria-hidden="true">✦</div><div class="eyebrow">KPAH • SECURE ACCESS</div><h1>Chào mừng trở lại.</h1><p>Đăng nhập vào trung tâm điều khiển để quản lý mã giới thiệu và hoạt động đăng ký.</p>
<?php if ($error !== ''): ?><p class="error"><?= kp_e($error) ?></p><?php endif; ?>
<form method="post" autocomplete="on"><input type="hidden" name="csrf" value="<?= kp_e($csrf) ?>">
<label for="username">Tên đăng nhập</label><input id="username" name="username" maxlength="64" required autocomplete="username">
<label for="password">Mật khẩu</label><input id="password" name="password" type="password" required autocomplete="current-password">
<?php if (kp_admin_totp_enabled()): ?>
<label for="otp">Mã xác thực 2 bước (6 số)</label><input id="otp" name="otp" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" minlength="6" autocomplete="one-time-code" required placeholder="Mã trên ứng dụng Authenticator">
<?php endif; ?>
<button type="submit">Đăng nhập an toàn <span aria-hidden="true">↗</span></button></form><div class="secure"><i></i> Kết nối quản trị · Phiên tự hết hạn nếu không hoạt động 30 phút</div><div class="footer">KPAH CONTROL CENTER · AUTHORIZED PERSONNEL ONLY</div></main></body></html>
