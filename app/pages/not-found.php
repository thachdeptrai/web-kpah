<?php
$projectRoot = dirname(__DIR__, 2);
$landingCssVersion = (int) @filemtime($projectRoot . '/assets/css/landing.css');
$responsiveCssVersion = (int) @filemtime($projectRoot . '/assets/css/site-responsive.css');
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#e9f5ff">
    <meta name="robots" content="noindex,follow">
    <title>Không tìm thấy trang · NGHIỆN KPAH</title>
    <meta name="description" content="Trang này không tồn tại hoặc đã được chuyển. Quay về thế giới NGHIỆN KPAH.">
    <link rel="icon" type="image/webp" href="/assets/img/favicon.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800;900&amp;family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&amp;display=swap">
    <link rel="stylesheet" href="/assets/css/landing.css?v=<?= $landingCssVersion ?>">
    <link rel="stylesheet" href="/assets/css/site-responsive.css?v=<?= $responsiveCssVersion ?>">
</head>
<body class="landing-page lp-error-page">
<main class="lp-error-shell">
    <a class="lp-error-brand" href="/" aria-label="NGHIỆN KPAH — Trang chủ">
        <img src="/assets/img/kpah-logo-main.webp" width="1280" height="512" alt="NGHIỆN KPAH">
    </a>
    <section class="lp-error-card" aria-labelledby="error-title">
        <p class="lp-error-code">✦ 404 · LẠC ĐƯỜNG MỘT CHÚT</p>
        <h1 id="error-title">Đường này chưa mở,<br><em>mình quay lại nhé?</em></h1>
        <p>Có thể liên kết đã thay đổi hoặc địa chỉ bạn nhập chưa chính xác. Cổng phiêu lưu vẫn ở ngay phía sau.</p>
        <div class="lp-error-actions">
            <a class="lp-button lp-button-primary" href="/">Về trang chủ <span aria-hidden="true">↗</span></a>
            <a class="lp-button lp-button-glass" href="/tai-game">Khám phá tải game <span aria-hidden="true">→</span></a>
        </div>
        <a class="lp-error-login" href="/dang-nhap">Đăng nhập tài khoản</a>
    </section>
</main>
</body>
</html>
