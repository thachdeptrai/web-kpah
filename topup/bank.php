<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/topup_core.php';
require_once __DIR__ . '/_layout.php';
$user=kp_topup_user();kp_topup_page_start('Nạp ngân hàng','bank',(string)$user['username']);?>
<section class="tp-hero"><span class="tp-kicker"><i></i> NẠP CHUYỂN KHOẢN</span><h1>Ngân hàng <em>sẽ mở sau.</em></h1><p class="tp-lead">Khu nạp ngân hàng đang được chuẩn bị. Hiện trang này không tạo mã chuyển khoản, không hiện số tài khoản nhận và không ghi nhận tiền tự động.</p></section>
<div class="tp-panel"><div class="tp-panel-head"><div><span class="tp-eyebrow">CHƯA KÍCH HOẠT</span><h2>Chuyển sang nạp thẻ</h2></div><span class="tp-chip">COMING SOON</span></div><p class="tp-muted">Trong lúc chờ kết nối ngân hàng, bạn có thể mở trang nạp thẻ hoặc xem lịch sử của mình.</p><div class="tp-actions" style="margin-top:20px"><a class="tp-button" href="card.php">Đến nạp thẻ ↗</a><a class="tp-button-secondary" href="history.php">Xem lịch sử</a></div></div>
<?php kp_topup_page_end(); ?>
