<?php
/**
 * Kết nối MySQL/MariaDB bằng PDO, đọc thông tin từ cấu hình (không ghi cứng).
 */
if (!defined('KP_ROOT')) {
    http_response_code(403);
    exit;
}

/** @return PDO */
function kp_db()
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = kp_config('db');
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $c['host'],
        (int) $c['port'],
        $c['name'],
        preg_replace('/[^a-z0-9]/i', '', (string) $c['charset'])
    );
    $pdo = new PDO($dsn, (string) $c['user'], (string) $c['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 5,
    ]);
    return $pdo;
}
