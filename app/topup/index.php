<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/topup_core.php';
require_once __DIR__ . '/../includes/rankings.php';
require_once __DIR__ . '/_layout.php';
$user=kp_topup_user();$cfg=kp_topup_cfg();$rates=kp_topup_rates();$hasTopNap=isset(kp_rankings_available()['nap']);
kp_topup_page_start('Tổng quan','index',(string)$user['username']);?>
<section class="tp-hero"><span class="tp-kicker"><i></i> TÀI NGUYÊN KPAH</span><h1>Chọn cách nạp <em>của bạn.</em></h1><p class="tp-lead">Chuyển khoản ngân hàng hoặc nạp thẻ để thêm VNĐ vào ví, sau đó đổi Lượng cho nhân vật.</p></section>
<section class="tp-section"><div class="tp-section-heading"><div><span class="tp-eyebrow">DỊCH VỤ</span><h2>Bạn muốn làm gì?</h2></div></div>
<div class="tp-grid">
<a class="tp-card" href="/nap-ngan-hang"><span class="tp-icon" aria-hidden="true"><?= kp_topup_art_icon('bank') ?></span><h3>Nạp ngân hàng</h3><p>Quét mã QR để nạp nhanh, nhận thêm ưu đãi chuyển khoản.</p><span class="tp-card-cta">Nạp ngân hàng <strong>↗</strong></span></a>
<a class="tp-card" href="/nap-the"><span class="tp-icon" aria-hidden="true"><?= kp_topup_art_icon('receipt') ?></span><h3>Nạp thẻ cào</h3><p>Thẻ hợp lệ sẽ cộng VNĐ thực nhận sau chiết khấu vào ví.</p><span class="tp-card-cta"><?= $cfg['card_ready'] ? 'Mở nạp thẻ' : 'Xem trang nạp thẻ' ?> <strong>↗</strong></span></a>
<a class="tp-card" href="/vi-vnd"><span class="tp-icon" aria-hidden="true"><?= kp_topup_art_icon('wallet') ?></span><h3>Ví VNĐ & đổi mốc</h3><p>Xem số dư VNĐ và đổi Lượng mở hoặc khóa cho nhân vật.</p><span class="tp-card-cta">Xem ví của tôi <strong>↗</strong></span></a>
<a class="tp-card" href="/diem-tich-luy"><span class="tp-icon" aria-hidden="true"><?= kp_topup_art_icon('points') ?></span><h3>Point tích lũy & đổi quà</h3><p>Nạp 10.000đ tích 10 Point. Xem ví Point, quà đang mở đổi và lịch sử sử dụng.</p><span class="tp-card-cta">Đến cửa hàng Point <strong>↗</strong></span></a>
<a class="tp-card" href="/lich-su-nap"><span class="tp-icon" aria-hidden="true"><?= kp_topup_art_icon('receipt') ?></span><h3>Lịch sử nạp</h3><p>Xem các lần nạp và số tiền đã nhận.</p><span class="tp-card-cta">Lịch sử của tôi <strong>↗</strong></span></a>
<?php if($hasTopNap): ?><a class="tp-card" href="/top-nap"><span class="tp-icon" aria-hidden="true"><?= kp_topup_art_icon('trophy') ?></span><h3>Top nạp Lượng</h3><p>Xem ai đang đứng đầu Top nạp Lượng.</p><span class="tp-card-cta">Xem xếp hạng <strong>↗</strong></span></a><?php endif; ?>
</div></section>
<div class="tp-panel"><span class="tp-eyebrow">CÁC MỐC ĐỔI VNĐ</span><h2>Dùng VNĐ trong ví để đổi Lượng</h2>
<div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Mệnh giá</th><th>Lượng mở</th><th>Lượng khóa</th></tr></thead><tbody>
<?php foreach(kp_card_packages() as $vnd=>$pack): ?><tr><td><?=number_format($vnd,0,',','.')?>đ</td><td><?=number_format($pack['luong'],0,',','.')?></td><td><?=number_format($pack['luonglock'],0,',','.')?></td></tr><?php endforeach; ?>
</tbody></table></div><p class="tp-muted tp-mt-15">Nạp ngân hàng cộng VNĐ vào ví kèm thưởng; nạp thẻ nhận VNĐ sau phí đối tác. Mốc quy đổi chỉ áp dụng khi người chơi chủ động đổi.</p></div>
<?php kp_topup_page_end(); ?>
