<?php
/* php tests/farmer-points.php — no DB connection required. */
define('KP_ROOT',dirname(__DIR__));
require KP_ROOT.'/app/includes/farmer_points.php';
function assert_point($given,$expected,$label){
    $actual=kp_farmer_points($given);
    if($actual!==$expected){
        fwrite(STDERR,"FAIL: ".$label." actual=".var_export($actual,true)."\n");
        exit(1);
    }
}
$prefix=implode(',',array_fill(0,117,'0')).',';
assert_point($prefix.'123,50,0,0,0,3600000',123,'index-117 value');
assert_point($prefix.'0,50',0,'zero is a valid value');
assert_point($prefix.'2147483647',2147483647,'maximum Java int');
assert_point($prefix.'2147483648',null,'overflow');
assert_point($prefix.'-1',null,'negative');
assert_point($prefix.'abc',null,'invalid text');
assert_point($prefix.'',null,'empty field');
assert_point(null,null,'null');
assert_point('1,2,3',null,'legacy short pInfo');
assert_point($prefix.'00012',12,'leading zeros');
if(kp_farmer_field($prefix.'123,50,0,0,0,3600000',118)!==50)exit(1);
if(kp_farmer_field($prefix.'123,50,0,0,0,3600000',122)!==3600000)exit(1);
$routes=require KP_ROOT.'/config/routes.php';
if(($routes['routes']['/quan-tri/diem-nong-dan']['scope']??'')!=='admin')exit(1);
echo "Farmer points parser and admin route: OK\n";
