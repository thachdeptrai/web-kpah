<?php
/**
 * Điểm khởi động chung: hằng số, cấu hình, lỗi, múi giờ, session.
 * Mọi trang/API của module đăng ký đều require file này đầu tiên.
 */
if (defined('KP_BOOTSTRAPPED')) {
    return;
}
define('KP_BOOTSTRAPPED', true);
define('KP_ROOT', dirname(__DIR__));

if (PHP_VERSION_ID < 70200) {
    http_response_code(500);
    exit('KPAH register: can PHP >= 7.2');
}

// Không bao giờ hiển thị lỗi PHP ra trình duyệt (tránh lộ đường dẫn/SQL); chỉ ghi log.
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
error_reporting(E_ALL);

if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/config_loader.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/captcha.php';
require_once __DIR__ . '/validator.php';
require_once __DIR__ . '/account.php';
require_once __DIR__ . '/referrals.php';

date_default_timezone_set((string) kp_config('app.timezone', 'Asia/Ho_Chi_Minh'));
kp_session_start();
