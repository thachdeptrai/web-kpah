<?php
/**
 * Đọc Điểm nông dân từ tob_char.pInfo, đúng thứ tự Char.java:
 * index 117 = điểm, 118 = hộp quà/ngày, 122 = thời gian farm/ngày (ms).
 * Chỉ đọc, không sửa hoặc lưu lại pInfo.
 */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }

/** NULL = chưa có trường hoặc dữ liệu không hợp lệ, khác với điểm 0. */
function kp_farmer_field($pInfo, int $index, int $max = PHP_INT_MAX): ?int
{
    if (!is_string($pInfo) || $pInfo === '' || $index < 0) return null;
    $parts = explode(',', $pInfo, $index + 2);
    if (!array_key_exists($index, $parts)) return null;
    $value = trim($parts[$index]);
    if ($value === '' || !ctype_digit($value)) return null;
    $digits = ltrim($value, '0');
    if ($digits === '') $digits = '0';
    $limit = (string)$max;
    if (strlen($digits) > strlen($limit)
        || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) return null;
    return (int)$digits;
}

function kp_farmer_points($pInfo): ?int
{
    return kp_farmer_field($pInfo, 117, 2147483647);
}
