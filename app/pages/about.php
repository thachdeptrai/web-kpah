<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/public_landing.php';
kp_send_security_headers(true);
header('Content-Type: text/html; charset=utf-8');
kp_public_page_start('Giới thiệu','about','Khám phá thế giới, lối chơi và cộng đồng NGHIỆN KPAH.');
kp_public_page_hero('GIỚI THIỆU','Khí Phách <em>Anh Hùng</em>','Tải game, tạo tài khoản và tham gia cộng đồng NGHIỆN KPAH.','⚔');
?>
<section class="lp-section lp-inner-content"><div class="lp-container">
    <div class="lp-adventure-grid">
        <article class="lp-adventure-card lp-adventure-card-large" data-landing-reveal><div class="lp-adventure-copy"><span class="lp-card-label">NGƯỜI CHƠI MỚI</span><h3>Bắt đầu từ tài khoản của bạn</h3><p>Đăng ký trên website, chọn bản tải phù hợp với thiết bị và dùng tài khoản đó để vào game.</p><a href="/dang-ky">Đăng ký tài khoản <span>→</span></a></div><img src="/assets/img/auth-register-adventure.webp" width="220" height="275" alt="Nữ cung thủ KPAH" loading="lazy"></article>
        <article class="lp-mini-feature lp-mini-feature-blue" data-landing-reveal><span>⚔</span><div><h3>Xem bảng xếp hạng</h3><p>Theo dõi thứ hạng nhân vật tại <a href="/bang-xep-hang">bảng xếp hạng</a>.</p></div></article>
        <article class="lp-mini-feature lp-mini-feature-gold" data-landing-reveal><span><img class="lp-about-zalo-icon" src="/assets/img/zalo-official.svg" width="30" height="30" alt="" aria-hidden="true" loading="lazy" decoding="async"></span><div><h3>Tham gia cộng đồng</h3><p>Hỏi cách chơi và tìm bang hội trong <a href="/cong-dong">nhóm người chơi</a>.</p></div></article>
    </div>
    <div class="lp-feature-strip" data-landing-reveal><article><b>01</b><h3>Bản cài đặt</h3><p>Xem các nền tảng được hỗ trợ tại <a href="/tai-game">trang tải game</a>.</p></article><article><b>02</b><h3>Giftcode</h3><p>Xem phần thưởng và sao chép mã tại <a href="/giftcode">trang giftcode</a>.</p></article><article><b>03</b><h3>Quản lý tài khoản</h3><p>Đăng nhập để xem thông tin tài khoản và nhân vật của bạn.</p></article></div>
</div></section>
<?php kp_public_page_end(); ?>
