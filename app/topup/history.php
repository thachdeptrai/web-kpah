<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/topup_core.php';
require_once __DIR__ . '/../includes/bank_payos.php';
require_once __DIR__ . '/_layout.php';
$user=kp_topup_user();
// The user's history can confirm stale pending cards without visiting admin.
// Provider check is throttled (120s/order) and never re-submits PIN/serial.
try{kp_topup_auto_recheck_cards((int)$user['id'],1);}
catch(Throwable $e){kp_log('player card status refresh deferred',$e);}
$orders=kp_topup_recent('orders',(int)$user['id'],100);
try{kp_bank_finalize_legacy_wallet_statuses(100,(int)$user['id']);}catch(Throwable $e){kp_log('wallet status history sync deferred',$e);}
$bankOrders=[];$bankError=false;
try{$st=kp_db()->prepare('SELECT order_code,amount,currency,units,status,created_at,paid_at,provider_reference FROM kp_bank_orders WHERE user_id=? ORDER BY id DESC LIMIT 100');$st->execute([(int)$user['id']]);$bankOrders=$st->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){kp_log('player payment history bank lookup failed',$e);$bankError=true;}
kp_topup_page_start('Lịch sử nạp','history',(string)$user['username']);?>
<section class="tp-hero"><span class="tp-kicker"><i></i> LỊCH SỬ GIAO DỊCH</span><h1>Theo dõi <em>đơn nạp.</em></h1><p class="tp-lead">Danh sách chỉ hiển thị giao dịch thuộc tài khoản đang đăng nhập. Bạn có thể xem đầy đủ trạng thái nạp VNĐ, các giao dịch cũ và lệnh cần đối soát.</p></section>
<section class="tp-section"><div class="tp-panel"><div class="tp-panel-head"><div><span class="tp-eyebrow">ĐƠN HÀNG</span><h2>Giao dịch gần đây</h2></div><span class="tp-chip"><?= count($orders)+count($bankOrders) ?> giao dịch</span></div>
<?php if(!$orders && !$bankOrders): ?><div class="tp-alert warning">Bạn chưa có giao dịch nạp tiền. <a class="tp-support" href="/nap-the">Đến trang nạp thẻ →</a></div>
<?php endif; ?>
<?php if($orders): ?><div class="tp-panel-head tp-mt-19"><div><span class="tp-eyebrow">NẠP THẺ CÀO</span><h2>Lịch sử thẻ cào</h2></div></div><div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Mã</th><th>Thời gian</th><th>Nhà mạng</th><th>Mệnh giá</th><th>Sau chiết khấu</th><th>Nhận</th><th>Trạng thái</th></tr></thead><tbody>
<?php foreach($orders as $order): ?>
<tr><td><b>#<?= kp_e(strtoupper(substr((string)$order['id'],0,10))) ?></b></td><td><?= date('d/m/Y H:i',(int)$order['created_at']) ?></td><td><?= kp_e($order['telco']) ?></td><td><?= number_format((int)$order['amount'],0,',','.') ?>đ</td><td><?= number_format((int)($order['net_amount'] ?? $order['amount']),0,',','.') ?>đ<?php if(isset($order['discount_percent'])): ?> <small>(-<?= (int)$order['discount_percent'] ?>%)</small><?php endif; ?></td><td><?php if(($order['delivery_mode']??'game')==='wallet'):?><?php if(($order['status']??'')==='wallet_credited'):?><?=number_format((int)($order['net_amount']??0),0,',','.')?>đ ví<?php else:?>Chưa cộng<?php endif;?><?php else:?><?= number_format((int)$order['units'],0,',','.') ?> <?= ($order['currency']??'')==='luonglock'?'Lượng khóa':'Lượng thường' ?><?php endif;?></td><td><span class="tp-pill <?= kp_e($order['status']) ?>"><?= kp_e(kp_topup_status_label($order['status'])) ?></span></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?>

<?php if($bankOrders): ?>
<div class="tp-panel-head tp-mt-19"><div><span class="tp-eyebrow">CHUYỂN KHOẢN PAYOS</span><h2>Lịch sử nạp ngân hàng</h2></div><span class="tp-chip"><?= count($bankOrders) ?> đơn</span></div>
<div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Mã đơn</th><th>Thời gian</th><th>VNĐ</th><th>Nhận</th><th>Trạng thái</th></tr></thead><tbody>
<?php foreach($bankOrders as $b): ?>
<tr><td><b>#<?=kp_e($b['order_code'])?></b></td><td><?=kp_e($b['created_at'])?></td><td><?=number_format((int)$b['amount'],0,',','.')?>đ</td><td><?php if(kp_bank_is_wallet_order($b)):?><?=number_format((int)$b['amount']+intdiv((int)$b['amount']*kp_bank_order_bonus($b),100),0,',','.')?>đ ví<?php else:?><?=number_format((int)$b['units'],0,',','.')?> <?= $b['currency']==='luonglock'?'Lượng khóa':'Lượng mở' ?><?php endif;?></td><td><span class="tp-pill <?=kp_e($b['status'])?>"><?=kp_e(['wallet_credited'=>'Đã cộng VNĐ vào ví','wallet_pending'=>'Chờ thanh toán','wallet_creating'=>'Đang tạo QR','wallet_crediting'=>'Đã nhận tiền · đang cộng ví','completed'=>kp_bank_is_wallet_order($b)?'Hoàn thành · Đã cộng VNĐ':'Hoàn thành · Đã cấp Lượng','queued'=>'Đã chuyển hàng chờ game','paid_waiting_delivery'=>'Đã nhận tiền · chờ cấp','needs_review'=>'Cần kiểm tra cấp Lượng','crediting'=>'Đang đối soát cấp Lượng','pending'=>'Chờ thanh toán','creating'=>'Đang tạo đơn'][$b['status']]??'Đang xử lý')?></span></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php endif; ?>
<?php if($bankError):?><div class="tp-alert warning">Chưa đọc được lịch sử chuyển khoản. Vui lòng thử lại sau hoặc liên hệ quản trị.</div><?php endif;?>
<p class="tp-muted tp-mt-16">Trạng thái thẻ đang chờ sẽ được hệ thống kiểm tra lại tự động khi nhà cung cấp trả kết quả. Không gửi lại cùng mã thẻ. Chỉ liên hệ quản trị với mã giao dịch nếu trạng thái cần đối soát hoặc bị chờ quá lâu.</p>
</div></section><?php kp_topup_page_end(); ?>
