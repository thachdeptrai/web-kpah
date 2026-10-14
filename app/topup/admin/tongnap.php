<?php
require __DIR__.'/../../includes/bootstrap.php';
require_once __DIR__.'/../../includes/topup_core.php';
require_once __DIR__.'/../../includes/wallet_vnd.php';
require_once __DIR__.'/_layout.php';
$admin=kp_topup_admin(); // backend: only role admin, never 'key'
$csrf=kp_csrf_token();
$error='';$notice='';$owner=null;$history=[];
$username=trim((string)($_POST['username']??$_GET['username']??''));
if(strlen($username)>45||($username!==''&&!preg_match('/^[a-zA-Z0-9_.-]{1,45}$/D',$username)))$username='';
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
 try{
    if(!kp_admin_csrf_valid($_POST['csrf']??null))throw new RuntimeException('Phiên quản trị không hợp lệ. Tải lại trang.');
    if(($_POST['action']??'')!=='add_tongnap')throw new RuntimeException('Thao tác không hợp lệ.');
    list($allowed,$retry)=kp_admin_limit('manual-tongnap',(string)$admin['id'],12,3600);
    if(!$allowed)throw new RuntimeException('Đã chạm giới hạn cộng thử trong một giờ.');
    $amountRaw=trim((string)($_POST['amount']??''));
    if(!preg_match('/^[1-9][0-9]*$/D',$amountRaw)||strlen($amountRaw)>9||(float)$amountRaw>100000000)
        throw new RuntimeException('Mỗi lần chỉ được cộng 1–100.000.000 VNĐ tổng nạp.');
    if(($_POST['confirm']??'')!=='CONG TONG NAP')
        throw new RuntimeException('Nhập đúng dòng xác nhận CONG TONG NAP.');
    $saved=kp_vnd_admin_add_tongnap(
        (int)$admin['id'],$username,(int)$amountRaw,(string)($_POST['request_id']??''),
        trim((string)($_POST['note']??''))
    );
    $notice='Đã cộng '.number_format((int)$saved['amount'],0,',','.').'đ vào tổng nạp của '.$saved['username']
        .' ('.number_format((int)$saved['before'],0,',','.').'đ → '.number_format((int)$saved['after'],0,',','.').'đ). Ví VNĐ không đổi.';
 }catch(Throwable $e){
    kp_log('admin manual tongnap operation failed',$e);
    $error=$e instanceof RuntimeException?$e->getMessage():'Không xác định được trạng thái cập nhật. Hãy kiểm tra lịch sử.';
 }
}
try{
    kp_vnd_require_tongnap(kp_db());
    if($username!==''){
        $st=kp_db()->prepare('SELECT id,username,COALESCE(vnd,0) AS vnd,COALESCE(tongnap,0) AS tongnap,ban FROM team_user WHERE username=? LIMIT 1');
        $st->execute([$username]);$owner=$st->fetch(PDO::FETCH_ASSOC);
    }
    $history=kp_vnd_admin_tongnap_history(40);
}catch(Throwable $e){kp_log('admin tongnap dashboard lookup failed',$e);$error=$error?:'Không đọc được team_user.tongnap hoặc thư mục lịch sử. Kiểm tra cột và quyền ghi.';}
$requestId=kp_topup_new_id();
kp_topup_page_start('Cộng tổng nạp','tongnap',(string)$admin['username'],true);
?>
<section class="tp-hero"><span class="tp-kicker"><i></i> QUẢN TRỊ / KIỂM THỬ</span><h1>Cộng <em>tổng nạp.</em></h1>
<p class="tp-lead">Chỉ tăng <code>team_user.tongnap</code> để thử Top nạp. Không cộng VNĐ vào ví, không cộng Point, không tạo đơn ngân hàng/thẻ cào và không cấp tài nguyên game.</p></section>
<?php if($notice):?><div class="tp-alert good" role="status"><?=kp_e($notice)?></div><?php endif;?>
<?php if($error):?><div class="tp-alert bad" role="alert"><?=kp_e($error)?></div><?php endif;?>
<section class="tp-section"><div class="tp-grid two">
<div class="tp-panel">
<h2>Tìm tài khoản</h2>
<form method="get" class="tp-form">
<label class="tp-field"><span>Username</span><input class="tp-input" name="username" maxlength="45" value="<?=kp_e($username)?>" required placeholder="Tên tài khoản game"></label>
<button type="submit" class="tp-button">Tra cứu tài khoản ↗</button>
</form>
<?php if($owner):?>
<div class="tp-summary tp-mt-19"><div><small>Tổng nạp hiện tại</small><strong><?=number_format((int)$owner['tongnap'],0,',','.')?>đ</strong></div>
<div><small>VNĐ trong ví (không thay đổi)</small><strong><?=number_format((int)$owner['vnd'],0,',','.')?>đ</strong></div></div>
<p class="tp-muted">Tài khoản #<?=(int)$owner['id']?> · <?=kp_e($owner['username'])?><?= (int)$owner['ban']!==0?' · Đã khóa':'' ?></p>
<?php elseif($username!==''):?><div class="tp-alert warning">Không tìm thấy tài khoản cần tra cứu.</div><?php endif;?>
</div>
<div class="tp-panel">
<h2>Cộng tổng nạp để test</h2>
<?php if(!$owner || (int)$owner['ban']!==0):?>
<p class="tp-muted">Chọn tài khoản đang hoạt động trước khi cộng.</p>
<?php else:?>
<form method="post" class="tp-form" id="admin-tongnap-form">
<input type="hidden" name="csrf" value="<?=kp_e($csrf)?>">
<input type="hidden" name="action" value="add_tongnap">
<input type="hidden" name="username" value="<?=kp_e($owner['username'])?>">
<input type="hidden" name="request_id" value="<?=kp_e($requestId)?>">
<label class="tp-field"><span>Chỉ cộng vào tổng nạp (VNĐ)</span><input class="tp-input" type="number" name="amount" min="1" max="100000000" step="1" required placeholder="Ví dụ: 10000"></label>
<label class="tp-field"><span>Lý do / ghi chú</span><textarea class="tp-textarea" name="note" minlength="5" maxlength="200" required placeholder="Kiểm tra Top nạp trên website"></textarea></label>
<label class="tp-field"><span>Nhập CONG TONG NAP để xác nhận</span><input class="tp-input" name="confirm" required autocomplete="off" placeholder="CONG TONG NAP"></label>
<button type="submit" class="tp-button" id="admin-tongnap-button">Cộng tổng nạp ↗</button>
</form>
<?php endif;?>
</div>
</div>
<div class="tp-alert warning">Đây là thao tác chỉnh thành tích tích lũy để kiểm thử, không phải một giao dịch nạp thật. Nếu một yêu cầu báo chưa rõ kết quả, <strong>không gửi lại</strong>; kiểm tra bản ghi và SQL trước khi thử tiếp.</div>
</section>
<section class="tp-section"><div class="tp-panel"><h2>Nhật ký cộng tổng nạp thủ công</h2>
<?php if(!$history):?><p class="tp-muted">Chưa có thao tác cộng tổng nạp thủ công.</p>
<?php else:?>
<div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Thời gian</th><th>Admin ID</th><th>Tài khoản</th><th>Giá trị cộng</th><th>Trước → sau</th><th>Kết quả</th><th>Ghi chú</th></tr></thead><tbody>
<?php foreach($history as $r):?><tr>
<td><?=date('d/m/Y H:i',(int)($r['created_at']??0))?></td><td>#<?=(int)$r['admin_id']?></td><td><?=kp_e($r['username'])?></td>
<td>+<?=number_format((int)$r['amount'],0,',','.')?>đ</td>
<td><?=number_format((int)$r['before'],0,',','.')?> → <?=number_format((int)$r['after'],0,',','.')?></td>
<td><?=kp_e($r['status'])?></td><td><?=kp_e($r['note'])?></td>
</tr><?php endforeach;?></tbody></table></div><?php endif;?>
</div></section>
<script src="/assets/js/tongnap-submit-guard.js?v=1" defer></script>
<?php kp_topup_page_end(); ?>
