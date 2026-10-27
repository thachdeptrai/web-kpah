<?php
/**
 * Static + pure-function security regression (CI only, no live endpoints).
 * Replays hostile display values to ensure server-side output encoding and
 * avoids dangerous JavaScript execution and client-owned authorization.
 */
define('KP_ROOT', dirname(__DIR__));
require KP_ROOT.'/app/includes/points_reward_display.php';
require KP_ROOT.'/app/includes/points_tiers.php';
require KP_ROOT.'/app/includes/points_audit.php';
function points_safe($ok,$description){if(!$ok)throw new RuntimeException('Point security: '.$description);}
try {
 $display=kp_point_reward_display_input(['icon_id'=>'22','item_id'=>'11',
     'option_lines'=>"<img src=x onerror=alert(1)>\n<script>alert(2)</script>"]);
 points_safe($display['icon_id']===22,'only numeric icon IDs accepted');
 points_safe(kp_point_reward_icon($display)==='/res/icon/22.png','only local game icon URLs constructed');
 points_safe(kp_point_reward_icon(['icon_id'=>'javascript:alert(1)'])==='','unsafe image URL is blocked');
 points_safe(count(kp_point_reward_option_lines($display))===2,'dangerous options stay plain text for PHP escaping');
 foreach(['<svg/onload=alert(1)>','data:image/svg+xml;base64,YQ==','99999999'] as $v){
  try{kp_point_reward_display_input(['icon_id'=>$v]);points_safe(false,'bad icon accepted '.$v);}
  catch(RuntimeException $expected){}
 }
 points_safe(kp_points_member_tier(0)['id']===0 &&
  kp_points_member_tier(1000)['id']===1 &&
  kp_points_member_tier(5000)['id']===2 &&
  kp_points_member_tier(15000)['id']===3,'tier boundaries are deterministic');
 $ctx=kp_points_audit_context(['REMOTE_ADDR'=>'192.0.2.10',
    'HTTP_USER_AGENT'=>'Mozilla/5.0 (iPhone; CPU iPhone OS 18 like Mac OS X) Mobile/15 Safari/604',
    'HTTP_X_FORWARDED_FOR'=>'203.0.113.13','HTTP_CF_CONNECTING_IP'=>'203.0.113.13',
    'GEOIP_CITY'=>'<script>alert(1)</script>']);
 points_safe($ctx['ip']==='192.0.2.10' && $ctx['device']==='Điện thoại'
    && $ctx['location']==='','X-Forwarded spoof and GeoIP markup rejected');
 $root=dirname(__DIR__);
 $player=(string)file_get_contents($root.'/app/topup/points.php');
 $admin=(string)file_get_contents($root.'/app/topup/admin/points.php');
 $core=(string)file_get_contents($root.'/app/includes/loyalty_points.php');
 $js=(string)file_get_contents($root.'/assets/js/points-guard.js');
 points_safe(strpos($player,'$user=kp_topup_user()')!==false
    && strpos($player,'kp_points_audit_list($db,$userId')!==false,
    'player audit view only uses authenticated owner ID');
 points_safe(strpos($admin,'$admin=kp_topup_admin()')!==false
    && strpos($admin,'kp_admin_csrf_valid(')!==false,
    'admin mutation requires role-bound session and CSRF');
 points_safe(strpos($core,'kp_points_validate_character')!==false
    && strpos($core,'kp_points_reward_min_tier')!==false,
    'character ownership and rank verified on backend');
 points_safe(strpos($player,'<?=kp_e($opt)?>')!==false
    && strpos($admin, "<?=kp_e(\$a['device_type']")!==false,
    'option rendering is HTML-escaped and audit values are not interpolated as JS');
 points_safe(!preg_match('/\b(?:eval|new Function|document\.write|innerHTML)\b/',$js),
    'client Point guard avoids executable HTML and evaluation sinks');
 points_safe(strpos($js,'checkValidity()')!==false,
    'JS click guard is only UX; PHP owns all authorization');
 echo "Point security / JS injection / device spoof / rank test PASS\n";
} catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
