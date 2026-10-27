<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/topup_core.php';
require_once __DIR__.'/../includes/loyalty_points.php';
require_once __DIR__.'/../includes/points_reward_display.php';
require_once __DIR__.'/_layout.php';
$user=kp_topup_user();$userId=(int)$user['id'];$csrf=kp_csrf_token();
$error='';$notice='';$ready=false;$wallet=null;$ledger=[];$rewards=[];$redemptions=[];$rewardDisplays=[];$redeemCharacters=[];$targetEnabled=false;$redemptionTargets=[];$pointAudit=[];$tierRequired=[];$redemptionEnabled=true;
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    try {
        if(!kp_csrf_check((string)($_POST['csrf']??'')))throw new RuntimeException('Phiên xác nhận hết hạn.');
        if(($_POST['action']??'')!=='redeem')throw new RuntimeException('Thao tác không hợp lệ.');
        list($allowed,$retry)=kp_rate_limit('points_redeem',(string)$userId,8,3600,true);
        if(!$allowed)throw new RuntimeException('Bạn thao tác quá nhanh, vui lòng thử lại sau.');
        $rewardId=filter_var($_POST['reward_id']??null,FILTER_VALIDATE_INT);
        if($rewardId===false||$rewardId<1)throw new RuntimeException('Quà tặng không hợp lệ.');
        $chosen=filter_var($_POST['char_id']??0,FILTER_VALIDATE_INT);
        $rid=kp_points_redeem($userId,$rewardId,(string)($_POST['token']??''),$chosen===false?0:(int)$chosen);
        $notice='Đã ghi nhận yêu cầu đổi quà #'.$rid.'. Quản trị viên sẽ xác nhận và phát quà.';
    }catch(Throwable $e){
        kp_log('player points redemption failed',$e);
        $error=$e instanceof RuntimeException?$e->getMessage():'Không thể đổi quà lúc này.';
    }
}
try {
    $db=kp_db();
    $program=$db->query('SELECT activated_at FROM kp_points_program WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    if(!$program)throw new RuntimeException('Chưa kích hoạt chương trình.');
    $ready=true;
    $redemptionEnabled=kp_points_redemption_enabled($db);
    $targetEnabled=kp_points_target_enabled($db);
    if($targetEnabled)$redeemCharacters=kp_points_available_characters($db,$userId);
    // Repair post-payment Points in case the site went offline after successful delivery.
    $st=$db->prepare("SELECT order_code,amount,created_at FROM kp_bank_orders WHERE user_id=? AND status IN ('completed','wallet_credited') AND created_at>=? ORDER BY id DESC LIMIT 150");
    $st->execute([$userId,$program['activated_at']]);
    foreach($st->fetchAll(PDO::FETCH_ASSOC) as $b){
        try{kp_points_award($userId,'payos',(string)$b['order_code'],(int)$b['amount'],$b['created_at']);}
        catch(Throwable $e){kp_log('recheck bank loyalty points failed',$e);}
    }
    foreach(kp_topup_recent('orders',$userId,150) as $c){
        if(!in_array(($c['status']??''),['verified_pending_delivery','queued','wallet_credited'],true))continue;
        try{kp_points_award($userId,'card',(string)$c['id'],(int)$c['amount'],$c['created_at']);}
        catch(Throwable $e){kp_log('recheck card loyalty points failed',$e);}
    }
    $st=$db->prepare('SELECT balance,total_earned,total_spent FROM kp_points_wallet WHERE user_id=?');
    $st->execute([$userId]);$wallet=$st->fetch(PDO::FETCH_ASSOC);
    $st=$db->prepare('SELECT points_delta,source_type,note,created_at FROM kp_points_ledger WHERE user_id=? ORDER BY id DESC LIMIT 40');
    $st->execute([$userId]);$ledger=$st->fetchAll(PDO::FETCH_ASSOC);
    $rewards=$db->query('SELECT id,title,description,points_cost,stock FROM kp_points_rewards WHERE is_active=1 ORDER BY points_cost ASC,id ASC LIMIT 80')->fetchAll(PDO::FETCH_ASSOC);
    $rewardDisplays=kp_point_reward_display_all($db,$rewards);
    $tierRequired=kp_points_reward_required_tiers($db,$rewards);
    $st=$db->prepare('SELECT id,reward_title,points_cost,status,admin_note,created_at FROM kp_points_redemptions WHERE user_id=? ORDER BY id DESC LIMIT 30');
    $st->execute([$userId]);$redemptions=$st->fetchAll(PDO::FETCH_ASSOC);
    $redemptionTargets=kp_points_targets($db,$redemptions);
    $pointAudit=kp_points_audit_list($db,$userId,40);
}catch(Throwable $e){
    kp_log('player loyalty page failed',$e);
    $ready=false;
}
$balance=(int)($wallet['balance']??0);
$earned=(int)($wallet['total_earned']??0);
$memberTier=kp_points_member_tier($earned);
$nextTier=kp_points_next_tier($earned);
$tiersList=kp_points_tiers();
kp_topup_page_start('Điểm tích lũy','points',(string)$user['username']);
?>
<section class="tp-hero"><span class="tp-kicker"><i></i> CHƯƠNG TRÌNH THÀNH VIÊN</span><h1>Point tích lũy <em>đổi quà.</em></h1><p class="tp-lead">Cứ nạp 10.000đ, bạn nhận 10 Point để dành đổi quà. Point dùng chung cho tất cả nhân vật trong tài khoản.</p></section>
<?php if($error):?><div class="tp-alert bad" role="alert"><?=kp_e($error)?></div><?php endif;?>
<?php if($notice):?><div class="tp-alert good" role="status"><?=kp_e($notice)?></div><?php endif;?>
<?php if(!$ready):?><div class="tp-alert warning">Cửa hàng đổi quà đang chuẩn bị mở cửa. Bạn ghé lại sau nhé!</div>
<?php else: ?>
<?php if(!$redemptionEnabled):?>
<div class="tp-alert warning point-redemption-paused" role="status">
 <strong>Chức năng hiện đang cập nhật</strong>
 <p>Đổi quà bằng Point đang tạm dừng. Bạn vẫn có thể tích Point, kiểm tra số dư, hạng thành viên và lịch sử đổi quà.</p>
</div>
<div hidden data-ui-toast="warning" data-ui-toast-title="Chức năng hiện đang cập nhật" data-ui-toast-message="Đổi quà Point đang tạm dừng. Vui lòng quay lại sau." data-ui-toast-duration="5000"></div>
<?php endif;?>
<section class="tp-section">
<div class="tp-summary"><div><small>Point hiện có</small><strong><?=number_format($balance,0,',','.')?></strong></div><div><small>Đã tích lũy</small><strong><?=number_format((int)($wallet['total_earned']??0),0,',','.')?></strong></div><div><small>Đã sử dụng</small><strong><?=number_format((int)($wallet['total_spent']??0),0,',','.')?></strong></div></div>
<div class="tp-panel point-member-panel">
<span class="tp-eyebrow">HẠNG THÀNH VIÊN</span>
<div class="point-member-current">
<img class="point-tier-featured" loading="eager" src="<?=kp_e($memberTier['image'])?>" alt="Huy hiệu K hạng <?=kp_e($memberTier['name'])?>" width="96" height="96" decoding="async">
<div><h2><?=kp_e($memberTier['name'])?> · <?=number_format($earned,0,',','.')?> Point đã tích lũy</h2>
<p class="tp-muted">Hạng được tính theo tổng Point tích lũy, không giảm khi đổi quà.</p></div>
</div>
<?php if($nextTier):
$base=$memberTier['threshold'];$goal=$nextTier['threshold'];
$progress=max(0,min(100,(int)floor(($earned-$base)*100/max(1,$goal-$base))));
?>
<p class="tp-muted">Còn <?=number_format($goal-$earned,0,',','.')?> Point để đạt hạng <?=kp_e($nextTier['name'])?>.</p>
<div class="point-tier-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?=$progress?>" aria-label="Tiến độ lên hạng"><span style="width:<?=$progress?>%"></span></div>
<?php else:?><p class="tp-muted">Bạn đã đạt hạng cao nhất. Quà đặc biệt tùy theo hạng sẽ hiển thị tại cửa hàng.</p><?php endif;?>
<div class="point-tier-gallery-heading"><strong>6 HẠNG THÀNH VIÊN</strong><span>Hạng hiện tại được đánh dấu nổi bật</span></div>
<div class="point-tier-gallery" aria-label="Sáu hạng thành viên">
<?php foreach($tiersList as $index=>$t):
  $nextLimit=(int)($tiersList[$index+1]['threshold']??0);
  $pointRange=$nextLimit>0
      ? number_format((int)$t['threshold'],0,',','.').' – '.number_format($nextLimit-1,0,',','.')
      : 'Trên '.number_format((int)$t['threshold']-1,0,',','.');
  $selected=(int)$memberTier['id']===(int)$t['id'];
?>
<article class="point-tier-chip point-tier-chip--<?=(int)$t['id']?><?=$selected?' is-active':''?>" <?=$selected?'aria-current="true"':''?>>
 <div class="point-tier-emblem"><img src="<?=kp_e($t['image'])?>" width="192" height="192" alt="Huy hiệu K hạng <?=kp_e($t['name'])?>" loading="lazy" decoding="async"></div>
 <div class="point-tier-copy"><strong>Hội viên <?=kp_e($t['name'])?></strong><small><?=kp_e($pointRange)?> Point</small></div>
</article>
<?php endforeach;?></div>
</div>

<div class="tp-panel tp-mt-17"><span class="tp-eyebrow">CỬA HÀNG POINT</span><h2>Chọn phần quà yêu thích</h2>
<?php if(!$rewards):?><p class="tp-muted">Quà mới đang được chuẩn bị. Hãy quay lại xem có gì thú vị nhé!</p><?php else: ?>
<div class="tp-grid">
<?php foreach($rewards as $reward):$available=($reward['stock']===null||(int)$reward['stock']>0);$requiredTier=(int)($tierRequired[(int)$reward['id']]??0);$tierLocked=(int)$memberTier['id']<$requiredTier; ?>
<article class="tp-card point-reward-card"><?php $visual=$rewardDisplays[(int)$reward['id']]??[];$icon=kp_point_reward_icon($visual); ?><div class="point-reward-preview"><?php if($icon):?><img src="<?=kp_e($icon)?>" alt="" loading="lazy" width="72" height="72" decoding="async"><?php else:?><span class="point-reward-placeholder" aria-hidden="true">✦</span><?php endif;?><div><h3><?=kp_e($reward['title'])?></h3><?php if(isset($visual['item_id'])&&$visual['item_id']!==null):?><small>Mã vật phẩm #<?=(int)$visual['item_id']?></small><?php endif;?></div></div><p><?=kp_e($reward['description']??'')?></p><?php if($requiredTier>0):?><span class="point-tier-label">Yêu cầu hạng <?=kp_e($tiersList[$requiredTier]['name']??'Đồng')?></span><?php endif;?><?php $optLines=kp_point_reward_option_lines($visual);if($optLines):?><details class="point-reward-options"><summary>Xem <?=count($optLines)?> option / chỉ số</summary><ul><?php foreach($optLines as $opt):?><li><?=kp_e($opt)?></li><?php endforeach;?></ul></details><?php endif;?>
<p><strong><?=number_format((int)$reward['points_cost'],0,',','.')?> Point</strong><?= $reward['stock']===null?'':' · Còn '.number_format((int)$reward['stock'],0,',','.') ?></p>
<form method="post" class="tp-form point-redeem-form"><input type="hidden" name="csrf" value="<?=kp_e($csrf)?>"><input type="hidden" name="action" value="redeem"><input type="hidden" name="reward_id" value="<?=(int)$reward['id']?>"><input type="hidden" name="token" value="<?=bin2hex(random_bytes(16))?>"><?php if($targetEnabled):?><label class="tp-field"><span>Nhân vật nhận quà</span><select class="tp-input" name="char_id" required><option value="">Chọn nhân vật thuộc tài khoản</option><?php foreach($redeemCharacters as $character):?><option value="<?=(int)$character['id']?>"><?=kp_e($character['charname'])?> (#<?=(int)$character['id']?>)</option><?php endforeach;?></select></label><?php endif;?>
<button class="tp-button" type="submit" <?= (!$redemptionEnabled||!$available||$balance<(int)$reward['points_cost']||($targetEnabled&&!$redeemCharacters)||$tierLocked)?'disabled':'' ?>><?=!$redemptionEnabled?'Đang cập nhật':(!$available?'Hết quà':($balance<(int)$reward['points_cost']?'Chưa đủ Point':($targetEnabled&&!$redeemCharacters?'Chưa có nhân vật':($tierLocked?'Chưa đủ hạng':'Đổi quà ↗'))))?></button></form>
</article>
<?php endforeach; ?></div><?php endif; ?></div>
</section>
<section class="tp-section"><div class="tp-panel"><h2>Yêu cầu đổi quà</h2>
<?php if(!$redemptions):?><p class="tp-muted">Bạn chưa đổi quà nào.</p><?php else:?>
<div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Mã</th><th>Phần quà</th><th>Point</th><th>Trạng thái</th><th>Cập nhật</th><th>Ngày</th></tr></thead><tbody>
<?php $redemptionLabels=['pending'=>'Đang chờ duyệt','approved'=>'Đã duyệt · chờ phát quà','delivering'=>'Admin đang phát quà','fulfilled'=>'Đã giao quà','rejected'=>'Từ chối · đã hoàn Point'];foreach($redemptions as $x):?><tr><td>#<?=(int)$x['id']?></td><td><?=kp_e($x['reward_title'])?><?php if(isset($redemptionTargets[(int)$x['id']])):$target=$redemptionTargets[(int)$x['id']];?><small> · Nhận bởi <?=kp_e($target['char_name'])?> (#<?=(int)$target['char_id']?>)</small><?php endif;?></td><td><?=number_format((int)$x['points_cost'],0,',','.')?></td><td><span class="tp-pill <?=kp_e($x['status'])?>"><?=kp_e($redemptionLabels[$x['status']]??$x['status'])?></span></td><td><?=kp_e($x['admin_note']?:($x['status']==='pending'?'Quản trị viên đang kiểm tra':'—'))?></td><td><?=kp_e($x['created_at'])?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div></section>
<section class="tp-section"><div class="tp-panel"><h2>Lịch sử Point</h2>
<?php if(!$ledger):?><p class="tp-muted">Chưa có giao dịch Point.</p><?php else:?>
<div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Thời gian</th><th>Hoạt động</th><th>Point</th></tr></thead><tbody>
<?php foreach($ledger as $x):?><tr><td><?=kp_e($x['created_at'])?></td><td><?=kp_e($x['note'])?></td><td><b><?= (int)$x['points_delta']>0?'+':'' ?><?=number_format((int)$x['points_delta'],0,',','.')?></b></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div></section>
<section class="tp-section"><div class="tp-panel"><span class="tp-eyebrow">NHẬT KÝ CỦA TÔI</span><h2>Lịch sử xác nhận và thiết bị</h2><p class="tp-muted">Chỉ hiển thị các thao tác Point của tài khoản đang đăng nhập. Vị trí IP là ước lượng nếu có, không phải GPS.</p>
<?php if(!$pointAudit):?><p class="tp-muted">Chưa có bản ghi nhật ký chi tiết mới.</p><?php else:?>
<div class="tp-table-wrap point-audit-scroll" tabindex="0" role="region" aria-label="Nhật ký Point tài khoản của tôi"><table class="tp-table point-audit-table">
<thead><tr><th>Thời gian</th><th>Thao tác</th><th>Point +/-</th><th>Số dư trước → sau</th><th>Thiết bị</th><th>IP</th><th>Vị trí IP</th></tr></thead><tbody>
<?php foreach($pointAudit as $a):?><tr><td><?=kp_e($a['created_at'])?></td><td><?=kp_e($a['operation'])?></td><td><?=((int)$a['points_delta']>=0?'+':'').number_format((int)$a['points_delta'],0,',','.')?></td>
<td><?=$a['balance_before']===null?'—':number_format((int)$a['balance_before'],0,',','.')?> → <?=$a['balance_after']===null?'—':number_format((int)$a['balance_after'],0,',','.')?></td>
<td><?=kp_e($a['device_type'].' · '.$a['browser'])?></td><td><?=kp_e($a['ip']!==''?$a['ip']:'Không có dữ liệu')?></td><td><?=kp_e($a['location_hint']!==''?$a['location_hint'].' (ước lượng)':'Không xác định')?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div></section>
<?php endif;?>
<script src="/assets/js/points-guard.js?v=1" defer></script>
<?php kp_topup_page_end(); ?>
