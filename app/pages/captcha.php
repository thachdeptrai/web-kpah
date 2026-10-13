<?php
/**
 * Trả ảnh CAPTCHA (PNG). Mã được lưu trong session phía server.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';

kp_send_security_headers(false);

if (!kp_captcha_enabled() || !kp_captcha_available()) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'CAPTCHA unavailable';
    exit;
}

list($allowed, $retry) = kp_rate_limit('captcha_img', kp_client_ip(), 60, 300, true);
if (!$allowed) {
    http_response_code(429);
    header('Retry-After: ' . $retry);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Too many requests';
    exit;
}

$code = kp_captcha_generate();
session_write_close(); // nhả khoá session sớm, việc vẽ ảnh không cần session

kp_captcha_render($code);
