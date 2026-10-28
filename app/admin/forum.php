<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/admin_security.php';
require_once __DIR__.'/../includes/admin_nav.php';
require_once __DIR__.'/../includes/forum_feedback.php';
require_once __DIR__.'/../includes/user_center.php';
kp_send_security_headers(true);header('Content-Type: text/html; charset=utf-8');
$admin=kp_admin_require('/quan-tri/dang-nhap',['admin']);
$csrf=kp_csrf_token();$error='';$success=isset($_GET['ok'])?'Đã lưu phản hồi/trạng thái.':'';
if(empty($_SESSION['kp_forum_admin_nonce']))$_SESSION['kp_forum_admin_nonce']=bin2hex(random_bytes(32));
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 try{
  if(!kp_admin_csrf_valid($_POST['csrf']??null))throw new RuntimeException('Phiên bảo mật đã hết hạn.');
  list($ok,$retry)=kp_admin_limit('forum_action',(string)$admin['id'],60,3600);
  if(!$ok)throw new RuntimeException('Đã đạt giới hạn thao tác, hãy thử lại sau.');
  if(($_POST['action']??'')!=='update')throw new RuntimeException('Thao tác không hợp lệ.');
  $id=filter_var($_POST['topic_id']??null,FILTER_VALIDATE_INT);
  if(!is_int($id)||$id<1)throw new RuntimeException('Mã bài không hợp lệ.');
  $reply=is_string($_POST['reply']??null)?trim((string)$_POST['reply']):'';
  $status=(string)($_POST['status']??'');$visibility=(string)($_POST['visibility']??'');
  if($reply!==''&&(!is_string($_POST['nonce']??null)||!hash_equals((string)$_SESSION['kp_forum_admin_nonce'],(string)$_POST['nonce'])))
     throw new RuntimeException('Phản hồi đã gửi hoặc phiên đã hết hạn, hãy tải lại.');
  // Avoid publishing a report or reply containing payment details, PII or exploit instructions.
  if($visibility==='public'){
    $st=kp_db()->prepare(kp_forum_sql('SELECT title,body FROM kp_forum_topics WHERE id=?'));
    $st->execute([$id]);$original=$st->fetch(PDO::FETCH_ASSOC);
    if(!$original)throw new RuntimeException('Không tìm thấy bài viết.');
    if(kp_forum_sensitive_content((string)$original['title']."\n".(string)$original['body'])
       || ($reply!==''&&kp_forum_sensitive_content($reply)))
       throw new RuntimeException('Nội dung có thể nhạy cảm, hãy ẩn bài và xử lý riêng thay vì công khai.');
  }
  $result=kp_forum_admin_update(kp_db(),$id,(int)$admin['id'],$status,$visibility,$reply,(string)($_POST['nonce']??''));
  $_SESSION['kp_forum_admin_nonce']=bin2hex(random_bytes(32));
  if($result['changed']){
    kp_center_notify((int)$result['user_id'],'Cập nhật báo lỗi / góp ý #'.$id,
      $reply!==''?'Admin đã phản hồi bài #'.$id.'. Vào diễn đàn để xem khi bài công khai.':'Trạng thái yêu cầu #'.$id.' đã thay đổi.','system','/dien-dan');
  }
  if(function_exists('kp_admin_log_event'))kp_admin_log_event('forum_ticket_updated',(int)$admin['id']);
  header('Location: /quan-tri/forum?ok=1',true,303);exit;
 }catch(Throwable $e){kp_log('forum admin update failed',$e);$error=$e instanceof RuntimeException?$e->getMessage():'Không cập nhật được bài viết.';}
}
$filter=isset($_GET['status'])&&is_string($_GET['status'])?(string)$_GET['status']:'';
if(!in_array($filter,['new','reviewing','resolved','declined'],true))$filter='';
$rows=[];$replies=[];$total=0;$unavailable=false;
$page=filter_var($_GET['trang']??1,FILTER_VALIDATE_INT);$page=is_int($page)?max(1,min(10000,$page)):1;
try{
 $db=kp_db();$rows=kp_forum_admin_list($db,$filter,60,$page);$total=kp_forum_admin_count($db,$filter);
 if($rows){
  $ids=array_map('intval',array_column($rows,'id'));
  $st=$db->prepare(kp_forum_sql('SELECT topic_id,body,created_at FROM kp_forum_replies WHERE topic_id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY id ASC'));
  $st->execute($ids);foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r)$replies[(int)$r['topic_id']][]=$r;
 }
}catch(Throwable $e){kp_log('forum admin list unavailable',$e);$unavailable=true;}
$statusLabels=['new'=>'Mới tiếp nhận','reviewing'=>'Đang xử lý','resolved'=>'Đã giải quyết','declined'=>'Không thực hiện'];
?><!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Quản lý báo lỗi & góp ý · KPAH</title>
<link rel="stylesheet" href="/assets/css/admin-home.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin-home.css') ?>">
<link rel="stylesheet" href="/assets/css/admin-nav.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin-nav.css') ?>">
<link rel="stylesheet" href="/assets/css/admin-theme.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin-theme.css') ?>">
<link rel="stylesheet" href="/assets/css/forum-feedback.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/forum-feedback.css') ?>">
</head><body><?php kp_admin_nav('forum','admin'); ?>
<main class="shell ff-admin-shell">
 <section class="ff-admin-heading"><div><span class="ff-kicker">ADMIN / FORUM</span><h1>Báo lỗi & góp ý</h1><p>Đọc yêu cầu, xử lý, trả lời công khai và kiểm soát các bài có thông tin nhạy cảm.</p></div><a href="/dien-dan" target="_blank" rel="noopener noreferrer">Xem diễn đàn ↗</a></section>
 <?php if($error):?><div class="ff-notice bad" role="alert"><?=kp_e($error)?></div><?php endif;?>
 <?php if($success):?><div class="ff-notice good" role="status"><?=kp_e($success)?></div><?php endif;?>
 <nav class="ff-filters" aria-label="Trạng thái bài">
  <?php foreach([''=>'Tất cả','new'=>'Mới','reviewing'=>'Đang xử lý','resolved'=>'Đã xong','declined'=>'Không thực hiện'] as $key=>$label):?>
   <a class="<?=$filter===$key?'selected':''?>" href="/quan-tri/forum<?=$key!==''?'?status='.kp_e($key):''?>"><?=kp_e($label)?></a>
  <?php endforeach;?>
 </nav>
 <?php if($unavailable):?><p class="ff-notice bad">Chưa có bảng diễn đàn. Import database/forum_feedback.sql vào database game/web.</p>
 <?php elseif(!$rows):?><p class="ff-empty">Không có báo lỗi hoặc góp ý nào trong bộ lọc này.</p>
 <?php else:?>
 <div class="ff-admin-list">
 <?php foreach($rows as $t):?>
  <article class="ff-admin-ticket" id="ticket-<?=(int)$t['id']?>">
   <div class="ff-topic-meta"><span class="ff-pill <?=$t['kind']==='bug'?'bug':'idea'?>"><?= $t['kind']==='bug'?'Báo lỗi':'Góp ý' ?></span><span class="ff-status status-<?=kp_e($t['status'])?>"><?=kp_e($statusLabels[$t['status']]??$t['status'])?></span><span><?= $t['visibility']==='public'?'Đang công khai':'ĐANG ẨN / CẦN KIỂM DUYỆT' ?></span><span><?=kp_e($t['created_at'])?></span></div>
   <h2>#<?=(int)$t['id']?> · <?=kp_e($t['title'])?></h2>
   <p class="ff-admin-owner">Tài khoản thật (chỉ Admin): <strong><?=kp_e($t['account_name']??'Không tìm thấy')?></strong> · Hiển thị công khai: <b><?=kp_e($t['public_alias'])?></b> · Nền tảng: <?=kp_e($t['platform'])?></p>
   <div class="ff-body"><?=kp_e($t['body'])?></div>
   <?php foreach($replies[(int)$t['id']]??[] as $r):?><div class="ff-admin-reply-history"><strong>Phản hồi Admin · <?=kp_e($r['created_at'])?></strong><p><?=kp_e($r['body'])?></p></div><?php endforeach;?>
   <form method="post" action="/quan-tri/forum" class="ff-admin-form">
    <input type="hidden" name="csrf" value="<?=kp_e($csrf)?>">
    <input type="hidden" name="nonce" value="<?=kp_e($_SESSION['kp_forum_admin_nonce'])?>">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="topic_id" value="<?=(int)$t['id']?>">
    <label>Trạng thái<select name="status"><?php foreach($statusLabels as $s=>$label):?><option value="<?=kp_e($s)?>" <?=$t['status']===$s?'selected':''?>><?=kp_e($label)?></option><?php endforeach;?></select></label>
    <label>Hiển thị<select name="visibility"><option value="public" <?=$t['visibility']==='public'?'selected':''?>>Công khai</option><option value="hidden" <?=$t['visibility']==='hidden'?'selected':''?>>Ẩn khỏi diễn đàn</option></select></label>
    <label class="ff-admin-reply-field">Phản hồi chính thức (nếu có)<textarea name="reply" rows="3" maxlength="3000" placeholder="Thông báo nguyên nhân, cách xử lý hoặc thời điểm khắc phục..."></textarea></label>
    <button type="submit" class="ff-submit">Lưu trạng thái & phản hồi</button>
   </form>
  </article>
 <?php endforeach;?>
 </div>
 <nav class="ff-filters" aria-label="Phân trang quản trị">
  <?php if($page>1):?><a href="/quan-tri/forum?trang=<?=($page-1)?><?= $filter!==''?'&amp;status='.kp_e($filter):'' ?>">← Trang trước</a><?php endif;?>
  <?php if($total>$page*60):?><a href="/quan-tri/forum?trang=<?=($page+1)?><?= $filter!==''?'&amp;status='.kp_e($filter):'' ?>">Trang tiếp →</a><?php endif;?>
 </nav>
 <?php endif;?>
</main>
<script src="/assets/js/admin-nav.js?v=1" defer></script>
<script src="/assets/js/ui-system.js?v=<?= (int)@filemtime(KP_ROOT.'/assets/js/ui-system.js') ?>" defer></script>
</body></html>
