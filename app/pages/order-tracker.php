<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/player_portal.php';
require_once __DIR__.'/../includes/topup_core.php';
require_once __DIR__.'/../includes/bank_payos.php';
$user=kp_player_account();$cards=[];$bank=[];$error='';
try {$cards=kp_topup_recent('orders',(int)$user['id'],30);}catch(Throwable $e){kp_log('tracker card orders failed',$e);$error='Chưa tải được đơn nạp thẻ.';}
try {
 $st=kp_db()->prepare('SELECT order_code,amount,status,created_at,paid_at,updated_at
                        FROM kp_bank_orders WHERE user_id=? ORDER BY id DESC LIMIT 30');
 $st->execute([(int)$user['id']]);$bank=$st->fetchAll(PDO::FETCH_ASSOC);
}catch(Throwable $e){kp_log('tracker bank orders failed',$e);$error=trim($error.' Chưa tải được đơn ngân hàng.');}
kp_player_head('Theo dõi đơn nạp','orders',$user);
?>
<section class="uc-heading"><span>GIAO DỊCH CỦA BẠN</span><h1>Theo dõi đơn nạp</h1><p>Xem trạng thái thực tế của các đơn nạp, không cần ghi lại mã thẻ hay thông tin thanh toán.</p></section>
<?php if($error):?><div class="uc-alert"><?=kp_e($error)?></div><?php endif;?>
<?php if(!$cards&&!$bank):?><div class="uc-empty">Chưa có đơn nạp gần đây.</div><?php endif;?>
<div class="uc-list">
<?php foreach($bank as $r):
$status=(string)$r['status'];
$done=in_array($status,['completed','wallet_credited'],true);
$paid=$done||in_array($status,['paid_waiting_delivery','wallet_crediting','crediting','queued','needs_review'],true);
?>
<article class="uc-record"><div><strong>Chuyển khoản #<?=kp_e($r['order_code'])?></strong><small><?=kp_e($r['created_at'])?> · <?=number_format((int)$r['amount'],0,',','.')?> VNĐ</small><p>Trạng thái: <b><?=kp_e(['completed'=>'Hoàn tất','wallet_credited'=>'Đã cộng ví','pending'=>'Chờ thanh toán','wallet_pending'=>'Chờ thanh toán','creating'=>'Đang tạo đơn','wallet_creating'=>'Đang tạo đơn','queued'=>'Chờ game xử lý','needs_review'=>'Đang đối soát','paid_waiting_delivery'=>'Đã thanh toán, chờ cấp','crediting'=>'Đang cấp','wallet_crediting'=>'Đang cộng ví'][$status]??'Đang xử lý')?></b></p>
<div class="uc-progress"><span class="is-done">Đã tạo</span><span class="<?= $paid?'is-done':'' ?>">Đã xác minh thanh toán</span><span class="<?= $done?'is-done':'' ?>">Hoàn tất</span></div></div></article>
<?php endforeach;?>
<?php foreach($cards as $r):
$status=(string)($r['status']??'');$complete=in_array($status,['completed','wallet_credited','success'],true);
?><article class="uc-record"><div><strong>Thẻ cào #<?=kp_e(substr((string)$r['id'],0,10))?></strong><small><?=date('d/m/Y H:i',(int)$r['created_at'])?> · <?=number_format((int)$r['amount'],0,',','.')?> VNĐ</small><p>Trạng thái: <?=kp_e(kp_topup_status_label($status))?></p>
<div class="uc-progress"><span class="is-done">Đã tạo</span><span class="<?= $complete?'is-done':'' ?>">Đã xử lý</span></div></div></article><?php endforeach;?>
</div><p class="uc-note">Các bước hiển thị dựa trên trạng thái được lưu, không tự coi đơn chưa xử lý là thanh toán thành công. <a href="/lich-su-nap">Xem bảng lịch sử đầy đủ →</a></p>
<?php kp_player_foot();?>
