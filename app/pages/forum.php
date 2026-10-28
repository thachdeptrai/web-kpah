<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/public_landing.php';
require_once __DIR__.'/../includes/forum_feedback.php';
require_once __DIR__.'/../includes/user_center.php';
kp_send_security_headers(true);header('Content-Type: text/html; charset=utf-8');
$loggedIn=(int)($_SESSION['kp_user_id']??0)>0;
$csrf=kp_csrf_token();
if(empty($_SESSION['kp_forum_submit_nonce']))$_SESSION['kp_forum_submit_nonce']=bin2hex(random_bytes(32));
$error='';$message=(string)($_SESSION['kp_forum_message']??'');unset($_SESSION['kp_forum_message']);
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 try{
  $userId=kp_player_api_user_id();
  if($userId<1)throw new RuntimeException('Bạn cần đăng nhập để gửi bài.');
  if(!kp_csrf_check($_POST['csrf']??null))throw new RuntimeException('Phiên bảo mật đã hết hạn, hãy tải lại trang.');
  if(!is_string($_POST['nonce']??null)||!hash_equals((string)$_SESSION['kp_forum_submit_nonce'],(string)$_POST['nonce']))
    throw new RuntimeException('Yêu cầu đã được gửi hoặc hết hạn. Vui lòng tải lại trang.');
  if(($_POST['public_consent']??'')!=='yes')throw new RuntimeException('Vui lòng xác nhận nội dung có thể được công khai.');
  list($ok,$retry)=kp_rate_limit('forum_user',(string)$userId,3,600,true);
  if(!$ok)throw new RuntimeException('Bạn gửi quá nhanh. Hãy thử lại sau.');
  list($ok,$retry)=kp_rate_limit('forum_ip',(string)kp_client_ip(),12,600,true);
  if(!$ok)throw new RuntimeException('Đã đạt giới hạn gửi bài từ kết nối này.');
  $db=kp_db();
  $id=kp_forum_create($db,$userId,(string)($_POST['kind']??''),(string)($_POST['platform']??''),
     $_POST['title']??'',$_POST['body']??'',(string)$_POST['nonce']);
  $_SESSION['kp_forum_submit_nonce']=bin2hex(random_bytes(32));
  $st=$db->prepare(kp_forum_sql('SELECT visibility FROM kp_forum_topics WHERE id=? AND user_id=?'));
  $st->execute([$id,$userId]);$visibility=$st->fetchColumn();
  $_SESSION['kp_forum_message']=$visibility==='public'
     ?'Đã đăng bài #'.$id.' lên diễn đàn. Ban quản trị sẽ theo dõi và phản hồi.'
     :'Đã tiếp nhận bài #'.$id.'. Nội dung có thể chứa thông tin nhạy cảm nên sẽ chờ Admin kiểm duyệt trước khi công khai.';
  header('Location: /dien-dan',true,303);exit;
 }catch(Throwable $e){kp_log('forum submission rejected',$e);$error=$e instanceof RuntimeException?$e->getMessage():'Không lưu được bài viết, vui lòng thử lại.';}
}
$kind=isset($_GET['loai'])&&is_string($_GET['loai'])?(string)$_GET['loai']:'';
$status=isset($_GET['trang-thai'])&&is_string($_GET['trang-thai'])?(string)$_GET['trang-thai']:'';
if(!in_array($kind,['bug','suggestion'],true))$kind='';
if(!in_array($status,['new','reviewing','resolved','declined'],true))$status='';
$topics=[];$replies=[];$own=[];$ownReplies=[];$total=0;$unavailable=false;
$page=filter_var($_GET['trang']??1,FILTER_VALIDATE_INT);$page=is_int($page)?max(1,min(10000,$page)):1;
try{$db=kp_db();$topics=kp_forum_public_topics($db,$kind,$status,50,$page);$total=kp_forum_public_count($db,$kind,$status);$replies=kp_forum_public_replies($db,array_column($topics,'id'));if($loggedIn){$myId=kp_player_api_user_id();$own=kp_forum_own_topics($db,$myId,20);$ownReplies=kp_forum_own_replies($db,$myId,array_column($own,'id'));}}
catch(Throwable $e){kp_log('forum public read unavailable',$e);$unavailable=true;}
$labels=['new'=>'Mới tiếp nhận','reviewing'=>'Đang xử lý','resolved'=>'Đã giải quyết','declined'=>'Không thực hiện'];
kp_public_page_start('Diễn đàn báo lỗi & góp ý','forum','Báo lỗi game, gửi góp ý và theo dõi câu trả lời chính thức từ Admin.');
?>
<section class="ff-hero"><div class="lp-container ff-hero-inner"><div><span class="lp-section-kicker">DIỄN ĐÀN CỘNG ĐỒNG</span><h1>Báo lỗi & <em>góp ý</em></h1><p>Chia sẻ vấn đề trong game, đề xuất cải tiến và theo dõi phản hồi công khai từ ban quản trị.</p><a class="ff-hero-link" href="#gui-bai">Gửi bài mới <span aria-hidden="true">↗</span></a></div><img src="/assets/img/rank-sorcerer.webp" alt="" aria-hidden="true" loading="eager"></div></section>
<div class="lp-container ff-layout">
 <section class="ff-main" aria-labelledby="ff-title">
  <header class="ff-panel-header"><div><span class="ff-kicker">CỘNG ĐỒNG ĐANG TRAO ĐỔI</span><h2 id="ff-title">Bài viết mới nhất</h2><p>Tên người gửi được ẩn một phần; chỉ Admin xem được tài khoản thật.</p></div></header>
  <nav class="ff-filters" aria-label="Lọc diễn đàn">
    <a <?= $kind===''?'class="selected" aria-current="page"':'' ?> href="/dien-dan">Tất cả</a>
    <a <?= $kind==='bug'?'class="selected" aria-current="page"':'' ?> href="/dien-dan?loai=bug">Báo lỗi</a>
    <a <?= $kind==='suggestion'?'class="selected" aria-current="page"':'' ?> href="/dien-dan?loai=suggestion">Góp ý</a>
    <a <?= $status==='resolved'?'class="selected" aria-current="page"':'' ?> href="/dien-dan?trang-thai=resolved">Đã giải quyết</a>
  </nav>
  <?php if($unavailable):?><div class="ff-notice bad">Diễn đàn chưa được khởi tạo hoặc database đang gặp sự cố. Admin cần chạy migration SQL.</div>
  <?php elseif(!$topics):?><div class="ff-empty">Chưa có bài viết phù hợp. Hãy gửi báo lỗi hoặc góp ý đầu tiên.</div>
  <?php else:?><div class="ff-topic-list">
  <?php foreach($topics as $t):$isBug=$t['kind']==='bug';?>
   <article class="ff-topic" id="bai-<?= (int)$t['id'] ?>">
    <div class="ff-topic-meta"><span class="ff-pill <?= $isBug?'bug':'idea' ?>"><?= $isBug?'Báo lỗi':'Góp ý' ?></span><span class="ff-status status-<?=kp_e($t['status'])?>"><?=kp_e($labels[$t['status']]??$t['status'])?></span><span class="ff-date"><?=kp_e(date('d/m/Y H:i',strtotime((string)$t['created_at'])))?></span></div>
    <h3><?=kp_e($t['title'])?></h3>
    <p class="ff-author"><span class="ff-avatar" aria-hidden="true"><?=kp_e(strtoupper(substr((string)$t['public_alias'],0,1)))?></span><strong><?=kp_e($t['public_alias'])?></strong><span>· <?=kp_e($t['platform'])?></span><span>· #<?=(int)$t['id']?></span></p>
    <div class="ff-body"><?=kp_e($t['body'])?></div>
    <?php foreach($replies[(int)$t['id']]??[] as $reply):?>
     <div class="ff-admin-reply"><div class="ff-reply-heading"><span>✓ Phản hồi chính thức từ Admin</span><time><?=kp_e(date('d/m/Y H:i',strtotime((string)$reply['created_at'])))?></time></div><p><?=kp_e($reply['body'])?></p></div>
    <?php endforeach;?>
   </article>
  <?php endforeach;?></div>
  <nav class="ff-filters" aria-label="Phân trang">
   <?php if($page>1):?><a href="/dien-dan?trang=<?=($page-1)?><?= $kind!==''?'&amp;loai='.kp_e($kind):'' ?><?= $status!==''?'&amp;trang-thai='.kp_e($status):'' ?>">← Trang trước</a><?php endif;?>
   <?php if($total>$page*50):?><a href="/dien-dan?trang=<?=($page+1)?><?= $kind!==''?'&amp;loai='.kp_e($kind):'' ?><?= $status!==''?'&amp;trang-thai='.kp_e($status):'' ?>">Trang tiếp →</a><?php endif;?>
  </nav>
  <?php endif;?>
 </section>
 <aside class="ff-sidebar" id="gui-bai">
  <div class="ff-compose"><span class="ff-kicker">GỬI BÀI LÊN DIỄN ĐÀN</span><h2>Bạn gặp vấn đề gì?</h2><p>Thông tin bài viết sẽ được lưu và thông báo đến Admin. Tránh đăng số điện thoại, email, mật khẩu, mã thanh toán và lỗ hổng có thể bị khai thác.</p>
   <?php if($message!==''):?><div role="status" class="ff-notice good"><?=kp_e($message)?></div><?php endif;?>
   <?php if($error!==''):?><div role="alert" class="ff-notice bad"><?=kp_e($error)?></div><?php endif;?>
   <?php if(!$loggedIn):?><div class="ff-signin">Bạn cần <a href="/dang-nhap">đăng nhập</a> để gửi bài. Mọi người vẫn có thể đọc diễn đàn công khai.</div>
   <?php else:?>
    <form method="post" action="/dien-dan" class="ff-form">
      <input type="hidden" name="csrf" value="<?=kp_e($csrf)?>">
      <input type="hidden" name="nonce" value="<?=kp_e($_SESSION['kp_forum_submit_nonce'])?>">
      <label>Loại bài<select name="kind" required><option value="bug">Báo lỗi</option><option value="suggestion" <?=($_POST['kind']??'')==='suggestion'?'selected':''?>>Góp ý / Đề xuất</option></select></label>
      <label>Nền tảng<select name="platform" required><?php foreach(['Android','iOS','Windows','J2ME','Web','Khác'] as $p):?><option value="<?=kp_e($p)?>" <?=($_POST['platform']??'')===$p?'selected':''?>><?=kp_e($p)?></option><?php endforeach;?></select></label>
      <label>Tiêu đề<input name="title" maxlength="140" minlength="8" required placeholder="Ví dụ: Không nhận được vật phẩm..." value="<?=kp_e(is_string($_POST['title']??null)?$_POST['title']:'')?>"></label>
      <label>Mô tả chi tiết<textarea name="body" rows="7" maxlength="5000" minlength="20" required placeholder="Các bước tái hiện, kết quả mong muốn, thiết bị, thời điểm xảy ra..."><?=kp_e(is_string($_POST['body']??null)?$_POST['body']:'')?></textarea></label>
      <label class="ff-consent"><input type="checkbox" name="public_consent" value="yes" required><span>Tôi đồng ý công khai nội dung không nhạy cảm trên diễn đàn; tên tài khoản sẽ được che một phần.</span></label>
      <button type="submit" class="ff-submit">Gửi cho ban quản trị ↗</button>
    </form>
   <?php endif;?>
  </div>
  <?php if($own):?><section class="ff-mine" aria-labelledby="ff-mine-heading">
    <span class="ff-kicker">LỊCH SỬ CỦA BẠN</span><h2 id="ff-mine-heading">Bài của tôi</h2>
    <p>Chỉ bạn và Admin xem được những bài đang ẩn hoặc cần kiểm duyệt.</p>
    <?php foreach($own as $mine):?><details class="ff-mine-item"><summary><strong>#<?=(int)$mine['id']?> · <?=kp_e($mine['title'])?></strong><span><?=kp_e($labels[$mine['status']]??$mine['status'])?> · <?= $mine['visibility']==='hidden'?'Riêng tư':'Công khai'?></span></summary>
      <?php if(empty($ownReplies[(int)$mine['id']])):?><p>Chưa có phản hồi mới.</p><?php else:?>
        <?php foreach($ownReplies[(int)$mine['id']] as $r):?><p class="ff-mine-answer"><b>Admin phản hồi:</b> <?=kp_e($r['body'])?></p><?php endforeach;?>
      <?php endif;?>
    </details><?php endforeach;?>
  </section><?php endif;?>
 </aside>
</div>
<?php kp_public_page_end(); ?>
