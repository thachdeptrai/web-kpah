<?php
/**
 * KPAH payment module. No database schema migration.
 * Existing tables are read-only except for deliberate grants/queue inserts.
 * Durable order and admin audit records are stored in protected JSON files.
 */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }

function kp_topup_env($name, $default = '') {
    $value = getenv($name);
    return is_string($value) && $value !== '' ? $value : $default;
}
function kp_topup_cfg() {
    $id = kp_topup_env('KPAH_CARD_PARTNER_ID');
    $key = kp_topup_env('KPAH_CARD_PARTNER_KEY');
    $secret = kp_topup_env('KPAH_TOPUP_SIGNING_SECRET');
    return [
        'partner_id' => $id,
        'partner_key' => $key,
        'secret' => $secret,
        'api_url' => 'https://napthesieure.vn/chargingws/v2',
        'card_ready' => $id !== '' && $key !== '' && strlen($secret) >= 32 && function_exists('curl_init'),
        // Off until game-server code confirms the semantics of board_naptien.
        'game_queue_verified' => kp_topup_env('KPAH_TOPUP_GAME_QUEUE_VERIFIED') === '1',
        'server_id' => (int) kp_topup_env('KPAH_TOPUP_SERVER_ID', '-1'),
    ];
}
function kp_topup_root() {
    $dir = KP_ROOT . '/storage/topup';
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Không có nơi lưu lịch sử nạp. Liên hệ quản trị.');
    }
    if (!is_writable($dir)) throw new RuntimeException('Thư mục lưu lịch sử nạp không có quyền ghi.');
    return $dir;
}
function kp_topup_path($type, $id) {
    if (!in_array($type, ['orders', 'grants'], true) || !preg_match('/^[a-f0-9]{32}$/D', (string)$id)) {
        throw new RuntimeException('Mã giao dịch không hợp lệ.');
    }
    $dir = kp_topup_root() . '/' . $type;
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Không thể tạo vùng lưu giao dịch.');
    }
    return $dir . '/' . $id . '.json';
}
function kp_topup_read_file($path) {
    if (!is_file($path)) return null;
    $data = json_decode((string)@file_get_contents($path), true);
    if (!is_array($data)) throw new RuntimeException('Dữ liệu giao dịch cần đối soát.');
    return $data;
}
function kp_topup_write_file($path, array $data) {
    $raw = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($raw)) throw new RuntimeException('Không thể lưu giao dịch.');
    $tmp = $path . '.' . bin2hex(random_bytes(5)) . '.tmp';
    if (@file_put_contents($tmp, $raw, LOCK_EX) !== strlen($raw)) {
        @unlink($tmp);
        throw new RuntimeException('Không ghi được lịch sử giao dịch.');
    }
    @chmod($tmp, 0600);
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Không thể hoàn tất ghi lịch sử giao dịch.');
    }
}
function kp_topup_with_record($type, $id, callable $callback) {
    $path = kp_topup_path($type, $id);
    $fh = @fopen($path . '.lock', 'c+');
    if (!$fh) throw new RuntimeException('Không tạo được khóa giao dịch.');
    try {
        if (!flock($fh, LOCK_EX)) throw new RuntimeException('Không khóa được giao dịch.');
        return $callback(kp_topup_read_file($path), $path);
    } finally {
        @flock($fh, LOCK_UN);
        fclose($fh);
    }
}
function kp_topup_new_id() { return bin2hex(random_bytes(16)); }
function kp_topup_rates($new = null) {
    $path = kp_topup_root() . '/rates.json';
    $lock = @fopen($path . '.lock', 'c+');
    if (!$lock) throw new RuntimeException('Không thể đọc cấu hình tỷ lệ.');
    try {
        if (!flock($lock, LOCK_EX)) throw new RuntimeException('Lỗi khóa tỷ lệ.');
        $rates = ['gold' => 0, 'luong' => 100, 'luonglock' => 200];
        // Read the earlier module's rates as a one-way fallback. Never delete
        // or overwrite that file; new saves go to the canonical path only.
        $legacyRatesPath = KP_ROOT . '/storage/topup-rates.json';
        $source = is_file($path) ? $path : (is_file($legacyRatesPath) ? $legacyRatesPath : null);
        if ($source !== null) {
            $existing = kp_topup_read_file($source);
            foreach ($rates as $currency => $v) {
                if (isset($existing[$currency]) && is_int($existing[$currency]) && $existing[$currency] >= 0 && $existing[$currency] <= 1000000000) {
                    $rates[$currency] = $existing[$currency];
                }
            }
        }
        if (is_array($new)) {
            foreach ($rates as $currency => $v) {
                if (!array_key_exists($currency, $new) || !is_int($new[$currency]) || $new[$currency] < 0 || $new[$currency] > 1000000000) {
                    throw new RuntimeException('Tỷ lệ nhập vào không hợp lệ.');
                }
            }
            $rates = array_intersect_key($new, $rates);
            kp_topup_write_file($path, $rates);
        }
        return $rates;
    } finally { @flock($lock, LOCK_UN); fclose($lock); }
}
function kp_topup_units($vnd, $per10k) {
    if (!is_int($vnd) || !is_int($per10k) || $vnd < 1 || $vnd > 1000000000 || $per10k < 1 || $per10k > 1000000000) {
        throw new RuntimeException('Số VNĐ hoặc tỷ lệ chưa hợp lệ.');
    }
    // Integer math: avoids floats, no forced 10k milestones.
    $units = intdiv($vnd * $per10k, 10000);
    if ($units < 1 || $units > 1000000000) throw new RuntimeException('Giá trị quy đổi vượt giới hạn.');
    return $units;
}
function kp_topup_user() {
    $id = (int)($_SESSION['kp_user_id'] ?? 0);
    if ($id <= 0) { header('Location: ../login.php'); exit; }
    if (empty($_SESSION['kp_user_last_seen']) || time() - (int)$_SESSION['kp_user_last_seen'] > 1800) {
        unset($_SESSION['kp_user_id'], $_SESSION['kp_user_username'], $_SESSION['kp_user_last_seen']);
        header('Location: ../login.php?expired=1'); exit;
    }
    $s = kp_db()->prepare('SELECT id,username,ban FROM team_user WHERE id=? LIMIT 1');
    $s->execute([$id]);
    $user = $s->fetch();
    if (!$user || (int)$user['ban'] !== 0) {
        unset($_SESSION['kp_user_id'], $_SESSION['kp_user_username'], $_SESSION['kp_user_last_seen']);
        header('Location: ../login.php'); exit;
    }
    $_SESSION['kp_user_last_seen'] = time();
    return $user;
}
function kp_topup_admin() {
    require_once __DIR__ . '/admin_security.php';
    return kp_admin_require('/admin/login.php');
}
function kp_topup_recent($kind, $userId = null, $limit = 50) {
    $dir = kp_topup_root() . '/' . $kind;
    if (!is_dir($dir)) return [];
    $paths = glob($dir . '/*.json') ?: [];
    $out = [];
    // Never deserialize card codes (they are not stored at all).
    foreach ($paths as $p) {
        $d = json_decode((string)@file_get_contents($p), true);
        if (!is_array($d)) continue;
        if ($userId !== null && (int)($d['user_id'] ?? -1) !== (int)$userId) continue;
        $out[] = $d;
    }
    usort($out, function($a,$b){ return ((int)($b['created_at'] ?? 0)) <=> ((int)($a['created_at'] ?? 0)); });
    return array_slice($out, 0, $limit);
}
function kp_topup_hmac($code, $serial, $secret) {
    return hash_hmac('sha256', $code . ':' . $serial, $secret);
}
function kp_topup_provider_request(array $params) {
    $cfg = kp_topup_cfg();
    if (!$cfg['card_ready']) throw new RuntimeException('Cổng thẻ chưa được cấu hình.');
    $h = curl_init($cfg['api_url']);
    if (!$h) throw new RuntimeException('Cổng thẻ chưa khả dụng.');
    curl_setopt_array($h, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params, '', '&', PHP_QUERY_RFC3986),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 18,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
    ]);
    $json = curl_exec($h);
    $http = (int)curl_getinfo($h, CURLINFO_HTTP_CODE);
    curl_close($h);
    if (!is_string($json) || $http !== 200 || strlen($json) > 100000) {
        throw new RuntimeException('Chưa nhận được trạng thái từ cổng thẻ. Không gửi lại thẻ.');
    }
    $data = json_decode($json, true);
    if (!is_array($data)) throw new RuntimeException('Phản hồi cổng thẻ chưa hợp lệ.');
    return $data;
}
function kp_topup_provider_charge(array $order, $code, $serial) {
    $cfg = kp_topup_cfg();
    $p = [
        'telco' => $order['telco'], 'code' => $code, 'serial' => $serial,
        'amount' => $order['amount'], 'request_id' => $order['id'],
        'partner_id' => $cfg['partner_id'], 'command' => 'charging',
    ];
    $p['sign'] = md5($cfg['partner_key'] . $code . 'charging' . $cfg['partner_id'] . $order['id'] . $serial . $order['telco']);
    return kp_topup_provider_request($p);
}
function kp_topup_provider_check($id) {
    $cfg = kp_topup_cfg();
    $p = ['command' => 'check','partner_id' => $cfg['partner_id'],'request_id' => $id];
    $p['sign'] = md5($cfg['partner_key'] . 'check' . $cfg['partner_id'] . $id);
    return kp_topup_provider_request($p);
}
function kp_topup_status_label($status) {
    $labels = [
        'submitting' => 'Đang gửi thẻ',
        'pending' => 'Đang xử lý',
        'verified_pending_delivery' => 'Thẻ hợp lệ · chờ cấp',
        'crediting' => 'Cần kiểm tra cấp tiền',
        'queued' => 'Đã đưa vào hàng chờ game',
        'failed' => 'Thẻ không hợp lệ',
        'needs_review' => 'Cần đối soát',
        'applied' => 'Đã cộng',
    ];
    return $labels[$status] ?? 'Đang kiểm tra';
}
function kp_topup_queue_existing_board(array $order) {
    $cfg = kp_topup_cfg();
    if (!$cfg['game_queue_verified']) return false;
    $currency = $order['currency'];
    if (!in_array($currency, ['gold','luong','luonglock'],true)) throw new RuntimeException('Loại tài nguyên không hợp lệ.');
    $units = (int)$order['units'];
    if ($units <= 0 || $units > 1000000000) throw new RuntimeException('Số lượng không hợp lệ.');
    // ID linkage: web login uses team_user.id, but board_naptien uses username.
    // The game-server consumer and svID MUST be validated prior to enabling this path.
    $st = kp_db()->prepare('SELECT username FROM team_user WHERE id=? AND username=? AND ban=0 LIMIT 1');
    $st->execute([(int)$order['user_id'], (string)$order['username']]);
    if (!$st->fetch()) throw new RuntimeException('Tài khoản nhận không còn hợp lệ.');
    $st = kp_db()->prepare('INSERT INTO board_naptien (username,svID,xu,luong,luongKhoa,ve) VALUES(?,?,?,?,?,0)');
    $st->execute([
        (string)$order['username'], $cfg['server_id'],
        $currency==='gold' ? $units : 0,
        $currency==='luong' ? $units : 0,
        $currency==='luonglock' ? $units : 0,
    ]);
    return true;
}
/**
 * Callback and on-demand reconciliation. A fresh provider "check" response is
 * required; callback alone does not authenticate the status/value fields.
 */
function kp_topup_reconcile($id, $callback = null) {
    $cfg = kp_topup_cfg();
    if (!$cfg['card_ready']) throw new RuntimeException('Cổng thẻ chưa sẵn sàng.');
    $response = kp_topup_provider_check($id);
    return kp_topup_with_record('orders',$id,function($order,$path) use ($response,$cfg,$callback) {
        if (!$order) throw new RuntimeException('Không tìm thấy yêu cầu nạp.');
        if (in_array($order['status'],['queued','failed','crediting','needs_review'],true)) return $order;
        if (isset($response['request_id']) && (string)$response['request_id'] !== $order['id']) {
            throw new RuntimeException('Mã giao dịch đối soát không trùng.');
        }
        $status = (int)($response['status'] ?? 99);
        if ($status === 1) {
            $value = filter_var($response['value'] ?? null,FILTER_VALIDATE_INT);
            if ($value === false || $value !== (int)$order['amount']) {
                $order['status']='needs_review';
                $order['updated_at']=time();
                kp_topup_write_file($path,$order);
                return $order;
            }
            $order['status']='verified_pending_delivery';
            $order['updated_at']=time();
            kp_topup_write_file($path,$order);
            if ($cfg['game_queue_verified']) {
                // Record "crediting" *before* the non-transactional MyISAM INSERT.
                // If interrupted, never replay this request automatically.
                $order['status']='crediting';
                kp_topup_write_file($path,$order);
                try {
                    kp_topup_queue_existing_board($order);
                    $order['status']='queued';
                    kp_topup_write_file($path,$order);
                } catch (Throwable $e) {
                    kp_log('topup game queue requires manual reconciliation',$e);
                    $order['status']='needs_review';
                    kp_topup_write_file($path,$order);
                }
            }
        } elseif (in_array($status,[2,3],true)) {
            $order['status'] = $status===2 ? 'needs_review' : 'failed';
            $order['updated_at']=time();
            kp_topup_write_file($path,$order);
        } else {
            $order['status']='pending';
            $order['updated_at']=time();
            kp_topup_write_file($path,$order);
        }
        return $order;
    });
}
