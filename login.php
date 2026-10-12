<?php
require __DIR__ . '/includes/bootstrap.php';
kp_send_security_headers(true);
header('Content-Type: text/html; charset=utf-8');

if (!empty($_SESSION['kp_user_id'])) {
    header('Location: tai-khoan');
    exit;
}
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if (!kp_csrf_check($_POST['csrf'] ?? '')) {
        $error = 'Phiên đăng nhập đã hết hạn. Hãy tải lại trang.';
    } elseif ($username === '' || $password === '') {
        $error = 'Nhập đầy đủ tên tài khoản và mật khẩu.';
    } else {
        list($allowed, $retry) = kp_rate_limit('user_login', kp_client_ip().'|'.strtolower($username), 8, 900, true);
        if (!$allowed) {
            $error = 'Bạn thử quá nhiều lần. Hãy thử lại sau '.max(1, (int)ceil($retry / 60)).' phút.';
        } else {
            try {
                $st = kp_db()->prepare('SELECT id, userid, username, password, email, phone FROM team_user WHERE username = ? LIMIT 1');
                $st->execute([$username]);
                $user = $st->fetch();
                $stored = $user ? (string)$user['password'] : '';
                $valid = $user && (
                    hash_equals(kp_game_password_hash($password), $stored)
                    || (!isset($stored[0]) || $stored[0] !== '*') && hash_equals($stored, $password)
                );
                if ($valid) {
                    session_regenerate_id(true);
                    $_SESSION['kp_user_id'] = (int)$user['id'];
                    $_SESSION['kp_user_username'] = (string)$user['username'];
                    $_SESSION['kp_user_last_seen'] = time();
                    unset($_SESSION['kp_admin_id'], $_SESSION['kp_admin_role']);
                    header('Location: home.php');
                    exit;
                }
                $error = 'Tên tài khoản hoặc mật khẩu không đúng.';
            } catch (Throwable $e) {
                kp_log('user login thất bại', $e);
                $error = 'Chưa thể đăng nhập lúc này. Hãy thử lại sau.';
            }
        }
    }
}
$csrf = kp_csrf_token();
$appName = (string)kp_config('app.name', 'KPAH');
$version = trim((string)kp_config('app.version', ''));
?><!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Đăng nhập tài khoản – <?= kp_e($appName) ?></title>
    <meta name="description" content="Đăng nhập tài khoản game <?= kp_e($appName) ?>.">
    <meta name="robots" content="noindex,follow">
    <meta name="theme-color" content="#0b0a16">
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600&amp;family=Cinzel:wght@700;900&amp;family=Philosopher:wght@700&amp;display=swap">
    <link rel="stylesheet" href="assets/css/register.css?v=1">
    <link rel="stylesheet" href="assets/css/login.css?v=2">
</head>
<body>
<a class="skip-link" href="#login-form">Bỏ qua và đến biểu mẫu đăng nhập</a>
<div class="scene" aria-hidden="true"><canvas id="embers"></canvas></div>
<svg class="sprite" width="0" height="0" aria-hidden="true" focusable="false">
    <defs>
        <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></symbol>
        <symbol id="i-lock" viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2.5"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></symbol>
        <symbol id="i-eye" viewBox="0 0 24 24"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></symbol>
        <symbol id="i-eye-off" viewBox="0 0 24 24"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/><path d="M4 4l16 16"/></symbol>
        <symbol id="i-alert" viewBox="0 0 24 24"><path d="M12 3l10 18H2L12 3z"/><path d="M12 10v5M12 18h.01"/></symbol>
        <symbol id="i-login" viewBox="0 0 24 24"><path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4"/><path d="M10 8l4 4-4 4M14 12H4"/></symbol>
        <symbol id="i-user-add" viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21c0-4.4 3-7 7-7 2 0 3.6.6 4.8 1.6M19 8v8M15 12h8"/></symbol>
    </defs>
</svg>
<main class="stage login-stage">
    <section class="brand" aria-labelledby="page-title">
        <div class="emblem" aria-hidden="true"><img src="assets/img/emblem.svg" alt="" width="400" height="400"></div>
        <p class="wordmark" aria-label="<?= kp_e($appName) ?>"><?= kp_e($appName) ?></p>
        <h1 id="page-title">Đăng nhập tài khoản</h1>
        <p class="lead">Tiếp tục hành trình của bạn trong thế giới <?= kp_e($appName) ?>.</p>
        <ul class="rules">
            <li><svg class="icon" aria-hidden="true"><use href="#i-user"/></svg><span><strong>Tài khoản:</strong> nhập đúng tên tài khoản đã đăng ký.</span></li>
            <li><svg class="icon" aria-hidden="true"><use href="#i-lock"/></svg><span><strong>Bảo mật:</strong> không chia sẻ mật khẩu hoặc mã xác minh.</span></li>
            <li><svg class="icon" aria-hidden="true"><use href="#i-user-add"/></svg><span>Chưa có tài khoản? <a class="brand-register-link" href="dang-ky">Đăng ký miễn phí ngay <span>↗</span></a></span></li>
        </ul>
    </section>
    <section class="panel" aria-labelledby="panel-title">
        <div class="panel-inner login-panel">
            <div class="login-panel-heading">
                <span class="login-eyebrow">PLAYER ACCESS <i></i></span>
                <span class="login-version"><?= $version !== '' ? 'VERSION '.kp_e($version) : 'SECURE SIGN IN' ?></span>
            </div>
            <h2 id="panel-title">Chào mừng trở lại</h2>
            <p class="login-intro">Đăng nhập để mở trang quản lý tài khoản của bạn.</p>
            <?php if ($error !== ''): ?>
                <div class="alert is-visible" data-type="error" role="alert">
                    <svg class="icon" aria-hidden="true"><use href="#i-alert"/></svg>
                    <div><strong>Không thể đăng nhập</strong><p><?= kp_e($error) ?></p></div>
                </div>
            <?php endif; ?>
            <form id="login-form" method="post" action="dang-nhap" autocomplete="on">
                <input type="hidden" name="csrf" value="<?= kp_e($csrf) ?>">
                <div class="fields">
                    <div class="field">
                        <label for="username">Tên tài khoản</label>
                        <div class="control">
                            <svg class="icon lead-icon" aria-hidden="true"><use href="#i-user"/></svg>
                            <input id="username" name="username" type="text" inputmode="text" autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" maxlength="20" placeholder="Nhập tên tài khoản" required value="<?= kp_e($_POST['username'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="field">
                        <div class="login-label-row"><label for="password">Mật khẩu</label><a class="login-forgot" href="quen-mat-khau">Quên mật khẩu?</a></div>
                        <div class="control">
                            <svg class="icon lead-icon" aria-hidden="true"><use href="#i-lock"/></svg>
                            <input id="password" name="password" type="password" autocomplete="current-password" maxlength="128" placeholder="Nhập mật khẩu" required>
                            <button type="button" class="eye" id="toggle-password" aria-label="Hiện mật khẩu" aria-pressed="false"><svg class="icon" aria-hidden="true"><use href="#i-eye"/></svg></button>
                        </div>
                    </div>
                </div>
                <button type="submit" id="login-submit" class="btn btn-primary login-submit"><svg class="icon" aria-hidden="true"><use href="#i-login"/></svg><span>Đăng nhập tài khoản</span><span class="login-arrow">↗</span></button>
                <p class="alt-link">Chưa có tài khoản? <a href="register.php">Tạo tài khoản mới</a></p>
            </form>
            <div class="login-secure-note"><svg class="icon" aria-hidden="true"><use href="#i-lock"/></svg><span>Phiên đăng nhập được bảo vệ. Chỉ đăng nhập trên thiết bị bạn tin cậy.</span></div>
        </div>
    </section>
</main>
<footer class="footer"><span>&copy; <?= date('Y') ?> <?= kp_e($appName) ?>. Bảo lưu mọi quyền.</span><?php if ($version !== ''): ?><span>Phiên bản <?= kp_e($version) ?></span><?php endif; ?><a href="register.php">Đăng ký tài khoản</a></footer>
<script src="assets/js/scene.js?v=1" defer></script>
<script src="assets/js/login.js?v=1" defer></script>
</body>
</html>
