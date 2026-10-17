<?php
/**
 * Admin-only KPAH game account and character moderation.
 * Distinct ban columns: team_user.ban vs tob_char.ban. Never change del.
 */
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/admin_security.php';
require_once __DIR__.'/../includes/admin_nav.php';
require_once __DIR__.'/../includes/farmer_points.php';
require_once __DIR__.'/../includes/game_account_admin.php';
$admin=kp_admin_require('/quan-tri/dang-nhap',['admin']);
kp_send_security_headers(true);
header('Content-Type: text/html; charset=utf-8');
$format=function($v): string{return $v===null?'—':number_format((int)$v,0,',','.');};
$validId=function($v): int {
    $id=filter_var($v,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]]);
    return $id===false?0:(int)$id;
};
$q=isset($_GET['q']) && is_string($_GET['q'])?trim($_GET['q']):'';
$q=substr($q,0,50);
$state=isset($_GET['state']) && is_string($_GET['state'])?$_GET['state']:'all';
if(!in_array($state,['all','normal','banned','special','char_banned'],true))$state='all';
$page=max(1,min(100000,(int)($_GET['page']??1)));
$perPage=25;
$selected=$validId($_GET['id']??null);
$error='';$notice=(string)($_SESSION['kp_game_mod_notice']??'');
unset($_SESSION['kp_game_mod_notice']);
$dbError=false;
try {
    $db=kp_db();
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
        try {
            if(!kp_admin_csrf_valid($_POST['csrf']??null)) {
                throw new RuntimeException('Phiên xác nhận đã hết hạn. Tải lại trang rồi thực hiện.');
            }
            list($allowed)=kp_admin_limit('game-account-moderation',(string)$admin['id'],30,3600);
            if(!$allowed)throw new RuntimeException('Đã thao tác quá nhiều lần trong giờ. Hãy thử lại sau.');
            $type=isset($_POST['target_type']) && is_string($_POST['target_type'])?$_POST['target_type']:'';
            $direction=isset($_POST['direction']) && is_string($_POST['direction'])?$_POST['direction']:'';
            $targetId=$validId($_POST['target_id']??null);
            $reason=isset($_POST['reason']) && is_string($_POST['reason'])?$_POST['reason']:'';
            $confirm=isset($_POST['confirm_name']) && is_string($_POST['confirm_name'])?$_POST['confirm_name']:'';
            if($direction==='bulk_unban') {
                $fingerprint=isset($_POST['fingerprint']) && is_string($_POST['fingerprint'])
                    ?$_POST['fingerprint']:'';
                $selectedCsv=isset($_POST['selected_ids_csv']) && is_string($_POST['selected_ids_csv'])
                    ? $_POST['selected_ids_csv'] : '';
                // One compact CSV field avoids PHP max_input_vars truncating large lists.
                if(strlen($selectedCsv)>1048576)throw new RuntimeException('Danh sách chọn quá lớn.');
                $selectedIds=trim($selectedCsv)!==''
                    ? explode(',', $selectedCsv)
                    : (isset($_POST['selected_ids']) && is_array($_POST['selected_ids'])
                        ? $_POST['selected_ids'] : []);
                $result=kp_game_moderation_bulk_unban($db,$admin,$type,$reason,$confirm,$fingerprint,$selectedIds);
                $label=$type==='account'?'tài khoản':'nhân vật';
                $notice='Đã mở ban '.(int)$result['changed'].' / '.(int)$result['total'].' '.$label.' được chọn; giữ nguyên ban '.(int)$result['excluded'].' '.$label.' không chọn.';
                if($result['failed']>0) {
                    $notice.=' Có '.(int)$result['failed'].' lỗi chưa xử lý; xem PHP error log, sau đó tải lại.';
                }
                kp_admin_log_event('game_bulk_'.$type.'_unban',(int)$admin['id']);
            } else {
                $notice=kp_game_moderation_change($db,$admin,$type,$targetId,$direction,$reason,$confirm);
                kp_admin_log_event('game_'.$type.'_'.$direction,(int)$admin['id']);
            }
            $_SESSION['kp_game_mod_notice']=$notice;
            $redirectId=$validId($_POST['account_id']??null);
            header('Location: /quan-tri/tai-khoan-game'.($redirectId?'?id='.$redirectId:''),true,303);
            exit;
        }catch(RuntimeException $e){$error=$e->getMessage();}
        catch(Throwable $e){kp_log('game admin moderation write failed',$e);$error='Không thể cập nhật trạng thái. Kiểm tra quyền ghi DB và bảng log quản trị.';}
    }
} catch(Throwable $e) {
    kp_log('game admin database connection failed',$e);
    $dbError=true;
    $error='Không kết nối được database game.';
}

$totals=['accounts'=>0,'banned_accounts'=>0,'banned_chars'=>0];
$users=[];$account=null;$chars=[];$audit=[];$pages=1;$matchCount=0;$protected=false;
$bulkTargets=['account'=>[],'character'=>[]];
if(!$dbError){
    try{
        $totals['accounts']=(int)$db->query('SELECT COUNT(*) FROM team_user')->fetchColumn();
        $totals['banned_accounts']=(int)$db->query('SELECT COUNT(*) FROM team_user WHERE ban=1')->fetchColumn();
        $totals['banned_chars']=(int)$db->query('SELECT COUNT(*) FROM tob_char WHERE ban=1')->fetchColumn();
        $bulkTargets['account']=kp_game_moderation_bulk_targets($db,'account');
        $bulkTargets['character']=kp_game_moderation_bulk_targets($db,'character');
        $filters=[];$args=[];
        if($q!==''){
            $term='%'.str_replace(['!','%','_'],['!!','!%','!_'],$q).'%';
            $filters[]="(u.username LIKE :username ESCAPE '!'
              OR EXISTS (SELECT 1 FROM tob_char c2 WHERE c2.userId=u.id AND c2.charname LIKE :charname ESCAPE '!')
              OR u.id=:id_match)";
            $args[':username']=$term;$args[':charname']=$term;
            $args[':id_match']=ctype_digit($q)?min(2147483647,(int)$q):0;
        }
        if($state==='normal')$filters[]='u.ban=0';
        elseif($state==='banned')$filters[]='u.ban=1';
        elseif($state==='special')$filters[]='u.ban NOT IN (0,1)';
        elseif($state==='char_banned')$filters[]='EXISTS (SELECT 1 FROM tob_char cx WHERE cx.userId=u.id AND cx.ban=1)';
        $where=$filters?' WHERE '.implode(' AND ',$filters):'';
        $count=$db->prepare('SELECT COUNT(*) FROM team_user u'.$where);
        foreach($args as $key=>$value)$count->bindValue($key,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR);
        $count->execute();
        $matchCount=(int)$count->fetchColumn();
        $pages=max(1,(int)ceil($matchCount/$perPage));
        $page=min($page,$pages);
        $offset=($page-1)*$perPage;
        $sql="SELECT u.id,u.username,u.ban,u.regdate,
             (SELECT COUNT(*) FROM tob_char cc WHERE cc.userId=u.id) AS chars_total,
             (SELECT COUNT(*) FROM tob_char cb WHERE cb.userId=u.id AND cb.ban=1) AS chars_banned
             FROM team_user u $where ORDER BY u.id DESC LIMIT :limit OFFSET :offset";
        $st=$db->prepare($sql);
        foreach($args as $key=>$value)$st->bindValue($key,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR);
        $st->bindValue(':limit',$perPage,PDO::PARAM_INT);
        $st->bindValue(':offset',$offset,PDO::PARAM_INT);
        $st->execute();
        $users=$st->fetchAll(PDO::FETCH_ASSOC);
        if($selected>0){
            $st=$db->prepare('SELECT id,userid,username,ban,email,phone,regdate,vnd,tongnap,fromgame
                              FROM team_user WHERE id=? LIMIT 1');
            $st->execute([$selected]);
            $account=$st->fetch(PDO::FETCH_ASSOC)?:null;
            if($account){
                $st=$db->prepare('SELECT id,charname,userId,lastLv,xp,gold,luong,luonglock,
                    pInfo,ban,del,isAdmin,lastLog,idserver,totalTimePlay,map,`class`
                    FROM tob_char WHERE userId=? ORDER BY id DESC');
                $st->execute([$selected]);
                $chars=$st->fetchAll(PDO::FETCH_ASSOC);
                foreach($chars as $c){if((int)$c['isAdmin']!==0)$protected=true;}
                $audit=kp_game_moderation_history($db,$selected,60);
            }
        }
    }catch(Throwable $e){
        kp_log('game admin user / character listing failed',$e);
        $dbError=true;
        $error='Không truy vấn được danh sách tài khoản/nhân vật. Kiểm tra kết nối SQL.';
    }
}
$filterUrl=function(int $p)use($q,$state): string {
    return '/quan-tri/tai-khoan-game?'.http_build_query(['q'=>$q,'state'=>$state,'page'=>$p],'','&',PHP_QUERY_RFC3986);
};
$banLabel=function(int $ban,string $type='account'): string {
    if($ban===0)return 'Bình thường';
    if($ban===1)return 'Đang ban';
    return $type==='account'?'Trạng thái đặc biệt ('.$ban.')':'Trạng thái khác ('.$ban.')';
};
$banClass=function(int $ban): string {return $ban===0?'ok':($ban===1?'banned':'special');};
?><!doctype html>
<html lang="vi"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Quản lý tài khoản game & nhân vật · NGHIỆN KPAH</title>
<link rel="stylesheet" href="/assets/css/admin-home.css?v=1">
<link rel="stylesheet" href="/assets/css/admin-nav.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin-nav.css') ?>">
<link rel="stylesheet" href="/assets/css/site-responsive.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/site-responsive.css') ?>">
<link rel="stylesheet" href="/assets/css/admin-game-accounts.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin-game-accounts.css') ?>">
</head><body>
<header class="topbar">
<a class="brand" href="/quan-tri"><span class="brand-icon"><img src="/assets/img/emblem.webp" width="50" height="50" alt=""></span><span><small>NGHIỆN KPAH · QUẢN TRỊ</small><strong>Tài khoản & nhân vật</strong></span></a>
<div class="top-actions"><span class="secure"><i></i> CHỈ ADMIN</span><span class="user"><?= kp_e($admin['username']) ?></span><a class="logout" href="/quan-tri/dang-xuat">Đăng xuất ↗</a></div>
</header>
<?php kp_admin_nav('game-accounts','admin'); ?>
<main class="shell gam-shell">
 <section class="welcome gam-welcome">
  <div><div class="eyebrow">PLAYER MODERATION / KPAH</div><h1>Quản lý <span>người chơi.</span></h1>
  <p>Tài khoản và nhân vật có trạng thái ban riêng. Xem thông tin, xác minh nhân vật thuộc tài khoản và ghi nhận đầy đủ lịch sử xử lý.</p></div>
  <span class="gam-readonly">Độc lập · Có nhật ký</span>
 </section>
 <?php if($notice): ?><div class="gam-alert success" role="status"><?= kp_e($notice) ?></div><?php endif; ?>
 <?php if($error): ?><div class="gam-alert danger" role="alert"><?= kp_e($error) ?></div><?php endif; ?>
 <section class="metrics gam-metrics" aria-label="Tổng quan quản lý người chơi">
  <article class="metric"><div class="metric-top">Tài khoản game <span>♙</span></div><strong><?= $format($totals['accounts']) ?></strong><small>Tổng account trong database</small></article>
  <article class="metric"><div class="metric-top">Account đang ban <span>!</span></div><strong><?= $format($totals['banned_accounts']) ?></strong><small>team_user.ban = 1</small></article>
  <article class="metric"><div class="metric-top">Nhân vật đang ban <span>⚑</span></div><strong><?= $format($totals['banned_chars']) ?></strong><small>tob_char.ban = 1</small></article>
 </section>
 <section class="gam-panel gam-bulk-panel" aria-labelledby="gam-bulk-heading">
   <div class="gam-section-head"><div><span class="eyebrow">MỞ BAN HÀNG LOẠT / TOÀN SERVER</span><h2 id="gam-bulk-heading">Mở ban tất cả</h2></div><span class="gam-bulk-scope">Chỉ đối tượng đang ban = 1</span></div>
   <p class="gam-hint">Hai thao tác hoàn toàn độc lập. Mở ban tất cả tài khoản <strong>không</strong> mở ban nhân vật; mở ban tất cả nhân vật <strong>không</strong> mở ban tài khoản. Không sửa trạng thái đặc biệt hoặc nhân vật quản trị game. Lệnh áp dụng trên toàn server, không bị giới hạn bởi bộ lọc tìm kiếm.</p>
   <div class="gam-bulk-grid">
   <?php foreach(['account'=>['title'=>'Mở ban ALL ACC','label'=>'tài khoản','phrase'=>'MO BAN TAT CA TAI KHOAN'],
                   'character'=>['title'=>'Mở ban ALL PLAYER','label'=>'nhân vật','phrase'=>'MO BAN TAT CA NHAN VAT']] as $type=>$meta):
       $count=count($bulkTargets[$type]); ?>
    <div class="gam-bulk-item">
      <div class="gam-bulk-overview"><strong><?= kp_e($meta['title']) ?></strong><span><?= number_format($count,0,',','.') ?> <?= kp_e($meta['label']) ?> có thể mở ban</span></div>
      <?php if($count>0): ?>
      <details class="gam-action-box gam-unlock">
        <summary><?= kp_e($meta['title']) ?> <span aria-hidden="true">⌄</span></summary>
        <form method="post" action="/quan-tri/tai-khoan-game" class="gam-mod-form gam-bulk-form" data-action-label="<?= kp_e($meta['title']) ?>">
          <input type="hidden" name="csrf" value="<?= kp_e(kp_csrf_token()) ?>">
          <input type="hidden" name="target_type" value="<?= kp_e($type) ?>">
          <input type="hidden" name="direction" value="bulk_unban">
          <input type="hidden" name="fingerprint" value="<?= kp_e(kp_game_moderation_bulk_fingerprint($bulkTargets[$type])) ?>">
          <input type="hidden" name="selected_ids_csv" value="" data-selected-csv>
          <fieldset class="gam-bulk-pick">
            <legend>Chọn <?= kp_e($meta['label']) ?> để mở ban</legend>
            <p class="gam-hint">Chỉ các mục được tích mới mở ban. Bỏ tích để <strong>giữ nguyên ban</strong> cho trường hợp đặc biệt. Mặc định chưa chọn ai.</p>
            <div class="gam-bulk-tools">
              <input type="search" class="gam-bulk-search" data-bulk-search placeholder="Lọc tên / ID..." aria-label="Lọc danh sách <?= kp_e($meta['label']) ?>">
              <button type="button" data-bulk-select="all">Chọn tất cả hiển thị</button>
              <button type="button" data-bulk-select="none">Bỏ chọn tất cả</button>
              <output class="gam-bulk-count" data-bulk-count>Đã chọn 0 / <?= $count ?></output>
            </div>
            <div class="gam-bulk-target-list">
              <?php foreach($bulkTargets[$type] as $target):
                $label=(string)$target['target_name'];
                $owner=(string)($target['owner_name']??'');
                $searchText=$label.' '.$owner.' '.(int)$target['id'];
              ?>
              <label class="gam-bulk-target" data-bulk-item data-search="<?= kp_e(strtolower($searchText)) ?>">
                <input type="checkbox" name="selected_ids[]" value="<?= (int)$target['id'] ?>" data-bulk-checkbox>
                <span><strong><?= kp_e($label) ?></strong>
                  <small>#<?= (int)$target['id'] ?><?= $owner!==''?' · Tài khoản '.kp_e($owner):'' ?></small></span>
                <em>Giữ ban nếu bỏ chọn</em>
              </label>
              <?php endforeach; ?>
            </div>
          </fieldset>
          <label>Lý do mở ban hàng loạt<textarea name="reason" minlength="8" maxlength="250" rows="2" required placeholder="Ghi lý do cho tất cả đối tượng..."></textarea></label>
          <label>Nhập đúng câu xác nhận <code><?= kp_e($meta['phrase']) ?></code>
            <input type="text" name="confirm_name" maxlength="64" required autocomplete="off" data-exact-name="<?= kp_e($meta['phrase']) ?>" placeholder="<?= kp_e($meta['phrase']) ?>">
          </label>
          <button type="submit" class="safe"><?= kp_e($meta['title']) ?> (<?= $count ?>)</button>
        </form>
      </details>
      <?php else: ?><p class="gam-muted">Không có <?= kp_e($meta['label']) ?> bị ban đủ điều kiện để mở.</p><?php endif; ?>
    </div>
   <?php endforeach; ?>
   </div>
   <p class="gam-hint">Có thể chọn tất cả rồi bỏ tích những tài khoản/nhân vật đặc biệt cần giữ ban. Những trạng thái ban khác 1 và nhân vật GM bị loại khỏi danh sách. Danh sách được đối chiếu lại trước khi thực hiện; mỗi đối tượng được mở ban đều có nhật ký riêng.</p>
 </section>
 <section class="gam-grid">
  <div class="gam-left">
    <section class="gam-panel" aria-labelledby="gam-search-heading">
      <div class="gam-section-head"><div><span class="eyebrow">BỘ LỌC / TÀI KHOẢN</span><h2 id="gam-search-heading">Tra cứu tài khoản</h2></div></div>
      <form action="/quan-tri/tai-khoan-game" method="get" class="gam-search">
       <label><span>Tên account / tên nhân vật / ID</span><input type="search" name="q" maxlength="50" value="<?= kp_e($q) ?>" placeholder="Nhập tài khoản, tên nhân vật..."></label>
       <label><span>Trạng thái</span><select name="state">
        <?php foreach(['all'=>'Tất cả','normal'=>'Account bình thường','banned'=>'Account bị ban','special'=>'Trạng thái khác','char_banned'=>'Có nhân vật bị ban'] as $value=>$title): ?>
        <option value="<?= kp_e($value) ?>"<?= $state===$value?' selected':'' ?>><?= kp_e($title) ?></option>
        <?php endforeach; ?>
       </select></label>
       <div class="gam-search-buttons"><button type="submit">Tìm kiếm ↗</button><a href="/quan-tri/tai-khoan-game">Xóa lọc</a></div>
      </form>
    </section>
    <section class="gam-panel" aria-label="Danh sách tài khoản">
      <div class="gam-section-head"><div><span class="eyebrow">KẾT QUẢ</span><h2><?= $format($matchCount) ?> tài khoản</h2></div><small>Trang <?= $page ?> / <?= $pages ?></small></div>
      <?php if(!$users): ?><p class="gam-muted">Không có tài khoản phù hợp với bộ lọc.</p><?php endif; ?>
      <div class="gam-account-list">
      <?php foreach($users as $u):
        $uid=(int)$u['id'];$ban=(int)$u['ban'];
        $link='/quan-tri/tai-khoan-game?'.http_build_query(['id'=>$uid,'q'=>$q,'state'=>$state],'','&',PHP_QUERY_RFC3986);
      ?>
       <a href="<?= kp_e($link) ?>" class="gam-account-item<?= $selected===$uid?' selected':'' ?>"<?= $selected===$uid?' aria-current="true"':'' ?>>
        <span class="gam-avatar" aria-hidden="true"><?= kp_e(strtoupper(substr((string)$u['username'],0,1))) ?></span>
        <span class="gam-account-main"><strong><?= kp_e($u['username']??('ID '.$uid)) ?></strong><small>#<?= $uid ?> · <?= (int)$u['chars_total'] ?> nhân vật · <?= (int)$u['chars_banned'] ?> đang ban</small></span>
        <span class="gam-pill <?= $banClass($ban) ?>"><?= kp_e($banLabel($ban)) ?></span>
        <span class="gam-arrow">↗</span>
       </a>
      <?php endforeach; ?>
      </div>
      <nav class="gam-pages" aria-label="Chuyển trang">
        <?php if($page>1): ?><a href="<?= kp_e($filterUrl($page-1)) ?>">← Trước</a><?php endif; ?>
        <span><?= $page ?> / <?= $pages ?></span>
        <?php if($page<$pages): ?><a href="<?= kp_e($filterUrl($page+1)) ?>">Sau →</a><?php endif; ?>
      </nav>
    </section>
  </div>
  <div class="gam-right" id="chi-tiet">
   <?php if($account): $accountBan=(int)$account['ban']; ?>
    <section class="gam-panel gam-account-detail">
      <div class="gam-section-head"><div><span class="eyebrow">HỒ SƠ / ACCOUNT #<?= (int)$account['id'] ?></span><h2><?= kp_e($account['username']) ?></h2></div><span class="gam-pill <?= $banClass($accountBan) ?>"><?= kp_e($banLabel($accountBan)) ?></span></div>
      <div class="gam-facts">
       <div><span>ID tài khoản</span><strong>#<?= (int)$account['id'] ?></strong></div>
       <div><span>Game userid</span><strong><?= (int)$account['userid'] ?></strong></div>
       <div><span>Ngày đăng ký</span><strong><?= kp_e($account['regdate']??'—') ?></strong></div>
       <div><span>Nguồn đăng ký</span><strong><?= (int)$account['fromgame']?'Trong game':'Website / khác' ?></strong></div>
       <div><span>Email</span><strong><?= kp_e($account['email']?:'Chưa liên kết') ?></strong></div>
       <div><span>Số điện thoại</span><strong><?= kp_e($account['phone']?:'Chưa liên kết') ?></strong></div>
       <div><span>Ví VNĐ website</span><strong><?= $format($account['vnd']??0) ?></strong></div>
       <div><span>Tổng đã nạp</span><strong><?= $format($account['tongnap']??0) ?></strong></div>
      </div>
      <p class="gam-hint">Ban tài khoản chỉ đổi <code>team_user.ban</code>; không đổi trạng thái ban của nhân vật. Giá trị ban khác 0/1 sẽ không bị ghi đè.</p>
      <?php if($protected): ?>
       <div class="gam-alert warning">Tài khoản có nhân vật quản trị game. Không cho thao tác ban/mở ban từ web.</div>
      <?php elseif(!in_array($accountBan,[0,1],true)): ?>
       <div class="gam-alert warning">Account có trạng thái đặc biệt <?= $accountBan ?>. Không thể ban/mở ban bằng thao tác thường.</div>
      <?php else: ?>
       <?php $direction=$accountBan===0?'ban':'unban'; ?>
       <details class="gam-action-box <?= $direction==='ban'?'gam-lock':'gam-unlock' ?>">
         <summary><?= $direction==='ban'?'Ban tài khoản':'Mở ban tài khoản' ?> <span aria-hidden="true">⌄</span></summary>
         <form method="post" action="/quan-tri/tai-khoan-game" class="gam-mod-form"
           data-action-label="<?= kp_e($direction==='ban'?'BAN TÀI KHOẢN':'MỞ BAN TÀI KHOẢN') ?>">
           <input type="hidden" name="csrf" value="<?= kp_e(kp_csrf_token()) ?>">
           <input type="hidden" name="account_id" value="<?= (int)$account['id'] ?>">
           <input type="hidden" name="target_type" value="account">
           <input type="hidden" name="target_id" value="<?= (int)$account['id'] ?>">
           <input type="hidden" name="direction" value="<?= $direction ?>">
           <label>Lý do xử lý <textarea name="reason" rows="2" minlength="8" maxlength="250" required placeholder="Ghi rõ lý do, từ 8 ký tự..."></textarea></label>
           <label>Nhập đúng <strong><?= kp_e($account['username']) ?></strong> để xác nhận<input name="confirm_name" type="text" required maxlength="64" autocomplete="off" placeholder="<?= kp_e($account['username']) ?>" data-exact-name="<?= kp_e($account['username']) ?>"></label>
           <button type="submit" class="<?= $direction==='ban'?'danger':'safe' ?>"><?= $direction==='ban'?'Xác nhận ban tài khoản':'Xác nhận mở ban tài khoản' ?></button>
         </form>
       </details>
      <?php endif; ?>
    </section>
    <section class="gam-panel">
      <div class="gam-section-head"><div><span class="eyebrow">NHÂN VẬT THUỘC TÀI KHOẢN</span><h2><?= count($chars) ?> nhân vật</h2></div></div>
      <?php if(!$chars): ?><p class="gam-muted">Tài khoản này chưa có nhân vật.</p><?php endif; ?>
      <?php foreach($chars as $c):
        $charId=(int)$c['id'];$charBan=(int)$c['ban'];$isPrivileged=(int)$c['isAdmin']!==0;
        $farmer=kp_farmer_points($c['pInfo']??null);
      ?>
       <article class="gam-char">
        <div class="gam-char-header"><div><strong><?= kp_e($c['charname']) ?></strong><span>#<?= $charId ?> · Cấp <?= (int)$c['lastLv'] ?> · Phái <?= (int)$c['class'] ?></span></div><span class="gam-pill <?= $banClass($charBan) ?>"><?= kp_e($banLabel($charBan,'character')) ?></span></div>
        <div class="gam-char-stats">
          <div><span>Xu</span><strong><?= $format($c['gold']) ?></strong></div>
          <div><span>Lượng</span><strong><?= $format($c['luong']) ?></strong></div>
          <div><span>Lượng khóa</span><strong><?= $format($c['luonglock']) ?></strong></div>
          <div><span>Điểm nông dân</span><strong><?= $format($farmer) ?></strong></div>
        </div>
        <div class="gam-char-sub"><span>Lần đăng nhập: <?= kp_e($c['lastLog']) ?></span><span>Server <?= (int)$c['idserver'] ?> · Map <?= (int)$c['map'] ?></span></div>
        <div class="gam-char-sub"><span>Trạng thái xóa: <?= (int)$c['del']===1?'Bình thường':'Đang xóa / không hoạt động' ?></span>
         <?php if($isPrivileged): ?><span class="gam-privileged">Nhân vật quản trị game</span><?php endif; ?></div>
        <?php if($isPrivileged): ?>
         <p class="gam-hint">Không thể thao tác ban nhân vật quản trị game từ website.</p>
        <?php elseif(!in_array($charBan,[0,1],true)): ?>
         <p class="gam-hint">Nhân vật có trạng thái ban ngoài phạm vi 0/1; chỉ cho xem.</p>
        <?php else: ?>
         <?php $direction=$charBan===0?'ban':'unban'; ?>
         <details class="gam-action-box <?= $direction==='ban'?'gam-lock':'gam-unlock' ?>">
           <summary><?= $direction==='ban'?'Ban riêng nhân vật':'Mở ban riêng nhân vật' ?> <span aria-hidden="true">⌄</span></summary>
           <form method="post" action="/quan-tri/tai-khoan-game" class="gam-mod-form"
             data-action-label="<?= kp_e($direction==='ban'?'BAN NHÂN VẬT':'MỞ BAN NHÂN VẬT') ?>">
             <input type="hidden" name="csrf" value="<?= kp_e(kp_csrf_token()) ?>">
             <input type="hidden" name="account_id" value="<?= (int)$account['id'] ?>">
             <input type="hidden" name="target_type" value="character">
             <input type="hidden" name="target_id" value="<?= $charId ?>">
             <input type="hidden" name="direction" value="<?= $direction ?>">
             <label>Lý do xử lý <textarea name="reason" rows="2" minlength="8" maxlength="250" required placeholder="Ghi lý do rõ ràng..."></textarea></label>
             <label>Nhập chính xác <strong><?= kp_e($c['charname']) ?></strong> để xác nhận<input type="text" name="confirm_name" required maxlength="64" autocomplete="off" placeholder="<?= kp_e($c['charname']) ?>" data-exact-name="<?= kp_e($c['charname']) ?>"></label>
             <button type="submit" class="<?= $direction==='ban'?'danger':'safe' ?>"><?= $direction==='ban'?'Xác nhận ban nhân vật':'Xác nhận mở ban nhân vật' ?></button>
           </form>
         </details>
        <?php endif; ?>
       </article>
      <?php endforeach; ?>
    </section>
    <section class="gam-panel">
      <div class="gam-section-head"><div><span class="eyebrow">AUDIT TRAIL</span><h2>Lịch sử ban / mở ban</h2></div></div>
      <?php if(!$audit): ?><p class="gam-muted">Chưa có thao tác quản trị nào được ghi nhận trên web.</p><?php else: ?>
       <div class="gam-audit-scroll"><table class="gam-audit">
       <thead><tr><th>Thời gian</th><th>Admin</th><th>Đối tượng</th><th>Thay đổi</th><th>Lý do</th><th>Kết quả</th></tr></thead>
       <tbody>
       <?php foreach($audit as $a): ?>
        <tr><td><?= kp_e($a['created_at']) ?></td><td><?= kp_e($a['admin_name']) ?></td>
         <td><?= $a['target_type']==='account'?'Account':'Nhân vật' ?>: <?= kp_e($a['target_name']) ?> (#<?= (int)$a['target_id'] ?>)</td>
         <td><?= (int)$a['old_ban'] ?> → <?= (int)$a['new_ban'] ?></td>
         <td><?= kp_e($a['reason']) ?></td>
         <td><?= kp_e($a['result']) ?></td></tr>
       <?php endforeach; ?>
       </tbody></table></div>
      <?php endif; ?>
      <p class="gam-hint">Lịch sử này chỉ ghi thao tác từ website kể từ lần sử dụng đầu tiên; không thể khôi phục các thao tác ban thủ công trước đó.</p>
    </section>
   <?php elseif($selected): ?>
    <section class="gam-panel gam-empty"><h2>Không tìm thấy tài khoản</h2><p>ID không tồn tại hoặc đã bị xóa.</p><a href="/quan-tri/tai-khoan-game">Quay lại danh sách ↗</a></section>
   <?php else: ?>
    <section class="gam-panel gam-empty"><span class="gam-empty-icon">♙</span><h2>Chọn tài khoản để kiểm tra</h2><p>Tìm theo username, tên nhân vật hoặc ID, sau đó chọn một tài khoản để xem hồ sơ, các nhân vật liên kết và thao tác ban/mở ban.</p></section>
   <?php endif; ?>
  </div>
 </section>
 <footer class="gam-footer">KPAH · ADMIN MODERATION · <span>Không thay đổi trạng thái xóa nhân vật hoặc số dư tài nguyên</span></footer>
</main>
<script src="/assets/js/admin-game-accounts.js?v=<?= (int)@filemtime(KP_ROOT.'/assets/js/admin-game-accounts.js') ?>" defer></script>
</body></html>
