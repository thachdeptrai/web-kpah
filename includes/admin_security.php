<?php
/**
 * Central admin authentication and step-up security.
 * No schema migrations. All MFA/replay/attempt state is stored outside the DB.
 */
if (!defined('KP_ROOT')) {
    http_response_code(403);
    exit;
}

function kp_admin_env($name, $fallback = '')
{
    $value = getenv($name);
    return is_string($value) && $value !== '' ? trim($value) : $fallback;
}

function kp_admin_allowed_ip()
{
    $allowed = kp_admin_env('KPAH_ADMIN_ALLOWED_IPS');
    if ($allowed === '') return true;
    $ip = kp_client_ip();
    foreach (explode(',', $allowed) as $value) {
        $candidate = trim($value);
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)
            && hash_equals($candidate, $ip)) return true;
    }
    return false;
}

/**
 * Validate the session CSRF token and, when present, browser Origin.
 * The origin check is additional defense; it never substitutes the token.
 */
function kp_admin_csrf_valid($token)
{
    if (!kp_csrf_check($token)) return false;
    if (isset($_SERVER['HTTP_ORIGIN']) && (string)$_SERVER['HTTP_ORIGIN'] !== '') {
        $origin = parse_url((string)$_SERVER['HTTP_ORIGIN']);
        $request = parse_url('http://'.(string)($_SERVER['HTTP_HOST'] ?? ''));
        $sourceHost = is_array($origin) ? ($origin['host'] ?? '') : '';
        $targetHost = is_array($request) ? ($request['host'] ?? '') : '';
        if (!is_string($sourceHost) || !is_string($targetHost)
            || $sourceHost === '' || $targetHost === ''
            || !hash_equals(strtolower($targetHost), strtolower($sourceHost))) {
            return false;
        }
    }
    return true;
}

function kp_admin_log_event($event, $adminId = 0)
{
    // Do not log credentials, session IDs, OTP, full POST bodies, or raw IPs.
    $safe = preg_replace('/[^a-z0-9_-]/', '', strtolower((string)$event));
    kp_log('admin_security '.$safe.' actor='.(int)$adminId);
}

function kp_admin_deny($redirect = '/admin/login.php')
{
    foreach ([
        'kp_admin_id', 'kp_admin_username', 'kp_admin_role',
        'kp_admin_last_seen', 'kp_admin_session_started',
        'kp_admin_session_rotation', 'kp_admin_ua_hash', 'kp_admin_totp_ok'
    ] as $name) unset($_SESSION[$name]);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
        kp_csrf_rotate();
    }
    header('Location: '.$redirect, true, 302);
    exit;
}

/**
 * Rate limiting for admin MUST fail closed if file locking/storage fails.
 * Call separately for username and IP to stop account-targeted/distributed attacks.
 */
function kp_admin_limit($bucket, $value, $max, $window)
{
    $dir = kp_rate_dir();
    if ($dir === '') {
        kp_admin_log_event('limit_storage_unavailable');
        return [false, $window];
    }
    $path = $dir.'/admin-'.hash('sha256', $bucket.'|'.$value).'.json';
    $fp = @fopen($path, 'c+');
    if (!$fp) {
        kp_admin_log_event('limit_file_unavailable');
        return [false, $window];
    }
    @chmod($path, 0600);
    try {
        if (!flock($fp, LOCK_EX)) return [false, $window];
        rewind($fp);
        $raw = stream_get_contents($fp);
        if ($raw === false) return [false, $window];
        $hits = $raw === '' ? [] : json_decode($raw, true);
        if (!is_array($hits)) return [false, $window];
        $now = time();
        $hits = array_values(array_filter($hits, function ($t) use ($now, $window) {
            return is_int($t) && $t > $now-$window;
        }));
        $allowed = count($hits) < $max;
        $retry = $allowed ? 0 : max(1, min($hits) + $window-$now);
        if ($allowed) $hits[] = $now;
        $next = json_encode($hits);
        if (!is_string($next)) return [false, $window];
        rewind($fp);
        if (!ftruncate($fp, 0) || fwrite($fp, $next) !== strlen($next) || !fflush($fp)) {
            kp_admin_log_event('limit_write_failed');
            return [false, $window];
        }
        return [$allowed, $retry];
    } finally {
        @flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function kp_admin_totp_enabled()
{
    return kp_admin_env('KPAH_ADMIN_REQUIRE_TOTP') === '1'
        || kp_admin_env('KPAH_ADMIN_TOTP_SECRETS') !== ''
        || kp_admin_env('KPAH_ADMIN_TOTP_SECRET') !== '';
}

function kp_admin_totp_secret($username)
{
    $json = kp_admin_env('KPAH_ADMIN_TOTP_SECRETS');
    if ($json !== '') {
        $map = json_decode($json, true);
        if (!is_array($map)) return '';
        $name = strtolower((string)$username);
        foreach ($map as $key => $value) {
            if (is_string($key) && strtolower($key) === $name && is_string($value)) {
                return $value;
            }
        }
        return ''; // Fail closed for admin accounts missing from the map.
    }
    // Single-admin deployment compatibility: never commit this value.
    return kp_admin_env('KPAH_ADMIN_TOTP_SECRET');
}

function kp_admin_base32_decode($input)
{
    $s = strtoupper(preg_replace('/[\s-]+/', '', trim((string)$input)));
    if (strlen($s) < 16 || strlen($s) > 128 || !preg_match('/^[A-Z2-7]+$/D', $s)) return false;
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $buffer = 0;
    $bits = 0;
    $out = '';
    for ($i = 0; $i < strlen($s); $i++) {
        $idx = strpos($alphabet, $s[$i]);
        if ($idx === false) return false;
        $buffer = ($buffer << 5) | $idx;
        $bits += 5;
        if ($bits >= 8) {
            $bits -= 8;
            $out .= chr(($buffer >> $bits) & 255);
            $buffer &= (1 << $bits) - 1;
        }
    }
    return strlen($out) >= 10 ? $out : false;
}

/** RFC 6238 (HMAC-SHA1, 6 digits, 30-second steps; one-step clock tolerance). */
function kp_admin_totp_step($base32, $submitted, $time = null)
{
    if (!is_string($submitted) || !preg_match('/^[0-9]{6}$/D', $submitted)) return false;
    $secret = kp_admin_base32_decode($base32);
    if ($secret === false) return false;
    $nowStep = intdiv($time === null ? time() : (int)$time, 30);
    foreach ([$nowStep, $nowStep-1, $nowStep+1] as $step) {
        if ($step < 0) continue;
        $counter = pack('N2', ($step >> 32) & 0xffffffff, $step & 0xffffffff);
        $mac = hash_hmac('sha1', $counter, $secret, true);
        $offset = ord($mac[19]) & 15;
        $value = ((ord($mac[$offset]) & 127) << 24)
               | ((ord($mac[$offset+1]) & 255) << 16)
               | ((ord($mac[$offset+2]) & 255) << 8)
               | (ord($mac[$offset+3]) & 255);
        $candidate = str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
        if (hash_equals($candidate, $submitted)) return $step;
    }
    return false;
}

/** Prevent reusing the same TOTP step for another login on this server. */
function kp_admin_totp_consume($adminId, $step)
{
    $dir = kp_rate_dir();
    if ($dir === '' || (int)$adminId <= 0 || (int)$step < 0) return false;
    $path = $dir.'/admin-totp-replay-'.(int)$adminId.'.txt';
    $fp = @fopen($path, 'c+');
    if (!$fp) return false;
    @chmod($path, 0600);
    try {
        if (!flock($fp, LOCK_EX)) return false;
        rewind($fp);
        $raw = stream_get_contents($fp);
        if ($raw === false || strlen($raw) > 32) return false;
        if (trim($raw) !== '' && (!ctype_digit(trim($raw)) || (int)trim($raw) >= (int)$step)) {
            return false;
        }
        rewind($fp);
        $text = (string)(int)$step;
        return ftruncate($fp, 0) && fwrite($fp, $text) === strlen($text) && fflush($fp);
    } finally {
        @flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function kp_admin_create_session(array $admin, $totpOk)
{
    session_regenerate_id(true);
    $_SESSION['kp_admin_id'] = (int)$admin['id'];
    $_SESSION['kp_admin_username'] = (string)$admin['username'];
    $_SESSION['kp_admin_role'] = 'admin';
    // A password-only session must NOT remain valid when MFA is enabled later.
    $_SESSION['kp_admin_totp_ok'] = kp_admin_totp_enabled() && (bool)$totpOk;
    $_SESSION['kp_admin_last_seen'] = time();
    $_SESSION['kp_admin_session_started'] = time();
    $_SESSION['kp_admin_session_rotation'] = time();
    $_SESSION['kp_admin_ua_hash'] = hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    // Prevent continuing under a player session in the admin area.
    unset($_SESSION['kp_user_id'], $_SESSION['kp_user_username'], $_SESSION['kp_user_last_seen']);
    kp_csrf_rotate();
    kp_admin_log_event('login_success', (int)$admin['id']);
}

function kp_admin_require($redirect = '/admin/login.php')
{
    $id = (int)($_SESSION['kp_admin_id'] ?? 0);
    $now = time();
    if ($id <= 0 || ($_SESSION['kp_admin_role'] ?? '') !== 'admin'
        || empty($_SESSION['kp_admin_last_seen'])
        || $now-(int)$_SESSION['kp_admin_last_seen'] > 1800
        || empty($_SESSION['kp_admin_session_started'])
        || $now-(int)$_SESSION['kp_admin_session_started'] > 28800
        || empty($_SESSION['kp_admin_ua_hash'])
        || !hash_equals((string)$_SESSION['kp_admin_ua_hash'],
            hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? '')))
        || (kp_admin_totp_enabled() && empty($_SESSION['kp_admin_totp_ok']))
    ) kp_admin_deny($redirect);

    // Optional allowlist of literal IPs, NOT untrusted HTTP-forwarded IP values.
    if (!kp_admin_allowed_ip()) {
        kp_admin_log_event('ip_not_allowed', $id);
        http_response_code(403);
        exit('Truy cập quản trị bị giới hạn.');
    }

    $st = kp_db()->prepare(
        "SELECT id, username FROM kpah_web_admins WHERE id = ? AND role = 'admin' AND is_active = 1 LIMIT 1"
    );
    $st->execute([$id]);
    $admin = $st->fetch(PDO::FETCH_ASSOC);
    if (!$admin) kp_admin_deny($redirect);
    $_SESSION['kp_admin_last_seen'] = $now;
    $_SESSION['kp_admin_username'] = (string)$admin['username'];
    if (empty($_SESSION['kp_admin_session_rotation'])
        || $now-(int)$_SESSION['kp_admin_session_rotation'] > 900) {
        session_regenerate_id(true);
        $_SESSION['kp_admin_session_rotation'] = $now;
    }
    return $admin;
}
