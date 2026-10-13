<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/topup_core.php';
require_once __DIR__ . '/../_layout.php';
$admin=kp_topup_admin();$orders=kp_topup_recent('orders',null,500);$grants=kp_topup_recent('grants',null,500);
$pending=0;foreach($orders as $o){if(in_array($o['status'],['needs_review','crediting','verified_pending_delivery','submitting'],true))$pending++;}
kp_topup_page_start('Quản lý nạp','index',(string)$admin['username'],true);?>
<section class="tp-hero"><span class="tp-kicker"><i></i> ADMIN / NẠP TÀI NGUYÊN</span><h1>Quản lý nạp <em>tách riêng.</em></h1><p class="tp-lead">Chỉnh tỷ lệ, cộng Xu/Lượng/Lượng khóa và đối soát thẻ ở từng trang. Không ảnh hưởng những mục quản trị đang chạy.</p></section>
<section class="tp-section"><div class="tp-summary tp-mb-22"><div><small>Yêu cầu thẻ đang lưu</small><strong><?= count($orders) ?></strong></div><div><small>Yêu cầu cần chú ý</small><strong><?= $pending ?></strong></div><div><small>Lượt cộng thủ công gần đây</small><strong><?= count($grants) ?></strong></div></div>
<div class="tp-grid"><a class="tp-card" href="rates.php"><span class="tp-icon">⚙</span><h3>Tỷ lệ quy đổi</h3><p>Đặt mỗi 10.000đ nhận bao nhiêu Xu, Lượng thường hoặc Lượng khóa. Không ép mốc nạp.</p><span class="tp-card-cta">Chỉnh tỷ lệ <strong>↗</strong></span></a>
<a class="tp-card" href="grant.php"><span class="tp-icon purple">✦</span><h3>Cộng tài nguyên</h3><p>Tìm tài khoản, chọn đúng nhân vật và cộng tài nguyên khi nhân vật đã đăng xuất game.</p><span class="tp-card-cta">Cộng thủ công <strong>↗</strong></span></a>
<a class="tp-card" href="orders.php"><span class="tp-icon green">◷</span><h3>Lịch sử & đối soát</h3><p>Xem kết quả thẻ cào, kiểm tra lại trạng thái qua đối tác và rà soát các lần cộng.</p><span class="tp-card-cta">Mở đối soát <strong>↗</strong></span></a>
<a class="tp-card" href="legacy.php"><span class="tp-icon">▤</span><h3>Dữ liệu nạp game</h3><p>Xem trực tiếp board_naptien và board_created. Chỉ đọc, không chỉnh sửa bảng game.</p><span class="tp-card-cta">Xem bảng nạp <strong>↗</strong></span></a></div></section>
<div class="tp-alert warning">Không tự bật cấp tiền qua <code>board_naptien</code> khi chưa xác nhận game server xử lý bảng này. Thẻ hợp lệ sẽ nằm ở trạng thái chờ cấp.</div>
<?php kp_topup_page_end(); ?>
