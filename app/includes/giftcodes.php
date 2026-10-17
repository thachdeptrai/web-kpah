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

function kp_giftcode_dbo_enabled()
{
    return function_exists('kp_config') && (bool)kp_config('giftcode.dbo_enabled', false);
}

function kp_giftcode_item_type_name($type)
{
    $names = [
        0=>'Áo', 1=>'Quần', 2=>'Nón', 3=>'Kiếm', 4=>'Đại đao', 5=>'Đũa',
        6=>'Búa', 7=>'Cung', 8=>'Nhẫn', 9=>'Dây chuyền', 10=>'Giày',
        11=>'Găng', 12=>'Ngọc', 13=>'Cuốc', 14=>'Giáp thú', 15=>'Hộ uyên thú',
        16=>'Nón thú', 17=>'Bàn đạp thú', 18=>'Yên cương thú', 19=>'Phi phong',
        21=>'Hộ vệ lệnh', 23=>'Xá tiêu lệnh', 24=>'Lệnh bài', 26=>'Trứng pet',
        28=>'Item pet',
    ];
    $type = (int)$type;
    return isset($names[$type]) ? $names[$type] : 'Loại '.$type;
}

function kp_giftcode_option_storage($id)
{
    $id = (int)$id;
    if ($id < 0 || $id > 140) return null;
    if ($id < 33) return ['group'=>'atb', 'index'=>$id, 'max'=>32767];
    if ($id < 43) return ['group'=>'newAtb', 'index'=>$id-33, 'max'=>127];
    if ($id < 58) return ['group'=>'addMoreLevelSkill', 'index'=>$id-43, 'max'=>127];
    if ($id < 61) return ['group'=>'lockAtb', 'index'=>$id-58, 'max'=>127];
    return ['group'=>'otherAtt', 'index'=>$id-61, 'max'=>32767];
}

/** Catalog is read from the same DB tables used by the Java server. */
function kp_giftcode_reward_catalog($pdo)
{
    $optionRows = $pdo->query('SELECT id, name, ispercent, idcolor, decript FROM data_attribute ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $options = [];
    $optionMap = [];
    foreach ($optionRows as $row) {
        $id = (int)$row['id'];
        $storage = kp_giftcode_option_storage($id);
        if ($storage === null) continue;
        $option = [
            'id'=>$id,
            'name'=>(string)$row['name'],
            'percent'=>(int)$row['ispercent'] > 0,
            'description'=>(string)$row['decript'],
            'group'=>$storage['group'],
            'index'=>$storage['index'],
            'min'=>1,
            'max'=>$storage['max'],
            'display_multiplier'=>($id === 33 || $id === 34) ? 1000 : 1,
        ];
        $options[] = $option;
        $optionMap[$id] = $option;
    }
    if (!$options) throw new RuntimeException('Bảng data_attribute đang trống.');

    $sql = 'SELECT id, name, type, style, he, gender, level, durable, xstart, nloan,'
         . ' atb0, atb1, atb2, atb3, atb4, atb5, atb6, atb7, atb8, atb9'
         . ' FROM data_item ORDER BY id';
    $itemRows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $items = [];
    $itemMap = [];
    foreach ($itemRows as $row) {
        $id = (int)$row['id'];
        $iconId = (int)$row['xstart'];
        $relative = 'res/icon/'.$iconId.'.png';
        $defaults = [];
        for ($i=0; $i<10; $i++) {
            $param = (int)$row['atb'.$i];
            if ($param !== 0 && isset($optionMap[$i])) {
                $defaults[] = ['id'=>$i, 'name'=>$optionMap[$i]['name'], 'param'=>$param];
            }
        }
        $item = [
            'id'=>$id,
            'name'=>(string)$row['name'],
            'type'=>(int)$row['type'],
            'type_name'=>kp_giftcode_item_type_name($row['type']),
            'level'=>(int)$row['level'],
            'gender'=>(int)$row['gender'],
            'he'=>(int)$row['he'],
            'durable'=>(int)$row['durable'],
            'icon_id'=>$iconId,
            'image'=>is_file(KP_ROOT.'/'.$relative) ? '/'.$relative : null,
            'default_options'=>$defaults,
        ];
        $items[] = $item;
        $itemMap[$id] = $item;
    }
    if (!$items) throw new RuntimeException('Bảng data_item đang trống.');
    return ['items'=>$items, 'options'=>$options, 'item_map'=>$itemMap, 'option_map'=>$optionMap];
}

function kp_giftcode_parse_rewards($value, array $itemMap = null)
{
    $result = ['rewards'=>[], 'legacy'=>[]];
    $value = trim((string)$value);
    if ($value === '' || strcasecmp($value, 'null') === 0) return $result;
    foreach (explode(',', $value) as $rawToken) {
        $token = trim($rawToken);
        if ($token === '') continue;
        $parts = explode(':', $token);
        if (count($parts) === 4 && $parts[0] === 'DB'
            && ctype_digit($parts[1]) && ctype_digit($parts[2]) && ctype_digit($parts[3])
            && ($itemMap === null || isset($itemMap[(int)$parts[1]]))) {
            $result['rewards'][] = [
                'item_id'=>(int)$parts[1], 'quantity'=>(int)$parts[2],
                'minutes'=>(int)$parts[3], 'lock'=>0, 'magic'=>0, 'options'=>[],
            ];
            continue;
        }
        $dbo = explode(':', $token, 7);
        if (count($dbo) === 7 && $dbo[0] === 'DBO'
            && ctype_digit($dbo[1]) && ctype_digit($dbo[2]) && ctype_digit($dbo[3])
            && ($dbo[4] === '0' || $dbo[4] === '1') && in_array($dbo[5], ['0','1','2'], true)
            && ($itemMap === null || isset($itemMap[(int)$dbo[1]]))) {
            $parsedOptions = [];
            $valid = true;
            foreach ($dbo[6] === '' ? [] : explode(';', $dbo[6]) as $pair) {
                $bits = explode('=', $pair, 2);
                if (count($bits) !== 2 || !ctype_digit($bits[0]) || !ctype_digit($bits[1])) {
                    $valid = false;
                    break;
                }
                $parsedOptions[] = ['id'=>(int)$bits[0], 'param'=>(int)$bits[1]];
            }
            if ($valid) {
                $result['rewards'][] = [
                    'item_id'=>(int)$dbo[1], 'quantity'=>(int)$dbo[2],
                    'minutes'=>(int)$dbo[3], 'lock'=>(int)$dbo[4],
                    'magic'=>(int)$dbo[5], 'options'=>$parsedOptions,
                ];
                continue;
            }
        }
        $result['legacy'][] = $token;
    }
    return $result;
}

function kp_giftcode_payload_int($value, $min, $max, $label)
{
    if (is_int($value)) $number = $value;
    elseif (is_string($value) && preg_match('/\\A[0-9]+\\z/D', $value)) $number = (int)$value;
    else throw new RuntimeException($label.' không hợp lệ.');
    if ($number < $min || $number > $max) throw new RuntimeException($label.' nằm ngoài giới hạn.');
    return $number;
}

/** Validate browser JSON against live item/option maps, then return canonical rewards. */
function kp_giftcode_validate_reward_payload($json, array $itemMap, array $optionMap, $dboEnabled)
{
    if (!is_string($json) || strlen($json) > 65535) throw new RuntimeException('Dữ liệu vật phẩm quá lớn.');
    $payload = json_decode($json === '' ? '[]' : $json, true);
    if (!is_array($payload) || array_values($payload) !== $payload) throw new RuntimeException('Danh sách vật phẩm không hợp lệ.');
    if (count($payload) > 30) throw new RuntimeException('Mỗi Giftcode tối đa 30 cấu hình vật phẩm.');
    $rewards = [];
    foreach ($payload as $index=>$raw) {
        if (!is_array($raw)) throw new RuntimeException('Vật phẩm thứ '.($index+1).' không hợp lệ.');
        $itemId = kp_giftcode_payload_int(isset($raw['item_id']) ? $raw['item_id'] : null, 1, 2147483647, 'ID vật phẩm');
        if (!isset($itemMap[$itemId])) throw new RuntimeException('Vật phẩm ID '.$itemId.' không tồn tại trong data_item.');
        $quantity = kp_giftcode_payload_int(isset($raw['quantity']) ? $raw['quantity'] : null, 1, 1000, 'Số lượng vật phẩm');
        $minutes = kp_giftcode_payload_int(isset($raw['minutes']) ? $raw['minutes'] : 0, 0, 2147483647, 'Thời hạn vật phẩm');
        $lock = kp_giftcode_payload_int(isset($raw['lock']) ? $raw['lock'] : 0, 0, 1, 'Trạng thái khóa');
        // GiftCode::AddItem only defines 0 = ma pháp and 1 = vật lý.
        $magic = kp_giftcode_payload_int(isset($raw['magic']) ? $raw['magic'] : 0, 0, 1, 'Hệ vật lý/ma pháp');
        $rawOptions = isset($raw['options']) ? $raw['options'] : [];
        if (!is_array($rawOptions) || array_values($rawOptions) !== $rawOptions || count($rawOptions) > 141) {
            throw new RuntimeException('Danh sách option của item ID '.$itemId.' không hợp lệ.');
        }
        $options = [];
        $seen = [];
        foreach ($rawOptions as $rawOption) {
            if (!is_array($rawOption)) throw new RuntimeException('Option của item ID '.$itemId.' không hợp lệ.');
            $optionId = kp_giftcode_payload_int(isset($rawOption['id']) ? $rawOption['id'] : null, 0, 140, 'ID option');
            if (!isset($optionMap[$optionId])) throw new RuntimeException('Option ID '.$optionId.' không tồn tại trong data_attribute.');
            if (isset($seen[$optionId])) throw new RuntimeException('Option ID '.$optionId.' bị lặp trong cùng vật phẩm.');
            $seen[$optionId] = true;
            $param = kp_giftcode_payload_int(isset($rawOption['param']) ? $rawOption['param'] : null, 1, (int)$optionMap[$optionId]['max'], 'Param option '.$optionId);
            $options[] = ['id'=>$optionId, 'param'=>$param];
        }
        usort($options, function ($a, $b) { return $a['id'] <=> $b['id']; });
        if (!$dboEnabled && ($lock !== 0 || $magic !== 0 || $options)) {
            throw new RuntimeException('Tùy chỉnh option đang khóa. Hãy patch game server rồi bật KPAH_GIFTCODE_DBO_ENABLED=1.');
        }
        $rewards[] = [
            'item_id'=>$itemId, 'quantity'=>$quantity, 'minutes'=>$minutes,
            'lock'=>$lock, 'magic'=>$magic, 'options'=>$options,
        ];
    }
    return $rewards;
}

function kp_giftcode_serialize_rewards(array $rewards)
{
    $tokens = [];
    foreach ($rewards as $reward) {
        if (!$reward['options'] && (int)$reward['lock'] === 0 && (int)$reward['magic'] === 0) {
            $tokens[] = 'DB:'.(int)$reward['item_id'].':'.(int)$reward['quantity'].':'.(int)$reward['minutes'];
            continue;
        }
        $pairs = [];
        foreach ($reward['options'] as $option) $pairs[] = (int)$option['id'].'='.(int)$option['param'];
        $tokens[] = 'DBO:'.(int)$reward['item_id'].':'.(int)$reward['quantity'].':'.(int)$reward['minutes']
            .':'.(int)$reward['lock'].':'.(int)$reward['magic'].':'.implode(';', $pairs);
    }
    return implode(',', $tokens);
}

function kp_giftcode_item_column_capacity($pdo)
{
    $row = $pdo->query("SHOW COLUMNS FROM giftcode LIKE 'item'")->fetch(PDO::FETCH_ASSOC);
    $type = strtolower((string)(isset($row['Type']) ? $row['Type'] : ''));
    if (preg_match('/\\Avarchar\\((\\d+)\\)/', $type, $match)) return (int)$match[1];
    if (strpos($type, 'tinytext') === 0) return 255;
    if (strpos($type, 'mediumtext') === 0) return 16777215;
    if (strpos($type, 'longtext') === 0) return 2147483647;
    if (strpos($type, 'text') === 0) return 65535;
    return 255;
}

/** Low-level syntax guard retained for legacy callers/tests. */
function kp_giftcode_valid_item($item)
{
    return $item === '' || (is_string($item) && strlen($item) <= 65535 &&
        preg_match('/\\A[A-Za-z0-9_:,;= -]+\\z/D', $item) === 1);
}
