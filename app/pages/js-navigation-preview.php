<?php
/**
 * Display-only response for a top-level browser navigation to /assets/js/*.js.
 *
 * IMPORTANT: This is a visual deterrent, not source protection. Script
 * requests still receive executable JavaScript from Apache. Never put
 * secrets/client-authoritative business logic in JavaScript.
 *
 * Apache reaches this file solely via an internal allowlisted rewrite.
 * /app/ remains forbidden as a public URL.
 */
$file = (string) ($_GET['asset'] ?? '');
if (!preg_match('/\A[a-z0-9_-]+\.js\z/iD', $file)) {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__, 2);
$sourcePath = $root . '/assets/js/' . $file;
$minifiedPath = $root . '/assets/js-dist/' . $file;
if (!is_file($sourcePath) || is_link($sourcePath)) {
    http_response_code(404);
    exit;
}
$path = is_file($minifiedPath) && !is_link($minifiedPath) ? $minifiedPath : $sourcePath;
$length = @filesize($path);
if ($length === false || $length <= 0 || $length > 262144) {
    http_response_code(404);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Cross-Origin-Resource-Policy: same-origin');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Content-Security-Policy: default-src \'none\'; frame-ancestors \'none\'');
header('Referrer-Policy: no-referrer');

if (!function_exists('openssl_encrypt')) {
    http_response_code(503);
    echo 'JavaScript preview is unavailable.';
    exit;
}
$script = @file_get_contents($path);
if (!is_string($script) || $script === '') {
    http_response_code(503);
    echo 'JavaScript preview is unavailable.';
    exit;
}

// AES-GCM with a throwaway key intentionally not delivered to the visitor.
// This encrypted *preview* is not the executable JS consumed by the website.
try {
    $key = random_bytes(32);
    $nonce = random_bytes(12);
    $tag = '';
    $encrypted = openssl_encrypt($script, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
    if (!is_string($encrypted) || strlen($tag) !== 16) {
        throw new RuntimeException('Preview cipher unavailable');
    }
    $display = base64_encode($nonce . $tag . $encrypted);
    unset($key, $script, $encrypted);
} catch (Throwable $e) {
    http_response_code(503);
    echo 'JavaScript preview is unavailable.';
    exit;
}

echo "/* ======================================================================== */\n";
echo "/* [KPAH_PROTECTED_PAYLOAD_CIPHER]                                          */\n";
echo "/* CẢNH BÁO: Nội dung xem trực tiếp đã được mã hóa để hiển thị.              */\n";
echo "/* Đây không phải cơ chế chống sao chép mã JavaScript trong trình duyệt.     */\n";
echo "/* ======================================================================== */\n\n";
echo 'window.__KPAH_PAYLOAD__ = ' . json_encode($display, JSON_UNESCAPED_SLASHES) . ";\n";
echo 'console.warn("[KPAH] Direct JavaScript navigation shows an encrypted preview.");' . "\n";
