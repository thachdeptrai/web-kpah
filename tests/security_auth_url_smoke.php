<?php
/**
 * Offline security invariants: no DB/network/payment provider involved.
 * Covers header/control-character rejection in rendered links and auth budgets.
 */
define('KP_ROOT', dirname(__DIR__));
require KP_ROOT.'/app/includes/security.php';
function security_guard_assert($ok,$label) {
    if (!$ok) { fwrite(STDERR,"FAIL: ".$label."\n"); exit(1); }
}
security_guard_assert(kp_safe_url('/tai-khoan') === '/tai-khoan', 'internal link stays valid');
security_guard_assert(kp_safe_url('https://example.org/support') === 'https://example.org/support', 'HTTPS links stay valid');
foreach ([
    '\\\\evil.example',
    '\\evil.example',
    'http:\\\\evil.example',
    '//evil.example',
    'javascript:alert(1)',
    "https://example.org/\r\nX-Injected: true",
    "https://example.org/\0bad",
] as $dangerous) {
    security_guard_assert(kp_safe_url($dangerous) === '', 'unsafe URL rejected');
}
$login = file_get_contents(KP_ROOT.'/app/pages/login.php');
security_guard_assert(is_string($login), 'login source readable');
foreach (["kp_rate_limit('user_login_ip'", "kp_rate_limit('user_login_identity'", "kp_rate_limit('user_login'"] as $check) {
    security_guard_assert(strpos($login, $check) !== false, 'login has independent throttle: '.$check);
}
security_guard_assert(strpos($login, '$ipAllowed && $nameAllowed && $pairAllowed') !== false, 'all login rate budgets enforced');
echo "Security auth/url regression PASS\n";
