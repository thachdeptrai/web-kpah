<?php
$root=dirname(__DIR__);function ucheck($ok,$label){if(!$ok){fwrite(STDERR,"FAIL: $label\n");exit(1);}}
$shell=file_get_contents($root.'/app/includes/player_portal.php');$page=file_get_contents($root.'/app/pages/notifications.php');$css=file_get_contents($root.'/assets/css/user-center.css');$portal=file_get_contents($root.'/assets/css/player-portal-v3.css');$login=file_get_contents($root.'/app/pages/login.php');
ucheck(strpos($shell,'portal-notice-count')!==false && strpos($shell,'portal-notification-shortcut')!==false,'menu and header badges');
ucheck(strpos($shell,'Bạn có <?=number_format($unread')!==false && strpos($shell,'kp_user_notice_toast_count')!==false,'unread toast deduplicated per count');
ucheck(strpos($page,"'uc-unread':'uc-read'")!==false,'read and unread classes');
ucheck(strpos($css,'.uc-record.uc-unread::before')!==false && strpos($css,'.uc-record.uc-read')!==false,'red dot and faded read styles');
ucheck(strpos($portal,'.portal-notification-shortcut.has-unread')!==false && strpos($portal,'.portal-notice-count')!==false,'global red badges styled');
ucheck(strpos($login,"unset(\$_SESSION['kp_user_notice_toast_count'])")!==false,'login resets toast marker');
echo "User notification badge and toast static checks: OK\n";
