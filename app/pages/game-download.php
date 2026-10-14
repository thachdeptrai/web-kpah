<?php
/** Stream only the admin-selected binary from protected storage. */
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/game_downloads.php';
kp_send_security_headers(false);
header('X-Robots-Tag: noindex, nofollow');
header('Content-Type: application/octet-stream');
header('Cache-Control: no-store, max-age=0');
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}
$key = (string)($_GET['nen-tang'] ?? '');
$platforms = kp_game_platforms();
if (!isset($platforms[$key])) { http_response_code(404); exit; }
$catalog = kp_game_download_catalog();
$row = $catalog[$key];
if (!$row['enabled'] || $row['source'] !== 'file'
    || !kp_game_download_filename_valid($row['file'], $key)) {
    http_response_code(404);
    exit;
}
$path = kp_game_download_dir() . '/' . $row['file'];
if (!is_file($path) || is_link($path)) { http_response_code(404); exit; }
$fp = @fopen($path, 'rb');
if (!$fp) { http_response_code(404); exit; }
$stat = fstat($fp);
$size = $stat === false ? 0 : (int)$stat['size'];
if ($size < 1) { fclose($fp); http_response_code(404); exit; }
$start = 0;
$end = $size - 1;
$range = trim((string)($_SERVER['HTTP_RANGE'] ?? ''));
if ($range !== '') {
    // One byte range, including suffix ranges; reject multi-range and malformed requests.
    if (!preg_match('/^bytes=(\d*)-(\d*)$/D', $range, $m)
        || ($m[1] === '' && $m[2] === '')) {
        fclose($fp);
        header('Content-Range: bytes */' . $size);
        http_response_code(416);
        exit;
    }
    if ($m[1] === '') {
        $suffix = (int)$m[2];
        if ($suffix <= 0) { fclose($fp); header('Content-Range: bytes */'.$size); http_response_code(416); exit; }
        $start = max(0, $size - $suffix);
    } else {
        $start = (int)$m[1];
        if ($m[2] !== '') $end = min($size - 1, (int)$m[2]);
    }
    if ($start >= $size || $end < $start) {
        fclose($fp);
        header('Content-Range: bytes */' . $size);
        http_response_code(416);
        exit;
    }
    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
}
$original = (string)$row['filename'];
$ext = strtolower(pathinfo($row['file'], PATHINFO_EXTENSION));
$base = preg_replace('/[^A-Za-z0-9._-]/', '_', $original);
if ($base === '' || $base === '.' || $base === '..') $base = 'Nghien_KPAH_' . $key . '.' . $ext;
if (strtolower(pathinfo($base, PATHINFO_EXTENSION)) !== $ext) $base .= '.' . $ext;
header('Content-Disposition: attachment; filename="' . $base . '"');
header('Content-Length: ' . ($end - $start + 1));
header('Accept-Ranges: bytes');
if ($row['sha256'] !== '') header('ETag: "' . $row['sha256'] . '"');
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
if ($method === 'HEAD') { fclose($fp); exit; }
@set_time_limit(0);
if ($start > 0 && fseek($fp, $start, SEEK_SET) !== 0) { fclose($fp); exit; }
$remaining = $end - $start + 1;
while ($remaining > 0 && !feof($fp) && !connection_aborted()) {
    $chunk = fread($fp, min(1024 * 1024, $remaining));
    if ($chunk === false || $chunk === '') break;
    echo $chunk;
    $remaining -= strlen($chunk);
    flush();
}
fclose($fp);
