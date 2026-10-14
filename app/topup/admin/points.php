<?php
require __DIR__.'/../../includes/bootstrap.php';
require_once __DIR__.'/../../includes/topup_core.php';
require_once __DIR__.'/../../includes/loyalty_points.php';
require_once __DIR__.'/../_layout.php';
$admin=kp_topup_admin();$csrf=kp_csrf_token();$error='';$notice='';$redemptions=[];$rewards=[];$ready=false;
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
 try {
  if(!kp_admin_csrf_valid($_POST['csrf']??null))throw new RuntimeException('Phiên bảo mật đã hết hạn.');
  list($allowed,$retry)=kp_rate_limit('admin_points',(string)$admin['id'],40,3600,true);
  if(!$allowed)throw new RuntimeException('Đã đạt giới hạn thao tác.');
  $action=(string)($_POST['action']??'');
  if($action==='create_reward'){
   $title=trim((string)($_POST['title']??''));
   $description=trim((string)($_POST['description']??''));
   $cost=filter_var($_POST['cost']??null,FILTER_VALIDATE_INT);
   $stockInput=trim((string)($_POST['stock']??''));
   $stock=$stockInput===''?null:filter_var($stockInput,FILTER_VALIDATE_INT);
   if($title===''||strlen($title)>120||strlen($description)>1500||$cost===false||$cost<1||$cost>100000000
      ||($stockInput!==''&&($stock===false||$stock<0)))throw new RuntimeException('Thông tin quà không hợp lệ.');
   $st=kp_db()->prepare('INSERT INTO kp_points_rewards (title,description,points_cost,stock,delivery_type,is_active) VALUES (?,?,?,?,?,1)');
   $st->execute([$title,$description,$cost,$stock,'manual']);$notice='Đã đăng quà mới.';
  }elseif($action==='disable_reward'){
   $id=filter_var($_POST['reward_id']??null,FILTER_VALIDATE_INT);
   if($id===false||$id<1)throw new RuntimeException('Mã quà không hợp lệ.');
   kp_db()->prepare('UPDATE kp_points_rewards SET is_active=0 WHERE id=?')->execute([$id]);$notice='Đã ẩn quà.';
  }elseif(in_array($action,['fulfilled','rejected'],true)){
   $id=filter_var($_POST['redemption_id']??null,FILTER_VALIDATE_INT);
   if($id===false||$id<1)throw new RuntimeException('Mã đổi quà không hợp lệ.');
   kp_points_review_redemption($id,(int)$admin['id'],$action,(string)($_POST['note']??''));
   $notice=$action==='fulfilled'?'Đã xác nhận phát quà.':'Đã từ chối và hoàn Point.';
  }else throw new RuntimeException('Thao tác không hợp lệ.');
 }catch(Throwable $e){kp_log('admin points request failed',$e);$error=$e instanceof RuntimeException?$e->getMessage():'Không hoàn thành thao tác.';}
}
try{
 $db=kp_db();
 $ready=(bool)$db->query('SELECT 1 FROM kp_points_program WHERE id=1')->fetchColumn();
 if($ready){
  $rewards=$db->query('SELECT id,title,points_cost,stock,is_active FROM kp_points_rewards ORDER BY id DESC LIMIT 70')->fetchAll(PDO::FETCH_ASSOC);
  $redemptions=$db->query('SELECT r.id,r.user_id,r.reward_title,r.points_cost,r.status,r.admin_note,r.created_at,u.username FROM kp_points_redemptions r LEFT JOIN team_user u ON u.id=r.user_id ORDER BY (r.status="pending") DESC,r.id DESC LIMIT 120')->fetchAll(PDO::FETCH_ASSOC);
 }
}catch(Throwable $e){kp_log('admin points dashboard query failed',$e);$error=$error?:'Không tải được hệ thống Point. Kiểm tra SQL migration.';}
kp_topup_page_start('Quản lý Point','points',(string)$admin['username'],true); ?>
<section class="tp-hero"><span class="tp-kicker"><i></i> QUẢN TRỊ / TÍCH ĐIỂM</span><h1>Quà tặng <em>Point.</em></h1><p class="tp-lead">Quà ở đây được phát thủ công. Chỉ xác nhận đã giao khi bạn thực sự phát quà cho người chơi trong game.</p></section>
<?php if($error):?><div class="tp-alert bad" role="alert"><?=kp_e($error)?></div><?php endif;?>
<?php if($notice):?><div class="tp-alert good" role="status"><?=kp_e($notice)?></div><?php endif;?>
<?php if(!$ready):?><div class="tp-alert warning">Chưa có bảng Point. Hãy chạy <code>database/loyalty_points.sql</code> trước.</div><?php else: ?>
<section class="tp-section"><div class="tp-grid two"><div class="tp-panel"><h2>Đăng quà mới</h2>
<form method="post" class="tp-form"><input type="hidden" name="csrf" value="<?=kp_e($csrf)?>"><input type="hidden" name="action" value="create_reward">
<label class="tp-field"><span>Tên quà</span><input class="tp-input" name="title" maxlength="120" required></label>
<label class="tp-field"><span>Mô tả</span><textarea class="tp-textarea" name="description" maxlength="1500"></textarea></label>
<label class="tp-field"><span>Giá Point</span><input class="tp-input" type="number" name="cost" min="1" max="100000000" required></label>
<label class="tp-field"><span>Số lượng (để trống = không giới hạn)</span><input class="tp-input" type="number" name="stock" min="0"></label>
<button class="tp-button" type="submit">Đăng quà để đổi ↗</button></form></div>
<div class="tp-panel"><h2>Danh mục quà</h2>
<?php if(!$rewards):?><p class="tp-muted">Chưa đăng quà nào.</p><?php else: ?>
<div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Quà</th><th>Point</th><th>Kho</th><th>Trạng thái</th></tr></thead><tbody>
<?php foreach($rewards as $x):?><tr><td><?=kp_e($x['title'])?><small> (#<?=(int)$x['id']?>)</small></td><td><?=number_format((int)$x['points_cost'],0,',','.')?></td><td><?= $x['stock']===null?'∞':(int)$x['stock']?></td><td><?php if((int)$x['is_active']):?><form method="post"><input type="hidden" name="csrf" value="<?=kp_e($csrf)?>"><input type="hidden" name="action" value="disable_reward"><input type="hidden" name="reward_id" value="<?=(int)$x['id']?>"><button type="submit" class="tp-button-secondary tp-btn--small">Ẩn quà</button></form><?php else:?>Đã ẩn<?php endif;?></td></tr><?php endforeach;?></tbody></table></div>
<?php endif;?></div></div></section>
<section class="tp-section"><div class="tp-panel"><h2>Danh sách đổi quà</h2>
<?php if(!$redemptions):?><p class="tp-muted">Chưa có yêu cầu.</p><?php else:?>
<div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Mã</th><th>Người đổi</th><th>Quà</th><th>Point</th><th>Ngày</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
<?php foreach($redemptions as $x):?><tr><td>#<?=(int)$x['id']?></td><td><?=kp_e($x['username']??('ID '.$x['user_id']))?></td><td><?=kp_e($x['reward_title'])?></td><td><?=number_format((int)$x['points_cost'],0,',','.')?></td><td><?=kp_e($x['created_at'])?></td><td><?=kp_e($x['status'])?></td><td>
<?php if($x['status']==='pending'):?><form method="post" class="tp-form">
<input type="hidden" name="csrf" value="<?=kp_e($csrf)?>"><input type="hidden" name="redemption_id" value="<?=(int)$x['id']?>">
<input class="tp-input" name="note" maxlength="200" required placeholder="Ghi chú phát quà / từ chối">
<button name="action" value="fulfilled" type="submit" class="tp-button-secondary tp-btn--small">Đã phát quà</button>
<button name="action" value="rejected" type="submit" class="tp-button-secondary tp-btn--small">Từ chối + hoàn Point</button></form>
<?php else: ?><?=kp_e($x['admin_note'])?><?php endif;?></td></tr><?php endforeach;?></tbody></table></div>
<?php endif;?></div></section>
<?php endif;?>
<?php kp_topup_page_end(); ?>
