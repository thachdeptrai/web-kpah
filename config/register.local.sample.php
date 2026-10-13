<?php
/**
 * Sao chép file này thành config/register.local.php rồi điền thông tin thật.
 * File config/register.local.php KHÔNG được commit lên Git (đã có trong .gitignore).
 */
if (!defined('KP_ROOT')) {
    http_response_code(403);
    exit;
}

return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'kpah',
        'user' => 'TEN_USER_DB',
        'pass' => 'MAT_KHAU_DB',
    ],
    'mail' => [
        'from_email' => 'your-email@example.com',
        'from_name' => 'KPAH',
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 587,
        'smtp_user' => 'your-email@gmail.com',
        'smtp_pass' => 'GMAIL_APP_PASSWORD',
        'smtp_secure' => 'tls',
    ],
    'registration' => [
        'phone' => 'required',
        'email_verification' => true,
        'email_code_ttl' => 600,
    ],
    'app' => [
        'version' => '1.0.0',
    ],
    'links' => [
        'login'    => '',   // điền đường dẫn trang đăng nhập THẬT của bạn
        'download' => '',   // điền link tải game (nếu có)
    ],
];
