<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/player_portal.php';
require_once __DIR__.'/../includes/dashboard_data.php';
$user=kp_player_account();
$username=(string)$user['username'];
$dashboard=kp_dashboard_data((int)$user['id']);
$registeredAt='';
if(!empty($user['regdate'])){
    $ts=is_numeric($user['regdate'])?(int)$user['regdate']:strtotime((string)$user['regdate']);
    if($ts!==false && $ts>0)$registeredAt=date('d/m/Y',$ts);
}
$hasEmail=filter_var(trim((string)($user['email']??'')),FILTER_VALIDATE_EMAIL)!==false;
$hasPhone=trim((string)($user['phone']??''))!=='';
$walletReady=$dashboard['vnd']!==null && is_numeric($dashboard['vnd']);
$totalReady=$dashboard['tongnap']!==null && is_numeric($dashboard['tongnap']);
$pointsReady=$dashboard['points']!==null && is_numeric($dashboard['points']);
$pointsValue=$pointsReady?(int)$dashboard['points']:0;
kp_player_head('Tổng quan tài khoản','home',$user);
?>
<section class="portal-hero portal-dashboard-hero">
    <div class="portal-hero-copy">
        <span class="portal-kicker"><i></i> KHÔNG GIAN TÀI KHOẢN KPAH</span>
        <h1>Xin chào, <em><?=kp_e($username)?>.</em></h1>
        <p>Thông tin tài khoản, tài nguyên, nhân vật và giao dịch gần đây của bạn — tất cả ở một nơi.</p>
        <div class="portal-hero-buttons">
            <a href="/vi-vnd" class="portal-primary">Ví VNĐ & đổi mốc <span aria-hidden="true">↗</span></a>
            <a href="/nhan-vat" class="portal-secondary">Xem nhân vật <span aria-hidden="true">↗</span></a>
        </div>
    </div>
    <div class="portal-hero-emblem" aria-hidden="true">
        <span class="portal-emblem-ring"></span>
        <img src="/assets/img/rank-guardian.svg" alt="" width="220" height="248" loading="lazy">
        <span class="portal-emblem-label">KPAH · LEGENDS</span>
    </div>
</section>

<section class="pd-section" aria-labelledby="pd-assets-title">
    <div class="pd-heading"><div><span class="portal-kicker"><i></i> TÀI NGUYÊN TÀI KHOẢN</span><h2 id="pd-assets-title">Ví & <em>tích lũy</em></h2></div>
        <a class="pd-inline-link" href="/lich-su-nap">Lịch sử nạp <span aria-hidden="true">↗</span></a>
    </div>
    <div class="pd-stat-grid">
        <a href="/vi-vnd" class="pd-stat-card pd-stat-wallet">
            <span class="pd-stat-top"><span>Ví VNĐ hiện có</span><span class="pd-stat-symbol" aria-hidden="true">◎</span></span>
            <strong><?= $walletReady?kp_player_format($dashboard['vnd']).'đ':'—' ?></strong>
            <small>Dùng số dư để đổi mốc Lượng <span aria-hidden="true">↗</span></small>
        </a>
        <a href="/bang-xep-hang?type=nap" class="pd-stat-card">
            <span class="pd-stat-top"><span>Tổng đã nạp</span><span class="pd-stat-symbol" aria-hidden="true">↗</span></span>
            <strong><?= $totalReady?kp_player_format($dashboard['tongnap']).'đ':'—' ?></strong>
            <small>Không tính tiền khuyến mãi <span aria-hidden="true">↗</span></small>
        </a>
        <a href="/diem-tich-luy" class="pd-stat-card">
            <span class="pd-stat-top"><span>Point tích lũy</span><span class="pd-stat-symbol" aria-hidden="true">✧</span></span>
            <strong><?= $pointsReady?kp_player_format($dashboard['points']):'—' ?></strong>
            <small>Điểm dùng để đổi quà <span aria-hidden="true">↗</span></small>
        </a>
        <a href="/nhan-vat" class="pd-stat-card">
            <span class="pd-stat-top"><span>Nhân vật sở hữu</span><span class="pd-stat-symbol" aria-hidden="true">♙</span></span>
            <strong><?= $dashboard['characters_count']===null?'—':(int)$dashboard['characters_count'] ?></strong>
            <small>Xem hồ sơ nhân vật <span aria-hidden="true">↗</span></small>
        </a>
    </div>
</section>

<section class="pd-section pd-split" aria-label="Nhân vật và đổi quà">
    <div class="pd-panel">
        <div class="pd-panel-header"><div><span class="portal-kicker"><i></i> THẾ GIỚI GAME</span><h2>Nhân vật <em>của tôi</em></h2></div><a class="pd-inline-link" href="/nhan-vat">Tất cả ↗</a></div>
        <?php if(!$dashboard['characters_ready']):?>
            <p class="pd-empty">Chưa tải được danh sách nhân vật. Bạn có thể thử lại ở trang nhân vật.</p>
        <?php elseif(!$dashboard['characters']):?>
            <p class="pd-empty">Tài khoản chưa có nhân vật nào được game ghi nhận.</p>
        <?php else:?>
            <div class="pd-character-list">
            <?php foreach($dashboard['characters'] as $character):
                $name=(string)($character['charname']??'');
                $clan=trim((string)($character['clanName']??''));
                $lastSeen='';$stamp=strtotime((string)($character['lastLog']??''));
                if($stamp!==false && $stamp>strtotime('2010-01-02'))$lastSeen=date('d/m/Y',$stamp);
            ?>
                <article class="pd-character">
                    <div class="pd-character-avatar" aria-hidden="true">♙</div>
                    <div class="pd-character-body">
                        <div class="pd-character-name"><strong><?=kp_e($name)?></strong><span>Cấp <?= (int)($character['lastLv']??0) ?></span></div>
                        <p><?= $clan!==''?kp_e($clan):'Chưa gia nhập bang hội' ?><?= $lastSeen!==''?' · Hoạt động '.$lastSeen:'' ?></p>
                        <div class="pd-character-resources">
                            <span>Xu <b><?=kp_player_format($character['gold']??0)?></b></span>
                            <span>Lượng <b><?=kp_player_format($character['luong']??0)?></b></span>
                        </div>
                    </div>
                </article>
            <?php endforeach;?>
            </div>
        <?php endif;?>
    </div>
    <div class="pd-panel pd-rewards">
        <div class="pd-panel-header"><div><span class="portal-kicker"><i></i> ĐẶC QUYỀN THÀNH VIÊN</span><h2>Đổi quà <em>Point</em></h2></div><span class="pd-panel-symbol" aria-hidden="true">✧</span></div>
        <div class="pd-reward-balance"><span>Point đang có</span><strong><?= $pointsReady?kp_player_format($pointsValue):'—' ?></strong><small><?= $pointsReady?'Đã tích lũy '.kp_player_format($dashboard['earned']??0).' Point':'Dữ liệu Point chưa sẵn sàng' ?></small></div>
        <?php if($dashboard['reward'] && $pointsReady):
            $reward=$dashboard['reward'];$cost=(int)$reward['points_cost'];$missing=max(0,$cost-$pointsValue); ?>
            <div class="pd-reward-preview">
                <span>QUÀ ĐANG MỞ ĐỔI</span>
                <strong><?=kp_e($reward['title'])?></strong>
                <small><?=kp_player_format($cost)?> Point · <?= $missing>0?'Còn thiếu '.kp_player_format($missing).' Point':'Bạn đã đủ Point để đổi' ?></small>
            </div>
        <?php elseif($dashboard['rewards_ready']):?>
            <p class="pd-empty pd-empty--reward">Chưa có quà đang mở đổi. Bạn có thể xem lịch sử Point bất cứ lúc nào.</p>
        <?php else:?>
            <p class="pd-empty pd-empty--reward">Danh sách quà tạm thời chưa tải được.</p>
        <?php endif;?>
        <a class="pd-panel-action" href="/diem-tich-luy">Vào cửa hàng Point <span aria-hidden="true">↗</span></a>
    </div>
</section>

<section class="pd-section pd-split pd-split--lower" aria-label="Giao dịch và thông tin tài khoản">
    <div class="pd-panel">
        <div class="pd-panel-header"><div><span class="portal-kicker"><i></i> NẠP GAME</span><h2>Giao dịch <em>gần đây</em></h2></div><a class="pd-inline-link" href="/lich-su-nap">Xem lịch sử ↗</a></div>
        <?php if(!$dashboard['payments']):?>
            <p class="pd-empty"><?= $dashboard['payments_ready']?'Chưa có giao dịch nạp gần đây.':'Chưa tải được lịch sử giao dịch.' ?></p>
        <?php else:?>
            <div class="pd-payment-list">
                <?php foreach($dashboard['payments'] as $payment):?>
                <div class="pd-payment">
                    <span class="pd-payment-icon" aria-hidden="true"><?= $payment['source']==='Thẻ cào'?'▣':'◎' ?></span>
                    <span class="pd-payment-main"><strong><?=kp_e($payment['source'])?></strong><small><?= $payment['time']>0?date('d/m/Y H:i',(int)$payment['time']):'Chưa rõ thời gian' ?> · #<?=kp_e($payment['reference'])?></small></span>
                    <span class="pd-payment-end"><b><?=kp_player_format($payment['amount'])?>đ</b><small class="<?= $payment['done']?'pd-status-good':'pd-status-neutral' ?>"><?=kp_e($payment['label'])?></small></span>
                </div>
                <?php endforeach;?>
            </div>
        <?php endif;?>
    </div>
    <div class="pd-panel pd-account">
        <div class="pd-panel-header"><div><span class="portal-kicker"><i></i> HỒ SƠ</span><h2>Thông tin <em>tài khoản</em></h2></div><a class="pd-inline-link" href="/ho-so">Chỉnh sửa ↗</a></div>
        <dl class="pd-account-list">
            <div><dt>ID tài khoản</dt><dd>#<?=(int)$user['id']?></dd></div>
            <div><dt>Ngày tham gia</dt><dd><?= $registeredAt!==''?kp_e($registeredAt):'Chưa cập nhật' ?></dd></div>
            <div><dt>Email</dt><dd class="<?= $hasEmail?'pd-verified':'pd-unverified' ?>"><?= $hasEmail?'Đã liên kết':'Chưa liên kết' ?></dd></div>
            <div><dt>Số điện thoại</dt><dd class="<?= $hasPhone?'pd-verified':'pd-unverified' ?>"><?= $hasPhone?'Đã liên kết':'Chưa liên kết' ?></dd></div>
        </dl>
        <div class="pd-account-actions">
            <a href="/ho-so#security">Bảo mật tài khoản ↗</a>
            <a href="/nap">Nạp thêm VNĐ ↗</a>
        </div>
    </div>
</section>

<?php if($dashboard['achievement']):$ach=$dashboard['achievement'];?>
<section class="pd-section pd-achievements" aria-labelledby="pd-achievements-title">
    <div class="pd-heading"><div><span class="portal-kicker"><i></i> THÀNH TÍCH CÁ NHÂN</span><h2 id="pd-achievements-title">Dấu ấn <em>nhân vật</em></h2></div><a class="pd-inline-link" href="/nhan-vat">Xem hồ sơ ↗</a></div>
    <div class="pd-achievement-row">
        <div class="pd-achievement-identity"><span aria-hidden="true">♛</span><div><small>Nhân vật tiêu biểu</small><strong><?=kp_e($ach['charname'])?></strong></div></div>
        <div class="pd-achievement-item"><small>Danh vọng</small><strong><?=kp_player_format($ach['honor']??0)?></strong></div>
        <div class="pd-achievement-item"><small>Điểm PK</small><strong><?=kp_player_format($ach['nPK']??0)?></strong></div>
        <div class="pd-achievement-item"><small>Cấp VIP</small><strong><?= $ach['vip']!==null?kp_player_format($ach['vip']):'—' ?></strong></div>
    </div>
</section>
<?php endif;?>

<section class="portal-pages" aria-labelledby="portal-pages-title">
    <div class="portal-section-heading"><div><span class="portal-kicker"><i></i> KHÁM PHÁ KPAH</span><h2 id="portal-pages-title">Đi tới <em>chức năng</em></h2></div><p>Truy cập nhanh các trang thường dùng.</p></div>
    <div class="portal-action-grid">
        <a class="portal-action-card portal-action-profile" href="/ho-so"><span class="portal-action-icon" aria-hidden="true">◎</span><span class="portal-action-copy"><small>TÀI KHOẢN</small><strong>Hồ sơ & bảo mật</strong><span>Cập nhật thông tin và mật khẩu.</span></span><span class="portal-action-go" aria-hidden="true">↗</span></a>
        <a class="portal-action-card portal-action-characters" href="/nhan-vat"><span class="portal-action-icon" aria-hidden="true">♙</span><span class="portal-action-copy"><small>THẾ GIỚI GAME</small><strong>Nhân vật của tôi</strong><span>Cấp độ, Xu, Lượng, bang hội.</span></span><span class="portal-action-go" aria-hidden="true">↗</span></a>
        <a class="portal-action-card portal-action-ranking" href="/bang-xep-hang"><span class="portal-action-icon" aria-hidden="true">♛</span><span class="portal-action-copy"><small>THÀNH TÍCH</small><strong>Bảng xếp hạng</strong><span>Thứ hạng trong thế giới KPAH.</span></span><span class="portal-action-go" aria-hidden="true">↗</span></a>
        <a class="portal-action-card portal-action-topup" href="/nap"><span class="portal-action-icon" aria-hidden="true">✦</span><span class="portal-action-copy"><small>NẠP TÀI NGUYÊN</small><strong>Nạp & đổi mốc</strong><span>Ngân hàng, thẻ cào, đổi Lượng.</span></span><span class="portal-action-go" aria-hidden="true">↗</span></a>
    </div>
    <div class="portal-quick-links">
        <a href="/vi-vnd"><span aria-hidden="true">◎</span> Ví VNĐ & đổi mốc <span aria-hidden="true">↗</span></a>
        <a href="/diem-tich-luy"><span aria-hidden="true">✧</span> Point tích lũy & đổi quà <span aria-hidden="true">↗</span></a>
        <a href="/bang-xep-hang?type=nap"><span aria-hidden="true">♛</span> Top nạp VNĐ <span aria-hidden="true">↗</span></a>
        <a href="/#dien-dan"><span aria-hidden="true">♧</span> Cộng đồng <span aria-hidden="true">↗</span></a>
    </div>
</section>
<?php kp_player_foot(); ?>
