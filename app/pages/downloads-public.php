<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/game_downloads.php';
require_once __DIR__.'/../includes/public_landing.php';
kp_send_security_headers(true); header('Content-Type: text/html; charset=utf-8');
$catalog=kp_game_download_catalog();
$platforms=[['android','Android','A','APK · Điện thoại & máy tính bảng'],['windows','Windows','W','PC · Máy tính Windows'],['java','Java / JAR','J','JAR · Thiết bị hỗ trợ Java'],['ios','iPhone / iPad','i','IPA · Thiết bị iOS']];
kp_public_page_start('Tải game','download','Tải NGHIỆN KPAH cho Android, Windows, Java và iOS.');
kp_public_page_hero('SẴN SÀNG LÊN ĐƯỜNG','Chọn thiết bị,<br><em>vào game ngay</em>','Các liên kết được lấy trực tiếp từ cấu hình bản phát hành. Bản chưa sẵn sàng sẽ được ghi rõ, không dẫn tới file giả.','↓');
?>
<section class="lp-section lp-inner-content"><div class="lp-container"><div class="lp-download-grid">
<?php foreach($platforms as $platform): $release=$catalog[$platform[0]]; $url=!$release['enabled']?'':($release['source']==='file'?'/tai-game/tep?nen-tang='.$platform[0]:$release['url']); ?>
<article class="lp-download-card<?= $url!==''?' is-ready':'' ?>" data-landing-reveal><span class="lp-platform-icon lp-platform-<?=kp_e($platform[0])?>"><?=kp_e($platform[2])?></span><div><span class="lp-availability"><?= $url!==''?'SẴN SÀNG':'ĐANG CẬP NHẬT' ?></span><h3><?=kp_e($platform[1])?></h3><p><?=kp_e($platform[3])?></p></div><?php if($url!==''):?><a href="<?=kp_e($url)?>" target="_blank" rel="noopener noreferrer">Tải xuống <span>↓</span></a><?php else:?><span class="lp-download-disabled"><?= $release['enabled']?'Đang chuẩn bị file':'Tạm chưa mở tải' ?></span><?php endif;?></article>
<?php endforeach; ?>
</div><div class="lp-safe-note" data-landing-reveal><span>✓</span><div><h3>Tải đúng nguồn, chơi an toàn</h3><p>Không tải bản cài đặt từ liên kết lạ. Đội ngũ NGHIỆN KPAH không bao giờ yêu cầu mật khẩu hay mã xác minh để cho tải game.</p></div></div></div></section>
<?php kp_public_page_end(); ?>
