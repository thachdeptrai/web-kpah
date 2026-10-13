<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/topup_core.php';
require_once __DIR__ . '/../_layout.php';
$admin=kp_topup_admin();$csrf=kp_csrf_token();$error='';$notice='';
try {
    $rates=kp_topup_rates();
    if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
        if(!kp_admin_csrf_valid($_POST['csrf']??null))throw new RuntimeException('Phiên đã hết hạn. Tải lại trang.');
        $new=[];
        foreach(['gold','luong','luonglock'] as $key){
            $raw=trim((string)($_POST[$key]??''));
            if(!preg_match('/^(0|[1-9][0-9]*)$/D',$raw) || strlen($raw)>10 || (float)$raw>1000000000)throw new RuntimeException('Tỷ lệ phải là số nguyên từ 0 đến 1 tỷ.');
            $new[$key]=(int)$raw;
        }
        $rates=kp_topup_rates($new);
        $notice='Đã lưu tỷ lệ cho cả 3 loại tài nguyên.';
    }
}catch(RuntimeException $e){$error=$e->getMessage();$rates=$rates??['gold'=>0,'luong'=>100,'luonglock'=>200];}
catch(Throwable $e){kp_log('topup rate panel error',$e);$error='Không lưu được tỷ lệ. Kiểm tra quyền ghi storage/topup.';$rates=$rates??['gold'=>0,'luong'=>100,'luonglock'=>200];}
kp_topup_page_start('Tỷ lệ quy đổi','rates',(string)$admin['username'],true);?>
<section class="tp-hero"><span class="tp-kicker"><i></i> TỶ LỆ / QUẢN TRỊ</span><h1>Điều chỉnh <em>quy đổi.</em></h1><p class="tp-lead">Bạn có thể chỉnh mức Xu và Lượng nhận theo từng 10.000đ.</p></section>
<?php if($error): ?><div class="tp-alert bad" role="alert"><?= kp_e($error) ?></div><?php endif; ?>
<?php if($notice): ?><div class="tp-alert good" role="status"><?= kp_e($notice) ?></div><?php endif; ?>
<section class="tp-section"><div class="tp-panel"><div class="tp-panel-head"><div><span class="tp-eyebrow">CẤU HÌNH</span><h2>Mỗi 10.000 VNĐ nhận</h2></div><span class="tp-chip">SỬA TRỰC TIẾP</span></div>
<form method="post" class="tp-form"><input type="hidden" name="csrf" value="<?= kp_e($csrf) ?>">
<div class="tp-row"><label class="tp-field"><span>Xu</span><input class="tp-input" type="number" name="gold" min="0" max="1000000000" step="1" required value="<?= (int)$rates['gold'] ?>"><small>Mặc định 0: chưa bật quy đổi Xu theo VNĐ.</small></label><label class="tp-field"><span>Lượng thường (không khóa)</span><input class="tp-input" type="number" name="luong" min="0" max="1000000000" step="1" required value="<?= (int)$rates['luong'] ?>"><small>Mặc định 100 / 10.000đ.</small></label></div>
<label class="tp-field"><span>Lượng khóa</span><input class="tp-input" type="number" name="luonglock" min="0" max="1000000000" step="1" required value="<?= (int)$rates['luonglock'] ?>"><small>Mặc định 200 / 10.000đ.</small></label>
<div class="tp-actions"><button class="tp-button" type="submit">Lưu tỷ lệ <span aria-hidden="true">↗</span></button><a class="tp-button-secondary" href="/quan-tri/nap">Quay lại</a></div></form>
</div><div class="tp-alert warning">Giá trị 0 tắt quy đổi loại tài nguyên tương ứng. Thẻ đã gửi trước đó vẫn dùng tỷ lệ được lưu tại thời điểm gửi, không thay đổi theo tỷ lệ mới.</div></section>
<?php kp_topup_page_end(); ?>
