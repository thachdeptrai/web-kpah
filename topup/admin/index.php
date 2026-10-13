<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/topup_core.php';
require_once __DIR__ . '/../_layout.php';
$admin=kp_topup_admin();$orders=kp_topup_recent('orders',null,500);$grants=kp_topup_recent('grants',null,500);
$pending=0;foreach($orders as $o){if(in_array($o['status'],['needs_review','crediting','verified_pending_delivery','submitting'],true))$pending++;}
kp_topup_page_start('Quản lý nạp','index',(string)$admin['username'],true);?>
<section class="tp-hero"><span class="tp-kicker"><i></i> ADMIN / NẠP TÀI NGUYÊN</span><h1>Giao dịch <em>& tài nguyên.</em></h1><p class="tp-lead">Tỷ lệ nạp, cộng tài nguyên và đối soát — mỗi việc có một mục riêng.</p></section>
<section class="tp-section"><div class="tp-summary tp-mb-22"><div><small>Yêu cầu thẻ đang lưu</small><strong><?= count($orders) ?></strong></div><div><small>Yêu cầu cần chú ý</small><strong><?= $pending ?></strong></div><div><small>Lượt cộng thủ công gần đây</small><strong><?= count($grants) ?></strong></div></div>
<div class="tp-grid"><a class="tp-card" href="/quan-tri/nap/ti-le"><span class="tp-icon">⚙</span><h3>Tỷ lệ quy đổi</h3><p>Đổi tỷ lệ nhận Xu, Lượng thường và Lượng khóa.</p><span class="tp-card-cta">Chỉnh tỷ lệ <strong>↗</strong></span></a>
<a class="tp-card" href="/quan-tri/nap/cong-tai-nguyen"><span class="tp-icon purple">✦</span><h3>Cộng tài nguyên</h3><p>Tìm nhân vật và cộng tài nguyên khi người chơi đã thoát game.</p><span class="tp-card-cta">Cộng thủ công <strong>↗</strong></span></a>
<a class="tp-card" href="/quan-tri/nap/doi-soat"><span class="tp-icon green">◷</span><h3>Lịch sử & đối soát</h3><p>Kiểm tra đơn nạp, giao dịch cần đối soát và các lần cộng thủ công.</p><span class="tp-card-cta">Mở đối soát <strong>↗</strong></span></a>
<a class="tp-card" href="/quan-tri/nap/du-lieu-game"><span class="tp-icon">▤</span><h3>Dữ liệu nạp game</h3><p>Xem dữ liệu game đã ghi nhận, chỉ để kiểm tra.</p><span class="tp-card-cta">Xem bảng nạp <strong>↗</strong></span></a></div></section>
<div class="tp-alert warning">Nạp thẻ tự động vẫn đang khóa. Cần kiểm tra luồng nhận tài nguyên của game trước khi mở.</div>
<?php kp_topup_page_end(); ?>
