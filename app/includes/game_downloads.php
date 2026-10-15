<?php
/**
 * Public game releases. Metadata and uploaded binaries live in storage/, which
 * Apache blocks. Only an allowlisted download handler may stream binaries.
 * No database migration and no executable files in the public assets tree.
 */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }

function kp_game_platforms()
{
    return [
        'windows' => ['name' => 'Windows / PC', 'ext' => ['zip', 'exe', 'msi'], 'hint' => 'ZIP, EXE hoặc MSI'],
        'java' => ['name' => 'Java / JAR', 'ext' => ['jar'], 'hint' => 'JAR'], 
        'android' => ['name' => 'Android', 'ext' => ['apk'], 'hint' => 'APK'],
        'ios' => ['name' => 'iPhone / iPad', 'ext' => ['ipa'], 'hint' => 'IPA'],
    ];
}

function kp_game_download_dir()
{
    return KP_ROOT . '/storage/game-downloads';
}

/**
 * Parse PHP INI shorthand limits (for example "2M", "512M", "1G").
 * Returns 0 when unset/unlimited; callers must also account for proxy limits.
 */
function kp_game_download_ini_bytes($raw)
{
    $value = trim((string)$raw);
    if ($value === '' || $value === '-1' || $value === '0') return 0;
    if (!preg_match('/^(\d+(?:\.\d+)?)\s*([KMG]?)B?$/iD', $value, $match)) return 0;
    $factor = ['' => 1, 'K' => 1024, 'M' => 1048576, 'G' => 1073741824];
    $unit = strtoupper($match[2]);
    $bytes = (float)$match[1] * $factor[$unit];
    return $bytes > PHP_INT_MAX ? PHP_INT_MAX : (int)$bytes;
}

function kp_game_download_url($value)
{
    $url = trim((string)$value);
    if ($url === '') return '';
    // Only HTTPS or an absolute local URL; no protocol-relative or userinfo URLs.
    if (strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url)) return '';
    if (substr($url, 0, 1) === '/' && substr($url, 0, 2) !== '//') {
        return $url;
    }
    if (!preg_match('~^https://~i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) return '';
    $parts = parse_url($url);
    return is_array($parts) && !isset($parts['user']) && !isset($parts['pass'])
        && !empty($parts['host']) ? $url : '';
}

function kp_game_download_filename_valid($name, $platform)
{
    if (!isset(kp_game_platforms()[$platform]) || !is_string($name)) return false;
    if (!preg_match('/^[a-f0-9]{32}\.([a-z0-9]{2,4})$/D', $name, $match)) return false;
    return in_array($match[1], kp_game_platforms()[$platform]['ext'], true);
}

function kp_game_download_defaults()
{
    $defaults = [];
    foreach (kp_game_platforms() as $key => $platform) {
        $url = kp_game_download_url(kp_config('links.download_' . $key, ''));
        $defaults[$key] = [
            'enabled' => $url !== '',
            'source' => 'url',
            'url' => $url,
            'file' => '',
            'filename' => '',
            'size' => 0,
            'sha256' => '',
            'updated_at' => '',
        ];
    }
    return $defaults;
}

/** Shared lock keeps readers from seeing a partially published catalog. */
function kp_game_download_catalog()
{
    $defaults = kp_game_download_defaults();
    $dir = kp_game_download_dir();
    $path = $dir . '/catalog.json';
    if (!is_file($path)) return $defaults;
    $lock = @fopen($dir . '/catalog.lock', 'c+');
    if (!$lock) return array_map(function($r) { $r['enabled'] = false; return $r; }, $defaults);
    try {
        if (!flock($lock, LOCK_SH)) return array_map(function($r) { $r['enabled'] = false; return $r; }, $defaults);
        $raw = @file_get_contents($path);
        $decoded = is_string($raw) && strlen($raw) <= 20000 ? json_decode($raw, true) : null;
    } finally {
        @flock($lock, LOCK_UN);
        fclose($lock);
    }
    if (!is_array($decoded)) return array_map(function($r) { $r['enabled'] = false; return $r; }, $defaults);
    foreach ($defaults as $key => &$row) {
        $stored = isset($decoded[$key]) && is_array($decoded[$key]) ? $decoded[$key] : [];
        $row['source'] = ($stored['source'] ?? '') === 'file' ? 'file' : 'url';
        $row['url'] = kp_game_download_url($stored['url'] ?? '');
        $file = $stored['file'] ?? '';
        $row['file'] = kp_game_download_filename_valid($file, $key) ? $file : '';
        $row['filename'] = is_string($stored['filename'] ?? null) ? substr($stored['filename'], 0, 120) : '';
        $row['size'] = max(0, (int)($stored['size'] ?? 0));
        $row['sha256'] = preg_match('/^[a-f0-9]{64}$/D', (string)($stored['sha256'] ?? '')) ? $stored['sha256'] : '';
        $row['updated_at'] = is_string($stored['updated_at'] ?? null) ? substr($stored['updated_at'], 0, 32) : '';
        $valid = $row['source'] === 'url'
            ? $row['url'] !== ''
            : ($row['file'] !== '' && is_file($dir . '/' . $row['file']));
        $row['enabled'] = !empty($stored['enabled']) && $valid;
    }
    unset($row);
    return $defaults;
}

function kp_game_download_upload($platform, $upload)
{
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'File vượt upload_max_filesize trong php.ini.',
        UPLOAD_ERR_FORM_SIZE => 'File vượt giới hạn 512 MB.',
        UPLOAD_ERR_PARTIAL => 'File chỉ tải lên được một phần. Hãy thử lại.',
        UPLOAD_ERR_NO_TMP_DIR => 'PHP chưa cấu hình thư mục tạm.',
        UPLOAD_ERR_CANT_WRITE => 'Không thể ghi file lên VPS.',
        UPLOAD_ERR_EXTENSION => 'PHP đã chặn file tải lên.',
    ];
    $code = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($code !== UPLOAD_ERR_OK) throw new RuntimeException($errors[$code] ?? 'Không thể tải file lên.');
    if (!is_string($upload['name'] ?? null) || !is_string($upload['tmp_name'] ?? null)) {
        throw new RuntimeException('Thông tin file không hợp lệ.');
    }
    $name = basename(str_replace('\\', '/', $upload['name']));
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, kp_game_platforms()[$platform]['ext'], true)) {
        throw new RuntimeException('Sai định dạng file cho ' . kp_game_platforms()[$platform]['name'] . '.');
    }
    $tmp = $upload['tmp_name'];
    if (!is_uploaded_file($tmp)) throw new RuntimeException('File không phải bản upload hợp lệ.');
    $size = @filesize($tmp);
    if (!is_int($size) || $size < 16 || $size > 512 * 1024 * 1024) {
        throw new RuntimeException('File phải có dung lượng từ 16 byte đến 512 MB.');
    }
    $fh = @fopen($tmp, 'rb');
    if (!$fh) throw new RuntimeException('Không đọc được file upload.');
    $magic = fread($fh, 8);
    fclose($fh);
    $zip = substr($magic, 0, 4) === "PK\x03\x04";
    $ole = $magic === "\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1";
    if ((in_array($ext, ['apk', 'ipa', 'zip', 'jar'], true) && !$zip)
        || ($ext === 'exe' && substr($magic, 0, 2) !== 'MZ')
        || ($ext === 'msi' && !$ole)) {
        throw new RuntimeException('Nội dung file không khớp với định dạng ' . strtoupper($ext) . '.');
    }
    if (class_exists('ZipArchive') && in_array($ext, ['apk', 'ipa', 'jar'], true)) {
        $archive = new ZipArchive();
        if ($archive->open($tmp, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Gói APK/IPA không đọc được.');
        }
        $valid = $ext === 'apk'
            ? $archive->locateName('AndroidManifest.xml') !== false
            : false;
        if ($ext === 'jar') {
            // A JAR must contain Java metadata or compiled bytecode; reject plain ZIPs.
            $valid = $archive->locateName('META-INF/MANIFEST.MF', ZipArchive::FL_NOCASE) !== false;
            if (!$valid) {
                for ($i = 0; $i < $archive->numFiles; $i++) {
                    $entry = $archive->getNameIndex($i);
                    if (is_string($entry) && preg_match('~\\.class$~iD', $entry)) {
                        $valid = true; break;
                    }
                }
            }
        }
        if ($ext === 'ipa') {
            for ($i = 0; $i < $archive->numFiles; $i++) {
                $entry = $archive->getNameIndex($i);
                if (is_string($entry) && preg_match('~^Payload/[^/]+\.app/Info\.plist$~', $entry)) {
                    $valid = true; break;
                }
            }
        }
        $archive->close();
        if (!$valid) throw new RuntimeException('File không có cấu trúc ' . strtoupper($ext) . ' hợp lệ.');
    }
    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    $dir = kp_game_download_dir();
    if (!move_uploaded_file($tmp, $dir . '/' . $stored)) {
        throw new RuntimeException('Không lưu được file vào storage/game-downloads.');
    }
    @chmod($dir . '/' . $stored, 0600);
    $digest = @hash_file('sha256', $dir . '/' . $stored);
    if (!is_string($digest) || !preg_match('/^[a-f0-9]{64}$/D', $digest)) {
        @unlink($dir . '/' . $stored);
        throw new RuntimeException('Không kiểm tra được checksum của file tải lên.');
    }
    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
    return [
        'file' => $stored,
        'filename' => substr($safeName, 0, 120),
        'size' => $size,
        'sha256' => $digest,
    ];
}

/** Admin-only; caller MUST check role, CSRF and request method. */
function kp_game_download_save($post, $files)
{
    $dir = kp_game_download_dir();
    if (is_link($dir) || (!is_dir($dir) && !@mkdir($dir, 0700, true)) || !is_dir($dir)) {
        throw new RuntimeException('Không tạo được thư mục storage/game-downloads.');
    }
    $lock = @fopen($dir . '/catalog.lock', 'c+');
    if (!$lock || !flock($lock, LOCK_EX)) {
        if ($lock) fclose($lock);
        throw new RuntimeException('Không thể khóa cấu hình tải game.');
    }
    @chmod($dir . '/catalog.lock', 0600);
    $created = [];
    $tmpCatalog = '';
    try {
        $old = kp_game_download_catalog_unlocked();
        $next = [];
        foreach (kp_game_platforms() as $key => $platform) {
            $current = $old[$key];
            $source = (string)($post['source'][$key] ?? '');
            if (!in_array($source, ['url', 'file'], true)) throw new RuntimeException('Nguồn tải không hợp lệ.');
            $urlRaw = trim((string)($post['url'][$key] ?? ''));
            $url = kp_game_download_url($urlRaw);
            if ($urlRaw !== '' && $url === '') throw new RuntimeException('Link ' . $platform['name'] . ' phải dùng HTTPS hoặc đường dẫn / hợp lệ.');
            $current['url'] = $url;
            $current['source'] = $source;
            $upload = $files['upload']['error'][$key] ?? UPLOAD_ERR_NO_FILE;
            $remove = isset($post['remove'][$key]) && $post['remove'][$key] === '1';
            if ($remove && (int)$upload !== UPLOAD_ERR_NO_FILE) {
                throw new RuntimeException('Không thể vừa xóa vừa upload file ' . $platform['name'] . '.');
            }
            if ($remove) {
                $current['file'] = '';
                $current['filename'] = '';
                $current['size'] = 0;
                $current['sha256'] = '';
            }
            if ((int)$upload !== UPLOAD_ERR_NO_FILE) {
                if ($source !== 'file') throw new RuntimeException('Chọn nguồn "Upload lên VPS" trước khi tải file.');
                $entry = [];
                foreach (['name', 'tmp_name', 'error', 'size', 'type'] as $field) {
                    $entry[$field] = $files['upload'][$field][$key] ?? null;
                }
                $new = kp_game_download_upload($key, $entry);
                $created[] = $new['file'];
                $current = array_replace($current, $new);
            }
            $current['enabled'] = isset($post['enabled'][$key]) && $post['enabled'][$key] === '1';
            if ($current['enabled'] && ($source === 'url' ? $url === '' :
                ($current['file'] === '' || !kp_game_download_filename_valid($current['file'], $key)
                    || !is_file($dir . '/' . $current['file'])))) {
                throw new RuntimeException('Bạn đã bật ' . $platform['name'] . ' nhưng chưa có nguồn tải hợp lệ.');
            }
            $current['updated_at'] = date('c');
            $next[$key] = $current;
        }
        $json = json_encode($next, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) throw new RuntimeException('Không mã hóa được cấu hình.');
        $tmpCatalog = $dir . '/catalog-' . bin2hex(random_bytes(8)) . '.tmp';
        if (@file_put_contents($tmpCatalog, $json, LOCK_EX) !== strlen($json)
            || !@rename($tmpCatalog, $dir . '/catalog.json')) {
            throw new RuntimeException('Không thể lưu cấu hình. Kiểm tra quyền ghi storage.');
        }
        $tmpCatalog = '';
        @chmod($dir . '/catalog.json', 0600);
        // Only remove replaced files; do not touch files still referenced by any platform.
        $referenced = array_column($next, 'file');
        foreach ($old as $row) {
            $oldFile = $row['file'];
            if ($oldFile !== '' && !in_array($oldFile, $referenced, true)
                && preg_match('/^[a-f0-9]{32}\.(?:apk|ipa|zip|exe|msi|jar)$/D', $oldFile)) {
                @unlink($dir . '/' . $oldFile);
            }
        }
    } catch (Throwable $e) {
        foreach ($created as $name) @unlink($dir . '/' . $name);
        if ($tmpCatalog !== '') @unlink($tmpCatalog);
        throw $e;
    } finally {
        @flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Read while the writer holds catalog.lock; caller already owns exclusive lock. */
function kp_game_download_catalog_unlocked()
{
    $path = kp_game_download_dir() . '/catalog.json';
    if (!is_file($path)) return kp_game_download_defaults();
    $raw = @file_get_contents($path);
    $decoded = is_string($raw) && strlen($raw) <= 20000 ? json_decode($raw, true) : null;
    if (!is_array($decoded)) throw new RuntimeException('Cấu hình hiện tại bị lỗi; không ghi đè tự động.');
    $defaults = kp_game_download_defaults();
    foreach ($defaults as $key => &$row) {
        $v = isset($decoded[$key]) && is_array($decoded[$key]) ? $decoded[$key] : [];
        $row['enabled'] = !empty($v['enabled']);
        $row['source'] = ($v['source'] ?? '') === 'file' ? 'file' : 'url';
        $row['url'] = kp_game_download_url($v['url'] ?? '');
        $row['file'] = kp_game_download_filename_valid($v['file'] ?? '', $key) ? $v['file'] : '';
        $row['filename'] = is_string($v['filename'] ?? null) ? substr($v['filename'], 0, 120) : '';
        $row['size'] = max(0, (int)($v['size'] ?? 0));
        $row['sha256'] = preg_match('/^[a-f0-9]{64}$/D', (string)($v['sha256'] ?? '')) ? $v['sha256'] : '';
        $row['updated_at'] = is_string($v['updated_at'] ?? null) ? substr($v['updated_at'], 0, 32) : '';
    }
    unset($row);
    return $defaults;
}
