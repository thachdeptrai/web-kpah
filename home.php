<?php
require __DIR__ . '/includes/bootstrap.php';
kp_send_security_headers(true);
header('Content-Type: text/html; charset=utf-8');

if (empty($_SESSION['kp_user_id'])) {
    header('Location: login.php');
    exit;
}

if (isset($_SESSION['kp_user_last_seen']) && time() - (int) $_SESSION['kp_user_last_seen'] > 1800) {
    unset($_SESSION['kp_user_id'], $_SESSION['kp_user_username'], $_SESSION['kp_user_last_seen']);
    header('Location: login.php?expired=1');
    exit;
}
$_SESSION['kp_user_last_seen'] = time();

try {
    $st = kp_db()->prepare('SELECT id, userid, username, email, phone, regdate FROM team_user WHERE id = ? LIMIT 1');
    $st->execute([(int) $_SESSION['kp_user_id']]);
    $user = $st->fetch();

    if (!$user) {
        unset($_SESSION['kp_user_id'], $_SESSION['kp_user_username'], $_SESSION['kp_user_last_seen']);
        header('Location: login.php');
        exit;
    }
} catch (Throwable $e) {
    kp_log('tải trang tài khoản thất bại', $e);
    http_response_code(503);
    exit('Chưa thể tải trang tài khoản. Vui lòng thử lại sau.');
}

$csrf = kp_csrf_token();
$username = (string) ($user['username'] ?? 'Player');
$email = trim((string) ($user['email'] ?? ''));
$phone = trim((string) ($user['phone'] ?? ''));
$initial = function_exists('mb_substr') ? mb_strtoupper(mb_substr($username, 0, 1, 'UTF-8'), 'UTF-8') : strtoupper(substr($username, 0, 1));
$appName = (string) kp_config('app.name', 'KPAH');
$playerId = (string) (($user['userid'] ?? '') !== '' ? $user['userid'] : $user['id']);
$registeredAt = '';
if (!empty($user['regdate'])) {
    $registeredTimestamp = is_numeric($user['regdate']) ? (int) $user['regdate'] : strtotime((string) $user['regdate']);
    if ($registeredTimestamp !== false && $registeredTimestamp > 0) {
        $registeredAt = date('d/m/Y', $registeredTimestamp);
    }
}
if ($registeredAt === '') {
    $registeredAt = 'Thành viên KPAH';
}
?><!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b0a16">
    <meta name="description" content="Quản lý hồ sơ, thông tin liên hệ và bảo mật tài khoản <?= kp_e($appName) ?>.">
    <meta name="robots" content="noindex, nofollow">
    <title>Tài khoản của tôi · <?= kp_e($appName) ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&amp;family=Cinzel:wght@700;900&amp;family=Philosopher:wght@700&amp;display=swap">
    <link rel="stylesheet" href="assets/css/register.css?v=1">
    <link rel="stylesheet" href="assets/css/user-home.css?v=5">
    <link rel="stylesheet" href="assets/css/home-theme.css?v=3">
</head>
<body class="home-page">

<a class="home-skip-link" href="#overview">Đi đến nội dung chính</a>

<aside class="side-rail" aria-label="Điều hướng tài khoản">
    <a class="rail-logo" href="/tai-khoan" aria-label="<?= kp_e($appName) ?>">K<span>.</span></a>
    <div class="rail-line" aria-hidden="true"></div>
    <a class="rail-icon active" href="#overview" aria-label="Tổng quan" title="Tổng quan"><span aria-hidden="true">⌂</span></a>
    <a class="rail-icon" href="#profile" aria-label="Hồ sơ tài khoản" title="Hồ sơ"><span aria-hidden="true">◎</span></a>
    <a class="rail-icon" href="#security" aria-label="Bảo mật" title="Bảo mật"><span aria-hidden="true">◇</span></a>
    <div class="rail-bottom">
        <a class="rail-icon rail-logout" href="api/logout.php" aria-label="Đăng xuất" title="Đăng xuất"><span aria-hidden="true">↗</span></a>
    </div>
</aside>

<main class="home-main">
    <header class="home-top">
        <div class="home-breadcrumb"><span class="breadcrumb-kicker">PLAYER SPACE</span><span class="breadcrumb-separator">/</span><span>TÀI KHOẢN</span></div>
        <div class="top-user">
            <span class="top-status"><i></i> ĐANG HOẠT ĐỘNG</span>
            <span class="top-user-name"><?= kp_e($username) ?></span>
            <span class="mini-avatar" aria-hidden="true"><?= kp_e($initial) ?></span>
            <a class="top-logout" href="api/logout.php">Đăng xuất <span aria-hidden="true">↗</span></a>
        </div>
    </header>

    <section class="welcome-block" id="overview" aria-labelledby="welcome-title">
        <div class="welcome-copy">
            <p class="kicker"><span class="kicker-mark"></span> ACCOUNT CENTER <span class="kicker-date"><?= date('d.m.Y') ?></span></p>
            <h1 id="welcome-title">Chào mừng trở lại,<br><em><?= kp_e($username) ?>.</em></h1>
            <p class="welcome-description">Mọi thông tin của bạn, trong một không gian riêng. Quản lý hồ sơ và giữ hành trình <?= kp_e($appName) ?> luôn an toàn.</p>
            <div class="welcome-actions">
                <a class="jump-link" href="#profile">Quản lý hồ sơ <span aria-hidden="true">↘</span></a>
                <span class="welcome-secure"><span aria-hidden="true">✦</span> KHU VỰC TÀI KHOẢN</span>
            </div>
        </div>

        <div class="welcome-index"><strong>01</strong><span> / 03</span></div>
    </section>

    <section class="metric-strip" aria-label="Tóm tắt tài khoản">
        <article class="metric-card">
            <span class="metric-icon" aria-hidden="true">⌘</span>
            <span class="metric-label">PLAYER ID</span>
            <strong>#<?= kp_e($playerId) ?></strong>
            <small>Mã định danh trong game</small>
        </article>
        <article class="metric-card">
            <span class="metric-icon" aria-hidden="true">✉</span>
            <span class="metric-label">EMAIL LIÊN KẾT</span>
            <strong class="metric-value"><?= $email !== '' ? kp_e($email) : 'Chưa liên kết' ?></strong>
            <small><?= $email !== '' ? 'Có thể dùng để khôi phục tài khoản' : 'Thêm email để tăng khả năng khôi phục' ?></small>
        </article>
        <article class="metric-card">
            <span class="metric-icon" aria-hidden="true">⌕</span>
            <span class="metric-label">SỐ ĐIỆN THOẠI</span>
            <strong class="metric-value"><?= $phone !== '' ? kp_e($phone) : 'Chưa cập nhật' ?></strong>
            <small>Thông tin liên hệ của bạn</small>
        </article>
        <article class="metric-card metric-card-status">
            <span class="metric-icon" aria-hidden="true">✧</span>
            <span class="metric-label">TRẠNG THÁI</span>
            <strong class="state-value"><i></i> ĐANG HOẠT ĐỘNG</strong>
            <small>Đăng nhập hợp lệ · <?= kp_e($registeredAt) ?></small>
        </article>
    </section>

    <section class="account-section" id="profile" aria-labelledby="profile-title">
        <div class="section-heading">
            <div>
                <p class="kicker">YOUR DETAILS <span>/ 02</span></p>
                <h2 id="profile-title">Hồ sơ tài khoản</h2>
            </div>
            <p>Cập nhật thông tin liên hệ ngay tại đây. Đổi email cần xác minh bằng mã 6 chữ số.</p>
        </div>

        <div class="profile-layout">
            <section class="profile-card" aria-labelledby="contact-title">
                <div class="card-heading">
                    <div>
                        <span class="card-index">A—01 / PROFILE</span>
                        <h3 id="contact-title">Thông tin liên hệ</h3>
                    </div>
                    <span class="edit-symbol" aria-hidden="true">↗</span>
                </div>

                <div class="profile-identity">
                    <div class="big-avatar" aria-hidden="true"><?= kp_e($initial) ?></div>
                    <div class="identity-copy">
                        <strong><?= kp_e($username) ?></strong>
                        <span><?= kp_e($appName) ?> Player · <?= kp_e($registeredAt) ?></span>
                    </div>
                    <span class="identity-tag">PLAYER</span>
                </div>

                <form id="profile-form" data-endpoint="api/user-profile.php">
                    <input type="hidden" name="csrf" value="<?= kp_e($csrf) ?>">
                    <div class="profile-field">
                        <label for="email">Gmail mới</label>
                        <input id="email" name="email" type="email" maxlength="254" autocomplete="email" required value="<?= kp_e($email) ?>" placeholder="name@example.com">
                        <p class="field-note">Đổi Gmail cần xác minh cả Gmail đang liên kết và Gmail mới.</p>
                    </div>
                    <div class="profile-field">
                        <label for="old_email_code">Mã gửi đến Gmail hiện tại</label>
                        <div class="input-with-action">
                            <input id="old_email_code" name="old_email_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="Mã 6 chữ số từ Gmail cũ">
                            <button type="button" id="send-old-code">Gửi mã Gmail cũ</button>
                        </div>
                    </div>
                    <div class="profile-field">
                        <label for="email_code">Mã gửi đến Gmail mới</label>
                        <div class="input-with-action">
                            <input id="email_code" name="email_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="Mã 6 chữ số từ Gmail mới">
                            <button type="button" id="send-code">Xác minh mã cũ &amp; gửi mã mới</button>
                        </div>
                        <p class="field-note">Nhập mã Gmail cũ trước khi bấm gửi mã mới. Cả hai mã đều có thời hạn.</p>
                    </div>
                    <div class="profile-field">
                        <label for="phone">Số điện thoại</label>
                        <input id="phone" name="phone" type="tel" maxlength="16" autocomplete="tel" placeholder="0xxxxxxxxx hoặc +84xxxxxxxxx" value="<?= kp_e($phone) ?>">
                    </div>
                    <div class="form-feedback" id="profile-feedback" role="status" aria-live="polite"></div>
                    <div class="profile-form-footer">
                        <span class="form-lock"><span aria-hidden="true">◇</span> Đổi Gmail yêu cầu xác minh 2 bước</span>
                        <button type="submit" class="save-button"><span>Lưu thay đổi</span><span aria-hidden="true">↗</span></button>
                    </div>
                </form>
            </section>

            <aside class="security-card" id="security" aria-labelledby="security-title">
                <div class="security-card-top"><span class="security-overline">ACCOUNT HYGIENE</span><span class="security-status"><i></i> ACTIVE</span></div>
                <div class="security-art" aria-hidden="true">
                    <div class="security-orbit orbit-a"></div>
                    <div class="security-orbit orbit-b"></div>
                    <div class="shield-shape"><span>✓</span></div>
                    <span class="security-spark spark-a">✦</span>
                    <span class="security-spark spark-b">✧</span>
                </div>
                <p class="kicker">BẢO MẬT TÀI KHOẢN <span>/ 03</span></p>
                <h3 id="security-title">Tài khoản an toàn,<br>cuộc chơi trọn vẹn.</h3>
                <p class="security-description">Giữ email và số điện thoại luôn chính xác để bạn có thể khôi phục quyền truy cập khi cần.</p>

                <div class="security-row">
                    <span><i></i> Phiên đăng nhập</span>
                    <b>Được bảo vệ</b>
                </div>
                <div class="security-row">
                    <span><i class="<?= $email !== '' ? 'verified' : 'pending' ?>"></i> Email liên hệ</span>
                    <b><?= $email !== '' ? 'Đã liên kết' : 'Cần bổ sung' ?></b>
                </div>

                <div class="password-change">
                    <div class="password-change-heading">
                        <div><span class="card-index">B—02 / SECURITY</span><h4>Đổi mật khẩu</h4></div>
                        <span class="password-key" aria-hidden="true">⌁</span>
                    </div>
                    <p class="password-help">Dùng mật khẩu hiện tại để xác nhận trước khi thay đổi.</p>
                    <form id="change-password-form" data-endpoint="api/change-password.php" autocomplete="off">
                        <input type="hidden" name="csrf" value="<?= kp_e($csrf) ?>">
                        <div class="profile-field">
                            <label for="current_password">Mật khẩu hiện tại</label>
                            <input id="current_password" name="current_password" type="password" minlength="6" maxlength="128" autocomplete="current-password" placeholder="Nhập mật khẩu hiện tại" required>
                        </div>
                        <div class="profile-field">
                            <label for="new_password">Mật khẩu mới</label>
                            <input id="new_password" name="new_password" type="password" minlength="6" maxlength="32" autocomplete="new-password" placeholder="Từ 6 đến 32 ký tự" required>
                        </div>
                        <div class="profile-field">
                            <label for="confirm_password">Xác nhận mật khẩu mới</label>
                            <input id="confirm_password" name="confirm_password" type="password" minlength="6" maxlength="32" autocomplete="new-password" placeholder="Nhập lại mật khẩu mới" required>
                        </div>
                        <div class="form-feedback" id="password-feedback" role="status" aria-live="polite"></div>
                        <button type="submit" id="change-password-submit" class="save-button password-submit"><span>Cập nhật mật khẩu</span><span aria-hidden="true">↗</span></button>
                    </form>
                </div>
                <a class="security-link" href="/quen-mat-khau"><span>Quên hoặc cần đặt lại mật khẩu?</span><span aria-hidden="true">→</span></a>
                <p class="security-footnote"><span aria-hidden="true">✦</span> Không chia sẻ mật khẩu hoặc mã xác minh với bất kỳ ai.</p>
            </aside>
        </div>
    </section>

    <footer class="home-footer">
        <span class="footer-brand"><?= kp_e($appName) ?> <i>·</i> PLAYER SPACE</span>
        <span class="footer-id">ACCOUNT ID <?= kp_e($user['id']) ?></span>
        <a href="api/logout.php">ĐĂNG XUẤT <span aria-hidden="true">↗</span></a>
    </footer>
</main>

<script src="assets/js/user-home.js?v=2" defer></script>
</body>
</html>