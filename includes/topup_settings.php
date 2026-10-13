<?php
/** Top-up rates stored in private JSON, no SQL schema or data migrations. */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }
function kp_topup_defaults() {
    return ['luong'=>100,'luonglock'=>200,'gold'=>0];
}
function kp_topup_settings_path() {
    return KP_ROOT . '/storage/topup-rates.json';
}
function kp_topup_rates() {
    $defaults=kp_topup_defaults();
    $path=kp_topup_settings_path();
    if (!is_file($path)) return $defaults;
    $json=@file_get_contents($path);
    $decoded=$json===false?null:json_decode($json,true);
    if (!is_array($decoded)) { kp_log('topup rates file invalid'); return $defaults; }
    foreach ($defaults as $key=>$value) {
        if (isset($decoded[$key]) && is_int($decoded[$key]) && $decoded[$key]>=0 && $decoded[$key]<=1000000000) $defaults[$key]=$decoded[$key];
    }
    return $defaults;
}
function kp_topup_save_rates(array $values) {
    $defaults=kp_topup_defaults();
    foreach($defaults as $key=>$ignored) {
        if (!array_key_exists($key,$values) || !is_int($values[$key]) || $values[$key]<0 || $values[$key]>1000000000)
            throw new RuntimeException('Tỷ lệ không hợp lệ.');
    }
    $dir=KP_ROOT.'/storage';
    if (!is_dir($dir) || !is_writable($dir)) throw new RuntimeException('Thư mục storage chưa có quyền ghi. Không thể lưu tỷ lệ.');
    $path=kp_topup_settings_path();
    $lock=@fopen($dir.'/topup-rates.lock','c');
    if (!$lock || !flock($lock,LOCK_EX)) throw new RuntimeException('Không thể khóa cấu hình tỷ lệ.');
    try {
        $temp=tempnam($dir,'topup-');
        if ($temp===false) throw new RuntimeException('Không thể tạo tệp cấu hình.');
        $json=json_encode($values,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        if (@file_put_contents($temp,$json,LOCK_EX)===false || !@rename($temp,$path)) {
            @unlink($temp); throw new RuntimeException('Lưu tỷ lệ thất bại.');
        }
    } finally { flock($lock,LOCK_UN);fclose($lock); }
}
function kp_topup_display($amount) { return number_format((float)$amount,0,',','.'); }
function kp_topup_convert($vnd,$rate) {
    if (!is_int($vnd) || $vnd<=0 || $vnd>1000000000 || !is_int($rate) || $rate<=0) return 0;
    return intdiv($vnd*$rate,10000);
}
function kp_topup_require_admin() {
    if (empty($_SESSION['kp_admin_id']) || ($_SESSION['kp_admin_role']??'')!=='admin' ||
        empty($_SESSION['kp_admin_last_seen']) || time()-(int)$_SESSION['kp_admin_last_seen']>1800) {
        header('Location: login.php?expired=1');exit;
    }
    $st=kp_db()->prepare("SELECT id FROM kpah_web_admins WHERE id=? AND role='admin' AND is_active=1 LIMIT 1");
    $st->execute([(int)$_SESSION['kp_admin_id']]);
    if (!$st->fetchColumn()) {header('Location: login.php');exit;}
    $_SESSION['kp_admin_last_seen']=time();
}
function kp_topup_require_user() {
    if (empty($_SESSION['kp_user_id']) || (isset($_SESSION['kp_user_last_seen']) && time()-(int)$_SESSION['kp_user_last_seen']>1800)) {
        header('Location: login.php');exit;
    }
    $st=kp_db()->prepare('SELECT id,username FROM team_user WHERE id=? LIMIT 1');
    $st->execute([(int)$_SESSION['kp_user_id']]);
    $u=$st->fetch(PDO::FETCH_ASSOC);
    if(!$u) {header('Location: login.php');exit;}
    $_SESSION['kp_user_last_seen']=time();
    return $u;
}
