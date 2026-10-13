<?php
/**
 * Trang chủ công khai NGHIỆN KPAH.
 * Không thay đổi luồng đăng nhập, đăng ký hay cấu trúc database game.
 */
require __DIR__ . '/../includes/bootstrap.php';

kp_send_security_headers(true);
header('Content-Type: text/html; charset=utf-8');

$appName = 'NGHIỆN KPAH';
$isLoggedIn = !empty($_SESSION['kp_user_id']);
$loginUrl = '/dang-nhap';
$registerUrl = '/dang-ky';
$accountUrl = '/tai-khoan';
$downloadUrl = kp_safe_url(kp_config('links.download', ''));
$supportUrl = kp_safe_url(kp_config('links.support', ''));
$accountCount = null;

try {
    $accountCount = (int) kp_db()->query('SELECT COUNT(*) FROM team_user')->fetchColumn();
} catch (Throwable $e) {
    kp_log('không tải được số lượng tài khoản trang chủ', $e);
}
?><!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b091b">
    <meta name="description" content="NGHIỆN KPAH — cổng thông tin, tài khoản và cộng đồng dành cho người chơi KPAH.">
    <meta name="robots" content="index,follow">
    <title>NGHIỆN KPAH — Cổng thông tin người chơi</title>
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&amp;family=Cinzel:wght@700;900&amp;display=swap">
    <link rel="stylesheet" href="/assets/css/landing.css?v=1">
</head>
<body class="landing-page">
<a class="lp-skip" href="#gioi-thieu">Đi đến nội dung chính</a>
<header class="lp-header">
    <div class="lp-wrap lp-nav">
        <a class="lp-brand" href="/" aria-label="NGHIỆN KPAH — Trang chủ">
            <img src="/assets/img/emblem.svg" width="400" height="400" alt="">
            <span class="lp-brand-copy"><strong>NGHIỆN KPAH</strong><small>CỘNG ĐỒNG NGƯỜI CHƠI</small></span>
        </a>
        <nav class="lp-links" aria-label="Điều hướng chính">
            <a href="#gioi-thieu">Giới thiệu</a>
            <a href="#thong-tin">Thông tin</a>
            <a href="#dien-dan">Diễn đàn</a>
            <a href="#website">Website</a>
            <?php if ($isLoggedIn): ?>
                <a class="lp-login" href="<?= kp_e($accountUrl) ?>">Tài khoản ↗</a>
            <?php else: ?>
                <a class="lp-login" href="<?= kp_e($loginUrl) ?>">Đăng nhập ↗</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main>
    <section class="lp-wrap lp-hero" aria-labelledby="lp-title">
        <p class="lp-eyebrow">THẾ GIỚI KPAH · KẾT NỐI CỘNG ĐỒNG</p>
        <img class="lp-hero-emblem" src="/assets/img/emblem.svg" width="400" height="400" alt="Biểu tượng KPAH">
        <h1 id="lp-title">NGHIỆN KPAH<span>HẸN NHAU TRONG THẾ GIỚI KPAH</span></h1>
        <p class="lp-lead">Vào xem nhân vật, theo dõi bảng xếp hạng hay tìm anh em cùng chơi — mọi thứ bạn cần đều ở đây.</p>
        <div class="lp-hero-actions">
            <?php if ($isLoggedIn): ?>
                <a class="lp-button" href="<?= kp_e($accountUrl) ?>">VÀO KHU TÀI KHOẢN <span aria-hidden="true">↗</span></a>
            <?php else: ?>
                <a class="lp-button" href="<?= kp_e($registerUrl) ?>">TẠO TÀI KHOẢN <span aria-hidden="true">↗</span></a>
                <a class="lp-button lp-button-secondary" href="<?= kp_e($loginUrl) ?>">ĐĂNG NHẬP</a>
            <?php endif; ?>
            <?php if ($downloadUrl !== ''): ?>
                <a class="lp-button lp-button-secondary" href="<?= kp_e($downloadUrl) ?>" target="_blank" rel="noopener noreferrer">TẢI GAME</a>
            <?php endif; ?>
        </div>
        <p class="lp-hero-note">Đã có tài khoản? Đăng nhập và gặp lại nhân vật của bạn.</p>
    </section>

    <section class="lp-wrap lp-section" id="gioi-thieu" aria-labelledby="intro-title">
        <div class="lp-section-head">
            <div><span class="lp-index">01 / GIỚI THIỆU</span><h2 id="intro-title">Chất KPAH, theo cách của bạn.</h2></div>
            <p>Có người mê luyện cấp, có người thích tranh tài. Còn bạn, hôm nay vào game làm gì?</p>
        </div>
        <p class="lp-intro"><strong>NGHIỆN KPAH</strong> là nơi anh em cùng chơi, cùng bàn chuyện game. Tạo tài khoản nếu bạn mới đến; còn nếu đã quen đường, ghé xem nhân vật và những cái tên đang dẫn đầu bảng xếp hạng.</p>
        <div class="lp-feature-list">
            <article class="lp-feature"><span class="lp-feature-mark">I.</span><h3>Theo dấu nhân vật</h3><p>Xem cấp độ, kinh nghiệm và tài nguyên của các nhân vật thuộc tài khoản bạn.</p></article>
            <article class="lp-feature"><span class="lp-feature-mark">II.</span><h3>Giữ tài khoản của bạn</h3><p>Cập nhật email, số điện thoại và đổi mật khẩu khi cần.</p></article>
            <article class="lp-feature"><span class="lp-feature-mark">III.</span><h3>Kết nối cộng đồng</h3><p>Có gì chưa rõ cứ hỏi anh em trong nhóm, biết đâu lại tìm được đồng đội mới.</p></article>
        </div>
    </section>

    <section class="lp-wrap lp-section" id="thong-tin" aria-labelledby="info-title">
        <div class="lp-section-head">
            <div><span class="lp-index">02 / THÔNG TIN</span><h2 id="info-title">Vào game từ đây.</h2></div>
            <p>Đăng ký lần đầu hay quay lại sau một thời gian, bạn đều có thể bắt đầu từ đây.</p>
        </div>
        <div class="lp-info-grid">
            <article class="lp-info"><span class="lp-info-icon" aria-hidden="true">⌘</span><div><h3>Tài khoản người chơi</h3><p>Chọn tên đăng nhập, đặt mật khẩu và làm theo hướng dẫn xác minh trên trang đăng ký.</p></div></article>
            <article class="lp-info"><span class="lp-info-icon" aria-hidden="true">◇</span><div><h3>Bảo mật tài khoản</h3><p>Cập nhật email, số điện thoại và đổi mật khẩu khi cần.</p></div></article>
            <article class="lp-info"><span class="lp-info-icon" aria-hidden="true">↗</span><div><h3>Khôi phục truy cập</h3><p>Quên mật khẩu? Dùng email đã liên kết để lấy lại quyền truy cập.</p></div></article>
            <article class="lp-info"><span class="lp-info-icon" aria-hidden="true">✦</span><div><h3>Theo dõi bảng xếp hạng</h3><p>Xem những nhân vật và bang hội dẫn đầu về cấp độ, danh vọng và thành tích tranh tài.</p></div></article>
        </div>
        <?php if ($accountCount !== null): ?>
            <div class="lp-count"><span>Số tài khoản đã đăng ký trên hệ thống</span><strong><?= number_format($accountCount, 0, ',', '.') ?></strong></div>
        <?php endif; ?>
    </section>

    <section class="lp-wrap lp-section" id="dien-dan" aria-labelledby="community-title">
        <div class="lp-section-head">
            <div><span class="lp-index">03 / DIỄN ĐÀN & CỘNG ĐỒNG</span><h2 id="community-title">Gặp nhau ngoài cuộc chơi.</h2></div>
            <p>Trao đổi kinh nghiệm, kết nối người chơi và tìm hỗ trợ khi cần.</p>
        </div>
        <div class="lp-community">
            <div>
                <h3>Cộng đồng NGHIỆN KPAH</h3>
                <p>Tìm bạn cùng chơi, hỏi kinh nghiệm lên cấp hoặc chia sẻ chuyện trong game. Anh em đang chờ bạn ở nhóm Zalo NGHIỆN KPAH.</p>
                <p class="lp-community-note">Không gửi mật khẩu hoặc mã xác minh cho người khác, kể cả trong nhóm cộng đồng.</p>
            </div>
            <a class="lp-button" href="https://zalo.me/g/zmyedl5en7sldmd9el1z" target="_blank" rel="noopener noreferrer">VÀO CỘNG ĐỒNG ZALO <span aria-hidden="true">↗</span></a>
        </div>
    </section>

    <section class="lp-wrap lp-section" id="website" aria-labelledby="site-title">
        <div class="lp-section-head">
            <div><span class="lp-index">04 / WEBSITE</span><h2 id="site-title">Bắt đầu nhanh.</h2></div>
            <p>Tạo tài khoản mới, đăng nhập hoặc lấy lại mật khẩu.</p>
        </div>
        <div class="lp-site-links">
            <a class="lp-site-link" href="<?= kp_e($registerUrl) ?>"><span aria-hidden="true">＋</span><strong>Đăng ký tài khoản</strong><small>Chọn tên đăng nhập và bắt đầu cùng anh em.</small></a>
            <a class="lp-site-link" href="<?= kp_e($isLoggedIn ? $accountUrl : $loginUrl) ?>"><span aria-hidden="true">◎</span><strong><?= $isLoggedIn ? 'Khu tài khoản' : 'Đăng nhập' ?></strong><small><?= $isLoggedIn ? 'Quản lý thông tin và bảo mật tài khoản.' : 'Đăng nhập để mở khu vực tài khoản cá nhân.' ?></small></a>
            <a class="lp-site-link" href="/quen-mat-khau"><span aria-hidden="true">◇</span><strong>Quên mật khẩu</strong><small>Lấy lại mật khẩu qua email.</small></a>
        </div>
        <?php if ($supportUrl !== ''): ?>
            <p class="lp-intro">Cần hỗ trợ? <a href="<?= kp_e($supportUrl) ?>">Liên hệ kênh hỗ trợ</a>.</p>
        <?php endif; ?>
    </section>
</main>

<footer class="lp-wrap lp-footer">
    <strong>NGHIỆN KPAH</strong>
    <span>© <?= date('Y') ?> · Cổng thông tin cộng đồng KPAH</span>
    <a href="#lp-title">Lên đầu trang ↑</a>
</footer>
</body>
</html>
