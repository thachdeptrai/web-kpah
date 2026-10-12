<?php
/** Referral code validation and usage tracking. */
if (!defined('KP_ROOT')) {
    http_response_code(403);
    exit;
}

/** Check a code without consuming a use. Returns row or null. */
function kp_referral_find_valid($code)
{
    $code = strtoupper(trim((string) $code));
    if ($code === '' || strlen($code) > 64) {
        return null;
    }
    $st = kp_db()->prepare(
        'SELECT `id`, `code`, `count`, `used_count`, `date`, `is_active`
         FROM `kpah_referral_codes`
         WHERE `code` = ? AND `is_active` = 1
         LIMIT 1'
    );
    $st->execute([$code]);
    $row = $st->fetch();
    if (!$row) {
        return null;
    }
    if ((int) $row['used_count'] >= (int) $row['count']) {
        return null;
    }
    if (!empty($row['date']) && strtotime($row['date']) < time()) {
        return null;
    }
    return $row;
}

/**
 * Serialize registrations using the same code, then increment usage and log it.
 * Caller must release the named lock after account creation finishes.
 */
function kp_referral_acquire($code)
{
    $code = strtoupper(trim((string) $code));
    if ($code === '' || strlen($code) > 64) {
        return ['status' => 'invalid', 'lock' => ''];
    }
    $lock = 'kpah_ref_' . substr(hash('sha256', $code), 0, 40);
    $st = kp_db()->prepare('SELECT GET_LOCK(?, 5)');
    $st->execute([$lock]);
    if ((int) $st->fetchColumn() !== 1) {
        return ['status' => 'busy', 'lock' => ''];
    }

    try {
        $row = kp_referral_find_valid($code);
        if (!$row) {
            return ['status' => 'invalid', 'lock' => $lock];
        }
        return ['status' => 'ok', 'lock' => $lock, 'row' => $row];
    } catch (Throwable $e) {
        kp_referral_release($lock);
        throw $e;
    }
}

function kp_referral_consume(array $row, $username)
{
    $pdo = kp_db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare(
            'UPDATE `kpah_referral_codes`
             SET `used_count` = `used_count` + 1
             WHERE `id` = ? AND `used_count` < `count`'
        );
        $st->execute([(int) $row['id']]);
        if ($st->rowCount() !== 1) {
            $pdo->rollBack();
            return false;
        }
        $ip = function_exists('kp_client_ip') ? kp_client_ip() : '';
        $ipHash = $ip !== '' ? hash('sha256', $ip) : null;
        $st = $pdo->prepare(
            'INSERT INTO `kpah_referral_code_uses` (`referral_id`, `code`, `username`, `ip_hash`)
             VALUES (?, ?, ?, ?)'
        );
        $st->execute([(int) $row['id'], (string) $row['code'], (string) $username, $ipHash]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function kp_referral_release($lock)
{
    if (!$lock) {
        return;
    }
    try {
        $st = kp_db()->prepare('SELECT RELEASE_LOCK(?)');
        $st->execute([$lock]);
    } catch (Throwable $e) {
        // MySQL releases named locks when the connection closes.
    }
}
