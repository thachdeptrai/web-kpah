<?php
/**
 * Compatibility facade for the original topup module URLs.
 * Canonical rate storage and access guards live in topup_core.php.
 * This prevents the two page groups using different rates.
 */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }
require_once KP_ROOT . '/includes/topup_core.php';
function kp_topup_defaults() { return ['gold'=>0,'luong'=>100,'luonglock'=>200]; }
function kp_topup_settings_path() { return kp_topup_root() . '/rates.json'; }
function kp_topup_save_rates(array $rates) { return kp_topup_rates($rates); }
function kp_topup_display($amount) { return number_format((float)$amount,0,',','.'); }
function kp_topup_convert($vnd,$rate) {
    try { return kp_topup_units((int)$vnd,(int)$rate); } catch (Throwable $e) { return 0; }
}
function kp_topup_require_admin() { return kp_topup_admin(); }
function kp_topup_require_user() { return kp_topup_user(); }
