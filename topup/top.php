<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/topup_core.php';
require_once __DIR__ . '/_layout.php';
$user=kp_topup_user();$top=[];$err=false;
try {$st=kp_db()->query('SELECT charname,luongnap FROM tob_event_top WHERE luongnap > 0 ORDER BY luongnap DESC,charname ASC LIMIT 20');$top=$st->fetchAll();}
catch(Throwable $e){kp_log('topup leaderboard unavailable',$e);$err=true;}
kp_topup_page_start('Top nạp Lượng','top',(string)$user['username']);?>
<section class="tp-hero"><span class="tp-kicker"><i></i> BẢNG XẾP HẠNG</span><h1>Top nạp <em>Lượng.</em></h1><p class="tp-lead">Bảng xếp hạng lấy từ thống kê nạp Lượng có sẵn trong game. Chỉ công khai tên nhân vật và số Lượng được ghi nhận.</p></section>
<section class="tp-section"><div class="tp-panel"><div class="tp-panel-head"><div><span class="tp-eyebrow">XẾP HẠNG</span><h2>20 nhân vật đứng đầu</h2></div><span class="tp-chip">TOP 20</span></div>
<?php if($err): ?><div class="tp-alert bad">Chưa tải được bảng xếp hạng.</div>
<?php elseif(!$top): ?><div class="tp-alert warning">Chưa có dữ liệu nạp Lượng để xếp hạng.</div>
<?php else: ?><div class="tp-table-wrap"><table class="tp-table tp-table--narrow"><thead><tr><th>Hạng</th><th>Nhân vật</th><th>Lượng đã nạp</th></tr></thead><tbody>
<?php foreach($top as $i=>$p): ?><tr><td><span class="tp-pill"><?= str_pad((string)($i+1),2,'0',STR_PAD_LEFT) ?></span></td><td><b><?= kp_e($p['charname']) ?></b></td><td><?= number_format((float)$p['luongnap'],0,',','.') ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
</div></section><?php kp_topup_page_end(); ?>
