<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/topup_core.php';
require_once __DIR__ . '/_layout.php';
$user=kp_topup_user();$orders=kp_topup_recent('orders',(int)$user['id'],100);
$bankOrders=[];$bankError=false;
try{$st=kp_db()->prepare('SELECT order_code,amount,currency,units,status,created_at,paid_at FROM kp_bank_orders WHERE user_id=? ORDER BY id DESC LIMIT 100');$st->execute([(int)$user['id']]);$bankOrders=$st->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){kp_log('player payment history bank lookup failed',$e);$bankError=true;}
kp_topup_page_start('Lịch sử nạp','history',(string)$user['username']);?>
<section class="tp-hero"><span class="tp-kicker"><i></i> LỊCH SỬ GIAO DỊCH</span><h1>Theo dõi <em>đơn nạp.</em></h1><p class="tp-lead">Danh sách chỉ hiển thị giao dịch thuộc tài khoản đang đăng nhập. Bạn sẽ thấy rõ trạng thái đã gửi, đang xử lý, chờ cấp hay cần đối soát.</p></section>
<section class="tp-section"><div class="tp-panel"><div class="tp-panel-head"><div><span class="tp-eyebrow">ĐƠN HÀNG</span><h2>Giao dịch gần đây</h2></div><span class="tp-chip"><?= count($orders)+count($bankOrders) ?> giao dịch</span></div>
<?php if(!$orders && !$bankOrders): ?><div class="tp-alert warning">Bạn chưa có giao dịch nạp tiền. <a class="tp-support" href="/nap-the">Đến trang nạp thẻ →</a></div>
<?php endif; ?>
<?php if($orders): ?><div class="tp-panel-head tp-mt-19"><div><span class="tp-eyebrow">NẠP THẺ CÀO</span><h2>Lịch sử thẻ cào</h2></div></div><div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Mã</th><th>Thời gian</th><th>Nhà mạng</th><th>Mệnh giá</th><th>Sau chiết khấu</th><th>Nhận</th><th>Trạng thái</th></tr></thead><tbody>
<?php foreach($orders as $order): ?>
<tr><td><b>#<?= kp_e(strtoupper(substr((string)$order['id'],0,10))) ?></b></td><td><?= date('d/m/Y H:i',(int)$order['created_at']) ?></td><td><?= kp_e($order['telco']) ?></td><td><?= number_format((int)$order['amount'],0,',','.') ?>đ</td><td><?= number_format((int)($order['net_amount'] ?? $order['amount']),0,',','.') ?>đ<?php if(isset($order['discount_percent'])): ?> <small>(-<?= (int)$order['discount_percent'] ?>%)</small><?php endif; ?></td><td><?= number_format((int)$order['units'],0,',','.') ?> <?= ($order['currency']??'')==='luonglock'?'Lượng khóa':'Lượng thường' ?></td><td><span class="tp-pill <?= kp_e($order['status']) ?>"><?= kp_e(kp_topup_status_label($order['status'])) ?></span></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?>

<?php if($bankOrders): ?>
<div class="tp-panel-head tp-mt-19"><div><span class="tp-eyebrow">CHUYỂN KHOẢN PAYOS</span><h2>Lịch sử nạp ngân hàng</h2></div><span class="tp-chip"><?= count($bankOrders) ?> đơn</span></div>
<div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Mã đơn</th><th>Thời gian</th><th>VNĐ</th><th>Nhận</th><th>Trạng thái</th></tr></thead><tbody>
<?php foreach($bankOrders as $b): ?>
<tr><td><b>#<?=kp_e($b['order_code'])?></b></td><td><?=kp_e($b['created_at'])?></td><td><?=number_format((int)$b['amount'],0,',','.')?>đ</td><td><?=number_format((int)$b['units'],0,',','.')?> <?= $b['currency']==='luonglock'?'Lượng khóa':'Lượng mở' ?></td><td><span class="tp-pill <?=kp_e($b['status'])?>"><?=kp_e(['completed'=>'Đã cộng vào nhân vật','queued'=>'Đã chuyển hàng chờ game','paid_waiting_delivery'=>'Đã nhận tiền · chờ cấp','needs_review'=>'Cần kiểm tra cấp Lượng','crediting'=>'Đang đối soát cấp Lượng','pending'=>'Chờ thanh toán','creating'=>'Đang tạo đơn'][$b['status']]??'Đang xử lý')?></span></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php endif; ?>
<?php if($bankError):?><div class="tp-alert warning">Chưa đọc được lịch sử chuyển khoản. Vui lòng thử lại sau hoặc liên hệ quản trị.</div><?php endif;?>
<p class="tp-muted tp-mt-16">Nếu thẻ đã được gửi nhưng trạng thái chưa cập nhật, đừng gửi thẻ lại. Hãy liên hệ quản trị viên bằng mã giao dịch để đối soát.</p>
</div></section><?php kp_topup_page_end(); ?>
