<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/topup_core.php';
require_once __DIR__ . '/../_layout.php';
$admin=kp_topup_admin();$csrf=kp_csrf_token();$cfg=kp_topup_cfg();$error='';$notice='';
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    try{
        if(!kp_admin_csrf_valid($_POST['csrf']??null))throw new RuntimeException('Phiên xác nhận đã hết hạn.');
        if(($_POST['action']??'')!=='check_card')throw new RuntimeException('Thao tác không hợp lệ.');
        $id=(string)($_POST['id']??'');
        if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new RuntimeException('Mã giao dịch không hợp lệ.');
        list($allow,$retry)=kp_rate_limit('kp_topup_admin_check',(string)$admin['id'],30,3600,true);
        if(!$allow)throw new RuntimeException('Đã đạt giới hạn kiểm tra trong giờ.');
        $result=kp_topup_reconcile($id);
        $notice='Đã kiểm tra #'.strtoupper(substr($id,0,10)).': '.kp_topup_status_label($result['status']).'.';
    }catch(RuntimeException $e){$error=$e->getMessage();}
    catch(Throwable $e){kp_log('admin card reconciliation failed',$e);$error='Không thể đối soát đơn này. Kiểm tra provider, game server và log.';}
}
$orders=kp_topup_recent('orders',null,150);
$grants=kp_topup_recent('grants',null,150);
kp_topup_page_start('Lịch sử & đối soát','orders',(string)$admin['username'],true);?>
<section class="tp-hero"><span class="tp-kicker"><i></i> ADMIN / ĐỐI SOÁT</span><h1>Kiểm tra từng <em>giao dịch.</em></h1><p class="tp-lead">Kiểm tra đơn thẻ và lịch sử cộng thủ công tại đây.</p></section>
<?php if($error): ?><div class="tp-alert bad" role="alert"><?= kp_e($error) ?></div><?php endif; ?>
<?php if($notice): ?><div class="tp-alert good" role="status"><?= kp_e($notice) ?></div><?php endif; ?>
<div class="tp-alert warning"><strong>Trạng thái cần chú ý:</strong> <code>needs_review</code> và <code>crediting</code> có thể là giao dịch đã ghi DB nhưng chưa hoàn tất lịch sử. Đối chiếu dữ liệu trước khi tiếp tục, không tự ý gửi lại. Việc kiểm tra trạng thái thẻ chỉ gọi API đối tác, không tạo thêm mã thẻ.</div>
<section class="tp-section"><div class="tp-panel"><div class="tp-panel-head"><div><span class="tp-eyebrow">NẠP THẺ CÀO</span><h2>150 đơn gần nhất</h2></div><span class="tp-chip"><?= count($orders) ?> giao dịch</span></div>
<?php if(!$orders): ?><p class="tp-muted">Chưa có giao dịch thẻ.</p>
<?php else: ?><div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Mã đơn</th><th>Ngày</th><th>Tài khoản</th><th>Thẻ</th><th>Giá trị</th><th>Đổi</th><th>Trạng thái</th><th>Kiểm tra</th></tr></thead><tbody>
<?php foreach($orders as $o): ?>
<tr><td><b><?= kp_e(strtoupper(substr($o['id'],0,10))) ?></b></td><td><?= date('d/m H:i',(int)$o['created_at']) ?></td><td><?= kp_e($o['username']) ?></td><td><?= kp_e($o['telco']) ?><br><small>Serial ···<?= kp_e($o['serial_end']) ?></small></td><td><?= number_format((int)$o['amount'],0,',','.') ?>đ</td><td><?= number_format((int)$o['units'],0,',','.') ?> <?= ($o['currency']??'')==='luonglock'?'Lượng khóa':'Lượng thường' ?></td><td><span class="tp-pill <?= kp_e($o['status']) ?>"><?= kp_e(kp_topup_status_label($o['status'])) ?></span></td>
<td><?php if(!in_array($o['status'],['queued','failed','crediting','needs_review'],true) && $cfg['card_ready']): ?><form method="post"><input type="hidden" name="csrf" value="<?= kp_e($csrf) ?>"><input type="hidden" name="action" value="check_card"><input type="hidden" name="id" value="<?= kp_e($o['id']) ?>"><button class="tp-button-secondary tp-btn--small" type="submit">Kiểm tra ↻</button></form><?php else: ?>—<?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?>
</div></section>
<section class="tp-section"><div class="tp-panel"><div class="tp-panel-head"><div><span class="tp-eyebrow">CỘNG THỦ CÔNG</span><h2>Lịch sử cấp tài nguyên</h2></div><span class="tp-chip"><?= count($grants) ?> thao tác</span></div>
<?php if(!$grants): ?><p class="tp-muted">Chưa có thao tác cộng thủ công.</p>
<?php else: ?><div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Mã</th><th>Thời gian</th><th>Admin</th><th>Tài khoản</th><th>Nhân vật</th><th>Loại</th><th>Số cộng</th><th>Số dư trước → sau</th><th>Trạng thái</th><th>Lý do</th></tr></thead><tbody>
<?php foreach($grants as $g): ?>
<tr><td><?= kp_e(strtoupper(substr($g['id'],0,10))) ?></td><td><?= date('d/m H:i',(int)$g['created_at']) ?></td><td>#<?= (int)$g['admin_id'] ?></td><td><?= kp_e($g['username']) ?></td><td><?= kp_e($g['charname']) ?></td><td><?= kp_e(['gold'=>'Xu','luong'=>'Lượng thường','luonglock'=>'Lượng khóa'][$g['currency']]??$g['currency']) ?></td><td><?= number_format((int)$g['units'],0,',','.') ?></td><td><?= number_format((int)$g['before'],0,',','.') ?> → <?= number_format((int)$g['after'],0,',','.') ?></td><td><span class="tp-pill <?= kp_e($g['status']) ?>"><?= kp_e(kp_topup_status_label($g['status'])) ?></span></td><td><?= kp_e($g['note']) ?></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?>
</div></section>
<?php kp_topup_page_end(); ?>
