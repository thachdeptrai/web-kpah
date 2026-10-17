<?php
define('KP_ROOT',dirname(__DIR__));
require KP_ROOT.'/app/includes/user_center.php';
function check($ok,$why){if(!$ok){fwrite(STDERR,"FAIL ".$why."\n");exit(1);}}
class CenterStatement {
 public $params=[];public function execute($params){$this->params=$params;return true;}
 public function fetchAll($mode){return [];}
}
class CenterPdo {
 public $sql='';public $stmt;
 public function __construct(){$this->stmt=new CenterStatement();}
 public function prepare($sql){$this->sql=$sql;return $this->stmt;}
}
$db=new CenterPdo();
kp_center_gift_history($db,48,25);
check(strpos($db->sql,'w.userid=?')!==false,'giftcode ownership filter');
check(strpos($db->sql,'w.charname=g.player')!==false,'only player character logs');
check($db->stmt->params===[48],'gift user parameter');
kp_center_notifications($db,48);
check(strpos($db->sql,'WHERE user_id=?')!==false,'notification owner');
check($db->stmt->params===[48],'notification user parameter');
kp_center_security($db,48);
check(strpos($db->sql,'WHERE user_id=?')!==false,'security event owner');
check($db->stmt->params===[48],'security user parameter');
check(strpos(file_get_contents(KP_ROOT.'/app/pages/notifications.php'),'kp_csrf_check')!==false,'notification POST csrf');
$noticeSource=file_get_contents(KP_ROOT.'/app/pages/notifications.php');
check(strpos($noticeSource, '<div class="uc-alert">Không thể đánh dấu') !== false,'notification inline error survives Toast');
check(strpos($noticeSource, 'data-toast-type="error"') !== false,'notification failure toast');
$trackerSource=file_get_contents(KP_ROOT.'/app/pages/order-tracker.php');
check(strpos($trackerSource, "['completed','wallet_credited']") !== false,'only fulfilled payment states finish card');
check(strpos($trackerSource, "verified_pending_delivery") !== false,'verified card not confused with delivered card');
check(strpos(file_get_contents(KP_ROOT.'/assets/js/toast.js'),'textContent=') !== false,'Toast uses text only for untrusted status');
$eventSource=file_get_contents(KP_ROOT.'/app/includes/user_center.php');
check(strpos($eventSource, 'mb_convert_encoding') !== false,'security history sanitizes browser header');
check(kp_center_safe_path('/lich-su-bao-mat')===true,'internal notification link allowed');
foreach (['//attacker.example','/\\\\attacker.example','https://attacker.example','/thong-bao?return=//attacker.example','/quan-tri'] as $badLink) {
 check(kp_center_safe_path($badLink)===false,'untrusted notification link blocked');
}
check(strpos(file_get_contents(KP_ROOT.'/app/pages/notifications.php'),'kp_center_safe_path')!==false,'renderer verifies internal links');
foreach (['app/pages/my-giftcodes.php','app/pages/notifications.php','app/pages/order-tracker.php','app/pages/security-history.php'] as $p) {
 $html=file_get_contents(KP_ROOT.'/'.$p);
 check(strpos($html,'uc-record-art')!==false,'custom artwork in '.$p);
 check(strpos($html,'uc-empty-illustrated')!==false,'empty-state artwork in '.$p);
}
$notice=file_get_contents(KP_ROOT.'/app/pages/notifications.php');
check(strpos($notice,'if($unreadCount>0)')!==false,'read-all only offered with unread items');
$orders=file_get_contents(KP_ROOT.'/app/pages/order-tracker.php');
check(strpos($orders,'uc-state-pill')!==false,'payment status presentation uses backend states');
echo "User center smoke PASS\\n";
