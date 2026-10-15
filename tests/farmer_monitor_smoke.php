<?php
// Pure source checks without a production database.
define('KP_ROOT',dirname(__DIR__));
require KP_ROOT.'/app/includes/farmer_points.php';
function monitor_check($test,$label){if(!$test){fwrite(STDERR,"FAIL $label\n");exit(1);}echo "PASS $label\n";}
$schema=file_get_contents(KP_ROOT.'/database/farmer-monitor.sql');
$collector=file_get_contents(KP_ROOT.'/tools/farmer-monitor.php');
monitor_check(strpos($schema,'kp_farmer_monitor_state')!==false,'state schema');
monitor_check(strpos($schema,'kp_farmer_monitor_changes')!==false,'history schema');
monitor_check(strpos($collector,"PHP_SAPI !== 'cli'")!==false,'CLI-only collector');
monitor_check(strpos($collector,'GET_LOCK')!==false && strpos($collector,'RELEASE_LOCK')!==false,'singleton lock');
monitor_check(strpos($collector,'FOR UPDATE')!==false,'serial snapshot');
monitor_check(strpos($collector,'kp_farmer_points')!==false,'game parser');
monitor_check(strpos($collector,'$luong-$prevLuong')!==false,'currency deltas');
monitor_check(!preg_match('/(?:UPDATE|DELETE|INSERT INTO)\s+tob_char\b/i',$collector),'game data read-only');
