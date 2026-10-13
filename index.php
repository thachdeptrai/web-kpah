<?php
/**
 * Trang chủ công khai NGHIỆN KPAH.
 * Không thay đổi luồng đăng nhập, đăng ký hay cấu trúc database game.
 */
require __DIR__ . '/includes/bootstrap.php';

kp_send_security_headers(true);
header('Content-Type: text/html; charset=utf-8');

$appName = 'NGHIỆN KPAH';
$isLoggedIn = !empty($_SESSION['kp_user_id']);
$loginUrl = '/login.php';
$registerUrl = '/register.php';
$accountUrl = '/home.php';
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
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&amp;family=Cinzel:wght@700;900&amp;display=swap">
    <link rel="stylesheet" href="assets/css/landing.css?v=1">
</head>
<body class="landing-page">
<a class="lp-skip" href="#gioi-thieu">Đi đến nội dung chính</a>
<header class="lp-header">
    <div class="lp-wrap lp-nav">
        <a class="lp-brand" href="/" aria-label="NGHIỆN KPAH — Trang chủ">
            <img src="assets/img/emblem.svg" width="400" height="400" alt="">
            <span class="lp-brand-copy"><strong>NGHIỆN KPAH</strong><small>PLAYER COMMUNITY</small></span>
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
        <img class="lp-hero-emblem" src="assets/img/emblem.svg" width="400" height="400" alt="Biểu tượng KPAH">
        <h1 id="lp-title">NGHIỆN KPAH<span>HÀNH TRÌNH CỦA BẠN BẮT ĐẦU TỪ ĐÂY</span></h1>
        <p class="lp-lead">Một điểm đến dành cho người chơi KPAH — tìm hiểu thông tin, quản lý tài khoản và kết nối cùng cộng đồng trong một giao diện gọn gàng, tối ưu cả máy tính lẫn điện thoại.</p>
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
        <p class="lp-hero-note">Giao diện dọc · Tối ưu cho mobile · Giữ phong cách huyền ảo KPAH</p>
    </section>

    <section class="lp-wrap lp-section" id="gioi-thieu" aria-labelledby="intro-title">
        <div class="lp-section-head">
            <div><span class="lp-index">01 / GIỚI THIỆU</span><h2 id="intro-title">Chất KPAH, theo cách của bạn.</h2></div>
            <p>Trang chủ tập trung vào những thứ người chơi cần tìm nhanh — không nhồi bảng điều khiển hay khung viền dày đặc.</p>
        </div>
        <p class="lp-intro"><strong>NGHIỆN KPAH</strong> là cổng thông tin và tài khoản dành cho cộng đồng người chơi. Từ đây, bạn có thể bắt đầu hành trình bằng tài khoản của mình, xem các thông tin hữu ích và tìm nơi kết nối với những người cùng đam mê. Nền trăng sao và biểu tượng KPAH hiện có được giữ lại, kết hợp bố cục dọc, khoảng thở rộng và điểm nhấn vàng tím.</p>
        <div class="lp-feature-list">
            <article class="lp-feature"><span class="lp-feature-mark">I.</span><h3>Không gian KPAH</h3><p>Nhận diện fantasy tối màu, ánh vàng và tím; nội dung đặt ở giữa màn hình, dễ đọc trên mọi kích thước.</p></article>
            <article class="lp-feature"><span class="lp-feature-mark">II.</span><h3>Tài khoản tập trung</h3><p>Đăng ký, đăng nhập và quản lý thông tin cá nhân từ các trang hiện có của website.</p></article>
            <article class="lp-feature"><span class="lp-feature-mark">III.</span><h3>Kết nối cộng đồng</h3><p>Đi tới kênh cộng đồng để trao đổi, hỏi đáp và theo dõi các thông báo được chia sẻ.</p></article>
        </div>
    </section>

    <section class="lp-wrap lp-section" id="thong-tin" aria-labelledby="info-title">
        <div class="lp-section-head">
            <div><span class="lp-index">02 / THÔNG TIN</span><h2 id="info-title">Mọi thứ cần thiết, rõ ràng.</h2></div>
            <p>Thông tin hiển thị dựa trên những chức năng và dữ liệu thực tế đang có trong source.</p>
        </div>
        <div class="lp-info-grid">
            <article class="lp-info"><span class="lp-info-icon" aria-hidden="true">⌘</span><div><h3>Tài khoản người chơi</h3><p>Tạo tài khoản bằng biểu mẫu đăng ký hiện có. Hệ thống yêu cầu xác minh email và thông tin theo cấu hình đăng ký.</p></div></article>
            <article class="lp-info"><span class="lp-info-icon" aria-hidden="true">◇</span><div><h3>Bảo mật tài khoản</h3><p>Quản lý email, số điện thoại và mật khẩu tại khu tài khoản; không chia sẻ mã xác minh hoặc mật khẩu.</p></div></article>
            <article class="lp-info"><span class="lp-info-icon" aria-hidden="true">↗</span><div><h3>Khôi phục truy cập</h3><p>Nếu quên mật khẩu, sử dụng trang khôi phục hiện có để bắt đầu quy trình đặt lại.</p></div></article>
            <article class="lp-info"><span class="lp-info-icon" aria-hidden="true">✦</span><div><h3>Thông tin minh bạch</h3><p>Không hiển thị trạng thái máy chủ hoặc tin sự kiện giả khi source chưa có dữ liệu tương ứng.</p></div></article>
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
                <p>Tham gia nhóm Zalo cộng đồng để trao đổi và theo dõi thông tin được chia sẻ. Website hiện chưa có bảng bài viết diễn đàn trong SQL, nên nút này dẫn tới kênh cộng đồng thực tế thay vì tạo liên kết diễn đàn không tồn tại.</p>
                <p class="lp-community-note">Khi bổ sung hệ thống bài viết/diễn đàn vào sau, khu vực này có thể chuyển sang hiển thị bài mới và chủ đề nổi bật.</p>
            </div>
            <a class="lp-button" href="https://zalo.me/g/zmyedl5en7sldmd9el1z" target="_blank" rel="noopener noreferrer">VÀO CỘNG ĐỒNG ZALO <span aria-hidden="true">↗</span></a>
        </div>
    </section>

    <section class="lp-wrap lp-section" id="website" aria-labelledby="site-title">
        <div class="lp-section-head">
            <div><span class="lp-index">04 / WEBSITE</span><h2 id="site-title">Bắt đầu nhanh.</h2></div>
            <p>Các lối tắt dẫn tới đúng trang đang có, không thay đổi API hay luồng tài khoản.</p>
        </div>
        <div class="lp-site-links">
            <a class="lp-site-link" href="<?= kp_e($registerUrl) ?>"><span aria-hidden="true">＋</span><strong>Đăng ký tài khoản</strong><small>Tạo tài khoản mới theo quy trình xác minh hiện tại.</small></a>
            <a class="lp-site-link" href="<?= kp_e($isLoggedIn ? $accountUrl : $loginUrl) ?>"><span aria-hidden="true">◎</span><strong><?= $isLoggedIn ? 'Khu tài khoản' : 'Đăng nhập' ?></strong><small><?= $isLoggedIn ? 'Quản lý thông tin và bảo mật tài khoản.' : 'Đăng nhập để mở khu vực tài khoản cá nhân.' ?></small></a>
            <a class="lp-site-link" href="/quen-mat-khau"><span aria-hidden="true">◇</span><strong>Quên mật khẩu</strong><small>Bắt đầu quy trình khôi phục quyền truy cập.</small></a>
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
