<?php
/**
 * Giám sát Điểm nông dân (read-only). KHÔNG phải Point của website.
 * Source game: tob_char.pInfo[117]; thông tin đối chiếu [118], [122].
 * Chỉ role admin truy cập; không có hành động thay đổi điểm.
 */
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/admin_security.php';
require_once __DIR__.'/../includes/admin_nav.php';
require_once __DIR__.'/../includes/farmer_points.php';
require_once __DIR__.'/../includes/farmer_monitor.php';
kp_admin_require();
kp_send_security_headers(true);
header('Content-Type: text/html; charset=utf-8');

$get=function(string $key, string $fallback=''): string {
    return isset($_GET[$key]) && is_string($_GET[$key]) ? $_GET[$key] : $fallback;
};
$q=trim($get('q'));
if(strlen($q)>60)$q=substr($q,0,60);
$state=$get('state','all');
$validStates=['all','positive','zero','high','missing','invalid','inactive'];
if(!in_array($state,$validStates,true))$state='all';
$sort=$get('sort','points_desc');
$sorts=[
    'points_desc'=>['Điểm cao nhất','t.farmer_points IS NULL ASC,t.farmer_points DESC,t.id ASC'],
    'points_asc'=>['Điểm thấp nhất','t.farmer_points IS NULL ASC,t.farmer_points ASC,t.id ASC'],
    'level_desc'=>['Cấp độ cao nhất','t.lastLv DESC,t.id ASC'],
    'recent'=>['Đăng nhập gần nhất','t.lastLog DESC,t.id DESC'],
    'name'=>['Tên nhân vật A-Z','t.charname ASC,t.id ASC'],
    'oldest'=>['Nhân vật tạo sớm','t.id ASC'],
];
if(!isset($sorts[$sort]))$sort='points_desc';
$perPage=(int)$get('size','25');
if(!in_array($perPage,[25,50,100],true))$perPage=25;
$page=max(1,min(100000,(int)$get('page','1')));
$limitValue=function(string $input): ?int {
    if($input==='' || !ctype_digit($input))return null;
    return min(2147483647,(int)$input);
};
$min=$limitValue($get('min'));
$max=$limitValue($get('max'));
$alert=$limitValue($get('alert','10000'));
if($alert===null || $alert<1)$alert=10000;

$rows=[];
$summary=['total'=>0,'malformed'=>0,'warnings'=>0,'max_points'=>null];
$error=false;
$pages=1;
try{
    $db=kp_db();
    // MySQL/MariaDB tương thích: vị trí thứ 118, đếm từ 1 (Java index 117).
    $raw="CASE WHEN c.pInfo IS NOT NULL AND
                (LENGTH(c.pInfo)-LENGTH(REPLACE(c.pInfo,',',''))) >= 117
                THEN TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(c.pInfo,',',118),',',-1))
                ELSE NULL END";
    $valid="s.farmer_raw REGEXP '^[0-9]{1,10}$'
            AND CAST(s.farmer_raw AS UNSIGNED)<=2147483647";
    $source="FROM (
       SELECT s.*,CASE WHEN $valid THEN CAST(s.farmer_raw AS UNSIGNED) ELSE NULL END AS farmer_points,
              CASE WHEN s.farmer_raw IS NULL THEN 'missing'
                   WHEN $valid THEN 'valid' ELSE 'invalid' END AS farmer_state
       FROM (
          SELECT c.id,c.charname,c.userId,c.lastLv,c.lastLog,c.del,c.ban,c.pInfo,
                 u.username,$raw AS farmer_raw
          FROM tob_char AS c LEFT JOIN team_user AS u ON u.id=c.userId
       ) AS s
    ) AS t";
    $where=[];
    $bindings=[];
    if($q!==''){
        $search='%'.str_replace(['!','%','_'],['!!','!%','!_'],$q).'%';
        $where[]="(t.charname LIKE :charname ESCAPE '!' OR t.username LIKE :username ESCAPE '!')";
        $bindings[':charname']=$search;
        $bindings[':username']=$search;
    }
    if($state==='positive')$where[]='t.farmer_points>0';
    elseif($state==='zero')$where[]='t.farmer_points=0';
    elseif($state==='high'){$where[]='t.farmer_points>=:high_points';$bindings[':high_points']=$alert;}
    elseif($state==='missing')$where[]="t.farmer_state='missing'";
    elseif($state==='invalid')$where[]="t.farmer_state='invalid'";
    elseif($state==='inactive')$where[]='(t.del<>1 OR t.ban<>0)';
    if($min!==null){$where[]='t.farmer_points>=:min_points';$bindings[':min_points']=$min;}
    if($max!==null){$where[]='t.farmer_points<=:max_points';$bindings[':max_points']=$max;}
    $whereSql=$where?' WHERE '.implode(' AND ',$where):'';
    $statSql="SELECT COUNT(*) AS total,
            SUM(CASE WHEN t.farmer_state='invalid' THEN 1 ELSE 0 END) AS malformed,
            SUM(CASE WHEN t.farmer_points>=:alert_total THEN 1 ELSE 0 END) AS warnings,
            MAX(t.farmer_points) AS max_points $source $whereSql";
    $st=$db->prepare($statSql);
    foreach($bindings as $key=>$value)$st->bindValue($key,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR);
    $st->bindValue(':alert_total',$alert,PDO::PARAM_INT);
    $st->execute();
    $summary=$st->fetch(PDO::FETCH_ASSOC)?:$summary;
    $total=(int)$summary['total'];
    $pages=max(1,(int)ceil($total/$perPage));
    $page=min($page,$pages);
    $offset=($page-1)*$perPage;
    $sortSql=$sorts[$sort][1]; // Danh sách cố định; không lấy tên cột từ request.
    $st=$db->prepare("SELECT t.id,t.userId,t.charname,t.username,t.lastLv,t.lastLog,
                              t.del,t.ban,t.pInfo,t.farmer_raw,t.farmer_points,t.farmer_state
                       $source $whereSql ORDER BY $sortSql LIMIT :offset,:size");
    foreach($bindings as $key=>$value)$st->bindValue($key,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR);
    $st->bindValue(':offset',$offset,PDO::PARAM_INT);
    $st->bindValue(':size',$perPage,PDO::PARAM_INT);
    $st->execute();
    $rows=$st->fetchAll(PDO::FETCH_ASSOC);
}catch(Throwable $e){
    kp_log('admin farmer points monitoring unavailable',$e);
    $error=true;
}
$monitorReady=false;$monitorStats=[];$monitorLast=null;$monitorHot=[];
try{
    $monitorReady=kp_farmer_monitor_ready(kp_db());
    if($monitorReady){
        $monitorStats=kp_farmer_monitor_hourly(kp_db(),array_column($rows,'id'));
        $monitorHot=kp_farmer_monitor_hot(kp_db());
        $monitorLast=kp_db()->query('SELECT MAX(sampled_at) FROM kp_farmer_monitor_state')->fetchColumn();
    }
}catch(Throwable $e){kp_log('farmer monitor summary unavailable',$e);$monitorReady=false;}
$filterQuery=['q'=>$q,'state'=>$state,'sort'=>$sort,'size'=>$perPage,'alert'=>$alert];
if($min!==null)$filterQuery['min']=$min;
if($max!==null)$filterQuery['max']=$max;
$pageUrl=function(int $num)use($filterQuery): string{
    return '/quan-tri/diem-nong-dan?'.http_build_query(array_merge($filterQuery,['page'=>$num]),'','&',PHP_QUERY_RFC3986);
};
$format=function($v): string{
    return $v===null?'—':number_format((int)$v,0,',','.');
};
?><!doctype html>
<html lang="vi"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Giám sát Điểm nông dân · KPAH</title>
<link rel="stylesheet" href="/assets/css/admin-home.css?v=1">
<link rel="stylesheet" href="/assets/css/admin-nav.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin-nav.css') ?>">
<link rel="stylesheet" href="/assets/css/admin-farmer.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin-farmer.css') ?>">
<link rel="stylesheet" href="/assets/css/site-responsive.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/site-responsive.css') ?>">
</head><body>
<header class="topbar">
  <a class="brand" href="/quan-tri"><span class="brand-icon"><img src="/assets/img/emblem.svg" width="50" height="50" alt=""></span><span><small>NGHIỆN KPAH · QUẢN TRỊ</small><strong>Điểm nông dân</strong></span></a>
  <div class="top-actions"><span class="secure"><i></i> Chỉ đọc</span><span class="user"><?= kp_e($_SESSION['kp_admin_username']??'admin') ?></span><a class="logout" href="/quan-tri/dang-xuat">Đăng xuất ↗</a></div>
</header>
<?php kp_admin_nav('farmer','admin'); ?>
<main class="shell farmer-shell">
  <section class="welcome farmer-hero">
    <div><div class="eyebrow">GAME DATA / GIÁM SÁT</div><h1>Điểm <span>nông dân.</span></h1>
    <p>Theo dõi số dư điểm nông dân của từng nhân vật trong database game. Tìm kiếm, lọc giá trị không hợp lệ và kiểm tra điểm cao bất thường, không ghi thay đổi vào SQL.</p></div>
    <span class="farmer-readonly">● LIVE DATA · READ ONLY</span>
  </section>
  <?php if($monitorReady): ?>
  <div class="farmer-monitor-status"><strong><?= $monitorLast && strtotime((string)$monitorLast)>=time()-900?'Đang ghi lịch sử':'Chưa có lượt quét gần đây' ?></strong>
  <span>Mẫu gần nhất: <?= $monitorLast?kp_e($monitorLast):'Chưa có' ?></span></div>
  <?php else: ?>
  <div class="alert" role="status"><strong>Chưa bật lưu lịch sử.</strong> Số dư game vẫn đọc bình thường. Chạy <code>php tools/farmer-monitor.php install</code> và đặt lịch theo <code>docs/FARMER_MONITORING.md</code>.</div>
  <?php endif; ?>
  <?php if($error): ?><div class="alert" role="alert">Không tải được dữ liệu Điểm nông dân. Kiểm tra kết nối database và log PHP trên máy chủ.</div><?php endif; ?>
  <section class="metrics farmer-metrics" aria-label="Tổng hợp theo bộ lọc">
    <article class="metric"><div class="metric-top">Nhân vật khớp lọc <span>♙</span></div><strong><?= $format($summary['total']??0) ?></strong><small>Tổng bản ghi phù hợp</small></article>
    <article class="metric"><div class="metric-top">Điểm cao nhất <span>✦</span></div><strong><?= $format($summary['max_points']??null) ?></strong><small>Trong tập kết quả đã lọc</small></article>
    <article class="metric"><div class="metric-top">Vượt ngưỡng theo dõi <span>⚑</span></div><strong><?= $format($summary['warnings']??0) ?></strong><small>Từ <?= $format($alert) ?> điểm trở lên · chưa kết luận bug</small></article>
    <article class="metric"><div class="metric-top">Dữ liệu không hợp lệ <span>!</span></div><strong><?= $format($summary['malformed']??0) ?></strong><small>Có trường điểm nhưng sai định dạng</small></article>
  </section>
  <?php if($monitorReady && $q==='' && $state==='all' && $min===null && $max===null): ?>
  <section class="panel farmer-panel" aria-label="Biến động 60 phút">
    <div class="panel-heading"><div><div class="eyebrow">GIÁM SÁT / 60 PHÚT</div><h2>Nhân vật tăng điểm nhanh</h2><p>Theo số dư tăng giữa các lượt quét, không phải kết luận bug.</p></div><span class="live">Lịch sử DB</span></div>
    <?php if($monitorHot): ?><div class="farmer-hot-list">
    <?php foreach($monitorHot as $hot): ?>
      <a class="farmer-hot-card" href="/quan-tri/diem-nong-dan/lich-su?id=<?= (int)$hot['char_id'] ?>"><strong><?= kp_e($hot['charname']) ?></strong><span>Điểm +<?= number_format((int)$hot['points_up'],0,',','.') ?></span><span>Lượng +<?= number_format((int)$hot['luong_up'],0,',','.') ?></span>↗</a>
    <?php endforeach; ?></div><?php else: ?><p class="farmer-caption">Chưa có biến động trong 60 phút.</p><?php endif; ?>
  </section>
  <?php endif; ?>
  <section class="panel farmer-panel" aria-labelledby="farmer-filter-heading">
    <div class="panel-heading"><div><div class="eyebrow">BỘ LỌC</div><h2 id="farmer-filter-heading">Truy vấn nhân vật</h2><p>Thay ngưỡng theo dõi để khoanh vùng các tài khoản có điểm cao khác thường.</p></div><a href="/quan-tri/diem-nong-dan" class="farmer-reset">Đặt lại bộ lọc ↺</a></div>
    <form class="farmer-form" method="get" action="/quan-tri/diem-nong-dan">
      <label class="farmer-field farmer-search"><span>Tài khoản / tên nhân vật</span><input name="q" maxlength="60" value="<?= kp_e($q) ?>" placeholder="Nhập tên tài khoản hoặc nhân vật" autocomplete="off"></label>
      <label class="farmer-field"><span>Trạng thái</span><select name="state">
        <?php foreach(['all'=>'Tất cả','positive'=>'Có điểm (> 0)','zero'=>'Điểm bằng 0','high'=>'Vượt ngưỡng','missing'=>'Chưa có dữ liệu','invalid'=>'Dữ liệu lỗi','inactive'=>'Nhân vật khóa / xóa'] as $key=>$label): ?>
        <option value="<?= kp_e($key) ?>"<?= $state===$key?' selected':'' ?>><?= kp_e($label) ?></option>
        <?php endforeach; ?>
      </select></label>
      <label class="farmer-field"><span>Sắp xếp</span><select name="sort">
        <?php foreach($sorts as $key=>$item): ?>
        <option value="<?= kp_e($key) ?>"<?= $sort===$key?' selected':'' ?>><?= kp_e($item[0]) ?></option>
        <?php endforeach; ?>
      </select></label>
      <label class="farmer-field"><span>Điểm từ</span><input type="number" name="min" min="0" max="2147483647" value="<?= $min===null?'':$min ?>" placeholder="Không giới hạn"></label>
      <label class="farmer-field"><span>Đến điểm</span><input type="number" name="max" min="0" max="2147483647" value="<?= $max===null?'':$max ?>" placeholder="Không giới hạn"></label>
      <label class="farmer-field"><span>Ngưỡng cảnh báo</span><input type="number" name="alert" min="1" max="2147483647" value="<?= $alert ?>"></label>
      <label class="farmer-field"><span>Số dòng / trang</span><select name="size">
        <?php foreach([25,50,100] as $size): ?><option value="<?= $size ?>"<?= $perPage===$size?' selected':'' ?>><?= $size ?> dòng</option><?php endforeach; ?>
      </select></label>
      <button class="farmer-apply" type="submit">Áp dụng bộ lọc ↗</button>
    </form>
  </section>
  <section class="panel farmer-panel" aria-labelledby="farmer-table-heading">
    <div class="panel-heading"><div><div class="eyebrow">DANH SÁCH / ĐỐI SOÁT</div><h2 id="farmer-table-heading">Chi tiết nhân vật</h2>
      <p>Trang <?= $page ?> / <?= $pages ?> · <?= $format($summary['total']??0) ?> kết quả · tải dữ liệu khi mở trang (không tự cập nhật).</p></div>
      <span class="live">Database game</span>
    </div>
    <div class="table-scroll farmer-table-scroll"><table class="farmer-table">
      <thead><tr><th>ID</th><th>Tài khoản</th><th>Nhân vật</th><th>Cấp</th><th>Điểm nông dân</th><th>Điểm +1h</th><th>Lượng up +1h</th><th>Lượng up TB/giờ (24h)</th><th>Hộp/ngày</th><th>Online farm/ngày</th><th>Đăng nhập gần nhất</th><th>Đánh giá dữ liệu</th><th>Lịch sử</th></tr></thead>
      <tbody>
      <?php foreach($rows as $r):
         $trend=$monitorStats[(int)$r['id']]??null;
         $point=$r['farmer_points']===null?null:(int)$r['farmer_points'];
         $boxes=kp_farmer_field($r['pInfo']??null,118,2147483647);
         $farmMs=kp_farmer_field($r['pInfo']??null,122);
         $farmHours=$farmMs===null?'—':number_format($farmMs/3600000,1,',','.').' giờ';
         $bad=$r['farmer_state']==='invalid';
         $missing=$r['farmer_state']==='missing';
         $high=$point!==null && $point>=$alert;
         $inactive=(int)$r['del']!==1 || (int)$r['ban']!==0;
         $last=(string)($r['lastLog']??'');
         $label=$bad?'Sai định dạng':($missing?'Chưa có dữ liệu':($high?'Cần kiểm tra':'Hợp lệ'));
         if($inactive)$label.=' · Đã khóa/xóa';
         $class=$bad?'farmer-bad':($high?'farmer-warning':($missing?'farmer-neutral':'farmer-ok'));
      ?>
        <tr>
          <td class="id">#<?= (int)$r['id'] ?></td>
          <td class="username"><?= kp_e($r['username']??('ID #'.$r['userId'])) ?></td>
          <td><?= kp_e($r['charname']) ?></td>
          <td><?= (int)$r['lastLv'] ?></td>
          <td class="farmer-score"><?= $format($point) ?></td>
          <td><?= !$trend?'—':$format($trend['points_1h']) ?></td>
          <td><?= !$trend?'—':$format($trend['luong_1h']) ?></td>
          <td><?= !$trend||$trend['avg_luong_hour']===null?'—':number_format($trend['avg_luong_hour'],1,',','.') ?></td>
          <td><?= $format($boxes) ?></td>
          <td><?= kp_e($farmHours) ?></td>
          <td><?= $last==='' || $last==='2010-01-01 00:00:00'?'—':kp_e($last) ?></td>
          <td><span class="farmer-pill <?= $class ?>"><?= kp_e($label) ?></span>
            <?php if($bad): ?><small class="farmer-raw" title="Giá trị tại pInfo[117]"><?= kp_e(substr((string)$r['farmer_raw'],0,32)) ?></small><?php endif; ?>
          </td>
          <td><?= $monitorReady?'<a class="farmer-history-link" href="/quan-tri/diem-nong-dan/lich-su?id='.(int)$r['id'].'">Xem ↗</a>':'—' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if(!$rows): ?><tr><td class="empty" colspan="13"><?= $error?'Không thể tải dữ liệu.':'Không có nhân vật phù hợp với bộ lọc.' ?></td></tr><?php endif; ?>
      </tbody>
    </table></div>
    <nav class="farmer-pagination" aria-label="Phân trang bảng giám sát">
      <?php if($page>1): ?><a href="<?= kp_e($pageUrl($page-1)) ?>">← Trang trước</a><?php endif; ?>
      <span>Trang <?= $page ?> / <?= $pages ?></span>
      <?php if($page<$pages): ?><a href="<?= kp_e($pageUrl($page+1)) ?>">Trang tiếp →</a><?php endif; ?>
    </nav>
    <p class="farmer-caption">Lượng up là tổng phần tăng dương quan sát được của số dư Lượng trong DB, không khẳng định Lượng kiếm được từ farm. Trung bình/giờ sử dụng thời gian theo dõi thực tế trong tối đa 24 giờ. Nếu game chưa lưu dữ liệu mới, số liệu sẽ chậm.</p>
    <p class="farmer-caption">Điểm được đọc từ <code>tob_char.pInfo[117]</code>; hộp/ngày: <code>[118]</code>; online farm/ngày: <code>[122]</code> (đổi mili-giây sang giờ). Dấu “—” là thiếu hoặc không đọc được dữ liệu. Ngưỡng cảnh báo chỉ phục vụ rà soát, không tự kết luận gian lận; điểm của nhân vật đang online có thể chỉ cập nhật sau khi server lưu.</p>
  </section>
  <footer><span>KPAH · GIÁM SÁT ĐIỂM NÔNG DÂN</span><a href="/quan-tri">← Quay về tổng quan quản trị</a></footer>
</main></body></html>
