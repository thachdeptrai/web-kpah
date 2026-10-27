<?php
// End-to-end Point workflow against the disposable CI database only.
if(getenv('KPAH_DB_NAME')!=='kpah_route_test'){fwrite(STDERR,"Refusing non-test database\n");exit(1);}
require dirname(__DIR__).'/app/includes/bootstrap.php';
require_once KP_ROOT.'/app/includes/loyalty_points.php';
require_once KP_ROOT.'/app/includes/points_reward_display.php';
$db=kp_db();
foreach([KP_ROOT.'/database/loyalty_points.sql',KP_ROOT.'/database/user_center.sql',KP_ROOT.'/database/points_stage1_reward_display.sql'] as $file){
    $sql=preg_replace('/^--.*$/m','',file_get_contents($file));
    foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql) as $statement)if(trim($statement)!=='')$db->exec($statement);
}
function point_ok($condition,$message){if(!$condition)throw new RuntimeException('FAIL: '.$message);echo "PASS: $message\n";}

$db->exec('DELETE FROM kp_admin_notifications');$db->exec('DELETE FROM kp_user_notifications');$db->exec('DELETE FROM kp_points_ledger');
$db->exec('DELETE FROM kp_points_redemptions');$db->exec('DELETE FROM kp_points_rewards');$db->exec('DELETE FROM kp_points_wallet');
$db->prepare('INSERT INTO kp_points_wallet (user_id,balance,total_earned,total_spent) VALUES (101,1000,1000,0)')->execute();
$db->prepare('INSERT INTO kp_points_rewards (title,description,points_cost,stock,delivery_type,is_active) VALUES (?,?,?,?,?,1)')
   ->execute(['Quà kiểm thử','Chỉ dùng trong CI',200,3,'manual']);
$reward=(int)$db->lastInsertId();
$display=kp_point_reward_display_input(['icon_id'=>'123','item_id'=>'456','option_lines'=>"Sức mạnh +10\nHP +500"]);
kp_point_reward_display_save($db,$reward,$display);
$stored=kp_point_reward_display_all($db,[['id'=>$reward]]);
point_ok(kp_point_reward_icon($stored[$reward])==='/res/icon/123.png','stage 1 reward image comes from numeric game icon');
point_ok(count(kp_point_reward_option_lines($stored[$reward]))===2,'stage 1 option details stored and read');
point_ok($stored[$reward]['item_id']==456,'stage 1 item ID saved only as metadata');
point_ok(kp_point_reward_icon(['icon_id'=>'javascript:alert(1)'])==='','unsafe icon protocol rejected');
$token='11111111111111111111111111111111';
$redemption=kp_points_redeem(101,$reward,$token);
point_ok((int)$db->query('SELECT balance FROM kp_points_wallet WHERE user_id=101')->fetchColumn()===800,'pending redemption reserves Point once');
point_ok((int)$db->query("SELECT COUNT(*) FROM kp_admin_notifications WHERE is_read=0")->fetchColumn()===1,'admin receives pending order notification');
point_ok((int)$db->query("SELECT COUNT(*) FROM kp_user_notifications WHERE user_id=101 AND category='gift'")->fetchColumn()===1,'user receives pending order notification');
point_ok(kp_points_redeem(101,$reward,$token)===$redemption,'redemption request token is idempotent');
point_ok((int)$db->query('SELECT balance FROM kp_points_wallet WHERE user_id=101')->fetchColumn()===800,'redemption replay cannot deduct twice');

kp_points_review_redemption($redemption,1,'rejected','Không còn suất sự kiện');
point_ok((int)$db->query('SELECT balance FROM kp_points_wallet WHERE user_id=101')->fetchColumn()===1000,'rejection refunds reserved Point');
point_ok((int)$db->query('SELECT stock FROM kp_points_rewards WHERE id='.$reward)->fetchColumn()===3,'rejection restores reward stock');
$blocked=false;try{kp_points_review_redemption($redemption,1,'rejected','Gửi lại');}catch(RuntimeException $e){$blocked=true;}
point_ok($blocked,'reviewed redemption cannot be processed twice');

$adjust='22222222222222222222222222222222';
point_ok(kp_points_adjust(101,1,120,'Bù sự kiện',$adjust)===true,'admin can append a Point credit');
point_ok(kp_points_adjust(101,1,120,'Bù sự kiện',$adjust)===false,'admin adjustment replay is idempotent');
point_ok((int)$db->query('SELECT balance FROM kp_points_wallet WHERE user_id=101')->fetchColumn()===1120,'Point adjustment changes balance exactly once');
point_ok(kp_points_adjust(101,1,-20,'Thu hồi nhập nhầm','33333333333333333333333333333333')===true,'admin can append a safe Point debit');

$redemption2=kp_points_redeem(101,$reward,'44444444444444444444444444444444');
kp_points_review_redemption($redemption2,1,'fulfilled','Đã cộng quà trong game');
point_ok($db->query('SELECT status FROM kp_points_redemptions WHERE id='.$redemption2)->fetchColumn()==='fulfilled','approval finalizes pending reward');
point_ok((int)$db->query("SELECT COUNT(*) FROM kp_user_notifications WHERE user_id=101 AND title='Quà đã được duyệt và cộng'")->fetchColumn()===1,'approval notifies the correct user');
echo "Point workflow integration PASS\n";
