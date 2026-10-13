<?php
/**
 * KPAH – Cấu hình trang đăng ký tài khoản (giá trị mặc định, KHÔNG chứa mật khẩu).
 *
 * - Thông tin kết nối database thật: đặt trong config.register.local.php
 *   (file này không được commit lên Git) hoặc dùng biến môi trường
 *   KPAH_DB_HOST / KPAH_DB_PORT / KPAH_DB_NAME / KPAH_DB_USER / KPAH_DB_PASS.
 * - File này chỉ được nạp bởi includes/bootstrap.php; truy cập trực tiếp sẽ bị chặn.
 */
if (!defined('KP_ROOT')) {
    http_response_code(403);
    exit;
}

return [
    'app' => [
        'name'      => 'KPAH',
        'version'   => '',                      // ví dụ '1.0.0' – để trống sẽ ẩn khỏi footer
        'timezone'  => 'Asia/Ho_Chi_Minh',      // dữ liệu regdate hiện có đang theo giờ Việt Nam
        'debug_log' => false,                   // true: ghi thêm thông điệp lỗi kỹ thuật vào error_log (KHÔNG hiển thị ra web)
    ],

    // Đặt true nếu config.php của dự án `return` một mảng có khoá 'db' / 'security' / 'captcha'.
    // Mặc định tắt để không xung đột với cấu hình hiện có.
    'integration' => [
        'load_project_config' => false,
    ],

    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'kpah',
        'user'    => '',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    'security' => [
        // Mẫu tên tài khoản (PCRE). Mặc định: 4–20 ký tự gồm chữ thường, số, gạch dưới.
        'username_pattern'   => '/^[a-z0-9_]{4,20}$/',
        'username_hint'      => 'Từ 4 đến 20 ký tự, chỉ gồm chữ thường (a–z), số và dấu gạch dưới (_).',
        'password_min'       => 6,
        'password_max'       => 32,
        'reserved_usernames' => ['admin', 'administrator', 'root', 'system', 'support', 'gm', 'mod', 'kpah', 'null', 'guest'],
        // true: từ chối tên tài khoản chứa từ trong bảng `bad_word` (khớp theo chuỗi con, có thể chặn nhầm).
        'use_bad_word_table' => false,

        // [số lần, số giây]
        'rate_attempts'      => [10, 600],      // tối đa 10 lượt gửi form / 10 phút / IP
        'rate_success'       => [5, 86400],     // tối đa 5 tài khoản được tạo / 24 giờ / IP
        'min_fill_seconds'   => 2,              // form gửi nhanh hơn mức này bị coi là bot

        // Nếu chạy sau Cloudflare/Proxy, đặt tên header chứa IP thật (vd 'HTTP_CF_CONNECTING_IP').
        // CHỈ đặt khi chắc chắn header do proxy của bạn gán, nếu không IP có thể bị giả mạo.
        'trusted_ip_header'     => '',
        'trust_forwarded_proto' => false,       // true nếu proxy gửi X-Forwarded-Proto và bạn tin nó

        'session_name' => 'KPAHREG',
        'admin_session_name' => 'KPAHADMIN', // admin và người chơi dùng phiên PHP riêng, tránh CSRF mất hiệu lực
        'cookie_path'  => '/',
    ],

    'mail' => [
        'from_email' => '',
        'from_name' => 'KPAH',
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_user' => '',
        'smtp_pass' => '',
        'smtp_secure' => 'tls',
    ],

    'captcha' => [
        'enabled' => true,
        'driver'  => 'auto',                    // auto: dùng GD nếu có, không thì tự chuyển sang SVG | gd | svg
        'length'  => 5,
        'ttl'     => 300,                       // giây
    ],

    'registration' => [
        'open'           => true,
        'phone'          => 'required',         // Bắt buộc số điện thoại khi đăng ký
        'terms_required' => true,
        'email_verification' => true,
        'email_code_ttl' => 600,
    ],

    // SMTP: cấu hình qua config.register.local.php hoặc biến môi trường; không commit mật khẩu.
    'links' => [
        'login'    => '',
        'download' => '',
        'home'     => '',
        'support'  => '',
    ],
];
