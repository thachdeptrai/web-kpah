<?php
/**
 * Trang chủ công khai NGHIỆN KPAH.
 * Chỉ tổng hợp dữ liệu đọc; không thay đổi luồng tài khoản hay database game.
 */
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/game_downloads.php';
require_once __DIR__ . '/../includes/giftcodes.php';
require_once __DIR__ . '/../includes/rankings.php';

kp_send_security_headers(true);
header('Content-Type: text/html; charset=utf-8');

$isLoggedIn = !empty($_SESSION['kp_user_id']);
$loginUrl = '/dang-nhap';
$registerUrl = '/dang-ky';
$accountUrl = '/tai-khoan';
$downloadCatalog = kp_game_download_catalog();
$downloadPlatforms = [
    ['key' => 'android', 'name' => 'Android', 'icon' => 'A', 'detail' => 'APK · Điện thoại & máy tính bảng'],
    ['key' => 'windows', 'name' => 'Windows', 'icon' => 'W', 'detail' => 'PC · Bản dành cho Windows'],
    ['key' => 'java', 'name' => 'Java / JAR', 'icon' => 'J', 'detail' => 'JAR · Máy có Java Runtime'],
    ['key' => 'ios', 'name' => 'iPhone / iPad', 'icon' => 'i', 'detail' => 'IPA · Thiết bị iOS'],
];
$downloadAvailable = false;
foreach ($downloadPlatforms as &$platform) {
    $release = $downloadCatalog[$platform['key']];
    $platform['enabled'] = $release['enabled'];
    $platform['url'] = !$release['enabled'] ? '' :
        ($release['source'] === 'file'
            ? '/tai-game/tep?nen-tang=' . $platform['key']
            : $release['url']);
    if ($platform['url'] !== '') $downloadAvailable = true;
}
unset($platform);

$supportUrl = kp_safe_url(kp_config('links.support', ''));
$communityUrl = 'https://zalo.me/g/zmyedl5en7sldmd9el1z';
$accountCount = null;
$publicGiftcodes = [];
$featuredHeroes = [];
$rankingMetric = 'Sức mạnh';

try {
    $db = kp_db();
    $accountCount = (int) $db->query('SELECT COUNT(*) FROM team_user')->fetchColumn();
    $publicGiftcodes = kp_giftcode_public_list($db, 6);
    try {
        $featuredHeroes = array_slice(kp_rankings_fetch('strength'), 0, 5);
    } catch (Throwable $rankingError) {
        $rankingMetric = 'Cấp độ';
        $featuredHeroes = array_slice(kp_rankings_fetch('level'), 0, 5);
    }
} catch (Throwable $e) {
    kp_log('không tải được dữ liệu động trang chủ', $e);
}

$readyDownloads = 0;
foreach ($downloadPlatforms as $platform) {
    if ($platform['url'] !== '') $readyDownloads++;
}

$highlights = [
    [
        'tone' => 'violet',
        'label' => 'THẾ GIỚI KPAH',
        'title' => 'Một hành trình mới đang chờ bạn',
        'copy' => 'Lập tổ đội, khám phá bản đồ và ghi tên mình lên bảng xếp hạng.',
        'href' => '#gioi-thieu',
        'action' => 'Khám phá ngay',
    ],
    [
        'tone' => 'cyan',
        'label' => 'BẢN TẢI',
        'title' => $readyDownloads > 0 ? $readyDownloads . ' nền tảng đã sẵn sàng' : 'Bản tải đang được chuẩn bị',
        'copy' => $readyDownloads > 0 ? 'Chọn đúng thiết bị, tải game và đăng nhập trong vài phút.' : 'Đội ngũ vận hành đang hoàn thiện các bản cài đặt.',
        'href' => '#tai-game',
        'action' => 'Xem bản tải',
    ],
    [
        'tone' => 'rose',
        'label' => 'QUÀ TẶNG',
        'title' => $publicGiftcodes ? count($publicGiftcodes) . ' giftcode đang hiển thị' : 'Đợt quà mới sắp xuất hiện',
        'copy' => $publicGiftcodes ? 'Sao chép mã công khai và nhập trực tiếp trong game.' : 'Theo dõi trang chủ để không bỏ lỡ giftcode tiếp theo.',
        'href' => '#giftcode',
        'action' => 'Nhận quà',
    ],
];
?><!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#dff2ff">
    <meta name="description" content="NGHIỆN KPAH — cổng thông tin, tải game, giftcode và cộng đồng dành cho người chơi KPAH.">
    <meta name="robots" content="index,follow">
    <title>NGHIỆN KPAH — Bước vào thế giới phiêu lưu</title>
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&amp;display=swap">
    <link rel="stylesheet" href="/assets/css/landing.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/landing.css') ?>">
    <link rel="stylesheet" href="/assets/css/site-responsive.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/site-responsive.css') ?>">
    <link rel="stylesheet" href="/assets/css/ui-system.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/ui-system.css') ?>">
</head>
<body class="landing-page">
<a class="lp-skip" href="#noi-dung">Đi đến nội dung chính</a>

<header class="lp-header" data-landing-header>
    <div class="lp-container lp-nav">
        <a class="lp-brand" href="/" aria-label="NGHIỆN KPAH — Trang chủ">
            <span class="lp-brand-mark"><img src="/assets/img/emblem.svg" width="52" height="52" alt=""></span>
            <span><strong>NGHIỆN KPAH</strong><small>THẾ GIỚI PHIÊU LƯU</small></span>
        </a>
        <button class="lp-menu-toggle" type="button" aria-expanded="false" aria-controls="lp-menu" data-landing-menu>
            <span></span><span></span><span></span><b class="lp-sr-only">Mở menu</b>
        </button>
        <nav class="lp-links" id="lp-menu" aria-label="Điều hướng chính">
            <a href="#gioi-thieu">Giới thiệu</a>
            <a href="#tai-game">Tải game</a>
            <a href="#giftcode">Giftcode</a>
            <a href="#tin-tuc">Tin tức</a>
            <a href="#cong-dong">Cộng đồng</a>
        </nav>
        <div class="lp-nav-actions">
            <?php if ($isLoggedIn): ?>
                <a class="lp-nav-account" href="<?= kp_e($accountUrl) ?>"><span>◎</span>Tài khoản</a>
            <?php else: ?>
                <a class="lp-nav-login" href="<?= kp_e($loginUrl) ?>">Đăng nhập</a>
                <a class="lp-button lp-button-small" href="<?= kp_e($registerUrl) ?>">Chơi ngay <span>→</span></a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main id="noi-dung">
    <section class="lp-hero" aria-labelledby="lp-title">
        <div class="lp-hero-sky" aria-hidden="true">
            <span class="lp-cloud lp-cloud-one"></span>
            <span class="lp-cloud lp-cloud-two"></span>
            <span class="lp-cloud lp-cloud-three"></span>
            <span class="lp-orb lp-orb-one"></span>
            <span class="lp-orb lp-orb-two"></span>
        </div>
        <div class="lp-container lp-hero-layout">
            <div class="lp-hero-copy" data-landing-reveal>
                <span class="lp-pill"><i></i> Máy chủ cộng đồng KPAH</span>
                <p class="lp-eyebrow">HÀNH TRÌNH HUYỀN THOẠI BẮT ĐẦU</p>
                <h1 id="lp-title">Bước vào thế giới<br><em>phiêu lưu bất tận</em></h1>
                <p class="lp-lead">Gặp lại ký ức tuổi thơ trong một thế giới KPAH sống động — nơi đồng đội sát cánh, thử thách đang chờ và huyền thoại mang tên bạn.</p>
                <div class="lp-hero-actions">
                    <a class="lp-button lp-button-primary" href="<?= kp_e($isLoggedIn ? $accountUrl : $registerUrl) ?>">
                        <?= $isLoggedIn ? 'Vào khu tài khoản' : 'Bắt đầu hành trình' ?> <span>→</span>
                    </a>
                    <a class="lp-button lp-button-glass" href="#tai-game"><span class="lp-play">↓</span> Tải game</a>
                </div>
                <div class="lp-hero-trust" aria-label="Thông tin nhanh">
                    <div class="lp-avatar-stack" aria-hidden="true"><span>Đ</span><span>P</span><span>K</span><span>+</span></div>
                    <p><strong><?= $accountCount !== null ? number_format($accountCount, 0, ',', '.') . '+' : 'Cộng đồng' ?></strong><span>người chơi cùng phiêu lưu</span></p>
                </div>
            </div>

            <div class="lp-hero-art" aria-hidden="true" data-landing-reveal>
                <span class="lp-magic-ring"></span>
                <span class="lp-art-glow"></span>
                <img class="lp-character lp-character-side lp-character-mage" src="/assets/img/rank-sorcerer.svg" width="220" height="247" alt="">
                <img class="lp-character lp-character-main" src="/assets/img/rank-guardian.svg" width="220" height="247" alt="">
                <img class="lp-character lp-character-side lp-character-archer" src="/assets/img/rank-archer.svg" width="220" height="247" alt="">
                <span class="lp-float-card lp-float-card-top"><i>✦</i><b>Thế giới mở</b><small>Tự do khám phá</small></span>
                <span class="lp-float-card lp-float-card-bottom"><i>♛</i><b>Bang hội</b><small>Chiến đấu cùng nhau</small></span>
                <span class="lp-island"></span>
            </div>
        </div>
        <div class="lp-container lp-stats" data-landing-reveal>
            <article><span class="lp-stat-icon lp-stat-violet">♟</span><div><strong><?= $accountCount !== null ? number_format($accountCount, 0, ',', '.') : '—' ?></strong><small>Tài khoản đã tạo</small></div></article>
            <article><span class="lp-stat-icon lp-stat-cyan">⌄</span><div><strong><?= $readyDownloads ?></strong><small>Nền tảng sẵn sàng</small></div></article>
            <article><span class="lp-stat-icon lp-stat-amber">✦</span><div><strong><?= count($publicGiftcodes) ?></strong><small>Giftcode công khai</small></div></article>
            <article><span class="lp-stat-icon lp-stat-rose">♥</span><div><strong>24/7</strong><small>Cộng đồng đồng hành</small></div></article>
        </div>
    </section>

    <section class="lp-section lp-intro-section" id="gioi-thieu">
        <div class="lp-container">
            <div class="lp-section-heading lp-section-heading-center" data-landing-reveal>
                <span class="lp-section-kicker">KHÁM PHÁ THẾ GIỚI</span>
                <h2>Một server, <em>vạn câu chuyện</em></h2>
                <p>Không chỉ là một trò chơi. Đây là nơi bạn tìm thấy đồng đội, chinh phục thử thách và viết nên hành trình của riêng mình.</p>
            </div>
            <div class="lp-adventure-grid">
                <article class="lp-adventure-card lp-adventure-card-large" data-landing-reveal>
                    <div class="lp-adventure-copy">
                        <span class="lp-card-label">THẾ GIỚI PHIÊU LƯU</span>
                        <h3>Khám phá, trưởng thành<br>và trở thành huyền thoại</h3>
                        <p>Từ những nhiệm vụ đầu tiên đến các trận chiến bang hội, mọi lựa chọn đều tạo nên dấu ấn của bạn trong thế giới KPAH.</p>
                        <a href="<?= kp_e($isLoggedIn ? $accountUrl : $registerUrl) ?>">Tạo câu chuyện của bạn <span>→</span></a>
                    </div>
                    <img src="/assets/img/rank-archer.svg" width="220" height="247" alt="Nữ cung thủ KPAH" loading="lazy">
                </article>
                <article class="lp-mini-feature lp-mini-feature-blue" data-landing-reveal><span>⚔</span><div><h3>Tranh tài đỉnh cao</h3><p>Leo hạng, đấu trường và những trận chiến không khoan nhượng.</p></div></article>
                <article class="lp-mini-feature lp-mini-feature-gold" data-landing-reveal><span>♛</span><div><h3>Bang hội gắn kết</h3><p>Tìm đồng đội, xây cộng đồng và cùng nhau làm nên kỳ tích.</p></div></article>
            </div>
        </div>
    </section>

    <section class="lp-section lp-download-section" id="tai-game">
        <div class="lp-container">
            <div class="lp-section-heading lp-section-heading-split" data-landing-reveal>
                <div><span class="lp-section-kicker">SẴN SÀNG LÊN ĐƯỜNG</span><h2>Chọn thiết bị,<br><em>vào game ngay</em></h2></div>
                <p>Không vòng vo, không rườm rà. Chọn đúng phiên bản cho thiết bị và bắt đầu cuộc phiêu lưu của bạn.</p>
            </div>
            <div class="lp-download-grid">
                <?php foreach ($downloadPlatforms as $platform): ?>
                <article class="lp-download-card<?= $platform['url'] !== '' ? ' is-ready' : '' ?>" data-landing-reveal>
                    <span class="lp-platform-icon lp-platform-<?= kp_e($platform['key']) ?>"><?= kp_e($platform['icon']) ?></span>
                    <div><span class="lp-availability"><?= $platform['url'] !== '' ? 'SẴN SÀNG' : 'ĐANG CẬP NHẬT' ?></span><h3><?= kp_e($platform['name']) ?></h3><p><?= kp_e($platform['detail']) ?></p></div>
                    <?php if ($platform['url'] !== ''): ?>
                        <a href="<?= kp_e($platform['url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="Tải NGHIỆN KPAH cho <?= kp_e($platform['name']) ?>">Tải xuống <span>↓</span></a>
                    <?php else: ?>
                        <span class="lp-download-disabled"><?= $platform['enabled'] ? 'Đang chuẩn bị file' : 'Tạm chưa mở tải' ?></span>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="lp-section lp-gift-section" id="giftcode">
        <div class="lp-container">
            <div class="lp-section-heading lp-section-heading-split" data-landing-reveal>
                <div><span class="lp-section-kicker">QUÀ TỪ THẾ GIỚI KPAH</span><h2>Giftcode dành cho <em>nhà phiêu lưu</em></h2></div>
                <p>Sao chép mã đang mở và nhập trực tiếp trong game. Số lượt còn lại được lấy từ hệ thống.</p>
            </div>
            <?php if ($publicGiftcodes): ?>
            <div class="lp-gift-grid">
                <?php foreach ($publicGiftcodes as $index => $gift): ?>
                <article class="lp-gift-card" data-landing-reveal>
                    <div class="lp-gift-visual tone-<?= ($index % 3) + 1 ?>"><span>✦</span><b>QUÀ<br>KPAH</b></div>
                    <div class="lp-gift-copy">
                        <div class="lp-gift-meta"><span>Giftcode công khai</span><small>Còn <?= number_format((int)$gift['limit_use'], 0, ',', '.') ?> lượt</small></div>
                        <h3><?= kp_e($gift['title'] !== '' ? $gift['title'] : 'Quà tặng nhà phiêu lưu') ?></h3>
                        <p><?= kp_e($gift['description'] !== '' ? $gift['description'] : 'Nhập mã trong game để nhận phần thưởng.') ?></p>
                        <div class="lp-gift-rewards">
                            <?php if ((int)$gift['xu'] > 0): ?><span><?= number_format((int)$gift['xu'],0,',','.') ?> Xu</span><?php endif; ?>
                            <?php if ((int)$gift['luong'] > 0): ?><span><?= number_format((int)$gift['luong'],0,',','.') ?> Lượng</span><?php endif; ?>
                            <?php if ((int)$gift['luongLock'] > 0): ?><span><?= number_format((int)$gift['luongLock'],0,',','.') ?> Lượng khóa</span><?php endif; ?>
                            <?php if ($gift['item'] !== null && $gift['item'] !== ''): ?><span>Quà vật phẩm</span><?php endif; ?>
                        </div>
                        <div class="lp-gift-code-line"><code><?= kp_e($gift['giftcode']) ?></code><button type="button" data-gift-code="<?= kp_e($gift['giftcode']) ?>" aria-label="Sao chép mã <?= kp_e($gift['giftcode']) ?>">Sao chép</button></div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="lp-empty-state" data-landing-reveal><span>✦</span><div><h3>Kho quà đang được làm mới</h3><p>Chưa có giftcode công khai ở thời điểm này. Giftcode mới sẽ tự xuất hiện tại đây khi được mở.</p></div></div>
            <?php endif; ?>
            <p class="lp-gift-note">Mỗi nhân vật chỉ nhận một lần với cùng mã. Đừng chia sẻ mật khẩu hoặc mã xác minh để đổi lấy giftcode.</p>
        </div>
    </section>

    <section class="lp-section lp-news-section" id="tin-tuc">
        <div class="lp-container">
            <div class="lp-section-heading lp-section-heading-split" data-landing-reveal>
                <div><span class="lp-section-kicker">TIN NỔI BẬT</span><h2>Đừng bỏ lỡ <em>điều mới</em></h2></div>
                <p>Các cập nhật quan trọng được tổng hợp từ trạng thái thật của website; không hiển thị thông báo giả.</p>
            </div>
            <div class="lp-news-grid">
                <?php foreach ($highlights as $highlight): ?>
                <article class="lp-news-card tone-<?= kp_e($highlight['tone']) ?>" data-landing-reveal>
                    <div class="lp-news-art"><span>✦</span><i></i></div>
                    <div class="lp-news-copy"><span><?= kp_e($highlight['label']) ?></span><h3><?= kp_e($highlight['title']) ?></h3><p><?= kp_e($highlight['copy']) ?></p><a href="<?= kp_e($highlight['href']) ?>"><?= kp_e($highlight['action']) ?> <b>→</b></a></div>
                </article>
                <?php endforeach; ?>
            </div>

            <div class="lp-ranking-panel" data-landing-reveal>
                <div class="lp-ranking-heading"><div><span class="lp-section-kicker">ANH HÙNG NỔI BẬT</span><h3>Bảng xếp hạng <?= kp_e(strtolower($rankingMetric)) ?></h3></div><a href="/bang-xep-hang">Xem toàn bộ <span>→</span></a></div>
                <?php if ($featuredHeroes): ?>
                <div class="lp-ranking-list">
                    <?php foreach ($featuredHeroes as $index => $hero): ?>
                    <article><span class="lp-rank-number rank-<?= $index + 1 ?>">#<?= $index + 1 ?></span><span class="lp-rank-avatar"><?= kp_e(strtoupper(substr((string)$hero['name'], 0, 1))) ?></span><div><strong><?= kp_e($hero['name']) ?></strong><small><?= kp_e($rankingMetric) ?></small></div><b><?= number_format((int)$hero['score'], 0, ',', '.') ?></b></article>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="lp-ranking-empty">Bảng xếp hạng sẽ xuất hiện khi máy chủ có dữ liệu phù hợp.</div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="lp-section lp-community-section" id="cong-dong">
        <div class="lp-container lp-community-card" data-landing-reveal>
            <div class="lp-community-art" aria-hidden="true"><span></span><img src="/assets/img/rank-sorcerer.svg" width="220" height="247" alt="" loading="lazy"></div>
            <div class="lp-community-copy"><span class="lp-section-kicker">KHÔNG PHIÊU LƯU MỘT MÌNH</span><h2>Gia nhập cộng đồng<br><em>NGHIỆN KPAH</em></h2><p>Tìm đồng đội, hỏi kinh nghiệm, cập nhật hoạt động và cùng anh em viết tiếp câu chuyện KPAH.</p><div class="lp-community-actions"><a class="lp-button lp-button-white" href="<?= kp_e($communityUrl) ?>" target="_blank" rel="noopener noreferrer">Vào nhóm Zalo <span>→</span></a><?php if ($supportUrl !== ''): ?><a class="lp-community-support" href="<?= kp_e($supportUrl) ?>">Kênh hỗ trợ</a><?php endif; ?></div><small>Không gửi mật khẩu hoặc mã xác minh cho bất kỳ ai.</small></div>
        </div>
    </section>

    <section class="lp-final-cta">
        <div class="lp-container" data-landing-reveal><img src="/assets/img/emblem.svg" width="88" height="88" alt=""><span class="lp-section-kicker">SẴN SÀNG CHƯA?</span><h2>Huyền thoại tiếp theo<br>có thể là <em>bạn</em></h2><p>Tạo tài khoản, chọn nhân vật và bắt đầu cuộc phiêu lưu ngay hôm nay.</p><div class="lp-hero-actions"><a class="lp-button lp-button-primary" href="<?= kp_e($isLoggedIn ? $accountUrl : $registerUrl) ?>"><?= $isLoggedIn ? 'Vào khu tài khoản' : 'Tạo tài khoản miễn phí' ?> <span>→</span></a><a class="lp-button lp-button-glass" href="#tai-game">Tải game</a></div></div>
    </section>
</main>

<footer class="lp-footer">
    <div class="lp-container lp-footer-main">
        <a class="lp-brand" href="/" aria-label="Về trang chủ"><span class="lp-brand-mark"><img src="/assets/img/emblem.svg" width="52" height="52" alt=""></span><span><strong>NGHIỆN KPAH</strong><small>THẾ GIỚI PHIÊU LƯU</small></span></a>
        <nav aria-label="Liên kết cuối trang"><a href="#gioi-thieu">Giới thiệu</a><a href="#tai-game">Tải game</a><a href="#giftcode">Giftcode</a><a href="/bang-xep-hang">Xếp hạng</a><a href="<?= kp_e($communityUrl) ?>" target="_blank" rel="noopener noreferrer">Cộng đồng</a></nav>
    </div>
    <div class="lp-container lp-footer-bottom"><span>© <?= date('Y') ?> NGHIỆN KPAH · Cổng thông tin cộng đồng người chơi</span><a href="#noi-dung">Lên đầu trang ↑</a></div>
</footer>

<script src="/assets/js/giftcodes.js?v=<?= (int)@filemtime(KP_ROOT.'/assets/js/giftcodes.js') ?>" defer></script>
<script src="/assets/js/landing.js?v=<?= (int)@filemtime(KP_ROOT.'/assets/js/landing.js') ?>" defer></script>
<script src="/assets/js/ui-system.js?v=<?= (int)@filemtime(KP_ROOT.'/assets/js/ui-system.js') ?>" defer></script>
</body>
</html>
