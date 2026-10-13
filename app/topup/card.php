<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/topup_core.php';
require_once __DIR__ . '/_layout.php';
$user = kp_topup_user();
$cfg = kp_topup_cfg();
// Do not charge real cards until BOTH provider and game-server queue are verified.
$cardLive = $cfg['card_ready'] && $cfg['game_queue_verified'];
$rates = kp_topup_rates();
$csrf = kp_csrf_token();
$error = ''; $notice = ''; $createdId = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        if (!$cardLive) throw new RuntimeException('Chưa bật nạp thẻ thật: cần cấu hình đối tác và xác minh cấp Lượng trên game server.');
        if (!kp_csrf_check($_POST['csrf'] ?? null)) throw new RuntimeException('Phiên đã hết hạn. Tải lại trang.');
        list($allow,$retry) = kp_rate_limit('kp_card', (string)$user['id'].'|'.kp_client_ip(), 5, 3600, true);
        if (!$allow) throw new RuntimeException('Bạn đã gửi nhiều yêu cầu. Thử lại sau '.(int)ceil($retry/60).' phút.');
        $telco = strtoupper(trim((string)($_POST['telco'] ?? '')));
        $currency = (string)($_POST['currency'] ?? '');
        $amount = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_INT);
        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        $serial = strtoupper(trim((string)($_POST['serial'] ?? '')));
        if (!in_array($telco,['VIETTEL','VINAPHONE','MOBIFONE','VNMOBI','GATE','ZING'],true)) {
            throw new RuntimeException('Loại thẻ không hỗ trợ.');
        }
        if (!in_array($currency,['luong','luonglock'],true)) throw new RuntimeException('Chọn loại Lượng muốn nhận.');
        if ($amount === false || $amount < 1000 || $amount > 1000000) throw new RuntimeException('Mệnh giá cần từ 1.000đ đến 1.000.000đ.');
        if (!preg_match('/^[A-Z0-9]{6,32}$/D',$code) || !preg_match('/^[A-Z0-9]{6,32}$/D',$serial)) {
            throw new RuntimeException('Mã thẻ hoặc serial không hợp lệ.');
        }
        $discountPercent = (int)$cfg['discount_percent'];
        $netAmount = kp_topup_after_discount((int)$amount, $discountPercent);
        $units = kp_topup_units($netAmount, (int)$rates[$currency]);
        $fingerprint = kp_topup_hmac($code,$serial,$cfg['secret']);
        $global = @fopen(kp_topup_root().'/new-card.lock','c+');
        if (!$global || !flock($global,LOCK_EX)) throw new RuntimeException('Không khóa được yêu cầu nạp thẻ.');
        try {
            // Prevent duplicate cards even across separate accounts and browser sessions.
            foreach (kp_topup_recent('orders',null,100000) as $existing) {
                if (hash_equals((string)($existing['fingerprint'] ?? ''),$fingerprint)) {
                    throw new RuntimeException('Thẻ này đã được gửi. Xem lịch sử hoặc liên hệ admin, không nhập lại.');
                }
            }
            $createdId = kp_topup_new_id();
            $order = [
                'id'=>$createdId,'user_id'=>(int)$user['id'],'username'=>(string)$user['username'],
                'currency'=>$currency,'telco'=>$telco,'amount'=>(int)$amount,
                'discount_percent'=>$discountPercent,'net_amount'=>$netAmount,
                'units'=>$units,'rate'=>(int)$rates[$currency],
                'fingerprint'=>$fingerprint,'serial_end'=>substr($serial,-4),
                'status'=>'submitting','created_at'=>time(),'updated_at'=>time()
            ];
            kp_topup_with_record('orders',$createdId,function($old,$path) use($order) {
                if ($old !== null) throw new RuntimeException('Mã giao dịch bị trùng.');
                kp_topup_write_file($path,$order);
            });
        } finally { @flock($global, LOCK_UN); fclose($global); }
        // No PIN/serial is recorded in our own database/files. Transmit to provider over TLS.
        try {
            $response = kp_topup_provider_charge($order,$code,$serial);
            if (isset($response['request_id']) && (string)$response['request_id'] !== $createdId) {
                throw new RuntimeException('Mã yêu cầu từ đối tác không khớp.');
            }
            kp_topup_with_record('orders',$createdId,function($old,$path) use($response) {
                if (!$old || $old['status']!=='submitting') return;
                $old['status'] = 'pending';
                $old['updated_at'] = time();
                kp_topup_write_file($path,$old);
            });
            if ((int)($response['status'] ?? 99) === 1) {
                try { kp_topup_reconcile($createdId); }
                catch (Throwable $e) { kp_log('instant card confirmation pending reconciliation',$e); }
            }
        } catch (Throwable $e) {
            // The provider may have received the request. Never ask to re-send the same PIN.
            kp_log('card submission uncertain; requires provider status check',$e);
        }
        $notice = 'Đã tiếp nhận yêu cầu #'.strtoupper(substr($createdId,0,10)).'. Xem lịch sử để theo dõi trạng thái. Không gửi lại cùng một thẻ.';
    } catch (RuntimeException $e) { $error = $e->getMessage(); }
    catch (Throwable $e) { kp_log('card submission failed',$e); $error='Chưa gửi được thẻ. Kiểm tra lịch sử trước khi thử lại.'; }
}
// Avoid accidentally echoing submitted card PIN/serial anywhere below.
kp_topup_page_start('Nạp thẻ cào','card',(string)$user['username']);
?>
<section class="tp-hero"><span class="tp-kicker"><i></i> NẠP THẺ CÀO</span><h1>Nạp thẻ, nhận <em>Lượng.</em></h1><p class="tp-lead">Chọn Lượng thường hoặc Lượng khóa. Lượng nhận được tính từ mệnh giá hợp lệ sau khi trừ chiết khấu thẻ.</p></section>
<?php if($error!==''): ?><div class="tp-alert bad" role="alert"><?= kp_e($error) ?></div><?php endif; ?>
<?php if($notice!==''): ?><div class="tp-alert good" role="status"><?= kp_e($notice) ?> <a class="tp-support" href="/lich-su-nap">Xem lịch sử →</a></div><?php endif; ?>
<?php if(!$cardLive): ?>
<div class="tp-alert warning"><strong>Chưa mở gửi thẻ thật.</strong> Chỉ kích hoạt sau khi đã có tài khoản đối tác và xác minh game server xử lý <code>board_naptien</code>. Không có trường hợp gửi thẻ thành công nhưng tính năng cấp Lượng chưa sẵn sàng.</div>
<?php endif; ?>
<section class="tp-section"><div class="tp-grid two">
<div class="tp-panel"><div class="tp-panel-head"><div><span class="tp-eyebrow">THÔNG TIN THẺ</span><h2>Gửi thẻ cào</h2></div><span class="tp-chip"><?= $cardLive ? 'SẴN SÀNG NẠP' : 'CHƯA MỞ' ?></span></div>
<form class="tp-form" id="card-topup-form" data-rate-luong="<?= (int)$rates['luong'] ?>" data-rate-luonglock="<?= (int)$rates['luonglock'] ?>" data-discount-percent="<?= (int)($cfg['discount_percent'] ?? 0) ?>" method="post" autocomplete="off">
<input type="hidden" name="csrf" value="<?= kp_e($csrf) ?>">
<div class="tp-row">
<label class="tp-field"><span>Loại thẻ</span><select class="tp-select" name="telco" required <?= !$cardLive?'disabled':'' ?>><option value="VIETTEL">Viettel</option><option value="VINAPHONE">Vinaphone</option><option value="MOBIFONE">Mobifone</option><option value="VNMOBI">Vietnamobile</option><option value="GATE">Gate</option><option value="ZING">Zing</option></select></label>
<label class="tp-field"><span>Mệnh giá ghi trên thẻ (VNĐ)</span><input class="tp-input" name="amount" type="number" inputmode="numeric" min="1000" max="1000000" step="1000" placeholder="VD: 50000" required <?= !$cardLive?'disabled':'' ?>><small>Nhập đúng mệnh giá được in trên thẻ.</small></label>
</div>
<label class="tp-field"><span>Nhận tài nguyên</span><select class="tp-select" name="currency" required <?= !$cardLive?'disabled':'' ?>><option value="luong">Lượng thường (không khóa)</option><option value="luonglock">Lượng khóa</option></select></label>
<div class="tp-row"><label class="tp-field"><span>Mã nạp thẻ</span><input class="tp-input" name="code" type="password" maxlength="32" minlength="6" spellcheck="false" autocomplete="off" placeholder="Mã PIN dưới lớp phủ" required <?= !$cardLive?'disabled':'' ?>></label><label class="tp-field"><span>Số serial</span><input class="tp-input" name="serial" type="text" maxlength="32" minlength="6" spellcheck="false" autocomplete="off" placeholder="Serial in trên thẻ" required <?= !$cardLive?'disabled':'' ?>></label></div>
<p id="tp-estimate" class="tp-alert warning" aria-live="polite">Nhập mệnh giá để ước tính Lượng sau chiết khấu.</p>
<label class="tp-check"><input type="checkbox" required <?= !$cardLive?'disabled':'' ?>><span>Tôi đã kiểm tra số serial, mã thẻ và mệnh giá. Mỗi thẻ chỉ được gửi một lần.</span></label>
<button class="tp-button" type="submit" <?= !$cardLive?'disabled':'' ?>>Gửi thẻ để kiểm tra <span aria-hidden="true">↗</span></button>
</form></div>
<div>
<div class="tp-panel"><span class="tp-eyebrow">TỶ LỆ HIỆN TẠI</span><h2>Đổi được bao nhiêu?</h2><p class="tp-muted">Chiết khấu <?= $cfg['discount_percent'] === null ? 'chưa cấu hình' : (int)$cfg['discount_percent'].'%' ?>; số Lượng tính trên mệnh giá sau chiết khấu. Mệnh giá thẻ sai cần đối soát.</p><div class="tp-summary tp-summary--two tp-mt-19"><div><small>10.000đ → Lượng thường</small><strong><?= number_format((int)$rates['luong'],0,',','.') ?></strong></div><div><small>10.000đ → Lượng khóa</small><strong><?= number_format((int)$rates['luonglock'],0,',','.') ?></strong></div></div></div>
<div class="tp-panel"><span class="tp-eyebrow">QUY TRÌNH</span><h2>3 bước nạp thẻ</h2><div class="tp-steps tp-steps--stack tp-mt-15"><div class="tp-step"><span class="tp-step-number">01</span><b>Gửi đúng thông tin thẻ</b><span>Chỉ gửi khi cổng thẻ đã mở.</span></div><div class="tp-step"><span class="tp-step-number">02</span><b>Đối tác xác minh</b><span>Chỉ thẻ hợp lệ mới được chấp nhận.</span></div><div class="tp-step"><span class="tp-step-number">03</span><b>Kiểm tra trạng thái</b><span>Trạng thái chờ cấp không có nghĩa là đã nhận tài nguyên.</span></div></div></div>
</div></div></section>
<script src="/assets/js/topup-forms.js?v=1" defer></script>
<?php kp_topup_page_end(); ?>
