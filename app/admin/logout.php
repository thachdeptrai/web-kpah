<?php
require __DIR__ . '/../includes/bootstrap.php';
// GET must not mutate a session. A malicious cross-site navigation/image may
// trigger GET without user intent (SameSite=Lax still permits top-level GET).
kp_send_security_headers(true);
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'GET' || $method === 'HEAD') {
    header('Content-Type: text/html; charset=utf-8');
    if ($method === 'HEAD') exit;
    $csrf = kp_csrf_token();
    ?><!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Đăng xuất quản trị · KPAH</title>
<link rel="stylesheet" href="/assets/css/login.css"></head>
<body><main style="max-width:420px;margin:12vh auto;padding:24px;background:#fff;border:1px solid #d9e3ef;border-radius:16px">
<h1>Đăng xuất quản trị</h1><p>Bạn có chắc chắn muốn kết thúc phiên đăng nhập hiện tại?</p>
<form method="post" action=""><input type="hidden" name="csrf" value="<?= kp_e($csrf) ?>">
<button type="submit">Xác nhận đăng xuất</button>
<a href="/quan-tri">Quay lại</a></form></main></body></html><?php
    exit;
}
if ($method !== 'POST') {
    header('Allow: GET, HEAD, POST');
    http_response_code(405);
    exit('Phương thức không hợp lệ.');
}
if (!kp_csrf_check($_POST['csrf'] ?? null)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Phiên bảo mật không hợp lệ. Hãy tải lại trang rồi thử lại.');
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000, 'path' => $params['path'],
        'domain' => $params['domain'], 'secure' => $params['secure'],
        'httponly' => $params['httponly'], 'samesite' => 'Lax'
    ]);
}
session_destroy();
header('Location: /quan-tri/dang-nhap', true, 303);
exit;
