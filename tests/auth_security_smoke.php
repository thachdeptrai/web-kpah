<?php
/**
 * Offline regression assertions for account-ban and password-CAS guards.
 * No database, real credentials, or payment traffic is used.
 * This is NOT a substitute for staging integration tests.
 */
define('KP_ROOT', dirname(__DIR__));
require KP_ROOT . '/app/includes/security.php';
require KP_ROOT . '/app/includes/account.php';

function auth_test($ok, $description) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: $description\n");
        exit(1);
    }
    echo "PASS: $description\n";
}
function auth_source($path) {
    $value = file_get_contents(KP_ROOT . '/' . $path);
    auth_test($value !== false, "source readable: " . $path);
    return $value;
}
$login = auth_source('app/pages/login.php');
auth_test(strpos($login, 'FROM team_user WHERE username = ? AND ban = 0 LIMIT 1') !== false, 'banned accounts rejected at login query');
$profile = auth_source('app/api/user-profile.php');
auth_test(strpos($profile, 'SELECT email FROM team_user WHERE id = ? AND ban = 0 LIMIT 1') !== false, 'profile reads require active account');
auth_test(strpos($profile, 'UPDATE team_user SET email = ?, phone = ? WHERE id = ? AND ban = 0') !== false, 'profile writes require active account');
$password = auth_source('app/api/change-password.php');
auth_test(strpos($password, 'SELECT password FROM team_user WHERE id = ? AND ban = 0 LIMIT 1') !== false, 'password change checks ban status');
auth_test(strpos($password, 'UPDATE team_user SET password = ? WHERE id = ? AND password = ? AND ban = 0') !== false, 'password update compares the verified old hash');
auth_test(strpos($password, '$up->rowCount() !== 1') !== false, 'password CAS checks affected rows');
$recovery = auth_source('app/api/forgot-password.php');
auth_test(strpos($recovery, 'AND ban = 0 LIMIT 1') !== false, 'recovery reads reject banned accounts');
auth_test(strpos($recovery, 'UPDATE team_user SET password = ? WHERE id = ? AND password = ? AND ban = 0') !== false, 'recovery CAS rejects stale or banned account');
auth_test(strpos($recovery, '$update->rowCount() !== 1') !== false, 'recovery CAS checks affected rows');
auth_test(strpos($recovery, "kp_rate_limit('password_recovery_ip'") !== false, 'recovery has global IP limit regardless of identity');
auth_test(strpos($recovery, "kp_rate_limit('password_recovery_identity_minute'") !== false, 'recovery has 60-second identity cooldown');
auth_test(strpos($recovery, "kp_rate_limit('password_recovery_recipient_minute'") !== false, 'recovery has shared 60-second recipient cooldown across sessions');
auth_test(strpos($recovery, "kp_rate_limit('password_recovery_recipient_window'") !== false, 'recovery limits number of emails per recipient');
auth_test(strpos($recovery, "kp_recovery_reply([\n                    'ok' => true") !== false, 'recovery recipient throttling uses an enumeration-safe response');
auth_test(strpos($recovery, "header('Retry-After: '.") !== false, 'recovery supplies Retry-After on IP or identity limit');
auth_test(strpos($recovery, "kp_csrf_rotate()") !== false, 'recovery rotates CSRF tokens like registration');
$recoveryJs = auth_source('assets/js/forgot-password.js');
auth_test(strpos($recoveryJs, 'startResendCountdown(result.retry_after || 60)') !== false, 'recovery client obeys backend cooldown');
auth_test(strpos($recoveryJs, 'form.elements.csrf.value = json.csrf') !== false, 'recovery client refreshes rotated CSRF');


$_SESSION = [];
auth_test(kp_csrf_check('') === false, 'empty CSRF rejected');
$csrf = kp_csrf_token();
auth_test(kp_csrf_check($csrf) === true, 'correct CSRF accepted');
auth_test(kp_csrf_check(str_repeat('0', 64)) === false, 'incorrect CSRF rejected');
kp_csrf_rotate();
auth_test(kp_csrf_check($csrf) === false, 'rotated CSRF revokes old token');
$hash = kp_game_password_hash('sample-compat-123');
auth_test(kp_verify_account_password('sample-compat-123', $hash), 'legacy game hash verification retained');
auth_test(!kp_verify_account_password('wrong', $hash), 'legacy game hash rejects incorrect password');
echo "PASS: auth security offline smoke tests\n";
