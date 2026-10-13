<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/topup_core.php';
require_once __DIR__ . '/_layout.php';
$user=kp_topup_user();$cfg=kp_topup_cfg();$rates=kp_topup_rates();
kp_topup_page_start('Tổng quan','index',(string)$user['username']);?>
<section class="tp-hero"><span class="tp-kicker"><i></i> TÀI NGUYÊN KPAH</span><h1>Nạp Lượng <em>cho hành trình mới.</em></h1><p class="tp-lead">Nạp thẻ, xem đơn đã gửi hay ngó bảng Top — chọn mục bạn cần bên dưới.</p></section>
<section class="tp-section"><div class="tp-section-heading"><div><span class="tp-eyebrow">DỊCH VỤ</span><h2>Bạn muốn làm gì?</h2></div></div>
<div class="tp-grid">
<a class="tp-card" href="/nap/the"><span class="tp-icon">✦</span><h3>Nạp thẻ cào</h3><p>Gửi mã thẻ để đối tác kiểm tra. Chọn Lượng thường hoặc Lượng khóa trước khi gửi.</p><span class="tp-card-cta"><?= $cfg['card_ready'] ? 'Mở nạp thẻ' : 'Xem trang nạp thẻ' ?> <strong>↗</strong></span></a>
<a class="tp-card is-disabled" href="/nap/ngan-hang"><span class="tp-icon purple">◇</span><h3>Nạp ngân hàng</h3><p>Chuyển khoản chưa mở. Khi nào sẵn sàng, bạn sẽ thấy ở đây.</p><span class="tp-card-cta">Xem trạng thái <strong>↗</strong></span></a>
<a class="tp-card" href="/nap/lich-su"><span class="tp-icon green">◷</span><h3>Lịch sử nạp</h3><p>Xem lại thẻ đã gửi và tình trạng xử lý.</p><span class="tp-card-cta">Lịch sử của tôi <strong>↗</strong></span></a>
<a class="tp-card" href="/bang-xep-hang?type=nap"><span class="tp-icon">♛</span><h3>Top nạp Lượng</h3><p>Ai đang dẫn đầu Top nạp Lượng?</p><span class="tp-card-cta">Xem xếp hạng <strong>↗</strong></span></a>
</div></section>
<div class="tp-panel"><span class="tp-eyebrow">TỶ LỆ QUY ĐỔI</span><h2>Nạp theo mệnh giá bạn chọn</h2><div class="tp-summary tp-summary--two tp-mt-17"><div><small>Mỗi 10.000 VNĐ · Lượng thường</small><strong><?= number_format((int)$rates['luong'],0,',','.') ?></strong></div><div><small>Mỗi 10.000 VNĐ · Lượng khóa</small><strong><?= number_format((int)$rates['luonglock'],0,',','.') ?></strong></div></div><p class="tp-muted tp-mt-15">Tỷ lệ có thể được quản trị viên điều chỉnh. Giao dịch thẻ được tính theo tỷ lệ tại thời điểm gửi.</p></div>
<?php kp_topup_page_end(); ?>
