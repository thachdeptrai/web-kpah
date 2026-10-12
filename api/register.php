<?php
/**
 * API đăng ký tài khoản KPAH.  POST /api/register.php  -> JSON
 *
 * Thứ tự kiểm tra: phương thức -> kích thước -> CSRF -> honeypot -> thời gian điền form
 *   -> giới hạn tần suất -> validate + CAPTCHA -> giới hạn tài khoản/IP -> ghi database.
 */
require __DIR__ . '/../includes/bootstrap.php';

kp_send_security_headers(false);
header('Content-Type: application/json; charset=utf-8');

/** Gửi JSON và kết thúc. Luôn kèm CSRF token mới để trang cập nhật. */
function kp_reply(array $payload, $status = 200)
{
    http_response_code($status);
    $payload['csrf'] = kp_csrf_rotate();
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    kp_reply(['ok' => false, 'message' => 'Phương thức không được hỗ trợ.'], 405);
}

if (isset($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > 8192) {
    kp_reply(['ok' => false, 'message' => 'Dữ liệu gửi lên quá lớn.'], 413);
}

if (!kp_config('registration.open', true)) {
    kp_reply(['ok' => false, 'message' => 'Hiện tại hệ thống đang tạm ngừng đăng ký tài khoản.'], 403);
}

// 1) CSRF
$token = isset($_POST['csrf']) ? $_POST['csrf'] : '';
if (!kp_csrf_check($token)) {
    kp_reply([
        'ok'      => false,
        'code'    => 'csrf',
        'message' => 'Phiên làm việc đã hết hạn. Vui lòng thử gửi lại biểu mẫu.',
    ], 403);
}

// 2) Honeypot: ô ẩn mà người thật không điền
if (!empty($_POST['website'])) {
    kp_reply(['ok' => false, 'message' => 'Không thể xử lý yêu cầu. Vui lòng tải lại trang và thử lại.'], 400);
}

// 3) Gửi quá nhanh sau khi mở trang -> nghi ngờ bot
$minFill = (int) kp_config('security.min_fill_seconds', 2);
$opened  = isset($_SESSION['kp_form_ts']) ? (int) $_SESSION['kp_form_ts'] : 0;
if ($opened === 0 || (time() - $opened) < $minFill) {
    kp_reply(['ok' => false, 'message' => 'Bạn thao tác quá nhanh. Vui lòng đợi giây lát rồi gửi lại.'], 429);
}

// 4) Giới hạn số lượt gửi
$ip = kp_client_ip();
list($maxAttempts, $attemptWindow) = array_pad((array) kp_config('security.rate_attempts', [10, 600]), 2, 600);
list($allowed, $retry) = kp_rate_limit('reg_attempt', $ip, (int) $maxAttempts, (int) $attemptWindow, true);
if (!$allowed) {
    header('Retry-After: ' . $retry);
    kp_reply([
        'ok'      => false,
        'code'    => 'rate_limit',
        'message' => 'Bạn đã gửi quá nhiều yêu cầu. Vui lòng thử lại sau ' . max(1, (int) ceil($retry / 60)) . ' phút.',
    ], 429);
}

// 5) Validate + CAPTCHA (gộp lỗi để người dùng sửa một lần)
list($errors, $clean) = kp_validate_registration($_POST);

if (kp_captcha_enabled()) {
    if (!kp_captcha_available()) {
        kp_log('CAPTCHA bật nhưng PHP thiếu GD');
        kp_reply(['ok' => false, 'message' => 'CAPTCHA tạm thời chưa khả dụng, vui lòng thử lại sau.'], 503);
    }
    $captchaInput = isset($_POST['captcha']) ? $_POST['captcha'] : '';
    if (!kp_captcha_verify($captchaInput)) {
        $errors['captcha'] = 'Mã xác nhận chưa đúng hoặc đã hết hạn. Hãy nhập mã ở ảnh mới.';
    }
}

if ($errors) {
    kp_reply([
        'ok'      => false,
        'code'    => 'validation',
        'message' => 'Vui lòng kiểm tra lại các thông tin được đánh dấu.',
        'errors'  => $errors,
    ], 422);
}

// 6) Giới hạn số tài khoản được tạo từ một IP
list($maxOk, $okWindow) = array_pad((array) kp_config('security.rate_success', [5, 86400]), 2, 86400);
list($okAllowed, $okRetry) = kp_rate_limit('reg_success', $ip, (int) $maxOk, (int) $okWindow, false);
if (!$okAllowed) {
    header('Retry-After: ' . $okRetry);
    kp_reply([
        'ok'      => false,
        'code'    => 'rate_limit',
        'message' => 'Địa chỉ mạng của bạn đã tạo quá số tài khoản cho phép trong ngày. Vui lòng quay lại sau.',
    ], 429);
}

// 7) Ghi database
try {
    $result = kp_create_account($clean['username'], $clean['password'], $clean['phone']);
} catch (\Throwable $e) {
    kp_log('tạo tài khoản thất bại', $e);
    kp_reply(['ok' => false, 'message' => 'Hệ thống đang bận hoặc bảo trì. Vui lòng thử lại sau ít phút.'], 500);
}

if ($result === 'exists') {
    kp_reply([
        'ok'      => false,
        'code'    => 'validation',
        'message' => 'Tên tài khoản này đã có người sử dụng.',
        'errors'  => ['username' => 'Tên tài khoản đã tồn tại. Hãy chọn tên khác.'],
    ], 409);
}
if ($result !== 'ok') {
    kp_log('kp_create_account trả về: ' . $result);
    kp_reply(['ok' => false, 'message' => 'Chưa thể tạo tài khoản lúc này. Vui lòng thử lại sau.'], 503);
}

kp_rate_limit('reg_success', $ip, (int) $maxOk, (int) $okWindow, true);
session_regenerate_id(true);

kp_reply([
    'ok'       => true,
    'message'  => 'Tạo tài khoản thành công.',
    'username' => $clean['username'],
]);
