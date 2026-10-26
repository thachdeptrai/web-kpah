<?php
/** Admin-only history of confirmed Farmer Point gift claims (not game farming). */
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/admin_security.php';
require_once __DIR__.'/../includes/admin_nav.php';
require_once __DIR__.'/../includes/bank_farmer_reward.php';
$admin=kp_admin_require('/quan-tri/dang-nhap',['admin']);
kp_send_security_headers(true);
header('Content-Type: text/html; charset=utf-8');
$page=max(1,min(100000,(int)($_GET['page']??1)));
$filter=isset($_GET['q'])&&is_string($_GET['q'])?trim(substr($_GET['q'],0,60)):'';
$history=['rows'=>[],'total'=>0,'pages'=>1,'page'=>1];
$error=false;$usernames=[];
try {
    // Admin may inspect ALL claims, with pagination and an optional search.
    $history=kp_bank_farmer_history_page(null,$page,40,$filter);
} catch(Throwable $e) {
    kp_log('admin farmer claim history unavailable',$e);
    $error=true;
}
if (!$error && $history['rows']) {
    // IMPORTANT: array_unique() preserves sparse numeric keys. PDO positional
    // placeholders require a dense 0-based list or SQLSTATE[HY093] occurs
    // whenever multiple claimed orders share the same user.
    $ids=array_values(array_unique(array_map('intval',array_column($history['rows'],'user_id'))));
    try {
        $holders=implode(',',array_fill(0,count($ids),'?'));
        $st=kp_db()->prepare('SELECT id,username FROM team_user WHERE id IN ('.$holders.')');
        $st->execute($ids);
        foreach($st->fetchAll(PDO::FETCH_ASSOC) as $userRow)
            $usernames[(int)$userRow['id']]=(string)$userRow['username'];
    } catch(Throwable $e) {
        // User name enrichment is optional. Keep valid audit history visible
        // with numeric user IDs even when this lookup temporarily fails.
        kp_log('admin farmer claim username enrichment unavailable',$e);
    }
}
$pageLink=static function(int $number)use($filter):string {
    return '/quan-tri/diem-nong-dan/nhan-qua?'.http_build_query(['q'=>$filter,'page'=>$number],'','&',PHP_QUERY_RFC3986);
};
$labels=['completed'=>'Đã nhận','prepared'=>'Đang đối soát','needs_review'=>'Cần kiểm tra'];
?><!doctype html>
<html lang="vi"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Lịch sử nhận Điểm nông dân · Admin KPAH</title>
<link rel="stylesheet" href="/assets/css/admin-home.css?v=<?=(int)@filemtime(KP_ROOT.'/assets/css/admin-home.css')?>">
<link rel="stylesheet" href="/assets/css/admin-nav.css?v=<?=(int)@filemtime(KP_ROOT.'/assets/css/admin-nav.css')?>">
<link rel="stylesheet" href="/assets/css/admin-farmer.css?v=<?=(int)@filemtime(KP_ROOT.'/assets/css/admin-farmer.css')?>">
<link rel="stylesheet" href="/assets/css/site-responsive.css?v=<?=(int)@filemtime(KP_ROOT.'/assets/css/site-responsive.css')?>">
<link rel="stylesheet" href="/assets/css/admin-theme.css?v=<?=(int)@filemtime(KP_ROOT.'/assets/css/admin-theme.css')?>">
<link rel="stylesheet" href="/assets/css/admin-history-fixes.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin-history-fixes.css') ?>">
</head><body class="farmer-admin">
<?php kp_admin_nav('farmer-claims','admin'); ?>
<main class="farmer-shell farmer-claim-shell">
<header class="farmer-heading">
<div><span>KIỂM TRA QUÀ NẠP PAYOS</span><h1>Lịch sử nhận Điểm nông dân</h1>
<p>Toàn bộ tài khoản · ghi nhận lúc người chơi bấm Nhận điểm. Đây không phải nhật ký các lần farm/đổi vật phẩm trong game.</p></div>
<div><a class="farmer-back" href="/quan-tri/diem-nong-dan">Giám sát nhân vật →</a></div>
</header>
<section class="farmer-panel farmer-claim-panel">
<div class="farmer-claim-toolbar"><div><h2>Nhật ký nhận quà</h2>
<p><?=number_format($history['total'],0,',','.')?> bản ghi nhận quà hoặc chờ đối soát</p></div>
<form method="get" class="farmer-claim-filter">
<label for="farmer-claim-q">Mã đơn / nhân vật / ID tài khoản</label>
<div><input id="farmer-claim-q" name="q" type="search" maxlength="60" value="<?=kp_e($filter)?>" placeholder="Nhập mã đơn, ID, tên nhân vật">
<button type="submit">Tìm kiếm</button><a href="/quan-tri/diem-nong-dan/nhan-qua">Xóa lọc</a></div>
</form></div>
<?php if($error):?><p class="farmer-claim-alert" role="alert">Chưa tải được lịch sử nhận điểm. Kiểm tra PHP error log và quyền đọc thư mục lưu quà nạp; không có nghĩa là điểm đã bị mất.</p>
<?php elseif(!$history['rows']):?><p class="farmer-claim-empty">Không có dữ liệu nhận Điểm nông dân phù hợp.</p>
<?php else:?>
<div class="farmer-table-scroll farmer-claim-scroll" role="region" aria-label="Lịch sử nhận Điểm nông dân trên tất cả tài khoản" tabindex="0">
<table class="farmer-table farmer-claim-table">
<thead><tr><th>Mã đơn</th><th>Tài khoản</th><th>Nhân vật</th><th>Số điểm nhận</th>
<th>Trước → sau</th><th>Thời gian (VN)</th><th>Thiết bị</th><th>IP</th><th>Vị trí IP (ước lượng)</th><th>Trạng thái</th></tr></thead><tbody>
<?php foreach($history['rows'] as $r):?>
<tr>
<td>#<?=(int)$r['order_code']?></td>
<td><?=kp_e($usernames[$r['user_id']]??('ID #'.$r['user_id']))?> <small>(#<?=(int)$r['user_id']?>)</small></td>
<td><?=kp_e($r['charname']!==''?$r['charname']:'—')?> <small>(#<?=(int)$r['char_id']?>)</small></td>
<td class="farmer-claim-points">+<?=number_format($r['points'],0,',','.')?></td>
<td><?=$r['points_before']!==null&&$r['points_after']!==null
    ?number_format($r['points_before'],0,',','.').' → '.number_format($r['points_after'],0,',','.')
    :'Không có dữ liệu'?></td>
<td><?=kp_e(kp_bank_farmer_history_time($r['time']))?></td>
<td><?=kp_e($r['device']!==''?$r['device']:'Không có dữ liệu')?>
<?php if($r['os']!==''||$r['browser']!==''):?><small><?=kp_e(implode(' · ',array_filter([$r['os'],$r['browser']])))?></small><?php endif;?></td>
<td><?=kp_e($r['ip']!==''?$r['ip']:'Không có dữ liệu')?></td>
<td><?=kp_e(kp_bank_farmer_history_place($r['location']))?></td>
<td><span class="farmer-claim-status"><?=kp_e($labels[$r['status']]??$r['status'])?></span></td>
</tr>
<?php endforeach;?>
</tbody></table></div>
<nav class="farmer-claim-pagination" aria-label="Phân trang lịch sử">
<?php if($history['page']>1):?><a href="<?=kp_e($pageLink($history['page']-1))?>">← Trang trước</a><?php endif;?>
<span>Trang <?=(int)$history['page']?> / <?=(int)$history['pages']?></span>
<?php if($history['page']<$history['pages']):?><a href="<?=kp_e($pageLink($history['page']+1))?>">Trang sau →</a><?php endif;?>
</nav>
<?php endif;?>
<p class="farmer-claim-footnote">Các giao dịch nhận quà cũ thiếu IP và thiết bị sẽ được ghi “Không có dữ liệu”; không tự phục dựng. GeoIP là vị trí tương đối của mạng, không phải GPS. Bản ghi “Đang đối soát” không được phép tự động cấp điểm lần hai.</p>
</section>
</main></body></html>
