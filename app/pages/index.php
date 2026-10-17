<?php
/** Public home: a focused first fold; detailed content lives on dedicated routes. */
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/giftcodes.php';
require_once __DIR__.'/../includes/rankings.php';
require_once __DIR__.'/../includes/public_landing.php';
kp_send_security_headers(true); header('Content-Type: text/html; charset=utf-8');
$accountCount=$characterCount=$onlineCount=null; $codes=[]; $heroes=[]; $metric='Sức mạnh';
try{
    $db=kp_db(); $accountCount=(int)$db->query('SELECT COUNT(*) FROM team_user')->fetchColumn();
    try{$characterCount=(int)$db->query('SELECT COUNT(*) FROM tob_char WHERE del=1 AND ban=0')->fetchColumn(); $onlineCount=(int)$db->query("SELECT COUNT(*) FROM tob_char WHERE del=1 AND ban=0 AND lastLog >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();}catch(Throwable $e){kp_log('không tải được thống kê nhân vật trang chủ',$e);}
    $codes=kp_giftcode_public_list($db,6);
    try{$heroes=array_slice(kp_rankings_fetch('strength'),0,5);}catch(Throwable $e){$metric='Cấp độ';$heroes=array_slice(kp_rankings_fetch('level'),0,5);}
}catch(Throwable $e){kp_log('không tải được dữ liệu động trang chủ',$e);}
$events=[['/tin-tuc','Hành trình mới trong thế giới KPAH','NEW'],['/tai-game','Tải game trên thiết bị của bạn','READY'],['/giftcode',$codes?count($codes).' giftcode công khai đang mở':'Kho quà đang cập nhật','HOT'],['/cong-dong','Gia nhập cộng đồng NGHIỆN KPAH','24/7']];
kp_public_page_start('Bước vào thế giới phiêu lưu','home','NGHIỆN KPAH — cổng thông tin, tải game, giftcode và cộng đồng dành cho người chơi KPAH.');
?>
<section class="lp-home-stage" aria-labelledby="lp-title">
    <div class="lp-home-cloud cloud-left" aria-hidden="true"></div><div class="lp-home-cloud cloud-right" aria-hidden="true"></div>
    <div class="lp-container lp-home-hero"><div class="lp-home-title" data-landing-reveal><h1 id="lp-title" class="lp-home-logo-heading"><img class="lp-home-logo" src="/assets/img/kpah-logo-main.webp" width="1280" height="512" alt=""><span class="lp-sr-only">NGHIỆN KPAH — Hành trình tuổi thơ</span></h1><p class="lp-home-subtitle">Sống lại ký ức · Kết nối đam mê · Cùng nhau phát triển</p><div class="lp-home-actions"><a class="lp-button lp-button-primary" href="/tai-game">Tải game ngay</a><a class="lp-button lp-button-warm" href="/nap-the">◉ Nạp thẻ</a></div></div></div>
    <div class="lp-container lp-home-dashboard" data-landing-reveal>
        <div class="lp-home-summary">
            <div class="lp-home-stats"><article><span class="lp-stat-icon is-green">⌁</span><div><strong><?= $onlineCount===null?'—':number_format($onlineCount,0,',','.') ?></strong><small>ĐANG ONLINE</small></div></article><article><span class="lp-stat-icon is-blue">♟</span><div><strong><?= $accountCount===null?'—':number_format($accountCount,0,',','.') ?></strong><small>TÀI KHOẢN</small></div></article><article><span class="lp-stat-icon is-pink">♣</span><div><strong><?= $characterCount===null?'—':number_format($characterCount,0,',','.') ?></strong><small>NHÂN VẬT</small></div></article><article><span class="lp-stat-icon is-orange">▣</span><div><strong><?=count($codes)?></strong><small>GIFTCODE</small></div></article></div>
            <article class="lp-event-card"><div class="lp-event-head"><strong>Sự kiện nổi bật <span>HOT</span></strong><a href="/tin-tuc">Xem tất cả →</a></div><ul><?php foreach($events as $event):?><li><span>✦</span><a href="<?=kp_e($event[0])?>"><?=kp_e($event[1])?></a><small><?=kp_e($event[2])?></small></li><?php endforeach;?></ul></article>
        </div>
        <section class="lp-quest-grid" aria-label="Cánh cổng phiêu lưu" data-kpf-reveal>
            <article class="lp-quest-card lp-quest-start">
                <div class="lp-quest-card-head"><span class="lp-quest-mark">🗺️</span><div><span class="lp-quest-kicker">CẨM NANG TÂN THỦ</span><h2>Khởi đầu hành trình</h2></div></div>
                <p class="lp-quest-description">Ba bước nhỏ để bước vào miền ký ức và viết tiếp câu chuyện của riêng bạn.</p>
                <ol class="lp-quest-steps">
                    <li><span>01</span><div><strong>Tạo tài khoản</strong><small>Giữ chỗ cho người hùng của bạn</small></div></li>
                    <li><span>02</span><div><strong>Tải bản game phù hợp</strong><small>Chọn thiết bị và sẵn sàng khám phá</small></div></li>
                    <li><span>03</span><div><strong>Gia nhập thế giới</strong><small>Kết bạn, lập đội và chinh phục thử thách</small></div></li>
                </ol>
                <a class="lp-quest-link" href="/dang-ky">Bắt đầu ngay <span aria-hidden="true">↗</span></a>
            </article>
            <article class="lp-quest-card lp-quest-treasure">
                <div class="lp-quest-card-head"><span class="lp-quest-mark lp-quest-gem" aria-hidden="true">✦</span><div><span class="lp-quest-kicker">BÍ MẬT TỪ THẾ GIỚI KPAH</span><h2>Kho báu phiêu lưu</h2></div></div>
                <p class="lp-quest-description">Mở chiếc rương bí ẩn, nhận mã thưởng và chuẩn bị cho cuộc phiêu lưu tiếp theo.</p>
                <div class="lp-quest-treasure-art" aria-hidden="true"><img class="lp-treasure-illustration" src="/assets/img/treasure-chest-fantasy.svg" alt="" width="640" height="460" loading="lazy" decoding="async"></div>
                <div class="lp-quest-meta"><strong><?= count($codes) ?></strong><span>mã quà đang mở</span></div>
                <a class="lp-quest-link" href="/giftcode">Mở kho quà <span aria-hidden="true">↗</span></a>
            </article>
            <article class="lp-quest-card lp-quest-community">
                <div class="lp-quest-card-head"><span class="lp-quest-mark">🤝</span><div><span class="lp-quest-kicker">HỘI QUÁN CHIẾN HỮU</span><h2>Đi cùng đồng đội</h2></div></div>
                <p class="lp-quest-description">Chia sẻ chiến thuật, tìm bạn cùng chơi và theo dõi những điều mới mẻ.</p>
                <div class="lp-quest-community-art" aria-hidden="true"><span>⚔️</span><span>🌟</span><span>🛡️</span></div>
                <?php if ($heroes): ?><p class="lp-quest-highlight">Một chiến binh đáng chú ý: <strong><?= kp_e($heroes[0]['name']) ?></strong></p><?php else: ?><p class="lp-quest-highlight">Những huyền thoại mới đang được viết nên mỗi ngày.</p><?php endif; ?>
                <div class="lp-quest-community-actions"><a class="lp-quest-link" href="/cong-dong">Vào hội quán <span aria-hidden="true">↗</span></a><a class="lp-quest-secondary" href="/bang-xep-hang">Bảng xếp hạng</a></div>
            </article>
        </section>
        <nav class="lp-home-destinations" aria-label="Khám phá nhanh"><a href="/gioi-thieu"><span>⚔</span><b>Giới thiệu</b><small>Khám phá server</small></a><a href="/tai-game"><span>↓</span><b>Tải game</b><small>Chọn thiết bị</small></a><a href="/giftcode"><span>♢</span><b>Giftcode</b><small>Nhận quà mới</small></a><a href="/tin-tuc"><span>◉</span><b>Tin nổi bật</b><small>Xem cập nhật</small></a><a href="/cong-dong"><span>♧</span><b>Cộng đồng</b><small>Tìm đồng đội</small></a></nav>
    </div>
</section>
<?php kp_public_page_end(); ?>
