<?php
/**
 * Offline security regression checks; no DB, external network or credentials.
 * Run: php tests/security_smoke.php
 */
define('KP_ROOT', dirname(__DIR__));
require KP_ROOT . '/app/includes/admin_security.php';
function sec_assert($condition, $message) {
    if (!$condition) { fwrite(STDERR, "FAIL: ".$message.PHP_EOL); exit(1); }
    echo "OK: ".$message.PHP_EOL;
}
// RFC 6238 test key (ASCII 12345678901234567890, base32 encoded):
// at epoch 59 the 8-digit SHA1 TOTP is 94287082, last 6 digits = 287082.
sec_assert(kp_admin_totp_step('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '287082', 59) === 1, 'TOTP RFC known-answer sample');
sec_assert(kp_admin_totp_step('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '287083', 59) === false, 'TOTP rejects incorrect code');
sec_assert(kp_admin_base32_decode('%%%%') === false, 'TOTP rejects invalid base32');
$htaccess = (string)file_get_contents(KP_ROOT.'/public/.htaccess');
foreach (['storage','database','includes','vendor','tests','docs'] as $dir) {
    sec_assert(strpos($htaccess,$dir) !== false, 'web boundary includes private '.$dir);
}
foreach (['sql','sqlite','zip','rar','7z','tar','gz','db','bak'] as $ext) {
    sec_assert(strpos($htaccess,$ext) !== false, 'backup/dump extension coverage '.$ext);
}
$gitignore = (string)file_get_contents(KP_ROOT.'/.gitignore');
sec_assert(strpos($gitignore,'/storage/*') !== false, 'runtime payment and MFA state excluded from git');
sec_assert(strpos($gitignore,'*.sql') !== false, 'SQL dumps excluded from git');
$topup=(string)file_get_contents(KP_ROOT . '/app/includes/topup_core.php');
sec_assert(strpos($topup, "return kp_admin_require('/admin/login.php')") !== false, 'topup shares central admin MFA/session guard');
echo "PASS: offline security invariants".PHP_EOL;
