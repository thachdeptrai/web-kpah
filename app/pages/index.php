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
    <div class="lp-container lp-home-hero"><div class="lp-home-title" data-landing-reveal><span class="lp-home-spark">✦</span><h1 id="lp-title"><small>NGHIỆN</small><strong>KPAH</strong></h1><p class="lp-home-tagline">HÀNH TRÌNH TUỔI THƠ</p><p class="lp-home-subtitle">Sống lại ký ức · Kết nối đam mê · Cùng nhau phát triển</p><div class="lp-home-actions"><a class="lp-button lp-button-primary" href="/tai-game">Tải game ngay</a><a class="lp-button lp-button-warm" href="/nap-the">◉ Nạp thẻ</a></div></div></div>
    <div class="lp-container lp-home-dashboard" data-landing-reveal>
        <div class="lp-home-summary">
            <div class="lp-home-stats"><article><span class="lp-stat-icon is-green">⌁</span><div><strong><?= $onlineCount===null?'—':number_format($onlineCount,0,',','.') ?></strong><small>ĐANG ONLINE</small></div></article><article><span class="lp-stat-icon is-blue">♟</span><div><strong><?= $accountCount===null?'—':number_format($accountCount,0,',','.') ?></strong><small>TÀI KHOẢN</small></div></article><article><span class="lp-stat-icon is-pink">♣</span><div><strong><?= $characterCount===null?'—':number_format($characterCount,0,',','.') ?></strong><small>NHÂN VẬT</small></div></article><article><span class="lp-stat-icon is-orange">▣</span><div><strong><?=count($codes)?></strong><small>GIFTCODE</small></div></article></div>
            <article class="lp-event-card"><div class="lp-event-head"><strong>Sự kiện nổi bật <span>HOT</span></strong><a href="/tin-tuc">Xem tất cả →</a></div><ul><?php foreach($events as $event):?><li><span>✦</span><a href="<?=kp_e($event[0])?>"><?=kp_e($event[1])?></a><small><?=kp_e($event[2])?></small></li><?php endforeach;?></ul></article>
        </div>
        <div class="lp-home-widgets">
            <article class="lp-widget lp-widget-ranking"><div class="lp-widget-head"><h2>🏅 Top <?=kp_e($metric)?></h2><a href="/bang-xep-hang">Xem tất cả →</a></div><?php if($heroes):?><ol><?php foreach($heroes as $index=>$hero):?><li><span class="lp-rank-medal rank-<?=$index+1?>"><?=$index+1?></span><span class="lp-rank-face"><?=kp_e(strtoupper(substr((string)$hero['name'],0,1)))?></span><strong><?=kp_e($hero['name'])?></strong><b><?=number_format((int)$hero['score'],0,',','.')?></b></li><?php endforeach;?></ol><?php else:?><p class="lp-widget-empty">Bảng xếp hạng đang cập nhật.</p><?php endif;?></article>
            <article class="lp-widget lp-widget-characters"><div class="lp-widget-head"><h2>🧩 Nhân vật nổi bật</h2><a href="/bang-xep-hang">Xem tất cả →</a></div><div class="lp-character-showcase"><?php $art=['rank-guardian.svg','rank-sorcerer.svg','rank-archer.svg','rank-guardian.svg'];foreach(array_slice($heroes,0,4) as $index=>$hero):?><div><strong><?=kp_e($hero['name'])?></strong><small><?=kp_e($metric)?> <?=number_format((int)$hero['score'],0,',','.')?></small><img src="/assets/img/<?=kp_e($art[$index])?>" width="150" height="170" alt="" loading="lazy"></div><?php endforeach;?><?php if(!$heroes):?><p class="lp-widget-empty">Nhân vật nổi bật sẽ xuất hiện khi có dữ liệu.</p><?php endif;?></div></article>
            <article class="lp-widget lp-widget-gallery"><div class="lp-widget-head"><h2>⚔ Khám phá KPAH</h2></div><a href="/gioi-thieu" class="lp-gallery-image"><img src="/assets/img/home-hero-anime.webp" width="560" height="315" alt="Thế giới fantasy NGHIỆN KPAH" loading="lazy"><span>Khám phá thế giới →</span></a></article>
        </div>
        <nav class="lp-home-destinations" aria-label="Khám phá nhanh"><a href="/gioi-thieu"><span>⚔</span><b>Giới thiệu</b><small>Khám phá server</small></a><a href="/tai-game"><span>↓</span><b>Tải game</b><small>Chọn thiết bị</small></a><a href="/giftcode"><span>♢</span><b>Giftcode</b><small>Nhận quà mới</small></a><a href="/tin-tuc"><span>◉</span><b>Tin nổi bật</b><small>Xem cập nhật</small></a><a href="/cong-dong"><span>♧</span><b>Cộng đồng</b><small>Tìm đồng đội</small></a></nav>
    </div>
</section>
<?php kp_public_page_end(); ?>
