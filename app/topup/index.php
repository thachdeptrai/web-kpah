<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/topup_core.php';
require_once __DIR__ . '/../includes/rankings.php';
require_once __DIR__ . '/_layout.php';
$user=kp_topup_user();$cfg=kp_topup_cfg();$rates=kp_topup_rates();$hasTopNap=isset(kp_rankings_available()['nap']);
kp_topup_page_start('Tổng quan','index',(string)$user['username']);?>
<section class="tp-hero"><span class="tp-kicker"><i></i> TÀI NGUYÊN KPAH</span><h1>Chọn cách nạp <em>của bạn.</em></h1><p class="tp-lead">Chọn nạp thẻ cào hoặc chuyển khoản VietQR qua payOS.</p></section>
<section class="tp-section"><div class="tp-section-heading"><div><span class="tp-eyebrow">DỊCH VỤ</span><h2>Bạn muốn làm gì?</h2></div></div>
<div class="tp-grid">
<a class="tp-card" href="/nap-the"><span class="tp-icon">✦</span><h3>Nạp thẻ cào</h3><p>Chọn loại Lượng, nhập đúng serial và mã thẻ.</p><span class="tp-card-cta"><?= $cfg['card_ready'] ? 'Mở nạp thẻ' : 'Xem trang nạp thẻ' ?> <strong>↗</strong></span></a>
<a class="tp-card" href="/nap-ngan-hang"><span class="tp-icon purple">◇</span><h3>Nạp ngân hàng</h3><p>Tạo QR payOS và theo dõi đơn thanh toán riêng của bạn.</p><span class="tp-card-cta">Nạp ngân hàng <strong>↗</strong></span></a>
<a class="tp-card" href="/diem-tich-luy"><span class="tp-icon green">✧</span><h3>Point tích lũy & đổi quà</h3><p>Nạp 10.000đ tích 10 Point. Xem ví Point, quà đang mở đổi và lịch sử sử dụng.</p><span class="tp-card-cta">Đến cửa hàng Point <strong>↗</strong></span></a>
<a class="tp-card" href="/lich-su-nap"><span class="tp-icon green">◷</span><h3>Lịch sử nạp</h3><p>Xem lại thẻ cào, chuyển khoản payOS và tình trạng cấp Lượng.</p><span class="tp-card-cta">Lịch sử của tôi <strong>↗</strong></span></a>
<?php if($hasTopNap): ?><a class="tp-card" href="/top-nap"><span class="tp-icon">♛</span><h3>Top nạp Lượng</h3><p>Xem ai đang đứng đầu Top nạp Lượng.</p><span class="tp-card-cta">Xem xếp hạng <strong>↗</strong></span></a><?php endif; ?>
</div></section>
<div class="tp-panel"><span class="tp-eyebrow">BẢNG NẠP THỰC TẾ</span><h2>Mệnh giá và Lượng nhận</h2>
<div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Mệnh giá</th><th>Lượng mở</th><th>Lượng khóa</th></tr></thead><tbody>
<?php foreach(kp_card_packages() as $vnd=>$pack): ?><tr><td><?=number_format($vnd,0,',','.')?>đ</td><td><?=number_format($pack['luong'],0,',','.')?></td><td><?=number_format($pack['luonglock'],0,',','.')?></td></tr><?php endforeach; ?>
</tbody></table></div><p class="tp-muted tp-mt-15">Chuyển khoản ngân hàng nhận theo mốc trên; thẻ cào được điều chỉnh theo phí của nhà cung cấp.</p></div>
<?php kp_topup_page_end(); ?>
