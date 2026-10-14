<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/topup_core.php';
require_once __DIR__ . '/../../includes/bank_payos.php';
require_once __DIR__ . '/../_layout.php';
$admin=kp_topup_admin();$csrf=kp_csrf_token();$cfg=kp_topup_cfg();$error='';$notice='';
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    try{
        if(!kp_admin_csrf_valid($_POST['csrf']??null))throw new RuntimeException('Phiên xác nhận đã hết hạn.');
        if(($_POST['action']??'')==='recover_bank'){
            list($allow,$retry)=kp_rate_limit('kp_admin_bank_recover',(string)$admin['id'],4,3600,true);
            if(!$allow)throw new RuntimeException('Đã đạt giới hạn thao tác phục hồi trong giờ.');
            $code=filter_var($_POST['code']??null,FILTER_VALIDATE_INT);
            $char=filter_var($_POST['char_id']??null,FILTER_VALIDATE_INT);
            $queue=filter_var($_POST['queue_id']??null,FILTER_VALIDATE_INT);
            if($code===false||$char===false||$queue===false)throw new RuntimeException('Mã đơn, nhân vật hoặc queue ID không hợp lệ.');
            $status=kp_bank_recover_queued((int)$code,(int)$char,(int)$queue,(string)($_POST['confirmation']??''));
            $notice='Phục hồi đơn #'.$code.': '.$status.'.';
            if($status==='needs_review')$notice.=' Không thao tác lại, đối chiếu SQL trước.';
        } elseif(($_POST['action']??'')==='check_bank'){
            $code=filter_var($_POST['code']??null,FILTER_VALIDATE_INT);
            if($code===false||$code<=0)throw new RuntimeException('Mã đơn ngân hàng không hợp lệ.');
            list($allow,$retry)=kp_rate_limit('kp_admin_bank_check',(string)$admin['id'],30,3600,true);
            if(!$allow)throw new RuntimeException('Thao tác quá nhiều lần.');
            $status=kp_bank_reconcile($code);
            $notice='Đơn ngân hàng #'.$code.': '.$status;
        } elseif(($_POST['action']??'')!=='check_card')throw new RuntimeException('Thao tác không hợp lệ.');
        if(in_array(($_POST['action']??''),['check_bank','recover_bank'],true))throw new LogicException('bank_handled');
        $id=(string)($_POST['id']??'');
        if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new RuntimeException('Mã giao dịch không hợp lệ.');
        list($allow,$retry)=kp_rate_limit('kp_topup_admin_check',(string)$admin['id'],30,3600,true);
        if(!$allow)throw new RuntimeException('Đã đạt giới hạn kiểm tra trong giờ.');
        $result=kp_topup_reconcile($id);
        $notice='Đã kiểm tra #'.strtoupper(substr($id,0,10)).': '.kp_topup_status_label($result['status']).'.';
    }catch(LogicException $e){if($e->getMessage()!=='bank_handled')$error='Lỗi thao tác.';}
    catch(RuntimeException $e){$error=$e->getMessage();}
    catch(Throwable $e){kp_log('admin card reconciliation failed',$e);$error='Không thể đối soát đơn này. Kiểm tra provider, game server và log.';}
}
$orders=kp_topup_recent('orders',null,150);
$grants=kp_topup_recent('grants',null,150);
kp_topup_page_start('Lịch sử & đối soát','orders',(string)$admin['username'],true);?>
<section class="tp-hero"><span class="tp-kicker"><i></i> ADMIN / ĐỐI SOÁT</span><h1>Kiểm tra từng <em>giao dịch.</em></h1><p class="tp-lead">Lịch sử thẻ cào và cộng tài nguyên thủ công ở hai mục riêng. Chỉ hiển thị thông tin để đối soát, không lưu hoặc hiện mã PIN/serial đầy đủ.</p></section>
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

<section class="tp-section"><div class="tp-panel"><div class="tp-panel-head"><div><span class="tp-eyebrow">NGÂN HÀNG / PAYOS</span><h2>Đơn chuyển khoản</h2></div></div>
<?php try { $bankOrders=kp_db()->query('SELECT order_code,user_id,char_id,username,amount,currency,units,status,created_at,delivery_mode,bonus_percent FROM kp_bank_orders ORDER BY id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC); ?>
<div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Mã</th><th>Thời gian</th><th>Tài khoản</th><th>VNĐ</th><th>Lượng</th><th>Trạng thái</th><th>Đối soát</th></tr></thead><tbody>
<?php foreach($bankOrders as $b):?><tr><td><?=kp_e($b['order_code'])?></td><td><?=kp_e($b['created_at'])?></td><td><?=kp_e($b['username'])?></td><td><?=number_format((int)$b['amount'],0,',','.')?></td><td><?php if(($b['delivery_mode']??'game')==='wallet'):?><?=number_format((int)$b['amount']+intdiv((int)$b['amount']*(int)$b['bonus_percent'],100),0,',','.')?>đ ví<?php else:?><?=number_format((int)$b['units'],0,',','.')?> <?= $b['currency']==='luong'?'L':'LK' ?><?php endif;?></td><td><?=kp_e($b['status'])?></td><td><?php if(!in_array($b['status'],['wallet_credited','completed','queued','crediting','needs_review'],true)&&kp_bank_cfg()['ready']):?><form method="post"><input type="hidden" name="csrf" value="<?=kp_e($csrf)?>"><input type="hidden" name="action" value="check_bank"><input type="hidden" name="code" value="<?=kp_e($b['order_code'])?>"><button class="tp-button-secondary tp-btn--small" type="submit">Kiểm tra ↻</button></form><?php else:?>—<?php endif;?></td></tr><?php endforeach;?></tbody></table></div>
<div class="tp-alert warning">Phục hồi đơn queued cũ: phải xác minh nhân vật CHƯA nhận Lượng, đã thoát game và game server KHÔNG còn đọc queue. Mỗi lần chọn đúng một dòng <code>board_naptien.id</code>. Nếu thất bại / needs_review thì dừng, không thao tác lại.</div>
<?php foreach($bankOrders as $b):if($b['status']!=='queued'||(int)$b['char_id']!==0||($b['delivery_mode']??'game')!=='game')continue;
$charSt=kp_db()->prepare('SELECT id,charname FROM tob_char WHERE userId=? AND del=1 AND ban=0 ORDER BY id DESC LIMIT 25');
$charSt->execute([(int)$b['user_id']]);$options=$charSt->fetchAll(PDO::FETCH_ASSOC);
$queueCol=$b['currency']==='luong'?'luong':'luongKhoa';
$qSt=kp_db()->prepare('SELECT id,svID FROM board_naptien WHERE username=? AND '.$queueCol.'=? AND xu=0 AND ve=0 ORDER BY id DESC LIMIT 20');
$qSt->execute([(string)$b['username'],(int)$b['units']]);$queues=$qSt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="tp-panel tp-mt-19"><h2>Phục hồi #<?=kp_e($b['order_code'])?> · <?=kp_e($b['username'])?></h2>
<p class="tp-muted"><?=number_format((int)$b['units'],0,',','.')?> <?= $b['currency']==='luong'?'Lượng mở':'Lượng khóa' ?> · Chỉ dùng nếu CHƯA được nhận trong game.</p>
<form method="post" class="tp-form"><input type="hidden" name="csrf" value="<?=kp_e($csrf)?>"><input type="hidden" name="action" value="recover_bank"><input type="hidden" name="code" value="<?=kp_e($b['order_code'])?>">
<label class="tp-field"><span>Nhân vật nhận</span><select class="tp-select" name="char_id" required><option value="">Chọn nhân vật đã thoát game</option><?php foreach($options as $c):?><option value="<?=(int)$c['id']?>"><?=kp_e($c['charname'])?> (#<?=(int)$c['id']?>)</option><?php endforeach;?></select></label>
<label class="tp-field"><span>Dòng queue cũ cần loại bỏ</span><select class="tp-select" name="queue_id" required><option value="">Chọn đúng ID từ SQL</option><?php foreach($queues as $q):?><option value="<?=(int)$q['id']?>">#<?=(int)$q['id']?> · svID <?=(int)$q['svID']?></option><?php endforeach;?></select></label>
<label class="tp-field"><span>Nhập nguyên văn để xác nhận</span><input class="tp-input" name="confirmation" required autocomplete="off" placeholder="TOI DA KIEM TRA CHUA NHAN LUONG"></label>
<button type="submit" class="tp-button" <?= (!$options||!$queues)?'disabled':'' ?>>Xác minh payOS và cộng một lần ↗</button></form></div>
<?php endforeach;?>
<?php }catch(Throwable $e) { kp_log('bank admin orders query failed',$e); ?>
<div class="tp-alert bad">Không tải được dữ liệu đối soát ngân hàng. Hãy kiểm tra bảng <code>kp_bank_orders</code> và cột <code>char_id</code> (migration <code>database/payos_direct_credit_upgrade.sql</code>). Nếu đã có cột này, kiểm tra PHP error log để biết câu SQL gây lỗi. Không import lại database/payos_bank.sql hoặc xóa bảng cũ.</div>
<?php } ?>
</div></section>
<?php kp_topup_page_end(); ?>
