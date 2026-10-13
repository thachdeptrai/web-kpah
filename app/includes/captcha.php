<?php
/**
 * CAPTCHA ảnh (GD) – mã lưu trong session phía server, dùng một lần, có hạn dùng.
 */
if (!defined('KP_ROOT')) {
    http_response_code(403);
    exit;
}

function kp_captcha_enabled()
{
    return (bool) kp_config('captcha.enabled', true);
}

/** Có GD (ảnh PNG) hay không. */
function kp_captcha_gd_available()
{
    return function_exists('imagecreatetruecolor') && function_exists('imagepng');
}

/** Chế độ vẽ: 'gd' (PNG) hoặc 'svg' (PHP thuần, không cần GD). Cấu hình captcha.driver: auto|gd|svg */
function kp_captcha_driver()
{
    $d = (string) kp_config('captcha.driver', 'auto');
    if ($d === 'svg') {
        return 'svg';
    }
    if ($d === 'gd') {
        return kp_captcha_gd_available() ? 'gd' : 'svg';
    }
    return kp_captcha_gd_available() ? 'gd' : 'svg';
}

/** CAPTCHA luôn dùng được: nếu thiếu GD sẽ tự chuyển sang SVG. */
function kp_captcha_available()
{
    return true;
}

/** Tạo mã mới, lưu vào session và trả về mã (chữ hoa). */
function kp_captcha_generate()
{
    $chars  = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // bỏ I, O, 0, 1 dễ nhầm
    $length = max(4, min(8, (int) kp_config('captcha.length', 5)));
    $code   = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    $_SESSION['kp_captcha'] = [
        'code' => $code,
        'exp'  => time() + max(60, (int) kp_config('captcha.ttl', 300)),
    ];
    return $code;
}

/** Kiểm tra mã người dùng nhập. Mã bị huỷ sau mỗi lần kiểm tra (dùng một lần). */
function kp_captcha_verify($input)
{
    $stored = isset($_SESSION['kp_captcha']) ? $_SESSION['kp_captcha'] : null;
    unset($_SESSION['kp_captcha']);

    if (!is_array($stored) || empty($stored['code']) || empty($stored['exp']) || $stored['exp'] < time()) {
        return false;
    }
    if (!is_string($input)) {
        return false;
    }
    $input = strtoupper(preg_replace('/\s+/', '', $input));
    return $input !== '' && hash_equals((string) $stored['code'], $input);
}

/** Vẽ ảnh CAPTCHA (PNG nếu có GD, ngược lại SVG) và gửi thẳng ra output. */
function kp_captcha_render($code)
{
    if (kp_captcha_driver() === 'gd') {
        kp_captcha_render_gd($code);
    } else {
        kp_captcha_render_svg($code);
    }
}

/** Số thực ngẫu nhiên trong [min, max]. */
function kp_rand_float($min, $max)
{
    return $min + (random_int(0, 10000) / 10000) * ($max - $min);
}

/**
 * CAPTCHA dạng SVG: ký tự được vẽ bằng nét (path) có xoay + rung nhẹ, KHÔNG có thẻ <text>,
 * nên không đọc được mã trực tiếp từ mã nguồn ảnh.
 */
function kp_captcha_render_svg($code)
{
    $glyphs = require KP_ROOT . '/app/includes/captcha_glyphs.php';
    $w = 168;
    $h = 58;
    $len = strlen($code);
    $slot = $w / $len;
    $palette = ['#f6dc9b', '#cfd6ff', '#e2b45a', '#9fb0ff', '#ffe7b0'];

    $svg  = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '">';
    $svg .= '<defs><linearGradient id="g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#141130"/><stop offset="1" stop-color="#1f1a48"/></linearGradient></defs>';
    $svg .= '<rect width="' . $w . '" height="' . $h . '" fill="url(#g)"/>';

    // nhiễu nền: đường cong + chấm
    for ($i = 0; $i < 6; $i++) {
        $svg .= sprintf(
            '<path d="M%.1f %.1f Q%.1f %.1f %.1f %.1f" stroke="%s" stroke-opacity=".35" stroke-width="1.2" fill="none"/>',
            kp_rand_float(0, $w), kp_rand_float(0, $h), kp_rand_float(0, $w), kp_rand_float(0, $h),
            kp_rand_float(0, $w), kp_rand_float(0, $h), $palette[random_int(0, 4)]
        );
    }
    for ($i = 0; $i < 40; $i++) {
        $svg .= sprintf('<circle cx="%.1f" cy="%.1f" r="%.1f" fill="#cfd6ff" fill-opacity=".35"/>', kp_rand_float(0, $w), kp_rand_float(0, $h), kp_rand_float(.5, 1.3));
    }

    // ký tự
    for ($i = 0; $i < $len; $i++) {
        $ch = $code[$i];
        if (!isset($glyphs[$ch])) {
            continue;
        }
        $s     = kp_rand_float(5.6, 6.3);
        $ang   = deg2rad(kp_rand_float(-15, 15));
        $cos   = cos($ang);
        $sin   = sin($ang);
        $cx    = $slot * ($i + 0.5) + kp_rand_float(-1.5, 1.5);
        $cy    = $h / 2 + kp_rand_float(-2, 2);
        $color = $palette[random_int(0, 4)];
        $d     = '';
        foreach ($glyphs[$ch] as $stroke) {
            foreach ($stroke as $k => $pt) {
                $x0 = ($pt[0] - 2) * $s + kp_rand_float(-0.9, 0.9);
                $y0 = ($pt[1] - 3) * $s + kp_rand_float(-0.9, 0.9);
                $x  = $x0 * $cos - $y0 * $sin + $cx;
                $y  = $x0 * $sin + $y0 * $cos + $cy;
                $d .= ($k === 0 ? 'M' : 'L') . round($x, 1) . ' ' . round($y, 1) . ' ';
            }
        }
        $svg .= '<path d="' . trim($d) . '" stroke="' . $color . '" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" fill="none"/>';
    }

    // đường cắt ngang qua chữ
    $svg .= sprintf(
        '<path d="M0 %.1f C%.1f %.1f %.1f %.1f %d %.1f" stroke="#ff8a7a" stroke-opacity=".55" stroke-width="1.4" fill="none"/>',
        kp_rand_float(18, 40), $w * 0.3, kp_rand_float(5, 52), $w * 0.65, kp_rand_float(5, 52), $w, kp_rand_float(18, 40)
    );
    $svg .= '<rect x=".5" y=".5" width="' . ($w - 1) . '" height="' . ($h - 1) . '" rx="6" fill="none" stroke="#a0aaff" stroke-opacity=".3"/>';
    $svg .= '</svg>';

    if (!headers_sent()) {
        header('Content-Type: image/svg+xml; charset=utf-8');
    }
    echo $svg;
}

/** Vẽ ảnh PNG (cần GD) và gửi thẳng ra output. */
function kp_captcha_render_gd($code)
{
    $w = 168;
    $h = 58;
    $len = strlen($code);

    $img = imagecreatetruecolor($w, $h);
    imagealphablending($img, true);

    // nền gradient tím than
    for ($y = 0; $y < $h; $y++) {
        $t = $y / $h;
        $c = imagecolorallocate($img, (int) (20 + 16 * $t), (int) (17 + 10 * $t), (int) (44 + 26 * $t));
        imageline($img, 0, $y, $w, $y, $c);
    }

    // nhiễu: đường thẳng và chấm
    for ($i = 0; $i < 7; $i++) {
        $c = imagecolorallocatealpha($img, random_int(90, 190), random_int(90, 190), random_int(150, 255), random_int(55, 100));
        imageline($img, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $c);
    }
    for ($i = 0; $i < 90; $i++) {
        $c = imagecolorallocatealpha($img, random_int(120, 255), random_int(120, 255), random_int(160, 255), random_int(70, 110));
        imagesetpixel($img, random_int(0, $w - 1), random_int(0, $h - 1), $c);
    }

    $font   = KP_ROOT . '/assets/fonts/DejaVuSans-Bold.ttf';
    $useTtf = function_exists('imagettftext') && is_file($font);

    if ($useTtf) {
        $slot = ($w - 20) / $len;
        for ($i = 0; $i < $len; $i++) {
            $color = imagecolorallocate($img, random_int(190, 255), random_int(170, 255), random_int(120, 255));
            $angle = random_int(-18, 18);
            $x = (int) (12 + $i * $slot + random_int(0, 4));
            $y = random_int(38, 46);
            imagettftext($img, random_int(24, 28), $angle, $x, $y, $color, $font, $code[$i]);
        }
    } else {
        // Dự phòng khi server không có FreeType: vẽ chữ bitmap nhỏ rồi phóng to.
        $sw = $len * 11 + 4;
        $sh = 19;
        $small = imagecreatetruecolor($sw, $sh);
        imagefill($small, 0, 0, imagecolorallocate($small, 24, 20, 56));
        for ($i = 0; $i < $len; $i++) {
            $color = imagecolorallocate($small, random_int(190, 255), random_int(170, 255), random_int(120, 255));
            imagestring($small, 5, 3 + $i * 11, random_int(0, 3), $code[$i], $color);
        }
        imagecopyresampled($img, $small, 0, 0, 0, 0, $w, $h, $sw, $sh);
        if (PHP_VERSION_ID < 80000) {
            imagedestroy($small);
        }
    }

    // làm méo theo sóng sin
    $out = imagecreatetruecolor($w, $h);
    imagefilledrectangle($out, 0, 0, $w, $h, imagecolorallocate($out, 24, 20, 56));
    $amp    = 3;
    $period = random_int(28, 42);
    $phase  = random_int(0, 628) / 100;
    for ($x = 0; $x < $w; $x++) {
        $dy = (int) round(sin($x / $period * 2 * M_PI + $phase) * $amp);
        imagecopy($out, $img, $x, $dy, $x, 0, 1, $h);
    }

    // viền mảnh
    imagerectangle($out, 0, 0, $w - 1, $h - 1, imagecolorallocatealpha($out, 160, 170, 255, 90));

    if (!headers_sent()) {
        header('Content-Type: image/png');
    }
    imagepng($out, null, 6);

    if (PHP_VERSION_ID < 80000) {
        imagedestroy($img);
        imagedestroy($out);
    }
}
