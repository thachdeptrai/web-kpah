<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/game_downloads.php';
require_once __DIR__.'/../includes/public_landing.php';
kp_send_security_headers(true); header('Content-Type: text/html; charset=utf-8');
$catalog=kp_game_download_catalog();
$platforms=[['android','Android','APK · Điện thoại & máy tính bảng'],['windows','Windows','PC · Máy tính Windows'],['java','Java / JAR','JAR · Thiết bị hỗ trợ Java'],['ios','iPhone / iPad','IPA · Thiết bị iOS']];
kp_public_page_start('Tải game','download','Tải NGHIỆN KPAH cho Android, Windows, Java và iOS.');
kp_public_page_hero('TẢI GAME','Tải <em>NGHIỆN KPAH</em>','Chọn bản cài đặt phù hợp với thiết bị của bạn.','↓');
?>
<section class="lp-section lp-inner-content"><div class="lp-container"><div class="lp-content-heading"><div><span class="lp-section-kicker">BẢN CÀI ĐẶT</span><h2>Chọn nền tảng</h2></div><p>Nút tải chỉ hiện khi có bản phát hành.</p></div><div class="lp-download-grid">
<?php foreach($platforms as $platform): $release=$catalog[$platform[0]]; $url=!$release['enabled']?'':($release['source']==='file'?'/tai-game/tep?nen-tang='.$platform[0]:$release['url']); ?>
<article class="lp-download-card<?= $url!==''?' is-ready':'' ?>" data-landing-reveal><span class="lp-platform-icon lp-platform-<?=kp_e($platform[0])?>"><span class="lp-platform-brand" aria-hidden="true"></span></span><div><span class="lp-availability"><?= $url!==''?'SẴN SÀNG':'ĐANG CẬP NHẬT' ?></span><h3><?=kp_e($platform[1])?></h3><p><?=kp_e($platform[2])?></p></div><?php if($url!==''):?><a href="<?=kp_e($url)?>" target="_blank" rel="noopener noreferrer">Tải xuống <span>↓</span></a><?php else:?><span class="lp-download-disabled"><?= $release['enabled']?'Đang cập nhật':'Chưa có bản tải' ?></span><?php endif;?></article>
<?php endforeach; ?>
</div><div class="lp-safe-note" data-landing-reveal><span>✓</span><div><h3>Cài đặt xong? Đăng nhập để chơi.</h3><p>Dùng tài khoản đã đăng ký trên website. Chưa có tài khoản? <a href="/dang-ky">Đăng ký tại đây</a>.</p></div></div></div></section>
<?php kp_public_page_end(); ?>
