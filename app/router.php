<?php
$catalog = require dirname(__DIR__) . '/config/routes.php';
$uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$path = explode('?', $uri, 2)[0];
// A top-level navigation to a public .js URL is a display-only preview.
// Apache rewrites only GET + document/navigate here. Recheck all conditions
// server-side: a caller must never use a query parameter to load PHP files.
if (isset($_GET['__kpah_js_preview'])) {
    $asset = $_GET['__kpah_js_preview'];
    if (is_string($asset)
        && preg_match('/\\A[a-z0-9_-]+\\.js\\z/iD', $asset)
        && $path === '/assets/js/' . $asset
        && strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET'
        && strtolower((string)($_SERVER['HTTP_SEC_FETCH_MODE'] ?? '')) === 'navigate'
        && strtolower((string)($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '')) === 'document') {
        $_GET['asset'] = $asset;
        require __DIR__ . '/pages/js-navigation-preview.php';
        exit;
    }
}

// Decode once; reject ambiguous separators and control characters.
if (preg_match('/%(?:2f|5c|00|0a|0d)/i', $path)) {
    http_response_code(400);
    exit('Đường dẫn không hợp lệ.');
}
$path = rawurldecode($path);
if (strpos($path, '\\') !== false || preg_match('/[\x00-\x1f\x7f]/', $path)) {
    http_response_code(400);
    exit('Đường dẫn không hợp lệ.');
}
$normalized = $path === '/' ? '/' : rtrim($path, '/');
$canonical = $catalog['aliases'][$normalized] ?? $normalized;
$route = $catalog['routes'][$canonical] ?? null;
if ($route === null) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    require __DIR__ . '/pages/not-found.php';
    exit;
}
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
// Never redirect POST bodies, JSON endpoints or payment callbacks.
if ($route['redirect_legacy'] && in_array($method, ['GET', 'HEAD'], true) && $path !== $canonical) {
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    if (preg_match('/[\r\n]/', $query)) { http_response_code(400); exit; }
    header('Location: ' . $canonical . ($query !== '' ? '?' . $query : ''), true, 301);
    exit;
}
define('KP_ROUTE_SCOPE', $route['scope']);
require dirname(__DIR__) . '/' . $route['file'];
