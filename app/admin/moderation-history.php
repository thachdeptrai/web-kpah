<?php
/**
 * Read-only history of admin-initiated game account and character ban changes.
 * Does not claim to include edits performed directly in Navicat / Java server.
 */
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/admin_security.php';
require_once __DIR__.'/../includes/admin_nav.php';
require_once __DIR__.'/../includes/game_account_admin.php';
$admin=kp_admin_require('/quan-tri/dang-nhap',['admin']);
kp_send_security_headers(true);
header('Content-Type: text/html; charset=utf-8');

$type=isset($_GET['type']) && is_string($_GET['type'])?$_GET['type']:'all';
$action=isset($_GET['action']) && is_string($_GET['action'])?$_GET['action']:'all';
$status=isset($_GET['result']) && is_string($_GET['result'])?$_GET['result']:'all';
$q=isset($_GET['q']) && is_string($_GET['q'])?trim($_GET['q']):'';
$q=mb_strlen($q,'UTF-8')>60?mb_substr($q,0,60,'UTF-8'):$q;
if(!in_array($type,['all','account','character'],true))$type='all';
if(!in_array($action,['all','ban','unban'],true))$action='all';
if(!in_array($status,['all','applied','pending','conflict'],true))$status='all';
$page=filter_var($_GET['page']??1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]]);
if(!$page)$page=1;
$perPage=30;
$rows=[];$total=0;$pages=1;$summary=['all'=>0,'bans'=>0,'unbans'=>0,'today'=>0];
$error='';$ready=false;
try{
    $db=kp_db();
    $ready=kp_game_moderation_log_available($db);
    if($ready){
        $summary=$db->query("SELECT COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN new_ban=1 AND result='applied' THEN 1 ELSE 0 END),0) AS bans,
            COALESCE(SUM(CASE WHEN new_ban=0 AND result='applied' THEN 1 ELSE 0 END),0) AS unbans,
            COALESCE(SUM(CASE WHEN created_at>=CURDATE() THEN 1 ELSE 0 END),0) AS today
            FROM kp_game_moderation_log")->fetch(PDO::FETCH_ASSOC);
        $where=[];$params=[];
        if($type!=='all'){$where[]='target_type=?';$params[]=$type;}
        if($action==='ban'){$where[]='new_ban=1';}
        elseif($action==='unban'){$where[]='new_ban=0';}
        if($status!=='all'){$where[]='result=?';$params[]=$status;}
        if($q!==''){
            $search='%'.str_replace(['!','%','_'],['!!','!%','!_'],$q).'%';
            $where[]="(target_name LIKE ? ESCAPE '!' OR admin_name LIKE ? ESCAPE '!'"
                .(ctype_digit($q) && strlen($q)<11?' OR target_id=? OR owner_id=?':'').')';
            $params[]=$search;$params[]=$search;
            if(ctype_digit($q) && strlen($q)<11){$params[]=(int)$q;$params[]=(int)$q;}
        }
        $sql=$where?' WHERE '.implode(' AND ',$where):'';
        $st=$db->prepare('SELECT COUNT(*) FROM kp_game_moderation_log'.$sql);
        $st->execute($params);
        $total=(int)$st->fetchColumn();
        $pages=max(1,(int)ceil($total/$perPage));
        $page=min($page,$pages);
        $st=$db->prepare('SELECT id,admin_name,target_type,target_id,target_name,owner_id,
                 old_ban,new_ban,reason,result,created_at,completed_at
                 FROM kp_game_moderation_log'.$sql.' ORDER BY id DESC LIMIT ? OFFSET ?');
        $i=1;
        foreach($params as $value)$st->bindValue($i++,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR);
        $st->bindValue($i++,$perPage,PDO::PARAM_INT);
        $st->bindValue($i,($page-1)*$perPage,PDO::PARAM_INT);
        $st->execute();
        $rows=$st->fetchAll(PDO::FETCH_ASSOC);
    }
}catch(Throwable $e){
    kp_log('moderation global history listing failed',$e);
    $error='Không thể tải lịch sử quản trị. Vui lòng kiểm tra kết nối database.';
}
$url=function($pageValue)use($q,$type,$action,$status): string {
    return '/quan-tri/lich-su-thao-tac?'.http_build_query([
        'q'=>$q,'type'=>$type,'action'=>$action,'result'=>$status,'page'=>$pageValue
    ],'','&',PHP_QUERY_RFC3986);
};
$fmt=function($value): string{return number_format((int)$value,0,',','.');};
?><!doctype html><html lang="vi"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" href="/assets/img/favicon.webp" type="image/webp">
<title>Lịch sử thao tác · NGHIỆN KPAH Admin</title>
<link rel="stylesheet" href="/assets/css/admin-home.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin-home.css') ?>">
<link rel="stylesheet" href="/assets/css/admin-nav.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin-nav.css') ?>">
<link rel="stylesheet" href="/assets/css/admin-game-accounts.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin-game-accounts.css') ?>">
<link rel="stylesheet" href="/assets/css/admin-moderation-history.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin-moderation-history.css') ?>">
<link rel="stylesheet" href="/assets/css/site-responsive.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/site-responsive.css') ?>">
<link rel="stylesheet" href="/assets/css/admin-theme.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin-theme.css') ?>">
<link rel="stylesheet" href="/assets/css/admin-history-fixes.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin-history-fixes.css') ?>">
</head><body class="kp-admin-redesign">
<header class="topbar kp-admin-topbar">
 <a class="brand" href="/quan-tri"><span class="brand-icon"><img src="/assets/img/emblem.webp" alt="" width="44" height="44"></span><span><small>NGHIỆN KPAH · QUẢN TRỊ</small><strong>Lịch sử thao tác</strong></span></a>
 <div class="top-actions"><span class="secure"><i></i> Kết nối bảo mật</span><span class="user"><?= kp_e($admin['username']) ?></span><a class="logout" href="/quan-tri/dang-xuat">Đăng xuất ↗</a></div>
</header>
<?php kp_admin_nav('mod-history','admin'); ?>
<main class="shell gam-shell">
  <section class="welcome gam-welcome">
    <div><div class="eyebrow">ACTIVITY LOG / QUẢN TRỊ GAME</div><h1>Lịch sử <span>ban &amp; mở ban.</span></h1>
      <p>Theo dõi thao tác sửa trạng thái tài khoản và nhân vật thực hiện trên website: người thao tác, trạng thái trước/sau và lý do.</p>
    </div><a href="/quan-tri/tai-khoan-game" class="gam-history-back">Quản lý người chơi ↗</a>
  </section>
  <?php if($error): ?><div class="gam-alert danger" role="alert"><?= kp_e($error) ?></div><?php endif; ?>
  <?php if(!$ready&&!$error): ?><div class="gam-alert warning" role="status">Chưa có lịch sử trên web. Nhật ký sẽ tự tạo khi admin ban/mở ban lần đầu tiên.</div><?php endif; ?>
  <section class="metrics gam-metrics gam-history-metrics">
   <article class="metric"><div class="metric-top">Tất cả lần xử lý</div><strong><?= $fmt($summary['total']??0) ?></strong><small>Từ lúc bắt đầu ghi nhật ký</small></article>
   <article class="metric"><div class="metric-top">Lượt ban thành công</div><strong><?= $fmt($summary['bans']??0) ?></strong><small>Account và nhân vật</small></article>
   <article class="metric"><div class="metric-top">Lượt mở ban thành công</div><strong><?= $fmt($summary['unbans']??0) ?></strong><small>Kể cả chọn mở ban hàng loạt</small></article>
   <article class="metric"><div class="metric-top">Hoạt động trong ngày</div><strong><?= $fmt($summary['today']??0) ?></strong><small>Ghi nhận theo múi giờ máy chủ</small></article>
  </section>
  <section class="gam-panel gam-history-filter">
    <div class="gam-section-head"><div><span class="eyebrow">BỘ LỌC</span><h2>Tìm theo người sửa hoặc đối tượng</h2></div><small><?= $fmt($total) ?> kết quả</small></div>
    <form action="/quan-tri/lich-su-thao-tac" method="get" class="gam-history-form">
       <label><span>Admin / tài khoản / nhân vật / ID</span><input type="search" name="q" maxlength="60" value="<?= kp_e($q) ?>" placeholder="Nhập tên hoặc ID để tra cứu..."></label>
       <label><span>Đối tượng</span><select name="type">
         <?php foreach(['all'=>'Tất cả','account'=>'Tài khoản','character'=>'Nhân vật'] as $v=>$label): ?><option value="<?= kp_e($v) ?>"<?= $type===$v?' selected':'' ?>><?= kp_e($label) ?></option><?php endforeach; ?>
       </select></label>
       <label><span>Thao tác</span><select name="action">
         <?php foreach(['all'=>'Tất cả','ban'=>'Ban','unban'=>'Mở ban'] as $v=>$label): ?><option value="<?= kp_e($v) ?>"<?= $action===$v?' selected':'' ?>><?= kp_e($label) ?></option><?php endforeach; ?>
       </select></label>
       <label><span>Kết quả</span><select name="result">
         <?php foreach(['all'=>'Tất cả','applied'=>'Thành công','pending'=>'Chờ xác nhận','conflict'=>'Xung đột'] as $v=>$label): ?><option value="<?= kp_e($v) ?>"<?= $status===$v?' selected':'' ?>><?= kp_e($label) ?></option><?php endforeach; ?>
       </select></label>
       <button type="submit">Áp dụng lọc ↗</button>
       <a class="gam-history-clear" href="/quan-tri/lich-su-thao-tac">Xóa lọc</a>
    </form>
  </section>
  <section class="gam-panel gam-history-panel">
   <div class="gam-section-head"><div><span class="eyebrow">NHẬT KÝ / MỚI NHẤT</span><h2>Chi tiết chỉnh sửa trạng thái</h2></div><small>Trang <?= $page ?> / <?= $pages ?></small></div>
   <?php if(!$rows): ?><p class="gam-muted">Chưa có thao tác phù hợp bộ lọc.</p><?php else: ?>
     <div class="gam-audit-scroll"><table class="gam-audit gam-history-table">
     <thead><tr><th>Thời điểm</th><th>Admin</th><th>Loại</th><th>Tài khoản / nhân vật</th><th>Trước → Sau</th><th>Lý do</th><th>Kết quả</th><th>Chi tiết</th></tr></thead>
     <tbody>
     <?php foreach($rows as $r):
         $to=(int)$r['new_ban'];$applied=$r['result']==='applied';
         $name=$r['target_type']==='character'?'Nhân vật':'Tài khoản';
     ?>
       <tr><td data-label="Thời gian"><time datetime="<?= kp_e($r['created_at']) ?>"><?= kp_e($r['created_at']) ?></time></td>
        <td data-label="Admin" class="gam-history-admin"><?= kp_e($r['admin_name']) ?></td>
        <td data-label="Loại" ><span class="gam-history-type"><?= kp_e($name) ?></span></td>
        <td data-label="Đối tượng"><strong><?= kp_e($r['target_name']) ?></strong><small>#<?= (int)$r['target_id'] ?></small></td>
        <td data-label="Trước → Sau"><span class="gam-history-transition"><?= (int)$r['old_ban'] ?> → <?= $to ?></span><span class="gam-pill <?= $to===0?'ok':'banned' ?>"><?= $to===0?'Mở ban':'Ban' ?></span></td>
        <td data-label="Lý do" class="gam-history-reason"><?= kp_e($r['reason']) ?></td>
        <td data-label="Kết quả"><span class="gam-history-status <?= $applied?'ok':'special' ?>"><?= $applied?'Thành công':($r['result']==='pending'?'Chờ xử lý':'Xung đột') ?></span></td>
        <td data-label="Hồ sơ"><a class="gam-history-link" href="/quan-tri/tai-khoan-game?id=<?= (int)$r['owner_id'] ?>">Xem hồ sơ ↗</a></td>
       </tr>
     <?php endforeach; ?>
     </tbody></table></div>
   <?php endif; ?>
   <nav class="gam-pages" aria-label="Các trang lịch sử">
    <?php if($page>1): ?><a href="<?= kp_e($url($page-1)) ?>">← Trang trước</a><?php endif; ?>
    <span><?= $page ?> / <?= $pages ?></span>
    <?php if($page<$pages): ?><a href="<?= kp_e($url($page+1)) ?>">Trang sau →</a><?php endif; ?>
   </nav>
   <p class="gam-hint">Lịch sử này ghi những lần sửa cờ ban của tài khoản hoặc nhân vật từ website kể từ khi bật chức năng. Các thao tác sửa trực tiếp trong database hoặc nguồn khác không được ghi tự động vào đây. Nhật ký là chỉ đọc.</p>
  </section>
</main></body></html>
