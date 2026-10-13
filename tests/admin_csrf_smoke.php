<?php
/**
 * Session separation + CSRF proxy regression tests.
 * Run: php tests/admin_csrf_smoke.php
 * Pure PHP; no database, website, or secret credentials are required.
 */
define('KP_ROOT', dirname(__DIR__));
require_once KP_ROOT . '/app/includes/config_loader.php';
require_once KP_ROOT . '/app/includes/security.php';
require_once KP_ROOT . '/app/includes/admin_security.php';

function assert_csrf($condition, $title)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: ".$title.PHP_EOL);
        exit(1);
    }
}

$_SERVER['SCRIPT_NAME']='/admin/login.php';
assert_csrf(kp_session_cookie_name()==='KPAHADMIN','admin login has its own cookie');
$_SERVER['SCRIPT_NAME']='/admin/referrals.php';
assert_csrf(kp_session_cookie_name()==='KPAHADMIN','other admin pages share admin session');
$_SERVER['SCRIPT_NAME']='/topup/admin/grant.php';
assert_csrf(kp_session_cookie_name()==='KPAHADMIN','topup admin shares same admin cookie');
$_SERVER['SCRIPT_NAME']='/topup/admin/rates.php';
assert_csrf(kp_session_cookie_name()==='KPAHADMIN','topup admin rates stay consistent');
$_SERVER['SCRIPT_NAME']='/login.php';
assert_csrf(kp_session_cookie_name()==='KPAHREG','player login has separate cookie');
$_SERVER['SCRIPT_NAME']='/home.php';
assert_csrf(kp_session_cookie_name()==='KPAHREG','player profile shares player session');
$_SERVER['SCRIPT_NAME']='/topup/card.php';
assert_csrf(kp_session_cookie_name()==='KPAHREG','player topup shares player session');
$_SERVER['SCRIPT_NAME']='/admin/login.php';

$_SESSION=[];
$validToken=kp_csrf_token();
$_SERVER['HTTP_HOST']='internal-webserver.local';
$_SERVER['HTTP_ORIGIN']='https://nghienkpah.io.vn';
$_SERVER['HTTP_SEC_FETCH_SITE']='same-origin';
assert_csrf(kp_admin_csrf_valid($validToken),'valid CSRF behind hostname-rewriting reverse proxy');
assert_csrf(!kp_admin_csrf_valid('wrong-token'),'bad token denied even for same-origin browser');
assert_csrf(!kp_admin_csrf_valid(''),'missing token denied');
$_SERVER['HTTP_SEC_FETCH_SITE']='cross-site';
assert_csrf(!kp_admin_csrf_valid($validToken),'cross-site browser form blocked');
$_SERVER['HTTP_SEC_FETCH_SITE']='same-site';
assert_csrf(!kp_admin_csrf_valid($validToken),'same-site different-origin form blocked');
$_SERVER['HTTP_SEC_FETCH_SITE']='';
$_SERVER['HTTP_HOST']='nghienkpah.io.vn';
assert_csrf(kp_admin_csrf_valid($validToken),'legacy browser same-origin accepted');
$_SERVER['HTTP_HOST']='internal-webserver.local';
assert_csrf(!kp_admin_csrf_valid($validToken),'legacy browser origin mismatch rejected');
putenv('KPAH_PUBLIC_HOST=nghienkpah.io.vn');
assert_csrf(kp_admin_csrf_valid($validToken),'canonical host supports old browsers behind proxy');
putenv('KPAH_PUBLIC_HOST');
$_SERVER['HTTP_ORIGIN']='https://untrusted.invalid';
assert_csrf(!kp_admin_csrf_valid($validToken),'third party origin rejected');
$_SERVER['HTTP_ORIGIN']='null';
assert_csrf(!kp_admin_csrf_valid($validToken),'opaque origin rejected');
unset($_SERVER['HTTP_ORIGIN'],$_SERVER['HTTP_SEC_FETCH_SITE']);
assert_csrf(kp_admin_csrf_valid($validToken),'token-only fallback allowed for older browsers without Origin');

$dir=sys_get_temp_dir().'/kpah-admin-csrf-test-'.bin2hex(random_bytes(5));
if (!mkdir($dir,0700)) { fwrite(STDERR,"Cannot create temporary session directory\n");exit(1); }
try {
    session_save_path($dir);
    session_id('');
    kp_session_start();
    assert_csrf(session_name()==='KPAHADMIN','PHP session starts using dedicated admin cookie');
    $sessionToken=kp_csrf_token();
    $sessionId=session_id();
    session_write_close();
    session_id($sessionId);
    kp_session_start();
    assert_csrf(kp_csrf_check($sessionToken),'admin CSRF token survives a new PHP request');
    session_write_close();
} finally {
    foreach (glob($dir.'/*') ?: [] as $file) @unlink($file);
    @rmdir($dir);
}
echo "PASS: isolated admin sessions, persistent CSRF and proxy origin checks\n";
