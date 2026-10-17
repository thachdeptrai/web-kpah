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
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#eef7ff">
<link rel="icon" type="image/webp" href="/assets/img/favicon.webp">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800;900&amp;family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&amp;display=swap">
<title>Đăng nhập quản trị · NGHIỆN KPAH</title>
<link rel="stylesheet" href="/assets/css/site-responsive.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/site-responsive.css') ?>">
<link rel="stylesheet" href="/assets/css/ui-system.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/ui-system.css') ?>">
<link rel="stylesheet" href="/assets/css/auth-fantasy.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/auth-fantasy.css') ?>">
<link rel="stylesheet" href="/assets/css/auth-portal-v2.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/auth-portal-v2.css') ?>">
<link rel="stylesheet" href="/assets/css/auth-portal-v3.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/auth-portal-v3.css') ?>">
<?php if (is_file(KP_ROOT . '/assets/img/auth-login-art.webp')): ?><link rel="stylesheet" href="/assets/css/auth-portal-v4.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/auth-portal-v4.css') ?>"><?php endif; ?>
<link rel="stylesheet" href="/assets/css/admin-login-portal.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin-login-portal.css') ?>">
</head>
<body class="kp-admin-login-page kp-auth-page kp-auth-login kp-auth-v2 kp-auth-v3<?= is_file(KP_ROOT . '/assets/img/auth-login-art.webp') ? ' kp-auth-v4' : '' ?>">
<a class="skip-link" href="#admin-login-form">Bỏ qua đến biểu mẫu đăng nhập</a>
<header class="kp-auth-topbar">
  <a class="kp-auth-logo" href="/" aria-label="NGHIỆN KPAH — Trang chủ">
    <img src="/assets/img/kpah-logo-main.webp" width="1280" height="512" alt="NGHIỆN KPAH">
  </a>
  <span class="kp-auth-header-tagline">Thế giới tuổi thơ<br>Sống lại trong bạn!</span>
  <nav class="kp-auth-links" aria-label="Điều hướng website">
    <a href="/cong-dong">Cộng đồng</a>
    <a href="/">Trang chủ</a>
    <a class="kp-auth-download" href="/tai-game"><span aria-hidden="true">↓</span>&nbsp;<span class="kp-auth-download__text">Tải game</span></a>
  </nav>
</header>
<div class="scene" aria-hidden="true"></div>
<main class="stage login-stage">
  <section class="brand kp-auth-story" aria-label="Thế giới NGHIỆN KPAH">
    <div class="kp-auth-story__intro">
      <div class="kp-auth-story__top"><span class="kp-auth-story__chapter">KHU VỰC QUẢN TRỊ</span></div>
      <h1>Đăng nhập quản trị</h1>
      <p class="lead">Dành riêng cho tài khoản quản trị được cấp quyền.</p>
    </div>
    <figure class="kp-auth-story__visual" aria-hidden="true"><?php if (!is_file(KP_ROOT . '/assets/img/auth-login-art.webp')): ?><img src="/assets/img/auth-login-adventure.webp" width="800" height="667" alt="" decoding="async"><?php endif; ?></figure>
  </section>
  <section class="panel" aria-labelledby="admin-login-title">
    <div class="panel-inner login-panel">
      <div class="login-panel-heading">
        <span class="login-eyebrow">ADMIN ACCESS <i></i></span>
        <span class="login-version">KPAH</span>
      </div>
      <h2 id="admin-login-title">Đăng nhập Admin</h2>
      <p class="login-intro">Nhập tài khoản quản trị để tiếp tục.</p>
      <?php if ($error !== ''): ?>
        <div class="alert is-visible" data-type="error" role="alert">
          <strong>Không thể đăng nhập</strong><p><?= kp_e($error) ?></p>
        </div>
      <?php endif; ?>
      <form id="admin-login-form" method="post" action="/quan-tri/dang-nhap" autocomplete="on">
        <input type="hidden" name="csrf" value="<?= kp_e($csrf) ?>">
        <div class="fields">
          <div class="field">
            <label for="username">Tên đăng nhập</label>
            <div class="control">
              <input id="username" name="username" type="text" maxlength="64" autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" required placeholder="Tài khoản quản trị" value="<?= kp_e($_POST['username'] ?? '') ?>">
            </div>
          </div>
          <div class="field">
            <label for="password">Mật khẩu</label>
            <div class="control">
              <input id="password" name="password" type="password" maxlength="256" autocomplete="current-password" required placeholder="Nhập mật khẩu">
            </div>
          </div>
          <?php if (kp_admin_totp_enabled()): ?>
          <div class="field">
            <label for="otp">Mã xác thực 2 bước</label>
            <div class="control">
              <input id="otp" name="otp" type="text" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code" required placeholder="Mã gồm 6 chữ số">
            </div>
          </div>
          <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary login-submit"><span>Đăng nhập quản trị</span><span class="login-arrow" aria-hidden="true">↗</span></button>
      </form>
      <div class="login-secure-note"><span>Tài khoản người chơi? <a href="/dang-nhap">Đăng nhập tại đây</a>.</span></div>
    </div>
  </section>
</main>
<footer class="footer"><span>© <?= date('Y') ?> NGHIỆN KPAH</span><a href="/">Về trang chủ</a></footer>
<?php if ($error !== ''): ?><div hidden data-ui-toast="error" data-ui-toast-title="Đăng nhập thất bại" data-ui-toast-message="<?= kp_e($error) ?>"></div><?php endif; ?>
<script src="/assets/js/ui-system.js?v=<?= (int)@filemtime(KP_ROOT.'/assets/js/ui-system.js') ?>" defer></script>
<script src="/assets/js/fantasy-foundation.js?v=<?= (int)@filemtime(KP_ROOT.'/assets/js/fantasy-foundation.js') ?>" defer></script>
</body>
</html>
