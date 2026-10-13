<?php
require dirname(__DIR__, 2).'/includes/bootstrap.php';
require_once dirname(__DIR__, 2).'/includes/player_portal.php';
$user=kp_player_account();
$username=(string)$user['username'];
$charCount=null;
try {
    $st=kp_db()->prepare('SELECT COUNT(*) FROM tob_web_info_char WHERE userid=?');
    $st->execute([(int)$user['id']]);
    $charCount=(int)$st->fetchColumn();
} catch (Throwable $e) { kp_log('player overview character count failed',$e); }
$registeredAt='';
if(!empty($user['regdate'])){
    $ts=is_numeric($user['regdate'])?(int)$user['regdate']:strtotime((string)$user['regdate']);
    if($ts!==false && $ts>0)$registeredAt=date('d/m/Y',$ts);
}
$hasEmail=filter_var(trim((string)($user['email']??'')),FILTER_VALIDATE_EMAIL)!==false;
kp_player_head('Tổng quan tài khoản','home',$user);
?>
<section class="portal-hero portal-dashboard-hero">
    <div class="portal-hero-copy">
        <span class="portal-kicker"><i></i> KHU VỰC NGƯỜI CHƠI</span>
        <h1>Chào, <em><?= kp_e($username) ?>.</em></h1>
        <p>Muốn xem nhân vật, đổi mật khẩu hay ngó bảng Top? Chọn ngay mục bên dưới.</p>
        <div class="portal-hero-buttons">
            <a href="/tai-khoan/nhan-vat" class="portal-primary">Nhân vật của tôi <span aria-hidden="true">↗</span></a>
            <a href="/bang-xep-hang" class="portal-secondary">Bảng xếp hạng <span aria-hidden="true">↗</span></a>
        </div>
    </div>
    <div class="portal-hero-emblem" aria-hidden="true">
        <span class="portal-emblem-ring"></span>
        <img src="/assets/img/rank-guardian.svg" alt="" width="220" height="248" loading="lazy">
        <span class="portal-emblem-label">KPAH · LEGENDS</span>
    </div>
</section>

<section class="portal-overview" aria-label="Tình trạng tài khoản">
    <div class="portal-overview-cell"><span class="portal-stat-icon" aria-hidden="true">◎</span><span><small>TÀI KHOẢN</small><b>#<?= (int)$user['id'] ?></b></span></div>
    <div class="portal-overview-cell"><span class="portal-stat-icon" aria-hidden="true">♙</span><span><small>NHÂN VẬT</small><b><?= $charCount===null?'—':(int)$charCount ?></b></span></div>
    <div class="portal-overview-cell"><span class="portal-stat-icon" aria-hidden="true">✧</span><span><small>EMAIL</small><b><?= $hasEmail?'Đã liên kết':'Chưa liên kết' ?></b></span></div>
    <div class="portal-overview-cell"><span class="portal-stat-icon" aria-hidden="true">◷</span><span><small>THAM GIA</small><b><?= $registeredAt!==''?kp_e($registeredAt):'Thành viên' ?></b></span></div>
</section>

<section class="portal-pages" aria-labelledby="portal-pages-title">
    <div class="portal-section-heading"><div><span class="portal-kicker"><i></i> ĐIỀU HƯỚNG</span><h2 id="portal-pages-title">Bạn muốn <em>làm gì?</em></h2></div><p>Mỗi việc một góc, khỏi phải cuộn tìm.</p></div>
    <div class="portal-action-grid">
        <a class="portal-action-card portal-action-profile" href="/tai-khoan/ho-so">
            <span class="portal-action-icon" aria-hidden="true">◎</span>
            <span class="portal-action-copy"><small>TÀI KHOẢN · 01</small><strong>Hồ sơ & bảo mật</strong><span>Đổi mật khẩu, email và số điện thoại.</span></span>
            <span class="portal-action-go" aria-hidden="true">↗</span>
        </a>
        <a class="portal-action-card portal-action-characters" href="/tai-khoan/nhan-vat">
            <span class="portal-action-icon" aria-hidden="true">♙</span>
            <span class="portal-action-copy"><small>NHÂN VẬT · 02</small><strong>Nhân vật của tôi</strong><span>Xem cấp độ, kinh nghiệm và tài nguyên game.</span></span>
            <span class="portal-action-go" aria-hidden="true">↗</span>
        </a>
        <a class="portal-action-card portal-action-ranking" href="/bang-xep-hang">
            <span class="portal-action-icon" aria-hidden="true">♛</span>
            <span class="portal-action-copy"><small>VINH DANH · 03</small><strong>Bảng xếp hạng</strong><span>Top 1–3 trên bục vinh danh, Top 4–10 bên dưới.</span></span>
            <span class="portal-action-go" aria-hidden="true">↗</span>
        </a>
        <a class="portal-action-card portal-action-topup" href="nap.php">
            <span class="portal-action-icon" aria-hidden="true">✦</span>
            <span class="portal-action-copy"><small>TÀI NGUYÊN · 04</small><strong>Nạp tài nguyên</strong><span>Thẻ cào, lịch sử và thông tin nạp.</span></span>
            <span class="portal-action-go" aria-hidden="true">↗</span>
        </a>
    </div>
    <div class="portal-quick-links">
        <a href="profile.php#security"><span aria-hidden="true">◇</span> Đổi mật khẩu <span aria-hidden="true">↗</span></a>
        <a href="/bang-xep-hang?type=nap"><span aria-hidden="true">♛</span> Top nạp Lượng <span aria-hidden="true">↗</span></a>
        <a href="index.php#dien-dan"><span aria-hidden="true">♧</span> Cộng đồng <span aria-hidden="true">↗</span></a>
    </div>
</section>
<?php kp_player_foot(); ?>
