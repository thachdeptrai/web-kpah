<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/player_portal.php';
require_once __DIR__.'/../includes/user_center.php';
$user=kp_player_account();$rows=[];$unavailable=false;$result='';
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
 if(!kp_csrf_check($_POST['csrf']??'')){$result='error';}
 else {
  try{
   if(($_POST['action']??'')!=='read_all')throw new RuntimeException('invalid action');
   $st=kp_db()->prepare('UPDATE kp_user_notifications SET is_read=1 WHERE user_id=? AND is_read=0');
   $st->execute([(int)$user['id']]);$_SESSION['kp_toast']=['type'=>'success','title'=>'Đã đọc thông báo','message'=>'Đã đánh dấu các thông báo của bạn là đã đọc.'];
   header('Location: /thong-bao',true,303);exit;
  }catch(Throwable $e){kp_log('notification mark read failed',$e);$result='error';}
 }
}
try{$rows=kp_center_notifications(kp_db(),$user['id']);}catch(Throwable $e){kp_log('notification center unavailable',$e);$unavailable=true;}
kp_player_head('Thông báo','notifications',$user);
?>
<section class="uc-heading"><span>TÀI KHOẢN</span><h1>Trung tâm thông báo</h1><p>Cập nhật về tài khoản và các thao tác được hệ thống ghi nhận từ khi kích hoạt tính năng.</p></section>
<?php if($result==='error'):?><div class="uc-alert">Không thể đánh dấu đã đọc. Hãy tải lại trang và thử lại.</div><div hidden data-toast-type="error" data-toast-title="Thất bại" data-toast-message="Yêu cầu không hợp lệ hoặc máy chủ không thể lưu thay đổi."></div><?php endif;?>
<?php if($unavailable):?><div class="uc-alert">Chưa bật trung tâm thông báo. Quản trị viên cần cài đặt dữ liệu hệ thống.</div>
<?php else:?>
<form method="post" class="uc-actions"><input type="hidden" name="csrf" value="<?=kp_e(kp_csrf_token())?>"><input type="hidden" name="action" value="read_all"><button type="submit">Đánh dấu tất cả đã đọc</button></form>
<?php if(!$rows):?><div class="uc-empty">Bạn chưa có thông báo mới.</div><?php endif;?>
<div class="uc-list"><?php foreach($rows as $r):?>
<article class="uc-record <?= (int)$r['is_read']===0?'uc-unread':''?>"><div><strong><?=kp_e($r['title'])?></strong><small><?=kp_e($r['created_at'])?> · <?= (int)$r['is_read']===0?'Chưa đọc':'Đã đọc'?></small><p><?=kp_e($r['body'])?></p></div>
<?php if(kp_center_safe_path((string)$r['link_path']) && (string)$r['link_path'] !== ''):?><a href="<?=kp_e($r['link_path'])?>">Xem chi tiết ↗</a><?php endif;?>
</article><?php endforeach;?></div>
<?php endif;?>
<?php kp_player_foot();?>
