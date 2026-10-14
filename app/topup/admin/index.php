<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/topup_core.php';
require_once __DIR__ . '/../_layout.php';
$admin=kp_topup_admin();$orders=kp_topup_recent('orders',null,500);$grants=kp_topup_recent('grants',null,500);
$pending=0;foreach($orders as $o){if(in_array($o['status'],['needs_review','crediting','verified_pending_delivery','submitting'],true))$pending++;}
$bankCount=0;$bankAttention=0;$bankPaid=0;
try {
 $row=kp_db()->query("SELECT COUNT(*) AS total,COALESCE(SUM(status IN ('needs_review','crediting','paid_waiting_delivery')),0) AS attention,COALESCE(SUM(status IN ('queued','completed','paid_waiting_delivery','needs_review','crediting')),0) AS paid FROM kp_bank_orders")->fetch(PDO::FETCH_ASSOC);
 $bankCount=(int)$row['total'];$bankAttention=(int)$row['attention'];$bankPaid=(int)$row['paid'];
}catch(Throwable $e){kp_log('admin payOS overview query failed',$e);}

kp_topup_page_start('Quản lý nạp','index',(string)$admin['username'],true);?>
<section class="tp-hero"><span class="tp-kicker"><i></i> ADMIN / NẠP TÀI NGUYÊN</span><h1>Quản lý nạp <em>tách riêng.</em></h1><p class="tp-lead">Tỷ lệ nạp, cộng tài nguyên và đối soát — mỗi mục ở một nơi.</p></section>
<section class="tp-section"><div class="tp-summary tp-mb-22"><div><small>Yêu cầu thẻ đang lưu</small><strong><?= count($orders) ?></strong></div><div><small>Yêu cầu cần chú ý</small><strong><?= $pending ?></strong></div><div><small>Lượt cộng thủ công gần đây</small><strong><?= count($grants) ?></strong></div><div><small>Đơn payOS</small><strong><?= $bankCount ?></strong></div><div><small>Đơn payOS cần đối soát</small><strong><?= $bankAttention ?></strong></div></div>
<div class="tp-grid"><a class="tp-card" href="/quan-tri/ty-le"><span class="tp-icon">⚙</span><h3>Tỷ lệ quy đổi</h3><p>Thay đổi tỷ lệ nhận Xu, Lượng thường và Lượng khóa.</p><span class="tp-card-cta">Chỉnh tỷ lệ <strong>↗</strong></span></a>
<a class="tp-card" href="/quan-tri/cong-tai-nguyen"><span class="tp-icon purple">✦</span><h3>Cộng tài nguyên</h3><p>Chọn nhân vật đã thoát game, nhập số cần cộng và xác nhận.</p><span class="tp-card-cta">Cộng thủ công <strong>↗</strong></span></a>
<a class="tp-card" href="/quan-tri/doi-soat"><span class="tp-icon green">◷</span><h3>Lịch sử & đối soát</h3><p>Xem đầy đủ đơn payOS, thẻ cào và lịch sử cộng trực tiếp hoặc thủ công.</p><span class="tp-card-cta">Mở đối soát <strong>↗</strong></span></a>
<a class="tp-card" href="/quan-tri/nap-game"><span class="tp-icon">▤</span><h3>Dữ liệu nạp game</h3><p>Xem dữ liệu nạp game hiện có, chỉ để kiểm tra.</p><span class="tp-card-cta">Xem bảng nạp <strong>↗</strong></span></a></div></section>
<div class="tp-alert warning">Các đơn payOS có trạng thái <code>needs_review</code> hoặc <code>crediting</code> cần kiểm tra trước khi cấp thêm tài nguyên để tránh cộng trùng.</div>
<?php kp_topup_page_end(); ?>
