<?php
/**
 * Read-only audit of claimed payOS Farmer Point gifts.
 *
 * The durable JSON entitlement created in bank_farmer_reward.php is the source
 * of truth.  Do NOT attempt to repair, re-credit or replay MyISAM game writes
 * while browsing history.
 */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }

/** PHP receives the effective visitor IP from the existing site policy. */
function kp_bank_farmer_audit_ip(array $request = null): string
{
    if ($request === null) {
        $ip = function_exists('kp_client_ip') ? kp_client_ip()
            : (string)($_SERVER['REMOTE_ADDR'] ?? '');
    } else {
        // Used in isolated tests, never take "ip" from browser POST data.
        $ip = (string)($request['REMOTE_ADDR'] ?? '');
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}

/** Device type is a user-agent heuristic; it is not verified hardware identity. */
function kp_bank_farmer_device(string $agent): array
{
    $agent = substr($agent, 0, 512);
    $type = 'Không xác định';
    if (preg_match('/iPad|Tablet|Android(?!.*Mobile)/i', $agent)) $type = 'Máy tính bảng';
    elseif (preg_match('/iPhone|iPod|Android.*Mobile|Mobile|Windows Phone/i', $agent)) $type = 'Điện thoại';
    elseif (preg_match('/Windows NT|Macintosh|X11|Linux x86_64/i', $agent)) $type = 'Máy tính';

    $browser = 'Không xác định';
    if (preg_match('/Edg\/|EdgiOS\/|EdgA\//i', $agent)) $browser = 'Edge';
    elseif (preg_match('/OPR\/|Opera/i', $agent)) $browser = 'Opera';
    elseif (preg_match('/Firefox\/|FxiOS\//i', $agent)) $browser = 'Firefox';
    elseif (preg_match('/Chrome\/|CriOS\//i', $agent)) $browser = 'Chrome';
    elseif (preg_match('/Safari\//i', $agent)) $browser = 'Safari';

    $os = 'Không xác định';
    if (preg_match('/iPhone|iPad|iPod/i', $agent)) $os = 'iOS / iPadOS';
    elseif (preg_match('/Android/i', $agent)) $os = 'Android';
    elseif (preg_match('/Windows NT/i', $agent)) $os = 'Windows';
    elseif (preg_match('/Mac OS X|Macintosh/i', $agent)) $os = 'macOS';
    elseif (preg_match('/Linux|X11/i', $agent)) $os = 'Linux';
    return ['type'=>$type, 'os'=>$os, 'browser'=>$browser];
}

/**
 * Geography is optional server-side GeoIP metadata.
 * Never trust request headers such as X-Forwarded-For or client-supplied city.
 * If the server does not supply GeoIP data, do not fabricate a location.
 */
function kp_bank_farmer_location(array $server): array
{
    $fields = ['city'=>'GEOIP_CITY','region'=>'GEOIP_REGION','country'=>'GEOIP_COUNTRY_NAME'];
    $out = ['city'=>'','region'=>'','country'=>'','source'=>''];
    foreach ($fields as $name=>$envKey) {
        $value = trim((string)($server[$envKey] ?? ''));
        if ($value !== '' && strlen($value) <= 90
            && preg_match('/\A[\p{L}\p{M}\p{N} .,\x27\-()]+\z/uD', $value)) {
            $out[$name] = $value;
        }
    }
    if ($out['city'] !== '' || $out['region'] !== '' || $out['country'] !== '') {
        $out['source'] = 'GeoIP máy chủ (ước lượng)';
    }
    return $out;
}

function kp_bank_farmer_claim_audit(array $server = null): array
{
    if ($server === null) $server = $_SERVER;
    $agent = (string)($server['HTTP_USER_AGENT'] ?? '');
    return [
        'claim_ip' => kp_bank_farmer_audit_ip($server === $_SERVER ? null : $server),
        'claim_device' => kp_bank_farmer_device($agent),
        'claim_location' => kp_bank_farmer_location($server),
    ];
}

/** Display every claim already saved; do not infer missing historic IP/device. */
function kp_bank_farmer_history_row(array $gift): ?array
{
    $status = (string)($gift['status'] ?? '');
    if (!in_array($status, ['completed','prepared','needs_review'], true)) return null;
    $userId = (int)($gift['user_id'] ?? 0);
    $code = (int)($gift['order_code'] ?? 0);
    $points = (int)($gift['points'] ?? 0);
    if ($userId < 1 || $code < 100000000 || $code > 999999999
        || $points < 1 || $points > 100000000) return null;
    $at = (int)($gift['completed_at'] ?? $gift['prepared_at'] ?? 0);
    $device = is_array($gift['claim_device'] ?? null) ? $gift['claim_device'] : [];
    $location = is_array($gift['claim_location'] ?? null) ? $gift['claim_location'] : [];
    return [
        'order_code'=>$code,
        'user_id'=>$userId,
        'char_id'=>(int)($gift['char_id'] ?? 0),
        'charname'=>(string)($gift['charname'] ?? ''),
        'amount'=>(int)($gift['amount'] ?? 0),
        'points'=>$points,
        'points_before'=>isset($gift['points_before']) ? (int)$gift['points_before'] : null,
        'points_after'=>isset($gift['points_after']) ? (int)$gift['points_after'] : null,
        'status'=>$status,
        'time'=>$at,
        'ip'=>filter_var($gift['claim_ip'] ?? '', FILTER_VALIDATE_IP) ? (string)$gift['claim_ip'] : '',
        'device'=>(string)($device['type'] ?? ''),
        'os'=>(string)($device['os'] ?? ''),
        'browser'=>(string)($device['browser'] ?? ''),
        'location'=>$location,
    ];
}

/**
 * Build a dense 0-based numeric argument list for PDO positional placeholders.
 * Two or more claims can belong to one user; array_unique alone leaves gaps.
 */
function kp_bank_farmer_history_user_ids(array $rows): array
{
    return array_values(array_unique(array_filter(
        array_map('intval', array_column($rows, 'user_id')),
        static function(int $id): bool { return $id > 0; }
    )));
}

/**
 * This helper MUST be called only after an existing authenticated user/admin
 * guard.  $ownerId is required for user views and is never request-controlled.
 * Returns a page of audit rows, in deterministic newest-first order.
 */
function kp_bank_farmer_history_page(?int $ownerId, int $page = 1, int $perPage = 25, string $filter = ''): array
{
    if ($ownerId !== null && $ownerId < 1) return ['rows'=>[], 'total'=>0,'pages'=>1,'page'=>1];
    $page=max(1,min(100000,$page));
    $perPage=max(1,min(100,$perPage));
    $filter=trim(substr($filter,0,60));
    // Reading history must NOT depend on storage being writable.  The normal
    // kp_topup_root() helper checks write access for payment operations and
    // can incorrectly block a perfectly readable, read-only audit folder.
    $dir=KP_ROOT.'/storage/topup/bank-farmer';
    if (!is_dir($dir)) return ['rows'=>[],'total'=>0,'pages'=>1,'page'=>1];
    if (!is_readable($dir)) {
        throw new RuntimeException('Thư mục lịch sử nhận điểm không thể đọc.');
    }
    $rows=[];
    // Iterate filenames directly; never accept a user-supplied file path.
    foreach (new DirectoryIterator($dir) as $file) {
        if (!$file->isFile() || !preg_match('/\A([1-9][0-9]{8})\.json\z/D', $file->getFilename(), $m)) continue;
        try {
            $gift=kp_topup_read_file($file->getPathname());
        } catch (Throwable $e) {
            // A single unreadable/broken historical JSON file must not hide
            // all other users' legitimate audit history. Never rewrite it.
            if (function_exists('kp_log')) kp_log('farmer claim audit unreadable order='.$m[1],$e);
            continue;
        }
        if (!is_array($gift) || (int)($gift['order_code'] ?? 0) !== (int)$m[1]) continue;
        $row=kp_bank_farmer_history_row($gift);
        if ($row===null || ($ownerId!==null && $row['user_id']!==$ownerId)) continue;
        if ($filter!=='' && stripos((string)$row['order_code'],$filter)===false
            && stripos((string)$row['charname'],$filter)===false
            && stripos((string)$row['user_id'],$filter)===false) continue;
        $rows[]=$row;
    }
    usort($rows, static function(array $a, array $b): int {
        return ($b['time'] <=> $a['time']) ?: ($b['order_code'] <=> $a['order_code']);
    });
    $total=count($rows);
    $pages=max(1,(int)ceil($total/$perPage));
    $page=min($page,$pages);
    return [
        'rows'=>array_slice($rows,($page-1)*$perPage,$perPage),
        'total'=>$total,
        'pages'=>$pages,
        'page'=>$page
    ];
}

function kp_bank_farmer_history_time(int $timestamp): string
{
    if ($timestamp <= 0) return 'Không có dữ liệu thời gian';
    return (new DateTimeImmutable('@'.$timestamp))
        ->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('d/m/Y H:i:s');
}

function kp_bank_farmer_history_place(array $loc): string
{
    $parts=[];
    foreach (['city','region','country'] as $field) {
        $s=trim((string)($loc[$field] ?? ''));
        if ($s!=='' && !in_array($s,$parts,true)) $parts[]=$s;
    }
    return $parts ? implode(', ',$parts).' (ước lượng)' : 'Không xác định';
}
