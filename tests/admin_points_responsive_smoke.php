<?php
/** Admin Point page responsive contract, safe to run without database. */
$root=dirname(__DIR__);
function ar_ok($ok,$name){if(!$ok){fwrite(STDERR,'FAIL: admin points '.$name.PHP_EOL);exit(1);}echo 'PASS: admin points '.$name.PHP_EOL;}
function ar_src($path){global $root;$s=@file_get_contents($root.'/'.$path);ar_ok(is_string($s),'file exists '.$path);return $s;}
$page=ar_src('app/topup/admin/points.php');
$layout=ar_src('app/topup/_layout.php');
$css=ar_src('assets/css/admin-points-responsive.css');
$picker=ar_src('assets/css/point-reward-picker.css');
foreach(['point-admin-jump','point-admin-orders-table','data-label="Thao tác"','point-admin-scroll','point-admin-reconcile','point-admin-orders','name="csrf"','name="redemption_id"'] as $str) ar_ok(strpos($page,$str)!==false,'markup '.$str);
ar_ok(strpos($page,'kp_admin_csrf_valid(')!==false && strpos($page,'$admin=kp_topup_admin()')!==false,'server permissions preserved');
ar_ok(strpos($page,'delete_reward')!==false && strpos($page,'name="confirm_delete" value="DELETE"')!==false,'explicit POST delete confirmation');
ar_ok(strpos($page,'kp_points_admin_delete_reward($db,$id)')!==false,'delete server helper integrated');
ar_ok(strpos($page,'point-reward-delete-locked')!==false,'historical gifts cannot be deleted through UI');
ar_ok(strpos($css,'.point-reward-delete-confirm')!==false && strpos($css,'.point-reward-delete-submit')!==false,'responsive delete UI');
ar_ok(strpos($layout,'tp-admin-points')!==false && strpos($layout,'/assets/css/admin-points-responsive.css?v=')!==false,'scoped style loaded for admin Point only');
foreach(['max-width:1499px','max-width:1190px','max-width:800px','max-width:700px','max-width:600px','max-width:360px','prefers-reduced-motion','point-admin-orders-table td:before','point-picker-dialog','overflow-x:auto'] as $str)ar_ok(strpos($css,$str)!==false,'responsive style '.$str);
ar_ok(strpos($picker,'.point-picker-dialog')!==false,'Point picker dialog stylesheet shared with admin');
ar_ok(strpos($picker,'#point-reward-picker [hidden]{display:none!important}')!==false,
  'hidden Item and Option panels cannot be overridden by display grid');
ar_ok(strpos($page,'aria-controls="point-picker-options"')!==false
 && strpos($page,'id="point-picker-options"')!==false,
  'option tab points to the real Options panel');
echo "PASS: Point admin static responsive suite\n";
