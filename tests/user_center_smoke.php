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
echo "User center smoke PASS\n";
