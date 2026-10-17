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
$events = [
    ['/tin-tuc', 'Tin tức & thông báo', 'Theo dõi những cập nhật từ thế giới KPAH', '01'],
    ['/tai-game', 'Chọn phiên bản của bạn', 'Tìm bản tải phù hợp với thiết bị', '02'],
    ['/giftcode', $codes ? count($codes).' mã quà đang chờ bạn' : 'Ghé thăm kho quà', 'Khám phá giftcode công khai', '03'],
];
kp_public_page_start('Bước vào thế giới phiêu lưu','home','NGHIỆN KPAH — cổng thông tin, tải game, giftcode và cộng đồng dành cho người chơi KPAH.');
?>
<section class="home-hero" aria-labelledby="home-title">
    <img class="home-hero-scene" src="/assets/img/home-hero-anime.webp" width="1672" height="941" alt="" fetchpriority="high" decoding="async">
    <div class="lp-container home-hero-inner">
        <div class="home-hero-copy">
            <span class="home-eyebrow home-hero-eyebrow">NGHIỆN KPAH · THẾ GIỚI PHIÊU LƯU</span>
            <h1 id="home-title">Một thế giới quen.<br><em>Ngàn hành trình mới.</em></h1>
            <p>Trở lại miền ký ức, gặp những người bạn mới.<br>Câu chuyện tiếp theo bắt đầu từ bạn.</p>
            <div class="home-actions">
                <a class="home-button home-button-primary" href="/tai-game">Tải game ngay <span aria-hidden="true">↓</span></a>
                <a class="home-button home-button-white" href="<?= !empty($_SESSION['kp_user_id']) ? '/tai-khoan' : '/dang-ky' ?>"><?= !empty($_SESSION['kp_user_id']) ? 'Vào tài khoản' : 'Tạo tài khoản' ?> <span aria-hidden="true">↗</span></a>
            </div>
            <a class="home-explore" href="#kham-pha">Khám phá thế giới KPAH <span aria-hidden="true">↓</span></a>
        </div>
    </div>
</section>

<div class="home-content">
    <div class="lp-container">
        <section class="home-stats" aria-label="Thống kê cộng đồng">
            <div class="home-stat"><span class="home-stat-dot" aria-hidden="true"></span><div><strong><?= $onlineCount === null ? '—' : number_format($onlineCount,0,',','.') ?></strong><span>Hoạt động gần đây</span></div></div>
            <div class="home-stat"><span class="home-stat-symbol" aria-hidden="true">✦</span><div><strong><?= $accountCount === null ? '—' : number_format($accountCount,0,',','.') ?></strong><span>Tài khoản</span></div></div>
            <div class="home-stat"><span class="home-stat-symbol" aria-hidden="true">♟</span><div><strong><?= $characterCount === null ? '—' : number_format($characterCount,0,',','.') ?></strong><span>Nhân vật</span></div></div>
            <a class="home-stat home-stat-gift" href="/giftcode"><span class="home-stat-symbol" aria-hidden="true">✧</span><div><strong><?= count($codes) ?></strong><span>Giftcode công khai <b aria-hidden="true">↗</b></span></div></a>
        </section>

        <section class="home-discover" id="kham-pha" aria-labelledby="home-discover-title">
            <div class="home-section-heading">
                <div><span class="home-eyebrow">HÀNH TRÌNH CỦA BẠN</span><h2 id="home-discover-title">Điểm hẹn của những <em>cuộc phiêu lưu.</em></h2></div>
                <a class="home-text-link" href="/gioi-thieu">Tìm hiểu KPAH <span aria-hidden="true">↗</span></a>
            </div>

            <div class="home-adventure-grid">
                <article class="home-panel home-start">
                    <div class="home-start-copy">
                        <span class="home-label">DÀNH CHO TÂN THỦ</span>
                        <h3>Khởi đầu nhỏ.<br>Hành trình lớn.</h3>
                        <p>Chỉ vài bước để sẵn sàng khám phá thế giới cùng những chiến hữu mới.</p>
                        <ol class="home-steps">
                            <li><span>01</span><a href="/dang-ky">Tạo tài khoản <b aria-hidden="true">↗</b></a></li>
                            <li><span>02</span><a href="/tai-game">Tải bản game phù hợp <b aria-hidden="true">↗</b></a></li>
                            <li><span>03</span><a href="/cong-dong">Tìm đồng đội, cùng vào game <b aria-hidden="true">↗</b></a></li>
                        </ol>
                        <a class="home-button home-button-primary" href="/gioi-thieu">Khám phá ngay <span aria-hidden="true">→</span></a>
                    </div>
                    <div class="home-start-art" aria-hidden="true">
                        <div class="home-art-orbit"></div>
                        <img src="/assets/img/auth-register-adventure.webp" width="800" height="731" alt="" loading="lazy" decoding="async">
                        <span class="home-art-caption">Một chương mới đang chờ</span>
                    </div>
                </article>

                <article class="home-panel home-treasure">
                    <div><span class="home-label">MÓN QUÀ CHO HÀNH TRÌNH</span><h3>Mở kho báu.<br>Nhận niềm vui.</h3></div>
                    <img src="/assets/img/treasure-chest-fantasy.webp" width="800" height="731" alt="Rương kho báu KPAH" loading="lazy" decoding="async">
                    <p class="home-gift-count"><strong><?= count($codes) ?></strong> mã quà công khai</p>
                    <a class="home-button home-button-white" href="/giftcode">Khám phá giftcode <span aria-hidden="true">↗</span></a>
                </article>
            </div>

            <div class="home-connect-grid">
                <article class="home-panel home-news">
                    <div class="home-panel-heading"><div><span class="home-eyebrow">ĐỪNG BỎ LỠ</span><h3>Ghé trạm thông tin</h3></div><a class="home-round-link" href="/tin-tuc" aria-label="Xem trang tin tức">↗</a></div>
                    <ul class="home-news-list">
                        <?php foreach ($events as $event): ?>
                        <li><a href="<?= kp_e($event[0]) ?>"><span class="home-news-number"><?= kp_e($event[3]) ?></span><div><h4><?= kp_e($event[1]) ?></h4><p><?= kp_e($event[2]) ?></p></div><span class="home-news-arrow" aria-hidden="true">↗</span></a></li>
                        <?php endforeach; ?>
                    </ul>
                </article>

                <article class="home-panel home-community">
                    <div class="home-community-copy">
                        <span class="home-label">HỘI QUÁN CHIẾN HỮU</span>
                        <h3>Chơi cùng nhau.<br>Nhớ lâu hơn.</h3>
                        <p>Tìm người cùng chinh phục thử thách, chia sẻ bí kíp và viết tiếp những kỷ niệm.</p>
                        <?php if ($heroes): ?>
                        <div class="home-player"><span aria-hidden="true">✦</span><div><small>CHIẾN BINH NỔI BẬT</small><strong><?= kp_e($heroes[0]['name']) ?></strong></div></div>
                        <?php else: ?>
                        <div class="home-player"><span aria-hidden="true">✦</span><div><small>MỘT THẾ GIỚI CHUNG</small><strong>Chiến hữu đang chờ bạn</strong></div></div>
                        <?php endif; ?>
                        <div class="home-community-actions"><a class="home-button home-button-primary" href="/cong-dong">Vào hội quán <span aria-hidden="true">↗</span></a><a class="home-text-link" href="/bang-xep-hang">Bảng xếp hạng →</a></div>
                    </div>
                </article>
            </div>
        </section>

        <nav class="home-shortcuts" aria-label="Tiện ích nhanh">
            <span>Mọi thứ bạn cần</span>
            <a href="/tai-game">Tải game <b aria-hidden="true">↓</b></a>
            <a href="/giftcode">Nhận giftcode <b aria-hidden="true">↗</b></a>
            <a href="/nap-the">Nạp thẻ <b aria-hidden="true">↗</b></a>
            <a href="/dang-nhap">Đăng nhập <b aria-hidden="true">↗</b></a>
        </nav>
    </div>
</div>
<?php kp_public_page_end(); ?>
