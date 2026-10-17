<?php
/** Player-facing notification and security history, optional until migration installed. */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }
function kp_center_event($userId, $type) {
    if ((int)$userId < 1 || !in_array($type, ['login','password_change'], true)) return;
    try {
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        // The UA header is untrusted input; prevent invalid UTF-8 and control characters.
        $ua = preg_replace('/[\\x00-\\x1F\\x7F]/', ' ', $ua);
        if (function_exists('mb_convert_encoding')) {
            $ua = mb_convert_encoding($ua, 'UTF-8', 'UTF-8');
            $ua = mb_substr($ua, 0, 100, 'UTF-8');
        } else {
            $ua = preg_replace('/[^\\x20-\\x7E]/', '', $ua);
            $ua = substr($ua, 0, 100);
        }
        // Store only a coarse, masked network hint, never a raw address.
        $ip = (string)kp_client_ip();
        $hint = filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4) ? preg_replace('/\.\d+$/','.xxx',$ip) : 'IPv6 / không xác định';
        kp_db()->prepare('INSERT INTO kp_user_security_events (user_id,event_type,device_label,ip_hint) VALUES (?,?,?,?)')
            ->execute([(int)$userId,$type,$ua,$hint]);
    } catch (Throwable $e) { kp_log('security event recording unavailable', $e); }
}
function kp_center_safe_path($path) {
    // Explicit allowlist prevents protocol-relative links, external redirects,
    // injected query strings and future unreviewed user-controlled links.
    return is_string($path) && in_array($path, [
        '', '/thong-bao', '/lich-su-bao-mat', '/giftcode-cua-toi',
        '/theo-doi-don-nap', '/lich-su-nap', '/ho-so', '/nhan-vat',
        '/diem-tich-luy'
    ], true);
}
function kp_admin_center_safe_path($path) {
    return is_string($path) && in_array($path, [
        '', '/quan-tri', '/quan-tri/thong-bao', '/quan-tri/diem-tich-luy', '/quan-tri/doi-soat'
    ], true);
}
function kp_admin_notify($title,$body,$category='system',$path='') {
    if (strlen($title)>120 || strlen($body)>500
        || !in_array($category,['system','payment','reward'],true)
        || !kp_admin_center_safe_path($path)) return false;
    try {
        $st=kp_db()->prepare('INSERT INTO kp_admin_notifications (title,body,category,link_path) VALUES (?,?,?,?)');
        return $st->execute([$title,$body,$category,$path]);
    } catch (Throwable $e) { kp_log('admin notification recording unavailable',$e); return false; }
}
function kp_admin_notifications($pdo,$max=30) {
    $st=$pdo->query('SELECT id,title,body,category,link_path,is_read,created_at
        FROM kp_admin_notifications ORDER BY id DESC LIMIT '.max(1,min(100,(int)$max)));
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
function kp_center_notify($userId,$title,$body,$category='system',$path='') {
    if ((int)$userId < 1 || strlen($title)>120 || strlen($body)>500
        || !in_array($category,['system','security','payment','gift'],true)
        || !kp_center_safe_path($path)) return false;
    try {
        $st=kp_db()->prepare('INSERT INTO kp_user_notifications (user_id,title,body,category,link_path) VALUES (?,?,?,?,?)');
        return $st->execute([(int)$userId,$title,$body,$category,$path]);
    } catch (Throwable $e) { kp_log('notification recording unavailable',$e); return false; }
}
function kp_center_notifications($pdo,$userId,$max=60) {
    $st=$pdo->prepare('SELECT id,title,body,category,link_path,is_read,created_at
        FROM kp_user_notifications WHERE user_id=? ORDER BY id DESC LIMIT '.max(1,min(100,(int)$max)));
    $st->execute([(int)$userId]); return $st->fetchAll(PDO::FETCH_ASSOC);
}
function kp_center_security($pdo,$userId,$max=50) {
    $st=$pdo->prepare('SELECT event_type,device_label,ip_hint,created_at
        FROM kp_user_security_events WHERE user_id=? ORDER BY id DESC LIMIT '.max(1,min(100,(int)$max)));
    $st->execute([(int)$userId]); return $st->fetchAll(PDO::FETCH_ASSOC);
}
function kp_center_gift_history($pdo,$userId,$max=80) {
    // Explicit ownership join; no user-supplied player name in SQL.
    $st=$pdo->prepare('SELECT g.id,g.giftcode,g.player,g.item,g.xu,g.luong,g.luongK
         FROM giftcode_log g
         WHERE EXISTS (SELECT 1 FROM tob_web_info_char w
                       WHERE w.userid=? AND w.charname=g.player)
         ORDER BY g.id DESC LIMIT '.max(1,min(100,(int)$max)));
    $st->execute([(int)$userId]); return $st->fetchAll(PDO::FETCH_ASSOC);
}
