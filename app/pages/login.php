<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/user_center.php';
kp_send_security_headers(true);
header('Content-Type: text/html; charset=utf-8');

if (!empty($_SESSION['kp_user_id'])) {
    header('Location: /tai-khoan', true, 302);
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
                $st = kp_db()->prepare('SELECT id, userid, username, password, email, phone FROM team_user WHERE username = ? AND ban = 0 LIMIT 1');
                $st->execute([$username]);
                $user = $st->fetch();
                $stored = $user ? (string)$user['password'] : '';
                $valid = $user && kp_verify_account_password($password, $stored);
                if ($valid) {
                    session_regenerate_id(true);
                    $_SESSION['kp_user_id'] = (int)$user['id'];
                    $_SESSION['kp_user_username'] = (string)$user['username'];
                    $_SESSION['kp_user_last_seen'] = time();
                    unset($_SESSION['kp_admin_id'], $_SESSION['kp_admin_role']);
                    kp_center_event((int)$user['id'], 'login');
                    $_SESSION['kp_toast']=['type'=>'success','title'=>'Đăng nhập thành công','message'=>'Đã mở khu tài khoản của bạn.'];
                    header('Location: /tai-khoan');
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
    <meta name="theme-color" content="#eef7ff">
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600&amp;family=Cinzel:wght@700;900&amp;family=Philosopher:wght@700&amp;display=swap">
    <link rel="stylesheet" href="/assets/css/register.css?v=1">
    <link rel="stylesheet" href="/assets/css/login.css?v=2">
    <link rel="stylesheet" href="/assets/css/user-center.css?v=1">
<link rel="stylesheet" href="/assets/css/site-responsive.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/site-responsive.css') ?>">
<link rel="stylesheet" href="/assets/css/ui-system.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/ui-system.css') ?>">
<link rel="stylesheet" href="/assets/css/auth-fantasy.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/auth-fantasy.css') ?>">
<link rel="stylesheet" href="/assets/css/auth-portal-v2.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/auth-portal-v2.css') ?>">
<link rel="stylesheet" href="/assets/css/auth-portal-v3.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/auth-portal-v3.css') ?>">
<link rel="stylesheet" href="/assets/css/auth-portal-v4.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/auth-portal-v4.css') ?>">
</head>
<body class="kp-auth-page kp-auth-login kp-auth-v2 kp-auth-v3 kp-auth-v4">
<a class="skip-link" href="#login-form">Bỏ qua và đến biểu mẫu đăng nhập</a>
<header class="kp-auth-topbar">
  <a class="kp-auth-logo" href="/" aria-label="NGHIỆN KPAH — Trang chủ"><img src="/assets/img/kpah-logo-fantasy.svg" width="152" height="59" alt="NGHIỆN KPAH"><strong>NGHIỆN KPAH</strong></a>
  <span class="kp-auth-header-tagline">Thế giới tuổi thơ<br>Sống lại trong bạn!</span>
  <nav class="kp-auth-links" aria-label="Điều hướng website">
    <a href="/cong-dong">Cộng đồng</a>
    <a href="/gioi-thieu">Hướng dẫn</a>
    <a href="/">Trang chủ</a>
    <a class="kp-auth-download" href="/tai-game"><span aria-hidden="true">↓</span>&nbsp;<span class="kp-auth-download__text">Tải game ngay</span></a>
  </nav>
</header>
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
    <section class="brand kp-auth-story" aria-labelledby="page-title">
        <div class="kp-auth-story__intro">
        <div class="kp-auth-story__top"><span class="kp-auth-story__chapter">01 · ĐĂNG NHẬP</span><span class="kp-auth-story__tag">HÀNH TRÌNH TUỔI THƠ</span></div>
        <img class="kp-auth-story__logo" src="/assets/img/kpah-logo-fantasy.svg" width="760" height="292" alt="" decoding="async">
        <h1 id="page-title">Chào mừng trở lại, chiến hữu!</h1>
        <p class="lead">Đăng nhập để tiếp tục chuyến phiêu lưu trong thế giới <?= kp_e($appName) ?>.</p>
        </div>
        <figure class="kp-auth-story__visual" aria-label="Chiến binh trở lại vùng đất tuổi thơ">
            <img src="/assets/img/auth-login-adventure.svg" width="760" height="710" alt="" loading="eager" decoding="async">
        </figure>
        <ul class="rules">
            <li><svg class="icon" aria-hidden="true"><use href="#i-user"/></svg><span><strong>Tài khoản:</strong> nhập đúng tên tài khoản đã đăng ký.</span></li>
            <li><svg class="icon" aria-hidden="true"><use href="#i-lock"/></svg><span><strong>Bảo mật:</strong> không chia sẻ mật khẩu hoặc mã xác minh.</span></li>
            <li><svg class="icon" aria-hidden="true"><use href="#i-user-add"/></svg><span>Chưa có tài khoản? <a class="brand-register-link" href="/dang-ky">Đăng ký miễn phí ngay <span>↗</span></a></span></li>
        </ul>
    </section>
    <section class="panel" aria-labelledby="panel-title">
        <div class="panel-inner login-panel">
            <nav class="kp-auth-switch" aria-label="Chuyển giữa các trang tài khoản">
                <a href="/dang-nhap" aria-current="page">Đăng nhập</a>
                <a href="/dang-ky">Đăng ký</a>
                <a href="/quen-mat-khau">Quên mật khẩu</a>
            </nav>
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
            <form id="login-form" method="post" action="/dang-nhap" autocomplete="on">
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
                        <div class="login-label-row"><label for="password">Mật khẩu</label><a class="login-forgot" href="/quen-mat-khau">Quên mật khẩu?</a></div>
                        <div class="control">
                            <svg class="icon lead-icon" aria-hidden="true"><use href="#i-lock"/></svg>
                            <input id="password" name="password" type="password" autocomplete="current-password" maxlength="128" placeholder="Nhập mật khẩu" required>
                            <button type="button" class="eye" id="toggle-password" aria-label="Hiện mật khẩu" aria-pressed="false"><svg class="icon" aria-hidden="true"><use href="#i-eye"/></svg></button>
                        </div>
                    </div>
                </div>
                <button type="submit" id="login-submit" class="btn btn-primary login-submit"><svg class="icon" aria-hidden="true"><use href="#i-login"/></svg><span>Đăng nhập tài khoản</span><span class="login-arrow">↗</span></button>
                <p class="alt-link">Chưa có tài khoản? <a href="/dang-ky">Tạo tài khoản mới</a></p>
            </form>
            <div class="login-secure-note"><svg class="icon" aria-hidden="true"><use href="#i-lock"/></svg><span>Phiên đăng nhập được bảo vệ. Chỉ đăng nhập trên thiết bị bạn tin cậy.</span></div>
        </div>
    </section>
</main>
<footer class="footer"><span>&copy; <?= date('Y') ?> <?= kp_e($appName) ?>. Bảo lưu mọi quyền.</span><?php if ($version !== ''): ?><span>Phiên bản <?= kp_e($version) ?></span><?php endif; ?><a href="/dang-ky">Đăng ký tài khoản</a></footer>
<?php if($error!==''):?><div hidden data-ui-toast="error" data-ui-toast-title="Đăng nhập thất bại" data-ui-toast-message="<?=kp_e($error)?>"></div><?php endif;?>
<script src="/assets/js/login.js?v=1" defer></script>
<script src="/assets/js/ui-system.js?v=<?= (int)@filemtime(KP_ROOT.'/assets/js/ui-system.js') ?>" defer></script>
<script src="/assets/js/fantasy-foundation.js?v=<?= (int)@filemtime(KP_ROOT.'/assets/js/fantasy-foundation.js') ?>" defer></script>
</body>
</html>
