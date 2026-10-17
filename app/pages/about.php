<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/public_landing.php';
kp_send_security_headers(true);
header('Content-Type: text/html; charset=utf-8');
kp_public_page_start('Giới thiệu','about','Khám phá thế giới, lối chơi và cộng đồng NGHIỆN KPAH.');
kp_public_page_hero('THẾ GIỚI NGHIỆN KPAH','Một server,<br><em>vạn câu chuyện</em>','Sống lại ký ức tuổi thơ trong một thế giới võ hiệp nhiều màu sắc, nơi đồng đội và hành trình quan trọng hơn những con số.','⚔');
?>
<section class="lp-section lp-inner-content"><div class="lp-container">
    <div class="lp-adventure-grid">
        <article class="lp-adventure-card lp-adventure-card-large" data-landing-reveal><div class="lp-adventure-copy"><span class="lp-card-label">HÀNH TRÌNH TUỔI THƠ</span><h3>Khám phá, trưởng thành<br>và trở thành huyền thoại</h3><p>Từ nhiệm vụ đầu tiên tới những trận chiến bang hội, mỗi lựa chọn đều tạo nên dấu ấn riêng của bạn.</p><a href="/dang-ky">Bắt đầu hành trình <span>→</span></a></div><img src="/assets/img/rank-archer.webp" width="220" height="247" alt="Nữ cung thủ KPAH" loading="lazy"></article>
        <article class="lp-mini-feature lp-mini-feature-blue" data-landing-reveal><span>⚔</span><div><h3>Tranh tài đỉnh cao</h3><p>Leo hạng, đấu trường và những trận chiến cần cả kỹ năng lẫn đồng đội.</p></div></article>
        <article class="lp-mini-feature lp-mini-feature-gold" data-landing-reveal><span>♛</span><div><h3>Bang hội gắn kết</h3><p>Tìm chiến hữu, xây cộng đồng và cùng nhau làm nên kỳ tích.</p></div></article>
    </div>
    <div class="lp-feature-strip" data-landing-reveal><article><b>01</b><h3>Thế giới rộng mở</h3><p>Nhiệm vụ, bản đồ và thử thách liên tục chờ khám phá.</p></article><article><b>02</b><h3>Nhân vật có chiều sâu</h3><p>Phát triển trang bị, kỹ năng và phong cách chiến đấu riêng.</p></article><article><b>03</b><h3>Cộng đồng là cốt lõi</h3><p>Kết nối người chơi cũ và mới trong một mái nhà thân thiện.</p></article></div>
</div></section>
<?php kp_public_page_end(); ?>
