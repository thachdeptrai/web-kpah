<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/player_portal.php';
require_once __DIR__.'/../includes/user_center.php';
$user=kp_player_account();$events=[];$error=false;
try{$events=kp_center_security(kp_db(),$user['id']);}catch(Throwable $e){kp_log('security history unavailable',$e);$error=true;}
kp_player_head('Lịch sử bảo mật','security',$user);
?>
<section class="uc-heading"><span>BẢO VỆ TÀI KHOẢN</span><h1>Lịch sử bảo mật</h1><p>Xem những lần đăng nhập và thay đổi mật khẩu được ghi nhận trên website.</p></section>
<?php if($error):?><div class="uc-alert">Chưa bật tính năng lịch sử bảo mật hoặc dữ liệu tạm thời không khả dụng.</div>
<?php elseif(!$events):?><div class="uc-empty">Chưa có sự kiện nào kể từ khi kích hoạt tính năng.</div>
<?php else:?>
<div class="uc-list"><?php foreach($events as $r):?><article class="uc-record"><div><strong><?= $r['event_type']==='login'?'Đăng nhập thành công':($r['event_type']==='password_change'?'Đổi mật khẩu':'Hoạt động bảo mật') ?></strong>
<small><?=kp_e($r['created_at'])?></small><p>Thiết bị/trình duyệt: <?=kp_e($r['device_label']!==''?$r['device_label']:'Không xác định')?> · Mạng: <?=kp_e($r['ip_hint'])?></p></div></article><?php endforeach;?></div><?php endif;?>
<p class="uc-note">Chỉ có sự kiện website phát sinh từ lúc kích hoạt. Không bao gồm đăng nhập trong game hoặc các lượt đăng nhập cũ.</p>
<?php kp_player_foot();?>
