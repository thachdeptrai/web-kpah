<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/bank_payos.php';
require_once __DIR__.'/_layout.php';
$user=kp_topup_user();$cfg=kp_bank_cfg();$csrf=kp_csrf_token();$error='';$checkout='';$activeCode=0;
$bonusPercent=0;$walletReady=true;
try{$bonusPercent=kp_vnd_bonus_percent();}catch(Throwable $e){$walletReady=false;kp_log('bank wallet unavailable',$e);}
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
 try{
  $amount=filter_var($_POST['amount']??null,FILTER_VALIDATE_INT);
  if($amount===false)throw new RuntimeException('Mệnh giá không hợp lệ.');
  if(!$walletReady)throw new RuntimeException('Ví VNĐ đang nâng cấp, vui lòng thử lại sau.');
  $url=kp_bank_create($user,(int)$amount,(string)($_POST['csrf']??''));
  $checkout=$url;
  $find=kp_db()->prepare('SELECT order_code FROM kp_bank_orders WHERE user_id=? AND checkout_url=? ORDER BY id DESC LIMIT 1');
  $find->execute([(int)$user['id'],$url]);$activeCode=(int)$find->fetchColumn();
 }catch(Throwable $e){$error=$e instanceof RuntimeException?$e->getMessage():'Chưa tạo được yêu cầu. Thử lại sau.';}
}
$recent=[];
try{$st=kp_db()->prepare('SELECT order_code,amount,currency,units,status,checkout_url,created_at,delivery_mode,bonus_percent FROM kp_bank_orders WHERE user_id=? ORDER BY id DESC LIMIT 15');$st->execute([(int)$user['id']]);$recent=$st->fetchAll(PDO::FETCH_ASSOC);}
catch(Throwable $e){$error=$error?:'Chưa có bảng nạp ngân hàng. Chạy migration database/payos_bank.sql.';}
kp_topup_page_start('Nạp ngân hàng','bank',(string)$user['username']);?>
<section class="tp-hero"><span class="tp-kicker"><i></i> NẠP NGÂN HÀNG</span><h1>Thanh toán <em>VietQR.</em></h1><p class="tp-lead">Chỉ hiện khu vực QR sau khi bạn bấm Tạo QR thành công. Đơn chưa thanh toán hết hạn sau 10 phút. Sau khi payOS xác nhận, website cộng số dư VNĐ vào tài khoản, bao gồm thưởng ngân hàng. Bạn có thể đổi mốc Lượng riêng sau đó.</p></section>
<?php if($error):?><div class="tp-alert bad" role="alert"><?=kp_e($error)?></div><?php endif;?>
<?php if(isset($_GET['result'])):?><div class="tp-alert warning">Trang thanh toán đã chuyển về website. Chỉ kết quả tra cứu từ payOS mới xác nhận tiền về.</div><?php endif;?>
<section class="tp-section"><div class="tp-grid two"><div class="tp-panel"><h2>Tạo đơn nạp</h2><?php if(!$walletReady):?><div class="tp-alert warning">Chưa cài bảng ví VNĐ. Cần chạy database/vnd_wallet_upgrade.sql.</div><?php endif;?><?php if(!$cfg['ready']):?><div class="tp-alert warning">Chưa có thông tin kết nối payOS.</div><?php endif;?>
<form method="post" class="tp-form" id="bank-create-form"><input type="hidden" name="csrf" value="<?=kp_e($csrf)?>">
<label class="tp-field"><span>Mệnh giá</span><select class="tp-select" name="amount" required <?=(!$cfg['ready']||!$walletReady)?'disabled':''?>>
<?php foreach(kp_card_packages() as $vnd=>$pkg):?><option value="<?=$vnd?>"><?=number_format($vnd,0,',','.')?>đ</option><?php endforeach;?></select></label>
<p class="tp-muted">Thưởng nạp ngân hàng hiện tại: <strong>+<?= (int)$bonusPercent ?>%</strong>. Nạp 10.000đ được cộng <?=number_format(10000+intdiv(10000*$bonusPercent,100),0,',','.')?>đ vào ví.</p>
<button type="submit" class="tp-button" id="bank-create-button" <?=!$cfg['ready']?'disabled':''?>>Tạo QR thanh toán ↗</button></form></div>
<div class="tp-panel"><h2>Bảng tiền về ví VNĐ</h2><div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Nạp</th><th>Thưởng</th><th>Ví nhận</th></tr></thead><tbody>
<?php foreach(kp_card_packages() as $vnd=>$pkg):$bonus=intdiv($vnd*$bonusPercent,100);?><tr><td><?=number_format($vnd,0,',','.')?>đ</td><td>+<?=number_format($bonus,0,',','.')?>đ</td><td><?=number_format($vnd+$bonus,0,',','.')?>đ</td></tr><?php endforeach;?></tbody></table></div></div></div></div></section>
<?php if($checkout!==''):?>
<section class="tp-section tp-payment-section" id="thanh-toan">
 <div class="tp-payment-panel">
  <div class="tp-payment-heading">
   <div><span class="tp-eyebrow">PAYOS · CHUYỂN KHOẢN VIETQR</span><h2>Quét mã thanh toán</h2><p id="bank-payment-status" class="tp-muted" role="status" aria-live="polite">Đang tải QR. Trạng thái thanh toán được cập nhật tự động.</p></div>
   <a class="tp-button-secondary tp-payment-external" href="<?=kp_e($checkout)?>" target="_blank" rel="noopener noreferrer">Mở payOS ↗</a>
  </div>
  <div class="tp-payment-stage">
   <div id="payos-embed" data-checkout="<?=kp_e($checkout)?>" data-order="<?= (int)$activeCode ?>" class="tp-payment-embed"></div>
  </div>
  <p class="tp-payment-hint">Giữ trang này mở khi chuyển khoản. Nếu QR không tải được, sử dụng nút Mở payOS. Không cần tạo thêm lệnh nạp mới.</p>
 </div>
</section>
<script src="https://cdn.payos.vn/payos-checkout/v1/stable/payos-initialize.js" defer></script>
<script src="/assets/js/payos-embed.js?v=2" defer></script>
<?php endif;?>
<section class="tp-section"><div class="tp-panel"><h2>Giao dịch của tôi</h2><div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Mã đơn</th><th>Ngày</th><th>VNĐ</th><th>Nhận</th><th>Trạng thái</th><th>Thanh toán</th></tr></thead><tbody>
<?php foreach($recent as $o):?><tr><td><?=kp_e($o['order_code'])?></td><td><?=kp_e($o['created_at'])?></td><td><?=number_format((int)$o['amount'],0,',','.')?></td><td><?php if(($o['delivery_mode']??'game')==='wallet'):?><?=number_format((int)$o['amount']+intdiv((int)$o['amount']*(int)$o['bonus_percent'],100),0,',','.')?>đ ví<?php else:?><?=number_format((int)$o['units'],0,',','.')?> <?= $o['currency']==='luong'?'L':'LK' ?><?php endif;?></td><td><?=kp_e($o['status'])?></td><td><?php if($o['status']==='pending'&&filter_var($o['checkout_url'],FILTER_VALIDATE_URL)):?><a href="<?=kp_e($o['checkout_url'])?>" target="_blank" rel="noopener">Mở QR ↗</a><?php else:?>—<?php endif;?></td></tr><?php endforeach;?></tbody></table></div></div></section>
<script src="/assets/js/payos-create-guard.js?v=1" defer></script>
<?php kp_topup_page_end();?>
