<?php
/** No database needed: membership gallery/css regression. */
$root=dirname(__DIR__);
function member_ok($ok,$why){if(!$ok)throw new RuntimeException('membership: '.$why);}
function member_src($path){global $root;$s=@file_get_contents($root.'/'.$path);member_ok(is_string($s),'missing '.$path);return $s;}
define('KP_ROOT',$root);
require $root.'/app/includes/points_tiers.php';
$tiers=kp_points_tiers();
member_ok(count($tiers)===6,'six ranks');
member_ok(array_column($tiers,'threshold')===[0,501,2001,5001,10001,20001],'concept rank thresholds');
member_ok(count(array_unique(array_column($tiers,'image')))===6,'unique local badges');
foreach($tiers as $tier){
 $image=$root.$tier['image'];
 member_ok(is_file($image),'badge missing: '.$image);
 $signature=file_get_contents($image,false,null,0,12);
 member_ok(substr($signature,0,4)==='RIFF' && substr($signature,8,4)==='WEBP','real WEBP '.$image);
}
$layout=member_src('app/topup/_layout.php');
member_ok(strpos($layout,'point-membership.css')!==false,'shared stylesheet for user and admin');
$css=member_src('assets/css/point-membership.css');
foreach(['repeat(6,minmax(0,1fr))','repeat(3,minmax(0,1fr))','repeat(2,minmax(0,1fr))','max-width:360px','prefers-reduced-motion'] as $needle)
 member_ok(strpos($css,$needle)!==false,'breakpoint '.$needle);
$page=member_src('app/topup/points.php');
member_ok(strpos($page,'point-tier-chip--')!==false && strpos($page,'aria-current="true"')!==false,'rank active marker');
member_ok(strpos($page,'kp_points_member_tier($earned)')!==false,'server-side membership calculation retained');
echo "PASS: six WEBP ranks and responsive user/admin styling\n";
