<?php
/** Compatibility loader. Source of truth: app/Infrastructure/Configuration.php. */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }
require_once KP_ROOT . '/app/Infrastructure/Configuration.php';
