<?php
/**
 * Local-only header/CORS fixture for a read-only Vibe Check scan.
 * Does not use the database, invoke transactions, or expose application secrets.
 */
define('KP_ROOT', dirname(__DIR__, 2));
require KP_ROOT . '/app/includes/config_loader.php';
require KP_ROOT . '/app/includes/security.php';
kp_send_security_headers(true);
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="en"><head><meta charset="UTF-8"><title>Local security fixture</title></head><body>Local-only baseline</body></html>';
