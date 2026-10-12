<?php
/**
 * Trang chủ: hiển thị trang đăng ký KPAH.
 * Mở http://localhost/kpah-register/ sẽ vào thẳng form đăng ký.
 * (Các đường dẫn assets/, api/, captcha.php đều tương đối nên hoạt động cả ở thư mục gốc lẫn thư mục con.)
 */
if (!empty($_SESSION['kp_user_id'])) {
    header('Location: home.php');
    exit;
}
require __DIR__ . '/register.php';
