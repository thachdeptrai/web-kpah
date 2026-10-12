<?php
require __DIR__ . '/includes/bootstrap.php';
kp_send_security_headers(true);
header('Content-Type: text/html; charset=utf-8');
if (empty($_SESSION['kp_user_id'])) {
    header('Location: login.php');
    exit;
}
if (isset($_SESSION['kp_user_last_seen']) && time() - (int)$_SESSION['kp_user_last_seen'] > 1800) {
    unset($_SESSION['kp_user_id'], $_SESSION['kp_user_username'], $_SESSION['kp_user_last_seen']);
    header('Location: login.php?expired=1');
    exit;
}
$_SESSION['kp_user_last_seen'] = time();
try {
    $st = kp_db()->prepare('SELECT id, userid, username, email, phone, regdate FROM team_user WHERE id = ? LIMIT 1');
    $st->execute([(int)$_SESSION['kp_user_id']]);
    $user = $st->fetch();
    if (!$user) {
        unset($_SESSION['kp_user_id'], $_SESSION['kp_user_username'], $_SESSION['kp_user_last_seen']);
        header('Location: login.php');
        exit;
    }
} catch (Throwable $e) {
    kp_log('tải trang chủ user thất bại', $e);
    http_response_code(503);
    exit('Chưa thể tải trang tài khoản. Vui lòng thử lại sau.');
}
$csrf = kp_csrf_token();
$email = (string)($user['email'] ?? '');
$phone = (string)($user['phone'] ?? '');
$initial = strtoupper(substr((string)$user['username'], 0, 1));
$appName = (string)kp_config('app.name', 'KPAH');
?><!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#11130f"><title>Tài khoản của tôi · <?=kp_e($appName)?></title><link rel="stylesheet" href="assets/css/user-home.css?v=1"><link rel="stylesheet" href="assets/css/user-auth.css?v=1"></head>
<body class="home-page"><div class="home-noise"></div><aside class="side-rail"><a class="rail-logo" href="home.php">K<span>.</span></a><div class="rail-line"></div><a class="rail-icon active" href="#overview" aria-label="Tổng quan">⌂</a><a class="rail-icon" href="#profile" aria-label="Thông tin tài khoản">◎</a><a class="rail-icon" href="#security" aria-label="Bảo mật">⌑</a><div class="rail-bottom"><a class="rail-icon" href="api/logout.php" aria-label="Đăng xuất">↗</a></div></aside>
<main class="home-main"><header class="home-top"><div class="breadcrumb">PLAYER SPACE <span>/</span> OVERVIEW</div><div class="top-user"><span class="online-dot"></span><span><?=kp_e($user['username'])?></span><div class="mini-avatar"><?=kp_e($initial)?></div></div></header>
<section class="welcome-block" id="overview"><div class="welcome-copy"><p class="kicker">ACCOUNT CENTER <span>— <?=date('d.m.Y')?></span></p><h1>Chào mừng trở lại,<br><em><?=kp_e($user['username'])?>.</em></h1><p>Không gian riêng của bạn. Kiểm tra thông tin, cập nhật liên hệ và giữ tài khoản luôn an toàn.</p><a class="jump-link" href="#profile">Quản lý hồ sơ <span>↘</span></a></div><div class="welcome-graphic" aria-hidden="true"><div class="graphic-ring ring-one"></div><div class="graphic-ring ring-two"></div><div class="graphic-sun"></div><div class="graphic-cross">✳</div><div class="graphic-code">KPAH / PLAYER<br>EST. <?=date('Y', strtotime((string)($user['regdate'] ?: 'now')))?></div></div><div class="welcome-index">01<span> / 03</span></div></section>
<section class="metric-strip"><article><span class="metric-label">PLAYER ID</span><strong>#<?=kp_e($user['userid'] ?? $user['id'])?></strong><small>Định danh trong game</small></article><article><span class="metric-label">EMAIL</span><strong class="metric-value"><?= $email !== '' ? kp_e($email) : 'Chưa liên kết' ?></strong><small><?= $email !== '' ? 'Thông tin liên hệ' : 'Nên thêm email để khôi phục mật khẩu' ?></small></article><article><span class="metric-label">PHONE</span><strong class="metric-value"><?= $phone !== '' ? kp_e($phone) : 'Chưa cập nhật' ?></strong><small>Số điện thoại liên hệ</small></article><article><span class="metric-label">TRẠNG THÁI</span><strong class="state-value"><i></i> ĐANG HOẠT ĐỘNG</strong><small>Phiên đăng nhập được bảo vệ</small></article></section>
<div class="section-heading" id="profile"><div><p class="kicker">YOUR DETAILS / 02</p><h2>Hồ sơ tài khoản</h2></div><p>Chỉ email mới cần mã xác minh trước khi lưu thay đổi.</p></div>
<div class="profile-layout"><section class="profile-card"><div class="card-heading"><div><span class="card-index">A—01</span><h3>Thông tin liên hệ</h3></div><span class="edit-symbol">↗</span></div><div class="profile-identity"><div class="big-avatar"><?=kp_e($initial)?></div><div><strong><?=kp_e($user['username'])?></strong><span>Thành viên KPAH</span></div><span class="identity-tag">PLAYER</span></div>
<form id="profile-form" data-endpoint="api/user-profile.php"><input type="hidden" name="csrf" value="<?=kp_e($csrf)?>"><label for="email">Địa chỉ Gmail / Email</label><div class="input-with-action"><input id="email" name="email" type="email" maxlength="254" autocomplete="email" required value="<?=kp_e($email)?>"><button type="button" id="send-code">Gửi mã</button></div><div class="field-note">Mã xác minh sẽ được gửi tới email mới bạn nhập.</div><label for="email_code">Mã xác minh email</label><input id="email_code" name="email_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="Nhập mã 6 chữ số"><label for="phone">Số điện thoại</label><input id="phone" name="phone" type="tel" maxlength="16" autocomplete="tel" placeholder="+84xxxxxxxxx hoặc 0xxxxxxxxx" value="<?=kp_e($phone)?>"><div class="form-feedback" id="profile-feedback" role="status" aria-live="polite"></div><button type="submit" class="save-button">Lưu thay đổi <span>↗</span></button></form></section>
<aside class="security-card" id="security"><div class="security-art" aria-hidden="true"><div class="shield-shape">✓</div><span class="security-orbit"></span></div><p class="kicker">ACCOUNT HYGIENE</p><h3>Giữ tài khoản<br>trong tầm kiểm soát.</h3><p>Email đang hoạt động giúp bạn khôi phục mật khẩu khi cần. Không gửi mã xác minh cho người khác.</p><div class="security-row"><span><i></i> Phiên đăng nhập</span><b>Được bảo vệ</b></div><div class="security-row"><span><i class="<?= $email !== '' ? 'verified' : 'pending' ?>"></i> Email liên hệ</span><b><?= $email !== '' ? 'Đã có' : 'Cần bổ sung' ?></b></div><a class="security-link" href="forgot-password.php">Đổi / khôi phục mật khẩu <span>→</span></a></aside></div>
<footer class="home-footer"><span>KPAH / PLAYER SPACE</span><span>ACCOUNT ID <?=kp_e($user['id'])?></span><a href="api/logout.php">ĐĂNG XUẤT ↗</a></footer></main>
<script src="assets/js/user-home.js?v=1" defer></script></body></html>