<?php
require __DIR__ . '/../includes/bootstrap.php';
kp_send_security_headers(false);
header('Content-Type: application/json; charset=utf-8');

function kp_change_password_reply(array $payload, $status = 200) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    kp_change_password_reply(['ok' => false, 'message' => 'Phương thức không hợp lệ.'], 405);
}
if (empty($_SESSION['kp_user_id'])) {
    kp_change_password_reply(['ok' => false, 'message' => 'Phiên đăng nhập đã hết hạn. Hãy đăng nhập lại.'], 401);
}
if (!kp_csrf_check($_POST['csrf'] ?? '')) {
    kp_change_password_reply(['ok' => false, 'message' => 'Phiên bảo mật hết hạn. Hãy tải lại trang.'], 403);
}

$userId = (int) $_SESSION['kp_user_id'];
list($allowed, $retry) = kp_rate_limit('change_password', $userId . '|' . kp_client_ip(), 6, 900, true);
if (!$allowed) {
    kp_change_password_reply(['ok' => false, 'message' => 'Bạn thử đổi mật khẩu quá nhiều lần. Hãy thử lại sau ít phút.'], 429);
}

$currentPassword = (string) ($_POST['current_password'] ?? '');
$newPassword = (string) ($_POST['new_password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');

if ($currentPassword === '' || strlen($currentPassword) > 128) {
    kp_change_password_reply(['ok' => false, 'message' => 'Hãy nhập mật khẩu hiện tại.'], 422);
}
if (strlen($newPassword) < 6 || strlen($newPassword) > 32 || preg_match('/\s/', $newPassword)) {
    kp_change_password_reply(['ok' => false, 'message' => 'Mật khẩu mới phải từ 6–32 ký tự và không chứa khoảng trắng.'], 422);
}
if (!hash_equals($newPassword, $confirmPassword)) {
    kp_change_password_reply(['ok' => false, 'message' => 'Mật khẩu mới và phần xác nhận không trùng nhau.'], 422);
}
if (hash_equals($currentPassword, $newPassword)) {
    kp_change_password_reply(['ok' => false, 'message' => 'Mật khẩu mới phải khác mật khẩu hiện tại.'], 422);
}

try {
    $db = kp_db();
    $st = $db->prepare('SELECT password FROM team_user WHERE id = ? LIMIT 1');
    $st->execute([$userId]);
    $currentHash = $st->fetchColumn();
    if (!is_string($currentHash) || $currentHash === '') {
        kp_change_password_reply(['ok' => false, 'message' => 'Không tìm thấy tài khoản.'], 404);
    }
    if (!kp_verify_account_password($currentPassword, $currentHash)) {
        kp_change_password_reply(['ok' => false, 'message' => 'Mật khẩu hiện tại không đúng.'], 422);
    }

    // Keep the account's existing hash family so the game server can still authenticate it.
    if (preg_match('/^\$2[aby]\$\d{2}\$/', $currentHash) === 1 || strpos($currentHash, '$argon2') === 0) {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    } else {
        $newHash = kp_game_password_hash($newPassword);
    }
    $up = $db->prepare('UPDATE team_user SET password = ? WHERE id = ?');
    $up->execute([$newHash, $userId]);

    kp_change_password_reply(['ok' => true, 'message' => 'Đổi mật khẩu thành công. Mật khẩu mới đã được lưu.']);
} catch (Throwable $e) {
    kp_log('đổi mật khẩu trong trang tài khoản thất bại', $e);
    kp_change_password_reply(['ok' => false, 'message' => 'Chưa thể đổi mật khẩu lúc này. Hãy thử lại sau.'], 503);
}
