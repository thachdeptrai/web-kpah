<?php
/** Compatibility loader. Source of truth: app/Payments/Topup.php. */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }
require_once KP_ROOT . '/app/Payments/Topup.php';
