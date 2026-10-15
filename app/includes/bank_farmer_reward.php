<?php
/**
 * Per-order bank top-up reward for game character farmer points (pInfo[117]).
 * A confirmed account payment creates an entitlement; the player explicitly
 * chooses a character after leaving the game. MyISAM updates are journaled,
 * never blindly replayed after uncertainty.
 */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }
require_once __DIR__ . '/topup_core.php';
require_once __DIR__ . '/wallet_vnd.php';
require_once __DIR__ . '/farmer_points.php';

function kp_bank_farmer_settings(?array $update = null): array
{
    $path = kp_topup_root() . '/bank-farmer.json';
    $lock = @fopen($path . '.lock', 'c+');
    if (!$lock || !flock($lock, LOCK_EX)) {
        if ($lock) fclose($lock);
        throw new RuntimeException('Không khóa được cấu hình điểm nông dân.');
    }
    try {
        // Enable only when admin opts in: never silently change live bank rewards.
        $value = ['enabled' => false, 'per_10k' => 10000];
        if (is_file($path)) {
            $stored = kp_topup_read_file($path);
            if (!is_bool($stored['enabled'] ?? null)
                || !is_int($stored['per_10k'] ?? null)
                || $stored['per_10k'] < 0 || $stored['per_10k'] > 100000) {
                throw new RuntimeException('Cấu hình Điểm nông dân không hợp lệ.');
            }
            $value = ['enabled' => $stored['enabled'], 'per_10k' => $stored['per_10k']];
        }
        if ($update !== null) {
            if (!is_bool($update['enabled'] ?? null)
                || !is_int($update['per_10k'] ?? null)
                || $update['per_10k'] < 0 || $update['per_10k'] > 100000) {
                throw new InvalidArgumentException('Điểm thưởng phải từ 0 đến 100.000 cho mỗi 10.000đ.');
            }
            $value = ['enabled' => $update['enabled'], 'per_10k' => $update['per_10k']];
            kp_topup_write_file($path, $value + ['updated_at' => time()]);
        }
        return $value;
    } finally {
        @flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function kp_bank_farmer_amount(int $amount, array $settings): int
{
    if ($amount < 10000 || $amount > 1000000000) throw new InvalidArgumentException('Mệnh giá không hợp lệ.');
    if (empty($settings['enabled'])) return 0;
    $rate = (int)($settings['per_10k'] ?? 0);
    if ($rate < 0 || $rate > 100000) throw new InvalidArgumentException('Tỷ lệ điểm không hợp lệ.');
    return intdiv($amount * $rate, 10000);
}

function kp_bank_farmer_path(int $orderCode): string
{
    if ($orderCode < 100000000 || $orderCode > 999999999) throw new InvalidArgumentException('Mã đơn không hợp lệ.');
    $dir = kp_topup_root() . '/bank-farmer';
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir))
        throw new RuntimeException('Chưa thể tạo nơi lưu quà nạp ngân hàng.');
    if (!is_writable($dir)) throw new RuntimeException('Chưa có quyền lưu quà nạp.');
    return $dir . '/' . $orderCode . '.json';
}

/** Called once before the actual payOS invoice is created, without overwriting. */
function kp_bank_farmer_reserve(int $code, int $userId, int $amount, array $settings): void
{
    $reward = kp_bank_farmer_amount($amount, $settings);
    if ($reward <= 0) return;
    $path = kp_bank_farmer_path($code);
    if (is_file($path)) throw new RuntimeException('Mã đơn đã có phần quà, hãy tạo lại.');
    $record = [
        'order_code' => $code, 'user_id' => $userId, 'amount' => $amount,
        'points' => $reward, 'status' => 'reserved', 'created_at' => time()
    ];
    kp_topup_write_file($path, $record);
}

/** A reward is redeemable only after both the payOS order and wallet journal agree. */
function kp_bank_farmer_paid(PDO $db, array $gift): bool
{
    $code = (int)($gift['order_code'] ?? 0);
    if ($code < 100000000 || $code > 999999999) return false;
    $st = $db->prepare('SELECT user_id,amount,status,provider_reference FROM kp_bank_orders WHERE order_code=? LIMIT 1');
    $st->execute([$code]);
    $order = $st->fetch(PDO::FETCH_ASSOC);
    if (!$order || (int)$order['user_id'] !== (int)$gift['user_id']
        || (int)$order['amount'] !== (int)$gift['amount']
        || (string)$order['status'] !== 'completed') return false;
    $ref = (string)$order['provider_reference'];
    if (!preg_match('/^VND_BONUS:(?:100|[1-9]?[0-9])$/D', $ref)) return false;
    $journal = kp_topup_read_file(kp_vnd_record_path('payos', (string)$code));
    return is_array($journal)
        && ($journal['status'] ?? '') === 'completed'
        && (int)($journal['user_id'] ?? 0) === (int)$gift['user_id']
        && (int)($journal['base'] ?? -1) === (int)$gift['amount'];
}

function kp_bank_farmer_list(int $userId, int $limit = 30): array
{
    if ($userId < 1) return [];
    $dir = kp_topup_root() . '/bank-farmer';
    if (!is_dir($dir)) return [];
    $db = kp_db();
    $out = [];
    // Query only the signed-in account; do not scan every player's gift files.
    // Examine a bounded window of recent completed bank wallet payments.
    $st = $db->prepare("SELECT order_code FROM kp_bank_orders
         WHERE user_id=? AND status='completed' AND provider_reference LIKE 'VND_BONUS:%'
         ORDER BY id DESC LIMIT 500");
    $st->execute([$userId]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $rawCode) {
        $code = filter_var($rawCode, FILTER_VALIDATE_INT);
        if ($code === false || $code < 100000000 || $code > 999999999) continue;
        $path = $dir . '/' . $code . '.json';
        if (!is_file($path)) continue;
        $row = kp_topup_read_file($path);
        if (!is_array($row) || (int)($row['user_id'] ?? 0) !== $userId
            || (int)($row['order_code'] ?? 0) !== $code
            || (int)($row['points'] ?? 0) < 1) continue;
        if (!kp_bank_farmer_paid($db, $row)) continue;
        $out[] = $row;
        if (count($out) >= max(1, min(100, $limit))) break;
    }
    return $out;
}

/** Pure helper: preserve EVERY other serialized pInfo field unchanged. */
function kp_bank_farmer_increment(string $pInfo, int $points): array
{
    if ($points < 1 || $points > 100000000) throw new RuntimeException('Số Điểm nông dân không hợp lệ.');
    $before = kp_farmer_points($pInfo);
    if ($before === null || $before > 2147483647 - $points)
        throw new RuntimeException('Nhân vật chưa có dữ liệu Điểm nông dân hợp lệ hoặc đã đạt giới hạn.');
    $fields = explode(',', $pInfo);
    if (count($fields) < 118) throw new RuntimeException('Dữ liệu nhân vật chưa hỗ trợ Điểm nông dân.');
    $fields[117] = (string)($before + $points);
    $after = implode(',', $fields);
    if (strlen($after) > 2000) throw new RuntimeException('Dữ liệu nhân vật vượt giới hạn lưu trữ.');
    return ['before' => $before, 'after' => $before + $points, 'pinfo' => $after];
}

function kp_bank_farmer_claim(int $userId, int $orderCode, int $charId): array
{
    if ($userId < 1 || $charId < 1) throw new RuntimeException('Tài khoản hoặc nhân vật không hợp lệ.');
    $path = kp_bank_farmer_path($orderCode);
    $lock = @fopen($path . '.lock', 'c+');
    if (!$lock || !flock($lock, LOCK_EX)) {
        if ($lock) fclose($lock);
        throw new RuntimeException('Chưa thể khóa phần quà. Vui lòng thử lại.');
    }
    try {
        $gift = kp_topup_read_file($path);
        if (!is_array($gift) || (int)($gift['user_id'] ?? 0) !== $userId
            || (int)($gift['order_code'] ?? 0) !== $orderCode
            || (int)($gift['points'] ?? 0) < 1)
            throw new RuntimeException('Phần quà không thuộc tài khoản của bạn.');
        if ($gift['status'] === 'completed')
            throw new RuntimeException('Phần quà đã được nhận, không thể nhận lại.');
        if ($gift['status'] !== 'reserved')
            throw new RuntimeException('Lượt nhận này đang cần kiểm tra. Vui lòng liên hệ quản trị, không gửi lại.');
        $db = kp_db();
        if (!kp_bank_farmer_paid($db, $gift))
            throw new RuntimeException('Thanh toán chưa được xác nhận cộng ví VNĐ. Hãy thử lại sau.');
        $st = $db->prepare('SELECT id FROM team_user WHERE id=? AND ban=0 LIMIT 1');
        $st->execute([$userId]);
        if (!$st->fetchColumn()) throw new RuntimeException('Tài khoản đã bị khóa.');
        $st = $db->prepare('SELECT id,charname,pInfo FROM tob_char WHERE id=? AND userId=? AND del=1 AND ban=0 LIMIT 1');
        $st->execute([$charId, $userId]);
        $char = $st->fetch(PDO::FETCH_ASSOC);
        if (!$char) throw new RuntimeException('Nhân vật không thuộc tài khoản hoặc đã bị khóa.');
        $pInfo = (string)($char['pInfo'] ?? '');
        $changed = kp_bank_farmer_increment($pInfo, (int)$gift['points']);
        $gift['status'] = 'prepared';
        $gift['char_id'] = $charId;
        $gift['charname'] = (string)$char['charname'];
        $gift['points_before'] = $changed['before'];
        $gift['points_after'] = $changed['after'];
        $gift['prepared_at'] = time();
        // Durable intent must be saved BEFORE a nontransactional game-table write.
        kp_topup_write_file($path, $gift);
        try {
            $st = $db->prepare('UPDATE tob_char SET pInfo=? WHERE id=? AND userId=? AND pInfo=? AND del=1 AND ban=0');
            $st->execute([$changed['pinfo'], $charId, $userId, $pInfo]);
            if ($st->rowCount() !== 1) throw new RuntimeException('Nhân vật vừa thay đổi dữ liệu, cần đối soát.');
            $gift['status'] = 'completed';
            $gift['completed_at'] = time();
            kp_topup_write_file($path, $gift);
            return $gift;
        } catch (Throwable $e) {
            kp_log('farmer gift claim needs manual review order=' . $orderCode, $e);
            $gift['status'] = 'needs_review';
            try { kp_topup_write_file($path, $gift); } catch (Throwable $ignored) {}
            throw new RuntimeException('Phần quà đang cần kiểm tra. Không bấm nhận lại để tránh cộng hai lần.');
        }
    } finally {
        @flock($lock, LOCK_UN);
        fclose($lock);
    }
}
