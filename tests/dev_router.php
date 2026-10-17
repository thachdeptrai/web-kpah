<?php
// PHP CLI test server only. Production uses Apache and the root .htaccess.
$path=rawurldecode(explode('?',$_SERVER['REQUEST_URI'],2)[0]);
// Permit versioned static filenames (e.g. platform-brands-7.3.1.woff2),
// but never dot segments, traversal, hidden files, or executable extensions.
if (preg_match('~^/assets/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_-]+(?:\.[a-zA-Z0-9_-]+)*\.(?:css|js|svg|png|jpe?g|gif|webp|ico|ttf|woff2?)$~iD', $path) && is_file(dirname(__DIR__).$path)) return false;
if (preg_match('~^/(?:app|config|tools|includes|storage|database|tests|docs|vendor|\.)~i',$path)
    || preg_match('~(?:\.sql|\.bak|\.zip|\.log|\.ini|\.pem|\.key)$~i',$path)
    || in_array($path,['/check.php','/config.register.php','/config.register.local.php','/README.md'],true)) {
    http_response_code(403); exit;
}
require dirname(__DIR__).'/index.php';
