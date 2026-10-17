<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_security.php';
kp_send_security_headers(true);
header('Content-Type: text/html; charset=utf-8');

if (!kp_admin_allowed_ip()) {
    http_response_code(403);
    exit('Truy cập quản trị bị giới hạn.');
}
if (!empty($_SESSION['kp_admin_id']) && in_array(($_SESSION['kp_admin_role'] ?? ''), ['admin','key'],true)) {
    kp_admin_require('/quan-tri/dang-nhap',['admin','key']);
    header('Location: '.(($_SESSION['kp_admin_role']??'')==='key'?'/quan-tri/ma-gioi-thieu':'/quan-tri'));
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
                        && in_array($admin['role'],['admin','key'],true)
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
                        header('Location: '.($admin['role']==='key'?'/quan-tri/ma-gioi-thieu':'/quan-tri'));
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
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#eef7ff">
<link rel="icon" type="image/webp" href="/assets/img/favicon.webp">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&amp;family=Cinzel:wght@700;900&amp;family=Philosopher:wght@700&amp;display=swap">
<title>Đăng nhập quản trị · NGHIỆN KPAH</title>
<link rel="stylesheet" href="/assets/css/site-responsive.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/site-responsive.css') ?>">
<link rel="stylesheet" href="/assets/css/admin-login.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin-login.css') ?>">
<link rel="stylesheet" href="/assets/css/ui-system.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/ui-system.css') ?>">
<link rel="stylesheet" href="/assets/css/admin-theme.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin-theme.css') ?>">
</head>
<body class="kp-admin-login-page">
<div class="kp-admin-login-scene" aria-hidden="true"></div>
<header class="kp-auth-header">
  <a class="kp-auth-brand" href="/" aria-label="NGHIỆN KPAH - Trang chủ">
    <img src="/assets/img/emblem.webp" width="46" height="46" alt="">
    <span><strong>NGHIỆN KPAH</strong><small>CỔNG QUẢN TRỊ</small></span>
  </a>
  <a href="/" class="kp-auth-return">← Trở về trang chủ</a>
</header>
<main class="kp-admin-login-stage">
 <section class="kp-admin-login-intro" aria-labelledby="admin-login-title">
  <div class="kp-admin-login-emblem" aria-hidden="true"><img src="/assets/img/emblem.webp" width="360" height="360" alt=""></div>
  <div class="kp-admin-login-kicker"><i></i> NGHIỆN KPAH / ADMIN ACCESS</div>
  <h1 id="admin-login-title">Trung tâm <em>quản trị.</em></h1>
  <p>Đăng nhập an toàn để quản lý người chơi, nhân vật và các hoạt động vận hành game.</p>
  <div class="kp-admin-login-points">
    <div><span aria-hidden="true">◇</span><strong>Đúng quyền truy cập</strong><small>Tài khoản admin được cấp phép</small></div>
    <div><span aria-hidden="true">✧</span><strong>Phiên đăng nhập bảo vệ</strong><small>Hỗ trợ xác thực hai bước</small></div>
    <div><span aria-hidden="true">◷</span><strong>Lịch sử xử lý</strong><small>Thao tác quản trị được ghi nhật ký</small></div>
  </div>
 </section>
 <section class="kp-admin-login-card" aria-labelledby="form-title">
  <div class="kp-admin-login-card-head"><span class="eyebrow">BẢO MẬT / XÁC MINH DANH TÍNH</span><span class="kp-admin-login-led"><i></i> SECURE</span></div>
  <h2 id="form-title">Đăng nhập Admin</h2>
  <p>Vui lòng sử dụng thông tin quản trị đã được cấp để tiếp tục.</p>
  <?php if($error!==''): ?><div class="error" role="alert"><strong>Không thể đăng nhập</strong><span><?= kp_e($error) ?></span></div><?php endif; ?>
  <form method="post" action="/quan-tri/dang-nhap" autocomplete="on">
    <input type="hidden" name="csrf" value="<?= kp_e($csrf) ?>">
    <label for="username">Tên đăng nhập</label>
    <input id="username" name="username" maxlength="64" autocomplete="username" required placeholder="Tài khoản quản trị">
    <label for="password">Mật khẩu</label>
    <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Nhập mật khẩu bảo mật">
    <?php if(kp_admin_totp_enabled()): ?>
    <label for="otp">Mã xác thực 2 bước (6 số)</label>
    <input id="otp" name="otp" type="text" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code" required placeholder="Mã trên ứng dụng Authenticator">
    <?php endif; ?>
    <button type="submit">Đăng nhập an toàn <span aria-hidden="true">↗</span></button>
  </form>
  <p class="kp-admin-login-security"><i aria-hidden="true"></i> Phiên đăng nhập tự hết hạn sau 30 phút không hoạt động.</p>
  <div class="kp-admin-login-foot">NGHIỆN KPAH · AUTHORIZED ADMINISTRATORS ONLY</div>
 </section>
</main>
<footer class="kp-auth-footer">© NGHIỆN KPAH · BẢO MẬT DÀNH CHO QUẢN TRỊ VIÊN</footer>
<script src="/assets/js/ui-system.js?v=<?= (int)@filemtime(KP_ROOT.'/assets/js/ui-system.js') ?>" defer></script>
<script src="/assets/js/fantasy-foundation.js?v=<?= (int)@filemtime(KP_ROOT.'/assets/js/fantasy-foundation.js') ?>" defer></script>
</body></html>
