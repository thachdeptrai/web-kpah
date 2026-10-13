<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
kp_send_security_headers(true);
header('Content-Type: text/html; charset=utf-8');

if (!empty($_SESSION['kp_user_id'])) {
    header('Location: /tai-khoan', true, 302);
    exit;
}

$csrf = kp_csrf_token();
$appName = (string) kp_config('app.name', 'KPAH');
$version = trim((string) kp_config('app.version', ''));
?><!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0b0a16">
    <title>Quên mật khẩu – <?= kp_e($appName) ?></title>
    <meta name="description" content="Khôi phục mật khẩu tài khoản <?= kp_e($appName) ?> bằng email đã liên kết.">
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600&amp;family=Cinzel:wght@700;900&amp;family=Philosopher:wght@700&amp;display=swap">
    <link rel="stylesheet" href="assets/css/register.css?v=1">
    <link rel="stylesheet" href="assets/css/login.css?v=2">
    <link rel="stylesheet" href="assets/css/recovery.css?v=2">
</head>
<body>
<a class="skip-link" href="#recovery-form">Bỏ qua và đến biểu mẫu khôi phục</a>
<div class="scene" aria-hidden="true"><canvas id="embers"></canvas></div>
<svg class="sprite" width="0" height="0" aria-hidden="true" focusable="false">
    <defs>
        <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></symbol>
        <symbol id="i-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></symbol>
        <symbol id="i-lock" viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2.5"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></symbol>
        <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 3l8 3v6c0 4.5-3.2 8.2-8 9-4.8-.8-8-4.5-8-9V6l8-3z"/><path d="M8.5 12l2.5 2.5 4.5-5"/></symbol>
        <symbol id="i-alert" viewBox="0 0 24 24"><path d="M12 3l10 18H2L12 3z"/><path d="M12 10v5M12 18h.01"/></symbol>
        <symbol id="i-login" viewBox="0 0 24 24"><path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4"/><path d="M10 8l4 4-4 4M14 12H4"/></symbol>
        <symbol id="i-key" viewBox="0 0 24 24"><circle cx="8" cy="15" r="5"/><path d="m11.5 11.5 8-8 2 2-2 2 2 2-3 3-2-2-3.5 3.5"/></symbol>
    </defs>
</svg>
<main class="stage login-stage recovery-stage">
    <section class="brand recovery-brand" aria-labelledby="page-title">
        <div class="emblem" aria-hidden="true"><img src="assets/img/emblem.svg" alt="" width="400" height="400"></div>
        <p class="wordmark" aria-label="<?= kp_e($appName) ?>"><?= kp_e($appName) ?></p>
        <p class="recovery-kicker">ACCOUNT SECURITY / <?= $version !== '' ? kp_e($version) : 'RECOVERY CENTER' ?></p>
        <h1 id="page-title">Lấy lại quyền<br><span>truy cập.</span></h1>
        <p class="lead">Một bước xác minh email để bạn trở lại hành trình của mình.</p>
        <ul class="rules">
            <li><svg class="icon" aria-hidden="true"><use href="#i-mail"/></svg><span><strong>Xác minh email:</strong> mã được gửi tới email đã liên kết với tài khoản.</span></li>
            <li><svg class="icon" aria-hidden="true"><use href="#i-shield"/></svg><span><strong>An toàn:</strong> mã chỉ dùng một lần và tự hết hạn.</span></li>
            <li><svg class="icon" aria-hidden="true"><use href="#i-lock"/></svg><span>Không chia sẻ mã xác minh với bất kỳ ai.</span></li>
        </ul>
        <div class="recovery-orbit" aria-hidden="true"><span></span><i>✦</i></div>
    </section>
    <section class="panel" aria-labelledby="panel-title">
        <div class="panel-inner login-panel recovery-panel">
            <div class="login-panel-heading">
                <span class="login-eyebrow">ACCOUNT RECOVERY <i></i></span>
                <span class="login-version">SECURE RESET</span>
            </div>
            <h2 id="panel-title">Quên mật khẩu?</h2>
            <p class="login-intro">Nhập tài khoản hoặc email đã đăng ký. Sau khi nhận mã, hãy đặt mật khẩu mới.</p>
            <form id="recovery-form" data-endpoint="api/forgot-password.php" autocomplete="on">
                <input type="hidden" name="csrf" value="<?= kp_e($csrf) ?>">
                <div class="fields">
                    <div class="field">
                        <label for="identity">Tên tài khoản hoặc email</label>
                        <div class="control">
                            <svg class="icon lead-icon" aria-hidden="true"><use href="#i-user"/></svg>
                            <input id="identity" name="identity" type="text" maxlength="254" autocomplete="username" autocapitalize="none" spellcheck="false" placeholder="Nhập tài khoản hoặc Gmail" required>
                        </div>
                    </div>
                    <button type="button" id="request-reset-code" class="btn btn-ghost recovery-send"><svg class="icon" aria-hidden="true"><use href="#i-mail"/></svg><span>Gửi mã xác minh về Gmail</span><b>↗</b></button>
                    <div class="recovery-separator"><span>SAU KHI NHẬN ĐƯỢC MÃ</span></div>
                    <div class="field">
                        <label for="code">Mã xác minh 6 chữ số</label>
                        <div class="control">
                            <svg class="icon lead-icon" aria-hidden="true"><use href="#i-shield"/></svg>
                            <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000" required>
                        </div>
                    </div>
                    <div class="field">
                        <label for="new_password">Mật khẩu mới</label>
                        <div class="control">
                            <svg class="icon lead-icon" aria-hidden="true"><use href="#i-lock"/></svg>
                            <input id="new_password" name="new_password" type="password" minlength="6" maxlength="32" autocomplete="new-password" placeholder="Từ 6 đến 32 ký tự" required>
                        </div>
                    </div>
                    <div class="field">
                        <label for="confirm_password">Nhập lại mật khẩu mới</label>
                        <div class="control">
                            <svg class="icon lead-icon" aria-hidden="true"><use href="#i-lock"/></svg>
                            <input id="confirm_password" name="confirm_password" type="password" minlength="6" maxlength="32" autocomplete="new-password" placeholder="Nhập lại mật khẩu mới" required>
                        </div>
                    </div>
                </div>
                <div class="form-feedback recovery-feedback" id="recovery-feedback" role="status" aria-live="polite"></div>
                <button type="submit" class="btn btn-primary login-submit recovery-submit"><svg class="icon" aria-hidden="true"><use href="#i-key"/></svg><span>Xác minh và đổi mật khẩu</span><b>↗</b></button>
            </form>
            <p class="alt-link recovery-back">Nhớ mật khẩu rồi? <a href="/login.php">Quay lại đăng nhập</a></p>
            <div class="login-secure-note"><svg class="icon" aria-hidden="true"><use href="#i-shield"/></svg><span>Mã có thời hạn. Nếu không yêu cầu khôi phục, hãy bỏ qua email.</span></div>
        </div>
    </section>
</main>
<footer class="footer"><span>&copy; <?= date('Y') ?> <?= kp_e($appName) ?>. Bảo lưu mọi quyền.</span><a href="/login.php">Đăng nhập</a></footer>
<script src="assets/js/scene.js?v=1" defer></script>
<script src="assets/js/forgot-password.js?v=2" defer></script>
</body>
</html>
