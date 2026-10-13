<?php
/**
 * Kiểm tra dữ liệu đăng ký phía server (không tin frontend).
 */
if (!defined('KP_ROOT')) {
    http_response_code(403);
    exit;
}

/**
 * Tách mẫu PCRE ("/.../i") thành phần thân + cờ để truyền sang JavaScript.
 *
 * @return array [string $body, string $flags]
 */
function kp_pattern_for_js($pattern)
{
    $pattern = (string) $pattern;
    if (strlen($pattern) < 3) {
        return ['^[a-z0-9_]{4,20}$', ''];
    }
    $delim = $pattern[0];
    $end   = strrpos($pattern, $delim);
    if ($end === false || $end < 1) {
        return ['^[a-z0-9_]{4,20}$', ''];
    }
    $body  = substr($pattern, 1, $end - 1);
    $flags = substr($pattern, $end + 1);
    return [$body, strpos($flags, 'i') !== false ? 'i' : ''];
}

/** Chuẩn hoá số điện thoại Việt Nam về dạng +84xxxxxxxxx; trả null nếu sai. */
function kp_normalize_phone($raw)
{
    $p = preg_replace('/[\s.\-()]/', '', (string) $raw);
    if (preg_match('/^(?:\+?84|0)([35789]\d{8})$/', $p, $m)) {
        return '+84' . $m[1];
    }
    return null;
}

/**
 * @param array $in  dữ liệu thô từ $_POST
 * @return array [array $errors (field => message), array $clean]
 */
function kp_validate_registration(array $in)
{
    $errors = [];
    $clean  = ['username' => '', 'password' => '', 'phone' => '', 'email' => ''];

    $pattern = (string) kp_config('security.username_pattern', '/^[a-z0-9_]{4,20}$/');
    $hint    = (string) kp_config('security.username_hint', 'Tên tài khoản không hợp lệ.');
    $pwMin   = (int) kp_config('security.password_min', 6);
    $pwMax   = (int) kp_config('security.password_max', 32);

    /* --- Tên tài khoản --- */
    $username = isset($in['username']) && is_string($in['username']) ? strtolower(trim($in['username'])) : '';
    if ($username === '') {
        $errors['username'] = 'Vui lòng nhập tên tài khoản.';
    } else {
        $ok = @preg_match($pattern, $username);
        if ($ok === false) {
            kp_log('username_pattern không hợp lệ trong cấu hình');
            $errors['username'] = 'Hệ thống đang được cấu hình lại, vui lòng thử lại sau.';
        } elseif ($ok !== 1) {
            $errors['username'] = $hint;
        } elseif (in_array($username, (array) kp_config('security.reserved_usernames', []), true)) {
            $errors['username'] = 'Tên tài khoản này đã được hệ thống giữ chỗ, vui lòng chọn tên khác.';
        } elseif (kp_config('security.use_bad_word_table', false) && kp_username_has_bad_word($username)) {
            $errors['username'] = 'Tên tài khoản chứa từ không được phép, vui lòng chọn tên khác.';
        }
    }
    $clean['username'] = $username;

    /* --- Mật khẩu --- */
    $password  = isset($in['password']) && is_string($in['password']) ? $in['password'] : '';
    $password2 = isset($in['password2']) && is_string($in['password2']) ? $in['password2'] : '';

    if ($password === '') {
        $errors['password'] = 'Vui lòng nhập mật khẩu.';
    } elseif (!preg_match('/^[\x21-\x7E]+$/', $password)) {
        $errors['password'] = 'Mật khẩu chỉ gồm chữ cái không dấu, số và ký tự đặc biệt thông thường, không chứa khoảng trắng.';
    } elseif (strlen($password) < $pwMin || strlen($password) > $pwMax) {
        $errors['password'] = 'Mật khẩu phải dài từ ' . $pwMin . ' đến ' . $pwMax . ' ký tự.';
    } elseif ($username !== '' && strtolower($password) === $username) {
        $errors['password'] = 'Mật khẩu không được trùng với tên tài khoản.';
    }

    if ($password2 === '') {
        $errors['password2'] = 'Vui lòng nhập lại mật khẩu.';
    } elseif (!hash_equals($password, $password2)) {
        $errors['password2'] = 'Mật khẩu nhập lại chưa khớp.';
    }
    $clean['password'] = $password;

    /* --- Email bắt buộc --- */
    $email = isset($in['email']) && is_string($in['email']) ? strtolower(trim($in['email'])) : '';
    if ($email === '') {
        $errors['email'] = 'Vui lòng nhập email.';
    } elseif (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Địa chỉ email chưa đúng định dạng.';
    } else {
        $clean['email'] = $email;
    }

    /* --- Số điện thoại (tuỳ cấu hình) --- */
    $mode = 'required'; // Bắt buộc số điện thoại, không cho config local vô tình bỏ qua.
    if ($mode !== 'off') {
        $rawPhone = isset($in['phone']) && is_string($in['phone']) ? trim($in['phone']) : '';
        if ($rawPhone === '') {
            if ($mode === 'required') {
                $errors['phone'] = 'Vui lòng nhập số điện thoại.';
            }
        } else {
            $norm = kp_normalize_phone($rawPhone);
            if ($norm === null) {
                $errors['phone'] = 'Số điện thoại chưa đúng định dạng (ví dụ 0912345678).';
            } else {
                $clean['phone'] = $norm;
            }
        }
    }

    /* --- Điều khoản --- */
    if (kp_config('registration.terms_required', true) && empty($in['agree'])) {
        $errors['agree'] = 'Bạn cần đồng ý với điều khoản sử dụng để tiếp tục.';
    }

    return [$errors, $clean];
}

/** Kiểm tra tên có chứa từ trong bảng bad_word (khớp chuỗi con, không phân biệt hoa thường). */
function kp_username_has_bad_word($username)
{
    try {
        $rows = kp_db()->query('SELECT `word` FROM `bad_word`')->fetchAll(PDO::FETCH_COLUMN);
    } catch (\Throwable $e) {
        kp_log('đọc bad_word thất bại', $e);
        return false;
    }
    foreach ($rows as $w) {
        $w = strtolower(trim((string) $w));
        if ($w !== '' && strpos($username, $w) !== false) {
            return true;
        }
    }
    return false;
}
