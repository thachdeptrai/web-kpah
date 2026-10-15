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
    dl_assert(kp_game_download_ini_bytes('2M') === 2097152, 'Parse PHP default upload_max_filesize 2M');
    dl_assert(kp_game_download_ini_bytes('8M') === 8388608, 'Parse PHP default post_max_size 8M');
    dl_assert(kp_game_download_ini_bytes('512M') === 536870912, 'Parse 512 MB upload limit');
    dl_assert(kp_game_download_ini_bytes('1G') === 1073741824, 'Parse 1GB upload limit');
    dl_assert(kp_game_download_ini_bytes('1.5M') === 1572864, 'Parse fractional upload limit');
    dl_assert(kp_game_download_ini_bytes('-1') === 0 && kp_game_download_ini_bytes('0') === 0, 'Unlimited or off limits are not misparsed');
    dl_assert(kp_game_download_ini_bytes('unexpected') === 0, 'Invalid ini limit handled safely');
    $default = kp_game_download_catalog();
    dl_assert(!$default['android']['enabled'], 'No catalog means disabled by default');
    dl_assert(isset(kp_game_platforms()['java']) && kp_game_platforms()['java']['ext'] === ['jar'], 'Java JAR is a separate download platform');
    dl_assert(!$default['java']['enabled'], 'Java JAR starts disabled without a real release');
    kp_game_download_save(dl_post(['android' => true], [], ['android' => 'https://example.com/client.apk']), []);
    $saved = kp_game_download_catalog();
    dl_assert($saved['android']['enabled'] && $saved['android']['source'] === 'url'
        && $saved['android']['url'] === 'https://example.com/client.apk', 'Admin enables Android URL');
    dl_assert(!$saved['windows']['enabled'] && !$saved['ios']['enabled'] && !$saved['java']['enabled'], 'Other platforms stay disabled');
    // Simulate a catalog created before Java/JAR was added; existing releases must survive.
    $legacy = json_decode(file_get_contents(kp_game_download_dir() . '/catalog.json'), true);
    unset($legacy['java']);
    file_put_contents(kp_game_download_dir() . '/catalog.json', json_encode($legacy));
    $oldCatalog = kp_game_download_catalog();
    dl_assert($oldCatalog['android']['enabled'] && !$oldCatalog['java']['enabled'], 'Legacy three-platform catalog loads without losing existing downloads');
    kp_game_download_save(dl_post(['android' => true, 'java' => true], [], [
        'android' => 'https://example.com/client.apk', 'java' => 'https://example.com/client.jar'
    ]), []);
    $jarEnabled = kp_game_download_catalog();
    dl_assert($jarEnabled['java']['enabled'] && $jarEnabled['java']['source'] === 'url'
        && $jarEnabled['java']['url'] === 'https://example.com/client.jar', 'Admin can enable external Java JAR without modifying Android');
    dl_assert($jarEnabled['android']['enabled'] && $jarEnabled['android']['url'] === 'https://example.com/client.apk', 'Existing Android link preserved after JAR enable');
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
    try {
        kp_game_download_save(dl_post(['java' => true], ['java' => 'file'], []), []);
        dl_assert(false, 'Java upload source without actual JAR must fail');
    } catch (RuntimeException $e) {
        dl_assert(kp_game_download_catalog()['java']['source'] === 'url', 'Failed Java file enable does not change saved catalog');
    }
    }
    kp_game_download_save(dl_post([], [], ['android' => 'https://example.com/client.apk']), []);
    dl_assert(!kp_game_download_catalog()['android']['enabled'], 'Admin can disable a previously active link');
    dl_assert(kp_game_download_catalog()['android']['url'] !== '', 'Disabled link retained for later reuse');
    dl_assert(kp_game_download_url('//evil.example') === '', 'Protocol-relative URL rejected');
    dl_assert(kp_game_download_url('https://user:pass@example.com') === '', 'URL credentials rejected');
    dl_assert(kp_game_download_filename_valid(str_repeat('a', 32) . '.apk', 'android'), 'Safe Android filename accepted');
    dl_assert(kp_game_download_filename_valid(str_repeat('c', 32) . '.jar', 'java'), 'Valid stored Java JAR filename accepted');
    dl_assert(!kp_game_download_filename_valid(str_repeat('c', 32) . '.apk', 'java'), 'Java slot rejects APK extension');
    dl_assert(!kp_game_download_filename_valid(str_repeat('c', 32) . '.jar', 'android'), 'Android slot rejects JAR extension');
    dl_assert(!kp_game_download_filename_valid('../evil.php', 'android'), 'Traversal filename rejected');
    dl_assert(!kp_game_download_filename_valid(str_repeat('a', 32) . '.exe', 'android'), 'Platform extension mismatch rejected');
    $dir = kp_game_download_dir();
    $oldName = str_repeat('b', 32) . '.apk';
    file_put_contents($dir . '/' . $oldName, "PK\\x03\\x04fixture");
    $catalog = json_decode(file_get_contents($dir . '/catalog.json'), true);
    $catalog['android']['file'] = $oldName;
    $catalog['android']['filename'] = 'OldGame.apk';
    $catalog['android']['size'] = 11;
    file_put_contents($dir . '/catalog.json', json_encode($catalog));
    $remove = dl_post([], [], ['android' => 'https://example.com/client.apk']);
    $remove['remove']['android'] = '1';
    kp_game_download_save($remove, []);
    dl_assert(!file_exists($dir . '/' . $oldName), 'Admin removal deletes unreferenced release');
    dl_assert(kp_game_download_catalog()['android']['file'] === '', 'Catalog no longer references deleted file');
    echo 'PASS: download catalog smoke checks' . PHP_EOL;
} finally {
    foreach (glob($root . '/storage/game-downloads/*') ?: [] as $file) @unlink($file);
    @rmdir($root . '/storage/game-downloads');
    @rmdir($root . '/storage');
    @rmdir($root);
}
