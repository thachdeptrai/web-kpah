<?php
/** Isolated game-download catalog checks; never touches the live VPS catalog. */
$root = sys_get_temp_dir() . '/kpah-download-test-' . bin2hex(random_bytes(6));
if (!mkdir($root . '/storage/game-downloads', 0700, true)) exit(1);
define('KP_ROOT', $root);
function kp_config($key, $fallback = null) { return $fallback; }
require dirname(__DIR__) . '/app/includes/game_downloads.php';
function dl_assert($yes, $msg) {
    if (!$yes) { fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL); exit(1); }
    echo 'PASS: ' . $msg . PHP_EOL;
}
function dl_post($enabled, $source, $url) {
    $post = ['enabled' => [], 'source' => [], 'url' => []];
    foreach (kp_game_platforms() as $key => $info) {
        $post['source'][$key] = $source[$key] ?? 'url';
        $post['url'][$key] = $url[$key] ?? '';
        if (!empty($enabled[$key])) $post['enabled'][$key] = '1';
    }
    return $post;
}
try {
    $default = kp_game_download_catalog();
    dl_assert(!$default['android']['enabled'], 'No catalog means disabled by default');
    kp_game_download_save(dl_post(['android' => true], [], ['android' => 'https://example.com/client.apk']), []);
    $saved = kp_game_download_catalog();
    dl_assert($saved['android']['enabled'] && $saved['android']['source'] === 'url'
        && $saved['android']['url'] === 'https://example.com/client.apk', 'Admin enables Android URL');
    dl_assert(!$saved['windows']['enabled'] && !$saved['ios']['enabled'], 'Other platforms stay disabled');
    try {
        kp_game_download_save(dl_post(['android' => true], [], ['android' => 'javascript:alert(1)']), []);
        dl_assert(false, 'Unsafe URL must fail');
    } catch (RuntimeException $e) {
        dl_assert(kp_game_download_catalog()['android']['url'] === 'https://example.com/client.apk',
            'Unsafe URL rejected without overwriting saved config');
    }
    try {
        kp_game_download_save(dl_post(['windows' => true], ['windows' => 'file'], []), []);
        dl_assert(false, 'Missing file must fail');
    } catch (RuntimeException $e) {
        dl_assert(!kp_game_download_catalog()['windows']['enabled'], 'Cannot enable missing binary');
    }
    kp_game_download_save(dl_post([], [], ['android' => 'https://example.com/client.apk']), []);
    dl_assert(!kp_game_download_catalog()['android']['enabled'], 'Admin can disable a previously active link');
    dl_assert(kp_game_download_catalog()['android']['url'] !== '', 'Disabled link retained for later reuse');
    dl_assert(kp_game_download_url('//evil.example') === '', 'Protocol-relative URL rejected');
    dl_assert(kp_game_download_url('https://user:pass@example.com') === '', 'URL credentials rejected');
    dl_assert(kp_game_download_filename_valid(str_repeat('a', 32) . '.apk', 'android'), 'Safe Android filename accepted');
    dl_assert(!kp_game_download_filename_valid('../evil.php', 'android'), 'Traversal filename rejected');
    dl_assert(!kp_game_download_filename_valid(str_repeat('a', 32) . '.exe', 'android'), 'Platform extension mismatch rejected');
    echo 'PASS: download catalog smoke checks' . PHP_EOL;
} finally {
    foreach (glob($root . '/storage/game-downloads/*') ?: [] as $file) @unlink($file);
    @rmdir($root . '/storage/game-downloads');
    @rmdir($root . '/storage');
    @rmdir($root);
}
