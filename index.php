<?php
/**
 * Trang mặc định: chuyển người chơi sang trang đăng nhập.
 */
require __DIR__ . '/includes/bootstrap.php';

$loginUrl = '/dang-nhap';
$accountUrl = '/tai-khoan';

if (!empty($_SESSION['kp_user_id'])) {
    header('Location: ' . $accountUrl, true, 302);
    exit;
}
header('Location: ' . $loginUrl, true, 302);
exit;
