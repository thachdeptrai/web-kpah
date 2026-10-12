<?php
/**
 * Trang mặc định: chuyển người chơi sang trang đăng nhập.
 */
require __DIR__ . '/includes/bootstrap.php';
if (!empty($_SESSION['kp_user_id'])) {
    header('Location: /tai-khoan');
    exit;
}
header('Location: /dang-nhap');
exit;
