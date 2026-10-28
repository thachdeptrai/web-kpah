<?php
/**
 * Player account pages: one login guard and one navigation shell.
 * User-owned character details never leak into public leaderboards.
 */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }
require_once __DIR__.'/farmer_points.php';
require_once __DIR__.'/user_center.php';

function kp_player_account()
{
    $id = (int)($_SESSION['kp_user_id'] ?? 0);
    $last = (int)($_SESSION['kp_user_last_seen'] ?? 0);
    if ($id < 1 || $last < 1 || time()-$last > 1800) {
        unset($_SESSION['kp_user_id'],$_SESSION['kp_user_username'],$_SESSION['kp_user_last_seen']);
        header('Location: /dang-nhap?expired=1',true,302); exit;
    }
    try {
        $stmt=kp_db()->prepare(
            'SELECT id, userid, username, email, phone, regdate, ban
             FROM team_user WHERE id=? LIMIT 1'
        );
        $stmt->execute([$id]);
        $user=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || (int)$user['ban'] !== 0) {
            unset($_SESSION['kp_user_id'],$_SESSION['kp_user_username'],$_SESSION['kp_user_last_seen']);
            header('Location: /dang-nhap',true,302); exit;
        }
    } catch (Throwable $e) {
        kp_log('player account unavailable',$e);
        http_response_code(503); exit('Chưa tải được tài khoản. Hãy thử lại sau.');
    }
    $_SESSION['kp_user_last_seen']=time();
    return $user;
}

function kp_player_characters($accountId,$limit=8)
{
    // The SQL dump maps tob_web_info_char.userid to team_user.id
    // (NOT to team_user.userid). Do not expose this result to other users.
    $sql='SELECT w.charname,
                 COALESCE(l.lv,c.lastLv,w.lv) AS lastLv,
                 COALESCE(l.exp,c.xp,0) AS xp,
                 COALESCE(c.gold,l.gold,0) AS gold,
                 COALESCE(c.luong,l.luong,0) AS luong,
                 COALESCE(c.luonglock,0) AS luonglock,
                 c.pInfo AS farmer_pinfo,
                 c.lastLog, clan.clanName
          FROM tob_web_info_char AS w
          LEFT JOIN tob_char AS c ON c.charname=w.charname AND c.userId=w.userid
          LEFT JOIN tob_char_luong AS l ON l.charname=w.charname
          LEFT JOIN tob_clan AS clan ON clan.id_clan=COALESCE(c.idClan,l.idclan) AND clan.isdel=0
          WHERE w.userid=?
          ORDER BY COALESCE(l.lv,c.lastLv,w.lv) DESC,COALESCE(l.exp,c.xp,0) DESC
          LIMIT '.max(1,min(30,(int)$limit));
    $st=kp_db()->prepare($sql);
    $st->execute([(int)$accountId]);
    $rows=$st->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as &$row){
        $row['diemNongDan']=kp_farmer_points($row['farmer_pinfo']??null);
        unset($row['farmer_pinfo']); // Không đưa chuỗi pInfo nhạy cảm lên template.
    }
    unset($row);
    return $rows;
}

function kp_player_format($value)
{
    // Preserve large UNSIGNED BIGINT values such as EXP, PK scores or Luong nap.
    // Casting them to float loses digits above PHP's exact integer range.
    $raw=trim((string)$value);
    if (preg_match('/^-?[0-9]+$/D',$raw)) {
        $negative=substr($raw,0,1)==='-';
        $digits=ltrim($negative?substr($raw,1):$raw,'0');
        if ($digits==='') $digits='0';
        $formatted=preg_replace('/\\B(?=(\\d{3})+(?!\\d))/', '.', $digits);
        return ($negative && $digits!=='0'?'-':'').$formatted;
    }
    return number_format((float)$value,0,',','.');
}

/** Original KPAH vector icons. Names come from fixed server-side templates only. */
function kp_player_icon_svg(string $name): string {
    static $allowed = ['dashboard','user','gamepad','trophy','wallet','gift','bell','receipt','shield','logout','points','bank'];
    if (!in_array($name,$allowed,true)) return '';
    $icons = [
        'dashboard' => '<path d="M3.5 13.2a8.5 8.5 0 1 1 17 0v5.3a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2z"/><path d="M7 13.5h3l1.4-3.2 2.2 6.1 1.5-3H18"/><path d="M8 4.7 6.8 3.5M16 4.7l1.2-1.2"/>',
        'user' => '<circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c.5-4.1 3-6.2 7.5-6.2s7 2.1 7.5 6.2"/><path d="m17.5 6.2 1.1 1.1 2-2"/>',
        'gamepad' => '<path d="M7.5 8.5h9a4.5 4.5 0 0 1 4.3 5.8l-1.2 3.9a2.7 2.7 0 0 1-4.5 1.1L13.5 18h-3l-1.6 1.3a2.7 2.7 0 0 1-4.4-1.1l-1.2-3.9a4.5 4.5 0 0 1 4.2-5.8Z"/><path d="M8 11v4m-2-2h4m6.5-1h.1m2 2h.1"/>',
        'trophy' => '<path d="M8 4h8v4.5a4 4 0 0 1-8 0z"/><path d="M8 6H4.5v2a4 4 0 0 0 4 4M16 6h3.5v2a4 4 0 0 1-4 4M12 12.5V17m-4 3h8m-7-3h6l1 3H8z"/>',
        'wallet' => '<path d="M4.5 6.5h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-13a3 3 0 0 1-3-3v-12a2 2 0 0 1 2-2h13"/><path d="M20.5 11h-5a2.5 2.5 0 0 0 0 5h5"/><path d="M15.5 13.5h.1"/>',
        'gift' => '<path d="M3.5 10h17v10a1.5 1.5 0 0 1-1.5 1.5H5A1.5 1.5 0 0 1 3.5 20zM2.5 7h19v4h-19zM12 7v14.5"/><path d="M12 7H8.7a2.2 2.2 0 1 1 2.1-2.8L12 7Zm0 0h3.3a2.2 2.2 0 1 0-2.1-2.8L12 7Z"/>',
        'bell' => '<path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4"/><path d="M12 2v1"/>',
        'receipt' => '<path d="M6 3.5h12v17l-3-1.8-3 1.8-3-1.8-3 1.8z"/><path d="M9 8h6m-6 4h6m-6 4h3"/>',
        'shield' => '<path d="M12 2.8 20 6v5.3c0 5-3 8-8 10-5-2-8-5-8-10V6z"/><path d="m8.5 12 2.2 2.2 4.8-5"/>',
        'logout' => '<path d="M10 4H5.5A2.5 2.5 0 0 0 3 6.5v11A2.5 2.5 0 0 0 5.5 20H10"/><path d="M14 7l5 5-5 5m5-5H8"/>',
        'points' => '<path d="m12 2.8 2.4 5 5.5.8-4 3.9.9 5.5-4.8-2.6-4.8 2.6.9-5.5-4-3.9 5.5-.8z"/><circle cx="19" cy="19" r="2.5"/>',
        'bank' => '<path d="m3 8 9-5 9 5v2H3zM4.5 10v8m5-8v8m5-8v8m5-8v8M3 18h18v3H3z"/><path d="M2 8h20"/>',
    ];
    $shape = $icons[$name];
    return '<svg class="kp-art-icon kp-art-icon--'.$name.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><g>'.$shape.'</g></svg>';
}
function kp_player_icon_art(string $name): string { return kp_player_icon_svg($name); }

function kp_player_head($title,$active,array $user,$withCharacters=false)
{
    kp_send_security_headers(true);
    header('Content-Type: text/html; charset=utf-8');
    $app=(string)kp_config('app.name','KPAH');
    $username=(string)$user['username'];
    $unread=0;$latestUnread=null;
    try{
        $st=kp_db()->prepare('SELECT COUNT(*) FROM kp_user_notifications WHERE user_id=? AND is_read=0');$st->execute([(int)$user['id']]);$unread=(int)$st->fetchColumn();
        if($unread>0){$st=kp_db()->prepare('SELECT id,title,body FROM kp_user_notifications WHERE user_id=? AND is_read=0 ORDER BY id DESC LIMIT 1');$st->execute([(int)$user['id']]);$latestUnread=$st->fetch(PDO::FETCH_ASSOC)?:null;}
    }catch(Throwable $e){kp_log('player unread notification count unavailable',$e);}
    $initial=function_exists('mb_substr')
        ? mb_strtoupper(mb_substr($username,0,1,'UTF-8'),'UTF-8')
        : strtoupper(substr($username,0,1));
    $nav=[
        ['home','/tai-khoan','dashboard','Tổng quan'],
        ['profile','/ho-so','user','Hồ sơ'],
        ['characters','/nhan-vat','gamepad','Nhân vật'],
        ['rankings','/bang-xep-hang','trophy','Xếp hạng'],
        ['topup','/nap','wallet','Nạp game'],
        ['gifts','/giftcode-cua-toi','gift','Giftcode của tôi'],
        ['notifications','/thong-bao','bell','Thông báo'],
        ['forum','/dien-dan','receipt','Báo lỗi & góp ý'],
        ['orders','/theo-doi-don-nap','receipt','Theo dõi đơn nạp'],
        ['security','/lich-su-bao-mat','shield','Lịch sử bảo mật'],
    ];
    ?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#eef7ff">
<meta name="robots" content="noindex,nofollow">
<title><?= kp_e($title) ?> · <?= kp_e($app) ?></title>
<link rel="icon" type="image/webp" href="/assets/img/favicon.webp">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&amp;family=Cinzel:wght@700;900&amp;display=swap">
<link rel="stylesheet" href="/assets/css/register.css?v=1">
<link rel="stylesheet" href="/assets/css/user-home.css?v=6">
<link rel="stylesheet" href="/assets/css/home-theme.css?v=7">
<?php if($withCharacters): ?><link rel="stylesheet" href="/assets/css/characters.css?v=2"><?php endif; ?>
<?php if($title==='Hồ sơ nhân vật'): ?><link rel="stylesheet" href="/assets/css/character-inventory.css?v=1"><?php endif; ?>
<link rel="stylesheet" href="/assets/css/player-portal.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/player-portal.css') ?>">
<?php if($active==='home'):?><link rel="stylesheet" href="/assets/css/dashboard.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/dashboard.css') ?>"><?php endif;?>
<link rel="stylesheet" href="/assets/css/user-center.css?v=1">
<link rel="stylesheet" href="/assets/css/site-responsive.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/site-responsive.css') ?>">
<link rel="stylesheet" href="/assets/css/ui-system.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/ui-system.css') ?>">
<link rel="stylesheet" href="/assets/css/player-portal-v3.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/player-portal-v3.css') ?>">
<link rel="stylesheet" href="/assets/css/player-art-icons.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/player-art-icons.css') ?>">
<link rel="stylesheet" href="/assets/css/brand-system.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/brand-system.css') ?>">
</head>
<body class="home-page portal-page">
<a class="home-skip-link" href="#portal-content">Đến nội dung chính</a>
<aside class="side-rail portal-rail" aria-label="Điều hướng tài khoản">
    <a class="rail-logo portal-rail-logo" href="/tai-khoan" aria-label="KPAH — trang tài khoản">
        <img src="/assets/img/kpah-logo-main.webp" width="166" height="64" alt="NGHIỆN KPAH">
        <span>PLAYER PORTAL</span>
    </a>
    <div class="rail-line" aria-hidden="true"></div>
    <span class="portal-nav-caption">DÀNH CHO NGƯỜI CHƠI</span>
    <button type="button" class="portal-nav-toggle" aria-controls="portal-nav-list" aria-expanded="false"><span class="portal-nav-toggle-art" aria-hidden="true"><?= kp_player_icon_art('dashboard') ?></span><span>Danh mục tài khoản</span><span class="portal-toggle-arrow" aria-hidden="true">▾</span></button>
    <nav id="portal-nav-list" class="portal-nav-list" aria-label="Các chức năng tài khoản">
        <?php foreach($nav as $item): ?>
        <a class="rail-icon<?= $active===$item[0]?' active':'' ?><?= $item[0]==='notifications'&&$unread>0?' has-unread':'' ?>" href="<?= kp_e($item[1]) ?>" title="<?= kp_e($item[3]) ?>"<?= $active===$item[0]?' aria-current="page"':'' ?>>
            <span class="portal-nav-icon" aria-hidden="true"><?= kp_player_icon_art($item[2]) ?></span>
            <span class="portal-nav-label"><?= kp_e($item[3]) ?></span>
            <?php if($item[0]==='notifications'&&$unread>0):?><span class="portal-notice-count" aria-label="<?=$unread?> thông báo chưa đọc"><?=$unread>99?'99+':$unread?></span><?php endif;?>
        </a>
        <?php endforeach; ?>
    </nav>
    <div class="rail-bottom">
        <a class="rail-icon rail-logout" href="/api/logout" title="Đăng xuất">
            <span class="portal-nav-icon" aria-hidden="true"><?= kp_player_icon_art('logout') ?></span>
            <span class="portal-nav-label">Đăng xuất</span>
        </a>
    </div>
</aside>
<main class="home-main portal-main" id="portal-content">
    <header class="home-top portal-top">
        <div class="home-breadcrumb"><span class="breadcrumb-kicker">KPAH</span><span class="breadcrumb-separator">/</span><span><?= kp_e($title) ?></span></div>
        <div class="top-user"><span class="top-status"><i></i> ĐANG ĐĂNG NHẬP</span><a class="portal-notification-shortcut<?= $unread>0?' has-unread':''?>" href="/thong-bao" aria-label="Thông báo<?= $unread>0?' · '.$unread.' chưa đọc':''?>"><?=kp_player_icon_art('bell')?><?php if($unread>0):?><b><?=$unread>99?'99+':$unread?></b><?php endif;?></a><span class="top-user-name"><?= kp_e($username) ?></span><span class="mini-avatar" aria-hidden="true"><?= kp_e($initial) ?></span><a class="top-logout" href="/api/logout">Đăng xuất ↗</a></div>
    </header>
<?php $noticeMarker=$unread.'|'.(int)($latestUnread['id']??0);$shown=(string)($_SESSION['kp_user_notice_toast_marker']??'');if($unread>0&&$shown!==$noticeMarker):$_SESSION['kp_user_notice_toast_marker']=$noticeMarker;?><div hidden data-ui-toast="warning" data-ui-toast-title="Bạn có <?=number_format($unread,0,',','.')?> thông báo chưa đọc" data-ui-toast-message="<?=kp_e($latestUnread['title']??'Mở Trung tâm thông báo để xem chi tiết.')?>" data-ui-toast-duration="6500"></div><?php elseif($unread===0):$_SESSION['kp_user_notice_toast_marker']='';endif;?>
<?php
}

function kp_player_foot($withProfileJs=false)
{
    ?>
    <footer class="home-footer portal-footer">
        <span class="footer-brand">KPAH <i>·</i> Khu tài khoản</span>
        <a href="/bang-xep-hang">BẢNG XẾP HẠNG ↗</a>
        <a href="/api/logout">ĐĂNG XUẤT ↗</a>
    </footer>
</main>
<?php if(!empty($_SESSION['kp_toast']) && is_array($_SESSION['kp_toast'])): $toast=$_SESSION['kp_toast']; unset($_SESSION['kp_toast']); ?>
<div data-ui-toast="<?=kp_e($toast['type']??'success')?>" data-ui-toast-title="<?=kp_e($toast['title']??'')?>" data-ui-toast-message="<?=kp_e($toast['message']??'')?>" hidden></div>
<?php endif;?>
<?php if($withProfileJs): ?><script src="/assets/js/user-home.js?v=2" defer></script><?php endif; ?>
<script src="/assets/js/player-mobile-nav.js?v=<?= (int)@filemtime(KP_ROOT.'/assets/js/player-mobile-nav.js') ?>" defer></script>
<script src="/assets/js/ui-system.js?v=<?= (int)@filemtime(KP_ROOT.'/assets/js/ui-system.js') ?>" defer></script>
<script src="/assets/js/fantasy-foundation.js?v=<?= (int)@filemtime(KP_ROOT.'/assets/js/fantasy-foundation.js') ?>" defer></script>
</body></html>
<?php
}
