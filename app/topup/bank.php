<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/bank_payos.php';
require_once __DIR__.'/_layout.php';
$user=kp_topup_user();$cfg=kp_bank_cfg();$csrf=kp_csrf_token();$error='';$checkout='';$activeCode=0;
$bonusPercent=0;$walletReady=true;
$farmerSettings=['enabled'=>false,'per_10k'=>10000];$farmerGifts=[];$farmerChars=[];$farmerNotice='';
if(isset($_SESSION['kp_farmer_bank_notice'])) {
    $farmerNotice=(string)$_SESSION['kp_farmer_bank_notice'];
    unset($_SESSION['kp_farmer_bank_notice']);
}
try {
    $farmerSettings=kp_bank_farmer_settings();
} catch(Throwable $e) { kp_log('bank farmer settings unavailable',$e); }

try{$bonusPercent=kp_vnd_bonus_percent();kp_vnd_balance((int)$user['id']);}catch(Throwable $e){$walletReady=false;kp_log('bank wallet unavailable',$e);}
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
 try{
  if(($_POST['action']??'')==='claim_farmer'){
   if(!kp_csrf_check($_POST['csrf']??''))throw new RuntimeException('Phiên đã hết hạn. Tải lại trang.');
   list($allowed,$retry)=kp_rate_limit('bank_farmer_claim',(string)$user['id'],12,900,true);
   if(!$allowed)throw new RuntimeException('Thử lại sau '.max(1,(int)ceil($retry/60)).' phút.');
   if(($_POST['offline']??'')!=='1')throw new RuntimeException('Bạn cần thoát game trên nhân vật nhận điểm trước khi xác nhận.');
   $code=filter_var($_POST['order_code']??null,FILTER_VALIDATE_INT);
   $char=filter_var($_POST['char_id']??null,FILTER_VALIDATE_INT);
   if($code===false||$char===false)throw new RuntimeException('Mã đơn hoặc nhân vật không hợp lệ.');
   $claimed=kp_bank_farmer_claim((int)$user['id'],(int)$code,(int)$char);
   $_SESSION['kp_farmer_bank_notice']='Đã nhận '.number_format((int)$claimed['points'],0,',','.').' Điểm nông dân cho '.$claimed['charname'].'.';
   header('Location: /nap-ngan-hang#qua-nap-bank',true,303);exit;
  }
  if(isset($_POST['action'])&&$_POST['action']!=='create_bank')throw new RuntimeException('Yêu cầu không hợp lệ.');
  $amount=filter_var($_POST['amount']??null,FILTER_VALIDATE_INT);
  if($amount===false)throw new RuntimeException('Mệnh giá không hợp lệ.');
  if(!$walletReady)throw new RuntimeException('Ví VNĐ đang nâng cấp, vui lòng thử lại sau.');
  $url=kp_bank_create($user,(int)$amount,(string)($_POST['csrf']??''));
  $checkout=$url;
  $find=kp_db()->prepare('SELECT order_code FROM kp_bank_orders WHERE user_id=? AND checkout_url=? ORDER BY id DESC LIMIT 1');
  $find->execute([(int)$user['id'],$url]);$activeCode=(int)$find->fetchColumn();
 }catch(Throwable $e){$error=$e instanceof RuntimeException?$e->getMessage():'Chưa tạo được yêu cầu. Thử lại sau.';}
}
// Update legacy wallet_credited records only when a completed wallet journal exists.
try{kp_bank_finalize_legacy_wallet_statuses(30,(int)$user['id']);}catch(Throwable $e){kp_log('bank status sync deferred',$e);}
$recent=[];
try{$st=kp_db()->prepare('SELECT order_code,amount,currency,units,status,checkout_url,created_at,provider_reference FROM kp_bank_orders WHERE user_id=? ORDER BY id DESC LIMIT 15');$st->execute([(int)$user['id']]);$recent=$st->fetchAll(PDO::FETCH_ASSOC);}
catch(Throwable $e){$error=$error?:'Chưa tải được lịch sử nạp ngân hàng. Bạn thử lại sau nhé.';}
try {
 $farmerGifts=kp_bank_farmer_list((int)$user['id'],30);
 $st=kp_db()->prepare('SELECT id,charname,pInfo FROM tob_char WHERE userId=? AND del=1 AND ban=0 ORDER BY id ASC LIMIT 30');
 $st->execute([(int)$user['id']]);
 $farmerChars=$st->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e) {
 kp_log('bank farmer gifts lookup deferred',$e);
 $error=$error?:'Chưa tải được phần quà Điểm nông dân. Bạn thử lại sau nhé.';
}
kp_topup_page_start('Nạp ngân hàng','bank',(string)$user['username']);?>
<section class="tp-hero"><span class="tp-kicker"><i></i> NẠP NGÂN HÀNG</span><h1>Thanh toán <em>VietQR.</em></h1><p class="tp-lead">Chọn số tiền, tạo mã QR và thanh toán trong 10 phút. Tiền nạp cùng ưu đãi chuyển khoản sẽ được cộng vào ví khi thanh toán thành công. Bạn có thể dùng VNĐ để đổi Lượng bất cứ lúc nào.</p></section>
<?php if($error):?><div class="tp-alert bad" role="alert"><?=kp_e($error)?></div><?php endif;?>
<?php if($farmerNotice):?><div class="tp-alert good" role="status"><?=kp_e($farmerNotice)?></div><?php endif;?>
<?php if(isset($_GET['result'])):?><div class="tp-alert warning">Trang thanh toán đã chuyển về website. Chỉ kết quả tra cứu từ payOS mới xác nhận tiền về.</div><?php endif;?>
<section class="tp-section"><div class="tp-grid two"><div class="tp-panel"><h2>Tạo đơn nạp</h2><?php if(!$walletReady):?><div class="tp-alert warning">Ví VNĐ đang tạm gián đoạn. Bạn vui lòng thử lại sau.</div><?php endif;?><?php if(!$cfg['ready']):?><div class="tp-alert warning">Nạp ngân hàng đang tạm gián đoạn. Vui lòng quay lại sau.</div><?php endif;?>
<form method="post" class="tp-form" id="bank-create-form"><input type="hidden" name="csrf" value="<?=kp_e($csrf)?>"><input type="hidden" name="action" value="create_bank">
<label class="tp-field"><span>Mệnh giá</span><select class="tp-select" name="amount" required <?=(!$cfg['ready']||!$walletReady)?'disabled':''?>>
<?php foreach(kp_card_packages() as $vnd=>$pkg):?><option value="<?=$vnd?>"><?=number_format($vnd,0,',','.')?>đ</option><?php endforeach;?></select></label>
<p class="tp-muted">Thưởng nạp ngân hàng hiện tại: <strong>+<?= (int)$bonusPercent ?>%</strong>. Nạp 10.000đ được cộng <?=number_format(10000+intdiv(10000*$bonusPercent,100),0,',','.')?>đ vào ví.</p>
<?php if($farmerSettings['enabled']&&(int)$farmerSettings['per_10k']>0):?>
<p class="tp-muted">Quà thêm: nạp 10.000đ được <strong><?=number_format((int)$farmerSettings['per_10k'],0,',','.')?> Điểm nông dân</strong>. Thanh toán xong, chọn nhân vật để nhận quà bên dưới.</p>
<?php endif;?>
<button type="submit" class="tp-button" id="bank-create-button" <?=(!$cfg['ready']||!$walletReady)?'disabled':''?>>Tạo QR thanh toán ↗</button></form></div>
<div class="tp-panel"><h2>Bảng tiền về ví VNĐ</h2><div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Nạp</th><th>Thưởng</th><th>Ví nhận</th></tr></thead><tbody>
<?php foreach(kp_card_packages() as $vnd=>$pkg):$bonus=intdiv($vnd*$bonusPercent,100);?><tr><td><?=number_format($vnd,0,',','.')?>đ</td><td>+<?=number_format($bonus,0,',','.')?>đ</td><td><?=number_format($vnd+$bonus,0,',','.')?>đ</td></tr><?php endforeach;?></tbody></table></div></div></div></section>
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
<section class="tp-section"><div class="tp-panel"><h2>Giao dịch của tôi</h2><p class="tp-bank-history-hint">Vuốt ngang bảng để xem đầy đủ mã đơn, ngày, số tiền và trạng thái.</p><div class="tp-table-wrap tp-bank-history-scroll" role="region" aria-label="Lịch sử giao dịch nạp ngân hàng, có thể cuộn ngang" tabindex="0"><table class="tp-table tp-bank-history-table"><thead><tr><th scope="col">Mã đơn</th><th scope="col">Ngày</th><th scope="col">VNĐ</th><th scope="col">Nhận</th><th scope="col">Trạng thái</th><th scope="col">Thanh toán</th></tr></thead><tbody>
<?php foreach($recent as $o):?><tr><td><?=kp_e($o['order_code'])?></td><td><?=kp_e($o['created_at'])?></td><td><?=number_format((int)$o['amount'],0,',','.')?></td><td><?php if(kp_bank_is_wallet_order($o)):?><?=number_format((int)$o['amount']+intdiv((int)$o['amount']*kp_bank_order_bonus($o),100),0,',','.')?>đ ví<?php else:?><?=number_format((int)$o['units'],0,',','.')?> <?= $o['currency']==='luong'?'L':'LK' ?><?php endif;?></td><td><?=kp_e($o['status'])?><?php if($o['status']==='completed' && kp_bank_is_wallet_order($o)):?><small> · Đã cộng VNĐ</small><?php endif;?></td><td><?php if(in_array($o['status'],['pending','wallet_pending'],true)&&filter_var($o['checkout_url'],FILTER_VALIDATE_URL)):?><a href="<?=kp_e($o['checkout_url'])?>" target="_blank" rel="noopener">Mở QR ↗</a><?php else:?>—<?php endif;?></td></tr><?php endforeach;?></tbody></table></div></div></section>
<section class="tp-section" id="qua-nap-bank"><div class="tp-panel"><span class="tp-eyebrow">QUÀ TẶNG KHI CHUYỂN KHOẢN</span><h2>Nhận Điểm nông dân</h2><p class="tp-muted"><a class="tp-support" href="/lich-su-nhan-diem-nong-dan">Xem lịch sử nhận Điểm nông dân của tôi →</a></p>
<p class="tp-muted">Những đơn bank đủ điều kiện sẽ hiện bên dưới sau khi VNĐ được cộng vào ví. Chọn nhân vật để nhận điểm. <strong>Hãy thoát game hoàn toàn trên nhân vật đó trước khi nhận</strong> để tránh bị game ghi đè số điểm.</p>
<?php if(!$farmerGifts):?><p class="tp-muted">Bạn chưa có phần quà Điểm nông dân đang chờ nhận.</p><?php else:?>
<div class="tp-grid two">
<?php foreach($farmerGifts as $gift): ?>
<article class="tp-panel tp-farmer-gift-card">
<span class="tp-eyebrow">MÃ NẠP #<?=(int)$gift['order_code']?></span>
<h3>+<?=number_format((int)$gift['points'],0,',','.')?> Điểm nông dân</h3>
<p class="tp-muted">Nạp <?=number_format((int)$gift['amount'],0,',','.')?>đ</p>
<?php if($gift['status']==='reserved' && $farmerChars):?>
<form method="post" action="/nap-ngan-hang#qua-nap-bank" class="tp-form">
<input type="hidden" name="action" value="claim_farmer">
<input type="hidden" name="csrf" value="<?=kp_e($csrf)?>">
<input type="hidden" name="order_code" value="<?=(int)$gift['order_code']?>">
<label class="tp-field"><span>Nhân vật nhận quà</span><select name="char_id" class="tp-select" required>
<?php foreach($farmerChars as $char):?>
<option value="<?=(int)$char['id']?>"><?=kp_e($char['charname'])?> · <?=number_format((int)(kp_farmer_points($char['pInfo'])??0),0,',','.')?> điểm hiện tại</option>
<?php endforeach;?></select></label>
<label class="tp-field tp-farmer-offline">
<input type="checkbox" name="offline" value="1" required class="tp-farmer-checkbox">
<span>Tôi đã thoát game trên nhân vật nhận điểm.</span></label>
<button class="tp-button" type="submit">Nhận <?=number_format((int)$gift['points'],0,',','.')?> điểm ↗</button>
</form>
<?php elseif($gift['status']==='completed'):?>
<p class="tp-alert good">Đã nhận vào <?=kp_e($gift['charname']??'nhân vật')?>.</p>
<?php elseif($gift['status']!=='reserved'):?>
<p class="tp-alert warning">Đang chờ kiểm tra. Liên hệ quản trị kèm mã nạp, không bấm nhận lại.</p>
<?php else:?>
<p class="tp-muted">Bạn cần có nhân vật hợp lệ trong game để nhận quà.</p>
<?php endif;?>
</article>
<?php endforeach;?></div><?php endif;?></div></section>
<script src="/assets/js/payos-create-guard.js?v=1" defer></script>
<?php kp_topup_page_end();?>
