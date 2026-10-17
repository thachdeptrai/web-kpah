<?php
/** Offline URL validation regression: includes production sanitizer, no database. */
define('KP_ROOT', dirname(__DIR__));
require_once KP_ROOT . '/app/includes/security.php';
$good = ['/dang-nhap', '/tai-khoan?tab=characters', '#contact', 'assets/img/logo.webp',
    'https://example.com/game', 'http://localhost:8080/debug'];
$bad = ['//evil.example', '\\\\evil.example', '/\\evil.example', 'javascript:alert(1)',
    'data:text/html,hi', 'https://example.com/a b', "/ok\r\nX-Test:bad", "https://example.com/\tfoo"];
foreach ($good as $url) {
    if (kp_safe_url($url) !== $url) {
        fwrite(STDERR, "Safe URL rejected\n"); exit(1);
    }
}
foreach ($bad as $url) {
    if (kp_safe_url($url) !== '') {
        fwrite(STDERR, "Unsafe URL accepted\n"); exit(1);
    }
}
echo "Safe URL validation PASS\n";
