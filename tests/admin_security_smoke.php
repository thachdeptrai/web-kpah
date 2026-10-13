<?php
/**
 * Offline checks for the central admin guard (no MySQL, no real passwords).
 * Run: php tests/admin_security_smoke.php
 */
define('KP_ROOT', dirname(__DIR__));
$testDir = sys_get_temp_dir().'/kpah-security-test-'.bin2hex(random_bytes(5));
if (!@mkdir($testDir, 0700)) { fwrite(STDERR, "No test directory\n"); exit(1); }
function kp_rate_dir() { global $testDir; return $testDir; }
function kp_log($message, $error = null) {}
function kp_client_ip() { return (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'); }
require KP_ROOT.'/includes/admin_security.php';
function verify($value, $expected, $name)
{
    if ($value !== $expected) {
        fwrite(STDERR, "FAIL: ".$name." got ".var_export($value,true)."\n");
        exit(1);
    }
    echo "PASS: ".$name."\n";
}
try {
    // RFC 6238 SHA1: 94287082 at 59 sec, last six digits 287082.
    $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
    verify(kp_admin_totp_step($secret, '287082', 59), 1, 'RFC 6238 six-digit TOTP');
    verify(kp_admin_totp_step($secret, '287082', 30), 1, 'adjacent time step');
    verify(kp_admin_totp_step($secret, '287082', 120), false, 'expired OTP rejected');
    verify(kp_admin_totp_step($secret, 'abc123', 59), false, 'malformed OTP rejected');
    verify(kp_admin_totp_step('not-base32', '287082', 59), false, 'invalid secret rejected');
    verify(kp_admin_totp_consume(42, 1), true, 'first OTP used');
    verify(kp_admin_totp_consume(42, 1), false, 'reused OTP blocked');
    verify(kp_admin_totp_consume(42, 2), true, 'new OTP step accepted');
    $limit = kp_admin_limit('test-ip', '203.0.113.1', 2, 60);
    verify($limit[0], true, 'first attempt permitted');
    verify(kp_admin_limit('test-ip', '203.0.113.1', 2, 60)[0], true, 'second attempt permitted');
    verify(kp_admin_limit('test-ip', '203.0.113.1', 2, 60)[0], false, 'third attempt blocked');
    putenv('KPAH_ADMIN_REQUIRE_TOTP=1');
    verify(kp_admin_totp_enabled(), true, 'required MFA enabled');
    putenv('KPAH_ADMIN_TOTP_SECRETS={"Admin":"'.$secret.'"}');
    verify(kp_admin_totp_secret('admin'), $secret, 'case-insensitive per-admin secret');
    verify(kp_admin_totp_secret('someoneelse'), '', 'unknown admin is not exempt');
    putenv('KPAH_ADMIN_TOTP_SECRETS');
    putenv('KPAH_ADMIN_REQUIRE_TOTP');
    putenv('KPAH_ADMIN_ALLOWED_IPS=127.0.0.1');
    $_SERVER['REMOTE_ADDR']='198.51.100.77';
    verify(kp_admin_allowed_ip(), false, 'admin IP allowlist denies unknown IP');
    $_SERVER['REMOTE_ADDR']='127.0.0.1';
    verify(kp_admin_allowed_ip(), true, 'admin IP allowlist allows configured IP');
    putenv('KPAH_ADMIN_ALLOWED_IPS');
    echo "ALL ADMIN SECURITY CHECKS PASSED\n";
} finally {
    foreach (glob($testDir.'/*') ?: [] as $path) @unlink($path);
    @rmdir($testDir);
}
