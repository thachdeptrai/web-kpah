<?php
// End-to-end Point workflow against the disposable CI database only.
if(getenv('KPAH_DB_NAME')!=='kpah_route_test'){fwrite(STDERR,"Refusing non-test database\n");exit(1);}
require dirname(__DIR__).'/app/includes/bootstrap.php';
require_once KP_ROOT.'/app/includes/loyalty_points.php';
require_once KP_ROOT.'/app/includes/points_reward_display.php';
require_once KP_ROOT.'/app/includes/points_reconciliation.php';
require_once KP_ROOT.'/app/includes/points_reward_admin.php';
$db=kp_db();
if(getenv('KPAH_POINTS_DB_NAME')==='kpah_route_points_test'){
 $schema=(string)$db->query('SELECT DATABASE()')->fetchColumn();
 if($schema!=='kpah_route_test')throw new RuntimeException('The game database was changed by Point routing');
}

foreach([KP_ROOT.'/database/loyalty_points.sql',KP_ROOT.'/database/user_center.sql',KP_ROOT.'/database/points_stage1_reward_display.sql',KP_ROOT.'/database/points_stage2_redemption_events.sql',KP_ROOT.'/database/points_stage3_redemption_targets.sql',KP_ROOT.'/database/points_stage4_audit.sql',KP_ROOT.'/database/points_stage6_tiers.sql',KP_ROOT.'/database/points_stage7_redemption_control.sql'] as $file){
    $sql=preg_replace('/^--.*$/m','',file_get_contents($file));
    foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql) as $statement)if(trim($statement)!=='')$db->exec(kp_points_sql($statement));
}
function point_ok($condition,$message){if(!$condition)throw new RuntimeException('FAIL: '.$message);echo "PASS: $message\n";}
if(getenv('KPAH_POINTS_DB_NAME')==='kpah_route_points_test'){
 $st=$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='kpah_route_points_test' AND TABLE_NAME='kp_points_wallet'");
 point_ok((int)$st->fetchColumn()===1,'wallet table created in isolated Point schema');
 $st=$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='kpah_route_test' AND TABLE_NAME='kp_points_wallet'");
 point_ok((int)$st->fetchColumn()===0,'Point wallet is NOT created in the game schema');
}


$db->exec('DELETE FROM kp_admin_notifications');$db->exec('DELETE FROM kp_user_notifications');$db->exec(kp_points_sql('DELETE FROM kp_points_ledger'));
$db->exec(kp_points_sql('DELETE FROM kp_points_redemption_events'));$db->exec(kp_points_sql('DELETE FROM kp_points_redemption_targets'));$db->exec(kp_points_sql('DELETE FROM kp_points_audit'));$db->exec(kp_points_sql('DELETE FROM kp_points_reward_tiers'));$db->exec(kp_points_sql('DELETE FROM kp_points_redemptions'));$db->exec(kp_points_sql('DELETE FROM kp_points_rewards'));$db->exec(kp_points_sql('DELETE FROM kp_points_wallet'));
point_ok(kp_points_redemption_control_ready($db),'stage 7 global switch table is installed');
point_ok(kp_points_redemption_enabled($db),'stage 7 defaults to enabled');
point_ok(kp_points_redemption_set_enabled($db,false,1),'admin can disable Point redemption');
point_ok(!kp_points_redemption_enabled($db),'stage 7 off persists in SQL');
point_ok(!kp_points_redemption_set_enabled($db,false,1),'stage 7 repeated disable is idempotent');
$walletBefore=(int)$db->query(kp_points_sql('SELECT COUNT(*) FROM kp_points_wallet'))->fetchColumn();
$redemptionsBefore=(int)$db->query(kp_points_sql('SELECT COUNT(*) FROM kp_points_redemptions'))->fetchColumn();
$blockedMaintenance=false;
try{kp_points_redeem(101,9999,'55555555555555555555555555555555',101);}
catch(RuntimeException $e){$blockedMaintenance=$e->getMessage()==='Chức năng hiện đang cập nhật';}
point_ok($blockedMaintenance,'new requests cannot bypass disabled switch through direct backend');
point_ok((int)$db->query(kp_points_sql('SELECT COUNT(*) FROM kp_points_wallet'))->fetchColumn()===$walletBefore
 && (int)$db->query(kp_points_sql('SELECT COUNT(*) FROM kp_points_redemptions'))->fetchColumn()===$redemptionsBefore,
 'maintenance-mode rejection does not create wallet or redemption rows');
point_ok(kp_points_redemption_set_enabled($db,true,1),'admin can re-enable new redemptions');
point_ok(kp_points_redemption_enabled($db),'stage 7 enabled state restores normal flow');
$badAdmin=false;try{kp_points_redemption_set_enabled($db,false,0);}catch(InvalidArgumentException $e){$badAdmin=true;}
point_ok($badAdmin,'invalid admin cannot switch redemption');
$db->prepare(kp_points_sql('INSERT INTO kp_points_wallet (user_id,balance,total_earned,total_spent) VALUES (101,1000,1000,0)'))->execute();
$db->prepare(kp_points_sql('INSERT INTO kp_points_rewards (title,description,points_cost,stock,delivery_type,is_active) VALUES (?,?,?,?,?,1)'))
   ->execute(['Quà kiểm thử','Chỉ dùng trong CI',200,3,'manual']);
$reward=(int)$db->lastInsertId();
$display=kp_point_reward_display_input(['icon_id'=>'123','item_id'=>'456','option_lines'=>"Sức mạnh +10\nHP +500"]);
kp_point_reward_display_save($db,$reward,$display);
$stored=kp_point_reward_display_all($db,[['id'=>$reward]]);
point_ok(kp_point_reward_icon($stored[$reward])==='/res/icon/123.png','stage 1 reward image comes from numeric game icon');
point_ok(count(kp_point_reward_option_lines($stored[$reward]))===2,'stage 1 option details stored and read');
point_ok($stored[$reward]['item_id']==456,'stage 1 item ID saved only as metadata');
point_ok(kp_point_reward_icon(['icon_id'=>'javascript:alert(1)'])==='','unsafe icon protocol rejected');
point_ok(kp_points_member_tier(1000)['name']==='Bạc' && kp_points_member_tier(5000)['name']==='Vàng',
 'stage 6 tier based on total earned Point, not spendable balance');
kp_points_reward_set_tier($db,$reward,2);
point_ok(kp_points_reward_min_tier($db,$reward)===2,'stage 6 saves minimum required rank');
$notHighEnough=false;
try{kp_points_redeem(101,$reward,'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee',101);}catch(RuntimeException $e){$notHighEnough=true;}
point_ok($notHighEnough,'stage 6 blocks player of insufficient tier before charging Point');
kp_points_reward_set_tier($db,$reward,1);
$token='11111111111111111111111111111111';
$blocked=false;try{kp_points_redeem(101,$reward,'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',202);}catch(RuntimeException $e){$blocked=true;}
point_ok($blocked,'stage 3 blocks a different account character before spending Point');
$redemption=kp_points_redeem(101,$reward,$token,101);
$recipient=$db->query(kp_points_sql('SELECT char_id,char_name FROM kp_points_redemption_targets WHERE redemption_id='.$redemption))->fetch(PDO::FETCH_ASSOC);
point_ok($recipient && (int)$recipient['char_id']===101,'stage 3 saves the selected recipient within the transaction');
$auditRecord=$db->query(kp_points_sql("SELECT user_id,operation,points_delta,balance_before,balance_after FROM kp_points_audit WHERE operation='redeem' ORDER BY id DESC LIMIT 1"))->fetch(PDO::FETCH_ASSOC);
point_ok($auditRecord && (int)$auditRecord['user_id']===101 && (int)$auditRecord['points_delta']===-200
  && (int)$auditRecord['balance_before']===1000 && (int)$auditRecord['balance_after']===800,
  'stage 4 stores auditable Point transfer within transaction');
point_ok((int)$db->query(kp_points_sql('SELECT balance FROM kp_points_wallet WHERE user_id=101'))->fetchColumn()===800,'pending redemption reserves Point once');
point_ok((int)$db->query("SELECT COUNT(*) FROM kp_admin_notifications WHERE is_read=0")->fetchColumn()===1,'admin receives pending order notification');
point_ok((int)$db->query("SELECT COUNT(*) FROM kp_user_notifications WHERE user_id=101 AND category='gift'")->fetchColumn()===1,'user receives pending order notification');
point_ok(kp_points_redemption_set_enabled($db,false,1),'temporary disable during existing redemption');
point_ok(kp_points_redeem(101,$reward,$token,101)===$redemption,
 'already-created request stays idempotent during maintenance');
$balancePaused=(int)$db->query(kp_points_sql('SELECT balance FROM kp_points_wallet WHERE user_id=101'))->fetchColumn();
$blockedNew=false;
try{kp_points_redeem(101,$reward,'66666666666666666666666666666666',101);}
catch(RuntimeException $e){$blockedNew=$e->getMessage()==='Chức năng hiện đang cập nhật';}
point_ok($blockedNew,'new redemption is rejected for an otherwise valid reward while disabled');
point_ok((int)$db->query(kp_points_sql('SELECT balance FROM kp_points_wallet WHERE user_id=101'))->fetchColumn()===$balancePaused,
 'disabled redemption never deducts Point');
point_ok(kp_points_redemption_set_enabled($db,true,1),'reopening restores ability to redeem');
point_ok(kp_points_redeem(101,$reward,$token,101)===$redemption,'redemption request token is idempotent');
point_ok((int)$db->query(kp_points_sql('SELECT balance FROM kp_points_wallet WHERE user_id=101'))->fetchColumn()===800,'redemption replay cannot deduct twice');

kp_points_review_redemption($redemption,1,'rejected','Không còn suất sự kiện');
point_ok((int)$db->query(kp_points_sql('SELECT balance FROM kp_points_wallet WHERE user_id=101'))->fetchColumn()===1000,'rejection refunds reserved Point');
point_ok((int)$db->query(kp_points_sql('SELECT stock FROM kp_points_rewards WHERE id='.$reward))->fetchColumn()===3,'rejection restores reward stock');
$blocked=false;try{kp_points_review_redemption($redemption,1,'rejected','Gửi lại');}catch(RuntimeException $e){$blocked=true;}
point_ok($blocked,'reviewed redemption cannot be processed twice');

$adjust='22222222222222222222222222222222';
point_ok(kp_points_adjust(101,1,120,'Bù sự kiện',$adjust)===true,'admin can append a Point credit');
point_ok(kp_points_adjust(101,1,120,'Bù sự kiện',$adjust)===false,'admin adjustment replay is idempotent');
point_ok((int)$db->query(kp_points_sql('SELECT balance FROM kp_points_wallet WHERE user_id=101'))->fetchColumn()===1120,'Point adjustment changes balance exactly once');
point_ok(kp_points_adjust(101,1,-20,'Thu hồi nhập nhầm','33333333333333333333333333333333')===true,'admin can append a safe Point debit');

$redemption2=kp_points_redeem(101,$reward,'44444444444444444444444444444444',101);
$early=false;try{kp_points_review_redemption($redemption2,1,'fulfilled','Chưa giao nhưng muốn bỏ qua');}catch(RuntimeException $e){$early=true;}
point_ok($early,'stage 2 blocks confirming delivery before approval and delivery');
kp_points_review_redemption($redemption2,1,'approved','Đã kiểm tra đủ thông tin');
point_ok($db->query(kp_points_sql('SELECT status FROM kp_points_redemptions WHERE id='.$redemption2))->fetchColumn()==='approved','approval does not falsely claim gift delivered');
kp_points_review_redemption($redemption2,1,'delivering','Đã phân công Admin phát quà');
$noRefund=false;try{kp_points_review_redemption($redemption2,1,'rejected','Đã phát rồi');}catch(RuntimeException $e){$noRefund=true;}
point_ok($noRefund,'stage 2 prohibits automatic refund once delivery has started');
kp_points_review_redemption($redemption2,1,'fulfilled','Đã cộng quà trong game');
point_ok((int)$db->query(kp_points_sql('SELECT COUNT(*) FROM kp_points_redemption_events WHERE redemption_id='.$redemption2))->fetchColumn()===3,'stage 2 logs each transition within Point transaction');
point_ok($db->query(kp_points_sql('SELECT status FROM kp_points_redemptions WHERE id='.$redemption2))->fetchColumn()==='fulfilled','approval finalizes pending reward');
point_ok((int)$db->query("SELECT COUNT(*) FROM kp_user_notifications WHERE user_id=101 AND title='Quà đã được xác nhận giao'")->fetchColumn()===1,'actual delivery notifies the correct user');
point_ok((int)$db->query(kp_points_sql("SELECT COUNT(*) FROM kp_points_audit WHERE operation IN ('approved','delivering','fulfilled')"))->fetchColumn()===3,
 'stage 4 includes all admin delivery decisions');
$_SERVER['HTTP_X_FORWARDED_FOR']='1.2.3.4';
$ctx=kp_points_audit_context(['REMOTE_ADDR'=>'203.0.113.19','HTTP_X_FORWARDED_FOR'=>'1.2.3.4','HTTP_USER_AGENT'=>'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0.0.0 Safari/537.36']);
point_ok($ctx['ip']==='203.0.113.19' && $ctx['device']==='Máy tính',
 'stage 4 does not trust spoofed forwarded IP or device claims');
$beforeAuditCount=(int)$db->query(kp_points_sql('SELECT COUNT(*) FROM kp_points_ledger'))->fetchColumn();
$beforeWalletBalance=(int)$db->query(kp_points_sql('SELECT balance FROM kp_points_wallet WHERE user_id=101'))->fetchColumn();
$rec=kp_points_reconcile($db);
point_ok(isset($rec['wallet_mismatch'],$rec['overdue_orders'],$rec['missing_payos_ledger']),
 'stage 5 reconciliation returns stable read-only result categories');
point_ok($beforeAuditCount===(int)$db->query(kp_points_sql('SELECT COUNT(*) FROM kp_points_ledger'))->fetchColumn()
 && $beforeWalletBalance===(int)$db->query(kp_points_sql('SELECT balance FROM kp_points_wallet WHERE user_id=101'))->fetchColumn(),
 'stage 5 never writes wallet or ledger');

// Removing a catalog gift is legal only before its first redemption.
$refused=false;
try { kp_points_admin_delete_reward($db,$reward); }
catch(RuntimeException $e) { $refused=strpos($e->getMessage(),'lịch sử đổi')!==false; }
point_ok($refused,'gift with a historical redemption cannot be permanently deleted');
point_ok((int)$db->query(kp_points_sql('SELECT COUNT(*) FROM kp_points_rewards WHERE id='.$reward))->fetchColumn()===1,
 'historical gift stays in catalog for replay and order audit');
point_ok((int)$db->query(kp_points_sql('SELECT COUNT(*) FROM kp_points_redemptions WHERE reward_id='.$reward))->fetchColumn()===2,
 'rejected and delivered redemption records remain unchanged');

$db->prepare(kp_points_sql('INSERT INTO kp_points_rewards (title,description,points_cost,stock,delivery_type,is_active) VALUES (?,?,?,?,?,1)'))
   ->execute(['Quà nháp có thể xóa','Kiểm thử xóa khi chưa đổi',300,4,'manual']);
$draftId=(int)$db->lastInsertId();
kp_point_reward_display_save($db,$draftId,['icon_id'=>42,'item_id'=>11,'option_lines'=>'HP +100']);
kp_points_reward_set_tier($db,$draftId,3);
$invalid=false;
try {kp_points_admin_delete_reward($db,0);}catch(InvalidArgumentException $e){$invalid=true;}
point_ok($invalid,'delete rejects invalid gift ID');
point_ok(kp_points_admin_delete_reward($db,$draftId)==='Quà nháp có thể xóa','unused gift can be deleted');
foreach(['kp_points_rewards'=>'id','kp_points_reward_display'=>'reward_id','kp_points_reward_tiers'=>'reward_id'] as $table=>$column){
 $st=$db->prepare(kp_points_sql('SELECT COUNT(*) FROM '.$table.' WHERE '.$column.'=?'));$st->execute([$draftId]);
 point_ok((int)$st->fetchColumn()===0,'deletion removes unused gift and related '.$table);
}
$missing=false;
try {kp_points_admin_delete_reward($db,$draftId);}catch(RuntimeException $e){$missing=true;}
point_ok($missing,'deletion replay cannot remove an already deleted gift');
echo "Point workflow integration PASS\n";
