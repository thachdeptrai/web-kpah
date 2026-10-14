<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/topup_core.php';
require_once __DIR__.'/../includes/loyalty_points.php';
require_once __DIR__.'/_layout.php';
$user=kp_topup_user();$userId=(int)$user['id'];$csrf=kp_csrf_token();
$error='';$notice='';$ready=false;$wallet=null;$ledger=[];$rewards=[];$redemptions=[];
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    try {
        if(!kp_csrf_check((string)($_POST['csrf']??'')))throw new RuntimeException('Phiên xác nhận hết hạn.');
        if(($_POST['action']??'')!=='redeem')throw new RuntimeException('Thao tác không hợp lệ.');
        list($allowed,$retry)=kp_rate_limit('points_redeem',(string)$userId,8,3600,true);
        if(!$allowed)throw new RuntimeException('Bạn thao tác quá nhanh, vui lòng thử lại sau.');
        $rewardId=filter_var($_POST['reward_id']??null,FILTER_VALIDATE_INT);
        if($rewardId===false||$rewardId<1)throw new RuntimeException('Quà tặng không hợp lệ.');
        $rid=kp_points_redeem($userId,$rewardId,(string)($_POST['token']??''));
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
    $st=$db->prepare('SELECT id,reward_title,points_cost,status,created_at FROM kp_points_redemptions WHERE user_id=? ORDER BY id DESC LIMIT 30');
    $st->execute([$userId]);$redemptions=$st->fetchAll(PDO::FETCH_ASSOC);
}catch(Throwable $e){
    kp_log('player loyalty page failed',$e);
    $ready=false;
}
$balance=(int)($wallet['balance']??0);
kp_topup_page_start('Điểm tích lũy','points',(string)$user['username']);
?>
<section class="tp-hero"><span class="tp-kicker"><i></i> CHƯƠNG TRÌNH THÀNH VIÊN</span><h1>Point tích lũy <em>đổi quà.</em></h1><p class="tp-lead">Cứ nạp 10.000đ, bạn nhận 10 Point để dành đổi quà. Point dùng chung cho tất cả nhân vật trong tài khoản.</p></section>
<?php if($error):?><div class="tp-alert bad" role="alert"><?=kp_e($error)?></div><?php endif;?>
<?php if($notice):?><div class="tp-alert good" role="status"><?=kp_e($notice)?></div><?php endif;?>
<?php if(!$ready):?><div class="tp-alert warning">Cửa hàng đổi quà đang chuẩn bị mở cửa. Bạn ghé lại sau nhé!</div>
<?php else: ?>
<section class="tp-section">
<div class="tp-summary"><div><small>Point hiện có</small><strong><?=number_format($balance,0,',','.')?></strong></div><div><small>Đã tích lũy</small><strong><?=number_format((int)($wallet['total_earned']??0),0,',','.')?></strong></div><div><small>Đã sử dụng</small><strong><?=number_format((int)($wallet['total_spent']??0),0,',','.')?></strong></div></div>
<div class="tp-panel tp-mt-17"><span class="tp-eyebrow">CỬA HÀNG POINT</span><h2>Chọn phần quà yêu thích</h2>
<?php if(!$rewards):?><p class="tp-muted">Quà mới đang được chuẩn bị. Hãy quay lại xem có gì thú vị nhé!</p><?php else: ?>
<div class="tp-grid">
<?php foreach($rewards as $reward):$available=($reward['stock']===null||(int)$reward['stock']>0); ?>
<div class="tp-card"><h3><?=kp_e($reward['title'])?></h3><p><?=kp_e($reward['description']??'')?></p>
<p><strong><?=number_format((int)$reward['points_cost'],0,',','.')?> Point</strong><?= $reward['stock']===null?'':' · Còn '.number_format((int)$reward['stock'],0,',','.') ?></p>
<form method="post" class="tp-form point-redeem-form"><input type="hidden" name="csrf" value="<?=kp_e($csrf)?>"><input type="hidden" name="action" value="redeem"><input type="hidden" name="reward_id" value="<?=(int)$reward['id']?>"><input type="hidden" name="token" value="<?=bin2hex(random_bytes(16))?>">
<button class="tp-button" type="submit" <?= (!$available||$balance<(int)$reward['points_cost'])?'disabled':'' ?>><?=!$available?'Hết quà':($balance<(int)$reward['points_cost']?'Chưa đủ Point':'Đổi quà ↗')?></button></form>
</div>
<?php endforeach; ?></div><?php endif; ?></div>
</section>
<section class="tp-section"><div class="tp-panel"><h2>Yêu cầu đổi quà</h2>
<?php if(!$redemptions):?><p class="tp-muted">Bạn chưa đổi quà nào.</p><?php else:?>
<div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Mã</th><th>Phần quà</th><th>Point</th><th>Trạng thái</th><th>Ngày</th></tr></thead><tbody>
<?php foreach($redemptions as $x):?><tr><td>#<?=(int)$x['id']?></td><td><?=kp_e($x['reward_title'])?></td><td><?=number_format((int)$x['points_cost'],0,',','.')?></td><td><?=kp_e($x['status'])?></td><td><?=kp_e($x['created_at'])?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div></section>
<section class="tp-section"><div class="tp-panel"><h2>Lịch sử Point</h2>
<?php if(!$ledger):?><p class="tp-muted">Chưa có giao dịch Point.</p><?php else:?>
<div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Thời gian</th><th>Hoạt động</th><th>Point</th></tr></thead><tbody>
<?php foreach($ledger as $x):?><tr><td><?=kp_e($x['created_at'])?></td><td><?=kp_e($x['note'])?></td><td><b><?= (int)$x['points_delta']>0?'+':'' ?><?=number_format((int)$x['points_delta'],0,',','.')?></b></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div></section>
<?php endif;?>
<script src="/assets/js/points-guard.js?v=1" defer></script>
<?php kp_topup_page_end(); ?>
