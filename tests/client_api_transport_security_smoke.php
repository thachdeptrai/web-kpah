<?php
/**
 * Guard intentional HTTP response rules for browser JavaScript and JSON.
 * Offline, no production network/credentials; not a substitute for Apache TLS tests.
 */
$root = dirname(__DIR__);
function kp_transport_assert(bool $ok, string $reason): void {
    if (!$ok) { fwrite(STDERR, 'FAIL: '.$reason.PHP_EOL); exit(1); }
}
$php = file_get_contents($root.'/app/includes/security.php');
$apache = file_get_contents($root.'/.htaccess');
if (!is_string($php) || !is_string($apache)) {
    fwrite(STDERR, "FAIL: missing security policies\n"); exit(1);
}
foreach ([
    'private, no-store',
    'Vary: Cookie',
    'Cross-Origin-Resource-Policy: same-origin',
    'X-Robots-Tag: noindex, nofollow, noarchive',
    "default-src 'none'; base-uri 'none'; frame-ancestors 'none'",
] as $policy) {
    kp_transport_assert(strpos($php, $policy) !== false, 'API response policy: '.$policy);
}
kp_transport_assert(strpos($apache, 'mod_headers.c') !== false, 'Apache guards for static JS');
kp_transport_assert(strpos($apache, '(?:js|mjs)') !== false, 'JS headers file scope');
kp_transport_assert(strpos($apache, 'Header always set X-Content-Type-Options') !== false, 'JS no-sniff protection');
kp_transport_assert(strpos($apache, 'Header always set Cross-Origin-Resource-Policy') !== false, 'JS same-origin embedding');
kp_transport_assert(strpos($apache, 'map|js') !== false, 'deny JavaScript source maps');
foreach (['app/api/change-password.php','app/api/register.php','app/api/forgot-password.php','app/api/user-profile.php',
          'app/topup/bank-webhook.php','app/topup/card-callback.php','app/topup/bank-status.php'] as $path) {
    kp_transport_assert(strpos((string)file_get_contents($root.'/'.$path), 'kp_send_security_headers(false)') !== false,
        'sensitive API invokes response protection: '.$path);
}
echo "Client JS / API transport regression PASS\n";
