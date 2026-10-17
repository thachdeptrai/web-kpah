<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/topup_core.php';
require_once __DIR__.'/../includes/wallet_vnd.php';
require_once __DIR__.'/_layout.php';
$user=kp_topup_user();$uid=(int)$user['id'];$csrf=kp_csrf_token();
$error='';$notice='';$balance=null;$chars=[];$history=[];
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
 try{
  if(!kp_csrf_check((string)($_POST['csrf']??'')))throw new RuntimeException('Phiên xác nhận đã hết hạn.');
  if(($_POST['action']??'')!=='exchange' || ($_POST['offline']??'')!=='1')
    throw new RuntimeException('Hãy xác nhận nhân vật đã thoát game trước khi đổi.');
  list($ok,$retry)=kp_rate_limit('vnd_exchange',(string)$uid,6,3600,true);
  if(!$ok)throw new RuntimeException('Bạn đã đổi quá nhiều lần, vui lòng kiểm tra lịch sử.');
  $amount=filter_var($_POST['amount']??null,FILTER_VALIDATE_INT);
  $char=filter_var($_POST['char_id']??null,FILTER_VALIDATE_INT);
  $currency=(string)($_POST['currency']??'');
  if($amount===false||$char===false)throw new RuntimeException('Mốc đổi hoặc nhân vật không hợp lệ.');
  $result=kp_vnd_exchange($uid,(int)$char,$currency,(int)$amount,(string)($_POST['token']??''));
  $notice=$result['status']==='completed'?'Đổi mốc thành công. Mã #'.$result['id'].'.':'Yêu cầu đổi #'.$result['id'].' đang được kiểm tra. Bạn đừng gửi lại để tránh trùng nhé.';
 }catch(Throwable $e){kp_log('vnd exchange request failed',$e);$error=$e instanceof RuntimeException?$e->getMessage():'Không đổi được lúc này.';}
}
try{
 $balance=kp_vnd_balance($uid);
 $st=kp_db()->prepare('SELECT id,charname FROM tob_char WHERE userId=? AND del=1 AND ban=0 ORDER BY id DESC LIMIT 40');
 $st->execute([$uid]);$chars=$st->fetchAll(PDO::FETCH_ASSOC);
 $history=kp_vnd_recent_exchanges($uid,30);
}catch(Throwable $e){kp_log('vnd exchange page wallet failed',$e);$error=$error?:'Chưa xem được ví VNĐ lúc này. Bạn thử lại sau nhé.';}
kp_topup_page_start('Ví VNĐ & đổi mốc','wallet',(string)$user['username']);?>
<section class="tp-hero"><span class="tp-kicker"><i></i> VÍ TÀI KHOẢN</span><h1>Số dư <em>VNĐ.</em></h1><p class="tp-lead">Dùng VNĐ trong ví để đổi Lượng cho nhân vật yêu thích. Hãy thoát hẳn game trên nhân vật nhận trước khi xác nhận.</p></section>
<?php if($error):?><div class="tp-alert bad" role="alert"><?=kp_e($error)?></div><?php endif;?>
<?php if($notice):?><div class="tp-alert warning" role="status"><?=kp_e($notice)?></div><?php endif;?>
<section class="tp-section"><div class="tp-summary"><div><small>VNĐ hiện có</small><strong><?= $balance===null?'—':number_format($balance,0,',','.') ?>đ</strong></div><div><small>Nạp thêm</small><a class="tp-button-secondary" href="/nap-ngan-hang">Nạp VietQR ↗</a></div></div></section>
<section class="tp-section"><div class="tp-grid two"><div class="tp-panel"><h2>Đổi VNĐ lấy Lượng</h2>
<aside class="vnd-exchange-warning" role="note" aria-labelledby="vnd-exchange-warning-title">
 <div class="vnd-exchange-warning-mark" aria-hidden="true">!</div>
 <div class="vnd-exchange-warning-copy">
  <h3 id="vnd-exchange-warning-title">Bắt buộc thoát game trước khi đổi</h3>
  <p>Hãy thoát hoàn toàn game trên <strong>đúng nhân vật nhận Lượng</strong> rồi mới xác nhận. Khi còn online, game có thể ghi đè dữ liệu cũ và làm mất số Lượng vừa đổi.</p>
  <p>VNĐ bị trừ một lần khi giao dịch được xử lý. Nếu nhân vật vẫn online và Lượng bị ghi đè, giao dịch vẫn được tính là đã thực hiện; <strong>admin không hỗ trợ cộng bù hoặc hoàn lại</strong>.</p>
 </div>
</aside>
<?php if(!$chars):?><p class="tp-muted">Không có nhân vật hợp lệ trong tài khoản để nhận Lượng. Kiểm tra nhân vật game trước khi đổi.</p><?php endif;?>
<form id="vnd-exchange-form" method="post" class="tp-form">
<input type="hidden" name="csrf" value="<?=kp_e($csrf)?>"><input type="hidden" name="action" value="exchange"><input type="hidden" name="token" value="<?=bin2hex(random_bytes(16))?>">
<label class="tp-field"><span>Mệnh giá đổi</span><select class="tp-select" name="amount" required><?php foreach(kp_card_packages() as $vnd=>$pkg):?><option value="<?=(int)$vnd?>"><?=number_format($vnd,0,',','.')?>đ</option><?php endforeach;?></select></label>
<label class="tp-field"><span>Loại Lượng nhận</span><select class="tp-select" name="currency" required><option value="luong">Lượng mở</option><option value="luonglock">Lượng khóa</option></select></label>
<label class="tp-field"><span>Nhân vật nhận</span><select class="tp-select" name="char_id" required><option value="">Chọn nhân vật</option><?php foreach($chars as $c):?><option value="<?=(int)$c['id']?>"><?=kp_e($c['charname'])?> (#<?=(int)$c['id']?>)</option><?php endforeach;?></select></label>
<label class="tp-check vnd-exchange-confirm"><input type="checkbox" name="offline" value="1" required><span>Nhân vật đã thoát game và tôi đồng ý trừ số VNĐ tương ứng một lần.</span></label>
<button id="vnd-exchange-button" type="submit" class="tp-button" <?= $balance===null||!$chars?'disabled':'' ?>>Xác nhận đổi mốc ↗</button>
</form></div>
<div class="tp-panel"><h2>Các mốc quy đổi</h2><div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>VNĐ</th><th>Lượng mở</th><th>Lượng khóa</th></tr></thead><tbody>
<?php foreach(kp_card_packages() as $vnd=>$pkg):?><tr><td><?=number_format($vnd,0,',','.')?></td><td><?=number_format($pkg['luong'],0,',','.')?></td><td><?=number_format($pkg['luonglock'],0,',','.')?></td></tr><?php endforeach;?></tbody></table></div></div></div></section>
<section class="tp-section"><div class="tp-panel"><h2>Lịch sử đổi mốc</h2><?php if(!$history):?><p class="tp-muted">Chưa có giao dịch đổi mốc.</p><?php else:?>
<div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Mã</th><th>Thời gian</th><th>VNĐ trừ</th><th>Lượng nhận</th><th>Trạng thái</th></tr></thead><tbody>
<?php foreach($history as $x):?><tr><td>#<?=kp_e(substr((string)$x['id'],0,10))?></td><td><?=kp_e($x['created_at'])?></td><td><?=number_format((int)$x['amount_vnd'],0,',','.')?>đ</td><td><?=number_format((int)$x['units'],0,',','.')?> <?= $x['currency']==='luong'?'Lượng':'Lượng khóa' ?></td><td><?=kp_e($x['status'])?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div></section>
<script src="/assets/js/vnd-exchange-guard.js" defer></script>
<?php kp_topup_page_end();?>
