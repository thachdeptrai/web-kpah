<?php
// php tests/farmer_history_smoke.php — static route + permission review.
define('KP_ROOT',dirname(__DIR__));
$routes=require KP_ROOT.'/config/routes.php';
$history=$routes['routes']['/quan-tri/diem-nong-dan/lich-su']??null;
if(!$history||$history['scope']!=='admin'||$history['file']!=='app/admin/farmer-history.php')exit(1);
$page=file_get_contents(KP_ROOT.'/'.$history['file']);
if(strpos($page,'kp_admin_require();')===false)exit(1);
if(strpos($page,'kp_farmer_monitor_hourly(')===false)exit(1);
if(strpos($page,'kp_farmer_monitor_ready(')===false)exit(1);
if(strpos($page,'Biến động Lượng theo từng giờ')===false)exit(1);
if(preg_match('/(?:UPDATE|DELETE|INSERT INTO)\s+tob_char\b/i',$page))exit(1);
echo "Farmer history admin route / read-only: OK\n";
