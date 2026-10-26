<?php
/** Player-visible history: always scoped to the authenticated account ID. */
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/bank_farmer_reward.php';
require_once __DIR__.'/_layout.php';
$user=kp_topup_user();
$page=max(1,min(100000,(int)($_GET['page']??1)));
$filter=isset($_GET['q'])&&is_string($_GET['q'])?trim(substr($_GET['q'],0,60)):'';
$history=['rows'=>[],'total'=>0,'pages'=>1,'page'=>1];
$error=false;
try {
    $history=kp_bank_farmer_history_page((int)$user['id'],$page,20,$filter);
} catch(Throwable $e) {
    kp_log('player farmer gift audit history unavailable',$e);
    $error=true;
}
$pageLink=static function(int $number)use($filter):string {
    return '/lich-su-nhan-diem-nong-dan?'.http_build_query(['q'=>$filter,'page'=>$number],'','&',PHP_QUERY_RFC3986);
};
$labels=['completed'=>'Đã nhận','prepared'=>'Đang đối soát','needs_review'=>'Cần kiểm tra'];
kp_topup_page_start('Lịch sử nhận Điểm nông dân','farmer-history',(string)$user['username']);
?>
<section class="tp-hero"><span class="tp-kicker"><i></i> LỊCH SỬ QUÀ NẠP</span>
<h1>Điểm nông dân <em>đã nhận.</em></h1>
<p class="tp-lead">Chỉ hiển thị các lần nhận quà của tài khoản đang đăng nhập. Lưu thời gian, nhân vật, số điểm và thông tin mạng/thiết bị từ thời điểm bấm Nhận điểm.</p></section>
<section class="tp-section"><div class="tp-panel">
<div class="tp-panel-head"><div><span class="tp-eyebrow">NHẬT KÝ CỦA TÔI</span><h2>Lịch sử nhận điểm</h2></div>
<a class="tp-button-secondary" href="/nap-ngan-hang#qua-nap-bank">Nhận quà mới</a></div>
<form method="get" class="tp-farmer-audit-search"><label class="tp-field">Tìm theo mã đơn hoặc tên nhân vật
<input class="tp-input" type="search" name="q" maxlength="60" value="<?=kp_e($filter)?>" placeholder="Mã đơn / Nhân vật"></label>
<button type="submit" class="tp-button">Tìm</button><a href="/lich-su-nhan-diem-nong-dan" class="tp-button-secondary">Xóa lọc</a></form>
<?php if($error):?>
<div class="tp-alert bad" role="alert">Chưa đọc được lịch sử nhận điểm. Vui lòng thử lại sau.</div>
<?php elseif(!$history['rows']):?>
<div class="tp-alert warning">Chưa có lượt nhận Điểm nông dân phù hợp. Đơn nạp mới đủ điều kiện sẽ có nút Nhận điểm tại trang Nạp ngân hàng.</div>
<?php else:?>
<p class="tp-muted">Tìm thấy <?=number_format($history['total'],0,',','.')?> lượt nhận / đối soát.</p>
<div class="tp-farmer-audit-list">
<?php foreach($history['rows'] as $r):?>
<article class="tp-farmer-audit-card">
<div class="tp-farmer-audit-card-head">
<div><span class="tp-eyebrow">ĐƠN #<?=(int)$r['order_code']?></span><h3><?=kp_e($r['charname']!==''?$r['charname']:'Nhân vật không xác định')?></h3></div>
<div><strong class="tp-farmer-audit-points">+<?=number_format($r['points'],0,',','.')?> điểm</strong><span class="tp-farmer-audit-status"><?=kp_e($labels[$r['status']]??'Chờ xử lý')?></span></div>
</div>
<dl class="tp-farmer-audit-detail">
<div><dt>Thời gian (Việt Nam)</dt><dd><?=kp_e(kp_bank_farmer_history_time($r['time']))?></dd></div>
<div><dt>Điểm trước → sau</dt><dd><?=$r['points_before']===null||$r['points_after']===null?'Không có dữ liệu':number_format($r['points_before'],0,',','.').' → '.number_format($r['points_after'],0,',','.')?></dd></div>
<div><dt>Thiết bị</dt><dd><?=kp_e($r['device']!==''?$r['device']:'Không có dữ liệu')?><?= $r['os']!==''?' · '.kp_e($r['os']):''?><?= $r['browser']!==''?' · '.kp_e($r['browser']):''?></dd></div>
<div><dt>IP nhận điểm</dt><dd><?=kp_e($r['ip']!==''?$r['ip']:'Không có dữ liệu')?></dd></div>
<div><dt>Vị trí IP (ước lượng)</dt><dd><?=kp_e(kp_bank_farmer_history_place($r['location']))?></dd></div>
<div><dt>Số tiền nạp</dt><dd><?=number_format($r['amount'],0,',','.')?>đ</dd></div>
</dl>
</article>
<?php endforeach;?>
</div>
<nav class="tp-farmer-audit-pages" aria-label="Phân trang lịch sử">
<?php if($history['page']>1):?><a href="<?=kp_e($pageLink($history['page']-1))?>">← Trang trước</a><?php endif;?>
<span>Trang <?=(int)$history['page']?> / <?=(int)$history['pages']?></span>
<?php if($history['page']<$history['pages']):?><a href="<?=kp_e($pageLink($history['page']+1))?>">Trang sau →</a><?php endif;?>
</nav>
<?php endif;?>
<p class="tp-muted tp-mt-16">Thông tin IP/thiết bị chỉ được lưu từ lúc tính năng nhật ký này triển khai. Vị trí IP không phải GPS; nếu máy chủ chưa có GeoIP, hệ thống hiển thị “Không xác định”. Hoạt động tích Điểm nông dân bên trong game không nằm trong nhật ký quà nạp này.</p>
</div></section>
<?php kp_topup_page_end(); ?>
