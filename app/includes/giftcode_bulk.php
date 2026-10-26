<?php
/**
 * Bulk giftcode generation. Reuses the validated reward string, not browser SQL.
 * Game giftcode tables may be MyISAM: insert as disabled, write private metadata,
 * then enable each code individually. Partial batches are reported, never retried.
 */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }

function kp_giftcode_bulk_prefix($prefix)
{
    $prefix = strtoupper(trim((string)$prefix));
    if (!preg_match('/\A[A-Z0-9_-]{3,30}\z/D', $prefix)) {
        throw new RuntimeException('Tiền tố phải dài 3–30 ký tự, chỉ gồm chữ, số, _ và -.');
    }
    return $prefix;
}

/** @return string[] Generate distinct codes before any DB modification. */
function kp_giftcode_bulk_generate($prefix, $count)
{
    $prefix = kp_giftcode_bulk_prefix($prefix);
    if (!is_int($count) || $count < 1 || $count > 100) {
        throw new RuntimeException('Mỗi lần chỉ được tạo từ 1 đến 100 giftcode.');
    }
    $codes = [];
    while (count($codes) < $count) {
        $code = $prefix . '-' . strtoupper(bin2hex(random_bytes(8)));
        if (!kp_giftcode_valid_code($code)) {
            throw new RuntimeException('Giftcode sinh ra không hợp lệ.');
        }
        $codes[$code] = $code;
    }
    return array_values($codes);
}

/**
 * @return array{codes:array,locked:array,error:string}
 *   codes = successfully enabled or explicitly disabled (requested by admin).
 *   locked = inserted rows left disabled after an interruption.
 * Note: no DB transaction is assumed; game tables may use MyISAM.
 */
function kp_giftcode_bulk_create($pdo, $prefix, $count, array $values, $item, array $meta, $disabled)
{
    $candidateCodes = kp_giftcode_bulk_generate($prefix, $count);
    $existing = $pdo->prepare('SELECT id FROM giftcode WHERE giftcode = ? LIMIT 1');
    $insert = $pdo->prepare('INSERT INTO giftcode (giftcode, xu, luong, luongLock, item, expire, limit_use, type) VALUES (?, ?, ?, ?, ?, 0, ?, 1)');
    $enable = $pdo->prepare('UPDATE giftcode SET type = ? WHERE id = ? AND type = 1');
    $result = ['codes'=>[], 'locked'=>[], 'error'=>''];
    $meta['public'] = false; // Bulk codes are private to prevent mass disclosure.
    foreach ($candidateCodes as $candidate) {
        $code = $candidate;
        $created = false;
        try {
            // Existing names may lack a unique index in historical game schemas.
            for ($tries = 0; $tries < 12; $tries++) {
                $existing->execute([$code]);
                if (!$existing->fetchColumn()) break;
                $code = kp_giftcode_bulk_generate($prefix, 1)[0];
            }
            if ($tries >= 12) {
                throw new RuntimeException('Không tạo được giftcode không trùng.');
            }
            // Never insert a redeemable code until catalog storage is consistent.
            $insert->execute([
                $code, (int)$values['xu'], (int)$values['luong'],
                (int)$values['luongLock'], $item === '' ? null : $item,
                (int)$values['limit_use'],
            ]);
            $created = true;
            $id = (int)$pdo->lastInsertId();
            if ($id <= 0) throw new RuntimeException('Không lấy được ID giftcode mới.');
            kp_giftcode_catalog_set($id, $meta);
            // Explicitly requested locked codes remain type=1.
            if (!$disabled) {
                $enable->execute([0, $id]);
                if ($enable->rowCount() !== 1) {
                    throw new RuntimeException('Không kích hoạt được Giftcode vừa tạo.');
                }
            }
            $result['codes'][] = $code;
        } catch (Throwable $e) {
            if ($created) $result['locked'][] = $code;
            $result['error'] = 'Đã dừng tạo mã do lỗi lưu trữ, DB hoặc xung đột. '
                . 'Các mã hoàn tất giữ nguyên trạng thái; mã tạo dở bị khóa. '
                . 'Không gửi lại tự động trước khi đối chiếu danh sách.';
            // Log technical error server-side; no DB secrets in the HTML response.
            if (function_exists('kp_log')) kp_log('giftcode bulk creation interrupted', $e);
            break;
        }
    }
    return $result;
}
