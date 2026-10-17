<?php
/**
 * Player account pages: one login guard and one navigation shell.
 * User-owned character details never leak into public leaderboards.
 */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }
require_once __DIR__.'/farmer_points.php';

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

/**
 * Original KPAH illustrations (raster PNG atlas, not third-party SVG glyphs).
 * Names come exclusively from the fixed server-side navigation and dashboard.
 */
function kp_player_icon_art(string $name): string {
    static $allowed = ['dashboard','user','gamepad','trophy','wallet','gift','bell','receipt','shield','logout','points','bank'];
    if (!in_array($name,$allowed,true)) return '';
    return '<span class="kp-art-icon kp-art-icon--'.$name.'" aria-hidden="true"></span>';
}

function kp_player_head($title,$active,array $user,$withCharacters=false)
{
    kp_send_security_headers(true);
    header('Content-Type: text/html; charset=utf-8');
    $app=(string)kp_config('app.name','KPAH');
    $username=(string)$user['username'];
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
        <a class="rail-icon<?= $active===$item[0]?' active':'' ?>" href="<?= kp_e($item[1]) ?>" title="<?= kp_e($item[3]) ?>"<?= $active===$item[0]?' aria-current="page"':'' ?>>
            <span class="portal-nav-icon" aria-hidden="true"><?= kp_player_icon_art($item[2]) ?></span>
            <span class="portal-nav-label"><?= kp_e($item[3]) ?></span>
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
        <div class="top-user"><span class="top-status"><i></i> ĐANG ĐĂNG NHẬP</span><span class="top-user-name"><?= kp_e($username) ?></span><span class="mini-avatar" aria-hidden="true"><?= kp_e($initial) ?></span><a class="top-logout" href="/api/logout">Đăng xuất ↗</a></div>
    </header>
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
<div data-toast-type="<?=kp_e($toast['type']??'success')?>" data-toast-title="<?=kp_e($toast['title']??'')?>" data-toast-message="<?=kp_e($toast['message']??'')?> " hidden></div>
<?php endif;?>
<?php if($withProfileJs): ?><script src="/assets/js/user-home.js?v=2" defer></script><?php endif; ?>
<script src="/assets/js/player-mobile-nav.js?v=<?= (int)@filemtime(KP_ROOT.'/assets/js/player-mobile-nav.js') ?>" defer></script>
<script src="/assets/js/toast.js?v=1" defer></script>
<script src="/assets/js/ui-system.js?v=<?= (int)@filemtime(KP_ROOT.'/assets/js/ui-system.js') ?>" defer></script>
<script src="/assets/js/fantasy-foundation.js?v=<?= (int)@filemtime(KP_ROOT.'/assets/js/fantasy-foundation.js') ?>" defer></script>
</body></html>
<?php
}
