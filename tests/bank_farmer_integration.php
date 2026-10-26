<?php
/**
 * Disposable DB integration for verified payOS farmer gifts.
 * Absolutely refuses non-test databases; never calls payOS.
 */
if (getenv('KPAH_DB_NAME') !== 'kpah_route_test') {
    fwrite(STDERR, "Refusing to mutate non-test database\n"); exit(1);
}
require dirname(__DIR__) . '/app/includes/bootstrap.php';
require_once KP_ROOT . '/app/includes/bank_farmer_reward.php';
function gift_assert($ok, $msg) {
    if (!$ok) { fwrite(STDERR,"FAIL: ".$msg."\n");exit(1); }
    echo "PASS: ".$msg."\n";
}
function gift_refused(callable $action, $msg) {
    try { $action(); gift_assert(false,$msg); }
    catch (RuntimeException $e) { gift_assert(true,$msg); }
}
$db = kp_db();
// Regression: multiple claim rows for one account previously left holes in
// array_unique() numeric keys, triggering PDO SQLSTATE[HY093] on name lookup.
$historyUserIds=kp_bank_farmer_history_user_ids([
    ['user_id'=>101],['user_id'=>101],['user_id'=>202],
    ['user_id'=>202],['user_id'=>101]
]);
gift_assert($historyUserIds===[101,202], 'claim history produces dense unique owner IDs');
$ownersSql=implode(',',array_fill(0,count($historyUserIds),'?'));
$ownersStmt=$db->prepare('SELECT id,username FROM team_user WHERE id IN ('.$ownersSql.')');
$ownersStmt->execute($historyUserIds);
$ownerRows=$ownersStmt->fetchAll(PDO::FETCH_ASSOC);
gift_assert(count($ownerRows)===2, 'claim history username enrichment supports repeated owner IDs');
$sql = preg_replace('/^--.*$/m', '', file_get_contents(KP_ROOT . '/database/payos_bank.sql'));
foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
    if (trim($statement) !== '') $db->exec($statement);
}
$code = random_int(300000000, 899999999);
$amount = 10000;
$giftFile = kp_bank_farmer_path($code);
$walletFile = kp_vnd_record_path('payos', (string)$code);
$before = $db->query('SELECT pInfo FROM tob_char WHERE id=101')->fetchColumn();
$otherBefore = $db->query('SELECT pInfo FROM tob_char WHERE id=202')->fetchColumn();
gift_assert(kp_farmer_points((string)$before) === 777,'test character begins with 777 farmer points');
try {
    $db->prepare("INSERT INTO kp_bank_orders(order_code,user_id,char_id,username,amount,currency,units,status,provider_reference)
      VALUES (?,101,0,'routeplayer',10000,'luong',0,'wallet_pending','VND_BONUS:10')")->execute([$code]);
    kp_bank_farmer_reserve($code,101,$amount,['enabled'=>true,'per_10k'=>10000]);
    $record=kp_topup_read_file($giftFile);
    gift_assert(!kp_bank_farmer_paid($db,$record),'pending payment is not eligible');
    gift_refused(function()use($code){kp_bank_farmer_claim(101,$code,101);},'cannot claim before payOS confirms payment');
    $db->prepare("UPDATE kp_bank_orders SET status='completed' WHERE order_code=?")->execute([$code]);
    gift_assert(!kp_bank_farmer_paid($db,$record),'completed order without wallet journal is still ineligible');
    gift_refused(function()use($code){kp_bank_farmer_claim(101,$code,101);},'cannot claim without completed wallet journal');
    kp_topup_write_file($walletFile,[
        'user_id'=>101,'source'=>'payos','ref'=>(string)$code,
        'base'=>$amount,'gross'=>$amount,'bonus_percent'=>10,'status'=>'completed'
    ]);
    gift_assert(kp_bank_farmer_paid($db,$record),'verified completed bank order and wallet journal eligible');
    gift_refused(function()use($code){kp_bank_farmer_claim(202,$code,202);},'wrong account cannot claim reward');
    gift_refused(function()use($code){kp_bank_farmer_claim(101,$code,202);},'cannot claim on another account character');
    $claimed=kp_bank_farmer_claim(101,$code,101);
    gift_assert($claimed['status']==='completed' && $claimed['points']===10000
       && $claimed['char_id']===101,'real DB claim marks entitlement completed once');
    $after = $db->query('SELECT pInfo FROM tob_char WHERE id=101')->fetchColumn();
    gift_assert(kp_farmer_points((string)$after)===10777, 'only selected character receives exactly 10000 points');
    gift_refused(function()use($code){kp_bank_farmer_claim(101,$code,101);},'replayed claim is rejected');
    gift_assert($db->query('SELECT pInfo FROM tob_char WHERE id=202')->fetchColumn() === $otherBefore,
      'other player receives no points');
    gift_assert(kp_farmer_points((string)$db->query('SELECT pInfo FROM tob_char WHERE id=101')->fetchColumn())===10777,
      'replayed claim cannot increase points again');
    echo "PASS: farmer bank gift MyISAM integration and replay protection\n";
} finally {
    $db->prepare('UPDATE tob_char SET pInfo=? WHERE id=101')->execute([$before]);
    $db->prepare('DELETE FROM kp_bank_orders WHERE order_code=?')->execute([$code]);
    @unlink($giftFile);
    @unlink($giftFile.'.lock');
    @unlink($walletFile);
}
