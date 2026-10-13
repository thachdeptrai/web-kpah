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

// Bảng xếp hạng công khai: chỉ đọc các cột đã xác nhận trong bảng player.
$topPlayers = [];
try {
    $rankStmt = kp_db()->query(
        'SELECT name, power, pet_power, (power + pet_power) AS total_power
         FROM player
         ORDER BY total_power DESC
         LIMIT 10'
    );
    $topPlayers = $rankStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // Không để lỗi bảng game ảnh hưởng trang tài khoản; chi tiết chỉ ghi vào log.
    kp_log('không tải được bảng xếp hạng người chơi', $e);
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
    <link rel="stylesheet" href="assets/css/user-home.css?v=6">
    <link rel="stylesheet" href="assets/css/home-theme.css?v=5">
</head>
<body class="home-page">

<a class="home-skip-link" href="#overview">Đi đến nội dung chính</a>

<aside class="side-rail" aria-label="Điều hướng tài khoản">
    <a class="rail-logo" href="/tai-khoan" aria-label="<?= kp_e($appName) ?>">K<span>.</span></a>
    <div class="rail-line" aria-hidden="true"></div>
    <a class="rail-icon active" href="#overview" aria-label="Tổng quan" title="Tổng quan"><span aria-hidden="true">⌂</span></a>
    <a class="rail-icon" href="#profile" aria-label="Hồ sơ tài khoản" title="Hồ sơ"><span aria-hidden="true">◎</span></a>
    <a class="rail-icon" href="#community" aria-label="Cộng đồng" title="Cộng đồng"><span aria-hidden="true">♧</span></a>
    <a class="rail-icon" href="#security" aria-label="Bảo mật" title="Bảo mật"><span aria-hidden="true">◇</span></a>
    <div class="rail-bottom">
        <a class="rail-icon rail-logout" href="api/logout.php" aria-label="Đăng xuất" title="Đăng xuất"><span aria-hidden="true">↗</span></a>
    </div>
</aside>

<main class="home-main">
    <header class="home-top">
        <div class="home-breadcrumb"><span class="breadcrumb-kicker">KPAH</span><span class="breadcrumb-separator">/</span><span>TÀI KHOẢN CỦA TÔI</span></div>
        <div class="top-user">
            <span class="top-status"><i></i> ĐANG ĐĂNG NHẬP</span>
            <span class="top-user-name"><?= kp_e($username) ?></span>
            <span class="mini-avatar" aria-hidden="true"><?= kp_e($initial) ?></span>
            <a class="top-logout" href="api/logout.php">Đăng xuất <span aria-hidden="true">↗</span></a>
        </div>
    </header>

    <section class="welcome-block" id="overview" aria-labelledby="welcome-title">
        <div class="welcome-copy">
            <p class="kicker"><span class="kicker-mark"></span> TÀI KHOẢN <span class="kicker-date"><?= date('d.m.Y') ?></span></p>
            <h1 id="welcome-title">Chào mừng trở lại,<br><em><?= kp_e($username) ?>.</em></h1>
            <p class="welcome-description">Quản lý thông tin tài khoản, cập nhật Gmail và đổi mật khẩu ngay tại đây.</p>
            <div class="welcome-actions">
                <a class="jump-link" href="#profile">Quản lý hồ sơ <span aria-hidden="true">↘</span></a>
                <span class="welcome-secure"><span aria-hidden="true">✦</span> TÀI KHOẢN CỦA BẠN CỦA TÔI</span>
            </div>
        </div>

        <div class="welcome-index"><strong>01</strong><span> / 03</span></div>
    </section>

    <section class="metric-strip" aria-label="Tóm tắt tài khoản">
        <article class="metric-card">
            <span class="metric-icon" aria-hidden="true">⌘</span>
            <span class="metric-label">MÃ NGƯỜI CHƠI</span>
            <strong>#<?= kp_e($playerId) ?></strong>
            <small>Mã tài khoản trong game</small>
        </article>
        <article class="metric-card">
            <span class="metric-icon" aria-hidden="true">✉</span>
            <span class="metric-label">GMAIL LIÊN KẾT</span>
            <strong class="metric-value"><?= $email !== '' ? kp_e($email) : 'Chưa liên kết' ?></strong>
            <small><?= $email !== '' ? 'Dùng để nhận mã xác minh và khôi phục tài khoản' : 'Thêm Gmail để dễ lấy lại tài khoản khi cần' ?></small>
        </article>
        <article class="metric-card">
            <span class="metric-icon" aria-hidden="true">⌕</span>
            <span class="metric-label">SỐ ĐIỆN THOẠI</span>
            <strong class="metric-value"><?= $phone !== '' ? kp_e($phone) : 'Chưa cập nhật' ?></strong>
            <small>Số liên hệ của tài khoản</small>
        </article>
        <article class="metric-card metric-card-status">
            <span class="metric-icon" aria-hidden="true">✧</span>
            <span class="metric-label">TRẠNG THÁI</span>
            <strong class="state-value"><i></i> ĐANG ĐĂNG NHẬP</strong>
            <small>Tham gia từ <?= kp_e($registeredAt) ?></small>
        </article>
    </section>

    <section class="community-dashboard" id="community" aria-labelledby="community-title">
        <div class="section-heading community-heading">
            <div>
                <p class="kicker">CỘNG ĐỒNG KPAH</p>
                <h2 id="community-title">Top sức mạnh</h2>
            </div>
            <p>10 nhân vật có tổng sức mạnh cao nhất hiện tại.</p>
        </div>

        <div class="community-grid community-grid-single">
            <section class="community-panel community-ranking" aria-labelledby="ranking-title">
                <div class="community-panel-heading">
                    <div><span class="card-index">TOP 10</span><h3 id="ranking-title">Nhân vật nổi bật</h3></div>
                    <span class="community-panel-icon" aria-hidden="true">✦</span>
                </div>
                <?php if (!empty($topPlayers)): ?>
                    <div class="community-rank-table" role="table" aria-label="Top 10 sức mạnh">
                        <div class="community-rank-header" role="row">
                            <span role="columnheader">Hạng</span>
                            <span role="columnheader">Nhân vật</span>
                            <span role="columnheader">Sức mạnh</span>
                            <span role="columnheader">Tổng sức mạnh</span>
                        </div>
                        <?php foreach ($topPlayers as $i => $player): ?>
                            <div class="community-rank-row" role="row">
                                <span class="community-rank rank-<?= (int) $i + 1 ?>" role="cell"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                                <strong class="community-rank-name" role="cell"><?= kp_e($player['name'] ?? 'Ẩn danh') ?></strong>
                                <span class="community-rank-power" role="cell"><?= number_format((int) ($player['power'] ?? 0), 0, ',', '.') ?></span>
                                <strong class="community-rank-total" role="cell"><?= number_format((int) ($player['total_power'] ?? 0), 0, ',', '.') ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                <?php else: ?>
                    <p class="community-empty">Chưa có dữ liệu xếp hạng.</p>
                <?php endif; ?>
            </section>
        </div>
    </section>

    <section class="account-section" id="profile" aria-labelledby="profile-title">
        <div class="section-heading">
            <div>
                <p class="kicker">THÔNG TIN TÀI KHOẢN</p>
                <h2 id="profile-title">Hồ sơ tài khoản</h2>
            </div>
            <p>Bạn có thể đổi Gmail, cập nhật số điện thoại và xác minh trước khi lưu.</p>
        </div>

        <div class="profile-layout">
            <section class="profile-card" aria-labelledby="contact-title">
                <div class="card-heading">
                    <div>
                        <span class="card-index">HỒ SƠ</span>
                        <h3 id="contact-title">Thông tin tài khoản</h3>
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
                        <p class="field-note">Để đổi Gmail, bạn cần xác minh cả địa chỉ cũ và địa chỉ mới.</p>
                    </div>
                    <div class="profile-field">
                        <label for="old_email_code">Mã xác minh Gmail cũ</label>
                        <div class="input-with-action">
                            <input id="old_email_code" name="old_email_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="Mã 6 chữ số từ Gmail cũ">
                            <button type="button" id="send-old-code">Gửi mã</button>
                        </div>
                    </div>
                    <div class="profile-field">
                        <label for="email_code">Mã xác minh Gmail mới</label>
                        <div class="input-with-action">
                            <input id="email_code" name="email_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="Mã 6 chữ số từ Gmail mới">
                            <button type="button" id="send-code">Xác minh và gửi mã mới</button>
                        </div>
                        <p class="field-note">Nhập mã từ Gmail cũ trước. Mã xác minh chỉ có hiệu lực trong một khoảng thời gian.</p>
                    </div>
                    <div class="profile-field">
                        <label for="phone">Số điện thoại</label>
                        <input id="phone" name="phone" type="tel" maxlength="16" autocomplete="tel" placeholder="0xxxxxxxxx hoặc +84xxxxxxxxx" value="<?= kp_e($phone) ?>">
                    </div>
                    <div class="form-feedback" id="profile-feedback" role="status" aria-live="polite"></div>
                    <div class="profile-form-footer">
                        <span class="form-lock"><span aria-hidden="true">◇</span> Cần xác minh 2 bước khi đổi Gmail</span>
                        <button type="submit" class="save-button"><span>Lưu thay đổi</span><span aria-hidden="true">↗</span></button>
                    </div>
                </form>
            </section>

            <aside class="security-card" id="security" aria-labelledby="security-title">
                <div class="security-card-top"><span class="security-overline">BẢO MẬT</span><span class="security-status"><i></i> ĐANG DÙNG</span></div>
                <div class="security-art" aria-hidden="true">
                    <div class="security-orbit orbit-a"></div>
                    <div class="security-orbit orbit-b"></div>
                    <div class="shield-shape"><span>✓</span></div>
                    <span class="security-spark spark-a">✦</span>
                    <span class="security-spark spark-b">✧</span>
                </div>
                <p class="kicker">BẢO MẬT TÀI KHOẢN CỦA TÔI <span>/ 03</span></p>
                <h3 id="security-title">Giữ tài khoản của bạn an toàn.</h3>
                <p class="security-description">Kiểm tra Gmail và số điện thoại để có thể lấy lại tài khoản khi cần.</p>

                <div class="security-row">
                    <span><i></i> Phiên đăng nhập</span>
                    <b>Đang hoạt động</b>
                </div>
                <div class="security-row">
                    <span><i class="<?= $email !== '' ? 'verified' : 'pending' ?>"></i> Gmail liên kết</span>
                    <b><?= $email !== '' ? 'Đã có Gmail' : 'Chưa có Gmail' ?></b>
                </div>

                <div class="password-change">
                    <div class="password-change-heading">
                        <div><span class="card-index">MẬT KHẨU</span><h4>Đổi mật khẩu</h4></div>
                        <span class="password-key" aria-hidden="true">⌁</span>
                    </div>
                    <p class="password-help">Nhập mật khẩu hiện tại, sau đó đặt mật khẩu mới.</p>
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
                <a class="security-link" href="/quen-mat-khau"><span>Quên mật khẩu?</span><span aria-hidden="true">→</span></a>
                <p class="security-footnote"><span aria-hidden="true">✦</span> Đừng chia sẻ mật khẩu hay mã xác minh cho người khác.</p>
            </aside>
        </div>
    </section>

    <footer class="home-footer">
        <span class="footer-brand"><?= kp_e($appName) ?> <i>·</i> KPAH</span>
        <span class="footer-id">MÃ TÀI KHOẢN <?= kp_e($user['id']) ?></span>
        <a href="api/logout.php">ĐĂNG XUẤT <span aria-hidden="true">↗</span></a>
    </footer>
</main>

<script src="assets/js/user-home.js?v=2" defer></script>
</body>
</html>