<?php
require __DIR__ . '/includes/bootstrap.php';
kp_send_security_headers(true);
header('Content-Type: text/html; charset=utf-8');

if (!empty($_SESSION['kp_user_id'])) {
    header('Location: home.php');
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
?><!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#11130f"><title>Đăng nhập · <?=kp_e($appName)?></title><link rel="stylesheet" href="assets/css/user-auth.css?v=1"></head>
<body class="auth-page"><div class="auth-grain"></div><main class="auth-shell">
<a class="auth-brand" href="register.php"><span class="brand-mark">K</span><span><?=kp_e($appName)?><small>PLAYER ACCESS</small></span></a>
<section class="auth-story"><p class="kicker">YOUR JOURNEY CONTINUES</p><h1>Trở lại<br><em>thế giới của bạn.</em></h1><p class="story-copy">Một cánh cửa. Một tài khoản. Mọi hành trình vẫn ở đó.</p><div class="orbit-art" aria-hidden="true"><div class="orbit orbit-a"></div><div class="orbit orbit-b"></div><div class="orbit-core">K</div><span class="orbit-dot"></span></div><div class="story-foot"><span>01 / PLAYER PORTAL</span><span>SECURE SESSION</span></div></section>
<section class="auth-form-wrap"><div class="form-top"><span class="tiny-label">PLAYER PORTAL <b>↗</b></span><span class="status"><i></i> HỆ THỐNG TRỰC TUYẾN</span></div><h2>Đăng nhập</h2><p class="form-sub">Nhập thông tin tài khoản game để tiếp tục.</p>
<?php if ($error !== ''): ?><div class="form-error" role="alert"><?=kp_e($error)?></div><?php endif; ?>
<form method="post" autocomplete="on"><input type="hidden" name="csrf" value="<?=kp_e($csrf)?>">
<label for="username">Tên tài khoản</label><input id="username" name="username" maxlength="20" autocomplete="username" placeholder="Nhập tên tài khoản" required value="<?=kp_e($_POST['username'] ?? '')?>">
<label for="password">Mật khẩu <a href="forgot-password.php">Quên mật khẩu?</a></label><input id="password" name="password" type="password" autocomplete="current-password" placeholder="Nhập mật khẩu" required>
<button class="primary-action" type="submit">Vào trang tài khoản <span>↗</span></button></form>
<div class="auth-switch">Chưa có tài khoản? <a href="register.php">Tạo tài khoản <span>→</span></a></div><p class="auth-privacy">Đừng chia sẻ mật khẩu hoặc mã xác minh với bất kỳ ai.</p></section>
</main></body></html>