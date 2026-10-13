<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/topup_core.php';
require_once __DIR__ . '/_layout.php';
$user=kp_topup_user();$orders=kp_topup_recent('orders',(int)$user['id'],100);
kp_topup_page_start('Lịch sử nạp','history',(string)$user['username']);?>
<section class="tp-hero"><span class="tp-kicker"><i></i> LỊCH SỬ GIAO DỊCH</span><h1>Theo dõi <em>đơn nạp.</em></h1><p class="tp-lead">Danh sách chỉ hiển thị giao dịch thuộc tài khoản đang đăng nhập. Bạn sẽ thấy rõ trạng thái đã gửi, đang xử lý, chờ cấp hay cần đối soát.</p></section>
<section class="tp-section"><div class="tp-panel"><div class="tp-panel-head"><div><span class="tp-eyebrow">ĐƠN HÀNG</span><h2>Giao dịch gần đây</h2></div><span class="tp-chip"><?= count($orders) ?> giao dịch</span></div>
<?php if(!$orders): ?><div class="tp-alert warning">Bạn chưa có giao dịch nạp thẻ. <a class="tp-support" href="card.php">Đến trang nạp thẻ →</a></div>
<?php else: ?><div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Mã</th><th>Thời gian</th><th>Nhà mạng</th><th>Mệnh giá</th><th>Nhận</th><th>Trạng thái</th></tr></thead><tbody>
<?php foreach($orders as $order): ?>
<tr><td><b>#<?= kp_e(strtoupper(substr((string)$order['id'],0,10))) ?></b></td><td><?= date('d/m/Y H:i',(int)$order['created_at']) ?></td><td><?= kp_e($order['telco']) ?></td><td><?= number_format((int)$order['amount'],0,',','.') ?>đ</td><td><?= number_format((int)$order['units'],0,',','.') ?> <?= ($order['currency']??'')==='luonglock'?'Lượng khóa':'Lượng thường' ?></td><td><span class="tp-pill <?= kp_e($order['status']) ?>"><?= kp_e(kp_topup_status_label($order['status'])) ?></span></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?>
<p class="tp-muted tp-mt-16">Nếu thẻ đã được gửi nhưng trạng thái chưa cập nhật, đừng gửi thẻ lại. Hãy liên hệ quản trị viên bằng mã giao dịch để đối soát.</p>
</div></section><?php kp_topup_page_end(); ?>
