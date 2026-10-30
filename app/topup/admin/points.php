<?php
require __DIR__.'/../../includes/bootstrap.php';
require_once __DIR__.'/../../includes/topup_core.php';
require_once __DIR__.'/../../includes/loyalty_points.php';
require_once __DIR__.'/../../includes/points_reward_display.php';
require_once __DIR__.'/../../includes/points_reward_picker.php';
require_once __DIR__.'/../../includes/points_reconciliation.php';
require_once __DIR__.'/../../includes/points_reward_admin.php';
require_once __DIR__.'/../_layout.php';

$admin=kp_topup_admin();$csrf=kp_csrf_token();$error='';$redemptions=[];$rewards=[];$ledger=[];$adminNotices=[];$ready=false;$rewardDisplays=[];$rewardVisualReady=false;$workflowEnabled=false;$redemptionTargets=[];$recipientEnabled=false;$pointAudit=[];$pointReconcile=[];$tierRequired=[];$tiersReady=false;$pointPickerCatalog=null;$pointPickerError='';$rewardUsed=[];$redemptionControlReady=false;$redemptionEnabled=true;

function kp_admin_points_reward_input(): array {
    $title=trim((string)($_POST['title']??''));$description=trim((string)($_POST['description']??''));
    $cost=filter_var($_POST['cost']??null,FILTER_VALIDATE_INT);$stockInput=trim((string)($_POST['stock']??''));
    $stock=$stockInput===''?null:filter_var($stockInput,FILTER_VALIDATE_INT);
    if($title===''||strlen($title)>120||strlen($description)>1500||$cost===false||$cost<1||$cost>100000000
        ||($stockInput!==''&&($stock===false||$stock<0||$stock>100000000)))throw new RuntimeException('Thông tin quà không hợp lệ.');
    return [$title,$description,$cost,$stock];
}

if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
 try {
  if(!kp_admin_csrf_valid($_POST['csrf']??null))throw new RuntimeException('Phiên bảo mật đã hết hạn.');
  list($allowed,$retry)=kp_rate_limit('admin_points',(string)$admin['id'],50,3600,true);
  if(!$allowed)throw new RuntimeException('Đã đạt giới hạn thao tác.');
  $action=(string)($_POST['action']??'');$notice='';$db=kp_db();
  if($action==='create_reward'){
   [$title,$description,$cost,$stock]=kp_admin_points_reward_input();
   $pointCatalog=trim((string)($_POST['point_picker_options']??''))!==''?kp_points_picker_catalog($db):null;
   $display=kp_points_picker_display_input($_POST,$pointCatalog);
   $tier=filter_var($_POST['min_tier']??0,FILTER_VALIDATE_INT);
   if($tier===false||$tier<0||$tier>5)throw new RuntimeException('Hạng đổi quà không hợp lệ.');
   if(!kp_point_reward_display_available($db) && ($display['icon_id']!==null || $display['item_id']!==null || $display['option_lines']!==''))throw new RuntimeException('Cần chạy migration hiển thị quà Point trước khi lưu icon/options.');
   $db->beginTransaction();
   try {
    $st=$db->prepare(kp_points_sql('INSERT INTO kp_points_rewards (title,description,points_cost,stock,delivery_type,is_active) VALUES (?,?,?,?,?,1)'));
    $st->execute([$title,$description,$cost,$stock,'manual']);
    $newRewardId=(int)$db->lastInsertId();
    if(kp_point_reward_display_available($db))kp_point_reward_display_save($db,$newRewardId,$display);
    kp_points_reward_set_tier($db,$newRewardId,(int)$tier);
    $db->commit();
   }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
   $notice='Đã tạo quà mới.';
  }elseif($action==='update_reward'){
   $id=filter_var($_POST['reward_id']??null,FILTER_VALIDATE_INT);if($id===false||$id<1)throw new RuntimeException('Mã quà không hợp lệ.');
   [$title,$description,$cost,$stock]=kp_admin_points_reward_input();
   $pointCatalog=trim((string)($_POST['point_picker_options']??''))!==''?kp_points_picker_catalog($db):null;
   $display=kp_points_picker_display_input($_POST,$pointCatalog);
   $tier=filter_var($_POST['min_tier']??0,FILTER_VALIDATE_INT);
   if($tier===false||$tier<0||$tier>5)throw new RuntimeException('Hạng đổi quà không hợp lệ.');
   if(!kp_point_reward_display_available($db) && ($display['icon_id']!==null || $display['item_id']!==null || $display['option_lines']!==''))throw new RuntimeException('Cần chạy migration hiển thị quà Point trước khi lưu icon/options.');
   $db->beginTransaction();
   try {
    $st=$db->prepare(kp_points_sql('UPDATE kp_points_rewards SET title=?,description=?,points_cost=?,stock=? WHERE id=?'));
    $st->execute([$title,$description,$cost,$stock,$id]);
    $check=$db->prepare(kp_points_sql('SELECT id FROM kp_points_rewards WHERE id=?'));$check->execute([$id]);
    if(!$check->fetchColumn())throw new RuntimeException('Không tìm thấy quà.');
    if(kp_point_reward_display_available($db))kp_point_reward_display_save($db,(int)$id,$display);
    kp_points_reward_set_tier($db,(int)$id,(int)$tier);
    $db->commit();
   }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
   $notice='Đã cập nhật quà #'.$id.'.';
   }elseif($action==='delete_reward'){
    $id=filter_var($_POST['reward_id']??null,FILTER_VALIDATE_INT);
    if(!is_int($id)||$id<1)throw new RuntimeException('Mã quà không hợp lệ.');
    if(($_POST['confirm_delete']??'')!=='DELETE')throw new RuntimeException('Chưa xác nhận xóa quà.');
    $removed=kp_points_admin_delete_reward($db,$id);
    if(function_exists('kp_admin_log_event'))kp_admin_log_event('points_reward_deleted',(int)$admin['id']);
    $notice='Đã xóa quà #'.$id.' ('.$removed.').';
   }elseif($action==='toggle_reward'){
   $id=filter_var($_POST['reward_id']??null,FILTER_VALIDATE_INT);$state=(string)($_POST['state']??'');
   if($id===false||$id<1||!in_array($state,['0','1'],true))throw new RuntimeException('Yêu cầu đổi trạng thái quà không hợp lệ.');
   $st=$db->prepare(kp_points_sql('UPDATE kp_points_rewards SET is_active=? WHERE id=? AND is_active<>?'));$st->execute([(int)$state,$id,(int)$state]);
   if($st->rowCount()!==1)throw new RuntimeException('Không tìm thấy quà hoặc trạng thái không đổi.');
   $notice=$state==='1'?'Đã mở lại quà.':'Đã ẩn quà an toàn; lịch sử vẫn được giữ.';
  }elseif($action==='adjust_points'){
   $username=trim((string)($_POST['username']??''));$delta=filter_var($_POST['delta']??null,FILTER_VALIDATE_INT);
   if($username===''||strlen($username)>20||$delta===false)throw new RuntimeException('Tài khoản hoặc số Point không hợp lệ.');
   $st=$db->prepare('SELECT id FROM team_user WHERE username=? AND ban=0 LIMIT 1');$st->execute([$username]);$userId=$st->fetchColumn();
   if($userId===false)throw new RuntimeException('Không tìm thấy tài khoản đang hoạt động.');
   $changed=kp_points_adjust((int)$userId,(int)$admin['id'],(int)$delta,(string)($_POST['reason']??''),(string)($_POST['token']??''));
   $notice=$changed?'Đã điều chỉnh Point và gửi thông báo cho người chơi.':'Thao tác này đã được xử lý trước đó.';
  }elseif($action==='set_redemption_enabled'){
   $enabledRaw=(string)($_POST['enabled']??'');
   if(!in_array($enabledRaw,['0','1'],true))throw new RuntimeException('Trạng thái đổi Point không hợp lệ.');
   $nextEnabled=$enabledRaw==='1';
   $changed=kp_points_redemption_set_enabled($db,$nextEnabled,(int)$admin['id']);
   if($changed && function_exists('kp_admin_log_event'))
    kp_admin_log_event($nextEnabled?'points_exchange_enabled':'points_exchange_disabled',(int)$admin['id']);
   $notice=$changed
    ?($nextEnabled?'Đã mở lại chức năng đổi quà bằng Point.':'Đã tạm tắt đổi quà bằng Point. Người chơi sẽ thấy thông báo bảo trì.')
    :'Trạng thái đổi quà bằng Point không thay đổi.';
  }elseif($action==='update_program'){
   $rate=filter_var($_POST['points_per_1000']??null,FILTER_VALIDATE_INT);if($rate===false||$rate<1||$rate>100)throw new RuntimeException('Tỷ lệ phải từ 1 đến 100 Point / 1.000đ.');
   $st=$db->prepare(kp_points_sql('UPDATE kp_points_program SET points_per_1000=? WHERE id=1'));$st->execute([$rate]);$notice='Đã cập nhật tỷ lệ tích Point.';
  }elseif(in_array($action,['approved','delivering','fulfilled','rejected'],true)){
   $id=filter_var($_POST['redemption_id']??null,FILTER_VALIDATE_INT);if($id===false||$id<1)throw new RuntimeException('Mã đổi quà không hợp lệ.');
   kp_points_review_redemption($id,(int)$admin['id'],$action,(string)($_POST['note']??''));
   $notice=['approved'=>'Đã duyệt, chờ phát quà.','delivering'=>'Đang phát quà cho người chơi.','fulfilled'=>'Đã xác nhận quà giao thành công.','rejected'=>'Đã từ chối và hoàn Point.'][$action];
  }elseif($action==='read_admin_notifications'){
   $db->exec(kp_center_sql('UPDATE kp_admin_notifications SET is_read=1 WHERE is_read=0'));$notice='Đã đánh dấu thông báo quản trị là đã đọc.';
  }else throw new RuntimeException('Thao tác không hợp lệ.');
  $_SESSION['kp_toast']=['type'=>'success','title'=>'Đã lưu thay đổi','message'=>$notice];header('Location: /quan-tri/diem-tich-luy',true,303);exit;
 }catch(Throwable $e){kp_log('admin points request failed',$e);$error=$e instanceof RuntimeException?$e->getMessage():'Không hoàn thành thao tác.';}
}

try{
 $db=kp_db();$program=$db->query(kp_points_sql('SELECT activated_at,points_per_1000 FROM kp_points_program WHERE id=1'))->fetch(PDO::FETCH_ASSOC);$ready=(bool)$program;
 $redemptionControlReady=kp_points_redemption_control_ready($db);
 $redemptionEnabled=kp_points_redemption_enabled($db);
 if($ready){
  $rewards=$db->query(kp_points_sql('SELECT id,title,description,points_cost,stock,is_active FROM kp_points_rewards ORDER BY id DESC LIMIT 70'))->fetchAll(PDO::FETCH_ASSOC);
  $rewardVisualReady=kp_point_reward_display_available($db);
  $workflowEnabled=kp_points_workflow_enabled($db);
  if($rewards){
   $ids=array_map('intval',array_column($rewards,'id'));
   $stUsed=$db->prepare(kp_points_sql('SELECT DISTINCT reward_id FROM kp_points_redemptions WHERE reward_id IN ('.implode(',',array_fill(0,count($ids),'?')).')'));
   $stUsed->execute($ids);
   foreach($stUsed->fetchAll(PDO::FETCH_COLUMN) as $usedId)$rewardUsed[(int)$usedId]=true;
  }
  $rewardDisplays=kp_point_reward_display_all($db,$rewards);
  $tiersReady=kp_points_tier_enabled($db);
  $tierRequired=kp_points_reward_required_tiers($db,$rewards);
  $redemptions=$db->query(kp_points_sql('SELECT r.id,r.user_id,r.reward_title,r.points_cost,r.status,r.admin_note,r.created_at,u.username FROM kp_points_redemptions r LEFT JOIN team_user u ON u.id=r.user_id ORDER BY (r.status="pending") DESC,r.id DESC LIMIT 120'))->fetchAll(PDO::FETCH_ASSOC);
  $recipientEnabled=kp_points_target_enabled($db);
  $redemptionTargets=kp_points_targets($db,$redemptions);
  $pointAudit=kp_points_audit_list($db,null,80);
  $pointReconcile=kp_points_reconcile($db);
  $ledger=$db->query(kp_points_sql('SELECT l.id,l.user_id,l.points_delta,l.source_type,l.note,l.created_at,u.username FROM kp_points_ledger l LEFT JOIN team_user u ON u.id=l.user_id ORDER BY l.id DESC LIMIT 100'))->fetchAll(PDO::FETCH_ASSOC);
  try{$adminNotices=kp_admin_notifications($db,12);}catch(Throwable $e){kp_log('admin points notifications unavailable',$e);}
 }
}catch(Throwable $e){kp_log('admin points dashboard query failed',$e);$error=$error?:'Không tải được hệ thống Point. Kiểm tra SQL migration.';}
if($ready) {
 try {$pointPickerCatalog=kp_points_picker_catalog(kp_db());}
 catch(Throwable $e) {if(function_exists('kp_log'))kp_log('points visual catalog unavailable',$e);
  $pointPickerError='Catalog data_item/data_attribute chưa sẵn sàng. Có thể nhập icon và mô tả thủ công.';}
}
$pending=0;$unread=0;foreach($redemptions as $x)if($x['status']==='pending')$pending++;foreach($adminNotices as $x)if(!(int)$x['is_read'])$unread++;
$statusLabels=['pending'=>'Chờ duyệt','approved'=>'Đã duyệt · chờ phát','delivering'=>'Đang phát quà','fulfilled'=>'Đã giao quà','rejected'=>'Đã từ chối'];
$nextDeliveryAction=static function(string $state,bool $workflow): array {
 if(!$workflow)return $state==='pending'?['fulfilled'=>'Đã phát quà','rejected'=>'Từ chối + hoàn Point']:[];
 return ['pending'=>['approved'=>'Duyệt đơn','rejected'=>'Từ chối + hoàn Point'],
         'approved'=>['delivering'=>'Bắt đầu phát','rejected'=>'Từ chối + hoàn Point'],
         'delivering'=>['fulfilled'=>'Xác nhận đã giao']][$state]??[];
};
kp_topup_page_start('Quản lý Point','points',(string)$admin['username'],true); ?>
<section class="tp-hero"><span class="tp-kicker"><i></i> QUẢN TRỊ / POINT &amp; QUÀ</span><h1>Point, quà &amp; <em>đơn chờ duyệt.</em></h1><p class="tp-lead">Mọi cộng/trừ Point đều lưu vào sổ giao dịch. Quà được ẩn thay vì xoá để không làm mất lịch sử.</p></section>
<?php if($error):?><div class="tp-alert bad" role="alert"><?=kp_e($error)?></div><div hidden data-ui-toast="error" data-ui-toast-title="Chưa lưu được" data-ui-toast-message="<?=kp_e($error)?>"></div><?php endif;?>
<?php if(!$ready):?><div class="tp-alert warning">Chưa có bảng Point. Hãy chạy <code>database/loyalty_points.sql</code> và <code>database/user_center.sql</code>.</div><?php else: ?>
<nav class="point-admin-jump" aria-label="Đi nhanh đến mục quản trị Point"><a href="#point-admin-redemption-switch">Bật/Tắt đổi Point</a><a href="#point-admin-control">Điều chỉnh Point</a><a href="#point-admin-rewards">Quản lý quà</a><a href="#point-admin-orders">Đơn chờ duyệt</a><a href="#point-admin-ledger">Lịch sử Point</a><a href="#point-admin-audit">Nhật ký chi tiết</a><a href="#point-admin-reconcile">Đối soát</a></nav>
<section class="tp-section point-admin-redemption-section" id="point-admin-redemption-switch">
 <div class="tp-panel point-admin-redemption-panel">
  <div class="point-admin-redemption-copy">
   <span class="tp-eyebrow">CẤU HÌNH CỬA HÀNG POINT</span>
   <h2>Bật / Tắt đổi quà bằng Point</h2>
   <p class="tp-muted">Chỉ tạm dừng tạo yêu cầu đổi quà mới. Người chơi vẫn tích Point, xem số dư và lịch sử; các đơn chờ duyệt vẫn được xử lý.</p>
   <span class="point-admin-redemption-state <?=$redemptionEnabled?'is-on':'is-off'?>" role="status">
    <?=$redemptionEnabled?'Đang mở đổi quà':'Đang tạm bảo trì'?>
   </span>
  </div>
  <div class="point-admin-redemption-control">
   <?php if(!$redemptionControlReady):?>
    <div class="tp-alert warning" role="alert">Chưa bật được công tắc. Cần chạy <code>database/points_stage7_redemption_control.sql</code> trong SQL trước khi sử dụng.</div>
   <?php else:?>
   <form method="post" class="point-admin-redemption-form">
    <input type="hidden" name="csrf" value="<?=kp_e($csrf)?>">
    <input type="hidden" name="action" value="set_redemption_enabled">
    <button type="submit" name="enabled" value="<?=$redemptionEnabled?'0':'1'?>"
      class="point-admin-redemption-button <?=$redemptionEnabled?'switch-off':'switch-on'?>"
      aria-label="<?=$redemptionEnabled?'Tắt chức năng đổi quà Point':'Bật lại chức năng đổi quà Point'?>">
      <span class="point-admin-redemption-indicator" aria-hidden="true"></span>
      <?=$redemptionEnabled?'Tắt đổi quà Point':'Bật lại đổi quà Point'?>
    </button>
   </form>
   <?php endif;?>
  </div>
 </div>
</section>
<section class="tp-section"><div class="tp-summary"><div><small>Đơn đang chờ</small><strong><?=$pending?></strong></div><div><small>Thông báo chưa đọc</small><strong><?=$unread?></strong></div><div><small>Quà đang mở</small><strong><?=count(array_filter($rewards,static fn($x)=>(int)$x['is_active']===1))?></strong></div></div></section>
<?php if($pending>0):?><div class="tp-alert warning" role="status"><strong>Có <?=$pending?> đơn đổi quà đang đợi duyệt.</strong> Kiểm tra từng đơn trước khi xác nhận đã phát quà.</div><?php endif;?>

<section class="tp-section" id="point-admin-control"><div class="tp-grid two">
<div class="tp-panel"><span class="tp-eyebrow">ĐIỀU CHỈNH POINT</span><h2>Cộng hoặc trừ Point</h2><p class="tp-muted">Không sửa thẳng số dư. Hệ thống tạo bút toán có mã chống gửi lặp và thông báo cho người chơi.</p>
<form method="post" class="tp-form"><input type="hidden" name="csrf" value="<?=kp_e($csrf)?>"><input type="hidden" name="action" value="adjust_points"><input type="hidden" name="token" value="<?=bin2hex(random_bytes(16))?>"><label class="tp-field"><span>Tên tài khoản chính xác</span><input class="tp-input" name="username" maxlength="20" required autocomplete="off"></label><label class="tp-field"><span>Số Point (+ để cộng, - để trừ)</span><input class="tp-input" type="number" name="delta" min="-100000000" max="100000000" required></label><label class="tp-field"><span>Lý do</span><input class="tp-input" name="reason" maxlength="180" required placeholder="Ví dụ: bù sự kiện tuần 3"></label><button class="tp-button" type="submit">Ghi bút toán Point ↗</button></form></div>
<div class="tp-panel"><span class="tp-eyebrow">CHƯƠNG TRÌNH</span><h2>Tỷ lệ tích Point</h2><form method="post" class="tp-form"><input type="hidden" name="csrf" value="<?=kp_e($csrf)?>"><input type="hidden" name="action" value="update_program"><label class="tp-field"><span>Point trên mỗi 1.000đ nạp hợp lệ</span><input class="tp-input" type="number" name="points_per_1000" min="1" max="100" value="<?=(int)$program['points_per_1000']?>" required></label><button class="tp-button-secondary" type="submit">Cập nhật tỷ lệ</button></form>
<hr><span class="tp-eyebrow">THÔNG BÁO ADMIN</span><h2>Hoạt động mới</h2><?php if(!$adminNotices):?><p class="tp-muted">Chưa có thông báo vận hành.</p><?php else:?><div class="tp-notice-list"><?php foreach($adminNotices as $n):?><article class="tp-mini-notice<?=!(int)$n['is_read']?' is-unread':''?>"><strong><?=kp_e($n['title'])?></strong><small><?=kp_e($n['created_at'])?></small><p><?=kp_e($n['body'])?></p></article><?php endforeach;?></div><?php if($unread):?><form method="post"><input type="hidden" name="csrf" value="<?=kp_e($csrf)?>"><input type="hidden" name="action" value="read_admin_notifications"><button class="tp-button-secondary tp-btn--small" type="submit">Đánh dấu đã đọc</button></form><?php endif;?><?php endif;?></div>
</div></section>

<section class="tp-section" id="point-admin-rewards"><div class="tp-panel"><span class="tp-eyebrow">CRUD QUÀ TẶNG</span><h2>Tạo quà mới</h2><div class="point-tier-admin-strip" aria-label="Huy hiệu K của các hạng thành viên">
<?php foreach(kp_points_tiers() as $tier):?><div class="point-tier-admin-chip"><img src="<?=kp_e($tier['image'])?>" width="56" height="56" alt="Huy hiệu K <?=kp_e($tier['name'])?>" loading="lazy"><span><?=kp_e($tier['name'])?><small>từ <?=number_format((int)$tier['threshold'],0,',','.')?> Point</small></span></div><?php endforeach;?></div><?php if(!$rewardVisualReady):?><p class="tp-alert warning">Cần chạy <code>database/points_stage1_reward_display.sql</code> trên database để bật ảnh và option.</p><?php endif;?><form method="post" class="tp-form tp-reward-create"><input type="hidden" name="csrf" value="<?=kp_e($csrf)?>"><input type="hidden" name="action" value="create_reward"><label class="tp-field"><span>Tên quà</span><input class="tp-input" name="title" maxlength="120" required></label><label class="tp-field"><span>Mô tả</span><textarea class="tp-textarea" name="description" maxlength="1500"></textarea></label><label class="tp-field"><span>Giá Point</span><input class="tp-input" type="number" name="cost" min="1" max="100000000" required></label><label class="tp-field"><span>Kho (trống = không giới hạn)</span><input class="tp-input" type="number" name="stock" min="0" max="100000000"></label><div class="point-reward-editor" data-point-editor><p class="tp-muted">Chọn vật phẩm, icon và option trực quan từ catalog game. Chỉ dùng để hiển thị; Admin vẫn phải phát quà thủ công.</p><button type="button" class="tp-button-secondary point-picker-open" data-point-open>Chọn vật phẩm / icon / options</button><input type="hidden" name="point_picker_options" value="" data-point-options><input type="hidden" name="item_source" value="data_item"><div class="point-picker-selection" data-point-selection aria-live="polite"></div><label class="tp-field"><span>Icon game (ID trong res/icon)</span><input class="tp-input" name="icon_id" type="number" min="0" max="999999" placeholder="Ví dụ 123"></label><label class="tp-field"><span>ID vật phẩm (để đối chiếu, KHÔNG tự phát)</span><input class="tp-input" name="item_id" type="number" min="0" max="999999"></label><label class="tp-field"><span>Chỉ số / option (mỗi dòng một chỉ số)</span><textarea class="tp-textarea" name="option_lines" maxlength="1500" placeholder="Sát thương +120&#10;HP +500"></textarea></label></div><label class="tp-field"><span>Hạng tối thiểu để đổi</span><select class="tp-input" name="min_tier"><?php foreach(kp_points_tiers() as $t):?><option value="<?=(int)$t['id']?>"><?=kp_e($t['name'])?> · từ <?=number_format($t['threshold'],0,',','.')?> Point</option><?php endforeach;?></select></label><button class="tp-button" type="submit">Tạo quà ↗</button></form>
<h2 class="tp-mt-17">Danh mục quà</h2><?php if(!$tiersReady):?><p class="tp-muted">Chạy <code>database/points_stage6_tiers.sql</code> để mở giới hạn quà theo hạng thành viên.</p><?php endif;?><?php if(!$rewards):?><p class="tp-muted">Chưa đăng quà nào.</p><?php else:?><div class="tp-reward-admin-grid"><?php foreach($rewards as $x):?><article class="tp-card<?=!(int)$x['is_active']?' is-disabled':''?>"><?php if($img=kp_point_reward_icon($rewardDisplays[(int)$x['id']]??[])):?><div class="point-reward-icon"><img src="<?=kp_e($img)?>" alt="" loading="lazy"></div><?php endif;?><form method="post" class="tp-form"><input type="hidden" name="csrf" value="<?=kp_e($csrf)?>"><input type="hidden" name="action" value="update_reward"><input type="hidden" name="reward_id" value="<?=(int)$x['id']?>"><label class="tp-field"><span>Tên quà #<?=(int)$x['id']?></span><input class="tp-input" name="title" maxlength="120" value="<?=kp_e($x['title'])?>" required></label><label class="tp-field"><span>Mô tả</span><textarea class="tp-textarea" name="description" maxlength="1500"><?=kp_e($x['description']??'')?></textarea></label><div class="point-reward-editor" data-point-editor><button type="button" class="tp-button-secondary point-picker-open" data-point-open>Chọn lại item / icon / option</button><input type="hidden" name="point_picker_options" value="" data-point-options><input type="hidden" name="item_source" value="<?=kp_e($rewardDisplays[(int)$x['id']]['item_source']??'data_item')?>"><div class="point-picker-selection" data-point-selection aria-live="polite"></div><div class="tp-grid two"><label class="tp-field"><span>Icon game</span><input class="tp-input" type="number" name="icon_id" min="0" max="999999" value="<?=kp_e($rewardDisplays[(int)$x['id']]['icon_id']??'')?>"></label><label class="tp-field"><span>ID vật phẩm đối chiếu</span><input class="tp-input" type="number" name="item_id" min="0" max="999999" value="<?=kp_e($rewardDisplays[(int)$x['id']]['item_id']??'')?>"></label><label class="tp-field"><span>Option quà (mỗi dòng)</span><textarea class="tp-textarea" name="option_lines" maxlength="1500"><?=kp_e($rewardDisplays[(int)$x['id']]['option_lines']??'')?></textarea></label><label class="tp-field"><span>Giá Point</span><input class="tp-input" type="number" name="cost" min="1" max="100000000" value="<?=(int)$x['points_cost']?>" required></label><label class="tp-field"><span>Kho</span><input class="tp-input" type="number" name="stock" min="0" max="100000000" value="<?=$x['stock']===null?'':(int)$x['stock']?>" placeholder="∞"></label></div></div><label class="tp-field"><span>Hạng tối thiểu</span><select class="tp-input" name="min_tier"><?php foreach(kp_points_tiers() as $t):?><option value="<?=(int)$t['id']?>" <?=((int)($tierRequired[(int)$x['id']]??0)===(int)$t['id'])?'selected':''?>><?=kp_e($t['name'])?></option><?php endforeach;?></select></label><button class="tp-button-secondary tp-btn--small" type="submit">Lưu chỉnh sửa</button></form><div class="point-reward-admin-actions">
<form method="post"><input type="hidden" name="csrf" value="<?=kp_e($csrf)?>"><input type="hidden" name="action" value="toggle_reward"><input type="hidden" name="reward_id" value="<?=(int)$x['id']?>"><input type="hidden" name="state" value="<?=(int)$x['is_active']?0:1?>"><button class="tp-button-secondary tp-btn--small" type="submit"><?=(int)$x['is_active']?'Ẩn quà':'Mở lại quà'?></button></form>
<?php if(!isset($rewardUsed[(int)$x['id']])):?>
<details class="point-reward-delete-confirm">
 <summary>Xóa quà</summary>
 <div class="point-reward-delete-body">
  <p><strong>Xóa vĩnh viễn quà #<?=(int)$x['id']?>?</strong> Không thể hoàn tác; icon, options và hạng thành viên của quà cũng bị xóa.</p>
  <form method="post" class="point-reward-delete-form">
   <input type="hidden" name="csrf" value="<?=kp_e($csrf)?>">
   <input type="hidden" name="action" value="delete_reward">
   <input type="hidden" name="reward_id" value="<?=(int)$x['id']?>">
   <input type="hidden" name="confirm_delete" value="DELETE">
   <button class="point-reward-delete-submit" type="submit">Xác nhận xóa quà #<?=(int)$x['id']?></button>
  </form>
 </div>
</details>
<?php else:?><p class="point-reward-delete-locked">Đã có lịch sử đổi — chỉ có thể ẩn để bảo vệ giao dịch.</p><?php endif;?>
</div></article><?php endforeach;?></div><?php endif;?></div></section>

<section class="tp-section" id="point-admin-orders">
<div class="tp-panel"><span class="tp-eyebrow">DUYỆT ĐỔI QUÀ</span><h2>Danh sách yêu cầu</h2>
<?php if(!$workflowEnabled):?><p class="tp-muted">Chạy <code>database/points_stage2_redemption_events.sql</code> để mở quy trình duyệt → phát → đã giao; trước khi chạy sẽ dùng quy trình cũ.</p><?php endif;?>
<?php if(!$redemptions):?><p class="tp-muted">Chưa có yêu cầu.</p>
<?php else:?>
<div class="tp-table-wrap point-admin-orders-wrap" tabindex="0" role="region" aria-label="Danh sách yêu cầu đổi quà">
<table class="tp-table point-admin-orders-table">
<thead><tr><th scope="col">Mã</th><th scope="col">Người đổi</th><th scope="col">Quà</th><th scope="col">Point</th><th scope="col">Ngày</th><th scope="col">Trạng thái</th><th scope="col">Thao tác</th></tr></thead>
<tbody>
<?php foreach($redemptions as $x):?>
<tr>
<td data-label="Mã"><span>#<?=(int)$x['id']?></span></td>
<td data-label="Người đổi"><span><?=kp_e($x['username']??('ID '.$x['user_id']))?></span></td>
<td data-label="Quà"><div class="point-admin-order-reward"><strong><?=kp_e($x['reward_title'])?></strong>
<?php if(isset($redemptionTargets[(int)$x['id']])):$target=$redemptionTargets[(int)$x['id']];?>
<small>Nhân vật: <?=kp_e($target['char_name'])?> (#<?=(int)$target['char_id']?>)</small>
<?php else:?><small>Chưa có nhân vật nhận</small><?php endif;?></div></td>
<td data-label="Point"><span><?=number_format((int)$x['points_cost'],0,',','.')?></span></td>
<td data-label="Ngày"><span><?=kp_e($x['created_at'])?></span></td>
<td data-label="Trạng thái"><span class="tp-pill <?=kp_e($x['status'])?>"><?=kp_e($statusLabels[$x['status']]??$x['status'])?></span></td>
<td data-label="Thao tác">
<?php $actions=$nextDeliveryAction((string)$x['status'],$workflowEnabled);if($actions):?>
<form method="post" class="tp-form point-admin-order-form">
<input type="hidden" name="csrf" value="<?=kp_e($csrf)?>">
<input type="hidden" name="redemption_id" value="<?=(int)$x['id']?>">
<label class="point-admin-order-note"><span class="point-admin-sr-only">Ghi chú xử lý đơn #<?=(int)$x['id']?></span>
<input class="tp-input" name="note" maxlength="200" required placeholder="Ghi chú xử lý / bằng chứng đã giao"></label>
<div class="point-admin-order-buttons"><?php foreach($actions as $value=>$caption):?>
<button name="action" value="<?=kp_e($value)?>" type="submit" class="tp-button-secondary tp-btn--small"><?=kp_e($caption)?></button>
<?php endforeach;?></div></form>
<?php else:?><span><?=kp_e($x['admin_note'])?></span><?php endif;?>
</td></tr>
<?php endforeach;?></tbody></table></div><?php endif;?></div></section>
<section class="tp-section" id="point-admin-ledger"><div class="tp-panel"><span class="tp-eyebrow">AUDIT POINT</span><h2>100 bút toán gần nhất</h2><p class="tp-muted">Lịch sử chỉ được ghi nối tiếp, không cho sửa hoặc xoá.</p><?php if(!$ledger):?><p class="tp-muted">Chưa có biến động Point.</p><?php else:?><p class="point-admin-scroll-hint">Vuốt ngang để xem hết các cột của bảng lịch sử.</p><div class="tp-table-wrap point-admin-scroll" tabindex="0" role="region" aria-label="Lịch sử 100 bút toán Point"><table class="tp-table"><thead><tr><th>Mã</th><th>Tài khoản</th><th>Point</th><th>Nguồn</th><th>Nội dung</th><th>Thời gian</th></tr></thead><tbody><?php foreach($ledger as $x):?><tr><td>#<?=(int)$x['id']?></td><td><?=kp_e($x['username']??('ID '.$x['user_id']))?></td><td><strong><?=((int)$x['points_delta']>0?'+':'').number_format((int)$x['points_delta'],0,',','.')?></strong></td><td><?=kp_e($x['source_type'])?></td><td><?=kp_e($x['note'])?></td><td><?=kp_e($x['created_at'])?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div></section>
<section class="tp-section" id="point-admin-audit"><div class="tp-panel"><span class="tp-eyebrow">AUDIT / IP · THIẾT BỊ</span><h2>Nhật ký Point chi tiết</h2>
<p class="tp-muted">Admin xem tất cả. Thời gian hiển thị đến giây; IP được ghi từ kết nối máy chủ, vị trí chỉ là GeoIP ước lượng nếu có.</p>
<?php if(!kp_points_audit_enabled($db)):?><p class="tp-alert warning">Chạy <code>database/points_stage4_audit.sql</code> để ghi lịch sử chi tiết mới. Giao dịch cũ không thể phục dựng IP và thiết bị.</p>
<?php elseif(!$pointAudit):?><p class="tp-muted">Chưa có bút toán audit mới.</p>
<?php else:?><div class="tp-table-wrap point-audit-scroll" tabindex="0" role="region" aria-label="Nhật ký Point tất cả tài khoản có thể cuộn ngang"><table class="tp-table point-audit-table">
<thead><tr><th>Thời gian</th><th>Tài khoản #</th><th>Thao tác</th><th>Point +/-</th><th>Trước → Sau</th><th>Admin #</th><th>IP</th><th>Thiết bị</th><th>Vị trí IP (ước lượng)</th></tr></thead>
<tbody><?php foreach($pointAudit as $a):?><tr>
<td><?=kp_e($a['created_at'])?></td><td><?=(int)$a['user_id']?></td><td><?=kp_e($a['operation'])?> · <?=kp_e($a['reference'])?></td>
<td><?=((int)$a['points_delta']>=0?'+':'').number_format((int)$a['points_delta'],0,',','.')?></td>
<td><?=$a['balance_before']===null?'—':number_format((int)$a['balance_before'],0,',','.')?> → <?=$a['balance_after']===null?'—':number_format((int)$a['balance_after'],0,',','.')?></td>
<td><?=$a['actor_admin_id']===null?'—':(int)$a['actor_admin_id']?></td>
<td><?=kp_e($a['ip']!==''?$a['ip']:'Không có dữ liệu')?></td>
<td><?=kp_e($a['device_type'].' · '.$a['browser'])?></td>
<td><?=kp_e($a['location_hint']!==''?$a['location_hint'].' (ước lượng)':'Không xác định')?></td>
</tr><?php endforeach;?></tbody></table></div><?php endif;?></div></section>
<section class="tp-section" id="point-admin-reconcile"><div class="tp-panel"><span class="tp-eyebrow">KIỂM TRA CHÉO · CHỈ ĐỌC</span><h2>Đối soát Point</h2>
<p class="tp-muted">Ba nhóm cần kiểm tra thủ công, mỗi nhóm tối đa 100 kết quả. Trang này không sửa số dư, không gọi webhook và không phát lại quà.</p>
<div class="tp-summary point-reconcile-summary">
<div><small>Ví khác sổ bút toán</small><strong><?=count($pointReconcile['wallet_mismatch']??[])?></strong></div>
<div><small>Đơn quá 24 giờ chưa xong</small><strong><?=count($pointReconcile['overdue_orders']??[])?></strong></div>
<div><small>Bank chưa thấy bút toán Point</small><strong><?=count($pointReconcile['missing_payos_ledger']??[])?></strong></div></div>
<?php if(!empty($pointReconcile['errors'])):?><p class="tp-alert warning">Không đọc được đầy đủ: <?=kp_e(implode(', ',$pointReconcile['errors']))?>. Không tự đối soát hoặc cấp lại.</p><?php endif;?>
<?php foreach(['wallet_mismatch'=>'Chênh lệch số dư ví / ledger','overdue_orders'=>'Yêu cầu đổi quà còn tồn quá 24 giờ','missing_payos_ledger'=>'Đơn nạp có thể thiếu Point — cần kiểm tra trạng thái ví'] as $key=>$caption):?>
<details class="point-reconcile-group"><summary><?=kp_e($caption)?> (<?=count($pointReconcile[$key]??[])?>)</summary>
<?php if(empty($pointReconcile[$key])):?><p class="tp-muted">Chưa phát hiện trong phạm vi dữ liệu được kiểm tra.</p><?php else:?>
<div class="tp-table-wrap point-audit-scroll" tabindex="0" role="region" aria-label="<?=kp_e($caption)?>"><table class="tp-table point-audit-table"><thead><tr>
<?php foreach(array_keys($pointReconcile[$key][0]) as $col):?><th><?=kp_e($col)?></th><?php endforeach;?></tr></thead><tbody>
<?php foreach($pointReconcile[$key] as $entry):?><tr><?php foreach($entry as $value):?><td><?=kp_e((string)$value)?></td><?php endforeach;?></tr><?php endforeach;?>
</tbody></table></div><?php endif;?></details><?php endforeach;?></div></section>
<?php endif;?>
<?php if($pointPickerError):?><div class="tp-alert warning"><?=kp_e($pointPickerError)?></div><?php endif;?>
<?php if($pointPickerCatalog):
$json=json_encode($pointPickerCatalog,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
?>
<script type="application/json" id="point-reward-catalog"><?=is_string($json)?$json:'{}'?></script>
<dialog id="point-reward-picker" class="point-picker-dialog" aria-label="Chọn icon vật phẩm và chỉ số">
  <header><strong>Chọn icon, vật phẩm và chỉ số</strong><button type="button" data-point-close aria-label="Đóng">×</button></header>
  <div class="point-picker-tabs" role="tablist" aria-label="Chọn loại dữ liệu">
    <button type="button" id="point-picker-tab-items" role="tab" aria-controls="point-picker-items" aria-selected="true" aria-pressed="true" data-point-tab="items">Trang bị · data_item</button>
    <button type="button" id="point-picker-tab-shop" role="tab" aria-controls="point-picker-items" aria-selected="false" aria-pressed="false" data-point-tab="shop">Data Shop</button>
    <button type="button" id="point-picker-tab-options" role="tab" aria-controls="point-picker-options" aria-selected="false" aria-pressed="false" data-point-tab="options">Chỉ số (Options)</button>
  </div>
  <label class="tp-field"><span>Tìm theo tên hoặc ID</span><input type="search" class="tp-input" id="point-picker-search" placeholder="Tên vật phẩm hoặc #123" autocomplete="off"></label>
  <div id="point-picker-selected-option" class="point-picker-param" hidden>
    <span id="point-picker-option-title" role="status"></span>
    <label>Giá trị option<input type="number" id="point-picker-param-value" min="1" max="32767" value="1"></label>
    <button type="button" id="point-picker-add-option">Thêm chỉ số</button>
  </div>
  <div id="point-picker-items" class="point-picker-items" role="tabpanel" aria-labelledby="point-picker-tab-items"></div>
  <div id="point-picker-options" class="point-picker-options" role="tabpanel" aria-labelledby="point-picker-tab-options" hidden></div>
  <div class="point-picker-actions"><p id="point-picker-count" role="status" aria-live="polite"></p><button type="button" id="point-picker-more">Hiển thị thêm</button></div>
  <p class="tp-muted point-picker-help">Chọn nguồn data_item hoặc Data Shop. ID được lưu kèm loại nguồn (sau migration stage8). Data Shop là vật phẩm đặc biệt và không có option data_item. Quà Point chỉ để hiển thị, Admin vẫn phát thủ công.</p>
</dialog>
<script src="/assets/js/points-reward-picker.js?v=<?= (int)@filemtime(KP_ROOT.'/assets/js/points-reward-picker.js') ?>" defer></script>
<?php endif;?>
<?php kp_topup_page_end(); ?>
