<?php
/**
 * Bảo mật: session/cookie, header, CSRF, IP client, giới hạn tần suất, tiện ích escape.
 */
if (!defined('KP_ROOT')) {
    http_response_code(403);
    exit;
}

/* ------------------------------------------------------------------ */
/* Tiện ích chung                                                      */
/* ------------------------------------------------------------------ */

/** Escape HTML (chống XSS) */
function kp_e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Chỉ cho phép URL tương đối hoặc http(s); trả về '' nếu không hợp lệ. */
function kp_safe_url($url)
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $url)) {
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }
    // đường dẫn tương đối: không cho scheme lạ (javascript:, data: ...) và không cho //host
    if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url) || strpos($url, '//') === 0) {
        return '';
    }
    return $url;
}

function kp_is_https()
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    if (kp_config('security.trust_forwarded_proto', false)
        && isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
        && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    return false;
}

function kp_client_ip()
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    $header = (string) kp_config('security.trusted_ip_header', '');
    if ($header !== '' && !empty($_SERVER[$header])) {
        $parts = explode(',', (string) $_SERVER[$header]);
        $cand = trim($parts[0]);
        if (filter_var($cand, FILTER_VALIDATE_IP)) {
            $ip = $cand;
        }
    }
    return $ip;
}

/** Ghi log lỗi nội bộ – KHÔNG bao giờ truyền mật khẩu hay dữ liệu nhạy cảm vào đây. */
function kp_log($where, $e = null)
{
    $line = '[KPAH] ' . $where;
    if ($e instanceof \Throwable) {
        $line .= ' | ' . get_class($e) . ' #' . $e->getCode() . ' @' . basename($e->getFile()) . ':' . $e->getLine();
        if (kp_config('app.debug_log', false)) {
            $line .= ' | ' . $e->getMessage();
        }
    }
    error_log($line);
}

/* ------------------------------------------------------------------ */
/* Header & session                                                    */
/* ------------------------------------------------------------------ */

function kp_send_security_headers($isPage)
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('X-Permitted-Cross-Domain-Policies: none');
    if (kp_is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    if ($isPage) {
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; "
            . "style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; "
            . "img-src 'self' data:; connect-src 'self'; object-src 'none'; "
            . "base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    }
}

function kp_session_start()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = kp_is_https();
    $path   = (string) kp_config('security.cookie_path', '/');

    session_name((string) kp_config('security.session_name', 'KPAHREG'));
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.cookie_httponly', '1');

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => $path,
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, $path . '; samesite=Lax', '', $secure, true);
    }
    session_start();
}

/* ------------------------------------------------------------------ */
/* CSRF                                                                */
/* ------------------------------------------------------------------ */

function kp_csrf_token()
{
    if (empty($_SESSION['kp_csrf']) || !is_string($_SESSION['kp_csrf'])) {
        $_SESSION['kp_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['kp_csrf'];
}

function kp_csrf_rotate()
{
    $_SESSION['kp_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['kp_csrf'];
}

function kp_csrf_check($token)
{
    if (!is_string($token) || $token === '' || empty($_SESSION['kp_csrf']) || !is_string($_SESSION['kp_csrf'])) {
        return false;
    }
    return hash_equals($_SESSION['kp_csrf'], $token);
}

/* ------------------------------------------------------------------ */
/* Giới hạn tần suất (lưu file, không cần thay đổi database)           */
/* ------------------------------------------------------------------ */

function kp_rate_dir()
{
    static $dir = null;
    if ($dir !== null) {
        return $dir;
    }
    $candidates = [
        KP_ROOT . '/storage/ratelimit',
        rtrim(sys_get_temp_dir(), '/\\') . '/kpah_ratelimit',
    ];
    foreach ($candidates as $d) {
        if (!is_dir($d)) {
            @mkdir($d, 0750, true);
        }
        if (is_dir($d) && is_writable($d)) {
            $dir = $d;
            return $dir;
        }
    }
    kp_log('rate limit: không có thư mục ghi được, bỏ qua giới hạn tần suất');
    $dir = '';
    return $dir;
}

/**
 * @param string $bucket  tên nhóm (vd 'reg_attempt')
 * @param string $key     khoá (vd IP)
 * @param int    $limit   số lượt tối đa trong cửa sổ
 * @param int    $window  độ dài cửa sổ (giây)
 * @param bool   $consume true: tính thêm 1 lượt nếu còn được phép
 * @return array [bool được phép, int số giây cần chờ]
 */
function kp_rate_limit($bucket, $key, $limit, $window, $consume = true)
{
    $dir = kp_rate_dir();
    $window = max(1, (int)$window);
    $limit = max(1, (int)$limit);
    // Fail closed on writable-storage errors. Otherwise brute-force protection
    // silently disappears when permissions/disk capacity change.
    if ($dir === '') {
        return [false, $window];
    }
    $file = $dir . '/' . hash('sha256', $bucket . '|' . $key) . '.json';
    $fh = @fopen($file, 'c+');
    if (!$fh) {
        kp_log('rate_limit file access failed');
        return [false, $window];
    }
    @chmod($file, 0600);
    try {
        if (!@flock($fh, LOCK_EX)) return [false, $window];
        rewind($fh);
        $raw = stream_get_contents($fh);
        if ($raw === false || strlen($raw) > 32768) return [false, $window];
        $hits = $raw === '' ? [] : json_decode($raw, true);
        if (!is_array($hits)) return [false, $window];
        $now = time();
        $hits = array_values(array_filter($hits, function ($t) use ($now, $window) {
            return is_int($t) && $t > $now - $window;
        }));
        $allowed = count($hits) < $limit;
        $retry = $allowed ? 0 : max(1, min($hits) + $window - $now);
        if ($consume && $allowed) $hits[] = $now;
        $serialized = json_encode($hits);
        if (!is_string($serialized)) return [false, $window];
        rewind($fh);
        if (!ftruncate($fh, 0) || fwrite($fh, $serialized) !== strlen($serialized)
            || !fflush($fh)) {
            kp_log('rate_limit persistence failed');
            return [false, $window];
        }
    } finally {
        @flock($fh, LOCK_UN);
        fclose($fh);
    }
    if (mt_rand(1, 60) === 1) kp_rate_gc($dir);
    return [$allowed, $retry];
}

function kp_rate_gc($dir)
{
    $files = glob($dir . '/*.json');
    if (!$files) {
        return;
    }
    $limit = time() - 2 * 86400;
    foreach ($files as $f) {
        if (@filemtime($f) < $limit) {
            @unlink($f);
        }
    }
}
