<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/topup_core.php';
require_once __DIR__ . '/_layout.php';
$user=kp_topup_user();$cfg=kp_topup_cfg();$rates=kp_topup_rates();
kp_topup_page_start('Tổng quan','index',(string)$user['username']);?>
<section class="tp-hero"><span class="tp-kicker"><i></i> TÀI NGUYÊN KPAH</span><h1>Chọn cách nạp <em>của bạn.</em></h1><p class="tp-lead">Nạp thẻ cào, xem lịch sử giao dịch và theo dõi bảng xếp hạng. Mỗi chức năng có trang riêng để thao tác nhanh trên cả máy tính và điện thoại.</p></section>
<section class="tp-section"><div class="tp-section-heading"><div><span class="tp-eyebrow">DỊCH VỤ</span><h2>Bạn muốn làm gì?</h2></div></div>
<div class="tp-grid">
<a class="tp-card" href="card.php"><span class="tp-icon">✦</span><h3>Nạp thẻ cào</h3><p>Gửi mã thẻ để đối tác kiểm tra. Chọn Lượng thường hoặc Lượng khóa trước khi gửi.</p><span class="tp-card-cta"><?= $cfg['card_ready'] ? 'Mở nạp thẻ' : 'Xem trang nạp thẻ' ?> <strong>↗</strong></span></a>
<a class="tp-card is-disabled" href="bank.php"><span class="tp-icon purple">◇</span><h3>Nạp ngân hàng</h3><p>Chức năng chuyển khoản tự động sẽ được tích hợp sau. Chưa nhận thanh toán qua ngân hàng ở trang này.</p><span class="tp-card-cta">Xem trạng thái <strong>↗</strong></span></a>
<a class="tp-card" href="history.php"><span class="tp-icon green">◷</span><h3>Lịch sử nạp</h3><p>Tra cứu từng giao dịch của tài khoản, biết giao dịch nào đang xử lý hoặc cần đối soát.</p><span class="tp-card-cta">Lịch sử của tôi <strong>↗</strong></span></a>
<a class="tp-card" href="top.php"><span class="tp-icon">♛</span><h3>Top nạp Lượng</h3><p>Xem bảng xếp hạng theo thống kê Lượng đã nạp trong game, nếu có dữ liệu.</p><span class="tp-card-cta">Xem xếp hạng <strong>↗</strong></span></a>
</div></section>
<div class="tp-panel"><span class="tp-eyebrow">TỶ LỆ QUY ĐỔI</span><h2>Không cần mua theo gói</h2><div class="tp-summary" style="margin-top:17px;grid-template-columns:repeat(2,minmax(0,1fr))"><div><small>Mỗi 10.000 VNĐ · Lượng thường</small><strong><?= number_format((int)$rates['luong'],0,',','.') ?></strong></div><div><small>Mỗi 10.000 VNĐ · Lượng khóa</small><strong><?= number_format((int)$rates['luonglock'],0,',','.') ?></strong></div></div><p class="tp-muted" style="margin-top:15px">Tỷ lệ có thể được quản trị viên điều chỉnh. Giao dịch thẻ được tính theo tỷ lệ tại thời điểm gửi.</p></div>
<?php kp_topup_page_end(); ?>
