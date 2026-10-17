<?php
/**
 * Nạp và hợp nhất cấu hình: mặc định -> local -> (tuỳ chọn) config.php dự án -> biến môi trường.
 */
if (!defined('KP_ROOT')) {
    http_response_code(403);
    exit;
}

/**
 * Đọc cấu hình theo đường dẫn dạng "security.password_min".
 * Không truyền tham số để lấy toàn bộ mảng.
 *
 * @param string|null $path
 * @param mixed       $default
 * @return mixed
 */
function kp_config($path = null, $default = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = kp_build_config();
    }
    if ($path === null) {
        return $cfg;
    }
    $cur = $cfg;
    foreach (explode('.', $path) as $seg) {
        if (is_array($cur) && array_key_exists($seg, $cur)) {
            $cur = $cur[$seg];
        } else {
            return $default;
        }
    }
    return $cur;
}

function kp_build_config()
{
    $cfg = require KP_ROOT . '/config/register.php';
    if (!is_array($cfg)) {
        $cfg = [];
    }

    // Existing VPS installations keep their local configuration after git pull.
    $local = is_file(KP_ROOT . '/config/register.local.php')
        ? KP_ROOT . '/config/register.local.php'
        : KP_ROOT . '/config.register.local.php';
    if (is_file($local)) {
        $l = require $local;
        if (is_array($l)) {
            $cfg = array_replace_recursive($cfg, $l);
        }
    }

    if (!empty($cfg['integration']['load_project_config'])) {
        $p = kp_include_project_config(KP_ROOT . '/config.php');
        if ($p) {
            $cfg = array_replace_recursive($cfg, array_intersect_key($p, array_flip(['db', 'security', 'captcha'])));
        }
    }

    $env = [
        'host' => 'KPAH_DB_HOST',
        'port' => 'KPAH_DB_PORT',
        'name' => 'KPAH_DB_NAME',
        'user' => 'KPAH_DB_USER',
        'pass' => 'KPAH_DB_PASS',
    ];
    foreach ($env as $key => $name) {
        $v = getenv($name);
        if ($v !== false && $v !== '') {
            $cfg['db'][$key] = $v;
        }
    }

    $giftcodeDbo = getenv('KPAH_GIFTCODE_DBO_ENABLED');
    if ($giftcodeDbo !== false && $giftcodeDbo !== '') {
        $cfg['giftcode']['dbo_enabled'] = in_array(strtolower(trim((string)$giftcodeDbo)), ['1', 'true', 'yes', 'on'], true);
    }

    return $cfg;
}

/**
 * Nạp config.php của dự án (chỉ khi bật integration.load_project_config).
 * Mọi output bị loại bỏ; chỉ nhận kết quả nếu file `return` một mảng.
 */
function kp_include_project_config($file)
{
    if (!is_file($file)) {
        return [];
    }
    $result = null;
    ob_start();
    try {
        $result = include $file;
    } catch (\Throwable $e) {
        $result = null;
    }
    ob_end_clean();
    return is_array($result) ? $result : [];
}
