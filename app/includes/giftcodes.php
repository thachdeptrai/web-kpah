<?php
/**
 * Website giftcode visibility catalog.
 * Game rewards and redemption remain owned by the original giftcode tables/Java server.
 * Only explicitly published rows are visible publicly.
 */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }

function kp_giftcode_catalog_path()
{
    return KP_ROOT . '/storage/giftcode/catalog.json';
}

function kp_giftcode_catalog_read()
{
    $path = kp_giftcode_catalog_path();
    if (!is_file($path)) return [];
    $json = @file_get_contents($path);
    if (!is_string($json) || strlen($json) > 131072) {
        throw new RuntimeException('Không đọc được cấu hình hiển thị giftcode.');
    }
    $data = json_decode($json, true);
    if (!is_array($data)) throw new RuntimeException('Dữ liệu cấu hình giftcode không hợp lệ.');
    return $data;
}

/**
 * Update one catalog entry under a file lock. Atomic rename avoids partial JSON reads.
 * $entry=null removes the public metadata for a code that has been deleted/archived.
 */
function kp_giftcode_catalog_set($id, $entry)
{
    $id = (int)$id;
    if ($id <= 0) throw new RuntimeException('ID giftcode không hợp lệ.');
    $path = kp_giftcode_catalog_path();
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Không tạo được storage/giftcode.');
    }
    $lock = @fopen($dir . '/catalog.lock', 'c+');
    if (!$lock || !flock($lock, LOCK_EX)) {
        if ($lock) fclose($lock);
        throw new RuntimeException('Không thể khóa cấu hình giftcode.');
    }
    try {
        $data = kp_giftcode_catalog_read();
        if ($entry === null) {
            unset($data[(string)$id]);
        } else {
            $title = trim((string)($entry['title'] ?? ''));
            $description = trim((string)($entry['description'] ?? ''));
            if (strlen($title) > 150 || strlen($description) > 600) {
                throw new RuntimeException('Tên hoặc mô tả giftcode quá dài.');
            }
            $data[(string)$id] = [
                'public' => !empty($entry['public']),
                'title' => $title,
                'description' => $description,
            ];
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($json) || strlen($json) > 131072) {
            throw new RuntimeException('Cấu hình giftcode quá lớn.');
        }
        $tmp = tempnam($dir, 'gift-');
        if ($tmp === false) throw new RuntimeException('Không thể ghi cấu hình giftcode.');
        try {
            if (file_put_contents($tmp, $json, LOCK_EX) === false) {
                throw new RuntimeException('Không thể ghi cấu hình giftcode.');
            }
            @chmod($tmp, 0600);
            if (!@rename($tmp, $path)) {
                throw new RuntimeException('Không thể cập nhật cấu hình giftcode.');
            }
        } finally {
            if (is_file($tmp)) @unlink($tmp);
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Only game-usable AND admin-published codes are shown on the landing page. */
function kp_giftcode_public_list($pdo, $max = 8)
{
    $entries = kp_giftcode_catalog_read();
    $ids = [];
    foreach ($entries as $id => $entry) {
        if (ctype_digit((string)$id) && (int)$id > 0 && is_array($entry) && !empty($entry['public'])) {
            $ids[] = (int)$id;
        }
    }
    $ids = array_slice(array_values(array_unique($ids)), 0, 200);
    if (!$ids) return [];
    $holders = implode(',', array_fill(0, count($ids), '?'));
    $max = max(1, min(12, (int)$max));
    $st = $pdo->prepare('SELECT id, giftcode, xu, luong, luongLock, item, limit_use
                         FROM giftcode WHERE id IN (' . $holders . ')
                         AND type = 0 AND limit_use > 0 ORDER BY id DESC LIMIT ' . $max);
    $st->execute($ids);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $meta = $entries[(string)$row['id']] ?? [];
        $row['title'] = (string)($meta['title'] ?? '');
        $row['description'] = (string)($meta['description'] ?? '');
    }
    unset($row);
    return $rows;
}

/** Validate characters against the game's legacy SQL string parsing constraints. */
function kp_giftcode_valid_code($code)
{
    return is_string($code) && preg_match('/\\A[A-Za-z0-9_-]{4,50}\\z/D', $code) === 1;
}

/** No arbitrary item IDs/formats are invented by the website. */
function kp_giftcode_valid_item($item)
{
    return $item === '' || (strlen($item) <= 255 &&
        preg_match('/\\A[A-Za-z0-9_:, -]+\\z/D', $item) === 1);
}
