<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/topup_core.php';
require_once __DIR__ . '/../../includes/wallet_vnd.php';
require_once __DIR__ . '/../../includes/bank_farmer_reward.php';
require_once __DIR__ . '/../_layout.php';
$admin=kp_topup_admin();$csrf=kp_csrf_token();$error='';$notice='';$bonus=0;$farmer=['enabled'=>false,'per_10k'=>10000];
try {
    $rates=kp_topup_rates();
    $bonus=kp_vnd_bonus_percent();
    $farmer=kp_bank_farmer_settings();
    if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
        if(!kp_admin_csrf_valid($_POST['csrf']??null))throw new RuntimeException('Phiên đã hết hạn. Tải lại trang.');
        if(($_POST['action']??'')==='bank_bonus'){
            $value=filter_var($_POST['bonus_percent']??null,FILTER_VALIDATE_INT);
            if($value===false||$value<0||$value>100)throw new RuntimeException('Thưởng ngân hàng phải từ 0% đến 100%.');
            kp_vnd_bonus_percent((int)$value);
            $bonus=$value;
            kp_log('bank bonus changed by admin #'.(int)$admin['id'].' to '.$value.' percent');
            $notice='Đã lưu thưởng chuyển khoản '.$value.'%. Chỉ đơn tạo sau thay đổi dùng tỷ lệ mới.';
        }elseif(($_POST['action']??'')==='bank_farmer'){
            $raw=filter_var($_POST['per_10k']??null,FILTER_VALIDATE_INT);
            if($raw===false||$raw<0||$raw>100000)throw new RuntimeException('Điểm thưởng phải từ 0 đến 100.000 mỗi 10.000đ nạp.');
            $enabled=($_POST['enabled']??'')==='1';
            $farmer=kp_bank_farmer_settings(['enabled'=>$enabled,'per_10k'=>(int)$raw]);
            kp_log('bank farmer reward changed by admin #'.(int)$admin['id'].' enabled='.($enabled?'1':'0').' per10k='.$raw);
            $notice=$enabled?'Đã mở thưởng Điểm nông dân. Chỉ đơn ngân hàng tạo từ bây giờ được áp dụng.':'Đã tắt thưởng Điểm nông dân cho các đơn tạo mới.';
        }elseif(($_POST['action']??'')==='rates' || !isset($_POST['action'])){
        $new=[];
        foreach(['gold','luong','luonglock'] as $key){
            $raw=trim((string)($_POST[$key]??''));
            if(!preg_match('/^(0|[1-9][0-9]*)$/D',$raw) || strlen($raw)>10 || (float)$raw>1000000000)throw new RuntimeException('Tỷ lệ phải là số nguyên từ 0 đến 1 tỷ.');
            $new[$key]=(int)$raw;
        }
        $rates=kp_topup_rates($new);
        $notice='Đã lưu tỷ lệ cho cả 3 loại tài nguyên.';
        }else{throw new RuntimeException('Thao tác không hợp lệ.');}
    }
}catch(RuntimeException $e){$error=$e->getMessage();$rates=$rates??['gold'=>0,'luong'=>100,'luonglock'=>200];}
catch(Throwable $e){kp_log('topup rate panel error',$e);$error='Không lưu được tỷ lệ. Kiểm tra quyền ghi storage/topup.';$rates=$rates??['gold'=>0,'luong'=>100,'luonglock'=>200];}
kp_topup_page_start('Tỷ lệ quy đổi','rates',(string)$admin['username'],true);?>
<section class="tp-hero"><span class="tp-kicker"><i></i> TỶ LỆ / QUẢN TRỊ</span><h1>Điều chỉnh <em>quy đổi.</em></h1><p class="tp-lead">Cấu hình thưởng nạp ngân hàng và tỷ lệ quy đổi cũ. Khuyến mãi chỉ áp dụng cho đơn mới, được chốt tại lúc tạo QR.</p></section>
<?php if($error): ?><div class="tp-alert bad" role="alert"><?= kp_e($error) ?></div><?php endif; ?>
<?php if($notice): ?><div class="tp-alert good" role="status"><?= kp_e($notice) ?></div><?php endif; ?>
<section class="tp-section"><div class="tp-panel"><div class="tp-panel-head"><div><span class="tp-eyebrow">CẤU HÌNH</span><h2>Mỗi 10.000 VNĐ nhận</h2></div><span class="tp-chip">SỬA TRỰC TIẾP</span></div>
<form method="post" class="tp-form"><input type="hidden" name="csrf" value="<?= kp_e($csrf) ?>"><input type="hidden" name="action" value="rates">
<div class="tp-row"><label class="tp-field"><span>Xu</span><input class="tp-input" type="number" name="gold" min="0" max="1000000000" step="1" required value="<?= (int)$rates['gold'] ?>"><small>Mặc định 0: chưa bật quy đổi Xu theo VNĐ.</small></label><label class="tp-field"><span>Lượng thường (không khóa)</span><input class="tp-input" type="number" name="luong" min="0" max="1000000000" step="1" required value="<?= (int)$rates['luong'] ?>"><small>Mặc định 100 / 10.000đ.</small></label></div>
<label class="tp-field"><span>Lượng khóa</span><input class="tp-input" type="number" name="luonglock" min="0" max="1000000000" step="1" required value="<?= (int)$rates['luonglock'] ?>"><small>Mặc định 200 / 10.000đ.</small></label>
<div class="tp-actions"><button class="tp-button" type="submit">Lưu tỷ lệ <span aria-hidden="true">↗</span></button><a class="tp-button-secondary" href="/quan-tri/nap">Quay lại</a></div></form>
</div><div class="tp-alert warning">Giá trị 0 tắt quy đổi loại tài nguyên tương ứng. Thẻ đã gửi trước đó vẫn dùng tỷ lệ được lưu tại thời điểm gửi, không thay đổi theo tỷ lệ mới.</div></section>
<section class="tp-section"><div class="tp-panel"><span class="tp-eyebrow">CHUYỂN KHOẢN PAYOS</span><h2>Thưởng thêm VNĐ khi nạp ngân hàng</h2>
<p class="tp-muted">Mặc định +10%. Ví dụ nạp 100.000đ với mức 10% sẽ nhận 110.000đ vào ví VNĐ. Tỷ lệ được chốt khi tạo QR, không đổi trên đơn đã tạo.</p>
<form method="post" class="tp-form"><input type="hidden" name="csrf" value="<?=kp_e($csrf)?>"><input type="hidden" name="action" value="bank_bonus">
<label class="tp-field"><span>Thưởng thêm (%)</span><input class="tp-input" type="number" min="0" max="100" step="1" name="bonus_percent" value="<?=(int)$bonus?>" required></label>
<button type="submit" class="tp-button">Lưu mức thưởng ↗</button>
</form></div></section>
<section class="tp-section"><div class="tp-panel"><span class="tp-eyebrow">QUÀ NẠP NGÂN HÀNG · ĐIỂM NÔNG DÂN</span><h2>Thưởng Điểm nông dân khi chuyển khoản</h2>
<p class="tp-muted">Mốc đề xuất: nạp <strong>10.000đ nhận 10.000 Điểm nông dân</strong>, ngoài số VNĐ và % thưởng đã cài ở trên. Điểm thuộc nhân vật game; sau khi thanh toán thành công, người chơi chọn nhân vật và nhận tại trang Nạp ngân hàng. Chỉ áp dụng cho QR tạo sau khi bật, không truy cộng cho giao dịch cũ.</p>
<form method="post" class="tp-form">
<input type="hidden" name="csrf" value="<?=kp_e($csrf)?>"><input type="hidden" name="action" value="bank_farmer">
<label class="tp-field"><span>Điểm nông dân / mỗi 10.000đ nạp</span><input class="tp-input" type="number" min="0" max="100000" step="1" name="per_10k" value="<?=(int)$farmer['per_10k']?>" required><small>Ví dụ: 10.000đ → 10.000 điểm; 50.000đ → 50.000 điểm. Không tính % VNĐ thưởng vào điểm.</small></label>
<label class="tp-field" style="display:flex;align-items:center;gap:10px"><input type="checkbox" name="enabled" value="1" style="width:20px;height:20px;flex:none;accent-color:#e2b45a" <?=$farmer['enabled']?'checked':''?>><span>Bật quà Điểm nông dân cho đơn bank mới</span></label>
<div class="tp-actions"><button class="tp-button" type="submit">Lưu thưởng Điểm nông dân ↗</button><span class="tp-chip"><?=$farmer['enabled']?'ĐANG MỞ':'ĐANG TẮT'?></span></div>
</form>
<div class="tp-alert warning" style="margin-top:16px">Điểm được ghi vào nhân vật khi người chơi chủ động nhận quà sau khi <strong>thoát game</strong>. Không sửa pInfo khi đang chơi để tránh bị dữ liệu trong game ghi đè. Một đơn chỉ được nhận một lần.</div>
</div></section>
<?php kp_topup_page_end(); ?>
