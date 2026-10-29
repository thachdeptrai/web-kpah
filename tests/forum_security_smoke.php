<?php
/** Offline security regressions for Forum privacy, schema selection and error text. */
define('KP_ROOT', dirname(__DIR__));
function kp_config($name,$default='') {
    return $name==='points_db.name'?'kpah_points':$default;
}
require KP_ROOT.'/app/includes/forum_feedback.php';
function forum_sec_check($ok,string $name):void {
    if(!$ok){fwrite(STDERR,"FAIL: ".$name.PHP_EOL);exit(1);}
    echo "PASS: ".$name.PHP_EOL;
}
$generic='Có lỗi nội bộ, vui lòng thử lại.';
forum_sec_check(kp_forum_safe_error(new PDOException("SQLSTATE[42S02]: Table 'kpah.team_user' not found"),$generic)===$generic,
    'PDO SQLSTATE never exposed to player');
forum_sec_check(kp_forum_safe_error(new UnexpectedValueException('Internal path /home/app/db.php'),$generic)===$generic,
    'internal exception not rendered');
forum_sec_check(kp_forum_safe_error(new RuntimeException('Phiên bảo mật đã hết hạn.'),$generic)==='Phiên bảo mật đã hết hạn.',
    'deliberate validation warning preserved');
forum_sec_check(kp_forum_safe_error(new RuntimeException('SELECT password FROM team_user'),$generic)===$generic,
    'SQL-containing exception suppressed');
forum_sec_check(kp_forum_mask_username('testplayer123')==='t****',
    'fixed length alias');
forum_sec_check(kp_forum_mask_username('trang')===kp_forum_mask_username('testplayer123'),
    'username length not exposed');
forum_sec_check(kp_forum_redact_username('Chào testplayer123!', 'testplayer123')==='Chào t****!',
    'real username redacted from body');
$pointSchema=kp_points_schema_name();
forum_sec_check($pointSchema!=='' && preg_match('/^[A-Za-z0-9_]+$/D',$pointSchema)===1,
    'notification schema explicitly configured and valid');
forum_sec_check(kp_center_sql('SELECT id FROM kp_user_notifications WHERE user_id=?')===
    'SELECT id FROM `'.$pointSchema.'`.`kp_user_notifications` WHERE user_id=?',
    'notification query never falls back to game schema');
forum_sec_check(kp_forum_sql('SELECT id FROM kp_forum_topics WHERE id=?')===
    'SELECT id FROM `'.$pointSchema.'`.`kp_forum_topics` WHERE id=?',
    'forum query points to same isolated schema');
forum_sec_check(kp_forum_sql('SELECT username FROM team_user WHERE id=?')===
    'SELECT username FROM team_user WHERE id=?',
    'account lookup remains in game schema');
foreach(['/thong-bao','/dien-dan'] as $path)
    forum_sec_check(kp_center_safe_path($path),'safe internal path '.$path);
foreach(['//attacker.example','https://attacker.example','/dien-dan?next=//attacker.example'] as $bad)
    forum_sec_check(!kp_center_safe_path($bad),'external/link manipulation rejected');
$page=(string)file_get_contents(KP_ROOT.'/app/pages/forum.php');
$admin=(string)file_get_contents(KP_ROOT.'/app/admin/forum.php');
forum_sec_check(strpos($page,'kp_csrf_check')!==false &&
    strpos($page,'kp_rate_limit')!==false &&
    strpos($page,'kp_forum_safe_error')!==false,'user form CSRF, rate limit and safe error');
forum_sec_check(strpos($admin,'kp_admin_require')!==false &&
    strpos($admin,'kp_admin_csrf_valid')!==false &&
    strpos($admin,'kp_admin_limit')!==false &&
    strpos($admin,'kp_forum_safe_error')!==false,'admin role, CSRF, rate limit, error safety');
$src=(string)file_get_contents(KP_ROOT.'/app/includes/forum_feedback.php');
$public=substr($src,strpos($src,'function kp_forum_public_topics'),strpos($src,'function kp_forum_public_count')-strpos($src,'function kp_forum_public_topics'));
forum_sec_check(strpos($public,'team_user')===false && strpos($public,'t.user_id')===false,
    'public listing does not join or return account ownership identifiers');
forum_sec_check(strpos($src,'$db->beginTransaction()')!==false &&
    strpos($src,'kp_forum_write_admin_notice')!==false &&
    strpos($src,'kp_forum_write_user_notice')!==false,'two-way alerts are saved in a transaction');
echo "Forum security smoke PASS".PHP_EOL;
