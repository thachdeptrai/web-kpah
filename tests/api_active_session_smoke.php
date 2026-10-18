<?php
/** Offline checks for active-user guards used by JSON payment APIs. */
define('KP_ROOT', dirname(__DIR__));
final class ApiMockRow {
    private $value;
    public function __construct($value) { $this->value=$value; }
    public function execute(array $args) { $GLOBALS['api_seen_args']=$args; }
    public function fetchColumn() { return $this->value; }
}
final class ApiMockDb {
    public $value=0;
    public $fail=false;
    public function prepare($sql) {
        if ($this->fail) throw new RuntimeException('mock DB offline');
        if (strpos($sql, 'WHERE id=? AND ban=0')===false) throw new RuntimeException('Missing ban guard');
        return new ApiMockRow($this->value);
    }
}
$GLOBALS['api_mock_db']=new ApiMockDb();
function kp_db() { return $GLOBALS['api_mock_db']; }
function api_guard_assert($condition,$message) {
    if (!$condition) {fwrite(STDERR,'FAIL: '.$message.PHP_EOL);exit(1);}
}
require KP_ROOT.'/app/includes/security.php';
$_SESSION=[];
api_guard_assert(kp_player_api_user_id()===0,'guest denied');
$_SESSION=['kp_user_id'=>11,'kp_user_last_seen'=>time()-1900,'kp_user_username'=>'fixture'];
$GLOBALS['api_mock_db']->value=11;
api_guard_assert(kp_player_api_user_id()===0,'expired session denied');
api_guard_assert(!isset($_SESSION['kp_user_id']),'expired session user cleared');
$_SESSION=['kp_user_id'=>11,'kp_user_last_seen'=>time()-20,'kp_user_username'=>'fixture'];
$GLOBALS['api_mock_db']->value=0;
api_guard_assert(kp_player_api_user_id()===0,'banned or deleted account denied');
api_guard_assert(!isset($_SESSION['kp_user_id']),'banned session user cleared');
$_SESSION=['kp_user_id'=>11,'kp_user_last_seen'=>time()-20,'kp_user_username'=>'fixture'];
$GLOBALS['api_mock_db']->value=11;
api_guard_assert(kp_player_api_user_id()===11,'active user allowed');
api_guard_assert($GLOBALS['api_seen_args']===[11],'session ID used for parameter binding');
api_guard_assert($_SESSION['kp_user_last_seen']>=time()-2,'valid session refreshed');
$GLOBALS['api_mock_db']->fail=true;
$threw=false;
try {kp_player_api_user_id();} catch(RuntimeException $e) {$threw=true;}
api_guard_assert($threw,'DB failure does not grant access');
foreach (['app/topup/card-fees.php','app/topup/bank-status.php'] as $file) {
    $content=file_get_contents(KP_ROOT.'/'.$file);
    api_guard_assert(strpos($content,'kp_player_api_user_id()')!==false,
        'API uses active user guard: '.$file);
    api_guard_assert(strpos($content,'http_response_code(401)')!==false,
        'API has unauthorized response: '.$file);
}
echo "JSON API active session and ban security PASS\n";
