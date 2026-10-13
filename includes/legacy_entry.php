<?php
/** Redirect only direct browser requests for legacy .php URLs.
 * Apache's internal clean-route rewrite has REQUEST_URI set to the clean URL,
 * so it will not be redirected again. POST requests are NEVER redirected.
 */
function kp_legacy_redirect($legacy, $canonical)
{
    $method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
    if ($method!=='GET' && $method!=='HEAD') return;
    $uri=(string)($_SERVER['REQUEST_URI']??'');
    $path=parse_url($uri,PHP_URL_PATH);
    if (!is_string($path) || $path!==$legacy) return;
    $query=(string)($_SERVER['QUERY_STRING']??'');
    if (preg_match('/[\r\n]/',$query)) $query='';
    header('Location: '.$canonical.($query!==''?'?'.$query:''),true,302);
    exit;
}
