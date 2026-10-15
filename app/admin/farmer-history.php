<?php
/**
 * Admin-only forensic history for a single game character.
 * Observations are derived from saved MySQL balances and do not prove the
 * underlying cause of a change. Never updates game tables.
 */
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/admin_security.php';
require_once __DIR__.'/../includes/admin_nav.php';
require_once __DIR__.'/../includes/farmer_points.php';
require_once __DIR__.'/../includes/farmer_monitor.php';
kp_admin_require();
kp_send_security_headers(true);
header('Content-Type: text/html; charset=utf-8');

$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$id){http_response_code(404);exit('Không tìm thấy nhân vật.');}
$mode=isset($_GET['mode']) && is_string($_GET['mode'])?(string)$_GET['mode']:'all';
if(!in_array($mode,['all','points','luong','up','alerts'],true))$mode='all';
$limit=(int)($_GET['alert']??6000);
$limit=max(100,min(1000000,$limit));
$page=max(1,min(100000,(int)($_GET['page']??1)));
$db=kp_db();
$st=$db->prepare('SELECT c.id,c.charname,c.userId,c.lastLv,c.pInfo,c.luong,u.username
                  FROM tob_char c LEFT JOIN team_user u ON u.id=c.userId
                  WHERE c.id=? LIMIT 1');
$st->execute([(int)$id]);
$char=$st->fetch(PDO::FETCH_ASSOC);
if(!$char){http_response_code(404);exit('Không tìm thấy nhân vật.');}
$farmer=kp_farmer_points($char['pInfo']);
$ready=kp_farmer_monitor_ready($db);
$stats=null;$rows=[];$hourly=[];$total=0;$pages=1;$historyError=false;$monitorInfo=null;
$now=time();
$cutoff=date('Y-m-d H:i:s',$now-7*86400);
if($ready){
    try{
        $metric=kp_farmer_monitor_hourly($db,[(int)$id]);
        $stats=$metric[(int)$id]??null;
        $st=$db->prepare('SELECT first_sampled_at,sampled_at FROM kp_farmer_monitor_state WHERE char_id=?');
        $st->execute([(int)$id]);
        $monitorInfo=$st->fetch(PDO::FETCH_ASSOC)?:null;

        // The selected filter has a fixed SQL fragment (never interpolates request).
        $conditions=[
            'all'=>'1=1',
            'points'=>'points_delta IS NOT NULL AND points_delta<>0',
            'luong'=>'luong_delta<>0',
            'up'=>'points_delta>0 OR luong_delta>0',
            'alerts'=>'points_delta>0 AND interval_seconds BETWEEN 1 AND 900 AND points_delta*3600 > ?*interval_seconds'
        ];
        $where='char_id=? AND observed_at>=? AND ('.$conditions[$mode].')';
        $params=[(int)$id,$cutoff];
        if($mode==='alerts')$params[]=$limit;
        $st=$db->prepare('SELECT COUNT(*) FROM kp_farmer_monitor_changes WHERE '.$where);
        $st->execute($params);
        $total=(int)$st->fetchColumn();
        $pages=max(1,(int)ceil($total/75));
        $page=min($page,$pages);
        $st=$db->prepare('SELECT id,started_at,observed_at,interval_seconds,
                                 points_before,points_after,points_delta,
                                 luong_before,luong_after,luong_delta,
                                 boxes_before,boxes_after,farm_ms_before,farm_ms_after,source
                          FROM kp_farmer_monitor_changes WHERE '.$where.'
                          ORDER BY id DESC LIMIT ? OFFSET ?');
        $cursor=1;
        foreach($params as $v)$st->bindValue($cursor++,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
        $st->bindValue($cursor++,75,PDO::PARAM_INT);
        $st->bindValue($cursor,($page-1)*75,PDO::PARAM_INT);
        $st->execute();
        $rows=$st->fetchAll(PDO::FETCH_ASSOC);

        $st=$db->prepare("SELECT DATE_FORMAT(observed_at,'%Y-%m-%d %H:00:00') AS hour_bucket,
                         SUM(CASE WHEN luong_delta>0 THEN luong_delta ELSE 0 END) AS luong_up,
                         SUM(CASE WHEN points_delta>0 THEN points_delta ELSE 0 END) AS points_up
                         FROM kp_farmer_monitor_changes
                         WHERE char_id=? AND observed_at>=? GROUP BY hour_bucket");
        $st->execute([(int)$id,date('Y-m-d H:i:s',$now-24*3600)]);
        foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r)
            $hourly[$r['hour_bucket']]=['luong'=>(int)$r['luong_up'],'points'=>(int)$r['points_up']];
    }catch(Throwable $e){
        kp_log('farmer character history unavailable',$e);
        $historyError=true;
    }
}
$hours=[];
$maxValue=0;
for($i=23;$i>=0;$i--){
    $stamp=strtotime(date('Y-m-d H:00:00',strtotime('-'.$i.' hours')));
    $key=date('Y-m-d H:00:00',$stamp);
    $value=(int)($hourly[$key]['luong']??0);
    $maxValue=max($maxValue,$value);
    $hours[]=['hour'=>date('H:00',$stamp),'value'=>$value];
}
$fmt=function($value): string {
    return $value===null?'—':number_format((int)$value,0,',','.');
};
$signed=function($value)use($fmt): string {
    return $value===null?'—':((int)$value>0?'+':'').$fmt($value);
};
$url=function($pageNumber)use($id,$mode,$limit): string {
    return '/quan-tri/diem-nong-dan/lich-su?'.http_build_query(
        ['id'=>$id,'mode'=>$mode,'alert'=>$limit,'page'=>$pageNumber],
        '','&',PHP_QUERY_RFC3986
    );
};
?><!doctype html>
<html lang="vi"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Lịch sử Điểm nông dân · <?= kp_e($char['charname']) ?></title>
<link rel="stylesheet" href="/assets/css/admin-home.css?v=1">
<link rel="stylesheet" href="/assets/css/admin-nav.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin-nav.css') ?>">
<link rel="stylesheet" href="/assets/css/admin-farmer.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin-farmer.css') ?>">
<link rel="stylesheet" href="/assets/css/site-responsive.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/site-responsive.css') ?>">
</head><body>
<header class="topbar">
  <a class="brand" href="/quan-tri/diem-nong-dan"><span class="brand-icon">✧</span><span><small>MONITOR / LỊCH SỬ</small><strong><?= kp_e($char['charname']) ?></strong></span></a>
  <div class="top-actions"><span class="secure"><i></i> Chỉ đọc</span><span class="user"><?= kp_e($_SESSION['kp_admin_username']??'admin') ?></span></div>
</header>
<?php kp_admin_nav('farmer','admin'); ?>
<main class="shell farmer-shell">
  <section class="welcome farmer-hero">
    <div><div class="eyebrow">GIÁM SÁT NHÂN VẬT / #<?= (int)$char['id'] ?></div>
      <h1>Lịch sử <span><?= kp_e($char['charname']) ?></span></h1>
      <p>Tài khoản <?= kp_e($char['username']?:('ID '.$char['userId'])) ?> · Cấp <?= (int)$char['lastLv'] ?> · Dữ liệu quan sát từ database game, không phải nhật ký hành động trực tiếp của người chơi.</p>
    </div><a class="farmer-reset" href="/quan-tri/diem-nong-dan">← Tất cả nhân vật</a>
  </section>
  <?php if(!$ready): ?>
    <div class="alert" role="status">Chưa cài bảng lịch sử. Chạy <code>php tools/farmer-monitor.php install</code> và đặt lịch quét 5 phút/lần trên VPS.</div>
  <?php elseif($historyError): ?>
    <div class="alert" role="alert">Chưa đọc được dữ liệu giám sát. Kiểm tra PHP error log.</div>
  <?php elseif(!$monitorInfo): ?>
    <div class="alert" role="status">Chưa có mốc theo dõi cho nhân vật này. Chạy bộ thu thập ít nhất một lần.</div>
  <?php endif; ?>
  <section class="metrics farmer-history-metrics" aria-label="Các chỉ số nhân vật">
    <article class="metric"><div class="metric-top">Điểm nông dân hiện tại</div><strong><?= $fmt($farmer) ?></strong><small>Đọc trực tiếp từ dữ liệu game</small></article>
    <article class="metric"><div class="metric-top">Lượng hiện tại</div><strong><?= $fmt($char['luong']) ?></strong><small>Số dư DB của nhân vật</small></article>
    <article class="metric"><div class="metric-top">Điểm tăng trong 1 giờ</div><strong><?= $stats?$fmt($stats['points_1h']):'—' ?></strong><small>Phần tăng dương giữa các mẫu</small></article>
    <article class="metric"><div class="metric-top">Lượng up TB/1 giờ</div><strong><?= $stats&&$stats['avg_luong_hour']!==null?number_format($stats['avg_luong_hour'],1,',','.'):'—' ?></strong><small>Trung bình thời gian theo dõi trong 24 giờ gần nhất</small></article>
  </section>
  <section class="panel farmer-panel" aria-labelledby="farmer-chart-title">
    <div class="panel-heading"><div><div class="eyebrow">LƯỢNG UP / 24 GIỜ</div><h2 id="farmer-chart-title">Biến động Lượng theo từng giờ</h2>
      <p>Lượng up 60 phút: <strong class="farmer-history-rate"><?= $stats?$fmt($stats['luong_1h']):'—' ?></strong>.
      Mẫu mới nhất: <?= $monitorInfo?kp_e($monitorInfo['sampled_at']):'Chưa có' ?><?= $stats&&!$stats['fresh']?' · Dữ liệu đã cũ':'' ?>.</p></div><span class="live">Mẫu số dư DB</span></div>
    <div class="farmer-chart-box">
      <svg class="farmer-chart" viewBox="0 0 780 212" role="img" aria-label="Biểu đồ cột Lượng tăng dương trong 24 giờ gần nhất">
        <line class="farmer-grid" x1="18" y1="177" x2="768" y2="177"></line>
        <line class="farmer-grid" x1="18" y1="97" x2="768" y2="97"></line>
        <?php foreach($hours as $n=>$bucket):
          $height=$maxValue>0?(int)round($bucket['value']*148/$maxValue):0;
          $x=29+$n*30;
          $y=177-$height;
        ?>
          <rect class="farmer-bar" x="<?= $x ?>" y="<?= $y ?>" width="20" height="<?= $height ?>">
            <title><?= kp_e($bucket['hour']) ?> — <?= $fmt($bucket['value']) ?> Lượng tăng</title>
          </rect>
          <?php if($n%4===0): ?><text class="farmer-axis-label" x="<?= $x ?>" y="196"><?= kp_e($bucket['hour']) ?></text><?php endif; ?>
        <?php endforeach; ?>
      </svg>
    </div>
    <p class="farmer-caption">Mỗi cột là tổng phần tăng dương của số dư Lượng giữa các lần quét trong giờ đó. Không thể tách phần đã tiêu hoặc Lượng đã nhận rồi dùng ngay trong cùng một chu kỳ 5 phút.</p>
  </section>
  <section class="panel farmer-panel" aria-labelledby="farmer-changes-title">
    <div class="panel-heading"><div><div class="eyebrow">LỊCH SỬ / 7 NGÀY</div><h2 id="farmer-changes-title">Số dư trước và sau</h2>
      <p>Khoảng cách giữa các mốc lớn hơn 15 phút được đánh dấu gián đoạn; cảnh báo tăng nhanh chỉ là tín hiệu rà soát.</p></div><span class="live"><?= $fmt($total) ?> lần biến động</span>
    </div>
    <form class="farmer-form" method="get" action="/quan-tri/diem-nong-dan/lich-su">
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <label class="farmer-field"><span>Lọc sự kiện</span><select name="mode">
      <?php foreach(['all'=>'Tất cả thay đổi','points'=>'Có biến động điểm','luong'=>'Có biến động Lượng','up'=>'Chỉ tăng điểm/Lượng','alerts'=>'Tăng điểm quá nhanh'] as $key=>$label): ?>
        <option value="<?= kp_e($key) ?>"<?= $mode===$key?' selected':'' ?>><?= kp_e($label) ?></option>
      <?php endforeach; ?>
      </select></label>
      <label class="farmer-field"><span>Ngưỡng cảnh báo, điểm/giờ</span><input type="number" min="100" max="1000000" name="alert" value="<?= $limit ?>"></label>
      <button class="farmer-apply" type="submit">Lọc lịch sử ↗</button>
    </form>
    <div class="table-scroll farmer-table-scroll farmer-history-scroll">
      <table class="farmer-table farmer-history-table">
        <thead><tr><th>Thời gian ghi nhận</th><th>Khoảng cách</th><th>Điểm trước → sau</th><th>Điểm +/-</th><th>Lượng trước → sau</th><th>Lượng +/-</th><th>Hộp/ngày</th><th>Nhận định</th></tr></thead>
        <tbody>
        <?php foreach($rows as $change):
          $seconds=(int)$change['interval_seconds'];
          $gap=$seconds>900;
          $gain=(int)($change['points_delta']??0);
          $suspicious=!$gap&&$seconds>0&&$gain>0&&($gain*3600)>($limit*$seconds);
          $notes=$gap?'Mất mẫu / lưu chậm':($suspicious?'Tăng điểm nhanh':'Biến động quan sát');
          $pointDelta=$change['points_delta']===null?null:(int)$change['points_delta'];
          $luongDelta=(int)$change['luong_delta'];
        ?>
          <tr class="<?= $suspicious?'suspicious':'' ?>">
            <td><?= kp_e($change['observed_at']) ?></td>
            <td class="<?= $gap?'gap':'' ?>"><?= number_format($seconds/60,1,',','.') ?> phút</td>
            <td><?= $fmt($change['points_before']) ?> → <?= $fmt($change['points_after']) ?></td>
            <td class="<?= $pointDelta===null?'':($pointDelta>0?'positive':($pointDelta<0?'negative':'')) ?>"><?= $signed($pointDelta) ?></td>
            <td><?= $fmt($change['luong_before']) ?> → <?= $fmt($change['luong_after']) ?></td>
            <td class="<?= $luongDelta>0?'positive':($luongDelta<0?'negative':'') ?>"><?= $signed($luongDelta) ?></td>
            <td><?= $fmt($change['boxes_after']) ?></td>
            <td><span class="farmer-pill <?= $gap?'farmer-warning':($suspicious?'farmer-bad':'farmer-neutral') ?>"><?= kp_e($notes) ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if(!$rows): ?><tr><td colspan="8" class="empty"><?= !$ready?'Chưa bật lưu lịch sử.':'Không có biến động phù hợp để hiển thị.' ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <nav class="farmer-pagination" aria-label="Trang lịch sử">
      <?php if($page>1): ?><a href="<?= kp_e($url($page-1)) ?>">← Trang trước</a><?php endif; ?>
      <span>Trang <?= $page ?> / <?= $pages ?></span>
      <?php if($page<$pages): ?><a href="<?= kp_e($url($page+1)) ?>">Trang sau →</a><?php endif; ?>
    </nav>
    <p class="farmer-caption">Nguồn biến động: <strong>snapshot database</strong>, chưa xác định hành động tạo ra điểm/Lượng. Mốc đầu tiên là baseline, không ghi thay đổi giả. Một điểm tăng bất thường cần đối chiếu thêm log Java và tình trạng lưu dữ liệu game trước khi kết luận.</p>
  </section>
  <footer><span>KPAH · FARMER MONITORING</span><a href="/quan-tri/diem-nong-dan">← Trở về danh sách</a></footer>
</main></body></html>
