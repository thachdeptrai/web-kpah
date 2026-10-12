<?php
/**
 * Trang mặc định: chuyển người chơi sang trang đăng nhập.
 */
require __DIR__ . '/includes/bootstrap.php';

// Tạo URL từ đường dẫn web hiện tại, tuyệt đối không dùng __DIR__ làm URL.
$scriptPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
$appBasePath = rtrim(dirname($scriptPath), '/.');
$loginUrl = ($appBasePath !== '' ? $appBasePath : '') . '/dang-nhap';
$accountUrl = ($appBasePath !== '' ? $appBasePath : '') . '/tai-khoan';

if (!empty($_SESSION['kp_user_id'])) {
    header('Location: ' . $accountUrl, true, 302);
    exit;
}
header('Location: ' . $loginUrl, true, 302);
exit;
