<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/player_portal.php';
require_once __DIR__.'/../includes/user_center.php';
$user=kp_player_account();$rows=[];$error=false;
try{$rows=kp_center_gift_history(kp_db(),$user['id']);}catch(Throwable $e){kp_log('personal giftcode history unavailable',$e);$error=true;}
kp_player_head('Giftcode của tôi','gifts',$user);
?>
<section class="uc-heading"><span>QUÀ ĐÃ NHẬN</span><h1>Lịch sử Giftcode của tôi</h1><p>Danh sách mã mà các nhân vật thuộc tài khoản của bạn đã nhận trong game.</p></section>
<?php if($error):?><div class="uc-alert">Không tải được lịch sử. Vui lòng thử lại sau.</div>
<?php elseif(!$rows):?><div class="uc-empty">Chưa có lượt nhận Giftcode nào được ghi nhận.</div>
<?php else:?>
<div class="uc-list"><?php foreach($rows as $r):?>
<article class="uc-record"><div><strong><?=kp_e($r['giftcode'])?></strong><small>Nhân vật: <?=kp_e($r['player'])?></small></div><div class="uc-tags">
<?php if((int)$r['xu']):?><span>Xu: <?=kp_e(kp_player_format($r['xu']))?></span><?php endif;?>
<?php if((int)$r['luong']):?><span>Lượng: <?=kp_e(kp_player_format($r['luong']))?></span><?php endif;?>
<?php if((int)$r['luongK']):?><span>Lượng khóa: <?=kp_e(kp_player_format($r['luongK']))?></span><?php endif;?>
<?php if(trim((string)$r['item'])!=='' && strtolower(trim((string)$r['item']))!=='null'):?><span>Có quà vật phẩm</span><?php endif;?>
</div></article>
<?php endforeach;?></div>
<?php endif;?><p class="uc-note">Lịch sử game hiện chưa ghi thời gian nhập mã; các lượt nhận được sắp xếp theo thứ tự ghi nhận gần nhất.</p>
<?php kp_player_foot(); ?>
